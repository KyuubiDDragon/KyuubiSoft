<?php

declare(strict_types=1);

namespace App\Modules\Backup\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Backup\Services\BackupService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class BackupController
{
    public function __construct(
        private readonly BackupService $backupService
    ) {}

    // ==================== Storage Targets ====================

    /**
     * List all storage targets
     */
    public function listTargets(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $targets = $this->backupService->getStorageTargets($userId);

        return JsonResponse::success($targets);
    }

    /**
     * Get single storage target
     */
    public function getTarget(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $target = $this->backupService->getStorageTarget($args['id'], $userId);

        if (!$target) {
            return JsonResponse::error('Storage target not found', 404);
        }

        // Remove sensitive config data
        unset($target['config']);

        return JsonResponse::success($target);
    }

    /**
     * Create storage target
     */
    public function createTarget(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name']) || empty($data['type'])) {
            return JsonResponse::error('Name and type are required', 400);
        }

        $validTypes = ['local', 's3', 'sftp', 'webdav'];
        if (!in_array($data['type'], $validTypes)) {
            return JsonResponse::error('Invalid storage type', 400);
        }

        try {
            $id = $this->backupService->createStorageTarget($userId, $data);
            return JsonResponse::success(['id' => $id], 201);
        } catch (\Exception $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Update storage target
     */
    public function updateTarget(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $success = $this->backupService->updateStorageTarget($args['id'], $userId, $data);

        if (!$success) {
            return JsonResponse::error('Storage target not found or no changes made', 404);
        }

        return JsonResponse::success(['message' => 'Storage target updated']);
    }

    /**
     * Delete storage target
     */
    public function deleteTarget(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $success = $this->backupService->deleteStorageTarget($args['id'], $userId);

        if (!$success) {
            return JsonResponse::error('Storage target not found', 404);
        }

        return JsonResponse::success(['message' => 'Storage target deleted']);
    }

    /**
     * Test storage target connection
     */
    public function testTarget(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $result = $this->backupService->testStorageTarget($args['id'], $userId);

        return JsonResponse::success($result);
    }

    // ==================== Schedules ====================

    /**
     * List all schedules
     */
    public function listSchedules(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $schedules = $this->backupService->getSchedules($userId);

        return JsonResponse::success($schedules);
    }

    /**
     * Get single schedule
     */
    public function getSchedule(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $schedule = $this->backupService->getSchedule($args['id'], $userId);

        if (!$schedule) {
            return JsonResponse::error('Schedule not found', 404);
        }

        return JsonResponse::success($schedule);
    }

    /**
     * Create schedule
     */
    public function createSchedule(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name']) || empty($data['target_id'])) {
            return JsonResponse::error('Name and target_id are required', 400);
        }

        try {
            $id = $this->backupService->createSchedule($userId, $data);
            return JsonResponse::success(['id' => $id], 201);
        } catch (\Exception $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Update schedule
     */
    public function updateSchedule(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $success = $this->backupService->updateSchedule($args['id'], $userId, $data);

        if (!$success) {
            return JsonResponse::error('Schedule not found or no changes made', 404);
        }

        return JsonResponse::success(['message' => 'Schedule updated']);
    }

    /**
     * Delete schedule
     */
    public function deleteSchedule(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $success = $this->backupService->deleteSchedule($args['id'], $userId);

        if (!$success) {
            return JsonResponse::error('Schedule not found', 404);
        }

        return JsonResponse::success(['message' => 'Schedule deleted']);
    }

    // ==================== Backups ====================

    /**
     * List all backups
     */
    public function listBackups(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $result = $this->backupService->getBackups($userId, $params);

        return JsonResponse::success($result['items'], 200, $result['pagination']);
    }

    /**
     * Get single backup
     */
    public function getBackup(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $backup = $this->backupService->getBackup($args['id'], $userId);

        if (!$backup) {
            return JsonResponse::error('Backup not found', 404);
        }

        return JsonResponse::success($backup);
    }

    /**
     * Create manual backup
     */
    public function createBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['target_id'])) {
            return JsonResponse::error('target_id is required', 400);
        }

        try {
            $result = $this->backupService->createBackup($userId, $data);
            return JsonResponse::success($result, 201);
        } catch (\Exception $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Delete backup
     */
    public function deleteBackup(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $success = $this->backupService->deleteBackup($args['id'], $userId);

        if (!$success) {
            return JsonResponse::error('Backup not found', 404);
        }

        return JsonResponse::success(['message' => 'Backup deleted']);
    }

    /**
     * Restore from backup
     */
    public function restoreBackup(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        try {
            $result = $this->backupService->restoreBackup($args['id'], $userId, $data);
            return JsonResponse::success($result);
        } catch (\Exception $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Download backup file
     */
    public function downloadBackup(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $backup = $this->backupService->getBackup($args['id'], $userId);

        if (!$backup) {
            return JsonResponse::error('Backup not found', 404);
        }

        if ($backup['status'] !== 'completed' || !$backup['file_path']) {
            return JsonResponse::error('Backup file not available', 400);
        }

        if (!file_exists($backup['file_path'])) {
            return JsonResponse::error('Backup file not found on disk', 404);
        }

        $response = $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $backup['file_name'] . '"')
            ->withHeader('Content-Length', (string) filesize($backup['file_path']));

        $stream = fopen($backup['file_path'], 'rb');
        return $response->withBody(new \Slim\Psr7\Stream($stream));
    }

    // ==================== Stats ====================

    /**
     * Get backup statistics
     */
    public function getStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $stats = $this->backupService->getStats($userId);

        return JsonResponse::success($stats);
    }
}
