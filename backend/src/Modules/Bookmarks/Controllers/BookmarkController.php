<?php

declare(strict_types=1);

namespace App\Modules\Bookmarks\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class BookmarkController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $search = $queryParams['search'] ?? null;
        $tagId = $queryParams['tag_id'] ?? null;
        $groupId = $queryParams['group_id'] ?? null;
        $grouped = ($queryParams['grouped'] ?? 'false') === 'true';

        // If grouped view requested, return groups with their bookmarks
        if ($grouped) {
            return $this->getGroupedBookmarks($userId, $search, $tagId);
        }

        $sql = 'SELECT b.* FROM bookmarks b WHERE b.user_id = ?';
        $params = [$userId];
        $types = [\PDO::PARAM_STR];

        if ($search) {
            $sql .= ' AND (b.title LIKE ? OR b.url LIKE ? OR b.description LIKE ?)';
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
        }

        if ($tagId) {
            $sql .= ' AND EXISTS (SELECT 1 FROM bookmark_tag_mappings btm WHERE btm.bookmark_id = b.id AND btm.tag_id = ?)';
            $params[] = $tagId;
            $types[] = \PDO::PARAM_STR;
        }

        if ($groupId === 'null') {
            $sql .= ' AND b.group_id IS NULL';
        } elseif ($groupId) {
            $sql .= ' AND b.group_id = ?';
            $params[] = $groupId;
            $types[] = \PDO::PARAM_STR;
        }

        $sql .= ' ORDER BY b.is_favorite DESC, b.position ASC, b.click_count DESC, b.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $perPage;
        $params[] = $offset;
        $types[] = \PDO::PARAM_INT;
        $types[] = \PDO::PARAM_INT;

        $bookmarks = $this->db->fetchAllAssociative($sql, $params, $types);

        // Add tags to each bookmark and cast booleans
        foreach ($bookmarks as &$bookmark) {
            $bookmark['is_favorite'] = (bool) $bookmark['is_favorite'];
            $bookmark['tags'] = $this->db->fetchAllAssociative(
                'SELECT bt.* FROM bookmark_tags bt
                 JOIN bookmark_tag_mappings btm ON bt.id = btm.tag_id
                 WHERE btm.bookmark_id = ?',
                [$bookmark['id']]
            );
        }

        // Count total
        $countSql = 'SELECT COUNT(*) FROM bookmarks b WHERE b.user_id = ?';
        $countParams = [$userId];

        if ($search) {
            $countSql .= ' AND (b.title LIKE ? OR b.url LIKE ? OR b.description LIKE ?)';
            $countParams[] = "%{$search}%";
            $countParams[] = "%{$search}%";
            $countParams[] = "%{$search}%";
        }
        if ($tagId) {
            $countSql .= ' AND EXISTS (SELECT 1 FROM bookmark_tag_mappings btm WHERE btm.bookmark_id = b.id AND btm.tag_id = ?)';
            $countParams[] = $tagId;
        }

        $total = (int) $this->db->fetchOne($countSql, $countParams);

        return JsonResponse::paginated($bookmarks, $total, $page, $perPage);
    }

    private function getGroupedBookmarks(string $userId, ?string $search, ?string $tagId): ResponseInterface
    {
        // Get all groups
        $groups = $this->db->fetchAllAssociative(
            'SELECT * FROM bookmark_groups WHERE user_id = ? ORDER BY position ASC, name ASC',
            [$userId]
        );

        // Build bookmark query with optional filters
        $bookmarkSql = 'SELECT b.* FROM bookmarks b WHERE b.user_id = ?';
        $params = [$userId];

        if ($search) {
            $bookmarkSql .= ' AND (b.title LIKE ? OR b.url LIKE ? OR b.description LIKE ?)';
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($tagId) {
            $bookmarkSql .= ' AND EXISTS (SELECT 1 FROM bookmark_tag_mappings btm WHERE btm.bookmark_id = b.id AND btm.tag_id = ?)';
            $params[] = $tagId;
        }

        $bookmarkSql .= ' ORDER BY b.is_favorite DESC, b.position ASC, b.click_count DESC';
        $allBookmarks = $this->db->fetchAllAssociative($bookmarkSql, $params);

        // Add tags to each bookmark
        foreach ($allBookmarks as &$bookmark) {
            $bookmark['is_favorite'] = (bool) $bookmark['is_favorite'];
            $bookmark['tags'] = $this->db->fetchAllAssociative(
                'SELECT bt.* FROM bookmark_tags bt
                 JOIN bookmark_tag_mappings btm ON bt.id = btm.tag_id
                 WHERE btm.bookmark_id = ?',
                [$bookmark['id']]
            );
        }

        // Organize bookmarks by group
        $ungrouped = [];
        $groupedBookmarks = [];

        foreach ($allBookmarks as $bookmark) {
            if ($bookmark['group_id']) {
                if (!isset($groupedBookmarks[$bookmark['group_id']])) {
                    $groupedBookmarks[$bookmark['group_id']] = [];
                }
                $groupedBookmarks[$bookmark['group_id']][] = $bookmark;
            } else {
                $ungrouped[] = $bookmark;
            }
        }

        // Attach bookmarks to groups
        foreach ($groups as &$group) {
            $group['is_collapsed'] = (bool) $group['is_collapsed'];
            $group['bookmarks'] = $groupedBookmarks[$group['id']] ?? [];
            $group['bookmark_count'] = count($group['bookmarks']);
        }

        return JsonResponse::success([
            'groups' => $groups,
            'ungrouped' => $ungrouped,
            'total_groups' => count($groups),
            'total_bookmarks' => count($allBookmarks),
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['url'])) {
            throw new ValidationException('URL is required');
        }

        $url = filter_var($data['url'], FILTER_VALIDATE_URL);
        if (!$url) {
            throw new ValidationException('Invalid URL format');
        }

        $id = Uuid::uuid4()->toString();
        $title = !empty($data['title']) ? $data['title'] : $this->extractTitle($url);

        $this->db->insert('bookmarks', [
            'id' => $id,
            'user_id' => $userId,
            'group_id' => $data['group_id'] ?? null,
            'title' => $title,
            'url' => $url,
            'description' => $data['description'] ?? null,
            'favicon' => $this->getFavicon($url),
            'color' => $data['color'] ?? '#6366f1',
            'is_favorite' => !empty($data['is_favorite']) ? 1 : 0,
            'position' => $data['position'] ?? 0,
        ]);

        // Handle tags
        if (!empty($data['tag_ids'])) {
            foreach ($data['tag_ids'] as $tagId) {
                $this->db->insert('bookmark_tag_mappings', [
                    'bookmark_id' => $id,
                    'tag_id' => $tagId,
                ]);
            }
        }

        $bookmark = $this->db->fetchAssociative(
            'SELECT * FROM bookmarks WHERE id = ?',
            [$id]
        );
        $bookmark['is_favorite'] = (bool) $bookmark['is_favorite'];

        return JsonResponse::created($bookmark, 'Bookmark created');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $bookmark = $this->db->fetchAssociative(
            'SELECT * FROM bookmarks WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$bookmark) {
            throw new NotFoundException('Bookmark not found');
        }

        $bookmark['is_favorite'] = (bool) $bookmark['is_favorite'];
        $bookmark['tags'] = $this->db->fetchAllAssociative(
            'SELECT bt.* FROM bookmark_tags bt
             JOIN bookmark_tag_mappings btm ON bt.id = btm.tag_id
             WHERE btm.bookmark_id = ?',
            [$id]
        );

        return JsonResponse::success($bookmark);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $bookmark = $this->db->fetchAssociative(
            'SELECT * FROM bookmarks WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$bookmark) {
            throw new NotFoundException('Bookmark not found');
        }

        $updates = [];
        $params = [];

        if (isset($data['title'])) {
            $updates[] = 'title = ?';
            $params[] = $data['title'];
        }
        if (isset($data['url'])) {
            $url = filter_var($data['url'], FILTER_VALIDATE_URL);
            if (!$url) {
                throw new ValidationException('Invalid URL format');
            }
            $updates[] = 'url = ?';
            $params[] = $url;
            $updates[] = 'favicon = ?';
            $params[] = $this->getFavicon($url);
        }
        if (isset($data['description'])) {
            $updates[] = 'description = ?';
            $params[] = $data['description'];
        }
        if (isset($data['color'])) {
            $updates[] = 'color = ?';
            $params[] = $data['color'];
        }
        if (isset($data['is_favorite'])) {
            $updates[] = 'is_favorite = ?';
            $params[] = $data['is_favorite'] ? 1 : 0;
        }
        if (array_key_exists('group_id', $data)) {
            $updates[] = 'group_id = ?';
            $params[] = $data['group_id'];
        }
        if (isset($data['position'])) {
            $updates[] = 'position = ?';
            $params[] = (int) $data['position'];
        }

        if (!empty($updates)) {
            $params[] = $id;
            $this->db->executeStatement(
                'UPDATE bookmarks SET ' . implode(', ', $updates) . ' WHERE id = ?',
                $params
            );
        }

        // Update tags
        if (isset($data['tag_ids'])) {
            $this->db->executeStatement(
                'DELETE FROM bookmark_tag_mappings WHERE bookmark_id = ?',
                [$id]
            );
            foreach ($data['tag_ids'] as $tagId) {
                $this->db->insert('bookmark_tag_mappings', [
                    'bookmark_id' => $id,
                    'tag_id' => $tagId,
                ]);
            }
        }

        return JsonResponse::success(null, 'Bookmark updated');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $bookmark = $this->db->fetchAssociative(
            'SELECT * FROM bookmarks WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$bookmark) {
            throw new NotFoundException('Bookmark not found');
        }

        $this->db->delete('bookmarks', ['id' => $id]);

        return JsonResponse::success(null, 'Bookmark deleted');
    }

    public function click(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $this->db->executeStatement(
            'UPDATE bookmarks SET click_count = click_count + 1, last_clicked_at = NOW()
             WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        return JsonResponse::success(null, 'Click recorded');
    }

    // Tags management
    public function getTags(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $tags = $this->db->fetchAllAssociative(
            'SELECT bt.*, COUNT(btm.bookmark_id) as bookmark_count
             FROM bookmark_tags bt
             LEFT JOIN bookmark_tag_mappings btm ON bt.id = btm.tag_id
             WHERE bt.user_id = ?
             GROUP BY bt.id
             ORDER BY bt.name',
            [$userId]
        );

        return JsonResponse::success(['items' => $tags]);
    }

    public function createTag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            throw new ValidationException('Tag name is required');
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('bookmark_tags', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'color' => $data['color'] ?? '#6366f1',
        ]);

        $tag = $this->db->fetchAssociative('SELECT * FROM bookmark_tags WHERE id = ?', [$id]);

        return JsonResponse::created($tag, 'Tag created');
    }

    public function deleteTag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $tagId = $routeContext->getRoute()->getArgument('tagId');

        $this->db->delete('bookmark_tags', ['id' => $tagId, 'user_id' => $userId]);

        return JsonResponse::success(null, 'Tag deleted');
    }

    // ==================
    // Group Methods
    // ==================

    public function getGroups(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $groups = $this->db->fetchAllAssociative(
            'SELECT g.*, COUNT(b.id) as bookmark_count
             FROM bookmark_groups g
             LEFT JOIN bookmarks b ON g.id = b.group_id
             WHERE g.user_id = ?
             GROUP BY g.id
             ORDER BY g.position ASC, g.name ASC',
            [$userId]
        );

        foreach ($groups as &$group) {
            $group['is_collapsed'] = (bool) $group['is_collapsed'];
        }

        return JsonResponse::success(['items' => $groups]);
    }

    public function createGroup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            throw new ValidationException('Group name is required');
        }

        // Get next position
        $maxPosition = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), -1) FROM bookmark_groups WHERE user_id = ?',
            [$userId]
        );

        $id = Uuid::uuid4()->toString();

        $this->db->insert('bookmark_groups', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'color' => $data['color'] ?? '#6366f1',
            'icon' => $data['icon'] ?? 'folder',
            'position' => $maxPosition + 1,
        ]);

        $group = $this->db->fetchAssociative('SELECT * FROM bookmark_groups WHERE id = ?', [$id]);
        $group['is_collapsed'] = (bool) $group['is_collapsed'];
        $group['bookmark_count'] = 0;

        return JsonResponse::created($group, 'Group created');
    }

    public function updateGroup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $groupId = $routeContext->getRoute()->getArgument('groupId');
        $data = $request->getParsedBody();

        $group = $this->db->fetchAssociative(
            'SELECT * FROM bookmark_groups WHERE id = ? AND user_id = ?',
            [$groupId, $userId]
        );

        if (!$group) {
            throw new NotFoundException('Group not found');
        }

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['color'])) {
            $updateData['color'] = $data['color'];
        }
        if (isset($data['icon'])) {
            $updateData['icon'] = $data['icon'];
        }
        if (isset($data['position'])) {
            $updateData['position'] = (int) $data['position'];
        }
        if (isset($data['is_collapsed'])) {
            $updateData['is_collapsed'] = $data['is_collapsed'] ? 1 : 0;
        }

        $this->db->update('bookmark_groups', $updateData, ['id' => $groupId]);

        $group = $this->db->fetchAssociative('SELECT * FROM bookmark_groups WHERE id = ?', [$groupId]);
        $group['is_collapsed'] = (bool) $group['is_collapsed'];

        return JsonResponse::success($group, 'Group updated');
    }

    public function deleteGroup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $groupId = $routeContext->getRoute()->getArgument('groupId');
        $queryParams = $request->getQueryParams();
        $deleteBookmarks = ($queryParams['delete_bookmarks'] ?? 'false') === 'true';

        $group = $this->db->fetchAssociative(
            'SELECT * FROM bookmark_groups WHERE id = ? AND user_id = ?',
            [$groupId, $userId]
        );

        if (!$group) {
            throw new NotFoundException('Group not found');
        }

        if ($deleteBookmarks) {
            // Delete all bookmarks in the group
            $this->db->executeStatement(
                'DELETE FROM bookmarks WHERE group_id = ?',
                [$groupId]
            );
        }
        // Note: If not deleting bookmarks, foreign key ON DELETE SET NULL handles ungrouping

        $this->db->delete('bookmark_groups', ['id' => $groupId]);

        return JsonResponse::success(null, 'Group deleted');
    }

    public function reorderGroups(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (!isset($data['groups']) || !is_array($data['groups'])) {
            throw new ValidationException('Groups array is required');
        }

        foreach ($data['groups'] as $position => $groupId) {
            $this->db->executeStatement(
                'UPDATE bookmark_groups SET position = ?, updated_at = NOW() WHERE id = ? AND user_id = ?',
                [(int) $position, $groupId, $userId]
            );
        }

        return JsonResponse::success(null, 'Groups reordered');
    }

    public function moveBookmarkToGroup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $bookmarkId = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $bookmark = $this->db->fetchAssociative(
            'SELECT * FROM bookmarks WHERE id = ? AND user_id = ?',
            [$bookmarkId, $userId]
        );

        if (!$bookmark) {
            throw new NotFoundException('Bookmark not found');
        }

        $groupId = $data['group_id'] ?? null;

        // Verify group belongs to user if specified
        if ($groupId) {
            $group = $this->db->fetchAssociative(
                'SELECT id FROM bookmark_groups WHERE id = ? AND user_id = ?',
                [$groupId, $userId]
            );
            if (!$group) {
                throw new NotFoundException('Group not found');
            }
        }

        $this->db->update('bookmarks', [
            'group_id' => $groupId,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $bookmarkId]);

        return JsonResponse::success(null, 'Bookmark moved');
    }

    private function extractTitle(string $url): string
    {
        // Simple title extraction from URL
        $parsed = parse_url($url);
        return $parsed['host'] ?? $url;
    }

    private function getFavicon(string $url): string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        return "https://www.google.com/s2/favicons?domain={$host}&sz=64";
    }
}
