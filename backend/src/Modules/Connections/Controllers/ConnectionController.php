<?php

declare(strict_types=1);

namespace App\Modules\Connections\Controllers;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Modules\Auth\Services\AuthService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class ConnectionController
{
    private string $encryptionKey;

    public function __construct(
        private readonly Connection $db,
        private readonly AuthService $authService
    ) {
        $this->encryptionKey = $_ENV['APP_KEY'] ?? 'default-key-change-me';
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $type = $queryParams['type'] ?? null;
        $search = $queryParams['search'] ?? null;
        $tagId = $queryParams['tag_id'] ?? null;

        $sql = 'SELECT c.*,
                       GROUP_CONCAT(ct.name) as tag_names,
                       GROUP_CONCAT(ct.id) as tag_ids
                FROM connections c
                LEFT JOIN connection_tag_mapping ctm ON c.id = ctm.connection_id
                LEFT JOIN connection_tags ct ON ctm.tag_id = ct.id
                WHERE c.user_id = ?';
        $params = [$userId];
        $types = [\PDO::PARAM_STR];

        if ($type) {
            $sql .= ' AND c.type = ?';
            $params[] = $type;
            $types[] = \PDO::PARAM_STR;
        }

        if ($search) {
            $sql .= ' AND (c.name LIKE ? OR c.host LIKE ? OR c.description LIKE ?)';
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
            $types[] = \PDO::PARAM_STR;
        }

        if ($tagId) {
            $sql .= ' AND c.id IN (SELECT connection_id FROM connection_tag_mapping WHERE tag_id = ?)';
            $params[] = $tagId;
            $types[] = \PDO::PARAM_STR;
        }

        $sql .= ' GROUP BY c.id ORDER BY c.is_favorite DESC, c.last_used_at DESC, c.name ASC';
        $sql .= ' LIMIT ? OFFSET ?';
        $params[] = $perPage;
        $params[] = $offset;
        $types[] = \PDO::PARAM_INT;
        $types[] = \PDO::PARAM_INT;

        $connections = $this->db->fetchAllAssociative($sql, $params, $types);

        // Parse tags for each connection
        foreach ($connections as &$conn) {
            $conn['tags'] = [];
            if ($conn['tag_names'] && $conn['tag_ids']) {
                $names = explode(',', $conn['tag_names']);
                $ids = explode(',', $conn['tag_ids']);
                for ($i = 0; $i < count($ids); $i++) {
                    $conn['tags'][] = ['id' => $ids[$i], 'name' => $names[$i]];
                }
            }
            unset($conn['tag_names'], $conn['tag_ids']);
            // Remove sensitive data from list
            unset($conn['password_encrypted'], $conn['private_key_encrypted']);
        }

        // Count total
        $countSql = 'SELECT COUNT(DISTINCT c.id) FROM connections c';
        if ($tagId) {
            $countSql .= ' LEFT JOIN connection_tag_mapping ctm ON c.id = ctm.connection_id';
        }
        $countSql .= ' WHERE c.user_id = ?';
        $countParams = [$userId];

        if ($type) {
            $countSql .= ' AND c.type = ?';
            $countParams[] = $type;
        }
        if ($search) {
            $countSql .= ' AND (c.name LIKE ? OR c.host LIKE ? OR c.description LIKE ?)';
            $countParams[] = "%{$search}%";
            $countParams[] = "%{$search}%";
            $countParams[] = "%{$search}%";
        }
        if ($tagId) {
            $countSql .= ' AND ctm.tag_id = ?';
            $countParams[] = $tagId;
        }

        $total = (int) $this->db->fetchOne($countSql, $countParams);

        return JsonResponse::paginated($connections, $total, $page, $perPage);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $this->validateConnectionData($data);

        $connectionId = Uuid::uuid4()->toString();

        $insertData = [
            'id' => $connectionId,
            'user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'host' => $data['host'],
            'port' => $data['port'] ?? $this->getDefaultPort($data['type']),
            'username' => $data['username'] ?? null,
            'color' => $data['color'] ?? '#6366f1',
            'icon' => $data['icon'] ?? null,
            'is_favorite' => !empty($data['is_favorite']) ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Encrypt sensitive data
        if (!empty($data['password'])) {
            $insertData['password_encrypted'] = $this->encrypt($data['password']);
        }
        if (!empty($data['private_key'])) {
            $insertData['private_key_encrypted'] = $this->encrypt($data['private_key']);
        }
        if (!empty($data['extra_data'])) {
            $insertData['extra_data'] = json_encode($data['extra_data']);
        }

        $this->db->insert('connections', $insertData);

        // Handle tags
        if (!empty($data['tag_ids']) && is_array($data['tag_ids'])) {
            foreach ($data['tag_ids'] as $tagId) {
                $this->db->insert('connection_tag_mapping', [
                    'connection_id' => $connectionId,
                    'tag_id' => $tagId,
                ]);
            }
        }

        $connection = $this->getConnectionForUser($connectionId, $userId);
        unset($connection['password_encrypted'], $connection['private_key_encrypted']);

        return JsonResponse::created($connection, 'Connection created successfully');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $connectionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $connection = $this->getConnectionForUser($connectionId, $userId);

        // Get tags
        $connection['tags'] = $this->db->fetchAllAssociative(
            'SELECT ct.* FROM connection_tags ct
             INNER JOIN connection_tag_mapping ctm ON ct.id = ctm.tag_id
             WHERE ctm.connection_id = ?',
            [$connectionId]
        );

        // Check if has credentials (don't expose actual values)
        $connection['has_password'] = !empty($connection['password_encrypted']);
        $connection['has_private_key'] = !empty($connection['private_key_encrypted']);
        unset($connection['password_encrypted'], $connection['private_key_encrypted']);

        return JsonResponse::success($connection);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $connectionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getConnectionForUser($connectionId, $userId);

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        $allowedFields = ['name', 'description', 'type', 'host', 'port', 'username', 'color', 'icon', 'is_favorite'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        // Handle password update
        if (isset($data['password'])) {
            if (empty($data['password'])) {
                $updateData['password_encrypted'] = null;
            } else {
                $updateData['password_encrypted'] = $this->encrypt($data['password']);
            }
        }

        // Handle private key update
        if (isset($data['private_key'])) {
            if (empty($data['private_key'])) {
                $updateData['private_key_encrypted'] = null;
            } else {
                $updateData['private_key_encrypted'] = $this->encrypt($data['private_key']);
            }
        }

        // Handle extra_data
        if (isset($data['extra_data'])) {
            $updateData['extra_data'] = json_encode($data['extra_data']);
        }

        $this->db->update('connections', $updateData, ['id' => $connectionId]);

        // Handle tags
        if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
            $this->db->delete('connection_tag_mapping', ['connection_id' => $connectionId]);
            foreach ($data['tag_ids'] as $tagId) {
                $this->db->insert('connection_tag_mapping', [
                    'connection_id' => $connectionId,
                    'tag_id' => $tagId,
                ]);
            }
        }

        $connection = $this->getConnectionForUser($connectionId, $userId);
        $connection['has_password'] = !empty($connection['password_encrypted']);
        $connection['has_private_key'] = !empty($connection['private_key_encrypted']);
        unset($connection['password_encrypted'], $connection['private_key_encrypted']);

        return JsonResponse::success($connection, 'Connection updated successfully');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $connectionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getConnectionForUser($connectionId, $userId);

        // Cleanup favorites and tags
        $this->db->delete('favorites', ['item_type' => 'connection', 'item_id' => $connectionId]);
        $this->db->delete('taggables', ['taggable_type' => 'connection', 'taggable_id' => $connectionId]);

        $this->db->delete('connections', ['id' => $connectionId]);

        return JsonResponse::success(null, 'Connection deleted successfully');
    }

    public function getCredentials(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $connectionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $connection = $this->getConnectionForUser($connectionId, $userId);

        // Update last_used_at
        $this->db->update('connections', [
            'last_used_at' => date('Y-m-d H:i:s'),
        ], ['id' => $connectionId]);

        $credentials = [
            'host' => $connection['host'],
            'port' => $connection['port'],
            'username' => $connection['username'],
            'password' => $connection['password_encrypted'] ? $this->decrypt($connection['password_encrypted']) : null,
            'private_key' => $connection['private_key_encrypted'] ? $this->decrypt($connection['private_key_encrypted']) : null,
            'extra_data' => $connection['extra_data'] ? json_decode($connection['extra_data'], true) : null,
        ];

        return JsonResponse::success($credentials);
    }

    public function markUsed(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $connectionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getConnectionForUser($connectionId, $userId);

        $this->db->update('connections', [
            'last_used_at' => date('Y-m-d H:i:s'),
        ], ['id' => $connectionId]);

        return JsonResponse::success(null, 'Connection marked as used');
    }

    // Tag management
    public function getTags(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $tags = $this->db->fetchAllAssociative(
            'SELECT ct.*, COUNT(ctm.connection_id) as connection_count
             FROM connection_tags ct
             LEFT JOIN connection_tag_mapping ctm ON ct.id = ctm.tag_id
             WHERE ct.user_id = ?
             GROUP BY ct.id
             ORDER BY ct.name',
            [$userId]
        );

        return JsonResponse::success(['items' => $tags]);
    }

    public function createTag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name'])) {
            throw new ValidationException('Tag name is required');
        }

