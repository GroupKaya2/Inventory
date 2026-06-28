<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$activePage = 'inventory';
$isOwner    = ($_SESSION['role'] ?? 'manager') === 'owner';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory DSpeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/inventory.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="app-main">

    <div class="page-header mb-4">
        <h4><i class="bi bi-box-seam me-2"></i>Product Inventory & Forecasting</h4>
    </div>

    <ul class="nav nav-tabs inv-tabs mb-3" id="invTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-stock" id="tab-stock-btn">
                <i class="bi bi-boxes me-1"></i>Products & Stocks
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-forecast" id="tab-forecast-btn">
                <i class="bi bi-graph-up me-1"></i>Forecasting
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-reorder" id="tab-reorder-btn">
                <i class="bi bi-arrow-repeat me-1"></i>Reorder
            </button>
        </li>
        <?php if ($isOwner): ?>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-categories" id="tab-categories-btn">
                <i class="bi bi-tags me-1"></i>Categories
            </button>
        </li>
        <?php endif; ?>
    </ul>

    <div class="tab-content">

        <!-- STOCK TAB -->
        <div class="tab-pane fade show active" id="tab-stock">
            <div class="inv-toolbar">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="flex-grow-1">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="Search by name or code…">
                        </div>
                    </div>
                    <div style="min-width:200px;">
                        <select class="form-select" id="categoryFilter">
                            <option value="">All Categories</option>
                        </select>
                    </div>
                    <div id="stockSummary" class="d-flex gap-2"></div>
                    <div class="d-flex gap-2">
                        <?php if ($isOwner): ?>
                            <button class="btn-pink" data-bs-toggle="modal" data-bs-target="#addModal">
                                <i class="bi bi-plus-lg"></i> Add New Product
                            </button>
                        <?php endif; ?>
                        <button class="btn-ghost" id="exportBtn">
                            <i class="bi bi-download"></i> CSV
                        </button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Category</th>
                                    <th>Description / Code</th>
                                    <th>Unit</th>
                                    <th>Cost</th>
                                    <th>Selling Price</th>
                                    <th>Margin</th>
                                    <th>Stock</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="stockTableBody">
                                <tr>
                                    <td colspan="9" style="text-align:center;padding:30px;color:#7a8499;">Loading products…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORECAST TAB -->
        <div class="tab-pane fade" id="tab-forecast">
            <div class="card mb-3">
                <div class="card-header-pink">
                    <i class="bi bi-calculator me-1"></i> Forecasting Method — Moving Average + Safety Stock
                </div>
                <div class="card-body" style="padding:14px 18px;">
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        <span class="summary-pill"><i class="bi bi-clock me-1"></i>Lead Time: <strong id="fcLeadTime">5 days</strong></span>
                        <span class="summary-pill"><i class="bi bi-shield me-1"></i>Safety Stock: <strong id="fcSafetyStock">3 units</strong></span>
                        <span class="summary-pill"><i class="bi bi-calendar3 me-1"></i>Based on: <strong id="fcMonths">last 3 months</strong></span>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header-pink">
                    <i class="bi bi-graph-up me-1"></i> Parts Demand Forecast (Next Month)
                </div>
                <div class="card-body p-0">
                    <div id="forecastLoading" style="text-align:center;padding:30px;">
                        <div class="spinner-border" style="color:#4ade80;"></div>
                    </div>
                    <div id="forecastContent" style="display:none;">
                        <div style="padding:12px 16px 0;" id="forecastMonthBadges"></div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Part Name</th>
                                        <th style="text-align:center;">Avg Used/Month</th>
                                        <th style="text-align:center;">Forecast Needed</th>
                                        <th style="text-align:center;">Reorder Point</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="forecastBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header-pink">
                    <i class="bi bi-calendar3 me-1"></i> Seasonal Demand — Last 12 Months
                </div>
                <div class="card-body">
                    <canvas id="seasonalChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- REORDER TAB -->
        <div class="tab-pane fade" id="tab-reorder">
            <div class="card">
                <div class="card-header-pink">
                    <i class="bi bi-arrow-repeat me-1"></i> Smart Reorder Recommendations
                </div>
                <div class="card-body p-0">
                    <p style="padding:14px 18px 0;font-size:.82rem;color:#7a8499;">
                        Items at or below reorder threshold. Click Restock to record received inventory.
                    </p>
                    <div id="reorderLoading" style="text-align:center;padding:30px;">
                        <div class="spinner-border" style="color:#e8175d;"></div>
                    </div>
                    <div id="reorderList" style="display:none;"></div>
                    <p id="reorderEmpty" style="display:none;text-align:center;padding:30px;color:#7a8499;">
                        ✅ All stock levels are healthy!
                    </p>
                </div>
            </div>
        </div>

        <!-- CATEGORIES TAB -->
        <?php if ($isOwner): ?>
        <div class="tab-pane fade" id="tab-categories">
            <div class="card">
                <div class="card-header-pink">
                    <i class="bi bi-tags me-1"></i> Category Management
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2 mb-3">
                        <button class="btn-pink" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="bi bi-plus-lg me-1"></i> Add Category
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Category Name</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="categoriesBody">
                                <tr>
                                    <td colspan="3" style="text-align:center;padding:30px;color:#7a8499;">Loading categories…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- ADD PRODUCT MODAL -->
