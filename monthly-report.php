<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$activePage = 'monthly_report';
$isOwner    = ($_SESSION['role'] ?? 'manager') === 'owner';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Sales Report — Dispeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .rpt-select {
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.12);
            color: #e8ecf4; border-radius: 10px;
            padding: 8px 14px; font-size: .88rem;
            font-family: 'DM Sans', sans-serif;
        }
        .rpt-select:focus {
            outline: none; border-color: rgba(232,23,93,.5);
            box-shadow: 0 0 0 3px rgba(232,23,93,.12);
        }
        .rpt-select option { background: #1c2030; }

        /* ── Ledger card ── */
        .ledger-card {
            background: #1c2030;
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 14px;
            overflow: hidden;
        }
        .ledger-heading {
            background: linear-gradient(135deg, #e8175d, #9b0d43);
            padding: 15px 22px;
            font-family: 'Syne', sans-serif;
            font-size: 1rem; font-weight: 800; color: #fff;
        }

        /* ── Table ── */
        .ledger-tbl { width: 100%; border-collapse: collapse; }

        .ledger-tbl thead th {
            background: rgba(232,23,93,.1);
            color: #ff6b9d;
            font-size: .68rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .6px;
            padding: 11px 14px;
            border-bottom: 2px solid rgba(232,23,93,.22);
            border-right: 1px solid rgba(255,255,255,.05);
            text-align: center; white-space: nowrap;
        }
        .ledger-tbl thead th:first-child { text-align: left; }
        .ledger-tbl thead th:last-child  { border-right: none; }

        /* day rows */
        .ledger-tbl tbody td {
            padding: 8px 14px;
            font-size: .83rem; color: #e8ecf4;
            border-bottom: 1px solid rgba(255,255,255,.04);
            border-right: 1px solid rgba(255,255,255,.04);
            text-align: center; vertical-align: middle;
        }
        .ledger-tbl tbody td:first-child { text-align: left; border-left: none; }
        .ledger-tbl tbody td:last-child  { border-right: none; }
        .ledger-tbl tbody tr:hover td { background: rgba(255,255,255,.025); }

        /* empty / closed day */
        .row-dim td { opacity: .3; }

        /* week subtotal */
        .row-week td {
            background: rgba(232,23,93,.07);
            border-top: 1px solid rgba(232,23,93,.2);
            border-bottom: 2px solid rgba(232,23,93,.22);
            font-weight: 700; font-size: .81rem; color: #fff;
            padding: 10px 14px;
        }
        .row-week td:first-child {
            color: #e8175d;
            font-family: 'Syne', sans-serif;
            font-size: .74rem; text-transform: uppercase; letter-spacing: .5px;
        }

        /* Monthly totals */
        .monthly-totals {
            border-top: 2px solid rgba(232,23,93,.25);
            border-bottom: 2px solid rgba(232,23,93,.25);
            padding: 18px 22px 18px;
        }
        .tot-row {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,.05);
            font-size: .87rem;
        }
        .tot-row:last-child { border-bottom: none; }
        .tot-row .t-lbl {
            display: flex; align-items: center; gap: 9px;
            color: #7a8499; font-weight: 600;
        }
        .tot-row .t-lbl i { font-size: .95rem; }
        .tot-row .t-val {
            font-family: 'Syne', sans-serif;
            font-weight: 700; font-size: .95rem;
        }
        /* Grand total row */
        .tot-grand {
            display: flex; align-items: center; justify-content: space-between;
            margin-top: 10px; padding: 14px 18px;
            background: linear-gradient(135deg,rgba(232,23,93,.13),rgba(155,13,67,.06));
            border: 1px solid rgba(232,23,93,.25);
            border-radius: 10px;
        }
        .tot-grand .t-lbl { color: #e8ecf4; font-size: .95rem; font-weight: 700; gap: 9px; display:flex;align-items:center; }
        .tot-grand .t-val { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.25rem; }

        /* Cell colours */
        .cv-parts { color: #93c5fd; font-weight: 600; }
        .cv-labor { color: #34d399; font-weight: 600; }
        .cv-exp   { color: #fca5a5; font-weight: 600; }
        .cv-net   { color: #fff;    font-weight: 700; }
        .cv-neg   { color: #fca5a5; font-weight: 700; }

        /* Expense description list inside cell */
        .exp-desc-list {
            text-align: left; font-size: .72rem;
            color: #fca5a5; line-height: 1.5;
        }
        .exp-desc-list div {
            white-space: nowrap; overflow: hidden;
            text-overflow: ellipsis; max-width: 190px;
        }

        /* Day label */
        .d-num  { font-weight: 700; color: #e8ecf4; }
        .d-name { font-size: .7rem; color: #7a8499; }

        .state-box {
            text-align: center; padding: 70px 20px; color: #7a8499;
        }
        .state-box i { font-size: 3rem; display: block; margin-bottom: 14px; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<main class="app-main">

    <!-- Header -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 style="margin:0;"><i class="bi bi-calendar3 me-2"></i>Monthly Sales Overview</h4>
                <p style="margin:0;">Daily records · Weekly totals · Monthly summary</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <select class="rpt-select" id="selMonth">
                    <?php
                    $mNames = ['January','February','March','April','May','June',
                               'July','August','September','October','November','December'];
                    for ($i = 1; $i <= 12; $i++) {
                        $sel = ($i == (int)date('n')) ? 'selected' : '';
                        echo "<option value=\"$i\" $sel>{$mNames[$i-1]}</option>";
                    }
                    ?>
                </select>
                <select class="rpt-select" id="selYear">
                    <?php
                    $cy = (int)date('Y');
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

    <!-- States -->
    <div id="stateLoading" class="state-box" style="display:none;">
        <div class="spinner-border" style="color:#e8175d;width:2.5rem;height:2.5rem;margin-bottom:14px;"></div>
        <p>Loading report…</p>
    </div>
    <div id="stateEmpty" class="state-box" style="display:none;">
        <i class="bi bi-calendar-x"></i>
        No data found for this period.
    </div>

    <!-- Report Output -->
    <div id="reportOut" style="display:none;">
        <div class="ledger-card">

            <!-- Title bar -->
            <div class="ledger-heading" id="ledgerHeading">Monthly Sales Overview</div>

            <!-- Monthly totals (TOP) -->
            <div class="monthly-totals" id="monthlyTotals" style="display:none;">
                <div class="tot-row">
                    <span class="t-lbl"><i class="bi bi-box-seam" style="color:#93c5fd;"></i>Total Revenue from Products / Goods</span>
                    <span class="t-val cv-parts" id="mParts">₱0.00</span>
                </div>
                <div class="tot-row">
                    <span class="t-lbl"><i class="bi bi-wrench-adjustable" style="color:#34d399;"></i>Total Revenue from Labor</span>
                    <span class="t-val cv-labor" id="mLabor">₱0.00</span>
                </div>
                <div class="tot-row">
                    <span class="t-lbl"><i class="bi bi-wallet2" style="color:#fca5a5;"></i>Total Expenses</span>
                    <span class="t-val cv-exp" id="mExp">₱0.00</span>
                </div>
                <div class="tot-grand">
                    <span class="t-lbl"><i class="bi bi-graph-up-arrow" style="color:#e8175d;font-size:1.1rem;"></i>Total Monthly Revenue</span>
                    <span class="t-val" id="mNet">₱0.00</span>
                </div>
            </div>

            <!-- Ledger table -->
            <div class="table-responsive">
                <table class="ledger-tbl">
                    <thead>
                        <tr>
                            <th style="min-width:90px;">Date</th>
                            <th style="min-width:180px;text-align:left;">List of Expenses</th>
                            <th style="min-width:110px;">Total of<br>Expenses</th>
                            <th style="min-width:120px;">Total Revenue<br>for Products</th>
                            <th style="min-width:120px;">Total Revenue<br>for Labor</th>
                            <th style="min-width:120px;">Daily Total<br>Sales</th>
                        </tr>
                    </thead>
                    <tbody id="ledgerBody"></tbody>
                </table>
            </div>

        </div><!-- ledger-card -->
    </div><!-- reportOut -->

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
'use strict';
const IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;
let _data = null;

function peso(n) {
    return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits:2, maximumFractionDigits:2 });
}

/* ── Load report from API ── */
async function loadReport() {
    const month = document.getElementById('selMonth').value;
    const year  = document.getElementById('selYear').value;

    document.getElementById('stateLoading').style.display = '';
    document.getElementById('stateEmpty').style.display   = 'none';
    document.getElementById('reportOut').style.display    = 'none';
    if (IS_OWNER) document.getElementById('exportBtn').style.display = 'none';

    try {
        const resp = await fetch(`backend/monthly-report.php?month=${month}&year=${year}`);
        const data = await resp.json();
        document.getElementById('stateLoading').style.display = 'none';

        if (!data.success) {
            Swal.fire({ icon:'error', title:'Error', text:data.message });
            return;
        }

        _data = data;
        renderLedger(data);
        document.getElementById('reportOut').style.display = '';
        if (IS_OWNER) document.getElementById('exportBtn').style.display = '';

    } catch(err) {
        document.getElementById('stateLoading').style.display = 'none';
        Swal.fire({ icon:'error', title:'Network Error', text:err.message });
    }
}

/* ── Render ledger table ── */
function renderLedger(data) {
    document.getElementById('ledgerHeading').textContent = `Monthly Sales Overview — ${data.month_name}`;

    const tbody = document.getElementById('ledgerBody');
    tbody.innerHTML = '';

    data.weeks.forEach((week, wi) => {

        /* Day rows */
        week.days.forEach(day => {
            const tr      = document.createElement('tr');
            const hasData = day.has_data || day.expenses > 0;
            if (!hasData) tr.className = 'row-dim';

            const d       = new Date(day.date + 'T00:00:00');
            const dayNum  = d.getDate();
            const dayName = d.toLocaleDateString('en-PH', { weekday:'short' });

            /* Expense descriptions list */
            let expCell = '<span style="color:#4a5568;">—</span>';
            if (day.expense_items && day.expense_items.length > 0) {
                expCell = `<div class="exp-desc-list">`
                    + day.expense_items.map(e =>
                        `<div title="${esc(e.description)}">· ${esc(e.description)}</div>`
                      ).join('')
                    + `</div>`;
            }

            const isNeg  = parseFloat(day.net) < 0;
            const netCls = hasData ? (isNeg ? 'cv-neg' : 'cv-net') : '';

            tr.innerHTML = `
                <td>
                    <span class="d-num">${dayNum}</span>
                    <span class="d-name"> ${dayName}</span>
                </td>
                <td style="text-align:left;">${hasData ? expCell : '<span style="color:#4a5568;">—</span>'}</td>
                <td class="cv-exp">${hasData ? peso(day.expenses) : '—'}</td>
                <td class="cv-parts">${day.has_data ? peso(day.parts) : '—'}</td>
                <td class="cv-labor">${day.has_data ? peso(day.labor) : '—'}</td>
                <td class="${netCls}">${hasData ? peso(day.net) : '—'}</td>
            `;
            tbody.appendChild(tr);
        });

        /* Week total row */
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

    /* Monthly summary */
    const t = data.totals;
    document.getElementById('mParts').textContent = peso(t.parts);
    document.getElementById('mLabor').textContent = peso(t.labor);
    document.getElementById('mExp').textContent   = peso(t.expenses);

    const netEl = document.getElementById('mNet');
    netEl.textContent = peso(t.net);
    netEl.style.color = parseFloat(t.net) >= 0 ? '#34d399' : '#fca5a5';

    document.getElementById('monthlyTotals').style.display = '';
}

function esc(s) {
    return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── CSV Export ── */
function exportCSV() {
    if (!_data) return;

    const rows = [
        [`Monthly Sales Overview — ${_data.month_name}`],
        [],
        ['Date','List of Expenses','Total Expenses (₱)','Revenue - Products (₱)','Revenue - Labor (₱)','Daily Total Sales (₱)'],
    ];

    _data.weeks.forEach((week, wi) => {
        week.days.forEach(d => {
            const expList = (d.expense_items || []).map(e => e.description).join('; ') || '—';
            const hasData = d.has_data || d.expenses > 0;
            rows.push([
                d.date,
                hasData ? expList : '—',
                hasData ? d.expenses.toFixed(2) : '',
                d.has_data ? d.parts.toFixed(2) : '',
                d.has_data ? d.labor.toFixed(2) : '',
                hasData ? d.net.toFixed(2) : '',
            ]);
        });
        rows.push([
            `Total for Week ${wi+1}`, '',
            week.expenses.toFixed(2),
            week.parts.toFixed(2),
            week.labor.toFixed(2),
            week.net.toFixed(2),
        ]);
        rows.push([]);
    });

    const t = _data.totals;
    rows.push([]);
    rows.push(['MONTHLY SUMMARY']);
    rows.push(['Total Revenue from Products/Goods','','',t.parts.toFixed(2),'','']);
    rows.push(['Total Revenue from Labor','','','',t.labor.toFixed(2),'']);
    rows.push(['Total Expenses','',t.expenses.toFixed(2),'','','']);
    rows.push(['Total Monthly Revenue','','','','',t.net.toFixed(2)]);

    const csv = rows.map(r =>
        r.map(v => `"${String(v ?? '').replace(/"/g,'""')}"`).join(',')
    ).join('\n');

    const a    = document.createElement('a');
    a.href     = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
    a.download = `monthly_report_${_data.year}_${String(_data.month).padStart(2,'0')}.csv`;
    a.click();
}

/* Auto-load on open */
loadReport();
</script>
</body>
</html>