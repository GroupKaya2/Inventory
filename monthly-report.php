<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$activePage = 'monthly_report';
$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Sales Report  DSpeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .rpt-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
            padding: 16px 18px;
            background: #111827;
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .rpt-field label {
            display: block;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #4b5a6e;
            margin-bottom: 5px;
        }

        .rpt-date {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
            color: #e2e8f0;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: .88rem;
            font-family: 'Inter', sans-serif;
            min-width: 160px;
        }

        .rpt-date:focus {
            outline: none;
            border-color: rgba(74, 222, 128, .35);
            box-shadow: 0 0 0 3px rgba(74, 222, 128, .08);
        }

        .rpt-hint {
            flex: 1 1 220px;
            font-size: .78rem;
            color: #64748b;
            line-height: 1.45;
            padding-bottom: 4px;
        }

        .rpt-hint strong {
            color: #94a3b8;
        }

        .week-nav {
            display: flex;
            gap: 6px;
        }

        .btn-week {
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .1);
            color: #94a3b8;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: .8rem;
            cursor: pointer;
            transition: all .15s;
        }

        .btn-week:hover {
            background: rgba(74, 222, 128, .08);
            border-color: rgba(74, 222, 128, .25);
            color: #e2e8f0;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .sum-card {
            background: #111827;
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 10px;
            padding: 14px 16px;
        }

        .sum-card .lbl {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .45px;
            color: #4b5a6e;
            margin-bottom: 6px;
        }

        .sum-card .val {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
        }

        .sum-card .sub {
            font-size: .68rem;
            color: #4b5a6e;
            margin-top: 4px;
        }

        .sum-card.week-net {
            border-color: rgba(74, 222, 128, .2);
            background: linear-gradient(135deg, rgba(74, 222, 128, .06), #111827);
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
            padding: 16px 22px;
        }

        .ledger-heading h5 {
            margin: 0;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
        }

        .ledger-heading p {
            margin: 4px 0 0;
            font-size: .76rem;
            color: #4b5a6e;
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
            letter-spacing: .5px;
            padding: 11px 14px;
            border-bottom: 1px solid rgba(74, 222, 128, .15);
            border-right: 1px solid rgba(255, 255, 255, .04);
            text-align: center;
            vertical-align: bottom;
            line-height: 1.35;
        }

        .ledger-tbl thead th:first-child,
        .ledger-tbl thead th:nth-child(2) {
            text-align: left;
        }

        .ledger-tbl thead th:last-child {
            border-right: none;
        }

        .ledger-tbl tbody td {
            padding: 10px 14px;
            font-size: .82rem;
            color: #e2e8f0;
            border-bottom: 1px solid rgba(255, 255, 255, .04);
            border-right: 1px solid rgba(255, 255, 255, .03);
            text-align: center;
            vertical-align: middle;
        }

        .ledger-tbl tbody td:first-child,
        .ledger-tbl tbody td:nth-child(2) {
            text-align: left;
        }

        .ledger-tbl tbody td:last-child {
            border-right: none;
        }

        .ledger-tbl tbody tr:not(.row-total):hover td {
            background: rgba(74, 222, 128, .02);
        }

        .row-dim td {
            opacity: .35;
        }

        .row-week td {
            background: rgba(74, 222, 128, .08);
            border-top: 2px solid rgba(74, 222, 128, .25);
            font-weight: 700;
            font-size: .84rem;
            color: #fff;
            padding: 12px 14px;
        }

        .row-week td:first-child {
            color: #4ade80;
            font-family: 'Space Grotesk', sans-serif;
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .row-month td {
            background: rgba(96, 165, 250, .08);
            border-top: 2px solid rgba(96, 165, 250, .25);
            font-weight: 800;
            font-size: .86rem;
            color: #fff;
            padding: 14px;
        }

        .row-month td:first-child {
            color: #60a5fa;
            font-family: 'Space Grotesk', sans-serif;
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .cv-parts { color: #60a5fa; font-weight: 600; }
        .cv-labor { color: #4ade80; font-weight: 600; }
        .cv-exp { color: #f87171; font-weight: 600; }
        .cv-net { color: #fff; font-weight: 700; }
        .cv-neg { color: #f87171; font-weight: 700; }

        .exp-desc-list {
            font-size: .72rem;
            color: #f87171;
            line-height: 1.55;
            max-width: 220px;
        }

        .exp-desc-list div {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .d-num {
            font-weight: 700;
            color: #e2e8f0;
            font-family: 'Space Grotesk', sans-serif;
            font-size: .95rem;
        }

        .d-name {
            font-size: .7rem;
            color: #4b5a6e;
            margin-left: 4px;
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

        .formula-hint {
            font-size: .72rem;
            color: #4b5a6e;
            padding: 10px 22px 14px;
            border-top: 1px solid rgba(255, 255, 255, .05);
        }

        .formula-hint i {
            color: #4ade80;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <main class="app-main">

        <div class="page-header mb-3">
            <h4 style="margin:0;"><i class="bi bi-calendar-week me-2"></i>Weekly Sales Report</h4>
            <p style="margin:4px 0 0;color:#64748b;font-size:.85rem;">
                Pick any date to view that week (Monday–Saturday). Weekly and monthly totals are shown at the bottom of the table.
            </p>
        </div>

        <!-- Toolbar -->
        <div class="rpt-toolbar">
            <div class="rpt-field">
                <label for="selDate"><i class="bi bi-calendar3 me-1"></i>Choose a date</label>
                <input type="date" class="rpt-date" id="selDate" value="<?= htmlspecialchars($today) ?>">
            </div>
            <div class="week-nav">
                <button type="button" class="btn-week" id="btnPrevWeek" title="Previous week">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <button type="button" class="btn-week" id="btnToday">Today</button>
                <button type="button" class="btn-week" id="btnNextWeek" title="Next week">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
            <button type="button" class="btn-pink" onclick="loadReport()" style="padding:8px 18px;font-size:.84rem;">
                <i class="bi bi-table me-1"></i>Show Week
            </button>
            <?php if ($isOwner): ?>
                <button type="button" class="btn-ghost" onclick="exportCSV()" id="exportBtn"
                    style="padding:8px 16px;font-size:.84rem;display:none;">
                    <i class="bi bi-download me-1"></i>Export CSV
                </button>
            <?php endif; ?>

        </div>

        <div id="stateLoading" class="state-box" style="display:none;">
            <div class="spinner-border" style="color:#4ade80;width:2.5rem;height:2.5rem;margin-bottom:14px;"></div>
            <p>Loading report…</p>
        </div>
        <div id="stateEmpty" class="state-box" style="display:none;">
            <i class="bi bi-calendar-x"></i>
            <p>No sales or expenses recorded for this week.</p>
        </div>

        <div id="reportOut" style="display:none;">

            <!-- Quick summary -->
            <div class="summary-grid" id="summaryGrid"></div>

            <!-- Table -->
            <div class="ledger-card">
                <div class="ledger-heading">
                    <h5 id="ledgerHeading">Week of …</h5>
                    <p id="ledgerSub">Monday – Saturday</p>
                </div>

                <div class="table-responsive">
                    <table class="ledger-tbl">
                        <thead>
                            <tr>
                                <th style="min-width:100px;">Date</th>
                                <th style="min-width:200px;">List of Expenses</th>
                                <th style="min-width:110px;">Total<br>Expenses</th>
                                <th style="min-width:115px;">Revenue<br>Products</th>
                                <th style="min-width:115px;">Revenue<br>Labor</th>
                                <th style="min-width:120px;">Daily Net<br>Sales</th>
                            </tr>
                        </thead>
                        <tbody id="ledgerBody"></tbody>
                    </table>
                </div>

                <div class="formula-hint">
                    <i class="bi bi-info-circle me-1"></i>
                    Daily Net Sales = Revenue (Products + Labor) − Total Expenses for that day.
                </div>
            </div>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        'use strict';
        const IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;
        let _data = null;

        const selDate = document.getElementById('selDate');

        function peso(n) {
            return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function esc(s) {
            return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        function hasActivity(day) {
            return day.has_data || parseFloat(day.expenses) > 0;
        }

        function shiftDate(days) {
            const d = new Date(selDate.value + 'T12:00:00');
            d.setDate(d.getDate() + days);
            selDate.value = d.toISOString().slice(0, 10);
            loadReport();
        }

        document.getElementById('btnPrevWeek').addEventListener('click', () => shiftDate(-7));
        document.getElementById('btnNextWeek').addEventListener('click', () => shiftDate(7));
        document.getElementById('btnToday').addEventListener('click', () => {
            selDate.value = new Date().toISOString().slice(0, 10);
            loadReport();
        });
        selDate.addEventListener('change', loadReport);

        async function loadReport() {
            const date = selDate.value;
            if (!date) return;

            document.getElementById('stateLoading').style.display = '';
            document.getElementById('stateEmpty').style.display = 'none';
            document.getElementById('reportOut').style.display = 'none';
            if (IS_OWNER) document.getElementById('exportBtn').style.display = 'none';

            try {
                const resp = await fetch(`backend/monthly-report.php?date=${encodeURIComponent(date)}`);
                const data = await resp.json();
                document.getElementById('stateLoading').style.display = 'none';

                if (!data.success) {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                    return;
                }

                _data = data;
                renderSummary(data);
                renderTable(data);
                document.getElementById('reportOut').style.display = '';
                if (IS_OWNER) document.getElementById('exportBtn').style.display = '';

            } catch (err) {
                document.getElementById('stateLoading').style.display = 'none';
                Swal.fire({ icon: 'error', title: 'Network Error', text: err.message });
            }
        }

        function renderSummary(data) {
            const w = data.week_totals;
            const m = data.month_totals;
            const netColor = parseFloat(w.net) >= 0 ? '#4ade80' : '#f87171';

            document.getElementById('summaryGrid').innerHTML = `
                <div class="sum-card">
                    <div class="lbl">Week — Products</div>
                    <div class="val cv-parts">${peso(w.parts)}</div>
                    <div class="sub">This Mon–Sat</div>
                </div>
                <div class="sum-card">
                    <div class="lbl">Week — Labor</div>
                    <div class="val cv-labor">${peso(w.labor)}</div>
                    <div class="sub">This Mon–Sat</div>
                </div>
                <div class="sum-card">
                    <div class="lbl">Week — Expenses</div>
                    <div class="val cv-exp">${peso(w.expenses)}</div>
                    <div class="sub">This Mon–Sat</div>
                </div>
                <div class="sum-card week-net">
                    <div class="lbl">Week — Net Sales</div>
                    <div class="val" style="color:${netColor}">${peso(w.net)}</div>
                    <div class="sub">${data.week_label}</div>
                </div>
                <div class="sum-card">
                    <div class="lbl">Month — Net Sales</div>
                    <div class="val" style="color:${parseFloat(m.net) >= 0 ? '#60a5fa' : '#f87171'}">${peso(m.net)}</div>
                    <div class="sub">${data.month_name} (full month)</div>
                </div>
            `;
        }

        function renderDayRow(day) {
            const active = hasActivity(day);
            const d = new Date(day.date + 'T12:00:00');
            const dayNum = d.getDate();
            const dayName = day.day_name || d.toLocaleDateString('en-PH', { weekday: 'short' });

            let expCell = '<span style="color:#2e3a4e;">—</span>';
            if (day.expense_items && day.expense_items.length > 0) {
                expCell = '<div class="exp-desc-list">'
                    + day.expense_items.map(e =>
                        `<div title="${esc(e.description)} (${peso(e.amount)})">· ${esc(e.description)} <span style="color:#64748b;">${peso(e.amount)}</span></div>`
                    ).join('')
                    + '</div>';
            }

            const isNeg = parseFloat(day.net) < 0;
            const netCls = active ? (isNeg ? 'cv-neg' : 'cv-net') : '';
            const dash = '<span style="color:#2e3a4e;">—</span>';

            const tr = document.createElement('tr');
            if (!active) tr.className = 'row-dim';
            tr.innerHTML = `
                <td><span class="d-num">${dayNum}</span><span class="d-name">${dayName}</span></td>
                <td>${active ? expCell : dash}</td>
                <td class="cv-exp">${active ? peso(day.expenses) : dash}</td>
                <td class="cv-parts">${day.txn_count > 0 || parseFloat(day.parts) > 0 ? peso(day.parts) : dash}</td>
                <td class="cv-labor">${day.txn_count > 0 || parseFloat(day.labor) > 0 ? peso(day.labor) : dash}</td>
                <td class="${netCls}">${active ? peso(day.net) : dash}</td>
            `;
            return tr;
        }

        function renderTotalRow(label, totals, rowClass, firstColClass) {
            const isNeg = parseFloat(totals.net) < 0;
            const tr = document.createElement('tr');
            tr.className = rowClass;
            tr.innerHTML = `
                <td colspan="2" class="${firstColClass || ''}">${label}</td>
                <td class="cv-exp">${peso(totals.expenses)}</td>
                <td class="cv-parts">${peso(totals.parts)}</td>
                <td class="cv-labor">${peso(totals.labor)}</td>
                <td class="${isNeg ? 'cv-neg' : 'cv-net'}">${peso(totals.net)}</td>
            `;
            return tr;
        }

        function renderTable(data) {
            document.getElementById('ledgerHeading').textContent = 'Week of ' + data.week_label;
            document.getElementById('ledgerSub').textContent = data.week_subtitle + ' · Pick another date to jump to a different week';

            const tbody = document.getElementById('ledgerBody');
            tbody.innerHTML = '';

            data.days.forEach(day => tbody.appendChild(renderDayRow(day)));
            tbody.appendChild(renderTotalRow('Weekly Total (Mon–Sat)', data.week_totals, 'row-week'));
            tbody.appendChild(renderTotalRow('Monthly Total — ' + data.month_name, data.month_totals, 'row-month'));
        }

        function exportCSV() {
            if (!_data) return;
            const rows = [
                ['Weekly Sales Report — ' + _data.week_label],
                ['Month: ' + _data.month_name],
                [],
                ['Date', 'List of Expenses', 'Total Expenses', 'Revenue Products', 'Revenue Labor', 'Daily Net Sales'],
            ];

            _data.days.forEach(d => {
                const expList = (d.expense_items || []).map(e => e.description + ' (' + e.amount.toFixed(2) + ')').join('; ') || '—';
                const active = hasActivity(d);
                rows.push([
                    d.date,
                    active ? expList : '—',
                    active ? parseFloat(d.expenses).toFixed(2) : '',
                    parseFloat(d.parts).toFixed(2),
                    parseFloat(d.labor).toFixed(2),
                    active ? parseFloat(d.net).toFixed(2) : '',
                ]);
            });

            const w = _data.week_totals;
            const m = _data.month_totals;
            rows.push([]);
            rows.push(['Weekly Total (Mon–Sat)', '', w.expenses.toFixed(2), w.parts.toFixed(2), w.labor.toFixed(2), w.net.toFixed(2)]);
            rows.push(['Monthly Total — ' + _data.month_name, '', m.expenses.toFixed(2), m.parts.toFixed(2), m.labor.toFixed(2), m.net.toFixed(2)]);

            const csv = rows.map(r => r.map(v => '"' + String(v ?? '').replace(/"/g, '""') + '"').join(',')).join('\n');
            const a = document.createElement('a');
            a.href = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
            a.download = `weekly_report_${_data.week_start}_to_${_data.week_end}.csv`;
            a.click();
        }

        loadReport();
    </script>
</body>

</html>
