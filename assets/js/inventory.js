'use strict';

const API = 'backend/products.php';
const FCAST = 'backend/forecast.php';

function peso(n) {
    return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function stockBadge(qty, threshold) {
    qty = parseInt(qty) || 0;
    threshold = parseInt(threshold) || 0;
    if (qty <= 0) return `<span class="badge bg-danger">Out of Stock</span>`;
    if (qty <= threshold) return `<span class="badge bg-warning text-dark">${qty} ⚠ Low</span>`;
    return `<span class="badge bg-success">${qty}</span>`;
}

let allProducts = [];
let allCategories = [];

const tbody = document.getElementById('stockTableBody');
const searchInput = document.getElementById('searchInput');
const categoryFilter = document.getElementById('categoryFilter');
const exportBtn = document.getElementById('exportBtn');

/*LOAD PRODUCTS*/
async function loadProducts() {
    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:30px;">
            <div class="spinner-border" style="color:#e8175d;"></div>
        </td></tr>`;

    try {
        const res = await fetch(`${API}?action=fetch`);
        const json = await res.json();

        if (!json.success) {
            tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:24px;color:#fca5a5;">
                    ⚠ ${json.message || 'Failed to load products.'}</td></tr>`;
            return;
        }

        allProducts = json.data || [];
        renderTable(allProducts);
        buildSummary(allProducts);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:24px;color:#fca5a5;">
                ⚠ Network error — could not reach backend. (${err.message})</td></tr>`;
    }
}
function renderTable(products) {
    if (!products.length) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:30px;color:#7a8499;">
                No products found.</td></tr>`;
        return;
    }

    tbody.innerHTML = products.map((p, idx) => `
            <tr data-id="${p.product_id}">
                <td><span class="badge-gray">${idx + 1}</span></td>
                <td>${p.category_name || '—'}</td>
                <td>
                    <div style="font-weight:600;">${p.description}</div>
                    ${p.code ? `<div style="font-size:.75rem;color:#7a8499;">${p.code}</div>` : ''}
                </td>
                <td>${p.unit}</td>
                <td style="color:#93c5fd;">${peso(p.unit_cost)}</td>
                <td style="color:#fff;">${peso(p.selling_price)}</td>
                <td style="color:${parseFloat(p.margin) >= 0 ? '#34d399' : '#f87171'};">${peso(p.margin)}</td>
                <td>${stockBadge(p.current_stock, p.reorder_threshold)}</td>
                <td>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-info"
                            onclick="openRestock(${p.product_id}, '${escHtml(p.description)}')"
                            title="Restock"><i class="bi bi-box-arrow-in-down"></i></button>
                        ${IS_OWNER ? `
                        <button class="btn btn-sm btn-outline-warning"
                            onclick="openEdit(${p.product_id})"
                            title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger"
                            onclick="deleteProduct(${p.product_id}, '${escHtml(p.description)}')"
                            title="Delete"><i class="bi bi-trash"></i></button>
                        ` : ''}
                    </div>
                </td>
            </tr>`).join('');
}

function escHtml(str) {
    return String(str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function buildSummary(products) {
    const el = document.getElementById('stockSummary');
    if (!el) return;
    const low = products.filter(p => parseInt(p.current_stock) <= parseInt(p.reorder_threshold) && parseInt(p.current_stock) > 0).length;
    const out = products.filter(p => parseInt(p.current_stock) <= 0).length;
    el.innerHTML = `
            <span class="summary-pill">Total: <strong>${products.length}</strong></span>
            ${low ? `<span class="summary-pill" style="border-color:#fbbf24;color:#fbbf24;">⚠ Low: <strong>${low}</strong></span>` : ''}
            ${out ? `<span class="summary-pill" style="border-color:#f87171;color:#f87171;">✖ Out: <strong>${out}</strong></span>` : ''}
        `;
}

/* search */
function applyFilters() {
    const q = (searchInput?.value || '').toLowerCase();
    const cat = categoryFilter?.value || '';
    const filtered = allProducts.filter(p => {
        const matchQ = !q || p.description.toLowerCase().includes(q) || (p.code || '').toLowerCase().includes(q);
        const matchCat = !cat || p.category_name === cat;
        return matchQ && matchCat;
    });
    renderTable(filtered);
}

searchInput?.addEventListener('input', applyFilters);
categoryFilter?.addEventListener('change', applyFilters);

/*  LOAD CATEGORIES */
async function loadCategories() {
    try {
        const res = await fetch(`${API}?action=categories`);
        const json = await res.json();
        if (!json.success) return;

        allCategories = json.data || [];

        // Populate filter dropdown
        if (categoryFilter) {
            categoryFilter.innerHTML = '<option value="">All Categories</option>' +
                allCategories.map(c => `<option value="${c.category_name}">${c.category_name}</option>`).join('');
        }

        // Populate add/edit modal selects
        ['addCategory', 'editCategory'].forEach(id => {
            const sel = document.getElementById(id);
            if (!sel) return;
            sel.innerHTML = '<option value="">Select Category</option>' +
                allCategories.map(c => `<option value="${c.category_id}">${c.category_name}</option>`).join('');
        });
    } catch (e) {
        console.warn('Could not load categories:', e);
    }
}

/*  ADD PRODUCT*/
['addCost', 'addPrice'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', () => {
        const cost = parseFloat(document.getElementById('addCost')?.value) || 0;
        const price = parseFloat(document.getElementById('addPrice')?.value) || 0;
        const el = document.getElementById('addMargin');
        if (el) el.textContent = peso(price - cost);
    });
});

