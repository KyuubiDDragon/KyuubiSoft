<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '@/core/api/axios'
import { useToast } from '@/composables/useToast'
import {
  DocumentTextIcon,
  ServerIcon,
  MagnifyingGlassIcon,
  ArrowPathIcon,
  PauseIcon,
  PlayIcon,
  XMarkIcon,
  ChevronDownIcon,
} from '@heroicons/vue/24/outline'

const toast = useToast()

// =========================================================================
// State
// =========================================================================

const sourceType  = ref('docker') // docker | local
const dockerHosts = ref([])
const localFiles  = ref([])

const selectedHost      = ref(null)
const dockerContainers  = ref([])
const selectedContainer = ref(null)

const selectedFile = ref(null)

const logs          = ref([])
const loading       = ref(false)
const filterText    = ref('')
const filterLevel   = ref('') // '' | error | warning | info | debug
const autoScroll    = ref(true)
const logContainer  = ref(null)

let autoRefreshTimer = null
const autoRefresh    = ref(false)
const refreshInterval = ref(10) // seconds

// =========================================================================
// Load sources
// =========================================================================

async function loadDockerHosts() {
  try {
    const res = await api.get('/api/v1/logs/docker-hosts')
    dockerHosts.value = res.data.data.items || []
  } catch {
    // docker may not be enabled
  }
}

async function loadLocalFiles() {
  try {
    const res = await api.get('/api/v1/logs/local/files')
    localFiles.value = res.data.data.items || []
  } catch {
    // server may not be enabled
  }
}

async function selectDockerHost(host) {
  selectedHost.value     = host
  selectedContainer.value = null
  dockerContainers.value = []

  try {
    const res = await api.get(`/api/v1/logs/docker-hosts/${host.id}/containers`)
    dockerContainers.value = res.data.data.items || []
  } catch {
    toast.error('Fehler beim Laden der Container')
  }
}

async function selectContainer(container) {
  selectedContainer.value = container
  await fetchLogs()
  startAutoRefresh()
}

async function selectLocalFile(file) {
  selectedFile.value = file
  await fetchLogs()
  startAutoRefresh()
}

// =========================================================================
// Fetch logs
// =========================================================================

async function fetchLogs() {
  loading.value = true
  try {
    let res
    if (sourceType.value === 'docker' && selectedContainer.value && selectedHost.value) {
      res = await api.get(`/api/v1/logs/docker/${selectedHost.value.id}/${selectedContainer.value.id}`, {
        params: { tail: 500 },
      })
      logs.value = res.data.data.logs || []
    } else if (sourceType.value === 'local' && selectedFile.value) {
      res = await api.get('/api/v1/logs/local/read', {
        params: { path: selectedFile.value.path, lines: 500 },
      })
      logs.value = (res.data.data.lines || []).map(l => ({
        message:   l.message,
        level:     l.level,
        stream:    'stdout',
        timestamp: '',
      }))
    }

    if (autoScroll.value) {
      scrollToBottom()
    }
  } catch (err) {
    toast.error('Fehler beim Laden der Logs: ' + (err.response?.data?.message || err.message))
  } finally {
    loading.value = false
  }
}

function scrollToBottom() {
  setTimeout(() => {
    if (logContainer.value) {
      logContainer.value.scrollTop = logContainer.value.scrollHeight
    }
  }, 50)
}

// =========================================================================
// Auto-refresh
// =========================================================================

function startAutoRefresh() {
  stopAutoRefresh()
  if (autoRefresh.value) {
    autoRefreshTimer = setInterval(fetchLogs, refreshInterval.value * 1000)
  }
}

function stopAutoRefresh() {
  if (autoRefreshTimer) {
    clearInterval(autoRefreshTimer)
    autoRefreshTimer = null
  }
}

function toggleAutoRefresh() {
  autoRefresh.value = !autoRefresh.value
  if (autoRefresh.value) {
    startAutoRefresh()
  } else {
    stopAutoRefresh()
  }
}

// =========================================================================
// Filtering
// =========================================================================

