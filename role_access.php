<?php
/**
 * role_access.php
 * Include this AFTER session_start() on every page.
 * Roles: 'owner' = full access, 'manager' = limited access
 */

function userRole(): string {
    return $_SESSION['role'] ?? 'manager';
}

function isOwner(): bool {
    return userRole() === 'owner';
}

function isManager(): bool {
    return userRole() === 'manager';
}

function ownerOnly(string $redirectTo = 'dashboard.php'): void {
    if (!isOwner()) {
        $_SESSION['access_error'] = 'Access denied. Owner account required.';
        header("Location: $redirectTo");
        exit;
    }
}

function lockedBadge(string $tip = 'Owner only'): string {
    return "<span class='badge bg-secondary ms-1' title='$tip' style='font-size:.6rem;'>
                <i class='bi bi-lock-fill'></i> $tip
            </span>";
}

function disabledIfManager(string $extraClasses = ''): string {
    return isManager() ? "disabled title='Owner only' $extraClasses" : $extraClasses;
}

// Check if current user can delete records
function canDelete(): bool {
    return isOwner();
}

// Check if current user can modify pricing
function canModifyPricing(): bool {
    return isOwner();
}

// Check if current user can manage users
function canManageUsers(): bool {
    return isOwner();
}

// Check if current user can export
function canExport(): bool {
    return isOwner();
}

// Check if current user can view finance
function canViewFinance(): bool {
    return isOwner();
}