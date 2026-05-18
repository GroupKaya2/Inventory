<?php
$conn = new mysqli("localhost", "root", "", "login_system");

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

/** Primary key column name for inventory_transactions (id vs transaction_id). */
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

// expenses (safe fallback)
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

// product_stock view
$conn->query("CREATE OR REPLACE VIEW product_stock AS
    SELECT
        p.product_id,
        p.description,
        p.reorder_threshold,
        c.category_name,
        COALESCE(p.initial_quantity, 0)
            + COALESCE(SUM(t.quantity_change), 0) AS current_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN inventory_transactions t ON t.product_id = p.product_id
    GROUP BY p.product_id, p.description, p.reorder_threshold,
            p.initial_quantity, c.category_name
");