<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  GlobeAltIcon,
  PlusIcon,
  PencilSquareIcon,
  TrashIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  ArrowDownTrayIcon,
  ArrowUpTrayIcon,
  ArrowPathIcon,
  CloudArrowDownIcon,
  CloudArrowUpIcon,
  SignalIcon,
  XMarkIcon,
  DocumentTextIcon,
  ClipboardDocumentIcon,
  CloudIcon,
} from '@heroicons/vue/24/outline'
import { useDnsStore } from '@/modules/dns/stores/dnsStore'
import DnsPropagationChecker from '@/modules/dns/components/DnsPropagationChecker.vue'

const dnsStore = useDnsStore()

// Local state
const showDomainModal = ref(false)
const showRecordModal = ref(false)
const showImportModal = ref(false)
const showExportModal = ref(false)
const editingDomain = ref(null)
const editingRecord = ref(null)
const activeDomainId = ref(null)
const expandedDomains = ref(new Set())
const deleteConfirmId = ref(null)
const deleteRecordConfirmId = ref(null)

// Cloudflare import
const showCloudflareModal = ref(false)
const cfToken = ref('')
const cfZones = ref([])
const cfZonesLoading = ref(false)
const cfImporting = ref(false)
const cfSyncing = ref(null)

// Webtropia import
const showWebtropiaModal = ref(false)
const wtToken = ref('')
const wtZoneName = ref('')
const wtVerifying = ref(false)
const wtImporting = ref(false)

// Propagation checker
const showPropagation = ref(false)
const propagationRecord = ref(null)
const propagationDomainName = ref('')

// Domain form
const domainForm = ref({
  name: '',
  provider: 'manual',
  notes: '',
  api_token: '',
})

// Record form
const recordForm = ref({
  type: 'A',
  name: '@',
  value: '',
  ttl: 3600,
  priority: null,
  notes: '',
})

// Import/Export
const importContent = ref('')
const importDomainId = ref(null)
const exportContent = ref('')

const recordTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA']

const providers = [
  { value: 'manual', label: 'Manuell' },
  { value: 'cloudflare', label: 'Cloudflare' },
  { value: 'route53', label: 'AWS Route 53' },
  { value: 'digitalocean', label: 'DigitalOcean' },
  { value: 'hetzner', label: 'Hetzner' },
  { value: 'webtropia', label: 'Webtropia' },
  { value: 'namecheap', label: 'Namecheap' },
  { value: 'godaddy', label: 'GoDaddy' },
  { value: 'other', label: 'Sonstiger' },
]

const needsPriority = computed(() => {
  return ['MX', 'SRV'].includes(recordForm.value.type)
})

function getRecordTypeColor(type) {
  const colors = {
    A: 'bg-blue-500/15 text-blue-400',
    AAAA: 'bg-indigo-500/15 text-indigo-400',
    CNAME: 'bg-purple-500/15 text-purple-400',
    MX: 'bg-amber-500/15 text-amber-400',
    TXT: 'bg-emerald-500/15 text-emerald-400',
    NS: 'bg-cyan-500/15 text-cyan-400',
    SRV: 'bg-rose-500/15 text-rose-400',
    CAA: 'bg-orange-500/15 text-orange-400',
  }
  return colors[type] || 'bg-gray-500/15 text-gray-400'
}

function getProviderLabel(provider) {
  const found = providers.find((p) => p.value === provider)
  return found ? found.label : provider
}

// Domain CRUD
function openCreateDomainModal() {
  editingDomain.value = null
  domainForm.value = { name: '', provider: 'manual', notes: '', api_token: '' }
  showDomainModal.value = true
}

function openEditDomainModal(domain) {
  editingDomain.value = domain
  domainForm.value = {
    name: domain.name,
    provider: domain.provider || 'manual',
    notes: domain.notes || '',
    api_token: domain.provider_config?.api_token || '',
  }
  showDomainModal.value = true
}

function closeDomainModal() {
  showDomainModal.value = false
  editingDomain.value = null
}

async function saveDomain() {
  const payload = {
    name: domainForm.value.name,
    provider: domainForm.value.provider,
    notes: domainForm.value.notes,
  }

  // Include provider_config for providers that need API tokens
  if (['cloudflare', 'webtropia'].includes(domainForm.value.provider) && domainForm.value.api_token) {
    payload.provider_config = { api_token: domainForm.value.api_token }
  }

  if (editingDomain.value) {
    const result = await dnsStore.updateDomain(editingDomain.value.id, payload)
    if (result) closeDomainModal()
  } else {
    const result = await dnsStore.createDomain(payload)
    if (result) closeDomainModal()
  }
}

