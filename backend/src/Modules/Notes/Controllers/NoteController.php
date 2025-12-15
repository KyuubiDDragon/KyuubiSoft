<?php

declare(strict_types=1);

namespace App\Modules\Notes\Controllers;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Modules\Notes\Services\NoteService;
use App\Modules\Notes\Services\WikiLinkService;
use App\Modules\Webhooks\Services\WebhookService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class NoteController
{
    public function __construct(
        private readonly Connection $db,
        private readonly NoteService $noteService,
        private readonly WikiLinkService $wikiLinkService,
        private readonly WebhookService $webhookService
    ) {}

    // =========================================================================
    // CRUD OPERATIONS
    // =========================================================================

    /**
     * List all notes with filters
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        // Pagination
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        // Build query conditions
        $conditions = ['n.user_id = ?', 'n.is_deleted = FALSE'];
        $bindings = [$userId];
        $types = [\PDO::PARAM_STR];

        // Filter by parent
        if (isset($params['parent_id'])) {
            if ($params['parent_id'] === 'root' || $params['parent_id'] === 'null') {
                $conditions[] = 'n.parent_id IS NULL';
            } else {
                $conditions[] = 'n.parent_id = ?';
                $bindings[] = $params['parent_id'];
                $types[] = \PDO::PARAM_STR;
            }
        }

        // Filter archived
        if (isset($params['archived'])) {
            $conditions[] = 'n.is_archived = ?';
            $bindings[] = $params['archived'] === '1' || $params['archived'] === 'true';
            $types[] = \PDO::PARAM_BOOL;
        } else {
            $conditions[] = 'n.is_archived = FALSE';
        }

        // Filter templates
        if (isset($params['template'])) {
            $conditions[] = 'n.is_template = ?';
            $bindings[] = $params['template'] === '1' || $params['template'] === 'true';
            $types[] = \PDO::PARAM_BOOL;
        } else {
            $conditions[] = 'n.is_template = FALSE';
        }

        // Search
        if (!empty($params['search'])) {
            $conditions[] = '(n.title LIKE ? OR n.content LIKE ?)';
            $searchTerm = '%' . $params['search'] . '%';
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
        }

        $whereClause = implode(' AND ', $conditions);

        // Fetch notes
        $sql = "SELECT n.*,
                    (SELECT COUNT(*) FROM notes WHERE parent_id = n.id AND is_deleted = FALSE) as children_count,
                    (SELECT COUNT(*) FROM note_versions WHERE note_id = n.id) as version_count,
                    (SELECT 1 FROM note_favorites WHERE note_id = n.id AND user_id = ?) as is_favorite
                FROM notes n
                WHERE {$whereClause}
                ORDER BY n.is_pinned DESC, n.sort_order ASC, n.updated_at DESC
                LIMIT ? OFFSET ?";

        $bindings = array_merge([$userId], $bindings, [$perPage, $offset]);
        $types = array_merge([\PDO::PARAM_STR], $types, [\PDO::PARAM_INT, \PDO::PARAM_INT]);

        $notes = $this->db->fetchAllAssociative($sql, $bindings, $types);

        // Convert boolean fields
        foreach ($notes as &$note) {
            $note['is_pinned'] = (bool) $note['is_pinned'];
            $note['is_archived'] = (bool) $note['is_archived'];
            $note['is_template'] = (bool) $note['is_template'];
            $note['is_deleted'] = (bool) $note['is_deleted'];
            $note['is_favorite'] = (bool) $note['is_favorite'];
            $note['children_count'] = (int) $note['children_count'];
            $note['version_count'] = (int) $note['version_count'];
        }

        // Count total
        $countSql = "SELECT COUNT(*) FROM notes n WHERE {$whereClause}";
        $countBindings = array_slice($bindings, 1, -2);
        $countTypes = array_slice($types, 1, -2);
        $total = (int) $this->db->fetchOne($countSql, $countBindings, $countTypes);

        return JsonResponse::paginated($notes, $total, $page, $perPage);
    }

    /**
     * Get note tree structure for sidebar
     */
    public function tree(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $notes = $this->db->fetchAllAssociative(
            "SELECT id, parent_id, title, slug, icon, is_pinned, is_archived, is_template, sort_order,
                    (SELECT COUNT(*) FROM notes WHERE parent_id = n.id AND is_deleted = FALSE) as children_count
             FROM notes n
             WHERE user_id = ? AND is_deleted = FALSE AND is_archived = FALSE AND is_template = FALSE
             ORDER BY sort_order ASC, title ASC",
            [$userId]
        );

        // Build tree structure
        $tree = $this->noteService->buildTree($notes);

        return JsonResponse::success($tree);
    }

    /**
     * Create a new note
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        // Validation
        $title = trim($data['title'] ?? '');
        if (empty($title)) {
            $title = 'Untitled';
        }

        // Generate slug
        $slug = $this->noteService->generateUniqueSlug($userId, $title);

        // Validate parent if provided
        if (!empty($data['parent_id'])) {
            $parent = $this->db->fetchAssociative(
                'SELECT id FROM notes WHERE id = ? AND user_id = ? AND is_deleted = FALSE',
                [$data['parent_id'], $userId]
            );
            if (!$parent) {
                throw new ValidationException('Parent note not found');
            }
        }

        // Generate ID
        $noteId = Uuid::uuid4()->toString();
        $content = $data['content'] ?? '';
        $wordCount = $this->noteService->countWords($content);

        // Insert note
        $this->db->insert('notes', [
            'id' => $noteId,
            'user_id' => $userId,
            'parent_id' => $data['parent_id'] ?? null,
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'icon' => $data['icon'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'is_pinned' => !empty($data['is_pinned']),
            'is_template' => !empty($data['is_template']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'word_count' => $wordCount,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create initial version
        $this->createVersion($noteId, $title, $content, $userId, 'Initial version');

        // Parse and store wiki links
        $this->wikiLinkService->updateLinks($noteId, $userId, $content);

        // Update recent
        $this->updateRecent($userId, $noteId);

        // Fetch created note
        $note = $this->db->fetchAssociative('SELECT * FROM notes WHERE id = ?', [$noteId]);

        // Trigger webhook
        $this->webhookService->trigger($userId, 'note.created', [
            'id' => $noteId,
            'title' => $note['title'],
            'message' => 'Note created: ' . $note['title'],
        ]);

        return JsonResponse::created($note, 'Note created successfully');
    }

    /**
     * Get a single note
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $note = $this->getNoteForUser($noteId, $userId);

        // Get additional data
        $note['children'] = $this->db->fetchAllAssociative(
            'SELECT id, title, slug, icon, is_pinned, sort_order FROM notes
             WHERE parent_id = ? AND is_deleted = FALSE ORDER BY sort_order ASC',
            [$noteId]
        );

        $note['backlinks'] = $this->wikiLinkService->getBacklinks($noteId, $userId);
        $note['breadcrumb'] = $this->noteService->getBreadcrumb($noteId);

        // Update recent access
        $this->updateRecent($userId, $noteId);

        // Check if favorite
        $note['is_favorite'] = (bool) $this->db->fetchOne(
            'SELECT 1 FROM note_favorites WHERE user_id = ? AND note_id = ?',
            [$userId, $noteId]
        );

        return JsonResponse::success($note);
    }

    /**
     * Update a note
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $note = $this->getNoteForUser($noteId, $userId);

        $updateData = [];
        $contentChanged = false;
        $titleChanged = false;

        // Handle title
        if (isset($data['title'])) {
            $title = trim($data['title']);
            if ($title !== $note['title']) {
                $updateData['title'] = $title;
                $updateData['slug'] = $this->noteService->generateUniqueSlug($userId, $title, $noteId);
                $titleChanged = true;
            }
        }

        // Handle content
        if (isset($data['content']) && $data['content'] !== $note['content']) {
            $updateData['content'] = $data['content'];
            $updateData['word_count'] = $this->noteService->countWords($data['content']);
            $contentChanged = true;
        }

        // Handle other fields
        $allowedFields = ['icon', 'cover_image', 'sort_order'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        // Handle boolean fields with proper type casting
        $booleanFields = ['is_pinned', 'is_archived', 'is_template'];
        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
        }

        // Handle parent change (move)
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== $note['parent_id']) {
            // Validate new parent
            if (!empty($data['parent_id'])) {
                // Check parent exists and is not the note itself or its descendant
                if ($data['parent_id'] === $noteId) {
                    throw new ValidationException('Cannot move note into itself');
                }
                $parent = $this->db->fetchAssociative(
                    'SELECT id FROM notes WHERE id = ? AND user_id = ? AND is_deleted = FALSE',
                    [$data['parent_id'], $userId]
                );
                if (!$parent) {
                    throw new ValidationException('Parent note not found');
                }
                // Check for circular reference
                if ($this->noteService->isDescendant($data['parent_id'], $noteId)) {
                    throw new ValidationException('Cannot move note into its own descendant');
                }
            }
            $updateData['parent_id'] = $data['parent_id'] ?: null;
        }

        // Create version if content changed
        if ($contentChanged || $titleChanged) {
            $this->createVersion(
                $noteId,
                $updateData['title'] ?? $note['title'],
                $updateData['content'] ?? $note['content'],
                $userId,
                $data['change_summary'] ?? null
            );
            $updateData['content_version'] = $note['content_version'] + 1;
        }

        // Update note
        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('notes', $updateData, ['id' => $noteId]);
        }

        // Update wiki links if content changed
        if ($contentChanged) {
            $this->wikiLinkService->updateLinks($noteId, $userId, $data['content']);
        }

        // Fetch updated note
        $updatedNote = $this->db->fetchAssociative('SELECT * FROM notes WHERE id = ?', [$noteId]);

        // Trigger webhook
        $this->webhookService->trigger($userId, 'note.updated', [
            'id' => $noteId,
            'title' => $updatedNote['title'],
            'message' => 'Note updated: ' . $updatedNote['title'],
        ]);

        return JsonResponse::success($updatedNote, 'Note updated successfully');
    }

    /**
     * Delete a note (soft delete)
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $note = $this->getNoteForUser($noteId, $userId);

        // Soft delete
        $this->db->update('notes', [
            'is_deleted' => true,
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $noteId]);

        // Also soft delete children
        $this->softDeleteChildren($noteId);

        // Trigger webhook
        $this->webhookService->trigger($userId, 'note.deleted', [
            'id' => $noteId,
            'title' => $note['title'],
            'message' => 'Note deleted: ' . $note['title'],
        ]);

        return JsonResponse::success(null, 'Note moved to trash');
    }

    // =========================================================================
    // HIERARCHY & NAVIGATION
    // =========================================================================

    /**
     * Get children of a note
     */
    public function children(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Verify access
        $this->getNoteForUser($noteId, $userId);

        $children = $this->db->fetchAllAssociative(
            "SELECT n.*,
                    (SELECT COUNT(*) FROM notes WHERE parent_id = n.id AND is_deleted = FALSE) as children_count
             FROM notes n
             WHERE n.parent_id = ? AND n.is_deleted = FALSE
             ORDER BY n.sort_order ASC, n.title ASC",
            [$noteId]
        );

        return JsonResponse::success($children);
    }

    /**
     * Get breadcrumb path for a note
     */
    public function breadcrumb(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getNoteForUser($noteId, $userId);
        $breadcrumb = $this->noteService->getBreadcrumb($noteId);

        return JsonResponse::success($breadcrumb);
    }

    /**
     * Move a note to a new parent
     */
    public function move(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getNoteForUser($noteId, $userId);

        $newParentId = $data['parent_id'] ?? null;

        // Validate
        if ($newParentId === $noteId) {
            throw new ValidationException('Cannot move note into itself');
        }

        if ($newParentId) {
            $parent = $this->db->fetchAssociative(
                'SELECT id FROM notes WHERE id = ? AND user_id = ? AND is_deleted = FALSE',
                [$newParentId, $userId]
            );
            if (!$parent) {
                throw new ValidationException('Parent note not found');
            }
            if ($this->noteService->isDescendant($newParentId, $noteId)) {
                throw new ValidationException('Cannot move note into its own descendant');
            }
        }

        $this->db->update('notes', [
            'parent_id' => $newParentId,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $noteId]);

        return JsonResponse::success(null, 'Note moved successfully');
    }

    /**
     * Reorder notes
     */
    public function reorder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new ValidationException('Items array is required');
        }

        foreach ($data['items'] as $index => $item) {
            if (empty($item['id'])) continue;

            // Verify ownership
            $note = $this->db->fetchOne(
                'SELECT id FROM notes WHERE id = ? AND user_id = ?',
                [$item['id'], $userId]
            );

            if ($note) {
                $this->db->update('notes', [
                    'sort_order' => $item['sort_order'] ?? $index,
                    'parent_id' => $item['parent_id'] ?? null,
                ], ['id' => $item['id']]);
            }
        }

        return JsonResponse::success(null, 'Notes reordered');
    }

    // =========================================================================
    // FAVORITES & PINNING
    // =========================================================================

    /**
     * Get recent notes
     */
    public function recent(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $limit = min(20, max(1, (int) ($request->getQueryParams()['limit'] ?? 10)));

        $notes = $this->db->fetchAllAssociative(
            "SELECT n.id, n.title, n.slug, n.icon, n.updated_at, nr.accessed_at
             FROM note_recent nr
             JOIN notes n ON nr.note_id = n.id
             WHERE nr.user_id = ? AND n.is_deleted = FALSE
             ORDER BY nr.accessed_at DESC
             LIMIT ?",
            [$userId, $limit],
            [\PDO::PARAM_STR, \PDO::PARAM_INT]
        );

        return JsonResponse::success($notes);
    }

    /**
     * Get favorite notes
     */
    public function favorites(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $notes = $this->db->fetchAllAssociative(
            "SELECT n.id, n.title, n.slug, n.icon, n.is_pinned, n.updated_at, nf.created_at as favorited_at
             FROM note_favorites nf
             JOIN notes n ON nf.note_id = n.id
             WHERE nf.user_id = ? AND n.is_deleted = FALSE
             ORDER BY nf.created_at DESC",
            [$userId]
        );

        return JsonResponse::success($notes);
    }

    /**
     * Add note to favorites
     */
    public function favorite(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getNoteForUser($noteId, $userId);

        // Check if already favorited
        $existing = $this->db->fetchOne(
            'SELECT 1 FROM note_favorites WHERE user_id = ? AND note_id = ?',
            [$userId, $noteId]
        );

        if (!$existing) {
            $this->db->insert('note_favorites', [
                'user_id' => $userId,
                'note_id' => $noteId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return JsonResponse::success(null, 'Note added to favorites');
    }

    /**
     * Remove note from favorites
     */
    public function unfavorite(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->db->delete('note_favorites', [
            'user_id' => $userId,
            'note_id' => $noteId,
        ]);

        return JsonResponse::success(null, 'Note removed from favorites');
    }

    /**
     * Pin a note
     */
    public function pin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getNoteForUser($noteId, $userId);

        $this->db->update('notes', [
            'is_pinned' => true,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $noteId]);

        return JsonResponse::success(null, 'Note pinned');
    }

    /**
     * Unpin a note
     */
    public function unpin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getNoteForUser($noteId, $userId);

        $this->db->update('notes', [
            'is_pinned' => false,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $noteId]);

        return JsonResponse::success(null, 'Note unpinned');
    }

    // =========================================================================
    // WIKI LINKS & SEARCH
    // =========================================================================

    /**
     * Get backlinks for a note
     */
    public function backlinks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getNoteForUser($noteId, $userId);
        $backlinks = $this->wikiLinkService->getBacklinks($noteId, $userId);

        return JsonResponse::success($backlinks);
    }

    /**
     * Get note by slug (for wiki link resolution)
     */
    public function bySlug(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $slug = RouteContext::fromRequest($request)->getRoute()->getArgument('slug');

        $note = $this->db->fetchAssociative(
            'SELECT * FROM notes WHERE user_id = ? AND slug = ? AND is_deleted = FALSE',
            [$userId, $slug]
        );

        if (!$note) {
            throw new NotFoundException('Note not found');
        }

        return JsonResponse::success($note);
    }

    /**
     * Search notes
     */
    public function search(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();
        $query = $params['q'] ?? '';

        if (strlen($query) < 2) {
            return JsonResponse::success([]);
        }

        $limit = min(50, max(1, (int) ($params['limit'] ?? 20)));

        // Use FULLTEXT search if query is long enough, otherwise LIKE
        if (strlen($query) >= 3) {
            $notes = $this->db->fetchAllAssociative(
                "SELECT id, title, slug, icon,
                        SUBSTRING(content, 1, 200) as preview,
                        MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                 FROM notes
                 WHERE user_id = ? AND is_deleted = FALSE AND is_template = FALSE
                   AND MATCH(title, content) AGAINST(? IN NATURAL LANGUAGE MODE)
                 ORDER BY relevance DESC
                 LIMIT ?",
                [$query, $userId, $query, $limit],
                [\PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_INT]
            );
        } else {
            $searchTerm = '%' . $query . '%';
            $notes = $this->db->fetchAllAssociative(
                "SELECT id, title, slug, icon, SUBSTRING(content, 1, 200) as preview
                 FROM notes
                 WHERE user_id = ? AND is_deleted = FALSE AND is_template = FALSE
                   AND (title LIKE ? OR content LIKE ?)
                 ORDER BY updated_at DESC
                 LIMIT ?",
                [$userId, $searchTerm, $searchTerm, $limit],
                [\PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_INT]
            );
        }

        return JsonResponse::success($notes);
    }

    /**
     * Get search suggestions for wiki links
     */
    public function suggestions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $query = $request->getQueryParams()['q'] ?? '';

        if (strlen($query) < 1) {
            // Return recent notes if no query
            $notes = $this->db->fetchAllAssociative(
                "SELECT id, title, slug, icon FROM notes
                 WHERE user_id = ? AND is_deleted = FALSE AND is_template = FALSE
                 ORDER BY updated_at DESC LIMIT 10",
                [$userId]
            );
        } else {
            $searchTerm = '%' . $query . '%';
            $notes = $this->db->fetchAllAssociative(
                "SELECT id, title, slug, icon FROM notes
                 WHERE user_id = ? AND is_deleted = FALSE AND is_template = FALSE
                   AND (title LIKE ? OR slug LIKE ?)
                 ORDER BY
                   CASE WHEN title LIKE ? THEN 0 ELSE 1 END,
                   title ASC
                 LIMIT 15",
                [$userId, $searchTerm, $searchTerm, $query . '%'],
                [\PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_STR]
            );
        }

        return JsonResponse::success($notes);
    }

    // =========================================================================
    // VERSIONS
    // =========================================================================

    /**
     * Get version history
     */
    public function versions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getNoteForUser($noteId, $userId);

        $versions = $this->db->fetchAllAssociative(
            "SELECT nv.id, nv.version_number, nv.title, nv.change_summary, nv.word_count,
                    nv.created_at, nv.created_by, u.username as created_by_name
             FROM note_versions nv
             LEFT JOIN users u ON nv.created_by = u.id
             WHERE nv.note_id = ?
             ORDER BY nv.version_number DESC",
            [$noteId]
        );

        return JsonResponse::success($versions);
    }

    /**
     * Get specific version
     */
    public function version(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $noteId = $route->getArgument('id');
        $versionId = $route->getArgument('versionId');

        $this->getNoteForUser($noteId, $userId);

        $version = $this->db->fetchAssociative(
            "SELECT nv.*, u.username as created_by_name
             FROM note_versions nv
             LEFT JOIN users u ON nv.created_by = u.id
             WHERE nv.id = ? AND nv.note_id = ?",
            [$versionId, $noteId]
        );

        if (!$version) {
            throw new NotFoundException('Version not found');
        }

        return JsonResponse::success($version);
    }

    /**
     * Restore a version
     */
    public function restoreVersion(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $noteId = $route->getArgument('id');
        $versionId = $route->getArgument('versionId');

        $note = $this->getNoteForUser($noteId, $userId);

        $version = $this->db->fetchAssociative(
            'SELECT * FROM note_versions WHERE id = ? AND note_id = ?',
            [$versionId, $noteId]
        );

        if (!$version) {
            throw new NotFoundException('Version not found');
        }

        // Create backup of current state
        $this->createVersion($noteId, $note['title'], $note['content'], $userId, 'Backup before restore');

        // Restore
        $this->db->update('notes', [
            'title' => $version['title'],
            'content' => $version['content'],
            'word_count' => $version['word_count'],
            'content_version' => $note['content_version'] + 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $noteId]);

        // Create restoration version
        $this->createVersion(
            $noteId,
            $version['title'],
            $version['content'],
            $userId,
            'Restored from version ' . $version['version_number']
        );

        // Update wiki links
        $this->wikiLinkService->updateLinks($noteId, $userId, $version['content']);

        return JsonResponse::success([
            'restored_version' => $version['version_number'],
        ], 'Version restored successfully');
    }

    // =========================================================================
    // TRASH & TEMPLATES
    // =========================================================================

    /**
     * Get trashed notes
     */
    public function trash(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $notes = $this->db->fetchAllAssociative(
            "SELECT id, title, slug, icon, deleted_at, updated_at
             FROM notes
             WHERE user_id = ? AND is_deleted = TRUE
             ORDER BY deleted_at DESC",
            [$userId]
        );

        return JsonResponse::success($notes);
    }

    /**
     * Restore from trash
     */
    public function restore(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $note = $this->db->fetchAssociative(
            'SELECT * FROM notes WHERE id = ? AND user_id = ? AND is_deleted = TRUE',
            [$noteId, $userId]
        );

        if (!$note) {
            throw new NotFoundException('Note not found in trash');
        }

        $this->db->update('notes', [
            'is_deleted' => false,
            'deleted_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $noteId]);

        return JsonResponse::success(null, 'Note restored');
    }

    /**
     * Permanently delete a note
     */
    public function permanentDelete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $note = $this->db->fetchAssociative(
            'SELECT id FROM notes WHERE id = ? AND user_id = ?',
            [$noteId, $userId]
        );

        if (!$note) {
            throw new NotFoundException('Note not found');
        }

        // Delete related data
        $this->db->delete('note_links', ['source_note_id' => $noteId]);
        $this->db->delete('note_links', ['target_note_id' => $noteId]);
        $this->db->delete('note_favorites', ['note_id' => $noteId]);
        $this->db->delete('note_recent', ['note_id' => $noteId]);
        $this->db->delete('note_tags', ['note_id' => $noteId]);
        $this->db->delete('note_versions', ['note_id' => $noteId]);

        // Delete note
        $this->db->delete('notes', ['id' => $noteId]);

        return JsonResponse::success(null, 'Note permanently deleted');
    }

    /**
     * Empty trash
     */
    public function emptyTrash(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        // Get all trashed notes
        $trashedNotes = $this->db->fetchAllAssociative(
            'SELECT id FROM notes WHERE user_id = ? AND is_deleted = TRUE',
            [$userId]
        );

        foreach ($trashedNotes as $note) {
            $this->db->delete('note_links', ['source_note_id' => $note['id']]);
            $this->db->delete('note_links', ['target_note_id' => $note['id']]);
            $this->db->delete('note_favorites', ['note_id' => $note['id']]);
            $this->db->delete('note_recent', ['note_id' => $note['id']]);
            $this->db->delete('note_tags', ['note_id' => $note['id']]);
            $this->db->delete('note_versions', ['note_id' => $note['id']]);
            $this->db->delete('notes', ['id' => $note['id']]);
        }

        return JsonResponse::success(['deleted_count' => count($trashedNotes)], 'Trash emptied');
    }

    /**
     * Get templates
     */
    public function templates(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        // Get system templates and user templates
        $templates = $this->db->fetchAllAssociative(
            "SELECT * FROM note_templates
             WHERE user_id IS NULL OR user_id = ?
             ORDER BY is_system DESC, sort_order ASC, name ASC",
            [$userId]
        );

        // Also get user's template notes
        $userTemplates = $this->db->fetchAllAssociative(
            "SELECT id, title as name, content, icon, 'user_note' as category, FALSE as is_system
             FROM notes
             WHERE user_id = ? AND is_template = TRUE AND is_deleted = FALSE
             ORDER BY title ASC",
            [$userId]
        );

        return JsonResponse::success([
            'system_templates' => array_filter($templates, fn($t) => $t['is_system']),
            'custom_templates' => array_filter($templates, fn($t) => !$t['is_system']),
            'note_templates' => $userTemplates,
        ]);
    }

    /**
     * Create note from template
     */
    public function fromTemplate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $templateId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        // Try to find template
        $template = $this->db->fetchAssociative(
            'SELECT * FROM note_templates WHERE id = ? AND (user_id IS NULL OR user_id = ?)',
            [$templateId, $userId]
        );

        // If not found, try note templates
        if (!$template) {
            $template = $this->db->fetchAssociative(
                'SELECT title as name, content, icon FROM notes WHERE id = ? AND user_id = ? AND is_template = TRUE',
                [$templateId, $userId]
            );
        }

        if (!$template) {
            throw new NotFoundException('Template not found');
        }

        // Create note
        $noteId = Uuid::uuid4()->toString();
        $title = $data['title'] ?? $template['name'];
        $content = $template['content'];
        $slug = $this->noteService->generateUniqueSlug($userId, $title);
        $wordCount = $this->noteService->countWords($content);

        $this->db->insert('notes', [
            'id' => $noteId,
            'user_id' => $userId,
            'parent_id' => $data['parent_id'] ?? null,
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'icon' => $template['icon'] ?? null,
            'word_count' => $wordCount,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->createVersion($noteId, $title, $content, $userId, 'Created from template');

        $note = $this->db->fetchAssociative('SELECT * FROM notes WHERE id = ?', [$noteId]);

        return JsonResponse::created($note, 'Note created from template');
    }

    /**
     * Duplicate a note
     */
    public function duplicate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $note = $this->getNoteForUser($noteId, $userId);

        $newNoteId = Uuid::uuid4()->toString();
        $newTitle = $note['title'] . ' (Copy)';
        $newSlug = $this->noteService->generateUniqueSlug($userId, $newTitle);

        $this->db->insert('notes', [
            'id' => $newNoteId,
            'user_id' => $userId,
            'parent_id' => $note['parent_id'],
            'title' => $newTitle,
            'slug' => $newSlug,
            'content' => $note['content'],
            'icon' => $note['icon'],
            'cover_image' => $note['cover_image'],
            'word_count' => $note['word_count'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->createVersion($newNoteId, $newTitle, $note['content'], $userId, 'Duplicated from ' . $note['title']);
        $this->wikiLinkService->updateLinks($newNoteId, $userId, $note['content']);

        $newNote = $this->db->fetchAssociative('SELECT * FROM notes WHERE id = ?', [$newNoteId]);

        return JsonResponse::created($newNote, 'Note duplicated');
    }

    /**
     * Get statistics
     */
    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $stats = $this->db->fetchAssociative(
            "SELECT
                COUNT(*) as total_notes,
                SUM(word_count) as total_words,
                SUM(CASE WHEN is_pinned = TRUE THEN 1 ELSE 0 END) as pinned_count,
                SUM(CASE WHEN is_archived = TRUE THEN 1 ELSE 0 END) as archived_count,
                SUM(CASE WHEN is_template = TRUE THEN 1 ELSE 0 END) as template_count,
                SUM(CASE WHEN is_deleted = TRUE THEN 1 ELSE 0 END) as trash_count
             FROM notes WHERE user_id = ?",
            [$userId]
        );

        $stats['favorites_count'] = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM note_favorites WHERE user_id = ?',
            [$userId]
        );

        return JsonResponse::success($stats);
    }

    // =========================================================================
    // TAGS
    // =========================================================================

    /**
     * Get tags for a note
     */
    public function getTags(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getNoteForUser($noteId, $userId);

        $tags = $this->db->fetchAllAssociative(
            "SELECT t.* FROM tags t
             JOIN note_tags nt ON t.id = nt.tag_id
             WHERE nt.note_id = ?
             ORDER BY t.name ASC",
            [$noteId]
        );

        return JsonResponse::success($tags);
    }

    /**
     * Add tags to a note
     */
    public function addTags(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getNoteForUser($noteId, $userId);

        $tagIds = $data['tag_ids'] ?? [];
        if (!is_array($tagIds)) {
            throw new ValidationException('tag_ids must be an array');
        }

        foreach ($tagIds as $tagId) {
            // Verify tag belongs to user
            $tag = $this->db->fetchOne(
                'SELECT id FROM tags WHERE id = ? AND user_id = ?',
                [$tagId, $userId]
            );

            if ($tag) {
                try {
                    $this->db->insert('note_tags', [
                        'note_id' => $noteId,
                        'tag_id' => $tagId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } catch (\Exception $e) {
                    // Ignore duplicate
                }
            }
        }

        return JsonResponse::success(null, 'Tags added');
    }

    /**
     * Remove tag from note
     */
    public function removeTag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $noteId = $route->getArgument('id');
        $tagId = $route->getArgument('tagId');

        $this->getNoteForUser($noteId, $userId);

        $this->db->delete('note_tags', [
            'note_id' => $noteId,
            'tag_id' => $tagId,
        ]);

        return JsonResponse::success(null, 'Tag removed');
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function getNoteForUser(string $noteId, string $userId): array
    {
        $note = $this->db->fetchAssociative(
            'SELECT * FROM notes WHERE id = ? AND user_id = ?',
            [$noteId, $userId]
        );

        if (!$note) {
            throw new NotFoundException('Note not found');
        }

        return $note;
    }

    private function createVersion(string $noteId, string $title, string $content, string $userId, ?string $summary = null): void
    {
        $lastVersion = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(version_number), 0) FROM note_versions WHERE note_id = ?',
            [$noteId]
        );

        $this->db->insert('note_versions', [
            'id' => Uuid::uuid4()->toString(),
            'note_id' => $noteId,
            'title' => $title,
            'content' => $content,
            'version_number' => $lastVersion + 1,
            'change_summary' => $summary,
            'word_count' => $this->noteService->countWords($content),
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function updateRecent(string $userId, string $noteId): void
    {
        // Upsert recent
        $this->db->executeStatement(
            'INSERT INTO note_recent (user_id, note_id, accessed_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE accessed_at = VALUES(accessed_at)',
            [$userId, $noteId, date('Y-m-d H:i:s')]
        );

        // Keep only last 50 recent
        $this->db->executeStatement(
            'DELETE FROM note_recent WHERE user_id = ? AND note_id NOT IN (
                SELECT note_id FROM (
                    SELECT note_id FROM note_recent WHERE user_id = ? ORDER BY accessed_at DESC LIMIT 50
                ) as keep_notes
            )',
            [$userId, $userId]
        );
    }

    private function softDeleteChildren(string $parentId): void
    {
        $children = $this->db->fetchAllAssociative(
            'SELECT id FROM notes WHERE parent_id = ? AND is_deleted = FALSE',
            [$parentId]
        );

        foreach ($children as $child) {
            $this->db->update('notes', [
                'is_deleted' => true,
                'deleted_at' => date('Y-m-d H:i:s'),
            ], ['id' => $child['id']]);

            $this->softDeleteChildren($child['id']);
        }
    }
}
