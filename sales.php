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

    // Self-healing: ensure compatible_brand exists even if backend/db.php on the
    // server is an older copy that doesn't create it yet.
    $conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS compatible_brand VARCHAR(50) NULL");

    $products = [];
    $hasBrandCol = $conn->query("SHOW COLUMNS FROM products LIKE 'compatible_brand'")->num_rows > 0;
    $brandSelect = $hasBrandCol ? "p.compatible_brand" : "'' AS compatible_brand";
    $r = $conn->query("
        SELECT p.product_id, p.code, p.description, p.unit, p.selling_price, $brandSelect,
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

    $conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS payment_method ENUM('cash','gcash','credit') NOT NULL DEFAULT 'cash'");
    $conn->query("ALTER TABLE sales MODIFY COLUMN payment_method ENUM('cash','gcash','credit') NOT NULL DEFAULT 'cash'");
    $conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS reference_number VARCHAR(10) NULL");

    // Next reference number for today: 001, 002, 003... resets daily
    // Reference number: continues globally across all days (016, 017, 018...),
    // never resets to 001. Uses MAX+1 (not COUNT+1) so a deleted sale in the
    // middle of the sequence never causes a number to be reused.
    $refMaxRow = $conn->query("SELECT MAX(CAST(reference_number AS UNSIGNED)) AS maxref FROM sales WHERE reference_number REGEXP '^[0-9]+$'")->fetch_assoc();
    $nextRefNumber = str_pad((int) ($refMaxRow['maxref'] ?? 0) + 1, 3, '0', STR_PAD_LEFT);

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
        <link rel="stylesheet" href="assets/css/sales.css?v=3">
        <style>
            /* Inline fallback so the Credit option highlights on tap even if
               assets/css/sales.css hasn't been updated on the server yet. */
            .txn-page .pay-toggle {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .txn-page .pay-toggle > div {
                flex: 1;
                min-width: 120px;
            }
            .txn-page .pay-toggle input[type="radio"] {
                position: absolute;
                opacity: 0;
                width: 0;
                height: 0;
                pointer-events: none;
            }
            .txn-page .pay-toggle label {
                display: flex !important;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 11px 16px;
                border-radius: 10px;
                cursor: pointer;
                width: 100%;
                border: 1.5px solid rgba(255, 255, 255, .1) !important;
                background: rgba(255, 255, 255, .04) !important;
                font-size: .88rem;
                font-weight: 600;
                color: #64748b !important;
                margin: 0;
                transition: all .15s;
            }
            .txn-page .pay-toggle label:hover {
                border-color: rgba(255, 255, 255, .2) !important;
                color: #94a3b8 !important;
            }
            .txn-page .pay-toggle input:checked + label.pay-cash {
                border-color: rgba(74, 222, 128, .55) !important;
                background: rgba(74, 222, 128, .14) !important;
                color: #4ade80 !important;
            }
            .txn-page .pay-toggle input:checked + label.pay-gcash {
                border-color: rgba(96, 165, 250, .55) !important;
                background: rgba(96, 165, 250, .14) !important;
                color: #60a5fa !important;
            }
            .txn-page .pay-toggle input:checked + label.pay-credit {
                border-color: rgba(167, 139, 250, .55) !important;
                background: rgba(167, 139, 250, .14) !important;
                color: #a78bfa !important;
            }
        </style>
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

                <div class="txn-layout">
                    <!-- Left: form cards -->
                    <div class="txn-form-col">

                        <!-- General information -->
                        <div class="txn-card">
                            <div class="info-grid">
                                <div>
                                    <label class="field-label" for="saleDate">Date</label>
                                    <input type="date" class="txn-input" id="saleDate" value="<?= $today ?>"
                                        max="<?= $today ?>">
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
                                <div>
                                    <label class="field-label" for="carModel">Car Model</label>
                                    <input type="text" class="txn-input" id="carModel" placeholder="e.g. Honda Click 125i" oninput="renderParts()">
                                </div>
                                <div>
                                    <label class="field-label" for="refNumber">Reference Number</label>
                                    <input type="text" class="txn-input" id="refNumber" value="<?= $nextRefNumber ?>" readonly>
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
                                                <i class="bi bi-phone-fill"></i> Online Payment
                                            </label>
                                        </div>
                                        <div>
                                            <input type="radio" name="payment" id="pay-credit" value="credit">
                                            <label for="pay-credit" class="pay-credit">
                                                <i class="bi bi-credit-card"></i> Credit
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

        </main>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            'use strict';

            const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
            const TODAY = '<?= $today ?>';
            const KNOWN_BRANDS = ['Honda', 'Yamaha', 'Suzuki', 'Kawasaki', 'Rusi'];

            let parts = [];
            let labors = [];
            let exps = [];
            let uid = 0;

            // Look for a known brand name inside whatever the user typed into Car Model,
            // e.g. "Honda Click 125i" -> "Honda". Case-insensitive, matches anywhere in the text.
            function detectBrand(carModelText) {
                const text = String(carModelText || '').toLowerCase().trim();
                if (!text) return null;
                const found = KNOWN_BRANDS.find(b => text.includes(b.toLowerCase()));
                return found || null;
            }

            function isUniversalPart(p) {
                const b = String(p.compatible_brand || '').trim().toLowerCase();
                return b === '' || b === 'universal';
            }

            function filteredProducts(selectedId) {
                const carModelText = document.getElementById('carModel')?.value || '';
                const detectedBrand = detectBrand(carModelText);

                let list = PRODUCTS;
                if (detectedBrand) {
                    list = PRODUCTS.filter(p =>
                        isUniversalPart(p) ||
                        String(p.compatible_brand).toLowerCase() === detectedBrand.toLowerCase() ||
                        String(p.product_id) === String(selectedId) // never hide a part already chosen on this row
                    );
                }
                return list;
            }

            function partLabel(p) {
                const stock = parseInt(p.current_stock) || 0;
                return `${p.description} (${stock} in stock)`;
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
                    if (type === 'part' && parts[idx]) {
                        el.value = peso(parts[idx].qty * parts[idx].unit_price);
                    } else if (type === 'labor' && labors[idx]) {
                        el.value = peso(laborAmount(labors[idx]));
                    }
                });
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

                wrap.innerHTML = parts.map((row, idx) => {
                    const matched = row.product_id
                        ? PRODUCTS.find(p => String(p.product_id) === String(row.product_id))
                        : null;
                    const initialValue = matched ? partLabel(matched) : '';
                    const listId = `partSearchList-${idx}`;
                    const datalistHtml = filteredProducts(row.product_id)
                        .map(p => `<option value="${esc(partLabel(p))}">`).join('');

                    return `
    <div class="item-line parts-grid">
    <div>
        <label class="field-label">Part</label>
        <input type="text" class="txn-input" list="${listId}" autocomplete="off"
        placeholder="Type to search parts…" value="${esc(initialValue)}"
        oninput="onPartSearchInput(${idx}, this)" onblur="onPartSearchBlur(${idx}, this)">
        <datalist id="${listId}">${datalistHtml}</datalist>
    </div>
    <div>
        <label class="field-label">Qty</label>
        <input type="number" class="txn-input" min="1" value="${row.qty}"
        oninput="parts[${idx}].qty=Math.max(1,parseInt(this.value)||1);recalc();">
    </div>
    <div>
        <label class="field-label">Unit Price</label>
        <input type="number" class="txn-input" id="partPrice-${idx}" min="0" step="0.01" value="${row.unit_price || ''}"
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
    </div>`;
                }).join('');
                recalc();
            }

            function onPartSearchInput(idx, el) {
                const typed = el.value.trim();
                const list = filteredProducts(parts[idx].product_id);
                const match = list.find(p => partLabel(p) === typed);

                if (match) {
                    parts[idx].product_id = match.product_id;
                    parts[idx].description = match.description;
                    parts[idx].unit = match.unit;
                    parts[idx].unit_price = parseFloat(match.selling_price) || 0;
                    el.style.borderColor = 'rgba(74,222,128,.55)';

                    const priceInput = document.getElementById(`partPrice-${idx}`);
                    if (priceInput) priceInput.value = parts[idx].unit_price;

                    recalc();
                } else {
                    parts[idx].product_id = '';
                    parts[idx].description = '';
                    parts[idx].unit_price = 0;
                    el.style.borderColor = typed ? 'rgba(251,191,36,.55)' : '';
                    recalc();
                }
            }

            function onPartSearchBlur(idx, el) {
                // If they leave the field without landing on a real part, clear the
                // stray text so it's obvious a real selection is still needed.
                if (!parts[idx].product_id) {
                    el.value = '';
                    el.style.borderColor = '';
                }
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
                document.getElementById('customerName').value = '';
                document.getElementById('plateNumber').value = '';
                document.getElementById('carModel').value = '';
                document.getElementById('saleDate').value = TODAY;
                document.getElementById('pay-cash').checked = true;
                renderParts();
                renderLabor();
                renderExp();

                // Bump the reference number for the next transaction of the day
                const refInput = document.getElementById('refNumber');
                const nextRef = (parseInt(refInput.value, 10) || 0) + 1;
                refInput.value = String(nextRef).padStart(3, '0');

                recalc();
            }

            async function submitSale() {
                const saleDate = document.getElementById('saleDate').value;

                // A blank labor row (no description, no price) means the user never
                // intended to add a service -- don't treat it as an incomplete entry.
                const activeLabors = labors.filter(r => r.description.trim() !== '' || laborAmount(r) > 0);

                if (!parts.length && !activeLabors.length) {
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

                for (let i = 0; i < activeLabors.length; i++) {
                    const r = activeLabors[i];
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
                    ...activeLabors.map(r => ({
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
                    car_model: document.getElementById('carModel').value.trim(),
                    reference_number: document.getElementById('refNumber').value.trim(),
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
                        let parts = [];
                        if (paymentMethod === 'gcash') parts.push('<span style="color:#60a5fa;">Online Payment</span>');
                        if (paymentMethod === 'credit') parts.push('<span style="color:#a78bfa;">Credit</span>');
                        if (expenses.length) {
                            const expSum = expenses.reduce((s, e) => s + e.amount, 0);
                            parts.push(`<small style="color:#f87171;">Expenses logged: ${peso(expSum)}</small>`);
                        }
                        if (lowItems.length) {
                            parts.push('<small style="color:#fbbf24;">Low stock: '
                                + lowItems.map(s => `${s.description} (${s.stock_left} left)`).join(', ') + '</small>');
                        }
                        const html = parts.join('<br>');
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
        </script>
        <?php include 'footer.php'; ?>

    </body>

    </html>