<?php

declare(strict_types=1);

namespace App\Modules\Discord\Repositories;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class DiscordBackupRepository
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function findById(string $id): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT b.*,
                    s.name as server_name, s.discord_guild_id,
                    c.name as channel_name, c.discord_channel_id as channel_discord_id
             FROM discord_backups b
             LEFT JOIN discord_servers s ON b.server_id = s.id
             LEFT JOIN discord_channels c ON b.channel_id = c.id
             WHERE b.id = ?',
            [$id]
        );
        return $result ?: null;
    }

    public function findByIdAndAccount(string $id, string $accountId): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT b.*,
                    s.name as server_name,
                    c.name as channel_name
             FROM discord_backups b
             LEFT JOIN discord_servers s ON b.server_id = s.id
             LEFT JOIN discord_channels c ON b.channel_id = c.id
             WHERE b.id = ? AND b.account_id = ?',
            [$id, $accountId]
        );
        return $result ?: null;
    }

    public function findAllByAccount(string $accountId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT b.*,
                    s.name as server_name, s.icon as server_icon,
                    c.name as channel_name, c.type as channel_type
             FROM discord_backups b
             LEFT JOIN discord_servers s ON b.server_id = s.id
             LEFT JOIN discord_channels c ON b.channel_id = c.id
             WHERE b.account_id = ?
             ORDER BY b.created_at DESC
             LIMIT ? OFFSET ?',
            [$accountId, $limit, $offset],
            [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]
        );
    }

    public function findAllByUser(string $userId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT b.*,
                    s.name as server_name, s.icon as server_icon,
                    c.name as channel_name, c.type as channel_type,
                    a.discord_username as account_name
             FROM discord_backups b
             INNER JOIN discord_accounts a ON b.account_id = a.id
             LEFT JOIN discord_servers s ON b.server_id = s.id
             LEFT JOIN discord_channels c ON b.channel_id = c.id
             WHERE a.user_id = ?
             ORDER BY b.created_at DESC
             LIMIT ? OFFSET ?',
            [$userId, $limit, $offset],
            [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]
        );
    }

    public function countByUser(string $userId): int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM discord_backups b
             INNER JOIN discord_accounts a ON b.account_id = a.id
             WHERE a.user_id = ?',
            [$userId]
        );
    }

    public function create(array $data): array
    {
        $id = $data['id'] ?? Uuid::uuid4()->toString();

        $this->db->insert('discord_backups', [
            'id' => $id,
            'account_id' => $data['account_id'],
            'server_id' => $data['server_id'] ?? null,
            'channel_id' => $data['channel_id'] ?? null,
            'discord_guild_id' => $data['discord_guild_id'] ?? null,
            'discord_channel_id' => $data['discord_channel_id'] ?? null,
            'target_name' => $data['target_name'],
            'type' => $data['type'] ?? 'channel',
            'include_media' => $data['include_media'] ?? true,
            'include_reactions' => $data['include_reactions'] ?? true,
            'include_threads' => $data['include_threads'] ?? false,
            'include_embeds' => $data['include_embeds'] ?? true,
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'status' => 'pending',
            'progress_percent' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->findById($id);
    }

    public function updateStatus(string $id, string $status, ?string $errorMessage = null): bool
    {
        $data = ['status' => $status];

        if ($status === 'running' && !$this->findById($id)['started_at']) {
            $data['started_at'] = date('Y-m-d H:i:s');
        }

        if (in_array($status, ['completed', 'failed', 'cancelled'])) {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }

        if ($errorMessage !== null) {
            $data['error_message'] = $errorMessage;
        }

        return $this->db->update('discord_backups', $data, ['id' => $id]) > 0;
    }

    public function updateProgress(string $id, int $processed, int $total, ?string $currentAction = null): bool
    {
        $percent = $total > 0 ? min(100, (int) (($processed / $total) * 100)) : 0;

        $data = [
            'messages_processed' => $processed,
            'messages_total' => $total,
            'progress_percent' => $percent,
        ];

        if ($currentAction !== null) {
            $data['current_action'] = $currentAction;
        }

        return $this->db->update('discord_backups', $data, ['id' => $id]) > 0;
    }

    public function updateResults(string $id, array $results): bool
    {
        return $this->db->update('discord_backups', [
            'messages_total' => $results['messages_total'] ?? 0,
            'messages_processed' => $results['messages_processed'] ?? 0,
            'media_count' => $results['media_count'] ?? 0,
            'media_size' => $results['media_size'] ?? 0,
            'file_path' => $results['file_path'] ?? null,
            'file_size' => $results['file_size'] ?? null,
        ], ['id' => $id]) > 0;
    }

    public function delete(string $id): bool
    {
        return $this->db->delete('discord_backups', ['id' => $id]) > 0;
    }

    // Message methods
    public function insertMessage(string $backupId, array $messageData): string
    {
        $id = Uuid::uuid4()->toString();

        $this->db->insert('discord_messages', [
            'id' => $id,
            'backup_id' => $backupId,
            'discord_message_id' => $messageData['id'],
            'discord_channel_id' => $messageData['channel_id'],
            'discord_author_id' => $messageData['author']['id'] ?? 'unknown',
            'author_username' => $messageData['author']['username'] ?? null,
            'author_avatar' => $messageData['author']['avatar'] ?? null,
            'content' => $messageData['content'] ?? null,
            'has_attachments' => !empty($messageData['attachments']),
            'has_embeds' => !empty($messageData['embeds']),
            'attachment_count' => count($messageData['attachments'] ?? []),
            'embed_count' => count($messageData['embeds'] ?? []),
            'reaction_count' => array_sum(array_column($messageData['reactions'] ?? [], 'count')),
            'is_pinned' => $messageData['pinned'] ?? false,
            'is_edited' => !empty($messageData['edited_timestamp']),
            'message_type' => $messageData['type'] ?? 'DEFAULT',
            'raw_data' => json_encode($messageData),
            'message_timestamp' => $this->parseTimestamp($messageData['timestamp']),
            'edited_timestamp' => !empty($messageData['edited_timestamp'])
                ? $this->parseTimestamp($messageData['edited_timestamp'])
                : null,
        ]);

        return $id;
    }

    public function findMessagesByBackup(string $backupId, int $limit = 100, int $offset = 0, ?string $search = null): array
    {
        $sql = 'SELECT * FROM discord_messages WHERE backup_id = ?';
        $params = [$backupId];
        $types = [\PDO::PARAM_STR];

        if ($search) {
            $sql .= ' AND MATCH(content) AGAINST(? IN BOOLEAN MODE)';
            $params[] = $search;
            $types[] = \PDO::PARAM_STR;
        }

        $sql .= ' ORDER BY message_timestamp DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $types[] = \PDO::PARAM_INT;
        $types[] = \PDO::PARAM_INT;

        return $this->db->fetchAllAssociative($sql, $params, $types);
    }

    public function countMessagesByBackup(string $backupId): int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM discord_messages WHERE backup_id = ?',
            [$backupId]
        );
    }

    /**
     * Search messages across all backups for a user
     */
    public function searchAllMessages(string $userId, string $search, int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT m.*, b.target_name as backup_name, b.type as backup_type
                FROM discord_messages m
                INNER JOIN discord_backups b ON m.backup_id = b.id
                INNER JOIN discord_accounts a ON b.account_id = a.id
                WHERE a.user_id = ?';
        $params = [$userId];
        $types = [\PDO::PARAM_STR];

        if ($search) {
            $sql .= ' AND MATCH(m.content) AGAINST(? IN BOOLEAN MODE)';
            $params[] = $search;
            $types[] = \PDO::PARAM_STR;
        }

        $sql .= ' ORDER BY m.message_timestamp DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $types[] = \PDO::PARAM_INT;
        $types[] = \PDO::PARAM_INT;

        return $this->db->fetchAllAssociative($sql, $params, $types);
    }

    public function countSearchResults(string $userId, string $search): int
    {
        $sql = 'SELECT COUNT(*)
                FROM discord_messages m
                INNER JOIN discord_backups b ON m.backup_id = b.id
                INNER JOIN discord_accounts a ON b.account_id = a.id
                WHERE a.user_id = ?';
        $params = [$userId];
        $types = [\PDO::PARAM_STR];

        if ($search) {
            $sql .= ' AND MATCH(m.content) AGAINST(? IN BOOLEAN MODE)';
            $params[] = $search;
            $types[] = \PDO::PARAM_STR;
        }

        return (int) $this->db->fetchOne($sql, $params, $types);
    }

    // Media methods
    public function insertMedia(string $backupId, array $mediaData): string
    {
        $id = Uuid::uuid4()->toString();

        $this->db->insert('discord_media', [
            'id' => $id,
            'backup_id' => $backupId,
            'discord_message_id' => $mediaData['message_id'],
            'discord_attachment_id' => $mediaData['attachment_id'] ?? null,
            'original_url' => $mediaData['url'],
            'local_path' => $mediaData['local_path'],
            'filename' => $mediaData['filename'],
            'file_size' => $mediaData['size'] ?? null,
            'mime_type' => $mediaData['content_type'] ?? null,
            'width' => $mediaData['width'] ?? null,
            'height' => $mediaData['height'] ?? null,
            'is_spoiler' => $mediaData['spoiler'] ?? false,
            'downloaded_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public function findMediaByBackup(string $backupId, int $limit = 100, int $offset = 0): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT * FROM discord_media WHERE backup_id = ? ORDER BY downloaded_at DESC LIMIT ? OFFSET ?',
            [$backupId, $limit, $offset],
            [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]
        );
    }

    public function findAllMediaByUser(string $userId, int $limit = 100, int $offset = 0): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT m.*, b.target_name as backup_name
             FROM discord_media m
             INNER JOIN discord_backups b ON m.backup_id = b.id
             INNER JOIN discord_accounts a ON b.account_id = a.id
             WHERE a.user_id = ?
             ORDER BY m.downloaded_at DESC
             LIMIT ? OFFSET ?',
            [$userId, $limit, $offset],
            [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]
        );
    }

    public function findMediaById(string $id): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT m.*, b.account_id, a.user_id
             FROM discord_media m
             INNER JOIN discord_backups b ON m.backup_id = b.id
             INNER JOIN discord_accounts a ON b.account_id = a.id
             WHERE m.id = ?',
            [$id]
        );
        return $result ?: null;
    }

    // Delete job methods
    public function createDeleteJob(array $data): array
    {
        $id = Uuid::uuid4()->toString();

        $this->db->insert('discord_delete_jobs', [
            'id' => $id,
            'account_id' => $data['account_id'],
            'discord_channel_id' => $data['discord_channel_id'],
            'channel_name' => $data['channel_name'] ?? null,
            'server_name' => $data['server_name'] ?? null,
            'status' => 'pending',
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'keyword_filter' => $data['keyword_filter'] ?? null,
            'delete_attachments_only' => $data['delete_attachments_only'] ?? false,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->findDeleteJobById($id);
    }

    public function findDeleteJobById(string $id): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM discord_delete_jobs WHERE id = ?',
            [$id]
        );
        return $result ?: null;
    }

    public function findDeleteJobsByUser(string $userId, int $limit = 50): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT j.*, a.discord_username as account_name
             FROM discord_delete_jobs j
             INNER JOIN discord_accounts a ON j.account_id = a.id
             WHERE a.user_id = ?
             ORDER BY j.created_at DESC
             LIMIT ?',
            [$userId, $limit],
            [\PDO::PARAM_STR, \PDO::PARAM_INT]
        );
    }

    public function updateDeleteJobStatus(string $id, string $status, ?string $errorMessage = null): bool
    {
        $data = ['status' => $status];

        if ($status === 'running') {
            $data['started_at'] = date('Y-m-d H:i:s');
        }

        if (in_array($status, ['completed', 'failed', 'cancelled'])) {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }

        if ($errorMessage !== null) {
            $data['error_message'] = $errorMessage;
        }

        return $this->db->update('discord_delete_jobs', $data, ['id' => $id]) > 0;
    }

    public function updateDeleteJobProgress(string $id, int $total, int $deleted, int $failed, ?string $currentMessageId = null): bool
    {
        $data = [
            'total_messages' => $total,
            'deleted_messages' => $deleted,
            'failed_messages' => $failed,
        ];

        if ($currentMessageId !== null) {
            $data['current_message_id'] = $currentMessageId;
        }

        return $this->db->update('discord_delete_jobs', $data, ['id' => $id]) > 0;
    }

    private function parseTimestamp(string $timestamp): string
    {
        $dt = new \DateTime($timestamp);
        return $dt->format('Y-m-d H:i:s');
    }
}
