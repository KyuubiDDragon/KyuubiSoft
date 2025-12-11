import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const useTemplatesStore = defineStore('templates', () => {
  const templates = ref([])
  const categories = ref([])
  const validTypes = ref([])
  const isLoading = ref(false)
  const error = ref(null)

  const templatesByType = computed(() => {
    const grouped = {}
    templates.value.forEach(template => {
      if (!grouped[template.type]) {
        grouped[template.type] = []
      }
      grouped[template.type].push(template)
    })
    return grouped
  })

  const typeLabels = {
    document: 'Dokumente',
    list: 'Listen',
    snippet: 'Snippets',
    checklist: 'Checklisten',
    kanban_board: 'Kanban-Boards',
    project: 'Projekte',
    invoice: 'Rechnungen'
  }

  async function loadTemplates(filters = {}) {
    isLoading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/templates', { params: filters })
      templates.value = response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to load templates'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function loadValidTypes() {
    try {
      const response = await api.get('/api/v1/templates/types')
      validTypes.value = response.data.data
    } catch (err) {
      console.error('Failed to load template types', err)
    }
  }

  async function loadCategories() {
    try {
      const response = await api.get('/api/v1/templates/categories')
      categories.value = response.data.data
    } catch (err) {
      console.error('Failed to load template categories', err)
    }
  }

  async function getTemplate(id) {
    try {
      const response = await api.get(`/api/v1/templates/${id}`)
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function createTemplate(data) {
    try {
      const response = await api.post('/api/v1/templates', data)
      const newTemplate = response.data.data
      templates.value.push(newTemplate)
      return newTemplate
    } catch (err) {
      throw err
    }
  }

  async function updateTemplate(id, data) {
    try {
      const response = await api.put(`/api/v1/templates/${id}`, data)
      const updatedTemplate = response.data.data
      const index = templates.value.findIndex(t => t.id === id)
      if (index !== -1) {
        templates.value[index] = updatedTemplate
      }
      return updatedTemplate
    } catch (err) {
      throw err
    }
  }

  async function deleteTemplate(id) {
    try {
      await api.delete(`/api/v1/templates/${id}`)
      templates.value = templates.value.filter(t => t.id !== id)
    } catch (err) {
      throw err
    }
  }

  async function useTemplate(id) {
    try {
      const response = await api.post(`/api/v1/templates/${id}/use`)
      // Update usage count locally
      const index = templates.value.findIndex(t => t.id === id)
      if (index !== -1) {
        templates.value[index].usage_count = (templates.value[index].usage_count || 0) + 1
      }
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function createFromItem(type, itemData, templateData) {
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
      const newTemplate = response.data.data
      templates.value.push(newTemplate)
      return newTemplate
    } catch (err) {
      throw err
    }
  }

  async function createCategory(data) {
    try {
      const response = await api.post('/api/v1/templates/categories', data)
      const newCategory = response.data.data
      categories.value.push(newCategory)
      return newCategory
    } catch (err) {
      throw err
    }
  }

  async function updateCategory(id, data) {
    try {
      const response = await api.put(`/api/v1/templates/categories/${id}`, data)
      const updatedCategory = response.data.data
      const index = categories.value.findIndex(c => c.id === id)
      if (index !== -1) {
        categories.value[index] = updatedCategory
      }
      return updatedCategory
    } catch (err) {
      throw err
    }
  }

  async function deleteCategory(id) {
    try {
      await api.delete(`/api/v1/templates/categories/${id}`)
      categories.value = categories.value.filter(c => c.id !== id)
    } catch (err) {
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
