<?php
session_start();
include "db.php";

// Only owner can delete users
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'owner') {
    die("Access Denied ❌");
}

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);

    if ($user_id == $_SESSION['user_id']) {
        die("You cannot delete yourself!");
    }

    // Prevent deleting the owner account
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        die("User not found.");
    }
    if ($row['role'] === 'owner') {
        die("You cannot delete the owner account.");
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: dashboard.php");
        exit();
    } else {
        $err = $stmt->error;
        $stmt->close();
        echo "Error deleting user: " . htmlspecialchars($err);
    }
} else {
    die("No user specified.");
}
?>
