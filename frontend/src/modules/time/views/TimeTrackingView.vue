<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useProjectStore } from '@/stores/project'
import {
  PlayIcon,
  StopIcon,
  PlusIcon,
  TrashIcon,
  PencilIcon,
  XMarkIcon,
  ClockIcon,
  CurrencyEuroIcon,
  ChartBarIcon,
  CalendarIcon,
  FolderIcon,
  TagIcon,
} from '@heroicons/vue/24/outline'
import { PlayIcon as PlayIconSolid } from '@heroicons/vue/24/solid'

const uiStore = useUiStore()
const projectStore = useProjectStore()

// Watch for global project changes and sync with local filter
watch(() => projectStore.selectedProjectId, (newId) => {
  filterProjectId.value = newId || ''
  fetchData()
})

// State
const entries = ref([])
const runningEntry = ref(null)
const projects = ref([])
const stats = ref(null)
const loading = ref(true)
const showModal = ref(false)
const editingEntry = ref(null)
const timerInterval = ref(null)
const currentDuration = ref(0)

// Filters - initialize with global project if set
const filterProjectId = ref(projectStore.selectedProjectId || '')
const filterFrom = ref('')
const filterTo = ref('')

// Form
const form = ref({
  task_name: '',
  description: '',
  project_id: null,
  is_billable: false,
  hourly_rate: null,
  tags: [],
  started_at: '',
  ended_at: '',
  duration_seconds: null,
})

// Quick start form - use selected project as default
const quickForm = ref({
  task_name: '',
  project_id: projectStore.selectedProjectId || null,
})

// Fetch data
async function fetchData() {
  loading.value = true
  try {
    const [entriesRes, runningRes, projectsRes, statsRes] = await Promise.all([
      api.get('/api/v1/time', { params: { project_id: filterProjectId.value, from: filterFrom.value, to: filterTo.value } }),
      api.get('/api/v1/time/running'),
      api.get('/api/v1/time/projects'),
      api.get('/api/v1/time/stats'),
    ])
    entries.value = entriesRes.data.data.items || []
    runningEntry.value = runningRes.data.data.entry
    projects.value = projectsRes.data.data.projects || []
    stats.value = statsRes.data.data
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Daten')
  } finally {
    loading.value = false
  }
}

// Start timer
async function startTimer() {
  if (!quickForm.value.task_name.trim()) {
    uiStore.showError('Aufgabenname ist erforderlich')
    return
  }

  try {
    const response = await api.post('/api/v1/time/start', {
      task_name: quickForm.value.task_name,
      project_id: quickForm.value.project_id || null,
    })
    runningEntry.value = response.data.data.entry
    quickForm.value.task_name = ''
    quickForm.value.project_id = null
    uiStore.showSuccess('Timer gestartet')
    startLocalTimer()
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Starten')
  }
}

// Stop timer
async function stopTimer() {
  if (!runningEntry.value) return

  try {
    await api.post(`/api/v1/time/${runningEntry.value.id}/stop`)
    uiStore.showSuccess('Timer gestoppt')
    stopLocalTimer()
    runningEntry.value = null
    await fetchData()
  } catch (error) {
    uiStore.showError('Fehler beim Stoppen')
  }
}

// Local timer for UI updates
function startLocalTimer() {
  if (timerInterval.value) return
  updateCurrentDuration()
  timerInterval.value = setInterval(updateCurrentDuration, 1000)
}

function stopLocalTimer() {
  if (timerInterval.value) {
    clearInterval(timerInterval.value)
    timerInterval.value = null
  }
  currentDuration.value = 0
}

function updateCurrentDuration() {
  if (!runningEntry.value) return
  let startStr = runningEntry.value.started_at
  // Ensure proper ISO format with T separator
  if (startStr.includes(' ') && !startStr.includes('T')) {
    startStr = startStr.replace(' ', 'T')
  }
  // If no timezone info, assume UTC (backend stores in UTC)
  if (!startStr.endsWith('Z') && !startStr.includes('+') && !/\d{2}:\d{2}:\d{2}-/.test(startStr)) {
    startStr += 'Z'
  }
  const start = new Date(startStr)
  const now = new Date()
  currentDuration.value = Math.floor((now - start) / 1000)
}

// Format duration
function formatDuration(seconds) {
  if (!seconds) return '00:00:00'
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  const s = seconds % 60
  return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
}

function formatDurationShort(seconds) {
  if (!seconds) return '0h'
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  return h > 0 ? `${h}h ${m}m` : `${m}m`
}

// Format date
function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatTime(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
}

