<?php

namespace Cepi\Utils;

/**
 * EmailHasher - Encrypts and decrypts email addresses for privacy
 * Uses AES-256-GCM encryption to protect emails while maintaining lookup and export functionality
 */
class EmailHasher {
    private static $cipher = 'aes-256-gcm';
    private static $keyLength = 32; // 256 bits
    
    /**
     * Get encryption key from environment or generate a default one
     * WARNING: In production, always set EMAIL_ENCRYPTION_KEY in .env!
     */
    private static function getKey() {
        $key = $_ENV['EMAIL_ENCRYPTION_KEY'] ?? null;
        
        if (empty($key)) {
            // Generate a default key (for development only)
            // In production, this should be set in .env
            $key = hash('sha256', 'cepi_default_email_key_change_in_production', true);
        } else {
            // Ensure key is exactly 32 bytes
            $key = hash('sha256', $key, true);
        }
        
        return $key;
    }
    
    /**
     * Hash/Encrypt an email address
     * 
     * @param string $email The email address to encrypt
     * @return string The encrypted email (base64 encoded)
     */
    public static function hash($email) {
        if (empty($email)) {
            return '';
        }
        
        $email = strtolower(trim($email));
        $key = self::getKey();
        
        // Generate a random IV (initialization vector)
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        // Encrypt the email
        $encrypted = openssl_encrypt(
            $email,
            self::$cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        if ($encrypted === false) {
            throw new \Exception('Failed to encrypt email address');
        }
        
        // Combine IV, tag, and encrypted data
        $combined = $iv . $tag . $encrypted;
        
        // Return base64 encoded for storage
        return base64_encode($combined);
    }
    
    /**
     * Unhash/Decrypt an email address
     * 
     * @param string $hashedEmail The encrypted email (base64 encoded)
     * @return string|false The decrypted email or false on failure
     */
    public static function unhash($hashedEmail) {
        if (empty($hashedEmail)) {
            return '';
        }
        
        $key = self::getKey();
        
        // Decode from base64
        $combined = base64_decode($hashedEmail, true);
        if ($combined === false) {
            return false;
        }
        
        // Extract IV, tag, and encrypted data
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $tagLength = 16; // GCM tag is always 16 bytes
        
        if (strlen($combined) < $ivLength + $tagLength) {
            return false;
        }
        
        $iv = substr($combined, 0, $ivLength);
        $tag = substr($combined, $ivLength, $tagLength);
        $encrypted = substr($combined, $ivLength + $tagLength);
        
        // Decrypt the email
        $decrypted = openssl_decrypt(
            $encrypted,
            self::$cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        return $decrypted;
    }
    
    /**
     * Check if an email matches a hashed email
     * 
     * @param string $email The plain email address
     * @param string $hashedEmail The encrypted email from database
     * @return bool True if they match
     */
    public static function verify($email, $hashedEmail) {
        if (empty($email) || empty($hashedEmail)) {
            return false;
        }
        
        $decrypted = self::unhash($hashedEmail);
        if ($decrypted === false) {
            return false;
        }
        
        return strtolower(trim($email)) === strtolower(trim($decrypted));
    }
    
    /**
     * Hash an email for database lookup (deterministic hash for searching)
     * This creates a searchable hash that can be used in WHERE clauses
     * 
     * @param string $email The email address to hash
     * @return string The deterministic hash
     */
    public static function hashForLookup($email) {
        if (empty($email)) {
            return '';
        }
        
        $email = strtolower(trim($email));
        $key = self::getKey();
        
        // Use HMAC-SHA256 for deterministic hashing
        // This allows us to search for emails without decrypting all records
        return hash_hmac('sha256', $email, $key);
    }
    
    /**
     * Check if an email matches a lookup hash
     * 
     * @param string $email The plain email address
     * @param string $lookupHash The lookup hash from database
     * @return bool True if they match
     */
    public static function verifyLookup($email, $lookupHash) {
        if (empty($email) || empty($lookupHash)) {
            return false;
        }
        
        $calculatedHash = self::hashForLookup($email);
        return hash_equals($calculatedHash, $lookupHash);
    }
}



