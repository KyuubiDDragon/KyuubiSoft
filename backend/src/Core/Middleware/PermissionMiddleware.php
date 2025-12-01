<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $requiredPermission
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userPermissions = $request->getAttribute('user_permissions', []);
        $userRoles = $request->getAttribute('user_roles', []);

        // Owners have all permissions
        if (in_array('owner', $userRoles, true)) {
            return $handler->handle($request);
        }

        // Check for specific permission
        if (in_array($this->requiredPermission, $userPermissions, true)) {
            return $handler->handle($request);
        }

        // Check for wildcard permission (e.g., "users.*" grants "users.read")
        $permissionParts = explode('.', $this->requiredPermission);
        if (count($permissionParts) >= 2) {
            $wildcardPermission = $permissionParts[0] . '.*';
            if (in_array($wildcardPermission, $userPermissions, true)) {
                return $handler->handle($request);
            }
        }

        return JsonResponse::forbidden('Insufficient permissions');
    }
}
