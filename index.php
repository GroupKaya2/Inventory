<?php session_start(); ?>
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

                <select name="role" required>
                    <option value="">Login as...</option>
                    <option value="owner">Owner</option>
                    <option value="manager">Manager</option>
                </select>

                <button type="submit">Login</button>
            </form>

            <p>Don't have an account? <a href="register.php">Sign up</a></p>

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
