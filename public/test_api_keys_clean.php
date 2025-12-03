<?php

/**
 * CLEAN API Key System Test Script
 * Eenvoudige versie zonder debug code
 */

require_once '../config/bootstrap.php';
require_once '../src/Models/ApiKey.php';
require_once '../src/Utils/ApiKeyValidator.php';
require_once '../src/Models/ActivityLog.php';

echo "🧪 CEPI API Key System Test\n";
echo "================================\n\n";

try {
    // Test 1: Database connectie
    echo "1. Testing database connection...\n";
    $db = Database::getInstance()->getConnection();
    echo "✅ Database connection successful\n\n";

    // Test 2: api_keys tabel
    echo "2. Checking api_keys table...\n";
    $result = $db->query("SHOW TABLES LIKE 'api_keys'");
    if ($result->rowCount() > 0) {
        echo "✅ api_keys table exists\n\n";
    } else {
        echo "❌ api_keys table missing - run migration!\n\n";
        exit(1);
    }

    // Test 3: admin_users tabel
    echo "3. Checking admin_users table...\n";
    $result = $db->query("SHOW TABLES LIKE 'admin_users'");
    if ($result->rowCount() > 0) {
        echo "✅ admin_users table exists\n\n";
    } else {
        echo "❌ admin_users table missing - run migration!\n\n";
        exit(1);
    }

    // Test 4: Admin accounts
    echo "4. Checking admin accounts...\n";
    $admins = $db->query("SELECT COUNT(*) as count FROM admin_users WHERE is_active = TRUE");
    $count = $admins->fetch()['count'];
    if ($count > 0) {
        echo "✅ Found {$count} active admin(s)\n\n";
    } else {
        echo "❌ No active admins found\n\n";
        exit(1);
    }

    // Test 5: API key generatie
    echo "5. Testing API key generation...\n";
    $apiKeyModel = new \Cepi\Models\ApiKey();
    $admin = $db->query("SELECT admin_id FROM admin_users WHERE is_active = TRUE LIMIT 1")->fetch();

    if (!$admin) {
        echo "❌ No admin found for testing\n\n";
        exit(1);
    }

    $testKey = $apiKeyModel->generate("Test Key - " . date('Y-m-d H:i:s'), null, $admin['admin_id']);
    echo "✅ API key generated\n";
    echo "   Key ID: {$testKey['id']}\n";
    echo "   Key: " . substr($testKey['key'], 0, 20) . "...\n\n";

    // Test 6: API key validatie
    echo "6. Testing API key validation...\n";
    $apiKeyValidator = new \Cepi\Utils\ApiKeyValidator();
    $validation = $apiKeyValidator->validate($testKey['key']);

    if ($validation) {
        echo "✅ API key validation successful\n\n";
    } else {
        echo "❌ API key validation failed\n\n";
        exit(1);
    }

    // Cleanup
    echo "7. Cleaning up...\n";
    $db->prepare("DELETE FROM api_keys WHERE api_key_id = ?")->execute([$testKey['id']]);
    echo "✅ Test key removed\n\n";

    echo "🎉 ALL TESTS PASSED!\n";
    echo "API Key systeem werkt correct.\n\n";

    echo "📋 Volgende stappen:\n";
    echo "1. Ga naar /admin/api-keys.php\n";
    echo "2. Maak een echte API key aan\n";
    echo "3. Test: /api/check-member.php?email=test@example.com\n\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
    echo "🔧 Check:\n";
    echo "- Database credentials in .env\n";
    echo "- All migrations executed\n";
    echo "- File permissions\n\n";
    exit(1);
}
