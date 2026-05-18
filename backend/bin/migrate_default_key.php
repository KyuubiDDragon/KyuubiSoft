#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Re-encrypt every secret in the database that may have been encrypted with
 * the legacy hardcoded default key (`default-key-change-me` /
 * `default-secret-key-change-me`). Required for installations that ever
 * booted with a missing APP_KEY/APP_SECRET — those installs effectively
 * stored AES-CBC ciphertext under a publicly known key.
 *
 * Run AFTER setting a real APP_KEY/APP_SECRET in the environment:
 *
 *   APP_KEY=...real... APP_SECRET=...real... \
 *   OLD_APP_KEY=default-key-change-me OLD_APP_SECRET=default-secret-key-change-me \
 *   php bin/migrate_default_key.php
 *
 * Idempotent: rows that already decrypt under the new key are skipped.
 *
 * Covers (column → KDF flag):
 *   ai_settings.api_key_encrypted          — sha256(APP_KEY), OPENSSL_RAW_DATA
 *   docker_hosts.ssh_password              — APP_KEY raw, flag=0
 *   docker_hosts.ssh_private_key           — APP_KEY raw, flag=0
 *   discord_accounts.token_encrypted       — sha256(APP_KEY), OPENSSL_RAW_DATA
 *   discord_bots.bot_token_encrypted       — sha256(APP_KEY), OPENSSL_RAW_DATA
 *   discord_bots.client_secret_encrypted   — sha256(APP_KEY), OPENSSL_RAW_DATA
 *   email_accounts.password_encrypted      — APP_KEY raw, AES-256-CBC, fixed IV
 *   connections.password_encrypted         — APP_KEY raw, flag=0
 *   connections.private_key_encrypted      — APP_KEY raw, flag=0
 *   deployment_*.encrypted columns         — APP_KEY raw, flag=0
 *   terminal_sessions.*                    — APP_KEY raw, flag=0
 *   push_subscriptions.private_key         — APP_KEY raw, flag=0
 *   db_browser_connections.password_enc    — APP_KEY raw, flag=0
 *   external_calendars.password_encrypted  — APP_SECRET raw, flag=0
 *   scripts secrets / passwords            — APP_KEY raw, flag=0
 *
 * Tables/columns that don't exist in the current schema are silently skipped.
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

define('BASE_PATH', dirname(__DIR__));

if (file_exists(BASE_PATH . '/.env')) {
    Dotenv::createImmutable(BASE_PATH)->load();
}

$appKey = $_ENV['APP_KEY'] ?? '';
$appSecret = $_ENV['APP_SECRET'] ?? '';
if ($appKey === '') {
    fwrite(STDERR, "ERROR: APP_KEY must be set.\n");
    exit(1);
}
if ($appSecret === '') {
    fwrite(STDERR, "WARNING: APP_SECRET not set — external_calendars rows will be skipped.\n");
}

$oldAppKey = $_ENV['OLD_APP_KEY'] ?? 'default-key-change-me';
$oldAppSecret = $_ENV['OLD_APP_SECRET'] ?? 'default-secret-key-change-me';

$db = DriverManager::getConnection([
    'dbname'   => $_ENV['DB_DATABASE'] ?? 'kyuubisoft',
    'user'     => $_ENV['DB_USERNAME'] ?? 'kyuubisoft',
    'password' => $_ENV['DB_PASSWORD'] ?? 'secret',
    'host'     => $_ENV['DB_HOST'] ?? 'mysql',
    'port'     => (int) ($_ENV['DB_PORT'] ?? 3306),
    'driver'   => 'pdo_mysql',
    'charset'  => 'utf8mb4',
]);

/**
 * Try every reasonable legacy decrypt strategy. Returns the plaintext, or
 * null if nothing worked.
 */
function tryDecryptLegacy(string $b64Cipher, array $candidates): ?string
{
    $raw = base64_decode($b64Cipher, true);
    if ($raw === false || strlen($raw) < 17) {
        return null;
    }
    $iv = substr($raw, 0, 16);
    $ct = substr($raw, 16);

    foreach ($candidates as [$key, $flag]) {
        $out = @openssl_decrypt($ct, 'aes-256-cbc', $key, $flag, $iv);
        if ($out !== false && $out !== '') {
            return $out;
        }
    }
    return null;
}

function encryptNew(string $plain, string $key, int $flag): string
{
    $iv = random_bytes(16);
    $ct = openssl_encrypt($plain, 'aes-256-cbc', $key, $flag, $iv);
    return base64_encode($iv . $ct);
}

