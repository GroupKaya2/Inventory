<?php

session_start();


if (isset($_SESSION['user_id'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p["path"], $p["domain"], $p["secure"], $p["httponly"]
        );
    }
    session_destroy();
    // Restart a clean session so error
    session_start();
}

// Show any error messages from the backend
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Dispeedway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

    <div class="page-wrap">

        <!-- Glowing login card -->
        <div class="login-card">

            <!-- LEFT: Form side -->
            <div class="card-left">
                <h1 class="form-title">Login</h1>

                <?php if ($error): ?>
                    <div class="error-msg">
                        <i class="bi bi-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form action="backend/auth.php" method="POST">
                    <input type="hidden" name="action" value="login">

                    <div class="field-group">
                        <label>Email</label>
                        <div class="input-wrap">
                            <input type="email" name="email" placeholder="Email" required autocomplete="email">
                            <i class="bi bi-envelope field-icon"></i>
                        </div>
                    </div>

                    <div class="field-group">
                        <label>Password</label>
                        <div class="input-wrap">
                            <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
                            <i class="bi bi-lock field-icon"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">Login</button>
                </form>

                <p class="signup-link">
                    Don't have an account? <a href="signup.php">Sign Up</a>
                </p>
            </div>

            <!-- RIGHT: Welcome side -->
            <div class="card-right">
                <div class="welcome-content">
                    <div class="welcome-logo">
                        <img src="assets/img/logo.jpg" alt="Logo" class="brand-logo">
                    </div>
                    <h2 class="welcome-title">WELCOME<br>BACK</h2>
                    <p class="welcome-sub">D Speedway Car Care Services</p>
                </div>
                <!-- Diagonal slash decoration -->
                <div class="slash-deco"></div>
            </div>

        </div>

    </div>

    <script src="assets/js/login.js"></script>
</body>
</html>