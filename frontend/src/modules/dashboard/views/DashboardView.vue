<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useProjectStore } from '@/stores/project'
import api from '@/core/api/axios'
import { useDashboardGrid } from '../composables/useDashboardGrid'
import WidgetWrapper from '../components/WidgetWrapper.vue'
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
  SunIcon,
  CloudIcon,
  MapPinIcon,
  LinkIcon,
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
const hasUnsavedChanges = ref(false)
const gridElement = ref(null)

// Initialize grid composable
const {
  GRID_COLS,
  draggedWidget,
  resizingWidget,
  ghostPosition,
  ghostSize,
  gridRows,
  emptyCells,
  canPlaceWidget,
  findNextAvailablePosition,
  startDrag,
  onDrag,
  endDrag,
  dropOnCell,
  startResize,
  getWidgetStyle,
  getGhostStyle,
} = useDashboardGrid(widgets, isEditMode)

// Weather widget state
const weatherData = ref({})
const weatherLoading = ref({})
const showWeatherConfig = ref(false)
const weatherConfigWidget = ref(null)
const weatherSearch = ref('')
const weatherSearchResults = ref([])
const isSearchingWeather = ref(false)

// Countdown widget state
const countdowns = ref({})
const showCountdownConfig = ref(false)
const countdownConfigWidget = ref(null)
const countdownForm = ref({
  title: '',
  date: '',
})

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

async function saveLayout(closeEditMode = true) {
  try {
    await api.post('/api/v1/dashboard/widgets/layout', {
      widgets: widgets.value
    })
    hasUnsavedChanges.value = false
    if (closeEditMode) {
      isEditMode.value = false
    }
  } catch (error) {
    console.error('Failed to save layout:', error)
  }
}

// Mark changes as unsaved when widgets are modified
function markUnsaved() {
  hasUnsavedChanges.value = true
}

// Handle widget drop on grid
function handleWidgetDrop(widget, x, y) {
  if (dropOnCell(widget, x, y)) {
    markUnsaved()
  }
}

// Handle drag over grid
function handleGridDragOver(event) {
  event.preventDefault()
  onDrag(event, gridElement.value)
}

// Handle drag end - apply position and mark unsaved
function handleDragEnd(event) {
  const hadWidget = !!draggedWidget.value
  const oldX = draggedWidget.value?.position_x
  const oldY = draggedWidget.value?.position_y

  endDrag(event)

  // Check if position actually changed
  if (hadWidget && draggedWidget.value === null) {
    // Widget was moved, mark as unsaved
    markUnsaved()
  }
}

// Handle drop on empty cell
function handleEmptyCellDrop(x, y, event) {
  event.preventDefault()
  if (draggedWidget.value) {
    handleWidgetDrop(draggedWidget.value, x, y)
  }
  endDrag(event)
  markUnsaved()
}

// Handle widget resize
function handleResize(widget, direction, event) {
  startResize(widget, direction, event)
  // Mark unsaved when resize ends
  const onResizeEnd = () => {
    markUnsaved()
    document.removeEventListener('mouseup', onResizeEnd)
  }
  document.addEventListener('mouseup', onResizeEnd)
}

async function addWidget(widgetType) {
  const widgetInfo = availableWidgets.value[widgetType]
  if (!widgetInfo) return

  const defaultWidth = widgetInfo.default_width || 1
  const defaultHeight = widgetInfo.default_height || 1
  const position = findNextAvailablePosition(defaultWidth, defaultHeight)

  const newWidget = {
    widget_type: widgetType,
    title: widgetInfo.title,
    position_x: position.x,
    position_y: position.y,
    width: defaultWidth,
    height: defaultHeight,
  }

  widgets.value.push(newWidget)
  markUnsaved()
  await fetchWidgetData(widgetType)
  showAddWidget.value = false
}

