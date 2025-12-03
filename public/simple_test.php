<?php

echo "=== SIMPLE PHP TEST ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Current working directory: " . getcwd() . "\n";
echo "Script directory: " . __DIR__ . "\n";

echo "\n=== FILE EXISTENCE CHECK ===\n";

$files = [
    '../config/bootstrap.php',
    '../vendor/autoload.php',
    '../.env',
    '../composer.json'
];

foreach ($files as $file) {
    $exists = file_exists(__DIR__ . '/' . $file) ? 'EXISTS' : 'MISSING';
    echo "$file: $exists\n";
}

echo "\n=== BASIC PHP FUNCTIONS TEST ===\n";
echo "time(): " . time() . "\n";
echo "date(): " . date('Y-m-d H:i:s') . "\n";

echo "\n=== END TEST ===\n";
