<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Core\Security\RbacManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

class RolesController
{
    public function __construct(
        private readonly RbacManager $rbacManager
    ) {}

    /**
     * Get all roles with their permissions
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $roles = $this->rbacManager->getAllRoles();

        // Add permissions to each role
        foreach ($roles as &$role) {
            $role['permissions'] = $this->rbacManager->getRolePermissions((int) $role['id']);
            $role['user_count'] = count($this->rbacManager->getUsersWithRole((int) $role['id']));
        }

        return JsonResponse::success($roles);
    }

    /**
     * Get a single role with details
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $roleId = (int) RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $role = $this->rbacManager->getRoleById($roleId);
        if (!$role) {
            throw new NotFoundException('Rolle nicht gefunden');
        }

        $role['permissions'] = $this->rbacManager->getRolePermissions($roleId);
        $role['users'] = $this->rbacManager->getUsersWithRole($roleId);

        return JsonResponse::success($role);
    }

    /**
     * Create a new role
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUserId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $hierarchyLevel = (int) ($data['hierarchy_level'] ?? 50);
        $permissions = $data['permissions'] ?? [];

        if (empty($name)) {
            throw new ValidationException('Rollenname ist erforderlich');
        }

        // Validate name format (lowercase, no spaces)
        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $name)) {
            throw new ValidationException('Rollenname darf nur Kleinbuchstaben, Zahlen, Unterstriche und Bindestriche enthalten');
        }

        // Check if role name already exists
        $existing = $this->rbacManager->getRoleByName($name);
        if ($existing) {
            throw new ValidationException('Eine Rolle mit diesem Namen existiert bereits');
        }

        // Only owner can create roles with high hierarchy level
        if ($hierarchyLevel >= 90 && !$this->rbacManager->hasRole($currentUserId, 'owner')) {
            throw new ForbiddenException('Nur Owner können Rollen mit hohem Rang erstellen');
        }

        // Create the role
        $roleId = $this->rbacManager->createRole($name, $description, $hierarchyLevel);

        // Assign permissions
        if (!empty($permissions)) {
            $this->rbacManager->setRolePermissions($roleId, $permissions);
        }

        $role = $this->rbacManager->getRoleById($roleId);
        $role['permissions'] = $this->rbacManager->getRolePermissions($roleId);

        return JsonResponse::success($role, 'Rolle erfolgreich erstellt');
    }

    /**
     * Update a role
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUserId = $request->getAttribute('user_id');
        $roleId = (int) RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $role = $this->rbacManager->getRoleById($roleId);
        if (!$role) {
            throw new NotFoundException('Rolle nicht gefunden');
        }

        // Check if this is a system role
        if ($role['is_system']) {
            throw new ForbiddenException('Systemrollen können nicht bearbeitet werden');
        }

        // Caller must outrank the target role (owner is exempt).
        $this->assertCallerOutranksRole($currentUserId, (int) ($role['hierarchy_level'] ?? 0));

        $updateData = [];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if (!preg_match('/^[a-z][a-z0-9_-]*$/', $name)) {
                throw new ValidationException('Rollenname darf nur Kleinbuchstaben, Zahlen, Unterstriche und Bindestriche enthalten');
            }
            // Check if name is already taken by another role
            $existing = $this->rbacManager->getRoleByName($name);
            if ($existing && $existing['id'] != $roleId) {
                throw new ValidationException('Eine Rolle mit diesem Namen existiert bereits');
            }
            $updateData['name'] = $name;
        }

        if (isset($data['description'])) {
            $updateData['description'] = trim($data['description']);
        }

        if (isset($data['hierarchy_level'])) {
            $hierarchyLevel = (int) $data['hierarchy_level'];
            if ($hierarchyLevel >= 90 && !$this->rbacManager->hasRole($currentUserId, 'owner')) {
                throw new ForbiddenException('Nur Owner können Rollen mit hohem Rang erstellen');
            }
            // Cannot raise a role above the caller's own ceiling.
            $this->assertCallerOutranksRole($currentUserId, $hierarchyLevel);
            $updateData['hierarchy_level'] = $hierarchyLevel;
        }

        if (!empty($updateData)) {
            $this->rbacManager->updateRole($roleId, $updateData);
        }

        // Update permissions if provided. Each permission must already be held
        // by the caller — prevents privilege escalation by editing a role they
        // themselves hold.
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $this->assertCallerCanGrantPermissions($currentUserId, $data['permissions']);
            $this->rbacManager->setRolePermissions($roleId, $data['permissions']);
        }

        $updatedRole = $this->rbacManager->getRoleById($roleId);
        $updatedRole['permissions'] = $this->rbacManager->getRolePermissions($roleId);

        return JsonResponse::success($updatedRole, 'Rolle erfolgreich aktualisiert');
    }

    /**
     * Delete a role
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $roleId = (int) RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $role = $this->rbacManager->getRoleById($roleId);
        if (!$role) {
            throw new NotFoundException('Rolle nicht gefunden');
        }

        // Check if this is a system role
        if ($role['is_system']) {
            throw new ForbiddenException('Systemrollen können nicht gelöscht werden');
        }

        // Check if users are still assigned to this role
        $users = $this->rbacManager->getUsersWithRole($roleId);
        if (count($users) > 0) {
            throw new ValidationException('Diese Rolle kann nicht gelöscht werden, da noch ' . count($users) . ' Benutzer zugewiesen sind');
        }

        $this->rbacManager->deleteRole($roleId);

        return JsonResponse::success(null, 'Rolle erfolgreich gelöscht');
    }

    /**
     * Get permissions for a role
     */
    public function getPermissions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $roleId = (int) RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $role = $this->rbacManager->getRoleById($roleId);
        if (!$role) {
            throw new NotFoundException('Rolle nicht gefunden');
        }