function confirmDeleteDomain(domainId) {
  deleteConfirmId.value = domainId
}

async function handleDeleteDomain(domainId) {
  await dnsStore.deleteDomain(domainId)
  deleteConfirmId.value = null
  expandedDomains.value.delete(domainId)
}

function cancelDeleteDomain() {
  deleteConfirmId.value = null
}

// Toggle expand domain to show records
async function toggleDomain(domainId) {
  if (expandedDomains.value.has(domainId)) {
    expandedDomains.value.delete(domainId)
  } else {
    expandedDomains.value.add(domainId)
    await dnsStore.fetchRecords(domainId)
  }
}

// Record CRUD
function openCreateRecordModal(domainId) {
  activeDomainId.value = domainId
  editingRecord.value = null
  recordForm.value = { type: 'A', name: '@', value: '', ttl: 3600, priority: null, notes: '' }
  showRecordModal.value = true
}

function openEditRecordModal(record, domainId) {
  activeDomainId.value = domainId
  editingRecord.value = record
  recordForm.value = {
    type: record.type,
    name: record.name,
    value: record.value,
    ttl: record.ttl,
    priority: record.priority,
    notes: record.notes || '',
  }
  showRecordModal.value = true
}

function closeRecordModal() {
  showRecordModal.value = false
  editingRecord.value = null
  activeDomainId.value = null
}

async function saveRecord() {
  if (editingRecord.value) {
    const result = await dnsStore.updateRecord(editingRecord.value.id, recordForm.value)
    if (result) closeRecordModal()
  } else {
    const result = await dnsStore.createRecord(activeDomainId.value, recordForm.value)
    if (result) closeRecordModal()
  }
}

function confirmDeleteRecord(recordId) {
  deleteRecordConfirmId.value = recordId
}

async function handleDeleteRecord(recordId, domainId) {
  await dnsStore.deleteRecord(recordId, domainId)
  deleteRecordConfirmId.value = null
}

function cancelDeleteRecord() {
  deleteRecordConfirmId.value = null
}

// Propagation
function openPropagationChecker(record, domainName) {
  propagationRecord.value = record
  propagationDomainName.value = domainName
  showPropagation.value = true
}

function closePropagationChecker() {
  showPropagation.value = false
  propagationRecord.value = null
  propagationDomainName.value = ''
}

// Zone Import/Export
async function openExportModal(domainId) {
  exportContent.value = ''
  const zone = await dnsStore.exportZone(domainId)
  if (zone) {
    exportContent.value = zone
    showExportModal.value = true
  }
}

function closeExportModal() {
  showExportModal.value = false
  exportContent.value = ''
}

function copyExportContent() {
  navigator.clipboard.writeText(exportContent.value)
}

function openImportModal(domainId) {
  importDomainId.value = domainId
  importContent.value = ''
  showImportModal.value = true
}

function closeImportModal() {
  showImportModal.value = false
  importContent.value = ''
  importDomainId.value = null
}

async function handleImport() {
  if (!importDomainId.value || !importContent.value.trim()) return
  const result = await dnsStore.importZone(importDomainId.value, importContent.value)
  if (result && result.imported_count > 0) {
    closeImportModal()
  }
}

// Cloudflare import
function openCloudflareModal() {
  cfToken.value = ''
  cfZones.value = []
  cfZonesLoading.value = false
  cfImporting.value = false
  showCloudflareModal.value = true
}

function closeCloudflareModal() {
  showCloudflareModal.value = false
  cfToken.value = ''
  cfZones.value = []
}

async function loadCloudflareZones() {
  if (!cfToken.value.trim()) return
  cfZonesLoading.value = true
  try {
    cfZones.value = await dnsStore.listCloudflareZones(cfToken.value.trim())
  } finally {
    cfZonesLoading.value = false
  }
}

async function importCfZone(zone) {
  cfImporting.value = true
  try {
    const result = await dnsStore.importCloudflareZone(cfToken.value.trim(), zone.id, zone.name)
    if (result) {
      closeCloudflareModal()
    }
  } finally {
    cfImporting.value = false
  }
}

