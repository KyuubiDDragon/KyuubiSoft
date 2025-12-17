<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import api from '@/core/api/axios'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'

const systemInfo = ref({
  version: '1.0.0',
  environment: 'production',
  phpVersion: '-',
  mysqlVersion: '-',
  redisStatus: '-',
})

const metrics = ref({
  cpu: { usage_percent: 0, cores: 1, load_1m: 0 },
  memory: { usage_percent: 0, used_formatted: '-', total_formatted: '-' },
  disk: { usage_percent: 0, used_formatted: '-', total_formatted: '-' },
  database: { users: 0, lists: 0, documents: 0, audit_logs: 0 },
  uptime: '-',
})

const auditLogs = ref([])
const auditPagination = ref({ page: 1, perPage: 20, total: 0 })
const auditFilters = ref({ action: '', user_id: '', entity_type: '' })
const expandedLogId = ref(null)
const availableEntityTypes = ref([
  { value: '', label: 'Alle Entitäten' },
  { value: 'user', label: 'Benutzer' },
  { value: 'list', label: 'Listen' },
  { value: 'document', label: 'Dokumente' },
  { value: 'snippet', label: 'Snippets' },
  { value: 'connection', label: 'Verbindungen' },
  { value: 'kanban', label: 'Kanban' },
  { value: 'project', label: 'Projekte' },
  { value: 'invoice', label: 'Rechnungen' },
  { value: 'checklist', label: 'Checklisten' },
  { value: 'password', label: 'Passwörter' },
  { value: 'recurring_task', label: 'Wiederkehrende Aufgaben' },
  { value: 'template', label: 'Templates' },
  { value: 'tag', label: 'Tags' },
  { value: 'api_key', label: 'API-Keys' },
  { value: 'cache', label: 'Cache' },
  { value: 'session', label: 'Sessions' },
])

const isLoading = ref(true)
const isLoadingMetrics = ref(true)
const isLoadingLogs = ref(true)
const isClearingCache = ref(false)
const isTerminatingSessions = ref(false)

let metricsInterval = null

const toast = useToast()
const { confirm } = useConfirmDialog()

const totalPages = computed(() => Math.ceil(auditPagination.value.total / auditPagination.value.perPage))

