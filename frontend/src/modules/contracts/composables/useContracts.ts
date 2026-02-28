import { ref, computed, type Ref, type ComputedRef } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'

export type ContractType = 'license' | 'development' | 'saas' | 'maintenance' | 'nda'
export type ContractStatus = 'draft' | 'sent' | 'signed' | 'active' | 'expired' | 'cancelled' | 'terminated'
export type ContractLanguage = 'de' | 'en'

export interface Contract {
  id: string
  contract_number: string
  title: string
  contract_type: ContractType
  language: ContractLanguage
  status: ContractStatus
  content_html: string
  variables_data?: Record<string, unknown>
  client_id?: string
  client_name?: string
  template_id?: string
  party_a_name?: string
  party_a_company?: string
  party_a_address?: string
  party_a_email?: string
  party_a_vat_id?: string
  party_b_name?: string
  party_b_company?: string
  party_b_address?: string
  party_b_email?: string
  party_b_vat_id?: string
  start_date?: string
  end_date?: string
  auto_renewal?: number
  renewal_period?: string
  notice_period_days?: number
  total_value?: number
  currency?: string
  payment_schedule?: string
  governing_law?: string
  jurisdiction?: string
  is_b2c?: number
  include_nda_clause?: number
  party_a_signed_at?: string | null
  party_a_signature_data?: string | null
  party_b_signed_at?: string | null
  party_b_signature_data?: string | null
  notes?: string
  invoices?: LinkedInvoice[]
  created_at?: string
  updated_at?: string
  [key: string]: unknown
}

export interface LinkedInvoice {
  id: string
  invoice_number: string
  status: string
  total: number
  issue_date: string
  currency: string
}

export interface ContractTemplate {
  id: string
  name: string
  contract_type: ContractType
  language: ContractLanguage
  content_html: string
  variables?: ContractTemplateVariable[]
  is_default: number
  [key: string]: unknown
}

export interface ContractTemplateVariable {
  key: string
  label: string
  type: 'text' | 'number' | 'date' | 'select' | 'textarea' | 'checkbox'
  default?: string
  options?: string[]
}

export interface ContractStats {
  total_contracts: number
  draft: number
  sent: number
  signed_count: number
  active: number
  expired: number
  total_active_value: number
  total_value: number
  [key: string]: unknown
}

export interface ContractHistoryEntry {
  id: string
  action: string
  details: string
  performed_by_name?: string
  created_at: string
}

export interface StatusOption {
  value: ContractStatus
  label: string
  color: string
  textColor: string
  icon: string
}

export interface ContractFormData {
  title?: string
  contract_type?: ContractType
  language?: ContractLanguage
  content_html?: string
  variables_data?: Record<string, unknown>
  client_id?: string
  template_id?: string
  party_a_name?: string
  party_a_company?: string
  party_a_address?: string
  party_a_email?: string
  party_a_vat_id?: string
  party_b_name?: string
  party_b_company?: string
  party_b_address?: string
  party_b_email?: string
  party_b_vat_id?: string
  start_date?: string
  end_date?: string
  auto_renewal?: number
  renewal_period?: string
  notice_period_days?: number
  total_value?: number
  currency?: string
  payment_schedule?: string
  governing_law?: string
  jurisdiction?: string
  is_b2c?: number
  include_nda_clause?: number
  notes?: string
  [key: string]: unknown
}

export interface UseContractsReturn {
  contracts: Ref<Contract[]>
  templates: Ref<ContractTemplate[]>
  stats: Ref<ContractStats | null>
  isLoading: Ref<boolean>
  statusFilter: Ref<string>
  typeFilter: Ref<string>
  searchQuery: Ref<string>
  statusOptions: StatusOption[]
  contractTypeLabels: Record<ContractType, string>
  filteredContracts: ComputedRef<Contract[]>
  loadData: () => Promise<void>
  loadTemplates: (type?: ContractType, language?: ContractLanguage) => Promise<void>
  saveContract: (form: ContractFormData, editingId?: string | null) => Promise<Contract | null>
  deleteContract: (contract: Contract) => Promise<boolean>
  updateContractStatus: (contract: Contract, status: ContractStatus) => Promise<boolean>
  duplicateContract: (contract: Contract) => Promise<void>
  signContract: (contractId: string, party: 'a' | 'b', signatureData: string) => Promise<boolean>
  linkInvoice: (contractId: string, invoiceId: string) => Promise<boolean>
  unlinkInvoice: (contractId: string, invoiceId: string) => Promise<boolean>
  getHistory: (contractId: string) => Promise<ContractHistoryEntry[]>
  formatCurrency: (amount: number | undefined, currency?: string) => string
  formatDate: (dateStr: string | undefined) => string
  getStatusInfo: (status: string) => StatusOption
}

