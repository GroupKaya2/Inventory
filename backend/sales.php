<?php

ob_start();
session_start();


function send($data) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}


set_error_handler(function ($no, $msg) {
    send(['success' => false, 'message' => "Server error: $msg"]);
});

if (!isset($_SESSION['user_id'])) send(['success' => false, 'message' => 'Not logged in']);

require_once __DIR__ . '/db.php';

$action  = $_GET['action'] ?? '';
$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
$userId  = (int)$_SESSION['user_id'];

//SAVE SALE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) send(['success' => false, 'message' => 'Invalid request.']);

    $saleDate     = trim($input['sale_date']     ?? date('Y-m-d'));
    $customerName = trim($input['customer_name'] ?? '');
    $plateNumber  = trim($input['plate_number']  ?? '');
    $items        = $input['items'] ?? [];

    //Validate date
    if (!DateTime::createFromFormat('Y-m-d', $saleDate)) {
        $saleDate = date('Y-m-d');
    }


    $partsTotal = 0.0;
    $laborTotal = 0.0;
    $validItems = [];

    foreach ($items as $row) {
        $type   = strtolower(trim($row['type'] ?? 'parts'));
        $type   = in_array($type, ['parts', 'labor']) ? $type : 'parts';
        $qty    = max(1, (int)($row['quantity'] ?? 1));
        $amount = (float)($row['amount'] ?? 0);

        if ($amount <= 0) continue;

        if ($type === 'labor') {
            $laborTotal += $amount;
            $validItems[] = [
                'line_type'   => 'labor',
                'product_id'  => null,
                'description' => trim($row['description'] ?? 'Labor'),
                'quantity'    => 1,
                'unit_price'  => $amount,
                'amount'      => $amount,
            ];
        } else {
            $pid = (int)($row['product_id'] ?? 0);
            if ($pid <= 0) continue;

            $unitPrice  = (float)($row['unit_price'] ?? 0);
            if ($unitPrice <= 0) $unitPrice = $qty > 0 ? $amount / $qty : 0;
            $lineAmount = $amount > 0 ? $amount : $unitPrice * $qty;
            $partsTotal += $lineAmount;

            $validItems[] = [
                'line_type'   => 'parts',
                'product_id'  => $pid,
                'description' => trim($row['description'] ?? ''),
                'quantity'    => $qty,
                'unit_price'  => $unitPrice,
                'amount'      => $lineAmount,
            ];
        }
    }

    if (empty($validItems)) {
        send(['success' => false, 'message' => 'Add at least one item with a valid amount.']);
    }

    $conn->begin_transaction();

    try {
    
        $stmt = $conn->prepare("
            INSERT INTO sales (sale_date, customer_name, plate_number, parts_total, labor_total, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssddi', $saleDate, $customerName, $plateNumber, $partsTotal, $laborTotal, $userId);
        if (!$stmt->execute()) throw new Exception('Sale insert failed: ' . $stmt->error);
        $saleId = (int)$conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO sale_items (sale_id, line_type, product_id, description, quantity, unit_price, amount)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($validItems as $item) {
            $lt   = $item['line_type'];
            $pid  = $item['product_id'];
            $desc = $item['description'];
            $q    = $item['quantity'];
            $up   = $item['unit_price'];
            $am   = $item['amount'];
            $stmt->bind_param('iisiddd', $saleId, $lt, $pid, $desc, $q, $up, $am);
            if (!$stmt->execute()) throw new Exception('Item insert failed: ' . $stmt->error);
        }
        $stmt->close();

        //Deduct stock for parts
        $deducted = [];
        $stmt = $conn->prepare("
            INSERT INTO inventory_transactions (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by)
            VALUES (?, ?, ?, 'sale', ?, ?)
        ");
        foreach ($validItems as $item) {
            if ($item['line_type'] !== 'parts' || !$item['product_id']) continue;
            $pid       = (int)$item['product_id'];
            $qtyChange = -(int)$item['quantity'];
            $remarks   = "Sale #{$saleId}" . ($customerName ? " - {$customerName}" : '');
            $stmt->bind_param('isisi', $pid, $saleDate, $qtyChange, $remarks, $userId);
            if (!$stmt->execute()) throw new Exception('Stock deduction failed: ' . $stmt->error);
            $deducted[] = ['description' => $item['description'], 'qty' => $item['quantity'], 'product_id' => $pid];
        }
        $stmt->close();

        $conn->commit();

        //Fetch updated stock levels to show user
        $stockSummary = [];
        foreach ($deducted as $d) {
            $pid = (int)$d['product_id'];
            $row = $conn->query("SELECT description, current_stock, reorder_threshold FROM product_stock WHERE product_id = $pid")->fetch_assoc();
            if ($row) {
                $stockSummary[] = [
                    'description' => $row['description'],
                    'qty_removed' => $d['qty'],
                    'stock_left'  => (int)$row['current_stock'],
                    'low_stock'   => (int)$row['current_stock'] <= (int)$row['reorder_threshold'],
                ];
            }
        }

        send(['success' => true, 'message' => 'Sale saved!', 'sale_id' => $saleId, 'stock_summary' => $stockSummary]);

    } catch (Exception $e) {
        $conn->rollback();
        send(['success' => false, 'message' => $e->getMessage()]);
    }
}

//GET SALE DETAIL
if ($action === 'detail') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) send(['success' => false, 'message' => 'Invalid ID']);

    $stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $sale = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sale) send(['success' => false, 'message' => 'Sale not found.']);

    $stmt = $conn->prepare("
        SELECT si.*, p.code
        FROM sale_items si
        LEFT JOIN products p ON si.product_id = p.product_id
        WHERE si.sale_id = ?
        ORDER BY si.id
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    send(['success' => true, 'sale' => $sale, 'items' => $items]);
}

//DELETE SALE (owner only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    if (!$isOwner) send(['success' => false, 'message' => 'Owner only.']);

    $ids = !empty($_POST['ids'])
        ? json_decode($_POST['ids'], true)
        : [(int)($_POST['id'] ?? 0)];

    $ids = array_filter(array_map('intval', (array)$ids), fn($v) => $v > 0);
    if (empty($ids)) send(['success' => false, 'message' => 'No valid IDs.']);

    $deleted = 0;
    foreach ($ids as $id) {
        $conn->query("DELETE FROM sale_items WHERE sale_id = $id");
        $conn->query("DELETE FROM sales WHERE id = $id");
        if ($conn->affected_rows >= 0) $deleted++;
    }

    send(['success' => true, 'message' => "$deleted transaction(s) deleted."]);
}

send(['success' => false, 'message' => 'Unknown action.']);
