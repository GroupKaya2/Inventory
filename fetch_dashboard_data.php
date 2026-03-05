<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include __DIR__ . '/db.php';

$conn->set_charset('utf8mb4');

function hasSalesTable($conn) {
    $q = @$conn->query("SHOW TABLES LIKE 'sales'");
    return $q && $q->num_rows > 0;
}

function getTodaySalesFromSales($conn) {
    $sql = "SELECT COALESCE(SUM(parts_total + labor_total), 0) AS total FROM sales WHERE sale_date = CURDATE()";
    $q = $conn->query($sql);
    return $q ? (float)($q->fetch_assoc()['total'] ?? 0) : 0;
}

function getTodaySalesCountFromSales($conn) {
    $sql = "SELECT COUNT(*) AS count FROM sales WHERE sale_date = CURDATE()";
    $q = $conn->query($sql);
    return $q ? (int)($q->fetch_assoc()['count'] ?? 0) : 0;
}

function getTodayItemsSoldFromSales($conn) {
    $sql = "SELECT COALESCE(SUM(si.quantity), 0) AS total_items
            FROM sale_items si
            INNER JOIN sales s ON s.id = si.sale_id
            WHERE s.sale_date = CURDATE() AND si.line_type = 'parts'";
    $q = @$conn->query($sql);
    return $q ? (int)($q->fetch_assoc()['total_items'] ?? 0) : 0;
}

function getLastWeekSameDaySalesFromSales($conn) {
    $sql = "SELECT COALESCE(SUM(parts_total + labor_total), 0) AS total FROM sales WHERE sale_date = DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $q = $conn->query($sql);
    return $q ? (float)($q->fetch_assoc()['total'] ?? 0) : 0;
}

function getMonthlyRevenueFromSales($conn, $year, $month) {
    $sql = "SELECT COALESCE(SUM(parts_total), 0) AS parts, COALESCE(SUM(labor_total), 0) AS labor FROM sales WHERE YEAR(sale_date) = ? AND MONTH(sale_date) = ?";
    $st = $conn->prepare($sql);
    if (!$st) return ['parts' => 0, 'labor' => 0, 'total' => 0];
    $st->bind_param('ii', $year, $month);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $parts = (float)($row['parts'] ?? 0);
    $labor = (float)($row['labor'] ?? 0);
    return ['parts' => $parts, 'labor' => $labor, 'total' => $parts + $labor];
}

function getRecentTransactions($conn, $limit = 10) {
    $sql = "SELECT id, sale_date, customer_name, plate_number, parts_total, labor_total, (parts_total + labor_total) AS total, created_at FROM sales ORDER BY sale_date DESC, id DESC LIMIT " . (int)$limit;
    $q = $conn->query($sql);
    if (!$q) return [];
    $out = [];
    while ($row = $q->fetch_assoc()) {
        $out[] = [
            'id' => (int)$row['id'],
            'sale_date' => $row['sale_date'],
            'customer_name' => $row['customer_name'] ?: '—',
            'plate_number' => $row['plate_number'] ?: '—',
            'parts_total' => (float)$row['parts_total'],
            'labor_total' => (float)$row['labor_total'],
            'total' => (float)$row['total'],
            'created_at' => $row['created_at'],
        ];
    }
    return $out;
}

function getPartsInStock($conn) {
    $sql = "SELECT COALESCE(SUM(s.current_stock), 0) AS total FROM product_stock s";
    $r = $conn->query($sql);
    return $r ? (int)($r->fetch_assoc()['total'] ?? 0) : 0;
}

function getLowStockCount($conn) {
    static $useThreshold = null;
    if ($useThreshold === null) {
        $useThreshold = @$conn->query("SHOW COLUMNS FROM products LIKE 'reorder_threshold'")->num_rows > 0;
    }
    $col = $useThreshold ? 'COALESCE(p.reorder_threshold, 5)' : '5';
    $sql = "SELECT COUNT(*) AS cnt FROM product_stock s
            INNER JOIN products p ON p.product_id = s.product_id
            WHERE s.current_stock <= $col";
    $q = $conn->query($sql);
    if (!$q) return 0;
    return (int)$q->fetch_assoc()['cnt'];
}

