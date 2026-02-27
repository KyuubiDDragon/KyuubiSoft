import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export interface EmailAccount {
  id: string
  user_id: string
  name: string
  email: string
  imap_host: string
  imap_port: number
  imap_encryption: 'ssl' | 'tls' | 'none'
  smtp_host: string
  smtp_port: number
  smtp_encryption: 'ssl' | 'tls' | 'none'
  username: string
  is_default: boolean
  is_active: boolean
  last_sync_at: string | null
  created_at: string
  updated_at: string
}

export interface EmailMessage {
  id: string
  account_id: string
  message_id: string | null
  folder: string
  from_address: string
  from_name: string | null
  to_addresses: { email: string; name?: string }[]
  cc_addresses: { email: string; name?: string }[]
  subject: string | null
  body_text: string | null
  body_html: string | null
  body_preview?: string
  is_read: boolean
  is_starred: boolean
  is_draft: boolean
  has_attachments: boolean
  received_at: string
  created_at: string
}

export interface EmailFolder {
  id: string
  name: string
  icon: string
  unread: number
}

export interface EmailStats {
  total_unread: number
  total_messages: number
  inbox_unread: number
  folders: Record<string, { total: number; unread: number }>
}

export interface ComposeData {
  account_id: string
  to: string | { email: string; name?: string }[]
  cc?: string | { email: string; name?: string }[]
  subject: string
  body: string
  is_draft?: boolean
}

interface Pagination {
  page: number
  limit: number
  total: number
  pages: number
}

