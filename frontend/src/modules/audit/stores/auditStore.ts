import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'

export interface AuditEntry {
  id: string
  user_id: string
  user_name?: string
  user_email?: string
  action: string
  entity_type?: string
  entity_id?: string
  entity_name?: string
  ip_address?: string
  user_agent?: string
  details?: Record<string, unknown>
  old_values?: Record<string, unknown>
  new_values?: Record<string, unknown>
  created_at: string
  [key: string]: unknown
}

export interface AuditStats {
  total_today: number
  unique_users: number
  most_common_action: string
  total_entries: number
  [key: string]: unknown
}

export interface AuditFilters {
  user_id: string
  action: string
  entity_type: string
  date_from: string
  date_to: string
  ip_address: string
  search: string
}

export interface AuditPagination {
  page: number
  perPage: number
  total: number
}

export const useAuditStore = defineStore('audit', () => {
  const uiStore = useUiStore()

  // State
  const entries = ref<AuditEntry[]>([])
  const currentEntry = ref<AuditEntry | null>(null)
  const stats = ref<AuditStats>({
    total_today: 0,
    unique_users: 0,
    most_common_action: '-',
    total_entries: 0,
  })
  const loading = ref<boolean>(false)

  const filters = ref<AuditFilters>({
    user_id: '',
    action: '',
    entity_type: '',
    date_from: '',
    date_to: '',
    ip_address: '',
    search: '',
  })

  const pagination = ref<AuditPagination>({
    page: 1,
    perPage: 25,
    total: 0,
  })

  // Getters
  const totalPages = computed(() =>
    Math.ceil(pagination.value.total / pagination.value.perPage) || 1
  )

  const hasActiveFilters = computed(() =>
    !!(filters.value.user_id ||
      filters.value.action ||
      filters.value.entity_type ||
      filters.value.date_from ||
      filters.value.date_to ||
      filters.value.ip_address ||
      filters.value.search)
  )

  // Actions
  async function fetchEntries(): Promise<void> {
    loading.value = true
    try {
      const params: Record<string, unknown> = {
        page: pagination.value.page,
        per_page: pagination.value.perPage,
      }

      if (filters.value.user_id) params.user_id = filters.value.user_id
      if (filters.value.action) params.action = filters.value.action
      if (filters.value.entity_type) params.entity_type = filters.value.entity_type
      if (filters.value.date_from) params.date_from = filters.value.date_from
      if (filters.value.date_to) params.date_to = filters.value.date_to
      if (filters.value.ip_address) params.ip_address = filters.value.ip_address
      if (filters.value.search) params.search = filters.value.search

      const response = await api.get('/api/v1/audit', { params })
      const data = response.data.data

      entries.value = data?.items || []
      pagination.value.total = data?.pagination?.total || data?.total || 0
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Audit-Einträge')
    } finally {
      loading.value = false
    }
  }

  async function fetchEntry(id: string): Promise<void> {
    loading.value = true
    try {
      const response = await api.get(`/api/v1/audit/${id}`)
      currentEntry.value = response.data.data
    } catch (error) {
      uiStore.showError('Fehler beim Laden des Audit-Eintrags')
    } finally {
      loading.value = false
    }
  }

  async function fetchStats(): Promise<void> {
    try {
      const response = await api.get('/api/v1/audit/stats')
      stats.value = response.data.data || stats.value
    } catch (error) {
      // Stats are optional, don't show error
      console.error('Failed to load audit stats:', error)
    }
  }

  async function fetchEntityHistory(type: string, id: string): Promise<AuditEntry[]> {
    try {
      const response = await api.get(`/api/v1/audit/entity/${type}/${id}`)
      return response.data.data?.items || []
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Entitäts-Historie')
      return []
    }
  }

  async function exportCsv(): Promise<void> {
    try {
      const params: Record<string, unknown> = {}

      if (filters.value.user_id) params.user_id = filters.value.user_id
      if (filters.value.action) params.action = filters.value.action
      if (filters.value.entity_type) params.entity_type = filters.value.entity_type
      if (filters.value.date_from) params.date_from = filters.value.date_from
      if (filters.value.date_to) params.date_to = filters.value.date_to
      if (filters.value.ip_address) params.ip_address = filters.value.ip_address
      if (filters.value.search) params.search = filters.value.search

      const response = await api.get('/api/v1/audit/export', {
        params,
        responseType: 'blob',
      })

      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `audit-log-${new Date().toISOString().split('T')[0]}.csv`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)

      uiStore.showSuccess('Audit-Log exportiert')
    } catch (error) {
      uiStore.showError('Fehler beim Exportieren des Audit-Logs')
    }
  }

  function setFilter(key: keyof AuditFilters, value: string): void {
    filters.value[key] = value
    pagination.value.page = 1
  }

  function resetFilters(): void {
    filters.value = {
      user_id: '',
      action: '',
      entity_type: '',
      date_from: '',
      date_to: '',
      ip_address: '',
      search: '',
    }
    pagination.value.page = 1
  }

  function setPage(page: number): void {
    pagination.value.page = page
  }

  return {
    // State
    entries,
    currentEntry,
    stats,
    loading,
    filters,
    pagination,

    // Getters
    totalPages,
    hasActiveFilters,

    // Actions
    fetchEntries,
    fetchEntry,
    fetchStats,
    fetchEntityHistory,
    exportCsv,
    setFilter,
    resetFilters,
    setPage,
  }
})
