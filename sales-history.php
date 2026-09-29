<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$activePage = 'sales_history';
$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
$today = date('Y-m-d');

// Stats
$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(parts_total + labor_total), 0) AS all_time,
        COALESCE(SUM(CASE WHEN sale_date = '$today' THEN parts_total + labor_total ELSE 0 END), 0) AS today_total,
        COALESCE(SUM(CASE WHEN sale_date >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN parts_total + labor_total ELSE 0 END), 0) AS month_total
    FROM sales
")->fetch_assoc();

// Total expenses all-time and today
$expStats = $conn->query("
    SELECT
        COALESCE(SUM(amount), 0) AS all_time_exp,
        COALESCE(SUM(CASE WHEN expense_date = '$today' THEN amount ELSE 0 END), 0) AS today_exp,
        COALESCE(SUM(CASE WHEN expense_date >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN amount ELSE 0 END), 0) AS month_exp
    FROM expenses
")->fetch_assoc();

// Expenses grouped by date for modal use
$expByDate = [];
$re = $conn->query("
    SELECT expense_date,
           GROUP_CONCAT(description ORDER BY id SEPARATOR ' | ') AS descriptions,
           SUM(amount) AS total_exp
    FROM expenses
    GROUP BY expense_date
");
if ($re)
    while ($row = $re->fetch_assoc()) {
        $expByDate[$row['expense_date']] = [
            'total' => (float) $row['total_exp'],
            'descriptions' => $row['descriptions'],
        ];
    }

// All individual expense rows for the separate expenses table
$expenseRows = [];
$re2 = $conn->query("
    SELECT id, expense_date, category, description, amount
    FROM expenses
    ORDER BY expense_date DESC, id DESC
");
if ($re2)
    while ($row = $re2->fetch_assoc())
        $expenseRows[] = $row;

$totalExpenses = array_sum(array_column($expenseRows, 'amount'));

$hasPayCol = $conn->query("SHOW COLUMNS FROM sales LIKE 'payment_method'")->num_rows > 0;
$paySelect = $hasPayCol ? ", payment_method" : ", 'cash' AS payment_method";

$hasSplitCols = $conn->query("SHOW COLUMNS FROM sales LIKE 'cash_amount'")->num_rows > 0;
$splitSelect = $hasSplitCols
    ? ", cash_amount, gcash_amount, credit_amount"
    : ", 0 AS cash_amount, 0 AS gcash_amount, 0 AS credit_amount";

$hasNotesCol = $conn->query("SHOW COLUMNS FROM sales LIKE 'notes'")->num_rows > 0;
$notesSelect = $hasNotesCol ? ", notes" : ", '' AS notes";

$hasCarModelCol = $conn->query("SHOW COLUMNS FROM sales LIKE 'car_model'")->num_rows > 0;
$carModelSelect = $hasCarModelCol ? ", car_model" : ", '' AS car_model";

$hasRefCol = $conn->query("SHOW COLUMNS FROM sales LIKE 'reference_number'")->num_rows > 0;
$refSelect = $hasRefCol ? ", reference_number" : ", '' AS reference_number";

$salesRows = [];
$r = $conn->query("
    SELECT id, sale_date, customer_name, plate_number,
           parts_total, labor_total,
           (parts_total + labor_total) AS grand_total
           $paySelect
           $splitSelect
           $notesSelect
           $carModelSelect
           $refSelect
    FROM sales
    ORDER BY sale_date DESC, id DESC
");
if ($r)
    while ($row = $r->fetch_assoc())
        $salesRows[] = $row;

// Parts used, categories touched, and full search text — per sale.
// Joins sale_items -> products -> categories so we can:
//   1) show which parts were used on each sale (Parts column)
//   2) let the search box match a category name (e.g. "Coolant")
//   3) power a Category filter dropdown
$partsBySale = [];       // sale_id => [ ['description'=>.., 'quantity'=>.., 'category'=>..], ... ]
$partsQtyBySale = [];    // sale_id => total quantity of parts sold on that sale
$laborQtyBySale = [];    // sale_id => total quantity of labor/service lines on that sale
$categoriesBySale = [];  // sale_id => [category_name => true]  (set, for the filter)
$searchTermsBySale = []; // sale_id => [term, term, ...] (descriptions + categories, for the search box)

$rp = $conn->query("
    SELECT si.sale_id, si.line_type, si.description, si.quantity,
           c.category_name
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY si.sale_id, si.id
");
if ($rp) {
    while ($row = $rp->fetch_assoc()) {
        $sid = $row['sale_id'];

        $searchTermsBySale[$sid] ??= [];
        $searchTermsBySale[$sid][] = $row['description'];
        if ($row['category_name']) {
            $searchTermsBySale[$sid][] = $row['category_name'];
            $categoriesBySale[$sid][$row['category_name']] = true;
        }

        if ($row['line_type'] === 'parts') {
            $partsBySale[$sid] ??= [];
            $partsBySale[$sid][] = [
                'description' => $row['description'],
                'quantity'    => (int) $row['quantity'],
                'category'    => $row['category_name'],
            ];
            $partsQtyBySale[$sid] = ($partsQtyBySale[$sid] ?? 0) + (int) $row['quantity'];
        } elseif ($row['line_type'] === 'labor') {
            $laborQtyBySale[$sid] = ($laborQtyBySale[$sid] ?? 0) + (int) $row['quantity'];
        }
    }
}

// Category list for the filter dropdown
$allCategories = [];
$rc = $conn->query("SELECT category_name FROM categories ORDER BY category_name ASC");
if ($rc)
    while ($row = $rc->fetch_assoc())
        $allCategories[] = $row['category_name'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History — DSpeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/sales-history.css">
    <style>
        .pay-cash {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(74, 222, 128, .12);
            color: #4ade80;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: .7rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .pay-gcash {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(96, 165, 250, .12);
            color: #60a5fa;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: .7rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .pay-credit {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(167, 139, 250, .12);
            color: #a78bfa;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: .7rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .pay-split {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: .7rem;
            font-weight: 700;
            white-space: nowrap;
        }

        /* ── Edit Payment modal: toggle + split panel (mirrors Daily Transaction) ── */
        .pay-toggle {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        .pay-toggle > div {
            flex: 1;
            min-width: 100px;
        }
        .pay-toggle input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
            pointer-events: none;
        }
        .pay-toggle label {
            display: flex !important;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 10px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            border: 1.5px solid rgba(255, 255, 255, .1) !important;
            background: rgba(255, 255, 255, .04) !important;
            font-size: .8rem;
            font-weight: 600;
            color: #64748b !important;
            margin: 0;
            transition: all .15s;
        }
        .pay-toggle input:checked + label.pay-cash {
            border-color: rgba(74, 222, 128, .55) !important;
            background: rgba(74, 222, 128, .14) !important;
            color: #4ade80 !important;
        }
        .pay-toggle input:checked + label.pay-gcash {
            border-color: rgba(96, 165, 250, .55) !important;
            background: rgba(96, 165, 250, .14) !important;
            color: #60a5fa !important;
        }
        .pay-toggle input:checked + label.pay-credit {
            border-color: rgba(167, 139, 250, .55) !important;
            background: rgba(167, 139, 250, .14) !important;
            color: #a78bfa !important;
        }
        .pay-toggle input:checked + label.pay-split {
            border-color: rgba(251, 191, 36, .55) !important;
            background: rgba(251, 191, 36, .14) !important;
            color: #fbbf24 !important;
        }

        .split-payment-panel {
            margin-top: 12px;
            padding: 12px 14px;
            background: rgba(251, 191, 36, .04);
            border: 1px solid rgba(251, 191, 36, .15);
            border-radius: 10px;
            display: none;
        }
        .split-payment-panel.active { display: block; }
        .split-payment-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin-bottom: 10px;
        }
        .split-remaining {
            display: flex;
            justify-content: space-between;
            font-size: .8rem;
            font-weight: 700;
            padding-top: 8px;
            border-top: 1px solid rgba(255, 255, 255, .08);
        }
        .split-remaining.balanced { color: #4ade80; }
        .split-remaining.unbalanced { color: #f87171; }

        .exp-cell {
            color: #f87171;
            font-weight: 600;
            font-size: .82rem;
        }

        .exp-desc-tip {
            display: block;
            font-size: .68rem;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
            margin-top: 2px;
        }

        .net-positive {
            color: #4ade80;
            font-weight: 700;
        }

        .net-negative {
            color: #f87171;
            font-weight: 700;
        }

        .net-zero {
            color: #94a3b8;
            font-weight: 600;
        }

        /* modal overrides for new theme */
        #viewModal .modal-content {
            background: #161b27;
            border: 1px solid rgba(74, 222, 128, .15);
            color: #e2e8f0;
        }

        #viewModal .modal-header {
            background: linear-gradient(135deg, #0f1f15, #111827);
            border-bottom: 1px solid rgba(74, 222, 128, .15);
        }

        /* ── Edit Payment / Notes modal ── */
        #editPaymentModal .modal-content {
            background: #161b27;
            border: 1px solid rgba(251, 191, 36, .18);
            color: #e2e8f0;
        }
        #editPaymentModal .modal-header {
            background: linear-gradient(135deg, #1f1608, #111827);
            border-bottom: 1px solid rgba(251, 191, 36, .18);
        }
        #editPaymentModal .modal-header .modal-title {
            color: #fbbf24;
            font-family: 'Space Grotesk', sans-serif;
            font-size: .95rem;
        }
        #editPaymentModal .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, .07);
        }
        #editPaymentModal .form-label {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #64748b;
            margin-bottom: 6px;
            display: block;
        }
        #editPaymentModal .form-input {
            width: 100%;
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .1);
            color: #e2e8f0;
            border-radius: 8px;
            padding: 9px 12px;
            font-size: .85rem;
            font-family: 'Inter', sans-serif;
        }
        #editPaymentModal .form-input:focus {
            outline: none;
            border-color: rgba(251, 191, 36, .4);
            background: rgba(251, 191, 36, .04);
            box-shadow: 0 0 0 3px rgba(251, 191, 36, .08);
        }
        #editPaymentModal .form-input::placeholder {
            color: #4b5a6e;
        }
        #editPaymentModal textarea.form-input {
            resize: vertical;
            min-height: 70px;
        }
        #editPaymentModal .split-payment-panel {
            background: rgba(251, 191, 36, .05);
            border-color: rgba(251, 191, 36, .18);
        }
        #epSubmit {
            background: linear-gradient(135deg, #f59e0b, #b45309) !important;
        }
        #epSubmit:hover {
            filter: brightness(1.08);
        }

        .detail-label {
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #4b5a6e;
            margin-bottom: 3px;
        }

        .detail-value {
            font-size: .88rem;
            color: #e2e8f0;
        }

        .exp-block {
            background: rgba(248, 113, 113, .05);
            border: 1px solid rgba(248, 113, 113, .15);
            border-radius: 8px;
            padding: 12px 14px;
            margin-top: 14px;
        }

        .exp-block-title {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #f87171;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .exp-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            border-bottom: 1px solid rgba(248, 113, 113, .08);
            font-size: .82rem;
        }

        .exp-item-row:last-child {
            border-bottom: none;
        }

        .exp-item-name {
            color: #94a3b8;
        }

        .exp-item-amt {
            color: #f87171;
            font-weight: 600;
        }

        .no-exp-msg {
            font-size: .8rem;
            color: #2e3a4e;
            font-style: italic;
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <main class="app-main">

        <div class="page-header mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 style="margin:0;"><i class="bi bi-clock-history me-2"></i>Sales History</h4>
                    <p style="margin:0;">All transactions with daily expenses & net</p>
                </div>
                <a href="sales.php" class="btn-pink"><i class="bi bi-plus-lg me-1"></i>New Sale</a>
            </div>
        </div>

        <!-- Summary pills -->
        <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="summary-pill"><i class="bi bi-receipt me-1" style="color:#4ade80;"></i>Total:
                <strong><?= number_format($stats['total']) ?> sales</strong></span>
            <span class="summary-pill"><i class="bi bi-calendar-day me-1" style="color:#60a5fa;"></i>Today Sales:
                <strong>₱<?= number_format($stats['today_total'], 2) ?></strong></span>
            <span class="summary-pill"><i class="bi bi-wallet2 me-1" style="color:#f87171;"></i>Today Expenses: <strong
                    style="color:#f87171;">₱<?= number_format($expStats['today_exp'], 2) ?></strong></span>
            <span class="summary-pill"><i class="bi bi-graph-up me-1" style="color:#4ade80;"></i>Today Net: <strong
                    style="color:<?= ($stats['today_total'] - $expStats['today_exp']) >= 0 ? '#4ade80' : '#f87171' ?>;">₱<?= number_format($stats['today_total'] - $expStats['today_exp'], 2) ?></strong></span>
            <span class="summary-pill"><i class="bi bi-calendar3 me-1" style="color:#a78bfa;"></i>Month Sales:
                <strong>₱<?= number_format($stats['month_total'], 2) ?></strong></span>
            <span class="summary-pill"><i class="bi bi-wallet2 me-1" style="color:#f87171;"></i>Month Expenses: <strong
                    style="color:#f87171;">₱<?= number_format($expStats['month_exp'], 2) ?></strong></span>
            <span class="summary-pill"><i class="bi bi-graph-up-arrow me-1" style="color:#4ade80;"></i>Month Net:
                <strong
                    style="color:<?= ($stats['month_total'] - $expStats['month_exp']) >= 0 ? '#4ade80' : '#f87171' ?>;">₱<?= number_format($stats['month_total'] - $expStats['month_exp'], 2) ?></strong></span>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar">
            <input type="text" id="searchInput" class="form-control" style="max-width:220px;"
                placeholder="Customer, plate, car model, category, part/service…">
            <input type="date" id="dateFrom" class="form-control" style="max-width:145px;">
            <input type="date" id="dateTo" class="form-control" style="max-width:145px;">
            <select id="payFilter" class="form-control" style="max-width:130px;">
                <option value="">All Payments</option>
                <option value="cash">💵 Cash</option>
                <option value="gcash">📱 Online Payment</option>
                <option value="credit">💳 Credit</option>
            </select>
            <select id="categoryFilter" class="form-control" style="max-width:160px;">
                <option value="">All Categories</option>
                <?php foreach ($allCategories as $cat): ?>
                    <option value="<?= htmlspecialchars(strtolower($cat)) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-pink" style="font-size:.82rem;padding:7px 16px;" onclick="filterTable()">
                <i class="bi bi-search me-1"></i>Search
            </button>
            <button class="btn-ghost" style="font-size:.82rem;padding:7px 16px;" onclick="resetFilter()">Reset</button>
            <?php if ($isOwner): ?>
                <button class="btn-ghost ms-auto" style="font-size:.82rem;padding:7px 16px;" onclick="exportCSV()">
                    <i class="bi bi-download me-1"></i>Export CSV
                </button>
                <button class="btn-ghost" id="selectModeBtn" style="font-size:.82rem;padding:7px 16px;" onclick="toggleSelectMode()">
                    <i class="bi bi-check2-square me-1"></i>Select
                </button>
                <button class="btn-pink" id="bulkDeleteBtn" style="font-size:.82rem;padding:7px 16px;background:linear-gradient(135deg,#dc2626,#7f1d1d);border-color:rgba(248,113,113,.4);color:#fca5a5;display:none;" onclick="bulkDeleteSales()">
                    <i class="bi bi-trash me-1"></i>Delete Selected (<span id="selCount">0</span>)
                </button>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="data-table" id="salesTable">
                        <thead>
                            <tr>
                                <?php if ($isOwner): ?>
                                <th id="checkboxColHead" style="display:none;width:36px;">
                                    <input type="checkbox" id="selectAllSales" onchange="toggleSelectAllSales(this)">
                                </th>
                                <?php endif; ?>
                                <th>#</th>
                                <th>Ref #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Plate</th>
                                <th>Car Model</th>
                                <th>Parts Used</th>
                                <th>QTY</th>
                                <th>Parts ₱</th>
                                <th>Labor ₱</th>
                                <th>Gross Total ₱</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="salesBody">
                            <?php if (empty($salesRows)): ?>
                                <tr>
                                    <td colspan="<?= $isOwner ? 14 : 13 ?>" style="text-align:center;padding:30px;color:#64748b;">
                                        No sales yet. <a href="sales.php">Record one →</a>
                                    </td>
                                </tr>
                            <?php else:
                                $rowNum = 0;
                                foreach ($salesRows as $s):
                                    $rowNum++;
                                    $pm = $s['payment_method'] ?? 'cash';
                                    $gross = (float) $s['grand_total'];
                                    ?>
                                    <?php
                                    $saleParts   = $partsBySale[$s['id']] ?? [];
                                    $savedPartsQty = $partsQtyBySale[$s['id']] ?? 0;
                                    $savedLaborQty = $laborQtyBySale[$s['id']] ?? 0;
                                    $savedTotalQty = $savedPartsQty + $savedLaborQty;
                                    $saleCats    = $categoriesBySale[$s['id']] ?? [];
                                    $searchTerms = $searchTermsBySale[$s['id']] ?? [];

                                    $partsLabel = $saleParts
                                        ? implode(', ', array_map(
                                            fn($p) => (
                                                $p['description'] === '' || ctype_digit((string) $p['description'])
                                                    ? '⚠ Unrecorded part'
                                                    : $p['description']
                                            ),
                                            $saleParts
                                          ))
                                        : '';

                                    $searchIndex = strtolower(htmlspecialchars(
                                        $s['customer_name'] . ' ' . $s['plate_number'] . ' ' . ($s['car_model'] ?? '')
                                        . ' ' . implode(' ', $searchTerms)
                                    ));

                                    $catAttr = strtolower(htmlspecialchars(implode(' ', array_keys($saleCats))));
                                    ?>
                                    <tr data-id="<?= $s['id'] ?>" data-date="<?= $s['sale_date'] ?>" data-pay="<?= $pm ?>"
                                        data-categories="<?= $catAttr ?>"
                                        data-search="<?= $searchIndex ?>">
                                        <?php if ($isOwner): ?>
                                        <td class="checkbox-col" style="display:none;">
                                            <input type="checkbox" class="sale-row-check" value="<?= $s['id'] ?>" onchange="updateSalesSelCount()">
                                        </td>
                                        <?php endif; ?>
                                        <td><span class="badge-gray row-num"><?= $rowNum ?></span></td>
                                        <td style="white-space:nowrap;">
                                            <span style="font-family:'Space Grotesk',sans-serif;font-weight:700;color:#4ade80;">
                                                <?= htmlspecialchars($s['reference_number'] ?: '—') ?>
                                            </span>
                                        </td>
                                        <td style="white-space:nowrap;"><?= date('M d, Y', strtotime($s['sale_date'])) ?></td>
                                        <td><?= htmlspecialchars($s['customer_name'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($s['plate_number'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($s['car_model'] ?: '—') ?></td>
                                        <td style="max-width:220px;">
                                            <?php if ($partsLabel): ?>
                                                <span style="font-size:.8rem;color:#93c5fd;" title="<?= htmlspecialchars($partsLabel) ?>">
                                                    <?= htmlspecialchars(mb_strimwidth($partsLabel, 0, 60, '…')) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#2e3a4e;font-style:italic;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;color:#94a3b8;">
                                            <?= $savedTotalQty > 0 ? $savedTotalQty : '—' ?>
                                        </td>
                                        <td style="color:#60a5fa;font-weight:600;">₱<?= number_format($s['parts_total'], 2) ?>
                                        </td>
                                        <td style="color:#4ade80;font-weight:600;">₱<?= number_format($s['labor_total'], 2) ?>
                                            <?php if (!empty($s['notes'])): ?>
                                                <i class="bi bi-sticky-fill ms-1" style="color:#94a3b8;font-size:.75rem;cursor:help;"
                                                   title="<?= htmlspecialchars($s['notes']) ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-weight:700;">₱<?= number_format($gross, 2) ?></td>
                                        <td>
                                            <?php if ($pm === 'split'): ?>
                                                <span class="pay-split" style="display:inline-flex;flex-direction:column;gap:1px;line-height:1.3;">
                                                    <span style="color:#fbbf24;"><i class="bi bi-arrow-left-right"></i> Split</span>
                                                    <span style="font-size:.68rem;color:#64748b;">
                                                        <?php
                                                        $splitParts = [];
                                                        if ($s['cash_amount'] > 0)   $splitParts[] = '₱' . number_format($s['cash_amount'], 0) . ' Cash';
                                                        if ($s['gcash_amount'] > 0)  $splitParts[] = '₱' . number_format($s['gcash_amount'], 0) . ' Online';
                                                        if ($s['credit_amount'] > 0) $splitParts[] = '₱' . number_format($s['credit_amount'], 0) . ' Credit';
                                                        echo htmlspecialchars(implode(' + ', $splitParts));
                                                        ?>
                                                    </span>
                                                </span>
                                            <?php elseif ($pm === 'gcash'): ?>
                                                <span class="pay-gcash"><i class="bi bi-phone-fill"></i> Online Payment</span>
                                            <?php elseif ($pm === 'credit'): ?>
                                                <span class="pay-credit"><i class="bi bi-credit-card"></i> Credit</span>
                                            <?php else: ?>
                                                <span class="pay-cash"><i class="bi bi-cash-coin"></i> Cash</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="white-space:nowrap;">
                                            <button class="btn btn-sm btn-outline-info"
                                                onclick="viewSale(<?= $s['id'] ?>, '<?= $s['sale_date'] ?>')"
                                                title="View details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success ms-1"
                                                onclick="printReceipt(<?= $s['id'] ?>)" title="Print Receipt">
                                                <i class="bi bi-printer"></i>
                                            </button>
                                            <?php if ($isOwner): ?>
                                                <button class="btn btn-sm btn-outline-warning ms-1"
                                                    onclick='openEditPayment(<?= $s["id"] ?>, <?= json_encode($pm) ?>, <?= (float) $s["cash_amount"] ?>, <?= (float) $s["gcash_amount"] ?>, <?= (float) $s["credit_amount"] ?>, <?= json_encode($s["notes"] ?? "") ?>, <?= (float) $s["parts_total"] + (float) $s["labor_total"] ?>)'
                                                    title="Edit Payment / Notes">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger ms-1"
                                                    onclick="deleteSale(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['customer_name'] ?: 'Sale #' . $s['id'])) ?>')"
                                                    title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ══ EXPENSES TABLE ══ -->
        <div class="page-header mt-4 mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 style="margin:0;"><i class="bi bi-wallet2 me-2" style="color:#f87171;"></i>Expenses History</h4>
                    <p style="margin:0;">All recorded expenses — separate from sales</p>
                </div>
                <span class="summary-pill">
                    <i class="bi bi-wallet2 me-1" style="color:#f87171;"></i>
                    Total: <strong style="color:#f87171;">₱<?= number_format($totalExpenses, 2) ?></strong>
                </span>
            </div>
        </div>

        <!-- Expense filter bar -->
        <div class="filter-bar" id="expFilterBar">
            <input type="text" id="expSearchInput" class="form-control" style="max-width:200px;"
                placeholder="Description or category…">
            <input type="date" id="expDateFrom" class="form-control" style="max-width:145px;">
            <input type="date" id="expDateTo" class="form-control" style="max-width:145px;">
            <button class="btn-pink" style="font-size:.82rem;padding:7px 16px;" onclick="filterExpenses()">
                <i class="bi bi-search me-1"></i>Search
            </button>
            <button class="btn-ghost" style="font-size:.82rem;padding:7px 16px;"
                onclick="resetExpenses()">Reset</button>
            <?php if ($isOwner): ?>
                <button class="btn-ghost ms-auto" style="font-size:.82rem;padding:7px 16px;" onclick="exportExpensesCSV()">
                    <i class="bi bi-download me-1"></i>Export CSV
                </button>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="data-table" id="expensesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount ₱</th>
                            </tr>
                        </thead>
                        <tbody id="expensesBody">
                            <?php if (empty($expenseRows)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center;padding:30px;color:#64748b;">
                                        No expenses recorded yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $expRowNum = 0;
                                foreach ($expenseRows as $e):
                                    $expRowNum++; ?>
                                    <tr data-date="<?= $e['expense_date'] ?>" data-id="<?= $e['id'] ?>"
                                        data-search="<?= strtolower(htmlspecialchars($e['description'] . ' ' . $e['category'])) ?>">
                                        <td><span class="badge-gray row-num"><?= $expRowNum ?></span></td>
                                        <td style="white-space:nowrap;"><?= date('M d, Y', strtotime($e['expense_date'])) ?>
                                        </td>
                                        <td><span class="badge-red"><?= htmlspecialchars($e['category'] ?: 'Other') ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($e['description']) ?></td>
                                        <td class="exp-cell">−₱<?= number_format($e['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <!-- View Sale Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"
                        style="color:#4ade80;font-family:'Space Grotesk',sans-serif;font-size:.95rem;">
                        <i class="bi bi-receipt me-2"></i>Sale Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="saleDetailBody">
                    <div style="text-align:center;padding:30px;">
                        <div class="spinner-border" style="color:#4ade80;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isOwner): ?>
    <div class="modal fade" id="editPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Payment / Notes</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="epId">
                    <input type="hidden" id="epGrandTotal">
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <div class="pay-toggle">
                            <div>
                                <input type="radio" name="epPayment" id="ep-cash" value="cash" onchange="epTogglePanel()">
                                <label for="ep-cash" class="pay-cash"><i class="bi bi-cash-coin"></i> Cash</label>
                            </div>
                            <div>
                                <input type="radio" name="epPayment" id="ep-gcash" value="gcash" onchange="epTogglePanel()">
                                <label for="ep-gcash" class="pay-gcash"><i class="bi bi-phone-fill"></i> Online</label>
                            </div>
                            <div>
                                <input type="radio" name="epPayment" id="ep-credit" value="credit" onchange="epTogglePanel()">
                                <label for="ep-credit" class="pay-credit"><i class="bi bi-credit-card"></i> Credit</label>
                            </div>
                            <div>
                                <input type="radio" name="epPayment" id="ep-split" value="split" onchange="epTogglePanel()">
                                <label for="ep-split" class="pay-split"><i class="bi bi-arrow-left-right"></i> Split</label>
                            </div>
                        </div>
                    </div>
                    <div class="split-payment-panel" id="epSplitPanel">
                        <div class="split-payment-row">
                            <div>
                                <label class="field-label">Cash</label>
                                <input type="number" class="form-input" id="epCash" min="0" step="0.01" value="0" oninput="epUpdateRemaining()">
                            </div>
                            <div>
                                <label class="field-label">Online</label>
                                <input type="number" class="form-input" id="epGcash" min="0" step="0.01" value="0" oninput="epUpdateRemaining()">
                            </div>
                            <div>
                                <label class="field-label">Credit</label>
                                <input type="number" class="form-input" id="epCredit" min="0" step="0.01" value="0" oninput="epUpdateRemaining()">
                            </div>
                        </div>
                        <div class="split-remaining" id="epRemaining">
                            <span>Remaining to allocate:</span>
                            <span id="epRemainingValue">₱0.00</span>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-input" id="epNotes" rows="3" placeholder="Optional notes…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="epSubmit" style="border:none;color:#fff;padding:9px 20px;border-radius:50px;font-weight:700;cursor:pointer;">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        'use strict';
        const IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;

        // Clean PHP data for CSV export — no HTML scraping needed
        const SALES_DATA = <?= json_encode(array_map(function($s) use ($partsBySale, $partsQtyBySale, $laborQtyBySale) {
            $parts = $partsBySale[$s['id']] ?? [];
            $partsUsed = implode('; ', array_map(
                fn($p) => (
                    $p['description'] === '' || ctype_digit((string) $p['description'])
                        ? 'Unrecorded part'
                        : $p['description']
                ),
                $parts
            ));
            return [
                'id'             => $s['id'],
                'reference_number' => $s['reference_number'] ?: '',
                'sale_date'      => $s['sale_date'],
                'customer_name'  => $s['customer_name'] ?: '',
                'plate_number'   => $s['plate_number'] ?: '',
                'car_model'      => $s['car_model'] ?: '',
                'parts_used'     => $partsUsed,
                'parts_qty'      => ($partsQtyBySale[$s['id']] ?? 0) + ($laborQtyBySale[$s['id']] ?? 0),
                'parts_total'    => number_format((float)$s['parts_total'], 2, '.', ''),
                'labor_total'    => number_format((float)$s['labor_total'], 2, '.', ''),
                'grand_total'    => number_format((float)$s['grand_total'], 2, '.', ''),
                'payment_method' => $s['payment_method'] ?? 'cash',
            ];
        }, $salesRows), JSON_UNESCAPED_UNICODE) ?>;

        const EXPENSES_DATA = <?= json_encode(array_map(function($e) {
            return [
                'id'           => $e['id'],
                'expense_date' => $e['expense_date'],
                'category'     => $e['category'] ?: 'Other',
                'description'  => $e['description'],
                'amount'       => number_format((float)$e['amount'], 2, '.', ''),
            ];
        }, $expenseRows), JSON_UNESCAPED_UNICODE) ?>;

        // Expenses by date from PHP — available in JS for modal
        const EXP_BY_DATE = <?= json_encode($expByDate, JSON_UNESCAPED_UNICODE) ?>;

        // ── Re-number visible rows ──
        function renumberRows(tbodyId) {
            let n = 0;
            document.querySelectorAll(`#${tbodyId} tr[data-date]`).forEach(tr => {
                if (tr.style.display !== 'none') {
                    n++;
                    const badge = tr.querySelector('.row-num');
                    if (badge) badge.textContent = n;
                }
            });
        }

        function filterTable() {
            const q = document.getElementById('searchInput').value.toLowerCase().trim();
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            const pay = document.getElementById('payFilter').value;
            const cat = document.getElementById('categoryFilter').value;

            document.querySelectorAll('#salesBody tr[data-date]').forEach(tr => {
                const d = tr.dataset.date;
                const matchQ = !q || tr.dataset.search.includes(q);
                const matchD = (!from || d >= from) && (!to || d <= to);
                const matchP = !pay || tr.dataset.pay === pay;
                const matchC = !cat || (tr.dataset.categories || '').split(' ').includes(cat);
                tr.style.display = matchQ && matchD && matchP && matchC ? '' : 'none';
            });
            renumberRows('salesBody');
        }

        function resetFilter() {
            ['searchInput', 'dateFrom', 'dateTo'].forEach(id => document.getElementById(id).value = '');
            document.getElementById('payFilter').value = '';
            document.getElementById('categoryFilter').value = '';
            document.querySelectorAll('#salesBody tr').forEach(tr => tr.style.display = '');
            renumberRows('salesBody');
        }

        document.getElementById('searchInput').addEventListener('input', filterTable);
        document.getElementById('payFilter').addEventListener('change', filterTable);
        document.getElementById('categoryFilter').addEventListener('change', filterTable);

        function exportCSV() {
            // Collect which sale IDs are currently visible (respects filters)
            const visibleIds = new Set();
            document.querySelectorAll('#salesBody tr[data-date]').forEach(tr => {
                if (tr.style.display !== 'none') visibleIds.add(String(tr.dataset.id));
            });

            const rows = [['ID','Ref #','Date','Customer','Plate No.','Car Model','Parts Used','QTY','Parts (PHP)','Labor (PHP)','Gross Total (PHP)','Payment']];
            SALES_DATA.forEach(s => {
                if (!visibleIds.has(String(s.id))) return;
                rows.push([s.id, s.reference_number, s.sale_date, s.customer_name, s.plate_number, s.car_model, s.parts_used, s.parts_qty,
                    s.parts_total, s.labor_total, s.grand_total, s.payment_method.toUpperCase()]);
            });

            if (rows.length <= 1) {
                Swal.fire({ icon: 'info', title: 'Nothing to export', text: 'No visible rows match the current filter.' });
                return;
            }

            const csv = rows.map(r => r.map(v => '"' + String(v).replace(/"/g, '""') + '"').join(',')).join('\n');
            const a = document.createElement('a');
            a.href = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
            a.download = 'DSpeedway_Sales_' + new Date().toISOString().slice(0, 10) + '.csv';
            a.click();
        }

        async function viewSale(id, saleDate) {
            const body = document.getElementById('saleDetailBody');
            body.innerHTML = '<div style="text-align:center;padding:30px;"><div class="spinner-border" style="color:#4ade80;"></div></div>';
            new bootstrap.Modal(document.getElementById('viewModal')).show();

            try {
                const resp = await fetch(`backend/sales.php?action=detail&id=${id}`);
                const data = await resp.json();

                if (!data.success) {
                    body.innerHTML = `<p style="color:#fca5a5;text-align:center;">${data.message}</p>`;
                    return;
                }

                const s = data.sale;
                const gross = parseFloat(s.parts_total) + parseFloat(s.labor_total);
                const pm = s.payment_method ?? 'cash';
                const expData = EXP_BY_DATE[saleDate] ?? null;
                const expAmt = expData ? parseFloat(expData.total) : 0;
                const net = gross - expAmt;

                const payBadge = pm === 'split'
                    ? `<span style="color:#fbbf24;"><i class="bi bi-arrow-left-right"></i> Split</span>
                       <div style="font-size:.72rem;color:#64748b;margin-top:2px;">
                           ${[
                               parseFloat(s.cash_amount)   > 0 ? '₱' + parseFloat(s.cash_amount).toFixed(2)   + ' Cash'   : '',
                               parseFloat(s.gcash_amount)  > 0 ? '₱' + parseFloat(s.gcash_amount).toFixed(2)  + ' Online' : '',
                               parseFloat(s.credit_amount) > 0 ? '₱' + parseFloat(s.credit_amount).toFixed(2) + ' Credit' : '',
                           ].filter(Boolean).join(' + ')}
                       </div>`
                    : pm === 'gcash'
                        ? `<span class="pay-gcash"><i class="bi bi-phone-fill"></i> Online Payment</span>`
                        : pm === 'credit'
                            ? `<span class="pay-credit"><i class="bi bi-credit-card"></i> Credit</span>`
                            : `<span class="pay-cash"><i class="bi bi-cash-coin"></i> Cash</span>`;

                // Build expense items HTML
                let expHtml = '';
                if (expAmt > 0) {
                    // Fetch detailed expense list for this date
                    const expResp = await fetch(`backend/expenses.php?action=by_date&date=${saleDate}`);
                    let expItems = [];
                    try {
                        const expJson = await expResp.json();
                        if (expJson.success) expItems = expJson.items;
                    } catch (e) { }

                    if (expItems.length > 0) {
                        expHtml = `
                <div class="exp-block">
                    <div class="exp-block-title"><i class="bi bi-wallet2"></i>Expenses on ${saleDate}</div>
                    ${expItems.map(e => `
                    <div class="exp-item-row">
                        <span class="exp-item-name">${e.description}${e.category ? ' <span style="color:#2e3a4e;font-size:.7rem;">(' + e.category + ')</span>' : ''}</span>
                        <span class="exp-item-amt">−₱${parseFloat(e.amount).toFixed(2)}</span>
                    </div>`).join('')}
                    <div style="display:flex;justify-content:flex-end;padding-top:8px;font-size:.82rem;">
                        Total Expenses: <strong class="exp-item-amt ms-2">−₱${expAmt.toFixed(2)}</strong>
                    </div>
                </div>`;
                    } else {
                        // fallback if backend doesn't have by_date action
                        expHtml = `
                <div class="exp-block">
                    <div class="exp-block-title"><i class="bi bi-wallet2"></i>Expenses on ${saleDate}</div>
                    ${(expData?.descriptions || '').split(' | ').map(d => `
                    <div class="exp-item-row">
                        <span class="exp-item-name">${d}</span>
                    </div>`).join('')}
                    <div style="display:flex;justify-content:flex-end;padding-top:8px;font-size:.82rem;">
                        Total: <strong class="exp-item-amt ms-2">−₱${expAmt.toFixed(2)}</strong>
                    </div>
                </div>`;
                    }
                } else {
                    expHtml = `<div class="exp-block"><div class="exp-block-title"><i class="bi bi-wallet2"></i>Expenses</div><p class="no-exp-msg">No expenses recorded on this date.</p></div>`;
                }

                const netColor = net >= 0 ? '#4ade80' : '#f87171';

                body.innerHTML = `
            <div class="row mb-3 g-3">
                <div class="col-6 col-sm-3">
                    <div class="detail-label">Reference #</div>
                    <div class="detail-value" style="color:#4ade80;font-weight:700;">${s.reference_number || '—'}</div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="detail-label">Date</div>
                    <div class="detail-value">${s.sale_date}</div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="detail-label">Customer</div>
                    <div class="detail-value">${s.customer_name || '—'}</div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="detail-label">Plate No.</div>
                    <div class="detail-value">${s.plate_number || '—'}</div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="detail-label">Payment</div>
                    <div style="margin-top:2px;">${payBadge}</div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="detail-label">Car Model</div>
                    <div class="detail-value">${s.car_model || '—'}</div>
                </div>
            </div>

            ${s.notes ? `
            <div style="margin-bottom:14px;padding:12px 14px;background:rgba(148,163,184,.05);border:1px solid rgba(148,163,184,.15);border-radius:9px;">
                <div class="detail-label" style="margin-bottom:4px;"><i class="bi bi-sticky-fill me-1"></i>Notes</div>
                <div style="font-size:.85rem;color:#cbd5e1;white-space:pre-wrap;">${s.notes.replace(/</g, '&lt;')}</div>
            </div>` : ''}

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Item / Service</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.items.map(i => {
                            const isBogus = !i.description || /^\d+$/.test(String(i.description));
                            const label = isBogus ? '⚠ Unrecorded item' : i.description;
                            return `
                        <tr>
                            <td><span class="${i.line_type === 'parts' ? 'badge-blue' : 'badge-green'}">${i.line_type}</span></td>
                            <td>${label}${i.category_name ? ` <span style="color:#4b5a6e;font-size:.72rem;">(${i.category_name})</span>` : ''}</td>
                            <td>${i.quantity}</td>
                            <td>₱${parseFloat(i.unit_price).toFixed(2)}</td>
                            <td>₱${parseFloat(i.amount).toFixed(2)}</td>
                        </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>

            ${expHtml}

            <!-- Summary footer -->
            <div style="margin-top:14px;padding:14px 16px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:9px;">
                <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:.84rem;border-bottom:1px solid rgba(255,255,255,.05);">
                    <span style="color:#64748b;">Parts</span>
                    <strong style="color:#60a5fa;">₱${parseFloat(s.parts_total).toFixed(2)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:.84rem;border-bottom:1px solid rgba(255,255,255,.05);">
                    <span style="color:#64748b;">Labor</span>
                    <strong style="color:#4ade80;">₱${parseFloat(s.labor_total).toFixed(2)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:.84rem;border-bottom:1px solid rgba(255,255,255,.05);">
                    <span style="color:#64748b;">Gross Total</span>
                    <strong style="color:#fff;">₱${gross.toFixed(2)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:.84rem;border-bottom:1px solid rgba(255,255,255,.05);">
                    <span style="color:#f87171;">Expenses</span>
                    <strong style="color:#f87171;">−₱${expAmt.toFixed(2)}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0 4px;font-size:1rem;">
                    <span style="color:#e2e8f0;font-weight:700;">Net</span>
                    <strong style="color:${netColor};font-size:1.1rem;">₱${net.toFixed(2)}</strong>
                </div>
            </div>`;

            } catch (err) {
                body.innerHTML = `<p style="color:#fca5a5;text-align:center;">Error: ${err.message}</p>`;
            }
        }

        async function printReceipt(id) {
            try {
                const resp = await fetch(`backend/receipt.php?action=generate&id=${id}`);
                const data = await resp.json();

                if (!data.success) {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                    return;
                }

                // Create a new window for printing
                const printWindow = window.open('', '_blank', 'width=400,height=600');
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Receipt #${id}</title>
                        <style>
                            body { margin: 0; padding: 0; font-family: Arial, sans-serif; }
                            @media print {
                                body { margin: 0; }
                            }
                        </style>
                    </head>
                    <body>
                        ${data.receipt_html}
                        <script>
                            window.onload = function() {
                                window.print();
                                window.onafterprint = function() {
                                    window.close();
                                };
                            };
                        <\/script>
                    </body>
                    </html>
                `);
                printWindow.document.close();
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            }
        }

        let selectModeSales = false;

        function toggleSelectMode() {
            selectModeSales = !selectModeSales;
            document.getElementById('checkboxColHead').style.display = selectModeSales ? '' : 'none';
            document.querySelectorAll('#salesBody .checkbox-col').forEach(td => td.style.display = selectModeSales ? '' : 'none');
            document.getElementById('selectModeBtn').innerHTML = selectModeSales
                ? '<i class="bi bi-x-lg me-1"></i>Cancel'
                : '<i class="bi bi-check2-square me-1"></i>Select';
            if (!selectModeSales) {
                document.querySelectorAll('.sale-row-check').forEach(cb => cb.checked = false);
                document.getElementById('selectAllSales').checked = false;
                document.getElementById('bulkDeleteBtn').style.display = 'none';
            }
            updateSalesSelCount();
        }

        function toggleSelectAllSales(masterCb) {
            document.querySelectorAll('#salesBody tr[data-id]').forEach(tr => {
                if (tr.style.display === 'none') return; // respect current filter
                const cb = tr.querySelector('.sale-row-check');
                if (cb) cb.checked = masterCb.checked;
            });
            updateSalesSelCount();
        }

        function updateSalesSelCount() {
            const checked = document.querySelectorAll('.sale-row-check:checked').length;
            document.getElementById('selCount').textContent = checked;
            document.getElementById('bulkDeleteBtn').style.display = checked > 0 ? '' : 'none';
        }

        async function bulkDeleteSales() {
            const ids = Array.from(document.querySelectorAll('.sale-row-check:checked')).map(cb => cb.value);
            if (!ids.length) return;

            const result = await Swal.fire({
                title: `Delete ${ids.length} sale${ids.length > 1 ? 's' : ''}?`,
                text: 'This will restore inventory for any parts sold and cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Delete All',
            });
            if (!result.isConfirmed) return;

            const fd = new FormData();
            ids.forEach(id => fd.append('ids[]', id));
            const resp = await fetch('backend/sales.php?action=bulk_delete', { method: 'POST', body: fd });
            const data = await resp.json();

            if (data.success) {
                ids.forEach(id => document.querySelector(`#salesBody tr[data-id="${id}"]`)?.remove());
                renumberRows('salesBody');
                Swal.fire({ icon: 'success', title: `${data.deleted} deleted`, timer: 1400, showConfirmButton: false });
                toggleSelectMode();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        }

        function openEditPayment(id, paymentMethod, cashAmt, gcashAmt, creditAmt, notes, grandTotal) {
            document.getElementById('epId').value = id;
            document.getElementById('epGrandTotal').value = grandTotal;
            document.getElementById('epCash').value = cashAmt || 0;
            document.getElementById('epGcash').value = gcashAmt || 0;
            document.getElementById('epCredit').value = creditAmt || 0;
            document.getElementById('epNotes').value = notes || '';

            const radio = document.getElementById('ep-' + (paymentMethod || 'cash'));
            if (radio) radio.checked = true;

            epTogglePanel();
            new bootstrap.Modal(document.getElementById('editPaymentModal')).show();
        }

        function epTogglePanel() {
            const isSplit = document.getElementById('ep-split')?.checked;
            document.getElementById('epSplitPanel').classList.toggle('active', !!isSplit);
            epUpdateRemaining();
        }

        function epUpdateRemaining() {
            const panel = document.getElementById('epSplitPanel');
            if (!panel.classList.contains('active')) return;

            const grand  = parseFloat(document.getElementById('epGrandTotal').value) || 0;
            const cash   = parseFloat(document.getElementById('epCash').value)   || 0;
            const gcash  = parseFloat(document.getElementById('epGcash').value)  || 0;
            const credit = parseFloat(document.getElementById('epCredit').value) || 0;
            const remaining = grand - (cash + gcash + credit);

            const wrap = document.getElementById('epRemaining');
            const el = document.getElementById('epRemainingValue');
            if (Math.abs(remaining) < 0.01) {
                wrap.classList.remove('unbalanced');
                wrap.classList.add('balanced');
                el.textContent = '✓ Fully allocated';
            } else {
                wrap.classList.remove('balanced');
                wrap.classList.add('unbalanced');
                el.textContent = (remaining > 0 ? '₱' + remaining.toFixed(2) + ' left' : 'Over by ₱' + (-remaining).toFixed(2));
            }
        }

        document.getElementById('epSubmit')?.addEventListener('click', async () => {
            const btn = document.getElementById('epSubmit');
            const id = document.getElementById('epId').value;
            const paymentMethod = document.querySelector('input[name="epPayment"]:checked')?.value || 'cash';
            const cashAmt   = parseFloat(document.getElementById('epCash').value)   || 0;
            const gcashAmt  = parseFloat(document.getElementById('epGcash').value)  || 0;
            const creditAmt = parseFloat(document.getElementById('epCredit').value) || 0;
            const notes = document.getElementById('epNotes').value.trim();

            if (paymentMethod === 'split') {
                const grand = parseFloat(document.getElementById('epGrandTotal').value) || 0;
                if (Math.abs(grand - (cashAmt + gcashAmt + creditAmt)) > 0.01) {
                    Swal.fire({ icon: 'warning', title: "Split doesn't match total", text: 'Adjust the amounts so they add up to the sale total.' });
                    return;
                }
            }

            btn.disabled = true;
            btn.textContent = 'Saving…';
            try {
                const resp = await fetch('backend/sales.php?action=update_payment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id, payment_method: paymentMethod,
                        cash_amount: cashAmt, gcash_amount: gcashAmt, credit_amount: creditAmt,
                        notes,
                    }),
                });
                const data = await resp.json();
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editPaymentModal'))?.hide();
                    await Swal.fire({ icon: 'success', title: 'Updated!', timer: 1200, showConfirmButton: false });
                    location.reload();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Network error', text: e.message });
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save Changes';
            }
        });

        async function deleteSale(id, name) {
            if (!id) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Invalid ID' });
                return;
            }

            const result = await Swal.fire({
                title: 'Delete this sale?',
                text: `"${name}" will be permanently removed.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel',
            });
            if (!result.isConfirmed) return;

            const fd = new FormData();
            fd.append('id', id);
            const resp = await fetch(`backend/sales.php?action=delete&id=${id}`, { method: 'POST', body: fd });
            const data = await resp.json();

            if (data.success) {
                document.querySelector(`#salesBody tr[data-id="${id}"]`)?.remove();
                renumberRows('salesBody');
                Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1200, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        }

        // ── Expenses table filter ──
        function filterExpenses() {
            const q = document.getElementById('expSearchInput').value.toLowerCase().trim();
            const from = document.getElementById('expDateFrom').value;
            const to = document.getElementById('expDateTo').value;

            document.querySelectorAll('#expensesBody tr[data-date]').forEach(tr => {
                const d = tr.dataset.date;
                const matchQ = !q || tr.dataset.search.includes(q);
                const matchD = (!from || d >= from) && (!to || d <= to);
                tr.style.display = matchQ && matchD ? '' : 'none';
            });
            renumberRows('expensesBody');
        }

        function resetExpenses() {
            ['expSearchInput', 'expDateFrom', 'expDateTo'].forEach(id => document.getElementById(id).value = '');
            document.querySelectorAll('#expensesBody tr').forEach(tr => tr.style.display = '');
            renumberRows('expensesBody');
        }

        document.getElementById('expSearchInput').addEventListener('input', filterExpenses);

        function exportExpensesCSV() {
            // Collect which expense IDs are currently visible
            const visibleIds = new Set();
            document.querySelectorAll('#expensesBody tr[data-date]').forEach(tr => {
                if (tr.style.display !== 'none') visibleIds.add(String(tr.dataset.id));
            });

            const rows = [['ID','Date','Category','Description','Amount (PHP)']];
            EXPENSES_DATA.forEach(e => {
                if (!visibleIds.has(String(e.id))) return;
                rows.push([e.id, e.expense_date, e.category, e.description, e.amount]);
            });

            if (rows.length <= 1) {
                Swal.fire({ icon: 'info', title: 'Nothing to export', text: 'No visible rows match the current filter.' });
                return;
            }

            const csv = rows.map(r => r.map(v => '"' + String(v).replace(/"/g, '""') + '"').join(',')).join('\n');
            const a = document.createElement('a');
            a.href = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
            a.download = 'DSpeedway_Expenses_' + new Date().toISOString().slice(0, 10) + '.csv';
            a.click();
        }
    </script>
    <?php include 'footer.php'; ?>

</body>

</html>