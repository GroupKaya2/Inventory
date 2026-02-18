<?php
session_start();
include "db.php";

// Only owner can create manager accounts
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: " . (isset($_SESSION['user']) ? "dashboard.php" : "index.php"));
    exit();
}

$name = $_POST['name'];
$email = $_POST['email'];
$password = $_POST['password'];
$role = 'manager'; // Only managers can be created by owner

$check = "SELECT * FROM users WHERE email='$email'";
$result = $conn->query($check);

if ($result->num_rows > 0) {
    $_SESSION['error'] = "Email already exists!";
    header("Location: register.php");
    exit();
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (name, email, password, role)
        VALUES ('$name', '$email', '$hashed_password', '$role')";

if ($conn->query($sql) === TRUE) {
    $_SESSION['success'] = "Manager account created successfully!";
    header("Location: dashboard.php");
    exit();
} else {
    $_SESSION['error'] = "Failed to create manager account!";
    header("Location: register.php");
    exit();
}
?>
