<?php

declare(strict_types=1);

namespace App\Modules\QuickAccess\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Exceptions\ValidationException;
use App\Core\Exceptions\NotFoundException;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class QuickAccessController
{
    // Valid navigation path prefixes - only internal app routes are allowed
    private const VALID_NAV_PREFIXES = [
        '/dashboard',
        '/documents',
        '/lists',
        '/kanban',
        '/projects',
        '/tickets',
        '/calendar',
        '/time-tracking',
        '/invoices',
        '/passwords',
        '/snippets',
        '/bookmarks',
        '/connections',
        '/checklists',
        '/wiki',
        '/chat',
        '/storage',
        '/docker',
        '/uptime',
        '/automation',
        '/templates',
        '/tags',
        '/settings',
        '/backup',
        '/users',
        '/webhooks',
        '/api-keys',
    ];

    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Get all quick access items for the current user
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $items = $this->db->fetchAllAssociative(
            'SELECT * FROM user_quick_access WHERE user_id = ? ORDER BY position ASC, created_at DESC',
            [$userId]
        );

        // Get max visible setting
        $maxVisible = $this->getMaxVisibleSetting($userId);

        return JsonResponse::success([
            'items' => $items,
            'max_visible' => $maxVisible,
        ]);
    }

    /**
     * Add a navigation item to quick access
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $this->validateNavItem($data);

        // Check if already exists
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM user_quick_access WHERE user_id = ? AND nav_id = ?',
            [$userId, $data['nav_id']]
        );

        if ($existing) {
            throw new ValidationException('Navigation item already in quick access');
        }

        // Get next position
        $maxPosition = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), 0) FROM user_quick_access WHERE user_id = ?',
            [$userId]
        );

        $id = Uuid::uuid4()->toString();
        $this->db->insert('user_quick_access', [
            'id' => $id,
            'user_id' => $userId,
            'nav_id' => $data['nav_id'],
            'nav_name' => $data['nav_name'],
            'nav_href' => $data['nav_href'],
            'nav_icon' => $data['nav_icon'],
            'position' => $maxPosition + 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $item = $this->db->fetchAssociative(
            'SELECT * FROM user_quick_access WHERE id = ?',
            [$id]
        );

        return JsonResponse::created($item);
    }

    /**
     * Remove a navigation item from quick access
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $navId = RouteContext::fromRequest($request)->getRoute()->getArgument('navId');

        $deleted = $this->db->delete('user_quick_access', [
            'user_id' => $userId,
            'nav_id' => $navId,
        ]);

        if ($deleted === 0) {
            throw new NotFoundException('Quick access item not found');
        }

        return JsonResponse::noContent();
    }

    /**
     * Toggle quick access status for a navigation item
     */
    public function toggle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $this->validateNavItem($data);

        $existing = $this->db->fetchAssociative(
            'SELECT id FROM user_quick_access WHERE user_id = ? AND nav_id = ?',
            [$userId, $data['nav_id']]
        );

        if ($existing) {
            // Remove from quick access
            $this->db->delete('user_quick_access', ['id' => $existing['id']]);
            return JsonResponse::success(['is_pinned' => false], 'Removed from quick access');
        } else {
            // Add to quick access
            $maxPosition = (int) $this->db->fetchOne(
                'SELECT COALESCE(MAX(position), 0) FROM user_quick_access WHERE user_id = ?',
                [$userId]
            );

            $id = Uuid::uuid4()->toString();
            $this->db->insert('user_quick_access', [
                'id' => $id,
                'user_id' => $userId,
                'nav_id' => $data['nav_id'],
                'nav_name' => $data['nav_name'],
                'nav_href' => $data['nav_href'],
                'nav_icon' => $data['nav_icon'],
                'position' => $maxPosition + 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return JsonResponse::success(['is_pinned' => true, 'id' => $id], 'Added to quick access');
        }
    }

    /**
     * Reorder quick access items
     */
    public function reorder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['order']) || !is_array($data['order'])) {
            throw new ValidationException('order array is required');
        }

        foreach ($data['order'] as $position => $itemId) {
            $this->db->update(
                'user_quick_access',
                ['position' => $position],
                ['id' => $itemId, 'user_id' => $userId]
            );
        }

        return JsonResponse::success(null, 'Quick access items reordered');
    }

    /**
     * Get settings for quick access
     */
    public function getSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $maxVisible = $this->getMaxVisibleSetting($userId);

        return JsonResponse::success([
            'max_visible' => $maxVisible,
        ]);
    }

    /**
     * Update settings for quick access
     */
    public function updateSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (!isset($data['max_visible'])) {
            throw new ValidationException('max_visible is required');
        }

        $maxVisible = max(1, min(10, (int) $data['max_visible']));

        // Check if setting exists (user_settings uses composite key: user_id + key)
        $existing = $this->db->fetchAssociative(
            'SELECT `value` FROM user_settings WHERE user_id = ? AND `key` = ?',
            [$userId, 'quick_access_max_visible']
        );

        if ($existing) {
            $this->db->executeStatement(
                'UPDATE user_settings SET `value` = ?, updated_at = ? WHERE user_id = ? AND `key` = ?',
                [json_encode($maxVisible), date('Y-m-d H:i:s'), $userId, 'quick_access_max_visible']
            );
        } else {
            $this->db->executeStatement(
                'INSERT INTO user_settings (user_id, `key`, `value`, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
                [$userId, 'quick_access_max_visible', json_encode($maxVisible), date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
            );
        }

        return JsonResponse::success(['max_visible' => $maxVisible], 'Settings updated');
    }

    /**
     * Check if a navigation item is pinned
     */
    public function check(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $navId = RouteContext::fromRequest($request)->getRoute()->getArgument('navId');

        $item = $this->db->fetchAssociative(
            'SELECT id FROM user_quick_access WHERE user_id = ? AND nav_id = ?',
            [$userId, $navId]
        );

        return JsonResponse::success([
            'is_pinned' => $item !== false,
            'id' => $item['id'] ?? null,
        ]);
    }

    /**
     * Get the max visible setting for a user
     */
    private function getMaxVisibleSetting(string $userId): int
    {
        $setting = $this->db->fetchOne(
            'SELECT `value` FROM user_settings WHERE user_id = ? AND `key` = ?',
            [$userId, 'quick_access_max_visible']
        );

        if ($setting) {
            $decoded = json_decode($setting, true);
            return is_int($decoded) ? $decoded : 5;
        }

        return 5; // Default to 5
    }

    /**
     * Validate navigation item data
     */
    private function validateNavItem(array $data): void
    {
        if (empty($data['nav_id'])) {
            throw new ValidationException('nav_id is required');
        }

        if (empty($data['nav_name'])) {
            throw new ValidationException('nav_name is required');
        }

        if (empty($data['nav_href'])) {
            throw new ValidationException('nav_href is required');
        }

        if (empty($data['nav_icon'])) {
            throw new ValidationException('nav_icon is required');
        }

        // Validate nav_href is a valid internal path (prevent arbitrary URLs)
        $navHref = $data['nav_href'];

        // Must start with / (relative path)
        if (!str_starts_with($navHref, '/')) {
            throw new ValidationException('nav_href must be a valid internal path');
        }

        // Must not contain protocol (no external URLs)
        if (preg_match('/^https?:\/\//i', $navHref) || str_contains($navHref, '://')) {
            throw new ValidationException('External URLs are not allowed in quick access');
        }

        // Must start with one of the valid prefixes
        $isValid = false;
        foreach (self::VALID_NAV_PREFIXES as $prefix) {
            if (str_starts_with($navHref, $prefix)) {
                $isValid = true;
                break;
            }
        }

        if (!$isValid) {
            throw new ValidationException('nav_href must be a valid application path');
        }

        // Validate nav_id format (alphanumeric with dashes/underscores)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['nav_id'])) {
            throw new ValidationException('nav_id contains invalid characters');
        }

        // Validate nav_name length
        if (mb_strlen($data['nav_name']) > 100) {
            throw new ValidationException('nav_name is too long (max 100 characters)');
        }

        // Validate nav_icon format (alphanumeric with dashes)
        if (!preg_match('/^[a-zA-Z0-9-]+$/', $data['nav_icon'])) {
            throw new ValidationException('nav_icon contains invalid characters');
        }
    }
}
