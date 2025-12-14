<?php

declare(strict_types=1);

namespace App\Modules\ServiceHealth\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Aggregates health data from Docker containers and Uptime Monitors
 */
class ServiceHealthController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Get combined service health overview
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();
        $projectId = $params['project_id'] ?? null;

        // Get Uptime Monitors
        $uptimeSql = 'SELECT
                        id, name, url, type, current_status as status,
                        last_check_at, uptime_percentage, project_id,
                        "uptime_monitor" as source
                      FROM uptime_monitors
                      WHERE user_id = ? AND is_active = 1';
        $uptimeParams = [$userId];

        if ($projectId) {
            $uptimeSql .= ' AND project_id = ?';
            $uptimeParams[] = $projectId;
        }

        $uptimeMonitors = $this->db->fetchAllAssociative($uptimeSql, $uptimeParams);

        // Get SSL Certificates
        $sslSql = 'SELECT
                    id, name, hostname as url, "ssl" as type, current_status as status,
                    last_check_at, days_until_expiry, project_id,
                    "ssl_certificate" as source
                   FROM ssl_certificates
                   WHERE user_id = ? AND is_active = 1';
        $sslParams = [$userId];

        if ($projectId) {
            $sslSql .= ' AND project_id = ?';
            $sslParams[] = $projectId;
        }

        $sslCertificates = $this->db->fetchAllAssociative($sslSql, $sslParams);

        // Normalize SSL status for unified display
        foreach ($sslCertificates as &$cert) {
            $cert['status'] = match ($cert['status']) {
                'valid' => 'up',
                'expiring_soon' => 'warning',
                'expired', 'invalid', 'error' => 'down',
                default => 'pending',
            };
            $cert['uptime_percentage'] = $cert['status'] === 'up' ? 100 : 0;
        }

        // Get Docker containers (if docker feature is available)
        $dockerContainers = [];
        try {
            $dockerHosts = $this->db->fetchAllAssociative(
                'SELECT id, name, host FROM docker_hosts WHERE user_id = ? AND is_active = 1',
                [$userId]
            );

            foreach ($dockerHosts as $host) {
                // Get cached container data
                $containers = $this->db->fetchAllAssociative(
                    'SELECT * FROM docker_containers WHERE host_id = ?',
                    [$host['id']]
                );

                foreach ($containers as $container) {
                    $dockerContainers[] = [
                        'id' => $container['id'],
                        'name' => $container['name'],
                        'url' => $host['name'],
                        'type' => 'docker',
                        'status' => $container['state'] === 'running' ? 'up' : 'down',
                        'last_check_at' => $container['updated_at'] ?? null,
                        'uptime_percentage' => null,
                        'project_id' => null,
                        'source' => 'docker',
                        'docker_host' => $host['name'],
                        'container_id' => $container['container_id'],
                        'image' => $container['image'],
                        'state' => $container['state'],
                    ];
                }
            }
        } catch (\Exception $e) {
            // Docker tables might not exist, skip
        }

        // Combine all services
        $allServices = array_merge($uptimeMonitors, $sslCertificates, $dockerContainers);

        // Calculate overall stats
        $stats = [
            'total' => count($allServices),
            'up' => count(array_filter($allServices, fn($s) => $s['status'] === 'up')),
            'down' => count(array_filter($allServices, fn($s) => $s['status'] === 'down')),
            'warning' => count(array_filter($allServices, fn($s) => $s['status'] === 'warning')),
            'pending' => count(array_filter($allServices, fn($s) => $s['status'] === 'pending')),
            'by_source' => [
                'uptime_monitor' => count($uptimeMonitors),
                'ssl_certificate' => count($sslCertificates),
                'docker' => count($dockerContainers),
            ],
            'overall_health' => $this->calculateOverallHealth($allServices),
        ];

        // Group by status for easy display
        $grouped = [
            'critical' => array_values(array_filter($allServices, fn($s) => $s['status'] === 'down')),
            'warning' => array_values(array_filter($allServices, fn($s) => $s['status'] === 'warning')),
            'healthy' => array_values(array_filter($allServices, fn($s) => $s['status'] === 'up')),
            'pending' => array_values(array_filter($allServices, fn($s) => $s['status'] === 'pending')),
        ];

        return JsonResponse::success([
            'services' => $allServices,
            'grouped' => $grouped,
            'stats' => $stats,
        ]);
    }

    /**
     * Get health summary (for dashboard widget)
     */
    public function summary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        // Quick counts
        $uptimeStats = $this->db->fetchAssociative(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN current_status = 'up' THEN 1 ELSE 0 END) as up,
                SUM(CASE WHEN current_status = 'down' THEN 1 ELSE 0 END) as down
             FROM uptime_monitors
             WHERE user_id = ? AND is_active = 1",
            [$userId]
        );

        $sslStats = $this->db->fetchAssociative(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN current_status = 'valid' THEN 1 ELSE 0 END) as valid,
                SUM(CASE WHEN current_status = 'expiring_soon' THEN 1 ELSE 0 END) as expiring,
                SUM(CASE WHEN current_status IN ('expired', 'invalid', 'error') THEN 1 ELSE 0 END) as error
             FROM ssl_certificates
             WHERE user_id = ? AND is_active = 1",
            [$userId]
        );

        // Get critical items (down services, expiring certs)
        $criticalServices = $this->db->fetchAllAssociative(
            "SELECT id, name, 'uptime' as type, current_status as status, last_check_at
             FROM uptime_monitors
             WHERE user_id = ? AND is_active = 1 AND current_status = 'down'
             ORDER BY last_check_at DESC
             LIMIT 5",
            [$userId]
        );

        $expiringCerts = $this->db->fetchAllAssociative(
            "SELECT id, name, 'ssl' as type, days_until_expiry, valid_until
             FROM ssl_certificates
             WHERE user_id = ? AND is_active = 1 AND days_until_expiry <= 30 AND days_until_expiry >= 0
             ORDER BY days_until_expiry ASC
             LIMIT 5",
            [$userId]
        );

        $summary = [
            'uptime' => $uptimeStats,
            'ssl' => $sslStats,
            'critical_services' => $criticalServices,
            'expiring_certificates' => $expiringCerts,
            'overall_status' => $this->determineOverallStatus($uptimeStats, $sslStats),
        ];

        return JsonResponse::success($summary);
    }

    /**
     * Get recent incidents across all services
     */
    public function incidents(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();
        $limit = min(100, (int) ($params['limit'] ?? 20));

        // Get uptime incidents
        $uptimeIncidents = $this->db->fetchAllAssociative(
            "SELECT
                i.id, i.started_at, i.ended_at, i.duration_seconds, i.cause,
                i.is_resolved, m.name as service_name, 'uptime' as source
             FROM uptime_incidents i
             JOIN uptime_monitors m ON i.monitor_id = m.id
             WHERE m.user_id = ?
             ORDER BY i.started_at DESC
             LIMIT ?",
            [$userId, $limit]
        );

        // Get SSL expiry events
        $sslEvents = $this->db->fetchAllAssociative(
            "SELECT
                n.id, n.sent_at as started_at, NULL as ended_at, NULL as duration_seconds,
                CONCAT('Certificate ', n.notification_type, ' - ', n.days_until_expiry, ' days') as cause,
                CASE WHEN n.notification_type = 'renewed' THEN 1 ELSE 0 END as is_resolved,
                c.name as service_name, 'ssl' as source
             FROM ssl_notifications n
             JOIN ssl_certificates c ON n.certificate_id = c.id
             WHERE c.user_id = ?
             ORDER BY n.sent_at DESC
             LIMIT ?",
            [$userId, $limit]
        );

        // Combine and sort by date
        $allIncidents = array_merge($uptimeIncidents, $sslEvents);
        usort($allIncidents, fn($a, $b) => strtotime($b['started_at']) - strtotime($a['started_at']));

        // Take only the first $limit items
        $allIncidents = array_slice($allIncidents, 0, $limit);

        foreach ($allIncidents as &$incident) {
            $incident['is_resolved'] = (bool) $incident['is_resolved'];
        }

        return JsonResponse::success([
            'incidents' => $allIncidents,
            'total' => count($allIncidents),
        ]);
    }

    /**
     * Get health timeline for charts
     */
    public function timeline(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();
        $days = min(90, (int) ($params['days'] ?? 7));

        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        // Get uptime check history grouped by day
        $uptimeHistory = $this->db->fetchAllAssociative(
            "SELECT
                DATE(checked_at) as date,
                COUNT(*) as total_checks,
                SUM(CASE WHEN status = 'up' THEN 1 ELSE 0 END) as up_checks,
                ROUND(SUM(CASE WHEN status = 'up' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as uptime_percentage
             FROM uptime_checks uc
             JOIN uptime_monitors um ON uc.monitor_id = um.id
             WHERE um.user_id = ? AND uc.checked_at >= ?
             GROUP BY DATE(checked_at)
             ORDER BY date ASC",
            [$userId, $startDate]
        );

        // Get SSL check history grouped by day
        $sslHistory = $this->db->fetchAllAssociative(
            "SELECT
                DATE(checked_at) as date,
                COUNT(*) as total_checks,
                SUM(CASE WHEN status = 'valid' THEN 1 ELSE 0 END) as valid_checks
             FROM ssl_certificate_checks sc
             JOIN ssl_certificates c ON sc.certificate_id = c.id
             WHERE c.user_id = ? AND sc.checked_at >= ?
             GROUP BY DATE(checked_at)
             ORDER BY date ASC",
            [$userId, $startDate]
        );

        // Get incident count by day
        $incidentHistory = $this->db->fetchAllAssociative(
            "SELECT
                DATE(started_at) as date,
                COUNT(*) as incident_count
             FROM uptime_incidents i
             JOIN uptime_monitors m ON i.monitor_id = m.id
             WHERE m.user_id = ? AND i.started_at >= ?
             GROUP BY DATE(started_at)
             ORDER BY date ASC",
            [$userId, $startDate]
        );

        return JsonResponse::success([
            'uptime_history' => $uptimeHistory,
            'ssl_history' => $sslHistory,
            'incident_history' => $incidentHistory,
            'period' => [
                'start' => $startDate,
                'end' => date('Y-m-d'),
                'days' => $days,
            ],
        ]);
    }

    // ==================== Helper Methods ====================

    private function calculateOverallHealth(array $services): float
    {
        if (empty($services)) {
            return 100.0;
        }

        $weights = [
            'up' => 100,
            'warning' => 70,
            'pending' => 50,
            'down' => 0,
        ];

        $totalWeight = 0;
        foreach ($services as $service) {
            $totalWeight += $weights[$service['status']] ?? 50;
        }

        return round($totalWeight / count($services), 1);
    }

    private function determineOverallStatus(array $uptimeStats, array $sslStats): string
    {
        $downServices = (int) ($uptimeStats['down'] ?? 0);
        $expiredCerts = (int) ($sslStats['error'] ?? 0);

        if ($downServices > 0 || $expiredCerts > 0) {
            return 'critical';
        }

        $expiringCerts = (int) ($sslStats['expiring'] ?? 0);
        if ($expiringCerts > 0) {
            return 'warning';
        }

        return 'healthy';
    }
}
