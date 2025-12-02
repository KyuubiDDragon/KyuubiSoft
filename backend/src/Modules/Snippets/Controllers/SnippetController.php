<?php

declare(strict_types=1);

namespace App\Modules\Snippets\Controllers;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class SnippetController
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
        $language = $queryParams['language'] ?? null;
        $category = $queryParams['category'] ?? null;
        $projectId = $queryParams['project_id'] ?? null;

        // Build project filter
        $projectJoin = '';
        $projectParams = [];
        $projectTypes = [];

        if ($projectId) {
            $projectJoin = ' INNER JOIN project_links pl ON pl.linkable_id = s.id AND pl.linkable_type = ? AND pl.project_id = ?';
            $projectParams = ['snippet', $projectId];
            $projectTypes = [\PDO::PARAM_STR, \PDO::PARAM_STR];
        }

        $sql = 'SELECT s.* FROM snippets s' . $projectJoin . ' WHERE s.user_id = ?';
        $params = array_merge($projectParams, [$userId]);
        $types = array_merge($projectTypes, [\PDO::PARAM_STR]);

        if ($search) {
            $sql .= ' AND (s.title LIKE ? OR s.description LIKE ? OR s.content LIKE ?)';
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
        }

        if ($language) {
            $sql .= ' AND s.language = ?';
            $params[] = $language;
            $types[] = \PDO::PARAM_STR;
        }

        if ($category) {
            $sql .= ' AND s.category = ?';
            $params[] = $category;
            $types[] = \PDO::PARAM_STR;
        }

        $sql .= ' ORDER BY s.is_favorite DESC, s.use_count DESC, s.updated_at DESC LIMIT ? OFFSET ?';
        $params[] = $perPage;
        $params[] = $offset;
        $types[] = \PDO::PARAM_INT;
        $types[] = \PDO::PARAM_INT;

        $snippets = $this->db->fetchAllAssociative($sql, $params, $types);

        // Parse tags JSON
        foreach ($snippets as &$snippet) {
            $snippet['tags'] = $snippet['tags'] ? json_decode($snippet['tags'], true) : [];
        }

        // Count total
        $countSql = 'SELECT COUNT(*) FROM snippets s' . $projectJoin . ' WHERE s.user_id = ?';
        $countParams = array_merge($projectParams, [$userId]);

        if ($search) {
            $countSql .= ' AND (s.title LIKE ? OR s.description LIKE ? OR s.content LIKE ?)';
            $countParams[] = "%{$search}%";
            $countParams[] = "%{$search}%";
            $countParams[] = "%{$search}%";
        }
        if ($language) {
            $countSql .= ' AND s.language = ?';
            $countParams[] = $language;
        }
        if ($category) {
            $countSql .= ' AND s.category = ?';
            $countParams[] = $category;
        }

        $total = (int) $this->db->fetchOne($countSql, $countParams);

        return JsonResponse::paginated($snippets, $total, $page, $perPage);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['title'])) {
            throw new ValidationException('Title is required');
        }
        if (empty($data['content'])) {
            throw new ValidationException('Content is required');
        }

        $snippetId = Uuid::uuid4()->toString();

        $this->db->insert('snippets', [
            'id' => $snippetId,
            'user_id' => $userId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'content' => $data['content'],
            'language' => $data['language'] ?? 'text',
            'category' => $data['category'] ?? null,
            'tags' => isset($data['tags']) ? json_encode($data['tags']) : null,
            'is_favorite' => !empty($data['is_favorite']) ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $snippet = $this->db->fetchAssociative('SELECT * FROM snippets WHERE id = ?', [$snippetId]);
        $snippet['tags'] = $snippet['tags'] ? json_decode($snippet['tags'], true) : [];

        return JsonResponse::created($snippet, 'Snippet created successfully');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $snippetId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $snippet = $this->getSnippetForUser($snippetId, $userId);
        $snippet['tags'] = $snippet['tags'] ? json_decode($snippet['tags'], true) : [];

        return JsonResponse::success($snippet);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $snippetId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getSnippetForUser($snippetId, $userId);

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        $allowedFields = ['title', 'description', 'content', 'language', 'category', 'is_favorite'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['tags'])) {
            $updateData['tags'] = json_encode($data['tags']);
        }

        $this->db->update('snippets', $updateData, ['id' => $snippetId]);

        $snippet = $this->db->fetchAssociative('SELECT * FROM snippets WHERE id = ?', [$snippetId]);
        $snippet['tags'] = $snippet['tags'] ? json_decode($snippet['tags'], true) : [];

        return JsonResponse::success($snippet, 'Snippet updated successfully');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $snippetId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getSnippetForUser($snippetId, $userId);
        $this->db->delete('snippets', ['id' => $snippetId]);

        return JsonResponse::success(null, 'Snippet deleted successfully');
    }

    public function copy(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $snippetId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $snippet = $this->getSnippetForUser($snippetId, $userId);

        // Increment use count and update last_used
        $this->db->update('snippets', [
            'use_count' => $snippet['use_count'] + 1,
            'last_used_at' => date('Y-m-d H:i:s'),
        ], ['id' => $snippetId]);

        return JsonResponse::success([
            'content' => $snippet['content'],
        ]);
    }

    public function getCategories(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $categories = $this->db->fetchAllAssociative(
            'SELECT category, COUNT(*) as count
             FROM snippets
             WHERE user_id = ? AND category IS NOT NULL
             GROUP BY category
             ORDER BY count DESC',
            [$userId]
        );

        return JsonResponse::success($categories);
    }

    public function getLanguages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $languages = $this->db->fetchAllAssociative(
            'SELECT language, COUNT(*) as count
             FROM snippets
             WHERE user_id = ?
             GROUP BY language
             ORDER BY count DESC',
            [$userId]
        );

        return JsonResponse::success($languages);
    }

    private function getSnippetForUser(string $snippetId, string $userId): array
    {
        $snippet = $this->db->fetchAssociative(
            'SELECT * FROM snippets WHERE id = ? AND user_id = ?',
            [$snippetId, $userId]
        );

        if (!$snippet) {
            throw new NotFoundException('Snippet not found');
        }

        return $snippet;
    }
}
