<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Security\AppKey;
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

// Fail fast if required secrets are missing or still set to a known-insecure
// default. Better to refuse to serve a single request than to silently encrypt
// stored credentials under a public constant.
AppKey::require('APP_KEY');
AppKey::require('APP_SECRET');

// Create and run application
$app = Application::create();
$app->run();
