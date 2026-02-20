<?php
/**
 * SMS Notification Service
 * Configure your SMS provider settings below
 * 
 * Supported providers:
 * - twilio: Twilio API
 * - semaphore: Semaphore SMS (Philippines)
 * - custom: Custom HTTP API
 */

class SMSService {
    private $provider;
    private $apiKey;
    private $apiSecret;
    private $fromNumber;
    private $apiUrl;
    private $enabled;
    private $defaultRecipient;
    private $conn;

    public function __construct($dbConnection = null) {
        // ============================================
        // CONFIGURE YOUR SMS SETTINGS HERE
        // ============================================
        
        // Set to true to enable SMS notifications, false to disable
        $this->enabled = true;
        
        // Provider: 'twilio', 'semaphore', or 'custom'
        $this->provider = 'semaphore';
        
        // For Semaphore (Philippines): https://semaphore.co/
        $this->apiKey = 'YOUR_SEMAPHORE_API_KEY';
        $this->apiUrl = 'https://api.semaphore.co/api/v4/messages';
        
        // For Twilio: https://www.twilio.com/
        // $this->provider = 'twilio';
        // $this->apiKey = 'YOUR_TWILIO_ACCOUNT_SID';
        // $this->apiSecret = 'YOUR_TWILIO_AUTH_TOKEN';
        // $this->fromNumber = 'YOUR_TWILIO_PHONE_NUMBER';
        // $this->apiUrl = 'https://api.twilio.com/2010-04-01/Accounts/' . $this->apiKey . '/Messages.json';
        
        // For Custom HTTP API
        // $this->provider = 'custom';
        // $this->apiUrl = 'https://your-sms-api.com/send';
        // $this->apiKey = 'YOUR_API_KEY';
        
        // Default recipient phone number (admin/manager)
        // Format: +639123456789 (Philippines) or +1234567890 (US)
        $this->defaultRecipient = '+639123456789'; // CHANGE THIS TO YOUR PHONE NUMBER
        
        // Store database connection for history logging
        $this->conn = $dbConnection;
    }
    
    /**
     * Get list of SMS recipients from database settings
     * @return array Array of phone numbers
     */
    private function getRecipients() {
        if (!$this->conn) {
            return [$this->defaultRecipient];
        }
        
        try {
            $hasTable = @$this->conn->query("SHOW TABLES LIKE 'sms_settings'")->num_rows > 0;
            if (!$hasTable) {
                return [$this->defaultRecipient];
            }
            
            $stmt = $this->conn->prepare("SELECT setting_value FROM sms_settings WHERE setting_key = 'recipients'");
            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $recipients = json_decode($row['setting_value'], true);
                    if (is_array($recipients) && !empty($recipients)) {
                        return $recipients;
                    }
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log('SMS Service: Error fetching recipients - ' . $e->getMessage());
        }
        
        return [$this->defaultRecipient];
    }
    
