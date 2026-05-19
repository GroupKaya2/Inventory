<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$isOwner = ($_SESSION['role'] ?? 'manager') === 'owner';
$action = $_GET['action'] ?? '';

if ($action === 'fetch') {
    $limit = (int) ($_GET['limit'] ?? 50);
    $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
    
    $entries = getAuditLog($conn, $limit, $userId);
    
    echo json_encode(['success' => true, 'data' => $entries]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
$conn->close();
