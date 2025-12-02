-- Migration: Voeg authenticatie toe voor organisaties
-- Voer dit uit in HeidiSQL op de cepi database

USE cepi;

-- Tabel voor organisatie authenticatie
CREATE TABLE IF NOT EXISTS organisation_auth (
    auth_id INT AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organisation_id) REFERENCES organisations(organisation_id) ON DELETE CASCADE,
    INDEX idx_username (username),
    INDEX idx_org_id (organisation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