function getLowStockAlerts($conn, $limit = 10) {
    $useThreshold = @$conn->query("SHOW COLUMNS FROM products LIKE 'reorder_threshold'")->num_rows > 0;
    $col = $useThreshold ? 'COALESCE(p.reorder_threshold, 5)' : '5';
    $sql = "SELECT p.description, p.code, s.current_stock, $col AS reorder_threshold
            FROM product_stock s
            INNER JOIN products p ON p.product_id = s.product_id
            WHERE s.current_stock <= $col
            ORDER BY s.current_stock ASC
            LIMIT " . (int)$limit;
    $q = $conn->query($sql);
    if (!$q) return [];
    $out = [];
    while ($row = $q->fetch_assoc()) {
        $out[] = [
            'description' => $row['description'],
            'code' => $row['code'],
            'current_stock' => (int)$row['current_stock'],
            'reorder_threshold' => (int)$row['reorder_threshold'],
        ];
    }
    return $out;
}

function getTodaySales($conn) {
    $sql = "SELECT COALESCE(SUM(ABS(t.quantity_change) * p.selling_price), 0) AS total
            FROM inventory_transactions t
            INNER JOIN products p ON p.product_id = t.product_id
            WHERE t.transaction_date = CURDATE() AND t.quantity_change < 0";
    $q = $conn->query($sql);
    return $q ? (float)($q->fetch_assoc()['total'] ?? 0) : 0;
}

function getTodaySalesCount($conn) {
    $sql = "SELECT COUNT(DISTINCT DATE(t.transaction_date)) AS count
            FROM inventory_transactions t
            WHERE t.transaction_date = CURDATE() AND t.quantity_change < 0";
    $q = $conn->query($sql);
    return $q ? (int)($q->fetch_assoc()['count'] ?? 0) : 0;
}

function getTodayItemsSold($conn) {
    $sql = "SELECT COALESCE(SUM(ABS(t.quantity_change)), 0) AS total_items
            FROM inventory_transactions t
            WHERE t.transaction_date = CURDATE() AND t.quantity_change < 0";
    $q = $conn->query($sql);
    return $q ? (int)($q->fetch_assoc()['total_items'] ?? 0) : 0;
}

function getLastWeekSameDaySales($conn) {
    $sql = "SELECT COALESCE(SUM(ABS(t.quantity_change) * p.selling_price), 0) AS total
            FROM inventory_transactions t
            INNER JOIN products p ON p.product_id = t.product_id
            WHERE t.transaction_date = DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND t.quantity_change < 0";
    $q = $conn->query($sql);
    return $q ? (float)($q->fetch_assoc()['total'] ?? 0) : 0;
}

function getOpenWorkOrders($conn) {
    $sql = "SELECT COUNT(*) AS cnt FROM work_orders WHERE status = 'open'";
    $q = @$conn->query($sql);
    if (!$q) return 0;
    return (int)$q->fetch_assoc()['cnt'];
}

function getWorkOrdersCompletedToday($conn) {
    $sql = "SELECT COUNT(*) AS cnt FROM work_orders
            WHERE status = 'completed' AND DATE(completed_at) = CURDATE()";
    $q = @$conn->query($sql);
    if (!$q) return 0;
    return (int)$q->fetch_assoc()['cnt'];
}

function getMonthlyRevenue($conn, $year, $month) {
    $parts = "SELECT COALESCE(SUM(ABS(t.quantity_change) * p.selling_price), 0) AS total
            FROM inventory_transactions t
                INNER JOIN products p ON p.product_id = t.product_id
                WHERE t.quantity_change < 0
                AND YEAR(t.transaction_date) = ? AND MONTH(t.transaction_date) = ?";
    $st = $conn->prepare($parts);
    $st->bind_param('ii', $year, $month);
    $st->execute();
    $partsRevenue = (float)($st->get_result()->fetch_assoc()['total'] ?? 0);
    $st->close();

    $labor = 0;
    $laborSql = "SELECT COALESCE(SUM(labor_amount), 0) AS total FROM work_orders
                WHERE status = 'completed' AND YEAR(completed_at) = ? AND MONTH(completed_at) = ?";
    $st2 = @$conn->prepare($laborSql);
    if ($st2) {
        $st2->bind_param('ii', $year, $month);
        $st2->execute();
        $labor = (float)($st2->get_result()->fetch_assoc()['total'] ?? 0);
        $st2->close();
    }
    return ['parts' => $partsRevenue, 'labor' => $labor, 'total' => $partsRevenue + $labor];
}

function getMonthlyRevenueTrend($conn, $numMonths = 6) {
    $useSalesTable = hasSalesTable($conn);
    $months = [];
    $now = new DateTime('first day of this month');
    for ($i = 0; $i < $numMonths; $i++) {
        $m = (int)$now->format('n');
        $y = (int)$now->format('Y');
        $rev = $useSalesTable ? getMonthlyRevenueFromSales($conn, $y, $m) : getMonthlyRevenue($conn, $y, $m);
        $months[] = [
            'label' => $now->format('M'),
            'year' => $y,
            'month' => $m,
            'parts' => round($rev['parts'], 2),
            'labor' => round($rev['labor'], 2),
            'total' => round($rev['total'], 2),
        ];
        $now->modify('-1 month');
    }
    return array_reverse($months);
}

