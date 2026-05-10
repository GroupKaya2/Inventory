<?php
// Handles login and logout
session_start();
require_once __DIR__ . '/db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// LOGIN
if ($action === 'login') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $_SESSION['error'] = "Email and password are required.";
        header("Location: ../login.php");
        exit();
    }

    // Sanitize & validate email
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: ../login.php");
        exit();
    }

    // Look up user
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Verify password and login if correct
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user'] = $user['name'];
        $_SESSION['email'] = base64_encode($user['email']);
        $_SESSION['role'] = $user['role'];

        header("Location: ../dashboard.php");
        exit();
    }

    $_SESSION['error'] = "Invalid email or password.";
    header("Location: ../login.php");
    exit();
}

// LOGOUT
if ($action === 'logout') {

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p["path"],
            $p["domain"],
            $p["secure"],
            $p["httponly"]
        );
    }
    session_destroy();
    header("Location: ../login.php");
    exit();
}

header("Location: ../login.php");
exit();
