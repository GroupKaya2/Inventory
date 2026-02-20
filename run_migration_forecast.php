<?php
/**
 * Run forecasting migration (reorder_preparations table).
 * Run once. Ensure migration_dashboard has been run first for reorder_threshold.
 */
include __DIR__ . '/db.php';

$done = [];
$errors = [];

$sql = "CREATE TABLE IF NOT EXISTS reorder_preparations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    recommended_qty INT NOT NULL,
    confirmed_qty INT NULL,
    status ENUM('pending','confirmed','received') DEFAULT 'pending',
    confirmed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
)";
if ($conn->query($sql)) {
    $done[] = 'reorder_preparations table created or already exists';
} else {
    $errors[] = 'reorder_preparations: ' . $conn->error;
}

header('Content-Type: application/json');
echo json_encode([
    'success' => count($errors) === 0,
    'done' => $done,
    'errors' => $errors,
], JSON_PRETTY_PRINT);
