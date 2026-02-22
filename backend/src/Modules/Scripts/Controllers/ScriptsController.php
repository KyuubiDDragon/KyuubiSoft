<?php

declare(strict_types=1);

namespace App\Modules\Scripts\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Modules\Scripts\Services\ScriptsService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class ScriptsController
{
    public function __construct(
        private readonly Connection $db,
        private readonly ScriptsService $service
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId      = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $language    = $queryParams['language'] ?? null;
        $search      = $queryParams['search']   ?? null;

        $sql    = 'SELECT id, name, description, language, tags, is_favorite, created_at, updated_at FROM scripts WHERE user_id = ?';
        $params = [$userId];

        if ($language) {
            $sql     .= ' AND language = ?';
            $params[] = $language;
        }

        if ($search) {
            $sql     .= ' AND (name LIKE ? OR description LIKE ?)';
            $term     = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= ' ORDER BY is_favorite DESC, updated_at DESC';

        $scripts = $this->db->fetchAllAssociative($sql, $params);

        foreach ($scripts as &$s) {
            $s['tags'] = $s['tags'] ? json_decode($s['tags'], true) : [];
        }

        return JsonResponse::success(['items' => $scripts]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId   = $request->getAttribute('user_id');
        $scriptId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $script = $this->getScriptForUser($scriptId, $userId);
        $script['tags'] = $script['tags'] ? json_decode($script['tags'], true) : [];

        return JsonResponse::success($script);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data   = $request->getParsedBody() ?? [];

        if (empty($data['name'])) {
            throw new ValidationException('Name is required');
        }
        if (empty($data['content'])) {
            throw new ValidationException('Content is required');
        }

        $validLanguages = ['bash', 'python', 'php', 'node'];
        $language       = $data['language'] ?? 'bash';
        if (!in_array($language, $validLanguages, true)) {
            throw new ValidationException('Invalid language. Allowed: ' . implode(', ', $validLanguages));
        }

        $scriptId = Uuid::uuid4()->toString();

        $this->db->insert('scripts', [
            'id'          => $scriptId,
            'user_id'     => $userId,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'language'    => $language,
            'content'     => $data['content'],
            'tags'        => !empty($data['tags']) ? json_encode($data['tags']) : null,
            'is_favorite' => !empty($data['is_favorite']) ? 1 : 0,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $script = $this->getScriptForUser($scriptId, $userId);
        $script['tags'] = $script['tags'] ? json_decode($script['tags'], true) : [];

        return JsonResponse::created($script, 'Script created');
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId   = $request->getAttribute('user_id');
        $scriptId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data     = $request->getParsedBody() ?? [];

        $this->getScriptForUser($scriptId, $userId);

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        foreach (['name', 'description', 'content', 'language'] as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['tags'])) {
            $updateData['tags'] = json_encode($data['tags']);
        }

        if (isset($data['is_favorite'])) {
            $updateData['is_favorite'] = $data['is_favorite'] ? 1 : 0;
        }

        $this->db->update('scripts', $updateData, ['id' => $scriptId, 'user_id' => $userId]);

        $script = $this->getScriptForUser($scriptId, $userId);
        $script['tags'] = $script['tags'] ? json_decode($script['tags'], true) : [];

        return JsonResponse::success($script, 'Script updated');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId   = $request->getAttribute('user_id');
        $scriptId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getScriptForUser($scriptId, $userId);

        $this->db->delete('scripts', ['id' => $scriptId, 'user_id' => $userId]);

        return JsonResponse::success(null, 'Script deleted');
    }

    /**
     * Execute a script (on the backend container or a remote SSH host).
     */
    public function run(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId   = $request->getAttribute('user_id');
        $scriptId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data     = $request->getParsedBody() ?? [];

        $script       = $this->getScriptForUser($scriptId, $userId);
        $connectionId = $data['connection_id'] ?? null;

        $result = $this->service->runScript($script, $connectionId, $userId);

        // Store execution
        $execId = Uuid::uuid4()->toString();
        $this->db->insert('script_executions', [
            'id'            => $execId,
            'script_id'     => $scriptId,
            'user_id'       => $userId,
            'connection_id' => $connectionId,
            'stdout'        => $result['stdout'],
            'stderr'        => $result['stderr'],
            'exit_code'     => $result['exit_code'],
            'duration_ms'   => $result['duration_ms'],
            'executed_at'   => date('Y-m-d H:i:s'),
        ]);

        $result['execution_id'] = $execId;

        return JsonResponse::success($result);
    }

    /**
     * Get execution history for a script.
     */
    public function history(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId   = $request->getAttribute('user_id');
        $scriptId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getScriptForUser($scriptId, $userId);

        $executions = $this->db->fetchAllAssociative(
            'SELECT id, connection_id, exit_code, duration_ms, executed_at FROM script_executions WHERE script_id = ? AND user_id = ? ORDER BY executed_at DESC LIMIT 50',
            [$scriptId, $userId]
        );

        return JsonResponse::success(['items' => $executions]);
    }

    /**
     * Get full output of a specific execution.
     */
    public function executionDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId      = $request->getAttribute('user_id');
        $route       = RouteContext::fromRequest($request)->getRoute();
        $scriptId    = $route->getArgument('id');
        $executionId = $route->getArgument('executionId');

        $this->getScriptForUser($scriptId, $userId);

        $exec = $this->db->fetchAssociative(
            'SELECT * FROM script_executions WHERE id = ? AND user_id = ?',
            [$executionId, $userId]
        );

        if (!$exec) {
            throw new NotFoundException('Execution not found');
        }

        return JsonResponse::success($exec);
    }

    private function getScriptForUser(string $scriptId, string $userId): array
    {
        $script = $this->db->fetchAssociative(
            'SELECT * FROM scripts WHERE id = ? AND user_id = ?',
            [$scriptId, $userId]
        );

        if (!$script) {
            throw new NotFoundException('Script not found');
        }

        return $script;
    }
}
