<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

/**
 * AI Tools Service - Provides system tools for the AI assistant
 */
class AIToolsService
{
    /**
     * Get all available tool definitions for function calling
     */
    public function getToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_docker_containers',
                    'description' => 'Listet alle Docker-Container auf (laufende und gestoppte)',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'all' => [
                                'type' => 'boolean',
                                'description' => 'Wenn true, werden auch gestoppte Container angezeigt',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_docker_container_logs',
                    'description' => 'Holt die letzten Log-Zeilen eines Docker-Containers',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'container' => [
                                'type' => 'string',
                                'description' => 'Container-Name oder ID',
                            ],
                            'lines' => [
                                'type' => 'integer',
                                'description' => 'Anzahl der letzten Zeilen (Standard: 50)',
                            ],
                        ],
                        'required' => ['container'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_system_info',
                    'description' => 'Holt Systeminformationen (CPU, RAM, Disk)',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_running_processes',
                    'description' => 'Listet die Prozesse mit dem hoechsten Ressourcenverbrauch auf',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Maximale Anzahl der Prozesse (Standard: 10)',
                            ],
                            'sort_by' => [
                                'type' => 'string',
                                'enum' => ['cpu', 'memory'],
                                'description' => 'Sortierung nach CPU oder Speicher',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_disk_usage',
                    'description' => 'Zeigt die Festplattennutzung an',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'Pfad zum Pruefen (Standard: /)',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_service_status',
                    'description' => 'Prueft den Status eines Systemdienstes',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'service' => [
                                'type' => 'string',
                                'description' => 'Name des Dienstes (z.B. nginx, mysql, php-fpm)',
                            ],
                        ],
                        'required' => ['service'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_network_info',
                    'description' => 'Zeigt Netzwerkinformationen und offene Ports an',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [],
                        'required' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get tool definitions in Anthropic format
     */
    public function getAnthropicToolDefinitions(): array
    {
        $tools = [];
        foreach ($this->getToolDefinitions() as $tool) {
            $tools[] = [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'],
                'input_schema' => $tool['function']['parameters'],
            ];
        }
        return $tools;
    }

    /**
     * Execute a tool by name
     */
    public function executeTool(string $name, array $arguments = []): array
    {
        return match ($name) {
            'get_docker_containers' => $this->getDockerContainers($arguments['all'] ?? false),
            'get_docker_container_logs' => $this->getDockerContainerLogs(
                $arguments['container'] ?? '',
                $arguments['lines'] ?? 50
            ),
            'get_system_info' => $this->getSystemInfo(),
            'get_running_processes' => $this->getRunningProcesses(
                $arguments['limit'] ?? 10,
                $arguments['sort_by'] ?? 'cpu'
            ),
            'get_disk_usage' => $this->getDiskUsage($arguments['path'] ?? '/'),
            'get_service_status' => $this->getServiceStatus($arguments['service'] ?? ''),
            'get_network_info' => $this->getNetworkInfo(),
            default => ['error' => 'Unknown tool: ' . $name],
        };
    }

    /**
     * Get Docker containers
     */
    private function getDockerContainers(bool $all = false): array
    {
        $flag = $all ? '-a' : '';
        $output = $this->execCommand("docker ps {$flag} --format '{{json .}}'");

        if ($output['error']) {
            return ['error' => $output['error'], 'hint' => 'Docker ist moeglicherweise nicht installiert oder nicht erreichbar'];
        }

        $containers = [];
        $lines = array_filter(explode("\n", $output['output']));

        foreach ($lines as $line) {
            $container = json_decode($line, true);
            if ($container) {
                $containers[] = [
                    'id' => substr($container['ID'] ?? '', 0, 12),
                    'name' => $container['Names'] ?? '',
                    'image' => $container['Image'] ?? '',
                    'status' => $container['Status'] ?? '',
                    'state' => $container['State'] ?? '',
                    'ports' => $container['Ports'] ?? '',
                    'created' => $container['CreatedAt'] ?? '',
                ];
            }
        }

        return [
            'success' => true,
            'count' => count($containers),
            'containers' => $containers,
        ];
    }

    /**
     * Get Docker container logs
     */
    private function getDockerContainerLogs(string $container, int $lines = 50): array
    {
        if (empty($container)) {
            return ['error' => 'Container-Name ist erforderlich'];
        }

        // Sanitize container name (only allow alphanumeric, dash, underscore)
        $container = preg_replace('/[^a-zA-Z0-9_-]/', '', $container);
        $lines = min(max($lines, 1), 500); // Limit between 1 and 500

        $output = $this->execCommand("docker logs --tail {$lines} {$container} 2>&1");

        if ($output['error']) {
            return ['error' => $output['error']];
        }

        return [
            'success' => true,
            'container' => $container,
            'lines' => $lines,
            'logs' => $output['output'],
        ];
    }

    /**
     * Get system information
     */
    private function getSystemInfo(): array
    {
        $info = [
            'hostname' => gethostname(),
            'os' => php_uname('s') . ' ' . php_uname('r'),
            'architecture' => php_uname('m'),
            'php_version' => PHP_VERSION,
        ];

        // CPU info
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match('/model name\s*:\s*(.+)/i', $cpuinfo, $matches);
            $info['cpu_model'] = trim($matches[1] ?? 'Unknown');
            $info['cpu_cores'] = substr_count($cpuinfo, 'processor');
        }

        // Memory info
        if (is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s*(\d+)/', $meminfo, $total);
            preg_match('/MemAvailable:\s*(\d+)/', $meminfo, $available);

            $totalMb = round(($total[1] ?? 0) / 1024);
            $availableMb = round(($available[1] ?? 0) / 1024);
            $usedMb = $totalMb - $availableMb;

            $info['memory'] = [
                'total_mb' => $totalMb,
                'used_mb' => $usedMb,
                'available_mb' => $availableMb,
                'usage_percent' => $totalMb > 0 ? round(($usedMb / $totalMb) * 100, 1) : 0,
            ];
        }

        // Load average
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $info['load_average'] = [
                '1min' => round($load[0], 2),
                '5min' => round($load[1], 2),
                '15min' => round($load[2], 2),
            ];
        }

