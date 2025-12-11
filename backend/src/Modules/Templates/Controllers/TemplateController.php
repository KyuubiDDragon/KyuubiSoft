<?php

declare(strict_types=1);

namespace App\Modules\Templates\Controllers;

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

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $templates,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get valid template types
     */
    public function getTypes(Request $request, Response $response): Response
    {
        $types = $this->templateService->getValidTypes();

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $types,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Template not found',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $template,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create a new template
     */
    public function create(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        if (empty($body['name']) || empty($body['type'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Name and type are required',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $template = $this->templateService->createTemplate($userId, $body);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $template,
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Template not found or not owned by user',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $template,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Template not found or not owned by user',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
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

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $result,
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
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
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'type, item_data, and template_name are required',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $template,
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    /**
     * Get template categories
     */
    public function getCategories(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $categories = $this->templateService->getCategories($userId);

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
            $category = $this->templateService->createCategory($userId, $body);

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

        $category = $this->templateService->updateCategory($userId, $categoryId, $body);

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

        $deleted = $this->templateService->deleteCategory($userId, $categoryId);

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
