<?php
/**
 * role_access.php
 * Include this AFTER session_start() on every page.
 * Provides helper functions for role-based access control.
 *
 * Roles:  'owner'   = full access (Admin)
 *         'manager' = limited access (read + sales only)
 */

// ── Get current user role ────────────────────────────────────────
function userRole(): string {
    return $_SESSION['role'] ?? 'manager';
}

function isOwner(): bool {
    return userRole() === 'owner';
}

function isManager(): bool {
    return userRole() === 'manager';
}

/**
 * Deny access to a page entirely if user is not owner.
 * Redirects manager away with an error flash.
 */
function ownerOnly(string $redirectTo = 'dashboard.php'): void {
    if (!isOwner()) {
        $_SESSION['access_error'] = 'Access denied. Owner account required.';
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Return HTML for a "locked" badge shown to managers
 * when a feature exists but is view-only.
 */
function lockedBadge(string $tip = 'Owner only'): string {
    return "<span class='badge bg-secondary ms-1' title='$tip' style='font-size:.6rem;'>
                <i class='bi bi-lock-fill'></i> $tip
            </span>";
}

/**
 * Render a disabled button (manager sees it greyed out).
 */
function disabledIfManager(string $extraClasses = ''): string {
    return isManager() ? "disabled title='Owner only' $extraClasses" : $extraClasses;
}