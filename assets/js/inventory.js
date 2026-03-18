// assets/js/inventory.js — Inventory Page Logic
// Handles: load products, filter, add/edit/delete, restock, forecast, reorder

'use strict';

// ── State ──────────────────────────────────────────────
let allProducts  = [];
let categories   = [];
let seasonChart  = null;

// ── Helpers ────────────────────────────────────────────
function money(n) {
    return '₱' + parseFloat(n || 0).toFixed(2);
}

function esc(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

// ── Load Categories ────────────────────────────────────
async function loadCategories() {
    const res  = await fetch('backend/products.php?action=categories');
    const data = await res.json();
    if (!data.success) return;

    categories = data.data;

    ['addCategory', 'editCategory', 'categoryFilter'].forEach(id => {
        const sel = document.getElementById(id);
        if (!sel) return;

        const isFilter = id === 'categoryFilter';
        const current  = sel.value;

        sel.innerHTML = isFilter
            ? '<option value="">All Categories</option>'
            : '<option value="">Select Category</option>';

        categories.forEach(cat => {
            const opt = document.createElement('option');
            opt.value       = cat.category_id;
            opt.textContent = cat.category_name;
            sel.appendChild(opt);
        });

        if (current) sel.value = current;
    });
}

// ── Load & Render Products ─────────────────────────────
async function loadProducts() {
    const spinner = document.getElementById('loadingSpinner');
    const body    = document.getElementById('stockTableBody');

    if (spinner) spinner.style.display = 'block';

    const res  = await fetch('backend/products.php?action=fetch');
    const data = await res.json();

    if (spinner) spinner.style.display = 'none';

    if (!data.success) {
        body.innerHTML = `<tr><td colspan="9" style="text-align:center;color:#fca5a5;padding:24px;">Error: ${data.message}</td></tr>`;
        return;
    }

    allProducts = data.data || [];
    applyFilters();
    updateSummary();
}

function renderProducts(products) {
    const body = document.getElementById('stockTableBody');
    if (!body) return;

    if (!products.length) {
        body.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:24px;color:#7a8499;">No products found.</td></tr>';
        return;
    }

    body.innerHTML = products.map(p => {
        const stock    = p.current_stock != null ? parseInt(p.current_stock) : parseInt(p.initial_quantity);
        const thresh   = parseInt(p.reorder_threshold) || 5;
        const isLow    = stock <= thresh && stock > 0;
        const isZero   = stock <= 0;
        const margin   = parseFloat(p.margin || 0);
        const rowClass = isZero ? 'row-zero' : (isLow ? 'row-low' : '');

        const stockBadge = isZero
            ? `<span class="badge-red">${stock}</span>`
            : isLow
                ? `<span class="badge-yellow">${stock}</span>`
                : `<span class="badge-green">${stock}</span>`;

        // Action buttons
        let actions = `
            <button class="btn btn-sm btn-outline-success" onclick="openRestock(${p.product_id}, '${esc(p.description)}')" title="Restock">
                <i class="bi bi-box-arrow-in-down"></i>
            </button>`;

        if (IS_OWNER) {
            actions += `
            <button class="btn btn-sm" style="background:#3b82f6;color:#fff;margin-left:4px;"
                onclick="openEdit(${p.product_id})" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm" style="background:#ef4444;color:#fff;margin-left:4px;"
                onclick="deleteProduct(${p.product_id}, '${esc(p.description)}')" title="Delete">
                <i class="bi bi-trash"></i>
            </button>`;
        }

        return `
        <tr class="${rowClass}">
            <td>${p.product_id}</td>
            <td>${esc(p.category_name)}</td>
            <td>
                <div style="font-weight:600;">${esc(p.description)}</div>
                <code style="font-size:.72rem;color:#a5b4fc;">${esc(p.code || '—')}</code>
            </td>
            <td>${esc(p.unit)}</td>
            <td>${money(p.unit_cost)}</td>
            <td>${money(p.selling_price)}</td>
            <td class="${margin >= 0 ? 'text-profit' : 'text-loss'}">${margin >= 0 ? '+' : ''}${money(margin)}</td>
            <td>${stockBadge}</td>
            <td style="white-space:nowrap;">${actions}</td>
        </tr>`;
    }).join('');
}

// ── Filter Logic ───────────────────────────────────────
function applyFilters() {
    const q    = (document.getElementById('searchInput')?.value || '').toLowerCase().trim();
    const catId = parseInt(document.getElementById('categoryFilter')?.value) || null;

    const filtered = allProducts.filter(p => {
        if (catId && parseInt(p.category_id) !== catId) return false;
        if (!q) return true;
        return (p.description + ' ' + p.code + ' ' + p.category_name).toLowerCase().includes(q);
    });

    renderProducts(filtered);
}

function updateSummary() {
    const total  = allProducts.length;
    const lowCnt = allProducts.filter(p => {
        const s = p.current_stock != null ? parseInt(p.current_stock) : parseInt(p.initial_quantity);
        return s <= (parseInt(p.reorder_threshold) || 5);
    }).length;

    const el = document.getElementById('stockSummary');
    if (el) {
        el.innerHTML = `<span class="badge-blue">${total} Products</span>
            ${lowCnt > 0 ? `<span class="badge-yellow">${lowCnt} Low</span>` : ''}`;
    }
}

// ── Margin Auto-Calculate ──────────────────────────────
function calcMargin(costId, priceId, displayId) {
    const cost  = parseFloat(document.getElementById(costId)?.value)  || 0;
    const price = parseFloat(document.getElementById(priceId)?.value) || 0;
    const m     = price - cost;
    const el    = document.getElementById(displayId);
    if (el) {
        el.textContent = (m >= 0 ? '+' : '') + money(m);
        el.style.color = m >= 0 ? '#34d399' : '#fca5a5';
    }
}

// ── ADD PRODUCT ────────────────────────────────────────
async function addProduct() {
    const catId = document.getElementById('addCategory').value;
    const desc  = document.getElementById('addDesc').value.trim();
    const unit  = document.getElementById('addUnit').value;

    if (!catId || !desc || !unit) {
        Swal.fire({ icon: 'warning', title: 'Required Fields', text: 'Category, description, and unit are required.' });
        return;
    }

    const fd = new FormData();
    fd.append('action',             'add');
    fd.append('category_id',        catId);
    fd.append('description',        desc);
    fd.append('unit',               unit);
    fd.append('code',               document.getElementById('addCode').value.trim());
    fd.append('unit_cost',          document.getElementById('addCost').value);
    fd.append('selling_price',      document.getElementById('addPrice').value);
    fd.append('initial_quantity',   document.getElementById('addQty').value);
    fd.append('reorder_threshold',  document.getElementById('addThresh').value);

    const res  = await fetch('backend/products.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        Swal.fire({ icon: 'success', title: 'Saved!', timer: 1400, showConfirmButton: false });
        bootstrap.Modal.getInstance(document.getElementById('addModal'))?.hide();
        loadProducts();
    } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
}

// ── EDIT PRODUCT ───────────────────────────────────────
async function openEdit(id) {
    const res  = await fetch(`backend/products.php?action=get&id=${id}`);
    const data = await res.json();
    if (!data.success) { Swal.fire({ icon: 'error', title: 'Error', text: data.message }); return; }

    const p = data.data;
    document.getElementById('editId').value       = p.product_id;
    document.getElementById('editCategory').value  = p.category_id;
    document.getElementById('editCode').value      = p.code;
    document.getElementById('editDesc').value      = p.description;
    document.getElementById('editUnit').value      = p.unit;
    document.getElementById('editCost').value      = p.unit_cost;
    document.getElementById('editPrice').value     = p.selling_price;
    document.getElementById('editQty').value       = p.initial_quantity;
    document.getElementById('editThresh').value    = p.reorder_threshold;
    calcMargin('editCost', 'editPrice', 'editMargin');

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

async function updateProduct() {
    const catId = document.getElementById('editCategory').value;
    const desc  = document.getElementById('editDesc').value.trim();
    const unit  = document.getElementById('editUnit').value;

    if (!catId || !desc || !unit) {
        Swal.fire({ icon: 'warning', title: 'Required Fields', text: 'Category, description, and unit are required.' });
        return;
    }

    const fd = new FormData();
    fd.append('action',             'update');
    fd.append('product_id',         document.getElementById('editId').value);
    fd.append('category_id',        catId);
    fd.append('description',        desc);
    fd.append('unit',               unit);
    fd.append('code',               document.getElementById('editCode').value.trim());
    fd.append('unit_cost',          document.getElementById('editCost').value);
    fd.append('selling_price',      document.getElementById('editPrice').value);
    fd.append('initial_quantity',   document.getElementById('editQty').value);
    fd.append('reorder_threshold',  document.getElementById('editThresh').value);

    const res  = await fetch('backend/products.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        Swal.fire({ icon: 'success', title: 'Updated!', timer: 1400, showConfirmButton: false });
        bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide();
        loadProducts();
    } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
}

// ── DELETE PRODUCT ─────────────────────────────────────
async function deleteProduct(id, name) {
    const res = await Swal.fire({
        title: 'Delete product?',
        text: `"${name}" will be permanently removed.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete!',
    });
    if (!res.isConfirmed) return;

    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);

    const resp = await fetch('backend/products.php', { method: 'POST', body: fd });
    const data = await resp.json();

    if (data.success) {
        Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1200, showConfirmButton: false });
        loadProducts();
    } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
}

// ── RESTOCK ────────────────────────────────────────────
function openRestock(id, name) {
    document.getElementById('restockId').value        = id;
    document.getElementById('restockName').textContent = name;
    document.getElementById('restockQty').value       = 20;
    document.getElementById('restockRemarks').value   = '';
    new bootstrap.Modal(document.getElementById('restockModal')).show();
}

async function submitRestock() {
    const id  = document.getElementById('restockId').value;
    const qty = parseInt(document.getElementById('restockQty').value);

    if (!id || qty < 1) {
        Swal.fire({ icon: 'warning', title: 'Invalid', text: 'Enter a valid quantity.' });
        return;
    }

    const fd = new FormData();
    fd.append('action',     'restock');
    fd.append('product_id', id);
    fd.append('quantity',   qty);
    fd.append('remarks',    document.getElementById('restockRemarks').value || 'Restock');

    const res  = await fetch('backend/products.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        bootstrap.Modal.getInstance(document.getElementById('restockModal'))?.hide();
        Swal.fire({ icon: 'success', title: 'Restocked!', timer: 1400, showConfirmButton: false });
        loadProducts();
    } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
}

// ── EXPORT CSV ─────────────────────────────────────────
function exportCSV() {
    const rows = [['ID', 'Category', 'Description', 'Code', 'Unit', 'Cost', 'Price', 'Margin', 'Stock', 'Reorder At']];

    allProducts.forEach(p => {
        const stock = p.current_stock != null ? p.current_stock : p.initial_quantity;
        rows.push([p.product_id, p.category_name, p.description, p.code, p.unit,
                   p.unit_cost, p.selling_price, p.margin, stock, p.reorder_threshold]);
    });

    const csv  = rows.map(r => r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `inventory_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// ── FORECAST ───────────────────────────────────────────
async function loadForecast() {
    const loading = document.getElementById('forecastLoading');
    const content = document.getElementById('forecastContent');

    loading.style.display = 'block';
    content.style.display = 'none';

    const res  = await fetch('backend/forecast.php');
    const data = await res.json();

    loading.style.display = 'none';

    if (!data.success || !data.items?.length) {
        loading.style.display = 'block';
        loading.innerHTML = '<p style="color:#7a8499;padding:20px;">No sales data yet. Record some sales to see forecasts.</p>';
        return;
    }

    content.style.display = 'block';

    // Weekly forecast table
    const weeklyBody = document.getElementById('weeklyBody');
    weeklyBody.innerHTML = data.items.map(item => {
        const avgWeekly  = parseFloat(item.total_qty) / 12;
        const next4Weeks = Math.ceil(avgWeekly * 4);
        const trend = avgWeekly >= 2
            ? '<span style="color:#34d399;">↑ High</span>'
            : '<span style="color:#fcd34d;">→ Moderate</span>';

        return `<tr>
            <td><strong>${esc(item.description)}</strong></td>
            <td><code style="color:#a5b4fc;">${esc(item.code || '—')}</code></td>
            <td>${avgWeekly.toFixed(1)} units/wk &nbsp;${trend}</td>
            <td><strong style="color:#f97316;">${next4Weeks} units</strong></td>
            <td><span class="badge-gray">${item.sale_weeks} wks</span></td>
        </tr>`;
    }).join('');

    // Monthly forecast table
    const monthlyBody = document.getElementById('monthlyBody');
    monthlyBody.innerHTML = data.items.map(item => {
        const avgMonthly  = parseFloat(item.total_qty) / 12;
        const next3Months = Math.ceil(avgMonthly * 3);
        const months      = parseInt(item.sale_months) || 0;
        const conf = months >= 6
            ? '<span style="color:#34d399;font-size:.72rem;">High confidence</span>'
            : months >= 3
                ? '<span style="color:#fcd34d;font-size:.72rem;">Moderate</span>'
                : '<span style="color:#7a8499;font-size:.72rem;">Low data</span>';

        return `<tr>
            <td><strong>${esc(item.description)}</strong></td>
            <td><code style="color:#a5b4fc;">${esc(item.code || '—')}</code></td>
            <td>${avgMonthly.toFixed(1)} units/mo</td>
            <td><strong style="color:#60a5fa;">${next3Months} units</strong> &nbsp;${conf}</td>
            <td><span class="badge-gray">${months} months</span></td>
        </tr>`;
    }).join('');

    // Seasonal chart
    renderSeasonalChart(data.monthly || []);
}

function renderSeasonalChart(monthly) {
    const canvas = document.getElementById('seasonalChart');
    if (!canvas) return;

    if (seasonChart) { seasonChart.destroy(); seasonChart = null; }

    if (!monthly.length) {
        canvas.parentElement.innerHTML += '<p style="text-align:center;color:#7a8499;font-size:.82rem;">No monthly data yet.</p>';
        return;
    }

    seasonChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: monthly.map(m => m.month_label),
            datasets: [
                {
                    label: 'Parts ₱',
                    data: monthly.map(m => parseFloat(m.parts_total) || 0),
                    backgroundColor: 'rgba(232,23,93,.7)',
                    borderRadius: 6,
                },
                {
                    label: 'Labor ₱',
                    data: monthly.map(m => parseFloat(m.labor_total) || 0),
                    backgroundColor: 'rgba(16,185,129,.7)',
                    borderRadius: 6,
                },
                {
                    label: 'Total ₱',
                    data: monthly.map(m => parseFloat(m.grand_total) || 0),
                    type: 'line',
                    borderColor: '#f97316',
                    borderWidth: 2,
                    pointRadius: 3,
                    fill: false,
                    tension: 0.4,
                },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { labels: { color: '#7a8499' } } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: '#7a8499' } },
                y: {
                    grid: { color: 'rgba(255,255,255,.04)' },
                    ticks: { color: '#7a8499', callback: v => '₱' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v) }
                },
            },
        },
    });
}

