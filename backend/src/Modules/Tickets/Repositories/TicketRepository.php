<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Repositories;

use Doctrine\DBAL\Connection;

class TicketRepository
{
    public function __construct(
        private readonly Connection $db
    ) {}

    // ========================================================================
    // Tickets
    // ========================================================================

    public function findById(string $id): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT t.*,
                    c.name as category_name, c.color as category_color,
                    u.username as creator_name, u.email as creator_email,
                    a.username as assignee_name,
                    p.name as project_name
             FROM tickets t
             LEFT JOIN ticket_categories c ON t.category_id = c.id
             LEFT JOIN users u ON t.user_id = u.id
             LEFT JOIN users a ON t.assigned_to = a.id
             LEFT JOIN projects p ON t.project_id = p.id
             WHERE t.id = ?',
            [$id]
        );

        return $result ?: null;
    }

    public function findByAccessCode(string $code): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT t.*,
                    c.name as category_name, c.color as category_color
             FROM tickets t
             LEFT JOIN ticket_categories c ON t.category_id = c.id
             WHERE t.access_code = ?',
            [$code]
        );

        return $result ?: null;
    }

    public function findByTicketNumber(int $number): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM tickets WHERE ticket_number = ?',
            [$number]
        );

        return $result ?: null;
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT t.*,
                       c.name as category_name, c.color as category_color,
                       u.username as creator_name,
                       a.username as assignee_name,
                       p.name as project_name,
                       (SELECT COUNT(*) FROM ticket_comments WHERE ticket_id = t.id) as comment_count
                FROM tickets t
                LEFT JOIN ticket_categories c ON t.category_id = c.id
                LEFT JOIN users u ON t.user_id = u.id
                LEFT JOIN users a ON t.assigned_to = a.id
                LEFT JOIN projects p ON t.project_id = p.id
                WHERE 1=1';

        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= ' AND t.user_id = ?';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= ' AND t.assigned_to = ?';
            $params[] = $filters['assigned_to'];
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '?'));
                $sql .= " AND t.status IN ($placeholders)";
                $params = array_merge($params, $filters['status']);
            } else {
                $sql .= ' AND t.status = ?';
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['priority'])) {
            $sql .= ' AND t.priority = ?';
            $params[] = $filters['priority'];
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND t.category_id = ?';
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['project_id'])) {
            $sql .= ' AND t.project_id = ?';
            $params[] = $filters['project_id'];
        }

        // Filter by multiple project IDs (for restricted users)
        if (!empty($filters['project_ids']) && is_array($filters['project_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['project_ids']), '?'));
            $sql .= " AND t.project_id IN ($placeholders)";
            $params = array_merge($params, $filters['project_ids']);
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND (t.title LIKE ? OR t.description LIKE ? OR t.ticket_number = ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = (int) preg_replace('/\D/', '', $filters['search']);
        }

        $sql .= ' ORDER BY
                  CASE t.priority
                    WHEN "urgent" THEN 1
                    WHEN "high" THEN 2
                    WHEN "normal" THEN 3
                    WHEN "low" THEN 4
                  END,
                  t.created_at DESC
                  LIMIT ? OFFSET ?';

        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAllAssociative($sql, $params);
    }

    public function count(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) FROM tickets t WHERE 1=1';
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= ' AND t.user_id = ?';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= ' AND t.assigned_to = ?';
            $params[] = $filters['assigned_to'];
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '?'));
                $sql .= " AND t.status IN ($placeholders)";
                $params = array_merge($params, $filters['status']);
            } else {
                $sql .= ' AND t.status = ?';
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['priority'])) {
            $sql .= ' AND t.priority = ?';
            $params[] = $filters['priority'];
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND t.category_id = ?';
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['project_id'])) {
            $sql .= ' AND t.project_id = ?';
            $params[] = $filters['project_id'];
        }

        // Filter by multiple project IDs (for restricted users)
        if (!empty($filters['project_ids']) && is_array($filters['project_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['project_ids']), '?'));
            $sql .= " AND t.project_id IN ($placeholders)";
            $params = array_merge($params, $filters['project_ids']);
        }

        return (int) $this->db->fetchOne($sql, $params);
    }

    public function create(array $data): array
    {
        $this->db->insert('tickets', $data);
        return $this->findById($data['id']);
    }

    public function update(string $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->update('tickets', $data, ['id' => $id]);
    }

    public function delete(string $id): void
    {
        $this->db->delete('tickets', ['id' => $id]);
    }

    public function generateAccessCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = 'TKT-';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while ($this->findByAccessCode($code));

        return $code;
    }

    // ========================================================================
    // Status History
    // ========================================================================

    public function addStatusHistory(string $ticketId, ?string $oldStatus, string $newStatus, ?string $changedBy, ?string $comment = null): void
    {
        $this->db->insert('ticket_status_history', [
            'ticket_id' => $ticketId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $changedBy,
            'comment' => $comment,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getStatusHistory(string $ticketId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT h.*, u.username as changed_by_name
             FROM ticket_status_history h
             LEFT JOIN users u ON h.changed_by = u.id
             WHERE h.ticket_id = ?
             ORDER BY h.created_at DESC',
            [$ticketId]
        );
    }

    // ========================================================================
    // Comments
    // ========================================================================

    public function getComments(string $ticketId, bool $includeInternal = false): array
    {
        $sql = 'SELECT c.*, u.username, u.avatar_url
                FROM ticket_comments c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.ticket_id = ?';

        if (!$includeInternal) {
            $sql .= ' AND c.is_internal = 0';
        }

        $sql .= ' ORDER BY c.created_at ASC';

        return $this->db->fetchAllAssociative($sql, [$ticketId]);
    }

    public function addComment(array $data): array
    {
        $this->db->insert('ticket_comments', $data);

        return $this->db->fetchAssociative(
            'SELECT c.*, u.username, u.avatar_url
             FROM ticket_comments c
             LEFT JOIN users u ON c.user_id = u.id
             WHERE c.id = ?',
            [$data['id']]
        );
    }

    public function deleteComment(string $id): void
    {
        $this->db->delete('ticket_comments', ['id' => $id]);
    }

    // ========================================================================
    // Categories
    // ========================================================================

    public function getCategories(bool $activeOnly = true, bool $nested = false): array
    {
        $sql = 'SELECT * FROM ticket_categories';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';

        $categories = $this->db->fetchAllAssociative($sql);

        if ($nested) {
            return $this->buildCategoryTree($categories);
        }

        return $categories;
    }

    private function buildCategoryTree(array $categories, ?string $parentId = null): array
    {
        $tree = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] === $parentId) {
                $category['children'] = $this->buildCategoryTree($categories, $category['id']);
                $tree[] = $category;
            }
        }
        return $tree;
    }

    public function findCategoryById(string $id): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM ticket_categories WHERE id = ?',
            [$id]
        );
        return $result ?: null;
    }

    public function createCategory(array $data): array
    {
        $this->db->insert('ticket_categories', $data);
        return $this->findCategoryById($data['id']);
    }

    public function updateCategory(string $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->update('ticket_categories', $data, ['id' => $id]);
    }

    public function deleteCategory(string $id): void
    {
        $this->db->delete('ticket_categories', ['id' => $id]);
    }

    // ========================================================================
    // Statistics
    // ========================================================================

    public function getStats(?string $userId = null): array
    {
        $baseWhere = $userId ? 'WHERE assigned_to = ?' : 'WHERE 1=1';
        $params = $userId ? [$userId] : [];

        $stats = [
            'total' => 0,
            'open' => 0,
            'in_progress' => 0,
            'waiting' => 0,
            'resolved' => 0,
            'closed' => 0,
            'by_priority' => [],
        ];

        // Count by status
        $statusCounts = $this->db->fetchAllAssociative(
            "SELECT status, COUNT(*) as count FROM tickets $baseWhere GROUP BY status",
            $params
        );

        foreach ($statusCounts as $row) {
            $stats[$row['status']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        // Count by priority (only open/in_progress)
        $priorityCounts = $this->db->fetchAllAssociative(
            "SELECT priority, COUNT(*) as count FROM tickets
             $baseWhere AND status IN ('open', 'in_progress', 'waiting')
             GROUP BY priority",
            $params
        );

        foreach ($priorityCounts as $row) {
            $stats['by_priority'][$row['priority']] = (int) $row['count'];
        }

        return $stats;
    }
}
