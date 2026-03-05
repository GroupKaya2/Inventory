<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$activePage = 'sales';

// Pre-load products for the dropdown
$products = [];
$r = $conn->query("SELECT product_id, code, description, selling_price, unit FROM product_stock ORDER BY description");
if ($r) while ($row = $r->fetch_assoc()) $products[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Sale – Dispeedway</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{
    --dark:#0f172a;--card:#1e293b;--border:rgba(255,255,255,.08);
    --text:#e2e8f0;--muted:#94a3b8;--orange:#f97316;
}
body{background:var(--dark);color:var(--text);}
.app-main{background:var(--dark);}

/* PAGE WRAPPER */
.sales-page{max-width:960px;margin:0 auto;}

/* HEADER */
.sales-header{
    background:linear-gradient(180deg,#111827 0%,#0b1220 100%);
    border:1px solid var(--border);border-radius:14px;
    padding:20px 24px;margin-bottom:20px;
    display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;
}
.sales-header h1{font-size:1.3rem;font-weight:700;margin:0;color:#fff;}
.sales-badge{background:rgba(59,130,246,.22);color:#93c5fd;padding:6px 12px;border-radius:8px;font-size:.8rem;font-weight:600;}

/* CARDS */
.sales-card{
    background:var(--card);border:1px solid var(--border);
    border-radius:14px;padding:20px;margin-bottom:18px;
}
.sales-card .form-label{color:var(--muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;}
.sales-card .form-control,
.sales-card .form-select{
    background:rgba(255,255,255,.06);border:1px solid var(--border);
    color:var(--text);border-radius:10px;height:44px;
}
.sales-card .form-control::placeholder{color:var(--muted);}
.sales-card .form-control:focus,
.sales-card .form-select:focus{
    background:rgba(255,255,255,.09);
    border-color:rgba(99,102,241,.5);
    color:var(--text);
    box-shadow:0 0 0 3px rgba(99,102,241,.15);
}
.sales-card .form-select option{background:#1e293b;color:var(--text);}

/* SECTION TITLE */
.section-title{
    font-size:.72rem;font-weight:700;text-transform:uppercase;
    letter-spacing:.5px;color:var(--muted);margin-bottom:14px;
}

/* ITEM ROW */
.item-row{
    display:grid;
    grid-template-columns:1fr 80px 110px 40px;
    gap:10px;align-items:end;margin-bottom:12px;
}
.item-row .form-control,
.item-row .form-select{height:42px;}
.item-label{font-size:.68rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px;}

@media(max-width:600px){
    .item-row{grid-template-columns:1fr 1fr;}
    .item-row .remove-col{grid-column:2;justify-self:end;}
}

/* REMOVE BUTTON */
.btn-remove-row{
    background:rgba(239,68,68,.18);color:#fca5a5;
    border:1px solid rgba(239,68,68,.3);border-radius:10px;
    width:40px;height:42px;padding:0;
    display:inline-flex;align-items:center;justify-content:center;
    cursor:pointer;
}
.btn-remove-row:hover{background:rgba(239,68,68,.32);color:#fff;}
/* hide remove on first row */
.item-row:first-child .btn-remove-row{visibility:hidden;}

/* ADD ITEM BUTTON */
.btn-add-item{
    background:rgba(255,255,255,.07);border:1px solid var(--border);
    color:var(--text);border-radius:10px;padding:10px 16px;font-weight:600;
}
.btn-add-item:hover{background:rgba(255,255,255,.12);color:#fff;}

/* TOTALS */
.totals-row{
    display:flex;flex-wrap:wrap;gap:24px;
    margin:16px 0 0;padding:14px 0 0;
    border-top:1px solid var(--border);
}
.totals-row .lbl{font-size:.82rem;color:var(--muted);}
.totals-row .val{color:#fff;font-size:1.05rem;font-weight:700;}

/* ACTION BUTTONS */
.btn-save{
    background:var(--orange);border:none;color:#111;
    padding:12px 24px;border-radius:10px;font-weight:700;
}
.btn-save:hover{background:#fb923c;color:#111;}
.btn-outline-dark-custom{
    background:rgba(255,255,255,.07);border:1px solid var(--border);
    color:var(--text);padding:12px 20px;border-radius:10px;font-weight:600;
}
.btn-outline-dark-custom:hover{background:rgba(255,255,255,.12);color:#fff;}

/* TYPE BADGE */
.type-parts{color:#60a5fa;}
.type-labor{color:#34d399;}

/* RECEIPT */
.receipt-print{display:none;background:#fff;color:#111;padding:24px;max-width:400px;margin:0 auto;font-family:monospace;}
@media print{
    body *{visibility:hidden;}
    .receipt-print,.receipt-print *{visibility:visible;}
    .receipt-print{display:block!important;position:absolute;left:0;top:0;}
}
</style>
</head>
<body>
<?php include "sidebar.php"; ?>

<main class="app-main p-3 p-md-4">
<div class="sales-page">

    <!-- HEADER -->
    <div class="sales-header">
        <h1><i class="bi bi-receipt me-2"></i>NEW SALE TRANSACTION</h1>
    </div>

    <!-- CUSTOMER INFO -->
    <div class="sales-card">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" id="saleDate" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Customer Name</label>
                <input type="text" class="form-control" id="customerName" placeholder="Customer Name">
            </div>
            <div class="col-md-4">
                <label class="form-label">Plate Number</label>
                <input type="text" class="form-control" id="plateNumber" placeholder="Plate Number">
            </div>
        </div>
    </div>

    <!-- LINE ITEMS -->
    <div class="sales-card">
        <div class="section-title"><i class="bi bi-list-ul me-1"></i>Items / Services</div>

        <!-- Column headers -->
        <div class="item-row" style="margin-bottom:6px;">
            <div class="item-label">Part / Service</div>
            <div class="item-label">Qty</div>
            <div class="item-label">Amount (₱)</div>
            <div></div>
        </div>

        <div id="itemsContainer">
            <!-- First row (template) -->
            <div class="item-row">
                <div>
                    <select class="form-select item-select">
                        <option value="">— Select Part or type Labor —</option>
                        <option value="labor" data-price="0" data-desc="Labor" data-type="labor">⚙️ Labor / Service (manual amount)</option>
                        <option disabled>──────────────────</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['product_id'] ?>"
                                data-price="<?= $p['selling_price'] ?>"
                                data-desc="<?= htmlspecialchars($p['description']) ?>"
                                data-type="parts">
                            <?= htmlspecialchars(($p['code'] ? $p['code'].' – ' : '') . $p['description']) ?>
                            (₱<?= number_format($p['selling_price'],2) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <input type="number" class="form-control item-qty" min="1" value="1">
                </div>
                <div>
                    <input type="number" class="form-control item-amount" min="0" step="0.01" value="0" placeholder="0.00">
                </div>
                <div>
                    <button type="button" class="btn-remove-row" title="Remove"><i class="bi bi-dash-lg"></i></button>
                </div>
            </div>
        </div>

        <button type="button" class="btn btn-add-item mt-2" id="addItemBtn">
            <i class="bi bi-plus-lg me-1"></i>Add Item
        </button>

        <!-- TOTALS -->
        <div class="totals-row">
            <div><span class="lbl">Parts Total: </span><span class="val" id="partsTotal">₱0.00</span></div>
            <div><span class="lbl">Labor Total: </span><span class="val" id="laborTotal">₱0.00</span></div>
            <div><span class="lbl">Grand Total: </span><span class="val text-warning" id="grandTotal">₱0.00</span></div>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <button type="button" class="btn btn-save" id="saveBtn">
            <i class="bi bi-save me-1"></i>Save Transaction
        </button>
        <button type="button" class="btn btn-outline-dark-custom" id="printBtn">
            <i class="bi bi-printer me-1"></i>Print Receipt
        </button>
        <button type="button" class="btn btn-outline-dark-custom" id="newBtn">
            <i class="bi bi-file-earmark-plus me-1"></i>New Work Order
        </button>
        <a href="sales_history.php" class="btn btn-outline-dark-custom ms-auto">
            <i class="bi bi-clock-history me-1"></i>View History
        </a>
    </div>

</div>

<!-- RECEIPT PRINT AREA (hidden, only shows on print) -->
<div id="receiptContent" class="receipt-print"></div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';

    // ── Products pre-loaded from PHP ──────────────────────────────
    const PRODUCTS = <?= json_encode($products) ?>;

    // ── Helpers ───────────────────────────────────────────────────
    function money(n) {
        return '₱' + (parseFloat(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function buildSelectOptions() {
        let html = '<option value="">— Select Part or type Labor —</option>';
        html += '<option value="labor" data-price="0" data-desc="Labor" data-type="labor">⚙️ Labor / Service (manual amount)</option>';
        html += '<option disabled>──────────────────</option>';
        PRODUCTS.forEach(p => {
            const label = (p.code ? p.code + ' – ' : '') + p.description + ' (₱' + parseFloat(p.selling_price).toFixed(2) + ')';
            html += `<option value="${p.product_id}" data-price="${p.selling_price}" data-desc="${p.description.replace(/"/g,'&quot;')}" data-type="parts">${label}</option>`;
        });
        return html;
    }

    // ── Add row ───────────────────────────────────────────────────
    function addRow() {
        const container = document.getElementById('itemsContainer');
        const div = document.createElement('div');
        div.className = 'item-row';
        div.innerHTML = `
            <div><select class="form-select item-select">${buildSelectOptions()}</select></div>
            <div><input type="number" class="form-control item-qty" min="1" value="1"></div>
            <div><input type="number" class="form-control item-amount" min="0" step="0.01" value="0" placeholder="0.00"></div>
            <div><button type="button" class="btn-remove-row" title="Remove"><i class="bi bi-dash-lg"></i></button></div>
        `;
        container.appendChild(div);
        updateFirstRowRemoveBtn();
    }

    function updateFirstRowRemoveBtn() {
        const rows = document.querySelectorAll('#itemsContainer .item-row');
        rows.forEach((row, i) => {
            const btn = row.querySelector('.btn-remove-row');
            if (btn) btn.style.visibility = i === 0 ? 'hidden' : 'visible';
        });
    }

    // ── Event delegation ──────────────────────────────────────────
    document.getElementById('itemsContainer').addEventListener('change', function (e) {
        const row = e.target.closest('.item-row');
        if (!row) return;

        if (e.target.classList.contains('item-select')) {
            const opt = e.target.options[e.target.selectedIndex];
            const type  = opt?.dataset?.type || 'parts';
            const price = parseFloat(opt?.dataset?.price || 0);
            const qty   = parseInt(row.querySelector('.item-qty').value, 10) || 1;
            const amtEl = row.querySelector('.item-amount');

            if (type === 'parts' && price > 0) {
                amtEl.value = (price * qty).toFixed(2);
            }
            updateTotals();
        }
    });

    document.getElementById('itemsContainer').addEventListener('input', function (e) {
        const row = e.target.closest('.item-row');
        if (!row) return;

        if (e.target.classList.contains('item-qty')) {
            const sel   = row.querySelector('.item-select');
            const opt   = sel?.options[sel.selectedIndex];
            const type  = opt?.dataset?.type || 'parts';
            const price = parseFloat(opt?.dataset?.price || 0);
            const qty   = parseInt(e.target.value, 10) || 1;

            if (type === 'parts' && price > 0) {
                row.querySelector('.item-amount').value = (price * qty).toFixed(2);
            }
        }
        updateTotals();
    });

    document.getElementById('itemsContainer').addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-remove-row');
        if (!btn) return;
        const rows = document.querySelectorAll('#itemsContainer .item-row');
        if (rows.length <= 1) return;
        btn.closest('.item-row').remove();
        updateFirstRowRemoveBtn();
        updateTotals();
    });

    document.getElementById('addItemBtn').addEventListener('click', addRow);

    // ── Totals ────────────────────────────────────────────────────
    function updateTotals() {
        let parts = 0, labor = 0;
        document.querySelectorAll('#itemsContainer .item-row').forEach(row => {
            const sel    = row.querySelector('.item-select');
            const opt    = sel?.options[sel.selectedIndex];
            const type   = opt?.dataset?.type || 'parts';
            const amount = parseFloat(row.querySelector('.item-amount')?.value) || 0;
            if (type === 'labor') labor += amount;
            else parts += amount;
        });
        document.getElementById('partsTotal').textContent = money(parts);
        document.getElementById('laborTotal').textContent = money(labor);
        document.getElementById('grandTotal').textContent = money(parts + labor);
    }

    // ── Build payload ─────────────────────────────────────────────
    function buildPayload() {
        const items = [];
        document.querySelectorAll('#itemsContainer .item-row').forEach(row => {
            const sel    = row.querySelector('.item-select');
            const opt    = sel?.options[sel.selectedIndex];
            const type   = opt?.dataset?.type || 'parts';
            const qty    = parseInt(row.querySelector('.item-qty')?.value, 10) || 1;
            const amount = parseFloat(row.querySelector('.item-amount')?.value) || 0;

            if (amount <= 0) return;

            if (type === 'labor') {
                items.push({ type: 'labor', product_id: null, description: 'Labor', quantity: 1, unit_price: amount, amount });
            } else {
                const pid   = parseInt(sel.value, 10);
                if (!pid) return;
                const price = parseFloat(opt?.dataset?.price || 0);
                const desc  = opt?.dataset?.desc || '';
                items.push({ type: 'parts', product_id: pid, description: desc, quantity: qty, unit_price: price, amount: price * qty || amount });
            }
        });
        return items;
    }

    // ── SAVE ─────────────────────────────────────────────────────
    document.getElementById('saveBtn').addEventListener('click', async function () {
        const items = buildPayload();
        if (!items.length) {
            Swal.fire({ icon: 'warning', title: 'No items', text: 'Add at least one part or labor item with an amount.' });
            return;
        }
        const saleDate = document.getElementById('saleDate').value;
        if (!saleDate) {
            Swal.fire({ icon: 'warning', title: 'Date required', text: 'Please select a transaction date.' });
            return;
        }

        this.disabled = true;
        this.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving…';

        try {
            const resp = await fetch('save_sale.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sale_date:     saleDate,
                    customer_name: document.getElementById('customerName').value,
                    plate_number:  document.getElementById('plateNumber').value,
                    items
                })
            });
            const data = await resp.json();

            if (data.success) {
                // Build stock deduction summary
                let stockHtml = '';
                if (data.stock_summary && data.stock_summary.length) {
                    stockHtml = '<div style="margin-top:12px;text-align:left;">'
                        + '<div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:8px;">📦 Stock Deducted</div>'
                        + '<table style="width:100%;font-size:.82rem;border-collapse:collapse;">'
                        + '<tr style="border-bottom:1px solid #e2e8f0;"><th style="padding:4px 6px;text-align:left;color:#475569;">Item</th><th style="padding:4px 6px;text-align:center;color:#ef4444;">Removed</th><th style="padding:4px 6px;text-align:center;color:#475569;">Stock Left</th></tr>';

                    data.stock_summary.forEach(s => {
                        const lowBadge = s.low_stock
                            ? '<span style="background:#fef3c7;color:#d97706;font-size:.65rem;padding:1px 6px;border-radius:6px;margin-left:4px;font-weight:700;">LOW</span>'
                            : '';
                        const stockColor = s.stock_left <= 0 ? '#ef4444' : (s.low_stock ? '#f59e0b' : '#10b981');
                        stockHtml += `<tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:5px 6px;">${s.description}</td>
                            <td style="padding:5px 6px;text-align:center;color:#ef4444;font-weight:700;">-${s.qty_removed}</td>
                            <td style="padding:5px 6px;text-align:center;font-weight:700;color:${stockColor};">${s.stock_left}${lowBadge}</td>
                        </tr>`;
                    });

                    stockHtml += '</table></div>';

                    // Check for any low stock warnings
                    const lowItems = data.stock_summary.filter(s => s.low_stock);
                    if (lowItems.length) {
                        stockHtml += `<div style="margin-top:10px;padding:8px 12px;background:#fef3c7;border-radius:8px;font-size:.78rem;color:#92400e;text-align:left;">
                            ⚠️ <strong>${lowItems.length} item(s) are running low!</strong> Consider restocking soon.
                        </div>`;
                    }
                }

                Swal.fire({
                    icon: 'success',
                    title: '✅ Sale Saved!',
                    html: `<div style="text-align:center;">
                        Transaction <strong>#${data.sale_id}</strong> recorded.<br>
                    </div>${stockHtml}`,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#10b981',
                    width: data.stock_summary && data.stock_summary.length ? 500 : 360
                });

                // Clear form after save
                document.getElementById('customerName').value = '';
                document.getElementById('plateNumber').value  = '';
                const container = document.getElementById('itemsContainer');
                while (container.children.length > 1) container.lastChild.remove();
                const firstRow = container.querySelector('.item-row');
                if (firstRow) {
                    firstRow.querySelector('.item-select').value = '';
                    firstRow.querySelector('.item-qty').value    = 1;
                    firstRow.querySelector('.item-amount').value = 0;
                }
                updateTotals();

            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to save.' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Network Error', text: err.message });
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-save me-1"></i>Save Transaction';
        }
    });

    // ── PRINT ────────────────────────────────────────────────────
    document.getElementById('printBtn').addEventListener('click', function () {
        let parts = 0, labor = 0;
        const lines = [];

        document.querySelectorAll('#itemsContainer .item-row').forEach(row => {
            const sel    = row.querySelector('.item-select');
            const opt    = sel?.options[sel.selectedIndex];
            const type   = opt?.dataset?.type || 'parts';
            const desc   = opt?.dataset?.desc || (type === 'labor' ? 'Labor' : 'Item');
            const qty    = parseInt(row.querySelector('.item-qty')?.value, 10) || 1;
            const amount = parseFloat(row.querySelector('.item-amount')?.value) || 0;
            if (amount <= 0) return;
            if (type === 'labor') labor += amount; else parts += amount;
            lines.push({ desc, qty, amount });
        });

        const date     = document.getElementById('saleDate').value;
        const customer = document.getElementById('customerName').value || '—';
        const plate    = document.getElementById('plateNumber').value || '—';

        document.getElementById('receiptContent').innerHTML = `
            <h4 style="text-align:center;border-bottom:2px solid #000;padding-bottom:8px;">DISPEEDWAY RECEIPT</h4>
            <p><strong>Date:</strong> ${date}</p>
            <p><strong>Customer:</strong> ${customer}</p>
            <p><strong>Plate:</strong> ${plate}</p>
            <hr>
            ${lines.map(l => `<div>${l.desc} x${l.qty} <span style="float:right">${money(l.amount)}</span></div>`).join('')}
            <hr>
            <p>Parts: <strong style="float:right">${money(parts)}</strong><br style="clear:both">
            Labor: <strong style="float:right">${money(labor)}</strong><br style="clear:both"></p>
            <p style="font-size:1.2em;border-top:2px solid #000;padding-top:6px;">
            Grand Total: <strong style="float:right">${money(parts + labor)}</strong></p>
            <p style="text-align:center;margin-top:16px;font-size:.85em;">Thank you for choosing Dispeedway!</p>
        `;
        document.getElementById('receiptContent').style.display = 'block';
        window.print();
        document.getElementById('receiptContent').style.display = 'none';
    });

    // ── NEW WORK ORDER (clear form) ───────────────────────────────
    document.getElementById('newBtn').addEventListener('click', function () {
        document.getElementById('customerName').value = '';
        document.getElementById('plateNumber').value  = '';
        document.getElementById('saleDate').value     = '<?= date('Y-m-d') ?>';

        const container = document.getElementById('itemsContainer');
        while (container.children.length > 1) container.lastChild.remove();
        const firstRow = container.querySelector('.item-row');
        if (firstRow) {
            firstRow.querySelector('.item-select').value  = '';
            firstRow.querySelector('.item-qty').value     = 1;
            firstRow.querySelector('.item-amount').value  = 0;
        }
        updateTotals();
        Swal.fire({ icon: 'info', title: 'Form Cleared', text: 'Ready for a new transaction.', timer: 1200, showConfirmButton: false });
    });

    // Init totals
    updateTotals();
})();
</script>
</body>
</html>