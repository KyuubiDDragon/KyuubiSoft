<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Core\Security\PasswordHasher;
use App\Core\Security\RbacManager;
use App\Modules\Auth\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class UserController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PasswordHasher $passwordHasher,
        private readonly RbacManager $rbacManager
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $users = $this->userRepository->findAll($perPage, $offset);
        $total = $this->userRepository->count();

        // Add roles to each user
        foreach ($users as &$user) {
            $user['roles'] = $this->rbacManager->getUserRoles($user['id']);
        }

        return JsonResponse::paginated($users, $total, $page, $perPage);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUserId = $request->getAttribute('user_id');
        $userId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Users can only view themselves unless they have permission
        if ($userId !== $currentUserId && !$this->rbacManager->hasPermission($currentUserId, 'users.read')) {
            throw new ForbiddenException('You can only view your own profile');
        }

        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new NotFoundException('User not found');
        }

        unset($user['password_hash'], $user['two_factor_secret'], $user['two_factor_temp_secret']);

        $user['roles'] = $this->rbacManager->getUserRoles($userId);

        return JsonResponse::success($user);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUserId = $request->getAttribute('user_id');
        $userId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Users can only update themselves unless they have permission
        if ($userId !== $currentUserId && !$this->rbacManager->hasPermission($currentUserId, 'users.write')) {
            throw new ForbiddenException('You can only update your own profile');
        }

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        $data = $request->getParsedBody() ?? [];
        $updateData = [];

        // Allowed fields for self-update
        $selfFields = ['username', 'avatar_url'];

        // Additional fields for admins
        $adminFields = ['email', 'is_active', 'is_verified', 'restricted_to_projects', 'require_2fa', 'allowed_project_ids'];

        // Boolean fields that need conversion
        $booleanFields = ['is_active', 'is_verified', 'restricted_to_projects', 'require_2fa'];

        foreach ($selfFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if ($userId !== $currentUserId || $this->rbacManager->hasPermission($currentUserId, 'users.write')) {
            foreach ($adminFields as $field) {
                if (array_key_exists($field, $data)) {
                    $value = $data[$field];

                    // Convert booleans to integers for MySQL
                    if (in_array($field, $booleanFields)) {
                        $value = $value ? 1 : 0;
                    }

                    // Handle allowed_project_ids as JSON
                    if ($field === 'allowed_project_ids') {
                        $value = is_array($value) ? json_encode($value) : $value;
                    }

                    $updateData[$field] = $value;
                }
            }
        }

        if (empty($updateData)) {
            throw new ValidationException('No valid fields to update');
        }

        $this->userRepository->update($userId, $updateData);

        $updatedUser = $this->userRepository->findById($userId);
        unset($updatedUser['password_hash'], $updatedUser['two_factor_secret']);

        // Add roles to response
        $updatedUser['roles'] = $this->rbacManager->getUserRoles($userId);

        return JsonResponse::success($updatedUser, 'User updated successfully');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUserId = $request->getAttribute('user_id');
        $userId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        if ($userId === $currentUserId) {
            throw new ForbiddenException('You cannot delete your own account');
        }

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        // Check if trying to delete an owner
        if ($this->rbacManager->hasRole($userId, 'owner')) {
            throw new ForbiddenException('Cannot delete system owner');
        }

        $this->userRepository->delete($userId);

        return JsonResponse::success(null, 'User deleted successfully');
    }

    public function profile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new NotFoundException('User not found');
        }

        unset($user['password_hash'], $user['two_factor_secret'], $user['two_factor_temp_secret']);

        $user['roles'] = $this->rbacManager->getUserRoles($userId);
        $user['permissions'] = $this->rbacManager->getUserPermissions($userId);

        return JsonResponse::success($user);
    }

    public function updateProfile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $allowedFields = ['username', 'avatar_url'];
        $updateData = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (!empty($updateData)) {
            $this->userRepository->update($userId, $updateData);
        }

        $user = $this->userRepository->findById($userId);
        unset($user['password_hash'], $user['two_factor_secret']);

        return JsonResponse::success($user, 'Profile updated successfully');
    }

    public function updatePassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $currentPassword = $data['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            throw new ValidationException('Current and new password are required');
        }

        if ($newPassword !== $confirmPassword) {
            throw new ValidationException('New passwords do not match');
        }

        $user = $this->userRepository->findById($userId);

        if (!$this->passwordHasher->verify($currentPassword, $user['password_hash'])) {
            throw new ValidationException('Current password is incorrect');
        }

        $passwordErrors = $this->passwordHasher->validateStrength($newPassword);
        if (!empty($passwordErrors)) {
            throw new ValidationException(implode(', ', $passwordErrors));
        }

        $this->userRepository->update($userId, [
            'password_hash' => $this->passwordHasher->hash($newPassword),
        ]);

        return JsonResponse::success(null, 'Password updated successfully');
    }

    // ============================================
    // Role Management
    // ============================================

    /**
     * Get all available roles
     */
    public function getRoles(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $roles = $this->rbacManager->getAllRoles();
        return JsonResponse::success($roles);
    }

    /**
     * Get all available permissions
     */
    public function getPermissions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $permissions = $this->rbacManager->getAllPermissions();

        // Group by module
        $grouped = [];
        foreach ($permissions as $permission) {
            $module = $permission['module'] ?? 'general';
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $permission;
        }

        return JsonResponse::success([
            'permissions' => $permissions,
            'grouped' => $grouped,
        ]);
    }

    /**
     * Get roles for a specific user
     */
    public function getUserRoles(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        $roles = $this->rbacManager->getUserRoles($userId);

        return JsonResponse::success(['roles' => $roles]);
    }

    /**
     * Assign a role to a user
     */
    public function assignRole(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUserId = $request->getAttribute('user_id');
        $userId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $roleName = $data['role'] ?? '';

        if (empty($roleName)) {
            throw new ValidationException('Role name is required');
        }

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        // Prevent assigning owner role unless current user is owner
        if ($roleName === 'owner' && !$this->rbacManager->hasRole($currentUserId, 'owner')) {
            throw new ForbiddenException('Only owners can assign the owner role');
        }

        // Check hierarchy - can only assign roles lower than your own
        $currentUserRoles = $this->rbacManager->getUserRoles($currentUserId);
        if (!in_array('owner', $currentUserRoles)) {
            $allRoles = $this->rbacManager->getAllRoles();
            $roleHierarchy = array_column($allRoles, 'hierarchy_level', 'name');

            $currentMaxLevel = 0;
            foreach ($currentUserRoles as $role) {
                if (isset($roleHierarchy[$role]) && $roleHierarchy[$role] > $currentMaxLevel) {
                    $currentMaxLevel = $roleHierarchy[$role];
                }
            }

            $targetLevel = $roleHierarchy[$roleName] ?? 0;
            if ($targetLevel >= $currentMaxLevel) {
                throw new ForbiddenException('Cannot assign a role with equal or higher privileges than your own');
            }
        }

        $success = $this->rbacManager->assignRole($userId, $roleName, $currentUserId);

        if (!$success) {
            throw new ValidationException('Failed to assign role. Role may not exist.');
        }

        $newRoles = $this->rbacManager->getUserRoles($userId);

        return JsonResponse::success(['roles' => $newRoles], 'Role assigned successfully');
    }

    /**
     * Remove a role from a user
     */
    public function removeRole(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUserId = $request->getAttribute('user_id');
        $userId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $roleName = RouteContext::fromRequest($request)->getRoute()->getArgument('role');

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        // Cannot remove own owner role
        if ($userId === $currentUserId && $roleName === 'owner') {
            throw new ForbiddenException('Cannot remove your own owner role');
        }

        // Only owners can remove owner role from others
        if ($roleName === 'owner' && !$this->rbacManager->hasRole($currentUserId, 'owner')) {
            throw new ForbiddenException('Only owners can remove the owner role');
        }

        $success = $this->rbacManager->removeRole($userId, $roleName);

        if (!$success) {
            throw new ValidationException('Failed to remove role');
        }

        $newRoles = $this->rbacManager->getUserRoles($userId);

        return JsonResponse::success(['roles' => $newRoles], 'Role removed successfully');
    }

    /**
     * Approve a pending user registration
     */
    public function approve(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUserId = $request->getAttribute('user_id');
        $userId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        // Check if user is actually pending
        $roles = $this->rbacManager->getUserRoles($userId);
        if (!in_array('pending', $roles)) {
            throw new ValidationException('Dieser Benutzer ist bereits freigeschaltet');
        }

        // Activate user
        $this->userRepository->update($userId, [
            'is_active' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Remove pending role and assign user role
        $this->rbacManager->removeRole($userId, 'pending');
        $this->rbacManager->assignRole($userId, 'user', $currentUserId);

        $updatedUser = $this->userRepository->findById($userId);
        unset($updatedUser['password_hash'], $updatedUser['two_factor_secret']);
        $updatedUser['roles'] = $this->rbacManager->getUserRoles($userId);

        return JsonResponse::success($updatedUser, 'Benutzer erfolgreich freigeschaltet');
    }

    /**
     * Reject a pending user registration (delete the user)
     */
    public function reject(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        // Check if user is actually pending
        $roles = $this->rbacManager->getUserRoles($userId);
        if (!in_array('pending', $roles)) {
            throw new ValidationException('Dieser Benutzer ist bereits freigeschaltet und kann nicht abgelehnt werden');
        }

        // Delete the pending user
        $this->userRepository->delete($userId);

        return JsonResponse::success(null, 'Registrierungsanfrage abgelehnt');
    }

    /**
     * Get all pending users awaiting approval
     */
    public function getPendingUsers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $users = $this->userRepository->findAll(1000, 0);

        // Filter to only pending users
        $pendingUsers = [];
        foreach ($users as $user) {
            $roles = $this->rbacManager->getUserRoles($user['id']);
            if (in_array('pending', $roles)) {
                unset($user['password_hash'], $user['two_factor_secret'], $user['two_factor_temp_secret']);
                $user['roles'] = $roles;
                $pendingUsers[] = $user;
            }
        }

        return JsonResponse::success([
            'users' => $pendingUsers,
            'total' => count($pendingUsers),
        ]);
    }

    /**
     * Create a new user (admin only)
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUserId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $email = trim($data['email'] ?? '');
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $roles = $data['roles'] ?? ['user'];

        if (empty($email) || empty($username) || empty($password)) {
            throw new ValidationException('Email, username and password are required');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email address');
        }

        // Check if email already exists
        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser) {
            throw new ValidationException('Email already registered');
        }

        // Validate password
        $passwordErrors = $this->passwordHasher->validateStrength($password);
        if (!empty($passwordErrors)) {
            throw new ValidationException(implode(', ', $passwordErrors));
        }

        // Create user
        $userId = Uuid::uuid4()->toString();
        $this->userRepository->create([
            'id' => $userId,
            'email' => $email,
            'username' => $username,
            'password_hash' => $this->passwordHasher->hash($password),
            'is_verified' => true, // Admin-created users are auto-verified
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Assign roles
        foreach ($roles as $roleName) {
            // Skip owner role if current user is not owner
            if ($roleName === 'owner' && !$this->rbacManager->hasRole($currentUserId, 'owner')) {
                continue;
            }
            $this->rbacManager->assignRole($userId, $roleName, $currentUserId);
        }

        $user = $this->userRepository->findById($userId);
        unset($user['password_hash']);
        $user['roles'] = $this->rbacManager->getUserRoles($userId);

        return JsonResponse::success($user, 'User created successfully');
    }

    // ============================================
    // Direct User Permission Management
    // ============================================

    /**
     * Get direct permissions assigned to a specific user (not from roles)
     */
    public function getUserDirectPermissions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        $directPermissions = $this->rbacManager->getUserDirectPermissions($userId);
        $allPermissions = $this->rbacManager->getUserPermissions($userId);
        $rolePermissions = array_values(array_diff($allPermissions, $directPermissions));

        return JsonResponse::success([
            'direct_permissions' => $directPermissions,
            'role_permissions' => $rolePermissions,
            'all_permissions' => $allPermissions,
        ]);
    }

    /**
     * Assign a permission directly to a user
     */
    public function assignUserPermission(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUserId = $request->getAttribute('user_id');
        $userId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $permissionName = $data['permission'] ?? '';

        if (empty($permissionName)) {
            throw new ValidationException('Permission name is required');
        }

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        // Only owners and admins can assign permissions
        if (!$this->rbacManager->hasPermission($currentUserId, 'users.write')) {
            throw new ForbiddenException('Du hast keine Berechtigung, Berechtigungen zu vergeben');
        }

        // Check if current user has this permission (can't give what you don't have)
        if (!$this->rbacManager->hasPermission($currentUserId, $permissionName) &&
            !$this->rbacManager->hasRole($currentUserId, 'owner')) {
            throw new ForbiddenException('Du kannst nur Berechtigungen vergeben, die du selbst besitzt');
        }

        $success = $this->rbacManager->assignPermission($userId, $permissionName, $currentUserId);

        if (!$success) {
            throw new ValidationException('Berechtigung existiert nicht');
        }

        $directPermissions = $this->rbacManager->getUserDirectPermissions($userId);

        return JsonResponse::success([
            'direct_permissions' => $directPermissions,
        ], 'Berechtigung erfolgreich zugewiesen');
    }

    /**
     * Remove a direct permission from a user
     */
    public function removeUserPermission(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $currentUserId = $request->getAttribute('user_id');
        $userId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $permissionName = RouteContext::fromRequest($request)->getRoute()->getArgument('permission');

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        // Only owners and admins can remove permissions
        if (!$this->rbacManager->hasPermission($currentUserId, 'users.write')) {
            throw new ForbiddenException('Du hast keine Berechtigung, Berechtigungen zu entfernen');
        }

        $success = $this->rbacManager->removePermission($userId, $permissionName);

        if (!$success) {
            throw new ValidationException('Berechtigung existiert nicht');
        }

        $directPermissions = $this->rbacManager->getUserDirectPermissions($userId);

        return JsonResponse::success([
            'direct_permissions' => $directPermissions,
        ], 'Berechtigung erfolgreich entfernt');
    }
}
