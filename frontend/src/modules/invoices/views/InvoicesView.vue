<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  PlusIcon,
  DocumentTextIcon,
  TrashIcon,
  PencilIcon,
  UserIcon,
  CurrencyEuroIcon,
  EyeIcon,
  XMarkIcon,
  CheckIcon,
  ClockIcon,
  PaperAirplaneIcon,
  ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline'

const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// State
const activeTab = ref('invoices')
const invoices = ref([])
const clients = ref([])
const stats = ref(null)
const isLoading = ref(true)
const showInvoiceModal = ref(false)
const showClientModal = ref(false)
const showDetailModal = ref(false)
const editingInvoice = ref(null)
const editingClient = ref(null)
const selectedInvoice = ref(null)
const statusFilter = ref('')
const kleinunternehmerMode = ref(false)
const pdfGenerating = ref(false)
const invoiceSenderSettings = ref({})

function escapeHtml(str) {
  return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')
}

// Invoice form
const invoiceForm = ref({
  client_id: null,
  project_id: null,
  issue_date: '',
  due_date: '',
  tax_rate: 19,
  notes: '',
})

// Client form
const clientForm = ref({
  name: '',
  company: '',
  email: '',
  phone: '',
  address_line1: '',
  address_line2: '',
  city: '',
  postal_code: '',
  country: 'Deutschland',
  vat_id: '',
  default_hourly_rate: null,
  color: '#6366f1',
})

const statusOptions = [
  { value: 'draft', label: 'Entwurf', color: 'bg-gray-500' },
  { value: 'sent', label: 'Gesendet', color: 'bg-blue-500' },
  { value: 'paid', label: 'Bezahlt', color: 'bg-green-500' },
  { value: 'overdue', label: 'Überfällig', color: 'bg-red-500' },
  { value: 'cancelled', label: 'Storniert', color: 'bg-gray-500' },
]

const colors = [
  '#6366f1', '#8B5CF6', '#EC4899', '#EF4444', '#F97316',
  '#EAB308', '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
]

// Filtered invoices
const filteredInvoices = computed(() => {
  if (!statusFilter.value) return invoices.value
  return invoices.value.filter(i => i.status === statusFilter.value)
})

// API Calls
onMounted(async () => {
  await loadData()
  // Load Kleinunternehmer setting + invoice sender settings
  try {
    const settingsResp = await api.get('/api/v1/settings/user')
    const s = settingsResp.data.data ?? {}
    kleinunternehmerMode.value = s.kleinunternehmer_mode ?? false
    invoiceSenderSettings.value = {
      sender_name: s.invoice_sender_name ?? '',
      sender_company: s.invoice_company ?? '',
      sender_address: s.invoice_address ?? '',
      sender_email: s.invoice_email ?? '',
      sender_phone: s.invoice_phone ?? '',
      sender_vat_id: s.invoice_vat_id ?? '',
      invoice_steuernummer: s.invoice_steuernummer ?? '',
      sender_bank_details: s.invoice_bank_details ?? '',
    }
  } catch (err) {
    console.warn('Einstellungen konnten nicht geladen werden', err)
    uiStore.showError('Einstellungen konnten nicht geladen werden')
  }
})

async function loadData() {
  isLoading.value = true
  try {
    const [invoicesRes, clientsRes, statsRes] = await Promise.all([
      api.get('/api/v1/invoices'),
      api.get('/api/v1/clients'),
      api.get('/api/v1/invoices/stats'),
    ])
    invoices.value = invoicesRes.data.data?.items || []
    clients.value = clientsRes.data.data?.items || []
    stats.value = statsRes.data.data
  } catch (error) {
    uiStore.showError('Fehler beim Laden')
  } finally {
    isLoading.value = false
  }
}

