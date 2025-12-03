<?php
/**
 * Migration Script: Hash Existing Emails
 * 
 * This script hashes all existing email addresses in the database.
 * Run this AFTER running database/migration_hash_emails.sql
 * 
 * Usage: php database/migrate_existing_emails.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Utils/EmailHasher.php';

use Database;
use Cepi\Utils\EmailHasher;

echo "CEPI Email Hashing Migration\n";
echo "============================\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if columns exist
    $checkColumns = $db->query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'members' 
        AND COLUMN_NAME IN ('email_hash', 'email_lookup_hash')
    ")->fetchAll();
    
    if (count($checkColumns) < 2) {
        echo "ERROR: Email hash columns not found in members table.\n";
        echo "Please run database/migration_hash_emails.sql first!\n";
        exit(1);
    }
    
    // Hash emails in members table
    echo "Hashing emails in members table...\n";
    $members = $db->query("
        SELECT member_id, email_address 
        FROM members 
        WHERE email_address IS NOT NULL 
        AND email_address != ''
        AND (email_hash IS NULL OR email_lookup_hash IS NULL)
    ")->fetchAll();
    
    $membersUpdated = 0;
    $membersStmt = $db->prepare("
        UPDATE members 
        SET email_hash = :email_hash, 
            email_lookup_hash = :email_lookup_hash 
        WHERE member_id = :member_id
    ");
    
    foreach ($members as $member) {
        $email = strtolower(trim($member['email_address']));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "  Skipping invalid email for member_id {$member['member_id']}: {$member['email_address']}\n";
            continue;
        }
        
        try {
            $emailHash = EmailHasher::hash($email);
            $emailLookupHash = EmailHasher::hashForLookup($email);
            
            $membersStmt->execute([
                ':member_id' => $member['member_id'],
                ':email_hash' => $emailHash,
                ':email_lookup_hash' => $emailLookupHash
            ]);
            
            $membersUpdated++;
        } catch (\Exception $e) {
            echo "  ERROR hashing email for member_id {$member['member_id']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "  Updated {$membersUpdated} member emails.\n\n";
    
    // Hash emails in organisation_auth table
    echo "Hashing emails in organisation_auth table...\n";
    $auths = $db->query("
        SELECT auth_id, email 
        FROM organisation_auth 
        WHERE email IS NOT NULL 
        AND email != ''
        AND (email_hash IS NULL OR email_lookup_hash IS NULL)
    ")->fetchAll();
    
    $authsUpdated = 0;
    $authsStmt = $db->prepare("
        UPDATE organisation_auth 
        SET email_hash = :email_hash, 
            email_lookup_hash = :email_lookup_hash 
        WHERE auth_id = :auth_id
    ");
    
    foreach ($auths as $auth) {
        $email = strtolower(trim($auth['email']));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "  Skipping invalid email for auth_id {$auth['auth_id']}: {$auth['email']}\n";
            continue;
        }
        
        try {
            $emailHash = EmailHasher::hash($email);
            $emailLookupHash = EmailHasher::hashForLookup($email);
            
            $authsStmt->execute([
                ':auth_id' => $auth['auth_id'],
                ':email_hash' => $emailHash,
                ':email_lookup_hash' => $emailLookupHash
            ]);
            
            $authsUpdated++;
        } catch (\Exception $e) {
            echo "  ERROR hashing email for auth_id {$auth['auth_id']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "  Updated {$authsUpdated} organisation auth emails.\n\n";
    
    // Verify migration
    echo "Verifying migration...\n";
    $unhashedMembers = $db->query("
        SELECT COUNT(*) as count 
        FROM members 
        WHERE email_address IS NOT NULL 
        AND email_address != ''
        AND (email_hash IS NULL OR email_lookup_hash IS NULL)
    ")->fetch()['count'];
    
    $unhashedAuths = $db->query("
        SELECT COUNT(*) as count 
        FROM organisation_auth 
        WHERE email IS NOT NULL 
        AND email != ''
        AND (email_hash IS NULL OR email_lookup_hash IS NULL)
    ")->fetch()['count'];
    
    if ($unhashedMembers > 0 || $unhashedAuths > 0) {
        echo "  WARNING: {$unhashedMembers} members and {$unhashedAuths} auth records still have unhashed emails.\n";
    } else {
        echo "  SUCCESS: All emails have been hashed!\n";
    }
    
    echo "\nMigration completed!\n";
    echo "\nIMPORTANT: Set EMAIL_ENCRYPTION_KEY in your .env file for production use!\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}



