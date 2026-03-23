<?php

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
$userId  = (int)$_SESSION['user_id'];
$action  = $_POST['action'] ?? '';

//UPDATE PROFILE
if ($action === 'update_profile') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$name || !$email) {
        echo json_encode(['success' => false, 'message' => 'Name and email are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    // Make sure email isn't taken by someone else
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
        $_SESSION['email'] = $email;
        echo json_encode(['success' => true, 'message' => 'Profile updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed.']);
    }
    $stmt->close();
    exit;
}

// CHANGE PASSWORD
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

    // Get current password hash
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $hash = $stmt->get_result()->fetch_assoc()['password'] ?? '';
    $stmt->close();

    if (!password_verify($current, $hash)) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $stmt    = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param('si', $newHash, $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
    }
    $stmt->close();
    exit;
}

// ── DELETE USER (owner only)
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

    // Can't delete another owner
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