function getTopSellingParts($conn, $limit = 5) {
    $sql = "SELECT p.description, p.product_id,
                   SUM(ABS(t.quantity_change) * p.selling_price) AS total_sales
            FROM inventory_transactions t
            INNER JOIN products p ON p.product_id = t.product_id
            WHERE t.quantity_change < 0
            GROUP BY p.product_id, p.description, p.selling_price
            ORDER BY total_sales DESC
            LIMIT " . (int)$limit;
    $q = $conn->query($sql);
    if (!$q) return [];
    $out = [];
    while ($row = $q->fetch_assoc()) {
        $out[] = [
            'description' => $row['description'],
            'total_sales' => (float)$row['total_sales'],
        ];
    }
    return $out;
}

function getTopSellingPartsFromSales($conn, $limit = 5) {
    $sql = "SELECT COALESCE(p.description, si.description) AS description,
                SUM(si.amount) AS total_sales
            FROM sale_items si
            LEFT JOIN products p ON p.product_id = si.product_id
            WHERE si.line_type = 'parts'
            GROUP BY si.product_id, COALESCE(p.description, si.description)
            ORDER BY total_sales DESC
            LIMIT " . (int)$limit;
    $q = @$conn->query($sql);
    if (!$q) return [];
    $out = [];
    while ($row = $q->fetch_assoc()) {
        $out[] = [
            'description' => $row['description'] ?: 'Parts',
            'total_sales' => (float)$row['total_sales'],
        ];
    }
    return $out;
}

function getTopLaborServices($conn, $limit = 5) {
    $sql = "SELECT service_name, SUM(labor_amount) AS total_revenue
            FROM work_orders
            WHERE status = 'completed'
            GROUP BY service_name
            ORDER BY total_revenue DESC
            LIMIT " . (int)$limit;
    $q = @$conn->query($sql);
    if (!$q) return [];
    $out = [];
    while ($row = $q->fetch_assoc()) {
        $out[] = [
            'service_name' => $row['service_name'],
            'total_revenue' => (float)$row['total_revenue'],
        ];
    }
    return $out;
}

function getTopLaborFromSales($conn, $limit = 5) {
    $sql = "SELECT COALESCE(si.description, 'Labor') AS service_name, SUM(si.amount) AS total_revenue
            FROM sale_items si
            WHERE si.line_type = 'labor'
            GROUP BY si.description
            ORDER BY total_revenue DESC
            LIMIT " . (int)$limit;
    $q = @$conn->query($sql);
    if (!$q) return [];
    $out = [];
    while ($row = $q->fetch_assoc()) {
        $out[] = [
            'service_name' => $row['service_name'] ?: 'Labor',
            'total_revenue' => (float)$row['total_revenue'],
        ];
    }
    return $out;
}

function getSalesSplit($conn, $year = null, $month = null) {
    if ($year === null) $year = (int)date('Y');
    if ($month === null) $month = (int)date('n');
    $rev = hasSalesTable($conn) ? getMonthlyRevenueFromSales($conn, $year, $month) : getMonthlyRevenue($conn, $year, $month);
    $parts = $rev['parts'];
    $labor = $rev['labor'];
    $total = $parts + $labor;
    $partsPct = $total > 0 ? round(100 * $parts / $total, 1) : 0;
    $laborPct = $total > 0 ? round(100 * $labor / $total, 1) : 0;
    return [
        'parts_amount' => round($parts, 2),
        'labor_amount' => round($labor, 2),
        'total' => round($total, 2),
        'parts_pct' => $partsPct,
        'labor_pct' => $laborPct,
    ];
}

