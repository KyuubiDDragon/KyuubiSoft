<?php

declare(strict_types=1);

namespace App\Modules\Audit\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

class AuditController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Paginated list of audit logs with filters
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];
        $types = [];

        // Filter by user_id
        if (!empty($queryParams['user_id'])) {
            $where[] = 'a.user_id = ?';
            $params[] = $queryParams['user_id'];
            $types[] = \PDO::PARAM_STR;
        }

        // Filter by action
        if (!empty($queryParams['action'])) {
            $where[] = 'a.action = ?';
            $params[] = $queryParams['action'];
            $types[] = \PDO::PARAM_STR;
        }

        // Filter by entity_type
        if (!empty($queryParams['entity_type'])) {
            $where[] = 'a.entity_type = ?';
            $params[] = $queryParams['entity_type'];
            $types[] = \PDO::PARAM_STR;
        }

        // Filter by date range
        if (!empty($queryParams['date_from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $queryParams['date_from'] . ' 00:00:00';
            $types[] = \PDO::PARAM_STR;
        }

        if (!empty($queryParams['date_to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $queryParams['date_to'] . ' 23:59:59';
            $types[] = \PDO::PARAM_STR;
        }

        // Filter by IP address
        if (!empty($queryParams['ip_address'])) {
            $where[] = 'a.ip_address = ?';
            $params[] = $queryParams['ip_address'];
            $types[] = \PDO::PARAM_STR;
        }

        // Search in old_values and new_values
        if (!empty($queryParams['search'])) {
            $where[] = '(a.old_values LIKE ? OR a.new_values LIKE ?)';
            $searchTerm = '%' . $queryParams['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $countSql = 'SELECT COUNT(*) FROM audit_logs a ' . $whereClause;
        $total = (int) $this->db->fetchOne($countSql, $params, $types);

        // Get paginated results
        $sql = 'SELECT a.*, u.username, u.email as user_email
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.id
                ' . $whereClause . '
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?';

        $params[] = $perPage;
        $params[] = $offset;
        $types[] = \PDO::PARAM_INT;
        $types[] = \PDO::PARAM_INT;

        $logs = $this->db->fetchAllAssociative($sql, $params, $types);

        // Decode JSON fields
        $logs = array_map([$this, 'formatLogEntry'], $logs);

        return JsonResponse::paginated($logs, $total, $page, $perPage);
    }

    /**
     * Single audit log entry by ID
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $log = $this->db->fetchAssociative(
            'SELECT a.*, u.username, u.email as user_email
             FROM audit_logs a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.id = ?',
            [$id]
        );

        if (!$log) {
            throw new NotFoundException('Audit log entry not found');
        }

        $log = $this->formatLogEntry($log);

        return JsonResponse::success($log);
    }

    /**
     * Aggregated audit statistics
     */
    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Actions per day (last 30 days)
        $actionsPerDay = $this->db->fetchAllAssociative(
            'SELECT DATE(created_at) as date, COUNT(*) as count
             FROM audit_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC'
        );

        // Top actions (count by action type)
        $topActions = $this->db->fetchAllAssociative(
            'SELECT action, COUNT(*) as count
             FROM audit_logs
             GROUP BY action
             ORDER BY count DESC
             LIMIT 20'
        );

        // Top users (count by user_id)
        $topUsers = $this->db->fetchAllAssociative(
            'SELECT a.user_id, u.username, u.email, COUNT(*) as count
             FROM audit_logs a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.user_id IS NOT NULL
             GROUP BY a.user_id, u.username, u.email
             ORDER BY count DESC
             LIMIT 20'
        );

        return JsonResponse::success([
            'actions_per_day' => $actionsPerDay,
            'top_actions' => $topActions,
            'top_users' => $topUsers,
        ]);
    }

    /**
     * All logs for a specific entity (entity_type + entity_id)
     */
    public function entityHistory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $route = RouteContext::fromRequest($request)->getRoute();
        $entityType = $route->getArgument('type');
        $entityId = $route->getArgument('id');

        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM audit_logs WHERE entity_type = ? AND entity_id = ?',
            [$entityType, $entityId]
        );

        $logs = $this->db->fetchAllAssociative(
            'SELECT a.*, u.username, u.email as user_email
             FROM audit_logs a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.entity_type = ? AND a.entity_id = ?
             ORDER BY a.created_at DESC
             LIMIT ? OFFSET ?',
            [$entityType, $entityId, $perPage, $offset],
            [\PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]
        );

        $logs = array_map([$this, 'formatLogEntry'], $logs);

        return JsonResponse::paginated($logs, $total, $page, $perPage);
    }

    /**
     * Export filtered audit logs as CSV
     */
    public function export(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();

        $where = [];
        $params = [];
        $types = [];

        // Same filters as index
        if (!empty($queryParams['user_id'])) {
            $where[] = 'a.user_id = ?';
            $params[] = $queryParams['user_id'];
            $types[] = \PDO::PARAM_STR;
        }

        if (!empty($queryParams['action'])) {
            $where[] = 'a.action = ?';
            $params[] = $queryParams['action'];
            $types[] = \PDO::PARAM_STR;
        }

        if (!empty($queryParams['entity_type'])) {
            $where[] = 'a.entity_type = ?';
            $params[] = $queryParams['entity_type'];
            $types[] = \PDO::PARAM_STR;
        }

        if (!empty($queryParams['date_from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $queryParams['date_from'] . ' 00:00:00';
            $types[] = \PDO::PARAM_STR;
        }

        if (!empty($queryParams['date_to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $queryParams['date_to'] . ' 23:59:59';
            $types[] = \PDO::PARAM_STR;
        }

        if (!empty($queryParams['ip_address'])) {
            $where[] = 'a.ip_address = ?';
            $params[] = $queryParams['ip_address'];
            $types[] = \PDO::PARAM_STR;
        }

        if (!empty($queryParams['search'])) {
            $where[] = '(a.old_values LIKE ? OR a.new_values LIKE ?)';
            $searchTerm = '%' . $queryParams['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = 'SELECT a.*, u.username, u.email as user_email
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.id
                ' . $whereClause . '
                ORDER BY a.created_at DESC';

        $logs = $this->db->fetchAllAssociative($sql, $params, $types);

        // Build CSV content
        $csvLines = [];

        // Header row
        $csvLines[] = implode(',', [
            'ID',
            'User ID',
            'Username',
            'Email',
            'Action',
            'Entity Type',
            'Entity ID',
            'Old Values',
            'New Values',
            'IP Address',
            'User Agent',
            'Created At',
        ]);

        // Data rows
        foreach ($logs as $log) {
            $csvLines[] = implode(',', [
                $this->csvEscape($log['id'] ?? ''),
                $this->csvEscape($log['user_id'] ?? ''),
                $this->csvEscape($log['username'] ?? ''),
                $this->csvEscape($log['user_email'] ?? ''),
                $this->csvEscape($log['action'] ?? ''),
                $this->csvEscape($log['entity_type'] ?? ''),
                $this->csvEscape($log['entity_id'] ?? ''),
                $this->csvEscape($log['old_values'] ?? ''),
                $this->csvEscape($log['new_values'] ?? ''),
                $this->csvEscape($log['ip_address'] ?? ''),
                $this->csvEscape($log['user_agent'] ?? ''),
                $this->csvEscape($log['created_at'] ?? ''),
            ]);
        }

        $csvContent = implode("\n", $csvLines);

        $response->getBody()->write($csvContent);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="audit_logs_' . date('Y-m-d_His') . '.csv"')
            ->withStatus(200);
    }

    /**
     * Format a log entry by decoding JSON fields
     */
    private function formatLogEntry(array $log): array
    {
        if (isset($log['old_values']) && is_string($log['old_values'])) {
            $log['old_values'] = json_decode($log['old_values'], true);
        }

        if (isset($log['new_values']) && is_string($log['new_values'])) {
            $log['new_values'] = json_decode($log['new_values'], true);
        }

        return $log;
    }

    /**
     * Escape a value for CSV output
     */
    private function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
