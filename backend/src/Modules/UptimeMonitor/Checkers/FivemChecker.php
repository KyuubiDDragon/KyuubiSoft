<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Checkers;

/**
 * FiveM/RedM Server Query
 * Uses the HTTP API endpoint that FiveM servers expose
 */
class FivemChecker implements CheckerInterface
{
    public function check(array $monitor): CheckResult
    {
        $startTime = microtime(true);
        $status = 'down';
        $errorMessage = null;
        $data = [];

        $host = $monitor['hostname'] ?? parse_url($monitor['url'], PHP_URL_HOST) ?? $monitor['url'];
        $port = (int) ($monitor['port'] ?? 30120);
        $timeout = (int) ($monitor['timeout'] ?? 10);

        try {
            // FiveM exposes a JSON API at /info.json and /players.json
            $infoUrl = "http://{$host}:{$port}/info.json";
            $playersUrl = "http://{$host}:{$port}/players.json";
            $dynamicUrl = "http://{$host}:{$port}/dynamic.json";

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $infoUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'KyuubiSoft Uptime Monitor/1.0',
            ]);

            $infoResponse = curl_exec($ch);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode === 200 && $infoResponse) {
                $info = json_decode($infoResponse, true);

                // Get players
                curl_setopt($ch, CURLOPT_URL, $playersUrl);
                $playersResponse = curl_exec($ch);
                $players = json_decode($playersResponse, true);

                // Get dynamic info (sv_maxclients etc.)
                curl_setopt($ch, CURLOPT_URL, $dynamicUrl);
                $dynamicResponse = curl_exec($ch);
                $dynamic = json_decode($dynamicResponse, true);

                $status = 'up';
                $data = [
                    'name' => $info['vars']['sv_hostname'] ?? $info['server'] ?? 'Unknown',
                    'game_type' => $info['vars']['gametype'] ?? 'FiveM',
                    'map' => $info['vars']['mapname'] ?? 'Unknown',
                    'players_online' => is_array($players) ? count($players) : 0,
                    'players_max' => (int) ($dynamic['sv_maxclients'] ?? $info['vars']['sv_maxClients'] ?? 32),
                    'version' => $info['version'] ?? 'Unknown',
                    'resources' => isset($info['resources']) ? count($info['resources']) : 0,
                    'one_sync' => $info['vars']['onesync_enabled'] ?? false,
                ];

                // Add player list if available
                if (is_array($players) && count($players) > 0) {
                    $data['player_list'] = array_map(fn($p) => [
                        'name' => $p['name'] ?? 'Unknown',
                        'id' => $p['id'] ?? 0,
                        'ping' => $p['ping'] ?? 0,
                    ], array_slice($players, 0, 50));
                }
            } else {
                $errorMessage = "Server returned HTTP {$httpCode}";
            }

            curl_close($ch);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
        }

        return new CheckResult($status, $responseTime ?? 0, null, $errorMessage, $data);
    }

    public static function getSupportedTypes(): array
    {
        return ['fivem'];
    }
}
