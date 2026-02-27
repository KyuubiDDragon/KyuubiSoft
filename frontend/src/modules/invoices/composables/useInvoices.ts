import { ref, computed, type Ref, type ComputedRef } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'

/**
 * Invoice line item
 */
export interface InvoiceItem {
  description?: string
  quantity?: number
  unit?: string
  unit_price?: number
  total?: number
}

/**
 * Invoice data structure
 */
export interface Invoice {
  id: string
  invoice_number?: string
  client_name?: string
  client_company?: string
  status: InvoiceStatus
  items?: InvoiceItem[]
  subtotal?: number
  tax_rate?: string | number
  tax_amount?: number
  total?: number
  issue_date?: string
  due_date?: string
  paid_date?: string | null
  document_type?: string
  [key: string]: unknown
}

/**
 * Client data structure
 */
export interface Client {
  id: string
  name?: string
  company?: string
  email?: string
  address?: string
  vat_id?: string
  [key: string]: unknown
}

/**
 * Invoice status values
 */
export type InvoiceStatus = 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled'

/**
 * Status option for display
 */
export interface StatusOption {
  value: InvoiceStatus
  label: string
  color: string
  textColor: string
  icon: string
}

/**
 * Invoice statistics
 */
export interface InvoiceStats {
  total_revenue?: number
  outstanding?: number
  overdue_count?: number
  [key: string]: unknown
}

/**
 * Invoice sender settings
 */
export interface InvoiceSenderSettings {
  sender_name?: string
  sender_company?: string
  sender_address?: string
  sender_email?: string
  sender_phone?: string
  sender_vat_id?: string
  invoice_steuernummer?: string
  sender_bank_details?: string
  logo_file_id?: string | null
  default_payment_terms?: string
  [key: string]: unknown
}

/**
 * Invoice form data for creating/updating
 */
export interface InvoiceFormData {
  invoice_number?: string
  client_id?: string
  client_name?: string
  status?: InvoiceStatus
  items?: InvoiceItem[]
  [key: string]: unknown
}

/**
 * Client form data for creating/updating
 */
export interface ClientFormData {
  name?: string
  company?: string
  email?: string
  address?: string
  [key: string]: unknown
}

/**
 * Return type of the useInvoices composable
 */
export interface UseInvoicesReturn {
  invoices: Ref<Invoice[]>
  clients: Ref<Client[]>
  stats: Ref<InvoiceStats | null>
  isLoading: Ref<boolean>
  statusFilter: Ref<string>
  searchQuery: Ref<string>
  kleinunternehmerMode: Ref<boolean>
  invoiceSenderSettings: Ref<InvoiceSenderSettings>
  pdfGenerating: Ref<string | null>
  statusOptions: StatusOption[]
  filteredInvoices: ComputedRef<Invoice[]>
  loadData: () => Promise<void>
  loadSettings: () => Promise<void>
  saveInvoice: (form: InvoiceFormData, editingId?: string | null) => Promise<Invoice | null>
  deleteInvoice: (invoice: Invoice) => Promise<boolean>
  updateInvoiceStatus: (invoice: Invoice, status: InvoiceStatus) => Promise<boolean>
  duplicateInvoice: (invoice: Invoice) => Promise<void>
  toggleKleinunternehmer: () => Promise<void>
  saveClient: (form: ClientFormData, editingId?: string | null) => Promise<boolean>
  deleteClient: (client: Client) => Promise<boolean>
  formatCurrency: (amount: number | undefined) => string
  formatDate: (dateStr: string | undefined) => string
  getStatusInfo: (status: string) => StatusOption
  escapeHtml: (str: string | undefined | null) => string
}

