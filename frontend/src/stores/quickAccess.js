import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const useQuickAccessStore = defineStore('quickAccess', () => {
  // State
  const items = ref([])
  const maxVisible = ref(5)
  const isLoading = ref(false)
  const isInitialized = ref(false)

  // Getters
  const count = computed(() => items.value.length)

  const visibleItems = computed(() => {
    return items.value.slice(0, maxVisible.value)
  })

  const overflowItems = computed(() => {
    return items.value.slice(maxVisible.value)
  })

  const hasOverflow = computed(() => {
    return items.value.length > maxVisible.value
  })

  // Actions
  async function load() {
    if (isLoading.value) return

    isLoading.value = true
    try {
      const response = await api.get('/api/v1/quick-access')
      items.value = response.data.data.items || []
      maxVisible.value = response.data.data.max_visible || 5
      isInitialized.value = true
    } catch (error) {
      console.error('Failed to load quick access:', error)
    } finally {
      isLoading.value = false
    }
  }

  async function toggle(navItem) {
    try {
      const response = await api.post('/api/v1/quick-access/toggle', {
        nav_id: navItem.id,
        nav_name: navItem.name,
        nav_href: navItem.href,
        nav_icon: navItem.icon,
      })
      await load()
      return response.data.data.is_pinned
    } catch (error) {
      console.error('Failed to toggle quick access:', error)
      throw error
    }
  }

  async function add(navItem) {
    try {
      await api.post('/api/v1/quick-access', {
        nav_id: navItem.id,
        nav_name: navItem.name,
        nav_href: navItem.href,
        nav_icon: navItem.icon,
      })
      await load()
    } catch (error) {
      console.error('Failed to add quick access:', error)
      throw error
    }
  }

  async function remove(navId) {
    try {
      await api.delete(`/api/v1/quick-access/${navId}`)
      items.value = items.value.filter(item => item.nav_id !== navId)
    } catch (error) {
      console.error('Failed to remove quick access:', error)
      throw error
    }
  }

  async function reorder(order) {
    try {
      await api.put('/api/v1/quick-access/reorder', { order })
    } catch (error) {
      console.error('Failed to reorder quick access:', error)
      throw error
    }
  }

  async function updateMaxVisible(value) {
    try {
      await api.put('/api/v1/quick-access/settings', { max_visible: value })
      maxVisible.value = value
    } catch (error) {
      console.error('Failed to update max visible:', error)
      throw error
    }
  }

  function isPinned(navId) {
    return items.value.some(item => item.nav_id === navId)
  }

  function reset() {
    items.value = []
    maxVisible.value = 5
    isInitialized.value = false
  }

  return {
    // State
    items,
    maxVisible,
    isLoading,
    isInitialized,

    // Getters
    count,
    visibleItems,
    overflowItems,
    hasOverflow,

    // Actions
    load,
    toggle,
    add,
    remove,
    reorder,
    updateMaxVisible,
    isPinned,
    reset,
  }
})
