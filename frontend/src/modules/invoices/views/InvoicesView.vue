<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import {
  PlusIcon,
  DocumentTextIcon,
  UserIcon,
  CurrencyEuroIcon,
  CheckIcon,
  ClockIcon,
  MagnifyingGlassIcon,
  XMarkIcon,
  PencilIcon,
  TrashIcon,
  PaperAirplaneIcon,
  ArrowDownTrayIcon,
  DocumentDuplicateIcon,
  EyeIcon,
  Bars2Icon,
} from '@heroicons/vue/24/outline'
import { RouterLink } from 'vue-router'
import { useInvoices } from '../composables/useInvoices'
import { useInvoiceHtml } from '../composables/useInvoiceHtml'
import InvoiceDetailPanel from '../components/InvoiceDetailPanel.vue'
import InvoiceCreateModal from '../components/InvoiceCreateModal.vue'
import ClientModal from '../components/ClientModal.vue'

const uiStore = useUiStore()
const toast = useToast()
const { downloadPdf } = useInvoiceHtml()

const {
  invoices, clients, stats, isLoading,
  statusFilter, searchQuery, kleinunternehmerMode, invoiceSenderSettings, pdfGenerating,
  statusOptions, filteredInvoices,
  loadData, loadSettings, saveInvoice, deleteInvoice, updateInvoiceStatus,
  duplicateInvoice, toggleKleinunternehmer, saveClient, deleteClient,
  formatCurrency, formatDate, getStatusInfo,
} = useInvoices()

// UI state
const activeTab = ref('invoices')
const showDetailPanel = ref(false)
const selectedInvoice = ref(null)
const showCreateModal = ref(false)
const editingInvoice = ref(null)
const showClientModal = ref(false)
const editingClient = ref(null)
const showTimeEntriesModal = ref(false)

// Time entries modal
const timeEntriesForm = ref({ client_id: null, project_id: null, hourly_rate: '' })
const projects = ref([])