        $tagId = Uuid::uuid4()->toString();

        $this->db->insert('connection_tags', [
            'id' => $tagId,
            'user_id' => $userId,
            'name' => $data['name'],
            'color' => $data['color'] ?? '#6366f1',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $tag = $this->db->fetchAssociative('SELECT * FROM connection_tags WHERE id = ?', [$tagId]);

        return JsonResponse::created($tag, 'Tag created successfully');
    }

    public function deleteTag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $tagId = RouteContext::fromRequest($request)->getRoute()->getArgument('tagId');

        $tag = $this->db->fetchAssociative(
            'SELECT * FROM connection_tags WHERE id = ? AND user_id = ?',
            [$tagId, $userId]
        );

        if (!$tag) {
            throw new NotFoundException('Tag not found');
        }

        $this->db->delete('connection_tags', ['id' => $tagId]);

        return JsonResponse::success(null, 'Tag deleted successfully');
    }

    private function getConnectionForUser(string $connectionId, string $userId): array
    {
        $connection = $this->db->fetchAssociative(
            'SELECT * FROM connections WHERE id = ? AND user_id = ?',
            [$connectionId, $userId]
        );

        if (!$connection) {
            throw new NotFoundException('Connection not found');
        }

        return $connection;
    }

    private function validateConnectionData(array $data): void
    {
        if (empty($data['name'])) {
            throw new ValidationException('Name is required');
        }
        if (empty($data['type'])) {
            throw new ValidationException('Type is required');
        }
        if (empty($data['host'])) {
            throw new ValidationException('Host is required');
        }

        $validTypes = ['ssh', 'ftp', 'sftp', 'database', 'api', 'other'];
        if (!in_array($data['type'], $validTypes)) {
            throw new ValidationException('Invalid connection type');
        }
    }

