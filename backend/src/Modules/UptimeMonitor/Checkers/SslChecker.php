<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Checkers;

/**
 * SSL Certificate Check
 * Verifies SSL certificate validity and expiration
 */
class SslChecker implements CheckerInterface
{
    public function check(array $monitor): CheckResult
    {
        $startTime = microtime(true);
        $status = 'down';
        $errorMessage = null;
        $data = [];

        $host = $monitor['hostname'] ?? parse_url($monitor['url'], PHP_URL_HOST) ?? $monitor['url'];
        $port = (int) ($monitor['port'] ?? 443);
        $timeout = (int) ($monitor['timeout'] ?? 30);
        $warnDays = (int) ($monitor['ssl_expiry_warn_days'] ?? 14);

        try {
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $socket = @stream_socket_client(
                "ssl://{$host}:{$port}",
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );

            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if (!$socket) {
                throw new \Exception("SSL connection failed: {$errstr}");
            }

            $params = stream_context_get_params($socket);
            $cert = $params['options']['ssl']['peer_certificate'] ?? null;

            if (!$cert) {
                throw new \Exception('Could not retrieve SSL certificate');
            }

            $certInfo = openssl_x509_parse($cert);

            if (!$certInfo) {
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

            $data = [
                'common_name' => $commonName,
                'issuer' => $issuerName,
                'valid_from' => date('Y-m-d H:i:s', $validFrom),
                'valid_to' => date('Y-m-d H:i:s', $validTo),
                'days_until_expiry' => $daysUntilExpiry,
                'is_expired' => $isExpired,
                'is_not_yet_valid' => $isNotYetValid,
                'san' => $san,
                'serial' => $certInfo['serialNumber'] ?? null,
                'signature_algorithm' => $certInfo['signatureTypeSN'] ?? null,
            ];

            if ($isExpired) {
                $errorMessage = 'Certificate has expired';
            } elseif ($isNotYetValid) {
                $errorMessage = 'Certificate is not yet valid';
            } elseif ($daysUntilExpiry <= $warnDays) {
                // Still mark as up, but include warning
                $status = 'up';
                $data['warning'] = "Certificate expires in {$daysUntilExpiry} days";
            } else {
                $status = 'up';
            }

            fclose($socket);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
        }

        return new CheckResult($status, $responseTime ?? 0, null, $errorMessage, $data);
    }

    public static function getSupportedTypes(): array
    {
        return ['ssl'];
    }
}
