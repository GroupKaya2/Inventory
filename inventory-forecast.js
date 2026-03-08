/**
 * inventory-forecast.js
 * Handles the Forecasting, Reorder, and Peak Workload tabs in inventory.php
 * Requires: Chart.js, fetch_sales_for_forecast.php, fetch_reorder.php
 */

(function () {
    'use strict';

    let forecastData = null;
    let seasonalChart = null;
    let workloadChart = null;

    // ── Wait for forecast tab to be clicked ───────────────
    document.addEventListener('DOMContentLoaded', function () {
        const forecastTab  = document.getElementById('forecast-tab');
        const reorderTab   = document.getElementById('reorder-tab');
        const workloadTab  = document.getElementById('workload-tab');

        if (forecastTab)  forecastTab.addEventListener('click',  function () { if (!forecastData) loadForecast(); else renderForecast(forecastData); });
        if (reorderTab)   reorderTab.addEventListener('click',   loadReorder);
        if (workloadTab)  workloadTab.addEventListener('click',  function () { if (forecastData) renderWorkload(forecastData.workload); else loadForecast(); });

        // Also load restock modal handler
        document.getElementById('submitRestock')?.addEventListener('click', submitRestock);
    });

    // ── LOAD FORECAST DATA ─────────────────────────────────
    function loadForecast() {
        showForecastLoading(true);

        fetch('fetch_sales_for_forecast.php')
            .then(function (r) {
                if (!r.ok) throw new Error('Server error: ' + r.status);
                return r.json();
            })
            .then(function (data) {
                showForecastLoading(false);
                if (!data.success) {
                    showForecastError(data.message || 'Failed to load data.');
                    return;
                }
                forecastData = data;
                renderForecast(data);
                renderWorkload(data.workload);
            })
            .catch(function (err) {
                showForecastLoading(false);
                showForecastError('Network error: ' + err.message + '. Make sure fetch_sales_for_forecast.php exists.');
            });
    }

    function showForecastLoading(show) {
        const el = document.getElementById('forecastLoading');
        const ct = document.getElementById('forecastContent');
        if (el) el.style.display = show ? 'block' : 'none';
        if (ct) ct.style.display = show ? 'none' : 'block';
    }

    function showForecastError(msg) {
        const el = document.getElementById('forecastLoading');
        if (el) {
            el.style.display = 'block';
            el.innerHTML = '<div style="color:#ef4444;padding:16px;text-align:center;"><i class="bi bi-exclamation-triangle-fill fs-4 d-block mb-2"></i>' + msg + '</div>';
        }
    }

    // ── RENDER FORECAST TABLES ─────────────────────────────
    function renderForecast(data) {
        const items = data.items || [];

        if (!items.length) {
            // No sales data yet — show friendly empty state
            const emptyHtml = '<tr><td colspan="5" class="text-center py-3 text-muted"><i class="bi bi-info-circle me-1"></i>No sales data yet. Record some sales to see forecasts.</td></tr>';
            const wb = document.getElementById('weeklyForecastBody');
            const mb = document.getElementById('monthlyForecastBody');
            if (wb) wb.innerHTML = emptyHtml;
            if (mb) mb.innerHTML = emptyHtml;
        } else {
            renderWeeklyForecast(items);
            renderMonthlyForecast(items);
        }

        // Always render seasonal chart
        renderSeasonalChart(data.monthly || []);
    }

    function renderWeeklyForecast(items) {
        const body = document.getElementById('weeklyForecastBody');
        if (!body) return;

        // Avg weekly = total_qty / 12 weeks
        const rows = items.map(function (item) {
            const totalQty   = parseFloat(item.total_qty) || 0;
            const avgWeekly  = totalQty / 12;
            const next4Weeks = Math.ceil(avgWeekly * 4);
            const dataPoints = parseInt(item.sale_weeks) || 0;

            const trend = avgWeekly > 0
                ? (avgWeekly >= 2 ? '<span style="color:#34d399;">↑ High demand</span>' : '<span style="color:#fcd34d;">→ Moderate</span>')
                : '<span style="color:#94a3b8;">— Low / none</span>';

            return '<tr>' +
                '<td><strong>' + esc(item.description) + '</strong></td>' +
                '<td><code style="font-size:.75rem;color:#a5b4fc;">' + esc(item.code || '—') + '</code></td>' +
                '<td>' + avgWeekly.toFixed(1) + ' units/wk &nbsp;' + trend + '</td>' +
                '<td><strong style="color:#f97316;">' + next4Weeks + ' units</strong></td>' +
                '<td><span class="badge bg-secondary">' + dataPoints + ' weeks</span></td>' +
                '</tr>';
        });

        body.innerHTML = rows.join('');
    }

    function renderMonthlyForecast(items) {
        const body = document.getElementById('monthlyForecastBody');
        if (!body) return;

        // Avg monthly = total_qty / 3 months (last quarter is most relevant)
        const rows = items.map(function (item) {
            const totalQty    = parseFloat(item.total_qty) || 0;
            const avgMonthly  = totalQty / 12; // normalize 12-month total
            const next3Months = Math.ceil(avgMonthly * 3);
            const dataMonths  = parseInt(item.sale_months) || 0;

            const confidence = dataMonths >= 6
                ? '<span style="color:#34d399;font-size:.72rem;">High confidence</span>'
                : dataMonths >= 3
                ? '<span style="color:#fcd34d;font-size:.72rem;">Moderate</span>'
                : '<span style="color:#94a3b8;font-size:.72rem;">Low data</span>';

            return '<tr>' +
                '<td><strong>' + esc(item.description) + '</strong></td>' +
                '<td><code style="font-size:.75rem;color:#a5b4fc;">' + esc(item.code || '—') + '</code></td>' +
                '<td>' + avgMonthly.toFixed(1) + ' units/mo</td>' +
                '<td><strong style="color:#60a5fa;">' + next3Months + ' units</strong> &nbsp;' + confidence + '</td>' +
                '<td><span class="badge bg-secondary">' + dataMonths + ' months</span></td>' +
                '</tr>';
        });

        body.innerHTML = rows.join('');
    }

    // ── SEASONAL DEMAND CHART ─────────────────────────────
    function renderSeasonalChart(monthly) {
        const canvas = document.getElementById('seasonalChart');
        if (!canvas) return;

        // Destroy existing chart
        if (seasonalChart) { seasonalChart.destroy(); seasonalChart = null; }

        if (!monthly || !monthly.length) {
            canvas.parentElement.innerHTML += '<p class="text-muted text-center mt-2" style="font-size:.82rem;">No monthly data yet. Sales will appear here once recorded.</p>';
            return;
        }

        const labels  = monthly.map(function (m) { return m.month_label; });
        const parts   = monthly.map(function (m) { return parseFloat(m.parts_total) || 0; });
        const labor   = monthly.map(function (m) { return parseFloat(m.labor_total) || 0; });
        const totals  = monthly.map(function (m) { return parseFloat(m.grand_total) || 0; });

        seasonalChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Parts Revenue (₱)',
                        data: parts,
                        backgroundColor: 'rgba(99,102,241,.7)',
                        borderRadius: 6,
                        borderSkipped: false
                    },
                    {
                        label: 'Labor Revenue (₱)',
                        data: labor,
                        backgroundColor: 'rgba(16,185,129,.7)',
                        borderRadius: 6,
                        borderSkipped: false
                    },
                    {
                        label: 'Total Revenue (₱)',
                        data: totals,
                        type: 'line',
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249,115,22,.15)',
                        borderWidth: 2,
                        pointBackgroundColor: '#f97316',
                        pointRadius: 4,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: '#94a3b8', font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.dataset.label + ': ₱' + ctx.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 0 });
                            }
                        }
                    }
                },
                scales: {
                    x: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,.05)' } },
                    y: { ticks: { color: '#94a3b8', callback: function (v) { return '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v); } }, grid: { color: 'rgba(255,255,255,.05)' } }
                }
            }
        });
    }

    // ── WORKLOAD CHART ─────────────────────────────────────
    function renderWorkload(workload) {
        const loading = document.getElementById('workloadLoading');
        const content = document.getElementById('workloadContent');
        const noData  = document.getElementById('workloadNoData');

        if (loading) loading.style.display = 'none';

        if (!workload || !workload.length) {
            if (noData)  noData.style.display  = 'block';
            if (content) content.style.display = 'none';
            return;
        }

        if (content) content.style.display = 'block';
        if (noData)  noData.style.display  = 'none';

        // Avg
        const avg = workload.reduce(function (s, w) { return s + parseInt(w.total_orders); }, 0) / workload.length;
        const avgEl = document.getElementById('workloadAvg');
        if (avgEl) avgEl.textContent = 'Average: ' + avg.toFixed(1) + ' work orders/week over the last 12 weeks';

        const canvas = document.getElementById('workloadChart');
        if (!canvas) return;
        if (workloadChart) { workloadChart.destroy(); workloadChart = null; }

        const labels    = workload.map(function (w) { return 'Wk ' + formatDate(w.week_start); });
        const totals    = workload.map(function (w) { return parseInt(w.total_orders); });
        const completed = workload.map(function (w) { return parseInt(w.completed); });

        workloadChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Work Orders',
                        data: totals,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,.15)',
                        fill: true, tension: 0.4,
                        pointBackgroundColor: '#6366f1', pointRadius: 4, borderWidth: 2
                    },
                    {
                        label: 'Completed',
                        data: completed,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,.1)',
                        fill: true, tension: 0.4,
                        pointBackgroundColor: '#10b981', pointRadius: 4, borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { labels: { color: '#94a3b8', font: { size: 11 } } } },
                scales: {
                    x: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,.05)' } },
                    y: { beginAtZero: true, ticks: { color: '#94a3b8', stepSize: 1 }, grid: { color: 'rgba(255,255,255,.05)' } }
                }
            }
        });
    }

    // ── REORDER TAB ────────────────────────────────────────
    function loadReorder() {
        const loading = document.getElementById('reorderLoading');
        const content = document.getElementById('reorderContent');
        if (loading) loading.style.display = 'block';
        if (content) content.style.display = 'none';

        fetch('fetch_reorder.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (loading) loading.style.display = 'none';
                if (content) content.style.display = 'block';

                const list  = document.getElementById('reorderList');
                const empty = document.getElementById('reorderEmpty');

                if (!data.success || !data.data || !data.data.length) {
                    if (list)  list.innerHTML  = '';
                    if (empty) empty.style.display = 'block';
                    return;
                }

                if (empty) empty.style.display = 'none';

                const rows = data.data.map(function (item) {
                    const stock     = parseInt(item.current_stock) || 0;
                    const threshold = parseInt(item.reorder_threshold) || 5;
                    const urgency   = stock <= 0
                        ? '<span class="badge bg-danger">Out of Stock</span>'
                        : stock <= Math.floor(threshold / 2)
                        ? '<span class="badge bg-danger">Critical</span>'
                        : '<span class="badge bg-warning text-dark">Low</span>';
                    const suggested = Math.max(threshold * 2, 10);

                    return '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;padding:12px 16px;border-bottom:1px solid rgba(255,255,255,.06);">' +
                        '<div>' +
                            '<strong style="color:#fff;">' + esc(item.description) + '</strong>' +
                            '<div style="font-size:.75rem;color:#94a3b8;">' + esc(item.category_name || '') + (item.code ? ' · ' + esc(item.code) : '') + '</div>' +
                        '</div>' +
                        '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">' +
                            urgency +
                            '<span style="font-size:.82rem;color:#94a3b8;">Stock: <strong style="color:#fca5a5;">' + stock + '</strong></span>' +
                            '<span style="font-size:.82rem;color:#94a3b8;">Threshold: <strong style="color:#fcd34d;">' + threshold + '</strong></span>' +
                            '<button onclick="openRestockModal(' + item.product_id + ', \'' + esc(item.description).replace(/'/g, "\\'") + '\', ' + suggested + ')" ' +
                                'class="btn btn-sm btn-warning text-dark fw-bold">' +
                                '<i class="bi bi-box-arrow-in-down me-1"></i>Restock' +
                            '</button>' +
                        '</div>' +
                    '</div>';
                });

                if (list) list.innerHTML = rows.join('');
            })
            .catch(function (err) {
                if (loading) loading.style.display = 'none';
                const list = document.getElementById('reorderList');
                if (list) list.innerHTML = '<div class="text-center text-danger py-3"><i class="bi bi-exclamation-triangle me-1"></i>Error: ' + err.message + '</div>';
            });
    }

    // ── RESTOCK MODAL ──────────────────────────────────────
    window.openRestockModal = function (productId, productName, suggestedQty) {
        document.getElementById('restockProductId').value   = productId;
        document.getElementById('restockProductName').textContent = productName;
        document.getElementById('restockQuantity').value   = suggestedQty || 20;
        document.getElementById('restockRemarks').value    = '';
        new bootstrap.Modal(document.getElementById('recordRestockModal')).show();
    };

    function submitRestock() {
        const productId = document.getElementById('restockProductId').value;
        const qty       = parseInt(document.getElementById('restockQuantity').value);
        const remarks   = document.getElementById('restockRemarks').value.trim();

        if (!productId || qty < 1) {
            Swal.fire({ icon: 'warning', title: 'Invalid', text: 'Enter a valid quantity.' });
            return;
        }

        const btn = document.getElementById('submitRestock');
        btn.disabled    = true;
        btn.textContent = 'Saving…';

        const fd = new FormData();
        fd.append('product_id', productId);
        fd.append('quantity',   qty);
        fd.append('remarks',    remarks || 'Restock');

        fetch('add_stock.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled    = false;
                btn.textContent = 'Record Restock';
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('recordRestockModal')).hide();
                    Swal.fire({ icon: 'success', title: 'Saved successfully', timer: 1400, showConfirmButton: false });
                    // Reload products table
                    if (typeof loadProducts === 'function') loadProducts();
                    // Reload reorder list if visible
                    const reorderPanel = document.getElementById('reorder-panel');
                    if (reorderPanel && reorderPanel.classList.contains('show')) loadReorder();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Failed to save.' });
                }
            })
            .catch(function (err) {
                btn.disabled    = false;
                btn.textContent = 'Record Restock';
                Swal.fire({ icon: 'error', title: 'Network Error', text: err.message });
            });
    }

    // ── HELPERS ────────────────────────────────────────────
    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        return (d.getMonth() + 1) + '/' + d.getDate();
    }

})();