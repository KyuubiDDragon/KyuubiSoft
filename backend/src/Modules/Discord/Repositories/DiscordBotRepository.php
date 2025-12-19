<?php

declare(strict_types=1);

namespace App\Modules\Discord\Repositories;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class DiscordBotRepository
{
    public function __construct(
        private readonly Connection $db
    ) {}

    // ========== Bot Methods ==========

    public function findBotById(string $id): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM discord_bots WHERE id = ?',
            [$id]
        );
        return $result ?: null;
    }

    public function findBotByIdAndUser(string $id, string $userId): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM discord_bots WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        return $result ?: null;
    }

    public function findAllBotsByUser(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, user_id, client_id, bot_user_id, bot_username, bot_discriminator,
                    bot_avatar, is_active, is_public, last_sync_at, created_at, updated_at,
                    (SELECT COUNT(*) FROM discord_bot_servers WHERE bot_id = discord_bots.id) as server_count
             FROM discord_bots
             WHERE user_id = ?
             ORDER BY created_at DESC',
            [$userId]
        );
    }

    public function findBotByClientId(string $userId, string $clientId): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM discord_bots WHERE user_id = ? AND client_id = ?',
            [$userId, $clientId]
        );
        return $result ?: null;
    }

    public function createBot(array $data): array
    {
        $id = $data['id'] ?? Uuid::uuid4()->toString();

        $this->db->insert('discord_bots', [
            'id' => $id,
            'user_id' => $data['user_id'],
            'client_id' => $data['client_id'],
            'client_secret_encrypted' => $data['client_secret_encrypted'] ?? null,
            'bot_token_encrypted' => $data['bot_token_encrypted'],
            'bot_user_id' => $data['bot_user_id'] ?? null,
            'bot_username' => $data['bot_username'] ?? null,
            'bot_discriminator' => $data['bot_discriminator'] ?? null,
            'bot_avatar' => $data['bot_avatar'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'is_public' => $data['is_public'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->findBotById($id);
    }

    public function updateBot(string $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('discord_bots', $data, ['id' => $id]) > 0;
    }

    public function updateBotLastSync(string $id): bool
    {
        return $this->db->update('discord_bots', [
            'last_sync_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]) > 0;
    }

    public function deleteBot(string $id): bool
    {
        return $this->db->delete('discord_bots', ['id' => $id]) > 0;
    }

    // ========== Bot Server Methods ==========

    public function findBotServerById(string $id): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM discord_bot_servers WHERE id = ?',
            [$id]
        );
        return $result ?: null;
    }

    public function findServersByBot(string $botId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM discord_bot_channels c WHERE c.bot_server_id = s.id) as channel_count,
                    (SELECT COUNT(*) FROM discord_backups b WHERE b.bot_id = ? AND b.discord_guild_id = s.discord_guild_id) as backup_count
             FROM discord_bot_servers s
             WHERE s.bot_id = ?
             ORDER BY s.is_favorite DESC, s.name ASC',
            [$botId, $botId]
        );
    }

    public function findBotServerByGuildId(string $botId, string $guildId): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM discord_bot_servers WHERE bot_id = ? AND discord_guild_id = ?',
            [$botId, $guildId]
        );
        return $result ?: null;
    }

    public function upsertBotServer(string $botId, array $serverData): string
    {
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM discord_bot_servers WHERE bot_id = ? AND discord_guild_id = ?',
            [$botId, $serverData['discord_guild_id']]
        );

        if ($existing) {
            $this->db->update('discord_bot_servers', [
                'name' => $serverData['name'],
                'icon' => $serverData['icon'] ?? null,
                'owner_id' => $serverData['owner_id'] ?? null,
                'member_count' => $serverData['member_count'] ?? null,
                'permissions' => $serverData['permissions'] ?? 0,
                'cached_at' => date('Y-m-d H:i:s'),
            ], ['id' => $existing['id']]);

            return $existing['id'];
        }

        $id = Uuid::uuid4()->toString();
        $this->db->insert('discord_bot_servers', [
            'id' => $id,
            'bot_id' => $botId,
            'discord_guild_id' => $serverData['discord_guild_id'],
            'name' => $serverData['name'],
            'icon' => $serverData['icon'] ?? null,
            'owner_id' => $serverData['owner_id'] ?? null,
            'member_count' => $serverData['member_count'] ?? null,
            'permissions' => $serverData['permissions'] ?? 0,
            'is_favorite' => 0,
            'auto_backup_enabled' => 0,
            'joined_at' => date('Y-m-d H:i:s'),
            'cached_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public function deleteBotServer(string $id): bool
    {
        return $this->db->delete('discord_bot_servers', ['id' => $id]) > 0;
    }

    public function toggleBotServerFavorite(string $serverId): bool
    {
        $this->db->executeStatement(
            'UPDATE discord_bot_servers SET is_favorite = NOT is_favorite WHERE id = ?',
            [$serverId]
        );
        return true;
    }

    public function updateBotServerAutoBackup(string $serverId, bool $enabled, ?string $interval = null): bool
    {
        return $this->db->update('discord_bot_servers', [
            'auto_backup_enabled' => $enabled ? 1 : 0,
            'auto_backup_interval' => $interval,
        ], ['id' => $serverId]) > 0;
    }

    // ========== Bot Channel Methods ==========

    public function findChannelsByBotServer(string $botServerId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT * FROM discord_bot_channels
             WHERE bot_server_id = ?
             ORDER BY position ASC',
            [$botServerId]
        );
    }

    public function upsertBotChannel(string $botId, string $botServerId, array $channelData): string
    {
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM discord_bot_channels WHERE bot_id = ? AND discord_channel_id = ?',
            [$botId, $channelData['discord_channel_id']]
        );

        $type = $this->mapChannelType($channelData['type'] ?? 0);

        if ($existing) {
            $this->db->update('discord_bot_channels', [
                'name' => $channelData['name'] ?? 'Unknown',
                'type' => $type,
                'parent_id' => $channelData['parent_id'] ?? null,
                'position' => $channelData['position'] ?? 0,
                'topic' => $channelData['topic'] ?? null,
                'permission_overwrites' => isset($channelData['permission_overwrites'])
                    ? json_encode($channelData['permission_overwrites'])
                    : null,
                'cached_at' => date('Y-m-d H:i:s'),
            ], ['id' => $existing['id']]);

            return $existing['id'];
        }

        $id = Uuid::uuid4()->toString();
        $this->db->insert('discord_bot_channels', [
            'id' => $id,
            'bot_server_id' => $botServerId,
            'bot_id' => $botId,
            'discord_channel_id' => $channelData['discord_channel_id'],
            'name' => $channelData['name'] ?? 'Unknown',
            'type' => $type,
            'parent_id' => $channelData['parent_id'] ?? null,
            'position' => $channelData['position'] ?? 0,
            'topic' => $channelData['topic'] ?? null,
            'permission_overwrites' => isset($channelData['permission_overwrites'])
                ? json_encode($channelData['permission_overwrites'])
                : null,
            'cached_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public function deleteChannelsByBotServer(string $botServerId): int
    {
        return $this->db->executeStatement(
            'DELETE FROM discord_bot_channels WHERE bot_server_id = ?',
            [$botServerId]
        );
    }

    private function mapChannelType(int $type): string
    {
        return match ($type) {
            0 => 'text',
            2 => 'voice',
            4 => 'category',
            5 => 'announcement',
            10, 11, 12 => 'thread',
            13 => 'stage',
            15 => 'forum',
            default => 'text',
        };
    }

    // ========== Cleanup Methods ==========

    public function cleanupOldBotServers(string $botId, array $activeGuildIds): int
    {
        if (empty($activeGuildIds)) {
            return $this->db->executeStatement(
                'DELETE FROM discord_bot_servers WHERE bot_id = ?',
                [$botId]
            );
        }

        $placeholders = implode(',', array_fill(0, count($activeGuildIds), '?'));
        $params = array_merge([$botId], $activeGuildIds);

        return $this->db->executeStatement(
            "DELETE FROM discord_bot_servers WHERE bot_id = ? AND discord_guild_id NOT IN ({$placeholders})",
            $params
        );
    }

    // ========== Backup Schedule Methods ==========

    public function findSchedulesByBot(string $botId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT s.*, bs.name as server_name, bs.discord_guild_id
             FROM discord_backup_schedules s
             JOIN discord_bot_servers bs ON s.bot_server_id = bs.id
             WHERE s.bot_id = ?
             ORDER BY s.created_at DESC',
            [$botId]
        );
    }

    public function findScheduleById(string $id): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM discord_backup_schedules WHERE id = ?',
            [$id]
        );
        return $result ?: null;
    }

    public function findDueSchedules(): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT s.*, b.bot_token_encrypted, bs.discord_guild_id, bs.name as server_name
             FROM discord_backup_schedules s
             JOIN discord_bots b ON s.bot_id = b.id
             JOIN discord_bot_servers bs ON s.bot_server_id = bs.id
             WHERE s.is_active = 1
               AND b.is_active = 1
               AND (s.next_run_at IS NULL OR s.next_run_at <= NOW())
             ORDER BY s.next_run_at ASC'
        );
    }

    public function createSchedule(array $data): array
    {
        $id = $data['id'] ?? Uuid::uuid4()->toString();

        $this->db->insert('discord_backup_schedules', [
            'id' => $id,
            'bot_id' => $data['bot_id'],
            'bot_server_id' => $data['bot_server_id'],
            'is_active' => $data['is_active'] ?? 1,
            'interval_type' => $data['interval_type'] ?? 'weekly',
            'day_of_week' => $data['day_of_week'] ?? null,
            'day_of_month' => $data['day_of_month'] ?? null,
            'time_of_day' => $data['time_of_day'] ?? '03:00:00',
            'include_media' => $data['include_media'] ?? 1,
            'include_threads' => $data['include_threads'] ?? 1,
            'include_roles' => $data['include_roles'] ?? 1,
            'include_emojis' => $data['include_emojis'] ?? 1,
            'keep_last_n' => $data['keep_last_n'] ?? 7,
            'next_run_at' => $data['next_run_at'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->findScheduleById($id);
    }

    public function updateSchedule(string $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('discord_backup_schedules', $data, ['id' => $id]) > 0;
    }

    public function deleteSchedule(string $id): bool
    {
        return $this->db->delete('discord_backup_schedules', ['id' => $id]) > 0;
    }
}
