<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  BoltIcon,
  PlusIcon,
  PlayIcon,
  StopIcon,
  TrashIcon,
  PencilSquareIcon,
  ClockIcon,
  ChevronRightIcon,
  XMarkIcon,
  CheckIcon,
  ExclamationTriangleIcon,
  DocumentDuplicateIcon,
  Cog6ToothIcon,
} from '@heroicons/vue/24/outline'

const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

const workflows = ref([])
const templates = ref([])
const options = ref({ trigger_types: {}, events: {}, action_types: {} })
const isLoading = ref(true)
const showCreateModal = ref(false)
const showTemplatesModal = ref(false)
const showHistoryModal = ref(false)
const selectedWorkflow = ref(null)
const runHistory = ref([])
const isExecuting = ref({})

// Create workflow form
const workflowForm = ref({
  name: '',
  description: '',
  trigger_type: 'manual',
  trigger_config: {},
  actions: [],
})

const editMode = ref(false)

async function fetchWorkflows() {
  try {
    const response = await api.get('/api/v1/workflows')
    workflows.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch workflows:', error)
  }
}

async function fetchTemplates() {
  try {
    const response = await api.get('/api/v1/workflows/templates')
    templates.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch templates:', error)
  }
}

async function fetchOptions() {
  try {
    const response = await api.get('/api/v1/workflows/options')
    options.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch options:', error)
  }
}

async function loadData() {
  isLoading.value = true
  await Promise.all([
    fetchWorkflows(),
    fetchTemplates(),
    fetchOptions(),
  ])
  isLoading.value = false
}

onMounted(() => {
  loadData()
})

function openCreateModal() {
  editMode.value = false
  workflowForm.value = {
    name: '',
    description: '',
    trigger_type: 'manual',
    trigger_config: {},
    actions: [],
  }
  showCreateModal.value = true
}

function editWorkflow(workflow) {
  editMode.value = true
  workflowForm.value = {
    id: workflow.id,
    name: workflow.name,
    description: workflow.description || '',
    trigger_type: workflow.trigger_type,
    trigger_config: workflow.trigger_config || {},
    actions: workflow.actions || [],
  }
  showCreateModal.value = true
}

async function saveWorkflow() {
  if (!workflowForm.value.name) {
    uiStore.showError('Name ist erforderlich')
    return
  }

  try {
    const data = {
      name: workflowForm.value.name,
      description: workflowForm.value.description,
      trigger_type: workflowForm.value.trigger_type,
      trigger_config: workflowForm.value.trigger_config,
      actions: workflowForm.value.actions.map((a, i) => ({
        action_type: a.action_type,
        config: a.config || {},
        position: i,
        continue_on_error: a.continue_on_error || false,
      })),
    }

    if (editMode.value && workflowForm.value.id) {
      await api.put(`/api/v1/workflows/${workflowForm.value.id}`, data)
      uiStore.showSuccess('Workflow aktualisiert')
    } else {
      await api.post('/api/v1/workflows', data)
      uiStore.showSuccess('Workflow erstellt')
    }

    showCreateModal.value = false
    await fetchWorkflows()
  } catch (error) {
    const message = error.response?.data?.error || 'Fehler beim Speichern'
    uiStore.showError(message)
  }
}

async function toggleWorkflow(workflow) {
  try {
    const response = await api.post(`/api/v1/workflows/${workflow.id}/toggle`)
    workflow.is_enabled = response.data.data.is_enabled
    uiStore.showSuccess(workflow.is_enabled ? 'Workflow aktiviert' : 'Workflow deaktiviert')
  } catch (error) {
    uiStore.showError('Fehler beim Umschalten')
  }
}

