<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
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
  GlobeAltIcon,
  ServerIcon,
  ShieldCheckIcon,
  CubeIcon,
  UsersIcon,
  FolderIcon,
  FolderPlusIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  FunnelIcon,
} from '@heroicons/vue/24/outline'

const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// State
const monitors = ref([])
const folders = ref([])
const projects = ref([])
const monitorTypes = ref({})
const stats = ref(null)
const isLoading = ref(true)
const showModal = ref(false)
const showDetailModal = ref(false)
const showFolderModal = ref(false)
const editingMonitor = ref(null)
const editingFolder = ref(null)
const selectedMonitor = ref(null)
const checkingId = ref(null)
const selectedMonitorIds = ref([])
let refreshInterval = null

// Filters
const filters = ref({
  project_id: '',
  folder_id: null, // null = all, '' = unfiled, uuid = specific folder
  type: '',
  status: '',
})

// Form
const form = ref({
  name: '',
  url: '',
  hostname: '',
  port: null,
  type: 'https',
  check_interval: 300,
  timeout: 30,
  expected_status_code: 200,
  expected_keyword: '',
  dns_record_type: 'A',
  ssl_expiry_warn_days: 14,
  notify_on_down: true,
  notify_on_recovery: true,
  project_id: '',
  folder_id: '',
})

const folderForm = ref({
  name: '',
  color: '#6366F1',
})

// Type icons mapping
const typeIcons = {
  http: GlobeAltIcon,
  https: ShieldCheckIcon,
  ping: SignalIcon,
  tcp: ServerIcon,
  udp: ServerIcon,
  minecraft: CubeIcon,
  source: PlayIcon,
  fivem: PlayIcon,
  teamspeak: UsersIcon,
  dns: GlobeAltIcon,
  ssl: ShieldCheckIcon,
}

const intervalOptions = [
  { value: 60, label: '1 Minute' },
  { value: 300, label: '5 Minuten' },
  { value: 600, label: '10 Minuten' },
  { value: 1800, label: '30 Minuten' },
  { value: 3600, label: '1 Stunde' },
]

const dnsRecordTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS']

const folderColors = [
  '#6366F1', '#8B5CF6', '#EC4899', '#EF4444', '#F97316',
  '#EAB308', '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
]

// Computed
const upCount = computed(() => monitors.value.filter(m => m.current_status === 'up').length)
const downCount = computed(() => monitors.value.filter(m => m.current_status === 'down').length)

const currentTypeInfo = computed(() => {
  return monitorTypes.value[form.value.type] || {}
})

const showUrlField = computed(() => ['http', 'https'].includes(form.value.type))
const showHostnameField = computed(() => !showUrlField.value)
const showPortField = computed(() => !['http', 'https', 'ping', 'dns'].includes(form.value.type))
const showHttpFields = computed(() => ['http', 'https'].includes(form.value.type))
const showDnsFields = computed(() => form.value.type === 'dns')
const showSslFields = computed(() => form.value.type === 'ssl')

// Group monitors by folder
const groupedMonitors = computed(() => {
  const groups = {}

  // Add folder groups
  folders.value.forEach(folder => {
    groups[folder.id] = {
      folder,
      monitors: [],
      isCollapsed: folder.is_collapsed,
    }
  })

  // Add unfiled group
  groups['unfiled'] = {
    folder: null,
    monitors: [],
    isCollapsed: false,
  }

  // Distribute monitors
  monitors.value.forEach(monitor => {
    const folderId = monitor.folder_id || 'unfiled'
    if (groups[folderId]) {
      groups[folderId].monitors.push(monitor)
    } else {
      groups['unfiled'].monitors.push(monitor)
    }
  })

  return groups
})

// Watch type changes to set default port
watch(() => form.value.type, (newType) => {
  const typeInfo = monitorTypes.value[newType]
  if (typeInfo?.default_port && !editingMonitor.value) {
    form.value.port = typeInfo.default_port
  }
})

// Watch filters
watch(filters, () => {
  loadData()
}, { deep: true })

// API Calls
onMounted(async () => {
  await Promise.all([loadData(), loadProjects()])
  refreshInterval = setInterval(loadData, 60000)
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})

