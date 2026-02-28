<?php

declare(strict_types=1);

namespace App\Modules\Dns\Services;

/**
 * Webtropia/myLoc DNS API client.
 *
 * @see https://apidoc.myloc.de/
 */
class WebtropiaService
{
    private const BASE_URL = 'https://zkm.myloc.de/api';

    /**
     * Validate a token by attempting to list records for a zone.
     */
    public function validateToken(string $token, string $zone): bool
    {
        $response = $this->request('GET', "/dns/zone/{$zone}", $token);
        return $response !== null && isset($response['content']['records']);
    }

    /**
     * List all DNS records for a zone.
     *
     * @return array<int, array{type: string, name: string, content: string, ttl: int}>
     */
    public function listRecords(string $token, string $zone): array
    {
        $response = $this->request('GET', "/dns/zone/{$zone}", $token);

        if (!$response || !isset($response['content']['records'])) {
            return [];
        }

        return $response['content']['records'];
    }

    /**
     * Add a DNS record to a zone.
     */
    public function createRecord(string $token, string $zone, array $data): bool
    {
        $payload = [
            'type'    => $data['type'],
            'name'    => $data['name'],
            'content' => $data['content'],
            'ttl'     => $data['ttl'] ?? 3600,
        ];

        $response = $this->request('PUT', "/dns/zone/{$zone}", $token, $payload);
        return $response !== null;
    }

    /**
     * Update a DNS record (old/new pattern).
     *
     * @param array $oldRecord The complete old record {type, name, content, ttl}
     * @param array $newRecord The complete new record {type, name, content, ttl}
     */
    public function updateRecord(string $token, string $zone, array $oldRecord, array $newRecord): bool
    {
        $payload = [
            'old' => $oldRecord,
            'new' => $newRecord,
        ];

        $response = $this->request('PATCH', "/dns/zone/{$zone}", $token, $payload);
        return $response !== null;
    }

    /**
     * Delete a DNS record from a zone.
     */
    public function deleteRecord(string $token, string $zone, array $data): bool
    {
        $payload = [
            'name'    => $data['name'],
            'type'    => $data['type'],
            'content' => $data['content'],
        ];

        $response = $this->request('DELETE', "/dns/zone/{$zone}", $token, $payload);
        return $response !== null;
    }

    /**
     * Make an authenticated request to the Webtropia/myLoc API.
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
            case 'PUT':
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if ($body !== null) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($body);
                }
                break;
            case 'PATCH':
                $options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
                if ($body !== null) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($body);
                }
                break;
            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                if ($body !== null) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($body);
                }
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
