<?php

declare(strict_types=1);

namespace App\Modules\NotificationRules\Services;

use App\Modules\Notifications\Services\NotificationService;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class NotificationRuleEngine
{
    public function __construct(
        private readonly Connection $db,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Get the notification service (used by the controller for test notifications)
     */
    public function getNotificationService(): NotificationService
    {
        return $this->notificationService;
    }

    /**
     * Process an event against all active rules for a user
     */
    public function processEvent(string $event, string $userId, array $eventData = []): void
    {
        $rules = $this->db->fetchAllAssociative(
            'SELECT * FROM notification_rules WHERE user_id = ? AND trigger_event = ? AND is_active = TRUE',
            [$userId, $event]
        );

        foreach ($rules as $rule) {
            $this->evaluateRule($rule, $eventData);
        }
    }

    /**
     * Evaluate a single rule against event data
     */
    private function evaluateRule(array $rule, array $eventData): void
    {
        // Check conditions
        $conditions = json_decode($rule['conditions'] ?? '[]', true);
        if (!$this->checkConditions($conditions, $eventData)) {
            return;
        }

        // Execute actions
        $actions = json_decode($rule['actions'], true);
        $result = 'success';
        $errorMessage = null;

        try {
            foreach ($actions as $action) {
                $this->executeAction($action, $rule, $eventData);
            }
        } catch (\Exception $e) {
            $result = 'error';
            $errorMessage = $e->getMessage();
        }

        // Log history
        $this->logExecution($rule['id'], $eventData, $result, $errorMessage);

        // Update trigger count
        $this->db->executeStatement(
            'UPDATE notification_rules SET trigger_count = trigger_count + 1, last_triggered_at = NOW() WHERE id = ?',
            [$rule['id']]
        );
    }

    /**
     * Check if all conditions match the event data
     */
    private function checkConditions(array $conditions, array $eventData): bool
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? '';
            $actual = $eventData[$field] ?? null;

            switch ($operator) {
                case 'equals':
                    if ($actual != $value) return false;
                    break;
                case 'not_equals':
                    if ($actual == $value) return false;
                    break;
                case 'contains':
                    if (!str_contains((string) $actual, $value)) return false;
                    break;
                case 'greater_than':
                    if ((float) $actual <= (float) $value) return false;
                    break;
                case 'less_than':
                    if ((float) $actual >= (float) $value) return false;
                    break;
            }
        }

        return true;
    }

    /**
     * Execute a single action
     */
    private function executeAction(array $action, array $rule, array $eventData): void
    {
        $type = $action['type'] ?? 'push';

        switch ($type) {
            case 'push':
                $this->notificationService->create(
                    $rule['user_id'],
                    $rule['trigger_event'],
                    $action['title'] ?? $rule['name'],
                    $action['message'] ?? 'Regel ausgelöst: ' . $rule['name'],
                    $eventData
                );
                break;

            case 'webhook':
                $url = $action['url'] ?? '';
                if ($url) {
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode([
                            'rule' => $rule['name'],
                            'event' => $rule['trigger_event'],
                            'data' => $eventData,
                        ]),
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_RETURNTRANSFER => true,
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }
                break;
        }
    }

    /**
     * Log a rule execution to history
     */
    private function logExecution(string $ruleId, array $eventData, string $result, ?string $error): void
    {
        $this->db->insert('notification_rule_history', [
            'id' => Uuid::uuid4()->toString(),
            'rule_id' => $ruleId,
            'triggered_at' => date('Y-m-d H:i:s'),
            'event_data' => json_encode($eventData),
            'result' => $result,
            'error_message' => $error,
        ]);
    }

    /**
     * Get available trigger events for the UI
     */
    public function getAvailableEvents(): array
    {
        return [
            ['event' => 'uptime.down', 'label' => 'Monitor geht offline', 'module' => 'Uptime Monitor'],
            ['event' => 'uptime.up', 'label' => 'Monitor wieder online', 'module' => 'Uptime Monitor'],
            ['event' => 'backup.success', 'label' => 'Backup erfolgreich', 'module' => 'Backup'],
            ['event' => 'backup.failed', 'label' => 'Backup fehlgeschlagen', 'module' => 'Backup'],
            ['event' => 'ssl.expiring', 'label' => 'SSL-Zertifikat läuft ab', 'module' => 'SSL'],
            ['event' => 'docker.container.stopped', 'label' => 'Container gestoppt', 'module' => 'Docker'],
            ['event' => 'kanban.card.moved', 'label' => 'Karte verschoben', 'module' => 'Kanban'],
            ['event' => 'kanban.card.assigned', 'label' => 'Karte zugewiesen', 'module' => 'Kanban'],
            ['event' => 'calendar.event.reminder', 'label' => 'Termin-Erinnerung', 'module' => 'Kalender'],
            ['event' => 'ticket.created', 'label' => 'Neues Ticket', 'module' => 'Tickets'],
            ['event' => 'ticket.updated', 'label' => 'Ticket aktualisiert', 'module' => 'Tickets'],
            ['event' => 'server.high_cpu', 'label' => 'Hohe CPU-Auslastung', 'module' => 'Server'],
            ['event' => 'server.high_memory', 'label' => 'Hohe RAM-Auslastung', 'module' => 'Server'],
            ['event' => 'server.disk_full', 'label' => 'Festplatte fast voll', 'module' => 'Server'],
        ];
    }
}
