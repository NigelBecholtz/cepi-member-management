<?php

// Export functionality removed - redirect to import
require_once __DIR__ . '/auth-check.php';
header('Location: import.php');
exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $format = $_POST['format'] ?? 'csv';
    $includeInactive = isset($_POST['include_inactive']);
    
    try {
        $exportService = new ExportService();
        
        if ($format === 'csv') {
            $exportService->exportToCsv($orgId, $includeInactive);
        } else {
            $exportService->exportToExcel($orgId, $includeInactive);
        }
    } catch (Exception $e) {
        die("Export error: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPI Export - Member Management</title>
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
        h1 { 
            color: #000000;
            margin-bottom: 10px;
        }
        .subtitle {
            color: rgba(0, 0, 0, 0.6);
            margin-bottom: 30px;
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
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #000000;
        }
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #8FABD4;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
            background: white;
        }
        select:focus {
            outline: none;
            border-color: #4A70A9;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .help-text {
            font-size: 12px;
            color: rgba(0, 0, 0, 0.6);
            margin-top: 5px;
        }
        .btn {
            background: #4A70A9;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #8FABD4;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h1>CEPI Members Export</h1>
                <p class="subtitle">Organization: <?= htmlspecialchars($orgName) ?></p>
            </div>
            <div>
                <a href="logout.php" style="color: #dc3545; text-decoration: none;">Logout</a>
            </div>
        </div>
        
        <nav class="nav">
            <a href="index.php">Dashboard</a>
            <a href="import.php">Import</a>
            <a href="export.php" class="active">Export</a>
        </nav>
        
        <div style="background: #fff3cd; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
            <strong>ℹ️ Info:</strong> Members will be exported for <strong><?= htmlspecialchars($orgName) ?></strong>
        </div>
        
        <form method="POST">
                
                <div class="form-group">
                    <label for="format">Export Format: *</label>
                    <select name="format" id="format" required>
                        <option value="csv">CSV (Comma Separated Values)</option>
                        <option value="excel">Excel (XLSX)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="include_inactive" id="include_inactive" value="1">
                        <label for="include_inactive" style="margin: 0; font-weight: normal;">
                            Include deactivated members
                        </label>
                    </div>
                    <p class="help-text">
                        Check to also export deactivated (inactive) members.
                    </p>
                </div>
                
                <button type="submit" class="btn">Export</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

