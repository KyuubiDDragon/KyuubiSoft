<?php

declare(strict_types=1);

namespace App\Modules\StatusPage\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;

class StatusPageController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Public endpoint — NO AUTH required.
     * Returns visible monitors with current status + recent incidents.
     */
    public function publicIndex(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Find the first public status page config
        $config = $this->db->fetchAssociative(
            'SELECT * FROM status_page_config WHERE is_public = 1 ORDER BY created_at ASC LIMIT 1'
        );

        if (!$config) {
            return JsonResponse::success([
                'title' => 'System Status',
                'description' => null,
                'monitors' => [],
                'incidents' => [],
            ]);
        }

        // Get assigned monitors with their uptime data
        $monitors = $this->db->fetchAllAssociative(
            'SELECT spm.id, spm.display_name, spm.display_order, spm.group_name,
                    um.id AS monitor_id, um.name, um.type, um.current_status,
                    um.uptime_percentage, um.last_check_at
             FROM status_page_monitors spm
             JOIN uptime_monitors um ON um.id = spm.monitor_id
             WHERE spm.config_id = ?
             ORDER BY spm.display_order ASC, spm.display_name ASC',
            [$config['id']]
        );

        // For each monitor, fetch 90-day check history (one entry per day)
        foreach ($monitors as &$monitor) {
            $dailyChecks = $this->db->fetchAllAssociative(
                "SELECT DATE(checked_at) AS check_date,
                        SUM(CASE WHEN status = 'up' THEN 1 ELSE 0 END) AS up_count,
                        COUNT(*) AS total_count
                 FROM uptime_checks
                 WHERE monitor_id = ? AND checked_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                 GROUP BY DATE(checked_at)
                 ORDER BY check_date ASC",
                [$monitor['monitor_id']]
            );

            $monitor['daily_history'] = array_map(function ($day) {
                $total = (int)$day['total_count'];
                $up = (int)$day['up_count'];
                return [
                    'date' => $day['check_date'],
                    'status' => $total === 0 ? 'no_data' : ($up === $total ? 'up' : ($up === 0 ? 'down' : 'degraded')),
                    'uptime' => $total > 0 ? round(($up / $total) * 100, 2) : null,
                ];
            }, $dailyChecks);

            // Use display_name if set, otherwise use monitor name
            $monitor['display_name'] = $monitor['display_name'] ?: $monitor['name'];
        }

        // Get active incidents
        $activeIncidents = $this->db->fetchAllAssociative(
            'SELECT * FROM status_page_incidents
             WHERE config_id = ? AND resolved_at IS NULL
             ORDER BY started_at DESC',
            [$config['id']]
        );

        foreach ($activeIncidents as &$incident) {
            $incident['updates'] = $this->db->fetchAllAssociative(
                'SELECT * FROM status_page_incident_updates
                 WHERE incident_id = ?
                 ORDER BY created_at DESC',
                [$incident['id']]
            );
        }

        // Get recently resolved incidents (last 7 days)
        $resolvedIncidents = $this->db->fetchAllAssociative(
            'SELECT * FROM status_page_incidents
             WHERE config_id = ? AND resolved_at IS NOT NULL
               AND resolved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY resolved_at DESC',
            [$config['id']]
        );

        foreach ($resolvedIncidents as &$incident) {
            $incident['updates'] = $this->db->fetchAllAssociative(
                'SELECT * FROM status_page_incident_updates
                 WHERE incident_id = ?
                 ORDER BY created_at DESC',
                [$incident['id']]
            );
        }

        return JsonResponse::success([
            'title' => $config['title'],
            'description' => $config['description'],
            'monitors' => $monitors,
            'active_incidents' => $activeIncidents,
            'resolved_incidents' => $resolvedIncidents,
        ]);
    }

    /**
     * Admin: get status page config for current user.
     */
    public function getConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $config = $this->db->fetchAssociative(
            'SELECT * FROM status_page_config WHERE user_id = ? ORDER BY created_at ASC LIMIT 1',
            [$userId]
        );

        if (!$config) {
            // Auto-create a default config for this user
            $id = Uuid::uuid4()->toString();
            $this->db->insert('status_page_config', [
                'id' => $id,
                'user_id' => $userId,
                'title' => 'System Status',
                'description' => null,
                'is_public' => 1,
            ]);

            $config = $this->db->fetchAssociative(
                'SELECT * FROM status_page_config WHERE id = ?',
                [$id]
            );
        }

        return JsonResponse::success($config);
    }

    /**
     * Admin: update title, description, is_public.
     */
    public function updateConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        $config = $this->db->fetchAssociative(
            'SELECT * FROM status_page_config WHERE user_id = ? ORDER BY created_at ASC LIMIT 1',
            [$userId]
        );

        if (!$config) {
            return JsonResponse::notFound('Status page config not found');
        }

        $updateData = [];
        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['is_public'])) {
            $updateData['is_public'] = $data['is_public'] ? 1 : 0;
        }

        if (!empty($updateData)) {
            $this->db->update('status_page_config', $updateData, ['id' => $config['id']]);
        }

        $config = $this->db->fetchAssociative(
            'SELECT * FROM status_page_config WHERE id = ?',
            [$config['id']]
        );

        return JsonResponse::success($config, 'Config updated');
    }

    /**
     * Admin: list monitors assigned to status page.
     */
    public function getMonitors(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $config = $this->db->fetchAssociative(
            'SELECT * FROM status_page_config WHERE user_id = ? ORDER BY created_at ASC LIMIT 1',
            [$userId]
        );

        if (!$config) {
            return JsonResponse::success([
                'assigned' => [],
                'available' => [],
            ]);
        }

        // Get assigned monitors
        $assigned = $this->db->fetchAllAssociative(
            'SELECT spm.*, um.name AS monitor_name, um.type AS monitor_type, um.current_status
             FROM status_page_monitors spm
             JOIN uptime_monitors um ON um.id = spm.monitor_id
             WHERE spm.config_id = ?
             ORDER BY spm.display_order ASC',
            [$config['id']]
        );

        // Get available monitors (not yet assigned)
        $assignedIds = array_column($assigned, 'monitor_id');
        if (!empty($assignedIds)) {
            $placeholders = implode(',', array_fill(0, count($assignedIds), '?'));
            $available = $this->db->fetchAllAssociative(
                "SELECT id, name, type, current_status FROM uptime_monitors
                 WHERE user_id = ? AND id NOT IN ($placeholders)
                 ORDER BY name ASC",
                array_merge([$userId], $assignedIds)
            );
        } else {
            $available = $this->db->fetchAllAssociative(
                'SELECT id, name, type, current_status FROM uptime_monitors
                 WHERE user_id = ?
                 ORDER BY name ASC',
                [$userId]
            );
        }

        return JsonResponse::success([
            'assigned' => $assigned,
            'available' => $available,
        ]);
    }

    /**
     * Admin: add monitor to status page.
     */
    public function addMonitor(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['monitor_id'])) {
            return JsonResponse::error('monitor_id is required', 400);
        }

        $config = $this->db->fetchAssociative(
            'SELECT * FROM status_page_config WHERE user_id = ? ORDER BY created_at ASC LIMIT 1',
            [$userId]
        );

        if (!$config) {
            return JsonResponse::notFound('Status page config not found');
        }

        // Verify the monitor belongs to this user
        $monitor = $this->db->fetchAssociative(
            'SELECT * FROM uptime_monitors WHERE id = ? AND user_id = ?',
            [$data['monitor_id'], $userId]
        );

        if (!$monitor) {
            return JsonResponse::notFound('Monitor not found');
        }

        // Check if already assigned
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM status_page_monitors WHERE config_id = ? AND monitor_id = ?',
            [$config['id'], $data['monitor_id']]
        );

        if ($existing) {
            return JsonResponse::error('Monitor already assigned', 409);
        }

        // Get max display_order
        $maxOrder = $this->db->fetchOne(
            'SELECT COALESCE(MAX(display_order), 0) FROM status_page_monitors WHERE config_id = ?',
            [$config['id']]
        );

        $id = Uuid::uuid4()->toString();
        $this->db->insert('status_page_monitors', [
            'id' => $id,
            'config_id' => $config['id'],
            'monitor_id' => $data['monitor_id'],
            'display_name' => $data['display_name'] ?? null,
            'display_order' => ((int)$maxOrder) + 1,
            'group_name' => $data['group_name'] ?? null,
        ]);

        $item = $this->db->fetchAssociative(
            'SELECT spm.*, um.name AS monitor_name, um.type AS monitor_type, um.current_status
             FROM status_page_monitors spm
             JOIN uptime_monitors um ON um.id = spm.monitor_id
             WHERE spm.id = ?',
            [$id]
        );

        return JsonResponse::created($item, 'Monitor added to status page');
    }

    /**
     * Admin: remove monitor from status page.
     */
    public function removeMonitor(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $monitorEntryId = $args['id'];

        $config = $this->db->fetchAssociative(
            'SELECT * FROM status_page_config WHERE user_id = ? ORDER BY created_at ASC LIMIT 1',
            [$userId]
        );

        if (!$config) {
            return JsonResponse::notFound('Status page config not found');
        }

        $deleted = $this->db->delete('status_page_monitors', [
            'id' => $monitorEntryId,
            'config_id' => $config['id'],
        ]);

        if (!$deleted) {
            return JsonResponse::notFound('Monitor entry not found');
        }

        return JsonResponse::success(null, 'Monitor removed from status page');
    }

    /**
     * List incidents (paginated).
     */
    public function getIncidents(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $config = $this->db->fetchAssociative(
            'SELECT * FROM status_page_config WHERE user_id = ? ORDER BY created_at ASC LIMIT 1',
            [$userId]
        );

        if (!$config) {
            return JsonResponse::success(['items' => [], 'total' => 0]);
        }

        $page = max(1, (int)($params['page'] ?? 1));
        $limit = min(50, max(1, (int)($params['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = (int)$this->db->fetchOne(
            'SELECT COUNT(*) FROM status_page_incidents WHERE config_id = ?',
            [$config['id']]
        );

        $incidents = $this->db->fetchAllAssociative(
            'SELECT * FROM status_page_incidents
             WHERE config_id = ?
             ORDER BY started_at DESC
             LIMIT ? OFFSET ?',
            [$config['id'], $limit, $offset],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::INTEGER]
        );

        foreach ($incidents as &$incident) {
            $incident['updates'] = $this->db->fetchAllAssociative(
                'SELECT * FROM status_page_incident_updates
                 WHERE incident_id = ?
                 ORDER BY created_at DESC',
                [$incident['id']]
            );
        }

        return JsonResponse::success([
            'items' => $incidents,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * Create incident with title, status, message, impact.
     */
    public function createIncident(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['title'])) {
            return JsonResponse::error('Title is required', 400);
        }

        $config = $this->db->fetchAssociative(
            'SELECT * FROM status_page_config WHERE user_id = ? ORDER BY created_at ASC LIMIT 1',
            [$userId]
        );

        if (!$config) {
            return JsonResponse::notFound('Status page config not found');
        }

        $validStatuses = ['investigating', 'identified', 'monitoring', 'resolved'];
        $status = $data['status'] ?? 'investigating';
        if (!in_array($status, $validStatuses)) {
            return JsonResponse::error('Invalid status. Must be one of: ' . implode(', ', $validStatuses), 400);
        }

        $validImpacts = ['none', 'minor', 'major', 'critical'];
        $impact = $data['impact'] ?? 'minor';
        if (!in_array($impact, $validImpacts)) {
            return JsonResponse::error('Invalid impact. Must be one of: ' . implode(', ', $validImpacts), 400);
        }

        $id = Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');

        $this->db->insert('status_page_incidents', [
            'id' => $id,
            'config_id' => $config['id'],
            'title' => $data['title'],
            'status' => $status,
            'message' => $data['message'] ?? null,
            'impact' => $impact,
            'started_at' => $data['started_at'] ?? $now,
            'resolved_at' => $status === 'resolved' ? $now : null,
            'created_at' => $now,
        ]);

        // Create initial incident update
        if (!empty($data['message'])) {
            $this->db->insert('status_page_incident_updates', [
                'id' => Uuid::uuid4()->toString(),
                'incident_id' => $id,
                'status' => $status,
                'message' => $data['message'],
                'created_at' => $now,
            ]);
        }

        $incident = $this->db->fetchAssociative(
            'SELECT * FROM status_page_incidents WHERE id = ?',
            [$id]
        );

        $incident['updates'] = $this->db->fetchAllAssociative(
            'SELECT * FROM status_page_incident_updates WHERE incident_id = ? ORDER BY created_at DESC',
            [$id]
        );

        return JsonResponse::created($incident, 'Incident created');
    }

    /**
     * Update incident status/message.
     */
    public function updateIncident(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $incidentId = $args['id'];
        $data = $request->getParsedBody();

        $config = $this->db->fetchAssociative(
            'SELECT * FROM status_page_config WHERE user_id = ? ORDER BY created_at ASC LIMIT 1',
            [$userId]
        );

        if (!$config) {
            return JsonResponse::notFound('Status page config not found');
        }

        $incident = $this->db->fetchAssociative(
            'SELECT * FROM status_page_incidents WHERE id = ? AND config_id = ?',
            [$incidentId, $config['id']]
        );

        if (!$incident) {
            return JsonResponse::notFound('Incident not found');
        }

        $updateData = [];
        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }
        if (isset($data['status'])) {
            $validStatuses = ['investigating', 'identified', 'monitoring', 'resolved'];
            if (!in_array($data['status'], $validStatuses)) {
                return JsonResponse::error('Invalid status', 400);
            }
            $updateData['status'] = $data['status'];
            if ($data['status'] === 'resolved' && empty($incident['resolved_at'])) {
                $updateData['resolved_at'] = date('Y-m-d H:i:s');
            }
        }
        if (array_key_exists('message', $data)) {
            $updateData['message'] = $data['message'];
        }
        if (isset($data['impact'])) {
            $validImpacts = ['none', 'minor', 'major', 'critical'];
            if (!in_array($data['impact'], $validImpacts)) {
                return JsonResponse::error('Invalid impact', 400);
            }
            $updateData['impact'] = $data['impact'];
        }

        if (!empty($updateData)) {
            $this->db->update('status_page_incidents', $updateData, ['id' => $incidentId]);
        }

        $incident = $this->db->fetchAssociative(
            'SELECT * FROM status_page_incidents WHERE id = ?',
            [$incidentId]
        );

        $incident['updates'] = $this->db->fetchAllAssociative(
            'SELECT * FROM status_page_incident_updates WHERE incident_id = ? ORDER BY created_at DESC',
            [$incidentId]
        );

        return JsonResponse::success($incident, 'Incident updated');
    }

    /**
     * Add update to incident timeline.
     */
    public function addIncidentUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $incidentId = $args['id'];
        $data = $request->getParsedBody();

        if (empty($data['message'])) {
            return JsonResponse::error('Message is required', 400);
        }
        if (empty($data['status'])) {
            return JsonResponse::error('Status is required', 400);
        }

        $config = $this->db->fetchAssociative(
            'SELECT * FROM status_page_config WHERE user_id = ? ORDER BY created_at ASC LIMIT 1',
            [$userId]
        );

        if (!$config) {
            return JsonResponse::notFound('Status page config not found');
        }

        $incident = $this->db->fetchAssociative(
            'SELECT * FROM status_page_incidents WHERE id = ? AND config_id = ?',
            [$incidentId, $config['id']]
        );

        if (!$incident) {
            return JsonResponse::notFound('Incident not found');
        }

        $validStatuses = ['investigating', 'identified', 'monitoring', 'resolved'];
        if (!in_array($data['status'], $validStatuses)) {
            return JsonResponse::error('Invalid status', 400);
        }

        $id = Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');

        $this->db->insert('status_page_incident_updates', [
            'id' => $id,
            'incident_id' => $incidentId,
            'status' => $data['status'],
            'message' => $data['message'],
            'created_at' => $now,
        ]);

        // Also update the incident's status
        $incidentUpdate = ['status' => $data['status']];
        if ($data['status'] === 'resolved' && empty($incident['resolved_at'])) {
            $incidentUpdate['resolved_at'] = $now;
        }
        $this->db->update('status_page_incidents', $incidentUpdate, ['id' => $incidentId]);

        $update = $this->db->fetchAssociative(
            'SELECT * FROM status_page_incident_updates WHERE id = ?',
            [$id]
        );

        return JsonResponse::created($update, 'Incident update added');
    }
}
