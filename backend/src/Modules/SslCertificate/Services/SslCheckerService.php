<?php

declare(strict_types=1);

namespace App\Modules\SslCertificate\Services;

/**
 * Service for checking SSL certificates
 */
class SslCheckerService
{
    /**
     * Check SSL certificate for a host
     */
    public function checkCertificate(string $hostname, int $port = 443): array
    {
        $startTime = microtime(true);

        try {
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'capture_peer_cert_chain' => true,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    'SNI_enabled' => true,
                    'peer_name' => $hostname,
                ],
            ]);

            $client = @stream_socket_client(
                "ssl://{$hostname}:{$port}",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$client) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'error_message' => "Connection failed: {$errstr} ({$errno})",
                    'response_time_ms' => $this->getElapsedMs($startTime),
                ];
            }

            $params = stream_context_get_params($client);
            fclose($client);

            if (!isset($params['options']['ssl']['peer_certificate'])) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'error_message' => 'No certificate received',
                    'response_time_ms' => $this->getElapsedMs($startTime),
                ];
            }

            $cert = $params['options']['ssl']['peer_certificate'];
            $certInfo = openssl_x509_parse($cert);

            if (!$certInfo) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'error_message' => 'Failed to parse certificate',
                    'response_time_ms' => $this->getElapsedMs($startTime),
                ];
            }

            // Extract certificate details
            $validFrom = new \DateTime('@' . $certInfo['validFrom_time_t']);
            $validUntil = new \DateTime('@' . $certInfo['validTo_time_t']);
            $now = new \DateTime();
            $daysUntilExpiry = $now->diff($validUntil)->days;
            if ($validUntil < $now) {
                $daysUntilExpiry = -$daysUntilExpiry;
            }

            // Get fingerprints
            openssl_x509_export($cert, $certPem);
            $fingerprintSha256 = openssl_x509_fingerprint($cert, 'sha256');
            $fingerprintSha1 = openssl_x509_fingerprint($cert, 'sha1');

            // Extract issuer
            $issuerParts = [];
            if (isset($certInfo['issuer']['CN'])) {
                $issuerParts[] = $certInfo['issuer']['CN'];
            }
            if (isset($certInfo['issuer']['O'])) {
                $issuerParts[] = $certInfo['issuer']['O'];
            }
            $issuer = implode(' - ', $issuerParts);

            // Extract subject
            $subject = $certInfo['subject']['CN'] ?? $hostname;

            // Extract SANs (Subject Alternative Names)
            $sanDomains = [];
            if (isset($certInfo['extensions']['subjectAltName'])) {
                $sans = explode(', ', $certInfo['extensions']['subjectAltName']);
                foreach ($sans as $san) {
                    if (str_starts_with($san, 'DNS:')) {
                        $sanDomains[] = substr($san, 4);
                    }
                }
            }

            // Check certificate chain
            $chain = $params['options']['ssl']['peer_certificate_chain'] ?? [];
            $chainInfo = [];
            foreach ($chain as $chainCert) {
                $chainCertInfo = openssl_x509_parse($chainCert);
                if ($chainCertInfo) {
                    $chainInfo[] = [
                        'subject' => $chainCertInfo['subject']['CN'] ?? 'Unknown',
                        'issuer' => $chainCertInfo['issuer']['CN'] ?? 'Unknown',
                        'valid_until' => date('Y-m-d H:i:s', $chainCertInfo['validTo_time_t']),
                    ];
                }
            }

            // Determine status
            $status = 'valid';
            if ($daysUntilExpiry < 0) {
                $status = 'expired';
            } elseif ($daysUntilExpiry <= 7) {
                $status = 'expiring_soon'; // Critical
            } elseif ($daysUntilExpiry <= 30) {
                $status = 'expiring_soon'; // Warning
            }

            return [
                'success' => true,
                'status' => $status,
                'issuer' => $issuer,
                'subject' => $subject,
                'serial_number' => $certInfo['serialNumberHex'] ?? null,
                'valid_from' => $validFrom->format('Y-m-d H:i:s'),
                'valid_until' => $validUntil->format('Y-m-d H:i:s'),
                'days_until_expiry' => $daysUntilExpiry,
                'fingerprint_sha256' => $fingerprintSha256,
                'fingerprint_sha1' => $fingerprintSha1,
                'san_domains' => $sanDomains,
                'chain_valid' => count($chain) > 0,
                'chain_depth' => count($chain),
                'chain_info' => $chainInfo,
                'response_time_ms' => $this->getElapsedMs($startTime),
                'error_message' => null,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'response_time_ms' => $this->getElapsedMs($startTime),
            ];
        }
    }

    /**
     * Get certificate info without connecting (for display purposes)
     */
    public function getCertificateDetails(string $hostname, int $port = 443): ?array
    {
        $result = $this->checkCertificate($hostname, $port);

        if (!$result['success']) {
            return null;
        }

        return $result;
    }

    /**
     * Check multiple certificates in parallel
     */
    public function checkMultiple(array $certificates): array
    {
        $results = [];

        foreach ($certificates as $cert) {
            $results[$cert['id']] = $this->checkCertificate(
                $cert['hostname'],
                $cert['port'] ?? 443
            );
        }

        return $results;
    }

    /**
     * Determine notification type based on days until expiry
     */
    public function getNotificationType(int $daysUntilExpiry, int $warnDays, int $criticalDays): ?string
    {
        if ($daysUntilExpiry < 0) {
            return 'expired';
        }

        if ($daysUntilExpiry <= $criticalDays) {
            return 'critical';
        }

        if ($daysUntilExpiry <= $warnDays) {
            return 'warning';
        }

        return null;
    }

    private function getElapsedMs(float $startTime): int
    {
        return (int) ((microtime(true) - $startTime) * 1000);
    }
}
