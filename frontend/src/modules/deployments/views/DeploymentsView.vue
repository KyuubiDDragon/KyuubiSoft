<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  RocketLaunchIcon,
  PlusIcon,
  PencilSquareIcon,
  TrashIcon,
  PlayIcon,
  EyeIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  XMarkIcon,
  ArrowPathIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon,
  ClockIcon,
  XCircleIcon,
  ArrowUturnLeftIcon,
  CodeBracketIcon,
} from '@heroicons/vue/24/outline'
import { useDeploymentStore } from '@/modules/deployments/stores/deploymentStore'

const deploymentStore = useDeploymentStore()

// Local state
const showPipelineModal = ref(false)
const showDeploymentDetailModal = ref(false)
const showRollbackConfirm = ref(false)
const showDeployModal = ref(false)
const editingPipeline = ref(null)
const expandedPipeline = ref(null)
const selectedDeployment = ref(null)
const rollbackTargetId = ref(null)
const deployTargetPipeline = ref(null)
const deleteConfirmId = ref(null)

// Deploy form
const deployForm = ref({
  commit_hash: '',
  commit_message: '',
})

// Pipeline form
const pipelineForm = ref({
  name: '',
  description: '',
  repository: '',
  branch: 'main',
  environment: 'production',
  connection_id: null,
  auto_deploy: false,
  notify_on_success: true,
  notify_on_failure: true,
  steps: [{ name: '', command: '', timeout: 60 }],
})

function resetPipelineForm() {
  pipelineForm.value = {
    name: '',
    description: '',
    repository: '',
    branch: 'main',
    environment: 'production',
    connection_id: null,
    auto_deploy: false,
    notify_on_success: true,
    notify_on_failure: true,
    steps: [{ name: '', command: '', timeout: 60 }],
  }
}

// Status badge helpers
const statusConfig = {
  pending: { label: 'Ausstehend', class: 'bg-gray-500/15 text-gray-400' },
  running: { label: 'Läuft', class: 'bg-amber-500/15 text-amber-400' },
  success: { label: 'Erfolgreich', class: 'bg-emerald-500/15 text-emerald-400' },
  failed: { label: 'Fehlgeschlagen', class: 'bg-red-500/15 text-red-400' },
  cancelled: { label: 'Abgebrochen', class: 'bg-gray-500/15 text-gray-500' },
  rolled_back: { label: 'Zurückgerollt', class: 'bg-purple-500/15 text-purple-400' },
}

const environmentConfig = {
  production: { label: 'Production', class: 'bg-red-500/15 text-red-400' },
  staging: { label: 'Staging', class: 'bg-amber-500/15 text-amber-400' },
  development: { label: 'Development', class: 'bg-blue-500/15 text-blue-400' },
}

function getStatusBadge(status) {
  return statusConfig[status] || statusConfig.pending
}

function getEnvironmentBadge(env) {
  return environmentConfig[env] || environmentConfig.production
}

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

function formatRelativeTime(dateString) {
  if (!dateString) return '-'
  const now = new Date()
  const date = new Date(dateString)
  const diffMs = now.getTime() - date.getTime()
  const diffSec = Math.floor(diffMs / 1000)
  const diffMin = Math.floor(diffSec / 60)
  const diffHour = Math.floor(diffMin / 60)
  const diffDay = Math.floor(diffHour / 24)

  if (diffSec < 60) return 'gerade eben'
  if (diffMin < 60) return `vor ${diffMin} Min.`
  if (diffHour < 24) return `vor ${diffHour} Std.`
  if (diffDay === 1) return 'gestern'
  if (diffDay < 7) return `vor ${diffDay} Tagen`

  return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
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

function shortHash(hash) {
  if (!hash) return '-'
  return hash.substring(0, 7)
}

// Pipeline modal actions
function openCreateModal() {
  editingPipeline.value = null
  resetPipelineForm()
  showPipelineModal.value = true
}

function openEditModal(pipeline) {
  editingPipeline.value = pipeline
  pipelineForm.value = {
    name: pipeline.name || '',
    description: pipeline.description || '',
    repository: pipeline.repository || '',
    branch: pipeline.branch || 'main',
    environment: pipeline.environment || 'production',
    connection_id: pipeline.connection_id || null,
    auto_deploy: pipeline.auto_deploy || false,
    notify_on_success: pipeline.notify_on_success !== false,
    notify_on_failure: pipeline.notify_on_failure !== false,
    steps: pipeline.steps && pipeline.steps.length > 0
      ? pipeline.steps.map((s) => ({ ...s }))
      : [{ name: '', command: '', timeout: 60 }],
  }
  showPipelineModal.value = true
}

function closePipelineModal() {
  showPipelineModal.value = false
  editingPipeline.value = null
}

function addStep() {
  pipelineForm.value.steps.push({ name: '', command: '', timeout: 60 })
}

function removeStep(index) {
  if (pipelineForm.value.steps.length > 1) {
    pipelineForm.value.steps.splice(index, 1)
  }
}

function moveStep(index, direction) {
  const steps = pipelineForm.value.steps
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= steps.length) return
  const temp = steps[index]
  steps[index] = steps[newIndex]
  steps[newIndex] = temp
}

