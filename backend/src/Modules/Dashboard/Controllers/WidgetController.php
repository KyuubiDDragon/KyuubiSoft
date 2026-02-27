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
        'quick_stats' => ['title' => 'Schnellstatistik', 'default_width' => 2, 'default_height' => 5, 'description' => 'Übersicht über wichtige Kennzahlen'],
        'recent_tasks' => ['title' => 'Aktuelle Aufgaben', 'default_width' => 1, 'default_height' => 10, 'description' => 'Liste der zuletzt bearbeiteten Aufgaben'],
        'recent_documents' => ['title' => 'Letzte Dokumente', 'default_width' => 1, 'default_height' => 10, 'description' => 'Zuletzt bearbeitete Dokumente'],
        'uptime_status' => ['title' => 'Uptime Status', 'default_width' => 1, 'default_height' => 6, 'description' => 'Status der Uptime-Monitore'],
        'time_tracking_today' => ['title' => 'Zeiterfassung Heute', 'default_width' => 1, 'default_height' => 6, 'description' => 'Heutige Zeiterfassung'],
        'kanban_summary' => ['title' => 'Kanban Übersicht', 'default_width' => 1, 'default_height' => 10, 'description' => 'Zusammenfassung der Kanban-Boards'],
        'productivity_chart' => ['title' => 'Produktivität', 'default_width' => 2, 'default_height' => 12, 'description' => 'Produktivitätsdiagramm'],
        'calendar_preview' => ['title' => 'Kalender', 'default_width' => 1, 'default_height' => 12, 'description' => 'Kommende Termine'],
        'quick_notes' => ['title' => 'Quick Notes', 'default_width' => 1, 'default_height' => 10, 'description' => 'Schnelle Notizen'],
        'recent_activity' => ['title' => 'Letzte Aktivität', 'default_width' => 1, 'default_height' => 10, 'description' => 'Aktivitätsprotokoll'],
        // New widgets
        'recurring_tasks_upcoming' => ['title' => 'Wiederkehrende Aufgaben', 'default_width' => 1, 'default_height' => 10, 'description' => 'Bevorstehende wiederkehrende Aufgaben'],
        'favorites_quick_access' => ['title' => 'Favoriten', 'default_width' => 1, 'default_height' => 10, 'description' => 'Schnellzugriff auf Favoriten'],
        'storage_usage' => ['title' => 'Speichernutzung', 'default_width' => 1, 'default_height' => 6, 'description' => 'Cloud-Speichernutzung'],
        'password_health' => ['title' => 'Passwort-Sicherheit', 'default_width' => 1, 'default_height' => 6, 'description' => 'Übersicht zur Passwortsicherheit'],
        'project_progress' => ['title' => 'Projektfortschritt', 'default_width' => 2, 'default_height' => 12, 'description' => 'Fortschritt aktiver Projekte'],
        'checklist_progress' => ['title' => 'Checklisten', 'default_width' => 1, 'default_height' => 10, 'description' => 'Fortschritt der Checklisten'],
        // Extended widgets
        'weather' => ['title' => 'Wetter', 'default_width' => 1, 'default_height' => 10, 'description' => 'Wettervorhersage für deinen Standort', 'configurable' => true],
        'countdown' => ['title' => 'Countdown', 'default_width' => 1, 'default_height' => 5, 'description' => 'Countdown zu einem Ereignis', 'configurable' => true],
        'custom_links' => ['title' => 'Schnelllinks', 'default_width' => 1, 'default_height' => 10, 'description' => 'Benutzerdefinierte Links', 'configurable' => true],
        'quote_of_day' => ['title' => 'Zitat des Tages', 'default_width' => 2, 'default_height' => 5, 'description' => 'Inspirierendes Zitat'],
        'link_stats' => ['title' => 'Link-Statistiken', 'default_width' => 1, 'default_height' => 5, 'description' => 'Übersicht über Short-Links'],
        'backup_status' => ['title' => 'Backup-Status', 'default_width' => 1, 'default_height' => 6, 'description' => 'Status der Backups'],
        'system_health' => ['title' => 'Systemstatus', 'default_width' => 1, 'default_height' => 8, 'description' => 'CPU, RAM und Festplattenauslastung'],
        'github_activity' => ['title' => 'Git-Aktivität', 'default_width' => 1, 'default_height' => 10, 'description' => 'Aktuelle Git-Commits und Aktivitäten'],
        'pomodoro_timer' => ['title' => 'Pomodoro Timer', 'default_width' => 1, 'default_height' => 8, 'description' => 'Pomodoro-Timer für fokussiertes Arbeiten'],
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
