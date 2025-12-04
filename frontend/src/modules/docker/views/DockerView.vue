<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
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
} from '@heroicons/vue/24/outline'

// State
const activeTab = ref('containers')
const loading = ref(false)
const error = ref(null)
const dockerAvailable = ref(null)
const dockerVersion = ref('')

// Data
const containers = ref([])
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

// Methods
async function checkDockerStatus() {
  try {
    const response = await api.get('/docker/status')
    dockerAvailable.value = response.data.available
    dockerVersion.value = response.data.version || ''
    if (!response.data.available) {
      error.value = response.data.error || 'Docker is not available'
    }
  } catch (e) {
    dockerAvailable.value = false
    error.value = 'Failed to connect to Docker'
  }
}

async function loadContainers() {
  try {
    const response = await api.get('/docker/containers', { params: { all: 'true' } })
    containers.value = response.data.containers || []
  } catch (e) {
    console.error('Failed to load containers:', e)
  }
}

async function loadImages() {
  try {
    const response = await api.get('/docker/images')
    images.value = response.data.images || []
  } catch (e) {
    console.error('Failed to load images:', e)
  }
}

async function loadNetworks() {
  try {
    const response = await api.get('/docker/networks')
    networks.value = response.data.networks || []
  } catch (e) {
    console.error('Failed to load networks:', e)
  }
}

async function loadVolumes() {
  try {
    const response = await api.get('/docker/volumes')
    volumes.value = response.data.volumes || []
  } catch (e) {
    console.error('Failed to load volumes:', e)
  }
}

async function loadSystemInfo() {
  try {
    const response = await api.get('/docker/system')
    systemInfo.value = response.data
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
    await api.post(`/docker/containers/${container.id}/start`)
    await loadContainers()
  } catch (e) {
    error.value = 'Failed to start container'
  }
}

async function stopContainer(container) {
  try {
    await api.post(`/docker/containers/${container.id}/stop`)
    await loadContainers()
  } catch (e) {
    error.value = 'Failed to stop container'
  }
}

async function restartContainer(container) {
  try {
    await api.post(`/docker/containers/${container.id}/restart`)
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
      api.get(`/docker/containers/${container.id}`),
      api.get(`/docker/containers/${container.id}/logs`, { params: { tail: 100 } }),
    ])
    containerDetails.value = detailsRes.data
    containerLogs.value = logsRes.data.logs || ''

    // Only load stats if container is running
    if (container.state === 'running') {
      const statsRes = await api.get(`/docker/containers/${container.id}/stats`)
      containerStats.value = statsRes.data
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
    const response = await api.get(`/docker/containers/${selectedContainer.value.id}/logs`, { params: { tail: 100 } })
    containerLogs.value = response.data.logs || ''
  } catch (e) {
    console.error('Failed to refresh logs:', e)
  }
}

async function refreshStats() {
  if (!selectedContainer.value || selectedContainer.value.state !== 'running') return
  try {
    const response = await api.get(`/docker/containers/${selectedContainer.value.id}/stats`)
    containerStats.value = response.data
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
    <div v-if="dockerAvailable === false" class="card p-6 border-red-500/30 bg-red-900/10">
      <div class="flex items-start gap-4">
        <ExclamationTriangleIcon class="w-8 h-8 text-red-400 flex-shrink-0" />
        <div>
          <h3 class="text-lg font-semibold text-red-400">Docker nicht verfügbar</h3>
          <p class="text-gray-400 mt-1">{{ error || 'Der Docker-Daemon ist nicht erreichbar. Stelle sicher, dass Docker installiert und gestartet ist.' }}</p>
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
        <div v-else class="space-y-2">
          <div
            v-for="container in containers"
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
