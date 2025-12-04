<?php

declare(strict_types=1);

namespace App\Modules\Docker\Controllers;

use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Modules\Docker\Repositories\DockerHostRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DockerController
{
    public function __construct(
        private readonly DockerHostRepository $hostRepository
    ) {}

    // ========================================================================
    // Docker Host Management
    // ========================================================================

    /**
     * List all Docker hosts for the current user
     */
    public function listHosts(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();
        $grouped = ($params['grouped'] ?? 'false') === 'true';

        if ($grouped) {
            $hosts = $this->hostRepository->findByUserGroupedByProject($userId);
        } else {
            $hosts = $this->hostRepository->findByUser($userId);
        }

        return JsonResponse::success(['hosts' => $hosts]);
    }

    /**
     * Get a single Docker host
     */
    public function getHost(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $hostId = $args['id'] ?? '';

        if (empty($hostId)) {
            throw new ValidationException('Host ID is required');
        }

        $host = $this->hostRepository->findById($hostId);

        if (!$host || $host['user_id'] !== $userId) {
            return JsonResponse::error('Docker host not found', 404);
        }

        return JsonResponse::success(['host' => $host]);
    }

    /**
     * Create a new Docker host
     */
    public function createHost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        // Validate required fields
        if (empty($data['name'])) {
            throw new ValidationException('Name is required');
        }

        $type = $data['type'] ?? 'socket';
        if (!in_array($type, ['socket', 'tcp'])) {
            throw new ValidationException('Invalid host type. Must be "socket" or "tcp"');
        }

        if ($type === 'tcp' && empty($data['tcp_host'])) {
            throw new ValidationException('TCP host is required for TCP connections');
        }

        $id = $this->generateUuid();
        $hostData = [
            'id' => $id,
            'user_id' => $userId,
            'project_id' => $data['project_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $type,
            'socket_path' => $type === 'socket' ? ($data['socket_path'] ?? '/var/run/docker.sock') : null,
            'tcp_host' => $type === 'tcp' ? $data['tcp_host'] : null,
            'tcp_port' => $type === 'tcp' ? (int) ($data['tcp_port'] ?? 2375) : null,
            'tls_enabled' => (int) ($data['tls_enabled'] ?? 0),
            'tls_ca_cert' => $data['tls_ca_cert'] ?? null,
            'tls_cert' => $data['tls_cert'] ?? null,
            'tls_key' => $data['tls_key'] ?? null,
            'is_active' => 1,
            'is_default' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $host = $this->hostRepository->create($hostData);

        return JsonResponse::success(['host' => $host], 201);
    }

    /**
     * Update a Docker host
     */
    public function updateHost(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $hostId = $args['id'] ?? '';
        $data = $request->getParsedBody();

        if (empty($hostId)) {
            throw new ValidationException('Host ID is required');
        }

        $host = $this->hostRepository->findById($hostId);

        if (!$host || $host['user_id'] !== $userId) {
            return JsonResponse::error('Docker host not found', 404);
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['project_id'])) {
            $updateData['project_id'] = $data['project_id'] ?: null;
        }
        if (isset($data['type'])) {
            if (!in_array($data['type'], ['socket', 'tcp'])) {
                throw new ValidationException('Invalid host type');
            }
            $updateData['type'] = $data['type'];
        }
        if (isset($data['socket_path'])) {
            $updateData['socket_path'] = $data['socket_path'];
        }
        if (isset($data['tcp_host'])) {
            $updateData['tcp_host'] = $data['tcp_host'];
        }
        if (isset($data['tcp_port'])) {
            $updateData['tcp_port'] = (int) $data['tcp_port'];
        }
        if (isset($data['tls_enabled'])) {
            $updateData['tls_enabled'] = (int) $data['tls_enabled'];
        }
        if (isset($data['tls_ca_cert'])) {
            $updateData['tls_ca_cert'] = $data['tls_ca_cert'];
        }
        if (isset($data['tls_cert'])) {
            $updateData['tls_cert'] = $data['tls_cert'];
        }
        if (isset($data['tls_key'])) {
            $updateData['tls_key'] = $data['tls_key'];
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = (int) $data['is_active'];
        }

        if (!empty($updateData)) {
            $this->hostRepository->update($hostId, $updateData);
        }

        $updatedHost = $this->hostRepository->findById($hostId);

        return JsonResponse::success(['host' => $updatedHost]);
    }

    /**
     * Delete a Docker host
     */
    public function deleteHost(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $hostId = $args['id'] ?? '';

        if (empty($hostId)) {
            throw new ValidationException('Host ID is required');
        }

        $host = $this->hostRepository->findById($hostId);

        if (!$host || $host['user_id'] !== $userId) {
            return JsonResponse::error('Docker host not found', 404);
        }

        $this->hostRepository->delete($hostId);

        return JsonResponse::success(['message' => 'Docker host deleted successfully']);
    }

    /**
     * Set a Docker host as default
     */
    public function setDefaultHost(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $hostId = $args['id'] ?? '';

        if (empty($hostId)) {
            throw new ValidationException('Host ID is required');
        }

        $host = $this->hostRepository->findById($hostId);

        if (!$host || $host['user_id'] !== $userId) {
            return JsonResponse::error('Docker host not found', 404);
        }

        $this->hostRepository->setDefault($userId, $hostId);

        return JsonResponse::success(['message' => 'Default host updated successfully']);
    }

    /**
     * Test connection to a Docker host
     */
    public function testHostConnection(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $hostId = $args['id'] ?? '';

        if (empty($hostId)) {
            throw new ValidationException('Host ID is required');
        }

        $host = $this->hostRepository->findById($hostId);

        if (!$host || $host['user_id'] !== $userId) {
            return JsonResponse::error('Docker host not found', 404);
        }

        try {
            $output = $this->execDockerOnHost($host, 'version --format json');
            $version = json_decode($output, true);

            $infoOutput = $this->execDockerOnHost($host, 'info --format json');
            $info = json_decode($infoOutput, true);

            $connectionInfo = [
                'version' => $version['Client']['Version'] ?? $version['Version'] ?? 'unknown',
                'api_version' => $version['Client']['ApiVersion'] ?? $version['ApiVersion'] ?? 'unknown',
                'containers' => $info['Containers'] ?? 0,
                'images' => $info['Images'] ?? 0,
            ];

            $this->hostRepository->updateConnectionStatus($hostId, 'connected', null, $connectionInfo);

            return JsonResponse::success([
                'connected' => true,
                'info' => $connectionInfo,
            ]);
        } catch (\Exception $e) {
            $this->hostRepository->updateConnectionStatus($hostId, 'error', $e->getMessage());

            return JsonResponse::success([
                'connected' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ========================================================================
    // Docker Operations (with host selection)
    // ========================================================================

    /**
     * Check if Docker is available on a host
     */
    public function status(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);

        try {
            $output = $this->execDockerOnHost($host, 'version --format json');
            $version = json_decode($output, true);

            return JsonResponse::success([
                'available' => true,
                'host_id' => $host['id'] ?? null,
                'host_name' => $host['name'] ?? 'Lokal',
                'version' => $version['Client']['Version'] ?? $version['Version'] ?? 'unknown',
                'apiVersion' => $version['Client']['ApiVersion'] ?? $version['ApiVersion'] ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            return JsonResponse::success([
                'available' => false,
                'host_id' => $host['id'] ?? null,
                'host_name' => $host['name'] ?? 'Lokal',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * List all containers on a host
     */
    public function containers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $params = $request->getQueryParams();
        $all = ($params['all'] ?? 'true') === 'true';

        try {
            $format = '{{json .}}';
            $cmd = $all ? "ps -a --format '$format'" : "ps --format '$format'";
            $output = $this->execDockerOnHost($host, $cmd);

            $containers = [];
            $lines = array_filter(explode("\n", trim($output)));

            foreach ($lines as $line) {
                $container = json_decode($line, true);
                if ($container) {
                    $containers[] = [
                        'id' => $container['ID'] ?? '',
                        'name' => ltrim($container['Names'] ?? '', '/'),
                        'image' => $container['Image'] ?? '',
                        'status' => $container['Status'] ?? '',
                        'state' => $container['State'] ?? '',
                        'ports' => $container['Ports'] ?? '',
                        'created' => $container['CreatedAt'] ?? '',
                        'size' => $container['Size'] ?? '',
                    ];
                }
            }

            return JsonResponse::success([
                'containers' => $containers,
                'host_id' => $host['id'] ?? null,
                'host_name' => $host['name'] ?? 'Lokal',
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to list containers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get container details
     */
    public function containerDetails(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $containerId = $args['id'] ?? '';

        if (empty($containerId)) {
            throw new ValidationException('Container ID is required');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $containerId)) {
            throw new ValidationException('Invalid container ID format');
        }

        try {
            $output = $this->execDockerOnHost($host, "inspect $containerId");
            $details = json_decode($output, true);

            if (empty($details) || !isset($details[0])) {
                return JsonResponse::error('Container not found', 404);
            }

            $container = $details[0];

            return JsonResponse::success([
                'id' => $container['Id'] ?? '',
                'name' => ltrim($container['Name'] ?? '', '/'),
                'image' => $container['Config']['Image'] ?? '',
                'created' => $container['Created'] ?? '',
                'state' => [
                    'status' => $container['State']['Status'] ?? '',
                    'running' => $container['State']['Running'] ?? false,
                    'paused' => $container['State']['Paused'] ?? false,
                    'restarting' => $container['State']['Restarting'] ?? false,
                    'startedAt' => $container['State']['StartedAt'] ?? '',
                    'finishedAt' => $container['State']['FinishedAt'] ?? '',
                ],
                'config' => [
                    'hostname' => $container['Config']['Hostname'] ?? '',
                    'env' => $container['Config']['Env'] ?? [],
                    'cmd' => $container['Config']['Cmd'] ?? [],
                    'workdir' => $container['Config']['WorkingDir'] ?? '',
                    'labels' => $container['Config']['Labels'] ?? [],
                ],
                'network' => [
                    'ports' => $container['NetworkSettings']['Ports'] ?? [],
                    'networks' => array_keys($container['NetworkSettings']['Networks'] ?? []),
                    'ipAddress' => $container['NetworkSettings']['IPAddress'] ?? '',
                ],
                'mounts' => array_map(function ($mount) {
                    return [
                        'type' => $mount['Type'] ?? '',
                        'source' => $mount['Source'] ?? '',
                        'destination' => $mount['Destination'] ?? '',
                        'mode' => $mount['Mode'] ?? '',
                        'rw' => $mount['RW'] ?? false,
                    ];
                }, $container['Mounts'] ?? []),
                'restartPolicy' => $container['HostConfig']['RestartPolicy'] ?? [],
                'host_id' => $host['id'] ?? null,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to get container details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Start a container
     */
    public function startContainer(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $containerId = $args['id'] ?? '';

        if (empty($containerId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $containerId)) {
            throw new ValidationException('Invalid container ID');
        }

        try {
            $this->execDockerOnHost($host, "start $containerId");
            return JsonResponse::success(['message' => 'Container started successfully']);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to start container: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Stop a container
     */
    public function stopContainer(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $containerId = $args['id'] ?? '';

        if (empty($containerId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $containerId)) {
            throw new ValidationException('Invalid container ID');
        }

        try {
            $this->execDockerOnHost($host, "stop $containerId");
            return JsonResponse::success(['message' => 'Container stopped successfully']);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to stop container: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Restart a container
     */
    public function restartContainer(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $containerId = $args['id'] ?? '';

        if (empty($containerId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $containerId)) {
            throw new ValidationException('Invalid container ID');
        }

        try {
            $this->execDockerOnHost($host, "restart $containerId");
            return JsonResponse::success(['message' => 'Container restarted successfully']);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to restart container: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get container logs
     */
    public function containerLogs(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $containerId = $args['id'] ?? '';
        $params = $request->getQueryParams();
        $tail = min((int) ($params['tail'] ?? 100), 1000);
        $since = $params['since'] ?? '';

        if (empty($containerId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $containerId)) {
            throw new ValidationException('Invalid container ID');
        }

        try {
            $cmd = "logs --tail $tail --timestamps";
            if ($since && preg_match('/^\d+[smhd]$/', $since)) {
                $cmd .= " --since $since";
            }
            $cmd .= " $containerId 2>&1";

            $output = $this->execDockerOnHost($host, $cmd);

            return JsonResponse::success([
                'logs' => $output,
                'tail' => $tail,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to get container logs: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get container stats
     */
    public function containerStats(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $containerId = $args['id'] ?? '';

        if (empty($containerId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $containerId)) {
            throw new ValidationException('Invalid container ID');
        }

        try {
            $output = $this->execDockerOnHost($host, "stats --no-stream --format '{{json .}}' $containerId");
            $stats = json_decode(trim($output), true);

            if (!$stats) {
                return JsonResponse::error('Failed to parse container stats', 500);
            }

            return JsonResponse::success([
                'containerId' => $stats['ID'] ?? '',
                'name' => $stats['Name'] ?? '',
                'cpu' => $stats['CPUPerc'] ?? '0%',
                'memory' => [
                    'usage' => $stats['MemUsage'] ?? '',
                    'percent' => $stats['MemPerc'] ?? '0%',
                ],
                'network' => [
                    'io' => $stats['NetIO'] ?? '',
                ],
                'block' => [
                    'io' => $stats['BlockIO'] ?? '',
                ],
                'pids' => $stats['PIDs'] ?? '0',
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to get container stats: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List all images
     */
    public function images(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);

        try {
            $output = $this->execDockerOnHost($host, "images --format '{{json .}}'");

            $images = [];
            $lines = array_filter(explode("\n", trim($output)));

            foreach ($lines as $line) {
                $image = json_decode($line, true);
                if ($image) {
                    $images[] = [
                        'id' => $image['ID'] ?? '',
                        'repository' => $image['Repository'] ?? '',
                        'tag' => $image['Tag'] ?? '',
                        'created' => $image['CreatedAt'] ?? $image['CreatedSince'] ?? '',
                        'size' => $image['Size'] ?? '',
                    ];
                }
            }

            return JsonResponse::success([
                'images' => $images,
                'host_id' => $host['id'] ?? null,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to list images: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get image details
     */
    public function imageDetails(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $imageId = $args['id'] ?? '';

        if (empty($imageId)) {
            throw new ValidationException('Image ID is required');
        }

        if (!preg_match('/^[a-zA-Z0-9_\/:.-]+$/', $imageId)) {
            throw new ValidationException('Invalid image ID format');
        }

        try {
            $output = $this->execDockerOnHost($host, "inspect $imageId");
            $details = json_decode($output, true);

            if (empty($details) || !isset($details[0])) {
                return JsonResponse::error('Image not found', 404);
            }

            $image = $details[0];

            return JsonResponse::success([
                'id' => $image['Id'] ?? '',
                'repoTags' => $image['RepoTags'] ?? [],
                'repoDigests' => $image['RepoDigests'] ?? [],
                'created' => $image['Created'] ?? '',
                'size' => $image['Size'] ?? 0,
                'virtualSize' => $image['VirtualSize'] ?? 0,
                'architecture' => $image['Architecture'] ?? '',
                'os' => $image['Os'] ?? '',
                'config' => [
                    'env' => $image['Config']['Env'] ?? [],
                    'cmd' => $image['Config']['Cmd'] ?? [],
                    'entrypoint' => $image['Config']['Entrypoint'] ?? [],
                    'workdir' => $image['Config']['WorkingDir'] ?? '',
                    'exposedPorts' => array_keys($image['Config']['ExposedPorts'] ?? []),
                    'volumes' => array_keys($image['Config']['Volumes'] ?? []),
                    'labels' => $image['Config']['Labels'] ?? [],
                ],
                'layers' => count($image['RootFS']['Layers'] ?? []),
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to get image details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List networks
     */
    public function networks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);

        try {
            $output = $this->execDockerOnHost($host, "network ls --format '{{json .}}'");

            $networks = [];
            $lines = array_filter(explode("\n", trim($output)));

            foreach ($lines as $line) {
                $network = json_decode($line, true);
                if ($network) {
                    $networks[] = [
                        'id' => $network['ID'] ?? '',
                        'name' => $network['Name'] ?? '',
                        'driver' => $network['Driver'] ?? '',
                        'scope' => $network['Scope'] ?? '',
                    ];
                }
            }

            return JsonResponse::success([
                'networks' => $networks,
                'host_id' => $host['id'] ?? null,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to list networks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List volumes
     */
    public function volumes(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);

        try {
            $output = $this->execDockerOnHost($host, "volume ls --format '{{json .}}'");

            $volumes = [];
            $lines = array_filter(explode("\n", trim($output)));

            foreach ($lines as $line) {
                $volume = json_decode($line, true);
                if ($volume) {
                    $volumes[] = [
                        'name' => $volume['Name'] ?? '',
                        'driver' => $volume['Driver'] ?? '',
                        'mountpoint' => $volume['Mountpoint'] ?? '',
                        'scope' => $volume['Scope'] ?? '',
                    ];
                }
            }

            return JsonResponse::success([
                'volumes' => $volumes,
                'host_id' => $host['id'] ?? null,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to list volumes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get system-wide Docker info
     */
    public function systemInfo(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);

        try {
            $output = $this->execDockerOnHost($host, 'system df --format json');
            $df = json_decode($output, true);

            $infoOutput = $this->execDockerOnHost($host, 'info --format json');
            $info = json_decode($infoOutput, true);

            return JsonResponse::success([
                'containers' => [
                    'total' => $info['Containers'] ?? 0,
                    'running' => $info['ContainersRunning'] ?? 0,
                    'paused' => $info['ContainersPaused'] ?? 0,
                    'stopped' => $info['ContainersStopped'] ?? 0,
                ],
                'images' => $info['Images'] ?? 0,
                'serverVersion' => $info['ServerVersion'] ?? '',
                'storageDriver' => $info['Driver'] ?? '',
                'operatingSystem' => $info['OperatingSystem'] ?? '',
                'architecture' => $info['Architecture'] ?? '',
                'cpus' => $info['NCPU'] ?? 0,
                'memory' => $info['MemTotal'] ?? 0,
                'diskUsage' => $df,
                'host_id' => $host['id'] ?? null,
                'host_name' => $host['name'] ?? 'Lokal',
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to get system info: ' . $e->getMessage(), 500);
        }
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Resolve which Docker host to use from request
     */
    private function resolveHost(ServerRequestInterface $request): array
    {
        $params = $request->getQueryParams();
        $hostId = $params['host_id'] ?? null;
        $userId = $request->getAttribute('user_id');

        // If specific host requested
        if ($hostId) {
            $host = $this->hostRepository->findById($hostId);
            if ($host && $host['user_id'] === $userId && $host['is_active']) {
                return $host;
            }
        }

        // Try to get default host
        $defaultHost = $this->hostRepository->findDefault($userId);
        if ($defaultHost) {
            return $defaultHost;
        }

        // Fall back to local socket (no host configured)
        return [
            'id' => null,
            'name' => 'Lokal',
            'type' => 'socket',
            'socket_path' => '/var/run/docker.sock',
            'tcp_host' => null,
            'tcp_port' => null,
            'tls_enabled' => false,
        ];
    }

    /**
     * Execute a Docker command on a specific host
     */
    private function execDockerOnHost(array $host, string $command): string
    {
        $dockerCmd = 'docker';

        // Build host connection argument
        if ($host['type'] === 'tcp' && !empty($host['tcp_host'])) {
            $port = $host['tcp_port'] ?? 2375;
            $protocol = $host['tls_enabled'] ? 'tcp' : 'tcp';
            $dockerCmd .= " -H {$protocol}://{$host['tcp_host']}:{$port}";

            // Add TLS options if enabled
            if ($host['tls_enabled']) {
                $dockerCmd .= ' --tls';
                if (!empty($host['tls_ca_cert'])) {
                    $caPath = $this->writeTempCert($host['tls_ca_cert'], 'ca');
                    $dockerCmd .= " --tlscacert=$caPath";
                }
                if (!empty($host['tls_cert'])) {
                    $certPath = $this->writeTempCert($host['tls_cert'], 'cert');
                    $dockerCmd .= " --tlscert=$certPath";
                }
                if (!empty($host['tls_key'])) {
                    $keyPath = $this->writeTempCert($host['tls_key'], 'key');
                    $dockerCmd .= " --tlskey=$keyPath";
                }
            }
        } elseif (!empty($host['socket_path']) && $host['socket_path'] !== '/var/run/docker.sock') {
            $socketPath = escapeshellarg($host['socket_path']);
            $dockerCmd .= " -H unix://$socketPath";
        }

        $fullCommand = "$dockerCmd $command 2>&1";
        $output = shell_exec($fullCommand);

        if ($output === null) {
            throw new \RuntimeException('Failed to execute Docker command');
        }

        // Check for common error patterns
        if (str_contains($output, 'Cannot connect to the Docker daemon') ||
            str_contains($output, 'permission denied') ||
            str_contains($output, 'command not found') ||
            str_contains($output, 'connection refused')) {
            throw new \RuntimeException(trim($output));
        }

        return $output;
    }

    /**
     * Write TLS certificate to temporary file
     */
    private function writeTempCert(string $content, string $prefix): string
    {
        $path = sys_get_temp_dir() . "/docker_{$prefix}_" . md5($content) . '.pem';
        if (!file_exists($path)) {
            file_put_contents($path, $content);
            chmod($path, 0600);
        }
        return $path;
    }

    /**
     * Generate UUID
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