async function removeWidget(index) {
  widgets.value.splice(index, 1)
  markUnsaved()
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

    // Load weather data for weather widgets
    if (widget.widget_type === 'weather' && widget.config) {
      await fetchWeatherData(widget.id, widget.config)
    }
  }

  // Initialize countdowns
  updateCountdowns()
  countdownInterval = setInterval(updateCountdowns, 1000)

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

// Weather widget functions
async function fetchWeatherData(widgetId, config) {
  if (!config?.location && !config?.latitude) return

  weatherLoading.value[widgetId] = true
  try {
    const params = config.latitude
      ? { lat: config.latitude, lon: config.longitude }
      : { location: config.location }

    const response = await api.get('/api/v1/weather', { params })
    weatherData.value[widgetId] = response.data.data
  } catch (error) {
    console.error('Failed to fetch weather:', error)
  } finally {
    weatherLoading.value[widgetId] = false
  }
}

async function searchWeatherLocation() {
  if (weatherSearch.value.length < 2) return

  isSearchingWeather.value = true
  try {
    const response = await api.get('/api/v1/weather/search', {
      params: { q: weatherSearch.value }
    })
    weatherSearchResults.value = response.data.data
  } catch (error) {
    console.error('Failed to search locations:', error)
  } finally {
    isSearchingWeather.value = false
  }
}

async function selectWeatherLocation(location) {
  if (!weatherConfigWidget.value) return

  const widgetIndex = widgets.value.findIndex(w => w.id === weatherConfigWidget.value.id)
  if (widgetIndex < 0) return

  widgets.value[widgetIndex].config = {
    location: `${location.name}, ${location.country}`,
    latitude: location.latitude,
    longitude: location.longitude,
  }

  markUnsaved()
  await fetchWeatherData(widgets.value[widgetIndex].id, widgets.value[widgetIndex].config)

  showWeatherConfig.value = false
  weatherConfigWidget.value = null
  weatherSearch.value = ''
  weatherSearchResults.value = []
}

function openWeatherConfig(widget) {
  weatherConfigWidget.value = widget
  showWeatherConfig.value = true
}

// Countdown widget functions
function calculateCountdown(targetDate) {
  const now = new Date()
  const target = new Date(targetDate)
  const diff = target - now

  if (diff <= 0) {
    return { days: 0, hours: 0, minutes: 0, seconds: 0, expired: true }
  }

  const days = Math.floor(diff / (1000 * 60 * 60 * 24))
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
  const seconds = Math.floor((diff % (1000 * 60)) / 1000)

  return { days, hours, minutes, seconds, expired: false }
}

function updateCountdowns() {
  widgets.value.forEach(widget => {
    if (widget.widget_type === 'countdown' && widget.config?.date) {
      countdowns.value[widget.id] = calculateCountdown(widget.config.date)
    }
  })
}

function openCountdownConfig(widget) {
  countdownConfigWidget.value = widget
  countdownForm.value = {
    title: widget.config?.title || '',
    date: widget.config?.date || '',
  }
  showCountdownConfig.value = true
}

async function saveCountdownConfig() {
  if (!countdownConfigWidget.value) return

  const widgetIndex = widgets.value.findIndex(w => w.id === countdownConfigWidget.value.id)
  if (widgetIndex < 0) return

  widgets.value[widgetIndex].config = {
    title: countdownForm.value.title,
    date: countdownForm.value.date,
  }

  markUnsaved()
  updateCountdowns()

  showCountdownConfig.value = false
  countdownConfigWidget.value = null
}

// Weather icon helper
function getWeatherIcon(icon) {
  const icons = {
    sunny: SunIcon,
    partly_cloudy: CloudIcon,
    cloudy: CloudIcon,
    rain: CloudIcon,
    snow: CloudIcon,
    thunderstorm: CloudIcon,
    fog: CloudIcon,
    drizzle: CloudIcon,
    showers: CloudIcon,
  }
  return icons[icon] || CloudIcon
}

