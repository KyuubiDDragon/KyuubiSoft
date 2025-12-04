<?php

declare(strict_types=1);

namespace App\Modules\Tools\Controllers;

use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ToolsController
{
    /**
     * WHOIS Lookup
     */
    public function whois(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $domain = $params['domain'] ?? '';

        if (empty($domain)) {
            throw new ValidationException('Domain is required');
        }

        // Clean domain
        $domain = $this->cleanDomain($domain);

        try {
            $whoisData = $this->performWhoisLookup($domain);

            return JsonResponse::success([
                'domain' => $domain,
                'raw' => $whoisData,
                'parsed' => $this->parseWhoisData($whoisData),
                'queriedAt' => date('c'),
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('WHOIS lookup failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * SSL Certificate Check
     */
    public function sslCheck(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $domain = $params['domain'] ?? '';
        $port = (int) ($params['port'] ?? 443);

        if (empty($domain)) {
            throw new ValidationException('Domain is required');
        }

        $domain = $this->cleanDomain($domain);

        try {
            $result = $this->checkSslCertificate($domain, $port);

            return JsonResponse::success($result);
        } catch (\Exception $e) {
            return JsonResponse::error('SSL check failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DNS Lookup
     */
    public function dnsLookup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $domain = $params['domain'] ?? '';
        $type = strtoupper($params['type'] ?? 'A');

        if (empty($domain)) {
            throw new ValidationException('Domain is required');
        }

        $domain = $this->cleanDomain($domain);

        // Map record type to PHP constant
        $typeMap = [
            'A' => DNS_A,
            'AAAA' => DNS_AAAA,
            'CNAME' => DNS_CNAME,
            'MX' => DNS_MX,
            'TXT' => DNS_TXT,
            'NS' => DNS_NS,
            'SOA' => DNS_SOA,
            'PTR' => DNS_PTR,
            'SRV' => DNS_SRV,
            'ALL' => DNS_ALL,
        ];

        $dnsType = $typeMap[$type] ?? DNS_A;

        try {
            $records = @dns_get_record($domain, $dnsType);

            if ($records === false) {
                return JsonResponse::success([
                    'domain' => $domain,
                    'type' => $type,
                    'records' => [],
                    'status' => 'NXDOMAIN',
                    'queriedAt' => date('c'),
                ]);
            }

            return JsonResponse::success([
                'domain' => $domain,
                'type' => $type,
                'records' => $records,
                'count' => count($records),
                'status' => count($records) > 0 ? 'NOERROR' : 'NODATA',
                'queriedAt' => date('c'),
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('DNS lookup failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Ping Check
     */
    public function ping(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $host = $params['host'] ?? '';
        $count = min(10, max(1, (int) ($params['count'] ?? 4)));

        if (empty($host)) {
            throw new ValidationException('Host is required');
        }

        $host = $this->cleanDomain($host);

        try {
            $results = $this->performPing($host, $count);

            return JsonResponse::success($results);
        } catch (\Exception $e) {
            return JsonResponse::error('Ping failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Port Check (TCP)
     */
    public function portCheck(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $host = $params['host'] ?? '';
        $port = (int) ($params['port'] ?? 0);
        $timeout = min(30, max(1, (int) ($params['timeout'] ?? 5)));

        if (empty($host)) {
            throw new ValidationException('Host is required');
        }

        if ($port < 1 || $port > 65535) {
            throw new ValidationException('Invalid port number (1-65535)');
        }

        $host = $this->cleanDomain($host);

        $startTime = microtime(true);
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $responseTime = (int) ((microtime(true) - $startTime) * 1000);

        $isOpen = $socket !== false;

        if ($socket) {
            fclose($socket);
        }

        return JsonResponse::success([
            'host' => $host,
            'port' => $port,
            'open' => $isOpen,
            'responseTime' => $isOpen ? $responseTime : null,
            'error' => !$isOpen ? $errstr : null,
            'checkedAt' => date('c'),
        ]);
    }

    /**
     * HTTP Headers Check
     */
    public function httpHeaders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $url = $params['url'] ?? '';

        if (empty($url)) {
            throw new ValidationException('URL is required');
        }

        // Ensure URL has protocol
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }

        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'HEAD',
                    'timeout' => 10,
                    'follow_location' => 0,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $startTime = microtime(true);
            $headers = @get_headers($url, true, $context);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if ($headers === false) {
                return JsonResponse::error('Could not retrieve headers', 500);
            }

            // Parse status line
            $statusLine = $headers[0] ?? '';
            preg_match('/HTTP\/[\d.]+ (\d+)/', $statusLine, $matches);
            $statusCode = (int) ($matches[1] ?? 0);

            // Remove numeric keys (status lines from redirects)
            $cleanHeaders = [];
            foreach ($headers as $key => $value) {
                if (!is_numeric($key)) {
                    $cleanHeaders[$key] = $value;
                }
            }

            return JsonResponse::success([
                'url' => $url,
                'statusCode' => $statusCode,
                'statusLine' => $statusLine,
                'headers' => $cleanHeaders,
                'responseTime' => $responseTime,
                'checkedAt' => date('c'),
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('HTTP headers check failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * IP Geolocation (using ip-api.com free tier)
     */
    public function ipLookup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $ip = $params['ip'] ?? '';

        // If no IP provided, get client IP
        if (empty($ip)) {
            $ip = $this->getClientIp($request);
        }

        // Validate IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new ValidationException('Invalid IP address');
        }

        try {
            $context = stream_context_create([
                'http' => ['timeout' => 10],
            ]);

            $result = @file_get_contents(
                "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query",
                false,
                $context
            );

            if ($result === false) {
                throw new \Exception('Could not fetch IP information');
            }

            $data = json_decode($result, true);

            if ($data['status'] === 'fail') {
                throw new \Exception($data['message'] ?? 'IP lookup failed');
            }

            return JsonResponse::success([
                'ip' => $data['query'],
                'country' => $data['country'],
                'countryCode' => $data['countryCode'],
                'region' => $data['regionName'],
                'city' => $data['city'],
                'zip' => $data['zip'],
                'lat' => $data['lat'],
                'lon' => $data['lon'],
                'timezone' => $data['timezone'],
                'isp' => $data['isp'],
                'org' => $data['org'],
                'as' => $data['as'],
                'queriedAt' => date('c'),
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('IP lookup failed: ' . $e->getMessage(), 500);
        }
    }

    // ==================== Private Helper Methods ====================

    private function cleanDomain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        $domain = preg_replace('/\/.*$/', '', $domain);
        $domain = preg_replace('/:\d+$/', '', $domain);
        return strtolower($domain);
    }

    private function performWhoisLookup(string $domain): string
    {
        // Determine WHOIS server based on TLD
        $parts = explode('.', $domain);
        $tld = end($parts);

        $whoisServers = [
            'com' => 'whois.verisign-grs.com',
            'net' => 'whois.verisign-grs.com',
            'org' => 'whois.pir.org',
            'info' => 'whois.afilias.net',
            'biz' => 'whois.biz',
            'de' => 'whois.denic.de',
            'uk' => 'whois.nic.uk',
            'co.uk' => 'whois.nic.uk',
            'eu' => 'whois.eu',
            'io' => 'whois.nic.io',
            'me' => 'whois.nic.me',
            'tv' => 'whois.nic.tv',
            'cc' => 'ccwhois.verisign-grs.com',
            'us' => 'whois.nic.us',
            'ca' => 'whois.cira.ca',
            'au' => 'whois.auda.org.au',
            'nl' => 'whois.domain-registry.nl',
            'fr' => 'whois.nic.fr',
            'it' => 'whois.nic.it',
            'es' => 'whois.nic.es',
            'ch' => 'whois.nic.ch',
            'at' => 'whois.nic.at',
            'be' => 'whois.dns.be',
            'pl' => 'whois.dns.pl',
            'ru' => 'whois.tcinet.ru',
            'jp' => 'whois.jprs.jp',
            'cn' => 'whois.cnnic.cn',
            'in' => 'whois.registry.in',
            'br' => 'whois.registro.br',
            'xyz' => 'whois.nic.xyz',
            'online' => 'whois.nic.online',
            'site' => 'whois.nic.site',
            'app' => 'whois.nic.google',
            'dev' => 'whois.nic.google',
        ];

        $whoisServer = $whoisServers[$tld] ?? "whois.nic.{$tld}";

        // Connect to WHOIS server
        $socket = @fsockopen($whoisServer, 43, $errno, $errstr, 10);

        if (!$socket) {
            // Try fallback to whois.iana.org
            $socket = @fsockopen('whois.iana.org', 43, $errno, $errstr, 10);

            if (!$socket) {
                throw new \Exception("Could not connect to WHOIS server: {$errstr}");
            }
        }

        // Send query
        fwrite($socket, $domain . "\r\n");

        // Read response
        $response = '';
        while (!feof($socket)) {
            $response .= fgets($socket, 128);
        }

        fclose($socket);

        if (empty(trim($response))) {
            throw new \Exception('Empty response from WHOIS server');
        }

        return $response;
    }

    private function parseWhoisData(string $rawData): array
    {
        $parsed = [];
        $lines = explode("\n", $rawData);

        $fieldMappings = [
            'domain name' => 'domainName',
            'registrar' => 'registrar',
            'registrar whois server' => 'whoisServer',
            'registrar url' => 'registrarUrl',
            'creation date' => 'createdDate',
            'updated date' => 'updatedDate',
            'registry expiry date' => 'expiryDate',
            'expiration date' => 'expiryDate',
            'registrar registration expiration date' => 'expiryDate',
            'name server' => 'nameServers',
            'nserver' => 'nameServers',
            'status' => 'status',
            'domain status' => 'status',
            'registrant name' => 'registrantName',
            'registrant organization' => 'registrantOrg',
            'registrant country' => 'registrantCountry',
            'admin name' => 'adminName',
            'admin email' => 'adminEmail',
            'tech name' => 'techName',
            'tech email' => 'techEmail',
            'dnssec' => 'dnssec',
        ];

        foreach ($lines as $line) {
            $colonIndex = strpos($line, ':');
            if ($colonIndex === false) {
                continue;
            }

            $key = strtolower(trim(substr($line, 0, $colonIndex)));
            $value = trim(substr($line, $colonIndex + 1));

            if (empty($value)) {
                continue;
            }

            $mappedKey = $fieldMappings[$key] ?? null;

            if ($mappedKey) {
                if ($mappedKey === 'nameServers' || $mappedKey === 'status') {
                    if (!isset($parsed[$mappedKey])) {
                        $parsed[$mappedKey] = [];
                    }
                    $parsed[$mappedKey][] = $value;
                } elseif (!isset($parsed[$mappedKey])) {
                    $parsed[$mappedKey] = $value;
                }
            }
        }

        return $parsed;
    }

    private function checkSslCertificate(string $host, int $port): array
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $socket = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new \Exception("SSL connection failed: {$errstr}");
        }

        $params = stream_context_get_params($socket);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if (!$cert) {
            fclose($socket);
            throw new \Exception('Could not retrieve SSL certificate');
        }

        $certInfo = openssl_x509_parse($cert);

        if (!$certInfo) {
            fclose($socket);
            throw new \Exception('Could not parse SSL certificate');
        }

        $validFrom = $certInfo['validFrom_time_t'];
        $validTo = $certInfo['validTo_time_t'];
        $now = time();

        $daysUntilExpiry = (int) (($validTo - $now) / 86400);
        $isExpired = $validTo < $now;
        $isNotYetValid = $validFrom > $now;

        // Get issuer info
        $issuer = $certInfo['issuer'];
        $issuerName = $issuer['O'] ?? $issuer['CN'] ?? 'Unknown';

        // Get subject info
        $subject = $certInfo['subject'];
        $commonName = $subject['CN'] ?? 'Unknown';

        // Get SAN (Subject Alternative Names)
        $san = [];
        if (isset($certInfo['extensions']['subjectAltName'])) {
            preg_match_all('/DNS:([^,\s]+)/', $certInfo['extensions']['subjectAltName'], $matches);
            $san = $matches[1] ?? [];
        }

        fclose($socket);

        return [
            'domain' => $host,
            'port' => $port,
            'valid' => !$isExpired && !$isNotYetValid,
            'certificate' => [
                'commonName' => $commonName,
                'issuer' => $issuerName,
                'issuerDetails' => $issuer,
                'subject' => $subject,
                'validFrom' => date('Y-m-d H:i:s', $validFrom),
                'validTo' => date('Y-m-d H:i:s', $validTo),
                'daysUntilExpiry' => $daysUntilExpiry,
                'isExpired' => $isExpired,
                'isNotYetValid' => $isNotYetValid,
                'san' => $san,
                'serialNumber' => $certInfo['serialNumber'] ?? null,
                'signatureAlgorithm' => $certInfo['signatureTypeSN'] ?? null,
                'version' => $certInfo['version'] ?? null,
            ],
            'checkedAt' => date('c'),
        ];
    }

    private function performPing(string $host, int $count): array
    {
        $results = [
            'host' => $host,
            'count' => $count,
            'results' => [],
            'statistics' => null,
        ];

        // Resolve hostname first
        $ip = gethostbyname($host);
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            throw new \Exception("Could not resolve hostname: {$host}");
        }

        $results['resolvedIp'] = $ip;

        // Use exec to run ping command
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            $cmd = "ping -n {$count} " . escapeshellarg($host);
        } else {
            $cmd = "ping -c {$count} -W 5 " . escapeshellarg($host) . " 2>&1";
        }

        $output = [];
        $returnVar = 0;
        exec($cmd, $output, $returnVar);

        $rawOutput = implode("\n", $output);
        $results['raw'] = $rawOutput;

        // Parse ping results
        $times = [];
        foreach ($output as $line) {
            // Match time=XX.XX ms or time=XX ms
            if (preg_match('/time[=<](\d+\.?\d*)\s*ms/i', $line, $matches)) {
                $times[] = (float) $matches[1];
            }
        }

        if (!empty($times)) {
            $results['statistics'] = [
                'sent' => $count,
                'received' => count($times),
                'lost' => $count - count($times),
                'lossPercent' => round((($count - count($times)) / $count) * 100, 1),
                'min' => min($times),
                'max' => max($times),
                'avg' => round(array_sum($times) / count($times), 2),
            ];
            $results['reachable'] = true;
        } else {
            $results['statistics'] = [
                'sent' => $count,
                'received' => 0,
                'lost' => $count,
                'lossPercent' => 100,
            ];
            $results['reachable'] = false;
        }

        $results['checkedAt'] = date('c');

        return $results;
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();

        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $serverParams['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($serverParams['HTTP_X_REAL_IP'])) {
            return $serverParams['HTTP_X_REAL_IP'];
        }

        return $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
