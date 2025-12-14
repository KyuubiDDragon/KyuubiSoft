<?php

declare(strict_types=1);

namespace App\Modules\PublicGallery\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Security\PasswordHasher;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class PublicGalleryController
{
    public function __construct(
        private readonly Connection $db,
        private readonly PasswordHasher $passwordHasher
    ) {}

    private function getRouteArg(ServerRequestInterface $request, string $name): ?string
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        return $route ? $route->getArgument($name) : null;
    }

    /**
     * List all galleries
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $sql = 'SELECT g.*, c.name as category_name, c.color as category_color,
                       p.name as project_name,
                       (SELECT COUNT(*) FROM public_gallery_items WHERE gallery_id = g.id) as item_count
                FROM public_galleries g
                LEFT JOIN public_gallery_categories c ON g.category_id = c.id
                LEFT JOIN projects p ON g.project_id = p.id
                WHERE g.user_id = ?';
        $sqlParams = [$userId];

        if (!empty($params['project_id'])) {
            $sql .= ' AND g.project_id = ?';
            $sqlParams[] = $params['project_id'];
        }

        if (!empty($params['category_id'])) {
            $sql .= ' AND g.category_id = ?';
            $sqlParams[] = $params['category_id'];
        }

        if (isset($params['is_public'])) {
            $sql .= ' AND g.is_public = ?';
            $sqlParams[] = $params['is_public'];
        }

        $sql .= ' ORDER BY g.updated_at DESC';

        $galleries = $this->db->fetchAllAssociative($sql, $sqlParams);

        // Cast booleans
        foreach ($galleries as &$gallery) {
            $gallery['is_public'] = (bool) $gallery['is_public'];
            $gallery['is_password_protected'] = (bool) $gallery['is_password_protected'];
            $gallery['is_active'] = (bool) $gallery['is_active'];
            $gallery['require_email'] = (bool) $gallery['require_email'];
            $gallery['show_header'] = (bool) $gallery['show_header'];
            $gallery['show_description'] = (bool) $gallery['show_description'];
            $gallery['show_item_titles'] = (bool) $gallery['show_item_titles'];
            $gallery['show_item_descriptions'] = (bool) $gallery['show_item_descriptions'];
            $gallery['show_download_button'] = (bool) $gallery['show_download_button'];
            $gallery['track_views'] = (bool) $gallery['track_views'];
            $gallery['track_downloads'] = (bool) $gallery['track_downloads'];
            $gallery['allow_indexing'] = (bool) $gallery['allow_indexing'];
            $gallery['allowed_emails'] = json_decode($gallery['allowed_emails'] ?? '[]', true);
            unset($gallery['password_hash']); // Don't expose
        }

        // Get categories
        $categories = $this->db->fetchAllAssociative(
            'SELECT * FROM public_gallery_categories WHERE user_id = ? ORDER BY sort_order, name',
            [$userId]
        );

        return JsonResponse::success([
            'items' => $galleries,
            'categories' => $categories,
        ]);
    }

    /**
     * Get a single gallery with items
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $gallery = $this->db->fetchAssociative(
            'SELECT * FROM public_galleries WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$gallery) {
            return JsonResponse::notFound('Gallery not found');
        }

        // Get items
        $items = $this->db->fetchAllAssociative(
            'SELECT * FROM public_gallery_items WHERE gallery_id = ? ORDER BY display_order ASC',
            [$id]
        );

        foreach ($items as &$item) {
            $item['is_featured'] = (bool) $item['is_featured'];
            $item['is_visible'] = (bool) $item['is_visible'];
            $item['allow_download'] = (bool) $item['allow_download'];
            $item['open_in_new_tab'] = (bool) $item['open_in_new_tab'];

            // Load linked item data if applicable
            if ($item['item_id']) {
                $item['linked_item'] = $this->getLinkedItem($item['item_type'], $item['item_id'], $userId);
            }
        }

        // Get view stats
        $stats = $this->db->fetchAssociative(
            "SELECT
                COUNT(*) as total_views,
                COUNT(DISTINCT visitor_ip) as unique_visitors,
                SUM(CASE WHEN access_type = 'download' OR access_type = 'item_download' THEN 1 ELSE 0 END) as downloads
             FROM public_gallery_views
             WHERE gallery_id = ?",
            [$id]
        );

        // Cast booleans
        $gallery['is_public'] = (bool) $gallery['is_public'];
        $gallery['is_password_protected'] = (bool) $gallery['is_password_protected'];
        $gallery['is_active'] = (bool) $gallery['is_active'];
        $gallery['allowed_emails'] = json_decode($gallery['allowed_emails'] ?? '[]', true);
        unset($gallery['password_hash']);

        return JsonResponse::success([
            'gallery' => $gallery,
            'items' => $items,
            'stats' => $stats,
        ]);
    }

    /**
     * Create a new gallery
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name'])) {
            return JsonResponse::error('Name is required', 400);
        }

        // Generate unique slug
        $slug = $this->generateSlug($data['name'], $data['slug'] ?? null);

        $id = Uuid::uuid4()->toString();

        $insertData = [
            'id' => $id,
            'user_id' => $userId,
            'project_id' => $data['project_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'slug' => $slug,
            'layout' => $data['layout'] ?? 'grid',
            'theme' => $data['theme'] ?? 'auto',
            'custom_css' => $data['custom_css'] ?? null,
            'show_header' => $data['show_header'] ?? 1,
            'show_description' => $data['show_description'] ?? 1,
            'show_item_titles' => $data['show_item_titles'] ?? 1,
            'show_item_descriptions' => $data['show_item_descriptions'] ?? 1,
            'show_download_button' => $data['show_download_button'] ?? 0,
            'items_per_row' => $data['items_per_row'] ?? 3,
            'thumbnail_size' => $data['thumbnail_size'] ?? 'medium',
            'cover_image_url' => $data['cover_image_url'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'accent_color' => $data['accent_color'] ?? '#6366f1',
            'is_public' => $data['is_public'] ?? 1,
            'is_password_protected' => !empty($data['password']) ? 1 : 0,
            'password_hash' => !empty($data['password']) ? $this->passwordHasher->hash($data['password']) : null,
            'require_email' => $data['require_email'] ?? 0,
            'allowed_emails' => !empty($data['allowed_emails']) ? json_encode($data['allowed_emails']) : null,
            'expires_at' => $data['expires_at'] ?? null,
            'max_views' => $data['max_views'] ?? null,
            'track_views' => $data['track_views'] ?? 1,
            'track_downloads' => $data['track_downloads'] ?? 1,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_image' => $data['meta_image'] ?? null,
            'allow_indexing' => $data['allow_indexing'] ?? 0,
            'is_active' => 1,
        ];

        $this->db->insert('public_galleries', $insertData);

        $gallery = $this->db->fetchAssociative('SELECT * FROM public_galleries WHERE id = ?', [$id]);
        unset($gallery['password_hash']);

        return JsonResponse::created($gallery, 'Gallery created');
    }

    /**
     * Update a gallery
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody() ?? [];

        $exists = $this->db->fetchOne(
            'SELECT 1 FROM public_galleries WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$exists) {
            return JsonResponse::notFound('Gallery not found');
        }

        $updateFields = [
            'name', 'description', 'layout', 'theme', 'custom_css',
            'show_header', 'show_description', 'show_item_titles', 'show_item_descriptions',
            'show_download_button', 'items_per_row', 'thumbnail_size',
            'cover_image_url', 'logo_url', 'accent_color',
            'is_public', 'require_email', 'expires_at', 'max_views',
            'track_views', 'track_downloads',
            'meta_title', 'meta_description', 'meta_image', 'allow_indexing',
            'is_active', 'project_id', 'category_id'
        ];

        $updates = [];
        $params = [];

        foreach ($updateFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        // Handle password separately
        if (isset($data['password'])) {
            if (empty($data['password'])) {
                $updates[] = 'is_password_protected = 0';
                $updates[] = 'password_hash = NULL';
            } else {
                $updates[] = 'is_password_protected = 1';
                $updates[] = 'password_hash = ?';
                $params[] = $this->passwordHasher->hash($data['password']);
            }
        }

        // Handle slug change
        if (!empty($data['slug'])) {
            $newSlug = $this->generateSlug($data['name'] ?? '', $data['slug'], $id);
            $updates[] = 'slug = ?';
            $params[] = $newSlug;
        }

        // Handle allowed emails
        if (array_key_exists('allowed_emails', $data)) {
            $updates[] = 'allowed_emails = ?';
            $params[] = !empty($data['allowed_emails']) ? json_encode($data['allowed_emails']) : null;
        }

        if (!empty($updates)) {
            $params[] = $id;
            $this->db->executeStatement(
                'UPDATE public_galleries SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = ?',
                $params
            );
        }

        return JsonResponse::success(null, 'Gallery updated');
    }

    /**
     * Delete a gallery
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $deleted = $this->db->delete('public_galleries', [
            'id' => $id,
            'user_id' => $userId,
        ]);

        if (!$deleted) {
            return JsonResponse::notFound('Gallery not found');
        }

        return JsonResponse::success(null, 'Gallery deleted');
    }

    // ==================== Gallery Items ====================

    /**
     * Add item to gallery
     */
    public function addItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $galleryId = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody() ?? [];

        // Verify gallery ownership
        $gallery = $this->db->fetchOne(
            'SELECT 1 FROM public_galleries WHERE id = ? AND user_id = ?',
            [$galleryId, $userId]
        );

        if (!$gallery) {
            return JsonResponse::notFound('Gallery not found');
        }

        if (empty($data['item_type'])) {
            return JsonResponse::error('Item type is required', 400);
        }

        // Get max display order
        $maxOrder = $this->db->fetchOne(
            'SELECT MAX(display_order) FROM public_gallery_items WHERE gallery_id = ?',
            [$galleryId]
        ) ?? 0;

        $id = Uuid::uuid4()->toString();

        $this->db->insert('public_gallery_items', [
            'id' => $id,
            'gallery_id' => $galleryId,
            'item_type' => $data['item_type'],
            'item_id' => $data['item_id'] ?? null,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'content' => $data['content'] ?? null,
            'url' => $data['url'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'display_order' => $data['display_order'] ?? ($maxOrder + 1),
            'is_featured' => $data['is_featured'] ?? 0,
            'is_visible' => $data['is_visible'] ?? 1,
            'custom_thumbnail' => $data['custom_thumbnail'] ?? null,
            'custom_title' => $data['custom_title'] ?? null,
            'custom_description' => $data['custom_description'] ?? null,
            'allow_download' => $data['allow_download'] ?? 1,
            'open_in_new_tab' => $data['open_in_new_tab'] ?? 0,
            'embed_width' => $data['embed_width'] ?? null,
            'embed_height' => $data['embed_height'] ?? null,
        ]);

        $item = $this->db->fetchAssociative(
            'SELECT * FROM public_gallery_items WHERE id = ?',
            [$id]
        );

        return JsonResponse::created($item, 'Item added');
    }

    /**
     * Update gallery item
     */
    public function updateItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $galleryId = $this->getRouteArg($request, 'id');
        $itemId = $this->getRouteArg($request, 'itemId');
        $data = $request->getParsedBody() ?? [];

        // Verify ownership
        $exists = $this->db->fetchOne(
            'SELECT 1 FROM public_gallery_items i
             JOIN public_galleries g ON i.gallery_id = g.id
             WHERE i.id = ? AND g.id = ? AND g.user_id = ?',
            [$itemId, $galleryId, $userId]
        );

        if (!$exists) {
            return JsonResponse::notFound('Item not found');
        }

        $updateFields = [
            'title', 'description', 'content', 'url', 'thumbnail_url',
            'display_order', 'is_featured', 'is_visible',
            'custom_thumbnail', 'custom_title', 'custom_description',
            'allow_download', 'open_in_new_tab', 'embed_width', 'embed_height'
        ];

        $updates = [];
        $params = [];

        foreach ($updateFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (!empty($updates)) {
            $params[] = $itemId;
            $this->db->executeStatement(
                'UPDATE public_gallery_items SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = ?',
                $params
            );
        }

        return JsonResponse::success(null, 'Item updated');
    }

    /**
     * Remove item from gallery
     */
    public function removeItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $galleryId = $this->getRouteArg($request, 'id');
        $itemId = $this->getRouteArg($request, 'itemId');

        // Verify ownership
        $exists = $this->db->fetchOne(
            'SELECT 1 FROM public_gallery_items i
             JOIN public_galleries g ON i.gallery_id = g.id
             WHERE i.id = ? AND g.id = ? AND g.user_id = ?',
            [$itemId, $galleryId, $userId]
        );

        if (!$exists) {
            return JsonResponse::notFound('Item not found');
        }

        $this->db->delete('public_gallery_items', ['id' => $itemId]);

        return JsonResponse::success(null, 'Item removed');
    }

    /**
     * Reorder gallery items
     */
    public function reorderItems(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $galleryId = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody() ?? [];

        // Verify ownership
        $gallery = $this->db->fetchOne(
            'SELECT 1 FROM public_galleries WHERE id = ? AND user_id = ?',
            [$galleryId, $userId]
        );

        if (!$gallery) {
            return JsonResponse::notFound('Gallery not found');
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            return JsonResponse::error('Items array is required', 400);
        }

        foreach ($data['items'] as $order => $itemId) {
            $this->db->update('public_gallery_items', [
                'display_order' => $order,
            ], [
                'id' => $itemId,
                'gallery_id' => $galleryId,
            ]);
        }

        return JsonResponse::success(null, 'Items reordered');
    }

    // ==================== Public Access ====================

    /**
     * View public gallery (no auth required)
     */
    public function viewPublic(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $slug = $this->getRouteArg($request, 'slug');

        $gallery = $this->db->fetchAssociative(
            'SELECT * FROM public_galleries WHERE slug = ? AND is_active = 1',
            [$slug]
        );

        if (!$gallery) {
            return JsonResponse::notFound('Gallery not found');
        }

        // Check if public
        if (!$gallery['is_public']) {
            return JsonResponse::forbidden('This gallery is private');
        }

        // Check expiration
        if ($gallery['expires_at'] && strtotime($gallery['expires_at']) < time()) {
            return JsonResponse::error('This gallery has expired', 410);
        }

        // Check max views
        if ($gallery['max_views'] && $gallery['current_views'] >= $gallery['max_views']) {
            return JsonResponse::error('This gallery has reached its view limit', 410);
        }

        // Check password protection
        if ($gallery['is_password_protected']) {
            $data = $request->getParsedBody() ?? [];
            $password = $data['password'] ?? $request->getQueryParams()['p'] ?? '';

            if (empty($password)) {
                return JsonResponse::success([
                    'requires_password' => true,
                    'name' => $gallery['name'],
                ]);
            }

            if (!$this->passwordHasher->verify($password, $gallery['password_hash'])) {
                return JsonResponse::error('Invalid password', 401);
            }
        }

        // Get items
        $items = $this->db->fetchAllAssociative(
            'SELECT * FROM public_gallery_items WHERE gallery_id = ? AND is_visible = 1 ORDER BY display_order ASC',
            [$gallery['id']]
        );

        foreach ($items as &$item) {
            $item['is_featured'] = (bool) $item['is_featured'];
            $item['allow_download'] = (bool) $item['allow_download'];
            $item['open_in_new_tab'] = (bool) $item['open_in_new_tab'];
        }

        // Record view
        if ($gallery['track_views']) {
            $this->recordView($gallery['id'], null, $request, 'view');

            // Increment view count
            $this->db->executeStatement(
                'UPDATE public_galleries SET current_views = current_views + 1 WHERE id = ?',
                [$gallery['id']]
            );
        }

        // Prepare public response
        $publicGallery = [
            'name' => $gallery['name'],
            'description' => $gallery['description'],
            'layout' => $gallery['layout'],
            'theme' => $gallery['theme'],
            'custom_css' => $gallery['custom_css'],
            'show_header' => (bool) $gallery['show_header'],
            'show_description' => (bool) $gallery['show_description'],
            'show_item_titles' => (bool) $gallery['show_item_titles'],
            'show_item_descriptions' => (bool) $gallery['show_item_descriptions'],
            'show_download_button' => (bool) $gallery['show_download_button'],
            'items_per_row' => $gallery['items_per_row'],
            'thumbnail_size' => $gallery['thumbnail_size'],
            'cover_image_url' => $gallery['cover_image_url'],
            'logo_url' => $gallery['logo_url'],
            'accent_color' => $gallery['accent_color'],
            'meta_title' => $gallery['meta_title'],
            'meta_description' => $gallery['meta_description'],
            'meta_image' => $gallery['meta_image'],
        ];

        return JsonResponse::success([
            'gallery' => $publicGallery,
            'items' => $items,
        ]);
    }

    /**
     * Get gallery statistics
     */
    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $gallery = $this->db->fetchOne(
            'SELECT 1 FROM public_galleries WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$gallery) {
            return JsonResponse::notFound('Gallery not found');
        }

        $params = $request->getQueryParams();
        $days = min(90, (int) ($params['days'] ?? 30));
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        // Views over time
        $viewsOverTime = $this->db->fetchAllAssociative(
            "SELECT DATE(viewed_at) as date, COUNT(*) as views
             FROM public_gallery_views
             WHERE gallery_id = ? AND viewed_at >= ?
             GROUP BY DATE(viewed_at)
             ORDER BY date ASC",
            [$id, $startDate]
        );

        // Total stats
        $totals = $this->db->fetchAssociative(
            "SELECT
                COUNT(*) as total_views,
                COUNT(DISTINCT visitor_ip) as unique_visitors,
                SUM(CASE WHEN access_type LIKE '%download%' THEN 1 ELSE 0 END) as downloads
             FROM public_gallery_views
             WHERE gallery_id = ?",
            [$id]
        );

        // Top items
        $topItems = $this->db->fetchAllAssociative(
            "SELECT i.id, i.title, i.custom_title,
                    COUNT(v.id) as view_count,
                    SUM(CASE WHEN v.access_type = 'item_download' THEN 1 ELSE 0 END) as download_count
             FROM public_gallery_items i
             LEFT JOIN public_gallery_views v ON i.id = v.item_id
             WHERE i.gallery_id = ?
             GROUP BY i.id
             ORDER BY view_count DESC
             LIMIT 10",
            [$id]
        );

        // Referrers
        $referrers = $this->db->fetchAllAssociative(
            "SELECT referer, COUNT(*) as count
             FROM public_gallery_views
             WHERE gallery_id = ? AND referer IS NOT NULL AND referer != ''
             GROUP BY referer
             ORDER BY count DESC
             LIMIT 10",
            [$id]
        );

        return JsonResponse::success([
            'views_over_time' => $viewsOverTime,
            'totals' => $totals,
            'top_items' => $topItems,
            'referrers' => $referrers,
            'period' => ['start' => $startDate, 'end' => date('Y-m-d'), 'days' => $days],
        ]);
    }

    // ==================== Categories ====================

    /**
     * Create category
     */
    public function createCategory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name'])) {
            return JsonResponse::error('Name is required', 400);
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('public_gallery_categories', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'color' => $data['color'] ?? '#6366f1',
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        $category = $this->db->fetchAssociative(
            'SELECT * FROM public_gallery_categories WHERE id = ?',
            [$id]
        );

        return JsonResponse::created($category, 'Category created');
    }

    /**
     * Update category
     */
    public function updateCategory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody() ?? [];

        $updated = $this->db->update('public_gallery_categories', [
            'name' => $data['name'] ?? null,
            'color' => $data['color'] ?? null,
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sort_order'] ?? null,
        ], [
            'id' => $id,
            'user_id' => $userId,
        ]);

        if (!$updated) {
            return JsonResponse::notFound('Category not found');
        }

        return JsonResponse::success(null, 'Category updated');
    }

    /**
     * Delete category
     */
    public function deleteCategory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $deleted = $this->db->delete('public_gallery_categories', [
            'id' => $id,
            'user_id' => $userId,
        ]);

        if (!$deleted) {
            return JsonResponse::notFound('Category not found');
        }

        return JsonResponse::success(null, 'Category deleted');
    }

    // ==================== Helper Methods ====================

    private function generateSlug(string $name, ?string $customSlug = null, ?string $excludeId = null): string
    {
        $baseSlug = $customSlug ?: $this->slugify($name);

        // Ensure unique
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $sql = 'SELECT 1 FROM public_galleries WHERE slug = ?';
            $params = [$slug];

            if ($excludeId) {
                $sql .= ' AND id != ?';
                $params[] = $excludeId;
            }

            $exists = $this->db->fetchOne($sql, $params);

            if (!$exists) {
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
        $text = strtolower($text);

        // Replace spaces with hyphens
        $text = preg_replace('/\s+/', '-', $text);

        // Remove special characters
        $text = preg_replace('/[^a-z0-9\-]/', '', $text);

        // Remove multiple hyphens
        $text = preg_replace('/-+/', '-', $text);

        // Trim hyphens from ends
        $text = trim($text, '-');

        // Limit length
        if (strlen($text) > 80) {
            $text = substr($text, 0, 80);
        }

        // Add random suffix if empty
        if (empty($text)) {
            $text = 'gallery-' . substr(md5(uniqid()), 0, 8);
        }

        return $text;
    }

    private function getLinkedItem(string $type, string $id, string $userId): ?array
    {
        return match ($type) {
            'document' => $this->db->fetchAssociative(
                'SELECT id, title, content FROM documents WHERE id = ? AND user_id = ?',
                [$id, $userId]
            ),
            'file' => $this->db->fetchAssociative(
                'SELECT id, original_name as title, path, mime_type FROM storage_files WHERE id = ? AND user_id = ?',
                [$id, $userId]
            ),
            default => null,
        };
    }

    private function recordView(string $galleryId, ?string $itemId, ServerRequestInterface $request, string $type): void
    {
        $this->db->insert('public_gallery_views', [
            'id' => Uuid::uuid4()->toString(),
            'gallery_id' => $galleryId,
            'item_id' => $itemId,
            'visitor_ip' => $this->getClientIp($request),
            'user_agent' => substr($request->getHeaderLine('User-Agent'), 0, 500),
            'referer' => substr($request->getHeaderLine('Referer'), 0, 500),
            'access_type' => $type,
        ]);
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $headers = ['X-Forwarded-For', 'X-Real-IP', 'CF-Connecting-IP'];

        foreach ($headers as $header) {
            $value = $request->getHeaderLine($header);
            if (!empty($value)) {
                $ips = explode(',', $value);
                return trim($ips[0]);
            }
        }

        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? '';
    }
}
