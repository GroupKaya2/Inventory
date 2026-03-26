<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$activePage = 'dashboard';
$today      = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

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

// Today's Sales
$todayRow = $conn->query("
    SELECT
        COALESCE(SUM(parts_total + labor_total), 0) AS revenue,
        COALESCE(SUM(parts_total), 0)               AS parts,
        COALESCE(SUM(labor_total), 0)               AS labor,
        COUNT(*) AS count
    FROM sales WHERE sale_date = '$today'
")->fetch_assoc();

// Today's Expenses
$todayExp = (float) $conn->query("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM expenses WHERE expense_date = '$today'
")->fetch_assoc()['total'];

// This Month
$monthRow = $conn->query("
    SELECT
        COALESCE(SUM(parts_total + labor_total), 0) AS revenue,
        COUNT(*) AS count
    FROM sales WHERE sale_date BETWEEN '$monthStart' AND '$monthEnd'
")->fetch_assoc();

$monthExp = (float) $conn->query("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM expenses WHERE expense_date BETWEEN '$monthStart' AND '$monthEnd'
")->fetch_assoc()['total'];

$todayRevenue = (float) $todayRow['revenue'];
$todayProfit  = $todayRevenue - $todayExp;
$monthRevenue = (float) $monthRow['revenue'];
$monthProfit  = $monthRevenue - $monthExp;

// Low Stock Items
$lowStockItems = [];
$ls = $conn->query("
    SELECT description, current_stock, reorder_threshold, category_name
    FROM product_stock
    WHERE current_stock <= reorder_threshold
    ORDER BY current_stock ASC
    LIMIT 8
");
if ($ls) while ($row = $ls->fetch_assoc()) $lowStockItems[] = $row;

// Recent Sales
$recentSales = [];
$rs = $conn->query("
    SELECT id, sale_date, customer_name, plate_number,
        parts_total, labor_total,
        (parts_total + labor_total) AS grand_total
    FROM sales
    ORDER BY created_at DESC
    LIMIT 8
");
if ($rs) while ($row = $rs->fetch_assoc()) $recentSales[] = $row;

// Last 30 days for chart
$chartDays = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));

    $rev = (float) $conn->query("
        SELECT COALESCE(SUM(parts_total + labor_total), 0) AS t
        FROM sales WHERE sale_date = '$d'
    ")->fetch_assoc()['t'];

    $exp = (float) $conn->query("
        SELECT COALESCE(SUM(amount), 0) AS t
        FROM expenses WHERE expense_date = '$d'
    ")->fetch_assoc()['t'];

    $chartDays[] = [
        'label'    => date('M d', strtotime($d)),
        'revenue'  => $rev,
        'expenses' => $exp,
        'profit'   => $rev - $exp,
    ];
}

// Top 5 Products
$topProducts = [];
$tp = $conn->query("
    SELECT p.description, p.code,
        SUM(si.quantity) AS qty_sold,
        SUM(si.amount)   AS revenue
    FROM sale_items si
    JOIN products p ON si.product_id = p.product_id
    WHERE si.line_type = 'parts'
    GROUP BY si.product_id
    ORDER BY revenue DESC
    LIMIT 5
");
if ($tp) while ($row = $tp->fetch_assoc()) $topProducts[] = $row;

// Top 5 Customers
$topCustomers = [];
$tc = $conn->query("
    SELECT customer_name, plate_number,
        COUNT(*) AS visits,
        SUM(parts_total + labor_total) AS total_spend
    FROM sales
    WHERE customer_name != ''
    GROUP BY customer_name, plate_number
    ORDER BY total_spend DESC
    LIMIT 5
");
if ($tc) while ($row = $tc->fetch_assoc()) $topCustomers[] = $row;

$jsChart    = json_encode($chartDays);
$jsProducts = json_encode($topProducts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DSPEEDWAY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="app-main">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 style="margin:0;font-family:'Syne',sans-serif;color:#fff;">
                <i class="bi bi-speedometer2 me-2" style="color:#e8175d;"></i>Dashboard
            </h4>
            <small style="color:#7a8499;">
                <?= htmlspecialchars($_SESSION['user'] ?? 'Admin') ?>
                &bull; <?= date('l, F j, Y') ?>
            </small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="sales.php" class="btn-pink"><i class="bi bi-plus-lg"></i> New Sale</a>
            <a href="inventory.php" class="btn-ghost"><i class="bi bi-box-seam"></i> Inventory</a>
        </div>
    </div>

    <p class="section-title mb-3">Today — <?= date('F j, Y') ?></p>
    <div class="row g-3 mb-4">

        <div class="col-6 col-lg-3">
            <div class="kpi-card">
                <div class="kpi-icon pink"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="kpi-label">Revenue</div>
                    <div class="kpi-value">₱<?= number_format($todayRevenue, 0) ?></div>
                    <div class="kpi-sub"><?= $todayRow['count'] ?> sale(s)</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi-card">
                <div class="kpi-icon red"><i class="bi bi-wallet2"></i></div>
                <div>
                    <div class="kpi-label">Expenses</div>
                    <div class="kpi-value" style="color:#fca5a5;">₱<?= number_format($todayExp, 0) ?></div>
                    <div class="kpi-sub"><a href="expenses.php" style="color:#7a8499;font-size:.72rem;">manage →</a></div>
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
                    <div class="kpi-value">₱<?= number_format((float) $todayRow['labor'], 0) ?></div>
                    <div class="kpi-sub">Parts: ₱<?= number_format((float) $todayRow['parts'], 0) ?></div>
                </div>
            </div>
        </div>

    </div>

    <!-- Monthly Summary Bar -->
    <div class="card mb-4 p-3" style="border-color:rgba(232,23,93,0.2);">
        <div class="d-flex flex-wrap gap-4 align-items-center">
            <div>
                <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;">Monthly Revenue</div>
                <div style="font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;color:#e8175d;">
                    ₱<?= number_format($monthRevenue, 2) ?>
                </div>
            </div>
            <div style="color:#7a8499;font-size:1.2rem;">−</div>
            <div>
                <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;">Monthly Expenses</div>
                <div style="font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;color:#fca5a5;">
                    ₱<?= number_format($monthExp, 2) ?>
                </div>
            </div>
            <div style="color:#7a8499;font-size:1.2rem;">=</div>
            <div>
                <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;">Net Profit</div>
                <div style="font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:700;"
                    class="<?= $monthProfit >= 0 ? 'text-profit' : 'text-loss' ?>">
                    <?= $monthProfit >= 0 ? '' : '−' ?>₱<?= number_format(abs($monthProfit), 2) ?>
                </div>
            </div>
            <?php if ($monthRevenue > 0): ?>
                <div class="ms-auto" style="min-width:160px;">
                    <?php $margin = ($monthProfit / $monthRevenue) * 100; ?>
                    <div style="font-size:.68rem;color:#7a8499;margin-bottom:5px;">Profit Margin</div>
                    <div class="progress" style="height:5px;border-radius:3px;background:rgba(255,255,255,.1);">
                        <div class="progress-bar <?= $margin >= 0 ? 'bg-success' : 'bg-danger' ?>"
                            style="width:<?= min(abs($margin), 100) ?>%"></div>
                    </div>
                    <small class="<?= $margin >= 0 ? 'text-profit' : 'text-loss' ?>" style="font-size:.7rem;">
                        <?= $margin >= 0 ? '+' : '' ?><?= number_format($margin, 1) ?>% margin
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="chart-card">
                <h6><i class="bi bi-graph-up me-2" style="color:#e8175d;"></i>Revenue vs Expenses — Last 30 Days</h6>
                <canvas id="revenueChart" height="180"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card">
                <h6><i class="bi bi-bar-chart-horizontal me-2" style="color:#06b6d4;"></i>Top Products</h6>
                <?php if (empty($topProducts)): ?>
                    <div style="text-align:center;color:#7a8499;padding:30px 0;">No sales data yet.</div>
                <?php else: ?>
                    <canvas id="productsChart" height="220"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bottom Row: Recent Sales + Low Stock -->
    <div class="row g-3">

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-0">
                    <div class="p-3 border-bottom" style="border-color:rgba(255,255,255,.07) !important;">
                        <h6 style="margin:0;font-size:.88rem;">
                            <i class="bi bi-receipt me-2" style="color:#e8175d;"></i>Recent Transactions
                        </h6>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Plate</th>
                                    <th>Parts ₱</th>
                                    <th>Labor ₱</th>
                                    <th>Total ₱</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentSales)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center;padding:24px;color:#7a8499;">
                                            No sales yet. <a href="sales.php">Record one →</a>
                                        </td>
                                    </tr>
                                <?php else: foreach ($recentSales as $s): ?>
                                    <tr>
                                        <td><span class="badge-gray"><?= $s['id'] ?></span></td>
                                        <td><?= date('M d', strtotime($s['sale_date'])) ?></td>
                                        <td><?= htmlspecialchars($s['customer_name'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($s['plate_number']  ?: '—') ?></td>
                                        <td style="color:#93c5fd;font-weight:600;">₱<?= number_format($s['parts_total'], 0) ?></td>
                                        <td style="color:#34d399;font-weight:600;">₱<?= number_format($s['labor_total'], 0) ?></td>
                                        <td><strong>₱<?= number_format($s['grand_total'], 0) ?></strong></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 text-end">
                        <a href="sales-history.php" class="btn-ghost" style="font-size:.8rem;">View All Sales →</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Panel -->
        <div class="col-lg-4">
            <?php if (!empty($lowStockItems)): ?>
                <div class="card">
                    <div class="card-body p-0">
                        <div class="p-3 border-bottom" style="border-color:rgba(255,255,255,.07) !important;">
                            <h6 style="margin:0;font-size:.88rem;color:#fcd34d;">
                                <i class="bi bi-exclamation-triangle me-2"></i>Low Stock (<?= count($lowStockItems) ?>)
                            </h6>
                        </div>
                        <div class="p-3">
                            <?php foreach ($lowStockItems as $item):
                                $critical = $item['current_stock'] <= 0;
                            ?>
                                <div style="
                                    display:flex;align-items:center;justify-content:space-between;
                                    padding:9px 12px;border-radius:8px;margin-bottom:8px;
                                    background:<?= $critical ? 'rgba(239,68,68,.1)' : 'rgba(245,158,11,.08)' ?>;
                                    border-left:3px solid <?= $critical ? '#ef4444' : '#f59e0b' ?>;
                                ">
                                    <div>
                                        <div style="font-size:.82rem;font-weight:600;color:#e8ecf4;">
                                            <?= htmlspecialchars($item['description']) ?>
                                        </div>
                                        <div style="font-size:.7rem;color:#7a8499;">
                                            <?= htmlspecialchars($item['category_name']) ?>
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <span class="<?= $critical ? 'badge-red' : 'badge-yellow' ?>">
                                            <?= $item['current_stock'] ?> left
                                        </span>
                                        <div style="font-size:.65rem;color:#7a8499;margin-top:2px;">
                                            Min: <?= $item['reorder_threshold'] ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <a href="inventory.php" class="btn-pink"
                                style="width:100%;justify-content:center;margin-top:4px;font-size:.82rem;">
                                Restock Now
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card p-4 text-center">
                    <i class="bi bi-check-circle" style="font-size:2rem;color:#34d399;margin-bottom:10px;"></i>
                    <div style="font-weight:600;color:#e8ecf4;">All stock healthy</div>
                    <div style="font-size:.78rem;color:#7a8499;margin-top:4px;">No items below reorder threshold</div>
                </div>
            <?php endif; ?>
        </div>

    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CHART_DAYS   = <?= $jsChart ?>;
const TOP_PRODUCTS = <?= $jsProducts ?>;

Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#7a8499';

// Revenue vs Expenses Line Chart
(function () {
    const ctx = document.getElementById('revenueChart')?.getContext('2d');
    if (!ctx) return;

    const gRev = ctx.createLinearGradient(0, 0, 0, 250);
    gRev.addColorStop(0, 'rgba(232,23,93,.3)');
    gRev.addColorStop(1, 'rgba(232,23,93,0)');

    const gExp = ctx.createLinearGradient(0, 0, 0, 250);
    gExp.addColorStop(0, 'rgba(239,68,68,.2)');
    gExp.addColorStop(1, 'rgba(239,68,68,0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: CHART_DAYS.map(d => d.label),
            datasets: [
                {
                    label: 'Revenue ₱',
                    data: CHART_DAYS.map(d => d.revenue),
                    borderColor: '#e8175d',
                    backgroundColor: gRev,
                    fill: true, tension: 0.4,
                    borderWidth: 2.5,
                    pointRadius: 2, pointHoverRadius: 5,
                },
                {
                    label: 'Expenses ₱',
                    data: CHART_DAYS.map(d => d.expenses),
                    borderColor: '#ef4444',
                    backgroundColor: gExp,
                    fill: true, tension: 0.4,
                    borderWidth: 2,
                    borderDash: [5, 3],
                    pointRadius: 2, pointHoverRadius: 4,
                },
                {
                    label: 'Profit ₱',
                    data: CHART_DAYS.map(d => d.profit),
                    borderColor: '#34d399',
                    fill: false, tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 2, pointHoverRadius: 5,
                },
            ],
        },
        options: {
            responsive: true,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { labels: { usePointStyle: true, padding: 16 } },
                tooltip: {
                    backgroundColor: '#1c2030',
                    borderColor: 'rgba(232,23,93,.3)',
                    borderWidth: 1,
                    callbacks: {
                        label: ctx => ' ' + ctx.dataset.label + ': ₱' + ctx.parsed.y.toLocaleString()
                    }
                }
            },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { maxTicksLimit: 10, font: { size: 10 } } },
                y: {
                    grid: { color: 'rgba(255,255,255,.04)' },
                    ticks: { callback: v => '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v) }
                }
            }
        }
    });
})();

// Top Products Bar Chart — fixed colors to be visible
(function () {
    const ctx = document.getElementById('productsChart')?.getContext('2d');
    if (!ctx || !TOP_PRODUCTS.length) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: TOP_PRODUCTS.map(p => p.code || p.description.substring(0, 14)),
            datasets: [{
                label: 'Revenue ₱',
                data: TOP_PRODUCTS.map(p => parseFloat(p.revenue)),
                backgroundColor: ['#e8175d', '#8b5cf6', '#3b82f6', '#10b981', '#f59e0b'],
                borderRadius: 7,
                borderSkipped: false,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1c2030',
                    borderColor: 'rgba(232,23,93,.3)',
                    borderWidth: 1,
                    callbacks: {
                        label: ctx => ' ₱' + ctx.parsed.x.toLocaleString()
                    }
                }
            },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { callback: v => '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v) } },
                y: { grid: { display: false } }
            }
        }
    });
})();
</script>
</body>
</html>