    private function getDefaultPort(string $type): ?int
    {
        return match ($type) {
            'ssh', 'sftp' => 22,
            'ftp' => 21,
            'database' => 3306,
            'api' => 443,
            default => null,
        };
    }

    private function encrypt(string $data): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    private function decrypt(string $data): string
    {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }

    // ========================================================================
    // SSH Terminal with 2FA
    // ========================================================================

    public function executeCommand(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $connectionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        // Verify 2FA token or code - accepts both 6-digit 2FA codes and sensitive tokens
        $tokenOrCode = $data['sensitive_token'] ?? $data['code'] ?? null;
        if (empty($tokenOrCode)) {
            return JsonResponse::error(
                '2FA-Verifizierung erforderlich für SSH-Zugriff',
                428,
                ['requires_2fa' => true, 'operation' => 'ssh_terminal']
            );
        }

        if (!$this->authService->verifyTokenOrCode($userId, $tokenOrCode, 'ssh_terminal')) {
            return JsonResponse::error('Ungültiger oder abgelaufener 2FA-Token', 401);
        }

        $connection = $this->getConnectionForUser($connectionId, $userId);

        if ($connection['type'] !== 'ssh' && $connection['type'] !== 'sftp') {
            throw new ValidationException('This connection does not support SSH commands');
        }

        $command = $data['command'] ?? null;
        if (empty($command)) {
            throw new ValidationException('Command is required');
        }

        // Get credentials
        $password = $connection['password_encrypted'] ? $this->decrypt($connection['password_encrypted']) : null;
        $privateKey = $connection['private_key_encrypted'] ? $this->decrypt($connection['private_key_encrypted']) : null;

        // Execute command via SSH
        $result = $this->executeSSHCommand(
            $connection['host'],
            (int) $connection['port'],
            $connection['username'],
            $password,
            $privateKey,
            $command
        );

        // Log the command execution
        $this->db->insert('connection_ssh_logs', [
            'id' => Uuid::uuid4()->toString(),
            'connection_id' => $connectionId,
            'user_id' => $userId,
            'command' => $command,
            'output' => $result['output'],
            'exit_code' => $result['exit_code'],
            'executed_at' => date('Y-m-d H:i:s'),
        ]);

        // Update last_used_at
        $this->db->update('connections', [
            'last_used_at' => date('Y-m-d H:i:s'),
        ], ['id' => $connectionId]);

        return JsonResponse::success($result);
    }

