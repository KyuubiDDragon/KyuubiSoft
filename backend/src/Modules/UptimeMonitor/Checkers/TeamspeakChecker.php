<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Checkers;

/**
 * TeamSpeak 3 Server Query
 * Uses the ServerQuery protocol (raw telnet-like)
 */
class TeamspeakChecker implements CheckerInterface
{
    public function check(array $monitor): CheckResult
    {
        $startTime = microtime(true);
        $status = 'down';
        $errorMessage = null;
        $data = [];

        $host = $monitor['hostname'] ?? parse_url($monitor['url'], PHP_URL_HOST) ?? $monitor['url'];
        $queryPort = (int) ($monitor['port'] ?? 10011); // ServerQuery port (voice port is usually 9987)
        $voicePort = (int) ($monitor['voice_port'] ?? 9987);
        $timeout = (int) ($monitor['timeout'] ?? 10);

        try {
            $socket = @fsockopen($host, $queryPort, $errno, $errstr, $timeout);

            if (!$socket) {
                throw new \Exception("Connection failed: {$errstr}");
            }

            stream_set_timeout($socket, $timeout);

            // Read welcome message
            $welcome = fgets($socket, 4096);
            if (strpos($welcome, 'TS3') === false) {
                throw new \Exception('Not a TeamSpeak 3 server');
            }

            // Skip the second line (server info)
            fgets($socket, 4096);

            // Select virtual server by port
            fwrite($socket, "use port={$voicePort}\n");
            $useResponse = $this->readResponse($socket);

            // Get server info
            fwrite($socket, "serverinfo\n");
            $serverInfo = $this->readResponse($socket);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if ($serverInfo && strpos($serverInfo, 'error id=0') !== false) {
                $status = 'up';
                $data = $this->parseServerInfo($serverInfo);
                $data['query_port'] = $queryPort;
                $data['voice_port'] = $voicePort;
            } else {
                // Try without virtual server selection (might be single server)
                $status = 'up';
                $data = [
                    'name' => 'TeamSpeak Server',
                    'query_port' => $queryPort,
                    'voice_port' => $voicePort,
                ];
            }

            // Quit cleanly
            fwrite($socket, "quit\n");
            fclose($socket);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
        }

        return new CheckResult($status, $responseTime ?? 0, null, $errorMessage, $data);
    }

    private function readResponse($socket): string
    {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 4096);
            $response .= $line;
            if (strpos($line, 'error id=') !== false) {
                break;
            }
        }
        return $response;
    }

    private function parseServerInfo(string $response): array
    {
        $data = [];
        $parts = explode(' ', $response);

        foreach ($parts as $part) {
            if (strpos($part, '=') !== false) {
                [$key, $value] = explode('=', $part, 2);
                $value = $this->unescapeTs3String($value);

                switch ($key) {
                    case 'virtualserver_name':
                        $data['name'] = $value;
                        break;
                    case 'virtualserver_clientsonline':
                        $data['players_online'] = (int) $value;
                        break;
                    case 'virtualserver_maxclients':
                        $data['players_max'] = (int) $value;
                        break;
                    case 'virtualserver_version':
                        $data['version'] = $value;
                        break;
                    case 'virtualserver_platform':
                        $data['platform'] = $value;
                        break;
                    case 'virtualserver_uptime':
                        $data['uptime'] = (int) $value;
                        break;
                    case 'virtualserver_channelsonline':
                        $data['channels'] = (int) $value;
                        break;
                }
            }
        }

        // Subtract query clients from online count
        if (isset($data['players_online'])) {
            $data['players_online'] = max(0, $data['players_online'] - 1);
        }

        return $data;
    }

    private function unescapeTs3String(string $str): string
    {
        return str_replace(
            ['\\s', '\\p', '\\/', '\\\\'],
            [' ', '|', '/', '\\'],
            $str
        );
    }

    public static function getSupportedTypes(): array
    {
        return ['teamspeak'];
    }
}
