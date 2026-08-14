'use strict';

class InventoryController {
    constructor(api, renderer, ledger) {
        this._api      = api;
        this._renderer = renderer;
        this._ledger   = ledger;

        this._allProducts      = [];
        this._allCategories    = [];
        this._reorderLoaded    = false;
        this._categoriesLoaded = false;
    }

    async init() {
        this._wireFilters();
        this._wireExport();
        this._wireTabs();
        this._wireAddProduct();
        this._wireEditProduct();
        this._wireRestock();
        this._wireCategoryManagement();
        await Promise.all([this.loadCategories(), this.loadProducts()]);
    }

    async loadProducts() {
        this._renderer.showTableLoading();
        try {
            const res = await fetch('backend/products.php?action=fetch');
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const text = await res.text();
            let json;
            try { json = JSON.parse(text); }
            catch(e) { throw new Error('Invalid JSON from products.php: ' + text.substring(0, 100)); }
            if (!json.success) {
                this._renderer.showTableError(json.message || 'Failed to load products.');
                return;
            }
            this._allProducts = json.data || [];
            this._renderer.renderTable(this._allProducts);
            this._renderer.renderSummary(this._allProducts);
        } catch (err) {
            this._renderer.showTableError('Network error: ' + err.message);
        }
    }

    async loadCategories() {
        try {
            const res  = await fetch('backend/products.php?action=categories');
            if (!res.ok) return;
            const text = await res.text();
            let json;
            try { json = JSON.parse(text); } catch(e) { return; }
            if (!json.success) return;
            this._allCategories = json.data || [];
            this._renderer.populateCategoryFilter(this._allCategories);
            this._renderer.populateCategorySelects(this._allCategories);
        } catch (e) {
            console.warn('Could not load categories:', e);
        }
    }

    async refresh() {
        await this.loadProducts();
        this._reorderLoaded    = false;
        this._categoriesLoaded = false;
    }

    applyFilters() {
        const q   = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const cat = document.getElementById('categoryFilter')?.value || '';
        const filtered = this._allProducts.filter(p => {
            const matchQ   = !q   || p.description.toLowerCase().includes(q) || (p.code || '').toLowerCase().includes(q);
            const matchCat = !cat || p.category_name === cat;
            return matchQ && matchCat;
        });
        this._renderer.renderTable(filtered);
    }

