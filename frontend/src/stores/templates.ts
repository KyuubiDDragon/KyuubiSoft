import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

interface TemplateCategory {
  id: string
  name: string
  description?: string
  [key: string]: unknown
}

interface Template {
  id: string
  name: string
  description?: string
  type: string
  icon?: string
  color?: string
  is_public?: boolean
  usage_count?: number
  category_ids?: string[]
  content?: unknown
  [key: string]: unknown
}

interface TemplateFilters {
  type?: string
  category_id?: string
  search?: string
  [key: string]: unknown
}

interface CreateTemplateData {
  name: string
  description?: string
  type: string
  icon?: string
  color?: string
  is_public?: boolean
  category_ids?: string[]
  content?: unknown
  [key: string]: unknown
}

interface UpdateTemplateData {
  name?: string
  description?: string
  type?: string
  icon?: string
  color?: string
  is_public?: boolean
  category_ids?: string[]
  content?: unknown
  [key: string]: unknown
}

interface CreateFromItemTemplateData {
  name: string
  description?: string
  icon?: string
  color?: string
  is_public?: boolean
  category_ids?: string[]
}

interface CreateCategoryData {
  name: string
  description?: string
  [key: string]: unknown
}

interface UpdateCategoryData {
  name?: string
  description?: string
  [key: string]: unknown
}

interface TypeLabels {
  [key: string]: string
}

export const useTemplatesStore = defineStore('templates', () => {
  const templates = ref<Template[]>([])
  const categories = ref<TemplateCategory[]>([])
  const validTypes = ref<string[]>([])
  const isLoading = ref<boolean>(false)
  const error = ref<string | null>(null)

  const templatesByType = computed<Record<string, Template[]>>(() => {
    const grouped: Record<string, Template[]> = {}
    templates.value.forEach(template => {
      if (!grouped[template.type]) {
        grouped[template.type] = []
      }
      grouped[template.type].push(template)
    })
    return grouped
  })

  const typeLabels: TypeLabels = {
    document: 'Dokumente',
    list: 'Listen',
    snippet: 'Snippets',
    checklist: 'Checklisten',
    kanban_board: 'Kanban-Boards',
    project: 'Projekte',
    invoice: 'Rechnungen'
  }

  async function loadTemplates(filters: TemplateFilters = {}): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/templates', { params: filters })
      templates.value = response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to load templates'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function loadValidTypes(): Promise<void> {
    try {
      const response = await api.get('/api/v1/templates/types')
      validTypes.value = response.data.data
    } catch (err: unknown) {
      console.error('Failed to load template types', err)
    }
  }

  async function loadCategories(): Promise<void> {
    try {
      const response = await api.get('/api/v1/templates/categories')
      categories.value = response.data.data
    } catch (err: unknown) {
      console.error('Failed to load template categories', err)
    }
  }

  async function getTemplate(id: string): Promise<Template> {
    try {
      const response = await api.get(`/api/v1/templates/${id}`)
      return response.data.data
    } catch (err: unknown) {
      throw err
    }
  }

  async function createTemplate(data: CreateTemplateData): Promise<Template> {
    try {
      const response = await api.post('/api/v1/templates', data)
      const newTemplate: Template = response.data.data
      templates.value.push(newTemplate)
      return newTemplate
    } catch (err: unknown) {
      throw err
    }
  }

  async function updateTemplate(id: string, data: UpdateTemplateData): Promise<Template> {
    try {
      const response = await api.put(`/api/v1/templates/${id}`, data)
      const updatedTemplate: Template = response.data.data
      const index = templates.value.findIndex(t => t.id === id)
      if (index !== -1) {
        templates.value[index] = updatedTemplate
      }
      return updatedTemplate
    } catch (err: unknown) {
      throw err
    }
  }

  async function deleteTemplate(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/templates/${id}`)
      templates.value = templates.value.filter(t => t.id !== id)
    } catch (err: unknown) {
      throw err
    }
  }

  async function useTemplate(id: string): Promise<unknown> {
    try {
      const response = await api.post(`/api/v1/templates/${id}/use`)
      // Update usage count locally
      const index = templates.value.findIndex(t => t.id === id)
      if (index !== -1) {
        templates.value[index].usage_count = (templates.value[index].usage_count || 0) + 1
      }
      return response.data.data
    } catch (err: unknown) {
      throw err
    }
  }

  async function createFromItem(type: string, itemData: unknown, templateData: CreateFromItemTemplateData): Promise<Template> {
    try {
      const response = await api.post('/api/v1/templates/from-item', {
        type,
        item_data: itemData,
        template_name: templateData.name,
        template_description: templateData.description,
        icon: templateData.icon,
        color: templateData.color,
        is_public: templateData.is_public,
        category_ids: templateData.category_ids
      })
      const newTemplate: Template = response.data.data
      templates.value.push(newTemplate)
      return newTemplate
    } catch (err: unknown) {
      throw err
    }
  }

  async function createCategory(data: CreateCategoryData): Promise<TemplateCategory> {
    try {
      const response = await api.post('/api/v1/templates/categories', data)
      const newCategory: TemplateCategory = response.data.data
      categories.value.push(newCategory)
      return newCategory
    } catch (err: unknown) {
      throw err
    }
  }

  async function updateCategory(id: string, data: UpdateCategoryData): Promise<TemplateCategory> {
    try {
      const response = await api.put(`/api/v1/templates/categories/${id}`, data)
      const updatedCategory: TemplateCategory = response.data.data
      const index = categories.value.findIndex(c => c.id === id)
      if (index !== -1) {
        categories.value[index] = updatedCategory
      }
      return updatedCategory
    } catch (err: unknown) {
      throw err
    }
  }

  async function deleteCategory(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/templates/categories/${id}`)
      categories.value = categories.value.filter(c => c.id !== id)
    } catch (err: unknown) {
      throw err
    }
  }

  return {
    templates,
    categories,
    validTypes,
    isLoading,
    error,
    templatesByType,
    typeLabels,
    loadTemplates,
    loadValidTypes,
    loadCategories,
    getTemplate,
    createTemplate,
    updateTemplate,
    deleteTemplate,
    useTemplate,
    createFromItem,
    createCategory,
    updateCategory,
    deleteCategory
  }
})