document.getElementById('submitAdd')?.addEventListener('click', async () => {
    const btn = document.getElementById('submitAdd');

    const catId = document.getElementById('addCategory')?.value;
    const desc = document.getElementById('addDesc')?.value.trim();
    const unit = document.getElementById('addUnit')?.value;

    if (!catId || !desc || !unit) {
        Swal.fire({ icon: 'warning', title: 'Required fields missing', text: 'Category, Description and Unit are required.' });
        return;
    }

    const fd = new FormData();
    fd.append('category_id', catId);
    fd.append('description', desc);
    fd.append('unit', unit);
    fd.append('code', document.getElementById('addCode')?.value.trim() || '');
    fd.append('unit_cost', document.getElementById('addCost')?.value || '0');
    fd.append('selling_price', document.getElementById('addPrice')?.value || '0');
    fd.append('initial_quantity', document.getElementById('addQty')?.value || '0');
    fd.append('reorder_threshold', document.getElementById('addThresh')?.value || '5');

    btn.disabled = true;
    btn.textContent = 'Saving…';

    try {
        const res = await fetch(`${API}?action=add`, { method: 'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('addModal'))?.hide();
            Swal.fire({ icon: 'success', title: 'Product added!', timer: 1400, showConfirmButton: false });
            await loadProducts();
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: json.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network error', text: e.message });
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save Product';
    }
});

/* EDIT PRODUCT*/
['editCost', 'editPrice'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', () => {
        const cost = parseFloat(document.getElementById('editCost')?.value) || 0;
        const price = parseFloat(document.getElementById('editPrice')?.value) || 0;
        const el = document.getElementById('editMargin');
        if (el) el.textContent = peso(price - cost);
    });
});

async function openEdit(id) {
    try {
        const res = await fetch(`${API}?action=get&id=${id}`);
        const json = await res.json();
        if (!json.success) { Swal.fire({ icon: 'error', title: 'Error', text: json.message }); return; }

        const p = json.data;
        document.getElementById('editId').value = p.product_id;
        document.getElementById('editCode').value = p.code || '';
        document.getElementById('editDesc').value = p.description || '';
        document.getElementById('editCost').value = p.unit_cost || 0;
        document.getElementById('editPrice').value = p.selling_price || 0;
        document.getElementById('editQty').value = p.initial_quantity || 0;
        document.getElementById('editThresh').value = p.reorder_threshold || 5;

        // Set category
        const catSel = document.getElementById('editCategory');
        if (catSel) catSel.value = p.category_id;

        // Set unit
        const unitSel = document.getElementById('editUnit');
        if (unitSel) unitSel.value = p.unit;

        // Update margin display
        const margin = parseFloat(p.selling_price) - parseFloat(p.unit_cost);
        const mEl = document.getElementById('editMargin');
        if (mEl) mEl.textContent = peso(margin);

        new bootstrap.Modal(document.getElementById('editModal')).show();
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network error', text: e.message });
    }
}

