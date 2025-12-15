<?php

declare(strict_types=1);

namespace App\Modules\Notes\Controllers;

use App\Core\Controller;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ForbiddenException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Predis\Client as RedisClient;
use Doctrine\DBAL\Connection;

/**
 * Controller for collaboration-related HTTP endpoints
 */
class CollaborationController extends Controller
{
    public function __construct(
        private RedisClient $redis,
        private Connection $db
    ) {}

    /**
     * Verify user has access to note (owner or collaborator)
     */
    private function verifyNoteAccess(string $noteId, string $userId): void
    {
        // Check if user is owner
        $isOwner = $this->db->fetchOne(
            'SELECT 1 FROM notes WHERE id = ? AND user_id = ?',
            [$noteId, $userId]
        );

        if ($isOwner) {
            return;
        }

        // Check if user is a collaborator (via sharing)
        $isCollaborator = $this->db->fetchOne(
            'SELECT 1 FROM note_shares WHERE note_id = ? AND shared_with_user_id = ?',
            [$noteId, $userId]
        );

        if (!$isCollaborator) {
            throw new ForbiddenException('You do not have access to this note');
        }
    }

    /**
     * Check if user is owner of note
     */
    private function isNoteOwner(string $noteId, string $userId): bool
    {
        return (bool) $this->db->fetchOne(
            'SELECT 1 FROM notes WHERE id = ? AND user_id = ?',
            [$noteId, $userId]
        );
    }

    /**
     * Get collaboration status and WebSocket URL
     */
    public function status(Request $request, Response $response): Response
    {
        $wsHost = $_ENV['WEBSOCKET_HOST'] ?? 'localhost';
        $wsPort = $_ENV['WEBSOCKET_PORT'] ?? '8090';
        $wsProtocol = $_ENV['WEBSOCKET_PROTOCOL'] ?? 'ws';

        return $this->json($response, [
            'enabled' => true,
            'websocket_url' => "{$wsProtocol}://{$wsHost}:{$wsPort}",
            'features' => [
                'realtime_sync' => true,
                'cursor_awareness' => true,
                'presence' => true,
            ],
        ]);
    }

    /**
     * Get active collaborators for a note
     */
    public function collaborators(Request $request, Response $response, array $args): Response
    {
        $noteId = $args['noteId'] ?? null;
        $userId = $request->getAttribute('user_id');

        if (!$noteId) {
            return $this->json($response, ['error' => 'Note ID required'], 400);
        }

        // Verify user has access to this note
        $this->verifyNoteAccess($noteId, $userId);

        // Get active collaborators from Redis
        $key = "collab:note:{$noteId}:users";
        $users = $this->redis->smembers($key);

        $collaborators = [];
        foreach ($users as $userId) {
            $userKey = "collab:user:{$userId}";
            $userData = $this->redis->hgetall($userKey);
            if ($userData) {
                $collaborators[] = $userData;
            }
        }

        return $this->json($response, [
            'noteId' => $noteId,
            'collaborators' => $collaborators,
            'count' => count($collaborators),
        ]);
    }

    /**
     * Get collaboration history/activity for a note
     */
    public function history(Request $request, Response $response, array $args): Response
    {
        $noteId = $args['noteId'] ?? null;
        $userId = $request->getAttribute('user_id');

        if (!$noteId) {
            return $this->json($response, ['error' => 'Note ID required'], 400);
        }

        // Verify user has access to this note
        $this->verifyNoteAccess($noteId, $userId);

        // Get recent activity from Redis
        $key = "collab:note:{$noteId}:activity";
        $activity = $this->redis->lrange($key, 0, 49); // Last 50 activities

        $history = array_map(function ($item) {
            return json_decode($item, true);
        }, $activity);

        return $this->json($response, [
            'noteId' => $noteId,
            'history' => $history,
        ]);
    }

    /**
     * Clear collaboration state for a note (owner only)
     */
    public function clear(Request $request, Response $response, array $args): Response
    {
        $noteId = $args['noteId'] ?? null;
        $userId = $request->getAttribute('user_id');

        if (!$noteId) {
            return $this->json($response, ['error' => 'Note ID required'], 400);
        }

        // Only note owner can clear collaboration state
        if (!$this->isNoteOwner($noteId, $userId)) {
            throw new ForbiddenException('Only the note owner can clear collaboration state');
        }

        // Clear collaboration state from Redis
        $keys = [
            "collab:note:{$noteId}:state",
            "collab:note:{$noteId}:users",
            "collab:note:{$noteId}:activity",
        ];

        foreach ($keys as $key) {
            $this->redis->del($key);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Collaboration state cleared',
        ]);
    }
}
