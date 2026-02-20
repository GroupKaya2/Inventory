<?php
/**
 * Save a new sale transaction: creates sale, sale_items, inventory_transactions (for parts), work_order (for labor).
 * Auto-updates inventory for parts sold.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

include __DIR__ . '/db.php';

// Check and create sales tables if they don't exist
function ensureSalesTables($conn) {
    $checkSales = $conn->query("SHOW TABLES LIKE 'sales'");
    if ($checkSales->num_rows == 0) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $conn->query("CREATE TABLE sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_date DATE NOT NULL,
            customer_name VARCHAR(255) NOT NULL DEFAULT '',
            plate_number VARCHAR(50) NOT NULL DEFAULT '',
            parts_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            labor_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )");
        $conn->query("CREATE TABLE sale_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT NOT NULL,
            line_type ENUM('parts','labor') NOT NULL,
            product_id INT NULL,
            description VARCHAR(255) NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL
        )");
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    }
}
ensureSalesTables($conn);

$userId = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$saleDate = trim($input['sale_date'] ?? '');
$customerName = trim($input['customer_name'] ?? '');
$plateNumber = trim($input['plate_number'] ?? '');
$items = $input['items'] ?? [];

if (!$saleDate) {
    $saleDate = date('Y-m-d');
} else {
    $d = DateTime::createFromFormat('Y-m-d', $saleDate);
    if (!$d) $d = DateTime::createFromFormat('d/m/Y', $saleDate);
    if ($d) $saleDate = $d->format('Y-m-d');
}

$partsTotal = 0;
$laborTotal = 0;
$validItems = [];

foreach ($items as $row) {
    $type = strtolower(trim($row['type'] ?? 'parts'));
    if (!in_array($type, ['parts', 'labor'])) $type = 'parts';
    $qty = (int)($row['quantity'] ?? 1);
    $amount = (float)($row['amount'] ?? 0);
    if ($amount <= 0 && $qty <= 0) continue;

    if ($type === 'labor') {
        $laborTotal += $amount;
        $validItems[] = [
            'line_type' => 'labor',
            'product_id' => null,
            'description' => trim($row['description'] ?? 'Labor'),
            'quantity' => max(1, $qty),
            'unit_price' => $amount,
            'amount' => $amount,
        ];
    } else {
        $productId = (int)($row['product_id'] ?? 0);
        $description = trim($row['description'] ?? '');
        if ($productId <= 0) continue;
        $unitPrice = (float)($row['unit_price'] ?? $row['amount'] ?? 0);
        if ($unitPrice <= 0 && $amount > 0) $unitPrice = $qty > 0 ? $amount / $qty : $amount;
        $lineAmount = $amount > 0 ? $amount : $unitPrice * $qty;
        $partsTotal += $lineAmount;
        $validItems[] = [
            'line_type' => 'parts',
            'product_id' => $productId,
            'description' => $description,
            'quantity' => max(1, $qty),
            'unit_price' => $unitPrice,
            'amount' => $lineAmount,
        ];
    }
}

if (empty($validItems)) {
    echo json_encode(['success' => false, 'message' => 'Add at least one item (Parts or Labor).']);
    exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO sales (sale_date, customer_name, plate_number, parts_total, labor_total, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception('DB prepare failed: ' . $conn->error);
    $stmt->bind_param('sssddi', $saleDate, $customerName, $plateNumber, $partsTotal, $laborTotal, $userId);
    if (!$stmt->execute()) throw new Exception('Failed to insert sale.');
    $saleId = (int)$conn->insert_id;
    $stmt->close();

    $insItem = $conn->prepare("INSERT INTO sale_items (sale_id, line_type, product_id, description, quantity, unit_price, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$insItem) throw new Exception('DB prepare sale_items failed.');

    foreach ($validItems as $item) {
        $pid = $item['product_id'];
        $desc = $item['description'];
        $q = $item['quantity'];
        $up = $item['unit_price'];
        $am = $item['amount'];
        $insItem->bind_param('isisddd', $saleId, $item['line_type'], $pid, $desc, $q, $up, $am);
        if (!$insItem->execute()) throw new Exception('Failed to insert sale item.');
    }
    $insItem->close();

    // Inventory: negative transaction for each parts line
    $invStmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by) VALUES (?, ?, ?, 'sale', ?, ?)");
    if (!$invStmt) throw new Exception('DB prepare inventory failed.');
    $affectedProductIds = [];
    foreach ($validItems as $item) {
        if ($item['line_type'] !== 'parts' || $item['product_id'] <= 0) continue;
        $qtyChange = - (int)$item['quantity'];
        $remarks = "Sale #{$saleId}";
        $invStmt->bind_param('iissi', $item['product_id'], $saleDate, $qtyChange, $remarks, $userId);
        if (!$invStmt->execute()) throw new Exception('Failed to update inventory.');
        $affectedProductIds[] = $item['product_id'];
    }
    $invStmt->close();

    // Labor: one completed work_order so dashboard labor revenue includes it (if table exists)
    if ($laborTotal > 0) {
        $hasWO = @$conn->query("SHOW TABLES LIKE 'work_orders'")->num_rows > 0;
        if ($hasWO) {
            $woService = 'Sale labor #' . $saleId;
            $woDate = $saleDate . ' ' . date('H:i:s');
            $woStmt = $conn->prepare("INSERT INTO work_orders (service_name, status, labor_amount, completed_at) VALUES (?, 'completed', ?, ?)");
            if ($woStmt) {
                $woStmt->bind_param('sds', $woService, $laborTotal, $woDate);
                $woStmt->execute();
                $woStmt->close();
            }
        }
    }

    $conn->commit();

    // Check for low stock alerts and send SMS
    if (!empty($affectedProductIds)) {
        require_once __DIR__ . '/sms_service.php';
        $smsService = new SMSService($conn);
        $smsService->checkAndSendLowStockAlerts($conn, $affectedProductIds);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Transaction saved. Inventory updated.',
        'sale_id' => $saleId,
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
