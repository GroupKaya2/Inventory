<?php
session_start();
include "db.php";

// Only owner can delete users
if ($_SESSION['role'] != 'owner') {
    die("Access Denied ❌");
}

if (isset($_GET['id'])) {
    $user_id = $_GET['id'];

    if ($user_id == $_SESSION['user_id']) {
        die("You cannot delete yourself!");
    }

    $sql = "DELETE FROM users WHERE id='$user_id'";
    if ($conn->query($sql) === TRUE) {
        header("Location: dashboard.php");
        exit();
    } else {
        echo "Error deleting user: " . $conn->error;
    }
} else {
    die("No user specified.");
}
?>
