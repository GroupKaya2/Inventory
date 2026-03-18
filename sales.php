<?php
// sales.php — New Sale Transaction Page

session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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
    <title>New Sale — Dispeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        /* ── Sales Card ── */
        .sale-card {
            background: #161921;
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 18px;
        }

        /* ── Form inputs ── */
        .sale-card .form-control,
        .sale-card .form-select {
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            color: #e8ecf4;
            border-radius: 10px;
            height: 42px;
            font-size: .85rem;
        }
        .sale-card .form-control::placeholder { color: #7a8499; }
        .sale-card .form-select option { background: #1c2030; }
        .sale-card .form-control:focus,
        .sale-card .form-select:focus {
            border-color: rgba(232,23,93,.5);
            box-shadow: 0 0 0 3px rgba(232,23,93,.12);
            background: rgba(255,255,255,.09);
            color: #fff;
        }
        .sale-card .form-label {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #7a8499;
            margin-bottom: 5px;
        }

        /* ── Item Row Grid ── */
        .item-row {
            display: grid;
            grid-template-columns: 1fr 80px 120px 42px;
            gap: 10px;
            align-items: end;
            margin-bottom: 10px;
        }

        @media (max-width: 600px) {
            .item-row { grid-template-columns: 1fr 1fr; }
        }

        /* ── Remove Row Button ── */
        .btn-remove {
            width: 42px; height: 42px;
            background: rgba(239,68,68,.15);
            border: 1px solid rgba(239,68,68,.25);
            color: #fca5a5;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: background .15s;
        }
        .btn-remove:hover { background: rgba(239,68,68,.3); color: #fff; }
        .btn-remove.hidden { visibility: hidden; }

        /* ── Totals ── */
        .totals-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            padding-top: 16px;
            margin-top: 14px;
            border-top: 1px solid rgba(255,255,255,.07);
        }
        .totals-bar .item { }
        .totals-bar .lbl  {
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #7a8499;
        }
        .totals-bar .val  {
            font-family: 'Syne', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
        }
        .totals-bar .val.grand {
            font-size: 1.3rem;
            color: #e8175d;
        }

        /* ── Print area ── */
        .print-area {
            display: none; }
        @media print {
            body * { visibility: hidden; }
            .print-area, .print-area * { visibility: visible; }
            .print-area { display: block !important; position: absolute; left: 0; top: 0; background: #fff; color: #000; padding: 24px; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="app-main">
    <div style="max-width: 900px; margin: 0 auto;">

        <!-- Page Title -->
        <div class="page-header mb-4">
            <h4><i class="bi bi-receipt me-2"></i>New Sale Transaction</h4>
            <p>Record parts sold and labor services for a customer</p>
        </div>

        <!-- Customer Info -->
        <div class="sale-card">
            <p class="section-title mb-3">Customer Information</p>
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
                    <input type="text" class="form-control" id="plateNumber" placeholder="e.g. ABC 1234">
                </div>
            </div>
        </div>

        <!-- Line Items -->
        <div class="sale-card">
            <p class="section-title mb-3"><i class="bi bi-list-ul me-1"></i>Items & Services</p>

            <!-- Column headers -->
            <div class="item-row" style="margin-bottom:4px;">
                <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;">Part / Service</div>
                <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;">Qty</div>
                <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;">Amount (₱)</div>
                <div></div>
            </div>

            <div id="itemsContainer">
                <!-- First row injected by JS -->
            </div>

            <button type="button" class="btn-ghost mt-2" id="addItemBtn" style="font-size:.82rem;">
                <i class="bi bi-plus-lg me-1"></i>Add Item
            </button>

            <!-- Totals -->
            <div class="totals-bar">
                <div class="item">
                    <div class="lbl">Parts Total</div>
                    <div class="val" id="partsTotal">₱0.00</div>
                </div>
                <div class="item">
                    <div class="lbl">Labor Total</div>
                    <div class="val" id="laborTotal">₱0.00</div>
                </div>
                <div class="item">
                    <div class="lbl">Grand Total</div>
                    <div class="val grand" id="grandTotal">₱0.00</div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <button type="button" class="btn-pink" id="saveBtn">
                <i class="bi bi-save me-1"></i>Save Transaction
            </button>
            <button type="button" class="btn-ghost" id="printBtn">
                <i class="bi bi-printer me-1"></i>Print Receipt
            </button>
            <button type="button" class="btn-ghost" id="clearBtn">
                <i class="bi bi-file-earmark-plus me-1"></i>New Order
            </button>
            <a href="sales-history.php" class="btn-ghost ms-auto">
                <i class="bi bi-clock-history me-1"></i>View History
            </a>
        </div>

    </div><!-- end max-width wrapper -->
</main>

<!-- Print Receipt Area -->
<div id="printArea" class="print-area"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Products from PHP → JS
const PRODUCTS = <?= json_encode($products) ?>;

(function () {
    'use strict';

    // ── Format money ──────────────────────────────────────
    function money(n) {
        return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    }

    // ── Build the product select HTML ─────────────────────
    function buildOptions() {
        let html = '<option value="">— Select Part or type Labor —</option>';
        html += '<option value="labor" data-price="0" data-desc="Labor" data-type="labor">⚙️ Labor / Service (enter amount manually)</option>';
        html += '<option disabled>─────────────────────────────</option>';
        PRODUCTS.forEach(p => {
            const label = (p.code ? p.code + ' – ' : '') + p.description + ' (₱' + parseFloat(p.selling_price).toFixed(2) + ')';
            html += `<option value="${p.product_id}" data-price="${p.selling_price}" data-desc="${p.description.replace(/"/g,'&quot;')}" data-type="parts">${label}</option>`;
        });
        return html;
    }

    // ── Create a new item row ─────────────────────────────
    function createRow(isFirst = false) {
        const div = document.createElement('div');
        div.className = 'item-row';
        div.innerHTML = `
            <div><select class="form-select item-select" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#e8ecf4;border-radius:10px;height:42px;font-size:.83rem;">${buildOptions()}</select></div>
            <div><input type="number" class="form-control item-qty" min="1" value="1" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#e8ecf4;border-radius:10px;height:42px;"></div>
            <div><input type="number" class="form-control item-amount" min="0" step="0.01" value="0" placeholder="0.00" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#e8ecf4;border-radius:10px;height:42px;"></div>
            <div><button type="button" class="btn-remove ${isFirst ? 'hidden' : ''}" title="Remove"><i class="bi bi-dash-lg"></i></button></div>
        `;
        return div;
    }

    function refreshRemoveButtons() {
        const rows = document.querySelectorAll('#itemsContainer .item-row');
        rows.forEach((row, i) => {
            row.querySelector('.btn-remove')?.classList.toggle('hidden', i === 0);
        });
    }

    // Add first row
    const container = document.getElementById('itemsContainer');
    container.appendChild(createRow(true));

    // ── Event delegation ──────────────────────────────────
    container.addEventListener('change', function (e) {
        const row = e.target.closest('.item-row');
        if (!row || !e.target.classList.contains('item-select')) return;

        const opt    = e.target.options[e.target.selectedIndex];
        const type   = opt?.dataset?.type || 'parts';
        const price  = parseFloat(opt?.dataset?.price || 0);
        const qty    = parseInt(row.querySelector('.item-qty').value) || 1;
        const amtEl  = row.querySelector('.item-amount');

        if (type === 'parts' && price > 0) {
            amtEl.value = (price * qty).toFixed(2);
        }
        updateTotals();
    });

    container.addEventListener('input', function (e) {
        const row = e.target.closest('.item-row');
        if (!row) return;

        if (e.target.classList.contains('item-qty')) {
            const sel   = row.querySelector('.item-select');
            const opt   = sel?.options[sel.selectedIndex];
            const type  = opt?.dataset?.type || 'parts';
            const price = parseFloat(opt?.dataset?.price || 0);
            const qty   = parseInt(e.target.value) || 1;
            if (type === 'parts' && price > 0) {
                row.querySelector('.item-amount').value = (price * qty).toFixed(2);
            }
        }
        updateTotals();
    });

    container.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-remove');
        if (!btn) return;
        const rows = container.querySelectorAll('.item-row');
        if (rows.length > 1) {
            btn.closest('.item-row').remove();
            refreshRemoveButtons();
            updateTotals();
        }
    });

    document.getElementById('addItemBtn').addEventListener('click', function () {
        container.appendChild(createRow(false));
        refreshRemoveButtons();
    });

    // ── Update totals ─────────────────────────────────────
    function updateTotals() {
        let parts = 0, labor = 0;
        container.querySelectorAll('.item-row').forEach(row => {
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

    // ── Build payload for API ─────────────────────────────
    function buildPayload() {
        const items = [];
        container.querySelectorAll('.item-row').forEach(row => {
            const sel    = row.querySelector('.item-select');
            const opt    = sel?.options[sel.selectedIndex];
            const type   = opt?.dataset?.type || 'parts';
            const qty    = parseInt(row.querySelector('.item-qty')?.value) || 1;
            const amount = parseFloat(row.querySelector('.item-amount')?.value) || 0;
            if (amount <= 0) return;

            if (type === 'labor') {
                items.push({ type: 'labor', product_id: null, description: 'Labor', quantity: 1, unit_price: amount, amount });
            } else {
                const pid   = parseInt(sel.value) || 0;
                if (!pid) return;
                const price = parseFloat(opt?.dataset?.price || 0);
                items.push({ type: 'parts', product_id: pid, description: opt?.dataset?.desc || '', quantity: qty, unit_price: price, amount });
            }
        });
        return items;
    }

    // ── SAVE ──────────────────────────────────────────────
    document.getElementById('saveBtn').addEventListener('click', async function () {
        const items = buildPayload();
        if (!items.length) {
            Swal.fire({ icon: 'warning', title: 'No items', text: 'Add at least one item with an amount.' });
            return;
        }

        this.disabled = true;
        this.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving…';

        try {
            const resp = await fetch('backend/sales.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sale_date:     document.getElementById('saleDate').value,
                    customer_name: document.getElementById('customerName').value,
                    plate_number:  document.getElementById('plateNumber').value,
                    items,
                }),
            });
            const data = await resp.json();

            if (data.success) {
                // Build stock summary HTML
                let stockHtml = '';
                if (data.stock_summary?.length) {
                    stockHtml = '<div style="text-align:left;margin-top:12px;">'
                        + '<div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:8px;">📦 Stock Deducted</div>'
                        + data.stock_summary.map(s => {
                            const color = s.stock_left <= 0 ? '#fca5a5' : (s.low_stock ? '#fcd34d' : '#34d399');
                            const low   = s.low_stock ? '<span style="background:#fef3c7;color:#d97706;font-size:.62rem;padding:1px 6px;border-radius:6px;margin-left:4px;font-weight:700;">LOW</span>' : '';
                            return `<div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:.82rem;">
                                <span>${s.description}</span>
                                <span style="color:#fca5a5;font-weight:700;">-${s.qty_removed}</span>
                                <span style="color:${color};font-weight:700;">${s.stock_left} left${low}</span>
                            </div>`;
                        }).join('')
                        + '</div>';
                }

                Swal.fire({
                    icon: 'success',
                    title: `Sale #${data.sale_id} Saved!`,
                    html: stockHtml || 'Transaction recorded successfully.',
                    confirmButtonColor: '#e8175d',
                });
                clearForm();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Network Error', text: err.message });
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-save me-1"></i>Save Transaction';
        }
    });

    // ── PRINT ─────────────────────────────────────────────
    document.getElementById('printBtn').addEventListener('click', function () {
        let parts = 0, labor = 0;
        const lines = [];

        container.querySelectorAll('.item-row').forEach(row => {
            const sel    = row.querySelector('.item-select');
            const opt    = sel?.options[sel.selectedIndex];
            const type   = opt?.dataset?.type || 'parts';
            const desc   = opt?.dataset?.desc || (type === 'labor' ? 'Labor' : 'Item');
            const qty    = parseInt(row.querySelector('.item-qty')?.value) || 1;
            const amount = parseFloat(row.querySelector('.item-amount')?.value) || 0;
            if (amount <= 0) return;
            if (type === 'labor') labor += amount; else parts += amount;
            lines.push({ desc, qty, amount });
        });

        document.getElementById('printArea').innerHTML = `
            <h3 style="text-align:center;border-bottom:2px solid #000;padding-bottom:8px;">DISPEEDWAY</h3>
            <p><b>Date:</b> ${document.getElementById('saleDate').value}</p>
            <p><b>Customer:</b> ${document.getElementById('customerName').value || '—'}</p>
            <p><b>Plate:</b> ${document.getElementById('plateNumber').value || '—'}</p>
            <hr>
            ${lines.map(l => `<div>${l.desc} x${l.qty}<span style="float:right">${money(l.amount)}</span></div>`).join('')}
            <hr>
            <p>Parts: <b style="float:right">${money(parts)}</b><br style="clear:both">
            Labor: <b style="float:right">${money(labor)}</b><br style="clear:both"></p>
            <p style="font-size:1.15em;border-top:2px solid #000;padding-top:6px;">
                TOTAL: <b style="float:right">${money(parts + labor)}</b>
            </p>
            <p style="text-align:center;margin-top:16px;font-size:.85em;">Thank you for choosing Dispeedway!</p>
        `;
        window.print();
    });

    // ── CLEAR FORM ────────────────────────────────────────
    function clearForm() {
        document.getElementById('customerName').value = '';
        document.getElementById('plateNumber').value  = '';
        container.innerHTML = '';
        container.appendChild(createRow(true));
        updateTotals();
    }

    document.getElementById('clearBtn').addEventListener('click', function () {
        clearForm();
        Swal.fire({ icon: 'info', title: 'Cleared', text: 'Ready for new transaction.', timer: 1200, showConfirmButton: false });
    });

    updateTotals();
})();
</script>
</body>
</html>