document.getElementById('submitEdit')?.addEventListener('click', async () => {
    const btn = document.getElementById('submitEdit');

    const id = document.getElementById('editId')?.value;
    const catId = document.getElementById('editCategory')?.value;
    const desc = document.getElementById('editDesc')?.value.trim();
    const unit = document.getElementById('editUnit')?.value;

    if (!id || !catId || !desc || !unit) {
        Swal.fire({ icon: 'warning', title: 'Required fields missing', text: 'Category, Description and Unit are required.' });
        return;
    }

    const fd = new FormData();
    fd.append('product_id', id);
    fd.append('category_id', catId);
    fd.append('description', desc);
    fd.append('unit', unit);
    fd.append('code', document.getElementById('editCode')?.value.trim() || '');
    fd.append('unit_cost', document.getElementById('editCost')?.value || '0');
    fd.append('selling_price', document.getElementById('editPrice')?.value || '0');
    fd.append('initial_quantity', document.getElementById('editQty')?.value || '0');
    fd.append('reorder_threshold', document.getElementById('editThresh')?.value || '5');

    btn.disabled = true;
    btn.textContent = 'Saving…';

    try {
        const res = await fetch(`${API}?action=update`, { method: 'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide();
            Swal.fire({ icon: 'success', title: 'Updated!', timer: 1400, showConfirmButton: false });
            await loadProducts();
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: json.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network error', text: e.message });
    } finally {
        btn.disabled = false;
        btn.textContent = 'Update Product';
    }
});

/*DELETE PRODUCT */
async function deleteProduct(id, name) {
    const confirm = await Swal.fire({
        title: 'Delete this product?',
        text: `"${name}" will be permanently removed.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Delete',
    });
    if (!confirm.isConfirmed) return;

    const fd = new FormData();
    fd.append('id', id);

    try {
        const res = await fetch(`${API}?action=delete`, { method: 'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1200, showConfirmButton: false });
            await loadProducts();
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: json.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network error', text: e.message });
    }
}

/*  RESTOCK*/
function openRestock(id, name) {
    document.getElementById('restockId').value = id;
    document.getElementById('restockName').textContent = name;
    document.getElementById('restockQty').value = 20;
    document.getElementById('restockRemarks').value = '';
    new bootstrap.Modal(document.getElementById('restockModal')).show();
}

document.getElementById('submitRestock')?.addEventListener('click', async () => {
    const btn = document.getElementById('submitRestock');
    const id = document.getElementById('restockId')?.value;
    const qty = parseInt(document.getElementById('restockQty')?.value) || 0;
    const remarks = document.getElementById('restockRemarks')?.value.trim() || '';

    if (qty <= 0) {
        Swal.fire({ icon: 'warning', title: 'Enter a valid quantity' });
        return;
    }

    const fd = new FormData();
    fd.append('product_id', id);
    fd.append('quantity', qty);
    fd.append('remarks', remarks);

    btn.disabled = true;
    btn.textContent = 'Saving…';

    try {
        const res = await fetch('backend/products.php?action=restock', { method: 'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('restockModal'))?.hide();
            await Swal.fire({
                icon: 'success',
                title: 'Restocked!',
                html: `<b>${json.product}</b><br>+${json.qty_added} units added<br>New stock: <b>${json.new_stock}</b>`,
                timer: 2000,
                showConfirmButton: false,
            });
            await loadProducts();
            // Refresh reorder tab if it was already loaded
            reorderLoaded = false;
        } else {
            Swal.fire({ icon: 'error', title: 'Restock Failed', text: json.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
    } finally {
        btn.disabled = false;
        btn.textContent = 'Record Restock';
    }
});

/*EXPORT CSV — Stock Table */
exportBtn?.addEventListener('click', () => {
    const q = (searchInput?.value || '').toLowerCase();
    const cat = categoryFilter?.value || '';
    const visible = allProducts.filter(p => {
        const matchQ = !q || p.description.toLowerCase().includes(q) || (p.code || '').toLowerCase().includes(q);
        const matchCat = !cat || p.category_name === cat;
        return matchQ && matchCat;
    });

    if (!visible.length) {
        Swal.fire({ icon: 'warning', title: 'Nothing to export', text: 'No products match the current filter.' });
        return;
    }

    const rows = [['#', 'Category', 'Description', 'Code', 'Unit', 'Unit Cost', 'Selling Price', 'Margin', 'Current Stock', 'Reorder Threshold']];
    visible.forEach((p, i) => rows.push([
        i + 1,
        p.category_name || '',
        p.description,
        p.code || '',
        p.unit,
        parseFloat(p.unit_cost || 0).toFixed(2),
        parseFloat(p.selling_price || 0).toFixed(2),
        parseFloat(p.margin || 0).toFixed(2),
        p.current_stock,
        p.reorder_threshold
    ]));

    const csv = rows.map(r => r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'inventory_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
});

/*  REORDER TAB*/
let reorderLoaded = false;

document.getElementById('tab-reorder-btn')?.addEventListener('click', () => {
    if (!reorderLoaded) loadReorder();
});

async function loadReorder() {
    try {
        const res = await fetch(`${API}?action=reorder-list`);
        const json = await res.json();

        document.getElementById('reorderLoading').style.display = 'none';

        if (!json.items?.length) {
            document.getElementById('reorderEmpty').style.display = '';
            reorderLoaded = true;
            return;
        }

        const list = document.getElementById('reorderList');
        list.style.display = '';
        list.innerHTML = `<table class="data-table">
                <thead><tr>
                    <th>#</th><th>Category</th><th>Description</th><th>Code</th>
                    <th>Stock</th><th>Threshold</th><th>Unit</th><th>Action</th>
                </tr></thead>
                <tbody>
                ${json.items.map((p, idx) => `<tr>
                    <td>${idx + 1}</td>
                    <td>${p.category_name || '—'}</td>
                    <td>${p.description}</td>
                    <td>${p.code || '—'}</td>
                    <td style="color:#fbbf24;">${p.current_stock}</td>
                    <td>${p.reorder_threshold}</td>
                    <td>${p.unit}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-success"
                            onclick="openRestock(${p.product_id}, '${escHtml(p.description)}')">
                            <i class="bi bi-box-arrow-in-down"></i> Restock
                        </button>
                    </td>
                </tr>`).join('')}
                </tbody>
            </table>`;

        reorderLoaded = true;
    } catch (e) {
        document.getElementById('reorderLoading').innerHTML =
            `<p style="color:#fca5a5;padding:20px;">Failed to load reorder list. (${e.message})</p>`;
    }
}

loadCategories();
loadProducts();

/* ══════════════════════════════════════
   CATEGORY MANAGEMENT
══════════════════════════════════════ */
let categoriesTabLoaded = false;

document.getElementById('tab-categories-btn')?.addEventListener('click', () => {
    if (!categoriesTabLoaded && IS_OWNER) loadCategoriesManagement();
});

async function loadCategoriesManagement() {
    const tbody = document.getElementById('categoriesBody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="3" style="text-align:center;padding:30px;">
        <div class="spinner-border" style="color:#e8175d;"></div>
    </td></tr>`;

    try {
        const res = await fetch('backend/categories.php?action=fetch');
        const json = await res.json();

        if (!json.success) {
            tbody.innerHTML = `<tr><td colspan="3" style="text-align:center;color:#fca5a5;">
                ⚠ ${json.message || 'Failed to load categories.'}</td></tr>`;
            return;
        }

        const categories = json.data || [];
        if (!categories.length) {
            tbody.innerHTML = `<tr><td colspan="3" style="text-align:center;color:#7a8499;">
                No categories found. Add one to get started.</td></tr>`;
            return;
        }

        tbody.innerHTML = categories.map((c, idx) => `
            <tr>
                <td><span class="badge-gray">${idx + 1}</span></td>
                <td style="font-weight:600;color:#e2e8f0;">${c.category_name}</td>
                <td>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-warning"
                            onclick="openEditCategory(${c.category_id}, '${escHtml(c.category_name)}')"
                            title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger"
                            onclick="deleteCategory(${c.category_id}, '${escHtml(c.category_name)}')"
                            title="Delete"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            </tr>
        `).join('');

        categoriesTabLoaded = true;
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="3" style="text-align:center;color:#fca5a5;">
            ⚠ Network error. (${err.message})</td></tr>`;
    }
}

document.getElementById('submitAddCategory')?.addEventListener('click', async () => {
    const name = document.getElementById('addCategoryName')?.value.trim();
    if (!name) {
        Swal.fire({ icon: 'warning', title: 'Required', text: 'Category name is required.' });
        return;
    }

    const fd = new FormData();
    fd.append('action', 'add');
    fd.append('category_name', name);

    try {
        const res = await fetch('backend/categories.php', { method: 'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('addCategoryModal'))?.hide();
            document.getElementById('addCategoryName').value = '';
            Swal.fire({ icon: 'success', title: 'Added!', timer: 1200, showConfirmButton: false });
            categoriesTabLoaded = false;
            loadCategoriesManagement();
            loadCategories(); // Refresh category dropdowns in product forms
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: json.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
    }
});

function openEditCategory(id, name) {
    document.getElementById('editCategoryId').value = id;
    document.getElementById('editCategoryName').value = name;
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}

document.getElementById('submitEditCategory')?.addEventListener('click', async () => {
    const id = document.getElementById('editCategoryId')?.value;
    const name = document.getElementById('editCategoryName')?.value.trim();

    if (!id || !name) {
        Swal.fire({ icon: 'warning', title: 'Required', text: 'Category name is required.' });
        return;
    }

    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('category_id', id);
    fd.append('category_name', name);

    try {
        const res = await fetch('backend/categories.php', { method: 'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('editCategoryModal'))?.hide();
            Swal.fire({ icon: 'success', title: 'Updated!', timer: 1200, showConfirmButton: false });
            categoriesTabLoaded = false;
            loadCategoriesManagement();
            loadCategories(); // Refresh category dropdowns
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: json.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
    }
});

async function deleteCategory(id, name) {
    const result = await Swal.fire({
        title: 'Delete this category?',
        text: `"${name}" will be permanently removed.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
    });
    if (!result.isConfirmed) return;

    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('category_id', id);

    try {
        const res = await fetch('backend/categories.php', { method: 'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1200, showConfirmButton: false });
            categoriesTabLoaded = false;
            loadCategoriesManagement();
            loadCategories(); // Refresh category dropdowns
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: json.message });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
    }
}