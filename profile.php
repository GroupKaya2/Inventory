<?php
// profile.php — Profile & Settings Page

session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$activePage = 'profile';
$isOwner    = ($_SESSION['role'] ?? 'manager') === 'owner';
$userId     = (int)$_SESSION['user_id'];

// Does the users table have a created_at column?
$hasCreatedAt = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'")->num_rows > 0;
$fields       = $hasCreatedAt ? 'id, name, email, role, created_at' : 'id, name, email, role';

// Load current user
$stmt = $conn->prepare("SELECT $fields FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!isset($me['created_at'])) $me['created_at'] = null;

// Load all users if owner
$allUsers = [];
if ($isOwner) {
    $r = $conn->query("SELECT $fields FROM users ORDER BY role DESC, name ASC");
    if ($r) while ($row = $r->fetch_assoc()) $allUsers[] = $row;
}

// Avatar initials
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($me['name'])))));
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — Dispeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        /* ── Profile Layout ── */
        .settings-layout {
            display: flex;
            gap: 20px;
            max-width: 1060px;
        }

        /* ── Left Nav ── */
        .settings-nav {
            width: 220px;
            flex-shrink: 0;
        }
        .settings-nav .nav-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 10px;
            border: none;
            background: none;
            color: #7a8499;
            font-size: .85rem;
            font-weight: 500;
            width: 100%;
            text-align: left;
            cursor: pointer;
            transition: background .15s, color .15s;
            margin-bottom: 2px;
        }
        .settings-nav .nav-btn:hover  { background: rgba(255,255,255,.06); color: #e8ecf4; }
        .settings-nav .nav-btn.active { background: rgba(232,23,93,.18); color: #fff; }
        .settings-nav .nav-btn i { width: 20px; text-align: center; }
        .nav-section-label {
            font-size: .6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: rgba(122,132,153,.5);
            padding: 12px 14px 4px;
        }

        /* ── Content Panel ── */
        .settings-content { flex: 1; }

        .settings-panel { display: none; }
        .settings-panel.active { display: block; }

        /* ── Avatar ── */
        .profile-avatar {
            width: 72px; height: 72px;
            border-radius: 16px;
            background: linear-gradient(135deg, #e8175d, #9b0d43);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Syne', sans-serif;
            font-size: 1.6rem; font-weight: 800;
            color: #fff; flex-shrink: 0;
            box-shadow: 0 6px 20px rgba(232,23,93,.35);
            position: relative;
        }
        .profile-avatar .online-dot {
            position: absolute;
            bottom: -2px; right: -2px;
            width: 16px; height: 16px;
            border-radius: 50%;
            background: #10b981;
            border: 3px solid #1c2030;
        }

        /* ── User Cards in manage users ── */
        .user-card {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 12px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .user-card .avatar-sm {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Syne', sans-serif;
            font-weight: 800; font-size: .88rem;
            color: #fff; flex-shrink: 0;
        }

        /* ── Toggle switch ── */
        .toggle-wrap { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid rgba(255,255,255,.07); }
        .toggle-wrap:last-child { border-bottom: none; }
        .toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
        .toggle-switch input { display: none; }
        .toggle-slider {
            position: absolute; inset: 0;
            background: rgba(255,255,255,.15);
            border-radius: 24px; cursor: pointer; transition: .3s;
        }
        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 18px; height: 18px;
            left: 3px; bottom: 3px;
            background: #fff; border-radius: 50%; transition: .3s;
        }
        input:checked + .toggle-slider { background: #e8175d; }
        input:checked + .toggle-slider::before { transform: translateX(20px); }

        @media (max-width: 768px) {
            .settings-layout { flex-direction: column; }
            .settings-nav { width: 100%; display: flex; flex-wrap: wrap; gap: 4px; }
            .settings-nav .nav-btn { width: auto; }
            .nav-section-label { display: none; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="app-main">

    <div class="page-header mb-4">
        <h4 style="margin:0;"><i class="bi bi-person-gear me-2"></i>Profile & Settings</h4>
        <p style="margin:4px 0 0;">Manage your account, password, and preferences</p>
    </div>

    <div class="settings-layout">

        <!-- Left Navigation -->
        <div class="settings-nav">
            <div class="nav-section-label">Account</div>
            <button class="nav-btn active" onclick="showPanel('overview', this)">
                <i class="bi bi-grid-1x2"></i> Overview
            </button>
            <button class="nav-btn" onclick="showPanel('personal', this)">
                <i class="bi bi-person"></i> Personal Info
            </button>
            <button class="nav-btn" onclick="showPanel('password', this)">
                <i class="bi bi-shield-lock"></i> Password
            </button>
            <div class="nav-section-label">Preferences</div>
            <button class="nav-btn" onclick="showPanel('notifications', this)">
                <i class="bi bi-bell"></i> Notifications
            </button>
            <button class="nav-btn" onclick="showPanel('appearance', this)">
                <i class="bi bi-palette"></i> Appearance
            </button>
            <?php if ($isOwner): ?>
            <div class="nav-section-label">Owner</div>
            <button class="nav-btn" onclick="showPanel('users', this)">
                <i class="bi bi-people"></i> Manage Users
            </button>
            <button class="nav-btn" onclick="showPanel('danger', this)">
                <i class="bi bi-exclamation-triangle" style="color:#fca5a5;"></i>
                <span style="color:#fca5a5;">Danger Zone</span>
            </button>
            <?php endif; ?>
        </div>

        <!-- Content Area -->
        <div class="settings-content">

            <!-- ── OVERVIEW ── -->
            <div class="settings-panel active" id="panel-overview">
                <div class="card p-4 mb-3">
                    <div class="d-flex align-items-center gap-4 flex-wrap">
                        <div class="profile-avatar">
                            <?= $initials ?>
                            <div class="online-dot"></div>
                        </div>
                        <div>
                            <h4 style="margin:0;"><?= htmlspecialchars($me['name']) ?></h4>
                            <p style="color:#7a8499;margin:4px 0 0;font-size:.85rem;"><?= htmlspecialchars($me['email']) ?></p>
                            <span class="<?= $isOwner ? 'badge-pink' : 'badge-gray' ?>" style="margin-top:8px;display:inline-block;">
                                <?= $isOwner ? '👑 Owner' : '🔧 Manager' ?>
                            </span>
                        </div>
                        <button class="btn-ghost ms-auto" onclick="showPanel('personal', document.querySelector('[onclick*=personal]'))">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </button>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <p class="section-title mb-3">Account Info</p>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;margin-bottom:4px;">Full Name</div>
                                <div style="font-size:.9rem;"><?= htmlspecialchars($me['name']) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;margin-bottom:4px;">Email</div>
                                <div style="font-size:.9rem;"><?= htmlspecialchars($me['email']) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;margin-bottom:4px;">Role</div>
                                <div style="font-size:.9rem;"><?= $isOwner ? '👑 Owner' : '🔧 Manager' ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#7a8499;margin-bottom:4px;">Member Since</div>
                                <div style="font-size:.9rem;">
                                    <?= $me['created_at'] ? date('F d, Y', strtotime($me['created_at'])) : 'N/A' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── PERSONAL INFO ── -->
            <div class="settings-panel" id="panel-personal">
                <div class="card p-4">
                    <p class="section-title mb-4"><i class="bi bi-person-circle me-2" style="color:#60a5fa;"></i>Personal Information</p>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-input" id="updName" value="<?= htmlspecialchars($me['name']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" id="updEmail" value="<?= htmlspecialchars($me['email']) ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-input" value="<?= $isOwner ? 'Owner' : 'Manager' ?>" readonly style="opacity:.5;cursor:not-allowed;">
                    </div>
                    <button class="btn-pink" onclick="saveProfile()">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                </div>
            </div>

            <!-- ── PASSWORD ── -->
            <div class="settings-panel" id="panel-password">
                <div class="card p-4">
                    <p class="section-title mb-4"><i class="bi bi-shield-lock me-2" style="color:#f59e0b;"></i>Change Password</p>
                    <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:.82rem;color:#fcd34d;">
                        <i class="bi bi-info-circle me-2"></i>Use at least 6 characters with numbers and symbols.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-input" id="curPass">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-input" id="newPass">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-input" id="confPass">
                    </div>
                    <button style="background:linear-gradient(135deg,#f59e0b,#d97706);border:none;color:#111;padding:10px 22px;border-radius:50px;font-weight:700;cursor:pointer;" onclick="changePassword()">
                        <i class="bi bi-shield-check me-1"></i>Update Password
                    </button>
                </div>
            </div>

            <!-- ── NOTIFICATIONS ── -->
            <div class="settings-panel" id="panel-notifications">
                <div class="card p-4">
                    <p class="section-title mb-4"><i class="bi bi-bell-fill me-2" style="color:#ef4444;"></i>Notification Preferences</p>
                    <div class="toggle-wrap">
                        <div>
                            <div style="font-weight:600;font-size:.88rem;">Low Stock Alerts</div>
                            <div style="font-size:.75rem;color:#7a8499;">Notify when parts fall below reorder threshold</div>
                        </div>
                        <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                    <div class="toggle-wrap">
                        <div>
                            <div style="font-weight:600;font-size:.88rem;">New Sale Notifications</div>
                            <div style="font-size:.75rem;color:#7a8499;">Alert when a new sale is recorded</div>
                        </div>
                        <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                    <div class="toggle-wrap">
                        <div>
                            <div style="font-weight:600;font-size:.88rem;">Reorder Suggestions</div>
                            <div style="font-size:.75rem;color:#7a8499;">Weekly smart reorder recommendations</div>
                        </div>
                        <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                    <button class="btn-pink mt-4" onclick="Swal.fire({icon:'success',title:'Saved!',timer:1200,showConfirmButton:false})">
                        <i class="bi bi-check-lg me-1"></i>Save Preferences
                    </button>
                </div>
            </div>

            <!-- ── APPEARANCE ── -->
            <div class="settings-panel" id="panel-appearance">
                <div class="card p-4">
                    <p class="section-title mb-4"><i class="bi bi-palette-fill me-2" style="color:#a78bfa;"></i>Appearance</p>
                    <div class="toggle-wrap">
                        <div>
                            <div style="font-weight:600;font-size:.88rem;">Dark Mode</div>
                            <div style="font-size:.75rem;color:#7a8499;">Use dark theme across all pages</div>
                        </div>
                        <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                    <div class="toggle-wrap">
                        <div>
                            <div style="font-weight:600;font-size:.88rem;">Show Low-Stock Badge</div>
                            <div style="font-size:.75rem;color:#7a8499;">Display low-stock count badge on sidebar</div>
                        </div>
                        <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>
                    <button style="background:linear-gradient(135deg,#a78bfa,#6d28d9);border:none;color:#fff;padding:10px 22px;border-radius:50px;font-weight:700;cursor:pointer;margin-top:20px;"
                        onclick="Swal.fire({icon:'success',title:'Saved!',timer:1200,showConfirmButton:false})">
                        <i class="bi bi-check-lg me-1"></i>Save Preferences
                    </button>
                </div>
            </div>

            <!-- ── MANAGE USERS (owner only) ── -->
            <?php if ($isOwner): ?>
            <div class="settings-panel" id="panel-users">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <p class="section-title mb-0"><i class="bi bi-people-fill me-2" style="color:#60a5fa;"></i>Team Members</p>
                    <a href="signup.php" class="btn-pink" style="font-size:.82rem;">
                        <i class="bi bi-person-plus me-1"></i>Add Manager
                    </a>
                </div>
                <?php foreach ($allUsers as $u):
                    $ini    = strtoupper(substr($u['name'], 0, 1));
                    $bgGrad = $u['role'] === 'owner'
                        ? 'linear-gradient(135deg,#e8175d,#9b0d43)'
                        : 'linear-gradient(135deg,#667eea,#764ba2)';
                ?>
                <div class="user-card">
                    <div class="avatar-sm" style="background:<?= $bgGrad ?>;"><?= $ini ?></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;font-size:.9rem;">
                            <?= htmlspecialchars($u['name']) ?>
                            <?php if ($u['id'] == $userId): ?>
                                <span class="badge-green ms-1" style="font-size:.62rem;">You</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:.75rem;color:#7a8499;"><?= htmlspecialchars($u['email']) ?></div>
                    </div>
                    <span class="<?= $u['role'] === 'owner' ? 'badge-pink' : 'badge-gray' ?>">
                        <?= $u['role'] === 'owner' ? '👑 Owner' : '🔧 Manager' ?>
                    </span>
                    <?php if ($u['id'] != $userId && $u['role'] !== 'owner'): ?>
                    <button onclick="removeUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')"
                        style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5;padding:6px 14px;border-radius:8px;font-size:.78rem;cursor:pointer;">
                        <i class="bi bi-trash me-1"></i>Remove
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ── DANGER ZONE ── -->
            <div class="settings-panel" id="panel-danger">
                <div class="card p-4" style="border-color:rgba(239,68,68,.25);">
                    <p class="section-title mb-3" style="border-color:#ef4444;color:#fca5a5;">
                        <i class="bi bi-exclamation-triangle me-2"></i>Danger Zone
                    </p>
                    <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:18px;">
                        <div style="font-weight:700;font-size:.9rem;color:#fca5a5;margin-bottom:6px;">
                            Clear All Sales History
                        </div>
                        <div style="font-size:.82rem;color:#7a8499;margin-bottom:14px;">
                            This will permanently delete all sales, sale items, and related inventory transactions. This cannot be undone.
                        </div>
                        <button onclick="clearSalesHistory()"
                            style="background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.4);color:#fca5a5;padding:9px 20px;border-radius:8px;font-size:.83rem;font-weight:700;cursor:pointer;">
                            <i class="bi bi-trash3 me-1"></i>Clear All Sales History
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- end settings-content -->
    </div><!-- end settings-layout -->

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Panel Switcher ────────────────────────────────────
function showPanel(id, btn) {
    document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.settings-nav .nav-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('panel-' + id)?.classList.add('active');
    btn?.classList.add('active');
}

// ── Save Profile ──────────────────────────────────────
async function saveProfile() {
    const name  = document.getElementById('updName').value.trim();
    const email = document.getElementById('updEmail').value.trim();
    if (!name || !email) { Swal.fire({ icon: 'warning', title: 'Required', text: 'Name and email are required.' }); return; }

    const fd = new FormData();
    fd.append('action', 'update_profile');
    fd.append('name', name);
    fd.append('email', email);

    const resp = await fetch('backend/profile.php', { method: 'POST', body: fd });
    const data = await resp.json();
    Swal.fire({ icon: data.success ? 'success' : 'error', title: data.success ? 'Saved!' : 'Error', text: data.message, timer: data.success ? 1400 : undefined, showConfirmButton: !data.success });
}

// ── Change Password ───────────────────────────────────
async function changePassword() {
    const cur  = document.getElementById('curPass').value;
    const nw   = document.getElementById('newPass').value;
    const conf = document.getElementById('confPass').value;

    if (!cur || !nw || !conf) { Swal.fire({ icon: 'warning', title: 'Fill all fields' }); return; }
    if (nw !== conf)           { Swal.fire({ icon: 'error', title: 'Passwords don\'t match' }); return; }
    if (nw.length < 6)         { Swal.fire({ icon: 'warning', title: 'Too short', text: 'At least 6 characters.' }); return; }

    const fd = new FormData();
    fd.append('action', 'change_password');
    fd.append('current', cur);
    fd.append('new', nw);

    const resp = await fetch('backend/profile.php', { method: 'POST', body: fd });
    const data = await resp.json();

    if (data.success) {
        Swal.fire({ icon: 'success', title: 'Password updated!', timer: 1400, showConfirmButton: false });
        ['curPass','newPass','confPass'].forEach(id => document.getElementById(id).value = '');
    } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
}

// ── Remove User ───────────────────────────────────────
async function removeUser(id, name) {
    const confirm = await Swal.fire({
        title: `Remove ${name}?`,
        text: 'This manager account will be permanently deleted.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Remove',
    });
    if (!confirm.isConfirmed) return;

    const fd = new FormData();
    fd.append('action', 'delete_user');
    fd.append('user_id', id);

    const resp = await fetch('backend/profile.php', { method: 'POST', body: fd });
    const data = await resp.json();

    if (data.success) {
        Swal.fire({ icon: 'success', title: 'Removed!', timer: 1200, showConfirmButton: false })
            .then(() => location.reload());
    } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message });
    }
}

// ── Clear Sales History ───────────────────────────────
async function clearSalesHistory() {
    const result = await Swal.fire({
        title: 'Clear ALL Sales History?',
        html: '<span style="color:#fca5a5;font-weight:bold;">This cannot be undone!</span><br><br>Type <b>DELETE</b> to confirm.',
        icon: 'warning',
        input: 'text',
        inputPlaceholder: 'Type DELETE',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Delete Everything',
        preConfirm: val => {
            if (val !== 'DELETE') Swal.showValidationMessage('Type DELETE exactly (uppercase)');
        }
    });
    if (!result.isConfirmed) return;
    Swal.fire({ icon: 'info', title: 'Coming Soon', text: 'Bulk delete will be available in the next update.' });
}
</script>
</body>
</html>
