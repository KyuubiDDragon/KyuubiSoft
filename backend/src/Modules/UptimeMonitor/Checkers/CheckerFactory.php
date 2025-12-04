<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Checkers;

class CheckerFactory
{
    private static array $checkers = [];

    /**
     * Get the appropriate checker for a monitor type
     */
    public static function getChecker(string $type): CheckerInterface
    {
        // Initialize checkers on first call
        if (empty(self::$checkers)) {
            self::registerCheckers();
        }

        if (!isset(self::$checkers[$type])) {
            throw new \InvalidArgumentException("Unknown monitor type: {$type}");
        }

        $checkerClass = self::$checkers[$type];
        return new $checkerClass();
    }

    /**
     * Get all supported monitor types with their metadata
     */
    public static function getSupportedTypes(): array
    {
        return [
            'http' => [
                'name' => 'HTTP',
                'description' => 'HTTP Website Check',
                'icon' => 'globe',
                'fields' => ['url', 'expected_status_code', 'expected_keyword'],
            ],
            'https' => [
                'name' => 'HTTPS',
                'description' => 'HTTPS Website Check (SSL)',
                'icon' => 'lock',
                'fields' => ['url', 'expected_status_code', 'expected_keyword'],
            ],
            'ping' => [
                'name' => 'Ping',
                'description' => 'ICMP Ping Check',
                'icon' => 'signal',
                'fields' => ['hostname'],
            ],
            'tcp' => [
                'name' => 'TCP Port',
                'description' => 'TCP Port Check',
                'icon' => 'server',
                'fields' => ['hostname', 'port'],
            ],
            'udp' => [
                'name' => 'UDP Port',
                'description' => 'UDP Port Check',
                'icon' => 'server',
                'fields' => ['hostname', 'port'],
            ],
            'minecraft' => [
                'name' => 'Minecraft',
                'description' => 'Minecraft Server (Java Edition)',
                'icon' => 'cube',
                'fields' => ['hostname', 'port'],
                'default_port' => 25565,
                'game_server' => true,
            ],
            'source' => [
                'name' => 'Source Engine',
                'description' => 'CS2, Garry\'s Mod, TF2, Rust, ARK, etc.',
                'icon' => 'play',
                'fields' => ['hostname', 'port'],
                'default_port' => 27015,
                'game_server' => true,
            ],
            'fivem' => [
                'name' => 'FiveM / RedM',
                'description' => 'FiveM / RedM (GTA V / RDR2) Server',
                'icon' => 'car',
                'fields' => ['hostname', 'port'],
                'default_port' => 30120,
                'game_server' => true,
            ],
            'teamspeak' => [
                'name' => 'TeamSpeak 3',
                'description' => 'TeamSpeak 3 Voice Server',
                'icon' => 'microphone',
                'fields' => ['hostname', 'port'],
                'default_port' => 10011,
                'game_server' => true,
            ],
            'dns' => [
                'name' => 'DNS',
                'description' => 'DNS Resolution Check',
                'icon' => 'globe-alt',
                'fields' => ['hostname', 'dns_record_type'],
            ],
            'ssl' => [
                'name' => 'SSL Certificate',
                'description' => 'SSL Certificate Expiry Check',
                'icon' => 'shield-check',
                'fields' => ['hostname', 'port', 'ssl_expiry_warn_days'],
                'default_port' => 443,
            ],
        ];
    }

    /**
     * Check if a type is a game server
     */
    public static function isGameServer(string $type): bool
    {
        $types = self::getSupportedTypes();
        return isset($types[$type]['game_server']) && $types[$type]['game_server'];
    }

    /**
     * Get default port for a type
     */
    public static function getDefaultPort(string $type): ?int
    {
        $types = self::getSupportedTypes();
        return $types[$type]['default_port'] ?? null;
    }

    /**
     * Register all available checkers
     */
    private static function registerCheckers(): void
    {
        $checkerClasses = [
            HttpChecker::class,
            TcpChecker::class,
            UdpChecker::class,
            PingChecker::class,
            MinecraftChecker::class,
            SourceChecker::class,
            FivemChecker::class,
            TeamspeakChecker::class,
            DnsChecker::class,
            SslChecker::class,
        ];

        foreach ($checkerClasses as $class) {
            foreach ($class::getSupportedTypes() as $type) {
                self::$checkers[$type] = $class;
            }
        }
    }
}
