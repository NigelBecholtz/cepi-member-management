<?php

require_once __DIR__ . '/auth-check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Utils/ErrorHandler.php';
require_once __DIR__ . '/../../src/Utils/CsrfToken.php';
require_once __DIR__ . '/../../src/Models/Organisation.php';
require_once __DIR__ . '/../../src/Models/Member.php';
require_once __DIR__ . '/../../src/Models/Auth.php';
require_once __DIR__ . '/../../src/Models/ActivityLog.php';

use Cepi\Utils\ErrorHandler;
use Cepi\Utils\CsrfToken;
use Cepi\Models\Organisation;
use Cepi\Models\Member;
use Cepi\Models\Auth;
use Cepi\Models\ActivityLog;

ErrorHandler::init();

$adminId = getLoggedInAdminId();
$adminUsername = getLoggedInAdminUsername();
$adminFullName = getLoggedInAdminFullName();

$orgModel = new Organisation();
$memberModel = new Member();
$authModel = new Auth();
$activityLog = new ActivityLog();

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfToken::validate($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Handle create organisation
        if (isset($_POST['action']) && $_POST['action'] === 'create_organisation') {
            $orgName = trim($_POST['organisation_name'] ?? '');
            
            // Validate input
            if (empty($orgName)) {
                $error = 'Organization name is required.';
            } elseif (strlen($orgName) > 255) {
                $error = 'Organization name must be 255 characters or less.';
            } elseif (!preg_match('/^[a-zA-Z0-9\s\-_\.]+$/', $orgName)) {
                $error = 'Organization name contains invalid characters.';
            } else {
                try {
                    // Check if organization already exists
                    $existing = $orgModel->getAll();
                    foreach ($existing as $existingOrg) {
                        if (strtolower(trim($existingOrg['organisation_name'])) === strtolower(trim($orgName))) {
                            $error = 'An organization with this name already exists.';
                            break;
                        }
                    }
                    
                    if (empty($error)) {
                        $orgId = $orgModel->create($orgName);
                        $activityLog->log('admin', $adminId, $adminUsername, 'create_organisation', [
                            'organisation_id' => $orgId,
                            'organisation_name' => $orgName
                        ]);
                        $message = 'Organization "' . htmlspecialchars($orgName) . '" created successfully.';
                    }
                } catch (Exception $e) {
                    ErrorHandler::log("Create organization error: " . $e->getMessage(), 'ERROR');
                    $error = 'Failed to create organization. Please try again.';
                }
            }
        }
        // Handle delete organisation
        elseif (isset($_POST['action']) && $_POST['action'] === 'delete_organisation') {
            $orgId = isset($_POST['organisation_id']) ? (int)$_POST['organisation_id'] : 0;
            
            if ($orgId <= 0) {
                $error = 'Invalid organization ID.';
            } else {
                try {
                    // Get organization info before deletion for logging
                    $org = $orgModel->getById($orgId);
                    if (!$org) {
                        $error = 'Organization not found.';
                    } else {
                        $orgName = $org['organisation_name'];
                        $totalMembers = $memberModel->countByOrganisation($orgId, false);
                        $hasAccount = $authModel->getByOrganisationId($orgId) !== false;
                        
                        // Delete the organization
                        $success = $orgModel->delete($orgId);
                        if ($success) {
                            $activityLog->log('admin', $adminId, $adminUsername, 'delete_organisation', [
                                'organisation_id' => $orgId,
                                'organisation_name' => $orgName,
                                'total_members' => $totalMembers,
                                'had_account' => $hasAccount
                            ]);
                            $message = 'Organization "' . htmlspecialchars($orgName) . '" deleted successfully.';
                        } else {
                            $error = 'Failed to delete organization.';
                        }
                    }
                } catch (Exception $e) {
                    ErrorHandler::log("Delete organization error: " . $e->getMessage(), 'ERROR');
                    $error = 'Failed to delete organization. Please try again.';
                }
            }
        }
    }
}

$organisations = $orgModel->getAll();

