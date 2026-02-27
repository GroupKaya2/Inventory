<?php
session_start();

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']);
exit;
}

include 'db.php';

$productId = (int)($_POST['product_id'] ?? 0);
$qty       = (int)($_POST['quantity'] ?? 0);
$remarks   = trim($_POST['remarks'] ?? 'Restock');
$userId    = (int)$_SESSION['user_id'];
$today     = date('Y-m-d');

if ($productId <= 0 || $qty <= 0) {
    echo json_encode(['success'=>false,'message'=>'Invalid product or quantity.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by) VALUES (?,?,?,'restock',?,?)");
$stmt->bind_param('iiisi', $productId, $today, $qty, $remarks, $userId);
if ($stmt->execute()) {
    // Get new stock
    $newStock = $conn->query("SELECT current_stock FROM product_stock WHERE product_id=$productId")->fetch_assoc()['current_stock'] ?? '?';
    echo json_encode(['success'=>true,'message'=>"Restock recorded. New stock: $newStock",'new_stock'=>$newStock]);
} else {
    echo json_encode(['success'=>false,'message'=>'Failed: '.$conn->error]);
}
$stmt->close(); $conn->close();