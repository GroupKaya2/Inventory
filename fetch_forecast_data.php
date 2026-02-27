<?php
// fetch_sales_for_forecast.php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
include 'db.php';

// Get sale_items with product info for the last 12 months
$items = [];
$r1 = $conn->query("
    SELECT si.product_id, si.line_type, si.quantity, si.description,
           p.code, DATE(si.created_at) AS sale_date
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.product_id
    WHERE si.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    ORDER BY si.created_at ASC
");
if ($r1) while ($row = $r1->fetch_assoc()) $items[] = $row;

// Get aggregated daily sales (last 12 months)
$sales = [];
$r2 = $conn->query("
    SELECT sale_date,
           SUM(parts_total) AS parts_total,
           SUM(labor_total) AS labor_total
    FROM sales
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY sale_date
    ORDER BY sale_date ASC
");
if ($r2) while ($row = $r2->fetch_assoc()) $sales[] = $row;

echo json_encode(['success'=>true, 'items'=>$items, 'sales'=>$sales]);
$conn->close();