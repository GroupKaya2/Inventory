
<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'manager') !== 'owner') {
    header("Location: index.php"); exit();
}

$done = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'DELETE') {
    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM sale_items");
        $conn->query("DELETE FROM inventory_transactions WHERE transaction_type = 'sale'");
        $conn->query("DELETE FROM sales");
        // Reset auto increment
        $conn->query("ALTER TABLE sales AUTO_INCREMENT = 1");
        $conn->query("ALTER TABLE sale_items AUTO_INCREMENT = 1");
        $conn->commit();
        $done = true;
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Count current records
$salesCount = $conn->query("SELECT COUNT(*) AS c FROM sales")->fetch_assoc()['c'] ?? 0;
$itemsCount = $conn->query("SELECT COUNT(*) AS c FROM sale_items")->fetch_assoc()['c'] ?? 0;
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clear Sales History</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    background: #0f172a;
    color: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
}
.box {
    background: #1e293b;
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 16px;
    padding: 36px;
    max-width: 460px;
    width: 100%;
}
</style>
</head>
<body>
<div class="box">
    <h4 class="fw-bold mb-1"><i class="bi bi-trash3-fill text-danger me-2"></i>Clear Sales History</h4>
    <p class="text-muted mb-4" style="font-size:.85rem;">This will permanently delete all sales records from the database.</p>

    <?php if ($done): ?>
        <div class="alert alert-success">✅ All sales history deleted successfully!</div>
        <a href="sales_history.php" class="btn btn-primary w-100">Go to Sales History</a>

    <?php elseif ($error): ?>
        <div class="alert alert-danger">❌ Error: <?= htmlspecialchars($error) ?></div>
        <a href="sales_history.php" class="btn btn-secondary w-100">Go Back</a>

    <?php else: ?>
        <div class="alert alert-warning">
            <strong>⚠️ Warning — This cannot be undone!</strong><br>
            <span style="font-size:.85rem;">
                Found: <strong><?= $salesCount ?> sales</strong> and <strong><?= $itemsCount ?> sale items</strong>
            </span>
        </div>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label text-muted" style="font-size:.82rem;">Type <strong>DELETE</strong> to confirm:</label>
                <input type="text" name="confirm" class="form-control" placeholder="Type DELETE here"
                    style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.15);color:#fff;">
            </div>
            <button type="submit" class="btn btn-danger w-100 fw-bold">Delete All Sales History</button>
            <a href="sales_history.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
        </form>
    <?php endif; ?>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</body>
</html>