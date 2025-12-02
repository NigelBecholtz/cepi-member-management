<?php

namespace Cepi\Utils;

class ErrorHandler {
    private static $logFile;
    private static $isInitialized = false;
    
    /**
     * Initialiseer de error handler
     */
    public static function init($logFile = null) {
        if (self::$isInitialized) {
            return;
        }
        
        self::$logFile = $logFile ?? __DIR__ . '/../../logs/error.log';
        
        // Zorg dat log directory bestaat
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Set error handlers
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        
        self::$isInitialized = true;
    }
    
    /**
     * Handle PHP errors
     */
    public static function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorType = self::getErrorType($severity);
        $logMessage = sprintf(
            "[%s] %s: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $errorType,
            $message,
            $file,
            $line
        );
        
        self::writeLog($logMessage);
        
        // In development mode, toon errors
        $isDev = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
        if ($isDev && $severity === E_ERROR) {
            self::displayError($errorType, $message, $file, $line);
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function handleException($exception) {
        $logMessage = sprintf(
            "[%s] EXCEPTION: %s in %s on line %d\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        self::writeLog($logMessage);
        
        // In development mode, toon exception details
        $isDev = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
        if ($isDev) {
            self::displayException($exception);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'An internal error occurred. Please try again later.',
                'error_code' => 'INTERNAL_ERROR'
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * Handle fatal errors
     */
    public static function handleShutdown() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $logMessage = sprintf(
                "[%s] FATAL ERROR: %s in %s on line %d",
                date('Y-m-d H:i:s'),
                $error['message'],
                $error['file'],
                $error['line']
            );
            
            self::writeLog($logMessage);
        }
    }
    
    /**
     * Log een custom message
     */
    public static function log($message, $level = 'INFO') {
        $logMessage = sprintf(
            "[%s] [%s] %s",
            date('Y-m-d H:i:s'),
            $level,
            $message
        );
        
        self::writeLog($logMessage);
    }
    
    /**
     * Schrijf naar log bestand
     */
    private static function writeLog($message) {
        if (self::$logFile) {
            file_put_contents(self::$logFile, $message . "\n", FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Get error type string
     */
    private static function getErrorType($severity) {
        $types = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        ];
        
        return $types[$severity] ?? 'UNKNOWN';
    }
    
    /**
     * Display error in development mode
     */
    private static function displayError($type, $message, $file, $line) {
        http_response_code(500);
        echo "<h2>Error: {$type}</h2>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($message) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($file) . "</p>";
        echo "<p><strong>Line:</strong> {$line}</p>";
        exit;
    }
    
    /**
     * Display exception in development mode
     */
    private static function displayException($exception) {
        http_response_code(500);
        echo "<h2>Exception: " . htmlspecialchars(get_class($exception)) . "</h2>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
        echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
        echo "<h3>Stack Trace:</h3>";
        echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        exit;
    }
}

