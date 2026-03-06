<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class WidgetController
{
    private const AVAILABLE_WIDGETS = [
        'quick_stats' => ['title' => 'Quick Stats', 'title_key' => 'widgets.quickStats', 'default_width' => 2, 'default_height' => 5, 'description' => 'Overview of key metrics'],
        'recent_tasks' => ['title' => 'Recent Tasks', 'title_key' => 'widgets.recentTasks', 'default_width' => 1, 'default_height' => 10, 'description' => 'Recently edited tasks'],
        'recent_documents' => ['title' => 'Recent Documents', 'title_key' => 'widgets.recentDocuments', 'default_width' => 1, 'default_height' => 10, 'description' => 'Recently edited documents'],
        'uptime_status' => ['title' => 'Uptime Status', 'title_key' => 'widgets.uptimeStatus', 'default_width' => 1, 'default_height' => 6, 'description' => 'Uptime monitor status'],
        'time_tracking_today' => ['title' => 'Time Tracking Today', 'title_key' => 'widgets.timeTrackingToday', 'default_width' => 1, 'default_height' => 6, 'description' => 'Today\'s time tracking'],
        'kanban_summary' => ['title' => 'Kanban Summary', 'title_key' => 'widgets.kanbanSummary', 'default_width' => 1, 'default_height' => 10, 'description' => 'Kanban boards summary'],
        'productivity_chart' => ['title' => 'Productivity', 'title_key' => 'widgets.productivity', 'default_width' => 2, 'default_height' => 12, 'description' => 'Productivity chart'],
        'calendar_preview' => ['title' => 'Calendar', 'title_key' => 'widgets.calendarPreview', 'default_width' => 1, 'default_height' => 12, 'description' => 'Upcoming events'],
        'quick_notes' => ['title' => 'Quick Notes', 'title_key' => 'widgets.quickNotes', 'default_width' => 1, 'default_height' => 10, 'description' => 'Quick notes'],
        'recent_activity' => ['title' => 'Recent Activity', 'title_key' => 'widgets.recentActivity', 'default_width' => 1, 'default_height' => 10, 'description' => 'Activity log'],
        // New widgets
        'recurring_tasks_upcoming' => ['title' => 'Recurring Tasks', 'title_key' => 'widgets.recurringTasks', 'default_width' => 1, 'default_height' => 10, 'description' => 'Upcoming recurring tasks'],
        'favorites_quick_access' => ['title' => 'Favorites', 'title_key' => 'widgets.favorites', 'default_width' => 1, 'default_height' => 10, 'description' => 'Quick access to favorites'],
        'storage_usage' => ['title' => 'Storage Usage', 'title_key' => 'widgets.storageUsage', 'default_width' => 1, 'default_height' => 6, 'description' => 'Cloud storage usage'],
        'password_health' => ['title' => 'Password Health', 'title_key' => 'widgets.passwordHealth', 'default_width' => 1, 'default_height' => 6, 'description' => 'Password security overview'],
        'project_progress' => ['title' => 'Project Progress', 'title_key' => 'widgets.projectProgress', 'default_width' => 2, 'default_height' => 12, 'description' => 'Active project progress'],
        'checklist_progress' => ['title' => 'Checklists', 'title_key' => 'widgets.checklistProgress', 'default_width' => 1, 'default_height' => 10, 'description' => 'Checklist progress'],
        // Extended widgets
        'weather' => ['title' => 'Weather', 'title_key' => 'widgets.weather', 'default_width' => 1, 'default_height' => 10, 'description' => 'Weather forecast for your location', 'configurable' => true],
        'countdown' => ['title' => 'Countdown', 'title_key' => 'widgets.countdown', 'default_width' => 1, 'default_height' => 5, 'description' => 'Countdown to an event', 'configurable' => true],
        'custom_links' => ['title' => 'Quick Links', 'title_key' => 'widgets.customLinks', 'default_width' => 1, 'default_height' => 10, 'description' => 'Custom links', 'configurable' => true],
        'quote_of_day' => ['title' => 'Quote of the Day', 'title_key' => 'widgets.quoteOfDay', 'default_width' => 2, 'default_height' => 5, 'description' => 'Inspirational quote'],
        'link_stats' => ['title' => 'Link Stats', 'title_key' => 'widgets.linkStats', 'default_width' => 1, 'default_height' => 5, 'description' => 'Short links overview'],
        'backup_status' => ['title' => 'Backup Status', 'title_key' => 'widgets.backupStatus', 'default_width' => 1, 'default_height' => 6, 'description' => 'Backup status'],
        'system_health' => ['title' => 'System Health', 'title_key' => 'widgets.systemHealth', 'default_width' => 1, 'default_height' => 8, 'description' => 'CPU, RAM and disk usage'],
        'github_activity' => ['title' => 'Git Activity', 'title_key' => 'widgets.githubActivity', 'default_width' => 1, 'default_height' => 10, 'description' => 'Recent git commits and activity'],
        'pomodoro_timer' => ['title' => 'Pomodoro Timer', 'title_key' => 'widgets.pomodoroTimer', 'default_width' => 1, 'default_height' => 8, 'description' => 'Pomodoro timer for focused work'],
    ];

    public function __construct(
        private readonly Connection $db
    ) {}

    public function getAvailableWidgets(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponse::success(self::AVAILABLE_WIDGETS);
    }

    public function getUserWidgets(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $widgets = $this->db->fetchAllAssociative(
            'SELECT * FROM dashboard_widgets
             WHERE user_id = ? AND is_visible = TRUE
             ORDER BY position_y ASC, position_x ASC',
            [$userId]
        );

        // Parse config JSON
        foreach ($widgets as &$widget) {
            $widget['config'] = $widget['config'] ? json_decode($widget['config'], true) : [];
        }

        // If user has no widgets, create default layout
        if (empty($widgets)) {
            $widgets = $this->createDefaultWidgets($userId);
        }

        return JsonResponse::success($widgets);
    }

    public function saveLayout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['widgets']) || !is_array($data['widgets'])) {
            throw new ValidationException('Widgets array is required');
        }

        // Clear existing widgets
        $this->db->delete('dashboard_widgets', ['user_id' => $userId]);

        // Insert new layout
        foreach ($data['widgets'] as $widget) {
            if (empty($widget['widget_type']) || !isset(self::AVAILABLE_WIDGETS[$widget['widget_type']])) {
                continue;
            }

            $this->db->insert('dashboard_widgets', [
                'id' => Uuid::uuid4()->toString(),
                'user_id' => $userId,
                'widget_type' => $widget['widget_type'],
                'title' => $widget['title'] ?? self::AVAILABLE_WIDGETS[$widget['widget_type']]['title'],
                'position_x' => (int) ($widget['position_x'] ?? 0),
                'position_y' => (int) ($widget['position_y'] ?? 0),
                'width' => (int) ($widget['width'] ?? self::AVAILABLE_WIDGETS[$widget['widget_type']]['default_width']),
                'height' => (int) ($widget['height'] ?? self::AVAILABLE_WIDGETS[$widget['widget_type']]['default_height']),
                'config' => isset($widget['config']) ? json_encode($widget['config']) : null,
                'is_visible' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return JsonResponse::success(null, 'Layout saved successfully');
    }

    public function updateWidget(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $widgetId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $widget = $this->db->fetchAssociative(
            'SELECT * FROM dashboard_widgets WHERE id = ? AND user_id = ?',
            [$widgetId, $userId]
        );

        if (!$widget) {
            throw new NotFoundException('Widget not found');
        }

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        $allowedFields = ['title', 'position_x', 'position_y', 'width', 'height', 'is_visible'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['config'])) {
            $updateData['config'] = json_encode($data['config']);
        }

        $this->db->update('dashboard_widgets', $updateData, ['id' => $widgetId]);

        $widget = $this->db->fetchAssociative('SELECT * FROM dashboard_widgets WHERE id = ?', [$widgetId]);
        $widget['config'] = $widget['config'] ? json_decode($widget['config'], true) : [];

        return JsonResponse::success($widget, 'Widget updated');
    }

    public function deleteWidget(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $widgetId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->db->delete('dashboard_widgets', [
            'id' => $widgetId,
            'user_id' => $userId,
        ]);

        return JsonResponse::success(null, 'Widget removed');
    }

    public function resetToDefault(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $this->db->delete('dashboard_widgets', ['user_id' => $userId]);
        $widgets = $this->createDefaultWidgets($userId);

        return JsonResponse::success($widgets, 'Dashboard reset to default');
    }

    private function createDefaultWidgets(string $userId): array
    {
        $defaultLayout = [
            ['widget_type' => 'quick_stats', 'position_x' => 0, 'position_y' => 0, 'width' => 4, 'height' => 5],
            ['widget_type' => 'recent_tasks', 'position_x' => 0, 'position_y' => 5, 'width' => 2, 'height' => 10],
            ['widget_type' => 'recent_documents', 'position_x' => 2, 'position_y' => 5, 'width' => 2, 'height' => 10],
            ['widget_type' => 'productivity_chart', 'position_x' => 0, 'position_y' => 15, 'width' => 2, 'height' => 12],
            ['widget_type' => 'calendar_preview', 'position_x' => 2, 'position_y' => 15, 'width' => 2, 'height' => 12],
            ['widget_type' => 'uptime_status', 'position_x' => 0, 'position_y' => 27, 'width' => 1, 'height' => 6],
            ['widget_type' => 'time_tracking_today', 'position_x' => 1, 'position_y' => 27, 'width' => 1, 'height' => 6],
            ['widget_type' => 'kanban_summary', 'position_x' => 2, 'position_y' => 27, 'width' => 2, 'height' => 10],
        ];

        $widgets = [];
        foreach ($defaultLayout as $widget) {
            $widgetId = Uuid::uuid4()->toString();
            $this->db->insert('dashboard_widgets', [
                'id' => $widgetId,
                'user_id' => $userId,
                'widget_type' => $widget['widget_type'],
                'title' => self::AVAILABLE_WIDGETS[$widget['widget_type']]['title'],
                'position_x' => $widget['position_x'],
                'position_y' => $widget['position_y'],
                'width' => $widget['width'],
                'height' => $widget['height'],
                'is_visible' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $widgets[] = [
                'id' => $widgetId,
                'widget_type' => $widget['widget_type'],
                'title' => self::AVAILABLE_WIDGETS[$widget['widget_type']]['title'],
                'position_x' => $widget['position_x'],
                'position_y' => $widget['position_y'],
                'width' => $widget['width'],
                'height' => $widget['height'],
                'config' => [],
            ];
        }

        return $widgets;
    }
}
