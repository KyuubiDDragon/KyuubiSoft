<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Notifications\Services\PushNotificationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PushNotificationController
{
    public function __construct(
        private readonly PushNotificationService $pushService
    ) {}

    /**
     * Get VAPID public key for client registration
     */
    public function getVapidKey(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $keys = $this->pushService->getVapidKeys();

        return JsonResponse::success([
            'publicKey' => $keys['publicKey'],
        ]);
    }

    /**
     * Subscribe to push notifications
     */
    public function subscribe(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['subscription'])) {
            return JsonResponse::error('Subscription data is required', 400);
        }

        $subscription = $data['subscription'];

        if (empty($subscription['endpoint'])) {
            return JsonResponse::error('Endpoint is required', 400);
        }

        $deviceName = $data['device_name'] ?? null;

        try {
            $subscriptionId = $this->pushService->saveSubscription($userId, $subscription, $deviceName);

            return JsonResponse::success([
                'subscription_id' => $subscriptionId,
                'message' => 'Successfully subscribed to push notifications',
            ], 201);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to save subscription: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Unsubscribe from push notifications
     */
    public function unsubscribe(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['endpoint'])) {
            return JsonResponse::error('Endpoint is required', 400);
        }

        $success = $this->pushService->removeSubscription($userId, $data['endpoint']);

        if (!$success) {
            return JsonResponse::error('Subscription not found', 404);
        }

        return JsonResponse::success(['message' => 'Successfully unsubscribed']);
    }

    /**
     * Get user's active subscriptions
     */
    public function getSubscriptions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $subscriptions = $this->pushService->getUserSubscriptions($userId);

        return JsonResponse::success($subscriptions);
    }

    /**
     * Get notification preferences
     */
    public function getPreferences(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $preferences = $this->pushService->getPreferences($userId);

        return JsonResponse::success($preferences);
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        try {
            $this->pushService->updatePreferences($userId, $data);

            return JsonResponse::success(['message' => 'Preferences updated']);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to update preferences: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get notification history
     */
    public function getHistory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));
        $offset = max(0, (int) ($params['offset'] ?? 0));

        $history = $this->pushService->getNotificationHistory($userId, $limit, $offset);

        return JsonResponse::success($history);
    }

    /**
     * Mark notification as clicked
     */
    public function markClicked(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->pushService->markClicked($args['id']);

        return JsonResponse::success(['message' => 'Notification marked as clicked']);
    }

    /**
     * Send test notification (for testing setup)
     */
    public function sendTest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $result = $this->pushService->sendToUser(
            $userId,
            'Test Notification',
            'Push notifications are working correctly!',
            'system',
            null,
            '/'
        );

        if ($result['sent'] > 0) {
            return JsonResponse::success([
                'message' => 'Test notification sent',
                'sent' => $result['sent'],
            ]);
        }

        if (isset($result['skipped'])) {
            return JsonResponse::error('Notification skipped: ' . $result['skipped'], 400);
        }

        return JsonResponse::error('Failed to send test notification', 500);
    }
}
