import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const usePasswordsStore = defineStore('passwords', () => {
  // State
  const passwords = ref([])
  const categories = ref([])
  const selectedCategory = ref(null)
  const searchQuery = ref('')
  const isLoading = ref(false)
  const currentPassword = ref(null)

  // Getters
  const filteredPasswords = computed(() => {
    let result = passwords.value

    // Filter by category
    if (selectedCategory.value) {
      result = result.filter(p => p.category_id === selectedCategory.value)
    }

    // Filter by search query
    if (searchQuery.value) {
      const query = searchQuery.value.toLowerCase()
      result = result.filter(p =>
        p.name?.toLowerCase().includes(query) ||
        p.username?.toLowerCase().includes(query) ||
        p.url?.toLowerCase().includes(query)
      )
    }

    return result
  })

  const favorites = computed(() =>
    passwords.value.filter(p => p.is_favorite)
  )

  // Actions
  async function loadPasswords(categoryId = null, includeArchived = false) {
    isLoading.value = true
    try {
      const params = new URLSearchParams()
      if (categoryId) params.append('category_id', categoryId)
      if (includeArchived) params.append('include_archived', 'true')

      const response = await api.get(`/api/v1/passwords?${params}`)
      passwords.value = response.data.data.items || []
    } catch (error) {
      console.error('Failed to load passwords:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function loadCategories() {
    try {
      const response = await api.get('/api/v1/passwords/categories')
      categories.value = response.data.data.items || []
    } catch (error) {
      console.error('Failed to load categories:', error)
    }
  }

  async function getPassword(id) {
    try {
      const response = await api.get(`/api/v1/passwords/${id}`)
      currentPassword.value = response.data.data
      return response.data.data
    } catch (error) {
      console.error('Failed to get password:', error)
      throw error
    }
  }

  async function createPassword(data) {
    try {
      const response = await api.post('/api/v1/passwords', data)
      await loadPasswords()
      return response.data.data
    } catch (error) {
      console.error('Failed to create password:', error)
      throw error
    }
  }

  async function updatePassword(id, data) {
    try {
      const response = await api.put(`/api/v1/passwords/${id}`, data)
      await loadPasswords()
      return response.data.data
    } catch (error) {
      console.error('Failed to update password:', error)
      throw error
    }
  }

  async function deletePassword(id) {
    try {
      await api.delete(`/api/v1/passwords/${id}`)
      passwords.value = passwords.value.filter(p => p.id !== id)
    } catch (error) {
      console.error('Failed to delete password:', error)
      throw error
    }
  }

  async function toggleFavorite(id) {
    try {
      const response = await api.post(`/api/v1/passwords/${id}/favorite`)
      const index = passwords.value.findIndex(p => p.id === id)
      if (index !== -1) {
        passwords.value[index].is_favorite = response.data.data.is_favorite
      }
      return response.data.data.is_favorite
    } catch (error) {
      console.error('Failed to toggle favorite:', error)
      throw error
    }
  }

  async function generateTOTP(id) {
    try {
      const response = await api.get(`/api/v1/passwords/${id}/totp`)
      return response.data.data
    } catch (error) {
      console.error('Failed to generate TOTP:', error)
      throw error
    }
  }

  async function generatePassword(options = {}) {
    try {
      const params = new URLSearchParams()
      if (options.length) params.append('length', options.length)
      if (options.uppercase !== undefined) params.append('uppercase', options.uppercase)
      if (options.lowercase !== undefined) params.append('lowercase', options.lowercase)
      if (options.numbers !== undefined) params.append('numbers', options.numbers)
      if (options.symbols !== undefined) params.append('symbols', options.symbols)

      const response = await api.get(`/api/v1/passwords/generate?${params}`)
      return response.data.data.password
    } catch (error) {
      console.error('Failed to generate password:', error)
      throw error
    }
  }

  // Category actions
  async function createCategory(data) {
    try {
      const response = await api.post('/api/v1/passwords/categories', data)
      categories.value.push(response.data.data)
      return response.data.data
    } catch (error) {
      console.error('Failed to create category:', error)
      throw error
    }
  }

  async function updateCategory(id, data) {
    try {
      const response = await api.put(`/api/v1/passwords/categories/${id}`, data)
      const index = categories.value.findIndex(c => c.id === id)
      if (index !== -1) {
        categories.value[index] = response.data.data
      }
      return response.data.data
    } catch (error) {
      console.error('Failed to update category:', error)
      throw error
    }
  }

  async function deleteCategory(id) {
    try {
      await api.delete(`/api/v1/passwords/categories/${id}`)
      categories.value = categories.value.filter(c => c.id !== id)
      if (selectedCategory.value === id) {
        selectedCategory.value = null
      }
    } catch (error) {
      console.error('Failed to delete category:', error)
      throw error
    }
  }

  function selectCategory(categoryId) {
    selectedCategory.value = categoryId
  }

  function setSearchQuery(query) {
    searchQuery.value = query
  }

  function clearCurrentPassword() {
    currentPassword.value = null
  }

  return {
    // State
    passwords,
    categories,
    selectedCategory,
    searchQuery,
    isLoading,
    currentPassword,

    // Getters
    filteredPasswords,
    favorites,

    // Actions
    loadPasswords,
    loadCategories,
    getPassword,
    createPassword,
    updatePassword,
    deletePassword,
    toggleFavorite,
    generateTOTP,
    generatePassword,
    createCategory,
    updateCategory,
    deleteCategory,
    selectCategory,
    setSearchQuery,
    clearCurrentPassword,
  }
})
