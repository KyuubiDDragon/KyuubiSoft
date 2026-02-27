<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import {
  KeyIcon,
  PlusIcon,
  TrashIcon,
  PencilSquareIcon,
  DocumentDuplicateIcon,
  ArrowDownTrayIcon,
  ArrowUpTrayIcon,
  EyeIcon,
  EyeSlashIcon,
  ClockIcon,
  FolderIcon,
  XMarkIcon,
  CheckIcon,
  MagnifyingGlassIcon,
  LockClosedIcon,
  LockOpenIcon,
} from '@heroicons/vue/24/outline'
import { useEnvironmentStore } from '@/modules/environments/stores/environmentStore'

const envStore = useEnvironmentStore()

// Local state
const searchQuery = ref('')
const selectedEnvId = ref(null)
const activeTab = ref('variables') // 'variables' | 'history'

// Modals
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showImportModal = ref(false)
const showExportModal = ref(false)
const deleteConfirmId = ref(null)
const deleteVarConfirmId = ref(null)

// Form state for create/edit
const envForm = ref({
  name: '',
  slug: '',
  description: '',
  project_id: null,
})

// Import state
const importContent = ref('')
const importOverwrite = ref(false)

// Export state
const exportContent = ref('')
const exportFilename = ref('')

// Variable editing state
const editingVariables = ref([])
const hasUnsavedChanges = ref(false)

// Computed: environments grouped by project
const groupedEnvironments = computed(() => {
  const filtered = envStore.environments.filter((e) => {
    if (!searchQuery.value) return true
    const q = searchQuery.value.toLowerCase()
    return (
      e.name.toLowerCase().includes(q) ||
      e.slug.toLowerCase().includes(q) ||
      (e.description && e.description.toLowerCase().includes(q))
    )
  })

  const groups = {}
  for (const env of filtered) {
    const key = env.project_name || 'Ohne Projekt'
    if (!groups[key]) {
      groups[key] = []
    }
    groups[key].push(env)
  }
  return groups
})

