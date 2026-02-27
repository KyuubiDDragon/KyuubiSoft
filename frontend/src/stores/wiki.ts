import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Ref, ComputedRef } from 'vue'
import api from '@/core/api/axios'

// Interfaces
interface WikiPage {
  id: string
  title: string
  slug: string
  content: string
  is_pinned: boolean
  is_published: boolean
  parent_id: string | null
  category_id: string | null
  [key: string]: unknown
}

interface WikiCategory {
  id: string
  name: string
  [key: string]: unknown
}

interface GraphNode {
  id: string
  [key: string]: unknown
}

interface GraphEdge {
  source: string
  target: string
  [key: string]: unknown
}

interface GraphData {
  nodes: GraphNode[]
  edges: GraphEdge[]
}

interface PageHistoryEntry {
  id: string
  [key: string]: unknown
}

interface PageFilters {
  category_id?: string
  parent_id?: string
  root_only?: boolean
  search?: string
  is_published?: boolean
}

interface ApiError {
  response?: {
    data?: {
      error?: string
    }
  }
}

export const useWikiStore = defineStore('wiki', () => {
  // State
  const pages: Ref<WikiPage[]> = ref<WikiPage[]>([])
  const currentPage: Ref<WikiPage | null> = ref<WikiPage | null>(null)
  const categories: Ref<WikiCategory[]> = ref<WikiCategory[]>([])
  const graphData: Ref<GraphData> = ref<GraphData>({ nodes: [], edges: [] })
  const recentPages: Ref<WikiPage[]> = ref<WikiPage[]>([])
  const searchResults: Ref<WikiPage[]> = ref<WikiPage[]>([])
  const pageHistory: Ref<PageHistoryEntry[]> = ref<PageHistoryEntry[]>([])
  const loading: Ref<boolean> = ref<boolean>(false)
  const error: Ref<string | null> = ref<string | null>(null)

  // Computed
  const pinnedPages: ComputedRef<WikiPage[]> = computed<WikiPage[]>(() => pages.value.filter(p => p.is_pinned))
  const publishedPages: ComputedRef<WikiPage[]> = computed<WikiPage[]>(() => pages.value.filter(p => p.is_published))
  const rootPages: ComputedRef<WikiPage[]> = computed<WikiPage[]>(() => pages.value.filter(p => !p.parent_id))

  // Actions
  async function fetchPages(filters: PageFilters = {}): Promise<WikiPage[]> {
    loading.value = true
    error.value = null
    try {
      const params = new URLSearchParams()
      if (filters.category_id) params.append('category_id', filters.category_id)
      if (filters.parent_id) params.append('parent_id', filters.parent_id)
      if (filters.root_only) params.append('root_only', 'true')
      if (filters.search) params.append('search', filters.search)
      if (filters.is_published !== undefined) params.append('is_published', filters.is_published.toString())

      const response = await api.get(`/api/v1/wiki/pages?${params}`)
      pages.value = response.data.data || []
      return pages.value
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to fetch pages'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchPage(identifier: string): Promise<WikiPage> {
    loading.value = true
    error.value = null
    try {
      console.log('Fetching page:', identifier)
      const response = await api.get(`/api/v1/wiki/pages/${identifier}`)
      console.log('API Response:', response.data)
      currentPage.value = response.data.data
      console.log('Current page set to:', currentPage.value)
      return currentPage.value as WikiPage
    } catch (err: unknown) {
      console.error('Fetch page error:', err)
      error.value = (err as ApiError).response?.data?.error || 'Failed to fetch page'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createPage(pageData: Partial<WikiPage>): Promise<WikiPage> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/api/v1/wiki/pages', pageData)
      const newPage: WikiPage = response.data.data
      pages.value.unshift(newPage)
      currentPage.value = newPage
      return newPage
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to create page'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updatePage(pageId: string, pageData: Partial<WikiPage>): Promise<WikiPage> {
    loading.value = true
    error.value = null
    try {
      const response = await api.put(`/api/v1/wiki/pages/${pageId}`, pageData)
      const updatedPage: WikiPage = response.data.data
      const index = pages.value.findIndex(p => p.id === pageId)
      if (index !== -1) {
        pages.value[index] = updatedPage
      }
      if (currentPage.value?.id === pageId) {
        currentPage.value = updatedPage
      }
      return updatedPage
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to update page'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deletePage(pageId: string): Promise<void> {
    loading.value = true
    error.value = null
    try {
      await api.delete(`/api/v1/wiki/pages/${pageId}`)
      pages.value = pages.value.filter(p => p.id !== pageId)
      if (currentPage.value?.id === pageId) {
        currentPage.value = null
      }
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to delete page'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchPageHistory(pageId: string): Promise<PageHistoryEntry[]> {
    loading.value = true
    error.value = null
    try {
      const response = await api.get(`/api/v1/wiki/pages/${pageId}/history`)
      pageHistory.value = response.data.data || []
      return pageHistory.value
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to fetch history'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function restoreFromHistory(pageId: string, historyId: string): Promise<WikiPage> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post(`/api/v1/wiki/pages/${pageId}/restore/${historyId}`)
      const restoredPage: WikiPage = response.data.data
      if (currentPage.value?.id === pageId) {
        currentPage.value = restoredPage
      }
      return restoredPage
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to restore version'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Categories
  async function fetchCategories(): Promise<WikiCategory[]> {
    loading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/wiki/categories')
      categories.value = response.data.data || []
      return categories.value
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to fetch categories'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createCategory(categoryData: Partial<WikiCategory>): Promise<WikiCategory> {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/api/v1/wiki/categories', categoryData)
      const newCategory: WikiCategory = response.data.data
      categories.value.push(newCategory)
      return newCategory
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to create category'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateCategory(categoryId: string, categoryData: Partial<WikiCategory>): Promise<WikiCategory> {
    loading.value = true
    error.value = null
    try {
      const response = await api.put(`/api/v1/wiki/categories/${categoryId}`, categoryData)
      const updatedCategory: WikiCategory = response.data.data
      const index = categories.value.findIndex(c => c.id === categoryId)
      if (index !== -1) {
        categories.value[index] = updatedCategory
      }
      return updatedCategory
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to update category'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteCategory(categoryId: string): Promise<void> {
    loading.value = true
    error.value = null
    try {
      await api.delete(`/api/v1/wiki/categories/${categoryId}`)
      categories.value = categories.value.filter(c => c.id !== categoryId)
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to delete category'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Graph & Search
  async function fetchGraphData(): Promise<GraphData> {
    loading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/wiki/graph')
      graphData.value = response.data.data || { nodes: [], edges: [] }
      return graphData.value
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to fetch graph data'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function searchPages(query: string): Promise<WikiPage[]> {
    loading.value = true
    error.value = null
    try {
      const response = await api.get(`/api/v1/wiki/search?q=${encodeURIComponent(query)}`)
      searchResults.value = response.data.data || []
      return searchResults.value
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Search failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchRecentPages(limit: number = 10): Promise<WikiPage[]> {
    loading.value = true
    error.value = null
    try {
      const response = await api.get(`/api/v1/wiki/pages/recent?limit=${limit}`)
      recentPages.value = response.data.data || []
      return recentPages.value
    } catch (err: unknown) {
      error.value = (err as ApiError).response?.data?.error || 'Failed to fetch recent pages'
      throw err
    } finally {
      loading.value = false
    }
  }

  function clearCurrentPage(): void {
    currentPage.value = null
    pageHistory.value = []
  }

  function clearSearch(): void {
    searchResults.value = []
  }

  return {
    // State
    pages,
    currentPage,
    categories,
    graphData,
    recentPages,
    searchResults,
    pageHistory,
    loading,
    error,

    // Computed
    pinnedPages,
    publishedPages,
    rootPages,

    // Actions
    fetchPages,
    fetchPage,
    createPage,
    updatePage,
    deletePage,
    fetchPageHistory,
    restoreFromHistory,
    fetchCategories,
    createCategory,
    updateCategory,
    deleteCategory,
    fetchGraphData,
    searchPages,
    fetchRecentPages,
    clearCurrentPage,
    clearSearch
  }
})