async function deleteWorkflow(workflow) {
  if (!await confirm({ message: `Workflow "${workflow.name}" wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/workflows/${workflow.id}`)
    workflows.value = workflows.value.filter(w => w.id !== workflow.id)
    uiStore.showSuccess('Workflow gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function executeWorkflow(workflow) {
  isExecuting.value[workflow.id] = true
  try {
    const response = await api.post(`/api/v1/workflows/${workflow.id}/execute`)
    const result = response.data.data

    if (result.status === 'success') {
      uiStore.showSuccess('Workflow erfolgreich ausgeführt')
    } else if (result.status === 'partial') {
      uiStore.showError('Workflow teilweise fehlgeschlagen')
    } else {
      uiStore.showError('Workflow fehlgeschlagen')
    }

    await fetchWorkflows()
  } catch (error) {
    const message = error.response?.data?.error || 'Ausführung fehlgeschlagen'
    uiStore.showError(message)
  } finally {
    isExecuting.value[workflow.id] = false
  }
}

async function showHistory(workflow) {
  selectedWorkflow.value = workflow
  try {
    const response = await api.get(`/api/v1/workflows/${workflow.id}/history`)
    runHistory.value = response.data.data
    showHistoryModal.value = true
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Historie')
  }
}

async function createFromTemplate(template) {
  try {
    await api.post(`/api/v1/workflows/templates/${template.id}`)
    uiStore.showSuccess('Workflow aus Vorlage erstellt')
    showTemplatesModal.value = false
    await fetchWorkflows()
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen')
  }
}

function addAction() {
  workflowForm.value.actions.push({
    action_type: 'send_notification',
    config: {},
    continue_on_error: false,
  })
}

function removeAction(index) {
  workflowForm.value.actions.splice(index, 1)
}

function getTriggerLabel(type) {
  return options.value.trigger_types[type] || type
}

function getActionLabel(type) {
  return options.value.action_types[type]?.name || type
}

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function getStatusColor(status) {
  switch (status) {
    case 'success': return 'bg-green-500/20 text-green-400'
    case 'failed': return 'bg-red-500/20 text-red-400'
    case 'partial': return 'bg-yellow-500/20 text-yellow-400'
    case 'running': return 'bg-blue-500/20 text-blue-400'
    default: return 'bg-gray-500/20 text-gray-400'
  }
}

function getStatusIcon(status) {
  switch (status) {
    case 'success': return CheckIcon
    case 'failed': return XMarkIcon
    case 'partial': return ExclamationTriangleIcon
    case 'running': return PlayIcon
    default: return ClockIcon
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white flex items-center gap-3">
          <BoltIcon class="w-8 h-8 text-yellow-400" />
          Automatisierungen
        </h1>
        <p class="text-gray-400 mt-1">
          Erstelle automatische Workflows wie bei IFTTT
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="showTemplatesModal = true" class="btn-secondary">
          <DocumentDuplicateIcon class="w-4 h-4 mr-2" />
          Vorlagen
        </button>
        <button @click="openCreateModal" class="btn-primary">
          <PlusIcon class="w-4 h-4 mr-2" />
          Neuer Workflow
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="i in 3" :key="i" class="card p-6 animate-pulse">
        <div class="h-5 bg-white/[0.08] rounded w-1/2 mb-4"></div>
        <div class="h-4 bg-white/[0.08] rounded w-full mb-2"></div>
        <div class="h-4 bg-white/[0.08] rounded w-3/4"></div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="workflows.length === 0" class="card p-12 text-center">
      <BoltIcon class="w-16 h-16 text-gray-600 mx-auto mb-4" />
      <h3 class="text-lg font-semibold text-white mb-2">Keine Workflows</h3>
      <p class="text-gray-500 mb-6">
        Erstelle deinen ersten Workflow oder wähle eine Vorlage.
      </p>
      <div class="flex items-center justify-center gap-4">
        <button @click="showTemplatesModal = true" class="btn-secondary">
          Vorlagen ansehen
        </button>
        <button @click="openCreateModal" class="btn-primary">
          Workflow erstellen
        </button>
      </div>
    </div>

    <!-- Workflows Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="workflow in workflows"
        :key="workflow.id"
        class="card p-6 hover:border-primary-500/50 transition-colors"
      >
        <div class="flex items-start justify-between mb-4">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-lg flex items-center justify-center"
              :class="workflow.is_enabled ? 'bg-green-500/20' : 'bg-gray-500/20'"
            >
              <BoltIcon
                class="w-5 h-5"
                :class="workflow.is_enabled ? 'text-green-400' : 'text-gray-500'"
              />
            </div>
            <div>
              <h3 class="font-semibold text-white">{{ workflow.name }}</h3>
              <p class="text-xs text-gray-500">{{ getTriggerLabel(workflow.trigger_type) }}</p>
            </div>
          </div>
          <button
            @click="toggleWorkflow(workflow)"
            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
            :class="workflow.is_enabled ? 'bg-green-600' : 'bg-white/[0.08]'"
          >
            <span
              class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
              :class="workflow.is_enabled ? 'translate-x-6' : 'translate-x-1'"
            />
          </button>
        </div>

        <p v-if="workflow.description" class="text-sm text-gray-400 mb-4 line-clamp-2">
          {{ workflow.description }}
        </p>

        <!-- Actions preview -->
        <div class="flex flex-wrap gap-1 mb-4">
          <span
            v-for="action in workflow.actions.slice(0, 3)"
            :key="action.id"
            class="px-2 py-1 rounded text-xs bg-white/[0.04] text-gray-400"
          >
            {{ getActionLabel(action.action_type) }}
          </span>
          <span v-if="workflow.actions.length > 3" class="px-2 py-1 rounded text-xs bg-white/[0.04] text-gray-500">
            +{{ workflow.actions.length - 3 }}
          </span>
        </div>

        <!-- Stats -->
        <div class="flex items-center justify-between text-xs text-gray-500 mb-4">
          <span>{{ workflow.run_count || 0 }} Ausführungen</span>
          <span v-if="workflow.last_run_at">
            Zuletzt: {{ formatDate(workflow.last_run_at) }}
          </span>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between pt-4 border-t border-white/[0.06]">
          <div class="flex items-center gap-2">
            <button
              @click="executeWorkflow(workflow)"
              :disabled="isExecuting[workflow.id]"
              class="p-2 rounded-lg hover:bg-white/[0.04] text-green-400 transition-colors"
              title="Ausführen"
            >
              <PlayIcon v-if="!isExecuting[workflow.id]" class="w-4 h-4" />
              <svg v-else class="animate-spin w-4 h-4" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </button>
            <button
              @click="showHistory(workflow)"
              class="p-2 rounded-lg hover:bg-white/[0.04] text-gray-400 transition-colors"
              title="Historie"
            >
              <ClockIcon class="w-4 h-4" />
            </button>
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="editWorkflow(workflow)"
              class="p-2 rounded-lg hover:bg-white/[0.04] text-gray-400 transition-colors"
              title="Bearbeiten"
            >
              <PencilSquareIcon class="w-4 h-4" />
            </button>
            <button
              @click="deleteWorkflow(workflow)"
              class="p-2 rounded-lg hover:bg-white/[0.04] text-red-400 transition-colors"
              title="Löschen"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
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
          v-if="showCreateModal"
          class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4"
        >
          <div class="modal w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-white/[0.06] sticky top-0 bg-white/[0.03]">
              <h3 class="text-lg font-semibold text-white">
                {{ editMode ? 'Workflow bearbeiten' : 'Neuer Workflow' }}
              </h3>
              <button @click="showCreateModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <div class="p-6 space-y-6">
              <!-- Basic Info -->
              <div class="space-y-4">
                <div>
                  <label class="label">Name</label>
                  <input v-model="workflowForm.name" type="text" class="input" placeholder="Mein Workflow" />
                </div>
                <div>
                  <label class="label">Beschreibung (optional)</label>
                  <textarea v-model="workflowForm.description" class="input" rows="2" placeholder="Was macht dieser Workflow?"></textarea>
                </div>
              </div>

              <!-- Trigger -->
              <div>
                <label class="label">Trigger (Auslöser)</label>
                <select v-model="workflowForm.trigger_type" class="select">
                  <option v-for="(label, type) in options.trigger_types" :key="type" :value="type">
                    {{ label }}
                  </option>
                </select>

                <!-- Event trigger config -->
                <div v-if="workflowForm.trigger_type === 'event'" class="mt-3">
                  <label class="label text-sm">Ereignis</label>
                  <select v-model="workflowForm.trigger_config.event" class="select">
                    <option v-for="(label, event) in options.events" :key="event" :value="event">
                      {{ label }}
                    </option>
                  </select>
                </div>

                <!-- Schedule trigger config -->
                <div v-if="workflowForm.trigger_type === 'schedule'" class="mt-3">
                  <label class="label text-sm">Cron-Ausdruck</label>
                  <input
                    v-model="workflowForm.trigger_config.cron"
                    type="text"
                    class="input"
                    placeholder="0 9 * * * (täglich 9 Uhr)"
                  />
                  <p class="text-xs text-gray-500 mt-1">Format: Minute Stunde Tag Monat Wochentag</p>
                </div>
              </div>

              <!-- Actions -->
              <div>
                <div class="flex items-center justify-between mb-3">
                  <label class="label">Aktionen</label>
                  <button @click="addAction" class="text-sm text-primary-400 hover:text-primary-300">
                    + Aktion hinzufügen
                  </button>
                </div>

                <div v-if="workflowForm.actions.length === 0" class="text-center py-8 bg-white/[0.03] rounded-lg">
                  <p class="text-gray-500">Keine Aktionen definiert</p>
                  <button @click="addAction" class="text-sm text-primary-400 hover:text-primary-300 mt-2">
                    Erste Aktion hinzufügen
                  </button>
                </div>

                <div v-else class="space-y-3">
                  <div
                    v-for="(action, index) in workflowForm.actions"
                    :key="index"
                    class="p-4 bg-white/[0.03] rounded-lg"
                  >
                    <div class="flex items-center justify-between mb-3">
                      <span class="text-xs text-gray-500">Aktion {{ index + 1 }}</span>
                      <button @click="removeAction(index)" class="text-red-400 hover:text-red-300">
                        <TrashIcon class="w-4 h-4" />
                      </button>
                    </div>

                    <select v-model="action.action_type" class="select mb-3">
                      <option v-for="(info, type) in options.action_types" :key="type" :value="type">
                        {{ info.name }}
                      </option>
                    </select>

                    <!-- Action-specific config -->
                    <div v-if="action.action_type === 'send_notification'" class="space-y-2">
                      <input v-model="action.config.title" type="text" class="input" placeholder="Titel" />
                      <textarea v-model="action.config.body" class="input" rows="2" placeholder="Nachricht ({{variable}} für dynamische Werte)"></textarea>
                    </div>

                    <div v-if="action.action_type === 'http_request'" class="space-y-2">
                      <div class="flex gap-2">
                        <select v-model="action.config.method" class="select w-24">
                          <option value="GET">GET</option>
                          <option value="POST">POST</option>
                          <option value="PUT">PUT</option>
                          <option value="DELETE">DELETE</option>
                        </select>
                        <input v-model="action.config.url" type="text" class="input flex-1" placeholder="https://..." />
                      </div>
                    </div>

                    <div v-if="action.action_type === 'delay'" class="space-y-2">
                      <div class="flex items-center gap-2">
                        <input v-model.number="action.config.seconds" type="number" min="1" max="60" class="input w-24" />
                        <span class="text-gray-400">Sekunden warten</span>
                      </div>
                    </div>

                    <label class="flex items-center gap-2 mt-3 text-sm text-gray-400">
                      <input v-model="action.continue_on_error" type="checkbox" class="rounded" />
                      Bei Fehler fortfahren
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-white/[0.06]">
              <button @click="showCreateModal = false" class="btn-secondary">
                Abbrechen
              </button>
              <button @click="saveWorkflow" class="btn-primary">
                {{ editMode ? 'Speichern' : 'Erstellen' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Templates Modal -->
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
          v-if="showTemplatesModal"
          class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4"
        >
          <div class="modal w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-white/[0.06]">
              <h3 class="text-lg font-semibold text-white">Workflow-Vorlagen</h3>
              <button @click="showTemplatesModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <div class="p-6 space-y-4">
              <div
                v-for="template in templates"
                :key="template.id"
                class="p-4 bg-white/[0.04] rounded-lg hover:bg-white/[0.04] transition-colors"
              >
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                      <h4 class="font-medium text-white">{{ template.name }}</h4>
                      <span v-if="template.is_featured" class="px-2 py-0.5 rounded text-xs bg-yellow-500/20 text-yellow-400">
                        Empfohlen
                      </span>
                    </div>
                    <p class="text-sm text-gray-400">{{ template.description }}</p>
                    <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                      <span>{{ getTriggerLabel(template.trigger_type) }}</span>
                      <span>{{ template.use_count }} mal verwendet</span>
                    </div>
                  </div>
                  <button @click="createFromTemplate(template)" class="btn-primary text-sm">
                    Verwenden
                  </button>
                </div>
              </div>

              <p v-if="templates.length === 0" class="text-center text-gray-500 py-4">
                Keine Vorlagen verfügbar
              </p>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- History Modal -->
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
          v-if="showHistoryModal"
          class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4"
        >
          <div class="modal w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-white/[0.06]">
              <h3 class="text-lg font-semibold text-white">
                Ausführungs-Historie: {{ selectedWorkflow?.name }}
              </h3>
              <button @click="showHistoryModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <div class="p-6">
              <div v-if="runHistory.length === 0" class="text-center py-8">
                <ClockIcon class="w-12 h-12 text-gray-600 mx-auto mb-2" />
                <p class="text-gray-500">Noch keine Ausführungen</p>
              </div>

              <div v-else class="space-y-3">
                <div
                  v-for="run in runHistory"
                  :key="run.id"
                  class="flex items-center justify-between p-4 bg-white/[0.04] rounded-lg"
                >
                  <div class="flex items-center gap-3">
                    <div
                      class="w-8 h-8 rounded-lg flex items-center justify-center"
                      :class="getStatusColor(run.status)"
                    >
                      <component :is="getStatusIcon(run.status)" class="w-4 h-4" />
                    </div>
                    <div>
                      <p class="text-sm text-white">{{ formatDate(run.started_at) }}</p>
                      <p class="text-xs text-gray-500">
                        Dauer: {{ run.duration_ms ? `${run.duration_ms}ms` : '-' }}
                      </p>
                    </div>
                  </div>
                  <span
                    class="px-2 py-1 rounded text-xs"
                    :class="getStatusColor(run.status)"
                  >
                    {{ run.status }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
