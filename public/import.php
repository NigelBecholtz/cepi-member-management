<?php

require_once __DIR__ . '/auth-check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Utils/ErrorHandler.php';
require_once __DIR__ . '/../src/Utils/CsrfToken.php';
require_once __DIR__ . '/../src/Services/ImportService.php';

use Cepi\Utils\ErrorHandler;
use Cepi\Utils\CsrfToken;
use Cepi\Services\ImportService;

// Initialiseer error handler
ErrorHandler::init();

$orgId = getLoggedInOrganisationId();
$orgName = getLoggedInOrganisationName();
$username = getLoggedInUsername();

$message = '';
$error = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!CsrfToken::validate($csrfToken)) {
        $error = "Invalid security token. Please try again.";
    } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please select a file to upload";
    } else {
        try {
            $importService = new ImportService();
            // Gebruik automatisch de ingelogde organisatie
            // Sync mode: Excel bestand is bron van waarheid
            $result = $importService->importFromFile($orgId, $_FILES['file'], $username);
            
            $message = sprintf(
                "Synchronization successful! %d members in file (%d added, %d updated, %d deleted)",
                $result['rows_imported'],
                $result['rows_added'],
                $result['rows_updated'],
                $result['rows_deleted']
            );
            
            if (!empty($result['errors'])) {
                $error = "Warnings: " . count($result['errors']) . " errors found. " . 
                         implode('; ', array_slice($result['errors'], 0, 5));
                if (count($result['errors']) > 5) {
                    $error .= " ... (and " . (count($result['errors']) - 5) . " more)";
                }
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEPI - Member Synchronization</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #EFECE3 -80%, #8FABD4 20%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #EFECE3;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            padding: 40px;
            border: 1px solid rgba(143, 171, 212, 0.3);
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 3px solid #8FABD4;
        }
        h1 { 
            color: #000000;
            margin-bottom: 8px;
            font-size: 32px;
            font-weight: 700;
        }
        .subtitle {
            color: rgba(0, 0, 0, 0.6);
            font-size: 16px;
        }
        .user-info {
            text-align: right;
        }
        .user-info p {
            color: rgba(0, 0, 0, 0.6);
            margin-bottom: 8px;
            font-size: 14px;
        }
        .user-info strong {
            color: #4A70A9;
            font-weight: 600;
        }
        .user-info a {
            color: #dc3545;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        .user-info a:hover {
            color: #c82333;
            text-decoration: underline;
        }
        .info-card {
            background: linear-gradient(135deg, rgba(74, 112, 169, 0.1) 0%, rgba(143, 171, 212, 0.2) 100%);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-left: 5px solid #4A70A9;
            box-shadow: 0 4px 12px rgba(74, 112, 169, 0.1);
        }
        .info-card strong {
            color: #4A70A9;
            font-size: 18px;
            display: block;
            margin-bottom: 10px;
        }
        .info-card p {
            color: rgba(0, 0, 0, 0.7);
            margin: 8px 0;
            line-height: 1.6;
        }
        .info-card .btn {
            margin-top: 15px;
            display: inline-block;
        }
        .form-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border: 2px solid rgba(143, 171, 212, 0.2);
        }
        .form-group {
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #000000;
            font-size: 15px;
        }
        input[type="file"] {
            width: 100%;
            padding: 14px;
            border: 2px dashed #8FABD4;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            background: rgba(143, 171, 212, 0.05);
            cursor: pointer;
        }
        input[type="file"]:hover {
            border-color: #4A70A9;
            background: rgba(74, 112, 169, 0.1);
        }
        input[type="file"]:focus {
            outline: none;
            border-color: #4A70A9;
            border-style: solid;
            background: white;
        }
        .help-text {
            font-size: 13px;
            color: rgba(0, 0, 0, 0.65);
            margin-top: 10px;
            line-height: 1.7;
            background: rgba(143, 171, 212, 0.1);
            padding: 12px;
            border-radius: 6px;
            border-left: 3px solid #8FABD4;
        }
        .help-text strong {
            color: #4A70A9;
        }
        .btn {
            background: linear-gradient(135deg, #4A70A9 0%, #8FABD4 100%);
            color: white;
            padding: 14px 35px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(74, 112, 169, 0.3);
            width: 100%;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(74, 112, 169, 0.4);
        }
        .btn:active {
            transform: translateY(0);
        }
        .message {
            padding: 18px 20px;
            margin: 25px 0;
            border-radius: 10px;
            border-left: 5px solid;
            font-size: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        .message.success {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.15) 0%, rgba(40, 167, 69, 0.05) 100%);
            color: #155724;
            border-color: #28a745;
        }
        .message.error {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.15) 0%, rgba(220, 53, 69, 0.05) 100%);
            color: #721c24;
            border-color: #dc3545;
        }
        .result-box {
            background: linear-gradient(135deg, rgba(143, 171, 212, 0.25) 0%, rgba(143, 171, 212, 0.15) 100%);
            padding: 25px;
            border-radius: 12px;
            margin-top: 25px;
            border: 2px solid rgba(74, 112, 169, 0.3);
            box-shadow: 0 4px 16px rgba(74, 112, 169, 0.15);
        }
        .result-box h3 {
            margin-bottom: 15px;
            color: #000000;
            font-size: 20px;
            font-weight: 700;
            padding-bottom: 10px;
            border-bottom: 2px solid #8FABD4;
        }
        .result-box ul {
            list-style: none;
            padding-left: 0;
        }
        .result-box li {
            padding: 10px 0;
            color: rgba(0, 0, 0, 0.7);
            font-size: 15px;
            border-bottom: 1px solid rgba(143, 171, 212, 0.3);
        }
        .result-box li:last-child {
            border-bottom: none;
        }
        .result-box li strong {
            color: #4A70A9;
            font-weight: 600;
            margin-right: 8px;
        }
        @media (max-width: 768px) {
            .container {
                padding: 25px;
            }
            .header-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .user-info {
                text-align: left;
            }
            h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <div>
                <h1>CEPI Member Synchronization</h1>
                <p class="subtitle">Organization: <?= htmlspecialchars($orgName) ?></p>
            </div>
            <div class="user-info">
                <p>Logged in as: <strong><?= htmlspecialchars($username) ?></strong></p>
                <a href="logout.php">Logout</a>
            </div>
        </div>
        
        <div class="info-card">
            <strong>📥 Download Template</strong>
            <p>
                Download an Excel template file to fill in your members before importing.
            </p>
            <a href="download-template.php" class="btn" style="width: auto; padding: 12px 25px;">
                Download Excel Template
            </a>
        </div>
        
        <?php if ($message): ?>
            <div class="message success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="form-section">
            <form method="POST" enctype="multipart/form-data">
                <?= CsrfToken::field() ?>
                
                <div class="form-group">
                    <label for="file">Excel File (CSV/XLS/XLSX): *</label>
                    <input type="file" name="file" id="file" accept=".csv,.xls,.xlsx" required>
                    <p class="help-text">
                        <strong>Required columns:</strong> email_address (or email), mm_cepi<br>
                        <strong>How it works:</strong><br>
                        • Members in the file → will be added or updated<br>
                        • Members NOT in the file → will be automatically deleted from the database<br>
                        • Organization: <?= htmlspecialchars($orgName) ?>
                    </p>
                </div>
                
                <button type="submit" class="btn">🔄 Synchronize Members</button>
            </form>
        </div>
        
        <?php if ($result): ?>
            <div class="result-box">
                <h3>📊 Synchronization Results</h3>
                <ul>
                    <li><strong>Members in file:</strong> <?= $result['rows_imported'] ?></li>
                    <li><strong>Newly added:</strong> <?= $result['rows_added'] ?></li>
                    <li><strong>Updated:</strong> <?= $result['rows_updated'] ?></li>
                    <li><strong>Deleted (not in file):</strong> <?= $result['rows_deleted'] ?></li>
                    <?php if (!empty($result['errors'])): ?>
                        <li><strong>Errors:</strong> <?= count($result['errors']) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

