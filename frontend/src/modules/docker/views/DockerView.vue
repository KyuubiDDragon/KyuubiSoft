<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useUiStore } from '@/stores/ui'
import { useProjectStore } from '@/stores/project'
import api from '@/core/api/axios'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
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
  EyeIcon,
  EyeSlashIcon,
  CodeBracketIcon,
  ClipboardDocumentIcon,
  PlusIcon,
  CloudArrowUpIcon,
  CloudArrowDownIcon,
  ArchiveBoxIcon,
  TrashIcon,
  ArrowDownTrayIcon,
  ArrowUpTrayIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

const projectStore = useProjectStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

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
const containerEnvVars = ref([])
const detailsTab = ref('info')
const loadingDetails = ref(false)
const showSensitiveEnvVars = ref(false)

// Compose File Modal
const showComposeModal = ref(false)
const composeStackName = ref('')
const composeFile = ref(null)
const composeContent = ref('')
const loadingCompose = ref(false)
const savingCompose = ref(false)

// Quick Deploy Modal
const showQuickDeployModal = ref(false)
const quickDeployForm = ref({
  image: '',
  name: '',
  ports: '',
  env: '',
  volumes: '',
  network: '',
  restart: 'unless-stopped',
})
const deploying = ref(false)

// Stack Deploy Modal
const showStackDeployModal = ref(false)
const stackDeployForm = ref({
  name: '',
  compose: '',
  env: '',
})
const deployingStack = ref(false)

// Backups
const backups = ref([])
const loadingBackups = ref(false)
const showBackupModal = ref(false)
const selectedBackup = ref(null)

