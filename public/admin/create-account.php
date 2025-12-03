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

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfToken::validate($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Handle create account
        if (isset($_POST['action']) && $_POST['action'] === 'create_account') {
            $organisationId = isset($_POST['organisation_id']) ? (int)$_POST['organisation_id'] : 0;
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';
            $email = trim($_POST['email'] ?? '');
            
            // Validate input
            if ($organisationId <= 0) {
                $error = 'Please select a valid organization.';
            } elseif (empty($username)) {
                $error = 'Username is required.';
            } elseif (strlen($username) > 100) {
                $error = 'Username must be 100 characters or less.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                $error = 'Username contains invalid characters. Use only letters, numbers, and underscores.';
            } elseif (empty($password)) {
                $error = 'Password is required.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters long.';
            } elseif (strlen($password) > 255) {
                $error = 'Password must be 255 characters or less.';
            } elseif ($password !== $passwordConfirm) {
                $error = 'Passwords do not match.';
            } elseif (!empty($email) && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255)) {
                $error = 'Please enter a valid email address.';
            } else {
                try {
                    // Check if organization already has an account
                    $existing = $authModel->getByOrganisationId($organisationId);
                    if ($existing) {
                        $error = 'This organization already has an account.';
                    } else {
                        // Check if username already exists
                        $db = Database::getInstance()->getConnection();
                        $checkStmt = $db->prepare("SELECT auth_id FROM organisation_auth WHERE username = :username AND is_active = TRUE");
                        $checkStmt->execute([':username' => $username]);
                        if ($checkStmt->fetch()) {
                            $error = 'This username already exists. Please choose another username.';
                        } else {
                            $success = $authModel->createAuth($organisationId, $username, $password, $email ?: null);
                            if ($success) {
                                $org = $orgModel->getById($organisationId);
                                $orgName = $org ? $org['organisation_name'] : 'Unknown';
                                
                                $activityLog->log('admin', $adminId, $adminUsername, 'create_account', [
                                    'organisation_id' => $organisationId,
                                    'organisation_name' => $orgName,
                                    'username' => $username
                                ]);
                                $message = 'Account created successfully for organization "' . htmlspecialchars($orgName) . '".';
                            } else {
                                $error = 'Failed to create account.';
                            }
                        }
                    }
                } catch (Exception $e) {
                    ErrorHandler::log("Create account error: " . $e->getMessage(), 'ERROR');
                    $error = 'Failed to create account. Please try again.';
                }
            }
        }
        // Handle update account
        elseif (isset($_POST['action']) && $_POST['action'] === 'update_account') {
            $authId = isset($_POST['auth_id']) ? (int)$_POST['auth_id'] : 0;
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';
            
            if ($authId <= 0) {
                $error = 'Invalid account ID.';
            } elseif (empty($username)) {
                $error = 'Username is required.';
            } elseif (strlen($username) > 100) {
                $error = 'Username must be 100 characters or less.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                $error = 'Username contains invalid characters. Use only letters, numbers, and underscores.';
            } elseif (!empty($email) && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255)) {
                $error = 'Please enter a valid email address.';
            } elseif (!empty($password) && (strlen($password) < 8 || strlen($password) > 255)) {
                $error = 'Password must be between 8 and 255 characters.';
            } elseif (!empty($password) && $password !== $passwordConfirm) {
                $error = 'Passwords do not match.';
            } else {
                try {
                    // Check if account exists
                    $account = $authModel->getById($authId);
                    if (!$account) {
                        $error = 'Account not found.';
                    } else {
                        // Check if username is already taken by another account
                        $db = Database::getInstance()->getConnection();
                        $checkStmt = $db->prepare("SELECT auth_id FROM organisation_auth WHERE username = :username AND auth_id != :auth_id AND is_active = TRUE");
                        $checkStmt->execute([':username' => $username, ':auth_id' => $authId]);
                        if ($checkStmt->fetch()) {
                            $error = 'This username is already taken by another account.';
                        } else {
                            $success = $authModel->update($authId, $username, $email ?: null, $password ?: null);
                            if ($success) {
                                $activityLog->log('admin', $adminId, $adminUsername, 'update_account', [
                                    'auth_id' => $authId,
                                    'organisation_id' => $account['organisation_id'],
                                    'organisation_name' => $account['organisation_name'],
                                    'username' => $username
                                ]);
                                $message = 'Account updated successfully.';
                            } else {
                                $error = 'Failed to update account.';
                            }
                        }
                    }
                } catch (Exception $e) {
                    ErrorHandler::log("Update account error: " . $e->getMessage(), 'ERROR');
                    $error = 'Failed to update account. Please try again.';
                }
            }
        }
        // Handle delete account
        elseif (isset($_POST['action']) && $_POST['action'] === 'delete_account') {
            $authId = isset($_POST['auth_id']) ? (int)$_POST['auth_id'] : 0;
            
            if ($authId <= 0) {
                $error = 'Invalid account ID.';
            } else {
                try {
                    $account = $authModel->getById($authId);
                    if (!$account) {
                        $error = 'Account not found.';
                    } else {
                        $success = $authModel->delete($authId);
                        if ($success) {
                            $activityLog->log('admin', $adminId, $adminUsername, 'delete_account', [
                                'auth_id' => $authId,
                                'organisation_id' => $account['organisation_id'],
                                'organisation_name' => $account['organisation_name'],
                                'username' => $account['username']
                            ]);
                            $message = 'Account deleted successfully.';
                        } else {
                            $error = 'Failed to delete account.';
                        }
                    }
                } catch (Exception $e) {
                    ErrorHandler::log("Delete account error: " . $e->getMessage(), 'ERROR');
                    $error = 'Failed to delete account. Please try again.';
                }
            }
        }
    }
}