function getAlerts($conn) {
    $alerts = [];
    $lowStock = getLowStockAlerts($conn, 5);
    foreach ($lowStock as $i => $item) {
        $alerts[] = [
            'type' => $item['current_stock'] <= 2 ? 'critical' : 'low',
            'title' => $item['description'] . ($item['code'] ? ' - ' . $item['code'] : '') . ' ' . ($item['current_stock'] <= 2 ? 'critical low' : 'Low stock'),
            'detail' => 'Only ' . $item['current_stock'] . ' remaining. Reorder threshold: ' . $item['reorder_threshold'] . '.',
            'time' => 'Now',
            'icon' => $item['current_stock'] <= 2 ? 'exclamation-triangle' : 'droplet',
        ];
    }
    $pendingReorder = getLowStockCount($conn);
    if ($pendingReorder > 0) {
        $alerts[] = [
            'type' => 'info',
            'title' => $pendingReorder . ' parts pending reorder approval',
            'detail' => 'Smart reorder detected high-demand items.',
            'time' => 'Today',
            'icon' => 'arrow-repeat',
        ];
    }
    $alerts[] = [
        'type' => 'success',
        'title' => 'Monthly report auto-generated',
        'detail' => date('F Y', strtotime('last month')) . ' report ready for download.',
        'time' => 'Today',
        'icon' => 'check-circle',
    ];
    return $alerts;
}

function getDashboardKpis($conn) {
    $useSalesTable = hasSalesTable($conn);
    $todaySales = $useSalesTable ? getTodaySalesFromSales($conn) : getTodaySales($conn);
    $todaySalesCount = $useSalesTable ? getTodaySalesCountFromSales($conn) : getTodaySalesCount($conn);
    $todayItemsSold = $useSalesTable ? getTodayItemsSoldFromSales($conn) : getTodayItemsSold($conn);
    $lastWeekSame = $useSalesTable ? getLastWeekSameDaySalesFromSales($conn) : getLastWeekSameDaySales($conn);
    $salesTrendPct = $lastWeekSame > 0
        ? round((($todaySales - $lastWeekSame) / $lastWeekSame) * 100, 0)
        : ($todaySales > 0 ? 100 : 0);

    $partsInStock = getPartsInStock($conn);
    $lowStockCount = getLowStockCount($conn);
    $openWorkOrders = getOpenWorkOrders($conn);
    $completedToday = getWorkOrdersCompletedToday($conn);

    $thisMonth = (int)date('n');
    $thisYear = (int)date('Y');
    $prevMonth = $thisMonth === 1 ? 12 : $thisMonth - 1;
    $prevYear = $thisMonth === 1 ? $thisYear - 1 : $thisYear;
    $nov = $useSalesTable ? getMonthlyRevenueFromSales($conn, $thisYear, $thisMonth) : getMonthlyRevenue($conn, $thisYear, $thisMonth);
    $sept = $useSalesTable ? getMonthlyRevenueFromSales($conn, $prevYear, $prevMonth) : getMonthlyRevenue($conn, $prevYear, $prevMonth);
    $currentRevenue = $nov['total'];
    $compareRevenue = $sept['total'];
    $revenueTrendPct = $compareRevenue > 0
        ? round((($currentRevenue - $compareRevenue) / $compareRevenue) * 100, 0)
        : 0;

    return [
        'today_sales' => round($todaySales, 2),
        'today_sales_count' => $todaySalesCount,
        'today_items_sold' => $todayItemsSold,
        'today_sales_trend_pct' => $salesTrendPct,
        'parts_in_stock' => $partsInStock,
        'low_stock_alerts' => $lowStockCount,
        'open_work_orders' => $openWorkOrders,
        'work_orders_completed_today' => $completedToday,
        'current_month_revenue' => round($currentRevenue, 2),
        'compare_month_revenue' => round($compareRevenue, 2),
        'revenue_trend_pct' => $revenueTrendPct,
        'current_month_label' => date('M', mktime(0, 0, 0, $thisMonth, 1)),
        'compare_month_label' => date('M', mktime(0, 0, 0, $prevMonth, 1)),
    ];
}

try {
    $kpis = getDashboardKpis($conn);
    $trend = getMonthlyRevenueTrend($conn, 6);
    $alerts = getAlerts($conn);
    $useSalesTable = hasSalesTable($conn);
    $topParts = $useSalesTable ? getTopSellingPartsFromSales($conn, 5) : getTopSellingParts($conn, 5);
    if (empty($topParts)) $topParts = getTopSellingParts($conn, 5);
    $topLabor = $useSalesTable ? getTopLaborFromSales($conn, 5) : getTopLaborServices($conn, 5);
    if (empty($topLabor)) $topLabor = getTopLaborServices($conn, 5);
    $salesSplit = getSalesSplit($conn);
    $recentTransactions = $useSalesTable ? getRecentTransactions($conn, 15) : [];

    echo json_encode([
        'success' => true,
        'kpis' => $kpis,
        'monthly_trend' => $trend,
        'alerts' => $alerts,
        'top_selling_parts' => $topParts,
        'top_labor_services' => $topLabor,
        'sales_split' => $salesSplit,
        'recent_transactions' => $recentTransactions,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

$conn->close();