export function useInvoices(): UseInvoicesReturn {
  const uiStore = useUiStore()
  const toast = useToast()
  const { confirm } = useConfirmDialog()

  const invoices = ref<Invoice[]>([])
  const clients = ref<Client[]>([])
  const stats = ref<InvoiceStats | null>(null)
  const isLoading = ref<boolean>(false)
  const statusFilter = ref<string>('')
  const searchQuery = ref<string>('')
  const kleinunternehmerMode = ref<boolean>(false)
  const invoiceSenderSettings = ref<InvoiceSenderSettings>({})
  const pdfGenerating = ref<string | null>(null)

  const statusOptions: StatusOption[] = [
    { value: 'draft', label: 'Entwurf', color: 'bg-gray-500', textColor: 'text-gray-300', icon: '\u270F\uFE0F' },
    { value: 'sent', label: 'Gesendet', color: 'bg-blue-500', textColor: 'text-blue-300', icon: '\uD83D\uDCE4' },
    { value: 'paid', label: 'Bezahlt', color: 'bg-green-500', textColor: 'text-green-300', icon: '\u2705' },
    { value: 'overdue', label: '\u00DCberf\u00E4llig', color: 'bg-red-500', textColor: 'text-red-300', icon: '\u26A0\uFE0F' },
    { value: 'cancelled', label: 'Storniert', color: 'bg-gray-600', textColor: 'text-gray-400', icon: '\uD83D\uDEAB' },
  ]

  const filteredInvoices = computed<Invoice[]>(() => {
    let list = invoices.value
    if (statusFilter.value) {
      list = list.filter((i: Invoice) => i.status === statusFilter.value)
    }
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim()
      list = list.filter((i: Invoice) =>
        (i.invoice_number || '').toLowerCase().includes(q) ||
        (i.client_name || '').toLowerCase().includes(q) ||
        (i.client_company || '').toLowerCase().includes(q)
      )
    }
    return list
  })

  async function loadData(): Promise<void> {
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
    } catch {
      uiStore.showError('Fehler beim Laden')
    } finally {
      isLoading.value = false
    }
  }

  async function loadSettings(): Promise<void> {
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
        logo_file_id: s.invoice_logo_file_id ?? null,
        default_payment_terms: s.invoice_default_payment_terms ?? 'Zahlbar innerhalb von 30 Tagen nach Rechnungsdatum.',
      }
    } catch {
      uiStore.showError('Einstellungen konnten nicht geladen werden')
    }
  }

  async function saveInvoice(form: InvoiceFormData, editingId?: string | null): Promise<Invoice | null> {
    try {
      let result: Invoice
      if (editingId) {
        const res = await api.put(`/api/v1/invoices/${editingId}`, form)
        result = res.data.data
        uiStore.showSuccess('Rechnung aktualisiert')
      } else {
        const res = await api.post('/api/v1/invoices', form)
        result = res.data.data
        uiStore.showSuccess('Rechnung erstellt')
      }
      await loadData()
      return result
    } catch {
      uiStore.showError('Fehler beim Speichern')
      return null
    }
  }

  async function deleteInvoice(invoice: Invoice): Promise<boolean> {
    if (!await confirm({ message: `Rechnung ${invoice.invoice_number} wirklich l\u00F6schen?`, type: 'danger', confirmText: 'L\u00F6schen' })) return false
    try {
      await api.delete(`/api/v1/invoices/${invoice.id}`)
      invoices.value = invoices.value.filter((i: Invoice) => i.id !== invoice.id)
      uiStore.showSuccess('Rechnung gel\u00F6scht')
      return true
    } catch {
      uiStore.showError('Fehler beim L\u00F6schen')
      return false
    }
  }

  async function updateInvoiceStatus(invoice: Invoice, status: InvoiceStatus): Promise<boolean> {
    try {
      await api.put(`/api/v1/invoices/${invoice.id}`, {
        status,
        paid_date: status === 'paid' ? new Date().toISOString().split('T')[0] : null,
      })
      const idx = invoices.value.findIndex((i: Invoice) => i.id === invoice.id)
      if (idx !== -1) invoices.value[idx] = { ...invoices.value[idx], status }
      uiStore.showSuccess('Status aktualisiert')
      return true
    } catch {
      uiStore.showError('Fehler beim Aktualisieren')
      return false
    }
  }

  async function duplicateInvoice(invoice: Invoice): Promise<void> {
    try {
      await api.post(`/api/v1/invoices/${invoice.id}/duplicate`)
      await loadData()
      uiStore.showSuccess('Rechnung dupliziert')
    } catch {
      uiStore.showError('Fehler beim Duplizieren')
    }
  }

  async function toggleKleinunternehmer(): Promise<void> {
    kleinunternehmerMode.value = !kleinunternehmerMode.value
    try {
      await api.put('/api/v1/settings/user', { kleinunternehmer_mode: kleinunternehmerMode.value })
      uiStore.showSuccess(kleinunternehmerMode.value
        ? 'Kleinunternehmer-Modus aktiviert (\u00A7 19 UStG)'
        : 'Kleinunternehmer-Modus deaktiviert')
    } catch {
      uiStore.showError('Einstellung konnte nicht gespeichert werden')
    }
  }

  async function saveClient(form: ClientFormData, editingId?: string | null): Promise<boolean> {
    if (!form.name?.trim()) {
      uiStore.showError('Name ist erforderlich')
      return false
    }
    try {
      if (editingId) {
        await api.put(`/api/v1/clients/${editingId}`, form)
        uiStore.showSuccess('Kunde aktualisiert')
      } else {
        await api.post('/api/v1/clients', form)
        uiStore.showSuccess('Kunde erstellt')
      }
      await loadData()
      return true
    } catch {
      uiStore.showError('Fehler beim Speichern')
      return false
    }
  }

  async function deleteClient(client: Client): Promise<boolean> {
    if (!await confirm({ message: `Kunde "${client.name}" wirklich l\u00F6schen?`, type: 'danger', confirmText: 'L\u00F6schen' })) return false
    try {
      await api.delete(`/api/v1/clients/${client.id}`)
      clients.value = clients.value.filter((c: Client) => c.id !== client.id)
      uiStore.showSuccess('Kunde gel\u00F6scht')
      return true
    } catch {
      uiStore.showError('Fehler beim L\u00F6schen')
      return false
    }
  }

  function formatCurrency(amount: number | undefined): string {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(amount || 0)
  }

  function formatDate(dateStr: string | undefined): string {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleDateString('de-DE')
  }

  function getStatusInfo(status: string): StatusOption {
    return statusOptions.find((s: StatusOption) => s.value === status) || statusOptions[0]
  }

  function escapeHtml(str: string | undefined | null): string {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')
  }

  return {
    invoices,
    clients,
    stats,
    isLoading,
    statusFilter,
    searchQuery,
    kleinunternehmerMode,
    invoiceSenderSettings,
    pdfGenerating,
    statusOptions,
    filteredInvoices,
    loadData,
    loadSettings,
    saveInvoice,
    deleteInvoice,
    updateInvoiceStatus,
    duplicateInvoice,
    toggleKleinunternehmer,
    saveClient,
    deleteClient,
    formatCurrency,
    formatDate,
    getStatusInfo,
    escapeHtml,
  }
}
