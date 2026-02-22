<?php

declare(strict_types=1);

namespace App\Modules\DatabaseBrowser\Services;

use App\Core\Exceptions\ValidationException;
use Predis\Client as Redis;

class DatabaseBrowserService
{
    private string $encryptionKey;

    public function __construct(
        private readonly Redis $redis
    ) {
        $this->encryptionKey = $_ENV['APP_KEY'] ?? 'default-key-change-me';
    }

    /**
     * Create a PDO connection from a connection record.
     */
    public function createPdoConnection(array $connection): \PDO
    {
        $host     = $connection['host'];
        $port     = (int) ($connection['port'] ?? 3306);
        $username = $connection['username'];
        $password = $connection['password_encrypted'] ? $this->decrypt($connection['password_encrypted']) : null;
        $extraData = $connection['extra_data'] ? json_decode($connection['extra_data'], true) : [];
        $dbName   = $extraData['database'] ?? null;

        $subtype = strtolower($extraData['subtype'] ?? 'mysql');

        $dsn = match ($subtype) {
            'postgresql', 'pgsql' => "pgsql:host={$host};port={$port}" . ($dbName ? ";dbname={$dbName}" : ''),
            'sqlite' => "sqlite:{$host}",
            default => "mysql:host={$host};port={$port};charset=utf8mb4" . ($dbName ? ";dbname={$dbName}" : ''),
        };

        $pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_TIMEOUT            => 10,
        ]);

        return $pdo;
    }

    /**
     * List databases/schemas on the connection.
     */
    public function listSchemas(array $connection): array
    {
        $extraData = $connection['extra_data'] ? json_decode($connection['extra_data'], true) : [];
        $subtype   = strtolower($extraData['subtype'] ?? 'mysql');

        if ($subtype === 'redis') {
            return $this->listRedisInfo($connection);
        }

        $pdo = $this->createPdoConnection($connection);

        $sql = match ($subtype) {
            'postgresql', 'pgsql' => "SELECT datname as schema_name FROM pg_database WHERE datistemplate = false ORDER BY datname",
            'sqlite'              => "PRAGMA database_list",
            default               => "SHOW DATABASES",
        };

        $stmt   = $pdo->query($sql);
        $result = $stmt->fetchAll();

        // Normalize column name
        return array_map(function ($row) use ($subtype) {
            if ($subtype === 'sqlite') {
                return ['schema_name' => $row['name']];
            }
            return ['schema_name' => $row['Database'] ?? $row['schema_name'] ?? $row['datname'] ?? array_values($row)[0]];
        }, $result);
    }

    /**
     * List tables in a specific database.
     */
    public function listTables(array $connection, string $database): array
    {
        $extraData = $connection['extra_data'] ? json_decode($connection['extra_data'], true) : [];
        $subtype   = strtolower($extraData['subtype'] ?? 'mysql');

        if ($subtype === 'redis') {
            return [];
        }

        // Set the database
        $extraData['database'] = $database;
        $connection['extra_data'] = json_encode($extraData);

        $pdo = $this->createPdoConnection($connection);

        $sql = match ($subtype) {
            'postgresql', 'pgsql' => "SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name",
            'sqlite'              => "SELECT name as table_name, type as table_type FROM sqlite_master WHERE type IN ('table','view') ORDER BY name",
            default               => "SELECT TABLE_NAME as table_name, TABLE_TYPE as table_type, TABLE_ROWS as row_count, DATA_LENGTH as data_size FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME",
        };

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get column definitions for a table.
     */
    public function getTableSchema(array $connection, string $database, string $table): array
    {
        $extraData = $connection['extra_data'] ? json_decode($connection['extra_data'], true) : [];
        $subtype   = strtolower($extraData['subtype'] ?? 'mysql');
        $extraData['database'] = $database;
        $connection['extra_data'] = json_encode($extraData);

        $pdo = $this->createPdoConnection($connection);

        $sql = match ($subtype) {
            'postgresql', 'pgsql' => "SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = ? AND table_schema = 'public' ORDER BY ordinal_position",
            'sqlite'              => "PRAGMA table_info({$table})",
            default               => "SELECT COLUMN_NAME as column_name, COLUMN_TYPE as data_type, IS_NULLABLE as is_nullable, COLUMN_DEFAULT as column_default, COLUMN_KEY as column_key, EXTRA as extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION",
        };

        if ($subtype === 'sqlite') {
            $stmt = $pdo->query($sql);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$table]);
        }

        return $stmt->fetchAll();
    }

    /**
     * Fetch paginated rows from a table.
     */
    public function getTableRows(array $connection, string $database, string $table, int $limit, int $offset, ?string $orderBy, ?string $orderDir): array
    {
        $extraData = $connection['extra_data'] ? json_decode($connection['extra_data'], true) : [];
        $extraData['database'] = $database;
        $connection['extra_data'] = json_encode($extraData);

        $pdo = $this->createPdoConnection($connection);

        // Sanitize identifiers
        $table    = preg_replace('/[^a-zA-Z0-9_.]/', '', $table);
        $orderBy  = $orderBy  ? preg_replace('/[^a-zA-Z0-9_]/', '', $orderBy) : null;
        $orderDir = ($orderDir === 'DESC') ? 'DESC' : 'ASC';
        $limit    = min(max(1, $limit), 1000);
        $offset   = max(0, $offset);

        $orderClause = $orderBy ? "ORDER BY `{$orderBy}` {$orderDir}" : '';
        $sql         = "SELECT * FROM `{$table}` {$orderClause} LIMIT {$limit} OFFSET {$offset}";

        // Count query
        $countSql  = "SELECT COUNT(*) as total FROM `{$table}`";
        $countStmt = $pdo->query($countSql);
        $total     = (int) $countStmt->fetchColumn();

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll();

        return ['rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /**
     * Execute a raw SQL query (SELECT only for non-admins).
     */
    public function executeQuery(array $connection, string $database, string $query, bool $allowWrite = false): array
    {
        $extraData = $connection['extra_data'] ? json_decode($connection['extra_data'], true) : [];
        $subtype   = strtolower($extraData['subtype'] ?? 'mysql');

        if ($subtype === 'redis') {
            return $this->executeRedisCommand($connection, $query);
        }

        $extraData['database'] = $database;
        $connection['extra_data'] = json_encode($extraData);

        $trimmed = trim($query);

        // Safety check: only allow write if explicitly permitted
        if (!$allowWrite) {
            $firstWord = strtoupper(strtok($trimmed, " \t\n\r"));
            $readOnlyKeywords = ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN', 'PRAGMA'];
            if (!in_array($firstWord, $readOnlyKeywords, true)) {
                throw new ValidationException('Only SELECT/SHOW/DESCRIBE queries are allowed. Enable write mode to run DML statements.');
            }
        }

        $pdo = $this->createPdoConnection($connection);

        $start = microtime(true);
        $stmt  = $pdo->prepare($trimmed);
        $stmt->execute();
        $duration = (int) ((microtime(true) - $start) * 1000);

        $rows = $stmt->fetchAll() ?: [];

        return [
            'rows'         => $rows,
            'row_count'    => count($rows),
            'affected_rows' => $stmt->rowCount(),
            'duration_ms'  => $duration,
            'columns'      => $rows ? array_keys($rows[0]) : [],
        ];
    }

    /**
     * Test a Redis connection.
     */
    private function listRedisInfo(array $connection): array
    {
        $predis = $this->createRedisConnection($connection);
        $info   = $predis->info('keyspace');

        $databases = [];
        if (isset($info['keyspace'])) {
            foreach ($info['keyspace'] as $db => $stats) {
                $databases[] = ['schema_name' => $db, 'stats' => $stats];
            }
        }

        return $databases ?: [['schema_name' => 'db0']];
    }

    private function executeRedisCommand(array $connection, string $command): array
    {
        $predis = $this->createRedisConnection($connection);

        $parts  = preg_split('/\s+/', trim($command), -1, PREG_SPLIT_NO_EMPTY);
        $cmd    = strtolower(array_shift($parts));

        $result = $predis->executeRaw(array_merge([$cmd], $parts));

        return [
            'rows'      => is_array($result) ? array_map(fn($v) => ['value' => $v], $result) : [['value' => $result]],
            'row_count' => is_array($result) ? count($result) : 1,
            'duration_ms' => 0,
            'columns'   => ['value'],
        ];
    }

    private function createRedisConnection(array $connection): \Predis\Client
    {
        $password = $connection['password_encrypted'] ? $this->decrypt($connection['password_encrypted']) : null;

        return new \Predis\Client([
            'scheme'   => 'tcp',
            'host'     => $connection['host'],
            'port'     => (int) ($connection['port'] ?? 6379),
            'password' => $password,
            'timeout'  => 10,
        ]);
    }

    private function decrypt(string $data): string
    {
        $data      = base64_decode($data);
        $iv        = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }
}
