<?php
//Profile & user management API

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
$userId  = (int) $_SESSION['user_id'];
$action  = $_POST['action'] ?? '';

// ── UPDATE OWN PROFILE (name + email) ───────────────────
if ($action === 'update_profile') {
    $name  = strip_tags(trim($_POST['name']  ?? ''));
    $email = trim($_POST['email'] ?? '');

    if (!$name || !$email) {
        echo json_encode(['success' => false, 'message' => 'Name and email are required.']);
        exit;
    }

    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    if (strlen($name) < 2 || strlen($name) > 100) {
        echo json_encode(['success' => false, 'message' => 'Name must be 2–100 characters.']);
        exit;
    }

    // Check email not taken by someone else
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->bind_param('si', $email, $userId);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already in use by another account.']);
        exit;
    }
    $check->close();

    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
    $stmt->bind_param('ssi', $name, $email, $userId);

    if ($stmt->execute()) {
        $_SESSION['user']  = $name;
        $_SESSION['email'] = base64_encode($email); // keep encoded like auth.php
        echo json_encode(['success' => true, 'message' => 'Profile updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed.']);
    }
    $stmt->close();
    exit;
}

// ── CHANGE OWN PASSWORD ─────────────────────────────────
if ($action === 'change_password') {
    $current = $_POST['current'] ?? '';
    $newPass = $_POST['new']     ?? '';

    if (!$current || !$newPass) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    if (strlen($newPass) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $hash = $stmt->get_result()->fetch_assoc()['password'] ?? '';
    $stmt->close();

    if (!password_verify($current, $hash)) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $newHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt    = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param('si', $newHash, $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
    }
    $stmt->close();
    exit;
}

// ── OWNER: GET ALL USERS ────────────────────────────────
if ($action === 'get_users') {
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only.']);
        exit;
    }

    $hasCreatedAt = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'")->num_rows > 0;
    $fields = $hasCreatedAt ? 'id, name, email, role, created_at' : 'id, name, email, role';
    $r = $conn->query("SELECT $fields FROM users ORDER BY role DESC, name ASC");
    $users = [];
    if ($r) while ($row = $r->fetch_assoc()) $users[] = $row;

    echo json_encode(['success' => true, 'users' => $users]);
    exit;
}

// ── OWNER: UPDATE ANY USER'S EMAIL OR PASSWORD ──────────
if ($action === 'update_user') {
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only.']);
        exit;
    }

    $targetId = (int)($_POST['user_id'] ?? 0);
    if ($targetId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
        exit;
    }

    // Fetch target user to check role
    $check = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $check->bind_param('i', $targetId);
    $check->execute();
    $targetUser = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$targetUser) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    // Owner cannot edit another owner's account (security)
    if ($targetUser['role'] === 'owner' && $targetId !== $userId) {
        echo json_encode(['success' => false, 'message' => 'Cannot modify another owner account.']);
        exit;
    }

    $newEmail    = trim($_POST['new_email']    ?? '');
    $newName     = strip_tags(trim($_POST['new_name'] ?? ''));
    $newPassword = $_POST['new_password'] ?? '';

    $updates = [];
    $types   = '';
    $params  = [];

    // Update name if provided
    if ($newName !== '') {
        if (strlen($newName) < 2 || strlen($newName) > 100) {
            echo json_encode(['success' => false, 'message' => 'Name must be 2–100 characters.']);
            exit;
        }
        $updates[] = 'name = ?';
        $types    .= 's';
        $params[]  = $newName;
    }

    // Update email if provided
    if ($newEmail !== '') {
        $newEmail = filter_var($newEmail, FILTER_SANITIZE_EMAIL);
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        // Check uniqueness
        $dup = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $dup->bind_param('si', $newEmail, $targetId);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'That email is already in use.']);
            exit;
        }
        $dup->close();

        $updates[] = 'email = ?';
        $types    .= 's';
        $params[]  = $newEmail;
    }

    // Update password if provided
    if ($newPassword !== '') {
        if (strlen($newPassword) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
            exit;
        }
        $hashed    = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $updates[] = 'password = ?';
        $types    .= 's';
        $params[]  = $hashed;
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'Nothing to update.']);
        exit;
    }

    $types   .= 'i';
    $params[] = $targetId;
    $sql      = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt     = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        // If owner edited their own email/name, update session too
        if ($targetId === $userId) {
            if ($newName  !== '') $_SESSION['user']  = $newName;
            if ($newEmail !== '') $_SESSION['email'] = base64_encode($newEmail);
        }
        echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// ── OWNER: DELETE USER ──────────────────────────────────
if ($action === 'delete_user') {
    if (!$isOwner) {
        echo json_encode(['success' => false, 'message' => 'Owner only.']);
        exit;
    }

    $targetId = (int)($_POST['user_id'] ?? 0);
    if ($targetId <= 0 || $targetId === $userId) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete this user.']);
        exit;
    }

    $roleCheck = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $roleCheck->bind_param('i', $targetId);
    $roleCheck->execute();
    $targetRole = $roleCheck->get_result()->fetch_assoc()['role'] ?? '';
    $roleCheck->close();

    if ($targetRole === 'owner') {
        echo json_encode(['success' => false, 'message' => 'Cannot delete an owner account.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $targetId);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'User removed.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);