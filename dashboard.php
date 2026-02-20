<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include "db.php";

$role = $_SESSION['role'];
$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Real-time overview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --dash-bg: #0f172a;
            --dash-card: #1e293b;
            --dash-border: rgba(255,255,255,.08);
            --dash-text: #e2e8f0;
            --dash-muted: #94a3b8;
            --dash-orange: #f97316;
            --dash-blue: #3b82f6;
            --dash-green: #22c55e;
        }
        body { background: var(--dash-bg); color: var(--dash-text); }
        .app-main { background: var(--dash-bg); }
        .dashboard-header {
            background: linear-gradient(180deg, #111827 0%, #0b1220 100%);
            border: 1px solid var(--dash-border);
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        .dashboard-header h1 { font-size: 1.35rem; font-weight: 700; margin: 0; color: #fff; }
        .dashboard-header .sub { font-size: 0.875rem; color: var(--dash-muted); }
        .kpi-card {
            background: var(--dash-card);
            border: 1px solid var(--dash-border);
            border-radius: 14px;
            padding: 18px;
            height: 100%;
        }
        .kpi-card .kpi-label { font-size: 0.8rem; color: var(--dash-muted); text-transform: uppercase; letter-spacing: .5px; }
        .kpi-card .kpi-value { font-size: 1.5rem; font-weight: 700; color: #fff; }
        .kpi-card .kpi-meta { font-size: 0.8rem; color: var(--dash-muted); margin-top: 6px; }
        .kpi-card .kpi-meta .trend-up { color: var(--dash-green); }
        .kpi-card .kpi-meta .trend-down { color: #ef4444; }
        .kpi-card .kpi-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem;
        }
        .kpi-icon.orange { background: rgba(249,115,22,.2); color: var(--dash-orange); }
        .kpi-icon.blue { background: rgba(59,130,246,.2); color: var(--dash-blue); }
        .kpi-icon.green { background: rgba(34,197,94,.2); color: var(--dash-green); }
        .kpi-icon.purple { background: rgba(139,92,246,.2); color: #8b5cf6; }
        .chart-card, .alerts-card, .list-card {
            background: var(--dash-card);
            border: 1px solid var(--dash-border);
            border-radius: 14px;
            padding: 20px;
            height: 100%;
        }
        .chart-card h6, .alerts-card h6, .list-card h6 {
            font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px;
            color: var(--dash-muted); margin-bottom: 14px;
        }
        .alert-item {
            display: flex; gap: 12px; padding: 12px 0;
            border-bottom: 1px solid var(--dash-border);
        }
        .alert-item:last-child { border-bottom: 0; }
        .alert-item .icon-wrap {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .alert-item .icon-wrap.warning { background: rgba(234,179,8,.25); color: #eab308; }
        .alert-item .icon-wrap.oil { background: rgba(120,53,15,.35); color: #d97706; }
        .alert-item .icon-wrap.info { background: rgba(59,130,246,.25); color: var(--dash-blue); }
        .alert-item .icon-wrap.success { background: rgba(34,197,94,.25); color: var(--dash-green); }
        .alert-item .title { font-weight: 600; font-size: 0.9rem; }
        .alert-item .detail { font-size: 0.8rem; color: var(--dash-muted); }
        .alert-item .time { font-size: 0.75rem; color: var(--dash-muted); }
        .top-part-row, .top-labor-row {
            display: flex; align-items: center; gap: 12px; margin-bottom: 12px;
        }
        .top-part-row .rank { width: 28px; font-weight: 700; color: var(--dash-muted); }
        .top-part-row .label { flex: 1; font-size: 0.9rem; }
        .top-part-row .amount { font-weight: 600; color: var(--dash-orange); }
        .top-labor-row .amount { font-weight: 600; color: var(--dash-blue); }
        .progress-wrap { flex: 1; max-width: 140px; }
        .progress-wrap .progress {
            height: 8px; border-radius: 4px; background: rgba(255,255,255,.1);
        }
        .progress-wrap .progress .bg-orange { background: var(--dash-orange); }
        .progress-wrap .progress .bg-blue { background: var(--dash-blue); }
        .donut-center {
            position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
            text-align: center; font-weight: 700; font-size: 1.1rem;
        }
        .donut-wrap { position: relative; max-width: 200px; margin: 0 auto; }
        .btn-dash { background: var(--dash-orange); color: #111; border: none; border-radius: 10px; font-weight: 600; }
        .btn-dash:hover { background: #fb923c; color: #111; }
    </style>
</head>
<body>
<?php include "sidebar.php"; ?>

<main class="app-main p-3 p-md-4">
    <div class="container-fluid">
        <div class="dashboard-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h1><i class="bi bi-speedometer2 me-2"></i>Real-time overview</h1>
                <p class="sub mb-0"><?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-dash" href="inventory.php"><i class="bi bi-box-seam me-1"></i> Inventory</a>
                <a class="btn btn-outline-light btn-sm" href="profile.php">Profile</a>
            </div>
        </div>

        <!-- KPIs -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="kpi-card d-flex align-items-start gap-3">
                    <div class="kpi-icon orange"><i class="bi bi-currency-exchange"></i></div>
                    <div>
                        <div class="kpi-label">Today's Sales</div>
                        <div class="kpi-value" id="kpi-today-sales">—</div>
                        <div class="kpi-meta" id="kpi-today-trend">—</div>
                        <div class="kpi-meta mt-1" id="kpi-today-details" style="font-size: 0.75rem;">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card d-flex align-items-start gap-3">
                    <div class="kpi-icon blue"><i class="bi bi-box-seam"></i></div>
                    <div>
                        <div class="kpi-label">Parts In Stock</div>
                        <div class="kpi-value" id="kpi-parts-stock">—</div>
                        <div class="kpi-meta" id="kpi-low-stock">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card d-flex align-items-start gap-3">
                    <div class="kpi-icon green"><i class="bi bi-wrench"></i></div>
                    <div>
                        <div class="kpi-label">Open Work Orders</div>
                        <div class="kpi-value" id="kpi-work-orders">—</div>
                        <div class="kpi-meta" id="kpi-completed-today">—</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card d-flex align-items-start gap-3">
                    <div class="kpi-icon purple"><i class="bi bi-graph-up"></i></div>
                    <div>
                        <div class="kpi-label" id="kpi-rev-label">Monthly Revenue</div>
                        <div class="kpi-value" id="kpi-revenue">—</div>
                        <div class="kpi-meta" id="kpi-rev-trend">—</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <!-- Monthly Revenue Trend -->
            <div class="col-12 col-lg-8">
                <div class="chart-card">
                    <h6>Monthly Revenue Trend</h6>
                    <p class="small text-muted mb-2">Parts vs Labor breakdown</p>
                    <div style="height: 280px;">
                        <canvas id="chartMonthlyTrend"></canvas>
                    </div>
                </div>
            </div>
            <!-- Alerts -->
            <div class="col-12 col-lg-4">
                <div class="alerts-card">
                    <h6>Alerts & Notifications</h6>
                    <div id="alertsContainer"></div>
                </div>
            </div>
        </div>

        <!-- Top Selling Parts, Top Labor, Sales Split -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-4">
                <div class="chart-card">
                    <h6>Monthly Sales and Labor</h6>
                    <div style="height: 220px;">
                        <canvas id="chartBarPartsLabor"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="alerts-card">
                    <h6>Quick notifications</h6>
                    <div id="quickAlertsContainer"></div>
                </div>
            </div>
            <div class="col-12 col-lg-4"></div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="list-card">
                    <h6>Top Selling Parts</h6>
                    <div id="topPartsContainer"></div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="list-card">
                    <h6>Top Labor Services</h6>
                    <div id="topLaborContainer"></div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="list-card">
                    <h6>Sales Split</h6>
                    <div class="donut-wrap" style="height: 200px;">
                        <canvas id="chartSalesSplit"></canvas>
                        <div class="donut-center" id="donutCenter">—</div>
                    </div>
                    <div class="mt-2 small text-center text-muted" id="salesSplitLegend"></div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions (all sales & money) -->
        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="chart-card">
                    <h6 class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <span><i class="bi bi-receipt me-1"></i>Recent Sales &amp; Transactions</span>
                        <a class="btn btn-dash btn-sm" href="sales.php"><i class="bi bi-plus-lg me-1"></i>New Sale</a>
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover table-borderless align-middle mb-0">
                            <thead>
                                <tr class="text-uppercase small" style="color: var(--dash-muted);">
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Plate</th>
                                    <th class="text-end">Parts</th>
                                    <th class="text-end">Labor</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody id="recentTransactionsBody">
                                <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="recentTransactionsEmpty" class="d-none small text-muted text-center py-3">No sales yet. <a href="sales.php">Record a sale</a> to see it here.</div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const formatMoney = (n) => 'P' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    const formatMoneyShort = (n) => {
        if (n >= 1000) return 'P' + (n / 1000).toFixed(1) + 'K';
        return 'P' + Number(n).toFixed(0);
    };

    let chartMonthlyTrend, chartBarPartsLabor, chartSalesSplit;

    function renderKpis(kpis) {
        document.getElementById('kpi-today-sales').textContent = formatMoney(kpis.today_sales);
        const t = kpis.today_sales_trend_pct;
        const trendEl = document.getElementById('kpi-today-trend');
        if (t >= 0) trendEl.innerHTML = '<span class="trend-up"><i class="bi bi-arrow-up"></i> ' + t + '% vs last week</span>';
        else trendEl.innerHTML = '<span class="trend-down"><i class="bi bi-arrow-down"></i> ' + Math.abs(t) + '% vs last week</span>';
        
        // Show daily sales count and items sold
        const detailsEl = document.getElementById('kpi-today-details');
        const salesCount = kpis.today_sales_count || 0;
        const itemsSold = kpis.today_items_sold || 0;
        if (salesCount > 0 || itemsSold > 0) {
            detailsEl.innerHTML = '<i class="bi bi-cart-check"></i> ' + salesCount + ' transaction' + (salesCount !== 1 ? 's' : '') + 
                                  ' &middot; ' + itemsSold + ' item' + (itemsSold !== 1 ? 's' : '') + ' sold';
        } else {
            detailsEl.textContent = 'No sales today';
        }

        document.getElementById('kpi-parts-stock').textContent = kpis.parts_in_stock;
        document.getElementById('kpi-low-stock').textContent = kpis.low_stock_alerts > 0
            ? '<span class="trend-down"><i class="bi bi-arrow-down"></i> ' + kpis.low_stock_alerts + ' low-stock alerts</span>'
            : 'No low-stock alerts';

        document.getElementById('kpi-work-orders').textContent = kpis.open_work_orders;
        document.getElementById('kpi-completed-today').textContent = kpis.work_orders_completed_today > 0
            ? '<span class="trend-up"><i class="bi bi-arrow-up"></i> ' + kpis.work_orders_completed_today + ' completed today</span>'
            : '';

        document.getElementById('kpi-rev-label').textContent = (kpis.current_month_label || 'Monthly') + ' Revenue';
        document.getElementById('kpi-revenue').textContent = formatMoneyShort(kpis.current_month_revenue);
        const r = kpis.revenue_trend_pct;
        const revTrend = document.getElementById('kpi-rev-trend');
        if (r >= 0) revTrend.innerHTML = '<span class="trend-up">vs ' + (kpis.compare_month_label || 'prev') + ' ' + formatMoneyShort(kpis.compare_month_revenue) + '</span>';
        else revTrend.innerHTML = '<span class="trend-down">vs ' + (kpis.compare_month_label || 'prev') + ' ' + formatMoneyShort(kpis.compare_month_revenue) + '</span>';
    }

    function renderAlerts(alerts) {
        const container = document.getElementById('alertsContainer');
        const icons = { 'exclamation-triangle': 'warning', 'droplet': 'oil', 'arrow-repeat': 'info', 'check-circle': 'success' };
        container.innerHTML = alerts.map(a => `
            <div class="alert-item">
                <div class="icon-wrap ${icons[a.icon] || 'info'}"><i class="bi bi-${a.icon}"></i></div>
                <div class="flex-grow-1">
                    <div class="title">${a.title}</div>
                    <div class="detail">${a.detail}</div>
                    <div class="time">${a.time}</div>
                </div>
            </div>
        `).join('');
    }

    function renderQuickAlerts(alerts) {
        const slice = alerts.slice(0, 2);
        const container = document.getElementById('quickAlertsContainer');
        const icons = { 'arrow-repeat': 'info', 'check-circle': 'success' };
        container.innerHTML = slice.map(a => `
            <div class="alert-item">
                <div class="icon-wrap ${icons[a.icon] || 'info'}"><i class="bi bi-${a.icon}"></i></div>
                <div><div class="title">${a.title}</div><div class="detail">${a.detail}</div><div class="time">${a.time}</div></div>
            </div>
        `).join('');
    }

    function renderTopParts(parts, maxSales) {
        const container = document.getElementById('topPartsContainer');
        if (!parts.length) { container.innerHTML = '<p class="small text-muted">No sales data yet.</p>'; return; }
        maxSales = maxSales || Math.max(...parts.map(p => p.total_sales), 1);
        container.innerHTML = parts.map((p, i) => {
            const pct = Math.round((p.total_sales / maxSales) * 100);
            return `
            <div class="top-part-row">
                <span class="rank">#${i + 1}</span>
                <span class="label">${p.description}</span>
                <div class="progress-wrap"><div class="progress"><div class="progress-bar bg-orange" style="width:${pct}%"></div></div></div>
                <span class="amount">${formatMoney(p.total_sales)}</span>
            </div>`;
        }).join('');
    }

    function renderTopLabor(labor, maxRev) {
        const container = document.getElementById('topLaborContainer');
        if (!labor.length) { container.innerHTML = '<p class="small text-muted">No labor data yet.</p>'; return; }
        maxRev = maxRev || Math.max(...labor.map(l => l.total_revenue), 1);
        container.innerHTML = labor.map((l, i) => {
            const pct = Math.round((l.total_revenue / maxRev) * 100);
            return `
            <div class="top-labor-row top-part-row">
                <span class="rank">#${i + 1}</span>
                <span class="label">${l.service_name}</span>
                <div class="progress-wrap"><div class="progress"><div class="progress-bar bg-blue" style="width:${pct}%"></div></div></div>
                <span class="amount">${formatMoney(l.total_revenue)}</span>
            </div>`;
        }).join('');
    }

    function renderRecentTransactions(list) {
        const tbody = document.getElementById('recentTransactionsBody');
        const emptyEl = document.getElementById('recentTransactionsEmpty');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '';
            if (emptyEl) emptyEl.classList.remove('d-none');
            return;
        }
        if (emptyEl) emptyEl.classList.add('d-none');
        const fmtDate = (d) => {
            if (!d) return '—';
            const dt = new Date(d + 'T00:00:00');
            return isNaN(dt.getTime()) ? d : dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        };
        tbody.innerHTML = list.map(t => `
            <tr>
                <td>${fmtDate(t.sale_date)}</td>
                <td>${escapeHtml(t.customer_name)}</td>
                <td>${escapeHtml(t.plate_number)}</td>
                <td class="text-end text-success">${formatMoney(t.parts_total)}</td>
                <td class="text-end text-info">${formatMoney(t.labor_total)}</td>
                <td class="text-end fw-bold">${formatMoney(t.total)}</td>
            </tr>
        `).join('');
    }
    function escapeHtml(s) { if (s == null) return ''; const div = document.createElement('div'); div.textContent = s; return div.innerHTML; }

    function buildCharts(data) {
        const trend = data.monthly_trend || [];
        const labels = trend.length ? trend.map(m => m.label) : (function() {
            const m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            const d = new Date();
            const out = [];
            for (let i = 5; i >= 0; i--) {
                const x = new Date(d.getFullYear(), d.getMonth() - i, 1);
                out.push(m[x.getMonth()]);
            }
            return out;
        })();
        const partsData = trend.length ? trend.map(m => m.parts) : labels.map(() => 0);
        const laborData = trend.length ? trend.map(m => m.labor) : labels.map(() => 0);
        const totalData = trend.length ? trend.map(m => m.total) : labels.map(() => 0);

        if (chartMonthlyTrend) chartMonthlyTrend.destroy();
        const ctx1 = document.getElementById('chartMonthlyTrend');
        if (ctx1) {
            chartMonthlyTrend = new Chart(ctx1.getContext('2d'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Parts Sales', data: partsData, backgroundColor: 'rgba(249,115,22,0.8)', borderRadius: 6 },
                        { label: 'Labor', data: laborData, backgroundColor: 'rgba(59,130,246,0.8)', borderRadius: 6 },
                        { label: 'Total', data: totalData, type: 'line', borderColor: '#22c55e', backgroundColor: 'transparent', fill: false, tension: 0.3, pointRadius: 5 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { color: '#94a3b8' } } },
                    scales: {
                        x: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: '#94a3b8' } },
                        y: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: '#94a3b8', callback: v => 'P' + (v/1000) + 'K' } }
                    }
                }
            });
        }

        if (chartBarPartsLabor) chartBarPartsLabor.destroy();
        const ctx2 = document.getElementById('chartBarPartsLabor');
        if (ctx2) {
            chartBarPartsLabor = new Chart(ctx2.getContext('2d'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Parts Sales', data: partsData, backgroundColor: 'rgba(249,115,22,0.8)', borderRadius: 6 },
                        { label: 'Labor', data: laborData, backgroundColor: 'rgba(59,130,246,0.8)', borderRadius: 6 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { color: '#94a3b8' } } },
                    scales: {
                        x: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: '#94a3b8' } },
                        y: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: '#94a3b8', callback: v => typeof v === 'number' ? 'P' + (v >= 1000 ? (v/1000) + 'K' : v) : v } }
                    }
                }
            });
        }

        const split = data.sales_split || {};
        const partsAmt = split.parts_amount || 0;
        const laborAmt = split.labor_amount || 0;
        const totalAmt = partsAmt + laborAmt;
        const donutCenter = document.getElementById('donutCenter');
        const salesSplitLegend = document.getElementById('salesSplitLegend');
        if (donutCenter) donutCenter.textContent = totalAmt > 0 ? (split.parts_pct || 0) + '% Parts' : 'No data';
        if (salesSplitLegend) salesSplitLegend.innerHTML = 'Parts: ' + formatMoney(partsAmt) + ' (' + (split.parts_pct || 0) + '%) &middot; Labor: ' + formatMoney(laborAmt) + ' (' + (split.labor_pct || 0) + '%)';

        if (chartSalesSplit) chartSalesSplit.destroy();
        const ctx3 = document.getElementById('chartSalesSplit');
        if (ctx3) {
            const doughnutValues = totalAmt > 0 ? [partsAmt, laborAmt] : [1, 1];
            const doughnutColors = totalAmt > 0 ? ['#f97316', '#3b82f6'] : ['rgba(255,255,255,0.1)', 'rgba(255,255,255,0.05)'];
            chartSalesSplit = new Chart(ctx3.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Parts', 'Labor'],
                    datasets: [{
                        data: doughnutValues,
                        backgroundColor: doughnutColors,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: { legend: { display: false } }
                }
            });
        }
    }

    fetch('fetch_dashboard_data.php')
        .then(r => r.json())
        .then(res => {
            if (!res.success) { console.error(res.message); return; }
            const d = res;
            renderKpis(d.kpis);
            renderAlerts(d.alerts || []);
            renderQuickAlerts(d.alerts || []);
            renderTopParts(d.top_selling_parts || []);
            renderTopLabor(d.top_labor_services || []);
            renderRecentTransactions(d.recent_transactions || []);
            buildCharts(d);
        })
        .catch(err => console.error('Dashboard load failed', err));
})();
</script>
</body>
</html>
