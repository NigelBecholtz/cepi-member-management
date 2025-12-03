-- CEPI Admin System Migration
-- Voeg admin gebruikers en activity logging toe
-- Voer dit bestand uit NADAT je schema.sql hebt geïmporteerd
-- Note: Database should already be selected when running this script

-- Tabel voor admin gebruikers
CREATE TABLE IF NOT EXISTS admin_users (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    full_name VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel voor activity logs
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin', 'organisation') NOT NULL,
    user_id INT,
    username VARCHAR(255),
    action_type VARCHAR(50) NOT NULL, -- 'api_call', 'login', 'upload', 'create_account', etc.
    action_details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_type),
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Maak standaard admin account aan (wachtwoord: admin123 - VERANDER DIT IN PRODUCTIE!)
-- Gebruik: php -r "echo password_hash('admin123', PASSWORD_DEFAULT);" om een nieuwe hash te genereren
INSERT INTO admin_users (username, password_hash, email, full_name, is_active) 
VALUES (
    'admin',
    '$2y$12$wxDEFezsCIX//3tnhRw2iO7QHIIkZPmwhld6sAAhye3CaXXzavMyi', -- admin123 (gegenereerd met PASSWORD_DEFAULT)
    'admin@cepi.local',
    'System Administrator',
    TRUE
) ON DUPLICATE KEY UPDATE username=username;

