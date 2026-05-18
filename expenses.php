<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$activePage = 'expenses';
$isOwner = (($_SESSION['role'] ?? 'manager') === 'owner');

$conn->query("CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$today = date('Y-m-d');

$stats = $conn->query("
    SELECT
        COALESCE(SUM(CASE WHEN expense_date = '$today' THEN amount END), 0) AS today,
        COALESCE(SUM(CASE WHEN expense_date >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN amount END), 0) AS month,
        COALESCE(SUM(amount), 0) AS total,
        COUNT(*) AS count
    FROM expenses
")->fetch_assoc();

$catRows = [];
$r = $conn->query("SELECT category, SUM(amount) AS total, COUNT(*) AS cnt FROM expenses GROUP BY category ORDER BY total DESC");
if ($r)
    while ($row = $r->fetch_assoc())
        $catRows[] = $row;

$expenses = [];
$r2 = $conn->query("SELECT e.*, u.name AS creator FROM expenses e LEFT JOIN users u ON e.created_by = u.id ORDER BY e.expense_date DESC, e.id DESC");
if ($r2)
    while ($row = $r2->fetch_assoc())
        $expenses[] = $row;

$categories = ['Rent', 'Salaries', 'Utilities', 'Supplies', 'Equipment', 'Maintenance', 'Marketing', 'Other'];
$catColors = ['Rent' => '#e8175d', 'Salaries' => '#8b5cf6', 'Utilities' => '#3b82f6', 'Supplies' => '#10b981', 'Equipment' => '#f59e0b', 'Maintenance' => '#06b6d4', 'Marketing' => '#ec4899', 'Other' => '#64748b'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses DSpeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/expenses.css">
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <main class="app-main">

        <div class="page-header mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 style="margin:0;"><i class="bi bi-wallet2 me-2"></i>Expenses<?= $isOwner ? ' Management' : '' ?></h4>
                    <?php if (!$isOwner): ?>
                        <p style="margin:4px 0 0;font-size:.8rem;color:#64748b;">You can add expenses. Only the owner can delete them.</p>
                    <?php endif; ?>
                </div>
                <button class="btn-pink" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Expenses
                </button>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <div class="kpi-icon pink"><i class="bi bi-calendar-day"></i></div>
                    <div>
                        <div class="kpi-label">Today</div>
                        <div class="kpi-value">₱<?= number_format($stats['today'], 0) ?></div>
                        <div class="kpi-sub">expenses today</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <div class="kpi-icon orange"><i class="bi bi-calendar-month"></i></div>
                    <div>
                        <div class="kpi-label">This Month</div>
                        <div class="kpi-value">₱<?= number_format($stats['month'], 0) ?></div>
                        <div class="kpi-sub">monthly total</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <div class="kpi-icon purple"><i class="bi bi-receipt-cutoff"></i></div>
                    <div>
                        <div class="kpi-label">Records</div>
                        <div class="kpi-value"><?= $stats['count'] ?></div>
                        <div class="kpi-sub">expenses entries</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <div class="kpi-icon red"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="kpi-label">All Time</div>
                        <div class="kpi-value">₱<?= number_format($stats['total'], 0) ?></div>
                        <div class="kpi-sub">total expenses</div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($catRows)): ?>
            <div class="row g-3 mb-4">
                <div class="col-lg-4">
                    <div class="chart-card h-100">
                        <h6><i class="bi bi-pie-chart me-2" style="color:#e8175d;"></i>By Category</h6>
                        <canvas id="donutChart" height="220"></canvas>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="chart-card h-100">
                        <h6><i class="bi bi-bar-chart me-2" style="color:#f97316;"></i>Category Amounts (₱)</h6>
                        <canvas id="barChart" height="220"></canvas>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-0">
                <div class="p-3 d-flex flex-wrap gap-2 align-items-center"
                    style="border-bottom:1px solid rgba(255,255,255,.07);">
                    <h6 style="margin:0;font-size:.88rem;"><i class="bi bi-table me-2" style="color:#fff;"> All Expenses
                        </i></h6>
                    <div class="ms-auto d-flex gap-2 flex-wrap align-items-center">
                        <input type="text" id="expSearch" placeholder="Search description, category…"
                            style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);color:#e2e8f0;border-radius:8px;padding:6px 12px;font-size:.82rem;width:200px;outline:none;font-family:'Inter',sans-serif;"
                            onfocus="this.style.borderColor='rgba(74,222,128,.35)'"
                            onblur="this.style.borderColor='rgba(255,255,255,.09)'">
                        <select id="expCatFilter"
                            style="background:#161b27;border:1px solid rgba(255,255,255,.09);color:#e2e8f0;border-radius:8px;padding:6px 12px;font-size:.82rem;outline:none;font-family:'Inter',sans-serif;cursor:pointer;">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c ?>"><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn-ghost" style="font-size:.8rem;padding:6px 14px;" onclick="exportCSV()">
                            <i class="bi bi-download me-1"></i>CSV
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="expTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Added By</th>
                                <?php if ($isOwner): ?><th>Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="expBody">
                            <?php if (empty($expenses)): ?>
                                <tr>
                                    <td colspan="<?= $isOwner ? 7 : 6 ?>" style="text-align:center;padding:30px;color:#7a8499;">
                                        No expenses recorded yet.
                                    </td>
                                </tr>
                            <?php else:
                                $expNum = 0;
                                foreach ($expenses as $e):
                                    $expNum++;
                                    $color = $catColors[$e['category']] ?? '#64748b';
                                    ?>
                                    <tr data-cat="<?= htmlspecialchars($e['category']) ?>"
                                        data-search="<?= strtolower(htmlspecialchars($e['description'] . ' ' . $e['category'] . ' ' . $e['expense_date'] . ' ' . ($e['creator'] ?? ''))) ?>">
                                        <td><span class="badge-gray row-num"><?= $expNum ?></span></td>
                                        <td><?= date('M d, Y', strtotime($e['expense_date'])) ?></td>
                                        <td>
                                            <span
                                                style="display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:.73rem;font-weight:700;background:<?= $color ?>22;color:<?= $color ?>;">
                                                <?= htmlspecialchars($e['category']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($e['description']) ?></td>
                                        <td style="color:#fca5a5;font-weight:700;">₱<?= number_format($e['amount'], 2) ?></td>
                                        <td style="color:#7a8499;font-size:.75rem;">
                                            <?= htmlspecialchars($e['creator'] ?? '—') ?></td>
                                        <?php if ($isOwner): ?>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger"
                                                onclick="deleteExpense(<?= $e['id'] ?>, '<?= htmlspecialchars(addslashes($e['description'])) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>


    <!-- Add Expenses Modal -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1">
        <div class="modal-dialog  modal-lg modal-dark">
            <div class="modal-content modal-dark">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Expenses</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date *</label>
                        <input type="date" class="form-input" id="expDate" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select class="form-input form-select" id="expCategory">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $c): ?>
                                <option><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <input type="text" class="form-input" id="expDesc" placeholder="e.g. Monthly Electricity Bill">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (₱) *</label>
                        <input type="number" class="form-input" id="expAmount" min="0.01" step="0.01"
                            placeholder="0.00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-pink" id="saveExpenseBtn">
                        <i class="bi bi-save me-1"></i>Save Expense
                    </button>
                </div>
            </div>
        </div>
    </div>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;
        const CAT_DATA = <?= json_encode($catRows) ?>;
        const PALETTE = ['#e8175d', '#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#06b6d4', '#ec4899', '#64748b', '#f97316'];

        Chart.defaults.color = '#7a8499';
        Chart.defaults.font.family = "'DM Sans', sans-serif";

        if (CAT_DATA.length) {
            new Chart(document.getElementById('donutChart'), {
                type: 'doughnut',
                data: {
                    labels: CAT_DATA.map(c => c.category),
                    datasets: [{ data: CAT_DATA.map(c => parseFloat(c.total)), backgroundColor: PALETTE, borderWidth: 2, borderColor: '#1c2030' }],
                },
                options: {
                    cutout: '60%',
                    plugins: {
                        legend: { position: 'bottom', labels: { padding: 10, font: { size: 11 } } },
                        tooltip: { callbacks: { label: ctx => ' ₱' + ctx.parsed.toLocaleString() } },
                    },
                },
            });

            new Chart(document.getElementById('barChart'), {
                type: 'bar',
                data: {
                    labels: CAT_DATA.map(c => c.category),
                    datasets: [{
                        label: 'Amount ₱',
                        data: CAT_DATA.map(c => parseFloat(c.total)),
                        backgroundColor: PALETTE.slice(0, CAT_DATA.length),
                        borderRadius: 8,
                        borderSkipped: false,
                    }],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ' ₱' + ctx.parsed.y.toLocaleString() } } },
                    scales: {
                        x: { grid: { color: 'rgba(255,255,255,.04)' } },
                        y: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { callback: v => '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v) } },
                    },
                },
            });
        }

        function filterExp() {
            const q = document.getElementById('expSearch').value.toLowerCase().trim();
            const cat = document.getElementById('expCatFilter').value;
            document.querySelectorAll('#expBody tr[data-cat]').forEach(tr => {
                const matchCat = !cat || tr.dataset.cat === cat;
                const matchText = !q || tr.dataset.search.includes(q);
                tr.style.display = matchCat && matchText ? '' : 'none';
            });
            renumberExpenses();
        }

        function renumberExpenses() {
            let n = 0;
            document.querySelectorAll('#expBody tr[data-cat]').forEach(tr => {
                if (tr.style.display !== 'none') {
                    n++;
                    const badge = tr.querySelector('.row-num');
                    if (badge) badge.textContent = n;
                }
            });
        }
        document.getElementById('expSearch').addEventListener('input', filterExp);
        document.getElementById('expCatFilter').addEventListener('change', filterExp);

        document.getElementById('saveExpenseBtn').addEventListener('click', async function () {
            const date = document.getElementById('expDate').value;
            const cat = document.getElementById('expCategory').value;
            const desc = document.getElementById('expDesc').value.trim();
            const amount = parseFloat(document.getElementById('expAmount').value);

            if (!date || !cat || !desc || !amount || amount <= 0) {
                Swal.fire({ icon: 'warning', title: 'Fill all fields', text: 'All fields are required and amount must be > 0.' });
                return;
            }

            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving…';

            const fd = new FormData();
            fd.append('action', 'save');
            fd.append('expense_date', date);
            fd.append('category', cat);
            fd.append('description', desc);
            fd.append('amount', amount);

            const resp = await fetch('backend/expenses.php', { method: 'POST', body: fd });
            const data = await resp.json();

            this.disabled = false;
            this.innerHTML = '<i class="bi bi-save me-1"></i>Save Expense';

            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Saved!', timer: 1400, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        });

        async function deleteExpense(id, desc) {
            if (!IS_OWNER) {
                Swal.fire({ icon: 'info', title: 'View only', text: 'Only the owner can delete expenses.' });
                return;
            }
            const confirm = await Swal.fire({
                title: 'Delete expense?', text: `"${desc}"`, icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Delete',
            });
            if (!confirm.isConfirmed) return;

            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);

            const resp = await fetch('backend/expenses.php', { method: 'POST', body: fd });
            const data = await resp.json();

            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1200, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        }

        function exportCSV() {
            const rows = [['ID', 'Date', 'Category', 'Description', 'Amount']];
            document.querySelectorAll('#expBody tr[data-cat]').forEach(tr => {
                if (tr.style.display === 'none') return;
                const cells = tr.querySelectorAll('td');
                rows.push([cells[0].textContent.trim(), cells[1].textContent.trim(), cells[2].textContent.trim(), cells[3].textContent.trim(), cells[4].textContent.replace('₱', '').trim()]);
            });
            const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
            const a = document.createElement('a');
            a.href = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
            a.download = 'expenses_' + new Date().toISOString().slice(0, 10) + '.csv';
            a.click();
        }
    </script>
</body>

</html>