export function useContracts(): UseContractsReturn {
  const uiStore = useUiStore()
  const toast = useToast()
  const { confirm } = useConfirmDialog()

  const contracts = ref<Contract[]>([])
  const templates = ref<ContractTemplate[]>([])
  const stats = ref<ContractStats | null>(null)
  const isLoading = ref<boolean>(false)
  const statusFilter = ref<string>('')
  const typeFilter = ref<string>('')
  const searchQuery = ref<string>('')

  const statusOptions: StatusOption[] = [
    { value: 'draft', label: 'Entwurf', color: 'bg-gray-500', textColor: 'text-gray-300', icon: '✏️' },
    { value: 'sent', label: 'Versendet', color: 'bg-blue-500', textColor: 'text-blue-300', icon: '📤' },
    { value: 'signed', label: 'Unterschrieben', color: 'bg-emerald-500', textColor: 'text-emerald-300', icon: '✍️' },
    { value: 'active', label: 'Aktiv', color: 'bg-green-500', textColor: 'text-green-300', icon: '✅' },
    { value: 'expired', label: 'Abgelaufen', color: 'bg-amber-500', textColor: 'text-amber-300', icon: '⏰' },
    { value: 'cancelled', label: 'Storniert', color: 'bg-gray-600', textColor: 'text-gray-400', icon: '🚫' },
    { value: 'terminated', label: 'Gekuendigt', color: 'bg-red-500', textColor: 'text-red-300', icon: '❌' },
  ]

  const contractTypeLabels: Record<ContractType, string> = {
    license: 'Softwarelizenz',
    development: 'Entwicklung',
    saas: 'SaaS',
    maintenance: 'Wartung',
    nda: 'NDA',
  }

  const filteredContracts = computed<Contract[]>(() => {
    let list = contracts.value
    if (statusFilter.value) {
      list = list.filter((c: Contract) => c.status === statusFilter.value)
    }
    if (typeFilter.value) {
      list = list.filter((c: Contract) => c.contract_type === typeFilter.value)
    }
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim()
      list = list.filter((c: Contract) =>
        (c.contract_number || '').toLowerCase().includes(q) ||
        (c.title || '').toLowerCase().includes(q) ||
        (c.party_b_name || '').toLowerCase().includes(q) ||
        (c.party_b_company || '').toLowerCase().includes(q)
      )
    }
    return list
  })

  async function loadData(): Promise<void> {
    isLoading.value = true
    try {
      const [contractsRes, statsRes] = await Promise.all([
        api.get('/api/v1/contracts'),
        api.get('/api/v1/contracts/stats'),
      ])
      contracts.value = contractsRes.data.data?.items || []
      stats.value = statsRes.data.data
    } catch {
      uiStore.showError('Fehler beim Laden der Vertraege')
    } finally {
      isLoading.value = false
    }
  }

  async function loadTemplates(type?: ContractType, language?: ContractLanguage): Promise<void> {
    try {
      const params: Record<string, string> = {}
      if (type) params.contract_type = type
      if (language) params.language = language
      const res = await api.get('/api/v1/contract-templates', { params })
      templates.value = res.data.data?.items || []
    } catch {
      uiStore.showError('Fehler beim Laden der Vorlagen')
    }
  }

  async function saveContract(form: ContractFormData, editingId?: string | null): Promise<Contract | null> {
    try {
      let result: Contract
      if (editingId) {
        const res = await api.put(`/api/v1/contracts/${editingId}`, form)
        result = res.data.data
        uiStore.showSuccess('Vertrag aktualisiert')
      } else {
        const res = await api.post('/api/v1/contracts', form)
        result = res.data.data
        uiStore.showSuccess('Vertrag erstellt')
      }
      await loadData()
      return result
    } catch {
      uiStore.showError('Fehler beim Speichern')
      return null
    }
  }

  async function deleteContract(contract: Contract): Promise<boolean> {
    if (!await confirm({ message: `Vertrag ${contract.contract_number} wirklich loeschen?`, type: 'danger', confirmText: 'Loeschen' })) return false
    try {
      await api.delete(`/api/v1/contracts/${contract.id}`)
      contracts.value = contracts.value.filter((c: Contract) => c.id !== contract.id)
      uiStore.showSuccess('Vertrag geloescht')
      return true
    } catch {
      uiStore.showError('Fehler beim Loeschen')
      return false
    }
  }

  async function updateContractStatus(contract: Contract, status: ContractStatus): Promise<boolean> {
    try {
      await api.put(`/api/v1/contracts/${contract.id}/status`, { status })
      const idx = contracts.value.findIndex((c: Contract) => c.id === contract.id)
      if (idx !== -1) contracts.value[idx] = { ...contracts.value[idx], status }
      uiStore.showSuccess('Status aktualisiert')
      return true
    } catch {
      uiStore.showError('Fehler beim Aktualisieren')
      return false
    }
  }

  async function duplicateContract(contract: Contract): Promise<void> {
    try {
      await api.post(`/api/v1/contracts/${contract.id}/duplicate`)
      await loadData()
      uiStore.showSuccess('Vertrag dupliziert')
    } catch {
      uiStore.showError('Fehler beim Duplizieren')
    }
  }

  async function signContract(contractId: string, party: 'a' | 'b', signatureData: string): Promise<boolean> {
    try {
      await api.post(`/api/v1/contracts/${contractId}/sign`, { party, signature_data: signatureData })
      uiStore.showSuccess('Unterschrift gespeichert')
      return true
    } catch {
      uiStore.showError('Fehler beim Unterschreiben')
      return false
    }
  }

  async function linkInvoice(contractId: string, invoiceId: string): Promise<boolean> {
    try {
      await api.post(`/api/v1/contracts/${contractId}/invoices`, { invoice_id: invoiceId })
      uiStore.showSuccess('Rechnung verknuepft')
      return true
    } catch {
      uiStore.showError('Fehler beim Verknuepfen')
      return false
    }
  }

  async function unlinkInvoice(contractId: string, invoiceId: string): Promise<boolean> {
    try {
      await api.delete(`/api/v1/contracts/${contractId}/invoices/${invoiceId}`)
      uiStore.showSuccess('Verknuepfung entfernt')
      return true
    } catch {
      uiStore.showError('Fehler beim Entfernen')
      return false
    }
  }

  async function getHistory(contractId: string): Promise<ContractHistoryEntry[]> {
    try {
      const res = await api.get(`/api/v1/contracts/${contractId}/history`)
      return res.data.data?.items || []
    } catch {
      return []
    }
  }

  function formatCurrency(amount: number | undefined, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('de-DE', { style: 'currency', currency }).format(amount || 0)
  }

  function formatDate(dateStr: string | undefined): string {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleDateString('de-DE')
  }

  function getStatusInfo(status: string): StatusOption {
    return statusOptions.find((s: StatusOption) => s.value === status) || statusOptions[0]
  }

  return {
    contracts,
    templates,
    stats,
    isLoading,
    statusFilter,
    typeFilter,
    searchQuery,
    statusOptions,
    contractTypeLabels,
    filteredContracts,
    loadData,
    loadTemplates,
    saveContract,
    deleteContract,
    updateContractStatus,
    duplicateContract,
    signContract,
    linkInvoice,
    unlinkInvoice,
    getHistory,
    formatCurrency,
    formatDate,
    getStatusInfo,
  }
}