// ── REORDER TAB ────────────────────────────────────────
async function loadReorder() {
    const loading = document.getElementById('reorderLoading');
    const list    = document.getElementById('reorderList');
    const empty   = document.getElementById('reorderEmpty');

    loading.style.display = 'block';
    list.style.display    = 'none';
    empty.style.display   = 'none';

    const res  = await fetch('backend/products.php?action=reorder-list');
    const data = await res.json();

    loading.style.display = 'none';

    if (!data.success || !data.items?.length) {
        empty.style.display = 'block';
        return;
    }

    list.style.display = 'block';
    list.innerHTML = data.items.map(item => {
        const stock     = parseInt(item.current_stock) || 0;
        const thresh    = parseInt(item.reorder_threshold) || 5;
        const suggested = Math.max(thresh * 2, 10);
        const urgency   = stock <= 0
            ? '<span class="badge-red">Out of Stock</span>'
            : stock <= Math.floor(thresh / 2)
                ? '<span class="badge-red">Critical</span>'
                : '<span class="badge-yellow">Low</span>';

        return `<div class="reorder-row">
            <div>
                <strong style="color:#fff;font-size:.88rem;">${esc(item.description)}</strong>
                <div style="font-size:.75rem;color:#7a8499;">${esc(item.category_name)}${item.code ? ' · ' + esc(item.code) : ''}</div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                ${urgency}
                <span style="font-size:.82rem;color:#7a8499;">Stock: <strong style="color:#fca5a5;">${stock}</strong></span>
                <span style="font-size:.82rem;color:#7a8499;">Min: <strong style="color:#fcd34d;">${thresh}</strong></span>
                <button onclick="openRestock(${item.product_id}, '${esc(item.description).replace(/'/g, "\\'")}')"
                    style="background:linear-gradient(135deg,#f59e0b,#d97706);border:none;color:#111;padding:6px 14px;border-radius:20px;font-size:.8rem;font-weight:700;cursor:pointer;">
                    <i class="bi bi-box-arrow-in-down me-1"></i>Restock
                </button>
            </div>
        </div>`;
    }).join('');
}

