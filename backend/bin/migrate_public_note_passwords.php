#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Hash any plaintext share passwords stored in `notes.public_settings.password`.
 *
 * Before the security fix, `share()` wrote the password as plaintext into the
 * settings JSON and `show()` never checked it. After the fix the controller
 * stores `password_hash` instead. This script migrates any existing rows.
 *
 * Idempotent — rows that already have `password_hash` set are skipped.
 *
 * Run:
 *   php bin/migrate_public_note_passwords.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

define('BASE_PATH', dirname(__DIR__));

if (file_exists(BASE_PATH . '/.env')) {
    Dotenv::createImmutable(BASE_PATH)->load();
}

$db = DriverManager::getConnection([
    'dbname'   => $_ENV['DB_DATABASE'] ?? 'kyuubisoft',
    'user'     => $_ENV['DB_USERNAME'] ?? 'kyuubisoft',
    'password' => $_ENV['DB_PASSWORD'] ?? 'secret',
    'host'     => $_ENV['DB_HOST'] ?? 'mysql',
    'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
    'driver'   => 'pdo_mysql',
    'charset'  => 'utf8mb4',
]);

$rows = $db->fetchAllAssociative(
    "SELECT id, public_settings FROM notes WHERE public_settings IS NOT NULL AND public_settings <> ''"
);

$migrated = 0;
$skipped = 0;
$cleared = 0;

foreach ($rows as $row) {
    $settings = json_decode($row['public_settings'], true);
    if (!is_array($settings)) {
        $skipped++;
        continue;
    }

    $plain = $settings['password'] ?? null;
    $hash = $settings['password_hash'] ?? null;

    // Already migrated and no leftover plaintext — nothing to do.
    if ($hash && !is_string($plain)) {
        $skipped++;
        continue;
    }

    if (is_string($plain) && $plain !== '') {
        // Promote plaintext to a hash. If a hash already exists we keep that
        // one (the user changed the password via the new endpoint) and just
        // strip the leftover plaintext field.
        if (!$hash) {
            $settings['password_hash'] = password_hash($plain, PASSWORD_DEFAULT);
            $migrated++;
        } else {
            $cleared++;
        }
        unset($settings['password']);
        $db->update('notes', ['public_settings' => json_encode($settings)], ['id' => $row['id']]);
    } elseif (array_key_exists('password', $settings)) {
        // Field exists but empty — just remove it.
        unset($settings['password']);
        $db->update('notes', ['public_settings' => json_encode($settings)], ['id' => $row['id']]);
        $cleared++;
    } else {
        $skipped++;
    }
}

echo "Public-note password migration finished.\n";
echo "  migrated (plaintext -> hash): {$migrated}\n";
echo "  cleared (leftover plaintext): {$cleared}\n";
echo "  skipped:                      {$skipped}\n";
