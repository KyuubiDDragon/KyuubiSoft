#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Database Migration Script
 * Usage: php bin/migrate.php [--seed]
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment
define('BASE_PATH', dirname(__DIR__));

if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
}

// Database connection
$config = [
    'host' => $_ENV['DB_HOST'] ?? 'mysql',
    'port' => $_ENV['DB_PORT'] ?? 3306,
    'dbname' => $_ENV['DB_DATABASE'] ?? 'kyuubisoft',
    'user' => $_ENV['DB_USERNAME'] ?? 'kyuubisoft',
    'password' => $_ENV['DB_PASSWORD'] ?? 'secret',
];

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['dbname']),
        $config['user'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "Connected to database.\n";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Create migrations table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL,
        executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Get executed migrations
$executed = $pdo->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);

// Get migration files
$migrationsPath = BASE_PATH . '/database/migrations';
$migrationFiles = glob($migrationsPath . '/*.sql');
sort($migrationFiles);

$newMigrations = 0;

foreach ($migrationFiles as $file) {
    $filename = basename($file);

    if (in_array($filename, $executed)) {
        echo "Skipping: $filename (already executed)\n";
        continue;
    }

    echo "Running: $filename\n";

    $sql = file_get_contents($file);

    try {
        $pdo->exec($sql);
        $pdo->prepare("INSERT IGNORE INTO migrations (migration) VALUES (?)")->execute([$filename]);
        echo "  ✓ Success\n";
        $newMigrations++;
    } catch (PDOException $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if ($newMigrations === 0) {
    echo "\nNo new migrations to run.\n";
} else {
    echo "\nRan $newMigrations migration(s).\n";
}

// Run seeders if --seed flag is provided
if (in_array('--seed', $argv)) {
    echo "\nRunning seeders...\n";

    $seedersPath = BASE_PATH . '/database/seeders';
    $seederFiles = glob($seedersPath . '/*.sql');
    sort($seederFiles);

    foreach ($seederFiles as $file) {
        $filename = basename($file);
        echo "Seeding: $filename\n";

        $sql = file_get_contents($file);

        try {
            $pdo->exec($sql);
            echo "  ✓ Success\n";
        } catch (PDOException $e) {
            echo "  ✗ Error: " . $e->getMessage() . "\n";
            // Continue with other seeders
        }
    }

    echo "\nSeeding complete.\n";
}

echo "\nDone!\n";
