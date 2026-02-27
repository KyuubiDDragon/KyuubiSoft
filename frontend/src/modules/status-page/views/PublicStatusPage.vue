<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '@/core/api/axios'
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XCircleIcon,
  ChevronDownIcon,
  ChevronRightIcon,
} from '@heroicons/vue/24/outline'

const data = ref(null)
const loading = ref(true)
const error = ref(false)
const showResolved = ref(false)
let refreshInterval = null

onMounted(async () => {
  await loadData()
  refreshInterval = setInterval(loadData, 60000)
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})

async function loadData() {
  try {
    const response = await api.get('/api/v1/status-page/public')
    data.value = response.data.data
    error.value = false
  } catch (e) {
    error.value = true
  } finally {
    loading.value = false
  }
}

const overallStatus = computed(() => {
  if (!data.value || !data.value.monitors || data.value.monitors.length === 0) return 'unknown'
  const statuses = data.value.monitors.map(m => m.current_status)
  if (statuses.every(s => s === 'up')) return 'operational'
  if (statuses.some(s => s === 'down')) {
    const downCount = statuses.filter(s => s === 'down').length
    return downCount > statuses.length / 2 ? 'major_outage' : 'partial_outage'
  }
  return 'degraded'
})

const overallLabel = computed(() => {
  switch (overallStatus.value) {
    case 'operational': return 'Alle Systeme betriebsbereit'
    case 'degraded': return 'Einige Systeme beeintraechtigt'
    case 'partial_outage': return 'Einige Systeme beeintraechtigt'
    case 'major_outage': return 'Schwerwiegende Stoerung'
    default: return 'Status unbekannt'
  }
})

const overallColor = computed(() => {
  switch (overallStatus.value) {
    case 'operational': return 'from-green-500/20 to-green-600/5 border-green-500/30'
    case 'degraded': return 'from-yellow-500/20 to-yellow-600/5 border-yellow-500/30'
    case 'partial_outage': return 'from-orange-500/20 to-orange-600/5 border-orange-500/30'
    case 'major_outage': return 'from-red-500/20 to-red-600/5 border-red-500/30'
    default: return 'from-gray-500/20 to-gray-600/5 border-gray-500/30'
  }
})

const overallIcon = computed(() => {
  switch (overallStatus.value) {
    case 'operational': return CheckCircleIcon
    case 'major_outage': return XCircleIcon
    default: return ExclamationTriangleIcon
  }
})

const overallIconColor = computed(() => {
  switch (overallStatus.value) {
    case 'operational': return 'text-green-400'
    case 'degraded': return 'text-yellow-400'
    case 'partial_outage': return 'text-orange-400'
    case 'major_outage': return 'text-red-400'
    default: return 'text-gray-400'
  }
})

const groupedMonitors = computed(() => {
  if (!data.value || !data.value.monitors) return []
  const groups = {}
  data.value.monitors.forEach(m => {
    const group = m.group_name || '__ungrouped__'
    if (!groups[group]) groups[group] = []
    groups[group].push(m)
  })
  return Object.entries(groups).map(([name, monitors]) => ({
    name: name === '__ungrouped__' ? null : name,
    monitors,
  }))
})

function getStatusDotClass(status) {
  switch (status) {
    case 'up': return 'bg-green-500'
    case 'down': return 'bg-red-500'
    default: return 'bg-gray-500'
  }
}

function getBarClass(status) {
  switch (status) {
    case 'up': return 'bg-green-500'
    case 'down': return 'bg-red-500'
    case 'degraded': return 'bg-yellow-500'
    default: return 'bg-gray-700'
  }
}

function getBarTooltip(day) {
  if (!day) return 'Keine Daten'
  if (day.uptime !== null) return `${day.date}: ${day.uptime}%`
  return `${day.date}: Keine Daten`
}

function padHistory(history) {
  // Pad to 90 days
  const result = []
  const today = new Date()
  for (let i = 89; i >= 0; i--) {
    const date = new Date(today)
    date.setDate(date.getDate() - i)
    const dateStr = date.toISOString().split('T')[0]
    const found = history?.find(d => d.date === dateStr)
    result.push(found || { date: dateStr, status: 'no_data', uptime: null })
  }
  return result
}

