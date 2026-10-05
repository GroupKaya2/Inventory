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

    // Same accounts_payable table Accounts Payable already uses -- a Purchase
    // Entry IS a supplier invoice, just entered with line items instead of a
    // single lump total.
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

    // Line items for a purchase invoice -- each one is a real, existing
    // product so restocking is unambiguous. Kept even after the invoice is
    // paid off, as the permanent record of what was actually received.
    $conn->query("CREATE TABLE IF NOT EXISTS purchase_items (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        ap_id         INT NOT NULL,
        product_id    INT NOT NULL,
        description   VARCHAR(255) NOT NULL,
        quantity      INT NOT NULL,
        unit_cost     DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ap_id) REFERENCES accounts_payable(id) ON DELETE CASCADE,
        INDEX idx_ap (ap_id),
        INDEX idx_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ══════════════════════════════════
    SAVE  ── the whole workflow in one atomic transaction:
            1. Create the Accounts Payable record (what we owe the supplier)
            2. Log each line item
            3. Update product cost if it changed
            4. Increase stock via inventory_transactions
    ══════════════════════════════════ */
    if ($action === 'save') {
        $input = json_decode(file_get_contents('php://input'), true);

        $supplier  = trim($input['supplier_name']  ?? '');
        $invoiceNo = trim($input['invoice_number'] ?? '');
        $invDate   = trim($input['invoice_date']   ?? date('Y-m-d'));
        $dueDate   = trim($input['due_date']       ?? '') ?: null;
        $mop       = $input['mode_of_payment']     ?? 'credit';
        $amountPaidInput = max(0, (float) ($input['amount_paid'] ?? 0));
        $notes     = trim($input['notes'] ?? '');
        $items     = $input['items'] ?? [];

        if ($supplier === '') {
            echo json_encode(['success' => false, 'message' => 'Supplier name is required.']);
            exit;
        }
        if (empty($items) || !is_array($items)) {
            echo json_encode(['success' => false, 'message' => 'Add at least one item.']);
            exit;
        }

        $validMop = ['cash', 'gcash', 'credit', 'bank_transfer', 'check'];
        if (!in_array($mop, $validMop)) $mop = 'credit';

        // Validate + recompute every line server-side -- never trust the
        // client's math for money or stock.
        $cleanItems = [];
        $grandTotal = 0.0;
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty       = (int) ($item['quantity'] ?? 0);
            $unitCost  = (float) ($item['unit_cost'] ?? 0);

            if ($productId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Every item must be matched to an existing product.']);
                exit;
            }
            if ($qty <= 0) {
                echo json_encode(['success' => false, 'message' => 'Quantity must be greater than 0 for every item.']);
                exit;
            }
            if ($unitCost < 0) {
                echo json_encode(['success' => false, 'message' => 'Unit cost cannot be negative.']);
                exit;
            }

            $pStmt = $conn->prepare("SELECT product_id, description, unit_cost FROM products WHERE product_id = ?");
            $pStmt->bind_param('i', $productId);
            $pStmt->execute();
            $product = $pStmt->get_result()->fetch_assoc();
            $pStmt->close();

            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'One of the selected products no longer exists.']);
                exit;
            }

            $lineTotal = $qty * $unitCost;
            $grandTotal += $lineTotal;

            $cleanItems[] = [
                'product_id'  => $productId,
                'description' => $product['description'],
                'quantity'    => $qty,
                'unit_cost'   => $unitCost,
                'old_cost'    => (float) $product['unit_cost'],
                'total'       => $lineTotal,
            ];
        }

        $amountPaid = min($amountPaidInput, $grandTotal);
        $status = ($amountPaid >= $grandTotal && $grandTotal > 0) ? 'paid' : 'unpaid';

        $conn->begin_transaction();
        try {
            /* 1. Accounts Payable header */
            $apStmt = $conn->prepare(
                "INSERT INTO accounts_payable
                    (supplier_name, invoice_number, invoice_date, due_date, mode_of_payment, total_amount, amount_paid, status, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $apStmt->bind_param(
                'sssssddssi',
                $supplier, $invoiceNo, $invDate, $dueDate, $mop,
                $grandTotal, $amountPaid, $status, $notes, $userId
            );
            $apStmt->execute();
            $apId = $conn->insert_id;
            $apStmt->close();

            $today = date('Y-m-d');
            $refLabel = 'Invoice' . ($invoiceNo !== '' ? " #$invoiceNo" : '') . " – $supplier";

            foreach ($cleanItems as $item) {
                /* 2. Line item record */
                $liStmt = $conn->prepare(
                    "INSERT INTO purchase_items (ap_id, product_id, description, quantity, unit_cost, total_amount)
                        VALUES (?, ?, ?, ?, ?, ?)"
                );
                $liStmt->bind_param(
                    'iisidd',
                    $apId, $item['product_id'], $item['description'],
                    $item['quantity'], $item['unit_cost'], $item['total']
                );
                $liStmt->execute();
                $liStmt->close();

                /* 3. Update product cost if the supplier's price changed */
                if (abs($item['unit_cost'] - $item['old_cost']) > 0.001) {
                    $costStmt = $conn->prepare("UPDATE products SET unit_cost = ? WHERE product_id = ?");
                    $costStmt->bind_param('di', $item['unit_cost'], $item['product_id']);
                    $costStmt->execute();
                    $costStmt->close();

                    logAudit(
                        $conn, $userId, 'UPDATE', 'products', $item['product_id'],
                        json_encode(['unit_cost' => $item['old_cost']]),
                        json_encode(['unit_cost' => $item['unit_cost'], 'note' => "Cost updated via Purchase Entry ($refLabel)"])
                    );
                }

                /* 4. Increase stock */
                $itStmt = $conn->prepare(
                    "INSERT INTO inventory_transactions
                        (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by)
                        VALUES (?, ?, ?, 'restock', ?, ?)"
                );
                $remarks = "$refLabel — {$item['quantity']} units received";
                $itStmt->bind_param('isisi', $item['product_id'], $today, $item['quantity'], $remarks, $userId);
                $itStmt->execute();
                $itStmt->close();
            }

            logAudit(
                $conn, $userId, 'CREATE', 'accounts_payable', $apId, null,
                json_encode(['supplier_name' => $supplier, 'invoice_number' => $invoiceNo, 'total_amount' => $grandTotal, 'item_count' => count($cleanItems)])
            );

            $conn->commit();
            echo json_encode([
                'success'      => true,
                'message'      => 'Purchase recorded successfully.',
                'ap_id'        => $apId,
                'total_amount' => $grandTotal,
                'item_count'   => count($cleanItems),
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
        exit;
    }

    /* Fetch line items for a past purchase (used by Accounts Payable's
       future "view items" if wired up, and handy for audit/debugging). */
    if ($action === 'detail') {
        $apId = (int) ($_GET['ap_id'] ?? 0);
        if ($apId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT * FROM purchase_items WHERE ap_id = ? ORDER BY id");
        $stmt->bind_param('i', $apId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success' => true, 'items' => $items]);
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
