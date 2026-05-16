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

    tbody.innerHTML = products.map(p => `
            <tr data-id="${p.product_id}">
                <td><span class="badge-gray">${p.product_id}</span></td>
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

/*EXPORT CSV */
exportBtn?.addEventListener('click', () => {
    const visible = allProducts.filter(p => {
        const q = (searchInput?.value || '').toLowerCase();
        const cat = categoryFilter?.value || '';
        return (!q || p.description.toLowerCase().includes(q) || (p.code || '').toLowerCase().includes(q))
            && (!cat || p.category_name === cat);
    });

    const rows = [['ID', 'Category', 'Description', 'Code', 'Unit', 'Cost', 'Price', 'Margin', 'Stock', 'Threshold']];
    visible.forEach(p => rows.push([
        p.product_id, p.category_name, p.description, p.code, p.unit,
        p.unit_cost, p.selling_price, p.margin, p.current_stock, p.reorder_threshold
    ]));

    const csv = rows.map(r => r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
    a.download = 'inventory_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
});

/*  FORECASTING TAB */
let forecastLoaded = false;

document.getElementById('tab-forecast-btn')?.addEventListener('click', () => {
    if (!forecastLoaded) loadForecast();
});

async function loadForecast() {
    try {
        const res = await fetch(FCAST);
        const json = await res.json();

        document.getElementById('forecastLoading').style.display = 'none';
        document.getElementById('forecastContent').style.display = '';

        const items = json.items || [];
        const monthly = json.monthly || [];

        // Weekly table
        const wBody = document.getElementById('weeklyBody');
        if (wBody) {
            wBody.innerHTML = items.length ? items.map(i => {
                const avgWeekly = i.sale_weeks > 0 ? (i.total_qty / i.sale_weeks).toFixed(1) : 0;
                const next4 = (parseFloat(avgWeekly) * 4).toFixed(0);
                return `<tr>
                        <td>${i.description}</td>
                        <td>${i.code || '—'}</td>
                        <td>${avgWeekly}</td>
                        <td><strong>${next4}</strong></td>
                        <td>${i.sale_weeks} wks</td>
                    </tr>`;
            }).join('') : `<tr><td colspan="5" style="text-align:center;color:#7a8499;padding:20px;">No sales data yet.</td></tr>`;
        }

        // Monthly table
        const mBody = document.getElementById('monthlyBody');
        if (mBody) {
            mBody.innerHTML = items.length ? items.map(i => {
                const avgMonthly = i.sale_months > 0 ? (i.total_qty / i.sale_months).toFixed(1) : 0;
                const next3 = (parseFloat(avgMonthly) * 3).toFixed(0);
                return `<tr>
                        <td>${i.description}</td>
                        <td>${i.code || '—'}</td>
                        <td>${avgMonthly}</td>
                        <td><strong>${next3}</strong></td>
                        <td>${i.sale_months} mos</td>
                    </tr>`;
            }).join('') : `<tr><td colspan="5" style="text-align:center;color:#7a8499;padding:20px;">No sales data yet.</td></tr>`;
        }

        // Seasonal chart
        if (monthly.length && document.getElementById('seasonalChart')) {
            const ctx = document.getElementById('seasonalChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthly.map(m => m.month_label),
                    datasets: [
                        { label: 'Parts ₱', data: monthly.map(m => m.parts_total), backgroundColor: 'rgba(59,130,246,.7)' },
                        { label: 'Labor ₱', data: monthly.map(m => m.labor_total), backgroundColor: 'rgba(52,211,153,.7)' },
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
        }

        forecastLoaded = true;
    } catch (e) {
        document.getElementById('forecastLoading').innerHTML =
            `<p style="color:#fca5a5;">Failed to load forecast data. (${e.message})</p>`;
    }
}

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
                    <th>ID</th><th>Category</th><th>Description</th><th>Code</th>
                    <th>Stock</th><th>Threshold</th><th>Unit</th><th>Action</th>
                </tr></thead>
                <tbody>
                ${json.items.map(p => `<tr>
                    <td>${p.product_id}</td>
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
   STOCK LEDGER TAB
══════════════════════════════════════ */
let ledgerData = null;

document.getElementById('tab-ledger-btn')?.addEventListener('click', () => {
    // Auto-load when tab is first opened
    if (!ledgerData) loadLedger();
});

async function loadLedger() {
    const month = document.getElementById('ledgerMonth')?.value;
    const year = document.getElementById('ledgerYear')?.value;

    // Show loading, hide others
    document.getElementById('ledgerLoading').style.display = '';
    document.getElementById('ledgerEmpty').style.display = 'none';
    document.getElementById('ledgerTableWrap').style.display = 'none';
    document.getElementById('ledgerHeading').style.display = 'none';
    document.getElementById('ledgerExportBtn').style.display = 'none';

    try {
        const res = await fetch(`backend/products.php?action=stock-ledger&month=${month}&year=${year}`);
        const json = await res.json();
        document.getElementById('ledgerLoading').style.display = 'none';

        if (!json.success) {
            Swal.fire({ icon: 'error', title: 'Error', text: json.message }); return;
        }

        ledgerData = json;

        if (!json.ledger || json.ledger.length === 0) {
            document.getElementById('ledgerEmpty').style.display = ''; return;
        }

        renderLedger(json);

    } catch (err) {
        document.getElementById('ledgerLoading').style.display = 'none';
        Swal.fire({ icon: 'error', title: 'Network Error', text: err.message });
    }
}

function renderLedger(json) {
    // Heading
    document.getElementById('ledgerTitle').textContent = 'Stock Ledger — ' + json.month_name;
    document.getElementById('ledgerHeading').style.display = '';

    const tbody = document.getElementById('ledgerBody');
    tbody.innerHTML = '';

    let totalBegin = 0, totalAdded = 0, totalUsed = 0, totalEnd = 0;

    json.ledger.forEach(item => {
        totalBegin += item.begin_stock;
        totalAdded += item.added;
        totalUsed += item.used;
        totalEnd += item.end_stock;

        // Build transaction detail tooltip
        const txnHtml = item.transactions.length > 0
            ? item.transactions.map(t => {
                const sign = t.quantity_change > 0 ? '+' : '';
                const color = t.quantity_change > 0 ? '#4ade80' : '#f87171';
                const icon = t.quantity_change > 0 ? '▲' : '▼';
                const tLabel = t.transaction_type === 'sale' ? 'Sale'
                    : t.transaction_type === 'restock' ? 'Restock'
                        : t.transaction_type === 'initial' ? 'Initial'
                            : t.transaction_type === 'adjustment' ? 'Adjustment'
                                : t.transaction_type;
                return `<div style="display:flex;gap:8px;align-items:center;padding:4px 0;
                    border-bottom:1px solid rgba(255,255,255,.04);font-size:.75rem;">
                    <span style="color:#4b5a6e;white-space:nowrap;min-width:78px;">${t.transaction_date}</span>
                    <span style="background:rgba(255,255,255,.05);border-radius:4px;padding:1px 6px;
                        font-size:.68rem;color:#94a3b8;">${tLabel}</span>
                    <span style="color:${color};font-weight:700;white-space:nowrap;">${icon} ${sign}${t.quantity_change}</span>
                    ${t.remarks ? `<span style="color:#4b5a6e;font-size:.7rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px;" title="${t.remarks}">${t.remarks}</span>` : ''}
                </div>`;
            }).join('')
            : `<div style="font-size:.76rem;color:#2e3a4e;padding:6px 0;font-style:italic;">No transactions this month</div>`;

        // Ending stock color
        const endColor = item.end_stock <= 0 ? '#f87171'
            : item.end_stock <= 5 ? '#fbbf24'
                : '#4ade80';

        // Formula string
        const formula = `${item.begin_stock} + ${item.added} − ${item.used} = ${item.end_stock}`;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="color:#94a3b8;font-size:.78rem;">${item.category || '—'}</td>
            <td>
                <div style="font-weight:600;color:#e2e8f0;">${item.description}</div>
                <div style="font-size:.7rem;color:#4b5a6e;margin-top:2px;font-family:monospace;">${formula}</div>
            </td>
            <td style="color:#64748b;font-size:.78rem;">${item.unit}</td>
            <td style="text-align:center;">
                <span style="font-family:'Space Grotesk',sans-serif;font-size:1rem;font-weight:700;color:#60a5fa;">
                    ${item.begin_stock}
                </span>
            </td>
            <td style="text-align:center;">
                <span style="font-family:'Space Grotesk',sans-serif;font-size:1rem;font-weight:700;color:#4ade80;">
                    ${item.added > 0 ? '+' + item.added : '—'}
                </span>
            </td>
            <td style="text-align:center;">
                <span style="font-family:'Space Grotesk',sans-serif;font-size:1rem;font-weight:700;color:#f87171;">
                    ${item.used > 0 ? '−' + item.used : '—'}
                </span>
            </td>
            <td style="text-align:center;">
                <span style="font-family:'Space Grotesk',sans-serif;font-size:1.1rem;font-weight:800;color:${endColor};">
                    ${item.end_stock}
                </span>
                ${item.end_stock <= 0
                ? `<div style="font-size:.65rem;color:#f87171;">OUT OF STOCK</div>`
                : item.end_stock <= 5
                    ? `<div style="font-size:.65rem;color:#fbbf24;">LOW STOCK</div>`
                    : ''}
            </td>
            <td>
                <button class="btn btn-sm btn-outline-secondary"
                    onclick="toggleLedgerDetail(this)"
                    style="font-size:.72rem;padding:3px 8px;"
                    data-txn='${JSON.stringify(item.transactions).replace(/'/g, "&#39;")}'>
                    <i class="bi bi-list-ul me-1"></i>${item.transactions.length} txn${item.transactions.length !== 1 ? 's' : ''}
                </button>
                <div class="ledger-detail-panel" style="display:none;margin-top:8px;
                    background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);
                    border-radius:8px;padding:10px 12px;min-width:340px;">
                    ${txnHtml}
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Totals row
    const tf = document.createElement('tr');
    tf.style.cssText = 'background:rgba(74,222,128,.04);border-top:2px solid rgba(74,222,128,.2);';
    tf.innerHTML = `
        <td colspan="3" style="font-family:'Space Grotesk',sans-serif;font-weight:700;color:#4ade80;font-size:.8rem;letter-spacing:.4px;text-transform:uppercase;">
            Monthly Totals
        </td>
        <td style="text-align:center;font-family:'Space Grotesk',sans-serif;font-weight:800;font-size:1rem;color:#60a5fa;">${totalBegin}</td>
        <td style="text-align:center;font-family:'Space Grotesk',sans-serif;font-weight:800;font-size:1rem;color:#4ade80;">+${totalAdded}</td>
        <td style="text-align:center;font-family:'Space Grotesk',sans-serif;font-weight:800;font-size:1rem;color:#f87171;">−${totalUsed}</td>
        <td style="text-align:center;font-family:'Space Grotesk',sans-serif;font-weight:800;font-size:1.1rem;color:#fff;">${totalEnd}</td>
        <td></td>
    `;
    tbody.appendChild(tf);

    // Summary bar
    document.getElementById('ledgerTotals').innerHTML = `
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#4b5a6e;">
            ${json.month_name} Summary
        </div>
        <div>
            <div style="font-size:.68rem;color:#4b5a6e;">Total Beginning Stock</div>
            <div style="font-size:1.1rem;font-family:'Space Grotesk',sans-serif;font-weight:700;color:#60a5fa;">${totalBegin} units</div>
        </div>
        <div style="color:#4b5a6e;font-size:1.2rem;">+</div>
        <div>
            <div style="font-size:.68rem;color:#4b5a6e;">Total Restocked (Bought)</div>
            <div style="font-size:1.1rem;font-family:'Space Grotesk',sans-serif;font-weight:700;color:#4ade80;">+${totalAdded} units</div>
        </div>
        <div style="color:#4b5a6e;font-size:1.2rem;">−</div>
        <div>
            <div style="font-size:.68rem;color:#4b5a6e;">Total Used for Repairs</div>
            <div style="font-size:1.1rem;font-family:'Space Grotesk',sans-serif;font-weight:700;color:#f87171;">−${totalUsed} units</div>
        </div>
        <div style="color:#4b5a6e;font-size:1.2rem;">=</div>
        <div>
            <div style="font-size:.68rem;color:#4b5a6e;">Ending Stock (→ June Beginning)</div>
            <div style="font-size:1.3rem;font-family:'Space Grotesk',sans-serif;font-weight:800;color:#fff;">${totalEnd} units</div>
        </div>
    `;

    document.getElementById('ledgerTableWrap').style.display = '';
    document.getElementById('ledgerExportBtn').style.display = '';
}

