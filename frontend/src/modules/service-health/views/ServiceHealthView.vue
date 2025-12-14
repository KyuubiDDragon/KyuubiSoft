<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '@/core/api/axios'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  XCircleIcon,
  ExclamationTriangleIcon,
  ClockIcon,
  ServerIcon,
  ShieldCheckIcon,
  CubeIcon,
  SignalIcon,
  ChartBarIcon,
  BellAlertIcon,
} from '@heroicons/vue/24/outline'

// State
const services = ref([])
const grouped = ref({})
const stats = ref(null)
const summary = ref(null)
const incidents = ref([])
const timeline = ref(null)
const isLoading = ref(true)
const activeTab = ref('overview')
let refreshInterval = null

// Methods
const loadData = async () => {
  try {
    isLoading.value = true
    const [healthRes, summaryRes, incidentsRes] = await Promise.all([
      api.get('/api/v1/service-health'),
      api.get('/api/v1/service-health/summary'),
      api.get('/api/v1/service-health/incidents'),
    ])
    services.value = healthRes.data.data.services
    grouped.value = healthRes.data.data.grouped
    stats.value = healthRes.data.data.stats
    summary.value = summaryRes.data.data
    incidents.value = incidentsRes.data.data.incidents
  } catch (error) {
    console.error('Failed to load service health:', error)
  } finally {
    isLoading.value = false
  }
}

const loadTimeline = async () => {
  try {
    const response = await api.get('/api/v1/service-health/timeline', { params: { days: 7 } })
    timeline.value = response.data.data
  } catch (error) {
    console.error('Failed to load timeline:', error)
  }
}

const getStatusIcon = (status) => {
  const icons = {
    up: CheckCircleIcon,
    down: XCircleIcon,
    warning: ExclamationTriangleIcon,
    pending: ClockIcon,
  }
  return icons[status] || ClockIcon
}

const getStatusColor = (status) => {
  const colors = {
    up: 'text-green-600',
    down: 'text-red-600',
    warning: 'text-amber-600',
    pending: 'text-gray-500',
  }
  return colors[status] || 'text-gray-500'
}

const getStatusBgColor = (status) => {
  const colors = {
    up: 'bg-green-100 dark:bg-green-900/30',
    down: 'bg-red-100 dark:bg-red-900/30',
    warning: 'bg-amber-100 dark:bg-amber-900/30',
    pending: 'bg-gray-100 dark:bg-gray-700',
  }
  return colors[status] || 'bg-gray-100'
}

const getSourceIcon = (source) => {
  const icons = {
    uptime_monitor: SignalIcon,
    ssl_certificate: ShieldCheckIcon,
    docker: CubeIcon,
  }
  return icons[source] || ServerIcon
}

const getSourceLabel = (source) => {
  const labels = {
    uptime_monitor: 'Uptime',
    ssl_certificate: 'SSL',
    docker: 'Docker',
  }
  return labels[source] || source
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleString('de-DE')
}

