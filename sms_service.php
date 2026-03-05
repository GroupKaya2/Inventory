<?php
class SMSService {
    private $conn;
    private $enabled   = false; // Set to true + configure API key to enable
    private $provider  = 'semaphore'; // 'semaphore' or 'twilio'
    private $apiKey    = 'YOUR_SEMAPHORE_API_KEY';
    private $apiSecret = 'YOUR_TWILIO_AUTH_TOKEN';
    private $fromNumber = 'YOUR_TWILIO_NUMBER';
    private $defaultRecipient = '+639123456789';

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function checkAndSendLowStockAlerts($conn, array $productIds = []) {
        if (!$this->enabled) return;

        $lowStock = [];
        if (!empty($productIds)) {
            $ids = implode(',', array_map('intval', $productIds));
            $r = $conn->query("SELECT product_id, description, current_stock, reorder_threshold FROM product_stock WHERE product_id IN ($ids) AND current_stock <= reorder_threshold");
            if ($r) while ($row = $r->fetch_assoc()) $lowStock[] = $row;
        }

        if (empty($lowStock)) return;

        $recipients = $this->getRecipients();
        if (empty($recipients)) $recipients = [$this->defaultRecipient];

        $lines = array_map(fn($p) => "{$p['description']}: {$p['current_stock']} left (min {$p['reorder_threshold']})", $lowStock);
        $message = "⚠️ LOW STOCK ALERT – Dispeedway\n" . implode("\n", $lines);
        $productIdList = array_column($lowStock, 'product_id');

        foreach ($recipients as $phone) {
            $this->send($phone, $message, $productIdList);
        }
    }

    private function getRecipients(): array {
        $chk = @$this->conn->query("SHOW TABLES LIKE 'sms_settings'");
        if (!$chk || $chk->num_rows === 0) return [];
        $row = $this->conn->query("SELECT setting_value FROM sms_settings WHERE setting_key='recipients' LIMIT 1")->fetch_assoc();
        if (!$row) return [];
        $decoded = json_decode($row['setting_value'], true);
        return is_array($decoded) ? $decoded : [];
    }

    public function send(string $phone, string $message, array $productIds = []): bool {
        $success = false;
        $errMsg  = null;

        try {
            if ($this->provider === 'semaphore') {
                $resp = file_get_contents('https://api.semaphore.co/api/v4/messages', false, stream_context_create([
                    'http' => [
                        'method'  => 'POST',
                        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                        'content' => http_build_query(['apikey'=>$this->apiKey,'number'=>$phone,'message'=>$message,'sendername'=>'Dispeedway'])
                    ]
                ]));
                $success = $resp !== false;
            } elseif ($this->provider === 'twilio') {
                $url  = "https://api.twilio.com/2010-04-01/Accounts/{$this->apiKey}/Messages.json";
                $opts = ['http'=>['method'=>'POST','header'=>"Authorization: Basic ".base64_encode($this->apiKey.':'.$this->apiSecret)."\r\nContent-Type: application/x-www-form-urlencoded\r\n",'content'=>http_build_query(['To'=>$phone,'From'=>$this->fromNumber,'Body'=>$message])]];
                $resp = file_get_contents($url, false, stream_context_create($opts));
                $success = $resp !== false;
            }
        } catch (\Exception $e) {
            $errMsg = $e->getMessage();
        }

        // Log to sms_history if table exists
        $chk = @$this->conn->query("SHOW TABLES LIKE 'sms_history'");
        if ($chk && $chk->num_rows > 0) {
            $status  = $success ? 'success' : 'failed';
            $pidsJson = json_encode($productIds);
            $stmt = $this->conn->prepare("INSERT INTO sms_history (recipient, message, status, provider, error_message, product_ids) VALUES (?,?,?,?,?,?)");
            if ($stmt) {
                $stmt->bind_param('ssssss', $phone, $message, $status, $this->provider, $errMsg, $pidsJson);
                $stmt->execute();
                $stmt->close();
            }
        }

        return $success;
    }

    public function testSend(string $phone): array {
        $message = "✅ Test SMS from Dispeedway Inventory System – " . date('Y-m-d H:i:s');
        $ok = $this->send($phone, $message);
        return ['success' => $ok, 'message' => $ok ? "SMS sent to $phone" : "Failed to send SMS. Check API credentials and enable SMS in sms_service.php."];
    }
}