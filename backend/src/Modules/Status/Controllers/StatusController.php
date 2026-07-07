<?php

declare(strict_types=1);

namespace App\Modules\Status\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Status\Services\StatusService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

/**
 * Read-only status endpoints for external integrations (Alexa skill,
 * dashboards, n8n, ...). Reachable with a JWT (web UI) or an API key carrying
 * the `status.read` scope.
 *
 * All data is LOCAL (local Docker daemon + local host). Uptime/SSL/cron are
 * scoped to the authenticated user.
 */
class StatusController
{
    public function __construct(
        private readonly StatusService $statusService
    ) {}

    /**
     * GET /status/overview — one compact snapshot for a voice/dashboard summary.
     */
    public function overview(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        try {
            return JsonResponse::success($this->statusService->getOverview($userId));
        } catch (\Throwable $e) {
            return JsonResponse::error('Failed to build status overview: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /status/server — local host metrics (CPU, RAM, disks, load, uptime).
     */
    public function server(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            return JsonResponse::success($this->statusService->getServer());
        } catch (\Throwable $e) {
            return JsonResponse::error('Failed to read server status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /status/containers — local Docker containers with live CPU/RAM.
     */
    public function containers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            return JsonResponse::success($this->statusService->getContainers());
        } catch (\Throwable $e) {
            return JsonResponse::error('Failed to read containers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /status/services — uptime monitors, SSL certificates and cron jobs.
     */
    public function services(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        try {
            return JsonResponse::success($this->statusService->getServices($userId));
        } catch (\Throwable $e) {
            return JsonResponse::error('Failed to read services: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /status/containers/{name}/{action} — control a local container.
     * Write operation: API keys need the `status.write` scope.
     */
    public function control(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $route = RouteContext::fromRequest($request)->getRoute();
        $name = (string) ($route?->getArgument('name') ?? '');
        $action = (string) ($route?->getArgument('action') ?? '');
        try {
            $result = $this->statusService->controlContainer($name, $action);
            if (!$result['ok']) {
                return JsonResponse::error($result['message'], 400);
            }
            return JsonResponse::success(['message' => $result['message']]);
        } catch (\Throwable $e) {
            return JsonResponse::error('Failed to control container: ' . $e->getMessage(), 500);
        }
    }
}
