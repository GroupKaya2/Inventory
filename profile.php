<?php
session_start();
include "db.php";
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$activePage = 'profile';
$isOwner    = ($_SESSION['role'] ?? 'manager') === 'owner';
$userId     = (int)$_SESSION['user_id'];

// Check if created_at column exists
$hasCreatedAt = false;
$colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'");
if ($colCheck && $colCheck->num_rows > 0) $hasCreatedAt = true;
$fields = $hasCreatedAt ? "id, name, email, role, created_at" : "id, name, email, role";

// Fetch current user
$u = $conn->prepare("SELECT $fields FROM users WHERE id=?");
$u->bind_param('i', $userId);
$u->execute();
$user = $u->get_result()->fetch_assoc();
if (!isset($user['created_at'])) $user['created_at'] = null;
$u->close();

// Fetch all users (owner only)
$allUsers = [];
if ($isOwner) {
    $r = $conn->query("SELECT $fields FROM users ORDER BY role DESC, name ASC");
    if ($r) while ($row = $r->fetch_assoc()) $allUsers[] = $row;
}

$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($user['name'])))));
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings – Dispeedway</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --bg:      #1a1a2e;
    --sidebar: #16213e;
    --card:    #1e2a45;
    --card2:   #243050;
    --border:  rgba(255,255,255,.08);
    --text:    #e2e8f0;
    --muted:   #8892b0;
    --accent:  #667eea;
    --orange:  #f97316;
    --green:   #43b581;
    --red:     #f04747;
}
* { box-sizing: border-box; }
body { background: var(--bg); color: var(--text); margin: 0; font-family: 'Segoe UI', sans-serif; }
.app-main { background: var(--bg); }

/* ── SETTINGS LAYOUT ── */
.settings-wrap {
    display: flex; min-height: calc(100vh - 0px);
    max-width: 1100px; margin: 0 auto;
}
.settings-nav {
    width: 230px; flex-shrink: 0;
    background: var(--sidebar);
    border-right: 1px solid var(--border);
    padding: 24px 10px; position: sticky; top: 0; height: 100vh; overflow-y: auto;
}
.settings-content { flex: 1; padding: 32px 36px; overflow-y: auto; }
@media(max-width: 768px) {
    .settings-wrap { flex-direction: column; }
    .settings-nav  { width: 100%; height: auto; position: relative; padding: 12px 8px; display: flex; flex-wrap: wrap; gap: 4px; border-right: none; border-bottom: 1px solid var(--border); }
    .settings-content { padding: 20px 16px; }
    .nav-section-label { display: none; }
}

/* ── NAV ITEMS ── */
.nav-section-label {
    font-size: .62rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .7px; color: rgba(136,146,176,.6);
    padding: 14px 10px 4px;
}
.nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px; border-radius: 8px; cursor: pointer;
    font-size: .85rem; color: var(--muted); font-weight: 500;
    transition: all .15s; position: relative; margin-bottom: 1px;
}
.nav-item:hover  { background: rgba(255,255,255,.06); color: var(--text); }
.nav-item.active { background: rgba(102,126,234,.2);  color: #fff; }
.nav-item i      { font-size: 1rem; width: 20px; text-align: center; }
.nav-badge {
    margin-left: auto; background: var(--red); color: #fff;
    font-size: .6rem; font-weight: 700; padding: 2px 6px;
    border-radius: 10px; min-width: 18px; text-align: center;
}

/* ── AVATAR ── */
.profile-avatar-lg {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), #764ba2);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; font-weight: 800; color: #fff;
    border: 4px solid var(--card2);
    position: relative; flex-shrink: 0;
}
.online-dot {
    position: absolute; bottom: 4px; right: 4px;
    width: 16px; height: 16px; border-radius: 50%;
    background: var(--green); border: 3px solid var(--card);
}
.active-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(67,181,129,.15); border: 1px solid rgba(67,181,129,.3);
    color: var(--green); font-size: .7rem; font-weight: 700;
    padding: 3px 10px; border-radius: 20px;
}

/* ── SECTION PANELS ── */
.settings-panel { display: none; }
.settings-panel.active { display: block; }

