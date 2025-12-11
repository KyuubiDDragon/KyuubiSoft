<?php

declare(strict_types=1);

namespace App\Modules\Passwords\Services;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class PasswordService
{
    private string $encryptionKey;

    public function __construct(
        private readonly Connection $db
    ) {
        // Derive encryption key from environment
        $secret = $_ENV['APP_KEY'] ?? $_ENV['JWT_SECRET'] ?? throw new \RuntimeException('No encryption key configured');
        $this->encryptionKey = hash('sha256', $secret, true);
    }

    /**
     * Encrypt sensitive data using AES-256-GCM
     */
    private function encrypt(string $plaintext): array
    {
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return [
            'ciphertext' => base64_encode($ciphertext),
            'iv' => bin2hex($iv),
            'tag' => bin2hex($tag),
        ];
    }

    /**
     * Decrypt sensitive data
     */
    private function decrypt(string $ciphertext, string $iv, string $tag): string
    {
        $plaintext = openssl_decrypt(
            base64_decode($ciphertext),
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            hex2bin($iv),
            hex2bin($tag)
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $plaintext;
    }

    /**
     * Get all passwords for a user
     */
    public function getByUser(string $userId, ?string $categoryId = null, bool $includeArchived = false): array
    {
        $sql = 'SELECT p.*, c.name as category_name, c.icon as category_icon, c.color as category_color
                FROM passwords p
                LEFT JOIN password_categories c ON p.category_id = c.id
                WHERE p.user_id = ?';
        $params = [$userId];

        if ($categoryId) {
            $sql .= ' AND p.category_id = ?';
            $params[] = $categoryId;
        }

        if (!$includeArchived) {
            $sql .= ' AND p.is_archived = FALSE';
        }

        $sql .= ' ORDER BY p.is_favorite DESC, p.name ASC';

        $passwords = $this->db->fetchAllAssociative($sql, $params);

        // Don't decrypt passwords in list view - only return metadata
        return array_map(function ($pwd) {
            unset($pwd['password_encrypted'], $pwd['password_iv'], $pwd['password_tag']);
            unset($pwd['notes_encrypted'], $pwd['notes_iv'], $pwd['notes_tag']);
            unset($pwd['totp_secret_encrypted'], $pwd['totp_iv'], $pwd['totp_tag']);
            return $pwd;
        }, $passwords);
    }

    /**
     * Get single password with decrypted data
     */
    public function getById(string $id, string $userId): ?array
    {
        $password = $this->db->fetchAssociative(
            'SELECT p.*, c.name as category_name
             FROM passwords p
             LEFT JOIN password_categories c ON p.category_id = c.id
             WHERE p.id = ? AND (p.user_id = ? OR EXISTS (
                SELECT 1 FROM password_shares ps WHERE ps.password_id = p.id AND ps.shared_with = ?
             ))',
            [$id, $userId, $userId]
        );

        if (!$password) {
            return null;
        }

        // Decrypt password
        $password['password'] = $this->decrypt(
            $password['password_encrypted'],
            $password['password_iv'],
            $password['password_tag']
        );

        // Decrypt notes if present
        if ($password['notes_encrypted']) {
            $password['notes'] = $this->decrypt(
                $password['notes_encrypted'],
                $password['notes_iv'],
                $password['notes_tag']
            );
        }

        // Decrypt TOTP if present
        if ($password['totp_secret_encrypted']) {
            $password['totp_secret'] = $this->decrypt(
                $password['totp_secret_encrypted'],
                $password['totp_iv'],
                $password['totp_tag']
            );
        }

        // Update last used
        $this->db->update('passwords', ['last_used_at' => date('Y-m-d H:i:s')], ['id' => $id]);

        // Clean up encrypted fields
        unset($password['password_encrypted'], $password['password_iv'], $password['password_tag']);
        unset($password['notes_encrypted'], $password['notes_iv'], $password['notes_tag']);
        unset($password['totp_secret_encrypted'], $password['totp_iv'], $password['totp_tag']);

        return $password;
    }

    /**
     * Create a new password entry
     */
    public function create(string $userId, array $data): array
    {
        $id = Uuid::uuid4()->toString();

        // Encrypt password
        $encrypted = $this->encrypt($data['password']);

        $insertData = [
            'id' => $id,
            'user_id' => $userId,
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'password_encrypted' => $encrypted['ciphertext'],
            'password_iv' => $encrypted['iv'],
            'password_tag' => $encrypted['tag'],
            'url' => $data['url'] ?? null,
            'favicon_url' => $data['favicon_url'] ?? null,
            'password_changed_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Encrypt notes if present
        if (!empty($data['notes'])) {
            $notesEncrypted = $this->encrypt($data['notes']);
            $insertData['notes_encrypted'] = $notesEncrypted['ciphertext'];
            $insertData['notes_iv'] = $notesEncrypted['iv'];
            $insertData['notes_tag'] = $notesEncrypted['tag'];
        }

        // Encrypt TOTP if present
        if (!empty($data['totp_secret'])) {
            $totpEncrypted = $this->encrypt($data['totp_secret']);
            $insertData['totp_secret_encrypted'] = $totpEncrypted['ciphertext'];
            $insertData['totp_iv'] = $totpEncrypted['iv'];
            $insertData['totp_tag'] = $totpEncrypted['tag'];
        }

        $this->db->insert('passwords', $insertData);

        // Add to history
        $this->addToHistory($id, $data['password']);

        return $this->getById($id, $userId);
    }

    /**
     * Update a password entry
     */
    public function update(string $id, string $userId, array $data): ?array
    {
        // Check ownership
        $password = $this->db->fetchAssociative(
            'SELECT id FROM passwords WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$password) {
            return null;
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['username'])) {
            $updateData['username'] = $data['username'];
        }
        if (isset($data['url'])) {
            $updateData['url'] = $data['url'];
        }
        if (isset($data['category_id'])) {
            $updateData['category_id'] = $data['category_id'] ?: null;
        }
        if (isset($data['favicon_url'])) {
            $updateData['favicon_url'] = $data['favicon_url'];
        }
        if (isset($data['is_favorite'])) {
            $updateData['is_favorite'] = $data['is_favorite'];
        }
        if (isset($data['is_archived'])) {
            $updateData['is_archived'] = $data['is_archived'];
        }

        // Update password if changed
        if (!empty($data['password'])) {
            $encrypted = $this->encrypt($data['password']);
            $updateData['password_encrypted'] = $encrypted['ciphertext'];
            $updateData['password_iv'] = $encrypted['iv'];
            $updateData['password_tag'] = $encrypted['tag'];
            $updateData['password_changed_at'] = date('Y-m-d H:i:s');

            // Add to history
            $this->addToHistory($id, $data['password']);
        }

        // Update notes
        if (isset($data['notes'])) {
            if (!empty($data['notes'])) {
                $notesEncrypted = $this->encrypt($data['notes']);
                $updateData['notes_encrypted'] = $notesEncrypted['ciphertext'];
                $updateData['notes_iv'] = $notesEncrypted['iv'];
                $updateData['notes_tag'] = $notesEncrypted['tag'];
            } else {
                $updateData['notes_encrypted'] = null;
                $updateData['notes_iv'] = null;
                $updateData['notes_tag'] = null;
            }
        }

        // Update TOTP
        if (isset($data['totp_secret'])) {
            if (!empty($data['totp_secret'])) {
                $totpEncrypted = $this->encrypt($data['totp_secret']);
                $updateData['totp_secret_encrypted'] = $totpEncrypted['ciphertext'];
                $updateData['totp_iv'] = $totpEncrypted['iv'];
                $updateData['totp_tag'] = $totpEncrypted['tag'];
            } else {
                $updateData['totp_secret_encrypted'] = null;
                $updateData['totp_iv'] = null;
                $updateData['totp_tag'] = null;
            }
        }

        if (!empty($updateData)) {
            $this->db->update('passwords', $updateData, ['id' => $id]);
        }

        return $this->getById($id, $userId);
    }

    /**
     * Delete a password
     */
    public function delete(string $id, string $userId): bool
    {
        $affected = $this->db->delete('passwords', [
            'id' => $id,
            'user_id' => $userId,
        ]);

        return $affected > 0;
    }

    /**
     * Add password to history for breach detection
     */
    private function addToHistory(string $passwordId, string $plainPassword): void
    {
        $hash = hash('sha256', $plainPassword);

        $this->db->insert('password_history', [
            'id' => Uuid::uuid4()->toString(),
            'password_id' => $passwordId,
            'password_hash' => $hash,
            'changed_at' => date('Y-m-d H:i:s'),
        ]);

        // Keep only last 10 history entries
        $this->db->executeStatement(
            'DELETE FROM password_history
             WHERE password_id = ?
             AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM password_history WHERE password_id = ? ORDER BY changed_at DESC LIMIT 10
                ) t
             )',
            [$passwordId, $passwordId]
        );
    }

    /**
     * Generate TOTP code
     */
    public function generateTOTP(string $secret): string
    {
        $time = floor(time() / 30);
        $secret = $this->base32Decode($secret);

        $hash = hash_hmac('sha1', pack('J', $time), $secret, true);
        $offset = ord(substr($hash, -1)) & 0x0f;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        foreach (str_split(strtoupper($input)) as $char) {
            if ($char === '=') break;
            $value = strpos($alphabet, $char);
            if ($value === false) continue;

            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xff);
            }
        }

        return $output;
    }

    // Category methods

    public function getCategories(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT c.*, COUNT(p.id) as password_count
             FROM password_categories c
             LEFT JOIN passwords p ON p.category_id = c.id AND p.is_archived = FALSE
             WHERE c.user_id = ?
             GROUP BY c.id
             ORDER BY c.position ASC, c.name ASC',
            [$userId]
        );
    }

    public function createCategory(string $userId, array $data): array
    {
        $id = Uuid::uuid4()->toString();

        $maxPosition = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), 0) FROM password_categories WHERE user_id = ?',
            [$userId]
        );

        $this->db->insert('password_categories', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'icon' => $data['icon'] ?? 'folder',
            'color' => $data['color'] ?? '#6366f1',
            'position' => $maxPosition + 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->fetchAssociative(
            'SELECT * FROM password_categories WHERE id = ?',
            [$id]
        );
    }

    public function updateCategory(string $id, string $userId, array $data): ?array
    {
        $category = $this->db->fetchAssociative(
            'SELECT id FROM password_categories WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$category) {
            return null;
        }

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['icon'])) $updateData['icon'] = $data['icon'];
        if (isset($data['color'])) $updateData['color'] = $data['color'];

        if (!empty($updateData)) {
            $this->db->update('password_categories', $updateData, ['id' => $id]);
        }

        return $this->db->fetchAssociative(
            'SELECT * FROM password_categories WHERE id = ?',
            [$id]
        );
    }

    public function deleteCategory(string $id, string $userId): bool
    {
        // Move passwords to uncategorized
        $this->db->update('passwords', ['category_id' => null], ['category_id' => $id]);

        $affected = $this->db->delete('password_categories', [
            'id' => $id,
            'user_id' => $userId,
        ]);

        return $affected > 0;
    }

    /**
     * Search passwords
     */
    public function search(string $userId, string $query): array
    {
        $passwords = $this->db->fetchAllAssociative(
            'SELECT p.*, c.name as category_name
             FROM passwords p
             LEFT JOIN password_categories c ON p.category_id = c.id
             WHERE p.user_id = ? AND p.is_archived = FALSE
             AND (p.name LIKE ? OR p.username LIKE ? OR p.url LIKE ?)
             ORDER BY p.name ASC
             LIMIT 50',
            [$userId, "%$query%", "%$query%", "%$query%"]
        );

        return array_map(function ($pwd) {
            unset($pwd['password_encrypted'], $pwd['password_iv'], $pwd['password_tag']);
            unset($pwd['notes_encrypted'], $pwd['notes_iv'], $pwd['notes_tag']);
            unset($pwd['totp_secret_encrypted'], $pwd['totp_iv'], $pwd['totp_tag']);
            return $pwd;
        }, $passwords);
    }

    /**
     * Generate secure password
     */
    public static function generatePassword(int $length = 16, bool $uppercase = true, bool $lowercase = true, bool $numbers = true, bool $symbols = true): string
    {
        $chars = '';
        if ($uppercase) $chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($lowercase) $chars .= 'abcdefghijklmnopqrstuvwxyz';
        if ($numbers) $chars .= '0123456789';
        if ($symbols) $chars .= '!@#$%^&*()_+-=[]{}|;:,.<>?';

        if (empty($chars)) {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        }

        $password = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }

        return $password;
    }
}