// Open modal for manual entry
function openModal(entry = null) {
  editingEntry.value = entry
  if (entry) {
    form.value = {
      task_name: entry.task_name,
      description: entry.description || '',
      project_id: entry.project_id,
      is_billable: entry.is_billable,
      hourly_rate: entry.hourly_rate,
      tags: entry.tags || [],
      started_at: entry.started_at?.slice(0, 16),
      ended_at: entry.ended_at?.slice(0, 16),
      duration_seconds: entry.duration_seconds,
    }
  } else {
    const now = new Date()
    form.value = {
      task_name: '',
      description: '',
      project_id: null,
      is_billable: false,
      hourly_rate: null,
      tags: [],
      started_at: now.toISOString().slice(0, 16),
      ended_at: '',
      duration_seconds: null,
    }
  }
  showModal.value = true
}

// Save entry
async function saveEntry() {
  if (!form.value.task_name.trim()) {
    uiStore.showError('Aufgabenname ist erforderlich')
    return
  }

  try {
    if (editingEntry.value) {
      await api.put(`/api/v1/time/${editingEntry.value.id}`, form.value)
      uiStore.showSuccess('Eintrag aktualisiert')
    } else {
      await api.post('/api/v1/time', form.value)
      uiStore.showSuccess('Eintrag erstellt')
    }
    showModal.value = false
    await fetchData()
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Speichern')
  }
}

