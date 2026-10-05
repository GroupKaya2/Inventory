<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$activePage = 'accounts_receivable';
$isOwner    = ($_SESSION['role'] ?? 'manager') === 'owner';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Receivable - DSpeedway</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .ar-tabs { display: flex; gap: 4px; margin-bottom: 18px; border-bottom: 1px solid rgba(255,255,255,.08); }
        .ar-tab { padding: 10px 18px; font-size: .85rem; font-weight: 600; color: #64748b; background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; }
        .ar-tab:hover { color: #e2e8f0; }
        .ar-tab.active { color: #4ade80; border-bottom-color: #4ade80; }
        .ar-panel { display: none; }
        .ar-panel.active { display: block; }

        .filter-bar { background: #111827; border: 1px solid rgba(255,255,255,.06); border-radius: 10px; padding: 12px 16px; margin-bottom: 14px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .filter-bar select, .filter-bar input { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); color: #e2e8f0; border-radius: 8px; height: 36px; font-size: .8rem; padding: 0 10px; }
        .filter-bar select option { background: #1c2336; }

        .status-unpaid { background: rgba(248,113,113,.12); color: #f87171; padding: 3px 10px; border-radius: 6px; font-size: .7rem; font-weight: 700; }
        .status-partial { background: rgba(251,191,36,.12); color: #fbbf24; padding: 3px 10px; border-radius: 6px; font-size: .7rem; font-weight: 700; }
        .status-paid { background: rgba(74,222,128,.12); color: #4ade80; padding: 3px 10px; border-radius: 6px; font-size: .7rem; font-weight: 700; }

        #payModal .modal-content, #historyModal .modal-content {
            background: #161b27; border: 1px solid rgba(74,222,128,.15); color: #e2e8f0;
        }
        #payModal .modal-header, #historyModal .modal-header {
            background: linear-gradient(135deg,#0f1f15,#111827); border-bottom: 1px solid rgba(74,222,128,.15);
        }
        #payModal .form-label, #historyModal .form-label {
            font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; margin-bottom: 6px; display: block;
        }
        #payModal .form-input {
            width: 100%; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1); color: #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: .85rem;
        }
        #payModal .form-input:focus { outline: none; border-color: rgba(74,222,128,.4); background: rgba(74,222,128,.04); }
        .balance-hint { font-size: .78rem; color: #94a3b8; margin-top: 4px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="app-main">

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4><i class="bi bi-cash-stack me-2" style="color:#4ade80;"></i>Accounts Receivable</h4>
            <p>What customers owe you — pulled automatically from Credit and Split-payment sales.</p>
        </div>
        <button class="btn-ghost" onclick="exportCSV()"><i class="bi bi-download me-1"></i>Export CSV</button>
    </div>

    <!-- KPI Row -->
    <div class="row g-3 mb-4" id="kpiRow">
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon red"><i class="bi bi-hourglass-split"></i></div>
                <div><div class="kpi-label">Total Outstanding</div><div class="kpi-value" id="kpiBalance">₱0.00</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon green"><i class="bi bi-check-circle"></i></div>
                <div><div class="kpi-label">Total Collected</div><div class="kpi-value" id="kpiCollected">₱0.00</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon orange"><i class="bi bi-exclamation-circle"></i></div>
                <div><div class="kpi-label">Unpaid Invoices</div><div class="kpi-value" id="kpiUnpaid">0</div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-icon blue"><i class="bi bi-hash"></i></div>
                <div><div class="kpi-label">Total Owed (Gross)</div><div class="kpi-value" id="kpiOwed">₱0.00</div></div>
            </div>
        </div>
    </div>

    <div class="ar-tabs">
        <button class="ar-tab active" data-tab="invoices" onclick="switchTab('invoices')">
            <i class="bi bi-receipt me-1"></i> Outstanding Invoices
        </button>
        <button class="ar-tab" data-tab="payments" onclick="switchTab('payments')">
            <i class="bi bi-clock-history me-1"></i> Payment History
        </button>
    </div>

    <!-- OUTSTANDING INVOICES -->
    <div class="ar-panel active" id="panel-invoices">
        <div class="filter-bar">
            <input type="text" id="searchInput" placeholder="Search customer, ref #, plate…" style="min-width:220px;" oninput="loadInvoices()">
            <select id="statusFilter" onchange="loadInvoices()">
                <option value="">All Status</option>
                <option value="unpaid">Unpaid</option>
                <option value="partial">Partial</option>
                <option value="paid">Paid</option>
            </select>
            <button class="btn-ghost" style="font-size:.8rem;padding:6px 14px;" onclick="resetFilters()">Reset</button>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th><th>Customer</th><th>Ref #</th><th>Sale Date</th>
                            <th>Payment Type</th><th>Amount Owed</th><th>Paid</th><th>Balance</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="invoicesBody">
                        <tr><td colspan="10" style="text-align:center;padding:30px;"><div class="spinner-border" style="color:#4ade80;"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PAYMENT HISTORY (all customers) -->
    <div class="ar-panel" id="panel-payments">
        <div class="filter-bar">
            <input type="date" id="payDateFrom" onchange="loadAllPayments()">
            <span style="color:#64748b;font-size:.8rem;">to</span>
            <input type="date" id="payDateTo" onchange="loadAllPayments()">
            <button class="btn-ghost" style="font-size:.8rem;padding:6px 14px;" onclick="resetPaymentFilters()">Reset</button>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th><th>Date</th><th>Customer</th><th>Invoice Ref</th>
                            <th>Amount Paid</th><th>Mode</th><th>Reference #</th><th>Collected By</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsBody">
                        <tr><td colspan="8" style="text-align:center;padding:30px;"><div class="spinner-border" style="color:#4ade80;"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<!-- Record Payment modal -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="color:#4ade80;"><i class="bi bi-cash me-2"></i>Record Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="paySaleId">
                <p style="font-size:.85rem;">
                    Customer: <strong id="payCustomerName" style="color:#4ade80;"></strong><br>
                    Outstanding Balance: <strong id="payBalanceDisplay" style="color:#f87171;"></strong>
                </p>
                <div class="mb-3">
                    <label class="form-label">Amount Paid (₱) *</label>
                    <input type="number" class="form-input" id="payAmount" min="0" step="0.01">
                    <div class="balance-hint" id="payRemainingHint"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mode of Payment</label>
                    <select class="form-input" id="payMode">
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="online_transfer">Online Transfer</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Date</label>
                    <input type="date" class="form-input" id="payDate">
                </div>
                <div class="mb-3">
                    <label class="form-label">Reference Number</label>
                    <input type="text" class="form-input" id="payRefNo" placeholder="Receipt / transaction / GCash ref no.">
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea class="form-input" id="payNotes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="paySubmit" style="background:linear-gradient(135deg,#22c55e,#16a34a);border:none;color:#fff;padding:9px 20px;border-radius:50px;font-weight:700;cursor:pointer;">
                    Save Payment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Payment History (per-invoice) modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="color:#4ade80;"><i class="bi bi-clock-history me-2"></i>Payment History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="historyModalBody"></div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
'use strict';

let allInvoices = [];
document.getElementById('payDate').value = new Date().toISOString().slice(0, 10);

function esc(s) { return String(s || '').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
function peso(n) { return '₱' + (parseFloat(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function fmtDate(s) { if (!s) return '—'; const d = new Date(s); return isNaN(d) ? s : d.toLocaleDateString('en-PH', { dateStyle: 'medium' }); }

function switchTab(tab) {
    document.querySelectorAll('.ar-tab').forEach(el => el.classList.toggle('active', el.dataset.tab === tab));
    document.querySelectorAll('.ar-panel').forEach(el => el.classList.remove('active'));
    document.getElementById('panel-' + tab).classList.add('active');
    if (tab === 'payments') loadAllPayments();
}

function paymentTypeLabel(pm) {
    if (pm === 'split') return '<span style="color:#fbbf24;"><i class="bi bi-arrow-left-right"></i> Split (credit portion)</span>';
    return '<span style="color:#f87171;"><i class="bi bi-credit-card"></i> Full Credit</span>';
}

function statusBadge(status) {
    return `<span class="status-${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}

async function loadInvoices() {
    const tbody = document.getElementById('invoicesBody');
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:30px;"><div class="spinner-border" style="color:#4ade80;"></div></td></tr>';

    const params = new URLSearchParams({
        action: 'fetch',
        search: document.getElementById('searchInput').value.trim(),
        status: document.getElementById('statusFilter').value,
    });

    try {
        const res = await fetch('backend/accounts-receivable.php?' + params);
        const json = await res.json();
        if (!json.success) { tbody.innerHTML = `<tr><td colspan="10" style="text-align:center;color:#fca5a5;padding:24px;">⚠ ${json.message}</td></tr>`; return; }

        allInvoices = json.data;
        updateKpis(json.totals);

        if (!allInvoices.length) {
            tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#7a8499;padding:30px;">No credit sales found.</td></tr>';
            return;
        }

        tbody.innerHTML = allInvoices.map((r, i) => `
            <tr>
                <td><span class="badge-gray">${i + 1}</span></td>
                <td style="font-weight:600;">${esc(r.customer_name || 'Walk-in')}</td>
                <td>${esc(r.invoice_number || '—')}</td>
                <td>${fmtDate(r.sale_date)}</td>
                <td>${paymentTypeLabel(r.payment_method)}</td>
                <td>${peso(r.amount_owed)}</td>
                <td style="color:#4ade80;">${peso(r.amount_paid)}</td>
                <td style="color:#f87171;font-weight:700;">${peso(r.balance_due)}</td>
                <td>${statusBadge(r.status)}</td>
                <td>
                    <div class="d-flex gap-1">
                        ${r.status !== 'paid' ? `<button class="btn btn-sm btn-outline-success" onclick="openPayModal(${r.id}, '${esc(r.customer_name)}', ${r.balance_due})" title="Record Payment"><i class="bi bi-cash"></i></button>` : ''}
                        <button class="btn btn-sm btn-outline-info" onclick="viewHistory(${r.id}, '${esc(r.customer_name)}')" title="Payment History"><i class="bi bi-clock-history"></i></button>
                    </div>
                </td>
            </tr>`).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="10" style="text-align:center;color:#fca5a5;padding:24px;">⚠ ${e.message}</td></tr>`;
    }
}

function updateKpis(totals) {
    document.getElementById('kpiBalance').textContent = peso(totals.total_balance);
    document.getElementById('kpiCollected').textContent = peso(totals.total_collected);
    document.getElementById('kpiUnpaid').textContent = totals.unpaid_count;
    document.getElementById('kpiOwed').textContent = peso(totals.total_owed);
}

function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    loadInvoices();
}

function openPayModal(saleId, customerName, balance) {
    document.getElementById('paySaleId').value = saleId;
    document.getElementById('payCustomerName').textContent = customerName || 'Walk-in';
    document.getElementById('payBalanceDisplay').textContent = peso(balance);
    document.getElementById('payAmount').value = '';
    document.getElementById('payAmount').max = balance;
    document.getElementById('payMode').value = 'cash';
    document.getElementById('payDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('payRefNo').value = '';
    document.getElementById('payNotes').value = '';
    document.getElementById('payRemainingHint').textContent = `Balance: ${peso(balance)}`;
    new bootstrap.Modal(document.getElementById('payModal')).show();
}

document.getElementById('payAmount').addEventListener('input', function () {
    const balance = parseFloat(document.getElementById('payAmount').max) || 0;
    const val = parseFloat(this.value) || 0;
    const remaining = balance - val;
    const hint = document.getElementById('payRemainingHint');
    if (val > balance) {
        hint.style.color = '#f87171';
        hint.textContent = `⚠ Exceeds balance of ${peso(balance)}`;
    } else {
        hint.style.color = '#94a3b8';
        hint.textContent = `Remaining after this payment: ${peso(remaining)}`;
    }
});

document.getElementById('paySubmit').addEventListener('click', async () => {
    const btn = document.getElementById('paySubmit');
    const saleId = document.getElementById('paySaleId').value;
    const amount = parseFloat(document.getElementById('payAmount').value) || 0;

    if (amount <= 0) {
        Swal.fire({ icon: 'warning', title: 'Enter a valid amount.' });
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Saving…';
    try {
        const res = await fetch('backend/accounts-receivable.php?action=record_payment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sale_id: saleId,
                amount_paid: amount,
                mode_of_payment: document.getElementById('payMode').value,
                payment_date: document.getElementById('payDate').value,
                reference_number: document.getElementById('payRefNo').value.trim(),
                notes: document.getElementById('payNotes').value.trim(),
            }),
        });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('payModal'))?.hide();
            await Swal.fire({ icon: 'success', title: 'Payment Recorded', html: `New balance: <strong>${peso(json.new_balance)}</strong>`, timer: 1800, showConfirmButton: false });
            loadInvoices();
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: json.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network error', text: e.message });
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save Payment';
    }
});

async function viewHistory(saleId, customerName) {
    const body = document.getElementById('historyModalBody');
    body.innerHTML = '<div style="text-align:center;padding:20px;"><div class="spinner-border" style="color:#4ade80;"></div></div>';
    new bootstrap.Modal(document.getElementById('historyModal')).show();

    try {
        const res = await fetch(`backend/accounts-receivable.php?action=payment_history&sale_id=${saleId}`);
        const json = await res.json();
        if (!json.success) { body.innerHTML = `<p style="color:#fca5a5;">⚠ ${json.message}</p>`; return; }

        if (!json.data.length) {
            body.innerHTML = `<p style="color:#7a8499;text-align:center;padding:20px;">No payments recorded yet for ${esc(customerName)}.</p>`;
            return;
        }

        body.innerHTML = `
            <table class="data-table">
                <thead><tr><th>Date</th><th>Amount</th><th>Mode</th><th>Reference #</th><th>Collected By</th></tr></thead>
                <tbody>
                    ${json.data.map(p => `
                        <tr>
                            <td>${fmtDate(p.payment_date)}</td>
                            <td style="color:#4ade80;font-weight:600;">${peso(p.amount_paid)}</td>
                            <td>${esc(p.mode_of_payment)}</td>
                            <td>${esc(p.reference_number || '—')}</td>
                            <td>${esc(p.collected_by || '—')}</td>
                        </tr>`).join('')}
                </tbody>
            </table>`;
    } catch (e) {
        body.innerHTML = `<p style="color:#fca5a5;">⚠ ${e.message}</p>`;
    }
}

async function loadAllPayments() {
    const tbody = document.getElementById('paymentsBody');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;"><div class="spinner-border" style="color:#4ade80;"></div></td></tr>';

    const params = new URLSearchParams({ action: 'all_payments' });
    const from = document.getElementById('payDateFrom').value;
    const to = document.getElementById('payDateTo').value;
    if (from) params.append('date_from', from);
    if (to) params.append('date_to', to);

    try {
        const res = await fetch('backend/accounts-receivable.php?' + params);
        const json = await res.json();
        if (!json.success) { tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:#fca5a5;padding:24px;">⚠ ${json.message}</td></tr>`; return; }

        if (!json.data.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#7a8499;padding:30px;">No payments found.</td></tr>';
            return;
        }

        tbody.innerHTML = json.data.map((p, i) => `
            <tr>
                <td><span class="badge-gray">${i + 1}</span></td>
                <td>${fmtDate(p.payment_date)}</td>
                <td style="font-weight:600;">${esc(p.customer_name || 'Walk-in')}</td>
                <td>${esc(p.invoice_number || '—')}</td>
                <td style="color:#4ade80;font-weight:600;">${peso(p.amount_paid)}</td>
                <td>${esc(p.mode_of_payment)}</td>
                <td>${esc(p.reference_number || '—')}</td>
                <td>${esc(p.collected_by || '—')}</td>
            </tr>`).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;color:#fca5a5;padding:24px;">⚠ ${e.message}</td></tr>`;
    }
}

function resetPaymentFilters() {
    document.getElementById('payDateFrom').value = '';
    document.getElementById('payDateTo').value = '';
    loadAllPayments();
}

function exportCSV() {
    if (!allInvoices.length) { Swal.fire({ icon: 'info', title: 'Nothing to export' }); return; }
    const headers = ['Customer', 'Ref #', 'Sale Date', 'Payment Type', 'Amount Owed', 'Paid', 'Balance', 'Status'];
    const rows = allInvoices.map(r => [
        r.customer_name || 'Walk-in', r.invoice_number || '', r.sale_date,
        r.payment_method === 'split' ? 'Split (credit portion)' : 'Full Credit',
        r.amount_owed.toFixed(2), r.amount_paid.toFixed(2), r.balance_due.toFixed(2), r.status,
    ]);
    let csv = headers.join(',') + '\n' + rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `accounts_receivable_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

loadInvoices();
</script>

</body>
</html>