<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\JsonResponse;
use App\Core\Services\FeatureService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Feature Middleware - Protects routes based on feature flags
 *
 * Usage:
 * - new FeatureMiddleware('docker')           // Requires docker feature enabled (any mode)
 * - new FeatureMiddleware('docker', 'full')   // Requires docker feature with 'full' mode
 * - new FeatureMiddleware('docker', null, 'system_socket')  // Requires docker.system_socket access
 */
class FeatureMiddleware implements MiddlewareInterface
{
    private static ?FeatureService $featureService = null;

    public function __construct(
        private readonly string $feature,
        private readonly ?string $requiredMode = null,
        private readonly ?string $subFeature = null
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $featureService = self::getFeatureService();

        // Check if feature is enabled at all
        if (!$featureService->isEnabled($this->feature)) {
            return JsonResponse::create(
                ['error' => 'Feature not available', 'feature' => $this->feature],
                403,
                'This feature is not available on this instance'
            );
        }

        // Check specific mode requirement
        if ($this->requiredMode !== null && !$featureService->hasMode($this->feature, $this->requiredMode)) {
            return JsonResponse::create(
                ['error' => 'Insufficient feature access', 'feature' => $this->feature, 'required' => $this->requiredMode],
                403,
                'This action requires elevated feature access'
            );
        }

        // Check sub-feature access
        if ($this->subFeature !== null && !$featureService->checkAccess($this->feature, $this->subFeature)) {
            return JsonResponse::create(
                ['error' => 'Sub-feature not available', 'feature' => $this->feature, 'subFeature' => $this->subFeature],
                403,
                'This specific action is not available on this instance'
            );
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
}
