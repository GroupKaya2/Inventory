<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
if (!$isOwner) {
    header("Location: dashboard.php");
    exit();
}

$activePage = 'activity_log';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - DSpeedway</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .al-tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 18px;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .al-tab {
            padding: 10px 18px;
            font-size: .85rem;
            font-weight: 600;
            color: #64748b;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: all .15s;
        }
        .al-tab:hover { color: #e2e8f0; }
        .al-tab.active {
            color: #4ade80;
            border-bottom-color: #4ade80;
        }
        .al-panel { display: none; }
        .al-panel.active { display: block; }

        .al-filter-bar {
            background: #111827;
            border: 1px solid rgba(255,255,255,.06);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .al-filter-bar select,
        .al-filter-bar input {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            color: #e2e8f0;
            border-radius: 8px;
            height: 36px;
            font-size: .8rem;
            padding: 0 10px;
        }
        .al-filter-bar select option { background: #1c2336; }

        .badge-create { background: rgba(74,222,128,.12); color: #4ade80; padding: 3px 10px; border-radius: 6px; font-size: .7rem; font-weight: 700; }
        .badge-update { background: rgba(96,165,250,.12); color: #60a5fa; padding: 3px 10px; border-radius: 6px; font-size: .7rem; font-weight: 700; }
        .badge-delete { background: rgba(248,113,113,.12); color: #f87171; padding: 3px 10px; border-radius: 6px; font-size: .7rem; font-weight: 700; }
        .badge-adjustment { background: rgba(251,191,36,.12); color: #fbbf24; padding: 3px 10px; border-radius: 6px; font-size: .7rem; font-weight: 700; }

        .al-role-owner { color: #fbbf24; font-size: .68rem; font-weight: 700; text-transform: uppercase; }
        .al-role-manager { color: #60a5fa; font-size: .68rem; font-weight: 700; text-transform: uppercase; }

        .al-details-btn {
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1);
            color: #94a3b8;
            border-radius: 6px;
            padding: 3px 10px;
            font-size: .72rem;
            cursor: pointer;
        }
        .al-details-btn:hover { background: rgba(255,255,255,.1); color: #fff; }

        #detailsModal .modal-content {
            background: #161b27;
            border: 1px solid rgba(74,222,128,.15);
            color: #e2e8f0;
        }
        #detailsModal .modal-header {
            background: linear-gradient(135deg,#0f1f15,#111827);
            border-bottom: 1px solid rgba(74,222,128,.15);
        }
        #detailsModal pre {
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 8px;
            padding: 12px;
            font-size: .78rem;
            color: #cbd5e1;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .session-active { color: #4ade80; font-weight: 600; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="app-main">

    <div class="page-header">
        <h4><i class="bi bi-shield-lock-fill me-2" style="color:#4ade80;"></i>Activity Log</h4>
        <p>Monitor staff logins and every add/edit/delete across the system.</p>
    </div>

    <div class="al-tabs">
        <button class="al-tab active" data-tab="logins" onclick="switchTab('logins')">
            <i class="bi bi-box-arrow-in-right me-1"></i> Login History
        </button>
        <button class="al-tab" data-tab="activity" onclick="switchTab('activity')">
            <i class="bi bi-clock-history me-1"></i> Activity Log
        </button>
    </div>

    <!-- LOGIN HISTORY -->
    <div class="al-panel active" id="panel-logins">
        <div class="al-filter-bar">
            <select id="loginUserFilter" onchange="loadLogins()">
                <option value="">All Users</option>
            </select>
            <input type="date" id="loginDateFrom" onchange="loadLogins()">
            <span style="color:#64748b;font-size:.8rem;">to</span>
            <input type="date" id="loginDateTo" onchange="loadLogins()">
            <button class="btn-ghost" style="font-size:.8rem;padding:6px 14px;" onclick="resetLoginFilters()">Reset</button>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Login Date &amp; Time</th>
                            <th>Logout Time</th>
                            <th>Session Duration</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="loginTableBody">
                        <tr><td colspan="7" style="text-align:center;padding:30px;"><div class="spinner-border" style="color:#4ade80;"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ACTIVITY LOG -->
    <div class="al-panel" id="panel-activity">
        <div class="al-filter-bar">
            <select id="actUserFilter" onchange="loadActivity()">
                <option value="">All Users</option>
            </select>
            <select id="actModuleFilter" onchange="loadActivity()">
                <option value="">All Modules</option>
                <option value="sales">Daily Transaction / Sales</option>
                <option value="expenses">Expenses</option>
                <option value="products">Products &amp; Stock</option>
                <option value="categories">Categories</option>
                <option value="services">Services</option>
                <option value="accounts_payable">Accounts Payable</option>
            </select>
            <select id="actTypeFilter" onchange="loadActivity()">
                <option value="">All Actions</option>
                <option value="CREATE">Create</option>
                <option value="UPDATE">Update</option>
                <option value="DELETE">Delete</option>
                <option value="ADJUSTMENT">Adjustment</option>
            </select>
            <input type="date" id="actDateFrom" onchange="loadActivity()">
            <span style="color:#64748b;font-size:.8rem;">to</span>
            <input type="date" id="actDateTo" onchange="loadActivity()">
            <button class="btn-ghost" style="font-size:.8rem;padding:6px 14px;" onclick="resetActivityFilters()">Reset</button>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date &amp; Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Record ID</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody id="activityTableBody">
                        <tr><td colspan="7" style="text-align:center;padding:30px;"><div class="spinner-border" style="color:#4ade80;"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<!-- Details modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="color:#4ade80;"><i class="bi bi-file-earmark-text me-2"></i>Change Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsModalBody"></div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
'use strict';

const MODULE_LABELS = {
    sales: 'Daily Transaction / Sales',
    expenses: 'Expenses',
    products: 'Products & Stock',
    categories: 'Categories',
    services: 'Services',
    accounts_payable: 'Accounts Payable',
};

function switchTab(tab) {
    document.querySelectorAll('.al-tab').forEach(el => el.classList.toggle('active', el.dataset.tab === tab));
    document.querySelectorAll('.al-panel').forEach(el => el.classList.remove('active'));
    document.getElementById('panel-' + tab).classList.add('active');
}

function esc(str) {
    return String(str || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function fmtDateTime(s) {
    if (!s) return '—';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d)) return s;
    return d.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
}

function sessionDuration(login, logout) {
    if (!logout) return '<span class="session-active">● Active</span>';
    const start = new Date(login.replace(' ', 'T'));
    const end = new Date(logout.replace(' ', 'T'));
    const mins = Math.round((end - start) / 60000);
    if (mins < 60) return mins + ' min';
    const h = Math.floor(mins / 60), m = mins % 60;
    return h + 'h ' + m + 'm';
}

async function populateUserFilters() {
    try {
        const res = await fetch('backend/audit-log.php?action=users');
        const json = await res.json();
        if (!json.success) return;
        const opts = json.data.map(u => `<option value="${u.id}">${esc(u.name)} (${u.role})</option>`).join('');
        document.getElementById('loginUserFilter').innerHTML += opts;
        document.getElementById('actUserFilter').innerHTML += opts;
    } catch (e) { console.error(e); }
}

async function loadLogins() {
    const tbody = document.getElementById('loginTableBody');
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;"><div class="spinner-border" style="color:#4ade80;"></div></td></tr>';

    const params = new URLSearchParams({ action: 'fetch_logins' });
    const userId = document.getElementById('loginUserFilter').value;
    const dateFrom = document.getElementById('loginDateFrom').value;
    const dateTo = document.getElementById('loginDateTo').value;
    if (userId) params.append('user_id', userId);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);

    try {
        const res = await fetch('backend/audit-log.php?' + params);
        const json = await res.json();
        if (!json.success) { tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#fca5a5;padding:24px;">⚠ ${json.message}</td></tr>`; return; }

        if (!json.data.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#7a8499;padding:30px;">No login records found.</td></tr>';
            return;
        }

        tbody.innerHTML = json.data.map((r, i) => `
            <tr>
                <td><span class="badge-gray">${i + 1}</span></td>
                <td style="font-weight:600;">${esc(r.username || 'Unknown')}</td>
                <td><span class="al-role-${r.user_role}">${esc(r.user_role || '')}</span></td>
                <td>${fmtDateTime(r.login_time)}</td>
                <td>${r.logout_time ? fmtDateTime(r.logout_time) : '—'}</td>
                <td>${sessionDuration(r.login_time, r.logout_time)}</td>
                <td style="font-family:monospace;font-size:.78rem;color:#7a8499;">${esc(r.ip_address)}</td>
            </tr>`).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#fca5a5;padding:24px;">⚠ ${e.message}</td></tr>`;
    }
}

async function loadActivity() {
    const tbody = document.getElementById('activityTableBody');
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;"><div class="spinner-border" style="color:#4ade80;"></div></td></tr>';

    const params = new URLSearchParams({ action: 'fetch', limit: 200 });
    const userId = document.getElementById('actUserFilter').value;
    const module = document.getElementById('actModuleFilter').value;
    const actionType = document.getElementById('actTypeFilter').value;
    const dateFrom = document.getElementById('actDateFrom').value;
    const dateTo = document.getElementById('actDateTo').value;
    if (userId) params.append('user_id', userId);
    if (module) params.append('module', module);
    if (actionType) params.append('action_type', actionType);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);

    try {
        const res = await fetch('backend/audit-log.php?' + params);
        const json = await res.json();
        if (!json.success) { tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#fca5a5;padding:24px;">⚠ ${json.message}</td></tr>`; return; }

        window.__activityData = json.data;

        if (!json.data.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#7a8499;padding:30px;">No activity found for these filters.</td></tr>';
            return;
        }

        tbody.innerHTML = json.data.map((r, i) => `
            <tr>
                <td><span class="badge-gray">${i + 1}</span></td>
                <td>${fmtDateTime(r.created_at)}</td>
                <td>
                    <div style="font-weight:600;">${esc(r.username || 'Unknown')}</div>
                    <div class="al-role-${r.user_role}">${esc(r.user_role || '')}</div>
                </td>
                <td><span class="badge-${(r.action_type || '').toLowerCase()}">${esc(r.action_type)}</span></td>
                <td>${esc(MODULE_LABELS[r.table_name] || r.table_name)}</td>
                <td style="color:#7a8499;">#${r.record_id}</td>
                <td><button class="al-details-btn" onclick="showDetails(${i})"><i class="bi bi-eye"></i> View</button></td>
            </tr>`).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#fca5a5;padding:24px;">⚠ ${e.message}</td></tr>`;
    }
}

function showDetails(idx) {
    const r = window.__activityData[idx];
    let html = `<p><strong>Action:</strong> ${esc(r.action_type)} on <strong>${esc(MODULE_LABELS[r.table_name] || r.table_name)}</strong> (Record #${r.record_id})</p>
                <p><strong>By:</strong> ${esc(r.username)} (${esc(r.user_role)}) at ${fmtDateTime(r.created_at)} from ${esc(r.ip_address)}</p>`;

    if (r.old_values) {
        html += `<p style="margin-top:14px;margin-bottom:4px;"><strong style="color:#f87171;">Before:</strong></p><pre>${esc(formatJson(r.old_values))}</pre>`;
    }
    if (r.new_values) {
        html += `<p style="margin-top:14px;margin-bottom:4px;"><strong style="color:#4ade80;">After:</strong></p><pre>${esc(formatJson(r.new_values))}</pre>`;
    }

    document.getElementById('detailsModalBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}

function formatJson(str) {
    try {
        return JSON.stringify(JSON.parse(str), null, 2);
    } catch (e) {
        return str;
    }
}

function resetLoginFilters() {
    document.getElementById('loginUserFilter').value = '';
    document.getElementById('loginDateFrom').value = '';
    document.getElementById('loginDateTo').value = '';
    loadLogins();
}

function resetActivityFilters() {
    document.getElementById('actUserFilter').value = '';
    document.getElementById('actModuleFilter').value = '';
    document.getElementById('actTypeFilter').value = '';
    document.getElementById('actDateFrom').value = '';
    document.getElementById('actDateTo').value = '';
    loadActivity();
}

(async function init() {
    await populateUserFilters();
    loadLogins();
    loadActivity();
})();
</script>

</body>

</html>