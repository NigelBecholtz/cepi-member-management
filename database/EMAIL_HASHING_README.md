# Email Hashing Implementation

This document explains how email hashing has been implemented in the CEPI Member Management System.

## Overview

All email addresses in the database are now encrypted/hashed for privacy and security. The system uses:
- **AES-256-GCM encryption** for storing emails (can be decrypted for export)
- **HMAC-SHA256 lookup hashes** for fast searching without decrypting all records

## Database Changes

### New Columns

**members table:**
- `email_hash` (VARCHAR(255)) - Encrypted email address
- `email_lookup_hash` (VARCHAR(64)) - Deterministic hash for searching

**organisation_auth table:**
- `email_hash` (VARCHAR(255)) - Encrypted email address  
- `email_lookup_hash` (VARCHAR(64)) - Deterministic hash for searching

### Unique Constraints

The unique constraint on `(organisation_id, email_address)` has been replaced with `(organisation_id, email_lookup_hash)` to prevent duplicate emails per organization.

## Installation Steps

### 1. Run Database Migration

```bash
mysql -u your_user -p cepi < database/migration_hash_emails.sql
```

### 2. Hash Existing Emails

If you have existing data, run the migration script:

```bash
php database/migrate_existing_emails.php
```

This will hash all existing email addresses in the database.

### 3. Configure Encryption Key

**IMPORTANT:** Set a strong encryption key in your `.env` file:

```env
EMAIL_ENCRYPTION_KEY=your_very_strong_random_key_here_minimum_32_characters
```

You can generate a secure key using:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

**WARNING:** 
- If you change the encryption key, all encrypted emails will become unreadable!
- Keep your encryption key secure and backed up.
- Never commit the encryption key to version control.

## How It Works

### Storing Emails

When an email is stored:
1. The email is encrypted using AES-256-GCM → stored in `email_hash`
2. A deterministic lookup hash is created → stored in `email_lookup_hash`
3. The original `email_address` field is set to empty string (kept for backwards compatibility)

### Searching Emails

When searching for an email:
1. The lookup hash is calculated from the input email
2. The database is queried using `email_lookup_hash` (fast index lookup)
3. If found, the encrypted email is decrypted for display

### Exporting Emails

When exporting:
1. All encrypted emails are retrieved from the database
2. Each email is decrypted using the encryption key
3. Decrypted emails are included in the export file

## Security Considerations

1. **Encryption Key Security:**
   - Store the key in `.env` file (not in code)
   - Use a strong, random key (minimum 32 characters)
   - Rotate keys periodically if needed
   - Backup the key securely

2. **Database Security:**
   - Encrypted emails are still sensitive - protect database access
   - Use database encryption at rest if possible
   - Regular backups should also be encrypted

3. **Performance:**
   - Lookup hashes allow fast searching without decrypting all records
   - Encryption/decryption adds minimal overhead
   - Indexes on `email_lookup_hash` ensure fast queries

## Code Changes

All email operations now use the `EmailHasher` utility class:

```php
use Cepi\Utils\EmailHasher;

// Hash an email for storage
$emailHash = EmailHasher::hash($email);
$lookupHash = EmailHasher::hashForLookup($email);

// Decrypt an email
$decryptedEmail = EmailHasher::unhash($emailHash);

// Verify an email matches a hash
$matches = EmailHasher::verify($email, $emailHash);
```

## Troubleshooting

### Emails Not Decrypting

- Check that `EMAIL_ENCRYPTION_KEY` is set in `.env`
- Verify the key hasn't changed since emails were encrypted
- Check error logs for decryption errors

### Migration Errors

- Ensure `openssl` extension is enabled in PHP
- Verify database columns were created correctly
- Check that existing emails are valid before hashing

### Performance Issues

- Ensure indexes exist on `email_lookup_hash` columns
- Check database query performance with `EXPLAIN`

## Backwards Compatibility

- The `email_address` column still exists but is no longer used
- Old code that reads `email_address` will get empty strings
- All new code should use `EmailHasher` to decrypt emails

## Future Improvements

- Consider adding email encryption key rotation
- Implement audit logging for email access
- Add support for multiple encryption keys (for migration)



