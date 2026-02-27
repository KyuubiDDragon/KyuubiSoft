<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import {
  ServerIcon,
  ClockIcon,
  CpuChipIcon,
  PlayIcon,
  StopIcon,
  ArrowPathIcon,
  PlusIcon,
  TrashIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon,
  XCircleIcon,
  XMarkIcon,
  CommandLineIcon,
  Cog6ToothIcon,
  DocumentTextIcon,
  EyeIcon,
  ChevronDownIcon,
  ChevronUpIcon,
} from '@heroicons/vue/24/outline'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'

const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// State
const activeTab = ref('overview')
const loading = ref(false)
const error = ref(null)

// System Info
const systemInfo = ref(null)

// Crontabs
const crontabs = ref([])
const rawCrontab = ref('')
const showCrontabModal = ref(false)
const showEditCrontabModal = ref(false)
const newCrontab = ref({ schedule: '', command: '' })

// Processes
const processes = ref([])
const processFilter = ref('')
const phpFpmStatus = ref(null)

// Services
const services = ref([])
const customServices = ref([])
const serviceFilter = ref('running')
const serviceSearch = ref('')
const showServiceModal = ref(false)
const showAddServiceModal = ref(false)
const newServiceName = ref('')
const selectedService = ref(null)
const serviceStatus = ref(null)
const serviceLogs = ref('')
const importantServicesExpanded = ref(true)

// Computed filtered services (filters as you type)
const filteredServices = computed(() => {
  if (!serviceSearch.value) {
    return services.value
  }
  const searchLower = serviceSearch.value.toLowerCase()
  return services.value.filter(service =>
    service.name.toLowerCase().includes(searchLower) ||
    (service.description && service.description.toLowerCase().includes(searchLower))
  )
})

// Auto-refresh
let refreshInterval = null
const autoRefresh = ref(false)

// Cron schedule presets
const cronPresets = [
  { label: 'Jede Minute', value: '* * * * *' },
  { label: 'Alle 5 Minuten', value: '*/5 * * * *' },
  { label: 'Alle 15 Minuten', value: '*/15 * * * *' },
  { label: 'Alle 30 Minuten', value: '*/30 * * * *' },
  { label: 'Stündlich', value: '0 * * * *' },
  { label: 'Täglich (00:00)', value: '0 0 * * *' },
  { label: 'Täglich (03:00)', value: '0 3 * * *' },
  { label: 'Wöchentlich (Sonntag)', value: '0 0 * * 0' },
  { label: 'Monatlich (1. Tag)', value: '0 0 1 * *' },
  { label: 'Bei Systemstart', value: '@reboot' },
]

// API Calls
async function loadSystemInfo() {
  try {
    const response = await api.get('/api/v1/server/info')
    systemInfo.value = response.data.data
  } catch (e) {
    console.error('Failed to load system info:', e)
  }
}

async function loadCrontabs() {
  try {
    const response = await api.get('/api/v1/server/crontabs')
    const data = response.data.data
    crontabs.value = data.crontabs || []
    rawCrontab.value = data.raw || ''
  } catch (e) {
    console.error('Failed to load crontabs:', e)
  }
}

async function loadProcesses() {
  try {
    const response = await api.get('/api/v1/server/processes', {
      params: processFilter.value ? { filter: processFilter.value } : {}
    })
    const data = response.data.data
    processes.value = data.processes || []
    phpFpmStatus.value = data.php_fpm
  } catch (e) {
    console.error('Failed to load processes:', e)
  }
}

async function loadServices() {
  try {
    const params = { type: serviceFilter.value }
    const response = await api.get('/api/v1/server/services', { params })
    const data = response.data.data
    services.value = data.services || []
  } catch (e) {
    console.error('Failed to load services:', e)
  }
}

async function loadCustomServices() {
  try {
    const response = await api.get('/api/v1/server/services/custom')
    customServices.value = response.data.data.services || []
  } catch (e) {
    console.error('Failed to load custom services:', e)
  }
}

async function addCustomService() {
  if (!newServiceName.value.trim()) {
    uiStore.showError('Service-Name ist erforderlich')
    return
  }

  try {
    await api.post('/api/v1/server/services/custom', { name: newServiceName.value.trim() })
    uiStore.showSuccess('Service hinzugefügt')
    showAddServiceModal.value = false
    newServiceName.value = ''
    await loadCustomServices()
  } catch (e) {
    uiStore.showError(e.response?.data?.message || 'Fehler beim Hinzufügen')
  }
}

