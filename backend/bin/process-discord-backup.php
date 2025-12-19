#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Discord Backup Processor CLI Script
 *
 * Processes Discord backups in the background.
 * Usage: php bin/process-discord-backup.php <backup_id> <encrypted_token>
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Modules\Discord\Repositories\DiscordBackupRepository;
use App\Modules\Discord\Services\DiscordApiService;

// Load environment
define('BASE_PATH', dirname(__DIR__));

if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
}

// Parse arguments
if ($argc < 3) {
    fwrite(STDERR, "Usage: php process-discord-backup.php <backup_id> <encrypted_token>\n");
    exit(1);
}

$backupId = $argv[1];
$encryptedToken = $argv[2];

// Validate backup ID (UUID format)
if (!preg_match('/^[a-f0-9\-]{36}$/i', $backupId)) {
    fwrite(STDERR, "Invalid backup ID format\n");
    exit(1);
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
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// Decrypt token
$appKey = $_ENV['APP_KEY'] ?? 'default-key-change-me';
$encryptionKey = hash('sha256', $appKey, true);

function decrypt(string $encrypted, string $key): ?string
{
    $data = base64_decode($encrypted);
    if ($data === false) {
        return null;
    }

    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);

    $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted !== false ? $decrypted : null;
}

$token = decrypt($encryptedToken, $encryptionKey);
if (!$token) {
    fwrite(STDERR, "Failed to decrypt token\n");
    exit(1);
}

// Storage path
$storagePath = $_ENV['STORAGE_PATH'] ?? '/var/www/storage';

// Initialize services
$backupRepository = new DiscordBackupRepository($pdo);
$discordApi = new DiscordApiService();

echo "Processing backup ID: $backupId\n";

try {
    processBackup($backupId, $token, $backupRepository, $discordApi, $storagePath);
    echo "Backup completed successfully\n";
} catch (\Exception $e) {
    fwrite(STDERR, "Backup failed: " . $e->getMessage() . "\n");
    exit(1);
}

function processBackup(
    string $backupId,
    string $token,
    DiscordBackupRepository $backupRepository,
    DiscordApiService $discordApi,
    string $storagePath
): void {
    $backup = $backupRepository->findById($backupId);
    if (!$backup || !in_array($backup['status'], ['pending', 'running'])) {
        throw new \RuntimeException('Backup not found or not in valid state');
    }

    $backupRepository->updateStatus($backupId, 'running');

    try {
        $discordChannelId = $backup['discord_channel_id'];
        if (!$discordChannelId) {
            throw new \RuntimeException('No channel specified for backup');
        }

        // Parse date filters
        $dateFrom = $backup['date_from'] ? strtotime($backup['date_from']) : null;
        $dateTo = $backup['date_to'] ? strtotime($backup['date_to']) : null;

        $messageCount = 0;
        $mediaCount = 0;
        $mediaSize = 0;
        $skippedByDate = 0;
        $backupMode = $backup['backup_mode'] ?? 'full';

        // Create media directory if needed
        $mediaDir = $storagePath . '/discord/media/' . $backupId;
        $needsMediaDir = $backupMode === 'media_only' || ($backupMode === 'full' && $backup['include_media']);

        if ($needsMediaDir) {
            if (!is_dir($storagePath)) {
                if (!@mkdir($storagePath, 0755, true)) {
                    throw new \RuntimeException('Cannot create storage directory: ' . $storagePath);
                }
            }
            if (!is_dir($mediaDir)) {
                if (!@mkdir($mediaDir, 0755, true)) {
                    throw new \RuntimeException('Cannot create media directory: ' . $mediaDir);
                }
            }
        }
        $linksCount = 0;

        $backupRepository->updateProgress($backupId, 0, 0, "Fetching messages from Discord...");

        echo "Starting message fetch...\n";

        // Get messages
        foreach ($discordApi->getAllChannelMessages($token, $discordChannelId) as $message) {
            // Apply date filter
            $messageTime = strtotime($message['timestamp']);

            if ($dateFrom && $messageTime < $dateFrom) {
                $skippedByDate++;
                continue;
            }

            if ($dateTo && $messageTime > $dateTo) {
                $skippedByDate++;
                continue;
            }

            // Store message only for full backup mode
            if ($backupMode === 'full') {
                $backupRepository->insertMessage($backupId, $message);
            }
            $messageCount++;

            // Update progress every 50 messages
            if ($messageCount % 50 === 0) {
                $backupRepository->updateProgress($backupId, $messageCount, $messageCount, "Processed {$messageCount} messages...");
                echo "Processed $messageCount messages...\n";
            }

            // Download media for full or media_only mode
            if (($backupMode === 'full' && $backup['include_media']) || $backupMode === 'media_only') {
                if (!empty($message['attachments'])) {
                    foreach ($message['attachments'] as $attachment) {
                        $filename = $attachment['id'] . '_' . $attachment['filename'];
                        $localPath = $mediaDir . '/' . $filename;

                        if ($discordApi->downloadAttachment($attachment['url'], $localPath)) {
                            $backupRepository->insertMedia($backupId, [
                                'message_id' => $message['id'],
                                'attachment_id' => $attachment['id'],
                                'url' => $attachment['url'],
                                'local_path' => $localPath,
                                'filename' => $attachment['filename'],
                                'size' => $attachment['size'] ?? null,
                                'content_type' => $attachment['content_type'] ?? null,
                                'width' => $attachment['width'] ?? null,
                                'height' => $attachment['height'] ?? null,
                                'spoiler' => str_starts_with($attachment['filename'], 'SPOILER_'),
                            ]);

                            $mediaCount++;
                            $mediaSize += $attachment['size'] ?? 0;
                        }
                    }
                }
            }

            // For links_only mode
            if ($backupMode === 'links_only' && !empty($message['content'])) {
                if (preg_match('/https?:\/\/[^\s]+/i', $message['content'])) {
                    $backupRepository->insertMessage($backupId, $message);
                    $linksCount++;
                }
            }
        }

        $backupRepository->updateResults($backupId, [
            'messages_total' => $messageCount,
            'messages_processed' => $messageCount,
            'media_count' => $mediaCount,
            'media_size' => $mediaSize,
        ]);

        $backupRepository->updateStatus($backupId, 'completed');

        echo "Backup completed: $messageCount messages, $mediaCount media files\n";

    } catch (\Exception $e) {
        error_log('Discord backup failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        $backupRepository->updateStatus($backupId, 'failed', $e->getMessage());
        throw $e;
    }
}
