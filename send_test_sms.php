<?php
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
require_once __DIR__ . '/sms_service.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $recipients = $input['recipients'] ?? [];
    
    if (empty($recipients)) {
        throw new Exception('No recipients specified');
    }

    $testMessage = "TEST SMS\n\nThis is a test message from the Inventory Management System.\n\nSent at: " . date('Y-m-d H:i:s');
    
    $smsService = new SMSService($conn);
    $result = $smsService->sendSMS($testMessage, $recipients);
    
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
