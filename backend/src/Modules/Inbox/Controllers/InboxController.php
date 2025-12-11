<?php

declare(strict_types=1);

namespace App\Modules\Inbox\Controllers;

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
            $response->getBody()->write(json_encode([
                'error' => 'Content is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $item = $this->inboxService->capture($userId, $data);

        $response->getBody()->write(json_encode($item));
        return $response->withHeader('Content-Type', 'application/json');
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

        $response->getBody()->write(json_encode($items));
        return $response->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'error' => 'Item not found'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($item));
        return $response->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'error' => 'Item not found'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($item));
        return $response->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'error' => 'Target type is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $result = $this->inboxService->moveToModule(
                $userId,
                $itemId,
                $data['target_type'],
                $data['target_id'] ?? null,
                $data['options'] ?? []
            );

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'error' => 'Item not found'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get inbox statistics
     */
    public function getStats(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $stats = $this->inboxService->getStats($userId);

        $response->getBody()->write(json_encode($stats));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Bulk actions on inbox items
     */
    public function bulkAction(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['ids']) || !is_array($data['ids'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Item IDs are required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (empty($data['action'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Action is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
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

        $response->getBody()->write(json_encode([
            'results' => $results,
            'errors' => $errors
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
