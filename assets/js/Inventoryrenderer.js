'use strict';

class InventoryRenderer {
    constructor(selectors, isOwner = false) {
        this._sel     = selectors;
        this._isOwner = isOwner;
    }

    renderTable(products) {
        const tbody = document.getElementById('stockTableBody');
        if (!tbody) return;
        if (!products.length) {
            tbody.innerHTML = this._emptyRow(9, 'No products found.');
            return;
        }
        tbody.innerHTML = products.map((p, idx) => this._productRow(p, idx)).join('');
    }

    renderSummary(products) {
        const el = document.getElementById('stockSummary');
        if (!el) return;
        const low = products.filter(p => parseInt(p.current_stock) <= parseInt(p.reorder_threshold) && parseInt(p.current_stock) > 0).length;
        const out = products.filter(p => parseInt(p.current_stock) <= 0).length;
        el.innerHTML =
            `<span class="summary-pill">Total: <strong>${products.length}</strong></span>` +
            (low ? `<span class="summary-pill" style="border-color:#fbbf24;color:#fbbf24;">⚠ Low: <strong>${low}</strong></span>` : '') +
            (out ? `<span class="summary-pill" style="border-color:#f87171;color:#f87171;">✖ Out: <strong>${out}</strong></span>` : '');
    }

    renderReorderTable(items) {
        const loading = document.getElementById('reorderLoading');
        const empty   = document.getElementById('reorderEmpty');
        const list    = document.getElementById('reorderList');

        if (loading) loading.style.display = 'none';

        if (!items || !items.length) {
            if (empty) empty.style.display = '';
            return;
        }

        if (list) {
            list.style.display = '';
            list.innerHTML = `
                <table class="data-table">
                    <thead><tr>
                        <th>#</th><th>Category</th><th>Description</th><th>Code</th>
                        <th>Remaining Stock</th>
                        <th>Threshold</th><th>Unit</th><th>Needed</th><th>Action</th>
                    </tr></thead>
                    <tbody>${items.map((p, i) => this._reorderRow(p, i)).join('')}</tbody>
                </table>`;
        }
    }

    populateCategoryFilter(categories) {
        const sel = document.getElementById('categoryFilter');
        if (!sel) return;
        sel.innerHTML = '<option value="">All Categories</option>' +
            categories.map(c => `<option value="${c.category_name}">${c.category_name}</option>`).join('');
    }

    populateCategorySelects(categories) {
        ['addCategory', 'editCategory'].forEach(id => {
            const sel = document.getElementById(id);
            if (!sel) return;
            sel.innerHTML = '<option value="">Select Category</option>' +
                categories.map(c => `<option value="${c.category_id}">${c.category_name}</option>`).join('');
        });
    }

    showTableLoading() {
        const tbody = document.getElementById('stockTableBody');
        if (tbody) tbody.innerHTML = this._spinnerRow(9);
    }

    showTableError(message) {
        const tbody = document.getElementById('stockTableBody');
        if (tbody) tbody.innerHTML = this._errorRow(9, message);
    }

    _productRow(p, idx) {
        const margin = parseFloat(p.margin || 0);
        const editBtn = this._isOwner
            ? `<button class="btn btn-sm btn-outline-warning" onclick="openEdit(${p.product_id})" title="Edit"><i class="bi bi-pencil"></i></button>
               <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(${p.product_id}, '${this._esc(p.description)}')" title="Delete"><i class="bi bi-trash"></i></button>`
            : '';
        return `<tr data-id="${p.product_id}">
            <td><span class="badge-gray">${idx + 1}</span></td>
            <td>${p.category_name || '—'}</td>
            <td>
                <div style="font-weight:600;">${p.description}</div>
                ${p.code ? `<div style="font-size:.75rem;color:#7a8499;">${p.code}</div>` : ''}
            </td>
            <td>${p.unit}</td>
            <td style="color:#93c5fd;">${this._peso(p.unit_cost)}</td>
            <td style="color:#fff;">${this._peso(p.selling_price)}</td>
            <td style="color:${margin >= 0 ? '#34d399' : '#f87171'};">${this._peso(margin)}</td>
            <td>${this._stockBadge(p.current_stock, p.reorder_threshold)}</td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-info" onclick="openRestock(${p.product_id}, '${this._esc(p.description)}')" title="Restock">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </button>
                    ${editBtn}
                </div>
            </td>
        </tr>`;
    }

    _reorderRow(p, idx) {
        // Physical stock can never be negative -- a negative ledger total means
        // sales outran what was ever logged as received, not real remaining units.
        const rawStock   = parseInt(p.current_stock) || 0;
        const threshold  = parseInt(p.reorder_threshold) || 0;
        const needed     = Math.max(0, threshold - rawStock); // units needed to reach threshold

        return `<tr>
            <td>${idx + 1}</td>
            <td>${p.category_name || '—'}</td>
            <td>${p.description}</td>
            <td>${p.code || '—'}</td>
            <td>${this._stockBadge(rawStock, threshold)}</td>
            <td>${threshold}</td>
            <td>${p.unit}</td>
            <td style="color:#86efac;">${needed > 0 ? '+' + needed : '—'}</td>
            <td>
                <button class="btn btn-sm btn-outline-success" onclick="openRestock(${p.product_id}, '${this._esc(p.description)}')">
                    <i class="bi bi-box-arrow-in-down"></i> Restock
                </button>
            </td>
        </tr>`;
    }

    _stockBadge(qty, threshold) {
        qty       = parseInt(qty) || 0;
        threshold = parseInt(threshold) || 0;
        const remaining = Math.max(0, qty); // physical stock can never be negative

        if (remaining <= 0) {
            return `<div style="text-align:center;line-height:1.3;">
                        <div style="font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:1rem;color:#f87171;">0</div>
                        <div style="font-size:.68rem;font-weight:600;color:#f87171;">Out of Stock</div>
                    </div>`;
        }
        if (remaining <= threshold) {
            return `<div style="text-align:center;line-height:1.3;">
                        <div style="font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:1rem;color:#fbbf24;">${remaining}</div>
                        <div style="font-size:.68rem;font-weight:600;color:#fbbf24;">⚠ Low Stock</div>
                    </div>`;
        }
        return `<div style="text-align:center;">
                    <span class="badge bg-success" style="font-size:.85rem;">${remaining}</span>
                </div>`;
    }

    _peso(n) {
        return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    _esc(str) {
        return String(str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    _spinnerRow(cols) {
        return `<tr><td colspan="${cols}" style="text-align:center;padding:30px;">
            <div class="spinner-border" style="color:#4ade80;"></div></td></tr>`;
    }

    _emptyRow(cols, msg) {
        return `<tr><td colspan="${cols}" style="text-align:center;padding:30px;color:#7a8499;">${msg}</td></tr>`;
    }

    _errorRow(cols, msg) {
        return `<tr><td colspan="${cols}" style="text-align:center;padding:24px;color:#fca5a5;">⚠ ${msg}</td></tr>`;
    }
}