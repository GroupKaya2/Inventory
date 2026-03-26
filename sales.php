<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$activePage = 'sales_history';
$isOwner    = ($_SESSION['role'] ?? 'manager') === 'owner';
$today      = date('Y-m-d');

$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(parts_total + labor_total), 0) AS all_time,
        COALESCE(SUM(CASE WHEN sale_date = '$today' THEN parts_total + labor_total ELSE 0 END), 0) AS today_total,
        COALESCE(SUM(CASE WHEN sale_date >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN parts_total + labor_total ELSE 0 END), 0) AS month_total
    FROM sales
")->fetch_assoc();

$salesRows = [];
$r = $conn->query("SELECT id, sale_date, customer_name, plate_number, parts_total, labor_total, (parts_total + labor_total) AS grand_total FROM sales ORDER BY sale_date DESC, id DESC");
if ($r) while ($row = $r->fetch_assoc()) $salesRows[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History — Dispeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/sales-history.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="app-main">

    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 style="margin:0;"><i class="bi bi-clock-history me-2"></i>Sales History</h4>

            </div>
            <a href="sales.php" class="btn-pink"><i class="bi bi-plus-lg me-1"></i>New Sale</a>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="summary-pill">Total: <strong><?= number_format($stats['total']) ?> sales</strong></span>
        <span class="summary-pill">Today: <strong>₱<?= number_format($stats['today_total'], 0) ?></strong></span>
        <span class="summary-pill">This Month: <strong>₱<?= number_format($stats['month_total'], 0) ?></strong></span>
        <span class="summary-pill">All Time: <strong>₱<?= number_format($stats['all_time'], 0) ?></strong></span>
    </div>


    <div class="filter-bar">
        <input type="text" id="searchInput" class="form-control" style="max-width:220px;" placeholder="Search customer or plate…">
        <input type="date" id="dateTo"      class="form-control" style="max-width:150px;">
        <button class="btn-pink" style="font-size:.82rem;padding:7px 16px;" onclick="filterTable()">
            <i class="bi bi-search me-1"></i>Search
        </button>
        <button class="btn-ghost" style="font-size:.82rem;padding:7px 16px;" onclick="resetFilter()">Reset</button>
        <?php if ($isOwner): ?>
        <button class="btn-ghost ms-auto" style="font-size:.82rem;padding:7px 16px;" onclick="exportCSV()">
            <i class="bi bi-download me-1"></i>Export CSV
        </button>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="data-table" id="salesTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Plate</th>
                            <th>Parts ₱</th>
                            <th>Labor ₱</th>
                            <th>Total ₱</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="salesBody">
                    <?php if (empty($salesRows)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:30px;color:#7a8499;">
                                No sales yet. <a href="sales.php">Record one →</a>
                            </td>
                        </tr>
                    <?php else: foreach ($salesRows as $s): ?>
                        <tr
                            data-id="<?= $s['id'] ?>"
                            data-date="<?= $s['sale_date'] ?>"
                            data-search="<?= strtolower(htmlspecialchars($s['customer_name'] . ' ' . $s['plate_number'])) ?>"
                        >
                            <td><span class="badge-gray"><?= $s['id'] ?></span></td>
                            <td><?= date('M d, Y', strtotime($s['sale_date'])) ?></td>
                            <td><?= htmlspecialchars($s['customer_name'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($s['plate_number']  ?: '—') ?></td>
                            <td style="color:#93c5fd;font-weight:600;">₱<?= number_format($s['parts_total'], 2) ?></td>
                            <td style="color:#34d399;font-weight:600;">₱<?= number_format($s['labor_total'], 2) ?></td>
                            <td><strong>₱<?= number_format($s['grand_total'], 2) ?></strong></td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" onclick="viewSale(<?= $s['id'] ?>)" title="View">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if ($isOwner): ?>
                                <button class="btn btn-sm btn-outline-danger ms-1"
                                    onclick="deleteSale(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['customer_name'] ?: 'Sale #' . $s['id'])) ?>')"
                                    title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<!-- View Sale Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modal-dark" style="background:#1c2030;color:#e8ecf4;border:1px solid rgba(255,255,255,0.07);">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Sale Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="saleDetailBody" style="background:#1c2030;color:#e8ecf4;">
                <div style="text-align:center;padding:30px;">
                    <div class="spinner-border" style="color:#e8175d;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;

function filterTable() {
    const q    = document.getElementById('searchInput').value.toLowerCase().trim();
    const from = document.getElementById('dateFrom').value;
    const to   = document.getElementById('dateTo').value;

    document.querySelectorAll('#salesBody tr[data-date]').forEach(tr => {
        const d      = tr.dataset.date;
        const matchQ = !q || tr.dataset.search.includes(q);
        const matchD = (!from || d >= from) && (!to || d <= to);
        tr.style.display = matchQ && matchD ? '' : 'none';
    });
}

function resetFilter() {
    ['searchInput','dateFrom','dateTo'].forEach(id => document.getElementById(id).value = '');
    document.querySelectorAll('#salesBody tr').forEach(tr => tr.style.display = '');
}

document.getElementById('searchInput').addEventListener('input', filterTable);

function exportCSV() {
    const rows = [['ID','Date','Customer','Plate','Parts','Labor','Total']];
    document.querySelectorAll('#salesBody tr[data-date]').forEach(tr => {
        if (tr.style.display === 'none') return;
        const cells = tr.querySelectorAll('td');
        rows.push([
            cells[0].textContent.trim(),
            cells[1].textContent.trim(),
            cells[2].textContent.trim(),
            cells[3].textContent.trim(),
            cells[4].textContent.replace(/[₱,]/g,'').trim(),
            cells[5].textContent.replace(/[₱,]/g,'').trim(),
            cells[6].textContent.replace(/[₱,]/g,'').trim(),
        ]);
    });
    const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
    const a   = document.createElement('a');
    a.href     = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
    a.download = 'sales_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
}

async function viewSale(id) {
    const body = document.getElementById('saleDetailBody');
    body.innerHTML = '<div style="text-align:center;padding:30px;"><div class="spinner-border" style="color:#e8175d;"></div></div>';
    new bootstrap.Modal(document.getElementById('viewModal')).show();

    const resp = await fetch(`backend/sales.php?action=detail&id=${id}`);
    const data = await resp.json();

    if (!data.success) {
        body.innerHTML = `<p style="color:#fca5a5;text-align:center;">${data.message}</p>`;
        return;
    }

    const s     = data.sale;
    const total = parseFloat(s.parts_total) + parseFloat(s.labor_total);

    body.innerHTML = `
        <div class="row mb-3 g-2" style="color:#e8ecf4;">
            <div class="col-sm-4"><strong style="color:#fff;">Date:</strong> ${s.sale_date}</div>
            <div class="col-sm-4"><strong style="color:#fff;">Customer:</strong> ${s.customer_name || '—'}</div>
            <div class="col-sm-4"><strong style="color:#fff;">Plate:</strong> ${s.plate_number || '—'}</div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Type</th><th>Item</th><th>Qty</th><th>Unit Price</th><th>Amount</th></tr>
                </thead>
                <tbody>
                    ${data.items.map(i => `<tr>
                        <td><span class="${i.line_type === 'parts' ? 'badge-blue' : 'badge-green'}">${i.line_type}</span></td>
                        <td style="color:#e8ecf4;">${i.description || '—'}</td>
                        <td style="color:#e8ecf4;">${i.quantity}</td>
                        <td style="color:#e8ecf4;">₱${parseFloat(i.unit_price).toFixed(2)}</td>
                        <td style="color:#e8ecf4;">₱${parseFloat(i.amount).toFixed(2)}</td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end gap-4 mt-3" style="padding-top:12px;border-top:1px  rgba(73, 141, 214, 0.07);">
            <span>Parts: <strong style="color:#93c5fd;">₱${parseFloat(s.parts_total).toFixed(2)}</strong></span>
            <span>Labor: <strong style="color:#34d399;">₱${parseFloat(s.labor_total).toFixed(2)}</strong></span>
            <span style="font-size:1.1rem;">TOTAL: <strong style="color:#e8175d;">₱${total.toFixed(2)}</strong></span>
        </div>`;
}

async function deleteSale(id, name) {
    const confirm = await Swal.fire({
        title: 'Delete this sale?',
        text: `"${name}" will be permanently removed.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Delete',
    });
    if (!confirm.isConfirmed) return;

    const fd = new FormData();
    fd.append('id', id);
    const resp = await fetch('backend/sales.php?action=delete', { method: 'POST', body: fd });
    const data = await resp.json();

    if (data.success) {
        document.querySelector(`#salesBody tr[data-id="${id}"]`)?.remove();
        Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1200, showConfirmButton: false });
    } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
}
</script>
</body>
</html>