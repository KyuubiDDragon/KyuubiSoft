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
use App\Modules\Search\Controllers\SearchController;
use App\Modules\Connections\Controllers\ConnectionController;
use App\Modules\Snippets\Controllers\SnippetController;
use App\Modules\Kanban\Controllers\KanbanController;
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

                // Search
                $protected->get('/search', [SearchController::class, 'search']);

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
                $protected->get('/lists/{id}/shares', [ListController::class, 'getShares']);
                $protected->post('/lists/{id}/shares', [ListController::class, 'addShare']);
                $protected->delete('/lists/{id}/shares/{userId}', [ListController::class, 'removeShare']);

                // Documents
                $protected->get('/documents', [DocumentController::class, 'index']);
                $protected->post('/documents', [DocumentController::class, 'create']);
                $protected->get('/documents/{id}', [DocumentController::class, 'show']);
                $protected->put('/documents/{id}', [DocumentController::class, 'update']);
                $protected->delete('/documents/{id}', [DocumentController::class, 'delete']);
                $protected->get('/documents/{id}/versions', [DocumentController::class, 'versions']);
                $protected->get('/documents/{id}/shares', [DocumentController::class, 'getShares']);
                $protected->post('/documents/{id}/shares', [DocumentController::class, 'addShare']);
                $protected->delete('/documents/{id}/shares/{userId}', [DocumentController::class, 'removeShare']);

                // Connections
                $protected->get('/connections', [ConnectionController::class, 'index']);
                $protected->post('/connections', [ConnectionController::class, 'create']);
                $protected->get('/connections/tags', [ConnectionController::class, 'getTags']);
                $protected->post('/connections/tags', [ConnectionController::class, 'createTag']);
                $protected->delete('/connections/tags/{tagId}', [ConnectionController::class, 'deleteTag']);
                $protected->get('/connections/{id}', [ConnectionController::class, 'show']);
                $protected->put('/connections/{id}', [ConnectionController::class, 'update']);
                $protected->delete('/connections/{id}', [ConnectionController::class, 'delete']);
                $protected->get('/connections/{id}/credentials', [ConnectionController::class, 'getCredentials']);
                $protected->post('/connections/{id}/used', [ConnectionController::class, 'markUsed']);

                // Snippets
                $protected->get('/snippets', [SnippetController::class, 'index']);
                $protected->post('/snippets', [SnippetController::class, 'create']);
                $protected->get('/snippets/categories', [SnippetController::class, 'getCategories']);
                $protected->get('/snippets/languages', [SnippetController::class, 'getLanguages']);
                $protected->get('/snippets/{id}', [SnippetController::class, 'show']);
                $protected->put('/snippets/{id}', [SnippetController::class, 'update']);
                $protected->delete('/snippets/{id}', [SnippetController::class, 'delete']);
                $protected->post('/snippets/{id}/copy', [SnippetController::class, 'copy']);

                // Kanban Boards
                $protected->get('/kanban/boards', [KanbanController::class, 'index']);
                $protected->post('/kanban/boards', [KanbanController::class, 'create']);
                $protected->get('/kanban/boards/{id}', [KanbanController::class, 'show']);
                $protected->put('/kanban/boards/{id}', [KanbanController::class, 'update']);
                $protected->delete('/kanban/boards/{id}', [KanbanController::class, 'delete']);
                // Kanban Columns
                $protected->post('/kanban/boards/{id}/columns', [KanbanController::class, 'createColumn']);
                $protected->put('/kanban/boards/{id}/columns/{columnId}', [KanbanController::class, 'updateColumn']);
                $protected->delete('/kanban/boards/{id}/columns/{columnId}', [KanbanController::class, 'deleteColumn']);
                $protected->put('/kanban/boards/{id}/columns/reorder', [KanbanController::class, 'reorderColumns']);
                // Kanban Cards
                $protected->post('/kanban/boards/{id}/columns/{columnId}/cards', [KanbanController::class, 'createCard']);
                $protected->put('/kanban/boards/{id}/cards/{cardId}', [KanbanController::class, 'updateCard']);
                $protected->delete('/kanban/boards/{id}/cards/{cardId}', [KanbanController::class, 'deleteCard']);
                $protected->put('/kanban/boards/{id}/cards/{cardId}/move', [KanbanController::class, 'moveCard']);

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
