<?php

header('Content-Type: application/json; charset=utf-8');

// CORS configuration - restrict to allowed origins
$allowedOrigins = [
    'http://localhost:3000',    // Development
    'http://localhost',         // Development
    'https://cepi.local',       // Local development
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // In production, you should specify your actual domain(s)
    // header('Access-Control-Allow-Origin: https://yourdomain.com');
    // For now, allow all but log suspicious requests
header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Utils/ErrorHandler.php';
require_once __DIR__ . '/../src/Models/ActivityLog.php';
require_once __DIR__ . '/../src/Utils/EmailHasher.php';
require_once __DIR__ . '/../src/Utils/ApiKeyValidator.php';

use Cepi\Utils\ErrorHandler;
use Cepi\Models\ActivityLog;
use Cepi\Utils\EmailHasher;
use Cepi\Utils\ApiKeyValidator;

// Initialiseer error handler
ErrorHandler::init();

try {
    $db = Database::getInstance()->getConnection();
    $activityLog = new ActivityLog();
    
    // API Key validation - required for all requests
    $apiKeyValidator = new ApiKeyValidator();
    $apiKeyData = $apiKeyValidator->validateFromRequest();

    if (!$apiKeyData) {
        http_response_code(401);
        echo json_encode([
            'error' => 'Valid API key required',
            'found' => false,
            'mm_cepi' => false,
            'organisation_id' => null,
            'organisation_name' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Update last used timestamp for the API key
    $apiKeyValidator->getApiKeyModel()->updateLastUsed($apiKeyData['api_key_id']);

    // Validate request method
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($requestMethod, ['GET', 'POST'], true)) {
        http_response_code(405);
        echo json_encode([
            'error' => 'Method not allowed. Use GET or POST.',
            'found' => false,
            'mm_cepi' => false,
            'organisation_id' => null,
            'organisation_name' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Input validation and sanitization
    if ($requestMethod === 'GET') {
        $email = trim($_GET['email'] ?? '');
    } else {
        // Validate Content-Type for POST requests
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') === false) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid content type. Use application/json for POST requests.',
                'found' => false,
                'mm_cepi' => false,
                'organisation_id' => null,
                'organisation_name' => null
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid JSON format.',
                'found' => false,
                'mm_cepi' => false,
                'organisation_id' => null,
                'organisation_name' => null
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $email = trim($data['email'] ?? '');
    }
    
    // Sanitize and validate email
    $email = strtolower(trim($email));
    if (empty($email)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Email address is required.',
            'found' => false,
            'mm_cepi' => false,
            'organisation_id' => null,
            'organisation_name' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Additional email validation (filter_var is good but we can be stricter)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        http_response_code(400);
        
        // Log invalid API call
        $activityLog->log('organisation', null, 'api', 'api_call', [
            'email' => $email,
            'success' => false,
            'reason' => 'invalid_email',
            'api_key_id' => $apiKeyData['api_key_id'],
            'api_key_name' => $apiKeyData['key_name']
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
            'organisation_id' => $member['organisation_id'],
            'api_key_id' => $apiKeyData['api_key_id'],
            'api_key_name' => $apiKeyData['key_name']
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
            'found' => false,
            'api_key_id' => $apiKeyData['api_key_id'],
            'api_key_name' => $apiKeyData['key_name']
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
        'error' => 'database_error',
        'api_key_id' => $apiKeyData['api_key_id'] ?? null,
        'api_key_name' => $apiKeyData['key_name'] ?? null
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
        'error' => 'server_error',
        'api_key_id' => $apiKeyData['api_key_id'] ?? null,
        'api_key_name' => $apiKeyData['key_name'] ?? null
    ]);
    
    echo json_encode([
        'error' => 'Server error',
        'found' => false,
        'mm_cepi' => false,
        'organisation_id' => null,
        'organisation_name' => null
    ], JSON_UNESCAPED_UNICODE);
}

