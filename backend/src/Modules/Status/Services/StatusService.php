<?php

declare(strict_types=1);

namespace App\Modules\Status\Services;

use Doctrine\DBAL\Connection;

/**
 * Aggregates read-only, LOCAL-ONLY health data for external consumers such as
 * the Alexa skill, dashboards or other integrations.
 *
 * Design guarantees:
 *  - Read-only: never starts/stops/deletes anything.
 *  - Local-only: shells out to the local Docker daemon / host, never to a
 *    user-configured remote Docker host. There is no host-selection here, so a
 *    caller can never pivot to another machine.
 *
 * Host metrics mirror the approach used by ServerController so values line up
 * with the web UI: when running inside a container, host-level commands are run
 * through a privileged helper that chroots into the host filesystem.
 */
class StatusService
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * One compact, voice-friendly snapshot combining server, docker and
     * service health. This is the endpoint Alexa calls most.
     *
     * @param string|null $userId Scope uptime/SSL/cron to this user. When null,
     *                            aggregate across all users (single-admin homelab).
     */
    public function getOverview(?string $userId = null): array
    {
        $server = $this->getServer();
        $docker = $this->getContainers();
        $services = $this->getServices($userId);

        return [
            'generated_at' => date('c'),
            'server' => $server,
            'docker' => [
                'available' => $docker['available'],
                'summary' => $docker['summary'],
            ],
            'services' => [
                'uptime' => $services['uptime']['summary'],
                'ssl' => $services['ssl']['summary'],
                'cron' => $services['cron']['summary'],
            ],
            'alerts' => $this->buildAlerts($server, $docker, $services),
        ];
    }

    // ------------------------------------------------------------------
    // Server (local host)
    // ------------------------------------------------------------------

    /**
     * Local server health: CPU %, memory, disks, load average and uptime.
     */
    public function getServer(): array
    {
        $uptime = trim($this->execOnHost('uptime -p')) ?: 'unknown';

        // Load average
        $loadParts = explode(' ', trim($this->execOnHost('cat /proc/loadavg')));
        $load = [
            (float) ($loadParts[0] ?? 0),
            (float) ($loadParts[1] ?? 0),
            (float) ($loadParts[2] ?? 0),
        ];

        // Memory (bytes)
        $memLines = explode("\n", $this->execOnHost('free -b'));
        $memParts = preg_split('/\s+/', trim($memLines[1] ?? ''));
        $memTotal = (int) ($memParts[1] ?? 0);
        $memUsed = (int) ($memParts[2] ?? 0);
        $memPercent = $memTotal > 0 ? round(($memUsed / $memTotal) * 100, 1) : 0.0;

        // Disks (exclude virtual filesystems)
        $dfOutput = $this->execOnHost(
            'df -B1 --output=target,size,used,avail,source ' .
            '--exclude-type=tmpfs --exclude-type=devtmpfs ' .
            '--exclude-type=squashfs --exclude-type=overlay 2>/dev/null | tail -n +2'
        );
        $disks = [];
        foreach (array_filter(explode("\n", trim($dfOutput))) as $line) {
            $p = preg_split('/\s+/', trim($line));
            if (count($p) < 5) {
                continue;
            }
            $total = (int) $p[1];
            $used = (int) $p[2];
            $disks[] = [
                'mount' => $p[0],
                'device' => $p[4],
                'total_bytes' => $total,
                'used_bytes' => $used,
                'free_bytes' => (int) $p[3],
                'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0.0,
            ];
        }

        // CPU usage % — two /proc/stat samples 500ms apart
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
        $cpuCores = (int) trim($this->execOnHost('nproc')) ?: 1;

        return [
            'cpu' => [
                'percent' => $cpuPercent,
                'cores' => $cpuCores,
                'load' => $load,
            ],
            'memory' => [
                'total_bytes' => $memTotal,
                'used_bytes' => $memUsed,
                'percent' => $memPercent,
            ],
            'disks' => $disks,
            'uptime' => $uptime,
        ];
    }

    // ------------------------------------------------------------------
    // Docker (local daemon)
    // ------------------------------------------------------------------

    /**
     * Local Docker containers with a running/stopped summary and per-container
     * CPU/memory load for the running ones.
     */
    public function getContainers(): array
    {
        if (!$this->dockerAvailable()) {
            return [
                'available' => false,
                'summary' => ['total' => 0, 'running' => 0, 'stopped' => 0, 'unhealthy' => 0],
                'containers' => [],
            ];
        }

        // List (running + stopped)
        $psOutput = $this->execDocker("ps -a --format '{{json .}}'");
        $containers = [];
        foreach (array_filter(explode("\n", trim($psOutput))) as $line) {
            $c = json_decode($line, true);
            if (!$c) {
                continue;
            }
            $status = $c['Status'] ?? '';
            $containers[] = [
                'name' => ltrim($c['Names'] ?? '', '/'),
                'image' => $c['Image'] ?? '',
                'state' => $c['State'] ?? '',
                'status' => $status,
                'unhealthy' => stripos($status, 'unhealthy') !== false,
                'stack' => $this->labelValue($c['Labels'] ?? '', 'com.docker.compose.project'),
                'cpu_percent' => null,
                'mem_percent' => null,
            ];
        }

        // Live stats for running containers (single call for all)
        $statsOutput = $this->execDocker("stats --no-stream --format '{{json .}}'");
        $statsByName = [];
        foreach (array_filter(explode("\n", trim($statsOutput))) as $line) {
            $s = json_decode($line, true);
            if (!$s) {
                continue;
            }
            $name = ltrim($s['Name'] ?? '', '/');
            $statsByName[$name] = [
                'cpu_percent' => $this->parsePercent($s['CPUPerc'] ?? ''),
                'mem_percent' => $this->parsePercent($s['MemPerc'] ?? ''),
            ];
        }
        foreach ($containers as &$c) {
            if (isset($statsByName[$c['name']])) {
                $c['cpu_percent'] = $statsByName[$c['name']]['cpu_percent'];
                $c['mem_percent'] = $statsByName[$c['name']]['mem_percent'];
            }
        }
        unset($c);

        $running = 0;
        $unhealthy = 0;
        foreach ($containers as $c) {
            if ($c['state'] === 'running') {
                $running++;
            }
            if ($c['unhealthy']) {
                $unhealthy++;
            }
        }
        $total = count($containers);

        return [
            'available' => true,
            'summary' => [
                'total' => $total,
                'running' => $running,
                'stopped' => $total - $running,
                'unhealthy' => $unhealthy,
            ],
            'containers' => $containers,
        ];
    }

    /**
     * Start / stop / restart a LOCAL container by name. Write operation.
     *
     * Strictly validated: the action must be one of the three known verbs and
     * the name must match an existing local container (exact name). No host
     * selection — only the local daemon is ever touched.
     *
     * @return array{ok:bool,message:string}
     */
    public function controlContainer(string $name, string $action): array
    {
        if (!in_array($action, ['start', 'stop', 'restart'], true)) {
            return ['ok' => false, 'message' => 'Unbekannte Aktion.'];
        }
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $name)) {
            return ['ok' => false, 'message' => 'Ungültiger Container-Name.'];
        }
        if (!$this->dockerAvailable()) {
            return ['ok' => false, 'message' => 'Docker ist nicht erreichbar.'];
        }

        // Confirm the container actually exists locally (exact-name match)
        // before issuing any command.
        $match = trim($this->execDocker(
            'ps -a --filter ' . escapeshellarg('name=^/' . $name . '$') . ' --format ' . escapeshellarg('{{.Names}}')
        ));
        $names = array_filter(explode("\n", $match), fn($n) => trim($n) === $name);
        if (empty($names)) {
            return ['ok' => false, 'message' => "Container {$name} wurde nicht gefunden."];
        }

        $output = $this->execDocker($action . ' ' . escapeshellarg($name));
        $failed = stripos($output, 'error') !== false
            || stripos($output, 'no such container') !== false
            || stripos($output, 'permission denied') !== false;

        if ($failed) {
            return ['ok' => false, 'message' => "Aktion für {$name} fehlgeschlagen."];
        }

        $verb = ['start' => 'gestartet', 'stop' => 'gestoppt', 'restart' => 'neu gestartet'][$action];
        return ['ok' => true, 'message' => "Container {$name} wurde {$verb}."];
    }

    // ------------------------------------------------------------------
    // Services (uptime monitors, SSL certs, cron jobs) — from the database
    // ------------------------------------------------------------------

    /**
     * Health of monitored services, SSL certificates and cron jobs.
     */
    public function getServices(?string $userId = null): array
    {
        return [
            'uptime' => $this->getUptime($userId),
            'ssl' => $this->getSsl($userId),
            'cron' => $this->getCron($userId),
        ];
    }

    private function getUptime(?string $userId): array
    {
        [$where, $params] = $this->userScope($userId);
        $rows = $this->db->fetchAllAssociative(
            "SELECT name, current_status, last_check_at, uptime_percentage
             FROM uptime_monitors
             WHERE is_active = 1 AND is_paused = 0 {$where}
             ORDER BY (current_status = 'down') DESC, name ASC",
            $params
        );

        $down = array_values(array_filter($rows, fn($r) => $r['current_status'] === 'down'));
        return [
            'summary' => [
                'total' => count($rows),
                'up' => count(array_filter($rows, fn($r) => $r['current_status'] === 'up')),
                'down' => count($down),
                'down_names' => array_map(fn($r) => $r['name'], $down),
            ],
            'monitors' => $rows,
        ];
    }

    private function getSsl(?string $userId): array
    {
        [$where, $params] = $this->userScope($userId);
        $rows = $this->db->fetchAllAssociative(
            "SELECT name, hostname, current_status, days_until_expiry, valid_until
             FROM ssl_certificates
             WHERE is_active = 1 {$where}
             ORDER BY days_until_expiry ASC",
            $params
        );

        $problem = array_values(array_filter(
            $rows,
            fn($r) => in_array($r['current_status'], ['expiring_soon', 'expired', 'invalid', 'error'], true)
        ));
        return [
            'summary' => [
                'total' => count($rows),
                'valid' => count(array_filter($rows, fn($r) => $r['current_status'] === 'valid')),
                'expiring_soon' => count(array_filter($rows, fn($r) => $r['current_status'] === 'expiring_soon')),
                'expired' => count(array_filter($rows, fn($r) => $r['current_status'] === 'expired')),
                'problem_names' => array_map(fn($r) => $r['name'], $problem),
            ],
            'certificates' => $rows,
        ];
    }

    private function getCron(?string $userId): array
    {
        [$where, $params] = $this->userScope($userId);
        // Latest history row per job, to detect the last run's exit code.
        $rows = $this->db->fetchAllAssociative(
            "SELECT cj.description, cj.expression, cj.last_run_at, cj.next_run_at,
                    h.exit_code AS last_exit_code
             FROM cron_jobs cj
             LEFT JOIN cron_job_history h ON h.id = (
                 SELECT id FROM cron_job_history
                 WHERE cron_job_id = cj.id
                 ORDER BY started_at DESC LIMIT 1
             )
             WHERE cj.is_active = 1 {$where}
             ORDER BY cj.last_run_at DESC",
            $params
        );

        $failed = array_values(array_filter(
            $rows,
            fn($r) => $r['last_exit_code'] !== null && (int) $r['last_exit_code'] !== 0
        ));
        return [
            'summary' => [
                'total' => count($rows),
                'failed' => count($failed),
                'failed_names' => array_map(fn($r) => $r['description'] ?: '(ohne Namen)', $failed),
            ],
            'jobs' => $rows,
        ];
    }

    // ------------------------------------------------------------------
    // Alerts — the noteworthy problems, ranked for a voice summary
    // ------------------------------------------------------------------

    private function buildAlerts(array $server, array $docker, array $services): array
    {
        $alerts = [];

        foreach ($server['disks'] as $disk) {
            if ($disk['percent'] >= 90) {
                $alerts[] = ['level' => 'critical', 'message' => "Festplatte {$disk['mount']} ist zu {$disk['percent']}% voll"];
            }
        }
        if ($server['memory']['percent'] >= 90) {
            $alerts[] = ['level' => 'warning', 'message' => "Arbeitsspeicher ist zu {$server['memory']['percent']}% belegt"];
        }
        if ($docker['available'] && $docker['summary']['unhealthy'] > 0) {
            $alerts[] = ['level' => 'critical', 'message' => "{$docker['summary']['unhealthy']} Container sind ungesund"];
        }
        if ($services['uptime']['summary']['down'] > 0) {
            $names = implode(', ', $services['uptime']['summary']['down_names']);
            $alerts[] = ['level' => 'critical', 'message' => "Offline: {$names}"];
        }
        foreach ($services['ssl']['summary']['problem_names'] as $name) {
            $alerts[] = ['level' => 'warning', 'message' => "SSL-Zertifikat Problem: {$name}"];
        }
        if ($services['cron']['summary']['failed'] > 0) {
            $names = implode(', ', $services['cron']['summary']['failed_names']);
            $alerts[] = ['level' => 'warning', 'message' => "Cron fehlgeschlagen: {$names}"];
        }

        return $alerts;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Build a `AND user_id = ?` clause, or an empty clause when aggregating.
     *
     * @return array{0:string,1:array}
     */
    private function userScope(?string $userId): array
    {
        if ($userId === null) {
            return ['', []];
        }
        return ['AND user_id = ?', [$userId]];
    }

    private function labelValue(string $labels, string $key): ?string
    {
        // `docker ps` renders labels as "k1=v1,k2=v2"
        foreach (explode(',', $labels) as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            if ($k === $key) {
                return $v;
            }
        }
        return null;
    }

    private function parsePercent(string $value): ?float
    {
        $value = trim(str_replace('%', '', $value));
        return is_numeric($value) ? (float) $value : null;
    }

    private function dockerAvailable(): bool
    {
        $out = $this->execDocker('version --format "{{.Server.Version}}"');
        return $out !== '' && stripos($out, 'Cannot connect') === false
            && stripos($out, 'error') === false;
    }

    /**
     * Run a command against the LOCAL Docker daemon. No host selection — this
     * intentionally cannot reach user-configured remote hosts.
     */
    private function execDocker(string $command): string
    {
        return trim((string) shell_exec("docker {$command} 2>&1"));
    }

    /**
     * Execute a command on the local host. When running inside a container,
     * chroot into the host via a short-lived privileged helper so host-level
     * metrics (not the container's) are returned. Mirrors ServerController.
     */
    private function execOnHost(string $command): string
    {
        if (!$this->isInContainer()) {
            return shell_exec($command . ' 2>&1') ?: '';
        }

        if (!file_exists('/var/run/docker.sock')) {
            return '';
        }

        $escapedCmd = str_replace("'", "'\\''", $command);
        $dockerCmd = 'docker run --rm --privileged --pid=host --network=host ' .
            "-v /:/host alpine:latest chroot /host sh -c '{$escapedCmd}' 2>&1";

        return shell_exec($dockerCmd) ?: '';
    }

    private function isInContainer(): bool
    {
        if (file_exists('/.dockerenv')) {
            return true;
        }
        if (file_exists('/proc/1/cgroup')) {
            $cgroup = (string) file_get_contents('/proc/1/cgroup');
            if (str_contains($cgroup, 'docker') || str_contains($cgroup, 'kubepods')) {
                return true;
            }
        }
        return false;
    }
}
