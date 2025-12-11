<?php

declare(strict_types=1);

namespace App\Modules\Tags\Controllers;

use App\Modules\Tags\Services\TagService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TagController
{
    public function __construct(
        private TagService $tagService
    ) {}

    /**
     * Get all tags
     */
    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $filters = [];
        if (!empty($params['search'])) {
            $filters['search'] = $params['search'];
        }

        $tags = $this->tagService->getAllTags($userId, $filters);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $tags,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get a single tag
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $tagId = $args['id'];

        $tag = $this->tagService->getTag($userId, $tagId);

        if (!$tag) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Tag not found',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $tag,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create a new tag
     */
    public function create(Request $request, Response $response): Response
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
            $tag = $this->tagService->createTag($userId, $body);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $tag,
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Tag name already exists',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }
    }

    /**
     * Update a tag
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $tagId = $args['id'];
        $body = $request->getParsedBody();

        $tag = $this->tagService->updateTag($userId, $tagId, $body);

        if (!$tag) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Tag not found',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $tag,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Delete a tag
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $tagId = $args['id'];

        $deleted = $this->tagService->deleteTag($userId, $tagId);

        if (!$deleted) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Tag not found',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get valid taggable types
     */
    public function getTypes(Request $request, Response $response): Response
    {
        $types = $this->tagService->getValidTypes();

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $types,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Tag an item
     */
    public function tagItem(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $tagId = $args['id'];
        $body = $request->getParsedBody();

        if (empty($body['taggable_type']) || empty($body['taggable_id'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'taggable_type and taggable_id are required',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $result = $this->tagService->tagItem(
            $userId,
            $tagId,
            $body['taggable_type'],
            $body['taggable_id']
        );

        if (!$result) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Invalid tag or item type',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Remove tag from an item
     */
    public function untagItem(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $tagId = $args['id'];
        $taggableType = $args['type'];
        $taggableId = $args['itemId'];

        $this->tagService->untagItem($userId, $tagId, $taggableType, $taggableId);

        $response->getBody()->write(json_encode([
            'success' => true,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get tags for an item
     */
    public function getItemTags(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $taggableType = $args['type'];
        $taggableId = $args['itemId'];

        $tags = $this->tagService->getItemTags($userId, $taggableType, $taggableId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $tags,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Set tags for an item
     */
    public function setItemTags(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $taggableType = $args['type'];
        $taggableId = $args['itemId'];
        $body = $request->getParsedBody();

        if (!isset($body['tag_ids']) || !is_array($body['tag_ids'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'tag_ids array is required',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $tags = $this->tagService->setItemTags($userId, $taggableType, $taggableId, $body['tag_ids']);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $tags,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Search items by tags
     */
    public function searchByTags(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        if (empty($params['tags'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'tags parameter is required',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $tagIds = explode(',', $params['tags']);
        $type = $params['type'] ?? null;

        $results = $this->tagService->searchByTags($userId, $tagIds, $type);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $results,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Merge tags
     */
    public function mergeTags(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        if (empty($body['source_ids']) || !is_array($body['source_ids']) || empty($body['target_id'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'source_ids array and target_id are required',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $result = $this->tagService->mergeTags($userId, $body['source_ids'], $body['target_id']);

            if (!$result) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Invalid tags',
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $mergedTag = $this->tagService->getTag($userId, $body['target_id']);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $mergedTag,
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Merge failed',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
