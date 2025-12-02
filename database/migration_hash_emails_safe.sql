-- CEPI Email Hashing Migration (Safe Version)
-- This migration adds email hashing support to the database
-- Run this AFTER schema.sql and other migrations
-- Note: Database should already be selected when running this script

-- Add columns for hashed emails in members table (only if they don't exist)
-- Check and add email_hash
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'members' 
  AND COLUMN_NAME = 'email_hash';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE members ADD COLUMN email_hash VARCHAR(255) NULL AFTER email_address',
    'SELECT "Column email_hash already exists in members" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add email_lookup_hash
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'members' 
  AND COLUMN_NAME = 'email_lookup_hash';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE members ADD COLUMN email_lookup_hash VARCHAR(64) NULL AFTER email_hash',
    'SELECT "Column email_lookup_hash already exists in members" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Make email_address nullable
ALTER TABLE members MODIFY COLUMN email_address VARCHAR(255) NULL;

-- Add columns for hashed emails in organisation_auth table
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'organisation_auth' 
  AND COLUMN_NAME = 'email_hash';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE organisation_auth ADD COLUMN email_hash VARCHAR(255) NULL AFTER email',
    'SELECT "Column email_hash already exists in organisation_auth" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'organisation_auth' 
  AND COLUMN_NAME = 'email_lookup_hash';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE organisation_auth ADD COLUMN email_lookup_hash VARCHAR(64) NULL AFTER email_hash',
    'SELECT "Column email_lookup_hash already exists in organisation_auth" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create indexes (ignore errors if they exist)
-- Note: MySQL doesn't support IF NOT EXISTS for CREATE INDEX, so we check first
SELECT COUNT(*) INTO @idx_exists 
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'members' 
  AND INDEX_NAME = 'idx_email_lookup_hash';

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_email_lookup_hash ON members(email_lookup_hash)',
    'SELECT "Index idx_email_lookup_hash already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @idx_exists 
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'organisation_auth' 
  AND INDEX_NAME = 'idx_org_auth_email_lookup_hash';

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_org_auth_email_lookup_hash ON organisation_auth(email_lookup_hash)',
    'SELECT "Index idx_org_auth_email_lookup_hash already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop old unique constraint (ignore if doesn't exist)
SELECT COUNT(*) INTO @idx_exists 
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'members' 
  AND INDEX_NAME = 'unique_org_email';

SET @sql = IF(@idx_exists > 0,
    'ALTER TABLE members DROP INDEX unique_org_email',
    'SELECT "Index unique_org_email does not exist" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create new unique constraint (ignore if exists)
SELECT COUNT(*) INTO @idx_exists 
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'members' 
  AND INDEX_NAME = 'unique_org_email_lookup';

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE members ADD UNIQUE KEY unique_org_email_lookup (organisation_id, email_lookup_hash)',
    'SELECT "Index unique_org_email_lookup already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Note: After running this migration, you need to run a PHP script to hash existing emails
-- See: database/migrate_existing_emails.php