// Delete entry
async function deleteEntry(entry) {
  if (!confirm('Eintrag wirklich löschen?')) return

  try {
    await api.delete(`/api/v1/time/${entry.id}`)
    uiStore.showSuccess('Eintrag gelöscht')
    await fetchData()
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Group entries by date
const groupedEntries = computed(() => {
  const groups = {}
  for (const entry of entries.value) {
    const date = entry.started_at.split('T')[0]
    if (!groups[date]) {
      groups[date] = { date, entries: [], total_seconds: 0 }
    }
    groups[date].entries.push(entry)
    groups[date].total_seconds += entry.duration_seconds || 0
  }
  return Object.values(groups).sort((a, b) => b.date.localeCompare(a.date))
})

// Get project by ID
function getProject(projectId) {
  return projects.value.find(p => p.id === projectId)
}

onMounted(() => {
  fetchData()
  if (runningEntry.value) {
    startLocalTimer()
  }
})

onUnmounted(() => {
  stopLocalTimer()
})

// Watch for running entry changes
watch(runningEntry, (val) => {
  if (val) {
    startLocalTimer()
  } else {
    stopLocalTimer()
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header with Timer -->
    <div class="bg-dark-800 border border-dark-700 rounded-xl p-6">
      <div v-if="runningEntry" class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 bg-red-500/20 rounded-xl flex items-center justify-center">
            <PlayIconSolid class="w-6 h-6 text-red-400 animate-pulse" />
          </div>
          <div>
            <h2 class="text-xl font-bold text-white">{{ runningEntry.task_name }}</h2>
            <p v-if="runningEntry.project_name" class="text-sm text-gray-400 flex items-center gap-1">
              <FolderIcon class="w-4 h-4" />
              {{ runningEntry.project_name }}
            </p>
          </div>
        </div>
        <div class="flex items-center gap-6">
          <div class="text-4xl font-mono font-bold text-white">
            {{ formatDuration(currentDuration) }}
          </div>
          <button
            @click="stopTimer"
            class="p-4 bg-red-600 text-white rounded-xl hover:bg-red-500 transition-colors"
          >
            <StopIcon class="w-6 h-6" />
          </button>
        </div>
      </div>

      <div v-else class="flex items-center gap-4">
        <input
          v-model="quickForm.task_name"
          type="text"
          class="flex-1 bg-dark-700 border border-dark-600 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
          placeholder="Was arbeitest du gerade?"
          @keyup.enter="startTimer"
        />
        <select
          v-model="quickForm.project_id"
          class="bg-dark-700 border border-dark-600 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary-500"
        >
          <option :value="null">Kein Projekt</option>
          <option v-for="project in projects" :key="project.id" :value="project.id">
            {{ project.name }}
          </option>
        </select>
        <button
          @click="startTimer"
          class="p-3 bg-green-600 text-white rounded-lg hover:bg-green-500 transition-colors"
        >
          <PlayIcon class="w-6 h-6" />
        </button>
        <button
          @click="openModal()"
          class="p-3 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors"
          title="Manueller Eintrag"
        >
          <PlusIcon class="w-6 h-6" />
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center">
            <ClockIcon class="w-5 h-5 text-blue-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-white">{{ formatDurationShort(stats.totals?.total_seconds) }}</p>
            <p class="text-sm text-gray-400">Gesamt (30 Tage)</p>
          </div>
        </div>
      </div>
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-green-500/20 rounded-lg flex items-center justify-center">
            <CurrencyEuroIcon class="w-5 h-5 text-green-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-white">{{ formatDurationShort(stats.totals?.billable_seconds) }}</p>
            <p class="text-sm text-gray-400">Abrechenbar</p>
          </div>
        </div>
      </div>
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center">
            <ChartBarIcon class="w-5 h-5 text-purple-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-white">{{ stats.totals?.entry_count || 0 }}</p>
            <p class="text-sm text-gray-400">Einträge</p>
          </div>
        </div>
      </div>
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-yellow-500/20 rounded-lg flex items-center justify-center">
            <CurrencyEuroIcon class="w-5 h-5 text-yellow-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-white">{{ stats.totals?.total_earnings?.toFixed(2) || '0.00' }} EUR</p>
            <p class="text-sm text-gray-400">Verdienst</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-4">
      <select
        v-model="filterProjectId"
        @change="fetchData"
        class="bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
      >
        <option value="">Alle Projekte</option>
        <option v-for="project in projects" :key="project.id" :value="project.id">
          {{ project.name }}
        </option>
      </select>
      <input
        v-model="filterFrom"
        type="date"
        @change="fetchData"
        class="bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
      />
      <span class="text-gray-400">bis</span>
      <input
        v-model="filterTo"
        type="date"
        @change="fetchData"
        class="bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
      />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Entries by Day -->
    <div v-else class="space-y-6">
      <div v-for="group in groupedEntries" :key="group.date" class="space-y-2">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-white">{{ formatDate(group.date) }}</h3>
          <span class="text-gray-400">{{ formatDurationShort(group.total_seconds) }}</span>
        </div>

        <div class="space-y-2">
          <div
            v-for="entry in group.entries"
            :key="entry.id"
            class="bg-dark-800 border border-dark-700 rounded-lg p-4 group"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div
                  class="w-1 h-12 rounded-full"
                  :style="{ backgroundColor: getProject(entry.project_id)?.color || '#6366f1' }"
                ></div>
                <div>
                  <h4 class="text-white font-medium">{{ entry.task_name }}</h4>
                  <div class="flex items-center gap-3 text-sm text-gray-400 mt-1">
                    <span v-if="entry.project_name" class="flex items-center gap-1">
                      <FolderIcon class="w-4 h-4" />
                      {{ entry.project_name }}
                    </span>
                    <span class="flex items-center gap-1">
                      <CalendarIcon class="w-4 h-4" />
                      {{ formatTime(entry.started_at) }} - {{ entry.ended_at ? formatTime(entry.ended_at) : 'läuft' }}
                    </span>
                    <span v-if="entry.is_billable" class="flex items-center gap-1 text-green-400">
                      <CurrencyEuroIcon class="w-4 h-4" />
                      {{ entry.hourly_rate ? `${entry.hourly_rate}/h` : 'abrechenbar' }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-4">
                <span class="text-lg font-mono text-white">
                  {{ formatDurationShort(entry.duration_seconds) }}
                </span>
                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button
                    @click="openModal(entry)"
                    class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
                  >
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="deleteEntry(entry)"
                    class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="groupedEntries.length === 0" class="bg-dark-800 border-2 border-dashed border-dark-600 rounded-xl p-8 text-center">
        <ClockIcon class="w-12 h-12 text-gray-500 mx-auto mb-3" />
        <p class="text-gray-400">Keine Zeiteinträge vorhanden</p>
        <p class="text-sm text-gray-500 mt-1">Starte den Timer oben um Zeit zu erfassen</p>
      </div>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="showModal = false"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">
              {{ editingEntry ? 'Eintrag bearbeiten' : 'Manueller Eintrag' }}
            </h2>
            <button @click="showModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Aufgabe *</label>
              <input
                v-model="form.task_name"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Was hast du gemacht?"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <textarea
                v-model="form.description"
                rows="2"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 resize-none"
                placeholder="Optionale Details..."
              ></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Projekt</label>
              <select
                v-model="form.project_id"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
              >
                <option :value="null">Kein Projekt</option>
                <option v-for="project in projects" :key="project.id" :value="project.id">
                  {{ project.name }}
                </option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Start *</label>
                <input
                  v-model="form.started_at"
                  type="datetime-local"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Ende</label>
                <input
                  v-model="form.ended_at"
                  type="datetime-local"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                />
              </div>
            </div>

            <div class="flex items-center gap-4">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="form.is_billable"
                  type="checkbox"
                  class="w-4 h-4 rounded bg-dark-700 border-dark-600 text-primary-600 focus:ring-primary-500"
                />
                <span class="text-gray-300">Abrechenbar</span>
              </label>
              <div v-if="form.is_billable" class="flex items-center gap-2">
                <input
                  v-model.number="form.hourly_rate"
                  type="number"
                  step="0.01"
                  class="w-24 bg-dark-700 border border-dark-600 rounded-lg px-3 py-1 text-white focus:outline-none focus:border-primary-500"
                  placeholder="0.00"
                />
                <span class="text-gray-400">EUR/h</span>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-dark-700">
            <button
              @click="showModal = false"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="saveEntry"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
            >
              {{ editingEntry ? 'Speichern' : 'Erstellen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
