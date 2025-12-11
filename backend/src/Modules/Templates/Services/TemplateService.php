<?php

declare(strict_types=1);

namespace App\Modules\Templates\Services;

use Doctrine\DBAL\Connection;

class TemplateService
{
    private const VALID_TYPES = [
        'document',
        'list',
        'snippet',
        'checklist',
        'kanban_board',
        'project',
        'invoice',
    ];

    public function __construct(
        private Connection $db
    ) {}

    /**
     * Get all templates for a user
     */
    public function getAllTemplates(string $userId, array $filters = []): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select('t.*')
            ->from('templates', 't')
            ->where('t.user_id = :user_id OR t.is_public = 1')
            ->setParameter('user_id', $userId)
            ->orderBy('t.name', 'ASC');

        if (!empty($filters['type'])) {
            $qb->andWhere('t.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('(t.name LIKE :search OR t.description LIKE :search)')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (isset($filters['is_public'])) {
            $qb->andWhere('t.is_public = :is_public')
               ->setParameter('is_public', $filters['is_public'] ? 1 : 0);
        }

        if (!empty($filters['category_id'])) {
            $qb->innerJoin('t', 'template_category_items', 'tci', 't.id = tci.template_id')
               ->andWhere('tci.category_id = :category_id')
               ->setParameter('category_id', $filters['category_id']);
        }

        $templates = $qb->executeQuery()->fetchAllAssociative();

        foreach ($templates as &$template) {
            $template['content'] = json_decode($template['content'], true);
            $template['categories'] = $this->getTemplateCategories($template['id']);
            $template['is_owner'] = $template['user_id'] === $userId;
        }

        return $templates;
    }

    /**
     * Get a single template
     */
    public function getTemplate(string $userId, string $templateId): ?array
    {
        $template = $this->db->fetchAssociative(
            "SELECT * FROM templates WHERE id = ? AND (user_id = ? OR is_public = 1)",
            [$templateId, $userId]
        );

        if ($template) {
            $template['content'] = json_decode($template['content'], true);
            $template['categories'] = $this->getTemplateCategories($templateId);
            $template['is_owner'] = $template['user_id'] === $userId;
        }

        return $template ?: null;
    }

    /**
     * Create a new template
     */
    public function createTemplate(string $userId, array $data): array
    {
        if (!in_array($data['type'] ?? '', self::VALID_TYPES)) {
            throw new \InvalidArgumentException('Invalid template type');
        }

        $id = $this->generateUuid();

        $this->db->insert('templates', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'content' => json_encode($data['content'] ?? []),
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
            'is_public' => $data['is_public'] ?? false,
        ]);

        // Assign categories
        if (!empty($data['category_ids'])) {
            $this->setTemplateCategories($id, $data['category_ids']);
        }