    private function executeSSHCommand(
        string $host,
        int $port,
        string $username,
        ?string $password,
        ?string $privateKey,
        string $command
    ): array {
        // Try php-ssh2 extension first
        if (function_exists('ssh2_connect')) {
            return $this->executeSSHCommandViaSsh2($host, $port, $username, $password, $privateKey, $command);
        }

        // Fallback to shell command
        return $this->executeSSHCommandViaShell($host, $port, $username, $password, $privateKey, $command);
    }

    private function executeSSHCommandViaSsh2(
        string $host,
        int $port,
        string $username,
        ?string $password,
        ?string $privateKey,
        string $command
    ): array {
        $connection = @ssh2_connect($host, $port);
        if (!$connection) {
            return ['success' => false, 'output' => "Failed to connect to {$host}:{$port}", 'exit_code' => -1];
        }

        $authenticated = false;

        if ($privateKey) {
            // Write key to temp file
            $keyFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
            file_put_contents($keyFile, $privateKey);
            chmod($keyFile, 0600);

            $authenticated = @ssh2_auth_pubkey_file($connection, $username, $keyFile . '.pub', $keyFile);
            unlink($keyFile);
        }

        if (!$authenticated && $password) {
            $authenticated = @ssh2_auth_password($connection, $username, $password);
        }

        if (!$authenticated) {
            return ['success' => false, 'output' => 'SSH authentication failed', 'exit_code' => -1];
        }

        $stream = ssh2_exec($connection, $command);
        if (!$stream) {
            return ['success' => false, 'output' => 'Failed to execute command', 'exit_code' => -1];
        }

        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);

        // Get stderr
        $stderr = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
        stream_set_blocking($stderr, true);
        $errorOutput = stream_get_contents($stderr);

        fclose($stream);
        fclose($stderr);

        $fullOutput = $output;
        if ($errorOutput) {
            $fullOutput .= "\n" . $errorOutput;
        }

