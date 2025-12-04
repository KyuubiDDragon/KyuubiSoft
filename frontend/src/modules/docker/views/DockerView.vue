<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import api from '@/core/api/axios'
import {
  ArrowPathIcon,
  PlayIcon,
  StopIcon,
  DocumentTextIcon,
  ChartBarIcon,
  CubeIcon,
  ServerIcon,
  CircleStackIcon,
  GlobeAltIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon,
  XCircleIcon,
  InformationCircleIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  ServerStackIcon,
  ComputerDesktopIcon,
  Cog6ToothIcon,
  RectangleStackIcon,
} from '@heroicons/vue/24/outline'

// State
const activeTab = ref('containers')
const loading = ref(false)
const error = ref(null)
const dockerAvailable = ref(null)
const dockerVersion = ref('')

// Host Selection
const dockerHosts = ref([])
const selectedHostId = ref(null)
const currentHostName = ref('Lokal')
const showHostDropdown = ref(false)

// Data
const containers = ref([])
const stacks = ref([])
const standaloneContainers = ref([])
const showGrouped = ref(true)
const expandedStacks = ref({})
const images = ref([])
const networks = ref([])
const volumes = ref([])
const systemInfo = ref(null)

// Container Details Modal
const selectedContainer = ref(null)
const containerDetails = ref(null)
const containerLogs = ref('')
const containerStats = ref(null)
const detailsTab = ref('info')
const loadingDetails = ref(false)

// Auto-refresh
let refreshInterval = null
const autoRefresh = ref(true)

// Computed
const runningContainers = computed(() =>
  containers.value.filter(c => c.state === 'running').length
)

const stoppedContainers = computed(() =>
  containers.value.filter(c => c.state !== 'running').length
)

// Helper to get host query params
function getHostParams() {
  return selectedHostId.value ? { host_id: selectedHostId.value } : {}
}

// Methods
async function loadHosts() {
  try {
    const response = await api.get('/api/v1/docker/hosts')
    dockerHosts.value = response.data.data.hosts || []

    // Find default host
    const defaultHost = dockerHosts.value.find(h => h.is_default)
    if (defaultHost) {
      selectedHostId.value = defaultHost.id
      currentHostName.value = defaultHost.name
    }
  } catch (e) {
    console.error('Failed to load Docker hosts:', e)
  }
}

async function selectHost(host) {
  if (host) {
    selectedHostId.value = host.id
    currentHostName.value = host.name
  } else {
    selectedHostId.value = null
    currentHostName.value = 'Lokal'
  }
  showHostDropdown.value = false

  // Re-check status and reload data for new host
  await checkDockerStatus()
  if (dockerAvailable.value) {
    await refreshData()
  }
}

async function checkDockerStatus() {
  try {
    const response = await api.get('/api/v1/docker/status', { params: getHostParams() })
    const data = response.data.data || response.data
    dockerAvailable.value = data.available
    dockerVersion.value = data.version || ''
    currentHostName.value = data.host_name || currentHostName.value
    if (!data.available) {
      error.value = data.error || 'Docker is not available'
    }
  } catch (e) {
    dockerAvailable.value = false
    error.value = 'Failed to connect to Docker'
  }
}

async function loadContainers() {
  try {
    const response = await api.get('/api/v1/docker/containers', {
      params: { all: 'true', grouped: 'true', ...getHostParams() }
    })
    const data = response.data.data || response.data

    // Store grouped data
    stacks.value = data.stacks || []
    standaloneContainers.value = data.standalone || []

    // Flatten for total count and backward compatibility
    const allContainers = []
    stacks.value.forEach(stack => {
      allContainers.push(...stack.containers)
    })
    allContainers.push(...standaloneContainers.value)
    containers.value = allContainers
  } catch (e) {
    console.error('Failed to load containers:', e)
  }
}

