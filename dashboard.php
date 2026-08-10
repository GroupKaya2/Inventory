<?php
    session_start();
    require_once 'backend/db.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    $activePage = 'dashboard';
    $isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');

    // ── KPI: Today ──
    $todayRow = $conn->query("
            SELECT COALESCE(SUM(parts_total+labor_total),0) AS revenue,
                COALESCE(SUM(parts_total),0) AS parts,
                COALESCE(SUM(labor_total),0) AS labor,
                COUNT(*) AS cnt
            FROM sales WHERE sale_date='$today'
        ")->fetch_assoc();
    $todayExp = (float) $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE expense_date='$today'")->fetch_assoc()['t'];

    // ── KPI: Month ──
    $monthRow = $conn->query("
            SELECT COALESCE(SUM(parts_total+labor_total),0) AS revenue, COUNT(*) AS cnt
            FROM sales WHERE sale_date BETWEEN '$monthStart' AND '$monthEnd'
        ")->fetch_assoc();
    $monthExp = (float) $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE expense_date BETWEEN '$monthStart' AND '$monthEnd'")->fetch_assoc()['t'];

    $todayRevenue = (float) $todayRow['revenue'];
    $todayProfit = $todayRevenue - $todayExp;
    $monthRevenue = (float) $monthRow['revenue'];
    $monthProfit = $monthRevenue - $monthExp;
    $monthMargin = $monthRevenue > 0 ? ($monthProfit / $monthRevenue) * 100 : 0;

    // ── Chart 1: Last 30 days revenue/expenses/profit ──
    $last30 = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $rev = (float) $conn->query("SELECT COALESCE(SUM(parts_total+labor_total),0) AS t FROM sales WHERE sale_date='$d'")->fetch_assoc()['t'];
        $exp = (float) $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE expense_date='$d'")->fetch_assoc()['t'];
        $last30[] = ['label' => date('M d', strtotime($d)), 'revenue' => $rev, 'expenses' => $exp, 'profit' => $rev - $exp];
    }

    // ── Chart 2: Monthly trend (last 12 months) ──
    $monthly12 = [];
    for ($i = 11; $i >= 0; $i--) {
        $ms = date('Y-m-01', strtotime("-$i months"));
        $me = date('Y-m-t', strtotime("-$i months"));
        $lbl = date('M Y', strtotime($ms));
        $rev = (float) $conn->query("SELECT COALESCE(SUM(parts_total+labor_total),0) AS t FROM sales WHERE sale_date BETWEEN '$ms' AND '$me'")->fetch_assoc()['t'];
        $exp = (float) $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE expense_date BETWEEN '$ms' AND '$me'")->fetch_assoc()['t'];
        $monthly12[] = ['label' => $lbl, 'revenue' => $rev, 'expenses' => $exp, 'profit' => $rev - $exp];
    }

    // ── Chart 3: Top 10 products by qty sold (all time) ──
    $top10products = [];
    $r = $conn->query("
            SELECT
                COALESCE(
                    NULLIF(TRIM(p.description),''),
                    NULLIF(TRIM(si.description),''),
                    CONCAT('Product #', si.product_id)
                ) AS description,
                COALESCE(p.code,'') AS code,
                SUM(si.quantity) AS qty_sold,
                SUM(si.amount)   AS revenue
            FROM sale_items si
            INNER JOIN products p ON si.product_id = p.product_id
            WHERE si.line_type = 'parts'
            AND si.product_id IS NOT NULL
            AND TRIM(COALESCE(p.description, si.description, '')) != ''
            GROUP BY si.product_id, p.description
            ORDER BY qty_sold DESC
            LIMIT 10
        ");
    if ($r)
        while ($row = $r->fetch_assoc())
            $top10products[] = $row;

    // ── Chart 4: Revenue split parts vs labor (this month) ──
    $splitParts = (float) $conn->query("SELECT COALESCE(SUM(parts_total),0) AS t FROM sales WHERE sale_date BETWEEN '$monthStart' AND '$monthEnd'")->fetch_assoc()['t'];
    $splitLabor = (float) $conn->query("SELECT COALESCE(SUM(labor_total),0) AS t FROM sales WHERE sale_date BETWEEN '$monthStart' AND '$monthEnd'")->fetch_assoc()['t'];

    // ── Chart 5: Low stock items ──
    $stockItems = [];
    $r = $conn->query("
            SELECT p.description,
                COALESCE(ps.current_stock,
                    p.initial_quantity + COALESCE((
                        SELECT SUM(t.quantity_change) FROM inventory_transactions t WHERE t.product_id = p.product_id
                    ), 0)
                ) AS current_stock,
                p.reorder_threshold
            FROM products p
            LEFT JOIN product_stock ps ON p.product_id = ps.product_id
            ORDER BY current_stock ASC
            LIMIT 12
        ");
    if ($r)
        while ($row = $r->fetch_assoc())
            $stockItems[] = $row;

    // ── Chart 6: Cash vs Online Payment vs Credit per month (last 6 months) ──
    $paymentSplit = [];
    for ($i = 5; $i >= 0; $i--) {
        $ms = date('Y-m-01', strtotime("-$i months"));
        $me = date('Y-m-t', strtotime("-$i months"));
        $lbl = date('M', strtotime($ms));
        $cash = (float) $conn->query("SELECT COALESCE(SUM(parts_total+labor_total),0) AS t FROM sales WHERE sale_date BETWEEN '$ms' AND '$me' AND payment_method='cash'")->fetch_assoc()['t'];
        $gcash = (float) $conn->query("SELECT COALESCE(SUM(parts_total+labor_total),0) AS t FROM sales WHERE sale_date BETWEEN '$ms' AND '$me' AND payment_method='gcash'")->fetch_assoc()['t'];
        $credit = (float) $conn->query("SELECT COALESCE(SUM(parts_total+labor_total),0) AS t FROM sales WHERE sale_date BETWEEN '$ms' AND '$me' AND payment_method='credit'")->fetch_assoc()['t'];
        $paymentSplit[] = ['label' => $lbl, 'cash' => $cash, 'gcash' => $gcash, 'credit' => $credit];
    }

    // ── Recent sales ──
    $recentSales = [];
    $r = $conn->query("SELECT id, sale_date, customer_name, payment_method, parts_total, labor_total, (parts_total+labor_total) AS grand_total FROM sales ORDER BY id DESC LIMIT 8");
    if ($r)
        while ($row = $r->fetch_assoc())
            $recentSales[] = $row;

    // ── Low stock alert ──
    $lowStockAlert = [];
    $r = $conn->query("
            SELECT p.description,
                COALESCE(c.category_name, '') AS category_name,
                COALESCE(ps.current_stock,
                    p.initial_quantity + COALESCE((
                        SELECT SUM(t.quantity_change) FROM inventory_transactions t WHERE t.product_id = p.product_id
                    ), 0)
                ) AS current_stock,
                p.reorder_threshold
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN product_stock ps ON p.product_id = ps.product_id
            WHERE COALESCE(ps.current_stock,
                    p.initial_quantity + COALESCE((
                        SELECT SUM(t2.quantity_change) FROM inventory_transactions t2 WHERE t2.product_id = p.product_id
                    ), 0)
                ) <= p.reorder_threshold
            ORDER BY current_stock ASC
            LIMIT 8
        ");
    if ($r)
        while ($row = $r->fetch_assoc())
            $lowStockAlert[] = $row;

    // Total inventory items
    $totalProducts = (int) ($conn->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()['c'] ?? 0);
    $lowStockCount = count($lowStockAlert);

    // ── Monthly Performance Chart: all 12 months of current year ──
    $perfYear        = (int) date('Y');
    $currentMonthKey = date('Y-m');

    // Fetch daily revenue for the whole year in one query
    $salesByDay = [];
    $r = $conn->query("
        SELECT DATE_FORMAT(sale_date,'%Y-%m') AS ym,
            DAY(sale_date)                 AS day_num,
            SUM(parts_total+labor_total)   AS revenue
        FROM sales
        WHERE YEAR(sale_date) = $perfYear
        GROUP BY ym, day_num
    ");
    if ($r) while ($row = $r->fetch_assoc())
        $salesByDay[$row['ym']][(int)$row['day_num']] = (float)$row['revenue'];

    // Fetch daily expenses for the whole year in one query
    $expByDay = [];
    $r = $conn->query("
        SELECT DATE_FORMAT(expense_date,'%Y-%m') AS ym,
            DAY(expense_date)                 AS day_num,
            SUM(amount)                       AS expenses
        FROM expenses
        WHERE YEAR(expense_date) = $perfYear
        GROUP BY ym, day_num
    ");
    if ($r) while ($row = $r->fetch_assoc())
        $expByDay[$row['ym']][(int)$row['day_num']] = (float)$row['expenses'];

    // Build all 12 months Jan–Dec
    $monthlyPerfData = [];
    for ($m = 1; $m <= 12; $m++) {
        $ym          = sprintf('%04d-%02d', $perfYear, $m);
        $daysInMonth = (int) date('t', strtotime($ym.'-01'));
        $monthLabel  = date('F', mktime(0,0,0,$m,1,$perfYear)); // "January" … "December"
        $dayRevenue  = [];
        $dayExpenses = [];
        $dayLabels   = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dayLabels[]   = $d;                                
            $dayRevenue[]  = $salesByDay[$ym][$d] ?? 0;
            $dayExpenses[] = $expByDay[$ym][$d]   ?? 0;
        }
        $totalRev = array_sum($dayRevenue);
        $totalExp = array_sum($dayExpenses);
        $activeDays = count(array_filter($dayRevenue));
        $avg        = $activeDays > 0 ? $totalRev / $activeDays : 0;
        $monthlyPerfData[$ym] = [
            'ym'        => $ym,
            'label'     => $monthLabel,          // "January", "February" …
            'labels'    => $dayLabels,
            'revenue'   => $dayRevenue,
            'expenses'  => $dayExpenses,
            'total_rev' => $totalRev,
            'total_exp' => $totalExp,
            'net'       => $totalRev - $totalExp,
            'avg_daily' => round($avg, 2),
        ];
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard DSpeedway</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link
            href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap"
            rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <link rel="stylesheet" href="assets/css/app.css">
        <style>
            /*Chart containers */
            .chart-wrap {
                background: #111827;
                border: 1px solid rgba(255, 255, 255, .06);
                border-radius: 10px;
                padding: 18px 18px 12px;
            }

            .chart-hdr {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 14px;
                flex-wrap: wrap;
                gap: 8px;
            }

            .chart-title {
                font-family: 'Space Grotesk', sans-serif;
                font-size: .88rem;
                font-weight: 600;
                color: #e2e8f0;
                margin: 0;
            }

            .chart-sub {
                font-size: .72rem;
                color: #4b5a6e;
                margin-top: 2px;
            }

            .chart-badge {
                font-size: .68rem;
                font-weight: 600;
                padding: 3px 9px;
                border-radius: 5px;
                white-space: nowrap;
            }

            .cb-green {
                background: rgba(74, 222, 128, .1);
                color: #4ade80;
            }

            .cb-blue {
                background: rgba(96, 165, 250, .1);
                color: #60a5fa;
            }

            .cb-amber {
                background: rgba(251, 191, 36, .1);
                color: #fbbf24;
            }

            .cb-purple {
                background: rgba(167, 139, 250, .1);
                color: #a78bfa;
            }

            /* ── Chart legend ── */
            .clegend {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                margin-bottom: 10px;
            }

            .clegend span {
                display: flex;
                align-items: center;
                gap: 5px;
                font-size: .72rem;
                color: #64748b;
            }

            .cleg-sq {
                width: 10px;
                height: 10px;
                border-radius: 2px;
                flex-shrink: 0;
            }

            .cleg-line {
                width: 14px;
                height: 2px;
                flex-shrink: 0;
            }

            .cleg-dash {
                width: 14px;
                height: 0;
                border-top: 2px dashed;
                flex-shrink: 0;
            }

            /* ── Tab buttons ── */
            .chart-tabs {
                display: flex;
                gap: 4px;
            }

            .ctab {
                background: none;
                border: 1px solid rgba(255, 255, 255, .08);
                border-radius: 6px;
                color: #4b5a6e;
                font-size: .72rem;
                font-weight: 600;
                padding: 4px 10px;
                cursor: pointer;
                transition: all .15s;
                font-family: 'Inter', sans-serif;
            }

            .ctab.active {
                background: rgba(74, 222, 128, .1);
                border-color: rgba(74, 222, 128, .25);
                color: #4ade80;
            }

            .ctab:hover:not(.active) {
                color: #e2e8f0;
                border-color: rgba(255, 255, 255, .18);
            }

            /* ── Monthly summary bar ── */
            .month-bar {
                background: #111827;
                border: 1px solid rgba(74, 222, 128, .12);
                border-radius: 10px;
                padding: 14px 20px;
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                align-items: center;
            }

            .mb-item .lbl {
                font-size: .65rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: .6px;
                color: #4b5a6e;
            }

            .mb-item .val {
                font-family: 'Space Grotesk', sans-serif;
                font-size: 1.1rem;
                font-weight: 700;
            }

            .mb-sep {
                color: #2e3a4e;
                font-size: 1rem;
                align-self: flex-end;
                padding-bottom: 3px;
            }

            /* ── Tooltip override ── */
            .tooltip-dark {
                background: #1c2336 !important;
                border: 1px solid rgba(74, 222, 128, .18) !important;
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
                            <div class="kpi-sub"><a href="expenses.php" style="color:#4b5a6e;font-size:.72rem;">manage →</a>
                            </div>
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
                            <?= $monthMargin >= 0 ? '+' : '' ?>    <?= number_format($monthMargin, 1) ?>% margin
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
                                <div class="chart-title"><i class="bi bi-graph-up me-2" style="color:#4ade80;"></i>Revenue
                                    Trend</div>
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
                            <canvas id="trendChart" role="img" aria-label="Revenue trend line chart">Revenue, expenses and
                                profit over time.</canvas>
                        </div>
                    </div>
                </div>

                <!-- Chart 4: Parts vs Labor Donut -->
                <div class="col-lg-4">
                    <div class="chart-wrap" style="height:100%;">
                        <div class="chart-hdr">
                            <div>
                                <div class="chart-title"><i class="bi bi-pie-chart-fill me-2"
                                        style="color:#60a5fa;"></i>Revenue Split</div>
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
                            <canvas id="donutChart" role="img" aria-label="Donut chart of parts vs labor revenue">Parts:
                                <?= $pct_parts ?>%, Labor: <?= $pct_labor ?>%</canvas>
                        </div>
                        <?php if ($splitTotal == 0): ?>
                            <div style="text-align:center;color:#4b5a6e;font-size:.8rem;padding:10px 0;">No sales this month
                                yet.</div>
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
                                <div class="chart-title"><i class="bi bi-bar-chart-horizontal me-2"
                                        style="color:#a78bfa;"></i>Top 10 Products Sold</div>
                                <div class="chart-sub">By units sold — all time</div>
                            </div>
                            <div class="chart-tabs">
                                <button class="ctab active" onclick="switchProducts('qty',this)">By Qty</button>
                                <button class="ctab" onclick="switchProducts('revenue',this)">By Revenue</button>
                            </div>
                        </div>
                        <div class="clegend">
                            <span><span class="cleg-sq" style="background:#7F77DD;"></span>Qty Sold / Revenue</span>
                        </div>
                        <?php if (empty($top10products)): ?>
                            <div style="text-align:center;color:#4b5a6e;padding:40px 0;font-size:.85rem;">
                                <i class="bi bi-box-seam" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                                No sales data yet.
                            </div>
                        <?php else: ?>
                            <div style="position:relative;height:<?= max(200, count($top10products) * 32 + 40) ?>px;">
                                <canvas id="productsChart" role="img"
                                    aria-label="Horizontal bar chart of top products by units sold">Top products ranked by sales
                                    volume.</canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chart 6: Cash vs Online Payment vs Credit -->
                <div class="col-lg-4">
                    <div class="chart-wrap" style="height:100%;">
                        <div class="chart-hdr">
                            <div>
                                <div class="chart-title"><i class="bi bi-credit-card-2-front me-2"
                                        style="color:#fbbf24;"></i>Payment Methods</div>
                                <div class="chart-sub">Cash vs Online Payment vs Credit — last 6 months</div>
                            </div>
                        </div>
                        <div class="clegend">
                            <span><span class="cleg-sq" style="background:#378ADD;"></span>Cash</span>
                            <span><span class="cleg-sq" style="background:#7F77DD;"></span>Online Payment</span>
                            <span><span class="cleg-sq" style="background:#a78bfa;"></span>Credit</span>
                        </div>
                        <div style="position:relative;height:220px;">
                            <canvas id="paymentChart" role="img"
                                aria-label="Stacked bar chart of cash, online payment, and credit payments per month">Payment method breakdown
                                by month.</canvas>
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
                                <div class="chart-title"><i class="bi bi-boxes me-2" style="color:#22d3ee;"></i>Stock Level
                                    Monitor</div>
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
                            <div style="text-align:center;color:#4b5a6e;padding:30px 0;font-size:.85rem;">No products found in
                                inventory.</div>
                        <?php else: ?>
                            <div style="position:relative;height:<?= max(180, count($stockItems) * 30 + 40) ?>px;">
                                <canvas id="stockChart" role="img" aria-label="Horizontal bar chart of stock levels">Stock
                                    levels for all products.</canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ═══ MONTHLY PERFORMANCE CHART ═══ -->
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="chart-wrap" id="monthlyPerfWrap">
                        <div class="chart-hdr">
                            <div>
                                <div class="chart-title">
                                    <i class="bi bi-bar-chart-steps me-2" style="color:#fbbf24;"></i>
                                    Monthly Sales Performance — <?= $perfYear ?>
                                </div>
                                <div class="chart-sub" id="monthlyPerfSub">Select a month to see daily breakdown</div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <!-- Month selector -->
                                <select id="monthPicker" class="form-select form-select-sm"
                                    style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#e2e8f0;border-radius:7px;font-size:.78rem;width:auto;padding:4px 10px;"
                                    onchange="switchPerfMonth(this.value)">
                                    <?php foreach ($monthlyPerfData as $ym => $md): ?>
                                        <option value="<?= $ym ?>"
                                            <?= $ym === $currentMonthKey ? 'selected' : '' ?>
                                            style="background:#1c2336;color:#e2e8f0;">
                                            <?= $md['label'] ?> <?= $perfYear ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- Download button -->
                                <button onclick="downloadPerfChart()"
                                    class="btn-ghost" style="font-size:.75rem;padding:5px 12px;gap:5px;">
                                    <i class="bi bi-download"></i> Save Image
                                </button>
                            </div>
                        </div>

                        <!-- Status pills -->
                        <div class="d-flex flex-wrap gap-2 mb-3" id="monthlyPerfStats">
                            <div class="summary-pill">
                                <i class="bi bi-cash" style="color:#4ade80;"></i>
                                Revenue: <strong id="statRev">₱0</strong>
                            </div>
                            <div class="summary-pill">
                                <i class="bi bi-wallet2" style="color:#f87171;"></i>
                                Expenses: <strong id="statExp" style="color:#f87171;">₱0</strong>
                            </div>
                            <div class="summary-pill">
                                <i class="bi bi-graph-up" style="color:#60a5fa;"></i>
                                Net: <strong id="statNet">₱0</strong>
                            </div>
                            <div class="summary-pill">
                                <i class="bi bi-calendar3" style="color:#fbbf24;"></i>
                                Avg/day: <strong id="statAvg" style="color:#fbbf24;">₱0</strong>
                            </div>
                            <div class="summary-pill" id="perfStatusPill">
                                <i class="bi bi-circle-fill" id="perfStatusIcon" style="font-size:.5rem;"></i>
                                <span id="perfStatusText">—</span>
                            </div>
                        </div>

                        <div class="clegend">
                            <span><span class="cleg-sq" style="background:#4ade80;"></span>Daily Revenue</span>
                            <span><span class="cleg-sq" style="background:rgba(248,113,113,.5);"></span>Expenses</span>
                            <span><span class="cleg-line" style="background:#fbbf24;"></span>Avg Revenue/day</span>
                        </div>

                        <div style="position:relative;height:240px;">
                            <canvas id="monthlyPerfChart" role="img" aria-label="Monthly daily performance chart"></canvas>
                        </div>

                        <div id="noMonthData" style="display:none;text-align:center;color:#4b5a6e;padding:40px 0;font-size:.85rem;">
                            <i class="bi bi-calendar-x" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                            No sales data for this month yet.
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ BOTTOM ROW: Recent Sales + Low Stock Alert ═══ -->
            <div class="row g-3">

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="p-3 d-flex align-items-center justify-content-between"
                                style="border-bottom:1px solid rgba(255,255,255,.06);">
                                <h6 style="margin:0;font-size:.88rem;color:#e2e8f0;">
                                    <i class="bi bi-receipt me-2" style="color:#4ade80;"></i>Recent Transactions
                                </h6>
                                <a href="sales-history.php" class="btn-ghost"
                                    style="font-size:.75rem;padding:5px 12px;">View All →</a>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Customer</th>
                                            <th>Payment</th>
                                            <th>Parts ₱</th>
                                            <th>Labor ₱</th>
                                            <th>Total ₱</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentSales)): ?>
                                            <tr>
                                                <td colspan="7" style="text-align:center;padding:24px;color:#4b5a6e;">
                                                    No sales yet. <a href="sales.php">Record one →</a>
                                                </td>
                                            </tr>
                                        <?php else:
                                            $recentNum = 0;
                                            foreach ($recentSales as $s):
                                                $recentNum++;
                                                $pm = $s['payment_method'] ?? 'cash'; ?>
                                                <tr>
                                                    <td><span class="badge-gray"><?= $recentNum ?></span></td>
                                                    <td><?= date('M d', strtotime($s['sale_date'])) ?></td>
                                                    <td><?= htmlspecialchars($s['customer_name'] ?: '—') ?></td>
                                                    <td>
                                                        <?php if ($pm === 'gcash'): ?>
                                                            <span class="badge-blue"><i class="bi bi-phone-fill me-1"></i>Online Payment</span>
                                                        <?php elseif ($pm === 'credit'): ?>
                                                            <span class="badge-gray" style="background:rgba(167,139,250,.12);color:#a78bfa;"><i class="bi bi-credit-card me-1"></i>Credit</span>
                                                        <?php else: ?>
                                                            <span class="badge-green"><i class="bi bi-cash-coin me-1"></i>Cash</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="color:#60a5fa;font-weight:600;">
                                                        ₱<?= number_format($s['parts_total'], 0) ?></td>
                                                    <td style="color:#4ade80;font-weight:600;">
                                                        ₱<?= number_format($s['labor_total'], 0) ?></td>
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
                                        <i class="bi bi-exclamation-triangle me-2"></i>Low Stock Alert
                                        (<?= count($lowStockAlert) ?>)
                                    </h6>
                                </div>
                                <div class="p-3">
                                    <?php foreach ($lowStockAlert as $item):
                                        $critical = (int) $item['current_stock'] <= 0; ?>
                                        <div style="display:flex;align-items:center;justify-content:space-between;
                                    padding:8px 10px;border-radius:8px;margin-bottom:7px;
                                    background:<?= $critical ? 'rgba(248,113,113,.07)' : 'rgba(251,191,36,.06)' ?>;
                                    border-left:3px solid <?= $critical ? '#f87171' : '#fbbf24' ?>;">
                                            <div>
                                                <div style="font-size:.8rem;font-weight:600;color:#e2e8f0;">
                                                    <?= htmlspecialchars($item['description']) ?></div>
                                                <div style="font-size:.68rem;color:#4b5a6e;">
                                                    <?= htmlspecialchars($item['category_name'] ?? '') ?></div>
                                            </div>
                                            <div style="text-align:right;">
                                                <span
                                                    class="<?= $critical ? 'badge-red' : 'badge-yellow' ?>"><?= $item['current_stock'] ?>
                                                    left</span>
                                                <div style="font-size:.63rem;color:#4b5a6e;margin-top:2px;">Min:
                                                    <?= $item['reorder_threshold'] ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <a href="inventory.php" class="btn-pink"
                                        style="width:100%;justify-content:center;margin-top:4px;font-size:.8rem;">
                                        <i class="bi bi-box-seam me-1"></i>Restock Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card p-4 text-center">
                            <i class="bi bi-check-circle"
                                style="font-size:2rem;color:#4ade80;margin-bottom:10px;display:block;"></i>
                            <div style="font-weight:600;color:#e2e8f0;font-family:'Space Grotesk',sans-serif;">All stock healthy
                            </div>
                            <div style="font-size:.78rem;color:#4b5a6e;margin-top:4px;"><?= $totalProducts ?> products · none
                                below threshold</div>
                            <a href="inventory.php" class="btn-ghost"
                                style="margin-top:12px;font-size:.78rem;justify-content:center;">View Inventory →</a>
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
            const DAILY = <?= json_encode($last30, JSON_UNESCAPED_UNICODE) ?>;
            const MONTHLY = <?= json_encode($monthly12, JSON_UNESCAPED_UNICODE) ?>;
            const PRODUCTS = <?= json_encode($top10products, JSON_UNESCAPED_UNICODE) ?>;
            const PAYMENTS = <?= json_encode($paymentSplit, JSON_UNESCAPED_UNICODE) ?>;
            const STOCKS = <?= json_encode($stockItems, JSON_UNESCAPED_UNICODE) ?>;
            const SPLIT = { parts: <?= $splitParts ?>, labor: <?= $splitLabor ?> };

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

            // CHART 1 & 2: Trend
            let trendChart = null;
            let trendMode = 'daily';

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
                            y: { grid: GRID, ticks: { ...TICK, callback: v => v >= 1000 ? '₱' + (v / 1000).toFixed(0) + 'k' : '₱' + v } }
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


            // CHART 3: Top Products (toggleable)
            let productsChart = null;
            let productsMode = 'qty';

            function buildProducts(mode) {
                const ctx = document.getElementById('productsChart')?.getContext('2d');
                if (!ctx || !PRODUCTS.length) return;
                if (productsChart) { productsChart.destroy(); }

                const sorted = [...PRODUCTS].sort((a, b) =>
                    mode === 'qty' ? b.qty_sold - a.qty_sold : b.revenue - a.revenue
                );
                const labels = sorted.map((p, i) => (p.description && p.description.trim()) ? p.description.trim() : ('Product #' + (i + 1)));
                const values = sorted.map(p => mode === 'qty' ? parseFloat(p.qty_sold) : parseFloat(p.revenue));
                const barColors = sorted.map((_, i) => {
                    const palette = ['#7F77DD', '#6366f1', '#8b5cf6', '#a78bfa', '#818cf8', '#7c3aed', '#6d28d9', '#5b21b6', '#4c1d95', '#7e22ce'];
                    return palette[i % palette.length];
                });

                productsChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: mode === 'qty' ? 'Units Sold' : 'Revenue',
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
                                    title: c => labels[c[0].dataIndex] || '',
                                    label: c => mode === 'qty'
                                        ? '  Qty Sold: ' + Math.round(c.parsed.x) + ' pcs'
                                        : '  Revenue: ' + peso(c.parsed.x)
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: GRID,
                                ticks: { ...TICK, callback: v => mode === 'qty' ? v : (v >= 1000 ? '₱' + (v / 1000).toFixed(0) + 'k' : '₱' + v) }
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
                                afterFit(scale) { scale.width = 200; }
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

            // CHART 4: Donut — Parts vs Labor
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

            // CHART 5: Stock Levels
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
                                barThickness: 14,
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
                                        
                                        const stock = c.parsed.x;
                                        const status = stock <= 0 ? ' ⛔ Out of stock' : stock <= thresh[c.dataIndex] ? ' ⚠ Low stock' : ' ✓ OK';
                                        return [' Stock: ' + stock + status, ' Reorder at: ' + thresh[c.dataIndex]];
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
    // CHART 6: Cash vs Online Payment vs Credit
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
                                label: 'Online Payment',
                                data: PAYMENTS.map(p => p.gcash),
                                backgroundColor: '#7F77DD',
                                stack: 'pay', borderRadius: 4, borderSkipped: false,
                            },
                            {
                                label: 'Credit',
                                data: PAYMENTS.map(p => p.credit),
                                backgroundColor: '#a78bfa',
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
                            y: { grid: GRID, ticks: { ...TICK, callback: v => v >= 1000 ? '₱' + (v / 1000).toFixed(0) + 'k' : '₱' + v }, stacked: true }
                        }
                    }
                });
            })();
            // MONTHLY PERFORMANCE CHART
            const MONTHLY_PERF = <?= json_encode(array_values($monthlyPerfData), JSON_UNESCAPED_UNICODE) ?>;
            const MONTHLY_PERF_MAP = <?= json_encode($monthlyPerfData, JSON_UNESCAPED_UNICODE) ?>;
            const CURRENT_MONTH = '<?= $currentMonthKey ?>';

            let perfChart = null;

            function peso2(v) {
                return '₱' + parseFloat(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            }

            function buildPerfChart(ym) {
                const canvas = document.getElementById('monthlyPerfChart');
                const noData = document.getElementById('noMonthData');
                if (!canvas) return;

                const mdata = MONTHLY_PERF_MAP[ym];

                // No data at all
                if (!mdata || (mdata.total_rev === 0 && mdata.total_exp === 0)) {
                    canvas.style.display = 'none';
                    noData.style.display = 'block';
                    document.getElementById('statRev').textContent = '₱0';
                    document.getElementById('statExp').textContent = '₱0';
                    document.getElementById('statNet').textContent = '₱0';
                    document.getElementById('statAvg').textContent = '₱0';
                    setStatus(0, 0);
                    return;
                }

                canvas.style.display = 'block';
                noData.style.display = 'none';

                // Update stat pills
                const net = mdata.total_rev - mdata.total_exp;
                document.getElementById('statRev').textContent = peso2(mdata.total_rev);
                document.getElementById('statExp').textContent = peso2(mdata.total_exp);
                const netEl = document.getElementById('statNet');
                netEl.textContent = (net < 0 ? '−' : '') + peso2(Math.abs(net));
                netEl.style.color = net >= 0 ? '#4ade80' : '#f87171';
                document.getElementById('statAvg').textContent = peso2(mdata.avg_daily);
                setStatus(mdata.total_rev, mdata.avg_daily);

                // Sub label
                document.getElementById('monthlyPerfSub').textContent =
                    'Daily revenue vs expenses — ' + mdata.label + ' <?= $perfYear ?>';

                const ctx = canvas.getContext('2d');
                if (perfChart) { perfChart.destroy(); }

                const avgLine = mdata.labels.map(() => mdata.avg_daily);

                perfChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: mdata.labels,
                        datasets: [
                            {
                                label: 'Revenue',
                                data: mdata.revenue,
                                backgroundColor: mdata.revenue.map(v =>
                                    v > mdata.avg_daily * 1.2
                                        ? 'rgba(74,222,128,.85)'   // High day — bright green
                                        : v > 0
                                            ? 'rgba(74,222,128,.45)'  // Normal day
                                            : 'rgba(74,222,128,.12)'  // No sales
                                ),
                                borderRadius: 3,
                                borderSkipped: false,
                                order: 2,
                            },
                            {
                                label: 'Expenses',
                                data: mdata.expenses,
                                backgroundColor: 'rgba(248,113,113,.45)',
                                borderRadius: 3,
                                borderSkipped: false,
                                order: 3,
                            },
                            {
                                label: 'Avg/day',
                                data: avgLine,
                                type: 'line',
                                borderColor: '#fbbf24',
                                borderWidth: 1.5,
                                borderDash: [5, 3],
                                pointRadius: 0,
                                fill: false,
                                order: 1,
                            }
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                ...TOOLTIP,
                                callbacks: {
                                    title: c => mdata.label + ' — ' + c[0].label,
                                    label: c => {
                                        if (c.dataset.label === 'Avg/day') return ' Avg/day: ' + peso2(c.parsed.y);
                                        return ' ' + c.dataset.label + ': ' + peso2(c.parsed.y);
                                    },
                                    afterBody: (items) => {
                                        const rev = items.find(i => i.dataset.label === 'Revenue')?.parsed.y || 0;
                                        const avg = mdata.avg_daily;
                                        if (rev === 0) return ['', ' ○ No sales this day'];
                                        if (rev > avg * 1.2) return ['', ' ▲ HIGH — above average'];
                                        if (rev < avg * 0.5 && rev > 0) return ['', ' ▼ LOW — below average'];
                                        return ['', ' ● Normal day'];
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: GRID,
                                ticks: {
                                    ...TICK,
                                    maxTicksLimit: 15,
                                    maxRotation: 0,
                                    callback: (val, idx) => 'D' + (idx + 1),
                                }
                            },
                            y: {
                                grid: GRID,
                                ticks: {
                                    ...TICK,
                                    callback: v => v >= 1000 ? '₱' + (v / 1000).toFixed(0) + 'k' : '₱' + v
                                },
                                beginAtZero: true,
                            }
                        }
                    }
                });
            }

            function setStatus(totalRev, avgDaily) {
                const pill   = document.getElementById('perfStatusPill');
                const icon   = document.getElementById('perfStatusIcon');
                const text   = document.getElementById('perfStatusText');

                if (totalRev === 0) {
                    icon.style.color = '#4b5a6e'; text.textContent = 'No data';
                    pill.style.background = 'rgba(100,116,139,.1)';
                    return;
                }
                // Simple heuristic: compare to previous month if available
                const keys = Object.keys(MONTHLY_PERF_MAP).sort();
                const currentIdx = keys.indexOf(document.getElementById('monthPicker').value);
                const prevYm = currentIdx > 0 ? keys[currentIdx - 1] : null;
                const prevRev = prevYm ? (MONTHLY_PERF_MAP[prevYm]?.total_rev || 0) : 0;

                let statusColor, statusTxt, bgColor;
                if (prevRev === 0) {
                    statusColor = '#4ade80'; statusTxt = '● Active month'; bgColor = 'rgba(74,222,128,.08)';
                } else {
                    const pct = ((totalRev - prevRev) / prevRev) * 100;
                    if (pct >= 10) {
                        statusColor = '#4ade80'; bgColor = 'rgba(74,222,128,.08)';
                        statusTxt = '▲ High — +'  + Math.round(pct) + '% vs last month';
                    } else if (pct <= -10) {
                        statusColor = '#f87171'; bgColor = 'rgba(248,113,113,.08)';
                        statusTxt = '▼ Low — ' + Math.round(pct) + '% vs last month';
                    } else {
                        statusColor = '#fbbf24'; bgColor = 'rgba(251,191,36,.08)';
                        statusTxt = '● Stable — ' + (pct >= 0 ? '+' : '') + Math.round(pct) + '% vs last month';
                    }
                }
                icon.style.color = statusColor;
                text.textContent = statusTxt;
                text.style.color = statusColor;
                pill.style.background = bgColor;
                pill.style.borderColor = statusColor.replace(')', ',.3)').replace('rgb', 'rgba');
            }

            function switchPerfMonth(ym) {
                buildPerfChart(ym);
            }

            function downloadPerfChart() {
                const canvas = document.getElementById('monthlyPerfChart');
                if (!canvas || canvas.style.display === 'none') {
                    Swal.fire({ icon: 'info', title: 'No Chart', text: 'No data to download for this month.' });
                    return;
                }
                // Create a new canvas with white/dark bg for clean download
                const dl = document.createElement('canvas');
                dl.width  = canvas.width;
                dl.height = canvas.height;
                const dctx = dl.getContext('2d');
                dctx.fillStyle = '#111827';
                dctx.fillRect(0, 0, dl.width, dl.height);
                dctx.drawImage(canvas, 0, 0);

                const ym  = document.getElementById('monthPicker').value;
                const lbl = (MONTHLY_PERF_MAP[ym]?.label || ym).replace(/\s+/g,'_');
                const a   = document.createElement('a');
                a.href     = dl.toDataURL('image/png');
                a.download = 'sales_' + lbl + '_<?= $perfYear ?>.png';
                a.click();
            }

            // Init: show current month
            (function () {
                const picker = document.getElementById('monthPicker');
                const initYm = picker?.value || CURRENT_MONTH;
                buildPerfChart(initYm);
            })();

        </script>

        <?php include 'footer.php'; ?>

    </body>

    </html>