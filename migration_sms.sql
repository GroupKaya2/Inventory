-- Migration: SMS History Table
-- Creates table to track all SMS notifications sent by the system

CREATE TABLE IF NOT EXISTS sms_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    recipient VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('success', 'failed', 'pending') DEFAULT 'pending',
    provider VARCHAR(50) DEFAULT NULL,
    error_message TEXT NULL,
    product_ids JSON NULL COMMENT 'Array of product IDs that triggered this alert',
    INDEX idx_sent_at (sent_at),
    INDEX idx_status (status),
    INDEX idx_recipient (recipient)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add SMS settings table for managing recipients
CREATE TABLE IF NOT EXISTS sms_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default SMS recipients setting
INSERT IGNORE INTO sms_settings (setting_key, setting_value) 
VALUES ('recipients', '[]');

-- Add SMS notification preferences per user (optional)
CREATE TABLE IF NOT EXISTS user_sms_preferences (
    user_id INT PRIMARY KEY,
    receive_alerts BOOLEAN DEFAULT TRUE,
    phone_number VARCHAR(20) NULL,
    alert_types JSON NULL COMMENT 'Array of alert types user wants to receive',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
