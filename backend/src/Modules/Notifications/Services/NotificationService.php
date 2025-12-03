<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class NotificationService
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Create a notification for a user
     */
    public function create(
        string $userId,
        string $type,
        string $title,
        ?string $message = null,
        ?array $data = null,
        ?string $link = null
    ): string {
        // Check if user has this type of notification enabled
        if (!$this->isNotificationTypeEnabled($userId, $type)) {
            return '';
        }

        $notificationId = Uuid::uuid4()->toString();

        $this->db->insert('notifications', [
            'id' => $notificationId,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data ? json_encode($data) : null,
            'link' => $link,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $notificationId;
    }

    /**
     * Create notifications for multiple users
     */
    public function createForUsers(
        array $userIds,
        string $type,
        string $title,
        ?string $message = null,
        ?array $data = null,
        ?string $link = null
    ): array {
        $notificationIds = [];

        foreach ($userIds as $userId) {
            $id = $this->create($userId, $type, $title, $message, $data, $link);
            if ($id) {
                $notificationIds[] = $id;
            }
        }

        return $notificationIds;
    }

    /**
     * Notify about uptime monitor status change
     */
    public function notifyUptimeStatusChange(
        string $userId,
        string $monitorName,
        string $status,
        ?string $monitorId = null
    ): string {
        $isDown = $status === 'down';

        return $this->create(
            $userId,
            'uptime_alert',
            $isDown ? "🔴 {$monitorName} ist offline" : "🟢 {$monitorName} ist wieder online",
            $isDown
                ? "Der Monitor '{$monitorName}' ist nicht erreichbar."
                : "Der Monitor '{$monitorName}' ist wieder erreichbar.",
            ['monitor_id' => $monitorId, 'status' => $status],
            $monitorId ? "/uptime/{$monitorId}" : '/uptime'
        );
    }

    /**
     * Notify about Kanban card due date
     */
    public function notifyKanbanDueDate(
        string $userId,
        string $cardTitle,
        string $dueDate,
        ?string $boardId = null,
        ?string $cardId = null
    ): string {
        return $this->create(
            $userId,
            'kanban_reminder',
            "📅 Fällig: {$cardTitle}",
            "Die Karte '{$cardTitle}' ist am {$dueDate} fällig.",
            ['board_id' => $boardId, 'card_id' => $cardId],
            $boardId ? "/kanban/{$boardId}" : '/kanban'
        );
    }

    /**
     * Notify about Kanban card assignment
     */
    public function notifyKanbanAssignment(
        string $userId,
        string $cardTitle,
        string $assignerName,
        ?string $boardId = null,
        ?string $cardId = null
    ): string {
        return $this->create(
            $userId,
            'kanban_reminder',
            "📋 Neue Aufgabe zugewiesen",
            "{$assignerName} hat dir die Karte '{$cardTitle}' zugewiesen.",
            ['board_id' => $boardId, 'card_id' => $cardId],
            $boardId ? "/kanban/{$boardId}" : '/kanban'
        );
    }

    /**
     * Notify about webhook event
     */
    public function notifyWebhookEvent(
        string $userId,
        string $webhookName,
        string $eventType,
        ?string $webhookId = null
    ): string {
        return $this->create(
            $userId,
            'webhook_alert',
            "🔔 Webhook: {$webhookName}",
            "Event '{$eventType}' wurde empfangen.",
            ['webhook_id' => $webhookId, 'event_type' => $eventType],
            $webhookId ? "/webhooks/{$webhookId}" : '/webhooks'
        );
    }

    /**
     * Notify about system event
     */
    public function notifySystem(
        string $userId,
        string $title,
        ?string $message = null,
        ?string $link = null
    ): string {
        return $this->create(
            $userId,
            'system_alert',
            "⚙️ {$title}",
            $message,
            null,
            $link
        );
    }

    /**
     * Check if a notification type is enabled for user
     */
    private function isNotificationTypeEnabled(string $userId, string $type): bool
    {
        $preferences = $this->db->fetchAssociative(
            'SELECT * FROM notification_preferences WHERE user_id = ?',
            [$userId]
        );

        if (!$preferences) {
            // Default: all enabled
            return true;
        }

        return match ($type) {
            'uptime_alert' => (bool) $preferences['uptime_alerts'],
            'kanban_reminder' => (bool) $preferences['kanban_reminders'],
            'webhook_alert' => (bool) $preferences['webhook_alerts'],
            'system_alert' => (bool) $preferences['system_alerts'],
            default => true,
        };
    }

    /**
     * Clean up old read notifications (older than 30 days)
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        return $this->db->executeStatement(
            'DELETE FROM notifications WHERE is_read = TRUE AND read_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$daysOld],
            [\PDO::PARAM_INT]
        );
    }
}