async function loadImages() {
  try {
    const response = await api.get('/api/v1/docker/images', { params: getHostParams() })
    const data = response.data.data || response.data
    images.value = data.images || []
  } catch (e) {
    console.error('Failed to load images:', e)
  }
}

async function loadNetworks() {
  try {
    const response = await api.get('/api/v1/docker/networks', { params: getHostParams() })
    const data = response.data.data || response.data
    networks.value = data.networks || []
  } catch (e) {
    console.error('Failed to load networks:', e)
  }
}

async function loadVolumes() {
  try {
    const response = await api.get('/api/v1/docker/volumes', { params: getHostParams() })
    const data = response.data.data || response.data
    volumes.value = data.volumes || []
  } catch (e) {
    console.error('Failed to load volumes:', e)
  }
}

async function loadSystemInfo() {
  try {
    const response = await api.get('/api/v1/docker/system', { params: getHostParams() })
    systemInfo.value = response.data.data || response.data
  } catch (e) {
    console.error('Failed to load system info:', e)
  }
}

async function refreshData() {
  loading.value = true
  error.value = null

  try {
    await Promise.all([
      loadContainers(),
      loadImages(),
      loadNetworks(),
      loadVolumes(),
      loadSystemInfo(),
    ])
  } catch (e) {
    error.value = 'Failed to load Docker data'
  } finally {
    loading.value = false
  }
}

async function startContainer(container) {
  try {
    await api.post(`/api/v1/docker/containers/${container.id}/start`, null, { params: getHostParams() })
    await loadContainers()
  } catch (e) {
    error.value = 'Failed to start container'
  }
}

async function stopContainer(container) {
  try {
    await api.post(`/api/v1/docker/containers/${container.id}/stop`, null, { params: getHostParams() })
    await loadContainers()
  } catch (e) {
    error.value = 'Failed to stop container'
  }
}

async function restartContainer(container) {
  try {
    await api.post(`/api/v1/docker/containers/${container.id}/restart`, null, { params: getHostParams() })
    await loadContainers()
  } catch (e) {
    error.value = 'Failed to restart container'
  }
}

async function showContainerDetails(container) {
  selectedContainer.value = container
  detailsTab.value = 'info'
  loadingDetails.value = true
  containerDetails.value = null
  containerLogs.value = ''
  containerStats.value = null

  try {
    const [detailsRes, logsRes] = await Promise.all([
      api.get(`/api/v1/docker/containers/${container.id}`, { params: getHostParams() }),
      api.get(`/api/v1/docker/containers/${container.id}/logs`, { params: { tail: 100, ...getHostParams() } }),
    ])
    containerDetails.value = detailsRes.data.data || detailsRes.data
    const logsData = logsRes.data.data || logsRes.data
    containerLogs.value = logsData.logs || ''

    // Only load stats if container is running
    if (container.state === 'running') {
      const statsRes = await api.get(`/api/v1/docker/containers/${container.id}/stats`, { params: getHostParams() })
      containerStats.value = statsRes.data.data || statsRes.data
    }
  } catch (e) {
    console.error('Failed to load container details:', e)
  } finally {
    loadingDetails.value = false
  }
}

async function refreshLogs() {
  if (!selectedContainer.value) return
  try {
    const response = await api.get(`/api/v1/docker/containers/${selectedContainer.value.id}/logs`, { params: { tail: 100, ...getHostParams() } })
    const data = response.data.data || response.data
    containerLogs.value = data.logs || ''
  } catch (e) {
    console.error('Failed to refresh logs:', e)
  }
}

async function refreshStats() {
  if (!selectedContainer.value || selectedContainer.value.state !== 'running') return
  try {
    const response = await api.get(`/api/v1/docker/containers/${selectedContainer.value.id}/stats`, { params: getHostParams() })
    containerStats.value = response.data.data || response.data
  } catch (e) {
    console.error('Failed to refresh stats:', e)
  }
}

