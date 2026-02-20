-- Forecasting & reorder support. Run after setup.sql and migration_dashboard.sql
-- mysql -u root login_system < migration_forecast.sql
-- (migration_dashboard.sql adds reorder_threshold to products)

SET FOREIGN_KEY_CHECKS = 0;

-- Log manager reorder confirmations for reporting
CREATE TABLE IF NOT EXISTS reorder_preparations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    recommended_qty INT NOT NULL,
    confirmed_qty INT NULL,
    status ENUM('pending','confirmed','received') DEFAULT 'pending',
    confirmed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;
