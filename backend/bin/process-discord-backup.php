#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Discord Backup Processor CLI Script
 *
 * Processes Discord backups in the background.
 * Usage: php bin/process-discord-backup.php <backup_id> <encrypted_token> [bot]
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;
use App\Modules\Discord\Repositories\DiscordBackupRepository;
use App\Modules\Discord\Services\DiscordApiService;
use App\Modules\Discord\Services\DiscordBotApiService;

// Load environment
define('BASE_PATH', dirname(__DIR__));

if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
}

// Parse arguments
if ($argc < 3) {
    fwrite(STDERR, "Usage: php process-discord-backup.php <backup_id> <encrypted_token> [bot]\n");
    exit(1);
}

$backupId = $argv[1];
$encryptedToken = $argv[2];
$isBot = isset($argv[3]) && $argv[3] === 'bot';

// Validate backup ID (UUID format)
if (!preg_match('/^[a-f0-9\-]{36}$/i', $backupId)) {
    fwrite(STDERR, "Invalid backup ID format\n");
    exit(1);
}

// Database connection using Doctrine DBAL
$connectionParams = [
    'dbname' => $_ENV['DB_DATABASE'] ?? 'kyuubisoft',
    'user' => $_ENV['DB_USERNAME'] ?? 'kyuubisoft',
    'password' => $_ENV['DB_PASSWORD'] ?? 'secret',
    'host' => $_ENV['DB_HOST'] ?? 'mysql',
    'port' => $_ENV['DB_PORT'] ?? 3306,
    'driver' => 'pdo_mysql',
    'charset' => 'utf8mb4',
];

