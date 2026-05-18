<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ─── Constants (matching the capstone document) ───────────────────────────────
define('LEAD_TIME_DAYS', 5);   // Supplier lead time in days
define('SAFETY_STOCK',    3);   // Default safety stock units
define('DAYS_PER_MONTH', 30);   // Used for lead-time conversion

// ─── Check tables ─────────────────────────────────────────────────────────────
$salesExists     = $conn->query("SHOW TABLES LIKE 'sales'")->num_rows     > 0;
$saleItemsExists = $conn->query("SHOW TABLES LIKE 'sale_items'")->num_rows > 0;

// ─── Per-product: quantity sold each of the last 3 calendar months ────────────
//     We need month-by-month breakdown, not just total, so we can compute
//     a true 3-month moving average.
$items = [];

if ($salesExists && $saleItemsExists) {

    // Build the three month labels we need: current month minus 0,1,2
    $months = [];
    for ($i = 2; $i >= 0; $i--) {
        $months[] = date('Y-m', strtotime("-$i months"));
    }

    // Fetch qty sold per product per month for those 3 months
    $monthList = "'" . implode("','", $months) . "'";
    $r = $conn->query("
        SELECT
            si.product_id,
            p.description,
            p.code,
            c.category_name,
            DATE_FORMAT(s.sale_date, '%Y-%m')  AS sale_month,
            SUM(si.quantity)                   AS qty_sold,
            COALESCE(ps.current_stock, 0)      AS current_stock,
            COALESCE(p.reorder_threshold, 5)   AS reorder_threshold
        FROM sale_items si
        JOIN sales s           ON s.id = si.sale_id
        LEFT JOIN products p   ON si.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN product_stock ps ON ps.product_id = si.product_id
        WHERE si.line_type = 'parts'
          AND si.product_id IS NOT NULL
          AND DATE_FORMAT(s.sale_date, '%Y-%m') IN ($monthList)
        GROUP BY si.product_id, p.description, p.code, c.category_name,
                 sale_month, ps.current_stock, p.reorder_threshold
        ORDER BY si.product_id, sale_month
    ");

    // Group by product
    $byProduct = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $pid = $row['product_id'];
            if (!isset($byProduct[$pid])) {
                $byProduct[$pid] = [
                    'product_id'       => $pid,
                    'description'      => $row['description'],
                    'code'             => $row['code'],
                    'category_name'    => $row['category_name'],
                    'current_stock'    => (int) $row['current_stock'],
                    'reorder_threshold'=> (int) $row['reorder_threshold'],
                    'monthly_usage'    => [],   // month_key => qty
                ];
            }
            $byProduct[$pid]['monthly_usage'][$row['sale_month']] = (int) $row['qty_sold'];
        }
    }

    // Also pull products that had NO sales in those 3 months but exist in inventory
    // so we can still show them (with 0 avg)
    $rAll = $conn->query("
        SELECT
            p.product_id,
            p.description,
            p.code,
            c.category_name,
            COALESCE(ps.current_stock, 0)     AS current_stock,
            COALESCE(p.reorder_threshold, 5)  AS reorder_threshold
        FROM products p
        LEFT JOIN categories c  ON p.category_id = c.category_id
        LEFT JOIN product_stock ps ON ps.product_id = p.product_id
        WHERE p.product_id NOT IN (" . (empty($byProduct) ? '0' : implode(',', array_keys($byProduct))) . ")
        ORDER BY p.description
    ");
    if ($rAll) {
        while ($row = $rAll->fetch_assoc()) {
            $pid = $row['product_id'];
            $byProduct[$pid] = [
                'product_id'       => $pid,
                'description'      => $row['description'],
                'code'             => $row['code'],
                'category_name'    => $row['category_name'],
                'current_stock'    => (int) $row['current_stock'],
                'reorder_threshold'=> (int) $row['reorder_threshold'],
                'monthly_usage'    => [],
            ];
        }
    }

    // ── Apply formulas per product ────────────────────────────────────────────
    foreach ($byProduct as $pid => &$p) {
        $usageValues = [];
        foreach ($months as $m) {
            $usageValues[] = $p['monthly_usage'][$m] ?? 0;
        }

        // 1. Moving Average (last 3 months)
        $avg_monthly = array_sum($usageValues) / 3;

        // 2. Forecast Needed = avg monthly usage × 1 month
        $forecast_needed = round($avg_monthly);

        // 3. Usage during lead time = (avg ÷ 30) × lead_time_days
        $usage_lead_time = ($avg_monthly / DAYS_PER_MONTH) * LEAD_TIME_DAYS;

        // 4. Reorder Point = usage_lead_time + safety_stock
        $reorder_point = ceil($usage_lead_time) + SAFETY_STOCK;

        // 5. Status
        $stock = $p['current_stock'];
        if ($stock <= 0) {
            $status = 'OUT_OF_STOCK';
        } elseif ($stock <= $reorder_point) {
            $status = 'REORDER_NOW';
        } elseif ($stock <= ($reorder_point * 1.3)) {
            $status = 'LOW_STOCK';
        } else {
            $status = 'SUFFICIENT';
        }

        $p['month_labels']     = $months;
        $p['monthly_usage_arr']= $usageValues;
        $p['avg_monthly']      = round($avg_monthly, 1);
        $p['forecast_needed']  = $forecast_needed;
        $p['usage_lead_time']  = round($usage_lead_time, 1);
        $p['safety_stock']     = SAFETY_STOCK;
        $p['reorder_point']    = $reorder_point;
        $p['status']           = $status;
        unset($p['monthly_usage']); // clean up raw map
    }
    unset($p);

    // Sort: REORDER_NOW first, then LOW_STOCK, then SUFFICIENT
    $order = ['OUT_OF_STOCK' => 0, 'REORDER_NOW' => 1, 'LOW_STOCK' => 2, 'SUFFICIENT' => 3];
    usort($byProduct, fn($a, $b) => ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9));

    $items = array_values($byProduct);
}

// ─── Monthly revenue totals (last 12 months) — kept for the chart ─────────────
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
    if ($r)
        while ($row = $r->fetch_assoc())
            $monthly[] = $row;
}

echo json_encode([
    'success'      => true,
    'items'        => $items,
    'monthly'      => $monthly,
    'constants'    => [
        'lead_time_days' => LEAD_TIME_DAYS,
        'safety_stock'   => SAFETY_STOCK,
        'days_per_month' => DAYS_PER_MONTH,
        'months_used'    => $months ?? [],
    ],
]);
$conn->close();