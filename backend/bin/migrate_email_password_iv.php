#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Re-encrypt email account passwords from the legacy fixed-IV scheme to the
 * new format (base64(random IV || ciphertext)).
 *
 * The previous implementation used `str_repeat('0', 16)` as the IV for every
 * row, which means identical passwords produced identical ciphertexts —
 * trivially observable from any DB dump and broken under IND-CPA.
 *
 * Idempotent: rows already in the new format are skipped.
 *
 * Run after deploying the EmailController fix:
 *   php bin/migrate_email_password_iv.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

define('BASE_PATH', dirname(__DIR__));

if (file_exists(BASE_PATH . '/.env')) {
    Dotenv::createImmutable(BASE_PATH)->load();
}

$key = $_ENV['APP_KEY'] ?? '';
if ($key === '') {
    fwrite(STDERR, "ERROR: APP_KEY must be set.\n");
    exit(1);
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
    "SELECT id, password_encrypted FROM email_accounts
     WHERE password_encrypted IS NOT NULL AND password_encrypted <> ''"
);

$migrated = 0;
$skipped = 0;
$failed = 0;

foreach ($rows as $row) {
    $encrypted = $row['password_encrypted'];

    // Skip if already in the new format (IV || cipher decrypts successfully).
    $raw = base64_decode($encrypted, true);
    if ($raw !== false && strlen($raw) > 16) {
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        if (@openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv) !== false) {
            $skipped++;
            continue;
        }
    }

    // Try legacy fixed-IV format.
    $plain = @openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, str_repeat('0', 16));
    if ($plain === false) {
        $failed++;
        continue;
    }

    $newIv = random_bytes(16);
    $newCipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $newIv);
    if ($newCipher === false) {
        $failed++;
        continue;
    }

    $db->update('email_accounts', [
        'password_encrypted' => base64_encode($newIv . $newCipher),
    ], ['id' => $row['id']]);

    $migrated++;
}

echo "Email password IV migration finished.\n";
echo "  migrated: {$migrated}\n";
echo "  skipped (already new): {$skipped}\n";
echo "  failed:   {$failed}\n";

if ($failed > 0) {
    fwrite(STDERR, "\nWARNING: {$failed} row(s) could not be decrypted with the configured APP_KEY.\n");
    fwrite(STDERR, "Verify APP_KEY matches the value used at encrypt time, then re-run.\n");
    exit(1);
}
