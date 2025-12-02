-- CEPI Email Hashing Migration
-- This migration adds email hashing support to the database
-- Run this AFTER schema.sql and other migrations
-- Note: Database should already be selected when running this script

-- Add columns for hashed emails in members table (only if they don't exist)
SET @dbname = DATABASE();
SET @tablename = "members";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = 'email_hash')
  ) > 0,
  "SELECT 'Column email_hash already exists in members'",
  "ALTER TABLE members ADD COLUMN email_hash VARCHAR(255) NULL AFTER email_address"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = 'email_lookup_hash')
  ) > 0,
  "SELECT 'Column email_lookup_hash already exists in members'",
  "ALTER TABLE members ADD COLUMN email_lookup_hash VARCHAR(64) NULL AFTER email_hash"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Make email_address nullable (we'll keep it for backwards compatibility but it won't be used)
ALTER TABLE members MODIFY COLUMN email_address VARCHAR(255) NULL;

-- Add columns for hashed emails in organisation_auth table (only if they don't exist)
SET @tablename = "organisation_auth";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = 'email_hash')
  ) > 0,
  "SELECT 'Column email_hash already exists in organisation_auth'",
  "ALTER TABLE organisation_auth ADD COLUMN email_hash VARCHAR(255) NULL AFTER email"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = 'email_lookup_hash')
  ) > 0,
  "SELECT 'Column email_lookup_hash already exists in organisation_auth'",
  "ALTER TABLE organisation_auth ADD COLUMN email_lookup_hash VARCHAR(64) NULL AFTER email_hash"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Create indexes for lookup hashes (for fast searching) - ignore if exists
CREATE INDEX IF NOT EXISTS idx_email_lookup_hash ON members(email_lookup_hash);
CREATE INDEX IF NOT EXISTS idx_org_auth_email_lookup_hash ON organisation_auth(email_lookup_hash);

-- Drop old unique constraint on email_address (ignore if doesn't exist)
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = 'members')
      AND (table_schema = @dbname)
      AND (index_name = 'unique_org_email')
  ) > 0,
  "ALTER TABLE members DROP INDEX unique_org_email",
  "SELECT 'Index unique_org_email does not exist'"
));
PREPARE dropIfExists FROM @preparedStatement;
EXECUTE dropIfExists;
DEALLOCATE PREPARE dropIfExists;

-- Create new unique constraint on email_lookup_hash per organisation (ignore if exists)
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = 'members')
      AND (table_schema = @dbname)
      AND (index_name = 'unique_org_email_lookup')
  ) > 0,
  "SELECT 'Index unique_org_email_lookup already exists'",
  "ALTER TABLE members ADD UNIQUE KEY unique_org_email_lookup (organisation_id, email_lookup_hash)"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Note: After running this migration, you need to run a PHP script to hash existing emails
-- See: database/migrate_existing_emails.php

