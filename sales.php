<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$activePage = 'sales';
$today = date('Y-m-d');
$todayDow = (int) date('N');

$products = [];
$r = $conn->query("
    SELECT p.product_id, p.code, p.description, p.unit, p.selling_price,
           COALESCE(ps.current_stock,
               p.initial_quantity + COALESCE((
                   SELECT SUM(t.quantity_change)
                   FROM inventory_transactions t
                   WHERE t.product_id = p.product_id
               ), 0)
           ) AS current_stock
    FROM products p
    LEFT JOIN product_stock ps ON p.product_id = ps.product_id
    ORDER BY p.description ASC
");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $products[] = $row;
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'Other',
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS payment_method ENUM('cash','gcash') NOT NULL DEFAULT 'cash'");

$savedExpenses = [];
$re = $conn->query("SELECT id, description, amount FROM expenses WHERE expense_date = '$today' ORDER BY id DESC");
if ($re) {
    while ($row = $re->fetch_assoc()) {
        $savedExpenses[] = $row;
    }
}
$savedExpTotal = array_sum(array_column($savedExpenses, 'amount'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Transaction — DSpeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/sales.css?v=2">
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <main class="app-main txn-page">

        <header class="txn-page-header">
            <div>
                <h4><i class="bi bi-receipt-cutoff me-2"></i>Daily Transaction</h4>
                <p class="subtitle">Record a new car care service sale</p>
            </div>
            <a href="sales-history.php" class="btn-history">
                <i class="bi bi-clock-history"></i> Sales History
            </a>
        </header>

        <?php if ($todayDow === 7): ?>
            <div class="txn-card">
                <div class="sunday-screen">
                    <i class="bi bi-moon-stars-fill"></i>
                    <h5 style="color:#e2e8f0;margin-bottom:8px;">Shop is Closed Today</h5>
                    <p style="color:#64748b;max-width:340px;margin:0 auto;font-size:.88rem;">
                        No transactions are recorded on Sundays. Come back Monday!
                    </p>
                </div>
            </div>
        <?php else: ?>

            <div class="txn-layout">
                <!-- Left: form cards -->
                <div class="txn-form-col">

                    <!-- General information -->
                    <div class="txn-card">
                        <div class="info-grid">
                            <div>
                                <label class="field-label" for="saleDate">Date</label>
                                <input type="date" class="txn-input" id="saleDate" value="<?= $today ?>"
                                    max="<?= $today ?>" onchange="guardSunday(this)">
                            </div>
                            <div>
                                <label class="field-label" for="customerName">Customer Name</label>
                                <input type="text" class="txn-input" id="customerName" placeholder="Optional">
                            </div>
                            <div>
                                <label class="field-label" for="plateNumber">Plate Number</label>
                                <input type="text" class="txn-input" id="plateNumber" placeholder="e.g. ABC 1234"
                                    style="text-transform:uppercase;">
                            </div>
                            <div class="pay-field">
                                <label class="field-label">Payment Method</label>
                                <div class="pay-toggle">
                                    <div>
                                        <input type="radio" name="payment" id="pay-cash" value="cash" checked>
                                        <label for="pay-cash" class="pay-cash">
                                            <i class="bi bi-cash-coin"></i> Cash
                                        </label>
                                    </div>
                                    <div>
                                        <input type="radio" name="payment" id="pay-gcash" value="gcash">
                                        <label for="pay-gcash" class="pay-gcash">
                                            <i class="bi bi-phone-fill"></i> GCash
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Parts -->
                    <div class="txn-card">
                        <div class="txn-card-head">
                            <h3 class="txn-card-title">
                                <span class="icon-wrap parts"><i class="bi bi-box-seam"></i></span>
                                Parts Used
                            </h3>
                            <button type="button" class="btn-add-row parts" onclick="addPart()">
                                <i class="bi bi-plus-lg"></i> Add Part
                            </button>
                        </div>
                        <div id="partsWrap"></div>
                        <p id="noPartsMsg" class="empty-hint">
                            No parts yet. Click <strong>Add Part</strong> to add inventory items.
                        </p>
                    </div>

                    <!-- Labor -->
                    <div class="txn-card">
                        <div class="txn-card-head">
                            <h3 class="txn-card-title">
                                <span class="icon-wrap labor"><i class="bi bi-wrench-adjustable"></i></span>
                                Labor / Services
                            </h3>
                            <button type="button" class="btn-add-row labor" onclick="addLabor()">
                                <i class="bi bi-plus-lg"></i> Add Labor
                            </button>
                        </div>
                        <div id="laborWrap"></div>
                        <p id="noLaborMsg" class="empty-hint">
                            No labor yet. Click <strong>Add Labor</strong> for service work.
                        </p>
                    </div>

                    <!-- Expenses -->
                    <div class="txn-card">
                        <div class="txn-card-head">
                            <h3 class="txn-card-title">
                                <span class="icon-wrap expense"><i class="bi bi-wallet2"></i></span>
                                Expenses <span style="font-weight:400;color:#4b5a6e;font-size:.78rem;">(optional)</span>
                            </h3>
                            <button type="button" class="btn-add-row expense" onclick="addExp()">
                                <i class="bi bi-plus-lg"></i> Add Expense
                            </button>
                        </div>
                        <div id="expWrap"></div>
                        <p id="noExpMsg" class="empty-hint">
                            No expenses for this transaction.
                        </p>

                        <?php if (!empty($savedExpenses)): ?>
                            <div class="saved-exp-block">
                                <div class="title"><i class="bi bi-clock me-1"></i>Already saved today</div>
                                <?php foreach ($savedExpenses as $e): ?>
                                    <div class="d-flex justify-content-between py-1" style="color:#94a3b8;">
                                        <span><?= htmlspecialchars($e['description']) ?></span>
                                        <span class="cv-exp" style="font-weight:600;">₱<?= number_format($e['amount'], 2) ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <div class="text-end mt-1" style="font-size:.8rem;color:#f87171;font-weight:700;">
                                    Saved total: ₱<?= number_format($savedExpTotal, 2) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Right: sale summary -->
                <div class="txn-summary-col">
                    <div class="summary-sticky">
                        <div class="summary-card">
                            <div class="sum-head">
                                <span class="icon-wrap"><i class="bi bi-receipt"></i></span>
                                Sale Summary
                            </div>

                            <div class="sum-line">
                                <span class="sum-lbl">
                                    <i class="bi bi-box-seam" style="color:#60a5fa;"></i> Parts Total
                                </span>
                                <span class="sum-val parts" id="sumParts">₱0.00</span>
                            </div>
                            <div class="sum-line">
                                <span class="sum-lbl">
                                    <i class="bi bi-wrench-adjustable" style="color:#4ade80;"></i> Labor Total
                                </span>
                                <span class="sum-val labor" id="sumLabor">₱0.00</span>
                            </div>
                            <div class="sum-line" id="expSumLine" style="display:none;">
                                <span class="sum-lbl">
                                    <i class="bi bi-wallet2" style="color:#f87171;"></i> Expenses
                                </span>
                                <span class="sum-val expense" id="sumExp">₱0.00</span>
                            </div>

                            <div class="sum-grand">
                                <div class="lbl">Grand Total</div>
                                <div class="val" id="sumGrand">₱0.00</div>
                            </div>

                            <button type="button" class="btn-save-txn" id="submitBtn" onclick="submitSale()">
                                <i class="bi bi-check-circle-fill"></i> Save Transaction
                            </button>

                            <button type="button" class="btn-add-row w-100 mt-2" onclick="clearAll()"
                                style="justify-content:center;">
                                <i class="bi bi-arrow-counterclockwise"></i> Clear Form
                            </button>

                            <a href="sales-history.php" class="link-back">
                                <i class="bi bi-arrow-left me-1"></i>Back to History
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        'use strict';

        const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
        const TODAY = '<?= $today ?>';

        let parts = [];
        let labors = [];
        let exps = [];
        let uid = 0;

        function productOptions(selectedId) {
            let html = '<option value="">-- Select Part --</option>';
            PRODUCTS.forEach(p => {
                const sel = String(p.product_id) === String(selectedId) ? ' selected' : '';
                const stock = parseInt(p.current_stock) || 0;
                html += `<option value="${p.product_id}" data-price="${p.selling_price}" data-unit="${esc(p.unit)}" data-desc="${esc(p.description)}"${sel}>${esc(p.description)} (${stock} in stock)</option>`;
            });
            return html;
        }

        function guardSunday(el) {
            const d = new Date(el.value + 'T12:00:00');
            if (d.getDay() === 0) {
                Swal.fire({ icon: 'warning', title: 'Shop Closed Sunday', text: 'No transactions allowed on Sundays.' });
                el.value = TODAY;
            }
        }

        function peso(n) {
            return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function esc(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function laborAmount(row) {
            return (parseInt(row.qty) || 1) * (parseFloat(row.unit_price) || 0);
        }

        function recalc() {
            const ps = parts.reduce((s, r) => s + r.qty * r.unit_price, 0);
            const ls = labors.reduce((s, r) => s + laborAmount(r), 0);
            const es = exps.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0);
            const grand = ps + ls;

            document.getElementById('sumParts').textContent = peso(ps);
            document.getElementById('sumLabor').textContent = peso(ls);
            document.getElementById('sumGrand').textContent = peso(grand);

            const expLine = document.getElementById('expSumLine');
            if (es > 0) {
                expLine.style.display = '';
                document.getElementById('sumExp').textContent = peso(es);
            } else {
                expLine.style.display = 'none';
            }

            document.querySelectorAll('[data-line-total]').forEach(el => {
                const type = el.dataset.lineType;
                const idx = parseInt(el.dataset.idx, 10);
                if (type === 'part') {
                    el.value = peso(parts[idx].qty * parts[idx].unit_price);
                } else if (type === 'labor') {
                    el.value = peso(laborAmount(labors[idx]));
                }
            });
        }

        function onPartSelect(idx, sel) {
            const opt = sel.options[sel.selectedIndex];
            if (!opt.value) {
                parts[idx].product_id = '';
                parts[idx].description = '';
                parts[idx].unit = '';
                parts[idx].unit_price = 0;
            } else {
                parts[idx].product_id = opt.value;
                parts[idx].description = opt.dataset.desc || '';
                parts[idx].unit = opt.dataset.unit || '';
                parts[idx].unit_price = parseFloat(opt.dataset.price) || 0;
            }
            renderParts();
        }

        function addPart() {
            parts.push({ id: ++uid, product_id: '', description: '', unit: '', qty: 1, unit_price: 0 });
            renderParts();
        }
        function removePart(idx) { parts.splice(idx, 1); renderParts(); }

        function renderParts() {
            const wrap = document.getElementById('partsWrap');
            const noMsg = document.getElementById('noPartsMsg');
            if (!parts.length) {
                wrap.innerHTML = '';
                noMsg.style.display = '';
                recalc();
                return;
            }
            noMsg.style.display = 'none';

            wrap.innerHTML = parts.map((row, idx) => `
<div class="item-line parts-grid">
  <div>
    <label class="field-label">Part</label>
    <select class="txn-select" onchange="onPartSelect(${idx}, this)">${productOptions(row.product_id)}</select>
  </div>
  <div>
    <label class="field-label">Qty</label>
    <input type="number" class="txn-input" min="1" value="${row.qty}"
      oninput="parts[${idx}].qty=Math.max(1,parseInt(this.value)||1);recalc();">
  </div>
  <div>
    <label class="field-label">Unit Price</label>
    <input type="number" class="txn-input" min="0" step="0.01" value="${row.unit_price || ''}"
      oninput="parts[${idx}].unit_price=parseFloat(this.value)||0;recalc();">
  </div>
  <div>
    <label class="field-label">Total</label>
    <input type="text" class="txn-input line-total" readonly data-line-total data-line-type="part" data-idx="${idx}"
      value="${peso(row.qty * row.unit_price)}">
  </div>
  <div class="col-del">
    <label class="field-label">&nbsp;</label>
    <button type="button" class="btn-remove" onclick="removePart(${idx})" title="Remove"><i class="bi bi-trash"></i></button>
  </div>
</div>`).join('');
            recalc();
        }

        function addLabor() {
            labors.push({ id: ++uid, description: '', qty: 1, unit_price: 0 });
            renderLabor();
        }
        function removeLabor(idx) { labors.splice(idx, 1); renderLabor(); }

        function renderLabor() {
            const wrap = document.getElementById('laborWrap');
            const noMsg = document.getElementById('noLaborMsg');
            if (!labors.length) {
                wrap.innerHTML = '';
                noMsg.style.display = '';
                recalc();
                return;
            }
            noMsg.style.display = 'none';

            wrap.innerHTML = labors.map((row, idx) => `
<div class="item-line labor-grid">
  <div>
    <label class="field-label">Service</label>
    <input type="text" class="txn-input" value="${esc(row.description)}"
      placeholder="e.g. Oil Change"
      oninput="labors[${idx}].description=this.value;">
  </div>
  <div>
    <label class="field-label">Qty</label>
    <input type="number" class="txn-input" min="1" value="${row.qty}"
      oninput="labors[${idx}].qty=Math.max(1,parseInt(this.value)||1);recalc();">
  </div>
  <div>
    <label class="field-label">Price</label>
    <input type="number" class="txn-input" min="0" step="0.01" value="${row.unit_price || ''}"
      oninput="labors[${idx}].unit_price=parseFloat(this.value)||0;recalc();">
  </div>
  <div>
    <label class="field-label">Total</label>
    <input type="text" class="txn-input line-total" readonly data-line-total data-line-type="labor" data-idx="${idx}"
      value="${peso(laborAmount(row))}">
  </div>
  <div class="col-del">
    <label class="field-label">&nbsp;</label>
    <button type="button" class="btn-remove" onclick="removeLabor(${idx})" title="Remove"><i class="bi bi-trash"></i></button>
  </div>
</div>`).join('');
            recalc();
        }

        function addExp() {
            exps.push({ id: ++uid, description: '', amount: 0 });
            renderExp();
        }
        function removeExp(idx) { exps.splice(idx, 1); renderExp(); }

        function renderExp() {
            const wrap = document.getElementById('expWrap');
            const noMsg = document.getElementById('noExpMsg');
            if (!exps.length) {
                wrap.innerHTML = '';
                noMsg.style.display = '';
                recalc();
                return;
            }
            noMsg.style.display = 'none';

            wrap.innerHTML = exps.map((row, idx) => `
<div class="item-line exp-grid">
  <div>
    <label class="field-label">Description</label>
    <input type="text" class="txn-input" value="${esc(row.description)}"
      placeholder="Expense description"
      oninput="exps[${idx}].description=this.value;">
  </div>
  <div>
    <label class="field-label">Amount</label>
    <input type="number" class="txn-input" min="0" step="0.01" value="${row.amount || ''}"
      placeholder="0.00"
      oninput="exps[${idx}].amount=parseFloat(this.value)||0;recalc();">
  </div>
  <div class="col-del">
    <label class="field-label">&nbsp;</label>
    <button type="button" class="btn-remove" onclick="removeExp(${idx})" title="Remove"><i class="bi bi-trash"></i></button>
  </div>
</div>`).join('');
            recalc();
        }

        function clearAll() {
            parts = [];
            labors = [];
            exps = [];
            renderParts();
            renderLabor();
            renderExp();
            document.getElementById('customerName').value = '';
            document.getElementById('plateNumber').value = '';
            document.getElementById('saleDate').value = TODAY;
            document.getElementById('pay-cash').checked = true;
            recalc();
        }

        async function submitSale() {
            const saleDate = document.getElementById('saleDate').value;

            if (new Date(saleDate + 'T12:00:00').getDay() === 0) {
                Swal.fire({ icon: 'warning', title: 'Shop Closed', text: 'No transactions on Sundays.' });
                return;
            }

            if (!parts.length && !labors.length) {
                Swal.fire({ icon: 'warning', title: 'Nothing to save', text: 'Add at least one part or labor row.' });
                return;
            }

            for (let i = 0; i < parts.length; i++) {
                const r = parts[i];
                if (!r.product_id) {
                    Swal.fire({ icon: 'warning', title: 'Incomplete', text: `Parts row ${i + 1}: select a part.` });
                    return;
                }
                if (r.qty < 1) {
                    Swal.fire({ icon: 'warning', title: 'Invalid', text: `Parts row ${i + 1}: quantity must be at least 1.` });
                    return;
                }
                if (r.unit_price <= 0) {
                    Swal.fire({ icon: 'warning', title: 'Invalid', text: `Parts row ${i + 1}: unit price must be greater than 0.` });
                    return;
                }
            }

            for (let i = 0; i < labors.length; i++) {
                const r = labors[i];
                const amt = laborAmount(r);
                if (!r.description.trim()) {
                    Swal.fire({ icon: 'warning', title: 'Incomplete', text: `Labor row ${i + 1}: enter a service description.` });
                    return;
                }
                if (amt <= 0) {
                    Swal.fire({ icon: 'warning', title: 'Invalid', text: `Labor row ${i + 1}: price must be greater than 0.` });
                    return;
                }
            }

            for (let i = 0; i < exps.length; i++) {
                const e = exps[i];
                if (e.amount > 0 && e.description.trim().length < 3) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Expense description required',
                        text: `Expense row ${i + 1}: describe what the money was used for (min. 3 characters).`
                    });
                    return;
                }
            }

            const paymentMethod = document.querySelector('input[name="payment"]:checked')?.value || 'cash';
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';

            const items = [
                ...parts.map(r => ({
                    type: 'parts',
                    product_id: parseInt(r.product_id, 10),
                    description: PRODUCTS.find(p => String(p.product_id) === String(r.product_id))?.description || r.description,
                    quantity: r.qty,
                    unit_price: r.unit_price,
                    amount: r.qty * r.unit_price,
                })),
                ...labors.map(r => ({
                    type: 'labor',
                    product_id: null,
                    description: r.description.trim(),
                    quantity: r.qty,
                    unit_price: r.unit_price,
                    amount: laborAmount(r),
                })),
            ];

            const expenses = exps
                .filter(e => e.amount > 0 && e.description.trim())
                .map(e => ({ description: e.description.trim(), amount: e.amount }));

            const payload = {
                sale_date: saleDate,
                customer_name: document.getElementById('customerName').value.trim(),
                plate_number: document.getElementById('plateNumber').value.trim().toUpperCase(),
                payment_method: paymentMethod,
                items,
                expenses,
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
                    let html = `Transaction <strong>#${data.sale_id}</strong> saved!`;
                    if (paymentMethod === 'gcash') html += ' <span style="color:#60a5fa;">(GCash)</span>';
                    if (expenses.length) {
                        const expSum = expenses.reduce((s, e) => s + e.amount, 0);
                        html += `<br><small style="color:#f87171;">Expenses logged: ${peso(expSum)}</small>`;
                    }
                    if (lowItems.length) {
                        html += '<br><small style="color:#fbbf24;">Low stock: '
                            + lowItems.map(s => `${s.description} (${s.stock_left} left)`).join(', ') + '</small>';
                    }
                    const res = await Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        html,
                        showCancelButton: true,
                        confirmButtonText: 'View History',
                        cancelButtonText: 'New Transaction',
                    });
                    if (res.isConfirmed) window.location.href = 'sales-history.php';
                    else clearAll();
                } else {
                    Swal.fire({ icon: 'error', title: 'Save Failed', text: data.message });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Network Error', text: err.message });
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Save Transaction';
            }
        }

        renderParts();
        renderLabor();
        renderExp();
        recalc();

        // Client-side Sunday guard
        function guardSunday(dateInput) {
            if (!dateInput.value) return;
            const date = new Date(dateInput.value);
            const dayOfWeek = date.getDay();
            if (dayOfWeek === 7) { // Sunday
                Swal.fire({
                    icon: 'warning',
                    title: 'Shop is Closed on Sundays',
                    text: 'Please select a different date.',
                    confirmButtonColor: '#e8175d'
                }).then(() => {
                    dateInput.value = '<?= $today ?>';
                });
            }
        }

        // Check on page load
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('saleDate');
            if (dateInput) {
                guardSunday(dateInput);
            }
        });
    </script>
</body>

</html>
