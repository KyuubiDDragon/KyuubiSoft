<script setup>
import { ref, computed, onMounted } from 'vue'
import { useStatusPageStore } from '../stores/statusPageStore'
import { useUiStore } from '@/stores/ui'
import {
  SignalIcon,
  PlusIcon,
  TrashIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  ArrowTopRightOnSquareIcon,
  XMarkIcon,
  ArrowsUpDownIcon,
} from '@heroicons/vue/24/outline'

const store = useStatusPageStore()
const uiStore = useUiStore()

const configForm = ref({
  title: '',
  description: '',
  is_public: true,
})

const showAddMonitorModal = ref(false)
const addMonitorForm = ref({
  monitor_id: '',
  display_name: '',
  group_name: '',
})

const showIncidentModal = ref(false)
const incidentForm = ref({
  title: '',
  status: 'investigating',
  message: '',
  impact: 'minor',
})

const showUpdateModal = ref(false)
const updateForm = ref({
  incident_id: '',
  status: 'investigating',
  message: '',
})

const expandedIncidents = ref(new Set())

const statusOptions = [
  { value: 'investigating', label: 'Untersuchung', color: 'bg-yellow-500' },
  { value: 'identified', label: 'Identifiziert', color: 'bg-orange-500' },
  { value: 'monitoring', label: 'Beobachtung', color: 'bg-blue-500' },
  { value: 'resolved', label: 'Behoben', color: 'bg-green-500' },
]

const impactOptions = [
  { value: 'none', label: 'Keine' },
  { value: 'minor', label: 'Gering' },
  { value: 'major', label: 'Erheblich' },
  { value: 'critical', label: 'Kritisch' },
]

function getStatusBadge(status) {
  const opt = statusOptions.find(s => s.value === status)
  return opt || { label: status, color: 'bg-gray-500' }
}

function getImpactBadge(impact) {
  const colors = { none: 'bg-gray-500', minor: 'bg-yellow-500', major: 'bg-orange-500', critical: 'bg-red-500' }
  const labels = { none: 'Keine', minor: 'Gering', major: 'Erheblich', critical: 'Kritisch' }
  return { color: colors[impact] || 'bg-gray-500', label: labels[impact] || impact }
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('de-DE')
}

function toggleIncident(id) {
  if (expandedIncidents.value.has(id)) {
    expandedIncidents.value.delete(id)
  } else {
    expandedIncidents.value.add(id)
  }
}

onMounted(async () => {
  try {
    await Promise.all([
      store.fetchConfig(),
      store.fetchMonitors(),
      store.fetchIncidents(),
    ])
    if (store.config) {
      configForm.value = {
        title: store.config.title || '',
        description: store.config.description || '',
        is_public: !!store.config.is_public,
      }
    }
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Status Page Konfiguration')
  }
})

