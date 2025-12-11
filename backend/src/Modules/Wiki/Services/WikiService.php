<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Services;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class WikiService
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Get all wiki pages for a user
     */
    public function getPages(string $userId, array $filters = []): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select('p.*', 'c.name as category_name', 'c.color as category_color')
            ->from('wiki_pages', 'p')
            ->leftJoin('p', 'wiki_categories', 'c', 'p.category_id = c.id')
            ->where('p.user_id = :user_id')
            ->setParameter('user_id', $userId)
            ->orderBy('p.is_pinned', 'DESC')
            ->addOrderBy('p.updated_at', 'DESC');

        if (!empty($filters['category_id'])) {
            $qb->andWhere('p.category_id = :category_id')
               ->setParameter('category_id', $filters['category_id']);
        }

        if (!empty($filters['parent_id'])) {
            $qb->andWhere('p.parent_id = :parent_id')
               ->setParameter('parent_id', $filters['parent_id']);
        } elseif (isset($filters['root_only']) && $filters['root_only']) {
            $qb->andWhere('p.parent_id IS NULL');
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('(p.title LIKE :search OR p.content LIKE :search)')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (isset($filters['is_published'])) {
            $qb->andWhere('p.is_published = :is_published')
               ->setParameter('is_published', $filters['is_published']);
        }

        $pages = $qb->executeQuery()->fetchAllAssociative();

        // Get tags for each page
        foreach ($pages as &$page) {
            $page['tags'] = $this->getPageTags($page['id']);
        }

        return $pages;
    }

    /**
     * Get a single wiki page by ID or slug
     */
    public function getPage(string $userId, string $identifier): ?array
    {
        $qb = $this->db->createQueryBuilder()
            ->select('p.*', 'c.name as category_name', 'c.color as category_color')
            ->from('wiki_pages', 'p')
            ->leftJoin('p', 'wiki_categories', 'c', 'p.category_id = c.id')
            ->where('p.user_id = :user_id')
            ->setParameter('user_id', $userId);

        // Check if identifier is UUID or slug
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            $qb->andWhere('p.id = :id')->setParameter('id', $identifier);
        } else {
            $qb->andWhere('p.slug = :slug')->setParameter('slug', $identifier);
        }

        $page = $qb->executeQuery()->fetchAssociative();

        if (!$page) {
            return null;
        }

        // Increment view count
        $this->db->executeStatement(
            'UPDATE wiki_pages SET view_count = view_count + 1 WHERE id = ?',
            [$page['id']]
        );

        // Get tags
        $page['tags'] = $this->getPageTags($page['id']);

        // Get backlinks
        $page['backlinks'] = $this->getBacklinks($page['id']);

        // Get children
        $page['children'] = $this->getChildren($userId, $page['id']);

        return $page;
    }

    /**
     * Create a new wiki page
     */
    public function createPage(string $userId, array $data): array
    {
        $id = Uuid::uuid4()->toString();
        $slug = $this->generateSlug($userId, $data['title'], $data['slug'] ?? null);
        $content = $data['content'] ?? '';

        // Calculate word count and reading time
        $wordCount = str_word_count(strip_tags($content));
        $readingTime = max(1, (int) ceil($wordCount / 200));

        // Generate excerpt
        $excerpt = $this->generateExcerpt($content);

        $this->db->insert('wiki_pages', [
            'id' => $id,
            'user_id' => $userId,
            'slug' => $slug,
            'title' => $data['title'],
            'content' => $content,
            'excerpt' => $excerpt,
            'icon' => $data['icon'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'is_published' => $data['is_published'] ?? false,
            'is_pinned' => $data['is_pinned'] ?? false,
            'parent_id' => $data['parent_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'word_count' => $wordCount,
            'reading_time' => $readingTime,
            'last_edited_by' => $userId,
            'published_at' => !empty($data['is_published']) ? date('Y-m-d H:i:s') : null,
        ]);

        // Handle tags
        if (!empty($data['tags'])) {
            $this->syncPageTags($id, $data['tags']);
        }

        // Extract and store wiki links
        $this->extractAndStoreLinks($userId, $id, $content);

        // Create initial history entry
        $this->createHistoryEntry($id, $data['title'], $content, $userId, 'Created page');

        return $this->getPage($userId, $id);
    }

    /**
     * Update a wiki page
     */
    public function updatePage(string $userId, string $pageId, array $data): ?array
    {
        $page = $this->getPage($userId, $pageId);
        if (!$page) {
            return null;
        }

        $updates = [];
        $types = [];

        if (isset($data['title'])) {
            $updates['title'] = $data['title'];
            // Update slug if title changed and no custom slug
            if (!isset($data['slug'])) {
                $updates['slug'] = $this->generateSlug($userId, $data['title'], null, $pageId);
            }
        }

        if (isset($data['slug'])) {
            $updates['slug'] = $this->generateSlug($userId, $data['title'] ?? $page['title'], $data['slug'], $pageId);
        }

        if (isset($data['content'])) {
            $updates['content'] = $data['content'];
            $updates['word_count'] = str_word_count(strip_tags($data['content']));
            $updates['reading_time'] = max(1, (int) ceil($updates['word_count'] / 200));
            $updates['excerpt'] = $this->generateExcerpt($data['content']);
        }

        if (array_key_exists('icon', $data)) {
            $updates['icon'] = $data['icon'];
        }

        if (array_key_exists('cover_image', $data)) {
            $updates['cover_image'] = $data['cover_image'];
        }

        if (isset($data['is_published'])) {
            $updates['is_published'] = $data['is_published'];
            if ($data['is_published'] && !$page['published_at']) {
                $updates['published_at'] = date('Y-m-d H:i:s');
            }
        }

        if (isset($data['is_pinned'])) {
            $updates['is_pinned'] = $data['is_pinned'];
        }

        if (array_key_exists('parent_id', $data)) {
            $updates['parent_id'] = $data['parent_id'];
        }

        if (array_key_exists('category_id', $data)) {
            $updates['category_id'] = $data['category_id'];
        }

        $updates['last_edited_by'] = $userId;

        if (!empty($updates)) {
            $this->db->update('wiki_pages', $updates, ['id' => $pageId]);
        }

        // Handle tags
        if (isset($data['tags'])) {
            $this->syncPageTags($pageId, $data['tags']);
        }

        // Update wiki links if content changed
        if (isset($data['content'])) {
            $this->extractAndStoreLinks($userId, $pageId, $data['content']);

            // Create history entry
            $this->createHistoryEntry(
                $pageId,
                $data['title'] ?? $page['title'],
                $data['content'],
                $userId,
                $data['change_note'] ?? null
            );
        }

        return $this->getPage($userId, $pageId);
    }

    /**
     * Delete a wiki page
     */
    public function deletePage(string $userId, string $pageId): bool
    {
        $result = $this->db->delete('wiki_pages', [
            'id' => $pageId,
            'user_id' => $userId
        ]);

        return $result > 0;
    }

    /**
     * Get page history
     */
    public function getPageHistory(string $userId, string $pageId): array
    {
        // Verify ownership
        $page = $this->db->fetchAssociative(
            'SELECT id FROM wiki_pages WHERE id = ? AND user_id = ?',
            [$pageId, $userId]
        );

        if (!$page) {
            return [];
        }

        return $this->db->fetchAllAssociative(
            'SELECT h.*, u.username as changed_by_name
             FROM wiki_page_history h
             LEFT JOIN users u ON h.changed_by = u.id
             WHERE h.page_id = ?
             ORDER BY h.created_at DESC',
            [$pageId]
        );
    }

    /**
     * Restore page from history
     */
    public function restoreFromHistory(string $userId, string $pageId, string $historyId): ?array
    {
        $history = $this->db->fetchAssociative(
            'SELECT h.* FROM wiki_page_history h
             JOIN wiki_pages p ON h.page_id = p.id
             WHERE h.id = ? AND p.user_id = ?',
            [$historyId, $userId]
        );

        if (!$history) {
            return null;
        }

        return $this->updatePage($userId, $pageId, [
            'title' => $history['title'],
            'content' => $history['content'],
            'change_note' => 'Restored from version ' . $history['version_number']
        ]);
    }

    // Categories

    /**
     * Get all categories
     */
    public function getCategories(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT c.*, COUNT(p.id) as page_count
             FROM wiki_categories c
             LEFT JOIN wiki_pages p ON c.id = p.category_id
             WHERE c.user_id = ?
             GROUP BY c.id
             ORDER BY c.position ASC, c.name ASC',
            [$userId]
        );
    }

    /**
     * Create category
     */
    public function createCategory(string $userId, array $data): array
    {
        $id = Uuid::uuid4()->toString();
        $slug = $this->generateCategorySlug($userId, $data['name'], $data['slug'] ?? null);

        $this->db->insert('wiki_categories', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#6366f1',
            'icon' => $data['icon'] ?? null,
            'position' => $data['position'] ?? 0,
        ]);

        return $this->db->fetchAssociative(
            'SELECT * FROM wiki_categories WHERE id = ?',
            [$id]
        );
    }

    /**
     * Update category
     */
    public function updateCategory(string $userId, string $categoryId, array $data): ?array
    {
        $category = $this->db->fetchAssociative(
            'SELECT * FROM wiki_categories WHERE id = ? AND user_id = ?',
            [$categoryId, $userId]
        );

        if (!$category) {
            return null;
        }

        $updates = [];

        if (isset($data['name'])) {
            $updates['name'] = $data['name'];
        }
        if (isset($data['slug'])) {
            $updates['slug'] = $this->generateCategorySlug($userId, $data['name'] ?? $category['name'], $data['slug'], $categoryId);
        }
        if (array_key_exists('description', $data)) {
            $updates['description'] = $data['description'];
        }
        if (isset($data['color'])) {
            $updates['color'] = $data['color'];
        }
        if (array_key_exists('icon', $data)) {
            $updates['icon'] = $data['icon'];
        }
        if (isset($data['position'])) {
            $updates['position'] = $data['position'];
        }

        if (!empty($updates)) {
            $this->db->update('wiki_categories', $updates, ['id' => $categoryId]);
        }

        return $this->db->fetchAssociative(
            'SELECT * FROM wiki_categories WHERE id = ?',
            [$categoryId]
        );
    }

    /**
     * Delete category
     */
    public function deleteCategory(string $userId, string $categoryId): bool
    {
        $result = $this->db->delete('wiki_categories', [
            'id' => $categoryId,
            'user_id' => $userId
        ]);

        return $result > 0;
    }

    // Graph and Links

    /**
     * Get graph data for visualization
     */
    public function getGraphData(string $userId): array
    {
        // Get all pages as nodes
        $pages = $this->db->fetchAllAssociative(
            'SELECT id, title, slug, category_id FROM wiki_pages WHERE user_id = ?',
            [$userId]
        );

        $nodes = [];
        foreach ($pages as $page) {
            $nodes[] = [
                'id' => $page['id'],
                'label' => $page['title'],
                'slug' => $page['slug'],
                'category_id' => $page['category_id']
            ];
        }

        // Get all links as edges
        $links = $this->db->fetchAllAssociative(
            'SELECT l.source_page_id, l.target_page_id, l.link_text
             FROM wiki_links l
             JOIN wiki_pages p ON l.source_page_id = p.id
             WHERE p.user_id = ?',
            [$userId]
        );

        $edges = [];
        foreach ($links as $link) {
            $edges[] = [
                'source' => $link['source_page_id'],
                'target' => $link['target_page_id'],
                'label' => $link['link_text']
            ];
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges
        ];
    }

    /**
     * Search wiki pages
     */
    public function search(string $userId, string $query): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, title, slug, excerpt, updated_at
             FROM wiki_pages
             WHERE user_id = ? AND MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE)
             LIMIT 20',
            [$userId, $query]
        );
    }

    /**
     * Get recently updated pages
     */
    public function getRecentPages(string $userId, int $limit = 10): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, title, slug, excerpt, updated_at
             FROM wiki_pages
             WHERE user_id = ?
             ORDER BY updated_at DESC
             LIMIT ?',
            [$userId, $limit],
            ['string', 'integer']
        );
    }

    // Private helper methods

    private function generateSlug(string $userId, string $title, ?string $customSlug = null, ?string $excludeId = null): string
    {
        $baseSlug = $customSlug ?? $this->slugify($title);
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $qb = $this->db->createQueryBuilder()
                ->select('COUNT(*)')
                ->from('wiki_pages')
                ->where('user_id = :user_id')
                ->andWhere('slug = :slug')
                ->setParameter('user_id', $userId)
                ->setParameter('slug', $slug);

            if ($excludeId) {
                $qb->andWhere('id != :exclude_id')
                   ->setParameter('exclude_id', $excludeId);
            }

            $count = (int) $qb->executeQuery()->fetchOne();

            if ($count === 0) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function generateCategorySlug(string $userId, string $name, ?string $customSlug = null, ?string $excludeId = null): string
    {
        $baseSlug = $customSlug ?? $this->slugify($name);
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $qb = $this->db->createQueryBuilder()
                ->select('COUNT(*)')
                ->from('wiki_categories')
                ->where('user_id = :user_id')
                ->andWhere('slug = :slug')
                ->setParameter('user_id', $userId)
                ->setParameter('slug', $slug);

            if ($excludeId) {
                $qb->andWhere('id != :exclude_id')
                   ->setParameter('exclude_id', $excludeId);
            }

            $count = (int) $qb->executeQuery()->fetchOne();

            if ($count === 0) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function slugify(string $text): string
    {
        // Convert to lowercase
        $slug = mb_strtolower($text);

        // Replace German umlauts
        $slug = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $slug);

        // Remove accents
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);

        // Replace non-alphanumeric characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');

        return $slug ?: 'page';
    }

    private function generateExcerpt(string $content, int $length = 200): string
    {
        // Strip markdown/html
        $text = strip_tags($content);
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text); // Remove markdown links
        $text = preg_replace('/[#*_~`]/', '', $text); // Remove markdown formatting
        $text = preg_replace('/\s+/', ' ', $text); // Normalize whitespace
        $text = trim($text);

        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . '...';
    }

    private function getPageTags(string $pageId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT t.* FROM tags t
             JOIN wiki_page_tags pt ON t.id = pt.tag_id
             WHERE pt.page_id = ?',
            [$pageId]
        );
    }

    private function syncPageTags(string $pageId, array $tagIds): void
    {
        // Remove existing tags
        $this->db->delete('wiki_page_tags', ['page_id' => $pageId]);

        // Add new tags
        foreach ($tagIds as $tagId) {
            $this->db->insert('wiki_page_tags', [
                'page_id' => $pageId,
                'tag_id' => $tagId
            ]);
        }
    }

    private function getBacklinks(string $pageId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT p.id, p.title, p.slug, l.link_text
             FROM wiki_links l
             JOIN wiki_pages p ON l.source_page_id = p.id
             WHERE l.target_page_id = ?',
            [$pageId]
        );
    }

    private function getChildren(string $userId, string $pageId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, title, slug, icon FROM wiki_pages
             WHERE user_id = ? AND parent_id = ?
             ORDER BY title ASC',
            [$userId, $pageId]
        );
    }

    private function extractAndStoreLinks(string $userId, string $pageId, string $content): void
    {
        // Remove existing links from this page
        $this->db->delete('wiki_links', ['source_page_id' => $pageId]);

        // Find wiki-style links: [[Page Title]] or [[slug|Display Text]]
        preg_match_all('/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $linkTarget = trim($match[1]);
            $linkText = isset($match[2]) ? trim($match[2]) : $linkTarget;

            // Try to find target page by title or slug
            $targetPage = $this->db->fetchAssociative(
                'SELECT id FROM wiki_pages
                 WHERE user_id = ? AND (slug = ? OR title = ?)
                 LIMIT 1',
                [$userId, $this->slugify($linkTarget), $linkTarget]
            );

            if ($targetPage) {
                // Check if link already exists
                $existing = $this->db->fetchOne(
                    'SELECT COUNT(*) FROM wiki_links WHERE source_page_id = ? AND target_page_id = ?',
                    [$pageId, $targetPage['id']]
                );

                if (!$existing) {
                    $this->db->insert('wiki_links', [
                        'id' => Uuid::uuid4()->toString(),
                        'source_page_id' => $pageId,
                        'target_page_id' => $targetPage['id'],
                        'link_text' => $linkText
                    ]);
                }
            }
        }
    }

    private function createHistoryEntry(string $pageId, string $title, string $content, string $userId, ?string $changeNote): void
    {
        // Get current version number
        $currentVersion = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(version_number), 0) FROM wiki_page_history WHERE page_id = ?',
            [$pageId]
        );

        $this->db->insert('wiki_page_history', [
            'id' => Uuid::uuid4()->toString(),
            'page_id' => $pageId,
            'title' => $title,
            'content' => $content,
            'changed_by' => $userId,
            'change_note' => $changeNote,
            'version_number' => $currentVersion + 1
        ]);
    }
}
