<?php

declare(strict_types=1);

namespace App\Modules\Mockup\Services;

use Doctrine\DBAL\Connection;

class MockupService
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Get all templates for a user
     */
    public function getTemplates(string $userId, array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $sql = "SELECT * FROM mockup_templates
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        $items = $this->db->fetchAllAssociative($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset,
        ], [
            'user_id' => \PDO::PARAM_STR,
            'limit' => \PDO::PARAM_INT,
            'offset' => \PDO::PARAM_INT,
        ]);

        // Parse JSON fields
        foreach ($items as &$item) {
            $item['elements'] = json_decode($item['elements'], true);
        }

        // Count total
        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM mockup_templates WHERE user_id = :user_id",
            ['user_id' => $userId]
        );

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * Get a single template
     */
    public function getTemplate(string $id, string $userId): ?array
    {
        $template = $this->db->fetchAssociative(
            "SELECT * FROM mockup_templates WHERE id = :id AND user_id = :user_id",
            ['id' => $id, 'user_id' => $userId]
        );

        if (!$template) {
            return null;
        }

        $template['elements'] = json_decode($template['elements'], true);
        return $template;
    }

    /**
     * Create a new template
     */
    public function createTemplate(string $userId, array $data): array
    {
        $id = $this->generateId();

        $this->db->insert('mockup_templates', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'] ?? 'Untitled Template',
            'description' => $data['description'] ?? '',
            'category' => $data['category'] ?? 'custom',
            'width' => $data['width'] ?? 1920,
            'height' => $data['height'] ?? 1080,
            'aspect_ratio' => $data['aspectRatio'] ?? '16:9',
            'elements' => json_encode($data['elements'] ?? []),
            'transparent_bg' => (int) ($data['transparentBg'] ?? false),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->getTemplate($id, $userId);
    }

    /**
     * Update a template
     */
    public function updateTemplate(string $id, string $userId, array $data): bool
    {
        $fields = [];

        if (isset($data['name'])) {
            $fields['name'] = $data['name'];
        }

        if (isset($data['description'])) {
            $fields['description'] = $data['description'];
        }

        if (isset($data['category'])) {
            $fields['category'] = $data['category'];
        }

        if (isset($data['elements'])) {
            $fields['elements'] = json_encode($data['elements']);
        }

        if (isset($data['width'])) {
            $fields['width'] = $data['width'];
        }

        if (isset($data['height'])) {
            $fields['height'] = $data['height'];
        }

        if (isset($data['transparentBg'])) {
            $fields['transparent_bg'] = (int) $data['transparentBg'];
        }

        if (empty($fields)) {
            return false;
        }

        $fields['updated_at'] = date('Y-m-d H:i:s');

        $affected = $this->db->update(
            'mockup_templates',
            $fields,
            ['id' => $id, 'user_id' => $userId]
        );

        return $affected > 0;
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(string $id, string $userId): bool
    {
        $affected = $this->db->delete('mockup_templates', [
            'id' => $id,
            'user_id' => $userId,
        ]);

        return $affected > 0;
    }

    // ==================== Drafts ====================

    /**
     * Get all drafts for a user
     */
    public function getDrafts(string $userId, array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $sql = "SELECT * FROM mockup_drafts
                WHERE user_id = :user_id
                ORDER BY updated_at DESC
                LIMIT :limit OFFSET :offset";

        $items = $this->db->fetchAllAssociative($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset,
        ], [
            'user_id' => \PDO::PARAM_STR,
            'limit' => \PDO::PARAM_INT,
            'offset' => \PDO::PARAM_INT,
        ]);

        // Parse JSON fields
        foreach ($items as &$item) {
            $item['elements'] = json_decode($item['elements'], true);
        }

        // Count total
        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM mockup_drafts WHERE user_id = :user_id",
            ['user_id' => $userId]
        );

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * Get a single draft
     */
    public function getDraft(string $id, string $userId): ?array
    {
        $draft = $this->db->fetchAssociative(
            "SELECT * FROM mockup_drafts WHERE id = :id AND user_id = :user_id",
            ['id' => $id, 'user_id' => $userId]
        );

        if (!$draft) {
            return null;
        }

        $draft['elements'] = json_decode($draft['elements'], true);
        return $draft;
    }

    /**
     * Create or update a draft (upsert)
     */
    public function saveDraft(string $userId, array $data): array
    {
        $id = $data['id'] ?? $this->generateId();

        // Check if draft exists
        $existing = $this->getDraft($id, $userId);

        if ($existing) {
            $this->updateDraft($id, $userId, $data);
        } else {
            $this->db->insert('mockup_drafts', [
                'id' => $id,
                'user_id' => $userId,
                'name' => $data['name'] ?? 'Untitled Draft',
                'template_id' => $data['templateId'] ?? null,
                'width' => $data['width'] ?? 1920,
                'height' => $data['height'] ?? 1080,
                'elements' => json_encode($data['elements'] ?? []),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->getDraft($id, $userId);
    }

    /**
     * Update a draft
     */
    public function updateDraft(string $id, string $userId, array $data): bool
    {
        $fields = [];

        if (isset($data['name'])) {
            $fields['name'] = $data['name'];
        }

        if (isset($data['elements'])) {
            $fields['elements'] = json_encode($data['elements']);
        }

        if (empty($fields)) {
            return false;
        }

        $fields['updated_at'] = date('Y-m-d H:i:s');

        $affected = $this->db->update(
            'mockup_drafts',
            $fields,
            ['id' => $id, 'user_id' => $userId]
        );

        return $affected > 0;
    }

    /**
     * Delete a draft
     */
    public function deleteDraft(string $id, string $userId): bool
    {
        $affected = $this->db->delete('mockup_drafts', [
            'id' => $id,
            'user_id' => $userId,
        ]);

        return $affected > 0;
    }

    // ==================== Helpers ====================

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