// Update countdowns every second
let countdownInterval = null

onUnmounted(() => {
  if (countdownInterval) {
    clearInterval(countdownInterval)
  }
})

// Discard changes and exit edit mode
function discardChanges() {
  hasUnsavedChanges.value = false
  isEditMode.value = false
  loadAllWidgetData()
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">
          Willkommen zurück, {{ authStore.user?.username || 'User' }}
        </h1>
        <p class="page-subtitle">Dein persönliches Dashboard</p>
      </div>
      <div class="flex items-center gap-2">
        <template v-if="isEditMode">
          <button @click="resetDashboard" class="btn-secondary text-sm">
            Zurücksetzen
          </button>
          <button @click="showAddWidget = true" class="btn-secondary text-sm">
            <PlusIcon class="w-4 h-4 mr-1.5" />
            Widget hinzufügen
          </button>
          <button
            v-if="hasUnsavedChanges"
            @click="discardChanges"
            class="btn-ghost text-sm text-red-400 hover:text-red-300"
          >
            Abbrechen
          </button>
          <button @click="saveLayout(true)" class="btn-primary text-sm">
            <CheckIcon class="w-4 h-4 mr-1.5" />
            Speichern
          </button>
        </template>
        <button v-else @click="isEditMode = true" class="btn-secondary text-sm">
          <Cog6ToothIcon class="w-4 h-4 mr-1.5" />
          Anpassen
        </button>
      </div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
      <div v-for="i in 4" :key="i" class="card p-5 animate-pulse">
        <div class="h-3 bg-dark-600 rounded-full w-1/3 mb-4"></div>
        <div class="h-7 bg-dark-600 rounded-lg w-1/2"></div>
      </div>
    </div>

    <!-- Edit Mode Info Bar -->
    <div v-if="isEditMode" class="bg-primary-500/8 border border-primary-500/20 rounded-xl px-4 py-3 flex items-center gap-4">
      <div class="flex-1 text-sm text-primary-300/90">
        <span class="font-medium">Bearbeitungsmodus</span> — Widgets per Drag &amp; Drop verschieben, Ecken zum Anpassen der Größe ziehen
      </div>
      <div v-if="hasUnsavedChanges" class="text-xs text-yellow-400 flex items-center gap-1.5 shrink-0">
        <ExclamationTriangleIcon class="w-3.5 h-3.5" />
        Ungespeicherte Änderungen
      </div>
    </div>

    <!-- Widgets Grid -->
    <div
      v-if="!isLoading"
      ref="gridElement"
      class="dashboard-grid relative"
      :style="{
        display: 'grid',
        gridTemplateColumns: 'repeat(4, 1fr)',
        gridAutoRows: '20px',
        gap: '1.5rem',
      }"
      @dragover="handleGridDragOver"
    >
      <!-- Ghost Preview (drop/resize indicator) -->
      <div
        v-if="ghostPosition && (draggedWidget || resizingWidget)"
        class="pointer-events-none rounded-xl border-4 transition-all duration-75 z-50"
        :class="resizingWidget ? 'border-green-500 bg-green-500/20' : 'border-primary-500 bg-primary-500/20'"
        :style="getGhostStyle()"
      ></div>

      <!-- Empty cells for drop zones in edit mode -->
      <div
        v-for="cell in emptyCells"
        :key="`empty-${cell.x}-${cell.y}`"
        class="rounded-xl border-2 border-dashed transition-all duration-200"
        :class="isEditMode ? 'border-dark-600 hover:border-dark-500 hover:bg-dark-800/50' : 'border-transparent'"
        :style="{
          gridColumn: `${cell.x + 1} / span 1`,
          gridRow: `${cell.y + 1} / span 1`,
        }"
        @dragover.prevent
        @drop="handleEmptyCellDrop(cell.x, cell.y, $event)"
      ></div>

      <!-- Widgets -->
      <template v-for="(widget, index) in widgets" :key="widget.id || index">
        <!-- Quick Stats Widget -->
        <WidgetWrapper
          v-if="widget.widget_type === 'quick_stats'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
          <h3 class="text-base font-semibold text-white mb-4">{{ widget.title }}</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="bg-dark-700/40 border border-dark-600/40 rounded-xl p-4 hover:border-dark-500/60 transition-colors">
              <p class="text-gray-500 text-xs mb-1.5">Listen</p>
              <p class="text-2xl font-bold text-white tabular-nums">{{ widgetData.quick_stats?.lists || 0 }}</p>
            </div>
            <div class="bg-yellow-500/5 border border-yellow-500/15 rounded-xl p-4 hover:border-yellow-500/25 transition-colors">
              <p class="text-gray-500 text-xs mb-1.5">Offene Aufgaben</p>
              <p class="text-2xl font-bold text-yellow-300 tabular-nums">{{ widgetData.quick_stats?.open_tasks || 0 }}</p>
            </div>
            <div class="bg-dark-700/40 border border-dark-600/40 rounded-xl p-4 hover:border-dark-500/60 transition-colors">
              <p class="text-gray-500 text-xs mb-1.5">Dokumente</p>
              <p class="text-2xl font-bold text-white tabular-nums">{{ widgetData.quick_stats?.documents || 0 }}</p>
            </div>
            <div class="bg-dark-700/40 border border-dark-600/40 rounded-xl p-4 hover:border-dark-500/60 transition-colors">
              <p class="text-gray-500 text-xs mb-1.5">Kanban Karten</p>
              <p class="text-2xl font-bold text-white tabular-nums">{{ widgetData.quick_stats?.kanban_cards || 0 }}</p>
            </div>
          </div>
        </WidgetWrapper>

        <!-- Recent Tasks Widget -->
        <WidgetWrapper
          v-else-if="widget.widget_type === 'recent_tasks'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
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
        </WidgetWrapper>

        <!-- Recent Documents Widget -->
        <WidgetWrapper
          v-else-if="widget.widget_type === 'recent_documents'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
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
        </WidgetWrapper>

        <!-- Productivity Chart Widget -->
        <WidgetWrapper
          v-else-if="widget.widget_type === 'productivity_chart'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
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
        </WidgetWrapper>

        <!-- Calendar Preview Widget -->
        <WidgetWrapper
          v-else-if="widget.widget_type === 'calendar_preview'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
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
        </WidgetWrapper>

        <!-- Uptime Status Widget -->
        <WidgetWrapper
          v-else-if="widget.widget_type === 'uptime_status'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
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
        </WidgetWrapper>

        <!-- Time Tracking Today Widget -->
        <WidgetWrapper
          v-else-if="widget.widget_type === 'time_tracking_today'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
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
        </WidgetWrapper>

        <!-- Kanban Summary Widget -->
        <WidgetWrapper
          v-else-if="widget.widget_type === 'kanban_summary'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
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
        </WidgetWrapper>

        <!-- Recent Activity Widget -->
        <WidgetWrapper
          v-else-if="widget.widget_type === 'recent_activity'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
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
        </WidgetWrapper>

        <!-- Quick Notes Widget -->
        <WidgetWrapper
          v-else-if="widget.widget_type === 'quick_notes'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
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
        </WidgetWrapper>

        <!-- Weather Widget -->
        <WidgetWrapper
          v-else-if="widget.widget_type === 'weather'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
            <button @click="openWeatherConfig(widget)" class="text-gray-400 hover:text-white">
              <Cog6ToothIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Loading -->
          <div v-if="weatherLoading[widget.id]" class="flex justify-center py-8">
            <svg class="animate-spin h-8 w-8 text-primary-500" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
          </div>

          <!-- No location configured -->
          <div v-else-if="!widget.config?.location" class="text-center py-6">
            <MapPinIcon class="w-10 h-10 text-gray-600 mx-auto mb-2" />
            <p class="text-gray-500 text-sm mb-3">Ort nicht konfiguriert</p>
            <button @click="openWeatherConfig(widget)" class="btn-secondary text-xs">
              Ort festlegen
            </button>
          </div>

          <!-- Weather data -->
          <div v-else-if="weatherData[widget.id]">
            <div class="flex items-center gap-2 text-xs text-gray-500 mb-3">
              <MapPinIcon class="w-3 h-3" />
              {{ widget.config.location }}
            </div>

            <!-- Current weather -->
            <div class="flex items-center justify-between mb-4">
              <div>
                <p class="text-4xl font-bold text-white">{{ weatherData[widget.id].current?.temperature }}°</p>
                <p class="text-sm text-gray-400">{{ weatherData[widget.id].current?.description }}</p>
              </div>
              <component :is="getWeatherIcon(weatherData[widget.id].current?.icon)" class="w-16 h-16 text-yellow-400" />
            </div>

            <!-- Details -->
            <div class="grid grid-cols-2 gap-2 text-sm mb-4">
              <div class="bg-dark-700/50 rounded p-2">
                <p class="text-gray-500 text-xs">Gefühlt</p>
                <p class="text-white">{{ weatherData[widget.id].current?.feels_like }}°</p>
              </div>
              <div class="bg-dark-700/50 rounded p-2">
                <p class="text-gray-500 text-xs">Wind</p>
                <p class="text-white">{{ weatherData[widget.id].current?.wind_speed }} km/h</p>
              </div>
            </div>

            <!-- Forecast -->
            <div class="flex gap-2 overflow-x-auto">
              <div
                v-for="day in (weatherData[widget.id].forecast || []).slice(1, 5)"
                :key="day.date"
                class="flex-shrink-0 text-center p-2 bg-dark-700/50 rounded"
              >
                <p class="text-xs text-gray-500">{{ day.day?.substring(0, 2) }}</p>
                <p class="text-sm font-medium text-white">{{ day.temp_max }}°</p>
                <p class="text-xs text-gray-500">{{ day.temp_min }}°</p>
              </div>
            </div>
          </div>
        </WidgetWrapper>

        <!-- Countdown Widget -->
        <WidgetWrapper
          v-else-if="widget.widget_type === 'countdown'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">{{ widget.config?.title || widget.title }}</h3>
            <button @click="openCountdownConfig(widget)" class="text-gray-400 hover:text-white">
              <Cog6ToothIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- No date configured -->
          <div v-if="!widget.config?.date" class="text-center py-6">
            <ClockIcon class="w-10 h-10 text-gray-600 mx-auto mb-2" />
            <p class="text-gray-500 text-sm mb-3">Kein Datum festgelegt</p>
            <button @click="openCountdownConfig(widget)" class="btn-secondary text-xs">
              Countdown einrichten
            </button>
          </div>

          <!-- Countdown display -->
          <div v-else-if="countdowns[widget.id]" class="text-center">
            <div v-if="countdowns[widget.id].expired" class="py-4">
              <p class="text-2xl font-bold text-green-400">Erreicht!</p>
            </div>
            <div v-else class="grid grid-cols-4 gap-2">
              <div class="bg-dark-700/50 rounded-lg p-2">
                <p class="text-2xl font-bold text-white">{{ countdowns[widget.id].days }}</p>
                <p class="text-xs text-gray-500">Tage</p>
              </div>
              <div class="bg-dark-700/50 rounded-lg p-2">
                <p class="text-2xl font-bold text-white">{{ countdowns[widget.id].hours }}</p>
                <p class="text-xs text-gray-500">Std</p>
              </div>
              <div class="bg-dark-700/50 rounded-lg p-2">
                <p class="text-2xl font-bold text-white">{{ countdowns[widget.id].minutes }}</p>
                <p class="text-xs text-gray-500">Min</p>
              </div>
              <div class="bg-dark-700/50 rounded-lg p-2">
                <p class="text-2xl font-bold text-white">{{ countdowns[widget.id].seconds }}</p>
                <p class="text-xs text-gray-500">Sek</p>
              </div>
            </div>
            <p class="text-xs text-gray-500 mt-3">{{ new Date(widget.config.date).toLocaleDateString('de-DE') }}</p>
          </div>
        </WidgetWrapper>

        <!-- Link Stats Widget -->
        <WidgetWrapper
          v-else-if="widget.widget_type === 'link_stats'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
            <router-link to="/links" class="text-sm text-primary-400 hover:text-primary-300">Alle</router-link>
          </div>
          <div class="text-center">
            <LinkIcon class="w-8 h-8 text-primary-400 mx-auto mb-2" />
            <p class="text-2xl font-bold text-white">{{ widgetData.link_stats?.total_links || 0 }}</p>
            <p class="text-gray-500 text-sm">Kurzlinks</p>
            <p class="text-xl font-bold text-green-400 mt-2">{{ widgetData.link_stats?.total_clicks || 0 }}</p>
            <p class="text-gray-500 text-sm">Klicks gesamt</p>
          </div>
        </WidgetWrapper>

        <!-- Storage Usage Widget -->
        <WidgetWrapper
          v-else-if="widget.widget_type === 'storage_usage'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
            <router-link to="/storage" class="text-sm text-primary-400 hover:text-primary-300">Öffnen</router-link>
          </div>
          <div class="text-center">
            <!-- Progress Ring -->
            <div class="relative inline-flex items-center justify-center w-24 h-24 mb-3">
              <svg class="w-24 h-24 transform -rotate-90">
                <circle
                  cx="48"
                  cy="48"
                  r="40"
                  stroke="currentColor"
                  stroke-width="8"
                  fill="none"
                  class="text-dark-600"
                />
                <circle
                  cx="48"
                  cy="48"
                  r="40"
                  stroke="currentColor"
                  stroke-width="8"
                  fill="none"
                  :stroke-dasharray="251.2"
                  :stroke-dashoffset="251.2 - (251.2 * (widgetData.storage_usage?.usage_percent || 0) / 100)"
                  class="text-primary-500 transition-all duration-500"
                  stroke-linecap="round"
                />
              </svg>
              <span class="absolute text-xl font-bold text-white">{{ widgetData.storage_usage?.usage_percent || 0 }}%</span>
            </div>
            <p class="text-white font-medium">{{ widgetData.storage_usage?.used_formatted || '0 B' }}</p>
            <p class="text-gray-500 text-sm">von {{ widgetData.storage_usage?.limit_formatted || '10 GB' }}</p>
            <p class="text-gray-400 text-xs mt-2">{{ widgetData.storage_usage?.file_count || 0 }} Dateien</p>
          </div>
        </WidgetWrapper>

        <!-- Backup Status Widget -->
        <WidgetWrapper
          v-else-if="widget.widget_type === 'backup_status'"
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">{{ widget.title }}</h3>
            <router-link to="/backups" class="text-sm text-primary-400 hover:text-primary-300">Alle</router-link>
          </div>
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-gray-400 text-sm">Erfolgreiche Backups</span>
              <span class="text-green-400 font-medium">{{ widgetData.backup_status?.successful_backups || 0 }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-400 text-sm">Fehlgeschlagen</span>
              <span class="text-red-400 font-medium">{{ widgetData.backup_status?.failed_backups || 0 }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-gray-400 text-sm">Aktive Zeitpläne</span>
              <span class="text-white font-medium">{{ widgetData.backup_status?.active_schedules || 0 }}</span>
            </div>
            <div v-if="widgetData.backup_status?.last_successful" class="pt-2 border-t border-dark-700">
              <p class="text-xs text-gray-500">Letztes erfolgreiches Backup:</p>
              <p class="text-sm text-white">{{ formatDate(widgetData.backup_status.last_successful) }}</p>
            </div>
          </div>
        </WidgetWrapper>

        <!-- Generic fallback widget -->
        <WidgetWrapper
          v-else
          :widget="widget"
          :index="index"
          :is-edit-mode="isEditMode"
          :is-dragging="draggedWidget?.id === widget.id"
          :style="getWidgetStyle(widget)"
          @remove="removeWidget"
          @dragstart="startDrag(widget, $event)"
          @dragend="handleDragEnd"
          @resize="(dir, e) => handleResize(widget, dir, e)"
        >
          <h3 class="text-lg font-semibold text-white mb-4">{{ widget.title }}</h3>
          <p class="text-gray-500 text-sm">Widget: {{ widget.widget_type }}</p>
        </WidgetWrapper>
      </template>
    </div>

    <!-- Empty state -->
    <div
      v-if="!isLoading && widgets.length === 0"
      class="card p-16 text-center"
    >
      <div class="w-16 h-16 rounded-2xl bg-dark-700/50 border border-dark-600/50 flex items-center justify-center mx-auto mb-5">
        <ChartBarIcon class="w-8 h-8 text-gray-600" />
      </div>
      <h3 class="text-base font-semibold text-white mb-2">Dashboard noch leer</h3>
      <p class="text-sm text-gray-500 mb-6 max-w-xs mx-auto">Füge Widgets hinzu, um dein Dashboard zu personalisieren und wichtige Infos auf einen Blick zu sehen.</p>
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

    <!-- Weather Config Modal -->
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
          v-if="showWeatherConfig"
          class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        >
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">Ort festlegen</h3>
              <button @click="showWeatherConfig = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <div class="p-6">
              <div class="relative">
                <input
                  v-model="weatherSearch"
                  @input="searchWeatherLocation"
                  type="text"
                  class="input pr-10"
                  placeholder="Stadt suchen..."
                />
                <MapPinIcon class="w-5 h-5 text-gray-500 absolute right-3 top-1/2 -translate-y-1/2" />
              </div>

              <!-- Loading -->
              <div v-if="isSearchingWeather" class="flex justify-center py-4">
                <svg class="animate-spin h-6 w-6 text-primary-500" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
              </div>

              <!-- Results -->
              <div v-else-if="weatherSearchResults.length > 0" class="mt-4 space-y-2">
                <button
                  v-for="loc in weatherSearchResults"
                  :key="`${loc.latitude}-${loc.longitude}`"
                  @click="selectWeatherLocation(loc)"
                  class="w-full text-left p-3 rounded-lg bg-dark-700/50 hover:bg-dark-700 transition-colors"
                >
                  <p class="text-white">{{ loc.name }}</p>
                  <p class="text-sm text-gray-500">{{ loc.admin1 ? `${loc.admin1}, ` : '' }}{{ loc.country }}</p>
                </button>
              </div>

              <p v-else-if="weatherSearch.length >= 2" class="text-gray-500 text-sm text-center py-4">
                Keine Ergebnisse gefunden
              </p>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Countdown Config Modal -->
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
          v-if="showCountdownConfig"
          class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        >
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">Countdown einrichten</h3>
              <button @click="showCountdownConfig = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <div class="p-6 space-y-4">
              <div>
                <label class="label">Titel</label>
                <input
                  v-model="countdownForm.title"
                  type="text"
                  class="input"
                  placeholder="z.B. Urlaub, Geburtstag..."
                />
              </div>
              <div>
                <label class="label">Datum & Uhrzeit</label>
                <input
                  v-model="countdownForm.date"
                  type="datetime-local"
                  class="input"
                />
              </div>
              <button @click="saveCountdownConfig" class="btn-primary w-full">
                Speichern
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
