<?php
session_start();
require_once __DIR__ . '/db.php';

$name     = trim($_POST['name']     ?? '');
$email    = trim($_POST['email']    ?? '');
$password =      $_POST['password'] ?? '';

if ($name === '' || $email === '' || $password === '') {
    $_SESSION['error'] = "All fields are required.";
    header("Location: ../signup.php");
    exit();
}

// Sanitize
$email = filter_var($email, FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email address.";
    header("Location: ../signup.php");
    exit();
}

$name = strip_tags($name);
if (strlen($name) < 2 || strlen($name) > 100) {
    $_SESSION['error'] = "Name must be between 2 and 100 characters.";
    header("Location: ../signup.php");
    exit();
}

if (strlen($password) < 6) {
    $_SESSION['error'] = "Password must be at least 6 characters.";
    header("Location: ../signup.php");
    exit();
}

// Determine role
$result    = $conn->query("SELECT COUNT(*) AS cnt FROM users");
$userCount = (int)($result->fetch_assoc()['cnt'] ?? 0);

$isOwnerSession = isset($_SESSION['role']) && $_SESSION['role'] === 'owner';

if ($userCount > 0 && !$isOwnerSession) {
    $_SESSION['error'] = "Registration is closed. Please log in.";
    header("Location: ../signup.php");
    exit();
}

$role = ($userCount === 0) ? 'owner' : 'manager';

// Duplicate email check
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($exists) {
    $_SESSION['error'] = "That email is already registered.";
    header("Location: ../signup.php");
    exit();
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Insert user
$stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);

if ($stmt->execute()) {
    $stmt->close();

    if ($isOwnerSession) {
        $_SESSION['success'] = "Manager account created for {$name}.";
        header("Location: ../profile.php");
    } else {
        $_SESSION['success'] = "Account created! Please log in.";
        header("Location: ../login.php");
    }
    exit();
}

$error = $stmt->error;
$stmt->close();
$_SESSION['error'] = "Failed to create account: " . $error;
header("Location: ../signup.php");
exit();
