<?php

declare(strict_types=1);

namespace App\Modules\RecurringTasks\Services;

use Doctrine\DBAL\Connection;

class RecurringTaskService
{
    public function __construct(
        private Connection $db
    ) {}

    /**
     * Get all recurring tasks for a user
     */
    public function getAllTasks(string $userId, array $filters = []): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select('rt.*', 'rtc.name as category_name', 'rtc.color as category_color', 'rtc.icon as category_icon')
            ->from('recurring_tasks', 'rt')
            ->leftJoin('rt', 'recurring_task_categories', 'rtc', 'rt.category_id = rtc.id')
            ->where('rt.user_id = :user_id')
            ->setParameter('user_id', $userId)
            ->orderBy('rt.next_occurrence', 'ASC');

        if (isset($filters['is_active'])) {
            $qb->andWhere('rt.is_active = :is_active')
               ->setParameter('is_active', $filters['is_active'] ? 1 : 0);
        }

        if (!empty($filters['category_id'])) {
            $qb->andWhere('rt.category_id = :category_id')
               ->setParameter('category_id', $filters['category_id']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('(rt.title LIKE :search OR rt.description LIKE :search)')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        $tasks = $qb->executeQuery()->fetchAllAssociative();

        foreach ($tasks as &$task) {
            $task['tags'] = $task['tags'] ? json_decode($task['tags'], true) : [];
        }

        return $tasks;
    }

    /**
     * Get a single recurring task
     */
    public function getTask(string $userId, string $taskId): ?array
    {
        $task = $this->db->fetchAssociative(
            "SELECT rt.*, rtc.name as category_name, rtc.color as category_color, rtc.icon as category_icon
             FROM recurring_tasks rt
             LEFT JOIN recurring_task_categories rtc ON rt.category_id = rtc.id
             WHERE rt.id = ? AND rt.user_id = ?",
            [$taskId, $userId]
        );

        if ($task) {
            $task['tags'] = $task['tags'] ? json_decode($task['tags'], true) : [];
            $task['instances'] = $this->getTaskInstances($taskId, 10);
        }

        return $task ?: null;
    }

    /**
     * Create a new recurring task
     */
    public function createTask(string $userId, array $data): array
    {
        $id = $this->generateUuid();

        $nextOccurrence = $this->calculateNextOccurrence($data);

        $this->db->insert('recurring_tasks', [
            'id' => $id,
            'user_id' => $userId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'frequency' => $data['frequency'] ?? 'weekly',
            'interval_value' => $data['interval_value'] ?? 1,
            'days_of_week' => $data['days_of_week'] ?? null,
            'day_of_month' => $data['day_of_month'] ?? null,
            'week_of_month' => $data['week_of_month'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'due_time' => $data['due_time'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'estimated_duration' => $data['estimated_duration'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'target_type' => $data['target_type'] ?? 'list',
            'target_id' => $data['target_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'next_occurrence' => $nextOccurrence,
            'color' => $data['color'] ?? null,
            'icon' => $data['icon'] ?? null,
            'tags' => !empty($data['tags']) ? json_encode($data['tags']) : null,
        ]);

        return $this->getTask($userId, $id);
    }

    /**
     * Update a recurring task
     */
    public function updateTask(string $userId, string $taskId, array $data): ?array
    {
        $task = $this->getTask($userId, $taskId);
        if (!$task) {
            return null;
        }

        $updateData = [];
        $recalculateNext = false;

        $allowedFields = [
            'title', 'description', 'frequency', 'interval_value', 'days_of_week',
            'day_of_month', 'week_of_month', 'start_date', 'end_date', 'due_time',
            'priority', 'estimated_duration', 'category_id', 'target_type', 'target_id',
            'is_active', 'color', 'icon'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
                if (in_array($field, ['frequency', 'interval_value', 'days_of_week', 'day_of_month', 'week_of_month', 'start_date'])) {
                    $recalculateNext = true;
                }
            }
        }

        if (array_key_exists('tags', $data)) {
            $updateData['tags'] = !empty($data['tags']) ? json_encode($data['tags']) : null;
        }

        if ($recalculateNext) {
            $mergedData = array_merge($task, $data);
            $updateData['next_occurrence'] = $this->calculateNextOccurrence($mergedData);
        }

        if (!empty($updateData)) {
            $this->db->update('recurring_tasks', $updateData, ['id' => $taskId]);
        }

        return $this->getTask($userId, $taskId);
    }

    /**
     * Delete a recurring task
     */
    public function deleteTask(string $userId, string $taskId): bool
    {
        $deleted = $this->db->delete('recurring_tasks', [
            'id' => $taskId,
            'user_id' => $userId,
        ]);

        return $deleted > 0;
    }

    /**
     * Toggle task active status
     */
    public function toggleActive(string $userId, string $taskId): ?array
    {
        $task = $this->getTask($userId, $taskId);
        if (!$task) {
            return null;
        }

        $newStatus = !$task['is_active'];
        $updateData = ['is_active' => $newStatus];

        if ($newStatus) {
            // Recalculate next occurrence when reactivating
            $updateData['next_occurrence'] = $this->calculateNextOccurrence($task);
        }

        $this->db->update('recurring_tasks', $updateData, ['id' => $taskId]);

        return $this->getTask($userId, $taskId);
    }

    /**
     * Get due tasks (tasks that need to generate items)
     */
    public function getDueTasks(): array
    {
        $today = date('Y-m-d');

        return $this->db->fetchAllAssociative(
            "SELECT rt.*, u.id as user_id
             FROM recurring_tasks rt
             INNER JOIN users u ON rt.user_id = u.id
             WHERE rt.is_active = 1
               AND rt.next_occurrence <= ?
               AND (rt.end_date IS NULL OR rt.end_date >= ?)",
            [$today, $today]
        );
    }

    /**
     * Process a recurring task (generate the actual task item)
     */
    public function processTask(string $taskId): array
    {
        $task = $this->db->fetchAssociative(
            "SELECT * FROM recurring_tasks WHERE id = ?",
            [$taskId]
        );

        if (!$task || !$task['is_active']) {
            return ['success' => false, 'error' => 'Task not found or inactive'];
        }

        $today = date('Y-m-d');

        // Create instance record
        $instanceId = $this->generateUuid();
        $this->db->insert('recurring_task_instances', [
            'id' => $instanceId,
            'recurring_task_id' => $taskId,
            'scheduled_date' => $today,
            'status' => 'pending',
        ]);

        // Create the actual task based on target_type
        try {
            $createdItem = $this->createTaskItem($task);

            // Update instance with created item info
            $this->db->update('recurring_task_instances', [
                'status' => 'created',
                'created_item_type' => $task['target_type'],
                'created_item_id' => $createdItem['id'] ?? null,
            ], ['id' => $instanceId]);

            // Calculate and set next occurrence
            $nextOccurrence = $this->calculateNextOccurrence($task, $today);
            $this->db->update('recurring_tasks', [
                'last_generated_date' => $today,
                'next_occurrence' => $nextOccurrence,
            ], ['id' => $taskId]);

            return [
                'success' => true,
                'created_item' => $createdItem,
                'next_occurrence' => $nextOccurrence,
            ];
        } catch (\Exception $e) {
            $this->db->update('recurring_task_instances', [
                'status' => 'failed',
            ], ['id' => $instanceId]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get task instances
     */
    public function getTaskInstances(string $taskId, int $limit = 20): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT * FROM recurring_task_instances
             WHERE recurring_task_id = ?
             ORDER BY scheduled_date DESC
             LIMIT ?",
            [$taskId, $limit],
            ['string', 'integer']
        );
    }

    /**
     * Skip an upcoming occurrence
     */
    public function skipOccurrence(string $userId, string $taskId): ?array
    {
        $task = $this->getTask($userId, $taskId);
        if (!$task) {
            return null;
        }

        $today = date('Y-m-d');

        // Log the skipped occurrence
        $this->db->insert('recurring_task_instances', [
            'id' => $this->generateUuid(),
            'recurring_task_id' => $taskId,
            'scheduled_date' => $task['next_occurrence'],
            'status' => 'skipped',
        ]);

        // Calculate next occurrence
        $nextOccurrence = $this->calculateNextOccurrence($task, $task['next_occurrence']);
        $this->db->update('recurring_tasks', [
            'next_occurrence' => $nextOccurrence,
        ], ['id' => $taskId]);

        return $this->getTask($userId, $taskId);
    }

    // Categories

    /**
     * Get all categories
     */
    public function getCategories(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT rtc.*, (SELECT COUNT(*) FROM recurring_tasks WHERE category_id = rtc.id) as task_count
             FROM recurring_task_categories rtc
             WHERE rtc.user_id = ?
             ORDER BY rtc.sort_order, rtc.name",
            [$userId]
        );
    }

    /**
     * Create a category
     */
    public function createCategory(string $userId, array $data): array
    {
        $id = $this->generateUuid();

        $this->db->insert('recurring_task_categories', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return $this->db->fetchAssociative(
            "SELECT * FROM recurring_task_categories WHERE id = ?",
            [$id]
        );
    }

    /**
     * Update a category
     */
    public function updateCategory(string $userId, string $categoryId, array $data): ?array
    {
        $category = $this->db->fetchAssociative(
            "SELECT * FROM recurring_task_categories WHERE id = ? AND user_id = ?",
            [$categoryId, $userId]
        );

        if (!$category) {
            return null;
        }

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (array_key_exists('icon', $data)) {
            $updateData['icon'] = $data['icon'];
        }
        if (array_key_exists('color', $data)) {
            $updateData['color'] = $data['color'];
        }
        if (isset($data['sort_order'])) {
            $updateData['sort_order'] = $data['sort_order'];
        }

        if (!empty($updateData)) {
            $this->db->update('recurring_task_categories', $updateData, ['id' => $categoryId]);
        }

        return $this->db->fetchAssociative(
            "SELECT * FROM recurring_task_categories WHERE id = ?",
            [$categoryId]
        );
    }

    /**
     * Delete a category
     */
    public function deleteCategory(string $userId, string $categoryId): bool
    {
        // Unset category from tasks
        $this->db->executeStatement(
            "UPDATE recurring_tasks SET category_id = NULL WHERE category_id = ? AND user_id = ?",
            [$categoryId, $userId]
        );

        $deleted = $this->db->delete('recurring_task_categories', [
            'id' => $categoryId,
            'user_id' => $userId,
        ]);

        return $deleted > 0;
    }

    /**
     * Calculate next occurrence based on frequency
     */
    private function calculateNextOccurrence(array $task, ?string $fromDate = null): ?string
    {
        $startDate = $fromDate ?? $task['start_date'];
        $date = new \DateTime($startDate);
        $today = new \DateTime();

        // If start date is in the future, use it
        if ($date > $today && !$fromDate) {
            return $date->format('Y-m-d');
        }

        // Calculate based on frequency
        switch ($task['frequency']) {
            case 'daily':
                $date->modify('+' . ($task['interval_value'] ?? 1) . ' day');
                break;

            case 'weekly':
                if (!empty($task['days_of_week'])) {
                    // Find next matching day
                    $days = explode(',', $task['days_of_week']);
                    $date->modify('+1 day');
                    $maxIterations = 14;
                    $i = 0;
                    while ($i < $maxIterations) {
                        if (in_array($date->format('w'), $days)) {
                            break;
                        }
                        $date->modify('+1 day');
                        $i++;
                    }
                } else {
                    $date->modify('+' . ($task['interval_value'] ?? 1) . ' week');
                }
                break;

            case 'biweekly':
                $date->modify('+2 weeks');
                break;

            case 'monthly':
                if (!empty($task['day_of_month'])) {
                    $date->modify('first day of next month');
                    if ($task['day_of_month'] === 'last') {
                        $date->modify('last day of this month');
                    } else {
                        $dayNum = min((int) $task['day_of_month'], (int) $date->format('t'));
                        $date->setDate((int) $date->format('Y'), (int) $date->format('m'), $dayNum);
                    }
                } else {
                    $date->modify('+' . ($task['interval_value'] ?? 1) . ' month');
                }
                break;

            case 'yearly':
                $date->modify('+' . ($task['interval_value'] ?? 1) . ' year');
                break;

            case 'custom':
                $date->modify('+' . ($task['interval_value'] ?? 1) . ' day');
                break;
        }

        // Ensure it's not in the past
        while ($date <= $today) {
            $date = new \DateTime($this->calculateNextOccurrence($task, $date->format('Y-m-d')));
        }

        // Check end date
        if (!empty($task['end_date']) && $date > new \DateTime($task['end_date'])) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    /**
     * Create the actual task item based on target type
     */
    private function createTaskItem(array $task): array
    {
        $today = date('Y-m-d');
        $dueDate = $task['due_time']
            ? $today . ' ' . $task['due_time']
            : $today;

        switch ($task['target_type']) {
            case 'list':
                if ($task['target_id']) {
                    // Add item to existing list
                    $itemId = $this->generateUuid();
                    $this->db->insert('list_items', [
                        'id' => $itemId,
                        'list_id' => $task['target_id'],
                        'content' => $task['title'],
                        'notes' => $task['description'],
                        'priority' => $task['priority'],
                        'due_date' => $dueDate,
                        'is_completed' => 0,
                    ]);
                    return ['id' => $itemId, 'type' => 'list_item'];
                }
                break;

            case 'checklist':
                if ($task['target_id']) {
                    $itemId = $this->generateUuid();
                    $this->db->insert('checklist_items', [
                        'id' => $itemId,
                        'checklist_id' => $task['target_id'],
                        'content' => $task['title'],
                        'notes' => $task['description'],
                        'is_completed' => 0,
                    ]);
                    return ['id' => $itemId, 'type' => 'checklist_item'];
                }
                break;

            case 'kanban':
                if ($task['target_id']) {
                    // target_id is the column_id
                    $cardId = $this->generateUuid();
                    $this->db->insert('kanban_cards', [
                        'id' => $cardId,
                        'column_id' => $task['target_id'],
                        'title' => $task['title'],
                        'description' => $task['description'],
                        'priority' => $task['priority'],
                        'due_date' => $dueDate,
                        'color' => $task['color'],
                    ]);
                    return ['id' => $cardId, 'type' => 'kanban_card'];
                }
                break;

            case 'project':
                if ($task['target_id']) {
                    $taskId = $this->generateUuid();
                    $this->db->insert('project_tasks', [
                        'id' => $taskId,
                        'project_id' => $task['target_id'],
                        'title' => $task['title'],
                        'description' => $task['description'],
                        'priority' => $task['priority'],
                        'due_date' => $dueDate,
                        'status' => 'todo',
                    ]);
                    return ['id' => $taskId, 'type' => 'project_task'];
                }
                break;
        }

        return ['id' => null, 'type' => null];
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
