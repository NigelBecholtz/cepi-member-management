<?php

namespace Cepi\Models;

require_once __DIR__ . '/../../config/database.php';

use Database;

class Admin {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function login($username, $password) {
        $stmt = $this->db->prepare("
            SELECT admin_id, username, password_hash, email, full_name, is_active
            FROM admin_users
            WHERE username = :username AND is_active = TRUE
        ");
        
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password_hash'])) {
            // Update last login
            $updateStmt = $this->db->prepare("
                UPDATE admin_users 
                SET last_login = CURRENT_TIMESTAMP 
                WHERE admin_id = :admin_id
            ");
            $updateStmt->execute([':admin_id' => $admin['admin_id']]);
            
            return [
                'admin_id' => $admin['admin_id'],
                'username' => $admin['username'],
                'email' => $admin['email'],
                'full_name' => $admin['full_name']
            ];
        }
        
        return false;
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT admin_id, username, email, full_name, is_active, last_login, created_at
            FROM admin_users
            WHERE admin_id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    
    public function getAll() {
        $stmt = $this->db->query("
            SELECT admin_id, username, email, full_name, is_active, last_login, created_at
            FROM admin_users
            ORDER BY created_at DESC
        ");
        return $stmt->fetchAll();
    }
    
    public function create($username, $password, $email = null, $fullName = null) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("
            INSERT INTO admin_users (username, password_hash, email, full_name)
            VALUES (:username, :password_hash, :email, :full_name)
        ");
        
        return $stmt->execute([
            ':username' => $username,
            ':password_hash' => $passwordHash,
            ':email' => $email,
            ':full_name' => $fullName
        ]);
    }
    
    public function updatePassword($adminId, $newPassword) {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("
            UPDATE admin_users 
            SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP
            WHERE admin_id = :admin_id
        ");
        
        return $stmt->execute([
            ':admin_id' => $adminId,
            ':password_hash' => $passwordHash
        ]);
    }
}

