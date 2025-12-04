<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AnalyticsController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function getProductivityStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $days = min(90, max(7, (int) ($queryParams['days'] ?? 30)));

        $stats = [
            'tasks_completed' => $this->getTaskCompletionStats($userId, $days),
            'time_tracked' => $this->getTimeTrackingStats($userId, $days),
            'activity_heatmap' => $this->getActivityHeatmap($userId, $days),
            'kanban_flow' => $this->getKanbanFlowStats($userId, $days),
            'productivity_score' => $this->calculateProductivityScore($userId, $days),
        ];

        return JsonResponse::success($stats);
    }

    public function getTaskCompletionStats(string $userId, int $days, ?string $projectId = null): array
    {
        if ($projectId) {
            // Daily completed tasks (with project filter via project_links)
            $dailyCompleted = $this->db->fetchAllAssociative(
                "SELECT DATE(li.updated_at) as date, COUNT(*) as count
                 FROM list_items li
                 JOIN lists l ON li.list_id = l.id
                 INNER JOIN project_links pl ON pl.linkable_id = l.id AND pl.linkable_type = 'list'
                 WHERE l.user_id = ? AND li.is_completed = TRUE
                   AND li.updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND pl.project_id = ?
                 GROUP BY DATE(li.updated_at)
                 ORDER BY date",
                [$userId, $days, $projectId],
                [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_STR]
            );

            // Total stats
            $totals = $this->db->fetchAssociative(
                "SELECT
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN is_completed = TRUE THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN is_completed = TRUE AND li.updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 ELSE 0 END) as completed_period
                 FROM list_items li
                 JOIN lists l ON li.list_id = l.id
                 INNER JOIN project_links pl ON pl.linkable_id = l.id AND pl.linkable_type = 'list'
                 WHERE l.user_id = ? AND pl.project_id = ?",
                [$days, $userId, $projectId],
                [\PDO::PARAM_INT, \PDO::PARAM_STR, \PDO::PARAM_STR]
            );
        } else {
            // Daily completed tasks (without project filter)
            $dailyCompleted = $this->db->fetchAllAssociative(
                "SELECT DATE(li.updated_at) as date, COUNT(*) as count
                 FROM list_items li
                 JOIN lists l ON li.list_id = l.id
                 WHERE l.user_id = ? AND li.is_completed = TRUE
                   AND li.updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY DATE(li.updated_at)
                 ORDER BY date",
                [$userId, $days],
                [\PDO::PARAM_STR, \PDO::PARAM_INT]
            );

            // Total stats
            $totals = $this->db->fetchAssociative(
                "SELECT
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN is_completed = TRUE THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN is_completed = TRUE AND li.updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 ELSE 0 END) as completed_period
                 FROM list_items li
                 JOIN lists l ON li.list_id = l.id
                 WHERE l.user_id = ?",
                [$days, $userId],
                [\PDO::PARAM_INT, \PDO::PARAM_STR]
            );
        }

        // Average per day
        $avgPerDay = $totals['completed_period'] > 0 ? round($totals['completed_period'] / $days, 1) : 0;

        return [
            'daily' => $dailyCompleted,
            'total' => (int) $totals['total_tasks'],
            'completed' => (int) $totals['completed_tasks'],
            'completed_period' => (int) $totals['completed_period'],
            'completion_rate' => $totals['total_tasks'] > 0
                ? round(($totals['completed_tasks'] / $totals['total_tasks']) * 100, 1)
                : 0,
            'avg_per_day' => $avgPerDay,
        ];
    }

    public function getTimeTrackingStats(string $userId, int $days): array
    {
        // Daily tracked time
        $dailyTime = $this->db->fetchAllAssociative(
            "SELECT DATE(started_at) as date,
                    SUM(TIMESTAMPDIFF(MINUTE, started_at, COALESCE(ended_at, NOW()))) as minutes
             FROM time_entries
             WHERE user_id = ? AND started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(started_at)
             ORDER BY date",
            [$userId, $days],
            [\PDO::PARAM_STR, \PDO::PARAM_INT]
        );

        // Total time in period
        $totalMinutes = $this->db->fetchOne(
            "SELECT SUM(TIMESTAMPDIFF(MINUTE, started_at, COALESCE(ended_at, NOW())))
             FROM time_entries
             WHERE user_id = ? AND started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$userId, $days],
            [\PDO::PARAM_STR, \PDO::PARAM_INT]
        ) ?? 0;

        // By project
        $byProject = $this->db->fetchAllAssociative(
            "SELECT p.name as project_name, p.color,
                    SUM(TIMESTAMPDIFF(MINUTE, te.started_at, COALESCE(te.ended_at, NOW()))) as minutes
             FROM time_entries te
             LEFT JOIN projects p ON te.project_id = p.id
             WHERE te.user_id = ? AND te.started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY te.project_id, p.name, p.color
             ORDER BY minutes DESC
             LIMIT 10",
            [$userId, $days],
            [\PDO::PARAM_STR, \PDO::PARAM_INT]
        );

        // Average per day (only count days with entries)
        $daysWithEntries = count(array_filter($dailyTime, fn($d) => $d['minutes'] > 0));
        $avgMinutesPerDay = $daysWithEntries > 0 ? round($totalMinutes / $daysWithEntries) : 0;

        return [
            'daily' => $dailyTime,
            'total_hours' => round($totalMinutes / 60, 1),
            'avg_hours_per_day' => round($avgMinutesPerDay / 60, 1),
            'by_project' => array_map(function ($p) {
                $p['hours'] = round((int) $p['minutes'] / 60, 1);
                return $p;
            }, $byProject),
        ];
    }

    public function getActivityHeatmap(string $userId, int $days): array
    {
        // Activity by hour and day of week
        $heatmap = $this->db->fetchAllAssociative(
            "SELECT
                DAYOFWEEK(created_at) as day_of_week,
                HOUR(created_at) as hour,
                COUNT(*) as count
             FROM audit_logs
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DAYOFWEEK(created_at), HOUR(created_at)",
            [$userId, $days],
            [\PDO::PARAM_STR, \PDO::PARAM_INT]
        );

        // Convert to matrix format
        $matrix = [];
        foreach ($heatmap as $entry) {
            $day = (int) $entry['day_of_week'] - 1; // 0 = Sunday
            $hour = (int) $entry['hour'];
            if (!isset($matrix[$day])) {
                $matrix[$day] = array_fill(0, 24, 0);
            }
            $matrix[$day][$hour] = (int) $entry['count'];
        }

        return [
            'data' => $matrix,
            'days' => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        ];
    }

    public function getKanbanFlowStats(string $userId, int $days): array
    {
        // Cards created vs completed
        $cardsCreated = $this->db->fetchAllAssociative(
            "SELECT DATE(kc.created_at) as date, COUNT(*) as count
             FROM kanban_cards kc
             JOIN kanban_columns col ON kc.column_id = col.id
             JOIN kanban_boards kb ON col.board_id = kb.id
             WHERE kb.user_id = ? AND kc.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(kc.created_at)
             ORDER BY date",
            [$userId, $days],
            [\PDO::PARAM_STR, \PDO::PARAM_INT]
        );

        // Cards by column (current state)
        $cardsByColumn = $this->db->fetchAllAssociative(
            "SELECT col.title as column_name, col.color, COUNT(kc.id) as count
             FROM kanban_columns col
             JOIN kanban_boards kb ON col.board_id = kb.id
             LEFT JOIN kanban_cards kc ON kc.column_id = col.id
             WHERE kb.user_id = ?
             GROUP BY col.id, col.title, col.color
             ORDER BY col.position",
            [$userId]
        );

        // Average time in columns (based on activity log)
        $avgTimeInColumn = $this->db->fetchAllAssociative(
            "SELECT
                kca.details->>'$.to_column' as column_name,
                AVG(TIMESTAMPDIFF(HOUR, kca.created_at,
                    COALESCE(
                        (SELECT MIN(kca2.created_at)
                         FROM kanban_card_activities kca2
                         WHERE kca2.card_id = kca.card_id
                           AND kca2.action = 'moved'
                           AND kca2.created_at > kca.created_at),
                        NOW()
                    )
                )) as avg_hours
             FROM kanban_card_activities kca
             WHERE kca.action = 'moved'
               AND kca.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY kca.details->>'$.to_column'",
            [$days],
            [\PDO::PARAM_INT]
        );

        return [
            'cards_created' => $cardsCreated,
            'cards_by_column' => $cardsByColumn,
            'avg_time_in_column' => $avgTimeInColumn,
        ];
    }

    public function calculateProductivityScore(string $userId, int $days): array
    {
        // Factors for productivity score
        $tasksCompleted = (int) $this->db->fetchOne(
            "SELECT COUNT(*)
             FROM list_items li
             JOIN lists l ON li.list_id = l.id
             WHERE l.user_id = ? AND li.is_completed = TRUE
               AND li.updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$userId, $days],
            [\PDO::PARAM_STR, \PDO::PARAM_INT]
        );

        $timeTrackedHours = (float) $this->db->fetchOne(
            "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, started_at, COALESCE(ended_at, NOW()))) / 60, 0)
             FROM time_entries
             WHERE user_id = ? AND started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$userId, $days],
            [\PDO::PARAM_STR, \PDO::PARAM_INT]
        );

        $activeDays = (int) $this->db->fetchOne(
            "SELECT COUNT(DISTINCT DATE(created_at))
             FROM audit_logs
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$userId, $days],
            [\PDO::PARAM_STR, \PDO::PARAM_INT]
        );

        // Calculate score (0-100)
        $taskScore = min(100, ($tasksCompleted / $days) * 20); // 5 tasks/day = 100
        $timeScore = min(100, ($timeTrackedHours / $days) * 12.5); // 8h/day = 100
        $consistencyScore = min(100, ($activeDays / $days) * 100);

        $totalScore = round(($taskScore * 0.4 + $timeScore * 0.3 + $consistencyScore * 0.3), 0);

        return [
            'score' => $totalScore,
            'factors' => [
                'tasks' => round($taskScore),
                'time' => round($timeScore),
                'consistency' => round($consistencyScore),
            ],
            'details' => [
                'tasks_completed' => $tasksCompleted,
                'hours_tracked' => round($timeTrackedHours, 1),
                'active_days' => $activeDays,
                'period_days' => $days,
            ],
        ];
    }

    public function getWidgetData(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $widgetType = $queryParams['type'] ?? null;
        $projectId = $queryParams['project_id'] ?? null;

        if (!$widgetType) {
            return JsonResponse::error('Widget type required', 400);
        }

        $data = match ($widgetType) {
            'quick_stats' => $this->getQuickStatsData($userId, $projectId),
            'recent_tasks' => $this->getRecentTasksData($userId, $projectId),
            'recent_documents' => $this->getRecentDocumentsData($userId, $projectId),
            'uptime_status' => $this->getUptimeStatusData($userId),
            'time_tracking_today' => $this->getTimeTrackingTodayData($userId, $projectId),
            'kanban_summary' => $this->getKanbanSummaryData($userId, $projectId),
            'productivity_chart' => $this->getProductivityChartData($userId, $projectId),
            'calendar_preview' => $this->getCalendarPreviewData($userId, $projectId),
            'quick_notes' => $this->getQuickNotesData($userId),
            'recent_activity' => $this->getRecentActivityData($userId, $projectId),
            default => null,
        };

        if ($data === null) {
            return JsonResponse::error('Unknown widget type', 400);
        }

        return JsonResponse::success($data);
    }

    private function getQuickStatsData(string $userId, ?string $projectId = null): array
    {
        // Lists and documents use project_links, kanban uses project_links too
        if ($projectId) {
            $lists = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM lists l
                 INNER JOIN project_links pl ON pl.linkable_id = l.id AND pl.linkable_type = 'list'
                 WHERE l.user_id = ? AND l.is_archived = FALSE AND pl.project_id = ?",
                [$userId, $projectId]
            );

            $openTasks = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM list_items li
                 JOIN lists l ON li.list_id = l.id
                 INNER JOIN project_links pl ON pl.linkable_id = l.id AND pl.linkable_type = 'list'
                 WHERE l.user_id = ? AND li.is_completed = FALSE AND pl.project_id = ?",
                [$userId, $projectId]
            );

            $documents = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM documents d
                 INNER JOIN project_links pl ON pl.linkable_id = d.id AND pl.linkable_type = 'document'
                 WHERE d.user_id = ? AND d.is_archived = FALSE AND pl.project_id = ?",
                [$userId, $projectId]
            );

            $kanbanCards = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM kanban_cards kc
                 JOIN kanban_columns col ON kc.column_id = col.id
                 JOIN kanban_boards kb ON col.board_id = kb.id
                 INNER JOIN project_links pl ON pl.linkable_id = kb.id AND pl.linkable_type = 'kanban_board'
                 WHERE kb.user_id = ? AND pl.project_id = ?",
                [$userId, $projectId]
            );
        } else {
            $lists = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM lists WHERE user_id = ? AND is_archived = FALSE",
                [$userId]
            );

            $openTasks = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM list_items li
                 JOIN lists l ON li.list_id = l.id
                 WHERE l.user_id = ? AND li.is_completed = FALSE",
                [$userId]
            );

            $documents = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM documents WHERE user_id = ? AND is_archived = FALSE",
                [$userId]
            );

            $kanbanCards = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM kanban_cards kc
                 JOIN kanban_columns col ON kc.column_id = col.id
                 JOIN kanban_boards kb ON col.board_id = kb.id
                 WHERE kb.user_id = ?",
                [$userId]
            );
        }

        return [
            'lists' => $lists,
            'open_tasks' => $openTasks,
            'documents' => $documents,
            'kanban_cards' => $kanbanCards,
        ];
    }

    private function getRecentTasksData(string $userId, ?string $projectId = null): array
    {
        if ($projectId) {
            return $this->db->fetchAllAssociative(
                "SELECT li.id, li.content, li.is_completed, li.due_date, l.title as list_title, l.color
                 FROM list_items li
                 JOIN lists l ON li.list_id = l.id
                 INNER JOIN project_links pl ON pl.linkable_id = l.id AND pl.linkable_type = 'list'
                 WHERE l.user_id = ? AND li.is_completed = FALSE AND pl.project_id = ?
                 ORDER BY li.due_date ASC, li.created_at DESC
                 LIMIT 10",
                [$userId, $projectId]
            );
        }

        return $this->db->fetchAllAssociative(
            "SELECT li.id, li.content, li.is_completed, li.due_date, l.title as list_title, l.color
             FROM list_items li
             JOIN lists l ON li.list_id = l.id
             WHERE l.user_id = ? AND li.is_completed = FALSE
             ORDER BY li.due_date ASC, li.created_at DESC
             LIMIT 10",
            [$userId]
        );
    }

    private function getRecentDocumentsData(string $userId, ?string $projectId = null): array
    {
        if ($projectId) {
            return $this->db->fetchAllAssociative(
                "SELECT d.id, d.title, d.format, d.updated_at
                 FROM documents d
                 INNER JOIN project_links pl ON pl.linkable_id = d.id AND pl.linkable_type = 'document'
                 WHERE d.user_id = ? AND d.is_archived = FALSE AND pl.project_id = ?
                 ORDER BY d.updated_at DESC
                 LIMIT 5",
                [$userId, $projectId]
            );
        }

        return $this->db->fetchAllAssociative(
            "SELECT id, title, format, updated_at FROM documents WHERE user_id = ? AND is_archived = FALSE ORDER BY updated_at DESC LIMIT 5",
            [$userId]
        );
    }

    private function getUptimeStatusData(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT um.id, um.name, um.url, um.current_status as status, um.last_check_at as last_check,
                    (SELECT uc.response_time FROM uptime_checks uc WHERE uc.monitor_id = um.id ORDER BY uc.checked_at DESC LIMIT 1) as response_time
             FROM uptime_monitors um
             WHERE um.user_id = ? AND um.is_active = TRUE
             ORDER BY um.current_status DESC, um.name ASC
             LIMIT 10",
            [$userId]
        );
    }

    private function getTimeTrackingTodayData(string $userId, ?string $projectId = null): array
    {
        $params = [$userId];
        $projectFilter = '';
        if ($projectId) {
            $projectFilter = ' AND project_id = ?';
            $params[] = $projectId;
        }

        $today = $this->db->fetchAssociative(
            "SELECT
                SUM(TIMESTAMPDIFF(MINUTE, started_at, COALESCE(ended_at, NOW()))) as total_minutes,
                COUNT(*) as entry_count
             FROM time_entries
             WHERE user_id = ? AND DATE(started_at) = CURDATE(){$projectFilter}",
            $params
        );

        $runningParams = [$userId];
        $runningProjectFilter = '';
        if ($projectId) {
            $runningProjectFilter = ' AND te.project_id = ?';
            $runningParams[] = $projectId;
        }

        $running = $this->db->fetchAssociative(
            "SELECT te.*, p.name as project_name, p.color as project_color
             FROM time_entries te
             LEFT JOIN projects p ON te.project_id = p.id
             WHERE te.user_id = ? AND te.ended_at IS NULL{$runningProjectFilter}
             ORDER BY te.started_at DESC
             LIMIT 1",
            $runningParams
        );

        return [
            'total_hours' => round(($today['total_minutes'] ?? 0) / 60, 1),
            'entry_count' => (int) ($today['entry_count'] ?? 0),
            'running' => $running,
        ];
    }

    private function getKanbanSummaryData(string $userId, ?string $projectId = null): array
    {
        if ($projectId) {
            $boards = $this->db->fetchAllAssociative(
                "SELECT kb.id, kb.title,
                        (SELECT COUNT(*) FROM kanban_cards kc JOIN kanban_columns col ON kc.column_id = col.id WHERE col.board_id = kb.id) as card_count
                 FROM kanban_boards kb
                 INNER JOIN project_links pl ON pl.linkable_id = kb.id AND pl.linkable_type = 'kanban_board'
                 WHERE kb.user_id = ? AND kb.is_archived = FALSE AND pl.project_id = ?
                 ORDER BY kb.updated_at DESC
                 LIMIT 5",
                [$userId, $projectId]
            );

            $dueSoon = $this->db->fetchAllAssociative(
                "SELECT kc.id, kc.title, kc.due_date, kb.id as board_id, kb.title as board_title
                 FROM kanban_cards kc
                 JOIN kanban_columns col ON kc.column_id = col.id
                 JOIN kanban_boards kb ON col.board_id = kb.id
                 INNER JOIN project_links pl ON pl.linkable_id = kb.id AND pl.linkable_type = 'kanban_board'
                 WHERE kb.user_id = ? AND kc.due_date IS NOT NULL AND kc.due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY) AND pl.project_id = ?
                 ORDER BY kc.due_date ASC
                 LIMIT 5",
                [$userId, $projectId]
            );
        } else {
            $boards = $this->db->fetchAllAssociative(
                "SELECT kb.id, kb.title,
                        (SELECT COUNT(*) FROM kanban_cards kc JOIN kanban_columns col ON kc.column_id = col.id WHERE col.board_id = kb.id) as card_count
                 FROM kanban_boards kb
                 WHERE kb.user_id = ? AND kb.is_archived = FALSE
                 ORDER BY kb.updated_at DESC
                 LIMIT 5",
                [$userId]
            );

            $dueSoon = $this->db->fetchAllAssociative(
                "SELECT kc.id, kc.title, kc.due_date, kb.id as board_id, kb.title as board_title
                 FROM kanban_cards kc
                 JOIN kanban_columns col ON kc.column_id = col.id
                 JOIN kanban_boards kb ON col.board_id = kb.id
                 WHERE kb.user_id = ? AND kc.due_date IS NOT NULL AND kc.due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                 ORDER BY kc.due_date ASC
                 LIMIT 5",
                [$userId]
            );
        }

        return [
            'boards' => $boards,
            'due_soon' => $dueSoon,
        ];
    }

    private function getProductivityChartData(string $userId, ?string $projectId = null): array
    {
        return $this->getTaskCompletionStats($userId, 14, $projectId);
    }

    private function getCalendarPreviewData(string $userId, ?string $projectId = null): array
    {
        $startOfWeek = date('Y-m-d', strtotime('monday this week'));
        $endOfWeek = date('Y-m-d', strtotime('sunday this week'));

        // Custom calendar events (project_id column may or may not exist yet)
        if ($projectId) {
            // Try with project filter - will work after migration 035 runs
            try {
                $events = $this->db->fetchAllAssociative(
                    "SELECT id, title, start_date, end_date, all_day, color, 'event' as source_type
                     FROM calendar_events
                     WHERE user_id = ? AND start_date >= ? AND start_date <= ? AND (project_id = ? OR project_id IS NULL)
                     ORDER BY start_date",
                    [$userId, $startOfWeek, $endOfWeek . ' 23:59:59', $projectId]
                );
            } catch (\Exception $e) {
                // Fallback if project_id column doesn't exist yet
                $events = $this->db->fetchAllAssociative(
                    "SELECT id, title, start_date, end_date, all_day, color, 'event' as source_type
                     FROM calendar_events
                     WHERE user_id = ? AND start_date >= ? AND start_date <= ?
                     ORDER BY start_date",
                    [$userId, $startOfWeek, $endOfWeek . ' 23:59:59']
                );
            }

            // Kanban due dates (uses project_links)
            $kanbanDue = $this->db->fetchAllAssociative(
                "SELECT kc.id, kc.title, kc.due_date as start_date, NULL as end_date, TRUE as all_day, 'red' as color, 'kanban' as source_type
                 FROM kanban_cards kc
                 JOIN kanban_columns col ON kc.column_id = col.id
                 JOIN kanban_boards kb ON col.board_id = kb.id
                 INNER JOIN project_links pl ON pl.linkable_id = kb.id AND pl.linkable_type = 'kanban_board'
                 WHERE kb.user_id = ? AND kc.due_date >= ? AND kc.due_date <= ? AND pl.project_id = ?",
                [$userId, $startOfWeek, $endOfWeek, $projectId]
            );

            // List item due dates (uses project_links)
            $tasksDue = $this->db->fetchAllAssociative(
                "SELECT li.id, li.content as title, li.due_date as start_date, NULL as end_date, TRUE as all_day, 'blue' as color, 'task' as source_type
                 FROM list_items li
                 JOIN lists l ON li.list_id = l.id
                 INNER JOIN project_links pl ON pl.linkable_id = l.id AND pl.linkable_type = 'list'
                 WHERE l.user_id = ? AND li.due_date >= ? AND li.due_date <= ? AND li.is_completed = FALSE AND pl.project_id = ?",
                [$userId, $startOfWeek, $endOfWeek, $projectId]
            );
        } else {
            $events = $this->db->fetchAllAssociative(
                "SELECT id, title, start_date, end_date, all_day, color, 'event' as source_type
                 FROM calendar_events
                 WHERE user_id = ? AND start_date >= ? AND start_date <= ?
                 ORDER BY start_date",
                [$userId, $startOfWeek, $endOfWeek . ' 23:59:59']
            );

            $kanbanDue = $this->db->fetchAllAssociative(
                "SELECT kc.id, kc.title, kc.due_date as start_date, NULL as end_date, TRUE as all_day, 'red' as color, 'kanban' as source_type
                 FROM kanban_cards kc
                 JOIN kanban_columns col ON kc.column_id = col.id
                 JOIN kanban_boards kb ON col.board_id = kb.id
                 WHERE kb.user_id = ? AND kc.due_date >= ? AND kc.due_date <= ?",
                [$userId, $startOfWeek, $endOfWeek]
            );

            $tasksDue = $this->db->fetchAllAssociative(
                "SELECT li.id, li.content as title, li.due_date as start_date, NULL as end_date, TRUE as all_day, 'blue' as color, 'task' as source_type
                 FROM list_items li
                 JOIN lists l ON li.list_id = l.id
                 WHERE l.user_id = ? AND li.due_date >= ? AND li.due_date <= ? AND li.is_completed = FALSE",
                [$userId, $startOfWeek, $endOfWeek]
            );
        }

        return array_merge($events, $kanbanDue, $tasksDue);
    }

    private function getQuickNotesData(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT * FROM quick_notes WHERE user_id = ? ORDER BY is_pinned DESC, position ASC LIMIT 5',
            [$userId]
        );
    }

    private function getRecentActivityData(string $userId, ?string $projectId = null): array
    {
        // Note: audit_logs may not have project_id, so this is a simple filter
        // For now, we return all activity regardless of project filter
        // as audit logs typically don't have project association
        return $this->db->fetchAllAssociative(
            'SELECT action, entity_type, entity_id, created_at
             FROM audit_logs
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 15',
            [$userId]
        );
    }
}
