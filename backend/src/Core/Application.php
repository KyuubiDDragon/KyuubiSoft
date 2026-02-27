<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Middleware\CorsMiddleware;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\ErrorMiddleware;
use App\Core\Middleware\JsonBodyParserMiddleware;
use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Slim\App;

class Application
{
    private App $app;
    private ContainerInterface $container;

    private function __construct()
    {
        $this->container = $this->buildContainer();
        $this->app = Bridge::create($this->container);
        $this->registerMiddleware();
        $this->registerRoutes();
    }

    public static function create(): self
    {
        return new self();
    }

    public function run(): void
    {
        $this->app->run();
    }

    public function getApp(): App
    {
        return $this->app;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    private function buildContainer(): ContainerInterface
    {
        $containerBuilder = new ContainerBuilder();

        // Enable compilation in production
        if ($this->isProduction()) {
            $containerBuilder->enableCompilation(BASE_PATH . '/storage/cache/container');
        }

        // Add definitions
        $containerBuilder->addDefinitions(BASE_PATH . '/config/container.php');

        return $containerBuilder->build();
    }

    private function registerMiddleware(): void
    {
        // Error handling (must be last to be executed first)
        $this->app->add(ErrorMiddleware::class);

        // CORS
        $this->app->add(CorsMiddleware::class);

        // CSRF protection for cookie-based auth
        $this->app->add(CsrfMiddleware::class);

        // JSON Body Parser
        $this->app->add(JsonBodyParserMiddleware::class);

        // Routing middleware
        $this->app->addRoutingMiddleware();
    }

    private function registerRoutes(): void
    {
        $router = new Router($this->app);
        $router->registerRoutes();
    }

    private function isProduction(): bool
    {
        return ($_ENV['APP_ENV'] ?? 'production') === 'production';
    }
}
