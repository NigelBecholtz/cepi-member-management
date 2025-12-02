<?php

require_once __DIR__ . '/auth-check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Utils/ErrorHandler.php';
require_once __DIR__ . '/../../src/Utils/CsrfToken.php';
require_once __DIR__ . '/../../src/Models/Organisation.php';
require_once __DIR__ . '/../../src/Models/Auth.php';
require_once __DIR__ . '/../../src/Models/ActivityLog.php';

use Cepi\Utils\ErrorHandler;
use Cepi\Utils\CsrfToken;
use Cepi\Models\Organisation;
use Cepi\Models\Auth;
use Cepi\Models\ActivityLog;

ErrorHandler::init();

$adminId = getLoggedInAdminId();
$adminUsername = getLoggedInAdminUsername();
$adminFullName = getLoggedInAdminFullName();

$orgModel = new Organisation();
$authModel = new Auth();
$activityLog = new ActivityLog();
$organisations = $orgModel->getAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!CsrfToken::validate($csrfToken)) {
        $error = "Invalid security token. Please try again.";
    } else {
        $action = $_POST['action'] ?? 'existing';
        $organisationId = (int)($_POST['organisation_id'] ?? 0);
        $newOrganisationName = trim($_POST['new_organisation_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        
        // Validation
        if (empty($username) || empty($password)) {
            $error = "Please enter username and password";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long";
        } elseif ($action === 'existing' && $organisationId <= 0) {
            $error = "Please select an organization";
        } elseif ($action === 'new' && empty($newOrganisationName)) {
            $error = "Please enter an organization name";
        } else {
            try {
                // If new organization, create it first
                if ($action === 'new') {
                    // Check if organization name already exists
                    $existingOrgs = $orgModel->getAll();
                    foreach ($existingOrgs as $org) {
                        if (strcasecmp($org['organisation_name'], $newOrganisationName) === 0) {
                            $error = "An organization with this name already exists. Please select it from the list.";
                            break;
                        }
                    }
                    
                    if (empty($error)) {
                        $organisationId = $orgModel->create($newOrganisationName);
                        if (!$organisationId) {
                            $error = "Could not create organization";
                        }
                    }
                }
                
                // If we have an organization ID, create account
                if (empty($error) && $organisationId > 0) {
                    // Check if this organization already has an account
                    $existing = $authModel->getByOrganisationId($organisationId);
                    if ($existing) {
                        $error = "This organization already has an account. Each organization can only have 1 account.";
                    } else {
                        // Check if username already exists
                        $db = Database::getInstance()->getConnection();
                        $checkStmt = $db->prepare("SELECT auth_id FROM organisation_auth WHERE username = :username");
                        $checkStmt->execute([':username' => $username]);
                        if ($checkStmt->fetch()) {
                            $error = "This username already exists. Please choose another username.";
                        } else {
                            $success = $authModel->createAuth($organisationId, $username, $password, $email);
                            if ($success) {
                                $orgName = $action === 'new' ? $newOrganisationName : $orgModel->getById($organisationId)['organisation_name'];
                                $message = "Organization and account successfully created!<br>";
                                $message .= "Organization: " . htmlspecialchars($orgName) . "<br>";
                                $message .= "Username: " . htmlspecialchars($username);
                                
                                // Log activity
                                $activityLog->log('admin', $adminId, $adminUsername, 'create_account', [
                                    'organisation_id' => $organisationId,
                                    'organisation_name' => $orgName,
                                    'username' => $username,
                                    'action' => $action === 'new' ? 'new_organisation' : 'existing_organisation'
                                ]);
                            } else {
                                $error = "Could not create account";
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                ErrorHandler::log("Create account error: " . $e->getMessage(), 'ERROR');
                $error = "Error: " . $e->getMessage();
            }
        }
        
        // Refresh organizations list after creation
        $organisations = $orgModel->getAll();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPI Admin - Account Aanmaken</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #EFECE3;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #EFECE3;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #8FABD4;
        }
        h1 { color: #000000; margin-bottom: 5px; }
        .subtitle { color: rgba(0, 0, 0, 0.6); }
        .admin-badge {
            display: inline-block;
            background: #dc3545;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
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
        }
        .nav a:hover, .nav a.active {
            color: #4A70A9;
            border-bottom-color: #4A70A9;
        }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #000000; }
        select, input { width: 100%; padding: 12px; border: 2px solid #8FABD4; border-radius: 6px; background: white; }
        .btn { background: #4A70A9; color: white; padding: 12px 30px; border: none; border-radius: 6px; cursor: pointer; width: 100%; transition: background 0.3s; }
        .btn:hover { background: #8FABD4; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .success { background: rgba(40, 167, 69, 0.1); color: #155724; }
        .error { background: rgba(220, 53, 69, 0.1); color: #721c24; }
        select:focus, input:focus { outline: none; border-color: #4A70A9; }
        .help-text { font-size: 12px; color: rgba(0, 0, 0, 0.6); margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Create Account <span class="admin-badge">ADMIN</span></h1>
                <p class="subtitle">Welcome, <?= htmlspecialchars($adminFullName) ?></p>
            </div>
            <div style="text-align: right;">
                <p style="color: rgba(0, 0, 0, 0.6); margin-bottom: 5px;">Logged in as: <strong><?= htmlspecialchars($adminUsername) ?></strong></p>
                <a href="logout.php" style="color: #dc3545; text-decoration: none; font-size: 14px;">Logout</a>
            </div>
        </div>
        
        <nav class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="create-account.php" class="active">Create Account</a>
            <a href="bulk-create-organisations.php">Bulk Create Organizations</a>
            <a href="organisations.php">Organizations</a>
        </nav>
        
        <?php if ($message): ?>
            <div class="message success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" id="accountForm">
            <?= CsrfToken::field() ?>
            
            <div class="form-group">
                <label>Action:</label>
                <select name="action" id="actionSelect" required onchange="toggleOrganisationFields()">
                    <option value="existing">Select existing organization</option>
                    <option value="new">Create new organization</option>
                </select>
            </div>
            
            <div class="form-group" id="existingOrgGroup">
                <label>Select Organization:</label>
                <select name="organisation_id" id="organisation_id">
                    <option value="">-- Select organization --</option>
                    <?php foreach ($organisations as $org): ?>
                        <?php
                        $hasAccount = $authModel->getByOrganisationId($org['organisation_id']);
                        ?>
                        <option value="<?= $org['organisation_id'] ?>" <?= $hasAccount ? 'disabled style="color: #999;"' : '' ?>>
                            <?= htmlspecialchars($org['organisation_name']) ?>
                            <?= $hasAccount ? ' (has account)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="help-text">
                    Organizations with an account are grayed out and cannot be selected.
                </p>
            </div>
            
            <div class="form-group" id="newOrgGroup" style="display: none;">
                <label>New Organization Name:</label>
                <input type="text" name="new_organisation_name" id="new_organisation_name" placeholder="e.g.: Organization XYZ">
                <p class="help-text">
                    Enter the name of the new organization.
                </p>
            </div>
            
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" required>
            </div>
            
            <div class="form-group">
                <label>Password (min. 6 characters):</label>
                <input type="password" name="password" required minlength="6">
            </div>
            
            <div class="form-group">
                <label>Email (optional):</label>
                <input type="email" name="email">
            </div>
            
            <button type="submit" class="btn">Create Account</button>
        </form>
    </div>
    
    <script>
        function toggleOrganisationFields() {
            const action = document.getElementById('actionSelect').value;
            const existingGroup = document.getElementById('existingOrgGroup');
            const newGroup = document.getElementById('newOrgGroup');
            const orgSelect = document.getElementById('organisation_id');
            const newOrgInput = document.getElementById('new_organisation_name');
            
            if (action === 'new') {
                existingGroup.style.display = 'none';
                newGroup.style.display = 'block';
                orgSelect.removeAttribute('required');
                newOrgInput.setAttribute('required', 'required');
            } else {
                existingGroup.style.display = 'block';
                newGroup.style.display = 'none';
                orgSelect.setAttribute('required', 'required');
                newOrgInput.removeAttribute('required');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            toggleOrganisationFields();
        });
    </script>
</body>
</html>

