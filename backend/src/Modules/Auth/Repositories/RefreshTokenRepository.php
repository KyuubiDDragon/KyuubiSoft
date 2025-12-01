<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use Doctrine\DBAL\Connection;

class RefreshTokenRepository
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function store(string $userId, string $tokenId, int $expiresAt, ?string $userAgent = null, ?string $ipAddress = null): void
    {
        $this->db->insert('refresh_tokens', [
            'id' => $tokenId,
            'user_id' => $userId,
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            'user_agent' => $userAgent,
            'ip_address' => $ipAddress,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function isValid(string $userId, string $tokenId): bool
    {
        $result = $this->db->fetchOne(
            'SELECT 1 FROM refresh_tokens
             WHERE id = ? AND user_id = ? AND revoked_at IS NULL AND expires_at > NOW()',
            [$tokenId, $userId]
        );

        return (bool) $result;
    }

    public function revoke(string $tokenId): void
    {
        $this->db->update('refresh_tokens', [
            'revoked_at' => date('Y-m-d H:i:s'),
        ], ['id' => $tokenId]);
    }

    public function revokeAllForUser(string $userId): void
    {
        $this->db->executeStatement(
            'UPDATE refresh_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL',
            [$userId]
        );
    }

    public function cleanupExpired(): int
    {
        return $this->db->executeStatement(
            'DELETE FROM refresh_tokens WHERE expires_at < NOW() OR revoked_at IS NOT NULL'
        );
    }

    public function getActiveTokensForUser(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, user_agent, ip_address, created_at
             FROM refresh_tokens
             WHERE user_id = ? AND revoked_at IS NULL AND expires_at > NOW()
             ORDER BY created_at DESC',
            [$userId]
        );
    }
}
