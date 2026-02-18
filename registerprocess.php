<?php
session_start();
include "db.php";

// Only owner can create manager accounts
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: " . (isset($_SESSION['user']) ? "dashboard.php" : "index.php"));
    exit();
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = 'manager'; // Only managers can be created by owner

if ($name === '' || $email === '' || $password === '') {
    $_SESSION['error'] = "All fields are required!";
    header("Location: register.php");
    exit();
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($exists) {
    $_SESSION['error'] = "Email already exists!";
    header("Location: register.php");
    exit();
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $name, $email, $hashed_password, $role);

if ($stmt->execute()) {
    $stmt->close();
    $_SESSION['success'] = "Manager account created successfully!";
    header("Location: dashboard.php");
    exit();
} else {
    $err = $stmt->error;
    $stmt->close();
    $_SESSION['error'] = "Failed to create manager account!";
    if ($err) {
        $_SESSION['error'] .= " " . $err;
    }
    header("Location: register.php");
    exit();
}
?>