// Webtropia import
function openWebtropiaModal() {
  wtToken.value = ''
  wtZoneName.value = ''
  wtVerifying.value = false
  wtImporting.value = false
  showWebtropiaModal.value = true
}

function closeWebtropiaModal() {
  showWebtropiaModal.value = false
  wtToken.value = ''
  wtZoneName.value = ''
}

async function verifyAndImportWebtropia() {
  if (!wtToken.value.trim() || !wtZoneName.value.trim()) return

  wtVerifying.value = true
  try {
    const valid = await dnsStore.verifyWebtropiaToken(wtToken.value.trim(), wtZoneName.value.trim())
    if (!valid) return

    wtImporting.value = true
    const result = await dnsStore.importWebtropiaZone(wtToken.value.trim(), wtZoneName.value.trim())
    if (result) {
      closeWebtropiaModal()
    }
  } finally {
    wtVerifying.value = false
    wtImporting.value = false
  }
}

async function handleSyncProvider(domainId) {
  cfSyncing.value = domainId
  try {
    await dnsStore.syncProvider(domainId)
  } finally {
    cfSyncing.value = null
  }
}

async function handlePushProvider(domainId) {
  cfSyncing.value = domainId
  try {
    await dnsStore.pushProvider(domainId)
  } finally {
    cfSyncing.value = null
  }
}

