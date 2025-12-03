<?php

/**
 * API Key System Test Script
 * Test of het API key systeem correct werkt
 * Dit script moet worden uitgevoerd vanaf de public/ directory
 *
 * DEBUGGING: Als dit script niets uitvoert, uncomment de debug sectie hieronder
 */


// === DEBUG SECTION - Uncomment als script niets uitvoert ===
echo "=== PHP DEBUG INFO ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Current Directory: " . __DIR__ . "\n";

echo "\n=== FILE CHECKS ===\n";
$files = [
    '../config/bootstrap.php',
    '../vendor/autoload.php',
    '../.env',
    '../src/Models/ApiKey.php',
    '../src/Utils/ApiKeyValidator.php'
];

foreach ($files as $file) {
    $fullPath = __DIR__ . '/' . $file;
    echo "Checking: $file\n";
    echo "  Full path: $fullPath\n";
    echo "  Exists: " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";
    if (file_exists($fullPath)) {
        echo "  Size: " . filesize($fullPath) . " bytes\n";
    }
    echo "\n";
}

echo "=== ENVIRONMENT ===\n";
echo "APP_DEBUG: " . ($_ENV['APP_DEBUG'] ?? 'not set') . "\n";
echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? 'not set') . "\n";

echo "\n=== TRYING TO LOAD BOOTSTRAP ===\n";
try {
    require_once '../config/bootstrap.php';
    echo "✅ Bootstrap loaded successfully\n";
} catch (Exception $e) {
    echo "❌ Bootstrap load failed: " . $e->getMessage() . "\n";
}

die("=== END DEBUG ===\n");
// === END DEBUG SECTION ===

echo "🧪 CEPI API Key System Test\n";
echo "================================\n\n";

try {
require_once '../src/Models/ApiKey.php';
require_once '../src/Utils/ApiKeyValidator.php';
require_once '../src/Models/ActivityLog.php';

echo "🧪 CEPI API Key System Test\n";
echo "================================\n\n";

try {
    // Test 1: Controleer database connectie
    echo "1. Testing database connection...\n";
    $db = Database::getInstance()->getConnection();
    echo "✅ Database connection successful\n\n";

    // Test 2: Controleer of api_keys tabel bestaat
    echo "2. Checking if api_keys table exists...\n";
    $result = $db->query("SHOW TABLES LIKE 'api_keys'");
    if ($result->rowCount() > 0) {
        echo "✅ api_keys table exists\n\n";
    } else {
        echo "❌ api_keys table does NOT exist\n";
        echo "   Please run: mysql -u [user] -p [database] < ../database/migration_api_keys.sql\n\n";
        exit(1);
    }

    // Test 3: Controleer admin_users tabel
    echo "3. Checking if admin_users table exists...\n";
    $result = $db->query("SHOW TABLES LIKE 'admin_users'");
    if ($result->rowCount() > 0) {
        echo "✅ admin_users table exists\n\n";
    } else {
        echo "❌ admin_users table does NOT exist\n";
        echo "   Please run: mysql -u [user] -p [database] < ../database/migration_admin_system.sql\n\n";
        exit(1);
    }

    // Test 4: Controleer of er admin accounts zijn
    echo "4. Checking for admin accounts...\n";
    $admins = $db->query("SELECT COUNT(*) as count FROM admin_users WHERE is_active = TRUE");
    $adminCount = $admins->fetch()['count'];
    if ($adminCount > 0) {
        echo "✅ Found {$adminCount} active admin account(s)\n\n";
    } else {
        echo "❌ No active admin accounts found\n";
        echo "   Please run: mysql -u [user] -p [database] < ../database/migration_admin_system.sql\n\n";
        exit(1);
    }

    // Test 5: Test API key generation
    echo "5. Testing API key generation...\n";
    $apiKeyModel = new \Cepi\Models\ApiKey();

    // Haal eerste admin op
    $admin = $db->query("SELECT admin_id, username FROM admin_users WHERE is_active = TRUE LIMIT 1")->fetch();
    if (!$admin) {
        echo "❌ No admin account found for testing\n\n";
        exit(1);
    }

    $testKey = $apiKeyModel->generate("Test API Key - " . date('Y-m-d H:i:s'), null, $admin['admin_id']);
    echo "✅ API key generated successfully\n";
    echo "   Key ID: {$testKey['id']}\n";
    echo "   Key: {$testKey['key']}\n\n";

    // Test 6: Test API key validatie
    echo "6. Testing API key validation...\n";
    $apiKeyValidator = new \Cepi\Utils\ApiKeyValidator();
    $validationResult = $apiKeyValidator->validate($testKey['key']);

    if ($validationResult) {
        echo "✅ API key validation successful\n";
        echo "   Key Name: {$validationResult['key_name']}\n";
        echo "   Created By: {$validationResult['created_by']}\n\n";
    } else {
        echo "❌ API key validation failed\n\n";
        exit(1);
    }

    // Test 7: Test API endpoint (simulatie)
    echo "7. Testing API endpoint simulation...\n";

    // Simuleer een request met API key
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $testKey['key'];
    $_GET['email'] = 'test@example.com';

    $apiKeyFromRequest = $apiKeyValidator->validateFromRequest();
    if ($apiKeyFromRequest) {
        echo "✅ API key extraction from request successful\n";
        echo "   Extracted Key ID: {$apiKeyFromRequest['api_key_id']}\n\n";
    } else {
        echo "❌ API key extraction from request failed\n\n";
        exit(1);
    }

    // Test 8: Controleer logging
    echo "8. Testing activity logging...\n";
    $activityLog = new \Cepi\Models\ActivityLog();
    $logResult = $activityLog->log('organisation', null, 'api', 'test_api_call', [
        'test' => true,
        'api_key_id' => $apiKeyFromRequest['api_key_id'],
        'api_key_name' => $apiKeyFromRequest['key_name']
    ]);

    if ($logResult) {
        echo "✅ Activity logging successful\n\n";
    } else {
        echo "❌ Activity logging failed\n\n";
    }

    // Cleanup: Verwijder test API key
    echo "9. Cleaning up test data...\n";
    $db->prepare("DELETE FROM api_keys WHERE api_key_id = ?")->execute([$testKey['id']]);
    echo "✅ Test API key removed\n\n";

    echo "🎉 ALL TESTS PASSED! API Key systeem werkt correct.\n\n";
    echo "📋 Volgende stappen:\n";
    echo "1. Maak een echte API key via admin panel (/admin/api-keys.php)\n";
    echo "2. Test de API endpoint: /api/check-member.php?email=test@example.com\n";
    echo "3. Gebruik de API key in Authorization header\n\n";

} catch (Exception $e) {
    echo "❌ Test failed with error: " . $e->getMessage() . "\n\n";
    echo "🔧 Troubleshooting:\n";
    echo "- Controleer database credentials in .env\n";
    echo "- Zorg dat alle migraties zijn uitgevoerd\n";
    echo "- Controleer PHP error logs\n\n";
    exit(1);
}
?>