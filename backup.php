<?php
session_start();
require_once __DIR__ . '/backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Owner-only page
if (($_SESSION['role'] ?? 'manager') !== 'owner') {
    header("Location: dashboard.php");
    exit();
}

$activePage = 'backup';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore — DSpeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .backup-note {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            background: rgba(96, 165, 250, .06);
            border: 1px solid rgba(96, 165, 250, .18);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 18px;
            font-size: .82rem;
            color: #93c5fd;
        }

        .backup-note i {
            font-size: 1rem;
            margin-top: 1px;
            flex-shrink: 0;
        }

        .backup-actions .btn {
            padding: 5px 11px;
            font-size: .76rem;
            border-radius: 7px;
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <main class="app-main">

        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4><i class="bi bi-cloud-arrow-down-fill me-2" style="color:#22d3ee;"></i>Database Backup &amp;
                    Restore</h4>
                <p>Create, download, and restore backups of your entire database.</p>
            </div>
            <button class="btn-pink" id="createBtn">
                <i class="bi bi-plus-lg"></i> Create Backup
            </button>
        </div>

        <div class="backup-note">
            <i class="bi bi-info-circle-fill"></i>
            <div>
                Backups are full snapshots of your database at the moment they're created. Restoring a backup
                <strong>replaces all current data</strong> with what was saved in that backup — any sales, expenses,
                or products added after that backup was made will be lost. Download backups regularly and keep a
                copy somewhere safe outside this server.
            </div>
        </div>

        <div class="card">
            <div class="card-header-pink">
                <i class="bi bi-archive me-2"></i>Available Backups
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>File Name</th>
                            <th>Date Created</th>
                            <th>Size</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="backupTableBody">
                        <tr>
                            <td colspan="5" style="text-align:center;padding:30px;">
                                <div class="spinner-border" style="color:#4ade80;"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const API = 'backend/backup.php';

        function fmtSize(bytes) {
            bytes = parseInt(bytes) || 0;
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

        function fmtDate(s) {
            const d = new Date(s.replace(' ', 'T'));
            if (isNaN(d)) return s;
            return d.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
        }

        function esc(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        async function loadBackups() {
            const tbody = document.getElementById('backupTableBody');
            try {
                const res = await fetch(`${API}?action=list`);
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Failed to load backups.');

                if (!data.backups.length) {
                    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:30px;color:#7a8499;">
                        No backups yet. Click <strong>Create Backup</strong> to make your first one.</td></tr>`;
                    return;
                }

                tbody.innerHTML = data.backups.map((b, i) => `
                    <tr>
                        <td><span class="badge-gray">${i + 1}</span></td>
                        <td style="font-family:monospace;font-size:.8rem;">${esc(b.name)}</td>
                        <td>${fmtDate(b.date)}</td>
                        <td>${fmtSize(b.size)}</td>
                        <td class="backup-actions">
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-info" onclick="downloadBackup('${esc(b.name)}')" title="Download">
                                    <i class="bi bi-download"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="restoreBackup('${esc(b.name)}')" title="Restore">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteBackup('${esc(b.name)}')" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:24px;color:#fca5a5;">
                    ⚠ ${esc(err.message)}</td></tr>`;
            }
        }

        document.getElementById('createBtn').addEventListener('click', async () => {
            const btn = document.getElementById('createBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating…';
            try {
                const res = await fetch(`${API}?action=create`);
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Failed to create backup.');
                await Swal.fire({ icon: 'success', title: 'Backup Created', text: data.file, timer: 1800, showConfirmButton: false });
                loadBackups();
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Backup Failed', text: err.message });
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-plus-lg"></i> Create Backup';
            }
        });

        function downloadBackup(name) {
            window.location.href = `${API}?action=download&file=${encodeURIComponent(name)}`;
        }

        async function restoreBackup(name) {
            const confirm = await Swal.fire({
                icon: 'warning',
                title: 'Restore this backup?',
                html: `This will <strong>replace all current data</strong> with the contents of<br><code>${esc(name)}</code><br><br>Anything added after this backup was made will be lost. This cannot be undone.`,
                showCancelButton: true,
                confirmButtonText: 'Yes, Restore',
                confirmButtonColor: '#e8175d',
            });
            if (!confirm.isConfirmed) return;

            try {
                const fd = new FormData();
                fd.append('file', name);
                const res = await fetch(`${API}?action=restore`, { method: 'POST', body: fd });
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Restore failed.');
                await Swal.fire({ icon: 'success', title: 'Restored', text: 'The database has been restored from this backup.' });
                window.location.href = 'dashboard.php';
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Restore Failed', text: err.message });
            }
        }

        async function deleteBackup(name) {
            const confirm = await Swal.fire({
                icon: 'warning',
                title: 'Delete this backup file?',
                text: name,
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#e8175d',
            });
            if (!confirm.isConfirmed) return;

            try {
                const fd = new FormData();
                fd.append('file', name);
                const res = await fetch(`${API}?action=delete`, { method: 'POST', body: fd });
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Delete failed.');
                loadBackups();
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Delete Failed', text: err.message });
            }
        }

        loadBackups();
    </script>

</body>

</html>