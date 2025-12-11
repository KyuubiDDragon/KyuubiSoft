<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Controllers;

use App\Modules\Wiki\Services\WikiService;
use App\Core\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class WikiController
{
    public function __construct(
        private readonly WikiService $wikiService
    ) {}

    // Pages

    /**
     * Get all wiki pages
     */
    public function getPages(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $filters = [];
        if (isset($params['category_id'])) {
            $filters['category_id'] = $params['category_id'];
        }
        if (isset($params['parent_id'])) {
            $filters['parent_id'] = $params['parent_id'];
        }
        if (isset($params['root_only'])) {
            $filters['root_only'] = $params['root_only'] === 'true';
        }
        if (isset($params['search'])) {
            $filters['search'] = $params['search'];
        }
        if (isset($params['is_published'])) {
            $filters['is_published'] = $params['is_published'] === 'true';
        }

        $pages = $this->wikiService->getPages($userId, $filters);

        return JsonResponse::success($pages);
    }

    /**
     * Get a single page
     */
    public function getPage(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $identifier = $args['id'];

        $page = $this->wikiService->getPage($userId, $identifier);

        if (!$page) {
            return JsonResponse::notFound('Page not found');
        }

        return JsonResponse::success($page);
    }

    /**
     * Create a new page
     */
    public function createPage(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['title'])) {
            return JsonResponse::error('Title is required', 400);
        }

        $page = $this->wikiService->createPage($userId, $data);

        return JsonResponse::created($page);
    }

    /**
     * Update a page
     */
    public function updatePage(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $pageId = $args['id'];
        $data = $request->getParsedBody();

        $page = $this->wikiService->updatePage($userId, $pageId, $data);

        if (!$page) {
            return JsonResponse::notFound('Page not found');
        }

        return JsonResponse::success($page);
    }

    /**
     * Delete a page
     */
    public function deletePage(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $pageId = $args['id'];

        $deleted = $this->wikiService->deletePage($userId, $pageId);

        if (!$deleted) {
            return JsonResponse::notFound('Page not found');
        }

        return JsonResponse::success(null, 'Page deleted');
    }

    /**
     * Get page history
     */
    public function getPageHistory(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $pageId = $args['id'];

        $history = $this->wikiService->getPageHistory($userId, $pageId);

        return JsonResponse::success($history);
    }

    /**
     * Restore page from history
     */
    public function restoreFromHistory(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $pageId = $args['id'];
        $historyId = $args['historyId'];

        $page = $this->wikiService->restoreFromHistory($userId, $pageId, $historyId);

        if (!$page) {
            return JsonResponse::notFound('History entry not found');
        }

        return JsonResponse::success($page);
    }

    // Categories

    /**
     * Get all categories
     */
    public function getCategories(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $categories = $this->wikiService->getCategories($userId);

        return JsonResponse::success($categories);
    }

    /**
     * Create a category
     */
    public function createCategory(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            return JsonResponse::error('Name is required', 400);
        }

        $category = $this->wikiService->createCategory($userId, $data);

        return JsonResponse::created($category);
    }

    /**
     * Update a category
     */
    public function updateCategory(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $categoryId = $args['id'];
        $data = $request->getParsedBody();

        $category = $this->wikiService->updateCategory($userId, $categoryId, $data);

        if (!$category) {
            return JsonResponse::notFound('Category not found');
        }

        return JsonResponse::success($category);
    }

    /**
     * Delete a category
     */
    public function deleteCategory(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $categoryId = $args['id'];

        $deleted = $this->wikiService->deleteCategory($userId, $categoryId);

        if (!$deleted) {
            return JsonResponse::notFound('Category not found');
        }

        return JsonResponse::success(null, 'Category deleted');
    }

    // Graph and Search

    /**
     * Get graph data
     */
    public function getGraph(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $graphData = $this->wikiService->getGraphData($userId);

        return JsonResponse::success($graphData);
    }

    /**
     * Search wiki pages
     */
    public function search(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        if (empty($params['q'])) {
            return JsonResponse::error('Search query is required', 400);
        }

        $results = $this->wikiService->search($userId, $params['q']);

        return JsonResponse::success($results);
    }

    /**
     * Get recent pages
     */
    public function getRecent(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $limit = min((int) ($params['limit'] ?? 10), 50);

        $pages = $this->wikiService->getRecentPages($userId, $limit);

        return JsonResponse::success($pages);
    }
}
