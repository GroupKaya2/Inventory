<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$activePage = 'monthly_report';
$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
$initialMonth = (isset($_GET['month']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['month']))
    ? $_GET['month'] : date('Y-m');
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Sales Report — DSpeedway</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        /* ── Toolbar ── */
        .mr-toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .mr-month { display: flex; align-items: center; gap: 6px; }
        .mr-select {
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .1);
            color: #e2e8f0;
            border-radius: 8px;
            height: 36px;
            padding: 0 10px;
            font-size: .82rem;
            font-family: 'Inter', sans-serif;
        }
        .mr-select option { background: #1c2336; color: #e2e8f0; }
        .mr-select:focus { outline: none; border-color: rgba(74, 222, 128, .4); }
        .mr-nav { height: 36px; padding: 0 11px; }

        .mr-status {
            display: none;
            align-items: center;
            gap: 6px;
            font-size: .74rem;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 20px;
            white-space: nowrap;
        }
        .mr-status.pending { display: inline-flex; background: rgba(251, 191, 36, .1); color: #fbbf24; }
        .mr-status.saving  { display: inline-flex; background: rgba(96, 165, 250, .1); color: #60a5fa; }
        .mr-status.saved   { display: inline-flex; background: rgba(74, 222, 128, .1); color: #4ade80; }
        .mr-status.error   { display: inline-flex; background: rgba(248, 113, 113, .12); color: #fca5a5; cursor: pointer; }
        .mr-status .bi-circle-fill { font-size: .5rem; }

        .mr-legend {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            font-size: .76rem;
            color: #64748b;
            margin-bottom: 12px;
        }
        .mr-legend .dot {
            display: inline-block;
            width: 10px; height: 10px;
            border-radius: 3px;
            margin-right: 6px;
            vertical-align: -1px;
        }

        /* ── Daily table ── */
        .mr-scroll {
            overflow: auto;
            max-height: calc(100vh - 270px);
            min-height: 380px;
            border-radius: var(--radius);
        }
        .mr-table { min-width: 1020px; }
        .mr-table thead th {
            position: sticky;
            top: 0;
            z-index: 3;
            white-space: nowrap;
            vertical-align: bottom;
            padding-top: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(74, 222, 128, .18);
        }
        .mr-table thead th.r, .mr-table td.num { text-align: right; }
        .mr-table thead th small {
            display: block;
            font-weight: 400;
            text-transform: none;
            letter-spacing: 0;
            color: #475569;
            font-size: .62rem;
            margin-top: 1px;
        }
        .mr-table thead th.manual { color: #fbbf24; }
        .mr-table thead th.manual small { color: rgba(251, 191, 36, .55); }

        .mr-table tbody td { padding: 6px 10px; }
        .mr-table td.num {
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .mr-table td.num.zero { color: #3b4658; }
        .mr-table td.num.neg { color: #f87171; }
        .mr-table td.strong { font-weight: 700; color: #fff; }
        .mr-table td.strong.zero { color: #3b4658; }

        .mr-date { white-space: nowrap; }
        .mr-day { font-weight: 600; color: #e2e8f0; margin-right: 8px; }
        .mr-dow { font-size: .68rem; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
        tr.is-sunday .mr-dow { color: #f87171; }
        tr.is-today { background: rgba(74, 222, 128, .05); box-shadow: inset 3px 0 0 #4ade80; }

        .mr-refin {
            display: inline-block;
            max-width: 210px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
            color: #94a3b8;
            font-size: .76rem;
        }
        .mr-dash { color: #2e3a4e; }

        .mr-input {
            width: 100%;
            min-width: 100px;
            background: rgba(251, 191, 36, .04);
            border: 1px solid rgba(251, 191, 36, .16);
            color: #e2e8f0;
            border-radius: 6px;
            padding: 5px 9px;
            font-size: .8rem;
            font-family: 'Inter', sans-serif;
            text-align: right;
            font-variant-numeric: tabular-nums;
            -moz-appearance: textfield;
        }
        .mr-input::-webkit-outer-spin-button,
        .mr-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .mr-input::placeholder { color: #3b4658; }
        .mr-input:focus {
            outline: none;
            border-color: rgba(251, 191, 36, .55);
            background: rgba(251, 191, 36, .08);
            box-shadow: 0 0 0 3px rgba(251, 191, 36, .1);
        }

        .mr-table tfoot td {
            position: sticky;
            bottom: 0;
            z-index: 2;
            background: #0f1f15;
            border-top: 1px solid rgba(74, 222, 128, .25);
            border-bottom: none;
            padding: 11px 10px;
            font-weight: 700;
            font-size: .84rem;
            color: #fff;
        }
        .mr-table tfoot td.num.zero { color: #4b5a6e; }
        .mr-table tfoot td.num.neg { color: #f87171; }
        .mr-table tfoot td.lbl { color: #4ade80; letter-spacing: .5px; }

        /* ── Month-end summary ── */
        .mr-summary { max-width: 700px; padding: 20px 24px; margin-top: 22px; margin-bottom: 24px; }
        .mr-sum-title {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 8px;
        }
        .mr-sum-title i { color: #4ade80; }
        .mr-sum-title span { margin-left: auto; font-size: .78rem; font-weight: 500; color: #64748b; }
        .mr-sum-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 9px 0;
            border-bottom: 1px solid rgba(255, 255, 255, .06);
            font-size: .88rem;
            color: #cbd5e1;
        }
        .mr-sum-row small { display: block; color: #475569; font-size: .68rem; }
        .mr-sum-row .v {
            font-variant-numeric: tabular-nums;
            font-weight: 600;
            color: #e2e8f0;
            white-space: nowrap;
        }
        .mr-sum-row.key {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: .98rem;
            color: #fff;
            background: rgba(74, 222, 128, .05);
            margin: 4px -12px;
            padding: 11px 12px;
            border-radius: 8px;
            border-bottom: none;
        }
        .mr-sum-row.key .v { font-size: 1.05rem; color: #4ade80; }
        .mr-sum-row.key .v.neg { color: #f87171; }
        .mr-sum-row.head { font-weight: 700; color: #fff; margin-top: 6px; }
        .mr-sum-row.exp { padding-left: 18px; color: #94a3b8; font-size: .84rem; }
        .mr-sum-row.exp .mr-input { width: 170px; min-width: 0; }
        .mr-sum-row.final { margin-top: 10px; }
        .mr-sum-row.final .v { font-size: 1.25rem; }
        .mr-sum-note { margin-top: 12px; font-size: .72rem; color: #475569; }

        @media (max-width: 576px) {
            .mr-sum-row.exp .mr-input { width: 130px; }
            .mr-scroll { max-height: none; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="app-main">

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4><i class="bi bi-bar-chart-line-fill me-2" style="color:#60a5fa;"></i>Monthly Sales Report</h4>
            <p>Every date of the month, with month-end summary and net income. Amounts in ₱.</p>
        </div>
        <div class="mr-toolbar">
            <div class="mr-month">
                <button class="btn-ghost mr-nav" onclick="shiftMonth(-1)" title="Previous month"><i class="bi bi-chevron-left"></i></button>
                <select id="mrMonth" class="mr-select" onchange="onMonthSelect()"></select>
                <select id="mrYear" class="mr-select" onchange="onMonthSelect()"></select>
                <button class="btn-ghost mr-nav" onclick="shiftMonth(1)" title="Next month"><i class="bi bi-chevron-right"></i></button>
            </div>
            <span id="mrStatus" class="mr-status" onclick="onStatusClick()"></span>
            <button class="btn-ghost" id="btnCsv" onclick="exportCsv()"><i class="bi bi-download"></i> CSV</button>
            <button class="btn-pink" id="btnExcel" onclick="exportExcel()"><i class="bi bi-file-earmark-excel"></i> Excel</button>
        </div>
    </div>

    <div class="mr-legend">
        <span><span class="dot" style="background:rgba(251,191,36,.55);"></span>Amber columns are encoded manually and save automatically</span>
        <span><span class="dot" style="background:rgba(148,163,184,.5);"></span>Parts / Goods and Labor come from Daily Transaction</span>
    </div>

    <div class="card">
        <div class="mr-scroll">
            <table class="data-table mr-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer / Ref</th>
                        <th class="r">Parts / Goods</th>
                        <th class="r">Labor</th>
                        <th class="r">Total Sales<small>Parts + Labor</small></th>
                        <th class="r manual">Credit<small>encode manually</small></th>
                        <th class="r manual">COS Weekly<small>encode manually</small></th>
                        <th class="r">Parts Margin<small>Parts − COS Weekly</small></th>
                        <th class="r manual">Cost of Sales Labor<small>encode manually</small></th>
                        <th class="r">Percentage<small>Margin ÷ COS Weekly</small></th>
                    </tr>
                </thead>
                <tbody id="mrBody">
                    <tr><td colspan="10" style="text-align:center;padding:40px;"><div class="spinner-border" style="color:#4ade80;"></div></td></tr>
                </tbody>
                <tfoot id="mrFoot"></tfoot>
            </table>
        </div>
    </div>

    <div class="card mr-summary" id="mrSummary"></div>

</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
'use strict';

const API = 'backend/monthly-report.php';
const TODAY = <?= json_encode($today) ?>;
const INITIAL_MONTH = <?= json_encode($initialMonth) ?>;

// ==== CORE START (pure logic -- no DOM access) ================================
const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const MONTH_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function num(v) {
    const n = parseFloat(v);
    return isFinite(n) ? n : 0;
}

// Round to cents; "+ 0" turns -0 into 0 so nothing ever prints as "-0.00".
function round2(n) {
    return Math.round((n + Number.EPSILON) * 100) / 100 + 0;
}

function fmt(n) {
    return round2(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// One day's computed columns.
//   Total Sales  = Parts + Labor
//   Parts Margin = Parts / Goods - COS Weekly
//   Percentage   = Parts Margin / COS Weekly   (0 when COS Weekly is 0)
function rowCalc(d) {
    const total = round2(num(d.parts) + num(d.labor));
    const margin = round2(num(d.parts) - num(d.cos_parts));
    const pct = num(d.cos_parts) > 0 ? round2(margin / num(d.cos_parts) * 100) : 0;
    return { total: total, margin: margin, pct: pct };
}

// Totals row. The total Percentage is Total Parts Margin / Total COS Weekly
// (not an average of the daily percentages).
function totalsCalc(days) {
    const t = { parts: 0, labor: 0, credit: 0, cos_parts: 0, cos_labor: 0 };
    days.forEach(function (d) {
        t.parts += num(d.parts);
        t.labor += num(d.labor);
        t.credit += num(d.credit);
        t.cos_parts += num(d.cos_parts);
        t.cos_labor += num(d.cos_labor);
    });
    Object.keys(t).forEach(function (k) { t[k] = round2(t[k]); });
    t.total = round2(t.parts + t.labor);
    t.margin = round2(t.parts - t.cos_parts);
    t.pct = t.cos_parts > 0 ? round2(t.margin / t.cos_parts * 100) : 0;
    return t;
}

// Month-end summary.
//   Net Sales  = Total Gross Sales - Cost of Sales Parts - Cost of Sales Labor
//   Net Income = Net Sales - all Less Expenses lines
function summaryCalc(t, expenses, lines) {
    const gross = t.total;
    const cosParts = t.cos_parts;
    const cosLabor = t.cos_labor;
    const netSales = round2(gross - cosParts - cosLabor);
    let exp = 0;
    lines.forEach(function (l) { exp += num(expenses[l.key]); });
    exp = round2(exp);
    return { gross: gross, cosParts: cosParts, cosLabor: cosLabor, netSales: netSales, totalExpenses: exp, netIncome: round2(netSales - exp) };
}

function csvCell(v) {
    const s = (v === null || v === undefined) ? '' : String(v);
    return /[",\r\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
}
function money(n) { return round2(n).toFixed(2); }
function pctText(p) { return round2(p).toFixed(2) + '%'; }

function dateLabel(d) {
    const p = d.date.split('-');
    return MONTH_SHORT[parseInt(p[1], 10) - 1] + ' ' + p[2] + ', ' + p[0] + ' (' + d.day_name + ')';
}

// Screen: ref numbers only (names in the hover). Export: ref + customer name.
function refLabel(sales) {
    return (sales || []).map(function (s) { return s.ref ? s.ref : (s.customer || ''); }).filter(Boolean).join(', ');
}
function refTitle(sales) {
    return (sales || []).map(function (s) { return (s.ref ? s.ref + ' – ' : '') + (s.customer || 'No name'); }).join('\n');
}
function refExport(sales) {
    return (sales || []).map(function (s) { return ((s.ref ? s.ref + ' ' : '') + (s.customer || '')).trim(); }).filter(Boolean).join('; ');
}

// Full table + month-end summary as CSV (UTF-8 with BOM so Excel opens it cleanly).
function buildCsv(r) {
    const rows = [];
    rows.push(['MONTHLY SALES REPORT']);
    rows.push([r.monthLabel]);
    rows.push([]);
    rows.push(['Date', 'Customer / Ref', 'Parts / Goods', 'Labor', 'Total Sales', 'Credit', 'COS Weekly', 'Parts Margin', 'Cost of Sales Labor', 'Percentage']);
    r.days.forEach(function (d) {
        const c = rowCalc(d);
        rows.push([dateLabel(d), refExport(d.sales), money(d.parts), money(d.labor), money(c.total), money(d.credit), money(d.cos_parts), money(c.margin), money(d.cos_labor), pctText(c.pct)]);
    });
    const t = totalsCalc(r.days);
    rows.push(['TOTAL', '', money(t.parts), money(t.labor), money(t.total), money(t.credit), money(t.cos_parts), money(t.margin), money(t.cos_labor), pctText(t.pct)]);
    rows.push([]);
    const s = summaryCalc(t, r.expenses, r.expenseLines);
    rows.push(['MONTH-END SUMMARY']);
    rows.push(['Total Gross Sales For The Month', money(s.gross)]);
    rows.push(['Cost Of Sales Parts', money(s.cosParts)]);
    rows.push(['Cost Of Sales Labor', money(s.cosLabor)]);
    rows.push(['NET SALES', money(s.netSales)]);
    rows.push(['LESS EXPENSES', money(s.totalExpenses)]);
    r.expenseLines.forEach(function (l) { rows.push(['   ' + l.label, money(r.expenses[l.key])]); });
    rows.push(['NET INCOME', money(s.netIncome)]);
    return '\ufeff' + rows.map(function (row) { return row.map(csvCell).join(','); }).join('\r\n');
}

// Styled .xlsx (ExcelJS) with real formulas, so edits in Excel still recalculate.
// Every formula cell also carries its computed result for viewers that don't recalc.
function buildWorkbook(ExcelJS, r) {
    const wb = new ExcelJS.Workbook();
    wb.creator = 'D Speedway';
    wb.created = new Date();
    const ws = wb.addWorksheet('Monthly Report', {
        views: [{ state: 'frozen', ySplit: 4 }],
        pageSetup: { orientation: 'landscape', fitToPage: true, fitToWidth: 1, fitToHeight: 0 }
    });

    const ORANGE = 'FFF4B183', AMBER = 'FFFFF2CC', GRAY = 'FFE7E6E6', GREEN = 'FFE2EFDA';
    const MONEY = '#,##0.00;[Red]-#,##0.00';
    const thin = { style: 'thin', color: { argb: 'FF808080' } };
    const box = { top: thin, left: thin, bottom: thin, right: thin };
    const fill = function (argb) { return { type: 'pattern', pattern: 'solid', fgColor: { argb: argb } }; };

    ws.columns = [{ width: 22 }, { width: 30 }, { width: 15 }, { width: 15 }, { width: 15 }, { width: 15 }, { width: 15 }, { width: 15 }, { width: 20 }, { width: 13 }];

    function put(rn, cn, value, numFmt, opts) {
        const cell = ws.getCell(rn, cn);
        opts = opts || {};
        if (!opts.noValue) cell.value = value;   // never write into a merged slave: it would overwrite the master
        if (numFmt) cell.numFmt = numFmt;
        cell.border = box;
        if (opts.fill) cell.fill = fill(opts.fill);
        if (opts.bold || opts.size) cell.font = { bold: !!opts.bold, size: opts.size || 11 };
        if (opts.align) cell.alignment = { horizontal: opts.align, vertical: 'middle', indent: opts.indent || 0 };
        return cell;
    }

    // Title block
    ws.mergeCells('A1:J1');
    const title = ws.getCell('A1');
    title.value = 'MONTHLY SALES REPORT';
    title.font = { bold: true, size: 16 };
    title.alignment = { horizontal: 'center', vertical: 'middle' };
    title.fill = fill(ORANGE);
    ws.getRow(1).height = 30;
    ws.mergeCells('A2:J2');
    const sub = ws.getCell('A2');
    sub.value = 'For the month of ' + r.monthLabel;
    sub.font = { italic: true, size: 11 };
    sub.alignment = { horizontal: 'center' };
    ws.getRow(3).height = 6;

    // Column headers
    const headers = ['Date', 'Customer / Ref', 'Parts / Goods', 'Labor', 'Total Sales', 'Credit', 'COS Weekly', 'Parts Margin', 'Cost of Sales Labor', 'Percentage'];
    headers.forEach(function (h, i) {
        put(4, i + 1, h, null, { fill: ORANGE, bold: true, align: 'center' });
    });
    ws.getRow(4).height = 22;

    // One row per date
    const first = 5;
    const n = r.days.length;
    r.days.forEach(function (d, i) {
        const rn = first + i;
        const c = rowCalc(d);
        const p = d.date.split('-').map(Number);
        // Real Excel date, written as a serial number (days since 1899-12-30) so it never depends on
        // Date-object handling or the viewer's time zone.
        put(rn, 1, Date.UTC(p[0], p[1] - 1, p[2]) / 86400000 + 25569, 'mmm dd, yyyy (ddd)', { align: 'left' });
        put(rn, 2, refExport(d.sales));
        put(rn, 3, round2(d.parts), MONEY);
        put(rn, 4, round2(d.labor), MONEY);
        put(rn, 5, { formula: 'C' + rn + '+D' + rn, result: c.total }, MONEY);
        put(rn, 6, round2(d.credit), MONEY, { fill: AMBER });
        put(rn, 7, round2(d.cos_parts), MONEY, { fill: AMBER });
        put(rn, 8, { formula: 'C' + rn + '-G' + rn, result: c.margin }, MONEY);
        put(rn, 9, round2(d.cos_labor), MONEY, { fill: AMBER });
        const cosP = round2(num(d.cos_parts));
        put(rn, 10, { formula: 'IF(G' + rn + '=0,0,H' + rn + '/G' + rn + ')', result: cosP > 0 ? c.margin / cosP : 0 }, '0.00%');
    });

    // Totals row
    const last = first + n - 1;
    const tr = last + 1;
    const t = totalsCalc(r.days);
    ws.mergeCells('A' + tr + ':B' + tr);
    put(tr, 1, 'TOTAL', null, { fill: GRAY, bold: true, align: 'left' });
    put(tr, 2, null, null, { fill: GRAY, noValue: true });
    const sumCol = function (col, cn, result) {
        put(tr, cn, { formula: 'SUM(' + col + first + ':' + col + last + ')', result: result }, MONEY, { fill: GRAY, bold: true });
    };
    sumCol('C', 3, t.parts);
    sumCol('D', 4, t.labor);
    sumCol('E', 5, t.total);
    sumCol('F', 6, t.credit);
    sumCol('G', 7, t.cos_parts);
    sumCol('H', 8, t.margin);
    sumCol('I', 9, t.cos_labor);
    put(tr, 10, { formula: 'IF(G' + tr + '=0,0,H' + tr + '/G' + tr + ')', result: t.cos_parts > 0 ? t.margin / t.cos_parts : 0 }, '0.00%', { fill: GRAY, bold: true });

    // Month-end summary
    const s = summaryCalc(t, r.expenses, r.expenseLines);
    let row = tr + 2;
    const label = function (rn, text, opts) {
        ws.mergeCells('A' + rn + ':B' + rn);
        put(rn, 1, text, null, opts);
        put(rn, 2, null, null, Object.assign({}, opts, { noValue: true }));
    };

    ws.mergeCells('A' + row + ':C' + row);
    put(row, 1, 'MONTH-END SUMMARY', null, { fill: ORANGE, bold: true, size: 12, align: 'left' });
    put(row, 2, null, null, { fill: ORANGE, noValue: true });
    put(row, 3, null, null, { fill: ORANGE, noValue: true });

    const rGross = ++row;
    label(rGross, 'Total Gross Sales For The Month');
    put(rGross, 3, { formula: 'E' + tr, result: s.gross }, MONEY);

    const rCosP = ++row;
    label(rCosP, 'Cost Of Sales Parts');
    put(rCosP, 3, { formula: 'G' + tr, result: s.cosParts }, MONEY);

    const rCosL = ++row;
    label(rCosL, 'Cost Of Sales Labor');
    put(rCosL, 3, { formula: 'I' + tr, result: s.cosLabor }, MONEY);

    const rNet = ++row;
    label(rNet, 'NET SALES', { fill: GREEN, bold: true });
    put(rNet, 3, { formula: 'C' + rGross + '-C' + rCosP + '-C' + rCosL, result: s.netSales }, MONEY, { fill: GREEN, bold: true });

    const rLess = ++row;
    const e1 = rLess + 1;
    const eN = rLess + r.expenseLines.length;
    label(rLess, 'LESS EXPENSES', { bold: true });
    put(rLess, 3, { formula: 'SUM(C' + e1 + ':C' + eN + ')', result: s.totalExpenses }, MONEY, { bold: true });

    r.expenseLines.forEach(function (l) {
        const rn = ++row;
        label(rn, l.label, { align: 'left', indent: 1 });
        put(rn, 3, round2(num(r.expenses[l.key])), MONEY, { fill: AMBER });
    });

    const rIncome = ++row;
    label(rIncome, 'NET INCOME', { fill: GREEN, bold: true, size: 12 });
    put(rIncome, 3, { formula: 'C' + rNet + '-C' + rLess, result: s.netIncome }, MONEY, { fill: GREEN, bold: true, size: 12 });

    return wb;
}
// ==== CORE END ===============================================================

// ── State ────────────────────────────────────────────────────────────────────
let REPORT = { month: INITIAL_MONTH, monthLabel: '', days: [], expenseLines: [], expenses: {} };
let dirtyDays = {};   // date  -> { date, credit, cos_parts, cos_labor }
let dirtyExp = {};    // key   -> amount
let saveTimer = null;
let currentSave = null;
let loaded = false;

function esc(s) {
    return String(s === null || s === undefined ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function pad2(n) { return String(n).padStart(2, '0'); }

// ── Month controls ───────────────────────────────────────────────────────────
function syncMonthControls() {
    const parts = REPORT.month.split('-');
    const y = parseInt(parts[0], 10), m = parseInt(parts[1], 10);

    const mSel = document.getElementById('mrMonth');
    if (!mSel.options.length) {
        mSel.innerHTML = MONTH_NAMES.map(function (n, i) { return '<option value="' + (i + 1) + '">' + n + '</option>'; }).join('');
    }
    mSel.value = String(m);

    const ySel = document.getElementById('mrYear');
    const cur = new Date().getFullYear();
    const from = Math.min(cur - 6, y), to = Math.max(cur + 1, y);
    let opts = '';
    for (let yy = to; yy >= from; yy--) opts += '<option value="' + yy + '">' + yy + '</option>';
    ySel.innerHTML = opts;
    ySel.value = String(y);
}

function shiftMonth(delta) {
    const parts = REPORT.month.split('-').map(Number);
    let y = parts[0], m = parts[1] + delta;
    if (m < 1) { m = 12; y--; }
    if (m > 12) { m = 1; y++; }
    loadMonth(y + '-' + pad2(m));
}

function onMonthSelect() {
    loadMonth(document.getElementById('mrYear').value + '-' + pad2(document.getElementById('mrMonth').value));
}

// ── Load ─────────────────────────────────────────────────────────────────────
async function loadMonth(month) {
    if (loaded && !(await ensureSaved())) { syncMonthControls(); return; }

    document.getElementById('mrBody').innerHTML =
        '<tr><td colspan="10" style="text-align:center;padding:40px;"><div class="spinner-border" style="color:#4ade80;"></div></td></tr>';
    document.getElementById('mrFoot').innerHTML = '';

    try {
        const res = await fetch(API + '?action=fetch&month=' + encodeURIComponent(month));
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Could not load the report.');

        REPORT = { month: json.month, monthLabel: json.month_label, days: json.days, expenseLines: json.expense_lines, expenses: json.expenses };
        dirtyDays = {};
        dirtyExp = {};
        loaded = true;
        syncMonthControls();
        history.replaceState(null, '', '?month=' + REPORT.month);
        renderAll();
        setStatus('idle');
    } catch (e) {
        document.getElementById('mrBody').innerHTML =
            '<tr><td colspan="10" style="text-align:center;padding:32px;color:#fca5a5;">⚠ ' + esc(e.message) + '</td></tr>';
    }
}

// ── Render ───────────────────────────────────────────────────────────────────
function renderAll() {
    renderTable();
    renderFoot();
    renderSummary();
}

function renderTable() {
    document.getElementById('mrBody').innerHTML = REPORT.days.map(function (d, i) {
        const c = rowCalc(d);
        const cls = (d.dow === 7 ? 'is-sunday ' : '') + (d.date === TODAY ? 'is-today' : '');
        const refs = refLabel(d.sales);
        const mp = MONTH_SHORT[parseInt(d.date.split('-')[1], 10) - 1];
        const input = function (field, val) {
            return '<td><input class="mr-input" type="number" min="0" step="0.01" placeholder="0.00" value="' + (val ? val : '') +
                '" data-i="' + i + '" data-col="' + field + '" oninput="onDayInput(' + i + ",'" + field + "',this)\" onkeydown=\"onKeyNav(event)\" onfocus=\"this.select()\"></td>";
        };
        return '<tr class="' + cls + '" data-i="' + i + '">' +
            '<td class="mr-date"><span class="mr-day">' + mp + ' ' + pad2(d.day) + '</span><span class="mr-dow">' + esc(d.day_name) + '</span></td>' +
            '<td>' + (refs ? '<span class="mr-refin" title="' + esc(refTitle(d.sales)) + '">' + esc(refs) + '</span>' : '<span class="mr-dash">—</span>') + '</td>' +
            '<td class="num' + (d.parts ? '' : ' zero') + '">' + fmt(d.parts) + '</td>' +
            '<td class="num' + (d.labor ? '' : ' zero') + '">' + fmt(d.labor) + '</td>' +
            '<td class="num strong' + (c.total ? '' : ' zero') + '" data-c="total">' + fmt(c.total) + '</td>' +
            input('credit', d.credit) +
            input('cos_parts', d.cos_parts) +
            '<td class="num' + (c.margin < 0 ? ' neg' : (c.margin === 0 ? ' zero' : '')) + '" data-c="margin">' + fmt(c.margin) + '</td>' +
            input('cos_labor', d.cos_labor) +
            '<td class="num' + (c.pct < 0 ? ' neg' : (c.pct === 0 ? ' zero' : '')) + '" data-c="pct">' + c.pct.toFixed(2) + '%</td>' +
            '</tr>';
    }).join('');
}

function updateRow(i) {
    const tr = document.querySelector('#mrBody tr[data-i="' + i + '"]');
    if (!tr) return;
    const c = rowCalc(REPORT.days[i]);
    const set = function (key, text, value) {
        const el = tr.querySelector('[data-c="' + key + '"]');
        if (!el) return;
        el.textContent = text;
        el.classList.toggle('zero', value === 0);
        el.classList.toggle('neg', value < 0);
    };
    set('total', fmt(c.total), c.total);
    set('margin', fmt(c.margin), c.margin);
    set('pct', c.pct.toFixed(2) + '%', c.pct);
}

function renderFoot() {
    const t = totalsCalc(REPORT.days);
    const cell = function (v, isPct) {
        const text = isPct ? t.pct.toFixed(2) + '%' : fmt(v);
        return '<td class="num' + (v < 0 ? ' neg' : (v === 0 ? ' zero' : '')) + '">' + text + '</td>';
    };
    document.getElementById('mrFoot').innerHTML =
        '<tr><td class="lbl" colspan="2">TOTAL</td>' +
        cell(t.parts) + cell(t.labor) + cell(t.total) + cell(t.credit) + cell(t.cos_parts) + cell(t.margin) + cell(t.cos_labor) + cell(t.pct, true) + '</tr>';
}

function renderSummary() {
    const lines = REPORT.expenseLines.map(function (l) {
        const v = REPORT.expenses[l.key];
        return '<div class="mr-sum-row exp"><span>' + esc(l.label) + '</span>' +
            '<input class="mr-input" type="number" min="0" step="0.01" placeholder="0.00" value="' + (v ? v : '') +
            '" oninput="onExpInput(\'' + l.key + '\',this)" onfocus="this.select()"></div>';
    }).join('');

    document.getElementById('mrSummary').innerHTML =
        '<div class="mr-sum-title"><i class="bi bi-journal-text"></i> Month-End Summary<span>' + esc(REPORT.monthLabel) + '</span></div>' +
        '<div class="mr-sum-row"><span>Total Gross Sales For The Month</span><span class="v" id="sumGross"></span></div>' +
        '<div class="mr-sum-row"><span>Cost Of Sales Parts</span><span class="v" id="sumCosP"></span></div>' +
        '<div class="mr-sum-row"><span>Cost Of Sales Labor</span><span class="v" id="sumCosL"></span></div>' +
        '<div class="mr-sum-row key"><span>NET SALES<small>Gross Sales − Cost of Sales Parts − Cost of Sales Labor</small></span><span class="v" id="sumNet"></span></div>' +
        '<div class="mr-sum-row head"><span>Less Expenses</span><span class="v" id="sumExp"></span></div>' +
        lines +
        '<div class="mr-sum-row key final"><span>NET INCOME<small>Net Sales − Less Expenses</small></span><span class="v" id="sumIncome"></span></div>' +
        '<div class="mr-sum-note">Expense lines are encoded manually and save automatically. Net Income still computes when some lines are empty.</div>';
    updateSummaryValues();
}

function updateSummaryValues() {
    const s = summaryCalc(totalsCalc(REPORT.days), REPORT.expenses, REPORT.expenseLines);
    const peso = function (n) { return '₱' + fmt(n); };
    const put = function (id, n, signed) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = peso(n);
        if (signed) el.classList.toggle('neg', n < 0);
    };
    put('sumGross', s.gross);
    put('sumCosP', s.cosParts);
    put('sumCosL', s.cosLabor);
    put('sumNet', s.netSales, true);
    put('sumExp', s.totalExpenses);
    put('sumIncome', s.netIncome, true);
}

// ── Editing ──────────────────────────────────────────────────────────────────
function onDayInput(i, field, el) {
    const d = REPORT.days[i];
    d[field] = Math.max(0, num(el.value));
    dirtyDays[d.date] = { date: d.date, credit: d.credit, cos_parts: d.cos_parts, cos_labor: d.cos_labor };
    updateRow(i);
    renderFoot();
    updateSummaryValues();
    scheduleSave();
}

function onExpInput(key, el) {
    REPORT.expenses[key] = Math.max(0, num(el.value));
    dirtyExp[key] = REPORT.expenses[key];
    updateSummaryValues();
    scheduleSave();
}

// Enter moves down the same column (Shift+Enter moves up), like a spreadsheet.
function onKeyNav(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const col = e.target.dataset.col;
    const i = parseInt(e.target.dataset.i, 10) + (e.shiftKey ? -1 : 1);
    const next = document.querySelector('.mr-input[data-col="' + col + '"][data-i="' + i + '"]');
    if (next) next.focus();
}

// ── Autosave ─────────────────────────────────────────────────────────────────
function hasDirty() {
    return Object.keys(dirtyDays).length > 0 || Object.keys(dirtyExp).length > 0;
}

function buildPayload() {
    return { month: REPORT.month, days: Object.keys(dirtyDays).map(function (k) { return dirtyDays[k]; }), expenses: Object.assign({}, dirtyExp) };
}

function setStatus(state, msg) {
    const el = document.getElementById('mrStatus');
    const html = {
        pending: '<i class="bi bi-circle-fill"></i> Unsaved changes…',
        saving: '<span class="spinner-border spinner-border-sm"></span> Saving…',
        saved: '<i class="bi bi-check-circle-fill"></i> All changes saved',
        error: '<i class="bi bi-exclamation-triangle-fill"></i> Not saved — click to retry'
    };
    el.className = 'mr-status' + (html[state] ? ' ' + state : '');
    el.innerHTML = html[state] || '';
    el.title = state === 'error' && msg ? msg : '';
}

function onStatusClick() {
    if (hasDirty()) flushSave();
}

function scheduleSave() {
    clearTimeout(saveTimer);
    setStatus('pending');
    saveTimer = setTimeout(flushSave, 900);
}

function flushSave() {
    clearTimeout(saveTimer);
    if (currentSave) return currentSave;
    if (!hasDirty()) return Promise.resolve();
    currentSave = doSave().then(function () { currentSave = null; }, function () { currentSave = null; });
    return currentSave;
}

async function doSave() {
    const payload = buildPayload();
    const sent = { days: dirtyDays, exp: dirtyExp };
    dirtyDays = {};
    dirtyExp = {};
    setStatus('saving');
    let ok = false;
    try {
        const res = await fetch(API + '?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Save failed.');
        ok = true;
    } catch (e) {
        // Put the unsaved entries back (without overriding anything typed since).
        Object.keys(sent.days).forEach(function (k) { if (!dirtyDays[k]) dirtyDays[k] = sent.days[k]; });
        Object.keys(sent.exp).forEach(function (k) { if (!(k in dirtyExp)) dirtyExp[k] = sent.exp[k]; });
        setStatus('error', e.message);
    }
    if (ok) {
        if (hasDirty()) scheduleSave(); else setStatus('saved');
    }
}

async function ensureSaved() {
    for (let tries = 0; tries < 3 && (hasDirty() || currentSave); tries++) {
        await flushSave();
    }
    if (!hasDirty()) return true;
    const r = await Swal.fire({
        icon: 'warning',
        title: 'Some changes are not saved',
        text: 'Leave this month anyway? Unsaved entries will be lost.',
        showCancelButton: true,
        confirmButtonText: 'Leave anyway',
        confirmButtonColor: '#dc2626'
    });
    return r.isConfirmed;
}

window.addEventListener('beforeunload', function (e) {
    if (hasDirty() || currentSave) { e.preventDefault(); e.returnValue = ''; }
});
// Last-chance save if the tab is closed or navigated away mid-edit.
window.addEventListener('pagehide', function () {
    if (hasDirty() && navigator.sendBeacon) {
        navigator.sendBeacon(API + '?action=save', new Blob([JSON.stringify(buildPayload())], { type: 'application/json' }));
    }
});

// ── Export ───────────────────────────────────────────────────────────────────
function reportForExport() {
    return { monthLabel: REPORT.monthLabel, days: REPORT.days, expenseLines: REPORT.expenseLines, expenses: REPORT.expenses };
}

function downloadBlob(blob, filename) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 800);
}

function exportCsv() {
    if (!loaded) return;
    downloadBlob(new Blob([buildCsv(reportForExport())], { type: 'text/csv;charset=utf-8' }), 'Monthly_Sales_Report_' + REPORT.month + '.csv');
}

function loadScript(src) {
    return new Promise(function (resolve, reject) {
        if (window.ExcelJS) return resolve();
        const s = document.createElement('script');
        s.src = src;
        s.onload = resolve;
        s.onerror = function () { reject(new Error('Could not load the Excel library. Check your internet connection, or use CSV.')); };
        document.head.appendChild(s);
    });
}

async function exportExcel() {
    if (!loaded) return;
    const btn = document.getElementById('btnExcel');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Preparing…';
    try {
        await loadScript('https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js');
        const wb = buildWorkbook(window.ExcelJS, reportForExport());
        const buf = await wb.xlsx.writeBuffer();
        downloadBlob(
            new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }),
            'Monthly_Sales_Report_' + REPORT.month + '.xlsx'
        );
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Excel export failed', text: e.message });
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

loadMonth(INITIAL_MONTH);
</script>

</body>
</html>