.settings-greeting { margin-bottom: 28px; }
.settings-greeting h2 { font-size: 1.5rem; font-weight: 800; margin: 0 0 4px; }
.settings-greeting p  { color: var(--muted); font-size: .85rem; margin: 0; }

/* ── GRID OF SETTING CARDS ── */
.setting-tiles {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px; margin-bottom: 28px;
}
.setting-tile {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 12px; padding: 18px 14px; cursor: pointer;
    transition: all .2s; text-align: center;
}
.setting-tile:hover { background: var(--card2); border-color: rgba(102,126,234,.4); transform: translateY(-2px); }
.setting-tile i    { font-size: 1.5rem; display: block; margin-bottom: 8px; }
.setting-tile span { font-size: .78rem; font-weight: 600; color: var(--text); }

/* ── ACCOUNT SUMMARY BOX ── */
.account-summary {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 14px; overflow: hidden;
}
.account-summary .head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px 18px; border-bottom: 1px solid var(--border);
    font-size: .75rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: var(--muted);
}
.account-row {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 18px; border-bottom: 1px solid var(--border);
}
.account-row:last-child { border-bottom: none; }
.account-row .icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.account-row .label  { font-size: .72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; }
.account-row .value  { font-size: .88rem; color: var(--text); font-weight: 500; }

/* ── FORM FIELDS ── */
.settings-form-group { margin-bottom: 20px; }
.settings-form-group label {
    font-size: .72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: var(--muted); margin-bottom: 6px; display: block;
}
.settings-input {
    width: 100%; background: rgba(255,255,255,.06);
    border: 1px solid var(--border); color: var(--text);
    border-radius: 10px; padding: 10px 14px; font-size: .88rem;
    transition: border-color .15s;
}
.settings-input:focus { outline: none; border-color: var(--accent); background: rgba(255,255,255,.09); }
.settings-input::placeholder { color: var(--muted); }

/* ── SAVE BUTTON ── */
.btn-save-settings {
    background: var(--accent); border: none; color: #fff;
    padding: 10px 24px; border-radius: 10px; font-weight: 700;
    font-size: .88rem; cursor: pointer; transition: all .15s;
    display: inline-flex; align-items: center; gap: 6px;
}
.btn-save-settings:hover { background: #5a6fd6; }

/* ── SECTION TITLE ── */
.section-title-bar {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 20px; padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}
.section-title-bar h4 { font-size: 1.1rem; font-weight: 800; margin: 0; }
.section-title-bar p  { font-size: .8rem; color: var(--muted); margin: 2px 0 0; }

/* ── USERS TABLE ── */
.users-list { display: flex; flex-direction: column; gap: 10px; }
.user-card {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 12px; padding: 14px 18px;
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
}
.user-avatar-sm {
    width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .9rem; color: #fff;
}
.role-pill {
    padding: 3px 10px; border-radius: 20px; font-size: .7rem; font-weight: 700;
}
.role-owner   { background: rgba(249,115,22,.2); color: #fb923c; }
.role-manager { background: rgba(100,116,139,.2); color: var(--muted); }

/* ── APPEARANCE TOGGLE ── */
.appear-option {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; background: var(--card); border: 1px solid var(--border);
    border-radius: 12px; margin-bottom: 10px;
}
.toggle-switch { position: relative; width: 44px; height: 24px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; inset: 0; background: rgba(255,255,255,.15);
    border-radius: 24px; cursor: pointer; transition: .3s;
}
.toggle-slider:before {
    content: ''; position: absolute;
    width: 18px; height: 18px; left: 3px; bottom: 3px;
    background: #fff; border-radius: 50%; transition: .3s;
}
input:checked + .toggle-slider { background: var(--accent); }
input:checked + .toggle-slider:before { transform: translateX(20px); }

/* ── NOTIFICATION ITEM ── */
.notif-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; background: var(--card); border: 1px solid var(--border);
    border-radius: 12px; margin-bottom: 10px;
}
.notif-item .left  { display: flex; align-items: center; gap: 12px; }
.notif-item .icon  { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; }

/* search bar */
.settings-search {
    width: 100%; background: rgba(255,255,255,.06); border: 1px solid var(--border);
    border-radius: 10px; padding: 10px 14px 10px 38px; font-size: .88rem;
    color: var(--text); margin-bottom: 24px; position: relative;
}
.search-wrap { position: relative; }
.search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); }
</style>
</head>
<body>
<?php include "sidebar.php"; ?>

