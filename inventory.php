<?php
// inventory.php — Inventory Management Page

session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$activePage = 'inventory';
$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Dspeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        /* ── Toolbar ── */
        .inv-toolbar {
            background: #161921;
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }

        .inv-toolbar .form-control,
        .inv-toolbar .form-select {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
            color: #e8ecf4;
            border-radius: 10px;
            height: 40px;
            font-size: .84rem;
        }

        .inv-toolbar .form-control::placeholder {
            color: #7a8499;
        }

        .inv-toolbar .form-select option {
            background: #1c2030;
        }

        .inv-toolbar .form-control:focus,
        .inv-toolbar .form-select:focus {
            border-color: rgba(232, 23, 93, .5);
            box-shadow: 0 0 0 3px rgba(232, 23, 93, .12);
            background: rgba(255, 255, 255, .09);
            color: #fff;
        }

        .inv-toolbar .input-group-text {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
            color: #7a8499;
            border-radius: 10px;
        }

        /* ── Nav tabs ── */
        .inv-tabs .nav-link {
            color: #7a8499;
            font-weight: 600;
            font-size: .83rem;
            border-radius: 10px 10px 0 0;
            padding: 9px 18px;
            border: none;
        }

        .inv-tabs .nav-link:hover {
            color: #e8ecf4;
        }

        .inv-tabs .nav-link.active {
            background: linear-gradient(135deg, #e8175d, #9b0d43);
            color: #fff;
            border: none;
        }

        /* ── Stock row low highlight ── */
        .row-low {
            background: rgba(245, 158, 11, .07) !important;
        }

        .row-zero {
            background: rgba(239, 68, 68, .07) !important;
        }

        /* ── Margin display ── */
        .margin-box {
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 8px;
            padding: 9px 13px;
            font-weight: 700;
            font-size: .95rem;
        }

        /* ── Reorder item row ── */
        .reorder-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, .06);
        }

        .reorder-row:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <main class="app-main">

        <!-- Page Header -->
        <div class="page-header mb-4">
            <h4><i class="bi bi-box-seam me-2"></i>Product Inventory & Forecasting</h4>
            <p>Manage stock, view demand forecasts, and handle reorders</p>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs inv-tabs mb-3" id="invTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-stock" id="tab-stock-btn">
                    <i class="bi bi-boxes me-1"></i>Current Stock
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
        </ul>

        <div class="tab-content">

            <!-- ══ STOCK TAB ══════════════════════════════════════════ -->
            <div class="tab-pane fade show active" id="tab-stock">

                <!-- Toolbar -->
                <div class="inv-toolbar">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="flex-grow-1">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="searchInput"
                                    placeholder="Search by name or code…">
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
                                    <i class="bi bi-plus-lg"></i> Add Part
                                </button>
                            <?php endif; ?>
                            <button class="btn-ghost" id="exportBtn">
                                <i class="bi bi-download"></i> CSV
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stock Table -->
                <div class="card">
                    <div class="card-body p-0">
                        <div id="loadingSpinner" style="display:none;text-align:center;padding:30px;">
                            <div class="spinner-border" style="color:#e8175d;"></div>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Category</th>
                                        <th>Description / Code</th>
                                        <th>Unit</th>
                                        <th>Cost</th>
                                        <th>Price</th>
                                        <th>Margin</th>
                                        <th>Stock</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="stockTableBody">
                                    <tr>
                                        <td colspan="9" style="text-align:center;padding:30px;color:#7a8499;">
                                            Loading products…
                                        </td>
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
                        <i class="bi bi-graph-up me-1"></i> Weekly & Monthly Parts Forecast
                    </div>
                    <div class="card-body">
                        <div id="forecastLoading" style="text-align:center;padding:30px;">
                            <div class="spinner-border" style="color:#e8175d;"></div>
                        </div>
                        <div id="forecastContent" style="display:none;">
                            <p class="section-title mb-3">Next 4 Weeks Forecast</p>
                            <div class="table-responsive mb-4">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Part</th>
                                            <th>Code</th>
                                            <th>Avg Weekly Usage</th>
                                            <th>Next 4 Weeks</th>
                                            <th>Data Points</th>
                                        </tr>
                                    </thead>
                                    <tbody id="weeklyBody"></tbody>
                                </table>
                            </div>
                            <p class="section-title mb-3">Next 3 Months Forecast</p>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Part</th>
                                            <th>Code</th>
                                            <th>Avg Monthly Usage</th>
                                            <th>Next 3 Months</th>
                                            <th>Months of Data</th>
                                        </tr>
                                    </thead>
                                    <tbody id="monthlyBody"></tbody>
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
            <div class="tab-pane fade" id="tab-reorder" id="reorder-panel">
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

        </div>
    </main>

        <!-- ADD PRODUCT MODAL (owner only) -->
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
                                <select class="form-input form-select" id="addCategory" name="category_id" required>
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
                                    <option>Gallon</option>
                                    <option>Liter</option>
                                    <option>Piece</option>
                                    <option>Box</option>
                                    <option>Set</option>
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
                                    <option>Gallon</option>
                                    <option>Liter</option>
                                    <option>Piece</option>
                                    <option>Box</option>
                                    <option>Set</option>
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
                                <label class="form-label">Margin (auto)</label>
                                <div class="margin-box" id="editMargin" style="color:#34d399;">₱0.00</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn-pink" id="submitEdit">Update Product</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- RESTOCK MODAL (all roles) -->
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Pass role to JS -->
    <script>const IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;</script>
    <script src="assets/js/inventory.js"></script>
</body>

</html>