async function handleSavePipeline() {
  const data = { ...pipelineForm.value }
  if (editingPipeline.value) {
    const result = await deploymentStore.updatePipeline(editingPipeline.value.id, data)
    if (result) {
      closePipelineModal()
      await deploymentStore.fetchPipelines()
    }
  } else {
    const result = await deploymentStore.createPipeline(data)
    if (result) {
      closePipelineModal()
      await deploymentStore.fetchPipelines()
    }
  }
}

// Deploy actions
function openDeployModal(pipeline) {
  deployTargetPipeline.value = pipeline
  deployForm.value = { commit_hash: '', commit_message: '' }
  showDeployModal.value = true
}

function closeDeployModal() {
  showDeployModal.value = false
  deployTargetPipeline.value = null
}

async function handleDeploy() {
  if (!deployTargetPipeline.value) return
  const result = await deploymentStore.deploy(deployTargetPipeline.value.id, {
    commit_hash: deployForm.value.commit_hash || undefined,
    commit_message: deployForm.value.commit_message || undefined,
  })
  if (result) {
    closeDeployModal()
    await deploymentStore.fetchPipelines()
    await deploymentStore.fetchStats()
  }
}

// Delete
function confirmDelete(pipelineId) {
  deleteConfirmId.value = pipelineId
}

async function handleDelete(pipelineId) {
  await deploymentStore.deletePipeline(pipelineId)
  deleteConfirmId.value = null
  await deploymentStore.fetchStats()
}

function cancelDelete() {
  deleteConfirmId.value = null
}

// Deployment history
async function toggleDeploymentHistory(pipelineId) {
  if (expandedPipeline.value === pipelineId) {
    expandedPipeline.value = null
    return
  }
  expandedPipeline.value = pipelineId
  await deploymentStore.fetchDeployments(pipelineId)
}

// Deployment detail
async function showDeploymentDetail(deployment) {
  const full = await deploymentStore.fetchDeployment(deployment.id)
  if (full) {
    selectedDeployment.value = full
    showDeploymentDetailModal.value = true
  }
}

function closeDeploymentDetailModal() {
  showDeploymentDetailModal.value = false
  selectedDeployment.value = null
}

// Rollback
function confirmRollback(deploymentId) {
  rollbackTargetId.value = deploymentId
  showRollbackConfirm.value = true
}

async function handleRollback() {
  if (!rollbackTargetId.value) return
  const result = await deploymentStore.rollback(rollbackTargetId.value)
  if (result) {
    showRollbackConfirm.value = false
    rollbackTargetId.value = null
    // Refresh data
    if (expandedPipeline.value) {
      await deploymentStore.fetchDeployments(expandedPipeline.value)
    }
    await deploymentStore.fetchPipelines()
    await deploymentStore.fetchStats()
  }
}

function cancelRollback() {
  showRollbackConfirm.value = false
  rollbackTargetId.value = null
}

// Cancel deployment
async function handleCancelDeployment(deploymentId) {
  await deploymentStore.cancelDeployment(deploymentId)
  if (expandedPipeline.value) {
    await deploymentStore.fetchDeployments(expandedPipeline.value)
  }
  await deploymentStore.fetchStats()
}

