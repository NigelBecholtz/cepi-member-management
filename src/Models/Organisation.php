<?php

namespace Cepi\Models;

require_once __DIR__ . '/../../config/database.php';

use Database;

class Organisation {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function getAll() {
        $stmt = $this->db->query("
            SELECT organisation_id, organisation_name, created_at, updated_at
            FROM organisations
            ORDER BY organisation_name
        ");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT organisation_id, organisation_name, created_at, updated_at
            FROM organisations
            WHERE organisation_id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    
    public function create($name) {
        $stmt = $this->db->prepare("
            INSERT INTO organisations (organisation_name)
            VALUES (:name)
        ");
        $stmt->execute([':name' => $name]);
        return $this->db->lastInsertId();
    }
    
    public function update($id, $name) {
        $stmt = $this->db->prepare("
            UPDATE organisations
            SET organisation_name = :name
            WHERE organisation_id = :id
        ");
        return $stmt->execute([':name' => $name, ':id' => $id]);
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare("
            DELETE FROM organisations
            WHERE organisation_id = :id
        ");
        return $stmt->execute([':id' => $id]);
    }
}



