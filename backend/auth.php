<?php
// backend/auth.php — Handles login and logout

session_start();
require_once __DIR__ . '/../backend/db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── LOGIN ──────────────────────────────────────────────
if ($action === 'login') {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if ($email === '' || $password === '') {
        $_SESSION['error'] = "Email and password are required.";
        header("Location: ../login.php");
        exit();
    }

    // Find user by email
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Check password
    if ($user && password_verify($password, $user['password'])) {
        // Save session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = $user['name'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['role']    = $user['role'];

        header("Location: ../dashboard.php");
        exit();
    }

    // Wrong credentials
    $_SESSION['error'] = "Invalid email or password.";
    header("Location: ../login.php");
    exit();
}

// ── LOGOUT ─────────────────────────────────────────────
if ($action === 'logout') {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Unknown action — redirect to login
header("Location: ../login.php");
exit();
