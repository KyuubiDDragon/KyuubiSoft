<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Checkers;

class TcpChecker implements CheckerInterface
{
    public function check(array $monitor): CheckResult
    {
        $startTime = microtime(true);
        $status = 'down';
        $errorMessage = null;

        $host = $monitor['hostname'] ?? parse_url($monitor['url'], PHP_URL_HOST) ?? $monitor['url'];
        $port = (int) ($monitor['port'] ?? 80);
        $timeout = (int) ($monitor['timeout'] ?? 30);

        try {
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if ($socket) {
                $status = 'up';
                fclose($socket);
            } else {
                $errorMessage = "Connection failed: {$errstr} (errno: {$errno})";
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
        }

        return new CheckResult($status, $responseTime ?? 0, null, $errorMessage, [
            'host' => $host,
            'port' => $port,
        ]);
    }

    public static function getSupportedTypes(): array
    {
        return ['tcp', 'port'];
    }
}
