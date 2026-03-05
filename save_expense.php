<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
include 'db.php';

$date   = trim($_POST['expense_date'] ?? '');
$cat    = trim($_POST['category'] ?? '');
$desc   = trim($_POST['description'] ?? '');
$amount = (float)($_POST['amount'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if (!$date || !$cat || !$desc || $amount <= 0) {
    echo json_encode(['success'=>false,'message'=>'All fields are required and amount must be greater than 0.']);
    exit;
}

// Auto-create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $conn->prepare("INSERT INTO expenses (expense_date, category, description, amount, created_by) VALUES (?,?,?,?,?)");
if (!$stmt) { echo json_encode(['success'=>false,'message'=>'DB error: '.$conn->error]); exit; }
$stmt->bind_param('sssdi', $date, $cat, $desc, $amount, $userId);

if ($stmt->execute()) {
    echo json_encode(['success'=>true,'message'=>'Saved successfully']);
} else {
    echo json_encode(['success'=>false,'message'=>'Failed: '.$conn->error]);
}
$stmt->close();
$conn->close();