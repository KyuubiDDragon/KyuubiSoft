<?php

declare(strict_types=1);

namespace App\Modules\Templates\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Templates\Services\TemplateService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TemplateController
{
    public function __construct(
        private TemplateService $templateService
    ) {}

    /**
     * Get all templates
     */
    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $filters = [];
        if (!empty($params['type'])) {
            $filters['type'] = $params['type'];
        }
        if (!empty($params['search'])) {
            $filters['search'] = $params['search'];
        }
        if (isset($params['is_public'])) {
            $filters['is_public'] = $params['is_public'] === 'true' || $params['is_public'] === '1';
        }
        if (!empty($params['category_id'])) {
            $filters['category_id'] = $params['category_id'];
        }

        $templates = $this->templateService->getAllTemplates($userId, $filters);

        return JsonResponse::success($templates, 'Success');
    }

    /**
     * Get valid template types
     */
    public function getTypes(Request $request, Response $response): Response
    {
        $types = $this->templateService->getValidTypes();

        return JsonResponse::success($types, 'Success');
    }

    /**
     * Get a single template
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $templateId = $args['id'];

        $template = $this->templateService->getTemplate($userId, $templateId);

        if (!$template) {
            return JsonResponse::notFound('Template not found');
        }

        return JsonResponse::success($template, 'Success');
    }

    /**
     * Create a new template
     */
    public function create(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        if (empty($body['name']) || empty($body['type'])) {
            return JsonResponse::error('Name and type are required', 400);
        }

        try {
            $template = $this->templateService->createTemplate($userId, $body);

            return JsonResponse::created($template, 'Created');
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Update a template
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $templateId = $args['id'];
        $body = $request->getParsedBody();

        $template = $this->templateService->updateTemplate($userId, $templateId, $body);

        if (!$template) {
            return JsonResponse::notFound('Template not found or not owned by user');
        }

        return JsonResponse::success($template, 'Success');
    }

    /**
     * Delete a template
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $templateId = $args['id'];

        $deleted = $this->templateService->deleteTemplate($userId, $templateId);

        if (!$deleted) {
            return JsonResponse::notFound('Template not found or not owned by user');
        }

        return JsonResponse::success(null, 'Success');
    }

    /**
     * Use a template
     */
    public function useTemplate(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $templateId = $args['id'];

        try {
            $result = $this->templateService->useTemplate($userId, $templateId);

            return JsonResponse::success($result, 'Success');
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::notFound($e->getMessage());
        }
    }

    /**
     * Create template from existing item
     */
    public function createFromItem(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        if (empty($body['type']) || empty($body['item_data']) || empty($body['template_name'])) {
            return JsonResponse::error('type, item_data, and template_name are required', 400);
        }

        try {
            $template = $this->templateService->createFromItem(
                $userId,
                $body['type'],
                $body['item_data'],
                [
                    'name' => $body['template_name'],
                    'description' => $body['template_description'] ?? null,
                    'icon' => $body['icon'] ?? null,
                    'color' => $body['color'] ?? null,
                    'is_public' => $body['is_public'] ?? false,
                    'category_ids' => $body['category_ids'] ?? [],
                ]
            );

            return JsonResponse::created($template, 'Created');
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Get template categories
     */
    public function getCategories(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $categories = $this->templateService->getCategories($userId);

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
            $category = $this->templateService->createCategory($userId, $body);

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

        $category = $this->templateService->updateCategory($userId, $categoryId, $body);

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

        $deleted = $this->templateService->deleteCategory($userId, $categoryId);

        if (!$deleted) {
            return JsonResponse::notFound('Category not found');
        }

        return JsonResponse::success(null, 'Success');
    }
}
