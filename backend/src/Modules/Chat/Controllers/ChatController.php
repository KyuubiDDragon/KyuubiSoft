<?php

declare(strict_types=1);

namespace App\Modules\Chat\Controllers;

use App\Modules\Chat\Services\ChatService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

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

        $response->getBody()->write(json_encode($rooms));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get single room
     */
    public function getRoom(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = $args['id'];

        $room = $this->chatService->getRoom($userId, $roomId);

        if (!$room) {
            $response->getBody()->write(json_encode([
                'error' => 'Room not found or access denied'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($room));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create a chat room
     */
    public function createRoom(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        $room = $this->chatService->createRoom($userId, $data);

        $response->getBody()->write(json_encode($room));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    /**
     * Start direct message
     */
    public function startDirectMessage(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['user_id'])) {
            $response->getBody()->write(json_encode([
                'error' => 'User ID is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $room = $this->chatService->getOrCreateDirectRoom($userId, $data['user_id']);

        $response->getBody()->write(json_encode($room));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get messages for a room
     */
    public function getMessages(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = $args['id'];
        $params = $request->getQueryParams();

        try {
            $messages = $this->chatService->getMessages(
                $userId,
                $roomId,
                (int) ($params['limit'] ?? 50),
                $params['before'] ?? null
            );

            $response->getBody()->write(json_encode($messages));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Send a message
     */
    public function sendMessage(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = $args['id'];
        $data = $request->getParsedBody();

        if (empty($data['content'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Message content is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $message = $this->chatService->sendMessage($userId, $roomId, $data);

            $response->getBody()->write(json_encode($message));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Edit a message
     */
    public function editMessage(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $messageId = $args['messageId'];
        $data = $request->getParsedBody();

        if (empty($data['content'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Message content is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $message = $this->chatService->editMessage($userId, $messageId, $data['content']);

        if (!$message) {
            $response->getBody()->write(json_encode([
                'error' => 'Message not found or not authorized'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($message));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Delete a message
     */
    public function deleteMessage(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $messageId = $args['messageId'];

        $deleted = $this->chatService->deleteMessage($userId, $messageId);

        if (!$deleted) {
            $response->getBody()->write(json_encode([
                'error' => 'Message not found or not authorized'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Add reaction
     */
    public function addReaction(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $messageId = $args['messageId'];
        $data = $request->getParsedBody();

        if (empty($data['emoji'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Emoji is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $this->chatService->addReaction($userId, $messageId, $data['emoji']);

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Remove reaction
     */
    public function removeReaction(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $messageId = $args['messageId'];
        $emoji = $args['emoji'];

        $this->chatService->removeReaction($userId, $messageId, $emoji);

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Mark room as read
     */
    public function markAsRead(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = $args['id'];

        $this->chatService->markAsRead($userId, $roomId);

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Set typing indicator
     */
    public function setTyping(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = $args['id'];

        $this->chatService->setTyping($userId, $roomId);

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get typing users
     */
    public function getTyping(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = $args['id'];

        $typing = $this->chatService->getTypingUsers($roomId, $userId);

        $response->getBody()->write(json_encode($typing));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get available users for chat
     */
    public function getAvailableUsers(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $users = $this->chatService->getAvailableUsers($userId);

        $response->getBody()->write(json_encode($users));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Search messages
     */
    public function searchMessages(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        if (empty($params['q'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Search query is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $messages = $this->chatService->searchMessages(
            $userId,
            $params['q'],
            $params['room_id'] ?? null
        );

        $response->getBody()->write(json_encode($messages));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Add participant to room
     */
    public function addParticipant(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = $args['id'];
        $data = $request->getParsedBody();

        if (empty($data['user_id'])) {
            $response->getBody()->write(json_encode([
                'error' => 'User ID is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $this->chatService->addParticipant($roomId, $data['user_id'], $data['role'] ?? 'member');

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Leave room
     */
    public function leaveRoom(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $roomId = $args['id'];

        $this->chatService->removeParticipant($roomId, $userId);

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
