<?php

declare(strict_types=1);

namespace App\Modules\System\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Services\FeatureService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FeaturesController
{
    public function __construct(
        private readonly FeatureService $featureService
    ) {}

    /**
     * Get all enabled features for the frontend
     * This endpoint is public (no auth required) so the frontend can check features before login
     */
    public function getFeatures(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponse::success([
            'features' => $this->featureService->getEnabledFeatures(),
            'details' => $this->featureService->getAllFeatures(),
        ]);
    }
}
