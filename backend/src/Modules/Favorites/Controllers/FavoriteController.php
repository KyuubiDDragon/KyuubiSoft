<?php

declare(strict_types=1);

namespace App\Modules\Favorites\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Exceptions\ValidationException;
use App\Core\Exceptions\NotFoundException;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class FavoriteController
{
    private const ALLOWED_TYPES = [
        'list',
        'document',
        'kanban_board',
        'project',
        'checklist',
        'snippet',
        'bookmark_group',
        'connection',
    ];

    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Get all favorites for the current user
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $favorites = $this->db->fetchAllAssociative(
            'SELECT * FROM favorites WHERE user_id = ? ORDER BY position ASC, created_at DESC',
            [$userId]
        );

        // Load item details for each favorite
        foreach ($favorites as &$fav) {
            $fav['item'] = $this->getItemDetails($fav['item_type'], $fav['item_id']);
        }

        // Filter out favorites where item no longer exists
        $favorites = array_filter($favorites, fn($f) => $f['item'] !== null);
        $favorites = array_values($favorites);

        return JsonResponse::success(['items' => $favorites]);
    }

    /**
     * Add a favorite
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['item_type'])) {
            throw new ValidationException('item_type is required');
        }

        if (empty($data['item_id'])) {
            throw new ValidationException('item_id is required');
        }

        if (!in_array($data['item_type'], self::ALLOWED_TYPES, true)) {
            throw new ValidationException('Invalid item_type');
        }

        // Check if already favorited
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM favorites WHERE user_id = ? AND item_type = ? AND item_id = ?',
            [$userId, $data['item_type'], $data['item_id']]
        );

        if ($existing) {
            throw new ValidationException('Item is already in favorites');
        }

        // Verify item exists
        $item = $this->getItemDetails($data['item_type'], $data['item_id']);
        if (!$item) {
            throw new NotFoundException('Item not found');
        }

        // Get next position
        $maxPosition = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), 0) FROM favorites WHERE user_id = ?',
            [$userId]
        );

        $id = Uuid::uuid4()->toString();
        $this->db->insert('favorites', [
            'id' => $id,
            'user_id' => $userId,
            'item_type' => $data['item_type'],
            'item_id' => $data['item_id'],
            'position' => $maxPosition + 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $favorite = $this->db->fetchAssociative(
            'SELECT * FROM favorites WHERE id = ?',
            [$id]
        );
        $favorite['item'] = $item;

        return JsonResponse::created($favorite);
    }

    /**
     * Remove a favorite
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $itemType = $route->getArgument('type');
        $itemId = $route->getArgument('id');

        $deleted = $this->db->delete('favorites', [
            'user_id' => $userId,
            'item_type' => $itemType,
            'item_id' => $itemId,
        ]);

        if ($deleted === 0) {
            throw new NotFoundException('Favorite not found');
        }

        return JsonResponse::noContent();
    }

    /**
     * Reorder favorites
     */
    public function reorder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['order']) || !is_array($data['order'])) {
            throw new ValidationException('order array is required');
        }

        foreach ($data['order'] as $position => $favoriteId) {
            $this->db->update(
                'favorites',
                ['position' => $position],
                ['id' => $favoriteId, 'user_id' => $userId]
            );
        }

        return JsonResponse::success(null, 'Favorites reordered');
    }

    /**
     * Check if an item is favorited
     */
    public function check(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $itemType = $route->getArgument('type');
        $itemId = $route->getArgument('id');

        $favorite = $this->db->fetchAssociative(
            'SELECT id FROM favorites WHERE user_id = ? AND item_type = ? AND item_id = ?',
            [$userId, $itemType, $itemId]
        );

        return JsonResponse::success([
            'is_favorite' => $favorite !== false,
            'favorite_id' => $favorite['id'] ?? null,
        ]);
    }

    /**
     * Toggle favorite status
     */
    public function toggle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['item_type']) || empty($data['item_id'])) {
            throw new ValidationException('item_type and item_id are required');
        }

        $existing = $this->db->fetchAssociative(
            'SELECT id FROM favorites WHERE user_id = ? AND item_type = ? AND item_id = ?',
            [$userId, $data['item_type'], $data['item_id']]
        );

        if ($existing) {
            // Remove favorite
            $this->db->delete('favorites', ['id' => $existing['id']]);
            return JsonResponse::success(['is_favorite' => false], 'Removed from favorites');
        } else {
            // Add favorite
            $item = $this->getItemDetails($data['item_type'], $data['item_id']);
            if (!$item) {
                throw new NotFoundException('Item not found');
            }

            $maxPosition = (int) $this->db->fetchOne(
                'SELECT COALESCE(MAX(position), 0) FROM favorites WHERE user_id = ?',
                [$userId]
            );

            $id = Uuid::uuid4()->toString();
            $this->db->insert('favorites', [
                'id' => $id,
                'user_id' => $userId,
                'item_type' => $data['item_type'],
                'item_id' => $data['item_id'],
                'position' => $maxPosition + 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return JsonResponse::success(['is_favorite' => true, 'favorite_id' => $id], 'Added to favorites');
        }
    }

    /**
     * Get item details based on type
     */
    private function getItemDetails(string $type, string $id): ?array
    {
        return match($type) {
            'list' => $this->db->fetchAssociative(
                'SELECT id, title, color, icon FROM lists WHERE id = ? AND is_archived = FALSE',
                [$id]
            ) ?: null,
            'document' => $this->db->fetchAssociative(
                'SELECT id, title FROM documents WHERE id = ? AND is_archived = FALSE',
                [$id]
            ) ?: null,
            'kanban_board' => $this->db->fetchAssociative(
                'SELECT id, name as title, color FROM kanban_boards WHERE id = ? AND is_archived = FALSE',
                [$id]
            ) ?: null,
            'project' => $this->db->fetchAssociative(
                'SELECT id, name as title, color FROM projects WHERE id = ? AND is_archived = FALSE',
                [$id]
            ) ?: null,
            'checklist' => $this->db->fetchAssociative(
                'SELECT id, name as title FROM shared_checklists WHERE id = ?',
                [$id]
            ) ?: null,
            'snippet' => $this->db->fetchAssociative(
                'SELECT id, title, language FROM snippets WHERE id = ?',
                [$id]
            ) ?: null,
            'bookmark_group' => $this->db->fetchAssociative(
                'SELECT id, name as title, icon FROM bookmark_groups WHERE id = ?',
                [$id]
            ) ?: null,
            'connection' => $this->db->fetchAssociative(
                'SELECT id, name as title, type FROM connections WHERE id = ?',
                [$id]
            ) ?: null,
            default => null,
        };
    }
}
