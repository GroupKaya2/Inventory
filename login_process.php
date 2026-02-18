<?php
session_start();
include "db.php";


$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    $_SESSION['error'] = "Email and password are required.";
    header("Location: index.php");
    exit();
}

$stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_id'] = $user['id'];

        header("Location: dashboard.php");
        exit();
    }

    $_SESSION['error'] = "Invalid password!";
    header("Location: index.php");
    exit();
}

$_SESSION['error'] = "User not found!";
header("Location: index.php");
exit();
?>
