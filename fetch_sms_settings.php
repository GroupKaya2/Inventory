<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include __DIR__ . '/db.php';

try {
    $hasTable = @$conn->query("SHOW TABLES LIKE 'sms_settings'")->num_rows > 0;
    if (!$hasTable) {
        echo json_encode(['success' => true, 'recipients' => []]);
        exit;
    }

    $stmt = $conn->prepare("SELECT setting_value FROM sms_settings WHERE setting_key = 'recipients'");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $recipients = json_decode($row['setting_value'], true);
            echo json_encode(['success' => true, 'recipients' => is_array($recipients) ? $recipients : []]);
        } else {
            echo json_encode(['success' => true, 'recipients' => []]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => true, 'recipients' => []]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