// Invoice CRUD
async function saveInvoice() {
  try {
    if (editingInvoice.value) {
      await api.put(`/api/v1/invoices/${editingInvoice.value.id}`, invoiceForm.value)
      uiStore.showSuccess('Rechnung aktualisiert')
    } else {
      await api.post('/api/v1/invoices', invoiceForm.value)
      uiStore.showSuccess('Rechnung erstellt')
    }
    await loadData()
    showInvoiceModal.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deleteInvoice(invoice) {
  if (!await confirm({ message: `Rechnung ${invoice.invoice_number} wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/invoices/${invoice.id}`)
    invoices.value = invoices.value.filter(i => i.id !== invoice.id)
    uiStore.showSuccess('Rechnung gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function updateInvoiceStatus(invoice, status) {
  try {
    await api.put(`/api/v1/invoices/${invoice.id}`, {
      status,
      paid_date: status === 'paid' ? new Date().toISOString().split('T')[0] : null,
    })
    invoice.status = status
    uiStore.showSuccess('Status aktualisiert')
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

async function openInvoiceDetail(invoice) {
  try {
    const response = await api.get(`/api/v1/invoices/${invoice.id}`)
    selectedInvoice.value = response.data.data
    showDetailModal.value = true
  } catch (error) {
    uiStore.showError('Fehler beim Laden')
  }
}

// Client CRUD
async function saveClient() {
  if (!clientForm.value.name.trim()) {
    uiStore.showError('Name ist erforderlich')
    return
  }

  try {
    if (editingClient.value) {
      await api.put(`/api/v1/clients/${editingClient.value.id}`, clientForm.value)
      uiStore.showSuccess('Kunde aktualisiert')
    } else {
      await api.post('/api/v1/clients', clientForm.value)
      uiStore.showSuccess('Kunde erstellt')
    }
    await loadData()
    showClientModal.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deleteClient(client) {
  if (!await confirm({ message: `Kunde "${client.name}" wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/clients/${client.id}`)
    clients.value = clients.value.filter(c => c.id !== client.id)
    uiStore.showSuccess('Kunde gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Modals
function openCreateInvoice() {
  editingInvoice.value = null
  const today = new Date().toISOString().split('T')[0]
  const dueDate = new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
  invoiceForm.value = {
    client_id: null,
    project_id: null,
    issue_date: today,
    due_date: dueDate,
    tax_rate: kleinunternehmerMode.value ? 0 : 19,
    notes: '',
  }
  showInvoiceModal.value = true
}

async function toggleKleinunternehmer() {
  kleinunternehmerMode.value = !kleinunternehmerMode.value
  try {
    await api.put('/api/v1/settings/user', { kleinunternehmer_mode: kleinunternehmerMode.value })
    uiStore.showSuccess(kleinunternehmerMode.value
      ? 'Kleinunternehmer-Modus aktiviert (§ 19 UStG)'
      : 'Kleinunternehmer-Modus deaktiviert')
  } catch {
    uiStore.showError('Einstellung konnte nicht gespeichert werden')
  }
}

async function downloadInvoicePdf(invoice) {
  pdfGenerating.value = invoice.id
  try {
    // Fetch full invoice data
    const resp = await api.get(`/api/v1/invoices/${invoice.id}`)
    const inv = resp.data.data

    // Validate required fields before generating (§14 UStG)
    const missing = []
    if (!inv.sender_name && !inv.sender_company) missing.push('Absender-Name')
    if (!inv.sender_address) missing.push('Absender-Adresse (Einstellungen → Rechnungen)')
    if (!inv.items?.length) missing.push('mind. eine Rechnungsposition')
    if (missing.length) {
      uiStore.showError(`PDF nicht möglich – fehlende Pflichtangaben: ${missing.join(', ')}`)
      return
    }

    const isKleinunternehmer = parseFloat(inv.tax_rate) === 0

    // Steuernummer: read from stored sender settings (loaded on mount)
    const steuernummer = invoiceSenderSettings.value.invoice_steuernummer || ''

    const itemsHtml = (inv.items || []).map(item => `
      <tr>
        <td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">${escapeHtml(item.description ?? '').replace(/\n/g, '<br>')}</td>
        <td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:right;">${parseFloat(item.quantity ?? 0).toLocaleString('de-DE')} ${escapeHtml(item.unit ?? '')}</td>
        <td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:right;">${formatCurrency(item.unit_price ?? 0)}</td>
        <td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:right;">${formatCurrency(item.total ?? 0)}</td>
      </tr>`).join('')

    const totalsHtml = isKleinunternehmer
      ? `<tr><td colspan="3" style="padding:8px 12px;text-align:right;font-weight:bold;">Gesamtbetrag (netto)</td><td style="padding:8px 12px;text-align:right;font-weight:bold;">${formatCurrency(inv.subtotal)}</td></tr>`
      : `
        <tr><td colspan="3" style="padding:8px 12px;text-align:right;">Netto</td><td style="padding:8px 12px;text-align:right;">${formatCurrency(inv.subtotal)}</td></tr>
        <tr><td colspan="3" style="padding:8px 12px;text-align:right;">MwSt. (${escapeHtml(String(inv.tax_rate))}%)</td><td style="padding:8px 12px;text-align:right;">${formatCurrency(inv.tax_amount)}</td></tr>
        <tr><td colspan="3" style="padding:8px 12px;text-align:right;font-weight:bold;border-top:2px solid #111827;">Gesamtbetrag</td><td style="padding:8px 12px;text-align:right;font-weight:bold;border-top:2px solid #111827;">${formatCurrency(inv.total)}</td></tr>`

    const senderTaxLine = isKleinunternehmer && steuernummer
      ? `<div style="color:#6b7280;font-size:11px;">Steuernummer: ${escapeHtml(steuernummer)}</div>`
      : (inv.sender_vat_id ? `<div style="color:#6b7280;font-size:11px;">USt-IdNr.: ${escapeHtml(inv.sender_vat_id)}</div>` : '')

    const html = `
      <html><head><meta charset="UTF-8">
      <style>
        body { font-family: Arial, sans-serif; font-size: 13px; color: #111827; margin: 0; padding: 40px; }
        h1 { font-size: 22px; font-weight: bold; margin-bottom: 4px; }
        .label { color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        thead th { background: #f3f4f6; padding: 10px 12px; text-align: left; font-size: 12px; color: #374151; }
        thead th:not(:first-child) { text-align: right; }
        .notice { margin-top: 24px; padding: 12px; background: #f9fafb; border-left: 3px solid #6b7280; font-size: 12px; color: #4b5563; }
      </style>
      </head><body>
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:32px;">
        <div>
          <h1>Rechnung</h1>
          <div style="color:#6b7280;">${escapeHtml(inv.invoice_number)}</div>
        </div>
        <div style="text-align:right;">
          <div class="label">Datum</div>
          <div>${formatDate(inv.issue_date)}</div>
          ${inv.due_date ? `<div class="label" style="margin-top:8px;">Fällig bis</div><div>${formatDate(inv.due_date)}</div>` : ''}
        </div>
      </div>
      <div style="display:flex;gap:48px;margin-bottom:32px;">
        <div style="flex:1;">
          <div class="label">Von</div>
          <div style="font-weight:600;">${escapeHtml(inv.sender_name ?? '')}</div>
          ${inv.sender_company ? `<div>${escapeHtml(inv.sender_company)}</div>` : ''}
          ${inv.sender_address ? `<div style="white-space:pre-line;color:#4b5563;">${escapeHtml(inv.sender_address)}</div>` : ''}
          ${senderTaxLine}
          ${inv.sender_email ? `<div style="color:#4b5563;">${escapeHtml(inv.sender_email)}</div>` : ''}
          ${inv.sender_phone ? `<div style="color:#4b5563;">${escapeHtml(inv.sender_phone)}</div>` : ''}
          ${inv.sender_bank_details ? `<div style="margin-top:8px;white-space:pre-line;font-size:11px;color:#6b7280;">${escapeHtml(inv.sender_bank_details)}</div>` : ''}
        </div>
        <div style="flex:1;">
          <div class="label">An</div>
          <div style="font-weight:600;">${escapeHtml(inv.client_name ?? '')}</div>
          ${inv.client_company ? `<div>${escapeHtml(inv.client_company)}</div>` : ''}
          ${inv.client_address ? `<div style="white-space:pre-line;color:#4b5563;">${escapeHtml(inv.client_address)}</div>` : ''}
          ${inv.client_email ? `<div style="color:#4b5563;">${escapeHtml(inv.client_email)}</div>` : ''}
          ${inv.client_vat_id ? `<div style="color:#6b7280;font-size:11px;">USt-IdNr.: ${escapeHtml(inv.client_vat_id)}</div>` : ''}
        </div>
      </div>
      <table>
        <thead><tr>
          <th>Beschreibung</th>
          <th style="text-align:right;">Menge</th>
          <th style="text-align:right;">Einzelpreis</th>
          <th style="text-align:right;">Betrag</th>
        </tr></thead>
        <tbody>${itemsHtml}</tbody>
        <tfoot>${totalsHtml}</tfoot>
      </table>
      ${inv.notes ? `<div style="margin-top:24px;"><div class="label">Anmerkungen</div><div style="color:#4b5563;">${escapeHtml(inv.notes).replace(/\n/g, '<br>')}</div></div>` : ''}
      ${inv.terms ? `<div class="notice">${escapeHtml(inv.terms).replace(/\n/g, '<br>')}</div>` : ''}
      </body></html>`

    const el = document.createElement('div')
    el.innerHTML = html
    el.style.position = 'absolute'
    el.style.left = '-9999px'
    document.body.appendChild(el)

    const { default: html2pdf } = await import('html2pdf.js')
    await html2pdf().set({
      margin: 0,
      filename: `${inv.invoice_number}.pdf`,
      html2canvas: { scale: 2, useCORS: true },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
    }).from(el.firstChild).save()

    document.body.removeChild(el)
  } catch (err) {
    uiStore.showError('PDF konnte nicht erstellt werden')
  } finally {
    pdfGenerating.value = null
  }
}

function openCreateClient() {
  editingClient.value = null
  clientForm.value = {
    name: '',
    company: '',
    email: '',
    phone: '',
    address_line1: '',
    address_line2: '',
    city: '',
    postal_code: '',
    country: 'Deutschland',
    vat_id: '',
    default_hourly_rate: null,
    color: '#6366f1',
  }
  showClientModal.value = true
}

function openEditClient(client) {
  editingClient.value = client
  clientForm.value = { ...client }
  showClientModal.value = true
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(amount || 0)
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('de-DE')
}

function getStatusInfo(status) {
  return statusOptions.find(s => s.value === status) || statusOptions[0]
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
            : 'bg-dark-700 border-dark-600 text-gray-400 hover:text-white'"
          title="Kleinunternehmer nach § 19 UStG"
        >
          <span class="font-mono text-xs">§19</span>
          <span>{{ kleinunternehmerMode ? 'Kleinunternehmer' : 'Regelbesteuerung' }}</span>
        </button>
        <button @click="openCreateClient" class="btn-secondary">
          <UserIcon class="w-5 h-5 mr-2" />
          Neuer Kunde
        </button>
        <button @click="openCreateInvoice" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Neue Rechnung
        </button>
      </div>
    </div>

    <!-- Kleinunternehmer info banner -->
    <div v-if="kleinunternehmerMode" class="bg-amber-500/10 border border-amber-500/30 rounded-xl px-4 py-3 text-sm text-amber-300 space-y-1">
      <p><span class="font-medium">Kleinunternehmer-Modus aktiv (§ 19 UStG):</span>
      Rechnungen werden ohne MwSt. erstellt und erhalten automatisch den gesetzlich vorgeschriebenen Hinweis.</p>
      <p class="text-amber-400/70 text-xs">Grenzen 2025: Vorjahresumsatz ≤ 25.000 € · Laufendes Jahr voraussichtlich ≤ 100.000 €</p>
    </div>

    <!-- Stats -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center">
            <DocumentTextIcon class="w-5 h-5 text-blue-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-white">{{ stats.total_invoices || 0 }}</p>
            <p class="text-sm text-gray-400">Rechnungen</p>
          </div>
        </div>
      </div>
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-green-500/20 rounded-lg flex items-center justify-center">
            <CheckIcon class="w-5 h-5 text-green-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-green-400">{{ formatCurrency(stats.total_paid) }}</p>
            <p class="text-sm text-gray-400">Bezahlt</p>
          </div>
        </div>
      </div>
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-yellow-500/20 rounded-lg flex items-center justify-center">
            <ClockIcon class="w-5 h-5 text-yellow-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-yellow-400">{{ formatCurrency(stats.total_outstanding) }}</p>
            <p class="text-sm text-gray-400">Ausstehend</p>
          </div>
        </div>
      </div>
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center">
            <UserIcon class="w-5 h-5 text-purple-400" />
          </div>
          <div>
            <p class="text-2xl font-bold text-white">{{ clients.length }}</p>
            <p class="text-sm text-gray-400">Kunden</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-4 border-b border-dark-700">
      <button
        @click="activeTab = 'invoices'"
        class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
        :class="activeTab === 'invoices' ? 'text-white border-primary-500' : 'text-gray-400 border-transparent hover:text-white'"
      >
        Rechnungen
      </button>
      <button
        @click="activeTab = 'clients'"
        class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
        :class="activeTab === 'clients' ? 'text-white border-primary-500' : 'text-gray-400 border-transparent hover:text-white'"
      >
        Kunden
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Invoices Tab -->
    <template v-else-if="activeTab === 'invoices'">
      <!-- Filter -->
      <div class="flex gap-4">
        <select v-model="statusFilter" class="input w-48">
          <option value="">Alle Status</option>
          <option v-for="status in statusOptions" :key="status.value" :value="status.value">
            {{ status.label }}
          </option>
        </select>
      </div>

      <!-- Invoices list -->
      <div v-if="filteredInvoices.length" class="bg-dark-800 border border-dark-700 rounded-xl overflow-hidden">
        <table class="w-full">
          <thead class="bg-dark-700">
            <tr>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Nr.</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Kunde</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Datum</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Fällig</th>
              <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Status</th>
              <th class="px-4 py-3 text-right text-sm font-medium text-gray-400">Betrag</th>
              <th class="px-4 py-3 text-right text-sm font-medium text-gray-400">Aktionen</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-dark-700">
            <tr v-for="invoice in filteredInvoices" :key="invoice.id" class="hover:bg-dark-700/50">
              <td class="px-4 py-3">
                <span class="font-mono text-white">{{ invoice.invoice_number }}</span>
              </td>
              <td class="px-4 py-3 text-gray-300">{{ invoice.client_name || '-' }}</td>
              <td class="px-4 py-3 text-gray-400">{{ formatDate(invoice.issue_date) }}</td>
              <td class="px-4 py-3 text-gray-400">{{ formatDate(invoice.due_date) }}</td>
              <td class="px-4 py-3">
                <span
                  class="px-2 py-1 text-xs rounded-full"
                  :class="getStatusInfo(invoice.status).color + '/20 text-white'"
                >
                  {{ getStatusInfo(invoice.status).label }}
                </span>
              </td>
              <td class="px-4 py-3 text-right font-medium text-white">
                {{ formatCurrency(invoice.total) }}
              </td>
              <td class="px-4 py-3">
                <div class="flex justify-end gap-1">
                  <button
                    @click="openInvoiceDetail(invoice)"
                    class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
                    title="Details anzeigen"
                  >
                    <EyeIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="downloadInvoicePdf(invoice)"
                    class="p-1.5 hover:bg-dark-600 rounded"
                    :class="pdfGenerating === invoice.id ? 'text-primary-400 animate-pulse' : 'text-gray-400 hover:text-primary-400'"
                    title="Als PDF herunterladen"
                    :disabled="pdfGenerating === invoice.id"
                  >
                    <ArrowDownTrayIcon class="w-4 h-4" />
                  </button>
                  <button
                    v-if="invoice.status === 'draft'"
                    @click="updateInvoiceStatus(invoice, 'sent')"
                    class="p-1.5 text-gray-400 hover:text-blue-400 hover:bg-dark-600 rounded"
                    title="Als gesendet markieren"
                  >
                    <PaperAirplaneIcon class="w-4 h-4" />
                  </button>
                  <button
                    v-if="invoice.status === 'sent'"
                    @click="updateInvoiceStatus(invoice, 'paid')"
                    class="p-1.5 text-gray-400 hover:text-green-400 hover:bg-dark-600 rounded"
                    title="Als bezahlt markieren"
                  >
                    <CheckIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="deleteInvoice(invoice)"
                    class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="card p-12 text-center">
        <DocumentTextIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
        <h3 class="text-lg font-medium text-white mb-2">Keine Rechnungen</h3>
        <p class="text-gray-400 mb-6">Erstelle deine erste Rechnung</p>
        <button @click="openCreateInvoice" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Rechnung erstellen
        </button>
      </div>
    </template>

    <!-- Clients Tab -->
    <template v-else-if="activeTab === 'clients'">
      <div v-if="clients.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="client in clients"
          :key="client.id"
          class="bg-dark-800 border border-dark-700 rounded-xl p-4 group hover:border-dark-600"
        >
          <div class="flex items-start justify-between mb-3">
            <div
              class="w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold"
              :style="{ backgroundColor: client.color }"
            >
              {{ client.name.charAt(0).toUpperCase() }}
            </div>
            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
              <button
                @click="openEditClient(client)"
                class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
              >
                <PencilIcon class="w-4 h-4" />
              </button>
              <button
                @click="deleteClient(client)"
                class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded"
              >
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
          <h3 class="font-medium text-white">{{ client.name }}</h3>
          <p v-if="client.company" class="text-sm text-gray-400">{{ client.company }}</p>
          <p v-if="client.email" class="text-sm text-gray-500 mt-1">{{ client.email }}</p>
          <div class="flex items-center justify-between mt-4 pt-3 border-t border-dark-700 text-sm">
            <span class="text-gray-500">{{ client.invoice_count || 0 }} Rechnungen</span>
            <span class="text-green-400">{{ formatCurrency(client.total_paid) }}</span>
          </div>
        </div>
      </div>
      <div v-else class="card p-12 text-center">
        <UserIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
        <h3 class="text-lg font-medium text-white mb-2">Keine Kunden</h3>
        <p class="text-gray-400 mb-6">Füge deinen ersten Kunden hinzu</p>
        <button @click="openCreateClient" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Kunde hinzufügen
        </button>
      </div>
    </template>

    <!-- Invoice Modal -->
    <Teleport to="body">
      <div
        v-if="showInvoiceModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="showInvoiceModal = false"
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-md border border-dark-700">
          <div class="p-4 border-b border-dark-700 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">Neue Rechnung</h2>
            <button @click="showInvoiceModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="saveInvoice" class="p-4 space-y-4">
            <div>
              <label class="label">Kunde</label>
              <select v-model="invoiceForm.client_id" class="input">
                <option :value="null">Kein Kunde</option>
                <option v-for="client in clients" :key="client.id" :value="client.id">
                  {{ client.name }}
                </option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">Rechnungsdatum</label>
                <input v-model="invoiceForm.issue_date" type="date" class="input" required />
              </div>
              <div>
                <label class="label">Fälligkeitsdatum</label>
                <input v-model="invoiceForm.due_date" type="date" class="input" />
              </div>
            </div>

            <div v-if="!kleinunternehmerMode">
              <label class="label">MwSt. (%)</label>
              <input v-model.number="invoiceForm.tax_rate" type="number" class="input" step="0.01" />
            </div>
            <div v-else class="bg-amber-500/10 border border-amber-500/30 rounded-lg px-3 py-2 text-sm text-amber-300">
              Kleinunternehmer: 0% MwSt. · § 19 UStG Hinweis wird automatisch hinzugefügt
            </div>

            <div>
              <label class="label">Anmerkungen</label>
              <textarea v-model="invoiceForm.notes" class="input" rows="2"></textarea>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="showInvoiceModal = false" class="btn-secondary flex-1">
                Abbrechen
              </button>
              <button type="submit" class="btn-primary flex-1">
                Erstellen
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Client Modal -->
    <Teleport to="body">
      <div
        v-if="showClientModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="showClientModal = false"
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-lg border border-dark-700 max-h-[90vh] overflow-y-auto">
          <div class="p-4 border-b border-dark-700 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white">
              {{ editingClient ? 'Kunde bearbeiten' : 'Neuer Kunde' }}
            </h2>
            <button @click="showClientModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="saveClient" class="p-4 space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2 sm:col-span-1">
                <label class="label">Name *</label>
                <input v-model="clientForm.name" type="text" class="input" required />
              </div>
              <div class="col-span-2 sm:col-span-1">
                <label class="label">Firma</label>
                <input v-model="clientForm.company" type="text" class="input" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">E-Mail</label>
                <input v-model="clientForm.email" type="email" class="input" />
              </div>
              <div>
                <label class="label">Telefon</label>
                <input v-model="clientForm.phone" type="tel" class="input" />
              </div>
            </div>

            <div>
              <label class="label">Adresse</label>
              <input v-model="clientForm.address_line1" type="text" class="input mb-2" placeholder="Straße, Nr." />
              <input v-model="clientForm.address_line2" type="text" class="input" placeholder="Zusatz (optional)" />
            </div>

            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="label">PLZ</label>
                <input v-model="clientForm.postal_code" type="text" class="input" />
              </div>
              <div class="col-span-2">
                <label class="label">Stadt</label>
                <input v-model="clientForm.city" type="text" class="input" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">USt-IdNr.</label>
                <input v-model="clientForm.vat_id" type="text" class="input" placeholder="DE123456789" />
              </div>
              <div>
                <label class="label">Std.-Satz (EUR)</label>
                <input v-model.number="clientForm.default_hourly_rate" type="number" class="input" step="0.01" />
              </div>
            </div>

            <div>
              <label class="label">Farbe</label>
              <div class="flex gap-2">
                <button
                  v-for="color in colors"
                  :key="color"
                  type="button"
                  @click="clientForm.color = color"
                  class="w-8 h-8 rounded-lg border-2 transition-transform hover:scale-110"
                  :class="clientForm.color === color ? 'border-white scale-110' : 'border-transparent'"
                  :style="{ backgroundColor: color }"
                ></button>
              </div>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="showClientModal = false" class="btn-secondary flex-1">
                Abbrechen
              </button>
              <button type="submit" class="btn-primary flex-1">
                {{ editingClient ? 'Speichern' : 'Erstellen' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Invoice Detail Modal -->
    <Teleport to="body">
      <div
        v-if="showDetailModal && selectedInvoice"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="showDetailModal = false"
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-2xl border border-dark-700 max-h-[90vh] overflow-y-auto">
          <div class="p-4 border-b border-dark-700 flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-white">{{ selectedInvoice.invoice_number }}</h2>
              <p class="text-sm text-gray-400">{{ formatDate(selectedInvoice.issue_date) }}</p>
            </div>
            <button @click="showDetailModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-6">
            <!-- Client info -->
            <div class="grid grid-cols-2 gap-6">
              <div>
                <h3 class="text-sm font-medium text-gray-400 mb-2">Von</h3>
                <p class="text-white">{{ selectedInvoice.sender_name }}</p>
                <p v-if="selectedInvoice.sender_company" class="text-gray-400">{{ selectedInvoice.sender_company }}</p>
                <p class="text-gray-400 text-sm whitespace-pre-line">{{ selectedInvoice.sender_address }}</p>
              </div>
              <div>
                <h3 class="text-sm font-medium text-gray-400 mb-2">An</h3>
                <p class="text-white">{{ selectedInvoice.client_name || '-' }}</p>
                <p v-if="selectedInvoice.client_company" class="text-gray-400">{{ selectedInvoice.client_company }}</p>
                <p class="text-gray-400 text-sm whitespace-pre-line">{{ selectedInvoice.client_address }}</p>
              </div>
            </div>

            <!-- Items -->
            <div>
              <h3 class="text-sm font-medium text-gray-400 mb-2">Positionen</h3>
              <div class="bg-dark-700 rounded-lg overflow-hidden">
                <table class="w-full">
                  <thead class="bg-dark-600">
                    <tr>
                      <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Beschreibung</th>
                      <th class="px-3 py-2 text-right text-xs font-medium text-gray-400">Menge</th>
                      <th class="px-3 py-2 text-right text-xs font-medium text-gray-400">Preis</th>
                      <th class="px-3 py-2 text-right text-xs font-medium text-gray-400">Gesamt</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-dark-600">
                    <tr v-for="item in selectedInvoice.items" :key="item.id">
                      <td class="px-3 py-2 text-sm text-white">{{ item.description }}</td>
                      <td class="px-3 py-2 text-sm text-gray-400 text-right">{{ item.quantity }} {{ item.unit }}</td>
                      <td class="px-3 py-2 text-sm text-gray-400 text-right">{{ formatCurrency(item.unit_price) }}</td>
                      <td class="px-3 py-2 text-sm text-white text-right">{{ formatCurrency(item.total) }}</td>
                    </tr>
                    <tr v-if="!selectedInvoice.items?.length">
                      <td colspan="4" class="px-3 py-4 text-center text-gray-500">Keine Positionen</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Totals -->
            <div class="flex justify-end">
              <div class="w-64 space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-400">Netto</span>
                  <span class="text-white">{{ formatCurrency(selectedInvoice.subtotal) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-400">MwSt. ({{ selectedInvoice.tax_rate }}%)</span>
                  <span class="text-white">{{ formatCurrency(selectedInvoice.tax_amount) }}</span>
                </div>
                <div class="flex justify-between text-lg font-bold pt-2 border-t border-dark-600">
                  <span class="text-white">Gesamt</span>
                  <span class="text-white">{{ formatCurrency(selectedInvoice.total) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