    exportCSV() {
        const q   = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const cat = document.getElementById('categoryFilter')?.value || '';
        const visible = this._allProducts.filter(p => {
            const matchQ   = !q   || p.description.toLowerCase().includes(q) || (p.code || '').toLowerCase().includes(q);
            const matchCat = !cat || p.category_name === cat;
            return matchQ && matchCat;
        });

        if (!visible.length) {
            Swal.fire({ icon: 'warning', title: 'Nothing to export', text: 'No products match the current filter.' });
            return;
        }

        const headers = ['#','Category','Description','Code','Unit','Unit Cost','Selling Price','Margin','Current Stock','Reorder Threshold'];
        const rows = [headers, ...visible.map((p, i) => [
            i + 1, p.category_name || '', p.description, p.code || '', p.unit,
            parseFloat(p.unit_cost || 0).toFixed(2),
            parseFloat(p.selling_price || 0).toFixed(2),
            parseFloat(p.margin || 0).toFixed(2),
            p.current_stock, p.reorder_threshold,
        ])];

        const csv  = rows.map(r => r.map(v => '"' + String(v ?? '').replace(/"/g, '""') + '"').join(',')).join('\n');
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url;
        a.download = 'inventory_' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    openRestock(id, name) {
        document.getElementById('restockId').value         = id;
        document.getElementById('restockName').textContent = name;
        document.getElementById('restockQty').value        = 20;
        document.getElementById('restockRemarks').value    = '';
        new bootstrap.Modal(document.getElementById('restockModal')).show();
    }

    async openEdit(id) {
        try {
            const res  = await fetch('backend/products.php?action=get&id=' + id);
            const json = await res.json();
            if (!json.success) { Swal.fire({ icon: 'error', title: 'Error', text: json.message }); return; }
            const p = json.data;
            document.getElementById('editId').value     = p.product_id;
            document.getElementById('editCode').value   = p.code || '';
            document.getElementById('editDesc').value   = p.description || '';
            document.getElementById('editCost').value   = p.unit_cost || 0;
            document.getElementById('editPrice').value  = p.selling_price || 0;
            document.getElementById('editQty').value    = p.initial_quantity || 0;
            document.getElementById('editThresh').value = p.reorder_threshold || 5;
            const brandField = document.getElementById('editBrand');
            if (brandField) brandField.value = p.compatible_brand || '';
            const catSel  = document.getElementById('editCategory');
            if (catSel)  catSel.value  = p.category_id;
            const unitSel = document.getElementById('editUnit');
            const unitOtherField = document.getElementById('editUnitOther');
            if (unitSel) {
                const isStandard = [...unitSel.options].some(o => o.value === p.unit);
                if (isStandard) {
                    unitSel.value = p.unit;
                    if (unitOtherField) unitOtherField.style.display = 'none';
                } else if (p.unit) {
                    unitSel.value = '__other__';
                    if (unitOtherField) {
                        unitOtherField.value = p.unit;
                        unitOtherField.style.display = '';
                    }
                } else {
                    unitSel.value = '';
                    if (unitOtherField) unitOtherField.style.display = 'none';
                }
            }
            this._updateMargin('edit');
            new bootstrap.Modal(document.getElementById('editModal')).show();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Network error', text: e.message });
        }
    }

    async deleteProduct(id, name) {
        const result = await Swal.fire({
            title: 'Delete this product?',
            text: '"' + name + '" will be permanently removed.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Delete',
        });
        if (!result.isConfirmed) return;
        try {
            const fd = new FormData();
            fd.append('id', id);
            const res  = await fetch('backend/products.php?action=delete', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1200, showConfirmButton: false });
                await this.refresh();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.message });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Network error', text: e.message });
        }
    }

    openEditCategory(id, name) {
        document.getElementById('editCategoryId').value   = id;
        document.getElementById('editCategoryName').value = name;
        new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
    }

    async deleteCategory(id, name) {
        const result = await Swal.fire({
            title: 'Delete this category?',
            text: '"' + name + '" will be permanently removed.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Delete',
        });
        if (!result.isConfirmed) return;
        try {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('category_id', id);
            const res  = await fetch('backend/categories.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1200, showConfirmButton: false });
                this._categoriesLoaded = false;
                await Promise.all([this._loadCategoriesTab(), this.loadCategories()]);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.message });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
        }
    }

    _wireFilters() {
        document.getElementById('searchInput')?.addEventListener('input', () => this.applyFilters());
        document.getElementById('categoryFilter')?.addEventListener('change', () => this.applyFilters());
    }

    _wireExport() {
        document.getElementById('exportBtn')?.addEventListener('click', () => this.exportCSV());
    }

    _wireTabs() {
        const reorderBtn    = document.getElementById('tab-reorder-btn');
        const categoriesBtn = document.getElementById('tab-categories-btn');

        const loadReorder = async () => {
            if (this._reorderLoaded) return;
            try {
                const res    = await fetch('backend/products.php?action=reorder-list');
                const json   = await res.json();
                const sorted = (json.items || []).sort((a, b) => parseInt(a.current_stock) - parseInt(b.current_stock));
                this._renderer.renderReorderTable(sorted);
                this._reorderLoaded = true;
            } catch (e) {
                const el = document.getElementById('reorderLoading');
                if (el) el.innerHTML = '<p style="color:#fca5a5;padding:20px;">Failed to load reorder list. (' + e.message + ')</p>';
            }
        };

        const loadCategories = () => {
            if (!this._categoriesLoaded) this._loadCategoriesTab();
        };

        if (reorderBtn) {
            reorderBtn.addEventListener('shown.bs.tab', loadReorder);
            reorderBtn.addEventListener('click', () => setTimeout(loadReorder, 100));
        }

        if (categoriesBtn) {
            categoriesBtn.addEventListener('shown.bs.tab', loadCategories);
            categoriesBtn.addEventListener('click', () => setTimeout(loadCategories, 100));
        }
    }

