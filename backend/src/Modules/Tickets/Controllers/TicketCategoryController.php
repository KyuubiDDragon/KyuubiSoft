<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Controllers;

use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Modules\Tickets\Repositories\TicketRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

class TicketCategoryController
{
    public function __construct(
        private readonly TicketRepository $repository
    ) {}

    private function getRouteArg(ServerRequestInterface $request, string $name): ?string
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        return $route ? $route->getArgument($name) : null;
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $activeOnly = !isset($params['all']) || $params['all'] !== 'true';
        $nested = isset($params['nested']) && $params['nested'] === 'true';

        $categories = $this->repository->getCategories($activeOnly, $nested);

        return JsonResponse::success(['categories' => $categories]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $id = $this->getRouteArg($request, 'id');

        $category = $this->repository->findCategoryById($id);

        if (!$category) {
            return JsonResponse::error('Kategorie nicht gefunden', 404);
        }

        return JsonResponse::success(['category' => $category]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            throw new ValidationException('Name ist erforderlich');
        }

        $id = $this->generateUuid();

        $categoryData = [
            'id' => $id,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#6366f1',
            'icon' => $data['icon'] ?? 'ticket',
            'sla_response_hours' => isset($data['sla_response_hours']) ? (int) $data['sla_response_hours'] : null,
            'sla_resolution_hours' => isset($data['sla_resolution_hours']) ? (int) $data['sla_resolution_hours'] : null,
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Validate parent exists if specified
        if ($categoryData['parent_id']) {
            $parent = $this->repository->findCategoryById($categoryData['parent_id']);
            if (!$parent) {
                throw new ValidationException('Übergeordnete Kategorie nicht gefunden');
            }
        }

        $category = $this->repository->createCategory($categoryData);

        return JsonResponse::created(['category' => $category], 'Kategorie erstellt');
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $id = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody();

        $category = $this->repository->findCategoryById($id);

        if (!$category) {
            return JsonResponse::error('Kategorie nicht gefunden', 404);
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }

        if (array_key_exists('parent_id', $data)) {
            // Prevent self-reference
            if ($data['parent_id'] === $id) {
                throw new ValidationException('Kategorie kann nicht ihr eigenes Elternelement sein');
            }

            // Validate parent exists if specified
            if ($data['parent_id']) {
                $parent = $this->repository->findCategoryById($data['parent_id']);
                if (!$parent) {
                    throw new ValidationException('Übergeordnete Kategorie nicht gefunden');
                }
            }

            $updateData['parent_id'] = $data['parent_id'] ?: null;
        }

        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['color'])) {
            $updateData['color'] = $data['color'];
        }

        if (isset($data['icon'])) {
            $updateData['icon'] = $data['icon'];
        }

        if (isset($data['sla_response_hours'])) {
            $updateData['sla_response_hours'] = $data['sla_response_hours'] ? (int) $data['sla_response_hours'] : null;
        }

        if (isset($data['sla_resolution_hours'])) {
            $updateData['sla_resolution_hours'] = $data['sla_resolution_hours'] ? (int) $data['sla_resolution_hours'] : null;
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = (int) $data['is_active'];
        }

        if (isset($data['sort_order'])) {
            $updateData['sort_order'] = (int) $data['sort_order'];
        }

        if (!empty($updateData)) {
            $this->repository->updateCategory($id, $updateData);
        }

        $updatedCategory = $this->repository->findCategoryById($id);

        return JsonResponse::success(['category' => $updatedCategory]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $id = $this->getRouteArg($request, 'id');

        $category = $this->repository->findCategoryById($id);

        if (!$category) {
            return JsonResponse::error('Kategorie nicht gefunden', 404);
        }

        $this->repository->deleteCategory($id);

        return JsonResponse::success(null, 'Kategorie gelöscht');
    }

    public function reorder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();

        if (empty($data['order']) || !is_array($data['order'])) {
            throw new ValidationException('Reihenfolge ist erforderlich');
        }

        foreach ($data['order'] as $index => $categoryId) {
            $this->repository->updateCategory($categoryId, ['sort_order' => $index]);
        }

        return JsonResponse::success(null, 'Reihenfolge aktualisiert');
    }
}
