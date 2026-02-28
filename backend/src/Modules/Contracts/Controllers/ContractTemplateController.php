<?php

declare(strict_types=1);

namespace App\Modules\Contracts\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class ContractTemplateController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $type = $queryParams['contract_type'] ?? null;
        $language = $queryParams['language'] ?? null;

        $sql = 'SELECT * FROM contract_templates WHERE (user_id = ? OR is_default = 1)';
        $params = [$userId];

        if ($type) {
            $sql .= ' AND contract_type = ?';
            $params[] = $type;
        }
        if ($language) {
            $sql .= ' AND language = ?';
            $params[] = $language;
        }

        $sql .= ' ORDER BY is_default DESC, name ASC';

        $templates = $this->db->fetchAllAssociative($sql, $params);

        return JsonResponse::success(['items' => $templates]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $template = $this->db->fetchAssociative(
            'SELECT * FROM contract_templates WHERE id = ? AND (user_id = ? OR is_default = 1)',
            [$id, $userId]
        );

        if (!$template) {
            throw new NotFoundException('Template not found');
        }

        return JsonResponse::success($template);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            throw new ValidationException('Template name is required');
        }
        if (empty($data['contract_type'])) {
            throw new ValidationException('Contract type is required');
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('contract_templates', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'contract_type' => $data['contract_type'],
            'language' => $data['language'] ?? 'de',
            'content_html' => $data['content_html'] ?? '',
            'variables' => isset($data['variables']) ? json_encode($data['variables']) : null,
            'is_default' => 0,
        ]);

        $template = $this->db->fetchAssociative('SELECT * FROM contract_templates WHERE id = ?', [$id]);

        return JsonResponse::created($template, 'Template created');
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $template = $this->db->fetchAssociative(
            'SELECT * FROM contract_templates WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$template) {
            throw new NotFoundException('Template not found');
        }

        $updates = [];
        $params = [];

        $fields = ['name', 'contract_type', 'language', 'content_html'];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (isset($data['variables'])) {
            $updates[] = 'variables = ?';
            $params[] = json_encode($data['variables']);
        }

        if (!empty($updates)) {
            $params[] = $id;
            $this->db->executeStatement(
                'UPDATE contract_templates SET ' . implode(', ', $updates) . ' WHERE id = ?',
                $params
            );
        }

        return JsonResponse::success(null, 'Template updated');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $template = $this->db->fetchAssociative(
            'SELECT * FROM contract_templates WHERE id = ? AND user_id = ? AND is_default = 0',
            [$id, $userId]
        );

        if (!$template) {
            throw new NotFoundException('Template not found or is a system template');
        }

        $this->db->delete('contract_templates', ['id' => $id]);

        return JsonResponse::success(null, 'Template deleted');
    }

    public function getDefaults(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $templates = $this->db->fetchAllAssociative(
            'SELECT * FROM contract_templates WHERE is_default = 1 ORDER BY contract_type, language'
        );

        return JsonResponse::success(['items' => $templates]);
    }
}
