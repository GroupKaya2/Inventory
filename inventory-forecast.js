// Inventory Forecasting & Planning – load forecast data and render tabs

let forecastData = null;
let seasonalChart = null;
let workloadChart = null;

function loadForecastData() {
    return fetch('fetch_forecast_data.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Failed to load forecast');
            forecastData = data;
            return data;
        });
}

function showForecastPanel() {
    const loading = document.getElementById('forecastLoading');
    const content = document.getElementById('forecastContent');
    if (!loading || !content) return;
    loading.style.display = 'block';
    content.style.display = 'none';
    loadForecastData().then(data => {
        loading.style.display = 'none';
        content.style.display = 'block';
        renderWeeklyForecast(data.weekly_forecast || []);
        renderMonthlyForecast(data.monthly_forecast || []);
        renderSeasonalChart(data.seasonal_demand || []);
    }).catch(err => {
        loading.style.display = 'none';
        content.innerHTML = '<p class="text-danger">' + (err.message || 'Error loading forecast') + '</p>';
        content.style.display = 'block';
    });
}

function renderWeeklyForecast(arr) {
    const tbody = document.getElementById('weeklyForecastBody');
    if (!tbody) return;
    tbody.innerHTML = arr.map(r => `
        <tr>
            <td>${escapeHtml(r.description)}</td>
            <td>${escapeHtml(r.code || '-')}</td>
            <td>${r.avg_weekly_usage}</td>
            <td>${r.next_4_weeks_predicted}</td>
            <td>${r.weeks_of_data || 0}</td>
        </tr>
    `).join('') || '<tr><td colspan="5" class="text-muted">No usage data yet.</td></tr>';
}

function renderMonthlyForecast(arr) {
    const tbody = document.getElementById('monthlyForecastBody');
    if (!tbody) return;
    tbody.innerHTML = arr.map(r => `
        <tr>
            <td>${escapeHtml(r.description)}</td>
            <td>${escapeHtml(r.code || '-')}</td>
            <td>${r.avg_monthly_usage}</td>
            <td>${r.next_3_months_predicted}</td>
            <td>${r.months_of_data || 0}</td>
        </tr>
    `).join('') || '<tr><td colspan="5" class="text-muted">No usage data yet.</td></tr>';
}

function renderSeasonalChart(seasonalDemand) {
    const canvas = document.getElementById('seasonalChart');
    if (!canvas) return;
    if (seasonalChart) seasonalChart.destroy();
    const arr = Array.isArray(seasonalDemand) ? seasonalDemand : [];
    const labels = arr.map(d => d.label || '');
    const values = arr.map(d => (d.total_qty != null ? Number(d.total_qty) : 0));
    seasonalChart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels.length ? labels : ['No data'],
            datasets: [{
                label: 'Parts usage',
                data: values.length ? values : [0],
                backgroundColor: 'rgba(99, 102, 241, 0.6)',
                borderColor: 'rgba(99, 102, 241, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: { beginAtZero: true }
            },
            plugins: {
                legend: { display: true }
            }
        }
    });
}

function showReorderPanel() {
    const loading = document.getElementById('reorderLoading');
    const content = document.getElementById('reorderContent');
    const empty = document.getElementById('reorderEmpty');
    const list = document.getElementById('reorderList');
    if (!loading || !content) return;
    loading.style.display = 'block';
    content.style.display = 'none';
    if (empty) empty.style.display = 'none';
    loadForecastData().then(data => {
        loading.style.display = 'none';
        content.style.display = 'block';
        const recs = data.reorder_recommendations || [];
        if (recs.length === 0) {
            if (empty) empty.style.display = 'block';
            if (list) list.innerHTML = '';
        } else {
            if (empty) empty.style.display = 'none';
            if (list) {
                list.innerHTML = recs.map(r => `
                    <div class="reorder-item d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <strong>${escapeHtml(r.description)}</strong> ${r.code ? '(' + escapeHtml(r.code) + ')' : ''}<br>
                            <small class="text-muted">Stock: ${r.current_stock} | Threshold: ${r.reorder_threshold} | Recommended: ${r.recommended_qty} ${r.reason ? ' · ' + r.reason : ''}</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-success" onclick="openRecordRestock(${r.product_id}, '${escapeJs(r.description)}', ${r.recommended_qty})">
                            <i class="bi bi-box-arrow-in-down"></i> Record reorder
                        </button>
                    </div>
                `).join('');
            }
        }
    }).catch(err => {
        loading.style.display = 'none';
        content.style.display = 'block';
        if (list) list.innerHTML = '<p class="text-danger">' + (err.message || 'Error loading recommendations') + '</p>';
    });
}

