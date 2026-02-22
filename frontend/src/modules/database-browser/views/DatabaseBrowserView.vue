<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/core/api/axios'
import { useToast } from '@/composables/useToast'
import MonacoEditor from '@/components/MonacoEditor.vue'
import {
  CircleStackIcon,
  TableCellsIcon,
  MagnifyingGlassIcon,
  PlayIcon,
  ClockIcon,
  ChevronRightIcon,
  ChevronDownIcon,
  ArrowPathIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

const toast = useToast()

// =========================================================================
// State
// =========================================================================

const connections  = ref([])
const loadingConns = ref(true)

const selectedConn   = ref(null)
const schemas        = ref([])
const selectedSchema = ref('')
const tables         = ref([])
const loadingTables  = ref(false)
const expandedSchema = ref('')

const selectedTable   = ref(null)
const tableSchema     = ref([])
const tableRows       = ref([])
const tableTotal      = ref(0)
const tableOffset     = ref(0)
const tableLimit      = ref(100)
const loadingRows     = ref(false)

const activeTab = ref('browser') // browser | query | history

const sqlQuery      = ref('SELECT * FROM ')
const queryResult   = ref(null)
const queryError    = ref('')
const queryRunning  = ref(false)
const queryAllowWrite = ref(false)

const history  = ref([])
const loadingHistory = ref(false)

// =========================================================================
// Connections
// =========================================================================

async function loadConnections() {
  loadingConns.value = true
  try {
    const res = await api.get('/api/v1/database/connections')
    connections.value = res.data.data.items || []
  } catch (err) {
    toast.error('Fehler beim Laden der Verbindungen')
  } finally {
    loadingConns.value = false
  }
}

async function selectConnection(conn) {
  selectedConn.value   = conn
  selectedSchema.value = ''
  selectedTable.value  = null
  tables.value         = []
  tableRows.value      = []
  queryResult.value    = null

  try {
    const res = await api.get(`/api/v1/database/connections/${conn.id}/schemas`)
    schemas.value = res.data.data.items || []
    if (schemas.value.length === 1) {
      selectSchema(schemas.value[0].schema_name)
    }
  } catch {
    toast.error('Fehler beim Laden der Schemas')
  }
}

async function selectSchema(name) {
  selectedSchema.value = name
  expandedSchema.value = name
  selectedTable.value  = null
  tableRows.value      = []
  loadingTables.value  = true

  try {
    const res = await api.get(`/api/v1/database/connections/${selectedConn.value.id}/tables`, {
      params: { database: name },
    })
    tables.value = res.data.data.items || []
  } catch {
    toast.error('Fehler beim Laden der Tabellen')
  } finally {
    loadingTables.value = false
  }
}

async function selectTable(table) {
  selectedTable.value = table
  tableOffset.value   = 0
  tableRows.value     = []
  activeTab.value     = 'browser'

  // Load schema + rows in parallel
  loadingRows.value = true
  try {
    const [schemaRes, rowsRes] = await Promise.all([
      api.get(`/api/v1/database/connections/${selectedConn.value.id}/tables/${table.table_name}/schema`, {
        params: { database: selectedSchema.value },
      }),
      api.get(`/api/v1/database/connections/${selectedConn.value.id}/tables/${table.table_name}/rows`, {
        params: { database: selectedSchema.value, limit: tableLimit.value, offset: 0 },
      }),
    ])
    tableSchema.value = schemaRes.data.data.columns || []
    tableRows.value   = rowsRes.data.data.rows     || []
    tableTotal.value  = rowsRes.data.data.total    || 0
  } catch {
    toast.error('Fehler beim Laden der Tabellendaten')
  } finally {
    loadingRows.value = false
  }
}

async function loadMoreRows(direction) {
  if (!selectedTable.value) return
  const newOffset = direction === 'next'
    ? tableOffset.value + tableLimit.value
    : Math.max(0, tableOffset.value - tableLimit.value)

  if (newOffset < 0 || newOffset >= tableTotal.value) return

  tableOffset.value = newOffset
  loadingRows.value = true
  try {
    const res = await api.get(
      `/api/v1/database/connections/${selectedConn.value.id}/tables/${selectedTable.value.table_name}/rows`,
      { params: { database: selectedSchema.value, limit: tableLimit.value, offset: newOffset } }
    )
    tableRows.value = res.data.data.rows || []
  } catch {
    toast.error('Fehler beim Laden der Zeilen')
  } finally {
    loadingRows.value = false
  }
}

// =========================================================================
// Query
// =========================================================================

async function runQuery() {
  if (!selectedConn.value || !sqlQuery.value.trim()) return

  queryRunning.value = true
  queryError.value   = ''
  queryResult.value  = null

  try {
    const res = await api.post(`/api/v1/database/connections/${selectedConn.value.id}/query`, {
      query:       sqlQuery.value,
      database:    selectedSchema.value,
      allow_write: queryAllowWrite.value,
    })
    queryResult.value = res.data.data
    toast.success(`${res.data.data.row_count} Zeilen in ${res.data.data.duration_ms}ms`)
    loadHistory()
  } catch (err) {
    queryError.value = err.response?.data?.message || 'Query fehlgeschlagen'
    toast.error(queryError.value)
  } finally {
    queryRunning.value = false
  }
}

function useHistoryQuery(q) {
  sqlQuery.value = q
  activeTab.value = 'query'
}

async function loadHistory() {
  if (!selectedConn.value) return
  loadingHistory.value = true
  try {
    const res = await api.get(`/api/v1/database/connections/${selectedConn.value.id}/history`)
    history.value = res.data.data.items || []
  } catch {
    // ignore
  } finally {
    loadingHistory.value = false
  }
}

// =========================================================================
// Computed
// =========================================================================

const columns = computed(() => {
  if (activeTab.value === 'browser' && tableRows.value.length) {
    return Object.keys(tableRows.value[0])
  }
  if (activeTab.value === 'query' && queryResult.value?.rows?.length) {
    return queryResult.value.columns || Object.keys(queryResult.value.rows[0])
  }
  return []
})

const displayRows = computed(() => {
  if (activeTab.value === 'browser') return tableRows.value
  if (activeTab.value === 'query') return queryResult.value?.rows || []
  return []
})

// =========================================================================
// Init
// =========================================================================

onMounted(loadConnections)
</script>

<template>
  <div class="flex h-full gap-0 overflow-hidden rounded-xl border border-dark-700">
    <!-- Left sidebar: connections → schemas → tables -->
    <div class="w-64 flex-shrink-0 flex flex-col bg-dark-900 border-r border-dark-700">
      <div class="px-4 py-3 border-b border-dark-700 flex items-center justify-between">
        <h2 class="font-semibold text-white flex items-center gap-2 text-sm">
          <CircleStackIcon class="w-4 h-4 text-primary-400" />
          Datenbanken
        </h2>
        <button @click="loadConnections" class="p-1 text-gray-400 hover:text-white rounded">
          <ArrowPathIcon class="w-3.5 h-3.5" />
        </button>
      </div>

      <div class="flex-1 overflow-y-auto p-2 space-y-1">
        <!-- Connections -->
        <div v-if="loadingConns" class="text-center py-4">
          <div class="w-5 h-5 border-2 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>

        <div v-else-if="connections.length === 0" class="text-gray-500 text-xs text-center py-6 px-2">
          Keine Datenbankverbindungen.<br>Erstelle eine im Connections-Modul.
        </div>

        <template v-for="conn in connections" :key="conn.id">
          <button
            @click="selectConnection(conn)"
            class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center gap-2"
            :class="selectedConn?.id === conn.id ? 'bg-primary-500/20 text-white' : 'text-gray-400 hover:bg-dark-700 hover:text-white'"
          >
            <CircleStackIcon class="w-4 h-4 flex-shrink-0" />
            <span class="truncate">{{ conn.name }}</span>
          </button>

          <!-- Schemas for selected connection -->
          <template v-if="selectedConn?.id === conn.id">
            <div
              v-for="schema in schemas"
              :key="schema.schema_name"
              class="ml-3"
            >
              <button
                @click="selectSchema(schema.schema_name)"
                class="w-full text-left px-2 py-1.5 rounded text-xs flex items-center gap-1.5 transition-colors"
                :class="selectedSchema === schema.schema_name ? 'text-primary-400' : 'text-gray-500 hover:text-gray-300'"
              >
                <component
                  :is="expandedSchema === schema.schema_name ? ChevronDownIcon : ChevronRightIcon"
                  class="w-3 h-3 flex-shrink-0"
                />
                {{ schema.schema_name }}
              </button>

              <!-- Tables -->
              <template v-if="expandedSchema === schema.schema_name">
                <div v-if="loadingTables" class="ml-4 py-2">
                  <div class="w-4 h-4 border-2 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
                </div>
                <button
                  v-for="table in tables"
                  :key="table.table_name"
                  @click="selectTable(table)"
                  class="w-full text-left ml-4 px-2 py-1 rounded text-xs flex items-center gap-1.5 transition-colors"
                  :class="selectedTable?.table_name === table.table_name ? 'text-white bg-dark-700' : 'text-gray-500 hover:text-gray-300'"
                >
                  <TableCellsIcon class="w-3 h-3 flex-shrink-0" />
                  <span class="truncate">{{ table.table_name }}</span>
                </button>
              </template>
            </div>
          </template>
        </template>
      </div>
    </div>

    <!-- Right panel -->
    <div class="flex-1 flex flex-col min-w-0 bg-dark-850">
      <!-- No connection selected -->
      <div v-if="!selectedConn" class="flex-1 flex items-center justify-center text-gray-500">
        <div class="text-center">
          <CircleStackIcon class="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p>Wähle eine Datenbankverbindung</p>
          <p class="text-xs mt-1 text-gray-600">Verbindungen werden im Connections-Modul erstellt</p>
        </div>
      </div>

      <template v-else>
        <!-- Tabs -->
        <div class="flex items-center gap-1 px-4 pt-3 pb-0 border-b border-dark-700 flex-shrink-0">
          <button
            v-for="tab in [{ id: 'browser', label: 'Tabellen-Browser', icon: TableCellsIcon }, { id: 'query', label: 'SQL-Editor', icon: MagnifyingGlassIcon }, { id: 'history', label: 'Verlauf', icon: ClockIcon }]"
            :key="tab.id"
            @click="activeTab = tab.id; if (tab.id === 'history') loadHistory()"
            class="flex items-center gap-1.5 px-3 py-2 text-sm border-b-2 transition-colors -mb-px"
            :class="activeTab === tab.id ? 'border-primary-500 text-white' : 'border-transparent text-gray-400 hover:text-white'"
          >
            <component :is="tab.icon" class="w-4 h-4" />
            {{ tab.label }}
          </button>
        </div>

        <!-- TABLE BROWSER TAB -->
        <div v-if="activeTab === 'browser'" class="flex-1 flex flex-col min-h-0">
          <div v-if="!selectedTable" class="flex-1 flex items-center justify-center text-gray-500 text-sm">
            <div class="text-center">
              <TableCellsIcon class="w-8 h-8 mx-auto mb-2 opacity-30" />
              Tabelle in der Sidebar auswählen
            </div>
          </div>

          <template v-else>
            <!-- Table header info -->
            <div class="flex items-center justify-between px-4 py-2 border-b border-dark-700 flex-shrink-0">
              <span class="text-white font-mono text-sm font-semibold">{{ selectedSchema }}.{{ selectedTable.table_name }}</span>
              <span class="text-gray-500 text-xs">{{ tableTotal.toLocaleString() }} Zeilen gesamt</span>
            </div>

            <!-- Data grid -->
            <div class="flex-1 overflow-auto min-h-0">
              <div v-if="loadingRows" class="flex items-center justify-center py-12">
                <div class="w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
              </div>

              <table v-else-if="tableRows.length" class="w-full text-xs font-mono border-collapse min-w-max">
                <thead class="sticky top-0 bg-dark-900 z-10">
                  <tr>
                    <th
                      v-for="col in columns"
                      :key="col"
                      class="text-left px-3 py-2 text-gray-400 font-semibold border-b border-dark-700 whitespace-nowrap"
                    >
                      {{ col }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(row, idx) in tableRows"
                    :key="idx"
                    class="border-b border-dark-800 hover:bg-dark-700/30 transition-colors"
                  >
                    <td
                      v-for="col in columns"
                      :key="col"
                      class="px-3 py-1.5 text-gray-300 max-w-xs truncate"
                      :title="String(row[col] ?? '')"
                    >
                      <span v-if="row[col] === null" class="text-gray-600 italic">NULL</span>
                      <span v-else>{{ row[col] }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>

              <div v-else class="text-center text-gray-500 py-8 text-sm">Keine Daten</div>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between px-4 py-2 border-t border-dark-700 flex-shrink-0 text-xs text-gray-400">
              <span>Zeilen {{ tableOffset + 1 }} – {{ Math.min(tableOffset + tableLimit, tableTotal) }} von {{ tableTotal.toLocaleString() }}</span>
              <div class="flex gap-2">
                <button
                  @click="loadMoreRows('prev')"
                  :disabled="tableOffset === 0 || loadingRows"
                  class="px-3 py-1 bg-dark-700 rounded hover:bg-dark-600 disabled:opacity-40 transition-colors"
                >Zurück</button>
                <button
                  @click="loadMoreRows('next')"
                  :disabled="tableOffset + tableLimit >= tableTotal || loadingRows"
                  class="px-3 py-1 bg-dark-700 rounded hover:bg-dark-600 disabled:opacity-40 transition-colors"
                >Weiter</button>
              </div>
            </div>
          </template>
        </div>

        <!-- SQL EDITOR TAB -->
        <div v-if="activeTab === 'query'" class="flex-1 flex flex-col min-h-0">
          <!-- Monaco Editor -->
          <div class="h-48 flex-shrink-0 border-b border-dark-700">
            <MonacoEditor
              v-model="sqlQuery"
              language="sql"
              :options="{ minimap: { enabled: false }, fontSize: 13, lineNumbers: 'on', scrollBeyondLastLine: false }"
            />
          </div>

          <!-- Toolbar -->
          <div class="flex items-center gap-3 px-4 py-2 border-b border-dark-700 flex-shrink-0">
            <button
              @click="runQuery"
              :disabled="queryRunning"
              class="flex items-center gap-1.5 px-4 py-1.5 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-500 disabled:opacity-50 transition-colors"
            >
              <PlayIcon class="w-4 h-4" />
              {{ queryRunning ? 'Wird ausgeführt…' : 'Ausführen' }}
            </button>

            <label class="flex items-center gap-2 text-xs text-gray-400 cursor-pointer select-none">
              <input v-model="queryAllowWrite" type="checkbox" class="rounded" />
              Schreibzugriff erlauben
            </label>

            <span v-if="queryResult" class="text-xs text-gray-500 ml-auto">
              {{ queryResult.row_count }} Zeilen · {{ queryResult.duration_ms }}ms
            </span>
          </div>

          <!-- Error -->
          <div v-if="queryError" class="mx-4 mt-2 px-3 py-2 bg-red-500/10 border border-red-500/30 rounded text-red-400 text-xs flex-shrink-0">
            {{ queryError }}
          </div>

          <!-- Results -->
          <div class="flex-1 overflow-auto min-h-0">
            <table v-if="displayRows.length" class="w-full text-xs font-mono border-collapse min-w-max">
              <thead class="sticky top-0 bg-dark-900 z-10">
                <tr>
                  <th
                    v-for="col in columns"
                    :key="col"
                    class="text-left px-3 py-2 text-gray-400 font-semibold border-b border-dark-700 whitespace-nowrap"
                  >{{ col }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(row, idx) in displayRows"
                  :key="idx"
                  class="border-b border-dark-800 hover:bg-dark-700/30"
                >
                  <td
                    v-for="col in columns"
                    :key="col"
                    class="px-3 py-1.5 text-gray-300 max-w-xs truncate"
                    :title="String(row[col] ?? '')"
                  >
                    <span v-if="row[col] === null" class="text-gray-600 italic">NULL</span>
                    <span v-else>{{ row[col] }}</span>
                  </td>
                </tr>
              </tbody>
            </table>

            <div v-else-if="!queryRunning && !queryError" class="flex items-center justify-center py-12 text-gray-500 text-sm">
              Query schreiben und ausführen
            </div>
          </div>
        </div>

        <!-- HISTORY TAB -->
        <div v-if="activeTab === 'history'" class="flex-1 overflow-y-auto">
          <div v-if="loadingHistory" class="flex justify-center py-8">
            <div class="w-5 h-5 border-2 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
          </div>

          <div v-else-if="history.length === 0" class="text-center py-12 text-gray-500 text-sm">
            Keine Queries ausgeführt
          </div>

          <div v-else class="divide-y divide-dark-700">
            <div
              v-for="item in history"
              :key="item.id"
              class="px-4 py-3 hover:bg-dark-700/30 transition-colors group"
            >
              <div class="flex items-start justify-between gap-3">
                <pre class="flex-1 text-xs font-mono text-gray-300 truncate overflow-hidden whitespace-nowrap">{{ item.query }}</pre>
                <button
                  @click="useHistoryQuery(item.query)"
                  class="flex-shrink-0 text-xs text-primary-400 hover:text-primary-300 opacity-0 group-hover:opacity-100 transition-opacity"
                >
                  Verwenden
                </button>
              </div>
              <div class="flex items-center gap-4 mt-1 text-xs text-gray-600">
                <span>{{ item.rows_returned ?? 0 }} Zeilen</span>
                <span v-if="item.duration_ms">{{ item.duration_ms }}ms</span>
                <span>{{ item.executed_at }}</span>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
