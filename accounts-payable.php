<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
    
}

$activePage = 'accounts-payable';
$isOwner    = ($_SESSION['role'] ?? 'manager') === 'owner';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Payable - DSpeedway</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .badge-unpaid { background: rgba(239,68,68,.15); color: #f87171; border: 1px solid rgba(239,68,68,.3); border-radius: 20px; padding: 4px 12px; font-size: .75rem; font-weight: 600; }
        .badge-paid   { background: rgba(74,222,128,.15); color: #4ade80; border: 1px solid rgba(74,222,128,.3); border-radius: 20px; padding: 4px 12px; font-size: .75rem; font-weight: 600; }
        .badge-overdue { background: rgba(251,191,36,.15); color: #fbbf24; border: 1px solid rgba(251,191,36,.3); border-radius: 20px; padding: 4px 12px; font-size: .75rem; font-weight: 600; }
        .kpi-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 18px 22px; }
        .kpi-label { font-size: .75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
        .kpi-value { font-size: 1.5rem; font-weight: 700; color: #e2e8f0; margin-top: 4px; }

        #addModal .modal-content,
        #editModal .modal-content {
            background: #161b27;
            color: #e2e8f0;
            border: 1px solid rgba(74,222,128,.18);
        }
        #addModal .modal-header,
        #editModal .modal-header {
            background: linear-gradient(135deg,#0f1f15,#111827);
            color: #4ade80;
            border-bottom: 1px solid rgba(74,222,128,.15);
        }
        #addModal .modal-footer,
        #editModal .modal-footer {
            border-top: 1px solid rgba(255,255,255,.08);
        }
        #addModal .form-label,
        #editModal .form-label {
            color: #94a3b8;
        }
        #addModal .form-input,
        #editModal .form-input,
        #addModal .form-select,
        #editModal .form-select,
        #addModal textarea.form-input,
        #editModal textarea.form-input {
            background: rgba(255,255,255,.05) !important;
            border: 1px solid rgba(255,255,255,.12) !important;
            color: #e2e8f0 !important;
        }
        #addModal .form-input::placeholder,
        #editModal .form-input::placeholder {
            color: #64748b !important;
        }
        #addModal .form-input[type="date"],
        #editModal .form-input[type="date"] {
            color-scheme: dark;
        }
        #addModal .form-select option,
        #editModal .form-select option {
            background: #1c2336;
            color: #e2e8f0;
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<main class="app-main">
    <div class="page-header mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <h4><i class="bi bi-receipt me-2"></i>Accounts Payable</h4>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($isOwner): ?>
            <button class="btn-ghost" id="selectModeBtn" onclick="toggleSelectMode()">
                <i class="bi bi-check2-square me-1"></i>Select
            </button>
            <button class="btn-pink" id="bulkDeleteBtn" style="background:linear-gradient(135deg,#dc2626,#7f1d1d);border-color:rgba(248,113,113,.4);color:#fca5a5;display:none;" onclick="bulkDeleteRecords()">
                <i class="bi bi-trash me-1"></i>Delete Selected (<span id="selCount">0</span>)
            </button>
            <?php endif; ?>
            <button class="btn-pink" onclick="openAddModal()">
                <i class="bi bi-plus-lg me-1"></i> Record Invoice
            </button>
        </div>
    </div>

    <!-- KPI Row -->
    <div class="row g-3 mb-4" id="kpiRow">
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Total Invoices</div>
                <div class="kpi-value" id="kpiTotal">—</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Total Amount</div>
                <div class="kpi-value" id="kpiAmount" style="color:#93c5fd;">—</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Balance Due</div>
                <div class="kpi-value" id="kpiBalance" style="color:#f87171;">—</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Unpaid</div>
                <div class="kpi-value" id="kpiUnpaid" style="color:#fbbf24;">—</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="flex-grow-1">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="searchInput" placeholder="Search supplier or invoice number…">
                    </div>
                </div>
                <select class="form-select" id="statusFilter" style="max-width:160px;">
                    <option value="">All Status</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="paid">Paid</option>
                </select>
                <button class="btn-ghost" id="clearFilterBtn">
                    <i class="bi bi-x-circle me-1"></i> Clear
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <?php if ($isOwner): ?>
                            <th id="checkboxColHead" style="display:none;width:36px;">
                                <input type="checkbox" id="selectAllAp" onchange="toggleSelectAllAp(this)">
                            </th>
                            <?php endif; ?>
                            <th>#</th>
                            <th>Supplier / Vendor</th>
                            <th>Invoice No.</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th>Mode of Payment</th>
                            <th>Total (₱)</th>
                            <th>Paid (₱)</th>
                            <th>Balance (₱)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="apTableBody">
                        <tr><td colspan="<?= $isOwner ? 12 : 11 ?>" style="text-align:center;padding:30px;"><div class="spinner-border" style="color:#4ade80;"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modal-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Record Supplier Invoice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Supplier / Vendor Name *</label>
                        <input type="text" class="form-input" id="addSupplier" placeholder="e.g. Shell Philippines">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Invoice Number</label>
                        <input type="text" class="form-input" id="addInvoiceNum" placeholder="e.g. INV-2026-001">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Invoice Date *</label>
                        <input type="date" class="form-input" id="addInvoiceDate">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Due Date</label>
                        <input type="date" class="form-input" id="addDueDate">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mode of Payment</label>
                        <select class="form-input form-select" id="addMop">
                            <option value="credit">Credit</option>
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="check">Check</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Total Amount (₱) *</label>
                        <input type="number" class="form-input" id="addTotal" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount Paid (₱)</label>
                        <input type="number" class="form-input" id="addPaid" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Balance Due (auto)</label>
                        <div class="form-input" id="addBalance" style="color:#f87171;font-weight:700;">₱0.00</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea class="form-input" id="addNotes" rows="2" placeholder="Optional notes…"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-pink" id="submitAdd">Save Invoice</button>
            </div>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modal-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Invoice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Supplier / Vendor Name *</label>
                        <input type="text" class="form-input" id="editSupplier">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Invoice Number</label>
                        <input type="text" class="form-input" id="editInvoiceNum">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Invoice Date *</label>
                        <input type="date" class="form-input" id="editInvoiceDate">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Due Date</label>
                        <input type="date" class="form-input" id="editDueDate">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mode of Payment</label>
                        <select class="form-input form-select" id="editMop">
                            <option value="credit">Credit</option>
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="check">Check</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Total Amount (₱) *</label>
                        <input type="number" class="form-input" id="editTotal" min="0" step="0.01">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount Paid (₱)</label>
                        <input type="number" class="form-input" id="editPaid" min="0" step="0.01">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Balance Due (auto)</label>
                        <div class="form-input" id="editBalance" style="color:#f87171;font-weight:700;">₱0.00</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea class="form-input" id="editNotes" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-pink" id="submitEdit">Update Invoice</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