function closeDetails() {
  selectedContainer.value = null
  containerDetails.value = null
  containerLogs.value = ''
  containerStats.value = null
}

function getStateColor(state) {
  switch (state) {
    case 'running': return 'text-green-400'
    case 'exited': return 'text-red-400'
    case 'paused': return 'text-yellow-400'
    case 'restarting': return 'text-blue-400'
    default: return 'text-gray-400'
  }
}

function toggleStack(stackName) {
  expandedStacks.value[stackName] = !expandedStacks.value[stackName]
}

function isStackExpanded(stackName) {
  return expandedStacks.value[stackName] ?? true // Expanded by default
}

function getStateIcon(state) {
  switch (state) {
    case 'running': return CheckCircleIcon
    case 'exited': return XCircleIcon
    case 'paused': return ExclamationTriangleIcon
    default: return InformationCircleIcon
  }
}

function formatBytes(bytes) {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

// Lifecycle
onMounted(async () => {
  // Load available hosts first
  await loadHosts()

  // Check status for default/selected host
  await checkDockerStatus()
  if (dockerAvailable.value) {
    await refreshData()
    if (autoRefresh.value) {
      refreshInterval = setInterval(refreshData, 10000)
    }
  }
})

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2">
          <CubeIcon class="w-7 h-7" />
          Docker Manager
        </h1>
        <p class="text-gray-400 mt-1">
          <template v-if="dockerAvailable">
            Docker {{ dockerVersion }} - {{ containers.length }} Container, {{ images.length }} Images
          </template>
          <template v-else>
            Docker nicht verfügbar
          </template>
        </p>
      </div>

      <div class="flex items-center gap-3">
        <!-- Host Selector -->
        <div class="relative">
          <button
            @click="showHostDropdown = !showHostDropdown"
            class="flex items-center gap-2 px-3 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg border border-dark-600 transition-colors"
          >
            <ServerStackIcon class="w-4 h-4 text-gray-400" />
            <span class="text-sm text-white">{{ currentHostName }}</span>
            <ChevronDownIcon class="w-4 h-4 text-gray-400" />
          </button>

          <!-- Host Dropdown -->
          <div
            v-if="showHostDropdown"
            class="absolute right-0 mt-1 py-1 bg-dark-700 border border-dark-600 rounded-lg shadow-xl z-50 min-w-48"
          >
            <!-- Local/Default Option -->
            <button
              @click="selectHost(null)"
              class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-dark-600 transition-colors"
              :class="!selectedHostId ? 'bg-dark-600' : ''"
            >
              <ComputerDesktopIcon class="w-4 h-4 text-blue-400" />
              <span class="text-sm text-gray-300">Lokal (Standard)</span>
            </button>

            <template v-if="dockerHosts.length > 0">
              <div class="h-px bg-dark-600 my-1"></div>

              <button
                v-for="host in dockerHosts"
                :key="host.id"
                @click="selectHost(host)"
                class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-dark-600 transition-colors"
                :class="selectedHostId === host.id ? 'bg-dark-600' : ''"
              >
                <ComputerDesktopIcon
                  v-if="host.type === 'socket'"
                  class="w-4 h-4 text-blue-400"
                />
                <GlobeAltIcon v-else class="w-4 h-4 text-purple-400" />
                <div class="flex-1 min-w-0">
                  <span class="text-sm text-gray-300 block truncate">{{ host.name }}</span>
                  <span v-if="host.project_name" class="text-xs text-gray-500">{{ host.project_name }}</span>
                </div>
                <CheckCircleIcon
                  v-if="host.connection_status === 'connected'"
                  class="w-4 h-4 text-green-400 flex-shrink-0"
                />
                <XCircleIcon
                  v-else-if="host.connection_status === 'error'"
                  class="w-4 h-4 text-red-400 flex-shrink-0"
                />
              </button>
            </template>

            <div class="h-px bg-dark-600 my-1"></div>

            <!-- Manage Hosts Link -->
            <a
              href="/docker/hosts"
              class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-dark-600 transition-colors"
            >
              <Cog6ToothIcon class="w-4 h-4 text-gray-400" />
              <span class="text-sm text-gray-400">Hosts verwalten</span>
            </a>
          </div>

          <!-- Backdrop -->
          <div
            v-if="showHostDropdown"
            class="fixed inset-0 z-40"
            @click="showHostDropdown = false"
          ></div>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-400">
          <input v-model="autoRefresh" type="checkbox" class="rounded border-dark-500 bg-dark-700" />
          Auto-Refresh
        </label>
        <button @click="refreshData" :disabled="loading" class="btn-secondary">
          <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': loading }" />
          Aktualisieren
        </button>
      </div>
    </div>

    <!-- Docker Not Available Warning -->
    <div v-if="dockerAvailable === false" class="space-y-6">
      <div class="card p-6 border-yellow-500/30 bg-yellow-900/10">
        <div class="flex items-start gap-4">
          <ExclamationTriangleIcon class="w-8 h-8 text-yellow-400 flex-shrink-0" />
          <div class="flex-1">
            <h3 class="text-lg font-semibold text-yellow-400">Docker-Daemon nicht erreichbar</h3>
            <p class="text-gray-400 mt-1">{{ error || 'Der Docker-Daemon ist nicht erreichbar.' }}</p>

            <div class="mt-4 p-4 bg-dark-800 rounded-lg">
              <p class="text-sm text-gray-300 font-medium mb-2">Mögliche Lösungen:</p>
              <ul class="text-sm text-gray-400 space-y-1 list-disc list-inside">
                <li>Prüfe ob Docker installiert ist: <code class="bg-dark-700 px-1 rounded">docker --version</code></li>
                <li>Starte den Docker-Daemon: <code class="bg-dark-700 px-1 rounded">sudo systemctl start docker</code></li>
                <li>Prüfe Berechtigungen: <code class="bg-dark-700 px-1 rounded">sudo usermod -aG docker $USER</code></li>
                <li>Bei Docker Desktop: Stelle sicher, dass die Anwendung läuft</li>
              </ul>
            </div>

            <button @click="checkDockerStatus" class="btn-secondary mt-4">
              <ArrowPathIcon class="w-4 h-4" />
              Erneut prüfen
            </button>
          </div>
        </div>
      </div>

      <!-- Generator Tools (work without Docker) -->
      <div>
        <h2 class="text-lg font-semibold text-white mb-4">Docker Generator-Tools</h2>
        <p class="text-gray-400 text-sm mb-4">Diese Tools funktionieren auch ohne laufenden Docker-Daemon:</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <a href="/toolbox" class="card p-4 hover:bg-dark-700 hover:border-primary-500 transition-all group">
            <div class="flex items-center gap-3">
              <span class="text-2xl">📄</span>
              <div>
                <h3 class="font-medium text-white group-hover:text-primary-400">Dockerfile Generator</h3>
                <p class="text-xs text-gray-400">Dockerfiles erstellen</p>
              </div>
            </div>
          </a>
          <a href="/toolbox" class="card p-4 hover:bg-dark-700 hover:border-primary-500 transition-all group">
            <div class="flex items-center gap-3">
              <span class="text-2xl">🔧</span>
              <div>
                <h3 class="font-medium text-white group-hover:text-primary-400">Compose Builder</h3>
                <p class="text-xs text-gray-400">docker-compose.yml erstellen</p>
              </div>
            </div>
          </a>
          <a href="/toolbox" class="card p-4 hover:bg-dark-700 hover:border-primary-500 transition-all group">
            <div class="flex items-center gap-3">
              <span class="text-2xl">⚙️</span>
              <div>
                <h3 class="font-medium text-white group-hover:text-primary-400">Command Builder</h3>
                <p class="text-xs text-gray-400">docker run Befehle</p>
              </div>
            </div>
          </a>
          <a href="/toolbox" class="card p-4 hover:bg-dark-700 hover:border-primary-500 transition-all group">
            <div class="flex items-center gap-3">
              <span class="text-2xl">🚫</span>
              <div>
                <h3 class="font-medium text-white group-hover:text-primary-400">.dockerignore</h3>
                <p class="text-xs text-gray-400">Ignore-Dateien generieren</p>
              </div>
            </div>
          </a>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <template v-else-if="dockerAvailable">
      <!-- Stats Overview -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="card p-4">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-green-500/20 rounded-lg">
              <CheckCircleIcon class="w-5 h-5 text-green-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-white">{{ runningContainers }}</p>
              <p class="text-xs text-gray-400">Running</p>
            </div>
          </div>
        </div>
        <div class="card p-4">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-red-500/20 rounded-lg">
              <XCircleIcon class="w-5 h-5 text-red-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-white">{{ stoppedContainers }}</p>
              <p class="text-xs text-gray-400">Stopped</p>
            </div>
          </div>
        </div>
        <div class="card p-4">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-blue-500/20 rounded-lg">
              <ServerIcon class="w-5 h-5 text-blue-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-white">{{ images.length }}</p>
              <p class="text-xs text-gray-400">Images</p>
            </div>
          </div>
        </div>
        <div class="card p-4">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-purple-500/20 rounded-lg">
              <CircleStackIcon class="w-5 h-5 text-purple-400" />
            </div>
            <div>
              <p class="text-2xl font-bold text-white">{{ volumes.length }}</p>
              <p class="text-xs text-gray-400">Volumes</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b border-dark-600">
        <nav class="flex gap-4">
          <button
            v-for="tab in [
              { id: 'containers', label: 'Container', icon: CubeIcon },
              { id: 'images', label: 'Images', icon: ServerIcon },
              { id: 'networks', label: 'Netzwerke', icon: GlobeAltIcon },
              { id: 'volumes', label: 'Volumes', icon: CircleStackIcon },
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

      <!-- Containers Tab -->
      <div v-if="activeTab === 'containers'" class="space-y-4">
        <div v-if="containers.length === 0" class="card p-8 text-center text-gray-400">
          Keine Container gefunden
        </div>

        <template v-else>
          <!-- Stacks -->
          <div v-for="stack in stacks" :key="stack.name" class="card overflow-hidden">
            <!-- Stack Header -->
            <div
              class="flex items-center justify-between p-4 bg-dark-700 cursor-pointer hover:bg-dark-600 transition-colors"
              @click="toggleStack(stack.name)"
            >
              <div class="flex items-center gap-3">
                <ChevronRightIcon
                  class="w-4 h-4 text-gray-400 transition-transform"
                  :class="{ 'rotate-90': isStackExpanded(stack.name) }"
                />
                <RectangleStackIcon class="w-5 h-5 text-primary-400" />
                <div>
                  <h3 class="font-medium text-white">{{ stack.name }}</h3>
                  <p class="text-xs text-gray-400">
                    {{ stack.running }}/{{ stack.total }} Container aktiv
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <span
                  class="px-2 py-1 text-xs rounded-full"
                  :class="stack.running === stack.total ? 'bg-green-500/20 text-green-400' : 'bg-yellow-500/20 text-yellow-400'"
                >
                  {{ stack.running === stack.total ? 'Healthy' : 'Partial' }}
                </span>
              </div>
            </div>

            <!-- Stack Containers -->
            <div v-if="isStackExpanded(stack.name)" class="divide-y divide-dark-600">
              <div
                v-for="container in stack.containers"
                :key="container.id"
                class="p-4 pl-12 hover:bg-dark-700 transition-colors cursor-pointer"
                @click="showContainerDetails(container)"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-4">
                    <component
                      :is="getStateIcon(container.state)"
                      class="w-5 h-5"
                      :class="getStateColor(container.state)"
                    />
                    <div>
                      <h3 class="font-medium text-white">
                        {{ container.service || container.name }}
                      </h3>
                      <p class="text-sm text-gray-400">{{ container.image }}</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-4">
                    <div class="text-right hidden sm:block">
                      <p class="text-sm text-gray-300">{{ container.status }}</p>
                      <p class="text-xs text-gray-500">{{ container.ports || 'Keine Ports' }}</p>
                    </div>
                    <div class="flex items-center gap-2" @click.stop>
                      <button
                        v-if="container.state !== 'running'"
                        @click="startContainer(container)"
                        class="btn-icon text-green-400 hover:bg-green-500/20"
                        title="Starten"
                      >
                        <PlayIcon class="w-4 h-4" />
                      </button>
                      <button
                        v-if="container.state === 'running'"
                        @click="stopContainer(container)"
                        class="btn-icon text-red-400 hover:bg-red-500/20"
                        title="Stoppen"
                      >
                        <StopIcon class="w-4 h-4" />
                      </button>
                      <button
                        @click="restartContainer(container)"
                        class="btn-icon text-blue-400 hover:bg-blue-500/20"
                        title="Neustarten"
                      >
                        <ArrowPathIcon class="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Standalone Containers -->
          <div v-if="standaloneContainers.length > 0" class="space-y-2">
            <h3 class="text-sm font-medium text-gray-400 px-1">Einzelne Container</h3>
            <div
              v-for="container in standaloneContainers"
              :key="container.id"
              class="card p-4 hover:bg-dark-700 transition-colors cursor-pointer"
              @click="showContainerDetails(container)"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                  <component
                    :is="getStateIcon(container.state)"
                    class="w-5 h-5"
                    :class="getStateColor(container.state)"
                  />
                  <div>
                    <h3 class="font-medium text-white">{{ container.name }}</h3>
                    <p class="text-sm text-gray-400">{{ container.image }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-4">
                  <div class="text-right hidden sm:block">
                    <p class="text-sm text-gray-300">{{ container.status }}</p>
                    <p class="text-xs text-gray-500">{{ container.ports || 'Keine Ports' }}</p>
                  </div>
                  <div class="flex items-center gap-2" @click.stop>
                    <button
                      v-if="container.state !== 'running'"
                      @click="startContainer(container)"
                      class="btn-icon text-green-400 hover:bg-green-500/20"
                      title="Starten"
                    >
                      <PlayIcon class="w-4 h-4" />
                    </button>
                    <button
                      v-if="container.state === 'running'"
                      @click="stopContainer(container)"
                      class="btn-icon text-red-400 hover:bg-red-500/20"
                      title="Stoppen"
                    >
                      <StopIcon class="w-4 h-4" />
                    </button>
                    <button
                      @click="restartContainer(container)"
                      class="btn-icon text-blue-400 hover:bg-blue-500/20"
                      title="Neustarten"
                    >
                      <ArrowPathIcon class="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Images Tab -->
      <div v-if="activeTab === 'images'" class="space-y-4">
        <div v-if="images.length === 0" class="card p-8 text-center text-gray-400">
          Keine Images gefunden
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="text-left text-sm text-gray-400 border-b border-dark-600">
                <th class="pb-3 font-medium">Repository</th>
                <th class="pb-3 font-medium">Tag</th>
                <th class="pb-3 font-medium">ID</th>
                <th class="pb-3 font-medium">Erstellt</th>
                <th class="pb-3 font-medium">Größe</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="image in images" :key="image.id" class="border-b border-dark-700">
                <td class="py-3 text-white">{{ image.repository }}</td>
                <td class="py-3">
                  <span class="px-2 py-1 bg-dark-600 rounded text-sm">{{ image.tag }}</span>
                </td>
                <td class="py-3 text-gray-400 font-mono text-sm">{{ image.id.substring(0, 12) }}</td>
                <td class="py-3 text-gray-400">{{ image.created }}</td>
                <td class="py-3 text-gray-400">{{ image.size }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Networks Tab -->
      <div v-if="activeTab === 'networks'" class="space-y-4">
        <div v-if="networks.length === 0" class="card p-8 text-center text-gray-400">
          Keine Netzwerke gefunden
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="text-left text-sm text-gray-400 border-b border-dark-600">
                <th class="pb-3 font-medium">Name</th>
                <th class="pb-3 font-medium">ID</th>
                <th class="pb-3 font-medium">Driver</th>
                <th class="pb-3 font-medium">Scope</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="network in networks" :key="network.id" class="border-b border-dark-700">
                <td class="py-3 text-white">{{ network.name }}</td>
                <td class="py-3 text-gray-400 font-mono text-sm">{{ network.id.substring(0, 12) }}</td>
                <td class="py-3">
                  <span class="px-2 py-1 bg-dark-600 rounded text-sm">{{ network.driver }}</span>
                </td>
                <td class="py-3 text-gray-400">{{ network.scope }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Volumes Tab -->
      <div v-if="activeTab === 'volumes'" class="space-y-4">
        <div v-if="volumes.length === 0" class="card p-8 text-center text-gray-400">
          Keine Volumes gefunden
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="text-left text-sm text-gray-400 border-b border-dark-600">
                <th class="pb-3 font-medium">Name</th>
                <th class="pb-3 font-medium">Driver</th>
                <th class="pb-3 font-medium">Scope</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="volume in volumes" :key="volume.name" class="border-b border-dark-700">
                <td class="py-3 text-white font-mono text-sm">{{ volume.name }}</td>
                <td class="py-3">
                  <span class="px-2 py-1 bg-dark-600 rounded text-sm">{{ volume.driver }}</span>
                </td>
                <td class="py-3 text-gray-400">{{ volume.scope }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Container Details Modal -->
    <Teleport to="body">
      <div
        v-if="selectedContainer"
        class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4"
        @click.self="closeDetails"
      >
        <div class="bg-dark-800 rounded-xl border border-dark-600 w-full max-w-4xl max-h-[90vh] flex flex-col">
          <!-- Modal Header -->
          <div class="flex items-center justify-between p-4 border-b border-dark-600">
            <div class="flex items-center gap-3">
              <component
                :is="getStateIcon(selectedContainer.state)"
                class="w-5 h-5"
                :class="getStateColor(selectedContainer.state)"
              />
              <div>
                <h2 class="text-lg font-semibold text-white">{{ selectedContainer.name }}</h2>
                <p class="text-sm text-gray-400">{{ selectedContainer.image }}</p>
              </div>
            </div>
            <button @click="closeDetails" class="btn-icon">
              <XCircleIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Details Tabs -->
          <div class="border-b border-dark-600 px-4">
            <nav class="flex gap-4">
              <button
                v-for="tab in [
                  { id: 'info', label: 'Info' },
                  { id: 'logs', label: 'Logs' },
                  { id: 'stats', label: 'Stats' },
                ]"
                :key="tab.id"
                @click="detailsTab = tab.id"
                :class="[
                  'px-3 py-2 border-b-2 transition-colors text-sm',
                  detailsTab === tab.id
                    ? 'border-primary-500 text-primary-400'
                    : 'border-transparent text-gray-400 hover:text-white'
                ]"
              >
                {{ tab.label }}
              </button>
            </nav>
          </div>

          <!-- Modal Body -->
          <div class="flex-1 overflow-auto p-4">
            <div v-if="loadingDetails" class="flex items-center justify-center py-8">
              <ArrowPathIcon class="w-6 h-6 text-gray-400 animate-spin" />
            </div>

            <!-- Info Tab -->
            <div v-else-if="detailsTab === 'info' && containerDetails" class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <p class="text-xs text-gray-400">Container ID</p>
                  <p class="text-sm text-white font-mono">{{ containerDetails.id.substring(0, 24) }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-400">Status</p>
                  <p class="text-sm" :class="getStateColor(containerDetails.state?.status)">
                    {{ containerDetails.state?.status }}
                  </p>
                </div>
                <div>
                  <p class="text-xs text-gray-400">Erstellt</p>
                  <p class="text-sm text-white">{{ containerDetails.created }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-400">IP-Adresse</p>
                  <p class="text-sm text-white font-mono">{{ containerDetails.network?.ipAddress || '-' }}</p>
                </div>
              </div>

              <!-- Mounts -->
              <div v-if="containerDetails.mounts?.length">
                <h4 class="text-sm font-medium text-gray-300 mb-2">Volumes / Mounts</h4>
                <div class="space-y-1">
                  <div
                    v-for="(mount, i) in containerDetails.mounts"
                    :key="i"
                    class="text-xs bg-dark-700 p-2 rounded font-mono"
                  >
                    <span class="text-gray-400">{{ mount.source }}</span>
                    <span class="text-gray-500 mx-2">:</span>
                    <span class="text-white">{{ mount.destination }}</span>
                    <span class="text-gray-500 ml-2">({{ mount.mode || 'rw' }})</span>
                  </div>
                </div>
              </div>

              <!-- Environment -->
              <div v-if="containerDetails.config?.env?.length">
                <h4 class="text-sm font-medium text-gray-300 mb-2">Umgebungsvariablen</h4>
                <div class="bg-dark-700 rounded p-2 max-h-40 overflow-auto">
                  <div
                    v-for="(env, i) in containerDetails.config.env.slice(0, 20)"
                    :key="i"
                    class="text-xs font-mono text-gray-300"
                  >
                    {{ env }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Logs Tab -->
            <div v-else-if="detailsTab === 'logs'" class="space-y-2">
              <div class="flex justify-end">
                <button @click="refreshLogs" class="btn-sm btn-secondary">
                  <ArrowPathIcon class="w-3 h-3" />
                  Aktualisieren
                </button>
              </div>
              <pre class="bg-dark-900 p-4 rounded-lg text-xs text-gray-300 font-mono overflow-auto max-h-96 whitespace-pre-wrap">{{ containerLogs || 'Keine Logs verfügbar' }}</pre>
            </div>

            <!-- Stats Tab -->
            <div v-else-if="detailsTab === 'stats'" class="space-y-4">
              <div v-if="selectedContainer.state !== 'running'" class="text-center text-gray-400 py-8">
                Stats sind nur für laufende Container verfügbar
              </div>
              <template v-else-if="containerStats">
                <div class="flex justify-end">
                  <button @click="refreshStats" class="btn-sm btn-secondary">
                    <ArrowPathIcon class="w-3 h-3" />
                    Aktualisieren
                  </button>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div class="card p-4">
                    <p class="text-xs text-gray-400 mb-1">CPU</p>
                    <p class="text-2xl font-bold text-white">{{ containerStats.cpu }}</p>
                  </div>
                  <div class="card p-4">
                    <p class="text-xs text-gray-400 mb-1">Memory</p>
                    <p class="text-2xl font-bold text-white">{{ containerStats.memory?.percent }}</p>
                    <p class="text-xs text-gray-500">{{ containerStats.memory?.usage }}</p>
                  </div>
                  <div class="card p-4">
                    <p class="text-xs text-gray-400 mb-1">Network I/O</p>
                    <p class="text-lg font-medium text-white">{{ containerStats.network?.io }}</p>
                  </div>
                  <div class="card p-4">
                    <p class="text-xs text-gray-400 mb-1">Block I/O</p>
                    <p class="text-lg font-medium text-white">{{ containerStats.block?.io }}</p>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
