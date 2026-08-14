'use strict';

document.addEventListener('DOMContentLoaded', async () => {

    const api      = new InventoryAPI('backend/products.php');
    const ledger   = new StockLedger();
    const renderer = new InventoryRenderer({}, typeof IS_OWNER !== 'undefined' ? IS_OWNER : false);
    const controller = new InventoryController(api, renderer, ledger);

    window.__inv         = controller;
    window.openRestock   = (id, name) => controller.openRestock(id, name);
    window.openEdit      = (id)       => controller.openEdit(id);
    window.deleteProduct = (id, name) => controller.deleteProduct(id, name);

    // Shows/hides the free-text unit input when "Others" is chosen in the Unit dropdown.
    window.toggleCustomUnit = (prefix) => {
        const sel = document.getElementById(prefix + 'Unit');
        const other = document.getElementById(prefix + 'UnitOther');
        if (!sel || !other) return;
        if (sel.value === '__other__') {
            other.style.display = '';
            other.focus();
        } else {
            other.style.display = 'none';
            other.value = '';
        }
    };

    // Reset the custom unit field whenever the Add Product modal is opened fresh.
    const addModalEl = document.getElementById('addModal');
    if (addModalEl) {
        addModalEl.addEventListener('show.bs.modal', () => {
            const sel = document.getElementById('addUnit');
            const other = document.getElementById('addUnitOther');
            if (sel) sel.value = '';
            if (other) { other.value = ''; other.style.display = 'none'; }
        });
    }

    try {
        await controller.init();
    } catch (err) {
        console.error('InventoryController init failed:', err);
        const tbody = document.getElementById('stockTableBody');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:24px;color:#fca5a5;">
                ⚠ JS Error: ${err.message}<br>
                <small style="color:#64748b;">Check browser console (F12) for details.</small>
            </td></tr>`;
        }
    }
});