<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/core/api/axios'
import { useToast } from '@/composables/useToast'
import MonacoEditor from '@/components/MonacoEditor.vue'
import {
  CommandLineIcon,
  PlusIcon,
  TrashIcon,
  PlayIcon,
  StarIcon,
  MagnifyingGlassIcon,
  ArrowPathIcon,
  ServerIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline'

const toast = useToast()

// =========================================================================
// State
// =========================================================================

const scripts      = ref([])
const connections  = ref([])
const loading      = ref(false)
const running      = ref(false)
const searchQuery  = ref('')
const filterLang   = ref('')

const selectedScript = ref(null)
const editForm       = ref(null)
const isNew          = ref(false)

const selectedConnectionId = ref('')
const output               = ref(null)
const activeTab            = ref('editor') // editor | history

const history = ref([])
const historyLoading = ref(false)

const languages = ['bash', 'python', 'php', 'node']

const langMonaco = {
  bash:   'shell',
  python: 'python',
  php:    'php',
  node:   'javascript',
}

// =========================================================================
// Data loading
// =========================================================================

async function loadScripts() {
  loading.value = true
  try {
    const res = await api.get('/api/v1/scripts')
    scripts.value = res.data.data.scripts || []
  } catch (err) {
    toast.error('Fehler beim Laden der Scripts')
  } finally {
    loading.value = false
  }
}

async function loadConnections() {
  try {
    const res = await api.get('/api/v1/connections')
    connections.value = (res.data.data || []).filter(
      c => c.type === 'ssh' || c.type === 'server'
    )
  } catch {
    // connections not required
  }
}

const filteredScripts = computed(() => {
  let list = scripts.value
  if (filterLang.value) {
    list = list.filter(s => s.language === filterLang.value)
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(s =>
      s.name.toLowerCase().includes(q) ||
      (s.description || '').toLowerCase().includes(q)
    )
  }
  return list
})

// =========================================================================
// Script selection / editing
// =========================================================================

function selectScript(script) {
  selectedScript.value = script
  editForm.value = { ...script, tags: Array.isArray(script.tags) ? [...script.tags] : [] }
  isNew.value = false
  output.value = null
  activeTab.value = 'editor'
}

function newScript() {
  const fresh = {
    id: null,
    name: 'Neues Script',
    description: '',
    language: 'bash',
    content: '#!/bin/bash\n\necho "Hello World"',
    tags: [],
    is_favorite: false,
  }
  selectedScript.value = fresh
  editForm.value = { ...fresh }
  isNew.value = true
  output.value = null
  activeTab.value = 'editor'
}

async function saveScript() {
  if (!editForm.value) return
  try {
    let res
    const payload = {
      name:        editForm.value.name,
      description: editForm.value.description,
      language:    editForm.value.language,
      content:     editForm.value.content,
      tags:        editForm.value.tags,
    }

    if (isNew.value) {
      res = await api.post('/api/v1/scripts', payload)
      const created = res.data.data.script
      scripts.value.unshift(created)
      selectedScript.value = created
      editForm.value = { ...created, tags: Array.isArray(created.tags) ? [...created.tags] : [] }
      isNew.value = false
      toast.success('Script erstellt')
    } else {
      res = await api.put(`/api/v1/scripts/${editForm.value.id}`, payload)
      const updated = res.data.data.script
      const idx = scripts.value.findIndex(s => s.id === updated.id)
      if (idx !== -1) scripts.value[idx] = updated
      selectedScript.value = updated
      toast.success('Script gespeichert')
    }
  } catch (err) {
    toast.error('Fehler beim Speichern: ' + (err.response?.data?.message || err.message))
  }
}

async function deleteScript(script) {
  if (!confirm(`Script "${script.name}" wirklich löschen?`)) return
  try {
    await api.delete(`/api/v1/scripts/${script.id}`)
    scripts.value = scripts.value.filter(s => s.id !== script.id)
    if (selectedScript.value?.id === script.id) {
      selectedScript.value = null
      editForm.value = null
    }
    toast.success('Script gelöscht')
  } catch {
    toast.error('Fehler beim Löschen')
  }
}

async function toggleFavorite(script) {
  try {
    const res = await api.put(`/api/v1/scripts/${script.id}`, {
      is_favorite: !script.is_favorite,
    })
    const updated = res.data.data.script
    const idx = scripts.value.findIndex(s => s.id === script.id)
    if (idx !== -1) scripts.value[idx] = updated
    if (selectedScript.value?.id === updated.id) {
      selectedScript.value = updated
    }
  } catch {
    toast.error('Fehler')
  }
}

// =========================================================================
// Execution
// =========================================================================

async function runScript() {
  if (!editForm.value?.id || running.value) return

  // Auto-save first
  await saveScript()

  running.value = true
  output.value = null
  const startTime = Date.now()

  try {
    const payload = {}
    if (selectedConnectionId.value) {
      payload.connection_id = selectedConnectionId.value
    }

    const res = await api.post(`/api/v1/scripts/${editForm.value.id}/run`, payload, {
      timeout: 120000,
    })
    const data = res.data.data

    output.value = {
      stdout:    data.stdout || '',
      stderr:    data.stderr || '',
      exit_code: data.exit_code,
      duration:  data.duration_ms || (Date.now() - startTime),
      success:   data.exit_code === 0,
    }

    if (data.exit_code === 0) {
      toast.success('Script erfolgreich ausgeführt')
    } else {
      toast.error(`Script beendet mit Code ${data.exit_code}`)
    }
  } catch (err) {
    output.value = {
      stdout:    '',
      stderr:    err.response?.data?.message || err.message,
      exit_code: -1,
      duration:  Date.now() - startTime,
      success:   false,
    }
    toast.error('Fehler bei der Ausführung')
  } finally {
    running.value = false
  }
}

// =========================================================================
// History
// =========================================================================

async function loadHistory() {
  if (!selectedScript.value?.id) return
  historyLoading.value = true
  try {
    const res = await api.get(`/api/v1/scripts/${selectedScript.value.id}/history`)
    history.value = res.data.data.executions || []
  } catch {
    toast.error('Verlauf konnte nicht geladen werden')
  } finally {
    historyLoading.value = false
  }
}

function switchTab(tab) {
  activeTab.value = tab
  if (tab === 'history' && selectedScript.value?.id) {
    loadHistory()
  }
}

function formatDuration(ms) {
  if (ms < 1000) return `${ms}ms`
  return `${(ms / 1000).toFixed(1)}s`
}

function formatTs(ts) {
  if (!ts) return ''
  return new Date(ts).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'medium' })
}