const filteredLogs = computed(() => {
  let result = logs.value

  if (filterLevel.value) {
    result = result.filter(l => l.level === filterLevel.value)
  }

  if (filterText.value.trim()) {
    const q = filterText.value.toLowerCase()
    result = result.filter(l => l.message.toLowerCase().includes(q))
  }

  return result
})

function levelClass(level) {
  return {
    critical: 'text-red-300 bg-red-500/5',
    error:    'text-red-400',
    warning:  'text-yellow-400',
    debug:    'text-gray-500',
    info:     'text-gray-300',
  }[level] || 'text-gray-300'
}

function levelBadgeClass(level) {
  return {
    critical: 'bg-red-500/20 text-red-300',
    error:    'bg-red-500/10 text-red-400',
    warning:  'bg-yellow-500/10 text-yellow-400',
    debug:    'bg-gray-500/10 text-gray-500',
    info:     'bg-blue-500/10 text-blue-400',
  }[level] || 'bg-gray-500/10 text-gray-400'
}

function clearLogs() {
  logs.value = []
}

// =========================================================================
// Lifecycle
// =========================================================================

onMounted(async () => {
  await Promise.all([loadDockerHosts(), loadLocalFiles()])
})

onUnmounted(() => {
  stopAutoRefresh()
})
</script>

