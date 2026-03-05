<?php
// get_product.php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']);
exit;
}

include 'db.php';

$id = (int)($_GET['product_id'] ?? 0);
if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid ID']);
exit;
}

$stmt = $conn->prepare("SELECT ps.* FROM product_stock ps WHERE ps.product_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res  = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['success'=>false,'message'=>'Product not found']);
exit;
}
echo json_encode(['success'=>true,'data'=>$res->fetch_assoc()]);
$stmt->close(); $conn->close();