    _wireAddProduct() {
        document.getElementById('addCost')?.addEventListener('input',  () => this._updateMargin('add'));
        document.getElementById('addPrice')?.addEventListener('input', () => this._updateMargin('add'));

        document.getElementById('submitAdd')?.addEventListener('click', async () => {
            const btn   = document.getElementById('submitAdd');
            const catId = document.getElementById('addCategory')?.value;
            const desc  = document.getElementById('addDesc')?.value.trim();
            const unitSelVal = document.getElementById('addUnit')?.value;
            const unit  = unitSelVal === '__other__'
                ? document.getElementById('addUnitOther')?.value.trim()
                : unitSelVal;

            if (!catId || !desc || !unit) {
                Swal.fire({ icon: 'warning', title: 'Required fields missing', text: 'Category, Description and Unit are required.' });
                return;
            }

            const fd = new FormData();
            fd.append('category_id',       catId);
            fd.append('description',       desc);
            fd.append('unit',              unit);
            fd.append('code',              document.getElementById('addCode')?.value.trim() || '');
            fd.append('unit_cost',         document.getElementById('addCost')?.value || '0');
            fd.append('selling_price',     document.getElementById('addPrice')?.value || '0');
            fd.append('initial_quantity',  document.getElementById('addQty')?.value || '0');
            fd.append('reorder_threshold', document.getElementById('addThresh')?.value || '5');
            fd.append('compatible_brand', document.getElementById('addBrand')?.value.trim() || '');

            this._setBtn(btn, true, 'Saving…');
            try {
                const res  = await fetch('backend/products.php?action=add', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    bootstrap.Modal.getInstance(document.getElementById('addModal'))?.hide();
                    Swal.fire({ icon: 'success', title: 'Product added!', timer: 1400, showConfirmButton: false });
                    await this.refresh();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: json.message });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Network error', text: e.message });
            } finally {
                this._setBtn(btn, false, 'Save Product');
            }
        });
    }

    _wireEditProduct() {
        document.getElementById('editCost')?.addEventListener('input',  () => this._updateMargin('edit'));
        document.getElementById('editPrice')?.addEventListener('input', () => this._updateMargin('edit'));

        document.getElementById('submitEdit')?.addEventListener('click', async () => {
            const btn   = document.getElementById('submitEdit');
            const id    = document.getElementById('editId')?.value;
            const catId = document.getElementById('editCategory')?.value;
            const desc  = document.getElementById('editDesc')?.value.trim();
            const unitSelVal = document.getElementById('editUnit')?.value;
            const unit  = unitSelVal === '__other__'
                ? document.getElementById('editUnitOther')?.value.trim()
                : unitSelVal;

            if (!id || !catId || !desc || !unit) {
                Swal.fire({ icon: 'warning', title: 'Required fields missing', text: 'Category, Description and Unit are required.' });
                return;
            }

            const fd = new FormData();
            fd.append('product_id',        id);
            fd.append('category_id',       catId);
            fd.append('description',       desc);
            fd.append('unit',              unit);
            fd.append('code',              document.getElementById('editCode')?.value.trim() || '');
            fd.append('unit_cost',         document.getElementById('editCost')?.value || '0');
            fd.append('selling_price',     document.getElementById('editPrice')?.value || '0');
            fd.append('initial_quantity',  document.getElementById('editQty')?.value || '0');
            fd.append('reorder_threshold', document.getElementById('editThresh')?.value || '5');
            fd.append('compatible_brand', document.getElementById('editBrand')?.value.trim() || '');

            this._setBtn(btn, true, 'Saving…');
            try {
                const res  = await fetch('backend/products.php?action=update', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide();
                    Swal.fire({ icon: 'success', title: 'Updated!', timer: 1400, showConfirmButton: false });
                    await this.refresh();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: json.message });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Network error', text: e.message });
            } finally {
                this._setBtn(btn, false, 'Update Product');
            }
        });
    }

    _wireRestock() {
        document.getElementById('submitRestock')?.addEventListener('click', async () => {
            const btn     = document.getElementById('submitRestock');
            const id      = document.getElementById('restockId')?.value;
            const qty     = parseInt(document.getElementById('restockQty')?.value) || 0;
            const remarks = document.getElementById('restockRemarks')?.value.trim() || '';

            if (qty <= 0) {
                Swal.fire({ icon: 'warning', title: 'Enter a valid quantity' });
                return;
            }

            this._setBtn(btn, true, 'Saving…');
            try {
                const fd = new FormData();
                fd.append('product_id', id);
                fd.append('quantity',   qty);
                fd.append('remarks',    remarks);
                const res  = await fetch('backend/products.php?action=restock', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    const product = this._allProducts.find(p => String(p.product_id) === String(id));
                    if (product) {
                        this._ledger.pushBatch({
                            batchId:  'restock-' + Date.now(),
                            date:     new Date().toISOString().slice(0, 10),
                            qty,
                            unitCost: parseFloat(product.unit_cost) || 0,
                        });
                    }
                    bootstrap.Modal.getInstance(document.getElementById('restockModal'))?.hide();
                    await Swal.fire({
                        icon: 'success',
                        title: 'Restocked!',
                        html: '<b>' + json.product + '</b><br>+' + json.qty_added + ' units added<br>New stock: <b>' + json.new_stock + '</b>',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                    await this.refresh();
                } else {
                    Swal.fire({ icon: 'error', title: 'Restock Failed', text: json.message });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
            } finally {
                this._setBtn(btn, false, 'Record Restock');
            }
        });
    }

    _wireCategoryManagement() {
        document.getElementById('submitAddCategory')?.addEventListener('click', async () => {
            const name = document.getElementById('addCategoryName')?.value.trim();
            if (!name) {
                Swal.fire({ icon: 'warning', title: 'Required', text: 'Category name is required.' });
                return;
            }
            try {
                const fd = new FormData();
                fd.append('action', 'add');
                fd.append('category_name', name);
                const res  = await fetch('backend/categories.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    bootstrap.Modal.getInstance(document.getElementById('addCategoryModal'))?.hide();
                    document.getElementById('addCategoryName').value = '';
                    Swal.fire({ icon: 'success', title: 'Added!', timer: 1200, showConfirmButton: false });
                    this._categoriesLoaded = false;
                    await Promise.all([this._loadCategoriesTab(), this.loadCategories()]);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: json.message });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
            }
        });

        document.getElementById('submitEditCategory')?.addEventListener('click', async () => {
            const id   = document.getElementById('editCategoryId')?.value;
            const name = document.getElementById('editCategoryName')?.value.trim();
            if (!id || !name) {
                Swal.fire({ icon: 'warning', title: 'Required', text: 'Category name is required.' });
                return;
            }
            try {
                const fd = new FormData();
                fd.append('action', 'update');
                fd.append('category_id', id);
                fd.append('category_name', name);
                const res  = await fetch('backend/categories.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editCategoryModal'))?.hide();
                    Swal.fire({ icon: 'success', title: 'Updated!', timer: 1200, showConfirmButton: false });
                    this._categoriesLoaded = false;
                    await Promise.all([this._loadCategoriesTab(), this.loadCategories()]);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: json.message });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
            }
        });
    }

    async _loadCategoriesTab() {
        const tbody = document.getElementById('categoriesBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:30px;"><div class="spinner-border" style="color:#4ade80;"></div></td></tr>';
        try {
            const res  = await fetch('backend/categories.php?action=fetch');
            const json = await res.json();
            if (!json.success) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#fca5a5;">⚠ ' + json.message + '</td></tr>';
                return;
            }
            const cats = json.data || [];
            if (!cats.length) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#7a8499;">No categories found.</td></tr>';
                return;
            }
            tbody.innerHTML = cats.map((c, idx) => `
                <tr>
                    <td><span class="badge-gray">${idx + 1}</span></td>
                    <td style="font-weight:600;color:#e2e8f0;">${c.category_name}</td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-warning"
                                onclick="window.__inv.openEditCategory(${c.category_id}, '${c.category_name.replace(/'/g, "\\'")}')"
                                title="Edit"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger"
                                onclick="window.__inv.deleteCategory(${c.category_id}, '${c.category_name.replace(/'/g, "\\'")}')"
                                title="Delete"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>`).join('');
            this._categoriesLoaded = true;
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#fca5a5;">⚠ Network error. (' + err.message + ')</td></tr>';
        }
    }

    _updateMargin(prefix) {
        const cost  = parseFloat(document.getElementById(prefix + 'Cost')?.value) || 0;
        const price = parseFloat(document.getElementById(prefix + 'Price')?.value) || 0;
        const el    = document.getElementById(prefix + 'Margin');
        if (el) el.textContent = '₱' + (price - cost).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    }

    _setBtn(btn, disabled, label) {
        if (!btn) return;
        btn.disabled    = disabled;
        btn.textContent = label;
    }
}