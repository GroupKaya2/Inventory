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

    // Ledger of payments collected against a credit/split sale over time.
    // Deliberately NOT a duplicate of "accounts_payable" -- a receivable is
    // never manually created here. It's always derived live from `sales`
    // rows where payment_method is 'credit', or 'split' with a credit
    // portion; this table only tracks what's been collected against those.
    $conn->query("CREATE TABLE IF NOT EXISTS receivable_payments (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        sale_id          INT NOT NULL,
        payment_date     DATE NOT NULL,
        amount_paid      DECIMAL(12,2) NOT NULL DEFAULT 0,
        mode_of_payment  ENUM('cash','gcash','online_transfer') NOT NULL DEFAULT 'cash',
        reference_number VARCHAR(100) NULL,
        notes            TEXT NULL,
        created_by       INT NULL,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sale (sale_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ══════════════════════════════════
    FETCH  ── every sale that is (or partly is) on credit, with what's been
             collected against it so far and the resulting balance.
    ══════════════════════════════════ */
    if ($action === 'fetch') {
        $status = $_GET['status'] ?? '';
        $search = trim($_GET['search'] ?? '');

        $sql = "
            SELECT
                s.id,
                s.sale_date,
                s.customer_name,
                s.plate_number,
                s.reference_number AS invoice_number,
                s.payment_method,
                (s.parts_total + s.labor_total) AS grand_total,
                CASE
                    WHEN s.payment_method = 'split' THEN s.credit_amount
                    ELSE (s.parts_total + s.labor_total)
                END AS amount_owed,
                COALESCE(rp.total_paid, 0) AS amount_paid
            FROM sales s
            LEFT JOIN (
                SELECT sale_id, SUM(amount_paid) AS total_paid
                FROM receivable_payments
                GROUP BY sale_id
            ) rp ON rp.sale_id = s.id
            WHERE s.payment_method = 'credit'
               OR (s.payment_method = 'split' AND s.credit_amount > 0)
        ";

        if ($search !== '') {
            $like = '%' . $conn->real_escape_string($search) . '%';
            $sql .= " AND (s.customer_name LIKE '$like' OR s.reference_number LIKE '$like' OR s.plate_number LIKE '$like')";
        }

        $sql .= " ORDER BY s.sale_date DESC, s.id DESC";

        $result = $conn->query($sql);
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $owed = (float) $row['amount_owed'];
                $paid = (float) $row['amount_paid'];
                $balance = round($owed - $paid, 2);

                if ($balance <= 0.01) {
                    $rowStatus = 'paid';
                } elseif ($paid > 0) {
                    $rowStatus = 'partial';
                } else {
                    $rowStatus = 'unpaid';
                }

                $row['amount_owed']  = $owed;
                $row['amount_paid']  = $paid;
                $row['balance_due']  = max(0, $balance);
                $row['status']       = $rowStatus;

                if ($status !== '' && $status !== $rowStatus) continue;

                $rows[] = $row;
            }
        }

        $totals = [
            'total_owed'     => array_sum(array_column($rows, 'amount_owed')),
            'total_collected'=> array_sum(array_column($rows, 'amount_paid')),
            'total_balance'  => array_sum(array_column($rows, 'balance_due')),
            'unpaid_count'   => count(array_filter($rows, fn($r) => $r['status'] === 'unpaid')),
            'partial_count'  => count(array_filter($rows, fn($r) => $r['status'] === 'partial')),
            'paid_count'     => count(array_filter($rows, fn($r) => $r['status'] === 'paid')),
        ];

        echo json_encode(['success' => true, 'data' => $rows, 'totals' => $totals]);
        exit;
    }

    /* ══════════════════════════════════
    RECORD PAYMENT ── Part 2 of the workflow: collecting money against an
                     outstanding credit sale, partial or full.
    ══════════════════════════════════ */
    if ($action === 'record_payment') {
        $input = json_decode(file_get_contents('php://input'), true);

        $saleId   = (int) ($input['sale_id'] ?? 0);
        $payDate  = trim($input['payment_date'] ?? date('Y-m-d'));
        $amount   = (float) ($input['amount_paid'] ?? 0);
        $mop      = $input['mode_of_payment'] ?? 'cash';
        $refNo    = trim($input['reference_number'] ?? '');
        $notes    = trim($input['notes'] ?? '');

        if ($saleId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid sale.']);
            exit;
        }
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Amount paid must be greater than 0.']);
            exit;
        }
        $validMop = ['cash', 'gcash', 'online_transfer'];
        if (!in_array($mop, $validMop)) $mop = 'cash';

        // Recompute the real current balance server-side -- never trust the
        // client for how much is actually still owed.
        $saleStmt = $conn->prepare("SELECT customer_name, parts_total, labor_total, payment_method, credit_amount FROM sales WHERE id = ?");
        $saleStmt->bind_param('i', $saleId);
        $saleStmt->execute();
        $sale = $saleStmt->get_result()->fetch_assoc();
        $saleStmt->close();

        if (!$sale) {
            echo json_encode(['success' => false, 'message' => 'Sale not found.']);
            exit;
        }

        $owed = ($sale['payment_method'] === 'split')
            ? (float) $sale['credit_amount']
            : ((float) $sale['parts_total'] + (float) $sale['labor_total']);

        $paidStmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) AS paid FROM receivable_payments WHERE sale_id = ?");
        $paidStmt->bind_param('i', $saleId);
        $paidStmt->execute();
        $alreadyPaid = (float) $paidStmt->get_result()->fetch_assoc()['paid'];
        $paidStmt->close();

        $currentBalance = round($owed - $alreadyPaid, 2);

        if ($amount > $currentBalance + 0.01) {
            echo json_encode([
                'success' => false,
                'message' => "Amount exceeds the outstanding balance (₱" . number_format($currentBalance, 2) . " remaining).",
            ]);
            exit;
        }

        $stmt = $conn->prepare(
            "INSERT INTO receivable_payments (sale_id, payment_date, amount_paid, mode_of_payment, reference_number, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('isdsssi', $saleId, $payDate, $amount, $mop, $refNo, $notes, $userId);

        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            $stmt->close();

            $newBalance = round($currentBalance - $amount, 2);

            logAudit($conn, $userId, 'CREATE', 'receivable_payments', $newId, null, json_encode([
                'sale_id' => $saleId, 'customer_name' => $sale['customer_name'],
                'amount_paid' => $amount, 'mode_of_payment' => $mop, 'reference_number' => $refNo,
            ]));

            echo json_encode([
                'success'     => true,
                'message'     => 'Payment recorded.',
                'new_balance' => max(0, $newBalance),
                'status'      => $newBalance <= 0.01 ? 'paid' : 'partial',
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed: ' . $stmt->error]);
            $stmt->close();
        }
        exit;
    }

    /* Payment history for one specific sale (per-row "View History") */
    if ($action === 'payment_history') {
        $saleId = (int) ($_GET['sale_id'] ?? 0);
        if ($saleId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid sale ID.']);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT rp.*, u.name AS collected_by
            FROM receivable_payments rp
            LEFT JOIN users u ON rp.created_by = u.id
            WHERE rp.sale_id = ?
            ORDER BY rp.payment_date DESC, rp.id DESC
        ");
        $stmt->bind_param('i', $saleId);
        $stmt->execute();
        $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success' => true, 'data' => $payments]);
        exit;
    }

    /* All payments across all customers -- Part 3's "All Payments Received" report */
    if ($action === 'all_payments') {
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo   = $_GET['date_to'] ?? '';

        $sql = "
            SELECT rp.*, s.customer_name, s.reference_number AS invoice_number, u.name AS collected_by
            FROM receivable_payments rp
            LEFT JOIN sales s ON rp.sale_id = s.id
            LEFT JOIN users u ON rp.created_by = u.id
            WHERE 1=1
        ";
        if ($dateFrom !== '') $sql .= " AND rp.payment_date >= '" . $conn->real_escape_string($dateFrom) . "'";
        if ($dateTo !== '')   $sql .= " AND rp.payment_date <= '" . $conn->real_escape_string($dateTo) . "'";
        $sql .= " ORDER BY rp.payment_date DESC, rp.id DESC";

        $result = $conn->query($sql);
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) $rows[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $rows]);
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