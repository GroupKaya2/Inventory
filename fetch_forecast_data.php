<?php
/**
 * Inventory forecasting & planning API.
 * Returns: real-time stock, weekly/monthly forecasts, seasonal demand,
 * reorder recommendations, peak workload prediction.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include __DIR__ . '/db.php';
$conn->set_charset('utf8mb4');

// ----- Real-time stock levels (from product_stock view) -----
function getCurrentStockLevels($conn) {
    $threshCol = getReorderThresholdCol($conn) ? 'COALESCE(p.reorder_threshold, 5)' : '5';
    $sql = "SELECT p.product_id, p.description, p.code, p.unit, c.category_name,
            COALESCE(s.current_stock, p.initial_quantity) AS current_stock,
            $threshCol AS reorder_threshold
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN product_stock s ON s.product_id = p.product_id
            ORDER BY p.product_id";
    $q = $conn->query($sql);
    if (!$q) return [];
    $out = [];
    while ($row = $q->fetch_assoc()) {
        $out[] = [
            'product_id' => (int)$row['product_id'],
            'description' => $row['description'],
            'code' => $row['code'],
            'category_name' => $row['category_name'],
            'unit' => $row['unit'],
            'current_stock' => (int)($row['current_stock'] ?? 0),
            'reorder_threshold' => (int)($row['reorder_threshold'] ?? 5),
        ];
    }
    return $out;
}

function getReorderThresholdCol($conn) {
    static $ok = null;
    if ($ok === null) {
        $ok = @$conn->query("SHOW COLUMNS FROM products LIKE 'reorder_threshold'")->num_rows > 0;
    }
    return $ok;
}

// ----- Usage: quantity consumed (outflows) per product -----
function getWeeklyUsageByProduct($conn, $weeks = 8) {
    $sql = "SELECT t.product_id, YEARWEEK(t.transaction_date) AS yw,
            SUM(ABS(t.quantity_change)) AS qty
            FROM inventory_transactions t
            WHERE t.quantity_change < 0
            AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
            GROUP BY t.product_id, YEARWEEK(t.transaction_date)";
    $st = $conn->prepare($sql);
    $st->bind_param('i', $weeks);
    $st->execute();
    $r = $st->get_result();
    $byProduct = [];
    while ($row = $r->fetch_assoc()) {
        $pid = (int)$row['product_id'];
        if (!isset($byProduct[$pid])) $byProduct[$pid] = [];
        $byProduct[$pid][] = (int)$row['qty'];
    }
    $st->close();
    return $byProduct;
}

function getMonthlyUsageByProduct($conn, $months = 12) {
    $sql = "SELECT t.product_id, YEAR(t.transaction_date) AS y, MONTH(t.transaction_date) AS m,
            SUM(ABS(t.quantity_change)) AS qty
            FROM inventory_transactions t
            WHERE t.quantity_change < 0
            AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY t.product_id, YEAR(t.transaction_date), MONTH(t.transaction_date)";
    $st = $conn->prepare($sql);
    $st->bind_param('i', $months);
    $st->execute();
    $r = $st->get_result();
    $byProduct = [];
    while ($row = $r->fetch_assoc()) {
        $pid = (int)$row['product_id'];
        if (!isset($byProduct[$pid])) $byProduct[$pid] = [];
        $byProduct[$pid][] = (int)$row['qty'];
    }
    $st->close();
    return $byProduct;
}

// ----- Seasonal demand: total usage by month (last 12 months) -----
function getSeasonalDemand($conn, $months = 12) {
    $sql = "SELECT YEAR(t.transaction_date) AS y, MONTH(t.transaction_date) AS m,
            SUM(ABS(t.quantity_change)) AS total_qty
            FROM inventory_transactions t
            WHERE t.quantity_change < 0
            AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY YEAR(t.transaction_date), MONTH(t.transaction_date)
            ORDER BY y, m";
    $st = $conn->prepare($sql);
    $st->bind_param('i', $months);
    $st->execute();
    $r = $st->get_result();
    $out = [];
    $monthNames = ['', 'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    while ($row = $r->fetch_assoc()) {
        $out[] = [
            'label' => $monthNames[(int)$row['m']] . ' ' . $row['y'],
            'month' => (int)$row['m'],
            'year' => (int)$row['y'],
            'total_qty' => (int)$row['total_qty'],
        ];
    }
    $st->close();
    return $out;
}

// ----- Forecast: average weekly usage -> next 4 weeks; average monthly -> next 3 months -----
function getWeeklyForecast($conn) {
    $byProduct = getWeeklyUsageByProduct($conn, 8);
    $products = getProductList($conn);
    $out = [];
    foreach ($products as $p) {
        $pid = $p['product_id'];
        $weeks = $byProduct[$pid] ?? [];
        $avg = count($weeks) > 0 ? array_sum($weeks) / count($weeks) : 0;
        $out[] = [
            'product_id' => $pid,
            'description' => $p['description'],
            'code' => $p['code'],
            'avg_weekly_usage' => round($avg, 1),
            'next_4_weeks_predicted' => round($avg * 4, 1),
            'weeks_of_data' => count($weeks),
        ];
    }
    return $out;
}

function getMonthlyForecast($conn) {
    $byProduct = getMonthlyUsageByProduct($conn, 12);
    $products = getProductList($conn);
    $out = [];
    foreach ($products as $p) {
        $pid = $p['product_id'];
        $months = $byProduct[$pid] ?? [];
        $avg = count($months) > 0 ? array_sum($months) / count($months) : 0;
        $out[] = [
            'product_id' => $pid,
            'description' => $p['description'],
            'code' => $p['code'],
            'avg_monthly_usage' => round($avg, 1),
            'next_3_months_predicted' => round($avg * 3, 1),
            'months_of_data' => count($months),
        ];
    }
    return $out;
}

function getProductList($conn) {
    $q = $conn->query("SELECT product_id, description, code FROM products ORDER BY product_id");
    if (!$q) return [];
    $out = [];
    while ($row = $q->fetch_assoc()) {
        $out[] = ['product_id' => (int)$row['product_id'], 'description' => $row['description'], 'code' => $row['code']];
    }
    return $out;
}

// ----- Reorder recommendations: low stock or forecast suggests shortage -----
function getReorderRecommendations($conn) {
    $stockLevels = getCurrentStockLevels($conn);
    $weeklyForecast = [];
    foreach (getWeeklyForecast($conn) as $f) {
        $weeklyForecast[$f['product_id']] = $f;
    }
    $recommendations = [];
    foreach ($stockLevels as $s) {
        $pid = $s['product_id'];
        $current = $s['current_stock'];
        $threshold = $s['reorder_threshold'];
        $forecast = $weeklyForecast[$pid] ?? null;
        $avgWeekly = $forecast ? (float)$forecast['avg_weekly_usage'] : 0;
        $recommendedQty = max(0, $threshold - $current);
        if ($avgWeekly > 0) {
            $weeksCover = $current / $avgWeekly;
            $suggestedOrder = max($recommendedQty, (int)ceil($avgWeekly * 2)); // at least 2 weeks supply
        } else {
            $suggestedOrder = $recommendedQty;
        }
        if ($current <= $threshold || $suggestedOrder > 0) {
            $recommendations[] = [
                'product_id' => $pid,
                'description' => $s['description'],
                'code' => $s['code'],
                'current_stock' => $current,
                'reorder_threshold' => $threshold,
                'recommended_qty' => max($suggestedOrder, $threshold),
                'reason' => $current <= $threshold ? 'Below reorder point' : 'Forecast suggests reorder',
            ];
        }
    }
    return $recommendations;
}

// ----- Peak workload: work orders by week (past + next 4 weeks trend) -----
function getPeakWorkload($conn, $weeksBack = 8, $weeksAhead = 4) {
    if (!getHasWorkOrders($conn)) {
        return ['past' => [], 'predicted_weeks' => [], 'message' => 'No work order data'];
    }
    $sql = "SELECT YEARWEEK(created_at) AS yw, COUNT(*) AS cnt
            FROM work_orders
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
            GROUP BY YEARWEEK(created_at)
            ORDER BY yw";
    $st = $conn->prepare($sql);
    $st->bind_param('i', $weeksBack);
    $st->execute();
    $r = $st->get_result();
    $past = [];
    while ($row = $r->fetch_assoc()) {
        $past[] = ['week' => $row['yw'], 'count' => (int)$row['cnt']];
    }
    $st->close();

    $avg = 0;
    if (count($past) > 0) {
        $avg = array_sum(array_column($past, 'count')) / count($past);
    }
    $predicted = [];
    for ($i = 1; $i <= $weeksAhead; $i++) {
        $d = new DateTime();
        $d->modify("+{$i} week");
        $predicted[] = [
            'week_label' => $d->format('M j'),
            'predicted_count' => round($avg, 0),
        ];
    }
    return [
        'past' => $past,
        'predicted_weeks' => $predicted,
        'avg_per_week' => round($avg, 1),
    ];
}

function getHasWorkOrders($conn) {
    static $ok = null;
    if ($ok === null) {
        $ok = @$conn->query("SHOW TABLES LIKE 'work_orders'")->num_rows > 0;
    }
    return $ok;
}

try {
    $current_stock = getCurrentStockLevels($conn);
    $weekly_forecast = getWeeklyForecast($conn);
    $monthly_forecast = getMonthlyForecast($conn);
    $seasonal_demand = getSeasonalDemand($conn, 12);
    $reorder_recommendations = getReorderRecommendations($conn);
    $peak_workload = getPeakWorkload($conn);

    echo json_encode([
        'success' => true,
        'current_stock_levels' => $current_stock,
        'weekly_forecast' => $weekly_forecast,
        'monthly_forecast' => $monthly_forecast,
        'seasonal_demand' => $seasonal_demand,
        'reorder_recommendations' => $reorder_recommendations,
        'peak_workload' => $peak_workload,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
$conn->close();
