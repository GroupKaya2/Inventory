<?php
session_start();
include "db.php";

$name = $_POST['name'];
$email = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

$sql = "INSERT INTO users (name, email, password)
        VALUES ('$name', '$email', '$password')";

if ($conn->query($sql) === TRUE) {
    header("Location: index.php");
} else {
    $_SESSION['error'] = "Email already exists!";
    header("Location: register.php");
}
?>