function getDomainRecords(domainId) {
  const domain = dnsStore.domains.find((d) => d.id === domainId)
  return domain?.records || []
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

onMounted(async () => {
  await dnsStore.fetchDomains()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">DNS Manager</h1>
        <p class="text-gray-400 mt-1">Domains und DNS-Records verwalten</p>
      </div>
      <div class="flex items-center gap-3">
        <button @click="openCloudflareModal" class="btn-secondary">
          <CloudIcon class="w-5 h-5 mr-2" />
          Von Cloudflare importieren
        </button>
        <button @click="openWebtropiaModal" class="btn-secondary">
          <GlobeAltIcon class="w-5 h-5 mr-2" />
          Von Webtropia importieren
        </button>
        <button @click="openCreateDomainModal" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Domain hinzufügen
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="dnsStore.loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Empty state -->
    <div
      v-else-if="dnsStore.domains.length === 0"
      class="card-glass p-12 text-center"
    >
      <GlobeAltIcon class="w-12 h-12 text-gray-600 mx-auto mb-4" />
      <h3 class="text-lg font-medium text-gray-300 mb-2">Keine Domains vorhanden.</h3>
      <p class="text-gray-500 mb-6">Fügen Sie Ihre erste Domain hinzu, um DNS-Records zu verwalten.</p>
      <button @click="openCreateDomainModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Domain hinzufügen
      </button>
    </div>

    <!-- Domain Cards -->
    <template v-else>
      <div v-for="domain in dnsStore.domains" :key="domain.id" class="card-glass overflow-hidden">
        <!-- Domain Header -->
        <div class="p-5">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3 mb-1">
                <GlobeAltIcon class="w-5 h-5 text-primary-400 flex-shrink-0" />
                <h3 class="text-lg font-semibold text-white truncate">{{ domain.name }}</h3>
                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-500/15 text-gray-400">
                  {{ getProviderLabel(domain.provider) }}
                </span>
              </div>
              <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                <span>{{ domain.record_count }} Records</span>
                <span>Erstellt: {{ formatTimestamp(domain.created_at) }}</span>
              </div>
              <p v-if="domain.notes" class="text-sm text-gray-400 mt-2">{{ domain.notes }}</p>
            </div>
          </div>

          <!-- Domain Actions -->
          <div class="flex items-center gap-2 pt-3 mt-3 border-t border-white/[0.06]">
            <button @click="toggleDomain(domain.id)" class="btn-ghost text-xs text-gray-400 hover:text-white">
              <component :is="expandedDomains.has(domain.id) ? ChevronUpIcon : ChevronDownIcon" class="w-4 h-4 mr-1" />
              Records {{ expandedDomains.has(domain.id) ? 'ausblenden' : 'anzeigen' }}
            </button>
            <button @click="openCreateRecordModal(domain.id)" class="btn-ghost text-xs text-gray-400 hover:text-white">
              <PlusIcon class="w-4 h-4 mr-1" />
              Record hinzufügen
            </button>
            <button @click="openExportModal(domain.id)" class="btn-ghost text-xs text-gray-400 hover:text-white">
              <ArrowDownTrayIcon class="w-4 h-4 mr-1" />
              Export
            </button>
            <button @click="openImportModal(domain.id)" class="btn-ghost text-xs text-gray-400 hover:text-white">
              <ArrowUpTrayIcon class="w-4 h-4 mr-1" />
              Import
            </button>
            <template v-if="['cloudflare', 'webtropia'].includes(domain.provider) && domain.external_zone_id">
              <button
                @click="handleSyncProvider(domain.id)"
                :disabled="cfSyncing === domain.id"
                class="btn-ghost text-xs text-cyan-400/60 hover:text-cyan-400"
              >
                <CloudArrowDownIcon class="w-4 h-4 mr-1" :class="{ 'animate-spin': cfSyncing === domain.id }" />
                Pull
              </button>
              <button
                @click="handlePushProvider(domain.id)"
                :disabled="cfSyncing === domain.id"
                class="btn-ghost text-xs text-amber-400/60 hover:text-amber-400"
              >
                <CloudArrowUpIcon class="w-4 h-4 mr-1" />
                Push
              </button>
            </template>
            <button @click="openEditDomainModal(domain)" class="btn-ghost text-xs text-gray-400 hover:text-white">
              <PencilSquareIcon class="w-4 h-4 mr-1" />
              Bearbeiten
            </button>
            <div class="flex-1"></div>

            <!-- Delete with confirmation -->
            <template v-if="deleteConfirmId === domain.id">
              <span class="text-xs text-red-400 mr-2">Wirklich löschen?</span>
              <button @click="handleDeleteDomain(domain.id)" class="btn-ghost text-xs text-red-400 hover:text-red-300">
                Ja
              </button>
              <button @click="cancelDeleteDomain" class="btn-ghost text-xs text-gray-400 hover:text-white">
                Nein
              </button>
            </template>
            <button
              v-else
              @click="confirmDeleteDomain(domain.id)"
              class="btn-ghost text-xs text-red-400/60 hover:text-red-400"
            >
              <TrashIcon class="w-4 h-4 mr-1" />
              Löschen
            </button>
          </div>
        </div>

        <!-- Records Panel (expandable) -->
        <div v-if="expandedDomains.has(domain.id)" class="border-t border-white/[0.06]">
          <!-- Records Loading -->
          <div v-if="dnsStore.recordsLoading" class="flex justify-center py-6">
            <div class="w-6 h-6 border-3 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
          </div>

          <!-- No Records -->
          <div
            v-else-if="getDomainRecords(domain.id).length === 0"
            class="p-6 text-center text-sm text-gray-500"
          >
            Keine DNS-Records vorhanden.
            <button @click="openCreateRecordModal(domain.id)" class="text-primary-400 hover:text-primary-300 ml-1">
              Jetzt hinzufügen
            </button>
          </div>

          <!-- Records Table -->
          <div v-else class="overflow-x-auto">
            <table class="table-glass w-full">
              <thead>
                <tr>
                  <th class="text-left text-xs text-gray-500 font-medium p-3">Typ</th>
                  <th class="text-left text-xs text-gray-500 font-medium p-3">Name</th>
                  <th class="text-left text-xs text-gray-500 font-medium p-3">Wert</th>
                  <th class="text-left text-xs text-gray-500 font-medium p-3">TTL</th>
                  <th class="text-left text-xs text-gray-500 font-medium p-3">Priorität</th>
                  <th class="text-right text-xs text-gray-500 font-medium p-3">Aktionen</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="record in getDomainRecords(domain.id)"
                  :key="record.id"
                  class="border-t border-white/[0.04]"
                >
                  <td class="p-3">
                    <span class="text-xs px-2 py-0.5 rounded-full font-mono font-medium" :class="getRecordTypeColor(record.type)">
                      {{ record.type }}
                    </span>
                  </td>
                  <td class="p-3 text-sm text-gray-300 font-mono">{{ record.name }}</td>
                  <td class="p-3 text-sm text-gray-400 font-mono max-w-[300px] truncate">{{ record.value }}</td>
                  <td class="p-3 text-sm text-gray-500">{{ record.ttl }}s</td>
                  <td class="p-3 text-sm text-gray-500">{{ record.priority !== null ? record.priority : '-' }}</td>
                  <td class="p-3 text-right">
                    <div class="flex items-center justify-end gap-1">
                      <button
                        @click="openPropagationChecker(record, domain.name)"
                        class="btn-ghost text-xs text-cyan-400/60 hover:text-cyan-400"
                        title="Propagation prüfen"
                      >
                        <SignalIcon class="w-4 h-4" />
                      </button>
                      <button
                        @click="openEditRecordModal(record, domain.id)"
                        class="btn-ghost text-xs text-gray-400 hover:text-white"
                        title="Bearbeiten"
                      >
                        <PencilSquareIcon class="w-4 h-4" />
                      </button>

                      <template v-if="deleteRecordConfirmId === record.id">
                        <button
                          @click="handleDeleteRecord(record.id, domain.id)"
                          class="btn-ghost text-xs text-red-400 hover:text-red-300"
                        >
                          Ja
                        </button>
                        <button
                          @click="cancelDeleteRecord"
                          class="btn-ghost text-xs text-gray-400 hover:text-white"
                        >
                          Nein
                        </button>
                      </template>
                      <button
                        v-else
                        @click="confirmDeleteRecord(record.id)"
                        class="btn-ghost text-xs text-red-400/60 hover:text-red-400"
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
      </div>
    </template>

    <!-- Domain Modal (Create/Edit) -->
    <Teleport to="body">
      <div
        v-if="showDomainModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeDomainModal"></div>
        <div class="relative card-glass w-full max-w-lg flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between p-5 border-b border-white/[0.06]">
            <h3 class="text-lg font-semibold text-white">
              {{ editingDomain ? 'Domain bearbeiten' : 'Domain hinzufügen' }}
            </h3>
            <button @click="closeDomainModal" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-5 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">Domain-Name</label>
              <input
                v-model="domainForm.name"
                type="text"
                class="input w-full"
                placeholder="beispiel.de"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">Provider</label>
              <select v-model="domainForm.provider" class="select w-full">
                <option v-for="p in providers" :key="p.value" :value="p.value">{{ p.label }}</option>
              </select>
            </div>

            <div v-if="['cloudflare', 'webtropia'].includes(domainForm.provider)">
              <label class="block text-sm font-medium text-gray-300 mb-1.5">
                {{ domainForm.provider === 'cloudflare' ? 'Cloudflare' : 'Webtropia' }} API Token
              </label>
              <input
                v-model="domainForm.api_token"
                type="password"
                class="input w-full"
                :placeholder="domainForm.provider === 'cloudflare' ? 'Cloudflare API Token...' : 'Webtropia/myLoc API Token...'"
              />
              <p class="text-xs text-gray-500 mt-1">
                {{ domainForm.provider === 'cloudflare' ? 'Wird für Sync mit Cloudflare benötigt.' : 'OAuth-Token aus dem myLoc ZKM-Panel. Wird für Sync benötigt.' }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">Notizen</label>
              <textarea
                v-model="domainForm.notes"
                class="input w-full"
                rows="3"
                placeholder="Optionale Notizen zur Domain..."
              ></textarea>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 p-5 border-t border-white/[0.06]">
            <button @click="closeDomainModal" class="btn-secondary">Abbrechen</button>
            <button @click="saveDomain" class="btn-primary" :disabled="!domainForm.name.trim()">
              {{ editingDomain ? 'Speichern' : 'Erstellen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Record Modal (Create/Edit) -->
    <Teleport to="body">
      <div
        v-if="showRecordModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeRecordModal"></div>
        <div class="relative card-glass w-full max-w-lg flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between p-5 border-b border-white/[0.06]">
            <h3 class="text-lg font-semibold text-white">
              {{ editingRecord ? 'DNS-Record bearbeiten' : 'DNS-Record hinzufügen' }}
            </h3>
            <button @click="closeRecordModal" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-5 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">Typ</label>
              <select v-model="recordForm.type" class="select w-full">
                <option v-for="t in recordTypes" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">Name</label>
              <input
                v-model="recordForm.name"
                type="text"
                class="input w-full"
                placeholder="@ oder Subdomain"
              />
              <p class="text-xs text-gray-500 mt-1">@ für die Root-Domain, oder z.B. www, mail, etc.</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">Wert</label>
              <textarea
                v-model="recordForm.value"
                class="input w-full"
                rows="2"
                :placeholder="recordForm.type === 'A' ? '192.168.1.1' :
                  recordForm.type === 'AAAA' ? '2001:db8::1' :
                  recordForm.type === 'CNAME' ? 'ziel.beispiel.de' :
                  recordForm.type === 'MX' ? 'mail.beispiel.de' :
                  recordForm.type === 'TXT' ? 'v=spf1 include:_spf.google.com ~all' :
                  recordForm.type === 'NS' ? 'ns1.beispiel.de' :
                  recordForm.type === 'SRV' ? 'ziel.beispiel.de' :
                  recordForm.type === 'CAA' ? '0 issue letsencrypt.org' :
                  'Wert eingeben'"
              ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">TTL (Sekunden)</label>
                <input
                  v-model.number="recordForm.ttl"
                  type="number"
                  class="input w-full"
                  min="60"
                  max="86400"
                  placeholder="3600"
                />
              </div>

              <div v-if="needsPriority">
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Priorität</label>
                <input
                  v-model.number="recordForm.priority"
                  type="number"
                  class="input w-full"
                  min="0"
                  max="65535"
                  placeholder="10"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">Notizen</label>
              <input
                v-model="recordForm.notes"
                type="text"
                class="input w-full"
                placeholder="Optionale Notizen..."
              />
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 p-5 border-t border-white/[0.06]">
            <button @click="closeRecordModal" class="btn-secondary">Abbrechen</button>
            <button @click="saveRecord" class="btn-primary" :disabled="!recordForm.value.trim()">
              {{ editingRecord ? 'Speichern' : 'Erstellen' }}
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
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeExportModal"></div>
        <div class="relative card-glass w-full max-w-2xl flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between p-5 border-b border-white/[0.06]">
            <h3 class="text-lg font-semibold text-white">Zone-Datei exportieren</h3>
            <button @click="closeExportModal" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-5">
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs text-gray-500 uppercase tracking-wider">BIND Zone-Datei</span>
              <button @click="copyExportContent" class="btn-ghost text-xs text-primary-400 hover:text-primary-300">
                <ClipboardDocumentIcon class="w-4 h-4 mr-1" />
                Kopieren
              </button>
            </div>
            <pre class="font-mono text-xs text-gray-300 bg-black/30 rounded-lg p-4 overflow-x-auto whitespace-pre-wrap max-h-[50vh] overflow-y-auto">{{ exportContent }}</pre>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 p-5 border-t border-white/[0.06]">
            <button @click="closeExportModal" class="btn-secondary">Schliessen</button>
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
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeImportModal"></div>
        <div class="relative card-glass w-full max-w-2xl flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between p-5 border-b border-white/[0.06]">
            <h3 class="text-lg font-semibold text-white">Zone-Datei importieren</h3>
            <button @click="closeImportModal" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-5 space-y-4">
            <p class="text-sm text-gray-400">
              Fügen Sie den Inhalt einer BIND Zone-Datei ein. Unterstützte Record-Typen: A, AAAA, CNAME, MX, TXT, NS, SRV, CAA.
            </p>
            <textarea
              v-model="importContent"
              class="input w-full font-mono text-xs"
              rows="12"
              placeholder="; Zone-Datei hier einfügen...
@ 3600 IN A 192.168.1.1
www 3600 IN CNAME beispiel.de.
@ 3600 IN MX 10 mail.beispiel.de."
            ></textarea>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 p-5 border-t border-white/[0.06]">
            <button @click="closeImportModal" class="btn-secondary">Abbrechen</button>
            <button @click="handleImport" class="btn-primary" :disabled="!importContent.trim()">
              <ArrowUpTrayIcon class="w-4 h-4 mr-2" />
              Importieren
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Cloudflare Import Modal -->
    <Teleport to="body">
      <div
        v-if="showCloudflareModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeCloudflareModal"></div>
        <div class="relative card-glass w-full max-w-2xl flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between p-5 border-b border-white/[0.06]">
            <h3 class="text-lg font-semibold text-white">
              <CloudIcon class="w-5 h-5 inline-block mr-2 text-orange-400" />
              Von Cloudflare importieren
            </h3>
            <button @click="closeCloudflareModal" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-5 space-y-4">
            <!-- Token Input -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">Cloudflare API Token</label>
              <div class="flex gap-2">
                <input
                  v-model="cfToken"
                  type="password"
                  class="input flex-1"
                  placeholder="Cloudflare API Token eingeben..."
                />
                <button
                  @click="loadCloudflareZones"
                  :disabled="!cfToken.trim() || cfZonesLoading"
                  class="btn-primary"
                >
                  <ArrowPathIcon v-if="cfZonesLoading" class="w-4 h-4 mr-1 animate-spin" />
                  Zonen laden
                </button>
              </div>
              <p class="text-xs text-gray-500 mt-1">
                Erstelle einen API Token unter Cloudflare Dashboard &rarr; My Profile &rarr; API Tokens
              </p>
            </div>

            <!-- Zones List -->
            <div v-if="cfZones.length > 0" class="space-y-2">
              <h4 class="text-sm font-medium text-gray-300">Verfügbare Zonen ({{ cfZones.length }})</h4>
              <div class="max-h-[40vh] overflow-y-auto space-y-1">
                <div
                  v-for="zone in cfZones"
                  :key="zone.id"
                  class="flex items-center justify-between p-3 rounded-lg bg-white/[0.03] hover:bg-white/[0.06] transition-colors"
                >
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                      <GlobeAltIcon class="w-4 h-4 text-primary-400 flex-shrink-0" />
                      <span class="text-sm font-medium text-white">{{ zone.name }}</span>
                      <span class="text-xs px-1.5 py-0.5 rounded-full" :class="zone.status === 'active' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-gray-500/15 text-gray-400'">
                        {{ zone.status }}
                      </span>
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">{{ zone.plan }} &middot; NS: {{ zone.name_servers?.join(', ') || '-' }}</div>
                  </div>
                  <button
                    @click="importCfZone(zone)"
                    :disabled="cfImporting"
                    class="btn-primary text-xs ml-3"
                  >
                    <ArrowDownTrayIcon class="w-4 h-4 mr-1" />
                    Importieren
                  </button>
                </div>
              </div>
            </div>

            <!-- No zones -->
            <div v-else-if="!cfZonesLoading && cfToken.trim() && cfZones.length === 0" class="text-sm text-gray-500 text-center py-4">
              Keine Zonen gefunden. Bitte Token prüfen.
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 p-5 border-t border-white/[0.06]">
            <button @click="closeCloudflareModal" class="btn-secondary">Schliessen</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Webtropia Import Modal -->
    <Teleport to="body">
      <div
        v-if="showWebtropiaModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeWebtropiaModal"></div>
        <div class="relative card-glass w-full max-w-lg flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between p-5 border-b border-white/[0.06]">
            <h3 class="text-lg font-semibold text-white">
              <GlobeAltIcon class="w-5 h-5 inline-block mr-2 text-blue-400" />
              Von Webtropia importieren
            </h3>
            <button @click="closeWebtropiaModal" class="btn-icon-sm">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-5 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">Webtropia API Token</label>
              <input
                v-model="wtToken"
                type="password"
                class="input w-full"
                placeholder="Webtropia/myLoc API Token eingeben..."
              />
              <p class="text-xs text-gray-500 mt-1">
                OAuth-Token aus dem myLoc ZKM-Panel
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1.5">Domain-Name (Zone)</label>
              <input
                v-model="wtZoneName"
                type="text"
                class="input w-full"
                placeholder="beispiel.de"
              />
              <p class="text-xs text-gray-500 mt-1">
                Der exakte Domain-Name, wie er bei Webtropia konfiguriert ist
              </p>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 p-5 border-t border-white/[0.06]">
            <button @click="closeWebtropiaModal" class="btn-secondary">Abbrechen</button>
            <button
              @click="verifyAndImportWebtropia"
              :disabled="!wtToken.trim() || !wtZoneName.trim() || wtVerifying || wtImporting"
              class="btn-primary"
            >
              <ArrowPathIcon v-if="wtVerifying || wtImporting" class="w-4 h-4 mr-1 animate-spin" />
              <ArrowDownTrayIcon v-else class="w-4 h-4 mr-1" />
              {{ wtVerifying ? 'Pruefen...' : wtImporting ? 'Importieren...' : 'Importieren' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Propagation Checker -->
    <DnsPropagationChecker
      :show="showPropagation"
      :record="propagationRecord"
      :domain-name="propagationDomainName"
      @close="closePropagationChecker"
    />
  </div>
</template>
