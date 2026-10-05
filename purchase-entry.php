    <?php
    session_start();
    require_once 'backend/db.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    $activePage = 'purchase_entry';
    $isOwner    = ($_SESSION['role'] ?? 'manager') === 'owner';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Purchase Entry - DSpeedway</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link rel="stylesheet" href="assets/css/app.css">
        <link rel="stylesheet" href="assets/css/purchase-entry.css?v=<?= file_exists(__DIR__ . '/assets/css/purchase-entry.css') ? filemtime(__DIR__ . '/assets/css/purchase-entry.css') : time() ?>">
    </head>
    <body>

    <?php include 'sidebar.php'; ?>

    <main class="app-main po-page">

        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4><i class="bi bi-truck me-2" style="color:#a78bfa;"></i>Purchase Entry</h4>
                <p>Record a supplier invoice — updates Accounts Payable and Inventory in one step.</p>
            </div>
            <a href="accounts-payable.php" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Back to Accounts Payable
            </a>
        </div>

        <div class="po-card">
            <div class="po-card-head">
                <h3 class="po-card-title">
                    <span class="icon-wrap" style="background:rgba(167,139,250,.15);color:#a78bfa;"><i class="bi bi-building"></i></span>
                    Supplier Invoice
                </h3>
            </div>
            <div class="po-info-grid">
                <div>
                    <label class="field-label">Supplier Name *</label>
                    <input type="text" class="po-input" id="supplierName" placeholder="e.g. AutoParts Distributor Inc.">
                </div>
                <div>
                    <label class="field-label">Invoice Number</label>
                    <input type="text" class="po-input" id="invoiceNumber" placeholder="e.g. INV-2026-0341">
                </div>
                <div>
                    <label class="field-label">Invoice Date *</label>
                    <input type="date" class="po-input" id="invoiceDate">
                </div>
                <div>
                    <label class="field-label">Due Date</label>
                    <input type="date" class="po-input" id="dueDate">
                </div>
                <div>
                    <label class="field-label">Mode of Payment</label>
                    <select class="po-input po-select" id="modeOfPayment">
                        <option value="credit">Credit (pay later)</option>
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="check">Check</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Amount Paid Now (₱)</label>
                    <input type="number" class="po-input" id="amountPaidNow" min="0" step="0.01" value="0">
                </div>
            </div>
            <div class="mt-3">
                <label class="field-label">Notes</label>
                <textarea class="po-input" id="poNotes" placeholder="Optional notes about this delivery/invoice…"></textarea>
            </div>
        </div>

        <div class="po-card">
            <div class="po-card-head">
                <h3 class="po-card-title">
                    <span class="icon-wrap" style="background:rgba(96,165,250,.15);color:#60a5fa;"><i class="bi bi-box-seam"></i></span>
                    Items Received
                </h3>
                <button type="button" class="btn-add-row" onclick="addItem()">
                    <i class="bi bi-plus-lg"></i> Add Item
                </button>
            </div>

            <div class="po-item-header">
                <div>Product</div>
                <div>Qty</div>
                <div>Unit Cost (₱)</div>
                <div>Total (₱)</div>
                <div></div>
            </div>
            <div id="itemsWrap"></div>
            <p id="noItemsMsg" class="empty-hint">
                No items yet. Click <strong>Add Item</strong> to start.
            </p>

            <div class="po-grand-total">
                <span>Grand Total:</span>
                <span id="grandTotalDisplay">₱0.00</span>
            </div>
        </div>

        <div class="po-actions">
            <button type="button" class="btn-ghost" onclick="clearForm()">
                <i class="bi bi-x-circle"></i> Clear Form
            </button>
            <button type="button" class="btn-save-po" id="submitPurchase">
                <i class="bi bi-check-circle"></i> Save Purchase
            </button>
        </div>

    </main>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    'use strict';

    let PRODUCTS = [];
    let items = [];
    let uid = 0;

    const TODAY = new Date().toISOString().slice(0, 10);
    document.getElementById('invoiceDate').value = TODAY;

    function esc(str) {
        return String(str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    function peso(n) {
        return '₱' + (parseFloat(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function productLabel(p) {
        return p.description + (p.code ? ' (' + p.code + ')' : '');
    }

    async function loadProducts() {
        try {
            const res = await fetch('backend/products.php?action=fetch');
            const json = await res.json();
            PRODUCTS = json.success ? (json.data || json.products || []) : [];
        } catch (e) {
            console.error('Failed to load products', e);
            PRODUCTS = [];
        }
    }

    function addItem() {
        items.push({ id: ++uid, product_id: null, description: '', quantity: 1, unit_cost: 0 });
        renderItems();
    }

    function removeItem(idx) {
        items.splice(idx, 1);
        renderItems();
    }

    function onProductSearchInput(idx, inputEl) {
        const typed = inputEl.value;
        const match = PRODUCTS.find(p => productLabel(p) === typed);
        if (match) {
            items[idx].product_id = match.product_id;
            items[idx].description = match.description;
            // Pre-fill cost from the product's current cost, but stays editable --
            // supplier prices drift, this is just a sane starting point.
            const costInput = document.getElementById(`poCost-${idx}`);
            if (costInput) costInput.value = parseFloat(match.unit_cost) || 0;
            items[idx].unit_cost = parseFloat(match.unit_cost) || 0;
        } else {
            items[idx].product_id = null;
            items[idx].description = typed;
        }
        recalc();
    }

    function renderItems() {
        const wrap = document.getElementById('itemsWrap');
        const noMsg = document.getElementById('noItemsMsg');

        if (!items.length) {
            wrap.innerHTML = '';
            noMsg.style.display = '';
            recalc();
            return;
        }
        noMsg.style.display = 'none';

        const datalistHtml = PRODUCTS.map(p => `<option value="${esc(productLabel(p))}">`).join('');

        wrap.innerHTML = items.map((row, idx) => {
            const listId = `poProductList-${idx}`;
            const matched = PRODUCTS.find(p => p.product_id === row.product_id);
            const initialValue = matched ? productLabel(matched) : row.description;

            return `
        <div class="po-item-row">
            <div>
                <input type="text" class="po-input" list="${listId}" autocomplete="off" value="${esc(initialValue)}"
                placeholder="Search product by name or code…"
                oninput="onProductSearchInput(${idx}, this)">
                <datalist id="${listId}">${datalistHtml}</datalist>
                ${!row.product_id && row.description ? '<div class="po-no-match">⚠ No matching product — pick one from the list</div>' : ''}
            </div>
            <div>
                <input type="number" class="po-input" min="1" value="${row.quantity}"
                oninput="items[${idx}].quantity=Math.max(1,parseInt(this.value)||1);recalc();">
            </div>
            <div>
                <input type="number" class="po-input" id="poCost-${idx}" min="0" step="0.01" value="${row.unit_cost || ''}"
                oninput="items[${idx}].unit_cost=parseFloat(this.value)||0;recalc();">
            </div>
            <div>
                <input type="text" class="po-input po-line-total" readonly data-line-total data-idx="${idx}" value="${peso(row.quantity * row.unit_cost)}">
            </div>
            <div>
                <button type="button" class="btn-remove" onclick="removeItem(${idx})" title="Remove"><i class="bi bi-trash"></i></button>
            </div>
        </div>`;
        }).join('');

        recalc();
    }

    function recalc() {
        document.querySelectorAll('[data-line-total]').forEach(el => {
            const idx = parseInt(el.dataset.idx, 10);
            const r = items[idx];
            if (r) el.value = peso(r.quantity * r.unit_cost);
        });

        const grand = items.reduce((s, r) => s + (r.quantity * r.unit_cost), 0);
        document.getElementById('grandTotalDisplay').textContent = peso(grand);
    }

    function currentGrandTotal() {
        return items.reduce((s, r) => s + (r.quantity * r.unit_cost), 0);
    }

    function clearForm() {
        document.getElementById('supplierName').value = '';
        document.getElementById('invoiceNumber').value = '';
        document.getElementById('invoiceDate').value = TODAY;
        document.getElementById('dueDate').value = '';
        document.getElementById('modeOfPayment').value = 'credit';
        document.getElementById('amountPaidNow').value = 0;
        document.getElementById('poNotes').value = '';
        items = [];
        renderItems();
    }

    document.getElementById('submitPurchase').addEventListener('click', async () => {
        const btn = document.getElementById('submitPurchase');
        const supplier = document.getElementById('supplierName').value.trim();
        const invoiceDate = document.getElementById('invoiceDate').value;

        if (!supplier) {
            Swal.fire({ icon: 'warning', title: 'Supplier name is required.' });
            return;
        }
        if (!invoiceDate) {
            Swal.fire({ icon: 'warning', title: 'Invoice date is required.' });
            return;
        }
        if (!items.length) {
            Swal.fire({ icon: 'warning', title: 'Add at least one item.' });
            return;
        }
        const unmatched = items.filter(r => !r.product_id);
        if (unmatched.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Match every item to a real product',
                text: 'Pick a product from the search list for each row — items typed freely without a match can\'t update inventory.',
            });
            return;
        }
        const badQty = items.some(r => !r.quantity || r.quantity <= 0);
        if (badQty) {
            Swal.fire({ icon: 'warning', title: 'Every item needs a quantity greater than 0.' });
            return;
        }

        const grandTotal = currentGrandTotal();
        const amountPaidNow = parseFloat(document.getElementById('amountPaidNow').value) || 0;
        if (amountPaidNow > grandTotal + 0.01) {
            Swal.fire({ icon: 'warning', title: 'Amount paid can\'t exceed the Grand Total.' });
            return;
        }

        const confirm = await Swal.fire({
            title: 'Confirm Purchase Entry',
            html: `<div style="text-align:left;font-size:.85rem;">
                    <strong>${esc(supplier)}</strong><br>
                    ${items.length} item(s) — Grand Total: <strong>${peso(grandTotal)}</strong><br><br>
                    This will:<br>
                    • Add ₱${grandTotal.toFixed(2)} to Accounts Payable${amountPaidNow > 0 ? ` (₱${amountPaidNow.toFixed(2)} marked paid now)` : ''}<br>
                    • Increase stock for ${items.length} product(s)
                </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            confirmButtonText: 'Save Purchase',
        });
        if (!confirm.isConfirmed) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';

        try {
            const payload = {
                supplier_name: supplier,
                invoice_number: document.getElementById('invoiceNumber').value.trim(),
                invoice_date: invoiceDate,
                due_date: document.getElementById('dueDate').value || null,
                mode_of_payment: document.getElementById('modeOfPayment').value,
                amount_paid: amountPaidNow,
                notes: document.getElementById('poNotes').value.trim(),
                items: items.map(r => ({
                    product_id: r.product_id,
                    quantity: r.quantity,
                    unit_cost: r.unit_cost,
                })),
            };

            const res = await fetch('backend/purchase-entry.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();

            if (data.success) {
                const next = await Swal.fire({
                    icon: 'success',
                    title: 'Purchase Recorded!',
                    html: `${data.item_count} item(s) added to inventory<br>₱${data.total_amount.toFixed(2)} added to Accounts Payable`,
                    showCancelButton: true,
                    confirmButtonText: 'View in Accounts Payable',
                    cancelButtonText: 'Record Another',
                    confirmButtonColor: '#16a34a',
                });
                if (next.isConfirmed) {
                    window.location.href = 'accounts-payable.php';
                } else {
                    clearForm();
                }
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Network error', text: e.message });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Save Purchase';
        }
    });

    (async function init() {
        await loadProducts();
        addItem(); // start with one empty row for convenience
    })();
    </script>

    </body>
    </html>