function openRecordRestock(productId, productName, recommendedQty) {
    document.getElementById('restockProductId').value = productId;
    document.getElementById('restockProductName').textContent = productName;
    document.getElementById('restockQuantity').value = Math.max(1, recommendedQty || 1);
    document.getElementById('restockRemarks').value = '';
    const modal = new bootstrap.Modal(document.getElementById('recordRestockModal'));
    modal.show();
}

function submitRestock() {
    const productId = document.getElementById('restockProductId').value;
    const qty = document.getElementById('restockQuantity').value;
    const remarks = document.getElementById('restockRemarks').value || 'Restock';
    if (!productId || !qty || parseInt(qty, 10) < 1) {
        if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Invalid', text: 'Enter a valid quantity.' });
        return;
    }
    const form = new FormData();
    form.append('product_id', productId);
    form.append('quantity', qty);
    form.append('remarks', remarks);
    fetch('add_stock.php', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Done', text: data.message, timer: 2000, showConfirmButton: false });
                }
                bootstrap.Modal.getInstance(document.getElementById('recordRestockModal')).hide();
                if (typeof loadProducts === 'function') loadProducts();
                showReorderPanel();
            } else {
                if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        })
        .catch(err => {
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Error', text: err.message });
        });
}

function showWorkloadPanel() {
    const loading = document.getElementById('workloadLoading');
    const content = document.getElementById('workloadContent');
    const noData = document.getElementById('workloadNoData');
    const avgEl = document.getElementById('workloadAvg');
    if (!loading || !content) return;
    loading.style.display = 'block';
    content.style.display = 'none';
    if (noData) noData.style.display = 'none';
    loadForecastData().then(data => {
        loading.style.display = 'none';
        const pw = data.peak_workload || {};
        if (pw.message || (!pw.past && !pw.predicted_weeks)) {
            if (noData) noData.style.display = 'block';
            content.style.display = 'none';
            return;
        }
        content.style.display = 'block';
        if (noData) noData.style.display = 'none';
        if (avgEl) avgEl.textContent = 'Average work orders per week: ' + (pw.avg_per_week != null ? pw.avg_per_week : 'N/A');
        const past = Array.isArray(pw.past) ? pw.past : [];
        const pred = Array.isArray(pw.predicted_weeks) ? pw.predicted_weeks : [];
        const labels = past.map(p => 'W' + String(p.week || '').slice(-2)).concat(pred.map(p => p.week_label || ''));
        const counts = past.map(p => Number(p.count) || 0).concat(pred.map(p => Number(p.predicted_count) || 0));
        if (labels.length === 0 && counts.length === 0) {
            if (noData) noData.style.display = 'block';
            content.style.display = 'none';
            return;
        }
        const colors = counts.map((_, i) => i < past.length ? 'rgba(100, 116, 139, 0.7)' : 'rgba(99, 102, 241, 0.5)');
        const canvas = document.getElementById('workloadChart');
        if (canvas) {
            if (workloadChart) workloadChart.destroy();
            workloadChart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels.length ? labels : ['No data'],
                    datasets: [{
                        label: 'Work orders',
                        data: counts.length ? counts : [0],
                        backgroundColor: colors.length ? colors : ['rgba(99, 102, 241, 0.5)'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    }).catch(() => {
        loading.style.display = 'none';
        if (noData) noData.style.display = 'block';
    });
}

function escapeHtml(s) {
    if (s == null) return '';
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}

function escapeJs(s) {
    if (s == null) return '';
    return String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, '\\n');
}

document.addEventListener('DOMContentLoaded', function() {
    const forecastTab = document.getElementById('forecast-tab');
    const reorderTab = document.getElementById('reorder-tab');
    const workloadTab = document.getElementById('workload-tab');
    if (forecastTab) {
        forecastTab.addEventListener('shown.bs.tab', function() {
            showForecastPanel();
        });
    }
    if (reorderTab) {
        reorderTab.addEventListener('shown.bs.tab', function() {
            showReorderPanel();
        });
    }
    if (workloadTab) {
        workloadTab.addEventListener('shown.bs.tab', function() {
            showWorkloadPanel();
        });
    }
    const submitBtn = document.getElementById('submitRestock');
    if (submitBtn) submitBtn.addEventListener('click', submitRestock);
});
