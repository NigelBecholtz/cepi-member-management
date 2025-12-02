<?php

require_once __DIR__ . '/auth-check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Utils/ErrorHandler.php';
require_once __DIR__ . '/../../src/Utils/CsrfToken.php';
require_once __DIR__ . '/../../src/Models/Organisation.php';
require_once __DIR__ . '/../../src/Models/ActivityLog.php';

use Cepi\Utils\ErrorHandler;
use Cepi\Utils\CsrfToken;
use Cepi\Models\Organisation;
use Cepi\Models\ActivityLog;

ErrorHandler::init();

$adminId = getLoggedInAdminId();
$adminUsername = getLoggedInAdminUsername();
$adminFullName = getLoggedInAdminFullName();

$orgModel = new Organisation();
$activityLog = new ActivityLog();

$message = '';
$error = '';
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!CsrfToken::validate($csrfToken)) {
        $error = "Invalid security token. Please try again.";
        } else {
            // Get organizations from array input (multiple input fields)
            $organisationsArray = $_POST['organisations'] ?? [];
            
            if (empty($organisationsArray) || !is_array($organisationsArray)) {
                $error = "Please enter at least one organization name";
            } else {
                // Clean up and filter empty values
                $orgNames = array_filter(
                    array_map('trim', $organisationsArray),
                    function($name) {
                        return !empty($name);
                    }
                );
            
            if (empty($orgNames)) {
                $error = "No valid organization names found";
            } else {
                $results = [
                    'total' => count($orgNames),
                    'created' => 0,
                    'skipped' => 0,
                    'errors' => [],
                    'created_orgs' => [],
                    'skipped_orgs' => []
                ];
                
                // Get existing organizations for duplicate check
                $existingOrgs = $orgModel->getAll();
                $existingNames = array_map(function($org) {
                    return strtolower(trim($org['organisation_name']));
                }, $existingOrgs);
                
                // Start transaction
                $db = \Database::getInstance()->getConnection();
                $db->beginTransaction();
                
                try {
                    foreach ($orgNames as $index => $orgName) {
                        $orgName = trim($orgName);
                        
                        if (empty($orgName)) {
                            continue;
                        }
                        
                        // Check for duplicates in input
                        $lowerName = strtolower($orgName);
                        if (in_array($lowerName, array_map('strtolower', array_column($results['created_orgs'], 'name')))) {
                            $results['skipped']++;
                            $results['skipped_orgs'][] = [
                                'name' => $orgName,
                                'reason' => 'Duplicate in input'
                            ];
                            continue;
                        }
                        
                        // Check if organization already exists
                        if (in_array($lowerName, $existingNames)) {
                            $results['skipped']++;
                            $results['skipped_orgs'][] = [
                                'name' => $orgName,
                                'reason' => 'Already exists'
                            ];
                            continue;
                        }
                        
                        // Create organization
                        try {
                            $orgId = $orgModel->create($orgName);
                            if ($orgId) {
                                $results['created']++;
                                $results['created_orgs'][] = [
                                    'id' => $orgId,
                                    'name' => $orgName
                                ];
                                $existingNames[] = $lowerName; // Add to existing list to prevent duplicates in same batch
                            } else {
                                $results['errors'][] = "Failed to create: {$orgName}";
                            }
                        } catch (\Exception $e) {
                            $results['errors'][] = "Error creating '{$orgName}': " . $e->getMessage();
                        }
                    }
                    
                    // Commit transaction
                    $db->commit();
                    
                    // Log activity
                    $activityLog->log('admin', $adminId, $adminUsername, 'bulk_create_organisations', [
                        'total' => $results['total'],
                        'created' => $results['created'],
                        'skipped' => $results['skipped'],
                        'errors' => count($results['errors'])
                    ]);
                    
                    // Build success message
                    if ($results['created'] > 0) {
                        $message = "Successfully created {$results['created']} organization(s).";
                        if ($results['skipped'] > 0) {
                            $message .= " {$results['skipped']} organization(s) were skipped (duplicates or already exist).";
                        }
                    } else {
                        $error = "No organizations were created. All organizations may already exist or there were errors.";
                    }
                    
                } catch (\Exception $e) {
                    // Only rollback if transaction is active
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    ErrorHandler::log("Bulk create organizations error: " . $e->getMessage(), 'ERROR');
                    $error = "Error: " . $e->getMessage();
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPI Admin - Bulk Create Organizations</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #EFECE3;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #8FABD4;
        }
        h1 { 
            color: #000000; 
            margin-bottom: 5px;
            font-size: 28px;
        }
        .subtitle { color: #666; }
        .admin-badge {
            display: inline-block;
            background: linear-gradient(135deg, #4A70A9 0%, #8FABD4 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
            box-shadow: 0 2px 8px rgba(74, 112, 169, 0.3);
        }
        .nav {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #8FABD4;
            padding-bottom: 10px;
        }
        .nav a {
            padding: 10px 20px;
            text-decoration: none;
            color: rgba(0, 0, 0, 0.6);
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
            font-weight: 500;
        }
        .nav a:hover, .nav a.active {
            color: #4A70A9;
            border-bottom-color: #4A70A9;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #4A70A9 0%, #8FABD4 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(74, 112, 169, 0.3);
        }
        .hero-section h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .hero-section p {
            opacity: 0.95;
            line-height: 1.6;
        }
        
        /* Instructions Card */
        .instructions-card {
            background: rgba(143, 171, 212, 0.2);
            border-left: 4px solid #4A70A9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .instructions-card h3 {
            color: #000000;
            margin-bottom: 12px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .instructions-card ul {
            margin-left: 20px;
            line-height: 1.8;
        }
        .instructions-card li {
            margin-bottom: 8px;
            color: rgba(0, 0, 0, 0.7);
        }
        .instructions-card code {
            background: #EFECE3;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            border: 1px solid #ddd;
        }
        
        /* Input Section */
        .input-section {
            background: rgba(143, 171, 212, 0.1);
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            transition: all 0.3s;
        }
        .input-section:hover {
            border-color: #4A70A9;
            background: rgba(143, 171, 212, 0.15);
        }
        .input-section.focused {
            border-color: #4A70A9;
            border-style: solid;
            background: #EFECE3;
            box-shadow: 0 0 0 3px rgba(74, 112, 169, 0.1);
        }
        .form-group { margin-bottom: 20px; }
        label { 
            display: block; 
            margin-bottom: 10px; 
            font-weight: 600;
            color: #000000;
            font-size: 16px;
        }
        .organizations-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 600px;
            overflow-y: auto;
            padding: 10px;
            background: rgba(143, 171, 212, 0.1);
            border-radius: 8px;
            border: 2px solid #e0e0e0;
        }
        .organization-input-row {
            display: flex;
            gap: 10px;
            align-items: center;
            background: #EFECE3;
            padding: 12px 15px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
            animation: slideInRow 0.3s ease-out;
        }
        @keyframes slideInRow {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        .organization-input-row:hover {
            border-color: #4A70A9;
            box-shadow: 0 2px 8px rgba(74, 112, 169, 0.1);
        }
        .organization-input-row.focused {
            border-color: #4A70A9;
            box-shadow: 0 0 0 3px rgba(74, 112, 169, 0.1);
        }
        .input-number {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #4A70A9 0%, #8FABD4 100%);
            color: white;
            border-radius: 50%;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }
        .organization-input-row input {
            flex: 1;
            padding: 12px 15px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            background: rgba(143, 171, 212, 0.2);
            transition: all 0.3s;
        }
        .organization-input-row input:focus {
            outline: none;
            background: #EFECE3;
            box-shadow: 0 0 0 2px rgba(74, 112, 169, 0.2);
        }
        .remove-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            width: 35px;
            height: 35px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s;
            flex-shrink: 0;
        }
        .remove-btn:hover {
            background: #c82333;
            transform: scale(1.1);
        }
        .remove-btn:active {
            transform: scale(0.95);
        }
        .example-btn {
            background: #e9ecef;
            color: #495057;
            border: 1px solid #dee2e6;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        .example-btn:hover {
            background: #dee2e6;
            border-color: #adb5bd;
            transform: translateY(-1px);
        }
        .empty-state-input {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .empty-state-input-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        .field-counter {
            text-align: center;
            margin-top: 15px;
            padding: 10px;
            background: rgba(143, 171, 212, 0.2);
            border-radius: 6px;
            color: rgba(0, 0, 0, 0.6);
            font-size: 14px;
        }
        .field-counter strong {
            color: #4A70A9;
            font-size: 16px;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        .btn { 
            padding: 14px 32px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #4A70A9 0%, #8FABD4 100%);
            color: white;
        }
        .btn-primary:hover { 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(74, 112, 169, 0.4);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Messages */
        .message { 
            padding: 18px 20px; 
            margin-bottom: 20px; 
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            border-left: 4px solid #28a745;
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            border-left: 4px solid #dc3545;
        }
        .message-icon {
            font-size: 20px;
        }
        
        /* Results Section */
        .results-section {
            margin-top: 30px;
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #8FABD4;
        }
        .results-header h3 {
            color: #000000;
            font-size: 22px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: linear-gradient(135deg, #4A70A9 0%, #8FABD4 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(74, 112, 169, 0.3);
        }
        .stat-card.success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        .stat-card.danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-card .label {
            font-size: 14px;
            opacity: 0.9;
        }
        .results-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #8FABD4;
        }
        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            color: rgba(0, 0, 0, 0.6);
            transition: all 0.3s;
            position: relative;
            top: 2px;
        }
        .tab:hover {
            color: #4A70A9;
        }
        .tab.active {
            color: #4A70A9;
            border-bottom-color: #4A70A9;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .results-list {
            background: rgba(143, 171, 212, 0.2);
            border-radius: 8px;
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        .results-list-item {
            padding: 12px 15px;
            margin-bottom: 8px;
            background: #EFECE3;
            border-radius: 6px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }
        .results-list-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .results-list-item.success {
            border-left-color: #28a745;
        }
        .results-list-item.warning {
            border-left-color: #ffc107;
        }
        .results-list-item.danger {
            border-left-color: #dc3545;
        }
        .item-icon {
            font-size: 18px;
        }
        .item-content {
            flex: 1;
        }
        .item-name {
            font-weight: 600;
            color: #000000;
        }
        .item-meta {
            font-size: 12px;
            color: rgba(0, 0, 0, 0.6);
            margin-top: 2px;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* Help Text */
        .help-text { 
            font-size: 13px; 
            color: rgba(0, 0, 0, 0.6); 
            margin-top: 8px;
            line-height: 1.6;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            .action-buttons {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Bulk Create Organizations <span class="admin-badge">ADMIN</span></h1>
                <p class="subtitle">Welcome, <?= htmlspecialchars($adminFullName) ?></p>
            </div>
            <div style="text-align: right;">
                <p style="color: #666; margin-bottom: 5px;">Logged in as: <strong><?= htmlspecialchars($adminUsername) ?></strong></p>
                <a href="logout.php" style="color: #dc3545; text-decoration: none; font-size: 14px;">Logout</a>
            </div>
        </div>
        
        <nav class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="create-account.php">Create Account</a>
            <a href="bulk-create-organisations.php" class="active">Bulk Create Organizations</a>
            <a href="organisations.php">Organizations</a>
        </nav>
        
        <div class="hero-section">
            <h2>🚀 Bulk Create Organizations</h2>
            <p>Create multiple organizations at once by entering their names, one per line. Perfect for quickly setting up your system with multiple organizations.</p>
        </div>
        
        <div class="instructions-card">
            <h3>📋 How it works</h3>
            <ul>
                <li><strong>One field per organization:</strong> Each input field represents one organization name</li>
                <li><strong>Add more fields:</strong> Click "Add One More Field" or use "Add 5 More" / "Add 10 More" buttons</li>
                <li><strong>Remove fields:</strong> Click the × button to remove any field you don't need</li>
                <li><strong>Empty fields ignored:</strong> Fields left empty will be automatically skipped</li>
                <li><strong>Smart duplicate detection:</strong> Duplicates and existing organizations will be skipped</li>
                <li><strong>Safe transaction:</strong> All organizations are created together - if one fails, none are created</li>
                <li><strong>Instant feedback:</strong> See exactly which organizations were created, skipped, or had errors</li>
            </ul>
        </div>
        
        <?php if ($message): ?>
            <div class="message success">
                <span class="message-icon">✅</span>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error">
                <span class="message-icon">❌</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="bulkCreateForm">
            <?= CsrfToken::field() ?>
            
            <div class="input-section" id="inputSection">
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <label style="margin: 0;">
                            Organization Names
                            <span style="color: #dc3545;">*</span>
                        </label>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="example-btn" onclick="addMoreFields(5)">
                                ➕ Add 5 More
                            </button>
                            <button type="button" class="example-btn" onclick="addMoreFields(10)">
                                ➕ Add 10 More
                            </button>
                        </div>
                    </div>
                    
                    <div id="organizationsContainer" class="organizations-container">
                        <!-- Input fields will be added here dynamically -->
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="addSingleField()" style="width: auto; padding: 10px 20px;">
                            ➕ Add One More Field
                        </button>
                    </div>
                    
                    <p class="help-text" style="margin-top: 15px;">
                        💡 <strong>Tip:</strong> Fill in as many organization names as you need. Empty fields will be automatically ignored.
                    </p>
                </div>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <span>✨</span>
                    <span>Create Organizations</span>
                </button>
                <button type="button" class="btn btn-secondary" onclick="clearAllFields()">
                    <span>🗑️</span>
                    <span>Clear All</span>
                </button>
            </div>
        </form>
        
        <?php if ($results): ?>
            <div class="results-section">
                <div class="results-header">
                    <h3>📊 Results</h3>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?= $results['total'] ?></div>
                        <div class="label">Total Entered</div>
                    </div>
                    <div class="stat-card success">
                        <div class="number"><?= $results['created'] ?></div>
                        <div class="label">Created</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="number"><?= $results['skipped'] ?></div>
                        <div class="label">Skipped</div>
                    </div>
                    <?php if (count($results['errors']) > 0): ?>
                    <div class="stat-card danger">
                        <div class="number"><?= count($results['errors']) ?></div>
                        <div class="label">Errors</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="results-tabs">
                    <?php if (!empty($results['created_orgs'])): ?>
                    <button class="tab active" onclick="switchTab('created')">
                        ✅ Created (<?= count($results['created_orgs']) ?>)
                    </button>
                    <?php endif; ?>
                    <?php if (!empty($results['skipped_orgs'])): ?>
                    <button class="tab <?= empty($results['created_orgs']) ? 'active' : '' ?>" onclick="switchTab('skipped')">
                        ⚠️ Skipped (<?= count($results['skipped_orgs']) ?>)
                    </button>
                    <?php endif; ?>
                    <?php if (!empty($results['errors'])): ?>
                    <button class="tab <?= empty($results['created_orgs']) && empty($results['skipped_orgs']) ? 'active' : '' ?>" onclick="switchTab('errors')">
                        ❌ Errors (<?= count($results['errors']) ?>)
                    </button>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($results['created_orgs'])): ?>
                <div id="tab-created" class="tab-content <?= !empty($results['created_orgs']) ? 'active' : '' ?>">
                    <div class="results-list">
                        <?php foreach ($results['created_orgs'] as $org): ?>
                            <div class="results-list-item success">
                                <span class="item-icon">✅</span>
                                <div class="item-content">
                                    <div class="item-name"><?= htmlspecialchars($org['name']) ?></div>
                                    <div class="item-meta">Organization ID: <?= $org['id'] ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($results['skipped_orgs'])): ?>
                <div id="tab-skipped" class="tab-content <?= empty($results['created_orgs']) && !empty($results['skipped_orgs']) ? 'active' : '' ?>">
                    <div class="results-list">
                        <?php foreach ($results['skipped_orgs'] as $org): ?>
                            <div class="results-list-item warning">
                                <span class="item-icon">⚠️</span>
                                <div class="item-content">
                                    <div class="item-name"><?= htmlspecialchars($org['name']) ?></div>
                                    <div class="item-meta">Reason: <?= htmlspecialchars($org['reason']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($results['errors'])): ?>
                <div id="tab-errors" class="tab-content <?= empty($results['created_orgs']) && empty($results['skipped_orgs']) && !empty($results['errors']) ? 'active' : '' ?>">
                    <div class="results-list">
                        <?php foreach ($results['errors'] as $errorMsg): ?>
                            <div class="results-list-item danger">
                                <span class="item-icon">❌</span>
                                <div class="item-content">
                                    <div class="item-name"><?= htmlspecialchars($errorMsg) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        let fieldCount = 0;
        
        function addSingleField() {
            addField('');
        }
        
        function addMoreFields(count) {
            for (let i = 0; i < count; i++) {
                addField('');
            }
            // Focus on the last added field
            const container = document.getElementById('organizationsContainer');
            const lastInput = container.lastElementChild.querySelector('input');
            if (lastInput) {
                lastInput.focus();
            }
        }
        
        function addField(value = '') {
            fieldCount++;
            const container = document.getElementById('organizationsContainer');
            
            // Remove empty state if it exists
            const emptyState = container.querySelector('.empty-state-input');
            if (emptyState) {
                emptyState.remove();
            }
            
            const row = document.createElement('div');
            row.className = 'organization-input-row';
            row.innerHTML = `
                <div class="input-number">${fieldCount}</div>
                <input 
                    type="text" 
                    name="organisations[]" 
                    placeholder="Enter organization name..."
                    value="${value}"
                    onfocus="this.parentElement.classList.add('focused')"
                    onblur="this.parentElement.classList.remove('focused')"
                    oninput="updateFieldCounter()"
                >
                <button type="button" class="remove-btn" onclick="removeField(this)" title="Remove this field">
                    ×
                </button>
            `;
            
            container.appendChild(row);
            updateFieldCounter();
            
            // Auto-focus new field
            const input = row.querySelector('input');
            input.focus();
        }
        
        function removeField(button) {
            const row = button.parentElement;
            row.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => {
                row.remove();
                updateFieldCounter();
                updateFieldNumbers();
                
                // Show empty state if no fields left
                const container = document.getElementById('organizationsContainer');
                if (container.children.length === 0) {
                    showEmptyState();
                }
            }, 300);
        }
        
        function updateFieldNumbers() {
            const rows = document.querySelectorAll('.organization-input-row');
            rows.forEach((row, index) => {
                const number = row.querySelector('.input-number');
                number.textContent = index + 1;
            });
            fieldCount = rows.length;
        }
        
        function updateFieldCounter() {
            const inputs = document.querySelectorAll('input[name="organisations[]"]');
            const filledCount = Array.from(inputs).filter(input => input.value.trim() !== '').length;
            const totalCount = inputs.length;
            
            let counter = document.getElementById('fieldCounter');
            if (!counter) {
                counter = document.createElement('div');
                counter.id = 'fieldCounter';
                counter.className = 'field-counter';
                const container = document.getElementById('organizationsContainer');
                container.parentElement.insertBefore(counter, container.nextSibling);
            }
            
            counter.innerHTML = `<strong>${filledCount}</strong> of <strong>${totalCount}</strong> fields filled`;
        }
        
        function showEmptyState() {
            const container = document.getElementById('organizationsContainer');
            container.innerHTML = `
                <div class="empty-state-input">
                    <div class="empty-state-input-icon">📝</div>
                    <p>No organization fields yet. Click "Add One More Field" to get started!</p>
                </div>
            `;
        }
        
        function clearAllFields() {
            if (confirm('Are you sure you want to remove all organization fields?')) {
                const container = document.getElementById('organizationsContainer');
                container.innerHTML = '';
                fieldCount = 0;
                showEmptyState();
                updateFieldCounter();
            }
        }
        
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById('tab-' + tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add initial fields
            addMoreFields(3);
            
            // Add CSS for slideOut animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideOut {
                    from {
                        opacity: 1;
                        transform: translateX(0);
                    }
                    to {
                        opacity: 0;
                        transform: translateX(20px);
                    }
                }
            `;
            document.head.appendChild(style);
        });
        
        // Form submission
        document.getElementById('bulkCreateForm').addEventListener('submit', function(e) {
            const inputs = document.querySelectorAll('input[name="organisations[]"]');
            const filledInputs = Array.from(inputs).filter(input => input.value.trim() !== '');
            
            if (filledInputs.length === 0) {
                e.preventDefault();
                alert('Please enter at least one organization name.');
                if (inputs.length > 0) {
                    inputs[0].focus();
                } else {
                    addSingleField();
                }
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>⏳</span><span>Creating Organizations...</span>';
        });
    </script>
</body>
</html>

