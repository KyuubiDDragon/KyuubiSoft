<?php

declare(strict_types=1);

namespace App\Modules\Storage\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Storage\Services\FileVersionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FileVersionController
{
    public function __construct(
        private FileVersionService $versionService
    ) {}

    /**
     * Get all versions of a file
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $fileId = $args['fileId'];

        try {
            $versions = $this->versionService->getVersions($userId, $fileId);

            return JsonResponse::success($versions, 'Success');
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::notFound($e->getMessage());
        }
    }

    /**
     * Get a specific version
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $versionId = $args['id'];

        $version = $this->versionService->getVersion($userId, $versionId);

        if (!$version) {
            return JsonResponse::notFound('Version not found');
        }

        return JsonResponse::success($version, 'Success');
    }

    /**
     * Restore a specific version
     */
    public function restore(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $versionId = $args['id'];

        try {
            $file = $this->versionService->restoreVersion($userId, $versionId);

            return JsonResponse::success($file, 'Version wiederhergestellt');
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Delete a specific version
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $versionId = $args['id'];

        try {
            $deleted = $this->versionService->deleteVersion($userId, $versionId);

            if (!$deleted) {
                return JsonResponse::notFound('Version not found');
            }

            return JsonResponse::success(null, 'Success');
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Compare two versions
     */
    public function compare(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $versionId1 = $params['version1'] ?? null;
        $versionId2 = $params['version2'] ?? null;

        if (!$versionId1 || !$versionId2) {
            return JsonResponse::error('Both version1 and version2 parameters are required', 400);
        }

        try {
            $comparison = $this->versionService->compareVersions($userId, $versionId1, $versionId2);

            return JsonResponse::success($comparison, 'Success');
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Download a specific version
     */
    public function download(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $versionId = $args['id'];

        $version = $this->versionService->getVersion($userId, $versionId);

        if (!$version) {
            return JsonResponse::notFound('Version not found');
        }

        if (!file_exists($version['path'])) {
            return JsonResponse::notFound('File not found on disk');
        }

        $stream = fopen($version['path'], 'rb');

        return $response
            ->withHeader('Content-Type', $version['mime_type'] ?? 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $version['original_name'] . '"')
            ->withHeader('Content-Length', (string) $version['size'])
            ->withBody(new \Slim\Psr7\Stream($stream));
    }

    /**
     * Get version settings
     */
    public function getSettings(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $settings = $this->versionService->getUserSettings($userId);

        return JsonResponse::success($settings, 'Success');
    }

    /**
     * Update version settings
     */
    public function updateSettings(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody() ?? [];

        $settings = $this->versionService->updateUserSettings($userId, $body);

        return JsonResponse::success($settings, 'Success');
    }

    /**
     * Get version statistics
     */
    public function stats(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $stats = $this->versionService->getVersionStats($userId);

        return JsonResponse::success($stats, 'Success');
    }

    /**
     * Cleanup expired versions
     */
    public function cleanup(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $deleted = $this->versionService->cleanupExpiredVersions($userId);

        return JsonResponse::success([
            'deleted_versions' => $deleted,
        ], 'Success');
    }
}
