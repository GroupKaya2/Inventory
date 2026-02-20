<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include __DIR__ . '/db.php';
require_once __DIR__ . '/sms_service.php';

try {
    $status = $_GET['status'] ?? null;
    $limit = (int)($_GET['limit'] ?? 50);
    
    $smsService = new SMSService($conn);
    $history = $smsService->getSMSHistory($limit, $status);
    
    echo json_encode(['success' => true, 'history' => $history]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
