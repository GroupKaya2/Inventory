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

            <form action="register_process.php" method="POST">
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Register</button>
            </form>

            <p>Already have an account? <a href="index.php">Login</a></p>
        </div>
    </div>

    <div class="right"></div>

</div>

</body>
</html>
