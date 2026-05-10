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
    --sb-w:      220px;
    --sb-bg:     #0b0f18;
    --sb-border: rgba(255,255,255,.05);
    --sb-hover:  rgba(74,222,128,.06);
    --sb-active: rgba(74,222,128,.1);
    --sb-text:   #4b5a6e;
    --sb-on:     #e2e8f0;
    --accent:    #4ade80;
    --topbar-h:  52px;
}

/* ── Shell ── */
.sidebar {
    position:fixed;top:0;left:0;
    width:var(--sb-w);height:100vh;
    background:var(--sb-bg);
    border-right:1px solid var(--sb-border);
    display:flex;flex-direction:column;
    z-index:1040;overflow-y:auto;overflow-x:hidden;
    transition:transform .28s cubic-bezier(.4,0,.2,1);
    scrollbar-width:none;
}
.sidebar::-webkit-scrollbar { display:none; }
.sidebar::after {
    content:'';position:absolute;top:0;right:0;bottom:0;width:1px;
    background:linear-gradient(to bottom,transparent,rgba(74,222,128,.15),transparent);
    pointer-events:none;
}

/* ── Logo ── */
.sb-logo {
    padding:16px 14px 14px;
    border-bottom:1px solid var(--sb-border);
    display:flex;align-items:center;gap:10px;
    text-decoration:none;flex-shrink:0;
}
.sb-logo-mark {
    width:34px;height:34px;border-radius:8px;overflow:hidden;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
    border:1px solid rgba(74,222,128,.25);
    box-shadow:0 0 12px rgba(74,222,128,.15);
}
.sb-brand-logo { width:100%;height:100%;object-fit:cover;display:block; }
.sb-logo-text .name { font-family:'Space Grotesk',sans-serif;font-size:.88rem;font-weight:700;color:#fff; }
.sb-logo-text .tag  { font-size:.58rem;text-transform:uppercase;letter-spacing:.5px;color:var(--sb-text); }

/* ── Search ── */
.sb-search { margin:10px 10px 2px;position:relative;flex-shrink:0; }
.sb-search-input {
    width:100%;
    background:rgba(255,255,255,.05);
    border:1px solid rgba(255,255,255,.08);
    border-radius:8px;
    padding:8px 30px 8px 30px;
    font-size:.78rem;color:#94a3b8;
    font-family:'Inter',sans-serif;
    transition:border-color .2s,background .2s;
    outline:none;
    -webkit-appearance:none;
}
.sb-search-input:focus {
    border-color:rgba(74,222,128,.4);
    background:rgba(74,222,128,.04);
    color:#e2e8f0;
    box-shadow:0 0 0 3px rgba(74,222,128,.06);
}
.sb-search-input::placeholder { color:#2e3a4e; }
.sb-search-icon {
    position:absolute;left:9px;top:50%;transform:translateY(-50%);
    font-size:.78rem;color:#2e3a4e;pointer-events:none;
    transition:color .2s;
}
.sb-search:focus-within .sb-search-icon { color:rgba(74,222,128,.6); }
.sb-clear-btn {
    position:absolute;right:7px;top:50%;transform:translateY(-50%);
    font-size:.75rem;color:#4b5a6e;cursor:pointer;
    background:none;border:none;padding:2px 4px;
    line-height:1;border-radius:4px;
    display:none;transition:color .15s,background .15s;
}
.sb-clear-btn:hover { color:#e2e8f0;background:rgba(255,255,255,.06); }
.sb-no-results {
    display:none;padding:10px 14px;
    font-size:.74rem;color:#2e3a4e;
    font-style:italic;text-align:center;
}

/* ── Sections & dividers ── */
.sb-section {
    font-size:.58rem;font-weight:700;text-transform:uppercase;
    letter-spacing:.8px;color:#1e2a3a;
    padding:14px 14px 4px;flex-shrink:0;
}
.sb-divider { border:none;border-top:1px solid var(--sb-border);margin:4px 0;flex-shrink:0; }

/* ── Nav links ── */
.sb-link {
    display:flex;align-items:center;gap:9px;
    padding:8px 10px;margin:1px 8px;border-radius:8px;
    text-decoration:none;color:var(--sb-text);font-size:.8rem;font-weight:500;
    transition:background .15s,color .15s;
    position:relative;font-family:'Inter',sans-serif;
    -webkit-tap-highlight-color:transparent;
    flex-shrink:0;
}
.sb-link:hover { background:var(--sb-hover);color:var(--sb-on);text-decoration:none; }
.sb-link.active { background:var(--sb-active);color:var(--accent); }
.sb-link.active .sb-link-text { color:var(--accent); }
.sb-link.active::before {
    content:'';position:absolute;left:-8px;top:50%;transform:translateY(-50%);
    width:2px;height:55%;background:var(--accent);border-radius:0 2px 2px 0;
    box-shadow:0 0 6px rgba(74,222,128,.6);
}
.sb-link.locked { opacity:.3;cursor:not-allowed;pointer-events:none; }
.sb-link.locked::after { content:'\F4C1';font-family:'bootstrap-icons';position:absolute;right:12px;font-size:.65rem; }
.sb-link.sb-hidden { display:none !important; }

/* ── Icons ── */
.sb-icon {
    width:28px;height:28px;border-radius:7px;
    display:flex;align-items:center;justify-content:center;
    font-size:.82rem;flex-shrink:0;
    background:rgba(255,255,255,.04);color:var(--sb-text);
    transition:background .15s,color .15s;
}
.sb-link:hover .sb-icon { background:rgba(74,222,128,.1);color:var(--accent); }
.sb-link.active .sb-icon { background:rgba(74,222,128,.15);color:var(--accent); }
.icon-green  { background:rgba(74,222,128,.1)!important;  color:#4ade80!important; }
.icon-blue   { background:rgba(96,165,250,.1)!important;  color:#60a5fa!important; }
.icon-orange { background:rgba(251,191,36,.1)!important;  color:#fbbf24!important; }
.icon-teal   { background:rgba(34,211,238,.1)!important;  color:#22d3ee!important; }
.icon-gray   { background:rgba(100,116,139,.1)!important; color:#94a3b8!important; }
.icon-red    { background:rgba(248,113,113,.1)!important; color:#f87171!important; }
.icon-purple { background:rgba(167,139,250,.1)!important; color:#a78bfa!important; }

/* ── Badge ── */
.sb-badge {
    margin-left:auto;background:rgba(74,222,128,.15);color:#4ade80;
    font-size:.6rem;font-weight:700;padding:1px 6px;border-radius:4px;
    min-width:16px;text-align:center;
}

/* ── User block ── */
.sb-user {
    margin-top:auto;padding:12px 14px;
    border-top:1px solid var(--sb-border);
    display:flex;align-items:center;gap:10px;flex-shrink:0;
}
.sb-avatar {
    width:30px;height:30px;border-radius:8px;
    background:linear-gradient(135deg,#16a34a,#052e16);
    border:1px solid rgba(74,222,128,.3);
    display:flex;align-items:center;justify-content:center;
    font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:.75rem;
    color:var(--accent);flex-shrink:0;
}
.sb-username {
    font-size:.77rem;font-weight:600;color:#e2e8f0;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:110px;
}
.sb-userrole { font-size:.62rem;color:var(--sb-text); }
.sb-logout {
    margin-left:auto;color:var(--sb-text);font-size:.9rem;
    padding:6px;border-radius:6px;
    transition:background .15s,color .15s;text-decoration:none;flex-shrink:0;
}
.sb-logout:hover { background:rgba(248,113,113,.1);color:#f87171; }

/* ── Mobile topbar ── */
.sb-topbar {
    display:none;
    position:fixed;top:0;left:0;right:0;
    height:var(--topbar-h);z-index:1041;
    background:#0b0f18;
    border-bottom:1px solid rgba(74,222,128,.1);
    align-items:center;padding:0 14px;gap:10px;
}
.sb-topbar-toggle {
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.08);
    color:#e2e8f0;width:36px;height:36px;border-radius:8px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
    font-size:1.1rem;cursor:pointer;transition:background .15s;
    -webkit-tap-highlight-color:transparent;
}
.sb-topbar-toggle:hover,.sb-topbar-toggle:focus { background:rgba(74,222,128,.1);color:var(--accent); }
.sb-topbar-brand { font-family:'Space Grotesk',sans-serif;font-size:.92rem;font-weight:700;color:#fff; }
.sb-topbar-page  { font-size:.72rem;color:#4b5a6e;margin-left:auto; }

/* ── Overlay ── */
.sb-overlay {
    display:none;position:fixed;inset:0;
    background:rgba(0,0,0,.75);
    z-index:1039;opacity:0;pointer-events:none;
    transition:opacity .28s;
    backdrop-filter:blur(3px);
    -webkit-backdrop-filter:blur(3px);
}
.sb-overlay.open { opacity:1;pointer-events:auto; }

/* ══════════════════
   RESPONSIVE
══════════════════ */
@media (max-width:991px) {
    .sidebar { transform:translateX(-100%); }
    .sidebar.open { transform:translateX(0);box-shadow:10px 0 40px rgba(0,0,0,.6); }
    .sb-topbar  { display:flex; }
    .sb-overlay { display:block; }
}
</style>

<!-- ── Mobile topbar ── -->
<div class="sb-topbar" id="sbTopbar">
    <button class="sb-topbar-toggle" id="sbToggle" aria-label="Open navigation menu" aria-expanded="false" aria-controls="sidebar">
        <i class="bi bi-list" aria-hidden="true"></i>
    </button>
    <span class="sb-topbar-brand">Dspeedway</span>
    <span class="sb-topbar-page">
        <?php
        $pageTitles = [
            'dashboard'     => 'Dashboard',
            'sales'         => 'Daily Transaction',
            'sales_history' => 'Sales History',
            'monthly_report'=> 'Monthly Report',
            'inventory'     => 'Inventory',
            'expenses'      => 'Expenses',
            'profile'       => 'Profile',
        ];
        echo htmlspecialchars($pageTitles[$activePage] ?? '');
        ?>
    </span>
</div>

<div class="sb-overlay" id="sbOverlay" aria-hidden="true"></div>

<aside class="sidebar" id="sidebar" role="navigation" aria-label="Main navigation">

    <!-- Logo -->
    <a href="dashboard.php" class="sb-logo">
        <div class="sb-logo-mark">
            <img src="assets/img/logo.jpg" alt="Dspeedway Logo" class="sb-brand-logo" loading="lazy" width="34" height="34">
        </div>
        <div class="sb-logo-text">
            <div class="name">Dspeedway</div>
            <div class="tag">Inventory System</div>
        </div>
    </a>

    <!-- ── WORKING SEARCH BAR ── -->
    <div class="sb-search">
        <i class="bi bi-search sb-search-icon" aria-hidden="true"></i>
        <input
            type="search"
            id="sbSearch"
            class="sb-search-input"
            placeholder="Search menu…"
            autocomplete="off"
            spellcheck="false"
            aria-label="Search navigation items"
            maxlength="50"
        >
        <button class="sb-clear-btn" id="sbClear" aria-label="Clear search" tabindex="-1">✕</button>
    </div>
    <div class="sb-no-results" id="sbNoResults" role="status" aria-live="polite">No results found</div>

    <!-- ── Nav items ── -->
    <div class="sb-section" data-sb-section>Main Menu</div>

    <a href="dashboard.php" class="sb-link <?= $activePage==='dashboard'?'active':'' ?>" data-sb-label="dashboard main">
        <span class="sb-icon icon-green" aria-hidden="true"><i class="bi bi-grid-1x2-fill"></i></span>
        <span class="sb-link-text">Dashboard</span>
    </a>

    <a href="sales.php" class="sb-link <?= $activePage==='sales'?'active':'' ?>" data-sb-label="daily transaction sales new">
        <span class="sb-icon icon-orange" aria-hidden="true"><i class="bi bi-receipt"></i></span>
        <span class="sb-link-text">Daily Transaction</span>
    </a>

    <hr class="sb-divider" data-sb-divider>
    <div class="sb-section" data-sb-section>Reports</div>

    <a href="sales-history.php" class="sb-link <?= $activePage==='sales_history'?'active':'' ?>" data-sb-label="sales history records">
        <span class="sb-icon icon-teal" aria-hidden="true"><i class="bi bi-clock-history"></i></span>
        <span class="sb-link-text">Sales History</span>
    </a>

    <a href="monthly-report.php" class="sb-link <?= $activePage==='monthly_report'?'active':'' ?>" data-sb-label="monthly report analytics">
        <span class="sb-icon icon-blue" aria-hidden="true"><i class="bi bi-bar-chart-line-fill"></i></span>
        <span class="sb-link-text">Monthly Report</span>
    </a>

    <hr class="sb-divider" data-sb-divider>
    <div class="sb-section" data-sb-section>Inventory</div>

    <a href="inventory.php" class="sb-link <?= $activePage==='inventory'?'active':'' ?>" data-sb-label="inventory products stock items">
        <span class="sb-icon icon-purple" aria-hidden="true"><i class="bi bi-box-seam-fill"></i></span>
        <span class="sb-link-text">Products & Stock</span>
    </a>

    <hr class="sb-divider" data-sb-divider>
    <div class="sb-section" data-sb-section>
        Finance
        <?php if (!$isOwner): ?>
            <span style="font-size:.48rem;color:#f87171;margin-left:4px;" aria-label="Owner only">● Owner Only</span>
        <?php endif; ?>
    </div>

    <?php if ($isOwner): ?>
        <a href="expenses.php" class="sb-link <?= $activePage==='expenses'?'active':'' ?>" data-sb-label="expenses finance budget">
            <span class="sb-icon icon-red" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
            <span class="sb-link-text">Expenses</span>
            <?php
            if (isset($conn)) {
                $expToday = (int)($conn->query("SELECT COUNT(*) AS c FROM expenses WHERE expense_date = CURDATE()")->fetch_assoc()['c'] ?? 0);
                if ($expToday > 0) echo "<span class='sb-badge' aria-label='{$expToday} expenses today'>{$expToday}</span>";
            }
            ?>
        </a>
    <?php else: ?>
        <span class="sb-link locked" aria-disabled="true" data-sb-label="expenses finance">
            <span class="sb-icon" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
            <span class="sb-link-text">Expenses</span>
        </span>
    <?php endif; ?>

    <hr class="sb-divider" data-sb-divider>
    <div class="sb-section" data-sb-section>Account</div>

    <a href="profile.php" class="sb-link <?= $activePage==='profile'?'active':'' ?>" data-sb-label="profile account users settings">
        <span class="sb-icon icon-gray" aria-hidden="true"><i class="bi bi-person-gear"></i></span>
        <span class="sb-link-text"><?= $isOwner ? 'Profile & Users' : 'My Profile' ?></span>
    </a>

    <!-- User block -->
    <div class="sb-user">
        <div class="sb-avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></div>
        <div style="overflow:hidden;min-width:0;">
            <div class="sb-username"><?= htmlspecialchars($userName) ?></div>
            <div class="sb-userrole"><?= $isOwner ? '⬡ Owner' : '◈ Manager' ?></div>
        </div>
        <a href="backend/auth.php?action=logout" class="sb-logout" title="Log out" aria-label="Log out of Dspeedway">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
        </a>
    </div>

</aside>

<script>
(function () {
    'use strict';

    /* ── Elements ── */
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sbOverlay');
    const toggle   = document.getElementById('sbToggle');
    const search   = document.getElementById('sbSearch');
    const clearBtn = document.getElementById('sbClear');
    const noRes    = document.getElementById('sbNoResults');

    if (!sidebar) return;

    /* ══════════════════════════
       SIDEBAR OPEN / CLOSE
    ══════════════════════════ */
    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        toggle?.setAttribute('aria-expanded', 'true');
        /* Focus search on open for quick keyboard nav */
        setTimeout(() => search?.focus(), 280);
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        document.body.style.overflow = '';
        toggle?.setAttribute('aria-expanded', 'false');
    }

    toggle?.addEventListener('click', () => {
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    overlay?.addEventListener('click', closeSidebar);

    /* Close when a nav link is tapped on mobile */
    sidebar.querySelectorAll('a.sb-link').forEach(a => {
        a.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                setTimeout(closeSidebar, 120); /* slight delay for ripple feel */
            }
        });
    });

    /* Swipe left to close */
    let touchStartX = 0, touchStartY = 0;
    sidebar.addEventListener('touchstart', e => {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
    }, { passive: true });
    sidebar.addEventListener('touchend', e => {
        const dx = e.changedTouches[0].clientX - touchStartX;
        const dy = Math.abs(e.changedTouches[0].clientY - touchStartY);
        /* Only close if clearly a horizontal swipe */
        if (dx < -60 && dy < 40) closeSidebar();
    }, { passive: true });

    /* Escape key */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeSidebar(); toggle?.focus(); }
    });

    /* Auto-close overlay on resize to desktop */
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }
    });

    /* ══════════════════════════
       SEARCH FUNCTIONALITY
    ══════════════════════════ */
    const links    = Array.from(sidebar.querySelectorAll('a.sb-link, span.sb-link'));
    const sections = Array.from(sidebar.querySelectorAll('[data-sb-section]'));
    const dividers = Array.from(sidebar.querySelectorAll('[data-sb-divider]'));

    function runSearch(raw) {
        const q = raw.toLowerCase().trim();

        /* Show / hide clear button */
        clearBtn.style.display = q ? 'block' : 'none';

        if (!q) {
            /* Restore all */
            links.forEach(l    => l.classList.remove('sb-hidden'));
            sections.forEach(s => (s.style.display = ''));
            dividers.forEach(d => (d.style.display = ''));
            noRes.style.display = 'none';
            return;
        }

        /* Match links */
        let anyMatch = false;
        links.forEach(link => {
            const haystack = [
                link.dataset.sbLabel || '',
                link.querySelector('.sb-link-text')?.textContent || '',
            ].join(' ').toLowerCase();

            const match = haystack.includes(q);
            link.classList.toggle('sb-hidden', !match);
            if (match) anyMatch = true;
        });

        /* Hide section labels & dividers while searching */
        sections.forEach(s => (s.style.display = 'none'));
        dividers.forEach(d => (d.style.display = 'none'));

        noRes.style.display = anyMatch ? 'none' : 'block';
    }

    search?.addEventListener('input',   () => runSearch(search.value));
    search?.addEventListener('search',  () => runSearch(search.value)); /* fires on ✕ in native search input */
    search?.addEventListener('keydown', e  => {
        if (e.key === 'Escape') { search.value = ''; runSearch(''); search.blur(); }
    });

    clearBtn?.addEventListener('click', () => {
        search.value = '';
        runSearch('');
        search.focus();
    });

})();
</script>
