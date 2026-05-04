<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$activePage  = 'sales';
$today       = date('Y-m-d');
$todayDow    = (int) date('N'); // 1=Mon … 7=Sun

// Fetch all products for the searchable dropdown
$products = [];
$r = $conn->query("
    SELECT p.product_id, p.code, p.description, p.unit, p.selling_price,
           COALESCE(ps.current_stock, 0) AS current_stock
    FROM products p
    LEFT JOIN product_stock ps ON p.product_id = ps.product_id
    ORDER BY p.description ASC
");
if ($r) while ($row = $r->fetch_assoc()) $products[] = $row;

// Ensure expenses table exists
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

// Ensure payment_method column exists
$conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS payment_method ENUM('cash','gcash') NOT NULL DEFAULT 'cash'");

// Today's saved expenses (to display under the expense section)
$savedExpenses = [];
$re = $conn->query("SELECT id, description, amount FROM expenses WHERE expense_date = '$today' ORDER BY id DESC");
if ($re) while ($row = $re->fetch_assoc()) $savedExpenses[] = $row;
$savedExpTotal = array_sum(array_column($savedExpenses, 'amount'));

// Today's gross sales so far (for context)
$todaySales = (float) $conn->query("
    SELECT COALESCE(SUM(parts_total + labor_total), 0) AS t FROM sales WHERE sale_date = '$today'
")->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Transaction — Dispeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/sales.css">
    <style>
        /* ── Section headings ── */
        .sec-label {
            display:inline-flex;align-items:center;gap:7px;
            font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;
            padding:5px 12px;border-radius:6px;margin-bottom:14px;
        }
        .sec-parts    { background:rgba(59,130,246,.14);  color:#93c5fd; }
        .sec-labor    { background:rgba(16,185,129,.14);  color:#34d399; }
        .sec-expenses { background:rgba(239,68,68,.14);   color:#fca5a5; }
        .sec-payment  { background:rgba(245,158,11,.14);  color:#fcd34d; }

        /* ── Net bar ── */
        .net-bar {
            background:linear-gradient(135deg,rgba(16,185,129,.1),rgba(16,185,129,.03));
            border:1px solid rgba(16,185,129,.22);
            border-radius:12px;padding:14px 20px;
            display:flex;flex-wrap:wrap;gap:16px;align-items:center;
            margin-bottom:16px;
        }
        .nb-item .lbl { font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499; }
        .nb-item .val { font-family:'Syne',sans-serif;font-size:.98rem;font-weight:700;color:#fff; }
        .nb-item .val.big { font-size:1.25rem; }
        .nb-sep { color:#4a5568;font-size:1.1rem;align-self:flex-end;padding-bottom:2px; }

        /* ── Payment toggle ── */
        .pay-toggle { display:flex;gap:10px; }
        .pay-toggle > div { flex:1; }
        .pay-toggle input { display:none; }
        .pay-toggle label {
            display:flex;align-items:center;justify-content:center;gap:8px;
            padding:11px 16px;border-radius:10px;cursor:pointer;width:100%;
            border:1.5px solid rgba(255,255,255,.1);
            font-size:.88rem;font-weight:600;color:#7a8499;transition:all .15s;
        }
        .pay-toggle input:checked + label {
            border-color:#e8175d;background:rgba(232,23,93,.13);color:#fff;
        }
        .pay-toggle label:hover { border-color:rgba(232,23,93,.4);color:#e8ecf4; }

        /* ── Product searchable dropdown ── */
        .psearch-wrap { position:relative; }
        .psearch-input {
            width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);
            color:#e8ecf4;border-radius:8px;padding:9px 13px;font-size:.85rem;
            font-family:'DM Sans',sans-serif;transition:border-color .15s;
        }
        .psearch-input:focus { outline:none;border-color:rgba(232,23,93,.5);background:rgba(255,255,255,.09);
            box-shadow:0 0 0 3px rgba(232,23,93,.12); }
        .psearch-input::placeholder { color:#7a8499; }
        .p-drop {
            position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:300;
            background:#1c2030;border:1px solid rgba(255,255,255,.12);
            border-radius:10px;max-height:230px;overflow-y:auto;
            display:none;box-shadow:0 10px 40px rgba(0,0,0,.5);
        }
        .p-drop.open { display:block; }
        .p-opt {
            padding:9px 14px;cursor:pointer;
            border-bottom:1px solid rgba(255,255,255,.05);
            transition:background .1s;
        }
        .p-opt:last-child { border-bottom:none; }
        .p-opt:hover { background:rgba(232,23,93,.12); }
        .p-opt .p-name { font-size:.83rem;color:#e8ecf4;font-weight:500; }
        .p-opt .p-meta { font-size:.7rem;color:#7a8499;margin-top:1px; }

        /* ── Row wrappers ── */
        .row-wrap {
            background:rgba(255,255,255,.025);border:1px solid rgba(255,255,255,.07);
            border-radius:10px;padding:14px 16px;margin-bottom:10px;
        }

        /* ── Expense rows ── */
        .exp-grid { display:grid;grid-template-columns:1fr 130px 42px;gap:10px;align-items:end; }
        @media(max-width:560px){ .exp-grid { grid-template-columns:1fr 1fr; } }

        .dark-input {
            background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);
            color:#e8ecf4;border-radius:8px;padding:9px 13px;font-size:.85rem;
            font-family:'DM Sans',sans-serif;width:100%;transition:border-color .15s;
        }
        .dark-input:focus { outline:none;border-color:rgba(232,23,93,.5);
            box-shadow:0 0 0 3px rgba(232,23,93,.12);background:rgba(255,255,255,.09); }
        .dark-input::placeholder { color:#7a8499; }

        /* ── Sunday screen ── */
        .sunday-screen {
            text-align:center;padding:70px 20px;
        }
        .sunday-screen i { font-size:4rem;color:#4a5568;display:block;margin-bottom:18px; }

        /* ── Transaction ID badge ── */
        .txn-badge { font-size:.72rem;color:#7a8499;font-family:'DM Sans',monospace; }
        .txn-badge span { color:#e8175d;font-weight:700; }

        /* ── Today so far pill ── */
        .today-pill {
            display:inline-flex;align-items:center;gap:6px;
            background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);
            border-radius:8px;padding:5px 12px;font-size:.78rem;color:#7a8499;
        }
        .today-pill strong { color:#e8175d; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="app-main">

    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 style="margin:0;"><i class="bi bi-receipt me-2"></i>Daily Transaction</h4>
                <p style="margin:0;"><?= date('l, F j, Y') ?></p>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <?php if ($todaySales > 0): ?>
                <div class="today-pill">
                    Today so far: <strong>₱<?= number_format($todaySales, 2) ?></strong>
                </div>
                <?php endif; ?>
                <a href="sales-history.php" class="btn-ghost" style="color:#fff;">
                    <i class="bi bi-clock-history me-1"></i>History
                </a>
                <a href="monthly-report.php" class="btn-ghost" style="color:#fff;">
                    <i class="bi bi-calendar3 me-1"></i>Monthly Report
                </a>
            </div>
        </div>
    </div>

    <?php if ($todayDow === 7): ?>
    <!-- ══════════ SUNDAY BLOCK ══════════ -->
    <div class="sale-card">
        <div class="sunday-screen">
            <i class="bi bi-moon-stars-fill"></i>
            <h5 style="color:#e8ecf4;margin-bottom:8px;">Shop is Closed Today</h5>
            <p style="color:#7a8499;max-width:340px;margin:0 auto;font-size:.88rem;">
                No transactions are recorded on Sundays. Come back Monday!
            </p>
        </div>
    </div>
    <?php else: ?>
    <!-- ══════════ MAIN FORM ══════════ -->

    <!-- ── 1. Basic Info ── -->
    <div class="sale-card mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label" style="color:#7a8499;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">
                    Transaction Date
                </label>
                <input type="date" class="dark-input" id="saleDate"
                    value="<?= $today ?>" max="<?= $today ?>"
                    onchange="guardSunday(this)">
            </div>
            <div class="col-md-4">
                <label class="form-label" style="color:#7a8499;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">
                    Customer Name
                </label>
                <input type="text" class="dark-input" id="customerName" placeholder="Optional">
            </div>
            <div class="col-md-3">
                <label class="form-label" style="color:#7a8499;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">
                    Plate Number
                </label>
                <input type="text" class="dark-input" id="plateNumber"
                    placeholder="e.g. ABC-123" style="text-transform:uppercase;">
            </div>
            <div class="col-md-2">
                <div class="txn-badge">TXN&nbsp;ID: <span>#AUTO</span></div>
                <div style="font-size:.65rem;color:#4a5568;margin-top:2px;">assigned on save</div>
            </div>
        </div>
    </div>

    <!-- ── 2. Parts / Products ── -->
    <div class="sale-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <div class="sec-label sec-parts"><i class="bi bi-box-seam"></i>Parts / Products</div>
            <button type="button" class="btn-pink" style="font-size:.8rem;padding:7px 15px;" onclick="addPart()">
                <i class="bi bi-plus-lg me-1"></i>Add Part
            </button>
        </div>
        <div id="partsWrap"></div>
        <p id="noPartsMsg" style="text-align:center;color:#7a8499;padding:14px 0;font-size:.84rem;margin:0;">
            No parts added. Click <strong style="color:#e8ecf4;">Add Part</strong> to begin.
        </p>
        <div class="d-flex justify-content-end mt-2" style="font-size:.83rem;font-weight:700;color:#93c5fd;">
            Parts Subtotal: <span id="partsTotal" style="margin-left:8px;">₱0.00</span>
        </div>
    </div>

    <!-- ── 3. Labor / Services ── -->
    <div class="sale-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <div class="sec-label sec-labor"><i class="bi bi-wrench-adjustable"></i>Labor / Services</div>
            <button type="button" class="btn-pink"
                style="font-size:.8rem;padding:7px 15px;background:linear-gradient(135deg,#10b981,#059669);"
                onclick="addLabor()">
                <i class="bi bi-plus-lg me-1"></i>Add Labor
            </button>
        </div>
        <div id="laborWrap"></div>
        <p id="noLaborMsg" style="text-align:center;color:#7a8499;padding:14px 0;font-size:.84rem;margin:0;">
            No labor added. Click <strong style="color:#e8ecf4;">Add Labor</strong> to begin.
        </p>
        <div class="d-flex justify-content-end mt-2" style="font-size:.83rem;font-weight:700;color:#34d399;">
            Labor Subtotal: <span id="laborTotal" style="margin-left:8px;">₱0.00</span>
        </div>
    </div>

    <!-- ── 4. Daily Expenses ── -->
    <div class="sale-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <div class="sec-label sec-expenses"><i class="bi bi-wallet2"></i>Daily Expenses</div>
            <button type="button" class="btn-ghost"
                style="font-size:.8rem;padding:7px 15px;border-color:rgba(239,68,68,.3);color:#fca5a5;"
                onclick="addExp()">
                <i class="bi bi-plus-lg me-1"></i>Add Expense
            </button>
        </div>
        <div id="expWrap"></div>
        <p id="noExpMsg" style="text-align:center;color:#7a8499;padding:14px 0;font-size:.84rem;margin:0;">
            No expenses to log. Expenses reduce your net daily sales.
        </p>

        <?php if (!empty($savedExpenses)): ?>
        <!-- Previously saved today -->
        <div style="margin-top:12px;padding:12px 14px;background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.12);border-radius:10px;">
            <div style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;margin-bottom:8px;">
                <i class="bi bi-clock me-1"></i>Already saved today
            </div>
            <?php foreach ($savedExpenses as $e): ?>
            <div style="display:flex;justify-content:space-between;font-size:.82rem;padding:3px 0;">
                <span style="color:#94a3b8;"><?= htmlspecialchars($e['description']) ?></span>
                <span style="color:#fca5a5;font-weight:600;">₱<?= number_format($e['amount'], 2) ?></span>
            </div>
            <?php endforeach; ?>
            <div style="border-top:1px solid rgba(239,68,68,.15);margin-top:6px;padding-top:6px;font-size:.8rem;color:#fca5a5;font-weight:700;text-align:right;">
                Saved total: ₱<?= number_format($savedExpTotal, 2) ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-end mt-2" style="font-size:.83rem;font-weight:700;color:#fca5a5;">
            Expenses Subtotal: <span id="expTotal" style="margin-left:8px;">₱0.00</span>
        </div>
    </div>

    <!-- ── 5. Payment Method ── -->
    <div class="sale-card mb-3">
        <div class="sec-label sec-payment mb-3"><i class="bi bi-credit-card-2-front"></i>Payment Method</div>
        <div class="pay-toggle">
            <div>
                <input type="radio" name="payment" id="pay-cash" value="cash" checked>
                <label for="pay-cash">
                    <i class="bi bi-cash-coin" style="font-size:1.15rem;"></i> Cash
                </label>
            </div>
            <div>
                <input type="radio" name="payment" id="pay-gcash" value="gcash">
                <label for="pay-gcash">
                    <i class="bi bi-phone-fill" style="font-size:1.15rem;"></i> GCash
                </label>
            </div>
        </div>
    </div>

    <!-- ── 6. Net Daily Sales Summary + Save ── -->
    <div class="sale-card">
        <!-- Net bar -->
        <div class="net-bar">
            <div class="nb-item">
                <div class="lbl">Parts</div>
                <div class="val" id="nb-parts">₱0.00</div>
            </div>
            <div class="nb-sep">+</div>
            <div class="nb-item">
                <div class="lbl">Labor</div>
                <div class="val" id="nb-labor">₱0.00</div>
            </div>
            <div class="nb-sep">−</div>
            <div class="nb-item">
                <div class="lbl">Expenses</div>
                <div class="val" style="color:#fca5a5;" id="nb-exp">₱0.00</div>
            </div>
            <div class="nb-sep">=</div>
            <div class="nb-item">
                <div class="lbl">Net Daily Sales</div>
                <div class="val big" id="nb-net">₱0.00</div>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end flex-wrap">
            <button type="button" class="btn-ghost" onclick="clearAll()">
                <i class="bi bi-trash me-1"></i>Clear All
            </button>
            <button type="button" class="btn-pink" id="submitBtn" onclick="submitSale()"
                style="padding:10px 28px;font-size:.95rem;">
                <i class="bi bi-save me-1"></i>Save Transaction
            </button>
        </div>
    </div>

    <?php endif; // end Sunday guard ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
'use strict';

/* ── Product data from PHP ── */
const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
const TODAY    = '<?= $today ?>';

let parts  = [];   // { id, product_id, description, unit, qty, unit_price }
let labors = [];   // { id, description, amount }
let exps   = [];   // { id, description, amount }
let uid    = 0;

/* ─────────── SUNDAY GUARD ─────────── */
function guardSunday(el) {
    const d = new Date(el.value + 'T00:00:00');
    if (d.getDay() === 0) {
        Swal.fire({ icon:'warning', title:'Shop Closed Sunday', text:'No transactions allowed on Sundays.' });
        el.value = TODAY;
    }
}

/* ─────────── HELPERS ─────────── */
function peso(n) {
    return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits:2, maximumFractionDigits:2 });
}
function esc(s) { return (s || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }
function escJS(s) { return (s || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"'); }
function darkInput(extra) {
    return `background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:#e8ecf4;border-radius:8px;padding:9px 13px;font-size:.85rem;font-family:'DM Sans',sans-serif;width:100%;${extra||''}`;
}

/* ─────────── RECALC ─────────── */
function recalc() {
    const ps = parts.reduce((s, r)  => s + r.qty * r.unit_price, 0);
    const ls = labors.reduce((s, r) => s + r.amount, 0);
    const es = exps.reduce((s, r)   => s + r.amount, 0);
    const net = ps + ls - es;

    document.getElementById('partsTotal').textContent = peso(ps);
    document.getElementById('laborTotal').textContent = peso(ls);
    document.getElementById('expTotal').textContent   = peso(es);
    document.getElementById('nb-parts').textContent  = peso(ps);
    document.getElementById('nb-labor').textContent  = peso(ls);
    document.getElementById('nb-exp').textContent    = peso(es);

    const netEl = document.getElementById('nb-net');
    netEl.textContent = peso(Math.max(0, net));
    netEl.style.color = net >= 0 ? '#34d399' : '#fca5a5';
}

/* ═══════════════════════════════
   PARTS
═══════════════════════════════ */
function addPart() {
    parts.push({ id: ++uid, product_id:'', description:'', unit:'', qty:1, unit_price:0 });
    renderParts();
}
function removePart(idx) { parts.splice(idx, 1); renderParts(); }

function renderParts() {
    const wrap = document.getElementById('partsWrap');
    const noMsg = document.getElementById('noPartsMsg');
    if (!parts.length) { wrap.innerHTML=''; noMsg.style.display=''; recalc(); return; }
    noMsg.style.display = 'none';

    wrap.innerHTML = parts.map((row, idx) => `
<div class="row-wrap">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-5">
      <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;display:block;margin-bottom:5px;">Product</label>
      <div class="psearch-wrap" id="psw-${idx}">
        <input type="text" class="psearch-input" id="ps-${idx}"
          value="${esc(row.description)}"
          placeholder="Search by name or code…"
          autocomplete="off"
          oninput="filterPDrop(${idx},this.value)"
          onfocus="openPDrop(${idx})">
        <input type="hidden" id="pid-${idx}" value="${row.product_id}">
        <input type="hidden" id="punit-${idx}" value="${esc(row.unit)}">
        <div class="p-drop" id="pd-${idx}"></div>
      </div>
      ${row.unit ? `<div style="font-size:.7rem;color:#7a8499;margin-top:3px;">Unit: ${row.unit}</div>` : ''}
    </div>
    <div class="col-5 col-md-2">
      <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;display:block;margin-bottom:5px;">Qty</label>
      <input type="number" min="1" value="${row.qty}"
        style="${darkInput()}"
        oninput="parts[${idx}].qty=Math.max(1,parseInt(this.value)||1);recalc();">
    </div>
    <div class="col-7 col-md-3">
      <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;display:block;margin-bottom:5px;">Unit Price (₱)</label>
      <input type="number" min="0" step="0.01" value="${row.unit_price}"
        style="${darkInput()}"
        oninput="parts[${idx}].unit_price=parseFloat(this.value)||0;recalc();">
    </div>
    <div class="col-12 col-md-2 d-flex justify-content-between align-items-end">
      <div style="font-size:.85rem;color:#93c5fd;font-weight:700;">${peso(row.qty * row.unit_price)}</div>
      <button type="button" class="btn-remove" onclick="removePart(${idx})"><i class="bi bi-x-lg"></i></button>
    </div>
  </div>
</div>`).join('');
    recalc();
}

/* Product dropdown */
function openPDrop(idx) {
    const q = (document.getElementById('ps-' + idx)?.value || '').toLowerCase().trim();
    buildDrop(idx, q);
}
function filterPDrop(idx, q) {
    parts[idx].description = q;
    parts[idx].product_id  = '';
    document.getElementById('pid-' + idx).value   = '';
    document.getElementById('punit-' + idx).value = '';
    buildDrop(idx, q.toLowerCase());
}
function buildDrop(idx, q) {
    const drop = document.getElementById('pd-' + idx);
    if (!drop) return;
    const list = q
        ? PRODUCTS.filter(p => p.description.toLowerCase().includes(q) || (p.code||'').toLowerCase().includes(q))
        : PRODUCTS;
    if (!list.length) {
        drop.innerHTML = '<div class="p-opt" style="color:#7a8499;cursor:default;">No results</div>';
    } else {
        drop.innerHTML = list.slice(0,25).map(p => `
<div class="p-opt" onmousedown="selectProd(${idx},${p.product_id},'${escJS(p.description)}','${escJS(p.unit)}',${p.selling_price})">
  <div class="p-name">${p.description}</div>
  <div class="p-meta">${p.code ? p.code + ' · ' : ''}${p.unit} · Stock: ${p.current_stock}</div>
</div>`).join('');
    }
    drop.classList.add('open');
}
function selectProd(idx, pid, desc, unit, price) {
    parts[idx].product_id  = pid;
    parts[idx].description = desc;
    parts[idx].unit        = unit;
    parts[idx].unit_price  = price;
    document.getElementById('pd-' + idx)?.classList.remove('open');
    renderParts();
}
document.addEventListener('click', e => {
    if (!e.target.closest('.psearch-wrap'))
        document.querySelectorAll('.p-drop').forEach(d => d.classList.remove('open'));
});

/* ═══════════════════════════════
   LABOR
═══════════════════════════════ */
function addLabor() {
    labors.push({ id: ++uid, description:'', amount:0 });
    renderLabor();
}
function removeLabor(idx) { labors.splice(idx, 1); renderLabor(); }

function renderLabor() {
    const wrap  = document.getElementById('laborWrap');
    const noMsg = document.getElementById('noLaborMsg');
    if (!labors.length) { wrap.innerHTML=''; noMsg.style.display=''; recalc(); return; }
    noMsg.style.display = 'none';
    wrap.innerHTML = labors.map((row, idx) => `
<div class="row-wrap">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-7">
      <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;display:block;margin-bottom:5px;">Service Description</label>
      <input type="text" class="dark-input" value="${esc(row.description)}"
        placeholder="e.g. Oil Change, Tire Rotation…"
        oninput="labors[${idx}].description=this.value;recalc();">
    </div>
    <div class="col-8 col-md-3">
      <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;display:block;margin-bottom:5px;">Amount (₱)</label>
      <input type="number" min="0" step="0.01" value="${row.amount}" class="dark-input"
        oninput="labors[${idx}].amount=parseFloat(this.value)||0;recalc();">
    </div>
    <div class="col-4 col-md-2 d-flex align-items-end justify-content-end">
      <button type="button" class="btn-remove" onclick="removeLabor(${idx})"><i class="bi bi-x-lg"></i></button>
    </div>
  </div>
</div>`).join('');
    recalc();
}

/* ═══════════════════════════════
   EXPENSES
═══════════════════════════════ */
function addExp() {
    exps.push({ id: ++uid, description:'', amount:0 });
    renderExp();
}
function removeExp(idx) { exps.splice(idx, 1); renderExp(); }

function renderExp() {
    const wrap  = document.getElementById('expWrap');
    const noMsg = document.getElementById('noExpMsg');
    if (!exps.length) { wrap.innerHTML=''; noMsg.style.display=''; recalc(); return; }
    noMsg.style.display = 'none';
    wrap.innerHTML = exps.map((row, idx) => `
<div class="exp-grid mb-2">
  <input type="text" class="dark-input" value="${esc(row.description)}"
    placeholder="What was this expense for? (required)"
    oninput="exps[${idx}].description=this.value;recalc();">
  <input type="number" min="0" step="0.01" value="${row.amount||''}" class="dark-input"
    placeholder="Amount"
    oninput="exps[${idx}].amount=parseFloat(this.value)||0;recalc();">
  <button type="button" class="btn-remove" onclick="removeExp(${idx})"><i class="bi bi-x-lg"></i></button>
</div>`).join('');
    recalc();
}

/* ─────────── CLEAR ALL ─────────── */
function clearAll() {
    parts = []; labors = []; exps = [];
    renderParts(); renderLabor(); renderExp();
    document.getElementById('customerName').value = '';
    document.getElementById('plateNumber').value  = '';
    document.getElementById('saleDate').value     = TODAY;
    document.getElementById('pay-cash').checked   = true;
    recalc();
}

/* ─────────── SUBMIT ─────────── */
async function submitSale() {
    const saleDate = document.getElementById('saleDate').value;

    /* Sunday re-check (server also validates) */
    if (new Date(saleDate + 'T00:00:00').getDay() === 0) {
        Swal.fire({ icon:'warning', title:'Shop Closed', text:'No transactions on Sundays.' }); return;
    }

    if (!parts.length && !labors.length) {
        Swal.fire({ icon:'warning', title:'Nothing to save', text:'Add at least one parts or labor row.' }); return;
    }

    /* Validate parts */
    for (let i = 0; i < parts.length; i++) {
        const r = parts[i];
        if (!r.product_id) {
            Swal.fire({ icon:'warning', title:'Incomplete', text:`Parts row ${i+1}: select a product from the dropdown.` }); return;
        }
        if (r.qty < 1) {
            Swal.fire({ icon:'warning', title:'Invalid', text:`Parts row ${i+1}: quantity must be ≥ 1.` }); return;
        }
        if (r.unit_price <= 0) {
            Swal.fire({ icon:'warning', title:'Invalid', text:`Parts row ${i+1}: unit price must be > 0.` }); return;
        }
    }

    /* Validate labor */
    for (let i = 0; i < labors.length; i++) {
        const r = labors[i];
        if (!r.description.trim()) {
            Swal.fire({ icon:'warning', title:'Incomplete', text:`Labor row ${i+1}: enter a service description.` }); return;
        }
        if (r.amount <= 0) {
            Swal.fire({ icon:'warning', title:'Invalid', text:`Labor row ${i+1}: amount must be > 0.` }); return;
        }
    }

    /* Validate expenses — description required when amount > 0 */
    for (let i = 0; i < exps.length; i++) {
        const e = exps[i];
        if (e.amount > 0 && e.description.trim().length < 3) {
            Swal.fire({ icon:'warning', title:'Expense description required',
                text:`Expense row ${i+1}: please specify what the money was used for (min 3 chars).` }); return;
        }
    }

    const paymentMethod = document.querySelector('input[name="payment"]:checked')?.value || 'cash';

    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving…';

    const items = [
        ...parts.map(r => ({
            type: 'parts',
            product_id:  parseInt(r.product_id),
            description: PRODUCTS.find(p => String(p.product_id) === String(r.product_id))?.description || r.description,
            quantity:    r.qty,
            unit_price:  r.unit_price,
            amount:      r.qty * r.unit_price,
        })),
        ...labors.map(r => ({
            type: 'labor',
            product_id:  null,
            description: r.description.trim(),
            quantity:    1,
            unit_price:  r.amount,
            amount:      r.amount,
        })),
    ];

    const expenses = exps
        .filter(e => e.amount > 0 && e.description.trim())
        .map(e => ({ description: e.description.trim(), amount: e.amount }));

    const payload = {
        sale_date:      saleDate,
        customer_name:  document.getElementById('customerName').value.trim(),
        plate_number:   document.getElementById('plateNumber').value.trim().toUpperCase(),
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
            if (paymentMethod === 'gcash') html += ' <span class="badge-blue">GCash</span>';
            if (expenses.length) {
                const expSum = expenses.reduce((s,e) => s + e.amount, 0);
                html += `<br><small style="color:#fca5a5;">Expenses logged: ${peso(expSum)}</small>`;
            }
            if (lowItems.length) {
                html += '<br><small style="color:#f59e0b;">⚠ Low stock: '
                    + lowItems.map(s => `${s.description} (${s.stock_left} left)`).join(', ') + '</small>';
            }
            const res = await Swal.fire({
                icon:'success', title:'Saved!', html,
                showCancelButton:true,
                confirmButtonText:'View History',
                cancelButtonText:'New Transaction',
            });
            if (res.isConfirmed) window.location.href = 'sales-history.php';
            else clearAll();
        } else {
            Swal.fire({ icon:'error', title:'Save Failed', text:data.message });
        }
    } catch (err) {
        Swal.fire({ icon:'error', title:'Network Error', text:err.message });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-1"></i>Save Transaction';
    }
}

/* kick off */
renderParts(); renderLabor(); renderExp(); recalc();
</script>
</body>
</html>