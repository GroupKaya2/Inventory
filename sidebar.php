<?php
// Reusable fixed left sidebar for authenticated pages
// Usage: set $activePage = 'dashboard' | 'inventory' | 'profile' before include (optional)

if (!isset($activePage)) {
    $activePage = '';
}

function sidebar_active($key, $activePage) {
    return $key === $activePage ? 'active' : '';
}
?>

<!-- Bootstrap Icons (safe to include multiple times) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    :root { --sidebar-width: 260px; }

    .app-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: var(--sidebar-width);
        background: linear-gradient(180deg, #111827 0%, #0b1220 100%);
        color: #e5e7eb;
        border-right: 1px solid rgba(255,255,255,.06);
        z-index: 1040;
    }
    .app-sidebar .brand {
        padding: 18px 18px 12px;
        font-weight: 700;
        letter-spacing: .2px;
        display: flex;
        gap: 10px;
        align-items: center;
        border-bottom: 1px solid rgba(255,255,255,.06);
    }
    .app-sidebar .brand .dot {
        width: 10px; height: 10px; border-radius: 999px;
        background: #6366f1;
        box-shadow: 0 0 0 6px rgba(99,102,241,.15);
    }
    .app-sidebar .nav {
        padding: 12px;
    }
    .app-sidebar .nav a {
        color: #e5e7eb;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 10px;
        margin-bottom: 6px;
        transition: background-color .15s ease, transform .15s ease;
    }
    .app-sidebar .nav a:hover {
        background: rgba(255,255,255,.08);
        transform: translateX(2px);
    }
    .app-sidebar .nav a.active {
        background: rgba(99,102,241,.20);
        border: 1px solid rgba(99,102,241,.35);
    }
    .app-sidebar .nav a .icon {
        width: 22px;
        display: inline-flex;
        justify-content: center;
        opacity: .95;
    }
    .app-main {
        margin-left: var(--sidebar-width);
        min-height: 100vh;
        background: #f8fafc;
    }

    /* Mobile: collapse into offcanvas-like drawer */
    .app-topbar {
        display: none;
        position: sticky;
        top: 0;
        z-index: 1039;
        background: #ffffff;
        border-bottom: 1px solid rgba(15, 23, 42, .08);
    }
    .app-topbar .btn {
        border-radius: 10px;
    }
    .app-sidebar-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(2, 6, 23, .55);
        z-index: 1035;
    }
    @media (max-width: 767.98px) {
        .app-topbar { display: block; }
        .app-main { margin-left: 0; }
        .app-sidebar {
            transform: translateX(-100%);
            transition: transform .2s ease;
        }
        .app-sidebar.open { transform: translateX(0); }
        .app-sidebar-backdrop.show { display: block; }
    }
</style>

<div class="app-sidebar-backdrop" id="appSidebarBackdrop" onclick="window.closeSidebar && window.closeSidebar()"></div>

<aside class="app-sidebar" id="appSidebar" aria-label="Sidebar Navigation">
    <div class="brand">
        <span class="dot"></span>
        <div>
            <div style="font-size: 14px; line-height: 1.1;">Smart Inventory</div>
            <div style="font-size: 12px; opacity: .75;">Owner System</div>
        </div>
    </div>
    <nav class="nav flex-column">
        <a class="<?php echo sidebar_active('dashboard', $activePage); ?>" href="dashboard.php">
            <span class="icon"><i class="bi bi-speedometer2"></i></span>
            <span>Dashboard</span>
        </a>
        <a class="<?php echo sidebar_active('inventory', $activePage); ?>" href="inventory.php">
            <span class="icon"><i class="bi bi-box-seam"></i></span>
            <span>Inventory</span>
        </a>
        <a class="<?php echo sidebar_active('profile', $activePage); ?>" href="profile.php">
            <span class="icon"><i class="bi bi-person-circle"></i></span>
            <span>Profile</span>
        </a>
        <a href="logout.php">
            <span class="icon"><i class="bi bi-box-arrow-right"></i></span>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<div class="app-topbar p-2">
    <div class="d-flex align-items-center justify-content-between">
        <button class="btn btn-outline-primary btn-sm" type="button" onclick="window.toggleSidebar && window.toggleSidebar()">
            <i class="bi bi-list"></i> Menu
        </button>
        <div class="small text-muted">
            <?php echo isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : ''; ?>
        </div>
    </div>
</div>

<script>
    window.toggleSidebar = function () {
        const s = document.getElementById('appSidebar');
        const b = document.getElementById('appSidebarBackdrop');
        if (!s || !b) return;
        s.classList.toggle('open');
        b.classList.toggle('show');
    }
    window.closeSidebar = function () {
        const s = document.getElementById('appSidebar');
        const b = document.getElementById('appSidebarBackdrop');
        if (!s || !b) return;
        s.classList.remove('open');
        b.classList.remove('show');
    }
</script>


