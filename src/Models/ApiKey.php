<?php

namespace Cepi\Models;

require_once __DIR__ . '/../../config/database.php';

use Database;

class ApiKey {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Generate a new API key
     */
    public function generate($name, $expiresAt = null, $createdBy = null) {
        // Generate a random 64-character hex string
        $apiKey = bin2hex(random_bytes(32));

        // Hash the key for storage
        $apiKeyHash = password_hash($apiKey, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("
            INSERT INTO api_keys
            (key_name, api_key_hash, expires_at, created_by)
            VALUES (:key_name, :api_key_hash, :expires_at, :created_by)
        ");

        $stmt->execute([
            ':key_name' => $name,
            ':api_key_hash' => $apiKeyHash,
            ':expires_at' => $expiresAt,
            ':created_by' => $createdBy
        ]);

        $apiKeyId = $this->db->lastInsertId();

        // Return both the key ID and the plain key (for one-time display)
        return [
            'id' => $apiKeyId,
            'key' => $apiKey,
            'name' => $name
        ];
    }

    /**
     * Validate an API key
     */
    public function validate($apiKey) {
        if (empty($apiKey)) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT api_key_id, key_name, api_key_hash, is_active, expires_at, last_used_at, created_at
            FROM api_keys
            WHERE is_active = TRUE
        ");

        $stmt->execute();
        $keys = $stmt->fetchAll();

        foreach ($keys as $keyData) {
            if (password_verify($apiKey, $keyData['api_key_hash'])) {
                // Check if expired
                if ($keyData['expires_at'] && strtotime($keyData['expires_at']) < time()) {
                    continue; // Skip expired keys
                }

                return $keyData;
            }
        }

        return false;
    }

    /**
     * Update last used timestamp for an API key
     */
    public function updateLastUsed($apiKeyId) {
        $stmt = $this->db->prepare("
            UPDATE api_keys
            SET last_used_at = CURRENT_TIMESTAMP
            WHERE api_key_id = :api_key_id
        ");

        return $stmt->execute([':api_key_id' => $apiKeyId]);
    }

    /**
     * Get all API keys
     */
    public function getAll() {
        $stmt = $this->db->prepare("
            SELECT
                api_key_id,
                key_name,
                is_active,
                last_used_at,
                expires_at,
                created_at,
                created_by,
                (SELECT COUNT(*) FROM activity_logs WHERE api_key_id = api_keys.api_key_id) as usage_count
            FROM api_keys
            ORDER BY created_at DESC
        ");

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get API key by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT
                api_key_id,
                key_name,
                is_active,
                last_used_at,
                expires_at,
                created_at,
                created_by,
                (SELECT COUNT(*) FROM activity_logs WHERE api_key_id = api_keys.api_key_id) as usage_count
            FROM api_keys
            WHERE api_key_id = :id
        ");

        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Get API key by hash (for validation)
     */
    public function getByHash($hash) {
        $stmt = $this->db->prepare("
            SELECT api_key_id, key_name, is_active, expires_at, last_used_at, created_at
            FROM api_keys
            WHERE api_key_hash = :hash
        ");

        $stmt->execute([':hash' => $hash]);
        return $stmt->fetch();
    }

    /**
     * Activate an API key
     */
    public function activate($id) {
        $stmt = $this->db->prepare("
            UPDATE api_keys
            SET is_active = TRUE, updated_at = CURRENT_TIMESTAMP
            WHERE api_key_id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    /**
     * Deactivate an API key
     */
    public function deactivate($id) {
        $stmt = $this->db->prepare("
            UPDATE api_keys
            SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP
            WHERE api_key_id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    /**
     * Delete an API key
     */
    public function delete($id) {
        $stmt = $this->db->prepare("
            DELETE FROM api_keys
            WHERE api_key_id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }


    /**
     * Check if a key is expired
     */
    public function isExpired($keyData) {
        if (!$keyData['expires_at']) {
            return false;
        }

        return strtotime($keyData['expires_at']) < time();
    }

    /**
     * Get usage statistics for an API key
     */
    public function getUsageStats($id) {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_calls,
                COUNT(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(action_details, '$.success')) = 'true' THEN 1 END) as successful_calls,
                COUNT(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(action_details, '$.success')) = 'false' THEN 1 END) as failed_calls,
                MAX(created_at) as last_used
            FROM activity_logs
            WHERE api_key_id = :api_key_id AND action_type = 'api_call'
        ");

        $stmt->execute([':api_key_id' => $id]);
        $stats = $stmt->fetch();

        return [
            'total_calls' => (int)$stats['total_calls'],
            'successful_calls' => (int)$stats['successful_calls'],
            'failed_calls' => (int)$stats['failed_calls'],
            'last_used' => $stats['last_used']
        ];
    }
}

