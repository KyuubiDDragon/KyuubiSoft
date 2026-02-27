<?php

declare(strict_types=1);

namespace App\Modules\KnowledgeBase\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

class KnowledgeBaseController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    // ─── Admin: Categories ───────────────────────────────────────────

    /**
     * List all categories for the current user (tree structure)
     */
    public function listCategories(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $categories = $this->db->fetchAllAssociative(
            'SELECT * FROM kb_categories WHERE user_id = ? ORDER BY sort_order ASC, name ASC',
            [$userId]
        );

        // Build tree structure
        $tree = $this->buildCategoryTree($categories);

        return JsonResponse::success($tree);
    }

    /**
     * Create a new category
     */
    public function createCategory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        $name = trim($body['name'] ?? '');
        if ($name === '') {
            return JsonResponse::error('Name is required', 422);
        }

        $slug = $body['slug'] ?? $this->generateSlug($name);
        $description = $body['description'] ?? null;
        $parentId = $body['parent_id'] ?? null;
        $icon = $body['icon'] ?? null;
        $sortOrder = (int) ($body['sort_order'] ?? 0);
        $isPublished = (bool) ($body['is_published'] ?? true);

        $id = $this->generateUuid();

        $this->db->insert('kb_categories', [
            'id' => $id,
            'user_id' => $userId,
            'parent_id' => $parentId ?: null,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'icon' => $icon,
            'sort_order' => $sortOrder,
            'is_published' => $isPublished ? 1 : 0,
        ]);

        $category = $this->db->fetchAssociative(
            'SELECT * FROM kb_categories WHERE id = ?',
            [$id]
        );

        return JsonResponse::created($category);
    }

    /**
     * Update a category
     */
    public function updateCategory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $body = (array) $request->getParsedBody();

        $category = $this->db->fetchAssociative(
            'SELECT * FROM kb_categories WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$category) {
            return JsonResponse::notFound('Category not found');
        }

        $updates = [];
        $params = [];

        if (isset($body['name'])) {
            $updates[] = 'name = ?';
            $params[] = trim($body['name']);
        }
        if (isset($body['slug'])) {
            $updates[] = 'slug = ?';
            $params[] = $body['slug'];
        }
        if (array_key_exists('description', $body)) {
            $updates[] = 'description = ?';
            $params[] = $body['description'];
        }
        if (array_key_exists('parent_id', $body)) {
            $updates[] = 'parent_id = ?';
            $params[] = $body['parent_id'] ?: null;
        }
        if (isset($body['icon'])) {
            $updates[] = 'icon = ?';
            $params[] = $body['icon'];
        }
        if (isset($body['sort_order'])) {
            $updates[] = 'sort_order = ?';
            $params[] = (int) $body['sort_order'];
        }
        if (isset($body['is_published'])) {
            $updates[] = 'is_published = ?';
            $params[] = (bool) $body['is_published'] ? 1 : 0;
        }

        if (!empty($updates)) {
            $params[] = $id;
            $params[] = $userId;
            $this->db->executeStatement(
                'UPDATE kb_categories SET ' . implode(', ', $updates) . ' WHERE id = ? AND user_id = ?',
                $params
            );
        }

        $updated = $this->db->fetchAssociative(
            'SELECT * FROM kb_categories WHERE id = ?',
            [$id]
        );

        return JsonResponse::success($updated);
    }

    /**
     * Delete a category
     */
    public function deleteCategory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $category = $this->db->fetchAssociative(
            'SELECT * FROM kb_categories WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$category) {
            return JsonResponse::notFound('Category not found');
        }

        // Set articles in this category to null category
        $this->db->executeStatement(
            'UPDATE kb_articles SET category_id = NULL WHERE category_id = ?',
            [$id]
        );

        // Move child categories to parent
        $this->db->executeStatement(
            'UPDATE kb_categories SET parent_id = ? WHERE parent_id = ? AND user_id = ?',
            [$category['parent_id'], $id, $userId]
        );

        $this->db->delete('kb_categories', ['id' => $id, 'user_id' => $userId]);

        return JsonResponse::success(null, 'Category deleted');
    }

    // ─── Admin: Articles ─────────────────────────────────────────────

    /**
     * Paginated articles list with filters
     */
    public function listArticles(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $where = ['a.user_id = ?'];
        $params = [$userId];
        $types = [\PDO::PARAM_STR];

        // Filter by category
        if (!empty($queryParams['category_id'])) {
            $where[] = 'a.category_id = ?';
            $params[] = $queryParams['category_id'];
            $types[] = \PDO::PARAM_STR;
        }

        // Filter by published status
        if (isset($queryParams['is_published']) && $queryParams['is_published'] !== '') {
            $where[] = 'a.is_published = ?';
            $params[] = (int) $queryParams['is_published'];
            $types[] = \PDO::PARAM_INT;
        }

        // Search
        if (!empty($queryParams['search'])) {
            $where[] = '(a.title LIKE ? OR a.content LIKE ?)';
            $searchTerm = '%' . $queryParams['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Count
        $total = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM kb_articles a ' . $whereClause,
            $params,
            $types
        );

        // Fetch
        $sql = 'SELECT a.*, c.name as category_name
                FROM kb_articles a
                LEFT JOIN kb_categories c ON a.category_id = c.id
                ' . $whereClause . '
                ORDER BY a.updated_at DESC
                LIMIT ? OFFSET ?';

        $params[] = $perPage;
        $params[] = $offset;
        $types[] = \PDO::PARAM_INT;
        $types[] = \PDO::PARAM_INT;

        $articles = $this->db->fetchAllAssociative($sql, $params, $types);

        // Decode tags JSON
        $articles = array_map(function (array $article): array {
            if (isset($article['tags']) && is_string($article['tags'])) {
                $article['tags'] = json_decode($article['tags'], true) ?? [];
            }
            return $article;
        }, $articles);

        return JsonResponse::paginated($articles, $total, $page, $perPage);
    }

    /**
     * Create a new article
     */
    public function createArticle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        $title = trim($body['title'] ?? '');
        if ($title === '') {
            return JsonResponse::error('Title is required', 422);
        }

        $slug = $body['slug'] ?? $this->generateSlug($title);
        $categoryId = $body['category_id'] ?? null;
        $content = $body['content'] ?? null;
        $excerpt = $body['excerpt'] ?? null;
        $tags = isset($body['tags']) ? json_encode($body['tags']) : null;
        $isPublished = (bool) ($body['is_published'] ?? false);

        $id = $this->generateUuid();

        $this->db->insert('kb_articles', [
            'id' => $id,
            'user_id' => $userId,
            'category_id' => $categoryId ?: null,
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'excerpt' => $excerpt,
            'tags' => $tags,
            'is_published' => $isPublished ? 1 : 0,
        ]);

        $article = $this->db->fetchAssociative(
            'SELECT a.*, c.name as category_name
             FROM kb_articles a
             LEFT JOIN kb_categories c ON a.category_id = c.id
             WHERE a.id = ?',
            [$id]
        );

        if (isset($article['tags']) && is_string($article['tags'])) {
            $article['tags'] = json_decode($article['tags'], true) ?? [];
        }

        return JsonResponse::created($article);
    }

    /**
     * Show a single article by ID (admin)
     */
    public function showArticle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $article = $this->db->fetchAssociative(
            'SELECT a.*, c.name as category_name
             FROM kb_articles a
             LEFT JOIN kb_categories c ON a.category_id = c.id
             WHERE a.id = ? AND a.user_id = ?',
            [$id, $userId]
        );

        if (!$article) {
            return JsonResponse::notFound('Article not found');
        }

        if (isset($article['tags']) && is_string($article['tags'])) {
            $article['tags'] = json_decode($article['tags'], true) ?? [];
        }

        return JsonResponse::success($article);
    }

    /**
     * Update an article
     */
    public function updateArticle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $body = (array) $request->getParsedBody();

        $article = $this->db->fetchAssociative(
            'SELECT * FROM kb_articles WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$article) {
            return JsonResponse::notFound('Article not found');
        }

        $updates = [];
        $params = [];

        if (isset($body['title'])) {
            $updates[] = 'title = ?';
            $params[] = trim($body['title']);
        }
        if (isset($body['slug'])) {
            $updates[] = 'slug = ?';
            $params[] = $body['slug'];
        }
        if (array_key_exists('category_id', $body)) {
            $updates[] = 'category_id = ?';
            $params[] = $body['category_id'] ?: null;
        }
        if (array_key_exists('content', $body)) {
            $updates[] = 'content = ?';
            $params[] = $body['content'];
        }
        if (array_key_exists('excerpt', $body)) {
            $updates[] = 'excerpt = ?';
            $params[] = $body['excerpt'];
        }
        if (isset($body['tags'])) {
            $updates[] = 'tags = ?';
            $params[] = json_encode($body['tags']);
        }
        if (isset($body['is_published'])) {
            $updates[] = 'is_published = ?';
            $params[] = (bool) $body['is_published'] ? 1 : 0;
        }

        if (!empty($updates)) {
            $params[] = $id;
            $params[] = $userId;
            $this->db->executeStatement(
                'UPDATE kb_articles SET ' . implode(', ', $updates) . ' WHERE id = ? AND user_id = ?',
                $params
            );
        }

        $updated = $this->db->fetchAssociative(
            'SELECT a.*, c.name as category_name
             FROM kb_articles a
             LEFT JOIN kb_categories c ON a.category_id = c.id
             WHERE a.id = ?',
            [$id]
        );

        if (isset($updated['tags']) && is_string($updated['tags'])) {
            $updated['tags'] = json_decode($updated['tags'], true) ?? [];
        }

        return JsonResponse::success($updated);
    }

    /**
     * Delete an article
     */
    public function deleteArticle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $article = $this->db->fetchAssociative(
            'SELECT * FROM kb_articles WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$article) {
            return JsonResponse::notFound('Article not found');
        }

        $this->db->delete('kb_articles', ['id' => $id, 'user_id' => $userId]);

        return JsonResponse::success(null, 'Article deleted');
    }

    // ─── Public Endpoints ────────────────────────────────────────────

    /**
     * PUBLIC: List published categories with article counts
     */
    public function publicCategories(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $categories = $this->db->fetchAllAssociative(
            'SELECT c.id, c.name, c.slug, c.description, c.icon, c.parent_id, c.sort_order,
                    COUNT(a.id) as article_count
             FROM kb_categories c
             LEFT JOIN kb_articles a ON a.category_id = c.id AND a.is_published = 1
             WHERE c.is_published = 1
             GROUP BY c.id, c.name, c.slug, c.description, c.icon, c.parent_id, c.sort_order
             ORDER BY c.sort_order ASC, c.name ASC'
        );

        return JsonResponse::success($categories);
    }

    /**
     * PUBLIC: Full-text search across published articles
     */
    public function publicSearch(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $query = trim($queryParams['q'] ?? '');

        if ($query === '') {
            return JsonResponse::success([]);
        }

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($queryParams['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        // Use LIKE search for broader matching; FULLTEXT can be restrictive with short terms
        $searchTerm = '%' . $query . '%';

        $total = (int) $this->db->fetchOne(
            'SELECT COUNT(*)
             FROM kb_articles a
             WHERE a.is_published = 1
               AND (a.title LIKE ? OR a.content LIKE ?)',
            [$searchTerm, $searchTerm]
        );

        $articles = $this->db->fetchAllAssociative(
            'SELECT a.id, a.title, a.slug, a.excerpt, a.category_id, a.tags,
                    a.view_count, a.helpful_count, a.not_helpful_count, a.created_at,
                    c.name as category_name
             FROM kb_articles a
             LEFT JOIN kb_categories c ON a.category_id = c.id
             WHERE a.is_published = 1
               AND (a.title LIKE ? OR a.content LIKE ?)
             ORDER BY a.view_count DESC, a.updated_at DESC
             LIMIT ? OFFSET ?',
            [$searchTerm, $searchTerm, $perPage, $offset],
            [\PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]
        );

        // Decode tags
        $articles = array_map(function (array $article): array {
            if (isset($article['tags']) && is_string($article['tags'])) {
                $article['tags'] = json_decode($article['tags'], true) ?? [];
            }
            return $article;
        }, $articles);

        return JsonResponse::paginated($articles, $total, $page, $perPage);
    }

    /**
     * PUBLIC: Single published article by slug (increments view_count)
     */
    public function publicArticle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $slug = RouteContext::fromRequest($request)->getRoute()->getArgument('slug');

        $article = $this->db->fetchAssociative(
            'SELECT a.id, a.title, a.slug, a.content, a.excerpt, a.category_id, a.tags,
                    a.view_count, a.helpful_count, a.not_helpful_count,
                    a.created_at, a.updated_at,
                    c.name as category_name
             FROM kb_articles a
             LEFT JOIN kb_categories c ON a.category_id = c.id
             WHERE a.slug = ? AND a.is_published = 1',
            [$slug]
        );

        if (!$article) {
            return JsonResponse::notFound('Article not found');
        }

        // Increment view count
        $this->db->executeStatement(
            'UPDATE kb_articles SET view_count = view_count + 1 WHERE id = ?',
            [$article['id']]
        );

        $article['view_count'] = (int) $article['view_count'] + 1;

        if (isset($article['tags']) && is_string($article['tags'])) {
            $article['tags'] = json_decode($article['tags'], true) ?? [];
        }

        return JsonResponse::success($article);
    }

    /**
     * PUBLIC: Rate an article (helpful / not helpful)
     */
    public function rateArticle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $slug = RouteContext::fromRequest($request)->getRoute()->getArgument('slug');
        $body = (array) $request->getParsedBody();

        $article = $this->db->fetchAssociative(
            'SELECT id FROM kb_articles WHERE slug = ? AND is_published = 1',
            [$slug]
        );

        if (!$article) {
            return JsonResponse::notFound('Article not found');
        }

        $isHelpful = (bool) ($body['is_helpful'] ?? true);
        $feedback = $body['feedback'] ?? null;

        // Rate-limit by IP hash
        $ipHash = hash('sha256', $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');

        $existingRating = $this->db->fetchOne(
            'SELECT COUNT(*) FROM kb_article_ratings
             WHERE article_id = ? AND ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)',
            [$article['id'], $ipHash]
        );

        if ((int) $existingRating > 0) {
            return JsonResponse::error('You have already rated this article recently', 429);
        }

        $ratingId = $this->generateUuid();

        $this->db->insert('kb_article_ratings', [
            'id' => $ratingId,
            'article_id' => $article['id'],
            'is_helpful' => $isHelpful ? 1 : 0,
            'feedback' => $feedback,
            'ip_hash' => $ipHash,
        ]);

        // Update article counts
        $column = $isHelpful ? 'helpful_count' : 'not_helpful_count';
        $this->db->executeStatement(
            'UPDATE kb_articles SET ' . $column . ' = ' . $column . ' + 1 WHERE id = ?',
            [$article['id']]
        );

        return JsonResponse::success(['rated' => true, 'is_helpful' => $isHelpful]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Build a tree structure from flat category list
     */
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

    /**
     * Generate a URL-friendly slug from a string
     */
    private function generateSlug(string $text): string
    {
        $slug = mb_strtolower($text, 'UTF-8');
        // Replace umlauts
        $slug = str_replace(
            ['ä', 'ö', 'ü', 'ß'],
            ['ae', 'oe', 'ue', 'ss'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug ?: 'untitled';
    }

    /**
     * Generate a UUID v4
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
