<?php

declare(strict_types=1);

namespace App\Modules\Docker\Repositories;

use Doctrine\DBAL\Connection;

class DockerHostRepository
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function findById(string $id, ?string $userId = null): ?array
    {
        $sql = 'SELECT dh.*, p.name as project_name, p.color as project_color
                FROM docker_hosts dh
                LEFT JOIN projects p ON dh.project_id = p.id
                WHERE dh.id = ?';
        $params = [$id];

        if ($userId !== null) {
            $sql .= ' AND dh.user_id = ?';
            $params[] = $userId;
        }

        $result = $this->db->fetchAssociative($sql, $params);

        return $result ?: null;
    }

    public function findByUser(string $userId, ?string $projectId = null): array
    {
        $sql = 'SELECT dh.*, p.name as project_name, p.color as project_color
                FROM docker_hosts dh
                LEFT JOIN projects p ON dh.project_id = p.id
                WHERE dh.user_id = ?';
        $params = [$userId];

        if ($projectId !== null) {
            $sql .= ' AND dh.project_id = ?';
            $params[] = $projectId;
        }

        $sql .= ' ORDER BY dh.is_default DESC, p.name ASC, dh.name ASC';

        return $this->db->fetchAllAssociative($sql, $params);
    }

    public function findByUserGroupedByProject(string $userId): array
    {
        $hosts = $this->findByUser($userId);

        $grouped = [
            'no_project' => [
                'project_id' => null,
                'project_name' => 'Ohne Projekt',
                'project_color' => '#6b7280',
                'hosts' => [],
            ],
        ];

        foreach ($hosts as $host) {
            if ($host['project_id']) {
                if (!isset($grouped[$host['project_id']])) {
                    $grouped[$host['project_id']] = [
                        'project_id' => $host['project_id'],
                        'project_name' => $host['project_name'],
                        'project_color' => $host['project_color'],
                        'hosts' => [],
                    ];
                }
                $grouped[$host['project_id']]['hosts'][] = $host;
            } else {
                $grouped['no_project']['hosts'][] = $host;
            }
        }

        // Remove empty groups
        return array_filter($grouped, fn($g) => !empty($g['hosts']));
    }

    public function findDefault(string $userId): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM docker_hosts WHERE user_id = ? AND is_default = 1 AND is_active = 1 LIMIT 1',
            [$userId]
        );

        return $result ?: null;
    }

    public function create(array $data): array
    {
        $this->db->insert('docker_hosts', $data);
        return $this->findById($data['id']);
    }

    public function update(string $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('docker_hosts', $data, ['id' => $id]) > 0;
    }

    public function delete(string $id): bool
    {
        return $this->db->delete('docker_hosts', ['id' => $id]) > 0;
    }

    public function setDefault(string $userId, string $hostId): bool
    {
        // First, unset all defaults for user
        $this->db->executeStatement(
            'UPDATE docker_hosts SET is_default = 0 WHERE user_id = ?',
            [$userId]
        );

        // Then set the new default
        return $this->db->update(
            'docker_hosts',
            ['is_default' => 1],
            ['id' => $hostId, 'user_id' => $userId]
        ) > 0;
    }

    public function updateConnectionStatus(string $id, string $status, ?string $error = null, ?array $info = null): bool
    {
        $data = [
            'connection_status' => $status,
            'last_error' => $error,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($status === 'connected') {
            $data['last_connected_at'] = date('Y-m-d H:i:s');
            if ($info) {
                $data['docker_version'] = $info['version'] ?? null;
                $data['api_version'] = $info['api_version'] ?? null;
                $data['containers_count'] = $info['containers'] ?? 0;
                $data['images_count'] = $info['images'] ?? 0;
            }
        }

        return $this->db->update('docker_hosts', $data, ['id' => $id]) > 0;
    }

    public function createDefaultForUser(string $userId): array
    {
        $id = $this->generateUuid();
        $data = [
            'id' => $id,
            'user_id' => $userId,
            'name' => 'Lokaler Docker',
            'description' => 'Standard Docker-Socket',
            'type' => 'socket',
            'socket_path' => '/var/run/docker.sock',
            'is_active' => 1,
            'is_default' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return $this->create($data);
    }

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
    // Portainer Integration
    // ========================================================================

    public function updatePortainerConfig(string $hostId, ?string $url, ?string $token, ?int $endpointId): bool
    {
        return $this->db->update('docker_hosts', [
            'portainer_url' => $url ?: null,
            'portainer_api_token' => $token ?: null,
            'portainer_endpoint_id' => $endpointId,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $hostId]) > 0;
    }

    public function linkStackToPortainer(string $userId, ?string $hostId, string $stackName, int $portainerStackId): bool
    {
        // Check if mapping exists
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM docker_stack_portainer_map WHERE user_id = ? AND (docker_host_id = ? OR (docker_host_id IS NULL AND ? IS NULL)) AND stack_name = ?',
            [$userId, $hostId, $hostId, $stackName]
        );

        if ($existing) {
            return $this->db->update('docker_stack_portainer_map', [
                'portainer_stack_id' => $portainerStackId,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $existing['id']]) > 0;
        }

        return $this->db->insert('docker_stack_portainer_map', [
            'id' => $this->generateUuid(),
            'user_id' => $userId,
            'docker_host_id' => $hostId,
            'stack_name' => $stackName,
            'portainer_stack_id' => $portainerStackId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    public function getStackPortainerMapping(string $userId, ?string $hostId, string $stackName): ?array
    {
        $result = $this->db->fetchAssociative(
            'SELECT * FROM docker_stack_portainer_map WHERE user_id = ? AND (docker_host_id = ? OR (docker_host_id IS NULL AND ? IS NULL)) AND stack_name = ?',
            [$userId, $hostId, $hostId, $stackName]
        );

        return $result ?: null;
    }

    public function getStackPortainerMappings(string $userId, ?string $hostId = null): array
    {
        if ($hostId) {
            return $this->db->fetchAllAssociative(
                'SELECT * FROM docker_stack_portainer_map WHERE user_id = ? AND docker_host_id = ?',
                [$userId, $hostId]
            );
        }

        return $this->db->fetchAllAssociative(
            'SELECT * FROM docker_stack_portainer_map WHERE user_id = ?',
            [$userId]
        );
    }
}
