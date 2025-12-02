<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Utils/ErrorHandler.php';
require_once __DIR__ . '/../../src/Models/ActivityLog.php';
require_once __DIR__ . '/../../src/Utils/EmailHasher.php';
require_once __DIR__ . '/../../src/Utils/RateLimiter.php';
require_once __DIR__ . '/../../src/Utils/ApiKeyValidator.php';

use Cepi\Utils\ErrorHandler;
use Cepi\Models\ActivityLog;
use Cepi\Utils\EmailHasher;
use Cepi\Utils\RateLimiter;
use Cepi\Utils\ApiKeyValidator;

// Initialiseer error handler
ErrorHandler::init();

// Validate API key
$apiKeyValidator = new ApiKeyValidator();
$apiKeyData = $apiKeyValidator->validateFromRequest();

if (!$apiKeyData) {
    http_response_code(401);

    // Log invalid API key attempt
    try {
        $activityLog = new ActivityLog();
        $activityLog->log('organisation', null, null, 'api_call_invalid_key', [
            'success' => false,
            'reason' => 'invalid_api_key'
        ]);
    } catch (\Exception $e) {
        // Ignore logging errors
    }

    echo json_encode([
        'error' => 'API key required',
        'message' => 'Please provide a valid API key. See documentation for authentication methods.',
        'found' => false,
        'mm_cepi' => false,
        'organisation_id' => null,
        'organisation_name' => null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Update last used timestamp for the API key
$apiKeyValidator->getApiKeyModel()->updateLastUsed($apiKeyData['api_key_id']);

// Check rate limit (60 requests per minute, 1000 per hour)
$rateLimiter = new RateLimiter(60, 1000);
$rateLimit = $rateLimiter->checkLimit();

// Add rate limit headers
header('X-RateLimit-Limit: ' . $rateLimit['limit']);
header('X-RateLimit-Remaining: ' . $rateLimit['remaining']);
header('X-RateLimit-Reset: ' . $rateLimit['reset']);

// If rate limit exceeded, return 429
if (!$rateLimit['allowed']) {
    http_response_code(429);

    // Log rate limit exceeded
    try {
        $activityLog = new ActivityLog();
        $activityLog->log('organisation', null, $apiKeyData['key_name'], 'api_call', [
            'success' => false,
            'reason' => 'rate_limit_exceeded',
            'limit_type' => $rateLimit['limit_type']
        ], null, null, $apiKeyData['api_key_id']);
    } catch (\Exception $e) {
        // Ignore logging errors for rate limit
    }

    echo json_encode([
        'error' => 'Rate limit exceeded',
        'message' => 'Too many requests. Please try again later.',
        'retry_after' => $rateLimit['reset'] - time(),
        'found' => false,
        'mm_cepi' => false,
        'organisation_id' => null,
        'organisation_name' => null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Clean old cache files occasionally (1% chance)
if (rand(1, 100) === 1) {
    $rateLimiter->cleanOldCache();
}

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
        $activityLog->log('organisation', null, $apiKeyData['key_name'], 'api_call', [
            'email' => $email,
            'success' => false,
            'reason' => 'invalid_email'
        ], null, null, $apiKeyData['api_key_id']);
        
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
        $activityLog->log('organisation', $member['organisation_id'], $apiKeyData['key_name'], 'api_call', [
            'email' => $email,
            'success' => true,
            'found' => true,
            'mm_cepi' => (bool)$member['mm_cepi'],
            'organisation_id' => $member['organisation_id']
        ], null, null, $apiKeyData['api_key_id']);
        
        echo json_encode([
            'found' => true,
            'mm_cepi' => (bool)$member['mm_cepi'],
            'organisation_id' => (int)$member['organisation_id'],
            'organisation_name' => $member['organisation_name']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Log API call - member not found
        $activityLog->log('organisation', null, $apiKeyData['key_name'], 'api_call', [
            'email' => $email,
            'success' => true,
            'found' => false
        ], null, null, $apiKeyData['api_key_id']);
        
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
    $activityLog->log('organisation', null, $apiKeyData['key_name'], 'api_call', [
        'success' => false,
        'error' => 'database_error'
    ], null, null, $apiKeyData['api_key_id']);
    
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
    $activityLog->log('organisation', null, $apiKeyData['key_name'], 'api_call', [
        'success' => false,
        'error' => 'server_error'
    ], null, null, $apiKeyData['api_key_id']);
    
    echo json_encode([
        'error' => 'Server error',
        'found' => false,
        'mm_cepi' => false,
        'organisation_id' => null,
        'organisation_name' => null
    ], JSON_UNESCAPED_UNICODE);
}

