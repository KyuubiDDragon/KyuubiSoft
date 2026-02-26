import { ref, computed } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'

export function useInvoices() {
  const uiStore = useUiStore()
  const toast = useToast()
  const { confirm } = useConfirmDialog()

  const invoices = ref([])
  const clients = ref([])
  const stats = ref(null)
  const isLoading = ref(false)
  const statusFilter = ref('')
  const searchQuery = ref('')
  const kleinunternehmerMode = ref(false)
  const invoiceSenderSettings = ref({})
  const pdfGenerating = ref(null)

  const statusOptions = [
    { value: 'draft', label: 'Entwurf', color: 'bg-gray-500', textColor: 'text-gray-300', icon: '✏️' },
    { value: 'sent', label: 'Gesendet', color: 'bg-blue-500', textColor: 'text-blue-300', icon: '📤' },
    { value: 'paid', label: 'Bezahlt', color: 'bg-green-500', textColor: 'text-green-300', icon: '✅' },
    { value: 'overdue', label: 'Überfällig', color: 'bg-red-500', textColor: 'text-red-300', icon: '⚠️' },
    { value: 'cancelled', label: 'Storniert', color: 'bg-gray-600', textColor: 'text-gray-400', icon: '🚫' },
  ]

  const filteredInvoices = computed(() => {
    let list = invoices.value
    if (statusFilter.value) {
      list = list.filter(i => i.status === statusFilter.value)
    }
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim()
      list = list.filter(i =>
        (i.invoice_number || '').toLowerCase().includes(q) ||
        (i.client_name || '').toLowerCase().includes(q) ||
        (i.client_company || '').toLowerCase().includes(q)
      )
    }
    return list
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
    } catch {
      uiStore.showError('Fehler beim Laden')
    } finally {
      isLoading.value = false
    }
  }

  async function loadSettings() {
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

  async function saveInvoice(form, editingId) {
    try {
      let result
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

  async function deleteInvoice(invoice) {
    if (!await confirm({ message: `Rechnung ${invoice.invoice_number} wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return false
    try {
      await api.delete(`/api/v1/invoices/${invoice.id}`)
      invoices.value = invoices.value.filter(i => i.id !== invoice.id)
      uiStore.showSuccess('Rechnung gelöscht')
      return true
    } catch {
      uiStore.showError('Fehler beim Löschen')
      return false
    }
  }

  async function updateInvoiceStatus(invoice, status) {
    try {
      await api.put(`/api/v1/invoices/${invoice.id}`, {
        status,
        paid_date: status === 'paid' ? new Date().toISOString().split('T')[0] : null,
      })
      const idx = invoices.value.findIndex(i => i.id === invoice.id)
      if (idx !== -1) invoices.value[idx] = { ...invoices.value[idx], status }
      uiStore.showSuccess('Status aktualisiert')
      return true
    } catch {
      uiStore.showError('Fehler beim Aktualisieren')
      return false
    }
  }

  async function duplicateInvoice(invoice) {
    try {
      await api.post(`/api/v1/invoices/${invoice.id}/duplicate`)
      await loadData()
      uiStore.showSuccess('Rechnung dupliziert')
    } catch {
      uiStore.showError('Fehler beim Duplizieren')
    }
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

  async function saveClient(form, editingId) {
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

  async function deleteClient(client) {
    if (!await confirm({ message: `Kunde "${client.name}" wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return false
    try {
      await api.delete(`/api/v1/clients/${client.id}`)
      clients.value = clients.value.filter(c => c.id !== client.id)
      uiStore.showSuccess('Kunde gelöscht')
      return true
    } catch {
      uiStore.showError('Fehler beim Löschen')
      return false
    }
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

  function escapeHtml(str) {
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