'use strict';

const IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;
let allRecords = [];

function peso(n) {
    return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function isOverdue(row) {
    if (row.status === 'paid' || !row.due_date) return false;
    return new Date(row.due_date) < new Date();
}

function statusBadge(row) {
    if (row.status === 'paid')    return '<span class="badge-paid">✔ Paid</span>';
    if (isOverdue(row))           return '<span class="badge-overdue">⚠ Overdue</span>';
    return '<span class="badge-unpaid">● Unpaid</span>';
}

function mopLabel(m) {
    const map = { cash:'Cash', gcash:'GCash', credit:'Credit', bank_transfer:'Bank Transfer', check:'Check' };
    return map[m] || m;
}

async function loadData() {
    const search = document.getElementById('searchInput').value.trim();
    const status = document.getElementById('statusFilter').value;
    const params = new URLSearchParams({ action: 'fetch', search, status });
    const tbody  = document.getElementById('apTableBody');
    const colCount = IS_OWNER ? 12 : 11;
    tbody.innerHTML = `<tr><td colspan="${colCount}" style="text-align:center;padding:30px;"><div class="spinner-border" style="color:#4ade80;"></div></td></tr>`;

    try {
        const res  = await fetch('backend/accounts-payable.php?' + params);
        const json = await res.json();
        if (!json.success) { tbody.innerHTML = `<tr><td colspan="${colCount}" style="text-align:center;color:#fca5a5;padding:24px;">⚠ ${json.message}</td></tr>`; return; }

        allRecords = json.data || [];
        updateKpis(json.totals);

        if (!allRecords.length) {
            tbody.innerHTML = `<tr><td colspan="${colCount}" style="text-align:center;color:#7a8499;padding:30px;">No invoices found.</td></tr>`;
            return;
        }

        tbody.innerHTML = allRecords.map((r, i) => `
            <tr>
                ${IS_OWNER ? `<td class="checkbox-col" style="display:none;"><input type="checkbox" class="ap-row-check" value="${r.id}" onchange="updateApSelCount()"></td>` : ''}
                <td><span class="badge-gray">${i + 1}</span></td>
                <td style="font-weight:600;color:#e2e8f0;">${r.supplier_name}</td>
                <td>${r.invoice_number || '—'}</td>
                <td>${r.invoice_date}</td>
                <td style="color:${isOverdue(r) ? '#fbbf24' : '#e2e8f0'}">${r.due_date || '—'}</td>
                <td>${mopLabel(r.mode_of_payment)}</td>
                <td style="color:#93c5fd;">${peso(r.total_amount)}</td>
                <td style="color:#4ade80;">${peso(r.amount_paid)}</td>
                <td style="color:#f87171;font-weight:700;">${peso(r.balance_due)}</td>
                <td>${statusBadge(r)}</td>
                <td>
                    <div class="d-flex gap-1">
                        ${r.status === 'unpaid' ? `<button class="btn btn-sm btn-outline-success" onclick="markPaid(${r.id})" title="Mark as Paid"><i class="bi bi-check-circle"></i></button>` : ''}
                        <button class="btn btn-sm btn-outline-warning" onclick="openEdit(${r.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                        ${IS_OWNER ? `<button class="btn btn-sm btn-outline-danger" onclick="deleteRecord(${r.id})" title="Delete"><i class="bi bi-trash"></i></button>` : ''}
                    </div>
                </td>
            </tr>`).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="${colCount}" style="text-align:center;color:#fca5a5;padding:24px;">⚠ Network error: ${e.message}</td></tr>`;
    }
}

function updateKpis(t) {
    if (!t) return;
    document.getElementById('kpiTotal').textContent   = t.total_records || 0;
    document.getElementById('kpiAmount').textContent  = peso(t.total_amount);
    document.getElementById('kpiBalance').textContent = peso(t.total_balance);
    document.getElementById('kpiUnpaid').textContent  = t.unpaid_count || 0;
}

function updateBalance(prefix) {
    const total   = parseFloat(document.getElementById(prefix + 'Total').value) || 0;
    const paid    = parseFloat(document.getElementById(prefix + 'Paid').value) || 0;
    const balance = Math.max(0, total - paid);
    document.getElementById(prefix + 'Balance').textContent = peso(balance);
}

function openAddModal() {
    document.getElementById('addSupplier').value    = '';
    document.getElementById('addInvoiceNum').value  = '';
    document.getElementById('addInvoiceDate').value = new Date().toISOString().slice(0,10);
    document.getElementById('addDueDate').value     = '';
    document.getElementById('addMop').value         = 'credit';
    document.getElementById('addTotal').value       = '0';
    document.getElementById('addPaid').value        = '0';
    document.getElementById('addNotes').value       = '';
    updateBalance('add');
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

async function openEdit(id) {
    try {
        const res  = await fetch('backend/accounts-payable.php?action=get&id=' + id);
        const json = await res.json();
        if (!json.success) { Swal.fire({ icon:'error', title:'Error', text: json.message }); return; }
        const r = json.data;
        document.getElementById('editId').value          = r.id;
        document.getElementById('editSupplier').value    = r.supplier_name;
        document.getElementById('editInvoiceNum').value  = r.invoice_number || '';
        document.getElementById('editInvoiceDate').value = r.invoice_date;
        document.getElementById('editDueDate').value     = r.due_date || '';
        document.getElementById('editMop').value         = r.mode_of_payment;
        document.getElementById('editTotal').value       = r.total_amount;
        document.getElementById('editPaid').value        = r.amount_paid;
        document.getElementById('editNotes').value       = r.notes || '';
        updateBalance('edit');
        new bootstrap.Modal(document.getElementById('editModal')).show();
    } catch (e) {
        Swal.fire({ icon:'error', title:'Network Error', text: e.message });
    }
}

async function markPaid(id) {
    const result = await Swal.fire({
        title: 'Mark as Paid?',
        text: 'This will set the full amount as paid and mark the invoice as settled.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        confirmButtonText: 'Yes, Mark Paid',
    });
    if (!result.isConfirmed) return;
    const fd = new FormData();
    fd.append('id', id);
    const res  = await fetch('backend/accounts-payable.php?action=mark_paid', { method:'POST', body: fd });
    const json = await res.json();
    if (json.success) {
        Swal.fire({ icon:'success', title:'Marked as Paid!', timer:1400, showConfirmButton:false });
        loadData();
    } else {
        Swal.fire({ icon:'error', title:'Error', text: json.message });
    }
}

let selectModeAp = false;

function toggleSelectMode() {
    selectModeAp = !selectModeAp;
    document.getElementById('checkboxColHead').style.display = selectModeAp ? '' : 'none';
    document.querySelectorAll('#apTableBody .checkbox-col').forEach(td => td.style.display = selectModeAp ? '' : 'none');
    document.getElementById('selectModeBtn').innerHTML = selectModeAp
        ? '<i class="bi bi-x-lg me-1"></i>Cancel'
        : '<i class="bi bi-check2-square me-1"></i>Select';
    if (!selectModeAp) {
        document.querySelectorAll('.ap-row-check').forEach(cb => cb.checked = false);
        document.getElementById('selectAllAp').checked = false;
        document.getElementById('bulkDeleteBtn').style.display = 'none';
    }
    updateApSelCount();
}

function toggleSelectAllAp(masterCb) {
    document.querySelectorAll('.ap-row-check').forEach(cb => cb.checked = masterCb.checked);
    updateApSelCount();
}

function updateApSelCount() {
    const checked = document.querySelectorAll('.ap-row-check:checked').length;
    document.getElementById('selCount').textContent = checked;
    document.getElementById('bulkDeleteBtn').style.display = checked > 0 ? '' : 'none';
}

async function bulkDeleteRecords() {
    const ids = Array.from(document.querySelectorAll('.ap-row-check:checked')).map(cb => cb.value);
    if (!ids.length) return;

    const result = await Swal.fire({
        title: `Delete ${ids.length} invoice${ids.length > 1 ? 's' : ''}?`,
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Delete All',
    });
    if (!result.isConfirmed) return;

    const fd = new FormData();
    ids.forEach(id => fd.append('ids[]', id));
    const res  = await fetch('backend/accounts-payable.php?action=bulk_delete', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
        Swal.fire({ icon: 'success', title: `${json.deleted} deleted`, timer: 1400, showConfirmButton: false });
        selectModeAp = true; // stay collapsed after reload rather than re-toggling weirdly
        toggleSelectMode();
        loadData();
    } else {
        Swal.fire({ icon: 'error', title: 'Error', text: json.message });
    }
}

async function deleteRecord(id) {
    const result = await Swal.fire({
        title: 'Delete this invoice?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Delete',
    });
    if (!result.isConfirmed) return;
    const fd = new FormData();
    fd.append('id', id);
    const res  = await fetch('backend/accounts-payable.php?action=delete', { method:'POST', body: fd });
    const json = await res.json();
    if (json.success) {
        Swal.fire({ icon:'success', title:'Deleted!', timer:1200, showConfirmButton:false });
        loadData();
    } else {
        Swal.fire({ icon:'error', title:'Error', text: json.message });
    }
}

document.getElementById('addTotal').addEventListener('input', () => updateBalance('add'));
document.getElementById('addPaid').addEventListener('input',  () => updateBalance('add'));
document.getElementById('editTotal').addEventListener('input', () => updateBalance('edit'));
document.getElementById('editPaid').addEventListener('input',  () => updateBalance('edit'));

document.getElementById('submitAdd').addEventListener('click', async () => {
    const btn      = document.getElementById('submitAdd');
    const supplier = document.getElementById('addSupplier').value.trim();
    const total    = parseFloat(document.getElementById('addTotal').value) || 0;
    if (!supplier) { Swal.fire({ icon:'warning', title:'Required', text:'Supplier name is required.' }); return; }
    if (total <= 0) { Swal.fire({ icon:'warning', title:'Required', text:'Total amount must be greater than 0.' }); return; }

    btn.disabled    = true;
    btn.textContent = 'Saving…';
    try {
        const res  = await fetch('backend/accounts-payable.php?action=add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                supplier_name:   supplier,
                invoice_number:  document.getElementById('addInvoiceNum').value.trim(),
                invoice_date:    document.getElementById('addInvoiceDate').value,
                due_date:        document.getElementById('addDueDate').value,
                mode_of_payment: document.getElementById('addMop').value,
                total_amount:    parseFloat(document.getElementById('addTotal').value) || 0,
                amount_paid:     parseFloat(document.getElementById('addPaid').value) || 0,
                notes:           document.getElementById('addNotes').value.trim(),
            })
        });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('addModal'))?.hide();
            Swal.fire({ icon:'success', title:'Invoice Recorded!', timer:1400, showConfirmButton:false });
            loadData();
        } else {
            Swal.fire({ icon:'error', title:'Error', text: json.message });
        }
    } catch (e) {
        Swal.fire({ icon:'error', title:'Network Error', text: e.message });
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Save Invoice';
    }
});

document.getElementById('submitEdit').addEventListener('click', async () => {
    const btn   = document.getElementById('submitEdit');
    const id    = document.getElementById('editId').value;
    const total = parseFloat(document.getElementById('editTotal').value) || 0;
    if (total <= 0) { Swal.fire({ icon:'warning', title:'Required', text:'Total amount must be greater than 0.' }); return; }

    btn.disabled    = true;
    btn.textContent = 'Saving…';
    try {
        const res  = await fetch('backend/accounts-payable.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id,
                supplier_name:   document.getElementById('editSupplier').value.trim(),
                invoice_number:  document.getElementById('editInvoiceNum').value.trim(),
                invoice_date:    document.getElementById('editInvoiceDate').value,
                due_date:        document.getElementById('editDueDate').value,
                mode_of_payment: document.getElementById('editMop').value,
                total_amount:    parseFloat(document.getElementById('editTotal').value) || 0,
                amount_paid:     parseFloat(document.getElementById('editPaid').value) || 0,
                notes:           document.getElementById('editNotes').value.trim(),
            })
        });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide();
            Swal.fire({ icon:'success', title:'Updated!', timer:1400, showConfirmButton:false });
            loadData();
        } else {
            Swal.fire({ icon:'error', title:'Error', text: json.message });
        }
    } catch (e) {
        Swal.fire({ icon:'error', title:'Network Error', text: e.message });
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Update Invoice';
    }
});

document.getElementById('searchInput').addEventListener('input', () => loadData());
document.getElementById('statusFilter').addEventListener('change', () => loadData());
document.getElementById('clearFilterBtn').addEventListener('click', () => {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    loadData();
});

loadData();
</script>
</body>
</html>