// Get statistics per organization
foreach ($organisations as &$org) {
    $org['total_members'] = $memberModel->countByOrganisation($org['organisation_id'], false);
    $org['active_members'] = $memberModel->countByOrganisation($org['organisation_id'], true);
    $org['has_account'] = $authModel->getByOrganisationId($org['organisation_id']) !== false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPI Admin - Organizations</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #EFECE3;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
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
        .orgs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .orgs-table th,
        .orgs-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #8FABD4;
        }
        .orgs-table th {
            background: rgba(143, 171, 212, 0.3);
            font-weight: 600;
            color: #000000;
        }
        .orgs-table tr:hover {
            background: rgba(143, 171, 212, 0.1);
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-yes {
            background: #28a745;
            color: white;
        }
        .badge-no {
            background: #dc3545;
            color: white;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        .message.success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
        }
        .message.error {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
        }
        .form-section {
            margin-bottom: 30px;
        }
        .form-section h2 {
            color: #000000;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: 600;
        }
        input:focus {
            outline: none;
            border-color: #4A70A9;
        }
        button:hover {
            background: #3a5a89 !important;
        }
        .btn-delete {
            padding: 6px 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.3s;
        }
        .btn-delete:hover {
            background: #c82333 !important;
        }
        .delete-form {
            margin: 0;
        }
        .btn-add {
            padding: 10px 20px;
            background: #4A70A9;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-add:hover {
            background: #3a5a89 !important;
        }
        .form-section {
            display: none;
        }
        .form-section.visible {
            display: block;
        }
        .btn-cancel {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s;
        }
        .btn-cancel:hover {
            background: #5a6268 !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Organizations <span class="admin-badge">ADMIN</span></h1>
                <p class="subtitle">Welcome, <?= htmlspecialchars($adminFullName) ?></p>
            </div>
            <div style="text-align: right;">
                <p style="color: rgba(0, 0, 0, 0.6); margin-bottom: 5px;">Logged in as: <strong><?= htmlspecialchars($adminUsername) ?></strong></p>
                <a href="logout.php" style="color: #dc3545; text-decoration: none; font-size: 14px;">Logout</a>
            </div>
        </div>
        
        <nav class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="create-account.php">Accounts</a>
            <a href="organisations.php" class="active">Organizations</a>
            <a href="api-keys.php">API Keys</a>
        </nav>
        
        <?php if ($message): ?>
            <div class="message success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <button type="button" class="btn-add" id="addOrgBtn">
            <span style="font-size: 18px; line-height: 1;">+</span>
            <span>Add New Organization</span>
        </button>
        
        <div class="form-section" id="addOrgForm">
            <h2>Add New Organization</h2>
            <form method="POST" id="createOrgForm" style="display: flex; gap: 10px; align-items: flex-end; margin-bottom: 30px;">
                <?= CsrfToken::field() ?>
                <input type="hidden" name="action" value="create_organisation">
                <div style="flex: 1;">
                    <label for="organisation_name" style="display: block; margin-bottom: 5px; color: rgba(0, 0, 0, 0.6); font-weight: 500;">Organization Name</label>
                    <input 
                        type="text" 
                        id="organisation_name" 
                        name="organisation_name" 
                        required 
                        maxlength="255"
                        pattern="[a-zA-Z0-9\s\-_\.]+"
                        style="width: 100%; padding: 10px; border: 1px solid #8FABD4; border-radius: 4px; font-size: 14px;"
                        placeholder="Enter organization name"
                    >
                </div>
                <div style="display: flex; gap: 10px; align-items: flex-end;">
                    <button type="submit" style="padding: 10px 20px; background: #4A70A9; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background 0.3s;">Add Organization</button>
                    <button type="button" class="btn-cancel" id="cancelBtn">Cancel</button>
                </div>
            </form>
        </div>
        
        <table class="orgs-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Organization Name</th>
                    <th>Total Members</th>
                    <th>Active Members</th>
                    <th>Has Account</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($organisations)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: rgba(0, 0, 0, 0.6);">
                            No organizations found
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($organisations as $org): ?>
                        <tr>
                            <td><?= $org['organisation_id'] ?></td>
                            <td><?= htmlspecialchars($org['organisation_name']) ?></td>
                            <td><?= $org['total_members'] ?></td>
                            <td><?= $org['active_members'] ?></td>
                            <td>
                                <span class="badge <?= $org['has_account'] ? 'badge-yes' : 'badge-no' ?>">
                                    <?= $org['has_account'] ? 'Yes' : 'No' ?>
                                </span>
                            </td>
                            <td><?= date('d-m-Y H:i', strtotime($org['created_at'])) ?></td>
                            <td>
                                <form method="POST" class="delete-form" style="display: inline;">
                                    <?= CsrfToken::field() ?>
                                    <input type="hidden" name="action" value="delete_organisation">
                                    <input type="hidden" name="organisation_id" value="<?= $org['organisation_id'] ?>">
                                    <button 
                                        type="submit" 
                                        class="btn-delete"
                                        data-org-name="<?= htmlspecialchars($org['organisation_name'], ENT_QUOTES) ?>"
                                        data-org-id="<?= $org['organisation_id'] ?>"
                                        data-total-members="<?= $org['total_members'] ?>"
                                        data-has-account="<?= $org['has_account'] ? 'yes' : 'no' ?>"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addOrgBtn = document.getElementById('addOrgBtn');
            const addOrgForm = document.getElementById('addOrgForm');
            const cancelBtn = document.getElementById('cancelBtn');
            const createOrgForm = document.getElementById('createOrgForm');
            const orgNameInput = document.getElementById('organisation_name');
            
            // Show form when + button is clicked
            addOrgBtn.addEventListener('click', function() {
                addOrgForm.classList.add('visible');
                addOrgBtn.style.display = 'none';
                orgNameInput.focus();
            });
            
            // Hide form when Cancel is clicked
            cancelBtn.addEventListener('click', function() {
                addOrgForm.classList.remove('visible');
                addOrgBtn.style.display = 'inline-flex';
                createOrgForm.reset();
            });
            
            // Hide form after successful submission (if there's a success message)
            <?php if ($message): ?>
                addOrgForm.classList.remove('visible');
                addOrgBtn.style.display = 'inline-flex';
                createOrgForm.reset();
            <?php endif; ?>
            
            // Delete confirmation dialogs
            const deleteForms = document.querySelectorAll('.delete-form');
            
            deleteForms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const button = form.querySelector('.btn-delete');
                    const orgName = button.getAttribute('data-org-name');
                    const orgId = button.getAttribute('data-org-id');
                    const totalMembers = parseInt(button.getAttribute('data-total-members')) || 0;
                    const hasAccount = button.getAttribute('data-has-account') === 'yes';
                    
                    // Build warning message
                    let warningMessage = 'Are you sure you want to delete the organization "' + orgName + '"?\n\n';
                    
                    if (totalMembers > 0 || hasAccount) {
                        warningMessage += '⚠️ WARNING:\n';
                        if (totalMembers > 0) {
                            warningMessage += '• This organization has ' + totalMembers + ' member(s)\n';
                        }
                        if (hasAccount) {
                            warningMessage += '• This organization has an active account\n';
                        }
                        warningMessage += '\nAll related data (members, accounts, etc.) will also be deleted!\n\n';
                    }
                    
                    warningMessage += 'This action cannot be undone.';
                    
                    if (confirm(warningMessage)) {
                        form.submit();
                    }
                });
            });
        });
    </script>
</body>
</html>

