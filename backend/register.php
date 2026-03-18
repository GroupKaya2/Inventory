<?php
// backend/register.php — Handles account registration

session_start();
require_once __DIR__ . '/db.php';

$name     = trim($_POST['name']     ?? '');
$email    = trim($_POST['email']    ?? '');
$password = $_POST['password']      ?? '';

// Basic validation
if ($name === '' || $email === '' || $password === '') {
    $_SESSION['error'] = "All fields are required.";
    header("Location: ../signup.php");
    exit();
}

if (strlen($password) < 6) {
    $_SESSION['error'] = "Password must be at least 6 characters.";
    header("Location: ../signup.php");
    exit();
}

// Check how many users exist (first user = owner, rest = manager)
$result    = $conn->query("SELECT COUNT(*) AS cnt FROM users");
$userCount = (int)($result->fetch_assoc()['cnt'] ?? 0);

$isOwnerSession = isset($_SESSION['role']) && $_SESSION['role'] === 'owner';

// Block if users exist and not called by owner
if ($userCount > 0 && !$isOwnerSession) {
    $_SESSION['error'] = "Registration is closed.";
    header("Location: ../signup.php");
    exit();
}

// First user becomes owner, owner-created accounts become managers
$role = ($userCount === 0) ? 'owner' : 'manager';

// Check if email already taken
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($exists) {
    $_SESSION['error'] = "That email is already registered.";
    header("Location: ../signup.php");
    exit();
}

// Create the account
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);

if ($stmt->execute()) {
    $stmt->close();

    if ($isOwnerSession) {
        // Owner added a manager
        $_SESSION['success'] = "Manager account created for {$name}.";
        header("Location: ../dashboard.php");
    } else {
        // First ever account — go to login
        $_SESSION['success'] = "Account created! Please log in.";
        header("Location: ../login.php");
    }
    exit();
}

$error = $stmt->error;
$stmt->close();
$_SESSION['error'] = "Failed to create account. " . $error;
header("Location: ../signup.php");
exit();