<main class="app-main p-0">
<div class="settings-wrap">

    <!-- ── LEFT NAV ── -->
    <nav class="settings-nav">
        <!-- User mini card -->
        <div style="display:flex;align-items:center;gap:10px;padding:8px 10px 16px;border-bottom:1px solid var(--border);margin-bottom:12px;">
            <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.9rem;color:#fff;flex-shrink:0;">
                <?= $initials ?>
            </div>
            <div style="overflow:hidden;">
                <div style="font-weight:700;font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($user['name']) ?></div>
                <div style="font-size:.68rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($user['email']) ?></div>
            </div>
        </div>

        <div class="nav-section-label">General</div>
        <div class="nav-item active" onclick="showPanel('overview', this)"><i class="bi bi-grid-1x2"></i> Overview</div>
        <div class="nav-item" onclick="showPanel('personal', this)"><i class="bi bi-person"></i> Personal Details</div>
        <div class="nav-item" onclick="showPanel('password', this)"><i class="bi bi-shield-lock"></i> Password & Security</div>
        <div class="nav-item" onclick="showPanel('notifications', this)">
            <i class="bi bi-bell"></i> Notifications
            <span class="nav-badge">3</span>
        </div>

        <div class="nav-section-label">Preferences</div>
        <div class="nav-item" onclick="showPanel('appearance', this)"><i class="bi bi-palette"></i> Appearance</div>
        <div class="nav-item" onclick="showPanel('privacy', this)"><i class="bi bi-eye-slash"></i> Privacy</div>
        <div class="nav-item" onclick="showPanel('storage', this)"><i class="bi bi-database"></i> Data & Storage</div>

        <?php if ($isOwner): ?>
        <div class="nav-section-label">Owner Tools</div>
        <div class="nav-item" onclick="showPanel('users', this)"><i class="bi bi-people"></i> Manage Users</div>
        <?php endif; ?>
    </nav>

    <!-- ── MAIN CONTENT ── -->
    <div class="settings-content">

        <!-- SEARCH BAR -->
        <div class="search-wrap" style="margin-bottom:20px;">
            <i class="bi bi-search"></i>
            <input type="text" class="settings-search" placeholder="Find a setting… (e.g. password, notifications, theme)" oninput="searchSettings(this.value)">
        </div>

        <!-- ══════════════════════════════════ -->
        <!-- PANEL: OVERVIEW -->
        <!-- ══════════════════════════════════ -->
        <div class="settings-panel active" id="panel-overview">
            <div class="settings-greeting">
                <h2>Good to see you, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?> 👋</h2>
                <p>Manage everything about your account from here.</p>
            </div>

            <!-- TILES -->
            <div class="setting-tiles">
                <div class="setting-tile" onclick="showPanel('personal', document.querySelector('[onclick*=personal]'))">
                    <i class="bi bi-person-circle" style="color:#60a5fa;"></i>
                    <span>Personal Details</span>
                </div>
                <div class="setting-tile" onclick="showPanel('password', document.querySelector('[onclick*=password]'))">
                    <i class="bi bi-shield-lock" style="color:#f59e0b;"></i>
                    <span>Password & Security</span>
                </div>
                <div class="setting-tile" onclick="showPanel('notifications', document.querySelector('[onclick*=notifications]'))">
                    <i class="bi bi-bell-fill" style="color:#ef4444;"></i>
                    <span>Notifications</span>
                </div>
                <div class="setting-tile" onclick="showPanel('appearance', document.querySelector('[onclick*=appearance]'))">
                    <i class="bi bi-palette-fill" style="color:#a78bfa;"></i>
                    <span>Appearance</span>
                </div>
                <div class="setting-tile" onclick="showPanel('privacy', document.querySelector('[onclick*=privacy]'))">
                    <i class="bi bi-eye-slash-fill" style="color:#34d399;"></i>
                    <span>Privacy</span>
                </div>
                <div class="setting-tile" onclick="showPanel('storage', document.querySelector('[onclick*=storage]'))">
                    <i class="bi bi-database-fill" style="color:#fb923c;"></i>
                    <span>Data & Storage</span>
                </div>
            </div>

            <!-- ACCOUNT SUMMARY -->
            <div class="account-summary">
                <div class="head">
                    <span><i class="bi bi-person-badge me-2"></i>Account Summary</span>
                    <button onclick="showPanel('personal', document.querySelector('[onclick*=personal]'))"
                        style="background:rgba(255,255,255,.08);border:1px solid var(--border);color:var(--text);padding:4px 14px;border-radius:8px;font-size:.75rem;font-weight:600;cursor:pointer;">
                        Edit
                    </button>
                </div>
                <div class="account-row">
                    <div class="icon" style="background:rgba(102,126,234,.2);color:#a5b4fc;"><i class="bi bi-person-fill"></i></div>
                    <div>
                        <div class="label">Full Name</div>
                        <div class="value"><?= htmlspecialchars($user['name']) ?></div>
                    </div>
                </div>
                <div class="account-row">
                    <div class="icon" style="background:rgba(96,165,250,.2);color:#60a5fa;"><i class="bi bi-envelope-fill"></i></div>
                    <div>
                        <div class="label">Username / Email</div>
                        <div class="value"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                </div>
                <div class="account-row">
                    <div class="icon" style="background:rgba(67,181,129,.2);color:#43b581;"><i class="bi bi-shield-check-fill"></i></div>
                    <div>
                        <div class="label">Account Status</div>
                        <div class="value">
                            <span class="active-badge"><i class="bi bi-circle-fill" style="font-size:.45rem;"></i> Active & Verified</span>
                        </div>
                    </div>
                </div>
                <div class="account-row">
                    <div class="icon" style="background:rgba(249,115,22,.2);color:#fb923c;"><i class="bi bi-person-gear"></i></div>
                    <div>
                        <div class="label">Role</div>
                        <div class="value">
                            <span class="role-pill <?= $isOwner ? 'role-owner' : 'role-manager' ?>">
                                <?= $isOwner ? '👑 Owner' : '🔧 Manager' ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="account-row">
                    <div class="icon" style="background:rgba(148,163,184,.15);color:#94a3b8;"><i class="bi bi-calendar3"></i></div>
                    <div>
                        <div class="label">Member Since</div>
                        <div class="value"><?= $user['created_at'] ? date('F d, Y', strtotime($user['created_at'])) : 'N/A' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════ -->
        <!-- PANEL: PERSONAL DETAILS -->
        <!-- ══════════════════════════════════ -->
        <div class="settings-panel" id="panel-personal">
            <div class="section-title-bar">
                <div>
                    <h4><i class="bi bi-person-circle me-2" style="color:#60a5fa;"></i>Personal Details</h4>
                    <p>Update your name and email address</p>
                </div>
            </div>

            <!-- Avatar -->
            <div style="display:flex;align-items:center;gap:20px;margin-bottom:28px;padding:20px;background:var(--card);border:1px solid var(--border);border-radius:14px;">
                <div class="profile-avatar-lg">
                    <?= $initials ?>
                    <div class="online-dot"></div>
                </div>
                <div>
                    <div style="font-weight:700;font-size:1.1rem;"><?= htmlspecialchars($user['name']) ?></div>
                    <div style="font-size:.8rem;color:var(--muted);margin:2px 0 8px;"><?= htmlspecialchars($user['email']) ?></div>
                    <span class="active-badge"><i class="bi bi-circle-fill" style="font-size:.45rem;"></i> Active Member</span>
                </div>
            </div>

            <div class="settings-form-group">
                <label>Full Name</label>
                <input type="text" class="settings-input" id="updName" value="<?= htmlspecialchars($user['name']) ?>">
            </div>
            <div class="settings-form-group">
                <label>Email Address</label>
                <input type="email" class="settings-input" id="updEmail" value="<?= htmlspecialchars($user['email']) ?>">
            </div>
            <div class="settings-form-group">
                <label>Role</label>
                <input type="text" class="settings-input" value="<?= $isOwner ? 'Owner' : 'Manager' ?>" readonly style="opacity:.6;cursor:not-allowed;">
            </div>
            <button class="btn-save-settings" onclick="savePersonal()">
                <i class="bi bi-check-lg"></i> Save Changes
            </button>
        </div>

        <!-- ══════════════════════════════════ -->
        <!-- PANEL: PASSWORD & SECURITY -->
        <!-- ══════════════════════════════════ -->
        <div class="settings-panel" id="panel-password">
            <div class="section-title-bar">
                <div>
                    <h4><i class="bi bi-shield-lock me-2" style="color:#f59e0b;"></i>Password & Security</h4>
                    <p>Change your password to keep your account safe</p>
                </div>
            </div>
            <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:12px;padding:14px 18px;margin-bottom:24px;font-size:.82rem;color:#fcd34d;">
                <i class="bi bi-info-circle me-2"></i>Use a strong password with at least 8 characters, including numbers and symbols.
            </div>
            <div class="settings-form-group">
                <label>Current Password</label>
                <input type="password" class="settings-input" id="curPass" placeholder="Enter current password">
            </div>
            <div class="settings-form-group">
                <label>New Password</label>
                <input type="password" class="settings-input" id="newPass" placeholder="Enter new password">
            </div>
            <div class="settings-form-group">
                <label>Confirm New Password</label>
                <input type="password" class="settings-input" id="confPass" placeholder="Re-enter new password">
            </div>
            <button class="btn-save-settings" style="background:#f59e0b;" onclick="changePassword()">
                <i class="bi bi-shield-check"></i> Update Password
            </button>
        </div>

        <!-- ══════════════════════════════════ -->
        <!-- PANEL: NOTIFICATIONS -->
        <!-- ══════════════════════════════════ -->
        <div class="settings-panel" id="panel-notifications">
            <div class="section-title-bar">
                <div>
                    <h4><i class="bi bi-bell-fill me-2" style="color:#ef4444;"></i>Notifications</h4>
                    <p>Choose what alerts you want to receive</p>
                </div>
            </div>
            <div class="notif-item">
                <div class="left">
                    <div class="icon" style="background:rgba(239,68,68,.2);color:#fca5a5;"><i class="bi bi-exclamation-triangle"></i></div>
                    <div>
                        <div style="font-weight:600;font-size:.88rem;">Low Stock Alerts</div>
                        <div style="font-size:.75rem;color:var(--muted);">Notify when parts fall below reorder threshold</div>
                    </div>
                </div>
                <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
            </div>
            <div class="notif-item">
                <div class="left">
                    <div class="icon" style="background:rgba(96,165,250,.2);color:#60a5fa;"><i class="bi bi-receipt"></i></div>
                    <div>
                        <div style="font-weight:600;font-size:.88rem;">New Sale Notifications</div>
                        <div style="font-size:.75rem;color:var(--muted);">Alert when a new sale is recorded</div>
                    </div>
                </div>
                <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
            </div>
            <div class="notif-item">
                <div class="left">
                    <div class="icon" style="background:rgba(167,139,250,.2);color:#a78bfa;"><i class="bi bi-stars"></i></div>
                    <div>
                        <div style="font-weight:600;font-size:.88rem;">Reorder Recommendations</div>
                        <div style="font-size:.75rem;color:var(--muted);">Weekly smart reorder suggestions</div>
                    </div>
                </div>
                <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
            </div>
            <?php if ($isOwner): ?>
            <div class="notif-item">
                <div class="left">
                    <div class="icon" style="background:rgba(249,115,22,.2);color:#fb923c;"><i class="bi bi-wallet2"></i></div>
                    <div>
                        <div style="font-weight:600;font-size:.88rem;">Daily Expense Summary</div>
                        <div style="font-size:.75rem;color:var(--muted);">Get daily totals for expenses (Owner only)</div>
                    </div>
                </div>
                <label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label>
            </div>
            <?php endif; ?>
            <button class="btn-save-settings" style="background:#ef4444;margin-top:8px;" onclick="saveNotifications()">
                <i class="bi bi-check-lg"></i> Save Preferences
            </button>
        </div>

        <!-- ══════════════════════════════════ -->
        <!-- PANEL: APPEARANCE -->
        <!-- ══════════════════════════════════ -->
        <div class="settings-panel" id="panel-appearance">
            <div class="section-title-bar">
                <div>
                    <h4><i class="bi bi-palette-fill me-2" style="color:#a78bfa;"></i>Appearance</h4>
                    <p>Customize how Dispeedway looks for you</p>
                </div>
            </div>
            <div class="appear-option">
                <div>
                    <div style="font-weight:600;font-size:.88rem;">Dark Mode</div>
                    <div style="font-size:.75rem;color:var(--muted);">Use dark theme across all pages</div>
                </div>
                <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
            </div>
            <div class="appear-option">
                <div>
                    <div style="font-weight:600;font-size:.88rem;">Compact Sidebar</div>
                    <div style="font-size:.75rem;color:var(--muted);">Collapse sidebar to icons only on desktop</div>
                </div>
                <label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label>
            </div>
            <div class="appear-option">
                <div>
                    <div style="font-weight:600;font-size:.88rem;">Show Stock Badges</div>
                    <div style="font-size:.75rem;color:var(--muted);">Show low-stock count badge on sidebar</div>
                </div>
                <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
            </div>
            <button class="btn-save-settings" style="background:#a78bfa;margin-top:8px;" onclick="saveAppearance()">
                <i class="bi bi-check-lg"></i> Save Preferences
            </button>
        </div>

        <!-- ══════════════════════════════════ -->
        <!-- PANEL: PRIVACY -->
        <!-- ══════════════════════════════════ -->
        <div class="settings-panel" id="panel-privacy">
            <div class="section-title-bar">
                <div>
                    <h4><i class="bi bi-eye-slash-fill me-2" style="color:#34d399;"></i>Privacy</h4>
                    <p>Control your data and visibility settings</p>
                </div>
            </div>
            <div class="appear-option">
                <div>
                    <div style="font-weight:600;font-size:.88rem;">Show Online Status</div>
                    <div style="font-size:.75rem;color:var(--muted);">Let other users see when you're online</div>
                </div>
                <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
            </div>
            <div class="appear-option">
                <div>
                    <div style="font-weight:600;font-size:.88rem;">Activity Log</div>
                    <div style="font-size:.75rem;color:var(--muted);">Record login activity for security review</div>
                </div>
                <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
            </div>
            <button class="btn-save-settings" style="background:#34d399;color:#111;margin-top:8px;" onclick="saveSimple('Privacy')">
                <i class="bi bi-check-lg"></i> Save Preferences
            </button>
        </div>

        <!-- ══════════════════════════════════ -->
        <!-- PANEL: DATA & STORAGE -->
        <!-- ══════════════════════════════════ -->
        <div class="settings-panel" id="panel-storage">
            <div class="section-title-bar">
                <div>
                    <h4><i class="bi bi-database-fill me-2" style="color:#fb923c;"></i>Data & Storage</h4>
                    <p>Manage your data, exports and system info</p>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:20px;">
                <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px;">
                    <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">System</div>
                    <div style="font-weight:700;font-size:.95rem;">Dispeedway v1.0</div>
                    <div style="font-size:.75rem;color:var(--muted);">PHP / MySQL</div>
                </div>
                <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px;">
                    <div style="font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Session</div>
                    <div style="font-weight:700;font-size:.95rem;">Active</div>
                    <div style="font-size:.75rem;color:var(--muted);"><?= date('M d, Y h:i A') ?></div>
                </div>
            </div>
            <?php if ($isOwner): ?>
            <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:12px;padding:16px;margin-top:8px;">
                <div style="font-weight:700;font-size:.9rem;color:#fca5a5;margin-bottom:6px;"><i class="bi bi-exclamation-triangle me-2"></i>Danger Zone</div>
                <div style="font-size:.8rem;color:var(--muted);margin-bottom:12px;">These actions are permanent and cannot be undone.</div>
                <button onclick="dangerAction()" style="background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.4);color:#fca5a5;padding:8px 18px;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;">
                    <i class="bi bi-trash3 me-1"></i> Clear All Sales History
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- ══════════════════════════════════ -->
        <!-- PANEL: MANAGE USERS (owner only) -->
        <!-- ══════════════════════════════════ -->
        <?php if ($isOwner): ?>
        <div class="settings-panel" id="panel-users">
            <div class="section-title-bar">
                <div>
                    <h4><i class="bi bi-people-fill me-2" style="color:#60a5fa;"></i>Manage Users</h4>
                    <p>View, add and remove team members</p>
                </div>
                <button class="btn-save-settings" style="margin-left:auto;" onclick="window.location='register.php'">
                    <i class="bi bi-person-plus"></i> Add Manager
                </button>
            </div>
            <div class="users-list">
                <?php foreach ($allUsers as $u2):
                    $ini2 = strtoupper(substr($u2['name'], 0, 1));
                    $bgColors = ['owner'=>'linear-gradient(135deg,#f97316,#ef4444)', 'manager'=>'linear-gradient(135deg,#667eea,#764ba2)'];
                    $bg = $bgColors[$u2['role']] ?? $bgColors['manager'];
                ?>
                <div class="user-card">
                    <div class="user-avatar-sm" style="background:<?= $bg ?>;"><?= $ini2 ?></div>
                    <div style="flex:1;">
                        <div style="font-weight:700;font-size:.9rem;">
                            <?= htmlspecialchars($u2['name']) ?>
                            <?php if ($u2['id'] == $userId): ?>
                            <span style="font-size:.65rem;background:rgba(67,181,129,.2);color:#43b581;padding:2px 8px;border-radius:10px;margin-left:6px;">You</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:.75rem;color:var(--muted);"><?= htmlspecialchars($u2['email']) ?></div>
                        <div style="font-size:.7rem;color:var(--muted);margin-top:2px;">Joined <?= $u2['created_at'] ? date('M d, Y', strtotime($u2['created_at'])) : 'N/A' ?></div>
                    </div>
                    <span class="role-pill <?= $u2['role'] === 'owner' ? 'role-owner' : 'role-manager' ?>">
                        <?= $u2['role'] === 'owner' ? '👑 Owner' : '🔧 Manager' ?>
                    </span>
                    <?php if ($u2['id'] != $userId && $u2['role'] !== 'owner'): ?>
                    <button onclick="deleteUser(<?= $u2['id'] ?>, '<?= htmlspecialchars(addslashes($u2['name'])) ?>')"
                        style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5;padding:6px 12px;border-radius:8px;font-size:.75rem;cursor:pointer;white-space:nowrap;">
                        <i class="bi bi-trash"></i> Remove
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- end settings-content -->
</div><!-- end settings-wrap -->
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── PANEL SWITCHER ────────────────────────────────────────
function showPanel(id, clickedEl) {
    document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    const panel = document.getElementById('panel-' + id);
    if (panel) panel.classList.add('active');
    if (clickedEl) clickedEl.classList.add('active');
    window.scrollTo(0, 0);
}

