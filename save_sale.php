<?php
ob_start();
session_start();

function sendJson($arr) {
    ob_end_clean();
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

set_error_handler(function($no, $str) {
    sendJson(['success' => false, 'message' => "PHP Error: $str"]);
});

if (empty($_SESSION['user_id'])) sendJson(['success' => false, 'message' => 'Unauthorized']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(['success' => false, 'message' => 'Invalid method']);

include __DIR__ . '/db.php';

$userId       = (int)$_SESSION['user_id'];
$input        = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) sendJson(['success' => false, 'message' => 'Invalid request data']);

$saleDate     = trim($input['sale_date']     ?? date('Y-m-d'));
$customerName = trim($input['customer_name'] ?? '');
$plateNumber  = trim($input['plate_number']  ?? '');
$items        = $input['items'] ?? [];

if (!DateTime::createFromFormat('Y-m-d', $saleDate)) {
    $saleDate = date('Y-m-d');
}

$partsTotal = 0.0;
$laborTotal = 0.0;
$validItems = [];

foreach ($items as $row) {
    $type   = strtolower(trim($row['type'] ?? 'parts'));
    if (!in_array($type, ['parts', 'labor'])) $type = 'parts';
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
    sendJson(['success' => false, 'message' => 'Add at least one item with a valid amount.']);
}

$conn->begin_transaction();
try {

    // 1. INSERT SALE HEADER
    $s1 = $conn->prepare(
        "INSERT INTO sales (sale_date, customer_name, plate_number, parts_total, labor_total, created_by)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    if (!$s1) throw new Exception('Prepare failed: ' . $conn->error);
    $s1->bind_param('sssddi', $saleDate, $customerName, $plateNumber, $partsTotal, $laborTotal, $userId);
    if (!$s1->execute()) throw new Exception('Sale insert failed: ' . $s1->error);
    $saleId = (int)$conn->insert_id;
    $s1->close();

    // 2. INSERT SALE ITEMS
    $s2 = $conn->prepare(
        "INSERT INTO sale_items (sale_id, line_type, product_id, description, quantity, unit_price, amount)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$s2) throw new Exception('Prepare items failed: ' . $conn->error);
    foreach ($validItems as $item) {
        $lt   = $item['line_type'];
        $pid  = $item['product_id'];
        $desc = $item['description'];
        $q    = $item['quantity'];
        $up   = $item['unit_price'];
        $am   = $item['amount'];
        $s2->bind_param('iisiddd', $saleId, $lt, $pid, $desc, $q, $up, $am);
        if (!$s2->execute()) throw new Exception('Item insert failed: ' . $s2->error);
    }
    $s2->close();

    // 3. DEDUCT STOCK (parts only)
    $s3 = $conn->prepare(
        "INSERT INTO inventory_transactions
             (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by)
         VALUES (?, ?, ?, 'sale', ?, ?)"
    );
    if (!$s3) throw new Exception('Prepare inventory failed: ' . $conn->error);
    $deducted = [];
    foreach ($validItems as $item) {
        if ($item['line_type'] !== 'parts' || !$item['product_id']) continue;
        $pid       = (int)$item['product_id'];
        $qtyChange = -(int)$item['quantity'];
        $remarks   = "Sale #$saleId" . ($customerName ? " - $customerName" : '');
        $s3->bind_param('isisi', $pid, $saleDate, $qtyChange, $remarks, $userId);
        if (!$s3->execute()) throw new Exception('Stock deduction failed (product ' . $pid . '): ' . $s3->error);
        $deducted[] = ['description' => $item['description'], 'qty_removed' => $item['quantity'], 'product_id' => $pid];
    }
    $s3->close();

    // 4. LOG LABOR AS WORK ORDER
    if ($laborTotal > 0) {
        $chk = $conn->query("SHOW TABLES LIKE 'work_orders'");
        if ($chk && $chk->num_rows > 0) {
            $woName = ($customerName ?: "Sale #$saleId") . ($plateNumber ? " ($plateNumber)" : '');
            $woDate = $saleDate . ' ' . date('H:i:s');
            $s4 = $conn->prepare("INSERT INTO work_orders (service_name, status, labor_amount, completed_at) VALUES (?, 'completed', ?, ?)");
            if ($s4) { $s4->bind_param('sds', $woName, $laborTotal, $woDate); $s4->execute(); $s4->close(); }
        }
    }

    $conn->commit();

    // 5. FETCH NEW STOCK LEVELS after commit
    $stockSummary = [];
    foreach ($deducted as $d) {
        $pid = (int)$d['product_id'];
        $r   = $conn->query("SELECT description, current_stock, reorder_threshold FROM product_stock WHERE product_id = $pid LIMIT 1");
        if ($r && $row = $r->fetch_assoc()) {
            $stockSummary[] = [
                'description' => $row['description'],
                'qty_removed' => $d['qty_removed'],
                'stock_left'  => (int)$row['current_stock'],
                'low_stock'   => (int)$row['current_stock'] <= (int)$row['reorder_threshold'],
            ];
        }
    }

    sendJson([
        'success'       => true,
        'message'       => 'Sale saved! Inventory updated.',
        'sale_id'       => $saleId,
        'stock_summary' => $stockSummary,
    ]);

} catch (Exception $e) {
    $conn->rollback();
    sendJson(['success' => false, 'message' => $e->getMessage()]);
}