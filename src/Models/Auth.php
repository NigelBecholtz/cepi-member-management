<?php

namespace Cepi\Models;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Utils/EmailHasher.php';

use Database;
use Cepi\Utils\EmailHasher;

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function login($username, $password) {
        $stmt = $this->db->prepare("
            SELECT a.*, o.organisation_name, o.organisation_id
            FROM organisation_auth a
            JOIN organisations o ON a.organisation_id = o.organisation_id
            WHERE a.username = :username AND a.is_active = TRUE
        ");
        
        $stmt->execute([':username' => $username]);
        $auth = $stmt->fetch();
        
        if ($auth && password_verify($password, $auth['password_hash'])) {
            // Update last login
            $updateStmt = $this->db->prepare("
                UPDATE organisation_auth 
                SET last_login = CURRENT_TIMESTAMP 
                WHERE auth_id = :auth_id
            ");
            $updateStmt->execute([':auth_id' => $auth['auth_id']]);
            
            // Decrypt email if it exists
            $email = null;
            if (!empty($auth['email_hash'])) {
                $email = EmailHasher::unhash($auth['email_hash']);
                if ($email === false) {
                    $email = null;
                }
            }
            
            return [
                'auth_id' => $auth['auth_id'],
                'organisation_id' => $auth['organisation_id'],
                'organisation_name' => $auth['organisation_name'],
                'username' => $auth['username'],
                'email' => $email
            ];
        }
        
        return false;
    }
    
    public function createAuth($organisationId, $username, $password, $email = null) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Hash email if provided
        $emailHash = null;
        $emailLookupHash = null;
        if (!empty($email)) {
            $emailHash = EmailHasher::hash($email);
            $emailLookupHash = EmailHasher::hashForLookup($email);
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO organisation_auth (organisation_id, username, password_hash, email, email_hash, email_lookup_hash)
            VALUES (:org_id, :username, :password_hash, '', :email_hash, :email_lookup_hash)
        ");
        
        return $stmt->execute([
            ':org_id' => $organisationId,
            ':username' => $username,
            ':password_hash' => $passwordHash,
            ':email_hash' => $emailHash,
            ':email_lookup_hash' => $emailLookupHash
        ]);
    }
    
    public function updatePassword($authId, $newPassword) {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("
            UPDATE organisation_auth 
            SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP
            WHERE auth_id = :auth_id
        ");
        
        return $stmt->execute([
            ':auth_id' => $authId,
            ':password_hash' => $passwordHash
        ]);
    }
    
    public function getByOrganisationId($organisationId) {
        $stmt = $this->db->prepare("
            SELECT * FROM organisation_auth
            WHERE organisation_id = :org_id AND is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute([':org_id' => $organisationId]);
        $result = $stmt->fetch();
        
        // Decrypt email if it exists
        if ($result && !empty($result['email_hash'])) {
            $result['email'] = EmailHasher::unhash($result['email_hash']);
            if ($result['email'] === false) {
                $result['email'] = null;
            }
        }
        
        return $result;
    }
}

