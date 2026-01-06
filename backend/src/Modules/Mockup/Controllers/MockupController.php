<?php

declare(strict_types=1);

namespace App\Modules\Mockup\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Mockup\Services\MockupService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

class MockupController
{
    public function __construct(
        private readonly MockupService $mockupService
    ) {}

    /**
     * Extract route argument from request
     */
    private function getRouteArg(ServerRequestInterface $request, string $name): ?string
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        return $route ? $route->getArgument($name) : null;
    }

    // ==================== Templates ====================

    /**
     * List all custom templates
     */
    public function indexTemplates(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $result = $this->mockupService->getTemplates($userId, $params);

        return JsonResponse::success([
            'items' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * Get a single template
     */
    public function showTemplate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $template = $this->mockupService->getTemplate($id, $userId);

        if (!$template) {
            return JsonResponse::error('Template not found', 404);
        }

        return JsonResponse::success($template);
    }

    /**
     * Create a new template
     */
    public function createTemplate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name'])) {
            return JsonResponse::error('Name is required', 400);
        }

        if (empty($data['elements']) || !is_array($data['elements'])) {
            return JsonResponse::error('Elements are required', 400);
        }

        try {
            $template = $this->mockupService->createTemplate($userId, $data);
            return JsonResponse::created($template);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to create template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update a template
     */
    public function updateTemplate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody() ?? [];

        $success = $this->mockupService->updateTemplate($id, $userId, $data);

        if (!$success) {
            return JsonResponse::error('Template not found or no changes made', 404);
        }

        $template = $this->mockupService->getTemplate($id, $userId);
        return JsonResponse::success($template);
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $success = $this->mockupService->deleteTemplate($id, $userId);

        if (!$success) {
            return JsonResponse::error('Template not found', 404);
        }

        return JsonResponse::success(['message' => 'Template deleted']);
    }

    // ==================== Drafts ====================

    /**
     * List all drafts
     */
    public function indexDrafts(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $result = $this->mockupService->getDrafts($userId, $params);

        return JsonResponse::success([
            'items' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * Get a single draft
     */
    public function showDraft(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $draft = $this->mockupService->getDraft($id, $userId);

        if (!$draft) {
            return JsonResponse::error('Draft not found', 404);
        }

        return JsonResponse::success($draft);
    }

    /**
     * Save a draft (create or update)
     */
    public function saveDraft(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['elements']) || !is_array($data['elements'])) {
            return JsonResponse::error('Elements are required', 400);
        }

        try {
            $draft = $this->mockupService->saveDraft($userId, $data);
            return JsonResponse::success($draft);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to save draft: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a draft
     */
    public function deleteDraft(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $success = $this->mockupService->deleteDraft($id, $userId);

        if (!$success) {
            return JsonResponse::error('Draft not found', 404);
        }

        return JsonResponse::success(['message' => 'Draft deleted']);
    }
}
