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

        $sql .= ' ORDER BY b.is_favorite DESC, b.click_count DESC, b.created_at DESC LIMIT ? OFFSET ?';
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
            'title' => $title,
            'url' => $url,
            'description' => $data['description'] ?? null,
            'favicon' => $this->getFavicon($url),
            'color' => $data['color'] ?? '#6366f1',
            'is_favorite' => !empty($data['is_favorite']) ? 1 : 0,
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
