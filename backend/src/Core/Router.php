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
use App\Modules\Webhooks\Controllers\WebhookController;
use App\Modules\Projects\Controllers\ProjectController;
use App\Modules\TimeTracking\Controllers\TimeTrackingController;
use App\Modules\Bookmarks\Controllers\BookmarkController;
use App\Modules\UptimeMonitor\Controllers\UptimeMonitorController;
use App\Modules\Invoices\Controllers\InvoiceController;
use App\Modules\ApiTester\Controllers\ApiTesterController;
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
                $protected->get('/kanban/boards/{id}/users', [KanbanController::class, 'getBoardUsers']);
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

                // Webhooks
                $protected->get('/webhooks', [WebhookController::class, 'index']);
                $protected->post('/webhooks', [WebhookController::class, 'create']);
                $protected->get('/webhooks/events', [WebhookController::class, 'getAvailableEvents']);
                $protected->get('/webhooks/{id}', [WebhookController::class, 'show']);
                $protected->put('/webhooks/{id}', [WebhookController::class, 'update']);
                $protected->delete('/webhooks/{id}', [WebhookController::class, 'delete']);
                $protected->post('/webhooks/{id}/test', [WebhookController::class, 'test']);

                // Projects
                $protected->get('/projects', [ProjectController::class, 'index']);
                $protected->post('/projects', [ProjectController::class, 'create']);
                $protected->get('/projects/{id}', [ProjectController::class, 'show']);
                $protected->put('/projects/{id}', [ProjectController::class, 'update']);
                $protected->delete('/projects/{id}', [ProjectController::class, 'delete']);
                $protected->post('/projects/{id}/links', [ProjectController::class, 'addLink']);
                $protected->delete('/projects/{id}/links/{linkId}', [ProjectController::class, 'removeLink']);
                $protected->get('/projects/{id}/linkable/{type}', [ProjectController::class, 'getLinkableItems']);
                $protected->get('/projects/{id}/shares', [ProjectController::class, 'getShares']);
                $protected->post('/projects/{id}/shares', [ProjectController::class, 'addShare']);
                $protected->delete('/projects/{id}/shares/{userId}', [ProjectController::class, 'removeShare']);

                // Time Tracking
                $protected->get('/time', [TimeTrackingController::class, 'index']);
                $protected->get('/time/running', [TimeTrackingController::class, 'getRunning']);
                $protected->post('/time/start', [TimeTrackingController::class, 'start']);
                $protected->post('/time/{id}/stop', [TimeTrackingController::class, 'stop']);
                $protected->post('/time', [TimeTrackingController::class, 'create']);
                $protected->put('/time/{id}', [TimeTrackingController::class, 'update']);
                $protected->delete('/time/{id}', [TimeTrackingController::class, 'delete']);
                $protected->get('/time/stats', [TimeTrackingController::class, 'getStats']);
                $protected->get('/time/projects', [TimeTrackingController::class, 'getProjects']);

                // Bookmarks
                $protected->get('/bookmarks', [BookmarkController::class, 'index']);
                $protected->post('/bookmarks', [BookmarkController::class, 'create']);
                $protected->get('/bookmarks/tags', [BookmarkController::class, 'getTags']);
                $protected->post('/bookmarks/tags', [BookmarkController::class, 'createTag']);
                $protected->delete('/bookmarks/tags/{tagId}', [BookmarkController::class, 'deleteTag']);
                $protected->get('/bookmarks/{id}', [BookmarkController::class, 'show']);
                $protected->put('/bookmarks/{id}', [BookmarkController::class, 'update']);
                $protected->delete('/bookmarks/{id}', [BookmarkController::class, 'delete']);
                $protected->post('/bookmarks/{id}/click', [BookmarkController::class, 'click']);

                // Uptime Monitor
                $protected->get('/uptime', [UptimeMonitorController::class, 'index']);
                $protected->post('/uptime', [UptimeMonitorController::class, 'create']);
                $protected->get('/uptime/stats', [UptimeMonitorController::class, 'getStats']);
                $protected->get('/uptime/{id}', [UptimeMonitorController::class, 'show']);
                $protected->put('/uptime/{id}', [UptimeMonitorController::class, 'update']);
                $protected->delete('/uptime/{id}', [UptimeMonitorController::class, 'delete']);
                $protected->post('/uptime/{id}/check', [UptimeMonitorController::class, 'check']);

                // Invoices - Clients
                $protected->get('/clients', [InvoiceController::class, 'getClients']);
                $protected->post('/clients', [InvoiceController::class, 'createClient']);
                $protected->put('/clients/{id}', [InvoiceController::class, 'updateClient']);
                $protected->delete('/clients/{id}', [InvoiceController::class, 'deleteClient']);

                // Invoices
                $protected->get('/invoices', [InvoiceController::class, 'index']);
                $protected->post('/invoices', [InvoiceController::class, 'create']);
                $protected->get('/invoices/stats', [InvoiceController::class, 'getStats']);
                $protected->post('/invoices/from-time', [InvoiceController::class, 'createFromTimeEntries']);
                $protected->get('/invoices/{id}', [InvoiceController::class, 'show']);
                $protected->put('/invoices/{id}', [InvoiceController::class, 'update']);
                $protected->delete('/invoices/{id}', [InvoiceController::class, 'delete']);
                $protected->post('/invoices/{id}/items', [InvoiceController::class, 'addItem']);
                $protected->put('/invoices/{id}/items/{itemId}', [InvoiceController::class, 'updateItem']);
                $protected->delete('/invoices/{id}/items/{itemId}', [InvoiceController::class, 'deleteItem']);

                // API Tester - Collections
                $protected->get('/api-tester/collections', [ApiTesterController::class, 'getCollections']);
                $protected->post('/api-tester/collections', [ApiTesterController::class, 'createCollection']);
                $protected->put('/api-tester/collections/{id}', [ApiTesterController::class, 'updateCollection']);
                $protected->delete('/api-tester/collections/{id}', [ApiTesterController::class, 'deleteCollection']);

                // API Tester - Environments
                $protected->get('/api-tester/environments', [ApiTesterController::class, 'getEnvironments']);
                $protected->post('/api-tester/environments', [ApiTesterController::class, 'createEnvironment']);
                $protected->put('/api-tester/environments/{id}', [ApiTesterController::class, 'updateEnvironment']);
                $protected->delete('/api-tester/environments/{id}', [ApiTesterController::class, 'deleteEnvironment']);

                // API Tester - Requests
                $protected->get('/api-tester/requests', [ApiTesterController::class, 'getRequests']);
                $protected->post('/api-tester/requests', [ApiTesterController::class, 'createRequest']);
                $protected->put('/api-tester/requests/{id}', [ApiTesterController::class, 'updateRequest']);
                $protected->delete('/api-tester/requests/{id}', [ApiTesterController::class, 'deleteRequest']);
                $protected->post('/api-tester/execute', [ApiTesterController::class, 'executeRequest']);

                // API Tester - History
                $protected->get('/api-tester/history', [ApiTesterController::class, 'getHistory']);
                $protected->get('/api-tester/history/{id}', [ApiTesterController::class, 'getHistoryItem']);
                $protected->delete('/api-tester/history', [ApiTesterController::class, 'clearHistory']);

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