onMounted(async () => {
  await Promise.all([
    deploymentStore.fetchPipelines(),
    deploymentStore.fetchStats(),
    deploymentStore.fetchConnections(),
  ])
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Deployments</h1>
        <p class="text-gray-400 mt-1">Deployment-Pipelines verwalten und überwachen</p>
      </div>
      <button @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Pipeline erstellen
      </button>
    </div>

    <!-- Stats Row -->
    <div v-if="deploymentStore.stats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="card-glass p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-blue-500/15 flex items-center justify-center">
            <RocketLaunchIcon class="w-5 h-5 text-blue-400" />
          </div>
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wider">Total Deployments</p>
            <p class="text-xl font-bold text-white">{{ deploymentStore.stats.total }}</p>
          </div>
        </div>
      </div>
      <div class="card-glass p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-emerald-500/15 flex items-center justify-center">
            <CheckCircleIcon class="w-5 h-5 text-emerald-400" />
          </div>
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wider">Erfolgsrate</p>
            <p class="text-xl font-bold text-white">{{ deploymentStore.stats.success_rate }}%</p>
          </div>
        </div>
      </div>
      <div class="card-glass p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-amber-500/15 flex items-center justify-center">
            <ClockIcon class="w-5 h-5 text-amber-400" />
          </div>
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wider">Durchschn. Dauer</p>
            <p class="text-xl font-bold text-white">{{ formatDuration(deploymentStore.stats.avg_duration_ms) }}</p>
          </div>
        </div>
      </div>
      <div class="card-glass p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-red-500/15 flex items-center justify-center">
            <XCircleIcon class="w-5 h-5 text-red-400" />
          </div>
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wider">Letzte Fehler</p>
            <p class="text-xl font-bold text-white">{{ deploymentStore.stats.recent_failures?.length || 0 }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="deploymentStore.loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="deploymentStore.pipelines.length === 0"
      class="card-glass p-12 text-center"
    >
      <RocketLaunchIcon class="w-12 h-12 text-gray-600 mx-auto mb-4" />
      <h3 class="text-lg font-medium text-gray-300 mb-2">Keine Pipelines vorhanden.</h3>
      <p class="text-gray-500 mb-6">Erstellen Sie Ihre erste Deployment-Pipeline.</p>
      <button @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Pipeline erstellen
      </button>
    </div>

    <!-- Pipeline List -->
    <template v-else>
      <div class="space-y-4">
        <div v-for="pipeline in deploymentStore.pipelines" :key="pipeline.id" class="card-glass p-5 space-y-3">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
              <!-- Pipeline name & environment -->
              <div class="flex items-center gap-3 mb-1">
                <h3 class="text-lg font-semibold text-white truncate">{{ pipeline.name }}</h3>
                <span
                  class="text-xs px-2 py-0.5 rounded-full"
                  :class="getEnvironmentBadge(pipeline.environment).class"
                >
                  {{ getEnvironmentBadge(pipeline.environment).label }}
                </span>
              </div>

              <!-- Description -->
              <p v-if="pipeline.description" class="text-sm text-gray-400 mb-2">{{ pipeline.description }}</p>

              <!-- Repo + Branch info -->
              <div class="flex items-center gap-4 text-xs text-gray-500">
                <div v-if="pipeline.repository" class="flex items-center gap-1.5">
                  <CodeBracketIcon class="w-3.5 h-3.5" />
                  <span class="truncate max-w-[250px]">{{ pipeline.repository }}</span>
                </div>
                <div v-if="pipeline.branch" class="flex items-center gap-1.5">
                  <span class="text-gray-600">/</span>
                  <span>{{ pipeline.branch }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span>{{ pipeline.steps?.length || 0 }} Schritte</span>
                </div>
              </div>

              <!-- Last deployment info -->
              <div class="flex items-center gap-3 mt-2">
                <span
                  v-if="pipeline.last_deployment_status"
                  class="text-xs px-2 py-0.5 rounded-full"
                  :class="getStatusBadge(pipeline.last_deployment_status).class"
                >
                  {{ getStatusBadge(pipeline.last_deployment_status).label }}
                </span>
                <span v-if="pipeline.last_deployment_at" class="text-xs text-gray-500">
                  {{ formatRelativeTime(pipeline.last_deployment_at) }}
                </span>
                <span v-if="pipeline.total_deployments" class="text-xs text-gray-600">
                  {{ pipeline.total_deployments }} Deployment{{ pipeline.total_deployments !== 1 ? 's' : '' }}
                </span>
              </div>
            </div>

            <!-- Deploy button -->
            <button
              @click="openDeployModal(pipeline)"
              class="btn-primary text-sm flex-shrink-0"
              :disabled="deploymentStore.deploying"
            >
              <PlayIcon class="w-4 h-4 mr-1.5" />
              Deploy
            </button>
          </div>

          <!-- Action buttons -->
          <div class="flex items-center gap-2 pt-2 border-t border-white/[0.06]">
            <button @click="openEditModal(pipeline)" class="btn-ghost text-xs text-gray-400 hover:text-white">
              <PencilSquareIcon class="w-4 h-4 mr-1" />
              Bearbeiten
            </button>
            <button @click="toggleDeploymentHistory(pipeline.id)" class="btn-ghost text-xs text-gray-400 hover:text-white">
              <EyeIcon class="w-4 h-4 mr-1" />
              Verlauf
              <component :is="expandedPipeline === pipeline.id ? ChevronUpIcon : ChevronDownIcon" class="w-3 h-3 ml-1" />
            </button>
            <div class="flex-1"></div>

            <!-- Delete with confirmation -->
            <template v-if="deleteConfirmId === pipeline.id">
              <span class="text-xs text-red-400 mr-2">Wirklich löschen?</span>
              <button @click="handleDelete(pipeline.id)" class="btn-ghost text-xs text-red-400 hover:text-red-300">
                Ja
              </button>
              <button @click="cancelDelete" class="btn-ghost text-xs text-gray-400 hover:text-white">
                Nein
              </button>
            </template>
            <button
              v-else
              @click="confirmDelete(pipeline.id)"
              class="btn-ghost text-xs text-red-400/60 hover:text-red-400"
            >
              <TrashIcon class="w-4 h-4 mr-1" />
              Löschen
            </button>
          </div>

          <!-- Deployment History (expandable) -->
          <div v-if="expandedPipeline === pipeline.id" class="mt-3 pt-3 border-t border-white/[0.06]">
            <div v-if="deploymentStore.deployments.length === 0" class="text-center text-gray-500 text-sm py-4">
              Keine Deployments vorhanden.
            </div>
            <div v-else class="overflow-x-auto">
              <table class="table-glass w-full">
                <thead>
                  <tr>
                    <th class="text-left text-xs text-gray-500 font-medium pb-2">Status</th>
                    <th class="text-left text-xs text-gray-500 font-medium pb-2">Commit</th>
                    <th class="text-left text-xs text-gray-500 font-medium pb-2">Gestartet</th>
                    <th class="text-left text-xs text-gray-500 font-medium pb-2">Dauer</th>
                    <th class="text-right text-xs text-gray-500 font-medium pb-2">Aktionen</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="dep in deploymentStore.deployments"
                    :key="dep.id"
                    class="border-t border-white/[0.04]"
                  >
                    <td class="py-2">
                      <span
                        class="text-xs px-2 py-0.5 rounded-full"
                        :class="getStatusBadge(dep.status).class"
                      >
                        {{ getStatusBadge(dep.status).label }}
                      </span>
                    </td>
                    <td class="py-2">
                      <code v-if="dep.commit_hash" class="text-xs text-gray-400 font-mono">{{ shortHash(dep.commit_hash) }}</code>
                      <span v-else class="text-xs text-gray-600">-</span>
                    </td>
                    <td class="py-2 text-xs text-gray-400">
                      {{ formatTimestamp(dep.started_at || dep.created_at) }}
                    </td>
                    <td class="py-2 text-xs text-gray-400">
                      {{ formatDuration(dep.duration_ms) }}
                    </td>
                    <td class="py-2 text-right space-x-2">
                      <button
                        @click="showDeploymentDetail(dep)"
                        class="btn-ghost text-xs text-primary-400 hover:text-primary-300"
                      >
                        Details
                      </button>
                      <button
                        v-if="dep.status === 'success'"
                        @click="confirmRollback(dep.id)"
                        class="btn-ghost text-xs text-purple-400 hover:text-purple-300"
                      >
                        Rollback
                      </button>
                      <button
                        v-if="dep.status === 'pending' || dep.status === 'running'"
                        @click="handleCancelDeployment(dep.id)"
                        class="btn-ghost text-xs text-red-400 hover:text-red-300"
                      >
                        Abbrechen
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Pipeline Builder Modal -->
    <Teleport to="body">
      <div
        v-if="showPipelineModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closePipelineModal"></div>
        <div class="relative card-glass w-full max-w-2xl max-h-[90vh] flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between p-5 border-b border-white/[0.06]">
            <h3 class="text-lg font-semibold text-white">
              {{ editingPipeline ? 'Pipeline bearbeiten' : 'Pipeline erstellen' }}
            </h3>
            <button @click="closePipelineModal" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-5 overflow-y-auto space-y-4">
            <!-- Name -->
            <div>
              <label class="block text-sm text-gray-400 mb-1">Name *</label>
              <input
                v-model="pipelineForm.name"
                type="text"
                class="input w-full"
                placeholder="z.B. Production Deploy"
              />
            </div>

            <!-- Description -->
            <div>
              <label class="block text-sm text-gray-400 mb-1">Beschreibung</label>
              <input
                v-model="pipelineForm.description"
                type="text"
                class="input w-full"
                placeholder="Optionale Beschreibung"
              />
            </div>

            <!-- Repository URL -->
            <div>
              <label class="block text-sm text-gray-400 mb-1">Repository URL</label>
              <input
                v-model="pipelineForm.repository"
                type="text"
                class="input w-full"
                placeholder="https://github.com/user/repo"
              />
            </div>

            <!-- Branch + Environment -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm text-gray-400 mb-1">Branch</label>
                <input
                  v-model="pipelineForm.branch"
                  type="text"
                  class="input w-full"
                  placeholder="main"
                />
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Umgebung</label>
                <select v-model="pipelineForm.environment" class="select w-full">
                  <option value="production">Production</option>
                  <option value="staging">Staging</option>
                  <option value="development">Development</option>
                </select>
              </div>
            </div>

            <!-- Server Connection -->
            <div>
              <label class="block text-sm text-gray-400 mb-1">Server-Verbindung</label>
              <select v-model="pipelineForm.connection_id" class="select w-full">
                <option :value="null">Lokal ausfuehren (kein SSH)</option>
                <option
                  v-for="conn in deploymentStore.availableConnections"
                  :key="conn.id"
                  :value="conn.id"
                >
                  {{ conn.name }} ({{ conn.host }})
                </option>
              </select>
              <p class="text-xs text-gray-600 mt-1">
                SSH-Verbindung für die Ausführung der Deployment-Schritte.
              </p>
            </div>

            <!-- Steps Builder -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="block text-sm text-gray-400">Deployment-Schritte *</label>
                <button @click="addStep" class="btn-ghost text-xs text-primary-400 hover:text-primary-300">
                  <PlusIcon class="w-3.5 h-3.5 mr-1" />
                  Schritt hinzufuegen
                </button>
              </div>
              <div class="space-y-3">
                <div
                  v-for="(step, index) in pipelineForm.steps"
                  :key="index"
                  class="p-3 rounded-lg bg-white/[0.03] border border-white/[0.06] space-y-2"
                >
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500 font-medium">Schritt {{ index + 1 }}</span>
                    <div class="flex items-center gap-1">
                      <button
                        v-if="index > 0"
                        @click="moveStep(index, -1)"
                        class="text-gray-500 hover:text-white p-0.5"
                        title="Nach oben"
                      >
                        <ChevronUpIcon class="w-3.5 h-3.5" />
                      </button>
                      <button
                        v-if="index < pipelineForm.steps.length - 1"
                        @click="moveStep(index, 1)"
                        class="text-gray-500 hover:text-white p-0.5"
                        title="Nach unten"
                      >
                        <ChevronDownIcon class="w-3.5 h-3.5" />
                      </button>
                      <button
                        v-if="pipelineForm.steps.length > 1"
                        @click="removeStep(index)"
                        class="text-red-400/60 hover:text-red-400 p-0.5 ml-1"
                        title="Entfernen"
                      >
                        <XMarkIcon class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-2">
                    <input
                      v-model="step.name"
                      type="text"
                      class="input w-full text-sm"
                      placeholder="Schrittname"
                    />
                    <input
                      v-model.number="step.timeout"
                      type="number"
                      class="input w-full text-sm"
                      placeholder="Timeout (Sek.)"
                      min="1"
                    />
                  </div>
                  <input
                    v-model="step.command"
                    type="text"
                    class="input w-full text-sm font-mono"
                    placeholder="Befehl, z.B. git pull origin main"
                  />
                </div>
              </div>
            </div>

            <!-- Toggles -->
            <div class="space-y-3 pt-2">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">Auto-Deploy bei Push</span>
                <button
                  @click="pipelineForm.auto_deploy = !pipelineForm.auto_deploy"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
                  :class="pipelineForm.auto_deploy ? 'bg-primary-600' : 'bg-gray-700'"
                >
                  <span
                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="pipelineForm.auto_deploy ? 'translate-x-6' : 'translate-x-1'"
                  />
                </button>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">Benachrichtigung bei Erfolg</span>
                <button
                  @click="pipelineForm.notify_on_success = !pipelineForm.notify_on_success"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
                  :class="pipelineForm.notify_on_success ? 'bg-primary-600' : 'bg-gray-700'"
                >
                  <span
                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="pipelineForm.notify_on_success ? 'translate-x-6' : 'translate-x-1'"
                  />
                </button>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">Benachrichtigung bei Fehler</span>
                <button
                  @click="pipelineForm.notify_on_failure = !pipelineForm.notify_on_failure"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
                  :class="pipelineForm.notify_on_failure ? 'bg-primary-600' : 'bg-gray-700'"
                >
                  <span
                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="pipelineForm.notify_on_failure ? 'translate-x-6' : 'translate-x-1'"
                  />
                </button>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 p-5 border-t border-white/[0.06]">
            <button @click="closePipelineModal" class="btn-secondary">Abbrechen</button>
            <button @click="handleSavePipeline" class="btn-primary">
              {{ editingPipeline ? 'Speichern' : 'Erstellen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Deploy Modal -->
    <Teleport to="body">
      <div
        v-if="showDeployModal && deployTargetPipeline"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeDeployModal"></div>
        <div class="relative card-glass w-full max-w-md">
          <div class="flex items-center justify-between p-5 border-b border-white/[0.06]">
            <h3 class="text-lg font-semibold text-white">Deployment starten</h3>
            <button @click="closeDeployModal" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <div class="p-5 space-y-4">
            <p class="text-sm text-gray-400">
              Pipeline: <span class="text-white font-medium">{{ deployTargetPipeline.name }}</span>
            </p>
            <div>
              <label class="block text-sm text-gray-400 mb-1">Commit Hash (optional)</label>
              <input
                v-model="deployForm.commit_hash"
                type="text"
                class="input w-full font-mono"
                placeholder="z.B. a1b2c3d"
              />
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-1">Commit Nachricht (optional)</label>
              <input
                v-model="deployForm.commit_message"
                type="text"
                class="input w-full"
                placeholder="z.B. Feature X implementiert"
              />
            </div>
          </div>
          <div class="flex items-center justify-end gap-3 p-5 border-t border-white/[0.06]">
            <button @click="closeDeployModal" class="btn-secondary">Abbrechen</button>
            <button @click="handleDeploy" class="btn-primary" :disabled="deploymentStore.deploying">
              <PlayIcon class="w-4 h-4 mr-1.5" />
              {{ deploymentStore.deploying ? 'Wird deployed...' : 'Jetzt deployen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Deployment Detail Modal -->
    <Teleport to="body">
      <div
        v-if="showDeploymentDetailModal && selectedDeployment"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeDeploymentDetailModal"></div>
        <div class="relative card-glass w-full max-w-2xl max-h-[80vh] flex flex-col">
          <div class="flex items-center justify-between p-5 border-b border-white/[0.06]">
            <div>
              <h3 class="text-lg font-semibold text-white">Deployment Details</h3>
              <p class="text-xs text-gray-500 mt-0.5 font-mono">{{ selectedDeployment.id }}</p>
            </div>
            <button @click="closeDeploymentDetailModal" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <div class="p-5 overflow-y-auto space-y-4">
            <!-- Summary -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Status</p>
                <span
                  class="text-xs px-2 py-0.5 rounded-full"
                  :class="getStatusBadge(selectedDeployment.status).class"
                >
                  {{ getStatusBadge(selectedDeployment.status).label }}
                </span>
              </div>
              <div>
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Dauer</p>
                <p class="text-sm text-white">{{ formatDuration(selectedDeployment.duration_ms) }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Gestartet</p>
                <p class="text-sm text-white">{{ formatTimestamp(selectedDeployment.started_at) }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Beendet</p>
                <p class="text-sm text-white">{{ formatTimestamp(selectedDeployment.finished_at) }}</p>
              </div>
              <div v-if="selectedDeployment.commit_hash">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Commit</p>
                <code class="text-sm text-primary-400 font-mono">{{ shortHash(selectedDeployment.commit_hash) }}</code>
              </div>
              <div v-if="selectedDeployment.commit_message">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Nachricht</p>
                <p class="text-sm text-gray-300">{{ selectedDeployment.commit_message }}</p>
              </div>
            </div>

            <!-- Error -->
            <div v-if="selectedDeployment.error_message" class="p-3 rounded-lg bg-red-500/10 border border-red-500/20">
              <div class="flex items-start gap-2">
                <ExclamationTriangleIcon class="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5" />
                <p class="text-sm text-red-400">{{ selectedDeployment.error_message }}</p>
              </div>
            </div>

            <!-- Rollback reference -->
            <div v-if="selectedDeployment.rollback_of" class="p-3 rounded-lg bg-purple-500/10 border border-purple-500/20">
              <div class="flex items-start gap-2">
                <ArrowUturnLeftIcon class="w-4 h-4 text-purple-400 flex-shrink-0 mt-0.5" />
                <p class="text-sm text-purple-400">
                  Rollback von Deployment <code class="font-mono">{{ shortHash(selectedDeployment.rollback_of) }}</code>
                </p>
              </div>
            </div>

            <!-- Steps Log -->
            <div v-if="selectedDeployment.steps_log && selectedDeployment.steps_log.length > 0">
              <h4 class="text-sm text-gray-400 font-medium mb-3">Schritte</h4>
              <div class="space-y-2">
                <div
                  v-for="(step, index) in selectedDeployment.steps_log"
                  :key="index"
                  class="p-3 rounded-lg bg-white/[0.03] border border-white/[0.06]"
                >
                  <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-2">
                      <span
                        class="w-2 h-2 rounded-full flex-shrink-0"
                        :class="step.status === 'success' ? 'bg-emerald-400' : step.status === 'failed' ? 'bg-red-400' : 'bg-gray-500'"
                      ></span>
                      <span class="text-sm text-white font-medium">{{ step.name }}</span>
                    </div>
                    <span class="text-xs text-gray-500">{{ formatDuration(step.duration_ms) }}</span>
                  </div>
                  <code class="text-xs text-gray-500 font-mono block mb-1">{{ step.command }}</code>
                  <pre v-if="step.output" class="text-xs text-gray-400 bg-black/30 rounded p-2 mt-1 overflow-x-auto whitespace-pre-wrap">{{ step.output }}</pre>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Rollback Confirmation Modal -->
    <Teleport to="body">
      <div
        v-if="showRollbackConfirm"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="cancelRollback"></div>
        <div class="relative card-glass w-full max-w-sm">
          <div class="p-5">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-10 h-10 rounded-full bg-purple-500/15 flex items-center justify-center">
                <ArrowUturnLeftIcon class="w-5 h-5 text-purple-400" />
              </div>
              <div>
                <h3 class="text-lg font-semibold text-white">Rollback bestaetigen</h3>
                <p class="text-xs text-gray-500">Dieser Vorgang kann nicht rueckgaengig gemacht werden.</p>
              </div>
            </div>
            <p class="text-sm text-gray-400 mb-4">
              Möchten Sie dieses Deployment wirklich zurückrollen? Es wird ein neues Rollback-Deployment erstellt.
            </p>
            <div class="flex items-center justify-end gap-3">
              <button @click="cancelRollback" class="btn-secondary">Abbrechen</button>
              <button @click="handleRollback" class="btn-primary bg-purple-600 hover:bg-purple-500" :disabled="deploymentStore.deploying">
                <ArrowUturnLeftIcon class="w-4 h-4 mr-1.5" />
                Rollback starten
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
