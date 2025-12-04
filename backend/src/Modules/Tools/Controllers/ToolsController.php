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

    /**
     * Security Headers Check
     */
    public function securityHeaders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
                    'timeout' => 15,
                    'follow_location' => 1,
                    'max_redirects' => 5,
                    'ignore_errors' => true,
                    'user_agent' => 'Mozilla/5.0 (compatible; SecurityHeadersChecker/1.0)',
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $headers = @get_headers($url, true, $context);

            if ($headers === false) {
                return JsonResponse::error('Could not retrieve headers', 500);
            }

            // Normalize header keys to lowercase for consistent checking
            $normalizedHeaders = [];
            foreach ($headers as $key => $value) {
                if (!is_numeric($key)) {
                    $normalizedHeaders[strtolower($key)] = is_array($value) ? end($value) : $value;
                }
            }

            // Define security headers to check
            $securityChecks = $this->analyzeSecurityHeaders($normalizedHeaders);

            // Calculate score
            $maxScore = array_sum(array_column($securityChecks, 'weight'));
            $earnedScore = 0;
            foreach ($securityChecks as $check) {
                if ($check['present'] && $check['valid']) {
                    $earnedScore += $check['weight'];
                }
            }

            $grade = $this->calculateSecurityGrade($earnedScore, $maxScore);

            return JsonResponse::success([
                'url' => $url,
                'headers' => $normalizedHeaders,
                'checks' => $securityChecks,
                'score' => [
                    'earned' => $earnedScore,
                    'max' => $maxScore,
                    'percentage' => $maxScore > 0 ? round(($earnedScore / $maxScore) * 100) : 0,
                    'grade' => $grade,
                ],
                'checkedAt' => date('c'),
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Security headers check failed: ' . $e->getMessage(), 500);
        }
    }

    private function analyzeSecurityHeaders(array $headers): array
    {
        $checks = [];

        // Content-Security-Policy
        $csp = $headers['content-security-policy'] ?? null;
        $checks['content-security-policy'] = [
            'name' => 'Content-Security-Policy',
            'present' => $csp !== null,
            'value' => $csp,
            'valid' => $csp !== null && strlen($csp) > 10,
            'weight' => 25,
            'description' => 'Verhindert XSS und andere Code-Injection-Angriffe',
            'recommendation' => $csp === null ? "Setze einen CSP-Header, z.B.: default-src 'self'; script-src 'self'" : null,
        ];

        // Strict-Transport-Security
        $hsts = $headers['strict-transport-security'] ?? null;
        $hstsValid = $hsts !== null && preg_match('/max-age=(\d+)/', $hsts, $m) && (int)$m[1] >= 31536000;
        $checks['strict-transport-security'] = [
            'name' => 'Strict-Transport-Security',
            'present' => $hsts !== null,
            'value' => $hsts,
            'valid' => $hstsValid,
            'weight' => 20,
            'description' => 'Erzwingt HTTPS-Verbindungen',
            'recommendation' => $hsts === null ? 'Setze: max-age=31536000; includeSubDomains; preload' : ($hstsValid ? null : 'max-age sollte mindestens 31536000 (1 Jahr) sein'),
        ];

        // X-Content-Type-Options
        $xcto = $headers['x-content-type-options'] ?? null;
        $checks['x-content-type-options'] = [
            'name' => 'X-Content-Type-Options',
            'present' => $xcto !== null,
            'value' => $xcto,
            'valid' => strtolower($xcto ?? '') === 'nosniff',
            'weight' => 15,
            'description' => 'Verhindert MIME-Type-Sniffing',
            'recommendation' => $xcto === null ? 'Setze: nosniff' : null,
        ];

        // X-Frame-Options
        $xfo = $headers['x-frame-options'] ?? null;
        $xfoValid = $xfo !== null && in_array(strtoupper($xfo), ['DENY', 'SAMEORIGIN']);
        $checks['x-frame-options'] = [
            'name' => 'X-Frame-Options',
            'present' => $xfo !== null,
            'value' => $xfo,
            'valid' => $xfoValid,
            'weight' => 15,
            'description' => 'Verhindert Clickjacking durch Iframe-Einbettung',
            'recommendation' => $xfo === null ? 'Setze: DENY oder SAMEORIGIN' : null,
        ];

        // X-XSS-Protection (Legacy, but still checked)
        $xxss = $headers['x-xss-protection'] ?? null;
        $checks['x-xss-protection'] = [
            'name' => 'X-XSS-Protection',
            'present' => $xxss !== null,
            'value' => $xxss,
            'valid' => $xxss !== null,
            'weight' => 5,
            'description' => 'Legacy XSS-Filter (veraltet, CSP bevorzugen)',
            'recommendation' => null, // Not critical
        ];

        // Referrer-Policy
        $rp = $headers['referrer-policy'] ?? null;
        $rpValid = $rp !== null && in_array(strtolower($rp), ['no-referrer', 'no-referrer-when-downgrade', 'origin', 'origin-when-cross-origin', 'same-origin', 'strict-origin', 'strict-origin-when-cross-origin']);
        $checks['referrer-policy'] = [
            'name' => 'Referrer-Policy',
            'present' => $rp !== null,
            'value' => $rp,
            'valid' => $rpValid,
            'weight' => 10,
            'description' => 'Kontrolliert Referrer-Informationen',
            'recommendation' => $rp === null ? 'Setze: strict-origin-when-cross-origin' : null,
        ];

        // Permissions-Policy (formerly Feature-Policy)
        $pp = $headers['permissions-policy'] ?? $headers['feature-policy'] ?? null;
        $checks['permissions-policy'] = [
            'name' => 'Permissions-Policy',
            'present' => $pp !== null,
            'value' => $pp,
            'valid' => $pp !== null && strlen($pp) > 5,
            'weight' => 10,
            'description' => 'Kontrolliert Browser-Features (Kamera, Mikrofon, etc.)',
            'recommendation' => $pp === null ? 'Setze: geolocation=(), microphone=(), camera=()' : null,
        ];

        // Cross-Origin headers
        $coep = $headers['cross-origin-embedder-policy'] ?? null;
        $checks['cross-origin-embedder-policy'] = [
            'name' => 'Cross-Origin-Embedder-Policy',
            'present' => $coep !== null,
            'value' => $coep,
            'valid' => $coep !== null,
            'weight' => 5,
            'description' => 'Isoliert Dokument von Cross-Origin-Ressourcen',
            'recommendation' => null,
        ];

        $coop = $headers['cross-origin-opener-policy'] ?? null;
        $checks['cross-origin-opener-policy'] = [
            'name' => 'Cross-Origin-Opener-Policy',
            'present' => $coop !== null,
            'value' => $coop,
            'valid' => $coop !== null,
            'weight' => 5,
            'description' => 'Isoliert Browsing-Context-Gruppe',
            'recommendation' => null,
        ];

        // Check for server info disclosure (negative points conceptually)
        $server = $headers['server'] ?? null;
        $serverSafe = $server === null || !preg_match('/\d+\.\d+/', $server);
        $checks['server'] = [
            'name' => 'Server (Information Disclosure)',
            'present' => $server !== null,
            'value' => $server,
            'valid' => $serverSafe,
            'weight' => 0, // Informational
            'description' => 'Server-Version sollte nicht offengelegt werden',
            'recommendation' => $server !== null && !$serverSafe ? 'Verstecke Server-Versionsinformationen' : null,
        ];

        $xPoweredBy = $headers['x-powered-by'] ?? null;
        $checks['x-powered-by'] = [
            'name' => 'X-Powered-By (Information Disclosure)',
            'present' => $xPoweredBy !== null,
            'value' => $xPoweredBy,
            'valid' => $xPoweredBy === null,
            'weight' => 0, // Informational, negative if present
            'description' => 'Sollte entfernt werden - offenbart Technologie-Stack',
            'recommendation' => $xPoweredBy !== null ? 'Entferne den X-Powered-By Header' : null,
        ];

        return $checks;
    }

    private function calculateSecurityGrade(int $earned, int $max): string
    {
        if ($max === 0) return 'F';

        $percentage = ($earned / $max) * 100;

        if ($percentage >= 90) return 'A+';
        if ($percentage >= 80) return 'A';
        if ($percentage >= 70) return 'B';
        if ($percentage >= 60) return 'C';
        if ($percentage >= 50) return 'D';
        return 'F';
    }

    /**
     * Open Graph / Meta Tags Preview
     */
    public function openGraph(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
            // Use cURL for better reliability
            $html = $this->fetchUrlWithCurl($url);

            if ($html === false || empty($html)) {
                return JsonResponse::error('Could not fetch URL', 500);
            }

            // Limit HTML size to prevent memory issues
            $html = substr($html, 0, 500000);

            // Parse meta tags
            $metaTags = $this->parseMetaTags($html);

            // Extract Open Graph data
            $og = $this->extractOpenGraph($metaTags);

            // Extract Twitter Card data
            $twitter = $this->extractTwitterCard($metaTags);

            // Extract basic meta
            $basic = $this->extractBasicMeta($metaTags, $html);

            // Detect favicon
            $favicon = $this->extractFavicon($html, $url);

            return JsonResponse::success([
                'url' => $url,
                'basic' => $basic,
                'openGraph' => $og,
                'twitter' => $twitter,
                'favicon' => $favicon,
                'allMeta' => $metaTags,
                'checkedAt' => date('c'),
            ]);
        } catch (\Exception $e) {
            return JsonResponse::error('Open Graph check failed: ' . $e->getMessage(), 500);
        }
    }

    private function fetchUrlWithCurl(string $url): string|false
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
                'Cache-Control: no-cache',
            ],
            CURLOPT_ENCODING => '', // Accept all encodings (gzip, deflate, etc.)
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error || $httpCode >= 400) {
            return false;
        }

        return $html;
    }

    private function parseMetaTags(string $html): array
    {
        $tags = [];

        // Match meta tags
        preg_match_all('/<meta\s+([^>]+)>/i', $html, $matches);

        foreach ($matches[1] as $attributes) {
            $tag = [];

            // Extract name/property and content
            if (preg_match('/(?:name|property)\s*=\s*["\']([^"\']+)["\']/i', $attributes, $m)) {
                $tag['name'] = $m[1];
            }
            if (preg_match('/content\s*=\s*["\']([^"\']*)["\']?/i', $attributes, $m)) {
                $tag['content'] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if (preg_match('/charset\s*=\s*["\']?([^"\'\s>]+)/i', $attributes, $m)) {
                $tag['charset'] = $m[1];
            }
            if (preg_match('/http-equiv\s*=\s*["\']([^"\']+)["\']/i', $attributes, $m)) {
                $tag['http-equiv'] = $m[1];
            }

            if (!empty($tag)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    private function extractOpenGraph(array $metaTags): array
    {
        $og = [];

        foreach ($metaTags as $tag) {
            $name = $tag['name'] ?? '';
            if (str_starts_with($name, 'og:')) {
                $key = substr($name, 3);
                $og[$key] = $tag['content'] ?? '';
            }
        }

        return $og;
    }

    private function extractTwitterCard(array $metaTags): array
    {
        $twitter = [];

        foreach ($metaTags as $tag) {
            $name = $tag['name'] ?? '';
            if (str_starts_with($name, 'twitter:')) {
                $key = substr($name, 8);
                $twitter[$key] = $tag['content'] ?? '';
            }
        }

        return $twitter;
    }

    private function extractBasicMeta(array $metaTags, string $html): array
    {
        $basic = [
            'title' => null,
            'description' => null,
            'keywords' => null,
            'author' => null,
            'robots' => null,
            'viewport' => null,
            'canonical' => null,
            'language' => null,
        ];

        // Extract title from <title> tag
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            $basic['title'] = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Extract canonical link
        if (preg_match('/<link[^>]+rel\s*=\s*["\']canonical["\'][^>]+href\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
            $basic['canonical'] = $m[1];
        }

        // Extract language
        if (preg_match('/<html[^>]+lang\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
            $basic['language'] = $m[1];
        }

        // Extract from meta tags
        foreach ($metaTags as $tag) {
            $name = strtolower($tag['name'] ?? '');
            $content = $tag['content'] ?? '';

            switch ($name) {
                case 'description':
                    $basic['description'] = $content;
                    break;
                case 'keywords':
                    $basic['keywords'] = $content;
                    break;
                case 'author':
                    $basic['author'] = $content;
                    break;
                case 'robots':
                    $basic['robots'] = $content;
                    break;
                case 'viewport':
                    $basic['viewport'] = $content;
                    break;
            }
        }

        return $basic;
    }

    private function extractFavicon(string $html, string $baseUrl): ?string
    {
        // Try to find favicon in HTML
        $patterns = [
            '/<link[^>]+rel\s*=\s*["\'](?:shortcut )?icon["\'][^>]+href\s*=\s*["\']([^"\']+)["\']/i',
            '/<link[^>]+href\s*=\s*["\']([^"\']+)["\'][^>]+rel\s*=\s*["\'](?:shortcut )?icon["\']/i',
            '/<link[^>]+rel\s*=\s*["\']apple-touch-icon["\'][^>]+href\s*=\s*["\']([^"\']+)["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $favicon = $m[1];
                // Make absolute URL
                if (!preg_match('/^https?:\/\//', $favicon)) {
                    $parsed = parse_url($baseUrl);
                    $base = $parsed['scheme'] . '://' . $parsed['host'];
                    if (str_starts_with($favicon, '/')) {
                        $favicon = $base . $favicon;
                    } else {
                        $favicon = $base . '/' . $favicon;
                    }
                }
                return $favicon;
            }
        }

        // Default to /favicon.ico
        $parsed = parse_url($baseUrl);
        return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . '/favicon.ico';
    }
}
