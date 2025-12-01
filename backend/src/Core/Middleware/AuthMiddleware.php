<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Security\JwtManager;
use App\Core\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly JwtManager $jwtManager
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return JsonResponse::unauthorized('Authentication required');
        }

        try {
            $payload = $this->jwtManager->validateAccessToken($token);

            // Add user info to request attributes
            $request = $request
                ->withAttribute('user_id', $payload->sub)
                ->withAttribute('user_email', $payload->email ?? null)
                ->withAttribute('user_roles', $payload->roles ?? [])
                ->withAttribute('user_permissions', $payload->permissions ?? [])
                ->withAttribute('jwt_payload', $payload);

            return $handler->handle($request);
        } catch (\Exception $e) {
            return JsonResponse::unauthorized('Invalid or expired token');
        }
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        // Also check for token in cookie
        $cookies = $request->getCookieParams();
        if (isset($cookies['access_token'])) {
            return $cookies['access_token'];
        }

        return null;
    }
}
