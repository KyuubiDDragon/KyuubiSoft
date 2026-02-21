<?php

declare(strict_types=1);

namespace App\Core\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class NotificationService
{
    // Notification types
    public const TYPE_TASK_DUE = 'task_due';
    public const TYPE_TASK_ASSIGNED = 'task_assigned';
    public const TYPE_MENTION = 'mention';
    public const TYPE_SHARE = 'share';
    public const TYPE_COMMENT = 'comment';
    public const TYPE_PROJECT_UPDATE = 'project_update';
    public const TYPE_RECURRING_TASK = 'recurring_task';
    public const TYPE_SYSTEM = 'system';
    public const TYPE_SECURITY = 'security';

    // Channel types
    public const CHANNEL_IN_APP = 'in_app';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_WEBHOOK = 'webhook';
    public const CHANNEL_SLACK = 'slack';
    public const CHANNEL_TELEGRAM = 'telegram';

    public function __construct(
        private readonly Connection $db,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Send a notification to a user
     */
    public function notify(
        string $userId,
        string $type,
        string $title,
        ?string $message = null,
        ?array $data = null,
        ?string $link = null,
        string $priority = 'normal'
    ): string {
        // Create notification
        $notificationId = Uuid::uuid4()->toString();

        $this->db->insert('notifications', [
            'id' => $notificationId,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data ? json_encode($data) : null,
            'link' => $link,
            'priority' => $priority,
            'is_read' => false,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Get user's preferences for this type
        $preferences = $this->getUserPreferences($userId, $type);

        // Send to enabled channels
        $channels = $this->getUserChannels($userId);
        foreach ($channels as $channel) {
            if (!$channel['is_enabled']) continue;

            // Check if this type is enabled for this channel
            if (!empty($preferences) && !empty($preferences['channels'])) {
                $enabledChannels = json_decode($preferences['channels'], true) ?? [];
                if (!in_array($channel['channel_type'], $enabledChannels)) {
                    continue;
                }
            }

            $this->deliverToChannel($notificationId, $userId, $channel, $title, $message, $data, $link, $priority);
        }

        return $notificationId;
    }

    /**
     * Deliver notification to a specific channel
     */
    private function deliverToChannel(
        string $notificationId,
        string $userId,
        array $channel,
        string $title,
        ?string $message,
        ?array $data,
        ?string $link,
        string $priority
    ): void {
        $deliveryId = Uuid::uuid4()->toString();

        try {
            switch ($channel['channel_type']) {
                case self::CHANNEL_IN_APP:
                    // Already stored in notifications table
                    $status = 'sent';
                    break;

                case self::CHANNEL_EMAIL:
                    $status = $this->sendEmailNotification($userId, $title, $message, $link);
                    break;

                case self::CHANNEL_WEBHOOK:
                    $config = $channel['config'] ? json_decode($channel['config'], true) : [];
                    $status = $this->sendWebhookNotification($config, $title, $message, $data, $link, $priority);
                    break;

                case self::CHANNEL_SLACK:
                    $config = $channel['config'] ? json_decode($channel['config'], true) : [];
                    $status = $this->sendSlackNotification($config, $title, $message, $link);
                    break;

                case self::CHANNEL_TELEGRAM:
                    $config = $channel['config'] ? json_decode($channel['config'], true) : [];
                    $status = $this->sendTelegramNotification($config, $title, $message, $link);
                    break;

                default:
                    $status = 'skipped';
            }

            $this->db->insert('notification_deliveries', [
                'id' => $deliveryId,
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'channel_type' => $channel['channel_type'],
                'status' => $status,
                'sent_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            $this->db->insert('notification_deliveries', [
                'id' => $deliveryId,
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'channel_type' => $channel['channel_type'],
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->logger?->error('Notification delivery failed', [
                'notification_id' => $notificationId,
                'channel' => $channel['channel_type'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(string $userId, string $title, ?string $message, ?string $link): string
    {
        // Get user's email
        $user = $this->db->fetchAssociative(
            'SELECT email FROM users WHERE id = ?',
            [$userId]
        );

        if (!$user || empty($user['email'])) {
            return 'skipped';
        }

        // TODO: Implement actual email sending via mail service
        // For now, log and return sent
        $this->logger?->info('Email notification would be sent', [
            'to' => $user['email'],
            'title' => $title,
            'message' => $message,
        ]);

        return 'sent';
    }

    /**
     * Send webhook notification
     */
    private function sendWebhookNotification(array $config, string $title, ?string $message, ?array $data, ?string $link, string $priority): string
    {
        $webhookUrl = $config['url'] ?? null;
        if (!$webhookUrl) {
            return 'skipped';
        }

        $payload = [
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'link' => $link,
            'priority' => $priority,
            'timestamp' => date('c'),
        ];

        $headers = [
            'Content-Type: application/json',
        ];

        if (!empty($config['secret'])) {
            $signature = hash_hmac('sha256', json_encode($payload), $config['secret']);
            $headers[] = 'X-Webhook-Signature: ' . $signature;
        }

        // Convert flat header strings to associative array
        $parsedHeaders = [];
        foreach ($headers as $header) {
            [$name, $value] = explode(': ', $header, 2);
            $parsedHeaders[$name] = $value;
        }

        try {
            $res = (new GuzzleClient(['timeout' => 10]))->post($webhookUrl, [
                'headers' => $parsedHeaders,
                'json'    => $payload,
            ]);
            return ($res->getStatusCode() >= 200 && $res->getStatusCode() < 300) ? 'sent' : 'failed';
        } catch (GuzzleException) {
            return 'failed';
        }
    }

    /**
     * Send Slack notification
     */
    private function sendSlackNotification(array $config, string $title, ?string $message, ?string $link): string
    {
        $webhookUrl = $config['webhook_url'] ?? null;
        if (!$webhookUrl) {
            return 'skipped';
        }

        $payload = [
            'text' => $title,
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*{$title}*\n" . ($message ?? ''),
                    ],
                ],
            ],
        ];

        if ($link) {
            $payload['blocks'][] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "<{$link}|Details anzeigen>",
                ],
            ];
        }

        try {
            $res = (new GuzzleClient(['timeout' => 10]))->post($webhookUrl, [
                'json' => $payload,
            ]);
            return ($res->getStatusCode() >= 200 && $res->getStatusCode() < 300) ? 'sent' : 'failed';
        } catch (GuzzleException) {
            return 'failed';
        }
    }

    /**
     * Send Telegram notification
     */
    private function sendTelegramNotification(array $config, string $title, ?string $message, ?string $link): string
    {
        $botToken = $config['bot_token'] ?? null;
        $chatId = $config['chat_id'] ?? null;

        if (!$botToken || !$chatId) {
            return 'skipped';
        }

        $text = "*{$title}*\n";
        if ($message) {
            $text .= "\n{$message}";
        }
        if ($link) {
            $text .= "\n\n[Details]({$link})";
        }

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true,
        ];

        try {
            $res = (new GuzzleClient(['timeout' => 10]))->post($url, [
                'form_params' => $payload,
            ]);
            return ($res->getStatusCode() >= 200 && $res->getStatusCode() < 300) ? 'sent' : 'failed';
        } catch (GuzzleException) {
            return 'failed';
        }
    }

    /**
     * Get user's notifications
     */
    public function getNotifications(string $userId, bool $unreadOnly = false, int $limit = 50, int $offset = 0): array
    {
        $where = 'user_id = ?';
        $params = [$userId];

        if ($unreadOnly) {
            $where .= ' AND is_read = FALSE';
        }

        $notifications = $this->db->fetchAllAssociative(
            "SELECT * FROM notifications WHERE {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [...$params, $limit, $offset],
            [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]
        );

        foreach ($notifications as &$notification) {
            $notification['data'] = $notification['data'] ? json_decode($notification['data'], true) : null;
        }

        return $notifications;
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(string $userId): int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE',
            [$userId]
        );
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $userId, string $notificationId): bool
    {
        $affected = $this->db->update('notifications', [
            'is_read' => true,
            'read_at' => date('Y-m-d H:i:s'),
        ], ['id' => $notificationId, 'user_id' => $userId]);

        return $affected > 0;
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(string $userId): int
    {
        return $this->db->executeStatement(
            'UPDATE notifications SET is_read = TRUE, read_at = ? WHERE user_id = ? AND is_read = FALSE',
            [date('Y-m-d H:i:s'), $userId]
        );
    }

    /**
     * Delete notification
     */
    public function delete(string $userId, string $notificationId): bool
    {
        $affected = $this->db->delete('notifications', [
            'id' => $notificationId,
            'user_id' => $userId,
        ]);

        return $affected > 0;
    }

    /**
     * Get user's notification channels
     */
    public function getUserChannels(string $userId): array
    {
        $channels = $this->db->fetchAllAssociative(
            'SELECT * FROM notification_channels WHERE user_id = ?',
            [$userId]
        );

        // Ensure in_app channel exists
        $hasInApp = false;
        foreach ($channels as $channel) {
            if ($channel['channel_type'] === self::CHANNEL_IN_APP) {
                $hasInApp = true;
                break;
            }
        }

        if (!$hasInApp) {
            // Create default in_app channel
            $id = Uuid::uuid4()->toString();
            $this->db->insert('notification_channels', [
                'id' => $id,
                'user_id' => $userId,
                'channel_type' => self::CHANNEL_IN_APP,
                'is_enabled' => true,
                'config' => '{}',
            ]);

            $channels[] = [
                'id' => $id,
                'user_id' => $userId,
                'channel_type' => self::CHANNEL_IN_APP,
                'is_enabled' => true,
                'config' => '{}',
            ];
        }

        return $channels;
    }

    /**
     * Update notification channel
     */
    public function updateChannel(string $userId, string $channelType, array $data): array
    {
        $channel = $this->db->fetchAssociative(
            'SELECT id FROM notification_channels WHERE user_id = ? AND channel_type = ?',
            [$userId, $channelType]
        );

        $updateData = [];
        if (isset($data['is_enabled'])) {
            $updateData['is_enabled'] = $data['is_enabled'] ? 1 : 0;
        }
        if (isset($data['config'])) {
            $updateData['config'] = json_encode($data['config']);
        }
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        if ($channel) {
            $this->db->update('notification_channels', $updateData, ['id' => $channel['id']]);
        } else {
            $updateData['id'] = Uuid::uuid4()->toString();
            $updateData['user_id'] = $userId;
            $updateData['channel_type'] = $channelType;
            if (!isset($updateData['is_enabled'])) {
                $updateData['is_enabled'] = 1;
            }
            $this->db->insert('notification_channels', $updateData);
        }

        return $this->db->fetchAssociative(
            'SELECT * FROM notification_channels WHERE user_id = ? AND channel_type = ?',
            [$userId, $channelType]
        ) ?: [];
    }

    /**
     * Get user's notification preferences
     */
    public function getUserPreferences(string $userId, ?string $type = null): array
    {
        if ($type) {
            return $this->db->fetchAssociative(
                'SELECT * FROM notification_preferences WHERE user_id = ? AND notification_type = ?',
                [$userId, $type]
            ) ?: [];
        }

        return $this->db->fetchAllAssociative(
            'SELECT * FROM notification_preferences WHERE user_id = ?',
            [$userId]
        );
    }

    /**
     * Update notification preference
     */
    public function updatePreference(string $userId, string $type, array $data): array
    {
        $pref = $this->db->fetchAssociative(
            'SELECT id FROM notification_preferences WHERE user_id = ? AND notification_type = ?',
            [$userId, $type]
        );

        $updateData = [];
        if (isset($data['channels'])) {
            $updateData['channels'] = json_encode($data['channels']);
        }
        if (isset($data['is_enabled'])) {
            $updateData['is_enabled'] = $data['is_enabled'] ? 1 : 0;
        }
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        if ($pref) {
            $this->db->update('notification_preferences', $updateData, ['id' => $pref['id']]);
        } else {
            $updateData['id'] = Uuid::uuid4()->toString();
            $updateData['user_id'] = $userId;
            $updateData['notification_type'] = $type;
            if (!isset($updateData['is_enabled'])) {
                $updateData['is_enabled'] = 1;
            }
            $this->db->insert('notification_preferences', $updateData);
        }

        return $this->db->fetchAssociative(
            'SELECT * FROM notification_preferences WHERE user_id = ? AND notification_type = ?',
            [$userId, $type]
        ) ?: [];
    }

    /**
     * Get available notification types
     */
    public function getNotificationTypes(): array
    {
        return [
            self::TYPE_TASK_DUE => ['label' => 'Aufgabe fällig', 'description' => 'Benachrichtigung wenn eine Aufgabe fällig wird'],
            self::TYPE_TASK_ASSIGNED => ['label' => 'Aufgabe zugewiesen', 'description' => 'Benachrichtigung wenn dir eine Aufgabe zugewiesen wird'],
            self::TYPE_MENTION => ['label' => 'Erwähnung', 'description' => 'Benachrichtigung wenn du erwähnt wirst'],
            self::TYPE_SHARE => ['label' => 'Freigabe', 'description' => 'Benachrichtigung wenn etwas mit dir geteilt wird'],
            self::TYPE_COMMENT => ['label' => 'Kommentar', 'description' => 'Benachrichtigung bei neuen Kommentaren'],
            self::TYPE_PROJECT_UPDATE => ['label' => 'Projekt-Update', 'description' => 'Benachrichtigung bei Projektänderungen'],
            self::TYPE_RECURRING_TASK => ['label' => 'Wiederkehrende Aufgabe', 'description' => 'Benachrichtigung wenn eine wiederkehrende Aufgabe erstellt wird'],
            self::TYPE_SYSTEM => ['label' => 'System', 'description' => 'Wichtige Systembenachrichtigungen'],
            self::TYPE_SECURITY => ['label' => 'Sicherheit', 'description' => 'Sicherheitsrelevante Benachrichtigungen'],
        ];
    }
}
