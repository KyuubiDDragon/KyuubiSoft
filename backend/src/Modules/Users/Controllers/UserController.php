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
        $adminFields = ['email', 'is_active', 'is_verified'];

        foreach ($selfFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if ($userId !== $currentUserId || $this->rbacManager->hasPermission($currentUserId, 'users.write')) {
            foreach ($adminFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
        }

        if (empty($updateData)) {
            throw new ValidationException('No valid fields to update');
        }

        $this->userRepository->update($userId, $updateData);

        $updatedUser = $this->userRepository->findById($userId);
        unset($updatedUser['password_hash'], $updatedUser['two_factor_secret']);

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
}
