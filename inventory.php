<?php
session_start();
include "db.php";
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
$activePage = 'inventory';
$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Product Inventory – Dispeedway</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body{background:#f0f2f8;}
.page-header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:24px 28px;border-radius:14px;margin-bottom:20px;box-shadow:0 4px 18px rgba(0,0,0,.12);}
.inventory-toolbar{background:linear-gradient(180deg,#111827 0%,#0b1220 100%);border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:12px;box-shadow:0 10px 25px rgba(2,6,23,.25);margin-bottom:14px;}
.inventory-toolbar .form-control,.inventory-toolbar .form-select{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);color:#e5e7eb;border-radius:12px;height:44px;}
.inventory-toolbar .form-control::placeholder{color:rgba(229,231,235,.65);}
.inventory-toolbar .input-group-text{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);color:rgba(229,231,235,.85);border-radius:12px;}
.inventory-toolbar .form-control:focus,.inventory-toolbar .form-select:focus{box-shadow:0 0 0 .2rem rgba(99,102,241,.20);border-color:rgba(99,102,241,.55);}
.inventory-toolbar .form-select option{background:#1e293b;color:#e5e7eb;}
.btn-toolbar-add{background:#f97316;border:none;color:#111827;height:44px;border-radius:12px;padding:0 16px;font-weight:600;white-space:nowrap;}
.btn-toolbar-add:hover{background:#fb8a3a;color:#111827;}
.btn-toolbar-export{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);color:#e5e7eb;height:44px;border-radius:12px;padding:0 16px;font-weight:600;white-space:nowrap;}
.btn-toolbar-export:hover{background:rgba(255,255,255,.10);color:#fff;}
.card{border:none;border-radius:14px;box-shadow:0 4px 18px rgba(0,0,0,.08);}
.table-responsive{border-radius:10px;overflow:hidden;}
.table thead{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}
.table thead th{border:none;font-size:.78rem;font-weight:600;padding:10px 12px;}
.table tbody td{font-size:.83rem;vertical-align:middle;padding:9px 12px;}
.table tbody tr.table-warning{background:#fffbeb;}
.btn-edit{background-color:#17a2b8;border:none;color:#fff;}
.btn-edit:hover{background-color:#138496;color:#fff;}
.btn-delete{background-color:#dc3545;border:none;color:#fff;}
.btn-delete:hover{background-color:#c82333;color:#fff;}
.margin-positive{color:#28a745;font-weight:600;}
.margin-negative{color:#dc3545;font-weight:600;}
.modal-header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;}
.margin-display{font-size:1.1em;font-weight:600;padding:10px;border-radius:5px;background:#e9ecef;}
.loading-spinner{display:none;text-align:center;padding:20px;}
.nav-inventory-tabs .nav-link{color:#475569;font-weight:500;border-radius:10px 10px 0 0;}
.nav-inventory-tabs .nav-link:hover{color:#667eea;}
.nav-inventory-tabs .nav-link.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-color:transparent;}
@media(max-width:576px){
    .table{font-size:.75rem;}
    .inventory-toolbar{padding:8px;}
}
</style>
</head>
<body>
<?php include "sidebar.php"; ?>

<main class="app-main p-3 p-md-4">
<div class="container-fluid">
    <div class="page-header">
        <h4 class="mb-0"><i class="bi bi-box-seam me-2"></i>Product Inventory & Forecasting</h4>
        <p class="mb-0 mt-1 opacity-75">Real-time stock, demand forecasting, reorder recommendations</p>
    </div>

    <ul class="nav nav-tabs mb-3 nav-inventory-tabs" id="inventoryMainTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock-panel" type="button" role="tab">
                <i class="bi bi-boxes me-1"></i>Current Stock
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="forecast-tab" data-bs-toggle="tab" data-bs-target="#forecast-panel" type="button" role="tab">
                <i class="bi bi-graph-up me-1"></i>Forecasting
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="reorder-tab" data-bs-toggle="tab" data-bs-target="#reorder-panel" type="button" role="tab">
                <i class="bi bi-arrow-repeat me-1"></i>Reorder
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="workload-tab" data-bs-toggle="tab" data-bs-target="#workload-panel" type="button" role="tab">
                <i class="bi bi-briefcase me-1"></i>Peak Workload
            </button>
        </li>
    </ul>

    <div class="tab-content" id="inventoryTabContent">
        <!-- STOCK TAB -->
        <div class="tab-pane fade show active" id="stock-panel" role="tabpanel">
            <div class="inventory-toolbar">
                <div class="d-flex flex-column flex-lg-row gap-2 align-items-stretch align-items-lg-center">
                    <div class="flex-grow-1">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="inventorySearch" placeholder="Search by name, code..." autocomplete="off">
                        </div>
                    </div>
                    <div style="min-width:220px;">
                        <select class="form-select" id="inventoryCategoryFilter">
                            <option value="all" selected>All Categories</option>
                        </select>
                    </div>
                    <div id="stockSummary" class="d-flex align-items-center gap-1"></div>
                    <div class="d-flex gap-2 justify-content-end">
                        <?php if ($isOwner): ?>
                        <button type="button" class="btn btn-toolbar-add" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="bi bi-plus-lg"></i> Add Part
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-toolbar-export" id="inventoryExportBtn">
                            <i class="bi bi-download"></i> Export
                        </button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="loading-spinner" id="loadingSpinner">
                        <div class="spinner-border text-primary"><span class="visually-hidden">Loading…</span></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th><th>Category</th><th>Description / Code</th><th>Unit</th>
                                    <th>Unit Cost</th><th>Selling Price</th><th>Margin</th><th>Stock</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="productsTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORECAST TAB -->
        <div class="tab-pane fade" id="forecast-panel" role="tabpanel">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white"><i class="bi bi-graph-up me-1"></i>Weekly & Monthly Parts Forecasting</div>
                <div class="card-body">
                    <div id="forecastLoading" class="text-center py-3"><div class="spinner-border text-primary"></div></div>
                    <div id="forecastContent" style="display:none;">
                        <h6 class="text-muted mb-2">Weekly forecast (next 4 weeks)</h6>
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-hover">
                                <thead class="table-light"><tr><th>Part</th><th>Code</th><th>Avg weekly usage</th><th>Next 4 weeks predicted</th><th>Data points</th></tr></thead>
                                <tbody id="weeklyForecastBody"></tbody>
                            </table>
                        </div>
                        <h6 class="text-muted mb-2">Monthly forecast (next 3 months)</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light"><tr><th>Part</th><th>Code</th><th>Avg monthly usage</th><th>Next 3 months predicted</th><th>Months of data</th></tr></thead>
                                <tbody id="monthlyForecastBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header bg-info text-white"><i class="bi bi-calendar3 me-1"></i>Seasonal Demand (last 12 months)</div>
                <div class="card-body"><canvas id="seasonalChart" height="110"></canvas></div>
            </div>
        </div>

        <!-- REORDER TAB -->
        <div class="tab-pane fade" id="reorder-panel" role="tabpanel">
            <div class="card">
                <div class="card-header bg-warning text-dark"><i class="bi bi-arrow-repeat me-1"></i>Smart Reorder Recommendations</div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Items at or below their reorder threshold. Click Restock to record received inventory.</p>
                    <div id="reorderLoading" class="text-center py-3"><div class="spinner-border text-warning"></div></div>
                    <div id="reorderContent" style="display:none;">
                        <div id="reorderList"></div>
                        <p id="reorderEmpty" class="text-muted text-center mt-3" style="display:none;">✅ All stock levels are healthy!</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- WORKLOAD TAB -->
        <div class="tab-pane fade" id="workload-panel" role="tabpanel">
            <div class="card">
                <div class="card-header bg-secondary text-white"><i class="bi bi-briefcase me-1"></i>Peak Workload Prediction</div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Work orders by week to plan staffing and parts inventory.</p>
                    <div id="workloadLoading" class="text-center py-3"><div class="spinner-border text-secondary"></div></div>
                    <div id="workloadContent" style="display:none;">
                        <p id="workloadAvg" class="mb-2 fw-bold text-muted"></p>
                        <canvas id="workloadChart" height="110"></canvas>
                    </div>
                    <p id="workloadNoData" class="text-muted text-center mt-3" style="display:none;">No work order data yet.</p>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<?php if ($isOwner): ?>
<!-- ADD PRODUCT MODAL (owner only) -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addProductForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="addCategory" name="category_id" required><option value="">Select Category</option></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Product Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="addCode" name="code" required placeholder="e.g. EO-001">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="addDescription" name="description" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Unit <span class="text-danger">*</span></label>
                            <select class="form-select" id="addUnit" name="unit" required>
                                <option value="">Select Unit</option>
                                <option>Gallon</option><option>Liter</option><option>Piece</option><option>Box</option><option>Set</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Unit Cost <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="addUnitCost" name="unit_cost" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Selling Price <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="addSellingPrice" name="selling_price" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Initial Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="addInitialQuantity" name="initial_quantity" min="0" required value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Reorder Threshold</label>
                            <input type="number" class="form-control" id="addReorderThreshold" name="reorder_threshold" min="0" value="5">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Margin (Auto)</label>
                            <div class="margin-display" id="addMarginDisplay">₱0.00</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitAddProduct">Add Product</button>
            </div>
        </div>
    </div>
</div>

<!-- EDIT PRODUCT MODAL (owner only) -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editProductForm">
                    <input type="hidden" id="editProductId" name="product_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="editCategory" name="category_id" required><option value="">Select Category</option></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Product Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editCode" name="code" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editDescription" name="description" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Unit <span class="text-danger">*</span></label>
                            <select class="form-select" id="editUnit" name="unit" required>
                                <option value="">Select Unit</option>
                                <option>Gallon</option><option>Liter</option><option>Piece</option><option>Box</option><option>Set</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Unit Cost <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="editUnitCost" name="unit_cost" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Selling Price <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="editSellingPrice" name="selling_price" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Initial Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="editInitialQuantity" name="initial_quantity" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Reorder Threshold</label>
                            <input type="number" class="form-control" id="editReorderThreshold" name="reorder_threshold" min="0" value="5">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Margin (Auto)</label>
                            <div class="margin-display" id="editMarginDisplay">₱0.00</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitEditProduct">Update Product</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- RECORD RESTOCK MODAL (all roles) -->
<div class="modal fade" id="recordRestockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-box-arrow-in-down me-2"></i>Record Restock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="restockProductId">
                <p class="mb-2">Part: <strong id="restockProductName"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Quantity received <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="restockQuantity" min="1" value="20">
                </div>
                <div class="mb-3">
                    <label class="form-label">Remarks</label>
                    <input type="text" class="form-control" id="restockRemarks" placeholder="e.g. PO #123 received">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="submitRestock">Record Restock</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- Inject role for JS -->
<script>const IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;</script>
<script src="inventory.js"></script>
<script src="inventory-forecast.js"></script>
</body>
</html>