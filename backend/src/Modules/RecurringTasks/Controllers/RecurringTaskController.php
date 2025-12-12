<?php

declare(strict_types=1);

namespace App\Modules\RecurringTasks\Controllers;

use App\Modules\RecurringTasks\Services\RecurringTaskService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class RecurringTaskController
{
    public function __construct(
        private RecurringTaskService $recurringTaskService
    ) {}

    /**
     * Get all recurring tasks
     */
    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $filters = [];
        if (isset($params['is_active'])) {
            $filters['is_active'] = $params['is_active'] === 'true' || $params['is_active'] === '1';
        }
        if (!empty($params['category_id'])) {
            $filters['category_id'] = $params['category_id'];
        }
        if (!empty($params['search'])) {
            $filters['search'] = $params['search'];
        }

        $tasks = $this->recurringTaskService->getAllTasks($userId, $filters);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $tasks,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get upcoming recurring tasks
     */
    public function upcoming(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();
        $days = isset($params['days']) ? (int) $params['days'] : 7;

        $upcoming = $this->recurringTaskService->getUpcomingTasks($userId, $days);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $upcoming,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Process all due recurring tasks
     */
    public function processDue(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $results = $this->recurringTaskService->processDueTasks($userId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $results,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get a single recurring task
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $taskId = $args['id'];

        $task = $this->recurringTaskService->getTask($userId, $taskId);

        if (!$task) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Task not found',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $task,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create a new recurring task
     */
    public function create(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        if (empty($body['title']) || empty($body['start_date'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Title and start_date are required',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $task = $this->recurringTaskService->createTask($userId, $body);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $task,
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    /**
     * Update a recurring task
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $taskId = $args['id'];
        $body = $request->getParsedBody();

        $task = $this->recurringTaskService->updateTask($userId, $taskId, $body);

        if (!$task) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Task not found',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $task,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Delete a recurring task
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $taskId = $args['id'];

        $deleted = $this->recurringTaskService->deleteTask($userId, $taskId);

        if (!$deleted) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Task not found',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Toggle task active status
     */
    public function toggleActive(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $taskId = $args['id'];

        $task = $this->recurringTaskService->toggleActive($userId, $taskId);

        if (!$task) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Task not found',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $task,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Skip an upcoming occurrence
     */
    public function skipOccurrence(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $taskId = $args['id'];

        $task = $this->recurringTaskService->skipOccurrence($userId, $taskId);

        if (!$task) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Task not found',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $task,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get task instances history
     */
    public function getInstances(Request $request, Response $response, array $args): Response
    {
        $taskId = $args['id'];
        $params = $request->getQueryParams();
        $limit = isset($params['limit']) ? (int) $params['limit'] : 20;

        $instances = $this->recurringTaskService->getTaskInstances($taskId, $limit);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $instances,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Manually trigger task processing
     */
    public function processTask(Request $request, Response $response, array $args): Response
    {
        $taskId = $args['id'];

        $result = $this->recurringTaskService->processTask($taskId);

        $response->getBody()->write(json_encode([
            'success' => $result['success'],
            'data' => $result,
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($result['success'] ? 200 : 400);
    }

    // Categories

    /**
     * Get categories
     */
    public function getCategories(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $categories = $this->recurringTaskService->getCategories($userId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $categories,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create a category
     */
    public function createCategory(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        if (empty($body['name'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Name is required',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $category = $this->recurringTaskService->createCategory($userId, $body);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $category,
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Category name already exists',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }
    }

    /**
     * Update a category
     */
    public function updateCategory(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $categoryId = $args['id'];
        $body = $request->getParsedBody();

        $category = $this->recurringTaskService->updateCategory($userId, $categoryId, $body);

        if (!$category) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Category not found',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $category,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Delete a category
     */
    public function deleteCategory(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $categoryId = $args['id'];

        $deleted = $this->recurringTaskService->deleteCategory($userId, $categoryId);

        if (!$deleted) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Category not found',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
