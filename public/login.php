<?php

session_start();

// If already logged in, redirect to import
if (isset($_SESSION['organisation_id'])) {
    header('Location: import.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Utils/ErrorHandler.php';
require_once __DIR__ . '/../src/Utils/CsrfToken.php';
require_once __DIR__ . '/../src/Models/Auth.php';
require_once __DIR__ . '/../src/Models/ActivityLog.php';

use Cepi\Utils\ErrorHandler;
use Cepi\Utils\CsrfToken;
use Cepi\Models\Auth;
use Cepi\Models\ActivityLog;

// Initialize error handler
ErrorHandler::init();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!CsrfToken::validate($csrfToken)) {
        $error = "Invalid security token. Please try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = "Please enter username and password";
        } else {
            $auth = new Auth();
            $user = $auth->login($username, $password);
            
            if ($user) {
                $_SESSION['organisation_id'] = $user['organisation_id'];
                $_SESSION['organisation_name'] = $user['organisation_name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['auth_id'] = $user['auth_id'];
                
                // Log successful login
                $activityLog = new ActivityLog();
                $activityLog->log('organisation', $user['organisation_id'], $user['username'], 'login', [
                    'success' => true
                ]);
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                header('Location: import.php');
                exit;
            } else {
                $error = "Invalid username or password";
                
                // Log failed login attempt
                $activityLog = new ActivityLog();
                $activityLog->log('organisation', null, $username, 'login', [
                    'success' => false,
                    'reason' => 'invalid_credentials'
                ]);
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
    <title>CEPI Login - Member Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #4A70A9 0%, #8FABD4 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: #EFECE3;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 400px;
            width: 100%;
        }
        h1 { 
            color: #000000;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            color: rgba(0, 0, 0, 0.6);
            margin-bottom: 30px;
            text-align: center;
            font-size: 14px;
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
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #8FABD4;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
            background: white;
        }
        input:focus {
            outline: none;
            border-color: #4A70A9;
        }
        .btn {
            width: 100%;
            background: linear-gradient(135deg, #4A70A9 0%, #8FABD4 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 112, 169, 0.4);
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
            font-size: 32px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">🔐</div>
        <h1>CEPI Login</h1>
        <p class="subtitle">Log in with your organization account</p>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <?= CsrfToken::field() ?>
            
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" name="username" id="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" name="password" id="password" required>
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
    </div>
</body>
</html>

