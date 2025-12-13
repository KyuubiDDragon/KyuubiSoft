<?php

declare(strict_types=1);

namespace App\Modules\Lists\Controllers;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Core\Services\ProjectAccessService;
use App\Modules\Webhooks\Services\WebhookService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class ListController
{
    public function __construct(
        private readonly Connection $db,
        private readonly ProjectAccessService $projectAccess,
        private readonly WebhookService $webhookService
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $projectId = $queryParams['project_id'] ?? null;

        // Check if user is restricted to projects only
        $isRestricted = $this->projectAccess->isUserRestricted($userId);
        $accessibleProjectIds = [];

        if ($isRestricted) {
            $accessibleProjectIds = $this->projectAccess->getUserAccessibleProjectIds($userId);
            if (empty($accessibleProjectIds)) {
                return JsonResponse::paginated([], 0, $page, $perPage);
            }
        }

        // Build project filter
        $projectJoin = '';
        $projectParams = [];
        $projectTypes = [];

        if ($projectId) {
            if ($isRestricted && !in_array($projectId, $accessibleProjectIds)) {
                return JsonResponse::paginated([], 0, $page, $perPage);
            }
            $projectJoin = ' INNER JOIN project_links pl ON pl.linkable_id = l.id AND pl.linkable_type = ? AND pl.project_id = ?';
            $projectParams = ['list', $projectId];
            $projectTypes = [\PDO::PARAM_STR, \PDO::PARAM_STR];
        } elseif ($isRestricted) {
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $projectJoin = " INNER JOIN project_links pl ON pl.linkable_id = l.id AND pl.linkable_type = 'list' AND pl.project_id IN ({$placeholders})";
            $projectParams = $accessibleProjectIds;
            $projectTypes = array_fill(0, count($accessibleProjectIds), \PDO::PARAM_STR);
        }

        $whereClause = $isRestricted ? 'l.is_archived = FALSE' : 'l.user_id = ? AND l.is_archived = FALSE';

        $sql = 'SELECT DISTINCT l.*,
                    (SELECT COUNT(*) FROM list_items WHERE list_id = l.id) as item_count,
                    (SELECT COUNT(*) FROM list_items WHERE list_id = l.id AND is_completed = TRUE) as completed_count
             FROM lists l' . $projectJoin . '
             WHERE ' . $whereClause . '
             ORDER BY l.updated_at DESC
             LIMIT ? OFFSET ?';

        $params = $isRestricted
            ? array_merge($projectParams, [$perPage, $offset])
            : array_merge($projectParams, [$userId, $perPage, $offset]);
        $types = $isRestricted
            ? array_merge($projectTypes, [\PDO::PARAM_INT, \PDO::PARAM_INT])
            : array_merge($projectTypes, [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]);

        $lists = $this->db->fetchAllAssociative($sql, $params, $types);

        $countSql = 'SELECT COUNT(DISTINCT l.id) FROM lists l' . $projectJoin . ' WHERE ' . $whereClause;
        $countParams = $isRestricted ? $projectParams : array_merge($projectParams, [$userId]);

        $total = (int) $this->db->fetchOne($countSql, $countParams);

        return JsonResponse::paginated($lists, $total, $page, $perPage);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['title'])) {
            throw new ValidationException('Title is required');
        }

        $listId = Uuid::uuid4()->toString();

        $this->db->insert('lists', [
            'id' => $listId,
            'user_id' => $userId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'todo',
            'color' => $data['color'] ?? '#3B82F6',
            'icon' => $data['icon'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $list = $this->db->fetchAssociative('SELECT * FROM lists WHERE id = ?', [$listId]);

        // Trigger webhook
        $this->webhookService->trigger($userId, 'list.created', [
            'id' => $listId,
            'title' => $list['title'],
            'message' => 'Neue Liste erstellt: ' . $list['title'],
        ]);

        return JsonResponse::created($list, 'List created successfully');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $listId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $list = $this->getListForUser($listId, $userId);

        // Get items
        $list['items'] = $this->db->fetchAllAssociative(
            'SELECT * FROM list_items WHERE list_id = ? ORDER BY position, created_at',
            [$listId]
        );

        return JsonResponse::success($list);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $listId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $list = $this->getListForUser($listId, $userId, true);

        $updateData = [];
        $allowedFields = ['title', 'description', 'type', 'color', 'icon', 'is_archived'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('lists', $updateData, ['id' => $listId]);
        }

        $updated = $this->db->fetchAssociative('SELECT * FROM lists WHERE id = ?', [$listId]);

        return JsonResponse::success($updated, 'List updated successfully');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $listId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $list = $this->getListForUser($listId, $userId, true);

        // Cleanup favorites and tags
        $this->db->delete('favorites', ['item_type' => 'list', 'item_id' => $listId]);
        $this->db->delete('taggables', ['taggable_type' => 'list', 'taggable_id' => $listId]);

        $this->db->delete('lists', ['id' => $listId]);

        // Trigger webhook
        $this->webhookService->trigger($userId, 'list.deleted', [
            'id' => $listId,
            'title' => $list['title'],
            'message' => 'Liste gelöscht: ' . $list['title'],
        ]);

        return JsonResponse::success(null, 'List deleted successfully');
    }

    public function addItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $route = RouteContext::fromRequest($request)->getRoute();
        $userId = $request->getAttribute('user_id');
        $listId = $route->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getListForUser($listId, $userId, true);

        if (empty($data['content'])) {
            throw new ValidationException('Content is required');
        }

        // Get max position
        $maxPosition = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), -1) FROM list_items WHERE list_id = ?',
            [$listId]
        );

        $itemId = Uuid::uuid4()->toString();

        $this->db->insert('list_items', [
            'id' => $itemId,
            'list_id' => $listId,
            'content' => $data['content'],
            'position' => $maxPosition + 1,
            'due_date' => $data['due_date'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Update list timestamp
        $this->db->update('lists', ['updated_at' => date('Y-m-d H:i:s')], ['id' => $listId]);

        $item = $this->db->fetchAssociative('SELECT * FROM list_items WHERE id = ?', [$itemId]);

        return JsonResponse::created($item, 'Item added successfully');
    }

    public function updateItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $route = RouteContext::fromRequest($request)->getRoute();
        $userId = $request->getAttribute('user_id');
        $listId = $route->getArgument('id');
        $itemId = $route->getArgument('itemId');
        $data = $request->getParsedBody() ?? [];

        $this->getListForUser($listId, $userId, true);

        $item = $this->db->fetchAssociative(
            'SELECT * FROM list_items WHERE id = ? AND list_id = ?',
            [$itemId, $listId]
        );

        if (!$item) {
            throw new NotFoundException('Item not found');
        }

        $updateData = [];
        $allowedFields = ['content', 'is_completed', 'due_date', 'priority', 'metadata'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $field === 'metadata' ? json_encode($data[$field]) : $data[$field];
            }
        }

        // Handle completion
        if (isset($data['is_completed'])) {
            $updateData['completed_at'] = $data['is_completed'] ? date('Y-m-d H:i:s') : null;
        }

        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('list_items', $updateData, ['id' => $itemId]);
        }

        // Update list timestamp
        $this->db->update('lists', ['updated_at' => date('Y-m-d H:i:s')], ['id' => $listId]);

        $updated = $this->db->fetchAssociative('SELECT * FROM list_items WHERE id = ?', [$itemId]);

        return JsonResponse::success($updated, 'Item updated successfully');
    }

    public function deleteItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $route = RouteContext::fromRequest($request)->getRoute();
        $userId = $request->getAttribute('user_id');
        $listId = $route->getArgument('id');
        $itemId = $route->getArgument('itemId');

        $this->getListForUser($listId, $userId, true);

        $deleted = $this->db->delete('list_items', ['id' => $itemId, 'list_id' => $listId]);

        if (!$deleted) {
            throw new NotFoundException('Item not found');
        }

        // Update list timestamp
        $this->db->update('lists', ['updated_at' => date('Y-m-d H:i:s')], ['id' => $listId]);

        return JsonResponse::success(null, 'Item deleted successfully');
    }

    public function reorderItems(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $listId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getListForUser($listId, $userId, true);

        if (!isset($data['items']) || !is_array($data['items'])) {
            throw new ValidationException('Items array is required');
        }

        foreach ($data['items'] as $position => $itemId) {
            $this->db->update(
                'list_items',
                ['position' => $position, 'updated_at' => date('Y-m-d H:i:s')],
                ['id' => $itemId, 'list_id' => $listId]
            );
        }

        return JsonResponse::success(null, 'Items reordered successfully');
    }

    // Sharing functionality
    public function getShares(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $listId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Only owner can view shares
        $list = $this->db->fetchAssociative('SELECT * FROM lists WHERE id = ?', [$listId]);
        if (!$list || $list['user_id'] !== $userId) {
            throw new ForbiddenException('Only the owner can manage shares');
        }

        $shares = $this->db->fetchAllAssociative(
            'SELECT ls.*, u.username, u.email
             FROM list_shares ls
             JOIN users u ON ls.user_id = u.id
             WHERE ls.list_id = ?',
            [$listId]
        );

        return JsonResponse::success($shares);
    }

    public function addShare(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $listId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        // Only owner can add shares
        $list = $this->db->fetchAssociative('SELECT * FROM lists WHERE id = ?', [$listId]);
        if (!$list || $list['user_id'] !== $userId) {
            throw new ForbiddenException('Only the owner can manage shares');
        }

        if (empty($data['user_id']) && empty($data['email'])) {
            throw new ValidationException('User ID or email is required');
        }

        // Find user by email if not provided by ID
        $shareUserId = $data['user_id'] ?? null;
        if (!$shareUserId && !empty($data['email'])) {
            $user = $this->db->fetchAssociative('SELECT id FROM users WHERE email = ?', [$data['email']]);
            if (!$user) {
                throw new NotFoundException('User not found with this email');
            }
            $shareUserId = $user['id'];
        }

        // Can't share with yourself
        if ($shareUserId === $userId) {
            throw new ValidationException('Cannot share with yourself');
        }

        // Check if already shared
        $existing = $this->db->fetchAssociative(
            'SELECT * FROM list_shares WHERE list_id = ? AND user_id = ?',
            [$listId, $shareUserId]
        );

        $permission = $data['permission'] ?? 'view';
        if (!in_array($permission, ['view', 'edit'])) {
            $permission = 'view';
        }

        if ($existing) {
            $this->db->update('list_shares', ['permission' => $permission], [
                'list_id' => $listId,
                'user_id' => $shareUserId,
            ]);
        } else {
            $this->db->insert('list_shares', [
                'list_id' => $listId,
                'user_id' => $shareUserId,
                'permission' => $permission,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return JsonResponse::success(null, 'List shared successfully');
    }

    public function removeShare(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $listId = $route->getArgument('id');
        $shareUserId = $route->getArgument('userId');

        // Only owner can remove shares
        $list = $this->db->fetchAssociative('SELECT * FROM lists WHERE id = ?', [$listId]);
        if (!$list || $list['user_id'] !== $userId) {
            throw new ForbiddenException('Only the owner can manage shares');
        }

        $this->db->delete('list_shares', [
            'list_id' => $listId,
            'user_id' => $shareUserId,
        ]);

        return JsonResponse::success(null, 'Share removed successfully');
    }

    private function getListForUser(string $listId, string $userId, bool $requireOwner = false): array
    {
        $list = $this->db->fetchAssociative(
            'SELECT * FROM lists WHERE id = ?',
            [$listId]
        );

        if (!$list) {
            throw new NotFoundException('List not found');
        }

        // Check if user is restricted to projects only
        if ($this->projectAccess->isUserRestricted($userId)) {
            if (!$this->projectAccess->canAccessItem($userId, 'list', $listId)) {
                throw new ForbiddenException('Access denied - list not in your accessible projects');
            }
            return $list;
        }

        // Check ownership
        if ($list['user_id'] === $userId) {
            return $list;
        }

        // Check shared access
        if (!$requireOwner) {
            $share = $this->db->fetchAssociative(
                'SELECT * FROM list_shares WHERE list_id = ? AND user_id = ?',
                [$listId, $userId]
            );

            if ($share) {
                $list['shared_permission'] = $share['permission'];
                return $list;
            }
        }

        throw new ForbiddenException('Access denied');
    }
}