<?php if ($isOwner): ?>
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dark">
        <div class="modal-content modal-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Category *</label>
                        <select class="form-input form-select" id="addCategory" required>
                            <option value="">Select Category</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Product Code</label>
                        <input type="text" class="form-input" id="addCode" placeholder="e.g. EO-001">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description *</label>
                        <input type="text" class="form-input" id="addDesc" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Unit *</label>
                        <select class="form-input form-select" id="addUnit" required>
                            <option value="">Select Unit</option>
                            <option>Gallon</option><option>Liter</option><option>Piece</option>
                            <option>Box</option><option>Set</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Unit Cost</label>
                        <input type="number" class="form-input" id="addCost" step="0.01" min="0" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Selling Price</label>
                        <input type="number" class="form-input" id="addPrice" step="0.01" min="0" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Initial Qty</label>
                        <input type="number" class="form-input" id="addQty" min="0" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Reorder Threshold</label>
                        <input type="number" class="form-input" id="addThresh" min="0" value="5">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Margin (auto)</label>
                        <div class="margin-box" id="addMargin" style="color:#34d399;">₱0.00</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-pink" id="submitAdd">Save Product</button>
            </div>
        </div>
    </div>
</div>

<!-- EDIT PRODUCT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modal-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Category *</label>
                        <select class="form-input form-select" id="editCategory" required>
                            <option value="">Select Category</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Product Code</label>
                        <input type="text" class="form-input" id="editCode">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description *</label>
                        <input type="text" class="form-input" id="editDesc" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Unit *</label>
                        <select class="form-input form-select" id="editUnit" required>
                            <option value="">Select Unit</option>
                            <option>Gallon</option><option>Liter</option><option>Piece</option>
                            <option>Box</option><option>Set</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Unit Cost</label>
                        <input type="number" class="form-input" id="editCost" step="0.01" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Selling Price</label>
                        <input type="number" class="form-input" id="editPrice" step="0.01" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Initial Qty</label>
                        <input type="number" class="form-input" id="editQty" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Reorder Threshold</label>
                        <input type="number" class="form-input" id="editThresh" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Margin</label>
                        <div class="margin-box" id="editMargin" style="color:#34d399;">₱0.00</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-pink" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-pink" id="submitEdit">Update Product</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- RESTOCK MODAL -->
<div class="modal fade" id="restockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-dark">
            <div class="modal-header" style="background:linear-gradient(135deg,#10b981,#059669);">
                <h5 class="modal-title"><i class="bi bi-box-arrow-in-down me-2"></i>Record Restock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="restockId">
                <p>Part: <strong id="restockName" style="color:#34d399;"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Quantity Received *</label>
                    <input type="number" class="form-input" id="restockQty" min="1" value="20">
                </div>
                <div class="mb-3">
                    <label class="form-label">Remarks</label>
                    <input type="text" class="form-input" id="restockRemarks" placeholder="e.g. PO #123 received">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="submitRestock"
                    style="background:linear-gradient(135deg,#10b981,#059669);border:none;color:#fff;padding:9px 20px;border-radius:50px;font-weight:700;cursor:pointer;">
                    Record Restock
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ADD CATEGORY MODAL -->
<?php if ($isOwner): ?>
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Category Name *</label>
                    <input type="text" class="form-input" id="addCategoryName" placeholder="e.g. Engine Parts">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-pink" id="submitAddCategory">Add Category</button>
            </div>
        </div>
    </div>
</div>

<!-- EDIT CATEGORY MODAL -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editCategoryId">
                <div class="mb-3">
                    <label class="form-label">Category Name *</label>
                    <input type="text" class="form-input" id="editCategoryName">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-pink" id="submitEditCategory">Update Category</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>const IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;</script>
