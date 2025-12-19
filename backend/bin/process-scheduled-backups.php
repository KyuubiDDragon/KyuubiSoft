#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Scheduled Discord Backup Processor
 *
 * Run this script via cron to process scheduled Discord bot backups.
 * Recommended: Every hour or every 15 minutes
 *
 * Usage: php bin/process-scheduled-backups.php
 * Cron:  0 * * * * php /path/to/bin/process-scheduled-backups.php >> /var/log/scheduled-backups.log 2>&1
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;
use App\Modules\Discord\Repositories\DiscordBotRepository;
use App\Modules\Discord\Repositories\DiscordBackupRepository;

// Load environment
define('BASE_PATH', dirname(__DIR__));

if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
}

echo "[" . date('Y-m-d H:i:s') . "] Starting scheduled backup processor\n";

// Database connection
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

$botRepository = new DiscordBotRepository($db);
$backupRepository = new DiscordBackupRepository($db);

// Get due schedules
$schedules = $botRepository->findDueSchedules();

if (empty($schedules)) {
    echo "[" . date('Y-m-d H:i:s') . "] No scheduled backups due\n";
    exit(0);
}

echo "[" . date('Y-m-d H:i:s') . "] Found " . count($schedules) . " due scheduled backup(s)\n";

$storagePath = $_ENV['STORAGE_PATH'] ?? '/var/www/storage';
$appKey = $_ENV['APP_KEY'] ?? 'default-key-change-me';

foreach ($schedules as $schedule) {
    $scheduleId = $schedule['id'];
    $botId = $schedule['bot_id'];
    $serverId = $schedule['bot_server_id'];
    $serverName = $schedule['server_name'] ?? 'Unknown Server';
    $guildId = $schedule['discord_guild_id'];
    $encryptedToken = $schedule['bot_token_encrypted'];

    echo "[" . date('Y-m-d H:i:s') . "] Processing schedule {$scheduleId} for server: {$serverName}\n";

    try {
        // Create backup record
        $backup = $backupRepository->create([
            'account_id' => null,
            'bot_id' => $botId,
            'server_id' => null,           // Not used for bot backups (references discord_servers)
            'bot_server_id' => $serverId,  // References discord_bot_servers
            'discord_guild_id' => $guildId,
            'discord_channel_id' => null,
            'target_name' => $serverName . ' (Scheduled)',
            'type' => 'full_server',
            'source_type' => 'bot',
            'backup_mode' => 'full',
            'include_media' => $schedule['include_media'] ?? true,
            'include_reactions' => true,
            'include_threads' => $schedule['include_threads'] ?? true,
            'include_embeds' => true,
        ]);

        $backupId = $backup['id'];
        $scriptPath = BASE_PATH . '/bin/process-discord-backup.php';
        $logPath = $storagePath . '/logs/backup-' . $backupId . '.log';

        // Run backup processor in background
        $cmd = sprintf(
            'nohup php %s %s %s bot > %s 2>&1 &',
            escapeshellarg($scriptPath),
            escapeshellarg($backupId),
            escapeshellarg($encryptedToken),
            escapeshellarg($logPath)
        );
        exec($cmd);

        // Calculate next run time
        $nextRun = calculateNextRun($schedule);

        // Update schedule
        $botRepository->updateSchedule($scheduleId, [
            'last_run_at' => date('Y-m-d H:i:s'),
            'next_run_at' => $nextRun,
            'last_backup_id' => $backupId,
        ]);

        // Update bot server last_backup_at
        $db->update('discord_bot_servers', [
            'last_backup_at' => date('Y-m-d H:i:s'),
        ], ['id' => $serverId]);

        echo "[" . date('Y-m-d H:i:s') . "] Started backup {$backupId} for {$serverName}, next run: {$nextRun}\n";

        // Apply retention policy
        applyRetentionPolicy($backupRepository, $botId, $guildId, $schedule['keep_last_n'] ?? 7, $storagePath);

    } catch (\Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] ERROR processing schedule {$scheduleId}: " . $e->getMessage() . "\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Scheduled backup processor completed\n";

/**
 * Calculate next run time based on schedule configuration
 */
function calculateNextRun(array $schedule): string
{
    $now = new DateTime();
    $timeOfDay = $schedule['time_of_day'] ?? '03:00:00';

    switch ($schedule['interval_type']) {
        case 'daily':
            $next = new DateTime('tomorrow ' . $timeOfDay);
            break;

        case 'weekly':
            $dayOfWeek = (int) ($schedule['day_of_week'] ?? 0);
            $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $dayName = $days[$dayOfWeek] ?? 'Sunday';
            $next = new DateTime("next {$dayName} " . $timeOfDay);
            break;

        case 'monthly':
            $dayOfMonth = (int) ($schedule['day_of_month'] ?? 1);
            $next = new DateTime('first day of next month ' . $timeOfDay);
            if ($dayOfMonth > 1) {
                $next->modify('+' . ($dayOfMonth - 1) . ' days');
            }
            break;

        default:
            $next = new DateTime('+1 week ' . $timeOfDay);
    }

    return $next->format('Y-m-d H:i:s');
}

/**
 * Apply retention policy - delete old backups
 */
function applyRetentionPolicy(
    DiscordBackupRepository $backupRepository,
    string $botId,
    string $guildId,
    int $keepLastN,
    string $storagePath
): void {
    // Get all completed backups for this bot and server, ordered by date
    global $db;

    $backups = $db->fetchAllAssociative(
        'SELECT id FROM discord_backups
         WHERE bot_id = ? AND discord_guild_id = ? AND status = ?
         ORDER BY created_at DESC',
        [$botId, $guildId, 'completed']
    );

    // Skip if we have fewer backups than the retention limit
    if (count($backups) <= $keepLastN) {
        return;
    }

    // Delete old backups
    $toDelete = array_slice($backups, $keepLastN);

    foreach ($toDelete as $backup) {
        $backupId = $backup['id'];

        // Delete media files
        $mediaDir = $storagePath . '/discord/media/' . $backupId;
        if (is_dir($mediaDir)) {
            deleteDirectory($mediaDir);
        }

        // Delete emoji files
        $emojiDir = $storagePath . '/discord/emojis/' . $backupId;
        if (is_dir($emojiDir)) {
            deleteDirectory($emojiDir);
        }

        // Delete asset files
        $assetDir = $storagePath . '/discord/assets/' . $backupId;
        if (is_dir($assetDir)) {
            deleteDirectory($assetDir);
        }

        // Delete from database (cascades to messages, media, etc.)
        $backupRepository->delete($backupId);

        echo "[" . date('Y-m-d H:i:s') . "] Deleted old backup {$backupId} (retention policy)\n";
    }
}

/**
 * Recursively delete a directory
 */
function deleteDirectory(string $dir): void
{
    if (!is_dir($dir)) return;

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}