// ── INIT ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    loadCategories();
    loadProducts();

    // Search & filter
    document.getElementById('searchInput')?.addEventListener('input', debounce(applyFilters, 200));
    document.getElementById('categoryFilter')?.addEventListener('change', applyFilters);

    // Export
    document.getElementById('exportBtn')?.addEventListener('click', exportCSV);

    // Margin auto-calc
    ['addCost', 'addPrice'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => calcMargin('addCost', 'addPrice', 'addMargin'));
    });
    ['editCost', 'editPrice'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => calcMargin('editCost', 'editPrice', 'editMargin'));
    });

    // Modal submit buttons
    document.getElementById('submitAdd')?.addEventListener('click', addProduct);
    document.getElementById('submitEdit')?.addEventListener('click', updateProduct);
    document.getElementById('submitRestock')?.addEventListener('click', submitRestock);

    // Tab events — lazy load forecast & reorder
    document.getElementById('tab-forecast-btn')?.addEventListener('click', loadForecast);
    document.getElementById('tab-reorder-btn')?.addEventListener('click', loadReorder);

    // Reset add modal on close
    document.getElementById('addModal')?.addEventListener('hidden.bs.modal', function () {
        ['addCategory', 'addCode', 'addDesc', 'addUnit', 'addCost', 'addPrice', 'addQty', 'addThresh'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = el.tagName === 'SELECT' ? '' : (id.includes('Thresh') ? '5' : '0');
        });
        const m = document.getElementById('addMargin');
        if (m) { m.textContent = '₱0.00'; m.style.color = '#34d399'; }
    });
});
