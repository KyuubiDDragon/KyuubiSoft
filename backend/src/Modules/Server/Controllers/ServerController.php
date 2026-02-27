<?php

namespace App\Modules\Server\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Exceptions\ValidationException;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

class ServerController
{
    public function __construct(
        private readonly Connection $db
    ) {}
    // ========================================================================
    // Host Command Execution Helpers
    // ========================================================================

    /**
     * Check if we're running inside a Docker container
     */
    private function isInContainer(): bool
    {
        // Check for .dockerenv file
        if (file_exists('/.dockerenv')) {
            return true;
        }

        // Check cgroup
        if (file_exists('/proc/1/cgroup')) {
            $cgroup = file_get_contents('/proc/1/cgroup');
            if (strpos($cgroup, 'docker') !== false || strpos($cgroup, 'kubepods') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Execute a command on the host system
     * When running in a container, uses Docker to execute on host
     */
    private function execOnHost(string $command): string
    {
        if (!$this->isInContainer()) {
            // Running directly on host
            return shell_exec($command . ' 2>&1') ?: '';
        }

        // Running in container - execute via Docker on host
        $dockerSocket = '/var/run/docker.sock';

        if (!file_exists($dockerSocket)) {
            return "Error: Docker socket not available for host access";
        }

        // Escape the command properly for shell
        $escapedCmd = str_replace("'", "'\\''", $command);

        // Use privileged container with host PID and run command via chroot
        // This is more compatible than nsenter which requires namespace access
        $dockerCmd = "docker run --rm --privileged " .
                     "--pid=host " .
                     "--network=host " .
                     "-v /:/host " .
                     "alpine:latest chroot /host sh -c '$escapedCmd' 2>&1";

        return shell_exec($dockerCmd) ?: '';
    }

    /**
     * Execute a command on host that needs write access
     * Note: This is the same as execOnHost since we always use privileged mode with chroot
     */
    private function execOnHostWithWrite(string $command): string
    {
        return $this->execOnHost($command);
    }

    /**
     * Read a file from the host
     */
    private function readHostFile(string $path): ?string
    {
        if (!$this->isInContainer()) {
            return file_exists($path) ? file_get_contents($path) : null;
        }

        $escapedPath = str_replace("'", "'\\''", $path);
        $result = $this->execOnHost("cat '$escapedPath'");

        if ($result && strpos($result, 'No such file') === false && strpos($result, "can't open") === false) {
            return $result;
        }

        return null;
    }

    /**
     * Write content to a file on the host
     */
    private function writeHostFile(string $path, string $content): bool
    {
        if (!$this->isInContainer()) {
            return file_put_contents($path, $content) !== false;
        }

        $escapedPath = str_replace("'", "'\\''", $path);
        $encodedContent = base64_encode($content);

        $result = $this->execOnHostWithWrite("echo '$encodedContent' | base64 -d > '$escapedPath'");

        return strpos($result, 'error') === false && strpos($result, 'denied') === false;
    }

    // ========================================================================
    // Crontab Management
    // ========================================================================

    /**
     * List all crontab entries for current user
     */
    public function listCrontabs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $output = $this->execOnHost('crontab -l');

            if (strpos($output, 'no crontab') !== false || strpos($output, 'must be suid') !== false) {
                // Try reading crontab file directly from host
                $user = trim($this->execOnHost('whoami'));
                $cronFile = $this->readHostFile("/var/spool/cron/crontabs/$user");

                if ($cronFile === null) {
                    // Also try /var/spool/cron/$user (different distros)
                    $cronFile = $this->readHostFile("/var/spool/cron/$user");
                }

                if ($cronFile === null) {
                    return JsonResponse::success([
                        'crontabs' => [],
                        'raw' => '',
                        'note' => 'No crontab found or access denied',
                    ]);
                }

                $output = $cronFile;
            }

            $lines = explode("\n", trim($output));
            $crontabs = [];
            $lineNumber = 0;

            foreach ($lines as $line) {
                $lineNumber++;
                $line = trim($line);

                // Skip empty lines and comments
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                // Parse cron expression
                $parsed = $this->parseCronLine($line);
                if ($parsed) {
                    $parsed['line'] = $lineNumber;
                    $parsed['enabled'] = true;
                    $crontabs[] = $parsed;
                }
            }

            return JsonResponse::success([
                'crontabs' => $crontabs,
                'raw' => $output,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to list crontabs: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Parse a cron line into components
     */
    private function parseCronLine(string $line): ?array
    {
        // Handle special strings like @reboot, @daily, etc.
        $specialPatterns = ['@reboot', '@yearly', '@annually', '@monthly', '@weekly', '@daily', '@midnight', '@hourly'];

        foreach ($specialPatterns as $pattern) {
            if (str_starts_with($line, $pattern)) {
                $command = trim(substr($line, strlen($pattern)));
                return [
                    'type' => 'cron',
                    'schedule' => $pattern,
                    'minute' => null,
                    'hour' => null,
                    'day' => null,
                    'month' => null,
                    'weekday' => null,
                    'command' => $command,
                    'raw' => $line,
                    'description' => $this->getScheduleDescription($pattern),
                ];
            }
        }

        // Standard cron format: minute hour day month weekday command
        $parts = preg_split('/\s+/', $line, 6);
        if (count($parts) >= 6) {
            $schedule = implode(' ', array_slice($parts, 0, 5));
            return [
                'type' => 'cron',
                'schedule' => $schedule,
                'minute' => $parts[0],
                'hour' => $parts[1],
                'day' => $parts[2],
                'month' => $parts[3],
                'weekday' => $parts[4],
                'command' => $parts[5],
                'raw' => $line,
                'description' => $this->getScheduleDescription($schedule),
            ];
        }

        return null;
    }

    /**
     * Get human-readable schedule description
     */
    private function getScheduleDescription(string $schedule): string
    {
        $descriptions = [
            '@reboot' => 'Bei Systemstart',
            '@yearly' => 'Jährlich (1. Januar, 00:00)',
            '@annually' => 'Jährlich (1. Januar, 00:00)',
            '@monthly' => 'Monatlich (1. Tag, 00:00)',
            '@weekly' => 'Wöchentlich (Sonntag, 00:00)',
            '@daily' => 'Täglich (00:00)',
            '@midnight' => 'Täglich (00:00)',
            '@hourly' => 'Stündlich (Minute 0)',
            '* * * * *' => 'Jede Minute',
            '*/5 * * * *' => 'Alle 5 Minuten',
            '*/10 * * * *' => 'Alle 10 Minuten',
            '*/15 * * * *' => 'Alle 15 Minuten',
            '*/30 * * * *' => 'Alle 30 Minuten',
            '0 * * * *' => 'Stündlich',
            '0 0 * * *' => 'Täglich um Mitternacht',
            '0 0 * * 0' => 'Wöchentlich (Sonntag)',
            '0 0 1 * *' => 'Monatlich (1. Tag)',
        ];

        return $descriptions[$schedule] ?? $schedule;
    }

    /**
     * Add a new crontab entry
     */
    public function addCrontab(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        $schedule = $body['schedule'] ?? null;
        $command = $body['command'] ?? null;

        if (empty($schedule) || empty($command)) {
            throw new ValidationException('Schedule and command are required');
        }

        // Validate schedule format
        if (!$this->isValidCronSchedule($schedule)) {
            throw new ValidationException('Invalid cron schedule format');
        }

        // Sanitize command (basic security check)
        if (preg_match('/[;&|`$]/', $command)) {
            throw new ValidationException('Command contains potentially dangerous characters');
        }

        try {
            // Get current crontab
            $current = $this->execOnHost('crontab -l');
            if (strpos($current, 'no crontab') !== false || strpos($current, 'must be suid') !== false) {
                $current = '';
            }

            // Add new entry
            $newLine = "$schedule $command";
            $newCrontab = trim($current) . "\n" . $newLine . "\n";

            // Write new crontab via host execution
            $encodedContent = base64_encode($newCrontab);
            $result = $this->execOnHostWithWrite("echo '$encodedContent' | base64 -d | crontab -");

            if (strpos($result, 'error') !== false || strpos($result, 'denied') !== false) {
                return JsonResponse::error('Failed to install crontab: ' . $result, 500);
            }

            return JsonResponse::success([
                'message' => 'Crontab entry added successfully',
                'entry' => $newLine,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to add crontab: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the entire crontab
     */
    public function updateCrontab(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $content = $body['content'] ?? null;

        if ($content === null) {
            throw new ValidationException('Crontab content is required');
        }

        try {
            // Write new crontab via host execution
            $encodedContent = base64_encode($content);
            $result = $this->execOnHostWithWrite("echo '$encodedContent' | base64 -d | crontab -");

            if (strpos($result, 'error') !== false || strpos($result, 'denied') !== false) {
                return JsonResponse::error('Invalid crontab syntax or permission denied: ' . $result, 400);
            }

            return JsonResponse::success(['message' => 'Crontab updated successfully']);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to update crontab: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a crontab entry by line number
     */
    public function deleteCrontab(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $lineNumber = (int) ($body['line'] ?? 0);

        if ($lineNumber < 1) {
            throw new ValidationException('Valid line number is required');
        }

        try {
            $current = $this->execOnHost('crontab -l');
            if (strpos($current, 'no crontab') !== false || strpos($current, 'must be suid') !== false) {
                throw new ValidationException('No crontab exists');
            }

            $lines = explode("\n", $current);

            if (!isset($lines[$lineNumber - 1])) {
                throw new ValidationException('Line number out of range');
            }

            unset($lines[$lineNumber - 1]);
            $newCrontab = implode("\n", $lines);

            // Write new crontab via host execution
            $encodedContent = base64_encode($newCrontab);
            $result = $this->execOnHostWithWrite("echo '$encodedContent' | base64 -d | crontab -");

            if (strpos($result, 'error') !== false || strpos($result, 'denied') !== false) {
                return JsonResponse::error('Failed to update crontab: ' . $result, 500);
            }

            return JsonResponse::success(['message' => 'Crontab entry deleted']);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to delete crontab: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Validate cron schedule format
     */
    private function isValidCronSchedule(string $schedule): bool
    {
        $specialPatterns = ['@reboot', '@yearly', '@annually', '@monthly', '@weekly', '@daily', '@midnight', '@hourly'];

        if (in_array($schedule, $specialPatterns)) {
            return true;
        }

        // Check standard format (5 fields)
        $parts = preg_split('/\s+/', $schedule);
        if (count($parts) !== 5) {
            return false;
        }

        // Basic validation for each field
        foreach ($parts as $part) {
            if (!preg_match('/^[\d,\-\*\/]+$/', $part)) {
                return false;
            }
        }

        return true;
    }

    // ========================================================================
    // Process Management
    // ========================================================================

    /**
     * List running processes
     */
    public function listProcesses(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $filter = $params['filter'] ?? null; // php, node, python, etc.
        $limit = min((int) ($params['limit'] ?? 100), 500); // Default 100, max 500

        try {
            // Get process list with details from host - limit output for performance
            $cmd = "ps aux --sort=-%mem";
            if ($filter) {
                $escapedFilter = escapeshellarg($filter);
                $cmd .= " | grep -i $escapedFilter | grep -v grep";
            }
            $cmd .= " | head -n " . ($limit + 1); // +1 for header

            $output = $this->execOnHost($cmd);
            $lines = array_filter(explode("\n", trim($output)));

            $processes = [];
            $isFirst = true;

            foreach ($lines as $line) {
                if ($isFirst && !$filter) {
                    $isFirst = false;
                    continue; // Skip header
                }

                $parts = preg_split('/\s+/', $line, 11);
                if (count($parts) >= 11) {
                    $processes[] = [
                        'user' => $parts[0],
                        'pid' => (int) $parts[1],
                        'cpu' => $parts[2] . '%',
                        'mem' => $parts[3] . '%',
                        'vsz' => $this->formatMemory((int) $parts[4] * 1024),
                        'rss' => $this->formatMemory((int) $parts[5] * 1024),
                        'tty' => $parts[6],
                        'stat' => $parts[7],
                        'start' => $parts[8],
                        'time' => $parts[9],
                        'command' => $parts[10],
                    ];
                }
            }

            // Get PHP-FPM status if available
            $phpFpmStatus = $this->getPhpFpmStatus();

            return JsonResponse::success([
                'processes' => $processes,
                'total' => count($processes),
                'php_fpm' => $phpFpmStatus,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to list processes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get PHP-FPM status
     */
    private function getPhpFpmStatus(): ?array
    {
        try {
            // Check if PHP-FPM is running on host
            $output = $this->execOnHost("systemctl is-active php*-fpm");
            $isActive = trim($output) === 'active';

            if (!$isActive) {
                // Also check for php-fpm without systemctl
                $poolStatus = $this->execOnHost("ps aux | grep 'php-fpm' | grep -v grep | wc -l");
                $workers = (int) trim($poolStatus);

                if ($workers > 0) {
                    return [
                        'active' => true,
                        'workers' => $workers,
                    ];
                }

                return null;
            }

            // Try to get PHP-FPM pool status
            $poolStatus = $this->execOnHost("ps aux | grep 'php-fpm' | grep -v grep | wc -l");

            return [
                'active' => $isActive,
                'workers' => (int) trim($poolStatus),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Kill a process
     */
    public function killProcess(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $pid = (int) ($body['pid'] ?? 0);
        $signal = $body['signal'] ?? 'TERM'; // TERM, KILL, HUP, etc.

        if ($pid < 1) {
            throw new ValidationException('Valid PID is required');
        }

        // Validate signal
        $validSignals = ['TERM', 'KILL', 'HUP', 'INT', 'QUIT', 'USR1', 'USR2'];
        if (!in_array(strtoupper($signal), $validSignals)) {
            throw new ValidationException('Invalid signal');
        }

        try {
            // Security: Don't allow killing system processes (check on host)
            $processInfo = $this->execOnHost("ps -p $pid -o user=");
            $processUser = trim($processInfo);

            $currentUser = trim($this->execOnHost('whoami'));

            if ($processUser !== $currentUser && $currentUser !== 'root') {
                return JsonResponse::error('Permission denied: Cannot kill process owned by another user', 403);
            }

            $result = $this->execOnHostWithWrite("kill -$signal $pid");

            // Check if process still exists
            sleep(1);
            $exists = $this->execOnHost("ps -p $pid");

            return JsonResponse::success([
                'message' => 'Signal sent to process',
                'pid' => $pid,
                'signal' => $signal,
                'terminated' => empty(trim($exists)) || strpos($exists, 'PID') === false,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to kill process: ' . $e->getMessage(), 500);
        }
    }

    // ========================================================================
    // Service Management (systemd)
    // ========================================================================

    /**
     * List systemd services
     */
    public function listServices(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $type = $params['type'] ?? 'all'; // all, running, failed, enabled
        $search = $params['search'] ?? null;

        try {
            $services = [];

            // Get service list based on type from host
            switch ($type) {
                case 'running':
                    $cmd = "systemctl list-units --type=service --state=running --no-pager --no-legend";
                    break;
                case 'failed':
                    $cmd = "systemctl list-units --type=service --state=failed --no-pager --no-legend";
                    break;
                case 'enabled':
                    $cmd = "systemctl list-unit-files --type=service --state=enabled --no-pager --no-legend";
                    break;
                default:
                    $cmd = "systemctl list-units --type=service --all --no-pager --no-legend";
            }

            $output = $this->execOnHost($cmd);

            // Check if systemctl is available on host
            if (strpos($output, 'not found') !== false || strpos($output, 'command not found') !== false) {
                return JsonResponse::success([
                    'services' => [],
                    'total' => 0,
                    'common' => [],
                    'note' => 'systemd not available on this host',
                ]);
            }

            $lines = array_filter(explode("\n", trim($output)));
            $searchLower = $search ? strtolower($search) : null;

            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', trim($line), 5);

                if (count($parts) >= 4) {
                    $name = str_replace('.service', '', $parts[0]);

                    // Apply search filter
                    if ($searchLower) {
                        $nameLower = strtolower($name);
                        $descLower = strtolower($parts[4] ?? '');
                        if (strpos($nameLower, $searchLower) === false && strpos($descLower, $searchLower) === false) {
                            continue;
                        }
                    }

                    $services[] = [
                        'name' => $name,
                        'load' => $parts[1] ?? 'unknown',
                        'active' => $parts[2] ?? 'unknown',
                        'sub' => $parts[3] ?? 'unknown',
                        'description' => $parts[4] ?? '',
                    ];
                }
            }

            return JsonResponse::success([
                'services' => $services,
                'total' => count($services),
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to list services: ' . $e->getMessage(), 500);
        }
    }


    /**
     * Get detailed service status
     */
    public function getServiceStatus(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $service = $params['service'] ?? null;

        if (empty($service) || !preg_match('/^[a-zA-Z0-9_@.-]+$/', $service)) {
            throw new ValidationException('Valid service name is required');
        }

        try {
            // Escape service name for shell
            $escapedService = str_replace("'", "'\\''", $service);

            $status = $this->execOnHost("systemctl status '$escapedService' --no-pager 2>&1");

            // Check if systemctl is available
            if (strpos($status, 'not found') !== false || strpos($status, 'command not found') !== false) {
                return JsonResponse::error('systemd not available on this host', 400);
            }

            $isActiveOutput = trim($this->execOnHost("systemctl is-active '$escapedService' 2>&1"));
            $isActive = $isActiveOutput === 'active';

            $isEnabledOutput = trim($this->execOnHost("systemctl is-enabled '$escapedService' 2>&1"));
            $isEnabled = $isEnabledOutput === 'enabled';

            // Get recent logs from host
            $logs = $this->execOnHost("journalctl -u '$escapedService' -n 50 --no-pager 2>&1");

            return JsonResponse::success([
                'service' => $service,
                'active' => $isActive,
                'enabled' => $isEnabled,
                'status' => $status,
                'logs' => $logs,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to get service status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Control a service (start, stop, restart, reload)
     */
    public function controlService(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $service = $body['service'] ?? null;
        $action = $body['action'] ?? null;

        if (empty($service) || !preg_match('/^[a-zA-Z0-9_@.-]+$/', $service)) {
            throw new ValidationException('Valid service name is required');
        }

        $validActions = ['start', 'stop', 'restart', 'reload', 'enable', 'disable'];
        if (!in_array($action, $validActions)) {
            throw new ValidationException('Valid action is required (start, stop, restart, reload, enable, disable)');
        }

        try {
            // Check if we have permission on host (need sudo for most service operations)
            $canSudo = !empty(trim($this->execOnHostWithWrite("sudo -n true && echo yes")));

            if (!$canSudo) {
                // Try without sudo on host (might be running as root)
                $output = $this->execOnHostWithWrite("systemctl $action $service");
            } else {
                $output = $this->execOnHostWithWrite("sudo systemctl $action $service");
            }

            // Get new status from host
            $isActive = trim($this->execOnHost("systemctl is-active $service")) === 'active';
            $isEnabled = trim($this->execOnHost("systemctl is-enabled $service")) === 'enabled';

            return JsonResponse::success([
                'message' => "Service $action completed",
                'service' => $service,
                'action' => $action,
                'active' => $isActive,
                'enabled' => $isEnabled,
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to control service: ' . $e->getMessage(), 500);
        }
    }

    // ========================================================================
    // System Info
    // ========================================================================

    /**
     * Get system overview
     */
    public function getSystemInfo(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            // Uptime from host
            $uptime = trim($this->execOnHost('uptime -p')) ?: 'unknown';
            $uptimeSince = trim($this->execOnHost('uptime -s')) ?: 'unknown';

            // Load average from host
            $loadOutput = $this->execOnHost("cat /proc/loadavg");
            $loadParts = explode(' ', trim($loadOutput));
            $loadAvg = [
                (float) ($loadParts[0] ?? 0),
                (float) ($loadParts[1] ?? 0),
                (float) ($loadParts[2] ?? 0),
            ];

            // Memory from host
            $memInfo = $this->execOnHost('free -b');
            $memLines = explode("\n", $memInfo);
            $memParts = preg_split('/\s+/', trim($memLines[1] ?? ''));

            $memTotal = (int) ($memParts[1] ?? 0);
            $memUsed = (int) ($memParts[2] ?? 0);
            $memFree = (int) ($memParts[3] ?? 0);

            // Disks from host – all real filesystems (exclude tmpfs, devtmpfs, squashfs, overlay)
            $dfOutput = $this->execOnHost(
                "df -B1 --output=target,size,used,avail,source " .
                "--exclude-type=tmpfs --exclude-type=devtmpfs " .
                "--exclude-type=squashfs --exclude-type=overlay 2>/dev/null | tail -n +2"
            );
            $disks = [];
            foreach (array_filter(explode("\n", trim($dfOutput))) as $dfLine) {
                $p = preg_split('/\s+/', trim($dfLine));
                if (count($p) < 5) {
                    continue;
                }
                $dTotal = (int) $p[1];
                $dUsed  = (int) $p[2];
                $dFree  = (int) $p[3];
                $disks[] = [
                    'mount'       => $p[0],
                    'device'      => $p[4],
                    'total'       => $this->formatMemory($dTotal),
                    'used'        => $this->formatMemory($dUsed),
                    'free'        => $this->formatMemory($dFree),
                    'percent'     => $dTotal > 0 ? round(($dUsed / $dTotal) * 100, 1) : 0,
                    'total_bytes' => $dTotal,
                    'used_bytes'  => $dUsed,
                ];
            }
            // Backwards-compat single 'disk' key – root fs or first entry
            $rootDisk  = current(array_filter($disks, fn($d) => $d['mount'] === '/')) ?: ($disks[0] ?? null);
            $diskTotal = (int) ($rootDisk['total_bytes'] ?? 0);
            $diskUsed  = (int) ($rootDisk['used_bytes']  ?? 0);
            $diskFree  = $diskTotal - $diskUsed;

            // CPU info from host
            $cpuInfo = $this->execOnHost("lscpu | grep 'Model name' | cut -d':' -f2");
            $cpuCores = (int) trim($this->execOnHost("nproc"));

            // CPU usage % – two /proc/stat samples 500ms apart for real-time accuracy
            $cpuRaw = trim($this->execOnHost(
                "sh -c '" .
                "a=\$(awk \"/^cpu /{print \\\$2,\\\$3,\\\$4,\\\$5,\\\$6,\\\$7,\\\$8}\" /proc/stat); " .
                "sleep 0.5; " .
                "b=\$(awk \"/^cpu /{print \\\$2,\\\$3,\\\$4,\\\$5,\\\$6,\\\$7,\\\$8}\" /proc/stat); " .
                "echo \$a \$b' | " .
                "awk '{i1=\$4; t1=\$1+\$2+\$3+\$4+\$5+\$6+\$7; " .
                "i2=\$11; t2=\$8+\$9+\$10+\$11+\$12+\$13+\$14; " .
                "dt=t2-t1; if(dt>0) printf \"%.1f\", (1-(i2-i1)/dt)*100; else print 0}'"
            ));
            $cpuPercent = is_numeric($cpuRaw) ? (float) $cpuRaw : 0.0;

            // Network interfaces – IP addresses and traffic counters
            $netRaw = trim($this->execOnHost(
                "ip -o addr show | awk '\$3==\"inet\" && \$2!=\"lo\" {print \$2, \$4}'"
            ));
            $netStatRaw = trim($this->execOnHost(
                "awk 'NR>2 && \$1!~/^lo:/{gsub(\":\",\"\",\$1); print \$1,\$2,\$10}' /proc/net/dev"
            ));
            $netInterfaces = [];
            foreach (array_filter(explode("\n", $netRaw)) as $iLine) {
                $parts = explode(' ', trim($iLine), 2);
                if (count($parts) === 2) {
                    $netInterfaces[$parts[0]] = ['name' => $parts[0], 'ip' => $parts[1], 'rx_bytes' => 0, 'tx_bytes' => 0];
                }
            }
            foreach (array_filter(explode("\n", $netStatRaw)) as $sLine) {
                $sp = preg_split('/\s+/', trim($sLine));
                if (count($sp) >= 3 && isset($netInterfaces[$sp[0]])) {
                    $netInterfaces[$sp[0]]['rx_bytes'] = (int) $sp[1];
                    $netInterfaces[$sp[0]]['tx_bytes'] = (int) $sp[2];
                    $netInterfaces[$sp[0]]['rx'] = $this->formatMemory((int) $sp[1]);
                    $netInterfaces[$sp[0]]['tx'] = $this->formatMemory((int) $sp[2]);
                }
            }

            // OS info from host
            $osInfo = $this->execOnHost("cat /etc/os-release | grep PRETTY_NAME | cut -d'\"' -f2");
            $kernel = trim($this->execOnHost('uname -r'));
            $hostname = trim($this->execOnHost('hostname'));

            return JsonResponse::success([
                'hostname' => $hostname,
                'os' => trim($osInfo),
                'kernel' => $kernel,
                'uptime' => $uptime,
                'uptime_since' => $uptimeSince,
                'load_average' => [
                    '1min' => round($loadAvg[0], 2),
                    '5min' => round($loadAvg[1], 2),
                    '15min' => round($loadAvg[2], 2),
                ],
                'cpu' => [
                    'model'   => trim($cpuInfo),
                    'cores'   => $cpuCores,
                    'percent' => $cpuPercent,
                ],
                'memory' => [
                    'total'   => $this->formatMemory($memTotal),
                    'used'    => $this->formatMemory($memUsed),
                    'free'    => $this->formatMemory($memFree),
                    'percent' => $memTotal > 0 ? round(($memUsed / $memTotal) * 100, 1) : 0,
                ],
                'disk' => [
                    'total'   => $this->formatMemory($diskTotal),
                    'used'    => $this->formatMemory($diskUsed),
                    'free'    => $this->formatMemory($diskFree),
                    'percent' => $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0,
                ],
                'disks'   => array_values($disks),
                'network' => array_values($netInterfaces),
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to get system info: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Format bytes to human readable
     */
    private function formatMemory(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 1) . ' GB';
    }

    // ========================================================================
    // Custom Important Services
    // ========================================================================

    /**
     * Get user's custom important services list
     */
    public function getCustomServices(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $setting = $this->db->fetchOne(
            "SELECT `value` FROM user_settings WHERE user_id = ? AND `key` = 'custom_important_services'",
            [$userId]
        );

        $services = $setting ? json_decode($setting, true) : [];

        if (empty($services)) {
            return JsonResponse::success([
                'services' => [],
                'names' => [],
            ]);
        }

        // Batch check all services at once for better performance
        $serviceList = implode(' ', array_map(fn($s) => escapeshellarg($s), $services));

        // Get active status for all services in one command
        $activeOutput = trim($this->execOnHost("systemctl is-active $serviceList 2>/dev/null"));
        $activeStates = explode("\n", $activeOutput);

        // Get enabled status for all services in one command
        $enabledOutput = trim($this->execOnHost("systemctl is-enabled $serviceList 2>/dev/null"));
        $enabledStates = explode("\n", $enabledOutput);

        // Build result
        $servicesWithStatus = [];
        foreach ($services as $index => $serviceName) {
            $activeState = $activeStates[$index] ?? 'unknown';
            $enabledState = $enabledStates[$index] ?? 'unknown';

            // Skip services that don't exist (returns 'inactive' for is-active on non-existent)
            // We check if enabled returns something like 'enabled', 'disabled', or 'static'
            $validStates = ['enabled', 'disabled', 'static', 'masked', 'linked'];
            if (!in_array($enabledState, $validStates) && $activeState === 'inactive') {
                continue;
            }

            $servicesWithStatus[] = [
                'name' => $serviceName,
                'active' => $activeState === 'active',
                'enabled' => $enabledState === 'enabled',
                'custom' => true,
            ];
        }

        return JsonResponse::success([
            'services' => $servicesWithStatus,
            'names' => $services,
        ]);
    }

    /**
     * Add a custom important service
     */
    public function addCustomService(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        $serviceName = trim($body['name'] ?? '');

        if (empty($serviceName) || !preg_match('/^[a-zA-Z0-9_@.-]+$/', $serviceName)) {
            throw new ValidationException('Valid service name is required');
        }

        // Check if service exists on host
        $exists = $this->execOnHost("systemctl cat '$serviceName' 2>&1");
        if (strpos($exists, 'No files found') !== false || strpos($exists, 'not found') !== false) {
            throw new ValidationException("Service '$serviceName' not found on system");
        }

        // Get current list
        $setting = $this->db->fetchOne(
            "SELECT `value` FROM user_settings WHERE user_id = ? AND `key` = 'custom_important_services'",
            [$userId]
        );

        $services = $setting ? json_decode($setting, true) : [];

        // Add if not already in list
        if (!in_array($serviceName, $services)) {
            $services[] = $serviceName;

            // Save using upsert
            $this->db->executeStatement(
                "INSERT INTO user_settings (user_id, `key`, `value`)
                 VALUES (?, 'custom_important_services', ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                [$userId, json_encode($services)]
            );
        }

        return JsonResponse::success([
            'message' => "Service '$serviceName' added to important services",
            'services' => $services,
        ]);
    }

    /**
     * Remove a custom important service
     */
    public function removeCustomService(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $serviceName = RouteContext::fromRequest($request)->getRoute()->getArgument('name');

        if (empty($serviceName)) {
            throw new ValidationException('Service name is required');
        }

        // Get current list
        $setting = $this->db->fetchOne(
            "SELECT `value` FROM user_settings WHERE user_id = ? AND `key` = 'custom_important_services'",
            [$userId]
        );

        $services = $setting ? json_decode($setting, true) : [];

        // Remove from list
        $services = array_values(array_filter($services, fn($s) => $s !== $serviceName));

        // Save
        $this->db->executeStatement(
            "INSERT INTO user_settings (user_id, `key`, `value`)
             VALUES (?, 'custom_important_services', ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [$userId, json_encode($services)]
        );

        return JsonResponse::success([
            'message' => "Service '$serviceName' removed from important services",
            'services' => $services,
        ]);
    }
}