// =========================================================================
// Lifecycle
// =========================================================================

onMounted(async () => {
  await Promise.all([loadScripts(), loadConnections()])
})
</script>

<template>
  <div class="flex h-full gap-0 overflow-hidden rounded-xl border border-dark-700">
    <!-- Sidebar -->
    <div class="w-64 flex-shrink-0 flex flex-col bg-dark-900 border-r border-dark-700">
      <!-- Header -->
      <div class="px-4 py-3 border-b border-dark-700">
        <div class="flex items-center justify-between mb-2">
          <h2 class="font-semibold text-white text-sm flex items-center gap-2">
            <CommandLineIcon class="w-4 h-4 text-primary-400" />
            Script Vault
          </h2>
          <button
            @click="newScript"
            class="p-1 rounded-lg bg-primary-600 hover:bg-primary-500 text-white transition-colors"
            title="Neues Script"
          >
            <PlusIcon class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- Search -->
        <div class="relative">
          <MagnifyingGlassIcon class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-500" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Suchen…"
            class="w-full bg-dark-700 border border-dark-600 rounded-lg pl-7 pr-3 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
          />
        </div>

        <!-- Language filter -->
        <div class="flex gap-1 mt-2 flex-wrap">
          <button
            @click="filterLang = ''"
            class="px-2 py-0.5 rounded text-xs transition-colors"
            :class="filterLang === '' ? 'bg-primary-600 text-white' : 'text-gray-500 hover:text-gray-300'"
          >Alle</button>
          <button
            v-for="lang in languages"
            :key="lang"
            @click="filterLang = filterLang === lang ? '' : lang"
            class="px-2 py-0.5 rounded text-xs transition-colors capitalize"
            :class="filterLang === lang ? 'bg-primary-600 text-white' : 'text-gray-500 hover:text-gray-300'"
          >{{ lang }}</button>
        </div>
      </div>

      <!-- Script list -->
      <div class="flex-1 overflow-y-auto p-2 space-y-1">
        <div v-if="loading" class="text-center py-8 text-gray-500 text-xs">
          <ArrowPathIcon class="w-5 h-5 mx-auto mb-2 animate-spin" />
          Laden…
        </div>

        <div v-else-if="filteredScripts.length === 0" class="text-gray-500 text-xs text-center py-6">
          Keine Scripts vorhanden
        </div>

        <div
          v-for="script in filteredScripts"
          :key="script.id"
          @click="selectScript(script)"
          class="group relative px-3 py-2 rounded-lg cursor-pointer transition-colors"
          :class="selectedScript?.id === script.id ? 'bg-primary-500/20 text-white' : 'text-gray-400 hover:bg-dark-700 hover:text-white'"
        >
          <div class="flex items-center justify-between gap-1">
            <span class="text-xs font-medium truncate flex-1">{{ script.name }}</span>
            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
              <button
                @click.stop="toggleFavorite(script)"
                :class="script.is_favorite ? 'text-yellow-400' : 'text-gray-500 hover:text-yellow-400'"
                class="p-0.5 rounded"
                title="Favorit"
              >
                <StarIcon class="w-3 h-3" />
              </button>
              <button
                @click.stop="deleteScript(script)"
                class="p-0.5 rounded text-gray-500 hover:text-red-400"
                title="Löschen"
              >
                <TrashIcon class="w-3 h-3" />
              </button>
            </div>
          </div>
          <div class="flex items-center gap-2 mt-0.5">
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-dark-700 text-gray-500 capitalize">
              {{ script.language }}
            </span>
            <span v-if="script.is_favorite" class="text-yellow-400">
              <StarIcon class="w-3 h-3" />
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Main panel -->
    <div class="flex-1 flex flex-col min-w-0 bg-dark-950">
      <!-- No script selected -->
      <div v-if="!editForm" class="flex-1 flex items-center justify-center text-gray-500">
        <div class="text-center">
          <CommandLineIcon class="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p class="text-sm">Script auswählen oder neues erstellen</p>
          <button
            @click="newScript"
            class="mt-4 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white text-sm rounded-lg transition-colors"
          >
            <PlusIcon class="w-4 h-4 inline mr-1" />
            Neues Script
          </button>
        </div>
      </div>

      <template v-else>
        <!-- Toolbar -->
        <div class="flex items-center gap-3 px-4 py-2 border-b border-dark-700 flex-shrink-0">
          <!-- Name -->
          <input
            v-model="editForm.name"
            type="text"
            class="bg-transparent text-white font-medium text-sm focus:outline-none flex-1 min-w-0"
            placeholder="Script-Name"
          />

          <!-- Language -->
          <select
            v-model="editForm.language"
            class="bg-dark-700 border border-dark-600 rounded-lg px-2 py-1 text-xs text-gray-300 focus:outline-none focus:border-primary-500"
          >
            <option v-for="lang in languages" :key="lang" :value="lang" class="capitalize">{{ lang }}</option>
          </select>

          <!-- Connection selector -->
          <select
            v-model="selectedConnectionId"
            class="bg-dark-700 border border-dark-600 rounded-lg px-2 py-1 text-xs text-gray-300 focus:outline-none focus:border-primary-500"
          >
            <option value="">Lokal</option>
            <option v-for="conn in connections" :key="conn.id" :value="conn.id">
              {{ conn.name }}
            </option>
          </select>

          <!-- Save -->
          <button
            @click="saveScript"
            class="px-3 py-1.5 bg-dark-700 hover:bg-dark-600 text-gray-300 hover:text-white text-xs rounded-lg transition-colors"
          >
            Speichern
          </button>

          <!-- Run -->
          <button
            @click="runScript"
            :disabled="running || isNew"
            class="flex items-center gap-1.5 px-3 py-1.5 bg-green-600 hover:bg-green-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs rounded-lg transition-colors"
          >
            <component :is="running ? ArrowPathIcon : PlayIcon" class="w-3.5 h-3.5" :class="{ 'animate-spin': running }" />
            {{ running ? 'Läuft…' : 'Ausführen' }}
          </button>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-dark-700 px-4 flex-shrink-0">
          <button
            @click="switchTab('editor')"
            class="px-3 py-2 text-xs border-b-2 transition-colors -mb-px"
            :class="activeTab === 'editor' ? 'border-primary-500 text-white' : 'border-transparent text-gray-500 hover:text-gray-300'"
          >Editor</button>
          <button
            @click="switchTab('history')"
            class="px-3 py-2 text-xs border-b-2 transition-colors -mb-px"
            :class="activeTab === 'history' ? 'border-primary-500 text-white' : 'border-transparent text-gray-500 hover:text-gray-300'"
          >Verlauf</button>
        </div>

        <!-- Editor tab -->
        <template v-if="activeTab === 'editor'">
          <!-- Description -->
          <div class="px-4 py-2 border-b border-dark-700 flex-shrink-0">
            <input
              v-model="editForm.description"
              type="text"
              placeholder="Beschreibung (optional)"
              class="w-full bg-transparent text-xs text-gray-400 focus:outline-none placeholder-gray-600"
            />
          </div>

          <!-- Monaco editor -->
          <div class="flex-1 min-h-0" :class="output ? 'h-1/2' : 'h-full'">
            <MonacoEditor
              v-if="editForm"
              v-model="editForm.content"
              :language="langMonaco[editForm.language] || 'shell'"
              class="h-full"
            />
          </div>

          <!-- Output panel -->
          <div v-if="output" class="h-48 border-t border-dark-700 flex flex-col flex-shrink-0">
            <div class="flex items-center gap-3 px-4 py-2 bg-dark-900 border-b border-dark-700 flex-shrink-0">
              <component
                :is="output.success ? CheckCircleIcon : XCircleIcon"
                class="w-4 h-4"
                :class="output.success ? 'text-green-400' : 'text-red-400'"
              />
              <span class="text-xs" :class="output.success ? 'text-green-400' : 'text-red-400'">
                Exit {{ output.exit_code }}
              </span>
              <span class="text-xs text-gray-500">
                <ClockIcon class="w-3 h-3 inline mr-1" />
                {{ formatDuration(output.duration) }}
              </span>
            </div>

            <div class="flex-1 overflow-y-auto font-mono text-xs p-4 space-y-2" style="background: #0d1117;">
              <div v-if="output.stdout" class="text-gray-300 whitespace-pre-wrap break-all">{{ output.stdout }}</div>
              <div v-if="output.stderr" class="text-red-400 whitespace-pre-wrap break-all">{{ output.stderr }}</div>
              <div v-if="!output.stdout && !output.stderr" class="text-gray-600">
                Keine Ausgabe
              </div>
            </div>
          </div>
        </template>

        <!-- History tab -->
        <div v-else class="flex-1 overflow-y-auto">
          <div v-if="historyLoading" class="text-center py-12 text-gray-500">
            <ArrowPathIcon class="w-6 h-6 mx-auto mb-2 animate-spin" />
          </div>

          <div v-else-if="history.length === 0" class="text-center py-12 text-gray-500 text-sm">
            Noch keine Ausführungen
          </div>

          <table v-else class="w-full text-xs">
            <thead>
              <tr class="text-gray-500 border-b border-dark-700">
                <th class="px-4 py-2 text-left font-medium">Zeitpunkt</th>
                <th class="px-4 py-2 text-left font-medium">Exit Code</th>
                <th class="px-4 py-2 text-left font-medium">Dauer</th>
                <th class="px-4 py-2 text-left font-medium">Ziel</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="exec in history"
                :key="exec.id"
                class="border-b border-dark-800 hover:bg-dark-800/40 transition-colors"
              >
                <td class="px-4 py-2 text-gray-300">{{ formatTs(exec.executed_at) }}</td>
                <td class="px-4 py-2">
                  <span
                    class="px-1.5 py-0.5 rounded text-xs font-mono"
                    :class="exec.exit_code === 0 ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400'"
                  >{{ exec.exit_code }}</span>
                </td>
                <td class="px-4 py-2 text-gray-400">{{ formatDuration(exec.duration_ms) }}</td>
                <td class="px-4 py-2 text-gray-400 flex items-center gap-1">
                  <ServerIcon class="w-3 h-3 flex-shrink-0" />
                  {{ exec.connection_id ? 'SSH' : 'Lokal' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>
</template>
