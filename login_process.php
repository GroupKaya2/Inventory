<?php
session_start();
include "db.php";

$email = $_POST['email'];
$password = $_POST['password'];
$selected_role = $_POST['role'];

$sql = "SELECT * FROM users WHERE email='$email'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {

        if ($selected_role != $user['role']) {
            $_SESSION['error'] = "Invalid role selected!";
            header("Location: index.php");
            exit();
        }

        // Set session variables
        $_SESSION['user'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_id'] = $user['id'];

        // ✅ Manager will see "Hello (NAME)"
        header("Location: dashboard.php");
        exit();

    } else {
        $_SESSION['error'] = "Invalid password!";
        header("Location: index.php");
        exit();
    }

} else {
    $_SESSION['error'] = "User not found!";
    header("Location: index.php");
    exit();
}
?>
