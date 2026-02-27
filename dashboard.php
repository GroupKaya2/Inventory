<?php
session_start();
include "db.php";
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
$activePage = 'dashboard';

$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

// ── AUTO-CREATE EXPENSES TABLE IF MISSING ───────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");


// KPI: TODAY
$todayRow = $conn->query("
    SELECT COALESCE(SUM(parts_total+labor_total),0) AS revenue,
        COALESCE(SUM(parts_total),0)             AS parts,
        COALESCE(SUM(labor_total),0)             AS labor,
        COUNT(*)                                  AS sales_count
    FROM sales WHERE sale_date='$today'
")->fetch_assoc();

$todayExpRow = $conn->query("
    SELECT COALESCE(SUM(amount),0) AS expenses
    FROM expenses WHERE expense_date='$today'
")->fetch_assoc();

$todayRevenue  = (float)$todayRow['revenue'];
$todayExpenses = (float)$todayExpRow['expenses'];
$todayProfit   = $todayRevenue - $todayExpenses;
$todayParts    = (float)$todayRow['parts'];
$todayLabor    = (float)$todayRow['labor'];
$todaySales    = (int)$todayRow['sales_count'];

// KPI: THIS MONTH
$monthRevRow = $conn->query("
    SELECT COALESCE(SUM(parts_total+labor_total),0) AS revenue,
        COALESCE(SUM(parts_total),0)             AS parts,
        COALESCE(SUM(labor_total),0)             AS labor,
        COUNT(*)                                  AS cnt
    FROM sales WHERE sale_date BETWEEN '$monthStart' AND '$monthEnd'
")->fetch_assoc();

$monthExpRow = $conn->query("
    SELECT COALESCE(SUM(amount),0) AS expenses
    FROM expenses WHERE expense_date BETWEEN '$monthStart' AND '$monthEnd'
")->fetch_assoc();

$monthRevenue  = (float)$monthRevRow['revenue'];
$monthExpenses = (float)$monthExpRow['expenses'];
$monthProfit   = $monthRevenue - $monthExpenses;
$monthSales    = (int)$monthRevRow['cnt'];


// LOW STOCK
$lowStockRes  = $conn->query("
    SELECT description, current_stock, reorder_threshold, category_name
    FROM product_stock WHERE current_stock <= reorder_threshold
    ORDER BY current_stock ASC LIMIT 8
");
$lowStockItems = $lowStockRes ? $lowStockRes->fetch_all(MYSQLI_ASSOC) : [];
$lowStockCount = count($lowStockItems);


// OPEN WORK ORDERS
$openWO = 0;
if ($conn->query("SHOW TABLES LIKE 'work_orders'")->num_rows > 0) {
    $openWO = (int)$conn->query("SELECT COUNT(*) AS c FROM work_orders WHERE status='open'")->fetch_assoc()['c'];
}

// DAILY REVENUE & EXPENSES – last 30 days (for charts)
$dailySales = [];
$r = $conn->query("
    SELECT sale_date,
        SUM(parts_total)              AS parts,
        SUM(labor_total)              AS labor,
        SUM(parts_total+labor_total)  AS revenue
    FROM sales
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY sale_date ORDER BY sale_date
");
if ($r) while ($row = $r->fetch_assoc()) $dailySales[$row['sale_date']] = $row;

$dailyExp = [];
$r2 = $conn->query("
    SELECT expense_date, SUM(amount) AS expenses
    FROM expenses
    WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY expense_date ORDER BY expense_date
");
if ($r2) while ($row = $r2->fetch_assoc()) $dailyExp[$row['expense_date']] = (float)$row['expenses'];

// Fill 30-day array
$chartDays = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $rev = isset($dailySales[$d]) ? (float)$dailySales[$d]['revenue'] : 0;
    $exp = $dailyExp[$d] ?? 0;
    $chartDays[] = [
        'label'   => date('M d', strtotime($d)),
        'revenue' => $rev,
        'expenses'=> $exp,
        'profit'  => $rev - $exp,
        'parts'   => isset($dailySales[$d]) ? (float)$dailySales[$d]['parts'] : 0,
        'labor'   => isset($dailySales[$d]) ? (float)$dailySales[$d]['labor'] : 0,
    ];
}

// ═══════════════════════════════════════════════════════════════
// BEST SALES – Top 5 products by revenue (all time)
// ═══════════════════════════════════════════════════════════════
$bestProducts = [];
$bp = $conn->query("
    SELECT p.description, p.code, c.category_name,
        SUM(si.quantity) AS qty_sold,
        SUM(si.amount)   AS revenue,
        p.selling_price,
        p.unit_cost,
        (p.selling_price - p.unit_cost) AS margin
    FROM sale_items si
    JOIN products p  ON si.product_id  = p.product_id
    JOIN categories c ON p.category_id = c.category_id
    WHERE si.line_type = 'parts'
    GROUP BY si.product_id
    ORDER BY revenue DESC
    LIMIT 5
");
if ($bp) while ($row = $bp->fetch_assoc()) $bestProducts[] = $row;

// Best labor services
$bestLabor = [];
$bl = $conn->query("
    SELECT description, COUNT(*) AS cnt, SUM(amount) AS revenue
    FROM sale_items WHERE line_type='labor'
    GROUP BY description
    ORDER BY revenue DESC LIMIT 5
");
if ($bl) while ($row = $bl->fetch_assoc()) $bestLabor[] = $row;

// Best customers (top 5 by spend)
$bestCustomers = [];
$bc = $conn->query("
    SELECT customer_name, plate_number,
           COUNT(*) AS visits,
           SUM(parts_total+labor_total) AS total_spend
    FROM sales
    WHERE customer_name != ''
    GROUP BY customer_name, plate_number
    ORDER BY total_spend DESC
    LIMIT 5
");
if ($bc) while ($row = $bc->fetch_assoc()) $bestCustomers[] = $row;

// ═══════════════════════════════════════════════════════════════
// RECENT SALES
// ═══════════════════════════════════════════════════════════════
$recentSales = [];
$rs = $conn->query("
    SELECT id, sale_date, customer_name, plate_number,
           parts_total, labor_total,
           (parts_total+labor_total) AS grand_total
    FROM sales ORDER BY created_at DESC LIMIT 8
");
if ($rs) while ($row = $rs->fetch_assoc()) $recentSales[] = $row;

// ═══════════════════════════════════════════════════════════════
// EXPENSE CATEGORY PIE (last 30 days)
// ═══════════════════════════════════════════════════════════════
$expCats = [];
$ec = $conn->query("
    SELECT category, SUM(amount) AS total
    FROM expenses
    WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY category ORDER BY total DESC
");
if ($ec) while ($row = $ec->fetch_assoc()) $expCats[] = $row;

// JSON for JS
$jsonDays      = json_encode($chartDays);
$jsonBestP     = json_encode($bestProducts);
$jsonBestC     = json_encode($bestCustomers);
$jsonExpCats   = json_encode($expCats);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – Dispeedway</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root{--primary:#667eea;--secondary:#764ba2;--bg:#f0f2f8;--dark:#1e293b;}
body{background:var(--bg);font-family:'Segoe UI',sans-serif;}
.app-main{min-height:100vh;}

/* KPI CARDS */
.kpi-card{
    border:none;
    border-radius:16px;
    box-shadow:0 4px 20px rgba(0,0,0,.08);
    transition:.2s;
    overflow:hidden;
}

.kpi-card:hover{
    transform:translateY(-3px);
    box-shadow:0 8px 28px rgba(0,0,0,.13);
}

.kpi-icon{
    width:54px;
    height:54px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.45rem;
    flex-shrink:0;
}

.kpi-title{
    font-size:.7rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.6px;
    color:#94a3b8;
}

.kpi-value{
    font-size:1.65rem;
    font-weight:800;
    color:var(--dark);
    line-height:1.15;
}

.kpi-sub{
    font-size:.74rem;
    color:#64748b;
    margin-top:2px;
}

.kpi-revenue .kpi-icon{
    background:linear-gradient(135deg,#667eea,#764ba2);
    color:#fff;
}

.kpi-expense .kpi-icon{
    background:linear-gradient(135deg,#ef4444,#dc2626);
    color:#fff;
}

.kpi-profit-pos .kpi-icon{
    background:linear-gradient(135deg,#10b981,#059669);
    color:#fff;
}

.kpi-profit-neg .kpi-icon{
    background:linear-gradient(135deg,#f59e0b,#ef4444);
    color:#fff;
}

.kpi-labor .kpi-icon{
    background:linear-gradient(135deg,#06b6d4,#3b82f6);
    color:#fff;
}

.kpi-alert .kpi-icon{
    background:linear-gradient(135deg,#f59e0b,#ef4444);
    color:#fff;
}

/* PROFIT HIGHLIGHT */
.profit-positive{color:#10b981 !important;}
.profit-negative{color:#ef4444 !important;}

/* CHART CARDS */
.chart-card{
    border:none;
    border-radius:16px;
    box-shadow:0 4px 20px rgba(0,0,0,.08);
    padding:20px;
}

.chart-card h6{
    font-weight:700;
    color:var(--dark);
    margin-bottom:14px;
    font-size:.9rem;
}


/* TABLE */
.dash-table thead th{
    background:linear-gradient(135deg,var(--primary),var(--secondary));
    color:#fff;
    font-size:.75rem;
    font-weight:600;
    border:none;
    padding:9px 12px;
}

.dash-table tbody td{
    font-size:.82rem;
    vertical-align:middle;
    padding:8px 12px;
    border-color:#f1f5f9;
}
.dash-table tbody tr:hover{
    background:#f8fafc;
}

/* BEST SALES CARDS */
.best-card{
    border:none;
    border-radius:14px;
    box-shadow:0 4px 16px rgba(0,0,0,.07);
    overflow:hidden;
}

.best-card .card-header{
    font-weight:700;
    font-size:.82rem;
    padding:12px 16px;
}
.rank-badge{
    width:28px;
    height:28px;
    border-radius:8px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:.75rem;
    font-weight:800;
    flex-shrink:0;
}

.rank-1{
    background:linear-gradient(135deg,#f59e0b,#d97706);
    color:#fff;
}

.rank-2{
    background:linear-gradient(135deg,#94a3b8,#64748b);
    color:#fff;
}

.rank-3{
    background:linear-gradient(135deg,#b45309,#92400e);
    color:#fff;
}

.rank-other{
    background:#f1f5f9;
    color:#64748b;
}

/* LOW STOCK */
.low-stock-item{
    border-left:4px solid #f59e0b;
    border-radius:8px;padding:8px 12px;
    margin-bottom:8px;
    background:#fffbeb;
}

.low-stock-item.critical{
    border-color:#ef4444;
    background:#fef2f2;
}


/* SECTION DIVIDER */
.section-title{
    font-weight:700;
    color:var(--dark);
    font-size:1rem;
    border-left:4px solid var(--primary);
    padding-left:10px;
    margin-bottom:16px;
}


/* PROFIT METER */
.profit-meter{
    background:#f8fafc;
    border-radius:12px;
    padding:14px 16px;
    border:1px solid #e2e8f0;
}

.profit-meter .label{
    font-size:.72rem;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:.5px;
    color:#94a3b8;
}

.profit-meter .val{
    font-size:1.1rem;
    font-weight:800;
}

.progress-thin{
    height:6px;
    border-radius:3px;
}

</style>
</head>
<body>

<?php include "sidebar.php"; ?>

<main class="app-main p-3 p-md-4">
<div class="container-fluid">

    <!-- PAGE TITLE -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0 fw-bold" style="color:var(--dark)">
                <i class="bi bi-speedometer2 me-2" style="color:var(--primary)"></i>Dashboard
            </h4>
            <small class="text-muted">
                <?= htmlspecialchars($_SESSION['user'] ?? 'Admin') ?> &bull; <?= date('l, F j, Y') ?>
            </small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="sales.php" class="btn btn-sm" style="background:linear-gradient(135deg,#f97316,#ef4444);color:#fff;border:none;border-radius:10px;font-weight:600;">
                <i class="bi bi-plus-lg"></i> New Sale
            </a>
            <a href="expenses.php" class="btn btn-sm" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:none;border-radius:10px;font-weight:600;">
                <i class="bi bi-wallet2"></i> Add Expense
            </a>
            <a href="inventory.php" class="btn btn-sm" style="background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;border:none;border-radius:10px;font-weight:600;">
                <i class="bi bi-box-seam"></i> Inventory
            </a>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
    TODAY's KPI ROW:  Revenue | Expenses | Profit | Labor
    ══════════════════════════════════════════════════════════════ -->
    <div class="mb-2"><span class="section-title">📅 Today – <?= date('F j, Y') ?></span></div>
    <div class="row g-3 mb-3">
        <!-- Revenue -->
        <div class="col-6 col-lg-3">
            <div class="card kpi-card kpi-revenue h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="kpi-title">Revenue</div>
                        <div class="kpi-value">₱<?= number_format($todayRevenue,0) ?></div>
                        <div class="kpi-sub"><?= $todaySales ?> sale<?= $todaySales!=1?'s':'' ?></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Expenses -->
        <div class="col-6 col-lg-3">
            <div class="card kpi-card kpi-expense h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="kpi-icon"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <div class="kpi-title">Expenses</div>
                        <div class="kpi-value text-danger">₱<?= number_format($todayExpenses,0) ?></div>
                        <div class="kpi-sub"><a href="expenses.php" class="text-decoration-none text-muted">manage →</a></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Profit -->
        <div class="col-6 col-lg-3">
            <div class="card kpi-card <?= $todayProfit >= 0 ? 'kpi-profit-pos' : 'kpi-profit-neg' ?> h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    <div>
                        <div class="kpi-title">Net Profit</div>
                        <div class="kpi-value <?= $todayProfit >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                            <?= $todayProfit >= 0 ? '' : '-' ?>₱<?= number_format(abs($todayProfit),0) ?>
                        </div>
                        <div class="kpi-sub">Revenue − Expenses</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Labor -->
        <div class="col-6 col-lg-3">
            <div class="card kpi-card kpi-labor h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="kpi-icon"><i class="bi bi-wrench-adjustable"></i></div>
                    <div>
                        <div class="kpi-title">Labor Today</div>
                        <div class="kpi-value">₱<?= number_format($todayLabor,0) ?></div>
                        <div class="kpi-sub">Parts: ₱<?= number_format($todayParts,0) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         PROFIT METER – THIS MONTH
    ══════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="profit-meter d-flex flex-wrap gap-4 align-items-center">
                <div>
                    <div class="label">Monthly Revenue</div>
                    <div class="val text-primary">₱<?= number_format($monthRevenue,2) ?></div>
                </div>
                <div style="font-size:1.3rem;color:#94a3b8;">−</div>
                <div>
                    <div class="label">Monthly Expenses</div>
                    <div class="val text-danger">₱<?= number_format($monthExpenses,2) ?></div>
                </div>
                <div style="font-size:1.3rem;color:#94a3b8;">=</div>
                <div>
                    <div class="label">Monthly Net Profit</div>
                    <div class="val <?= $monthProfit>=0?'profit-positive':'profit-negative' ?>" style="font-size:1.4rem;">
                        <?= $monthProfit>=0?'':'−' ?>₱<?= number_format(abs($monthProfit),2) ?>
                    </div>
                </div>
                <?php if ($monthRevenue > 0): ?>
                <div class="flex-grow-1" style="min-width:160px;">
                    <div class="label mb-1">Profit Margin</div>
                    <?php $margin = ($monthProfit / $monthRevenue) * 100; ?>
                    <div class="progress progress-thin mb-1">
                        <div class="progress-bar <?= $margin>=0?'bg-success':'bg-danger' ?>" style="width:<?= min(abs($margin),100) ?>%"></div>
                    </div>
                    <small class="<?= $margin>=0?'text-success':'text-danger' ?> fw-bold">
                        <?= $margin>=0?'+':'' ?><?= number_format($margin,1) ?>% margin
                    </small>
                </div>
                <?php endif; ?>
                <div class="ms-auto">
                    <a href="expenses.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-wallet2 me-1"></i>View All Expenses</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         CHARTS ROW 1: Revenue vs Expenses | Expense Breakdown
    ══════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="chart-card h-100">
                <h6><i class="bi bi-graph-up-arrow me-2" style="color:var(--primary)"></i>Revenue vs Expenses vs Profit – Last 30 Days</h6>
                <canvas id="revenueExpensesChart" height="200"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card h-100">
                <h6><i class="bi bi-pie-chart me-2" style="color:#ef4444"></i>Expense Breakdown (30 days)</h6>
                <?php if (empty($expCats)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-wallet2 fs-1 d-block mb-2 opacity-25"></i>
                    No expenses yet.<br>
                    <a href="expenses.php" class="btn btn-sm btn-outline-danger mt-2">Add Expense</a>
                </div>
                <?php else: ?>
                <canvas id="expCatChart" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         BEST SALES SECTION
    ══════════════════════════════════════════════════════════════ -->
    <div class="mb-2"><span class="section-title">🏆 Best Sales</span></div>
    <div class="row g-3 mb-4">

        <!-- TOP PRODUCTS -->
        <div class="col-lg-4">
            <div class="card best-card h-100">
                <div class="card-header" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;">
                    <i class="bi bi-trophy me-2"></i>Top 5 Best-Selling Parts
                </div>
                <div class="card-body p-3">
                    <?php if (empty($bestProducts)): ?>
                    <p class="text-muted text-center">No sales yet.</p>
                    <?php else: foreach ($bestProducts as $idx => $bp): ?>
                    <div class="d-flex align-items-center gap-3 mb-3 pb-3 <?= $idx < count($bestProducts)-1 ? 'border-bottom' : '' ?>">
                        <span class="rank-badge rank-<?= $idx < 3 ? ($idx+1) : 'other' ?>"><?= $idx+1 ?></span>
                        <div class="flex-grow-1">
                            <div class="fw-600" style="font-size:.83rem;line-height:1.2;"><?= htmlspecialchars($bp['description']) ?></div>
                            <div class="text-muted" style="font-size:.71rem;"><?= htmlspecialchars($bp['category_name']) ?> &bull; <code><?= htmlspecialchars($bp['code']) ?></code></div>
                            <div style="font-size:.75rem;color:#10b981;">Margin: ₱<?= number_format($bp['margin'],2) ?>/unit</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-primary" style="font-size:.88rem;">₱<?= number_format($bp['revenue'],0) ?></div>
                            <div class="text-muted" style="font-size:.7rem;"><?= number_format($bp['qty_sold']) ?> sold</div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- TOP LABOR SERVICES -->
        <div class="col-lg-4">
            <div class="card best-card h-100">
                <div class="card-header" style="background:linear-gradient(135deg,#06b6d4,#3b82f6);color:#fff;">
                    <i class="bi bi-wrench me-2"></i>Top 5 Labor Services
                </div>
                <div class="card-body p-3">
                    <?php if (empty($bestLabor)): ?>
                    <p class="text-muted text-center">No labor recorded yet.</p>
                    <?php else: foreach ($bestLabor as $idx => $bl): ?>
                    <div class="d-flex align-items-center gap-3 mb-3 pb-3 <?= $idx < count($bestLabor)-1 ? 'border-bottom' : '' ?>">
                        <span class="rank-badge rank-<?= $idx < 3 ? ($idx+1) : 'other' ?>"><?= $idx+1 ?></span>
                        <div class="flex-grow-1">
                            <div class="fw-600" style="font-size:.83rem;"><?= htmlspecialchars($bl['description']) ?></div>
                            <div class="text-muted" style="font-size:.71rem;">Done <?= $bl['cnt'] ?> time<?= $bl['cnt']!=1?'s':'' ?></div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-info" style="font-size:.88rem;">₱<?= number_format($bl['revenue'],0) ?></div>
                            <div class="text-muted" style="font-size:.7rem;">total</div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- TOP CUSTOMERS -->
        <div class="col-lg-4">
            <div class="card best-card h-100">
                <div class="card-header" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;">
                    <i class="bi bi-person-check me-2"></i>Top 5 Best Customers
                </div>
                <div class="card-body p-3">
                    <?php if (empty($bestCustomers)): ?>
                    <p class="text-muted text-center">No customer data yet.</p>
                    <?php else: foreach ($bestCustomers as $idx => $bc): ?>
                    <div class="d-flex align-items-center gap-3 mb-3 pb-3 <?= $idx < count($bestCustomers)-1 ? 'border-bottom' : '' ?>">
                        <span class="rank-badge rank-<?= $idx < 3 ? ($idx+1) : 'other' ?>"><?= $idx+1 ?></span>
                        <div class="flex-grow-1">
                            <div class="fw-600" style="font-size:.83rem;"><?= htmlspecialchars($bc['customer_name'] ?: '(No name)') ?></div>
                            <div class="text-muted" style="font-size:.71rem;"><?= htmlspecialchars($bc['plate_number'] ?: '—') ?> &bull; <?= $bc['visits'] ?> visit<?= $bc['visits']!=1?'s':'' ?></div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-success" style="font-size:.88rem;">₱<?= number_format($bc['total_spend'],0) ?></div>
                            <div class="text-muted" style="font-size:.7rem;">total spent</div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         CHARTS ROW 2: Top Products Bar | Parts vs Labor
    ══════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="chart-card">
                <h6><i class="bi bi-bar-chart-horizontal me-2" style="color:#06b6d4"></i>Best Products by Revenue</h6>
                <canvas id="topProductsChart" height="220"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card">
                <h6><i class="bi bi-graph-up me-2" style="color:#10b981"></i>Parts vs Labor – 30 Days</h6>
                <canvas id="partsLaborChart" height="220"></canvas>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         BOTTOM: Recent Transactions | Low Stock
    ══════════════════════════════════════════════════════════════ -->
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="chart-card">
                <h6><i class="bi bi-receipt me-2" style="color:var(--primary)"></i>Recent Transactions</h6>
                <div class="table-responsive">
                    <table class="table dash-table mb-0">
                        <thead>
                            <tr><th>#</th><th>Date</th><th>Customer</th><th>Plate</th><th>Parts ₱</th><th>Labor ₱</th><th>Total ₱</th></tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentSales)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No sales yet. <a href="sales.php">Record one →</a></td></tr>
                        <?php else: foreach ($recentSales as $s): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= $s['id'] ?></span></td>
                            <td><?= date('M d', strtotime($s['sale_date'])) ?></td>
                            <td><?= htmlspecialchars($s['customer_name']?:'—') ?></td>
                            <td><?= htmlspecialchars($s['plate_number']?:'—') ?></td>
                            <td class="text-primary fw-bold">₱<?= number_format($s['parts_total'],0) ?></td>
                            <td class="text-success fw-bold">₱<?= number_format($s['labor_total'],0) ?></td>
                            <td><strong>₱<?= number_format($s['grand_total'],0) ?></strong></td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-2">
                    <a href="sales_history.php" class="btn btn-sm btn-outline-primary">View All Sales →</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- LOW STOCK -->
            <?php if ($lowStockCount > 0): ?>
            <div class="chart-card mb-3">
                <h6 class="text-warning"><i class="bi bi-exclamation-triangle me-2"></i>Low Stock (<?= $lowStockCount ?>)</h6>
                <?php foreach ($lowStockItems as $ls):
                    $crit = $ls['current_stock'] <= 0;
                ?>
                <div class="low-stock-item <?= $crit ? 'critical' : '' ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong style="font-size:.82rem"><?= htmlspecialchars($ls['description']) ?></strong>
                            <div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($ls['category_name']) ?></div>
                        </div>
                        <div class="text-end">
                            <span class="badge <?= $crit ? 'bg-danger' : 'bg-warning text-dark' ?>"><?= $ls['current_stock'] ?> left</span>
                            <div class="text-muted" style="font-size:.68rem">Min: <?= $ls['reorder_threshold'] ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <a href="inventory.php#reorder-panel" class="btn btn-sm btn-warning w-100 mt-1">Restock Now</a>
            </div>
            <?php endif; ?>

            <!-- OPEN WORK ORDERS -->
            <?php if ($openWO > 0): ?>
            <div class="chart-card">
                <h6><i class="bi bi-tools me-2" style="color:#764ba2"></i>Open Work Orders</h6>
                <div class="text-center py-2">
                    <div style="font-size:3rem;font-weight:800;color:#764ba2;"><?= $openWO ?></div>
                    <div class="text-muted">pending work orders</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── DATA FROM PHP ────────────────────────────────────────────────
const DAYS    = <?= $jsonDays ?>;
const BEST_P  = <?= $jsonBestP ?>;
const BEST_C  = <?= $jsonBestC ?>;
const EXP_CAT = <?= $jsonExpCats ?>;

const PALETTE = ['#667eea','#ef4444','#10b981','#f97316','#06b6d4','#8b5cf6','#f59e0b','#ec4899','#3b82f6','#14b8a6'];

Chart.defaults.font.family = "'Segoe UI', sans-serif";
Chart.defaults.plugins.legend.labels.usePointStyle = true;

// ── 1. REVENUE vs EXPENSES vs PROFIT ────────────────────────────
(function () {
    const ctx = document.getElementById('revenueExpensesChart')?.getContext('2d');
    if (!ctx) return;

    const labels   = DAYS.map(d => d.label);
    const revenues = DAYS.map(d => d.revenue);
    const expenses = DAYS.map(d => d.expenses);
    const profits  = DAYS.map(d => d.profit);

    const gRev = ctx.createLinearGradient(0, 0, 0, 260);
    gRev.addColorStop(0, 'rgba(102,126,234,.35)');
    gRev.addColorStop(1, 'rgba(102,126,234,.01)');

    const gExp = ctx.createLinearGradient(0, 0, 0, 260);
    gExp.addColorStop(0, 'rgba(239,68,68,.25)');
    gExp.addColorStop(1, 'rgba(239,68,68,.01)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Revenue ₱',
                    data: revenues,
                    borderColor: '#667eea', backgroundColor: gRev,
                    fill: true, tension: 0.4, borderWidth: 2.5,
                    pointRadius: 2, pointHoverRadius: 6
                },
                {
                    label: 'Expenses ₱',
                    data: expenses,
                    borderColor: '#ef4444', backgroundColor: gExp,
                    fill: true, tension: 0.4, borderWidth: 2,
                    borderDash: [5, 3], pointRadius: 2, pointHoverRadius: 5
                },
                {
                    label: 'Profit ₱',
                    data: profits,
                    borderColor: '#10b981', backgroundColor: 'transparent',
                    fill: false, tension: 0.4, borderWidth: 2.5,
                    pointRadius: 2, pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + ctx.dataset.label + ': ₱' + ctx.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 0 })
                    }
                }
            },
            scales: {
                x: { ticks: { maxTicksLimit: 10, font: { size: 10 } }, grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v) }
                }
            }
        }
    });
})();

// ── 2. EXPENSE CATEGORY DONUT ────────────────────────────────────
(function () {
    const ctx = document.getElementById('expCatChart')?.getContext('2d');
    if (!ctx || !EXP_CAT.length) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: EXP_CAT.map(c => c.category),
            datasets: [{ data: EXP_CAT.map(c => parseFloat(c.total)), backgroundColor: PALETTE, borderWidth: 2, borderColor: '#fff' }]
        },
        options: {
            cutout: '60%',
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 8 } },
                tooltip: { callbacks: { label: ctx => ' ₱' + ctx.parsed.toLocaleString('en-PH', { minimumFractionDigits: 0 }) } }
            }
        }
    });
})();

// ── 3. TOP PRODUCTS BAR ───────────────────────────────────────────
(function () {
    const ctx = document.getElementById('topProductsChart')?.getContext('2d');
    if (!ctx || !BEST_P.length) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: BEST_P.map(p => p.code || p.description.substring(0, 16)),
            datasets: [{
                label: 'Revenue ₱',
                data: BEST_P.map(p => parseFloat(p.revenue)),
                backgroundColor: PALETTE.slice(0, BEST_P.length),
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ₱' + ctx.parsed.x.toLocaleString('en-PH', { minimumFractionDigits: 0 }),
                        afterLabel: ctx => ` ${BEST_P[ctx.dataIndex].qty_sold} units sold`
                    }
                }
            },
            scales: {
                x: { beginAtZero: true, ticks: { callback: v => '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v) } },
                y: { grid: { display: false } }
            }
        }
    });
})();

// ── 4. PARTS vs LABOR LINE ────────────────────────────────────────
(function () {
    const ctx = document.getElementById('partsLaborChart')?.getContext('2d');
    if (!ctx) return;

    const gP = ctx.createLinearGradient(0, 0, 0, 260);
    gP.addColorStop(0, 'rgba(102,126,234,.35)');
    gP.addColorStop(1, 'rgba(102,126,234,.01)');

    const gL = ctx.createLinearGradient(0, 0, 0, 260);
    gL.addColorStop(0, 'rgba(16,185,129,.35)');
    gL.addColorStop(1, 'rgba(16,185,129,.01)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: DAYS.map(d => d.label),
            datasets: [
                {
                    label: 'Parts ₱',
                    data: DAYS.map(d => d.parts),
                    borderColor: '#667eea', backgroundColor: gP,
                    fill: true, tension: 0.4, borderWidth: 2, pointRadius: 2, pointHoverRadius: 5
                },
                {
                    label: 'Labor ₱',
                    data: DAYS.map(d => d.labor),
                    borderColor: '#10b981', backgroundColor: gL,
                    fill: true, tension: 0.4, borderWidth: 2, pointRadius: 2, pointHoverRadius: 5
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + ctx.dataset.label + ': ₱' + ctx.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 0 })
                    }
                }
            },
            scales: {
                x: { ticks: { maxTicksLimit: 8, font: { size: 10 } }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { callback: v => '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v) } }
            }
        }
    });
})();
</script>
</body>
</html>