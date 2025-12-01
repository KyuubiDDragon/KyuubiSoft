<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\PermissionMiddleware;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Users\Controllers\UserController;
use App\Modules\Lists\Controllers\ListController;
use App\Modules\Documents\Controllers\DocumentController;
use App\Modules\Settings\Controllers\SettingsController;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\System\Controllers\SystemController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

class Router
{
    public function __construct(
        private readonly App $app
    ) {}

    public function registerRoutes(): void
    {
        // Health check
        $this->app->get('/api/health', function ($request, $response) {
            $response->getBody()->write(json_encode([
                'status' => 'ok',
                'timestamp' => date('c'),
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // API v1 routes
        $this->app->group('/api/v1', function (RouteCollectorProxy $group) {
            // Public auth routes
            $group->group('/auth', function (RouteCollectorProxy $auth) {
                $auth->post('/register', [AuthController::class, 'register']);
                $auth->post('/login', [AuthController::class, 'login']);
                $auth->post('/refresh', [AuthController::class, 'refresh']);
                $auth->post('/forgot-password', [AuthController::class, 'forgotPassword']);
                $auth->post('/reset-password', [AuthController::class, 'resetPassword']);
            });

            // Protected routes
            $group->group('', function (RouteCollectorProxy $protected) {
                // Auth (protected)
                $protected->post('/auth/logout', [AuthController::class, 'logout']);
                $protected->get('/auth/me', [AuthController::class, 'me']);
                $protected->post('/auth/2fa/enable', [AuthController::class, 'enable2FA']);
                $protected->post('/auth/2fa/verify', [AuthController::class, 'verify2FA']);
                $protected->delete('/auth/2fa/disable', [AuthController::class, 'disable2FA']);

                // Dashboard
                $protected->get('/dashboard', [DashboardController::class, 'index']);
                $protected->get('/dashboard/stats', [DashboardController::class, 'stats']);

                // Users
                $protected->get('/users', [UserController::class, 'index'])
                    ->add(new PermissionMiddleware('users.read'));
                $protected->get('/users/{id}', [UserController::class, 'show']);
                $protected->put('/users/{id}', [UserController::class, 'update']);
                $protected->delete('/users/{id}', [UserController::class, 'delete'])
                    ->add(new PermissionMiddleware('users.delete'));
                $protected->get('/users/me/profile', [UserController::class, 'profile']);
                $protected->put('/users/me/profile', [UserController::class, 'updateProfile']);
                $protected->put('/users/me/password', [UserController::class, 'updatePassword']);

                // Lists
                $protected->get('/lists', [ListController::class, 'index']);
                $protected->post('/lists', [ListController::class, 'create']);
                $protected->get('/lists/{id}', [ListController::class, 'show']);
                $protected->put('/lists/{id}', [ListController::class, 'update']);
                $protected->delete('/lists/{id}', [ListController::class, 'delete']);
                $protected->post('/lists/{id}/items', [ListController::class, 'addItem']);
                $protected->put('/lists/{id}/items/{itemId}', [ListController::class, 'updateItem']);
                $protected->delete('/lists/{id}/items/{itemId}', [ListController::class, 'deleteItem']);
                $protected->put('/lists/{id}/items/reorder', [ListController::class, 'reorderItems']);

                // Documents
                $protected->get('/documents', [DocumentController::class, 'index']);
                $protected->post('/documents', [DocumentController::class, 'create']);
                $protected->get('/documents/{id}', [DocumentController::class, 'show']);
                $protected->put('/documents/{id}', [DocumentController::class, 'update']);
                $protected->delete('/documents/{id}', [DocumentController::class, 'delete']);
                $protected->get('/documents/{id}/versions', [DocumentController::class, 'versions']);

                // Settings
                $protected->get('/settings/user', [SettingsController::class, 'getUserSettings']);
                $protected->put('/settings/user', [SettingsController::class, 'updateUserSettings']);
                $protected->get('/settings/system', [SettingsController::class, 'getSystemSettings'])
                    ->add(new PermissionMiddleware('settings.system.read'));
                $protected->put('/settings/system', [SettingsController::class, 'updateSystemSettings'])
                    ->add(new PermissionMiddleware('settings.system.write'));

                // System (Owner only)
                $protected->get('/system/info', [SystemController::class, 'getInfo'])
                    ->add(new PermissionMiddleware('settings.system.read'));
                $protected->get('/system/metrics', [SystemController::class, 'getMetrics'])
                    ->add(new PermissionMiddleware('settings.system.read'));
                $protected->get('/system/audit-logs', [SystemController::class, 'getAuditLogs'])
                    ->add(new PermissionMiddleware('settings.system.read'));
                $protected->post('/system/clear-cache', [SystemController::class, 'clearCache'])
                    ->add(new PermissionMiddleware('settings.system.write'));
                $protected->post('/system/terminate-sessions', [SystemController::class, 'terminateSessions'])
                    ->add(new PermissionMiddleware('settings.system.write'));

            })->add(AuthMiddleware::class);
        });
    }
}
