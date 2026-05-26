<?php
// Load environment variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    define('DB_HOST', $env['DB_HOST'] ?? 'localhost');
    define('DB_USER', $env['DB_USER'] ?? 'root');
    define('DB_PASS', $env['DB_PASS'] ?? '');
    define('DB_NAME', $env['DB_NAME'] ?? 'login_system');
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'login_system');
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    $is_api = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
        || (strpos($_SERVER['REQUEST_URI'] ?? '', 'backend/') !== false);
    if ($is_api) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    } else {
        http_response_code(500);
        echo "Database connection failed. Please check server settings.";
    }
    exit;
}
$conn->set_charset("utf8mb4");


// inventory_transactions
$conn->query("CREATE TABLE IF NOT EXISTS inventory_transactions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    product_id       INT NOT NULL,
    transaction_date DATE NOT NULL,
    quantity_change  INT NOT NULL DEFAULT 0,
    transaction_type ENUM('initial','restock','sale','adjustment') NOT NULL DEFAULT 'restock',
    remarks          VARCHAR(255) NULL,
    created_by       INT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product (product_id),
    INDEX idx_date    (transaction_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");


function inventory_txn_pk_column(mysqli $conn): string
{
    static $col = null;
    if ($col !== null) {
        return $col;
    }
    $r = $conn->query("SHOW COLUMNS FROM inventory_transactions LIKE 'transaction_id'");
    $col = ($r && $r->num_rows > 0) ? 'transaction_id' : 'id';
    return $col;
}

// expenses
$conn->query("CREATE TABLE IF NOT EXISTS expenses (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category     VARCHAR(100) NOT NULL DEFAULT 'Other',
    description  VARCHAR(255) NOT NULL,
    amount       DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by   INT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// payment_method column on sales
$conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS payment_method
    ENUM('cash','gcash') NOT NULL DEFAULT 'cash'");

$conn->query("SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");
$conn->query("CREATE OR REPLACE VIEW product_stock AS
    SELECT
        p.product_id,
        p.description,
        p.reorder_threshold,
        c.category_name,
        COALESCE(SUM(t.quantity_change), 0) AS current_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN inventory_transactions t ON t.product_id = p.product_id
    GROUP BY p.product_id, p.description, p.reorder_threshold, c.category_name
");

// password_reset_tokens table
$conn->query("CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// audit_log table
$conn->query("CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    old_values TEXT NULL,
    new_values TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_action (action_type),
    INDEX idx_table (table_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");