        $permissions = $this->rbacManager->getRolePermissions($roleId);

        return JsonResponse::success([
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Add a permission to a role
     */
    public function addPermission(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUserId = $request->getAttribute('user_id');
        $roleId = (int) RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $permissionName = $data['permission'] ?? '';

        if (empty($permissionName)) {
            throw new ValidationException('Berechtigung ist erforderlich');
        }

        $role = $this->rbacManager->getRoleById($roleId);
        if (!$role) {
            throw new NotFoundException('Rolle nicht gefunden');
        }

        if ($role['is_system']) {
            throw new ForbiddenException('Berechtigungen von Systemrollen können nicht geändert werden');
        }

        // Caller must outrank the target role and must already hold the
        // permission being granted (otherwise this is a privilege escalation).
        $this->assertCallerOutranksRole($currentUserId, (int) ($role['hierarchy_level'] ?? 0));
        $this->assertCallerCanGrantPermissions($currentUserId, [$permissionName]);

        $success = $this->rbacManager->assignPermissionToRole($roleId, $permissionName);
        if (!$success) {
            throw new ValidationException('Berechtigung existiert nicht');
        }

        $permissions = $this->rbacManager->getRolePermissions($roleId);

        return JsonResponse::success([
            'permissions' => $permissions,
        ], 'Berechtigung hinzugefügt');
    }

    /**
     * Remove a permission from a role
     */
    public function removePermission(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUserId = $request->getAttribute('user_id');
        $roleId = (int) RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $permissionName = RouteContext::fromRequest($request)->getRoute()->getArgument('permission');

        $role = $this->rbacManager->getRoleById($roleId);
        if (!$role) {
            throw new NotFoundException('Rolle nicht gefunden');
        }

        if ($role['is_system']) {
            throw new ForbiddenException('Berechtigungen von Systemrollen können nicht geändert werden');
        }

        $this->assertCallerOutranksRole($currentUserId, (int) ($role['hierarchy_level'] ?? 0));

        $success = $this->rbacManager->removePermissionFromRole($roleId, $permissionName);
        if (!$success) {
            throw new ValidationException('Berechtigung existiert nicht');
        }

        $permissions = $this->rbacManager->getRolePermissions($roleId);

        return JsonResponse::success([
            'permissions' => $permissions,
        ], 'Berechtigung entfernt');
    }

    /**
     * Caller must already hold every permission they want to grant.
     * Owners can grant anything.
     */
    private function assertCallerCanGrantPermissions(string $currentUserId, array $permissions): void
    {
        if ($this->rbacManager->hasRole($currentUserId, 'owner')) {
            return;
        }
        foreach ($permissions as $permission) {
            $name = is_string($permission) ? $permission : (string) ($permission['name'] ?? '');
            if ($name === '') {
                continue;
            }
            if (!$this->rbacManager->hasPermission($currentUserId, $name)) {
                throw new ForbiddenException(
                    'Du kannst nur Berechtigungen vergeben, die du selbst besitzt: ' . $name
                );
            }
        }
    }

    /**
     * Caller must rank strictly above the target hierarchy level. Owners are
     * exempt.
     */
    private function assertCallerOutranksRole(string $currentUserId, int $targetHierarchy): void
    {
        if ($this->rbacManager->hasRole($currentUserId, 'owner')) {
            return;
        }
        $callerRoles = $this->rbacManager->getUserRoles($currentUserId);
        $allRoles = $this->rbacManager->getAllRoles();
        $byName = array_column($allRoles, 'hierarchy_level', 'name');

        $callerMax = 0;
        foreach ($callerRoles as $name) {
            if (isset($byName[$name]) && $byName[$name] > $callerMax) {
                $callerMax = (int) $byName[$name];
            }
        }

        if ($targetHierarchy >= $callerMax) {
            throw new ForbiddenException('Du kannst keine Rolle mit gleichem oder höherem Rang bearbeiten');
        }
    }
}
