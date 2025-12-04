<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useProjectStore } from '@/stores/project'
import api from '@/core/api/axios'
import {
  ListBulletIcon,
  DocumentTextIcon,
  ClockIcon,
  ViewColumnsIcon,
  ChartBarIcon,
  CalendarIcon,
  SignalIcon,
  PencilSquareIcon,
  ArrowPathIcon,
  Cog6ToothIcon,
  PlusIcon,
  XMarkIcon,
  CheckIcon,
  ExclamationTriangleIcon,
  ArrowTrendingUpIcon,
  PlayIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const authStore = useAuthStore()
const projectStore = useProjectStore()

const widgets = ref([])
const availableWidgets = ref({})
const widgetData = ref({})
const isLoading = ref(true)
const isEditMode = ref(false)
const showAddWidget = ref(false)
const analyticsData = ref(null)

// Widget type icons
const widgetIcons = {
  quick_stats: ChartBarIcon,
  recent_tasks: ListBulletIcon,
  recent_documents: DocumentTextIcon,
  uptime_status: SignalIcon,
  time_tracking_today: ClockIcon,
  kanban_summary: ViewColumnsIcon,
  productivity_chart: ArrowTrendingUpIcon,
  calendar_preview: CalendarIcon,
  quick_notes: PencilSquareIcon,
  recent_activity: ArrowPathIcon,
}

async function fetchWidgets() {
  try {
    const response = await api.get('/api/v1/dashboard/widgets')
    widgets.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch widgets:', error)
  }
}

async function fetchAvailableWidgets() {
  try {
    const response = await api.get('/api/v1/dashboard/widgets/available')
    availableWidgets.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch available widgets:', error)
  }
}

async function fetchWidgetData(widgetType) {
  try {
    const params = { type: widgetType }
    if (projectStore.selectedProjectId) {
      params.project_id = projectStore.selectedProjectId
    }
    const response = await api.get('/api/v1/analytics/widget-data', { params })
    widgetData.value[widgetType] = response.data.data
  } catch (error) {
    console.error(`Failed to fetch data for ${widgetType}:`, error)
  }
}

async function fetchAnalytics() {
  try {
    const response = await api.get('/api/v1/analytics/productivity', {
      params: { days: 30 }
    })
    analyticsData.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch analytics:', error)
  }
}

async function saveLayout() {
  try {
    await api.post('/api/v1/dashboard/widgets/layout', {
      widgets: widgets.value
    })
    isEditMode.value = false
  } catch (error) {
    console.error('Failed to save layout:', error)
  }
}

async function addWidget(widgetType) {
  const widgetInfo = availableWidgets.value[widgetType]
  if (!widgetInfo) return

  const newWidget = {
    widget_type: widgetType,
    title: widgetInfo.title,
    position_x: 0,
    position_y: widgets.value.length,
    width: widgetInfo.default_width,
    height: widgetInfo.default_height,
  }

  widgets.value.push(newWidget)
  await saveLayout()
  await fetchWidgetData(widgetType)
  showAddWidget.value = false
}

async function removeWidget(index) {
  widgets.value.splice(index, 1)
  await saveLayout()
}

async function resetDashboard() {
  try {
    const response = await api.post('/api/v1/dashboard/widgets/reset')
    widgets.value = response.data.data
    isEditMode.value = false
    loadAllWidgetData()
  } catch (error) {
    console.error('Failed to reset dashboard:', error)
  }
}

async function loadAllWidgetData() {
  isLoading.value = true
  await Promise.all([
    fetchWidgets(),
    fetchAvailableWidgets(),
    fetchAnalytics(),
  ])

  // Load data for each widget
  for (const widget of widgets.value) {
    await fetchWidgetData(widget.widget_type)
  }
  isLoading.value = false
}

// Watch for project changes
watch(() => projectStore.selectedProjectId, () => {
  loadAllWidgetData()
})

onMounted(() => {
  loadAllWidgetData()
})

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatHours(minutes) {
  if (!minutes) return '0h'
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`
}

function getStatusColor(status) {
  return status === 'up' ? 'text-green-400' : 'text-red-400'
}

function getStatusBg(status) {
  return status === 'up' ? 'bg-green-500' : 'bg-red-500'
}

// Check which widgets are not yet added
const availableToAdd = computed(() => {
  const addedTypes = widgets.value.map(w => w.widget_type)
  return Object.entries(availableWidgets.value)
    .filter(([type]) => !addedTypes.includes(type))
    .map(([type, info]) => ({ type, ...info }))
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">
          Willkommen zurück, {{ authStore.user?.username || 'User' }}!
        </h1>
        <p class="text-gray-400 mt-1">
          Hier ist dein persönliches Dashboard.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          v-if="isEditMode"
          @click="resetDashboard"
          class="btn-secondary text-sm"
        >
          Zurücksetzen
        </button>
        <button
          v-if="isEditMode"
          @click="showAddWidget = true"
          class="btn-secondary text-sm"
        >
          <PlusIcon class="w-4 h-4 mr-1" />
          Widget hinzufügen
        </button>
        <button
          @click="isEditMode ? saveLayout() : isEditMode = true"
          class="btn-primary text-sm"
        >
          <Cog6ToothIcon v-if="!isEditMode" class="w-4 h-4 mr-1" />
          <CheckIcon v-else class="w-4 h-4 mr-1" />
          {{ isEditMode ? 'Speichern' : 'Anpassen' }}
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <div v-for="i in 4" :key="i" class="card p-6 animate-pulse">
        <div class="h-4 bg-dark-600 rounded w-1/3 mb-4"></div>
        <div class="h-8 bg-dark-600 rounded w-1/2"></div>
      </div>
    </div>

    <!-- Widgets Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <template v-for="(widget, index) in widgets" :key="widget.id || index">
        <!-- Quick Stats Widget -->
        <div
          v-if="widget.widget_type === 'quick_stats'"
          class="card p-6 col-span-full lg:col-span-4 relative group"
        >
          <button
            v-if="isEditMode"
            @click="removeWidget(index)"
            class="absolute top-2 right-2 p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
          <h3 class="text-lg font-semibold text-white mb-4">{{ widget.title }}</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-dark-700/50 rounded-lg p-4">
              <p class="text-gray-400 text-sm">Listen</p>
              <p class="text-2xl font-bold text-white">{{ widgetData.quick_stats?.lists || 0 }}</p>
            </div>
            <div class="bg-dark-700/50 rounded-lg p-4">
              <p class="text-gray-400 text-sm">Offene Aufgaben</p>
              <p class="text-2xl font-bold text-yellow-400">{{ widgetData.quick_stats?.open_tasks || 0 }}</p>
            </div>
            <div class="bg-dark-700/50 rounded-lg p-4">
              <p class="text-gray-400 text-sm">Dokumente</p>
              <p class="text-2xl font-bold text-white">{{ widgetData.quick_stats?.documents || 0 }}</p>
            </div>
            <div class="bg-dark-700/50 rounded-lg p-4">
              <p class="text-gray-400 text-sm">Kanban Karten</p>
              <p class="text-2xl font-bold text-white">{{ widgetData.quick_stats?.kanban_cards || 0 }}</p>
            </div>
          </div>
        </div>

        <!-- Recent Tasks Widget -->
        <div
          v-else-if="widget.widget_type === 'recent_tasks'"
          class="card p-6 col-span-1 lg:col-span-2 relative group"
        >
          <button
            v-if="isEditMode"
            @click="removeWidget(index)"
            class="absolute top-2 right-2 p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
            <router-link to="/lists" class="text-sm text-primary-400 hover:text-primary-300">Alle</router-link>
          </div>
          <div class="space-y-2">
            <div
              v-for="task in (widgetData.recent_tasks || []).slice(0, 5)"
              :key="task.id"
              class="flex items-center gap-3 p-2 rounded-lg hover:bg-dark-700/50 transition-colors"
            >
              <div
                class="w-3 h-3 rounded-full flex-shrink-0"
                :style="{ backgroundColor: task.color || '#3B82F6' }"
              ></div>
              <div class="flex-1 min-w-0">
                <p class="text-sm text-white truncate">{{ task.content }}</p>
                <p class="text-xs text-gray-500">{{ task.list_title }}</p>
              </div>
              <span
                v-if="task.due_date"
                class="text-xs px-2 py-0.5 rounded"
                :class="new Date(task.due_date) < new Date() ? 'bg-red-500/20 text-red-400' : 'bg-yellow-500/20 text-yellow-400'"
              >
                {{ formatDate(task.due_date) }}
              </span>
            </div>
            <p v-if="!widgetData.recent_tasks?.length" class="text-gray-500 text-sm text-center py-4">
              Keine offenen Aufgaben
            </p>
          </div>
        </div>

        <!-- Recent Documents Widget -->
        <div
          v-else-if="widget.widget_type === 'recent_documents'"
          class="card p-6 col-span-1 lg:col-span-2 relative group"
        >
          <button
            v-if="isEditMode"
            @click="removeWidget(index)"
            class="absolute top-2 right-2 p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
            <router-link to="/documents" class="text-sm text-primary-400 hover:text-primary-300">Alle</router-link>
          </div>
          <div class="space-y-2">
            <router-link
              v-for="doc in (widgetData.recent_documents || []).slice(0, 5)"
              :key="doc.id"
              :to="`/documents?open=${doc.id}`"
              class="flex items-center gap-3 p-2 rounded-lg hover:bg-dark-700/50 transition-colors"
            >
              <DocumentTextIcon class="w-5 h-5 text-green-400 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <p class="text-sm text-white truncate">{{ doc.title }}</p>
                <p class="text-xs text-gray-500">{{ doc.format }}</p>
              </div>
              <span class="text-xs text-gray-500">{{ formatDate(doc.updated_at) }}</span>
            </router-link>
            <p v-if="!widgetData.recent_documents?.length" class="text-gray-500 text-sm text-center py-4">
              Keine Dokumente
            </p>
          </div>
        </div>

        <!-- Productivity Chart Widget -->
        <div
          v-else-if="widget.widget_type === 'productivity_chart'"
          class="card p-6 col-span-1 lg:col-span-2 relative group"
        >
          <button
            v-if="isEditMode"
            @click="removeWidget(index)"
            class="absolute top-2 right-2 p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
          <h3 class="text-lg font-semibold text-white mb-4">{{ widget.title }}</h3>

          <!-- Productivity Score -->
          <div v-if="analyticsData?.productivity_score" class="mb-4">
            <div class="flex items-center justify-between mb-2">
              <span class="text-gray-400 text-sm">Produktivitäts-Score</span>
              <span class="text-2xl font-bold text-white">{{ analyticsData.productivity_score.score }}/100</span>
            </div>
            <div class="h-2 bg-dark-600 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-500"
                :class="analyticsData.productivity_score.score >= 70 ? 'bg-green-500' : analyticsData.productivity_score.score >= 40 ? 'bg-yellow-500' : 'bg-red-500'"
                :style="{ width: `${analyticsData.productivity_score.score}%` }"
              ></div>
            </div>
            <div class="grid grid-cols-3 gap-2 mt-3 text-xs">
              <div class="text-center">
                <p class="text-gray-500">Aufgaben</p>
                <p class="text-white font-medium">{{ analyticsData.productivity_score.factors.tasks }}%</p>
              </div>
              <div class="text-center">
                <p class="text-gray-500">Zeit</p>
                <p class="text-white font-medium">{{ analyticsData.productivity_score.factors.time }}%</p>
              </div>
              <div class="text-center">
                <p class="text-gray-500">Konsistenz</p>
                <p class="text-white font-medium">{{ analyticsData.productivity_score.factors.consistency }}%</p>
              </div>
            </div>
          </div>

          <!-- Tasks completed chart -->
          <div v-if="analyticsData?.tasks_completed?.daily?.length" class="mt-4">
            <p class="text-sm text-gray-400 mb-2">Erledigte Aufgaben (14 Tage)</p>
            <div class="flex items-end gap-1 h-20">
              <div
                v-for="(day, i) in analyticsData.tasks_completed.daily.slice(-14)"
                :key="i"
                class="flex-1 bg-primary-500/20 rounded-t relative group/bar"
                :style="{ height: `${Math.min(100, (day.count / Math.max(...analyticsData.tasks_completed.daily.map(d => d.count || 1))) * 100)}%` }"
              >
                <div
                  class="absolute bottom-0 left-0 right-0 bg-primary-500 rounded-t"
                  :style="{ height: '100%' }"
                ></div>
                <div class="absolute -top-6 left-1/2 -translate-x-1/2 bg-dark-600 px-1 py-0.5 rounded text-xs text-white opacity-0 group-hover/bar:opacity-100 whitespace-nowrap z-10">
                  {{ day.count }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Calendar Preview Widget -->
        <div
          v-else-if="widget.widget_type === 'calendar_preview'"
          class="card p-6 col-span-1 lg:col-span-2 relative group"
        >
          <button
            v-if="isEditMode"
            @click="removeWidget(index)"
            class="absolute top-2 right-2 p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
            <router-link to="/calendar" class="text-sm text-primary-400 hover:text-primary-300">Kalender</router-link>
          </div>
          <div class="space-y-2">
            <div
              v-for="event in (widgetData.calendar_preview || []).slice(0, 5)"
              :key="event.id"
              class="flex items-center gap-3 p-2 rounded-lg hover:bg-dark-700/50 transition-colors"
            >
              <div
                class="w-1 h-8 rounded-full flex-shrink-0"
                :class="`bg-${event.color || 'primary'}-500`"
                :style="{ backgroundColor: event.color }"
              ></div>
              <div class="flex-1 min-w-0">
                <p class="text-sm text-white truncate">{{ event.title }}</p>
                <p class="text-xs text-gray-500">
                  {{ event.source_type === 'kanban' ? 'Kanban' : event.source_type === 'task' ? 'Aufgabe' : 'Event' }}
                </p>
              </div>
              <span class="text-xs text-gray-400">{{ formatDate(event.start_date) }}</span>
            </div>
            <p v-if="!widgetData.calendar_preview?.length" class="text-gray-500 text-sm text-center py-4">
              Keine Termine diese Woche
            </p>
          </div>
        </div>

        <!-- Uptime Status Widget -->
        <div
          v-else-if="widget.widget_type === 'uptime_status'"
          class="card p-6 col-span-1 relative group"
        >
          <button
            v-if="isEditMode"
            @click="removeWidget(index)"
            class="absolute top-2 right-2 p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
            <router-link to="/uptime" class="text-sm text-primary-400 hover:text-primary-300">Alle</router-link>
          </div>
          <div class="space-y-2">
            <div
              v-for="monitor in (widgetData.uptime_status || []).slice(0, 5)"
              :key="monitor.id"
              class="flex items-center gap-3 p-2 rounded-lg hover:bg-dark-700/50 transition-colors"
            >
              <span
                class="w-2 h-2 rounded-full flex-shrink-0"
                :class="getStatusBg(monitor.status)"
              ></span>
              <div class="flex-1 min-w-0">
                <p class="text-sm text-white truncate">{{ monitor.name }}</p>
                <p class="text-xs text-gray-500">{{ monitor.response_time }}ms</p>
              </div>
              <span class="text-xs" :class="getStatusColor(monitor.status)">
                {{ monitor.status === 'up' ? 'Online' : 'Offline' }}
              </span>
            </div>
            <p v-if="!widgetData.uptime_status?.length" class="text-gray-500 text-sm text-center py-4">
              Keine Monitore
            </p>
          </div>
        </div>

        <!-- Time Tracking Today Widget -->
        <div
          v-else-if="widget.widget_type === 'time_tracking_today'"
          class="card p-6 col-span-1 relative group"
        >
          <button
            v-if="isEditMode"
            @click="removeWidget(index)"
            class="absolute top-2 right-2 p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
          <h3 class="text-lg font-semibold text-white mb-4">{{ widget.title }}</h3>
          <div class="text-center">
            <p class="text-4xl font-bold text-white">
              {{ widgetData.time_tracking_today?.total_hours || 0 }}h
            </p>
            <p class="text-gray-500 text-sm mt-1">
              {{ widgetData.time_tracking_today?.entry_count || 0 }} Einträge
            </p>
            <div
              v-if="widgetData.time_tracking_today?.running"
              class="mt-4 p-3 bg-green-500/10 border border-green-500/30 rounded-lg"
            >
              <div class="flex items-center gap-2 text-green-400">
                <PlayIcon class="w-4 h-4 animate-pulse" />
                <span class="text-sm font-medium">Läuft</span>
              </div>
              <p class="text-xs text-gray-400 mt-1 truncate">
                {{ widgetData.time_tracking_today.running.project_name || 'Kein Projekt' }}
              </p>
            </div>
          </div>
        </div>

        <!-- Kanban Summary Widget -->
        <div
          v-else-if="widget.widget_type === 'kanban_summary'"
          class="card p-6 col-span-1 lg:col-span-2 relative group"
        >
          <button
            v-if="isEditMode"
            @click="removeWidget(index)"
            class="absolute top-2 right-2 p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
            <router-link to="/kanban" class="text-sm text-primary-400 hover:text-primary-300">Boards</router-link>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-gray-400 mb-2">Boards</p>
              <div class="space-y-1">
                <router-link
                  v-for="board in (widgetData.kanban_summary?.boards || []).slice(0, 3)"
                  :key="board.id"
                  :to="`/kanban/${board.id}`"
                  class="flex items-center justify-between p-2 rounded-lg hover:bg-dark-700/50 transition-colors"
                >
                  <span class="text-sm text-white truncate">{{ board.title }}</span>
                  <span class="text-xs text-gray-500">{{ board.card_count }}</span>
                </router-link>
              </div>
            </div>
            <div>
              <p class="text-sm text-gray-400 mb-2">Bald fällig</p>
              <div class="space-y-1">
                <div
                  v-for="card in (widgetData.kanban_summary?.due_soon || []).slice(0, 3)"
                  :key="card.id"
                  class="p-2 rounded-lg bg-yellow-500/10"
                >
                  <p class="text-sm text-white truncate">{{ card.title }}</p>
                  <p class="text-xs text-yellow-400">{{ formatDate(card.due_date) }}</p>
                </div>
                <p v-if="!widgetData.kanban_summary?.due_soon?.length" class="text-xs text-gray-500">
                  Keine fälligen Karten
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activity Widget -->
        <div
          v-else-if="widget.widget_type === 'recent_activity'"
          class="card p-6 col-span-1 lg:col-span-2 relative group"
        >
          <button
            v-if="isEditMode"
            @click="removeWidget(index)"
            class="absolute top-2 right-2 p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
          <h3 class="text-lg font-semibold text-white mb-4">{{ widget.title }}</h3>
          <div class="space-y-2 max-h-64 overflow-y-auto">
            <div
              v-for="activity in (widgetData.recent_activity || []).slice(0, 10)"
              :key="activity.created_at"
              class="flex items-center gap-3 p-2 text-sm"
            >
              <ArrowPathIcon class="w-4 h-4 text-gray-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <span class="text-gray-400">{{ activity.action }}</span>
                <span class="text-white"> {{ activity.entity_type }}</span>
              </div>
              <span class="text-xs text-gray-500">{{ formatDate(activity.created_at) }}</span>
            </div>
            <p v-if="!widgetData.recent_activity?.length" class="text-gray-500 text-sm text-center py-4">
              Keine Aktivität
            </p>
          </div>
        </div>

        <!-- Quick Notes Widget -->
        <div
          v-else-if="widget.widget_type === 'quick_notes'"
          class="card p-6 col-span-1 relative group"
        >
          <button
            v-if="isEditMode"
            @click="removeWidget(index)"
            class="absolute top-2 right-2 p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
          <h3 class="text-lg font-semibold text-white mb-4">{{ widget.title }}</h3>
          <div class="space-y-2">
            <div
              v-for="note in (widgetData.quick_notes || []).slice(0, 4)"
              :key="note.id"
              class="p-2 rounded-lg bg-dark-700/50 text-sm text-gray-300 line-clamp-2"
            >
              {{ note.content }}
            </div>
            <p v-if="!widgetData.quick_notes?.length" class="text-gray-500 text-sm text-center py-4">
              Keine Notizen
            </p>
          </div>
        </div>

        <!-- Generic fallback widget -->
        <div
          v-else
          class="card p-6 col-span-1 relative group"
        >
          <button
            v-if="isEditMode"
            @click="removeWidget(index)"
            class="absolute top-2 right-2 p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
          <h3 class="text-lg font-semibold text-white mb-4">{{ widget.title }}</h3>
          <p class="text-gray-500 text-sm">Widget: {{ widget.widget_type }}</p>
        </div>
      </template>
    </div>

    <!-- Empty state -->
    <div
      v-if="!isLoading && widgets.length === 0"
      class="card p-12 text-center"
    >
      <ChartBarIcon class="w-16 h-16 text-gray-600 mx-auto mb-4" />
      <h3 class="text-lg font-semibold text-white mb-2">Kein Dashboard konfiguriert</h3>
      <p class="text-gray-500 mb-4">Füge Widgets hinzu, um dein Dashboard anzupassen.</p>
      <button @click="resetDashboard" class="btn-primary">
        Standard-Layout laden
      </button>
    </div>

    <!-- Add Widget Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showAddWidget"
          class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
          
        >
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-lg">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">Widget hinzufügen</h3>
              <button @click="showAddWidget = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <div class="p-6 space-y-2 max-h-96 overflow-y-auto">
              <button
                v-for="widget in availableToAdd"
                :key="widget.type"
                @click="addWidget(widget.type)"
                class="w-full flex items-center gap-4 p-4 rounded-lg bg-dark-700/50 hover:bg-dark-700 transition-colors text-left"
              >
                <div class="w-10 h-10 rounded-lg bg-primary-500/20 flex items-center justify-center">
                  <component :is="widgetIcons[widget.type] || ChartBarIcon" class="w-5 h-5 text-primary-400" />
                </div>
                <div>
                  <p class="text-white font-medium">{{ widget.title }}</p>
                  <p class="text-sm text-gray-500">{{ widget.default_width }}x{{ widget.default_height }}</p>
                </div>
              </button>
              <p v-if="availableToAdd.length === 0" class="text-center text-gray-500 py-4">
                Alle Widgets wurden bereits hinzugefügt
              </p>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
