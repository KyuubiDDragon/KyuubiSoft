<?php

declare(strict_types=1);

namespace App\Modules\Projects\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class ProjectController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $status = $params['status'] ?? null;
        $search = $params['search'] ?? null;

        // Simplified query - fetch projects first
        $sql = 'SELECT DISTINCT p.*
                FROM projects p
                LEFT JOIN project_shares ps ON ps.project_id = p.id AND ps.user_id = ?
                WHERE p.user_id = ? OR ps.user_id IS NOT NULL';
        $sqlParams = [$userId, $userId];
        $types = [\PDO::PARAM_STR, \PDO::PARAM_STR];

        if ($status) {
            $sql .= ' AND p.status = ?';
            $sqlParams[] = $status;
            $types[] = \PDO::PARAM_STR;
        }

        if ($search) {
            $sql .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
            $sqlParams[] = '%' . $search . '%';
            $sqlParams[] = '%' . $search . '%';
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
        }

        $sql .= ' ORDER BY p.is_favorite DESC, p.updated_at DESC';

        $projects = $this->db->fetchAllAssociative($sql, $sqlParams, $types);

        // Fetch counts separately for each project
        foreach ($projects as &$project) {
            $project['is_favorite'] = (bool) $project['is_favorite'];
            $project['is_owner'] = $project['user_id'] === $userId;

            // Get link count
            $project['link_count'] = (int) $this->db->fetchOne(
                'SELECT COUNT(*) FROM project_links WHERE project_id = ?',
                [$project['id']]
            );

            // Get total time
            $project['total_time_seconds'] = (int) ($this->db->fetchOne(
                'SELECT COALESCE(SUM(duration_seconds), 0) FROM time_entries WHERE project_id = ?',
                [$project['id']]
            ) ?? 0);
        }

        return JsonResponse::success(['items' => $projects]);
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            return JsonResponse::error( 'Name ist erforderlich', 400);
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('projects', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $name,
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#6366f1',
            'icon' => $data['icon'] ?? 'folder',
            'status' => 'active',
            'is_favorite' => 0,
        ]);

        return JsonResponse::success( [
            'id' => $id,
            'message' => 'Projekt erstellt',
        ], 201);
    }

    public function show(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $projectId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $project = $this->getProjectForUser($projectId, $userId);

        if (!$project) {
            return JsonResponse::error( 'Projekt nicht gefunden', 404);
        }

        // Get linked items
        $links = $this->db->fetchAllAssociative(
            'SELECT * FROM project_links WHERE project_id = ? ORDER BY created_at DESC',
            [$projectId]
        );

        // Fetch details for each linked item
        $linkedItems = [];
        foreach ($links as $link) {
            $item = $this->fetchLinkedItem($link['linkable_type'], $link['linkable_id']);
            if ($item) {
                $linkedItems[] = [
                    'link_id' => $link['id'],
                    'type' => $link['linkable_type'],
                    'id' => $link['linkable_id'],
                    'data' => $item,
                    'linked_at' => $link['created_at'],
                ];
            }
        }

        // Get time tracking summary
        $timeStats = $this->db->fetchAssociative(
            'SELECT
                COUNT(*) as entry_count,
                SUM(duration_seconds) as total_seconds,
                SUM(CASE WHEN is_billable THEN duration_seconds ELSE 0 END) as billable_seconds
             FROM time_entries
             WHERE project_id = ?',
            [$projectId]
        );

        $project['linked_items'] = $linkedItems;
        $project['time_stats'] = [
            'entry_count' => (int) $timeStats['entry_count'],
            'total_seconds' => (int) ($timeStats['total_seconds'] ?? 0),
            'billable_seconds' => (int) ($timeStats['billable_seconds'] ?? 0),
        ];
        $project['is_favorite'] = (bool) $project['is_favorite'];
        $project['is_owner'] = $project['user_id'] === $userId;

        return JsonResponse::success( $project);
    }

    public function update(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $projectId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $project = $this->getProjectForUser($projectId, $userId, 'edit');

        if (!$project) {
            return JsonResponse::error( 'Projekt nicht gefunden', 404);
        }

        $updates = [];
        $params = [];

        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $params[] = trim($data['name']);
        }

        if (array_key_exists('description', $data)) {
            $updates[] = 'description = ?';
            $params[] = $data['description'];
        }

        if (isset($data['color'])) {
            $updates[] = 'color = ?';
            $params[] = $data['color'];
        }

        if (isset($data['icon'])) {
            $updates[] = 'icon = ?';
            $params[] = $data['icon'];
        }

        if (isset($data['status'])) {
            if (!in_array($data['status'], ['active', 'archived', 'completed'])) {
                return JsonResponse::error( 'Ungültiger Status', 400);
            }
            $updates[] = 'status = ?';
            $params[] = $data['status'];
        }

        if (isset($data['is_favorite'])) {
            $updates[] = 'is_favorite = ?';
            $params[] = $data['is_favorite'] ? 1 : 0;
        }

        if (!empty($updates)) {
            $params[] = $projectId;
            $this->db->executeStatement(
                'UPDATE projects SET ' . implode(', ', $updates) . ' WHERE id = ?',
                $params
            );
        }

        return JsonResponse::success( ['message' => 'Projekt aktualisiert']);
    }

    public function delete(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $projectId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Only owner can delete
        $deleted = $this->db->delete('projects', [
            'id' => $projectId,
            'user_id' => $userId,
        ]);

        if (!$deleted) {
            return JsonResponse::error( 'Projekt nicht gefunden', 404);
        }

        return JsonResponse::success( ['message' => 'Projekt gelöscht']);
    }

    // Link management
    public function addLink(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $projectId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $project = $this->getProjectForUser($projectId, $userId, 'edit');

        if (!$project) {
            return JsonResponse::error( 'Projekt nicht gefunden', 404);
        }

        $type = $data['type'] ?? '';
        $itemId = $data['item_id'] ?? '';

        $validTypes = ['document', 'list', 'kanban_board', 'connection', 'snippet'];
        if (!in_array($type, $validTypes)) {
            return JsonResponse::error( 'Ungültiger Linktyp', 400);
        }

        // Verify the item exists
        $item = $this->fetchLinkedItem($type, $itemId);
        if (!$item) {
            return JsonResponse::error( 'Element nicht gefunden', 404);
        }

        // Check if link already exists
        $existing = $this->db->fetchOne(
            'SELECT id FROM project_links WHERE project_id = ? AND linkable_type = ? AND linkable_id = ?',
            [$projectId, $type, $itemId]
        );

        if ($existing) {
            return JsonResponse::error( 'Link existiert bereits', 400);
        }

        $linkId = Uuid::uuid4()->toString();

        $this->db->insert('project_links', [
            'id' => $linkId,
            'project_id' => $projectId,
            'linkable_type' => $type,
            'linkable_id' => $itemId,
        ]);

        return JsonResponse::success( [
            'id' => $linkId,
            'message' => 'Element verknüpft',
        ], 201);
    }

    public function removeLink(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $projectId = $route->getArgument('id');
        $linkId = $route->getArgument('linkId');

        $project = $this->getProjectForUser($projectId, $userId, 'edit');

        if (!$project) {
            return JsonResponse::error( 'Projekt nicht gefunden', 404);
        }

        $deleted = $this->db->delete('project_links', [
            'id' => $linkId,
            'project_id' => $projectId,
        ]);

        if (!$deleted) {
            return JsonResponse::error( 'Link nicht gefunden', 404);
        }

        return JsonResponse::success( ['message' => 'Verknüpfung entfernt']);
    }

    public function getLinkableItems(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $projectId = $route->getArgument('id');
        $type = $route->getArgument('type');

        $validTypes = ['document', 'list', 'kanban_board', 'connection', 'snippet'];
        if (!in_array($type, $validTypes)) {
            return JsonResponse::error( 'Ungültiger Typ', 400);
        }

        // Get items not yet linked to this project
        $items = match($type) {
            'document' => $this->db->fetchAllAssociative(
                'SELECT id, title as name, type, updated_at FROM documents
                 WHERE user_id = ? AND id NOT IN (SELECT linkable_id FROM project_links WHERE project_id = ? AND linkable_type = ?)
                 ORDER BY updated_at DESC LIMIT 50',
                [$userId, $projectId, $type]
            ),
            'list' => $this->db->fetchAllAssociative(
                'SELECT id, title as name, updated_at FROM lists
                 WHERE user_id = ? AND id NOT IN (SELECT linkable_id FROM project_links WHERE project_id = ? AND linkable_type = ?)
                 ORDER BY updated_at DESC LIMIT 50',
                [$userId, $projectId, $type]
            ),
            'kanban_board' => $this->db->fetchAllAssociative(
                'SELECT id, title as name, color, updated_at FROM kanban_boards
                 WHERE user_id = ? AND id NOT IN (SELECT linkable_id FROM project_links WHERE project_id = ? AND linkable_type = ?)
                 ORDER BY updated_at DESC LIMIT 50',
                [$userId, $projectId, $type]
            ),
            'connection' => $this->db->fetchAllAssociative(
                'SELECT id, name, type, host FROM connections
                 WHERE user_id = ? AND id NOT IN (SELECT linkable_id FROM project_links WHERE project_id = ? AND linkable_type = ?)
                 ORDER BY updated_at DESC LIMIT 50',
                [$userId, $projectId, $type]
            ),
            'snippet' => $this->db->fetchAllAssociative(
                'SELECT id, title as name, language, category FROM snippets
                 WHERE user_id = ? AND id NOT IN (SELECT linkable_id FROM project_links WHERE project_id = ? AND linkable_type = ?)
                 ORDER BY updated_at DESC LIMIT 50',
                [$userId, $projectId, $type]
            ),
            default => [],
        };

        return JsonResponse::success( ['items' => $items]);
    }

    // Shares
    public function getShares(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $projectId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $project = $this->getProjectForUser($projectId, $userId);

        if (!$project) {
            return JsonResponse::error( 'Projekt nicht gefunden', 404);
        }

        $shares = $this->db->fetchAllAssociative(
            'SELECT ps.user_id, ps.permission, ps.created_at, u.username, u.email
             FROM project_shares ps
             JOIN users u ON u.id = ps.user_id
             WHERE ps.project_id = ?',
            [$projectId]
        );

        return JsonResponse::success( ['shares' => $shares]);
    }

    public function addShare(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $projectId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        // Only owner can share
        $project = $this->db->fetchAssociative(
            'SELECT id FROM projects WHERE id = ? AND user_id = ?',
            [$projectId, $userId]
        );

        if (!$project) {
            return JsonResponse::error( 'Projekt nicht gefunden oder keine Berechtigung', 404);
        }

        $shareUserId = $data['user_id'] ?? null;
        $email = $data['email'] ?? null;
        $permission = $data['permission'] ?? 'view';

        if (!in_array($permission, ['view', 'edit'])) {
            return JsonResponse::error( 'Ungültige Berechtigung', 400);
        }

        // Find user by ID or email
        if ($shareUserId) {
            $shareUser = $this->db->fetchAssociative('SELECT id FROM users WHERE id = ?', [$shareUserId]);
        } elseif ($email) {
            $shareUser = $this->db->fetchAssociative('SELECT id FROM users WHERE email = ?', [$email]);
        } else {
            return JsonResponse::error( 'User-ID oder E-Mail erforderlich', 400);
        }

        if (!$shareUser) {
            return JsonResponse::error( 'Benutzer nicht gefunden', 404);
        }

        if ($shareUser['id'] === $userId) {
            return JsonResponse::error( 'Kann nicht mit sich selbst teilen', 400);
        }

        $this->db->executeStatement(
            'INSERT INTO project_shares (project_id, user_id, permission) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE permission = VALUES(permission)',
            [$projectId, $shareUser['id'], $permission]
        );

        return JsonResponse::success( ['message' => 'Projekt geteilt']);
    }

    public function removeShare(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $projectId = $route->getArgument('id');
        $shareUserId = $route->getArgument('userId');

        // Only owner can remove shares
        $project = $this->db->fetchAssociative(
            'SELECT id FROM projects WHERE id = ? AND user_id = ?',
            [$projectId, $userId]
        );

        if (!$project) {
            return JsonResponse::error( 'Projekt nicht gefunden oder keine Berechtigung', 404);
        }

        $this->db->delete('project_shares', [
            'project_id' => $projectId,
            'user_id' => $shareUserId,
        ]);

        return JsonResponse::success( ['message' => 'Freigabe entfernt']);
    }

    private function getProjectForUser(string $projectId, string $userId, string $requiredPermission = 'view'): ?array
    {
        $project = $this->db->fetchAssociative(
            'SELECT p.*, ps.permission as shared_permission
             FROM projects p
             LEFT JOIN project_shares ps ON ps.project_id = p.id AND ps.user_id = ?
             WHERE p.id = ? AND (p.user_id = ? OR ps.user_id IS NOT NULL)',
            [$userId, $projectId, $userId]
        );

        if (!$project) {
            return null;
        }

        // Check permission
        if ($requiredPermission === 'edit' && $project['user_id'] !== $userId && $project['shared_permission'] !== 'edit') {
            return null;
        }

        return $project;
    }

    private function fetchLinkedItem(string $type, string $itemId): ?array
    {
        return match($type) {
            'document' => $this->db->fetchAssociative('SELECT id, title, type FROM documents WHERE id = ?', [$itemId]),
            'list' => $this->db->fetchAssociative('SELECT id, title FROM lists WHERE id = ?', [$itemId]),
            'kanban_board' => $this->db->fetchAssociative('SELECT id, title, color FROM kanban_boards WHERE id = ?', [$itemId]),
            'connection' => $this->db->fetchAssociative('SELECT id, name, type, host FROM connections WHERE id = ?', [$itemId]),
            'snippet' => $this->db->fetchAssociative('SELECT id, title, language FROM snippets WHERE id = ?', [$itemId]),
            default => null,
        };
    }
}
