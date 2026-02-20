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

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $recipients = $input['recipients'] ?? [];

    // Validate recipients
    if (!is_array($recipients)) {
        throw new Exception('Recipients must be an array');
    }

    // Validate each phone number
    foreach ($recipients as $phone) {
        if (!preg_match('/^\+?[1-9]\d{9,14}$/', $phone)) {
            throw new Exception("Invalid phone number format: $phone");
        }
    }

    // Ensure table exists
    $hasTable = @$conn->query("SHOW TABLES LIKE 'sms_settings'")->num_rows > 0;
    if (!$hasTable) {
        // Create table if it doesn't exist
        $conn->query("CREATE TABLE sms_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT NULL,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    $recipientsJson = json_encode($recipients);
    $userId = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO sms_settings (setting_key, setting_value, updated_by) 
                            VALUES ('recipients', ?, ?) 
                            ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP");
    if ($stmt) {
        $stmt->bind_param('sisi', $recipientsJson, $userId, $recipientsJson, $userId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
        } else {
            throw new Exception('Failed to save settings');
        }
        $stmt->close();
    } else {
        throw new Exception('Database error: ' . $conn->error);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
