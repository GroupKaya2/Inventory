<?php
/**
 * Record restock (positive inventory transaction).
 * Used when manager prepares/confirms reorder and receives stock.
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
    $quantity = (int)($_POST['quantity'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? 'Restock');

    if ($product_id <= 0 || $quantity <= 0) {
        throw new Exception('Invalid product or quantity. Quantity must be positive.');
    }

    $stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_date, quantity_change, transaction_type, remarks, created_by) VALUES (?, CURDATE(), ?, 'restock', ?, ?)");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    $uid = (int)$_SESSION['user_id'];
    $stmt->bind_param('iisi', $product_id, $quantity, $remarks, $uid);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to record restock.');
    }
    $stmt->close();

    // Check for low stock alerts after restock (in case other items are still low)
    require_once __DIR__ . '/sms_service.php';
    $smsService = new SMSService($conn);
    $smsService->checkAndSendLowStockAlerts($conn, [$product_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Restock recorded successfully. Stock updated.',
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
$conn->close();
