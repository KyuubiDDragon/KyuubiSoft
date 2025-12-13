<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Services;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class WebhookService
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Trigger webhooks for a specific event
     */
    public function trigger(string $userId, string $event, array $data = []): void
    {
        // Find active webhooks that are subscribed to this event
        $webhooks = $this->db->fetchAllAssociative(
            'SELECT * FROM webhooks
             WHERE user_id = ? AND is_active = 1 AND failure_count < 5
             AND JSON_CONTAINS(events, ?)',
            [$userId, json_encode($event)]
        );

        foreach ($webhooks as $webhook) {
            $this->sendWebhookAsync($webhook, $event, $data);
        }
    }

    /**
     * Send webhook asynchronously (fire and forget)
     */
    private function sendWebhookAsync(array $webhook, string $event, array $data): void
    {
        $payload = $this->buildPayload($webhook['type'], $event, $data);

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
            CURLOPT_TIMEOUT => 5, // Short timeout for async
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
    }

    private function buildPayload(string $type, string $event, array $data): array
    {
        $action = explode('.', $event)[1] ?? '';
        $category = explode('.', $event)[0] ?? '';

        if ($type === 'discord') {
            return [
                'embeds' => [[
                    'title' => $this->getEventLabel($event),
                    'description' => $data['message'] ?? $data['description'] ?? '',
                    'color' => $this->getEventColor($action, $category),
                    'timestamp' => $data['timestamp'] ?? date('c'),
                    'footer' => ['text' => 'KyuubiSoft'],
                    'fields' => $this->buildDiscordFields($data),
                ]],
            ];
        }

        if ($type === 'slack') {
            return [
                'text' => $this->getEventLabel($event),
                'attachments' => [[
                    'text' => $data['message'] ?? $data['description'] ?? '',
                    'color' => '#' . str_pad(dechex($this->getEventColor($action, $category)), 6, '0', STR_PAD_LEFT),
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

    private function getEventLabel(string $event): string
    {
        $labels = [
            'document.created' => 'Dokument erstellt',
            'document.updated' => 'Dokument aktualisiert',
            'document.deleted' => 'Dokument gelöscht',
            'list.created' => 'Liste erstellt',
            'list.updated' => 'Liste aktualisiert',
            'list.deleted' => 'Liste gelöscht',
            'list.item_completed' => 'Listenelement erledigt',
            'kanban.board_created' => 'Kanban-Board erstellt',
            'kanban.board_deleted' => 'Kanban-Board gelöscht',
            'kanban.card_created' => 'Karte erstellt',
            'kanban.card_moved' => 'Karte verschoben',
            'kanban.card_deleted' => 'Karte gelöscht',
            'project.created' => 'Projekt erstellt',
            'project.updated' => 'Projekt aktualisiert',
            'project.deleted' => 'Projekt gelöscht',
            'time.started' => 'Zeiterfassung gestartet',
            'time.stopped' => 'Zeiterfassung gestoppt',
            'ticket.created' => 'Ticket erstellt',
            'ticket.status_changed' => 'Ticket-Status geändert',
            'ticket.resolved' => 'Ticket gelöst',
            'invoice.created' => 'Rechnung erstellt',
            'invoice.paid' => 'Rechnung bezahlt',
            'monitor.down' => 'Monitor offline',
            'monitor.up' => 'Monitor online',
            'backup.completed' => 'Backup abgeschlossen',
            'backup.failed' => 'Backup fehlgeschlagen',
        ];

        return $labels[$event] ?? $event;
    }

    private function getEventColor(string $action, string $category): int
    {
        // Category-specific colors
        if ($category === 'monitor') {
            return match($action) {
                'up' => 0x22C55E,    // green
                'down' => 0xEF4444, // red
                default => 0x6366F1,
            };
        }

        if ($category === 'backup') {
            return match($action) {
                'completed' => 0x22C55E, // green
                'failed' => 0xEF4444,    // red
                default => 0x6366F1,
            };
        }

        // Default action-based colors
        return match($action) {
            'created', 'started', 'up', 'completed', 'resolved', 'paid' => 0x22C55E, // green
            'updated', 'moved' => 0x3B82F6, // blue
            'deleted', 'failed', 'down', 'stopped' => 0xEF4444, // red
            'item_completed', 'task_completed' => 0x8B5CF6, // purple
            default => 0x6366F1, // indigo
        };
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

        return array_slice($fields, 0, 6);
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
}
