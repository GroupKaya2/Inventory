<?php session_start();
include "db.php";

// Only owner can create manager accounts
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'owner') {
    header("Location: " . (isset($_SESSION['user']) ? "dashboard.php" : "index.php"));
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Manager</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <div class="left">
        <div class="form-box">

            <h2>Create Manager Account</h2>
            <form action="registerprocess.php" method="POST">
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>

                <button type="submit">Create Manager</button>
            </form>

            <p><a href="dashboard.php">Back to Dashboard</a></p>

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
