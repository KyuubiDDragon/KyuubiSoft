<?php

declare(strict_types=1);

namespace App\Modules\Terminal\Controllers;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Modules\Auth\Services\AuthService;
use Doctrine\DBAL\Connection;
use Predis\Client as Redis;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class TerminalController
{
    private string $encryptionKey;
    private string $collaborationWsUrl;

    public function __construct(
        private readonly Connection $db,
        private readonly AuthService $authService,
        private readonly Redis $redis
    ) {
        $this->encryptionKey = $_ENV['APP_KEY'] ?? 'default-key-change-me';
        $this->collaborationWsUrl = $_ENV['COLLABORATION_WS_URL'] ?? 'ws://collaboration:1234';
    }

    /**
     * Create a terminal session.
     * Stores SSH credentials in Redis (TTL 30s) and returns a session ID
     * that the frontend uses to open a WebSocket to the collaboration server.
     */
    public function createSession(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['connection_id'])) {
            throw new ValidationException('connection_id is required');
        }

        $connectionId = $data['connection_id'];

        // Load connection and verify ownership
        $connection = $this->db->fetchAssociative(
            'SELECT * FROM connections WHERE id = ? AND user_id = ?',
            [$connectionId, $userId]
        );

        if (!$connection) {
            throw new NotFoundException('Connection not found');
        }

        if (!in_array($connection['type'], ['ssh', 'sftp'], true)) {
            throw new ValidationException('Connection type must be ssh or sftp');
        }

        // Decrypt credentials
        $password   = $connection['password_encrypted']    ? $this->decrypt($connection['password_encrypted'])    : null;
        $privateKey = $connection['private_key_encrypted'] ? $this->decrypt($connection['private_key_encrypted']) : null;

        // Create session
        $sessionId = Uuid::uuid4()->toString();
        $redisKey  = "terminal_session:{$sessionId}";

        $sessionData = [
            'user_id'     => $userId,
            'connection_id' => $connectionId,
            'host'        => $connection['host'],
            'port'        => (int) ($connection['port'] ?? 22),
            'username'    => $connection['username'],
            'password'    => $password,
            'private_key' => $privateKey,
        ];

        // Store in Redis with 60-second TTL (one-time use, consumed on first WS connect)
        $this->redis->setex($redisKey, 60, json_encode($sessionData));

        // Log in DB
        $this->db->insert('terminal_sessions', [
            'id'            => $sessionId,
            'user_id'       => (int) $userId,
            'connection_id' => $connectionId,
            'status'        => 'pending',
            'created_at'    => date('Y-m-d H:i:s'),
            'expires_at'    => date('Y-m-d H:i:s', time() + 60),
        ]);

        // Update connection last_used_at
        $this->db->update('connections', ['last_used_at' => date('Y-m-d H:i:s')], ['id' => $connectionId]);

        return JsonResponse::created([
            'session_id'  => $sessionId,
            'ws_url'      => $this->collaborationWsUrl . '/terminal/' . $sessionId,
            'connection'  => [
                'name'     => $connection['name'],
                'host'     => $connection['host'],
                'port'     => $connection['port'],
                'username' => $connection['username'],
            ],
        ], 'Terminal session created');
    }

    /**
     * Get status of an existing session.
     */
    public function getStatus(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId    = $request->getAttribute('user_id');
        $sessionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $session = $this->db->fetchAssociative(
            'SELECT id, status, created_at, expires_at FROM terminal_sessions WHERE id = ? AND user_id = ?',
            [$sessionId, $userId]
        );

        if (!$session) {
            throw new NotFoundException('Terminal session not found');
        }

        return JsonResponse::success($session);
    }

    private function decrypt(string $data): string
    {
        $data      = base64_decode($data);
        $iv        = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $result    = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        if ($result === false) {
            throw new \RuntimeException('Decryption failed: invalid data or wrong APP_KEY');
        }
        return $result;
    }
}
