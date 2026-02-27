-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- =========================
-- DROP TABLES (for fresh setup)
-- =========================
DROP TABLE IF EXISTS inventory_transactions;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =========================
-- CREATE DATABASE
-- =========================
CREATE DATABASE IF NOT EXISTS login_system;
USE login_system;

-- =========================
-- USERS TABLE
-- =========================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user'
);

-- =========================
-- CATEGORIES TABLE
-- =========================
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE
);

INSERT IGNORE INTO categories (category_name) VALUES
('Diesel Engine Oil'),
('Gasoline Engine Oils'),
('Transmission Fluids'),
('Coolants'),
('Differential Oil');

-- =========================
-- PRODUCTS TABLE
-- =========================
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    description VARCHAR(255) NOT NULL,
    unit VARCHAR(50),
    unit_cost DECIMAL(10,2) NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL,
    code VARCHAR(50) UNIQUE,
    initial_quantity INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
        ON DELETE SET NULL
);

-- =========================
-- INVENTORY TRANSACTIONS TABLE
-- =========================
CREATE TABLE inventory_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    quantity_change INT NOT NULL, 
    transaction_type VARCHAR(50) NOT NULL, 
    remarks VARCHAR(255),
    created_by INT,
    FOREIGN KEY (product_id) REFERENCES products(product_id)
        ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
);

-- =========================
-- VIEW FOR CURRENT STOCK
-- =========================
CREATE OR REPLACE VIEW product_stock AS
SELECT 
    p.product_id,
    p.description,
    p.initial_quantity + IFNULL(SUM(t.quantity_change), 0) AS current_stock
FROM products p
LEFT JOIN inventory_transactions t 
    ON p.product_id = t.product_id
GROUP BY p.product_id, p.description, p.initial_quantity;