function calcOverallUptime(history) {
  if (!history || history.length === 0) return null
  const withData = history.filter(d => d.uptime !== null)
  if (withData.length === 0) return null
  const total = withData.reduce((sum, d) => sum + d.uptime, 0)
  return (total / withData.length).toFixed(2)
}

function getStatusBadge(status) {
  const map = {
    investigating: { label: 'Untersuchung', color: 'bg-yellow-500' },
    identified: { label: 'Identifiziert', color: 'bg-orange-500' },
    monitoring: { label: 'Beobachtung', color: 'bg-blue-500' },
    resolved: { label: 'Behoben', color: 'bg-green-500' },
  }
  return map[status] || { label: status, color: 'bg-gray-500' }
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleString('de-DE')
}
</script>

<template>
  <div class="min-h-screen bg-gray-950">
    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center min-h-screen">
      <div class="w-10 h-10 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <XCircleIcon class="w-16 h-16 mx-auto text-red-500 mb-4" />
        <h1 class="text-2xl font-bold text-white mb-2">Statusseite nicht verfuegbar</h1>
        <p class="text-gray-400">Bitte versuche es spaeter erneut.</p>
      </div>
    </div>

    <!-- Content -->
    <div v-else-if="data" class="max-w-4xl mx-auto px-4 py-8 sm:py-12">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2">{{ data.title }}</h1>
        <p v-if="data.description" class="text-gray-400 text-lg">{{ data.description }}</p>
      </div>

      <!-- Overall Status Banner -->
      <div
        class="bg-gradient-to-r border rounded-xl p-6 mb-8 flex items-center gap-4"
        :class="overallColor"
      >
        <component :is="overallIcon" class="w-10 h-10" :class="overallIconColor" />
        <div>
          <p class="text-xl font-semibold text-white">{{ overallLabel }}</p>
          <p class="text-sm text-gray-400 mt-0.5">
            Zuletzt aktualisiert: {{ new Date().toLocaleString('de-DE') }}
          </p>
        </div>
      </div>

      <!-- Monitors Grid -->
      <div v-if="data.monitors && data.monitors.length > 0" class="space-y-6 mb-8">
        <template v-for="(group, gi) in groupedMonitors" :key="gi">
          <div v-if="group.name" class="mb-2">
            <h3 class="text-sm font-medium text-gray-400 uppercase tracking-wider">{{ group.name }}</h3>
          </div>

          <div
            v-for="monitor in group.monitors"
            :key="monitor.id"
            class="bg-white/[0.03] border border-white/[0.06] rounded-xl p-4"
          >
            <!-- Monitor header -->
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full" :class="getStatusDotClass(monitor.current_status)"></div>
                <span class="text-white font-medium">{{ monitor.display_name }}</span>
              </div>
              <span
                class="text-sm font-medium"
                :class="{
                  'text-green-400': monitor.current_status === 'up',
                  'text-red-400': monitor.current_status === 'down',
                  'text-gray-400': !monitor.current_status || monitor.current_status === 'pending',
                }"
              >
                {{ calcOverallUptime(monitor.daily_history) !== null ? calcOverallUptime(monitor.daily_history) + '%' : '-' }}
              </span>
            </div>

            <!-- 90-day bars -->
            <div class="flex gap-px">
              <div
                v-for="(day, di) in padHistory(monitor.daily_history)"
                :key="di"
                class="flex-1 h-8 rounded-sm transition-colors hover:opacity-80 cursor-pointer"
                :class="getBarClass(day.status)"
                :title="getBarTooltip(day)"
              ></div>
            </div>

            <!-- Labels -->
            <div class="flex justify-between mt-1">
              <span class="text-xs text-gray-600">90 Tage</span>
              <span class="text-xs text-gray-600">Heute</span>
            </div>
          </div>
        </template>
      </div>

      <!-- No monitors -->
      <div v-else class="bg-white/[0.03] border border-white/[0.06] rounded-xl p-8 text-center mb-8">
        <p class="text-gray-400">Keine Monitore konfiguriert</p>
      </div>

      <!-- Active Incidents -->
      <div v-if="data.active_incidents && data.active_incidents.length > 0" class="mb-8">
        <h2 class="text-lg font-semibold text-white mb-4">Aktive Vorfaelle</h2>
        <div class="space-y-4">
          <div
            v-for="incident in data.active_incidents"
            :key="incident.id"
            class="bg-white/[0.03] border border-white/[0.06] rounded-xl p-5"
          >
            <div class="flex items-center gap-3 mb-3">
              <span
                class="px-2.5 py-1 text-xs font-medium rounded text-white"
                :class="getStatusBadge(incident.status).color"
              >
                {{ getStatusBadge(incident.status).label }}
              </span>
              <h3 class="text-white font-medium">{{ incident.title }}</h3>
            </div>
            <p v-if="incident.message" class="text-gray-400 text-sm mb-4">{{ incident.message }}</p>

            <!-- Timeline -->
            <div v-if="incident.updates && incident.updates.length > 0" class="border-t border-white/[0.06] pt-4">
              <div
                v-for="update in incident.updates"
                :key="update.id"
                class="flex gap-3 mb-3 last:mb-0"
              >
                <div class="flex flex-col items-center">
                  <div class="w-2 h-2 rounded-full mt-2" :class="getStatusBadge(update.status).color"></div>
                  <div class="w-px flex-1 bg-white/[0.06] mt-1"></div>
                </div>
                <div class="pb-3">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-white">{{ getStatusBadge(update.status).label }}</span>
                    <span class="text-xs text-gray-500">{{ formatDate(update.created_at) }}</span>
                  </div>
                  <p class="text-sm text-gray-400 mt-1">{{ update.message }}</p>
                </div>
              </div>
            </div>

            <p class="text-xs text-gray-500 mt-2">Gestartet: {{ formatDate(incident.started_at) }}</p>
          </div>
        </div>
      </div>

      <!-- No Active Incidents -->
      <div v-else class="mb-8">
        <div class="bg-white/[0.03] border border-white/[0.06] rounded-xl p-5 text-center">
          <CheckCircleIcon class="w-8 h-8 mx-auto text-green-500 mb-2" />
          <p class="text-gray-400">Keine aktiven Vorfaelle</p>
        </div>
      </div>

      <!-- Resolved Incidents (Collapsible) -->
      <div v-if="data.resolved_incidents && data.resolved_incidents.length > 0">
        <button
          @click="showResolved = !showResolved"
          class="flex items-center gap-2 text-gray-400 hover:text-white transition-colors mb-4"
        >
          <component :is="showResolved ? ChevronDownIcon : ChevronRightIcon" class="w-4 h-4" />
          <span class="text-sm font-medium">Behobene Vorfaelle der letzten 7 Tage ({{ data.resolved_incidents.length }})</span>
        </button>

        <div v-if="showResolved" class="space-y-3">
          <div
            v-for="incident in data.resolved_incidents"
            :key="incident.id"
            class="bg-white/[0.02] border border-white/[0.04] rounded-lg p-4"
          >
            <div class="flex items-center gap-3 mb-2">
              <span class="px-2 py-0.5 text-xs font-medium rounded text-white bg-green-500">Behoben</span>
              <h4 class="text-white text-sm font-medium">{{ incident.title }}</h4>
              <span class="text-xs text-gray-500 ml-auto">{{ formatDate(incident.resolved_at) }}</span>
            </div>
            <p v-if="incident.message" class="text-gray-500 text-sm">{{ incident.message }}</p>

            <div v-if="incident.updates && incident.updates.length > 0" class="mt-3 space-y-2">
              <div
                v-for="update in incident.updates"
                :key="update.id"
                class="flex gap-2 text-xs"
              >
                <span class="text-gray-600">{{ formatDate(update.created_at) }}</span>
                <span class="px-1.5 py-0.5 rounded text-white" :class="getStatusBadge(update.status).color">
                  {{ getStatusBadge(update.status).label }}
                </span>
                <span class="text-gray-400">{{ update.message }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="text-center mt-12 pt-8 border-t border-white/[0.06]">
        <p class="text-sm text-gray-600">Powered by KyuubiSoft</p>
      </div>
    </div>
  </div>
</template>
