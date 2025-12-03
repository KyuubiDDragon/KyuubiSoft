<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DashboardController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $projectId = $queryParams['project_id'] ?? null;

        $data = [
            'welcome_message' => 'Welcome to KyuubiSoft Dashboard',
            'quick_stats' => $this->getQuickStats($userId, $projectId),
            'recent_lists' => $this->getRecentLists($userId, $projectId),
            'recent_documents' => $this->getRecentDocuments($userId, $projectId),
            'recent_activity' => $this->getRecentActivity($userId),
        ];

        return JsonResponse::success($data);
    }

    private function getRecentLists(string $userId, ?string $projectId = null): array
    {
        if ($projectId) {
            // Filter by project
            $lists = $this->db->fetchAllAssociative(
                'SELECT l.id, l.title, l.type, l.color, l.updated_at,
                        (SELECT COUNT(*) FROM list_items WHERE list_id = l.id) as item_count,
                        (SELECT COUNT(*) FROM list_items WHERE list_id = l.id AND is_completed = 0) as open_count
                 FROM lists l
                 INNER JOIN project_links pl ON pl.linkable_id = l.id AND pl.linkable_type = ?
                 WHERE l.user_id = ? AND l.is_archived = 0 AND pl.project_id = ?
                 ORDER BY l.updated_at DESC
                 LIMIT 5',
                ['list', $userId, $projectId]
            );
        } else {
            $lists = $this->db->fetchAllAssociative(
                'SELECT l.id, l.title, l.type, l.color, l.updated_at,
                        (SELECT COUNT(*) FROM list_items WHERE list_id = l.id) as item_count,
                        (SELECT COUNT(*) FROM list_items WHERE list_id = l.id AND is_completed = 0) as open_count
                 FROM lists l
                 WHERE l.user_id = ? AND l.is_archived = 0
                 ORDER BY l.updated_at DESC
                 LIMIT 5',
                [$userId]
            );
        }

        return $lists;
    }

    private function getRecentDocuments(string $userId, ?string $projectId = null): array
    {
        if ($projectId) {
            // Filter by project
            $documents = $this->db->fetchAllAssociative(
                'SELECT d.id, d.title, d.format, d.updated_at
                 FROM documents d
                 INNER JOIN project_links pl ON pl.linkable_id = d.id AND pl.linkable_type = ?
                 WHERE d.user_id = ? AND d.is_archived = 0 AND pl.project_id = ?
                 ORDER BY d.updated_at DESC
                 LIMIT 5',
                ['document', $userId, $projectId]
            );
        } else {
            $documents = $this->db->fetchAllAssociative(
                'SELECT id, title, format, updated_at
                 FROM documents
                 WHERE user_id = ? AND is_archived = 0
                 ORDER BY updated_at DESC
                 LIMIT 5',
                [$userId]
            );
        }

        return $documents;
    }

    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $stats = [
            'lists' => $this->getListStats($userId),
            'documents' => $this->getDocumentStats($userId),
            'activity' => $this->getActivityStats($userId),
        ];

        return JsonResponse::success($stats);
    }

    private function getQuickStats(string $userId, ?string $projectId = null): array
    {
        if ($projectId) {
            // Filter by project
            $listCount = (int) $this->db->fetchOne(
                'SELECT COUNT(*) FROM lists l
                 INNER JOIN project_links pl ON pl.linkable_id = l.id AND pl.linkable_type = ?
                 WHERE l.user_id = ? AND l.is_archived = FALSE AND pl.project_id = ?',
                ['list', $userId, $projectId]
            );

            $openTasks = (int) $this->db->fetchOne(
                'SELECT COUNT(*) FROM list_items li
                 JOIN lists l ON li.list_id = l.id
                 INNER JOIN project_links pl ON pl.linkable_id = l.id AND pl.linkable_type = ?
                 WHERE l.user_id = ? AND li.is_completed = FALSE AND pl.project_id = ?',
                ['list', $userId, $projectId]
            );

            $documentCount = (int) $this->db->fetchOne(
                'SELECT COUNT(*) FROM documents d
                 INNER JOIN project_links pl ON pl.linkable_id = d.id AND pl.linkable_type = ?
                 WHERE d.user_id = ? AND d.is_archived = FALSE AND pl.project_id = ?',
                ['document', $userId, $projectId]
            );
        } else {
            $listCount = (int) $this->db->fetchOne(
                'SELECT COUNT(*) FROM lists WHERE user_id = ? AND is_archived = FALSE',
                [$userId]
            );

            $openTasks = (int) $this->db->fetchOne(
                'SELECT COUNT(*) FROM list_items li
                 JOIN lists l ON li.list_id = l.id
                 WHERE l.user_id = ? AND li.is_completed = FALSE',
                [$userId]
            );

            $documentCount = (int) $this->db->fetchOne(
                'SELECT COUNT(*) FROM documents WHERE user_id = ? AND is_archived = FALSE',
                [$userId]
            );
        }

        return [
            'total_lists' => $listCount,
            'open_tasks' => $openTasks,
            'total_documents' => $documentCount,
        ];
    }

    private function getRecentActivity(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT action, entity_type, entity_id, created_at
             FROM audit_logs
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 10',
            [$userId]
        );
    }

    private function getListStats(string $userId): array
    {
        $byType = $this->db->fetchAllAssociative(
            'SELECT type, COUNT(*) as count FROM lists WHERE user_id = ? GROUP BY type',
            [$userId]
        );

        $completionRate = $this->db->fetchAssociative(
            'SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_completed = TRUE THEN 1 ELSE 0 END) as completed
             FROM list_items li
             JOIN lists l ON li.list_id = l.id
             WHERE l.user_id = ?',
            [$userId]
        );

        return [
            'by_type' => $byType,
            'completion_rate' => $completionRate['total'] > 0
                ? round(($completionRate['completed'] / $completionRate['total']) * 100, 1)
                : 0,
        ];
    }

    private function getDocumentStats(string $userId): array
    {
        $byFormat = $this->db->fetchAllAssociative(
            'SELECT format, COUNT(*) as count FROM documents WHERE user_id = ? GROUP BY format',
            [$userId]
        );

        $recentlyEdited = $this->db->fetchAllAssociative(
            'SELECT id, title, updated_at FROM documents
             WHERE user_id = ?
             ORDER BY updated_at DESC
             LIMIT 5',
            [$userId]
        );

        return [
            'by_format' => $byFormat,
            'recently_edited' => $recentlyEdited,
        ];
    }

    private function getActivityStats(string $userId): array
    {
        // Activity over last 7 days
        $daily = $this->db->fetchAllAssociative(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM audit_logs
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date",
            [$userId]
        );

        return [
            'daily_activity' => $daily,
        ];
    }
}
