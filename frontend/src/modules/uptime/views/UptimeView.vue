<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import {
  PlusIcon,
  SignalIcon,
  TrashIcon,
  PencilIcon,
  PlayIcon,
  PauseIcon,
  ArrowPathIcon,
  XMarkIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline'

const uiStore = useUiStore()

// State
const monitors = ref([])
const stats = ref(null)
const isLoading = ref(true)
const showModal = ref(false)
const showDetailModal = ref(false)
const editingMonitor = ref(null)
const selectedMonitor = ref(null)
const checkingId = ref(null)
let refreshInterval = null

// Form
const form = ref({
  name: '',
  url: '',
  type: 'https',
  check_interval: 300,
  timeout: 30,
  expected_status_code: 200,
  expected_keyword: '',
  notify_on_down: true,
  notify_on_recovery: true,
})

const monitorTypes = [
  { value: 'https', label: 'HTTPS' },
  { value: 'http', label: 'HTTP' },
  { value: 'ping', label: 'Ping' },
]

const intervalOptions = [
  { value: 60, label: '1 Minute' },
  { value: 300, label: '5 Minuten' },
  { value: 600, label: '10 Minuten' },
  { value: 1800, label: '30 Minuten' },
  { value: 3600, label: '1 Stunde' },
]

// Computed
const upCount = computed(() => monitors.value.filter(m => m.current_status === 'up').length)
const downCount = computed(() => monitors.value.filter(m => m.current_status === 'down').length)

// API Calls
onMounted(async () => {
  await loadData()
  refreshInterval = setInterval(loadData, 60000) // Refresh every minute
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})

async function loadData() {
  isLoading.value = monitors.value.length === 0
  try {
    const [monitorsRes, statsRes] = await Promise.all([
      api.get('/api/v1/uptime'),
      api.get('/api/v1/uptime/stats'),
    ])
    monitors.value = monitorsRes.data.data?.items || []
    stats.value = statsRes.data.data
  } catch (error) {
    uiStore.showError('Fehler beim Laden')
  } finally {
    isLoading.value = false
  }
}

async function saveMonitor() {
  if (!form.value.name.trim() || !form.value.url.trim()) {
    uiStore.showError('Name und URL sind erforderlich')
    return
  }

  try {
    if (editingMonitor.value) {
      await api.put(`/api/v1/uptime/${editingMonitor.value.id}`, form.value)
      uiStore.showSuccess('Monitor aktualisiert')
    } else {
      await api.post('/api/v1/uptime', form.value)
      uiStore.showSuccess('Monitor erstellt')
    }
    await loadData()
    showModal.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deleteMonitor(monitor) {
  if (!confirm(`Monitor "${monitor.name}" wirklich löschen?`)) return

  try {
    await api.delete(`/api/v1/uptime/${monitor.id}`)
    monitors.value = monitors.value.filter(m => m.id !== monitor.id)
    uiStore.showSuccess('Monitor gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function togglePause(monitor) {
  try {
    await api.put(`/api/v1/uptime/${monitor.id}`, {
      is_paused: !monitor.is_paused,
    })
    monitor.is_paused = !monitor.is_paused
    uiStore.showSuccess(monitor.is_paused ? 'Monitor pausiert' : 'Monitor fortgesetzt')
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

async function checkNow(monitor) {
  checkingId.value = monitor.id
  try {
    const response = await api.post(`/api/v1/uptime/${monitor.id}/check`)
    const result = response.data.data
    monitor.current_status = result.status
    monitor.last_check_at = new Date().toISOString()
    if (result.status === 'up') {
      monitor.last_up_at = new Date().toISOString()
    } else {
      monitor.last_down_at = new Date().toISOString()
    }
    uiStore.showSuccess(`Check abgeschlossen: ${result.status.toUpperCase()}`)
  } catch (error) {
    uiStore.showError('Fehler beim Check')
  } finally {
    checkingId.value = null
  }
}

async function openDetail(monitor) {
  try {
    const response = await api.get(`/api/v1/uptime/${monitor.id}`)
    selectedMonitor.value = response.data.data
    showDetailModal.value = true
  } catch (error) {
    uiStore.showError('Fehler beim Laden')
  }
}

// Modal
function openCreateModal() {
  editingMonitor.value = null
  form.value = {
    name: '',
    url: '',
    type: 'https',
    check_interval: 300,
    timeout: 30,
    expected_status_code: 200,
    expected_keyword: '',
    notify_on_down: true,
    notify_on_recovery: true,
  }
  showModal.value = true
}

function openEditModal(monitor) {
  editingMonitor.value = monitor
  form.value = {
    name: monitor.name,
    url: monitor.url,
    type: monitor.type,
    check_interval: monitor.check_interval,
    timeout: monitor.timeout,
    expected_status_code: monitor.expected_status_code,
    expected_keyword: monitor.expected_keyword || '',
    notify_on_down: monitor.notify_on_down,
    notify_on_recovery: monitor.notify_on_recovery,
  }
  showModal.value = true
}

function formatDate(dateStr) {
  if (!dateStr) return 'Nie'
  return new Date(dateStr).toLocaleString('de-DE')
}

function formatDuration(seconds) {
  if (!seconds) return '-'
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  if (hours > 0) return `${hours}h ${minutes}m`
  return `${minutes}m`
}

function getStatusColor(status) {
  switch (status) {
    case 'up': return 'text-green-400'
    case 'down': return 'text-red-400'
    default: return 'text-gray-400'
  }
}

function getStatusBg(status) {
  switch (status) {
    case 'up': return 'bg-green-500'
    case 'down': return 'bg-red-500'
    default: return 'bg-gray-500'
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">Uptime Monitor</h1>
        <p class="text-gray-400 mt-1">Überwache deine Websites und Services</p>
      </div>
      <button @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Neuer Monitor
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center">
            <SignalIcon class="w-5 h-5 text-blue-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-white">{{ monitors.length }}</p>
            <p class="text-sm text-gray-400">Monitore</p>
          </div>
        </div>
      </div>
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-green-500/20 rounded-lg flex items-center justify-center">
            <CheckCircleIcon class="w-5 h-5 text-green-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-green-400">{{ upCount }}</p>
            <p class="text-sm text-gray-400">Online</p>
          </div>
        </div>
      </div>
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-red-500/20 rounded-lg flex items-center justify-center">
            <ExclamationCircleIcon class="w-5 h-5 text-red-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-red-400">{{ downCount }}</p>
            <p class="text-sm text-gray-400">Offline</p>
          </div>
        </div>
      </div>
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-yellow-500/20 rounded-lg flex items-center justify-center">
            <ClockIcon class="w-5 h-5 text-yellow-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-white">{{ stats?.recent_incidents?.length || 0 }}</p>
            <p class="text-sm text-gray-400">Letzte Incidents</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Empty state -->
    <div v-else-if="monitors.length === 0" class="card p-12 text-center">
      <SignalIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
      <h3 class="text-lg font-medium text-white mb-2">Keine Monitore</h3>
      <p class="text-gray-400 mb-6">Erstelle deinen ersten Monitor</p>
      <button @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Monitor erstellen
      </button>
    </div>

    <!-- Monitors List -->
    <div v-else class="space-y-3">
      <div
        v-for="monitor in monitors"
        :key="monitor.id"
        class="bg-dark-800 border border-dark-700 rounded-xl p-4 hover:border-dark-600 transition-colors group"
      >
        <div class="flex items-center gap-4">
          <!-- Status indicator -->
          <div
            class="w-3 h-3 rounded-full animate-pulse"
            :class="getStatusBg(monitor.current_status)"
          ></div>

          <!-- Info -->
          <div class="flex-1 min-w-0 cursor-pointer" @click="openDetail(monitor)">
            <div class="flex items-center gap-2">
              <h3 class="font-medium text-white">{{ monitor.name }}</h3>
              <span v-if="monitor.is_paused" class="px-2 py-0.5 text-xs rounded bg-yellow-500/20 text-yellow-400">
                Pausiert
              </span>
            </div>
            <p class="text-sm text-gray-500 truncate">{{ monitor.url }}</p>
          </div>

          <!-- Uptime bars (last 30 checks) -->
          <div class="hidden md:flex gap-0.5">
            <div
              v-for="(check, i) in (monitor.recent_checks || []).slice(0, 30)"
              :key="i"
              class="w-1.5 h-6 rounded-sm"
              :class="check.status === 'up' ? 'bg-green-500' : 'bg-red-500'"
              :title="`${check.status} - ${check.response_time}ms`"
            ></div>
          </div>

          <!-- Uptime percentage -->
          <div class="text-right">
            <p class="text-lg font-bold" :class="getStatusColor(monitor.current_status)">
              {{ monitor.uptime_percentage }}%
            </p>
            <p class="text-xs text-gray-500">Uptime</p>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <button
              @click="checkNow(monitor)"
              :disabled="checkingId === monitor.id"
              class="p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors"
              title="Jetzt prüfen"
            >
              <ArrowPathIcon class="w-5 h-5" :class="{ 'animate-spin': checkingId === monitor.id }" />
            </button>
            <button
              @click="togglePause(monitor)"
              class="p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors"
              :title="monitor.is_paused ? 'Fortsetzen' : 'Pausieren'"
            >
              <PlayIcon v-if="monitor.is_paused" class="w-5 h-5" />
              <PauseIcon v-else class="w-5 h-5" />
            </button>
            <button
              @click="openEditModal(monitor)"
              class="p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors"
            >
              <PencilIcon class="w-5 h-5" />
            </button>
            <button
              @click="deleteMonitor(monitor)"
              class="p-2 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded-lg transition-colors"
            >
              <TrashIcon class="w-5 h-5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="showModal = false"
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-lg border border-dark-700">
          <div class="p-4 border-b border-dark-700 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">
              {{ editingMonitor ? 'Monitor bearbeiten' : 'Neuer Monitor' }}
            </h2>
            <button @click="showModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="saveMonitor" class="p-4 space-y-4">
            <div>
              <label class="label">Name *</label>
              <input v-model="form.name" type="text" class="input" placeholder="Meine Website" required />
            </div>

            <div>
              <label class="label">URL *</label>
              <input v-model="form.url" type="url" class="input" placeholder="https://example.com" required />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">Typ</label>
                <select v-model="form.type" class="input">
                  <option v-for="type in monitorTypes" :key="type.value" :value="type.value">
                    {{ type.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="label">Check-Intervall</label>
                <select v-model="form.check_interval" class="input">
                  <option v-for="opt in intervalOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">Timeout (Sek.)</label>
                <input v-model.number="form.timeout" type="number" class="input" min="5" max="60" />
              </div>
              <div>
                <label class="label">Erwarteter Status</label>
                <input v-model.number="form.expected_status_code" type="number" class="input" />
              </div>
            </div>

            <div>
              <label class="label">Erwartetes Keyword (optional)</label>
              <input v-model="form.expected_keyword" type="text" class="input" placeholder="z.B. 'OK' oder 'Welcome'" />
            </div>

            <div class="flex gap-4">
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="form.notify_on_down" type="checkbox" class="checkbox" />
                <span class="text-gray-300">Bei Ausfall benachrichtigen</span>
              </label>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="showModal = false" class="btn-secondary flex-1">
                Abbrechen
              </button>
              <button type="submit" class="btn-primary flex-1">
                {{ editingMonitor ? 'Speichern' : 'Erstellen' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Detail Modal -->
    <Teleport to="body">
      <div
        v-if="showDetailModal && selectedMonitor"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="showDetailModal = false"
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-2xl border border-dark-700 max-h-[90vh] overflow-y-auto">
          <div class="p-4 border-b border-dark-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div
                class="w-3 h-3 rounded-full"
                :class="getStatusBg(selectedMonitor.current_status)"
              ></div>
              <h2 class="text-lg font-semibold text-white">{{ selectedMonitor.name }}</h2>
            </div>
            <button @click="showDetailModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-6">
            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4">
              <div v-for="(stat, period) in selectedMonitor.stats" :key="period" class="bg-dark-700 rounded-lg p-3">
                <p class="text-sm text-gray-400">{{ period }}</p>
                <p class="text-xl font-bold text-white">{{ stat.percentage }}%</p>
                <p class="text-xs text-gray-500">Ø {{ stat.avg_response_time }}ms</p>
              </div>
            </div>

            <!-- Recent incidents -->
            <div v-if="selectedMonitor.incidents?.length">
              <h3 class="text-sm font-medium text-gray-400 mb-2">Letzte Incidents</h3>
              <div class="space-y-2">
                <div
                  v-for="incident in selectedMonitor.incidents"
                  :key="incident.id"
                  class="bg-dark-700 rounded-lg p-3"
                >
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-red-400">{{ formatDate(incident.started_at) }}</span>
                    <span class="text-sm text-gray-400">
                      {{ incident.is_resolved ? `Dauer: ${formatDuration(incident.duration_seconds)}` : 'Andauernd' }}
                    </span>
                  </div>
                  <p v-if="incident.cause" class="text-xs text-gray-500 mt-1">{{ incident.cause }}</p>
                </div>
              </div>
            </div>

            <!-- Recent checks -->
            <div>
              <h3 class="text-sm font-medium text-gray-400 mb-2">Letzte Checks</h3>
              <div class="space-y-1 max-h-48 overflow-y-auto">
                <div
                  v-for="check in selectedMonitor.recent_checks"
                  :key="check.id"
                  class="flex items-center justify-between py-1 text-sm"
                >
                  <span :class="check.status === 'up' ? 'text-green-400' : 'text-red-400'">
                    {{ check.status.toUpperCase() }}
                  </span>
                  <span class="text-gray-500">{{ check.response_time }}ms</span>
                  <span class="text-gray-500">{{ formatDate(check.checked_at) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
