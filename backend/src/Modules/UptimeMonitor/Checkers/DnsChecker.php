<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Checkers;

/**
 * DNS Resolution Check
 * Verifies that a domain resolves correctly
 */
class DnsChecker implements CheckerInterface
{
    public function check(array $monitor): CheckResult
    {
        $startTime = microtime(true);
        $status = 'down';
        $errorMessage = null;
        $data = [];

        $host = $monitor['hostname'] ?? parse_url($monitor['url'], PHP_URL_HOST) ?? $monitor['url'];
        $recordType = $monitor['dns_record_type'] ?? 'A';

        try {
            // Map record type to PHP constant
            $typeMap = [
                'A' => DNS_A,
                'AAAA' => DNS_AAAA,
                'CNAME' => DNS_CNAME,
                'MX' => DNS_MX,
                'TXT' => DNS_TXT,
                'NS' => DNS_NS,
            ];

            $dnsType = $typeMap[$recordType] ?? DNS_A;

            $records = @dns_get_record($host, $dnsType);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if ($records && count($records) > 0) {
                $status = 'up';
                $data = [
                    'record_type' => $recordType,
                    'records_found' => count($records),
                    'records' => array_map(function ($record) use ($recordType) {
                        switch ($recordType) {
                            case 'A':
                                return $record['ip'] ?? null;
                            case 'AAAA':
                                return $record['ipv6'] ?? null;
                            case 'CNAME':
                                return $record['target'] ?? null;
                            case 'MX':
                                return ['host' => $record['target'] ?? null, 'priority' => $record['pri'] ?? 0];
                            case 'TXT':
                                return $record['txt'] ?? null;
                            case 'NS':
                                return $record['target'] ?? null;
                            default:
                                return $record;
                        }
                    }, $records),
                    'ttl' => $records[0]['ttl'] ?? null,
                ];
            } else {
                $errorMessage = "No {$recordType} records found for {$host}";
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
        }

        return new CheckResult($status, $responseTime ?? 0, null, $errorMessage, $data);
    }

    public static function getSupportedTypes(): array
    {
        return ['dns'];
    }
}
