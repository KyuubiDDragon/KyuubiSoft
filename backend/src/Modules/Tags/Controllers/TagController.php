<?php

declare(strict_types=1);

namespace App\Modules\Tags\Controllers;

use App\Core\Http\JsonResponse;
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

        return JsonResponse::success($tags, 'Success');
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
            return JsonResponse::notFound('Tag not found');
        }

        return JsonResponse::success($tag, 'Success');
    }

    /**
     * Create a new tag
     */
    public function create(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        if (empty($body['name'])) {
            return JsonResponse::error('Name is required', 400);
        }

        try {
            $tag = $this->tagService->createTag($userId, $body);

            return JsonResponse::created($tag, 'Created');
        } catch (\Exception $e) {
            return JsonResponse::error('Tag name already exists', 409);
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
            return JsonResponse::notFound('Tag not found');
        }

        return JsonResponse::success($tag, 'Success');
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
            return JsonResponse::notFound('Tag not found');
        }

        return JsonResponse::success(null, 'Success');
    }

    /**
     * Get valid taggable types
     */
    public function getTypes(Request $request, Response $response): Response
    {
        $types = $this->tagService->getValidTypes();

        return JsonResponse::success($types, 'Success');
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
            return JsonResponse::error('taggable_type and taggable_id are required', 400);
        }

        $result = $this->tagService->tagItem(
            $userId,
            $tagId,
            $body['taggable_type'],
            $body['taggable_id']
        );

        if (!$result) {
            return JsonResponse::error('Invalid tag or item type', 400);
        }

        return JsonResponse::success(null, 'Success');
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

        return JsonResponse::success(null, 'Success');
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

        return JsonResponse::success($tags, 'Success');
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
            return JsonResponse::error('tag_ids array is required', 400);
        }

        $tags = $this->tagService->setItemTags($userId, $taggableType, $taggableId, $body['tag_ids']);

        return JsonResponse::success($tags, 'Success');
    }

    /**
     * Search items by tags
     */
    public function searchByTags(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        if (empty($params['tags'])) {
            return JsonResponse::error('tags parameter is required', 400);
        }

        $tagIds = explode(',', $params['tags']);
        $type = $params['type'] ?? null;

        $results = $this->tagService->searchByTags($userId, $tagIds, $type);

        return JsonResponse::success($results, 'Success');
    }

    /**
     * Merge tags
     */
    public function mergeTags(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        if (empty($body['source_ids']) || !is_array($body['source_ids']) || empty($body['target_id'])) {
            return JsonResponse::error('source_ids array and target_id are required', 400);
        }

        try {
            $result = $this->tagService->mergeTags($userId, $body['source_ids'], $body['target_id']);

            if (!$result) {
                return JsonResponse::error('Invalid tags', 400);
            }

            $mergedTag = $this->tagService->getTag($userId, $body['target_id']);

            return JsonResponse::success($mergedTag, 'Success');
        } catch (\Exception $e) {
            return JsonResponse::serverError('Merge failed');
        }
    }
}
