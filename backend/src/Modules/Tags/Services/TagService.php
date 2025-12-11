<?php

declare(strict_types=1);

namespace App\Modules\Tags\Services;

use Doctrine\DBAL\Connection;

class TagService
{
    private const VALID_TAGGABLE_TYPES = [
        'list',
        'document',
        'snippet',
        'bookmark',
        'connection',
        'password',
        'checklist',
        'kanban_board',
        'kanban_card',
        'project',
        'invoice',
        'calendar_event',
    ];

    public function __construct(
        private Connection $db
    ) {}

    /**
     * Get all tags for a user
     */
    public function getAllTags(string $userId, array $filters = []): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select('t.*')
            ->from('tags', 't')
            ->where('t.user_id = :user_id')
            ->setParameter('user_id', $userId)
            ->orderBy('t.name', 'ASC');

        if (!empty($filters['search'])) {
            $qb->andWhere('t.name LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        $tags = $qb->executeQuery()->fetchAllAssociative();

        // Get usage counts per type for each tag
        foreach ($tags as &$tag) {
            $tag['usage_by_type'] = $this->getTagUsageByType($tag['id']);
        }

        return $tags;
    }

    /**
     * Get a single tag
     */
    public function getTag(string $userId, string $tagId): ?array
    {
        $tag = $this->db->fetchAssociative(
            "SELECT * FROM tags WHERE id = ? AND user_id = ?",
            [$tagId, $userId]
        );

        if ($tag) {
            $tag['usage_by_type'] = $this->getTagUsageByType($tagId);
            $tag['items'] = $this->getTaggedItems($tagId);
        }

        return $tag ?: null;
    }

    /**
     * Create a new tag
     */
    public function createTag(string $userId, array $data): array
    {
        $id = $this->generateUuid();

        $this->db->insert('tags', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'color' => $data['color'] ?? '#6366f1',
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
        ]);

        return $this->getTag($userId, $id);
    }