try {
    $db = DriverManager::getConnection($connectionParams);
    $db->connect();
} catch (\Exception $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// Decrypt token
$appKey = $_ENV['APP_KEY'] ?? 'default-key-change-me';
$encryptionKey = hash('sha256', $appKey, true);

function decrypt(string $encrypted, string $key): ?string
{
    $data = base64_decode($encrypted);
    if ($data === false || strlen($data) < 17) {
        return null;
    }

    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $ivLength);
    $encryptedData = substr($data, $ivLength);

    $decrypted = openssl_decrypt($encryptedData, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
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
$backupRepository = new DiscordBackupRepository($db);

// Use appropriate API service based on source type
if ($isBot) {
    $discordApi = new DiscordBotApiService();
} else {
    $discordApi = new DiscordApiService();
}

echo "Processing backup ID: $backupId (Source: " . ($isBot ? 'Bot' : 'User Token') . ")\n";

try {
    if ($isBot) {
        processBotBackup($backupId, $token, $backupRepository, $discordApi, $storagePath, $db);
    } else {
        processUserBackup($backupId, $token, $backupRepository, $discordApi, $storagePath);
    }
    echo "Backup completed successfully\n";
} catch (\Exception $e) {
    fwrite(STDERR, "Backup failed: " . $e->getMessage() . "\n");
    exit(1);
}

/**
 * Process user token backup (channel messages)
 */
function processUserBackup(
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

        processChannelMessages($backupId, $token, $discordChannelId, $backup, $backupRepository, $discordApi, $storagePath);

        $backupRepository->updateStatus($backupId, 'completed');

    } catch (\Exception $e) {
        error_log('Discord backup failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        $backupRepository->updateStatus($backupId, 'failed', $e->getMessage());
        throw $e;
    }
}

/**
 * Process bot backup (full server or channel)
 */
function processBotBackup(
    string $backupId,
    string $token,
    DiscordBackupRepository $backupRepository,
    DiscordBotApiService $discordApi,
    string $storagePath,
    $db
): void {
    $backup = $backupRepository->findById($backupId);
    if (!$backup || !in_array($backup['status'], ['pending', 'running'])) {
        throw new \RuntimeException('Backup not found or not in valid state');
    }

    $backupRepository->updateStatus($backupId, 'running');

    try {
        $type = $backup['type'] ?? 'channel';
        $discordGuildId = $backup['discord_guild_id'];

        if ($type === 'full_server' && $discordGuildId) {
            // Full server backup
            processFullServerBackup($backupId, $token, $discordGuildId, $backup, $backupRepository, $discordApi, $storagePath, $db);
        } elseif ($backup['discord_channel_id']) {
            // Channel backup using bot
            processChannelMessagesWithBot($backupId, $token, $backup['discord_channel_id'], $backup, $backupRepository, $discordApi, $storagePath);
        } else {
            throw new \RuntimeException('No channel or guild specified for backup');
        }

        $backupRepository->updateStatus($backupId, 'completed');

    } catch (\Exception $e) {
        error_log('Discord bot backup failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        $backupRepository->updateStatus($backupId, 'failed', $e->getMessage());
        throw $e;
    }
}

/**
 * Full server backup (settings, roles, emojis, all channels)
 */
function processFullServerBackup(
    string $backupId,
    string $token,
    string $discordGuildId,
    array $backup,
    DiscordBackupRepository $backupRepository,
    DiscordBotApiService $discordApi,
    string $storagePath,
    $db
): void {
    $mediaDir = $storagePath . '/discord/media/' . $backupId;
    ensureDirectory($mediaDir);

    $totalMessages = 0;
    $totalMedia = 0;
    $totalMediaSize = 0;

    // Step 1: Backup server settings
    $backupRepository->updateProgress($backupId, 0, 0, "Backing up server settings...");
    echo "Backing up server settings...\n";

    try {
        $guild = $discordApi->getGuild($token, $discordGuildId);
        saveServerSettings($db, $backupId, $guild, $discordApi, $storagePath);
    } catch (\Exception $e) {
        echo "Warning: Could not backup server settings: " . $e->getMessage() . "\n";
    }

    // Step 2: Backup roles
    $backupRepository->updateProgress($backupId, 0, 0, "Backing up roles...");
    echo "Backing up roles...\n";

    try {
        $roles = $discordApi->getGuildRoles($token, $discordGuildId);
        saveRoles($db, $backupId, $roles);
        echo "Saved " . count($roles) . " roles\n";
    } catch (\Exception $e) {
        echo "Warning: Could not backup roles: " . $e->getMessage() . "\n";
    }

    // Step 3: Backup emojis
    $backupRepository->updateProgress($backupId, 0, 0, "Backing up emojis...");
    echo "Backing up emojis...\n";

    try {
        $emojis = $discordApi->getGuildEmojis($token, $discordGuildId);
        $emojiDir = $storagePath . '/discord/emojis/' . $backupId;
        ensureDirectory($emojiDir);
        saveEmojis($db, $backupId, $emojis, $discordApi, $emojiDir);
        echo "Saved " . count($emojis) . " emojis\n";
    } catch (\Exception $e) {
        echo "Warning: Could not backup emojis: " . $e->getMessage() . "\n";
    }

    // Step 4: Get all text channels
    $backupRepository->updateProgress($backupId, 0, 0, "Getting channel list...");
    echo "Getting channel list...\n";

    $channels = $discordApi->getGuildChannels($token, $discordGuildId);
    $textChannels = array_filter($channels, function ($ch) {
        return in_array($ch['type'], [0, 5, 15]); // text, announcement, forum
    });

    echo "Found " . count($textChannels) . " text channels\n";

    // Step 5: Backup each channel
    $channelIndex = 0;
    $totalChannels = count($textChannels);

    foreach ($textChannels as $channel) {
        $channelIndex++;
        $channelName = $channel['name'] ?? 'unknown';
        $channelId = $channel['id'];

        $backupRepository->updateProgress(
            $backupId,
            $totalMessages,
            $totalMessages,
            "Backing up channel {$channelIndex}/{$totalChannels}: #{$channelName}..."
        );
        echo "Backing up channel #{$channelName} ({$channelIndex}/{$totalChannels})...\n";

        try {
            $result = processChannelMessagesWithBot(
                $backupId,
                $token,
                $channelId,
                $backup,
                $backupRepository,
                $discordApi,
                $storagePath,
                false // Don't update status
            );

            $totalMessages += $result['messages'];
            $totalMedia += $result['media'];
            $totalMediaSize += $result['media_size'];

        } catch (\Exception $e) {
            echo "Warning: Could not backup channel #{$channelName}: " . $e->getMessage() . "\n";
        }
    }

    // Update final results
    $backupRepository->updateResults($backupId, [
        'messages_total' => $totalMessages,
        'messages_processed' => $totalMessages,
        'media_count' => $totalMedia,
        'media_size' => $totalMediaSize,
    ]);

    echo "Server backup completed: {$totalMessages} messages, {$totalMedia} media files from {$totalChannels} channels\n";
}

/**
 * Process channel messages using bot API
 */
function processChannelMessagesWithBot(
    string $backupId,
    string $token,
    string $discordChannelId,
    array $backup,
    DiscordBackupRepository $backupRepository,
    DiscordBotApiService $discordApi,
    string $storagePath,
    bool $updateStatus = true
): array {
    $dateFrom = $backup['date_from'] ? strtotime($backup['date_from']) : null;
    $dateTo = $backup['date_to'] ? strtotime($backup['date_to']) : null;

    $messageCount = 0;
    $mediaCount = 0;
    $mediaSize = 0;
    $backupMode = $backup['backup_mode'] ?? 'full';

    $mediaDir = $storagePath . '/discord/media/' . $backupId;
    $needsMediaDir = $backupMode === 'media_only' || ($backupMode === 'full' && $backup['include_media']);

    if ($needsMediaDir) {
        ensureDirectory($mediaDir);
    }

    // Get messages using bot API
    foreach ($discordApi->getAllChannelMessages($token, $discordChannelId) as $message) {
        $messageTime = strtotime($message['timestamp']);

        if ($dateFrom && $messageTime < $dateFrom) continue;
        if ($dateTo && $messageTime > $dateTo) continue;

        if ($backupMode === 'full') {
            $backupRepository->insertMessage($backupId, $message);
        }
        $messageCount++;

        if ($messageCount % 100 === 0 && $updateStatus) {
            $backupRepository->updateProgress($backupId, $messageCount, $messageCount, "Processed {$messageCount} messages...");
        }

        // Download media
        if (($backupMode === 'full' && $backup['include_media']) || $backupMode === 'media_only') {
            if (!empty($message['attachments'])) {
                foreach ($message['attachments'] as $attachment) {
                    $filename = $attachment['id'] . '_' . $attachment['filename'];
                    $localPath = $mediaDir . '/' . $filename;

                    if ($discordApi->downloadFile($attachment['url'], $localPath)) {
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

        // Links only mode
        if ($backupMode === 'links_only' && !empty($message['content'])) {
            if (preg_match('/https?:\/\/[^\s]+/i', $message['content'])) {
                $backupRepository->insertMessage($backupId, $message);
            }
        }
    }

    if ($updateStatus) {
        $backupRepository->updateResults($backupId, [
            'messages_total' => $messageCount,
            'messages_processed' => $messageCount,
            'media_count' => $mediaCount,
            'media_size' => $mediaSize,
        ]);
    }

    return [
        'messages' => $messageCount,
        'media' => $mediaCount,
        'media_size' => $mediaSize,
    ];
}

/**
 * Process channel messages using user token API
 */
function processChannelMessages(
    string $backupId,
    string $token,
    string $discordChannelId,
    array $backup,
    DiscordBackupRepository $backupRepository,
    DiscordApiService $discordApi,
    string $storagePath
): void {
    $dateFrom = $backup['date_from'] ? strtotime($backup['date_from']) : null;
    $dateTo = $backup['date_to'] ? strtotime($backup['date_to']) : null;

    $messageCount = 0;
    $mediaCount = 0;
    $mediaSize = 0;
    $skippedByDate = 0;
    $backupMode = $backup['backup_mode'] ?? 'full';

    $mediaDir = $storagePath . '/discord/media/' . $backupId;
    $needsMediaDir = $backupMode === 'media_only' || ($backupMode === 'full' && $backup['include_media']);

    if ($needsMediaDir) {
        ensureDirectory($mediaDir);
    }

    $backupRepository->updateProgress($backupId, 0, 0, "Fetching messages from Discord...");

    echo "Starting message fetch...\n";

    foreach ($discordApi->getAllChannelMessages($token, $discordChannelId) as $message) {
        $messageTime = strtotime($message['timestamp']);

        if ($dateFrom && $messageTime < $dateFrom) {
            $skippedByDate++;
            continue;
        }

        if ($dateTo && $messageTime > $dateTo) {
            $skippedByDate++;
            continue;
        }

        if ($backupMode === 'full') {
            $backupRepository->insertMessage($backupId, $message);
        }
        $messageCount++;

        if ($messageCount % 50 === 0) {
            $backupRepository->updateProgress($backupId, $messageCount, $messageCount, "Processed {$messageCount} messages...");
            echo "Processed $messageCount messages...\n";
        }

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

        if ($backupMode === 'links_only' && !empty($message['content'])) {
            if (preg_match('/https?:\/\/[^\s]+/i', $message['content'])) {
                $backupRepository->insertMessage($backupId, $message);
            }
        }
    }

    $backupRepository->updateResults($backupId, [
        'messages_total' => $messageCount,
        'messages_processed' => $messageCount,
        'media_count' => $mediaCount,
        'media_size' => $mediaSize,
    ]);

    echo "Backup completed: $messageCount messages, $mediaCount media files\n";
}

/**
 * Save server settings to database
 */
function saveServerSettings($db, string $backupId, array $guild, DiscordBotApiService $discordApi, string $storagePath): void
{
    $iconLocalPath = null;
    $splashLocalPath = null;
    $bannerLocalPath = null;

    $assetDir = $storagePath . '/discord/assets/' . $backupId;
    ensureDirectory($assetDir);

    // Download icon
    if (!empty($guild['icon'])) {
        $iconUrl = $discordApi->getGuildIconUrl($guild['id'], $guild['icon'], 512);
        $iconLocalPath = $assetDir . '/icon.' . (str_starts_with($guild['icon'], 'a_') ? 'gif' : 'png');
        $discordApi->downloadFile($iconUrl, $iconLocalPath);
    }

    // Download splash
    if (!empty($guild['splash'])) {
        $splashUrl = "https://cdn.discordapp.com/splashes/{$guild['id']}/{$guild['splash']}.png?size=1024";
        $splashLocalPath = $assetDir . '/splash.png';
        $discordApi->downloadFile($splashUrl, $splashLocalPath);
    }

    // Download banner
    if (!empty($guild['banner'])) {
        $bannerUrl = "https://cdn.discordapp.com/banners/{$guild['id']}/{$guild['banner']}.png?size=1024";
        $bannerLocalPath = $assetDir . '/banner.png';
        $discordApi->downloadFile($bannerUrl, $bannerLocalPath);
    }

    $db->insert('discord_server_settings', [
        'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'backup_id' => $backupId,
        'name' => $guild['name'],
        'description' => $guild['description'] ?? null,
        'icon_hash' => $guild['icon'] ?? null,
        'icon_local_path' => $iconLocalPath,
        'splash_hash' => $guild['splash'] ?? null,
        'splash_local_path' => $splashLocalPath,
        'banner_hash' => $guild['banner'] ?? null,
        'banner_local_path' => $bannerLocalPath,
        'features' => json_encode($guild['features'] ?? []),
        'verification_level' => $guild['verification_level'] ?? null,
        'default_notifications' => $guild['default_message_notifications'] ?? null,
        'explicit_content_filter' => $guild['explicit_content_filter'] ?? null,
        'afk_channel_id' => $guild['afk_channel_id'] ?? null,
        'afk_timeout' => $guild['afk_timeout'] ?? null,
        'system_channel_id' => $guild['system_channel_id'] ?? null,
        'rules_channel_id' => $guild['rules_channel_id'] ?? null,
        'raw_data' => json_encode($guild),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

/**
 * Save roles to database
 */
function saveRoles($db, string $backupId, array $roles): void
{
    foreach ($roles as $role) {
        $db->insert('discord_roles', [
            'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'backup_id' => $backupId,
            'discord_role_id' => $role['id'],
            'name' => $role['name'],
            'color' => $role['color'] ?? 0,
            'hoist' => ($role['hoist'] ?? false) ? 1 : 0,
            'icon' => $role['icon'] ?? null,
            'unicode_emoji' => $role['unicode_emoji'] ?? null,
            'position' => $role['position'] ?? 0,
            'permissions' => $role['permissions'] ?? 0,
            'managed' => ($role['managed'] ?? false) ? 1 : 0,
            'mentionable' => ($role['mentionable'] ?? false) ? 1 : 0,
            'raw_data' => json_encode($role),
        ]);
    }
}

/**
 * Save emojis to database and download them
 */
function saveEmojis($db, string $backupId, array $emojis, DiscordBotApiService $discordApi, string $emojiDir): void
{
    foreach ($emojis as $emoji) {
        $animated = $emoji['animated'] ?? false;
        $emojiUrl = $discordApi->getEmojiUrl($emoji['id'], $animated);
        $localPath = $emojiDir . '/' . $emoji['id'] . '.' . ($animated ? 'gif' : 'png');

        $discordApi->downloadFile($emojiUrl, $localPath);

        $db->insert('discord_emojis', [
            'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'backup_id' => $backupId,
            'discord_emoji_id' => $emoji['id'],
            'name' => $emoji['name'],
            'animated' => $animated ? 1 : 0,
            'available' => ($emoji['available'] ?? true) ? 1 : 0,
            'require_colons' => ($emoji['require_colons'] ?? true) ? 1 : 0,
            'managed' => ($emoji['managed'] ?? false) ? 1 : 0,
            'original_url' => $emojiUrl,
            'local_path' => $localPath,
        ]);
    }
}

/**
 * Ensure directory exists
 */
function ensureDirectory(string $path): void
{
    if (!is_dir($path)) {
        if (!@mkdir($path, 0755, true)) {
            throw new \RuntimeException('Cannot create directory: ' . $path);
        }
    }
}
