<?php

require_once __DIR__ . '/auth-check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Utils/ErrorHandler.php';
require_once __DIR__ . '/../../src/Models/Organisation.php';
require_once __DIR__ . '/../../src/Models/Member.php';
require_once __DIR__ . '/../../src/Models/Auth.php';

use Cepi\Utils\ErrorHandler;
use Cepi\Models\Organisation;
use Cepi\Models\Member;
use Cepi\Models\Auth;

ErrorHandler::init();

$adminId = getLoggedInAdminId();
$adminUsername = getLoggedInAdminUsername();
$adminFullName = getLoggedInAdminFullName();

$orgModel = new Organisation();
$memberModel = new Member();
$authModel = new Auth();

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
            <a href="create-account.php">Create Account</a>
            <a href="bulk-create-organisations.php">Bulk Create Organizations</a>
            <a href="organisations.php" class="active">Organizations</a>
        </nav>
        
        <table class="orgs-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Organization Name</th>
                    <th>Total Members</th>
                    <th>Active Members</th>
                    <th>Has Account</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($organisations)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: rgba(0, 0, 0, 0.6);">
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
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

