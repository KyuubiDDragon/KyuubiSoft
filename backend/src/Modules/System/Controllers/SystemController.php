<?php

declare(strict_types=1);

namespace App\Modules\System\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Auth\Repositories\RefreshTokenRepository;
use Doctrine\DBAL\Connection;
use Predis\Client as RedisClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SystemController
{
    public function __construct(
        private readonly Connection $db,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly ?RedisClient $redis = null
    ) {}

    public function getInfo(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Get MySQL version
        $mysqlVersion = $this->db->fetchOne('SELECT VERSION()') ?: 'Unknown';

        // Check Redis status
        $redisStatus = 'Not configured';
        if ($this->redis !== null) {
            try {
                $pong = $this->redis->ping();
                $redisStatus = ($pong === 'PONG' || $pong === true) ? 'Connected' : 'Disconnected';
            } catch (\Exception $e) {
                $redisStatus = 'Error: ' . $e->getMessage();
            }
        }

        return JsonResponse::success([
            'version' => '1.0.0',
            'environment' => getenv('APP_ENV') ?: 'production',
            'phpVersion' => PHP_VERSION,
            'mysqlVersion' => $mysqlVersion,
            'redisStatus' => $redisStatus,
        ]);
    }

    public function getMetrics(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // CPU Usage
        $cpuUsage = $this->getCpuUsage();

        // Memory Usage
        $memInfo = $this->getMemoryUsage();

        // Disk Usage
        $diskInfo = $this->getDiskUsage();

        // Database stats
        $dbStats = $this->getDatabaseStats();

        return JsonResponse::success([
            'cpu' => $cpuUsage,
            'memory' => $memInfo,
            'disk' => $diskInfo,
            'database' => $dbStats,
            'uptime' => $this->getUptime(),
            'timestamp' => date('c'),
        ]);
    }

    public function getAuditLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        // Filter options
        $action = $queryParams['action'] ?? null;
        $userId = $queryParams['user_id'] ?? null;
        $entityType = $queryParams['entity_type'] ?? null;

        $whereClause = [];
        $params = [];
        $types = [];

        if ($action) {
            $whereClause[] = 'al.action = ?';
            $params[] = $action;
            $types[] = \PDO::PARAM_STR;
        }

        if ($userId) {
            $whereClause[] = 'al.user_id = ?';
            $params[] = $userId;
            $types[] = \PDO::PARAM_STR;
        }

        if ($entityType) {
            $whereClause[] = 'al.entity_type = ?';
            $params[] = $entityType;
            $types[] = \PDO::PARAM_STR;
        }

        $where = !empty($whereClause) ? 'WHERE ' . implode(' AND ', $whereClause) : '';

        // Get logs with user info
        $sql = "SELECT al.*, u.email as user_email, u.username as user_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                {$where}
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;
        $types[] = \PDO::PARAM_INT;
        $types[] = \PDO::PARAM_INT;

        $logs = $this->db->fetchAllAssociative($sql, $params, $types);

        // Get total count
        $countSql = "SELECT COUNT(*) FROM audit_logs al {$where}";
        $countParams = array_slice($params, 0, -2);
        $countTypes = array_slice($types, 0, -2);
        $total = (int) $this->db->fetchOne($countSql, $countParams, $countTypes);

        return JsonResponse::paginated($logs, $total, $page, $perPage);
    }

    public function clearCache(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $cleared = [];

        // Clear Redis cache if available
        if ($this->redis !== null) {
            try {
                $this->redis->flushdb();
                $cleared[] = 'Redis';
            } catch (\Exception $e) {
                // Redis failed, continue with other caches
            }
        }

        // Clear OPCache if available
        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $cleared[] = 'OPCache';
        }

        // Clear any file-based caches
        $cacheDir = dirname(__DIR__, 4) . '/var/cache';
        if (is_dir($cacheDir)) {
            $this->clearDirectory($cacheDir);
            $cleared[] = 'File Cache';
        }

        // Log the action
        $this->logAction(
            $request->getAttribute('user_id'),
            'cache.clear',
            'system',
            null,
            $request,
            null,
            ['cleared' => $cleared]
        );

        if (empty($cleared)) {
            return JsonResponse::success(null, 'No caches configured');
        }

        return JsonResponse::success(
            ['cleared' => $cleared],
            'Cache cleared: ' . implode(', ', $cleared)
        );
    }

    private function clearDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }
    }

    public function terminateSessions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $currentUserId = $request->getAttribute('user_id');

            // Get all user IDs except current user
            $users = $this->db->fetchAllAssociative(
                'SELECT id FROM users WHERE id != ?',
                [$currentUserId]
            );

            $terminated = 0;
            foreach ($users as $user) {
                $this->refreshTokenRepository->revokeAllForUser($user['id']);
                $terminated++;
            }

            // Log the action
            $this->logAction(
                $currentUserId,
                'sessions.terminate_all',
                'system',
                null,
                $request,
                null,
                ['terminated_users' => $terminated]
            );

            return JsonResponse::success(
                ['terminated_users' => $terminated],
                "Terminated sessions for {$terminated} users"
            );
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to terminate sessions: ' . $e->getMessage(), 500);
        }
    }

    private function getCpuUsage(): array
    {
        $load = sys_getloadavg();

        // Get number of CPU cores
        $cpuCores = 1;
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            $cpuCores = substr_count($cpuinfo, 'processor');
        }

        $loadPercentage = min(100, round(($load[0] / $cpuCores) * 100, 1));

        return [
            'load_1m' => round($load[0], 2),
            'load_5m' => round($load[1], 2),
            'load_15m' => round($load[2], 2),
            'cores' => $cpuCores,
            'usage_percent' => $loadPercentage,
        ];
    }

    private function getMemoryUsage(): array
    {
        $total = 0;
        $free = 0;
        $available = 0;
        $buffers = 0;
        $cached = 0;

        if (is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');

            if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches)) {
                $total = (int) $matches[1] * 1024;
            }
            if (preg_match('/MemFree:\s+(\d+)\s+kB/', $meminfo, $matches)) {
                $free = (int) $matches[1] * 1024;
            }
            if (preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $matches)) {
                $available = (int) $matches[1] * 1024;
            }
            if (preg_match('/Buffers:\s+(\d+)\s+kB/', $meminfo, $matches)) {
                $buffers = (int) $matches[1] * 1024;
            }
            if (preg_match('/Cached:\s+(\d+)\s+kB/', $meminfo, $matches)) {
                $cached = (int) $matches[1] * 1024;
            }
        }

        $used = $total - $available;
        $usagePercent = $total > 0 ? round(($used / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'available' => $available,
            'buffers' => $buffers,
            'cached' => $cached,
            'usage_percent' => $usagePercent,
            'total_formatted' => $this->formatBytes($total),
            'used_formatted' => $this->formatBytes($used),
            'available_formatted' => $this->formatBytes($available),
        ];
    }

    private function getDiskUsage(): array
    {
        $path = '/';
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $used = $total - $free;
        $usagePercent = $total > 0 ? round(($used / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'usage_percent' => $usagePercent,
            'total_formatted' => $this->formatBytes($total),
            'used_formatted' => $this->formatBytes($used),
            'free_formatted' => $this->formatBytes($free),
            'path' => $path,
        ];
    }

    private function getDatabaseStats(): array
    {
        $stats = [];

        // Users count
        $stats['users'] = (int) $this->db->fetchOne('SELECT COUNT(*) FROM users');

        // Active users (last 24h)
        $stats['active_users_24h'] = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM users WHERE last_login_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );

        // Lists count
        $stats['lists'] = (int) $this->db->fetchOne('SELECT COUNT(*) FROM lists');

        // Documents count
        $stats['documents'] = (int) $this->db->fetchOne('SELECT COUNT(*) FROM documents');

        // Audit logs count
        $stats['audit_logs'] = (int) $this->db->fetchOne('SELECT COUNT(*) FROM audit_logs');

        return $stats;
    }

    private function getUptime(): string
    {
        if (is_readable('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $seconds = (int) explode(' ', $uptime)[0];

            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            $minutes = floor(($seconds % 3600) / 60);

            $parts = [];
            if ($days > 0) $parts[] = "{$days}d";
            if ($hours > 0) $parts[] = "{$hours}h";
            if ($minutes > 0) $parts[] = "{$minutes}m";

            return implode(' ', $parts) ?: '< 1m';
        }

        return 'Unknown';
    }

    private function formatBytes(float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function logAction(
        ?string $userId,
        string $action,
        string $entityType,
        ?string $entityId,
        ServerRequestInterface $request,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $this->db->insert('audit_logs', [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $this->getClientIp($request),
            'user_agent' => substr($request->getHeaderLine('User-Agent'), 0, 500),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function getClientIp(ServerRequestInterface $request): ?string
    {
        $serverParams = $request->getServerParams();

        // Check for forwarded IP
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $serverParams['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($serverParams['HTTP_X_REAL_IP'])) {
            return $serverParams['HTTP_X_REAL_IP'];
        }

        return $serverParams['REMOTE_ADDR'] ?? null;
    }
}
