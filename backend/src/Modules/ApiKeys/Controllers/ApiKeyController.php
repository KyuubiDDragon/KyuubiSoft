<?php

declare(strict_types=1);

namespace App\Modules\ApiKeys\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Exceptions\ValidationException;
use App\Core\Exceptions\NotFoundException;
use App\Modules\ApiKeys\Services\ApiKeyService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

class ApiKeyController
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {}

    /**
     * List all API keys for the current user
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $keys = $this->apiKeyService->getByUser($userId);

        // Parse scopes JSON for each key
        foreach ($keys as &$key) {
            $key['scopes'] = json_decode($key['scopes'], true);
        }

        return JsonResponse::success([
            'items' => $keys,
            'available_scopes' => ApiKeyService::getAvailableScopes(),
        ]);
    }

    /**
     * Create a new API key
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name'])) {
            throw new ValidationException('Name is required');
        }

        if (empty($data['scopes']) || !is_array($data['scopes'])) {
            throw new ValidationException('At least one scope is required');
        }

        // Validate scopes
        $availableScopes = array_keys(ApiKeyService::getAvailableScopes());
        foreach ($data['scopes'] as $scope) {
            if (!in_array($scope, $availableScopes, true) && $scope !== '*') {
                throw new ValidationException("Invalid scope: {$scope}");
            }
        }

        $expiresAt = null;
        if (!empty($data['expires_in_days'])) {
            $days = (int) $data['expires_in_days'];
            if ($days > 0) {
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            }
        }

        $result = $this->apiKeyService->generate(
            $userId,
            $data['name'],
            $data['scopes'],
            $expiresAt
        );

        return JsonResponse::created($result, 'API key created. Save the key now - it won\'t be shown again!');
    }

    /**
     * Update an API key
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $keyId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['scopes']) && is_array($data['scopes'])) {
            $updateData['scopes'] = $data['scopes'];
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = (bool) $data['is_active'];
        }

        $result = $this->apiKeyService->update($keyId, $userId, $updateData);

        if (!$result) {
            throw new NotFoundException('API key not found');
        }

        $result['scopes'] = json_decode($result['scopes'], true);

        return JsonResponse::success($result, 'API key updated');
    }

    /**
     * Revoke an API key (soft delete)
     */
    public function revoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $keyId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $success = $this->apiKeyService->revoke($keyId, $userId);

        if (!$success) {
            throw new NotFoundException('API key not found');
        }

        return JsonResponse::success(null, 'API key revoked');
    }

    /**
     * Delete an API key permanently
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $keyId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $success = $this->apiKeyService->delete($keyId, $userId);

        if (!$success) {
            throw new NotFoundException('API key not found');
        }

        return JsonResponse::noContent();
    }
}
