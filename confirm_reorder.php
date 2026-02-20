<?php
/**
 * Log reorder confirmation (optional). Creates reorder_preparations record.
 * Table may not exist if migration_forecast.sql was not run.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

include __DIR__ . '/db.php';

try {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $recommended_qty = (int)($_POST['recommended_qty'] ?? 0);
    $confirmed_qty = (int)($_POST['confirmed_qty'] ?? 0);

    if ($product_id <= 0) {
        throw new Exception('Invalid product.');
    }

    $qty = $confirmed_qty > 0 ? $confirmed_qty : $recommended_qty;
    if ($qty <= 0) {
        throw new Exception('Quantity must be positive.');
    }

    $hasTable = @$conn->query("SHOW TABLES LIKE 'reorder_preparations'")->num_rows > 0;
    if ($hasTable) {
        $stmt = $conn->prepare("INSERT INTO reorder_preparations (product_id, recommended_qty, confirmed_qty, status) VALUES (?, ?, ?, 'confirmed')");
        if ($stmt) {
            $stmt->bind_param('iii', $product_id, $recommended_qty, $qty);
            $stmt->execute();
            $stmt->close();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Reorder confirmed and logged.',
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
$conn->close();