async function loadData() {
  isLoading.value = monitors.value.length === 0
  try {
    const params = new URLSearchParams()
    if (filters.value.project_id) params.append('project_id', filters.value.project_id)
    if (filters.value.folder_id !== null) params.append('folder_id', filters.value.folder_id)
    if (filters.value.type) params.append('type', filters.value.type)
    if (filters.value.status) params.append('status', filters.value.status)

    const [monitorsRes, statsRes, typesRes] = await Promise.all([
      api.get(`/api/v1/uptime?${params.toString()}`),
      api.get('/api/v1/uptime/stats'),
      api.get('/api/v1/uptime/types'),
    ])
    monitors.value = monitorsRes.data.data?.items || []
    folders.value = monitorsRes.data.data?.folders || []
    stats.value = statsRes.data.data
    monitorTypes.value = typesRes.data.data || {}
  } catch (error) {
    uiStore.showError('Fehler beim Laden')
  } finally {
    isLoading.value = false
  }
}

async function loadProjects() {
  try {
    const response = await api.get('/api/v1/projects')
    projects.value = response.data.data?.items || []
  } catch (error) {
    console.error('Failed to load projects')
  }
}

async function saveMonitor() {
  if (!form.value.name.trim()) {
    uiStore.showError('Name ist erforderlich')
    return
  }

  if (showUrlField.value && !form.value.url.trim()) {
    uiStore.showError('URL ist erforderlich')
    return
  }

  if (showHostnameField.value && !form.value.hostname.trim()) {
    uiStore.showError('Hostname ist erforderlich')
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
  if (!await confirm({ message: `Monitor "${monitor.name}" wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return

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
    if (result.data) {
      monitor.game_server_data = result.data
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

// Folder operations
async function saveFolder() {
  if (!folderForm.value.name.trim()) {
    uiStore.showError('Ordnername ist erforderlich')
    return
  }

  try {
    if (editingFolder.value) {
      await api.put(`/api/v1/uptime/folders/${editingFolder.value.id}`, folderForm.value)
      uiStore.showSuccess('Ordner aktualisiert')
    } else {
      await api.post('/api/v1/uptime/folders', folderForm.value)
      uiStore.showSuccess('Ordner erstellt')
    }
    await loadData()
    showFolderModal.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deleteFolder(folder) {
  if (!await confirm({ message: `Ordner "${folder.name}" löschen? Monitore werden nicht gelöscht.`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/uptime/folders/${folder.id}`)
    await loadData()
    uiStore.showSuccess('Ordner gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function toggleFolderCollapse(folder) {
  folder.is_collapsed = !folder.is_collapsed
  try {
    await api.put(`/api/v1/uptime/folders/${folder.id}`, {
      is_collapsed: folder.is_collapsed,
    })
  } catch (error) {
    // Revert on error
    folder.is_collapsed = !folder.is_collapsed
  }
}

async function moveToFolder(monitorIds, folderId) {
  try {
    await api.post('/api/v1/uptime/move-to-folder', {
      monitor_ids: monitorIds,
      folder_id: folderId || null,
    })
    await loadData()
    selectedMonitorIds.value = []
    uiStore.showSuccess('Monitore verschoben')
  } catch (error) {
    uiStore.showError('Fehler beim Verschieben')
  }
}

// Modal
function openCreateModal() {
  editingMonitor.value = null
  form.value = {
    name: '',
    url: '',
    hostname: '',
    port: null,
    type: 'https',
    check_interval: 300,
    timeout: 30,
    expected_status_code: 200,
    expected_keyword: '',
    dns_record_type: 'A',
    ssl_expiry_warn_days: 14,
    notify_on_down: true,
    notify_on_recovery: true,
    project_id: filters.value.project_id || '',
    folder_id: typeof filters.value.folder_id === 'string' && filters.value.folder_id ? filters.value.folder_id : '',
  }
  showModal.value = true
}

function openEditModal(monitor) {
  editingMonitor.value = monitor
  form.value = {
    name: monitor.name,
    url: monitor.url || '',
    hostname: monitor.hostname || '',
    port: monitor.port,
    type: monitor.type,
    check_interval: monitor.check_interval,
    timeout: monitor.timeout,
    expected_status_code: monitor.expected_status_code,
    expected_keyword: monitor.expected_keyword || '',
    dns_record_type: monitor.dns_record_type || 'A',
    ssl_expiry_warn_days: monitor.ssl_expiry_warn_days || 14,
    notify_on_down: monitor.notify_on_down,
    notify_on_recovery: monitor.notify_on_recovery,
    project_id: monitor.project_id || '',
    folder_id: monitor.folder_id || '',
  }
  showModal.value = true
}

function openFolderModal(folder = null) {
  editingFolder.value = folder
  folderForm.value = {
    name: folder?.name || '',
    color: folder?.color || '#6366F1',
  }
  showFolderModal.value = true
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

function getTypeIcon(type) {
  return typeIcons[type] || SignalIcon
}

function getTypeName(type) {
  return monitorTypes.value[type]?.name || type.toUpperCase()
}

function formatGameServerInfo(monitor) {
  const data = monitor.game_server_data
  if (!data) return null

  if (data.players_online !== undefined) {
    return `${data.players_online}/${data.players_max} Spieler`
  }
  return null
}

function toggleMonitorSelection(monitorId) {
  const index = selectedMonitorIds.value.indexOf(monitorId)
  if (index === -1) {
    selectedMonitorIds.value.push(monitorId)
  } else {
    selectedMonitorIds.value.splice(index, 1)
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">Uptime Monitor</h1>
        <p class="text-gray-400 mt-1">Überwache Websites, Server und Game-Server</p>
      </div>
      <div class="flex gap-2">
        <button @click="openFolderModal()" class="btn-secondary">
          <FolderPlusIcon class="w-5 h-5 mr-2" />
          Ordner
        </button>
        <button @click="openCreateModal" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Neuer Monitor
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
      <div class="flex items-center gap-2 mb-3">
        <FunnelIcon class="w-4 h-4 text-gray-400" />
        <span class="text-sm text-gray-400">Filter</span>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <select v-model="filters.project_id" class="input text-sm">
          <option value="">Alle Projekte</option>
          <option v-for="project in projects" :key="project.id" :value="project.id">
            {{ project.name }}
          </option>
        </select>
        <select v-model="filters.folder_id" class="input text-sm">
          <option :value="null">Alle Ordner</option>
          <option value="">Ohne Ordner</option>
          <option v-for="folder in folders" :key="folder.id" :value="folder.id">
            {{ folder.name }}
          </option>
        </select>
        <select v-model="filters.type" class="input text-sm">
          <option value="">Alle Typen</option>
          <option v-for="(info, type) in monitorTypes" :key="type" :value="type">
            {{ info.name }}
          </option>
        </select>
        <select v-model="filters.status" class="input text-sm">
          <option value="">Alle Status</option>
          <option value="up">Online</option>
          <option value="down">Offline</option>
          <option value="pending">Ausstehend</option>
        </select>
      </div>
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
          <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center">
            <FolderIcon class="w-5 h-5 text-purple-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-white">{{ folders.length }}</p>
            <p class="text-sm text-gray-400">Ordner</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Bulk actions -->
    <div v-if="selectedMonitorIds.length > 0" class="bg-primary-500/10 border border-primary-500/30 rounded-xl p-4 flex items-center justify-between">
      <span class="text-primary-400">{{ selectedMonitorIds.length }} Monitor(e) ausgewählt</span>
      <div class="flex items-center gap-2">
        <select @change="moveToFolder(selectedMonitorIds, $event.target.value); $event.target.value = ''" class="input text-sm w-48">
          <option value="">In Ordner verschieben...</option>
          <option :value="null">Ohne Ordner</option>
          <option v-for="folder in folders" :key="folder.id" :value="folder.id">
            {{ folder.name }}
          </option>
        </select>
        <button @click="selectedMonitorIds = []" class="text-gray-400 hover:text-white">
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Empty state -->
    <div v-else-if="monitors.length === 0 && folders.length === 0" class="card p-12 text-center">
      <SignalIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
      <h3 class="text-lg font-medium text-white mb-2">Keine Monitore</h3>
      <p class="text-gray-400 mb-6">Erstelle deinen ersten Monitor</p>
      <button @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Monitor erstellen
      </button>
    </div>

    <!-- Monitors by folder -->
    <div v-else class="space-y-4">
      <!-- Folders with monitors -->
      <template v-for="(group, folderId) in groupedMonitors" :key="folderId">
        <div v-if="group.monitors.length > 0 || group.folder" class="bg-dark-800 border border-dark-700 rounded-xl overflow-hidden">
          <!-- Folder header -->
          <div
            v-if="group.folder"
            class="flex items-center justify-between px-4 py-3 bg-dark-750 border-b border-dark-700 cursor-pointer hover:bg-dark-700 transition-colors"
            @click="toggleFolderCollapse(group.folder)"
          >
            <div class="flex items-center gap-3">
              <component
                :is="group.isCollapsed ? ChevronRightIcon : ChevronDownIcon"
                class="w-4 h-4 text-gray-400"
              />
              <div
                class="w-3 h-3 rounded-full"
                :style="{ backgroundColor: group.folder.color }"
              ></div>
              <span class="font-medium text-white">{{ group.folder.name }}</span>
              <span class="text-sm text-gray-500">({{ group.monitors.length }})</span>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click.stop="openFolderModal(group.folder)"
                class="p-1 text-gray-400 hover:text-white"
              >
                <PencilIcon class="w-4 h-4" />
              </button>
              <button
                @click.stop="deleteFolder(group.folder)"
                class="p-1 text-gray-400 hover:text-red-400"
              >
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Unfiled header -->
          <div
            v-else
            class="flex items-center px-4 py-3 bg-dark-750 border-b border-dark-700"
          >
            <FolderIcon class="w-4 h-4 text-gray-500 mr-2" />
            <span class="text-gray-400">Ohne Ordner</span>
            <span class="text-sm text-gray-500 ml-2">({{ group.monitors.length }})</span>
          </div>

          <!-- Monitors list -->
          <div v-show="!group.isCollapsed" class="divide-y divide-dark-700">
            <div
              v-for="monitor in group.monitors"
              :key="monitor.id"
              class="flex items-center gap-4 p-4 hover:bg-dark-750 transition-colors group"
            >
              <!-- Checkbox -->
              <input
                type="checkbox"
                :checked="selectedMonitorIds.includes(monitor.id)"
                @change="toggleMonitorSelection(monitor.id)"
                class="checkbox"
              />

              <!-- Status indicator -->
              <div
                class="w-3 h-3 rounded-full animate-pulse"
                :class="getStatusBg(monitor.current_status)"
              ></div>

              <!-- Type icon -->
              <div class="w-8 h-8 bg-dark-700 rounded-lg flex items-center justify-center">
                <component :is="getTypeIcon(monitor.type)" class="w-4 h-4 text-gray-400" />
              </div>

              <!-- Info -->
              <div class="flex-1 min-w-0 cursor-pointer" @click="openDetail(monitor)">
                <div class="flex items-center gap-2">
                  <h3 class="font-medium text-white">{{ monitor.name }}</h3>
                  <span class="px-2 py-0.5 text-xs rounded bg-dark-600 text-gray-400">
                    {{ getTypeName(monitor.type) }}
                  </span>
                  <span
                    v-if="monitor.project_name"
                    class="px-2 py-0.5 text-xs rounded"
                    :style="{ backgroundColor: monitor.project_color + '20', color: monitor.project_color }"
                  >
                    {{ monitor.project_name }}
                  </span>
                  <span v-if="monitor.is_paused" class="px-2 py-0.5 text-xs rounded bg-yellow-500/20 text-yellow-400">
                    Pausiert
                  </span>
                </div>
                <div class="flex items-center gap-3 text-sm text-gray-500">
                  <span class="truncate">{{ monitor.hostname || monitor.url }}</span>
                  <span v-if="monitor.port" class="text-gray-600">:{{ monitor.port }}</span>
                  <span v-if="formatGameServerInfo(monitor)" class="text-primary-400">
                    {{ formatGameServerInfo(monitor) }}
                  </span>
                </div>
              </div>

              <!-- Uptime bars -->
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
      </template>
    </div>

    <!-- Create/Edit Monitor Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-lg border border-dark-700 max-h-[90vh] overflow-y-auto">
          <div class="p-4 border-b border-dark-700 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">
              {{ editingMonitor ? 'Monitor bearbeiten' : 'Neuer Monitor' }}
            </h2>
            <button @click="showModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="saveMonitor" class="p-4 space-y-4">
            <!-- Name -->
            <div>
              <label class="label">Name *</label>
              <input v-model="form.name" type="text" class="input" placeholder="Mein Server" required />
            </div>

            <!-- Project & Folder -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">Projekt</label>
                <select v-model="form.project_id" class="input">
                  <option value="">Kein Projekt</option>
                  <option v-for="project in projects" :key="project.id" :value="project.id">
                    {{ project.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="label">Ordner</label>
                <select v-model="form.folder_id" class="input">
                  <option value="">Kein Ordner</option>
                  <option v-for="folder in folders" :key="folder.id" :value="folder.id">
                    {{ folder.name }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Type Selection -->
            <div>
              <label class="label">Typ *</label>
              <div class="grid grid-cols-3 gap-2">
                <button
                  v-for="(info, type) in monitorTypes"
                  :key="type"
                  type="button"
                  @click="form.type = type"
                  class="p-3 rounded-lg border text-left transition-colors"
                  :class="form.type === type
                    ? 'border-primary-500 bg-primary-500/10 text-white'
                    : 'border-dark-600 bg-dark-700 text-gray-400 hover:border-dark-500'"
                >
                  <component :is="getTypeIcon(type)" class="w-5 h-5 mb-1" />
                  <p class="text-sm font-medium">{{ info.name }}</p>
                </button>
              </div>
              <p v-if="currentTypeInfo.description" class="text-xs text-gray-500 mt-2">
                {{ currentTypeInfo.description }}
              </p>
            </div>

            <!-- URL (for HTTP/HTTPS) -->
            <div v-if="showUrlField">
              <label class="label">URL *</label>
              <input v-model="form.url" type="url" class="input" placeholder="https://example.com" required />
            </div>

            <!-- Hostname (for other types) -->
            <div v-if="showHostnameField">
              <label class="label">Hostname / IP *</label>
              <input v-model="form.hostname" type="text" class="input" placeholder="play.example.com" required />
            </div>

            <!-- Port -->
            <div v-if="showPortField" class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">Port</label>
                <input v-model.number="form.port" type="number" class="input" :placeholder="currentTypeInfo.default_port || '25565'" />
              </div>
              <div>
                <label class="label">Timeout (Sek.)</label>
                <input v-model.number="form.timeout" type="number" class="input" min="5" max="60" />
              </div>
            </div>

            <!-- HTTP-specific fields -->
            <div v-if="showHttpFields" class="space-y-4">
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
            </div>

            <!-- DNS-specific fields -->
            <div v-if="showDnsFields">
              <label class="label">DNS Record Typ</label>
              <select v-model="form.dns_record_type" class="input">
                <option v-for="type in dnsRecordTypes" :key="type" :value="type">{{ type }}</option>
              </select>
            </div>

            <!-- SSL-specific fields -->
            <div v-if="showSslFields" class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">Port</label>
                <input v-model.number="form.port" type="number" class="input" placeholder="443" />
              </div>
              <div>
                <label class="label">Warnung (Tage vor Ablauf)</label>
                <input v-model.number="form.ssl_expiry_warn_days" type="number" class="input" min="1" max="90" />
              </div>
            </div>

            <!-- Check Interval -->
            <div>
              <label class="label">Check-Intervall</label>
              <select v-model="form.check_interval" class="input">
                <option v-for="opt in intervalOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <!-- Notifications -->
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

    <!-- Folder Modal -->
    <Teleport to="body">
      <div
        v-if="showFolderModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-sm border border-dark-700">
          <div class="p-4 border-b border-dark-700 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">
              {{ editingFolder ? 'Ordner bearbeiten' : 'Neuer Ordner' }}
            </h2>
            <button @click="showFolderModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="saveFolder" class="p-4 space-y-4">
            <div>
              <label class="label">Name *</label>
              <input v-model="folderForm.name" type="text" class="input" placeholder="Mein Ordner" required />
            </div>

            <div>
              <label class="label">Farbe</label>
              <div class="flex gap-2 flex-wrap">
                <button
                  v-for="color in folderColors"
                  :key="color"
                  type="button"
                  @click="folderForm.color = color"
                  class="w-8 h-8 rounded-lg transition-transform"
                  :class="{ 'ring-2 ring-white scale-110': folderForm.color === color }"
                  :style="{ backgroundColor: color }"
                ></button>
              </div>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="showFolderModal = false" class="btn-secondary flex-1">
                Abbrechen
              </button>
              <button type="submit" class="btn-primary flex-1">
                {{ editingFolder ? 'Speichern' : 'Erstellen' }}
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
        
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-2xl border border-dark-700 max-h-[90vh] overflow-y-auto">
          <div class="p-4 border-b border-dark-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div
                class="w-3 h-3 rounded-full"
                :class="getStatusBg(selectedMonitor.current_status)"
              ></div>
              <component :is="getTypeIcon(selectedMonitor.type)" class="w-5 h-5 text-gray-400" />
              <h2 class="text-lg font-semibold text-white">{{ selectedMonitor.name }}</h2>
              <span class="px-2 py-0.5 text-xs rounded bg-dark-600 text-gray-400">
                {{ getTypeName(selectedMonitor.type) }}
              </span>
            </div>
            <button @click="showDetailModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-6">
            <!-- Game Server Data -->
            <div v-if="selectedMonitor.game_server_data && Object.keys(selectedMonitor.game_server_data).length" class="bg-dark-700 rounded-lg p-4">
              <h3 class="text-sm font-medium text-gray-400 mb-3">Server Info</h3>
              <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div v-if="selectedMonitor.game_server_data.players_online !== undefined">
                  <p class="text-xs text-gray-500">Spieler</p>
                  <p class="text-lg font-bold text-white">
                    {{ selectedMonitor.game_server_data.players_online }}/{{ selectedMonitor.game_server_data.players_max }}
                  </p>
                </div>
                <div v-if="selectedMonitor.game_server_data.map">
                  <p class="text-xs text-gray-500">Map</p>
                  <p class="text-sm text-white">{{ selectedMonitor.game_server_data.map }}</p>
                </div>
                <div v-if="selectedMonitor.game_server_data.version">
                  <p class="text-xs text-gray-500">Version</p>
                  <p class="text-sm text-white">{{ selectedMonitor.game_server_data.version }}</p>
                </div>
                <div v-if="selectedMonitor.game_server_data.name">
                  <p class="text-xs text-gray-500">Server Name</p>
                  <p class="text-sm text-white truncate">{{ selectedMonitor.game_server_data.name }}</p>
                </div>
                <div v-if="selectedMonitor.game_server_data.game">
                  <p class="text-xs text-gray-500">Spiel</p>
                  <p class="text-sm text-white">{{ selectedMonitor.game_server_data.game }}</p>
                </div>
                <div v-if="selectedMonitor.game_server_data.motd">
                  <p class="text-xs text-gray-500">MOTD</p>
                  <p class="text-sm text-white truncate">{{ selectedMonitor.game_server_data.motd }}</p>
                </div>
              </div>
            </div>

            <!-- SSL Certificate Info -->
            <div v-if="selectedMonitor.type === 'ssl' && selectedMonitor.game_server_data" class="bg-dark-700 rounded-lg p-4">
              <h3 class="text-sm font-medium text-gray-400 mb-3">SSL Zertifikat</h3>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <p class="text-xs text-gray-500">Common Name</p>
                  <p class="text-sm text-white">{{ selectedMonitor.game_server_data.common_name }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Aussteller</p>
                  <p class="text-sm text-white">{{ selectedMonitor.game_server_data.issuer }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Gültig bis</p>
                  <p class="text-sm" :class="selectedMonitor.game_server_data.days_until_expiry < 14 ? 'text-yellow-400' : 'text-white'">
                    {{ selectedMonitor.game_server_data.valid_to }}
                  </p>
                </div>
                <div>
                  <p class="text-xs text-gray-500">Tage verbleibend</p>
                  <p class="text-lg font-bold" :class="selectedMonitor.game_server_data.days_until_expiry < 14 ? 'text-yellow-400' : 'text-green-400'">
                    {{ selectedMonitor.game_server_data.days_until_expiry }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4">
              <div v-for="(stat, period) in selectedMonitor.stats" :key="period" class="bg-dark-700 rounded-lg p-3">
                <p class="text-sm text-gray-400">{{ period }}</p>
                <p class="text-xl font-bold text-white">{{ stat.percentage }}%</p>
                <p class="text-xs text-gray-500">Ø {{ stat.avg_response_time || '-' }}ms</p>
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
