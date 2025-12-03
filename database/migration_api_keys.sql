-- CEPI API Keys Migration
-- Adds support for API key authentication and logging

-- Create API keys table
CREATE TABLE IF NOT EXISTS api_keys (
    api_key_id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(255) NOT NULL,
    api_key_hash VARCHAR(255) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES admin_users(admin_id) ON DELETE SET NULL,
    INDEX idx_key_hash (api_key_hash),
    INDEX idx_active (is_active),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add api_key_id column to activity_logs table
-- This allows us to track which API key made each API call
-- Check if column exists first to avoid duplicate column error
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'activity_logs'
    AND COLUMN_NAME = 'api_key_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE activity_logs ADD COLUMN api_key_id INT NULL AFTER user_id',
    'SELECT "api_key_id column already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index if it doesn't exist
SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'activity_logs'
    AND INDEX_NAME = 'idx_api_key_id'
);

SET @sql2 = IF(@index_exists = 0,
    'ALTER TABLE activity_logs ADD INDEX idx_api_key_id (api_key_id)',
    'SELECT "idx_api_key_id index already exists" as message'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Add foreign key constraint if it doesn't exist
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'activity_logs'
    AND CONSTRAINT_NAME = 'fk_activity_logs_api_key_id'
);

SET @sql3 = IF(@fk_exists = 0,
    'ALTER TABLE activity_logs ADD CONSTRAINT fk_activity_logs_api_key_id FOREIGN KEY (api_key_id) REFERENCES api_keys(api_key_id) ON DELETE SET NULL',
    'SELECT "fk_activity_logs_api_key_id constraint already exists" as message'
);
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

