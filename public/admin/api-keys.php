<?php

require_once __DIR__ . '/auth-check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Utils/ErrorHandler.php';
require_once __DIR__ . '/../../src/Utils/CsrfToken.php';
require_once __DIR__ . '/../../src/Models/ApiKey.php';
require_once __DIR__ . '/../../src/Models/ActivityLog.php';

use Cepi\Utils\ErrorHandler;
use Cepi\Utils\CsrfToken;
use Cepi\Models\ApiKey;
use Cepi\Models\ActivityLog;

ErrorHandler::init();

$adminId = getLoggedInAdminId();
$adminUsername = getLoggedInAdminUsername();
$adminFullName = getLoggedInAdminFullName();

$apiKeyModel = new ApiKey();
$activityLog = new ActivityLog();

$message = '';
$error = '';
$newApiKey = null; // For displaying newly created key

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!CsrfToken::validate($csrfToken)) {
        $error = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            // Create new API key
            $keyName = trim($_POST['key_name'] ?? '');
            $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

            if (empty($keyName)) {
                $error = 'API key name is required';
            } else {
                try {
                    $newApiKey = $apiKeyModel->generate($keyName, $expiresAt, $adminId);

                    // Log the creation
                    $activityLog->log('admin', $adminId, $adminUsername, 'api_key_created', [
                        'api_key_name' => $keyName,
                        'api_key_id' => $newApiKey['id'],
                        'expires_at' => $expiresAt
                    ]);

                    $message = "API key '{$keyName}' created successfully!";
                } catch (\Exception $e) {
                    $error = 'Failed to create API key: ' . $e->getMessage();
                }
            }

        } elseif ($action === 'activate') {
            $keyId = (int)($_POST['key_id'] ?? 0);
            if ($apiKeyModel->activate($keyId)) {
                $activityLog->log('admin', $adminId, $adminUsername, 'api_key_activated', [
                    'api_key_id' => $keyId
                ]);
                $message = 'API key activated successfully!';
            } else {
                $error = 'Failed to activate API key';
            }

        } elseif ($action === 'deactivate') {
            $keyId = (int)($_POST['key_id'] ?? 0);
            if ($apiKeyModel->deactivate($keyId)) {
                $activityLog->log('admin', $adminId, $adminUsername, 'api_key_deactivated', [
                    'api_key_id' => $keyId
                ]);
                $message = 'API key deactivated successfully!';
            } else {
                $error = 'Failed to deactivate API key';
            }

        } elseif ($action === 'delete') {
            $keyId = (int)($_POST['key_id'] ?? 0);
            if ($apiKeyModel->delete($keyId)) {
                $activityLog->log('admin', $adminId, $adminUsername, 'api_key_deleted', [
                    'api_key_id' => $keyId
                ]);
                $message = 'API key deleted successfully!';
            } else {
                $error = 'Failed to delete API key';
            }
        }
    }
}

// Get all API keys
$apiKeys = $apiKeyModel->getAll();

// Filter options
$filterStatus = $_GET['status'] ?? '';
$filteredKeys = $apiKeys;

