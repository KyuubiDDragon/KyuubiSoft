<?php

declare(strict_types=1);

namespace App\Modules\Chat\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Chat\Services\ChatService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

class ChatController
{
    public function __construct(
        private readonly ChatService $chatService
    ) {}

    /**
     * Get user's chat rooms
     */
    public function getRooms(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $rooms = $this->chatService->getRooms($userId);

        return JsonResponse::create($rooms);
    }

    /**
     * Get single room
     */
    public function getRoom(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $room = $this->chatService->getRoom($userId, $roomId);

        if (!$room) {
            return JsonResponse::notFound('Room not found or access denied');
        }

        return JsonResponse::create($room);
    }

    /**
     * Create a chat room
     */
    public function createRoom(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        $room = $this->chatService->createRoom($userId, $data);

        return JsonResponse::created($room, 'Room created');
    }

    /**
     * Start direct message
     */
    public function startDirectMessage(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['user_id'])) {
            return JsonResponse::error('User ID is required', 400);
        }

        $room = $this->chatService->getOrCreateDirectRoom($userId, $data['user_id']);

        return JsonResponse::create($room);
    }

    /**
     * Get messages for a room
     */
    public function getMessages(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $params = $request->getQueryParams();

        try {
            $messages = $this->chatService->getMessages(
                $userId,
                $roomId,
                (int) ($params['limit'] ?? 50),
                $params['before'] ?? null
            );

            return JsonResponse::create($messages);
        } catch (\RuntimeException $e) {
            return JsonResponse::forbidden($e->getMessage());
        }
    }

    /**
     * Send a message
     */
    public function sendMessage(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        if (empty($data['content'])) {
            return JsonResponse::error('Message content is required', 400);
        }

        try {
            $message = $this->chatService->sendMessage($userId, $roomId, $data);

            return JsonResponse::created($message, 'Message sent');
        } catch (\RuntimeException $e) {
            return JsonResponse::forbidden($e->getMessage());
        }
    }

    /**
     * Edit a message
     */
    public function editMessage(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $messageId = RouteContext::fromRequest($request)->getRoute()->getArgument('messageId');
        $data = $request->getParsedBody();

        if (empty($data['content'])) {
            return JsonResponse::error('Message content is required', 400);
        }

        $message = $this->chatService->editMessage($userId, $messageId, $data['content']);

        if (!$message) {
            return JsonResponse::notFound('Message not found or not authorized');
        }

        return JsonResponse::create($message);
    }

    /**
     * Delete a message
     */
    public function deleteMessage(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $messageId = RouteContext::fromRequest($request)->getRoute()->getArgument('messageId');

        $deleted = $this->chatService->deleteMessage($userId, $messageId);

        if (!$deleted) {
            return JsonResponse::notFound('Message not found or not authorized');
        }

        return JsonResponse::success(null, 'Message deleted');
    }

    /**
     * Add reaction
     */
    public function addReaction(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $messageId = RouteContext::fromRequest($request)->getRoute()->getArgument('messageId');
        $data = $request->getParsedBody();

        if (empty($data['emoji'])) {
            return JsonResponse::error('Emoji is required', 400);
        }

        $this->chatService->addReaction($userId, $messageId, $data['emoji']);

        return JsonResponse::success(null, 'Reaction added');
    }

    /**
     * Remove reaction
     */
    public function removeReaction(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $messageId = $route->getArgument('messageId');
        $emoji = $route->getArgument('emoji');

        $this->chatService->removeReaction($userId, $messageId, $emoji);

        return JsonResponse::success(null, 'Reaction removed');
    }

    /**
     * Mark room as read
     */
    public function markAsRead(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->chatService->markAsRead($userId, $roomId);

        return JsonResponse::success(null, 'Marked as read');
    }

    /**
     * Set typing indicator
     */
    public function setTyping(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->chatService->setTyping($userId, $roomId);

        return JsonResponse::success(null, 'Typing indicator set');
    }

    /**
     * Get typing users
     */
    public function getTyping(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $typing = $this->chatService->getTypingUsers($roomId, $userId);

        return JsonResponse::create($typing);
    }

    /**
     * Get available users for chat
     */
    public function getAvailableUsers(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $users = $this->chatService->getAvailableUsers($userId);

        return JsonResponse::create($users);
    }

    /**
     * Search messages
     */
    public function searchMessages(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        if (empty($params['q'])) {
            return JsonResponse::error('Search query is required', 400);
        }

        $messages = $this->chatService->searchMessages(
            $userId,
            $params['q'],
            $params['room_id'] ?? null
        );

        return JsonResponse::create($messages);
    }

    /**
     * Add participant to room
     */
    public function addParticipant(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        if (empty($data['user_id'])) {
            return JsonResponse::error('User ID is required', 400);
        }

        try {
            $this->chatService->addParticipant($roomId, $data['user_id'], $data['role'] ?? 'member');

            return JsonResponse::success(null, 'Participant added');
        } catch (\Exception $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Leave room
     */
    public function leaveRoom(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->chatService->removeParticipant($roomId, $userId);

        return JsonResponse::success(null, 'Left room');
    }
}