        return $this->getTemplate($userId, $id);
    }

    /**
     * Update a template
     */
    public function updateTemplate(string $userId, string $templateId, array $data): ?array
    {
        $template = $this->db->fetchAssociative(
            "SELECT * FROM templates WHERE id = ? AND user_id = ?",
            [$templateId, $userId]
        );

        if (!$template) {
            return null;
        }

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['content'])) {
            $updateData['content'] = json_encode($data['content']);
        }
        if (array_key_exists('icon', $data)) {
            $updateData['icon'] = $data['icon'];
        }
        if (array_key_exists('color', $data)) {
            $updateData['color'] = $data['color'];
        }
        if (isset($data['is_public'])) {
            $updateData['is_public'] = $data['is_public'];
        }

        if (!empty($updateData)) {
            $this->db->update('templates', $updateData, ['id' => $templateId]);
        }

        // Update categories
        if (isset($data['category_ids'])) {
            $this->setTemplateCategories($templateId, $data['category_ids']);
        }

        return $this->getTemplate($userId, $templateId);
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(string $userId, string $templateId): bool
    {
        $deleted = $this->db->delete('templates', [
            'id' => $templateId,
            'user_id' => $userId,
        ]);

        return $deleted > 0;
    }

    /**
     * Use a template (create item from template)
     */
    public function useTemplate(string $userId, string $templateId): array
    {
        $template = $this->getTemplate($userId, $templateId);
        if (!$template) {
            throw new \InvalidArgumentException('Template not found');
        }

        // Increment usage count
        $this->db->executeStatement(
            "UPDATE templates SET usage_count = usage_count + 1 WHERE id = ?",
            [$templateId]
        );

        // Return the content with metadata for creating the actual item
        return [
            'type' => $template['type'],
            'content' => $template['content'],
            'template_name' => $template['name'],
        ];
    }

    /**
     * Create template from existing item
     */
    public function createFromItem(string $userId, string $type, array $itemData, array $templateData): array
    {
        if (!in_array($type, self::VALID_TYPES)) {
            throw new \InvalidArgumentException('Invalid template type');
        }

        // Extract relevant content based on type
        $content = $this->extractContent($type, $itemData);

        return $this->createTemplate($userId, [
            'name' => $templateData['name'],
            'description' => $templateData['description'] ?? null,
            'type' => $type,
            'content' => $content,
            'icon' => $templateData['icon'] ?? null,
            'color' => $templateData['color'] ?? null,
            'is_public' => $templateData['is_public'] ?? false,
            'category_ids' => $templateData['category_ids'] ?? [],
        ]);
    }

    /**
     * Get template categories
     */
    public function getCategories(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT tc.*,
                    (SELECT COUNT(*) FROM template_category_items WHERE category_id = tc.id) as template_count
             FROM template_categories tc
             WHERE tc.user_id = ?
             ORDER BY tc.sort_order, tc.name",
            [$userId]
        );
    }

    /**
     * Create a category
     */
    public function createCategory(string $userId, array $data): array
    {
        $id = $this->generateUuid();

        $this->db->insert('template_categories', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return $this->db->fetchAssociative(
            "SELECT * FROM template_categories WHERE id = ?",
            [$id]
        );
    }

    /**
     * Update a category
     */
    public function updateCategory(string $userId, string $categoryId, array $data): ?array
    {
        $category = $this->db->fetchAssociative(
            "SELECT * FROM template_categories WHERE id = ? AND user_id = ?",
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
            $this->db->update('template_categories', $updateData, ['id' => $categoryId]);
        }

        return $this->db->fetchAssociative(
            "SELECT * FROM template_categories WHERE id = ?",
            [$categoryId]
        );
    }

    /**
     * Delete a category
     */
    public function deleteCategory(string $userId, string $categoryId): bool
    {
        $deleted = $this->db->delete('template_categories', [
            'id' => $categoryId,
            'user_id' => $userId,
        ]);

        return $deleted > 0;
    }

    /**
     * Get valid template types
     */
    public function getValidTypes(): array
    {
        return self::VALID_TYPES;
    }

    /**
     * Get template categories for a template
     */
    private function getTemplateCategories(string $templateId): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT tc.* FROM template_categories tc
             INNER JOIN template_category_items tci ON tc.id = tci.category_id
             WHERE tci.template_id = ?
             ORDER BY tc.name",
            [$templateId]
        );
    }

    /**
     * Set template categories
     */
    private function setTemplateCategories(string $templateId, array $categoryIds): void
    {
        // Remove existing
        $this->db->delete('template_category_items', ['template_id' => $templateId]);

        // Add new
        foreach ($categoryIds as $categoryId) {
            $this->db->insert('template_category_items', [
                'template_id' => $templateId,
                'category_id' => $categoryId,
            ]);
        }
    }

    /**
     * Extract content from an item for template creation
     */
    private function extractContent(string $type, array $itemData): array
    {
        return match ($type) {
            'document' => [
                'title' => $itemData['title'] ?? 'Untitled',
                'content' => $itemData['content'] ?? '',
            ],
            'list' => [
                'name' => $itemData['name'] ?? 'Untitled List',
                'description' => $itemData['description'] ?? '',
                'items' => array_map(fn($item) => [
                    'content' => $item['content'] ?? '',
                    'priority' => $item['priority'] ?? 'medium',
                ], $itemData['items'] ?? []),
            ],
            'snippet' => [
                'title' => $itemData['title'] ?? 'Untitled',
                'code' => $itemData['code'] ?? '',
                'language' => $itemData['language'] ?? 'plaintext',
                'description' => $itemData['description'] ?? '',
            ],
            'checklist' => [
                'title' => $itemData['title'] ?? 'Untitled',
                'description' => $itemData['description'] ?? '',
                'items' => array_map(fn($item) => [
                    'content' => $item['content'] ?? '',
                ], $itemData['items'] ?? []),
            ],
            'kanban_board' => [
                'name' => $itemData['name'] ?? 'Untitled Board',
                'description' => $itemData['description'] ?? '',
                'columns' => array_map(fn($col) => [
                    'name' => $col['name'] ?? '',
                    'color' => $col['color'] ?? null,
                    'wip_limit' => $col['wip_limit'] ?? null,
                ], $itemData['columns'] ?? []),
            ],
            'project' => [
                'name' => $itemData['name'] ?? 'Untitled Project',
                'description' => $itemData['description'] ?? '',
                'tasks' => array_map(fn($task) => [
                    'title' => $task['title'] ?? '',
                    'description' => $task['description'] ?? '',
                    'priority' => $task['priority'] ?? 'medium',
                ], $itemData['tasks'] ?? []),
            ],
            'invoice' => [
                'items' => array_map(fn($item) => [
                    'description' => $item['description'] ?? '',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                ], $itemData['items'] ?? []),
                'notes' => $itemData['notes'] ?? '',
                'tax_rate' => $itemData['tax_rate'] ?? 0,
            ],
            default => $itemData,
        };
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
