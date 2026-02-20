<?php
/**
 * Create sales and sale_items tables
 * Run this file once via browser or command line
 */
include __DIR__ . '/db.php';

$sql = "
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;

CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_date DATE NOT NULL,
    customer_name VARCHAR(255) NOT NULL DEFAULT '',
    plate_number VARCHAR(50) NOT NULL DEFAULT '',
    parts_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    labor_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    line_type ENUM('parts','labor') NOT NULL,
    product_id INT NULL,
    description VARCHAR(255) NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL
);

SET FOREIGN_KEY_CHECKS = 1;
";

// Split SQL into individual statements
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^--/', $stmt);
    }
);

$errors = [];
$success = [];

foreach ($statements as $stmt) {
    if ($conn->multi_query($stmt)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        $success[] = "Executed: " . substr($stmt, 0, 50) . "...";
    } else {
        $errors[] = "Error: " . $conn->error . " - " . substr($stmt, 0, 50) . "...";
    }
}

$conn->close();

// Output results
if (php_sapi_name() === 'cli') {
    echo "Sales Tables Migration\n";
    echo "======================\n\n";
    foreach ($success as $msg) echo $msg . "\n";
    foreach ($errors as $msg) echo $msg . "\n";
    echo "\nMigration completed.\n";
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><head><title>Sales Migration</title></head><body>';
    echo '<h2>Sales Tables Migration</h2>';
    if (!empty($success)) {
        echo '<h3 style="color:green;">Success:</h3><ul>';
        foreach ($success as $msg) echo '<li>' . htmlspecialchars($msg) . '</li>';
        echo '</ul>';
    }
    if (!empty($errors)) {
        echo '<h3 style="color:red;">Errors:</h3><ul>';
        foreach ($errors as $msg) echo '<li>' . htmlspecialchars($msg) . '</li>';
        echo '</ul>';
    }
    if (empty($errors)) {
        echo '<p style="color:green;"><strong>Migration completed successfully!</strong></p>';
        echo '<p><a href="sales.php">Go to Sales Page</a></p>';
    }
    echo '</body></html>';
}
?>
