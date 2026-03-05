<?php
session_start();
include "db.php";

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($name === '' || $email === '' || $password === '') {
    $_SESSION['error'] = "All fields are required.";
    header("Location: signup.php");
    exit();
}
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM users");
$stmt->execute();
$countRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$userCount = intval($countRow['cnt']);
$isOwner = isset($_SESSION['role']) && $_SESSION['role'] === 'owner';

// If users exist and user is not owner, block registration
if ($userCount > 0 && !$isOwner) {
    $_SESSION['error'] = "Registration is disabled. Please login.";
    header("Location: index.php");
    exit();
}

// Determine role: first user becomes owner, subsequent users created by owner become manager
$role = ($userCount === 0) ? 'owner' : 'manager';

// Check if email already exists (defensive)
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($exists) {
    $_SESSION['error'] = "Email already exists!";
    header("Location: signup.php");
    exit();
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $name, $email, $hashed_password, $role);

if ($stmt->execute()) {
    $stmt->close();
    
    if ($isOwner) {
        // Owner created a new account, redirect to dashboard
        $_SESSION['success'] = ucfirst($role) . " account created successfully!";
        header("Location: dashboard.php");
    } else {
        // First user registration, redirect to login
        $_SESSION['success'] = "Owner account created. Please login.";
        header("Location: index.php");
    }
    exit();
}

$err = $stmt->error;
$stmt->close();
$_SESSION['error'] = "Failed to create account: " . $err;
header("Location: signup.php");
exit();
?>

