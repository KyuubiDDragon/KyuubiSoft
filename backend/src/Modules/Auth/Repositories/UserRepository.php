<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use Doctrine\DBAL\Connection;

class UserRepository
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function findById(string $id): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM users WHERE id = ?',
            [$id]
        );

        return $result ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM users WHERE email = ?',
            [$email]
        );

        return $result ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM users WHERE username = ?',
            [$username]
        );

        return $result ?: null;
    }

    public function findByEmailOrUsername(string $login): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM users WHERE email = ? OR username = ?',
            [$login, $login]
        );

        return $result ?: null;
    }

    public function create(array $data): array
    {
        $this->db->insert('users', $data);

        return $this->findById($data['id']);
    }

    public function update(string $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->db->update('users', $data, ['id' => $id]) > 0;
    }

    public function delete(string $id): bool
    {
        return $this->db->delete('users', ['id' => $id]) > 0;
    }

    public function storePasswordResetToken(string $userId, string $token, string $expiresAt): void
    {
        // Delete existing tokens for user
        $this->db->delete('password_resets', ['user_id' => $userId]);

        $this->db->insert('password_resets', [
            'user_id' => $userId,
            'token' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findPasswordResetToken(string $token): ?array
    {
        return $this->db->fetchAssociative(
            'SELECT * FROM password_resets WHERE token = ?',
            [hash('sha256', $token)]
        ) ?: null;
    }

    public function deletePasswordResetToken(string $token): void
    {
        $this->db->delete('password_resets', ['token' => hash('sha256', $token)]);
    }

    public function storeBackupCodes(string $userId, array $codes): void
    {
        // Delete existing codes
        $this->db->delete('two_factor_backup_codes', ['user_id' => $userId]);

        foreach ($codes as $code) {
            $this->db->insert('two_factor_backup_codes', [
                'user_id' => $userId,
                'code' => hash('sha256', $code),
                'used_at' => null,
            ]);
        }
    }

    public function deleteBackupCodes(string $userId): void
    {
        $this->db->delete('two_factor_backup_codes', ['user_id' => $userId]);
    }

    public function findAll(int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, email, username, avatar_url, is_active, is_verified, restricted_to_projects, last_login_at, created_at
             FROM users
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?',
            [$limit, $offset],
            [\PDO::PARAM_INT, \PDO::PARAM_INT]
        );
    }

    public function count(): int
    {
        return (int) $this->db->fetchOne('SELECT COUNT(*) FROM users');
    }
}