export const useEmailStore = defineStore('email', () => {
  // State
  const accounts = ref<EmailAccount[]>([])
  const messages = ref<EmailMessage[]>([])
  const currentMessage = ref<EmailMessage | null>(null)
  const folders = ref<EmailFolder[]>([])
  const stats = ref<EmailStats | null>(null)
  const activeFolder = ref<string>('INBOX')
  const activeAccountId = ref<string | null>(null)
  const loading = ref<boolean>(false)
  const messagesLoading = ref<boolean>(false)
  const sendingMessage = ref<boolean>(false)
  const error = ref<string | null>(null)
  const pagination = ref<Pagination>({ page: 1, limit: 50, total: 0, pages: 0 })

  // Computed
  const defaultAccount = computed<EmailAccount | undefined>(() => {
    return accounts.value.find(a => a.is_default) || accounts.value[0]
  })

  const activeAccounts = computed<EmailAccount[]>(() => {
    return accounts.value.filter(a => a.is_active)
  })

  const unreadCount = computed<number>(() => {
    return stats.value?.inbox_unread ?? 0
  })

  // Actions

  async function loadAccounts(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/email/accounts')
      accounts.value = response.data.data?.items ?? response.data.items ?? response.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Fehler beim Laden der Konten'
    } finally {
      loading.value = false
    }
  }

  async function createAccount(data: Record<string, unknown>): Promise<EmailAccount> {
    try {
      const response = await api.post('/api/v1/email/accounts', data)
      const account = response.data.data ?? response.data
      accounts.value.push(account)
      return account
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Fehler beim Erstellen des Kontos'
      throw err
    }
  }

  async function updateAccount(id: string, data: Record<string, unknown>): Promise<EmailAccount> {
    try {
      const response = await api.put(`/api/v1/email/accounts/${id}`, data)
      const updated = response.data.data ?? response.data
      const index = accounts.value.findIndex(a => a.id === id)
      if (index !== -1) {
        accounts.value[index] = updated
      }
      return updated
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Fehler beim Aktualisieren des Kontos'
      throw err
    }
  }

  async function deleteAccount(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/email/accounts/${id}`)
      accounts.value = accounts.value.filter(a => a.id !== id)
      if (activeAccountId.value === id) {
        activeAccountId.value = null
      }
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Fehler beim Löschen des Kontos'
      throw err
    }
  }

  async function testConnection(id: string): Promise<{ imap: { status: string; message: string }; smtp: { status: string; message: string } }> {
    try {
      const response = await api.post(`/api/v1/email/accounts/${id}/test`)
      return response.data.data ?? response.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Verbindungstest fehlgeschlagen'
      throw err
    }
  }

  async function loadMessages(params?: { folder?: string; account_id?: string; search?: string; page?: number }): Promise<void> {
    messagesLoading.value = true
    error.value = null
    try {
      const queryParams: Record<string, string | number> = {}
      if (params?.folder) queryParams.folder = params.folder
      if (params?.account_id) queryParams.account_id = params.account_id
      if (params?.search) queryParams.search = params.search
      if (params?.page) queryParams.page = params.page

      const response = await api.get('/api/v1/email/messages', { params: queryParams })
      const data = response.data.data ?? response.data
      messages.value = data.items ?? []
      if (data.pagination) {
        pagination.value = data.pagination
      }
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Fehler beim Laden der Nachrichten'
    } finally {
      messagesLoading.value = false
    }
  }

  async function loadMessage(id: string): Promise<EmailMessage> {
    try {
      const response = await api.get(`/api/v1/email/messages/${id}`)
      const message = response.data.data ?? response.data
      currentMessage.value = message

      // Update read status in messages list
      const index = messages.value.findIndex(m => m.id === id)
      if (index !== -1) {
        messages.value[index].is_read = true
      }

      return message
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Fehler beim Laden der Nachricht'
      throw err
    }
  }

  async function sendMessage(data: ComposeData): Promise<EmailMessage> {
    sendingMessage.value = true
    try {
      const response = await api.post('/api/v1/email/messages', data)
      const message = response.data.data ?? response.data
      return message
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Fehler beim Senden der Nachricht'
      throw err
    } finally {
      sendingMessage.value = false
    }
  }

  async function deleteMessage(id: string): Promise<void> {
    try {
      await api.delete(`/api/v1/email/messages/${id}`)
      messages.value = messages.value.filter(m => m.id !== id)
      if (currentMessage.value?.id === id) {
        currentMessage.value = null
      }
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Fehler beim Löschen der Nachricht'
      throw err
    }
  }

  async function toggleRead(id: string): Promise<void> {
    try {
      const response = await api.post(`/api/v1/email/messages/${id}/read`)
      const data = response.data.data ?? response.data
      const index = messages.value.findIndex(m => m.id === id)
      if (index !== -1) {
        messages.value[index].is_read = data.is_read
      }
      if (currentMessage.value?.id === id) {
        currentMessage.value.is_read = data.is_read
      }
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Fehler beim Aktualisieren des Lesestatus'
      throw err
    }
  }

  async function toggleStar(id: string): Promise<void> {
    try {
      const response = await api.post(`/api/v1/email/messages/${id}/star`)
      const data = response.data.data ?? response.data
      const index = messages.value.findIndex(m => m.id === id)
      if (index !== -1) {
        messages.value[index].is_starred = data.is_starred
      }
      if (currentMessage.value?.id === id) {
        currentMessage.value.is_starred = data.is_starred
      }
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Fehler beim Aktualisieren des Sternstatus'
      throw err
    }
  }

  async function loadFolders(): Promise<void> {
    try {
      const response = await api.get('/api/v1/email/folders')
      folders.value = response.data.data?.items ?? response.data.items ?? response.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Fehler beim Laden der Ordner'
    }
  }

  async function loadStats(): Promise<void> {
    try {
      const params: Record<string, string> = {}
      if (activeAccountId.value) params.account_id = activeAccountId.value
      const response = await api.get('/api/v1/email/stats', { params })
      stats.value = response.data.data ?? response.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Fehler beim Laden der Statistiken'
    }
  }

  function setActiveFolder(folder: string): void {
    activeFolder.value = folder
    currentMessage.value = null
  }

  function setActiveAccount(accountId: string | null): void {
    activeAccountId.value = accountId
  }

  return {
    // State
    accounts,
    messages,
    currentMessage,
    folders,
    stats,
    activeFolder,
    activeAccountId,
    loading,
    messagesLoading,
    sendingMessage,
    error,
    pagination,
    // Computed
    defaultAccount,
    activeAccounts,
    unreadCount,
    // Actions
    loadAccounts,
    createAccount,
    updateAccount,
    deleteAccount,
    testConnection,
    loadMessages,
    loadMessage,
    sendMessage,
    deleteMessage,
    toggleRead,
    toggleStar,
    loadFolders,
    loadStats,
    setActiveFolder,
    setActiveAccount,
  }
})
