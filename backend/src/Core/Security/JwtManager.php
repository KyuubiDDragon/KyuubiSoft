<?php

declare(strict_types=1);

namespace App\Core\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Ramsey\Uuid\Uuid;
use stdClass;

class JwtManager
{
    public function __construct(
        private readonly string $secret,
        private readonly int $accessTtl,
        private readonly int $refreshTtl,
        private readonly string $issuer,
        private readonly string $algorithm
    ) {}

    /**
     * Generate an access token for a user
     */
    public function generateAccessToken(
        string $userId,
        string $email,
        array $roles = [],
        array $permissions = []
    ): string {
        $now = time();

        $payload = [
            'iss' => $this->issuer,
            'sub' => $userId,
            'email' => $email,
            'roles' => $roles,
            'permissions' => $permissions,
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
            'jti' => Uuid::uuid4()->toString(),
            'type' => 'access',
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Generate a refresh token for a user
     */
    public function generateRefreshToken(string $userId): array
    {
        $now = time();
        $tokenId = Uuid::uuid4()->toString();

        $payload = [
            'iss' => $this->issuer,
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $this->refreshTtl,
            'jti' => $tokenId,
            'type' => 'refresh',
        ];

        return [
            'token' => JWT::encode($payload, $this->secret, $this->algorithm),
            'token_id' => $tokenId,
            'expires_at' => $now + $this->refreshTtl,
        ];
    }

    /**
     * Validate an access token and return its payload
     */
    public function validateAccessToken(string $token): stdClass
    {
        $payload = JWT::decode($token, new Key($this->secret, $this->algorithm));

        if (($payload->type ?? '') !== 'access') {
            throw new \InvalidArgumentException('Invalid token type');
        }

        return $payload;
    }

    /**
     * Validate a refresh token and return its payload
     */
    public function validateRefreshToken(string $token): stdClass
    {
        $payload = JWT::decode($token, new Key($this->secret, $this->algorithm));

        if (($payload->type ?? '') !== 'refresh') {
            throw new \InvalidArgumentException('Invalid token type');
        }

        return $payload;
    }

    /**
     * Get access token TTL in seconds
     */
    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }

    /**
     * Get refresh token TTL in seconds
     */
    public function getRefreshTtl(): int
    {
        return $this->refreshTtl;
    }
}