function toggleLedgerDetail(btn) {
    const panel = btn.nextElementSibling;
    const isOpen = panel.style.display !== 'none';
    panel.style.display = isOpen ? 'none' : '';
    btn.innerHTML = isOpen
        ? `<i class="bi bi-list-ul me-1"></i>${btn.textContent.trim()}`
        : `<i class="bi bi-chevron-up me-1"></i>Hide`;
}

function exportLedgerCSV() {
    if (!ledgerData || !ledgerData.ledger) return;
    const rows = [
        ['Stock Ledger — ' + ledgerData.month_name],
        [],
        ['Category', 'Product', 'Unit', 'Beginning Stock', 'Bought / Restocked', 'Used for Repairs', 'Ending Stock', 'Formula'],
    ];
    ledgerData.ledger.forEach(item => {
        rows.push([
            item.category,
            item.description,
            item.unit,
            item.begin_stock,
            item.added,
            item.used,
            item.end_stock,
            `${item.begin_stock} + ${item.added} - ${item.used} = ${item.end_stock}`,
        ]);
    });
    const totals = ledgerData.ledger.reduce((a, i) => {
        a.b += i.begin_stock; a.a += i.added; a.u += i.used; a.e += i.end_stock; return a;
    }, { b: 0, a: 0, u: 0, e: 0 });
    rows.push([]);
    rows.push(['TOTALS', '', '', totals.b, totals.a, totals.u, totals.e,
        `${totals.b} + ${totals.a} - ${totals.u} = ${totals.e}`]);

    const csv = rows.map(r => r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
    a.download = `stock_ledger_${ledgerData.year}_${String(ledgerData.month).padStart(2, '0')}.csv`;
    a.click();
}
