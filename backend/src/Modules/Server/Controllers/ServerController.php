<?php

namespace App\Modules\Server\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ServerController
{
    // ========================================================================
    // Crontab Management
    // ========================================================================

    /**
     * List all crontab entries for current user
     */
    public function listCrontabs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $output = shell_exec('crontab -l 2>&1');

            if (strpos($output, 'no crontab') !== false) {
                return JsonResponse::success([
                    'crontabs' => [],
                    'raw' => '',
                ]);
            }

            $lines = explode("\n", trim($output));
            $crontabs = [];
            $lineNumber = 0;

            foreach ($lines as $line) {
                $lineNumber++;
                $line = trim($line);

                if (empty($line)) continue;

                // Check if comment
                if (str_starts_with($line, '#')) {
                    $crontabs[] = [
                        'line' => $lineNumber,
                        'type' => 'comment',
                        'raw' => $line,
                        'enabled' => false,
                    ];
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
            $current = shell_exec('crontab -l 2>/dev/null') ?: '';

            // Add new entry
            $newLine = "$schedule $command";
            $newCrontab = trim($current) . "\n" . $newLine . "\n";

            // Write new crontab
            $tempFile = tempnam(sys_get_temp_dir(), 'cron');
            file_put_contents($tempFile, $newCrontab);

            $result = shell_exec("crontab $tempFile 2>&1");
            unlink($tempFile);

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
            $tempFile = tempnam(sys_get_temp_dir(), 'cron');
            file_put_contents($tempFile, $content);

            $result = shell_exec("crontab $tempFile 2>&1");
            unlink($tempFile);

            if (strpos($result, 'error') !== false) {
                return JsonResponse::error('Invalid crontab syntax: ' . $result, 400);
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
            $current = shell_exec('crontab -l 2>/dev/null') ?: '';
            $lines = explode("\n", $current);

            if (!isset($lines[$lineNumber - 1])) {
                throw new ValidationException('Line number out of range');
            }

            unset($lines[$lineNumber - 1]);
            $newCrontab = implode("\n", $lines);

            $tempFile = tempnam(sys_get_temp_dir(), 'cron');
            file_put_contents($tempFile, $newCrontab);
            shell_exec("crontab $tempFile 2>&1");
            unlink($tempFile);

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

        try {
            // Get process list with details
            $cmd = "ps aux --sort=-%mem";
            if ($filter) {
                $cmd .= " | grep -i " . escapeshellarg($filter) . " | grep -v grep";
            }

            $output = shell_exec($cmd . " 2>&1");
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
            // Check if PHP-FPM is running
            $output = shell_exec("systemctl is-active php*-fpm 2>/dev/null") ?: '';
            $isActive = trim($output) === 'active';

            if (!$isActive) {
                return null;
            }

            // Try to get PHP-FPM pool status
            $poolStatus = shell_exec("ps aux | grep 'php-fpm' | grep -v grep | wc -l");

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
            // Security: Don't allow killing system processes
            $processInfo = shell_exec("ps -p $pid -o user= 2>/dev/null");
            $processUser = trim($processInfo);

            $currentUser = trim(shell_exec('whoami'));

            if ($processUser !== $currentUser && $currentUser !== 'root') {
                return JsonResponse::error('Permission denied: Cannot kill process owned by another user', 403);
            }

            $result = shell_exec("kill -$signal $pid 2>&1");

            // Check if process still exists
            sleep(1);
            $exists = shell_exec("ps -p $pid 2>/dev/null");

            return JsonResponse::success([
                'message' => 'Signal sent to process',
                'pid' => $pid,
                'signal' => $signal,
                'terminated' => empty(trim($exists)),
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

        try {
            $services = [];

            // Get service list based on type
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

            $output = shell_exec("$cmd 2>&1");
            $lines = array_filter(explode("\n", trim($output)));

            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', trim($line), 5);

                if (count($parts) >= 4) {
                    $name = str_replace('.service', '', $parts[0]);

                    $services[] = [
                        'name' => $name,
                        'load' => $parts[1] ?? 'unknown',
                        'active' => $parts[2] ?? 'unknown',
                        'sub' => $parts[3] ?? 'unknown',
                        'description' => $parts[4] ?? '',
                    ];
                }
            }

            // Get common services status
            $commonServices = $this->getCommonServicesStatus();

            return JsonResponse::success([
                'services' => $services,
                'total' => count($services),
                'common' => $commonServices,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to list services: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get status of common services
     */
    private function getCommonServicesStatus(): array
    {
        $commonServices = [
            'nginx', 'apache2', 'httpd', 'mysql', 'mariadb', 'postgresql',
            'redis', 'memcached', 'docker', 'php-fpm', 'php8.2-fpm', 'php8.1-fpm',
            'ssh', 'sshd', 'cron', 'fail2ban', 'ufw', 'supervisor'
        ];

        $status = [];

        foreach ($commonServices as $service) {
            $isActive = trim(shell_exec("systemctl is-active $service 2>/dev/null")) === 'active';
            $isEnabled = trim(shell_exec("systemctl is-enabled $service 2>/dev/null")) === 'enabled';
            $exists = !empty(trim(shell_exec("systemctl cat $service 2>/dev/null")));

            if ($exists) {
                $status[] = [
                    'name' => $service,
                    'active' => $isActive,
                    'enabled' => $isEnabled,
                ];
            }
        }

        return $status;
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
            $status = shell_exec("systemctl status $service --no-pager 2>&1");
            $isActive = trim(shell_exec("systemctl is-active $service 2>/dev/null")) === 'active';
            $isEnabled = trim(shell_exec("systemctl is-enabled $service 2>/dev/null")) === 'enabled';

            // Get recent logs
            $logs = shell_exec("journalctl -u $service -n 50 --no-pager 2>&1");

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
            // Check if we have permission (need sudo for most service operations)
            $canSudo = !empty(trim(shell_exec("sudo -n true 2>/dev/null && echo yes")));

            if (!$canSudo) {
                return JsonResponse::error('Insufficient permissions. Sudo access required.', 403);
            }

            $output = shell_exec("sudo systemctl $action $service 2>&1");

            // Get new status
            $isActive = trim(shell_exec("systemctl is-active $service 2>/dev/null")) === 'active';
            $isEnabled = trim(shell_exec("systemctl is-enabled $service 2>/dev/null")) === 'enabled';

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
            // Uptime
            $uptime = trim(shell_exec('uptime -p 2>/dev/null')) ?: 'unknown';
            $uptimeSince = trim(shell_exec('uptime -s 2>/dev/null')) ?: 'unknown';

            // Load average
            $loadAvg = sys_getloadavg();

            // Memory
            $memInfo = shell_exec('free -b 2>/dev/null');
            $memLines = explode("\n", $memInfo);
            $memParts = preg_split('/\s+/', trim($memLines[1] ?? ''));

            $memTotal = (int) ($memParts[1] ?? 0);
            $memUsed = (int) ($memParts[2] ?? 0);
            $memFree = (int) ($memParts[3] ?? 0);

            // Disk
            $diskTotal = disk_total_space('/');
            $diskFree = disk_free_space('/');
            $diskUsed = $diskTotal - $diskFree;

            // CPU info
            $cpuInfo = shell_exec("lscpu 2>/dev/null | grep 'Model name' | cut -d':' -f2");
            $cpuCores = (int) trim(shell_exec("nproc 2>/dev/null"));

            // OS info
            $osInfo = shell_exec("cat /etc/os-release 2>/dev/null | grep PRETTY_NAME | cut -d'\"' -f2");
            $kernel = trim(shell_exec('uname -r 2>/dev/null'));
            $hostname = gethostname();

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
                    'model' => trim($cpuInfo),
                    'cores' => $cpuCores,
                ],
                'memory' => [
                    'total' => $this->formatMemory($memTotal),
                    'used' => $this->formatMemory($memUsed),
                    'free' => $this->formatMemory($memFree),
                    'percent' => $memTotal > 0 ? round(($memUsed / $memTotal) * 100, 1) : 0,
                ],
                'disk' => [
                    'total' => $this->formatMemory($diskTotal),
                    'used' => $this->formatMemory($diskUsed),
                    'free' => $this->formatMemory($diskFree),
                    'percent' => $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0,
                ],
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
}
