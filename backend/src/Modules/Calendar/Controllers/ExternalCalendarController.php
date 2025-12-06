<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Core\Services\ICalService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class ExternalCalendarController
{
    public function __construct(
        private readonly Connection $db,
        private readonly ICalService $icalService
    ) {}

    /**
     * Get all external calendars for user
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $calendars = $this->db->fetchAllAssociative(
            'SELECT id, name, type, url, color, is_visible, sync_interval_minutes,
                    last_synced_at, last_sync_error, created_at
             FROM external_calendars
             WHERE user_id = ?
             ORDER BY name',
            [$userId]
        );

        return JsonResponse::success($calendars);
    }

    /**
     * Add a new external calendar
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name'])) {
            throw new ValidationException('Name is required');
        }
        if (empty($data['url'])) {
            throw new ValidationException('URL is required');
        }
        if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            throw new ValidationException('Invalid URL format');
        }

        $type = $data['type'] ?? 'ical';
        if (!in_array($type, ['ical', 'caldav'])) {
            throw new ValidationException('Invalid calendar type');
        }

        // Test the calendar URL
        try {
            $this->icalService->fetchAndParse(
                $data['url'],
                $data['username'] ?? null,
                $data['password'] ?? null
            );
        } catch (\Exception $e) {
            throw new ValidationException('Could not fetch calendar: ' . $e->getMessage());
        }

        $calendarId = Uuid::uuid4()->toString();

        // Encrypt password if provided
        $encryptedPassword = null;
        if (!empty($data['password'])) {
            $encryptedPassword = $this->encryptPassword($data['password']);
        }

        $this->db->insert('external_calendars', [
            'id' => $calendarId,
            'user_id' => $userId,
            'name' => $data['name'],
            'type' => $type,
            'url' => $data['url'],
            'username' => $data['username'] ?? null,
            'password_encrypted' => $encryptedPassword,
            'color' => $data['color'] ?? 'blue',
            'is_visible' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Sync immediately
        $this->syncCalendar($calendarId);

        $calendar = $this->db->fetchAssociative(
            'SELECT id, name, type, url, color, is_visible, last_synced_at FROM external_calendars WHERE id = ?',
            [$calendarId]
        );

        return JsonResponse::created($calendar, 'Calendar added successfully');
    }

    /**
     * Update external calendar settings
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $calendarId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $calendar = $this->getCalendarForUser($calendarId, $userId);

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        $allowedFields = ['name', 'color', 'is_visible', 'sync_interval_minutes'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'is_visible') {
                    $updateData[$field] = $data[$field] ? 1 : 0;
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }

        $this->db->update('external_calendars', $updateData, ['id' => $calendarId]);

        $calendar = $this->db->fetchAssociative(
            'SELECT id, name, type, url, color, is_visible, last_synced_at FROM external_calendars WHERE id = ?',
            [$calendarId]
        );

        return JsonResponse::success($calendar, 'Calendar updated successfully');
    }

    /**
     * Delete external calendar
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $calendarId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getCalendarForUser($calendarId, $userId);

        // Delete events first (foreign key)
        $this->db->delete('external_calendar_events', ['calendar_id' => $calendarId]);
        $this->db->delete('external_calendars', ['id' => $calendarId]);

        return JsonResponse::success(null, 'Calendar deleted successfully');
    }

    /**
     * Sync external calendar
     */
    public function sync(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $calendarId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getCalendarForUser($calendarId, $userId);

        try {
            $eventCount = $this->syncCalendar($calendarId);
            return JsonResponse::success(['events_synced' => $eventCount], 'Calendar synced successfully');
        } catch (\Exception $e) {
            // Store error
            $this->db->update('external_calendars', [
                'last_sync_error' => $e->getMessage(),
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $calendarId]);

            throw new ValidationException('Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Get events from external calendars for a date range
     */
    public function getEvents(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $start = $queryParams['start'] ?? date('Y-m-01');
        $end = $queryParams['end'] ?? date('Y-m-t');

        $events = $this->db->fetchAllAssociative(
            "SELECT
                ece.id,
                ece.title,
                ece.description,
                ece.location,
                ece.start_date,
                ece.end_date,
                ece.all_day,
                ec.color,
                ec.name as calendar_name,
                'external' as source_type,
                ec.id as source_id
             FROM external_calendar_events ece
             JOIN external_calendars ec ON ece.calendar_id = ec.id
             WHERE ec.user_id = ?
               AND ec.is_visible = 1
               AND ((ece.start_date >= ? AND ece.start_date <= ?)
                    OR (ece.end_date >= ? AND ece.end_date <= ?)
                    OR (ece.start_date <= ? AND ece.end_date >= ?))
             ORDER BY ece.start_date",
            [$userId, $start, $end . ' 23:59:59', $start, $end . ' 23:59:59', $start, $end]
        );

        return JsonResponse::success($events);
    }

    /**
     * Sync a calendar's events
     */
    private function syncCalendar(string $calendarId): int
    {
        $calendar = $this->db->fetchAssociative(
            'SELECT * FROM external_calendars WHERE id = ?',
            [$calendarId]
        );

        if (!$calendar) {
            throw new NotFoundException('Calendar not found');
        }

        // Decrypt password if present
        $password = null;
        if ($calendar['password_encrypted']) {
            $password = $this->decryptPassword($calendar['password_encrypted']);
        }

        // Fetch and parse events
        $events = $this->icalService->fetchAndParse(
            $calendar['url'],
            $calendar['username'],
            $password
        );

        // Get existing event UIDs
        $existingUids = $this->db->fetchFirstColumn(
            'SELECT external_uid FROM external_calendar_events WHERE calendar_id = ?',
            [$calendarId]
        );

        $syncedUids = [];
        $eventCount = 0;

        foreach ($events as $event) {
            if (empty($event['uid']) || empty($event['start_date'])) {
                continue;
            }

            $syncedUids[] = $event['uid'];

            $eventData = [
                'calendar_id' => $calendarId,
                'external_uid' => $event['uid'],
                'title' => $event['title'] ?: 'Untitled Event',
                'description' => $event['description'],
                'location' => $event['location'],
                'start_date' => $event['start_date'],
                'end_date' => $event['end_date'],
                'all_day' => $event['all_day'] ? 1 : 0,
                'recurrence_rule' => $event['recurrence_rule'],
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if (in_array($event['uid'], $existingUids)) {
                // Update existing event
                $this->db->update(
                    'external_calendar_events',
                    $eventData,
                    ['calendar_id' => $calendarId, 'external_uid' => $event['uid']]
                );
            } else {
                // Insert new event
                $eventData['id'] = Uuid::uuid4()->toString();
                $eventData['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert('external_calendar_events', $eventData);
            }

            $eventCount++;
        }

        // Delete events that no longer exist in the source
        if (!empty($syncedUids)) {
            $placeholders = implode(',', array_fill(0, count($syncedUids), '?'));
            $this->db->executeStatement(
                "DELETE FROM external_calendar_events
                 WHERE calendar_id = ? AND external_uid NOT IN ($placeholders)",
                array_merge([$calendarId], $syncedUids)
            );
        } else {
            // No events in source, delete all
            $this->db->delete('external_calendar_events', ['calendar_id' => $calendarId]);
        }

        // Update sync timestamp
        $this->db->update('external_calendars', [
            'last_synced_at' => date('Y-m-d H:i:s'),
            'last_sync_error' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $calendarId]);

        return $eventCount;
    }

    private function getCalendarForUser(string $calendarId, string $userId): array
    {
        $calendar = $this->db->fetchAssociative(
            'SELECT * FROM external_calendars WHERE id = ? AND user_id = ?',
            [$calendarId, $userId]
        );

        if (!$calendar) {
            throw new NotFoundException('Calendar not found');
        }

        return $calendar;
    }

    private function encryptPassword(string $password): string
    {
        $key = $_ENV['APP_SECRET'] ?? 'default-secret-key-change-me';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    private function decryptPassword(string $encrypted): string
    {
        $key = $_ENV['APP_SECRET'] ?? 'default-secret-key-change-me';
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}
