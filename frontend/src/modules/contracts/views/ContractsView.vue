<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import {
  PlusIcon,
  MagnifyingGlassIcon,
  PencilIcon,
  TrashIcon,
  ArrowDownTrayIcon,
  DocumentDuplicateIcon,
  EyeIcon,
  PencilSquareIcon,
} from '@heroicons/vue/24/outline'
import { useContracts } from '../composables/useContracts'
import { useContractHtml } from '../composables/useContractHtml'
import ContractDetailPanel from '../components/ContractDetailPanel.vue'
import ContractWizard from '../components/ContractWizard.vue'
import ContractSignatureModal from '../components/ContractSignatureModal.vue'
import ContractPdfPreview from '../components/ContractPdfPreview.vue'
import ContractShareModal from '../components/ContractShareModal.vue'

const uiStore = useUiStore()
const { downloadPdf } = useContractHtml()

const {
  contracts, templates, stats, isLoading,
  statusFilter, typeFilter, searchQuery,
  statusOptions, contractTypeLabels, filteredContracts,
  loadData, loadTemplates, saveContract, deleteContract,
  updateContractStatus, duplicateContract, signContract,
  linkInvoice, unlinkInvoice, getHistory,
  formatCurrency, formatDate, getStatusInfo,
} = useContracts()

// UI state
const showDetailPanel = ref(false)
const selectedContract = ref(null)
const showWizard = ref(false)
const showSignatureModal = ref(false)
const signatureContractId = ref(null)
const signatureParty = ref('a')
const showPdfPreview = ref(false)
const previewContract = ref(null)
const showShareModal = ref(false)
const shareContract = ref(null)
const clients = ref([])
const senderSettings = ref({})

