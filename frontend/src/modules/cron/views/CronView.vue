<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  ClockIcon,
  PlusIcon,
  PencilSquareIcon,
  TrashIcon,
  PlayIcon,
  EyeIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  ServerIcon,
  CommandLineIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import { useCronStore } from '@/modules/cron/stores/cronStore'
import CronJobModal from '@/modules/cron/components/CronJobModal.vue'

const cronStore = useCronStore()

// Local state
const showModal = ref(false)
const editingJob = ref(null)
const expandedHistory = ref(null)
const showOutputModal = ref(false)
const selectedHistoryEntry = ref(null)
const deleteConfirmId = ref(null)

// Computed: group jobs by connection
const groupedJobs = computed(() => {
  const groups = {}
  for (const job of cronStore.jobs) {
    const key = job.connection_name || 'Lokal'
    if (!groups[key]) {
      groups[key] = []
    }
    groups[key].push(job)
  }
  return groups
})

function formatTimestamp(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatDuration(ms) {
  if (ms === null || ms === undefined) return '-'
  if (ms < 1000) return `${ms}ms`
  const seconds = (ms / 1000).toFixed(1)
  if (ms < 60000) return `${seconds}s`
  const minutes = Math.floor(ms / 60000)
  const remainSec = ((ms % 60000) / 1000).toFixed(0)
  return `${minutes}m ${remainSec}s`
}

function openCreateModal() {
  editingJob.value = null
  showModal.value = true
}

function openEditModal(job) {
  editingJob.value = { ...job }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingJob.value = null
}

async function handleSaved() {
  closeModal()
  await cronStore.fetchJobs()
}

async function handleToggle(job) {
  await cronStore.toggleJob(job.id)
}

function confirmDelete(jobId) {
  deleteConfirmId.value = jobId
}

async function handleDelete(jobId) {
  await cronStore.deleteJob(jobId)
  deleteConfirmId.value = null
}

function cancelDelete() {
  deleteConfirmId.value = null
}

async function toggleHistory(jobId) {
  if (expandedHistory.value === jobId) {
    expandedHistory.value = null
    return
  }
  expandedHistory.value = jobId
  await cronStore.fetchHistory(jobId)
}

function showOutput(entry) {
  selectedHistoryEntry.value = entry
  showOutputModal.value = true
}

function closeOutputModal() {
  showOutputModal.value = false
  selectedHistoryEntry.value = null
}

onMounted(async () => {
  await cronStore.fetchJobs()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Cron Jobs</h1>
        <p class="text-gray-400 mt-1">Geplante Aufgaben verwalten und ueberwachen</p>
      </div>
      <button @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Job erstellen
      </button>
    </div>

    <!-- Loading -->
    <div v-if="cronStore.loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Empty state -->
    <div
      v-else-if="cronStore.jobs.length === 0"
      class="card-glass p-12 text-center"
    >
      <ClockIcon class="w-12 h-12 text-gray-600 mx-auto mb-4" />
      <h3 class="text-lg font-medium text-gray-300 mb-2">Keine Cron-Jobs vorhanden.</h3>
      <p class="text-gray-500 mb-6">Erstellen Sie Ihren ersten geplanten Job.</p>
      <button @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Job erstellen
      </button>
    </div>

    <!-- Jobs grouped by connection -->
    <template v-else>
      <div v-for="(groupJobs, groupName) in groupedJobs" :key="groupName" class="space-y-3">
        <!-- Group header -->
        <div class="flex items-center gap-2 px-1">
          <ServerIcon class="w-4 h-4 text-gray-500" />
          <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">{{ groupName }}</h2>
          <span class="text-xs text-gray-600">({{ groupJobs.length }})</span>
        </div>

        <!-- Job cards -->
        <div v-for="job in groupJobs" :key="job.id" class="card-glass p-5 space-y-3">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
              <!-- Expression -->
              <div class="flex items-center gap-3 mb-1">
                <code class="font-mono text-primary-400 text-sm font-medium">{{ job.expression }}</code>
                <span
                  class="text-xs px-2 py-0.5 rounded-full"
                  :class="job.is_active ? 'bg-emerald-500/15 text-emerald-400' : 'bg-gray-500/15 text-gray-500'"
                >
                  {{ job.is_active ? 'Aktiv' : 'Inaktiv' }}
                </span>
              </div>

              <!-- Human-readable description -->
              <p class="text-xs text-gray-500 mb-2">{{ job.readable_expression || job.expression }}</p>

              <!-- Command -->
              <div class="flex items-center gap-2 mb-1">
                <CommandLineIcon class="w-3.5 h-3.5 text-gray-600 flex-shrink-0" />
                <code class="font-mono text-gray-400 text-xs truncate">{{ job.command }}</code>
              </div>

              <!-- Description -->
              <p v-if="job.description" class="text-sm text-gray-400 mt-1">{{ job.description }}</p>

              <!-- Last run info -->
              <div v-if="job.last_run_at" class="flex items-center gap-2 mt-2 text-xs text-gray-500">
                <ClockIcon class="w-3.5 h-3.5" />
                <span>Letzter Lauf: {{ formatTimestamp(job.last_run_at) }}</span>
              </div>
            </div>

            <!-- Toggle switch -->
            <div class="flex items-center gap-3">
              <button
                @click="handleToggle(job)"
                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
                :class="job.is_active ? 'bg-primary-600' : 'bg-gray-700'"
              >
                <span
                  class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                  :class="job.is_active ? 'translate-x-6' : 'translate-x-1'"
                />
              </button>
            </div>
          </div>

          <!-- Action buttons -->
          <div class="flex items-center gap-2 pt-2 border-t border-white/[0.06]">
            <button @click="openEditModal(job)" class="btn-ghost text-xs text-gray-400 hover:text-white">
              <PencilSquareIcon class="w-4 h-4 mr-1" />
              Bearbeiten
            </button>
            <button @click="toggleHistory(job.id)" class="btn-ghost text-xs text-gray-400 hover:text-white">
              <EyeIcon class="w-4 h-4 mr-1" />
              Verlauf
              <component :is="expandedHistory === job.id ? ChevronUpIcon : ChevronDownIcon" class="w-3 h-3 ml-1" />
            </button>
            <div class="flex-1"></div>

            <!-- Delete with confirmation -->
            <template v-if="deleteConfirmId === job.id">
              <span class="text-xs text-red-400 mr-2">Wirklich loeschen?</span>
              <button @click="handleDelete(job.id)" class="btn-ghost text-xs text-red-400 hover:text-red-300">
                Ja
              </button>
              <button @click="cancelDelete" class="btn-ghost text-xs text-gray-400 hover:text-white">
                Nein
              </button>
            </template>
            <button
              v-else
              @click="confirmDelete(job.id)"
              class="btn-ghost text-xs text-red-400/60 hover:text-red-400"
            >
              <TrashIcon class="w-4 h-4 mr-1" />
              Loeschen
            </button>
          </div>

          <!-- History panel (expandable) -->
          <div v-if="expandedHistory === job.id" class="mt-3 pt-3 border-t border-white/[0.06]">
            <div v-if="cronStore.history.length === 0" class="text-center text-gray-500 text-sm py-4">
              Keine Ausfuehrungshistorie vorhanden.
            </div>
            <div v-else class="overflow-x-auto">
              <table class="table-glass w-full">
                <thead>
                  <tr>
                    <th class="text-left text-xs text-gray-500 font-medium pb-2">Gestartet</th>
                    <th class="text-left text-xs text-gray-500 font-medium pb-2">Dauer</th>
                    <th class="text-left text-xs text-gray-500 font-medium pb-2">Exit-Code</th>
                    <th class="text-right text-xs text-gray-500 font-medium pb-2">Aktionen</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="entry in cronStore.history"
                    :key="entry.id"
                    class="border-t border-white/[0.04]"
                  >
                    <td class="py-2 text-xs text-gray-400 font-mono">
                      {{ formatTimestamp(entry.started_at) }}
                    </td>
                    <td class="py-2 text-xs text-gray-400">
                      {{ formatDuration(entry.duration_ms) }}
                    </td>
                    <td class="py-2">
                      <span
                        class="text-xs px-2 py-0.5 rounded-full font-mono"
                        :class="entry.exit_code === 0
                          ? 'bg-emerald-500/15 text-emerald-400'
                          : entry.exit_code !== null
                            ? 'bg-red-500/15 text-red-400'
                            : 'bg-gray-500/15 text-gray-500'"
                      >
                        {{ entry.exit_code !== null ? entry.exit_code : '-' }}
                      </span>
                    </td>
                    <td class="py-2 text-right">
                      <button
                        v-if="entry.stdout || entry.stderr"
                        @click="showOutput(entry)"
                        class="btn-ghost text-xs text-primary-400 hover:text-primary-300"
                      >
                        Ausgabe
                      </button>
                      <span v-else class="text-xs text-gray-600">-</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Create/Edit Modal -->
    <CronJobModal
      :show="showModal"
      :job="editingJob"
      @close="closeModal"
      @saved="handleSaved"
    />

    <!-- Output Modal -->
    <Teleport to="body">
      <div
        v-if="showOutputModal && selectedHistoryEntry"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeOutputModal"></div>
        <div class="relative card-glass w-full max-w-3xl max-h-[80vh] flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between p-5 border-b border-white/[0.06]">
            <h3 class="text-lg font-semibold text-white">Ausfuehrungsausgabe</h3>
            <button @click="closeOutputModal" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Content -->
          <div class="p-5 overflow-y-auto space-y-4">
            <!-- Stdout -->
            <div v-if="selectedHistoryEntry.stdout">
              <h4 class="text-xs text-gray-500 uppercase tracking-wider mb-2">Standardausgabe (stdout)</h4>
              <pre class="font-mono text-xs text-gray-300 bg-black/30 rounded-lg p-4 overflow-x-auto whitespace-pre-wrap">{{ selectedHistoryEntry.stdout }}</pre>
            </div>

            <!-- Stderr -->
            <div v-if="selectedHistoryEntry.stderr">
              <h4 class="text-xs text-gray-500 uppercase tracking-wider mb-2">Fehlerausgabe (stderr)</h4>
              <pre class="font-mono text-xs text-red-400 bg-black/30 rounded-lg p-4 overflow-x-auto whitespace-pre-wrap">{{ selectedHistoryEntry.stderr }}</pre>
            </div>

            <!-- No output -->
            <div v-if="!selectedHistoryEntry.stdout && !selectedHistoryEntry.stderr" class="text-center text-gray-500 py-8">
              Keine Ausgabe vorhanden.
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
