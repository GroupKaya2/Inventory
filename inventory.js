// inventory.js – Inventory Management (Complete, No Errors)

let categories = [];
let allProducts = [];
let filteredProducts = [];

function getSearchValue() {
    const el = document.getElementById('inventorySearch');
    return (el?.value || '').trim().toLowerCase();
}

function getSelectedCategoryId() {
    const el = document.getElementById('inventoryCategoryFilter');
    const v = (el?.value || 'all').trim();
    if (v === '' || v === 'all') return null;
    const parsed = parseInt(v, 10);
    return Number.isFinite(parsed) ? parsed : null;
}

function renderProducts(products) {
    const tableBody = document.getElementById('productsTableBody');
    if (!tableBody) return;

    if (!products || products.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">No products found.</td></tr>';
        return;
    }

    tableBody.innerHTML = products.map(product => {
        const unitCost     = parseFloat(product.unit_cost);
        const sellingPrice = parseFloat(product.selling_price);
        const margin       = parseFloat(product.margin);
        const marginClass  = margin >= 0 ? 'margin-positive' : 'margin-negative';
        const marginSign   = margin >= 0 ? '+' : '';
        const stock        = product.current_stock != null ? parseInt(product.current_stock) : parseInt(product.initial_quantity);
        const threshold    = parseInt(product.reorder_threshold) || 5;
        const lowStock     = stock <= threshold;
        const stockClass   = stock <= 0 ? 'text-danger fw-bold' : (lowStock ? 'text-warning fw-bold' : 'text-success fw-bold');
        const stockBadge   = stock <= 0 ? '🔴' : (lowStock ? '🟡' : '');

        const safeDesc = String(product.description ?? '').replace(/'/g, "\\'");

        return `
            <tr class="${lowStock ? 'table-warning' : ''}">
                <td>${product.product_id}</td>
                <td>${product.category_name}</td>
                <td>
                    <div>${product.description}</div>
                    <small class="text-muted"><code>${product.code}</code></small>
                </td>
                <td>${product.unit}</td>
                <td>${formatCurrency(unitCost)}</td>
                <td>${formatCurrency(sellingPrice)}</td>
                <td class="${marginClass}">${marginSign}${formatCurrency(margin)}</td>
                <td class="${stockClass}">${stockBadge} ${stock}</td>
                <td>
                    <button class="btn btn-sm btn-outline-success me-1" onclick="openRestockModal(${product.product_id}, '${safeDesc}', 20)" title="Restock">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </button>
                    <button class="btn btn-sm btn-edit me-1" onclick="editProduct(${product.product_id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-delete" onclick="deleteProduct(${product.product_id}, '${safeDesc}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function applyFilters() {
    const q = getSearchValue();
    const selectedCategoryId = getSelectedCategoryId();

    filteredProducts = allProducts.filter(p => {
        if (selectedCategoryId !== null && parseInt(p.category_id, 10) !== selectedCategoryId) return false;
        if (!q) return true;
        const haystack = [p.description, p.code, p.category_name]
            .map(v => String(v ?? '').toLowerCase()).join(' ');
        return haystack.includes(q);
    });

    renderProducts(filteredProducts);
    updateStockSummary();
}

function updateStockSummary() {
    const total   = allProducts.length;
    const lowStk  = allProducts.filter(p => {
        const s = p.current_stock != null ? parseInt(p.current_stock) : parseInt(p.initial_quantity);
        return s <= parseInt(p.reorder_threshold || 5);
    }).length;

    const summEl = document.getElementById('stockSummary');
    if (summEl) {
        summEl.innerHTML = `
            <span class="badge bg-primary me-2">${total} Products</span>
            ${lowStk > 0 ? `<span class="badge bg-warning text-dark me-2">${lowStk} Low Stock</span>` : ''}
        `;
    }
}

function debounce(fn, waitMs) {
    let t = null;
    return function (...args) {
        window.clearTimeout(t);
        t = window.setTimeout(() => fn.apply(this, args), waitMs);
    };
}

function exportFilteredProductsToCsv() {
    const rows = filteredProducts.length ? filteredProducts : allProducts;
    const header = ['ID','Category','Description','Code','Unit','Unit Cost','Selling Price','Margin','Stock','Reorder At'];
    const csvEscape = v => {
        const s = String(v ?? '');
        if (/[",\n]/.test(s)) return `"${s.replace(/"/g, '""')}"`;
        return s;
    };
    const lines = [header.map(csvEscape).join(',')];
    rows.forEach(p => {
        const stock = p.current_stock != null ? p.current_stock : p.initial_quantity;
        lines.push([p.product_id, p.category_name, p.description, p.code, p.unit,
            p.unit_cost, p.selling_price, p.margin, stock, p.reorder_threshold].map(csvEscape).join(','));
    });
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    const pad2 = n => String(n).padStart(2, '0');
    const ts   = new Date();
    a.href     = url;
    a.download = `inventory_${ts.getFullYear()}-${pad2(ts.getMonth()+1)}-${pad2(ts.getDate())}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

function loadCategories() {
    fetch('get_categories.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            categories = data.data;

            ['addCategory','editCategory'].forEach(id => {
                const sel = document.getElementById(id);
                if (!sel) return;
                const cur = sel.value;
                sel.innerHTML = '<option value="">Select Category</option>';
                categories.forEach(cat => {
                    const opt = document.createElement('option');
                    opt.value = cat.category_id;
                    opt.textContent = cat.category_name;
                    sel.appendChild(opt);
                });
                if (cur) sel.value = cur;
            });

            const filterSel = document.getElementById('inventoryCategoryFilter');
            if (filterSel) {
                const cur = filterSel.value || 'all';
                filterSel.innerHTML = '<option value="all">All Categories</option>';
                categories.forEach(cat => {
                    const opt = document.createElement('option');
                    opt.value = cat.category_id;
                    opt.textContent = cat.category_name;
                    filterSel.appendChild(opt);
                });
                filterSel.value = cur;
            }
        })
        .catch(e => console.error('Error loading categories:', e));
}

function formatCurrency(amount) {
    return '₱' + parseFloat(amount || 0).toFixed(2);
}

function calculateMargin(unitCost, sellingPrice) {
    return parseFloat(sellingPrice || 0) - parseFloat(unitCost || 0);
}

function formatMargin(margin) {
    const formatted = formatCurrency(Math.abs(margin));
    return margin >= 0 ? formatted : '-' + formatted;
}

function loadProducts() {
    const tableBody     = document.getElementById('productsTableBody');
    const loadingSpinner = document.getElementById('loadingSpinner');
    if (loadingSpinner) loadingSpinner.style.display = 'block';
    if (tableBody)      tableBody.innerHTML = '';

    fetch('fetch_products.php')
        .then(r => r.json())
        .then(data => {
            if (loadingSpinner) loadingSpinner.style.display = 'none';
            if (data.success) {
                allProducts = Array.isArray(data.data) ? data.data : [];
                applyFilters();
                document.dispatchEvent(new Event('productReloaded'));
            } else {
                if (tableBody) tableBody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(error => {
            if (loadingSpinner) loadingSpinner.style.display = 'none';
            if (tableBody) tableBody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">Network error: ${error.message}</td></tr>`;
        });
}

