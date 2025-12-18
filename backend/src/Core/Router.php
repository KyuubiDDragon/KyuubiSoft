<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\ApiKeyMiddleware;
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
use App\Modules\Notifications\Controllers\PushNotificationController;
use App\Modules\Dashboard\Controllers\WidgetController;
use App\Modules\Server\Controllers\ServerController;
use App\Modules\Dashboard\Controllers\AnalyticsController;
use App\Modules\Dashboard\Controllers\WeatherController;
use App\Modules\Automation\Controllers\WorkflowController;
use App\Modules\Calendar\Controllers\CalendarController;
use App\Modules\Calendar\Controllers\ExternalCalendarController;
use App\Modules\Tools\Controllers\ToolsController;
use App\Modules\Docker\Controllers\DockerController;
use App\Modules\Tickets\Controllers\TicketController;
use App\Modules\Tickets\Controllers\TicketCategoryController;
use App\Modules\Setup\Controllers\SetupController;
use App\Modules\News\Controllers\NewsController;
use App\Modules\Storage\Controllers\StorageController;
use App\Modules\Checklists\Controllers\SharedChecklistController;
use App\Modules\ApiKeys\Controllers\ApiKeyController;
use App\Modules\Favorites\Controllers\FavoriteController;
use App\Modules\Passwords\Controllers\PasswordController;
use App\Modules\Export\Controllers\ExportController;
use App\Modules\Tags\Controllers\TagController;
use App\Modules\Templates\Controllers\TemplateController;
use App\Modules\RecurringTasks\Controllers\RecurringTaskController;
use App\Modules\Inbox\Controllers\InboxController;
use App\Modules\AI\Controllers\AIController;
use App\Modules\Chat\Controllers\ChatController;
use App\Modules\Wiki\Controllers\WikiController;
use App\Modules\QuickAccess\Controllers\QuickAccessController;
use App\Modules\Backup\Controllers\BackupController;
use App\Modules\Links\Controllers\LinkController;
use App\Modules\GitRepository\Controllers\GitRepositoryController;
use App\Modules\SslCertificate\Controllers\SslCertificateController;
use App\Modules\PublicGallery\Controllers\PublicGalleryController;
use App\Modules\Notes\Controllers\NoteController;
use App\Modules\Notes\Controllers\DatabaseController;
use App\Modules\Notes\Controllers\CollaborationController;
use App\Modules\Notes\Controllers\PublicNoteController;
use App\Modules\Discord\Controllers\DiscordController;
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

        // Short Link Redirect - directly under /s/ (public, no auth)
        $this->app->get('/s/{code}', [LinkController::class, 'redirect']);
        $this->app->post('/s/{code}', [LinkController::class, 'redirect']);
        $this->app->get('/s/{code}/info', [LinkController::class, 'getLinkInfo']);

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

            // Public note access (no auth required)
            $group->get('/public/notes/{token}', [PublicNoteController::class, 'show']);

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

                // Notes - Hierarchical note-taking system
                $protected->get('/notes', [NoteController::class, 'index']);
                $protected->post('/notes', [NoteController::class, 'create']);
                $protected->get('/notes/tree', [NoteController::class, 'tree']);
                $protected->get('/notes/recent', [NoteController::class, 'recent']);
                $protected->get('/notes/favorites', [NoteController::class, 'favorites']);
                $protected->get('/notes/trash', [NoteController::class, 'trash']);
                $protected->delete('/notes/trash', [NoteController::class, 'emptyTrash']);
                $protected->get('/notes/templates', [NoteController::class, 'templates']);
                $protected->get('/notes/search', [NoteController::class, 'search']);
                $protected->get('/notes/search/suggestions', [NoteController::class, 'suggestions']);
                $protected->get('/notes/stats', [NoteController::class, 'stats']);
                $protected->get('/notes/by-slug/{slug}', [NoteController::class, 'bySlug']);
                $protected->post('/notes/from-template/{id}', [NoteController::class, 'fromTemplate']);
                $protected->put('/notes/reorder', [NoteController::class, 'reorder']);
                $protected->get('/notes/{id}', [NoteController::class, 'show']);
                $protected->put('/notes/{id}', [NoteController::class, 'update']);
                $protected->delete('/notes/{id}', [NoteController::class, 'delete']);
                $protected->get('/notes/{id}/children', [NoteController::class, 'children']);
                $protected->get('/notes/{id}/breadcrumb', [NoteController::class, 'breadcrumb']);
                $protected->get('/notes/{id}/backlinks', [NoteController::class, 'backlinks']);
                $protected->put('/notes/{id}/move', [NoteController::class, 'move']);
                $protected->post('/notes/{id}/favorite', [NoteController::class, 'favorite']);
                $protected->delete('/notes/{id}/favorite', [NoteController::class, 'unfavorite']);
                $protected->post('/notes/{id}/pin', [NoteController::class, 'pin']);
                $protected->delete('/notes/{id}/pin', [NoteController::class, 'unpin']);
                $protected->post('/notes/{id}/duplicate', [NoteController::class, 'duplicate']);
                $protected->post('/notes/{id}/restore', [NoteController::class, 'restore']);
                $protected->delete('/notes/{id}/permanent', [NoteController::class, 'permanentDelete']);
                $protected->get('/notes/{id}/versions', [NoteController::class, 'versions']);
                $protected->get('/notes/{id}/versions/{versionId}', [NoteController::class, 'version']);
                $protected->post('/notes/{id}/versions/{versionId}/restore', [NoteController::class, 'restoreVersion']);
                $protected->get('/notes/{id}/tags', [NoteController::class, 'getTags']);
                $protected->post('/notes/{id}/tags', [NoteController::class, 'addTags']);
                $protected->delete('/notes/{id}/tags/{tagId}', [NoteController::class, 'removeTag']);
                // Public sharing
                $protected->get('/notes/{id}/share', [PublicNoteController::class, 'status']);
                $protected->post('/notes/{id}/share', [PublicNoteController::class, 'share']);
                $protected->delete('/notes/{id}/share', [PublicNoteController::class, 'unshare']);
                $protected->post('/notes/{id}/share/regenerate', [PublicNoteController::class, 'regenerateToken']);

                // Note Databases - Inline databases for notes
                $protected->post('/databases', [DatabaseController::class, 'create']);
                $protected->get('/databases/{id}', [DatabaseController::class, 'show']);
                $protected->put('/databases/{id}', [DatabaseController::class, 'update']);
                $protected->delete('/databases/{id}', [DatabaseController::class, 'delete']);
                $protected->post('/databases/{id}/duplicate', [DatabaseController::class, 'duplicate']);
                // Properties
                $protected->post('/databases/{id}/properties', [DatabaseController::class, 'addProperty']);
                $protected->put('/databases/{id}/properties/{propertyId}', [DatabaseController::class, 'updateProperty']);
                $protected->delete('/databases/{id}/properties/{propertyId}', [DatabaseController::class, 'deleteProperty']);
                $protected->put('/databases/{id}/properties/reorder', [DatabaseController::class, 'reorderProperties']);
                // Rows
                $protected->post('/databases/{id}/rows', [DatabaseController::class, 'addRow']);
                $protected->put('/databases/{id}/rows/{rowId}', [DatabaseController::class, 'updateRow']);
                $protected->delete('/databases/{id}/rows/{rowId}', [DatabaseController::class, 'deleteRow']);
                $protected->put('/databases/{id}/rows/reorder', [DatabaseController::class, 'reorderRows']);
                // Views
                $protected->post('/databases/{id}/views', [DatabaseController::class, 'createView']);
                $protected->put('/databases/{id}/views/{viewId}', [DatabaseController::class, 'updateView']);
                $protected->delete('/databases/{id}/views/{viewId}', [DatabaseController::class, 'deleteView']);

                // Collaboration - Real-time editing
                $protected->get('/collaboration/status', [CollaborationController::class, 'status']);
                $protected->get('/collaboration/notes/{noteId}/collaborators', [CollaborationController::class, 'collaborators']);
                $protected->get('/collaboration/notes/{noteId}/history', [CollaborationController::class, 'history']);
                $protected->delete('/collaboration/notes/{noteId}', [CollaborationController::class, 'clear']);

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

                // Push Notifications
                $protected->get('/push/vapid-key', [PushNotificationController::class, 'getVapidKey']);
                $protected->post('/push/subscribe', [PushNotificationController::class, 'subscribe']);
                $protected->post('/push/unsubscribe', [PushNotificationController::class, 'unsubscribe']);
                $protected->get('/push/subscriptions', [PushNotificationController::class, 'getSubscriptions']);
                $protected->get('/push/preferences', [PushNotificationController::class, 'getPreferences']);
                $protected->put('/push/preferences', [PushNotificationController::class, 'updatePreferences']);
                $protected->get('/push/history', [PushNotificationController::class, 'getHistory']);
                $protected->post('/push/history/{id}/clicked', [PushNotificationController::class, 'markClicked']);
                $protected->post('/push/test', [PushNotificationController::class, 'sendTest']);

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

                // Weather Widget
                $protected->get('/weather', [WeatherController::class, 'getWeather']);
                $protected->get('/weather/search', [WeatherController::class, 'searchLocation']);

                // Automation / Workflows
                $protected->get('/workflows', [WorkflowController::class, 'index']);
                $protected->post('/workflows', [WorkflowController::class, 'create']);
                $protected->get('/workflows/options', [WorkflowController::class, 'options']);
                $protected->get('/workflows/templates', [WorkflowController::class, 'templates']);
                $protected->post('/workflows/templates/{template_id}', [WorkflowController::class, 'createFromTemplate']);
                $protected->get('/workflows/{id}', [WorkflowController::class, 'show']);
                $protected->put('/workflows/{id}', [WorkflowController::class, 'update']);
                $protected->delete('/workflows/{id}', [WorkflowController::class, 'delete']);
                $protected->post('/workflows/{id}/toggle', [WorkflowController::class, 'toggle']);
                $protected->post('/workflows/{id}/execute', [WorkflowController::class, 'execute']);
                $protected->get('/workflows/{id}/history', [WorkflowController::class, 'history']);
                $protected->get('/workflows/{id}/runs/{run_id}', [WorkflowController::class, 'runDetails']);

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
                $protected->get('/news/items/{itemId}/full-content', [NewsController::class, 'fetchFullContent']);

                // Cloud Storage
                $protected->get('/storage', [StorageController::class, 'index']);
                $protected->post('/storage/upload', [StorageController::class, 'upload']);
                $protected->get('/storage/stats', [StorageController::class, 'stats']);
                $protected->get('/storage/shares', [StorageController::class, 'listShares']);
                $protected->get('/storage/{id}', [StorageController::class, 'show']);
                $protected->put('/storage/{id}', [StorageController::class, 'update']);
                $protected->delete('/storage/{id}', [StorageController::class, 'delete']);
                $protected->get('/storage/{id}/download', [StorageController::class, 'download']);
                $protected->get('/storage/{id}/thumbnail', [StorageController::class, 'thumbnail']);
                $protected->get('/storage/{id}/share', [StorageController::class, 'getShare']);
                $protected->post('/storage/{id}/share', [StorageController::class, 'createShare']);
                $protected->put('/storage/{id}/share', [StorageController::class, 'updateShare']);
                $protected->delete('/storage/{id}/share', [StorageController::class, 'deleteShare']);

                // File Versioning
                $protected->get('/storage/versions/settings', [\App\Modules\Storage\Controllers\FileVersionController::class, 'getSettings']);
                $protected->put('/storage/versions/settings', [\App\Modules\Storage\Controllers\FileVersionController::class, 'updateSettings']);
                $protected->get('/storage/versions/stats', [\App\Modules\Storage\Controllers\FileVersionController::class, 'stats']);
                $protected->get('/storage/versions/compare', [\App\Modules\Storage\Controllers\FileVersionController::class, 'compare']);
                $protected->post('/storage/versions/cleanup', [\App\Modules\Storage\Controllers\FileVersionController::class, 'cleanup']);
                $protected->get('/storage/{fileId}/versions', [\App\Modules\Storage\Controllers\FileVersionController::class, 'index']);
                $protected->get('/storage/versions/{id}', [\App\Modules\Storage\Controllers\FileVersionController::class, 'show']);
                $protected->post('/storage/versions/{id}/restore', [\App\Modules\Storage\Controllers\FileVersionController::class, 'restore']);
                $protected->delete('/storage/versions/{id}', [\App\Modules\Storage\Controllers\FileVersionController::class, 'delete']);
                $protected->get('/storage/versions/{id}/download', [\App\Modules\Storage\Controllers\FileVersionController::class, 'download']);

                // Shared Checklists (Test Lists)
                $protected->get('/checklists', [SharedChecklistController::class, 'index']);
                $protected->post('/checklists', [SharedChecklistController::class, 'create']);
                $protected->get('/checklists/{id}', [SharedChecklistController::class, 'show']);
                $protected->put('/checklists/{id}', [SharedChecklistController::class, 'update']);
                $protected->delete('/checklists/{id}', [SharedChecklistController::class, 'delete']);
                // Checklist Categories
                $protected->post('/checklists/{id}/categories', [SharedChecklistController::class, 'addCategory']);
                $protected->put('/checklists/{id}/categories/{categoryId}', [SharedChecklistController::class, 'updateCategory']);
                $protected->delete('/checklists/{id}/categories/{categoryId}', [SharedChecklistController::class, 'deleteCategory']);
                // Checklist Items
                $protected->post('/checklists/{id}/items', [SharedChecklistController::class, 'addItem']);
                $protected->put('/checklists/{id}/items/{itemId}', [SharedChecklistController::class, 'updateItem']);
                $protected->delete('/checklists/{id}/items/{itemId}', [SharedChecklistController::class, 'deleteItem']);
                // Checklist Activity
                $protected->get('/checklists/{id}/activity', [SharedChecklistController::class, 'getActivity']);
                // Checklist Entries (Admin)
                $protected->delete('/checklists/{id}/entries/{entryId}', [SharedChecklistController::class, 'deleteEntryAdmin']);
                $protected->post('/checklists/{id}/entries/{entryId}/image', [SharedChecklistController::class, 'uploadEntryImageAdmin']);
                $protected->delete('/checklists/{id}/entries/{entryId}/image', [SharedChecklistController::class, 'deleteEntryImageAdmin']);
                // Duplicate & Reset
                $protected->post('/checklists/{id}/duplicate', [SharedChecklistController::class, 'duplicate']);
                $protected->post('/checklists/{id}/reset', [SharedChecklistController::class, 'resetEntries']);

                // Settings
                $protected->get('/settings/user', [SettingsController::class, 'getUserSettings']);
                $protected->put('/settings/user', [SettingsController::class, 'updateUserSettings']);
                $protected->get('/settings/system', [SettingsController::class, 'getSystemSettings'])
                    ->add(new PermissionMiddleware('settings.system.read'));
                $protected->put('/settings/system', [SettingsController::class, 'updateSystemSettings'])
                    ->add(new PermissionMiddleware('settings.system.write'));

                // API Keys
                $protected->get('/settings/api-keys', [ApiKeyController::class, 'index']);
                $protected->post('/settings/api-keys', [ApiKeyController::class, 'create']);
                $protected->put('/settings/api-keys/{id}', [ApiKeyController::class, 'update']);
                $protected->post('/settings/api-keys/{id}/revoke', [ApiKeyController::class, 'revoke']);
                $protected->delete('/settings/api-keys/{id}', [ApiKeyController::class, 'delete']);

                // Favorites / Quick Access
                $protected->get('/favorites', [FavoriteController::class, 'index']);
                $protected->post('/favorites', [FavoriteController::class, 'create']);
                $protected->post('/favorites/toggle', [FavoriteController::class, 'toggle']);
                $protected->put('/favorites/reorder', [FavoriteController::class, 'reorder']);
                $protected->get('/favorites/{type}/{id}', [FavoriteController::class, 'check']);
                $protected->delete('/favorites/{type}/{id}', [FavoriteController::class, 'delete']);

                // Quick Access Navigation
                $protected->get('/quick-access', [QuickAccessController::class, 'index']);
                $protected->post('/quick-access', [QuickAccessController::class, 'create']);
                $protected->post('/quick-access/toggle', [QuickAccessController::class, 'toggle']);
                $protected->put('/quick-access/reorder', [QuickAccessController::class, 'reorder']);
                $protected->get('/quick-access/settings', [QuickAccessController::class, 'getSettings']);
                $protected->put('/quick-access/settings', [QuickAccessController::class, 'updateSettings']);
                $protected->get('/quick-access/{navId}', [QuickAccessController::class, 'check']);
                $protected->delete('/quick-access/{navId}', [QuickAccessController::class, 'delete']);

                // Password Manager
                $protected->get('/passwords', [PasswordController::class, 'index']);
                $protected->post('/passwords', [PasswordController::class, 'create']);
                $protected->get('/passwords/search', [PasswordController::class, 'search']);
                $protected->get('/passwords/generate', [PasswordController::class, 'generatePassword']);
                $protected->get('/passwords/categories', [PasswordController::class, 'getCategories']);
                $protected->post('/passwords/categories', [PasswordController::class, 'createCategory']);
                $protected->put('/passwords/categories/{id}', [PasswordController::class, 'updateCategory']);
                $protected->delete('/passwords/categories/{id}', [PasswordController::class, 'deleteCategory']);
                $protected->get('/passwords/{id}', [PasswordController::class, 'show']);
                $protected->put('/passwords/{id}', [PasswordController::class, 'update']);
                $protected->delete('/passwords/{id}', [PasswordController::class, 'delete']);
                $protected->post('/passwords/{id}/favorite', [PasswordController::class, 'toggleFavorite']);
                $protected->get('/passwords/{id}/totp', [PasswordController::class, 'generateTOTP']);

                // Export/Import
                $protected->get('/export/stats', [ExportController::class, 'getStats']);
                $protected->post('/export', [ExportController::class, 'export']);
                $protected->post('/import/validate', [ExportController::class, 'validateImport']);
                $protected->post('/import', [ExportController::class, 'import']);

                // Global Tags
                $protected->get('/tags', [TagController::class, 'index']);
                $protected->post('/tags', [TagController::class, 'create']);
                $protected->get('/tags/types', [TagController::class, 'getTypes']);
                $protected->get('/tags/search', [TagController::class, 'searchByTags']);
                $protected->post('/tags/merge', [TagController::class, 'mergeTags']);
                $protected->get('/tags/{id}', [TagController::class, 'show']);
                $protected->put('/tags/{id}', [TagController::class, 'update']);
                $protected->delete('/tags/{id}', [TagController::class, 'delete']);
                $protected->post('/tags/{id}/tag', [TagController::class, 'tagItem']);
                $protected->delete('/tags/{id}/{type}/{itemId}', [TagController::class, 'untagItem']);
                // Item tags
                $protected->get('/taggable/{type}/{itemId}', [TagController::class, 'getItemTags']);
                $protected->put('/taggable/{type}/{itemId}', [TagController::class, 'setItemTags']);

                // Templates
                $protected->get('/templates', [TemplateController::class, 'index']);
                $protected->post('/templates', [TemplateController::class, 'create']);
                $protected->get('/templates/types', [TemplateController::class, 'getTypes']);
                $protected->post('/templates/from-item', [TemplateController::class, 'createFromItem']);
                $protected->get('/templates/categories', [TemplateController::class, 'getCategories']);
                $protected->post('/templates/categories', [TemplateController::class, 'createCategory']);
                $protected->put('/templates/categories/{id}', [TemplateController::class, 'updateCategory']);
                $protected->delete('/templates/categories/{id}', [TemplateController::class, 'deleteCategory']);
                $protected->get('/templates/{id}', [TemplateController::class, 'show']);
                $protected->put('/templates/{id}', [TemplateController::class, 'update']);
                $protected->delete('/templates/{id}', [TemplateController::class, 'delete']);
                $protected->post('/templates/{id}/use', [TemplateController::class, 'useTemplate']);

                // Recurring Tasks
                $protected->get('/recurring-tasks', [RecurringTaskController::class, 'index']);
                $protected->post('/recurring-tasks', [RecurringTaskController::class, 'create']);
                $protected->get('/recurring-tasks/upcoming', [RecurringTaskController::class, 'upcoming']);
                $protected->post('/recurring-tasks/process-due', [RecurringTaskController::class, 'processDue']);
                $protected->get('/recurring-tasks/{id}', [RecurringTaskController::class, 'show']);
                $protected->put('/recurring-tasks/{id}', [RecurringTaskController::class, 'update']);
                $protected->delete('/recurring-tasks/{id}', [RecurringTaskController::class, 'delete']);
                $protected->post('/recurring-tasks/{id}/toggle', [RecurringTaskController::class, 'toggleActive']);
                $protected->post('/recurring-tasks/{id}/skip', [RecurringTaskController::class, 'skipOccurrence']);
                $protected->post('/recurring-tasks/{id}/process', [RecurringTaskController::class, 'processTask']);
                $protected->get('/recurring-tasks/{id}/instances', [RecurringTaskController::class, 'getInstances']);

                // Inbox / Quick Capture
                $protected->get('/inbox', [InboxController::class, 'getItems']);
                $protected->post('/inbox', [InboxController::class, 'capture']);
                $protected->get('/inbox/stats', [InboxController::class, 'getStats']);
                $protected->post('/inbox/bulk', [InboxController::class, 'bulkAction']);
                $protected->get('/inbox/{id}', [InboxController::class, 'getItem']);
                $protected->put('/inbox/{id}', [InboxController::class, 'updateItem']);
                $protected->delete('/inbox/{id}', [InboxController::class, 'deleteItem']);
                $protected->post('/inbox/{id}/move', [InboxController::class, 'moveToModule']);

                // AI Assistant
                $protected->get('/ai/settings', [AIController::class, 'getSettings']);
                $protected->post('/ai/settings', [AIController::class, 'saveSettings']);
                $protected->delete('/ai/settings/api-key', [AIController::class, 'removeApiKey']);
                $protected->get('/ai/status', [AIController::class, 'checkStatus']);
                $protected->post('/ai/chat', [AIController::class, 'chat']);
                $protected->get('/ai/conversations', [AIController::class, 'getConversations']);
                $protected->get('/ai/conversations/{id}', [AIController::class, 'getConversation']);
                $protected->delete('/ai/conversations/{id}', [AIController::class, 'deleteConversation']);
                $protected->get('/ai/prompts', [AIController::class, 'getPrompts']);
                $protected->post('/ai/prompts', [AIController::class, 'savePrompt']);
                $protected->delete('/ai/prompts/{id}', [AIController::class, 'deletePrompt']);

                // Team Chat
                $protected->get('/chat/rooms', [ChatController::class, 'getRooms']);
                $protected->post('/chat/rooms', [ChatController::class, 'createRoom']);
                $protected->get('/chat/rooms/{id}', [ChatController::class, 'getRoom']);
                $protected->post('/chat/rooms/{id}/leave', [ChatController::class, 'leaveRoom']);
                $protected->post('/chat/rooms/{id}/participants', [ChatController::class, 'addParticipant']);
                $protected->get('/chat/rooms/{id}/messages', [ChatController::class, 'getMessages']);
                $protected->post('/chat/rooms/{id}/messages', [ChatController::class, 'sendMessage']);
                $protected->post('/chat/rooms/{id}/read', [ChatController::class, 'markAsRead']);
                $protected->post('/chat/rooms/{id}/typing', [ChatController::class, 'setTyping']);
                $protected->get('/chat/rooms/{id}/typing', [ChatController::class, 'getTyping']);
                $protected->put('/chat/messages/{messageId}', [ChatController::class, 'editMessage']);
                $protected->delete('/chat/messages/{messageId}', [ChatController::class, 'deleteMessage']);
                $protected->post('/chat/messages/{messageId}/reactions', [ChatController::class, 'addReaction']);
                $protected->delete('/chat/messages/{messageId}/reactions/{emoji}', [ChatController::class, 'removeReaction']);
                $protected->post('/chat/direct', [ChatController::class, 'startDirectMessage']);
                $protected->get('/chat/users', [ChatController::class, 'getAvailableUsers']);
                $protected->get('/chat/search', [ChatController::class, 'searchMessages']);

                // Wiki / Knowledge Base
                $protected->get('/wiki/pages', [WikiController::class, 'getPages']);
                $protected->post('/wiki/pages', [WikiController::class, 'createPage']);
                $protected->get('/wiki/pages/recent', [WikiController::class, 'getRecent']);
                $protected->get('/wiki/search', [WikiController::class, 'search']);
                $protected->get('/wiki/graph', [WikiController::class, 'getGraph']);
                $protected->get('/wiki/pages/{id}', [WikiController::class, 'getPage']);
                $protected->put('/wiki/pages/{id}', [WikiController::class, 'updatePage']);
                $protected->delete('/wiki/pages/{id}', [WikiController::class, 'deletePage']);
                $protected->get('/wiki/pages/{id}/history', [WikiController::class, 'getPageHistory']);
                $protected->post('/wiki/pages/{id}/restore/{historyId}', [WikiController::class, 'restoreFromHistory']);
                $protected->get('/wiki/categories', [WikiController::class, 'getCategories']);
                $protected->post('/wiki/categories', [WikiController::class, 'createCategory']);
                $protected->put('/wiki/categories/{id}', [WikiController::class, 'updateCategory']);
                $protected->delete('/wiki/categories/{id}', [WikiController::class, 'deleteCategory']);

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
                $protected->post('/docker/stacks/{name}/pull-redeploy', [DockerController::class, 'stackPullAndRedeploy'])
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

                // Backup & Recovery System
                // Storage Targets
                $protected->get('/backups/targets', [BackupController::class, 'listTargets'])
                    ->add(new PermissionMiddleware('backups.view'));
                $protected->post('/backups/targets', [BackupController::class, 'createTarget'])
                    ->add(new PermissionMiddleware('backups.manage_targets'));
                $protected->get('/backups/targets/{id}', [BackupController::class, 'getTarget'])
                    ->add(new PermissionMiddleware('backups.view'));
                $protected->put('/backups/targets/{id}', [BackupController::class, 'updateTarget'])
                    ->add(new PermissionMiddleware('backups.manage_targets'));
                $protected->delete('/backups/targets/{id}', [BackupController::class, 'deleteTarget'])
                    ->add(new PermissionMiddleware('backups.manage_targets'));
                $protected->post('/backups/targets/{id}/test', [BackupController::class, 'testTarget'])
                    ->add(new PermissionMiddleware('backups.view'));

                // Backup Schedules
                $protected->get('/backups/schedules', [BackupController::class, 'listSchedules'])
                    ->add(new PermissionMiddleware('backups.view'));
                $protected->post('/backups/schedules', [BackupController::class, 'createSchedule'])
                    ->add(new PermissionMiddleware('backups.manage_schedules'));
                $protected->get('/backups/schedules/{id}', [BackupController::class, 'getSchedule'])
                    ->add(new PermissionMiddleware('backups.view'));
                $protected->put('/backups/schedules/{id}', [BackupController::class, 'updateSchedule'])
                    ->add(new PermissionMiddleware('backups.manage_schedules'));
                $protected->delete('/backups/schedules/{id}', [BackupController::class, 'deleteSchedule'])
                    ->add(new PermissionMiddleware('backups.manage_schedules'));

                // Backups
                $protected->get('/backups', [BackupController::class, 'listBackups'])
                    ->add(new PermissionMiddleware('backups.view'));
                $protected->post('/backups', [BackupController::class, 'createBackup'])
                    ->add(new PermissionMiddleware('backups.create'));
                $protected->get('/backups/stats', [BackupController::class, 'getStats'])
                    ->add(new PermissionMiddleware('backups.view'));
                $protected->get('/backups/{id}', [BackupController::class, 'getBackup'])
                    ->add(new PermissionMiddleware('backups.view'));
                $protected->delete('/backups/{id}', [BackupController::class, 'deleteBackup'])
                    ->add(new PermissionMiddleware('backups.delete'));
                $protected->post('/backups/{id}/restore', [BackupController::class, 'restoreBackup'])
                    ->add(new PermissionMiddleware('backups.restore'));
                $protected->get('/backups/{id}/download', [BackupController::class, 'downloadBackup'])
                    ->add(new PermissionMiddleware('backups.view'));

                // Link Shortener
                $protected->get('/links', [LinkController::class, 'index'])
                    ->add(new PermissionMiddleware('links.view'));
                $protected->post('/links', [LinkController::class, 'create'])
                    ->add(new PermissionMiddleware('links.create'));
                $protected->get('/links/stats', [LinkController::class, 'userStats'])
                    ->add(new PermissionMiddleware('links.view'));
                $protected->get('/links/{id}', [LinkController::class, 'show'])
                    ->add(new PermissionMiddleware('links.view'));
                $protected->put('/links/{id}', [LinkController::class, 'update'])
                    ->add(new PermissionMiddleware('links.create'));
                $protected->delete('/links/{id}', [LinkController::class, 'delete'])
                    ->add(new PermissionMiddleware('links.delete'));
                $protected->get('/links/{id}/stats', [LinkController::class, 'stats'])
                    ->add(new PermissionMiddleware('links.view'));
                $protected->get('/links/{id}/qr', [LinkController::class, 'qrCode'])
                    ->add(new PermissionMiddleware('links.view'));

                // Git Repository Dashboard
                $protected->get('/git/repositories', [GitRepositoryController::class, 'index'])
                    ->add(new FeatureMiddleware('git', null, 'view'));
                $protected->post('/git/repositories', [GitRepositoryController::class, 'create'])
                    ->add(new FeatureMiddleware('git', null, 'manage'));
                $protected->get('/git/repositories/stats', [GitRepositoryController::class, 'stats'])
                    ->add(new FeatureMiddleware('git', null, 'view'));
                $protected->get('/git/repositories/{id}', [GitRepositoryController::class, 'show'])
                    ->add(new FeatureMiddleware('git', null, 'view'));
                $protected->put('/git/repositories/{id}', [GitRepositoryController::class, 'update'])
                    ->add(new FeatureMiddleware('git', null, 'manage'));
                $protected->delete('/git/repositories/{id}', [GitRepositoryController::class, 'delete'])
                    ->add(new FeatureMiddleware('git', null, 'manage'));
                $protected->post('/git/repositories/{id}/sync', [GitRepositoryController::class, 'sync'])
                    ->add(new FeatureMiddleware('git', null, 'sync'));
                $protected->get('/git/folders', [GitRepositoryController::class, 'index'])
                    ->add(new FeatureMiddleware('git', null, 'view'));
                $protected->post('/git/folders', [GitRepositoryController::class, 'createFolder'])
                    ->add(new FeatureMiddleware('git', null, 'manage'));
                $protected->put('/git/folders/{id}', [GitRepositoryController::class, 'updateFolder'])
                    ->add(new FeatureMiddleware('git', null, 'manage'));
                $protected->delete('/git/folders/{id}', [GitRepositoryController::class, 'deleteFolder'])
                    ->add(new FeatureMiddleware('git', null, 'manage'));

                // SSL Certificate Monitor
                $protected->get('/ssl/certificates', [SslCertificateController::class, 'index'])
                    ->add(new FeatureMiddleware('ssl', null, 'view'));
                $protected->post('/ssl/certificates', [SslCertificateController::class, 'create'])
                    ->add(new FeatureMiddleware('ssl', null, 'manage'));
                $protected->get('/ssl/certificates/stats', [SslCertificateController::class, 'stats'])
                    ->add(new FeatureMiddleware('ssl', null, 'view'));
                $protected->post('/ssl/certificates/check-all', [SslCertificateController::class, 'checkAll'])
                    ->add(new FeatureMiddleware('ssl', null, 'check'));
                $protected->get('/ssl/certificates/{id}', [SslCertificateController::class, 'show'])
                    ->add(new FeatureMiddleware('ssl', null, 'view'));
                $protected->put('/ssl/certificates/{id}', [SslCertificateController::class, 'update'])
                    ->add(new FeatureMiddleware('ssl', null, 'manage'));
                $protected->delete('/ssl/certificates/{id}', [SslCertificateController::class, 'delete'])
                    ->add(new FeatureMiddleware('ssl', null, 'manage'));
                $protected->post('/ssl/certificates/{id}/check', [SslCertificateController::class, 'check'])
                    ->add(new FeatureMiddleware('ssl', null, 'check'));
                $protected->post('/ssl/folders', [SslCertificateController::class, 'createFolder'])
                    ->add(new FeatureMiddleware('ssl', null, 'manage'));
                $protected->put('/ssl/folders/{id}', [SslCertificateController::class, 'updateFolder'])
                    ->add(new FeatureMiddleware('ssl', null, 'manage'));
                $protected->delete('/ssl/folders/{id}', [SslCertificateController::class, 'deleteFolder'])
                    ->add(new FeatureMiddleware('ssl', null, 'manage'));

                // Public Galleries
                $protected->get('/galleries', [PublicGalleryController::class, 'index'])
                    ->add(new FeatureMiddleware('galleries', null, 'view'));
                $protected->post('/galleries', [PublicGalleryController::class, 'create'])
                    ->add(new FeatureMiddleware('galleries', null, 'manage'));
                $protected->get('/galleries/{id}', [PublicGalleryController::class, 'show'])
                    ->add(new FeatureMiddleware('galleries', null, 'view'));
                $protected->put('/galleries/{id}', [PublicGalleryController::class, 'update'])
                    ->add(new FeatureMiddleware('galleries', null, 'manage'));
                $protected->delete('/galleries/{id}', [PublicGalleryController::class, 'delete'])
                    ->add(new FeatureMiddleware('galleries', null, 'manage'));
                $protected->get('/galleries/{id}/stats', [PublicGalleryController::class, 'stats'])
                    ->add(new FeatureMiddleware('galleries', null, 'view'));
                $protected->post('/galleries/{id}/items', [PublicGalleryController::class, 'addItem'])
                    ->add(new FeatureMiddleware('galleries', null, 'manage'));
                $protected->put('/galleries/{id}/items/{itemId}', [PublicGalleryController::class, 'updateItem'])
                    ->add(new FeatureMiddleware('galleries', null, 'manage'));
                $protected->delete('/galleries/{id}/items/{itemId}', [PublicGalleryController::class, 'removeItem'])
                    ->add(new FeatureMiddleware('galleries', null, 'manage'));
                $protected->post('/galleries/{id}/reorder', [PublicGalleryController::class, 'reorderItems'])
                    ->add(new FeatureMiddleware('galleries', null, 'manage'));
                $protected->post('/gallery-categories', [PublicGalleryController::class, 'createCategory'])
                    ->add(new FeatureMiddleware('galleries', null, 'manage'));
                $protected->put('/gallery-categories/{id}', [PublicGalleryController::class, 'updateCategory'])
                    ->add(new FeatureMiddleware('galleries', null, 'manage'));
                $protected->delete('/gallery-categories/{id}', [PublicGalleryController::class, 'deleteCategory'])
                    ->add(new FeatureMiddleware('galleries', null, 'manage'));

                // Discord Manager
                // Accounts
                $protected->get('/discord/accounts', [DiscordController::class, 'getAccounts'])
                    ->add(new PermissionMiddleware('discord.view'));
                $protected->post('/discord/accounts', [DiscordController::class, 'addAccount'])
                    ->add(new PermissionMiddleware('discord.manage_accounts'));
                $protected->delete('/discord/accounts/{id}', [DiscordController::class, 'deleteAccount'])
                    ->add(new PermissionMiddleware('discord.manage_accounts'));
                $protected->post('/discord/accounts/{id}/sync', [DiscordController::class, 'syncAccount'])
                    ->add(new PermissionMiddleware('discord.view'));
                // Servers & Channels
                $protected->get('/discord/servers', [DiscordController::class, 'getServers'])
                    ->add(new PermissionMiddleware('discord.view'));
                $protected->get('/discord/servers/{id}/channels', [DiscordController::class, 'getServerChannels'])
                    ->add(new PermissionMiddleware('discord.view'));
                $protected->post('/discord/servers/{id}/sync', [DiscordController::class, 'syncServerChannels'])
                    ->add(new PermissionMiddleware('discord.view'));
                $protected->post('/discord/servers/{id}/favorite', [DiscordController::class, 'toggleServerFavorite'])
                    ->add(new PermissionMiddleware('discord.view'));
                $protected->get('/discord/dm-channels', [DiscordController::class, 'getDMChannels'])
                    ->add(new PermissionMiddleware('discord.view'));
                // Backups
                $protected->get('/discord/backups', [DiscordController::class, 'getBackups'])
                    ->add(new PermissionMiddleware('discord.view'));
                $protected->post('/discord/backups', [DiscordController::class, 'createBackup'])
                    ->add(new PermissionMiddleware('discord.create_backups'));
                $protected->get('/discord/backups/{id}', [DiscordController::class, 'getBackup'])
                    ->add(new PermissionMiddleware('discord.view'));
                $protected->delete('/discord/backups/{id}', [DiscordController::class, 'deleteBackup'])
                    ->add(new PermissionMiddleware('discord.delete_backups'));
                $protected->get('/discord/backups/{id}/messages', [DiscordController::class, 'getBackupMessages'])
                    ->add(new PermissionMiddleware('discord.view_messages'));
                // Media
                $protected->get('/discord/media', [DiscordController::class, 'getMedia'])
                    ->add(new PermissionMiddleware('discord.download_media'));
                $protected->get('/discord/media/{id}', [DiscordController::class, 'serveMedia'])
                    ->add(new PermissionMiddleware('discord.download_media'));
                // Message Deletion
                $protected->get('/discord/messages/search', [DiscordController::class, 'searchOwnMessages'])
                    ->add(new PermissionMiddleware('discord.delete_messages'));
                $protected->post('/discord/delete-jobs', [DiscordController::class, 'createDeleteJob'])
                    ->add(new PermissionMiddleware('discord.delete_messages'));
                $protected->get('/discord/delete-jobs', [DiscordController::class, 'getDeleteJobs'])
                    ->add(new PermissionMiddleware('discord.delete_messages'));
                $protected->get('/discord/delete-jobs/{id}', [DiscordController::class, 'getDeleteJob'])
                    ->add(new PermissionMiddleware('discord.delete_messages'));
                $protected->post('/discord/delete-jobs/{id}/cancel', [DiscordController::class, 'cancelDeleteJob'])
                    ->add(new PermissionMiddleware('discord.delete_messages'));

            })->add(AuthMiddleware::class)->add(ApiKeyMiddleware::class);

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

            // Public Storage Download (no auth required)
            $group->get('/storage/public/{token}', [StorageController::class, 'getPublicShare']);
            $group->get('/storage/public/{token}/download', [StorageController::class, 'downloadPublic']);
            $group->post('/storage/public/{token}/download', [StorageController::class, 'downloadPublic']);
            $group->get('/storage/public/{token}/thumbnail', [StorageController::class, 'thumbnailPublic']);

            // Public Checklist Routes (no auth required)
            $group->get('/checklists/public/{token}', [SharedChecklistController::class, 'getPublic']);
            $group->get('/checklists/public/{token}/stream', [SharedChecklistController::class, 'stream']);
            $group->get('/checklists/public/{token}/updates', [SharedChecklistController::class, 'getUpdates']);
            $group->post('/checklists/public/{token}/entries', [SharedChecklistController::class, 'addEntry']);
            $group->put('/checklists/public/{token}/entries/{entryId}', [SharedChecklistController::class, 'updateEntry']);
            $group->delete('/checklists/public/{token}/entries/{entryId}', [SharedChecklistController::class, 'deleteEntry']);
            $group->post('/checklists/public/{token}/entries/{entryId}/image', [SharedChecklistController::class, 'uploadEntryImage']);
            $group->delete('/checklists/public/{token}/entries/{entryId}/image', [SharedChecklistController::class, 'deleteEntryImage']);
            $group->post('/checklists/public/{token}/items', [SharedChecklistController::class, 'addItemPublic']);
            $group->put('/checklists/public/{token}/items/{itemId}', [SharedChecklistController::class, 'updateItemPublic']);
            $group->delete('/checklists/public/{token}/items/{itemId}', [SharedChecklistController::class, 'deleteItemPublic']);
            $group->post('/checklists/public/{token}/categories', [SharedChecklistController::class, 'addCategoryPublic']);
            // Public checklist image serve
            $group->get('/checklists/images/{filename}', [SharedChecklistController::class, 'serveImage']);

            // Public Short Link Routes
            $group->get('/s/{code}', [LinkController::class, 'redirect']);
            $group->post('/s/{code}', [LinkController::class, 'redirect']); // For password-protected links
            $group->get('/s/{code}/info', [LinkController::class, 'getLinkInfo']);

            // Public Gallery Routes (no auth required)
            $group->get('/gallery/{slug}', [PublicGalleryController::class, 'viewPublic']);
            $group->post('/gallery/{slug}', [PublicGalleryController::class, 'viewPublic']); // For password-protected galleries
        });
    }
}
