<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Checkers;

/**
 * Source Engine Query Protocol (A2S)
 * Works with: CS2, CS:GO, Garry's Mod, TF2, Rust, ARK, etc.
 */
class SourceChecker implements CheckerInterface
{
    private const A2S_INFO = "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00";
    private const A2S_PLAYER = "\xFF\xFF\xFF\xFF\x55";
    private const A2S_RULES = "\xFF\xFF\xFF\xFF\x56";

    public function check(array $monitor): CheckResult
    {
        $startTime = microtime(true);
        $status = 'down';
        $errorMessage = null;
        $data = [];

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

            // Send A2S_INFO query
            socket_sendto($socket, self::A2S_INFO, strlen(self::A2S_INFO), 0, $host, $port);

            $buffer = '';
            $from = '';
            $fromPort = 0;
            $received = @socket_recvfrom($socket, $buffer, 4096, 0, $from, $fromPort);

            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if ($received && strlen($buffer) > 5) {
                // Check for challenge response (new Source servers)
                if (ord($buffer[4]) === 0x41) {
                    // Challenge response, need to resend with challenge number
                    $challenge = substr($buffer, 5, 4);
                    $newQuery = self::A2S_INFO . $challenge;
                    socket_sendto($socket, $newQuery, strlen($newQuery), 0, $host, $port);
                    $received = @socket_recvfrom($socket, $buffer, 4096, 0, $from, $fromPort);
                }

                if ($received && strlen($buffer) > 5) {
                    $response = $this->parseA2SInfo($buffer);
                    if ($response) {
                        $status = 'up';
                        $data = $response;
                    }
                }
            }

            socket_close($socket);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
        }

        return new CheckResult($status, $responseTime ?? 0, null, $errorMessage, $data);
    }

    private function parseA2SInfo(string $buffer): ?array
    {
        $pos = 4; // Skip header

        $type = ord($buffer[$pos++]);

        // Old GoldSource response (0x6D) or new Source response (0x49)
        if ($type !== 0x49 && $type !== 0x6D) {
            return null;
        }

        $result = [];

        if ($type === 0x49) {
            // Source Engine response
            $result['protocol'] = ord($buffer[$pos++]);
            $result['name'] = $this->readString($buffer, $pos);
            $result['map'] = $this->readString($buffer, $pos);
            $result['folder'] = $this->readString($buffer, $pos);
            $result['game'] = $this->readString($buffer, $pos);
            $result['app_id'] = unpack('v', substr($buffer, $pos, 2))[1];
            $pos += 2;
            $result['players_online'] = ord($buffer[$pos++]);
            $result['players_max'] = ord($buffer[$pos++]);
            $result['bots'] = ord($buffer[$pos++]);
            $result['server_type'] = chr(ord($buffer[$pos++])); // d = dedicated, l = listen, p = proxy
            $result['environment'] = chr(ord($buffer[$pos++])); // l = Linux, w = Windows, m = Mac
            $result['visibility'] = ord($buffer[$pos++]); // 0 = public, 1 = private
            $result['vac'] = ord($buffer[$pos++]); // 0 = unsecured, 1 = secured
        } else {
            // GoldSource response
            $result['address'] = $this->readString($buffer, $pos);
            $result['name'] = $this->readString($buffer, $pos);
            $result['map'] = $this->readString($buffer, $pos);
            $result['folder'] = $this->readString($buffer, $pos);
            $result['game'] = $this->readString($buffer, $pos);
            $result['players_online'] = ord($buffer[$pos++]);
            $result['players_max'] = ord($buffer[$pos++]);
            $result['protocol'] = ord($buffer[$pos++]);
        }

        return $result;
    }

    private function readString(string $buffer, int &$pos): string
    {
        $end = strpos($buffer, "\x00", $pos);
        if ($end === false) {
            return '';
        }
        $string = substr($buffer, $pos, $end - $pos);
        $pos = $end + 1;
        return $string;
    }

    public static function getSupportedTypes(): array
    {
        return ['source'];
    }
}
