<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Checkers;

class MinecraftChecker implements CheckerInterface
{
    public function check(array $monitor): CheckResult
    {
        $startTime = microtime(true);
        $status = 'down';
        $errorMessage = null;
        $data = [];

        $host = $monitor['hostname'] ?? parse_url($monitor['url'], PHP_URL_HOST) ?? $monitor['url'];
        $port = (int) ($monitor['port'] ?? 25565);
        $timeout = (int) ($monitor['timeout'] ?? 10);

        try {
            // Connect to Minecraft server using the new protocol (1.7+)
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

            if (!$socket) {
                throw new \Exception("Connection failed: {$errstr}");
            }

            stream_set_timeout($socket, $timeout);

            // Build handshake packet
            $handshake = $this->buildHandshakePacket($host, $port);
            fwrite($socket, $handshake);

            // Build status request packet
            $statusRequest = $this->buildPacket(0x00, '');
            fwrite($socket, $statusRequest);

            // Read response
            $response = $this->readPacket($socket);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if ($response) {
                $jsonData = json_decode($response, true);
                if ($jsonData) {
                    $status = 'up';
                    $data = [
                        'players_online' => $jsonData['players']['online'] ?? 0,
                        'players_max' => $jsonData['players']['max'] ?? 0,
                        'version' => $jsonData['version']['name'] ?? 'Unknown',
                        'protocol' => $jsonData['version']['protocol'] ?? 0,
                        'motd' => $this->cleanMotd($jsonData['description'] ?? ''),
                        'favicon' => isset($jsonData['favicon']) ? true : false,
                    ];
                }
            }

            fclose($socket);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
        }

        return new CheckResult($status, $responseTime ?? 0, null, $errorMessage, $data);
    }

    private function buildHandshakePacket(string $host, int $port): string
    {
        $data = '';
        $data .= $this->writeVarInt(47); // Protocol version (1.8.x)
        $data .= $this->writeVarInt(strlen($host)) . $host;
        $data .= pack('n', $port);
        $data .= $this->writeVarInt(1); // Next state: status

        return $this->buildPacket(0x00, $data);
    }

    private function buildPacket(int $packetId, string $data): string
    {
        $packet = $this->writeVarInt($packetId) . $data;
        return $this->writeVarInt(strlen($packet)) . $packet;
    }

    private function writeVarInt(int $value): string
    {
        $result = '';
        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            if ($value !== 0) {
                $byte |= 0x80;
            }
            $result .= chr($byte);
        } while ($value !== 0);
        return $result;
    }

    private function readVarInt($socket): int
    {
        $value = 0;
        $position = 0;

        while (true) {
            $byte = ord(fread($socket, 1));
            $value |= ($byte & 0x7F) << $position;
            if (($byte & 0x80) === 0) {
                break;
            }
            $position += 7;
            if ($position >= 32) {
                throw new \Exception('VarInt too big');
            }
        }

        return $value;
    }

    private function readPacket($socket): ?string
    {
        $length = $this->readVarInt($socket);
        if ($length <= 0) {
            return null;
        }

        $packetId = $this->readVarInt($socket);
        $jsonLength = $this->readVarInt($socket);

        if ($jsonLength <= 0) {
            return null;
        }

        $json = '';
        while (strlen($json) < $jsonLength) {
            $chunk = fread($socket, $jsonLength - strlen($json));
            if ($chunk === false) {
                break;
            }
            $json .= $chunk;
        }

        return $json;
    }

    private function cleanMotd($description): string
    {
        if (is_array($description)) {
            if (isset($description['text'])) {
                return $this->stripMinecraftColors($description['text']);
            }
            if (isset($description['extra'])) {
                $text = '';
                foreach ($description['extra'] as $part) {
                    $text .= is_string($part) ? $part : ($part['text'] ?? '');
                }
                return $this->stripMinecraftColors($text);
            }
            return '';
        }
        return $this->stripMinecraftColors((string) $description);
    }

    private function stripMinecraftColors(string $text): string
    {
        return preg_replace('/§[0-9a-fk-or]/i', '', $text);
    }

    public static function getSupportedTypes(): array
    {
        return ['minecraft'];
    }
}
