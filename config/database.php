<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Laad .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $dbname = $_ENV['DB_NAME'] ?? 'cepi';
            $username = $_ENV['DB_USER'] ?? 'root';
            $password = $_ENV['DB_PASSWORD'] ?? '';
            $port = $_ENV['DB_PORT'] ?? 3306;
            
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            
            $this->connection = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false
                ]
            );
        } catch (PDOException $e) {
            $errorMsg = "Database connection failed: " . $e->getMessage();
            error_log($errorMsg);
            
            // In development mode, toon meer details
            $isDev = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
            
            if ($isDev) {
                die("
                <h2>Database Connection Error</h2>
                <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                <p><strong>Host:</strong> " . htmlspecialchars($host) . "</p>
                <p><strong>Database:</strong> " . htmlspecialchars($dbname) . "</p>
                <p><strong>Port:</strong> " . htmlspecialchars($port) . "</p>
                <p><strong>User:</strong> " . htmlspecialchars($username) . "</p>
                <hr>
                <p><strong>Controleer:</strong></p>
                <ul>
                    <li>Is je .env bestand correct geconfigureerd?</li>
                    <li>Is de database server bereikbaar?</li>
                    <li>Bestaat de database al? (Importeer database/schema.sql)</li>
                    <li>Zijn de credentials correct?</li>
                </ul>
                ");
            } else {
                die("Database connection failed. Please check your configuration.");
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function __clone() {
        throw new Exception("Cannot clone a singleton.");
    }
    
    public function __wakeup() {
        throw new Exception("Cannot unserialize a singleton.");
    }
}

