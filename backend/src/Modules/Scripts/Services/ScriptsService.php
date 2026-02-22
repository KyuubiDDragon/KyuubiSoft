<?php

declare(strict_types=1);

namespace App\Modules\Scripts\Services;

use Doctrine\DBAL\Connection;

class ScriptsService
{
    private string $encryptionKey;

    public function __construct(
        private readonly Connection $db
    ) {
        $this->encryptionKey = $_ENV['APP_KEY'] ?? 'default-key-change-me';
    }

    /**
     * Run a script either locally (in the PHP container) or via SSH on a remote host.
     */
    public function runScript(array $script, ?string $connectionId, string $userId): array
    {
        $start = microtime(true);

        if ($connectionId) {
            $result = $this->runViaSSH($script, $connectionId, $userId);
        } else {
            $result = $this->runLocally($script);
        }

        $result['duration_ms'] = (int) ((microtime(true) - $start) * 1000);

        return $result;
    }

    /**
     * Run the script locally inside the PHP backend container.
     */
    private function runLocally(array $script): array
    {
        $ext = match ($script['language']) {
            'python' => 'py',
            'php'    => 'php',
            'node'   => 'js',
            default  => 'sh',
        };

        $tmpFile = tempnam(sys_get_temp_dir(), 'kyuubi_script_') . '.' . $ext;
        file_put_contents($tmpFile, $script['content']);
        chmod($tmpFile, 0700);

        $interpreter = match ($script['language']) {
            'python' => 'python3',
            'php'    => 'php',
            'node'   => 'node',
            default  => 'bash',
        };

        $cmd = escapeshellarg($interpreter) . ' ' . escapeshellarg($tmpFile) . ' 2>&1';

        $stdout   = [];
        $exitCode = 0;
        exec($cmd, $stdout, $exitCode);

        @unlink($tmpFile);

        return [
            'stdout'    => implode("\n", $stdout),
            'stderr'    => '',
            'exit_code' => $exitCode,
            'host'      => 'local',
        ];
    }

    /**
     * Run the script via SSH on a remote connection.
     */
    private function runViaSSH(array $script, string $connectionId, string $userId): array
    {
        $connection = $this->db->fetchAssociative(
            'SELECT * FROM connections WHERE id = ? AND user_id = ? AND type IN (\'ssh\', \'sftp\')',
            [$connectionId, $userId]
        );

        if (!$connection) {
            return [
                'stdout'    => '',
                'stderr'    => 'SSH connection not found or not accessible',
                'exit_code' => -1,
                'host'      => 'unknown',
            ];
        }

        $password   = $connection['password_encrypted']    ? $this->decrypt($connection['password_encrypted'])    : null;
        $privateKey = $connection['private_key_encrypted'] ? $this->decrypt($connection['private_key_encrypted']) : null;

        // Escape and wrap content as a heredoc command
        $content = base64_encode($script['content']);

        $interpreter = match ($script['language']) {
            'python' => 'python3',
            'php'    => 'php',
            'node'   => 'node',
            default  => 'bash',
        };

        // Decode on remote, write to tmp, execute, delete
        $remoteCmd = "echo {$content} | base64 -d > /tmp/__kscript && {$interpreter} /tmp/__kscript; rm -f /tmp/__kscript";

        return $this->execSSH($connection['host'], (int) $connection['port'], $connection['username'], $password, $privateKey, $remoteCmd);
    }

    private function execSSH(string $host, int $port, string $username, ?string $password, ?string $privateKey, string $command): array
    {
        if (function_exists('ssh2_connect')) {
            $conn = @ssh2_connect($host, $port);
            if ($conn) {
                $auth = false;
                if ($privateKey) {
                    $kf = tempnam(sys_get_temp_dir(), 'ssh_k_');
                    file_put_contents($kf, $privateKey);
                    chmod($kf, 0600);
                    $auth = @ssh2_auth_pubkey_file($conn, $username, $kf . '.pub', $kf);
                    @unlink($kf);
                }
                if (!$auth && $password) {
                    $auth = @ssh2_auth_password($conn, $username, $password);
                }

                if ($auth) {
                    $stream = ssh2_exec($conn, $command);
                    stream_set_blocking($stream, true);
                    $out = stream_get_contents($stream);
                    fclose($stream);
                    return ['stdout' => $out, 'stderr' => '', 'exit_code' => 0, 'host' => $host];
                }
            }
        }

        // Fallback: shell-based SSH
        $args = ['-o', 'StrictHostKeyChecking=no', '-o', 'ConnectTimeout=15', '-p', (string) $port];

        $kf = null;
        if ($privateKey) {
            $kf = tempnam(sys_get_temp_dir(), 'ssh_k_');
            file_put_contents($kf, $privateKey);
            chmod($kf, 0600);
            $args[] = '-i';
            $args[] = $kf;
        }

        $sshCmd = 'ssh ' . implode(' ', array_map('escapeshellarg', $args))
            . ' ' . escapeshellarg("{$username}@{$host}")
            . ' ' . escapeshellarg($command) . ' 2>&1';

        if ($password && !$privateKey) {
            $sshCmd = 'sshpass -p ' . escapeshellarg($password) . ' ' . $sshCmd;
        }

        $output   = [];
        $exitCode = 0;
        exec($sshCmd, $output, $exitCode);

        if ($kf) @unlink($kf);

        return ['stdout' => implode("\n", $output), 'stderr' => '', 'exit_code' => $exitCode, 'host' => $host];
    }

    private function decrypt(string $data): string
    {
        $data      = base64_decode($data);
        $iv        = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }
}
