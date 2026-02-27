<?php
session_start();
include "db.php";
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
if (($_SESSION['role'] ?? 'manager') !== 'owner') {
    $_SESSION['access_error'] = 'Only the Owner can view Expenses.';
    header("Location: dashboard.php"); exit;
}
$activePage = 'expenses';
$conn->query("CREATE TABLE IF NOT EXISTS expenses (id INT AUTO_INCREMENT PRIMARY KEY, expense_date DATE NOT NULL, category VARCHAR(100) NOT NULL, description VARCHAR(255) NOT NULL, amount DECIMAL(12,2) NOT NULL DEFAULT 0, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$today = date('Y-m-d');
$statsRow = $conn->query("SELECT COALESCE(SUM(CASE WHEN expense_date='$today' THEN amount ELSE 0 END),0) AS today_exp, COALESCE(SUM(CASE WHEN expense_date>=DATE_FORMAT(NOW(),'%Y-%m-01') THEN amount ELSE 0 END),0) AS month_exp, COALESCE(SUM(amount),0) AS total_exp, COUNT(*) AS total_count FROM expenses")->fetch_assoc();
$catRows=[]; $r=$conn->query("SELECT category,SUM(amount) AS total,COUNT(*) AS cnt FROM expenses GROUP BY category ORDER BY total DESC"); if($r) while($row=$r->fetch_assoc()) $catRows[]=$row;
$expenses=[]; $r2=$conn->query("SELECT * FROM expenses ORDER BY expense_date DESC, id DESC"); if($r2) while($row=$r2->fetch_assoc()) $expenses[]=$row;
$categories=['Rent','Salaries','Utilities','Supplies','Equipment','Maintenance','Marketing','Other'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expenses – Dispeedway</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
body{background:#f0f2f8;}
.page-header{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;padding:24px 28px;border-radius:14px;margin-bottom:20px;}
.kpi-card{border:none;border-radius:14px;box-shadow:0 4px 18px rgba(0,0,0,.08);}
.kpi-icon{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;}
.kpi-title{font-size:.72rem;font-weight:600;text-transform:uppercase;color:#94a3b8;}
.kpi-value{font-size:1.6rem;font-weight:800;color:#1e293b;}
.chart-card{border:none;border-radius:14px;box-shadow:0 4px 18px rgba(0,0,0,.08);padding:20px;}
.data-card{border:none;border-radius:14px;box-shadow:0 4px 18px rgba(0,0,0,.08);}
.table thead th{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-size:.78rem;font-weight:600;border:none;padding:10px 12px;}
.table tbody td{font-size:.83rem;vertical-align:middle;padding:9px 12px;}
.table tbody tr:hover{background:#fef2f2;}
.cat-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600;}
</style>
</head><body>
<?php include "sidebar.php"; ?>
<main class="app-main p-3 p-md-4"><div class="container-fluid">

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Expenses Management</h4>
            <p class="mb-0 mt-1 opacity-75">Track shop expenses — rent, salaries, supplies, utilities</p>
        </div>
        <button class="btn" style="background:#fff;color:#dc2626;font-weight:700;border-radius:10px;" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="bi bi-plus-lg me-1"></i>Add Expense
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card kpi-card h-100"><div class="card-body d-flex align-items-center gap-3 p-3"><div class="kpi-icon" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;"><i class="bi bi-calendar-day"></i></div><div><div class="kpi-title">Today</div><div class="kpi-value">₱<?= number_format($statsRow['today_exp'],0) ?></div></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card kpi-card h-100"><div class="card-body d-flex align-items-center gap-3 p-3"><div class="kpi-icon" style="background:linear-gradient(135deg,#f97316,#ef4444);color:#fff;"><i class="bi bi-calendar-month"></i></div><div><div class="kpi-title">This Month</div><div class="kpi-value">₱<?= number_format($statsRow['month_exp'],0) ?></div></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card kpi-card h-100"><div class="card-body d-flex align-items-center gap-3 p-3"><div class="kpi-icon" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;"><i class="bi bi-receipt-cutoff"></i></div><div><div class="kpi-title">Records</div><div class="kpi-value"><?= $statsRow['total_count'] ?></div></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card kpi-card h-100"><div class="card-body d-flex align-items-center gap-3 p-3"><div class="kpi-icon" style="background:linear-gradient(135deg,#1e293b,#0f172a);color:#fff;"><i class="bi bi-cash-coin"></i></div><div><div class="kpi-title">All Time</div><div class="kpi-value">₱<?= number_format($statsRow['total_exp'],0) ?></div></div></div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-5"><div class="chart-card h-100"><h6><i class="bi bi-pie-chart me-2" style="color:#ef4444"></i>By Category</h6><canvas id="expCategoryChart" height="220"></canvas></div></div>
    <div class="col-lg-7"><div class="chart-card h-100"><h6><i class="bi bi-bar-chart me-2" style="color:#f97316"></i>Category Breakdown (₱)</h6><canvas id="expBarChart" height="220"></canvas></div></div>
</div>

<div class="card data-card">
    <div class="card-body p-0">
        <div class="p-3 d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom">
            <h6 class="mb-0 fw-bold"><i class="bi bi-table me-2" style="color:#ef4444"></i>All Expenses</h6>
            <div class="d-flex gap-2">
                <input type="text" id="expSearch" class="form-control form-control-sm" placeholder="Search…" style="max-width:180px;">
                <select id="expCatFilter" class="form-select form-select-sm" style="max-width:150px;"><option value="">All Categories</option><?php foreach($categories as $c): ?><option><?= $c ?></option><?php endforeach; ?></select>
                <button class="btn btn-sm btn-success" onclick="exportExpenses()"><i class="bi bi-download"></i> CSV</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table mb-0"><thead><tr><th>#</th><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Delete</th></tr></thead>
            <tbody id="expTableBody">
            <?php $cc=['Rent'=>'#dc2626','Salaries'=>'#7c3aed','Utilities'=>'#2563eb','Supplies'=>'#059669','Equipment'=>'#d97706','Maintenance'=>'#0891b2','Marketing'=>'#db2777','Other'=>'#64748b']; foreach($expenses as $e): $col=$cc[$e['category']]??'#64748b'; ?>
            <tr data-cat="<?= htmlspecialchars($e['category']) ?>" data-desc="<?= strtolower(htmlspecialchars($e['description'])) ?>">
                <td><span class="badge bg-secondary"><?= $e['id'] ?></span></td>
                <td><?= date('M d, Y',strtotime($e['expense_date'])) ?></td>
                <td><span class="cat-badge" style="background:<?= $col ?>22;color:<?= $col ?>;"><?= htmlspecialchars($e['category']) ?></span></td>
                <td><?= htmlspecialchars($e['description']) ?></td>
                <td class="text-danger fw-bold">₱<?= number_format($e['amount'],2) ?></td>
                <td><button class="btn btn-sm btn-outline-danger" onclick="deleteExpense(<?= $e['id'] ?>,'<?= htmlspecialchars(addslashes($e['description'])) ?>')"><i class="bi bi-trash"></i></button></td>
            </tr>
            <?php endforeach; if(empty($expenses)): ?><tr><td colspan="6" class="text-center py-4 text-muted">No expenses yet.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </div>
</div>
</div></main>

<div class="modal fade" id="addExpenseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;"><h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Expense</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label fw-bold">Date *</label><input type="date" class="form-control" id="expDate" value="<?= date('Y-m-d') ?>"></div>
        <div class="mb-3"><label class="form-label fw-bold">Category *</label><select class="form-select" id="expCategory"><option value="">Select</option><?php foreach($categories as $c): ?><option><?= $c ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label fw-bold">Description *</label><input type="text" class="form-control" id="expDescription" placeholder="e.g. Monthly Electricity Bill"></div>
        <div class="mb-3"><label class="form-label fw-bold">Amount (₱) *</label><input type="number" class="form-control" id="expAmount" min="0.01" step="0.01" placeholder="0.00"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" id="saveExpenseBtn"><i class="bi bi-save me-1"></i>Save</button></div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CAT_DATA=<?= json_encode($catRows) ?>;const COLORS=['#dc2626','#7c3aed','#2563eb','#059669','#d97706','#0891b2','#db2777','#64748b','#f97316','#10b981'];
if(CAT_DATA.length){new Chart(document.getElementById('expCategoryChart'),{type:'doughnut',data:{labels:CAT_DATA.map(c=>c.category),datasets:[{data:CAT_DATA.map(c=>parseFloat(c.total)),backgroundColor:COLORS,borderWidth:2,borderColor:'#fff'}]},options:{cutout:'60%',plugins:{legend:{position:'bottom'}}}});new Chart(document.getElementById('expBarChart'),{type:'bar',data:{labels:CAT_DATA.map(c=>c.category),datasets:[{data:CAT_DATA.map(c=>parseFloat(c.total)),backgroundColor:COLORS.slice(0,CAT_DATA.length),borderRadius:8,borderSkipped:false}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true}}}});}
function filterTable(){const q=document.getElementById('expSearch').value.toLowerCase();const cat=document.getElementById('expCatFilter').value;document.querySelectorAll('#expTableBody tr[data-cat]').forEach(tr=>{tr.style.display=((!cat||tr.dataset.cat===cat)&&(!q||tr.dataset.desc.includes(q)))?'':'none';});}
document.getElementById('expSearch').addEventListener('input',filterTable);
document.getElementById('expCatFilter').addEventListener('change',filterTable);
document.getElementById('saveExpenseBtn').addEventListener('click',async function(){
    const date=document.getElementById('expDate').value,cat=document.getElementById('expCategory').value,desc=document.getElementById('expDescription').value.trim(),amt=parseFloat(document.getElementById('expAmount').value);
    if(!date||!cat||!desc||!amt||amt<=0){Swal.fire({icon:'warning',title:'Incomplete',text:'Fill in all fields.'});return;}
    this.disabled=true;const fd=new FormData();fd.append('expense_date',date);fd.append('category',cat);fd.append('description',desc);fd.append('amount',amt);
    const resp=await fetch('save_expense.php',{method:'POST',body:fd});const data=await resp.json();this.disabled=false;
    if(data.success)Swal.fire({icon:'success',title:'Saved!',timer:1500,showConfirmButton:false}).then(()=>location.reload());
    else Swal.fire({icon:'error',title:'Error',text:data.message});
});
async function deleteExpense(id,desc){
    const res=await Swal.fire({title:'Delete?',text:`"${desc}"`,icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',confirmButtonText:'Delete'});
    if(!res.isConfirmed)return;
    const fd=new FormData();fd.append('id',id);const resp=await fetch('delete_expense.php',{method:'POST',body:fd});const data=await resp.json();
    if(data.success)Swal.fire({icon:'success',title:'Deleted!',timer:1200,showConfirmButton:false}).then(()=>location.reload());
    else Swal.fire({icon:'error',title:'Error',text:data.message});
}
function exportExpenses(){const rows=[['ID','Date','Category','Description','Amount']];document.querySelectorAll('#expTableBody tr[data-cat]').forEach(tr=>{if(tr.style.display==='none')return;const c=tr.querySelectorAll('td');rows.push([c[0].textContent.trim(),c[1].textContent.trim(),c[2].textContent.trim(),c[3].textContent.trim(),c[4].textContent.replace('₱','').trim()]);});const csv=rows.map(r=>r.map(v=>`"${v}"`).join(',')).join('\n');const a=document.createElement('a');a.href='data:text/csv;charset=utf-8,'+encodeURIComponent(csv);a.download='expenses_'+new Date().toISOString().slice(0,10)+'.csv';a.click();}
</script>
</body></html>