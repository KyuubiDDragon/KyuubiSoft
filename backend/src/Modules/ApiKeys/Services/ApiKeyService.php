<?php

declare(strict_types=1);

namespace App\Modules\ApiKeys\Services;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class ApiKeyService
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Generate a new API key
     * Returns the key only once - it cannot be retrieved later
     */
    public function generate(string $userId, string $name, array $scopes, ?string $expiresAt = null): array
    {
        // Format: ks_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX (32 hex chars after prefix)
        $key = 'ks_' . bin2hex(random_bytes(16));
        $prefix = substr($key, 0, 11); // "ks_XXXXXXXX"
        $keyHash = hash('sha256', $key);

        $id = Uuid::uuid4()->toString();

        $this->db->insert('api_keys', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $name,
            'key_hash' => $keyHash,
            'key_prefix' => $prefix,
            'scopes' => json_encode($scopes),
            'expires_at' => $expiresAt,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'id' => $id,
            'key' => $key, // Only returned once!
            'prefix' => $prefix,
            'name' => $name,
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Validate an API key and return user info
     */
    public function validate(string $apiKey): ?array
    {
        $keyHash = hash('sha256', $apiKey);

        $keyData = $this->db->fetchAssociative(
            'SELECT ak.*, u.id as uid, u.email, u.username
             FROM api_keys ak
             JOIN users u ON ak.user_id = u.id
             WHERE ak.key_hash = ? AND ak.is_active = TRUE
             AND (ak.expires_at IS NULL OR ak.expires_at > NOW())',
            [$keyHash]
        );

        if (!$keyData) {
            return null;
        }

        // Update last used
        $this->db->update('api_keys', [
            'last_used_at' => date('Y-m-d H:i:s'),
        ], ['id' => $keyData['id']]);

        return [
            'key_id' => $keyData['id'],
            'user_id' => $keyData['user_id'],
            'email' => $keyData['email'],
            'username' => $keyData['username'],
            'scopes' => json_decode($keyData['scopes'], true),
        ];
    }

    /**
     * Update last used IP
     */
    public function updateLastUsedIp(string $keyId, ?string $ip): void
    {
        $this->db->update('api_keys', [
            'last_used_ip' => $ip,
        ], ['id' => $keyId]);
    }

    /**
     * Get all API keys for a user (without the actual key)
     */
    public function getByUser(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, name, key_prefix, scopes, last_used_at, last_used_ip, expires_at, is_active, created_at
             FROM api_keys
             WHERE user_id = ?
             ORDER BY created_at DESC',
            [$userId]
        );
    }

    /**
     * Revoke an API key
     */
    public function revoke(string $keyId, string $userId): bool
    {
        $affected = $this->db->update(
            'api_keys',
            ['is_active' => false],
            ['id' => $keyId, 'user_id' => $userId]
        );

        return $affected > 0;
    }

    /**
     * Delete an API key
     */
    public function delete(string $keyId, string $userId): bool
    {
        $affected = $this->db->delete('api_keys', [
            'id' => $keyId,
            'user_id' => $userId,
        ]);

        return $affected > 0;
    }

    /**
     * Update API key name and scopes
     */
    public function update(string $keyId, string $userId, array $data): ?array
    {
        $key = $this->db->fetchAssociative(
            'SELECT * FROM api_keys WHERE id = ? AND user_id = ?',
            [$keyId, $userId]
        );

        if (!$key) {
            return null;
        }

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['scopes'])) {
            $updateData['scopes'] = json_encode($data['scopes']);
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }

        if (!empty($updateData)) {
            $this->db->update('api_keys', $updateData, ['id' => $keyId]);
        }

        return $this->db->fetchAssociative(
            'SELECT id, name, key_prefix, scopes, last_used_at, last_used_ip, expires_at, is_active, created_at
             FROM api_keys WHERE id = ?',
            [$keyId]
        );
    }

    /**
     * Check if a scope is allowed
     */
    public function hasScope(array $keyScopes, string $requiredScope): bool
    {
        // Direct match
        if (in_array($requiredScope, $keyScopes, true)) {
            return true;
        }

        // Wildcard match (e.g., "lists.*" grants "lists.read")
        $parts = explode('.', $requiredScope);
        if (count($parts) >= 2) {
            $wildcard = $parts[0] . '.*';
            if (in_array($wildcard, $keyScopes, true)) {
                return true;
            }
        }

        // Full access
        if (in_array('*', $keyScopes, true)) {
            return true;
        }

        return false;
    }

    /**
     * Get available scopes
     */
    public static function getAvailableScopes(): array
    {
        return [
            'lists.read' => 'Listen lesen',
            'lists.write' => 'Listen erstellen/bearbeiten',
            'documents.read' => 'Dokumente lesen',
            'documents.write' => 'Dokumente erstellen/bearbeiten',
            'kanban.read' => 'Kanban-Boards lesen',
            'kanban.write' => 'Kanban-Boards bearbeiten',
            'snippets.read' => 'Snippets lesen',
            'snippets.write' => 'Snippets erstellen/bearbeiten',
            'bookmarks.read' => 'Bookmarks lesen',
            'bookmarks.write' => 'Bookmarks erstellen/bearbeiten',
            'time.read' => 'Zeiteinträge lesen',
            'time.write' => 'Zeiteinträge erstellen',
            'projects.read' => 'Projekte lesen',
            'projects.write' => 'Projekte bearbeiten',
            'uptime.read' => 'Uptime-Daten lesen',
            'storage.read' => 'Cloud Storage lesen',
            'storage.write' => 'Cloud Storage hochladen',
            'checklists.read' => 'Checklisten lesen',
            'checklists.write' => 'Checklisten bearbeiten',
        ];
    }
}