const formatDuration = (seconds) => {
  if (!seconds) return '-'
  if (seconds < 60) return `${seconds}s`
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`
  return `${Math.floor(seconds / 86400)}d ${Math.floor((seconds % 86400) / 3600)}h`
}

const getOverallStatusClass = (status) => {
  const classes = {
    healthy: 'bg-green-500',
    warning: 'bg-amber-500',
    critical: 'bg-red-500',
  }
  return classes[status] || 'bg-gray-500'
}

onMounted(() => {
  loadData()
  loadTimeline()
  refreshInterval = setInterval(loadData, 30000)
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Service Health</h1>
        <p class="text-gray-500 dark:text-gray-400">Übersicht aller Dienste und deren Status</p>
      </div>
      <button @click="loadData" class="btn-secondary">
        <ArrowPathIcon class="w-5 h-5 mr-2" />
        Aktualisieren
      </button>
    </div>

    <!-- Overall Status Banner -->
    <div v-if="summary" class="rounded-lg p-4 flex items-center justify-between"
         :class="getOverallStatusClass(summary.overall_status)">
      <div class="flex items-center gap-3 text-white">
        <component :is="summary.overall_status === 'healthy' ? CheckCircleIcon : summary.overall_status === 'warning' ? ExclamationTriangleIcon : XCircleIcon"
                   class="w-8 h-8" />
        <div>
          <div class="font-bold text-lg">
            {{ summary.overall_status === 'healthy' ? 'Alle Systeme operational' :
               summary.overall_status === 'warning' ? 'Einige Warnungen' :
               'Kritische Probleme erkannt' }}
          </div>
          <div class="text-sm opacity-90">
            {{ stats?.up }} von {{ stats?.total }} Diensten online
          </div>
        </div>
      </div>
      <div class="text-white text-right">
        <div class="text-3xl font-bold">{{ stats?.overall_health?.toFixed(1) }}%</div>
        <div class="text-sm opacity-90">Gesamtstatus</div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div v-if="stats" class="grid grid-cols-5 gap-4">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
        <div class="text-gray-500 dark:text-gray-400 text-sm">Gesamt</div>
        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</div>
      </div>
      <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 shadow">
        <div class="text-green-600 dark:text-green-400 text-sm">Online</div>
        <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ stats.up }}</div>
      </div>
      <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 shadow">
        <div class="text-red-600 dark:text-red-400 text-sm">Offline</div>
        <div class="text-2xl font-bold text-red-700 dark:text-red-300">{{ stats.down }}</div>
      </div>
      <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4 shadow">
        <div class="text-amber-600 dark:text-amber-400 text-sm">Warnungen</div>
        <div class="text-2xl font-bold text-amber-700 dark:text-amber-300">{{ stats.warning }}</div>
      </div>
      <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 shadow">
        <div class="text-gray-500 dark:text-gray-400 text-sm">Ausstehend</div>
        <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ stats.pending }}</div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-700">
      <nav class="flex gap-4">
        <button @click="activeTab = 'overview'"
                class="py-2 px-4 border-b-2 font-medium text-sm"
                :class="activeTab === 'overview' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
          Übersicht
        </button>
        <button @click="activeTab = 'incidents'"
                class="py-2 px-4 border-b-2 font-medium text-sm"
                :class="activeTab === 'incidents' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
          Vorfälle
        </button>
      </nav>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
    </div>

    <!-- Overview Tab -->
    <div v-else-if="activeTab === 'overview'" class="space-y-6">
      <!-- Critical Services -->
      <div v-if="grouped.critical?.length" class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
        <h3 class="font-semibold text-red-700 dark:text-red-400 mb-3 flex items-center gap-2">
          <XCircleIcon class="w-5 h-5" />
          Kritisch ({{ grouped.critical.length }})
        </h3>
        <div class="space-y-2">
          <div v-for="service in grouped.critical" :key="service.id"
               class="bg-white dark:bg-gray-800 rounded-lg p-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <component :is="getSourceIcon(service.source)" class="w-5 h-5 text-red-600" />
              <div>
                <div class="font-medium text-gray-900 dark:text-white">{{ service.name }}</div>
                <div class="text-sm text-gray-500">{{ service.url }}</div>
              </div>
            </div>
            <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700">
              {{ getSourceLabel(service.source) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Warning Services -->
      <div v-if="grouped.warning?.length" class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4">
        <h3 class="font-semibold text-amber-700 dark:text-amber-400 mb-3 flex items-center gap-2">
          <ExclamationTriangleIcon class="w-5 h-5" />
          Warnungen ({{ grouped.warning.length }})
        </h3>
        <div class="space-y-2">
          <div v-for="service in grouped.warning" :key="service.id"
               class="bg-white dark:bg-gray-800 rounded-lg p-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <component :is="getSourceIcon(service.source)" class="w-5 h-5 text-amber-600" />
              <div>
                <div class="font-medium text-gray-900 dark:text-white">{{ service.name }}</div>
                <div class="text-sm text-gray-500">{{ service.url }}</div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span v-if="service.days_until_expiry !== undefined" class="text-sm text-amber-600">
                {{ service.days_until_expiry }} Tage
              </span>
              <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700">
                {{ getSourceLabel(service.source) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Healthy Services -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
          <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
            <CheckCircleIcon class="w-5 h-5 text-green-600" />
            Online ({{ grouped.healthy?.length || 0 }})
          </h3>
        </div>
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
          <div v-for="service in grouped.healthy" :key="service.id"
               class="p-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                   :class="getStatusBgColor(service.status)">
                <component :is="getSourceIcon(service.source)" class="w-4 h-4" :class="getStatusColor(service.status)" />
              </div>
              <div>
                <div class="font-medium text-gray-900 dark:text-white">{{ service.name }}</div>
                <div class="text-sm text-gray-500">{{ service.url }}</div>
              </div>
            </div>
            <div class="flex items-center gap-4">
              <span v-if="service.uptime_percentage" class="text-sm text-gray-500">
                {{ service.uptime_percentage }}% Uptime
              </span>
              <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                {{ getSourceLabel(service.source) }}
              </span>
            </div>
          </div>
          <div v-if="!grouped.healthy?.length" class="p-8 text-center text-gray-500">
            Keine Dienste online
          </div>
        </div>
      </div>
    </div>

    <!-- Incidents Tab -->
    <div v-else-if="activeTab === 'incidents'" class="bg-white dark:bg-gray-800 rounded-lg shadow">
      <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
        <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
          <BellAlertIcon class="w-5 h-5 text-gray-400" />
          Letzte Vorfälle
        </h3>
      </div>
      <div class="divide-y divide-gray-200 dark:divide-gray-700">
        <div v-for="incident in incidents" :key="incident.id"
             class="p-4 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                 :class="incident.is_resolved ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30'">
              <component :is="incident.is_resolved ? CheckCircleIcon : XCircleIcon"
                         :class="incident.is_resolved ? 'text-green-600' : 'text-red-600'"
                         class="w-4 h-4" />
            </div>
            <div>
              <div class="font-medium text-gray-900 dark:text-white">{{ incident.service_name }}</div>
              <div class="text-sm text-gray-500">{{ incident.cause || 'Keine Details' }}</div>
            </div>
          </div>
          <div class="text-right text-sm">
            <div class="text-gray-900 dark:text-white">{{ formatDate(incident.started_at) }}</div>
            <div v-if="incident.duration_seconds" class="text-gray-500">
              Dauer: {{ formatDuration(incident.duration_seconds) }}
            </div>
            <span class="text-xs px-2 py-0.5 rounded mt-1 inline-block"
                  :class="incident.source === 'uptime' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'">
              {{ incident.source === 'uptime' ? 'Uptime' : 'SSL' }}
            </span>
          </div>
        </div>
        <div v-if="!incidents.length" class="p-8 text-center text-gray-500">
          Keine Vorfälle
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.btn-secondary {
  @apply inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors;
}
</style>
