<?php
session_start();
include "db.php";

// Allow registration if:
// 1. No users exist yet (first registration creates owner)
// 2. OR current user is logged in as owner
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM users");
$stmt->execute();
$cnt = $stmt->get_result()->fetch_assoc();
$stmt->close();

$userCount = intval($cnt['cnt']);
$isOwner = isset($_SESSION['role']) && $_SESSION['role'] === 'owner';

// If users exist and user is not owner, redirect to login
if ($userCount > 0 && !$isOwner) {
    $_SESSION['error'] = "Registration is disabled. Please login.";
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sign Up</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>

<div class="container">

    <div class="left">
        <div class="form-box">

            <div class="logo">
                <img src="logo.jpg" alt="Logo">
            </div>

            <h2><?php echo $isOwner ? 'Add New Account' : 'Create Account'; ?></h2>
            
            <?php if ($isOwner): ?>
                <p class="text-muted" style="font-size: 14px; margin-bottom: 15px;">
                    <i class="bi bi-info-circle"></i> You are creating a new account as the owner.
                </p>
            <?php endif; ?>

            <form action="signup_process.php" method="POST">
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>

                <button type="submit"><?php echo $isOwner ? 'Create Account' : 'Sign Up'; ?></button>
            </form>

            <?php if ($isOwner): ?>
                <p><a href="dashboard.php">Back to Dashboard</a></p>
            <?php else: ?>
                <p>Already have an account? <a href="index.php">Login</a></p>
            <?php endif; ?>

            <?php
            if (isset($_SESSION['error'])) {
                echo "<p class='error'>" . $_SESSION['error'] . "</p>";
                unset($_SESSION['error']);
            }

            if (isset($_SESSION['success'])) {
                echo "<p class='success'>" . $_SESSION['success'] . "</p>";
                unset($_SESSION['success']);
            }
            ?>

        </div>
    </div>

    <div class="right"></div>

</div>

</body>
</html>

