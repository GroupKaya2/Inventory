<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
$userId = (int) $_SESSION['user_id'];

/* Ensure payment_method column exists (safe to run on every request) */
$conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS payment_method ENUM('cash','gcash') NOT NULL DEFAULT 'cash'");

/* SAVE SALE */
if ($action === 'save') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
        exit;
    }

    $saleDate = trim($input['sale_date'] ?? date('Y-m-d'));
    $custName = trim($input['customer_name'] ?? '');
    $plateNum = trim($input['plate_number'] ?? '');
    $payMethod = in_array($input['payment_method'] ?? '', ['cash', 'gcash'])
        ? $input['payment_method'] : 'cash';
    $items = $input['items'] ?? [];
    $expenses = $input['expenses'] ?? [];

    /* Server-side Sunday guard */
    if (date('N', strtotime($saleDate)) == 7) {
        echo json_encode(['success' => false, 'message' => 'The shop is closed on Sundays.']);
        exit;
    }

    // Allow saving expenses without parts or labor
    if (empty($items) && empty($expenses)) {
        echo json_encode(['success' => false, 'message' => 'Please add at least one item or expense.']);
        exit;
    }

    $partsTotal = 0.0;
    $laborTotal = 0.0;
    foreach ($items as $item) {
        $amt = (float) ($item['amount'] ?? 0);
        $type = ($item['type'] ?? 'parts') === 'labor' ? 'labor' : 'parts';
        if ($type === 'labor')
            $laborTotal += $amt;
        else
            $partsTotal += $amt;
    }

    $conn->begin_transaction();
    try {
        /* Insert sale header */
        $stmt = $conn->prepare(
            "INSERT INTO sales
                (sale_date, customer_name, plate_number, parts_total, labor_total, payment_method, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'sssddsi',
            $saleDate,
            $custName,
            $plateNum,
            $partsTotal,
            $laborTotal,
            $payMethod,
            $userId
        );
        $stmt->execute();
        $saleId = $conn->insert_id;
        $stmt->close();

        $stockSummary = [];

        /* Insert line items */
        foreach ($items as $item) {
            $type = ($item['type'] ?? 'parts') === 'labor' ? 'labor' : 'parts';
            $productId = (int) ($item['product_id'] ?? 0);
            $desc = trim($item['description'] ?? '');
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $amount = (float) ($item['amount'] ?? 0);

            $si = $conn->prepare(
                "INSERT INTO sale_items
                    (sale_id, line_type, product_id, description, quantity, unit_price, amount)
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $pidParam = $productId ?: null;
            $si->bind_param('isiisdd', $saleId, $type, $pidParam, $desc, $qty, $unitPrice, $amount);
            $si->execute();
            $si->close();

            /* Deduct inventory for parts */
            if ($type === 'parts' && $productId > 0) {
                $it = $conn->prepare(
                    "INSERT INTO inventory_transactions
                        (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by)
                        VALUES (?, ?, ?, 'sale', ?, ?)"
                );
                $negQty = -$qty;
                $remarks = "Sale #$saleId" . ($custName ? " – $custName" : '');
                $it->bind_param('iissi', $productId, $saleDate, $negQty, $remarks, $userId);
                $it->execute();
                $it->close();

                /* Stock level check */
                $st = $conn->prepare(
                    "SELECT
                            COALESCE(ps.current_stock,
                                p.initial_quantity + COALESCE((
                                    SELECT SUM(it2.quantity_change)
                                    FROM inventory_transactions it2
                                    WHERE it2.product_id = p.product_id
                                ), 0)
                            ) AS current_stock,
                            p.description,
                            p.reorder_threshold
                        FROM products p
                        LEFT JOIN product_stock ps ON ps.product_id = p.product_id
                        WHERE p.product_id = ?"
                );
                $st->bind_param('i', $productId);
                $st->execute();
                $stockRow = $st->get_result()->fetch_assoc();
                $st->close();

                if ($stockRow) {
                    $left = (int) $stockRow['current_stock'];
                    $stockSummary[] = [
                        'description' => $stockRow['description'],
                        'qty_removed' => $qty,
                        'stock_left' => $left,
                        'low_stock' => $left <= (int) $stockRow['reorder_threshold'],
                    ];
                }
            }
        }

        /* Save inline expenses */
        if (!empty($expenses)) {
            $expStmt = $conn->prepare(
                "INSERT INTO expenses (expense_date, category, description, amount, created_by)
                    VALUES (?, 'Other', ?, ?, ?)"
            );
            foreach ($expenses as $exp) {
                $eDesc = trim($exp['description'] ?? '');
                $eAmount = (float) ($exp['amount'] ?? 0);
                if ($eDesc !== '' && $eAmount > 0) {
                    $expStmt->bind_param('ssdi', $saleDate, $eDesc, $eAmount, $userId);
                    $expStmt->execute();
                }
            }
            $expStmt->close();
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'sale_id' => $saleId,
            'payment_method' => $payMethod,
            'stock_summary' => $stockSummary,
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
    exit;
}

/*GET SALE DETAIL*/
if ($action === 'detail') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $sale = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$sale) {
        echo json_encode(['success' => false, 'message' => 'Sale not found.']);
        exit;
    }

    $si = $conn->prepare("SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id");
    $si->bind_param('i', $id);
    $si->execute();
    $items = $si->get_result()->fetch_all(MYSQLI_ASSOC);
    $si->close();

    echo json_encode(['success' => true, 'sale' => $sale, 'items' => $items]);
    exit;
}

/*DELETE SALE (owner only)*/
if ($action === 'delete') {
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only.']);
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit;
    }

    $conn->begin_transaction();
    try {
        $today = date('Y-m-d');
        $reverseRemark = "Sale #$id deleted";

        // Fetch old values for audit log before deletion
        $oldStmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
        $oldStmt->bind_param('i', $id);
        $oldStmt->execute();
        $oldResult = $oldStmt->get_result()->fetch_assoc();
        $oldStmt->close();

        $rows = $conn->query(
            "SELECT product_id, quantity FROM sale_items
                WHERE sale_id = $id AND line_type = 'parts' AND product_id IS NOT NULL"
        );
        while ($row = $rows->fetch_assoc()) {
            $pid = (int) $row['product_id'];
            $qty = (int) $row['quantity'];
            $conn->query(
                "INSERT INTO inventory_transactions
                    (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by)
                    VALUES ($pid, '$today', $qty, 'adjustment', '$reverseRemark', $userId)"
            );
        }

        $conn->query("DELETE FROM sale_items WHERE sale_id = $id");
        $conn->query("DELETE FROM sales WHERE id = $id");
        $conn->commit();
        
        // Log audit entry
        $oldValues = json_encode($oldResult);
        logAudit($conn, $userId, 'DELETE', 'sales', $id, $oldValues, null);
        
        echo json_encode(['success' => true, 'message' => 'Sale deleted.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);