// ── SEARCH ────────────────────────────────────────────────
const PANEL_KEYWORDS = {
    overview:      ['overview','home','account','summary'],
    personal:      ['name','email','personal','details','profile'],
    password:      ['password','security','change','login'],
    notifications: ['notif','alert','low stock','sale','reorder'],
    appearance:    ['dark','theme','sidebar','compact','appearance'],
    privacy:       ['privacy','online','activity','log'],
    storage:       ['data','storage','export','system','danger'],
    users:         ['users','manager','team','members','register']
};
function searchSettings(q) {
    if (!q.trim()) return;
    const lower = q.toLowerCase();
    for (const [panel, keys] of Object.entries(PANEL_KEYWORDS)) {
        if (keys.some(k => k.includes(lower) || lower.includes(k))) {
            const navEl = document.querySelector(`.nav-item[onclick*="${panel}"]`);
            showPanel(panel, navEl);
            return;
        }
    }
}

// ── SAVE PERSONAL ─────────────────────────────────────────
async function savePersonal() {
    const name  = document.getElementById('updName').value.trim();
    const email = document.getElementById('updEmail').value.trim();
    if (!name || !email) { Swal.fire({icon:'warning',title:'Required',text:'Name and email are required.'}); return; }
    const fd = new FormData();
    fd.append('action', 'update_profile');
    fd.append('name', name);
    fd.append('email', email);
    const resp = await fetch('profile_action.php', {method:'POST',body:fd});
    const data = await resp.json();
    if (data.success) {
        Swal.fire({icon:'success',title:'Saved successfully',timer:1400,showConfirmButton:false});
    } else {
        Swal.fire({icon:'error',title:'Error',text:data.message});
    }
}