        // Uptime
        if (is_readable('/proc/uptime')) {
            $uptime = (float) file_get_contents('/proc/uptime');
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            $minutes = floor(($uptime % 3600) / 60);
            $info['uptime'] = "{$days}d {$hours}h {$minutes}m";
        }

        return ['success' => true, 'system' => $info];
    }

    /**
     * Get running processes
     */
    private function getRunningProcesses(int $limit = 10, string $sortBy = 'cpu'): array
    {
        $limit = min(max($limit, 1), 50);
        $sortCol = $sortBy === 'memory' ? 4 : 3; // %MEM or %CPU

        $output = $this->execCommand("ps aux --sort=-" . ($sortBy === 'memory' ? '%mem' : '%cpu') . " | head -n " . ($limit + 1));

        if ($output['error']) {
            return ['error' => $output['error']];
        }

        $lines = array_filter(explode("\n", $output['output']));
        $processes = [];

        foreach (array_slice($lines, 1) as $line) { // Skip header
            $parts = preg_split('/\s+/', trim($line), 11);
            if (count($parts) >= 11) {
                $processes[] = [
                    'user' => $parts[0],
                    'pid' => $parts[1],
                    'cpu_percent' => $parts[2],
                    'mem_percent' => $parts[3],
                    'command' => $parts[10],
                ];
            }
        }

        return [
            'success' => true,
            'sort_by' => $sortBy,
            'count' => count($processes),
            'processes' => $processes,
        ];
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage(string $path = '/'): array
    {
        // Sanitize path
        $path = realpath($path) ?: '/';

        $output = $this->execCommand("df -h " . escapeshellarg($path));

        if ($output['error']) {
            return ['error' => $output['error']];
        }

        $lines = array_filter(explode("\n", $output['output']));

        if (count($lines) < 2) {
            return ['error' => 'Konnte Festplatteninfo nicht lesen'];
        }

        $parts = preg_split('/\s+/', trim($lines[1]));

        return [
            'success' => true,
            'path' => $path,
            'disk' => [
                'filesystem' => $parts[0] ?? '',
                'size' => $parts[1] ?? '',
                'used' => $parts[2] ?? '',
                'available' => $parts[3] ?? '',
                'usage_percent' => $parts[4] ?? '',
                'mounted_on' => $parts[5] ?? '',
            ],
        ];
    }

    /**
     * Get service status
     */
    private function getServiceStatus(string $service): array
    {
        if (empty($service)) {
            return ['error' => 'Service-Name ist erforderlich'];
        }

        // Sanitize service name
        $service = preg_replace('/[^a-zA-Z0-9_-]/', '', $service);

        // Try systemctl first
        $output = $this->execCommand("systemctl is-active " . escapeshellarg($service) . " 2>/dev/null");

        if (!$output['error'] && !empty(trim($output['output']))) {
            $status = trim($output['output']);

            // Get more details
            $details = $this->execCommand("systemctl status " . escapeshellarg($service) . " 2>/dev/null | head -n 5");

            return [
                'success' => true,
                'service' => $service,
                'status' => $status,
                'is_running' => $status === 'active',
                'details' => $details['output'] ?? '',
            ];
        }

        // Fallback: check if process is running
        $output = $this->execCommand("pgrep -x " . escapeshellarg($service) . " 2>/dev/null");

        return [
            'success' => true,
            'service' => $service,
            'status' => empty($output['output']) ? 'not running' : 'running',
            'is_running' => !empty($output['output']),
            'pids' => array_filter(explode("\n", $output['output'] ?? '')),
        ];
    }

    /**
     * Get network information
     */
    private function getNetworkInfo(): array
    {
        $info = [];

        // Get IP addresses
        $output = $this->execCommand("ip -4 addr show 2>/dev/null | grep inet | awk '{print \$2, \$NF}'");
        if (!$output['error']) {
            $info['ip_addresses'] = array_filter(explode("\n", trim($output['output'])));
        }

        // Get listening ports
        $output = $this->execCommand("ss -tlnp 2>/dev/null | tail -n +2 | head -n 20");
        if (!$output['error']) {
            $ports = [];
            foreach (array_filter(explode("\n", $output['output'])) as $line) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 4) {
                    $ports[] = [
                        'state' => $parts[0] ?? '',
                        'local' => $parts[3] ?? '',
                        'process' => $parts[5] ?? '',
                    ];
                }
            }
            $info['listening_ports'] = $ports;
        }

        return ['success' => true, 'network' => $info];
    }

    /**
     * Execute a shell command safely
     */
    private function execCommand(string $command): array
    {
        $output = [];
        $returnCode = 0;

        exec($command . ' 2>&1', $output, $returnCode);

        return [
            'output' => implode("\n", $output),
            'error' => $returnCode !== 0 ? "Command failed with code {$returnCode}" : null,
            'code' => $returnCode,
        ];
    }
}
