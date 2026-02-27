import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

interface PasswordCategory {
  id: string
  name: string
  description?: string
  [key: string]: unknown
}

interface PasswordEntry {
  id: string
  name?: string
  username?: string
  password?: string
  url?: string
  notes?: string
  category_id?: string | null
  is_favorite: boolean
  is_archived?: boolean
  totp_secret?: string
  [key: string]: unknown
}

interface TOTPData {
  code: string
  remaining_seconds: number
  [key: string]: unknown
}

interface GeneratePasswordOptions {
  length?: number
  uppercase?: boolean
  lowercase?: boolean
  numbers?: boolean
  symbols?: boolean
}

interface CreatePasswordData {
  name: string
  username?: string
  password?: string
  url?: string
  notes?: string
  category_id?: string | null
  is_favorite?: boolean
  totp_secret?: string
  [key: string]: unknown
}

interface UpdatePasswordData {
  name?: string
  username?: string
  password?: string
  url?: string
  notes?: string
  category_id?: string | null
  is_favorite?: boolean
  totp_secret?: string
  [key: string]: unknown
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

export const usePasswordsStore = defineStore('passwords', () => {
  // State
  const passwords = ref<PasswordEntry[]>([])
  const categories = ref<PasswordCategory[]>([])
  const selectedCategory = ref<string | null>(null)
  const searchQuery = ref<string>('')
  const isLoading = ref<boolean>(false)
  const currentPassword = ref<PasswordEntry | null>(null)

  // Getters
  const filteredPasswords = computed<PasswordEntry[]>(() => {
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

  const favorites = computed<PasswordEntry[]>(() =>
    passwords.value.filter(p => p.is_favorite)
  )

  // Actions
  async function loadPasswords(categoryId: string | null = null, includeArchived: boolean = false): Promise<void> {
    isLoading.value = true
    try {
      const params = new URLSearchParams()
      if (categoryId) params.append('category_id', categoryId)
      if (includeArchived) params.append('include_archived', 'true')

      const response = await api.get(`/api/v1/passwords?${params}`)
      passwords.value = response.data.data.items || []
    } catch (error: unknown) {
      console.error('Failed to load passwords:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function loadCategories(): Promise<void> {
    try {
      const response = await api.get('/api/v1/passwords/categories')
      categories.value = response.data.data.items || []
    } catch (error: unknown) {
      console.error('Failed to load categories:', error)
    }
  }

  async function getPassword(id: string): Promise<PasswordEntry> {
    try {
      const response = await api.get(`/api/v1/passwords/${id}`)
      currentPassword.value = response.data.data
      return response.data.data
    } catch (error: unknown) {
      console.error('Failed to get password:', error)
      throw error
    }
  }

  async function createPassword(data: CreatePasswordData): Promise<PasswordEntry> {
    try {
      const response = await api.post('/api/v1/passwords', data)
      await loadPasswords()
      return response.data.data
    } catch (error: unknown) {
      console.error('Failed to create password:', error)
      throw error
    }
  }

  async function updatePassword(id: string, data: UpdatePasswordData): Promise<PasswordEntry> {
    try {
      const response = await api.put(`/api/v1/passwords/${id}`, data)
      await loadPasswords()
      return response.data.data
    } catch (error: unknown) {
      console.error('Failed to update password:', error)
      throw error
    }
  }

  async function deletePassword(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/passwords/${id}`)
      passwords.value = passwords.value.filter(p => p.id !== id)
    } catch (error: unknown) {
      console.error('Failed to delete password:', error)
      throw error
    }
  }

  async function toggleFavorite(id: string): Promise<boolean> {
    try {
      const response = await api.post(`/api/v1/passwords/${id}/favorite`)
      const index = passwords.value.findIndex(p => p.id === id)
      if (index !== -1) {
        passwords.value[index].is_favorite = response.data.data.is_favorite
      }
      return response.data.data.is_favorite
    } catch (error: unknown) {
      console.error('Failed to toggle favorite:', error)
      throw error
    }
  }

  async function generateTOTP(id: string): Promise<TOTPData> {
    try {
      const response = await api.get(`/api/v1/passwords/${id}/totp`)
      return response.data.data
    } catch (error: unknown) {
      console.error('Failed to generate TOTP:', error)
      throw error
    }
  }

  async function generatePassword(options: GeneratePasswordOptions = {}): Promise<string> {
    try {
      const params = new URLSearchParams()
      if (options.length) params.append('length', String(options.length))
      if (options.uppercase !== undefined) params.append('uppercase', String(options.uppercase))
      if (options.lowercase !== undefined) params.append('lowercase', String(options.lowercase))
      if (options.numbers !== undefined) params.append('numbers', String(options.numbers))
      if (options.symbols !== undefined) params.append('symbols', String(options.symbols))

      const response = await api.get(`/api/v1/passwords/generate?${params}`)
      return response.data.data.password
    } catch (error: unknown) {
      console.error('Failed to generate password:', error)
      throw error
    }
  }

  // Category actions
  async function createCategory(data: CreateCategoryData): Promise<PasswordCategory> {
    try {
      const response = await api.post('/api/v1/passwords/categories', data)
      categories.value.push(response.data.data)
      return response.data.data
    } catch (error: unknown) {
      console.error('Failed to create category:', error)
      throw error
    }
  }

  async function updateCategory(id: string, data: UpdateCategoryData): Promise<PasswordCategory> {
    try {
      const response = await api.put(`/api/v1/passwords/categories/${id}`, data)
      const index = categories.value.findIndex(c => c.id === id)
      if (index !== -1) {
        categories.value[index] = response.data.data
      }
      return response.data.data
    } catch (error: unknown) {
      console.error('Failed to update category:', error)
      throw error
    }
  }

  async function deleteCategory(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/passwords/categories/${id}`)
      categories.value = categories.value.filter(c => c.id !== id)
      if (selectedCategory.value === id) {
        selectedCategory.value = null
      }
    } catch (error: unknown) {
      console.error('Failed to delete category:', error)
      throw error
    }
  }

  function selectCategory(categoryId: string | null): void {
    selectedCategory.value = categoryId
  }

  function setSearchQuery(query: string): void {
    searchQuery.value = query
  }

  function clearCurrentPassword(): void {
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
