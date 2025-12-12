<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import {
  CloudArrowUpIcon,
  CloudArrowDownIcon,
  ServerStackIcon,
  ClockIcon,
  TrashIcon,
  PlayIcon,
  Cog6ToothIcon,
  PlusIcon,
  XMarkIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  ArrowPathIcon,
  FolderIcon,
  CalendarIcon,
  DocumentArrowDownIcon,
  ShieldCheckIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const uiStore = useUiStore()

// State
const isLoading = ref(true)
const backups = ref([])
const targets = ref([])
const schedules = ref([])
const stats = ref(null)
const activeTab = ref('backups')

// Modals
const showTargetModal = ref(false)
const showScheduleModal = ref(false)
const showBackupModal = ref(false)
const showRestoreConfirm = ref(false)
const selectedBackup = ref(null)
const isProcessing = ref(false)

// Forms
const targetForm = ref({
  id: null,
  name: '',
  type: 'local',
  config: {
    path: '/backups',
  },
  is_default: false,
})

const scheduleForm = ref({
  id: null,
  name: '',
  type: 'full',
  target_id: '',
  cron_expression: '0 3 * * *',
  retention_days: 30,
  retention_count: 10,
  is_enabled: true,
  include_uploads: true,
  include_logs: false,
  compression: 'gzip',
})

const backupForm = ref({
  target_id: '',
  type: 'full',
  compression: 'gzip',
})

// Options
const backupTypes = [
  { value: 'full', label: 'Vollbackup', description: 'Datenbank + Dateien' },
  { value: 'database', label: 'Datenbank', description: 'Nur MySQL-Dump' },
  { value: 'files', label: 'Dateien', description: 'Nur Uploads' },
]

const storageTypes = [
  { value: 'local', label: 'Lokal', icon: FolderIcon },
  { value: 's3', label: 'S3 / MinIO', icon: CloudArrowUpIcon },
  { value: 'sftp', label: 'SFTP', icon: ServerStackIcon },
  { value: 'webdav', label: 'WebDAV', icon: CloudArrowUpIcon },
]

const compressionOptions = [
  { value: 'gzip', label: 'GZIP (.tar.gz)' },
  { value: 'zip', label: 'ZIP (.zip)' },
  { value: 'none', label: 'Keine Kompression' },
]

const cronPresets = [
  { value: '0 3 * * *', label: 'Täglich um 3:00 Uhr' },
  { value: '0 3 * * 0', label: 'Wöchentlich (Sonntag 3:00)' },
  { value: '0 3 1 * *', label: 'Monatlich (1. des Monats)' },
  { value: '0 */6 * * *', label: 'Alle 6 Stunden' },
  { value: '0 * * * *', label: 'Stündlich' },
]

// Computed
const defaultTarget = computed(() => targets.value.find(t => t.is_default))

// Fetch data
async function fetchData() {
  isLoading.value = true
  try {
    const [backupsRes, targetsRes, schedulesRes, statsRes] = await Promise.all([
      api.get('/api/v1/backups'),
      api.get('/api/v1/backups/targets'),
      api.get('/api/v1/backups/schedules'),
      api.get('/api/v1/backups/stats'),
    ])
    backups.value = backupsRes.data.data || []
    targets.value = targetsRes.data.data || []
    schedules.value = schedulesRes.data.data || []
    stats.value = statsRes.data.data
  } catch (error) {
    console.error('Failed to fetch backup data:', error)
    uiStore.showError('Fehler beim Laden der Backup-Daten')
  } finally {
    isLoading.value = false
  }
}

// Target CRUD
function openTargetModal(target = null) {
  if (target) {
    targetForm.value = {
      id: target.id,
      name: target.name,
      type: target.type,
      config: target.config || {},
      is_default: target.is_default,
    }
  } else {
    targetForm.value = {
      id: null,
      name: '',
      type: 'local',
      config: { path: '/backups' },
      is_default: false,
    }
  }
  showTargetModal.value = true
}

async function saveTarget() {
  isProcessing.value = true
  try {
    if (targetForm.value.id) {
      await api.put(`/api/v1/backups/targets/${targetForm.value.id}`, targetForm.value)
      uiStore.showSuccess('Speicherziel aktualisiert')
    } else {
      await api.post('/api/v1/backups/targets', targetForm.value)
      uiStore.showSuccess('Speicherziel erstellt')
    }
    showTargetModal.value = false
    await fetchData()
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Speichern')
  } finally {
    isProcessing.value = false
  }
}

async function deleteTarget(target) {
  if (!confirm(`Speicherziel "${target.name}" wirklich löschen?`)) return
  try {
    await api.delete(`/api/v1/backups/targets/${target.id}`)
    uiStore.showSuccess('Speicherziel gelöscht')
    await fetchData()
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function testTarget(target) {
  try {
    const response = await api.post(`/api/v1/backups/targets/${target.id}/test`)
    if (response.data.data.success) {
      uiStore.showSuccess('Verbindung erfolgreich!')
    } else {
      uiStore.showError(response.data.data.message || 'Verbindung fehlgeschlagen')
    }
    await fetchData()
  } catch (error) {
    uiStore.showError('Verbindungstest fehlgeschlagen')
  }
}

// Schedule CRUD
function openScheduleModal(schedule = null) {
  if (schedule) {
    scheduleForm.value = { ...schedule }
  } else {
    scheduleForm.value = {
      id: null,
      name: '',
      type: 'full',
      target_id: defaultTarget.value?.id || '',
      cron_expression: '0 3 * * *',
      retention_days: 30,
      retention_count: 10,
      is_enabled: true,
      include_uploads: true,
      include_logs: false,
      compression: 'gzip',
    }
  }
  showScheduleModal.value = true
}

async function saveSchedule() {
  isProcessing.value = true
  try {
    if (scheduleForm.value.id) {
      await api.put(`/api/v1/backups/schedules/${scheduleForm.value.id}`, scheduleForm.value)
      uiStore.showSuccess('Zeitplan aktualisiert')
    } else {
      await api.post('/api/v1/backups/schedules', scheduleForm.value)
      uiStore.showSuccess('Zeitplan erstellt')
    }
    showScheduleModal.value = false
    await fetchData()
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Speichern')
  } finally {
    isProcessing.value = false
  }
}

async function deleteSchedule(schedule) {
  if (!confirm(`Zeitplan "${schedule.name}" wirklich löschen?`)) return
  try {
    await api.delete(`/api/v1/backups/schedules/${schedule.id}`)
    uiStore.showSuccess('Zeitplan gelöscht')
    await fetchData()
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function toggleSchedule(schedule) {
  try {
    await api.put(`/api/v1/backups/schedules/${schedule.id}`, {
      is_enabled: !schedule.is_enabled
    })
    schedule.is_enabled = !schedule.is_enabled
  } catch (error) {
    uiStore.showError('Fehler beim Umschalten')
  }
}

// Backup actions
function openBackupModal() {
  backupForm.value = {
    target_id: defaultTarget.value?.id || targets.value[0]?.id || '',
    type: 'full',
    compression: 'gzip',
  }
  showBackupModal.value = true
}

async function createBackup() {
  isProcessing.value = true
  try {
    const response = await api.post('/api/v1/backups', backupForm.value)
    uiStore.showSuccess(`Backup erstellt: ${response.data.data.file_name}`)
    showBackupModal.value = false
    await fetchData()
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Backup fehlgeschlagen')
  } finally {
    isProcessing.value = false
  }
}

async function deleteBackup(backup) {
  if (!confirm('Backup wirklich löschen?')) return
  try {
    await api.delete(`/api/v1/backups/${backup.id}`)
    uiStore.showSuccess('Backup gelöscht')
    await fetchData()
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

function openRestoreConfirm(backup) {
  selectedBackup.value = backup
  showRestoreConfirm.value = true
}

async function restoreBackup() {
  if (!selectedBackup.value) return
  isProcessing.value = true
  try {
    await api.post(`/api/v1/backups/${selectedBackup.value.id}/restore`)
    uiStore.showSuccess('Wiederherstellung abgeschlossen!')
    showRestoreConfirm.value = false
    selectedBackup.value = null
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Wiederherstellung fehlgeschlagen')
  } finally {
    isProcessing.value = false
  }
}

async function downloadBackup(backup) {
  try {
    const response = await api.get(`/api/v1/backups/${backup.id}/download`, {
      responseType: 'blob'
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', backup.file_name)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (error) {
    uiStore.showError('Download fehlgeschlagen')
  }
}

// Helpers
function formatBytes(bytes) {
  if (!bytes) return '0 B'
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${sizes[i]}`
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('de-DE')
}

function formatDuration(seconds) {
  if (!seconds) return '-'
  if (seconds < 60) return `${seconds}s`
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins}m ${secs}s`
}

function getStatusColor(status) {
  return {
    'completed': 'text-green-400 bg-green-500/20',
    'running': 'text-blue-400 bg-blue-500/20',
    'pending': 'text-yellow-400 bg-yellow-500/20',
    'failed': 'text-red-400 bg-red-500/20',
    'cancelled': 'text-gray-400 bg-gray-500/20',
  }[status] || 'text-gray-400 bg-gray-500/20'
}

function getStatusLabel(status) {
  return {
    'completed': 'Erfolgreich',
    'running': 'Läuft',
    'pending': 'Ausstehend',
    'failed': 'Fehlgeschlagen',
    'cancelled': 'Abgebrochen',
  }[status] || status
}

onMounted(fetchData)
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Backup & Recovery</h1>
        <p class="text-gray-400 mt-1">Sichere deine Daten und stelle sie bei Bedarf wieder her</p>
      </div>
      <button @click="openBackupModal" class="btn-primary">
        <CloudArrowUpIcon class="w-5 h-5 mr-2" />
        Backup erstellen
      </button>
    </div>

    <!-- Stats Cards -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="card p-4">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-green-500/20">
            <CheckCircleIcon class="w-6 h-6 text-green-400" />
          </div>
          <div>
            <p class="text-gray-400 text-sm">Erfolgreiche Backups</p>
            <p class="text-xl font-bold text-white">{{ stats.successful_backups }}</p>
          </div>
        </div>
      </div>
      <div class="card p-4">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-blue-500/20">
            <ServerStackIcon class="w-6 h-6 text-blue-400" />
          </div>
          <div>
            <p class="text-gray-400 text-sm">Speicherverbrauch</p>
            <p class="text-xl font-bold text-white">{{ formatBytes(stats.total_size) }}</p>
          </div>
        </div>
      </div>
      <div class="card p-4">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-purple-500/20">
            <ClockIcon class="w-6 h-6 text-purple-400" />
          </div>
          <div>
            <p class="text-gray-400 text-sm">Aktive Zeitpläne</p>
            <p class="text-xl font-bold text-white">{{ stats.active_schedules }}</p>
          </div>
        </div>
      </div>
      <div class="card p-4">
        <div class="flex items-center gap-3">
          <div class="p-2 rounded-lg bg-yellow-500/20">
            <CalendarIcon class="w-6 h-6 text-yellow-400" />
          </div>
          <div>
            <p class="text-gray-400 text-sm">Letztes Backup</p>
            <p class="text-sm font-medium text-white">
              {{ stats.last_backup ? formatDate(stats.last_backup.created_at) : 'Nie' }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-dark-700">
      <nav class="flex gap-4">
        <button
          @click="activeTab = 'backups'"
          class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
          :class="activeTab === 'backups' ? 'border-primary-500 text-white' : 'border-transparent text-gray-400 hover:text-white'"
        >
          <CloudArrowUpIcon class="w-4 h-4 inline mr-2" />
          Backups
        </button>
        <button
          @click="activeTab = 'schedules'"
          class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
          :class="activeTab === 'schedules' ? 'border-primary-500 text-white' : 'border-transparent text-gray-400 hover:text-white'"
        >
          <ClockIcon class="w-4 h-4 inline mr-2" />
          Zeitpläne
        </button>
        <button
          @click="activeTab = 'targets'"
          class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
          :class="activeTab === 'targets' ? 'border-primary-500 text-white' : 'border-transparent text-gray-400 hover:text-white'"
        >
          <ServerStackIcon class="w-4 h-4 inline mr-2" />
          Speicherziele
        </button>
      </nav>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="card p-12 text-center">
      <ArrowPathIcon class="w-8 h-8 text-gray-500 mx-auto animate-spin" />
      <p class="text-gray-500 mt-2">Lade Daten...</p>
    </div>

    <!-- Backups Tab -->
    <div v-else-if="activeTab === 'backups'" class="space-y-4">
      <div v-if="backups.length === 0" class="card p-12 text-center">
        <CloudArrowUpIcon class="w-16 h-16 text-gray-600 mx-auto mb-4" />
        <h3 class="text-lg font-semibold text-white mb-2">Keine Backups vorhanden</h3>
        <p class="text-gray-500 mb-4">Erstelle dein erstes Backup, um deine Daten zu sichern.</p>
        <button @click="openBackupModal" class="btn-primary">
          <PlusIcon class="w-4 h-4 mr-2" />
          Backup erstellen
        </button>
      </div>

      <div v-else class="card overflow-hidden">
        <table class="w-full">
          <thead class="bg-dark-700">
            <tr>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Backup</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Typ</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Speicherziel</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Größe</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Status</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Erstellt</th>
              <th class="px-4 py-3 text-right text-sm font-medium text-gray-400">Aktionen</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-dark-700">
            <tr v-for="backup in backups" :key="backup.id" class="hover:bg-dark-700/50">
              <td class="px-4 py-3">
                <p class="text-white text-sm font-medium">{{ backup.file_name || 'Backup' }}</p>
                <p v-if="backup.schedule_name" class="text-xs text-gray-500">{{ backup.schedule_name }}</p>
              </td>
              <td class="px-4 py-3">
                <span class="text-sm text-gray-300">
                  {{ backupTypes.find(t => t.value === backup.type)?.label || backup.type }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="text-sm text-gray-300">{{ backup.target_name }}</span>
              </td>
              <td class="px-4 py-3">
                <span class="text-sm text-gray-300">{{ formatBytes(backup.file_size) }}</span>
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-1 text-xs rounded-full" :class="getStatusColor(backup.status)">
                  {{ getStatusLabel(backup.status) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="text-sm text-gray-400">{{ formatDate(backup.created_at) }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button
                    v-if="backup.status === 'completed'"
                    @click="downloadBackup(backup)"
                    class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg"
                    title="Download"
                  >
                    <DocumentArrowDownIcon class="w-4 h-4" />
                  </button>
                  <button
                    v-if="backup.status === 'completed'"
                    @click="openRestoreConfirm(backup)"
                    class="p-1.5 text-gray-400 hover:text-green-400 hover:bg-green-500/10 rounded-lg"
                    title="Wiederherstellen"
                  >
                    <ArrowPathIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="deleteBackup(backup)"
                    class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg"
                    title="Löschen"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Schedules Tab -->
    <div v-else-if="activeTab === 'schedules'" class="space-y-4">
      <div class="flex justify-end">
        <button @click="openScheduleModal()" class="btn-secondary">
          <PlusIcon class="w-4 h-4 mr-2" />
          Zeitplan erstellen
        </button>
      </div>

      <div v-if="schedules.length === 0" class="card p-12 text-center">
        <ClockIcon class="w-16 h-16 text-gray-600 mx-auto mb-4" />
        <h3 class="text-lg font-semibold text-white mb-2">Keine Zeitpläne</h3>
        <p class="text-gray-500 mb-4">Erstelle automatische Backup-Zeitpläne.</p>
      </div>

      <div v-else class="grid gap-4">
        <div
          v-for="schedule in schedules"
          :key="schedule.id"
          class="card p-4"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
              <div
                class="p-2 rounded-lg"
                :class="schedule.is_enabled ? 'bg-green-500/20' : 'bg-gray-500/20'"
              >
                <ClockIcon
                  class="w-6 h-6"
                  :class="schedule.is_enabled ? 'text-green-400' : 'text-gray-500'"
                />
              </div>
              <div>
                <h3 class="text-white font-medium">{{ schedule.name }}</h3>
                <p class="text-sm text-gray-400">
                  {{ backupTypes.find(t => t.value === schedule.type)?.label }}
                  {{ schedule.target_name }}
                </p>
                <p class="text-xs text-gray-500">
                  {{ cronPresets.find(c => c.value === schedule.cron_expression)?.label || schedule.cron_expression }}
                  | Aufbewahrung: {{ schedule.retention_days }} Tage / max. {{ schedule.retention_count }}
                </p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="toggleSchedule(schedule)"
                class="px-3 py-1.5 text-sm rounded-lg transition-colors"
                :class="schedule.is_enabled
                  ? 'bg-green-500/20 text-green-400 hover:bg-green-500/30'
                  : 'bg-gray-500/20 text-gray-400 hover:bg-gray-500/30'"
              >
                {{ schedule.is_enabled ? 'Aktiv' : 'Inaktiv' }}
              </button>
              <button
                @click="openScheduleModal(schedule)"
                class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg"
              >
                <Cog6ToothIcon class="w-4 h-4" />
              </button>
              <button
                @click="deleteSchedule(schedule)"
                class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg"
              >
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Targets Tab -->
    <div v-else-if="activeTab === 'targets'" class="space-y-4">
      <div class="flex justify-end">
        <button @click="openTargetModal()" class="btn-secondary">
          <PlusIcon class="w-4 h-4 mr-2" />
          Speicherziel hinzufügen
        </button>
      </div>

      <div v-if="targets.length === 0" class="card p-12 text-center">
        <ServerStackIcon class="w-16 h-16 text-gray-600 mx-auto mb-4" />
        <h3 class="text-lg font-semibold text-white mb-2">Keine Speicherziele</h3>
        <p class="text-gray-500 mb-4">Füge ein Speicherziel hinzu (Lokal, S3, SFTP, WebDAV).</p>
      </div>

      <div v-else class="grid gap-4 md:grid-cols-2">
        <div
          v-for="target in targets"
          :key="target.id"
          class="card p-4"
        >
          <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
              <div class="p-2 rounded-lg bg-dark-700">
                <component
                  :is="storageTypes.find(t => t.value === target.type)?.icon || ServerStackIcon"
                  class="w-6 h-6 text-primary-400"
                />
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="text-white font-medium">{{ target.name }}</h3>
                  <span v-if="target.is_default" class="px-1.5 py-0.5 text-xs bg-primary-500/20 text-primary-400 rounded">
                    Standard
                  </span>
                </div>
                <p class="text-sm text-gray-400">
                  {{ storageTypes.find(t => t.value === target.type)?.label }}
                </p>
                <div v-if="target.last_test_at" class="flex items-center gap-1 mt-1">
                  <component
                    :is="target.last_test_status === 'success' ? CheckCircleIcon : ExclamationCircleIcon"
                    class="w-3 h-3"
                    :class="target.last_test_status === 'success' ? 'text-green-400' : 'text-red-400'"
                  />
                  <span class="text-xs text-gray-500">
                    Getestet: {{ formatDate(target.last_test_at) }}
                  </span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="testTarget(target)"
                class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg"
                title="Verbindung testen"
              >
                <PlayIcon class="w-4 h-4" />
              </button>
              <button
                @click="openTargetModal(target)"
                class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg"
                title="Bearbeiten"
              >
                <Cog6ToothIcon class="w-4 h-4" />
              </button>
              <button
                @click="deleteTarget(target)"
                class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg"
                title="Löschen"
              >
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Backup Modal -->
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
          v-if="showBackupModal"
          class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        >
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">Backup erstellen</h3>
              <button @click="showBackupModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <form @submit.prevent="createBackup" class="p-6 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Speicherziel</label>
                <select
                  v-model="backupForm.target_id"
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                  required
                >
                  <option v-for="target in targets" :key="target.id" :value="target.id">
                    {{ target.name }} ({{ target.type }})
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Backup-Typ</label>
                <div class="grid grid-cols-3 gap-2">
                  <button
                    v-for="type in backupTypes"
                    :key="type.value"
                    type="button"
                    @click="backupForm.type = type.value"
                    class="p-3 rounded-lg border-2 text-center transition-all"
                    :class="backupForm.type === type.value
                      ? 'border-primary-500 bg-primary-500/10'
                      : 'border-dark-600 hover:border-dark-500'"
                  >
                    <p class="text-sm font-medium text-white">{{ type.label }}</p>
                    <p class="text-xs text-gray-500">{{ type.description }}</p>
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Kompression</label>
                <select
                  v-model="backupForm.compression"
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                >
                  <option v-for="opt in compressionOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>

              <div class="flex gap-2 pt-4">
                <button type="button" @click="showBackupModal = false" class="btn-secondary flex-1">
                  Abbrechen
                </button>
                <button type="submit" class="btn-primary flex-1" :disabled="isProcessing">
                  <ArrowPathIcon v-if="isProcessing" class="w-4 h-4 mr-2 animate-spin" />
                  <CloudArrowUpIcon v-else class="w-4 h-4 mr-2" />
                  Backup starten
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Target Modal -->
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
          v-if="showTargetModal"
          class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        >
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
              <h3 class="text-lg font-semibold text-white">
                {{ targetForm.id ? 'Speicherziel bearbeiten' : 'Speicherziel hinzufügen' }}
              </h3>
              <button @click="showTargetModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <form @submit.prevent="saveTarget" class="p-6 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Name</label>
                <input
                  v-model="targetForm.name"
                  type="text"
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                  placeholder="z.B. Lokaler Speicher"
                  required
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Typ</label>
                <div class="grid grid-cols-2 gap-2">
                  <button
                    v-for="type in storageTypes"
                    :key="type.value"
                    type="button"
                    @click="targetForm.type = type.value"
                    class="p-3 rounded-lg border-2 flex items-center gap-2 transition-all"
                    :class="targetForm.type === type.value
                      ? 'border-primary-500 bg-primary-500/10'
                      : 'border-dark-600 hover:border-dark-500'"
                  >
                    <component :is="type.icon" class="w-5 h-5 text-gray-400" />
                    <span class="text-sm text-white">{{ type.label }}</span>
                  </button>
                </div>
              </div>

              <!-- Local Config -->
              <div v-if="targetForm.type === 'local'">
                <label class="block text-sm font-medium text-gray-400 mb-1">Pfad</label>
                <input
                  v-model="targetForm.config.path"
                  type="text"
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                  placeholder="/backups"
                />
              </div>

              <!-- S3 Config -->
              <template v-if="targetForm.type === 's3'">
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Endpoint</label>
                  <input
                    v-model="targetForm.config.endpoint"
                    type="text"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                    placeholder="s3.amazonaws.com"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Bucket</label>
                  <input
                    v-model="targetForm.config.bucket"
                    type="text"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                    placeholder="my-backups"
                  />
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Access Key</label>
                    <input
                      v-model="targetForm.config.access_key"
                      type="text"
                      class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Secret Key</label>
                    <input
                      v-model="targetForm.config.secret_key"
                      type="password"
                      class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                    />
                  </div>
                </div>
              </template>

              <!-- SFTP Config -->
              <template v-if="targetForm.type === 'sftp'">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Host</label>
                    <input
                      v-model="targetForm.config.host"
                      type="text"
                      class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Port</label>
                    <input
                      v-model="targetForm.config.port"
                      type="number"
                      class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                      placeholder="22"
                    />
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Benutzername</label>
                    <input
                      v-model="targetForm.config.username"
                      type="text"
                      class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Passwort</label>
                    <input
                      v-model="targetForm.config.password"
                      type="password"
                      class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                    />
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Pfad</label>
                  <input
                    v-model="targetForm.config.path"
                    type="text"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                    placeholder="/backups"
                  />
                </div>
              </template>

              <!-- WebDAV Config -->
              <template v-if="targetForm.type === 'webdav'">
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">URL</label>
                  <input
                    v-model="targetForm.config.url"
                    type="url"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                    placeholder="https://nextcloud.example.com/remote.php/dav/files/user/"
                  />
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Benutzername</label>
                    <input
                      v-model="targetForm.config.username"
                      type="text"
                      class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Passwort</label>
                    <input
                      v-model="targetForm.config.password"
                      type="password"
                      class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                    />
                  </div>
                </div>
              </template>

              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="targetForm.is_default"
                  class="w-4 h-4 rounded border-dark-600 bg-dark-700 text-primary-500"
                />
                <span class="text-sm text-gray-400">Als Standard-Speicherziel festlegen</span>
              </label>

              <div class="flex gap-2 pt-4">
                <button type="button" @click="showTargetModal = false" class="btn-secondary flex-1">
                  Abbrechen
                </button>
                <button type="submit" class="btn-primary flex-1" :disabled="isProcessing">
                  {{ targetForm.id ? 'Speichern' : 'Erstellen' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Schedule Modal -->
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
          v-if="showScheduleModal"
          class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        >
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700 sticky top-0 bg-dark-800">
              <h3 class="text-lg font-semibold text-white">
                {{ scheduleForm.id ? 'Zeitplan bearbeiten' : 'Zeitplan erstellen' }}
              </h3>
              <button @click="showScheduleModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <form @submit.prevent="saveSchedule" class="p-6 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Name</label>
                <input
                  v-model="scheduleForm.name"
                  type="text"
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                  placeholder="z.B. Tägliches Backup"
                  required
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Speicherziel</label>
                <select
                  v-model="scheduleForm.target_id"
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                  required
                >
                  <option v-for="target in targets" :key="target.id" :value="target.id">
                    {{ target.name }} ({{ target.type }})
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Backup-Typ</label>
                <div class="grid grid-cols-3 gap-2">
                  <button
                    v-for="type in backupTypes"
                    :key="type.value"
                    type="button"
                    @click="scheduleForm.type = type.value"
                    class="p-2 rounded-lg border-2 text-center transition-all"
                    :class="scheduleForm.type === type.value
                      ? 'border-primary-500 bg-primary-500/10'
                      : 'border-dark-600 hover:border-dark-500'"
                  >
                    <p class="text-sm font-medium text-white">{{ type.label }}</p>
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Zeitplan</label>
                <select
                  v-model="scheduleForm.cron_expression"
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                >
                  <option v-for="preset in cronPresets" :key="preset.value" :value="preset.value">
                    {{ preset.label }}
                  </option>
                </select>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Aufbewahrung (Tage)</label>
                  <input
                    v-model.number="scheduleForm.retention_days"
                    type="number"
                    min="1"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-400 mb-1">Max. Anzahl</label>
                  <input
                    v-model.number="scheduleForm.retention_count"
                    type="number"
                    min="1"
                    class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Kompression</label>
                <select
                  v-model="scheduleForm.compression"
                  class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
                >
                  <option v-for="opt in compressionOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>

              <div class="space-y-2">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    v-model="scheduleForm.include_uploads"
                    class="w-4 h-4 rounded border-dark-600 bg-dark-700 text-primary-500"
                  />
                  <span class="text-sm text-gray-400">Upload-Dateien einschließen</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    v-model="scheduleForm.include_logs"
                    class="w-4 h-4 rounded border-dark-600 bg-dark-700 text-primary-500"
                  />
                  <span class="text-sm text-gray-400">Log-Dateien einschließen</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    v-model="scheduleForm.is_enabled"
                    class="w-4 h-4 rounded border-dark-600 bg-dark-700 text-primary-500"
                  />
                  <span class="text-sm text-gray-400">Zeitplan aktivieren</span>
                </label>
              </div>

              <div class="flex gap-2 pt-4">
                <button type="button" @click="showScheduleModal = false" class="btn-secondary flex-1">
                  Abbrechen
                </button>
                <button type="submit" class="btn-primary flex-1" :disabled="isProcessing">
                  {{ scheduleForm.id ? 'Speichern' : 'Erstellen' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Restore Confirmation Modal -->
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
          v-if="showRestoreConfirm"
          class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        >
          <div class="bg-dark-800 border border-dark-700 rounded-xl shadow-2xl w-full max-w-md">
            <div class="p-6 text-center">
              <div class="w-16 h-16 rounded-full bg-yellow-500/20 flex items-center justify-center mx-auto mb-4">
                <ExclamationTriangleIcon class="w-8 h-8 text-yellow-400" />
              </div>
              <h3 class="text-lg font-semibold text-white mb-2">Wiederherstellung bestätigen</h3>
              <p class="text-gray-400 mb-4">
                Möchtest du wirklich das Backup <strong class="text-white">{{ selectedBackup?.file_name }}</strong> wiederherstellen?
              </p>
              <p class="text-sm text-red-400 mb-6">
                Achtung: Alle aktuellen Daten werden überschrieben!
              </p>
              <div class="flex gap-2">
                <button
                  @click="showRestoreConfirm = false; selectedBackup = null"
                  class="btn-secondary flex-1"
                >
                  Abbrechen
                </button>
                <button
                  @click="restoreBackup"
                  class="btn-primary flex-1 bg-yellow-600 hover:bg-yellow-700"
                  :disabled="isProcessing"
                >
                  <ArrowPathIcon v-if="isProcessing" class="w-4 h-4 mr-2 animate-spin" />
                  <ShieldCheckIcon v-else class="w-4 h-4 mr-2" />
                  Wiederherstellen
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
