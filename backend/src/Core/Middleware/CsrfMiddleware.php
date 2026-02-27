<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\JsonResponse;
use App\Core\Services\CacheService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CsrfMiddleware implements MiddlewareInterface
{
    private const TOKEN_TTL = 7200; // 2 hours
    private const HEADER_NAME = 'X-CSRF-Token';
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly CacheService $cache
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        // Safe methods don't need CSRF validation
        if (in_array($method, self::SAFE_METHODS, true)) {
            $response = $handler->handle($request);
            return $this->attachToken($request, $response);
        }

        // Skip CSRF for API key authenticated requests
        if ($request->getAttribute('auth_type') === 'api_key') {
            return $handler->handle($request);
        }

        // Skip CSRF for Bearer token auth (not auto-sent by browser)
        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return $handler->handle($request);
        }

        // For cookie-based auth, validate CSRF token
        $cookies = $request->getCookieParams();
        if (isset($cookies['access_token'])) {
            $csrfToken = $request->getHeaderLine(self::HEADER_NAME);

            if (empty($csrfToken)) {
                return JsonResponse::forbidden('CSRF token missing');
            }

            $userId = $request->getAttribute('user_id');
            if ($userId === null) {
                // Auth hasn't run yet or user not authenticated
                return $handler->handle($request);
            }

            $storedToken = $this->cache->get("csrf:{$userId}");
            if ($storedToken === null || !hash_equals((string)$storedToken, $csrfToken)) {
                return JsonResponse::forbidden('CSRF token invalid');
            }
        }

        $response = $handler->handle($request);
        return $this->attachToken($request, $response);
    }

    private function attachToken(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        if ($userId === null) {
            return $response;
        }

        $token = $this->getOrCreateToken($userId);
        return $response->withHeader(self::HEADER_NAME, $token);
    }

    private function getOrCreateToken(string $userId): string
    {
        $cacheKey = "csrf:{$userId}";
        $existing = $this->cache->get($cacheKey);

        if ($existing !== null) {
            return (string)$existing;
        }

        $token = bin2hex(random_bytes(32));
        $this->cache->set($cacheKey, $token, self::TOKEN_TTL);

        return $token;
    }
}