<template>
  <div class="flex h-full gap-0 overflow-hidden rounded-xl border border-dark-700">
    <!-- Sidebar -->
    <div class="w-64 flex-shrink-0 flex flex-col bg-dark-900 border-r border-dark-700">
      <div class="px-4 py-3 border-b border-dark-700">
        <h2 class="font-semibold text-white text-sm flex items-center gap-2">
          <DocumentTextIcon class="w-4 h-4 text-primary-400" />
          Log Viewer
        </h2>
        <!-- Source type toggle -->
        <div class="flex mt-2 rounded-lg overflow-hidden border border-dark-700">
          <button
            @click="sourceType = 'docker'"
            class="flex-1 py-1 text-xs transition-colors"
            :class="sourceType === 'docker' ? 'bg-primary-600 text-white' : 'text-gray-400 hover:text-white'"
          >Docker</button>
          <button
            @click="sourceType = 'local'"
            class="flex-1 py-1 text-xs transition-colors"
            :class="sourceType === 'local' ? 'bg-primary-600 text-white' : 'text-gray-400 hover:text-white'"
          >Lokal</button>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto p-2 space-y-1">
        <!-- Docker sources -->
        <template v-if="sourceType === 'docker'">
          <div v-if="dockerHosts.length === 0" class="text-gray-500 text-xs text-center py-6">
            Keine Docker-Hosts verfügbar
          </div>

          <template v-for="host in dockerHosts" :key="host.id">
            <button
              @click="selectDockerHost(host)"
              class="w-full text-left px-3 py-2 rounded-lg text-xs transition-colors flex items-center gap-2"
              :class="selectedHost?.id === host.id ? 'bg-primary-500/20 text-white' : 'text-gray-400 hover:bg-dark-700 hover:text-white'"
            >
              <ServerIcon class="w-4 h-4 flex-shrink-0" />
              {{ host.name }}
            </button>

            <template v-if="selectedHost?.id === host.id">
              <button
                v-for="container in dockerContainers"
                :key="container.id"
                @click="selectContainer(container)"
                class="w-full text-left ml-4 px-2 py-1.5 rounded text-xs flex items-center gap-1.5 transition-colors"
                :class="selectedContainer?.id === container.id ? 'text-white bg-dark-700' : 'text-gray-500 hover:text-gray-300'"
              >
                <span
                  class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                  :class="container.state === 'running' ? 'bg-green-400' : 'bg-gray-500'"
                ></span>
                <span class="truncate">{{ container.name }}</span>
              </button>
            </template>
          </template>
        </template>

        <!-- Local files -->
        <template v-else>
          <div v-if="localFiles.length === 0" class="text-gray-500 text-xs text-center py-6">
            Keine Log-Dateien gefunden
          </div>

          <button
            v-for="file in localFiles"
            :key="file.path"
            @click="selectLocalFile(file)"
            class="w-full text-left px-3 py-2 rounded-lg text-xs transition-colors"
            :class="selectedFile?.path === file.path ? 'bg-primary-500/20 text-white' : 'text-gray-400 hover:bg-dark-700 hover:text-white'"
          >
            <div class="font-medium truncate">{{ file.name }}</div>
            <div class="text-gray-600 mt-0.5">{{ file.label }}</div>
          </button>
        </template>
      </div>
    </div>

    <!-- Log panel -->
    <div class="flex-1 flex flex-col min-w-0 bg-dark-950">
      <!-- No source selected -->
      <div v-if="!selectedContainer && !selectedFile" class="flex-1 flex items-center justify-center text-gray-500">
        <div class="text-center">
          <DocumentTextIcon class="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p class="text-sm">Log-Quelle auswählen</p>
        </div>
      </div>

      <template v-else>
        <!-- Toolbar -->
        <div class="flex items-center gap-2 px-4 py-2 border-b border-dark-700 flex-shrink-0">
          <!-- Search -->
          <div class="relative flex-1 max-w-xs">
            <MagnifyingGlassIcon class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-500" />
            <input
              v-model="filterText"
              type="text"
              placeholder="Filter…"
              class="w-full bg-dark-700 border border-dark-600 rounded-lg pl-8 pr-3 py-1.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
            />
          </div>

          <!-- Level filter -->
          <select
            v-model="filterLevel"
            class="bg-dark-700 border border-dark-600 rounded-lg px-3 py-1.5 text-sm text-gray-300 focus:outline-none focus:border-primary-500"
          >
            <option value="">Alle Level</option>
            <option value="critical">Critical</option>
            <option value="error">Error</option>
            <option value="warning">Warning</option>
            <option value="info">Info</option>
            <option value="debug">Debug</option>
          </select>

          <div class="flex items-center gap-1 ml-auto">
            <!-- Auto-scroll -->
            <label class="flex items-center gap-1.5 text-xs text-gray-400 cursor-pointer select-none">
              <input v-model="autoScroll" type="checkbox" class="rounded" />
              Auto-Scroll
            </label>

            <!-- Auto-refresh -->
            <button
              @click="toggleAutoRefresh"
              class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs transition-colors"
              :class="autoRefresh ? 'bg-green-500/20 text-green-400' : 'bg-dark-700 text-gray-400 hover:text-white'"
            >
              <component :is="autoRefresh ? PauseIcon : PlayIcon" class="w-3.5 h-3.5" />
              {{ autoRefresh ? `Live (${refreshInterval}s)` : 'Live' }}
            </button>

            <!-- Refresh -->
            <button
              @click="fetchLogs"
              :disabled="loading"
              class="p-1.5 text-gray-400 hover:text-white rounded-lg hover:bg-dark-700 transition-colors"
            >
              <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': loading }" />
            </button>

            <!-- Clear -->
            <button
              @click="clearLogs"
              class="p-1.5 text-gray-400 hover:text-white rounded-lg hover:bg-dark-700 transition-colors"
            >
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Log count -->
        <div class="px-4 py-1 border-b border-dark-700 text-xs text-gray-600 flex-shrink-0">
          {{ filteredLogs.length.toLocaleString() }} Einträge
          <span v-if="filterText || filterLevel">(gefiltert von {{ logs.length.toLocaleString() }})</span>
        </div>

        <!-- Log lines -->
        <div
          ref="logContainer"
          class="flex-1 overflow-y-auto font-mono text-xs"
          style="background: #0d1117;"
        >
          <div
            v-for="(log, idx) in filteredLogs"
            :key="idx"
            class="flex items-start gap-2 px-4 py-0.5 hover:bg-white/5 transition-colors border-b border-dark-800/30"
            :class="levelClass(log.level)"
          >
            <span class="flex-shrink-0 text-gray-600 w-24 truncate text-right">
              {{ log.timestamp ? log.timestamp.slice(11, 19) : '' }}
            </span>
            <span
              v-if="log.stream === 'stderr'"
              class="flex-shrink-0 px-1 rounded text-xs bg-red-500/10 text-red-400"
            >stderr</span>
            <span class="flex-1 break-all whitespace-pre-wrap">{{ log.message }}</span>
          </div>

          <div v-if="filteredLogs.length === 0 && !loading" class="text-center text-gray-600 py-12 text-sm">
            Keine Logs
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
