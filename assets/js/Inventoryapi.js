'use strict';

class InventoryAPI {
    constructor(baseUrl = 'backend/products.php') {
        this._base = baseUrl;
    }

    async _get(action, params = {}) {
        const qs = new URLSearchParams({ action, ...params }).toString();
        const res = await fetch(`${this._base}?${qs}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    async _post(action, fields = {}) {
        const fd = new FormData();
        Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
        const res = await fetch(`${this._base}?action=${action}`, { method: 'POST', body: fd });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    fetchAll()                                          { return this._get('fetch'); }
    fetchOne(id)                                        { return this._get('get', { id }); }
    fetchCategories()                                   { return this._get('categories'); }
    fetchReorderList()                                  { return this._get('reorder-list'); }
    addProduct(fields)                                  { return this._post('add', fields); }
    updateProduct(fields)                               { return this._post('update', fields); }
    deleteProduct(id)                                   { return this._post('delete', { id }); }
    restock(productId, quantity, remarks = 'Restock')   { return this._post('restock', { product_id: productId, quantity, remarks }); }
}