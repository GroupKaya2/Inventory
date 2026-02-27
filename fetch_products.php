<?php
// fetch_products.php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
include 'db.php';

$result = $conn->query("
    SELECT product_id, code, description, unit, unit_cost, selling_price, margin,
           initial_quantity, reorder_threshold, current_stock, category_id, category_name
    FROM product_stock
    ORDER BY category_name, description
");
$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) $data[] = $row;
}
echo json_encode(['success' => true, 'data' => $data]);
$conn->close();