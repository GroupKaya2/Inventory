<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$activePage = 'dashboard';
$isOwner    = ($_SESSION['role'] ?? 'manager') === 'owner';
$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

// Ensure expenses table exists
$conn->query("CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'Other',
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS payment_method ENUM('cash','gcash') NOT NULL DEFAULT 'cash'");

// ── KPI: Today ──
$todayRow = $conn->query("
    SELECT COALESCE(SUM(parts_total+labor_total),0) AS revenue,
           COALESCE(SUM(parts_total),0) AS parts,
           COALESCE(SUM(labor_total),0) AS labor,
           COUNT(*) AS cnt
    FROM sales WHERE sale_date='$today'
")->fetch_assoc();
$todayExp = (float)$conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE expense_date='$today'")->fetch_assoc()['t'];

// ── KPI: Month ──
$monthRow = $conn->query("
    SELECT COALESCE(SUM(parts_total+labor_total),0) AS revenue, COUNT(*) AS cnt
    FROM sales WHERE sale_date BETWEEN '$monthStart' AND '$monthEnd'
")->fetch_assoc();
$monthExp = (float)$conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE expense_date BETWEEN '$monthStart' AND '$monthEnd'")->fetch_assoc()['t'];

$todayRevenue = (float)$todayRow['revenue'];
$todayProfit  = $todayRevenue - $todayExp;
$monthRevenue = (float)$monthRow['revenue'];
$monthProfit  = $monthRevenue - $monthExp;
$monthMargin  = $monthRevenue > 0 ? ($monthProfit / $monthRevenue) * 100 : 0;

// ── Chart 1: Last 30 days revenue/expenses/profit ──
$last30 = [];
for ($i = 29; $i >= 0; $i--) {
    $d   = date('Y-m-d', strtotime("-$i days"));
    $rev = (float)$conn->query("SELECT COALESCE(SUM(parts_total+labor_total),0) AS t FROM sales WHERE sale_date='$d'")->fetch_assoc()['t'];
    $exp = (float)$conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE expense_date='$d'")->fetch_assoc()['t'];
    $last30[] = ['label' => date('M d', strtotime($d)), 'revenue' => $rev, 'expenses' => $exp, 'profit' => $rev - $exp];
}

// ── Chart 2: Monthly trend (last 12 months) ──
$monthly12 = [];
for ($i = 11; $i >= 0; $i--) {
    $ms  = date('Y-m-01', strtotime("-$i months"));
    $me  = date('Y-m-t',  strtotime("-$i months"));
    $lbl = date('M Y',    strtotime($ms));
    $rev = (float)$conn->query("SELECT COALESCE(SUM(parts_total+labor_total),0) AS t FROM sales WHERE sale_date BETWEEN '$ms' AND '$me'")->fetch_assoc()['t'];
    $exp = (float)$conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE expense_date BETWEEN '$ms' AND '$me'")->fetch_assoc()['t'];
    $monthly12[] = ['label' => $lbl, 'revenue' => $rev, 'expenses' => $exp, 'profit' => $rev - $exp];
}

// ── Chart 3: Top 10 products by qty sold (all time) ──
$top10products = [];
$r = $conn->query("
    SELECT
        COALESCE(NULLIF(TRIM(si.description),''), p.description, 'Unknown Product') AS description,
        COALESCE(p.code,'') AS code,
        SUM(si.quantity) AS qty_sold,
        SUM(si.amount)   AS revenue
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.product_id
    WHERE si.line_type = 'parts'
    GROUP BY COALESCE(NULLIF(TRIM(si.description),''), p.description, 'Unknown Product')
    ORDER BY qty_sold DESC
    LIMIT 10
");
if ($r) while ($row = $r->fetch_assoc()) $top10products[] = $row;

// ── Chart 4: Revenue split parts vs labor (this month) ──
$splitParts = (float)$conn->query("SELECT COALESCE(SUM(parts_total),0) AS t FROM sales WHERE sale_date BETWEEN '$monthStart' AND '$monthEnd'")->fetch_assoc()['t'];
$splitLabor = (float)$conn->query("SELECT COALESCE(SUM(labor_total),0) AS t FROM sales WHERE sale_date BETWEEN '$monthStart' AND '$monthEnd'")->fetch_assoc()['t'];

// ── Chart 5: Low stock items ──
$stockItems = [];
$r = $conn->query("
    SELECT p.description, ps.current_stock, p.reorder_threshold
    FROM products p
    LEFT JOIN product_stock ps ON p.product_id = ps.product_id
    ORDER BY ps.current_stock ASC
    LIMIT 12
");
if ($r) while ($row = $r->fetch_assoc()) $stockItems[] = $row;

// ── Chart 6: Cash vs GCash per month (last 6 months) ──
$paymentSplit = [];
for ($i = 5; $i >= 0; $i--) {
    $ms  = date('Y-m-01', strtotime("-$i months"));
    $me  = date('Y-m-t',  strtotime("-$i months"));
    $lbl = date('M',      strtotime($ms));
    $cash  = (float)$conn->query("SELECT COALESCE(SUM(parts_total+labor_total),0) AS t FROM sales WHERE sale_date BETWEEN '$ms' AND '$me' AND payment_method='cash'")->fetch_assoc()['t'];
    $gcash = (float)$conn->query("SELECT COALESCE(SUM(parts_total+labor_total),0) AS t FROM sales WHERE sale_date BETWEEN '$ms' AND '$me' AND payment_method='gcash'")->fetch_assoc()['t'];
    $paymentSplit[] = ['label' => $lbl, 'cash' => $cash, 'gcash' => $gcash];
}

// ── Recent sales ──
$recentSales = [];
$r = $conn->query("SELECT id, sale_date, customer_name, payment_method, parts_total, labor_total, (parts_total+labor_total) AS grand_total FROM sales ORDER BY id DESC LIMIT 8");
if ($r) while ($row = $r->fetch_assoc()) $recentSales[] = $row;

// ── Low stock alert ──
$lowStockAlert = [];
$r = $conn->query("SELECT description, current_stock, reorder_threshold, category_name FROM product_stock WHERE current_stock <= reorder_threshold ORDER BY current_stock ASC LIMIT 8");
if ($r) while ($row = $r->fetch_assoc()) $lowStockAlert[] = $row;

// Total inventory items
$totalProducts = (int)$conn->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()['c'];
$lowStockCount = (int)$conn->query("SELECT COUNT(*) AS c FROM product_stock WHERE current_stock <= reorder_threshold")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — DSpeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
    /* ── Chart containers ── */
    .chart-wrap {
        background: #111827;
        border: 1px solid rgba(255,255,255,.06);
        border-radius: 10px;
        padding: 18px 18px 12px;
    }
    .chart-hdr {
        display:flex;align-items:center;justify-content:space-between;
        margin-bottom: 14px;
        flex-wrap: wrap; gap: 8px;
    }
    .chart-title {
        font-family: 'Space Grotesk', sans-serif;
        font-size: .88rem; font-weight: 600; color: #e2e8f0; margin: 0;
    }
    .chart-sub { font-size: .72rem; color: #4b5a6e; margin-top: 2px; }
    .chart-badge {
        font-size: .68rem; font-weight: 600; padding: 3px 9px;
        border-radius: 5px; white-space: nowrap;
    }
    .cb-green { background: rgba(74,222,128,.1); color: #4ade80; }
    .cb-blue  { background: rgba(96,165,250,.1); color: #60a5fa; }
    .cb-amber { background: rgba(251,191,36,.1); color: #fbbf24; }
    .cb-purple{ background: rgba(167,139,250,.1);color: #a78bfa; }

    /* ── Chart legend ── */
    .clegend { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:10px; }
    .clegend span { display:flex; align-items:center; gap:5px; font-size:.72rem; color:#64748b; }
    .cleg-sq { width:10px; height:10px; border-radius:2px; flex-shrink:0; }
    .cleg-line { width:14px; height:2px; flex-shrink:0; }
    .cleg-dash { width:14px; height:0; border-top:2px dashed; flex-shrink:0; }

    /* ── Tab buttons ── */
    .chart-tabs { display:flex; gap:4px; }
    .ctab {
        background: none; border: 1px solid rgba(255,255,255,.08);
        border-radius: 6px; color: #4b5a6e;
        font-size: .72rem; font-weight: 600; padding: 4px 10px;
        cursor: pointer; transition: all .15s; font-family: 'Inter', sans-serif;
    }
    .ctab.active { background: rgba(74,222,128,.1); border-color: rgba(74,222,128,.25); color: #4ade80; }
    .ctab:hover:not(.active) { color: #e2e8f0; border-color: rgba(255,255,255,.18); }

    /* ── Monthly summary bar ── */
    .month-bar {
        background: #111827;
        border: 1px solid rgba(74,222,128,.12);
        border-radius: 10px;
        padding: 14px 20px;
        display: flex; flex-wrap: wrap; gap: 20px; align-items: center;
    }
    .mb-item .lbl { font-size:.65rem;font-weight:600;text-transform:uppercase;letter-spacing:.6px;color:#4b5a6e; }
    .mb-item .val { font-family:'Space Grotesk',sans-serif;font-size:1.1rem;font-weight:700; }
    .mb-sep { color:#2e3a4e; font-size:1rem; align-self:flex-end; padding-bottom:3px; }

    /* ── Tooltip override ── */
    .tooltip-dark {
        background: #1c2336 !important;
        border: 1px solid rgba(74,222,128,.18) !important;
        color: #e2e8f0 !important;
    }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<main class="app-main">

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 style="margin:0;font-family:'Space Grotesk',sans-serif;color:#fff;">
                <i class="bi bi-grid-1x2-fill me-2" style="color:#4ade80;"></i>Dashboard
            </h4>
            <small style="color:#4b5a6e;">
                <?= htmlspecialchars($_SESSION['user'] ?? 'User') ?> &bull; <?= date('l, F j, Y') ?>
            </small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="sales.php" class="btn-pink"><i class="bi bi-plus-lg me-1"></i>New Sale</a>
            <a href="inventory.php" class="btn-ghost"><i class="bi bi-box-seam me-1"></i>Inventory</a>
        </div>
    </div>

    <!-- KPI Row -->
    <p class="section-title mb-3">Today — <?= date('F j, Y') ?></p>
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="kpi-card">
                <div class="kpi-icon green"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="kpi-label">Revenue</div>
                    <div class="kpi-value">₱<?= number_format($todayRevenue, 0) ?></div>
                    <div class="kpi-sub"><?= $todayRow['cnt'] ?> sale(s) today</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card">
                <div class="kpi-icon red"><i class="bi bi-wallet2"></i></div>
                <div>
                    <div class="kpi-label">Expenses</div>
                    <div class="kpi-value" style="color:#f87171;">₱<?= number_format($todayExp, 0) ?></div>
                    <div class="kpi-sub"><a href="expenses.php" style="color:#4b5a6e;font-size:.72rem;">manage →</a></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card">
                <div class="kpi-icon <?= $todayProfit >= 0 ? 'green' : 'orange' ?>">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div>
                    <div class="kpi-label">Net Profit</div>
                    <div class="kpi-value <?= $todayProfit >= 0 ? 'text-profit' : 'text-loss' ?>">
                        <?= $todayProfit >= 0 ? '' : '−' ?>₱<?= number_format(abs($todayProfit), 0) ?>
                    </div>
                    <div class="kpi-sub">Revenue − Expenses</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="kpi-card">
                <div class="kpi-icon teal"><i class="bi bi-wrench-adjustable"></i></div>
                <div>
                    <div class="kpi-label">Labor Today</div>
                    <div class="kpi-value">₱<?= number_format((float)$todayRow['labor'], 0) ?></div>
                    <div class="kpi-sub">Parts: ₱<?= number_format((float)$todayRow['parts'], 0) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Summary Bar -->
    <div class="month-bar mb-4">
        <div class="mb-item">
            <div class="lbl">Monthly Revenue</div>
            <div class="val" style="color:#4ade80;">₱<?= number_format($monthRevenue, 2) ?></div>
        </div>
        <div class="mb-sep">−</div>
        <div class="mb-item">
            <div class="lbl">Monthly Expenses</div>
            <div class="val" style="color:#f87171;">₱<?= number_format($monthExp, 2) ?></div>
        </div>
        <div class="mb-sep">=</div>
        <div class="mb-item">
            <div class="lbl">Net Profit</div>
            <div class="val <?= $monthProfit >= 0 ? 'text-profit' : 'text-loss' ?>">
                <?= $monthProfit >= 0 ? '' : '−' ?>₱<?= number_format(abs($monthProfit), 2) ?>
            </div>
        </div>
        <div class="mb-item">
            <div class="lbl">Transactions</div>
            <div class="val" style="color:#60a5fa;"><?= $monthRow['cnt'] ?></div>
        </div>
        <?php if ($monthRevenue > 0): ?>
        <div class="ms-auto" style="min-width:160px;">
            <div style="font-size:.65rem;color:#4b5a6e;margin-bottom:5px;">Profit Margin</div>
            <div class="progress" style="height:4px;border-radius:3px;background:rgba(255,255,255,.07);">
                <div class="progress-bar <?= $monthMargin >= 0 ? 'bg-success' : 'bg-danger' ?>"
                    style="width:<?= min(abs($monthMargin), 100) ?>%"></div>
            </div>
            <small class="<?= $monthMargin >= 0 ? 'text-profit' : 'text-loss' ?>" style="font-size:.7rem;">
                <?= $monthMargin >= 0 ? '+' : '' ?><?= number_format($monthMargin, 1) ?>% margin
            </small>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ CHART ROW 1: Revenue trend + Donut ═══ -->
    <div class="row g-3 mb-3">

        <!-- Chart 1 & 2 combined: toggleable line chart -->
        <div class="col-lg-8">
            <div class="chart-wrap">
                <div class="chart-hdr">
                    <div>
                        <div class="chart-title"><i class="bi bi-graph-up me-2" style="color:#4ade80;"></i>Revenue Trend</div>
                        <div class="chart-sub" id="trendSub">Last 30 days — daily view</div>
                    </div>
                    <div class="chart-tabs">
                        <button class="ctab active" onclick="switchTrend('daily',this)">30 Days</button>
                        <button class="ctab" onclick="switchTrend('monthly',this)">12 Months</button>
                    </div>
                </div>
                <div class="clegend">
                    <span><span class="cleg-line" style="background:#4ade80;"></span>Revenue</span>
                    <span><span class="cleg-dash" style="border-color:#f87171;"></span>Expenses</span>
                    <span><span class="cleg-line" style="background:#60a5fa;"></span>Profit</span>
                </div>
                <div style="position:relative;height:220px;">
                    <canvas id="trendChart" role="img" aria-label="Revenue trend line chart">Revenue, expenses and profit over time.</canvas>
                </div>
            </div>
        </div>

        <!-- Chart 4: Parts vs Labor Donut -->
        <div class="col-lg-4">
            <div class="chart-wrap" style="height:100%;">
                <div class="chart-hdr">
                    <div>
                        <div class="chart-title"><i class="bi bi-pie-chart-fill me-2" style="color:#60a5fa;"></i>Revenue Split</div>
                        <div class="chart-sub">Parts vs Labor — this month</div>
                    </div>
                    <span class="chart-badge cb-blue">
                        ₱<?= number_format($splitParts + $splitLabor, 0) ?>
                    </span>
                </div>
                <?php
                    $splitTotal = $splitParts + $splitLabor;
                    $pct_parts = $splitTotal > 0 ? round(($splitParts / $splitTotal) * 100) : 0;
                    $pct_labor = $splitTotal > 0 ? round(($splitLabor / $splitTotal) * 100) : 0;
                ?>
                <div class="clegend">
                    <span><span class="cleg-sq" style="background:#378ADD;"></span>Parts <?= $pct_parts ?>%</span>
                    <span><span class="cleg-sq" style="background:#1D9E75;"></span>Labor <?= $pct_labor ?>%</span>
                </div>
                <div style="position:relative;height:180px;">
                    <canvas id="donutChart" role="img" aria-label="Donut chart of parts vs labor revenue">Parts: <?= $pct_parts ?>%, Labor: <?= $pct_labor ?>%</canvas>
                </div>
                <?php if ($splitTotal == 0): ?>
                <div style="text-align:center;color:#4b5a6e;font-size:.8rem;padding:10px 0;">No sales this month yet.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ═══ CHART ROW 2: Top Products + Payment Split ═══ -->
    <div class="row g-3 mb-3">

        <!-- Chart 3: Top 10 Products -->
        <div class="col-lg-8">
            <div class="chart-wrap">
                <div class="chart-hdr">
                    <div>
                        <div class="chart-title"><i class="bi bi-bar-chart-horizontal me-2" style="color:#a78bfa;"></i>Top 10 Products Sold</div>
                        <div class="chart-sub">By units sold — all time</div>
                    </div>
                    <div class="chart-tabs">
                        <button class="ctab active" onclick="switchProducts('qty',this)">By Qty</button>
                        <button class="ctab" onclick="switchProducts('revenue',this)">By Revenue</button>
                    </div>
                </div>
                <div class="clegend">
                    <span><span class="cleg-sq" style="background:#7F77DD;"></span>Units sold</span>
                </div>
                <?php if (empty($top10products)): ?>
                <div style="text-align:center;color:#4b5a6e;padding:40px 0;font-size:.85rem;">
                    <i class="bi bi-box-seam" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                    No sales data yet.
                </div>
                <?php else: ?>
                <div style="position:relative;height:<?= max(200, count($top10products) * 32 + 40) ?>px;">
                    <canvas id="productsChart" role="img" aria-label="Horizontal bar chart of top products by units sold">Top products ranked by sales volume.</canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chart 6: Cash vs GCash -->
        <div class="col-lg-4">
            <div class="chart-wrap" style="height:100%;">
                <div class="chart-hdr">
                    <div>
                        <div class="chart-title"><i class="bi bi-credit-card-2-front me-2" style="color:#fbbf24;"></i>Payment Methods</div>
                        <div class="chart-sub">Cash vs GCash — last 6 months</div>
                    </div>
                </div>
                <div class="clegend">
                    <span><span class="cleg-sq" style="background:#378ADD;"></span>Cash</span>
                    <span><span class="cleg-sq" style="background:#7F77DD;"></span>GCash</span>
                </div>
                <div style="position:relative;height:220px;">
                    <canvas id="paymentChart" role="img" aria-label="Stacked bar chart of cash vs GCash payments per month">Payment method breakdown by month.</canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- ═══ CHART ROW 3: Stock Levels ═══ -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="chart-wrap">
                <div class="chart-hdr">
                    <div>
                        <div class="chart-title"><i class="bi bi-boxes me-2" style="color:#22d3ee;"></i>Stock Level Monitor</div>
                        <div class="chart-sub">Current stock vs reorder threshold — color coded alerts</div>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <div class="clegend" style="margin:0;">
                            <span><span class="cleg-sq" style="background:#E24B4A;"></span>Out of stock</span>
                            <span><span class="cleg-sq" style="background:#BA7517;"></span>Low stock</span>
                            <span><span class="cleg-sq" style="background:#1D9E75;"></span>OK</span>
                        </div>
                        <?php if ($lowStockCount > 0): ?>
                        <span class="chart-badge cb-amber">
                            <i class="bi bi-exclamation-triangle me-1"></i><?= $lowStockCount ?> need restock
                        </span>
                        <?php endif; ?>
                        <a href="inventory.php" class="chart-badge cb-green" style="text-decoration:none;">
                            <i class="bi bi-arrow-right me-1"></i>Manage Stock
                        </a>
                    </div>
                </div>
                <?php if (empty($stockItems)): ?>
                <div style="text-align:center;color:#4b5a6e;padding:30px 0;font-size:.85rem;">No products found in inventory.</div>
                <?php else: ?>
                <div style="position:relative;height:<?= max(180, count($stockItems) * 30 + 40) ?>px;">
                    <canvas id="stockChart" role="img" aria-label="Horizontal bar chart of stock levels">Stock levels for all products.</canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ═══ BOTTOM ROW: Recent Sales + Low Stock Alert ═══ -->
    <div class="row g-3">

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-0">
                    <div class="p-3 d-flex align-items-center justify-content-between" style="border-bottom:1px solid rgba(255,255,255,.06);">
                        <h6 style="margin:0;font-size:.88rem;color:#e2e8f0;">
                            <i class="bi bi-receipt me-2" style="color:#4ade80;"></i>Recent Transactions
                        </h6>
                        <a href="sales-history.php" class="btn-ghost" style="font-size:.75rem;padding:5px 12px;">View All →</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th><th>Date</th><th>Customer</th>
                                    <th>Payment</th><th>Parts ₱</th><th>Labor ₱</th><th>Total ₱</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentSales)): ?>
                                    <tr><td colspan="7" style="text-align:center;padding:24px;color:#4b5a6e;">
                                        No sales yet. <a href="sales.php">Record one →</a>
                                    </td></tr>
                                <?php else: foreach ($recentSales as $s):
                                    $pm = $s['payment_method'] ?? 'cash'; ?>
                                    <tr>
                                        <td><span class="badge-gray"><?= $s['id'] ?></span></td>
                                        <td><?= date('M d', strtotime($s['sale_date'])) ?></td>
                                        <td><?= htmlspecialchars($s['customer_name'] ?: '—') ?></td>
                                        <td>
                                            <?php if ($pm === 'gcash'): ?>
                                                <span class="badge-blue"><i class="bi bi-phone-fill me-1"></i>GCash</span>
                                            <?php else: ?>
                                                <span class="badge-green"><i class="bi bi-cash-coin me-1"></i>Cash</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color:#60a5fa;font-weight:600;">₱<?= number_format($s['parts_total'], 0) ?></td>
                                        <td style="color:#4ade80;font-weight:600;">₱<?= number_format($s['labor_total'], 0) ?></td>
                                        <td><strong>₱<?= number_format($s['grand_total'], 0) ?></strong></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <div class="col-lg-4">
            <?php if (!empty($lowStockAlert)): ?>
            <div class="card">
                <div class="card-body p-0">
                    <div class="p-3" style="border-bottom:1px solid rgba(255,255,255,.06);">
                        <h6 style="margin:0;font-size:.88rem;color:#fbbf24;">
                            <i class="bi bi-exclamation-triangle me-2"></i>Low Stock Alert (<?= count($lowStockAlert) ?>)
                        </h6>
                    </div>
                    <div class="p-3">
                        <?php foreach ($lowStockAlert as $item):
                            $critical = (int)$item['current_stock'] <= 0; ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;
                            padding:8px 10px;border-radius:8px;margin-bottom:7px;
                            background:<?= $critical ? 'rgba(248,113,113,.07)' : 'rgba(251,191,36,.06)' ?>;
                            border-left:3px solid <?= $critical ? '#f87171' : '#fbbf24' ?>;">
                            <div>
                                <div style="font-size:.8rem;font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($item['description']) ?></div>
                                <div style="font-size:.68rem;color:#4b5a6e;"><?= htmlspecialchars($item['category_name'] ?? '') ?></div>
                            </div>
                            <div style="text-align:right;">
                                <span class="<?= $critical ? 'badge-red' : 'badge-yellow' ?>"><?= $item['current_stock'] ?> left</span>
                                <div style="font-size:.63rem;color:#4b5a6e;margin-top:2px;">Min: <?= $item['reorder_threshold'] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <a href="inventory.php" class="btn-pink" style="width:100%;justify-content:center;margin-top:4px;font-size:.8rem;">
                            <i class="bi bi-box-seam me-1"></i>Restock Now
                        </a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card p-4 text-center">
                <i class="bi bi-check-circle" style="font-size:2rem;color:#4ade80;margin-bottom:10px;display:block;"></i>
                <div style="font-weight:600;color:#e2e8f0;font-family:'Space Grotesk',sans-serif;">All stock healthy</div>
                <div style="font-size:.78rem;color:#4b5a6e;margin-top:4px;"><?= $totalProducts ?> products · none below threshold</div>
                <a href="inventory.php" class="btn-ghost" style="margin-top:12px;font-size:.78rem;justify-content:center;">View Inventory →</a>
            </div>
            <?php endif; ?>
        </div>

    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
'use strict';

// ── Data from PHP ──
const DAILY    = <?= json_encode($last30,       JSON_UNESCAPED_UNICODE) ?>;
const MONTHLY  = <?= json_encode($monthly12,    JSON_UNESCAPED_UNICODE) ?>;
const PRODUCTS = <?= json_encode($top10products, JSON_UNESCAPED_UNICODE) ?>;
const PAYMENTS = <?= json_encode($paymentSplit,  JSON_UNESCAPED_UNICODE) ?>;
const STOCKS   = <?= json_encode($stockItems,    JSON_UNESCAPED_UNICODE) ?>;
const SPLIT    = { parts: <?= $splitParts ?>, labor: <?= $splitLabor ?> };

// ── Chart.js defaults ──
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = '#4b5a6e';

const TOOLTIP = {
    backgroundColor: '#1c2336',
    borderColor: 'rgba(74,222,128,.2)',
    borderWidth: 1,
    titleColor: '#e2e8f0',
    bodyColor: '#94a3b8',
    padding: 10,
    cornerRadius: 7,
};
const GRID = { color: 'rgba(255,255,255,.04)' };
const TICK = { color: '#4b5a6e', font: { size: 11 } };

function peso(v) { return '₱' + parseFloat(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 }); }

// ══════════════════════════════════
// CHART 1 & 2: Trend (toggleable)
// ══════════════════════════════════
let trendChart = null;
let trendMode  = 'daily';

function buildTrend(data) {
    const ctx = document.getElementById('trendChart')?.getContext('2d');
    if (!ctx) return;
    if (trendChart) { trendChart.destroy(); }

    trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(d => d.label),
            datasets: [
                {
                    label: 'Revenue',
                    data: data.map(d => d.revenue),
                    borderColor: '#4ade80',
                    backgroundColor: 'rgba(74,222,128,.07)',
                    fill: true, tension: 0.4,
                    borderWidth: 2, pointRadius: 2, pointHoverRadius: 5,
                    pointBackgroundColor: '#4ade80',
                },
                {
                    label: 'Expenses',
                    data: data.map(d => d.expenses),
                    borderColor: '#f87171',
                    backgroundColor: 'transparent',
                    fill: false, tension: 0.4,
                    borderWidth: 2, borderDash: [5, 3],
                    pointRadius: 2, pointHoverRadius: 4,
                    pointBackgroundColor: '#f87171',
                },
                {
                    label: 'Profit',
                    data: data.map(d => d.profit),
                    borderColor: '#60a5fa',
                    backgroundColor: 'transparent',
                    fill: false, tension: 0.4,
                    borderWidth: 2, pointRadius: 2, pointHoverRadius: 5,
                    pointBackgroundColor: '#60a5fa',
                },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...TOOLTIP,
                    callbacks: { label: c => ' ' + c.dataset.label + ': ' + peso(c.parsed.y) }
                }
            },
            scales: {
                x: { grid: GRID, ticks: { ...TICK, maxTicksLimit: 10, maxRotation: 0 } },
                y: { grid: GRID, ticks: { ...TICK, callback: v => v >= 1000 ? '₱' + (v/1000).toFixed(0) + 'k' : '₱' + v } }
            }
        }
    });
}

function switchTrend(mode, btn) {
    trendMode = mode;
    document.querySelectorAll('.ctab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('trendSub').textContent = mode === 'daily' ? 'Last 30 days — daily view' : 'Last 12 months — monthly view';
    buildTrend(mode === 'daily' ? DAILY : MONTHLY);
}

buildTrend(DAILY);

// ══════════════════════════════════
// CHART 3: Top Products (toggleable)
// ══════════════════════════════════
let productsChart = null;
let productsMode  = 'qty';

function buildProducts(mode) {
    const ctx = document.getElementById('productsChart')?.getContext('2d');
    if (!ctx || !PRODUCTS.length) return;
    if (productsChart) { productsChart.destroy(); }

    const sorted = [...PRODUCTS].sort((a, b) =>
        mode === 'qty' ? b.qty_sold - a.qty_sold : b.revenue - a.revenue
    );
    const labels = sorted.map(p => (p.description && p.description.trim()) ? p.description.trim() : ('Product #' + (sorted.indexOf(p) + 1)));
    const values = sorted.map(p => mode === 'qty' ? parseFloat(p.qty_sold) : parseFloat(p.revenue));
    const barColors = sorted.map((_, i) => {
        const palette = ['#7F77DD','#6366f1','#8b5cf6','#a78bfa','#818cf8','#7c3aed','#6d28d9','#5b21b6','#4c1d95','#7e22ce'];
        return palette[i % palette.length];
    });

    productsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: mode === 'qty' ? 'Units sold' : 'Revenue',
                data: values,
                backgroundColor: barColors,
                borderRadius: 4, borderSkipped: false,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            layout: { padding: { left: 0 } },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...TOOLTIP,
                    callbacks: {
                        title: c => sorted[c[0].dataIndex]?.description || '',
                        label: c => mode === 'qty'
                            ? '  ' + Math.round(c.parsed.x) + ' units'
                            : '  ' + peso(c.parsed.x)
                    }
                }
            },
            scales: {
                x: {
                    grid: GRID,
                    ticks: { ...TICK, callback: v => mode === 'qty' ? v : (v >= 1000 ? '₱'+(v/1000).toFixed(0)+'k' : '₱'+v) }
                },
                y: {
                    grid: { display: false },
                    ticks: {
                        ...TICK,
                        font: { size: 11, family: "'Inter', sans-serif" },
                        color: '#94a3b8',
                        autoSkip: false,
                        crossAlign: 'far',
                    },
                    afterFit(scale) { scale.width = 180; }
                }
            }
        }
    });
}

function switchProducts(mode, btn) {
    productsMode = mode;
    document.querySelectorAll('[onclick^="switchProducts"]').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    buildProducts(mode);
}

buildProducts('qty');

// ══════════════════════════════════
// CHART 4: Donut — Parts vs Labor
// ══════════════════════════════════
(function () {
    const ctx = document.getElementById('donutChart')?.getContext('2d');
    if (!ctx) return;
    const total = SPLIT.parts + SPLIT.labor;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Parts', 'Labor'],
            datasets: [{
                data: total > 0 ? [SPLIT.parts, SPLIT.labor] : [1, 1],
                backgroundColor: total > 0 ? ['#378ADD', '#1D9E75'] : ['#1e2a3a', '#1e2a3a'],
                borderWidth: 0,
                hoverOffset: 6,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...TOOLTIP,
                    callbacks: {
                        label: c => {
                            if (total === 0) return ' No data';
                            const pct = Math.round((c.parsed / total) * 100);
                            return ' ' + c.label + ': ' + peso(c.parsed) + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
})();

// ══════════════════════════════════
// CHART 5: Stock Levels
// ══════════════════════════════════
(function () {
    const ctx = document.getElementById('stockChart')?.getContext('2d');
    if (!ctx || !STOCKS.length) return;

    const labels = STOCKS.map(s => s.description);
    const values = STOCKS.map(s => Math.max(0, parseInt(s.current_stock) || 0));
    const thresh = STOCKS.map(s => parseInt(s.reorder_threshold) || 5);
    const colors = STOCKS.map((s, i) => {
        const stock = parseInt(s.current_stock) || 0;
        if (stock <= 0) return '#E24B4A';
        if (stock <= thresh[i]) return '#BA7517';
        return '#1D9E75';
    });

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Current Stock',
                    data: values,
                    backgroundColor: colors,
                    borderRadius: 4, borderSkipped: false,
                    order: 1,
                },
                {
                    label: 'Reorder Threshold',
                    data: thresh,
                    type: 'line',
                    borderColor: 'rgba(251,191,36,.5)',
                    borderWidth: 1.5,
                    borderDash: [4, 3],
                    pointRadius: 0,
                    fill: false,
                    order: 0,
                }
            ],
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...TOOLTIP,
                    callbacks: {
                        label: c => {
                            if (c.datasetIndex === 1) return ' Threshold: ' + c.parsed.x;
                            const stock = c.parsed.x;
                            const status = stock <= 0 ? ' ⛔ Out of stock' : stock <= thresh[c.dataIndex] ? ' ⚠ Low stock' : ' ✓ OK';
                            return ' Stock: ' + stock + status;
                        }
                    }
                }
            },
            scales: {
                x: { grid: GRID, ticks: { ...TICK }, min: 0 },
                y: {
                    grid: { display: false },
                    ticks: {
                        ...TICK,
                        font: { size: 11, family: "'Inter', sans-serif" },
                        color: '#94a3b8',
                        autoSkip: false,
                        crossAlign: 'far',
                    },
                    afterFit(scale) { scale.width = 180; }
                }
            }
        }
    });
})();

// ══════════════════════════════════
// CHART 6: Cash vs GCash
// ══════════════════════════════════
(function () {
    const ctx = document.getElementById('paymentChart')?.getContext('2d');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: PAYMENTS.map(p => p.label),
            datasets: [
                {
                    label: 'Cash',
                    data: PAYMENTS.map(p => p.cash),
                    backgroundColor: '#378ADD',
                    stack: 'pay', borderRadius: 4, borderSkipped: false,
                },
                {
                    label: 'GCash',
                    data: PAYMENTS.map(p => p.gcash),
                    backgroundColor: '#7F77DD',
                    stack: 'pay', borderRadius: 4, borderSkipped: false,
                }
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...TOOLTIP,
                    callbacks: { label: c => ' ' + c.dataset.label + ': ' + peso(c.parsed.y) }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { ...TICK }, stacked: true },
                y: { grid: GRID, ticks: { ...TICK, callback: v => v >= 1000 ? '₱'+(v/1000).toFixed(0)+'k' : '₱'+v }, stacked: true }
            }
        }
    });
})();
</script>
</body>
</html>
