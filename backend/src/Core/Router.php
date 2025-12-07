<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\FeatureMiddleware;
use App\Core\Middleware\PermissionMiddleware;
use App\Modules\System\Controllers\FeaturesController;
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
use App\Modules\YouTubeDownloader\Controllers\YouTubeController;
use App\Modules\QuickNotes\Controllers\QuickNoteController;
use App\Modules\Notifications\Controllers\NotificationController;
use App\Modules\Dashboard\Controllers\WidgetController;
use App\Modules\Server\Controllers\ServerController;
use App\Modules\Dashboard\Controllers\AnalyticsController;
use App\Modules\Calendar\Controllers\CalendarController;
use App\Modules\Calendar\Controllers\ExternalCalendarController;
use App\Modules\Tools\Controllers\ToolsController;
use App\Modules\Docker\Controllers\DockerController;
use App\Modules\Tickets\Controllers\TicketController;
use App\Modules\Tickets\Controllers\TicketCategoryController;
use App\Modules\Setup\Controllers\SetupController;
use App\Modules\News\Controllers\NewsController;
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
            // Public features endpoint (for frontend to check available features)
            $group->get('/features', [FeaturesController::class, 'getFeatures']);

            // Public auth routes
            $group->group('/auth', function (RouteCollectorProxy $auth) {
                $auth->post('/register', [AuthController::class, 'register']);
                $auth->post('/login', [AuthController::class, 'login']);
                $auth->post('/refresh', [AuthController::class, 'refresh']);
                $auth->post('/forgot-password', [AuthController::class, 'forgotPassword']);
                $auth->post('/reset-password', [AuthController::class, 'resetPassword']);
            });

            // Public YouTube file download (filename is unique/random ID)
            $group->get('/youtube/file/{filename}', [YouTubeController::class, 'serveFile']);

            // Setup routes (public - needed before any user exists)
            $group->group('/setup', function (RouteCollectorProxy $setup) {
                $setup->get('/status', [SetupController::class, 'checkStatus']);
                $setup->post('/complete', [SetupController::class, 'complete']);
            });

            // Protected routes
            $group->group('', function (RouteCollectorProxy $protected) {
                // Auth (protected)
                $protected->post('/auth/logout', [AuthController::class, 'logout']);
                $protected->get('/auth/me', [AuthController::class, 'me']);
                $protected->post('/auth/2fa/enable', [AuthController::class, 'enable2FA']);
                $protected->post('/auth/2fa/verify', [AuthController::class, 'verify2FA']);
                $protected->delete('/auth/2fa/disable', [AuthController::class, 'disable2FA']);
                $protected->post('/auth/2fa/verify-sensitive', [AuthController::class, 'verifySensitiveOperation']);

                // Dashboard
                $protected->get('/dashboard', [DashboardController::class, 'index']);
                $protected->get('/dashboard/stats', [DashboardController::class, 'stats']);

                // Search
                $protected->get('/search', [SearchController::class, 'search']);

                // Users
                $protected->get('/users', [UserController::class, 'index'])
                    ->add(new PermissionMiddleware('users.read'));
                $protected->post('/users', [UserController::class, 'create'])
                    ->add(new PermissionMiddleware('users.write'));
                $protected->get('/users/{id}', [UserController::class, 'show']);
                $protected->put('/users/{id}', [UserController::class, 'update']);
                $protected->delete('/users/{id}', [UserController::class, 'delete'])
                    ->add(new PermissionMiddleware('users.delete'));
                $protected->get('/users/me/profile', [UserController::class, 'profile']);
                $protected->put('/users/me/profile', [UserController::class, 'updateProfile']);
                $protected->put('/users/me/password', [UserController::class, 'updatePassword']);

                // User Role Management
                $protected->get('/users/{id}/roles', [UserController::class, 'getUserRoles'])
                    ->add(new PermissionMiddleware('users.read'));
                $protected->post('/users/{id}/roles', [UserController::class, 'assignRole'])
                    ->add(new PermissionMiddleware('users.write'));
                $protected->delete('/users/{id}/roles/{role}', [UserController::class, 'removeRole'])
                    ->add(new PermissionMiddleware('users.write'));

                // Roles & Permissions (Admin)
                $protected->get('/admin/roles', [UserController::class, 'getRoles'])
                    ->add(new PermissionMiddleware('users.read'));
                $protected->get('/admin/permissions', [UserController::class, 'getPermissions'])
                    ->add(new PermissionMiddleware('users.read'));

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
                $protected->get('/documents/{id}/versions/{versionId}', [DocumentController::class, 'getVersion']);
                $protected->post('/documents/{id}/versions/{versionId}/restore', [DocumentController::class, 'restoreVersion']);
                $protected->get('/documents/{id}/shares', [DocumentController::class, 'getShares']);
                $protected->post('/documents/{id}/shares', [DocumentController::class, 'addShare']);
                $protected->delete('/documents/{id}/shares/{userId}', [DocumentController::class, 'removeShare']);
                // Public sharing management
                $protected->get('/documents/{id}/public', [DocumentController::class, 'getPublicShareInfo']);
                $protected->post('/documents/{id}/public', [DocumentController::class, 'enablePublicShare']);
                $protected->delete('/documents/{id}/public', [DocumentController::class, 'disablePublicShare']);

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
                // SSH Terminal
                $protected->post('/connections/{id}/execute', [ConnectionController::class, 'executeCommand']);
                $protected->get('/connections/{id}/history', [ConnectionController::class, 'getCommandHistory']);
                // Command Presets
                $protected->get('/connections/{id}/presets', [ConnectionController::class, 'getCommandPresets']);
                $protected->post('/connections/{id}/presets', [ConnectionController::class, 'createCommandPreset']);
                $protected->put('/connections/{id}/presets/{presetId}', [ConnectionController::class, 'updateCommandPreset']);
                $protected->delete('/connections/{id}/presets/{presetId}', [ConnectionController::class, 'deleteCommandPreset']);

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
                $protected->put('/kanban/boards/{id}/columns/reorder', [KanbanController::class, 'reorderColumns']);
                $protected->post('/kanban/boards/{id}/columns', [KanbanController::class, 'createColumn']);
                $protected->put('/kanban/boards/{id}/columns/{columnId}', [KanbanController::class, 'updateColumn']);
                $protected->delete('/kanban/boards/{id}/columns/{columnId}', [KanbanController::class, 'deleteColumn']);
                // Kanban Cards
                $protected->post('/kanban/boards/{id}/columns/{columnId}/cards', [KanbanController::class, 'createCard']);
                $protected->put('/kanban/boards/{id}/cards/{cardId}', [KanbanController::class, 'updateCard']);
                $protected->delete('/kanban/boards/{id}/cards/{cardId}', [KanbanController::class, 'deleteCard']);
                $protected->put('/kanban/boards/{id}/cards/{cardId}/move', [KanbanController::class, 'moveCard']);
                // Kanban Card Attachments
                $protected->post('/kanban/boards/{id}/cards/{cardId}/attachments', [KanbanController::class, 'uploadAttachment']);
                $protected->delete('/kanban/boards/{id}/cards/{cardId}/attachments/{attachmentId}', [KanbanController::class, 'deleteAttachment']);
                $protected->get('/kanban/attachments/{filename}', [KanbanController::class, 'serveAttachment']);
                // Kanban Tags
                $protected->get('/kanban/boards/{id}/tags', [KanbanController::class, 'getTags']);
                $protected->post('/kanban/boards/{id}/tags', [KanbanController::class, 'createTag']);
                $protected->put('/kanban/boards/{id}/tags/{tagId}', [KanbanController::class, 'updateTag']);
                $protected->delete('/kanban/boards/{id}/tags/{tagId}', [KanbanController::class, 'deleteTag']);
                $protected->post('/kanban/boards/{id}/cards/{cardId}/tags/{tagId}', [KanbanController::class, 'addCardTag']);
                $protected->delete('/kanban/boards/{id}/cards/{cardId}/tags/{tagId}', [KanbanController::class, 'removeCardTag']);
                // Kanban Card Links
                $protected->get('/kanban/boards/{id}/cards/{cardId}/links', [KanbanController::class, 'getCardLinks']);
                $protected->post('/kanban/boards/{id}/cards/{cardId}/links', [KanbanController::class, 'addCardLink']);
                $protected->delete('/kanban/boards/{id}/cards/{cardId}/links/{linkId}', [KanbanController::class, 'removeCardLink']);
                $protected->get('/kanban/boards/{id}/linkable/{type}', [KanbanController::class, 'getLinkableItems']);
                // Kanban Checklists
                $protected->get('/kanban/boards/{id}/cards/{cardId}/checklists', [KanbanController::class, 'getChecklists']);
                $protected->post('/kanban/boards/{id}/cards/{cardId}/checklists', [KanbanController::class, 'createChecklist']);
                $protected->put('/kanban/boards/{id}/checklists/{checklistId}', [KanbanController::class, 'updateChecklist']);
                $protected->delete('/kanban/boards/{id}/checklists/{checklistId}', [KanbanController::class, 'deleteChecklist']);
                $protected->post('/kanban/boards/{id}/checklists/{checklistId}/items', [KanbanController::class, 'addChecklistItem']);
                $protected->put('/kanban/boards/{id}/checklist-items/{itemId}', [KanbanController::class, 'updateChecklistItem']);
                $protected->post('/kanban/boards/{id}/checklist-items/{itemId}/toggle', [KanbanController::class, 'toggleChecklistItem']);
                $protected->delete('/kanban/boards/{id}/checklist-items/{itemId}', [KanbanController::class, 'deleteChecklistItem']);
                // Kanban Comments
                $protected->get('/kanban/boards/{id}/cards/{cardId}/comments', [KanbanController::class, 'getComments']);
                $protected->post('/kanban/boards/{id}/cards/{cardId}/comments', [KanbanController::class, 'addComment']);
                $protected->put('/kanban/boards/{id}/comments/{commentId}', [KanbanController::class, 'updateComment']);
                $protected->delete('/kanban/boards/{id}/comments/{commentId}', [KanbanController::class, 'deleteComment']);

                // Kanban Card Activities
                $protected->get('/kanban/boards/{id}/cards/{cardId}/activities', [KanbanController::class, 'getCardActivities']);

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
                $protected->get('/bookmarks/groups', [BookmarkController::class, 'getGroups']);
                $protected->post('/bookmarks/groups', [BookmarkController::class, 'createGroup']);
                $protected->put('/bookmarks/groups/reorder', [BookmarkController::class, 'reorderGroups']);
                $protected->put('/bookmarks/groups/{groupId}', [BookmarkController::class, 'updateGroup']);
                $protected->delete('/bookmarks/groups/{groupId}', [BookmarkController::class, 'deleteGroup']);
                $protected->get('/bookmarks/{id}', [BookmarkController::class, 'show']);
                $protected->put('/bookmarks/{id}', [BookmarkController::class, 'update']);
                $protected->delete('/bookmarks/{id}', [BookmarkController::class, 'delete']);
                $protected->post('/bookmarks/{id}/click', [BookmarkController::class, 'click']);
                $protected->put('/bookmarks/{id}/move', [BookmarkController::class, 'moveBookmarkToGroup']);

                // Uptime Monitor - protected by feature flags
                $protected->get('/uptime', [UptimeMonitorController::class, 'index'])
                    ->add(new FeatureMiddleware('uptime', null, 'view'));
                $protected->post('/uptime', [UptimeMonitorController::class, 'create'])
                    ->add(new FeatureMiddleware('uptime', null, 'manage'));
                $protected->get('/uptime/types', [UptimeMonitorController::class, 'getTypes'])
                    ->add(new FeatureMiddleware('uptime', null, 'view'));
                $protected->get('/uptime/stats', [UptimeMonitorController::class, 'getStats'])
                    ->add(new FeatureMiddleware('uptime', null, 'view'));
                $protected->get('/uptime/folders', [UptimeMonitorController::class, 'getFolders'])
                    ->add(new FeatureMiddleware('uptime', null, 'view'));
                $protected->post('/uptime/folders', [UptimeMonitorController::class, 'createFolder'])
                    ->add(new FeatureMiddleware('uptime', null, 'manage'));
                $protected->put('/uptime/folders/reorder', [UptimeMonitorController::class, 'reorderFolders'])
                    ->add(new FeatureMiddleware('uptime', null, 'manage'));
                $protected->put('/uptime/folders/{id}', [UptimeMonitorController::class, 'updateFolder'])
                    ->add(new FeatureMiddleware('uptime', null, 'manage'));
                $protected->delete('/uptime/folders/{id}', [UptimeMonitorController::class, 'deleteFolder'])
                    ->add(new FeatureMiddleware('uptime', null, 'manage'));
                $protected->post('/uptime/move-to-folder', [UptimeMonitorController::class, 'moveMonitorsToFolder'])
                    ->add(new FeatureMiddleware('uptime', null, 'manage'));
                $protected->get('/uptime/{id}', [UptimeMonitorController::class, 'show'])
                    ->add(new FeatureMiddleware('uptime', null, 'view'));
                $protected->put('/uptime/{id}', [UptimeMonitorController::class, 'update'])
                    ->add(new FeatureMiddleware('uptime', null, 'manage'));
                $protected->delete('/uptime/{id}', [UptimeMonitorController::class, 'delete'])
                    ->add(new FeatureMiddleware('uptime', null, 'manage'));
                $protected->post('/uptime/{id}/check', [UptimeMonitorController::class, 'check'])
                    ->add(new FeatureMiddleware('uptime', null, 'manage'));

                // Invoices - Clients - protected by feature flags
                $protected->get('/clients', [InvoiceController::class, 'getClients'])
                    ->add(new FeatureMiddleware('invoices', null, 'view'));
                $protected->post('/clients', [InvoiceController::class, 'createClient'])
                    ->add(new FeatureMiddleware('invoices', null, 'create'));
                $protected->put('/clients/{id}', [InvoiceController::class, 'updateClient'])
                    ->add(new FeatureMiddleware('invoices', null, 'edit'));
                $protected->delete('/clients/{id}', [InvoiceController::class, 'deleteClient'])
                    ->add(new FeatureMiddleware('invoices', null, 'delete'));

                // Invoices - protected by feature flags
                $protected->get('/invoices', [InvoiceController::class, 'index'])
                    ->add(new FeatureMiddleware('invoices', null, 'view'));
                $protected->post('/invoices', [InvoiceController::class, 'create'])
                    ->add(new FeatureMiddleware('invoices', null, 'create'));
                $protected->get('/invoices/stats', [InvoiceController::class, 'getStats'])
                    ->add(new FeatureMiddleware('invoices', null, 'view'));
                $protected->post('/invoices/from-time', [InvoiceController::class, 'createFromTimeEntries'])
                    ->add(new FeatureMiddleware('invoices', null, 'create'));
                $protected->get('/invoices/{id}', [InvoiceController::class, 'show'])
                    ->add(new FeatureMiddleware('invoices', null, 'view'));
                $protected->put('/invoices/{id}', [InvoiceController::class, 'update'])
                    ->add(new FeatureMiddleware('invoices', null, 'edit'));
                $protected->delete('/invoices/{id}', [InvoiceController::class, 'delete'])
                    ->add(new FeatureMiddleware('invoices', null, 'delete'));
                $protected->post('/invoices/{id}/items', [InvoiceController::class, 'addItem'])
                    ->add(new FeatureMiddleware('invoices', null, 'edit'));
                $protected->put('/invoices/{id}/items/{itemId}', [InvoiceController::class, 'updateItem'])
                    ->add(new FeatureMiddleware('invoices', null, 'edit'));
                $protected->delete('/invoices/{id}/items/{itemId}', [InvoiceController::class, 'deleteItem'])
                    ->add(new FeatureMiddleware('invoices', null, 'edit'));

                // API Tester - Collections - protected by feature flags
                $protected->get('/api-tester/collections', [ApiTesterController::class, 'getCollections'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));
                $protected->post('/api-tester/collections', [ApiTesterController::class, 'createCollection'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));
                $protected->put('/api-tester/collections/{id}', [ApiTesterController::class, 'updateCollection'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));
                $protected->delete('/api-tester/collections/{id}', [ApiTesterController::class, 'deleteCollection'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));

                // API Tester - Environments - protected by feature flags
                $protected->get('/api-tester/environments', [ApiTesterController::class, 'getEnvironments'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));
                $protected->post('/api-tester/environments', [ApiTesterController::class, 'createEnvironment'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));
                $protected->put('/api-tester/environments/{id}', [ApiTesterController::class, 'updateEnvironment'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));
                $protected->delete('/api-tester/environments/{id}', [ApiTesterController::class, 'deleteEnvironment'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));

                // API Tester - Requests - protected by feature flags
                $protected->get('/api-tester/requests', [ApiTesterController::class, 'getRequests'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));
                $protected->post('/api-tester/requests', [ApiTesterController::class, 'createRequest'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));
                $protected->put('/api-tester/requests/{id}', [ApiTesterController::class, 'updateRequest'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));
                $protected->delete('/api-tester/requests/{id}', [ApiTesterController::class, 'deleteRequest'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));
                $protected->post('/api-tester/execute', [ApiTesterController::class, 'executeRequest'])
                    ->add(new FeatureMiddleware('api_tester', null, 'execute'));

                // API Tester - History - protected by feature flags
                $protected->get('/api-tester/history', [ApiTesterController::class, 'getHistory'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));
                $protected->get('/api-tester/history/{id}', [ApiTesterController::class, 'getHistoryItem'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));
                $protected->delete('/api-tester/history', [ApiTesterController::class, 'clearHistory'])
                    ->add(new FeatureMiddleware('api_tester', null, 'view'));

                // YouTube Downloader - protected by feature flags
                $protected->post('/youtube/info', [YouTubeController::class, 'getInfo'])
                    ->add(new FeatureMiddleware('youtube', null, 'use'));
                $protected->post('/youtube/download', [YouTubeController::class, 'download'])
                    ->add(new FeatureMiddleware('youtube', null, 'use'));
                $protected->post('/youtube/cleanup', [YouTubeController::class, 'cleanup'])
                    ->add(new FeatureMiddleware('youtube', null, 'use'));

                // Quick Notes
                $protected->get('/quick-notes', [QuickNoteController::class, 'index']);
                $protected->post('/quick-notes', [QuickNoteController::class, 'create']);
                $protected->put('/quick-notes/reorder', [QuickNoteController::class, 'reorder']);
                $protected->put('/quick-notes/{id}', [QuickNoteController::class, 'update']);
                $protected->delete('/quick-notes/{id}', [QuickNoteController::class, 'delete']);

                // Notifications
                $protected->get('/notifications', [NotificationController::class, 'index']);
                $protected->get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
                $protected->post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
                $protected->delete('/notifications/clear', [NotificationController::class, 'clearAll']);
                $protected->post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
                $protected->delete('/notifications/{id}', [NotificationController::class, 'delete']);
                $protected->get('/notifications/preferences', [NotificationController::class, 'getPreferences']);
                $protected->put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);

                // Dashboard Widgets
                $protected->get('/dashboard/widgets', [WidgetController::class, 'getUserWidgets']);
                $protected->get('/dashboard/widgets/available', [WidgetController::class, 'getAvailableWidgets']);
                $protected->post('/dashboard/widgets/layout', [WidgetController::class, 'saveLayout']);
                $protected->post('/dashboard/widgets/reset', [WidgetController::class, 'resetToDefault']);
                $protected->put('/dashboard/widgets/{id}', [WidgetController::class, 'updateWidget']);
                $protected->delete('/dashboard/widgets/{id}', [WidgetController::class, 'deleteWidget']);

                // Analytics
                $protected->get('/analytics/productivity', [AnalyticsController::class, 'getProductivityStats']);
                $protected->get('/analytics/widget-data', [AnalyticsController::class, 'getWidgetData']);

                // Calendar
                $protected->get('/calendar/events', [CalendarController::class, 'getEvents']);
                $protected->get('/calendar/upcoming', [CalendarController::class, 'getUpcoming']);
                $protected->post('/calendar/events', [CalendarController::class, 'createEvent']);
                $protected->put('/calendar/events/{id}', [CalendarController::class, 'updateEvent']);
                $protected->delete('/calendar/events/{id}', [CalendarController::class, 'deleteEvent']);

                // External Calendars
                $protected->get('/calendar/external', [ExternalCalendarController::class, 'index']);
                $protected->post('/calendar/external', [ExternalCalendarController::class, 'create']);
                $protected->get('/calendar/external/events', [ExternalCalendarController::class, 'getEvents']);
                $protected->put('/calendar/external/{id}', [ExternalCalendarController::class, 'update']);
                $protected->delete('/calendar/external/{id}', [ExternalCalendarController::class, 'delete']);
                $protected->post('/calendar/external/{id}/sync', [ExternalCalendarController::class, 'sync']);

                // News/RSS Feeds
                $protected->get('/news/feeds', [NewsController::class, 'getFeeds']);
                $protected->get('/news/subscriptions', [NewsController::class, 'getSubscriptions']);
                $protected->post('/news/feeds/{feedId}/subscribe', [NewsController::class, 'subscribe']);
                $protected->delete('/news/feeds/{feedId}/subscribe', [NewsController::class, 'unsubscribe']);
                $protected->get('/news', [NewsController::class, 'getNews']);
                $protected->post('/news/items/{itemId}/read', [NewsController::class, 'markAsRead']);
                $protected->post('/news/items/{itemId}/save', [NewsController::class, 'toggleSaved']);
                $protected->post('/news/refresh', [NewsController::class, 'refreshFeeds']);
                $protected->post('/news/feeds', [NewsController::class, 'addFeed']);
                $protected->delete('/news/feeds/{feedId}', [NewsController::class, 'deleteFeed']);
                $protected->get('/news/unread-count', [NewsController::class, 'getUnreadCount']);

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

                // Tools (Network utilities) - protected by feature flags
                $protected->get('/tools/whois', [ToolsController::class, 'whois'])
                    ->add(new FeatureMiddleware('tools', null, 'whois'));
                $protected->get('/tools/ssl-check', [ToolsController::class, 'sslCheck'])
                    ->add(new FeatureMiddleware('tools'));
                $protected->get('/tools/dns', [ToolsController::class, 'dnsLookup'])
                    ->add(new FeatureMiddleware('tools', null, 'dns'));
                $protected->get('/tools/ping', [ToolsController::class, 'ping'])
                    ->add(new FeatureMiddleware('tools', null, 'ping'));
                $protected->get('/tools/port-check', [ToolsController::class, 'portCheck'])
                    ->add(new FeatureMiddleware('tools', null, 'port_check'));
                $protected->get('/tools/http-headers', [ToolsController::class, 'httpHeaders'])
                    ->add(new FeatureMiddleware('tools'));
                $protected->get('/tools/ip-lookup', [ToolsController::class, 'ipLookup'])
                    ->add(new FeatureMiddleware('tools'));
                $protected->get('/tools/security-headers', [ToolsController::class, 'securityHeaders'])
                    ->add(new FeatureMiddleware('tools'));
                $protected->get('/tools/open-graph', [ToolsController::class, 'openGraph'])
                    ->add(new FeatureMiddleware('tools'));

                // Docker Host Management - requires at least 'own' mode
                $protected->get('/docker/hosts', [DockerController::class, 'listHosts'])
                    ->add(new FeatureMiddleware('docker', null, 'view'));
                $protected->post('/docker/hosts', [DockerController::class, 'createHost'])
                    ->add(new FeatureMiddleware('docker', null, 'hosts_manage'));
                $protected->get('/docker/hosts/{id}', [DockerController::class, 'getHost'])
                    ->add(new FeatureMiddleware('docker', null, 'view'));
                $protected->put('/docker/hosts/{id}', [DockerController::class, 'updateHost'])
                    ->add(new FeatureMiddleware('docker', null, 'hosts_manage'));
                $protected->delete('/docker/hosts/{id}', [DockerController::class, 'deleteHost'])
                    ->add(new FeatureMiddleware('docker', null, 'hosts_manage'));
                $protected->post('/docker/hosts/{id}/default', [DockerController::class, 'setDefaultHost'])
                    ->add(new FeatureMiddleware('docker', null, 'view'));
                $protected->post('/docker/hosts/{id}/test', [DockerController::class, 'testHostConnection'])
                    ->add(new FeatureMiddleware('docker', null, 'view'));

                // Docker Operations (with optional ?host_id= parameter)
                $protected->get('/docker/status', [DockerController::class, 'status'])
                    ->add(new FeatureMiddleware('docker', null, 'containers'));
                $protected->get('/docker/containers', [DockerController::class, 'containers'])
                    ->add(new FeatureMiddleware('docker', null, 'containers'));
                $protected->get('/docker/containers/{id}', [DockerController::class, 'containerDetails'])
                    ->add(new FeatureMiddleware('docker', null, 'containers'));
                $protected->post('/docker/containers/{id}/start', [DockerController::class, 'startContainer'])
                    ->add(new FeatureMiddleware('docker', null, 'containers'));
                $protected->post('/docker/containers/{id}/stop', [DockerController::class, 'stopContainer'])
                    ->add(new FeatureMiddleware('docker', null, 'containers'));
                $protected->post('/docker/containers/{id}/restart', [DockerController::class, 'restartContainer'])
                    ->add(new FeatureMiddleware('docker', null, 'containers'));
                $protected->get('/docker/containers/{id}/logs', [DockerController::class, 'containerLogs'])
                    ->add(new FeatureMiddleware('docker', null, 'containers'));
                $protected->get('/docker/containers/{id}/stats', [DockerController::class, 'containerStats'])
                    ->add(new FeatureMiddleware('docker', null, 'containers'));
                $protected->get('/docker/containers/{id}/env', [DockerController::class, 'getContainerEnv'])
                    ->add(new FeatureMiddleware('docker', null, 'containers'));

                // Docker Stack/Compose Operations
                $protected->get('/docker/stacks/{name}/compose', [DockerController::class, 'getStackCompose'])
                    ->add(new FeatureMiddleware('docker'));
                $protected->put('/docker/stacks/{name}/compose', [DockerController::class, 'updateStackCompose'])
                    ->add(new FeatureMiddleware('docker'));
                $protected->post('/docker/stacks/{name}/up', [DockerController::class, 'stackUp'])
                    ->add(new FeatureMiddleware('docker'));
                $protected->post('/docker/stacks/{name}/down', [DockerController::class, 'stackDown'])
                    ->add(new FeatureMiddleware('docker'));
                $protected->post('/docker/stacks/{name}/restart', [DockerController::class, 'stackRestart'])
                    ->add(new FeatureMiddleware('docker'));
                $protected->post('/docker/stacks/{name}/backup', [DockerController::class, 'backupStack'])
                    ->add(new FeatureMiddleware('docker'));
                $protected->post('/docker/stacks/deploy', [DockerController::class, 'deployStack'])
                    ->add(new FeatureMiddleware('docker'));

                // Docker Deploy
                $protected->post('/docker/run', [DockerController::class, 'runContainer'])
                    ->add(new FeatureMiddleware('docker'));
                $protected->post('/docker/pull', [DockerController::class, 'pullImage'])
                    ->add(new FeatureMiddleware('docker'));
                $protected->delete('/docker/containers/{id}', [DockerController::class, 'removeContainer'])
                    ->add(new FeatureMiddleware('docker'));

                // Docker Backups
                $protected->get('/docker/backups', [DockerController::class, 'listBackups'])
                    ->add(new FeatureMiddleware('docker'));
                $protected->get('/docker/backups/{file}', [DockerController::class, 'getBackup'])
                    ->add(new FeatureMiddleware('docker'));
                $protected->post('/docker/backups/{file}/restore', [DockerController::class, 'restoreBackup'])
                    ->add(new FeatureMiddleware('docker'));
                $protected->delete('/docker/backups/{file}', [DockerController::class, 'deleteBackup'])
                    ->add(new FeatureMiddleware('docker'));

                $protected->get('/docker/images', [DockerController::class, 'images'])
                    ->add(new FeatureMiddleware('docker', null, 'images'));
                $protected->get('/docker/images/{id}', [DockerController::class, 'imageDetails'])
                    ->add(new FeatureMiddleware('docker', null, 'images'));
                $protected->get('/docker/networks', [DockerController::class, 'networks'])
                    ->add(new FeatureMiddleware('docker', null, 'networks'));
                $protected->get('/docker/volumes', [DockerController::class, 'volumes'])
                    ->add(new FeatureMiddleware('docker', null, 'volumes'));
                $protected->get('/docker/system', [DockerController::class, 'systemInfo'])
                    ->add(new FeatureMiddleware('docker', null, 'system_socket'));

                // Portainer Integration
                $protected->put('/docker/hosts/{id}/portainer', [DockerController::class, 'updatePortainerConfig'])
                    ->add(new FeatureMiddleware('docker', null, 'portainer'));
                $protected->get('/docker/portainer/stacks', [DockerController::class, 'listPortainerStacks'])
                    ->add(new FeatureMiddleware('docker', null, 'portainer'));
                $protected->get('/docker/portainer/stacks/{stackId}/file', [DockerController::class, 'getPortainerStackFile'])
                    ->add(new FeatureMiddleware('docker', null, 'portainer'));
                $protected->post('/docker/portainer/link', [DockerController::class, 'linkStackToPortainer'])
                    ->add(new FeatureMiddleware('docker', null, 'portainer'));

                // Server Management - protected by feature flags
                // Note: localhost sub-feature required for local server access
                $protected->get('/server/info', [ServerController::class, 'getSystemInfo'])
                    ->add(new FeatureMiddleware('server', null, 'view'));
                $protected->get('/server/crontabs', [ServerController::class, 'listCrontabs'])
                    ->add(new FeatureMiddleware('server', null, 'manage'));
                $protected->post('/server/crontabs', [ServerController::class, 'addCrontab'])
                    ->add(new FeatureMiddleware('server', null, 'manage'));
                $protected->put('/server/crontabs', [ServerController::class, 'updateCrontab'])
                    ->add(new FeatureMiddleware('server', null, 'manage'));
                $protected->delete('/server/crontabs', [ServerController::class, 'deleteCrontab'])
                    ->add(new FeatureMiddleware('server', null, 'manage'));
                $protected->get('/server/processes', [ServerController::class, 'listProcesses'])
                    ->add(new FeatureMiddleware('server', null, 'manage'));
                $protected->post('/server/processes/kill', [ServerController::class, 'killProcess'])
                    ->add(new FeatureMiddleware('server', null, 'manage'));
                $protected->get('/server/services', [ServerController::class, 'listServices'])
                    ->add(new FeatureMiddleware('server', null, 'manage'));
                $protected->get('/server/services/status', [ServerController::class, 'getServiceStatus'])
                    ->add(new FeatureMiddleware('server', null, 'manage'));
                $protected->post('/server/services/control', [ServerController::class, 'controlService'])
                    ->add(new FeatureMiddleware('server', null, 'manage'));
                $protected->get('/server/services/custom', [ServerController::class, 'getCustomServices'])
                    ->add(new FeatureMiddleware('server', null, 'manage'));
                $protected->post('/server/services/custom', [ServerController::class, 'addCustomService'])
                    ->add(new FeatureMiddleware('server', null, 'manage'));
                $protected->delete('/server/services/custom/{name}', [ServerController::class, 'removeCustomService'])
                    ->add(new FeatureMiddleware('server', null, 'manage'));

                // Tickets - protected by feature flags
                $protected->get('/tickets', [TicketController::class, 'index'])
                    ->add(new FeatureMiddleware('tickets', null, 'view'));
                $protected->get('/tickets/stats', [TicketController::class, 'stats'])
                    ->add(new FeatureMiddleware('tickets', null, 'view'));
                $protected->get('/tickets/categories', [TicketController::class, 'getCategories'])
                    ->add(new FeatureMiddleware('tickets', null, 'view'));
                $protected->post('/tickets', [TicketController::class, 'create'])
                    ->add(new FeatureMiddleware('tickets', null, 'create'));
                $protected->get('/tickets/{id}', [TicketController::class, 'show'])
                    ->add(new FeatureMiddleware('tickets', null, 'view'));
                $protected->put('/tickets/{id}', [TicketController::class, 'update'])
                    ->add(new FeatureMiddleware('tickets', null, 'manage'));
                $protected->delete('/tickets/{id}', [TicketController::class, 'delete'])
                    ->add(new FeatureMiddleware('tickets', null, 'manage'));
                $protected->put('/tickets/{id}/status', [TicketController::class, 'updateStatus'])
                    ->add(new FeatureMiddleware('tickets', null, 'manage'));
                $protected->put('/tickets/{id}/assign', [TicketController::class, 'assign'])
                    ->add(new FeatureMiddleware('tickets', null, 'manage'));
                $protected->post('/tickets/{id}/comments', [TicketController::class, 'addComment'])
                    ->add(new FeatureMiddleware('tickets', null, 'create'));

                // Ticket Categories (Admin) - protected by feature flags
                $protected->get('/admin/tickets/categories', [TicketCategoryController::class, 'index'])
                    ->add(new FeatureMiddleware('tickets', null, 'manage'));
                $protected->post('/admin/tickets/categories', [TicketCategoryController::class, 'create'])
                    ->add(new FeatureMiddleware('tickets', null, 'manage'));
                $protected->get('/admin/tickets/categories/{id}', [TicketCategoryController::class, 'show'])
                    ->add(new FeatureMiddleware('tickets', null, 'manage'));
                $protected->put('/admin/tickets/categories/{id}', [TicketCategoryController::class, 'update'])
                    ->add(new FeatureMiddleware('tickets', null, 'manage'));
                $protected->delete('/admin/tickets/categories/{id}', [TicketCategoryController::class, 'delete'])
                    ->add(new FeatureMiddleware('tickets', null, 'manage'));
                $protected->post('/admin/tickets/categories/reorder', [TicketCategoryController::class, 'reorder'])
                    ->add(new FeatureMiddleware('tickets', null, 'manage'));

            })->add(AuthMiddleware::class);

            // Public Ticket Routes (no auth required)
            $group->get('/public/ticket-categories', [TicketController::class, 'getCategories']);
            $group->post('/tickets/public', [TicketController::class, 'createPublic']);
            $group->get('/tickets/public/{code}', [TicketController::class, 'showPublic']);
            $group->post('/tickets/public/{code}/comments', [TicketController::class, 'addPublicComment']);

            // Public Document View (no auth required)
            $group->get('/documents/public/{token}', [DocumentController::class, 'showPublic']);
            $group->post('/documents/public/{token}', [DocumentController::class, 'showPublic']);

            // Collaborative Editing (no auth required)
            $group->post('/documents/public/{token}/join', [DocumentController::class, 'joinEditSession']);
            $group->post('/documents/public/{token}/update', [DocumentController::class, 'updatePublicContent']);
            $group->get('/documents/public/{token}/poll', [DocumentController::class, 'pollChanges']);
            $group->post('/documents/public/{token}/leave', [DocumentController::class, 'leaveEditSession']);

            // Internal sync from collaboration server
            $group->post('/documents/public/{token}/sync', [DocumentController::class, 'syncFromCollaboration']);
        });
    }
}
