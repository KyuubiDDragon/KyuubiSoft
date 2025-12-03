#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Uptime Monitor Scheduler
 * Run via cron: * * * * * php /var/www/html/bin/uptime-check.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Database connection
$db = DriverManager::getConnection([
    'dbname' => $_ENV['DB_DATABASE'] ?? 'kyuubisoft',
    'user' => $_ENV['DB_USERNAME'] ?? 'kyuubisoft',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'host' => $_ENV['DB_HOST'] ?? 'db',
    'port' => $_ENV['DB_PORT'] ?? 3306,
    'driver' => 'pdo_mysql',
    'charset' => 'utf8mb4',
]);

echo "[" . date('Y-m-d H:i:s') . "] Starting uptime checks...\n";

// Get all active monitors that need checking
$monitors = $db->fetchAllAssociative(
    'SELECT * FROM uptime_monitors
     WHERE is_active = 1 AND is_paused = 0
     AND (last_check_at IS NULL OR last_check_at <= DATE_SUB(NOW(), INTERVAL check_interval SECOND))'
);

echo "Found " . count($monitors) . " monitors to check.\n";

foreach ($monitors as $monitor) {
    echo "Checking: {$monitor['name']} ({$monitor['url']})...\n";

    $startTime = microtime(true);
    $status = 'down';
    $statusCode = null;
    $errorMessage = null;
    $responseTime = null;

    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $monitor['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) $monitor['timeout'],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_USERAGENT => 'KyuubiSoft Uptime Monitor/1.0',
        ]);

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseTime = (int) ((microtime(true) - $startTime) * 1000);

        if (curl_errno($ch)) {
            $errorMessage = curl_error($ch);
        } else {
            $expectedStatus = (int) $monitor['expected_status_code'];
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
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        $responseTime = (int) ((microtime(true) - $startTime) * 1000);
    }

    // Record check
    $checkId = \Ramsey\Uuid\Uuid::uuid4()->toString();
    $db->insert('uptime_checks', [
        'id' => $checkId,
        'monitor_id' => $monitor['id'],
        'status' => $status,
        'response_time' => $responseTime,
        'status_code' => $statusCode,
        'error_message' => $errorMessage,
    ]);

    // Update monitor status
    $previousStatus = $monitor['current_status'];
    $updates = [
        'current_status' => $status,
        'last_check_at' => date('Y-m-d H:i:s'),
    ];

    if ($status === 'up') {
        $updates['last_up_at'] = date('Y-m-d H:i:s');

        // Close incident if any
        if ($previousStatus === 'down') {
            $db->executeStatement(
                'UPDATE uptime_incidents SET ended_at = NOW(), is_resolved = 1,
                 duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
                 WHERE monitor_id = ? AND is_resolved = 0',
                [$monitor['id']]
            );
            echo "  -> Recovered! Incident closed.\n";
        }
    } else {
        $updates['last_down_at'] = date('Y-m-d H:i:s');

        // Create incident if new
        if ($previousStatus !== 'down') {
            $db->insert('uptime_incidents', [
                'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
                'monitor_id' => $monitor['id'],
                'started_at' => date('Y-m-d H:i:s'),
                'cause' => $errorMessage,
            ]);
            echo "  -> DOWN! New incident created.\n";
        }
    }

    // Calculate uptime percentage (30 days)
    $since = date('Y-m-d H:i:s', strtotime('-30 days'));
    $stats = $db->fetchAssociative(
        'SELECT COUNT(*) as total, SUM(CASE WHEN status = "up" THEN 1 ELSE 0 END) as up_count
         FROM uptime_checks WHERE monitor_id = ? AND checked_at >= ?',
        [$monitor['id'], $since]
    );

    $uptimePercentage = $stats['total'] > 0
        ? round(($stats['up_count'] / $stats['total']) * 100, 2)
        : 100;
    $updates['uptime_percentage'] = $uptimePercentage;

    $db->update('uptime_monitors', $updates, ['id' => $monitor['id']]);

    echo "  -> Status: {$status}, Response: {$responseTime}ms, Uptime: {$uptimePercentage}%\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Done.\n";