if ($filterStatus === 'active') {
    $filteredKeys = array_filter($filteredKeys, function($key) { return $key['is_active']; });
} elseif ($filterStatus === 'inactive') {
    $filteredKeys = array_filter($filteredKeys, function($key) { return !$key['is_active']; });
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Keys - CEPI Admin</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #EFECE3;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
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

        .content {
            padding: 0;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-section {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .form-section h2 {
            margin-top: 0;
            color: #000000;
            font-size: 1.5em;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 14px;
            color: #000000;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #8FABD4;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #4A70A9;
        }

        .btn {
            background: #4A70A9;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover {
            background: #8FABD4;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #8FABD4;
        }

        .table th {
            background: rgba(143, 171, 212, 0.3);
            font-weight: 600;
            color: #000000;
        }

        .table tr:hover {
            background: rgba(143, 171, 212, 0.1);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #28a745;
            color: white;
        }

        .status-inactive {
            background: #dc3545;
            color: white;
        }

        .status-expired {
            background: #ffc107;
            color: #333;
        }

        .key-preview {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 14px;
            border: 1px solid #8FABD4;
        }

        .filters {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #8FABD4;
        }

        .filters select {
            padding: 8px 12px;
            border: 1px solid #8FABD4;
            border-radius: 4px;
            margin-left: 10px;
            background: white;
        }

        .new-key-display {
            background: linear-gradient(135deg, #4A70A9 0%, #8FABD4 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .new-key-display h3 {
            margin: 0 0 10px 0;
            font-size: 1.2em;
        }

        .new-key-code {
            font-family: monospace;
            font-size: 18px;
            background: rgba(255,255,255,0.2);
            padding: 10px;
            border-radius: 4px;
            word-break: break-all;
            margin: 10px 0;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .copy-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }

        .copy-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .table th, .table td {
                padding: 10px 5px;
                font-size: 14px;
            }

            .actions {
                flex-direction: column;
            }

            .btn-small {
                width: 100%;
                margin-bottom: 2px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔑 API Keys</h1>
            <p>Manage API keys for accessing the CEPI Member Check API</p>
        </div>

        <div class="nav">
            <a href="dashboard.php">📊 Dashboard</a>
            <a href="organisations.php">🏢 Organizations</a>
            <a href="api-keys.php">🔑 API Keys</a>
            <a href="logout.php">🚪 Logout</a>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($newApiKey): ?>
                <div class="new-key-display">
                    <h3>🎉 New API Key Created!</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($newApiKey['name']); ?></p>
                    <p><strong>API Key:</strong></p>
                    <div class="new-key-code" id="newApiKey"><?php echo htmlspecialchars($newApiKey['key']); ?></div>
                    <p><em>This key will only be shown once. Make sure to copy it now!</em></p>
                    <button class="copy-btn" onclick="copyToClipboard('newApiKey')">📋 Copy Key</button>
                </div>
            <?php endif; ?>

            <div class="form-section">
                <h2>➕ Create New API Key</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(CsrfToken::get()); ?>">
                    <input type="hidden" name="action" value="create">

                    <div class="form-group">
                        <label for="key_name">API Key Name:</label>
                        <input type="text" id="key_name" name="key_name" required
                               placeholder="e.g., My Integration Key" maxlength="255">
                    </div>

                    <div class="form-group">
                        <label for="expires_at">Expiration Date (optional):</label>
                        <input type="datetime-local" id="expires_at" name="expires_at">
                        <small style="color: #666; display: block; margin-top: 5px;">
                            Leave empty for no expiration. Format: YYYY-MM-DD HH:MM
                        </small>
                    </div>

                    <button type="submit" class="btn">🔑 Create API Key</button>
                </form>
            </div>

            <div class="filters">
                <strong>Filter:</strong>
                <select onchange="filterKeys(this.value)">
                    <option value="">All Keys</option>
                    <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active Only</option>
                    <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                </select>
            </div>

            <h2>📋 API Keys (<?php echo count($filteredKeys); ?>)</h2>

            <?php if (empty($filteredKeys)): ?>
                <p>No API keys found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Key Preview</th>
                                <th>Status</th>
                                <th>Last Used</th>
                                <th>Usage Count</th>
                                <th>Created</th>
                                <th>Expires</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredKeys as $key): ?>
                                <?php
                                $isExpired = $key['expires_at'] && strtotime($key['expires_at']) < time();
                                $statusClass = $key['is_active'] ? ($isExpired ? 'status-expired' : 'status-active') : 'status-inactive';
                                $statusText = $key['is_active'] ? ($isExpired ? 'Expired' : 'Active') : 'Inactive';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($key['key_name']); ?></td>
                                    <td>
                                        <span class="key-preview">
                                            <?php echo htmlspecialchars(substr($key['api_key_hash'], 0, 8) . '...'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $key['last_used_at'] ? date('Y-m-d H:i', strtotime($key['last_used_at'])) : 'Never'; ?>
                                    </td>
                                    <td><?php echo number_format($key['usage_count']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($key['created_at'])); ?></td>
                                    <td><?php echo $key['expires_at'] ? date('Y-m-d H:i', strtotime($key['expires_at'])) : 'Never'; ?></td>
                                    <td>
                                        <div class="actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(CsrfToken::get()); ?>">
                                                <input type="hidden" name="key_id" value="<?php echo $key['api_key_id']; ?>">

                                                <?php if ($key['is_active'] && !$isExpired): ?>
                                                    <button type="submit" name="action" value="deactivate" class="btn btn-danger btn-small">
                                                        ❌ Deactivate
                                                    </button>
                                                <?php elseif (!$key['is_active'] && !$isExpired): ?>
                                                    <button type="submit" name="action" value="activate" class="btn btn-success btn-small">
                                                        ✅ Activate
                                                    </button>
                                                <?php endif; ?>

                                                <button type="submit" name="action" value="delete"
                                                        class="btn btn-danger btn-small"
                                                        onclick="return confirm('Are you sure you want to delete this API key? This action cannot be undone.')">
                                                    🗑️ Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent || element.innerText;

            navigator.clipboard.writeText(text).then(function() {
                // Show temporary feedback
                const original = element.style.backgroundColor;
                element.style.backgroundColor = '#4CAF50';
                setTimeout(() => {
                    element.style.backgroundColor = original;
                }, 500);
            }).catch(function(err) {
                // Fallback for older browsers
                const textArea = document.createElement("textarea");
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                } catch (err) {
                    console.error('Fallback copy failed: ', err);
                }
                document.body.removeChild(textArea);
            });
        }

        function filterKeys(status) {
            const url = new URL(window.location);
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
