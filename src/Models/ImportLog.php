<?php

namespace Cepi\Models;

require_once __DIR__ . '/../../config/database.php';

use Database;

class ImportLog {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO import_logs 
            (organisation_id, filename, rows_imported, rows_added, rows_updated, rows_inactivated, import_status, error_message)
            VALUES (:org_id, :filename, :imported, :added, :updated, :inactivated, :status, :error)
        ");
        
        return $stmt->execute([
            ':org_id' => $data['organisation_id'],
            ':filename' => $data['filename'],
            ':imported' => $data['rows_imported'],
            ':added' => $data['rows_added'],
            ':updated' => $data['rows_updated'],
            ':inactivated' => $data['rows_inactivated'],
            ':status' => $data['import_status'],
            ':error' => $data['error_message'] ?? null
        ]);
    }
    
    public function getByOrganisation($organisationId, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT *
            FROM import_logs
            WHERE organisation_id = :org_id
            ORDER BY imported_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':org_id', $organisationId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}



