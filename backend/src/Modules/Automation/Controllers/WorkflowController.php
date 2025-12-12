<?php

declare(strict_types=1);

namespace App\Modules\Automation\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Automation\Services\WorkflowService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class WorkflowController
{
    public function __construct(
        private readonly WorkflowService $workflowService
    ) {}

    /**
     * List all workflows
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $workflows = $this->workflowService->getWorkflows($userId);

        return JsonResponse::success($workflows);
    }

    /**
     * Get a single workflow
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $workflow = $this->workflowService->getWorkflow($args['id'], $userId);

        if (!$workflow) {
            return JsonResponse::error('Workflow not found', 404);
        }

        return JsonResponse::success($workflow);
    }

    /**
     * Create a new workflow
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name'])) {
            return JsonResponse::error('Name is required', 400);
        }

        if (empty($data['trigger_type'])) {
            return JsonResponse::error('Trigger type is required', 400);
        }

        try {
            $workflow = $this->workflowService->createWorkflow($userId, $data);
            return JsonResponse::created($workflow);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Update a workflow
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $success = $this->workflowService->updateWorkflow($args['id'], $userId, $data);

        if (!$success) {
            return JsonResponse::error('Workflow not found', 404);
        }

        $workflow = $this->workflowService->getWorkflow($args['id'], $userId);
        return JsonResponse::success($workflow);
    }

    /**
     * Delete a workflow
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $success = $this->workflowService->deleteWorkflow($args['id'], $userId);

        if (!$success) {
            return JsonResponse::error('Workflow not found', 404);
        }

        return JsonResponse::success(['message' => 'Workflow deleted']);
    }

    /**
     * Toggle workflow enabled status
     */
    public function toggle(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $newStatus = $this->workflowService->toggleWorkflow($args['id'], $userId);

        if ($newStatus === null) {
            return JsonResponse::error('Workflow not found', 404);
        }

        return JsonResponse::success([
            'is_enabled' => $newStatus,
            'message' => $newStatus ? 'Workflow enabled' : 'Workflow disabled',
        ]);
    }

    /**
     * Execute a workflow manually
     */
    public function execute(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        try {
            $result = $this->workflowService->executeWorkflow(
                $args['id'],
                $userId,
                array_merge(['user_id' => $userId], $data['trigger_data'] ?? [])
            );

            return JsonResponse::success($result);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            return JsonResponse::error('Execution failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get workflow run history
     */
    public function history(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));

        $runs = $this->workflowService->getRunHistory($args['id'], $userId, $limit);

        return JsonResponse::success($runs);
    }

    /**
     * Get run details
     */
    public function runDetails(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $run = $this->workflowService->getRunDetails($args['run_id'], $userId);

        if (!$run) {
            return JsonResponse::error('Run not found', 404);
        }

        return JsonResponse::success($run);
    }

    /**
     * Get workflow templates
     */
    public function templates(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $category = $params['category'] ?? null;

        $templates = $this->workflowService->getTemplates($category);

        return JsonResponse::success($templates);
    }

    /**
     * Create workflow from template
     */
    public function createFromTemplate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        try {
            $workflow = $this->workflowService->createFromTemplate($args['template_id'], $userId);
            return JsonResponse::created($workflow);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Get available triggers and actions
     */
    public function options(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $options = $this->workflowService->getAvailableOptions();

        return JsonResponse::success($options);
    }
}
