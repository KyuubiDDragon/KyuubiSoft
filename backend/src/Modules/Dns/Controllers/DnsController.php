<?php

declare(strict_types=1);

namespace App\Modules\Dns\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Dns\Services\CloudflareService;
use App\Modules\Dns\Services\WebtropiaService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class DnsController
{
    private CloudflareService $cloudflare;
    private WebtropiaService $webtropia;

    public function __construct(
        private readonly Connection $db
    ) {
        $this->cloudflare = new CloudflareService();
        $this->webtropia = new WebtropiaService();
    }

    /**
     * List all DNS domains for the authenticated user with record counts.
     */
    public function listDomains(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $domains = $this->db->fetchAllAssociative(
            'SELECT d.*, COUNT(r.id) as record_count
             FROM dns_domains d
             LEFT JOIN dns_records r ON d.id = r.domain_id
             WHERE d.user_id = ?
             GROUP BY d.id
             ORDER BY d.name ASC',
            [$userId]
        );

        $domains = array_map(function (array $domain): array {
            $domain['record_count'] = (int) $domain['record_count'];
            if ($domain['provider_config'] !== null) {
                $domain['provider_config'] = json_decode($domain['provider_config'], true);
            }
            return $domain;
        }, $domains);

        return JsonResponse::success($domains);
    }

    /**
     * Create a new DNS domain.
     */
    public function createDomain(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        $name = trim((string) ($body['name'] ?? ''));
        $provider = trim((string) ($body['provider'] ?? 'manual'));
        $providerConfig = $body['provider_config'] ?? null;
        $notes = trim((string) ($body['notes'] ?? ''));

        if ($name === '') {
            return JsonResponse::validationError([
                'name' => ['Domain-Name ist erforderlich'],
            ]);
        }

        // Validate domain name format
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/', $name)) {
            return JsonResponse::validationError([
                'name' => ['Ungueltiger Domain-Name'],
            ]);
        }

        // Check for duplicate domain name for this user
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM dns_domains WHERE user_id = ? AND name = ?',
            [$userId, $name]
        );

        if ($existing) {
            return JsonResponse::validationError([
                'name' => ['Diese Domain existiert bereits'],
            ]);
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('dns_domains', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $name,
            'provider' => $provider,
            'provider_config' => $providerConfig !== null ? json_encode($providerConfig) : null,
            'external_zone_id' => ($provider === 'webtropia' && $providerConfig !== null) ? $name : null,
            'notes' => $notes ?: null,
        ]);

        $domain = $this->db->fetchAssociative(
            'SELECT d.*, COUNT(r.id) as record_count
             FROM dns_domains d
             LEFT JOIN dns_records r ON d.id = r.domain_id
             WHERE d.id = ?
             GROUP BY d.id',
            [$id]
        );

        $domain['record_count'] = (int) $domain['record_count'];
        if ($domain['provider_config'] !== null) {
            $domain['provider_config'] = json_decode($domain['provider_config'], true);
        }

        return JsonResponse::created($domain);
    }

    /**
     * Get a single domain with all its DNS records.
     */
    public function showDomain(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $domain = $this->db->fetchAssociative(
            'SELECT d.*, COUNT(r.id) as record_count
             FROM dns_domains d
             LEFT JOIN dns_records r ON d.id = r.domain_id
             WHERE d.id = ? AND d.user_id = ?
             GROUP BY d.id',
            [$id, $userId]
        );

        if (!$domain) {
            return JsonResponse::error('Domain nicht gefunden', 404);
        }

        $domain['record_count'] = (int) $domain['record_count'];
        if ($domain['provider_config'] !== null) {
            $domain['provider_config'] = json_decode($domain['provider_config'], true);
        }

        // Fetch all records for this domain
        $records = $this->db->fetchAllAssociative(
            'SELECT * FROM dns_records WHERE domain_id = ? ORDER BY type ASC, name ASC',
            [$id]
        );

        $records = array_map(function (array $record): array {
            $record['ttl'] = (int) $record['ttl'];
            $record['priority'] = $record['priority'] !== null ? (int) $record['priority'] : null;
            return $record;
        }, $records);

        $domain['records'] = $records;

        return JsonResponse::success($domain);
    }

    /**
     * Update domain metadata (name, provider, notes).
     */
    public function updateDomain(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $existing = $this->db->fetchAssociative(
            'SELECT id FROM dns_domains WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$existing) {
            return JsonResponse::error('Domain nicht gefunden', 404);
        }

        $body = (array) $request->getParsedBody();
        $updates = [];

        if (isset($body['name'])) {
            $name = trim((string) $body['name']);
            if ($name === '') {
                return JsonResponse::validationError([
                    'name' => ['Domain-Name darf nicht leer sein'],
                ]);
            }
            if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/', $name)) {
                return JsonResponse::validationError([
                    'name' => ['Ungueltiger Domain-Name'],
                ]);
            }
            // Check uniqueness (excluding current)
            $dup = $this->db->fetchAssociative(
                'SELECT id FROM dns_domains WHERE user_id = ? AND name = ? AND id != ?',
                [$userId, $name, $id]
            );
            if ($dup) {
                return JsonResponse::validationError([
                    'name' => ['Diese Domain existiert bereits'],
                ]);
            }
            $updates['name'] = $name;
        }

        if (isset($body['provider'])) {
            $updates['provider'] = trim((string) $body['provider']);
        }

        if (array_key_exists('provider_config', $body)) {
            $updates['provider_config'] = $body['provider_config'] !== null
                ? json_encode($body['provider_config'])
                : null;
        }

        if (array_key_exists('notes', $body)) {
            $updates['notes'] = trim((string) $body['notes']) ?: null;
        }

        if (!empty($updates)) {
            $this->db->update('dns_domains', $updates, ['id' => $id]);
        }

        $domain = $this->db->fetchAssociative(
            'SELECT d.*, COUNT(r.id) as record_count
             FROM dns_domains d
             LEFT JOIN dns_records r ON d.id = r.domain_id
             WHERE d.id = ?
             GROUP BY d.id',
            [$id]
        );

        $domain['record_count'] = (int) $domain['record_count'];
        if ($domain['provider_config'] !== null) {
            $domain['provider_config'] = json_decode($domain['provider_config'], true);
        }

        return JsonResponse::success($domain);
    }

    /**
     * Delete a domain and all its records (cascade).
     */
    public function deleteDomain(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $existing = $this->db->fetchAssociative(
            'SELECT id FROM dns_domains WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$existing) {
            return JsonResponse::error('Domain nicht gefunden', 404);
        }

        $this->db->delete('dns_domains', ['id' => $id]);

        return JsonResponse::success(null, 'Domain geloescht');
    }

    /**
     * List all DNS records for a specific domain.
     */
    public function listRecords(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $domainId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Verify domain ownership
        $domain = $this->db->fetchAssociative(
            'SELECT id, name FROM dns_domains WHERE id = ? AND user_id = ?',
            [$domainId, $userId]
        );

        if (!$domain) {
            return JsonResponse::error('Domain nicht gefunden', 404);
        }

        $records = $this->db->fetchAllAssociative(
            'SELECT * FROM dns_records WHERE domain_id = ? ORDER BY type ASC, name ASC',
            [$domainId]
        );

        $records = array_map(function (array $record): array {
            $record['ttl'] = (int) $record['ttl'];
            $record['priority'] = $record['priority'] !== null ? (int) $record['priority'] : null;
            return $record;
        }, $records);

        return JsonResponse::success($records);
    }

    /**
     * Create a new DNS record for a domain.
     */
    public function createRecord(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $domainId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Verify domain ownership
        $domain = $this->db->fetchAssociative(
            'SELECT id, name FROM dns_domains WHERE id = ? AND user_id = ?',
            [$domainId, $userId]
        );

        if (!$domain) {
            return JsonResponse::error('Domain nicht gefunden', 404);
        }

        $body = (array) $request->getParsedBody();

        $type = strtoupper(trim((string) ($body['type'] ?? '')));
        $name = trim((string) ($body['name'] ?? '@'));
        $value = trim((string) ($body['value'] ?? ''));
        $ttl = (int) ($body['ttl'] ?? 3600);
        $priority = isset($body['priority']) && $body['priority'] !== '' ? (int) $body['priority'] : null;
        $notes = trim((string) ($body['notes'] ?? ''));

        $allowedTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'];

        $errors = [];
        if ($type === '' || !in_array($type, $allowedTypes, true)) {
            $errors['type'] = ['Ungueltiger Record-Typ. Erlaubt: ' . implode(', ', $allowedTypes)];
        }
        if ($value === '') {
            $errors['value'] = ['Wert ist erforderlich'];
        }
        if ($ttl < 60 || $ttl > 86400) {
            $errors['ttl'] = ['TTL muss zwischen 60 und 86400 Sekunden liegen'];
        }
        if (in_array($type, ['MX', 'SRV'], true) && $priority === null) {
            $errors['priority'] = ['Prioritaet ist fuer ' . $type . '-Records erforderlich'];
        }

        if (!empty($errors)) {
            return JsonResponse::validationError($errors);
        }

        $id = Uuid::uuid4()->toString();
        $externalId = null;

        // If this is a Cloudflare domain, also create the record on Cloudflare
        $fullDomain = $this->db->fetchAssociative(
            'SELECT provider, provider_config, external_zone_id, name as domain_name FROM dns_domains WHERE id = ?',
            [$domainId]
        );

        if ($fullDomain && $fullDomain['provider'] === 'cloudflare' && $fullDomain['external_zone_id']) {
            $config = $fullDomain['provider_config'] ? json_decode($fullDomain['provider_config'], true) : [];
            $token = $config['api_token'] ?? '';

            if ($token !== '') {
                $cfName = ($name === '' || $name === '@')
                    ? $fullDomain['domain_name']
                    : $name . '.' . $fullDomain['domain_name'];

                $cfData = [
                    'type'    => $type,
                    'name'    => $cfName,
                    'content' => $value,
                    'ttl'     => $ttl,
                ];
                if ($priority !== null) {
                    $cfData['priority'] = $priority;
                }

                $cfResult = $this->cloudflare->createRecord($token, $fullDomain['external_zone_id'], $cfData);
                if ($cfResult) {
                    $externalId = $cfResult['id'];
                }
            }
        }

        $this->db->insert('dns_records', [
            'id' => $id,
            'domain_id' => $domainId,
            'external_id' => $externalId,
            'type' => $type,
            'name' => $name ?: '@',
            'value' => $value,
            'ttl' => $ttl,
            'priority' => $priority,
            'notes' => $notes ?: null,
        ]);

        $record = $this->db->fetchAssociative(
            'SELECT * FROM dns_records WHERE id = ?',
            [$id]
        );

        $record['ttl'] = (int) $record['ttl'];
        $record['priority'] = $record['priority'] !== null ? (int) $record['priority'] : null;

        return JsonResponse::created($record);
    }

    /**
     * Update an existing DNS record.
     */
    public function updateRecord(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $recordId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Fetch record and verify ownership through domain
        $record = $this->db->fetchAssociative(
            'SELECT r.*, d.user_id
             FROM dns_records r
             JOIN dns_domains d ON r.domain_id = d.id
             WHERE r.id = ? AND d.user_id = ?',
            [$recordId, $userId]
        );

        if (!$record) {
            return JsonResponse::error('DNS-Record nicht gefunden', 404);
        }

        $body = (array) $request->getParsedBody();
        $updates = [];

        $allowedTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'];

        if (isset($body['type'])) {
            $type = strtoupper(trim((string) $body['type']));
            if (!in_array($type, $allowedTypes, true)) {
                return JsonResponse::validationError([
                    'type' => ['Ungueltiger Record-Typ. Erlaubt: ' . implode(', ', $allowedTypes)],
                ]);
            }
            $updates['type'] = $type;
        }

        if (isset($body['name'])) {
            $updates['name'] = trim((string) $body['name']) ?: '@';
        }

        if (isset($body['value'])) {
            $value = trim((string) $body['value']);
            if ($value === '') {
                return JsonResponse::validationError([
                    'value' => ['Wert darf nicht leer sein'],
                ]);
            }
            $updates['value'] = $value;
        }

        if (isset($body['ttl'])) {
            $ttl = (int) $body['ttl'];
            if ($ttl < 60 || $ttl > 86400) {
                return JsonResponse::validationError([
                    'ttl' => ['TTL muss zwischen 60 und 86400 Sekunden liegen'],
                ]);
            }
            $updates['ttl'] = $ttl;
        }

        if (array_key_exists('priority', $body)) {
            $updates['priority'] = ($body['priority'] !== null && $body['priority'] !== '')
                ? (int) $body['priority']
                : null;
        }

        if (array_key_exists('notes', $body)) {
            $updates['notes'] = trim((string) $body['notes']) ?: null;
        }

        if (!empty($updates)) {
            $this->db->update('dns_records', $updates, ['id' => $recordId]);
        }

        $updated = $this->db->fetchAssociative(
            'SELECT * FROM dns_records WHERE id = ?',
            [$recordId]
        );

        // If Cloudflare domain and record has external_id, also update on Cloudflare
        if (!empty($updated['external_id'])) {
            $domain = $this->db->fetchAssociative(
                'SELECT provider, provider_config, external_zone_id, name as domain_name FROM dns_domains WHERE id = ?',
                [$updated['domain_id']]
            );

            if ($domain && $domain['provider'] === 'cloudflare' && $domain['external_zone_id']) {
                $config = $domain['provider_config'] ? json_decode($domain['provider_config'], true) : [];
                $token = $config['api_token'] ?? '';

                if ($token !== '') {
                    $cfName = ($updated['name'] === '@')
                        ? $domain['domain_name']
                        : $updated['name'] . '.' . $domain['domain_name'];

                    $cfData = [
                        'type'    => $updated['type'],
                        'name'    => $cfName,
                        'content' => $updated['value'],
                        'ttl'     => (int) $updated['ttl'],
                    ];
                    if ($updated['priority'] !== null) {
                        $cfData['priority'] = (int) $updated['priority'];
                    }

                    $this->cloudflare->updateRecord($token, $domain['external_zone_id'], $updated['external_id'], $cfData);
                }
            }
        }

        $updated['ttl'] = (int) $updated['ttl'];
        $updated['priority'] = $updated['priority'] !== null ? (int) $updated['priority'] : null;

        return JsonResponse::success($updated);
    }

    /**
     * Delete a DNS record.
     */
    public function deleteRecord(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $recordId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Verify ownership through domain and get Cloudflare info
        $record = $this->db->fetchAssociative(
            'SELECT r.id, r.external_id, r.domain_id,
                    d.provider, d.provider_config, d.external_zone_id
             FROM dns_records r
             JOIN dns_domains d ON r.domain_id = d.id
             WHERE r.id = ? AND d.user_id = ?',
            [$recordId, $userId]
        );

        if (!$record) {
            return JsonResponse::error('DNS-Record nicht gefunden', 404);
        }

        // If Cloudflare domain and record has external_id, also delete from Cloudflare
        if (!empty($record['external_id']) && $record['provider'] === 'cloudflare' && $record['external_zone_id']) {
            $config = $record['provider_config'] ? json_decode($record['provider_config'], true) : [];
            $token = $config['api_token'] ?? '';

            if ($token !== '') {
                $this->cloudflare->deleteRecord($token, $record['external_zone_id'], $record['external_id']);
            }
        }

        $this->db->delete('dns_records', ['id' => $recordId]);

        return JsonResponse::success(null, 'DNS-Record geloescht');
    }

    /**
     * Check DNS propagation for a specific record.
     * Uses PHP's dns_get_record() to query live DNS and compare.
     */
    public function checkPropagation(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $recordId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Fetch record and verify ownership
        $record = $this->db->fetchAssociative(
            'SELECT r.*, d.name as domain_name, d.user_id
             FROM dns_records r
             JOIN dns_domains d ON r.domain_id = d.id
             WHERE r.id = ? AND d.user_id = ?',
            [$recordId, $userId]
        );

        if (!$record) {
            return JsonResponse::error('DNS-Record nicht gefunden', 404);
        }

        $domainName = $record['domain_name'];
        $recordName = $record['name'];
        $queryName = ($recordName === '@' || $recordName === '')
            ? $domainName
            : $recordName . '.' . $domainName;

        $typeMap = [
            'A' => DNS_A,
            'AAAA' => DNS_AAAA,
            'CNAME' => DNS_CNAME,
            'MX' => DNS_MX,
            'TXT' => DNS_TXT,
            'NS' => DNS_NS,
            'SRV' => DNS_SRV,
            'CAA' => DNS_CAA,
        ];

        $dnsType = $typeMap[$record['type']] ?? DNS_ANY;

        $results = [];
        $propagated = false;

        try {
            $dnsResults = @dns_get_record($queryName, $dnsType);

            if ($dnsResults !== false && is_array($dnsResults)) {
                foreach ($dnsResults as $entry) {
                    $foundValue = $this->extractDnsValue($entry, $record['type']);
                    $results[] = [
                        'host' => $entry['host'] ?? $queryName,
                        'type' => $entry['type'] ?? $record['type'],
                        'value' => $foundValue,
                        'ttl' => $entry['ttl'] ?? null,
                    ];

                    // Check if any result matches expected value
                    if ($this->dnsValuesMatch($foundValue, $record['value'], $record['type'])) {
                        $propagated = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // DNS query failed, return empty results
        }

        return JsonResponse::success([
            'record_id' => $record['id'],
            'query_name' => $queryName,
            'expected_type' => $record['type'],
            'expected_value' => $record['value'],
            'propagated' => $propagated,
            'dns_results' => $results,
            'checked_at' => date('c'),
        ]);
    }

    /**
     * Export domain records as a BIND zone file.
     */
    public function exportZone(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $domainId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $domain = $this->db->fetchAssociative(
            'SELECT * FROM dns_domains WHERE id = ? AND user_id = ?',
            [$domainId, $userId]
        );

        if (!$domain) {
            return JsonResponse::error('Domain nicht gefunden', 404);
        }

        $records = $this->db->fetchAllAssociative(
            'SELECT * FROM dns_records WHERE domain_id = ? ORDER BY type ASC, name ASC',
            [$domainId]
        );

        $zone = $this->generateZoneFile($domain['name'], $records);

        return JsonResponse::success([
            'domain' => $domain['name'],
            'zone_file' => $zone,
        ]);
    }

    /**
     * Import records from a BIND zone file format.
     */
    public function importZone(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $domainId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $domain = $this->db->fetchAssociative(
            'SELECT * FROM dns_domains WHERE id = ? AND user_id = ?',
            [$domainId, $userId]
        );

        if (!$domain) {
            return JsonResponse::error('Domain nicht gefunden', 404);
        }

        $body = (array) $request->getParsedBody();
        $zoneContent = trim((string) ($body['zone_content'] ?? ''));

        if ($zoneContent === '') {
            return JsonResponse::validationError([
                'zone_content' => ['Zone-Datei-Inhalt ist erforderlich'],
            ]);
        }

        $allowedTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'];
        $imported = [];
        $errors = [];
        $lineNumber = 0;

        $lines = explode("\n", $zoneContent);

        foreach ($lines as $line) {
            $lineNumber++;
            $line = trim($line);

            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '#')) {
                continue;
            }

            // Skip SOA, $ORIGIN, $TTL directives
            if (str_starts_with($line, '$') || str_contains($line, 'SOA')) {
                continue;
            }

            $parsed = $this->parseZoneLine($line, $domain['name']);

            if ($parsed === null) {
                $errors[] = "Zeile {$lineNumber}: Konnte nicht geparst werden: {$line}";
                continue;
            }

            if (!in_array($parsed['type'], $allowedTypes, true)) {
                $errors[] = "Zeile {$lineNumber}: Nicht unterstuetzter Record-Typ: {$parsed['type']}";
                continue;
            }

            $id = Uuid::uuid4()->toString();

            $this->db->insert('dns_records', [
                'id' => $id,
                'domain_id' => $domainId,
                'type' => $parsed['type'],
                'name' => $parsed['name'],
                'value' => $parsed['value'],
                'ttl' => $parsed['ttl'],
                'priority' => $parsed['priority'],
            ]);

            $record = $this->db->fetchAssociative(
                'SELECT * FROM dns_records WHERE id = ?',
                [$id]
            );

            $record['ttl'] = (int) $record['ttl'];
            $record['priority'] = $record['priority'] !== null ? (int) $record['priority'] : null;

            $imported[] = $record;
        }

        return JsonResponse::success([
            'imported_count' => count($imported),
            'records' => $imported,
            'errors' => $errors,
        ]);
    }

    // ==================== Cloudflare Integration ====================

    /**
     * Verify a Cloudflare API token.
     * POST /dns/cloudflare/verify
     */
    public function verifyCloudflareToken(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $token = trim((string) ($body['api_token'] ?? ''));

        if ($token === '') {
            return JsonResponse::validationError(['api_token' => ['API-Token ist erforderlich']]);
        }

        try {
            $valid = $this->cloudflare->validateToken($token);
            if (!$valid) {
                return JsonResponse::error('Ungueltiger API-Token', 401);
            }
            return JsonResponse::success(['valid' => true], 'Token ist gueltig');
        } catch (\Throwable $e) {
            return JsonResponse::error('Token-Validierung fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List all Cloudflare zones accessible with a token.
     * POST /dns/cloudflare/zones
     */
    public function listCloudflareZones(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $token = trim((string) ($body['api_token'] ?? ''));

        if ($token === '') {
            return JsonResponse::validationError(['api_token' => ['API-Token ist erforderlich']]);
        }

        try {
            $zones = $this->cloudflare->listZones($token);
            return JsonResponse::success(['items' => $zones]);
        } catch (\Throwable $e) {
            return JsonResponse::error('Fehler beim Laden der Zonen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import a Cloudflare zone as a new domain with all its records.
     * POST /dns/cloudflare/import
     */
    public function importCloudflareZone(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        $token  = trim((string) ($body['api_token'] ?? ''));
        $zoneId = trim((string) ($body['zone_id'] ?? ''));
        $zoneName = trim((string) ($body['zone_name'] ?? ''));

        if ($token === '' || $zoneId === '' || $zoneName === '') {
            return JsonResponse::validationError([
                'api_token' => $token === '' ? ['API-Token ist erforderlich'] : [],
                'zone_id'   => $zoneId === '' ? ['Zone-ID ist erforderlich'] : [],
                'zone_name' => $zoneName === '' ? ['Zone-Name ist erforderlich'] : [],
            ]);
        }

        // Check if domain already exists
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM dns_domains WHERE user_id = ? AND name = ?',
            [$userId, $zoneName]
        );

        if ($existing) {
            return JsonResponse::validationError([
                'zone_name' => ['Diese Domain existiert bereits. Nutze Sync um Records zu aktualisieren.'],
            ]);
        }

        try {
            // Create the domain
            $domainId = Uuid::uuid4()->toString();
            $this->db->insert('dns_domains', [
                'id'               => $domainId,
                'user_id'          => $userId,
                'name'             => $zoneName,
                'provider'         => 'cloudflare',
                'provider_config'  => json_encode(['api_token' => $token]),
                'external_zone_id' => $zoneId,
            ]);

            // Fetch and import all records
            $cfRecords = $this->cloudflare->listRecords($token, $zoneId);
            $allowedTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'];
            $importedCount = 0;

            foreach ($cfRecords as $cfRecord) {
                if (!in_array($cfRecord['type'], $allowedTypes, true)) {
                    continue;
                }

                // Convert Cloudflare record name to relative name
                $recordName = $cfRecord['name'];
                if ($recordName === $zoneName) {
                    $recordName = '@';
                } elseif (str_ends_with($recordName, '.' . $zoneName)) {
                    $recordName = substr($recordName, 0, -(strlen($zoneName) + 1));
                }

                $this->db->insert('dns_records', [
                    'id'          => Uuid::uuid4()->toString(),
                    'domain_id'   => $domainId,
                    'external_id' => $cfRecord['id'],
                    'type'        => $cfRecord['type'],
                    'name'        => $recordName,
                    'value'       => $cfRecord['content'],
                    'ttl'         => $cfRecord['ttl'] === 1 ? 3600 : $cfRecord['ttl'], // Cloudflare uses 1 for "auto"
                    'priority'    => $cfRecord['priority'],
                ]);

                $importedCount++;
            }

            $domain = $this->db->fetchAssociative(
                'SELECT d.*, COUNT(r.id) as record_count
                 FROM dns_domains d
                 LEFT JOIN dns_records r ON d.id = r.domain_id
                 WHERE d.id = ?
                 GROUP BY d.id',
                [$domainId]
            );

            $domain['record_count'] = (int) $domain['record_count'];
            if ($domain['provider_config'] !== null) {
                $domain['provider_config'] = json_decode($domain['provider_config'], true);
            }

            return JsonResponse::created([
                'domain'         => $domain,
                'imported_count' => $importedCount,
            ], "{$importedCount} Records von Cloudflare importiert");

        } catch (\Throwable $e) {
            return JsonResponse::error('Import fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Sync (pull) records from provider into the local database.
     * POST /dns/domains/{id}/sync-provider
     */
    public function syncProvider(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $domainId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $domain = $this->db->fetchAssociative(
            'SELECT * FROM dns_domains WHERE id = ? AND user_id = ?',
            [$domainId, $userId]
        );

        if (!$domain) {
            return JsonResponse::error('Domain nicht gefunden', 404);
        }

        $config = $domain['provider_config'] ? json_decode($domain['provider_config'], true) : [];
        $token  = $config['api_token'] ?? '';

        if ($token === '') {
            return JsonResponse::error('API-Token fehlt in der Provider-Konfiguration', 400);
        }

        return match ($domain['provider']) {
            'cloudflare' => $this->syncCloudflare($domain, $token, $domainId),
            'webtropia'  => $this->syncWebtropia($domain, $token, $domainId),
            default      => JsonResponse::error('Sync ist fuer diesen Provider nicht verfuegbar', 400),
        };
    }

    private function syncCloudflare(array $domain, string $token, string $domainId): Response
    {
        $zoneId = $domain['external_zone_id'] ?? '';
        if ($zoneId === '') {
            return JsonResponse::error('Cloudflare Zone-ID fehlt', 400);
        }

        try {
            $cfRecords = $this->cloudflare->listRecords($token, $zoneId);
            $allowedTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'];
            $added = 0;
            $updated = 0;

            foreach ($cfRecords as $cfRecord) {
                if (!in_array($cfRecord['type'], $allowedTypes, true)) {
                    continue;
                }

                $recordName = $cfRecord['name'];
                if ($recordName === $domain['name']) {
                    $recordName = '@';
                } elseif (str_ends_with($recordName, '.' . $domain['name'])) {
                    $recordName = substr($recordName, 0, -(strlen($domain['name']) + 1));
                }

                $existing = $this->db->fetchAssociative(
                    'SELECT id FROM dns_records WHERE domain_id = ? AND external_id = ?',
                    [$domainId, $cfRecord['id']]
                );

                $data = [
                    'type'     => $cfRecord['type'],
                    'name'     => $recordName,
                    'value'    => $cfRecord['content'],
                    'ttl'      => $cfRecord['ttl'] === 1 ? 3600 : $cfRecord['ttl'],
                    'priority' => $cfRecord['priority'],
                ];

                if ($existing) {
                    $this->db->update('dns_records', $data, ['id' => $existing['id']]);
                    $updated++;
                } else {
                    $data['id'] = Uuid::uuid4()->toString();
                    $data['domain_id'] = $domainId;
                    $data['external_id'] = $cfRecord['id'];
                    $this->db->insert('dns_records', $data);
                    $added++;
                }
            }

            return JsonResponse::success([
                'added'   => $added,
                'updated' => $updated,
                'total'   => $added + $updated,
            ], "Sync abgeschlossen: {$added} neu, {$updated} aktualisiert");

        } catch (\Throwable $e) {
            return JsonResponse::error('Sync fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }

    private function syncWebtropia(array $domain, string $token, string $domainId): Response
    {
        $zoneName = $domain['external_zone_id'] ?? $domain['name'];

        try {
            $wtRecords = $this->webtropia->listRecords($token, $zoneName);
            $allowedTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'];
            $added = 0;
            $updated = 0;

            foreach ($wtRecords as $wtRecord) {
                $type = strtoupper($wtRecord['type'] ?? '');
                if (!in_array($type, $allowedTypes, true)) {
                    continue;
                }

                $recordName = $this->fromWebtropiaFqdn($wtRecord['name'] ?? '', $domain['name']);
                $externalId = $this->webtropiaRecordKey($wtRecord);

                // Parse MX priority from content
                $priority = null;
                $value = $wtRecord['content'] ?? '';
                if ($type === 'MX' && preg_match('/^(\d+)\s+(.+)$/', $value, $matches)) {
                    $priority = (int) $matches[1];
                    $value = rtrim($matches[2], '.');
                }

                // Try to find by external_id (composite key)
                $existing = $this->db->fetchAssociative(
                    'SELECT id FROM dns_records WHERE domain_id = ? AND external_id = ?',
                    [$domainId, $externalId]
                );

                $data = [
                    'type'     => $type,
                    'name'     => $recordName,
                    'value'    => $value,
                    'ttl'      => (int) ($wtRecord['ttl'] ?? 3600),
                    'priority' => $priority,
                ];

                if ($existing) {
                    $this->db->update('dns_records', $data, ['id' => $existing['id']]);
                    $updated++;
                } else {
                    // Fallback: match by type+name+value
                    $matchByContent = $this->db->fetchAssociative(
                        'SELECT id FROM dns_records WHERE domain_id = ? AND type = ? AND name = ? AND value = ?',
                        [$domainId, $type, $recordName, $value]
                    );

                    if ($matchByContent) {
                        $data['external_id'] = $externalId;
                        $this->db->update('dns_records', $data, ['id' => $matchByContent['id']]);
                        $updated++;
                    } else {
                        $data['id'] = Uuid::uuid4()->toString();
                        $data['domain_id'] = $domainId;
                        $data['external_id'] = $externalId;
                        $this->db->insert('dns_records', $data);
                        $added++;
                    }
                }
            }

            return JsonResponse::success([
                'added'   => $added,
                'updated' => $updated,
                'total'   => $added + $updated,
            ], "Sync abgeschlossen: {$added} neu, {$updated} aktualisiert");

        } catch (\Throwable $e) {
            return JsonResponse::error('Sync fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Push local records to provider.
     * POST /dns/domains/{id}/push-provider
     */
    public function pushProvider(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $domainId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $domain = $this->db->fetchAssociative(
            'SELECT * FROM dns_domains WHERE id = ? AND user_id = ?',
            [$domainId, $userId]
        );

        if (!$domain) {
            return JsonResponse::error('Domain nicht gefunden', 404);
        }

        $config = $domain['provider_config'] ? json_decode($domain['provider_config'], true) : [];
        $token  = $config['api_token'] ?? '';

        if ($token === '') {
            return JsonResponse::error('API-Token fehlt in der Provider-Konfiguration', 400);
        }

        return match ($domain['provider']) {
            'cloudflare' => $this->pushCloudflare($domain, $token, $domainId),
            'webtropia'  => $this->pushWebtropia($domain, $token, $domainId),
            default      => JsonResponse::error('Push ist fuer diesen Provider nicht verfuegbar', 400),
        };
    }

    private function pushCloudflare(array $domain, string $token, string $domainId): Response
    {
        $zoneId = $domain['external_zone_id'] ?? '';
        if ($zoneId === '') {
            return JsonResponse::error('Cloudflare Zone-ID fehlt', 400);
        }

        try {
            $localRecords = $this->db->fetchAllAssociative(
                'SELECT * FROM dns_records WHERE domain_id = ?',
                [$domainId]
            );

            $created = 0;
            $updatedCount = 0;
            $errors = [];

            foreach ($localRecords as $record) {
                $cfName = $record['name'] === '@'
                    ? $domain['name']
                    : $record['name'] . '.' . $domain['name'];

                $cfData = [
                    'type'    => $record['type'],
                    'name'    => $cfName,
                    'content' => $record['value'],
                    'ttl'     => (int) $record['ttl'],
                ];

                if ($record['priority'] !== null) {
                    $cfData['priority'] = (int) $record['priority'];
                }

                if (!empty($record['external_id'])) {
                    $result = $this->cloudflare->updateRecord($token, $zoneId, $record['external_id'], $cfData);
                    if ($result) {
                        $updatedCount++;
                    } else {
                        $errors[] = "Record {$record['name']} ({$record['type']}) konnte nicht aktualisiert werden";
                    }
                } else {
                    $result = $this->cloudflare->createRecord($token, $zoneId, $cfData);
                    if ($result) {
                        $this->db->update('dns_records', [
                            'external_id' => $result['id'],
                        ], ['id' => $record['id']]);
                        $created++;
                    } else {
                        $errors[] = "Record {$record['name']} ({$record['type']}) konnte nicht erstellt werden";
                    }
                }
            }

            return JsonResponse::success([
                'created' => $created,
                'updated' => $updatedCount,
                'errors'  => $errors,
            ], "Push abgeschlossen: {$created} erstellt, {$updatedCount} aktualisiert");

        } catch (\Throwable $e) {
            return JsonResponse::error('Push fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }

    private function pushWebtropia(array $domain, string $token, string $domainId): Response
    {
        $zoneName = $domain['external_zone_id'] ?? $domain['name'];

        try {
            $localRecords = $this->db->fetchAllAssociative(
                'SELECT * FROM dns_records WHERE domain_id = ?',
                [$domainId]
            );

            // Fetch current remote records for comparison
            $remoteRecords = $this->webtropia->listRecords($token, $zoneName);
            $remoteByKey = [];
            foreach ($remoteRecords as $rr) {
                $key = $this->webtropiaRecordKey($rr);
                $remoteByKey[$key] = $rr;
            }

            $created = 0;
            $updatedCount = 0;
            $errors = [];

            foreach ($localRecords as $record) {
                $fqdn = $this->toWebtropiaFqdn($record['name'], $domain['name']);

                // Build content for Webtropia (MX needs priority prepended)
                $content = $record['value'];
                if ($record['type'] === 'MX' && $record['priority'] !== null) {
                    $content = $record['priority'] . ' ' . $record['value'];
                    if (!str_ends_with($content, '.')) {
                        $content .= '.';
                    }
                }

                $newRecordData = [
                    'type'    => $record['type'],
                    'name'    => $fqdn,
                    'content' => $content,
                    'ttl'     => (int) $record['ttl'],
                ];

                $newExternalId = $this->webtropiaRecordKey($newRecordData);

                if (!empty($record['external_id']) && isset($remoteByKey[$record['external_id']])) {
                    // Record exists remotely — check if update needed
                    $oldRemote = $remoteByKey[$record['external_id']];
                    $oldFqdn = $oldRemote['name'] ?? '';
                    $oldContent = $oldRemote['content'] ?? '';
                    $oldTtl = (int) ($oldRemote['ttl'] ?? 3600);

                    if ($fqdn !== $oldFqdn || $content !== $oldContent || (int) $record['ttl'] !== $oldTtl) {
                        $oldRecordData = [
                            'type'    => $oldRemote['type'],
                            'name'    => $oldRemote['name'],
                            'content' => $oldRemote['content'],
                            'ttl'     => (int) $oldRemote['ttl'],
                        ];

                        $result = $this->webtropia->updateRecord($token, $zoneName, $oldRecordData, $newRecordData);
                        if ($result) {
                            $this->db->update('dns_records', [
                                'external_id' => $newExternalId,
                            ], ['id' => $record['id']]);
                            $updatedCount++;
                        } else {
                            $errors[] = "Record {$record['name']} ({$record['type']}) konnte nicht aktualisiert werden";
                        }
                    }
                } else {
                    // Create new record on Webtropia
                    $result = $this->webtropia->createRecord($token, $zoneName, $newRecordData);
                    if ($result) {
                        $this->db->update('dns_records', [
                            'external_id' => $newExternalId,
                        ], ['id' => $record['id']]);
                        $created++;
                    } else {
                        $errors[] = "Record {$record['name']} ({$record['type']}) konnte nicht erstellt werden";
                    }
                }
            }

            return JsonResponse::success([
                'created' => $created,
                'updated' => $updatedCount,
                'errors'  => $errors,
            ], "Push abgeschlossen: {$created} erstellt, {$updatedCount} aktualisiert");

        } catch (\Throwable $e) {
            return JsonResponse::error('Push fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }

    // ==================== Webtropia Integration ====================

    /**
     * Verify a Webtropia API token by attempting to fetch a zone.
     * POST /dns/webtropia/verify
     */
    public function verifyWebtropiaToken(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $token = trim((string) ($body['api_token'] ?? ''));
        $zone  = trim((string) ($body['zone'] ?? ''));

        if ($token === '') {
            return JsonResponse::validationError(['api_token' => ['API-Token ist erforderlich']]);
        }
        if ($zone === '') {
            return JsonResponse::validationError(['zone' => ['Domain-Name ist erforderlich']]);
        }

        try {
            $valid = $this->webtropia->validateToken($token, $zone);
            if (!$valid) {
                return JsonResponse::error('Ungueltiger API-Token oder Domain nicht gefunden', 401);
            }
            return JsonResponse::success(['valid' => true], 'Token ist gueltig');
        } catch (\Throwable $e) {
            return JsonResponse::error('Token-Validierung fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import a Webtropia zone as a new domain with all its records.
     * POST /dns/webtropia/import
     */
    public function importWebtropiaZone(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        $token    = trim((string) ($body['api_token'] ?? ''));
        $zoneName = trim((string) ($body['zone_name'] ?? ''));

        if ($token === '' || $zoneName === '') {
            return JsonResponse::validationError([
                'api_token' => $token === '' ? ['API-Token ist erforderlich'] : [],
                'zone_name' => $zoneName === '' ? ['Domain-Name ist erforderlich'] : [],
            ]);
        }

        // Check if domain already exists
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM dns_domains WHERE user_id = ? AND name = ?',
            [$userId, $zoneName]
        );

        if ($existing) {
            return JsonResponse::validationError([
                'zone_name' => ['Diese Domain existiert bereits. Nutze Sync um Records zu aktualisieren.'],
            ]);
        }

        try {
            $wtRecords = $this->webtropia->listRecords($token, $zoneName);

            $domainId = Uuid::uuid4()->toString();
            $this->db->insert('dns_domains', [
                'id'               => $domainId,
                'user_id'          => $userId,
                'name'             => $zoneName,
                'provider'         => 'webtropia',
                'provider_config'  => json_encode(['api_token' => $token]),
                'external_zone_id' => $zoneName,
            ]);

            $allowedTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'];
            $importedCount = 0;

            foreach ($wtRecords as $wtRecord) {
                $type = strtoupper($wtRecord['type'] ?? '');
                if (!in_array($type, $allowedTypes, true)) {
                    continue;
                }

                $recordName = $this->fromWebtropiaFqdn($wtRecord['name'] ?? '', $zoneName);
                $externalId = $this->webtropiaRecordKey($wtRecord);

                // Parse MX priority from content
                $priority = null;
                $value = $wtRecord['content'] ?? '';
                if ($type === 'MX' && preg_match('/^(\d+)\s+(.+)$/', $value, $matches)) {
                    $priority = (int) $matches[1];
                    $value = rtrim($matches[2], '.');
                }

                $this->db->insert('dns_records', [
                    'id'          => Uuid::uuid4()->toString(),
                    'domain_id'   => $domainId,
                    'external_id' => $externalId,
                    'type'        => $type,
                    'name'        => $recordName,
                    'value'       => $value,
                    'ttl'         => (int) ($wtRecord['ttl'] ?? 3600),
                    'priority'    => $priority,
                ]);

                $importedCount++;
            }

            $domain = $this->db->fetchAssociative(
                'SELECT d.*, COUNT(r.id) as record_count
                 FROM dns_domains d
                 LEFT JOIN dns_records r ON d.id = r.domain_id
                 WHERE d.id = ?
                 GROUP BY d.id',
                [$domainId]
            );

            $domain['record_count'] = (int) $domain['record_count'];
            if ($domain['provider_config'] !== null) {
                $domain['provider_config'] = json_decode($domain['provider_config'], true);
            }

            return JsonResponse::created([
                'domain'         => $domain,
                'imported_count' => $importedCount,
            ], "{$importedCount} Records von Webtropia importiert");

        } catch (\Throwable $e) {
            return JsonResponse::error('Import fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }

    // ==================== Helper Methods ====================

    /**
     * Extract the relevant value from a dns_get_record result entry.
     */
    private function extractDnsValue(array $entry, string $type): string
    {
        return match ($type) {
            'A' => $entry['ip'] ?? '',
            'AAAA' => $entry['ipv6'] ?? '',
            'CNAME' => $entry['target'] ?? '',
            'MX' => $entry['target'] ?? '',
            'TXT' => $entry['txt'] ?? '',
            'NS' => $entry['target'] ?? '',
            'SRV' => ($entry['target'] ?? '') . ':' . ($entry['port'] ?? ''),
            'CAA' => ($entry['tag'] ?? '') . ' ' . ($entry['value'] ?? ''),
            default => $entry['target'] ?? $entry['ip'] ?? '',
        };
    }

    /**
     * Compare DNS values, handling trailing dots and case differences.
     */
    private function dnsValuesMatch(string $found, string $expected, string $type): bool
    {
        $found = rtrim(strtolower(trim($found)), '.');
        $expected = rtrim(strtolower(trim($expected)), '.');

        if ($type === 'TXT') {
            // TXT records may be quoted
            $found = trim($found, '"');
            $expected = trim($expected, '"');
        }

        return $found === $expected;
    }

    /**
     * Generate a BIND-format zone file from domain and records.
     */
    private function generateZoneFile(string $domainName, array $records): string
    {
        $lines = [];
        $lines[] = "; Zone file for {$domainName}";
        $lines[] = "; Exported at " . date('Y-m-d H:i:s');
        $lines[] = '$ORIGIN ' . $domainName . '.';
        $lines[] = '$TTL 3600';
        $lines[] = '';

        foreach ($records as $record) {
            $name = $record['name'] === '@' ? '@' : $record['name'];
            $ttl = (int) $record['ttl'];
            $type = $record['type'];
            $value = $record['value'];

            // Add trailing dot to FQDN values where needed
            if (in_array($type, ['CNAME', 'MX', 'NS'], true) && !str_ends_with($value, '.')) {
                $value .= '.';
            }

            if ($type === 'MX' || $type === 'SRV') {
                $priority = (int) ($record['priority'] ?? 10);
                $lines[] = sprintf("%-24s %d  IN  %-6s %d %s", $name, $ttl, $type, $priority, $value);
            } elseif ($type === 'TXT') {
                $lines[] = sprintf("%-24s %d  IN  %-6s \"%s\"", $name, $ttl, $type, $value);
            } else {
                $lines[] = sprintf("%-24s %d  IN  %-6s %s", $name, $ttl, $type, $value);
            }
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Parse a single line from a BIND zone file.
     * Returns null if unparseable.
     */
    private function parseZoneLine(string $line, string $domainName): ?array
    {
        // Remove inline comments
        $commentPos = strpos($line, ';');
        if ($commentPos !== false) {
            $line = trim(substr($line, 0, $commentPos));
        }

        if ($line === '') {
            return null;
        }

        // Normalize whitespace
        $parts = preg_split('/\s+/', $line);

        if (count($parts) < 3) {
            return null;
        }

        $name = '@';
        $ttl = 3600;
        $type = '';
        $value = '';
        $priority = null;

        $idx = 0;

        // First token might be a name or a TTL or IN
        if (!is_numeric($parts[$idx]) && strtoupper($parts[$idx]) !== 'IN' && !$this->isDnsType($parts[$idx])) {
            $name = rtrim($parts[$idx], '.');
            // If name equals the domain, replace with @
            if (strtolower($name) === strtolower($domainName)) {
                $name = '@';
            }
            // Remove domain suffix if present
            $suffix = '.' . strtolower($domainName);
            if (str_ends_with(strtolower($name), $suffix)) {
                $name = substr($name, 0, -strlen($suffix));
            }
            $idx++;
        }

        if ($idx >= count($parts)) {
            return null;
        }

        // Optional TTL
        if (is_numeric($parts[$idx])) {
            $ttl = (int) $parts[$idx];
            $idx++;
        }

        if ($idx >= count($parts)) {
            return null;
        }

        // Optional IN class
        if (strtoupper($parts[$idx]) === 'IN') {
            $idx++;
        }

        if ($idx >= count($parts)) {
            return null;
        }

        // Record type
        $type = strtoupper($parts[$idx]);
        $idx++;

        if ($idx >= count($parts)) {
            return null;
        }

        // For MX and SRV, next is priority
        if (in_array($type, ['MX', 'SRV'], true)) {
            if (is_numeric($parts[$idx])) {
                $priority = (int) $parts[$idx];
                $idx++;
            }
        }

        if ($idx >= count($parts)) {
            return null;
        }

        // Remaining parts are the value
        $value = implode(' ', array_slice($parts, $idx));

        // Remove trailing dot from value
        $value = rtrim($value, '.');

        // Remove quotes from TXT records
        if ($type === 'TXT') {
            $value = trim($value, '"');
        }

        return [
            'name' => $name ?: '@',
            'ttl' => max(60, min(86400, $ttl)),
            'type' => $type,
            'value' => $value,
            'priority' => $priority,
        ];
    }

    /**
     * Check if a string is a known DNS record type.
     */
    private function isDnsType(string $str): bool
    {
        return in_array(strtoupper($str), ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'SOA', 'PTR'], true);
    }

    // ==================== Webtropia Helpers ====================

    /**
     * Convert a local record name to Webtropia FQDN with trailing dot.
     * "@" -> "example.com.", "www" -> "www.example.com.", "*" -> "*.example.com."
     */
    private function toWebtropiaFqdn(string $name, string $domainName): string
    {
        if ($name === '@' || $name === '') {
            return $domainName . '.';
        }
        return $name . '.' . $domainName . '.';
    }

    /**
     * Convert a Webtropia FQDN (with trailing dot) to local relative name.
     * "example.com." -> "@", "www.example.com." -> "www", "*.example.com." -> "*"
     */
    private function fromWebtropiaFqdn(string $fqdn, string $domainName): string
    {
        $fqdn = rtrim($fqdn, '.');
        if (strtolower($fqdn) === strtolower($domainName)) {
            return '@';
        }
        $suffix = '.' . strtolower($domainName);
        if (str_ends_with(strtolower($fqdn), $suffix)) {
            return substr($fqdn, 0, -(strlen($domainName) + 1));
        }
        return $fqdn;
    }

    /**
     * Generate a deterministic composite key for a Webtropia record (used as external_id).
     * Uses MD5 hash to keep it within VARCHAR(100).
     */
    private function webtropiaRecordKey(array $record): string
    {
        $type    = strtoupper($record['type'] ?? '');
        $name    = rtrim($record['name'] ?? '', '.');
        $content = $record['content'] ?? '';
        return 'wt:' . md5("{$type}|{$name}|{$content}");
    }
}
