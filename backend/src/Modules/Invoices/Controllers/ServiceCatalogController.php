<?php

declare(strict_types=1);

namespace App\Modules\Invoices\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class ServiceCatalogController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $items = $this->db->fetchAllAssociative(
            'SELECT * FROM service_catalog WHERE user_id = ? ORDER BY sort_order ASC, name ASC',
            [$userId]
        );

        foreach ($items as &$item) {
            $item['unit_price'] = (float) $item['unit_price'];
            $item['is_active'] = (bool) $item['is_active'];
        }

        return JsonResponse::success($items);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        $name = trim($body['name'] ?? '');
        $unitPrice = (float) ($body['unit_price'] ?? 0);

        $errors = [];
        if (empty($name)) $errors['name'] = 'Name ist erforderlich';
        if ($unitPrice < 0) $errors['unit_price'] = 'Preis darf nicht negativ sein';

        if (!empty($errors)) {
            return JsonResponse::validationError($errors);
        }

        $id = Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');

        $this->db->insert('service_catalog', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $name,
            'description' => $body['description'] ?? null,
            'unit' => $body['unit'] ?? 'Stunde',
            'unit_price' => $unitPrice,
            'currency' => strtoupper(substr($body['currency'] ?? 'EUR', 0, 3)),
            'is_active' => 1,
            'sort_order' => (int) ($body['sort_order'] ?? 0),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $item = $this->db->fetchAssociative('SELECT * FROM service_catalog WHERE id = ?', [$id]);
        $item['unit_price'] = (float) $item['unit_price'];
        $item['is_active'] = (bool) $item['is_active'];

        return JsonResponse::created($item);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $body = (array) $request->getParsedBody();

        $existing = $this->db->fetchAssociative(
            'SELECT id FROM service_catalog WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$existing) {
            return JsonResponse::notFound('Leistung nicht gefunden');
        }

        $updates = ['updated_at' => date('Y-m-d H:i:s')];
        if (isset($body['name']) && trim($body['name']) !== '') {
            $updates['name'] = trim($body['name']);
        }
        if (array_key_exists('description', $body)) $updates['description'] = $body['description'];
        if (isset($body['unit'])) $updates['unit'] = $body['unit'];
        if (isset($body['unit_price'])) $updates['unit_price'] = (float) $body['unit_price'];
        if (isset($body['currency'])) $updates['currency'] = strtoupper(substr($body['currency'], 0, 3));
        if (isset($body['is_active'])) $updates['is_active'] = $body['is_active'] ? 1 : 0;
        if (isset($body['sort_order'])) $updates['sort_order'] = (int) $body['sort_order'];

        $this->db->update('service_catalog', $updates, ['id' => $id]);

        $item = $this->db->fetchAssociative('SELECT * FROM service_catalog WHERE id = ?', [$id]);
        $item['unit_price'] = (float) $item['unit_price'];
        $item['is_active'] = (bool) $item['is_active'];

        return JsonResponse::success($item);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $existing = $this->db->fetchAssociative(
            'SELECT id FROM service_catalog WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$existing) {
            return JsonResponse::notFound('Leistung nicht gefunden');
        }

        $this->db->delete('service_catalog', ['id' => $id]);

        return JsonResponse::noContent();
    }
}
