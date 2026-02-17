<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <div class="left">
        <div class="form-box">

            <h2>Create Account</h2>

            <!-- Correct form action -->
            <form action="registerprocess.php" method="POST">
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>

                <select name="role" required>
                    <option value="">Register as...</option>
                    <option value="owner">Owner</option>
                    <option value="manager">Manager</option>
                </select>

                <button type="submit">Register</button>
            </form>

            <p>Already have an account? <a href="index.php">Login</a></p>

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