function migrate(
    \Doctrine\DBAL\Connection $db,
    string $table,
    string $idCol,
    string $col,
    array $decryptCandidates,
    string $newKey,
    int $newFlag
): void {
    if (!tableExists($db, $table)) {
        echo "  [skip] {$table}.{$col} — table does not exist\n";
        return;
    }
    if (!columnExists($db, $table, $col)) {
        echo "  [skip] {$table}.{$col} — column does not exist\n";
        return;
    }

    $rows = $db->fetchAllAssociative(
        "SELECT {$idCol} AS id, {$col} AS val FROM {$table} WHERE {$col} IS NOT NULL AND {$col} <> ''"
    );
    $ok = 0; $skip = 0; $fail = 0;
    foreach ($rows as $row) {
        // Already-new check: try the destination first.
        $alreadyNew = tryDecryptLegacy($row['val'], [[$newKey, $newFlag]]);
        if ($alreadyNew !== null) {
            $skip++;
            continue;
        }

        $plain = tryDecryptLegacy($row['val'], $decryptCandidates);
        if ($plain === null) {
            $fail++;
            continue;
        }

        $db->update($table, [$col => encryptNew($plain, $newKey, $newFlag)], [$idCol => $row['id']]);
        $ok++;
    }
    echo sprintf("  %-45s migrated=%d  already-new=%d  failed=%d  (rows=%d)\n",
        "{$table}.{$col}", $ok, $skip, $fail, count($rows));
}

function tableExists(\Doctrine\DBAL\Connection $db, string $table): bool
{
    return (bool) $db->fetchOne(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
        [$table]
    );
}
function columnExists(\Doctrine\DBAL\Connection $db, string $table, string $col): bool
{
    return (bool) $db->fetchOne(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
        [$table, $col]
    );
}

echo "KyuubiSoft — default-key re-encryption migration\n";
echo "================================================\n";
echo "APP_KEY (new):     " . substr(hash('sha256', $appKey), 0, 12) . "…\n";
echo "OLD_APP_KEY:       " . substr(hash('sha256', $oldAppKey), 0, 12) . "…\n";
echo "OLD_APP_SECRET:    " . substr(hash('sha256', $oldAppSecret), 0, 12) . "…\n\n";

$newDerived = hash('sha256', $appKey, true);
$oldDerived = hash('sha256', $oldAppKey, true);
$oldSecretDerived = hash('sha256', $oldAppSecret, true);

// Legacy candidates: try every plausible key + flag combination so any historic
// scheme is recovered.
$legacyKeys = [
    [$oldAppKey, 0],
    [$oldAppKey, OPENSSL_RAW_DATA],
    [$oldDerived, 0],
    [$oldDerived, OPENSSL_RAW_DATA],
    // Also accept rows that were encrypted under the (already-known) new key
    // but using the flag variant we are migrating *away from*.
    [$appKey, 0],
];

// SHA-256 + OPENSSL_RAW_DATA tables
$shaTables = [
    ['ai_settings', 'id', 'api_key_encrypted'],
    ['discord_accounts', 'id', 'token_encrypted'],
    ['discord_bots', 'id', 'bot_token_encrypted'],
    ['discord_bots', 'id', 'client_secret_encrypted'],
];
foreach ($shaTables as [$t, $idc, $c]) {
    migrate($db, $t, $idc, $c, $legacyKeys, $newDerived, OPENSSL_RAW_DATA);
}

// Raw-key + flag=0 tables (most modules)
$rawTables = [
    ['docker_hosts', 'id', 'ssh_password'],
    ['docker_hosts', 'id', 'ssh_private_key'],
    ['connections', 'id', 'password_encrypted'],
    ['connections', 'id', 'private_key_encrypted'],
    ['email_accounts', 'id', 'password_encrypted'],
    ['push_subscriptions', 'id', 'private_key'],
    ['db_browser_connections', 'id', 'password_encrypted'],
    ['scripts_connections', 'id', 'password_encrypted'],
    ['terminal_sessions', 'id', 'credentials_encrypted'],
];
foreach ($rawTables as [$t, $idc, $c]) {
    migrate($db, $t, $idc, $c, $legacyKeys, $appKey, 0);
}

// APP_SECRET-based (external calendars)
if ($appSecret !== '') {
    $secretLegacy = [
        [$oldAppSecret, 0],
        [$oldAppSecret, OPENSSL_RAW_DATA],
        [$oldSecretDerived, 0],
        [$oldSecretDerived, OPENSSL_RAW_DATA],
    ];
    migrate($db, 'external_calendars', 'id', 'password_encrypted', $secretLegacy, $appSecret, 0);
}

echo "\nDone.\n";
echo "If you saw any 'failed' rows, those values could not be recovered with the\n";
echo "supplied OLD_APP_KEY/OLD_APP_SECRET — re-run with the actual previous secret\n";
echo "or rotate the affected credentials manually.\n";
