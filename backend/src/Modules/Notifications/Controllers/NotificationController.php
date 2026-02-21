<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class NotificationController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($queryParams['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $unreadOnly = ($queryParams['unread'] ?? null) === '1';

        $sql = 'SELECT * FROM notifications WHERE user_id = ?';
        $params = [$userId];

        if ($unreadOnly) {
            $sql .= ' AND is_read = FALSE';
        }

        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $perPage;
        $params[] = $offset;

        $notifications = $this->db->fetchAllAssociative($sql, $params, [
            \PDO::PARAM_STR,
            \PDO::PARAM_INT,
            \PDO::PARAM_INT,
        ]);

        // Parse JSON data
        foreach ($notifications as &$notification) {
            $notification['data'] = $notification['data'] ? json_decode($notification['data'], true) : null;
        }

        // Count total
        $countSql = 'SELECT COUNT(*) FROM notifications WHERE user_id = ?';
        $countParams = [$userId];
        if ($unreadOnly) {
            $countSql .= ' AND is_read = FALSE';
        }
        $total = (int) $this->db->fetchOne($countSql, $countParams);

        return JsonResponse::paginated($notifications, $total, $page, $perPage);
    }

    public function unreadCount(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $count = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE',
            [$userId]
        );

        return JsonResponse::success(['count' => $count]);
    }

    public function markAsRead(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $notificationId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $notification = $this->db->fetchAssociative(
            'SELECT * FROM notifications WHERE id = ? AND user_id = ?',
            [$notificationId, $userId]
        );

        if (!$notification) {
            throw new NotFoundException('Notification not found');
        }

        $this->db->update('notifications', [
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s'),
        ], ['id' => $notificationId]);

        return JsonResponse::success(null, 'Notification marked as read');
    }

    public function markAllAsRead(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $this->db->executeStatement(
            'UPDATE notifications SET is_read = TRUE, read_at = ? WHERE user_id = ? AND is_read = FALSE',
            [date('Y-m-d H:i:s'), $userId]
        );

        return JsonResponse::success(null, 'All notifications marked as read');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $notificationId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->db->delete('notifications', [
            'id' => $notificationId,
            'user_id' => $userId,
        ]);

        return JsonResponse::success(null, 'Notification deleted');
    }

    public function clearAll(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $this->db->delete('notifications', ['user_id' => $userId]);

        return JsonResponse::success(null, 'All notifications cleared');
    }

    public function getChannels(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $channels = $this->db->fetchAllAssociative(
            'SELECT * FROM notification_channels WHERE user_id = ? ORDER BY channel_type ASC',
            [$userId]
        );

        return JsonResponse::success($channels);
    }

    public function updateChannel(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $channelType = $args['type'];
        $data = (array) $request->getParsedBody();

        $allowed = ['ntfy', 'gotify', 'healthchecks', 'webhook', 'slack', 'telegram'];
        if (!in_array($channelType, $allowed, true)) {
            return JsonResponse::validationError(['type' => 'Ungültiger Kanaltyp']);
        }

        $existing = $this->db->fetchAssociative(
            'SELECT id FROM notification_channels WHERE user_id = ? AND channel_type = ?',
            [$userId, $channelType]
        );

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        if (array_key_exists('is_enabled', $data)) {
            $updateData['is_enabled'] = $data['is_enabled'] ? 1 : 0;
        }
        if (array_key_exists('config', $data)) {
            $updateData['config'] = is_array($data['config']) ? json_encode($data['config']) : $data['config'];
        }

        if ($existing) {
            $this->db->update('notification_channels', $updateData, ['id' => $existing['id']]);
        } else {
            $updateData['id'] = Uuid::uuid4()->toString();
            $updateData['user_id'] = $userId;
            $updateData['channel_type'] = $channelType;
            if (!isset($updateData['is_enabled'])) {
                $updateData['is_enabled'] = 0;
            }
            $this->db->insert('notification_channels', $updateData);
        }

        $channel = $this->db->fetchAssociative(
            'SELECT * FROM notification_channels WHERE user_id = ? AND channel_type = ?',
            [$userId, $channelType]
        );

        return JsonResponse::success($channel);
    }

    public function getPreferences(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $preferences = $this->db->fetchAssociative(
            'SELECT * FROM notification_preferences WHERE user_id = ?',
            [$userId]
        );

        if (!$preferences) {
            // Create default preferences
            $prefId = Uuid::uuid4()->toString();
            $this->db->insert('notification_preferences', [
                'id' => $prefId,
                'user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $preferences = $this->db->fetchAssociative(
                'SELECT * FROM notification_preferences WHERE id = ?',
                [$prefId]
            );
        }

        return JsonResponse::success($preferences);
    }

    public function updatePreferences(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        // Ensure preferences exist
        $preferences = $this->db->fetchAssociative(
            'SELECT * FROM notification_preferences WHERE user_id = ?',
            [$userId]
        );

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        $allowedFields = [
            'email_enabled', 'email_digest', 'push_enabled',
            'uptime_alerts', 'kanban_reminders', 'webhook_alerts', 'system_alerts'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = is_bool($data[$field]) ? ($data[$field] ? 1 : 0) : $data[$field];
            }
        }

        if ($preferences) {
            $this->db->update('notification_preferences', $updateData, ['user_id' => $userId]);
        } else {
            $updateData['id'] = Uuid::uuid4()->toString();
            $updateData['user_id'] = $userId;
            $updateData['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('notification_preferences', $updateData);
        }

        $preferences = $this->db->fetchAssociative(
            'SELECT * FROM notification_preferences WHERE user_id = ?',
            [$userId]
        );

        return JsonResponse::success($preferences, 'Preferences updated');
    }
}
