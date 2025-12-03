<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class CalendarController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function getEvents(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $start = $queryParams['start'] ?? date('Y-m-01');
        $end = $queryParams['end'] ?? date('Y-m-t');
        $sources = isset($queryParams['sources']) ? explode(',', $queryParams['sources']) : ['events', 'kanban', 'tasks', 'time'];

        $events = [];

        // Custom calendar events
        if (in_array('events', $sources)) {
            $customEvents = $this->db->fetchAllAssociative(
                "SELECT id, title, description, start_date, end_date, all_day, color, 'event' as source_type, NULL as source_id
                 FROM calendar_events
                 WHERE user_id = ?
                   AND ((start_date >= ? AND start_date <= ?)
                        OR (end_date >= ? AND end_date <= ?)
                        OR (start_date <= ? AND end_date >= ?))
                 ORDER BY start_date",
                [$userId, $start, $end . ' 23:59:59', $start, $end . ' 23:59:59', $start, $end],
            );
            $events = array_merge($events, $customEvents);
        }

        // Kanban due dates
        if (in_array('kanban', $sources)) {
            $kanbanDue = $this->db->fetchAllAssociative(
                "SELECT kc.id, kc.title, NULL as description, kc.due_date as start_date, NULL as end_date,
                        TRUE as all_day, COALESCE(kc.flag_color, 'orange') as color, 'kanban' as source_type, kb.id as source_id
                 FROM kanban_cards kc
                 JOIN kanban_columns col ON kc.column_id = col.id
                 JOIN kanban_boards kb ON col.board_id = kb.id
                 WHERE kb.user_id = ? AND kc.due_date >= ? AND kc.due_date <= ?",
                [$userId, $start, $end]
            );
            $events = array_merge($events, $kanbanDue);
        }

        // List item due dates
        if (in_array('tasks', $sources)) {
            $tasksDue = $this->db->fetchAllAssociative(
                "SELECT li.id, li.content as title, NULL as description, li.due_date as start_date, NULL as end_date,
                        TRUE as all_day, COALESCE(l.color, 'blue') as color, 'task' as source_type, l.id as source_id
                 FROM list_items li
                 JOIN lists l ON li.list_id = l.id
                 WHERE l.user_id = ? AND li.due_date >= ? AND li.due_date <= ? AND li.is_completed = FALSE",
                [$userId, $start, $end]
            );
            $events = array_merge($events, $tasksDue);
        }

        // Time entries
        if (in_array('time', $sources)) {
            $timeEntries = $this->db->fetchAllAssociative(
                "SELECT te.id, COALESCE(te.description, p.name, 'Zeiterfassung') as title, te.description,
                        te.start_time as start_date, te.end_time as end_date,
                        FALSE as all_day, COALESCE(p.color, 'green') as color, 'time' as source_type, te.project_id as source_id
                 FROM time_entries te
                 LEFT JOIN projects p ON te.project_id = p.id
                 WHERE te.user_id = ? AND DATE(te.start_time) >= ? AND DATE(te.start_time) <= ?",
                [$userId, $start, $end]
            );
            $events = array_merge($events, $timeEntries);
        }

        // Sort by start date
        usort($events, fn($a, $b) => strtotime($a['start_date']) - strtotime($b['start_date']));

        return JsonResponse::success($events);
    }

    public function createEvent(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['title'])) {
            throw new ValidationException('Title is required');
        }
        if (empty($data['start_date'])) {
            throw new ValidationException('Start date is required');
        }

        $eventId = Uuid::uuid4()->toString();

        $this->db->insert('calendar_events', [
            'id' => $eventId,
            'user_id' => $userId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'all_day' => !empty($data['all_day']) ? 1 : 0,
            'color' => $data['color'] ?? 'primary',
            'reminder_minutes' => $data['reminder_minutes'] ?? null,
            'recurrence' => $data['recurrence'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $event = $this->db->fetchAssociative('SELECT * FROM calendar_events WHERE id = ?', [$eventId]);

        return JsonResponse::created($event, 'Event created successfully');
    }

    public function updateEvent(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $eventId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $event = $this->getEventForUser($eventId, $userId);

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        $allowedFields = ['title', 'description', 'start_date', 'end_date', 'all_day', 'color', 'reminder_minutes', 'recurrence'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'all_day') {
                    $updateData[$field] = $data[$field] ? 1 : 0;
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }

        $this->db->update('calendar_events', $updateData, ['id' => $eventId]);

        $event = $this->db->fetchAssociative('SELECT * FROM calendar_events WHERE id = ?', [$eventId]);

        return JsonResponse::success($event, 'Event updated successfully');
    }

    public function deleteEvent(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $eventId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getEventForUser($eventId, $userId);
        $this->db->delete('calendar_events', ['id' => $eventId]);

        return JsonResponse::success(null, 'Event deleted successfully');
    }

    public function getUpcoming(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $days = min(30, max(1, (int) ($queryParams['days'] ?? 7)));

        $endDate = date('Y-m-d', strtotime("+{$days} days"));

        // Combine all upcoming items
        $events = [];

        // Custom events
        $customEvents = $this->db->fetchAllAssociative(
            "SELECT id, title, start_date, 'event' as type, color
             FROM calendar_events
             WHERE user_id = ? AND start_date >= NOW() AND start_date <= ?
             ORDER BY start_date
             LIMIT 20",
            [$userId, $endDate . ' 23:59:59']
        );
        $events = array_merge($events, $customEvents);

        // Kanban due dates
        $kanbanDue = $this->db->fetchAllAssociative(
            "SELECT kc.id, kc.title, kc.due_date as start_date, 'kanban' as type, COALESCE(kc.flag_color, 'orange') as color
             FROM kanban_cards kc
             JOIN kanban_columns col ON kc.column_id = col.id
             JOIN kanban_boards kb ON col.board_id = kb.id
             WHERE kb.user_id = ? AND kc.due_date >= CURDATE() AND kc.due_date <= ?
             ORDER BY kc.due_date
             LIMIT 20",
            [$userId, $endDate]
        );
        $events = array_merge($events, $kanbanDue);

        // Task due dates
        $tasksDue = $this->db->fetchAllAssociative(
            "SELECT li.id, li.content as title, li.due_date as start_date, 'task' as type, COALESCE(l.color, 'blue') as color
             FROM list_items li
             JOIN lists l ON li.list_id = l.id
             WHERE l.user_id = ? AND li.due_date >= CURDATE() AND li.due_date <= ? AND li.is_completed = FALSE
             ORDER BY li.due_date
             LIMIT 20",
            [$userId, $endDate]
        );
        $events = array_merge($events, $tasksDue);

        // Sort by date
        usort($events, fn($a, $b) => strtotime($a['start_date']) - strtotime($b['start_date']));

        // Take first 20
        $events = array_slice($events, 0, 20);

        return JsonResponse::success($events);
    }

    private function getEventForUser(string $eventId, string $userId): array
    {
        $event = $this->db->fetchAssociative(
            'SELECT * FROM calendar_events WHERE id = ? AND user_id = ?',
            [$eventId, $userId]
        );

        if (!$event) {
            throw new NotFoundException('Event not found');
        }

        return $event;
    }
}