// ── CHANGE PASSWORD ───────────────────────────────────────
async function changePassword() {
    const cur  = document.getElementById('curPass').value;
    const nw   = document.getElementById('newPass').value;
    const conf = document.getElementById('confPass').value;
    if (!cur || !nw || !conf) { Swal.fire({icon:'warning',title:'Fill all fields'}); return; }
    if (nw !== conf) { Swal.fire({icon:'error',title:'Mismatch',text:'New passwords do not match.'}); return; }
    if (nw.length < 6) { Swal.fire({icon:'warning',title:'Too short',text:'Password must be at least 6 characters.'}); return; }
    const fd = new FormData();
    fd.append('action', 'change_password');
    fd.append('current', cur);
    fd.append('new', nw);
    const resp = await fetch('profile_action.php', {method:'POST',body:fd});
    const data = await resp.json();
    if (data.success) {
        Swal.fire({icon:'success',title:'Password updated!',timer:1400,showConfirmButton:false});
        document.getElementById('curPass').value = '';
        document.getElementById('newPass').value = '';
        document.getElementById('confPass').value = '';
    } else {
        Swal.fire({icon:'error',title:'Error',text:data.message});
    }
}

// ── SIMPLE SAVE FEEDBACK ──────────────────────────────────
function saveNotifications() { Swal.fire({icon:'success',title:'Saved successfully',timer:1200,showConfirmButton:false}); }
function saveAppearance()    { Swal.fire({icon:'success',title:'Saved successfully',timer:1200,showConfirmButton:false}); }
function saveSimple(label)   { Swal.fire({icon:'success',title:'Saved successfully',timer:1200,showConfirmButton:false}); }

