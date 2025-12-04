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
use App\Modules\YouTubeDownloader\Controllers\YouTubeController;
use App\Modules\QuickNotes\Controllers\QuickNoteController;
use App\Modules\Notifications\Controllers\NotificationController;
use App\Modules\Dashboard\Controllers\WidgetController;
use App\Modules\Server\Controllers\ServerController;
use App\Modules\Dashboard\Controllers\AnalyticsController;
use App\Modules\Calendar\Controllers\CalendarController;
use App\Modules\Tools\Controllers\ToolsController;
use App\Modules\Docker\Controllers\DockerController;
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

            // Public YouTube file download (filename is unique/random ID)
            $group->get('/youtube/file/{filename}', [YouTubeController::class, 'serveFile']);

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

                // Uptime Monitor
                $protected->get('/uptime', [UptimeMonitorController::class, 'index']);
                $protected->post('/uptime', [UptimeMonitorController::class, 'create']);
                $protected->get('/uptime/types', [UptimeMonitorController::class, 'getTypes']);
                $protected->get('/uptime/stats', [UptimeMonitorController::class, 'getStats']);
                $protected->get('/uptime/folders', [UptimeMonitorController::class, 'getFolders']);
                $protected->post('/uptime/folders', [UptimeMonitorController::class, 'createFolder']);
                $protected->put('/uptime/folders/reorder', [UptimeMonitorController::class, 'reorderFolders']);
                $protected->put('/uptime/folders/{id}', [UptimeMonitorController::class, 'updateFolder']);
                $protected->delete('/uptime/folders/{id}', [UptimeMonitorController::class, 'deleteFolder']);
                $protected->post('/uptime/move-to-folder', [UptimeMonitorController::class, 'moveMonitorsToFolder']);
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

                // YouTube Downloader
                $protected->post('/youtube/info', [YouTubeController::class, 'getInfo']);
                $protected->post('/youtube/download', [YouTubeController::class, 'download']);
                $protected->post('/youtube/cleanup', [YouTubeController::class, 'cleanup']);

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

                // Tools (Network utilities)
                $protected->get('/tools/whois', [ToolsController::class, 'whois']);
                $protected->get('/tools/ssl-check', [ToolsController::class, 'sslCheck']);
                $protected->get('/tools/dns', [ToolsController::class, 'dnsLookup']);
                $protected->get('/tools/ping', [ToolsController::class, 'ping']);
                $protected->get('/tools/port-check', [ToolsController::class, 'portCheck']);
                $protected->get('/tools/http-headers', [ToolsController::class, 'httpHeaders']);
                $protected->get('/tools/ip-lookup', [ToolsController::class, 'ipLookup']);
                $protected->get('/tools/security-headers', [ToolsController::class, 'securityHeaders']);
                $protected->get('/tools/open-graph', [ToolsController::class, 'openGraph']);

                // Docker Host Management
                $protected->get('/docker/hosts', [DockerController::class, 'listHosts']);
                $protected->post('/docker/hosts', [DockerController::class, 'createHost']);
                $protected->get('/docker/hosts/{id}', [DockerController::class, 'getHost']);
                $protected->put('/docker/hosts/{id}', [DockerController::class, 'updateHost']);
                $protected->delete('/docker/hosts/{id}', [DockerController::class, 'deleteHost']);
                $protected->post('/docker/hosts/{id}/default', [DockerController::class, 'setDefaultHost']);
                $protected->post('/docker/hosts/{id}/test', [DockerController::class, 'testHostConnection']);

                // Docker Operations (with optional ?host_id= parameter)
                $protected->get('/docker/status', [DockerController::class, 'status']);
                $protected->get('/docker/containers', [DockerController::class, 'containers']);
                $protected->get('/docker/containers/{id}', [DockerController::class, 'containerDetails']);
                $protected->post('/docker/containers/{id}/start', [DockerController::class, 'startContainer']);
                $protected->post('/docker/containers/{id}/stop', [DockerController::class, 'stopContainer']);
                $protected->post('/docker/containers/{id}/restart', [DockerController::class, 'restartContainer']);
                $protected->get('/docker/containers/{id}/logs', [DockerController::class, 'containerLogs']);
                $protected->get('/docker/containers/{id}/stats', [DockerController::class, 'containerStats']);
                $protected->get('/docker/containers/{id}/env', [DockerController::class, 'getContainerEnv']);

                // Docker Stack/Compose Operations
                $protected->get('/docker/stacks/{name}/compose', [DockerController::class, 'getStackCompose']);
                $protected->put('/docker/stacks/{name}/compose', [DockerController::class, 'updateStackCompose']);
                $protected->post('/docker/stacks/{name}/up', [DockerController::class, 'stackUp']);
                $protected->post('/docker/stacks/{name}/down', [DockerController::class, 'stackDown']);
                $protected->post('/docker/stacks/{name}/restart', [DockerController::class, 'stackRestart']);
                $protected->post('/docker/stacks/{name}/backup', [DockerController::class, 'backupStack']);
                $protected->post('/docker/stacks/deploy', [DockerController::class, 'deployStack']);

                // Docker Deploy
                $protected->post('/docker/run', [DockerController::class, 'runContainer']);
                $protected->post('/docker/pull', [DockerController::class, 'pullImage']);
                $protected->delete('/docker/containers/{id}', [DockerController::class, 'removeContainer']);

                // Docker Backups
                $protected->get('/docker/backups', [DockerController::class, 'listBackups']);
                $protected->get('/docker/backups/{file}', [DockerController::class, 'getBackup']);
                $protected->post('/docker/backups/{file}/restore', [DockerController::class, 'restoreBackup']);
                $protected->delete('/docker/backups/{file}', [DockerController::class, 'deleteBackup']);

                $protected->get('/docker/images', [DockerController::class, 'images']);
                $protected->get('/docker/images/{id}', [DockerController::class, 'imageDetails']);
                $protected->get('/docker/networks', [DockerController::class, 'networks']);
                $protected->get('/docker/volumes', [DockerController::class, 'volumes']);
                $protected->get('/docker/system', [DockerController::class, 'systemInfo']);

                // Server Management
                $protected->get('/server/info', [ServerController::class, 'getSystemInfo']);
                $protected->get('/server/crontabs', [ServerController::class, 'listCrontabs']);
                $protected->post('/server/crontabs', [ServerController::class, 'addCrontab']);
                $protected->put('/server/crontabs', [ServerController::class, 'updateCrontab']);
                $protected->delete('/server/crontabs', [ServerController::class, 'deleteCrontab']);
                $protected->get('/server/processes', [ServerController::class, 'listProcesses']);
                $protected->post('/server/processes/kill', [ServerController::class, 'killProcess']);
                $protected->get('/server/services', [ServerController::class, 'listServices']);
                $protected->get('/server/services/status', [ServerController::class, 'getServiceStatus']);
                $protected->post('/server/services/control', [ServerController::class, 'controlService']);

            })->add(AuthMiddleware::class);
        });
    }
}
