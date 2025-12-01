<?php

declare(strict_types=1);

namespace App\Core\Database;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\DriverManager;

class Connection
{
    public static function create(array $config): DBALConnection
    {
        $connectionParams = [
            'dbname' => $config['dbname'],
            'user' => $config['user'],
            'password' => $config['password'],
            'host' => $config['host'],
            'port' => $config['port'] ?? 3306,
            'driver' => 'pdo_mysql',
            'charset' => $config['charset'] ?? 'utf8mb4',
            'driverOptions' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'",
            ],
        ];

        $configuration = new Configuration();

        return DriverManager::getConnection($connectionParams, $configuration);
    }
}
