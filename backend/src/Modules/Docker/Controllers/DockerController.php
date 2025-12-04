<?php

declare(strict_types=1);

namespace App\Modules\Docker\Controllers;

use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DockerController
{
    /**
     * Check if Docker is available
     */
    public function status(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $output = $this->execDocker('version --format json');
            $version = json_decode($output, true);

            return JsonResponse::success([
                'available' => true,
                'version' => $version['Client']['Version'] ?? $version['Version'] ?? 'unknown',
                'apiVersion' => $version['Client']['ApiVersion'] ?? $version['ApiVersion'] ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            return JsonResponse::success([
                'available' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * List all containers
     */
    public function containers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $all = ($params['all'] ?? 'true') === 'true';

        try {
            $format = '{{json .}}';
            $cmd = $all ? "ps -a --format '$format'" : "ps --format '$format'";
            $output = $this->execDocker($cmd);

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

            return JsonResponse::success(['containers' => $containers]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to list containers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get container details
     */
    public function containerDetails(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $containerId = $args['id'] ?? '';

        if (empty($containerId)) {
            throw new ValidationException('Container ID is required');
        }

        // Validate container ID format (alphanumeric)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $containerId)) {
            throw new ValidationException('Invalid container ID format');
        }

        try {
            $output = $this->execDocker("inspect $containerId");
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
        $containerId = $args['id'] ?? '';

        if (empty($containerId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $containerId)) {
            throw new ValidationException('Invalid container ID');
        }

        try {
            $this->execDocker("start $containerId");
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
        $containerId = $args['id'] ?? '';

        if (empty($containerId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $containerId)) {
            throw new ValidationException('Invalid container ID');
        }

        try {
            $this->execDocker("stop $containerId");
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
        $containerId = $args['id'] ?? '';

        if (empty($containerId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $containerId)) {
            throw new ValidationException('Invalid container ID');
        }

        try {
            $this->execDocker("restart $containerId");
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

            $output = $this->execDocker($cmd);

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
        $containerId = $args['id'] ?? '';

        if (empty($containerId) || !preg_match('/^[a-zA-Z0-9_-]+$/', $containerId)) {
            throw new ValidationException('Invalid container ID');
        }

        try {
            $output = $this->execDocker("stats --no-stream --format '{{json .}}' $containerId");
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
        try {
            $output = $this->execDocker("images --format '{{json .}}'");

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

            return JsonResponse::success(['images' => $images]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to list images: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get image details
     */
    public function imageDetails(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $imageId = $args['id'] ?? '';

        if (empty($imageId)) {
            throw new ValidationException('Image ID is required');
        }

        // Allow image names like nginx:latest, sha256:abc123, etc.
        if (!preg_match('/^[a-zA-Z0-9_\/:.-]+$/', $imageId)) {
            throw new ValidationException('Invalid image ID format');
        }

        try {
            $output = $this->execDocker("inspect $imageId");
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
        try {
            $output = $this->execDocker("network ls --format '{{json .}}'");

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

            return JsonResponse::success(['networks' => $networks]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to list networks: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List volumes
     */
    public function volumes(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $output = $this->execDocker("volume ls --format '{{json .}}'");

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

            return JsonResponse::success(['volumes' => $volumes]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to list volumes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get system-wide Docker info
     */
    public function systemInfo(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $output = $this->execDocker('system df --format json');
            $df = json_decode($output, true);

            $infoOutput = $this->execDocker('info --format json');
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
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Failed to get system info: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Execute a Docker command safely
     */
    private function execDocker(string $command): string
    {
        $fullCommand = "docker $command 2>&1";

        $output = shell_exec($fullCommand);

        if ($output === null) {
            throw new \RuntimeException('Failed to execute Docker command');
        }

        // Check for common error patterns
        if (str_contains($output, 'Cannot connect to the Docker daemon') ||
            str_contains($output, 'permission denied') ||
            str_contains($output, 'command not found')) {
            throw new \RuntimeException(trim($output));
        }

        return $output;
    }
}
