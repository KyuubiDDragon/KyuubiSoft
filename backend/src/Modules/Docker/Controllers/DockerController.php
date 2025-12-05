<?php

declare(strict_types=1);

namespace App\Modules\Docker\Controllers;

use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Modules\Docker\Repositories\DockerHostRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

class DockerController
{
    public function __construct(
        private readonly DockerHostRepository $hostRepository
    ) {}

    /**
     * Helper to get route argument
     */
    private function getRouteArg(ServerRequestInterface $request, string $name): ?string
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        return $route ? $route->getArgument($name) : null;
    }

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
        $projectId = $params['project_id'] ?? null;

        if ($grouped) {
            $hosts = $this->hostRepository->findByUserGroupedByProject($userId);
        } else {
            $hosts = $this->hostRepository->findByUser($userId, $projectId);
        }

        // Auto-create default local host if user has no hosts configured
        if (empty($hosts) && !$projectId) {
            $defaultHost = $this->hostRepository->createDefaultForUser($userId);
            $hosts = [$defaultHost];
        }

        return JsonResponse::success(['hosts' => $hosts]);
    }

    /**
     * Get a single Docker host
     */
    public function getHost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $hostId = $this->getRouteArg($request, 'id');

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
            'tls_ca' => $data['tls_ca'] ?? null,
            'tls_cert' => $data['tls_cert'] ?? null,
            'tls_key' => $data['tls_key'] ?? null,
            'portainer_url' => $data['portainer_url'] ?? null,
            'portainer_api_token' => $data['portainer_api_token'] ?? null,
            'portainer_endpoint_id' => isset($data['portainer_endpoint_id']) ? (int) $data['portainer_endpoint_id'] : null,
            'portainer_only' => (int) ($data['portainer_only'] ?? 0),
            'is_active' => 1,
            'is_default' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $host = $this->hostRepository->create($hostData);

        return JsonResponse::success(['host' => $host], 'Host created', 201);
    }

    /**
     * Update a Docker host
     */
    public function updateHost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $hostId = $this->getRouteArg($request, 'id');
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
        if (isset($data['tls_ca'])) {
            $updateData['tls_ca'] = $data['tls_ca'];
        }
        if (isset($data['tls_cert'])) {
            $updateData['tls_cert'] = $data['tls_cert'];
        }
        if (isset($data['tls_key'])) {
            $updateData['tls_key'] = $data['tls_key'];
        }
        if (isset($data['portainer_url'])) {
            $updateData['portainer_url'] = $data['portainer_url'] ?: null;
        }
        if (isset($data['portainer_api_token'])) {
            $updateData['portainer_api_token'] = $data['portainer_api_token'] ?: null;
        }
        if (isset($data['portainer_endpoint_id'])) {
            $updateData['portainer_endpoint_id'] = $data['portainer_endpoint_id'] ? (int) $data['portainer_endpoint_id'] : null;
        }
        if (isset($data['portainer_only'])) {
            $updateData['portainer_only'] = (int) $data['portainer_only'];
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
    public function deleteHost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $hostId = $this->getRouteArg($request, 'id');

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
    public function setDefaultHost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $hostId = $this->getRouteArg($request, 'id');

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
    public function testHostConnection(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $hostId = $this->getRouteArg($request, 'id');

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
            // Use Portainer API if portainer_only mode is enabled
            if ($this->isPortainerOnlyMode($host)) {
                $version = $this->fetchPortainerDockerVersion($host);
                return JsonResponse::success([
                    'available' => true,
                    'host_id' => $host['id'] ?? null,
                    'host_name' => $host['name'] ?? 'Lokal',
                    'version' => $version['Version'] ?? 'unknown',
                    'apiVersion' => $version['ApiVersion'] ?? 'unknown',
                    'portainer_mode' => true,
                ]);
            }

            $output = $this->execDockerOnHost($host, 'version --format json');
            $version = json_decode($output, true);

            return JsonResponse::success([
                'available' => true,
                'host_id' => $host['id'] ?? null,
                'host_name' => $host['name'] ?? 'Lokal',
                'version' => $version['Client']['Version'] ?? $version['Version'] ?? 'unknown',
                'apiVersion' => $version['Client']['ApiVersion'] ?? $version['ApiVersion'] ?? 'unknown',
                'portainer_mode' => false,
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
        $grouped = ($params['grouped'] ?? 'false') === 'true';

        try {
            // Use Portainer API if portainer_only mode is enabled
            if ($this->isPortainerOnlyMode($host)) {
                $containers = $this->fetchPortainerContainers($host, $all);
            } else {
                $format = '{{json .}}';
                $cmd = $all ? "ps -a --format '$format'" : "ps --format '$format'";
                $output = $this->execDockerOnHost($host, $cmd);

                $containers = [];
                $lines = array_filter(explode("\n", trim($output)));

                foreach ($lines as $line) {
                    $container = json_decode($line, true);
                    if ($container) {
                        $containerId = $container['ID'] ?? '';

                        // Get labels for stack/project detection
                        $labels = $this->getContainerLabels($host, $containerId);

                        $containers[] = [
                            'id' => $containerId,
                            'name' => ltrim($container['Names'] ?? '', '/'),
                            'image' => $container['Image'] ?? '',
                            'status' => $container['Status'] ?? '',
                            'state' => $container['State'] ?? '',
                            'ports' => $container['Ports'] ?? '',
                            'created' => $container['CreatedAt'] ?? '',
                            'size' => $container['Size'] ?? '',
                            'stack' => $labels['com.docker.compose.project'] ?? null,
                            'service' => $labels['com.docker.compose.service'] ?? null,
                            'labels' => $labels,
                        ];
                    }
                }
            }

            // Group by stack if requested
            if ($grouped) {
                $stacks = [];
                $standalone = [];

                foreach ($containers as $container) {
                    if ($container['stack']) {
                        if (!isset($stacks[$container['stack']])) {
                            $stacks[$container['stack']] = [
                                'name' => $container['stack'],
                                'containers' => [],
                                'running' => 0,
                                'total' => 0,
                            ];
                        }
                        $stacks[$container['stack']]['containers'][] = $container;
                        $stacks[$container['stack']]['total']++;
                        if ($container['state'] === 'running') {
                            $stacks[$container['stack']]['running']++;
                        }
                    } else {
                        $standalone[] = $container;
                    }
                }

                return JsonResponse::success([
                    'stacks' => array_values($stacks),
                    'standalone' => $standalone,
                    'host_id' => $host['id'] ?? null,
                    'host_name' => $host['name'] ?? 'Lokal',
                ]);
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
     * Get container labels
     */
    private function getContainerLabels(array $host, string $containerId): array
    {
        try {
            $output = $this->execDockerOnHost($host, "inspect --format '{{json .Config.Labels}}' $containerId");
            $labels = json_decode(trim($output), true);
            return is_array($labels) ? $labels : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get container details
     */
    public function containerDetails(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $containerId = $this->getRouteArg($request, 'id');

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
    public function startContainer(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $containerId = $this->getRouteArg($request, 'id');

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
    public function stopContainer(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $containerId = $this->getRouteArg($request, 'id');

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
    public function restartContainer(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $containerId = $this->getRouteArg($request, 'id');

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
    public function containerLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $containerId = $this->getRouteArg($request, 'id');
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
    public function containerStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $containerId = $this->getRouteArg($request, 'id');

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
            // Use Portainer API if portainer_only mode is enabled
            if ($this->isPortainerOnlyMode($host)) {
                $images = $this->fetchPortainerImages($host);
            } else {
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
    public function imageDetails(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $imageId = $this->getRouteArg($request, 'id');

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
            // Use Portainer API if portainer_only mode is enabled
            if ($this->isPortainerOnlyMode($host)) {
                $networks = $this->fetchPortainerNetworks($host);
            } else {
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
            // Use Portainer API if portainer_only mode is enabled
            if ($this->isPortainerOnlyMode($host)) {
                $volumes = $this->fetchPortainerVolumes($host);
            } else {
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

        // No host configured - create a default local host automatically
        return $this->hostRepository->createDefaultForUser($userId);
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
                if (!empty($host['tls_ca'])) {
                    $caPath = $this->writeTempCert($host['tls_ca'], 'ca');
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

    // ========================================================================
    // Stack / Compose Methods
    // ========================================================================

    /**
     * Get compose file for a stack
     */
    public function getStackCompose(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $stackName = $this->getRouteArg($request, 'name');
        $userId = $request->getAttribute('user_id');

        if (empty($stackName) || !preg_match('/^[a-zA-Z0-9_-]+$/', $stackName)) {
            throw new ValidationException('Invalid stack name');
        }

        try {
            // Find a container in the stack to get compose file path
            $output = $this->execDockerOnHost($host, "ps -a --filter 'label=com.docker.compose.project=$stackName' --format '{{.ID}}'");
            $containerIds = array_filter(explode("\n", trim($output)));

            if (empty($containerIds)) {
                return JsonResponse::error('Stack not found', 404);
            }

            // Get labels from first container
            $containerId = $containerIds[0];
            $labels = $this->getContainerLabels($host, $containerId);

            $configFiles = $labels['com.docker.compose.project.config_files'] ?? null;
            $workingDir = $labels['com.docker.compose.project.working_dir'] ?? null;

            if (!$configFiles) {
                return JsonResponse::error('Compose file path not found in labels', 404);
            }

            // Parse first config file path (may be comma-separated)
            $paths = explode(',', $configFiles);
            $composePath = trim($paths[0]);

            // Check if this is a Portainer-managed stack (path contains /data/compose/)
            // If Portainer is configured, try Portainer FIRST for these stacks
            $isPortainerPath = str_contains($composePath, '/data/compose/');
            $fromPortainer = false;
            $content = null;

            if ($isPortainerPath && !empty($host['portainer_url']) && !empty($host['portainer_api_token'])) {
                // Try Portainer first for Portainer-managed stacks
                $portainerContent = $this->getComposeFromPortainer($host, $stackName, $userId);
                if ($portainerContent) {
                    $content = $portainerContent;
                    $fromPortainer = true;
                }
            }

            // Read compose file content (only works for local host)
            if ($content === null && ($host['type'] !== 'socket' || !empty($host['tcp_host']))) {
                // Not local and no Portainer content - try Portainer anyway as last resort
                if (!empty($host['portainer_url']) && !empty($host['portainer_api_token'])) {
                    $portainerContent = $this->getComposeFromPortainer($host, $stackName, $userId);
                    if ($portainerContent) {
                        $content = $portainerContent;
                        $fromPortainer = true;
                    }
                }

                if ($content === null) {
                    return JsonResponse::success([
                        'stack' => $stackName,
                        'path' => $configFiles,
                        'working_dir' => $workingDir,
                        'content' => null,
                        'readable' => false,
                        'message' => 'Compose file can only be read on local Docker host. Configure Portainer integration to fetch from Portainer API.',
                    ]);
                }
            }

            // Try to read file from filesystem if not already fetched from Portainer
            if ($content === null) {
                $content = $this->readHostFile($host, $composePath);
            }

            // If not found, try common compose filenames in working directory
            if ($content === null && $workingDir) {
                $commonComposeNames = [
                    'docker-compose.yml',
                    'docker-compose.yaml',
                    'docker-compose.prod.yml',
                    'docker-compose.prod.yaml',
                    'docker-compose.production.yml',
                    'compose.yml',
                    'compose.yaml',
                ];

                foreach ($commonComposeNames as $filename) {
                    $altPath = $workingDir . '/' . $filename;
                    $altContent = $this->readHostFile($host, $altPath);
                    if ($altContent !== null) {
                        $content = $altContent;
                        $composePath = $altPath;
                        break;
                    }
                }
            }

            // If still not found, try Portainer as final fallback
            if ($content === null && !empty($host['portainer_url']) && !empty($host['portainer_api_token'])) {
                $portainerContent = $this->getComposeFromPortainer($host, $stackName, $userId);
                if ($portainerContent) {
                    $content = $portainerContent;
                    $fromPortainer = true;
                }
            }

            if ($content === null) {
                // List available files in the directory for debugging
                $availableFiles = $this->listHostDirectory($host, $workingDir);

                return JsonResponse::success([
                    'stack' => $stackName,
                    'path' => $composePath,
                    'working_dir' => $workingDir,
                    'content' => null,
                    'readable' => false,
                    'available_files' => $availableFiles,
                    'message' => 'Compose file not found. Configure Portainer integration to fetch from Portainer API.',
                ]);
            }

            $writable = !$fromPortainer && file_exists($composePath) && is_writable($composePath);

            // Also fetch .env file
            $envContent = null;
            $envPath = $workingDir . '/.env';
            $envSource = null;

            // For Portainer-managed stacks, try to get env from Portainer first
            if ($fromPortainer && !empty($host['portainer_url']) && !empty($host['portainer_api_token'])) {
                $portainerStackId = $this->getPortainerStackId($host, $stackName, $userId);
                if ($portainerStackId) {
                    $envContent = $this->fetchPortainerStackEnv($host, $portainerStackId);
                    if ($envContent) {
                        $envSource = 'portainer';
                    }
                }
            }

            // If no env from Portainer, try filesystem
            if ($envContent === null) {
                $envContent = $this->readHostFile($host, $envPath);
                if ($envContent !== null) {
                    $envSource = 'filesystem';
                }
            }

            return JsonResponse::success([
                'stack' => $stackName,
                'path' => $fromPortainer ? 'portainer://' . $stackName : $composePath,
                'working_dir' => $workingDir,
                'content' => $content,
                'readable' => true,
                'writable' => $writable,
                'source' => $fromPortainer ? 'portainer' : 'filesystem',
                'env_content' => $envContent,
                'env_source' => $envSource,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to get compose file: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update compose file for a stack
     */
    public function updateStackCompose(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $stackName = $this->getRouteArg($request, 'name');

        if (empty($stackName) || !preg_match('/^[a-zA-Z0-9_-]+$/', $stackName)) {
            throw new ValidationException('Invalid stack name');
        }

        // Only allow on local host
        if ($host['type'] !== 'socket' || !empty($host['tcp_host'])) {
            return JsonResponse::error('Compose file can only be updated on local Docker host', 403);
        }

        $body = json_decode((string)$request->getBody(), true);
        $content = $body['content'] ?? null;
        $path = $body['path'] ?? null;

        if (empty($content) || empty($path)) {
            throw new ValidationException('Content and path are required');
        }

        // Validate path is the actual compose file for this stack
        try {
            $output = $this->execDockerOnHost($host, "ps -a --filter 'label=com.docker.compose.project=$stackName' --format '{{.ID}}'");
            $containerIds = array_filter(explode("\n", trim($output)));

            if (empty($containerIds)) {
                return JsonResponse::error('Stack not found', 404);
            }

            $labels = $this->getContainerLabels($host, $containerIds[0]);
            $configFiles = $labels['com.docker.compose.project.config_files'] ?? '';
            $paths = array_map('trim', explode(',', $configFiles));

            if (!in_array($path, $paths)) {
                return JsonResponse::error('Path does not match stack compose file', 403);
            }

            // Try to backup and write (direct or via docker)
            $backupPath = $path . '.backup.' . date('YmdHis');
            $backupSuccess = $this->copyHostFile($host, $path, $backupPath);
            $writeSuccess = $this->writeHostFile($host, $path, $content);

            if (!$writeSuccess) {
                return JsonResponse::error('Compose file is not writable', 403);
            }

            return JsonResponse::success([
                'message' => 'Compose file updated successfully',
                'backup' => $backupSuccess ? $backupPath : null,
                'note' => 'Run "docker compose up -d" to apply changes',
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to update compose file: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get environment variables for a container (parsed)
     */
    public function getContainerEnv(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $containerId = $this->getRouteArg($request, 'id');

        if (empty($containerId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $containerId)) {
            throw new ValidationException('Invalid container ID');
        }

        try {
            $output = $this->execDockerOnHost($host, "inspect --format '{{json .Config.Env}}' $containerId");
            $envArray = json_decode(trim($output), true) ?? [];

            // Parse KEY=VALUE format into structured array
            $envVars = [];
            foreach ($envArray as $env) {
                $pos = strpos($env, '=');
                if ($pos !== false) {
                    $key = substr($env, 0, $pos);
                    $value = substr($env, $pos + 1);
                    $envVars[] = [
                        'key' => $key,
                        'value' => $value,
                        'sensitive' => $this->isSensitiveEnvVar($key),
                    ];
                }
            }

            return JsonResponse::success([
                'container_id' => $containerId,
                'env' => $envVars,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to get environment variables: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check if environment variable name suggests sensitive data
     */
    private function isSensitiveEnvVar(string $key): bool
    {
        $sensitivePatterns = [
            'PASSWORD', 'SECRET', 'KEY', 'TOKEN', 'CREDENTIAL',
            'API_KEY', 'APIKEY', 'AUTH', 'PRIVATE', 'CERT',
        ];

        $keyUpper = strtoupper($key);
        foreach ($sensitivePatterns as $pattern) {
            if (strpos($keyUpper, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    // ========================================================================
    // Quick Deploy (docker run)
    // ========================================================================

    /**
     * Run a new container (docker run)
     */
    public function runContainer(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $body = $request->getParsedBody();

        // Required fields
        $image = $body['image'] ?? null;
        if (empty($image)) {
            throw new ValidationException('Image is required');
        }

        // Validate image name
        if (!preg_match('/^[a-zA-Z0-9._\/:@-]+$/', $image)) {
            throw new ValidationException('Invalid image name');
        }

        // Optional fields
        $name = $body['name'] ?? null;
        $ports = $body['ports'] ?? []; // Array of "host:container" or "host:container/protocol"
        $env = $body['env'] ?? []; // Array of "KEY=VALUE"
        $volumes = $body['volumes'] ?? []; // Array of "host:container" or "host:container:ro"
        $network = $body['network'] ?? null;
        $restart = $body['restart'] ?? 'unless-stopped'; // no, always, unless-stopped, on-failure
        $detach = $body['detach'] ?? true;
        $pull = $body['pull'] ?? 'missing'; // always, missing, never
        $labels = $body['labels'] ?? []; // Array of "key=value"
        $command = $body['command'] ?? null;

        // Build docker run command
        $cmd = 'run';

        if ($detach) {
            $cmd .= ' -d';
        }

        if ($name && preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            $cmd .= ' --name ' . escapeshellarg($name);
        }

        if ($restart && in_array($restart, ['no', 'always', 'unless-stopped', 'on-failure'])) {
            $cmd .= ' --restart ' . $restart;
        }

        if ($pull && in_array($pull, ['always', 'missing', 'never'])) {
            $cmd .= ' --pull ' . $pull;
        }

        // Ports
        foreach ($ports as $port) {
            if (preg_match('/^[\d:\/a-z]+$/i', $port)) {
                $cmd .= ' -p ' . escapeshellarg($port);
            }
        }

        // Environment variables
        foreach ($env as $e) {
            if (is_string($e) && strpos($e, '=') !== false) {
                $cmd .= ' -e ' . escapeshellarg($e);
            }
        }

        // Volumes
        foreach ($volumes as $vol) {
            if (preg_match('/^[a-zA-Z0-9_.\/:-]+$/', $vol)) {
                $cmd .= ' -v ' . escapeshellarg($vol);
            }
        }

        // Network
        if ($network && preg_match('/^[a-zA-Z0-9_-]+$/', $network)) {
            $cmd .= ' --network ' . escapeshellarg($network);
        }

        // Labels
        foreach ($labels as $label) {
            if (is_string($label) && strpos($label, '=') !== false) {
                $cmd .= ' --label ' . escapeshellarg($label);
            }
        }

        // Add image
        $cmd .= ' ' . escapeshellarg($image);

        // Add command if provided
        if ($command) {
            $cmd .= ' ' . $command;
        }

        try {
            $output = $this->execDockerOnHost($host, $cmd);
            $containerId = trim($output);

            return JsonResponse::success([
                'message' => 'Container started successfully',
                'container_id' => $containerId,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to run container: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Pull an image
     */
    public function pullImage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $body = $request->getParsedBody();

        $image = $body['image'] ?? null;
        if (empty($image) || !preg_match('/^[a-zA-Z0-9._\/:@-]+$/', $image)) {
            throw new ValidationException('Valid image name is required');
        }

        try {
            $output = $this->execDockerOnHost($host, 'pull ' . escapeshellarg($image));

            return JsonResponse::success([
                'message' => 'Image pulled successfully',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to pull image: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove a container
     */
    public function removeContainer(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $containerId = $this->getRouteArg($request, 'id');
        $params = $request->getQueryParams();
        $force = ($params['force'] ?? 'false') === 'true';
        $removeVolumes = ($params['volumes'] ?? 'false') === 'true';

        if (empty($containerId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $containerId)) {
            throw new ValidationException('Invalid container ID');
        }

        try {
            $cmd = 'rm';
            if ($force) $cmd .= ' -f';
            if ($removeVolumes) $cmd .= ' -v';
            $cmd .= ' ' . $containerId;

            $this->execDockerOnHost($host, $cmd);

            return JsonResponse::success(['message' => 'Container removed successfully']);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to remove container: ' . $e->getMessage(), 500);
        }
    }

    // ========================================================================
    // Stack Deploy (docker-compose)
    // ========================================================================

    /**
     * Deploy a new stack from compose content
     */
    public function deployStack(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        // Only allow on local host
        if ($host['type'] !== 'socket' || !empty($host['tcp_host'])) {
            return JsonResponse::error('Stack deployment only available on local Docker host', 403);
        }

        $stackName = $body['name'] ?? null;
        $composeContent = $body['compose'] ?? null;
        $envContent = $body['env'] ?? null;

        if (empty($stackName) || !preg_match('/^[a-zA-Z0-9_-]+$/', $stackName)) {
            throw new ValidationException('Valid stack name is required');
        }

        if (empty($composeContent)) {
            throw new ValidationException('Compose content is required');
        }

        // Create stack directory
        $stacksDir = $this->getStacksDirectory($userId);
        $stackDir = $stacksDir . '/' . $stackName;

        if (is_dir($stackDir)) {
            throw new ValidationException('Stack with this name already exists');
        }

        if (!mkdir($stackDir, 0755, true)) {
            return JsonResponse::error('Failed to create stack directory', 500);
        }

        try {
            // Write docker-compose.yml
            $composePath = $stackDir . '/docker-compose.yml';
            file_put_contents($composePath, $composeContent);

            // Write .env if provided
            if ($envContent) {
                $envPath = $stackDir . '/.env';
                file_put_contents($envPath, $envContent);
            }

            // Run docker compose up
            $output = shell_exec("cd " . escapeshellarg($stackDir) . " && docker compose -p " . escapeshellarg($stackName) . " up -d 2>&1");

            return JsonResponse::success([
                'message' => 'Stack deployed successfully',
                'stack' => $stackName,
                'path' => $stackDir,
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            // Cleanup on failure
            $this->removeDirectory($stackDir);
            return JsonResponse::error('Failed to deploy stack: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Start a stack (docker compose up -d)
     */
    public function stackUp(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $stackName = $this->getRouteArg($request, 'name');

        if (empty($stackName) || !preg_match('/^[a-zA-Z0-9_-]+$/', $stackName)) {
            throw new ValidationException('Invalid stack name');
        }

        try {
            $workingDir = $this->getStackWorkingDir($host, $stackName);

            if ($workingDir && is_dir($workingDir)) {
                $output = shell_exec("cd " . escapeshellarg($workingDir) . " && docker compose up -d 2>&1");
            } else {
                // Try to start just by project name
                $output = shell_exec("docker compose -p " . escapeshellarg($stackName) . " up -d 2>&1");
            }

            return JsonResponse::success([
                'message' => 'Stack started successfully',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to start stack: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Stop a stack (docker compose down)
     */
    public function stackDown(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $stackName = $this->getRouteArg($request, 'name');
        $params = $request->getQueryParams();
        $removeVolumes = ($params['volumes'] ?? 'false') === 'true';

        if (empty($stackName) || !preg_match('/^[a-zA-Z0-9_-]+$/', $stackName)) {
            throw new ValidationException('Invalid stack name');
        }

        try {
            $workingDir = $this->getStackWorkingDir($host, $stackName);
            $volumeFlag = $removeVolumes ? ' -v' : '';

            if ($workingDir && is_dir($workingDir)) {
                $output = shell_exec("cd " . escapeshellarg($workingDir) . " && docker compose down{$volumeFlag} 2>&1");
            } else {
                $output = shell_exec("docker compose -p " . escapeshellarg($stackName) . " down{$volumeFlag} 2>&1");
            }

            return JsonResponse::success([
                'message' => 'Stack stopped successfully',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to stop stack: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Restart a stack (docker compose restart)
     */
    public function stackRestart(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $stackName = $this->getRouteArg($request, 'name');

        if (empty($stackName) || !preg_match('/^[a-zA-Z0-9_-]+$/', $stackName)) {
            throw new ValidationException('Invalid stack name');
        }

        try {
            $workingDir = $this->getStackWorkingDir($host, $stackName);

            if ($workingDir && is_dir($workingDir)) {
                $output = shell_exec("cd " . escapeshellarg($workingDir) . " && docker compose restart 2>&1");
            } else {
                $output = shell_exec("docker compose -p " . escapeshellarg($stackName) . " restart 2>&1");
            }

            return JsonResponse::success([
                'message' => 'Stack restarted successfully',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to restart stack: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get stack working directory from container labels
     */
    private function getStackWorkingDir(array $host, string $stackName): ?string
    {
        try {
            $output = $this->execDockerOnHost($host, "ps -a --filter 'label=com.docker.compose.project=$stackName' --format '{{.ID}}'");
            $containerIds = array_filter(explode("\n", trim($output)));

            if (empty($containerIds)) {
                return null;
            }

            $labels = $this->getContainerLabels($host, $containerIds[0]);
            return $labels['com.docker.compose.project.working_dir'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    // ========================================================================
    // Backup & Restore
    // ========================================================================

    /**
     * Backup a stack's .env and docker-compose files
     */
    public function backupStack(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $userId = $request->getAttribute('user_id');
        $stackName = $this->getRouteArg($request, 'name');

        if (empty($stackName) || !preg_match('/^[a-zA-Z0-9_-]+$/', $stackName)) {
            throw new ValidationException('Invalid stack name');
        }

        // Only allow on local host
        if ($host['type'] !== 'socket' || !empty($host['tcp_host'])) {
            return JsonResponse::error('Backup only available on local Docker host', 403);
        }

        try {
            // Get compose file path from labels first
            $output = $this->execDockerOnHost($host, "ps -a --filter 'label=com.docker.compose.project=$stackName' --format '{{.ID}}'");
            $containerIds = array_filter(explode("\n", trim($output)));

            if (empty($containerIds)) {
                return JsonResponse::error('No containers found for stack', 404);
            }

            $labels = $this->getContainerLabels($host, $containerIds[0]);
            $workingDir = $labels['com.docker.compose.project.working_dir'] ?? null;
            $configFiles = $labels['com.docker.compose.project.config_files'] ?? null;

            if (!$workingDir) {
                return JsonResponse::error('Stack working directory not found in container labels', 404);
            }

            // Prepare backup data
            $backupData = [
                'stack_name' => $stackName,
                'working_dir' => $workingDir,
                'backup_date' => date('Y-m-d H:i:s'),
                'files' => [],
            ];

            // Check if this is a Portainer-managed stack (path contains /data/compose/)
            $firstConfigPath = $configFiles ? trim(explode(',', $configFiles)[0]) : '';
            $isPortainerPath = str_contains($firstConfigPath, '/data/compose/');
            $foundComposeFile = false;

            // If Portainer-managed stack and Portainer is configured, try Portainer FIRST
            if ($isPortainerPath && !empty($host['portainer_url']) && !empty($host['portainer_api_token'])) {
                $portainerContent = $this->getComposeFromPortainer($host, $stackName, $userId);
                if ($portainerContent) {
                    $backupData['files'][] = [
                        'name' => 'docker-compose.yml',
                        'path' => 'portainer://' . $stackName,
                        'content' => $portainerContent,
                    ];
                    $foundComposeFile = true;
                }
            }

            // Read compose file(s) - try direct access first, then via docker container
            if (!$foundComposeFile && $configFiles) {
                $paths = explode(',', $configFiles);
                foreach ($paths as $path) {
                    $path = trim($path);
                    $content = $this->readHostFile($host, $path);
                    if ($content !== null) {
                        $backupData['files'][] = [
                            'name' => basename($path),
                            'path' => $path,
                            'content' => $content,
                        ];
                        $foundComposeFile = true;
                    }
                }
            }

            // If no compose file found from labels, try common filenames in working directory
            if (!$foundComposeFile && $workingDir) {
                $commonComposeNames = [
                    'docker-compose.yml',
                    'docker-compose.yaml',
                    'docker-compose.prod.yml',
                    'docker-compose.prod.yaml',
                    'docker-compose.production.yml',
                    'compose.yml',
                    'compose.yaml',
                ];

                foreach ($commonComposeNames as $filename) {
                    $path = $workingDir . '/' . $filename;
                    $content = $this->readHostFile($host, $path);
                    if ($content !== null) {
                        $backupData['files'][] = [
                            'name' => $filename,
                            'path' => $path,
                            'content' => $content,
                        ];
                        $foundComposeFile = true;
                        break; // Only take first found
                    }
                }
            }

            // If still no compose file found, try Portainer as final fallback
            if (!$foundComposeFile && !empty($host['portainer_url']) && !empty($host['portainer_api_token'])) {
                $portainerContent = $this->getComposeFromPortainer($host, $stackName, $userId);
                if ($portainerContent) {
                    $backupData['files'][] = [
                        'name' => 'docker-compose.yml',
                        'path' => 'portainer://' . $stackName,
                        'content' => $portainerContent,
                    ];
                    $foundComposeFile = true;
                }
            }

            // Read .env file if exists
            $envContent = null;
            $envPath = $workingDir . '/.env';

            // For Portainer-managed stacks, try to get env from Portainer first
            if ($isPortainerPath && !empty($host['portainer_url']) && !empty($host['portainer_api_token'])) {
                $portainerStackId = $this->getPortainerStackId($host, $stackName, $userId);
                if ($portainerStackId) {
                    $envContent = $this->fetchPortainerStackEnv($host, $portainerStackId);
                    if ($envContent) {
                        $backupData['files'][] = [
                            'name' => '.env',
                            'path' => 'portainer://' . $stackName . '/.env',
                            'content' => $envContent,
                        ];
                    }
                }
            }

            // If no env from Portainer, try filesystem
            if ($envContent === null) {
                $envContent = $this->readHostFile($host, $envPath);
                if ($envContent !== null) {
                    $backupData['files'][] = [
                        'name' => '.env',
                        'path' => $envPath,
                        'content' => $envContent,
                    ];
                }
            }

            if (empty($backupData['files'])) {
                // List available files for debugging
                $availableFiles = $this->listHostDirectory($host, $workingDir);
                return JsonResponse::error(
                    'No files could be read for backup. Configure Portainer integration to fetch compose files from Portainer API. Available files in directory: ' . implode(', ', $availableFiles ?: ['(empty or inaccessible)']),
                    404
                );
            }

            // Save backup to user's backup directory
            $backupsDir = $this->getBackupsDirectory($userId);
            $backupFileName = $stackName . '_' . date('Y-m-d_His') . '.json';
            $backupPath = $backupsDir . '/' . $backupFileName;

            file_put_contents($backupPath, json_encode($backupData, JSON_PRETTY_PRINT));

            return JsonResponse::success([
                'message' => 'Backup created successfully',
                'backup_file' => $backupFileName,
                'files_count' => count($backupData['files']),
                'files' => array_map(fn($f) => $f['name'], $backupData['files']),
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to create backup: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Read a file from the host filesystem
     * Tries direct access first, then falls back to reading via docker container
     */
    private function readHostFile(array $host, string $path): ?string
    {
        // Try direct access first
        if (file_exists($path) && is_readable($path)) {
            return file_get_contents($path);
        }

        // Fall back to reading via docker container
        try {
            $escapedPath = escapeshellarg($path);
            $parentDir = dirname($path);
            $escapedDir = escapeshellarg($parentDir);

            // Use alpine container to read file from host
            $output = $this->execDockerOnHost(
                $host,
                "run --rm -v {$escapedDir}:{$escapedDir}:ro alpine cat {$escapedPath}"
            );

            return $output !== '' ? $output : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * List files in a host directory
     */
    private function listHostDirectory(array $host, string $path): array
    {
        // Try direct access first
        if (is_dir($path) && is_readable($path)) {
            return array_values(array_filter(scandir($path), fn($f) => $f !== '.' && $f !== '..'));
        }

        // Fall back to listing via docker container
        try {
            $escapedDir = escapeshellarg($path);
            $output = $this->execDockerOnHost(
                $host,
                "run --rm -v {$escapedDir}:{$escapedDir}:ro alpine ls -1 {$escapedDir} 2>/dev/null"
            );

            if ($output === '') {
                return [];
            }

            return array_filter(explode("\n", trim($output)));
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Write a file to the host filesystem
     * Tries direct access first, then falls back to writing via docker container
     */
    private function writeHostFile(array $host, string $path, string $content): bool
    {
        // Try direct access first
        if (file_exists($path) && is_writable($path)) {
            return file_put_contents($path, $content) !== false;
        }

        // Fall back to writing via docker container
        try {
            $parentDir = dirname($path);
            $escapedDir = escapeshellarg($parentDir);
            $escapedPath = escapeshellarg($path);
            $escapedContent = base64_encode($content);

            // Use alpine container to write file to host
            $this->execDockerOnHost(
                $host,
                "run --rm -v {$escapedDir}:{$escapedDir} alpine sh -c 'echo {$escapedContent} | base64 -d > {$escapedPath}'"
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Copy a file on the host filesystem
     * Tries direct access first, then falls back to copying via docker container
     */
    private function copyHostFile(array $host, string $source, string $dest): bool
    {
        // Try direct access first
        if (file_exists($source) && is_readable($source)) {
            return copy($source, $dest);
        }

        // Fall back to copying via docker container
        try {
            $parentDir = dirname($source);
            $escapedDir = escapeshellarg($parentDir);
            $escapedSource = escapeshellarg($source);
            $escapedDest = escapeshellarg($dest);

            // Use alpine container to copy file on host
            $this->execDockerOnHost(
                $host,
                "run --rm -v {$escapedDir}:{$escapedDir} alpine cp {$escapedSource} {$escapedDest}"
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * List all backups for the current user
     */
    public function listBackups(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();
        $stackName = $params['stack'] ?? null;

        try {
            $backupsDir = $this->getBackupsDirectory($userId);
            $backups = [];

            $files = glob($backupsDir . '/*.json');
            foreach ($files as $file) {
                $fileName = basename($file);

                // Filter by stack if provided
                if ($stackName && !str_starts_with($fileName, $stackName . '_')) {
                    continue;
                }

                $data = json_decode(file_get_contents($file), true);
                $backups[] = [
                    'file' => $fileName,
                    'stack_name' => $data['stack_name'] ?? 'unknown',
                    'backup_date' => $data['backup_date'] ?? null,
                    'files_count' => count($data['files'] ?? []),
                    'files' => array_map(fn($f) => $f['name'], $data['files'] ?? []),
                    'size' => filesize($file),
                ];
            }

            // Sort by date descending
            usort($backups, fn($a, $b) => strcmp($b['backup_date'] ?? '', $a['backup_date'] ?? ''));

            return JsonResponse::success(['backups' => $backups]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to list backups: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get backup details
     */
    public function getBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $fileName = $this->getRouteArg($request, 'file');

        if (empty($fileName) || !preg_match('/^[a-zA-Z0-9_.-]+\.json$/', $fileName)) {
            throw new ValidationException('Invalid backup file name');
        }

        try {
            $backupsDir = $this->getBackupsDirectory($userId);
            $backupPath = $backupsDir . '/' . $fileName;

            if (!file_exists($backupPath)) {
                return JsonResponse::error('Backup not found', 404);
            }

            $data = json_decode(file_get_contents($backupPath), true);

            return JsonResponse::success([
                'backup' => $data,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to get backup: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Restore a stack from backup
     */
    public function restoreBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $fileName = $this->getRouteArg($request, 'file');
        $body = $request->getParsedBody();
        $targetDir = $body['target_dir'] ?? null;
        $deploy = $body['deploy'] ?? false;

        if (empty($fileName) || !preg_match('/^[a-zA-Z0-9_.-]+\.json$/', $fileName)) {
            throw new ValidationException('Invalid backup file name');
        }

        try {
            $backupsDir = $this->getBackupsDirectory($userId);
            $backupPath = $backupsDir . '/' . $fileName;

            if (!file_exists($backupPath)) {
                return JsonResponse::error('Backup not found', 404);
            }

            $data = json_decode(file_get_contents($backupPath), true);
            $stackName = $data['stack_name'] ?? 'restored_stack';

            // Determine target directory
            if (!$targetDir) {
                $targetDir = $data['working_dir'] ?? null;
            }

            if (!$targetDir) {
                // Create in user's stacks directory
                $stacksDir = $this->getStacksDirectory($userId);
                $targetDir = $stacksDir . '/' . $stackName . '_restored_' . date('YmdHis');
            }

            // Create directory if it doesn't exist
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true)) {
                    return JsonResponse::error('Failed to create target directory', 500);
                }
            }

            // Restore files
            $restoredFiles = [];
            foreach ($data['files'] ?? [] as $file) {
                $targetPath = $targetDir . '/' . $file['name'];
                file_put_contents($targetPath, $file['content']);
                $restoredFiles[] = $file['name'];
            }

            $result = [
                'message' => 'Backup restored successfully',
                'target_dir' => $targetDir,
                'files_restored' => $restoredFiles,
            ];

            // Deploy if requested
            if ($deploy) {
                $output = shell_exec("cd " . escapeshellarg($targetDir) . " && docker compose -p " . escapeshellarg($stackName) . " up -d 2>&1");
                $result['deploy_output'] = $output;
            }

            return JsonResponse::success($result);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to restore backup: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a backup
     */
    public function deleteBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $fileName = $this->getRouteArg($request, 'file');

        if (empty($fileName) || !preg_match('/^[a-zA-Z0-9_.-]+\.json$/', $fileName)) {
            throw new ValidationException('Invalid backup file name');
        }

        try {
            $backupsDir = $this->getBackupsDirectory($userId);
            $backupPath = $backupsDir . '/' . $fileName;

            if (!file_exists($backupPath)) {
                return JsonResponse::error('Backup not found', 404);
            }

            unlink($backupPath);

            return JsonResponse::success(['message' => 'Backup deleted successfully']);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to delete backup: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's stacks directory
     */
    private function getStacksDirectory(string $userId): string
    {
        $dir = __DIR__ . '/../../../../storage/docker/stacks/' . $userId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Get user's backups directory
     */
    private function getBackupsDirectory(string $userId): string
    {
        $dir = __DIR__ . '/../../../../storage/docker/backups/' . $userId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Recursively remove a directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    // ========================================================================
    // Portainer Integration
    // ========================================================================

    /**
     * Update Portainer configuration for a Docker host
     */
    public function updatePortainerConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $hostId = $this->getRouteArg($request, 'id');

        $body = json_decode((string)$request->getBody(), true);
        $portainerUrl = trim($body['portainer_url'] ?? '', '/');
        $portainerToken = $body['portainer_api_token'] ?? null;
        $portainerEndpointId = $body['portainer_endpoint_id'] ?? null;

        try {
            $host = $this->hostRepository->findById($hostId, $userId);
            if (!$host) {
                return JsonResponse::error('Docker host not found', 404);
            }

            // Test connection if URL and token provided
            if ($portainerUrl && $portainerToken) {
                $testResult = $this->testPortainerConnection($portainerUrl, $portainerToken);
                if (!$testResult['success']) {
                    return JsonResponse::error('Portainer connection failed: ' . $testResult['error'], 400);
                }
            }

            $this->hostRepository->updatePortainerConfig($hostId, $portainerUrl, $portainerToken, $portainerEndpointId);

            return JsonResponse::success(['message' => 'Portainer configuration updated']);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to update Portainer config: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List stacks from Portainer
     */
    public function listPortainerStacks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);

        if (empty($host['portainer_url']) || empty($host['portainer_api_token'])) {
            return JsonResponse::error('Portainer not configured for this host', 400);
        }

        try {
            $stacks = $this->fetchPortainerStacks($host);
            return JsonResponse::success(['stacks' => $stacks]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to fetch Portainer stacks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get stack file from Portainer
     */
    public function getPortainerStackFile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $host = $this->resolveHost($request);
        $stackId = $this->getRouteArg($request, 'stackId');

        if (empty($host['portainer_url']) || empty($host['portainer_api_token'])) {
            return JsonResponse::error('Portainer not configured for this host', 400);
        }

        try {
            $content = $this->fetchPortainerStackFile($host, (int)$stackId);
            return JsonResponse::success([
                'stack_id' => $stackId,
                'content' => $content,
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to fetch stack file: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Link a Docker stack to a Portainer stack ID
     */
    public function linkStackToPortainer(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $host = $this->resolveHost($request);

        $body = json_decode((string)$request->getBody(), true);
        $stackName = $body['stack_name'] ?? null;
        $portainerStackId = $body['portainer_stack_id'] ?? null;

        if (!$stackName || !$portainerStackId) {
            throw new ValidationException('stack_name and portainer_stack_id are required');
        }

        try {
            $this->hostRepository->linkStackToPortainer(
                $userId,
                $host['id'] ?? null,
                $stackName,
                (int)$portainerStackId
            );

            return JsonResponse::success(['message' => 'Stack linked to Portainer']);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to link stack: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Test Portainer connection
     */
    private function testPortainerConnection(string $url, string $token): array
    {
        try {
            $ch = curl_init($url . '/api/status');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $token,
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return ['success' => false, 'error' => $error];
            }

            if ($httpCode !== 200) {
                return ['success' => false, 'error' => 'HTTP ' . $httpCode];
            }

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch stacks from Portainer API
     */
    private function fetchPortainerStacks(array $host): array
    {
        $url = $host['portainer_url'] . '/api/stacks';
        $token = $host['portainer_api_token'];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $token,
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('Curl error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('Portainer API returned HTTP ' . $httpCode);
        }

        $stacks = json_decode($response, true);
        if (!is_array($stacks)) {
            throw new \RuntimeException('Invalid response from Portainer');
        }

        // Filter by endpoint if configured
        if (!empty($host['portainer_endpoint_id'])) {
            $stacks = array_filter($stacks, fn($s) => ($s['EndpointId'] ?? null) == $host['portainer_endpoint_id']);
        }

        return array_map(fn($s) => [
            'id' => $s['Id'],
            'name' => $s['Name'],
            'type' => $s['Type'] ?? 1,
            'status' => $s['Status'] ?? 0,
            'endpoint_id' => $s['EndpointId'] ?? null,
        ], array_values($stacks));
    }

    /**
     * Fetch stack file content from Portainer API
     */
    private function fetchPortainerStackFile(array $host, int $stackId): string
    {
        $url = $host['portainer_url'] . '/api/stacks/' . $stackId . '/file';
        $token = $host['portainer_api_token'];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $token,
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('Curl error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('Portainer API returned HTTP ' . $httpCode);
        }

        $data = json_decode($response, true);
        return $data['StackFileContent'] ?? '';
    }

    /**
     * Fetch stack environment variables from Portainer API
     */
    private function fetchPortainerStackEnv(array $host, int $stackId): ?string
    {
        $url = $host['portainer_url'] . '/api/stacks/' . $stackId;
        $token = $host['portainer_api_token'];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $token,
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);
        $envVars = $data['Env'] ?? [];

        if (empty($envVars)) {
            return null;
        }

        // Convert Portainer env format to .env format
        $envContent = '';
        foreach ($envVars as $env) {
            $name = $env['name'] ?? '';
            $value = $env['value'] ?? '';
            if ($name) {
                // Escape values with special characters
                if (preg_match('/[\s"\'$`\\\\]/', $value)) {
                    $value = '"' . str_replace(['\\', '"', '$', '`'], ['\\\\', '\\"', '\\$', '\\`'], $value) . '"';
                }
                $envContent .= "$name=$value\n";
            }
        }

        return $envContent ?: null;
    }

    /**
     * Get Portainer stack ID by name
     */
    private function getPortainerStackId(array $host, string $stackName, string $userId): ?int
    {
        // First check if we have a mapping
        $mapping = $this->hostRepository->getStackPortainerMapping($userId, $host['id'] ?? null, $stackName);
        if ($mapping) {
            return (int) $mapping['portainer_stack_id'];
        }

        // Try to find stack by name in Portainer
        try {
            $stacks = $this->fetchPortainerStacks($host);
            foreach ($stacks as $stack) {
                if (strtolower($stack['name']) === strtolower($stackName)) {
                    // Auto-link for future use
                    $this->hostRepository->linkStackToPortainer($userId, $host['id'] ?? null, $stackName, $stack['id']);
                    return $stack['id'];
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }

    /**
     * Try to get stack compose content from Portainer as fallback
     */
    private function getComposeFromPortainer(array $host, string $stackName, string $userId): ?string
    {
        if (empty($host['portainer_url']) || empty($host['portainer_api_token'])) {
            return null;
        }

        try {
            // First check if we have a mapping
            $mapping = $this->hostRepository->getStackPortainerMapping($userId, $host['id'] ?? null, $stackName);

            if ($mapping) {
                return $this->fetchPortainerStackFile($host, (int)$mapping['portainer_stack_id']);
            }

            // Try to find stack by name in Portainer
            $stacks = $this->fetchPortainerStacks($host);
            foreach ($stacks as $stack) {
                if (strtolower($stack['name']) === strtolower($stackName)) {
                    // Auto-link for future use
                    $this->hostRepository->linkStackToPortainer($userId, $host['id'] ?? null, $stackName, $stack['id']);
                    return $this->fetchPortainerStackFile($host, $stack['id']);
                }
            }

            return null;
        } catch (\Exception $e) {
            // Silently fail, this is just a fallback
            return null;
        }
    }

    // ========================================================================
    // Portainer-Only Mode - Fetch Docker data via Portainer API
    // ========================================================================

    /**
     * Check if host should use Portainer-only mode
     */
    private function isPortainerOnlyMode(array $host): bool
    {
        return !empty($host['portainer_only']) &&
               !empty($host['portainer_url']) &&
               !empty($host['portainer_api_token']) &&
               !empty($host['portainer_endpoint_id']);
    }

    /**
     * Make a request to Portainer Docker API
     */
    private function portainerDockerRequest(array $host, string $endpoint, string $method = 'GET', ?array $data = null): array
    {
        $endpointId = $host['portainer_endpoint_id'];
        $url = rtrim($host['portainer_url'], '/') . "/api/endpoints/{$endpointId}/docker" . $endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $host['portainer_api_token'],
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Portainer API error: $error");
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException("Portainer API returned HTTP $httpCode");
        }

        return json_decode($response, true) ?: [];
    }

    /**
     * Fetch containers from Portainer API
     */
    private function fetchPortainerContainers(array $host, bool $all = true): array
    {
        $query = $all ? '?all=true' : '';
        $containers = $this->portainerDockerRequest($host, "/containers/json{$query}");

        return array_map(function ($c) {
            $name = $c['Names'][0] ?? '';
            $labels = $c['Labels'] ?? [];

            return [
                'id' => substr($c['Id'] ?? '', 0, 12),
                'name' => ltrim($name, '/'),
                'image' => $c['Image'] ?? '',
                'status' => $c['Status'] ?? '',
                'state' => strtolower($c['State'] ?? ''),
                'ports' => $this->formatPortainerPorts($c['Ports'] ?? []),
                'created' => date('Y-m-d H:i:s', $c['Created'] ?? 0),
                'size' => '',
                'stack' => $labels['com.docker.compose.project'] ?? null,
                'service' => $labels['com.docker.compose.service'] ?? null,
                'labels' => $labels,
            ];
        }, $containers);
    }

    /**
     * Format ports from Portainer API response
     */
    private function formatPortainerPorts(array $ports): string
    {
        $formatted = [];
        foreach ($ports as $port) {
            $public = $port['PublicPort'] ?? null;
            $private = $port['PrivatePort'] ?? null;
            $type = $port['Type'] ?? 'tcp';

            if ($public && $private) {
                $formatted[] = "{$public}->{$private}/{$type}";
            } elseif ($private) {
                $formatted[] = "{$private}/{$type}";
            }
        }
        return implode(', ', $formatted);
    }

    /**
     * Fetch images from Portainer API
     */
    private function fetchPortainerImages(array $host): array
    {
        $images = $this->portainerDockerRequest($host, '/images/json');

        return array_map(function ($img) {
            $repoTags = $img['RepoTags'] ?? ['<none>:<none>'];
            $tag = $repoTags[0] ?? '<none>:<none>';
            [$repo, $tagName] = explode(':', $tag) + ['', 'latest'];

            return [
                'id' => substr($img['Id'] ?? '', 7, 12),
                'repository' => $repo,
                'tag' => $tagName,
                'created' => date('Y-m-d H:i:s', $img['Created'] ?? 0),
                'size' => $this->formatBytes($img['Size'] ?? 0),
                'sizeBytes' => $img['Size'] ?? 0,
            ];
        }, $images);
    }

    /**
     * Fetch networks from Portainer API
     */
    private function fetchPortainerNetworks(array $host): array
    {
        $networks = $this->portainerDockerRequest($host, '/networks');

        return array_map(function ($net) {
            return [
                'id' => substr($net['Id'] ?? '', 0, 12),
                'name' => $net['Name'] ?? '',
                'driver' => $net['Driver'] ?? '',
                'scope' => $net['Scope'] ?? '',
                'internal' => $net['Internal'] ?? false,
                'containers' => count($net['Containers'] ?? []),
            ];
        }, $networks);
    }

    /**
     * Fetch volumes from Portainer API
     */
    private function fetchPortainerVolumes(array $host): array
    {
        $response = $this->portainerDockerRequest($host, '/volumes');
        $volumes = $response['Volumes'] ?? [];

        return array_map(function ($vol) {
            return [
                'name' => $vol['Name'] ?? '',
                'driver' => $vol['Driver'] ?? '',
                'mountpoint' => $vol['Mountpoint'] ?? '',
                'scope' => $vol['Scope'] ?? '',
                'created' => $vol['CreatedAt'] ?? '',
            ];
        }, $volumes);
    }

    /**
     * Fetch Docker info from Portainer API
     */
    private function fetchPortainerDockerInfo(array $host): array
    {
        return $this->portainerDockerRequest($host, '/info');
    }

    /**
     * Fetch Docker version from Portainer API
     */
    private function fetchPortainerDockerVersion(array $host): array
    {
        return $this->portainerDockerRequest($host, '/version');
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }
}
