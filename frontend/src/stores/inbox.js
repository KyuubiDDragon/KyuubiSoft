import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const useInboxStore = defineStore('inbox', () => {
  const items = ref([])
  const stats = ref({ total: 0, inbox: 0, processing: 0, done: 0, urgent: 0 })
  const loading = ref(false)
  const error = ref(null)
  const filters = ref({
    status: null,
    priority: null,
    search: '',
    sort: 'newest'
  })

  // Computed
  const filteredItems = computed(() => {
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

  const inboxCount = computed(() => stats.value.inbox)
  const urgentCount = computed(() => stats.value.urgent)

  // Actions
  async function fetchItems(customFilters = {}) {
    loading.value = true
    error.value = null
    try {
      const params = { ...filters.value, ...customFilters }
      const response = await api.get('/api/v1/inbox', { params })
      items.value = response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch inbox items'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchStats() {
    try {
      const response = await api.get('/api/v1/inbox/stats')
      stats.value = response.data
    } catch (err) {
      console.error('Failed to fetch inbox stats:', err)
    }
  }

  async function capture(data) {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/api/v1/inbox', data)
      items.value.unshift(response.data)
      await fetchStats()
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to capture item'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateItem(id, data) {
    try {
      const response = await api.put(`/api/v1/inbox/${id}`, data)
      const index = items.value.findIndex(item => item.id === id)
      if (index !== -1) {
        items.value[index] = response.data
      }
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update item'
      throw err
    }
  }

  async function deleteItem(id) {
    try {
      await api.delete(`/api/v1/inbox/${id}`)
      items.value = items.value.filter(item => item.id !== id)
      await fetchStats()
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete item'
      throw err
    }
  }

  async function moveToModule(id, targetType, targetId = null, options = {}) {
    try {
      const response = await api.post(`/api/v1/inbox/${id}/move`, {
        target_type: targetType,
        target_id: targetId,
        options
      })
      // Remove from inbox list
      items.value = items.value.filter(item => item.id !== id)
      await fetchStats()
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to move item'
      throw err
    }
  }

  async function bulkAction(ids, action, options = {}) {
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
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to perform bulk action'
      throw err
    }
  }

  function setFilter(key, value) {
    filters.value[key] = value
  }

  function clearFilters() {
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
