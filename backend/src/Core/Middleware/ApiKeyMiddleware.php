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
    /**
     * Resources that map to a scope namespace. GET/HEAD requires `<ns>.read`,
     * everything else requires `<ns>.write`. Wildcard `*` always satisfies.
     */
    private const SCOPE_NAMESPACES = [
        'lists', 'documents', 'kanban', 'snippets', 'bookmarks',
        'time', 'projects', 'uptime', 'storage', 'checklists', 'status',
    ];

    /**
     * Path prefixes API keys must NEVER reach, regardless of scope. These are
     * account-level controls (password, 2FA, e-mail) and meta-management
     * endpoints (auth flows, user/role admin, key admin).
     */
    private const API_KEY_FORBIDDEN_PREFIXES = [
        '/api/v1/auth',
        '/api/v1/users/me/password',
        '/api/v1/users/me/2fa',
        '/api/v1/users/me/email',
        '/api/v1/users',
        '/api/v1/roles',
        '/api/v1/permissions',
        '/api/v1/api-keys',
    ];

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

        $path = $request->getUri()->getPath();
        $method = strtoupper($request->getMethod());

        // Hard blocklist: account/admin endpoints cannot be driven via API keys
        foreach (self::API_KEY_FORBIDDEN_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return JsonResponse::forbidden('API keys are not permitted to call this endpoint');
            }
        }

        // Scope enforcement
        $requiredScope = $this->requiredScope($path, $method);
        $scopes = $keyData['scopes'] ?? [];
        if ($requiredScope !== null && !$this->apiKeyService->hasScope($scopes, $requiredScope)) {
            return JsonResponse::forbidden('API key missing required scope: ' . $requiredScope);
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
            ->withAttribute('api_key_scopes', $scopes)
            ->withAttribute('auth_type', 'api_key');

        return $handler->handle($request);
    }

    /**
     * Resolve the scope an API key must hold to call `$path` with `$method`.
     * Returns `*` for resources without a dedicated namespace — only keys with
     * the wildcard scope can reach those.
     */
    private function requiredScope(string $path, string $method): ?string
    {
        if (!preg_match('#^/api/v\d+/([a-z0-9_-]+)#', $path, $m)) {
            return '*';
        }
        $resource = $m[1];

        if (!in_array($resource, self::SCOPE_NAMESPACES, true)) {
            return '*';
        }

        $action = in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) ? 'read' : 'write';
        return $resource . '.' . $action;
    }
}
