<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class ContactController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $search = $queryParams['search'] ?? null;
        $sort = $queryParams['sort'] ?? 'name_asc';
        $favorite = $queryParams['favorite'] ?? null;

        $sql = 'SELECT * FROM contacts WHERE user_id = ?';
        $params = [$userId];
        $types = [\PDO::PARAM_STR];

        if ($search) {
            $sql .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ?)';
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
        }

        if ($favorite === 'true' || $favorite === '1') {
            $sql .= ' AND is_favorite = 1';
        }

        // Sorting
        $orderBy = match ($sort) {
            'name_desc' => 'last_name DESC, first_name DESC',
            'company_asc' => 'company ASC, last_name ASC',
            'company_desc' => 'company DESC, last_name DESC',
            'created_desc' => 'created_at DESC',
            'created_asc' => 'created_at ASC',
            'last_contact' => 'last_contact_at DESC',
            default => 'last_name ASC, first_name ASC',
        };

        $sql .= " ORDER BY is_favorite DESC, {$orderBy} LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        $types[] = \PDO::PARAM_INT;
        $types[] = \PDO::PARAM_INT;

        $contacts = $this->db->fetchAllAssociative($sql, $params, $types);

        // Cast booleans and decode JSON
        foreach ($contacts as &$contact) {
            $contact['is_favorite'] = (bool) $contact['is_favorite'];
            $contact['tags'] = $contact['tags'] ? json_decode($contact['tags'], true) : [];
        }

        // Count total
        $countSql = 'SELECT COUNT(*) FROM contacts WHERE user_id = ?';
        $countParams = [$userId];

        if ($search) {
            $countSql .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR company LIKE ?)';
            $countParams[] = "%{$search}%";
            $countParams[] = "%{$search}%";
            $countParams[] = "%{$search}%";
            $countParams[] = "%{$search}%";
        }

        if ($favorite === 'true' || $favorite === '1') {
            $countSql .= ' AND is_favorite = 1';
        }

        $total = (int) $this->db->fetchOne($countSql, $countParams);

        return JsonResponse::paginated($contacts, $total, $page, $perPage);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['first_name']) || empty($data['last_name'])) {
            throw new ValidationException('Vorname und Nachname sind erforderlich');
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('contacts', [
            'id' => $id,
            'user_id' => $userId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'company' => $data['company'] ?? null,
            'position' => $data['position'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'] ?? 'Deutschland',
            'website' => $data['website'] ?? null,
            'notes' => $data['notes'] ?? null,
            'tags' => !empty($data['tags']) ? json_encode($data['tags']) : null,
            'is_favorite' => !empty($data['is_favorite']) ? 1 : 0,
            'avatar_color' => $data['avatar_color'] ?? '#6366f1',
        ]);

        $contact = $this->db->fetchAssociative(
            'SELECT * FROM contacts WHERE id = ?',
            [$id]
        );
        $contact['is_favorite'] = (bool) $contact['is_favorite'];
        $contact['tags'] = $contact['tags'] ? json_decode($contact['tags'], true) : [];

        return JsonResponse::created($contact, 'Kontakt erstellt');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $contact = $this->db->fetchAssociative(
            'SELECT * FROM contacts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$contact) {
            throw new NotFoundException('Kontakt nicht gefunden');
        }

        $contact['is_favorite'] = (bool) $contact['is_favorite'];
        $contact['tags'] = $contact['tags'] ? json_decode($contact['tags'], true) : [];

        // Include recent activities
        $contact['activities'] = $this->db->fetchAllAssociative(
            'SELECT * FROM contact_activities WHERE contact_id = ? ORDER BY activity_date DESC LIMIT 20',
            [$id]
        );

        return JsonResponse::success($contact);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $contact = $this->db->fetchAssociative(
            'SELECT * FROM contacts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$contact) {
            throw new NotFoundException('Kontakt nicht gefunden');
        }

        $updates = [];
        $params = [];

        $fields = [
            'first_name', 'last_name', 'email', 'phone', 'mobile',
            'company', 'position', 'address', 'city', 'postal_code',
            'country', 'website', 'notes', 'avatar_color',
        ];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (isset($data['tags'])) {
            $updates[] = 'tags = ?';
            $params[] = json_encode($data['tags']);
        }

        if (isset($data['is_favorite'])) {
            $updates[] = 'is_favorite = ?';
            $params[] = $data['is_favorite'] ? 1 : 0;
        }

        if (isset($data['last_contact_at'])) {
            $updates[] = 'last_contact_at = ?';
            $params[] = $data['last_contact_at'];
        }

        if (!empty($updates)) {
            $params[] = $id;
            $this->db->executeStatement(
                'UPDATE contacts SET ' . implode(', ', $updates) . ' WHERE id = ?',
                $params
            );
        }

        $updated = $this->db->fetchAssociative('SELECT * FROM contacts WHERE id = ?', [$id]);
        $updated['is_favorite'] = (bool) $updated['is_favorite'];
        $updated['tags'] = $updated['tags'] ? json_decode($updated['tags'], true) : [];

        return JsonResponse::success($updated, 'Kontakt aktualisiert');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $contact = $this->db->fetchAssociative(
            'SELECT * FROM contacts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$contact) {
            throw new NotFoundException('Kontakt nicht gefunden');
        }

        $this->db->delete('contacts', ['id' => $id]);

        return JsonResponse::success(null, 'Kontakt gelöscht');
    }

    public function toggleFavorite(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $contact = $this->db->fetchAssociative(
            'SELECT * FROM contacts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$contact) {
            throw new NotFoundException('Kontakt nicht gefunden');
        }

        $newValue = $contact['is_favorite'] ? 0 : 1;

        $this->db->executeStatement(
            'UPDATE contacts SET is_favorite = ? WHERE id = ?',
            [$newValue, $id]
        );

        return JsonResponse::success(['is_favorite' => (bool) $newValue], 'Favorit aktualisiert');
    }

    public function getActivities(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        // Verify contact belongs to user
        $contact = $this->db->fetchAssociative(
            'SELECT id FROM contacts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$contact) {
            throw new NotFoundException('Kontakt nicht gefunden');
        }

        $activities = $this->db->fetchAllAssociative(
            'SELECT * FROM contact_activities WHERE contact_id = ? ORDER BY activity_date DESC',
            [$id]
        );

        return JsonResponse::success(['items' => $activities]);
    }

    public function createActivity(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $contactId = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        // Verify contact belongs to user
        $contact = $this->db->fetchAssociative(
            'SELECT id FROM contacts WHERE id = ? AND user_id = ?',
            [$contactId, $userId]
        );

        if (!$contact) {
            throw new NotFoundException('Kontakt nicht gefunden');
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('contact_activities', [
            'id' => $id,
            'contact_id' => $contactId,
            'user_id' => $userId,
            'type' => $data['type'] ?? 'note',
            'subject' => $data['subject'] ?? null,
            'description' => $data['description'] ?? null,
            'activity_date' => $data['activity_date'] ?? date('Y-m-d H:i:s'),
        ]);

        // Update last_contact_at on the contact
        $this->db->executeStatement(
            'UPDATE contacts SET last_contact_at = NOW() WHERE id = ?',
            [$contactId]
        );

        $activity = $this->db->fetchAssociative(
            'SELECT * FROM contact_activities WHERE id = ?',
            [$id]
        );

        return JsonResponse::created($activity, 'Aktivität erstellt');
    }

    public function deleteActivity(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $contactId = $routeContext->getRoute()->getArgument('id');
        $activityId = $routeContext->getRoute()->getArgument('activityId');

        // Verify contact belongs to user
        $contact = $this->db->fetchAssociative(
            'SELECT id FROM contacts WHERE id = ? AND user_id = ?',
            [$contactId, $userId]
        );

        if (!$contact) {
            throw new NotFoundException('Kontakt nicht gefunden');
        }

        $activity = $this->db->fetchAssociative(
            'SELECT id FROM contact_activities WHERE id = ? AND contact_id = ?',
            [$activityId, $contactId]
        );

        if (!$activity) {
            throw new NotFoundException('Aktivität nicht gefunden');
        }

        $this->db->delete('contact_activities', ['id' => $activityId]);

        return JsonResponse::success(null, 'Aktivität gelöscht');
    }

    public function getStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $total = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM contacts WHERE user_id = ?',
            [$userId]
        );

        $favorites = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM contacts WHERE user_id = ? AND is_favorite = 1',
            [$userId]
        );

        $recent = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM contacts WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            [$userId]
        );

        return JsonResponse::success([
            'total' => $total,
            'favorites' => $favorites,
            'recent' => $recent,
        ]);
    }
}
