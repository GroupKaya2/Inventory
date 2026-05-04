<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$activePage = 'profile';
$isOwner    = ($_SESSION['role'] ?? 'manager') === 'owner';
$userId     = (int) $_SESSION['user_id'];

$hasCreatedAt = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'")->num_rows > 0;
$fields       = $hasCreatedAt ? 'id, name, email, role, created_at' : 'id, name, email, role';

$stmt = $conn->prepare("SELECT $fields FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!isset($me['created_at'])) $me['created_at'] = null;

$myEmail = $me['email'];

$allUsers = [];
if ($isOwner) {
    $r = $conn->query("SELECT $fields FROM users ORDER BY role DESC, name ASC");
    if ($r) while ($row = $r->fetch_assoc()) $allUsers[] = $row;
}

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
    <link rel="stylesheet" href="assets/css/profile.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="app-main">

    <div class="page-header mb-4">
        <h4 style="margin:0;"><i class="bi bi-person-gear me-2"></i>Profile & Settings</h4>
        <p style="margin:4px 0 0;">Manage your account, password<?= $isOwner ? ', and team members' : '' ?></p>
    </div>

    <div class="settings-layout">

        <!-- Left Nav -->
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

        <div class="settings-content">

            <!-- OVERVIEW -->
            <div class="settings-panel active" id="panel-overview">
                <div class="card p-4 mb-3">
                    <div class="d-flex align-items-center gap-4 flex-wrap">
                        <div class="profile-avatar">
                            <?= htmlspecialchars($initials) ?>
                            <div class="online-dot"></div>
                        </div>
                        <div>
                            <h4 style="margin:0;"><?= htmlspecialchars($me['name']) ?></h4>
                            <p style="color:#7a8499;margin:4px 0 0;font-size:.85rem;"><?= htmlspecialchars($myEmail) ?></p>
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
                                <div class="form-label">Full Name</div>
                                <div style="font-size:.9rem;color:#e8ecf4;"><?= htmlspecialchars($me['name']) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-label">Email</div>
                                <div style="font-size:.9rem;color:#e8ecf4;"><?= htmlspecialchars($myEmail) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-label">Role</div>
                                <div style="font-size:.9rem;color:#e8ecf4;"><?= $isOwner ? 'Owner' : 'Manager' ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-label">Member Since</div>
                                <div style="font-size:.9rem;color:#e8ecf4;">
                                    <?= $me['created_at'] ? date('F d, Y', strtotime($me['created_at'])) : 'N/A' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PERSONAL INFO -->
            <div class="settings-panel" id="panel-personal">
                <div class="card p-4">
                    <p class="section-title mb-4"><i class="bi bi-person-circle me-2" style="color:#60a5fa;"></i>Personal Information</p>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-input" id="updName" value="<?= htmlspecialchars($me['name']) ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" id="updEmail" value="<?= htmlspecialchars($myEmail) ?>">
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

            <!-- PASSWORD -->
            <div class="settings-panel" id="panel-password">
                <div class="card p-4">
                    <p class="section-title mb-4"><i class="bi bi-shield-lock me-2" style="color:#f59e0b;"></i>Change Password</p>
                    <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:.82rem;color:#fcd34d;">
                        <i class="bi bi-info-circle me-2"></i>Passwords are encrypted with bcrypt. Use at least 6 characters.
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

            <!-- MANAGE USERS (owner only) -->
            <?php if ($isOwner): ?>
            <div class="settings-panel" id="panel-users">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <p class="section-title mb-0"><i class="bi bi-people-fill me-2" style="color:#60a5fa;"></i>Team Members</p>
                    <button class="btn-pink" style="font-size:.82rem;" onclick="openAddManager()">
                        <i class="bi bi-person-plus me-1"></i>Add Manager
                    </button>
                </div>

                <div id="usersList">
                <?php foreach ($allUsers as $u):
                    $ini    = strtoupper(substr($u['name'], 0, 1));
                    $bgGrad = $u['role'] === 'owner'
                        ? 'linear-gradient(135deg,#e8175d,#9b0d43)'
                        : 'linear-gradient(135deg,#667eea,#764ba2)';
                    $uEmail = htmlspecialchars($u['email']);
                    $uName  = htmlspecialchars($u['name']);
                ?>
                <div class="user-card" id="usercard-<?= $u['id'] ?>">
                    <div class="avatar-sm" style="background:<?= $bgGrad ?>;"><?= $ini ?></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;font-size:.9rem;">
                            <?= $uName ?>
                            <?php if ($u['id'] == $userId): ?>
                                <span class="badge-green ms-1" style="font-size:.62rem;">You</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:.75rem;color:#7a8499;"><?= $uEmail ?></div>
                        <?php if (isset($u['created_at']) && $u['created_at']): ?>
                        <div style="font-size:.68rem;color:#4a5568;">Joined <?= date('M d, Y', strtotime($u['created_at'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="<?= $u['role'] === 'owner' ? 'badge-pink' : 'badge-gray' ?>">
                        <?= $u['role'] === 'owner' ? '👑 Owner' : '🔧 Manager' ?>
                    </span>
                    <div class="user-actions">
                        <?php if ($u['role'] !== 'owner' || $u['id'] == $userId): ?>
                        <button class="btn-edit-user"
                            onclick="openEditUser(<?= $u['id'] ?>, '<?= addslashes($uName) ?>', '<?= addslashes($uEmail) ?>')"
                            title="Edit email / password">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </button>
                        <?php endif; ?>
                        <?php if ($u['id'] != $userId && $u['role'] !== 'owner'): ?>
                        <button class="btn-del-user"
                            onclick="removeUser(<?= $u['id'] ?>, '<?= addslashes($uName) ?>')">
                            <i class="bi bi-trash me-1"></i>Remove
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>

            <!-- DANGER ZONE -->
            <div class="settings-panel" id="panel-danger">
                <div class="card p-4" style="border-color:rgba(239,68,68,.25);">
                    <p class="section-title mb-3" style="border-color:#ef4444;color:#fca5a5;">
                        <i class="bi bi-exclamation-triangle me-2"></i>Danger Zone
                    </p>
                    <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:18px;">
                        <div style="font-weight:700;font-size:.9rem;color:#fca5a5;margin-bottom:6px;">Clear All Sales History</div>
                        <div style="font-size:.82rem;color:#7a8499;margin-bottom:14px;">
                            Permanently deletes all sales, sale items, and related inventory transactions. Cannot be undone.
                        </div>
                        <button onclick="clearSalesHistory()"
                            style="background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.4);color:#fca5a5;padding:9px 20px;border-radius:8px;font-size:.83rem;font-weight:700;cursor:pointer;">
                            <i class="bi bi-trash3 me-1"></i>Clear All Sales History
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</main>

<?php if ($isOwner): ?>
<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editUserId">
                <div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:.82rem;color:#93c5fd;">
                    <i class="bi bi-info-circle me-2"></i>Leave a field blank to keep it unchanged.
                </div>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-input" id="editUserName" placeholder="Leave blank to keep current">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-input" id="editUserEmail" placeholder="Leave blank to keep current">
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-input" id="editUserPass" placeholder="Leave blank to keep current">
                </div>
                <div class="mb-1">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-input" id="editUserPassConf" placeholder="Confirm new password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-pink" id="saveEditUserBtn" onclick="submitEditUser()">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Manager Modal -->
<div class="modal fade" id="addManagerModal" tabindex="-1">
    <div class="modal-dialog  modal-lg modal-dark">
        <div class="modal-content modal-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Manager</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-input" id="newMgrName" placeholder="Full Name">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-input" id="newMgrEmail" placeholder="email@example.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password * <small style="color:#7a8499;">(min 6 chars)</small></label>
                    <input type="password" class="form-input" id="newMgrPass" placeholder="Password">
                </div>
                <div class="mb-1">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" class="form-input" id="newMgrPassConf" placeholder="Confirm">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-pink" id="saveNewMgrBtn" onclick="submitAddManager()">
                    <i class="bi bi-person-check me-1"></i>Create Manager
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showPanel(id, btn) {
    document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.settings-nav .nav-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('panel-' + id)?.classList.add('active');
    btn?.classList.add('active');
}

async function saveProfile() {
    const name  = document.getElementById('updName').value.trim();
    const email = document.getElementById('updEmail').value.trim();
    if (!name || !email) { Swal.fire({ icon:'warning', title:'Required', text:'Name and email are required.' }); return; }

    const fd = new FormData();
    fd.append('action', 'update_profile');
    fd.append('name',   name);
    fd.append('email',  email);

    const resp = await fetch('backend/profile.php', { method:'POST', body:fd });
    const data = await resp.json();
    Swal.fire({
        icon: data.success ? 'success' : 'error',
        title: data.success ? 'Saved!' : 'Error',
        text: data.message,
        timer: data.success ? 1400 : undefined,
        showConfirmButton: !data.success,
    });
}

async function changePassword() {
    const cur  = document.getElementById('curPass').value;
    const nw   = document.getElementById('newPass').value;
    const conf = document.getElementById('confPass').value;

    if (!cur || !nw || !conf) { Swal.fire({ icon:'warning', title:'Fill all fields' }); return; }
    if (nw !== conf) { Swal.fire({ icon:'error', title:"Passwords don't match" }); return; }
    if (nw.length < 6) { Swal.fire({ icon:'warning', title:'Too short', text:'At least 6 characters.' }); return; }

    const fd = new FormData();
    fd.append('action',  'change_password');
    fd.append('current', cur);
    fd.append('new',     nw);

    const resp = await fetch('backend/profile.php', { method:'POST', body:fd });
    const data = await resp.json();

    if (data.success) {
        Swal.fire({ icon:'success', title:'Password updated!', timer:1400, showConfirmButton:false });
        ['curPass','newPass','confPass'].forEach(id => document.getElementById(id).value = '');
    } else {
        Swal.fire({ icon:'error', title:'Error', text:data.message });
    }
}

function openEditUser(id, name, email) {
    document.getElementById('editUserId').value           = id;
    document.getElementById('editUserName').value         = '';
    document.getElementById('editUserEmail').value        = '';
    document.getElementById('editUserPass').value         = '';
    document.getElementById('editUserPassConf').value     = '';
    document.getElementById('editUserName').placeholder   = name;
    document.getElementById('editUserEmail').placeholder  = email;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

async function submitEditUser() {
    const id       = document.getElementById('editUserId').value;
    const newName  = document.getElementById('editUserName').value.trim();
    const newEmail = document.getElementById('editUserEmail').value.trim();
    const newPass  = document.getElementById('editUserPass').value;
    const confPass = document.getElementById('editUserPassConf').value;

    if (!newName && !newEmail && !newPass) {
        Swal.fire({ icon:'warning', title:'Nothing to update', text:'Fill at least one field.' }); return;
    }
    if (newPass && newPass !== confPass) { Swal.fire({ icon:'error', title:"Passwords don't match" }); return; }
    if (newPass && newPass.length < 6)   { Swal.fire({ icon:'warning', title:'Too short', text:'Min 6 chars.' }); return; }

    const btn = document.getElementById('saveEditUserBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving…';

    const fd = new FormData();
    fd.append('action',  'update_user');
    fd.append('user_id', id);
    if (newName)  fd.append('new_name',     newName);
    if (newEmail) fd.append('new_email',    newEmail);
    if (newPass)  fd.append('new_password', newPass);

    const resp = await fetch('backend/profile.php', { method:'POST', body:fd });
    const data = await resp.json();

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Changes';

    if (data.success) {
        bootstrap.Modal.getInstance(document.getElementById('editUserModal'))?.hide();
        Swal.fire({ icon:'success', title:'Updated!', timer:1400, showConfirmButton:false })
            .then(() => location.reload());
    } else {
        Swal.fire({ icon:'error', title:'Error', text:data.message });
    }
}

function openAddManager() {
    ['newMgrName','newMgrEmail','newMgrPass','newMgrPassConf'].forEach(id => {
        document.getElementById(id).value = '';
    });
    new bootstrap.Modal(document.getElementById('addManagerModal')).show();
}

async function submitAddManager() {
    const name  = document.getElementById('newMgrName').value.trim();
    const email = document.getElementById('newMgrEmail').value.trim();
    const pass  = document.getElementById('newMgrPass').value;
    const conf  = document.getElementById('newMgrPassConf').value;

    if (!name || !email || !pass || !conf) { Swal.fire({ icon:'warning', title:'Fill all fields' }); return; }
    if (pass !== conf) { Swal.fire({ icon:'error', title:"Passwords don't match" }); return; }
    if (pass.length < 6) { Swal.fire({ icon:'warning', title:'Too short', text:'Min 6 chars.' }); return; }

    const btn = document.getElementById('saveNewMgrBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Creating…';

    const fd = new FormData();
    fd.append('name',     name);
    fd.append('email',    email);
    fd.append('password', pass);

    const resp = await fetch('backend/register.php', { method:'POST', body:fd });
    if (resp.redirected || resp.ok) {
        bootstrap.Modal.getInstance(document.getElementById('addManagerModal'))?.hide();
        Swal.fire({ icon:'success', title:'Manager created!', timer:1400, showConfirmButton:false })
            .then(() => location.reload());
    } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-person-check me-1"></i>Create Manager';
        Swal.fire({ icon:'error', title:'Error', text:'Could not create manager.' });
    }
}

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
    fd.append('action',  'delete_user');
    fd.append('user_id', id);

    const resp = await fetch('backend/profile.php', { method:'POST', body:fd });
    const data = await resp.json();

    if (data.success) {
        Swal.fire({ icon:'success', title:'Removed!', timer:1200, showConfirmButton:false })
            .then(() => location.reload());
    } else {
        Swal.fire({ icon:'error', title:'Error', text:data.message });
    }
}

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
    Swal.fire({ icon:'info', title:'Coming Soon', text:'Bulk delete will be available in the next update.' });
}
</script>
</body>
</html>
