<?php
/**
 * Run once to add dashboard support: reorder_threshold on products, work_orders table.
 * Optionally seeds sample work orders so labor KPIs and Top Labor show data.
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

include __DIR__ . '/db.php';

$done = [];
$errors = [];

// 1) Add reorder_threshold to products if missing
$check = $conn->query("SHOW COLUMNS FROM products LIKE 'reorder_threshold'");
if ($check && $check->num_rows === 0) {
    if ($conn->query("ALTER TABLE products ADD COLUMN reorder_threshold INT DEFAULT 5")) {
        $done[] = 'Added column products.reorder_threshold';
    } else {
        $errors[] = 'ALTER products: ' . $conn->error;
    }
} else {
    $done[] = 'products.reorder_threshold already exists';
}

// 2) Create work_orders table if not exists
$sql = "CREATE TABLE IF NOT EXISTS work_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(255) NOT NULL,
    status ENUM('open','completed') NOT NULL DEFAULT 'open',
    labor_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql)) {
    $done[] = 'work_orders table ready';
} else {
    $errors[] = 'CREATE work_orders: ' . $conn->error;
}

// 3) Seed sample work orders only if table is empty
$count = $conn->query("SELECT COUNT(*) AS c FROM work_orders")->fetch_assoc()['c'];
if ($count == 0) {
    $samples = [
        ['Complete Alignment 4W', 'completed', 18000, date('Y-m-d H:i:s', strtotime('-2 days'))],
        ['Oil Change (Valvoline)', 'completed', 14400, date('Y-m-d H:i:s', strtotime('-1 day'))],
        ['Tire Rotation SUV', 'completed', 10800, date('Y-m-d H:i:s', strtotime('-1 day'))],
        ['ATF Machine Change', 'completed', 8100, date('Y-m-d H:i:s', strtotime('today'))],
        ['Underchassis Service', 'completed', 5400, date('Y-m-d H:i:s', strtotime('today'))],
        ['Brake Pad Replacement', 'open', 0, null],
        ['Battery Check', 'open', 0, null],
    ];
    $stmt = $conn->prepare("INSERT INTO work_orders (service_name, status, labor_amount, completed_at) VALUES (?, ?, ?, ?)");
    foreach ($samples as $s) {
        $stmt->bind_param('ssds', $s[0], $s[1], $s[2], $s[3]);
        $stmt->execute();
    }
    $stmt->close();
    $done[] = 'Inserted sample work orders';
}

$conn->close();

header('Content-Type: application/json');
echo json_encode([
    'success' => count($errors) === 0,
    'done' => $done,
    'errors' => $errors,
]);
exit;
