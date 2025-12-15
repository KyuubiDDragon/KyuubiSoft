#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * WebSocket Server for Real-time Collaboration
 *
 * Usage: php bin/websocket.php [port]
 * Default port: 8090
 */

require __DIR__ . '/../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\WebSocket\CollaborationServer;
use Predis\Client as RedisClient;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Get port from argument or environment
$port = (int) ($argv[1] ?? $_ENV['WEBSOCKET_PORT'] ?? 8090);

// Redis configuration
$redisConfig = [
    'scheme' => 'tcp',
    'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
    'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
];

if (!empty($_ENV['REDIS_PASSWORD'])) {
    $redisConfig['password'] = $_ENV['REDIS_PASSWORD'];
}

// JWT secret (same as main application)
$jwtSecret = $_ENV['JWT_SECRET'] ?? throw new RuntimeException('JWT_SECRET not configured');

try {
    echo "========================================\n";
    echo "  KyuubiSoft Collaboration Server\n";
    echo "========================================\n";
    echo "\n";

    // Initialize Redis
    echo "Connecting to Redis... ";
    $redis = new RedisClient($redisConfig);
    $redis->ping();
    echo "OK\n";

    // Create collaboration server
    echo "Initializing collaboration handler... ";
    $collaborationServer = new CollaborationServer($redis, $jwtSecret);
    echo "OK\n";

    // Create WebSocket server
    echo "Starting WebSocket server on port {$port}... ";
    $server = IoServer::factory(
        new HttpServer(
            new WsServer($collaborationServer)
        ),
        $port
    );
    echo "OK\n";

    echo "\n";
    echo "Server is running!\n";
    echo "WebSocket URL: ws://localhost:{$port}\n";
    echo "Press Ctrl+C to stop.\n";
    echo "\n";

    // Run the server
    $server->run();
} catch (Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
