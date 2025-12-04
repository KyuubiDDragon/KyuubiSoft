<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Modules\UptimeMonitor\Checkers\CheckerFactory;
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
        $params = $request->getQueryParams();

        // Build query with optional filters
        $sql = 'SELECT um.*, p.name as project_name, p.color as project_color, uf.name as folder_name, uf.color as folder_color
                FROM uptime_monitors um
                LEFT JOIN projects p ON um.project_id = p.id
                LEFT JOIN uptime_folders uf ON um.folder_id = uf.id
                WHERE um.user_id = ?';
        $queryParams = [$userId];

        // Filter by project
        if (!empty($params['project_id'])) {
            $sql .= ' AND um.project_id = ?';
            $queryParams[] = $params['project_id'];
        }

        // Filter by folder
        if (!empty($params['folder_id'])) {
            $sql .= ' AND um.folder_id = ?';
            $queryParams[] = $params['folder_id'];
        } elseif (isset($params['folder_id']) && $params['folder_id'] === '') {
            // Unfiled monitors only
            $sql .= ' AND um.folder_id IS NULL';
        }

        // Filter by type
        if (!empty($params['type'])) {
            $sql .= ' AND um.type = ?';
            $queryParams[] = $params['type'];
        }

        // Filter by status
        if (!empty($params['status'])) {
            $sql .= ' AND um.current_status = ?';
            $queryParams[] = $params['status'];
        }

        $sql .= ' ORDER BY uf.position, uf.name, um.name';

        $monitors = $this->db->fetchAllAssociative($sql, $queryParams);

        // Add recent checks for each monitor and cast booleans
        foreach ($monitors as &$monitor) {
            $monitor['is_active'] = (bool) $monitor['is_active'];
            $monitor['is_paused'] = (bool) $monitor['is_paused'];
            $monitor['notify_on_down'] = (bool) $monitor['notify_on_down'];
            $monitor['notify_on_recovery'] = (bool) $monitor['notify_on_recovery'];
            $monitor['game_server_data'] = $monitor['game_server_data'] ? json_decode($monitor['game_server_data'], true) : null;
            $monitor['recent_checks'] = $this->db->fetchAllAssociative(
                'SELECT status, response_time, checked_at, check_data FROM uptime_checks
                 WHERE monitor_id = ? ORDER BY checked_at DESC LIMIT 30',
                [$monitor['id']]
            );
            // Decode check_data JSON
            foreach ($monitor['recent_checks'] as &$check) {
                $check['check_data'] = $check['check_data'] ? json_decode($check['check_data'], true) : null;
            }
        }

        // Get folders
        $folders = $this->db->fetchAllAssociative(
            'SELECT uf.*, COUNT(um.id) as monitor_count
             FROM uptime_folders uf
             LEFT JOIN uptime_monitors um ON uf.id = um.folder_id
             WHERE uf.user_id = ?
             GROUP BY uf.id
             ORDER BY uf.position, uf.name',
            [$userId]
        );

        foreach ($folders as &$folder) {
            $folder['is_collapsed'] = (bool) $folder['is_collapsed'];
        }

        return JsonResponse::success([
            'items' => $monitors,
            'folders' => $folders,
        ]);
    }

    public function getTypes(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponse::success(CheckerFactory::getSupportedTypes());
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            throw new ValidationException('Name is required');
        }

        $type = $data['type'] ?? 'https';
        $typeInfo = CheckerFactory::getSupportedTypes()[$type] ?? null;

        if (!$typeInfo) {
            throw new ValidationException('Invalid monitor type');
        }

        // Validate required fields based on type
        $needsUrl = in_array($type, ['http', 'https']);
        $needsHostname = !$needsUrl;

        if ($needsUrl && empty($data['url'])) {
            throw new ValidationException('URL is required for HTTP/HTTPS monitors');
        }

        if ($needsHostname && empty($data['hostname']) && empty($data['url'])) {
            throw new ValidationException('Hostname is required');
        }

        // Extract hostname from URL if not provided
        $hostname = $data['hostname'] ?? null;
        if (!$hostname && !empty($data['url'])) {
            $hostname = parse_url($data['url'], PHP_URL_HOST);
        }

        // Get default port for type
        $port = $data['port'] ?? CheckerFactory::getDefaultPort($type);

        $id = Uuid::uuid4()->toString();

        $insertData = [
            'id' => $id,
            'user_id' => $userId,
            'project_id' => !empty($data['project_id']) ? $data['project_id'] : null,
            'folder_id' => !empty($data['folder_id']) ? $data['folder_id'] : null,
            'name' => $data['name'],
            'url' => $data['url'] ?? ($hostname ? "tcp://{$hostname}:{$port}" : null),
            'hostname' => $hostname,
            'port' => $port,
            'type' => $type,
            'check_interval' => $data['check_interval'] ?? 300,
            'timeout' => $data['timeout'] ?? 30,
            'expected_status_code' => $data['expected_status_code'] ?? 200,
            'expected_keyword' => $data['expected_keyword'] ?? null,
            'dns_record_type' => $data['dns_record_type'] ?? 'A',
            'ssl_expiry_warn_days' => $data['ssl_expiry_warn_days'] ?? 14,
            'notify_on_down' => !empty($data['notify_on_down']) ? 1 : 0,
            'notify_on_recovery' => !empty($data['notify_on_recovery']) ? 1 : 0,
            'is_active' => 1,
            'current_status' => 'pending',
        ];

        $this->db->insert('uptime_monitors', $insertData);

        $monitor = $this->db->fetchAssociative('SELECT * FROM uptime_monitors WHERE id = ?', [$id]);
        $this->castMonitorBooleans($monitor);

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

        $this->castMonitorBooleans($monitor);
        $monitor['game_server_data'] = $monitor['game_server_data'] ? json_decode($monitor['game_server_data'], true) : null;

        // Get recent checks with extended data
        $monitor['recent_checks'] = $this->db->fetchAllAssociative(
            'SELECT * FROM uptime_checks WHERE monitor_id = ? ORDER BY checked_at DESC LIMIT 100',
            [$id]
        );

        foreach ($monitor['recent_checks'] as &$check) {
            $check['check_data'] = $check['check_data'] ? json_decode($check['check_data'], true) : null;
        }

        // Get incidents
        $incidents = $this->db->fetchAllAssociative(
            'SELECT * FROM uptime_incidents WHERE monitor_id = ? ORDER BY started_at DESC LIMIT 10',
            [$id]
        );
        foreach ($incidents as &$incident) {
            $incident['is_resolved'] = (bool) $incident['is_resolved'];
        }
        $monitor['incidents'] = $incidents;

        // Calculate uptime stats for different periods
        $monitor['stats'] = [
            '24h' => $this->calculateUptime($id, '-24 hours'),
            '7d' => $this->calculateUptime($id, '-7 days'),
            '30d' => $this->calculateUptime($id, '-30 days'),
        ];

        // Add type metadata
        $monitor['type_info'] = CheckerFactory::getSupportedTypes()[$monitor['type']] ?? null;

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

        $fields = [
            'name', 'url', 'hostname', 'port', 'type', 'check_interval', 'timeout',
            'expected_status_code', 'expected_keyword', 'dns_record_type', 'ssl_expiry_warn_days'
        ];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        // Handle nullable fields (project_id, folder_id)
        $nullableFields = ['project_id', 'folder_id'];
        foreach ($nullableFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = !empty($data[$field]) ? $data[$field] : null;
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

        // Count by type
        $byType = $this->db->fetchAllAssociative(
            'SELECT type, COUNT(*) as count FROM uptime_monitors WHERE user_id = ? GROUP BY type',
            [$userId]
        );

        $recentIncidents = $this->db->fetchAllAssociative(
            'SELECT ui.*, um.name as monitor_name, um.type as monitor_type
             FROM uptime_incidents ui
             JOIN uptime_monitors um ON ui.monitor_id = um.id
             WHERE um.user_id = ?
             ORDER BY ui.started_at DESC LIMIT 10',
            [$userId]
        );

        return JsonResponse::success([
            'totals' => $totals,
            'by_type' => $byType,
            'recent_incidents' => $recentIncidents,
        ]);
    }

    public function performCheck(array $monitor): array
    {
        $type = $monitor['type'] ?? 'https';

        try {
            $checker = CheckerFactory::getChecker($type);
            $result = $checker->check($monitor);
        } catch (\Exception $e) {
            // Fallback if checker fails
            return [
                'status' => 'down',
                'response_time' => 0,
                'error_message' => $e->getMessage(),
            ];
        }

        // Record check
        $checkId = Uuid::uuid4()->toString();
        $this->db->insert('uptime_checks', [
            'id' => $checkId,
            'monitor_id' => $monitor['id'],
            'status' => $result->status,
            'response_time' => $result->responseTime,
            'status_code' => $result->statusCode,
            'error_message' => $result->errorMessage,
            'check_data' => $result->data ? json_encode($result->data) : null,
        ]);

        // Update monitor status
        $previousStatus = $monitor['current_status'];
        $updates = [
            'current_status' => $result->status,
            'last_check_at' => date('Y-m-d H:i:s'),
        ];

        // Store game server data if available
        if ($result->data && CheckerFactory::isGameServer($type)) {
            $updates['game_server_data'] = json_encode($result->data);
        }

        if ($result->isUp()) {
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
                    'cause' => $result->errorMessage,
                ]);
            }
        }

        // Calculate uptime percentage
        $uptimeData = $this->calculateUptime($monitor['id'], '-30 days');
        $updates['uptime_percentage'] = $uptimeData['percentage'];

        $this->db->update('uptime_monitors', $updates, ['id' => $monitor['id']]);

        return $result->toArray();
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

    private function castMonitorBooleans(array &$monitor): void
    {
        $monitor['is_active'] = (bool) $monitor['is_active'];
        $monitor['is_paused'] = (bool) $monitor['is_paused'];
        $monitor['notify_on_down'] = (bool) $monitor['notify_on_down'];
        $monitor['notify_on_recovery'] = (bool) $monitor['notify_on_recovery'];
    }

    // ==================== Folder Operations ====================

    public function getFolders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $folders = $this->db->fetchAllAssociative(
            'SELECT uf.*, COUNT(um.id) as monitor_count
             FROM uptime_folders uf
             LEFT JOIN uptime_monitors um ON uf.id = um.folder_id
             WHERE uf.user_id = ?
             GROUP BY uf.id
             ORDER BY uf.position, uf.name',
            [$userId]
        );

        foreach ($folders as &$folder) {
            $folder['is_collapsed'] = (bool) $folder['is_collapsed'];
        }

        return JsonResponse::success(['items' => $folders]);
    }

    public function createFolder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            throw new ValidationException('Folder name is required');
        }

        // Get max position
        $maxPos = $this->db->fetchOne(
            'SELECT MAX(position) FROM uptime_folders WHERE user_id = ?',
            [$userId]
        );

        $id = Uuid::uuid4()->toString();

        $this->db->insert('uptime_folders', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'color' => $data['color'] ?? '#6366F1',
            'icon' => $data['icon'] ?? 'folder',
            'position' => ($maxPos ?? 0) + 1,
        ]);

        $folder = $this->db->fetchAssociative('SELECT * FROM uptime_folders WHERE id = ?', [$id]);
        $folder['is_collapsed'] = (bool) $folder['is_collapsed'];
        $folder['monitor_count'] = 0;

        return JsonResponse::created($folder, 'Folder created');
    }

    public function updateFolder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $folder = $this->db->fetchAssociative(
            'SELECT * FROM uptime_folders WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$folder) {
            throw new NotFoundException('Folder not found');
        }

        $updates = [];
        $params = [];

        $fields = ['name', 'color', 'icon', 'position'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (isset($data['is_collapsed'])) {
            $updates[] = 'is_collapsed = ?';
            $params[] = $data['is_collapsed'] ? 1 : 0;
        }

        if (!empty($updates)) {
            $params[] = $id;
            $this->db->executeStatement(
                'UPDATE uptime_folders SET ' . implode(', ', $updates) . ' WHERE id = ?',
                $params
            );
        }

        return JsonResponse::success(null, 'Folder updated');
    }

    public function deleteFolder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $folder = $this->db->fetchAssociative(
            'SELECT * FROM uptime_folders WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$folder) {
            throw new NotFoundException('Folder not found');
        }

        // Monitors will have folder_id set to NULL due to ON DELETE SET NULL
        $this->db->delete('uptime_folders', ['id' => $id]);

        return JsonResponse::success(null, 'Folder deleted');
    }

    public function reorderFolders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['order']) || !is_array($data['order'])) {
            throw new ValidationException('Order array is required');
        }

        foreach ($data['order'] as $position => $folderId) {
            $this->db->executeStatement(
                'UPDATE uptime_folders SET position = ? WHERE id = ? AND user_id = ?',
                [$position, $folderId, $userId]
            );
        }

        return JsonResponse::success(null, 'Folders reordered');
    }

    public function moveMonitorsToFolder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['monitor_ids']) || !is_array($data['monitor_ids'])) {
            throw new ValidationException('Monitor IDs array is required');
        }

        $folderId = !empty($data['folder_id']) ? $data['folder_id'] : null;

        // Verify folder belongs to user if specified
        if ($folderId) {
            $folder = $this->db->fetchAssociative(
                'SELECT id FROM uptime_folders WHERE id = ? AND user_id = ?',
                [$folderId, $userId]
            );
            if (!$folder) {
                throw new NotFoundException('Folder not found');
            }
        }

        // Update all monitors
        $placeholders = implode(',', array_fill(0, count($data['monitor_ids']), '?'));
        $params = array_merge([$folderId], $data['monitor_ids'], [$userId]);

        $this->db->executeStatement(
            "UPDATE uptime_monitors SET folder_id = ? WHERE id IN ({$placeholders}) AND user_id = ?",
            $params
        );

        return JsonResponse::success(null, 'Monitors moved');
    }
}
