<?php
$activePage = $activePage ?? '';
$userRole   = $_SESSION['role'] ?? 'manager';
$userName   = $_SESSION['user'] ?? 'User';
$isOwner    = ($userRole === 'owner');

$words    = explode(' ', trim($userName));
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], $words)));
$initials = substr($initials, 0, 2);
?>

<style>
    :root {
        --sb-w: 240px;
        --sb-bg: #0d0f16;
        --sb-border: rgba(255,255,255,.06);
        --sb-hover: rgba(255,255,255,.05);
        --sb-active: rgba(232,23,93,.18);
        --sb-text: #7a8499;
        --sb-text-on: #ffffff;
        --pink: #e8175d;
    }

    .sidebar {
        position:fixed;top:0;left:0;
        width:var(--sb-w);height:100vh;
        background:var(--sb-bg);
        border-right:1px solid var(--sb-border);
        display:flex;flex-direction:column;
        z-index:1040;overflow-y:auto;overflow-x:hidden;
        transition:transform .25s ease;
    }
    .sidebar::before {
        content:'';position:absolute;top:0;left:0;right:0;height:2px;
        background:linear-gradient(90deg,var(--pink),transparent);
    }
    .sb-logo {
        padding:18px 16px 14px;
        border-bottom:1px solid var(--sb-border);
        display:flex;align-items:center;gap:10px;text-decoration:none;
    }
    .sb-logo-mark {
        width:36px;height:36px;border-radius:50%;overflow:hidden;
        display:flex;align-items:center;justify-content:center;flex-shrink:0;
        border:1.5px solid rgba(255,255,255,.3);
        box-shadow:0 0 8px rgba(232,23,93,.4);
    }
    .sb-brand-logo { width:100%;height:100%;object-fit:cover;display:block; }
    .sb-logo-text .name { font-family:'Syne',sans-serif;font-size:.9rem;font-weight:800;color:#fff; }
    .sb-logo-text .tag  { font-size:.6rem;text-transform:uppercase;letter-spacing:.4px;color:var(--sb-text); }
    .sb-role { margin-left:auto;font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:2px 8px;border-radius:20px; }
    .sb-role.owner   { background:rgba(232,23,93,.2);color:#ff6b9d; }
    .sb-role.manager { background:rgba(100,116,139,.2);color:#94a3b8; }
    .sb-section { font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:rgba(122,132,153,.5);padding:14px 16px 4px; }
    .sb-link {
        display:flex;align-items:center;gap:10px;
        padding:9px 12px;margin:1px 8px;border-radius:10px;
        text-decoration:none;color:var(--sb-text);font-size:.82rem;font-weight:500;
        transition:background .15s,color .15s;position:relative;
    }
    .sb-link:hover { background:var(--sb-hover);color:var(--sb-text-on);text-decoration:none; }
    .sb-link.active { background:var(--sb-active);color:#fff; }
    .sb-link.active::before {
        content:'';position:absolute;left:-8px;top:50%;transform:translateY(-50%);
        width:3px;height:60%;background:var(--pink);border-radius:0 2px 2px 0;
    }
    .sb-link.locked { opacity:.4;cursor:not-allowed;pointer-events:none; }
    .sb-link.locked::after { content:'\F4C1';font-family:'bootstrap-icons';position:absolute;right:14px;font-size:.7rem; }
    .sb-icon {
        width:30px;height:30px;border-radius:8px;
        display:flex;align-items:center;justify-content:center;
        font-size:.9rem;flex-shrink:0;
        background:rgba(255,255,255,.07);color:var(--sb-text);
        transition:background .15s;
    }
    .sb-link.active .sb-icon, .sb-link:hover .sb-icon { background:rgba(232,23,93,.25);color:#ff6b9d; }
    .icon-pink   { background:linear-gradient(135deg,#e8175d,#9b0d43)!important;color:#fff!important; }
    .icon-green  { background:linear-gradient(135deg,#10b981,#059669)!important;color:#fff!important; }
    .icon-blue   { background:linear-gradient(135deg,#3b82f6,#1d4ed8)!important;color:#fff!important; }
    .icon-orange { background:linear-gradient(135deg,#f97316,#dc2626)!important;color:#fff!important; }
    .icon-teal   { background:linear-gradient(135deg,#06b6d4,#0891b2)!important;color:#fff!important; }
    .icon-gray   { background:linear-gradient(135deg,#475569,#334155)!important;color:#fff!important; }
    .icon-purple { background:linear-gradient(135deg,#8b5cf6,#6d28d9)!important;color:#fff!important; }
    .sb-divider  { border:none;border-top:1px solid var(--sb-border);margin:6px 0; }
    .sb-badge {
        margin-left:auto;background:rgba(232,23,93,.25);color:#ff6b9d;
        font-size:.62rem;font-weight:700;padding:2px 7px;border-radius:10px;min-width:18px;text-align:center;
    }
    .sb-user {
        margin-top:auto;padding:14px 16px;
        border-top:1px solid var(--sb-border);
        display:flex;align-items:center;gap:10px;
    }
    .sb-avatar {
        width:34px;height:34px;border-radius:10px;
        background:linear-gradient(135deg,var(--pink),#9b0d43);
        display:flex;align-items:center;justify-content:center;
        font-family:'Syne',sans-serif;font-weight:800;font-size:.82rem;color:#fff;flex-shrink:0;
    }
    .sb-username { font-size:.8rem;font-weight:600;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:130px; }
    .sb-userrole { font-size:.66rem;color:var(--sb-text); }
    .sb-logout {
        margin-left:auto;color:var(--sb-text);font-size:1rem;
        padding:6px;border-radius:8px;transition:background .15s,color .15s;text-decoration:none;flex-shrink:0;
    }
    .sb-logout:hover { background:rgba(239,68,68,.2);color:#fca5a5; }
    .sb-toggle {
        display:none;position:fixed;top:12px;left:12px;z-index:1050;
        background:var(--sb-bg);border:1px solid var(--sb-border);color:#fff;
        width:40px;height:40px;border-radius:10px;
        align-items:center;justify-content:center;font-size:1.2rem;cursor:pointer;
    }
    .sb-overlay {
        display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);
        z-index:1039;opacity:0;pointer-events:none;transition:opacity .25s;
    }
    .sb-overlay.open { opacity:1;pointer-events:auto; }
    @media(max-width:768px){
        .sidebar { transform:translateX(-100%); }
        .sidebar.open { transform:translateX(0); }
        .sb-toggle { display:flex; }
        .sb-overlay { display:block; }
    }
</style>

<button class="sb-toggle" id="sbToggle" aria-label="Open menu"><i class="bi bi-list"></i></button>
<div class="sb-overlay" id="sbOverlay"></div>

<aside class="sidebar" id="sidebar">

    <a href="dashboard.php" class="sb-logo">
        <div class="sb-logo-mark">
            <img src="assets/img/logo.jpg" alt="Logo" class="sb-brand-logo">
        </div>
        <div class="sb-logo-text">
            <div class="name">Dspeedway</div>
            <div class="tag">Inventory System</div>
        </div>
    </a>

    <div class="sb-section">Main</div>

    <a href="dashboard.php" class="sb-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
        <span class="sb-icon icon-pink"><i class="bi bi-speedometer2"></i></span>
        Dashboard
    </a>

    <a href="sales.php" class="sb-link <?= $activePage === 'sales' ? 'active' : '' ?>">
        <span class="sb-icon icon-orange"><i class="bi bi-receipt"></i></span>
        Daily Transaction
    </a>

    <hr class="sb-divider">
    <div class="sb-section">Reports</div>

    <a href="sales-history.php" class="sb-link <?= $activePage === 'sales_history' ? 'active' : '' ?>">
        <span class="sb-icon icon-green"><i class="bi bi-clock-history"></i></span>
        Sales History
    </a>

    <a href="monthly-report.php" class="sb-link <?= $activePage === 'monthly_report' ? 'active' : '' ?>">
        <span class="sb-icon icon-blue"><i class="bi bi-calendar3"></i></span>
        Monthly Report
    </a>

    <hr class="sb-divider">
    <div class="sb-section">Inventory</div>

    <a href="inventory.php" class="sb-link <?= $activePage === 'inventory' ? 'active' : '' ?>">
        <span class="sb-icon icon-teal"><i class="bi bi-box-seam"></i></span>
        Products & Stock
    </a>

    <hr class="sb-divider">
    <div class="sb-section">
        Finance
        <?php if (!$isOwner): ?>
            <span style="font-size:.5rem;color:#ef4444;margin-left:4px;">● Owner Only</span>
        <?php endif; ?>
    </div>

    <?php if ($isOwner): ?>
        <a href="expenses.php" class="sb-link <?= $activePage === 'expenses' ? 'active' : '' ?>">
            <span class="sb-icon icon-pink"><i class="bi bi-wallet2"></i></span>
            Expenses
            <?php
            if (isset($conn)) {
                $expToday = (int)($conn->query("SELECT COUNT(*) AS c FROM expenses WHERE expense_date = CURDATE()")->fetch_assoc()['c'] ?? 0);
                if ($expToday > 0) echo "<span class='sb-badge'>{$expToday}</span>";
            }
            ?>
        </a>
    <?php else: ?>
        <span class="sb-link locked">
            <span class="sb-icon"><i class="bi bi-wallet2"></i></span>
            Expenses
        </span>
    <?php endif; ?>

    <hr class="sb-divider">
    <div class="sb-section">Account</div>

    <a href="profile.php" class="sb-link <?= $activePage === 'profile' ? 'active' : '' ?>">
        <span class="sb-icon icon-gray"><i class="bi bi-person-gear"></i></span>
        <?= $isOwner ? 'Profile & Users' : 'My Profile' ?>
    </a>

    <div class="sb-user">
        <div class="sb-avatar"><?= htmlspecialchars($initials) ?></div>
        <div style="overflow:hidden;">
            <div class="sb-username"><?= htmlspecialchars($userName) ?></div>
            <div class="sb-userrole"><?= $isOwner ? '👑 Owner' : '🔧 Manager' ?></div>
        </div>
        <a href="backend/auth.php?action=logout" class="sb-logout" title="Log out">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>

</aside>

<script>
(function(){
    const toggle  = document.getElementById('sbToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sbOverlay');
    toggle?.addEventListener('click',  () => { sidebar.classList.toggle('open'); overlay.classList.toggle('open'); });
    overlay?.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('open'); });
})();
</script>