onMounted(async () => {
  await Promise.all([loadData(), loadClients(), loadSettings()])
  window.addEventListener('keydown', handleGlobalKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
})

function handleGlobalKeydown(e) {
  if (e.key === 'Escape') {
    if (showDetailPanel.value) showDetailPanel.value = false
  }
}

async function loadClients() {
  try {
    const res = await api.get('/api/v1/clients')
    clients.value = res.data.data?.items || []
  } catch { /* ignore */ }
}

async function loadSettings() {
  try {
    const res = await api.get('/api/v1/settings/user')
    const s = res.data.data ?? {}
    senderSettings.value = {
      sender_name: s.invoice_sender_name ?? '',
      sender_company: s.invoice_company ?? '',
      sender_address: s.invoice_address ?? '',
      sender_email: s.invoice_email ?? '',
      sender_phone: s.invoice_phone ?? '',
      sender_vat_id: s.invoice_vat_id ?? '',
    }
  } catch { /* ignore */ }
}

async function openContractDetail(contract) {
  try {
    const response = await api.get(`/api/v1/contracts/${contract.id}`)
    selectedContract.value = response.data.data
    showDetailPanel.value = true
  } catch {
    uiStore.showError('Fehler beim Laden des Vertrags')
  }
}

async function handleWizardSave(formData) {
  const result = await saveContract(formData)
  if (result) {
    showWizard.value = false
    openContractDetail(result)
  }
}

async function handleStatusChange(contract, status) {
  await updateContractStatus(contract, status)
  if (selectedContract.value?.id === contract.id) {
    selectedContract.value = { ...selectedContract.value, status }
  }
}

async function handleDuplicate(contract) {
  await duplicateContract(contract)
}

async function handleDelete(contract) {
  const deleted = await deleteContract(contract)
  if (deleted && selectedContract.value?.id === contract.id) {
    showDetailPanel.value = false
    selectedContract.value = null
  }
}

async function handleDownloadPdf(contract) {
  try {
    const res = await api.get(`/api/v1/contracts/${contract.id}`)
    await downloadPdf(res.data.data)
  } catch {
    uiStore.showError('Fehler beim PDF-Download')
  }
}

function openSignature(contractId, party) {
  signatureContractId.value = contractId
  signatureParty.value = party
  showSignatureModal.value = true
}

async function handleSignatureSave(signatureData) {
  const success = await signContract(signatureContractId.value, signatureParty.value, signatureData)
  if (success) {
    showSignatureModal.value = false
    if (selectedContract.value?.id === signatureContractId.value) {
      const res = await api.get(`/api/v1/contracts/${signatureContractId.value}`)
      selectedContract.value = res.data.data
    }
    await loadData()
  }
}

function openPdfPreview(contract) {
  previewContract.value = contract
  showPdfPreview.value = true
}

function openShare(contract) {
  shareContract.value = contract
  showShareModal.value = true
}

const typeColors = {
  license: 'bg-blue-500/20 text-blue-300',
  development: 'bg-purple-500/20 text-purple-300',
  saas: 'bg-cyan-500/20 text-cyan-300',
  maintenance: 'bg-amber-500/20 text-amber-300',
  nda: 'bg-emerald-500/20 text-emerald-300',
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">Verträge</h1>
        <p class="text-sm text-gray-400 mt-0.5">Verträge erstellen, verwalten und unterschreiben</p>
      </div>
      <button
        @click="showWizard = true"
        class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-600 text-white text-sm font-semibold hover:bg-primary-500 transition-colors"
      >
        <PlusIcon class="w-4 h-4" />
        Neuer Vertrag
      </button>
    </div>

    <!-- Stats Cards -->
    <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-4 hover:border-white/[0.08] transition-colors">
        <div class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Gesamt</div>
        <div class="text-2xl font-bold text-white mt-1">{{ stats.total_contracts || 0 }}</div>
      </div>
      <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-4 hover:border-white/[0.08] transition-colors">
        <div class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Aktiv</div>
        <div class="text-2xl font-bold text-green-400 mt-1">{{ stats.active || 0 }}</div>
      </div>
      <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-4 hover:border-white/[0.08] transition-colors">
        <div class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Entwürfe</div>
        <div class="text-2xl font-bold text-gray-300 mt-1">{{ stats.draft || 0 }}</div>
      </div>
      <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-4 hover:border-white/[0.08] transition-colors">
        <div class="text-xs text-gray-400 uppercase tracking-wider font-semibold">Aktiver Vertragswert</div>
        <div class="text-2xl font-bold text-primary-400 mt-1">{{ formatCurrency(stats.total_active_value) }}</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-3">
      <div class="relative flex-1 max-w-xs">
        <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Suchen..."
          class="input pl-9 text-sm"
        />
      </div>
      <select v-model="statusFilter" class="input w-40 text-sm">
        <option value="">Alle Status</option>
        <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
      <select v-model="typeFilter" class="input w-40 text-sm">
        <option value="">Alle Typen</option>
        <option v-for="(label, key) in contractTypeLabels" :key="key" :value="key">{{ label }}</option>
      </select>
    </div>

    <!-- Contracts Table -->
    <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl overflow-hidden">
      <div v-if="isLoading" class="p-12 text-center text-gray-500">Lade Verträge...</div>
      <div v-else-if="filteredContracts.length === 0" class="p-12 text-center text-gray-500">
        <p class="text-lg font-medium">Keine Verträge gefunden</p>
        <p class="text-sm mt-1">Erstellen Sie Ihren ersten Vertrag mit dem Button oben.</p>
      </div>
      <table v-else class="w-full">
        <thead>
          <tr class="text-left text-xs text-gray-400 uppercase tracking-wider border-b border-white/[0.06]">
            <th class="px-4 py-3 font-semibold">Vertrag</th>
            <th class="px-4 py-3 font-semibold">Typ</th>
            <th class="px-4 py-3 font-semibold">Kunde</th>
            <th class="px-4 py-3 font-semibold">Status</th>
            <th class="px-4 py-3 font-semibold text-right">Wert</th>
            <th class="px-4 py-3 font-semibold">Laufzeit</th>
            <th class="px-4 py-3 font-semibold text-right">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="contract in filteredContracts"
            :key="contract.id"
            class="border-b border-white/[0.04] hover:bg-white/[0.02] transition-colors cursor-pointer"
            @click="openContractDetail(contract)"
          >
            <td class="px-4 py-3">
              <div class="font-semibold text-white text-sm">{{ contract.contract_number }}</div>
              <div class="text-xs text-gray-400 truncate max-w-[200px]">{{ contract.title }}</div>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium" :class="typeColors[contract.contract_type] || 'bg-gray-500/20 text-gray-300'">
                {{ contractTypeLabels[contract.contract_type] || contract.contract_type }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-300">
              {{ contract.party_b_company || contract.party_b_name || contract.client_name || '-' }}
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                :class="getStatusInfo(contract.status).color + '/20 ' + getStatusInfo(contract.status).textColor">
                {{ getStatusInfo(contract.status).label }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-right text-gray-300">
              {{ formatCurrency(contract.total_value, contract.currency) }}
            </td>
            <td class="px-4 py-3 text-sm text-gray-400">
              {{ formatDate(contract.start_date) }}
              <span v-if="contract.end_date"> — {{ formatDate(contract.end_date) }}</span>
              <span v-else class="text-gray-500 text-xs"> (unbefr.)</span>
            </td>
            <td class="px-4 py-3 text-right" @click.stop>
              <div class="flex items-center justify-end gap-1">
                <button @click="openPdfPreview(contract)" class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.06]" title="Vorschau">
                  <EyeIcon class="w-4 h-4" />
                </button>
                <button @click="handleDownloadPdf(contract)" class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.06]" title="PDF">
                  <ArrowDownTrayIcon class="w-4 h-4" />
                </button>
                <button @click="handleDuplicate(contract)" class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/[0.06]" title="Duplizieren">
                  <DocumentDuplicateIcon class="w-4 h-4" />
                </button>
                <button @click="handleDelete(contract)" class="p-1.5 rounded-lg text-gray-400 hover:text-red-400 hover:bg-red-500/10" title="Löschen">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Wizard -->
    <ContractWizard
      :show="showWizard"
      :clients="clients"
      :templates="templates"
      :sender-settings="senderSettings"
      @close="showWizard = false"
      @save="handleWizardSave"
    />

    <!-- Detail Panel -->
    <ContractDetailPanel
      :show="showDetailPanel"
      :contract="selectedContract"
      :clients="clients"
      @close="showDetailPanel = false; selectedContract = null"
      @status-change="handleStatusChange"
      @sign="openSignature"
      @download-pdf="handleDownloadPdf"
      @preview="openPdfPreview"
      @delete="handleDelete"
      @duplicate="handleDuplicate"
      @link-invoice="linkInvoice"
      @unlink-invoice="unlinkInvoice"
      @reload="async (c) => { const res = await api.get(`/api/v1/contracts/${c.id}`); selectedContract = res.data.data; await loadData() }"
      @share="openShare"
    />

    <!-- Signature Modal -->
    <ContractSignatureModal
      :show="showSignatureModal"
      @close="showSignatureModal = false"
      @save="handleSignatureSave"
    />

    <!-- PDF Preview -->
    <ContractPdfPreview
      :show="showPdfPreview"
      :contract="previewContract"
      @close="showPdfPreview = false"
      @download="handleDownloadPdf"
    />

    <!-- Share Modal -->
    <ContractShareModal
      :show="showShareModal"
      :contract="shareContract"
      @close="showShareModal = false"
      @updated="loadData"
    />
  </div>
</template>