    /**
     * Update a tag
     */
    public function updateTag(string $userId, string $tagId, array $data): ?array
    {
        $tag = $this->getTag($userId, $tagId);
        if (!$tag) {
            return null;
        }

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['color'])) {
            $updateData['color'] = $data['color'];
        }
        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }
        if (array_key_exists('icon', $data)) {
            $updateData['icon'] = $data['icon'];
        }

        if (!empty($updateData)) {
            $this->db->update('tags', $updateData, ['id' => $tagId]);
        }

        return $this->getTag($userId, $tagId);
    }

    /**
     * Delete a tag
     */
    public function deleteTag(string $userId, string $tagId): bool
    {
        $tag = $this->getTag($userId, $tagId);
        if (!$tag) {
            return false;
        }

        $this->db->delete('tags', ['id' => $tagId]);
        return true;
    }

    /**
     * Tag an item
     */
    public function tagItem(string $userId, string $tagId, string $taggableType, string $taggableId): bool
    {
        // Verify tag belongs to user
        $tag = $this->db->fetchOne(
            "SELECT id FROM tags WHERE id = ? AND user_id = ?",
            [$tagId, $userId]
        );

        if (!$tag) {
            return false;
        }

        // Validate taggable type
        if (!in_array($taggableType, self::VALID_TAGGABLE_TYPES)) {
            return false;
        }

        // Check if already tagged
        $existing = $this->db->fetchOne(
            "SELECT id FROM taggables WHERE tag_id = ? AND taggable_type = ? AND taggable_id = ?",
            [$tagId, $taggableType, $taggableId]
        );

        if ($existing) {
            return true; // Already tagged
        }

        $this->db->insert('taggables', [
            'id' => $this->generateUuid(),
            'tag_id' => $tagId,
            'taggable_type' => $taggableType,
            'taggable_id' => $taggableId,
        ]);

        // Update usage count
        $this->db->executeStatement(
            "UPDATE tags SET usage_count = usage_count + 1 WHERE id = ?",
            [$tagId]
        );

        return true;
    }

    /**
     * Remove tag from an item
     */
    public function untagItem(string $userId, string $tagId, string $taggableType, string $taggableId): bool
    {
        // Verify tag belongs to user
        $tag = $this->db->fetchOne(
            "SELECT id FROM tags WHERE id = ? AND user_id = ?",
            [$tagId, $userId]
        );

        if (!$tag) {
            return false;
        }

        $deleted = $this->db->delete('taggables', [
            'tag_id' => $tagId,
            'taggable_type' => $taggableType,
            'taggable_id' => $taggableId,
        ]);

        if ($deleted > 0) {
            // Update usage count
            $this->db->executeStatement(
                "UPDATE tags SET usage_count = GREATEST(usage_count - 1, 0) WHERE id = ?",
                [$tagId]
            );
        }

        return true;
    }

    /**
     * Get all tags for a specific item
     */
    public function getItemTags(string $userId, string $taggableType, string $taggableId): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT t.* FROM tags t
             INNER JOIN taggables tg ON t.id = tg.tag_id
             WHERE t.user_id = ? AND tg.taggable_type = ? AND tg.taggable_id = ?
             ORDER BY t.name",
            [$userId, $taggableType, $taggableId]
        );
    }

    /**
     * Set tags for an item (replace all existing tags)
     */
    public function setItemTags(string $userId, string $taggableType, string $taggableId, array $tagIds): array
    {
        // Get current tags
        $currentTagIds = array_column(
            $this->getItemTags($userId, $taggableType, $taggableId),
            'id'
        );

        // Remove tags that are no longer needed
        foreach ($currentTagIds as $currentTagId) {
            if (!in_array($currentTagId, $tagIds)) {
                $this->untagItem($userId, $currentTagId, $taggableType, $taggableId);
            }
        }

        // Add new tags
        foreach ($tagIds as $tagId) {
            if (!in_array($tagId, $currentTagIds)) {
                $this->tagItem($userId, $tagId, $taggableType, $taggableId);
            }
        }

        return $this->getItemTags($userId, $taggableType, $taggableId);
    }

    /**
     * Search items by tags
     */
    public function searchByTags(string $userId, array $tagIds, ?string $type = null): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select('tg.taggable_type, tg.taggable_id, COUNT(DISTINCT tg.tag_id) as tag_count')
            ->from('taggables', 'tg')
            ->innerJoin('tg', 'tags', 't', 't.id = tg.tag_id')
            ->where('t.user_id = :user_id')
            ->andWhere('tg.tag_id IN (:tag_ids)')
            ->setParameter('user_id', $userId)
            ->setParameter('tag_ids', $tagIds, Connection::PARAM_STR_ARRAY)
            ->groupBy('tg.taggable_type, tg.taggable_id')
            ->having('tag_count = :tag_count')
            ->setParameter('tag_count', count($tagIds));

        if ($type) {
            $qb->andWhere('tg.taggable_type = :type')
               ->setParameter('type', $type);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Get tag usage by type
     */
    private function getTagUsageByType(string $tagId): array
    {
        $results = $this->db->fetchAllAssociative(
            "SELECT taggable_type, COUNT(*) as count
             FROM taggables
             WHERE tag_id = ?
             GROUP BY taggable_type",
            [$tagId]
        );

        $usage = [];
        foreach ($results as $row) {
            $usage[$row['taggable_type']] = (int) $row['count'];
        }

        return $usage;
    }

    /**
     * Get tagged items for a tag
     */
    private function getTaggedItems(string $tagId, int $limit = 50): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT taggable_type, taggable_id, created_at
             FROM taggables
             WHERE tag_id = ?
             ORDER BY created_at DESC
             LIMIT ?",
            [$tagId, $limit],
            ['string', 'integer']
        );
    }

    /**
     * Merge tags (combine multiple tags into one)
     */
    public function mergeTags(string $userId, array $sourceTagIds, string $targetTagId): bool
    {
        // Verify all tags belong to user
        $count = $this->db->fetchOne(
            "SELECT COUNT(*) FROM tags WHERE user_id = ? AND id IN (?)",
            [$userId, [...$sourceTagIds, $targetTagId]],
            ['string', Connection::PARAM_STR_ARRAY]
        );

        $expectedCount = count($sourceTagIds) + 1;
        if ((int) $count !== $expectedCount) {
            return false;
        }

        $this->db->beginTransaction();

        try {
            // Move all taggables from source tags to target tag
            foreach ($sourceTagIds as $sourceTagId) {
                if ($sourceTagId === $targetTagId) {
                    continue;
                }

                // Get items from source tag
                $items = $this->db->fetchAllAssociative(
                    "SELECT taggable_type, taggable_id FROM taggables WHERE tag_id = ?",
                    [$sourceTagId]
                );

                foreach ($items as $item) {
                    // Check if target already has this item
                    $exists = $this->db->fetchOne(
                        "SELECT id FROM taggables WHERE tag_id = ? AND taggable_type = ? AND taggable_id = ?",
                        [$targetTagId, $item['taggable_type'], $item['taggable_id']]
                    );

                    if (!$exists) {
                        // Move to target tag
                        $this->db->executeStatement(
                            "UPDATE taggables SET tag_id = ? WHERE tag_id = ? AND taggable_type = ? AND taggable_id = ?",
                            [$targetTagId, $sourceTagId, $item['taggable_type'], $item['taggable_id']]
                        );
                    }
                }

                // Delete source tag (cascades to taggables)
                $this->db->delete('tags', ['id' => $sourceTagId]);
            }

            // Update target tag usage count
            $newCount = $this->db->fetchOne(
                "SELECT COUNT(*) FROM taggables WHERE tag_id = ?",
                [$targetTagId]
            );
            $this->db->update('tags', ['usage_count' => $newCount], ['id' => $targetTagId]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get valid taggable types
     */
    public function getValidTypes(): array
    {
        return self::VALID_TAGGABLE_TYPES;
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
