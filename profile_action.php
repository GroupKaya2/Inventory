<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
include 'db.php';

$action = $_POST['action'] ?? '';
$userId = (int)$_SESSION['user_id'];
$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';

// ── UPDATE PROFILE ────────────────────────────────────────
if ($action === 'update_profile') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    if (!$name || !$email) { echo json_encode(['success'=>false,'message'=>'Name and email required.']); exit; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'message'=>'Invalid email format.']); exit; }

    // Check email not taken by another user
    $chk = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
    $chk->bind_param('si', $email, $userId);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) { echo json_encode(['success'=>false,'message'=>'Email already in use by another account.']); exit; }
    $chk->close();

    $stmt = $conn->prepare("UPDATE users SET name=?, email=? WHERE id=?");
    $stmt->bind_param('ssi', $name, $email, $userId);
    if ($stmt->execute()) {
        $_SESSION['user']  = $name;
        $_SESSION['email'] = $email;
        echo json_encode(['success'=>true,'message'=>'Saved successfully']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Update failed: '.$conn->error]);
    }
    $stmt->close();
    exit;
}

// ── CHANGE PASSWORD ───────────────────────────────────────
if ($action === 'change_password') {
    $current = $_POST['current'] ?? '';
    $newPass = $_POST['new']     ?? '';
    if (!$current || !$newPass) { echo json_encode(['success'=>false,'message'=>'All fields required.']); exit; }
    if (strlen($newPass) < 6)   { echo json_encode(['success'=>false,'message'=>'Password must be at least 6 characters.']); exit; }

    $row = $conn->prepare("SELECT password FROM users WHERE id=?");
    $row->bind_param('i', $userId);
    $row->execute();
    $hash = $row->get_result()->fetch_assoc()['password'] ?? '';
    $row->close();

    if (!password_verify($current, $hash)) { echo json_encode(['success'=>false,'message'=>'Current password is incorrect.']); exit; }

    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $upd->bind_param('si', $newHash, $userId);
    if ($upd->execute()) {
        echo json_encode(['success'=>true,'message'=>'Password updated successfully']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Failed: '.$conn->error]);
    }
    $upd->close();
    exit;
}

// ── DELETE USER (owner only) ──────────────────────────────
if ($action === 'delete_user') {
    if (!$isOwner) { echo json_encode(['success'=>false,'message'=>'Owner only.']); exit; }
    $targetId = (int)($_POST['user_id'] ?? 0);
    if ($targetId <= 0 || $targetId === $userId) { echo json_encode(['success'=>false,'message'=>'Invalid user.']); exit; }

    // Cannot delete owner accounts
    $roleChk = $conn->prepare("SELECT role FROM users WHERE id=?");
    $roleChk->bind_param('i', $targetId);
    $roleChk->execute();
    $targetRole = $roleChk->get_result()->fetch_assoc()['role'] ?? '';
    $roleChk->close();
    if ($targetRole === 'owner') { echo json_encode(['success'=>false,'message'=>'Cannot delete an owner account.']); exit; }

    $del = $conn->prepare("DELETE FROM users WHERE id=?");
    $del->bind_param('i', $targetId);
    if ($del->execute() && $del->affected_rows > 0) {
        echo json_encode(['success'=>true,'message'=>'User removed.']);
    } else {
        echo json_encode(['success'=>false,'message'=>'User not found.']);
    }
    $del->close();
    exit;
}

echo json_encode(['success'=>false,'message'=>'Unknown action.']);
$conn->close();