async function saveConfig() {
  try {
    await store.updateConfig(configForm.value)
    uiStore.showSuccess('Konfiguration gespeichert')
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function addMonitor() {
  if (!addMonitorForm.value.monitor_id) {
    uiStore.showError('Bitte einen Monitor auswaehlen')
    return
  }
  try {
    await store.addMonitor({
      monitor_id: addMonitorForm.value.monitor_id,
      display_name: addMonitorForm.value.display_name || undefined,
      group_name: addMonitorForm.value.group_name || undefined,
    })
    showAddMonitorModal.value = false
    addMonitorForm.value = { monitor_id: '', display_name: '', group_name: '' }
    uiStore.showSuccess('Monitor hinzugefuegt')
  } catch (error) {
    uiStore.showError('Fehler beim Hinzufuegen')
  }
}

async function removeMonitor(id) {
  try {
    await store.removeMonitor(id)
    uiStore.showSuccess('Monitor entfernt')
  } catch (error) {
    uiStore.showError('Fehler beim Entfernen')
  }
}

async function createIncident() {
  if (!incidentForm.value.title.trim()) {
    uiStore.showError('Titel ist erforderlich')
    return
  }
  try {
    await store.createIncident(incidentForm.value)
    showIncidentModal.value = false
    incidentForm.value = { title: '', status: 'investigating', message: '', impact: 'minor' }
    uiStore.showSuccess('Vorfall erstellt')
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen')
  }
}

function openUpdateModal(incident) {
  updateForm.value = {
    incident_id: incident.id,
    status: incident.status,
    message: '',
  }
  showUpdateModal.value = true
}

async function addUpdate() {
  if (!updateForm.value.message.trim()) {
    uiStore.showError('Nachricht ist erforderlich')
    return
  }
  try {
    await store.addIncidentUpdate(updateForm.value.incident_id, {
      status: updateForm.value.status,
      message: updateForm.value.message,
    })
    showUpdateModal.value = false
    uiStore.showSuccess('Status aktualisiert')
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">Status Page</h1>
        <p class="text-gray-400 mt-1">Verwalte deine oeffentliche Statusseite</p>
      </div>
      <div class="flex gap-2">
        <router-link to="/status" target="_blank" class="btn-secondary">
          <ArrowTopRightOnSquareIcon class="w-5 h-5 mr-2" />
          Oeffentliche Seite
        </router-link>
      </div>
    </div>

    <!-- Konfiguration -->
    <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-6">
      <h2 class="text-lg font-semibold text-white mb-4">Konfiguration</h2>
      <div class="space-y-4">
        <div>
          <label class="label">Titel</label>
          <input v-model="configForm.title" type="text" class="input" placeholder="System Status" />
        </div>
        <div>
          <label class="label">Beschreibung</label>
          <textarea v-model="configForm.description" class="input" rows="3" placeholder="Optionale Beschreibung..."></textarea>
        </div>
        <div class="flex items-center gap-3">
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="configForm.is_public" type="checkbox" class="checkbox" />
            <span class="text-gray-300">Oeffentlich sichtbar</span>
          </label>
        </div>
        <div>
          <button @click="saveConfig" class="btn-primary">Speichern</button>
        </div>
      </div>
    </div>

    <!-- Monitore -->
    <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-white">Monitore</h2>
        <button @click="showAddMonitorModal = true" class="btn-primary text-sm">
          <PlusIcon class="w-4 h-4 mr-1" />
          Monitor hinzufuegen
        </button>
      </div>

      <div v-if="store.monitors.assigned.length === 0" class="text-center py-8">
        <SignalIcon class="w-12 h-12 mx-auto text-gray-600 mb-3" />
        <p class="text-gray-400">Noch keine Monitore zugewiesen</p>
        <p class="text-gray-500 text-sm mt-1">Fuege Monitore hinzu, die auf der Statusseite angezeigt werden sollen</p>
      </div>

      <div v-else class="space-y-2">
        <div
          v-for="monitor in store.monitors.assigned"
          :key="monitor.id"
          class="flex items-center gap-3 p-3 bg-white/[0.03] border border-white/[0.06] rounded-lg group"
        >
          <ArrowsUpDownIcon class="w-4 h-4 text-gray-600 cursor-grab" />
          <div
            class="w-2.5 h-2.5 rounded-full"
            :class="{
              'bg-green-500': monitor.current_status === 'up',
              'bg-red-500': monitor.current_status === 'down',
              'bg-gray-500': !monitor.current_status || monitor.current_status === 'pending',
            }"
          ></div>
          <div class="flex-1 min-w-0">
            <p class="text-white text-sm font-medium">{{ monitor.display_name || monitor.monitor_name }}</p>
            <p class="text-gray-500 text-xs">{{ monitor.monitor_type }} &middot; {{ monitor.group_name || 'Keine Gruppe' }}</p>
          </div>
          <button
            @click="removeMonitor(monitor.id)"
            class="p-1.5 text-gray-400 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-all"
          >
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Vorfaelle -->
    <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-white">Vorfaelle</h2>
        <button @click="showIncidentModal = true" class="btn-primary text-sm">
          <PlusIcon class="w-4 h-4 mr-1" />
          Vorfall erstellen
        </button>
      </div>

      <div v-if="store.incidents.items.length === 0" class="text-center py-8">
        <p class="text-gray-400">Keine Vorfaelle</p>
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="incident in store.incidents.items"
          :key="incident.id"
          class="bg-white/[0.03] border border-white/[0.06] rounded-lg overflow-hidden"
        >
          <div
            class="flex items-center gap-3 p-4 cursor-pointer hover:bg-white/[0.02] transition-colors"
            @click="toggleIncident(incident.id)"
          >
            <component
              :is="expandedIncidents.has(incident.id) ? ChevronDownIcon : ChevronRightIcon"
              class="w-4 h-4 text-gray-400"
            />
            <span
              class="px-2 py-0.5 text-xs font-medium rounded text-white"
              :class="getStatusBadge(incident.status).color"
            >
              {{ getStatusBadge(incident.status).label }}
            </span>
            <span
              class="px-2 py-0.5 text-xs font-medium rounded text-white"
              :class="getImpactBadge(incident.impact).color"
            >
              {{ getImpactBadge(incident.impact).label }}
            </span>
            <span class="flex-1 text-white text-sm font-medium">{{ incident.title }}</span>
            <span class="text-xs text-gray-500">{{ formatDate(incident.started_at) }}</span>
            <button
              @click.stop="openUpdateModal(incident)"
              class="px-2 py-1 text-xs bg-white/[0.06] hover:bg-white/[0.1] text-gray-300 rounded transition-colors"
            >
              Status aktualisieren
            </button>
          </div>

          <!-- Timeline -->
          <div v-if="expandedIncidents.has(incident.id)" class="border-t border-white/[0.06] p-4">
            <p v-if="incident.message" class="text-sm text-gray-300 mb-4">{{ incident.message }}</p>
            <div v-if="incident.updates && incident.updates.length > 0" class="space-y-3">
              <div
                v-for="update in incident.updates"
                :key="update.id"
                class="flex gap-3 text-sm"
              >
                <div class="flex flex-col items-center">
                  <div
                    class="w-2.5 h-2.5 rounded-full mt-1.5"
                    :class="getStatusBadge(update.status).color"
                  ></div>
                  <div class="w-px flex-1 bg-white/[0.06] mt-1"></div>
                </div>
                <div class="pb-4">
                  <div class="flex items-center gap-2">
                    <span class="font-medium text-white">{{ getStatusBadge(update.status).label }}</span>
                    <span class="text-gray-500 text-xs">{{ formatDate(update.created_at) }}</span>
                  </div>
                  <p class="text-gray-400 mt-1">{{ update.message }}</p>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-gray-500">Keine Updates vorhanden</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Monitor Modal -->
    <Teleport to="body">
      <div
        v-if="showAddMonitorModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
      >
        <div class="modal w-full max-w-md">
          <div class="p-4 border-b border-white/[0.06] flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">Monitor hinzufuegen</h2>
            <button @click="showAddMonitorModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <form @submit.prevent="addMonitor" class="p-4 space-y-4">
            <div>
              <label class="label">Monitor *</label>
              <select v-model="addMonitorForm.monitor_id" class="input">
                <option value="">Monitor auswaehlen...</option>
                <option v-for="m in store.monitors.available" :key="m.id" :value="m.id">
                  {{ m.name }} ({{ m.type }})
                </option>
              </select>
            </div>
            <div>
              <label class="label">Anzeigename (optional)</label>
              <input v-model="addMonitorForm.display_name" type="text" class="input" placeholder="Benutzerdefinierter Name" />
            </div>
            <div>
              <label class="label">Gruppenname (optional)</label>
              <input v-model="addMonitorForm.group_name" type="text" class="input" placeholder="z.B. Webserver, Datenbanken" />
            </div>
            <div class="flex gap-3 pt-2">
              <button type="button" @click="showAddMonitorModal = false" class="btn-secondary flex-1">Abbrechen</button>
              <button type="submit" class="btn-primary flex-1">Hinzufuegen</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Create Incident Modal -->
    <Teleport to="body">
      <div
        v-if="showIncidentModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
      >
        <div class="modal w-full max-w-md">
          <div class="p-4 border-b border-white/[0.06] flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">Vorfall erstellen</h2>
            <button @click="showIncidentModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <form @submit.prevent="createIncident" class="p-4 space-y-4">
            <div>
              <label class="label">Titel *</label>
              <input v-model="incidentForm.title" type="text" class="input" placeholder="Kurze Beschreibung des Vorfalls" required />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">Status</label>
                <select v-model="incidentForm.status" class="input">
                  <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <label class="label">Auswirkung</label>
                <select v-model="incidentForm.impact" class="input">
                  <option v-for="opt in impactOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </div>
            </div>
            <div>
              <label class="label">Nachricht</label>
              <textarea v-model="incidentForm.message" class="input" rows="3" placeholder="Details zum Vorfall..."></textarea>
            </div>
            <div class="flex gap-3 pt-2">
              <button type="button" @click="showIncidentModal = false" class="btn-secondary flex-1">Abbrechen</button>
              <button type="submit" class="btn-primary flex-1">Erstellen</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Update Incident Modal -->
    <Teleport to="body">
      <div
        v-if="showUpdateModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50 p-4"
      >
        <div class="modal w-full max-w-md">
          <div class="p-4 border-b border-white/[0.06] flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">Status aktualisieren</h2>
            <button @click="showUpdateModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <form @submit.prevent="addUpdate" class="p-4 space-y-4">
            <div>
              <label class="label">Status</label>
              <select v-model="updateForm.status" class="input">
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="label">Nachricht *</label>
              <textarea v-model="updateForm.message" class="input" rows="3" placeholder="Update-Nachricht..." required></textarea>
            </div>
            <div class="flex gap-3 pt-2">
              <button type="button" @click="showUpdateModal = false" class="btn-secondary flex-1">Abbrechen</button>
              <button type="submit" class="btn-primary flex-1">Aktualisieren</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>