$accounts = $authModel->getAll();
$organisations = $orgModel->getAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPI Admin - Accounts</title>
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
        .accounts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .accounts-table th,
        .accounts-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #8FABD4;
        }
        .accounts-table th {
            background: rgba(143, 171, 212, 0.3);
            font-weight: 600;
            color: #000000;
        }
        .accounts-table tr:hover {
            background: rgba(143, 171, 212, 0.1);
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
            display: none;
            margin-bottom: 30px;
        }
        .form-section.visible {
            display: block;
        }
        .form-section h2 {
            color: #000000;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: rgba(0, 0, 0, 0.6);
            font-weight: 500;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #8FABD4;
            border-radius: 4px;
            font-size: 14px;
        }
        .password-wrapper {
            position: relative;
        }
        .password-wrapper input {
            padding-right: 40px;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: rgba(0, 0, 0, 0.6);
            font-size: 13px;
            font-weight: 500;
            padding: 5px 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .password-toggle:hover {
            color: #4A70A9;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #4A70A9;
        }
        .password-mismatch {
            border-color: #dc3545 !important;
        }
        .password-error {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        .password-error.show {
            display: block;
        }
        .btn {
            padding: 10px 20px;
            background: #4A70A9;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #3a5a89 !important;
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
        .btn-edit {
            padding: 6px 12px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.3s;
            margin-right: 5px;
        }
        .btn-edit:hover {
            background: #218838 !important;
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
        .btn-group {
            display: flex;
            gap: 10px;
        }
        .delete-form, .edit-form {
            display: inline;
            margin: 0;
        }
        .edit-form-section {
            display: none;
        }
        .edit-form-section.visible {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Accounts <span class="admin-badge">ADMIN</span></h1>
                <p class="subtitle">Welcome, <?= htmlspecialchars($adminFullName) ?></p>
            </div>
            <div style="text-align: right;">
                <p style="color: rgba(0, 0, 0, 0.6); margin-bottom: 5px;">Logged in as: <strong><?= htmlspecialchars($adminUsername) ?></strong></p>
                <a href="logout.php" style="color: #dc3545; text-decoration: none; font-size: 14px;">Logout</a>
            </div>
        </div>
        
        <nav class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="create-account.php" class="active">Accounts</a>
            <a href="organisations.php">Organizations</a>
            <a href="api-keys.php">API Keys</a>
        </nav>
        
        <?php if ($message): ?>
            <div class="message success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <button type="button" class="btn-add" id="addAccountBtn">
            <span style="font-size: 18px; line-height: 1;">+</span>
            <span>Add New Account</span>
        </button>
        
        <div class="form-section" id="addAccountForm">
            <h2>Add New Account</h2>
            <form method="POST" id="createAccountForm">
                <?= CsrfToken::field() ?>
                <input type="hidden" name="action" value="create_account">
                <div class="form-group">
                    <label for="organisation_id">Organization *</label>
                    <select name="organisation_id" id="organisation_id" required>
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
                </div>
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" required maxlength="100" pattern="[a-zA-Z0-9_]+" placeholder="Enter username">
                </div>
                <div class="form-group">
                    <label for="password">Password *</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required minlength="8" maxlength="255" placeholder="Enter password (min. 8 characters)">
                        <button type="button" class="password-toggle" onclick="togglePassword('password', 'toggle-password')" tabindex="-1">
                            <span id="toggle-password">Show</span>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Repeat Password *</label>
                    <div class="password-wrapper">
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="8" maxlength="255" placeholder="Repeat password" oninput="checkPasswordMatch()">
                        <button type="button" class="password-toggle" onclick="togglePassword('password_confirm', 'toggle-password-confirm')" tabindex="-1">
                            <span id="toggle-password-confirm">Show</span>
                        </button>
                    </div>
                    <div class="password-error" id="password-error">Passwords do not match</div>
                </div>
                <div class="form-group">
                    <label for="email">Email (optional)</label>
                    <input type="email" id="email" name="email" maxlength="255" placeholder="Enter email address">
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn">Add Account</button>
                    <button type="button" class="btn-cancel" id="cancelAddBtn">Cancel</button>
                </div>
            </form>
        </div>
        
        <table class="accounts-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Organization</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($accounts)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: rgba(0, 0, 0, 0.6);">
                            No accounts found
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td><?= $account['auth_id'] ?></td>
                            <td><?= htmlspecialchars($account['organisation_name']) ?></td>
                            <td><?= htmlspecialchars($account['username']) ?></td>
                            <td><?= htmlspecialchars($account['email'] ?? '-') ?></td>
                            <td><?= $account['last_login'] ? date('d-m-Y H:i', strtotime($account['last_login'])) : '-' ?></td>
                            <td><?= date('d-m-Y H:i', strtotime($account['created_at'])) ?></td>
                            <td>
                                <button type="button" class="btn-edit" onclick="showEditForm(<?= $account['auth_id'] ?>, '<?= htmlspecialchars($account['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($account['email'] ?? '', ENT_QUOTES) ?>')">Edit</button>
                                <form method="POST" class="delete-form">
                                    <?= CsrfToken::field() ?>
                                    <input type="hidden" name="action" value="delete_account">
                                    <input type="hidden" name="auth_id" value="<?= $account['auth_id'] ?>">
                                    <button type="submit" class="btn-delete" data-username="<?= htmlspecialchars($account['username'], ENT_QUOTES) ?>" data-org-name="<?= htmlspecialchars($account['organisation_name'], ENT_QUOTES) ?>">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <tr class="edit-form-section" id="edit-form-<?= $account['auth_id'] ?>">
                            <td colspan="7" style="background: rgba(143, 171, 212, 0.05); padding: 20px;">
                                <h3 style="margin-bottom: 15px; color: #000000;">Edit Account</h3>
                                <form method="POST" id="edit-form-<?= $account['auth_id'] ?>-form">
                                    <?= CsrfToken::field() ?>
                                    <input type="hidden" name="action" value="update_account">
                                    <input type="hidden" name="auth_id" value="<?= $account['auth_id'] ?>">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                        <div class="form-group">
                                            <label>Username *</label>
                                            <input type="text" name="username" value="<?= htmlspecialchars($account['username']) ?>" required maxlength="100" pattern="[a-zA-Z0-9_]+">
                                        </div>
                                        <div class="form-group">
                                            <label>Email (optional)</label>
                                            <input type="email" name="email" value="<?= htmlspecialchars($account['email'] ?? '') ?>" maxlength="255">
                                        </div>
                                    </div>
                                    <div class="form-group" style="margin-bottom: 15px;">
                                        <label>New Password (leave empty to keep current password)</label>
                                        <div class="password-wrapper">
                                            <input type="password" name="password" id="edit-password-<?= $account['auth_id'] ?>" minlength="8" maxlength="255" placeholder="Enter new password" oninput="checkPasswordMatchEdit(<?= $account['auth_id'] ?>)">
                                            <button type="button" class="password-toggle" onclick="togglePassword('edit-password-<?= $account['auth_id'] ?>', 'toggle-edit-password-<?= $account['auth_id'] ?>')" tabindex="-1">
                                                <span id="toggle-edit-password-<?= $account['auth_id'] ?>">Show</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="form-group" style="margin-bottom: 15px;">
                                        <label>Repeat New Password</label>
                                        <div class="password-wrapper">
                                            <input type="password" name="password_confirm" id="edit-password-confirm-<?= $account['auth_id'] ?>" minlength="8" maxlength="255" placeholder="Repeat new password" oninput="checkPasswordMatchEdit(<?= $account['auth_id'] ?>)">
                                            <button type="button" class="password-toggle" onclick="togglePassword('edit-password-confirm-<?= $account['auth_id'] ?>', 'toggle-edit-password-confirm-<?= $account['auth_id'] ?>')" tabindex="-1">
                                                <span id="toggle-edit-password-confirm-<?= $account['auth_id'] ?>">Show</span>
                                            </button>
                                        </div>
                                        <div class="password-error" id="password-error-edit-<?= $account['auth_id'] ?>">Passwords do not match</div>
                                    </div>
                                    <div class="btn-group">
                                        <button type="submit" class="btn">Update Account</button>
                                        <button type="button" class="btn-cancel" onclick="hideEditForm(<?= $account['auth_id'] ?>)">Cancel</button>
                                    </div>
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
            const addAccountBtn = document.getElementById('addAccountBtn');
            const addAccountForm = document.getElementById('addAccountForm');
            const cancelAddBtn = document.getElementById('cancelAddBtn');
            const createAccountForm = document.getElementById('createAccountForm');
            
            // Show form when + button is clicked
            addAccountBtn.addEventListener('click', function() {
                addAccountForm.classList.add('visible');
                addAccountBtn.style.display = 'none';
                document.getElementById('organisation_id').focus();
            });
            
            // Hide form when Cancel is clicked
            cancelAddBtn.addEventListener('click', function() {
                addAccountForm.classList.remove('visible');
                addAccountBtn.style.display = 'inline-flex';
                createAccountForm.reset();
            });
            
            // Hide form after successful submission
            <?php if ($message): ?>
                addAccountForm.classList.remove('visible');
                addAccountBtn.style.display = 'inline-flex';
                createAccountForm.reset();
            <?php endif; ?>
            
            // Password match validation on form submit
            createAccountForm.addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const passwordConfirm = document.getElementById('password_confirm').value;
                
                if (password !== passwordConfirm) {
                    e.preventDefault();
                    checkPasswordMatch();
                    document.getElementById('password-error').classList.add('show');
                    return false;
                }
            });
            
            // Edit form password validation
            document.querySelectorAll('[id^="edit-form-"]').forEach(function(editForm) {
                const form = editForm.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        const authId = form.querySelector('input[name="auth_id"]').value;
                        const password = document.getElementById('edit-password-' + authId).value;
                        const passwordConfirm = document.getElementById('edit-password-confirm-' + authId).value;
                        
                        // Only validate if password is provided
                        if (password && password !== passwordConfirm) {
                            e.preventDefault();
                            checkPasswordMatchEdit(authId);
                            document.getElementById('password-error-edit-' + authId).classList.add('show');
                            return false;
                        }
                    });
                }
            });
            
            // Delete confirmation dialogs
            const deleteForms = document.querySelectorAll('.delete-form');
            
            deleteForms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const button = form.querySelector('.btn-delete');
                    const username = button.getAttribute('data-username');
                    const orgName = button.getAttribute('data-org-name');
                    
                    const warningMessage = 'Are you sure you want to delete the account for "' + orgName + '" (username: ' + username + ')?\n\n' +
                        'This action cannot be undone.';
                    
                    if (confirm(warningMessage)) {
                        form.submit();
                    }
                });
            });
        });
        
        function showEditForm(authId, username, email) {
            const editForm = document.getElementById('edit-form-' + authId);
            editForm.classList.add('visible');
        }
        
        function hideEditForm(authId) {
            const editForm = document.getElementById('edit-form-' + authId);
            editForm.classList.remove('visible');
            const form = document.getElementById('edit-form-' + authId + '-form');
            if (form) {
                form.reset();
            }
        }
        
        function togglePassword(inputId, toggleId) {
            const input = document.getElementById(inputId);
            const toggle = document.getElementById(toggleId);
            
            if (input.type === 'password') {
                input.type = 'text';
                toggle.textContent = 'Hide';
            } else {
                input.type = 'password';
                toggle.textContent = 'Show';
            }
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password');
            const passwordConfirm = document.getElementById('password_confirm');
            const errorDiv = document.getElementById('password-error');
            
            if (passwordConfirm.value && password.value !== passwordConfirm.value) {
                password.classList.add('password-mismatch');
                passwordConfirm.classList.add('password-mismatch');
                errorDiv.classList.add('show');
            } else {
                password.classList.remove('password-mismatch');
                passwordConfirm.classList.remove('password-mismatch');
                errorDiv.classList.remove('show');
            }
        }
        
        function checkPasswordMatchEdit(authId) {
            const password = document.getElementById('edit-password-' + authId);
            const passwordConfirm = document.getElementById('edit-password-confirm-' + authId);
            const errorDiv = document.getElementById('password-error-edit-' + authId);
            
            // Only validate if password is provided
            if (password.value && passwordConfirm.value) {
                if (password.value !== passwordConfirm.value) {
                    password.classList.add('password-mismatch');
                    passwordConfirm.classList.add('password-mismatch');
                    errorDiv.classList.add('show');
                } else {
                    password.classList.remove('password-mismatch');
                    passwordConfirm.classList.remove('password-mismatch');
                    errorDiv.classList.remove('show');
                }
            } else {
                password.classList.remove('password-mismatch');
                passwordConfirm.classList.remove('password-mismatch');
                errorDiv.classList.remove('show');
            }
        }
    </script>
</body>
</html>
