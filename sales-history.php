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

// Expenses grouped by date for joining
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

$hasPayCol = $conn->query("SHOW COLUMNS FROM sales LIKE 'payment_method'")->num_rows > 0;
$paySelect = $hasPayCol ? ", payment_method" : ", 'cash' AS payment_method";

$salesRows = [];
$r = $conn->query("
    SELECT id, sale_date, customer_name, plate_number,
           parts_total, labor_total,
           (parts_total + labor_total) AS grand_total
           $paySelect
    FROM sales
    ORDER BY sale_date DESC, id DESC
");
if ($r)
    while ($row = $r->fetch_assoc())
        $salesRows[] = $row;
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
            <input type="text" id="searchInput" class="form-control" style="max-width:200px;"
                placeholder="Customer or plate…">
            <input type="date" id="dateFrom" class="form-control" style="max-width:145px;">
            <input type="date" id="dateTo" class="form-control" style="max-width:145px;">
            <select id="payFilter" class="form-control" style="max-width:130px;">
                <option value="">All Payments</option>
                <option value="cash">💵 Cash</option>
                <option value="gcash">📱 GCash</option>
            </select>
            <button class="btn-pink" style="font-size:.82rem;padding:7px 16px;" onclick="filterTable()">
                <i class="bi bi-search me-1"></i>Search
            </button>
            <button class="btn-ghost" style="font-size:.82rem;padding:7px 16px;" onclick="resetFilter()">Reset</button>
            <?php if ($isOwner): ?>
                <button class="btn-ghost ms-auto" style="font-size:.82rem;padding:7px 16px;" onclick="exportCSV()">
                    <i class="bi bi-download me-1"></i>Export CSV
                </button>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="data-table" id="salesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Plate</th>
                                <th>Parts ₱</th>
                                <th>Labor ₱</th>
                                <th>Gross Total ₱</th>
                                <th>Expenses ₱</th>
                                <th>Net ₱</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="salesBody">
                            <?php if (empty($salesRows)): ?>
                                <tr>
                                    <td colspan="11" style="text-align:center;padding:30px;color:#64748b;">
                                        No sales yet. <a href="sales.php">Record one →</a>
                                    </td>
                                </tr>
                            <?php else:
                                foreach ($salesRows as $s):
                                    $pm = $s['payment_method'] ?? 'cash';
                                    $gross = (float) $s['grand_total'];
                                    $expDate = $expByDate[$s['sale_date']] ?? null;
                                    $expAmt = $expDate ? $expDate['total'] : 0;
                                    $expDesc = $expDate ? $expDate['descriptions'] : '';
                                    $net = $gross - $expAmt;
                                    $netCls = $net > 0 ? 'net-positive' : ($net < 0 ? 'net-negative' : 'net-zero');
                                    ?>
                                    <tr data-id="<?= $s['id'] ?>" data-date="<?= $s['sale_date'] ?>" data-pay="<?= $pm ?>"
                                        data-exp="<?= $expAmt ?>" data-net="<?= $net ?>"
                                        data-search="<?= strtolower(htmlspecialchars($s['customer_name'] . ' ' . $s['plate_number'])) ?>">
                                        <td><span class="badge-gray"><?= $s['id'] ?></span></td>
                                        <td style="white-space:nowrap;"><?= date('M d, Y', strtotime($s['sale_date'])) ?></td>
                                        <td><?= htmlspecialchars($s['customer_name'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($s['plate_number'] ?: '—') ?></td>
                                        <td style="color:#60a5fa;font-weight:600;">₱<?= number_format($s['parts_total'], 2) ?>
                                        </td>
                                        <td style="color:#4ade80;font-weight:600;">₱<?= number_format($s['labor_total'], 2) ?>
                                        </td>
                                        <td style="font-weight:700;">₱<?= number_format($gross, 2) ?></td>
                                        <td>
                                            <?php if ($expAmt > 0): ?>
                                                <span class="exp-cell">−₱<?= number_format($expAmt, 2) ?></span>
                                                <?php if ($expDesc): ?>
                                                    <span class="exp-desc-tip" title="<?= htmlspecialchars($expDesc) ?>">
                                                        <?= htmlspecialchars($expDesc) ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color:#2e3a4e;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="<?= $netCls ?>">₱<?= number_format($net, 2) ?></td>
                                        <td>
                                            <?php if ($pm === 'gcash'): ?>
                                                <span class="pay-gcash"><i class="bi bi-phone-fill"></i> GCash</span>
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
                                            <?php if ($isOwner): ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        'use strict';
        const IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;

        // Expenses by date from PHP — available in JS for modal
        const EXP_BY_DATE = <?= json_encode($expByDate, JSON_UNESCAPED_UNICODE) ?>;

        function filterTable() {
            const q = document.getElementById('searchInput').value.toLowerCase().trim();
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            const pay = document.getElementById('payFilter').value;

            document.querySelectorAll('#salesBody tr[data-date]').forEach(tr => {
                const d = tr.dataset.date;
                const matchQ = !q || tr.dataset.search.includes(q);
                const matchD = (!from || d >= from) && (!to || d <= to);
                const matchP = !pay || tr.dataset.pay === pay;
                tr.style.display = matchQ && matchD && matchP ? '' : 'none';
            });
        }

        function resetFilter() {
            ['searchInput', 'dateFrom', 'dateTo'].forEach(id => document.getElementById(id).value = '');
            document.getElementById('payFilter').value = '';
            document.querySelectorAll('#salesBody tr').forEach(tr => tr.style.display = '');
        }

        document.getElementById('searchInput').addEventListener('input', filterTable);
        document.getElementById('payFilter').addEventListener('change', filterTable);

        function exportCSV() {
            const rows = [['ID', 'Date', 'Customer', 'Plate', 'Parts (₱)', 'Labor (₱)', 'Gross Total (₱)', 'Expenses (₱)', 'Net (₱)', 'Payment']];
            document.querySelectorAll('#salesBody tr[data-date]').forEach(tr => {
                if (tr.style.display === 'none') return;
                const cells = tr.querySelectorAll('td');
                rows.push([
                    cells[0].textContent.trim(),
                    cells[1].textContent.trim(),
                    cells[2].textContent.trim(),
                    cells[3].textContent.trim(),
                    cells[4].textContent.replace(/[₱,]/g, '').trim(),
                    cells[5].textContent.replace(/[₱,]/g, '').trim(),
                    cells[6].textContent.replace(/[₱,]/g, '').trim(),
                    (tr.dataset.exp || '0'),
                    (tr.dataset.net || '0'),
                    cells[9].textContent.trim(),
                ]);
            });
            const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
            const a = document.createElement('a');
            a.href = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
            a.download = 'sales_history_' + new Date().toISOString().slice(0, 10) + '.csv';
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

                const payBadge = pm === 'gcash'
                    ? `<span class="pay-gcash"><i class="bi bi-phone-fill"></i> GCash</span>`
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
            </div>

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
                        ${data.items.map(i => `
                        <tr>
                            <td><span class="${i.line_type === 'parts' ? 'badge-blue' : 'badge-green'}">${i.line_type}</span></td>
                            <td>${i.description || '—'}</td>
                            <td>${i.quantity}</td>
                            <td>₱${parseFloat(i.unit_price).toFixed(2)}</td>
                            <td>₱${parseFloat(i.amount).toFixed(2)}</td>
                        </tr>`).join('')}
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

        async function deleteSale(id, name) {
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
            const resp = await fetch('backend/sales.php?action=delete', { method: 'POST', body: fd });
            const data = await resp.json();

            if (data.success) {
                document.querySelector(`#salesBody tr[data-id="${id}"]`)?.remove();
                Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1200, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        }
    </script>
</body>

</html>