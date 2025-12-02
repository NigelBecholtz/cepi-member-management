<?php

namespace Cepi\Models;

require_once __DIR__ . '/../../config/database.php';

use Database;

class ActivityLog {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Log een activiteit
     */
    public function log($userType, $userId, $username, $actionType, $actionDetails = null, $ipAddress = null, $userAgent = null, $apiKeyId = null) {
        // Haal IP adres op als niet gegeven
        if ($ipAddress === null) {
            $ipAddress = self::getClientIp();
        }

        // Haal user agent op als niet gegeven
        if ($userAgent === null) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        }

        $stmt = $this->db->prepare("
            INSERT INTO activity_logs
            (user_type, user_id, api_key_id, username, action_type, action_details, ip_address, user_agent)
            VALUES (:user_type, :user_id, :api_key_id, :username, :action_type, :action_details, :ip_address, :user_agent)
        ");

        return $stmt->execute([
            ':user_type' => $userType,
            ':user_id' => $userId,
            ':api_key_id' => $apiKeyId,
            ':username' => $username,
            ':action_type' => $actionType,
            ':action_details' => $actionDetails ? json_encode($actionDetails, JSON_UNESCAPED_UNICODE) : null,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent
        ]);
    }
    
    /**
     * Haal logs op met filters
     */
    public function getLogs($filters = [], $limit = 100, $offset = 0) {
        $where = [];
        $params = [];
        
        if (isset($filters['user_type'])) {
            $where[] = "user_type = :user_type";
            $params[':user_type'] = $filters['user_type'];
        }
        
        if (isset($filters['action_type'])) {
            $where[] = "action_type = :action_type";
            $params[':action_type'] = $filters['action_type'];
        }
        
        if (isset($filters['user_id'])) {
            $where[] = "user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        if (isset($filters['api_key_id'])) {
            $where[] = "api_key_id = :api_key_id";
            $params[':api_key_id'] = $filters['api_key_id'];
        }

        if (isset($filters['date_from'])) {
            $where[] = "created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $where[] = "created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $sql = "
            SELECT *
            FROM activity_logs
            {$whereClause}
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Tel logs met filters
     */
    public function countLogs($filters = []) {
        $where = [];
        $params = [];
        
        if (isset($filters['user_type'])) {
            $where[] = "user_type = :user_type";
            $params[':user_type'] = $filters['user_type'];
        }
        
        if (isset($filters['action_type'])) {
            $where[] = "action_type = :action_type";
            $params[':action_type'] = $filters['action_type'];
        }
        
        if (isset($filters['user_id'])) {
            $where[] = "user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        if (isset($filters['api_key_id'])) {
            $where[] = "api_key_id = :api_key_id";
            $params[':api_key_id'] = $filters['api_key_id'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $sql = "SELECT COUNT(*) as total FROM activity_logs {$whereClause}";
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)$result['total'];
    }
    
    /**
     * Haal client IP adres op
     */
    private static function getClientIp() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

