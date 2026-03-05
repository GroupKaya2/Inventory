<?php
// get_sale_detail.php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']);
exit;
}

include 'db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid ID']);
exit;
}

$saleStmt = $conn->prepare("SELECT * FROM sales WHERE id=?");
$saleStmt->bind_param('i', $id);
$saleStmt->execute();
$saleRes = $saleStmt->get_result();

if ($saleRes->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'Sale not found']);
exit;
}

$sale = $saleRes->fetch_assoc();
$saleStmt->close();

$itemStmt = $conn->prepare("SELECT si.*, p.code FROM sale_items si LEFT JOIN products p ON si.product_id=p.product_id WHERE si.sale_id=? ORDER BY si.id");
$itemStmt->bind_param('i', $id);
$itemStmt->execute();
$items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$itemStmt->close();

echo json_encode(['success'=>true,'sale'=>$sale,'items'=>$items]);

$conn->close();