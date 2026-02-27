(function () {
    'use strict';

    // ── HELPERS ──────────────────────────────────────────────────────
    function fmt(n) {
        return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function hide(id)    { const el = document.getElementById(id); if (el) el.style.display = 'none'; }
    function show(id)    { const el = document.getElementById(id); if (el) el.style.display = '';    }
    function setText(id, v) { const el = document.getElementById(id); if (el) el.textContent = v;   }

    // Week key: YYYY-WNN
    function weekKey(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        const tmp = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
        const dayNum = tmp.getUTCDay() || 7;
        tmp.setUTCDate(tmp.getUTCDate() + 4 - dayNum);
        const yearStart = new Date(Date.UTC(tmp.getUTCFullYear(), 0, 1));
        const weekNum = Math.ceil((((tmp - yearStart) / 86400000) + 1) / 7);
        return `${tmp.getUTCFullYear()}-W${String(weekNum).padStart(2, '0')}`;
    }

    // Month key: YYYY-MM
    function monthKey(dateStr) { return dateStr.substring(0, 7); }

    // ── SEASONAL CHART INSTANCE ──────────────────────────────────────
    let seasonalChartInstance = null;
    let workloadChartInstance = null;

    // ── LOAD & PROCESS SALES DATA ────────────────────────────────────
    async function loadForecastData() {
        show('forecastLoading');
        hide('forecastContent');

        try {
            const resp = await fetch('fetch_sales_for_forecast.php');
            const data = await resp.json();

            if (!data.success) {
                setText('weeklyForecastBody', '');
                document.getElementById('weeklyForecastBody').innerHTML =
                    '<tr><td colspan="5" class="text-center text-danger">Error loading data: ' + (data.message || '') + '</td></tr>';
                hide('forecastLoading');
                show('forecastContent');
                return;
            }

            buildForecast(data.items || [], data.sales || []);
        } catch (e) {
            console.error('Forecast fetch error:', e);
            document.getElementById('weeklyForecastBody').innerHTML =
                '<tr><td colspan="5" class="text-center text-danger">Network error loading forecast data.</td></tr>';
        } finally {
            hide('forecastLoading');
            show('forecastContent');
        }
    }

    function buildForecast(items, sales) {
        // ── Group sale_items by product + week / month ──
        const productMap = {}; // product_id → { description, code, weeklyQtys:{}, monthlyQtys:{} }

        items.forEach(item => {
            if (item.line_type !== 'parts' || !item.product_id) return;
            const pid  = item.product_id;
            const date = item.sale_date;

            if (!productMap[pid]) {
                productMap[pid] = {
                    description: item.description || 'Unknown',
                    code: item.code || '',
                    weeklyQtys:  {},
                    monthlyQtys: {}
                };
            }

            const wk = weekKey(date);
            const mk = monthKey(date);
            productMap[pid].weeklyQtys[wk]  = (productMap[pid].weeklyQtys[wk]  || 0) + (parseFloat(item.quantity) || 0);
            productMap[pid].monthlyQtys[mk] = (productMap[pid].monthlyQtys[mk] || 0) + (parseFloat(item.quantity) || 0);
        });

        // ── WEEKLY TABLE ──
        const wkBody = document.getElementById('weeklyForecastBody');
        wkBody.innerHTML = '';

        const wkRows = Object.entries(productMap).map(([pid, p]) => {
            const vals = Object.values(p.weeklyQtys);
            const avg  = vals.length ? vals.reduce((a, b) => a + b, 0) / vals.length : 0;
            return { description: p.description, code: p.code, avg, dataPoints: vals.length };
        }).filter(r => r.avg > 0).sort((a, b) => b.avg - a.avg);

        if (!wkRows.length) {
            wkBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No weekly sales data yet.</td></tr>';
        } else {
            wkRows.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escHtml(r.description)}</td>
                    <td><code>${escHtml(r.code)}</code></td>
                    <td>${r.avg.toFixed(1)} units/wk</td>
                    <td><strong>${(r.avg * 4).toFixed(0)} units</strong> (4 wks)</td>
                    <td><span class="badge bg-secondary">${r.dataPoints} wks</span></td>
                `;
                wkBody.appendChild(tr);
            });
        }

        // ── MONTHLY TABLE ──
        const mkBody = document.getElementById('monthlyForecastBody');
        mkBody.innerHTML = '';

        const mkRows = Object.entries(productMap).map(([pid, p]) => {
            const vals = Object.values(p.monthlyQtys);
            const avg  = vals.length ? vals.reduce((a, b) => a + b, 0) / vals.length : 0;
            return { description: p.description, code: p.code, avg, months: vals.length };
        }).filter(r => r.avg > 0).sort((a, b) => b.avg - a.avg);

        if (!mkRows.length) {
            mkBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No monthly data yet.</td></tr>';
        } else {
            mkRows.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escHtml(r.description)}</td>
                    <td><code>${escHtml(r.code)}</code></td>
                    <td>${r.avg.toFixed(1)} units/mo</td>
                    <td><strong>${(r.avg * 3).toFixed(0)} units</strong> (3 mos)</td>
                    <td><span class="badge bg-info text-dark">${r.months} mo</span></td>
                `;
                mkBody.appendChild(tr);
            });
        }

        // ── SEASONAL CHART (last 12 months revenue) ──
        buildSeasonalChart(sales);
    }

    function buildSeasonalChart(sales) {
        const monthlyRevenue = {};
        const now = new Date();
        // Initialize last 12 months
        for (let i = 11; i >= 0; i--) {
            const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const k = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            monthlyRevenue[k] = 0;
        }

        sales.forEach(s => {
            const mk = monthKey(s.sale_date);
            if (mk in monthlyRevenue) {
                monthlyRevenue[mk] += parseFloat(s.parts_total || 0) + parseFloat(s.labor_total || 0);
            }
        });

        const labels = Object.keys(monthlyRevenue).map(k => {
            const [y, m] = k.split('-');
            return new Date(y, m - 1).toLocaleString('en-PH', { month: 'short', year: '2-digit' });
        });
        const values = Object.values(monthlyRevenue);

        const ctx = document.getElementById('seasonalChart');
        if (!ctx) return;

        if (seasonalChartInstance) seasonalChartInstance.destroy();

        const grad = ctx.getContext('2d').createLinearGradient(0, 0, 0, 200);
        grad.addColorStop(0, 'rgba(6,182,212,0.4)');
        grad.addColorStop(1, 'rgba(6,182,212,0.02)');

        seasonalChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Monthly Revenue (₱)',
                    data: values,
                    backgroundColor: values.map((v, i) =>
                        i === values.length - 1 ? '#f97316' : 'rgba(6,182,212,0.75)'
                    ),
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ₱' + ctx.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 0 })
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v) }
                    }
                }
            }
        });
    }

    // ── REORDER RECOMMENDATIONS ──────────────────────────────────────
    async function loadReorderRecommendations() {
        show('reorderLoading');
        hide('reorderContent');
        hide('reorderEmpty');

        try {
            const resp = await fetch('fetch_reorder.php');
            const data = await resp.json();

            hide('reorderLoading');

            if (!data.success || !data.items || !data.items.length) {
                show('reorderContent');
                show('reorderEmpty');
                document.getElementById('reorderList').innerHTML = '';
                return;
            }

            show('reorderContent');
            buildReorderList(data.items);
        } catch (e) {
            hide('reorderLoading');
            show('reorderContent');
            document.getElementById('reorderList').innerHTML =
                '<p class="text-danger text-center">Error loading recommendations.</p>';
        }
    }

    function buildReorderList(items) {
        const container = document.getElementById('reorderList');
        container.innerHTML = '';

        items.forEach(item => {
            const critical  = parseInt(item.current_stock) <= 0;
            const urgency   = critical ? 'danger' : (parseInt(item.current_stock) <= Math.ceil(parseInt(item.reorder_threshold) / 2) ? 'warning' : 'info');
            const urgLabel  = critical ? '🔴 OUT OF STOCK' : (urgency === 'warning' ? '🟡 CRITICAL LOW' : '🔵 LOW STOCK');
            const suggested = Math.max(parseInt(item.reorder_threshold) * 3, 20);

            const div = document.createElement('div');
            div.className = `reorder-item border-${urgency === 'danger' ? 'danger' : urgency === 'warning' ? 'warning' : 'info'} bg-${urgency === 'danger' ? 'danger' : urgency === 'warning' ? 'warning' : 'info'} bg-opacity-10`;
            div.style.cssText = 'border-radius:10px;padding:12px 16px;margin-bottom:10px;border-left-width:4px;border-left-style:solid;';
            div.innerHTML = `
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <span class="badge bg-${urgency === 'danger' ? 'danger' : urgency === 'warning' ? 'warning text-dark' : 'info'} mb-1">${urgLabel}</span>
                        <div class="fw-bold">${escHtml(item.description)}</div>
                        <div class="text-muted small">${escHtml(item.category_name)} &bull; Code: <code>${escHtml(item.code)}</code></div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-center">
                            <div class="fw-bold fs-5 text-${urgency === 'danger' ? 'danger' : urgency === 'warning' ? 'warning' : 'primary'}">${item.current_stock}</div>
                            <div class="text-muted" style="font-size:.72rem">Current</div>
                        </div>
                        <div class="text-center">
                            <div class="fw-bold fs-5">${item.reorder_threshold}</div>
                            <div class="text-muted" style="font-size:.72rem">Min Level</div>
                        </div>
                        <div class="text-center">
                            <div class="fw-bold fs-5 text-success">${suggested}</div>
                            <div class="text-muted" style="font-size:.72rem">Order Qty</div>
                        </div>
                        <button class="btn btn-sm btn-success" onclick="openRestockModal(${item.product_id}, '${escHtml(item.description).replace(/'/g,"\\'")}', ${suggested})">
                            <i class="bi bi-box-arrow-in-down"></i> Restock
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(div);
        });
    }

    // ── RESTOCK MODAL ────────────────────────────────────────────────
    window.openRestockModal = function (productId, productName, suggestedQty) {
        document.getElementById('restockProductId').value   = productId;
        document.getElementById('restockProductName').textContent = productName;
        document.getElementById('restockQuantity').value   = suggestedQty || 20;
        document.getElementById('restockRemarks').value    = '';
        const modal = new bootstrap.Modal(document.getElementById('recordRestockModal'));
        modal.show();
    };

    document.addEventListener('DOMContentLoaded', function () {
        const submitBtn = document.getElementById('submitRestock');
        if (submitBtn) {
            submitBtn.addEventListener('click', async function () {
                const pid     = document.getElementById('restockProductId').value;
                const qty     = parseInt(document.getElementById('restockQuantity').value, 10);
                const remarks = document.getElementById('restockRemarks').value || 'Restock';

                if (!pid || qty <= 0) {
                    alert('Enter a valid quantity.');
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving…';

                try {
                    const fd = new FormData();
                    fd.append('product_id', pid);
                    fd.append('quantity', qty);
                    fd.append('remarks', remarks);

                    const resp = await fetch('add_stock.php', { method: 'POST', body: fd });
                    const data = await resp.json();

                    const modal = bootstrap.Modal.getInstance(document.getElementById('recordRestockModal'));
                    modal.hide();

                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Restocked!', text: data.message, timer: 1800, showConfirmButton: false });
                        loadProducts && loadProducts();
                        loadReorderRecommendations();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                    }
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Network error.' });
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Record Restock';
                }
            });
        }
    });

    // ── PEAK WORKLOAD ────────────────────────────────────────────────
    async function loadWorkloadData() {
        show('workloadLoading');
        hide('workloadContent');
        hide('workloadNoData');

        try {
            const resp = await fetch('fetch_workload.php');
            const data = await resp.json();

            hide('workloadLoading');

            if (!data.success || !data.weeks || !data.weeks.length) {
                show('workloadNoData');
                return;
            }

            show('workloadContent');
            buildWorkloadChart(data.weeks, data.avg_per_week);
        } catch (e) {
            hide('workloadLoading');
            show('workloadNoData');
        }
    }

    function buildWorkloadChart(weeks, avg) {
        setText('workloadAvg', `Average: ${avg} work orders / week`);

        const ctx = document.getElementById('workloadChart');
        if (!ctx) return;
        if (workloadChartInstance) workloadChartInstance.destroy();

        workloadChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: weeks.map(w => w.week_label || w.week),
                datasets: [
                    {
                        label: 'Work Orders',
                        data: weeks.map(w => w.count),
                        backgroundColor: weeks.map(w =>
                            w.week === weeks[weeks.length - 1].week ? '#f97316' : 'rgba(102,126,234,0.75)'
                        ),
                        borderRadius: 6,
                        borderSkipped: false
                    },
                    {
                        label: 'Avg',
                        data: weeks.map(() => avg),
                        type: 'line',
                        borderColor: '#ef4444',
                        borderDash: [6, 4],
                        borderWidth: 2,
                        pointRadius: 0,
                        fill: false,
                        tension: 0
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: { label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y}` }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    // ── XSS escape ──────────────────────────────────────────────────
    function escHtml(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    // ── TAB ACTIVATION ───────────────────────────────────────────────
    let forecastLoaded  = false;
    let reorderLoaded   = false;
    let workloadLoaded  = false;

    document.addEventListener('DOMContentLoaded', function () {
        const tabEls = document.querySelectorAll('#inventoryMainTabs [data-bs-toggle="tab"]');
        tabEls.forEach(tab => {
            tab.addEventListener('shown.bs.tab', function (e) {
                const target = e.target.getAttribute('data-bs-target');
                if (target === '#forecast-panel' && !forecastLoaded) {
                    forecastLoaded = true;
                    loadForecastData();
                } else if (target === '#reorder-panel' && !reorderLoaded) {
                    reorderLoaded = true;
                    loadReorderRecommendations();
                } else if (target === '#workload-panel' && !workloadLoaded) {
                    workloadLoaded = true;
                    loadWorkloadData();
                }
            });
        });

        // Reload reorder after any restock (product table reload)
        document.addEventListener('productReloaded', function () {
            reorderLoaded = false;
        });
    });

})();