<?php

/**
 * Bootstrap file - Initialiseer error handler en andere globale instellingen
 * Include dit bestand bovenaan elke PHP file
 */

// Zet error reporting aan voor development
$isDev = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
if ($isDev) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', 0);
}

// Initialiseer error handler
require_once __DIR__ . '/../src/Utils/ErrorHandler.php';
\Cepi\Utils\ErrorHandler::init();

