import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

interface NavItem {
  id: string
  name: string
  href: string
  icon: string
  [key: string]: unknown
}

interface QuickAccessItem {
  nav_id: string
  nav_name: string
  nav_href: string
  nav_icon: string
  [key: string]: unknown
}

interface ReorderItem {
  nav_id: string
  position: number
}

export const useQuickAccessStore = defineStore('quickAccess', () => {
  // State
  const items = ref<QuickAccessItem[]>([])
  const maxVisible = ref<number>(5)
  const isLoading = ref<boolean>(false)
  const isInitialized = ref<boolean>(false)

  // Getters
  const count = computed<number>(() => items.value.length)

  const visibleItems = computed<QuickAccessItem[]>(() => {
    return items.value.slice(0, maxVisible.value)
  })

  const overflowItems = computed<QuickAccessItem[]>(() => {
    return items.value.slice(maxVisible.value)
  })

  const hasOverflow = computed<boolean>(() => {
    return items.value.length > maxVisible.value
  })

  // Actions
  async function load(): Promise<void> {
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

  async function toggle(navItem: NavItem): Promise<boolean> {
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

  async function add(navItem: NavItem): Promise<void> {
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

  async function remove(navId: string): Promise<void> {
    try {
      await api.delete(`/api/v1/quick-access/${navId}`)
      items.value = items.value.filter(item => item.nav_id !== navId)
    } catch (error) {
      console.error('Failed to remove quick access:', error)
      throw error
    }
  }

  async function reorder(order: ReorderItem[]): Promise<void> {
    try {
      await api.put('/api/v1/quick-access/reorder', { order })
    } catch (error) {
      console.error('Failed to reorder quick access:', error)
      throw error
    }
  }

  async function updateMaxVisible(value: number): Promise<void> {
    try {
      await api.put('/api/v1/quick-access/settings', { max_visible: value })
      maxVisible.value = value
    } catch (error) {
      console.error('Failed to update max visible:', error)
      throw error
    }
  }

  function isPinned(navId: string): boolean {
    return items.value.some(item => item.nav_id === navId)
  }

  function reset(): void {
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
