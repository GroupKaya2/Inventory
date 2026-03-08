<?php
/**
 * fetch_sales_for_forecast.php
 * Returns sales data for the forecasting tab in inventory.php
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include 'db.php';

// ── Check if tables exist ─────────────────────────────────
$salesExists = $conn->query("SHOW TABLES LIKE 'sales'")->num_rows > 0;
$saleItemsExists = $conn->query("SHOW TABLES LIKE 'sale_items'")->num_rows > 0;
$workOrdersExists = $conn->query("SHOW TABLES LIKE 'work_orders'")->num_rows > 0;

// ── 1. Per-product sales items (last 12 months) ───────────
$items = [];
if ($salesExists && $saleItemsExists) {
    $r = $conn->query("
        SELECT
            si.product_id,
            si.description,
            p.code,
            p.category_id,
            c.category_name,
            SUM(si.quantity)                                AS total_qty,
            COUNT(DISTINCT DATE(s.sale_date))               AS sale_days,
            COUNT(DISTINCT YEARWEEK(s.sale_date, 1))        AS sale_weeks,
            COUNT(DISTINCT DATE_FORMAT(s.sale_date,'%Y-%m')) AS sale_months,
            MIN(s.sale_date)                                AS first_sale,
            MAX(s.sale_date)                                AS last_sale
        FROM sale_items si
        JOIN sales s      ON s.id = si.sale_id
        LEFT JOIN products p  ON si.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE si.line_type = 'parts'
          AND si.product_id IS NOT NULL
          AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY si.product_id, si.description, p.code, p.category_id, c.category_name
        ORDER BY total_qty DESC
    ");
    if ($r) while ($row = $r->fetch_assoc()) $items[] = $row;
}

// ── 2. Monthly revenue totals (last 12 months) ────────────
$monthly = [];
if ($salesExists) {
    $r2 = $conn->query("
        SELECT
            DATE_FORMAT(sale_date, '%Y-%m')   AS month_key,
            DATE_FORMAT(sale_date, '%b %Y')   AS month_label,
            SUM(parts_total)                  AS parts_total,
            SUM(labor_total)                  AS labor_total,
            SUM(parts_total + labor_total)    AS grand_total,
            COUNT(*)                          AS sale_count
        FROM sales
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY month_key, month_label
        ORDER BY month_key ASC
    ");
    if ($r2) while ($row = $r2->fetch_assoc()) $monthly[] = $row;
}

// ── 3. Weekly sales (last 12 weeks) ──────────────────────
$weekly = [];
if ($salesExists) {
    $r3 = $conn->query("
        SELECT
            YEARWEEK(sale_date, 1)            AS week_key,
            MIN(sale_date)                    AS week_start,
            SUM(parts_total + labor_total)    AS total_revenue,
            COUNT(*)                          AS sale_count
        FROM sales
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
        GROUP BY week_key
        ORDER BY week_key ASC
    ");
    if ($r3) while ($row = $r3->fetch_assoc()) $weekly[] = $row;
}

// ── 4. Work orders by week (last 12 weeks) ────────────────
$workload = [];
if ($workOrdersExists) {
    $r4 = $conn->query("
        SELECT
            YEARWEEK(created_at, 1)           AS week_key,
            MIN(DATE(created_at))             AS week_start,
            COUNT(*)                          AS total_orders,
            SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status='open'      THEN 1 ELSE 0 END) AS open_orders,
            COALESCE(SUM(labor_amount), 0)    AS total_labor
        FROM work_orders
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
        GROUP BY week_key
        ORDER BY week_key ASC
    ");
    if ($r4) while ($row = $r4->fetch_assoc()) $workload[] = $row;
}

echo json_encode([
    'success'  => true,
    'items'    => $items,
    'monthly'  => $monthly,
    'weekly'   => $weekly,
    'workload' => $workload,
    'has_data' => [
        'items'    => count($items) > 0,
        'monthly'  => count($monthly) > 0,
        'weekly'   => count($weekly) > 0,
        'workload' => count($workload) > 0,
    ]
]);
$conn->close();