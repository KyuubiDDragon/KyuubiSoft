<?php

declare(strict_types=1);

namespace App\Core\Services;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class LoggerService
{
    public static function create(string $channel = 'daily', string $level = 'debug'): LoggerInterface
    {
        $logger = new Logger('kyuubisoft');
        $logLevel = self::parseLevel($level);
        $logPath = BASE_PATH . '/storage/logs';

        // Ensure log directory exists
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        switch ($channel) {
            case 'single':
                $logger->pushHandler(
                    new StreamHandler($logPath . '/app.log', $logLevel)
                );
                break;

            case 'daily':
            default:
                $logger->pushHandler(
                    new RotatingFileHandler(
                        $logPath . '/app.log',
                        14, // Keep 14 days of logs
                        $logLevel
                    )
                );
                break;
        }

        // Always surface errors on stderr so `docker logs <container>` shows
        // real application errors — the rotating file lives inside the container
        // and is otherwise invisible. In debug mode, stream everything.
        $stderrLevel = (($_ENV['APP_DEBUG'] ?? 'false') === 'true') ? $logLevel : Level::Error;
        $logger->pushHandler(
            new StreamHandler('php://stderr', $stderrLevel)
        );

        return $logger;
    }

    private static function parseLevel(string $level): Level
    {
        return match (strtolower($level)) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Debug,
        };
    }
}
