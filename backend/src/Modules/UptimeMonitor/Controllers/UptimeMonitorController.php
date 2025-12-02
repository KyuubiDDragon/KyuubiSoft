<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class UptimeMonitorController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $monitors = $this->db->fetchAllAssociative(
            'SELECT * FROM uptime_monitors WHERE user_id = ? ORDER BY name',
            [$userId]
        );

        // Add recent checks for each monitor
        foreach ($monitors as &$monitor) {
            $monitor['recent_checks'] = $this->db->fetchAllAssociative(
                'SELECT status, response_time, checked_at FROM uptime_checks
                 WHERE monitor_id = ? ORDER BY checked_at DESC LIMIT 30',
                [$monitor['id']]
            );
        }

        return JsonResponse::success(['items' => $monitors]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name']) || empty($data['url'])) {
            throw new ValidationException('Name and URL are required');
        }

        $url = filter_var($data['url'], FILTER_VALIDATE_URL);
        if (!$url) {
            throw new ValidationException('Invalid URL format');
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('uptime_monitors', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'url' => $url,
            'type' => $data['type'] ?? 'https',
            'check_interval' => $data['check_interval'] ?? 300,
            'timeout' => $data['timeout'] ?? 30,
            'expected_status_code' => $data['expected_status_code'] ?? 200,
            'expected_keyword' => $data['expected_keyword'] ?? null,
            'notify_on_down' => !empty($data['notify_on_down']) ? 1 : 0,
            'notify_on_recovery' => !empty($data['notify_on_recovery']) ? 1 : 0,
            'is_active' => 1,
            'current_status' => 'pending',
        ]);

        $monitor = $this->db->fetchAssociative('SELECT * FROM uptime_monitors WHERE id = ?', [$id]);

        return JsonResponse::created($monitor, 'Monitor created');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $monitor = $this->db->fetchAssociative(
            'SELECT * FROM uptime_monitors WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$monitor) {
            throw new NotFoundException('Monitor not found');
        }

        // Get recent checks
        $monitor['recent_checks'] = $this->db->fetchAllAssociative(
            'SELECT * FROM uptime_checks WHERE monitor_id = ? ORDER BY checked_at DESC LIMIT 100',
            [$id]
        );

        // Get incidents
        $monitor['incidents'] = $this->db->fetchAllAssociative(
            'SELECT * FROM uptime_incidents WHERE monitor_id = ? ORDER BY started_at DESC LIMIT 10',
            [$id]
        );

        // Calculate uptime stats for different periods
        $monitor['stats'] = [
            '24h' => $this->calculateUptime($id, '-24 hours'),
            '7d' => $this->calculateUptime($id, '-7 days'),
            '30d' => $this->calculateUptime($id, '-30 days'),
        ];

        return JsonResponse::success($monitor);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $monitor = $this->db->fetchAssociative(
            'SELECT * FROM uptime_monitors WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$monitor) {
            throw new NotFoundException('Monitor not found');
        }

        $updates = [];
        $params = [];

        $fields = ['name', 'url', 'type', 'check_interval', 'timeout', 'expected_status_code', 'expected_keyword'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        $boolFields = ['notify_on_down', 'notify_on_recovery', 'is_active', 'is_paused'];
        foreach ($boolFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field] ? 1 : 0;
            }
        }

        if (!empty($updates)) {
            $params[] = $id;
            $this->db->executeStatement(
                'UPDATE uptime_monitors SET ' . implode(', ', $updates) . ' WHERE id = ?',
                $params
            );
        }

        return JsonResponse::success(null, 'Monitor updated');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $monitor = $this->db->fetchAssociative(
            'SELECT * FROM uptime_monitors WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$monitor) {
            throw new NotFoundException('Monitor not found');
        }

        $this->db->delete('uptime_monitors', ['id' => $id]);

        return JsonResponse::success(null, 'Monitor deleted');
    }

    public function check(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $monitor = $this->db->fetchAssociative(
            'SELECT * FROM uptime_monitors WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$monitor) {
            throw new NotFoundException('Monitor not found');
        }

        $result = $this->performCheck($monitor);

        return JsonResponse::success($result);
    }

    public function getStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $totals = $this->db->fetchAssociative(
            'SELECT
                COUNT(*) as total,
                SUM(CASE WHEN current_status = "up" THEN 1 ELSE 0 END) as up,
                SUM(CASE WHEN current_status = "down" THEN 1 ELSE 0 END) as down,
                SUM(CASE WHEN is_paused = 1 THEN 1 ELSE 0 END) as paused
             FROM uptime_monitors WHERE user_id = ?',
            [$userId]
        );

        $recentIncidents = $this->db->fetchAllAssociative(
            'SELECT ui.*, um.name as monitor_name
             FROM uptime_incidents ui
             JOIN uptime_monitors um ON ui.monitor_id = um.id
             WHERE um.user_id = ?
             ORDER BY ui.started_at DESC LIMIT 10',
            [$userId]
        );

        return JsonResponse::success([
            'totals' => $totals,
            'recent_incidents' => $recentIncidents,
        ]);
    }

    public function performCheck(array $monitor): array
    {
        $startTime = microtime(true);
        $status = 'down';
        $statusCode = null;
        $errorMessage = null;
        $responseTime = null;

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $monitor['url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => (int) $monitor['timeout'],
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_NOBODY => false,
            ]);

            $body = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if (curl_errno($ch)) {
                $errorMessage = curl_error($ch);
            } else {
                // Check status code
                $expectedStatus = (int) $monitor['expected_status_code'];
                if ($statusCode === $expectedStatus || ($expectedStatus === 200 && $statusCode >= 200 && $statusCode < 300)) {
                    // Check keyword if specified
                    if (!empty($monitor['expected_keyword'])) {
                        if (stripos($body, $monitor['expected_keyword']) !== false) {
                            $status = 'up';
                        } else {
                            $errorMessage = 'Expected keyword not found';
                        }
                    } else {
                        $status = 'up';
                    }
                } else {
                    $errorMessage = "Unexpected status code: {$statusCode}";
                }
            }

            curl_close($ch);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
        }

        // Record check
        $checkId = Uuid::uuid4()->toString();
        $this->db->insert('uptime_checks', [
            'id' => $checkId,
            'monitor_id' => $monitor['id'],
            'status' => $status,
            'response_time' => $responseTime,
            'status_code' => $statusCode,
            'error_message' => $errorMessage,
        ]);

        // Update monitor status
        $previousStatus = $monitor['current_status'];
        $updates = [
            'current_status' => $status,
            'last_check_at' => date('Y-m-d H:i:s'),
        ];

        if ($status === 'up') {
            $updates['last_up_at'] = date('Y-m-d H:i:s');

            // Close incident if any
            if ($previousStatus === 'down') {
                $this->db->executeStatement(
                    'UPDATE uptime_incidents SET ended_at = NOW(), is_resolved = 1,
                     duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
                     WHERE monitor_id = ? AND is_resolved = 0',
                    [$monitor['id']]
                );
            }
        } else {
            $updates['last_down_at'] = date('Y-m-d H:i:s');

            // Create incident if new
            if ($previousStatus !== 'down') {
                $this->db->insert('uptime_incidents', [
                    'id' => Uuid::uuid4()->toString(),
                    'monitor_id' => $monitor['id'],
                    'started_at' => date('Y-m-d H:i:s'),
                    'cause' => $errorMessage,
                ]);
            }
        }

        // Calculate uptime percentage
        $uptimeData = $this->calculateUptime($monitor['id'], '-30 days');
        $updates['uptime_percentage'] = $uptimeData['percentage'];

        $this->db->update('uptime_monitors', $updates, ['id' => $monitor['id']]);

        return [
            'status' => $status,
            'response_time' => $responseTime,
            'status_code' => $statusCode,
            'error_message' => $errorMessage,
        ];
    }

    private function calculateUptime(string $monitorId, string $period): array
    {
        $since = date('Y-m-d H:i:s', strtotime($period));

        $stats = $this->db->fetchAssociative(
            'SELECT
                COUNT(*) as total_checks,
                SUM(CASE WHEN status = "up" THEN 1 ELSE 0 END) as up_checks,
                AVG(response_time) as avg_response_time
             FROM uptime_checks
             WHERE monitor_id = ? AND checked_at >= ?',
            [$monitorId, $since]
        );

        $percentage = $stats['total_checks'] > 0
            ? round(($stats['up_checks'] / $stats['total_checks']) * 100, 2)
            : 100;

        return [
            'total_checks' => (int) $stats['total_checks'],
            'up_checks' => (int) $stats['up_checks'],
            'percentage' => $percentage,
            'avg_response_time' => $stats['avg_response_time'] ? round((float) $stats['avg_response_time']) : null,
        ];
    }
}
