<?php
session_start();
include "db.php";

// Owner-only system: show sign-up link ONLY if no users exist yet
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM users");
$stmt->execute();
$countRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$canRegister = (intval($countRow['cnt']) === 0);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <div class="left">
        <div class="form-box">

            <div class="logo">
                <img src="logo.jpg" alt="Logo">
            </div>

            <h2>Smart Inventory & Parts Planning System</h2>

            <form action="login_process.php" method="POST">

                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>

                <button type="submit">Login</button>
            </form>
            <?php if ($canRegister) { ?>
                <p>Don't have an account? <a href="signup.php">Sign up</a></p>
            <?php } else { ?>
                <p class="text-muted" style="font-size: 13px;">Registration is disabled. Please login.</p>
            <?php } ?>

            <?php
            if (isset($_SESSION['error'])) {
                echo "<p class='error'>" . $_SESSION['error'] . "</p>";
                unset($_SESSION['error']);
            }
            ?>

        </div>
    </div>

    <div class="right"></div>

</div>

</body>
</html>
