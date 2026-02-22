<?php

declare(strict_types=1);

namespace App\Modules\Backup\Services;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class BackupService
{
    private const BACKUP_DIR = __DIR__ . '/../../../../storage/backups/';
    private const UPLOAD_DIR = __DIR__ . '/../../../../storage/uploads/';

    public function __construct(
        private readonly Connection $db
    ) {
        if (!is_dir(self::BACKUP_DIR)) {
            mkdir(self::BACKUP_DIR, 0755, true);
        }
    }

    // ==================== Storage Targets ====================

    public function getStorageTargets(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, name, type, is_default, is_enabled, last_test_at, last_test_status, created_at
             FROM backup_storage_targets WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );
    }

    public function getStorageTarget(string $id, string $userId): ?array
    {
        return $this->db->fetchAssociative(
            'SELECT * FROM backup_storage_targets WHERE id = ? AND user_id = ?',
            [$id, $userId]
        ) ?: null;
    }

    public function createStorageTarget(string $userId, array $data): string
    {
        $id = Uuid::uuid4()->toString();

        // Encrypt sensitive config
        $config = $this->encryptConfig($data['config'] ?? []);

        // If this is set as default, unset other defaults
        if (!empty($data['is_default'])) {
            $this->db->executeStatement(
                'UPDATE backup_storage_targets SET is_default = 0 WHERE user_id = ?',
                [$userId]
            );
        }

        $this->db->insert('backup_storage_targets', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'type' => $data['type'],
            'config' => $config,
            'is_default' => !empty($data['is_default']) ? 1 : 0,
            'is_enabled' => 1,
        ]);

        return $id;
    }

    public function updateStorageTarget(string $id, string $userId, array $data): bool
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['config'])) {
            $updateData['config'] = $this->encryptConfig($data['config']);
        }
        if (isset($data['is_enabled'])) {
            $updateData['is_enabled'] = $data['is_enabled'] ? 1 : 0;
        }
        if (isset($data['is_default'])) {
            if ($data['is_default']) {
                $this->db->executeStatement(
                    'UPDATE backup_storage_targets SET is_default = 0 WHERE user_id = ?',
                    [$userId]
                );
            }
            $updateData['is_default'] = $data['is_default'] ? 1 : 0;
        }

        if (empty($updateData)) {
            return false;
        }

        return $this->db->update('backup_storage_targets', $updateData, [
            'id' => $id,
            'user_id' => $userId,
        ]) > 0;
    }

    public function deleteStorageTarget(string $id, string $userId): bool
    {
        return $this->db->delete('backup_storage_targets', [
            'id' => $id,
            'user_id' => $userId,
        ]) > 0;
    }

    public function testStorageTarget(string $id, string $userId): array
    {
        $target = $this->getStorageTarget($id, $userId);
        if (!$target) {
            return ['success' => false, 'message' => 'Storage target not found'];
        }

        $config = $this->decryptConfig($target['config']);
        $success = false;
        $message = '';

        try {
            switch ($target['type']) {
                case 'local':
                    $path = $config['path'] ?? self::BACKUP_DIR;
                    if (!is_dir($path)) {
                        mkdir($path, 0755, true);
                    }
                    $success = is_writable($path);
                    $message = $success ? 'Local path is writable' : 'Local path is not writable';
                    break;

                case 's3':
                    // Test S3 connection
                    $success = $this->testS3Connection($config);
                    $message = $success ? 'S3 connection successful' : 'S3 connection failed';
                    break;

                case 'sftp':
                    // Test SFTP connection
                    $success = $this->testSftpConnection($config);
                    $message = $success ? 'SFTP connection successful' : 'SFTP connection failed';
                    break;

                case 'webdav':
                    // Test WebDAV connection
                    $success = $this->testWebDavConnection($config);
                    $message = $success ? 'WebDAV connection successful' : 'WebDAV connection failed';
                    break;

                default:
                    $message = 'Unknown storage type';
            }
        } catch (\Exception $e) {
            $message = 'Error: ' . $e->getMessage();
        }

        // Update test status
        $this->db->update('backup_storage_targets', [
            'last_test_at' => date('Y-m-d H:i:s'),
            'last_test_status' => $success ? 'success' : 'failed',
        ], ['id' => $id]);

        return ['success' => $success, 'message' => $message];
    }

    // ==================== Schedules ====================

    public function getSchedules(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT s.*, t.name as target_name, t.type as target_type
             FROM backup_schedules s
             JOIN backup_storage_targets t ON s.target_id = t.id
             WHERE s.user_id = ?
             ORDER BY s.created_at DESC',
            [$userId]
        );
    }

    public function getSchedule(string $id, string $userId): ?array
    {
        return $this->db->fetchAssociative(
            'SELECT s.*, t.name as target_name, t.type as target_type
             FROM backup_schedules s
             JOIN backup_storage_targets t ON s.target_id = t.id
             WHERE s.id = ? AND s.user_id = ?',
            [$id, $userId]
        ) ?: null;
    }

    public function createSchedule(string $userId, array $data): string
    {
        $id = Uuid::uuid4()->toString();
        $nextRun = $this->calculateNextRun($data['cron_expression'] ?? '0 3 * * *');

        $this->db->insert('backup_schedules', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'type' => $data['type'] ?? 'full',
            'target_id' => $data['target_id'],
            'cron_expression' => $data['cron_expression'] ?? '0 3 * * *',
            'retention_days' => $data['retention_days'] ?? 30,
            'retention_count' => $data['retention_count'] ?? 10,
            'is_enabled' => isset($data['is_enabled']) ? ($data['is_enabled'] ? 1 : 0) : 1,
            'include_uploads' => isset($data['include_uploads']) ? ($data['include_uploads'] ? 1 : 0) : 1,
            'include_logs' => isset($data['include_logs']) ? ($data['include_logs'] ? 1 : 0) : 0,
            'compression' => $data['compression'] ?? 'gzip',
            'next_run_at' => $nextRun,
        ]);

        return $id;
    }

    public function updateSchedule(string $id, string $userId, array $data): bool
    {
        $updateData = [];

        $fields = ['name', 'type', 'target_id', 'cron_expression', 'retention_days',
                   'retention_count', 'compression'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        $boolFields = ['is_enabled', 'include_uploads', 'include_logs'];
        foreach ($boolFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field] ? 1 : 0;
            }
        }

        if (isset($data['cron_expression'])) {
            $updateData['next_run_at'] = $this->calculateNextRun($data['cron_expression']);
        }

        if (empty($updateData)) {
            return false;
        }

        return $this->db->update('backup_schedules', $updateData, [
            'id' => $id,
            'user_id' => $userId,
        ]) > 0;
    }

    public function deleteSchedule(string $id, string $userId): bool
    {
        return $this->db->delete('backup_schedules', [
            'id' => $id,
            'user_id' => $userId,
        ]) > 0;
    }

    // ==================== Backups ====================

    public function getBackups(string $userId, array $params = []): array
    {
        $sql = 'SELECT b.*, t.name as target_name, t.type as target_type, s.name as schedule_name
                FROM backups b
                JOIN backup_storage_targets t ON b.target_id = t.id
                LEFT JOIN backup_schedules s ON b.schedule_id = s.id
                WHERE b.user_id = ?';
        $queryParams = [$userId];

        if (!empty($params['status'])) {
            $sql .= ' AND b.status = ?';
            $queryParams[] = $params['status'];
        }

        if (!empty($params['type'])) {
            $sql .= ' AND b.type = ?';
            $queryParams[] = $params['type'];
        }

        $sql .= ' ORDER BY b.created_at DESC';

        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $sql .= ' LIMIT ? OFFSET ?';
        $queryParams[] = $perPage;
        $queryParams[] = $offset;

        $backups = $this->db->fetchAllAssociative($sql, $queryParams);

        $total = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM backups WHERE user_id = ?',
            [$userId]
        );

        return [
            'items' => $backups,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    public function getBackup(string $id, string $userId): ?array
    {
        return $this->db->fetchAssociative(
            'SELECT b.*, t.name as target_name, t.type as target_type
             FROM backups b
             JOIN backup_storage_targets t ON b.target_id = t.id
             WHERE b.id = ? AND b.user_id = ?',
            [$id, $userId]
        ) ?: null;
    }

    public function createBackup(string $userId, array $data): array
    {
        $backupId = Uuid::uuid4()->toString();
        $targetId = $data['target_id'];
        $type = $data['type'] ?? 'full';

        $target = $this->db->fetchAssociative(
            'SELECT * FROM backup_storage_targets WHERE id = ? AND user_id = ?',
            [$targetId, $userId]
        );

        if (!$target) {
            throw new \InvalidArgumentException('Storage target not found');
        }

        // Create backup record
        $this->db->insert('backups', [
            'id' => $backupId,
            'user_id' => $userId,
            'schedule_id' => $data['schedule_id'] ?? null,
            'target_id' => $targetId,
            'type' => $type,
            'status' => 'running',
            'compression' => $data['compression'] ?? 'gzip',
            'started_at' => date('Y-m-d H:i:s'),
            'metadata' => json_encode([
                'app_version' => '1.0.0',
                'php_version' => PHP_VERSION,
                'initiated_by' => $data['initiated_by'] ?? 'manual',
            ]),
        ]);

        try {
            $result = $this->executeBackup($backupId, $userId, $target, $type, $data);
            return $result;
        } catch (\Exception $e) {
            $this->db->update('backups', [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s'),
            ], ['id' => $backupId]);

            throw $e;
        }
    }

    private function executeBackup(string $backupId, string $userId, array $target, string $type, array $options): array
    {
        $startTime = microtime(true);
        $config = $this->decryptConfig($target['config']);
        $compression = $options['compression'] ?? 'gzip';

        $timestamp = date('Y-m-d_His');
        $fileName = "backup_{$type}_{$timestamp}";
        $tables = [];
        $fileCount = 0;

        // Determine backup path
        $backupPath = $this->getBackupPath($target['type'], $config);
        $tempPath = self::BACKUP_DIR . $fileName;

        if ($type === 'full' || $type === 'database') {
            // Database backup
            $dbFile = $tempPath . '_db.sql';
            $this->backupDatabase($dbFile);
            $tables = $this->getDatabaseTables();
        }

        if ($type === 'full' || $type === 'files') {
            // Files backup
            $includeUploads = $options['include_uploads'] ?? true;
            if ($includeUploads && is_dir(self::UPLOAD_DIR)) {
                $fileCount = $this->countFiles(self::UPLOAD_DIR);
            }
        }

        // Create archive
        $archiveFile = $this->createArchive($tempPath, $type, $compression, $options);
        $fileSize = filesize($archiveFile);
        $checksum = hash_file('sha256', $archiveFile);

        // Upload to storage target
        $finalPath = $this->uploadToTarget($archiveFile, $target, $config, basename($archiveFile));

        // Cleanup temp files
        $this->cleanupTempFiles($tempPath);

        $duration = (int) (microtime(true) - $startTime);

        // Update backup record
        $this->db->update('backups', [
            'status' => 'completed',
            'file_path' => $finalPath,
            'file_name' => basename($archiveFile),
            'file_size' => $fileSize,
            'checksum' => $checksum,
            'tables_included' => json_encode($tables),
            'files_included' => $fileCount,
            'completed_at' => date('Y-m-d H:i:s'),
            'duration_seconds' => $duration,
        ], ['id' => $backupId]);

        return [
            'id' => $backupId,
            'file_name' => basename($archiveFile),
            'file_size' => $fileSize,
            'duration' => $duration,
        ];
    }

    public function deleteBackup(string $id, string $userId): bool
    {
        $backup = $this->getBackup($id, $userId);
        if (!$backup) {
            return false;
        }

        // Delete file from storage
        if ($backup['file_path']) {
            $target = $this->db->fetchAssociative(
                'SELECT * FROM backup_storage_targets WHERE id = ?',
                [$backup['target_id']]
            );

            if ($target) {
                $this->deleteFromTarget($backup['file_path'], $target);
            }
        }

        return $this->db->delete('backups', [
            'id' => $id,
            'user_id' => $userId,
        ]) > 0;
    }

    // ==================== Restore ====================

    public function restoreBackup(string $backupId, string $userId, array $options = []): array
    {
        $backup = $this->getBackup($backupId, $userId);
        if (!$backup) {
            throw new \InvalidArgumentException('Backup not found');
        }

        if ($backup['status'] !== 'completed') {
            throw new \InvalidArgumentException('Cannot restore incomplete backup');
        }

        $restoreId = Uuid::uuid4()->toString();
        $restoreType = $options['type'] ?? $backup['type'];

        $this->db->insert('backup_restores', [
            'id' => $restoreId,
            'user_id' => $userId,
            'backup_id' => $backupId,
            'status' => 'running',
            'restore_type' => $restoreType,
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $result = $this->executeRestore($backup, $restoreType, $options);

            $this->db->update('backup_restores', [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
                'duration_seconds' => $result['duration'],
            ], ['id' => $restoreId]);

            return $result;
        } catch (\Exception $e) {
            $this->db->update('backup_restores', [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s'),
            ], ['id' => $restoreId]);

            throw $e;
        }
    }

    private function executeRestore(array $backup, string $type, array $options): array
    {
        $startTime = microtime(true);

        // Download backup file
        $target = $this->db->fetchAssociative(
            'SELECT * FROM backup_storage_targets WHERE id = ?',
            [$backup['target_id']]
        );

        $config = $this->decryptConfig($target['config']);
        $tempFile = self::BACKUP_DIR . 'restore_' . Uuid::uuid4()->toString();

        $this->downloadFromTarget($backup['file_path'], $target, $config, $tempFile);

        // Extract archive
        $extractPath = $tempFile . '_extracted';
        $this->extractArchive($tempFile, $extractPath, $backup['compression']);

        // Restore database if applicable
        if ($type === 'full' || $type === 'database') {
            $dbFile = $this->findDatabaseFile($extractPath);
            if ($dbFile) {
                $this->restoreDatabase($dbFile);
            }
        }

        // Restore files if applicable
        if ($type === 'full' || $type === 'files') {
            $filesPath = $extractPath . '/uploads';
            if (is_dir($filesPath)) {
                $this->restoreFiles($filesPath, self::UPLOAD_DIR);
            }
        }

        // Cleanup
        $this->cleanupTempFiles($tempFile);
        $this->cleanupTempFiles($extractPath);

        $duration = (int) (microtime(true) - $startTime);

        return [
            'success' => true,
            'duration' => $duration,
        ];
    }

    // ==================== Helper Methods ====================

    private function encryptConfig(array $config): string
    {
        // Simple encoding - in production use proper encryption
        return json_encode($config);
    }

    private function decryptConfig(string $config): array
    {
        return json_decode($config, true) ?? [];
    }

    private function calculateNextRun(string $cronExpression): string
    {
        // Simple implementation - parse cron and calculate next run
        // For now, default to tomorrow at 3 AM
        $tomorrow = new \DateTime('tomorrow 03:00');
        return $tomorrow->format('Y-m-d H:i:s');
    }

    private function getBackupPath(string $type, array $config): string
    {
        return match ($type) {
            'local' => $config['path'] ?? self::BACKUP_DIR,
            's3' => $config['bucket'] . '/' . ($config['prefix'] ?? 'backups'),
            'sftp' => $config['path'] ?? '/backups',
            'webdav' => $config['path'] ?? '/backups',
            default => self::BACKUP_DIR,
        };
    }

    private function backupDatabase(string $outputFile): void
    {
        $host = $_ENV['DB_HOST'] ?? 'mysql';
        $database = $_ENV['DB_DATABASE'] ?? 'kyuubisoft';
        $user = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        // Check if mariadb-dump or mysqldump is available
        $dumpCommand = 'mysqldump';
        exec('which mariadb-dump 2>&1', $mariaOutput, $mariaReturn);
        if ($mariaReturn === 0) {
            $dumpCommand = 'mariadb-dump';
        } else {
            exec('which mysqldump 2>&1', $mysqlOutput, $mysqlReturn);
            if ($mysqlReturn !== 0) {
                throw new \RuntimeException('Database backup failed: Neither mysqldump nor mariadb-dump is installed in the container');
            }
        }

        // Create error file for capturing stderr
        $errorFile = $outputFile . '.err';

        // Use MYSQL_PWD env var for password, disable SSL for local connections
        $cmd = sprintf(
            'MYSQL_PWD=%s %s --host=%s --user=%s --single-transaction --quick --skip-ssl %s > %s 2>%s',
            escapeshellarg($password),
            $dumpCommand,
            escapeshellarg($host),
            escapeshellarg($user),
            escapeshellarg($database),
            escapeshellarg($outputFile),
            escapeshellarg($errorFile)
        );

        exec($cmd, $output, $returnCode);

        // Read error output if command failed
        $errorMsg = '';
        if (file_exists($errorFile)) {
            $errorMsg = trim(file_get_contents($errorFile));
            // Filter out deprecation warnings
            $errorMsg = preg_replace('/.*Deprecated program name.*\n?/i', '', $errorMsg);
            $errorMsg = trim($errorMsg);
            unlink($errorFile);
        }

        if ($returnCode !== 0) {
            // Clean up potentially incomplete dump file
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
            throw new \RuntimeException('Database backup failed: ' . ($errorMsg ?: 'Unknown error (exit code: ' . $returnCode . ')'));
        }

        // Verify the dump file was created and has content
        if (!file_exists($outputFile) || filesize($outputFile) === 0) {
            throw new \RuntimeException('Database backup failed: Dump file is empty or was not created');
        }
    }

    private function restoreDatabase(string $inputFile): void
    {
        $host = $_ENV['DB_HOST'] ?? 'mysql';
        $database = $_ENV['DB_DATABASE'] ?? 'kyuubisoft';
        $user = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        // Use mariadb or mysql command
        $mysqlCommand = 'mysql';
        exec('which mariadb 2>&1', $mariaOutput, $mariaReturn);
        if ($mariaReturn === 0) {
            $mysqlCommand = 'mariadb';
        }

        $errorFile = $inputFile . '.restore.err';

        $cmd = sprintf(
            'MYSQL_PWD=%s %s --host=%s --user=%s --skip-ssl %s < %s 2>%s',
            escapeshellarg($password),
            $mysqlCommand,
            escapeshellarg($host),
            escapeshellarg($user),
            escapeshellarg($database),
            escapeshellarg($inputFile),
            escapeshellarg($errorFile)
        );

        exec($cmd, $output, $returnCode);

        $errorMsg = '';
        if (file_exists($errorFile)) {
            $errorMsg = trim(file_get_contents($errorFile));
            $errorMsg = preg_replace('/.*Deprecated program name.*\n?/i', '', $errorMsg);
            $errorMsg = trim($errorMsg);
            unlink($errorFile);
        }

        if ($returnCode !== 0) {
            throw new \RuntimeException('Database restore failed: ' . ($errorMsg ?: 'Unknown error'));
        }
    }

    private function getDatabaseTables(): array
    {
        $tables = $this->db->fetchAllAssociative('SHOW TABLES');
        return array_map(fn($row) => array_values($row)[0], $tables);
    }

    private function countFiles(string $dir): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }
        return $count;
    }

    private function createArchive(string $basePath, string $type, string $compression, array $options): string
    {
        $extension = match ($compression) {
            'gzip' => '.tar.gz',
            'zip' => '.zip',
            default => '.tar',
        };

        $archiveFile = $basePath . $extension;

        if ($compression === 'zip') {
            $zip = new \ZipArchive();
            $zip->open($archiveFile, \ZipArchive::CREATE);

            // Add database dump if exists
            $dbFile = $basePath . '_db.sql';
            if (file_exists($dbFile)) {
                $zip->addFile($dbFile, 'database.sql');
            }

            // Add uploads if requested
            if (($type === 'full' || $type === 'files') && ($options['include_uploads'] ?? true)) {
                $this->addDirToZip($zip, self::UPLOAD_DIR, 'uploads');
            }

            $zip->close();
        } else {
            // Use tar
            $tarFile = $basePath . '.tar';
            $files = [];

            $dbFile = $basePath . '_db.sql';
            if (file_exists($dbFile)) {
                $files[] = $dbFile;
            }

            // Create tar archive
            $cmd = 'tar -cf ' . escapeshellarg($tarFile);
            foreach ($files as $file) {
                $cmd .= ' -C ' . escapeshellarg(dirname($file)) . ' ' . escapeshellarg(basename($file));
            }

            if (($type === 'full' || $type === 'files') && ($options['include_uploads'] ?? true) && is_dir(self::UPLOAD_DIR)) {
                $cmd .= ' -C ' . escapeshellarg(dirname(self::UPLOAD_DIR)) . ' uploads';
            }

            exec($cmd);

            if ($compression === 'gzip') {
                exec('gzip ' . escapeshellarg($tarFile));
                $archiveFile = $tarFile . '.gz';
            } else {
                $archiveFile = $tarFile;
            }
        }

        // Cleanup temp sql file
        $dbFile = $basePath . '_db.sql';
        if (file_exists($dbFile)) {
            unlink($dbFile);
        }

        return $archiveFile;
    }

    private function addDirToZip(\ZipArchive $zip, string $dir, string $prefix): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = $prefix . '/' . substr($file->getPathname(), strlen($dir) + 1);
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }
    }

    private function extractArchive(string $archiveFile, string $extractPath, string $compression): void
    {
        mkdir($extractPath, 0755, true);

        if ($compression === 'zip') {
            $zip = new \ZipArchive();
            $zip->open($archiveFile);
            $zip->extractTo($extractPath);
            $zip->close();
        } else {
            $cmd = 'tar -xzf ' . escapeshellarg($archiveFile) . ' -C ' . escapeshellarg($extractPath);
            exec($cmd);
        }
    }

    private function findDatabaseFile(string $extractPath): ?string
    {
        $possibleNames = ['database.sql', 'db.sql', 'backup.sql'];
        foreach ($possibleNames as $name) {
            $file = $extractPath . '/' . $name;
            if (file_exists($file)) {
                return $file;
            }
        }

        // Find any .sql file
        $files = glob($extractPath . '/*.sql');
        return $files[0] ?? null;
    }

    private function restoreFiles(string $source, string $destination): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $destPath = $destination . '/' . $iterator->getSubPathName();
            if ($file->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($file->getPathname(), $destPath);
            }
        }
    }

    private function uploadToTarget(string $sourceFile, array $target, array $config, string $fileName): string
    {
        return match ($target['type']) {
            'local' => $this->uploadToLocal($sourceFile, $config, $fileName),
            's3' => $this->uploadToS3($sourceFile, $config, $fileName),
            'sftp' => $this->uploadToSftp($sourceFile, $config, $fileName),
            'webdav' => $this->uploadToWebDav($sourceFile, $config, $fileName),
            default => throw new \InvalidArgumentException('Unknown storage type'),
        };
    }

    private function uploadToLocal(string $sourceFile, array $config, string $fileName): string
    {
        $destPath = ($config['path'] ?? self::BACKUP_DIR) . '/' . $fileName;
        $destDir = dirname($destPath);

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        rename($sourceFile, $destPath);
        return $destPath;
    }

    private function uploadToS3(string $sourceFile, array $config, string $fileName): string
    {
        $bucket    = $config['bucket'] ?? '';
        $region    = $config['region'] ?? 'us-east-1';
        $prefix    = trim($config['prefix'] ?? 'backups', '/');
        $objectKey = ($prefix ? $prefix . '/' : '') . $fileName;
        $endpoint  = $config['endpoint'] ?? "https://s3.{$region}.amazonaws.com";
        $accessKey = $config['access_key'] ?? '';
        $secretKey = $config['secret_key'] ?? '';

        if (empty($bucket) || empty($accessKey) || empty($secretKey)) {
            throw new \RuntimeException('S3 configuration incomplete (bucket, access_key, secret_key required)');
        }

        $url         = rtrim($endpoint, '/') . "/{$bucket}/{$objectKey}";
        $fileContent = file_get_contents($sourceFile);
        $contentHash = hash('sha256', $fileContent);
        $date        = gmdate('Ymd');
        $datetime    = gmdate('Ymd\THis\Z');
        $contentType = 'application/octet-stream';

        // AWS Signature V4
        $canonicalHeaders = "content-type:{$contentType}\nhost:" . parse_url($endpoint, PHP_URL_HOST) . "\nx-amz-content-sha256:{$contentHash}\nx-amz-date:{$datetime}\n";
        $signedHeaders    = 'content-type;host;x-amz-content-sha256;x-amz-date';
        $canonicalRequest = "PUT\n/{$bucket}/{$objectKey}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$contentHash}";

        $credentialScope = "{$date}/{$region}/s3/aws4_request";
        $stringToSign    = "AWS4-HMAC-SHA256\n{$datetime}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $signingKey = hash_hmac('sha256', 'aws4_request',
            hash_hmac('sha256', 's3',
                hash_hmac('sha256', $region,
                    hash_hmac('sha256', $date, 'AWS4' . $secretKey, true),
                    true),
                true),
            true);

        $signature     = hash_hmac('sha256', $stringToSign, $signingKey);
        $authorization = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$credentialScope},SignedHeaders={$signedHeaders},Signature={$signature}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $fileContent,
            CURLOPT_HTTPHEADER     => [
                "Authorization: {$authorization}",
                "Content-Type: {$contentType}",
                "x-amz-content-sha256: {$contentHash}",
                "x-amz-date: {$datetime}",
            ],
            CURLOPT_TIMEOUT        => 300,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("S3 upload failed: " . ($error ?: "HTTP {$httpCode}"));
        }

        return "s3://{$bucket}/{$objectKey}";
    }

    private function uploadToSftp(string $sourceFile, array $config, string $fileName): string
    {
        $host       = $config['host'] ?? '';
        $port       = (int) ($config['port'] ?? 22);
        $username   = $config['username'] ?? '';
        $password   = $config['password'] ?? '';
        $privateKey = $config['private_key'] ?? '';
        $remotePath = rtrim($config['path'] ?? '/backups', '/') . '/' . $fileName;

        if (empty($host) || empty($username)) {
            throw new \RuntimeException('SFTP configuration incomplete (host, username required)');
        }

        $connection = @ssh2_connect($host, $port);
        if (!$connection) {
            throw new \RuntimeException("SFTP: Could not connect to {$host}:{$port}");
        }

        $authenticated = false;
        if (!empty($privateKey)) {
            $keyFile = tempnam(sys_get_temp_dir(), 'sftp_key_');
            file_put_contents($keyFile, $privateKey);
            $authenticated = @ssh2_auth_pubkey_file($connection, $username, $keyFile . '.pub', $keyFile);
            @unlink($keyFile);
        }

        if (!$authenticated && !empty($password)) {
            $authenticated = @ssh2_auth_password($connection, $username, $password);
        }

        if (!$authenticated) {
            throw new \RuntimeException('SFTP: Authentication failed');
        }

        $sftp = @ssh2_sftp($connection);
        if (!$sftp) {
            throw new \RuntimeException('SFTP: Could not initialize SFTP subsystem');
        }

        // Ensure remote directory exists
        $remoteDir = dirname($remotePath);
        @ssh2_sftp_mkdir($sftp, $remoteDir, 0755, true);

        $stream = @fopen("ssh2.sftp://{$sftp}{$remotePath}", 'w');
        if (!$stream) {
            throw new \RuntimeException("SFTP: Could not open remote file {$remotePath}");
        }

        $localStream = fopen($sourceFile, 'r');
        stream_copy_to_stream($localStream, $stream);
        fclose($localStream);
        fclose($stream);

        return "sftp://{$host}{$remotePath}";
    }

    private function uploadToWebDav(string $sourceFile, array $config, string $fileName): string
    {
        $baseUrl    = rtrim($config['url'] ?? '', '/');
        $remotePath = rtrim($config['path'] ?? '/backups', '/') . '/' . $fileName;
        $username   = $config['username'] ?? '';
        $password   = $config['password'] ?? '';

        if (empty($baseUrl)) {
            throw new \RuntimeException('WebDAV configuration incomplete (url required)');
        }

        $url = $baseUrl . $remotePath;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_INFILE         => fopen($sourceFile, 'r'),
            CURLOPT_INFILESIZE     => filesize($sourceFile),
            CURLOPT_UPLOAD         => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/octet-stream'],
        ]);

        if (!empty($username)) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error || !in_array($httpCode, [200, 201, 204])) {
            throw new \RuntimeException("WebDAV upload failed: " . ($error ?: "HTTP {$httpCode}"));
        }

        return $url;
    }

    private function downloadFromTarget(string $remotePath, array $target, array $config, string $localPath): void
    {
        match ($target['type']) {
            'local' => copy($remotePath, $localPath),
            // Add S3, SFTP, WebDAV implementations
            default => copy($remotePath, $localPath),
        };
    }

    private function deleteFromTarget(string $path, array $target): void
    {
        if ($target['type'] === 'local' && file_exists($path)) {
            unlink($path);
        }
        // Add S3, SFTP, WebDAV implementations
    }

    private function cleanupTempFiles(string $basePath): void
    {
        $patterns = [$basePath, $basePath . '_*', $basePath . '.*'];
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) as $file) {
                if (is_file($file)) {
                    unlink($file);
                } elseif (is_dir($file)) {
                    $this->deleteDirectory($file);
                }
            }
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function testS3Connection(array $config): bool
    {
        $bucket    = $config['bucket'] ?? '';
        $region    = $config['region'] ?? 'us-east-1';
        $endpoint  = $config['endpoint'] ?? "https://s3.{$region}.amazonaws.com";
        $accessKey = $config['access_key'] ?? '';
        $secretKey = $config['secret_key'] ?? '';

        if (empty($bucket) || empty($accessKey) || empty($secretKey)) {
            return false;
        }

        $url         = rtrim($endpoint, '/') . "/{$bucket}/?max-keys=1";
        $contentHash = hash('sha256', '');
        $date        = gmdate('Ymd');
        $datetime    = gmdate('Ymd\THis\Z');

        $host             = parse_url($endpoint, PHP_URL_HOST);
        $canonicalHeaders = "host:{$host}\nx-amz-content-sha256:{$contentHash}\nx-amz-date:{$datetime}\n";
        $signedHeaders    = 'host;x-amz-content-sha256;x-amz-date';
        $canonicalRequest = "GET\n/{$bucket}/\nmax-keys=1\n{$canonicalHeaders}\n{$signedHeaders}\n{$contentHash}";

        $credentialScope = "{$date}/{$region}/s3/aws4_request";
        $stringToSign    = "AWS4-HMAC-SHA256\n{$datetime}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $signingKey = hash_hmac('sha256', 'aws4_request',
            hash_hmac('sha256', 's3',
                hash_hmac('sha256', $region,
                    hash_hmac('sha256', $date, 'AWS4' . $secretKey, true),
                    true),
                true),
            true);

        $signature     = hash_hmac('sha256', $stringToSign, $signingKey);
        $authorization = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$credentialScope},SignedHeaders={$signedHeaders},Signature={$signature}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                "Authorization: {$authorization}",
                "x-amz-content-sha256: {$contentHash}",
                "x-amz-date: {$datetime}",
            ],
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    private function testSftpConnection(array $config): bool
    {
        $host     = $config['host'] ?? '';
        $port     = (int) ($config['port'] ?? 22);
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        if (empty($host) || empty($username)) {
            return false;
        }

        $connection = @ssh2_connect($host, $port);
        if (!$connection) {
            return false;
        }

        $authenticated = !empty($password) && @ssh2_auth_password($connection, $username, $password);
        return $authenticated !== false;
    }

    private function testWebDavConnection(array $config): bool
    {
        $baseUrl  = rtrim($config['url'] ?? '', '/');
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        if (empty($baseUrl)) {
            return false;
        }

        $ch = curl_init($baseUrl . '/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CUSTOMREQUEST  => 'PROPFIND',
            CURLOPT_HTTPHEADER     => ['Depth: 0', 'Content-Type: application/xml'],
        ]);

        if (!empty($username)) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
        }

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return in_array($httpCode, [200, 207]);
    }

    // ==================== Stats ====================

    public function getStats(string $userId): array
    {
        $totalBackups = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM backups WHERE user_id = ?',
            [$userId]
        );

        $successfulBackups = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM backups WHERE user_id = ? AND status = ?',
            [$userId, 'completed']
        );

        $totalSize = (int) $this->db->fetchOne(
            'SELECT COALESCE(SUM(file_size), 0) FROM backups WHERE user_id = ? AND status = ?',
            [$userId, 'completed']
        );

        $lastBackup = $this->db->fetchAssociative(
            'SELECT * FROM backups WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1',
            [$userId, 'completed']
        );

        $activeSchedules = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM backup_schedules WHERE user_id = ? AND is_enabled = 1',
            [$userId]
        );

        return [
            'total_backups' => $totalBackups,
            'successful_backups' => $successfulBackups,
            'failed_backups' => $totalBackups - $successfulBackups,
            'total_size' => $totalSize,
            'last_backup' => $lastBackup,
            'active_schedules' => $activeSchedules,
        ];
    }
}
