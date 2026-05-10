<?php
$activePage = $activePage ?? '';
$userRole = $_SESSION['role'] ?? 'manager';
$userName = $_SESSION['user'] ?? 'User';
$isOwner = ($userRole === 'owner');

$words = explode(' ', trim($userName));
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], $words)));
$initials = substr($initials, 0, 2);
?>

<style>
    :root {
        --sb-w: 220px;
        --sb-bg: #0b0f18;
        --sb-border: rgba(255, 255, 255, .05);
        --sb-hover: rgba(74, 222, 128, .06);
        --sb-active: rgba(74, 222, 128, .1);
        --sb-text: #4b5a6e;
        --sb-text-on: #e2e8f0;
        --accent: #4ade80;
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sb-w);
        height: 100vh;
        background: var(--sb-bg);
        border-right: 1px solid var(--sb-border);
        display: flex;
        flex-direction: column;
        z-index: 1040;
        overflow-y: auto;
        overflow-x: hidden;
        transition: transform .25s ease;
    }

    .sidebar::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        width: 1px;
        background: linear-gradient(to bottom, transparent, rgba(74, 222, 128, .15), transparent);
        pointer-events: none;
    }

    /* Logo */
    .sb-logo {
        padding: 16px 14px 14px;
        border-bottom: 1px solid var(--sb-border);
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
    }

    .sb-logo-mark {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border: 1px solid rgba(74, 222, 128, .25);
        box-shadow: 0 0 12px rgba(74, 222, 128, .15);
    }

    .sb-brand-logo {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .sb-logo-text .name {
        font-family: 'Space Grotesk', sans-serif;
        font-size: .88rem;
        font-weight: 700;
        color: #fff;
        letter-spacing: .2px;
    }

    .sb-logo-text .tag {
        font-size: .58rem;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: var(--sb-text);
    }

    /* Search */
    .sb-search {
        margin: 10px 10px 4px;
        position: relative;
    }

    .sb-search input {
        width: 100%;
        background: rgba(255, 255, 255, .04);
        border: 1px solid rgba(255, 255, 255, .07);
        border-radius: 8px;
        padding: 7px 10px 7px 30px;
        font-size: .75rem;
        color: #94a3b8;
        font-family: 'Inter', sans-serif;
        transition: border-color .15s;
    }

    .sb-search input:focus {
        outline: none;
        border-color: rgba(74, 222, 128, .25);
        color: #e2e8f0;
    }

    .sb-search input::placeholder {
        color: #2e3a4e;
    }

    .sb-search .si {
        position: absolute;
        left: 9px;
        top: 50%;
        transform: translateY(-50%);
        font-size: .78rem;
        color: #2e3a4e;
    }

    /* Sections */
    .sb-section {
        font-size: .58rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .8px;
        color: #1e2a3a;
        padding: 14px 14px 4px;
    }

    /* Links */
    .sb-link {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 8px 10px;
        margin: 1px 8px;
        border-radius: 8px;
        text-decoration: none;
        color: var(--sb-text);
        font-size: .8rem;
        font-weight: 500;
        transition: background .15s, color .15s;
        position: relative;
        font-family: 'Inter', sans-serif;
    }

    .sb-link:hover {
        background: var(--sb-hover);
        color: var(--sb-text-on);
        text-decoration: none;
    }

    .sb-link.active {
        background: var(--sb-active);
        color: var(--accent);
    }

    .sb-link.active .sb-link-text {
        color: var(--accent);
    }

    .sb-link.active::before {
        content: '';
        position: absolute;
        left: -8px;
        top: 50%;
        transform: translateY(-50%);
        width: 2px;
        height: 55%;
        background: var(--accent);
        border-radius: 0 2px 2px 0;
        box-shadow: 0 0 6px rgba(74, 222, 128, .6);
    }

    .sb-link.locked {
        opacity: .3;
        cursor: not-allowed;
        pointer-events: none;
    }

    .sb-link.locked::after {
        content: '\F4C1';
        font-family: 'bootstrap-icons';
        position: absolute;
        right: 12px;
        font-size: .65rem;
    }

    /* Icons */
    .sb-icon {
        width: 28px;
        height: 28px;
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .82rem;
        flex-shrink: 0;
        background: rgba(255, 255, 255, .04);
        color: var(--sb-text);
        transition: background .15s, color .15s;
    }

    .sb-link:hover .sb-icon {
        background: rgba(74, 222, 128, .1);
        color: var(--accent);
    }

    .sb-link.active .sb-icon {
        background: rgba(74, 222, 128, .15);
        color: var(--accent);
    }

    /* Specific icon colors on active/specific pages */
    .icon-green {
        background: rgba(74, 222, 128, .1) !important;
        color: #4ade80 !important;
    }

    .icon-blue {
        background: rgba(96, 165, 250, .1) !important;
        color: #60a5fa !important;
    }

    .icon-orange {
        background: rgba(251, 191, 36, .1) !important;
        color: #fbbf24 !important;
    }

    .icon-teal {
        background: rgba(34, 211, 238, .1) !important;
        color: #22d3ee !important;
    }

    .icon-gray {
        background: rgba(100, 116, 139, .1) !important;
        color: #94a3b8 !important;
    }

    .icon-red {
        background: rgba(248, 113, 113, .1) !important;
        color: #f87171 !important;
    }

    .icon-purple {
        background: rgba(167, 139, 250, .1) !important;
        color: #a78bfa !important;
    }

    .sb-divider {
        border: none;
        border-top: 1px solid var(--sb-border);
        margin: 4px 0;
    }

    .sb-badge {
        margin-left: auto;
        background: rgba(74, 222, 128, .15);
        color: #4ade80;
        font-size: .6rem;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 4px;
        min-width: 16px;
        text-align: center;
    }

    /* User block */
    .sb-user {
        margin-top: auto;
        padding: 12px 14px;
        border-top: 1px solid var(--sb-border);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .sb-avatar {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        background: linear-gradient(135deg, #16a34a, #052e16);
        border: 1px solid rgba(74, 222, 128, .3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Space Grotesk', sans-serif;
        font-weight: 700;
        font-size: .75rem;
        color: var(--accent);
        flex-shrink: 0;
    }

    .sb-username {
        font-size: .77rem;
        font-weight: 600;
        color: #e2e8f0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 120px;
    }

    .sb-userrole {
        font-size: .62rem;
        color: var(--sb-text);
    }

    .sb-logout {
        margin-left: auto;
        color: var(--sb-text);
        font-size: .9rem;
        padding: 5px;
        border-radius: 6px;
        transition: background .15s, color .15s;
        text-decoration: none;
        flex-shrink: 0;
    }

    .sb-logout:hover {
        background: rgba(248, 113, 113, .1);
        color: #f87171;
    }

    /* Mobile toggle */
    .sb-toggle {
        display: none;
        position: fixed;
        top: 10px;
        left: 10px;
        z-index: 1050;
        background: var(--sb-bg);
        border: 1px solid var(--sb-border);
        color: #fff;
        width: 38px;
        height: 38px;
        border-radius: 8px;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        cursor: pointer;
    }

    .sb-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .7);
        z-index: 1039;
        opacity: 0;
        pointer-events: none;
        transition: opacity .25s;
    }

    .sb-overlay.open {
        opacity: 1;
        pointer-events: auto;
    }

    @media(max-width:768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .sb-toggle {
            display: flex;
        }

        .sb-overlay {
            display: block;
        }
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

    <div class="sb-search">
        <i class="bi bi-search si"></i>
        <input type="text" placeholder="Search">
    </div>

    <div class="sb-section">Main Menu</div>

    <a href="dashboard.php" class="sb-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
        <span class="sb-icon icon-green"><i class="bi bi-grid-1x2-fill"></i></span>
        <span class="sb-link-text">Dashboard</span>
    </a>

    <a href="sales.php" class="sb-link <?= $activePage === 'sales' ? 'active' : '' ?>">
        <span class="sb-icon icon-orange"><i class="bi bi-receipt"></i></span>
        <span class="sb-link-text">Daily Transaction</span>
    </a>

    <hr class="sb-divider">
    <div class="sb-section">Reports</div>

    <a href="sales-history.php" class="sb-link <?= $activePage === 'sales_history' ? 'active' : '' ?>">
        <span class="sb-icon icon-teal"><i class="bi bi-clock-history"></i></span>
        <span class="sb-link-text">Sales History</span>
    </a>

    <a href="monthly-report.php" class="sb-link <?= $activePage === 'monthly_report' ? 'active' : '' ?>">
        <span class="sb-icon icon-blue"><i class="bi bi-bar-chart-line-fill"></i></span>
        <span class="sb-link-text">Monthly Report</span>
    </a>

    <hr class="sb-divider">
    <div class="sb-section">Inventory</div>

    <a href="inventory.php" class="sb-link <?= $activePage === 'inventory' ? 'active' : '' ?>">
        <span class="sb-icon icon-purple"><i class="bi bi-box-seam-fill"></i></span>
        <span class="sb-link-text">Products & Stock</span>
    </a>

    <hr class="sb-divider">
    <div class="sb-section">
        Finance
        <?php if (!$isOwner): ?><span style="font-size:.48rem;color:#f87171;margin-left:4px;">● Owner
                Only</span><?php endif; ?>
    </div>

    <?php if ($isOwner): ?>
        <a href="expenses.php" class="sb-link <?= $activePage === 'expenses' ? 'active' : '' ?>">
            <span class="sb-icon icon-red"><i class="bi bi-wallet2"></i></span>
            <span class="sb-link-text">Expenses</span>
            <?php
            if (isset($conn)) {
                $expToday = (int) ($conn->query("SELECT COUNT(*) AS c FROM expenses WHERE expense_date = CURDATE()")->fetch_assoc()['c'] ?? 0);
                if ($expToday > 0)
                    echo "<span class='sb-badge'>{$expToday}</span>";
            }
            ?>
        </a>
    <?php else: ?>
        <span class="sb-link locked">
            <span class="sb-icon"><i class="bi bi-wallet2"></i></span>
            <span class="sb-link-text">Expenses</span>
        </span>
    <?php endif; ?>

    <hr class="sb-divider">
    <div class="sb-section">Account</div>

    <a href="profile.php" class="sb-link <?= $activePage === 'profile' ? 'active' : '' ?>">
        <span class="sb-icon icon-gray"><i class="bi bi-person-gear"></i></span>
        <span class="sb-link-text"><?= $isOwner ? 'Profile & Users' : 'My Profile' ?></span>
    </a>

    <div class="sb-user">
        <div class="sb-avatar"><?= htmlspecialchars($initials) ?></div>
        <div style="overflow:hidden;">
            <div class="sb-username"><?= htmlspecialchars($userName) ?></div>
            <div class="sb-userrole"><?= $isOwner ? '⬡ Owner' : '◈ Manager' ?></div>
        </div>
        <a href="backend/auth.php?action=logout" class="sb-logout" title="Log out">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>

</aside>

<script>
    (function () {
        const toggle = document.getElementById('sbToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sbOverlay');
        toggle?.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('open'); });
        overlay?.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('open'); });
    })();
</script>
