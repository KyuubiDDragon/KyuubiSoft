<?php

declare(strict_types=1);

namespace App\Modules\Passwords\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Exceptions\ValidationException;
use App\Core\Exceptions\NotFoundException;
use App\Modules\Passwords\Services\PasswordService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

class PasswordController
{
    public function __construct(
        private readonly PasswordService $passwordService
    ) {}

    /**
     * List all passwords
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $categoryId = $params['category_id'] ?? null;
        $includeArchived = ($params['include_archived'] ?? 'false') === 'true';

        $passwords = $this->passwordService->getByUser($userId, $categoryId, $includeArchived);

        return JsonResponse::success(['items' => $passwords]);
    }

    /**
     * Get single password with decrypted data
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $password = $this->passwordService->getById($id, $userId);

        if (!$password) {
            throw new NotFoundException('Password not found');
        }

        return JsonResponse::success($password);
    }

    /**
     * Create new password
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name'])) {
            throw new ValidationException('Name is required');
        }
        if (empty($data['password'])) {
            throw new ValidationException('Password is required');
        }

        $password = $this->passwordService->create($userId, $data);

        return JsonResponse::created($password);
    }

    /**
     * Update password
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $password = $this->passwordService->update($id, $userId, $data);

        if (!$password) {
            throw new NotFoundException('Password not found');
        }

        return JsonResponse::success($password, 'Password updated');
    }

    /**
     * Delete password
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $success = $this->passwordService->delete($id, $userId);

        if (!$success) {
            throw new NotFoundException('Password not found');
        }

        return JsonResponse::noContent();
    }

    /**
     * Search passwords
     */
    public function search(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $query = $request->getQueryParams()['q'] ?? '';

        if (strlen($query) < 2) {
            return JsonResponse::success(['items' => []]);
        }

        $passwords = $this->passwordService->search($userId, $query);

        return JsonResponse::success(['items' => $passwords]);
    }

    /**
     * Generate TOTP code
     */
    public function generateTOTP(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $password = $this->passwordService->getById($id, $userId);

        if (!$password) {
            throw new NotFoundException('Password not found');
        }

        if (empty($password['totp_secret'])) {
            throw new ValidationException('No TOTP secret configured');
        }

        $code = $this->passwordService->generateTOTP($password['totp_secret']);
        $validUntil = (floor(time() / 30) + 1) * 30;

        return JsonResponse::success([
            'code' => $code,
            'valid_until' => $validUntil,
            'seconds_remaining' => $validUntil - time(),
        ]);
    }

    /**
     * Generate random password
     */
    public function generatePassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();

        $length = min(128, max(8, (int) ($params['length'] ?? 16)));
        $uppercase = ($params['uppercase'] ?? 'true') === 'true';
        $lowercase = ($params['lowercase'] ?? 'true') === 'true';
        $numbers = ($params['numbers'] ?? 'true') === 'true';
        $symbols = ($params['symbols'] ?? 'true') === 'true';

        $password = PasswordService::generatePassword($length, $uppercase, $lowercase, $numbers, $symbols);

        return JsonResponse::success(['password' => $password]);
    }

    /**
     * Toggle favorite
     */
    public function toggleFavorite(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $password = $this->passwordService->getById($id, $userId);

        if (!$password) {
            throw new NotFoundException('Password not found');
        }

        $updated = $this->passwordService->update($id, $userId, [
            'is_favorite' => !$password['is_favorite'],
        ]);

        return JsonResponse::success([
            'is_favorite' => $updated['is_favorite'],
        ]);
    }

    // Category endpoints

    /**
     * List categories
     */
    public function getCategories(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $categories = $this->passwordService->getCategories($userId);

        return JsonResponse::success(['items' => $categories]);
    }

    /**
     * Create category
     */
    public function createCategory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name'])) {
            throw new ValidationException('Name is required');
        }

        $category = $this->passwordService->createCategory($userId, $data);

        return JsonResponse::created($category);
    }

    /**
     * Update category
     */
    public function updateCategory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $category = $this->passwordService->updateCategory($id, $userId, $data);

        if (!$category) {
            throw new NotFoundException('Category not found');
        }

        return JsonResponse::success($category);
    }

    /**
     * Delete category
     */
    public function deleteCategory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $success = $this->passwordService->deleteCategory($id, $userId);

        if (!$success) {
            throw new NotFoundException('Category not found');
        }

        return JsonResponse::noContent();
    }
}
