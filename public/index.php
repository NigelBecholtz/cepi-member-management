<?php

// Redirect to import page
require_once __DIR__ . '/auth-check.php';
header('Location: import.php');
exit;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPI Member Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 { 
            color: #333;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .nav {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        .nav a {
            padding: 10px 20px;
            text-decoration: none;
            color: #666;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        .nav a:hover, .nav a.active {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-decoration: none;
            display: block;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #768ef0 0%, #865bb2 100%);
        }
        .stat-card:active {
            transform: translateY(-2px);
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
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h1>CEPI Member Management</h1>
                <p class="subtitle">Welcome, <?= htmlspecialchars($orgName) ?></p>
            </div>
            <div style="text-align: right;">
                <p style="color: #666; margin-bottom: 5px;">Logged in as: <strong><?= htmlspecialchars($username) ?></strong></p>
                <a href="logout.php" style="color: #dc3545; text-decoration: none; font-size: 14px;">Logout</a>
            </div>
        </div>
        
        <nav class="nav">
            <a href="index.php" class="active">Dashboard</a>
            <a href="import.php">Import</a>
            <a href="export.php">Export</a>
        </nav>
        
        <div class="stats">
            <a href="import.php" class="stat-card">
                <h3>Active Members</h3>
                <div class="number"><?= $activeMembers ?></div>
            </a>
            <a href="import.php" class="stat-card">
                <h3>Total Members</h3>
                <div class="number"><?= $totalMembers ?></div>
            </a>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="import.php" class="btn">Import Members</a>
            <a href="export.php" class="btn btn-secondary" style="margin-left: 10px;">Export Members</a>
            <a href="download-template.php" class="btn btn-secondary" style="margin-left: 10px; background: #28a745;">
                📥 Download Template
            </a>
        </div>
    </div>
</body>
</html>

