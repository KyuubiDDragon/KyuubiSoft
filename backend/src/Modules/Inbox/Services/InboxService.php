<?php

declare(strict_types=1);

namespace App\Modules\Inbox\Services;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class InboxService
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Quick capture - create new inbox item
     */
    public function capture(string $userId, array $data): array
    {
        $id = Uuid::uuid4()->toString();

        $this->db->insert('inbox_items', [
            'id' => $id,
            'user_id' => $userId,
            'content' => $data['content'],
            'note' => $data['note'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'source' => $data['source'] ?? 'quick_capture',
            'source_url' => $data['source_url'] ?? null,
            'tags' => isset($data['tags']) ? json_encode($data['tags']) : null,
            'reminder_at' => $data['reminder_at'] ?? null,
            'status' => 'inbox',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->getItem($userId, $id);
    }

    /**
     * Get all inbox items
     */
    public function getItems(string $userId, array $filters = []): array
    {
        $where = ['user_id = ?'];
        $params = [$userId];

        if (isset($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        } else {
            // Default: show inbox items only
            $where[] = "status IN ('inbox', 'processing')";
        }

        if (isset($filters['priority'])) {
            $where[] = 'priority = ?';
            $params[] = $filters['priority'];
        }

        if (isset($filters['search'])) {
            $where[] = 'content LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }

        $orderBy = match ($filters['sort'] ?? 'newest') {
            'oldest' => 'created_at ASC',
            'priority' => 'FIELD(priority, "urgent", "high", "normal", "low"), created_at DESC',
            default => 'created_at DESC',
        };

        $items = $this->db->fetchAllAssociative(
            "SELECT * FROM inbox_items WHERE " . implode(' AND ', $where) . " ORDER BY {$orderBy}",
            $params
        );

        foreach ($items as &$item) {
            $item['tags'] = $item['tags'] ? json_decode($item['tags'], true) : [];
            $item['attachments'] = $item['attachments'] ? json_decode($item['attachments'], true) : [];
        }

        return $items;
    }

    /**
     * Get single inbox item
     */
    public function getItem(string $userId, string $itemId): ?array
    {
        $item = $this->db->fetchAssociative(
            'SELECT * FROM inbox_items WHERE id = ? AND user_id = ?',
            [$itemId, $userId]
        );

        if ($item) {
            $item['tags'] = $item['tags'] ? json_decode($item['tags'], true) : [];
            $item['attachments'] = $item['attachments'] ? json_decode($item['attachments'], true) : [];
        }

        return $item ?: null;
    }

    /**
     * Update inbox item
     */
    public function updateItem(string $userId, string $itemId, array $data): ?array
    {
        $item = $this->getItem($userId, $itemId);
        if (!$item) {
            return null;
        }

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        $allowedFields = ['content', 'note', 'priority', 'status', 'reminder_at'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['tags'])) {
            $updateData['tags'] = json_encode($data['tags']);
        }

        $this->db->update('inbox_items', $updateData, ['id' => $itemId, 'user_id' => $userId]);

        return $this->getItem($userId, $itemId);
    }

    /**
     * Move inbox item to another module
     */
    public function moveToModule(string $userId, string $itemId, string $targetType, ?string $targetId, array $options = []): array
    {
        $item = $this->getItem($userId, $itemId);
        if (!$item) {
            throw new \InvalidArgumentException('Item not found');
        }

        $createdItem = null;

        switch ($targetType) {
            case 'list':
                $createdItem = $this->moveToList($userId, $item, $targetId, $options);
                break;
            case 'document':
                $createdItem = $this->moveToDocument($userId, $item, $options);
                break;
            case 'kanban':
                $createdItem = $this->moveToKanban($userId, $item, $targetId, $options);
                break;
            case 'calendar':
                $createdItem = $this->moveToCalendar($userId, $item, $options);
                break;
            case 'trash':
                // Just mark as archived
                break;
            default:
                throw new \InvalidArgumentException('Invalid target type');
        }

        // Update inbox item
        $this->db->update('inbox_items', [
            'status' => 'done',
            'processed_at' => date('Y-m-d H:i:s'),
            'moved_to_type' => $targetType,
            'moved_to_id' => $createdItem['id'] ?? $targetId,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $itemId]);

        return [
            'inbox_item' => $this->getItem($userId, $itemId),
            'created_item' => $createdItem,
        ];
    }

    /**
     * Move to list as task
     */
    private function moveToList(string $userId, array $item, ?string $listId, array $options): array
    {
        // If no list specified, get or create default inbox list
        if (!$listId) {
            $listId = $this->getOrCreateDefaultList($userId);
        }

        $taskId = Uuid::uuid4()->toString();
        $this->db->insert('list_items', [
            'id' => $taskId,
            'list_id' => $listId,
            'content' => $item['content'],
            'note' => $item['note'],
            'priority' => $this->mapPriority($item['priority']),
            'due_date' => $options['due_date'] ?? null,
            'position' => $this->getNextPosition('list_items', 'list_id', $listId),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['id' => $taskId, 'type' => 'list_item', 'list_id' => $listId];
    }

    /**
     * Move to document
     */
    private function moveToDocument(string $userId, array $item, array $options): array
    {
        $docId = Uuid::uuid4()->toString();
        $this->db->insert('documents', [
            'id' => $docId,
            'user_id' => $userId,
            'title' => $options['title'] ?? substr($item['content'], 0, 100),
            'content' => $item['content'] . ($item['note'] ? "\n\n---\n\n" . $item['note'] : ''),
            'format' => 'markdown',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['id' => $docId, 'type' => 'document'];
    }

    /**
     * Move to kanban card
     */
    private function moveToKanban(string $userId, array $item, ?string $boardId, array $options): array
    {
        if (!$boardId) {
            throw new \InvalidArgumentException('Board ID required for kanban');
        }

        // Get first column of board
        $column = $this->db->fetchAssociative(
            'SELECT id FROM kanban_columns WHERE board_id = ? ORDER BY position ASC LIMIT 1',
            [$boardId]
        );

        if (!$column) {
            throw new \InvalidArgumentException('Board has no columns');
        }

        $cardId = Uuid::uuid4()->toString();
        $this->db->insert('kanban_cards', [
            'id' => $cardId,
            'column_id' => $options['column_id'] ?? $column['id'],
            'title' => substr($item['content'], 0, 255),
            'description' => $item['note'],
            'position' => $this->getNextPosition('kanban_cards', 'column_id', $options['column_id'] ?? $column['id']),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['id' => $cardId, 'type' => 'kanban_card', 'board_id' => $boardId];
    }

    /**
     * Move to calendar event
     */
    private function moveToCalendar(string $userId, array $item, array $options): array
    {
        $eventId = Uuid::uuid4()->toString();
        $startDate = $options['start_date'] ?? date('Y-m-d H:i:s');

        $this->db->insert('calendar_events', [
            'id' => $eventId,
            'user_id' => $userId,
            'title' => substr($item['content'], 0, 255),
            'description' => $item['note'],
            'start_date' => $startDate,
            'end_date' => $options['end_date'] ?? $startDate,
            'all_day' => $options['all_day'] ?? true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['id' => $eventId, 'type' => 'calendar_event'];
    }

    /**
     * Delete inbox item
     */
    public function deleteItem(string $userId, string $itemId): bool
    {
        $affected = $this->db->delete('inbox_items', [
            'id' => $itemId,
            'user_id' => $userId,
        ]);

        return $affected > 0;
    }

    /**
     * Get inbox stats
     */
    public function getStats(string $userId): array
    {
        $stats = $this->db->fetchAssociative(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'inbox' THEN 1 ELSE 0 END) as inbox_count,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done_count,
                SUM(CASE WHEN priority = 'urgent' AND status = 'inbox' THEN 1 ELSE 0 END) as urgent_count
             FROM inbox_items
             WHERE user_id = ?",
            [$userId]
        );

        return [
            'total' => (int) $stats['total'],
            'inbox' => (int) $stats['inbox_count'],
            'processing' => (int) $stats['processing_count'],
            'done' => (int) $stats['done_count'],
            'urgent' => (int) $stats['urgent_count'],
        ];
    }

    /**
     * Get or create default inbox list
     */
    private function getOrCreateDefaultList(string $userId): string
    {
        $list = $this->db->fetchAssociative(
            "SELECT id FROM lists WHERE user_id = ? AND title = 'Inbox' LIMIT 1",
            [$userId]
        );

        if ($list) {
            return $list['id'];
        }

        $listId = Uuid::uuid4()->toString();
        $this->db->insert('lists', [
            'id' => $listId,
            'user_id' => $userId,
            'title' => 'Inbox',
            'description' => 'Items from Quick Capture',
            'color' => '#6366f1',
            'icon' => 'inbox',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $listId;
    }

    private function getNextPosition(string $table, string $column, string $value): int
    {
        $max = $this->db->fetchOne(
            "SELECT MAX(position) FROM {$table} WHERE {$column} = ?",
            [$value]
        );
        return ($max ?? -1) + 1;
    }

    private function mapPriority(string $priority): int
    {
        return match ($priority) {
            'urgent' => 4,
            'high' => 3,
            'normal' => 2,
            'low' => 1,
            default => 2,
        };
    }
}
