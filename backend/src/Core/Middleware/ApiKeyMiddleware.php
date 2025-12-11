<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\JsonResponse;
use App\Modules\ApiKeys\Services\ApiKeyService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware to authenticate requests using API keys
 * Falls back to JWT authentication if no API key is provided
 */
class ApiKeyMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check for API key in header
        $apiKey = $request->getHeaderLine('X-API-Key');

        if (empty($apiKey)) {
            // No API key, let other middleware handle auth (JWT)
            return $handler->handle($request);
        }

        // Validate API key
        $keyData = $this->apiKeyService->validate($apiKey);

        if (!$keyData) {
            return JsonResponse::unauthorized('Invalid or expired API key');
        }

        // Update last used IP
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        if ($ip) {
            $this->apiKeyService->updateLastUsedIp($keyData['key_id'], $ip);
        }

        // Attach user info to request
        $request = $request
            ->withAttribute('user_id', $keyData['user_id'])
            ->withAttribute('user_email', $keyData['email'])
            ->withAttribute('api_key_id', $keyData['key_id'])
            ->withAttribute('api_key_scopes', $keyData['scopes'])
            ->withAttribute('auth_type', 'api_key');

        return $handler->handle($request);
    }
}
