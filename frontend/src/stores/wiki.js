import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const useWikiStore = defineStore('wiki', () => {
  // State
  const pages = ref([])
  const currentPage = ref(null)
  const categories = ref([])
  const graphData = ref({ nodes: [], edges: [] })
  const recentPages = ref([])
  const searchResults = ref([])
  const pageHistory = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Computed
  const pinnedPages = computed(() => pages.value.filter(p => p.is_pinned))
  const publishedPages = computed(() => pages.value.filter(p => p.is_published))
  const rootPages = computed(() => pages.value.filter(p => !p.parent_id))

  // Actions
  async function fetchPages(filters = {}) {
    loading.value = true
    error.value = null
    try {
      const params = new URLSearchParams()
      if (filters.category_id) params.append('category_id', filters.category_id)
      if (filters.parent_id) params.append('parent_id', filters.parent_id)
      if (filters.root_only) params.append('root_only', 'true')
      if (filters.search) params.append('search', filters.search)
      if (filters.is_published !== undefined) params.append('is_published', filters.is_published.toString())

      const { data } = await api.get(`/api/v1/wiki/pages?${params}`)
      pages.value = data
      return data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch pages'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchPage(identifier) {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get(`/api/v1/wiki/pages/${identifier}`)
      currentPage.value = data
      return data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch page'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createPage(pageData) {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.post('/api/v1/wiki/pages', pageData)
      pages.value.unshift(data)
      currentPage.value = data
      return data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create page'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updatePage(pageId, pageData) {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.put(`/api/v1/wiki/pages/${pageId}`, pageData)
      const index = pages.value.findIndex(p => p.id === pageId)
      if (index !== -1) {
        pages.value[index] = data
      }
      if (currentPage.value?.id === pageId) {
        currentPage.value = data
      }
      return data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update page'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deletePage(pageId) {
    loading.value = true
    error.value = null
    try {
      await api.delete(`/api/v1/wiki/pages/${pageId}`)
      pages.value = pages.value.filter(p => p.id !== pageId)
      if (currentPage.value?.id === pageId) {
        currentPage.value = null
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete page'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchPageHistory(pageId) {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get(`/api/v1/wiki/pages/${pageId}/history`)
      pageHistory.value = data
      return data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch history'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function restoreFromHistory(pageId, historyId) {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.post(`/api/v1/wiki/pages/${pageId}/restore/${historyId}`)
      if (currentPage.value?.id === pageId) {
        currentPage.value = data
      }
      return data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to restore version'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Categories
  async function fetchCategories() {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get('/api/v1/wiki/categories')
      categories.value = data
      return data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch categories'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createCategory(categoryData) {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.post('/api/v1/wiki/categories', categoryData)
      categories.value.push(data)
      return data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create category'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateCategory(categoryId, categoryData) {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.put(`/api/v1/wiki/categories/${categoryId}`, categoryData)
      const index = categories.value.findIndex(c => c.id === categoryId)
      if (index !== -1) {
        categories.value[index] = data
      }
      return data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update category'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteCategory(categoryId) {
    loading.value = true
    error.value = null
    try {
      await api.delete(`/api/v1/wiki/categories/${categoryId}`)
      categories.value = categories.value.filter(c => c.id !== categoryId)
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete category'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Graph & Search
  async function fetchGraphData() {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get('/api/v1/wiki/graph')
      graphData.value = data
      return data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch graph data'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function searchPages(query) {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get(`/api/v1/wiki/search?q=${encodeURIComponent(query)}`)
      searchResults.value = data
      return data
    } catch (err) {
      error.value = err.response?.data?.error || 'Search failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchRecentPages(limit = 10) {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get(`/api/v1/wiki/pages/recent?limit=${limit}`)
      recentPages.value = data
      return data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch recent pages'
      throw err
    } finally {
      loading.value = false
    }
  }

  function clearCurrentPage() {
    currentPage.value = null
    pageHistory.value = []
  }

  function clearSearch() {
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
