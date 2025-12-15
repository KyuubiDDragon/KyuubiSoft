<?php

declare(strict_types=1);

namespace App\WebSocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Predis\Client as RedisClient;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * WebSocket server for real-time note collaboration
 */
class CollaborationServer implements MessageComponentInterface
{
    /** @var \SplObjectStorage<ConnectionInterface, array> */
    protected \SplObjectStorage $clients;

    /** @var array<string, array<string, ConnectionInterface>> Room ID => [Connection ID => Connection] */
    protected array $rooms = [];

    /** @var array<string, array> Connection ID => User data */
    protected array $users = [];

    /** @var array<string, array> Room ID => Document state */
    protected array $documentStates = [];

    protected RedisClient $redis;
    protected string $jwtSecret;

    public function __construct(RedisClient $redis, string $jwtSecret)
    {
        $this->clients = new \SplObjectStorage();
        $this->redis = $redis;
        $this->jwtSecret = $jwtSecret;

        echo "Collaboration WebSocket Server initialized\n";
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn, [
            'authenticated' => false,
            'user' => null,
            'rooms' => [],
        ]);

        $connId = $this->getConnectionId($conn);
        echo "New connection: {$connId}\n";

        // Send welcome message
        $conn->send(json_encode([
            'type' => 'connected',
            'connectionId' => $connId,
            'message' => 'Connected to collaboration server. Please authenticate.',
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $connId = $this->getConnectionId($from);

        try {
            $data = json_decode($msg, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->sendError($from, 'Invalid JSON message');
            return;
        }

        $type = $data['type'] ?? null;

        if (!$type) {
            $this->sendError($from, 'Missing message type');
            return;
        }

        // Handle authentication first
        if ($type === 'auth') {
            $this->handleAuth($from, $data);
            return;
        }

        // All other messages require authentication
        $clientData = $this->clients[$from];
        if (!$clientData['authenticated']) {
            $this->sendError($from, 'Not authenticated');
            return;
        }

        // Route message to appropriate handler
        match ($type) {
            'join' => $this->handleJoin($from, $data),
            'leave' => $this->handleLeave($from, $data),
            'update' => $this->handleUpdate($from, $data),
            'cursor' => $this->handleCursor($from, $data),
            'selection' => $this->handleSelection($from, $data),
            'awareness' => $this->handleAwareness($from, $data),
            'sync' => $this->handleSync($from, $data),
            'ping' => $this->handlePing($from),
            default => $this->sendError($from, "Unknown message type: {$type}"),
        };
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $connId = $this->getConnectionId($conn);
        $clientData = $this->clients[$conn] ?? null;

        if ($clientData) {
            // Leave all rooms
            foreach ($clientData['rooms'] as $roomId) {
                $this->leaveRoom($conn, $roomId);
            }

            // Clean up user data
            unset($this->users[$connId]);
        }

        $this->clients->detach($conn);
        echo "Connection closed: {$connId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $connId = $this->getConnectionId($conn);
        echo "Error on connection {$connId}: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Handle authentication
     */
    protected function handleAuth(ConnectionInterface $conn, array $data): void
    {
        $token = $data['token'] ?? null;

        if (!$token) {
            $this->sendError($conn, 'Missing authentication token');
            return;
        }

        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));

            $user = [
                'id' => $decoded->sub ?? $decoded->user_id,
                'name' => $decoded->name ?? 'Anonymous',
                'email' => $decoded->email ?? null,
                'color' => $this->generateUserColor($decoded->sub ?? $decoded->user_id),
            ];

            $connId = $this->getConnectionId($conn);
            $this->users[$connId] = $user;

            // Update client data
            $clientData = $this->clients[$conn];
            $clientData['authenticated'] = true;
            $clientData['user'] = $user;
            $this->clients[$conn] = $clientData;

            $conn->send(json_encode([
                'type' => 'authenticated',
                'user' => $user,
            ]));

            echo "User authenticated: {$user['name']} ({$connId})\n";
        } catch (\Exception $e) {
            $this->sendError($conn, 'Authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle joining a collaboration room (note)
     */
    protected function handleJoin(ConnectionInterface $conn, array $data): void
    {
        $roomId = $data['roomId'] ?? $data['noteId'] ?? null;

        if (!$roomId) {
            $this->sendError($conn, 'Missing roomId');
            return;
        }

        $connId = $this->getConnectionId($conn);
        $user = $this->users[$connId] ?? null;

        // Add to room
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [];
        }
        $this->rooms[$roomId][$connId] = $conn;

        // Track room in client data
        $clientData = $this->clients[$conn];
        $clientData['rooms'][] = $roomId;
        $this->clients[$conn] = $clientData;

        // Load document state from Redis if exists
        $stateKey = "collab:note:{$roomId}:state";
        $savedState = $this->redis->get($stateKey);

        if ($savedState) {
            $this->documentStates[$roomId] = json_decode($savedState, true);
        } elseif (!isset($this->documentStates[$roomId])) {
            $this->documentStates[$roomId] = [
                'version' => 0,
                'updates' => [],
            ];
        }

        // Get current participants
        $participants = $this->getRoomParticipants($roomId);

        // Notify user of successful join
        $conn->send(json_encode([
            'type' => 'joined',
            'roomId' => $roomId,
            'participants' => $participants,
            'state' => $this->documentStates[$roomId],
        ]));

        // Notify others in room
        $this->broadcastToRoom($roomId, [
            'type' => 'user_joined',
            'roomId' => $roomId,
            'user' => $user,
            'participants' => $participants,
        ], $conn);

        echo "User {$user['name']} joined room {$roomId}\n";
    }

    /**
     * Handle leaving a room
     */
    protected function handleLeave(ConnectionInterface $conn, array $data): void
    {
        $roomId = $data['roomId'] ?? null;

        if ($roomId) {
            $this->leaveRoom($conn, $roomId);
        }
    }

    /**
     * Leave a specific room
     */
    protected function leaveRoom(ConnectionInterface $conn, string $roomId): void
    {
        $connId = $this->getConnectionId($conn);
        $user = $this->users[$connId] ?? null;

        if (isset($this->rooms[$roomId][$connId])) {
            unset($this->rooms[$roomId][$connId]);

            // Remove room from client tracking
            $clientData = $this->clients[$conn];
            $clientData['rooms'] = array_filter(
                $clientData['rooms'],
                fn($r) => $r !== $roomId
            );
            $this->clients[$conn] = $clientData;

            // Notify others
            $participants = $this->getRoomParticipants($roomId);
            $this->broadcastToRoom($roomId, [
                'type' => 'user_left',
                'roomId' => $roomId,
                'user' => $user,
                'participants' => $participants,
            ]);

            // Clean up empty rooms
            if (empty($this->rooms[$roomId])) {
                // Save state to Redis before cleaning up
                if (isset($this->documentStates[$roomId])) {
                    $stateKey = "collab:note:{$roomId}:state";
                    $this->redis->setex(
                        $stateKey,
                        86400 * 7, // Keep for 7 days
                        json_encode($this->documentStates[$roomId])
                    );
                }

                unset($this->rooms[$roomId]);
                unset($this->documentStates[$roomId]);
            }

            echo "User left room {$roomId}\n";
        }
    }

    /**
     * Handle document update (Y.js update)
     */
    protected function handleUpdate(ConnectionInterface $from, array $data): void
    {
        $roomId = $data['roomId'] ?? null;
        $update = $data['update'] ?? null;
        $version = $data['version'] ?? null;

        if (!$roomId || !$update) {
            $this->sendError($from, 'Missing roomId or update');
            return;
        }

        $connId = $this->getConnectionId($from);
        $user = $this->users[$connId] ?? null;

        // Store update
        if (isset($this->documentStates[$roomId])) {
            $this->documentStates[$roomId]['version']++;
            $this->documentStates[$roomId]['updates'][] = [
                'data' => $update,
                'userId' => $user['id'] ?? null,
                'timestamp' => time(),
            ];

            // Keep only last 100 updates in memory
            if (count($this->documentStates[$roomId]['updates']) > 100) {
                array_shift($this->documentStates[$roomId]['updates']);
            }
        }

        // Broadcast to all other clients in the room
        $this->broadcastToRoom($roomId, [
            'type' => 'update',
            'roomId' => $roomId,
            'update' => $update,
            'version' => $this->documentStates[$roomId]['version'] ?? 0,
            'userId' => $user['id'] ?? null,
        ], $from);
    }

    /**
     * Handle cursor position update
     */
    protected function handleCursor(ConnectionInterface $from, array $data): void
    {
        $roomId = $data['roomId'] ?? null;
        $cursor = $data['cursor'] ?? null;

        if (!$roomId) {
            return;
        }

        $connId = $this->getConnectionId($from);
        $user = $this->users[$connId] ?? null;

        $this->broadcastToRoom($roomId, [
            'type' => 'cursor',
            'roomId' => $roomId,
            'userId' => $user['id'] ?? null,
            'user' => $user,
            'cursor' => $cursor,
        ], $from);
    }

    /**
     * Handle selection update
     */
    protected function handleSelection(ConnectionInterface $from, array $data): void
    {
        $roomId = $data['roomId'] ?? null;
        $selection = $data['selection'] ?? null;

        if (!$roomId) {
            return;
        }

        $connId = $this->getConnectionId($from);
        $user = $this->users[$connId] ?? null;

        $this->broadcastToRoom($roomId, [
            'type' => 'selection',
            'roomId' => $roomId,
            'userId' => $user['id'] ?? null,
            'user' => $user,
            'selection' => $selection,
        ], $from);
    }

    /**
     * Handle awareness update (general presence)
     */
    protected function handleAwareness(ConnectionInterface $from, array $data): void
    {
        $roomId = $data['roomId'] ?? null;
        $awareness = $data['awareness'] ?? null;

        if (!$roomId) {
            return;
        }

        $connId = $this->getConnectionId($from);
        $user = $this->users[$connId] ?? null;

        $this->broadcastToRoom($roomId, [
            'type' => 'awareness',
            'roomId' => $roomId,
            'userId' => $user['id'] ?? null,
            'user' => $user,
            'awareness' => $awareness,
        ], $from);
    }

    /**
     * Handle sync request
     */
    protected function handleSync(ConnectionInterface $conn, array $data): void
    {
        $roomId = $data['roomId'] ?? null;

        if (!$roomId || !isset($this->documentStates[$roomId])) {
            $this->sendError($conn, 'Room not found');
            return;
        }

        $conn->send(json_encode([
            'type' => 'sync_response',
            'roomId' => $roomId,
            'state' => $this->documentStates[$roomId],
        ]));
    }

    /**
     * Handle ping (keepalive)
     */
    protected function handlePing(ConnectionInterface $conn): void
    {
        $conn->send(json_encode(['type' => 'pong', 'timestamp' => time()]));
    }

    /**
     * Broadcast message to all clients in a room
     */
    protected function broadcastToRoom(string $roomId, array $message, ?ConnectionInterface $exclude = null): void
    {
        if (!isset($this->rooms[$roomId])) {
            return;
        }

        $json = json_encode($message);

        foreach ($this->rooms[$roomId] as $conn) {
            if ($exclude && $conn === $exclude) {
                continue;
            }
            $conn->send($json);
        }
    }

    /**
     * Get list of participants in a room
     */
    protected function getRoomParticipants(string $roomId): array
    {
        $participants = [];

        if (isset($this->rooms[$roomId])) {
            foreach ($this->rooms[$roomId] as $connId => $conn) {
                if (isset($this->users[$connId])) {
                    $participants[] = $this->users[$connId];
                }
            }
        }

        return $participants;
    }

    /**
     * Send error message to client
     */
    protected function sendError(ConnectionInterface $conn, string $message): void
    {
        $conn->send(json_encode([
            'type' => 'error',
            'message' => $message,
        ]));
    }

    /**
     * Get unique connection ID
     */
    protected function getConnectionId(ConnectionInterface $conn): string
    {
        return spl_object_hash($conn);
    }

    /**
     * Generate consistent color for user
     */
    protected function generateUserColor(string $userId): string
    {
        $colors = [
            '#EF4444', '#F97316', '#F59E0B', '#EAB308',
            '#84CC16', '#22C55E', '#10B981', '#14B8A6',
            '#06B6D4', '#0EA5E9', '#3B82F6', '#6366F1',
            '#8B5CF6', '#A855F7', '#D946EF', '#EC4899',
        ];

        $hash = crc32($userId);
        $index = abs($hash) % count($colors);

        return $colors[$index];
    }
}
