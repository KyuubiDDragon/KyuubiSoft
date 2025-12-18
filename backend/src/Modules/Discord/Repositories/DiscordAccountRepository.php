<?php

declare(strict_types=1);

namespace App\Modules\Discord\Repositories;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class DiscordAccountRepository
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function findById(string $id): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM discord_accounts WHERE id = ?',
            [$id]
        );
        return $result ?: null;
    }

    public function findByIdAndUser(string $id, string $userId): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM discord_accounts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        return $result ?: null;
    }

    public function findAllByUser(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, user_id, discord_user_id, discord_username, discord_discriminator,
                    discord_avatar, discord_email, is_active, last_sync_at, created_at, updated_at
             FROM discord_accounts
             WHERE user_id = ?
             ORDER BY created_at DESC',
            [$userId]
        );
    }

    public function create(array $data): array
    {
        $id = $data['id'] ?? Uuid::uuid4()->toString();

        $this->db->insert('discord_accounts', [
            'id' => $id,
            'user_id' => $data['user_id'],
            'discord_user_id' => $data['discord_user_id'],
            'discord_username' => $data['discord_username'] ?? null,
            'discord_discriminator' => $data['discord_discriminator'] ?? null,
            'discord_avatar' => $data['discord_avatar'] ?? null,
            'discord_email' => $data['discord_email'] ?? null,
            'token_encrypted' => $data['token_encrypted'],
            'is_active' => $data['is_active'] ?? 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->findById($id);
    }

    public function update(string $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('discord_accounts', $data, ['id' => $id]) > 0;
    }

    public function updateLastSync(string $id): bool
    {
        return $this->db->update('discord_accounts', [
            'last_sync_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]) > 0;
    }

    public function delete(string $id): bool
    {
        return $this->db->delete('discord_accounts', ['id' => $id]) > 0;
    }

    // Server methods
    public function findServersByAccount(string $accountId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM discord_channels c WHERE c.server_id = s.id) as channel_count,
                    (SELECT COUNT(*) FROM discord_backups b WHERE b.server_id = s.id) as backup_count
             FROM discord_servers s
             WHERE s.account_id = ?
             ORDER BY s.is_favorite DESC, s.name ASC',
            [$accountId]
        );
    }

    public function findServerById(string $serverId): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM discord_servers WHERE id = ?',
            [$serverId]
        );
        return $result ?: null;
    }

    public function upsertServer(string $accountId, array $serverData): string
    {
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM discord_servers WHERE account_id = ? AND discord_guild_id = ?',
            [$accountId, $serverData['discord_guild_id']]
        );

        if ($existing) {
            $this->db->update('discord_servers', [
                'name' => $serverData['name'],
                'icon' => $serverData['icon'] ?? null,
                'owner_id' => $serverData['owner_id'] ?? null,
                'member_count' => $serverData['member_count'] ?? null,
                'cached_at' => date('Y-m-d H:i:s'),
            ], ['id' => $existing['id']]);

            return $existing['id'];
        }

        $id = Uuid::uuid4()->toString();
        $this->db->insert('discord_servers', [
            'id' => $id,
            'account_id' => $accountId,
            'discord_guild_id' => $serverData['discord_guild_id'],
            'name' => $serverData['name'],
            'icon' => $serverData['icon'] ?? null,
            'owner_id' => $serverData['owner_id'] ?? null,
            'member_count' => $serverData['member_count'] ?? null,
            'is_favorite' => 0,
            'cached_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public function toggleServerFavorite(string $serverId): bool
    {
        $this->db->executeStatement(
            'UPDATE discord_servers SET is_favorite = NOT is_favorite WHERE id = ?',
            [$serverId]
        );
        return true;
    }

    // Channel methods
    public function findChannelsByServer(string $serverId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM discord_backups b WHERE b.channel_id = c.id) as backup_count
             FROM discord_channels c
             WHERE c.server_id = ?
             ORDER BY c.position ASC',
            [$serverId]
        );
    }

    public function findDMChannelsByAccount(string $accountId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM discord_backups b WHERE b.channel_id = c.id) as backup_count
             FROM discord_channels c
             WHERE c.account_id = ? AND c.type IN (\'dm\', \'group_dm\')
             ORDER BY c.cached_at DESC',
            [$accountId]
        );
    }

    public function findChannelById(string $channelId): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM discord_channels WHERE id = ?',
            [$channelId]
        );
        return $result ?: null;
    }

    public function findChannelByDiscordId(string $accountId, string $discordChannelId): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM discord_channels WHERE account_id = ? AND discord_channel_id = ?',
            [$accountId, $discordChannelId]
        );
        return $result ?: null;
    }

    public function upsertChannel(string $accountId, ?string $serverId, array $channelData): string
    {
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM discord_channels WHERE account_id = ? AND discord_channel_id = ?',
            [$accountId, $channelData['discord_channel_id']]
        );

        $type = $this->mapChannelType($channelData['type'] ?? 0);

        if ($existing) {
            $this->db->update('discord_channels', [
                'server_id' => $serverId,
                'name' => $channelData['name'] ?? 'Unknown',
                'type' => $type,
                'parent_id' => $channelData['parent_id'] ?? null,
                'position' => $channelData['position'] ?? 0,
                'recipient_username' => $channelData['recipient_username'] ?? null,
                'recipient_avatar' => $channelData['recipient_avatar'] ?? null,
                'recipient_id' => $channelData['recipient_id'] ?? null,
                'last_message_id' => $channelData['last_message_id'] ?? null,
                'cached_at' => date('Y-m-d H:i:s'),
            ], ['id' => $existing['id']]);

            return $existing['id'];
        }

        $id = Uuid::uuid4()->toString();
        $this->db->insert('discord_channels', [
            'id' => $id,
            'server_id' => $serverId,
            'account_id' => $accountId,
            'discord_channel_id' => $channelData['discord_channel_id'],
            'discord_guild_id' => $channelData['discord_guild_id'] ?? null,
            'name' => $channelData['name'] ?? 'Unknown',
            'type' => $type,
            'parent_id' => $channelData['parent_id'] ?? null,
            'position' => $channelData['position'] ?? 0,
            'recipient_username' => $channelData['recipient_username'] ?? null,
            'recipient_avatar' => $channelData['recipient_avatar'] ?? null,
            'recipient_id' => $channelData['recipient_id'] ?? null,
            'last_message_id' => $channelData['last_message_id'] ?? null,
            'is_favorite' => 0,
            'cached_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public function toggleChannelFavorite(string $channelId): bool
    {
        $this->db->executeStatement(
            'UPDATE discord_channels SET is_favorite = NOT is_favorite WHERE id = ?',
            [$channelId]
        );
        return true;
    }

    private function mapChannelType(int $type): string
    {
        return match ($type) {
            0 => 'text',
            1 => 'dm',
            2 => 'voice',
            3 => 'group_dm',
            4 => 'category',
            5 => 'announcement',
            10, 11, 12 => 'thread',
            15 => 'forum',
            default => 'text',
        };
    }

    // Cleanup old cached data
    public function cleanupOldServers(string $accountId, array $activeGuildIds): int
    {
        if (empty($activeGuildIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($activeGuildIds), '?'));
        $params = array_merge([$accountId], $activeGuildIds);

        return $this->db->executeStatement(
            "DELETE FROM discord_servers WHERE account_id = ? AND discord_guild_id NOT IN ({$placeholders})",
            $params
        );
    }
}
