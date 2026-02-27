<?php

declare(strict_types=1);

namespace App\Modules\Dns\Services;

/**
 * Cloudflare DNS API v4 client.
 *
 * @see https://developers.cloudflare.com/api/
 */
class CloudflareService
{
    private const BASE_URL = 'https://api.cloudflare.com/client/v4';

    /**
     * Verify that an API token is valid.
     */
    public function validateToken(string $token): bool
    {
        $response = $this->request('GET', '/user/tokens/verify', $token);
        return $response !== null && ($response['success'] ?? false);
    }

    /**
     * List all zones (domains) accessible with the given token.
     *
     * @return array<int, array{id: string, name: string, status: string, name_servers: string[]}>
     */
    public function listZones(string $token): array
    {
        $zones = [];
        $page  = 1;

        do {
            $response = $this->request('GET', "/zones?per_page=50&page={$page}", $token);
            if (!$response || !($response['success'] ?? false)) {
                break;
            }

            foreach ($response['result'] as $zone) {
                $zones[] = [
                    'id'           => $zone['id'],
                    'name'         => $zone['name'],
                    'status'       => $zone['status'],
                    'name_servers' => $zone['name_servers'] ?? [],
                    'plan'         => $zone['plan']['name'] ?? 'Free',
                ];
            }

            $totalPages = $response['result_info']['total_pages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $zones;
    }

    /**
     * List all DNS records for a zone.
     *
     * @return array<int, array{id: string, type: string, name: string, content: string, ttl: int, priority: ?int, proxied: bool}>
     */
    public function listRecords(string $token, string $zoneId): array
    {
        $records = [];
        $page    = 1;

        do {
            $response = $this->request('GET', "/zones/{$zoneId}/dns_records?per_page=100&page={$page}", $token);
            if (!$response || !($response['success'] ?? false)) {
                break;
            }

            foreach ($response['result'] as $record) {
                $records[] = [
                    'id'       => $record['id'],
                    'type'     => $record['type'],
                    'name'     => $record['name'],
                    'content'  => $record['content'],
                    'ttl'      => $record['ttl'],
                    'priority' => $record['priority'] ?? null,
                    'proxied'  => $record['proxied'] ?? false,
                ];
            }

            $totalPages = $response['result_info']['total_pages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $records;
    }

    /**
     * Create a DNS record in Cloudflare.
     *
     * @return array|null The created record or null on failure.
     */
    public function createRecord(string $token, string $zoneId, array $data): ?array
    {
        $payload = [
            'type'    => $data['type'],
            'name'    => $data['name'],
            'content' => $data['content'],
            'ttl'     => $data['ttl'] ?? 3600,
        ];

        if (isset($data['priority'])) {
            $payload['priority'] = (int) $data['priority'];
        }

        if (isset($data['proxied'])) {
            $payload['proxied'] = (bool) $data['proxied'];
        }

        $response = $this->request('POST', "/zones/{$zoneId}/dns_records", $token, $payload);

        if (!$response || !($response['success'] ?? false)) {
            return null;
        }

        return $response['result'];
    }

    /**
     * Update a DNS record in Cloudflare.
     */
    public function updateRecord(string $token, string $zoneId, string $recordId, array $data): ?array
    {
        $payload = [
            'type'    => $data['type'],
            'name'    => $data['name'],
            'content' => $data['content'],
            'ttl'     => $data['ttl'] ?? 3600,
        ];

        if (isset($data['priority'])) {
            $payload['priority'] = (int) $data['priority'];
        }

        if (isset($data['proxied'])) {
            $payload['proxied'] = (bool) $data['proxied'];
        }

        $response = $this->request('PUT', "/zones/{$zoneId}/dns_records/{$recordId}", $token, $payload);

        if (!$response || !($response['success'] ?? false)) {
            return null;
        }

        return $response['result'];
    }

    /**
     * Delete a DNS record from Cloudflare.
     */
    public function deleteRecord(string $token, string $zoneId, string $recordId): bool
    {
        $response = $this->request('DELETE', "/zones/{$zoneId}/dns_records/{$recordId}", $token);
        return $response !== null && ($response['success'] ?? false);
    }

    /**
     * Make an authenticated request to the Cloudflare API.
     */
    private function request(string $method, string $endpoint, string $token, ?array $body = null): ?array
    {
        $ch = curl_init();

        $url = self::BASE_URL . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        switch (strtoupper($method)) {
            case 'POST':
                $options[CURLOPT_POST] = true;
                if ($body !== null) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($body);
                }
                break;
            case 'PUT':
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if ($body !== null) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($body);
                }
                break;
            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        return json_decode($response, true);
    }
}
