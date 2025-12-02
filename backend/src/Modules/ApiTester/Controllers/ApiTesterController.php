<?php

declare(strict_types=1);

namespace App\Modules\ApiTester\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class ApiTesterController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    // ============ COLLECTIONS ============

    public function getCollections(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $collections = $this->db->fetchAllAssociative(
            'SELECT c.*, COUNT(r.id) as request_count
             FROM api_collections c
             LEFT JOIN api_requests r ON c.id = r.collection_id
             WHERE c.user_id = ?
             GROUP BY c.id
             ORDER BY c.name',
            [$userId]
        );

        return JsonResponse::success(['items' => $collections]);
    }

    public function createCollection(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            throw new ValidationException('Collection name is required');
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('api_collections', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#6366f1',
        ]);

        $collection = $this->db->fetchAssociative('SELECT * FROM api_collections WHERE id = ?', [$id]);

        return JsonResponse::created($collection, 'Collection created');
    }

    public function updateCollection(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $updates = [];
        $params = [];

        foreach (['name', 'description', 'color'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (!empty($updates)) {
            $params[] = $id;
            $params[] = $userId;
            $this->db->executeStatement(
                'UPDATE api_collections SET ' . implode(', ', $updates) . ' WHERE id = ? AND user_id = ?',
                $params
            );
        }

        return JsonResponse::success(null, 'Collection updated');
    }

    public function deleteCollection(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $this->db->delete('api_collections', ['id' => $id, 'user_id' => $userId]);

        return JsonResponse::success(null, 'Collection deleted');
    }

    // ============ ENVIRONMENTS ============

    public function getEnvironments(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $environments = $this->db->fetchAllAssociative(
            'SELECT * FROM api_environments WHERE user_id = ? ORDER BY name',
            [$userId]
        );

        foreach ($environments as &$env) {
            $env['is_active'] = (bool) $env['is_active'];
            $env['variables'] = $env['variables'] ? json_decode($env['variables'], true) : [];
        }

        return JsonResponse::success(['items' => $environments]);
    }

    public function createEnvironment(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            throw new ValidationException('Environment name is required');
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('api_environments', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'variables' => json_encode($data['variables'] ?? []),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        // If set as active, deactivate others
        if (!empty($data['is_active'])) {
            $this->db->executeStatement(
                'UPDATE api_environments SET is_active = 0 WHERE user_id = ? AND id != ?',
                [$userId, $id]
            );
        }

        $env = $this->db->fetchAssociative('SELECT * FROM api_environments WHERE id = ?', [$id]);
        $env['is_active'] = (bool) $env['is_active'];
        $env['variables'] = json_decode($env['variables'], true);

        return JsonResponse::created($env, 'Environment created');
    }

    public function updateEnvironment(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $updates = [];
        $params = [];

        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $params[] = $data['name'];
        }
        if (isset($data['variables'])) {
            $updates[] = 'variables = ?';
            $params[] = json_encode($data['variables']);
        }
        if (isset($data['is_active'])) {
            $updates[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;

            if ($data['is_active']) {
                // Deactivate others
                $this->db->executeStatement(
                    'UPDATE api_environments SET is_active = 0 WHERE user_id = ? AND id != ?',
                    [$userId, $id]
                );
            }
        }

        if (!empty($updates)) {
            $params[] = $id;
            $params[] = $userId;
            $this->db->executeStatement(
                'UPDATE api_environments SET ' . implode(', ', $updates) . ' WHERE id = ? AND user_id = ?',
                $params
            );
        }

        return JsonResponse::success(null, 'Environment updated');
    }

    public function deleteEnvironment(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $this->db->delete('api_environments', ['id' => $id, 'user_id' => $userId]);

        return JsonResponse::success(null, 'Environment deleted');
    }

    // ============ REQUESTS ============

    public function getRequests(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $collectionId = $queryParams['collection_id'] ?? null;

        $sql = 'SELECT * FROM api_requests WHERE user_id = ?';
        $params = [$userId];

        if ($collectionId) {
            $sql .= ' AND collection_id = ?';
            $params[] = $collectionId;
        }

        $sql .= ' ORDER BY sort_order, name';

        $requests = $this->db->fetchAllAssociative($sql, $params);

        foreach ($requests as &$req) {
            $req['headers'] = $req['headers'] ? json_decode($req['headers'], true) : [];
            $req['auth_config'] = $req['auth_config'] ? json_decode($req['auth_config'], true) : [];
        }

        return JsonResponse::success(['items' => $requests]);
    }

    public function createRequest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name']) || empty($data['url'])) {
            throw new ValidationException('Name and URL are required');
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('api_requests', [
            'id' => $id,
            'user_id' => $userId,
            'collection_id' => $data['collection_id'] ?? null,
            'name' => $data['name'],
            'method' => $data['method'] ?? 'GET',
            'url' => $data['url'],
            'headers' => json_encode($data['headers'] ?? []),
            'body_type' => $data['body_type'] ?? 'none',
            'body' => $data['body'] ?? null,
            'auth_type' => $data['auth_type'] ?? 'none',
            'auth_config' => json_encode($data['auth_config'] ?? []),
            'pre_request_script' => $data['pre_request_script'] ?? null,
            'test_script' => $data['test_script'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        $req = $this->db->fetchAssociative('SELECT * FROM api_requests WHERE id = ?', [$id]);
        $req['headers'] = json_decode($req['headers'], true);
        $req['auth_config'] = json_decode($req['auth_config'], true);

        return JsonResponse::created($req, 'Request created');
    }

    public function updateRequest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $updates = [];
        $params = [];

        $fields = ['collection_id', 'name', 'method', 'url', 'body_type', 'body', 'auth_type',
                   'pre_request_script', 'test_script', 'sort_order'];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (isset($data['headers'])) {
            $updates[] = 'headers = ?';
            $params[] = json_encode($data['headers']);
        }
        if (isset($data['auth_config'])) {
            $updates[] = 'auth_config = ?';
            $params[] = json_encode($data['auth_config']);
        }

        if (!empty($updates)) {
            $params[] = $id;
            $params[] = $userId;
            $this->db->executeStatement(
                'UPDATE api_requests SET ' . implode(', ', $updates) . ' WHERE id = ? AND user_id = ?',
                $params
            );
        }

        return JsonResponse::success(null, 'Request updated');
    }

    public function deleteRequest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $this->db->delete('api_requests', ['id' => $id, 'user_id' => $userId]);

        return JsonResponse::success(null, 'Request deleted');
    }

    // ============ EXECUTE REQUEST ============

    public function executeRequest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['url'])) {
            throw new ValidationException('URL is required');
        }

        $method = strtoupper($data['method'] ?? 'GET');
        $url = $data['url'];
        $headers = $data['headers'] ?? [];
        $bodyType = $data['body_type'] ?? 'none';
        $body = $data['body'] ?? null;
        $authType = $data['auth_type'] ?? 'none';
        $authConfig = $data['auth_config'] ?? [];

        // Apply environment variables if active environment
        $activeEnv = $this->db->fetchAssociative(
            'SELECT * FROM api_environments WHERE user_id = ? AND is_active = 1',
            [$userId]
        );

        if ($activeEnv && $activeEnv['variables']) {
            $variables = json_decode($activeEnv['variables'], true);
            $url = $this->replaceVariables($url, $variables);
            $body = $body ? $this->replaceVariables($body, $variables) : null;
            foreach ($headers as $key => $value) {
                $headers[$key] = $this->replaceVariables($value, $variables);
            }
        }

        // Build cURL request
        $ch = curl_init();
        $curlHeaders = [];

        // Set up authentication
        if ($authType === 'bearer' && !empty($authConfig['token'])) {
            $curlHeaders[] = 'Authorization: Bearer ' . $authConfig['token'];
        } elseif ($authType === 'basic' && !empty($authConfig['username'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $authConfig['username'] . ':' . ($authConfig['password'] ?? ''));
        } elseif ($authType === 'api_key' && !empty($authConfig['key']) && !empty($authConfig['value'])) {
            if (($authConfig['add_to'] ?? 'header') === 'header') {
                $curlHeaders[] = $authConfig['key'] . ': ' . $authConfig['value'];
            } else {
                $url .= (strpos($url, '?') !== false ? '&' : '?') . $authConfig['key'] . '=' . urlencode($authConfig['value']);
            }
        }

        // Set up headers
        foreach ($headers as $key => $value) {
            if (!empty($value)) {
                $curlHeaders[] = "{$key}: {$value}";
            }
        }

        // Set up body
        if ($bodyType === 'json' && $body) {
            $curlHeaders[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($bodyType === 'form' && $body) {
            $curlHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($bodyType === 'raw' && $body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $startTime = microtime(true);
        $fullResponse = curl_exec($ch);
        $responseTime = (int) ((microtime(true) - $startTime) * 1000);

        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        curl_close($ch);

        $responseHeaders = [];
        $responseBody = null;

        if ($fullResponse) {
            $headerText = substr($fullResponse, 0, $headerSize);
            $responseBody = substr($fullResponse, $headerSize);

            // Parse response headers
            foreach (explode("\r\n", $headerText) as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $responseHeaders[trim($key)] = trim($value);
                }
            }
        }

        // Store in history
        $historyId = Uuid::uuid4()->toString();
        $this->db->insert('api_request_history', [
            'id' => $historyId,
            'user_id' => $userId,
            'request_id' => $data['request_id'] ?? null,
            'method' => $method,
            'url' => $url,
            'request_headers' => json_encode($headers),
            'request_body' => $body,
            'response_status' => $statusCode,
            'response_headers' => json_encode($responseHeaders),
            'response_body' => $responseBody,
            'response_time' => $responseTime,
            'response_size' => strlen($responseBody ?? ''),
            'error_message' => $error ?: null,
        ]);

        return JsonResponse::success([
            'status' => $statusCode,
            'headers' => $responseHeaders,
            'body' => $responseBody,
            'time' => $responseTime,
            'size' => strlen($responseBody ?? ''),
            'error' => $error ?: null,
        ]);
    }

    // ============ HISTORY ============

    public function getHistory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $limit = min(100, max(1, (int) ($queryParams['limit'] ?? 50)));

        $history = $this->db->fetchAllAssociative(
            'SELECT id, request_id, method, url, response_status, response_time, executed_at
             FROM api_request_history
             WHERE user_id = ?
             ORDER BY executed_at DESC
             LIMIT ?',
            [$userId, $limit],
            [\PDO::PARAM_STR, \PDO::PARAM_INT]
        );

        return JsonResponse::success(['items' => $history]);
    }

    public function getHistoryItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $item = $this->db->fetchAssociative(
            'SELECT * FROM api_request_history WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$item) {
            throw new NotFoundException('History item not found');
        }

        $item['request_headers'] = $item['request_headers'] ? json_decode($item['request_headers'], true) : [];
        $item['response_headers'] = $item['response_headers'] ? json_decode($item['response_headers'], true) : [];

        return JsonResponse::success($item);
    }

    public function clearHistory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $this->db->executeStatement(
            'DELETE FROM api_request_history WHERE user_id = ?',
            [$userId]
        );

        return JsonResponse::success(null, 'History cleared');
    }

    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }
}
