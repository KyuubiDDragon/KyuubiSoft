<?php

declare(strict_types=1);

namespace App\Modules\Logs\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

class LogsController
{
    private string $encryptionKey;

    public function __construct(
        private readonly Connection $db,
    ) {
        $this->encryptionKey = $_ENV['APP_KEY'] ?? 'default-key-change-me';
    }

    /**
     * Fetch logs from a Docker container via the existing Docker module logic.
     * This is a proxy/aggregation endpoint for the log viewer.
     *
     * GET /logs/docker/{hostId}/{containerId}?tail=200
     */
    public function dockerContainerLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId      = $request->getAttribute('user_id');
        $route       = RouteContext::fromRequest($request)->getRoute();
        $hostId      = $route->getArgument('hostId');
        $containerId = $route->getArgument('containerId');
        $queryParams = $request->getQueryParams();
        $tail        = max(50, min(2000, (int) ($queryParams['tail'] ?? 200)));

        // Load Docker host
        $host = $this->db->fetchAssociative(
            'SELECT * FROM docker_hosts WHERE id = ? AND user_id = ?',
            [$hostId, $userId]
        );

        if (!$host) {
            return JsonResponse::error('Docker host not found', 404);
        }

        try {
            $logs = $this->fetchDockerLogs($host, $containerId, $tail);
            return JsonResponse::success(['logs' => $logs, 'source' => 'docker', 'container_id' => $containerId]);
        } catch (\Throwable $e) {
            return JsonResponse::error('Failed to fetch logs: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List available log files on the backend container (local logs).
     *
     * GET /logs/local/files
     */
    public function listLocalFiles(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $logDirs = [
            '/var/log'           => 'System',
            '/app/storage/logs'  => 'Application',
        ];

        $files = [];

        foreach ($logDirs as $dir => $label) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS);
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['log', 'txt', ''], true)) {
                    $files[] = [
                        'path'      => $file->getPathname(),
                        'name'      => $file->getFilename(),
                        'label'     => $label,
                        'size'      => $file->getSize(),
                        'modified'  => date('Y-m-d H:i:s', $file->getMTime()),
                    ];
                }
            }
        }

        usort($files, fn($a, $b) => strcmp($a['path'], $b['path']));

        return JsonResponse::success(['items' => $files]);
    }

    /**
     * Read the tail of a local log file.
     *
     * GET /logs/local/read?path=/var/log/syslog&lines=200
     */
    public function readLocalFile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $path        = $queryParams['path'] ?? '';
        $lines       = max(50, min(2000, (int) ($queryParams['lines'] ?? 200)));

        // Security: only allow reading from whitelisted directories
        $allowedPrefixes = ['/var/log/', '/app/storage/logs/'];
        $safe = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with(realpath($path) ?: $path, $prefix)) {
                $safe = true;
                break;
            }
        }

        if (!$safe || !is_file($path)) {
            return JsonResponse::error('File not accessible', 403);
        }

        // Read last N lines efficiently
        $logLines = $this->tailFile($path, $lines);

        return JsonResponse::success([
            'path'  => $path,
            'lines' => $logLines,
            'count' => count($logLines),
        ]);
    }

    /**
     * List available Docker hosts for the log viewer.
     */
    public function listDockerHosts(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $hosts = $this->db->fetchAllAssociative(
            'SELECT id, name, type, tcp_host, tcp_port FROM docker_hosts WHERE user_id = ? ORDER BY name',
            [$userId]
        );

        return JsonResponse::success(['items' => $hosts]);
    }

    /**
     * List containers for a Docker host.
     */
    public function listDockerContainers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId  = $request->getAttribute('user_id');
        $hostId  = RouteContext::fromRequest($request)->getRoute()->getArgument('hostId');

        $host = $this->db->fetchAssociative(
            'SELECT * FROM docker_hosts WHERE id = ? AND user_id = ?',
            [$hostId, $userId]
        );

        if (!$host) {
            return JsonResponse::error('Docker host not found', 404);
        }

        try {
            $dockerUrl = $this->getDockerApiUrl($host);
            $client    = new HttpClient(['timeout' => 10, 'verify' => false]);
            $res       = $client->get($dockerUrl . '/containers/json?all=true');
            $containers = json_decode($res->getBody()->getContents(), true);

            $simplified = array_map(fn($c) => [
                'id'    => substr($c['Id'], 0, 12),
                'name'  => ltrim($c['Names'][0] ?? $c['Id'], '/'),
                'image' => $c['Image'],
                'state' => $c['State'],
            ], $containers);

            return JsonResponse::success(['items' => $simplified]);
        } catch (\Throwable $e) {
            return JsonResponse::error('Failed to list containers: ' . $e->getMessage(), 500);
        }
    }

    // =========================================================================

    private function fetchDockerLogs(array $host, string $containerId, int $tail): array
    {
        $dockerUrl = $this->getDockerApiUrl($host);
        $client    = new HttpClient(['timeout' => 15, 'verify' => false]);
        $res       = $client->get($dockerUrl . "/containers/{$containerId}/logs?stdout=true&stderr=true&tail={$tail}&timestamps=true");

        $rawLogs = $res->getBody()->getContents();

        // Docker multiplexed stream: 8-byte header per line
        $lines = [];
        $pos   = 0;
        $len   = strlen($rawLogs);

        while ($pos < $len) {
            if ($pos + 8 > $len) break;
            $streamType = ord($rawLogs[$pos]);
            $lineLen    = unpack('N', substr($rawLogs, $pos + 4, 4))[1];
            $pos       += 8;

            if ($pos + $lineLen > $len) break;
            $line  = substr($rawLogs, $pos, $lineLen);
            $pos  += $lineLen;

            // Parse timestamp (first 30 chars ISO8601)
            $timestamp = '';
            $message   = $line;
            if (preg_match('/^(\d{4}-\d{2}-\d{2}T[\d:.]+Z)\s(.*)$/s', $line, $m)) {
                $timestamp = $m[1];
                $message   = rtrim($m[2], "\r\n");
            }

            $lines[] = [
                'timestamp' => $timestamp,
                'stream'    => $streamType === 2 ? 'stderr' : 'stdout',
                'message'   => $message,
                'level'     => $this->detectLevel($message),
            ];
        }

        // Fallback: split by newlines if multiplexed parsing yielded nothing
        if (empty($lines)) {
            foreach (explode("\n", $rawLogs) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $lines[] = ['timestamp' => '', 'stream' => 'stdout', 'message' => $line, 'level' => $this->detectLevel($line)];
            }
        }

        return $lines;
    }

    private function getDockerApiUrl(array $host): string
    {
        if ($host['type'] === 'socket') {
            return 'http://localhost';
        }
        $proto = ($host['tls_enabled'] ?? false) ? 'https' : 'http';
        return "{$proto}://{$host['tcp_host']}:{$host['tcp_port']}";
    }

    private function detectLevel(string $message): string
    {
        $upper = strtoupper($message);
        foreach (['CRITICAL', 'FATAL', 'ERROR', 'WARN', 'WARNING', 'INFO', 'DEBUG', 'TRACE'] as $level) {
            if (str_contains($upper, $level)) {
                return match ($level) {
                    'CRITICAL', 'FATAL' => 'critical',
                    'ERROR'             => 'error',
                    'WARN', 'WARNING'   => 'warning',
                    'DEBUG', 'TRACE'    => 'debug',
                    default             => 'info',
                };
            }
        }
        return 'info';
    }

    private function tailFile(string $path, int $lines): array
    {
        $output = [];
        $file   = new \SplFileObject($path);
        $file->seek(PHP_INT_MAX);
        $total = $file->key();

        $start = max(0, $total - $lines);
        $file->seek($start);

        while (!$file->eof()) {
            $line = rtrim($file->fgets(), "\r\n");
            if ($line !== '') {
                $output[] = [
                    'message' => $line,
                    'level'   => $this->detectLevel($line),
                ];
            }
        }

        return $output;
    }
}
