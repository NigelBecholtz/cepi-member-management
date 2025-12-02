<?php

require_once __DIR__ . '/auth-check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Utils/ErrorHandler.php';
require_once __DIR__ . '/../../src/Utils/CsrfToken.php';
require_once __DIR__ . '/../../src/Models/ActivityLog.php';
require_once __DIR__ . '/../../src/Models/Organisation.php';
require_once __DIR__ . '/../../src/Models/Member.php';

use Cepi\Utils\ErrorHandler;
use Cepi\Utils\CsrfToken;
use Cepi\Models\ActivityLog;
use Cepi\Models\Organisation;
use Cepi\Models\Member;

ErrorHandler::init();

$adminId = getLoggedInAdminId();
$adminUsername = getLoggedInAdminUsername();
$adminFullName = getLoggedInAdminFullName();

$activityLog = new ActivityLog();
$orgModel = new Organisation();
$memberModel = new Member();

// Get statistics
$totalOrgs = count($orgModel->getAll());
$totalMembers = 0;
$orgs = $orgModel->getAll();
foreach ($orgs as $org) {
    $totalMembers += $memberModel->countByOrganisation($org['organisation_id'], false);
}

// Get recent logs
$recentLogs = $activityLog->getLogs([], 20);

// Filter options
$filterAction = $_GET['action_type'] ?? '';
$filterUserType = $_GET['user_type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$filters = [];
if ($filterAction) $filters['action_type'] = $filterAction;
if ($filterUserType) $filters['user_type'] = $filterUserType;
if ($dateFrom) $filters['date_from'] = $dateFrom . ' 00:00:00';
if ($dateTo) $filters['date_to'] = $dateTo . ' 23:59:59';

$logs = $activityLog->getLogs($filters, 100);
$totalLogs = $activityLog->countLogs($filters);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPI Admin Dashboard</title>
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
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #4A70A9 0%, #8FABD4 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
        }
        .stat-card h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
        }
        .filters {
            background: rgba(143, 171, 212, 0.2);
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .form-group {
            margin-bottom: 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 14px;
            color: #000000;
        }
        select, input[type="date"] {
            width: 100%;
            padding: 8px;
            border: 2px solid #8FABD4;
            border-radius: 4px;
            font-size: 14px;
            background: white;
        }
        select:focus, input[type="date"]:focus {
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
        .btn-secondary {
            background: rgba(0, 0, 0, 0.3);
        }
        .btn-secondary:hover {
            background: rgba(0, 0, 0, 0.5);
        }
        .logs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .logs-table th,
        .logs-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #8FABD4;
        }
        .logs-table th {
            background: rgba(143, 171, 212, 0.3);
            font-weight: 600;
            color: #000000;
        }
        .logs-table tr:hover {
            background: rgba(143, 171, 212, 0.1);
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-admin {
            background: #dc3545;
            color: white;
        }
        .badge-org {
            background: #4A70A9;
            color: white;
        }
        .badge-api {
            background: #28a745;
            color: white;
        }
        .badge-login {
            background: #17a2b8;
            color: white;
        }
        .badge-upload {
            background: #ffc107;
            color: #333;
        }
        .action-details {
            font-size: 12px;
            color: rgba(0, 0, 0, 0.6);
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>CEPI Admin Dashboard <span class="admin-badge">ADMIN</span></h1>
                <p class="subtitle">Welcome, <?= htmlspecialchars($adminFullName) ?></p>
            </div>
            <div style="text-align: right;">
                <p style="color: rgba(0, 0, 0, 0.6); margin-bottom: 5px;">Logged in as: <strong><?= htmlspecialchars($adminUsername) ?></strong></p>
                <a href="logout.php" style="color: #dc3545; text-decoration: none; font-size: 14px;">Logout</a>
            </div>
        </div>
        
        <nav class="nav">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="create-account.php">Create Account</a>
            <a href="bulk-create-organisations.php">Bulk Create Organizations</a>
            <a href="organisations.php">Organizations</a>
            <a href="api-keys.php">API Keys</a>
        </nav>
        
        <div class="stats">
            <div class="stat-card">
                <h3>Total Organizations</h3>
                <div class="number"><?= $totalOrgs ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Members</h3>
                <div class="number"><?= $totalMembers ?></div>
            </div>
            <div class="stat-card">
                <h3>Activity Logs</h3>
                <div class="number"><?= $totalLogs ?></div>
            </div>
        </div>
        
        <h2 style="margin-bottom: 20px;">Activity Logs</h2>
        
        <div class="filters">
            <form method="GET">
                <div class="form-group">
                    <label for="action_type">Action Type:</label>
                    <select name="action_type" id="action_type">
                        <option value="">All Actions</option>
                        <option value="api_call" <?= $filterAction === 'api_call' ? 'selected' : '' ?>>API Calls</option>
                        <option value="login" <?= $filterAction === 'login' ? 'selected' : '' ?>>Logins</option>
                        <option value="upload" <?= $filterAction === 'upload' ? 'selected' : '' ?>>Excel Uploads</option>
                        <option value="create_account" <?= $filterAction === 'create_account' ? 'selected' : '' ?>>Create Account</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="user_type">User Type:</label>
                    <select name="user_type" id="user_type">
                        <option value="">All Users</option>
                        <option value="admin" <?= $filterUserType === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="organisation" <?= $filterUserType === 'organisation' ? 'selected' : '' ?>>Organization</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_from">From Date:</label>
                    <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="form-group">
                    <label for="date_to">To Date:</label>
                    <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">Filter</button>
                    <a href="dashboard.php" class="btn btn-secondary" style="margin-left: 10px; display: inline-block; text-decoration: none;">Reset</a>
                </div>
            </form>
        </div>
        
        <table class="logs-table">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>User Type</th>
                    <th>Username</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: rgba(0, 0, 0, 0.6);">
                            No logs found
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= date('d-m-Y H:i:s', strtotime($log['created_at'] ?? 'now')) ?></td>
                            <td>
                                <span class="badge badge-<?= ($log['user_type'] ?? '') === 'admin' ? 'admin' : 'org' ?>">
                                    <?= strtoupper($log['user_type'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($log['username'] ?? 'N/A') ?></td>
                            <td>
                                <?php
                                $actionType = $log['action_type'] ?? '';
                                $badgeClass = 'badge-org';
                                if ($actionType === 'api_call') $badgeClass = 'badge-api';
                                elseif ($actionType === 'login') $badgeClass = 'badge-login';
                                elseif ($actionType === 'upload') $badgeClass = 'badge-upload';
                                ?>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($actionType ?: 'N/A') ?>
                                </span>
                            </td>
                            <td class="action-details">
                                <?php
                                if (!empty($log['action_details'])) {
                                    $details = json_decode($log['action_details'], true);
                                    if (is_array($details)) {
                                        echo htmlspecialchars(implode(', ', array_map(function($k, $v) {
                                            return "$k: $v";
                                        }, array_keys($details), $details)));
                                    } else {
                                        echo htmlspecialchars($log['action_details']);
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

