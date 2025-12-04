<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Checkers;

class PingChecker implements CheckerInterface
{
    public function check(array $monitor): CheckResult
    {
        $startTime = microtime(true);
        $status = 'down';
        $errorMessage = null;
        $responseTime = null;

        $host = $monitor['hostname'] ?? parse_url($monitor['url'], PHP_URL_HOST) ?? $monitor['url'];
        $timeout = (int) ($monitor['timeout'] ?? 10);

        // Determine OS and set ping command accordingly
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            $cmd = sprintf('ping -n 1 -w %d %s', $timeout * 1000, escapeshellarg($host));
        } else {
            $cmd = sprintf('ping -c 1 -W %d %s 2>&1', $timeout, escapeshellarg($host));
        }

        try {
            exec($cmd, $output, $returnCode);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if ($returnCode === 0) {
                $status = 'up';

                // Try to extract ping time from output
                $outputStr = implode("\n", $output);
                if (preg_match('/time[=<](\d+\.?\d*)\s*ms/i', $outputStr, $matches)) {
                    $responseTime = (int) round((float) $matches[1]);
                }
            } else {
                $errorMessage = 'Host unreachable';
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
        }

        return new CheckResult($status, $responseTime ?? 0, null, $errorMessage, [
            'host' => $host,
        ]);
    }

    public static function getSupportedTypes(): array
    {
        return ['ping'];
    }
}
