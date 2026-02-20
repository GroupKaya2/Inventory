<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$activePage = 'sales';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Sale Transaction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --sales-bg: #0f172a;
            --sales-card: #1e293b;
            --sales-border: rgba(255,255,255,.08);
            --sales-text: #e2e8f0;
            --sales-muted: #94a3b8;
            --sales-orange: #f97316;
        }
        body { background: var(--sales-bg); color: var(--sales-text); }
        .app-main { background: var(--sales-bg); }
        .sales-page {
            max-width: 900px;
            margin: 0 auto;
        }
        .sales-header {
            background: linear-gradient(180deg, #111827 0%, #0b1220 100%);
            border: 1px solid var(--sales-border);
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .sales-header h1 {
            font-size: 1.35rem;
            font-weight: 700;
            margin: 0;
            color: #fff;
        }
        .sales-badge {
            background: rgba(59, 130, 246, 0.25);
            color: #93c5fd;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .sales-card {
            background: var(--sales-card);
            border: 1px solid var(--sales-border);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 18px;
        }
        .sales-card .form-label {
            color: var(--sales-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 6px;
        }
        .sales-card .form-control,
        .sales-card .form-select {
            background: rgba(255,255,255,.06);
            border: 1px solid var(--sales-border);
            color: var(--sales-text);
            border-radius: 10px;
            height: 44px;
        }
        .sales-card .form-control::placeholder {
            color: var(--sales-muted);
        }
        .sales-card .form-control:focus,
        .sales-card .form-select:focus {
            background: rgba(255,255,255,.08);
            border-color: rgba(99,102,241,.5);
            color: var(--sales-text);
            box-shadow: 0 0 0 3px rgba(99,102,241,.15);
        }
        .section-title {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--sales-muted);
            margin-bottom: 12px;
        }
        .item-row {
            display: grid;
            grid-template-columns: 1fr 100px 80px 120px 40px;
            gap: 10px;
            align-items: end;
            margin-bottom: 12px;
        }
        @media (max-width: 768px) {
            .item-row {
                grid-template-columns: 1fr 1fr;
            }
            .item-row .btn-remove-row { grid-column: 1 / -1; }
        }
        .item-row .form-control,
        .item-row .form-select { height: 42px; }
        .btn-remove-row {
            background: rgba(239,68,68,.2);
            color: #fca5a5;
            border: 1px solid rgba(239,68,68,.3);
            border-radius: 10px;
            width: 40px;
            height: 42px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-remove-row:hover {
            background: rgba(239,68,68,.35);
            color: #fff;
        }
        .btn-add-item {
            background: rgba(255,255,255,.08);
            border: 1px solid var(--sales-border);
            color: var(--sales-text);
            border-radius: 10px;
            padding: 10px 16px;
            font-weight: 600;
        }
        .btn-add-item:hover {
            background: rgba(255,255,255,.12);
            color: #fff;
        }
        .totals-row {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            margin: 16px 0;
            padding: 12px 0;
            border-top: 1px solid var(--sales-border);
        }
        .totals-row span {
            font-size: 1rem;
            color: var(--sales-muted);
        }
        .totals-row strong {
            color: #fff;
            font-size: 1.1rem;
        }
        .btn-save {
            background: var(--sales-orange);
            border: none;
            color: #111;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
        }
        .btn-save:hover {
            background: #fb923c;
            color: #111;
        }
        .btn-print, .btn-new-wo {
            background: rgba(255,255,255,.08);
            border: 1px solid var(--sales-border);
            color: var(--sales-text);
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-print:hover, .btn-new-wo:hover {
            background: rgba(255,255,255,.12);
            color: #fff;
        }
        #itemsContainer .item-row:first-child .btn-remove-row { visibility: hidden; }
        .receipt-print {
            display: none;
            background: #fff;
            color: #111;
            padding: 24px;
            max-width: 400px;
            margin: 0 auto;
            font-family: monospace;
        }
        @media print {
            body * { visibility: hidden; }
            .receipt-print, .receipt-print * { visibility: visible; }
            .receipt-print { display: block !important; position: absolute; left: 0; top: 0; }
        }
    </style>
</head>
<body>
    <?php include "sidebar.php"; ?>

    <main class="app-main p-3 p-md-4">
        <div class="sales-page">
            <div class="sales-header">
                <h1><i class="bi bi-receipt"></i> NEW SALE TRANSACTION</h1>
                <span class="sales-badge"><i class="bi bi-arrow-repeat"></i> Auto-updates Inventory</span>
            </div>

            <div class="sales-card">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">DATE</label>
                        <input type="date" class="form-control" id="saleDate" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CUSTOMER NAME</label>
                        <input type="text" class="form-control" id="customerName" placeholder="Customer / Vehicle owner">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">PLATE NUMBER</label>
                        <input type="text" class="form-control" id="plateNumber" placeholder="e.g. FHG-150">
                    </div>
                </div>
            </div>

            <div class="sales-card">
                <div class="section-title">ITEM / SERVICE</div>
                <div id="itemsContainer">
                    <div class="item-row">
                        <div>
                            <label class="form-label d-none d-md-block">Part / Service</label>
                            <select class="form-select item-select" data-row="0">
                                <option value="">- Select Part/Service -</option>
                                <option value="labor">— Labor / Service —</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label d-none d-md-block">TYPE</label>
                            <select class="form-select item-type" data-row="0">
                                <option value="parts">Parts</option>
                                <option value="labor">Labor</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label d-none d-md-block">QTY</label>
                            <input type="number" class="form-control item-qty" min="1" value="1" data-row="0">
                        </div>
                        <div>
                            <label class="form-label d-none d-md-block">AMOUNT</label>
                            <input type="number" class="form-control item-amount" min="0" step="0.01" value="0" placeholder="0" data-row="0">
                        </div>
                        <button type="button" class="btn btn-remove-row" title="Remove row"><i class="bi bi-dash-lg"></i></button>
                    </div>
                </div>
                <button type="button" class="btn btn-add-item mt-2" id="addItemBtn"><i class="bi bi-plus-lg"></i> Add Item</button>
                <div class="totals-row">
                    <span>Parts Total: <strong id="partsTotal">P0</strong></span>
                    <span>Labor Total: <strong id="laborTotal">P0</strong></span>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-save" id="saveTransactionBtn"><i class="bi bi-save"></i> Save Transaction</button>
                <button type="button" class="btn btn-print" id="printReceiptBtn"><i class="bi bi-printer"></i> Print Receipt</button>
                <button type="button" class="btn btn-new-wo" id="newWorkOrderBtn"><i class="bi bi-file-earmark-plus"></i> New Work Order</button>
            </div>
        </div>

        <div id="receiptContent" class="receipt-print"></div>
    </main>

    <script>
(function() {
    let products = [];
    let rowCount = 1;

    function formatMoney(n) {
        return 'P' + (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function loadProducts() {
        fetch('fetch_products.php')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data) {
                    products = data.data;
                    const sel = document.querySelector('.item-select[data-row="0"]');
                    if (sel) {
                        products.forEach(p => {
                            const opt = document.createElement('option');
                            opt.value = p.product_id;
                            opt.textContent = (p.code ? p.code + ' - ' : '') + p.description + ' (P' + p.selling_price + ')';
                            opt.dataset.price = p.selling_price;
                            opt.dataset.desc = p.description;
                            sel.appendChild(opt);
                        });
                    }
                }
            })
            .catch(() => {});
    }

    function addRow() {
        const container = document.getElementById('itemsContainer');
        const firstRow = container.querySelector('.item-row');
        const clone = firstRow.cloneNode(true);
        clone.querySelectorAll('[data-row]').forEach(el => el.setAttribute('data-row', rowCount));
        clone.querySelector('.item-select').innerHTML = '<option value="">- Select Part/Service -</option><option value="labor">— Labor / Service —</option>';
        products.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.product_id;
            opt.textContent = (p.code ? p.code + ' - ' : '') + p.description + ' (P' + p.selling_price + ')';
            opt.dataset.price = p.selling_price;
            opt.dataset.desc = p.description;
            clone.querySelector('.item-select').appendChild(opt);
        });
        clone.querySelector('.item-qty').value = 1;
        clone.querySelector('.item-amount').value = 0;
        clone.querySelector('.btn-remove-row').style.visibility = 'visible';
        container.appendChild(clone);
        rowCount++;
    }

    function bindRowEvents() {
        const container = document.getElementById('itemsContainer');
        if (!container) return;
        container.addEventListener('change', function(e) {
            const sel = e.target;
            if (sel.classList.contains('item-select')) {
                const row = sel.closest('.item-row');
                const typeSel = row.querySelector('.item-type');
                const amt = row.querySelector('.item-amount');
                if (sel.value === 'labor') {
                    typeSel.value = 'labor';
                    row.querySelector('.item-qty').value = 1;
                } else if (sel.value) {
                    typeSel.value = 'parts';
                    const opt = sel.options[sel.selectedIndex];
                    const price = parseFloat(opt.dataset.price) || 0;
                    const q = parseInt(row.querySelector('.item-qty').value, 10) || 1;
                    amt.value = (price * q).toFixed(2);
                }
                updateTotals();
            } else if (sel.classList.contains('item-type')) {
                const row = sel.closest('.item-row');
                const partSel = row.querySelector('.item-select');
                if (sel.value === 'labor') partSel.value = 'labor';
                updateTotals();
            }
        });
        container.addEventListener('input', function(e) {
            if (e.target.classList.contains('item-qty') || e.target.classList.contains('item-amount')) {
                const row = e.target.closest('.item-row');
                if (e.target.classList.contains('item-qty') && row) {
                    const type = row.querySelector('.item-type').value;
                    const select = row.querySelector('.item-select');
                    if (type === 'parts' && select.value) {
                        const opt = select.options[select.selectedIndex];
                        const price = parseFloat(opt.dataset.price) || 0;
                        row.querySelector('.item-amount').value = (price * (parseInt(e.target.value, 10) || 1)).toFixed(2);
                    }
                }
                updateTotals();
            }
        });
        container.addEventListener('click', function(e) {
            if (e.target.closest('.btn-remove-row')) {
                const row = e.target.closest('.item-row');
                if (document.querySelectorAll('.item-row').length <= 1) return;
                row.remove();
                updateTotals();
            }
        });
    }

    function updateTotals() {
        let parts = 0, labor = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const type = row.querySelector('.item-type').value;
            const qty = parseInt(row.querySelector('.item-qty').value, 10) || 0;
            const amount = parseFloat(row.querySelector('.item-amount').value) || 0;
            if (type === 'labor') labor += amount;
            else parts += amount;
        });
        document.getElementById('partsTotal').textContent = formatMoney(parts);
        document.getElementById('laborTotal').textContent = formatMoney(labor);
    }

    function getItemsPayload() {
        const items = [];
        document.querySelectorAll('.item-row').forEach(row => {
            const select = row.querySelector('.item-select');
            const typeSel = row.querySelector('.item-type');
            const type = typeSel.value;
            const qty = parseInt(row.querySelector('.item-qty').value, 10) || 1;
            const amount = parseFloat(row.querySelector('.item-amount').value) || 0;
            if (type === 'labor') {
                if (amount <= 0) return;
                items.push({
                    type: 'labor',
                    product_id: null,
                    description: 'Labor',
                    quantity: 1,
                    amount: amount,
                    unit_price: amount
                });
            } else {
                const pid = parseInt(select.value, 10);
                if (!pid) return;
                const opt = select.options[select.selectedIndex];
                const unitPrice = parseFloat(opt.dataset.price) || amount;
                const desc = opt.dataset.desc || '';
                items.push({
                    type: 'parts',
                    product_id: pid,
                    description: desc,
                    quantity: qty,
                    amount: amount || (unitPrice * qty),
                    unit_price: unitPrice
                });
            }
        });
        return items;
    }

    document.getElementById('addItemBtn').addEventListener('click', addRow);

    document.getElementById('saveTransactionBtn').addEventListener('click', function() {
        const items = getItemsPayload();
        if (items.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Add items', text: 'Add at least one part or labor item.' });
            return;
        }
        const saleDate = document.getElementById('saleDate').value;
        if (!saleDate) {
            Swal.fire({ icon: 'warning', title: 'Date required', text: 'Select transaction date.' });
            return;
        }
        this.disabled = true;
        fetch('save_sale.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sale_date: saleDate,
                customer_name: document.getElementById('customerName').value,
                plate_number: document.getElementById('plateNumber').value,
                items: items
            })
        })
            .then(r => r.json())
            .then(data => {
                this.disabled = false;
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Saved', text: data.message });
                    lastSavedReceipt = { sale_id: data.sale_id, date: saleDate, customer: document.getElementById('customerName').value, plate: document.getElementById('plateNumber').value, items: items, partsTotal: items.filter(i => i.type === 'parts').reduce((s, i) => s + (i.amount||0), 0), laborTotal: items.filter(i => i.type === 'labor').reduce((s, i) => s + (i.amount||0), 0) };
                    document.getElementById('printReceiptBtn').focus();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to save.' });
                }
            })
            .catch(() => {
                this.disabled = false;
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' });
            });
    });

    let lastSavedReceipt = null;

    document.getElementById('printReceiptBtn').addEventListener('click', function() {
        const receiptEl = document.getElementById('receiptContent');
        let parts = 0, labor = 0;
        const lines = [];
        document.querySelectorAll('.item-row').forEach(row => {
            const type = row.querySelector('.item-type').value;
            const select = row.querySelector('.item-select');
            const desc = type === 'labor' ? 'Labor' : (select.options[select.selectedIndex] && select.options[select.selectedIndex].dataset.desc) || 'Part';
            const qty = row.querySelector('.item-qty').value;
            const amount = parseFloat(row.querySelector('.item-amount').value) || 0;
            if (amount <= 0 && type !== 'labor') return;
            if (type === 'labor') labor += amount; else parts += amount;
            lines.push({ desc, qty, amount });
        });
        const total = parts + labor;
        const date = document.getElementById('saleDate').value;
        const customer = document.getElementById('customerName').value;
        const plate = document.getElementById('plateNumber').value;
        receiptEl.innerHTML =
            '<h4 style="text-align:center">RECEIPT</h4>' +
            '<p><strong>Date:</strong> ' + (date || '—') + '</p>' +
            '<p><strong>Customer:</strong> ' + (customer || '—') + '</p>' +
            '<p><strong>Plate:</strong> ' + (plate || '—') + '</p>' +
            '<hr/>' +
            lines.map(l => l.desc + ' x' + l.qty + ' — ' + formatMoney(l.amount)).join('<br/>') +
            '<hr/>' +
            '<p>Parts Total: ' + formatMoney(parts) + '</p>' +
            '<p>Labor Total: ' + formatMoney(labor) + '</p>' +
            '<p><strong>Grand Total: ' + formatMoney(total) + '</strong></p>';
        receiptEl.style.display = 'block';
        window.print();
        receiptEl.style.display = 'none';
    });

    document.getElementById('newWorkOrderBtn').addEventListener('click', function() {
        document.getElementById('customerName').value = '';
        document.getElementById('plateNumber').value = '';
        document.getElementById('saleDate').value = '<?php echo date('Y-m-d'); ?>';
        const container = document.getElementById('itemsContainer');
        while (container.querySelectorAll('.item-row').length > 1)
            container.querySelector('.item-row:last-child').remove();
        const first = container.querySelector('.item-row');
        first.querySelector('.item-select').value = '';
        first.querySelector('.item-type').value = 'parts';
        first.querySelector('.item-qty').value = 1;
        first.querySelector('.item-amount').value = 0;
        updateTotals();
        Swal.fire({ icon: 'info', title: 'New transaction', text: 'Form cleared. Add items and save.' });
    });

    loadProducts();
    bindRowEvents();
})();
    </script>
</body>
</html>