    /**
     * Log SMS to history table
     * @param string $recipient Phone number
     * @param string $message SMS message
     * @param string $status success|failed|pending
     * @param string|null $errorMessage Error message if failed
     * @param array|null $productIds Product IDs that triggered alert
     * @return bool Success status
     */
    private function logSMS($recipient, $message, $status, $errorMessage = null, $productIds = null) {
        if (!$this->conn) {
            return false;
        }
        
        try {
            $hasTable = @$this->conn->query("SHOW TABLES LIKE 'sms_history'")->num_rows > 0;
            if (!$hasTable) {
                return false;
            }
            
            $productIdsJson = $productIds ? json_encode($productIds) : null;
            $stmt = $this->conn->prepare("INSERT INTO sms_history (recipient, message, status, provider, error_message, product_ids) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('ssssss', $recipient, $message, $status, $this->provider, $errorMessage, $productIdsJson);
                $stmt->execute();
                $stmt->close();
                return true;
            }
        } catch (Exception $e) {
            error_log('SMS Service: Error logging SMS - ' . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Send SMS notification to single or multiple recipients
     * @param string $message The message to send
     * @param string|array|null $toPhoneNumber Phone number(s) (optional, uses default/recipients list if not provided)
     * @param array|null $productIds Product IDs that triggered this alert (for logging)
     * @return array ['success' => bool, 'message' => string, 'sent_count' => int, 'failed_count' => int]
     */
    public function sendSMS($message, $toPhoneNumber = null, $productIds = null) {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'SMS notifications are disabled', 'sent_count' => 0, 'failed_count' => 0];
        }

        // Determine recipients
        $recipients = [];
        if ($toPhoneNumber === null) {
            // Use recipients from database settings
            $recipients = $this->getRecipients();
        } elseif (is_array($toPhoneNumber)) {
            // Multiple recipients provided
            $recipients = $toPhoneNumber;
        } else {
            // Single recipient provided
            $recipients = [$toPhoneNumber];
        }

        if (empty($recipients)) {
            return ['success' => false, 'message' => 'No recipients specified', 'sent_count' => 0, 'failed_count' => 0];
        }

        $sentCount = 0;
        $failedCount = 0;
        $errors = [];

        // Send to each recipient
        foreach ($recipients as $phone) {
            // Validate and format phone number
            if (empty($phone) || strlen($phone) < 10) {
                $failedCount++;
                $errors[] = "Invalid phone number: $phone";
                $this->logSMS($phone, $message, 'failed', "Invalid phone number format", $productIds);
                continue;
            }

            // Ensure phone number starts with +
            if (substr($phone, 0, 1) !== '+') {
                $phone = '+' . $phone;
            }

            // Send via appropriate provider
            $result = null;
            switch ($this->provider) {
                case 'semaphore':
                    $result = $this->sendViaSemaphore($message, $phone);
                    break;
                case 'twilio':
                    $result = $this->sendViaTwilio($message, $phone);
                    break;
                case 'custom':
                    $result = $this->sendViaCustom($message, $phone);
                    break;
                default:
                    $result = ['success' => false, 'message' => 'SMS provider not configured'];
            }

            // Log result
            $status = $result['success'] ? 'success' : 'failed';
            $errorMsg = $result['success'] ? null : $result['message'];
            $this->logSMS($phone, $message, $status, $errorMsg, $productIds);

            if ($result['success']) {
                $sentCount++;
            } else {
                $failedCount++;
                $errors[] = "$phone: " . $result['message'];
            }
        }

        // Return summary
        if ($sentCount > 0 && $failedCount == 0) {
            return ['success' => true, 'message' => "SMS sent successfully to $sentCount recipient(s)", 'sent_count' => $sentCount, 'failed_count' => $failedCount];
        } elseif ($sentCount > 0) {
            return ['success' => true, 'message' => "SMS sent to $sentCount recipient(s), $failedCount failed", 'sent_count' => $sentCount, 'failed_count' => $failedCount, 'errors' => $errors];
        } else {
            return ['success' => false, 'message' => 'Failed to send SMS: ' . implode('; ', $errors), 'sent_count' => $sentCount, 'failed_count' => $failedCount, 'errors' => $errors];
        }
    }

    /**
     * Send via Semaphore SMS (Philippines)
     */
    private function sendViaSemaphore($message, $phone) {
        if ($this->apiKey === 'YOUR_SEMAPHORE_API_KEY') {
            error_log('SMS Service: Semaphore API key not configured');
            return ['success' => false, 'message' => 'SMS API not configured'];
        }

        $data = [
            'apikey' => $this->apiKey,
            'number' => $phone,
            'message' => $message
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return ['success' => true, 'message' => 'SMS sent successfully'];
        } else {
            error_log('SMS Service Error: ' . $response);
            return ['success' => false, 'message' => 'Failed to send SMS: ' . $response];
        }
    }

    /**
     * Send via Twilio
     */
    private function sendViaTwilio($message, $phone) {
        if ($this->apiKey === 'YOUR_TWILIO_ACCOUNT_SID' || empty($this->apiSecret)) {
            error_log('SMS Service: Twilio credentials not configured');
            return ['success' => false, 'message' => 'SMS API not configured'];
        }

        $data = [
            'From' => $this->fromNumber,
            'To' => $phone,
            'Body' => $message
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->apiKey . ':' . $this->apiSecret);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            return ['success' => true, 'message' => 'SMS sent successfully'];
        } else {
            error_log('SMS Service Error: ' . $response);
            return ['success' => false, 'message' => 'Failed to send SMS'];
        }
    }

    /**
     * Send via Custom HTTP API
     */
    private function sendViaCustom($message, $phone) {
        $data = [
            'phone' => $phone,
            'message' => $message,
            'api_key' => $this->apiKey
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return ['success' => true, 'message' => 'SMS sent successfully'];
        } else {
            error_log('SMS Service Error: ' . $response);
            return ['success' => false, 'message' => 'Failed to send SMS'];
        }
    }

