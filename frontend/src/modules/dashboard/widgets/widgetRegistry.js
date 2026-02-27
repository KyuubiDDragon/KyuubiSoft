import QuickStatsWidget from './QuickStatsWidget.vue'
import RecentTasksWidget from './RecentTasksWidget.vue'
import RecentDocumentsWidget from './RecentDocumentsWidget.vue'
import ProductivityChartWidget from './ProductivityChartWidget.vue'
import CalendarPreviewWidget from './CalendarPreviewWidget.vue'
import UptimeStatusWidget from './UptimeStatusWidget.vue'
import TimeTrackingTodayWidget from './TimeTrackingTodayWidget.vue'
import KanbanSummaryWidget from './KanbanSummaryWidget.vue'
import RecentActivityWidget from './RecentActivityWidget.vue'
import QuickNotesWidget from './QuickNotesWidget.vue'
import WeatherWidget from './WeatherWidget.vue'
import CountdownWidget from './CountdownWidget.vue'
import LinkStatsWidget from './LinkStatsWidget.vue'
import StorageUsageWidget from './StorageUsageWidget.vue'
import BackupStatusWidget from './BackupStatusWidget.vue'
import SystemHealthWidget from './SystemHealthWidget.vue'
import GitHubActivityWidget from './GitHubActivityWidget.vue'
import PomodoroTimerWidget from './PomodoroTimerWidget.vue'

export const widgetRegistry = {
  quick_stats: QuickStatsWidget,
  recent_tasks: RecentTasksWidget,
  recent_documents: RecentDocumentsWidget,
  productivity_chart: ProductivityChartWidget,
  calendar_preview: CalendarPreviewWidget,
  uptime_status: UptimeStatusWidget,
  time_tracking_today: TimeTrackingTodayWidget,
  kanban_summary: KanbanSummaryWidget,
  recent_activity: RecentActivityWidget,
  quick_notes: QuickNotesWidget,
  weather: WeatherWidget,
  countdown: CountdownWidget,
  link_stats: LinkStatsWidget,
  storage_usage: StorageUsageWidget,
  backup_status: BackupStatusWidget,
  system_health: SystemHealthWidget,
  github_activity: GitHubActivityWidget,
  pomodoro_timer: PomodoroTimerWidget,
}
