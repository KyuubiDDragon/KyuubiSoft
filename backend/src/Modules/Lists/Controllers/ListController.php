<?php

declare(strict_types=1);

namespace App\Modules\Lists\Controllers;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;

class ListController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $lists = $this->db->fetchAllAssociative(
            'SELECT l.*,
                    (SELECT COUNT(*) FROM list_items WHERE list_id = l.id) as item_count,
                    (SELECT COUNT(*) FROM list_items WHERE list_id = l.id AND is_completed = TRUE) as completed_count
             FROM lists l
             WHERE l.user_id = ? AND l.is_archived = FALSE
             ORDER BY l.updated_at DESC
             LIMIT ? OFFSET ?',
            [$userId, $perPage, $offset],
            [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]
        );

        $total = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM lists WHERE user_id = ? AND is_archived = FALSE',
            [$userId]
        );

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

        return JsonResponse::created($list, 'List created successfully');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $listId = $args['id'];

        $list = $this->getListForUser($listId, $userId);

        // Get items
        $list['items'] = $this->db->fetchAllAssociative(
            'SELECT * FROM list_items WHERE list_id = ? ORDER BY position, created_at',
            [$listId]
        );

        return JsonResponse::success($list);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $listId = $args['id'];
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

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $listId = $args['id'];

        $this->getListForUser($listId, $userId, true);

        $this->db->delete('lists', ['id' => $listId]);

        return JsonResponse::success(null, 'List deleted successfully');
    }

    public function addItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $listId = $args['id'];
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

    public function updateItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $listId = $args['id'];
        $itemId = $args['itemId'];
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

    public function deleteItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $listId = $args['id'];
        $itemId = $args['itemId'];

        $this->getListForUser($listId, $userId, true);

        $deleted = $this->db->delete('list_items', ['id' => $itemId, 'list_id' => $listId]);

        if (!$deleted) {
            throw new NotFoundException('Item not found');
        }

        // Update list timestamp
        $this->db->update('lists', ['updated_at' => date('Y-m-d H:i:s')], ['id' => $listId]);

        return JsonResponse::success(null, 'Item deleted successfully');
    }

    public function reorderItems(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $listId = $args['id'];
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

    private function getListForUser(string $listId, string $userId, bool $requireOwner = false): array
    {
        $list = $this->db->fetchAssociative(
            'SELECT * FROM lists WHERE id = ?',
            [$listId]
        );

        if (!$list) {
            throw new NotFoundException('List not found');
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