const selectedEnvironment = computed(() => {
  if (!selectedEnvId.value) return null
  return envStore.environments.find((e) => e.id === selectedEnvId.value) || null
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

function getActionLabel(action) {
  const labels = {
    created: 'Erstellt',
    updated: 'Aktualisiert',
    variables_updated: 'Variablen aktualisiert',
    variable_deleted: 'Variable geloescht',
    duplicated: 'Dupliziert',
    imported: 'Importiert',
  }
  return labels[action] || action
}

// Select environment
async function selectEnvironment(envId) {
  if (hasUnsavedChanges.value) {
    if (!confirm('Ungespeicherte Aenderungen verwerfen?')) return
  }
  selectedEnvId.value = envId
  envStore.currentEnvironment =
    envStore.environments.find((e) => e.id === envId) || null
  activeTab.value = 'variables'
  await envStore.fetchVariables(envId)
  syncEditingVariables()
}

function syncEditingVariables() {
  editingVariables.value = envStore.variables.map((v) => ({
    id: v.id,
    key: v.var_key,
    value: v.var_value,
    is_secret: v.is_secret,
    isNew: false,
  }))
  hasUnsavedChanges.value = false
}

function markChanged() {
  hasUnsavedChanges.value = true
}

// Add new variable row
function addVariable() {
  editingVariables.value.push({
    id: null,
    key: '',
    value: '',
    is_secret: false,
    isNew: true,
  })
  hasUnsavedChanges.value = true
  nextTick(() => {
    const inputs = document.querySelectorAll('.var-key-input')
    if (inputs.length > 0) {
      inputs[inputs.length - 1].focus()
    }
  })
}

// Remove variable from editing list
function removeVariable(index) {
  editingVariables.value.splice(index, 1)
  hasUnsavedChanges.value = true
}

// Toggle secret for a variable
function toggleSecret(index) {
  editingVariables.value[index].is_secret = !editingVariables.value[index].is_secret
  hasUnsavedChanges.value = true
}

// Save all variables
async function saveVariables() {
  if (!selectedEnvId.value) return
  const vars = editingVariables.value
    .filter((v) => v.key.trim() !== '')
    .map((v) => ({
      key: v.key.trim(),
      value: v.value,
      is_secret: v.is_secret,
    }))
  const result = await envStore.setVariables(selectedEnvId.value, vars)
  if (result) {
    syncEditingVariables()
  }
}

// Delete a single variable via API
async function handleDeleteVariable(varId) {
  if (!selectedEnvId.value || !varId) return
  await envStore.deleteVariable(selectedEnvId.value, varId)
  syncEditingVariables()
  deleteVarConfirmId.value = null
}

// Create modal
function openCreateModal() {
  envForm.value = { name: '', slug: '', description: '', project_id: null }
  showCreateModal.value = true
}

async function handleCreate() {
  const result = await envStore.createEnvironment({
    name: envForm.value.name,
    slug: envForm.value.slug || undefined,
    description: envForm.value.description || undefined,
    project_id: envForm.value.project_id || undefined,
  })
  if (result) {
    showCreateModal.value = false
    await selectEnvironment(result.id)
  }
}

// Edit modal
function openEditModal() {
  if (!selectedEnvironment.value) return
  envForm.value = {
    name: selectedEnvironment.value.name,
    slug: selectedEnvironment.value.slug,
    description: selectedEnvironment.value.description || '',
    project_id: selectedEnvironment.value.project_id,
  }
  showEditModal.value = true
}

async function handleUpdate() {
  if (!selectedEnvId.value) return
  const result = await envStore.updateEnvironment(selectedEnvId.value, {
    name: envForm.value.name,
    slug: envForm.value.slug,
    description: envForm.value.description || null,
    project_id: envForm.value.project_id || null,
  })
  if (result) {
    showEditModal.value = false
  }
}

// Delete environment
function confirmDeleteEnv(envId) {
  deleteConfirmId.value = envId
}

async function handleDeleteEnv() {
  if (!deleteConfirmId.value) return
  const id = deleteConfirmId.value
  await envStore.deleteEnvironment(id)
  deleteConfirmId.value = null
  if (selectedEnvId.value === id) {
    selectedEnvId.value = null
    editingVariables.value = []
  }
}

// Duplicate
async function handleDuplicate() {
  if (!selectedEnvId.value) return
  const result = await envStore.duplicateEnvironment(selectedEnvId.value)
  if (result) {
    await selectEnvironment(result.id)
  }
}

// Export
async function handleExport() {
  if (!selectedEnvId.value) return
  const result = await envStore.exportEnv(selectedEnvId.value)
  if (result) {
    exportContent.value = result.content
    exportFilename.value = result.filename
    showExportModal.value = true
  }
}

function downloadExport() {
  const blob = new Blob([exportContent.value], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = exportFilename.value || '.env'
  a.click()
  URL.revokeObjectURL(url)
}

function copyExport() {
  navigator.clipboard.writeText(exportContent.value)
}

// Import
function openImportModal() {
  importContent.value = ''
  importOverwrite.value = false
  showImportModal.value = true
}

async function handleImport() {
  if (!selectedEnvId.value) return
  const success = await envStore.importEnv(
    selectedEnvId.value,
    importContent.value,
    importOverwrite.value
  )
  if (success) {
    showImportModal.value = false
    syncEditingVariables()
  }
}

// History tab
async function showHistory() {
  if (!selectedEnvId.value) return
  activeTab.value = 'history'
  await envStore.fetchHistory(selectedEnvId.value)
}

// Auto-generate slug from name
function onNameInput() {
  if (!showEditModal.value) {
    envForm.value.slug = envForm.value.name
      .toLowerCase()
      .replace(/[^a-z0-9\-_]/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '')
  }
}

onMounted(async () => {
  await envStore.fetchEnvironments()
})
</script>

<template>
  <div class="flex h-full gap-6">
    <!-- Left Sidebar: Environment List -->
    <div class="w-80 flex-shrink-0 flex flex-col space-y-4">
      <!-- Header -->
      <div>
        <h1 class="text-2xl font-bold text-white">Umgebungsvariablen</h1>
        <p class="text-gray-400 text-sm mt-1">Secrets und Konfiguration verwalten</p>
      </div>

      <!-- Search -->
      <div class="relative">
        <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Umgebung suchen..."
          class="input pl-9 w-full"
        />
      </div>

      <!-- New Button -->
      <button @click="openCreateModal" class="btn-primary w-full">
        <PlusIcon class="w-5 h-5 mr-2" />
        Neue Umgebung
      </button>

      <!-- Loading -->
      <div v-if="envStore.loading" class="flex justify-center py-8">
        <div class="w-6 h-6 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <!-- Environment List -->
      <div v-else class="flex-1 overflow-y-auto space-y-4 pr-1">
        <div v-if="Object.keys(groupedEnvironments).length === 0" class="text-center text-gray-500 py-8">
          <KeyIcon class="w-10 h-10 mx-auto mb-3 text-gray-600" />
          <p class="text-sm">Keine Umgebungen vorhanden.</p>
        </div>

        <div v-for="(envs, groupName) in groupedEnvironments" :key="groupName">
          <div class="flex items-center gap-2 px-1 mb-2">
            <FolderIcon class="w-3.5 h-3.5 text-gray-500" />
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ groupName }}</span>
            <span class="text-xs text-gray-600">({{ envs.length }})</span>
          </div>

          <div class="space-y-1">
            <button
              v-for="env in envs"
              :key="env.id"
              @click="selectEnvironment(env.id)"
              class="w-full text-left px-3 py-2.5 rounded-lg transition-all duration-150"
              :class="selectedEnvId === env.id
                ? 'bg-primary-600/20 border border-primary-500/30 text-white'
                : 'hover:bg-white/[0.04] text-gray-400 hover:text-gray-200 border border-transparent'"
            >
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium truncate">{{ env.name }}</span>
                <span class="text-xs text-gray-600 ml-2 flex-shrink-0">{{ env.variable_count }} Var.</span>
              </div>
              <div v-if="env.description" class="text-xs text-gray-500 truncate mt-0.5">{{ env.description }}</div>
              <div class="text-xs text-gray-600 font-mono mt-0.5">{{ env.slug }}</div>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Panel: Variable Editor -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- No selection state -->
      <div v-if="!selectedEnvironment" class="flex-1 flex items-center justify-center">
        <div class="text-center">
          <KeyIcon class="w-16 h-16 text-gray-700 mx-auto mb-4" />
          <h3 class="text-lg font-medium text-gray-400 mb-2">Keine Umgebung ausgewaehlt</h3>
          <p class="text-gray-600 text-sm">Waehlen Sie eine Umgebung aus der Liste oder erstellen Sie eine neue.</p>
        </div>
      </div>

      <!-- Selected environment -->
      <template v-else>
        <!-- Environment Header -->
        <div class="card-glass p-4 mb-4">
          <div class="flex items-center justify-between">
            <div class="min-w-0">
              <h2 class="text-lg font-semibold text-white truncate">{{ selectedEnvironment.name }}</h2>
              <div class="flex items-center gap-3 mt-1">
                <code class="text-xs text-gray-500 font-mono">{{ selectedEnvironment.slug }}</code>
                <span v-if="selectedEnvironment.description" class="text-xs text-gray-500">{{ selectedEnvironment.description }}</span>
              </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <button @click="openEditModal" class="btn-secondary text-xs">
                <PencilSquareIcon class="w-4 h-4 mr-1" />
                Bearbeiten
              </button>
              <button @click="handleDuplicate" class="btn-secondary text-xs">
                <DocumentDuplicateIcon class="w-4 h-4 mr-1" />
                Duplizieren
              </button>
              <button @click="handleExport" class="btn-secondary text-xs">
                <ArrowDownTrayIcon class="w-4 h-4 mr-1" />
                Export
              </button>
              <button @click="openImportModal" class="btn-secondary text-xs">
                <ArrowUpTrayIcon class="w-4 h-4 mr-1" />
                Import
              </button>
              <template v-if="deleteConfirmId === selectedEnvId">
                <span class="text-xs text-red-400 ml-2">Wirklich loeschen?</span>
                <button @click="handleDeleteEnv" class="btn-secondary text-xs text-red-400 border-red-500/30 hover:bg-red-500/10">Ja</button>
                <button @click="deleteConfirmId = null" class="btn-secondary text-xs">Nein</button>
              </template>
              <button v-else @click="confirmDeleteEnv(selectedEnvId)" class="btn-secondary text-xs text-red-400/60 hover:text-red-400">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        <!-- Tabs -->
        <div class="flex items-center gap-1 mb-4">
          <button
            @click="activeTab = 'variables'"
            class="px-4 py-2 text-sm rounded-lg transition-colors"
            :class="activeTab === 'variables'
              ? 'bg-primary-600/20 text-primary-400 font-medium'
              : 'text-gray-500 hover:text-gray-300 hover:bg-white/[0.04]'"
          >
            <KeyIcon class="w-4 h-4 inline mr-1.5 -mt-0.5" />
            Variablen
          </button>
          <button
            @click="showHistory"
            class="px-4 py-2 text-sm rounded-lg transition-colors"
            :class="activeTab === 'history'
              ? 'bg-primary-600/20 text-primary-400 font-medium'
              : 'text-gray-500 hover:text-gray-300 hover:bg-white/[0.04]'"
          >
            <ClockIcon class="w-4 h-4 inline mr-1.5 -mt-0.5" />
            Historie
          </button>
        </div>

        <!-- Variables Tab -->
        <div v-if="activeTab === 'variables'" class="flex-1 flex flex-col min-h-0">
          <!-- Save bar -->
          <div v-if="hasUnsavedChanges" class="card-glass p-3 mb-4 flex items-center justify-between border border-yellow-500/20">
            <span class="text-sm text-yellow-400">Ungespeicherte Aenderungen vorhanden</span>
            <div class="flex gap-2">
              <button @click="syncEditingVariables" class="btn-secondary text-xs">Verwerfen</button>
              <button @click="saveVariables" class="btn-primary text-xs">
                <CheckIcon class="w-4 h-4 mr-1" />
                Speichern
              </button>
            </div>
          </div>

          <!-- Variables table -->
          <div class="card-glass flex-1 overflow-y-auto">
            <table class="table-glass w-full">
              <thead>
                <tr>
                  <th class="text-left text-xs text-gray-500 font-medium p-3 w-[35%]">Schluessel</th>
                  <th class="text-left text-xs text-gray-500 font-medium p-3 w-[45%]">Wert</th>
                  <th class="text-center text-xs text-gray-500 font-medium p-3 w-[10%]">Secret</th>
                  <th class="text-right text-xs text-gray-500 font-medium p-3 w-[10%]">Aktionen</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(variable, index) in editingVariables"
                  :key="variable.id || 'new-' + index"
                  class="border-t border-white/[0.04] group"
                >
                  <td class="p-2">
                    <input
                      v-model="variable.key"
                      @input="markChanged"
                      type="text"
                      class="var-key-input input w-full font-mono text-sm"
                      placeholder="KEY_NAME"
                    />
                  </td>
                  <td class="p-2">
                    <input
                      v-model="variable.value"
                      @input="markChanged"
                      :type="variable.is_secret ? 'password' : 'text'"
                      class="input w-full font-mono text-sm"
                      placeholder="Wert eingeben..."
                    />
                  </td>
                  <td class="p-2 text-center">
                    <button
                      @click="toggleSecret(index)"
                      class="p-1.5 rounded-lg transition-colors"
                      :class="variable.is_secret
                        ? 'text-yellow-400 bg-yellow-500/10 hover:bg-yellow-500/20'
                        : 'text-gray-600 hover:text-gray-400 hover:bg-white/[0.04]'"
                      :title="variable.is_secret ? 'Secret (maskiert)' : 'Sichtbar'"
                    >
                      <LockClosedIcon v-if="variable.is_secret" class="w-4 h-4" />
                      <LockOpenIcon v-else class="w-4 h-4" />
                    </button>
                  </td>
                  <td class="p-2 text-right">
                    <template v-if="deleteVarConfirmId === (variable.id || 'new-' + index)">
                      <button
                        @click="variable.id ? handleDeleteVariable(variable.id) : removeVariable(index)"
                        class="text-xs text-red-400 hover:text-red-300 mr-1"
                      >Ja</button>
                      <button @click="deleteVarConfirmId = null" class="text-xs text-gray-400 hover:text-white">Nein</button>
                    </template>
                    <button
                      v-else
                      @click="deleteVarConfirmId = variable.id || 'new-' + index"
                      class="p-1.5 rounded-lg text-gray-600 hover:text-red-400 hover:bg-red-500/10 transition-colors opacity-0 group-hover:opacity-100"
                    >
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Empty state -->
            <div v-if="editingVariables.length === 0" class="text-center py-12">
              <KeyIcon class="w-10 h-10 text-gray-700 mx-auto mb-3" />
              <p class="text-sm text-gray-500">Keine Variablen vorhanden.</p>
              <p class="text-xs text-gray-600 mt-1">Fuegen Sie die erste Variable hinzu.</p>
            </div>

            <!-- Add variable button -->
            <div class="p-3 border-t border-white/[0.04]">
              <button @click="addVariable" class="btn-secondary text-xs w-full justify-center">
                <PlusIcon class="w-4 h-4 mr-1" />
                Variable hinzufuegen
              </button>
            </div>
          </div>
        </div>

        <!-- History Tab -->
        <div v-if="activeTab === 'history'" class="flex-1 overflow-y-auto">
          <div class="card-glass">
            <div v-if="envStore.history.length === 0" class="text-center py-12 text-gray-500">
              <ClockIcon class="w-10 h-10 mx-auto mb-3 text-gray-700" />
              <p class="text-sm">Keine Historie vorhanden.</p>
            </div>

            <div v-else class="divide-y divide-white/[0.04]">
              <div
                v-for="entry in envStore.history"
                :key="entry.id"
                class="p-4 flex items-start gap-4"
              >
                <div class="w-8 h-8 rounded-full bg-primary-600/15 flex items-center justify-center flex-shrink-0">
                  <ClockIcon class="w-4 h-4 text-primary-400" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="text-sm font-medium text-white">{{ getActionLabel(entry.action) }}</span>
                    <span class="text-xs text-gray-600">von {{ entry.user_name || 'System' }}</span>
                  </div>
                  <div class="text-xs text-gray-500">{{ formatTimestamp(entry.created_at) }}</div>
                  <!-- Show change details -->
                  <div v-if="entry.changes" class="mt-2">
                    <template v-if="Array.isArray(entry.changes)">
                      <div
                        v-for="(change, ci) in entry.changes.slice(0, 10)"
                        :key="ci"
                        class="text-xs text-gray-500 font-mono mt-0.5"
                      >
                        <span class="text-gray-400">{{ change.key || change.action }}</span>
                        <span v-if="change.old_value"> {{ change.old_value }} -&gt; {{ change.new_value }}</span>
                      </div>
                      <div v-if="entry.changes.length > 10" class="text-xs text-gray-600 mt-1">
                        ... und {{ entry.changes.length - 10 }} weitere
                      </div>
                    </template>
                    <template v-else-if="typeof entry.changes === 'object'">
                      <div
                        v-for="(val, key) in entry.changes"
                        :key="key"
                        class="text-xs text-gray-500 font-mono mt-0.5"
                      >
                        <span class="text-gray-400">{{ key }}:</span>
                        <template v-if="val && typeof val === 'object' && val.old !== undefined">
                          {{ val.old }} -&gt; {{ val.new }}
                        </template>
                        <template v-else>
                          {{ typeof val === 'object' ? JSON.stringify(val) : val }}
                        </template>
                      </div>
                    </template>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Create/Edit Environment Modal -->
    <Teleport to="body">
      <div
        v-if="showCreateModal || showEditModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="showCreateModal = false; showEditModal = false"></div>
        <div class="relative card-glass w-full max-w-lg">
          <div class="modal-header flex items-center justify-between p-5 border-b border-white/[0.06]">
            <h3 class="text-lg font-semibold text-white">
              {{ showCreateModal ? 'Neue Umgebung erstellen' : 'Umgebung bearbeiten' }}
            </h3>
            <button @click="showCreateModal = false; showEditModal = false" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <div class="modal-body p-5 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">Name *</label>
              <input
                v-model="envForm.name"
                @input="onNameInput"
                type="text"
                class="input w-full"
                placeholder="z.B. Produktion, Staging, Entwicklung..."
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">Slug</label>
              <input
                v-model="envForm.slug"
                type="text"
                class="input w-full font-mono"
                placeholder="automatisch generiert"
              />
              <p class="text-xs text-gray-600 mt-1">Eindeutiger Bezeichner (URL-sicher)</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">Beschreibung</label>
              <textarea
                v-model="envForm.description"
                class="input w-full"
                rows="3"
                placeholder="Optionale Beschreibung..."
              ></textarea>
            </div>
          </div>
          <div class="modal-footer flex items-center justify-end gap-3 p-5 border-t border-white/[0.06]">
            <button @click="showCreateModal = false; showEditModal = false" class="btn-secondary">
              Abbrechen
            </button>
            <button
              @click="showCreateModal ? handleCreate() : handleUpdate()"
              class="btn-primary"
              :disabled="!envForm.name.trim()"
            >
              {{ showCreateModal ? 'Erstellen' : 'Speichern' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Import Modal -->
    <Teleport to="body">
      <div
        v-if="showImportModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="showImportModal = false"></div>
        <div class="relative card-glass w-full max-w-2xl">
          <div class="modal-header flex items-center justify-between p-5 border-b border-white/[0.06]">
            <h3 class="text-lg font-semibold text-white">Variablen importieren (.env)</h3>
            <button @click="showImportModal = false" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <div class="modal-body p-5 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">.env Inhalt einfuegen</label>
              <textarea
                v-model="importContent"
                class="input w-full font-mono text-sm"
                rows="12"
                placeholder="KEY=value
DATABASE_URL=postgres://...
SECRET_KEY=abc123"
              ></textarea>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                v-model="importOverwrite"
                type="checkbox"
                class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-primary-600 focus:ring-primary-500"
              />
              <span class="text-sm text-gray-400">Bestehende Variablen ueberschreiben</span>
            </label>
          </div>
          <div class="modal-footer flex items-center justify-end gap-3 p-5 border-t border-white/[0.06]">
            <button @click="showImportModal = false" class="btn-secondary">Abbrechen</button>
            <button @click="handleImport" class="btn-primary" :disabled="!importContent.trim()">
              <ArrowUpTrayIcon class="w-4 h-4 mr-1" />
              Importieren
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Export Modal -->
    <Teleport to="body">
      <div
        v-if="showExportModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="showExportModal = false"></div>
        <div class="relative card-glass w-full max-w-2xl">
          <div class="modal-header flex items-center justify-between p-5 border-b border-white/[0.06]">
            <h3 class="text-lg font-semibold text-white">Umgebung exportieren</h3>
            <button @click="showExportModal = false" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <div class="modal-body p-5 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ exportFilename }}</label>
              <pre class="font-mono text-xs text-gray-300 bg-black/30 rounded-lg p-4 overflow-x-auto whitespace-pre-wrap max-h-80 overflow-y-auto">{{ exportContent }}</pre>
            </div>
          </div>
          <div class="modal-footer flex items-center justify-end gap-3 p-5 border-t border-white/[0.06]">
            <button @click="showExportModal = false" class="btn-secondary">Schliessen</button>
            <button @click="copyExport" class="btn-secondary">
              In Zwischenablage kopieren
            </button>
            <button @click="downloadExport" class="btn-primary">
              <ArrowDownTrayIcon class="w-4 h-4 mr-1" />
              Herunterladen
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
