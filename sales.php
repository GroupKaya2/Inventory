<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$activePage = 'sales';
$today = date('Y-m-d');

// Fetch all products for the dropdown
$products = [];
$r = $conn->query("
    SELECT p.product_id, p.code, p.description, p.unit, p.selling_price, ps.current_stock
    FROM products p
    LEFT JOIN product_stock ps ON p.product_id = ps.product_id
    ORDER BY p.description ASC
");
if ($r)
    while ($row = $r->fetch_assoc())
        $products[] = $row;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Sale Dispeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/sales.css">
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <main class="app-main">

        <div class="page-header mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 style="margin:0;"><i class="bi bi-receipt me-2"></i>New Sale</h4>
                    <p style="margin:0;">Record parts-only, labor-only, or combined transactions</p>
                </div>
                <a href="sales-history.php" class="btn-ghost" style="color:#fff;">
                    <i class="bi bi-clock-history me-1"></i>Sales History
                </a>
            </div>
        </div>

        <!-- Sale Header Info -->
        <div class="sale-card mb-3">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" style="color:#7a8499;">Sale Date</label>
                    <input type="date" class="form-control" id="saleDate" value="<?= $today ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" style="color:#7a8499;">Customer Name</label>
                    <input type="text" class="form-control" id="customerName" placeholder="Enter Customer Name">
                </div>
                <div class="col-md-4">
                    <label class="form-label" style="color:#7a8499;">Plate Number</label>
                    <input type="text" class="form-control" id="plateNumber" placeholder="Enter Plate Number"
                        style="text-transform:uppercase;">
                </div>
            </div>
        </div>

        <!-- Parts Section -->
        <div class="sale-card mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 style="margin:0;font-size:.9rem;color:#93c5fd;"><i class="bi bi-box-seam me-2"></i>Parts / Products
                </h6>
                <button type="button" class="btn-pink" style="font-size:.8rem;padding:7px 14px;"
                    onclick="addPartsRow()">
                    <i class="bi bi-plus-lg me-1"></i>Add Parts Row
                </button>
            </div>
            <div id="partsContainer"></div>
            <div id="noPartsMsg" style="text-align:center;color:#7a8499;padding:18px 0;font-size:.85rem;">
                No parts added yet. Click <strong style="color:#e8ecf4;">Add Parts Row</strong> above.
            </div>
        </div>

        <!-- Labor Section -->
        <div class="sale-card mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 style="margin:0;font-size:.9rem;color:#34d399;"><i class="bi bi-wrench-adjustable me-2"></i>Labor /
                    Services</h6>
                <button type="button" class="btn-pink"
                    style="font-size:.8rem;padding:7px 14px;background:linear-gradient(135deg,#10b981,#059669);"
                    onclick="addLaborRow()">
                    <i class="bi bi-plus-lg me-1"></i>Add Labor Row
                </button>
            </div>
            <div id="laborContainer"></div>
            <div id="noLaborMsg" style="text-align:center;color:#7a8499;padding:18px 0;font-size:.85rem;">
                No labor added yet. Click <strong style="color:#e8ecf4;">Add Labor Row</strong> above.
            </div>
        </div>

        <!-- Totals Bar -->
        <div class="sale-card mb-4">
            <div class="totals-bar">
                <div>
                    <div class="lbl">Parts Total</div>
                    <div class="val" id="partsTotal">₱0.00</div>
                </div>
                <div>
                    <div class="lbl">Labor Total</div>
                    <div class="val" style="color:#34d399;" id="laborTotal">₱0.00</div>
                </div>
                <div>
                    <div class="lbl">Grand Total</div>
                    <div class="val grand" id="grandTotal">₱0.00</div>
                </div>
                <div class="ms-auto d-flex gap-2 flex-wrap align-items-center">
                    <button type="button" class="btn-ghost" onclick="clearAll()" style="padding:10px 20px;">
                        <i class="bi bi-trash me-1"></i>Clear
                    </button>
                    <button type="button" class="btn-pink" id="submitBtn" onclick="submitSale()"
                        style="padding:10px 26px;font-size:.95rem;">
                        <i class="bi bi-save me-1"></i>Save Sale
                    </button>
                </div>
            </div>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        'use strict';
        const PRODUCTS = <?= json_encode($products) ?>;

        let partsRows = [];
        let laborRows = [];
        let rowCounter = 0;

        function peso(n) {
            return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function buildProductOptions(selectedId) {
            let opts = '<option value="">— Select Product —</option>';
            PRODUCTS.forEach(p => {
                const sel = String(p.product_id) === String(selectedId) ? 'selected' : '';
                opts += `<option value="${p.product_id}" data-price="${p.selling_price}" data-stock="${p.current_stock}" ${sel}>${p.description} (${p.code}) — Stock: ${p.current_stock ?? 0}</option>`;
            });
            return opts;
        }

        function renderParts() {
            const c = document.getElementById('partsContainer');
            const nm = document.getElementById('noPartsMsg');
            if (!partsRows.length) { c.innerHTML = ''; nm.style.display = ''; recalc(); return; }
            nm.style.display = 'none';
            c.innerHTML = partsRows.map((row, idx) => `
        <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:14px 16px;margin-bottom:10px;">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label" style="color:#7a8499;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Product</label>
                    <select class="form-select" onchange="onProductChange(${idx},this)" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#e8ecf4;border-radius:8px;">
                        ${buildProductOptions(row.product_id)}
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" style="color:#7a8499;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Qty</label>
                    <input type="number" class="form-control" min="1" value="${row.qty}"
                        oninput="updateParts(${idx},'qty',this.value)"
                        style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#e8ecf4;border-radius:8px;">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" style="color:#7a8499;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Unit Price (₱)</label>
                    <input type="number" class="form-control" min="0" step="0.01" value="${row.unit_price}"
                        oninput="updateParts(${idx},'unit_price',this.value)"
                        style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#e8ecf4;border-radius:8px;">
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end justify-content-between">
                    <div style="font-size:.82rem;color:#93c5fd;font-weight:700;">${peso(row.qty * row.unit_price)}</div>
                    <button type="button" class="btn-remove" onclick="removePartsRow(${idx})"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
        </div>
    `).join('');
            recalc();
        }

        function renderLabor() {
            const c = document.getElementById('laborContainer');
            const nm = document.getElementById('noLaborMsg');
            if (!laborRows.length) { c.innerHTML = ''; nm.style.display = ''; recalc(); return; }
            nm.style.display = 'none';
            c.innerHTML = laborRows.map((row, idx) => `
        <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:14px 16px;margin-bottom:10px;">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-7">
                    <label class="form-label" style="color:#7a8499;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Service Description</label>
                    <input type="text" class="form-control" value="${escAttr(row.description)}"
                        placeholder="e.g. Oil Change Service"
                        oninput="updateLabor(${idx},'description',this.value)"
                        style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#e8ecf4;border-radius:8px;">
                </div>
                <div class="col-8 col-md-3">
                    <label class="form-label" style="color:#7a8499;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Amount (₱)</label>
                    <input type="number" class="form-control" min="0" step="0.01" value="${row.amount}"
                        oninput="updateLabor(${idx},'amount',this.value)"
                        style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#e8ecf4;border-radius:8px;">
                </div>
                <div class="col-4 col-md-2 d-flex align-items-end justify-content-end">
                    <button type="button" class="btn-remove" onclick="removeLaborRow(${idx})"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
        </div>
    `).join('');
            recalc();
        }

        function escAttr(s) { return (s || '').replace(/"/g, '&quot;'); }

        function addPartsRow() { partsRows.push({ id: ++rowCounter, product_id: '', qty: 1, unit_price: 0 }); renderParts(); }
        function addLaborRow() { laborRows.push({ id: ++rowCounter, description: '', amount: 0 }); renderLabor(); }
        function removePartsRow(idx) { partsRows.splice(idx, 1); renderParts(); }
        function removeLaborRow(idx) { laborRows.splice(idx, 1); renderLabor(); }

        function onProductChange(idx, sel) {
            const opt = sel.options[sel.selectedIndex];
            partsRows[idx].product_id = sel.value;
            partsRows[idx].unit_price = parseFloat(opt.dataset.price || 0);
            renderParts();
        }

        function updateParts(idx, field, val) {
            partsRows[idx][field] = field === 'qty' ? Math.max(1, parseInt(val) || 1) : parseFloat(val) || 0;
            recalc();
            // update amount display inline without full re-render
            document.querySelectorAll('#partsContainer > div')[idx]
                ?.querySelectorAll('[style*="93c5fd"]')[0]
                ?.let?.(() => { })
            renderParts();
        }

        function updateLabor(idx, field, val) {
            laborRows[idx][field] = field === 'amount' ? parseFloat(val) || 0 : val;
            recalc();
        }

        function recalc() {
            const ps = partsRows.reduce((s, r) => s + r.qty * r.unit_price, 0);
            const ls = laborRows.reduce((s, r) => s + r.amount, 0);
            document.getElementById('partsTotal').textContent = peso(ps);
            document.getElementById('laborTotal').textContent = peso(ls);
            document.getElementById('grandTotal').textContent = peso(ps + ls);
        }

        function clearAll() {
            partsRows = []; laborRows = [];
            renderParts(); renderLabor();
            document.getElementById('customerName').value = '';
            document.getElementById('plateNumber').value = '';
            document.getElementById('saleDate').value = '<?= $today ?>';
            recalc();
        }

        async function submitSale() {
            if (!partsRows.length && !laborRows.length) {
                Swal.fire({ icon: 'warning', title: 'Nothing to save', text: 'Add at least one parts or labor row.' });
                return;
            }

            // Only validate parts rows if there are any
            for (let i = 0; i < partsRows.length; i++) {
                if (!partsRows[i].product_id) {
                    Swal.fire({ icon: 'warning', title: 'Incomplete', text: `Parts row ${i + 1}: Please select a product.` });
                    return;
                }
                if (partsRows[i].qty <= 0) {
                    Swal.fire({ icon: 'warning', title: 'Invalid quantity', text: `Parts row ${i + 1}: Quantity must be at least 1.` });
                    return;
                }
            }

            // Only validate labor rows if there are any
            for (let i = 0; i < laborRows.length; i++) {
                if (!laborRows[i].description.trim()) {
                    Swal.fire({ icon: 'warning', title: 'Incomplete', text: `Labor row ${i + 1}: Please enter a service description.` });
                    return;
                }
                if (laborRows[i].amount <= 0) {
                    Swal.fire({ icon: 'warning', title: 'Invalid amount', text: `Labor row ${i + 1}: Amount must be greater than 0.` });
                    return;
                }
            }

            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving…';

            const items = [
                ...partsRows.map(r => ({
                    type: 'parts',
                    product_id: parseInt(r.product_id),
                    description: PRODUCTS.find(p => String(p.product_id) === String(r.product_id))?.description || '',
                    quantity: r.qty,
                    unit_price: r.unit_price,
                    amount: r.qty * r.unit_price,
                })),
                ...laborRows.map(r => ({
                    type: 'labor',
                    product_id: null,
                    description: r.description.trim(),
                    quantity: 1,
                    unit_price: r.amount,
                    amount: r.amount,
                })),
            ];

            const payload = {
                sale_date: document.getElementById('saleDate').value,
                customer_name: document.getElementById('customerName').value.trim(),
                plate_number: document.getElementById('plateNumber').value.trim().toUpperCase(),
                items,
            };

            try {
                const resp = await fetch('backend/sales.php?action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await resp.json();

                if (data.success) {
                    const lowItems = (data.stock_summary || []).filter(s => s.low_stock);
                    let html = `Sale #${data.sale_id} recorded successfully.`;
                    if (lowItems.length) {
                        html += '<br><small style="color:#f59e0b;">⚠ Low stock: ' +
                            lowItems.map(s => `${s.description} (${s.stock_left} left)`).join(', ') + '</small>';
                    }
                    await Swal.fire({
                        icon: 'success', title: 'Sale Saved!', html,
                        confirmButtonText: 'View History',
                        showCancelButton: true, cancelButtonText: 'New Sale',
                    }).then(r => r.isConfirmed ? (window.location.href = 'sales-history.php') : clearAll());
                } else {
                    Swal.fire({ icon: 'error', title: 'Save Failed', text: data.message });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Network Error', text: err.message });
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-save me-1"></i>Save Sale';
            }
        }

        // Start clean — user picks what they need
        renderParts();
        renderLabor();
    </script>
</body>

</html>