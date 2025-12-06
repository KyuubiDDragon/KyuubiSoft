<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\JsonResponse;
use App\Core\Security\RbacManager;
use App\Core\Services\FeatureService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Feature Middleware - Protects routes based on feature flags and permissions
 *
 * Two-layer access control:
 * 1. Instance-Level (ENV): Is this feature enabled on this instance?
 * 2. User-Level (Permission): Does the user have permission to access this feature?
 *
 * Usage:
 * - new FeatureMiddleware('docker')                     // Feature enabled + user has docker.view
 * - new FeatureMiddleware('docker', 'full')             // Feature has 'full' mode
 * - new FeatureMiddleware('docker', null, 'system_socket')  // Sub-feature check
 * - new FeatureMiddleware('docker', null, null, false)  // Skip permission check (ENV only)
 */
class FeatureMiddleware implements MiddlewareInterface
{
    private static ?FeatureService $featureService = null;
    private static ?RbacManager $rbacManager = null;

    public function __construct(
        private readonly string $feature,
        private readonly ?string $requiredMode = null,
        private readonly ?string $subFeature = null,
        private readonly bool $checkPermission = true
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $featureService = self::getFeatureService();

        // 1. Check if feature is enabled at instance level
        if (!$featureService->isEnabled($this->feature)) {
            return JsonResponse::create(
                ['error' => 'Feature not available', 'feature' => $this->feature],
                403,
                'This feature is not available on this instance'
            );
        }

        // 2. Check specific mode requirement (if specified)
        if ($this->requiredMode !== null && !$featureService->hasMode($this->feature, $this->requiredMode)) {
            return JsonResponse::create(
                ['error' => 'Insufficient feature access', 'feature' => $this->feature, 'required' => $this->requiredMode],
                403,
                'This action requires elevated feature access'
            );
        }

        // 3. Check sub-feature access at instance level (if specified)
        if ($this->subFeature !== null && !$featureService->isSubFeatureAllowed($this->feature, $this->subFeature)) {
            return JsonResponse::create(
                ['error' => 'Sub-feature not available', 'feature' => $this->feature, 'subFeature' => $this->subFeature],
                403,
                'This specific action is not available on this instance'
            );
        }

        // 4. Check user permission (if enabled and user is authenticated)
        if ($this->checkPermission) {
            $userId = $request->getAttribute('user_id');

            if ($userId !== null) {
                $rbac = self::getRbacManager();

                if ($rbac !== null) {
                    // Build permission name
                    $permission = $this->subFeature
                        ? "{$this->feature}.{$this->subFeature}"
                        : "{$this->feature}.view";

                    if (!$rbac->hasPermission($userId, $permission)) {
                        return JsonResponse::create(
                            ['error' => 'Permission denied', 'permission' => $permission],
                            403,
                            'You do not have permission to access this feature'
                        );
                    }
                }
            }
        }

        return $handler->handle($request);
    }

    private static function getFeatureService(): FeatureService
    {
        if (self::$featureService === null) {
            self::$featureService = new FeatureService();
        }
        return self::$featureService;
    }

    private static function getRbacManager(): ?RbacManager
    {
        if (self::$rbacManager === null) {
            // Try to get from container if available
            global $container;
            if ($container !== null && $container->has(RbacManager::class)) {
                self::$rbacManager = $container->get(RbacManager::class);
            }
        }
        return self::$rbacManager;
    }

    /**
     * Set the RBAC manager (for testing or manual injection)
     */
    public static function setRbacManager(?RbacManager $rbac): void
    {
        self::$rbacManager = $rbac;
    }

    /**
     * Set the Feature service (for testing or manual injection)
     */
    public static function setFeatureService(?FeatureService $service): void
    {
        self::$featureService = $service;
    }
}
