import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

interface InboxItem {
  id: string
  content: string
  note?: string
  status?: string
  priority?: string
  [key: string]: unknown
}

interface InboxStats {
  total: number
  inbox: number
  processing: number
  done: number
  urgent: number
}

interface InboxFilters {
  status: string | null
  priority: string | null
  search: string
  sort: string
}

interface MoveOptions {
  [key: string]: unknown
}

interface BulkOptions {
  [key: string]: unknown
}

export const useInboxStore = defineStore('inbox', () => {
  const items = ref<InboxItem[]>([])
  const stats = ref<InboxStats>({ total: 0, inbox: 0, processing: 0, done: 0, urgent: 0 })
  const loading = ref<boolean>(false)
  const error = ref<string | null>(null)
  const filters = ref<InboxFilters>({
    status: null,
    priority: null,
    search: '',
    sort: 'newest'
  })

  // Computed
  const filteredItems = computed<InboxItem[]>(() => {
    let result = [...items.value]

    if (filters.value.search) {
      const search = filters.value.search.toLowerCase()
      result = result.filter(item =>
        item.content.toLowerCase().includes(search) ||
        (item.note && item.note.toLowerCase().includes(search))
      )
    }

    return result
  })

  const inboxCount = computed<number>(() => stats.value.inbox)
  const urgentCount = computed<number>(() => stats.value.urgent)

  // Actions
  async function fetchItems(customFilters: Record<string, unknown> = {}): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const params = { ...filters.value, ...customFilters }
      const response = await api.get('/api/v1/inbox', { params })
      items.value = response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to fetch inbox items'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchStats(): Promise<void> {
    try {
      const response = await api.get('/api/v1/inbox/stats')
      stats.value = response.data.data
    } catch (err: unknown) {
      console.error('Failed to fetch inbox stats:', err)
    }
  }

  async function capture(data: Record<string, unknown>): Promise<InboxItem> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/api/v1/inbox', data)
      items.value.unshift(response.data.data)
      await fetchStats()
      return response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to capture item'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateItem(id: string, data: Record<string, unknown>): Promise<InboxItem> {
    try {
      const response = await api.put(`/api/v1/inbox/${id}`, data)
      const index = items.value.findIndex(item => item.id === id)
      if (index !== -1) {
        items.value[index] = response.data.data
      }
      return response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to update item'
      throw err
    }
  }

  async function deleteItem(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/inbox/${id}`)
      items.value = items.value.filter(item => item.id !== id)
      await fetchStats()
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to delete item'
      throw err
    }
  }

  async function moveToModule(id: string, targetType: string, targetId: string | null = null, options: MoveOptions = {}): Promise<unknown> {
    try {
      const response = await api.post(`/api/v1/inbox/${id}/move`, {
        target_type: targetType,
        target_id: targetId,
        options
      })
      // Remove from inbox list
      items.value = items.value.filter(item => item.id !== id)
      await fetchStats()
      return response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to move item'
      throw err
    }
  }

  async function bulkAction(ids: string[], action: string, options: BulkOptions = {}): Promise<unknown> {
    try {
      const response = await api.post('/api/v1/inbox/bulk', {
        ids,
        action,
        ...options
      })
      if (action === 'delete' || action === 'archive' || action === 'move') {
        items.value = items.value.filter(item => !ids.includes(item.id))
      }
      await fetchStats()
      return response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to perform bulk action'
      throw err
    }
  }

  function setFilter(key: keyof InboxFilters, value: string | null): void {
    ;(filters.value[key] as string | null) = value
  }

  function clearFilters(): void {
    filters.value = {
      status: null,
      priority: null,
      search: '',
      sort: 'newest'
    }
  }

  return {
    // State
    items,
    stats,
    loading,
    error,
    filters,
    // Computed
    filteredItems,
    inboxCount,
    urgentCount,
    // Actions
    fetchItems,
    fetchStats,
    capture,
    updateItem,
    deleteItem,
    moveToModule,
    bulkAction,
    setFilter,
    clearFilters
  }
})