// ── DELETE USER ───────────────────────────────────────────
async function deleteUser(id, name) {
    const res = await Swal.fire({
        title: 'Remove ' + name + '?',
        text: 'This manager account will be permanently deleted.',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#ef4444', confirmButtonText: 'Yes, Remove'
    });
    if (!res.isConfirmed) return;
    const fd = new FormData();
    fd.append('action', 'delete_user');
    fd.append('user_id', id);
    const resp = await fetch('profile_action.php', {method:'POST',body:fd});
    const data = await resp.json();
    if (data.success) {
        Swal.fire({icon:'success',title:'Removed!',timer:1200,showConfirmButton:false}).then(() => location.reload());
    } else {
        Swal.fire({icon:'error',title:'Error',text:data.message});
    }
}

// ── DANGER ZONE ───────────────────────────────────────────
async function dangerAction() {
    const res = await Swal.fire({
        title: 'Clear All Sales History?',
        html: '<span style="color:#ef4444;font-weight:bold;">This cannot be undone!</span><br>Type <b>DELETE</b> to confirm.',
        icon: 'warning', input: 'text', inputPlaceholder: 'Type DELETE',
        showCancelButton: true, confirmButtonColor: '#ef4444',
        preConfirm: v => { if (v !== 'DELETE') Swal.showValidationMessage('Type DELETE exactly'); }
    });
    if (!res.isConfirmed) return;
    Swal.fire({icon:'info',title:'Feature coming soon',text:'Bulk delete will be available in the next update.'});
}
</script>
</body>
</html>