onMounted(async () => {
  await Promise.all([loadData(), loadSettings()])

  // Keyboard shortcut: Escape closes panel
  window.addEventListener('keydown', handleGlobalKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
})

function handleGlobalKeydown(e) {
  if (e.key === 'Escape' && showDetailPanel.value) {
    showDetailPanel.value = false
  }
}

// --- Invoice Detail ---
async function openInvoiceDetail(invoice) {
  try {
    const response = await api.get(`/api/v1/invoices/${invoice.id}`)
    selectedInvoice.value = response.data.data
    showDetailPanel.value = true
  } catch {
    uiStore.showError('Fehler beim Laden')
  }
}

async function refreshSelectedInvoice() {
  if (!selectedInvoice.value) return
  try {
    const response = await api.get(`/api/v1/invoices/${selectedInvoice.value.id}`)
    selectedInvoice.value = response.data.data
    await loadData()
  } catch {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

// --- Invoice Actions ---
function openCreateInvoice() {
  editingInvoice.value = null
  showCreateModal.value = true
}

function openEditInvoice(invoice) {
  editingInvoice.value = invoice
  showCreateModal.value = true
  // close panel if editing from within
  showDetailPanel.value = false
}

async function handleSaveInvoice(form, editingId) {
  const result = await saveInvoice(form, editingId)
  showCreateModal.value = false
  if (result && !editingId) {
    // Auto-open detail panel for new invoice on items tab
    try {
      const response = await api.get(`/api/v1/invoices/${result.id}`)
      selectedInvoice.value = response.data.data
      showDetailPanel.value = true
    } catch { /* non-critical */ }
  }
}

async function handleDeleteInvoice(invoice) {
  const ok = await deleteInvoice(invoice)
  if (ok && selectedInvoice.value?.id === invoice.id) {
    showDetailPanel.value = false
    selectedInvoice.value = null
  }
}

async function handleStatusChange(invoice, status) {
  const ok = await updateInvoiceStatus(invoice, status)
  if (ok && selectedInvoice.value?.id === invoice.id) {
    selectedInvoice.value = { ...selectedInvoice.value, status }
  }
}

async function handleDownloadPdf(invoice) {
  pdfGenerating.value = invoice.id
  const missing = []
  if (!invoice.sender_name && !invoice.sender_company) missing.push('Absender-Name')
  if (!invoice.sender_address) missing.push('Absender-Adresse')
  if (!invoice.items?.length) missing.push('mind. eine Position')
  if (missing.length) {
    uiStore.showError(`PDF nicht möglich – fehlend: ${missing.join(', ')}`)
    pdfGenerating.value = null
    return
  }
  try {
    // If called from list, fetch full data first
    let fullInvoice = invoice
    if (!invoice.items) {
      const res = await api.get(`/api/v1/invoices/${invoice.id}`)
      fullInvoice = res.data.data
    }
    await downloadPdf(fullInvoice, invoiceSenderSettings.value)
  } catch {
    uiStore.showError('PDF konnte nicht erstellt werden')
  } finally {
    pdfGenerating.value = null
  }
}

// --- Client Actions ---
function openCreateClient() {
  editingClient.value = null
  showClientModal.value = true
}

function openEditClient(client) {
  editingClient.value = client
  showClientModal.value = true
}

async function handleSaveClient(form, editingId) {
  const ok = await saveClient(form, editingId)
  if (ok) showClientModal.value = false
}

async function handleDeleteClient(client) {
  await deleteClient(client)
}

// --- Time Entries ---
async function openTimeEntriesModal() {
  try {
    const resp = await api.get('/api/v1/time/projects')
    projects.value = resp.data.data ?? []
  } catch {
    projects.value = []
  }
  timeEntriesForm.value = { client_id: null, project_id: null, hourly_rate: '' }
  showTimeEntriesModal.value = true
}

async function createFromTimeEntries() {
  if (!timeEntriesForm.value.project_id && !timeEntriesForm.value.client_id) {
    toast.error('Bitte Projekt oder Kunden auswählen')
    return
  }
  try {
    const payload = {
      client_id: timeEntriesForm.value.client_id || null,
      project_id: timeEntriesForm.value.project_id || null,
    }
    if (timeEntriesForm.value.hourly_rate) {
      payload.hourly_rate = parseFloat(timeEntriesForm.value.hourly_rate)
    }
    const teResp = await api.get('/api/v1/time', {
      params: { project_id: payload.project_id, limit: 100 },
    })
    const allEntries = teResp.data.data?.items ?? teResp.data.items ?? []
    const billableIds = allEntries.filter(e => e.is_billable && !e.invoiced).map(e => e.id)
    if (billableIds.length === 0) {
      toast.error('Keine abrechenbaren, noch nicht abgerechneten Zeiteinträge gefunden')
      return
    }
    await api.post('/api/v1/invoices/from-time', { ...payload, time_entry_ids: billableIds })
    showTimeEntriesModal.value = false
    await loadData()
    uiStore.showSuccess(`Rechnung aus ${billableIds.length} Zeiteinträgen erstellt`)
  } catch (err) {
    toast.error('Fehler: ' + (err?.response?.data?.message ?? err?.message ?? 'Unbekannter Fehler'))
  }
}
</script>

<template>
  <div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">Rechnungen</h1>
        <p class="text-gray-400 mt-1">Erstelle und verwalte deine Rechnungen</p>
      </div>
      <div class="flex flex-wrap gap-2 items-center">
        <!-- Kleinunternehmer toggle -->
        <button
          @click="toggleKleinunternehmer"
          class="flex items-center gap-2 px-3 py-2 rounded-lg border text-sm transition-colors"
          :class="kleinunternehmerMode
            ? 'bg-amber-500/20 border-amber-500/50 text-amber-300'
            : 'bg-white/[0.04] border-white/[0.06] text-gray-400 hover:text-white'"
          title="Kleinunternehmer nach § 19 UStG"
        >
          <span class="font-mono text-xs font-bold">§19</span>
          <span>{{ kleinunternehmerMode ? 'Kleinunternehmer' : 'Regelbesteuerung' }}</span>
        </button>
        <button @click="openCreateClient" class="btn-secondary">
          <UserIcon class="w-4 h-4 mr-2" />
          Neuer Kunde
        </button>
        <button @click="openTimeEntriesModal" class="btn-secondary">
          <ClockIcon class="w-4 h-4 mr-2" />
          Aus Zeiteinträgen
        </button>
        <button @click="openCreateInvoice" class="btn-primary">
          <PlusIcon class="w-4 h-4 mr-2" />
          Neue Rechnung
        </button>
      </div>
    </div>

    <!-- Setup prompt -->
    <div
      v-if="!invoiceSenderSettings.sender_address"
      class="bg-blue-500/10 border border-blue-500/30 rounded-xl px-4 py-3 text-sm text-blue-300 flex items-center justify-between gap-4"
    >
      <span>Absenderdaten fehlen. Hinterlege Name, Adresse, Steuernummer und Logo für rechtsgültige Rechnungen.</span>
      <RouterLink to="/settings" class="shrink-0 underline hover:no-underline text-blue-200">Einstellungen → Rechnungen</RouterLink>
    </div>

    <!-- Kleinunternehmer banner -->
    <div
      v-if="kleinunternehmerMode"
      class="bg-amber-500/10 border border-amber-500/30 rounded-xl px-4 py-3 text-sm text-amber-300 space-y-1"
    >
      <p><span class="font-semibold">Kleinunternehmer-Modus aktiv (§ 19 UStG):</span> Rechnungen ohne MwSt.</p>
      <p class="text-amber-400/70 text-xs">Grenzen 2025: Vorjahresumsatz ≤ 25.000 € · Laufendes Jahr voraussichtlich ≤ 100.000 €</p>
    </div>

    <!-- Stats -->
    <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-4 hover:border-white/[0.08] transition-colors">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-blue-500/20 rounded-xl flex items-center justify-center shrink-0">
            <DocumentTextIcon class="w-5 h-5 text-blue-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-white">{{ stats.total_invoices || 0 }}</p>
            <p class="text-xs text-gray-500">Rechnungen</p>
          </div>
        </div>
      </div>
      <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-4 hover:border-white/[0.08] transition-colors">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-green-500/20 rounded-xl flex items-center justify-center shrink-0">
            <CheckIcon class="w-5 h-5 text-green-400" />
          </div>
          <div>
            <p class="text-xl font-bold text-green-400">{{ formatCurrency(stats.total_paid) }}</p>
            <p class="text-xs text-gray-500">Bezahlt</p>
          </div>
        </div>
      </div>
      <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-4 hover:border-white/[0.08] transition-colors">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-yellow-500/20 rounded-xl flex items-center justify-center shrink-0">
            <ClockIcon class="w-5 h-5 text-yellow-400" />
          </div>
          <div>
            <p class="text-xl font-bold text-yellow-400">{{ formatCurrency(stats.total_outstanding) }}</p>
            <p class="text-xs text-gray-500">Ausstehend</p>
          </div>
        </div>
      </div>
      <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-4 hover:border-white/[0.08] transition-colors">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-purple-500/20 rounded-xl flex items-center justify-center shrink-0">
            <UserIcon class="w-5 h-5 text-purple-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-white">{{ clients.length }}</p>
            <p class="text-xs text-gray-500">Kunden</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-white/[0.06]">
      <button
        v-for="tab in [{ id: 'invoices', label: 'Rechnungen' }, { id: 'clients', label: 'Kunden' }]"
        :key="tab.id"
        @click="activeTab = tab.id"
        class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
        :class="activeTab === tab.id
          ? 'text-white border-primary-500'
          : 'text-gray-400 border-transparent hover:text-white'"
      >
        {{ tab.label }}
        <span
          v-if="tab.id === 'invoices' && invoices.length"
          class="ml-1.5 text-xs"
          :class="activeTab === 'invoices' ? 'text-gray-400' : 'text-gray-600'"
        >{{ invoices.length }}</span>
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-16">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- INVOICES TAB -->
    <template v-else-if="activeTab === 'invoices'">

      <!-- Search + Filter bar -->
      <div class="flex gap-3 flex-wrap">
        <div class="relative flex-1 min-w-48">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500 pointer-events-none" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Suche nach Rechnungsnummer oder Kunde..."
            class="input pl-9 w-full"
          />
          <button
            v-if="searchQuery"
            @click="searchQuery = ''"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
        <select v-model="statusFilter" class="input w-40">
          <option value="">Alle Status</option>
          <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
      </div>

      <!-- Invoices table -->
      <div v-if="filteredInvoices.length" class="bg-white/[0.04] border border-white/[0.06] rounded-xl overflow-hidden">
        <table class="w-full">
          <thead class="bg-white/[0.03]">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nr.</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kunde</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Datum</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Fällig</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Betrag</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aktionen</th>
            </tr>
          </thead>
          <TransitionGroup tag="tbody" name="list" class="divide-y divide-white/[0.06]">
            <tr
              v-for="invoice in filteredInvoices"
              :key="invoice.id"
              class="hover:bg-white/[0.04]/40 transition-colors cursor-pointer group"
              @click="openInvoiceDetail(invoice)"
            >
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <span class="font-mono text-sm text-white font-semibold">{{ invoice.invoice_number }}</span>
                  <span
                    v-if="invoice.document_type && invoice.document_type !== 'invoice'"
                    class="text-xs px-1.5 py-0.5 rounded font-medium hidden sm:inline"
                    :class="{
                      'bg-blue-500/20 text-blue-300': invoice.document_type === 'proforma',
                      'bg-yellow-500/20 text-yellow-300': invoice.document_type === 'quote',
                      'bg-red-500/20 text-red-300': invoice.document_type === 'credit_note',
                      'bg-orange-500/20 text-orange-300': invoice.document_type === 'reminder',
                    }"
                  >{{ { proforma: 'Proforma', quote: 'Angebot', credit_note: 'Gutschrift', reminder: 'Mahnung' }[invoice.document_type] }}</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <div>
                  <p class="text-sm text-gray-200">{{ invoice.client_name || '–' }}</p>
                  <p v-if="invoice.client_company" class="text-xs text-gray-500">{{ invoice.client_company }}</p>
                </div>
              </td>
              <td class="px-4 py-3 text-sm text-gray-400 hidden md:table-cell">{{ formatDate(invoice.issue_date) }}</td>
              <td class="px-4 py-3 text-sm hidden md:table-cell" :class="invoice.status === 'overdue' ? 'text-red-400 font-medium' : 'text-gray-400'">
                {{ formatDate(invoice.due_date) }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded-full font-medium"
                  :class="getStatusInfo(invoice.status).color + '/15 ' + getStatusInfo(invoice.status).textColor"
                >
                  {{ getStatusInfo(invoice.status).label }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <span class="text-sm font-semibold text-white">{{ formatCurrency(invoice.total) }}</span>
              </td>
              <td class="px-4 py-3" @click.stop>
                <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button
                    @click="openInvoiceDetail(invoice)"
                    class="p-1.5 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded-lg"
                    title="Details anzeigen"
                  >
                    <EyeIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="handleDownloadPdf(invoice)"
                    class="p-1.5 hover:bg-white/[0.04] rounded-lg"
                    :class="pdfGenerating === invoice.id ? 'text-primary-400 animate-pulse' : 'text-gray-400 hover:text-primary-400'"
                    title="PDF herunterladen"
                    :disabled="pdfGenerating === invoice.id"
                  >
                    <ArrowDownTrayIcon class="w-4 h-4" />
                  </button>
                  <button
                    v-if="invoice.status === 'draft'"
                    @click="handleStatusChange(invoice, 'sent')"
                    class="p-1.5 text-gray-400 hover:text-blue-400 hover:bg-white/[0.04] rounded-lg"
                    title="Als gesendet markieren"
                  >
                    <PaperAirplaneIcon class="w-4 h-4" />
                  </button>
                  <button
                    v-if="invoice.status === 'sent'"
                    @click="handleStatusChange(invoice, 'paid')"
                    class="p-1.5 text-gray-400 hover:text-green-400 hover:bg-white/[0.04] rounded-lg"
                    title="Als bezahlt markieren"
                  >
                    <CheckIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="openEditInvoice(invoice)"
                    class="p-1.5 text-gray-400 hover:text-yellow-400 hover:bg-white/[0.04] rounded-lg"
                    title="Bearbeiten"
                  >
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="duplicateInvoice(invoice)"
                    class="p-1.5 text-gray-400 hover:text-purple-400 hover:bg-white/[0.04] rounded-lg"
                    title="Duplizieren"
                  >
                    <DocumentDuplicateIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="handleDeleteInvoice(invoice)"
                    class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-white/[0.04] rounded-lg"
                    title="Löschen"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </TransitionGroup>
        </table>
      </div>

      <!-- Empty state -->
      <div v-else class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-16 text-center">
        <DocumentTextIcon class="w-16 h-16 mx-auto text-gray-700 mb-4" />
        <h3 class="text-lg font-semibold text-white mb-2">
          {{ searchQuery || statusFilter ? 'Keine Ergebnisse' : 'Noch keine Rechnungen' }}
        </h3>
        <p class="text-gray-500 mb-6">
          {{ searchQuery || statusFilter ? 'Versuche andere Suchkriterien.' : 'Erstelle deine erste Rechnung.' }}
        </p>
        <button v-if="!searchQuery && !statusFilter" @click="openCreateInvoice" class="btn-primary">
          <PlusIcon class="w-4 h-4 mr-2" />
          Rechnung erstellen
        </button>
      </div>

    </template>

    <!-- CLIENTS TAB -->
    <template v-else-if="activeTab === 'clients'">
      <div v-if="clients.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="client in clients"
          :key="client.id"
          class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-5 group hover:border-white/[0.08] transition-colors"
        >
          <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
              <div
                class="w-11 h-11 rounded-xl flex items-center justify-center text-white font-bold text-lg shrink-0"
                :style="{ backgroundColor: client.color }"
              >
                {{ client.name.charAt(0).toUpperCase() }}
              </div>
              <div>
                <h3 class="font-semibold text-white leading-tight">{{ client.name }}</h3>
                <p v-if="client.company" class="text-sm text-gray-400 leading-tight">{{ client.company }}</p>
              </div>
            </div>
            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
              <button
                @click="openEditClient(client)"
                class="p-1.5 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded-lg transition-colors"
                title="Bearbeiten"
              >
                <PencilIcon class="w-4 h-4" />
              </button>
              <button
                @click="handleDeleteClient(client)"
                class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-white/[0.04] rounded-lg transition-colors"
                title="Löschen"
              >
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div class="space-y-1 mb-4">
            <p v-if="client.email" class="text-sm text-gray-500 flex items-center gap-1.5">
              <span class="w-4 text-center text-gray-600">@</span>{{ client.email }}
            </p>
            <p v-if="client.phone" class="text-sm text-gray-500 flex items-center gap-1.5">
              <span class="w-4 text-center text-gray-600">#</span>{{ client.phone }}
            </p>
            <p v-if="client.city" class="text-sm text-gray-500 flex items-center gap-1.5">
              <span class="w-4 text-center text-gray-600">📍</span>{{ client.postal_code }} {{ client.city }}
            </p>
          </div>

          <div class="flex items-center justify-between pt-3 border-t border-white/[0.06] text-sm">
            <span class="text-gray-600">{{ client.invoice_count || 0 }} Rechnungen</span>
            <span class="text-green-400 font-semibold">{{ formatCurrency(client.total_paid) }}</span>
          </div>
        </div>
      </div>

      <div v-else class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-16 text-center">
        <UserIcon class="w-16 h-16 mx-auto text-gray-700 mb-4" />
        <h3 class="text-lg font-semibold text-white mb-2">Keine Kunden</h3>
        <p class="text-gray-500 mb-6">Füge deinen ersten Kunden hinzu.</p>
        <button @click="openCreateClient" class="btn-primary">
          <PlusIcon class="w-4 h-4 mr-2" />
          Kunde hinzufügen
        </button>
      </div>
    </template>

    <!-- MODALS & PANELS -->

    <!-- Invoice Detail Slide-Over -->
    <InvoiceDetailPanel
      :invoice="selectedInvoice"
      :sender-settings="invoiceSenderSettings"
      :pdf-generating="pdfGenerating"
      @close="showDetailPanel = false; selectedInvoice = null"
      @download-pdf="handleDownloadPdf"
      @duplicate="duplicateInvoice"
      @edit="openEditInvoice"
      @delete="handleDeleteInvoice"
      @status-change="handleStatusChange"
      @items-changed="refreshSelectedInvoice"
    />

    <!-- Invoice Create / Edit Modal -->
    <InvoiceCreateModal
      :show="showCreateModal"
      :editing-invoice="editingInvoice"
      :clients="clients"
      :kleinunternehmer-mode="kleinunternehmerMode"
      :default-payment-terms="invoiceSenderSettings.default_payment_terms"
      @close="showCreateModal = false"
      @save="handleSaveInvoice"
    />

    <!-- Client Modal -->
    <ClientModal
      :show="showClientModal"
      :editing-client="editingClient"
      @close="showClientModal = false"
      @save="handleSaveClient"
    />

    <!-- Time Entries Modal -->
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
          v-if="showTimeEntriesModal"
          class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4"
          @click.self="showTimeEntriesModal = false"
        >
          <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
          >
            <div
              v-if="showTimeEntriesModal"
              class="modal w-full max-w-md"
            >
              <div class="flex items-center justify-between px-6 py-4 border-b border-white/[0.06]">
                <h3 class="text-lg font-bold text-white">Rechnung aus Zeiteinträgen</h3>
                <button @click="showTimeEntriesModal = false" class="p-1.5 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded-lg">
                  <XMarkIcon class="w-5 h-5" />
                </button>
              </div>
              <div class="p-6 space-y-4">
                <div class="bg-blue-500/10 border border-blue-500/20 rounded-xl px-4 py-3 text-sm text-blue-300">
                  Alle abrechenbaren, noch nicht abgerechneten Zeiteinträge des gewählten Projekts werden importiert.
                </div>
                <div>
                  <label class="label">Projekt</label>
                  <select v-model="timeEntriesForm.project_id" class="input">
                    <option :value="null">Kein Projekt ausgewählt</option>
                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="label">Kunde</label>
                  <select v-model="timeEntriesForm.client_id" class="input">
                    <option :value="null">Kein Kunde</option>
                    <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="label">Stundensatz (€) <span class="text-gray-500 font-normal">— überschreibt Eintragswert</span></label>
                  <input v-model="timeEntriesForm.hourly_rate" type="number" step="0.01" min="0" class="input" placeholder="z.B. 75.00" />
                </div>
                <div class="flex gap-3 pt-2">
                  <button @click="showTimeEntriesModal = false" class="btn-secondary flex-1">Abbrechen</button>
                  <button
                    @click="createFromTimeEntries"
                    class="btn-primary flex-1"
                    :disabled="!timeEntriesForm.project_id && !timeEntriesForm.client_id"
                  >
                    Rechnung erstellen
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<style scoped>
.list-enter-active,
.list-leave-active {
  transition: all 0.2s ease;
}
.list-enter-from {
  opacity: 0;
  transform: translateY(-8px);
}
.list-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
.list-move {
  transition: transform 0.2s ease;
}
</style>
