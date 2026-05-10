<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if required tables exist
$salesExists     = $conn->query("SHOW TABLES LIKE 'sales'")->num_rows > 0;
$saleItemsExists = $conn->query("SHOW TABLES LIKE 'sale_items'")->num_rows > 0;

// Per-product sales data (last 12 months)
$items = [];
if ($salesExists && $saleItemsExists) {
    $r = $conn->query("
        SELECT
            si.product_id,
            si.description,
            p.code,
            c.category_name,
            SUM(si.quantity)                                AS total_qty,
            COUNT(DISTINCT YEARWEEK(s.sale_date, 1))        AS sale_weeks,
            COUNT(DISTINCT DATE_FORMAT(s.sale_date,'%Y-%m')) AS sale_months
        FROM sale_items si
        JOIN sales s          ON s.id = si.sale_id
        LEFT JOIN products p  ON si.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE si.line_type = 'parts'
        AND si.product_id IS NOT NULL
        AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY si.product_id, si.description, p.code, c.category_name
        ORDER BY total_qty DESC
    ");
    if ($r) while ($row = $r->fetch_assoc()) $items[] = $row;
}

// Monthly revenue totals (last 12 months)
$monthly = [];
if ($salesExists) {
    $r = $conn->query("
        SELECT
            DATE_FORMAT(sale_date, '%Y-%m')  AS month_key,
            DATE_FORMAT(sale_date, '%b %Y')  AS month_label,
            SUM(parts_total)                 AS parts_total,
            SUM(labor_total)                 AS labor_total,
            SUM(parts_total + labor_total)   AS grand_total,
            COUNT(*)                         AS sale_count
        FROM sales
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY month_key, month_label
        ORDER BY month_key ASC
    ");
    if ($r) while ($row = $r->fetch_assoc()) $monthly[] = $row;
}

echo json_encode([
    'success' => true,
    'items'   => $items,
    'monthly' => $monthly,
]);
$conn->close();
