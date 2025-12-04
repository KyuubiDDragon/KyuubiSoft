<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Checkers;

class HttpChecker implements CheckerInterface
{
    public function check(array $monitor): CheckResult
    {
        $startTime = microtime(true);
        $status = 'down';
        $statusCode = null;
        $errorMessage = null;

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $monitor['url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => (int) ($monitor['timeout'] ?? 30),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_SSL_VERIFYPEER => $monitor['type'] === 'https',
                CURLOPT_NOBODY => false,
                CURLOPT_USERAGENT => 'KyuubiSoft Uptime Monitor/1.0',
            ]);

            $body = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if (curl_errno($ch)) {
                $errorMessage = curl_error($ch);
            } else {
                $expectedStatus = (int) ($monitor['expected_status_code'] ?? 200);
                if ($statusCode === $expectedStatus || ($expectedStatus === 200 && $statusCode >= 200 && $statusCode < 300)) {
                    if (!empty($monitor['expected_keyword'])) {
                        if (stripos($body, $monitor['expected_keyword']) !== false) {
                            $status = 'up';
                        } else {
                            $errorMessage = 'Expected keyword not found';
                        }
                    } else {
                        $status = 'up';
                    }
                } else {
                    $errorMessage = "Unexpected status code: {$statusCode}";
                }
            }

            curl_close($ch);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
        }

        return new CheckResult($status, $responseTime ?? 0, $statusCode, $errorMessage);
    }

    public static function getSupportedTypes(): array
    {
        return ['http', 'https'];
    }
}
