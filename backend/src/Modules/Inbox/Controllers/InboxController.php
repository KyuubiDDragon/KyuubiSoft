<?php

declare(strict_types=1);

namespace App\Modules\Inbox\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Inbox\Services\InboxService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class InboxController
{
    public function __construct(
        private readonly InboxService $inboxService
    ) {}

    /**
     * Quick capture - create new inbox item
     */
    public function capture(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['content'])) {
            return JsonResponse::error('Content is required', 400);
        }

        $item = $this->inboxService->capture($userId, $data);

        return JsonResponse::created($item, 'Captured');
    }

    /**
     * Get all inbox items
     */
    public function getItems(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $filters = [];
        if (isset($params['status'])) {
            $filters['status'] = $params['status'];
        }
        if (isset($params['priority'])) {
            $filters['priority'] = $params['priority'];
        }
        if (isset($params['search'])) {
            $filters['search'] = $params['search'];
        }
        if (isset($params['sort'])) {
            $filters['sort'] = $params['sort'];
        }

        $items = $this->inboxService->getItems($userId, $filters);

        return JsonResponse::success($items, 'Success');
    }

    /**
     * Get single inbox item
     */
    public function getItem(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $itemId = $args['id'];

        $item = $this->inboxService->getItem($userId, $itemId);

        if (!$item) {
            return JsonResponse::notFound('Item not found');
        }

        return JsonResponse::success($item, 'Success');
    }

    /**
     * Update inbox item
     */
    public function updateItem(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $itemId = $args['id'];
        $data = $request->getParsedBody();

        $item = $this->inboxService->updateItem($userId, $itemId, $data);

        if (!$item) {
            return JsonResponse::notFound('Item not found');
        }

        return JsonResponse::success($item, 'Success');
    }

    /**
     * Move inbox item to another module
     */
    public function moveToModule(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $itemId = $args['id'];
        $data = $request->getParsedBody();

        if (empty($data['target_type'])) {
            return JsonResponse::error('Target type is required', 400);
        }

        try {
            $result = $this->inboxService->moveToModule(
                $userId,
                $itemId,
                $data['target_type'],
                $data['target_id'] ?? null,
                $data['options'] ?? []
            );

            return JsonResponse::success($result, 'Success');
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Delete inbox item
     */
    public function deleteItem(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $itemId = $args['id'];

        $deleted = $this->inboxService->deleteItem($userId, $itemId);

        if (!$deleted) {
            return JsonResponse::notFound('Item not found');
        }

        return JsonResponse::success(null, 'Deleted');
    }

    /**
     * Get inbox statistics
     */
    public function getStats(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $stats = $this->inboxService->getStats($userId);

        return JsonResponse::success($stats, 'Success');
    }

    /**
     * Bulk actions on inbox items
     */
    public function bulkAction(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['ids']) || !is_array($data['ids'])) {
            return JsonResponse::error('Item IDs are required', 400);
        }

        if (empty($data['action'])) {
            return JsonResponse::error('Action is required', 400);
        }

        $results = [];
        $errors = [];

        foreach ($data['ids'] as $itemId) {
            try {
                switch ($data['action']) {
                    case 'delete':
                        $this->inboxService->deleteItem($userId, $itemId);
                        $results[] = ['id' => $itemId, 'success' => true];
                        break;

                    case 'archive':
                        $this->inboxService->updateItem($userId, $itemId, ['status' => 'archived']);
                        $results[] = ['id' => $itemId, 'success' => true];
                        break;

                    case 'move':
                        if (empty($data['target_type'])) {
                            throw new \InvalidArgumentException('Target type required for move');
                        }
                        $this->inboxService->moveToModule(
                            $userId,
                            $itemId,
                            $data['target_type'],
                            $data['target_id'] ?? null,
                            $data['options'] ?? []
                        );
                        $results[] = ['id' => $itemId, 'success' => true];
                        break;

                    case 'set_priority':
                        if (empty($data['priority'])) {
                            throw new \InvalidArgumentException('Priority required');
                        }
                        $this->inboxService->updateItem($userId, $itemId, ['priority' => $data['priority']]);
                        $results[] = ['id' => $itemId, 'success' => true];
                        break;

                    default:
                        throw new \InvalidArgumentException('Unknown action: ' . $data['action']);
                }
            } catch (\Exception $e) {
                $errors[] = ['id' => $itemId, 'error' => $e->getMessage()];
            }
        }

        return JsonResponse::success([
            'results' => $results,
            'errors' => $errors,
        ], 'Success');
    }
}
