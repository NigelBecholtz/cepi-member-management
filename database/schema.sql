-- CEPI Member Management Database Schema
-- MySQL 5.7+ / MariaDB 10.2+
-- Note: Database should already be selected when running this script
--
-- SECURITY: This schema includes email hashing columns for privacy compliance
-- - email_address: Legacy field (nullable, kept for backwards compatibility)
-- - email_hash: AES-256-GCM encrypted email addresses
-- - email_lookup_hash: HMAC-SHA256 deterministic hash for fast searching

-- Tabel voor organisaties
CREATE TABLE IF NOT EXISTS organisations (
    organisation_id INT AUTO_INCREMENT PRIMARY KEY,
    organisation_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_org_name (organisation_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel voor leden
CREATE TABLE IF NOT EXISTS members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT NOT NULL,
    email_address VARCHAR(255) NULL,
    email_hash VARCHAR(255) NULL,
    email_lookup_hash VARCHAR(64) NULL,
    mm_cepi BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organisation_id) REFERENCES organisations(organisation_id) ON DELETE CASCADE,
    UNIQUE KEY unique_org_email_lookup (organisation_id, email_lookup_hash),
    INDEX idx_email_lookup_hash (email_lookup_hash),
    INDEX idx_org_id (organisation_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel voor organisatie authenticatie
CREATE TABLE IF NOT EXISTS organisation_auth (
    auth_id INT AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    email_hash VARCHAR(255) NULL,
    email_lookup_hash VARCHAR(64) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organisation_id) REFERENCES organisations(organisation_id) ON DELETE CASCADE,
    INDEX idx_username (username),
    INDEX idx_org_auth_email_lookup_hash (email_lookup_hash),
    INDEX idx_org_id (organisation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel voor import logs
CREATE TABLE IF NOT EXISTS import_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT NOT NULL,
    filename VARCHAR(255),
    rows_imported INT DEFAULT 0,
    rows_added INT DEFAULT 0,
    rows_updated INT DEFAULT 0,
    rows_inactivated INT DEFAULT 0,
    import_status ENUM('success', 'failed', 'partial') DEFAULT 'success',
    error_message TEXT,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organisation_id) REFERENCES organisations(organisation_id) ON DELETE CASCADE,
    INDEX idx_org_id (organisation_id),
    INDEX idx_imported_at (imported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Voorbeeld organisatie (optioneel)
-- INSERT INTO organisations (organisation_name) VALUES ('Voorbeeld Organisatie');

