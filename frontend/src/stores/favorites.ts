import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

interface FavoriteItem {
  item_type: string
  item_id: string
  [key: string]: unknown
}

interface ReorderItem {
  item_type: string
  item_id: string
  position: number
}

export const useFavoritesStore = defineStore('favorites', () => {
  // State
  const favorites = ref<FavoriteItem[]>([])
  const isLoading = ref<boolean>(false)
  const isInitialized = ref<boolean>(false)

  // Getters
  const count = computed<number>(() => favorites.value.length)

  // Actions
  async function load(): Promise<void> {
    if (isLoading.value) return

    isLoading.value = true
    try {
      const response = await api.get('/api/v1/favorites')
      favorites.value = response.data.data.items || []
      isInitialized.value = true
    } catch (error) {
      console.error('Failed to load favorites:', error)
    } finally {
      isLoading.value = false
    }
  }

  async function toggle(itemType: string, itemId: string): Promise<boolean> {
    try {
      const response = await api.post('/api/v1/favorites/toggle', {
        item_type: itemType,
        item_id: itemId,
      })
      // Reload to get updated list with item details
      await load()
      return response.data.data.is_favorite
    } catch (error) {
      console.error('Failed to toggle favorite:', error)
      throw error
    }
  }

  async function add(itemType: string, itemId: string): Promise<void> {
    try {
      await api.post('/api/v1/favorites', {
        item_type: itemType,
        item_id: itemId,
      })
      await load()
    } catch (error) {
      console.error('Failed to add favorite:', error)
      throw error
    }
  }

  async function remove(itemType: string, itemId: string): Promise<void> {
    try {
      await api.delete(`/api/v1/favorites/${itemType}/${itemId}`)
      favorites.value = favorites.value.filter(
        f => !(f.item_type === itemType && f.item_id === itemId)
      )
    } catch (error) {
      console.error('Failed to remove favorite:', error)
      throw error
    }
  }

  async function reorder(order: ReorderItem[]): Promise<void> {
    try {
      await api.put('/api/v1/favorites/reorder', { order })
    } catch (error) {
      console.error('Failed to reorder favorites:', error)
      throw error
    }
  }

  function isFavorite(itemType: string, itemId: string): boolean {
    return favorites.value.some(
      f => f.item_type === itemType && f.item_id === itemId
    )
  }

  function getFavoritesByType(type: string): FavoriteItem[] {
    return favorites.value.filter(f => f.item_type === type)
  }

  // Get icon for item type
  function getItemIcon(type: string): string {
    const icons: Record<string, string> = {
      list: 'ListBulletIcon',
      document: 'DocumentTextIcon',
      kanban_board: 'ViewColumnsIcon',
      project: 'FolderIcon',
      checklist: 'ClipboardDocumentCheckIcon',
      snippet: 'CodeBracketIcon',
      bookmark_group: 'BookmarkIcon',
      connection: 'ServerIcon',
    }
    return icons[type] || 'StarIcon'
  }

  // Get route for item
  function getItemRoute(favorite: FavoriteItem): string {
    const routes: Record<string, string> = {
      list: `/lists/${favorite.item_id}`,
      document: `/documents/${favorite.item_id}`,
      kanban_board: `/kanban/${favorite.item_id}`,
      project: `/projects/${favorite.item_id}`,
      checklist: `/checklists/${favorite.item_id}`,
      snippet: `/snippets/${favorite.item_id}`,
      bookmark_group: `/bookmarks?group=${favorite.item_id}`,
      connection: `/connections/${favorite.item_id}`,
    }
    return routes[favorite.item_type] || '/'
  }

  return {
    // State
    favorites,
    isLoading,
    isInitialized,

    // Getters
    count,

    // Actions
    load,
    toggle,
    add,
    remove,
    reorder,
    isFavorite,
    getFavoritesByType,
    getItemIcon,
    getItemRoute,
  }
})
