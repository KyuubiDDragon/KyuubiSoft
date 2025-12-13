<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class WebhookController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    // Available events - organized by category
    private array $availableEvents = [
        // Documents
        'document.created',
        'document.updated',
        'document.deleted',
        'document.shared',

        // Lists
        'list.created',
        'list.updated',
        'list.deleted',
        'list.item_completed',

        // Kanban
        'kanban.board_created',
        'kanban.board_deleted',
        'kanban.card_created',
        'kanban.card_moved',
        'kanban.card_completed',
        'kanban.card_deleted',

        // Projects
        'project.created',
        'project.updated',
        'project.deleted',
        'project.archived',

        // Time Tracking
        'time.started',
        'time.stopped',
        'time.entry_created',
        'time.entry_updated',
        'time.entry_deleted',

        // User & Authentication
        'user.registered',
        'user.login',
        'user.logout',
        'user.password_changed',
        'user.profile_updated',
        'user.deleted',
        'user.invited',
        'user.2fa_enabled',
        'user.2fa_disabled',

        // Tickets
        'ticket.created',
        'ticket.updated',
        'ticket.assigned',
        'ticket.status_changed',
        'ticket.comment_added',
        'ticket.resolved',
        'ticket.closed',
        'ticket.reopened',
        'ticket.deleted',

        // Invoices
        'invoice.created',
        'invoice.updated',
        'invoice.sent',
        'invoice.paid',
        'invoice.overdue',
        'invoice.cancelled',
        'invoice.deleted',

        // Clients
        'client.created',
        'client.updated',
        'client.deleted',

        // Chat
        'chat.room_created',
        'chat.room_deleted',
        'chat.message_sent',
        'chat.message_deleted',
        'chat.user_joined',
        'chat.user_left',

        // Wiki
        'wiki.page_created',
        'wiki.page_updated',
        'wiki.page_deleted',

        // Snippets
        'snippet.created',
        'snippet.updated',
        'snippet.deleted',

        // Bookmarks
        'bookmark.created',
        'bookmark.updated',
        'bookmark.deleted',

        // Links
        'link.created',
        'link.updated',
        'link.deleted',
        'link.clicked',

        // Calendar
        'calendar.event_created',
        'calendar.event_updated',
        'calendar.event_deleted',
        'calendar.event_starting',
        'calendar.event_reminder',

        // Checklists
        'checklist.created',
        'checklist.updated',
        'checklist.deleted',
        'checklist.item_completed',

        // Recurring Tasks
        'recurring.task_created',
        'recurring.task_updated',
        'recurring.task_deleted',
        'recurring.task_due',
        'recurring.task_completed',

        // Quick Notes
        'quicknote.created',
        'quicknote.updated',
        'quicknote.deleted',

        // Templates
        'template.created',
        'template.updated',
        'template.deleted',
        'template.used',

        // Files
        'file.uploaded',
        'file.updated',
        'file.deleted',
        'file.shared',
        'file.downloaded',

        // Passwords (Vault)
        'password.created',
        'password.updated',
        'password.deleted',
        'password.shared',
        'password.accessed',

        // API Keys
        'apikey.created',
        'apikey.revoked',
        'apikey.used',

        // Backup
        'backup.started',
        'backup.completed',
        'backup.failed',
        'backup.restored',

        // Docker
        'docker.container_started',
        'docker.container_stopped',
        'docker.container_restarted',
        'docker.container_created',
        'docker.container_deleted',
        'docker.image_pulled',

        // Uptime Monitor
        'monitor.created',
        'monitor.deleted',
        'monitor.down',
        'monitor.up',
        'monitor.ssl_expiring',

        // Tags
        'tag.created',
        'tag.updated',
        'tag.deleted',

        // Connections
        'connection.created',
        'connection.updated',
        'connection.deleted',
        'connection.tested',

        // AI
        'ai.request_completed',
        'ai.request_failed',

        // Comments
        'comment.created',
        'comment.updated',
        'comment.deleted',

        // Notifications
        'notification.sent',

        // Inbox
        'inbox.message_received',
        'inbox.message_read',

        // System
        'system.maintenance_started',
        'system.maintenance_completed',
        'system.update_available',
    ];

    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $webhooks = $this->db->fetchAllAssociative(
            'SELECT id, name, url, type, events, is_active, last_triggered_at, last_status, failure_count, created_at
             FROM webhooks
             WHERE user_id = ?
             ORDER BY created_at DESC',
            [$userId]
        );

        foreach ($webhooks as &$webhook) {
            $webhook['events'] = json_decode($webhook['events'], true);
            $webhook['is_active'] = (bool) $webhook['is_active'];
        }

        return JsonResponse::success( ['items' => $webhooks]);
    }

    public function create(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        $name = trim($data['name'] ?? '');
        $url = trim($data['url'] ?? '');
        $type = $data['type'] ?? 'discord';
        $events = $data['events'] ?? [];
        $secret = $data['secret'] ?? null;

        if (empty($name)) {
            return JsonResponse::error( 'Name ist erforderlich', 400);
        }

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return JsonResponse::error( 'Gültige URL ist erforderlich', 400);
        }

        if (!in_array($type, ['discord', 'slack', 'custom'])) {
            return JsonResponse::error( 'Ungültiger Webhook-Typ', 400);
        }

        if (empty($events) || !is_array($events)) {
            return JsonResponse::error( 'Mindestens ein Event ist erforderlich', 400);
        }

        // Validate events
        $events = array_intersect($events, $this->availableEvents);
        if (empty($events)) {
            return JsonResponse::error( 'Keine gültigen Events ausgewählt', 400);
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('webhooks', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $name,
            'url' => $url,
            'type' => $type,
            'events' => json_encode(array_values($events)),
            'secret' => $secret,
            'is_active' => 1,
        ]);

        return JsonResponse::created([
            'id' => $id,
        ], 'Webhook erstellt');
    }

    public function show(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $webhookId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $webhook = $this->db->fetchAssociative(
            'SELECT * FROM webhooks WHERE id = ? AND user_id = ?',
            [$webhookId, $userId]
        );

        if (!$webhook) {
            return JsonResponse::error( 'Webhook nicht gefunden', 404);
        }

        $webhook['events'] = json_decode($webhook['events'], true);
        $webhook['is_active'] = (bool) $webhook['is_active'];
        unset($webhook['secret']); // Don't expose secret

        // Get recent logs
        $logs = $this->db->fetchAllAssociative(
            'SELECT id, event_type, response_status, error_message, created_at
             FROM webhook_logs
             WHERE webhook_id = ?
             ORDER BY created_at DESC
             LIMIT 20',
            [$webhookId]
        );

        $webhook['recent_logs'] = $logs;

        return JsonResponse::success( $webhook);
    }

    public function update(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $webhookId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $webhook = $this->db->fetchAssociative(
            'SELECT id FROM webhooks WHERE id = ? AND user_id = ?',
            [$webhookId, $userId]
        );

        if (!$webhook) {
            return JsonResponse::error( 'Webhook nicht gefunden', 404);
        }

        $updates = [];
        $params = [];

        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $params[] = trim($data['name']);
        }

        if (isset($data['url'])) {
            $url = trim($data['url']);
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return JsonResponse::error( 'Gültige URL ist erforderlich', 400);
            }
            $updates[] = 'url = ?';
            $params[] = $url;
        }

        if (isset($data['type'])) {
            if (!in_array($data['type'], ['discord', 'slack', 'custom'])) {
                return JsonResponse::error( 'Ungültiger Webhook-Typ', 400);
            }
            $updates[] = 'type = ?';
            $params[] = $data['type'];
        }

        if (isset($data['events']) && is_array($data['events'])) {
            $events = array_intersect($data['events'], $this->availableEvents);
            if (empty($events)) {
                return JsonResponse::error( 'Keine gültigen Events', 400);
            }
            $updates[] = 'events = ?';
            $params[] = json_encode(array_values($events));
        }

        if (isset($data['is_active'])) {
            $updates[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
        }

        if (isset($data['secret'])) {
            $updates[] = 'secret = ?';
            $params[] = $data['secret'] ?: null;
        }

        if (!empty($updates)) {
            $params[] = $webhookId;
            $this->db->executeStatement(
                'UPDATE webhooks SET ' . implode(', ', $updates) . ' WHERE id = ?',
                $params
            );
        }

        return JsonResponse::success( ['message' => 'Webhook aktualisiert']);
    }

    public function delete(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $webhookId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $deleted = $this->db->delete('webhooks', [
            'id' => $webhookId,
            'user_id' => $userId,
        ]);

        if (!$deleted) {
            return JsonResponse::error( 'Webhook nicht gefunden', 404);
        }

        return JsonResponse::success( ['message' => 'Webhook gelöscht']);
    }

    public function test(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $webhookId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $webhook = $this->db->fetchAssociative(
            'SELECT * FROM webhooks WHERE id = ? AND user_id = ?',
            [$webhookId, $userId]
        );

        if (!$webhook) {
            return JsonResponse::error( 'Webhook nicht gefunden', 404);
        }

        $testPayload = $this->buildPayload($webhook['type'], 'test', [
            'message' => 'Dies ist eine Test-Nachricht von KyuubiSoft',
            'timestamp' => date('c'),
        ]);

        $result = $this->sendWebhook($webhook, 'test', $testPayload);

        return JsonResponse::success( [
            'success' => $result['success'],
            'status' => $result['status'],
            'message' => $result['success'] ? 'Test erfolgreich' : 'Test fehlgeschlagen: ' . $result['error'],
        ]);
    }

    public function getAvailableEvents(Request $request, Response $response): Response
    {
        $events = [];
        foreach ($this->availableEvents as $event) {
            [$category, $action] = explode('.', $event);
            $events[] = [
                'value' => $event,
                'category' => $category,
                'action' => $action,
                'label' => $this->getEventLabel($event),
            ];
        }

        return JsonResponse::success( ['events' => $events]);
    }

    private function getEventLabel(string $event): string
    {
        $labels = [
            // Documents
            'document.created' => 'Dokument erstellt',
            'document.updated' => 'Dokument aktualisiert',
            'document.deleted' => 'Dokument gelöscht',
            'document.shared' => 'Dokument geteilt',

            // Lists
            'list.created' => 'Liste erstellt',
            'list.updated' => 'Liste aktualisiert',
            'list.deleted' => 'Liste gelöscht',
            'list.item_completed' => 'Listenelement erledigt',

            // Kanban
            'kanban.board_created' => 'Kanban-Board erstellt',
            'kanban.board_deleted' => 'Kanban-Board gelöscht',
            'kanban.card_created' => 'Karte erstellt',
            'kanban.card_moved' => 'Karte verschoben',
            'kanban.card_completed' => 'Karte abgeschlossen',
            'kanban.card_deleted' => 'Karte gelöscht',

            // Projects
            'project.created' => 'Projekt erstellt',
            'project.updated' => 'Projekt aktualisiert',
            'project.deleted' => 'Projekt gelöscht',
            'project.archived' => 'Projekt archiviert',

            // Time Tracking
            'time.started' => 'Zeiterfassung gestartet',
            'time.stopped' => 'Zeiterfassung gestoppt',
            'time.entry_created' => 'Zeiteintrag erstellt',
            'time.entry_updated' => 'Zeiteintrag aktualisiert',
            'time.entry_deleted' => 'Zeiteintrag gelöscht',

            // User & Authentication
            'user.registered' => 'Benutzer registriert',
            'user.login' => 'Benutzer angemeldet',
            'user.logout' => 'Benutzer abgemeldet',
            'user.password_changed' => 'Passwort geändert',
            'user.profile_updated' => 'Profil aktualisiert',
            'user.deleted' => 'Benutzer gelöscht',
            'user.invited' => 'Benutzer eingeladen',
            'user.2fa_enabled' => '2FA aktiviert',
            'user.2fa_disabled' => '2FA deaktiviert',

            // Tickets
            'ticket.created' => 'Ticket erstellt',
            'ticket.updated' => 'Ticket aktualisiert',
            'ticket.assigned' => 'Ticket zugewiesen',
            'ticket.status_changed' => 'Ticket-Status geändert',
            'ticket.comment_added' => 'Ticket-Kommentar hinzugefügt',
            'ticket.resolved' => 'Ticket gelöst',
            'ticket.closed' => 'Ticket geschlossen',
            'ticket.reopened' => 'Ticket wiedereröffnet',
            'ticket.deleted' => 'Ticket gelöscht',

            // Invoices
            'invoice.created' => 'Rechnung erstellt',
            'invoice.updated' => 'Rechnung aktualisiert',
            'invoice.sent' => 'Rechnung versendet',
            'invoice.paid' => 'Rechnung bezahlt',
            'invoice.overdue' => 'Rechnung überfällig',
            'invoice.cancelled' => 'Rechnung storniert',
            'invoice.deleted' => 'Rechnung gelöscht',

            // Clients
            'client.created' => 'Kunde erstellt',
            'client.updated' => 'Kunde aktualisiert',
            'client.deleted' => 'Kunde gelöscht',

            // Chat
            'chat.room_created' => 'Chatraum erstellt',
            'chat.room_deleted' => 'Chatraum gelöscht',
            'chat.message_sent' => 'Nachricht gesendet',
            'chat.message_deleted' => 'Nachricht gelöscht',
            'chat.user_joined' => 'Benutzer beigetreten',
            'chat.user_left' => 'Benutzer verlassen',

            // Wiki
            'wiki.page_created' => 'Wiki-Seite erstellt',
            'wiki.page_updated' => 'Wiki-Seite aktualisiert',
            'wiki.page_deleted' => 'Wiki-Seite gelöscht',

            // Snippets
            'snippet.created' => 'Snippet erstellt',
            'snippet.updated' => 'Snippet aktualisiert',
            'snippet.deleted' => 'Snippet gelöscht',

            // Bookmarks
            'bookmark.created' => 'Lesezeichen erstellt',
            'bookmark.updated' => 'Lesezeichen aktualisiert',
            'bookmark.deleted' => 'Lesezeichen gelöscht',

            // Links
            'link.created' => 'Link erstellt',
            'link.updated' => 'Link aktualisiert',
            'link.deleted' => 'Link gelöscht',
            'link.clicked' => 'Link geklickt',

            // Calendar
            'calendar.event_created' => 'Termin erstellt',
            'calendar.event_updated' => 'Termin aktualisiert',
            'calendar.event_deleted' => 'Termin gelöscht',
            'calendar.event_starting' => 'Termin beginnt',
            'calendar.event_reminder' => 'Terminerinnerung',

            // Checklists
            'checklist.created' => 'Checkliste erstellt',
            'checklist.updated' => 'Checkliste aktualisiert',
            'checklist.deleted' => 'Checkliste gelöscht',
            'checklist.item_completed' => 'Checklistenpunkt erledigt',

            // Recurring Tasks
            'recurring.task_created' => 'Wiederkehrende Aufgabe erstellt',
            'recurring.task_updated' => 'Wiederkehrende Aufgabe aktualisiert',
            'recurring.task_deleted' => 'Wiederkehrende Aufgabe gelöscht',
            'recurring.task_due' => 'Wiederkehrende Aufgabe fällig',
            'recurring.task_completed' => 'Wiederkehrende Aufgabe erledigt',

            // Quick Notes
            'quicknote.created' => 'Schnellnotiz erstellt',
            'quicknote.updated' => 'Schnellnotiz aktualisiert',
            'quicknote.deleted' => 'Schnellnotiz gelöscht',

            // Templates
            'template.created' => 'Vorlage erstellt',
            'template.updated' => 'Vorlage aktualisiert',
            'template.deleted' => 'Vorlage gelöscht',
            'template.used' => 'Vorlage verwendet',

            // Files
            'file.uploaded' => 'Datei hochgeladen',
            'file.updated' => 'Datei aktualisiert',
            'file.deleted' => 'Datei gelöscht',
            'file.shared' => 'Datei geteilt',
            'file.downloaded' => 'Datei heruntergeladen',

            // Passwords (Vault)
            'password.created' => 'Passwort erstellt',
            'password.updated' => 'Passwort aktualisiert',
            'password.deleted' => 'Passwort gelöscht',
            'password.shared' => 'Passwort geteilt',
            'password.accessed' => 'Passwort aufgerufen',

            // API Keys
            'apikey.created' => 'API-Schlüssel erstellt',
            'apikey.revoked' => 'API-Schlüssel widerrufen',
            'apikey.used' => 'API-Schlüssel verwendet',

            // Backup
            'backup.started' => 'Backup gestartet',
            'backup.completed' => 'Backup abgeschlossen',
            'backup.failed' => 'Backup fehlgeschlagen',
            'backup.restored' => 'Backup wiederhergestellt',

            // Docker
            'docker.container_started' => 'Container gestartet',
            'docker.container_stopped' => 'Container gestoppt',
            'docker.container_restarted' => 'Container neugestartet',
            'docker.container_created' => 'Container erstellt',
            'docker.container_deleted' => 'Container gelöscht',
            'docker.image_pulled' => 'Image heruntergeladen',

            // Uptime Monitor
            'monitor.created' => 'Monitor erstellt',
            'monitor.deleted' => 'Monitor gelöscht',
            'monitor.down' => 'Monitor offline',
            'monitor.up' => 'Monitor online',
            'monitor.ssl_expiring' => 'SSL-Zertifikat läuft ab',

            // Tags
            'tag.created' => 'Tag erstellt',
            'tag.updated' => 'Tag aktualisiert',
            'tag.deleted' => 'Tag gelöscht',

            // Connections
            'connection.created' => 'Verbindung erstellt',
            'connection.updated' => 'Verbindung aktualisiert',
            'connection.deleted' => 'Verbindung gelöscht',
            'connection.tested' => 'Verbindung getestet',

            // AI
            'ai.request_completed' => 'KI-Anfrage abgeschlossen',
            'ai.request_failed' => 'KI-Anfrage fehlgeschlagen',

            // Comments
            'comment.created' => 'Kommentar erstellt',
            'comment.updated' => 'Kommentar aktualisiert',
            'comment.deleted' => 'Kommentar gelöscht',

            // Notifications
            'notification.sent' => 'Benachrichtigung gesendet',

            // Inbox
            'inbox.message_received' => 'Nachricht empfangen',
            'inbox.message_read' => 'Nachricht gelesen',

            // System
            'system.maintenance_started' => 'Wartung gestartet',
            'system.maintenance_completed' => 'Wartung abgeschlossen',
            'system.update_available' => 'Update verfügbar',

            // Test
            'test' => 'Test-Webhook',
        ];

        return $labels[$event] ?? $event;
    }

    private function buildPayload(string $type, string $event, array $data): array
    {
        $action = explode('.', $event)[1] ?? '';
        $category = explode('.', $event)[0] ?? '';

        if ($type === 'discord') {
            $color = $this->getEventColor($action, $category);

            return [
                'embeds' => [[
                    'title' => $this->getEventLabel($event),
                    'description' => $data['message'] ?? $data['description'] ?? '',
                    'color' => $color,
                    'timestamp' => $data['timestamp'] ?? date('c'),
                    'footer' => ['text' => 'KyuubiSoft'],
                    'fields' => $this->buildDiscordFields($data),
                ]],
            ];
        }

        if ($type === 'slack') {
            $slackColor = $this->getSlackColor($action, $category);

            return [
                'text' => $this->getEventLabel($event),
                'attachments' => [[
                    'text' => $data['message'] ?? $data['description'] ?? '',
                    'color' => $slackColor,
                    'footer' => 'KyuubiSoft',
                    'ts' => time(),
                    'fields' => $this->buildSlackFields($data),
                ]],
            ];
        }

        // Custom webhook - send raw data
        return [
            'event' => $event,
            'data' => $data,
            'timestamp' => date('c'),
        ];
    }

    private function getEventColor(string $action, string $category): int
    {
        // Category-specific colors for critical events
        $categoryColors = [
            'backup' => match($action) {
                'completed' => 0x22C55E, // green
                'failed' => 0xEF4444,    // red
                'started' => 0xF59E0B,   // amber
                'restored' => 0x3B82F6,  // blue
                default => 0x6366F1,
            },
            'monitor' => match($action) {
                'up' => 0x22C55E,         // green
                'down' => 0xEF4444,       // red
                'ssl_expiring' => 0xF59E0B, // amber
                default => 0x6366F1,
            },
            'invoice' => match($action) {
                'paid' => 0x22C55E,      // green
                'overdue' => 0xEF4444,   // red
                'sent' => 0x3B82F6,      // blue
                'cancelled' => 0x6B7280, // gray
                default => 0x6366F1,
            },
            'ticket' => match($action) {
                'resolved', 'closed' => 0x22C55E, // green
                'reopened' => 0xF59E0B,  // amber
                'assigned' => 0x8B5CF6,  // purple
                default => 0x6366F1,
            },
            'user' => match($action) {
                'registered' => 0x22C55E, // green
                'deleted' => 0xEF4444,    // red
                'login' => 0x3B82F6,      // blue
                'logout' => 0x6B7280,     // gray
                '2fa_enabled' => 0x22C55E, // green
                '2fa_disabled' => 0xF59E0B, // amber
                'password_changed' => 0xF59E0B, // amber
                default => 0x6366F1,
            },
            'docker' => match($action) {
                'container_started' => 0x22C55E, // green
                'container_stopped' => 0xEF4444, // red
                'container_restarted' => 0xF59E0B, // amber
                default => 0x6366F1,
            },
            'ai' => match($action) {
                'request_completed' => 0x22C55E, // green
                'request_failed' => 0xEF4444,    // red
                default => 0x6366F1,
            },
            'system' => match($action) {
                'maintenance_started' => 0xF59E0B,   // amber
                'maintenance_completed' => 0x22C55E, // green
                'update_available' => 0x3B82F6,      // blue
                default => 0x6366F1,
            },
        ];

        if (isset($categoryColors[$category])) {
            return $categoryColors[$category];
        }

        // Default action-based colors
        return match($action) {
            'created', 'uploaded', 'registered', 'started', 'up', 'completed', 'resolved', 'paid' => 0x22C55E, // green
            'updated', 'moved', 'sent', 'login' => 0x3B82F6, // blue
            'deleted', 'revoked', 'cancelled', 'failed', 'down', 'stopped' => 0xEF4444, // red
            'completed', 'item_completed', 'task_completed' => 0x8B5CF6, // purple
            'shared', 'assigned', 'invited' => 0x10B981, // emerald
            'archived', 'closed', 'logout' => 0x6B7280, // gray
            'due', 'overdue', 'ssl_expiring', 'reminder', 'starting' => 0xF59E0B, // amber
            'reopened', 'accessed', 'clicked', 'used', 'downloaded' => 0x06B6D4, // cyan
            default => 0x6366F1, // indigo
        };
    }

    private function getSlackColor(string $action, string $category): string
    {
        $color = $this->getEventColor($action, $category);
        return '#' . str_pad(dechex($color), 6, '0', STR_PAD_LEFT);
    }

    private function buildDiscordFields(array $data): array
    {
        $fields = [];
        $allowedFields = ['name', 'title', 'status', 'priority', 'amount', 'user', 'project', 'client', 'url'];

        foreach ($allowedFields as $key) {
            if (isset($data[$key]) && !empty($data[$key])) {
                $fields[] = [
                    'name' => ucfirst($key),
                    'value' => (string) $data[$key],
                    'inline' => true,
                ];
            }
        }

        return array_slice($fields, 0, 6); // Discord limit
    }

    private function buildSlackFields(array $data): array
    {
        $fields = [];
        $allowedFields = ['name', 'title', 'status', 'priority', 'amount', 'user', 'project', 'client'];

        foreach ($allowedFields as $key) {
            if (isset($data[$key]) && !empty($data[$key])) {
                $fields[] = [
                    'title' => ucfirst($key),
                    'value' => (string) $data[$key],
                    'short' => true,
                ];
            }
        }

        return array_slice($fields, 0, 8);
    }

    private function sendWebhook(array $webhook, string $event, array $payload): array
    {
        $headers = [
            'Content-Type: application/json',
            'User-Agent: KyuubiSoft/1.0',
        ];

        // Add HMAC signature for custom webhooks
        if ($webhook['type'] === 'custom' && !empty($webhook['secret'])) {
            $signature = hash_hmac('sha256', json_encode($payload), $webhook['secret']);
            $headers[] = 'X-Signature: ' . $signature;
        }

        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $responseBody = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $success = $status >= 200 && $status < 300;

        // Log the webhook call
        $this->db->insert('webhook_logs', [
            'id' => Uuid::uuid4()->toString(),
            'webhook_id' => $webhook['id'],
            'event_type' => $event,
            'payload' => json_encode($payload),
            'response_status' => $status,
            'response_body' => substr($responseBody ?: '', 0, 1000),
            'error_message' => $error ?: null,
        ]);

        // Update webhook status
        $this->db->executeStatement(
            'UPDATE webhooks SET
                last_triggered_at = NOW(),
                last_status = ?,
                failure_count = IF(? = 1, 0, failure_count + 1)
             WHERE id = ?',
            [$success ? 'success' : 'failed', $success ? 1 : 0, $webhook['id']]
        );

        return [
            'success' => $success,
            'status' => $status,
            'error' => $error ?: ($success ? null : 'HTTP ' . $status),
        ];
    }

    // Public method to trigger webhooks from other controllers
    public function trigger(string $userId, string $event, array $data): void
    {
        $webhooks = $this->db->fetchAllAssociative(
            'SELECT * FROM webhooks
             WHERE user_id = ? AND is_active = 1 AND failure_count < 5
             AND JSON_CONTAINS(events, ?)',
            [$userId, json_encode($event)]
        );

        foreach ($webhooks as $webhook) {
            $payload = $this->buildPayload($webhook['type'], $event, $data);
            $this->sendWebhook($webhook, $event, $payload);
        }
    }
}
