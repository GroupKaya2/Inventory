<?php
session_start();
include "db.php";
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
$activePage = 'sales_history';
$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';

$today = date('Y-m-d');
$stats = $conn->query("
    SELECT
        COUNT(*) AS total_sales,
        COALESCE(SUM(parts_total + labor_total),0) AS grand_total,
        COALESCE(SUM(CASE WHEN sale_date='$today' THEN parts_total+labor_total ELSE 0 END),0) AS today_total,
        COALESCE(SUM(CASE WHEN sale_date >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN parts_total+labor_total ELSE 0 END),0) AS month_total
    FROM sales
")->fetch_assoc();

$sales = $conn->query("
    SELECT id, sale_date, customer_name, plate_number, parts_total, labor_total,
        (parts_total + labor_total) AS grand_total
    FROM sales ORDER BY sale_date DESC, id DESC
");
$salesRows = [];
if ($sales) while ($r = $sales->fetch_assoc()) $salesRows[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales History – Dispeedway</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { background: #f0f2f8; }
.page-header { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; padding: 24px 28px; border-radius: 14px; margin-bottom: 24px; }
.card { border: none; border-radius: 14px; box-shadow: 0 4px 18px rgba(0,0,0,.08); }
.table thead th { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; font-size: .78rem; font-weight: 600; border: none; padding: 10px 12px; }
.table tbody td { font-size: .83rem; vertical-align: middle; padding: 9px 12px; }
.table tbody tr:hover { background: #f8fafc; }
.filter-bar { background: #fff; border-radius: 12px; padding: 14px 18px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.summary-pill { display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9; border-radius: 8px; padding: 6px 12px; font-size: .82rem; font-weight: 600; color: #475569; }
.summary-pill .val { color: #667eea; }
@media(max-width:576px){
    .filter-bar { flex-direction:column; }
    .filter-bar input, .filter-bar button { width:100%; }
    .table { font-size:.75rem; }
}
</style>
</head>
<body>
<?php include "sidebar.php"; ?>
<main class="app-main p-3 p-md-4">
<div class="container-fluid">

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="mb-0"><i class="bi bi-clock-history me-2"></i>Sales History</h4>
        </div>
        <?php if ($isOwner): ?>
        <a href="sales.php" class="btn btn-light btn-sm fw-bold"><i class="bi bi-plus-lg me-1"></i>New Sale</a>
        <?php endif; ?>
    </div>
</div>

<!-- SUMMARY PILLS -->
<div class="d-flex flex-wrap gap-2 mb-3">
    <span class="summary-pill">Total Sales: <span class="val"><?= number_format($stats['total_sales']) ?></span></span>
    <span class="summary-pill">Today: <span class="val">₱<?= number_format($stats['today_total'], 0) ?></span></span>
    <span class="summary-pill">This Month: <span class="val">₱<?= number_format($stats['month_total'], 0) ?></span></span>
    <span class="summary-pill">All Time: <span class="val">₱<?= number_format($stats['grand_total'], 0) ?></span></span>
</div>

<!-- FILTER BAR -->
<div class="filter-bar d-flex flex-wrap gap-2 align-items-center">
    <input type="text"  id="searchInput" class="form-control" style="max-width:220px;" placeholder="Search customer/plate...">
    <input type="date"  id="dateFrom"    class="form-control" style="max-width:160px;">
    <button class="btn btn-primary btn-sm"           onclick="filterTable()"><i class="bi bi-search"></i> Filter</button>
    <?php if ($isOwner): ?>
    <button class="btn btn-success btn-sm ms-auto"   onclick="exportCSV()"><i class="bi bi-download"></i> Export CSV</button>
    <?php else: ?>
    <?php endif; ?>
</div>

<!-- TABLE -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" id="salesTable">
                <thead>
                    <tr>
                        <th>#</th><th>Date</th><th>Customer</th><th>Plate</th>
                        <th>Parts ₱</th><th>Labor ₱</th><th>Total ₱</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody id="salesBody">
                <?php if (empty($salesRows)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No sales recorded yet. <a href="sales.php">Record a sale →</a></td></tr>
                <?php else: foreach ($salesRows as $s): ?>
                    <tr
                        data-id="<?= $s['id'] ?>"
                        data-date="<?= $s['sale_date'] ?>"
                        data-customer="<?= strtolower(htmlspecialchars($s['customer_name'])) ?>"
                        data-plate="<?= strtolower(htmlspecialchars($s['plate_number'])) ?>"
                    >
                        <td><span class="badge bg-secondary"><?= $s['id'] ?></span></td>
                        <td><?= date('M d, Y', strtotime($s['sale_date'])) ?></td>
                        <td><?= htmlspecialchars($s['customer_name'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($s['plate_number']  ?: '—') ?></td>
                        <td class="text-primary fw-bold">₱<?= number_format($s['parts_total'], 2) ?></td>
                        <td class="text-success fw-bold">₱<?= number_format($s['labor_total'], 2) ?></td>
                        <td><strong>₱<?= number_format($s['grand_total'], 2) ?></strong></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1"
                                onclick="viewSale(<?= $s['id'] ?>)" title="View">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if ($isOwner): ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
</main>

<!-- VIEW SALE MODAL -->
<div class="modal fade" id="viewSaleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Sale Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="saleDetailBody">
                <div class="text-center py-3"><div class="spinner-border text-primary"></div></div>
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
    document.querySelectorAll('#salesBody tr[data-date]').forEach(function(tr) {
        const d       = tr.dataset.date;
        const matchQ  = !q || (tr.dataset.customer + ' ' + tr.dataset.plate).includes(q);
        const matchD  = (!from || d >= from) && (!to || d <= to);
        tr.style.display = (matchQ && matchD) ? '' : 'none';
    });
}

function resetFilter() {
    document.getElementById('searchInput').value = '';
    document.getElementById('dateFrom').value    = '';
    document.getElementById('dateTo').value      = '';
    document.querySelectorAll('#salesBody tr').forEach(function(tr) { tr.style.display = ''; });
}

function exportCSV() {
    if (!IS_OWNER) { Swal.fire({icon:'warning',title:'Access Denied',text:'Export is for Owner only.'}); return; }
    var rows = [['ID','Date','Customer','Plate','Parts (PHP)','Labor (PHP)','Total (PHP)']];
    document.querySelectorAll('#salesBody tr[data-date]').forEach(function(tr) {
        if (tr.style.display === 'none') return;
        var cells = tr.querySelectorAll('td');
        rows.push([
            cells[0].textContent.trim(),
            cells[1].textContent.trim(),
            cells[2].textContent.trim(),
            cells[3].textContent.trim(),
            cells[4].textContent.replace(/[₱,]/g,'').trim(),
            cells[5].textContent.replace(/[₱,]/g,'').trim(),
            cells[6].textContent.replace(/[₱,]/g,'').trim()
        ]);
    });
    if (rows.length <= 1) { Swal.fire({icon:'info',title:'No data',text:'Nothing to export.'}); return; }
    var csv  = rows.map(function(r){ return r.map(function(v){ return '"'+String(v).replace(/"/g,'""')+'"'; }).join(','); }).join('\r\n');
    var blob = new Blob(['\uFEFF'+csv], {type:'text/csv;charset=utf-8;'});
    var url  = URL.createObjectURL(blob);
    var a    = document.createElement('a');
    a.href = url; a.download = 'sales_history_'+new Date().toISOString().slice(0,10)+'.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
}

async function deleteSale(id, name) {
    if (!IS_OWNER) { Swal.fire({icon:'warning',title:'Access Denied',text:'Only Owner can delete records.'}); return; }
    var res = await Swal.fire({
        title:'Delete transaction?',
        html:'<strong>'+name+'</strong><br><small class="text-muted">Cannot be undone.</small>',
        icon:'warning', showCancelButton:true,
        confirmButtonColor:'#ef4444', confirmButtonText:'Yes, delete!'
    });
    if (!res.isConfirmed) return;
    var fd = new FormData();
    fd.append('id', id);
    try {
        var resp = await fetch('delete_sales.php', {method:'POST',body:fd});
        var data = await resp.json();
        if (data.success) {
            var row = document.querySelector('#salesBody tr[data-id="'+id+'"]');
            if (row) row.remove();
            Swal.fire({icon:'success',title:'Deleted!',timer:1200,showConfirmButton:false});
        } else {
            Swal.fire({icon:'error',title:'Error',text:data.message});
        }
    } catch(e) { Swal.fire({icon:'error',title:'Network Error',text:e.message}); }
}

async function deleteAllTransactions() {
    if (!IS_OWNER) { Swal.fire({icon:'warning',title:'Access Denied',text:'Only Owner can delete records.'}); return; }
    var visible = Array.from(document.querySelectorAll('#salesBody tr[data-date]')).filter(function(tr){ return tr.style.display !== 'none'; });
    if (!visible.length) { Swal.fire({icon:'info',title:'Nothing to delete'}); return; }
    var res = await Swal.fire({
        title:'Delete ALL '+visible.length+' transaction(s)?',
        html:'<span style="color:#ef4444;font-weight:bold;">This cannot be undone!</span><br><br>Type <b>DELETE</b> to confirm.',
        icon:'warning', input:'text', inputPlaceholder:'Type DELETE here',
        showCancelButton:true, confirmButtonColor:'#ef4444', confirmButtonText:'Delete All',
        preConfirm:function(val){ if(val!=='DELETE') Swal.showValidationMessage('Type DELETE exactly (uppercase)'); }
    });
    if (!res.isConfirmed) return;
    var ids = visible.map(function(tr){ return tr.dataset.id; }).filter(Boolean);
    var fd = new FormData();
    fd.append('ids', JSON.stringify(ids));
    try {
        var resp = await fetch('delete_sales.php', {method:'POST',body:fd});
        var data = await resp.json();
        if (data.success) {
            visible.forEach(function(tr){ tr.remove(); });
            Swal.fire({icon:'success',title:'All Deleted!',text:data.message,timer:1800,showConfirmButton:false});
        } else {
            Swal.fire({icon:'error',title:'Error',text:data.message});
        }
    } catch(e) { Swal.fire({icon:'error',title:'Network Error',text:e.message}); }
}

async function viewSale(id) {
    document.getElementById('saleDetailBody').innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary"></div></div>';
    var modal = new bootstrap.Modal(document.getElementById('viewSaleModal'));
    modal.show();
    try {
        var resp = await fetch('get_sale_detail.php?id='+id);
        var data = await resp.json();
        if (!data.success) { document.getElementById('saleDetailBody').innerHTML='<p class="text-danger text-center">'+(data.message||'Failed to load.')+'</p>'; return; }
        var s = data.sale, items = data.items;
        var html = '<div class="row mb-3">'
            +'<div class="col-sm-4"><strong>Date:</strong> '+s.sale_date+'</div>'
            +'<div class="col-sm-4"><strong>Customer:</strong> '+(s.customer_name||'—')+'</div>'
            +'<div class="col-sm-4"><strong>Plate:</strong> '+(s.plate_number||'—')+'</div>'
            +'</div>'
            +'<div class="table-responsive"><table class="table table-sm"><thead class="table-light"><tr>'
            +'<th>Type</th><th>Item</th><th>Qty</th><th>Unit Price</th><th>Amount</th>'
            +'</tr></thead><tbody>';
        items.forEach(function(i){
            html+='<tr>'
                +'<td><span class="badge '+(i.line_type==='parts'?'bg-primary':'bg-success')+'">'+i.line_type+'</span></td>'
                +'<td>'+(i.description||'—')+'</td>'
                +'<td>'+i.quantity+'</td>'
                +'<td>₱'+parseFloat(i.unit_price).toFixed(2)+'</td>'
                +'<td>₱'+parseFloat(i.amount).toFixed(2)+'</td>'
                +'</tr>';
        });
        var total = parseFloat(s.parts_total)+parseFloat(s.labor_total);
        html+='</tbody></table></div>'
            +'<div class="d-flex justify-content-end gap-4 border-top pt-2 mt-2">'
            +'<span>Parts: <strong>₱'+parseFloat(s.parts_total).toFixed(2)+'</strong></span>'
            +'<span>Labor: <strong>₱'+parseFloat(s.labor_total).toFixed(2)+'</strong></span>'
            +'<span>TOTAL: <strong class="fs-5 text-primary">₱'+total.toFixed(2)+'</strong></span>'
            +'</div>';
        document.getElementById('saleDetailBody').innerHTML = html;
    } catch(e) { document.getElementById('saleDetailBody').innerHTML='<p class="text-danger text-center">Error: '+e.message+'</p>'; }
}

// Live search on input
document.getElementById('searchInput').addEventListener('input', filterTable);
</script>
</body>
</html>