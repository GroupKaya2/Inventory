<?php
session_start();
include "db.php";

$name = $_POST['name'];
$email = $_POST['email'];
$password = $_POST['password'];
$role = $_POST['role'];

// Check if email already exists
$check = "SELECT * FROM users WHERE email='$email'";
$result = $conn->query($check);

if ($result->num_rows > 0) {
    $_SESSION['error'] = "Email already exists!";
    header("Location: register.php");
    exit();
}

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert new user
$sql = "INSERT INTO users (name, email, password, role)
        VALUES ('$name', '$email', '$hashed_password', '$role')";

if ($conn->query($sql) === TRUE) {
    $_SESSION['success'] = "Registration successful! You can now login.";
    header("Location: index.php"); // Send user to login page
    exit();
} else {
    $_SESSION['error'] = "Registration failed!";
    header("Location: register.php");
    exit();
}
?>
