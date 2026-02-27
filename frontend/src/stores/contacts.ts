import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export interface Contact {
  id: string
  user_id: string
  first_name: string
  last_name: string
  email?: string
  phone?: string
  mobile?: string
  company?: string
  position?: string
  address?: string
  city?: string
  postal_code?: string
  country?: string
  website?: string
  notes?: string
  tags: string[]
  is_favorite: boolean
  avatar_color: string
  last_contact_at?: string
  created_at: string
  updated_at: string
  activities?: ContactActivity[]
  [key: string]: unknown
}

export interface ContactActivity {
  id: string
  contact_id: string
  user_id: string
  type: 'note' | 'email' | 'call' | 'meeting'
  subject?: string
  description?: string
  activity_date: string
  created_at: string
  [key: string]: unknown
}

export interface ContactFilters {
  search: string
  sort: string
  favorite: boolean
}

export interface ContactStats {
  total: number
  favorites: number
  recent: number
}

interface CreateContactData {
  first_name: string
  last_name: string
  email?: string
  phone?: string
  mobile?: string
  company?: string
  position?: string
  address?: string
  city?: string
  postal_code?: string
  country?: string
  website?: string
  notes?: string
  tags?: string[]
  is_favorite?: boolean
  avatar_color?: string
  [key: string]: unknown
}

interface UpdateContactData {
  first_name?: string
  last_name?: string
  email?: string
  phone?: string
  mobile?: string
  company?: string
  position?: string
  address?: string
  city?: string
  postal_code?: string
  country?: string
  website?: string
  notes?: string
  tags?: string[]
  is_favorite?: boolean
  avatar_color?: string
  last_contact_at?: string
  [key: string]: unknown
}

interface CreateActivityData {
  type: 'note' | 'email' | 'call' | 'meeting'
  subject?: string
  description?: string
  activity_date?: string
  [key: string]: unknown
}

export const useContactsStore = defineStore('contacts', () => {
  // State
  const contacts = ref<Contact[]>([])
  const currentContact = ref<Contact | null>(null)
  const activities = ref<ContactActivity[]>([])
  const stats = ref<ContactStats>({ total: 0, favorites: 0, recent: 0 })
  const isLoading = ref<boolean>(false)
  const isLoadingContact = ref<boolean>(false)
  const isLoadingActivities = ref<boolean>(false)
  const filters = ref<ContactFilters>({
    search: '',
    sort: 'name_asc',
    favorite: false,
  })
  const totalContacts = ref<number>(0)
  const currentPage = ref<number>(1)

  // Getters
  const filteredContacts = computed<Contact[]>(() => {
    return contacts.value
  })

  // Actions
  async function loadContacts(page: number = 1): Promise<void> {
    isLoading.value = true
    try {
      const params = new URLSearchParams()
      params.append('page', String(page))
      params.append('per_page', '50')
      params.append('sort', filters.value.sort)

      if (filters.value.search) {
        params.append('search', filters.value.search)
      }
      if (filters.value.favorite) {
        params.append('favorite', 'true')
      }

      const response = await api.get(`/api/v1/contacts?${params}`)
      contacts.value = response.data.data || []
      totalContacts.value = response.data.meta?.total || 0
      currentPage.value = page
    } catch (error: unknown) {
      console.error('Failed to load contacts:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function loadContact(id: string): Promise<Contact> {
    isLoadingContact.value = true
    try {
      const response = await api.get(`/api/v1/contacts/${id}`)
      currentContact.value = response.data.data
      return response.data.data
    } catch (error: unknown) {
      console.error('Failed to load contact:', error)
      throw error
    } finally {
      isLoadingContact.value = false
    }
  }

  async function createContact(data: CreateContactData): Promise<Contact> {
    try {
      const response = await api.post('/api/v1/contacts', data)
      await loadContacts()
      await loadStats()
      return response.data.data
    } catch (error: unknown) {
      console.error('Failed to create contact:', error)
      throw error
    }
  }

  async function updateContact(id: string, data: UpdateContactData): Promise<Contact> {
    try {
      const response = await api.put(`/api/v1/contacts/${id}`, data)
      const updated = response.data.data
      // Update in list
      const index = contacts.value.findIndex(c => c.id === id)
      if (index !== -1) {
        contacts.value[index] = updated
      }
      if (currentContact.value?.id === id) {
        currentContact.value = { ...currentContact.value, ...updated }
      }
      return updated
    } catch (error: unknown) {
      console.error('Failed to update contact:', error)
      throw error
    }
  }

  async function deleteContact(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/contacts/${id}`)
      contacts.value = contacts.value.filter(c => c.id !== id)
      if (currentContact.value?.id === id) {
        currentContact.value = null
      }
      await loadStats()
    } catch (error: unknown) {
      console.error('Failed to delete contact:', error)
      throw error
    }
  }

  async function toggleFavorite(id: string): Promise<boolean> {
    try {
      const response = await api.post(`/api/v1/contacts/${id}/favorite`)
      const isFavorite = response.data.data.is_favorite
      const index = contacts.value.findIndex(c => c.id === id)
      if (index !== -1) {
        contacts.value[index].is_favorite = isFavorite
      }
      if (currentContact.value?.id === id) {
        currentContact.value.is_favorite = isFavorite
      }
      await loadStats()
      return isFavorite
    } catch (error: unknown) {
      console.error('Failed to toggle favorite:', error)
      throw error
    }
  }

  async function loadActivities(contactId: string): Promise<void> {
    isLoadingActivities.value = true
    try {
      const response = await api.get(`/api/v1/contacts/${contactId}/activities`)
      activities.value = response.data.data.items || []
    } catch (error: unknown) {
      console.error('Failed to load activities:', error)
      throw error
    } finally {
      isLoadingActivities.value = false
    }
  }

  async function createActivity(contactId: string, data: CreateActivityData): Promise<ContactActivity> {
    try {
      const response = await api.post(`/api/v1/contacts/${contactId}/activities`, data)
      activities.value.unshift(response.data.data)
      return response.data.data
    } catch (error: unknown) {
      console.error('Failed to create activity:', error)
      throw error
    }
  }

  async function deleteActivity(contactId: string, activityId: string): Promise<void> {
    try {
      await api.delete(`/api/v1/contacts/${contactId}/activities/${activityId}`)
      activities.value = activities.value.filter(a => a.id !== activityId)
    } catch (error: unknown) {
      console.error('Failed to delete activity:', error)
      throw error
    }
  }

  async function loadStats(): Promise<void> {
    try {
      const response = await api.get('/api/v1/contacts/stats')
      stats.value = response.data.data
    } catch (error: unknown) {
      console.error('Failed to load stats:', error)
    }
  }

  return {
    // State
    contacts,
    currentContact,
    activities,
    stats,
    isLoading,
    isLoadingContact,
    isLoadingActivities,
    filters,
    totalContacts,
    currentPage,

    // Getters
    filteredContacts,

    // Actions
    loadContacts,
    loadContact,
    createContact,
    updateContact,
    deleteContact,
    toggleFavorite,
    loadActivities,
    createActivity,
    deleteActivity,
    loadStats,
  }
})