    /**
     * Check for low stock items and send SMS alerts
     * @param mysqli $conn Database connection
     * @param array|null $affectedProductIds Array of product IDs to check (null = check all)
     * @return array List of products that triggered alerts
     */
    public function checkAndSendLowStockAlerts($conn, $affectedProductIds = null) {
        if (!$this->enabled) {
            return [];
        }

        $useThreshold = @$conn->query("SHOW COLUMNS FROM products LIKE 'reorder_threshold'")->num_rows > 0;
        $threshCol = $useThreshold ? 'COALESCE(p.reorder_threshold, 5)' : '5';

        $sql = "SELECT p.product_id, p.description, p.code, s.current_stock, $threshCol AS reorder_threshold
                FROM product_stock s
                INNER JOIN products p ON p.product_id = s.product_id
                WHERE s.current_stock <= $threshCol";
        
        if ($affectedProductIds !== null && !empty($affectedProductIds)) {
            $placeholders = str_repeat('?,', count($affectedProductIds) - 1) . '?';
            $sql .= " AND p.product_id IN ($placeholders)";
        }
        
        $sql .= " ORDER BY s.current_stock ASC LIMIT 10";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('SMS Service: Failed to prepare low stock query');
            return [];
        }

        if ($affectedProductIds !== null && !empty($affectedProductIds)) {
            $types = str_repeat('i', count($affectedProductIds));
            $stmt->bind_param($types, ...$affectedProductIds);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $lowStockItems = [];
        
        while ($row = $result->fetch_assoc()) {
            $lowStockItems[] = [
                'product_id' => (int)$row['product_id'],
                'description' => $row['description'],
                'code' => $row['code'],
                'current_stock' => (int)$row['current_stock'],
                'reorder_threshold' => (int)$row['reorder_threshold'],
            ];
        }
        $stmt->close();

        if (empty($lowStockItems)) {
            return [];
        }

        // Build SMS message
        $message = "LOW STOCK ALERT\n\n";
        foreach ($lowStockItems as $item) {
            $status = $item['current_stock'] <= 2 ? 'CRITICAL' : 'Low';
            $message .= $status . ": " . $item['description'];
            if ($item['code']) {
                $message .= " (" . $item['code'] . ")";
            }
            $message .= "\nStock: " . $item['current_stock'] . " | Threshold: " . $item['reorder_threshold'] . "\n\n";
        }
        $message .= "Please reorder soon.";

        // Get product IDs for logging
        $productIds = array_column($lowStockItems, 'product_id');
        
        // Send SMS to all recipients
        $smsResult = $this->sendSMS($message, null, $productIds);
        
        if ($smsResult['success']) {
            error_log('SMS Alert sent for ' . count($lowStockItems) . ' low stock items to ' . $smsResult['sent_count'] . ' recipient(s)');
        } else {
            error_log('SMS Alert failed: ' . $smsResult['message']);
        }

        return $lowStockItems;
    }
    
    /**
     * Get SMS history from database
     * @param int $limit Number of records to return
     * @param string|null $status Filter by status (success/failed/pending)
     * @return array Array of SMS history records
     */
    public function getSMSHistory($limit = 50, $status = null) {
        if (!$this->conn) {
            return [];
        }
        
        try {
            $hasTable = @$this->conn->query("SHOW TABLES LIKE 'sms_history'")->num_rows > 0;
            if (!$hasTable) {
                return [];
            }
            
            $sql = "SELECT * FROM sms_history";
            if ($status) {
                $sql .= " WHERE status = ?";
            }
            $sql .= " ORDER BY sent_at DESC LIMIT ?";
            
            $stmt = $this->conn->prepare($sql);
            if ($stmt) {
                if ($status) {
                    $stmt->bind_param('si', $status, $limit);
                } else {
                    $stmt->bind_param('i', $limit);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                $history = [];
                while ($row = $result->fetch_assoc()) {
                    $row['product_ids'] = $row['product_ids'] ? json_decode($row['product_ids'], true) : null;
                    $history[] = $row;
                }
                $stmt->close();
                return $history;
            }
        } catch (Exception $e) {
            error_log('SMS Service: Error fetching history - ' . $e->getMessage());
        }
        
        return [];
    }
    
    /**
     * Get SMS statistics
     * @return array Statistics about SMS usage
     */
    public function getSMSStatistics() {
        if (!$this->conn) {
            return ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0];
        }
        
        try {
            $hasTable = @$this->conn->query("SHOW TABLES LIKE 'sms_history'")->num_rows > 0;
            if (!$hasTable) {
                return ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0];
            }
            
            $stmt = $this->conn->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                FROM sms_history");
            
            if ($stmt && $row = $stmt->fetch_assoc()) {
                return [
                    'total' => (int)$row['total'],
                    'success' => (int)$row['success'],
                    'failed' => (int)$row['failed'],
                    'pending' => (int)$row['pending']
                ];
            }
        } catch (Exception $e) {
            error_log('SMS Service: Error fetching statistics - ' . $e->getMessage());
        }
        
        return ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0];
    }
    }
}
