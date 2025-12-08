<?php

declare(strict_types=1);

namespace App\Modules\Storage\Controllers;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Ramsey\Uuid\Uuid;
use Slim\Psr7\Response;

class StorageController
{
    private const UPLOAD_DIR = __DIR__ . '/../../../../storage/uploads/storage/';
    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB

    // Dangerous file types that should be blocked
    private const BLOCKED_EXTENSIONS = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'exe', 'bat', 'cmd', 'sh', 'bash', 'com', 'scr', 'pif', 'vbs', 'vbe', 'js', 'jse', 'wsf', 'wsh', 'msc', 'jar'];
    private const BLOCKED_MIME_TYPES = ['application/x-php', 'application/x-httpd-php', 'application/x-executable', 'application/x-msdos-program'];

    // Virus scanning settings
    private const VIRUS_SCAN_ENABLED = true;
    private const CLAMAV_HOST = 'clamav';  // Docker service name
    private const CLAMAV_PORT = 3310;      // Default clamd port

    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * List all files for the current user
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $search = $queryParams['search'] ?? null;

        $sql = 'SELECT f.*,
                       (SELECT COUNT(*) FROM storage_shares s WHERE s.file_id = f.id AND s.is_active = 1) as active_shares
                FROM storage_files f
                WHERE f.user_id = ?';
        $params = [$userId];

        if ($search) {
            $sql .= ' AND f.name LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY f.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $perPage;
        $params[] = $offset;

        $files = $this->db->fetchAllAssociative($sql, $params);

        // Get total count
        $countSql = 'SELECT COUNT(*) FROM storage_files WHERE user_id = ?';
        $countParams = [$userId];
        if ($search) {
            $countSql .= ' AND name LIKE ?';
            $countParams[] = '%' . $search . '%';
        }
        $total = (int) $this->db->fetchOne($countSql, $countParams);

        // Calculate total storage used
        $usedStorage = (int) $this->db->fetchOne(
            'SELECT COALESCE(SUM(size), 0) FROM storage_files WHERE user_id = ?',
            [$userId]
        );

        return JsonResponse::success([
            'items' => $files,
            'used_storage' => $usedStorage,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total,
            ],
        ]);
    }

    /**
     * Upload a new file
     */
    public function upload(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $uploadedFiles = $request->getUploadedFiles();

        if (empty($uploadedFiles['file'])) {
            throw new ValidationException('Keine Datei hochgeladen');
        }

        /** @var UploadedFileInterface $file */
        $file = $uploadedFiles['file'];

        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException($this->getUploadErrorMessage($file->getError()));
        }

        // Validate file
        $this->validateFile($file);

        // Ensure upload directory exists
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }

        // Generate unique filename
        $fileId = Uuid::uuid4()->toString();
        $originalFilename = $file->getClientFilename();
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $storedFilename = $fileId . ($extension ? '.' . $extension : '');

        // Move file to storage
        $filePath = self::UPLOAD_DIR . $storedFilename;
        $file->moveTo($filePath);

        // Scan for viruses
        $scanResult = $this->scanForVirus($filePath);
        if ($scanResult !== true) {
            // Delete infected file
            @unlink($filePath);
            throw new ValidationException('Datei wurde als schädlich erkannt: ' . $scanResult);
        }

        // Get actual MIME type from file
        $mimeType = mime_content_type($filePath) ?: $file->getClientMediaType() ?: 'application/octet-stream';

        // Save to database
        $this->db->insert('storage_files', [
            'id' => $fileId,
            'user_id' => $userId,
            'name' => pathinfo($originalFilename, PATHINFO_FILENAME),
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'extension' => $extension ?: null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $fileRecord = $this->db->fetchAssociative(
            'SELECT * FROM storage_files WHERE id = ?',
            [$fileId]
        );

        return JsonResponse::created($fileRecord, 'Datei erfolgreich hochgeladen');
    }

    /**
     * Get file details
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $file = $this->getFileForUser($id, $userId);

        // Get share info if exists
        $share = $this->db->fetchAssociative(
            'SELECT * FROM storage_shares WHERE file_id = ? ORDER BY created_at DESC LIMIT 1',
            [$id]
        );

        $file['share'] = $share;

        return JsonResponse::success($file);
    }

    /**
     * Update file (rename)
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $file = $this->getFileForUser($id, $userId, true);

        $updates = ['updated_at' => date('Y-m-d H:i:s')];

        if (isset($data['name'])) {
            $updates['name'] = trim($data['name']);
        }

        $this->db->update('storage_files', $updates, ['id' => $id]);

        $updatedFile = $this->db->fetchAssociative('SELECT * FROM storage_files WHERE id = ?', [$id]);

        return JsonResponse::success($updatedFile, 'Datei aktualisiert');
    }

    /**
     * Delete a file
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $file = $this->getFileForUser($id, $userId, true);

        // Delete physical file
        $filePath = self::UPLOAD_DIR . $file['stored_filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete from database (cascade will delete shares)
        $this->db->delete('storage_files', ['id' => $id]);

        return JsonResponse::success(null, 'Datei gelöscht');
    }

    /**
     * Download a file (for owner)
     */
    public function download(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $file = $this->getFileForUser($id, $userId);

        return $this->serveFile($file);
    }

    /**
     * Create or update a share for a file
     */
    public function createShare(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $file = $this->getFileForUser($id, $userId, true);

        // Check if share already exists
        $existingShare = $this->db->fetchAssociative(
            'SELECT * FROM storage_shares WHERE file_id = ?',
            [$id]
        );

        $shareData = [
            'password_hash' => !empty($data['password']) ? password_hash($data['password'], PASSWORD_ARGON2ID) : null,
            'max_downloads' => isset($data['max_downloads']) && $data['max_downloads'] > 0 ? (int) $data['max_downloads'] : null,
            'expires_at' => !empty($data['expires_at']) ? $data['expires_at'] : null,
            'is_active' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existingShare) {
            // Update existing share
            $this->db->update('storage_shares', $shareData, ['id' => $existingShare['id']]);
            $shareId = $existingShare['id'];
        } else {
            // Create new share
            $shareId = Uuid::uuid4()->toString();
            $shareToken = bin2hex(random_bytes(32));

            $shareData['id'] = $shareId;
            $shareData['file_id'] = $id;
            $shareData['share_token'] = $shareToken;
            $shareData['download_count'] = 0;
            $shareData['created_at'] = date('Y-m-d H:i:s');

            $this->db->insert('storage_shares', $shareData);
        }

        $share = $this->db->fetchAssociative('SELECT * FROM storage_shares WHERE id = ?', [$shareId]);

        // Don't expose password hash
        unset($share['password_hash']);
        $share['has_password'] = !empty($data['password']) || (!empty($existingShare) && !empty($existingShare['password_hash']) && empty($data['password']));

        return JsonResponse::success($share, 'Freigabe erstellt');
    }

    /**
     * Get share info for a file
     */
    public function getShare(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $file = $this->getFileForUser($id, $userId);

        $share = $this->db->fetchAssociative(
            'SELECT * FROM storage_shares WHERE file_id = ?',
            [$id]
        );

        if ($share) {
            $share['has_password'] = !empty($share['password_hash']);
            unset($share['password_hash']);
        }

        return JsonResponse::success($share);
    }

    /**
     * Update share settings
     */
    public function updateShare(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $file = $this->getFileForUser($id, $userId, true);

        $share = $this->db->fetchAssociative(
            'SELECT * FROM storage_shares WHERE file_id = ?',
            [$id]
        );

        if (!$share) {
            throw new NotFoundException('Keine Freigabe gefunden');
        }

        $updates = ['updated_at' => date('Y-m-d H:i:s')];

        // Update password if provided (empty string removes password)
        if (array_key_exists('password', $data)) {
            $updates['password_hash'] = !empty($data['password'])
                ? password_hash($data['password'], PASSWORD_ARGON2ID)
                : null;
        }

        if (array_key_exists('max_downloads', $data)) {
            $updates['max_downloads'] = $data['max_downloads'] > 0 ? (int) $data['max_downloads'] : null;
        }

        if (array_key_exists('expires_at', $data)) {
            $updates['expires_at'] = !empty($data['expires_at']) ? $data['expires_at'] : null;
        }

        if (isset($data['is_active'])) {
            $updates['is_active'] = $data['is_active'] ? 1 : 0;
        }

        // Reset download count if requested
        if (!empty($data['reset_downloads'])) {
            $updates['download_count'] = 0;
        }

        $this->db->update('storage_shares', $updates, ['id' => $share['id']]);

        $updatedShare = $this->db->fetchAssociative('SELECT * FROM storage_shares WHERE id = ?', [$share['id']]);
        $updatedShare['has_password'] = !empty($updatedShare['password_hash']);
        unset($updatedShare['password_hash']);

        return JsonResponse::success($updatedShare, 'Freigabe aktualisiert');
    }

    /**
     * Delete a share
     */
    public function deleteShare(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $file = $this->getFileForUser($id, $userId, true);

        $this->db->delete('storage_shares', ['file_id' => $id]);

        return JsonResponse::success(null, 'Freigabe gelöscht');
    }

    /**
     * Get public share info (for download page)
     */
    public function getPublicShare(ServerRequestInterface $request, ResponseInterface $response, string $token): ResponseInterface
    {

        $share = $this->db->fetchAssociative(
            'SELECT s.*, f.name, f.original_filename, f.size, f.mime_type, f.extension,
                    u.username as owner_name
             FROM storage_shares s
             JOIN storage_files f ON s.file_id = f.id
             JOIN users u ON f.user_id = u.id
             WHERE s.share_token = ?',
            [$token]
        );

        if (!$share) {
            throw new NotFoundException('Freigabe nicht gefunden');
        }

        // Check if share is valid
        $validationResult = $this->validateShare($share);
        if ($validationResult !== true) {
            return JsonResponse::error($validationResult, 403);
        }

        // Return public info (no sensitive data)
        return JsonResponse::success([
            'name' => $share['name'],
            'original_filename' => $share['original_filename'],
            'size' => (int) $share['size'],
            'mime_type' => $share['mime_type'],
            'extension' => $share['extension'],
            'owner_name' => $share['owner_name'],
            'has_password' => !empty($share['password_hash']),
            'max_downloads' => $share['max_downloads'] ? (int) $share['max_downloads'] : null,
            'download_count' => (int) $share['download_count'],
            'expires_at' => $share['expires_at'],
            'downloads_remaining' => $share['max_downloads']
                ? max(0, (int) $share['max_downloads'] - (int) $share['download_count'])
                : null,
        ]);
    }

    /**
     * Download file via public share
     */
    public function downloadPublic(ServerRequestInterface $request, ResponseInterface $response, string $token): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];
        $queryParams = $request->getQueryParams();
        $password = $data['password'] ?? $queryParams['password'] ?? null;

        $share = $this->db->fetchAssociative(
            'SELECT s.*, f.stored_filename, f.original_filename, f.mime_type, f.size
             FROM storage_shares s
             JOIN storage_files f ON s.file_id = f.id
             WHERE s.share_token = ?',
            [$token]
        );

        if (!$share) {
            throw new NotFoundException('Freigabe nicht gefunden');
        }

        // Validate share
        $validationResult = $this->validateShare($share);
        if ($validationResult !== true) {
            return JsonResponse::error($validationResult, 403);
        }

        // Check password
        if (!empty($share['password_hash'])) {
            if (empty($password) || !password_verify($password, $share['password_hash'])) {
                return JsonResponse::error('Falsches Passwort', 401);
            }
        }

        // Increment download count
        $this->db->executeStatement(
            'UPDATE storage_shares SET download_count = download_count + 1 WHERE id = ?',
            [$share['id']]
        );

        // Serve file
        return $this->serveFile([
            'stored_filename' => $share['stored_filename'],
            'original_filename' => $share['original_filename'],
            'mime_type' => $share['mime_type'],
            'size' => $share['size'],
        ]);
    }

    /**
     * Get storage stats
     */
    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $stats = $this->db->fetchAssociative(
            'SELECT
                COUNT(*) as total_files,
                COALESCE(SUM(size), 0) as total_size
             FROM storage_files
             WHERE user_id = ?',
            [$userId]
        );

        $shareStats = $this->db->fetchAssociative(
            'SELECT
                COUNT(*) as total_shares,
                SUM(download_count) as total_downloads
             FROM storage_shares s
             JOIN storage_files f ON s.file_id = f.id
             WHERE f.user_id = ? AND s.is_active = 1',
            [$userId]
        );

        return JsonResponse::success([
            'total_files' => (int) $stats['total_files'],
            'total_size' => (int) $stats['total_size'],
            'active_shares' => (int) ($shareStats['total_shares'] ?? 0),
            'total_downloads' => (int) ($shareStats['total_downloads'] ?? 0),
        ]);
    }

    // ==================== Helper Methods ====================

    /**
     * Get file with ownership check
     */
    private function getFileForUser(string $fileId, string $userId, bool $requireOwner = false): array
    {
        $file = $this->db->fetchAssociative(
            'SELECT * FROM storage_files WHERE id = ?',
            [$fileId]
        );

        if (!$file) {
            throw new NotFoundException('Datei nicht gefunden');
        }

        if ($file['user_id'] !== $userId) {
            throw new ForbiddenException('Zugriff verweigert');
        }

        return $file;
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(UploadedFileInterface $file): void
    {
        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new ValidationException('Datei zu groß (max. 100MB)');
        }

        // Check extension
        $filename = $file->getClientFilename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($extension, self::BLOCKED_EXTENSIONS)) {
            throw new ValidationException('Dieser Dateityp ist nicht erlaubt');
        }

        // Check MIME type
        $mimeType = $file->getClientMediaType();
        if ($mimeType && in_array($mimeType, self::BLOCKED_MIME_TYPES)) {
            throw new ValidationException('Dieser Dateityp ist nicht erlaubt');
        }
    }

    /**
     * Validate share is still valid
     */
    private function validateShare(array $share): bool|string
    {
        if (!$share['is_active']) {
            return 'Diese Freigabe ist deaktiviert';
        }

        if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
            return 'Diese Freigabe ist abgelaufen';
        }

        if ($share['max_downloads'] && $share['download_count'] >= $share['max_downloads']) {
            return 'Download-Limit erreicht';
        }

        return true;
    }

    /**
     * Serve file for download
     */
    private function serveFile(array $file): ResponseInterface
    {
        $filePath = self::UPLOAD_DIR . $file['stored_filename'];

        if (!file_exists($filePath)) {
            throw new NotFoundException('Datei nicht gefunden');
        }

        $response = new Response();

        // Sanitize filename for Content-Disposition header
        $filename = $file['original_filename'];
        $encodedFilename = rawurlencode($filename);

        // Set headers
        $response = $response
            ->withHeader('Content-Type', $file['mime_type'])
            ->withHeader('Content-Length', (string) filesize($filePath))
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"; filename*=UTF-8''{$encodedFilename}")
            ->withHeader('Cache-Control', 'private, no-cache, no-store, must-revalidate')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0')
            ->withHeader('X-Content-Type-Options', 'nosniff');

        // Stream file in chunks for large files
        $stream = fopen($filePath, 'rb');
        while (!feof($stream)) {
            $response->getBody()->write(fread($stream, 8192));
        }
        fclose($stream);

        return $response;
    }

    /**
     * Scan file for viruses using ClamAV
     * Returns true if clean, or string with virus name if infected
     */
    private function scanForVirus(string $filePath): bool|string
    {
        // Skip if virus scanning is disabled
        if (!self::VIRUS_SCAN_ENABLED) {
            return true;
        }

        // Try TCP connection to ClamAV container first (Docker setup)
        $tcpResult = $this->scanViaTcp($filePath);
        if ($tcpResult !== null) {
            return $tcpResult;
        }

        // Fallback: Try clamdscan CLI (uses local daemon)
        $clamdscan = $this->findExecutable(['clamdscan', '/usr/bin/clamdscan', '/usr/local/bin/clamdscan']);
        if ($clamdscan) {
            $output = [];
            $returnCode = 0;
            $escapedPath = escapeshellarg($filePath);

            exec("{$clamdscan} --no-summary --stdout {$escapedPath} 2>&1", $output, $returnCode);

            // Return codes: 0 = clean, 1 = virus found, 2 = error
            if ($returnCode === 0) {
                return true;
            }

            if ($returnCode === 1) {
                // Extract virus name from output
                $outputStr = implode(' ', $output);
                if (preg_match('/: (.+) FOUND/', $outputStr, $matches)) {
                    return $matches[1];
                }
                return 'Unbekannter Virus';
            }
        }

        // Fallback: Try clamscan CLI (slower, standalone)
        $clamscan = $this->findExecutable(['clamscan', '/usr/bin/clamscan', '/usr/local/bin/clamscan']);
        if ($clamscan) {
            $output = [];
            $returnCode = 0;
            $escapedPath = escapeshellarg($filePath);

            exec("{$clamscan} --no-summary --stdout {$escapedPath} 2>&1", $output, $returnCode);

            if ($returnCode === 0) {
                return true;
            }

            if ($returnCode === 1) {
                $outputStr = implode(' ', $output);
                if (preg_match('/: (.+) FOUND/', $outputStr, $matches)) {
                    return $matches[1];
                }
                return 'Unbekannter Virus';
            }
        }

        // ClamAV not available - log warning and allow upload
        error_log('ClamAV not available for virus scanning - file upload allowed without scan: ' . $filePath);
        return true;
    }

    /**
     * Scan file via TCP connection to ClamAV daemon
     * Returns true if clean, virus name if infected, null if connection failed
     */
    private function scanViaTcp(string $filePath): bool|string|null
    {
        $socket = @fsockopen(self::CLAMAV_HOST, self::CLAMAV_PORT, $errno, $errstr, 5);
        if (!$socket) {
            return null; // Connection failed, try fallback
        }

        try {
            // Read file content
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                fclose($socket);
                return null;
            }

            // Send INSTREAM command (stream-based scanning)
            fwrite($socket, "zINSTREAM\0");

            // Send file in chunks (max 2GB, we send in 8KB chunks)
            $chunkSize = 8192;
            $offset = 0;
            $fileSize = strlen($fileContent);

            while ($offset < $fileSize) {
                $chunk = substr($fileContent, $offset, $chunkSize);
                $chunkLen = strlen($chunk);

                // Send chunk size as 4-byte big-endian integer
                fwrite($socket, pack('N', $chunkLen));
                fwrite($socket, $chunk);

                $offset += $chunkLen;
            }

            // Send zero-length chunk to signal end of stream
            fwrite($socket, pack('N', 0));

            // Read response
            $response = '';
            while (!feof($socket)) {
                $response .= fread($socket, 1024);
            }

            fclose($socket);

            // Parse response
            $response = trim($response);

            if (str_contains($response, 'OK')) {
                return true;
            }

            if (str_contains($response, 'FOUND')) {
                // Extract virus name: "stream: VirusName FOUND"
                if (preg_match('/stream:\s*(.+)\s*FOUND/', $response, $matches)) {
                    return trim($matches[1]);
                }
                return 'Unbekannter Virus';
            }

            // Error or unexpected response
            error_log('ClamAV unexpected response: ' . $response);
            return null;

        } catch (\Throwable $e) {
            @fclose($socket);
            error_log('ClamAV scan error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Find executable in common paths
     */
    private function findExecutable(array $paths): ?string
    {
        foreach ($paths as $path) {
            // If it's just a command name, try to find it in PATH
            if (!str_contains($path, '/')) {
                $result = shell_exec("which {$path} 2>/dev/null");
                if ($result) {
                    return trim($result);
                }
            } elseif (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Get human readable upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'Datei überschreitet die maximale Upload-Größe',
            UPLOAD_ERR_FORM_SIZE => 'Datei überschreitet die maximale Formulargröße',
            UPLOAD_ERR_PARTIAL => 'Datei wurde nur teilweise hochgeladen',
            UPLOAD_ERR_NO_FILE => 'Keine Datei hochgeladen',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Verzeichnis fehlt',
            UPLOAD_ERR_CANT_WRITE => 'Fehler beim Schreiben der Datei',
            UPLOAD_ERR_EXTENSION => 'Upload durch Erweiterung gestoppt',
            default => 'Unbekannter Upload-Fehler',
        };
    }
}
