<?php

declare(strict_types=1);

namespace App\Modules\TimeTracking\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Services\ProjectAccessService;
use App\Modules\Webhooks\Services\WebhookService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class TimeTrackingController
{
    public function __construct(
        private readonly Connection $db,
        private readonly ProjectAccessService $projectAccess,
        private readonly WebhookService $webhookService
    ) {}

    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $projectId = $params['project_id'] ?? null;
        $from = $params['from'] ?? null;
        $to = $params['to'] ?? null;
        $limit = min((int) ($params['limit'] ?? 50), 100);
        $offset = (int) ($params['offset'] ?? 0);

        // Check project access for restricted users
        $isRestricted = $this->projectAccess->isUserRestricted($userId);
        $accessibleProjectIds = $isRestricted ? $this->projectAccess->getUserAccessibleProjectIds($userId) : [];

        // Validate requested project_id access
        if ($projectId && $isRestricted && !in_array($projectId, $accessibleProjectIds)) {
            return JsonResponse::error('Keine Berechtigung für dieses Projekt', 403);
        }

        $sql = 'SELECT te.*, p.name as project_name, p.color as project_color
                FROM time_entries te
                LEFT JOIN projects p ON p.id = te.project_id
                WHERE te.user_id = ?';
        $sqlParams = [$userId];
        $types = [\PDO::PARAM_STR];

        // Filter by accessible projects for restricted users
        if ($isRestricted && !$projectId) {
            if (empty($accessibleProjectIds)) {
                return JsonResponse::success([
                    'items' => [],
                    'total' => 0,
                    'limit' => $limit,
                    'offset' => $offset,
                ]);
            }
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $sql .= " AND te.project_id IN ({$placeholders})";
            foreach ($accessibleProjectIds as $pid) {
                $sqlParams[] = $pid;
                $types[] = \PDO::PARAM_STR;
            }
        }

        if ($projectId) {
            $sql .= ' AND te.project_id = ?';
            $sqlParams[] = $projectId;
            $types[] = \PDO::PARAM_STR;
        }

        if ($from) {
            $sql .= ' AND te.started_at >= ?';
            $sqlParams[] = $from;
            $types[] = \PDO::PARAM_STR;
        }

        if ($to) {
            $sql .= ' AND te.started_at <= ?';
            $sqlParams[] = $to . ' 23:59:59';
            $types[] = \PDO::PARAM_STR;
        }

        // Count total before adding LIMIT/OFFSET
        $countSql = 'SELECT COUNT(*) FROM time_entries te WHERE te.user_id = ?';
        $countParams = [$userId];

        // Filter count by accessible projects for restricted users
        if ($isRestricted && !$projectId && !empty($accessibleProjectIds)) {
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $countSql .= " AND te.project_id IN ({$placeholders})";
            $countParams = array_merge($countParams, $accessibleProjectIds);
        }

        if ($projectId) {
            $countSql .= ' AND te.project_id = ?';
            $countParams[] = $projectId;
        }
        if ($from) {
            $countSql .= ' AND te.started_at >= ?';
            $countParams[] = $from;
        }
        if ($to) {
            $countSql .= ' AND te.started_at <= ?';
            $countParams[] = $to . ' 23:59:59';
        }
        $total = (int) $this->db->fetchOne($countSql, $countParams);

        $sql .= ' ORDER BY te.started_at DESC LIMIT ? OFFSET ?';
        $sqlParams[] = $limit;
        $sqlParams[] = $offset;
        $types[] = \PDO::PARAM_INT;
        $types[] = \PDO::PARAM_INT;

        $entries = $this->db->fetchAllAssociative($sql, $sqlParams, $types);

        foreach ($entries as &$entry) {
            $entry['tags'] = json_decode($entry['tags'] ?? '[]', true);
            $entry['is_running'] = (bool) $entry['is_running'];
            $entry['is_billable'] = (bool) $entry['is_billable'];
            $entry['duration_seconds'] = $this->calculateDuration($entry);
        }

        return JsonResponse::success([
            'items' => $entries,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function getRunning(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $entry = $this->db->fetchAssociative(
            'SELECT te.*, p.name as project_name, p.color as project_color
             FROM time_entries te
             LEFT JOIN projects p ON p.id = te.project_id
             WHERE te.user_id = ? AND te.is_running = 1',
            [$userId]
        );

        if ($entry) {
            $entry['tags'] = json_decode($entry['tags'] ?? '[]', true);
            $entry['is_running'] = true;
            $entry['is_billable'] = (bool) $entry['is_billable'];
            $entry['duration_seconds'] = $this->calculateDuration($entry);
        }

        return JsonResponse::success( ['entry' => $entry]);
    }

    public function start(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        // Validate project access for restricted users
        $projectId = $data['project_id'] ?? null;
        if ($projectId && $this->projectAccess->isUserRestricted($userId)) {
            $accessibleProjectIds = $this->projectAccess->getUserAccessibleProjectIds($userId);
            if (!in_array($projectId, $accessibleProjectIds)) {
                return JsonResponse::error('Keine Berechtigung für dieses Projekt', 403);
            }
        }

        // Stop any running entry first
        $running = $this->db->fetchAssociative(
            'SELECT id FROM time_entries WHERE user_id = ? AND is_running = 1',
            [$userId]
        );

        if ($running) {
            $this->stopEntry($running['id']);
        }

        $taskName = trim($data['task_name'] ?? '');
        if (empty($taskName)) {
            return JsonResponse::error( 'Aufgabenname ist erforderlich', 400);
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('time_entries', [
            'id' => $id,
            'user_id' => $userId,
            'project_id' => $projectId,
            'task_name' => $taskName,
            'description' => $data['description'] ?? null,
            'started_at' => date('Y-m-d H:i:s'),
            'is_running' => 1,
            'is_billable' => !empty($data['is_billable']) ? 1 : 0,
            'hourly_rate' => $data['hourly_rate'] ?? null,
            'tags' => json_encode($data['tags'] ?? []),
        ]);

        $entry = $this->db->fetchAssociative(
            'SELECT te.*, p.name as project_name, p.color as project_color
             FROM time_entries te
             LEFT JOIN projects p ON p.id = te.project_id
             WHERE te.id = ?',
            [$id]
        );

        $entry['tags'] = json_decode($entry['tags'] ?? '[]', true);
        $entry['is_running'] = true;
        $entry['is_billable'] = (bool) $entry['is_billable'];

        // Trigger webhook
        $this->webhookService->trigger($userId, 'time.started', [
            'id' => $id,
            'name' => $taskName,
            'project' => $entry['project_name'] ?? null,
            'message' => 'Zeiterfassung gestartet: ' . $taskName,
        ]);

        return JsonResponse::created([
            'entry' => $entry,
        ], 'Zeiterfassung gestartet');
    }

    public function stop(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $entryId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $entry = $this->db->fetchAssociative(
            'SELECT * FROM time_entries WHERE id = ? AND user_id = ?',
            [$entryId, $userId]
        );

        if (!$entry) {
            return JsonResponse::error( 'Eintrag nicht gefunden', 404);
        }

        if (!$entry['is_running']) {
            return JsonResponse::error( 'Eintrag läuft nicht', 400);
        }

        $this->stopEntry($entryId);

        $updated = $this->db->fetchAssociative(
            'SELECT te.*, p.name as project_name, p.color as project_color
             FROM time_entries te
             LEFT JOIN projects p ON p.id = te.project_id
             WHERE te.id = ?',
            [$entryId]
        );

        $updated['tags'] = json_decode($updated['tags'] ?? '[]', true);
        $updated['is_running'] = false;
        $updated['is_billable'] = (bool) $updated['is_billable'];

        // Trigger webhook
        $this->webhookService->trigger($userId, 'time.stopped', [
            'id' => $entryId,
            'name' => $updated['task_name'],
            'project' => $updated['project_name'] ?? null,
            'duration_seconds' => $updated['duration_seconds'],
            'message' => 'Zeiterfassung gestoppt: ' . $updated['task_name'],
        ]);

        return JsonResponse::success( [
            'entry' => $updated,
            'message' => 'Zeiterfassung gestoppt',
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        // Validate project access for restricted users
        $projectId = $data['project_id'] ?? null;
        if ($projectId && $this->projectAccess->isUserRestricted($userId)) {
            $accessibleProjectIds = $this->projectAccess->getUserAccessibleProjectIds($userId);
            if (!in_array($projectId, $accessibleProjectIds)) {
                return JsonResponse::error('Keine Berechtigung für dieses Projekt', 403);
            }
        }

        $taskName = trim($data['task_name'] ?? '');
        if (empty($taskName)) {
            return JsonResponse::error( 'Aufgabenname ist erforderlich', 400);
        }

        $startedAt = $data['started_at'] ?? null;
        $endedAt = $data['ended_at'] ?? null;
        $durationSeconds = $data['duration_seconds'] ?? null;

        if (!$startedAt) {
            return JsonResponse::error( 'Startzeit ist erforderlich', 400);
        }

        // Calculate duration if ended_at provided
        if ($endedAt && !$durationSeconds) {
            $start = new \DateTime($startedAt);
            $end = new \DateTime($endedAt);
            $durationSeconds = $end->getTimestamp() - $start->getTimestamp();
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('time_entries', [
            'id' => $id,
            'user_id' => $userId,
            'project_id' => $projectId,
            'task_name' => $taskName,
            'description' => $data['description'] ?? null,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => $durationSeconds,
            'is_running' => 0,
            'is_billable' => !empty($data['is_billable']) ? 1 : 0,
            'hourly_rate' => $data['hourly_rate'] ?? null,
            'tags' => json_encode($data['tags'] ?? []),
        ]);

        return JsonResponse::created([
            'id' => $id,
        ], 'Zeiteintrag erstellt');
    }

    public function update(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $entryId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $entry = $this->db->fetchAssociative(
            'SELECT * FROM time_entries WHERE id = ? AND user_id = ?',
            [$entryId, $userId]
        );

        if (!$entry) {
            return JsonResponse::error( 'Eintrag nicht gefunden', 404);
        }

        $updates = [];
        $params = [];

        if (isset($data['task_name'])) {
            $updates[] = 'task_name = ?';
            $params[] = trim($data['task_name']);
        }

        if (array_key_exists('description', $data)) {
            $updates[] = 'description = ?';
            $params[] = $data['description'];
        }

        if (array_key_exists('project_id', $data)) {
            $updates[] = 'project_id = ?';
            $params[] = $data['project_id'];
        }

        if (isset($data['started_at'])) {
            $updates[] = 'started_at = ?';
            $params[] = $data['started_at'];
        }

        if (isset($data['ended_at'])) {
            $updates[] = 'ended_at = ?';
            $params[] = $data['ended_at'];
        }

        if (isset($data['duration_seconds'])) {
            $updates[] = 'duration_seconds = ?';
            $params[] = $data['duration_seconds'];
        }

        if (isset($data['is_billable'])) {
            $updates[] = 'is_billable = ?';
            $params[] = $data['is_billable'] ? 1 : 0;
        }

        if (isset($data['hourly_rate'])) {
            $updates[] = 'hourly_rate = ?';
            $params[] = $data['hourly_rate'];
        }

        if (isset($data['tags'])) {
            $updates[] = 'tags = ?';
            $params[] = json_encode($data['tags']);
        }

        if (!empty($updates)) {
            $params[] = $entryId;
            $this->db->executeStatement(
                'UPDATE time_entries SET ' . implode(', ', $updates) . ' WHERE id = ?',
                $params
            );
        }

        return JsonResponse::success( ['message' => 'Eintrag aktualisiert']);
    }

    public function delete(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $entryId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $deleted = $this->db->delete('time_entries', [
            'id' => $entryId,
            'user_id' => $userId,
        ]);

        if (!$deleted) {
            return JsonResponse::error( 'Eintrag nicht gefunden', 404);
        }

        return JsonResponse::success( ['message' => 'Eintrag gelöscht']);
    }

    public function getStats(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $from = $params['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to = $params['to'] ?? date('Y-m-d');

        // Total time
        $totals = $this->db->fetchAssociative(
            'SELECT
                COUNT(*) as entry_count,
                SUM(duration_seconds) as total_seconds,
                SUM(CASE WHEN is_billable THEN duration_seconds ELSE 0 END) as billable_seconds,
                SUM(CASE WHEN is_billable AND hourly_rate IS NOT NULL THEN duration_seconds * hourly_rate / 3600 ELSE 0 END) as total_earnings
             FROM time_entries
             WHERE user_id = ? AND started_at >= ? AND started_at <= ?',
            [$userId, $from, $to . ' 23:59:59']
        );

        // By project
        $byProject = $this->db->fetchAllAssociative(
            'SELECT
                p.id, p.name, p.color,
                COUNT(*) as entry_count,
                SUM(te.duration_seconds) as total_seconds
             FROM time_entries te
             LEFT JOIN projects p ON p.id = te.project_id
             WHERE te.user_id = ? AND te.started_at >= ? AND te.started_at <= ?
             GROUP BY te.project_id
             ORDER BY total_seconds DESC',
            [$userId, $from, $to . ' 23:59:59']
        );

        // By day
        $byDay = $this->db->fetchAllAssociative(
            'SELECT
                DATE(started_at) as date,
                SUM(duration_seconds) as total_seconds
             FROM time_entries
             WHERE user_id = ? AND started_at >= ? AND started_at <= ?
             GROUP BY DATE(started_at)
             ORDER BY date',
            [$userId, $from, $to . ' 23:59:59']
        );

        return JsonResponse::success( [
            'period' => ['from' => $from, 'to' => $to],
            'totals' => [
                'entry_count' => (int) $totals['entry_count'],
                'total_seconds' => (int) ($totals['total_seconds'] ?? 0),
                'billable_seconds' => (int) ($totals['billable_seconds'] ?? 0),
                'total_earnings' => round((float) ($totals['total_earnings'] ?? 0), 2),
            ],
            'by_project' => $byProject,
            'by_day' => $byDay,
        ]);
    }

    public function getProjects(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $projects = $this->db->fetchAllAssociative(
            'SELECT id, name, color FROM projects WHERE user_id = ? AND status = ? ORDER BY name',
            [$userId, 'active']
        );

        return JsonResponse::success( ['projects' => $projects]);
    }

    private function stopEntry(string $entryId): void
    {
        $entry = $this->db->fetchAssociative(
            'SELECT started_at FROM time_entries WHERE id = ?',
            [$entryId]
        );

        if (!$entry) {
            return;
        }

        $start = new \DateTime($entry['started_at']);
        $end = new \DateTime();
        $duration = $end->getTimestamp() - $start->getTimestamp();

        $this->db->executeStatement(
            'UPDATE time_entries SET is_running = 0, ended_at = NOW(), duration_seconds = ? WHERE id = ?',
            [$duration, $entryId]
        );
    }

    private function calculateDuration(array $entry): int
    {
        if ($entry['duration_seconds']) {
            return (int) $entry['duration_seconds'];
        }

        if ($entry['is_running']) {
            $start = new \DateTime($entry['started_at']);
            $now = new \DateTime();
            return $now->getTimestamp() - $start->getTimestamp();
        }

        if ($entry['ended_at']) {
            $start = new \DateTime($entry['started_at']);
            $end = new \DateTime($entry['ended_at']);
            return $end->getTimestamp() - $start->getTimestamp();
        }

        return 0;
    }
}
