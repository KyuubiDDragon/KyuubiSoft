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
            $sql .= ' AND (te.started_at >= ? OR (te.started_at IS NULL AND te.entry_month >= ?))';
            $sqlParams[] = $from;
            $sqlParams[] = substr($from, 0, 7);
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
        }

        if ($to) {
            $sql .= ' AND (te.started_at <= ? OR (te.started_at IS NULL AND te.entry_month <= ?))';
            $sqlParams[] = $to . ' 23:59:59';
            $sqlParams[] = substr($to, 0, 7);
            $types[] = \PDO::PARAM_STR;
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
            $countSql .= ' AND (te.started_at >= ? OR (te.started_at IS NULL AND te.entry_month >= ?))';
            $countParams[] = $from;
            $countParams[] = substr($from, 0, 7);
        }
        if ($to) {
            $countSql .= ' AND (te.started_at <= ? OR (te.started_at IS NULL AND te.entry_month <= ?))';
            $countParams[] = $to . ' 23:59:59';
            $countParams[] = substr($to, 0, 7);
        }
        $total = (int) $this->db->fetchOne($countSql, $countParams);

        $sql .= ' ORDER BY COALESCE(CAST(te.started_at AS CHAR), CONCAT(te.entry_month, "-99 23:59:59")) DESC, te.created_at DESC LIMIT ? OFFSET ?';
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
        $entryMonth = $data['entry_month'] ?? null;

        if ($entryMonth) {
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $entryMonth)) {
                return JsonResponse::error('Ungültiges Monatsformat (YYYY-MM)', 400);
            }
            if (!$durationSeconds || (int) $durationSeconds <= 0) {
                return JsonResponse::error('Dauer ist für Monatseinträge erforderlich', 400);
            }
            $startedAt = null;
            $endedAt = null;
        } else {
            if (!$startedAt) {
                return JsonResponse::error('Startzeit ist erforderlich', 400);
            }

            // Calculate duration if ended_at provided
            if ($endedAt && !$durationSeconds) {
                $start = new \DateTime($startedAt);
                $end = new \DateTime($endedAt);
                $durationSeconds = $end->getTimestamp() - $start->getTimestamp();
            }
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
            'entry_month' => $entryMonth,
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

        if (array_key_exists('started_at', $data)) {
            $updates[] = 'started_at = ?';
            $params[] = $data['started_at'] ?: null;
        }

        if (array_key_exists('ended_at', $data)) {
            $updates[] = 'ended_at = ?';
            $params[] = $data['ended_at'] ?: null;
        }

        if (array_key_exists('entry_month', $data)) {
            $month = $data['entry_month'] ?: null;
            if ($month !== null && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                return JsonResponse::error('Ungültiges Monatsformat (YYYY-MM)', 400);
            }
            $updates[] = 'entry_month = ?';
            $params[] = $month;
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

        $fromMonth = substr($from, 0, 7);
        $toMonth = substr($to, 0, 7);

        // Total time
        $totals = $this->db->fetchAssociative(
            'SELECT
                COUNT(*) as entry_count,
                SUM(duration_seconds) as total_seconds,
                SUM(CASE WHEN is_billable THEN duration_seconds ELSE 0 END) as billable_seconds,
                SUM(CASE WHEN is_billable AND hourly_rate IS NOT NULL THEN duration_seconds * hourly_rate / 3600 ELSE 0 END) as total_earnings
             FROM time_entries
             WHERE user_id = ?
               AND (
                   (started_at >= ? AND started_at <= ?)
                   OR (started_at IS NULL AND entry_month >= ? AND entry_month <= ?)
               )',
            [$userId, $from, $to . ' 23:59:59', $fromMonth, $toMonth]
        );

        // By project
        $byProject = $this->db->fetchAllAssociative(
            'SELECT
                p.id, p.name, p.color,
                COUNT(*) as entry_count,
                SUM(te.duration_seconds) as total_seconds
             FROM time_entries te
             LEFT JOIN projects p ON p.id = te.project_id
             WHERE te.user_id = ?
               AND (
                   (te.started_at >= ? AND te.started_at <= ?)
                   OR (te.started_at IS NULL AND te.entry_month >= ? AND te.entry_month <= ?)
               )
             GROUP BY te.project_id
             ORDER BY total_seconds DESC',
            [$userId, $from, $to . ' 23:59:59', $fromMonth, $toMonth]
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

        if (empty($entry['started_at'])) {
            return 0;
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

    public function exportCsv(Request $request, Response $response): Response
    {
        [$entries, $filters] = $this->fetchForExport($request);
        if ($entries === null) {
            return JsonResponse::error('Keine Berechtigung für dieses Projekt', 403);
        }

        $lang = $this->parseLang($request);
        $columns = $this->parseColumns($request);
        $labels = $this->translations()[$lang];

        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM so Excel detects encoding
        $csv .= implode(';', array_map([$this, 'csvEscape'], array_map(
            fn (string $col) => $labels['col_' . $col],
            $columns
        ))) . "\r\n";

        foreach ($entries as $entry) {
            $row = [];
            foreach ($columns as $col) {
                $row[] = $this->cellValue($entry, $col, $lang, $labels);
            }
            $csv .= implode(';', array_map([$this, 'csvEscape'], $row)) . "\r\n";
        }

        $filename = 'time-entries-' . date('Y-m-d') . '.csv';

        $response = $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->getBody()->write($csv);

        return $response;
    }

    public function exportPdf(Request $request, Response $response): Response
    {
        [$entries, $filters] = $this->fetchForExport($request);
        if ($entries === null) {
            return JsonResponse::error('Keine Berechtigung für dieses Projekt', 403);
        }

        $lang = $this->parseLang($request);
        $columns = $this->parseColumns($request);

        $totalSeconds = 0;
        $billableSeconds = 0;
        $billableAmount = 0.0;
        foreach ($entries as $entry) {
            $duration = (int) $entry['duration_seconds'];
            $totalSeconds += $duration;
            if ($entry['is_billable']) {
                $billableSeconds += $duration;
                $billableAmount += ($duration / 3600) * (float) ($entry['hourly_rate'] ?? 0);
            }
        }

        $html = $this->buildPdfHtml($entries, $filters, $totalSeconds, $billableSeconds, $billableAmount, $lang, $columns);

        $fontCacheDir = dirname(__DIR__, 4) . '/storage/cache/dompdf';
        if (!is_dir($fontCacheDir)) {
            mkdir($fontCacheDir, 0775, true);
        }

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => false,
            'defaultFont' => 'Helvetica',
            'fontDir' => $fontCacheDir,
            'fontCache' => $fontCacheDir,
            'tempDir' => sys_get_temp_dir(),
            'chroot' => dirname(__DIR__, 4),
        ]);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml($html);
        $dompdf->render();

        $filename = 'time-entries-' . date('Y-m-d') . '.pdf';

        $response = $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->getBody()->write($dompdf->output());

        return $response;
    }

    /**
     * Run the same filter logic as index() but without pagination, returning
     * all matching entries plus the filter context (for PDF header display).
     * Returns [null, []] on access denial.
     */
    private function fetchForExport(Request $request): array
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $projectId = $params['project_id'] ?? null;
        $from = $params['from'] ?? null;
        $to = $params['to'] ?? null;

        $isRestricted = $this->projectAccess->isUserRestricted($userId);
        $accessibleProjectIds = $isRestricted ? $this->projectAccess->getUserAccessibleProjectIds($userId) : [];

        if ($projectId && $isRestricted && !in_array($projectId, $accessibleProjectIds)) {
            return [null, []];
        }

        $sql = 'SELECT te.*, p.name as project_name, p.color as project_color
                FROM time_entries te
                LEFT JOIN projects p ON p.id = te.project_id
                WHERE te.user_id = ?';
        $sqlParams = [$userId];
        $types = [\PDO::PARAM_STR];

        if ($isRestricted && !$projectId) {
            if (empty($accessibleProjectIds)) {
                return [[], compact('projectId', 'from', 'to')];
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
            $sql .= ' AND (te.started_at >= ? OR (te.started_at IS NULL AND te.entry_month >= ?))';
            $sqlParams[] = $from;
            $sqlParams[] = substr($from, 0, 7);
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
        }

        if ($to) {
            $sql .= ' AND (te.started_at <= ? OR (te.started_at IS NULL AND te.entry_month <= ?))';
            $sqlParams[] = $to . ' 23:59:59';
            $sqlParams[] = substr($to, 0, 7);
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
        }

        $sql .= ' ORDER BY COALESCE(CAST(te.started_at AS CHAR), CONCAT(te.entry_month, "-99 23:59:59")) DESC, te.created_at DESC';

        $entries = $this->db->fetchAllAssociative($sql, $sqlParams, $types);

        foreach ($entries as &$entry) {
            $entry['tags'] = json_decode($entry['tags'] ?? '[]', true);
            $entry['is_running'] = (bool) $entry['is_running'];
            $entry['is_billable'] = (bool) $entry['is_billable'];
            $entry['duration_seconds'] = $this->calculateDuration($entry);
        }
        unset($entry);

        return [$entries, compact('projectId', 'from', 'to')];
    }

    private function csvEscape($value): string
    {
        $str = (string) $value;
        if (strpbrk($str, ";\"\n\r") !== false) {
            return '"' . str_replace('"', '""', $str) . '"';
        }
        return $str;
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 0) {
            $seconds = 0;
        }
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        return sprintf('%d:%02d', $hours, $minutes);
    }

    private function buildPdfHtml(array $entries, array $filters, int $totalSeconds, int $billableSeconds, float $billableAmount, string $lang, array $columns): string
    {
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $labels = $this->translations()[$lang];

        $filterLines = [];
        if (!empty($filters['from']) || !empty($filters['to'])) {
            $filterLines[] = $labels['period'] . ': ' . ($filters['from'] ?: '…') . ' — ' . ($filters['to'] ?: '…');
        }
        if (!empty($filters['projectId']) && !empty($entries)) {
            $projectLabel = $entries[0]['project_name'] ?? '';
            if ($projectLabel !== '') {
                $filterLines[] = $labels['col_project'] . ': ' . $projectLabel;
            }
        }

        $numericColumns = ['duration', 'rate', 'amount'];

        $headerCells = '';
        foreach ($columns as $col) {
            $cls = in_array($col, $numericColumns, true) ? ' class="num"' : '';
            $headerCells .= '<th' . $cls . '>' . $esc($labels['col_' . $col]) . '</th>';
        }

        $rows = '';
        foreach ($entries as $entry) {
            $rows .= '<tr>';
            foreach ($columns as $col) {
                $value = $this->cellValue($entry, $col, $lang, $labels);
                $cls = in_array($col, $numericColumns, true) ? ' class="num"' : '';
                $rows .= '<td' . $cls . '>' . $esc($value !== '' ? $value : '—') . '</td>';
            }
            $rows .= '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="' . count($columns) . '" style="text-align:center;color:#888;padding:20px;">'
                . $esc($labels['no_entries']) . '</td></tr>';
        }

        $filtersHtml = '';
        foreach ($filterLines as $line) {
            $filtersHtml .= '<div>' . $esc($line) . '</div>';
        }

        $dateFmt = $lang === 'en' ? 'Y-m-d H:i' : 'd.m.Y H:i';
        $generatedAt = (new \DateTime())->format($dateFmt);

        $billableLine = $labels['billable_summary'] . ': ' . $this->formatDuration($billableSeconds) . ' h';
        if (in_array('amount', $columns, true) || in_array('rate', $columns, true)) {
            $billableLine .= '  (' . $this->formatMoney($billableAmount, $lang) . ')';
        }

        return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { font-family: Helvetica, sans-serif; }
  body { color: #222; font-size: 10px; }
  h1 { font-size: 18px; margin: 0 0 4px 0; }
  .meta { color: #666; font-size: 10px; margin-bottom: 12px; }
  .summary { margin: 12px 0; padding: 8px 12px; background: #f3f4f6; border-radius: 4px; font-size: 11px; }
  .summary strong { display: inline-block; min-width: 140px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th { background: #222; color: #fff; text-align: left; padding: 6px; font-size: 10px; }
  td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; font-size: 9.5px; vertical-align: top; }
  td.num, th.num { text-align: right; white-space: nowrap; }
  tr:nth-child(even) td { background: #fafafa; }
</style>
</head>
<body>
  <h1>' . $esc($labels['title']) . '</h1>
  <div class="meta">' . $esc($labels['generated_at']) . ' ' . $esc($generatedAt) . '</div>
  ' . $filtersHtml . '
  <div class="summary">
    <div><strong>' . $esc($labels['entries']) . ':</strong> ' . count($entries) . '</div>
    <div><strong>' . $esc($labels['total_time']) . ':</strong> ' . $esc($this->formatDuration($totalSeconds)) . ' h</div>
    <div><strong>' . $esc($billableLine) . '</strong></div>
  </div>
  <table>
    <thead><tr>' . $headerCells . '</tr></thead>
    <tbody>' . $rows . '</tbody>
  </table>
</body>
</html>';
    }

    private function availableColumns(): array
    {
        return ['date', 'start', 'end', 'duration', 'project', 'task', 'description', 'billable', 'rate', 'amount', 'tags'];
    }

    private function parseLang(Request $request): string
    {
        $lang = strtolower((string) ($request->getQueryParams()['lang'] ?? ''));
        return $lang === 'en' ? 'en' : 'de';
    }

    private function parseColumns(Request $request): array
    {
        $raw = (string) ($request->getQueryParams()['columns'] ?? '');
        $requested = array_filter(array_map('trim', explode(',', $raw)));
        $available = $this->availableColumns();
        $selected = array_values(array_intersect($available, $requested));
        return empty($selected) ? $available : $selected;
    }

    private function cellValue(array $entry, string $col, string $lang, array $labels): string
    {
        $started = $entry['started_at'] ? new \DateTime($entry['started_at']) : null;
        $ended = $entry['ended_at'] ? new \DateTime($entry['ended_at']) : null;
        $duration = (int) $entry['duration_seconds'];
        $rate = (float) ($entry['hourly_rate'] ?? 0);
        $amount = $entry['is_billable'] ? ($duration / 3600) * $rate : 0;
        $tags = is_array($entry['tags']) ? $entry['tags'] : json_decode($entry['tags'] ?? '[]', true);
        $dateFmt = $lang === 'en' ? 'Y-m-d' : 'd.m.Y';

        return match ($col) {
            'date' => $started ? $started->format($dateFmt) : ($entry['entry_month'] ?? ''),
            'start' => $started ? $started->format('H:i') : '',
            'end' => $ended ? $ended->format('H:i') : ($entry['is_running'] ? $labels['running'] : ''),
            'duration' => $this->formatDuration($duration),
            'project' => (string) ($entry['project_name'] ?? ''),
            'task' => (string) ($entry['task_name'] ?? ''),
            'description' => (string) ($entry['description'] ?? ''),
            'billable' => $entry['is_billable'] ? $labels['yes'] : $labels['no'],
            'rate' => $rate > 0 ? $this->formatMoney($rate, $lang) : '',
            'amount' => $entry['is_billable'] ? $this->formatMoney($amount, $lang) : '',
            'tags' => is_array($tags) ? implode(', ', $tags) : '',
            default => '',
        };
    }

    private function formatMoney(float $value, string $lang): string
    {
        if ($lang === 'en') {
            return '€ ' . number_format($value, 2, '.', ',');
        }
        return number_format($value, 2, ',', '.') . ' €';
    }

    private function translations(): array
    {
        return [
            'de' => [
                'title' => 'Zeiterfassungs-Export',
                'generated_at' => 'Erstellt am',
                'period' => 'Zeitraum',
                'entries' => 'Einträge',
                'total_time' => 'Gesamtzeit',
                'billable_summary' => 'Abrechenbar',
                'no_entries' => 'Keine Einträge gefunden.',
                'running' => 'läuft',
                'yes' => 'ja',
                'no' => 'nein',
                'col_date' => 'Datum',
                'col_start' => 'Start',
                'col_end' => 'Ende',
                'col_duration' => 'Dauer',
                'col_project' => 'Projekt',
                'col_task' => 'Aufgabe',
                'col_description' => 'Beschreibung',
                'col_billable' => 'Abrechenbar',
                'col_rate' => 'Stundensatz',
                'col_amount' => 'Betrag',
                'col_tags' => 'Tags',
            ],
            'en' => [
                'title' => 'Time Tracking Export',
                'generated_at' => 'Created on',
                'period' => 'Period',
                'entries' => 'Entries',
                'total_time' => 'Total time',
                'billable_summary' => 'Billable',
                'no_entries' => 'No entries found.',
                'running' => 'running',
                'yes' => 'yes',
                'no' => 'no',
                'col_date' => 'Date',
                'col_start' => 'Start',
                'col_end' => 'End',
                'col_duration' => 'Duration',
                'col_project' => 'Project',
                'col_task' => 'Task',
                'col_description' => 'Description',
                'col_billable' => 'Billable',
                'col_rate' => 'Hourly rate',
                'col_amount' => 'Amount',
                'col_tags' => 'Tags',
            ],
        ];
    }
}