<script src="assets/js/InventoryAPI.js"></script>
<script src="assets/js/StockLedger.js"></script>
<script src="assets/js/InventoryRenderer.js"></script>
<script src="assets/js/InventoryController.js"></script>
<script src="assets/js/inventory.js?v=<?= time() ?>"></script>
<script>
(function () {
    'use strict';
    var _chartInstance = null;
    var _fLoaded = false;

    function runForecast() {
        if (_fLoaded) return;
        _fLoaded = true;
        var loadingEl = document.getElementById('forecastLoading');
        var contentEl = document.getElementById('forecastContent');
        if (loadingEl) { loadingEl.style.display = 'block'; loadingEl.innerHTML = '<div class="spinner-border" style="color:#4ade80;"></div>'; }
        if (contentEl) contentEl.style.display = 'none';

        fetch('backend/forecast.php')
            .then(function(res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
            .then(function(json) {
                if (!json.success) throw new Error(json.message || 'Server error');
                if (loadingEl) loadingEl.style.display = 'none';
                if (contentEl) contentEl.style.display = '';

                var items = json.items || [], monthly = json.monthly || [], constants = json.constants || {};
                var leadEl = document.getElementById('fcLeadTime');
                var safeEl = document.getElementById('fcSafetyStock');
                if (leadEl) leadEl.textContent = (constants.lead_time_days || 5) + ' days';
                if (safeEl) safeEl.textContent = (constants.safety_stock || 3) + ' units';

                var monthLabels = constants.months_used || [];
                var fmt = function(ym) {
                    if (!ym) return '';
                    var p = ym.split('-');
                    var n = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    return n[parseInt(p[1]) - 1] + ' ' + p[0];
                };
                var badgeWrap = document.getElementById('forecastMonthBadges');
                if (badgeWrap && monthLabels.length) {
                    badgeWrap.innerHTML = '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">'
                        + '<span style="font-size:.7rem;color:#64748b;align-self:center;">Data period:</span>'
                        + monthLabels.map(function(m) { return '<span class="summary-pill">' + fmt(m) + '</span>'; }).join('')
                        + '</div>';
                }

                function statusBadge(s) {
                    var map = {
                        'OUT_OF_STOCK': ['badge-red', '&#9940; OUT OF STOCK'],
                        'REORDER_NOW':  ['badge-red', '&#128308; REORDER NOW'],
                        'LOW_STOCK':    ['badge-yellow', '&#128993; LOW STOCK'],
                        'SUFFICIENT':   ['badge-green', '&#128994; SUFFICIENT']
                    };
                    var pair = map[s] || ['badge-gray', s];
                    return '<span class="' + pair[0] + '">' + pair[1] + '</span>';
                }

                var fBody = document.getElementById('forecastBody');
                if (fBody) {
                    fBody.innerHTML = !items.length
                        ? '<tr><td colspan="5" style="text-align:center;color:#7a8499;padding:24px;">No inventory data found. Add products and record sales to generate forecasts.</td></tr>'
                        : items.map(function(item) {
                            var rc = (item.status === 'OUT_OF_STOCK' || item.status === 'REORDER_NOW') ? 'row-zero' : item.status === 'LOW_STOCK' ? 'row-low' : '';
                            return '<tr class="' + rc + '">'
                                + '<td style="font-weight:600;color:#e2e8f0;">' + (item.description || '') + '</td>'
                                + '<td style="text-align:center;color:#60a5fa;font-weight:600;">' + item.avg_monthly + '</td>'
                                + '<td style="text-align:center;color:#4ade80;font-weight:700;">' + item.forecast_needed + ' pcs</td>'
                                + '<td style="text-align:center;color:#f87171;font-weight:700;">' + item.reorder_point + '</td>'
                                + '<td>' + statusBadge(item.status) + '</td>'
                                + '</tr>';
                        }).join('');
                }

                var chartCanvas = document.getElementById('seasonalChart');
                if (chartCanvas) {
                    if (_chartInstance) { _chartInstance.destroy(); _chartInstance = null; }
                    if (monthly.length) {
                        _chartInstance = new Chart(chartCanvas.getContext('2d'), {
                            type: 'bar',
                            data: {
                                labels: monthly.map(function(m) { return m.month_label; }),
                                datasets: [
                                    { label: 'Parts ₱', data: monthly.map(function(m) { return m.parts_total; }), backgroundColor: 'rgba(96,165,250,.7)' },
                                    { label: 'Labor ₱', data: monthly.map(function(m) { return m.labor_total; }), backgroundColor: 'rgba(74,222,128,.7)' }
                                ]
                            },
                            options: {
                                responsive: true,
                                plugins: { legend: { labels: { color: '#e2e8f0' } } },
                                scales: {
                                    x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,.05)' } },
                                    y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,.05)' } }
                                }
                            }
                        });
                    } else {
                        chartCanvas.parentElement.innerHTML = '<p style="text-align:center;color:#4b5a6e;padding:24px;font-size:.84rem;">No monthly revenue data yet. Record sales to see the seasonal demand chart.</p>';
                    }
                }
            })
            .catch(function(e) {
                _fLoaded = false;
                if (loadingEl) {
                    loadingEl.style.display = 'block';
                    loadingEl.innerHTML = '<p style="color:#fca5a5;padding:16px;"><i class="bi bi-exclamation-triangle me-2"></i>Forecast error: ' + e.message + '</p>'
                        + '<button onclick="window._retryForecast()" style="padding:8px 18px;background:#16a34a;border:none;border-radius:8px;color:#fff;cursor:pointer;"><i class="bi bi-arrow-clockwise me-1"></i>Retry</button>';
                }
            });
    }

    window._retryForecast = function() { _fLoaded = false; runForecast(); };

    var btn = document.getElementById('tab-forecast-btn');
    if (btn) {
        btn.addEventListener('shown.bs.tab', runForecast);
        btn.addEventListener('click', function() { setTimeout(runForecast, 150); });
    }
})();
</script>
</body>
</html>