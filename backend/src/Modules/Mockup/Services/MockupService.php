<?php

declare(strict_types=1);

namespace App\Modules\Mockup\Services;

use PDO;

class MockupService
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    /**
     * Get all templates for a user
     */
    public function getTemplates(int $userId, array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $sql = "SELECT * FROM mockup_templates
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse JSON fields
        foreach ($items as &$item) {
            $item['elements'] = json_decode($item['elements'], true);
        }

        // Count total
        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM mockup_templates WHERE user_id = :user_id"
        );
        $countStmt->execute([':user_id' => $userId]);
        $total = (int) $countStmt->fetchColumn();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit),
            ],
        ];
    }

    /**
     * Get a single template
     */
    public function getTemplate(string $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM mockup_templates WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            return null;
        }

        $template['elements'] = json_decode($template['elements'], true);
        return $template;
    }

    /**
     * Create a new template
     */
    public function createTemplate(int $userId, array $data): array
    {
        $id = $this->generateId();

        $stmt = $this->pdo->prepare(
            "INSERT INTO mockup_templates (id, user_id, name, description, category, width, height, aspect_ratio, elements, transparent_bg, created_at, updated_at)
             VALUES (:id, :user_id, :name, :description, :category, :width, :height, :aspect_ratio, :elements, :transparent_bg, NOW(), NOW())"
        );

        $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
            ':name' => $data['name'] ?? 'Untitled Template',
            ':description' => $data['description'] ?? '',
            ':category' => $data['category'] ?? 'custom',
            ':width' => $data['width'] ?? 1920,
            ':height' => $data['height'] ?? 1080,
            ':aspect_ratio' => $data['aspectRatio'] ?? '16:9',
            ':elements' => json_encode($data['elements'] ?? []),
            ':transparent_bg' => (int) ($data['transparentBg'] ?? false),
        ]);

        return $this->getTemplate($id, $userId);
    }

    /**
     * Update a template
     */
    public function updateTemplate(string $id, int $userId, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id, ':user_id' => $userId];

        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params[':name'] = $data['name'];
        }

        if (isset($data['description'])) {
            $fields[] = 'description = :description';
            $params[':description'] = $data['description'];
        }

        if (isset($data['category'])) {
            $fields[] = 'category = :category';
            $params[':category'] = $data['category'];
        }

        if (isset($data['elements'])) {
            $fields[] = 'elements = :elements';
            $params[':elements'] = json_encode($data['elements']);
        }

        if (isset($data['width'])) {
            $fields[] = 'width = :width';
            $params[':width'] = $data['width'];
        }

        if (isset($data['height'])) {
            $fields[] = 'height = :height';
            $params[':height'] = $data['height'];
        }

        if (isset($data['transparentBg'])) {
            $fields[] = 'transparent_bg = :transparent_bg';
            $params[':transparent_bg'] = (int) $data['transparentBg'];
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = NOW()';

        $sql = "UPDATE mockup_templates SET " . implode(', ', $fields) .
               " WHERE id = :id AND user_id = :user_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(string $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM mockup_templates WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    // ==================== Drafts ====================

    /**
     * Get all drafts for a user
     */
    public function getDrafts(int $userId, array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $sql = "SELECT * FROM mockup_drafts
                WHERE user_id = :user_id
                ORDER BY updated_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse JSON fields
        foreach ($items as &$item) {
            $item['elements'] = json_decode($item['elements'], true);
        }

        // Count total
        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM mockup_drafts WHERE user_id = :user_id"
        );
        $countStmt->execute([':user_id' => $userId]);
        $total = (int) $countStmt->fetchColumn();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit),
            ],
        ];
    }

    /**
     * Get a single draft
     */
    public function getDraft(string $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM mockup_drafts WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $draft = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$draft) {
            return null;
        }

        $draft['elements'] = json_decode($draft['elements'], true);
        return $draft;
    }

    /**
     * Create or update a draft (upsert)
     */
    public function saveDraft(int $userId, array $data): array
    {
        $id = $data['id'] ?? $this->generateId();

        // Check if draft exists
        $existing = $this->getDraft($id, $userId);

        if ($existing) {
            $this->updateDraft($id, $userId, $data);
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO mockup_drafts (id, user_id, name, template_id, width, height, elements, created_at, updated_at)
                 VALUES (:id, :user_id, :name, :template_id, :width, :height, :elements, NOW(), NOW())"
            );

            $stmt->execute([
                ':id' => $id,
                ':user_id' => $userId,
                ':name' => $data['name'] ?? 'Untitled Draft',
                ':template_id' => $data['templateId'] ?? null,
                ':width' => $data['width'] ?? 1920,
                ':height' => $data['height'] ?? 1080,
                ':elements' => json_encode($data['elements'] ?? []),
            ]);
        }

        return $this->getDraft($id, $userId);
    }

    /**
     * Update a draft
     */
    public function updateDraft(string $id, int $userId, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id, ':user_id' => $userId];

        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params[':name'] = $data['name'];
        }

        if (isset($data['elements'])) {
            $fields[] = 'elements = :elements';
            $params[':elements'] = json_encode($data['elements']);
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = NOW()';

        $sql = "UPDATE mockup_drafts SET " . implode(', ', $fields) .
               " WHERE id = :id AND user_id = :user_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a draft
     */
    public function deleteDraft(string $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM mockup_drafts WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    // ==================== Helpers ====================

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
