import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'

export interface KBCategory {
  id: string
  user_id?: string
  parent_id: string | null
  name: string
  slug: string
  description: string | null
  icon: string | null
  sort_order: number
  is_published: boolean
  article_count?: number
  children?: KBCategory[]
  created_at?: string
  updated_at?: string
}

export interface KBArticle {
  id: string
  category_id: string | null
  user_id?: string
  title: string
  slug: string
  content: string | null
  excerpt: string | null
  tags: string[]
  is_published: boolean
  view_count: number
  helpful_count: number
  not_helpful_count: number
  category_name?: string | null
  created_at?: string
  updated_at?: string
}

export interface KBArticleRating {
  id: string
  article_id: string
  is_helpful: boolean
  feedback: string | null
  created_at?: string
}

export const useKnowledgeBaseStore = defineStore('knowledgeBase', () => {
  const uiStore = useUiStore()

  // State
  const categories = ref<KBCategory[]>([])
  const articles = ref<KBArticle[]>([])
  const currentArticle = ref<KBArticle | null>(null)
  const publicCategories = ref<KBCategory[]>([])
  const searchResults = ref<KBArticle[]>([])
  const loading = ref(false)

  const pagination = ref({
    page: 1,
    perPage: 25,
    total: 0,
  })

  const searchPagination = ref({
    page: 1,
    perPage: 20,
    total: 0,
  })

  // Getters
  const totalPages = computed(() =>
    Math.ceil(pagination.value.total / pagination.value.perPage) || 1
  )

  const searchTotalPages = computed(() =>
    Math.ceil(searchPagination.value.total / searchPagination.value.perPage) || 1
  )

  // ─── Admin: Categories ───

  async function fetchCategories(): Promise<void> {
    loading.value = true
    try {
      const response = await api.get('/api/v1/knowledge-base/categories')
      categories.value = response.data.data || []
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Kategorien')
    } finally {
      loading.value = false
    }
  }

  async function createCategory(data: Partial<KBCategory>): Promise<KBCategory | null> {
    try {
      const response = await api.post('/api/v1/knowledge-base/categories', data)
      await fetchCategories()
      uiStore.showSuccess('Kategorie erstellt')
      return response.data.data
    } catch (error) {
      uiStore.showError('Fehler beim Erstellen der Kategorie')
      return null
    }
  }

  async function updateCategory(id: string, data: Partial<KBCategory>): Promise<KBCategory | null> {
    try {
      const response = await api.put(`/api/v1/knowledge-base/categories/${id}`, data)
      await fetchCategories()
      uiStore.showSuccess('Kategorie aktualisiert')
      return response.data.data
    } catch (error) {
      uiStore.showError('Fehler beim Aktualisieren der Kategorie')
      return null
    }
  }

  async function deleteCategory(id: string): Promise<boolean> {
    try {
      await api.delete(`/api/v1/knowledge-base/categories/${id}`)
      await fetchCategories()
      uiStore.showSuccess('Kategorie gelöscht')
      return true
    } catch (error) {
      uiStore.showError('Fehler beim Löschen der Kategorie')
      return false
    }
  }

  // ─── Admin: Articles ───

  async function fetchArticles(filters: Record<string, unknown> = {}): Promise<void> {
    loading.value = true
    try {
      const params: Record<string, unknown> = {
        page: pagination.value.page,
        per_page: pagination.value.perPage,
        ...filters,
      }

      const response = await api.get('/api/v1/knowledge-base/articles', { params })
      const data = response.data.data
      articles.value = data?.items || []
      pagination.value.total = data?.pagination?.total || 0
    } catch (error) {
      uiStore.showError('Fehler beim Laden der Artikel')
    } finally {
      loading.value = false
    }
  }

  async function fetchArticle(id: string): Promise<void> {
    loading.value = true
    try {
      const response = await api.get(`/api/v1/knowledge-base/articles/${id}`)
      currentArticle.value = response.data.data
    } catch (error) {
      uiStore.showError('Fehler beim Laden des Artikels')
    } finally {
      loading.value = false
    }
  }

  async function createArticle(data: Partial<KBArticle>): Promise<KBArticle | null> {
    try {
      const response = await api.post('/api/v1/knowledge-base/articles', data)
      uiStore.showSuccess('Artikel erstellt')
      return response.data.data
    } catch (error) {
      uiStore.showError('Fehler beim Erstellen des Artikels')
      return null
    }
  }

  async function updateArticle(id: string, data: Partial<KBArticle>): Promise<KBArticle | null> {
    try {
      const response = await api.put(`/api/v1/knowledge-base/articles/${id}`, data)
      uiStore.showSuccess('Artikel aktualisiert')
      return response.data.data
    } catch (error) {
      uiStore.showError('Fehler beim Aktualisieren des Artikels')
      return null
    }
  }

  async function deleteArticle(id: string): Promise<boolean> {
    try {
      await api.delete(`/api/v1/knowledge-base/articles/${id}`)
      uiStore.showSuccess('Artikel gelöscht')
      return true
    } catch (error) {
      uiStore.showError('Fehler beim Löschen des Artikels')
      return false
    }
  }

  // ─── Public ───

  async function fetchPublicCategories(): Promise<void> {
    loading.value = true
    try {
      const response = await api.get('/api/v1/kb/categories')
      publicCategories.value = response.data.data || []
    } catch (error) {
      console.error('Failed to load public categories:', error)
    } finally {
      loading.value = false
    }
  }

  async function fetchPublicArticle(slug: string): Promise<KBArticle | null> {
    loading.value = true
    try {
      const response = await api.get(`/api/v1/kb/articles/${slug}`)
      currentArticle.value = response.data.data
      return response.data.data
    } catch (error) {
      console.error('Failed to load article:', error)
      return null
    } finally {
      loading.value = false
    }
  }

  async function searchArticles(query: string, page: number = 1): Promise<void> {
    loading.value = true
    try {
      const response = await api.get('/api/v1/kb/search', {
        params: { q: query, page, per_page: searchPagination.value.perPage },
      })
      const data = response.data.data
      searchResults.value = data?.items || []
      searchPagination.value.total = data?.pagination?.total || 0
      searchPagination.value.page = page
    } catch (error) {
      console.error('Search failed:', error)
    } finally {
      loading.value = false
    }
  }

  async function rateArticle(slug: string, isHelpful: boolean, feedback?: string): Promise<boolean> {
    try {
      await api.post(`/api/v1/kb/articles/${slug}/rate`, {
        is_helpful: isHelpful,
        feedback: feedback || null,
      })
      return true
    } catch (error: any) {
      if (error?.response?.status === 429) {
        uiStore.showError('Sie haben diesen Artikel bereits bewertet')
      }
      return false
    }
  }

  function setPage(page: number): void {
    pagination.value.page = page
  }

  return {
    // State
    categories,
    articles,
    currentArticle,
    publicCategories,
    searchResults,
    loading,
    pagination,
    searchPagination,

    // Getters
    totalPages,
    searchTotalPages,

    // Admin actions
    fetchCategories,
    createCategory,
    updateCategory,
    deleteCategory,
    fetchArticles,
    fetchArticle,
    createArticle,
    updateArticle,
    deleteArticle,

    // Public actions
    fetchPublicCategories,
    fetchPublicArticle,
    searchArticles,
    rateArticle,

    setPage,
  }
})
