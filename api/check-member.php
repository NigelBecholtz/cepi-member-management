<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Utils/ErrorHandler.php';
require_once __DIR__ . '/../src/Models/ActivityLog.php';
require_once __DIR__ . '/../src/Utils/EmailHasher.php';

use Cepi\Utils\ErrorHandler;
use Cepi\Models\ActivityLog;
use Cepi\Utils\EmailHasher;

// Initialiseer error handler
ErrorHandler::init();

try {
    $db = Database::getInstance()->getConnection();
    $activityLog = new ActivityLog();
    
    // GET of POST
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $email = $_GET['email'] ?? '';
    } else {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
    }
    
    $email = strtolower(trim($email));
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        
        // Log invalid API call
        $activityLog->log('organisation', null, 'api', 'api_call', [
            'email' => $email,
            'success' => false,
            'reason' => 'invalid_email'
        ]);
        
        echo json_encode([
            'error' => 'Invalid email address',
            'found' => false,
            'mm_cepi' => false,
            'organisation_id' => null,
            'organisation_name' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Hash email for lookup
    $emailLookupHash = EmailHasher::hashForLookup($email);
    
    // Check member using lookup hash
    $stmt = $db->prepare("
        SELECT m.member_id, m.organisation_id, m.mm_cepi, o.organisation_name
        FROM members m
        JOIN organisations o ON m.organisation_id = o.organisation_id
        WHERE m.email_lookup_hash = :lookup_hash AND m.is_active = TRUE
        LIMIT 1
    ");
    
    $stmt->execute([':lookup_hash' => $emailLookupHash]);
    $member = $stmt->fetch();
    
    if ($member) {
        // Log successful API call
        $activityLog->log('organisation', $member['organisation_id'], 'api', 'api_call', [
            'email' => $email,
            'success' => true,
            'found' => true,
            'mm_cepi' => (bool)$member['mm_cepi'],
            'organisation_id' => $member['organisation_id']
        ]);
        
        echo json_encode([
            'found' => true,
            'mm_cepi' => (bool)$member['mm_cepi'],
            'organisation_id' => (int)$member['organisation_id'],
            'organisation_name' => $member['organisation_name']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Log API call - member not found
        $activityLog->log('organisation', null, 'api', 'api_call', [
            'email' => $email,
            'success' => true,
            'found' => false
        ]);
        
        echo json_encode([
            'found' => false,
            'mm_cepi' => false,
            'organisation_id' => null,
            'organisation_name' => null
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (\PDOException $e) {
    http_response_code(500);
    ErrorHandler::log("API Database Error: " . $e->getMessage(), 'ERROR');
    
    // Log API error
    $activityLog = new ActivityLog();
    $activityLog->log('organisation', null, 'api', 'api_call', [
        'success' => false,
        'error' => 'database_error'
    ]);
    
    echo json_encode([
        'error' => 'Database error',
        'found' => false,
        'mm_cepi' => false,
        'organisation_id' => null,
        'organisation_name' => null
    ], JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    http_response_code(500);
    ErrorHandler::log("API Error: " . $e->getMessage(), 'ERROR');
    
    // Log API error
    $activityLog = new ActivityLog();
    $activityLog->log('organisation', null, 'api', 'api_call', [
        'success' => false,
        'error' => 'server_error'
    ]);
    
    echo json_encode([
        'error' => 'Server error',
        'found' => false,
        'mm_cepi' => false,
        'organisation_id' => null,
        'organisation_name' => null
    ], JSON_UNESCAPED_UNICODE);
}

