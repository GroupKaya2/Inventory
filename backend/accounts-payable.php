    <?php
    session_start();
    error_reporting(0);
    ini_set('display_errors', '0');
    ob_start();
    header('Content-Type: application/json');
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/audit.php';
    ob_clean();

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
    $userId  = (int) $_SESSION['user_id'];
    $action  = $_GET['action'] ?? $_POST['action'] ?? '';

    try {

        $conn->query("CREATE TABLE IF NOT EXISTS accounts_payable (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            supplier_name   VARCHAR(150) NOT NULL,
            invoice_number  VARCHAR(100) NULL,
            invoice_date    DATE NOT NULL,
            due_date        DATE NULL,
            mode_of_payment ENUM('cash','gcash','credit','bank_transfer','check') NOT NULL DEFAULT 'credit',
            total_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
            amount_paid     DECIMAL(12,2) NOT NULL DEFAULT 0,
            balance_due     DECIMAL(12,2) GENERATED ALWAYS AS (total_amount - amount_paid) STORED,
            status          ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
            notes           TEXT NULL,
            created_by      INT NULL,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_due_date (due_date),
            INDEX idx_supplier (supplier_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Patch an older/pre-existing table on the live server so a schema
        // mismatch never throws instead of just silently having the columns.
        $conn->query("ALTER TABLE accounts_payable ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(100) NULL");
        $conn->query("ALTER TABLE accounts_payable ADD COLUMN IF NOT EXISTS due_date DATE NULL");
        $conn->query("ALTER TABLE accounts_payable ADD COLUMN IF NOT EXISTS mode_of_payment ENUM('cash','gcash','credit','bank_transfer','check') NOT NULL DEFAULT 'credit'");
        $conn->query("ALTER TABLE accounts_payable ADD COLUMN IF NOT EXISTS notes TEXT NULL");
        $conn->query("ALTER TABLE accounts_payable ADD COLUMN IF NOT EXISTS created_by INT NULL");

        if ($action === 'fetch') {
            $status = $_GET['status'] ?? '';
            $search = trim($_GET['search'] ?? '');

            $where = [];
            $params = [];
            $types  = '';

            if ($status !== '') {
                $where[]  = 'status = ?';
                $params[] = $status;
                $types   .= 's';
            }
            if ($search !== '') {
                $where[]  = '(supplier_name LIKE ? OR invoice_number LIKE ?)';
                $like      = '%' . $search . '%';
                $params[] = $like;
                $params[] = $like;
                $types   .= 'ss';
            }

            $sql = 'SELECT * FROM accounts_payable';
            if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
            $sql .= ' ORDER BY due_date ASC, id DESC';

            $stmt = $conn->prepare($sql);
            if ($params) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $totals = $conn->query("SELECT
                COUNT(*) AS total_records,
                SUM(total_amount) AS total_amount,
                SUM(amount_paid) AS total_paid,
                SUM(balance_due) AS total_balance,
                SUM(status = 'unpaid') AS unpaid_count,
                SUM(status = 'paid') AS paid_count
                FROM accounts_payable")->fetch_assoc();

            echo json_encode(['success' => true, 'data' => $rows, 'totals' => $totals]);
            exit;
        }

        if ($action === 'export') {
            // Streams a raw CSV instead of JSON -- clear the output buffer and
            // override the Content-Type set at the top of this file.
            while (ob_get_level() > 0) { ob_end_clean(); }

            $rows = $conn->query("SELECT * FROM accounts_payable ORDER BY due_date ASC, id DESC");

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="accounts_payable_' . date('Y-m-d') . '.csv"');

            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Supplier', 'Invoice #', 'Invoice Date', 'Due Date', 'Mode of Payment',
                'Total Amount', 'Amount Paid', 'Balance Due', 'Status', 'Notes',
            ]);
            if ($rows) {
                while ($row = $rows->fetch_assoc()) {
                    fputcsv($out, [
                        $row['supplier_name'],
                        $row['invoice_number'],
                        $row['invoice_date'],
                        $row['due_date'],
                        ucwords(str_replace('_', ' ', $row['mode_of_payment'])),
                        $row['total_amount'],
                        $row['amount_paid'],
                        $row['balance_due'],
                        ucfirst($row['status']),
                        $row['notes'],
                    ]);
                }
            }
            fclose($out);
            $conn->close();
            exit;
        }

        if ($action === 'add') {
            $input = json_decode(file_get_contents('php://input'), true);

            $supplier   = trim($input['supplier_name']   ?? '');
            $invoice    = trim($input['invoice_number']  ?? '');
            $invDate    = trim($input['invoice_date']    ?? date('Y-m-d'));
            $dueDate    = trim($input['due_date']        ?? '') ?: null;
            $mop        = $input['mode_of_payment']      ?? 'credit';
            $total      = (float) ($input['total_amount']  ?? 0);
            $paid       = (float) ($input['amount_paid']   ?? 0);
            $notes      = trim($input['notes']           ?? '');

            if ($supplier === '') {
                echo json_encode(['success' => false, 'message' => 'Supplier name is required.']);
                exit;
            }
            if ($total <= 0) {
                echo json_encode(['success' => false, 'message' => 'Total amount must be greater than 0.']);
                exit;
            }
            if ($paid > $total) {
                echo json_encode(['success' => false, 'message' => 'Amount paid cannot exceed total amount.']);
                exit;
            }

            $status = ($paid >= $total) ? 'paid' : 'unpaid';

            $validMop = ['cash', 'gcash', 'credit', 'bank_transfer', 'check'];
            if (!in_array($mop, $validMop)) $mop = 'credit';

            $stmt = $conn->prepare("INSERT INTO accounts_payable
                (supplier_name, invoice_number, invoice_date, due_date, mode_of_payment, total_amount, amount_paid, status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssssddssi', $supplier, $invoice, $invDate, $dueDate, $mop, $total, $paid, $status, $notes, $userId);
            $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();

            $newStmt = $conn->prepare("SELECT * FROM accounts_payable WHERE id = ?");
            $newStmt->bind_param('i', $newId);
            $newStmt->execute();
            $newRow = $newStmt->get_result()->fetch_assoc();
            $newStmt->close();
            logAudit($conn, $userId, 'CREATE', 'accounts_payable', $newId, null, json_encode($newRow));

            echo json_encode(['success' => true, 'message' => 'Invoice recorded successfully.', 'id' => $newId]);
            exit;
        }

        if ($action === 'update') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id    = (int) ($input['id'] ?? 0);

            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
                exit;
            }

            $oldStmt = $conn->prepare("SELECT * FROM accounts_payable WHERE id = ?");
            $oldStmt->bind_param('i', $id);
            $oldStmt->execute();
            $old = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();
            if (!$old) {
                echo json_encode(['success' => false, 'message' => 'Record not found.']);
                exit;
            }

            $supplier = trim($input['supplier_name']   ?? $old['supplier_name']);
            $invoice  = trim($input['invoice_number']  ?? $old['invoice_number']);
            $invDate  = trim($input['invoice_date']    ?? $old['invoice_date']);
            $dueDate  = trim($input['due_date']        ?? $old['due_date']) ?: null;
            $mop      = $input['mode_of_payment']      ?? $old['mode_of_payment'];
            $total    = (float) ($input['total_amount']  ?? $old['total_amount']);
            $paid     = (float) ($input['amount_paid']   ?? $old['amount_paid']);
            $notes    = trim($input['notes']           ?? $old['notes']);

            if ($paid > $total) {
                echo json_encode(['success' => false, 'message' => 'Amount paid cannot exceed total amount.']);
                exit;
            }

            $status = ($paid >= $total) ? 'paid' : 'unpaid';

            $stmt = $conn->prepare("UPDATE accounts_payable SET
                supplier_name=?, invoice_number=?, invoice_date=?, due_date=?,
                mode_of_payment=?, total_amount=?, amount_paid=?, status=?, notes=?
                WHERE id=?");
            $stmt->bind_param('sssssddssi', $supplier, $invoice, $invDate, $dueDate, $mop, $total, $paid, $status, $notes, $id);
            $stmt->execute();
            $stmt->close();

            logAudit($conn, $userId, 'UPDATE', 'accounts_payable', $id, json_encode($old), json_encode($input));
            echo json_encode(['success' => true, 'message' => 'Invoice updated successfully.']);
            exit;
        }

        if ($action === 'mark_paid') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
                exit;
            }

            $oldStmt = $conn->prepare("SELECT * FROM accounts_payable WHERE id = ?");
            $oldStmt->bind_param('i', $id);
            $oldStmt->execute();
            $old = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();

            if (!$old) {
                echo json_encode(['success' => false, 'message' => 'Record not found.']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE accounts_payable SET amount_paid = total_amount, status = 'paid' WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            logAudit($conn, $userId, 'UPDATE', 'accounts_payable', $id, json_encode($old), json_encode(['status' => 'paid', 'amount_paid' => $old['total_amount']]));
            echo json_encode(['success' => true, 'message' => 'Marked as paid.']);
            exit;
        }

        if ($action === 'delete') {
            if (!$isOwner) {
                echo json_encode(['success' => false, 'message' => 'Owner only.']);
                exit;
            }

            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
                exit;
            }

            $oldStmt = $conn->prepare("SELECT * FROM accounts_payable WHERE id = ?");
            $oldStmt->bind_param('i', $id);
            $oldStmt->execute();
            $old = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();

            $del = $conn->prepare("DELETE FROM accounts_payable WHERE id = ?");
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();

            logAudit($conn, $userId, 'DELETE', 'accounts_payable', $id, json_encode($old), null);

            echo json_encode(['success' => true, 'message' => 'Record deleted.']);
            exit;
        }

        if ($action === 'bulk_delete') {
            if (!$isOwner) {
                echo json_encode(['success' => false, 'message' => 'Owner only.']);
                exit;
            }

            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                echo json_encode(['success' => false, 'message' => 'No records selected.']);
                exit;
            }
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
            if (empty($ids)) {
                echo json_encode(['success' => false, 'message' => 'No valid IDs provided.']);
                exit;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));

            // Fetch old values first so each deletion is still individually audit-logged
            $oldStmt = $conn->prepare("SELECT * FROM accounts_payable WHERE id IN ($placeholders)");
            $oldStmt->bind_param($types, ...$ids);
            $oldStmt->execute();
            $oldRows = $oldStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $oldStmt->close();

            $del = $conn->prepare("DELETE FROM accounts_payable WHERE id IN ($placeholders)");
            $del->bind_param($types, ...$ids);

            if ($del->execute()) {
                $deleted = $del->affected_rows;
                $del->close();
                foreach ($oldRows as $old) {
                    logAudit($conn, $userId, 'DELETE', 'accounts_payable', (int) $old['id'], json_encode($old), null);
                }
                echo json_encode(['success' => true, 'deleted' => $deleted]);
            } else {
                $err = $conn->error;
                $del->close();
                echo json_encode(['success' => false, 'message' => 'Bulk delete failed: ' . $err]);
            }
            exit;
        }

        if ($action === 'get') {
            $id = (int) ($_GET['id'] ?? 0);
            $stmt = $conn->prepare("SELECT * FROM accounts_payable WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            echo json_encode($row ? ['success' => true, 'data' => $row] : ['success' => false, 'message' => 'Not found.']);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        $conn->close();

    } catch (\Throwable $e) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }