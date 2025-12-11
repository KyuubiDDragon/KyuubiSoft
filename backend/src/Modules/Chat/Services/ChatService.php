<?php

declare(strict_types=1);

namespace App\Modules\Chat\Services;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class ChatService
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Get user's chat rooms
     */
    public function getRooms(string $userId): array
    {
        $rooms = $this->db->fetchAllAssociative(
            "SELECT cr.*, cp.role, cp.is_muted, cp.unread_count, cp.last_read_at
             FROM chat_rooms cr
             INNER JOIN chat_participants cp ON cp.room_id = cr.id
             WHERE cp.user_id = ?
             ORDER BY cr.last_message_at DESC NULLS LAST",
            [$userId]
        );

        foreach ($rooms as &$room) {
            if ($room['type'] === 'direct') {
                // Get the other participant for direct chats
                $other = $this->db->fetchAssociative(
                    "SELECT u.id, u.username, u.email
                     FROM chat_participants cp
                     INNER JOIN users u ON u.id = cp.user_id
                     WHERE cp.room_id = ? AND cp.user_id != ?",
                    [$room['id'], $userId]
                );
                $room['other_user'] = $other;
                $room['name'] = $other['username'] ?? 'Unknown';
            }
            $room['participants'] = $this->getRoomParticipants($room['id']);
        }

        return $rooms;
    }

    /**
     * Get room by ID
     */
    public function getRoom(string $userId, string $roomId): ?array
    {
        $room = $this->db->fetchAssociative(
            "SELECT cr.*, cp.role, cp.is_muted, cp.unread_count, cp.last_read_at
             FROM chat_rooms cr
             INNER JOIN chat_participants cp ON cp.room_id = cr.id
             WHERE cr.id = ? AND cp.user_id = ?",
            [$roomId, $userId]
        );

        if (!$room) {
            return null;
        }

        $room['participants'] = $this->getRoomParticipants($roomId);

        if ($room['type'] === 'direct') {
            $other = $this->db->fetchAssociative(
                "SELECT u.id, u.username, u.email
                 FROM chat_participants cp
                 INNER JOIN users u ON u.id = cp.user_id
                 WHERE cp.room_id = ? AND cp.user_id != ?",
                [$roomId, $userId]
            );
            $room['other_user'] = $other;
        }

        return $room;
    }

    /**
     * Get room participants
     */
    public function getRoomParticipants(string $roomId): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT u.id, u.username, u.email, cp.role, cp.nickname, cp.joined_at
             FROM chat_participants cp
             INNER JOIN users u ON u.id = cp.user_id
             WHERE cp.room_id = ?",
            [$roomId]
        );
    }

    /**
     * Create a chat room
     */
    public function createRoom(string $userId, array $data): array
    {
        $roomId = Uuid::uuid4()->toString();

        $this->db->insert('chat_rooms', [
            'id' => $roomId,
            'name' => $data['name'] ?? null,
            'type' => $data['type'] ?? 'group',
            'description' => $data['description'] ?? null,
            'is_private' => $data['is_private'] ?? true,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Add creator as owner
        $this->addParticipant($roomId, $userId, 'owner');

        // Add other participants
        if (!empty($data['participants'])) {
            foreach ($data['participants'] as $participantId) {
                if ($participantId !== $userId) {
                    $this->addParticipant($roomId, $participantId, 'member');
                }
            }
        }

        return $this->getRoom($userId, $roomId);
    }

    /**
     * Create or get direct message room
     */
    public function getOrCreateDirectRoom(string $userId, string $otherUserId): array
    {
        // Check if direct room already exists
        $existing = $this->db->fetchOne(
            "SELECT cr.id
             FROM chat_rooms cr
             INNER JOIN chat_participants cp1 ON cp1.room_id = cr.id AND cp1.user_id = ?
             INNER JOIN chat_participants cp2 ON cp2.room_id = cr.id AND cp2.user_id = ?
             WHERE cr.type = 'direct'",
            [$userId, $otherUserId]
        );

        if ($existing) {
            return $this->getRoom($userId, $existing);
        }

        // Create new direct room
        $roomId = Uuid::uuid4()->toString();

        $this->db->insert('chat_rooms', [
            'id' => $roomId,
            'type' => 'direct',
            'is_private' => true,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->addParticipant($roomId, $userId, 'member');
        $this->addParticipant($roomId, $otherUserId, 'member');

        return $this->getRoom($userId, $roomId);
    }

    /**
     * Add participant to room
     */
    public function addParticipant(string $roomId, string $userId, string $role = 'member'): void
    {
        $this->db->insert('chat_participants', [
            'id' => Uuid::uuid4()->toString(),
            'room_id' => $roomId,
            'user_id' => $userId,
            'role' => $role,
            'joined_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Remove participant from room
     */
    public function removeParticipant(string $roomId, string $userId): bool
    {
        return $this->db->delete('chat_participants', [
            'room_id' => $roomId,
            'user_id' => $userId,
        ]) > 0;
    }

    /**
     * Get messages for a room
     */
    public function getMessages(string $userId, string $roomId, int $limit = 50, ?string $before = null): array
    {
        // Verify user is participant
        if (!$this->isParticipant($roomId, $userId)) {
            throw new \RuntimeException('Not a participant of this room');
        }

        $sql = "SELECT m.*, u.username, u.email
                FROM chat_messages m
                INNER JOIN users u ON u.id = m.user_id
                WHERE m.room_id = ? AND m.is_deleted = 0";
        $params = [$roomId];

        if ($before) {
            $sql .= " AND m.created_at < ?";
            $params[] = $before;
        }

        $sql .= " ORDER BY m.created_at DESC LIMIT ?";
        $params[] = $limit;

        $messages = $this->db->fetchAllAssociative($sql, $params);

        // Parse JSON fields and reverse for chronological order
        foreach ($messages as &$message) {
            $message['attachments'] = $message['attachments'] ? json_decode($message['attachments'], true) : [];
            $message['mentions'] = $message['mentions'] ? json_decode($message['mentions'], true) : [];
            $message['reactions'] = $message['reactions'] ? json_decode($message['reactions'], true) : [];

            // Get reply-to message if exists
            if ($message['reply_to_id']) {
                $message['reply_to'] = $this->db->fetchAssociative(
                    "SELECT m.id, m.content, u.username
                     FROM chat_messages m
                     INNER JOIN users u ON u.id = m.user_id
                     WHERE m.id = ?",
                    [$message['reply_to_id']]
                );
            }
        }

        return array_reverse($messages);
    }

    /**
     * Send a message
     */
    public function sendMessage(string $userId, string $roomId, array $data): array
    {
        if (!$this->isParticipant($roomId, $userId)) {
            throw new \RuntimeException('Not a participant of this room');
        }

        $messageId = Uuid::uuid4()->toString();
        $content = $data['content'];
        $now = date('Y-m-d H:i:s');

        $this->db->insert('chat_messages', [
            'id' => $messageId,
            'room_id' => $roomId,
            'user_id' => $userId,
            'content' => $content,
            'type' => $data['type'] ?? 'text',
            'reply_to_id' => $data['reply_to_id'] ?? null,
            'attachments' => isset($data['attachments']) ? json_encode($data['attachments']) : null,
            'mentions' => isset($data['mentions']) ? json_encode($data['mentions']) : null,
            'created_at' => $now,
        ]);

        // Update room's last message
        $preview = substr($content, 0, 100);
        $this->db->update('chat_rooms', [
            'last_message_at' => $now,
            'last_message_preview' => $preview,
            'updated_at' => $now,
        ], ['id' => $roomId]);

        // Increment unread count for other participants
        $this->db->executeStatement(
            "UPDATE chat_participants SET unread_count = unread_count + 1 WHERE room_id = ? AND user_id != ?",
            [$roomId, $userId]
        );

        // Get the full message
        $message = $this->db->fetchAssociative(
            "SELECT m.*, u.username, u.email
             FROM chat_messages m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.id = ?",
            [$messageId]
        );

        $message['attachments'] = $message['attachments'] ? json_decode($message['attachments'], true) : [];
        $message['mentions'] = $message['mentions'] ? json_decode($message['mentions'], true) : [];
        $message['reactions'] = [];

        return $message;
    }

    /**
     * Edit a message
     */
    public function editMessage(string $userId, string $messageId, string $content): ?array
    {
        $message = $this->db->fetchAssociative(
            'SELECT * FROM chat_messages WHERE id = ? AND user_id = ?',
            [$messageId, $userId]
        );

        if (!$message) {
            return null;
        }

        $this->db->update('chat_messages', [
            'content' => $content,
            'is_edited' => true,
            'edited_at' => date('Y-m-d H:i:s'),
        ], ['id' => $messageId]);

        return $this->db->fetchAssociative(
            "SELECT m.*, u.username, u.email
             FROM chat_messages m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.id = ?",
            [$messageId]
        );
    }

    /**
     * Delete a message (soft delete)
     */
    public function deleteMessage(string $userId, string $messageId): bool
    {
        return $this->db->update('chat_messages', [
            'is_deleted' => true,
            'deleted_at' => date('Y-m-d H:i:s'),
        ], ['id' => $messageId, 'user_id' => $userId]) > 0;
    }

    /**
     * Add reaction to message
     */
    public function addReaction(string $userId, string $messageId, string $emoji): void
    {
        try {
            $this->db->insert('chat_reactions', [
                'id' => Uuid::uuid4()->toString(),
                'message_id' => $messageId,
                'user_id' => $userId,
                'emoji' => $emoji,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->updateMessageReactions($messageId);
        } catch (\Exception $e) {
            // Duplicate reaction - ignore
        }
    }

    /**
     * Remove reaction from message
     */
    public function removeReaction(string $userId, string $messageId, string $emoji): void
    {
        $this->db->delete('chat_reactions', [
            'message_id' => $messageId,
            'user_id' => $userId,
            'emoji' => $emoji,
        ]);
        $this->updateMessageReactions($messageId);
    }

    /**
     * Update message reactions JSON
     */
    private function updateMessageReactions(string $messageId): void
    {
        $reactions = $this->db->fetchAllAssociative(
            "SELECT emoji, COUNT(*) as count FROM chat_reactions WHERE message_id = ? GROUP BY emoji",
            [$messageId]
        );

        $reactionData = [];
        foreach ($reactions as $r) {
            $reactionData[$r['emoji']] = (int) $r['count'];
        }

        $this->db->update('chat_messages', [
            'reactions' => json_encode($reactionData),
        ], ['id' => $messageId]);
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(string $userId, string $roomId): void
    {
        $this->db->update('chat_participants', [
            'last_read_at' => date('Y-m-d H:i:s'),
            'unread_count' => 0,
        ], ['room_id' => $roomId, 'user_id' => $userId]);
    }

    /**
     * Set typing indicator
     */
    public function setTyping(string $userId, string $roomId): void
    {
        $this->db->executeStatement(
            "INSERT INTO chat_typing (room_id, user_id, started_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE started_at = VALUES(started_at)",
            [$roomId, $userId, date('Y-m-d H:i:s')]
        );
    }

    /**
     * Clear typing indicator
     */
    public function clearTyping(string $userId, string $roomId): void
    {
        $this->db->delete('chat_typing', [
            'room_id' => $roomId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Get users currently typing
     */
    public function getTypingUsers(string $roomId, string $excludeUserId): array
    {
        // Only get typing started within last 5 seconds
        $cutoff = date('Y-m-d H:i:s', strtotime('-5 seconds'));

        return $this->db->fetchAllAssociative(
            "SELECT u.id, u.username
             FROM chat_typing ct
             INNER JOIN users u ON u.id = ct.user_id
             WHERE ct.room_id = ? AND ct.user_id != ? AND ct.started_at > ?",
            [$roomId, $excludeUserId, $cutoff]
        );
    }

    /**
     * Check if user is participant
     */
    public function isParticipant(string $roomId, string $userId): bool
    {
        return (bool) $this->db->fetchOne(
            'SELECT 1 FROM chat_participants WHERE room_id = ? AND user_id = ?',
            [$roomId, $userId]
        );
    }

    /**
     * Get all users for starting a chat
     */
    public function getAvailableUsers(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT id, username, email FROM users WHERE id != ? ORDER BY username",
            [$userId]
        );
    }

    /**
     * Search messages
     */
    public function searchMessages(string $userId, string $query, ?string $roomId = null): array
    {
        $sql = "SELECT m.*, u.username, cr.name as room_name
                FROM chat_messages m
                INNER JOIN users u ON u.id = m.user_id
                INNER JOIN chat_rooms cr ON cr.id = m.room_id
                INNER JOIN chat_participants cp ON cp.room_id = m.room_id AND cp.user_id = ?
                WHERE m.content LIKE ? AND m.is_deleted = 0";
        $params = [$userId, '%' . $query . '%'];

        if ($roomId) {
            $sql .= " AND m.room_id = ?";
            $params[] = $roomId;
        }

        $sql .= " ORDER BY m.created_at DESC LIMIT 50";

        return $this->db->fetchAllAssociative($sql, $params);
    }
}
