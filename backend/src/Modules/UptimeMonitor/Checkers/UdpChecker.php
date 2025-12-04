<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Checkers;

class UdpChecker implements CheckerInterface
{
    public function check(array $monitor): CheckResult
    {
        $startTime = microtime(true);
        $status = 'down';
        $errorMessage = null;

        $host = $monitor['hostname'] ?? parse_url($monitor['url'], PHP_URL_HOST) ?? $monitor['url'];
        $port = (int) ($monitor['port'] ?? 27015);
        $timeout = (int) ($monitor['timeout'] ?? 10);

        try {
            $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if (!$socket) {
                throw new \Exception('Failed to create UDP socket');
            }

            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $timeout, 'usec' => 0]);
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $timeout, 'usec' => 0]);

            // Send a simple probe packet
            $probe = "\xFF\xFF\xFF\xFF";
            if (@socket_sendto($socket, $probe, strlen($probe), 0, $host, $port)) {
                // Try to receive a response (optional for UDP)
                $buf = '';
                $from = '';
                $fromPort = 0;

                // UDP is connectionless, so we consider it "up" if we can send
                // Some servers respond, some don't
                @socket_recvfrom($socket, $buf, 2048, 0, $from, $fromPort);
                $status = 'up';
            }

            socket_close($socket);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
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
        return ['udp'];
    }
}