function getProgressColor(percent) {
  if (percent >= 90) return 'bg-red-500'
  if (percent >= 70) return 'bg-yellow-500'
  return 'bg-green-500'
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function getActionLabel(action) {
  if (!action) return '-'
  const labels = {
    // Auth
    'user.login': 'Anmeldung',
    'user.logout': 'Abmeldung',
    'user.register': 'Registrierung',
    'user.update': 'Benutzer aktualisiert',
    'user.delete': 'Benutzer gelöscht',
    'user.password_change': 'Passwort geändert',
    '2fa.enabled': '2FA aktiviert',
    '2fa.disabled': '2FA deaktiviert',
    // Lists
    'list.create': 'Liste erstellt',
    'list.update': 'Liste aktualisiert',
    'list.delete': 'Liste gelöscht',
    // Documents
    'document.create': 'Dokument erstellt',
    'document.update': 'Dokument aktualisiert',
    'document.delete': 'Dokument gelöscht',
    'document.share': 'Dokument geteilt',
    // Snippets
    'snippet.create': 'Snippet erstellt',
    'snippet.update': 'Snippet aktualisiert',
    'snippet.delete': 'Snippet gelöscht',
    // Connections
    'connection.create': 'Verbindung erstellt',
    'connection.update': 'Verbindung aktualisiert',
    'connection.delete': 'Verbindung gelöscht',
    // Kanban
    'kanban.create': 'Kanban-Board erstellt',
    'kanban.update': 'Kanban-Board aktualisiert',
    'kanban.delete': 'Kanban-Board gelöscht',
    // Projects
    'project.create': 'Projekt erstellt',
    'project.update': 'Projekt aktualisiert',
    'project.delete': 'Projekt gelöscht',
    // Invoices
    'invoice.create': 'Rechnung erstellt',
    'invoice.update': 'Rechnung aktualisiert',
    'invoice.delete': 'Rechnung gelöscht',
    // Checklists
    'checklist.create': 'Checkliste erstellt',
    'checklist.update': 'Checkliste aktualisiert',
    'checklist.delete': 'Checkliste gelöscht',
    // Passwords
    'password.create': 'Passwort erstellt',
    'password.update': 'Passwort aktualisiert',
    'password.delete': 'Passwort gelöscht',
    'password.view': 'Passwort angezeigt',
    // Recurring Tasks
    'recurring_task.create': 'Wiederkehrende Aufgabe erstellt',
    'recurring_task.update': 'Wiederkehrende Aufgabe aktualisiert',
    'recurring_task.delete': 'Wiederkehrende Aufgabe gelöscht',
    'recurring_task.process': 'Wiederkehrende Aufgabe ausgeführt',
    // Templates
    'template.create': 'Template erstellt',
    'template.update': 'Template aktualisiert',
    'template.delete': 'Template gelöscht',
    'template.use': 'Template verwendet',
    // Tags
    'tag.create': 'Tag erstellt',
    'tag.update': 'Tag aktualisiert',
    'tag.delete': 'Tag gelöscht',
    'tag.merge': 'Tags zusammengeführt',
    // API Keys
    'api_key.create': 'API-Key erstellt',
    'api_key.revoke': 'API-Key widerrufen',
    'api_key.delete': 'API-Key gelöscht',
    // Export/Import
    'export.create': 'Export durchgeführt',
    'import.create': 'Import durchgeführt',
    // System
    'cache.clear': 'Cache geleert',
    'sessions.terminate_all': 'Sessions beendet',
    'system.settings_update': 'Systemeinstellungen geändert',
  }
  return labels[action] || action
}

function toggleLogDetails(logId) {
  expandedLogId.value = expandedLogId.value === logId ? null : logId
}

function parseJsonSafe(str) {
  if (!str) return null
  try {
    return typeof str === 'string' ? JSON.parse(str) : str
  } catch {
    return null
  }
}

function applyFilters() {
  auditPagination.value.page = 1
  loadAuditLogs()
}

function resetFilters() {
  auditFilters.value = { action: '', user_id: '', entity_type: '' }
  auditPagination.value.page = 1
  loadAuditLogs()
}

function getActionColor(action) {
  if (!action) return 'text-gray-400'
  if (action.includes('delete') || action.includes('terminate')) return 'text-red-400'
  if (action.includes('create') || action.includes('register')) return 'text-green-400'
  if (action.includes('login')) return 'text-blue-400'
  if (action.includes('update')) return 'text-yellow-400'
  return 'text-gray-400'
}

async function loadSystemInfo() {
  try {
    const response = await api.get('/api/v1/system/info')
    if (response.data.data) {
      systemInfo.value = { ...systemInfo.value, ...response.data.data }
    }
  } catch (err) {
    console.error('Could not load system info:', err)
  } finally {
    isLoading.value = false
  }
}

async function loadMetrics() {
  try {
    const response = await api.get('/api/v1/system/metrics')
    if (response.data.data) {
      metrics.value = response.data.data
    }
  } catch (err) {
    console.error('Could not load metrics:', err)
  } finally {
    isLoadingMetrics.value = false
  }
}

async function loadAuditLogs() {
  isLoadingLogs.value = true
  try {
    const params = {
      page: auditPagination.value.page,
      per_page: auditPagination.value.perPage,
    }
    if (auditFilters.value.action) params.action = auditFilters.value.action
    if (auditFilters.value.user_id) params.user_id = auditFilters.value.user_id
    if (auditFilters.value.entity_type) params.entity_type = auditFilters.value.entity_type

    const response = await api.get('/api/v1/system/audit-logs', { params })
    if (response.data.data) {
      auditLogs.value = response.data.data
      auditPagination.value.total = response.data.pagination?.total || 0
    }
  } catch (err) {
    console.error('Could not load audit logs:', err)
  } finally {
    isLoadingLogs.value = false
  }
}

async function clearCache() {
  if (!await confirm({ message: 'Möchten Sie wirklich den Cache leeren?', type: 'warning', confirmText: 'Bestätigen' })) return

  isClearingCache.value = true
  try {
    await api.post('/api/v1/system/clear-cache')
    toast.success('Cache wurde erfolgreich geleert')
    loadAuditLogs()
  } catch (err) {
    toast.error('Fehler beim Leeren des Caches: ' + (err.response?.data?.error || err.message))
  } finally {
    isClearingCache.value = false
  }
}

async function terminateSessions() {
  if (!await confirm({ message: 'Möchten Sie wirklich alle Benutzer-Sessions beenden? Alle Benutzer werden abgemeldet.', type: 'warning', confirmText: 'Bestätigen' })) return

  isTerminatingSessions.value = true
  try {
    const response = await api.post('/api/v1/system/terminate-sessions')
    toast.success(response.data.message || 'Sessions wurden erfolgreich beendet')
    loadAuditLogs()
  } catch (err) {
    toast.error('Fehler beim Beenden der Sessions: ' + (err.response?.data?.error || err.message))
  } finally {
    isTerminatingSessions.value = false
  }
}

function prevPage() {
  if (auditPagination.value.page > 1) {
    auditPagination.value.page--
    loadAuditLogs()
  }
}

function nextPage() {
  if (auditPagination.value.page < totalPages.value) {
    auditPagination.value.page++
    loadAuditLogs()
  }
}

onMounted(async () => {
  await Promise.all([loadSystemInfo(), loadMetrics(), loadAuditLogs()])

  // Update metrics every 30 seconds
  metricsInterval = setInterval(loadMetrics, 30000)
})

onUnmounted(() => {
  if (metricsInterval) {
    clearInterval(metricsInterval)
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-white">System</h1>
      <p class="text-gray-400 mt-1">Systemeinstellungen und -informationen (nur Owner)</p>
    </div>

    <!-- System Info Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
      <div class="bg-dark-800 rounded-lg border border-dark-700 p-4">
        <h3 class="text-xs font-medium text-gray-400 uppercase tracking-wider">Version</h3>
        <p class="mt-1 text-xl font-bold text-white">{{ systemInfo.version }}</p>
      </div>

      <div class="bg-dark-800 rounded-lg border border-dark-700 p-4">
        <h3 class="text-xs font-medium text-gray-400 uppercase tracking-wider">Umgebung</h3>
        <p class="mt-1 text-xl font-bold text-white capitalize">{{ systemInfo.environment }}</p>
      </div>

      <div class="bg-dark-800 rounded-lg border border-dark-700 p-4">
        <h3 class="text-xs font-medium text-gray-400 uppercase tracking-wider">PHP Version</h3>
        <p class="mt-1 text-xl font-bold text-white">{{ systemInfo.phpVersion }}</p>
      </div>

      <div class="bg-dark-800 rounded-lg border border-dark-700 p-4">
        <h3 class="text-xs font-medium text-gray-400 uppercase tracking-wider">MySQL</h3>
        <p class="mt-1 text-xl font-bold text-white">{{ systemInfo.mysqlVersion }}</p>
      </div>

      <div class="bg-dark-800 rounded-lg border border-dark-700 p-4">
        <h3 class="text-xs font-medium text-gray-400 uppercase tracking-wider">Uptime</h3>
        <p class="mt-1 text-xl font-bold text-white">{{ metrics.uptime }}</p>
      </div>
    </div>

    <!-- Resource Monitoring -->
    <div class="bg-dark-800 rounded-lg border border-dark-700 p-6">
      <h2 class="text-lg font-semibold text-white mb-4">Ressourcen-Monitoring</h2>

      <div v-if="isLoadingMetrics" class="text-gray-400">Lade Metriken...</div>

      <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- CPU -->
        <div>
          <div class="flex justify-between text-sm mb-2">
            <span class="text-gray-400">CPU ({{ metrics.cpu.cores }} Kerne)</span>
            <span class="text-white font-medium">{{ metrics.cpu.usage_percent }}%</span>
          </div>
          <div class="w-full bg-dark-700 rounded-full h-3">
            <div
              :class="['h-3 rounded-full transition-all duration-500', getProgressColor(metrics.cpu.usage_percent)]"
              :style="{ width: metrics.cpu.usage_percent + '%' }"
            ></div>
          </div>
          <p class="text-xs text-gray-500 mt-1">Load: {{ metrics.cpu.load_1m }} (1m)</p>
        </div>

        <!-- Memory -->
        <div>
          <div class="flex justify-between text-sm mb-2">
            <span class="text-gray-400">Speicher</span>
            <span class="text-white font-medium">{{ metrics.memory.usage_percent }}%</span>
          </div>
          <div class="w-full bg-dark-700 rounded-full h-3">
            <div
              :class="['h-3 rounded-full transition-all duration-500', getProgressColor(metrics.memory.usage_percent)]"
              :style="{ width: metrics.memory.usage_percent + '%' }"
            ></div>
          </div>
          <p class="text-xs text-gray-500 mt-1">{{ metrics.memory.used_formatted }} / {{ metrics.memory.total_formatted }}</p>
        </div>

        <!-- Disk -->
        <div>
          <div class="flex justify-between text-sm mb-2">
            <span class="text-gray-400">Festplatte</span>
            <span class="text-white font-medium">{{ metrics.disk.usage_percent }}%</span>
          </div>
          <div class="w-full bg-dark-700 rounded-full h-3">
            <div
              :class="['h-3 rounded-full transition-all duration-500', getProgressColor(metrics.disk.usage_percent)]"
              :style="{ width: metrics.disk.usage_percent + '%' }"
            ></div>
          </div>
          <p class="text-xs text-gray-500 mt-1">{{ metrics.disk.used_formatted }} / {{ metrics.disk.total_formatted }}</p>
        </div>
      </div>
    </div>

    <!-- Database Stats -->
    <div class="bg-dark-800 rounded-lg border border-dark-700 p-6">
      <h2 class="text-lg font-semibold text-white mb-4">Datenbank-Statistiken</h2>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="text-center p-4 bg-dark-700 rounded-lg">
          <p class="text-2xl font-bold text-blue-400">{{ metrics.database.users || 0 }}</p>
          <p class="text-sm text-gray-400">Benutzer</p>
        </div>
        <div class="text-center p-4 bg-dark-700 rounded-lg">
          <p class="text-2xl font-bold text-green-400">{{ metrics.database.lists || 0 }}</p>
          <p class="text-sm text-gray-400">Listen</p>
        </div>
        <div class="text-center p-4 bg-dark-700 rounded-lg">
          <p class="text-2xl font-bold text-yellow-400">{{ metrics.database.documents || 0 }}</p>
          <p class="text-sm text-gray-400">Dokumente</p>
        </div>
        <div class="text-center p-4 bg-dark-700 rounded-lg">
          <p class="text-2xl font-bold text-purple-400">{{ metrics.database.audit_logs || 0 }}</p>
          <p class="text-sm text-gray-400">Audit Logs</p>
        </div>
      </div>
    </div>

    <!-- Danger Zone -->
    <div class="bg-dark-800 rounded-lg border border-red-900 p-6">
      <h2 class="text-lg font-semibold text-red-400 mb-4">Gefahrenzone</h2>
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-white font-medium">Cache leeren</h3>
            <p class="text-sm text-gray-400">Leert alle zwischengespeicherten Daten</p>
          </div>
          <button
            @click="clearCache"
            :disabled="isClearingCache"
            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ isClearingCache ? 'Wird geleert...' : 'Cache leeren' }}
          </button>
        </div>
        <div class="border-t border-dark-700"></div>
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-white font-medium">Alle Sessions beenden</h3>
            <p class="text-sm text-gray-400">Meldet alle Benutzer ab (außer Sie selbst)</p>
          </div>
          <button
            @click="terminateSessions"
            :disabled="isTerminatingSessions"
            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ isTerminatingSessions ? 'Wird beendet...' : 'Sessions beenden' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Audit Log -->
    <div class="bg-dark-800 rounded-lg border border-dark-700 p-6">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-white">Audit Log</h2>
        <span class="text-sm text-gray-400">{{ auditPagination.total }} Einträge</span>
      </div>

      <!-- Filters -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 p-4 bg-dark-700/50 rounded-lg">
        <div>
          <label class="block text-xs text-gray-400 mb-1">Entitätstyp</label>
          <select
            v-model="auditFilters.entity_type"
            class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white text-sm focus:outline-none focus:border-primary-500"
          >
            <option v-for="type in availableEntityTypes" :key="type.value" :value="type.value">
              {{ type.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Aktion enthält</label>
          <input
            v-model="auditFilters.action"
            type="text"
            placeholder="z.B. create, delete"
            class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white text-sm focus:outline-none focus:border-primary-500"
          />
        </div>
        <div class="flex items-end gap-2">
          <button
            @click="applyFilters"
            class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm"
          >
            Filtern
          </button>
          <button
            @click="resetFilters"
            class="px-4 py-2 bg-dark-600 text-white rounded-lg hover:bg-dark-500 transition-colors text-sm"
          >
            Zurücksetzen
          </button>
        </div>
      </div>

      <div v-if="isLoadingLogs" class="text-gray-400 py-4">Lade Audit Logs...</div>

      <div v-else-if="auditLogs.length === 0" class="text-gray-400 py-4">Keine Audit Logs vorhanden</div>

      <div v-else>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-gray-400 border-b border-dark-700">
                <th class="pb-3 font-medium w-8"></th>
                <th class="pb-3 font-medium">Zeitpunkt</th>
                <th class="pb-3 font-medium">Benutzer</th>
                <th class="pb-3 font-medium">Aktion</th>
                <th class="pb-3 font-medium">Entität</th>
                <th class="pb-3 font-medium">IP</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-dark-700">
              <template v-for="log in auditLogs" :key="log.id">
                <tr
                  @click="toggleLogDetails(log.id)"
                  class="hover:bg-dark-700/50 cursor-pointer"
                  :class="{ 'bg-dark-700/30': expandedLogId === log.id }"
                >
                  <td class="py-3 text-gray-400">
                    <svg
                      class="w-4 h-4 transition-transform"
                      :class="{ 'rotate-90': expandedLogId === log.id }"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                  </td>
                  <td class="py-3 text-gray-300">{{ formatDate(log.created_at) }}</td>
                  <td class="py-3 text-gray-300">{{ log.user_name || log.user_email || 'System' }}</td>
                  <td class="py-3">
                    <span :class="getActionColor(log.action)">{{ getActionLabel(log.action) }}</span>
                  </td>
                  <td class="py-3 text-gray-400">
                    {{ log.entity_type }}
                    <span v-if="log.entity_id" class="text-gray-500 text-xs ml-1">({{ log.entity_id.substring(0, 8) }}...)</span>
                  </td>
                  <td class="py-3 text-gray-500 text-xs font-mono">{{ log.ip_address || '-' }}</td>
                </tr>
                <!-- Expanded Details Row -->
                <tr v-if="expandedLogId === log.id">
                  <td colspan="6" class="bg-dark-700/20 p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <!-- General Info -->
                      <div class="space-y-2">
                        <h4 class="text-sm font-medium text-white">Details</h4>
                        <div class="text-xs space-y-1">
                          <p><span class="text-gray-500">ID:</span> <span class="text-gray-300 font-mono">{{ log.id }}</span></p>
                          <p><span class="text-gray-500">Entität ID:</span> <span class="text-gray-300 font-mono">{{ log.entity_id || '-' }}</span></p>
                          <p><span class="text-gray-500">User Agent:</span> <span class="text-gray-300">{{ log.user_agent || '-' }}</span></p>
                        </div>
                      </div>
                      <!-- Old Values -->
                      <div v-if="parseJsonSafe(log.old_values)" class="space-y-2">
                        <h4 class="text-sm font-medium text-red-400">Vorherige Werte</h4>
                        <pre class="text-xs bg-dark-800 rounded p-2 overflow-x-auto text-gray-300">{{ JSON.stringify(parseJsonSafe(log.old_values), null, 2) }}</pre>
                      </div>
                      <!-- New Values -->
                      <div v-if="parseJsonSafe(log.new_values)" class="space-y-2">
                        <h4 class="text-sm font-medium text-green-400">Neue Werte</h4>
                        <pre class="text-xs bg-dark-800 rounded p-2 overflow-x-auto text-gray-300">{{ JSON.stringify(parseJsonSafe(log.new_values), null, 2) }}</pre>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex justify-between items-center mt-4 pt-4 border-t border-dark-700">
          <button
            @click="prevPage"
            :disabled="auditPagination.page <= 1"
            class="px-3 py-1 bg-dark-700 text-white rounded hover:bg-dark-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Zurück
          </button>
          <span class="text-gray-400">Seite {{ auditPagination.page }} von {{ totalPages }}</span>
          <button
            @click="nextPage"
            :disabled="auditPagination.page >= totalPages"
            class="px-3 py-1 bg-dark-700 text-white rounded hover:bg-dark-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Weiter
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
