<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
include 'db.php';

$productId = (int)($_POST['product_id'] ?? 0);
$quantity  = (int)($_POST['quantity']   ?? 0);
$remarks   = trim($_POST['remarks']     ?? 'Restock');
$userId    = (int)$_SESSION['user_id'];

if ($productId <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid product.']); exit; }
if ($quantity  <= 0) { echo json_encode(['success'=>false,'message'=>'Quantity must be greater than 0.']); exit; }

// Verify product exists
$chk = $conn->prepare("SELECT product_id, description FROM products WHERE product_id=?");
$chk->bind_param('i', $productId);
$chk->execute();
$product = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$product) { echo json_encode(['success'=>false,'message'=>'Product not found.']); exit; }

// Insert restock transaction (positive quantity = stock added)
$stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by) VALUES (?, CURDATE(), ?, 'restock', ?, ?)");
if (!$stmt) { echo json_encode(['success'=>false,'message'=>'DB error: '.$conn->error]); exit; }
$stmt->bind_param('iisi', $productId, $quantity, $remarks, $userId);

if ($stmt->execute()) {
    echo json_encode(['success'=>true,'message'=>'Saved successfully','qty_added'=>$quantity,'product'=>$product['description']]);
} else {
    echo json_encode(['success'=>false,'message'=>'Failed: '.$conn->error]);
}
$stmt->close();
$conn->close();