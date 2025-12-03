<?php

namespace Cepi\Models;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Utils/EmailHasher.php';

use Database;
use Cepi\Utils\EmailHasher;

class Member {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function getByEmail($email, $activeOnly = true) {
        // Use lookup hash for searching (faster than decrypting all emails)
        $lookupHash = EmailHasher::hashForLookup($email);
        
        $sql = "
            SELECT m.*, o.organisation_name
            FROM members m
            JOIN organisations o ON m.organisation_id = o.organisation_id
            WHERE m.email_lookup_hash = :lookup_hash
        ";
        
        if ($activeOnly) {
            $sql .= " AND m.is_active = TRUE";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':lookup_hash' => $lookupHash]);
        $result = $stmt->fetch();
        
        // Decrypt email for return value
        if ($result && !empty($result['email_hash'])) {
            $result['email_address'] = EmailHasher::unhash($result['email_hash']);
        }
        
        return $result;
    }
    
    public function getByOrganisation($organisationId, $activeOnly = true) {
        $sql = "
            SELECT *
            FROM members
            WHERE organisation_id = :org_id
        ";
        
        if ($activeOnly) {
            $sql .= " AND is_active = TRUE";
        }
        
        $sql .= " ORDER BY email_lookup_hash";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':org_id' => $organisationId]);
        $results = $stmt->fetchAll();
        
        // Decrypt all emails
        foreach ($results as &$result) {
            if (!empty($result['email_hash'])) {
                $result['email_address'] = EmailHasher::unhash($result['email_hash']);
            }
        }
        
        return $results;
    }
    
    public function countByOrganisation($organisationId, $activeOnly = true) {
        $sql = "
            SELECT COUNT(*) as total
            FROM members
            WHERE organisation_id = :org_id
        ";
        
        if ($activeOnly) {
            $sql .= " AND is_active = TRUE";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':org_id' => $organisationId]);
        $result = $stmt->fetch();
        return (int)$result['total'];
    }
}

