<?php
// signup.php — Register / Create Account Page

session_start();
require_once 'backend/db.php';

// How many users already exist?
$result    = $conn->query("SELECT COUNT(*) AS cnt FROM users");
$userCount = (int)($result->fetch_assoc()['cnt'] ?? 0);

$isOwner = isset($_SESSION['role']) && $_SESSION['role'] === 'owner';

// Block registration if users exist and current visitor is not the owner
if ($userCount > 0 && !$isOwner) {
    $_SESSION['error'] = "Registration is closed. Please log in.";
    header("Location: login.php");
    exit();
}

$error   = $_SESSION['error']   ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up — Dispeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        /* Signup card is slightly taller */
        .login-card { height: auto; min-height: 400px; }
        .card-left  { padding: 32px 36px; }
        .card-right .welcome-title { font-size: 1.5rem; }
    </style>
</head>
<body>

    <div class="page-wrap">
        <div class="login-card">

            <!-- LEFT: Form -->
            <div class="card-left">
                <h1 class="form-title"><?= $isOwner ? 'Add Manager' : 'Create Account' ?></h1>

                <?php if ($error): ?>
                    <div class="error-msg">
                        <i class="bi bi-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="error-msg" style="background:rgba(16,185,129,.25);border-color:rgba(16,185,129,.4);">
                        <i class="bi bi-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form action="backend/register.php" method="POST">

                    <div class="field-group">
                        <div class="input-wrap">
                            <input type="text" name="name" placeholder="Full Name" required autocomplete="name">
                            <i class="bi bi-person field-icon"></i>
                        </div>
                    </div>

                    <div class="field-group">
                        <div class="input-wrap">
                            <input type="email" name="email" placeholder="Email" required autocomplete="email">
                            <i class="bi bi-envelope field-icon"></i>
                        </div>
                    </div>

                    <div class="field-group">
                        <div class="input-wrap">
                            <input type="password" name="password" placeholder="Password" required minlength="6">
                            <i class="bi bi-lock field-icon"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        <?= $isOwner ? 'Create Manager' : 'Create Account' ?>
                    </button>
                </form>

                <p class="signup-link">
                    <?php if ($isOwner): ?>
                        <a href="dashboard.php"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
                    <?php else: ?>
                        Already have an account? <a href="login.php">Login</a>
                    <?php endif; ?>
                </p>
            </div>

            <!-- RIGHT: Welcome -->
            <div class="card-right">
                <div class="welcome-content">
                    <div class="welcome-logo">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <h2 class="welcome-title">
                        <?= $isOwner ? 'ADD<br>MANAGER' : 'JOIN<br>US' ?>
                    </h2>
                    <p class="welcome-sub">Dispeedway System</p>
                </div>
                <div class="slash-deco"></div>
            </div>

        </div>
    </div>

</body>
</html>
