<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include "db.php";

$role = $_SESSION['role'];
$activePage = 'dashboard';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { border: none; border-radius: 14px; box-shadow: 0 10px 30px rgba(2,6,23,.08); }
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(2,6,23,.10);
        }
    </style>
</head>
<body>
<?php include "sidebar.php"; ?>

<main class="app-main p-3 p-md-4">
    <div class="container-fluid">
        <div class="hero p-4 mb-3 mb-md-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h3 class="mb-1">Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></h3>
                    <div class="opacity-75">Role: <?php echo htmlspecialchars(strtoupper($role)); ?></div>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-light" href="inventory.php">Go to Inventory</a>
                    <a class="btn btn-outline-light" href="profile.php">Profile</a>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
