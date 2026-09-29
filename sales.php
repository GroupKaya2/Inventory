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
    $isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';

    // Self-healing: ensure compatible_brand exists even if backend/db.php on the
    // server is an older copy that doesn't create it yet.
    $conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS compatible_brand VARCHAR(50) NULL");

    $products = [];
    $hasBrandCol = $conn->query("SHOW COLUMNS FROM products LIKE 'compatible_brand'")->num_rows > 0;
    $brandSelect = $hasBrandCol ? "p.compatible_brand" : "'' AS compatible_brand";
    $r = $conn->query("
        SELECT p.product_id, p.code, p.description, p.unit, p.selling_price, p.unit_cost, $brandSelect,
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

    // Self-healing: ensure the services catalog exists even if backend/services.php
    // on the server is an older copy that hasn't created it yet.
    $conn->query("CREATE TABLE IF NOT EXISTS services (
        service_id   INT AUTO_INCREMENT PRIMARY KEY,
        service_name VARCHAR(150) NOT NULL UNIQUE,
        price        DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $services = [];
    $rs = $conn->query("SELECT service_id, service_name, price FROM services ORDER BY service_name ASC");
    if ($rs) {
        while ($row = $rs->fetch_assoc()) {
            $services[] = $row;
        }
    }

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
        <link rel="stylesheet" href="assets/css/sales.css?v=<?= file_exists(__DIR__ . '/assets/css/sales.css') ? filemtime(__DIR__ . '/assets/css/sales.css') : time() ?>">
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
            .txn-page .pay-toggle input:checked + label.pay-split {
                border-color: rgba(251, 191, 36, .55) !important;
                background: rgba(251, 191, 36, .14) !important;
                color: #fbbf24 !important;
            }

            .txn-page .split-payment-panel {
                margin-top: 12px;
                padding: 14px 16px;
                background: rgba(251, 191, 36, .04);
                border: 1px solid rgba(251, 191, 36, .15);
                border-radius: 10px;
                display: none;
            }
            .txn-page .split-payment-panel.active {
                display: block;
            }
            .txn-page .split-payment-row {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 10px;
                margin-bottom: 10px;
            }
            @media (max-width: 576px) {
                .txn-page .split-payment-row { grid-template-columns: 1fr; }
            }
            .txn-page .split-remaining {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: .85rem;
                font-weight: 700;
                padding-top: 8px;
                border-top: 1px solid rgba(255, 255, 255, .08);
            }
            .txn-page .split-remaining.balanced { color: #4ade80; }
            .txn-page .split-remaining.unbalanced { color: #f87171; }

            .txn-page textarea.txn-input {
                min-height: 70px;
                resize: vertical;
                font-family: 'Inter', sans-serif !important;
            }

            .txn-page .outside-toggle-row {
                grid-column: 1 / -1;
                display: flex;
                margin-top: 2px;
            }
            .txn-page .outside-top-row {
                grid-column: 1 / -1;
                display: grid;
                grid-template-columns: minmax(0, 1fr) 90px 44px;
                gap: 10px;
                align-items: end;
            }
            @media (max-width: 767.98px) {
                .txn-page .outside-top-row {
                    grid-template-columns: 1fr 80px 44px;
                }
            }
            .txn-page .pricing-profit-box {
                grid-column: 1 / -1;
                display: block;
                margin-top: 10px;
                background: rgba(96, 165, 250, .06);
                border: 1px solid rgba(96, 165, 250, .18);
                border-radius: 8px;
                padding: 12px 14px;
            }
            .txn-page .pricing-inline-title {
                font-size: .72rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .5px;
                color: #60a5fa;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .txn-page .pricing-grid {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 10px;
            }
            @media (max-width: 767.98px) {
                .txn-page .pricing-grid {
                    grid-template-columns: 1fr 1fr;
                }
            }
            .txn-page .profit-line {
                margin-top: 10px;
                font-size: .82rem;
                font-weight: 700;
                color: #4ade80;
            }
            .txn-page .outside-supplier-row {
                grid-column: 1 / -1;
                display: block;
                margin-top: 8px;
                background: rgba(167, 139, 250, .06);
                border: 1px solid rgba(167, 139, 250, .18);
                border-radius: 8px;
                padding: 12px 14px;
            }
            .txn-page .ap-inline-title {
                font-size: .72rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .5px;
                color: #a78bfa;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .txn-page .ap-inline-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }
            @media (max-width: 767.98px) {
                .txn-page .ap-inline-grid {
                    grid-template-columns: 1fr 1fr;
                }
            }
            .txn-page .btn-outside-toggle {
                background: rgba(167, 139, 250, .08) !important;
                border: 1px solid rgba(167, 139, 250, .25) !important;
                color: #a78bfa !important;
                padding: 6px 12px !important;
                border-radius: 7px !important;
                font-size: .75rem !important;
                font-weight: 600;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                white-space: nowrap;
            }
            .txn-page .btn-outside-toggle:hover {
                background: rgba(167, 139, 250, .16) !important;
            }
            .txn-page .outside-tag {
                display: inline-block;
                margin-left: 6px;
                padding: 1px 8px;
                border-radius: 20px;
                background: rgba(167, 139, 250, .15);
                color: #a78bfa;
                font-size: .64rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .4px;
            }
            .txn-page .item-line.outside-row {
                background: rgba(167, 139, 250, .03);
                border-radius: 8px;
                padding: 12px 8px;
            }

            /* ── Manage Services modal (self-contained -- not inside .txn-page) ── */
            .svc-modal {
                background: #161b27;
                color: #e2e8f0;
                border: 1px solid rgba(74, 222, 128, .18);
                border-radius: 14px;
                overflow: hidden;
            }
            .svc-modal-header {
                background: linear-gradient(135deg, #0f1f15, #111827);
                color: #4ade80;
                border-bottom: 1px solid rgba(74, 222, 128, .15);
                padding: 16px 20px;
            }
            .svc-modal-header .modal-title {
                font-family: 'Space Grotesk', sans-serif;
                font-size: 1rem;
                font-weight: 700;
            }
            .svc-modal-body {
                padding: 20px;
            }
            .svc-modal-footer {
                border-top: 1px solid rgba(255, 255, 255, .08);
                padding: 14px 20px;
            }
            .svc-add-row {
                display: flex;
                align-items: flex-end;
                gap: 10px;
                margin-bottom: 18px;
                padding-bottom: 18px;
                border-bottom: 1px solid rgba(255, 255, 255, .07);
            }
            .svc-add-field {
                flex: 1;
            }
            .svc-add-field.svc-add-price {
                max-width: 120px;
                flex: 0 0 120px;
            }
            .svc-add-row .field-label {
                font-size: .66rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .5px;
                color: #64748b;
                margin-bottom: 6px;
                display: block;
            }
            .svc-input {
                display: block;
                width: 100%;
                min-height: 40px;
                background-color: rgba(255, 255, 255, .06) !important;
                border: 1px solid rgba(255, 255, 255, .1) !important;
                color: #e2e8f0 !important;
                border-radius: 8px !important;
                padding: 9px 12px !important;
                font-size: .85rem !important;
                font-family: 'Inter', sans-serif !important;
                box-shadow: none !important;
                outline: none;
                appearance: none;
                transition: border-color .15s, background-color .15s;
            }
            .svc-input::placeholder {
                color: #4b5a6e !important;
            }
            .svc-input:focus {
                border-color: rgba(74, 222, 128, .4) !important;
                background-color: rgba(255, 255, 255, .08) !important;
                box-shadow: 0 0 0 3px rgba(74, 222, 128, .1) !important;
            }
            .svc-btn-add {
                width: 40px;
                height: 40px;
                min-height: 40px;
                flex-shrink: 0;
                border: 1px solid rgba(74, 222, 128, .35) !important;
                background: linear-gradient(135deg, #16a34a, #052e16) !important;
                color: #4ade80 !important;
                border-radius: 8px !important;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: box-shadow .15s, transform .15s;
            }
            .svc-btn-add:hover {
                box-shadow: 0 0 16px rgba(74, 222, 128, .25);
                transform: translateY(-1px);
            }
            .svc-list {
                max-height: 340px;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .svc-row {
                display: flex;
                align-items: center;
                gap: 8px;
                background: rgba(255, 255, 255, .025);
                border: 1px solid rgba(255, 255, 255, .06);
                border-radius: 10px;
                padding: 8px 10px;
                transition: border-color .15s;
            }
            .svc-row:hover {
                border-color: rgba(74, 222, 128, .18);
            }
            .svc-row .svc-input {
                min-height: 36px;
                padding: 7px 10px !important;
                font-size: .82rem !important;
            }
            .svc-row .svc-row-name {
                flex: 1.6;
            }
            .svc-row .svc-row-price {
                flex: 0 0 100px;
            }
            .svc-row-btn {
                width: 34px;
                height: 34px;
                min-height: 34px;
                flex-shrink: 0;
                border-radius: 7px !important;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: background .15s, color .15s;
            }
            .svc-row-btn.svc-save {
                background: rgba(74, 222, 128, .1) !important;
                border: 1px solid rgba(74, 222, 128, .3) !important;
                color: #4ade80 !important;
            }
            .svc-row-btn.svc-save:hover {
                background: rgba(74, 222, 128, .2) !important;
            }
            .svc-row-btn.svc-delete {
                background: rgba(248, 113, 113, .1) !important;
                border: 1px solid rgba(248, 113, 113, .25) !important;
                color: #f87171 !important;
            }
            .svc-row-btn.svc-delete:hover {
                background: rgba(248, 113, 113, .2) !important;
            }
            .svc-empty {
                text-align: center;
                padding: 30px 12px;
                font-size: .82rem;
                color: #4b5a6e;
            }
            .svc-empty i {
                font-size: 1.8rem;
                display: block;
                margin-bottom: 10px;
                color: #374151;
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
                                    <input type="text" class="txn-input" id="refNumber" value="<?= $nextRefNumber ?>" maxlength="10">
                                </div>
                                <div class="pay-field">
                                    <label class="field-label">Payment Method</label>
                                    <div class="pay-toggle">
                                        <div>
                                            <input type="radio" name="payment" id="pay-cash" value="cash" checked onchange="toggleSplitPanel()">
                                            <label for="pay-cash" class="pay-cash">
                                                <i class="bi bi-cash-coin"></i> Cash
                                            </label>
                                        </div>
                                        <div>
                                            <input type="radio" name="payment" id="pay-gcash" value="gcash" onchange="toggleSplitPanel()">
                                            <label for="pay-gcash" class="pay-gcash">
                                                <i class="bi bi-phone-fill"></i> Online Payment
                                            </label>
                                        </div>
                                        <div>
                                            <input type="radio" name="payment" id="pay-credit" value="credit" onchange="toggleSplitPanel()">
                                            <label for="pay-credit" class="pay-credit">
                                                <i class="bi bi-credit-card"></i> Credit
                                            </label>
                                        </div>
                                        <div>
                                            <input type="radio" name="payment" id="pay-split" value="split" onchange="toggleSplitPanel()">
                                            <label for="pay-split" class="pay-split">
                                                <i class="bi bi-arrow-left-right"></i> Split Payment
                                            </label>
                                        </div>
                                    </div>
                                    <div class="split-payment-panel" id="splitPaymentPanel">
                                        <div class="split-payment-row">
                                            <div>
                                                <label class="field-label"><i class="bi bi-cash-coin" style="color:#4ade80;"></i> Cash</label>
                                                <input type="number" class="txn-input" id="splitCash" min="0" step="0.01" value="0" oninput="updateSplitRemaining()">
                                            </div>
                                            <div>
                                                <label class="field-label"><i class="bi bi-phone-fill" style="color:#60a5fa;"></i> Online</label>
                                                <input type="number" class="txn-input" id="splitGcash" min="0" step="0.01" value="0" oninput="updateSplitRemaining()">
                                            </div>
                                            <div>
                                                <label class="field-label"><i class="bi bi-credit-card" style="color:#a78bfa;"></i> Credit</label>
                                                <input type="number" class="txn-input" id="splitCredit" min="0" step="0.01" value="0" oninput="updateSplitRemaining()">
                                            </div>
                                        </div>
                                        <div class="split-remaining" id="splitRemaining">
                                            <span>Remaining to allocate:</span>
                                            <span id="splitRemainingValue">₱0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="txn-card">
                            <div class="txn-card-head">
                                <h3 class="txn-card-title">
                                    <span class="icon-wrap" style="background:rgba(148,163,184,.15);color:#94a3b8;"><i class="bi bi-sticky"></i></span>
                                    Notes
                                </h3>
                            </div>
                            <textarea class="txn-input" id="saleNotes" placeholder="Optional notes about this transaction — e.g. special instructions, split payment details, follow-ups…"></textarea>
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
                                <div class="d-flex gap-2">
                                    <?php if ($isOwner): ?>
                                        <button type="button" class="btn-add-row" onclick="openManageServices()">
                                            <i class="bi bi-gear"></i> Manage Services
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn-add-row labor" onclick="addLabor()">
                                        <i class="bi bi-plus-lg"></i> Add Labor
                                    </button>
                                </div>
                            </div>
                            <div id="laborWrap"></div>
                            <p id="noLaborMsg" class="empty-hint">
                                No labor yet. Click <strong>Add Labor</strong> for service work.
                            </p>
                            <div id="laborSummaryBlock" class="labor-summary-block" style="display:none;">
                                <div class="labor-summary-line">
                                    <span>Labor Subtotal:</span>
                                    <span id="laborSubtotalDisplay">₱0.00</span>
                                </div>
                                <div class="labor-summary-line">
                                    <span>Labor Discount (₱):</span>
                                    <input type="number" class="txn-input labor-discount-input" min="0" step="0.01"
                                    id="laborDiscountInput" value=""
                                    oninput="laborDiscount=Math.max(0,parseFloat(this.value)||0);recalc();">
                                </div>
                                <hr class="labor-summary-divider">
                                <div class="labor-summary-line labor-summary-net">
                                    <span>Labor Net Total:</span>
                                    <span id="laborNetDisplay">₱0.00</span>
                                </div>
                            </div>
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

                                <div class="sum-line" id="listPriceLine" style="display:none;">
                                    <span class="sum-lbl">
                                        <i class="bi bi-box-seam" style="color:#60a5fa;"></i> Parts Total (List Price)
                                    </span>
                                    <span class="sum-val" id="sumPartsList">₱0.00</span>
                                </div>
                                <div class="sum-line" id="discountSumLine" style="display:none;">
                                    <span class="sum-lbl">
                                        <i class="bi bi-tag" style="color:#f87171;"></i> Less: Parts Discount
                                    </span>
                                    <span class="sum-val" id="sumPartsDiscount" style="color:#f87171;">−₱0.00</span>
                                </div>
                                <div class="sum-line">
                                    <span class="sum-lbl">
                                        <i class="bi bi-box-seam" style="color:#60a5fa;"></i> <span id="sumPartsLabel">Parts Total</span>
                                    </span>
                                    <span class="sum-val parts" id="sumParts">₱0.00</span>
                                </div>
                                <div class="sum-line">
                                    <span class="sum-lbl">
                                        <i class="bi bi-wrench-adjustable" style="color:#4ade80;"></i> Labor / Service Total
                                    </span>
                                    <span class="sum-val labor" id="sumLabor">₱0.00</span>
                                </div>
                                <div class="sum-line" id="expSumLine" style="display:none;">
                                    <span class="sum-lbl">
                                        <i class="bi bi-wallet2" style="color:#f87171;"></i> Expenses
                                    </span>
                                    <span class="sum-val expense" id="sumExp">₱0.00</span>
                                </div>

                                <div class="sum-line" style="border-top:1px dashed rgba(255,255,255,.08);padding-top:14px;margin-top:4px;">
                                    <span class="sum-lbl">
                                        <i class="bi bi-truck" style="color:#fbbf24;"></i> Cost of Parts (Supplier)
                                    </span>
                                    <span class="sum-val" id="sumCost" style="color:#fbbf24;">₱0.00</span>
                                </div>
                                <div class="sum-line">
                                    <span class="sum-lbl">
                                        <i class="bi bi-graph-up-arrow" style="color:#a78bfa;"></i> Gross Profit
                                    </span>
                                    <span class="sum-val" id="sumProfit" style="color:#a78bfa;">₱0.00</span>
                                </div>

                                <div class="sum-grand">
                                    <div class="lbl">Grand Total (Customer Pays)</div>
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

        <?php if ($isOwner): ?>
        <!-- MANAGE SERVICES MODAL -->
        <div class="modal fade" id="servicesModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content svc-modal">
                    <div class="modal-header svc-modal-header">
                        <h5 class="modal-title"><i class="bi bi-wrench-adjustable me-2"></i>Manage Services</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body svc-modal-body">
                        <div class="svc-add-row">
                            <div class="svc-add-field">
                                <label class="field-label">Service Name</label>
                                <input type="text" class="svc-input" id="svcNewName" placeholder="e.g. Oil Change">
                            </div>
                            <div class="svc-add-field svc-add-price">
                                <label class="field-label">Price (₱)</label>
                                <input type="number" class="svc-input" id="svcNewPrice" placeholder="0.00" min="0" step="0.01">
                            </div>
                            <button type="button" class="svc-btn-add" onclick="addService()" title="Add service">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                        <div id="svcListWrap" class="svc-list"></div>
                    </div>
                    <div class="modal-footer svc-modal-footer">
                        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            'use strict';

            const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
            let SERVICES = <?= json_encode($services, JSON_UNESCAPED_UNICODE) ?>;
            const IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;
            const TODAY = '<?= $today ?>';
            const KNOWN_BRANDS = ['Honda', 'Yamaha', 'Suzuki', 'Kawasaki', 'Rusi'];

            let parts = [];
            let labors = [];
            let laborDiscount = 0; // single ₱ discount applied to the whole Labor subtotal
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

            function laborSubtotal() {
                return labors.reduce((s, r) => s + laborAmount(r), 0);
            }

            function laborNetTotal() {
                const sub = laborSubtotal();
                const disc = Math.max(0, Math.min(parseFloat(laborDiscount) || 0, sub));
                return Math.max(0, sub - disc);
            }

            // Net customer-facing total for a parts row: (unit/retail price × qty) − discount.
            // Discount is a flat peso amount off the line, clamped so it can never push
            // the line below ₱0 and never affects Cost Price / Accounts Payable math.
            function partSubtotal(row) {
                const price = row.unit_price || 0;
                return row.qty * price;
            }
            function partDiscount(row) {
                return Math.max(0, Math.min(parseFloat(row.discount) || 0, partSubtotal(row)));
            }
            function partNetTotal(row) {
                return Math.max(0, partSubtotal(row) - partDiscount(row));
            }

            function recalc() {
                const ps = parts.reduce((s, r) => s + partNetTotal(r), 0);
                const partsList = parts.reduce((s, r) => s + partSubtotal(r), 0);
                const partsDiscount = parts.reduce((s, r) => s + partDiscount(r), 0);
                const laborSub = laborSubtotal();
                const ls = laborNetTotal();
                const es = exps.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0);
                const grand = ps + ls;

                // Cost of Parts is what we owe/paid the supplier for every part on
                // this sale -- inventory-sourced parts use the product's unit_cost,
                // outside-purchased parts use their own entered Cost Price. Kept
                // fully separate from the customer-facing retail total (ps) above.
                const partsCost = parts.reduce((s, r) => s + r.qty * (parseFloat(r.costPrice) || 0), 0);
                const grossProfit = ps - partsCost;

                document.getElementById('sumParts').textContent = peso(ps);
                document.getElementById('sumLabor').textContent = peso(ls);
                document.getElementById('sumGrand').textContent = peso(grand);
                document.getElementById('sumCost').textContent = peso(partsCost);
                document.getElementById('sumProfit').textContent = peso(grossProfit);

                // Labor Subtotal / Discount / Net block, shown inline under the labor rows.
                const laborSubEl = document.getElementById('laborSubtotalDisplay');
                const laborNetEl = document.getElementById('laborNetDisplay');
                if (laborSubEl) laborSubEl.textContent = peso(laborSub);
                if (laborNetEl) laborNetEl.textContent = peso(ls);

                // Only show the List Price / Discount breakdown when a discount is
                // actually applied on this transaction -- keeps the summary simple
                // for the common case of a straight, no-discount sale.
                const listLine = document.getElementById('listPriceLine');
                const discLine = document.getElementById('discountSumLine');
                const partsLabel = document.getElementById('sumPartsLabel');
                if (partsDiscount > 0) {
                    listLine.style.display = '';
                    discLine.style.display = '';
                    document.getElementById('sumPartsList').textContent = peso(partsList);
                    document.getElementById('sumPartsDiscount').textContent = '−' + peso(partsDiscount);
                    partsLabel.textContent = 'Parts Total (Net)';
                } else {
                    listLine.style.display = 'none';
                    discLine.style.display = 'none';
                    partsLabel.textContent = 'Parts Total';
                }

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
                        el.value = peso(partNetTotal(parts[idx]));
                    } else if (type === 'labor' && labors[idx]) {
                        el.value = peso(laborAmount(labors[idx]));
                    }
                });

                document.querySelectorAll('[data-ap-balance]').forEach(el => {
                    const idx = parseInt(el.dataset.idx, 10);
                    const r = parts[idx];
                    if (r && r.outside) {
                        const costTotal = r.qty * (r.costPrice || 0);
                        const paid = parseFloat(r.amountPaid) || 0;
                        el.value = peso(Math.max(0, costTotal - paid));
                    }
                });

                document.querySelectorAll('[data-ap-invoice-total]').forEach(el => {
                    const idx = parseInt(el.dataset.idx, 10);
                    const r = parts[idx];
                    if (r && r.outside) el.value = peso(r.qty * (r.costPrice || 0));
                });

                document.querySelectorAll('[data-profit-line]').forEach(el => {
                    const idx = parseInt(el.dataset.idx, 10);
                    const r = parts[idx];
                    if (r && r.outside) {
                        // Profit = what the customer actually pays (net of discount) minus
                        // what we owe the supplier (Cost Price × Qty) -- Cost Price / AP
                        // itself is never touched by the discount.
                        const costTotal = r.qty * (r.costPrice || 0);
                        const profit = Math.max(0, partNetTotal(r) - costTotal);
                        el.textContent = `Profit: ${peso(profit)}`;
                    }
                });

                updateSplitRemaining();
            }

            function toggleSplitPanel() {
                const isSplit = document.getElementById('pay-split')?.checked;
                const panel = document.getElementById('splitPaymentPanel');
                if (panel) panel.classList.toggle('active', !!isSplit);
                updateSplitRemaining();
            }

            function currentGrandTotal() {
                const ps = parts.reduce((s, r) => s + partNetTotal(r), 0);
                const ls = laborNetTotal();
                return ps + ls;
            }

            function updateSplitRemaining() {
                const panel = document.getElementById('splitPaymentPanel');
                if (!panel || !panel.classList.contains('active')) return;

                const grand  = currentGrandTotal();
                const cash   = parseFloat(document.getElementById('splitCash').value)   || 0;
                const gcash  = parseFloat(document.getElementById('splitGcash').value)  || 0;
                const credit = parseFloat(document.getElementById('splitCredit').value) || 0;
                const remaining = grand - (cash + gcash + credit);

                const el = document.getElementById('splitRemainingValue');
                const wrap = document.getElementById('splitRemaining');
                el.textContent = peso(Math.abs(remaining));
                if (Math.abs(remaining) < 0.01) {
                    wrap.classList.remove('unbalanced');
                    wrap.classList.add('balanced');
                    el.textContent = '✓ Fully allocated';
                } else {
                    wrap.classList.remove('balanced');
                    wrap.classList.add('unbalanced');
                    el.textContent = (remaining > 0 ? peso(remaining) + ' left to allocate' : 'Over by ' + peso(-remaining));
                }
            }

            function addPart() {
                parts.push({
                    id: ++uid, product_id: '', description: '', unit: '', qty: 1, unit_price: 0,
                    outside: false, supplier: '', invoiceNumber: '', mode: 'credit', dueDate: '', amountPaid: 0,
                    costPrice: 0, markupPercent: 0, discount: 0,
                });
                renderParts();
            }
            function removePart(idx) { parts.splice(idx, 1); renderParts(); }

            // Marks a parts row as bought from an outside shop rather than pulled
            // from inventory. Outside rows never touch stock and instead get
            // logged to Accounts Payable when the sale is saved.
            function togglePartOutside(idx) {
                parts[idx].outside = !parts[idx].outside;
                if (parts[idx].outside) {
                    parts[idx].product_id = '';
                } else {
                    parts[idx].supplier = '';
                    parts[idx].description = '';
                    parts[idx].invoiceNumber = '';
                    parts[idx].mode = 'credit';
                    parts[idx].dueDate = '';
                    parts[idx].amountPaid = 0;
                    parts[idx].costPrice = 0;
                    parts[idx].markupPercent = 0;
                    parts[idx].unit_price = 0;
                    parts[idx].discount = 0;
                }
                renderParts();
            }

            // Outside-purchased parts price off Cost + Markup % by default, but the
            // Unit Price (Retail) field can also be typed into directly to override
            // this for a one-off sale -- whichever was edited most recently wins.
            function updateOutsidePricing(idx) {
                const row = parts[idx];
                if (!row) return;
                row.unit_price = row.costPrice * (1 + (row.markupPercent || 0) / 100);
                const priceInput = document.getElementById(`outsidePrice-${idx}`);
                if (priceInput) priceInput.value = row.unit_price ? row.unit_price.toFixed(2) : '';
                recalc();
            }

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
                    if (row.outside) {
                        const retail = row.unit_price || 0;
                        const subtotal = row.qty * retail;
                        const discount = Math.max(0, Math.min(parseFloat(row.discount) || 0, subtotal));
                        const total = Math.max(0, subtotal - discount);
                        // AP tracks what we owe the SUPPLIER, not what the customer pays --
                        // always Cost Price x Qty, kept fully separate from the (discounted)
                        // retail total above.
                        const costTotal = row.qty * (row.costPrice || 0);
                        const profit = Math.max(0, total - costTotal);
                        const paid = parseFloat(row.amountPaid) || 0;
                        const balance = Math.max(0, costTotal - paid);
                        return `
    <div class="item-line parts-grid outside-row">
    <div class="outside-top-row">
        <div class="outside-desc-col">
            <label class="field-label">Description<span class="outside-tag">Purchased Outside</span></label>
            <input type="text" class="txn-input" placeholder="e.g. Custom decal set" value="${esc(row.description)}"
            oninput="parts[${idx}].description=this.value;">
        </div>
        <div class="outside-qty-col">
            <label class="field-label">Qty</label>
            <input type="number" class="txn-input" min="1" value="${row.qty}"
            oninput="parts[${idx}].qty=Math.max(1,parseInt(this.value)||1);recalc();">
        </div>
        <div class="outside-del-col">
            <label class="field-label">&nbsp;</label>
            <button type="button" class="btn-remove" onclick="removePart(${idx})" title="Remove"><i class="bi bi-trash"></i></button>
        </div>
    </div>

    <div class="pricing-profit-box">
        <div class="pricing-inline-title"><i class="bi bi-graph-up-arrow"></i> Pricing &amp; Profit</div>
        <div class="pricing-grid">
            <div>
                <label class="field-label">Cost Price (₱)</label>
                <input type="number" class="txn-input" min="0" step="0.01" value="${row.costPrice || ''}"
                oninput="parts[${idx}].costPrice=parseFloat(this.value)||0;updateOutsidePricing(${idx});">
            </div>
            <div>
                <label class="field-label">Markup %</label>
                <input type="number" class="txn-input" min="0" step="0.01" value="${row.markupPercent || ''}"
                oninput="parts[${idx}].markupPercent=parseFloat(this.value)||0;updateOutsidePricing(${idx});">
            </div>
            <div>
                <label class="field-label">Unit Price (Retail)</label>
                <input type="number" class="txn-input" min="0" step="0.01" id="outsidePrice-${idx}"
                value="${retail || ''}"
                oninput="parts[${idx}].unit_price=parseFloat(this.value)||0;recalc();">
            </div>
            <div>
                <label class="field-label">Discount (₱)</label>
                <input type="number" class="txn-input" min="0" step="0.01" value="${row.discount || ''}"
                oninput="parts[${idx}].discount=Math.max(0,parseFloat(this.value)||0);recalc();">
            </div>
            <div>
                <label class="field-label">Total</label>
                <input type="text" class="txn-input line-total" readonly data-line-total data-line-type="part" data-idx="${idx}"
                value="${peso(total)}">
            </div>
        </div>
        <div class="profit-line" data-profit-line data-idx="${idx}">Profit: ${peso(profit)}</div>
    </div>

    <div class="outside-supplier-row">
        <div class="ap-inline-title"><i class="bi bi-receipt"></i> Accounts Payable Details</div>
        <div class="ap-inline-grid">
            <div>
                <label class="field-label">Supplier / Vendor Name</label>
                <input type="text" class="txn-input" placeholder="e.g. Shell Philippines" value="${esc(row.supplier || '')}"
                oninput="parts[${idx}].supplier=this.value;">
            </div>
            <div>
                <label class="field-label">Invoice Number</label>
                <input type="text" class="txn-input" placeholder="Optional" value="${esc(row.invoiceNumber || '')}"
                oninput="parts[${idx}].invoiceNumber=this.value;">
            </div>
            <div>
                <label class="field-label">Invoice Total (₱)</label>
                <input type="text" class="txn-input line-total" readonly data-ap-invoice-total data-idx="${idx}"
                value="${peso(costTotal)}">
            </div>
            <div>
                <label class="field-label">Mode of Payment</label>
                <select class="txn-input txn-select" onchange="parts[${idx}].mode=this.value;">
                    <option value="credit" ${row.mode === 'credit' ? 'selected' : ''}>Credit</option>
                    <option value="cash" ${row.mode === 'cash' ? 'selected' : ''}>Cash</option>
                    <option value="gcash" ${row.mode === 'gcash' ? 'selected' : ''}>GCash</option>
                    <option value="bank_transfer" ${row.mode === 'bank_transfer' ? 'selected' : ''}>Bank Transfer</option>
                    <option value="check" ${row.mode === 'check' ? 'selected' : ''}>Check</option>
                </select>
            </div>
            <div>
                <label class="field-label">Due Date</label>
                <input type="date" class="txn-input" value="${row.dueDate || ''}"
                onchange="parts[${idx}].dueDate=this.value;">
            </div>
            <div>
                <label class="field-label">Amount Paid (₱)</label>
                <input type="number" class="txn-input" min="0" step="0.01" value="${row.amountPaid || ''}"
                oninput="parts[${idx}].amountPaid=parseFloat(this.value)||0;recalc();">
            </div>
            <div>
                <label class="field-label">Balance Owed to Supplier</label>
                <input type="text" class="txn-input" readonly data-ap-balance data-idx="${idx}"
                value="${peso(balance)}" style="color:#f87171;font-weight:700;">
            </div>
        </div>
        <button type="button" class="btn-outside-toggle mt-2" onclick="togglePartOutside(${idx})">
            <i class="bi bi-box-seam"></i> Use Inventory Instead
        </button>
    </div>
    </div>`;
                    }

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
        <label class="field-label">Discount (₱)</label>
        <input type="number" class="txn-input" id="partDiscount-${idx}" min="0" step="0.01" value="${row.discount || ''}"
        oninput="parts[${idx}].discount=Math.max(0,parseFloat(this.value)||0);recalc();">
    </div>
    <div>
        <label class="field-label">Total</label>
        <input type="text" class="txn-input line-total" readonly data-line-total data-line-type="part" data-idx="${idx}"
        value="${peso(partNetTotal(row))}">
    </div>
    <div class="col-del">
        <label class="field-label">&nbsp;</label>
        <button type="button" class="btn-remove" onclick="removePart(${idx})" title="Remove"><i class="bi bi-trash"></i></button>
    </div>
    <div class="outside-toggle-row">
        <button type="button" class="btn-outside-toggle" onclick="togglePartOutside(${idx})">
            <i class="bi bi-shop"></i> Purchased Outside?
        </button>
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
                    parts[idx].costPrice = parseFloat(match.unit_cost) || 0;
                    el.style.borderColor = 'rgba(74,222,128,.55)';

                    const priceInput = document.getElementById(`partPrice-${idx}`);
                    if (priceInput) priceInput.value = parts[idx].unit_price;

                    recalc();
                } else {
                    parts[idx].product_id = '';
                    parts[idx].description = '';
                    parts[idx].unit_price = 0;
                    parts[idx].costPrice = 0;
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

            function serviceLabel(s) {
                return `${s.service_name} (₱${parseFloat(s.price).toFixed(2)})`;
            }

            // Matches typed text against a pre-set service. On a match, locks in the
            // fixed price. On no match, the typed text is kept as-is as a free-form
            // custom service -- manual entry stays fully supported.
            function onServiceSearchInput(idx, el) {
                const typed = el.value;
                const match = SERVICES.find(s => serviceLabel(s) === typed.trim());

                if (match) {
                    labors[idx].description = match.service_name;
                    labors[idx].unit_price = parseFloat(match.price) || 0;
                    el.style.borderColor = 'rgba(74,222,128,.55)';

                    const priceInput = document.getElementById(`laborPrice-${idx}`);
                    if (priceInput) priceInput.value = labors[idx].unit_price;

                    recalc();
                } else {
                    labors[idx].description = typed;
                    el.style.borderColor = '';
                    recalc();
                }
            }

            function renderLabor() {
                const wrap = document.getElementById('laborWrap');
                const noMsg = document.getElementById('noLaborMsg');
                const summaryBlock = document.getElementById('laborSummaryBlock');
                if (!labors.length) {
                    wrap.innerHTML = '';
                    noMsg.style.display = '';
                    if (summaryBlock) summaryBlock.style.display = 'none';
                    recalc();
                    return;
                }
                noMsg.style.display = 'none';
                if (summaryBlock) summaryBlock.style.display = '';

                wrap.innerHTML = labors.map((row, idx) => {
                    const matchedService = SERVICES.find(s => s.service_name === row.description);
                    const initialValue = matchedService ? serviceLabel(matchedService) : row.description;
                    const listId = `serviceSearchList-${idx}`;
                    const datalistHtml = SERVICES.map(s => `<option value="${esc(serviceLabel(s))}">`).join('');

                    return `
    <div class="item-line labor-grid">
    <div>
        <label class="field-label">Service</label>
        <input type="text" class="txn-input" list="${listId}" autocomplete="off" value="${esc(initialValue)}"
        placeholder="Search a service or type your own…"
        oninput="onServiceSearchInput(${idx}, this)">
        <datalist id="${listId}">${datalistHtml}</datalist>
    </div>
    <div>
        <label class="field-label">Qty</label>
        <input type="number" class="txn-input" min="1" value="${row.qty}"
        oninput="labors[${idx}].qty=Math.max(1,parseInt(this.value)||1);recalc();">
    </div>
    <div>
        <label class="field-label">Price</label>
        <input type="number" class="txn-input" id="laborPrice-${idx}" min="0" step="0.01" value="${row.unit_price || ''}"
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
    </div>`;
                }).join('');
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
                laborDiscount = 0;
                const laborDiscInput = document.getElementById('laborDiscountInput');
                if (laborDiscInput) laborDiscInput.value = '';
                document.getElementById('customerName').value = '';
                document.getElementById('plateNumber').value = '';
                document.getElementById('carModel').value = '';
                document.getElementById('saleDate').value = TODAY;
                document.getElementById('pay-cash').checked = true;
                document.getElementById('saleNotes').value = '';
                document.getElementById('splitCash').value = 0;
                document.getElementById('splitGcash').value = 0;
                document.getElementById('splitCredit').value = 0;
                toggleSplitPanel();
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
                    if (r.outside) {
                        if (!r.description.trim()) {
                            Swal.fire({ icon: 'warning', title: 'Incomplete', text: `Parts row ${i + 1}: enter a description.` });
                            return;
                        }
                        if (!r.supplier || !r.supplier.trim()) {
                            Swal.fire({ icon: 'warning', title: 'Incomplete', text: `Parts row ${i + 1}: enter the supplier / shop name.` });
                            return;
                        }
                        if (!r.costPrice || r.costPrice <= 0) {
                            Swal.fire({ icon: 'warning', title: 'Invalid', text: `Parts row ${i + 1}: enter a cost price greater than 0.` });
                            return;
                        }
                    } else if (!r.product_id) {
                        Swal.fire({ icon: 'warning', title: 'Incomplete', text: `Parts row ${i + 1}: select a part.` });
                        return;
                    }
                    if (r.qty < 1) {
                        Swal.fire({ icon: 'warning', title: 'Invalid', text: `Parts row ${i + 1}: quantity must be at least 1.` });
                        return;
                    }
                    const priceCheck = r.unit_price;
                    if (priceCheck <= 0) {
                        const msg = r.outside
                            ? `Parts row ${i + 1}: retail price came out to 0 -- check the cost price and markup %.`
                            : `Parts row ${i + 1}: retail price must be greater than 0.`;
                        Swal.fire({ icon: 'warning', title: 'Invalid', text: msg });
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

                let splitCash = 0, splitGcash = 0, splitCredit = 0;
                if (paymentMethod === 'split') {
                    splitCash   = parseFloat(document.getElementById('splitCash').value)   || 0;
                    splitGcash  = parseFloat(document.getElementById('splitGcash').value)  || 0;
                    splitCredit = parseFloat(document.getElementById('splitCredit').value) || 0;
                    const grandNow = currentGrandTotal();
                    const allocated = splitCash + splitGcash + splitCredit;
                    if (Math.abs(grandNow - allocated) > 0.01) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Split payment doesn\'t match total',
                            text: `Grand Total is ${peso(grandNow)} but the split amounts add up to ${peso(allocated)}. Adjust the split before saving.`,
                        });
                        return;
                    }
                }

                const btn = document.getElementById('submitBtn');
                btn.disabled = true;
                btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';

                const items = [
                    ...parts.map(r => {
                        const price = r.unit_price || 0;
                        return {
                            type: 'parts',
                            product_id: r.outside ? null : parseInt(r.product_id, 10),
                            description: r.outside
                                ? r.description.trim()
                                : (PRODUCTS.find(p => String(p.product_id) === String(r.product_id))?.description || r.description),
                            quantity: r.qty,
                            unit_price: price,
                            discount: partDiscount(r),
                            amount: partNetTotal(r),
                        };
                    }),
                    ...activeLabors.map(r => ({
                        type: 'labor',
                        product_id: null,
                        description: r.description.trim(),
                        quantity: r.qty,
                        unit_price: r.unit_price,
                        discount: 0,
                        amount: laborAmount(r),
                    })),
                ];

                // Single Labor Discount applies to the whole section, not any one line --
                // append it as its own negative line so the sum of labor item amounts
                // (which backend totals into sales.labor_total) nets out correctly, while
                // each real service line keeps its own true, undiscounted amount for
                // accurate per-service reporting.
                const laborDiscAmt = Math.max(0, Math.min(parseFloat(laborDiscount) || 0, laborSubtotal()));
                if (laborDiscAmt > 0 && activeLabors.length) {
                    items.push({
                        type: 'labor',
                        product_id: null,
                        description: 'Labor Discount',
                        quantity: 1,
                        unit_price: -laborDiscAmt,
                        discount: 0,
                        amount: -laborDiscAmt,
                    });
                }

                const expenses = exps
                    .filter(e => e.amount > 0 && e.description.trim())
                    .map(e => ({ description: e.description.trim(), amount: e.amount }));

                // Each outside-purchased part row carries its own full Accounts
                // Payable details (edited inline), so each becomes its own invoice.
                const apRefLabel = document.getElementById('refNumber').value.trim();
                const outsideInvoices = parts
                    .filter(r => r.outside && r.description.trim() && r.supplier && r.supplier.trim())
                    .map(r => {
                        // AP is what we owe the SUPPLIER -- always Cost Price x Qty,
                        // never the customer-facing retail total. Mixing the two
                        // would inflate our liability by our own markup.
                        const total = r.qty * (r.costPrice || 0);
                        const paid = Math.min(parseFloat(r.amountPaid) || 0, total);
                        return {
                            supplier_name: r.supplier.trim(),
                            invoice_number: (r.invoiceNumber || '').trim() || ('REF-' + apRefLabel),
                            invoice_date: saleDate,
                            due_date: r.dueDate || '',
                            mode_of_payment: r.mode || 'credit',
                            total_amount: total,
                            amount_paid: paid,
                            notes: `Auto-logged from Daily Transaction (Ref #${apRefLabel}): ${r.description.trim()} x${r.qty}`,
                        };
                    });

                const payload = {
                    sale_date: saleDate,
                    customer_name: document.getElementById('customerName').value.trim(),
                    plate_number: document.getElementById('plateNumber').value.trim().toUpperCase(),
                    car_model: document.getElementById('carModel').value.trim(),
                    reference_number: document.getElementById('refNumber').value.trim(),
                    payment_method: paymentMethod,
                    cash_amount: splitCash,
                    gcash_amount: splitGcash,
                    credit_amount: splitCredit,
                    notes: document.getElementById('saleNotes').value.trim(),
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
                        // Log each outside-purchased row to Accounts Payable now that
                        // the sale itself has been saved successfully.
                        for (const inv of outsideInvoices) {
                            try {
                                await fetch('backend/accounts-payable.php?action=add', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify(inv),
                                });
                            } catch (apErr) {
                                console.error('Accounts Payable entry failed for', inv.supplier_name, apErr);
                            }
                        }

                        const lowItems = (data.stock_summary || []).filter(s => s.low_stock);
                        let msgParts = [];
                        if (paymentMethod === 'gcash') msgParts.push('<span style="color:#60a5fa;">Online Payment</span>');
                        if (paymentMethod === 'credit') msgParts.push('<span style="color:#a78bfa;">Credit</span>');
                        if (expenses.length) {
                            const expSum = expenses.reduce((s, e) => s + e.amount, 0);
                            msgParts.push(`<small style="color:#f87171;">Expenses logged: ${peso(expSum)}</small>`);
                        }
                        if (lowItems.length) {
                            msgParts.push('<small style="color:#fbbf24;">Low stock: '
                                + lowItems.map(s => `${s.description} (${s.stock_left} left)`).join(', ') + '</small>');
                        }
                        if (outsideInvoices.length) {
                            const apTotal = outsideInvoices.reduce((s, inv) => s + inv.total_amount, 0);
                            msgParts.push(`<small style="color:#a78bfa;">Accounts Payable logged: ${peso(apTotal)} across ${outsideInvoices.length} invoice(s)</small>`);
                        }
                        const html = msgParts.join('<br>');
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

            // ── Manage Services (Owner only) ─────────────────────────────
            function openManageServices() {
                renderServicesList();
                new bootstrap.Modal(document.getElementById('servicesModal')).show();
            }

            function renderServicesList() {
                const wrap = document.getElementById('svcListWrap');
                if (!wrap) return;
                if (!SERVICES.length) {
                    wrap.innerHTML = `
    <div class="svc-empty">
        <i class="bi bi-wrench-adjustable"></i>
        No services yet. Add one above to start filling the Labor dropdown.
    </div>`;
                    return;
                }
                wrap.innerHTML = SERVICES.map(s => `
    <div class="svc-row">
        <input type="text" class="svc-input svc-row-name" value="${esc(s.service_name)}" id="svcName-${s.service_id}">
        <input type="number" class="svc-input svc-row-price" value="${s.price}" min="0" step="0.01" id="svcPrice-${s.service_id}">
        <button type="button" class="svc-row-btn svc-save" onclick="saveServiceEdit(${s.service_id})" title="Save"><i class="bi bi-check-lg"></i></button>
        <button type="button" class="svc-row-btn svc-delete" onclick="deleteService(${s.service_id})" title="Delete"><i class="bi bi-trash"></i></button>
    </div>`).join('');
            }

            async function refreshServices() {
                try {
                    const res = await fetch('backend/services.php?action=fetch');
                    const json = await res.json();
                    if (json.success) SERVICES = json.data || [];
                } catch (e) {
                    console.error('Failed to refresh services', e);
                }
            }

            async function addService() {
                const name = document.getElementById('svcNewName').value.trim();
                const price = parseFloat(document.getElementById('svcNewPrice').value) || 0;
                if (!name) { Swal.fire({ icon: 'warning', title: 'Required', text: 'Enter a service name.' }); return; }

                const fd = new FormData();
                fd.append('service_name', name);
                fd.append('price', price);
                const res = await fetch('backend/services.php?action=add', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    document.getElementById('svcNewName').value = '';
                    document.getElementById('svcNewPrice').value = '';
                    await refreshServices();
                    renderServicesList();
                    renderLabor();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: json.message });
                }
            }

            async function saveServiceEdit(id) {
                const name = document.getElementById(`svcName-${id}`).value.trim();
                const price = parseFloat(document.getElementById(`svcPrice-${id}`).value) || 0;
                if (!name) { Swal.fire({ icon: 'warning', title: 'Required', text: 'Service name cannot be blank.' }); return; }

                const fd = new FormData();
                fd.append('service_id', id);
                fd.append('service_name', name);
                fd.append('price', price);
                const res = await fetch('backend/services.php?action=update', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    await refreshServices();
                    renderServicesList();
                    renderLabor();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: json.message });
                }
            }

            async function deleteService(id) {
                const result = await Swal.fire({
                    title: 'Delete this service?',
                    text: 'This only removes it from the dropdown -- past transactions are unaffected.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    confirmButtonText: 'Delete',
                });
                if (!result.isConfirmed) return;

                const fd = new FormData();
                fd.append('service_id', id);
                const res = await fetch('backend/services.php?action=delete', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    await refreshServices();
                    renderServicesList();
                    renderLabor();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: json.message });
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