<?php

declare(strict_types=1);

use App\Core\Database\Connection;
use App\Core\Security\JwtManager;
use App\Core\Security\PasswordHasher;
use App\Core\Security\RbacManager;
use App\Core\Services\AuditLogger;
use App\Core\Services\CacheService;
use App\Core\Services\FeatureService;
use App\Core\Services\ICalService;
use App\Core\Services\LoggerService;
use App\Core\Services\ProjectAccessService;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Setup\Controllers\SetupController;
use App\Modules\ApiKeys\Services\ApiKeyService;
use App\Modules\ApiKeys\Controllers\ApiKeyController;
use App\Modules\Favorites\Controllers\FavoriteController;
use App\Modules\Passwords\Services\PasswordService;
use App\Modules\Passwords\Controllers\PasswordController;
use App\Modules\Export\Services\ExportService;
use App\Modules\Export\Controllers\ExportController;
use App\Modules\Tags\Services\TagService;
use App\Modules\Tags\Controllers\TagController;
use App\Modules\Templates\Services\TemplateService;
use App\Modules\Templates\Controllers\TemplateController;
use App\Modules\RecurringTasks\Services\RecurringTaskService;
use App\Modules\RecurringTasks\Controllers\RecurringTaskController;
use App\Modules\Inbox\Services\InboxService;
use App\Modules\Inbox\Controllers\InboxController;
use App\Modules\AI\Services\AIService;
use App\Modules\AI\Controllers\AIController;
use App\Modules\Chat\Services\ChatService;
use App\Modules\Chat\Controllers\ChatController;
use App\Modules\Wiki\Services\WikiService;
use App\Modules\Wiki\Controllers\WikiController;
use App\Modules\QuickAccess\Controllers\QuickAccessController;
use App\Core\Middleware\ApiKeyMiddleware;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Auth\Repositories\RefreshTokenRepository;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Calendar\Controllers\ExternalCalendarController;
use App\Modules\System\Controllers\FeaturesController;
use App\Modules\System\Controllers\SystemController;
use Doctrine\DBAL\Connection as DBALConnection;
use Monolog\Logger;
use Predis\Client as RedisClient;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
    // Database Connection
    DBALConnection::class => function (ContainerInterface $c): DBALConnection {
        return Connection::create([
            'host' => $_ENV['DB_HOST'] ?? 'mysql',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'dbname' => $_ENV['DB_DATABASE'] ?? 'kyuubisoft',
            'user' => $_ENV['DB_USERNAME'] ?? 'kyuubisoft',
            'password' => $_ENV['DB_PASSWORD'] ?? 'secret',
            'charset' => 'utf8mb4',
        ]);
    },

    // Redis Client
    RedisClient::class => function (ContainerInterface $c): RedisClient {
        return new RedisClient([
            'scheme' => 'tcp',
            'host' => $_ENV['REDIS_HOST'] ?? 'redis',
            'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
        ]);
    },

    // Logger
    LoggerInterface::class => function (ContainerInterface $c): LoggerInterface {
        return LoggerService::create(
            $_ENV['LOG_CHANNEL'] ?? 'daily',
            $_ENV['LOG_LEVEL'] ?? 'debug'
        );
    },

    // Cache Service
    CacheService::class => function (ContainerInterface $c): CacheService {
        return new CacheService(
            $c->get(RedisClient::class),
            $_ENV['REDIS_PREFIX'] ?? 'kyuubisoft:'
        );
    },

    // JWT Manager
    JwtManager::class => function (ContainerInterface $c): JwtManager {
        return new JwtManager(
            $_ENV['JWT_SECRET'] ?? throw new RuntimeException('JWT_SECRET not set'),
            (int) ($_ENV['JWT_ACCESS_TTL'] ?? 900),
            (int) ($_ENV['JWT_REFRESH_TTL'] ?? 604800),
            $_ENV['JWT_ISSUER'] ?? 'kyuubisoft',
            $_ENV['JWT_ALGORITHM'] ?? 'HS256'
        );
    },

    // Password Hasher
    PasswordHasher::class => function (ContainerInterface $c): PasswordHasher {
        return new PasswordHasher();
    },

    // RBAC Manager
    RbacManager::class => function (ContainerInterface $c): RbacManager {
        return new RbacManager(
            $c->get(DBALConnection::class),
            $c->get(CacheService::class)
        );
    },

    // Repositories
    UserRepository::class => function (ContainerInterface $c): UserRepository {
        return new UserRepository($c->get(DBALConnection::class));
    },

    RefreshTokenRepository::class => function (ContainerInterface $c): RefreshTokenRepository {
        return new RefreshTokenRepository($c->get(DBALConnection::class));
    },

    // Services
    AuthService::class => function (ContainerInterface $c): AuthService {
        return new AuthService(
            $c->get(UserRepository::class),
            $c->get(RefreshTokenRepository::class),
            $c->get(JwtManager::class),
            $c->get(PasswordHasher::class),
            $c->get(RbacManager::class)
        );
    },

    // Project Access Service
    ProjectAccessService::class => function (ContainerInterface $c): ProjectAccessService {
        return new ProjectAccessService($c->get(DBALConnection::class));
    },

    // Audit Logger
    AuditLogger::class => function (ContainerInterface $c): AuditLogger {
        return new AuditLogger($c->get(DBALConnection::class));
    },

    // Auth Controller
    AuthController::class => function (ContainerInterface $c): AuthController {
        return new AuthController(
            $c->get(AuthService::class),
            $c->get(AuditLogger::class)
        );
    },

    // System Controller
    SystemController::class => function (ContainerInterface $c): SystemController {
        // Try to get Redis, but allow failure (optional dependency)
        $redis = null;
        try {
            $redis = $c->get(RedisClient::class);
            // Test connection
            $redis->ping();
        } catch (\Exception $e) {
            $redis = null;
        }

        return new SystemController(
            $c->get(DBALConnection::class),
            $c->get(RefreshTokenRepository::class),
            $redis
        );
    },

    // iCal Service
    ICalService::class => function (ContainerInterface $c): ICalService {
        return new ICalService();
    },

    // External Calendar Controller
    ExternalCalendarController::class => function (ContainerInterface $c): ExternalCalendarController {
        return new ExternalCalendarController(
            $c->get(DBALConnection::class),
            $c->get(ICalService::class)
        );
    },

    // Feature Service
    FeatureService::class => function (ContainerInterface $c): FeatureService {
        return new FeatureService();
    },

    // Features Controller
    FeaturesController::class => function (ContainerInterface $c): FeaturesController {
        return new FeaturesController(
            $c->get(FeatureService::class)
        );
    },

    // Setup Controller
    SetupController::class => function (ContainerInterface $c): SetupController {
        return new SetupController(
            $c->get(DBALConnection::class),
            $c->get(UserRepository::class),
            $c->get(PasswordHasher::class),
            $c->get(RbacManager::class)
        );
    },

    // API Key Service
    ApiKeyService::class => function (ContainerInterface $c): ApiKeyService {
        return new ApiKeyService($c->get(DBALConnection::class));
    },

    // API Key Controller
    ApiKeyController::class => function (ContainerInterface $c): ApiKeyController {
        return new ApiKeyController($c->get(ApiKeyService::class));
    },

    // API Key Middleware
    ApiKeyMiddleware::class => function (ContainerInterface $c): ApiKeyMiddleware {
        return new ApiKeyMiddleware($c->get(ApiKeyService::class));
    },

    // Favorite Controller
    FavoriteController::class => function (ContainerInterface $c): FavoriteController {
        return new FavoriteController($c->get(DBALConnection::class));
    },

    // Password Service
    PasswordService::class => function (ContainerInterface $c): PasswordService {
        return new PasswordService($c->get(DBALConnection::class));
    },

    // Password Controller
    PasswordController::class => function (ContainerInterface $c): PasswordController {
        return new PasswordController($c->get(PasswordService::class));
    },

    // Export Service
    ExportService::class => function (ContainerInterface $c): ExportService {
        return new ExportService(
            $c->get(DBALConnection::class),
            $c->get(LoggerInterface::class)
        );
    },

    // Export Controller
    ExportController::class => function (ContainerInterface $c): ExportController {
        return new ExportController($c->get(ExportService::class));
    },

    // Tag Service
    TagService::class => function (ContainerInterface $c): TagService {
        return new TagService($c->get(DBALConnection::class));
    },

    // Tag Controller
    TagController::class => function (ContainerInterface $c): TagController {
        return new TagController($c->get(TagService::class));
    },

    // Template Service
    TemplateService::class => function (ContainerInterface $c): TemplateService {
        return new TemplateService($c->get(DBALConnection::class));
    },

    // Template Controller
    TemplateController::class => function (ContainerInterface $c): TemplateController {
        return new TemplateController($c->get(TemplateService::class));
    },

    // Recurring Task Service
    RecurringTaskService::class => function (ContainerInterface $c): RecurringTaskService {
        return new RecurringTaskService($c->get(DBALConnection::class));
    },

    // Recurring Task Controller
    RecurringTaskController::class => function (ContainerInterface $c): RecurringTaskController {
        return new RecurringTaskController($c->get(RecurringTaskService::class));
    },

    // Inbox Service
    InboxService::class => function (ContainerInterface $c): InboxService {
        return new InboxService($c->get(DBALConnection::class));
    },

    // Inbox Controller
    InboxController::class => function (ContainerInterface $c): InboxController {
        return new InboxController($c->get(InboxService::class));
    },

    // AI Service
    AIService::class => function (ContainerInterface $c): AIService {
        return new AIService($c->get(DBALConnection::class));
    },

    // AI Controller
    AIController::class => function (ContainerInterface $c): AIController {
        return new AIController($c->get(AIService::class));
    },

    // Chat Service
    ChatService::class => function (ContainerInterface $c): ChatService {
        return new ChatService($c->get(DBALConnection::class));
    },

    // Chat Controller
    ChatController::class => function (ContainerInterface $c): ChatController {
        return new ChatController($c->get(ChatService::class));
    },

    // Wiki Service
    WikiService::class => function (ContainerInterface $c): WikiService {
        return new WikiService($c->get(DBALConnection::class));
    },

    // Wiki Controller
    WikiController::class => function (ContainerInterface $c): WikiController {
        return new WikiController($c->get(WikiService::class));
    },

    // File Version Service
    \App\Modules\Storage\Services\FileVersionService::class => function (ContainerInterface $c): \App\Modules\Storage\Services\FileVersionService {
        return new \App\Modules\Storage\Services\FileVersionService($c->get(DBALConnection::class));
    },

    // File Version Controller
    \App\Modules\Storage\Controllers\FileVersionController::class => function (ContainerInterface $c): \App\Modules\Storage\Controllers\FileVersionController {
        return new \App\Modules\Storage\Controllers\FileVersionController(
            $c->get(\App\Modules\Storage\Services\FileVersionService::class)
        );
    },

    // Notification Service
    \App\Core\Services\NotificationService::class => function (ContainerInterface $c): \App\Core\Services\NotificationService {
        return new \App\Core\Services\NotificationService(
            $c->get(DBALConnection::class),
            $c->get(LoggerInterface::class)
        );
    },

    // Notification Controller
    \App\Modules\Notifications\Controllers\NotificationController::class => function (ContainerInterface $c): \App\Modules\Notifications\Controllers\NotificationController {
        return new \App\Modules\Notifications\Controllers\NotificationController(
            $c->get(DBALConnection::class)
        );
    },

    // Quick Access Controller
    QuickAccessController::class => function (ContainerInterface $c): QuickAccessController {
        return new QuickAccessController($c->get(DBALConnection::class));
    },

    // Backup Service
    \App\Modules\Backup\Services\BackupService::class => function (ContainerInterface $c): \App\Modules\Backup\Services\BackupService {
        return new \App\Modules\Backup\Services\BackupService($c->get(DBALConnection::class));
    },

    // Backup Controller
    \App\Modules\Backup\Controllers\BackupController::class => function (ContainerInterface $c): \App\Modules\Backup\Controllers\BackupController {
        return new \App\Modules\Backup\Controllers\BackupController(
            $c->get(\App\Modules\Backup\Services\BackupService::class)
        );
    },

    // Link Service
    \App\Modules\Links\Services\LinkService::class => function (ContainerInterface $c): \App\Modules\Links\Services\LinkService {
        return new \App\Modules\Links\Services\LinkService($c->get(DBALConnection::class));
    },

    // Link Controller
    \App\Modules\Links\Controllers\LinkController::class => function (ContainerInterface $c): \App\Modules\Links\Controllers\LinkController {
        return new \App\Modules\Links\Controllers\LinkController(
            $c->get(\App\Modules\Links\Services\LinkService::class)
        );
    },

    // Git Repository Service
    \App\Modules\GitRepository\Services\GitProviderService::class => function (ContainerInterface $c): \App\Modules\GitRepository\Services\GitProviderService {
        return new \App\Modules\GitRepository\Services\GitProviderService();
    },

    // Git Repository Controller
    \App\Modules\GitRepository\Controllers\GitRepositoryController::class => function (ContainerInterface $c): \App\Modules\GitRepository\Controllers\GitRepositoryController {
        return new \App\Modules\GitRepository\Controllers\GitRepositoryController(
            $c->get(DBALConnection::class),
            $c->get(\App\Modules\GitRepository\Services\GitProviderService::class)
        );
    },

    // SSL Certificate Service
    \App\Modules\SslCertificate\Services\SslCheckerService::class => function (ContainerInterface $c): \App\Modules\SslCertificate\Services\SslCheckerService {
        return new \App\Modules\SslCertificate\Services\SslCheckerService();
    },

    // SSL Certificate Controller
    \App\Modules\SslCertificate\Controllers\SslCertificateController::class => function (ContainerInterface $c): \App\Modules\SslCertificate\Controllers\SslCertificateController {
        return new \App\Modules\SslCertificate\Controllers\SslCertificateController(
            $c->get(DBALConnection::class),
            $c->get(\App\Modules\SslCertificate\Services\SslCheckerService::class)
        );
    },

    // Public Gallery Controller
    \App\Modules\PublicGallery\Controllers\PublicGalleryController::class => function (ContainerInterface $c): \App\Modules\PublicGallery\Controllers\PublicGalleryController {
        return new \App\Modules\PublicGallery\Controllers\PublicGalleryController(
            $c->get(DBALConnection::class),
            $c->get(PasswordHasher::class)
        );
    },
];
