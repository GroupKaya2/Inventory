-- Dashboard support: reorder threshold, work orders, and sample data
-- Run this once: mysql -u root login_system < migration_dashboard.sql

SET FOREIGN_KEY_CHECKS = 0;

-- Add reorder_threshold to products (for low-stock alerts)
-- Run only if column does not exist: ALTER TABLE products ADD COLUMN reorder_threshold INT DEFAULT 5;
ALTER TABLE products ADD COLUMN reorder_threshold INT DEFAULT 5;

-- Work orders (for open/completed counts and labor revenue)
CREATE TABLE IF NOT EXISTS work_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(255) NOT NULL,
    status ENUM('open','completed') NOT NULL DEFAULT 'open',
    labor_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Optional: ensure reorder_threshold exists (if ALTER failed above)
-- Run separately if your MySQL version doesn't support ADD COLUMN IF NOT EXISTS:
-- ALTER TABLE products ADD COLUMN reorder_threshold INT DEFAULT 5;

SET FOREIGN_KEY_CHECKS = 1;
