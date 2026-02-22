<?php

declare(strict_types=1);

namespace App\Modules\DatabaseBrowser\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\JsonResponse;
use App\Modules\DatabaseBrowser\Services\DatabaseBrowserService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

class DatabaseBrowserController
{
    public function __construct(
        private readonly Connection $db,
        private readonly DatabaseBrowserService $service
    ) {}

    /**
     * List connections of type 'database' belonging to this user.
     */
    public function listConnections(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $connections = $this->db->fetchAllAssociative(
            "SELECT id, name, host, port, username, type, color, icon, extra_data, last_used_at, created_at
             FROM connections
             WHERE user_id = ? AND type = 'database'
             ORDER BY last_used_at DESC, name ASC",
            [$userId]
        );

        // Parse extra_data for frontend
        foreach ($connections as &$conn) {
            $conn['extra_data'] = $conn['extra_data'] ? json_decode($conn['extra_data'], true) : [];
        }

        return JsonResponse::success(['items' => $connections]);
    }

    /**
     * Test connectivity for a database connection.
     */
    public function testConnection(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId       = $request->getAttribute('user_id');
        $connectionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $connection = $this->getConnectionForUser($connectionId, $userId);

        try {
            $this->service->listSchemas($connection);
            return JsonResponse::success(['success' => true], 'Connection successful');
        } catch (\Throwable $e) {
            return JsonResponse::error('Connection failed: ' . $e->getMessage(), 422);
        }
    }

    /**
     * List schemas/databases on the connection.
     */
    public function listSchemas(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId       = $request->getAttribute('user_id');
        $connectionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $connection = $this->getConnectionForUser($connectionId, $userId);

        $schemas = $this->service->listSchemas($connection);

        return JsonResponse::success(['items' => $schemas]);
    }

    /**
     * List tables in a schema.
     */
    public function listTables(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId       = $request->getAttribute('user_id');
        $connectionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $queryParams  = $request->getQueryParams();
        $database     = $queryParams['database'] ?? '';

        $connection = $this->getConnectionForUser($connectionId, $userId);

        $tables = $this->service->listTables($connection, $database);

        return JsonResponse::success(['items' => $tables]);
    }

    /**
     * Get column definitions for a table.
     */
    public function tableSchema(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId       = $request->getAttribute('user_id');
        $route        = RouteContext::fromRequest($request)->getRoute();
        $connectionId = $route->getArgument('id');
        $table        = $route->getArgument('table');
        $queryParams  = $request->getQueryParams();
        $database     = $queryParams['database'] ?? '';

        $connection = $this->getConnectionForUser($connectionId, $userId);

        $columns = $this->service->getTableSchema($connection, $database, $table);

        return JsonResponse::success(['columns' => $columns]);
    }

    /**
     * Fetch rows from a table with pagination.
     */
    public function tableRows(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId       = $request->getAttribute('user_id');
        $route        = RouteContext::fromRequest($request)->getRoute();
        $connectionId = $route->getArgument('id');
        $table        = $route->getArgument('table');
        $queryParams  = $request->getQueryParams();

        $database = $queryParams['database'] ?? '';
        $limit    = (int) ($queryParams['limit']  ?? 100);
        $offset   = (int) ($queryParams['offset'] ?? 0);
        $orderBy  = $queryParams['order_by']  ?? null;
        $orderDir = strtoupper($queryParams['order_dir'] ?? 'ASC');

        $connection = $this->getConnectionForUser($connectionId, $userId);

        $result = $this->service->getTableRows($connection, $database, $table, $limit, $offset, $orderBy, $orderDir);

        return JsonResponse::success($result);
    }

    /**
     * Execute a free SQL/Redis query.
     */
    public function executeQuery(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId       = $request->getAttribute('user_id');
        $connectionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data         = $request->getParsedBody() ?? [];

        $query      = trim($data['query'] ?? '');
        $database   = $data['database'] ?? '';
        $allowWrite = !empty($data['allow_write']);

        if (empty($query)) {
            return JsonResponse::error('Query is required', 422);
        }

        $connection = $this->getConnectionForUser($connectionId, $userId);

        $start  = microtime(true);
        $result = $this->service->executeQuery($connection, $database, $query, $allowWrite);
        $result['duration_ms'] = (int) ((microtime(true) - $start) * 1000);

        // Log to history
        $this->db->insert('db_query_history', [
            'user_id'       => (int) $userId,
            'connection_id' => $connectionId,
            'database_name' => $database ?: null,
            'query'         => $query,
            'duration_ms'   => $result['duration_ms'],
            'rows_returned' => $result['row_count'] ?? 0,
            'error'         => null,
            'executed_at'   => date('Y-m-d H:i:s'),
        ]);

        return JsonResponse::success($result);
    }

    /**
     * Get query history for a connection.
     */
    public function getHistory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId       = $request->getAttribute('user_id');
        $connectionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getConnectionForUser($connectionId, $userId);

        $history = $this->db->fetchAllAssociative(
            'SELECT * FROM db_query_history WHERE user_id = ? AND connection_id = ? ORDER BY executed_at DESC LIMIT 50',
            [(int) $userId, $connectionId]
        );

        return JsonResponse::success(['items' => $history]);
    }

    private function getConnectionForUser(string $connectionId, string $userId): array
    {
        $connection = $this->db->fetchAssociative(
            "SELECT * FROM connections WHERE id = ? AND user_id = ? AND type = 'database'",
            [$connectionId, $userId]
        );

        if (!$connection) {
            throw new NotFoundException('Database connection not found');
        }

        return $connection;
    }
}
