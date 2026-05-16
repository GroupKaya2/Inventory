<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$activePage = 'monthly_report';
$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Sales Report — DSpeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .rpt-select {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
            color: #e2e8f0;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: .84rem;
            font-family: 'Inter', sans-serif;
            transition: border-color .15s;
        }

        .rpt-select:focus {
            outline: none;
            border-color: rgba(74, 222, 128, .35);
            box-shadow: 0 0 0 3px rgba(74, 222, 128, .08);
        }

        .rpt-select option {
            background: #161b27;
        }

        .ledger-card {
            background: #111827;
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 10px;
            overflow: hidden;
        }

        .ledger-heading {
            background: linear-gradient(135deg, #0f1f15, #111827);
            border-bottom: 1px solid rgba(74, 222, 128, .15);
            padding: 14px 22px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: .95rem;
            font-weight: 700;
            color: #fff;
        }

        .ledger-tbl {
            width: 100%;
            border-collapse: collapse;
        }

        .ledger-tbl thead th {
            background: rgba(74, 222, 128, .06);
            color: #4ade80;
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            padding: 10px 14px;
            border-bottom: 1px solid rgba(74, 222, 128, .15);
            border-right: 1px solid rgba(255, 255, 255, .04);
            text-align: center;
            white-space: nowrap;
        }

        .ledger-tbl thead th:first-child {
            text-align: left;
        }

        .ledger-tbl thead th:last-child {
            border-right: none;
        }

        .ledger-tbl tbody td {
            padding: 8px 14px;
            font-size: .82rem;
            color: #e2e8f0;
            border-bottom: 1px solid rgba(255, 255, 255, .04);
            border-right: 1px solid rgba(255, 255, 255, .03);
            text-align: center;
            vertical-align: middle;
        }

        .ledger-tbl tbody td:first-child {
            text-align: left;
        }

        .ledger-tbl tbody td:last-child {
            border-right: none;
        }

        .ledger-tbl tbody tr:hover td {
            background: rgba(74, 222, 128, .02);
        }

        .row-dim td {
            opacity: .3;
        }

        .row-week td {
            background: rgba(74, 222, 128, .05);
            border-top: 1px solid rgba(74, 222, 128, .15);
            border-bottom: 1px solid rgba(74, 222, 128, .15);
            font-weight: 700;
            font-size: .8rem;
            color: #fff;
            padding: 10px 14px;
        }

        .row-week td:first-child {
            color: #4ade80;
            font-family: 'Space Grotesk', sans-serif;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .monthly-totals {
            border-top: 1px solid rgba(74, 222, 128, .12);
            padding: 18px 22px;
        }

        .tot-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 0;
            border-bottom: 1px solid rgba(255, 255, 255, .05);
            font-size: .85rem;
        }

        .tot-row:last-child {
            border-bottom: none;
        }

        .tot-row .t-lbl {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #64748b;
            font-weight: 600;
        }

        .tot-row .t-val {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: .93rem;
        }

        .tot-grand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 12px;
            padding: 14px 18px;
            background: rgba(74, 222, 128, .06);
            border: 1px solid rgba(74, 222, 128, .2);
            border-radius: 9px;
        }

        .tot-grand .t-lbl {
            color: #e2e8f0;
            font-size: .92rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .tot-grand .t-val {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 800;
            font-size: 1.2rem;
        }

        .cv-parts {
            color: #60a5fa;
            font-weight: 600;
        }

        .cv-labor {
            color: #4ade80;
            font-weight: 600;
        }

        .cv-exp {
            color: #f87171;
            font-weight: 600;
        }

        .cv-net {
            color: #fff;
            font-weight: 700;
        }

        .cv-neg {
            color: #f87171;
            font-weight: 700;
        }

        .exp-desc-list {
            text-align: left;
            font-size: .71rem;
            color: #f87171;
            line-height: 1.5;
        }

        .exp-desc-list div {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 190px;
        }

        .d-num {
            font-weight: 700;
            color: #e2e8f0;
        }

        .d-name {
            font-size: .68rem;
            color: #4b5a6e;
        }

        .state-box {
            text-align: center;
            padding: 70px 20px;
            color: #4b5a6e;
        }

        .state-box i {
            font-size: 3rem;
            display: block;
            margin-bottom: 14px;
        }

        /* Chart wrap */
        .chart-wrap {
            background: #111827;
            border: 1px solid rgba(255, 255, 255, .06);
            border-radius: 10px;
            padding: 18px;
        }

        .chart-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: .88rem;
            font-weight: 600;
            color: #e2e8f0;
            margin-bottom: 4px;
        }

        .chart-sub {
            font-size: .72rem;
            color: #4b5a6e;
            margin-bottom: 14px;
        }

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

        .cleg-dash {
            width: 14px;
            height: 0;
            border-top: 2px dashed;
            flex-shrink: 0;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <main class="app-main">

        <div class="page-header mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 style="margin:0;"><i class="bi bi-bar-chart-line-fill me-2"></i>Monthly Sales Report</h4>
                    <p style="margin:0;">Daily records · Weekly totals · Charts</p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <select class="rpt-select" id="selMonth">
                        <?php
                        $mNames = [
                            'January',
                            'February',
                            'March',
                            'April',
                            'May',
                            'June',
                            'July',
                            'August',
                            'September',
                            'October',
                            'November',
                            'December'
                        ];
                        for ($i = 1; $i <= 12; $i++) {
                            $sel = ($i == (int) date('n')) ? 'selected' : '';
                            echo "<option value=\"$i\" $sel>{$mNames[$i - 1]}</option>";
                        }
                        ?>
                    </select>
                    <select class="rpt-select" id="selYear">
                        <?php
                        $cy = (int) date('Y');
                        for ($y = $cy; $y >= $cy - 3; $y--) {
                            $s = ($y === $cy) ? 'selected' : '';
                            echo "<option value=\"$y\" $s>$y</option>";
                        }
                        ?>
                    </select>
                    <button class="btn-pink" onclick="loadReport()" style="padding:8px 18px;font-size:.84rem;">
                        <i class="bi bi-search me-1"></i>Load
                    </button>
                    <?php if ($isOwner): ?>
                        <button class="btn-ghost" onclick="exportCSV()" id="exportBtn"
                            style="padding:8px 16px;font-size:.84rem;display:none;">
                            <i class="bi bi-download me-1"></i>Export CSV
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="stateLoading" class="state-box" style="display:none;">
            <div class="spinner-border" style="color:#4ade80;width:2.5rem;height:2.5rem;margin-bottom:14px;"></div>
            <p>Loading report…</p>
        </div>
        <div id="stateEmpty" class="state-box" style="display:none;">
            <i class="bi bi-calendar-x"></i>
            No data found for this period.
        </div>

        <div id="reportOut" style="display:none;">

            <!-- ═══ CHARTS ROW ═══ -->
            <div class="row g-3 mb-4" id="chartsRow">

                <!-- Weekly revenue bar -->
                <div class="col-lg-8">
                    <div class="chart-wrap">
                        <div class="chart-title"><i class="bi bi-bar-chart me-2" style="color:#4ade80;"></i>Weekly
                            Revenue Breakdown</div>
                        <div class="chart-sub">Parts · Labor · Expenses — per week</div>
                        <div class="clegend">
                            <span><span class="cleg-sq" style="background:#378ADD;"></span>Parts</span>
                            <span><span class="cleg-sq" style="background:#1D9E75;"></span>Labor</span>
                            <span><span class="cleg-dash" style="border-color:#f87171;"></span>Expenses</span>
                        </div>
                        <div style="position:relative;height:220px;">
                            <canvas id="weeklyChart" role="img" aria-label="Weekly revenue breakdown bar chart">Weekly
                                parts, labor, and expenses.</canvas>
                        </div>
                    </div>
                </div>

                <!-- Monthly donut -->
                <div class="col-lg-4">
                    <div class="chart-wrap">
                        <div class="chart-title"><i class="bi bi-pie-chart-fill me-2" style="color:#60a5fa;"></i>Revenue
                            Split</div>
                        <div class="chart-sub">Parts vs Labor this month</div>
                        <div class="clegend">
                            <span><span class="cleg-sq" style="background:#378ADD;"></span>Parts</span>
                            <span><span class="cleg-sq" style="background:#1D9E75;"></span>Labor</span>
                        </div>
                        <div style="position:relative;height:180px;">
                            <canvas id="splitDonut" role="img" aria-label="Donut chart of parts vs labor revenue">Parts
                                vs Labor split.</canvas>
                        </div>
                    </div>
                </div>

                <!-- Daily net profit line -->
                <div class="col-12">
                    <div class="chart-wrap">
                        <div class="chart-title"><i class="bi bi-graph-up me-2" style="color:#a78bfa;"></i>Daily Net
                            Profit</div>
                        <div class="chart-sub">Gross sales minus expenses — each working day</div>
                        <div class="clegend">
                            <span><span class="cleg-sq" style="background:#4ade80;"></span>Net Profit</span>
                            <span><span class="cleg-dash" style="border-color:#f87171;"></span>Expenses</span>
                        </div>
                        <div style="position:relative;height:180px;">
                            <canvas id="dailyLineChart" role="img" aria-label="Daily net profit line chart">Daily profit
                                over the month.</canvas>
                        </div>
                    </div>
                </div>

            </div>

            <!--  LEDGER TABLE -->
            <div class="ledger-card">
                <div class="ledger-heading" id="ledgerHeading">Monthly Sales Overview</div>

                <div class="monthly-totals" id="monthlyTotals" style="display:none;">
                    <div class="tot-row">
                        <span class="t-lbl"><i class="bi bi-box-seam" style="color:#60a5fa;"></i>Total Revenue from
                            Products / Goods</span>
                        <span class="t-val cv-parts" id="mParts">₱0.00</span>
                    </div>
                    <div class="tot-row">
                        <span class="t-lbl"><i class="bi bi-wrench-adjustable" style="color:#4ade80;"></i>Total Revenue
                            from Labor</span>
                        <span class="t-val cv-labor" id="mLabor">₱0.00</span>
                    </div>
                    <div class="tot-row">
                        <span class="t-lbl"><i class="bi bi-wallet2" style="color:#f87171;"></i>Total Expenses</span>
                        <span class="t-val cv-exp" id="mExp">₱0.00</span>
                    </div>
                    <div class="tot-grand">
                        <span class="t-lbl"><i class="bi bi-graph-up-arrow"
                                style="color:#4ade80;font-size:1.1rem;"></i>Net Monthly Revenue</span>
                        <span class="t-val" id="mNet">₱0.00</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="ledger-tbl">
                        <thead>
                            <tr>
                                <th style="min-width:90px;">Date</th>
                                <th style="min-width:180px;text-align:left;">List of Expenses</th>
                                <th style="min-width:110px;">Total<br>Expenses</th>
                                <th style="min-width:120px;">Revenue<br>Products</th>
                                <th style="min-width:120px;">Revenue<br>Labor</th>
                                <th style="min-width:120px;">Daily Net<br>Sales</th>
                            </tr>
                        </thead>
                        <tbody id="ledgerBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        'use strict';
        const IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;
        let _data = null;

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

        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#4b5a6e';

        let weeklyChart = null, splitDonut = null, dailyLine = null;

        function peso(n) {
            return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function esc(s) {
            return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        async function loadReport() {
            const month = document.getElementById('selMonth').value;
            const year = document.getElementById('selYear').value;

            document.getElementById('stateLoading').style.display = '';
            document.getElementById('stateEmpty').style.display = 'none';
            document.getElementById('reportOut').style.display = 'none';
            if (IS_OWNER) document.getElementById('exportBtn').style.display = 'none';

            try {
                const resp = await fetch(`backend/monthly-report.php?month=${month}&year=${year}`);
                const data = await resp.json();
                document.getElementById('stateLoading').style.display = 'none';

                if (!data.success) {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message }); return;
                }

                const hasAnyData = data.weeks.some(w => w.days.some(d => d.has_data || d.expenses > 0));
                if (!hasAnyData) {
                    document.getElementById('stateEmpty').style.display = ''; return;
                }

                _data = data;
                renderCharts(data);
                renderLedger(data);
                document.getElementById('reportOut').style.display = '';
                if (IS_OWNER) document.getElementById('exportBtn').style.display = '';

            } catch (err) {
                document.getElementById('stateLoading').style.display = 'none';
                Swal.fire({ icon: 'error', title: 'Network Error', text: err.message });
            }
        }

        function renderCharts(data) {
            // Destroy existing
            [weeklyChart, splitDonut, dailyLine].forEach(c => { if (c) c.destroy(); });

            const weeks = data.weeks;
            const weekLabels = weeks.map((_, i) => 'Week ' + (i + 1));

            // 1. Weekly grouped bar
            const wCtx = document.getElementById('weeklyChart').getContext('2d');
            weeklyChart = new Chart(wCtx, {
                type: 'bar',
                data: {
                    labels: weekLabels,
                    datasets: [
                        { label: 'Parts', data: weeks.map(w => parseFloat(w.parts)), backgroundColor: '#378ADD', borderRadius: 4 },
                        { label: 'Labor', data: weeks.map(w => parseFloat(w.labor)), backgroundColor: '#1D9E75', borderRadius: 4 },
                        { label: 'Expenses', data: weeks.map(w => parseFloat(w.expenses)), backgroundColor: 'rgba(248,113,113,.6)', borderRadius: 4 },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { ...TOOLTIP, callbacks: { label: c => ' ' + c.dataset.label + ': ' + peso(c.parsed.y) } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: TICK },
                        y: { grid: GRID, ticks: { ...TICK, callback: v => v >= 1000 ? '₱' + (v / 1000).toFixed(0) + 'k' : '₱' + v } }
                    }
                }
            });

            // 2. Parts vs Labor donut
            const sCtx = document.getElementById('splitDonut').getContext('2d');
            const totalParts = parseFloat(data.totals.parts);
            const totalLabor = parseFloat(data.totals.labor);
            const splitTotal = totalParts + totalLabor;
            splitDonut = new Chart(sCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Parts', 'Labor'],
                    datasets: [{
                        data: splitTotal > 0 ? [totalParts, totalLabor] : [1, 1],
                        backgroundColor: splitTotal > 0 ? ['#378ADD', '#1D9E75'] : ['#1e2a3a', '#1e2a3a'],
                        borderWidth: 0, hoverOffset: 6,
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
                                    if (splitTotal === 0) return ' No data';
                                    const pct = Math.round((c.parsed / splitTotal) * 100);
                                    return ' ' + c.label + ': ' + peso(c.parsed) + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });

            // 3. Daily net profit line
            const allDays = weeks.flatMap(w => w.days);
            const dCtx = document.getElementById('dailyLineChart').getContext('2d');
            dailyLine = new Chart(dCtx, {
                type: 'line',
                data: {
                    labels: allDays.map(d => {
                        const dt = new Date(d.date + 'T00:00:00');
                        return dt.getDate() + ' ' + dt.toLocaleDateString('en-PH', { month: 'short' });
                    }),
                    datasets: [
                        {
                            label: 'Net Profit',
                            data: allDays.map(d => parseFloat(d.net)),
                            borderColor: '#4ade80',
                            backgroundColor: ctx => {
                                const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 180);
                                g.addColorStop(0, 'rgba(74,222,128,.12)');
                                g.addColorStop(1, 'rgba(74,222,128,0)');
                                return g;
                            },
                            fill: true, tension: 0.4,
                            borderWidth: 2, pointRadius: 2, pointHoverRadius: 5,
                            pointBackgroundColor: '#4ade80',
                        },
                        {
                            label: 'Expenses',
                            data: allDays.map(d => parseFloat(d.expenses)),
                            borderColor: '#f87171',
                            backgroundColor: 'transparent',
                            fill: false, tension: 0.4,
                            borderWidth: 1.5, borderDash: [5, 3],
                            pointRadius: 2, pointHoverRadius: 4,
                            pointBackgroundColor: '#f87171',
                        },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: { ...TOOLTIP, callbacks: { label: c => ' ' + c.dataset.label + ': ' + peso(c.parsed.y) } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { ...TICK, maxTicksLimit: 12, maxRotation: 0 } },
                        y: { grid: GRID, ticks: { ...TICK, callback: v => v >= 1000 ? '₱' + (v / 1000).toFixed(0) + 'k' : '₱' + v } }
                    }
                }
            });
        }

        function renderLedger(data) {
            document.getElementById('ledgerHeading').textContent = 'Monthly Sales Overview — ' + data.month_name;

            const tbody = document.getElementById('ledgerBody');
            tbody.innerHTML = '';

            data.weeks.forEach((week, wi) => {
                week.days.forEach(day => {
                    const tr = document.createElement('tr');
                    const hasData = day.has_data || day.expenses > 0;
                    if (!hasData) tr.className = 'row-dim';

                    const d = new Date(day.date + 'T00:00:00');
                    const dayNum = d.getDate();
                    const dayName = d.toLocaleDateString('en-PH', { weekday: 'short' });

                    let expCell = '<span style="color:#2e3a4e;">—</span>';
                    if (day.expense_items && day.expense_items.length > 0) {
                        expCell = '<div class="exp-desc-list">'
                            + day.expense_items.map(e => `<div title="${esc(e.description)}">· ${esc(e.description)}</div>`).join('')
                            + '</div>';
                    }

                    const isNeg = parseFloat(day.net) < 0;
                    const netCls = hasData ? (isNeg ? 'cv-neg' : 'cv-net') : '';

                    tr.innerHTML = `
                <td><span class="d-num">${dayNum}</span> <span class="d-name">${dayName}</span></td>
                <td style="text-align:left;">${hasData ? expCell : '<span style="color:#2e3a4e;">—</span>'}</td>
                <td class="cv-exp">${hasData ? peso(day.expenses) : '—'}</td>
                <td class="cv-parts">${day.has_data ? peso(day.parts) : '—'}</td>
                <td class="cv-labor">${day.has_data ? peso(day.labor) : '—'}</td>
                <td class="${netCls}">${hasData ? peso(day.net) : '—'}</td>
            `;
                    tbody.appendChild(tr);
                });

                const wtr = document.createElement('tr');
                wtr.className = 'row-week';
                const wIsNeg = parseFloat(week.net) < 0;
                wtr.innerHTML = `
            <td colspan="2">Total for Week ${wi + 1}</td>
            <td class="cv-exp">${peso(week.expenses)}</td>
            <td class="cv-parts">${peso(week.parts)}</td>
            <td class="cv-labor">${peso(week.labor)}</td>
            <td class="${wIsNeg ? 'cv-neg' : 'cv-net'}">${peso(week.net)}</td>
        `;
                tbody.appendChild(wtr);
            });

            const t = data.totals;
            document.getElementById('mParts').textContent = peso(t.parts);
            document.getElementById('mLabor').textContent = peso(t.labor);
            document.getElementById('mExp').textContent = peso(t.expenses);
            const netEl = document.getElementById('mNet');
            netEl.textContent = peso(t.net);
            netEl.style.color = parseFloat(t.net) >= 0 ? '#4ade80' : '#f87171';
            document.getElementById('monthlyTotals').style.display = '';
        }

        function exportCSV() {
            if (!_data) return;
            const rows = [
                ['Monthly Sales Overview — ' + _data.month_name], [],
                ['Date', 'List of Expenses', 'Total Expenses (₱)', 'Revenue - Products (₱)', 'Revenue - Labor (₱)', 'Daily Net Sales (₱)'],
            ];
            _data.weeks.forEach((week, wi) => {
                week.days.forEach(d => {
                    const expList = (d.expense_items || []).map(e => e.description).join('; ') || '—';
                    const hasData = d.has_data || d.expenses > 0;
                    rows.push([
                        d.date, hasData ? expList : '—',
                        hasData ? d.expenses.toFixed(2) : '',
                        d.has_data ? d.parts.toFixed(2) : '',
                        d.has_data ? d.labor.toFixed(2) : '',
                        hasData ? d.net.toFixed(2) : '',
                    ]);
                });
                rows.push(['Total for Week ' + (wi + 1), '', week.expenses.toFixed(2), week.parts.toFixed(2), week.labor.toFixed(2), week.net.toFixed(2)]);
                rows.push([]);
            });
            const t = _data.totals;
            rows.push([], ['MONTHLY SUMMARY'],
                ['Total Revenue Products/Goods', '', '', t.parts.toFixed(2), '', ''],
                ['Total Revenue Labor', '', '', '', t.labor.toFixed(2), ''],
                ['Total Expenses', '', t.expenses.toFixed(2), '', '', ''],
                ['Net Monthly Revenue', '', '', '', '', t.net.toFixed(2)]
            );
            const csv = rows.map(r => r.map(v => '"' + (String(v ?? '').replace(/"/g, '""')) + '"').join(',')).join('\n');
            const a = document.createElement('a');
            a.href = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
            a.download = `monthly_report_${_data.year}_${String(_data.month).padStart(2, '0')}.csv`;
            a.click();
        }

        loadReport();
    </script>
</body>

</html>