<?php
session_start();
require_once 'backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$activePage = 'profile';
$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
$userId = (int) $_SESSION['user_id'];

$hasCreatedAt = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'")->num_rows > 0;
$fields = $hasCreatedAt ? 'id, name, email, role, created_at' : 'id, name, email, role';

$stmt = $conn->prepare("SELECT $fields FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!isset($me['created_at']))
    $me['created_at'] = null;

$myEmail = $me['email'];
$allUsers = [];
if ($isOwner) {
    $r = $conn->query("SELECT $fields FROM users ORDER BY role DESC, name ASC");
    if ($r)
        while ($row = $r->fetch_assoc())
            $allUsers[] = $row;
}

$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($me['name'])))));
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — DSpeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .ac-wrap {
            display: flex;
            gap: 0;
            min-height: calc(100vh - 90px);
            background: #111827;
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 12px;
            overflow: hidden;
        }

        /* LEFT */
        .ac-left {
            width: 300px;
            flex-shrink: 0;
            border-right: 1px solid rgba(255, 255, 255, .07);
            padding: 24px 0 16px;
        }

        .ac-left-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
            padding: 0 20px 5px;
        }

        .ac-left-sub {
            font-size: .75rem;
            color: #4b5a6e;
            padding: 0 20px 18px;
            line-height: 1.5;
        }

        .ac-nav-section {
            font-size: .58rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #1e2a3a;
            padding: 14px 20px 5px;
        }

        .ac-nav-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 12px;
            margin: 1px 8px;
            width: calc(100% - 16px);
            cursor: pointer;
            border: none;
            background: none;
            text-align: left;
            color: #64748b;
            font-size: .83rem;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
            border-radius: 9px;
            transition: background .12s, color .12s;
        }

        .ac-nav-item:hover {
            background: rgba(255, 255, 255, .05);
            color: #e2e8f0;
        }

        .ac-nav-item.active {
            background: rgba(74, 222, 128, .08);
            color: #fff;
        }

        .nav-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .82rem;
            flex-shrink: 0;
            color: #4b5a6e;
            transition: all .12s;
        }

        .ac-nav-item:hover .nav-icon,
        .ac-nav-item.active .nav-icon {
            background: rgba(74, 222, 128, .12);
            color: #4ade80;
        }

        /* RIGHT */
        .ac-right {
            flex: 1;
            padding: 28px 30px;
            overflow-y: auto;
        }

        .ac-panel {
            display: none;
        }

        .ac-panel.active {
            display: block;
        }

        .ac-right-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px;
        }

        .ac-right-sub {
            font-size: .78rem;
            color: #4b5a6e;
            margin-bottom: 22px;
            line-height: 1.5;
        }

        .ac-section-label {
            font-family: 'Space Grotesk', sans-serif;
            font-size: .85rem;
            font-weight: 600;
            color: #e2e8f0;
            margin: 0 0 10px;
        }

        /* Cards */
        .ac-card {
            background: #1c2336;
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .ac-row {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 14px 18px;
            cursor: pointer;
            transition: background .12s;
            border-bottom: 1px solid rgba(255, 255, 255, .05);
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-family: 'Inter', sans-serif;
        }

        .ac-row:last-child {
            border-bottom: none;
        }

        .ac-row:hover {
            background: rgba(255, 255, 255, .03);
        }

        .ac-row-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #16a34a, #052e16);
            border: 1px solid rgba(74, 222, 128, .3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: .88rem;
            color: #4ade80;
            flex-shrink: 0;
        }

        .ac-row-name {
            font-size: .88rem;
            font-weight: 600;
            color: #e2e8f0;
        }

        .ac-row-sub {
            font-size: .75rem;
            color: #4b5a6e;
            margin-top: 1px;
        }

        .ac-chevron {
            color: #4b5a6e;
            font-size: .8rem;
            margin-left: auto;
            flex-shrink: 0;
        }

        .ac-detail-row {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            cursor: pointer;
            transition: background .12s;
            border-bottom: 1px solid rgba(255, 255, 255, .05);
        }

        .ac-detail-row:last-child {
            border-bottom: none;
        }

        .ac-detail-row:hover {
            background: rgba(255, 255, 255, .03);
        }

        .ac-detail-label {
            font-size: .85rem;
            font-weight: 600;
            color: #e2e8f0;
        }

        .ac-detail-val {
            font-size: .77rem;
            color: #4b5a6e;
            margin-top: 2px;
        }

        .ac-add-link {
            display: block;
            padding: 12px 18px;
            font-size: .82rem;
            font-weight: 600;
            color: #4ade80;
            cursor: pointer;
            border: none;
            background: none;
            text-align: left;
            width: 100%;
            font-family: 'Inter', sans-serif;
            transition: background .12s;
        }

        .ac-add-link:hover {
            background: rgba(74, 222, 128, .04);
        }

        /* Manager cards */
        .mgr-card {
            background: #1c2336;
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 14px 16px;
            margin-bottom: 8px;
            transition: border-color .15s;
        }

        .mgr-card:hover {
            border-color: rgba(74, 222, 128, .18);
        }

        .mgr-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: .82rem;
            flex-shrink: 0;
        }

        .mgr-name {
            font-size: .87rem;
            font-weight: 600;
            color: #e2e8f0;
        }

        .mgr-email {
            font-size: .74rem;
            color: #4b5a6e;
        }

        .mgr-actions {
            margin-left: auto;
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }

        .btn-edit-u {
            background: rgba(96, 165, 250, .08);
            border: 1px solid rgba(96, 165, 250, .2);
            color: #93c5fd;
            padding: 5px 12px;
            border-radius: 7px;
            font-size: .76rem;
            cursor: pointer;
            transition: background .15s;
            font-family: 'Inter', sans-serif;
        }

        .btn-edit-u:hover {
            background: rgba(96, 165, 250, .18);
            color: #fff;
        }

        .btn-del-u {
            background: rgba(248, 113, 113, .08);
            border: 1px solid rgba(248, 113, 113, .2);
            color: #fca5a5;
            padding: 5px 12px;
            border-radius: 7px;
            font-size: .76rem;
            cursor: pointer;
            transition: background .15s;
            font-family: 'Inter', sans-serif;
        }

        .btn-del-u:hover {
            background: rgba(248, 113, 113, .18);
            color: #fff;
        }

        /* Form */
        .ac-input {
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .09);
            border-radius: 8px;
            color: #e2e8f0;
            padding: 9px 13px;
            font-size: .84rem;
            font-family: 'Inter', sans-serif;
            width: 100%;
            transition: border-color .15s;
        }

        .ac-input:focus {
            outline: none;
            border-color: rgba(74, 222, 128, .35);
            background: rgba(74, 222, 128, .03);
            box-shadow: 0 0 0 3px rgba(74, 222, 128, .06);
        }

        .ac-input::placeholder {
            color: #2e3a4e;
        }

        .ac-label {
            font-size: .65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #4b5a6e;
            display: block;
            margin-bottom: 5px;
        }

        .ac-input[readonly] {
            opacity: .4;
            cursor: not-allowed;
        }

        .info-notice {
            background: rgba(74, 222, 128, .04);
            border: 1px solid rgba(74, 222, 128, .12);
            border-radius: 9px;
            padding: 12px 16px;
            font-size: .78rem;
            color: #4b5a6e;
            line-height: 1.6;
            margin-top: 14px;
        }

        .info-notice a {
            color: #4ade80;
            font-weight: 600;
        }



        /* Modal */
        .dark-modal .modal-content {
            background: #161b27;
            border: 1px solid rgba(255, 255, 255, .09);
            color: #e2e8f0;
        }

        .dark-modal .modal-header {
            background: #111827;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
        }

        .dark-modal .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, .07);
        }

        @media(max-width:768px) {
            .ac-wrap {
                flex-direction: column;
            }

            .ac-left {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, .07);
                padding-bottom: 10px;
            }

            .ac-right {
                padding: 20px 16px;
            }
        }
    </style>
