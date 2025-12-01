<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Services\CacheService;
use Doctrine\DBAL\Connection;

class RbacManager
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_PREFIX = 'rbac:';

    public function __construct(
        private readonly Connection $db,
        private readonly CacheService $cache
    ) {}

    /**
     * Get all roles for a user
     */
    public function getUserRoles(string $userId): array
    {
        $cacheKey = self::CACHE_PREFIX . "user_roles:{$userId}";

        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            return $this->db->fetchFirstColumn(
                'SELECT r.name
                 FROM roles r
                 INNER JOIN user_roles ur ON r.id = ur.role_id
                 WHERE ur.user_id = ?',
                [$userId]
            );
        });
    }

    /**
     * Get all permissions for a user (from all their roles)
     */
    public function getUserPermissions(string $userId): array
    {
        $cacheKey = self::CACHE_PREFIX . "user_permissions:{$userId}";

        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            return $this->db->fetchFirstColumn(
                'SELECT DISTINCT p.name
                 FROM permissions p
                 INNER JOIN role_permissions rp ON p.id = rp.permission_id
                 INNER JOIN user_roles ur ON rp.role_id = ur.role_id
                 WHERE ur.user_id = ?',
                [$userId]
            );
        });
    }

    /**
     * Check if a user has a specific permission
     */
    public function hasPermission(string $userId, string $permission): bool
    {
        $permissions = $this->getUserPermissions($userId);

        // Direct permission check
        if (in_array($permission, $permissions, true)) {
            return true;
        }

        // Wildcard check (e.g., "users.*" grants "users.read")
        $parts = explode('.', $permission);
        if (count($parts) >= 2) {
            $wildcardPermission = $parts[0] . '.*';
            if (in_array($wildcardPermission, $permissions, true)) {
                return true;
            }
        }

        // Check if user is owner (has all permissions)
        $roles = $this->getUserRoles($userId);
        return in_array('owner', $roles, true);
    }

    /**
     * Check if a user has a specific role
     */
    public function hasRole(string $userId, string $role): bool
    {
        $roles = $this->getUserRoles($userId);
        return in_array($role, $roles, true);
    }

    /**
     * Assign a role to a user
     */
    public function assignRole(string $userId, string $roleName, ?string $assignedBy = null): bool
    {
        $role = $this->db->fetchAssociative(
            'SELECT id FROM roles WHERE name = ?',
            [$roleName]
        );

        if (!$role) {
            return false;
        }

        // Check if already assigned
        $existing = $this->db->fetchOne(
            'SELECT 1 FROM user_roles WHERE user_id = ? AND role_id = ?',
            [$userId, $role['id']]
        );

        if ($existing) {
            return true;
        }

        $this->db->insert('user_roles', [
            'user_id' => $userId,
            'role_id' => $role['id'],
            'assigned_at' => date('Y-m-d H:i:s'),
            'assigned_by' => $assignedBy,
        ]);

        $this->clearUserCache($userId);
        return true;
    }

    /**
     * Remove a role from a user
     */
    public function removeRole(string $userId, string $roleName): bool
    {
        $role = $this->db->fetchAssociative(
            'SELECT id FROM roles WHERE name = ?',
            [$roleName]
        );

        if (!$role) {
            return false;
        }

        $this->db->delete('user_roles', [
            'user_id' => $userId,
            'role_id' => $role['id'],
        ]);

        $this->clearUserCache($userId);
        return true;
    }

    /**
     * Get all available roles
     */
    public function getAllRoles(): array
    {
        return $this->cache->remember(self::CACHE_PREFIX . 'all_roles', self::CACHE_TTL, function () {
            return $this->db->fetchAllAssociative(
                'SELECT id, name, description, hierarchy_level FROM roles ORDER BY hierarchy_level DESC'
            );
        });
    }

    /**
     * Get all available permissions
     */
    public function getAllPermissions(): array
    {
        return $this->cache->remember(self::CACHE_PREFIX . 'all_permissions', self::CACHE_TTL, function () {
            return $this->db->fetchAllAssociative(
                'SELECT id, name, description, module FROM permissions ORDER BY module, name'
            );
        });
    }

    /**
     * Clear user's RBAC cache
     */
    public function clearUserCache(string $userId): void
    {
        $this->cache->delete(self::CACHE_PREFIX . "user_roles:{$userId}");
        $this->cache->delete(self::CACHE_PREFIX . "user_permissions:{$userId}");
    }
}
