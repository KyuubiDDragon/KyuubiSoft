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
     * Get all permissions for a user (from roles AND direct assignments)
     */
    public function getUserPermissions(string $userId): array
    {
        $cacheKey = self::CACHE_PREFIX . "user_permissions:{$userId}";

        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            // Get permissions from roles
            $rolePermissions = $this->db->fetchFirstColumn(
                'SELECT DISTINCT p.name
                 FROM permissions p
                 INNER JOIN role_permissions rp ON p.id = rp.permission_id
                 INNER JOIN user_roles ur ON rp.role_id = ur.role_id
                 WHERE ur.user_id = ?',
                [$userId]
            );

            // Get direct user permissions
            $directPermissions = $this->db->fetchFirstColumn(
                'SELECT DISTINCT p.name
                 FROM permissions p
                 INNER JOIN user_permissions up ON p.id = up.permission_id
                 WHERE up.user_id = ?',
                [$userId]
            );

            // Merge and return unique permissions
            return array_values(array_unique(array_merge($rolePermissions, $directPermissions)));
        });
    }

    /**
     * Get only direct permissions for a user (not from roles)
     */
    public function getUserDirectPermissions(string $userId): array
    {
        return $this->db->fetchFirstColumn(
            'SELECT p.name
             FROM permissions p
             INNER JOIN user_permissions up ON p.id = up.permission_id
             WHERE up.user_id = ?
             ORDER BY p.module, p.name',
            [$userId]
        );
    }

    /**
     * Assign a permission directly to a user
     */
    public function assignPermission(string $userId, string $permissionName, ?string $grantedBy = null): bool
    {
        $permission = $this->db->fetchAssociative(
            'SELECT id FROM permissions WHERE name = ?',
            [$permissionName]
        );

        if (!$permission) {
            return false;
        }

        // Check if already assigned
        $existing = $this->db->fetchOne(
            'SELECT 1 FROM user_permissions WHERE user_id = ? AND permission_id = ?',
            [$userId, $permission['id']]
        );

        if ($existing) {
            return true;
        }

        $this->db->insert('user_permissions', [
            'user_id' => $userId,
            'permission_id' => $permission['id'],
            'granted_at' => date('Y-m-d H:i:s'),
            'granted_by' => $grantedBy,
        ]);

        $this->clearUserCache($userId);
        return true;
    }

    /**
     * Remove a direct permission from a user
     */
    public function removePermission(string $userId, string $permissionName): bool
    {
        $permission = $this->db->fetchAssociative(
            'SELECT id FROM permissions WHERE name = ?',
            [$permissionName]
        );

        if (!$permission) {
            return false;
        }

        $this->db->delete('user_permissions', [
            'user_id' => $userId,
            'permission_id' => $permission['id'],
        ]);

        $this->clearUserCache($userId);
        return true;
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
                'SELECT id, name, description, hierarchy_level, is_system FROM roles ORDER BY hierarchy_level DESC'
            );
        });
    }

    /**
     * Get a single role by ID
     */
    public function getRoleById(int $roleId): ?array
    {
        $role = $this->db->fetchAssociative(
            'SELECT id, name, description, hierarchy_level, is_system FROM roles WHERE id = ?',
            [$roleId]
        );
        return $role ?: null;
    }

    /**
     * Get a single role by name
     */
    public function getRoleByName(string $name): ?array
    {
        $role = $this->db->fetchAssociative(
            'SELECT id, name, description, hierarchy_level, is_system FROM roles WHERE name = ?',
            [$name]
        );
        return $role ?: null;
    }

    /**
     * Create a new role
     */
    public function createRole(string $name, string $description = '', int $hierarchyLevel = 50): int
    {
        $this->db->insert('roles', [
            'name' => $name,
            'description' => $description,
            'hierarchy_level' => $hierarchyLevel,
            'is_system' => 0,
        ]);

        $this->clearRolesCache();
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a role
     */
    public function updateRole(int $roleId, array $data): bool
    {
        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['hierarchy_level'])) {
            $updateData['hierarchy_level'] = $data['hierarchy_level'];
        }

        if (empty($updateData)) {
            return false;
        }

        $this->db->update('roles', $updateData, ['id' => $roleId]);
        $this->clearRolesCache();
        return true;
    }

    /**
     * Delete a role
     */
    public function deleteRole(int $roleId): bool
    {
        // First remove all user-role assignments
        $this->db->delete('user_roles', ['role_id' => $roleId]);
        // Then remove all role-permission assignments
        $this->db->delete('role_permissions', ['role_id' => $roleId]);
        // Finally delete the role
        $this->db->delete('roles', ['id' => $roleId]);

        $this->clearRolesCache();
        return true;
    }

    /**
     * Get all permissions assigned to a role
     */
    public function getRolePermissions(int $roleId): array
    {
        return $this->db->fetchFirstColumn(
            'SELECT p.name
             FROM permissions p
             INNER JOIN role_permissions rp ON p.id = rp.permission_id
             WHERE rp.role_id = ?
             ORDER BY p.module, p.name',
            [$roleId]
        );
    }

    /**
     * Assign a permission to a role
     */
    public function assignPermissionToRole(int $roleId, string $permissionName): bool
    {
        $permission = $this->db->fetchAssociative(
            'SELECT id FROM permissions WHERE name = ?',
            [$permissionName]
        );

        if (!$permission) {
            return false;
        }

        // Check if already assigned
        $existing = $this->db->fetchOne(
            'SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?',
            [$roleId, $permission['id']]
        );

        if ($existing) {
            return true;
        }

        $this->db->insert('role_permissions', [
            'role_id' => $roleId,
            'permission_id' => $permission['id'],
        ]);

        $this->clearRolesCache();
        return true;
    }

    /**
     * Remove a permission from a role
     */
    public function removePermissionFromRole(int $roleId, string $permissionName): bool
    {
        $permission = $this->db->fetchAssociative(
            'SELECT id FROM permissions WHERE name = ?',
            [$permissionName]
        );

        if (!$permission) {
            return false;
        }

        $this->db->delete('role_permissions', [
            'role_id' => $roleId,
            'permission_id' => $permission['id'],
        ]);

        $this->clearRolesCache();
        return true;
    }

    /**
     * Set all permissions for a role (replaces existing)
     */
    public function setRolePermissions(int $roleId, array $permissionNames): bool
    {
        // Remove all existing permissions
        $this->db->delete('role_permissions', ['role_id' => $roleId]);

        // Add new permissions
        foreach ($permissionNames as $permissionName) {
            $permission = $this->db->fetchAssociative(
                'SELECT id FROM permissions WHERE name = ?',
                [$permissionName]
            );

            if ($permission) {
                $this->db->insert('role_permissions', [
                    'role_id' => $roleId,
                    'permission_id' => $permission['id'],
                ]);
            }
        }

        $this->clearRolesCache();
        return true;
    }

    /**
     * Get users with a specific role
     */
    public function getUsersWithRole(int $roleId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT u.id, u.username, u.email
             FROM users u
             INNER JOIN user_roles ur ON u.id = ur.user_id
             WHERE ur.role_id = ?
             ORDER BY u.username',
            [$roleId]
        );
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

    /**
     * Clear roles cache
     */
    public function clearRolesCache(): void
    {
        $this->cache->delete(self::CACHE_PREFIX . 'all_roles');
    }
}
