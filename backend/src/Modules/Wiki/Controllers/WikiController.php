<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Controllers;

use App\Modules\Wiki\Services\WikiService;
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

        $response->getBody()->write(json_encode($pages));
        return $response->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'error' => 'Page not found'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($page));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create a new page
     */
    public function createPage(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['title'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Title is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $page = $this->wikiService->createPage($userId, $data);

        $response->getBody()->write(json_encode($page));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'error' => 'Page not found'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($page));
        return $response->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'error' => 'Page not found'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get page history
     */
    public function getPageHistory(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $pageId = $args['id'];

        $history = $this->wikiService->getPageHistory($userId, $pageId);

        $response->getBody()->write(json_encode($history));
        return $response->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'error' => 'History entry not found'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($page));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Categories

    /**
     * Get all categories
     */
    public function getCategories(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $categories = $this->wikiService->getCategories($userId);

        $response->getBody()->write(json_encode($categories));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create a category
     */
    public function createCategory(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Name is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $category = $this->wikiService->createCategory($userId, $data);

        $response->getBody()->write(json_encode($category));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'error' => 'Category not found'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($category));
        return $response->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'error' => 'Category not found'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Graph and Search

    /**
     * Get graph data
     */
    public function getGraph(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $graphData = $this->wikiService->getGraphData($userId);

        $response->getBody()->write(json_encode($graphData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Search wiki pages
     */
    public function search(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        if (empty($params['q'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Search query is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $results = $this->wikiService->search($userId, $params['q']);

        $response->getBody()->write(json_encode($results));
        return $response->withHeader('Content-Type', 'application/json');
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

        $response->getBody()->write(json_encode($pages));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