        return [
            'success' => true,
            'output' => $fullOutput,
            'exit_code' => 0,
        ];
    }

    private function executeSSHCommandViaShell(
        string $host,
        int $port,
        string $username,
        ?string $password,
        ?string $privateKey,
        string $command
    ): array {
        $sshArgs = [
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=10',
            '-p', (string) $port,
        ];

        if ($privateKey) {
            $keyFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
            file_put_contents($keyFile, $privateKey);
            chmod($keyFile, 0600);
            $sshArgs[] = '-i';
            $sshArgs[] = $keyFile;
        }

        $sshCommand = 'ssh ' . implode(' ', array_map('escapeshellarg', $sshArgs)) . ' '
            . escapeshellarg("{$username}@{$host}") . ' '
            . escapeshellarg($command);

        if ($password && !$privateKey) {
            $sshCommand = "sshpass -p " . escapeshellarg($password) . " " . $sshCommand;
        }

        $output = [];
        $exitCode = 0;
        exec($sshCommand . ' 2>&1', $output, $exitCode);

        if (isset($keyFile)) {
            unlink($keyFile);
        }

        return [
            'success' => $exitCode === 0,
            'output' => implode("\n", $output),
            'exit_code' => $exitCode,
        ];
    }

    // Get command history
    public function getCommandHistory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $connectionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getConnectionForUser($connectionId, $userId);

        $logs = $this->db->fetchAllAssociative(
            'SELECT id, command, exit_code, executed_at
             FROM connection_ssh_logs
             WHERE connection_id = ? AND user_id = ?
             ORDER BY executed_at DESC
             LIMIT 50',
            [$connectionId, $userId]
        );

        return JsonResponse::success(['items' => $logs]);
    }

    // ========================================================================
    // Command Presets
    // ========================================================================

    public function getCommandPresets(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $connectionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getConnectionForUser($connectionId, $userId);

        $presets = $this->db->fetchAllAssociative(
            'SELECT * FROM connection_command_presets
             WHERE connection_id = ?
             ORDER BY sort_order, name',
            [$connectionId]
        );

        return JsonResponse::success(['items' => $presets]);
    }

    public function createCommandPreset(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $connectionId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getConnectionForUser($connectionId, $userId);

        if (empty($data['name']) || empty($data['command'])) {
            throw new ValidationException('Name and command are required');
        }

        $presetId = Uuid::uuid4()->toString();

        $this->db->insert('connection_command_presets', [
            'id' => $presetId,
            'connection_id' => $connectionId,
            'name' => $data['name'],
            'command' => $data['command'],
            'description' => $data['description'] ?? null,
            'is_dangerous' => !empty($data['is_dangerous']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $preset = $this->db->fetchAssociative('SELECT * FROM connection_command_presets WHERE id = ?', [$presetId]);

        return JsonResponse::created($preset, 'Command preset created');
    }

    public function updateCommandPreset(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $connectionId = $route->getArgument('id');
        $presetId = $route->getArgument('presetId');
        $data = $request->getParsedBody() ?? [];

        $this->getConnectionForUser($connectionId, $userId);

        $preset = $this->db->fetchAssociative(
            'SELECT * FROM connection_command_presets WHERE id = ? AND connection_id = ?',
            [$presetId, $connectionId]
        );

        if (!$preset) {
            throw new NotFoundException('Command preset not found');
        }

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        $allowedFields = ['name', 'command', 'description', 'is_dangerous', 'sort_order'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        $this->db->update('connection_command_presets', $updateData, ['id' => $presetId]);

        $updated = $this->db->fetchAssociative('SELECT * FROM connection_command_presets WHERE id = ?', [$presetId]);

        return JsonResponse::success($updated, 'Command preset updated');
    }

    public function deleteCommandPreset(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $connectionId = $route->getArgument('id');
        $presetId = $route->getArgument('presetId');

        $this->getConnectionForUser($connectionId, $userId);

        $this->db->delete('connection_command_presets', ['id' => $presetId, 'connection_id' => $connectionId]);

        return JsonResponse::success(null, 'Command preset deleted');
    }
}
