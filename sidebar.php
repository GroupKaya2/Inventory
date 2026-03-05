<?php
// sidebar.php – Dispeedway Inventory System
$activePage = $activePage ?? '';
$userRole   = $_SESSION['role'] ?? 'manager';
$isOwner    = ($userRole === 'owner');
?>
<style>
:root {
    --sb-width: 240px;
    --sb-bg: #0f172a;
    --sb-border: rgba(255,255,255,.07);
    --sb-text: #94a3b8;
    --sb-text-active: #ffffff;
    --sb-hover: rgba(255,255,255,.06);
    --sb-active: rgba(102,126,234,.18);
    --sb-orange: #f97316;
}
.sidebar {
    position: fixed; top: 0; left: 0;
    width: var(--sb-width); height: 100vh;
    background: var(--sb-bg);
    border-right: 1px solid var(--sb-border);
    display: flex; flex-direction: column;
    z-index: 1040; overflow-y: auto;
    transition: transform .25s ease;
}
.sb-logo {
    padding: 20px 18px 14px;
    border-bottom: 1px solid var(--sb-border);
    display: flex; align-items: center; gap: 10px;
    text-decoration: none;
}
.sb-logo-icon {
    width: 38px; height: 38px;
    background: linear-gradient(135deg, var(--sb-orange), #ef4444);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; color: #fff; flex-shrink: 0;
}
.sb-logo-text .title { font-size:.92rem; font-weight:800; color:#fff; line-height:1.1; }
.sb-logo-text .sub   { font-size:.65rem; color:var(--sb-text); text-transform:uppercase; letter-spacing:.4px; }
.sb-section {
    padding: 14px 18px 4px;
    font-size:.62rem; font-weight:700; text-transform:uppercase;
    letter-spacing:.7px; color:rgba(148,163,184,.5);
}
.sb-item {
    display:flex; align-items:center; gap:10px;
    padding:9px 14px; margin:1px 8px; border-radius:10px;
    text-decoration:none; color:var(--sb-text);
    font-size:.83rem; font-weight:500;
    transition:background .15s, color .15s;
}
.sb-item:hover  { background:var(--sb-hover); color:var(--sb-text-active); text-decoration:none; }
.sb-item.active { background:var(--sb-active); color:var(--sb-text-active); }
.sb-item.locked {
    opacity:.45; cursor:not-allowed; pointer-events:none;
    position:relative;
}
.sb-item.locked::after {
    content:'\F4C1';
    font-family:'bootstrap-icons';
    position:absolute; right:14px;
    font-size:.75rem; color:#94a3b8;
}
.sb-icon {
    width:30px; height:30px; border-radius:8px;
    display:flex; align-items:center; justify-content:center;
    font-size:.95rem; flex-shrink:0;
    background:rgba(255,255,255,.07); color:var(--sb-text);
}
.icon-dashboard { background:linear-gradient(135deg,#667eea,#764ba2)!important; color:#fff!important; }
.icon-sale      { background:linear-gradient(135deg,#f97316,#ef4444)!important; color:#fff!important; }
.icon-inventory { background:linear-gradient(135deg,#06b6d4,#3b82f6)!important; color:#fff!important; }
.icon-history   { background:linear-gradient(135deg,#10b981,#059669)!important; color:#fff!important; }
.icon-expense   { background:linear-gradient(135deg,#ef4444,#dc2626)!important; color:#fff!important; }
.icon-profile   { background:linear-gradient(135deg,#94a3b8,#64748b)!important; color:#fff!important; }
.sb-role-badge {
    font-size:.6rem; font-weight:700; text-transform:uppercase;
    letter-spacing:.5px; padding:2px 8px; border-radius:6px;
    margin-left:auto;
}
.role-owner   { background:rgba(249,115,22,.25); color:#fb923c; }
.role-manager { background:rgba(100,116,139,.25); color:#94a3b8; }
.sb-divider { border-top:1px solid var(--sb-border); margin:8px 0; }
.sb-user {
    margin-top:auto; padding:12px 14px;
    border-top:1px solid var(--sb-border);
    display:flex; align-items:center; gap:10px;
}
.sb-avatar {
    width:34px; height:34px; border-radius:10px;
    background:linear-gradient(135deg,#667eea,#764ba2);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-weight:700; font-size:.85rem; flex-shrink:0;
}
.sb-user-name { font-size:.8rem;  font-weight:600; color:#e2e8f0; }
.sb-user-role { font-size:.68rem; color:var(--sb-text); text-transform:capitalize; }
.sb-logout {
    margin-left:auto; color:var(--sb-text); text-decoration:none;
    font-size:1rem; padding:6px; border-radius:8px;
    transition:background .15s, color .15s;
}
.sb-logout:hover { background:rgba(239,68,68,.2); color:#fca5a5; }
.app-main { margin-left:var(--sb-width); }
.sb-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.55); z-index:1039;
}
.sb-toggle {
    display:none; position:fixed; top:12px; left:12px; z-index:1050;
    background:var(--sb-bg); border:1px solid var(--sb-border); color:#fff;
    width:40px; height:40px; border-radius:10px;
    align-items:center; justify-content:center; font-size:1.2rem; cursor:pointer;
}
@media(max-width:768px){
    .sidebar { transform:translateX(-100%); }
    .sidebar.open { transform:translateX(0); }
    .app-main { margin-left:0 !important; padding-top:60px !important; }
    .sb-toggle { display:flex; }
    .sb-overlay { display:block; opacity:0; pointer-events:none; transition:opacity .25s; }
    .sb-overlay.open { opacity:1; pointer-events:auto; }
}
</style>

<button class="sb-toggle" id="sbToggle"><i class="bi bi-list"></i></button>
<div class="sb-overlay" id="sbOverlay"></div>

<aside class="sidebar" id="sidebar">
    <a href="dashboard.php" class="sb-logo">
        <div class="sb-logo-icon"><i class="bi bi-speedometer2"></i></div>
        <div class="sb-logo-text">
            <div class="title">Dispeedway</div>
            <div class="sub">Inventory System</div>
        </div>
        <span class="sb-role-badge <?= $isOwner ? 'role-owner' : 'role-manager' ?>">
            <?= $isOwner ? 'Owner' : 'Manager' ?>
        </span>
    </a>

    <div class="sb-section">Main</div>

    <a href="dashboard.php" class="sb-item <?= $activePage==='dashboard'?'active':'' ?>">
        <span class="sb-icon icon-dashboard"><i class="bi bi-speedometer2"></i></span>
        Dashboard
    </a>

    <a href="sales.php" class="sb-item <?= $activePage==='sales'?'active':'' ?>">
        <span class="sb-icon icon-sale"><i class="bi bi-receipt"></i></span>
        New Sale
    </a>

    <div class="sb-divider"></div>
    <div class="sb-section">Inventory</div>

    <a href="inventory.php" class="sb-item <?= $activePage==='inventory'?'active':'' ?>">
        <span class="sb-icon icon-inventory"><i class="bi bi-box-seam"></i></span>
        Products & Stock
    </a>

    <a href="sales_history.php" class="sb-item <?= $activePage==='sales_history'?'active':'' ?>">
        <span class="sb-icon icon-history"><i class="bi bi-clock-history"></i></span>
        Sales History
    </a>

    <div class="sb-divider"></div>

    <div class="sb-section">
        Finance
        <?php if (!$isOwner): ?>
        <span style="font-size:.55rem;color:#ef4444;margin-left:4px;">● Owner Only</span>
        <?php endif; ?>
    </div>

    <?php if ($isOwner): ?>
    <a href="expenses.php" class="sb-item <?= $activePage==='expenses'?'active':'' ?>">
        <span class="sb-icon icon-expense"><i class="bi bi-wallet2"></i></span>
        Expenses
        <?php
        if (isset($conn)) {
            $expToday = $conn->query("SELECT COUNT(*) AS c FROM expenses WHERE expense_date=CURDATE()")->fetch_assoc()['c'] ?? 0;
            if ($expToday > 0) echo "<span style='margin-left:auto;background:rgba(239,68,68,.25);color:#fca5a5;font-size:.65rem;padding:2px 7px;border-radius:8px;font-weight:700;'>$expToday</span>";
        }
        ?>
    </a>
    <?php else: ?>
    <span class="sb-item locked">
        <span class="sb-icon icon-expense"><i class="bi bi-wallet2"></i></span>
        Expenses
    </span>
    <?php endif; ?>

    <div class="sb-divider"></div>
    <div class="sb-section">System</div>

    <?php if ($isOwner): ?>
    <a href="profile.php" class="sb-item <?= $activePage==='profile'?'active':'' ?>">
        <span class="sb-icon icon-profile"><i class="bi bi-person-gear"></i></span>
        Profile / Users
    </a>
    <?php else: ?>
    <a href="profile.php" class="sb-item <?= $activePage==='profile'?'active':'' ?>">
        <span class="sb-icon icon-profile"><i class="bi bi-person"></i></span>
        My Profile
    </a>
    <?php endif; ?>

    <div class="sb-user">
        <div class="sb-avatar"><?= strtoupper(substr($_SESSION['user'] ?? 'A', 0, 1)) ?></div>
        <div>
            <div class="sb-user-name"><?= htmlspecialchars($_SESSION['user'] ?? 'Admin') ?></div>
            <div class="sb-user-role"><?= $isOwner ? '👑 Owner' : '🔧 Manager' ?></div>
        </div>
        <a href="logout.php" class="sb-logout" title="Log out"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</aside>

<script>
(function () {
    const toggle  = document.getElementById('sbToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sbOverlay');
    if (toggle) {
        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        });
    }
})();
</script>