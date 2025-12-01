<?php

declare(strict_types=1);

use App\Core\Application;
use Dotenv\Dotenv;

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Autoloader
require BASE_PATH . '/vendor/autoload.php';

// Load environment variables
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
}

// Create and run application
$app = Application::create();
$app->run();