function calculateAddMargin() {
    const unitCost     = document.getElementById('addUnitCost')?.value || 0;
    const sellingPrice = document.getElementById('addSellingPrice')?.value || 0;
    const margin       = calculateMargin(unitCost, sellingPrice);
    const marginDisplay = document.getElementById('addMarginDisplay');
    if (!marginDisplay) return;
    marginDisplay.textContent = formatMargin(margin);
    marginDisplay.className   = 'margin-display ' + (margin >= 0 ? 'margin-positive' : 'margin-negative');
}

function calculateEditMargin() {
    const unitCost     = document.getElementById('editUnitCost')?.value || 0;
    const sellingPrice = document.getElementById('editSellingPrice')?.value || 0;
    const margin       = calculateMargin(unitCost, sellingPrice);
    const marginDisplay = document.getElementById('editMarginDisplay');
    if (!marginDisplay) return;
    marginDisplay.textContent = formatMargin(margin);
    marginDisplay.className   = 'margin-display ' + (margin >= 0 ? 'margin-positive' : 'margin-negative');
}

function addProduct() {
    const form = document.getElementById('addProductForm');
    const formData = new FormData(form);
    fetch('add_product.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon:'success', title:'Added!', text:data.message, timer:1500, showConfirmButton:false });
                bootstrap.Modal.getInstance(document.getElementById('addProductModal'))?.hide();
                form.reset();
                const addMarginDisplay = document.getElementById('addMarginDisplay');
                if (addMarginDisplay) { addMarginDisplay.textContent='₱0.00'; addMarginDisplay.className='margin-display'; }
                loadProducts();
            } else {
                Swal.fire({ icon:'error', title:'Error!', text:data.message });
            }
        })
        .catch(e => Swal.fire({ icon:'error', title:'Error!', text:'Network error: '+e.message }));
}

