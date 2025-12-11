<?php

declare(strict_types=1);

namespace App\Modules\Storage\Controllers;

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

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $versions,
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
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
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Version not found',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $version,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
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

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $file,
                'message' => 'Version wiederhergestellt',
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Version not found',
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $response->getBody()->write(json_encode([
                'success' => true,
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Both version1 and version2 parameters are required',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $comparison = $this->versionService->compareVersions($userId, $versionId1, $versionId2);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $comparison,
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Version not found',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        if (!file_exists($version['path'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'File not found on disk',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
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

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $settings,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Update version settings
     */
    public function updateSettings(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody() ?? [];

        $settings = $this->versionService->updateUserSettings($userId, $body);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $settings,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get version statistics
     */
    public function stats(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $stats = $this->versionService->getVersionStats($userId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $stats,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Cleanup expired versions
     */
    public function cleanup(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $deleted = $this->versionService->cleanupExpiredVersions($userId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'deleted_versions' => $deleted,
            ],
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