async function removeCustomService(serviceName) {
  if (!await confirm({ message: `Service "${serviceName}" wirklich aus der Liste entfernen?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/server/services/custom/${encodeURIComponent(serviceName)}`)
    uiStore.showSuccess('Service entfernt')
    await loadCustomServices()
  } catch (e) {
    uiStore.showError('Fehler beim Entfernen')
  }
}

async function refreshAll() {
  loading.value = true
  try {
    // Load system info first (lightweight)
    await loadSystemInfo()

    // Load data for current tab only
    switch (activeTab.value) {
      case 'overview':
        await Promise.all([loadServices(), loadCustomServices()])
        break
      case 'crontabs':
        await loadCrontabs()
        break
      case 'processes':
        await loadProcesses()
        break
    }
  } finally {
    loading.value = false
  }
}

// Initial load - just system info and current tab data
async function initialLoad() {
  loading.value = true
  try {
    await Promise.all([
      loadSystemInfo(),
      loadServices(),
      loadCustomServices(),
    ])
  } finally {
    loading.value = false
  }
}

// Crontab Actions
async function addCrontab() {
  if (!newCrontab.value.schedule || !newCrontab.value.command) {
    uiStore.showError('Schedule und Command sind erforderlich')
    return
  }

  try {
    await api.post('/api/v1/server/crontabs', newCrontab.value)
    uiStore.showSuccess('Crontab hinzugefügt')
    showCrontabModal.value = false
    newCrontab.value = { schedule: '', command: '' }
    await loadCrontabs()
  } catch (e) {
    uiStore.showError(e.response?.data?.message || 'Fehler beim Hinzufügen')
  }
}

async function deleteCrontab(line) {
  if (!await confirm({ message: 'Diesen Crontab-Eintrag wirklich löschen?', type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete('/api/v1/server/crontabs', { data: { line } })
    uiStore.showSuccess('Crontab gelöscht')
    await loadCrontabs()
  } catch (e) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function saveCrontabRaw() {
  try {
    await api.put('/api/v1/server/crontabs', { content: rawCrontab.value })
    uiStore.showSuccess('Crontab gespeichert')
    showEditCrontabModal.value = false
    await loadCrontabs()
  } catch (e) {
    uiStore.showError(e.response?.data?.message || 'Fehler beim Speichern')
  }
}

// Process Actions
async function killProcess(pid, signal = 'TERM') {
  if (!await confirm({ message: `Prozess ${pid} wirklich beenden?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    const response = await api.post('/api/v1/server/processes/kill', { pid, signal })
    const data = response.data.data
    if (data.terminated) {
      uiStore.showSuccess('Prozess beendet')
    } else {
      uiStore.showWarning('Signal gesendet, Prozess läuft noch')
    }
    await loadProcesses()
  } catch (e) {
    uiStore.showError(e.response?.data?.message || 'Fehler beim Beenden')
  }
}

// Service Actions
async function viewService(service) {
  selectedService.value = service
  showServiceModal.value = true

  try {
    const response = await api.get('/api/v1/server/services/status', {
      params: { service: service.name }
    })
    const data = response.data.data
    serviceStatus.value = data.status
    serviceLogs.value = data.logs
  } catch (e) {
    serviceStatus.value = 'Failed to load status'
    serviceLogs.value = ''
  }
}

async function controlService(service, action) {
  const actionNames = {
    start: 'starten',
    stop: 'stoppen',
    restart: 'neustarten',
    reload: 'neu laden',
  }

  if (!await confirm({ message: `${service} wirklich ${actionNames[action] || action}?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.post('/api/v1/server/services/control', { service, action })
    uiStore.showSuccess(`Service ${actionNames[action] || action}`)
    await loadServices()

    if (showServiceModal.value && selectedService.value?.name === service) {
      await viewService(selectedService.value)
    }
  } catch (e) {
    uiStore.showError(e.response?.data?.message || 'Fehler bei Service-Kontrolle')
  }
}

// Lifecycle
onMounted(async () => {
  await initialLoad()
})

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
})

// Smart auto-refresh - only refresh data relevant to current tab
function refreshCurrentTab() {
  switch (activeTab.value) {
    case 'overview':
      loadServices()
      loadCustomServices()
      break
    case 'crontabs':
      loadCrontabs()
      break
    case 'processes':
      loadProcesses()
      break
  }
  // Always refresh system info (lightweight)
  loadSystemInfo()
}

// Watch auto-refresh toggle
function toggleAutoRefresh() {
  if (autoRefresh.value) {
    refreshInterval = setInterval(refreshCurrentTab, 10000)
  } else if (refreshInterval) {
    clearInterval(refreshInterval)
    refreshInterval = null
  }
}

// Watch tab changes to load data on demand
watch(activeTab, (newTab) => {
  switch (newTab) {
    case 'overview':
      if (services.value.length === 0) loadServices()
      break
    case 'crontabs':
      if (crontabs.value.length === 0) loadCrontabs()
      break
    case 'processes':
      loadProcesses() // Always refresh processes
      break
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <ServerIcon class="w-7 h-7" />
          Server Manager
        </h1>
        <p class="text-gray-400 mt-1">
          {{ systemInfo?.hostname || 'Server' }} - {{ systemInfo?.os || 'Loading...' }}
        </p>
      </div>
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-sm text-gray-400">
          <input v-model="autoRefresh" @change="toggleAutoRefresh" type="checkbox" class="rounded border-dark-500 bg-dark-700" />
          Auto-Refresh
        </label>
        <button @click="refreshAll" :disabled="loading" class="btn-secondary">
          <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>
      </div>
    </div>

    <!-- System Overview Cards -->
    <div v-if="systemInfo" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div class="card p-4">
        <p class="text-xs text-gray-400 mb-1">Uptime</p>
        <p class="text-lg font-semibold text-white">{{ systemInfo.uptime }}</p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-gray-400 mb-1">Load Average</p>
        <p class="text-lg font-semibold text-white">
          {{ systemInfo.load_average?.['1min'] }} / {{ systemInfo.load_average?.['5min'] }} / {{ systemInfo.load_average?.['15min'] }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-gray-400 mb-1">Memory</p>
        <p class="text-lg font-semibold text-white">{{ systemInfo.memory?.percent }}%</p>
        <div class="w-full h-1.5 bg-dark-600 rounded-full mt-1.5 mb-1">
          <div
            class="h-full rounded-full transition-all"
            :class="systemInfo.memory?.percent >= 90 ? 'bg-red-500' : systemInfo.memory?.percent >= 70 ? 'bg-yellow-500' : 'bg-primary-500'"
            :style="{ width: systemInfo.memory?.percent + '%' }"
          ></div>
        </div>
        <p class="text-xs text-gray-500">{{ systemInfo.memory?.used }} / {{ systemInfo.memory?.total }}</p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-gray-400 mb-1">CPU</p>
        <p class="text-lg font-semibold text-white">{{ systemInfo.cpu?.percent ?? '–' }}%</p>
        <div class="w-full h-1.5 bg-dark-600 rounded-full mt-1.5 mb-1">
          <div
            class="h-full rounded-full transition-all"
            :class="systemInfo.cpu?.percent >= 90 ? 'bg-red-500' : systemInfo.cpu?.percent >= 70 ? 'bg-yellow-500' : 'bg-primary-500'"
            :style="{ width: (systemInfo.cpu?.percent ?? 0) + '%' }"
          ></div>
        </div>
        <p class="text-xs text-gray-500">{{ systemInfo.cpu?.cores }} Kerne</p>
      </div>

      <!-- All Disks -->
      <div v-if="systemInfo.disks?.length" class="col-span-2 sm:col-span-4 card p-4">
        <p class="text-xs text-gray-400 mb-3">Festplatten</p>
        <div class="space-y-2">
          <div v-for="disk in systemInfo.disks" :key="disk.mount" class="flex items-center gap-3">
            <span class="text-xs text-gray-400 w-28 truncate shrink-0 font-mono">{{ disk.mount }}</span>
            <div class="flex-1 h-2 bg-dark-600 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :class="disk.percent >= 90 ? 'bg-red-500' : disk.percent >= 70 ? 'bg-yellow-500' : 'bg-primary-500'"
                :style="{ width: disk.percent + '%' }"
              ></div>
            </div>
            <span class="text-xs text-white w-10 text-right shrink-0">{{ disk.percent }}%</span>
            <span class="text-xs text-gray-500 w-36 text-right shrink-0 hidden sm:block">{{ disk.used }} / {{ disk.total }}</span>
            <span class="text-xs text-gray-600 w-28 truncate shrink-0 font-mono hidden md:block">{{ disk.device }}</span>
          </div>
        </div>
      </div>

      <!-- Network Interfaces -->
      <div v-if="systemInfo.network?.length" class="col-span-2 sm:col-span-4 card p-4">
        <p class="text-xs text-gray-400 mb-3">Netzwerk</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div v-for="iface in systemInfo.network" :key="iface.name" class="bg-dark-800 rounded-lg px-3 py-2">
            <p class="text-xs font-semibold text-white font-mono">{{ iface.name }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ iface.ip }}</p>
            <div class="flex gap-3 mt-1 text-xs text-gray-500">
              <span>↓ {{ iface.rx }}</span>
              <span>↑ {{ iface.tx }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-dark-600">
      <nav class="flex gap-4">
        <button
          v-for="tab in [
            { id: 'overview', label: 'Services', icon: Cog6ToothIcon },
            { id: 'crontabs', label: 'Crontabs', icon: ClockIcon },
            { id: 'processes', label: 'Prozesse', icon: CpuChipIcon },
          ]"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'flex items-center gap-2 px-4 py-3 border-b-2 transition-colors',
            activeTab === tab.id
              ? 'border-primary-500 text-primary-400'
              : 'border-transparent text-gray-400 hover:text-white'
          ]"
        >
          <component :is="tab.icon" class="w-4 h-4" />
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <!-- Services Tab -->
    <div v-if="activeTab === 'overview'" class="space-y-6">
      <!-- Important Services Section (Collapsible) - Only user's custom services -->
      <div class="card">
        <!-- Header with toggle -->
        <div
          class="flex items-center justify-between p-4 cursor-pointer hover:bg-dark-700/50 transition-colors"
          @click="importantServicesExpanded = !importantServicesExpanded"
        >
          <div class="flex items-center gap-3">
            <component
              :is="importantServicesExpanded ? ChevronDownIcon : ChevronUpIcon"
              class="w-5 h-5 text-gray-400"
            />
            <h3 class="text-lg font-medium text-white">Wichtige Services</h3>
            <span class="text-sm text-gray-500">({{ customServices.length }})</span>
          </div>
          <button
            @click.stop="showAddServiceModal = true"
            class="btn-secondary text-sm"
            title="Service hinzufuegen"
          >
            <PlusIcon class="w-4 h-4 mr-1" />
            Hinzufuegen
          </button>
        </div>

        <!-- Collapsible content -->
        <div v-show="importantServicesExpanded" class="p-4 pt-0">
          <div v-if="customServices.length === 0" class="text-center text-gray-500 py-8">
            <Cog6ToothIcon class="w-12 h-12 mx-auto mb-3 opacity-50" />
            <p>Keine wichtigen Services hinterlegt</p>
            <p class="text-sm mt-1">Klicke auf "Hinzufuegen" um Services zu deiner Liste hinzuzufuegen</p>
          </div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <!-- Custom user services only -->
            <div
              v-for="service in customServices"
              :key="service.name"
              class="bg-dark-700 rounded-lg p-4 flex items-center justify-between"
            >
              <div class="flex items-center gap-3">
                <div
                  class="w-3 h-3 rounded-full"
                  :class="service.active ? 'bg-green-400' : 'bg-red-400'"
                ></div>
                <div>
                  <p class="font-medium text-white">{{ service.name }}</p>
                  <p class="text-xs text-gray-500">{{ service.enabled ? 'Aktiviert' : 'Deaktiviert' }}</p>
                </div>
              </div>
              <div class="flex items-center gap-1">
                <button
                  v-if="!service.active"
                  @click="controlService(service.name, 'start')"
                  class="btn-icon text-green-400 hover:bg-green-500/20"
                  title="Starten"
                >
                  <PlayIcon class="w-4 h-4" />
                </button>
                <button
                  v-if="service.active"
                  @click="controlService(service.name, 'restart')"
                  class="btn-icon text-blue-400 hover:bg-blue-500/20"
                  title="Neustarten"
                >
                  <ArrowPathIcon class="w-4 h-4" />
                </button>
                <button
                  v-if="service.active"
                  @click="controlService(service.name, 'stop')"
                  class="btn-icon text-red-400 hover:bg-red-500/20"
                  title="Stoppen"
                >
                  <StopIcon class="w-4 h-4" />
                </button>
                <button
                  @click="removeCustomService(service.name)"
                  class="btn-icon text-gray-400 hover:text-red-400 hover:bg-red-500/20"
                  title="Aus Liste entfernen"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Service Filter -->
      <div class="flex flex-wrap items-center gap-4">
        <h3 class="text-lg font-medium text-white">Alle Services</h3>
        <select v-model="serviceFilter" @change="loadServices" class="input w-40">
          <option value="all">Alle</option>
          <option value="running">Laufend</option>
          <option value="failed">Fehlgeschlagen</option>
          <option value="enabled">Aktiviert</option>
        </select>
        <input
          v-model="serviceSearch"
          type="text"
          class="input w-48"
          placeholder="Suche..."
        />
        <span class="text-sm text-gray-500">{{ filteredServices.length }} von {{ services.length }}</span>
      </div>

      <!-- Services Table -->
      <div class="card overflow-hidden">
        <table class="w-full">
          <thead class="bg-dark-700">
            <tr class="text-left text-sm text-gray-400">
              <th class="p-3">Service</th>
              <th class="p-3">Status</th>
              <th class="p-3 hidden sm:table-cell">Beschreibung</th>
              <th class="p-3 text-right">Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="service in filteredServices"
              :key="service.name"
              class="border-t border-dark-600 hover:bg-dark-700"
            >
              <td class="p-3">
                <span class="font-medium text-white">{{ service.name }}</span>
              </td>
              <td class="p-3">
                <span
                  class="px-2 py-1 text-xs rounded-full"
                  :class="{
                    'bg-green-500/20 text-green-400': service.active === 'active',
                    'bg-red-500/20 text-red-400': service.active === 'failed',
                    'bg-gray-500/20 text-gray-400': service.active === 'inactive',
                  }"
                >
                  {{ service.active }} ({{ service.sub }})
                </span>
              </td>
              <td class="p-3 text-gray-400 text-sm hidden sm:table-cell truncate max-w-xs">
                {{ service.description }}
              </td>
              <td class="p-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button
                    @click="viewService(service)"
                    class="btn-icon text-gray-400 hover:text-white"
                    title="Details"
                  >
                    <EyeIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="controlService(service.name, 'restart')"
                    class="btn-icon text-blue-400 hover:bg-blue-500/20"
                    title="Neustarten"
                  >
                    <ArrowPathIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Crontabs Tab -->
    <div v-if="activeTab === 'crontabs'" class="space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-medium text-white">Crontab Einträge</h3>
        <div class="flex gap-2">
          <button @click="showEditCrontabModal = true" class="btn-secondary">
            <DocumentTextIcon class="w-4 h-4 mr-1" />
            Raw bearbeiten
          </button>
          <button @click="showCrontabModal = true" class="btn-primary">
            <PlusIcon class="w-4 h-4 mr-1" />
            Hinzufügen
          </button>
        </div>
      </div>

      <div v-if="crontabs.length === 0" class="card p-8 text-center text-gray-400">
        <ClockIcon class="w-12 h-12 mx-auto mb-4 opacity-50" />
        <p>Keine Crontab-Einträge vorhanden</p>
      </div>

      <div v-else class="space-y-2">
        <div
          v-for="cron in crontabs"
          :key="cron.line"
          class="card p-4"
          :class="{ 'opacity-50': !cron.enabled }"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <span
                  class="px-2 py-0.5 text-xs rounded bg-primary-500/20 text-primary-400 font-mono"
                >
                  {{ cron.schedule }}
                </span>
                <span class="text-sm text-gray-400">{{ cron.description }}</span>
              </div>
              <code class="text-sm text-gray-300 font-mono break-all">{{ cron.command }}</code>
            </div>
            <button
              v-if="cron.enabled"
              @click="deleteCrontab(cron.line)"
              class="btn-icon text-red-400 hover:bg-red-500/20 flex-shrink-0"
              title="Löschen"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Processes Tab -->
    <div v-if="activeTab === 'processes'" class="space-y-4">
      <div class="flex items-center justify-between gap-4">
        <h3 class="text-lg font-medium text-white">Laufende Prozesse</h3>
        <div class="flex items-center gap-2">
          <input
            v-model="processFilter"
            type="text"
            class="input w-48"
            placeholder="Filter (php, node...)"
            @keyup.enter="loadProcesses"
          />
          <button @click="loadProcesses" class="btn-secondary">
            <ArrowPathIcon class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- PHP-FPM Status -->
      <div v-if="phpFpmStatus" class="card p-4 bg-blue-500/10 border-blue-500/30">
        <div class="flex items-center gap-3">
          <CheckCircleIcon class="w-5 h-5 text-blue-400" />
          <span class="text-blue-400">PHP-FPM aktiv mit {{ phpFpmStatus.workers }} Worker(n)</span>
        </div>
      </div>

      <!-- Processes Table -->
      <div class="card overflow-x-auto">
        <table class="w-full">
          <thead class="bg-dark-700">
            <tr class="text-left text-xs text-gray-400">
              <th class="p-3">PID</th>
              <th class="p-3">User</th>
              <th class="p-3">CPU</th>
              <th class="p-3">MEM</th>
              <th class="p-3 hidden lg:table-cell">RSS</th>
              <th class="p-3">Command</th>
              <th class="p-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="proc in processes.slice(0, 50)"
              :key="proc.pid"
              class="border-t border-dark-600 hover:bg-dark-700"
            >
              <td class="p-3 font-mono text-sm text-gray-300">{{ proc.pid }}</td>
              <td class="p-3 text-sm text-gray-400">{{ proc.user }}</td>
              <td class="p-3 text-sm" :class="parseFloat(proc.cpu) > 50 ? 'text-red-400' : 'text-gray-400'">
                {{ proc.cpu }}
              </td>
              <td class="p-3 text-sm" :class="parseFloat(proc.mem) > 50 ? 'text-red-400' : 'text-gray-400'">
                {{ proc.mem }}
              </td>
              <td class="p-3 text-sm text-gray-400 hidden lg:table-cell">{{ proc.rss }}</td>
              <td class="p-3 text-sm text-gray-300 font-mono truncate max-w-xs" :title="proc.command">
                {{ proc.command }}
              </td>
              <td class="p-3">
                <button
                  @click="killProcess(proc.pid)"
                  class="btn-icon text-red-400 hover:bg-red-500/20"
                  title="Beenden"
                >
                  <XCircleIcon class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="text-sm text-gray-500">Zeige {{ Math.min(processes.length, 50) }} von {{ processes.length }} Prozessen</p>
    </div>

    <!-- Add Crontab Modal -->
    <Teleport to="body">
      <div
        v-if="showCrontabModal"
        class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-dark-800 rounded-xl border border-dark-600 w-full max-w-lg">
          <div class="flex items-center justify-between p-4 border-b border-dark-600">
            <h2 class="text-lg font-semibold text-white">Neuer Crontab</h2>
            <button @click="showCrontabModal = false" class="btn-icon">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="addCrontab" class="p-4 space-y-4">
            <div>
              <label class="label">Schedule</label>
              <select v-model="newCrontab.schedule" class="input mb-2">
                <option value="">Eigene Eingabe...</option>
                <option v-for="preset in cronPresets" :key="preset.value" :value="preset.value">
                  {{ preset.label }} ({{ preset.value }})
                </option>
              </select>
              <input
                v-model="newCrontab.schedule"
                type="text"
                class="input font-mono"
                placeholder="* * * * *"
              />
              <p class="text-xs text-gray-500 mt-1">Format: Minute Stunde Tag Monat Wochentag</p>
            </div>

            <div>
              <label class="label">Command</label>
              <input
                v-model="newCrontab.command"
                type="text"
                class="input font-mono"
                placeholder="/usr/bin/php /path/to/script.php"
              />
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="showCrontabModal = false" class="btn-secondary flex-1">
                Abbrechen
              </button>
              <button type="submit" class="btn-primary flex-1">
                Hinzufügen
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Edit Raw Crontab Modal -->
      <div
        v-if="showEditCrontabModal"
        class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-dark-800 rounded-xl border border-dark-600 w-full max-w-2xl">
          <div class="flex items-center justify-between p-4 border-b border-dark-600">
            <h2 class="text-lg font-semibold text-white">Crontab bearbeiten</h2>
            <button @click="showEditCrontabModal = false" class="btn-icon">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4">
            <textarea
              v-model="rawCrontab"
              class="w-full h-80 bg-dark-900 text-gray-300 font-mono text-sm p-4 rounded-lg border border-dark-600 focus:border-primary-500 focus:outline-none resize-none"
              spellcheck="false"
            ></textarea>
          </div>

          <div class="flex justify-end gap-3 p-4 border-t border-dark-600">
            <button @click="showEditCrontabModal = false" class="btn-secondary">
              Abbrechen
            </button>
            <button @click="saveCrontabRaw" class="btn-primary">
              Speichern
            </button>
          </div>
        </div>
      </div>

      <!-- Service Details Modal -->
      <div
        v-if="showServiceModal && selectedService"
        class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-dark-800 rounded-xl border border-dark-600 w-full max-w-3xl max-h-[90vh] flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-dark-600">
            <div>
              <h2 class="text-lg font-semibold text-white">{{ selectedService.name }}</h2>
              <p class="text-sm text-gray-400">{{ selectedService.description }}</p>
            </div>
            <button @click="showServiceModal = false" class="btn-icon">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="flex-1 overflow-auto p-4 space-y-4">
            <div>
              <h4 class="text-sm font-medium text-gray-300 mb-2">Status</h4>
              <pre class="bg-dark-900 p-4 rounded-lg text-xs text-gray-300 font-mono overflow-auto max-h-40 whitespace-pre-wrap">{{ serviceStatus || 'Loading...' }}</pre>
            </div>

            <div>
              <h4 class="text-sm font-medium text-gray-300 mb-2">Logs (letzte 50 Zeilen)</h4>
              <pre class="bg-dark-900 p-4 rounded-lg text-xs text-gray-300 font-mono overflow-auto max-h-60 whitespace-pre-wrap">{{ serviceLogs || 'Keine Logs verfügbar' }}</pre>
            </div>
          </div>

          <div class="flex justify-between p-4 border-t border-dark-600">
            <div class="flex gap-2">
              <button
                @click="controlService(selectedService.name, 'start')"
                class="btn-secondary text-green-400"
              >
                <PlayIcon class="w-4 h-4 mr-1" />
                Start
              </button>
              <button
                @click="controlService(selectedService.name, 'stop')"
                class="btn-secondary text-red-400"
              >
                <StopIcon class="w-4 h-4 mr-1" />
                Stop
              </button>
              <button
                @click="controlService(selectedService.name, 'restart')"
                class="btn-secondary text-blue-400"
              >
                <ArrowPathIcon class="w-4 h-4 mr-1" />
                Restart
              </button>
              <button
                @click="controlService(selectedService.name, 'reload')"
                class="btn-secondary text-yellow-400"
              >
                <ArrowPathIcon class="w-4 h-4 mr-1" />
                Reload
              </button>
            </div>
            <button @click="showServiceModal = false" class="btn-secondary">
              Schließen
            </button>
          </div>
        </div>
      </div>

      <!-- Add Custom Service Modal -->
      <div
        v-if="showAddServiceModal"
        class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4"
      >
        <div class="bg-dark-800 rounded-xl border border-dark-600 w-full max-w-md">
          <div class="flex items-center justify-between p-4 border-b border-dark-600">
            <h2 class="text-lg font-semibold text-white">Service hinzufuegen</h2>
            <button @click="showAddServiceModal = false" class="btn-icon">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="addCustomService" class="p-4 space-y-4">
            <div>
              <label class="label">Service-Name</label>
              <input
                v-model="newServiceName"
                type="text"
                class="input"
                placeholder="z.B. mysql, postgresql, redis..."
                autofocus
              />
              <p class="text-xs text-gray-500 mt-1">
                Der exakte systemd Service-Name (ohne .service)
              </p>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="showAddServiceModal = false" class="btn-secondary flex-1">
                Abbrechen
              </button>
              <button type="submit" class="btn-primary flex-1">
                Hinzufuegen
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>
