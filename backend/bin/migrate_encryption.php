#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Encryption Migration Script
 *
 * Re-encrypts all AI provider API keys from the legacy format (raw APP_KEY, flag=0)
 * to the new secure format (SHA-256 derived key, OPENSSL_RAW_DATA).
 *
 * Run once after deploying the AES key-derivation fix:
 *   php bin/migrate_encryption.php
 *
 * Safe to run multiple times – values already using the new key are left unchanged.
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

define('BASE_PATH', dirname(__DIR__));

if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
}

$appKey  = $_ENV['APP_KEY'] ?? 'default-key-change-me';
$legacyKey  = $appKey;                                    // old: raw string, flag=0
$derivedKey = hash('sha256', $appKey, true);              // new: 32-byte binary, OPENSSL_RAW_DATA

$connectionParams = [
    'dbname'   => $_ENV['DB_DATABASE'] ?? 'kyuubisoft',
    'user'     => $_ENV['DB_USERNAME'] ?? 'kyuubisoft',
    'password' => $_ENV['DB_PASSWORD'] ?? 'secret',
    'host'     => $_ENV['DB_HOST'] ?? 'mysql',
    'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
    'driver'   => 'pdo_mysql',
    'charset'  => 'utf8mb4',
];

$db = DriverManager::getConnection($connectionParams);

echo "KyuubiSoft – API Key Encryption Migration\n";
echo "=========================================\n\n";

$rows = $db->fetchAllAssociative('SELECT id, user_id, api_key_encrypted FROM ai_settings WHERE api_key_encrypted IS NOT NULL');

if (empty($rows)) {
    echo "No AI settings with encrypted API keys found. Nothing to migrate.\n";
    exit(0);
}

echo "Found " . count($rows) . " row(s) to process.\n\n";

$migrated = 0;
$skipped  = 0;
$failed   = 0;

foreach ($rows as $row) {
    $id        = $row['id'];
    $encrypted = $row['api_key_encrypted'];

    $decoded    = base64_decode($encrypted);
    $iv         = substr($decoded, 0, 16);
    $ciphertext = substr($decoded, 16);

    // Step 1: Try to decrypt with the NEW derived key.
    // If this succeeds, the row was already migrated – skip it.
    $withNew = openssl_decrypt($ciphertext, 'aes-256-cbc', $derivedKey, OPENSSL_RAW_DATA, $iv);
    if ($withNew !== false) {
        echo "  [SKIP] Row {$id} (user {$row['user_id']}) – already uses new key.\n";
        $skipped++;
        continue;
    }

    // Step 2: Try to decrypt with the LEGACY raw key.
    $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $legacyKey, 0, $iv);
    if ($plaintext === false) {
        echo "  [FAIL] Row {$id} (user {$row['user_id']}) – could not decrypt with either key!\n";
        $failed++;
        continue;
    }

    // Step 3: Re-encrypt with the new derived key.
    $newIv        = random_bytes(16);
    $newEncrypted = openssl_encrypt($plaintext, 'aes-256-cbc', $derivedKey, OPENSSL_RAW_DATA, $newIv);
    $newStored    = base64_encode($newIv . $newEncrypted);

    $db->update('ai_settings', ['api_key_encrypted' => $newStored], ['id' => $id]);
    echo "  [OK]   Row {$id} (user {$row['user_id']}) – migrated successfully.\n";
    $migrated++;
}

echo "\nDone. Migrated: {$migrated} | Skipped (already new): {$skipped} | Failed: {$failed}\n";

if ($failed > 0) {
    echo "\nWARNING: {$failed} row(s) could not be decrypted. Check your APP_KEY.\n";
    exit(1);
}

exit(0);
