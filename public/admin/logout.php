<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Store session data before clearing (for logging)
$adminId = $_SESSION['admin_id'] ?? null;
$adminUsername = $_SESSION['admin_username'] ?? null;

// Log logout als admin ingelogd was (try-catch to prevent logout failure)
if ($adminId) {
    try {
        // Only try to log if files exist
        $dbFile = __DIR__ . '/../../config/database.php';
        $logFile = __DIR__ . '/../../src/Models/ActivityLog.php';
        
        if (file_exists($dbFile) && file_exists($logFile)) {
            require_once $dbFile;
            require_once $logFile;
            
            // Check if class exists before using it
            if (class_exists('\Cepi\Models\ActivityLog')) {
                $activityLog = new \Cepi\Models\ActivityLog();
                $activityLog->log(
                    'admin', 
                    $adminId, 
                    $adminUsername ?? 'unknown', 
                    'logout'
                );
            }
        }
    } catch (\Throwable $e) {
        // Silently fail - don't prevent logout
        error_log("Logout logging error: " . $e->getMessage());
    } catch (\Exception $e) {
        // Fallback for older PHP versions
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Clear all session data
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"] ?? '/',
        $params["domain"] ?? '',
        $params["secure"] ?? false,
        $params["httponly"] ?? false
    );
}

// Destroy session
@session_destroy();

// Redirect to login
header('Location: login.php');
exit;

