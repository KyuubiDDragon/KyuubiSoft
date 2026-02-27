import type { Component } from 'vue'
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
import AuditActivityWidget from './AuditActivityWidget.vue'
import CronJobsWidget from './CronJobsWidget.vue'
import DeploymentStatusWidget from './DeploymentStatusWidget.vue'

/**
 * Widget type identifiers
 */
export type WidgetType =
  | 'quick_stats'
  | 'recent_tasks'
  | 'recent_documents'
  | 'productivity_chart'
  | 'calendar_preview'
  | 'uptime_status'
  | 'time_tracking_today'
  | 'kanban_summary'
  | 'recent_activity'
  | 'quick_notes'
  | 'weather'
  | 'countdown'
  | 'link_stats'
  | 'storage_usage'
  | 'backup_status'
  | 'system_health'
  | 'github_activity'
  | 'pomodoro_timer'
  | 'audit_activity'
  | 'cron_jobs'
  | 'deployment_status'

/**
 * Registry mapping widget type keys to their Vue components
 */
export const widgetRegistry: Record<WidgetType, Component> = {
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
  audit_activity: AuditActivityWidget,
  cron_jobs: CronJobsWidget,
  deployment_status: DeploymentStatusWidget,
}