function editProduct(productId) {
    fetch(`get_product.php?product_id=${productId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { Swal.fire({ icon:'error', title:'Error!', text:data.message }); return; }
            const p = data.data;
            document.getElementById('editProductId').value        = p.product_id;
            document.getElementById('editCategory').value         = p.category_id || '';
            document.getElementById('editDescription').value      = p.description;
            document.getElementById('editUnit').value             = p.unit;
            document.getElementById('editCode').value             = p.code;
            document.getElementById('editUnitCost').value         = p.unit_cost;
            document.getElementById('editSellingPrice').value     = p.selling_price;
            document.getElementById('editInitialQuantity').value  = p.initial_quantity;
            document.getElementById('editReorderThreshold').value = p.reorder_threshold != null ? p.reorder_threshold : 5;
            calculateEditMargin();
            new bootstrap.Modal(document.getElementById('editProductModal')).show();
        })
        .catch(e => Swal.fire({ icon:'error', title:'Error!', text:'Network error: '+e.message }));
}

function updateProduct() {
    const form = document.getElementById('editProductForm');
    const formData = new FormData(form);
    fetch('update_product.php', { method:'POST', body:formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon:'success', title:'Updated!', text:data.message, timer:1500, showConfirmButton:false });
                bootstrap.Modal.getInstance(document.getElementById('editProductModal'))?.hide();
                loadProducts();
            } else {
                Swal.fire({ icon:'error', title:'Error!', text:data.message });
            }
        })
        .catch(e => Swal.fire({ icon:'error', title:'Error!', text:'Network error: '+e.message }));
}

function deleteProduct(productId, productDescription) {
    Swal.fire({
        title:'Are you sure?',
        text:`Delete "${productDescription}"?`,
        icon:'warning',
        showCancelButton:true,
        confirmButtonColor:'#dc3545',
        cancelButtonColor:'#6c757d',
        confirmButtonText:'Yes, delete it!'
    }).then(result => {
        if (!result.isConfirmed) return;
        const fd = new FormData();
        fd.append('product_id', productId);
        fetch('delete_product.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon:'success', title:'Deleted!', text:data.message, timer:1500, showConfirmButton:false });
                    loadProducts();
                } else {
                    Swal.fire({ icon:'error', title:'Error!', text:data.message });
                }
            })
            .catch(e => Swal.fire({ icon:'error', title:'Error!', text:'Network error: '+e.message }));
    });
}

// ── EVENT LISTENERS ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    loadCategories();
    loadProducts();

    const debouncedApply = debounce(applyFilters, 200);

    const searchInput = document.getElementById('inventorySearch');
    if (searchInput) searchInput.addEventListener('input', debouncedApply);

    const filterSelect = document.getElementById('inventoryCategoryFilter');
    if (filterSelect) filterSelect.addEventListener('change', applyFilters);

    const exportBtn = document.getElementById('inventoryExportBtn');
    if (exportBtn) exportBtn.addEventListener('click', exportFilteredProductsToCsv);

    const submitAdd = document.getElementById('submitAddProduct');
    if (submitAdd) {
        submitAdd.addEventListener('click', function () {
            const form = document.getElementById('addProductForm');
            if (form.checkValidity()) addProduct();
            else form.reportValidity();
        });
    }

    const submitEdit = document.getElementById('submitEditProduct');
    if (submitEdit) {
        submitEdit.addEventListener('click', function () {
            const form = document.getElementById('editProductForm');
            if (form.checkValidity()) updateProduct();
            else form.reportValidity();
        });
    }

    document.getElementById('addUnitCost')?.addEventListener('input', calculateAddMargin);
    document.getElementById('addSellingPrice')?.addEventListener('input', calculateAddMargin);
    document.getElementById('editUnitCost')?.addEventListener('input', calculateEditMargin);
    document.getElementById('editSellingPrice')?.addEventListener('input', calculateEditMargin);

    document.getElementById('addProductModal')?.addEventListener('hidden.bs.modal', function () {
        document.getElementById('addProductForm')?.reset();
        const addCat = document.getElementById('addCategory');
        const addUnit = document.getElementById('addUnit');
        const addThresh = document.getElementById('addReorderThreshold');
        const addMargin = document.getElementById('addMarginDisplay');
        if (addCat)    addCat.value = '';
        if (addUnit)   addUnit.value = '';
        if (addThresh) addThresh.value = 5;
        if (addMargin) { addMargin.textContent = '₱0.00'; addMargin.className = 'margin-display'; }
    });
});