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

    // Available events
    private array $availableEvents = [
        'document.created',
        'document.updated',
        'document.deleted',
        'list.created',
        'list.updated',
        'list.item_completed',
        'kanban.card_created',
        'kanban.card_moved',
        'kanban.card_completed',
        'project.created',
        'project.updated',
        'time.started',
        'time.stopped',
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

        return JsonResponse::success( [
            'id' => $id,
            'message' => 'Webhook erstellt',
        ], 201);
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
            'document.created' => 'Dokument erstellt',
            'document.updated' => 'Dokument aktualisiert',
            'document.deleted' => 'Dokument gelöscht',
            'list.created' => 'Liste erstellt',
            'list.updated' => 'Liste aktualisiert',
            'list.item_completed' => 'Listenelement erledigt',
            'kanban.card_created' => 'Karte erstellt',
            'kanban.card_moved' => 'Karte verschoben',
            'kanban.card_completed' => 'Karte abgeschlossen',
            'project.created' => 'Projekt erstellt',
            'project.updated' => 'Projekt aktualisiert',
            'time.started' => 'Zeiterfassung gestartet',
            'time.stopped' => 'Zeiterfassung gestoppt',
        ];

        return $labels[$event] ?? $event;
    }

    private function buildPayload(string $type, string $event, array $data): array
    {
        if ($type === 'discord') {
            $color = match(explode('.', $event)[1] ?? '') {
                'created' => 0x22C55E, // green
                'updated' => 0x3B82F6, // blue
                'deleted' => 0xEF4444, // red
                'completed' => 0x8B5CF6, // purple
                default => 0x6366F1, // indigo
            };

            return [
                'embeds' => [[
                    'title' => $this->getEventLabel($event),
                    'description' => $data['message'] ?? $data['description'] ?? '',
                    'color' => $color,
                    'timestamp' => $data['timestamp'] ?? date('c'),
                    'footer' => ['text' => 'KyuubiSoft'],
                ]],
            ];
        }

        if ($type === 'slack') {
            return [
                'text' => $this->getEventLabel($event),
                'attachments' => [[
                    'text' => $data['message'] ?? $data['description'] ?? '',
                    'color' => '#6366F1',
                    'footer' => 'KyuubiSoft',
                    'ts' => time(),
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

    private function sendWebhook(array $webhook, string $event, array $payload): array
    {
        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: KyuubiSoft/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        // Add HMAC signature for custom webhooks
        if ($webhook['type'] === 'custom' && !empty($webhook['secret'])) {
            $signature = hash_hmac('sha256', json_encode($payload), $webhook['secret']);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'User-Agent: KyuubiSoft/1.0',
                'X-Signature: ' . $signature,
            ]);
        }

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
