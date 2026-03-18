<?php
// index.php — Entry Point
// Redirects to dashboard if logged in, otherwise to login page

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit();
