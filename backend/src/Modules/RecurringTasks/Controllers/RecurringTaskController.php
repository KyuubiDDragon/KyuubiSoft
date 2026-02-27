<?php

declare(strict_types=1);

namespace App\Modules\RecurringTasks\Controllers;

use App\Core\Http\JsonResponse;
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

        return JsonResponse::success($tasks, 'Success');
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

        return JsonResponse::success($upcoming, 'Success');
    }

    /**
     * Process all due recurring tasks
     */
    public function processDue(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $results = $this->recurringTaskService->processDueTasks($userId);

        return JsonResponse::success($results, 'Success');
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
            return JsonResponse::notFound('Task not found');
        }

        return JsonResponse::success($task, 'Success');
    }

    /**
     * Create a new recurring task
     */
    public function create(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        if (empty($body['title']) || empty($body['start_date'])) {
            return JsonResponse::error('Title and start_date are required', 400);
        }

        $task = $this->recurringTaskService->createTask($userId, $body);

        return JsonResponse::created($task, 'Created');
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
            return JsonResponse::notFound('Task not found');
        }

        return JsonResponse::success($task, 'Success');
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
            return JsonResponse::notFound('Task not found');
        }

        return JsonResponse::success(null, 'Success');
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
            return JsonResponse::notFound('Task not found');
        }

        return JsonResponse::success($task, 'Success');
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
            return JsonResponse::notFound('Task not found');
        }

        return JsonResponse::success($task, 'Success');
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

        return JsonResponse::success($instances, 'Success');
    }

    /**
     * Manually trigger task processing
     */
    public function processTask(Request $request, Response $response, array $args): Response
    {
        $taskId = $args['id'];

        $result = $this->recurringTaskService->processTask($taskId);

        if ($result['success']) {
            return JsonResponse::success($result, 'Success');
        }

        return JsonResponse::error('Task processing failed', 400);
    }

    // Categories

    /**
     * Get categories
     */
    public function getCategories(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $categories = $this->recurringTaskService->getCategories($userId);

        return JsonResponse::success($categories, 'Success');
    }

    /**
     * Create a category
     */
    public function createCategory(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        if (empty($body['name'])) {
            return JsonResponse::error('Name is required', 400);
        }

        try {
            $category = $this->recurringTaskService->createCategory($userId, $body);

            return JsonResponse::created($category, 'Created');
        } catch (\Exception $e) {
            return JsonResponse::error('Category name already exists', 409);
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
            return JsonResponse::notFound('Category not found');
        }

        return JsonResponse::success($category, 'Success');
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
            return JsonResponse::notFound('Category not found');
        }

        return JsonResponse::success(null, 'Success');
    }
}
