<?php

declare(strict_types=1);

namespace App\Modules\Links\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Links\Services\LinkService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

class LinkController
{
    public function __construct(
        private readonly LinkService $linkService
    ) {}

    /**
     * Extract route argument from request
     */
    private function getRouteArg(ServerRequestInterface $request, string $name): ?string
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        return $route ? $route->getArgument($name) : null;
    }

    /**
     * List all links
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $result = $this->linkService->getLinks($userId, $params);

        return JsonResponse::success([
            'items' => $result['items'],
            'pagination' => $result['pagination']
        ]);
    }

    /**
     * Get a single link
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $link = $this->linkService->getLink($id, $userId);

        if (!$link) {
            return JsonResponse::error('Link not found', 404);
        }

        return JsonResponse::success($link);
    }

    /**
     * Create a new link
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['url'])) {
            return JsonResponse::error('URL is required', 400);
        }

        // Validate URL
        if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            return JsonResponse::error('Invalid URL format', 400);
        }

        try {
            $link = $this->linkService->createLink($userId, $data);
            return JsonResponse::created($link);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Update a link
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody() ?? [];

        $success = $this->linkService->updateLink($id, $userId, $data);

        if (!$success) {
            return JsonResponse::error('Link not found or no changes made', 404);
        }

        return JsonResponse::success(['message' => 'Link updated']);
    }

    /**
     * Delete a link
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $success = $this->linkService->deleteLink($id, $userId);

        if (!$success) {
            return JsonResponse::error('Link not found', 404);
        }

        return JsonResponse::success(['message' => 'Link deleted']);
    }

    /**
     * Get link statistics
     */
    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $params = $request->getQueryParams();
        $days = (int) ($params['days'] ?? 30);

        $stats = $this->linkService->getStats($id, $userId, $days);

        if (empty($stats)) {
            return JsonResponse::error('Link not found', 404);
        }

        return JsonResponse::success($stats);
    }

    /**
     * Get user statistics
     */
    public function userStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $stats = $this->linkService->getUserStats($userId);

        return JsonResponse::success($stats);
    }

    /**
     * Public redirect endpoint
     */
    public function redirect(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $code = $this->getRouteArg($request, 'code');
        $isJsonRequest = str_contains($request->getHeaderLine('Content-Type'), 'application/json');

        // Check if password is required
        if ($this->linkService->requiresPassword($code)) {
            $data = $request->getParsedBody() ?? [];
            $password = $data['password'] ?? $request->getQueryParams()['p'] ?? '';

            if (empty($password)) {
                return JsonResponse::error('Password required', 401, ['requires_password' => true]);
            }

            if (!$this->linkService->verifyPassword($code, $password)) {
                return JsonResponse::error('Invalid password', 401);
            }
        }

        // Get request info for analytics
        $requestInfo = [
            'ip' => $this->getClientIp($request),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'referrer' => $request->getHeaderLine('Referer'),
        ];

        $originalUrl = $this->linkService->recordClick($code, $requestInfo);

        if (!$originalUrl) {
            return JsonResponse::error('Link not found or expired', 404);
        }

        // For JSON requests (from frontend), return URL as JSON
        // This allows the frontend to handle the redirect
        if ($isJsonRequest) {
            return JsonResponse::success(['original_url' => $originalUrl]);
        }

        // For browser requests, return 302 redirect
        return $response
            ->withStatus(302)
            ->withHeader('Location', $originalUrl);
    }

    /**
     * Get link info (public - for password check and direct redirect)
     */
    public function getLinkInfo(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $code = $this->getRouteArg($request, 'code');
        $link = $this->linkService->getLinkByCode($code);

        if (!$link) {
            return JsonResponse::error('Link not found', 404);
        }

        // Check if active
        if (!$link['is_active']) {
            return JsonResponse::error('Link is inactive', 410);
        }

        // Check if expired
        if ($link['expires_at'] && strtotime($link['expires_at']) < time()) {
            return JsonResponse::error('Link expired', 410);
        }

        // Check max clicks
        if ($link['max_clicks'] && $link['click_count'] >= $link['max_clicks']) {
            return JsonResponse::error('Link limit reached', 410);
        }

        $requiresPassword = !empty($link['password_hash']);

        // If no password required, return the original URL and record click
        if (!$requiresPassword) {
            // Record the click
            $requestInfo = [
                'ip' => $this->getClientIp($request),
                'user_agent' => $request->getHeaderLine('User-Agent'),
                'referrer' => $request->getHeaderLine('Referer'),
            ];
            $this->linkService->recordClick($code, $requestInfo);

            return JsonResponse::success([
                'title' => $link['title'],
                'requires_password' => false,
                'original_url' => $link['original_url'],
            ]);
        }

        // Password required - don't return URL yet
        return JsonResponse::success([
            'title' => $link['title'],
            'requires_password' => true,
        ]);
    }

    /**
     * Generate QR code for link
     */
    public function qrCode(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $link = $this->linkService->getLink($id, $userId);

        if (!$link) {
            return JsonResponse::error('Link not found', 404);
        }

        $params = $request->getQueryParams();
        $size = min(500, max(100, (int) ($params['size'] ?? 200)));

        // Generate QR code URL using QR code API
        $shortUrl = $this->getShortUrl($link['short_code']);
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($shortUrl);

        return JsonResponse::success([
            'qr_url' => $qrUrl,
            'short_url' => $shortUrl,
        ]);
    }

    // ==================== Helper Methods ====================

    private function getClientIp(ServerRequestInterface $request): string
    {
        $headers = ['X-Forwarded-For', 'X-Real-IP', 'CF-Connecting-IP'];

        foreach ($headers as $header) {
            $value = $request->getHeaderLine($header);
            if (!empty($value)) {
                $ips = explode(',', $value);
                return trim($ips[0]);
            }
        }

        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? '';
    }

    private function getShortUrl(string $code): string
    {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        return rtrim($baseUrl, '/') . '/s/' . $code;
    }
}