</head>

<body>

    <?php include 'sidebar.php'; ?>

    <main class="app-main">

        <div class="page-header mb-3">
            <h4 style="margin:0;"><i class="bi bi-person-gear me-2"></i>Accounts Center</h4>
            <p style="margin:4px 0 0;">Manage your account and settings<?= $isOwner ? ', and team members' : '' ?></p>
        </div>

        <div class="ac-wrap">

            <!-- ═══ LEFT NAV ═══ -->
            <div class="ac-left">
                <div class="ac-left-title">Accounts Center</div>
                <div class="ac-left-sub">Manage your connected account and settings across DSpeedway.</div>

                <div class="ac-nav-section">Main</div>
                <button class="ac-nav-item active" onclick="showPanel('profiles',this)">
                    <span class="nav-icon"><i class="bi bi-person-fill"></i></span>
                    Profiles and personal details
                </button>

                <div class="ac-nav-section">Account settings</div>
                <button class="ac-nav-item" onclick="showPanel('password',this)">
                    <span class="nav-icon"><i class="bi bi-shield-lock-fill"></i></span>
                    Password and security
                </button>
                <button class="ac-nav-item" onclick="showPanel('personal',this)">
                    <span class="nav-icon"><i class="bi bi-pencil-fill"></i></span>
                    Edit profile info
                </button>
                <?php if ($isOwner): ?>
                    <button class="ac-nav-item" onclick="showPanel('users',this)">
                        <span class="nav-icon"><i class="bi bi-people-fill"></i></span>
                        Manage accounts
                    </button>
                    <button class="ac-nav-item" onclick="showPanel('audit',this)">
                        <span class="nav-icon"><i class="bi bi-journal-text"></i></span>
                        Audit Log
                    </button>

                <?php endif; ?>
            </div>

            <!-- ═══ RIGHT CONTENT ═══ -->
            <div class="ac-right">

                <!-- PROFILES & PERSONAL DETAILS -->
                <div class="ac-panel active" id="panel-profiles">
                    <div class="ac-right-title">Profiles and personal details</div>
                    <div class="ac-right-sub">Review the profiles and personal details you've added to this Accounts
                        Center.</div>

                    <div class="ac-section-label">Profiles</div>
                    <div class="ac-card mb-4">
                        <button class="ac-row" style="border-bottom:1px solid rgba(255,255,255,.05);"
                            onclick="showPanel('personal',document.querySelector('[onclick*=personal]'))">
                            <div class="ac-row-avatar"><?= htmlspecialchars($initials) ?></div>
                            <div style="flex:1;text-align:left;">
                                <div class="ac-row-name"><?= htmlspecialchars($me['name']) ?></div>
                                <div class="ac-row-sub">DSpeedway · <?= $isOwner ? 'Owner' : 'Manager' ?></div>
                            </div>
                            <i class="bi bi-chevron-right ac-chevron"></i>
                        </button>
                        <button class="ac-add-link"
                            onclick="showPanel('personal',document.querySelector('[onclick*=personal]'))">
                            <i class="bi bi-pencil me-1"></i> Edit profile
                        </button>
                    </div>

                    <?php if ($isOwner && count($allUsers) > 1): ?>
                        <div class="ac-section-label">Pages you manage</div>
                        <div class="ac-card mb-4">
                            <?php foreach ($allUsers as $u):
                                if ($u['id'] == $userId)
                                    continue;
                                $uIni = strtoupper(substr($u['name'], 0, 1));
                                ?>
                                <button class="ac-row" style="border-bottom:1px solid rgba(255,255,255,.05);"
                                    onclick="openEditUser(<?= $u['id'] ?>,'<?= addslashes(htmlspecialchars($u['name'])) ?>','<?= addslashes(htmlspecialchars($u['email'])) ?>')">
                                    <div class="ac-row-avatar"
                                        style="background:linear-gradient(135deg,#4338ca,#1e1b4b);color:#a5b4fc;border-color:rgba(165,180,252,.25);">
                                        <?= $uIni ?>
                                    </div>
                                    <div style="flex:1;text-align:left;">
                                        <div class="ac-row-name"><?= htmlspecialchars($u['name']) ?></div>
                                        <div class="ac-row-sub"><?= htmlspecialchars($u['email']) ?></div>
                                    </div>
                                    <i class="bi bi-chevron-right ac-chevron"></i>
                                </button>
                            <?php endforeach; ?>
                            <button class="ac-add-link"
                                onclick="showPanel('users',document.querySelector('[onclick*=users]'))">
                                <i class="bi bi-person-plus me-1"></i> Add manager account
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="ac-section-label">Personal details</div>
                    <div class="ac-card">
                        <div class="ac-detail-row"
                            onclick="showPanel('personal',document.querySelector('[onclick*=personal]'))">
                            <div style="flex:1;">
                                <div class="ac-detail-label">Contact info</div>
                                <div class="ac-detail-val"><?= htmlspecialchars($myEmail) ?></div>
                            </div>
                            <i class="bi bi-chevron-right ac-chevron"></i>
                        </div>
                        <div class="ac-detail-row"
                            onclick="showPanel('password',document.querySelector('[onclick*=password]'))">
                            <div style="flex:1;">
                                <div class="ac-detail-label">Password and security</div>
                                <div class="ac-detail-val">Manage your password settings</div>
                            </div>
                            <i class="bi bi-chevron-right ac-chevron"></i>
                        </div>
                        <div class="ac-detail-row">
                            <div style="flex:1;">
                                <div class="ac-detail-label">Role</div>
                                <div class="ac-detail-val">
                                    <?= $isOwner ? 'Owner — full system access' : 'Manager — limited access' ?>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right ac-chevron"></i>
                        </div>
                        <?php if ($me['created_at']): ?>
                            <div class="ac-detail-row">
                                <div style="flex:1;">
                                    <div class="ac-detail-label">Member since</div>
                                    <div class="ac-detail-val"><?= date('F j, Y', strtotime($me['created_at'])) ?></div>
                                </div>
                                <i class="bi bi-chevron-right ac-chevron"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="info-notice">
                        <i class="bi bi-info-circle me-2" style="color:#4ade80;"></i>
                        You can now manage your profile info and password from the settings on the left.
                        <?php if ($isOwner): ?>
                            <a href="#"
                                onclick="showPanel('users',document.querySelector('[onclick*=users]'));return false;">Learn
                                more about managing accounts →</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- EDIT PROFILE INFO -->
                <div class="ac-panel" id="panel-personal">
                    <div class="ac-right-title">Edit profile info</div>
                    <div class="ac-right-sub">Update your display name and email address on DSpeedway.</div>
                    <div class="ac-card">
                        <div style="padding:20px 22px;">
                            <div class="mb-3">
                                <label class="ac-label">Full Name</label>
                                <input type="text" class="ac-input" id="updName"
                                    value="<?= htmlspecialchars($me['name']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="ac-label">Email Address</label>
                                <input type="email" class="ac-input" id="updEmail"
                                    value="<?= htmlspecialchars($myEmail) ?>">
                            </div>
                            <div class="mb-4">
                                <label class="ac-label">Role</label>
                                <input type="text" class="ac-input" value="<?= $isOwner ? 'Owner' : 'Manager' ?>"
                                    readonly>
                            </div>
                            <button class="btn-pink" onclick="saveProfile()">
                                <i class="bi bi-check-lg me-1"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </div>

                <!-- PASSWORD & SECURITY -->
                <div class="ac-panel" id="panel-password">
                    <div class="ac-right-title">Password and security</div>
                    <div class="ac-right-sub">Keep your account safe with a strong, unique password.</div>
                    <div class="ac-card">
                        <div style="padding:20px 22px;">
                            <div
                                style="background:rgba(251,191,36,.05);border:1px solid rgba(251,191,36,.16);border-radius:8px;padding:11px 15px;margin-bottom:20px;font-size:.79rem;color:#fde68a;">
                                <i class="bi bi-shield-check me-2"></i>Passwords are encrypted with bcrypt and never
                                stored in plain text.
                            </div>
                            <div class="mb-3">
                                <label class="ac-label">Current Password</label>
                                <input type="password" class="ac-input" id="curPass"
                                    placeholder="Enter current password">
                            </div>
                            <div class="mb-3">
                                <label class="ac-label">New Password</label>
                                <input type="password" class="ac-input" id="newPass"
                                    placeholder="At least 6 characters">
                            </div>
                            <div class="mb-4">
                                <label class="ac-label">Confirm New Password</label>
                                <input type="password" class="ac-input" id="confPass" placeholder="Repeat new password">
                            </div>
                            <button onclick="changePassword()"
                                style="background:linear-gradient(135deg,#b45309,#3a1a02);border:1px solid rgba(251,191,36,.28);color:#fbbf24;padding:9px 20px;border-radius:8px;font-weight:600;cursor:pointer;font-size:.84rem;font-family:'Inter',sans-serif;">
                                <i class="bi bi-shield-lock me-1"></i>Update Password
                            </button>
                        </div>
                    </div>
                </div>

                <!-- MANAGE ACCOUNTS (owner only) -->
                <?php if ($isOwner): ?>
                    <div class="ac-panel" id="panel-users">
                        <div class="d-flex align-items-start justify-content-between mb-1 gap-2 flex-wrap">
                            <div>
                                <div class="ac-right-title">Manage accounts</div>
                                <div class="ac-right-sub">Add, edit, or remove manager accounts for your team.</div>
                            </div>
                            <button class="btn-pink" style="flex-shrink:0;" onclick="openAddManager()">
                                <i class="bi bi-person-plus me-1"></i>Add Manager
                            </button>
                        </div>

                        <div id="usersList">
                            <?php foreach ($allUsers as $u):
                                $uIni = strtoupper(substr($u['name'], 0, 1));
                                $isMe = ($u['id'] == $userId);
                                $grad = $u['role'] === 'owner' ? 'linear-gradient(135deg,#16a34a,#052e16)' : 'linear-gradient(135deg,#4338ca,#1e1b4b)';
                                $tc = $u['role'] === 'owner' ? '#4ade80' : '#a5b4fc';
                                $bc = $u['role'] === 'owner' ? 'rgba(74,222,128,.3)' : 'rgba(165,180,252,.25)';
                                ?>
                                <div class="mgr-card" id="usercard-<?= $u['id'] ?>">
                                    <div class="mgr-avatar"
                                        style="background:<?= $grad ?>;color:<?= $tc ?>;border:1px solid <?= $bc ?>;">
                                        <?= $uIni ?>
                                    </div>
                                    <div style="flex:1;min-width:0;">
                                        <div class="mgr-name">
                                            <?= htmlspecialchars($u['name']) ?>
                                            <?php if ($isMe): ?><span
                                                    style="background:rgba(74,222,128,.1);color:#4ade80;font-size:.6rem;font-weight:700;padding:1px 7px;border-radius:4px;margin-left:6px;">You</span><?php endif; ?>
                                        </div>
                                        <div class="mgr-email"><?= htmlspecialchars($u['email']) ?></div>
                                        <?php if (!empty($u['created_at'])): ?>
                                            <div style="font-size:.67rem;color:#2e3a4e;margin-top:1px;">Joined
                                                <?= date('M d, Y', strtotime($u['created_at'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span
                                        style="background:<?= $u['role'] === 'owner' ? 'rgba(74,222,128,.1)' : 'rgba(100,116,139,.1)' ?>;color:<?= $u['role'] === 'owner' ? '#4ade80' : '#94a3b8' ?>;padding:3px 10px;border-radius:5px;font-size:.69rem;font-weight:600;white-space:nowrap;">
                                        <?= $u['role'] === 'owner' ? '⬡ Owner' : '◈ Manager' ?>
                                    </span>
                                    <div class="mgr-actions">
                                        <?php if ($u['role'] !== 'owner' || $isMe): ?>
                                            <button class="btn-edit-u"
                                                onclick="openEditUser(<?= $u['id'] ?>,'<?= addslashes(htmlspecialchars($u['name'])) ?>','<?= addslashes(htmlspecialchars($u['email'])) ?>')">
                                                <i class="bi bi-pencil me-1"></i>Edit
                                            </button>
                                        <?php endif; ?>
                                        <?php if (!$isMe && $u['role'] !== 'owner'): ?>
                                            <button class="btn-del-u"
                                                onclick="removeUser(<?= $u['id'] ?>,'<?= addslashes(htmlspecialchars($u['name'])) ?>')">
                                                <i class="bi bi-trash me-1"></i>Remove
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>



                    <!-- AUDIT LOG -->
                    <div class="ac-panel" id="panel-audit">
                        <div class="ac-right-title">Audit Log</div>
                        <div class="ac-right-sub">Track all edits and deletions in the system.</div>
                        
                        <div class="d-flex gap-2 mb-3">
                            <button class="btn-pink" onclick="loadAuditLog()" style="font-size:.82rem;padding:7px 16px;">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                            </button>
                        </div>
                        
                        <div class="card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>Table</th>
                                                <th>Record ID</th>
                                                <th>IP Address</th>
                                            </tr>
                                        </thead>
                                        <tbody id="auditLogBody">
                                            <tr>
                                                <td colspan="6" style="text-align:center;padding:30px;color:#7a8499;">
                                                    Loading audit log…
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <!-- Edit User Modal -->
    <?php if ($isOwner): ?>
        <div class="modal fade" id="editUserModal" tabindex="-1">
            <div class="modal-dialog dark-modal">
                <div class="modal-content dark-modal">
                    <div class="modal-header dark-modal">
                        <h5 class="modal-title" style="color:#fff;font-size:.93rem;"><i class="bi bi-pencil-square me-2"
                                style="color:#4ade80;"></i>Edit Account</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding:20px;">
                        <input type="hidden" id="editUserId">
                        <div
                            style="background:rgba(96,165,250,.06);border:1px solid rgba(96,165,250,.16);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:.77rem;color:#93c5fd;">
                            <i class="bi bi-info-circle me-2"></i>Leave a field blank to keep it unchanged.
                        </div>
                        <div class="mb-3"><label class="ac-label">Full Name</label><input type="text" class="ac-input"
                                id="editUserName" placeholder="Leave blank to keep current"></div>
                        <div class="mb-3"><label class="ac-label">Email Address</label><input type="email" class="ac-input"
                                id="editUserEmail" placeholder="Leave blank to keep current"></div>
                        <div class="mb-3"><label class="ac-label">New Password</label><input type="password"
                                class="ac-input" id="editUserPass" placeholder="Leave blank to keep current"></div>
                        <div><label class="ac-label">Confirm Password</label><input type="password" class="ac-input"
                                id="editUserPassConf" placeholder="Confirm new password"></div>
                    </div>
                    <div class="modal-footer dark-modal" style="padding:14px 20px;">
                        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn-pink" id="saveEditUserBtn" onclick="submitEditUser()"><i
                                class="bi bi-check-lg me-1"></i>Save Changes</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Manager Modal -->
        <div class="modal fade" id="addManagerModal" tabindex="-1">
            <div class="modal-dialog dark-modal">
                <div class="modal-content dark-modal">
                    <div class="modal-header dark-modal">
                        <h5 class="modal-title" style="color:#fff;font-size:.93rem;"><i class="bi bi-person-plus me-2"
                                style="color:#4ade80;"></i>Add Manager</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding:20px;">
                        <div class="mb-3"><label class="ac-label">Full Name *</label><input type="text" class="ac-input"
                                id="newMgrName" placeholder="Full Name"></div>
                        <div class="mb-3"><label class="ac-label">Email *</label><input type="email" class="ac-input"
                                id="newMgrEmail" placeholder="email@example.com"></div>
                        <div class="mb-3"><label class="ac-label">Password * <span style="color:#2e3a4e;">(min 6
                                    chars)</span></label><input type="password" class="ac-input" id="newMgrPass"
                                placeholder="Password"></div>
                        <div><label class="ac-label">Confirm Password *</label><input type="password" class="ac-input"
                                id="newMgrPassConf" placeholder="Confirm"></div>
                    </div>
                    <div class="modal-footer dark-modal" style="padding:14px 20px;">
                        <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn-pink" id="saveNewMgrBtn" onclick="submitAddManager()"><i
                                class="bi bi-person-check me-1"></i>Create Manager</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let auditLogLoaded = false;

        function showPanel(id, btn) {
            document.querySelectorAll('.ac-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.ac-nav-item').forEach(b => b.classList.remove('active'));
            document.getElementById('panel-' + id)?.classList.add('active');
            btn?.classList.add('active');
            
            // Load audit log when panel is shown
            if (id === 'audit' && !auditLogLoaded) {
                loadAuditLog();
            }
        }

        async function loadAuditLog() {
            const tbody = document.getElementById('auditLogBody');
            if (!tbody) return;

            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:30px;">
                <div class="spinner-border" style="color:#e8175d;"></div>
            </td></tr>`;

            try {
                const res = await fetch('backend/audit-log.php?action=fetch&limit=50');
                const json = await res.json();

                if (!json.success) {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#fca5a5;">
                        ⚠ ${json.message || 'Failed to load audit log.'}</td></tr>`;
                    return;
                }

                const entries = json.data || [];
                if (!entries.length) {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#7a8499;">
                        No audit log entries found.</td></tr>`;
                    return;
                }

                tbody.innerHTML = entries.map((e, idx) => {
                    const actionBadge = e.action_type === 'INSERT' 
                        ? '<span class="badge-green">INSERT</span>'
                        : e.action_type === 'UPDATE' 
                        ? '<span class="badge-yellow">UPDATE</span>'
                        : e.action_type === 'DELETE'
                        ? '<span class="badge-red">DELETE</span>'
                        : `<span class="badge-gray">${e.action_type}</span>`;
                    
                    return `<tr>
                        <td>${new Date(e.created_at).toLocaleString()}</td>
                        <td>${e.username || 'Unknown'}</td>
                        <td>${actionBadge}</td>
                        <td style="font-weight:600;">${e.table_name}</td>
                        <td>${e.record_id}</td>
                        <td style="font-size:.75rem;color:#64748b;">${e.ip_address || 'N/A'}</td>
                    </tr>`;
                }).join('');

                auditLogLoaded = true;
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#fca5a5;">
                    ⚠ Network error. (${err.message})</td></tr>`;
            }
        }

        async function saveProfile() {
            const name = document.getElementById('updName').value.trim(), email = document.getElementById('updEmail').value.trim();
            if (!name || !email) { Swal.fire({ icon: 'warning', title: 'Required', text: 'Name and email are required.' }); return; }
            const fd = new FormData(); fd.append('action', 'update_profile'); fd.append('name', name); fd.append('email', email);
            const data = await (await fetch('backend/profile.php', { method: 'POST', body: fd })).json();
            Swal.fire({ icon: data.success ? 'success' : 'error', title: data.success ? 'Saved!' : 'Error', text: data.message, timer: data.success ? 1400 : undefined, showConfirmButton: !data.success });
        }

        async function changePassword() {
            const cur = document.getElementById('curPass').value, nw = document.getElementById('newPass').value, conf = document.getElementById('confPass').value;
            if (!cur || !nw || !conf) { Swal.fire({ icon: 'warning', title: 'Fill all fields' }); return; }
            if (nw !== conf) { Swal.fire({ icon: 'error', title: "Passwords don't match" }); return; }
            if (nw.length < 6) { Swal.fire({ icon: 'warning', title: 'Too short', text: 'Min 6 characters.' }); return; }
            const fd = new FormData(); fd.append('action', 'change_password'); fd.append('current', cur); fd.append('new', nw);
            const data = await (await fetch('backend/profile.php', { method: 'POST', body: fd })).json();
            if (data.success) { Swal.fire({ icon: 'success', title: 'Password updated!', timer: 1400, showConfirmButton: false });['curPass', 'newPass', 'confPass'].forEach(id => document.getElementById(id).value = ''); }
            else Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        }

        function openEditUser(id, name, email) {
            document.getElementById('editUserId').value = id;
            ['editUserName', 'editUserEmail', 'editUserPass', 'editUserPassConf'].forEach(i => document.getElementById(i).value = '');
            document.getElementById('editUserName').placeholder = name;
            document.getElementById('editUserEmail').placeholder = email;
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        async function submitEditUser() {
            const id = document.getElementById('editUserId').value;
            const newName = document.getElementById('editUserName').value.trim();
            const newEmail = document.getElementById('editUserEmail').value.trim();
            const newPass = document.getElementById('editUserPass').value;
            const confPass = document.getElementById('editUserPassConf').value;
            if (!newName && !newEmail && !newPass) { Swal.fire({ icon: 'warning', title: 'Nothing to update', text: 'Fill at least one field.' }); return; }
            if (newPass && newPass !== confPass) { Swal.fire({ icon: 'error', title: "Passwords don't match" }); return; }
            if (newPass && newPass.length < 6) { Swal.fire({ icon: 'warning', title: 'Too short', text: 'Min 6 chars.' }); return; }
            const btn = document.getElementById('saveEditUserBtn'); btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving…';
            const fd = new FormData(); fd.append('action', 'update_user'); fd.append('user_id', id);
            if (newName) fd.append('new_name', newName); if (newEmail) fd.append('new_email', newEmail); if (newPass) fd.append('new_password', newPass);
            const data = await (await fetch('backend/profile.php', { method: 'POST', body: fd })).json();
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Changes';
            if (data.success) { bootstrap.Modal.getInstance(document.getElementById('editUserModal'))?.hide(); Swal.fire({ icon: 'success', title: 'Updated!', timer: 1400, showConfirmButton: false }).then(() => location.reload()); }
            else Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        }

        function openAddManager() {
            ['newMgrName', 'newMgrEmail', 'newMgrPass', 'newMgrPassConf'].forEach(id => document.getElementById(id).value = '');
            new bootstrap.Modal(document.getElementById('addManagerModal')).show();
        }

        async function submitAddManager() {
            const name = document.getElementById('newMgrName').value.trim(), email = document.getElementById('newMgrEmail').value.trim();
            const pass = document.getElementById('newMgrPass').value, conf = document.getElementById('newMgrPassConf').value;
            if (!name || !email || !pass || !conf) { Swal.fire({ icon: 'warning', title: 'Fill all fields' }); return; }
            if (pass !== conf) { Swal.fire({ icon: 'error', title: "Passwords don't match" }); return; }
            if (pass.length < 6) { Swal.fire({ icon: 'warning', title: 'Too short', text: 'Min 6 chars.' }); return; }
            const btn = document.getElementById('saveNewMgrBtn'); btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Creating…';
            const fd = new FormData(); fd.append('name', name); fd.append('email', email); fd.append('password', pass);
            await fetch('backend/register.php', { method: 'POST', body: fd });
            bootstrap.Modal.getInstance(document.getElementById('addManagerModal'))?.hide();
            Swal.fire({ icon: 'success', title: 'Manager created!', timer: 1400, showConfirmButton: false }).then(() => location.reload());
        }

        async function removeUser(id, name) {
            const c = await Swal.fire({ title: `Remove ${name}?`, text: 'This manager will be permanently deleted.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Remove' });
            if (!c.isConfirmed) return;
            const fd = new FormData(); fd.append('action', 'delete_user'); fd.append('user_id', id);
            const data = await (await fetch('backend/profile.php', { method: 'POST', body: fd })).json();
            if (data.success) Swal.fire({ icon: 'success', title: 'Removed!', timer: 1200, showConfirmButton: false }).then(() => location.reload());
            else Swal.fire({ icon: 'error', title: 'Error', text: data.message });
        }

        async function clearSalesHistory() {
            const r = await Swal.fire({ title: 'Clear ALL Sales History?', html: '<span style="color:#fca5a5;font-weight:bold;">This cannot be undone!</span><br><br>Type <b>DELETE</b> to confirm.', icon: 'warning', input: 'text', inputPlaceholder: 'Type DELETE', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Delete Everything', preConfirm: v => { if (v !== 'DELETE') Swal.showValidationMessage('Type DELETE exactly (uppercase)'); } });
            if (!r.isConfirmed) return;
            Swal.fire({ icon: 'info', title: 'Coming Soon', text: 'Bulk delete will be available in the next update.' });
        }
    </script>
    <?php include 'footer.php'; ?>

</body>

</html>