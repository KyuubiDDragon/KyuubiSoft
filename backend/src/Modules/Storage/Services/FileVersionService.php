<?php

declare(strict_types=1);

namespace App\Modules\Storage\Services;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class FileVersionService
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Create a new version of a file
     */
    public function createVersion(
        string $userId,
        string $fileId,
        string $newPath,
        string $storedName,
        int $size,
        ?string $mimeType = null,
        ?string $changeNote = null
    ): array {
        // Get current file
        $file = $this->db->fetchAssociative(
            'SELECT * FROM storage_files WHERE id = ? AND user_id = ?',
            [$fileId, $userId]
        );

        if (!$file) {
            throw new \InvalidArgumentException('File not found');
        }

        // Get current version number
        $currentVersion = (int) ($file['current_version'] ?? 1);
        $newVersion = $currentVersion + 1;

        // Calculate hash for deduplication
        $hash = null;
        if (file_exists($newPath)) {
            $hash = hash_file('sha256', $newPath);
        }

        // Check if same hash already exists (skip creating duplicate version)
        $existingVersion = $this->db->fetchAssociative(
            'SELECT * FROM file_versions WHERE file_id = ? AND hash = ?',
            [$fileId, $hash]
        );

        if ($existingVersion) {
            // Same content, just return existing version
            return $existingVersion;
        }

        // Get user's version settings
        $settings = $this->getUserSettings($userId);
        $maxVersions = $settings['max_versions_per_file'] ?? 10;

        // Mark current version as not current
        $this->db->executeStatement(
            'UPDATE file_versions SET is_current = FALSE WHERE file_id = ?',
            [$fileId]
        );

        // Create new version entry
        $versionId = Uuid::uuid4()->toString();
        $this->db->insert('file_versions', [
            'id' => $versionId,
            'file_id' => $fileId,
            'version_number' => $newVersion,
            'original_name' => $file['original_name'],
            'stored_name' => $storedName,
            'path' => $newPath,
            'size' => $size,
            'mime_type' => $mimeType ?? $file['mime_type'],
            'hash' => $hash,
            'created_by' => $userId,
            'change_note' => $changeNote,
            'is_current' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Update main file
        $this->db->update('storage_files', [
            'current_version' => $newVersion,
            'is_versioned' => true,
            'size' => $size,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $fileId]);

        // Cleanup old versions if needed
        if ($maxVersions > 0) {
            $this->cleanupOldVersions($fileId, $maxVersions);
        }

        return $this->db->fetchAssociative(
            'SELECT * FROM file_versions WHERE id = ?',
            [$versionId]
        );
    }

    /**
     * Get all versions of a file
     */
    public function getVersions(string $userId, string $fileId): array
    {
        // Verify file ownership
        $file = $this->db->fetchAssociative(
            'SELECT id FROM storage_files WHERE id = ? AND user_id = ?',
            [$fileId, $userId]
        );

        if (!$file) {
            throw new \InvalidArgumentException('File not found');
        }

        return $this->db->fetchAllAssociative(
            'SELECT fv.*, u.username as created_by_name
             FROM file_versions fv
             LEFT JOIN users u ON fv.created_by = u.id
             WHERE fv.file_id = ?
             ORDER BY fv.version_number DESC',
            [$fileId]
        );
    }

    /**
     * Get a specific version
     */
    public function getVersion(string $userId, string $versionId): ?array
    {
        return $this->db->fetchAssociative(
            'SELECT fv.* FROM file_versions fv
             JOIN storage_files sf ON fv.file_id = sf.id
             WHERE fv.id = ? AND sf.user_id = ?',
            [$versionId, $userId]
        );
    }

    /**
     * Restore a specific version
     */
    public function restoreVersion(string $userId, string $versionId): array
    {
        $version = $this->getVersion($userId, $versionId);

        if (!$version) {
            throw new \InvalidArgumentException('Version not found');
        }

        $fileId = $version['file_id'];

        // Get current file info
        $file = $this->db->fetchAssociative(
            'SELECT * FROM storage_files WHERE id = ?',
            [$fileId]
        );

        // Create a backup of current version before restoring
        $currentVersion = $this->db->fetchAssociative(
            'SELECT * FROM file_versions WHERE file_id = ? AND is_current = TRUE',
            [$fileId]
        );

        if ($currentVersion) {
            // Already have versions tracked
            $newVersion = (int) $file['current_version'] + 1;
        } else {
            $newVersion = 2;
        }

        // Mark all as not current
        $this->db->executeStatement(
            'UPDATE file_versions SET is_current = FALSE WHERE file_id = ?',
            [$fileId]
        );

        // Create new version entry for the restore
        $newVersionId = Uuid::uuid4()->toString();
        $this->db->insert('file_versions', [
            'id' => $newVersionId,
            'file_id' => $fileId,
            'version_number' => $newVersion,
            'original_name' => $version['original_name'],
            'stored_name' => $version['stored_name'],
            'path' => $version['path'],
            'size' => $version['size'],
            'mime_type' => $version['mime_type'],
            'hash' => $version['hash'],
            'created_by' => $userId,
            'change_note' => "Wiederhergestellt von Version {$version['version_number']}",
            'is_current' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Update main file to point to restored version
        $this->db->update('storage_files', [
            'path' => $version['path'],
            'stored_name' => $version['stored_name'],
            'size' => $version['size'],
            'current_version' => $newVersion,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $fileId]);

        return $this->db->fetchAssociative(
            'SELECT * FROM storage_files WHERE id = ?',
            [$fileId]
        );
    }

    /**
     * Delete a specific version
     */
    public function deleteVersion(string $userId, string $versionId): bool
    {
        $version = $this->getVersion($userId, $versionId);

        if (!$version) {
            return false;
        }

        // Don't allow deleting current version
        if ($version['is_current']) {
            throw new \InvalidArgumentException('Cannot delete current version');
        }

        // Delete the file if it's unique to this version
        $otherVersionsWithSameFile = $this->db->fetchOne(
            'SELECT COUNT(*) FROM file_versions WHERE hash = ? AND id != ?',
            [$version['hash'], $versionId]
        );

        if ($otherVersionsWithSameFile == 0 && file_exists($version['path'])) {
            unlink($version['path']);
        }

        $this->db->delete('file_versions', ['id' => $versionId]);

        return true;
    }

    /**
     * Compare two versions
     */
    public function compareVersions(string $userId, string $versionId1, string $versionId2): array
    {
        $v1 = $this->getVersion($userId, $versionId1);
        $v2 = $this->getVersion($userId, $versionId2);

        if (!$v1 || !$v2) {
            throw new \InvalidArgumentException('Version not found');
        }

        return [
            'version_1' => $v1,
            'version_2' => $v2,
            'differences' => [
                'size_diff' => $v2['size'] - $v1['size'],
                'same_content' => $v1['hash'] === $v2['hash'],
                'time_diff_seconds' => strtotime($v2['created_at']) - strtotime($v1['created_at']),
            ],
        ];
    }

    /**
     * Get user's version settings
     */
    public function getUserSettings(string $userId): array
    {
        $settings = $this->db->fetchAssociative(
            'SELECT * FROM file_version_settings WHERE user_id = ?',
            [$userId]
        );

        if (!$settings) {
            // Return defaults
            return [
                'auto_version' => true,
                'max_versions_per_file' => 10,
                'keep_days' => 90,
            ];
        }

        return $settings;
    }

    /**
     * Update user's version settings
     */
    public function updateUserSettings(string $userId, array $data): array
    {
        $settings = $this->db->fetchAssociative(
            'SELECT id FROM file_version_settings WHERE user_id = ?',
            [$userId]
        );

        $updateData = [];
        if (isset($data['auto_version'])) {
            $updateData['auto_version'] = (bool) $data['auto_version'];
        }
        if (isset($data['max_versions_per_file'])) {
            $updateData['max_versions_per_file'] = max(0, min(100, (int) $data['max_versions_per_file']));
        }
        if (isset($data['keep_days'])) {
            $updateData['keep_days'] = max(0, (int) $data['keep_days']);
        }

        if ($settings) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('file_version_settings', $updateData, ['user_id' => $userId]);
        } else {
            $updateData['id'] = Uuid::uuid4()->toString();
            $updateData['user_id'] = $userId;
            $this->db->insert('file_version_settings', $updateData);
        }

        return $this->getUserSettings($userId);
    }

    /**
     * Cleanup old versions beyond the limit
     */
    private function cleanupOldVersions(string $fileId, int $maxVersions): void
    {
        // Get versions to delete (oldest first, excluding current)
        $versions = $this->db->fetchAllAssociative(
            'SELECT id, path, hash FROM file_versions
             WHERE file_id = ? AND is_current = FALSE
             ORDER BY version_number ASC',
            [$fileId]
        );

        $totalVersions = count($versions) + 1; // +1 for current

        if ($totalVersions <= $maxVersions) {
            return;
        }

        $toDelete = $totalVersions - $maxVersions;

        for ($i = 0; $i < $toDelete && $i < count($versions); $i++) {
            $version = $versions[$i];

            // Check if file is used by other versions
            $otherUses = $this->db->fetchOne(
                'SELECT COUNT(*) FROM file_versions WHERE hash = ? AND id != ?',
                [$version['hash'], $version['id']]
            );

            if ($otherUses == 0 && file_exists($version['path'])) {
                unlink($version['path']);
            }

            $this->db->delete('file_versions', ['id' => $version['id']]);
        }
    }

    /**
     * Cleanup versions older than keep_days setting
     */
    public function cleanupExpiredVersions(string $userId): int
    {
        $settings = $this->getUserSettings($userId);
        $keepDays = $settings['keep_days'] ?? 90;

        if ($keepDays <= 0) {
            return 0; // Unlimited retention
        }

        $expiredVersions = $this->db->fetchAllAssociative(
            'SELECT fv.id, fv.path, fv.hash FROM file_versions fv
             JOIN storage_files sf ON fv.file_id = sf.id
             WHERE sf.user_id = ? AND fv.is_current = FALSE
               AND fv.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$userId, $keepDays]
        );

        $deleted = 0;
        foreach ($expiredVersions as $version) {
            // Check if file is used by other versions
            $otherUses = $this->db->fetchOne(
                'SELECT COUNT(*) FROM file_versions WHERE hash = ? AND id != ?',
                [$version['hash'], $version['id']]
            );

            if ($otherUses == 0 && file_exists($version['path'])) {
                unlink($version['path']);
            }

            $this->db->delete('file_versions', ['id' => $version['id']]);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Get version statistics for a user
     */
    public function getVersionStats(string $userId): array
    {
        $stats = $this->db->fetchAssociative(
            'SELECT
                COUNT(fv.id) as total_versions,
                SUM(fv.size) as total_size,
                COUNT(DISTINCT fv.file_id) as versioned_files
             FROM file_versions fv
             JOIN storage_files sf ON fv.file_id = sf.id
             WHERE sf.user_id = ?',
            [$userId]
        );

        return [
            'total_versions' => (int) ($stats['total_versions'] ?? 0),
            'total_size' => (int) ($stats['total_size'] ?? 0),
            'versioned_files' => (int) ($stats['versioned_files'] ?? 0),
        ];
    }
}
