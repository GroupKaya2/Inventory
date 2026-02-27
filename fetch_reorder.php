<?php
// fetch_reorder.php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
include 'db.php';

$items = [];
$r = $conn->query("
    SELECT product_id, code, description, category_name,
           current_stock, reorder_threshold, unit
    FROM product_stock
    WHERE current_stock <= reorder_threshold
    ORDER BY current_stock ASC, description ASC
");
if ($r) while ($row = $r->fetch_assoc()) $items[] = $row;
echo json_encode(['success'=>true,'items'=>$items]);
$conn->close();