// 2FA Verification Modal (for SSH operations)
const show2FAModal = ref(false)
const twoFactorCode = ref('')
const pending2FAOperation = ref(null)
const verifying2FA = ref(false)
const twoFactorError = ref('')

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
    // Filter hosts by selected project
    const params = projectStore.getProjectFilter()
    const response = await api.get('/api/v1/docker/hosts', { params })
    dockerHosts.value = response.data.data.hosts || []

    // Find default host or first available
    const defaultHost = dockerHosts.value.find(h => h.is_default) || dockerHosts.value[0]
    if (defaultHost) {
      selectedHostId.value = defaultHost.id
      currentHostName.value = defaultHost.name
    } else {
      selectedHostId.value = null
      currentHostName.value = 'Kein Host'
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
  containerEnvVars.value = []
  showSensitiveEnvVars.value = false

  try {
    const [detailsRes, logsRes, envRes] = await Promise.all([
      api.get(`/api/v1/docker/containers/${container.id}`, { params: getHostParams() }),
      api.get(`/api/v1/docker/containers/${container.id}/logs`, { params: { tail: 100, ...getHostParams() } }),
      api.get(`/api/v1/docker/containers/${container.id}/env`, { params: getHostParams() }),
    ])
    containerDetails.value = detailsRes.data.data || detailsRes.data
    const logsData = logsRes.data.data || logsRes.data
    containerLogs.value = logsData.logs || ''
    const envData = envRes.data.data || envRes.data
    containerEnvVars.value = envData.env || []

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
  containerEnvVars.value = []
}

// Compose File Methods
async function openComposeModal(stackName) {
  composeStackName.value = stackName
  showComposeModal.value = true
  loadingCompose.value = true
  composeFile.value = null
  composeContent.value = ''

  try {
    const response = await api.get(`/api/v1/docker/stacks/${stackName}/compose`, { params: getHostParams() })
    const data = response.data.data || response.data
    composeFile.value = data
    composeContent.value = data.content || ''
  } catch (e) {
    console.error('Failed to load compose file:', e)
    error.value = 'Compose-Datei konnte nicht geladen werden'
  } finally {
    loadingCompose.value = false
  }
}

function closeComposeModal() {
  showComposeModal.value = false
  composeStackName.value = ''
  composeFile.value = null
  composeContent.value = ''
}

async function saveComposeFile() {
  if (!composeFile.value?.writable) return

  savingCompose.value = true
  try {
    await api.put(`/api/v1/docker/stacks/${composeStackName.value}/compose`, {
      content: composeContent.value,
      path: composeFile.value.path,
    }, { params: getHostParams() })

    // Reload compose file to verify
    const response = await api.get(`/api/v1/docker/stacks/${composeStackName.value}/compose`, { params: getHostParams() })
    const data = response.data.data || response.data
    composeFile.value = data
    composeContent.value = data.content || ''

    error.value = null
    toast.success('Compose-Datei gespeichert! Führe "docker compose up -d" aus um Änderungen anzuwenden.')
  } catch (e) {
    console.error('Failed to save compose file:', e)
    error.value = 'Compose-Datei konnte nicht gespeichert werden'
  } finally {
    savingCompose.value = false
  }
}

function copyEnvValue(value) {
  navigator.clipboard.writeText(value)
}

// Quick Deploy
async function quickDeploy() {
  if (!quickDeployForm.value.image) {
    error.value = 'Image ist erforderlich'
    return
  }

  deploying.value = true
  try {
    const payload = {
      image: quickDeployForm.value.image.trim(),
      name: quickDeployForm.value.name.trim() || undefined,
      restart: quickDeployForm.value.restart,
      ports: quickDeployForm.value.ports ? quickDeployForm.value.ports.split('\n').map(p => p.trim()).filter(Boolean) : [],
      env: quickDeployForm.value.env ? quickDeployForm.value.env.split('\n').map(e => e.trim()).filter(Boolean) : [],
      volumes: quickDeployForm.value.volumes ? quickDeployForm.value.volumes.split('\n').map(v => v.trim()).filter(Boolean) : [],
      network: quickDeployForm.value.network.trim() || undefined,
    }

    await api.post('/api/v1/docker/run', payload, { params: getHostParams() })
    showQuickDeployModal.value = false
    quickDeployForm.value = { image: '', name: '', ports: '', env: '', volumes: '', network: '', restart: 'unless-stopped' }
    await loadContainers()
    error.value = null
  } catch (e) {
    error.value = e.response?.data?.message || 'Container konnte nicht gestartet werden'
  } finally {
    deploying.value = false
  }
}

// Stack Deploy
async function deployStack() {
  if (!stackDeployForm.value.name || !stackDeployForm.value.compose) {
    error.value = 'Name und Compose-Inhalt sind erforderlich'
    return
  }

  deployingStack.value = true
  try {
    await api.post('/api/v1/docker/stacks/deploy', {
      name: stackDeployForm.value.name.trim(),
      compose: stackDeployForm.value.compose,
      env: stackDeployForm.value.env || undefined,
    }, { params: getHostParams() })
    showStackDeployModal.value = false
    stackDeployForm.value = { name: '', compose: '', env: '' }
    await loadContainers()
    error.value = null
  } catch (e) {
    error.value = e.response?.data?.message || 'Stack konnte nicht deployed werden'
  } finally {
    deployingStack.value = false
  }
}

// Stack Operations
async function stackUp(stackName) {
  try {
    await api.post(`/api/v1/docker/stacks/${stackName}/up`, null, { params: getHostParams() })
    await loadContainers()
  } catch (e) {
    error.value = 'Stack konnte nicht gestartet werden'
  }
}

async function stackDown(stackName) {
  if (!await confirm({ message: `Stack "${stackName}" stoppen?`, type: 'danger', confirmText: 'Bestätigen' })) return
  try {
    await api.post(`/api/v1/docker/stacks/${stackName}/down`, null, { params: getHostParams() })
    await loadContainers()
  } catch (e) {
    error.value = 'Stack konnte nicht gestoppt werden'
  }
}

async function stackRestart(stackName) {
  try {
    await api.post(`/api/v1/docker/stacks/${stackName}/restart`, null, { params: getHostParams() })
    await loadContainers()
  } catch (e) {
    error.value = 'Stack konnte nicht neugestartet werden'
  }
}

async function stackPullAndRedeploy(stackName) {
  if (!await confirm({ message: `Stack "${stackName}" neu pullen und redeployen? Dies kann einige Minuten dauern.`, type: 'danger', confirmText: 'Bestätigen' })) return
  try {
    loading.value = true
    const response = await api.post(`/api/v1/docker/stacks/${stackName}/pull-redeploy`, null, { params: getHostParams() })
    await loadContainers()
    const data = response.data.data || response.data
    toast.success(`Stack "${stackName}" erfolgreich aktualisiert!\n\nPull Output:\n${data.pull_output?.substring(0, 500) || 'OK'}`)
  } catch (e) {
    error.value = e.response?.data?.message || 'Pull & Redeploy fehlgeschlagen'
  } finally {
    loading.value = false
  }
}

// Backups
async function loadBackups() {
  loadingBackups.value = true
  try {
    // Filter backups by selected host
    const response = await api.get('/api/v1/docker/backups', { params: getHostParams() })
    backups.value = response.data.data?.backups || []
  } catch (e) {
    console.error('Failed to load backups:', e)
  } finally {
    loadingBackups.value = false
  }
}

async function backupStack(stackName, sensitiveToken = null) {
  try {
    const params = { ...getHostParams() }
    if (sensitiveToken) {
      params.sensitive_token = sensitiveToken
    }
    const response = await api.post(`/api/v1/docker/stacks/${stackName}/backup`, null, { params })
    const data = response.data.data || response.data
    toast.success(`Backup erstellt: ${data.backup_file}\nDateien: ${data.files?.join(', ')}`)
    await loadBackups()
  } catch (e) {
    // Check if 2FA verification is required (status 428)
    if (e.response?.status === 428 && e.response?.data?.data?.requires_2fa) {
      // Store the pending operation and show 2FA modal
      pending2FAOperation.value = {
        type: 'backup',
        stackName,
        operation: e.response?.data?.data?.operation || 'ssh_backup'
      }
      twoFactorCode.value = ''
      twoFactorError.value = ''
      show2FAModal.value = true
      return
    }
    error.value = e.response?.data?.message || 'Backup konnte nicht erstellt werden'
  }
}

// 2FA Verification for sensitive operations
async function verify2FAAndRetry() {
  if (!twoFactorCode.value || twoFactorCode.value.length < 6) {
    twoFactorError.value = 'Bitte gib einen gültigen 6-stelligen Code ein'
    return
  }

  verifying2FA.value = true
  twoFactorError.value = ''

  try {
    // Verify 2FA and get sensitive token
    const response = await api.post('/api/v1/auth/2fa/verify-sensitive', {
      code: twoFactorCode.value,
      operation: pending2FAOperation.value?.operation || 'ssh_backup'
    })

    const sensitiveToken = response.data.data?.sensitive_token
    if (!sensitiveToken) {
      throw new Error('Kein Token erhalten')
    }

    // Close modal
    show2FAModal.value = false

    // Retry the original operation with the token
    if (pending2FAOperation.value?.type === 'backup') {
      await backupStack(pending2FAOperation.value.stackName, sensitiveToken)
    }

    // Clear pending operation
    pending2FAOperation.value = null
    twoFactorCode.value = ''
  } catch (e) {
    twoFactorError.value = e.response?.data?.message || 'Verifizierung fehlgeschlagen'
  } finally {
    verifying2FA.value = false
  }
}

function cancel2FA() {
  show2FAModal.value = false
  pending2FAOperation.value = null
  twoFactorCode.value = ''
  twoFactorError.value = ''
}

async function viewBackup(backup) {
  try {
    const response = await api.get(`/api/v1/docker/backups/${backup.file}`, { params: getHostParams() })
    selectedBackup.value = response.data.data?.backup || null
    showBackupModal.value = true
  } catch (e) {
    error.value = 'Backup konnte nicht geladen werden'
  }
}

async function restoreBackup(backup, deploy = false) {
  const action = deploy ? 'wiederherstellen und deployen' : 'nur wiederherstellen'
  if (!await confirm({ message: `Backup "${backup.file}" ${action}?`, type: 'danger', confirmText: 'Bestätigen' })) return

  try {
    await api.post(`/api/v1/docker/backups/${backup.file}/restore`, { deploy, ...getHostParams() })
    if (deploy) {
      await loadContainers()
    }
    showBackupModal.value = false
    toast.success('Backup erfolgreich wiederhergestellt!')
  } catch (e) {
    error.value = e.response?.data?.message || 'Backup konnte nicht wiederhergestellt werden'
  }
}

async function deleteBackup(backup) {
  if (!await confirm({ message: `Backup "${backup.file}" wirklich löschen?`, type: 'danger', confirmText: 'Bestätigen' })) return

  try {
    await api.delete(`/api/v1/docker/backups/${backup.file}`, { params: getHostParams() })
    await loadBackups()
    showBackupModal.value = false
  } catch (e) {
    error.value = 'Backup konnte nicht gelöscht werden'
  }
}

async function removeContainer(container) {
  if (!await confirm({ message: `Container "${container.name}" wirklich löschen?`, type: 'danger', confirmText: 'Bestätigen' })) return

  try {
    await api.delete(`/api/v1/docker/containers/${container.id}`, { params: { force: 'true', ...getHostParams() } })
    await loadContainers()
  } catch (e) {
    error.value = 'Container konnte nicht gelöscht werden'
  }
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
  const currentState = expandedStacks.value[stackName] ?? true // Match default from isStackExpanded
  expandedStacks.value[stackName] = !currentState
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

// Watch for tab changes to load backups
watch(activeTab, (newTab) => {
  if (newTab === 'backups') {
    loadBackups()
  }
})

// Watch for host changes to reload backups if on backups tab
watch(selectedHostId, () => {
  if (activeTab.value === 'backups') {
    loadBackups()
  }
})

// Watch for project changes to reload hosts
watch(() => projectStore.selectedProjectId, async () => {
  await loadHosts()
  if (selectedHostId.value) {
    await loadContainers()
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
            class="flex items-center gap-2 px-3 py-2 bg-white/[0.04] hover:bg-white/[0.04] rounded-lg border border-white/[0.06] transition-colors"
          >
            <ServerStackIcon class="w-4 h-4 text-gray-400" />
            <span class="text-sm text-white">{{ currentHostName }}</span>
            <ChevronDownIcon class="w-4 h-4 text-gray-400" />
          </button>

          <!-- Host Dropdown -->
          <div
            v-if="showHostDropdown"
            class="absolute right-0 mt-1 py-1 bg-white/[0.04] border border-white/[0.06] rounded-lg shadow-float z-50 min-w-48"
          >
            <!-- Local/Default Option -->
            <button
              @click="selectHost(null)"
              class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-white/[0.04] transition-colors"
              :class="!selectedHostId ? 'bg-white/[0.08]' : ''"
            >
              <ComputerDesktopIcon class="w-4 h-4 text-blue-400" />
              <span class="text-sm text-gray-300">Lokal (Standard)</span>
            </button>

            <template v-if="dockerHosts.length > 0">
              <div class="h-px bg-white/[0.06] my-1"></div>

              <button
                v-for="host in dockerHosts"
                :key="host.id"
                @click="selectHost(host)"
                class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-white/[0.04] transition-colors"
                :class="selectedHostId === host.id ? 'bg-white/[0.08]' : ''"
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

            <div class="h-px bg-white/[0.06] my-1"></div>

            <!-- Manage Hosts Link -->
            <a
              href="/docker/hosts"
              class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-white/[0.04] transition-colors"
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
          <input v-model="autoRefresh" type="checkbox" class="rounded border-white/[0.08] bg-white/[0.04]" />
          Auto-Refresh
        </label>
        <button @click="refreshData" :disabled="loading" class="btn-secondary">
          <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        </button>
        <button @click="showQuickDeployModal = true" class="btn-secondary" v-if="dockerAvailable">
          <PlusIcon class="w-4 h-4 mr-1" />
          Container
        </button>
        <button @click="showStackDeployModal = true" class="btn-primary" v-if="dockerAvailable">
          <CloudArrowUpIcon class="w-4 h-4 mr-1" />
          Stack Deploy
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

            <div class="mt-4 p-4 bg-white/[0.04] rounded-lg">
              <p class="text-sm text-gray-300 font-medium mb-2">Mögliche Lösungen:</p>
              <ul class="text-sm text-gray-400 space-y-1 list-disc list-inside">
                <li>Prüfe ob Docker installiert ist: <code class="bg-white/[0.04] px-1 rounded">docker --version</code></li>
                <li>Starte den Docker-Daemon: <code class="bg-white/[0.04] px-1 rounded">sudo systemctl start docker</code></li>
                <li>Prüfe Berechtigungen: <code class="bg-white/[0.04] px-1 rounded">sudo usermod -aG docker $USER</code></li>
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
          <a href="/toolbox" class="card p-4 hover:bg-white/[0.04] hover:border-primary-500 transition-all group">
            <div class="flex items-center gap-3">
              <span class="text-2xl">📄</span>
              <div>
                <h3 class="font-medium text-white group-hover:text-primary-400">Dockerfile Generator</h3>
                <p class="text-xs text-gray-400">Dockerfiles erstellen</p>
              </div>
            </div>
          </a>
          <a href="/toolbox" class="card p-4 hover:bg-white/[0.04] hover:border-primary-500 transition-all group">
            <div class="flex items-center gap-3">
              <span class="text-2xl">🔧</span>
              <div>
                <h3 class="font-medium text-white group-hover:text-primary-400">Compose Builder</h3>
                <p class="text-xs text-gray-400">docker-compose.yml erstellen</p>
              </div>
            </div>
          </a>
          <a href="/toolbox" class="card p-4 hover:bg-white/[0.04] hover:border-primary-500 transition-all group">
            <div class="flex items-center gap-3">
              <span class="text-2xl">⚙️</span>
              <div>
                <h3 class="font-medium text-white group-hover:text-primary-400">Command Builder</h3>
                <p class="text-xs text-gray-400">docker run Befehle</p>
              </div>
            </div>
          </a>
          <a href="/toolbox" class="card p-4 hover:bg-white/[0.04] hover:border-primary-500 transition-all group">
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
      <div class="border-b border-white/[0.06]">
        <nav class="flex gap-4">
          <button
            v-for="tab in [
              { id: 'containers', label: 'Container', icon: CubeIcon },
              { id: 'images', label: 'Images', icon: ServerIcon },
              { id: 'networks', label: 'Netzwerke', icon: GlobeAltIcon },
              { id: 'volumes', label: 'Volumes', icon: CircleStackIcon },
              { id: 'backups', label: 'Backups', icon: ArchiveBoxIcon },
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
              class="flex items-center justify-between p-4 bg-white/[0.03] cursor-pointer hover:bg-white/[0.04] transition-colors"
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
              <div class="flex items-center gap-2" @click.stop>
                <button
                  @click="backupStack(stack.name)"
                  class="btn-icon text-gray-400 hover:text-white hover:bg-white/[0.04]"
                  title="Backup erstellen"
                >
                  <ArchiveBoxIcon class="w-4 h-4" />
                </button>
                <button
                  @click="openComposeModal(stack.name)"
                  class="btn-icon text-gray-400 hover:text-white hover:bg-white/[0.04]"
                  title="Compose-Datei anzeigen"
                >
                  <CodeBracketIcon class="w-4 h-4" />
                </button>
                <button
                  @click="stackPullAndRedeploy(stack.name)"
                  class="btn-icon text-green-400 hover:bg-green-500/20"
                  title="Pull & Redeploy"
                >
                  <CloudArrowDownIcon class="w-4 h-4" />
                </button>
                <button
                  @click="stackRestart(stack.name)"
                  class="btn-icon text-blue-400 hover:bg-blue-500/20"
                  title="Stack neustarten"
                >
                  <ArrowPathIcon class="w-4 h-4" />
                </button>
                <button
                  @click="stackDown(stack.name)"
                  class="btn-icon text-red-400 hover:bg-red-500/20"
                  title="Stack stoppen"
                >
                  <StopIcon class="w-4 h-4" />
                </button>
                <span
                  class="px-2 py-1 text-xs rounded-full"
                  :class="stack.running === stack.total ? 'bg-green-500/20 text-green-400' : 'bg-yellow-500/20 text-yellow-400'"
                >
                  {{ stack.running === stack.total ? 'Healthy' : 'Partial' }}
                </span>
              </div>
            </div>

            <!-- Stack Containers -->
            <div v-if="isStackExpanded(stack.name)" class="divide-y divide-white/[0.06]">
              <div
                v-for="container in stack.containers"
                :key="container.id"
                class="p-4 pl-12 hover:bg-white/[0.04] transition-colors cursor-pointer"
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
              class="card p-4 hover:bg-white/[0.04] transition-colors cursor-pointer"
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
              <tr class="text-left text-sm text-gray-400 border-b border-white/[0.06]">
                <th class="pb-3 font-medium">Repository</th>
                <th class="pb-3 font-medium">Tag</th>
                <th class="pb-3 font-medium">ID</th>
                <th class="pb-3 font-medium">Erstellt</th>
                <th class="pb-3 font-medium">Größe</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="image in images" :key="image.id" class="border-b border-white/[0.06]">
                <td class="py-3 text-white">{{ image.repository }}</td>
                <td class="py-3">
                  <span class="px-2 py-1 bg-white/[0.08] rounded text-sm">{{ image.tag }}</span>
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
              <tr class="text-left text-sm text-gray-400 border-b border-white/[0.06]">
                <th class="pb-3 font-medium">Name</th>
                <th class="pb-3 font-medium">ID</th>
                <th class="pb-3 font-medium">Driver</th>
                <th class="pb-3 font-medium">Scope</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="network in networks" :key="network.id" class="border-b border-white/[0.06]">
                <td class="py-3 text-white">{{ network.name }}</td>
                <td class="py-3 text-gray-400 font-mono text-sm">{{ network.id.substring(0, 12) }}</td>
                <td class="py-3">
                  <span class="px-2 py-1 bg-white/[0.08] rounded text-sm">{{ network.driver }}</span>
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
              <tr class="text-left text-sm text-gray-400 border-b border-white/[0.06]">
                <th class="pb-3 font-medium">Name</th>
                <th class="pb-3 font-medium">Driver</th>
                <th class="pb-3 font-medium">Scope</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="volume in volumes" :key="volume.name" class="border-b border-white/[0.06]">
                <td class="py-3 text-white font-mono text-sm">{{ volume.name }}</td>
                <td class="py-3">
                  <span class="px-2 py-1 bg-white/[0.08] rounded text-sm">{{ volume.driver }}</span>
                </td>
                <td class="py-3 text-gray-400">{{ volume.scope }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Backups Tab -->
      <div v-if="activeTab === 'backups'" class="space-y-4">
        <div class="flex justify-between items-center">
          <p class="text-gray-400 text-sm">Backups deiner Stack-Konfigurationen (.env und docker-compose)</p>
          <button @click="loadBackups" class="btn-secondary">
            <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': loadingBackups }" />
            Aktualisieren
          </button>
        </div>

        <div v-if="loadingBackups" class="card p-8 text-center">
          <ArrowPathIcon class="w-8 h-8 text-gray-400 animate-spin mx-auto" />
        </div>

        <div v-else-if="backups.length === 0" class="card p-8 text-center text-gray-400">
          <ArchiveBoxIcon class="w-12 h-12 mx-auto mb-4 opacity-50" />
          <p>Keine Backups vorhanden</p>
          <p class="text-sm mt-2">Erstelle Backups über das Archiv-Symbol bei deinen Stacks</p>
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="backup in backups"
            :key="backup.file"
            class="card p-4 flex items-center justify-between hover:bg-white/[0.04] transition-colors"
          >
            <div class="flex items-center gap-4">
              <ArchiveBoxIcon class="w-8 h-8 text-primary-400" />
              <div>
                <h4 class="font-medium text-white">{{ backup.stack_name }}</h4>
                <p class="text-sm text-gray-400">{{ backup.backup_date }}</p>
                <p class="text-xs text-gray-500">{{ backup.files?.join(', ') }}</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="viewBackup(backup)"
                class="btn-icon text-gray-400 hover:text-white"
                title="Anzeigen"
              >
                <EyeIcon class="w-4 h-4" />
              </button>
              <button
                @click="restoreBackup(backup, true)"
                class="btn-icon text-green-400 hover:bg-green-500/20"
                title="Wiederherstellen & Deployen"
              >
                <ArrowUpTrayIcon class="w-4 h-4" />
              </button>
              <button
                @click="deleteBackup(backup)"
                class="btn-icon text-red-400 hover:bg-red-500/20"
                title="Löschen"
              >
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Container Details Modal -->
    <Teleport to="body">
      <div
        v-if="selectedContainer"
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
        
      >
        <div class="modal w-full max-w-4xl max-h-[90vh] flex flex-col">
          <!-- Modal Header -->
          <div class="flex items-center justify-between p-4 border-b border-white/[0.06]">
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
          <div class="border-b border-white/[0.06] px-4">
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
                    class="text-xs bg-white/[0.04] p-2 rounded font-mono"
                  >
                    <span class="text-gray-400">{{ mount.source }}</span>
                    <span class="text-gray-500 mx-2">:</span>
                    <span class="text-white">{{ mount.destination }}</span>
                    <span class="text-gray-500 ml-2">({{ mount.mode || 'rw' }})</span>
                  </div>
                </div>
              </div>

              <!-- Environment Variables -->
              <div v-if="containerEnvVars.length">
                <div class="flex items-center justify-between mb-2">
                  <h4 class="text-sm font-medium text-gray-300">Umgebungsvariablen</h4>
                  <button
                    @click="showSensitiveEnvVars = !showSensitiveEnvVars"
                    class="flex items-center gap-1 text-xs text-gray-400 hover:text-white"
                  >
                    <component :is="showSensitiveEnvVars ? EyeSlashIcon : EyeIcon" class="w-4 h-4" />
                    {{ showSensitiveEnvVars ? 'Sensible Werte ausblenden' : 'Sensible Werte anzeigen' }}
                  </button>
                </div>
                <div class="bg-white/[0.04] rounded overflow-hidden max-h-60 overflow-auto">
                  <table class="w-full text-xs">
                    <tbody>
                      <tr
                        v-for="(env, i) in containerEnvVars"
                        :key="i"
                        class="border-b border-white/[0.06] last:border-0 hover:bg-white/[0.04]"
                      >
                        <td class="py-1.5 px-2 text-primary-400 font-mono whitespace-nowrap">{{ env.key }}</td>
                        <td class="py-1.5 px-2 text-gray-300 font-mono break-all">
                          <template v-if="env.sensitive && !showSensitiveEnvVars">
                            <span class="text-gray-500">••••••••</span>
                          </template>
                          <template v-else>
                            {{ env.value }}
                          </template>
                        </td>
                        <td class="py-1.5 px-1 w-8">
                          <button
                            @click="copyEnvValue(env.value)"
                            class="btn-icon p-1 text-gray-400 hover:text-white"
                            title="Kopieren"
                          >
                            <ClipboardDocumentIcon class="w-3 h-3" />
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
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
              <pre class="bg-white/[0.02] p-4 rounded-lg text-xs text-gray-300 font-mono overflow-auto max-h-96 whitespace-pre-wrap">{{ containerLogs || 'Keine Logs verfügbar' }}</pre>
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

      <!-- Compose File Modal -->
      <div
        v-if="showComposeModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
        
      >
        <div class="modal w-full max-w-4xl max-h-[90vh] flex flex-col">
          <!-- Modal Header -->
          <div class="flex items-center justify-between p-4 border-b border-white/[0.06]">
            <div class="flex items-center gap-3">
              <CodeBracketIcon class="w-5 h-5 text-primary-400" />
              <div>
                <h2 class="text-lg font-semibold text-white">{{ composeStackName }}</h2>
                <p class="text-sm text-gray-400 font-mono">{{ composeFile?.path || 'docker-compose.yml' }}</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span
                v-if="composeFile?.writable"
                class="px-2 py-1 text-xs bg-green-500/20 text-green-400 rounded"
              >
                Editierbar
              </span>
              <span
                v-else-if="composeFile?.readable"
                class="px-2 py-1 text-xs bg-yellow-500/20 text-yellow-400 rounded"
              >
                Nur Lesen
              </span>
              <button @click="closeComposeModal" class="btn-icon">
                <XCircleIcon class="w-5 h-5" />
              </button>
            </div>
          </div>

          <!-- Modal Body -->
          <div class="flex-1 overflow-auto p-4">
            <div v-if="loadingCompose" class="flex items-center justify-center py-8">
              <ArrowPathIcon class="w-6 h-6 text-gray-400 animate-spin" />
            </div>

            <div v-else-if="!composeFile?.readable" class="text-center py-8">
              <ExclamationTriangleIcon class="w-12 h-12 text-yellow-400 mx-auto mb-4" />
              <p class="text-gray-300">{{ composeFile?.message || 'Compose-Datei konnte nicht gelesen werden' }}</p>
              <p class="text-sm text-gray-500 mt-2">Pfad: {{ composeFile?.path }}</p>
            </div>

            <div v-else>
              <textarea
                v-model="composeContent"
                :readonly="!composeFile?.writable"
                class="w-full h-96 bg-white/[0.02] text-gray-300 font-mono text-sm p-4 rounded-lg border border-white/[0.06] focus:border-primary-500 focus:outline-none resize-none"
                :class="{ 'cursor-not-allowed opacity-75': !composeFile?.writable }"
                spellcheck="false"
              ></textarea>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="flex items-center justify-between p-4 border-t border-white/[0.06]">
            <p class="text-xs text-gray-500">
              {{ composeFile?.working_dir ? `Working Dir: ${composeFile.working_dir}` : '' }}
            </p>
            <div class="flex items-center gap-2">
              <button @click="closeComposeModal" class="btn-secondary">
                Schließen
              </button>
              <button
                v-if="composeFile?.writable"
                @click="saveComposeFile"
                :disabled="savingCompose"
                class="btn-primary"
              >
                <ArrowPathIcon v-if="savingCompose" class="w-4 h-4 animate-spin" />
                <template v-else>Speichern</template>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Deploy Modal -->
      <div
        v-if="showQuickDeployModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
      >
        <div class="modal w-full max-w-lg">
          <div class="flex items-center justify-between p-4 border-b border-white/[0.06]">
            <div class="flex items-center gap-3">
              <PlusIcon class="w-5 h-5 text-primary-400" />
              <h2 class="text-lg font-semibold text-white">Quick Deploy</h2>
            </div>
            <button @click="showQuickDeployModal = false" class="btn-icon">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="quickDeploy" class="p-4 space-y-4">
            <div>
              <label class="label">Image *</label>
              <input v-model="quickDeployForm.image" type="text" class="input" placeholder="nginx:latest" required />
            </div>

            <div>
              <label class="label">Container Name</label>
              <input v-model="quickDeployForm.name" type="text" class="input" placeholder="my-container" />
            </div>

            <div>
              <label class="label">Ports (pro Zeile: host:container)</label>
              <textarea v-model="quickDeployForm.ports" class="input" rows="2" placeholder="8080:80&#10;443:443"></textarea>
            </div>

            <div>
              <label class="label">Environment (pro Zeile: KEY=VALUE)</label>
              <textarea v-model="quickDeployForm.env" class="input" rows="2" placeholder="NODE_ENV=production&#10;DEBUG=false"></textarea>
            </div>

            <div>
              <label class="label">Volumes (pro Zeile: host:container)</label>
              <textarea v-model="quickDeployForm.volumes" class="input" rows="2" placeholder="/data:/app/data&#10;./config:/etc/config"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">Network</label>
                <input v-model="quickDeployForm.network" type="text" class="input" placeholder="bridge" />
              </div>
              <div>
                <label class="label">Restart Policy</label>
                <select v-model="quickDeployForm.restart" class="input">
                  <option value="no">No</option>
                  <option value="always">Always</option>
                  <option value="unless-stopped">Unless Stopped</option>
                  <option value="on-failure">On Failure</option>
                </select>
              </div>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="showQuickDeployModal = false" class="btn-secondary flex-1">
                Abbrechen
              </button>
              <button type="submit" :disabled="deploying" class="btn-primary flex-1">
                <ArrowPathIcon v-if="deploying" class="w-4 h-4 animate-spin" />
                <template v-else>
                  <PlayIcon class="w-4 h-4 mr-1" />
                  Starten
                </template>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Stack Deploy Modal -->
      <div
        v-if="showStackDeployModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
      >
        <div class="modal w-full max-w-2xl max-h-[90vh] flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-white/[0.06]">
            <div class="flex items-center gap-3">
              <CloudArrowUpIcon class="w-5 h-5 text-primary-400" />
              <h2 class="text-lg font-semibold text-white">Stack Deploy</h2>
            </div>
            <button @click="showStackDeployModal = false" class="btn-icon">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="deployStack" class="flex-1 overflow-auto p-4 space-y-4">
            <div>
              <label class="label">Stack Name *</label>
              <input v-model="stackDeployForm.name" type="text" class="input" placeholder="my-stack" required />
            </div>

            <div>
              <label class="label">docker-compose.yml *</label>
              <textarea
                v-model="stackDeployForm.compose"
                class="input font-mono text-sm"
                rows="12"
                placeholder="version: '3.8'
services:
  web:
    image: nginx:latest
    ports:
      - '80:80'"
                required
              ></textarea>
            </div>

            <div>
              <label class="label">.env Datei (optional)</label>
              <textarea
                v-model="stackDeployForm.env"
                class="input font-mono text-sm"
                rows="4"
                placeholder="DB_HOST=localhost
DB_PASSWORD=secret"
              ></textarea>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="showStackDeployModal = false" class="btn-secondary flex-1">
                Abbrechen
              </button>
              <button type="submit" :disabled="deployingStack" class="btn-primary flex-1">
                <ArrowPathIcon v-if="deployingStack" class="w-4 h-4 animate-spin" />
                <template v-else>
                  <CloudArrowUpIcon class="w-4 h-4 mr-1" />
                  Deploy
                </template>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Backup Details Modal -->
      <div
        v-if="showBackupModal && selectedBackup"
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
      >
        <div class="modal w-full max-w-3xl max-h-[90vh] flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-white/[0.06]">
            <div class="flex items-center gap-3">
              <ArchiveBoxIcon class="w-5 h-5 text-primary-400" />
              <div>
                <h2 class="text-lg font-semibold text-white">{{ selectedBackup.stack_name }}</h2>
                <p class="text-sm text-gray-400">{{ selectedBackup.backup_date }}</p>
              </div>
            </div>
            <button @click="showBackupModal = false" class="btn-icon">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="flex-1 overflow-auto p-4 space-y-4">
            <div v-for="file in selectedBackup.files" :key="file.name" class="space-y-2">
              <div class="flex items-center justify-between">
                <h4 class="font-medium text-white">{{ file.name }}</h4>
                <span class="text-xs text-gray-500 font-mono">{{ file.path }}</span>
              </div>
              <pre class="bg-white/[0.02] p-4 rounded-lg text-xs text-gray-300 font-mono overflow-auto max-h-60 whitespace-pre-wrap">{{ file.content }}</pre>
            </div>
          </div>

          <div class="flex items-center justify-between p-4 border-t border-white/[0.06]">
            <p class="text-xs text-gray-500">Working Dir: {{ selectedBackup.working_dir }}</p>
            <div class="flex items-center gap-2">
              <button @click="deleteBackup(selectedBackup)" class="btn-secondary text-red-400">
                <TrashIcon class="w-4 h-4 mr-1" />
                Löschen
              </button>
              <button @click="restoreBackup(selectedBackup, false)" class="btn-secondary">
                <ArrowDownTrayIcon class="w-4 h-4 mr-1" />
                Nur Dateien
              </button>
              <button @click="restoreBackup(selectedBackup, true)" class="btn-primary">
                <ArrowUpTrayIcon class="w-4 h-4 mr-1" />
                Wiederherstellen & Deploy
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- 2FA Verification Modal -->
    <Teleport to="body">
      <div v-if="show2FAModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-md" @click="cancel2FA"></div>
        <div class="relative modal w-full max-w-md">
          <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-12 h-12 rounded-full bg-primary-500/20 flex items-center justify-center">
                <svg class="w-6 h-6 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-white">2FA-Verifizierung erforderlich</h3>
                <p class="text-sm text-gray-400">SSH-Zugriff erfordert zusätzliche Sicherheit</p>
              </div>
            </div>

            <p class="text-gray-300 mb-4">
              Um auf den Remote-Server per SSH zuzugreifen, bestätige bitte mit deinem 2FA-Code.
            </p>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">2FA-Code</label>
                <input
                  v-model="twoFactorCode"
                  type="text"
                  inputmode="numeric"
                  pattern="[0-9]*"
                  maxlength="6"
                  class="input w-full text-center text-2xl tracking-widest font-mono"
                  placeholder="000000"
                  @keyup.enter="verify2FAAndRetry"
                  autofocus
                />
              </div>

              <div v-if="twoFactorError" class="bg-red-500/10 border border-red-500/30 rounded-lg p-3">
                <p class="text-sm text-red-400">{{ twoFactorError }}</p>
              </div>

              <div class="flex gap-3">
                <button
                  @click="cancel2FA"
                  class="btn-secondary flex-1"
                  :disabled="verifying2FA"
                >
                  Abbrechen
                </button>
                <button
                  @click="verify2FAAndRetry"
                  class="btn-primary flex-1"
                  :disabled="verifying2FA || twoFactorCode.length < 6"
                >
                  <ArrowPathIcon v-if="verifying2FA" class="w-4 h-4 mr-2 animate-spin" />
                  {{ verifying2FA ? 'Verifiziere...' : 'Bestätigen' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
