import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const useDiscordStore = defineStore('discord', () => {
  // State
  const accounts = ref([])
  const servers = ref([])
  const dmChannels = ref([])
  const backups = ref([])
  const deleteJobs = ref([])
  const media = ref([])

  const selectedAccount = ref(null)
  const selectedServer = ref(null)
  const selectedChannel = ref(null)

  const isLoading = ref(false)
  const isSyncing = ref(false)

  // Getters
  const activeAccount = computed(() => accounts.value.find(a => a.id === selectedAccount.value))

  const favoriteServers = computed(() => servers.value.filter(s => s.is_favorite))

  const serverChannels = computed(() => {
    if (!selectedServer.value) return []
    return selectedServer.value.channels || []
  })

  const textChannels = computed(() => {
    return serverChannels.value.filter(c => c.type === 'text' || c.type === 'announcement')
  })

  const pendingBackups = computed(() => backups.value.filter(b => b.status === 'pending' || b.status === 'running'))

  const runningDeleteJobs = computed(() => deleteJobs.value.filter(j => j.status === 'pending' || j.status === 'running'))

  // Actions
  async function loadAccounts() {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/discord/accounts')
      accounts.value = response.data.data?.items || []

      if (accounts.value.length > 0 && !selectedAccount.value) {
        selectedAccount.value = accounts.value[0].id
      }
    } finally {
      isLoading.value = false
    }
  }

  async function addAccount(token) {
    const response = await api.post('/api/v1/discord/accounts', { token })
    const account = response.data.data

    const existingIndex = accounts.value.findIndex(a => a.id === account.id)
    if (existingIndex >= 0) {
      accounts.value[existingIndex] = account
    } else {
      accounts.value.push(account)
    }

    selectedAccount.value = account.id
    return account
  }

  async function deleteAccount(accountId) {
    await api.delete(`/api/v1/discord/accounts/${accountId}`)
    accounts.value = accounts.value.filter(a => a.id !== accountId)

    if (selectedAccount.value === accountId) {
      selectedAccount.value = accounts.value.length > 0 ? accounts.value[0].id : null
    }
  }

  async function syncAccount(accountId) {
    isSyncing.value = true
    try {
      const response = await api.post(`/api/v1/discord/accounts/${accountId}/sync`)
      await loadServers(accountId)
      await loadDMChannels(accountId)
      return response.data.data
    } finally {
      isSyncing.value = false
    }
  }

  async function loadServers(accountId = null) {
    const params = accountId ? { account_id: accountId } : {}
    const response = await api.get('/api/v1/discord/servers', { params })
    servers.value = response.data.data?.items || []
  }

  async function loadServerChannels(serverId) {
    const response = await api.get(`/api/v1/discord/servers/${serverId}/channels`)
    selectedServer.value = {
      ...response.data.data.server,
      channels: response.data.data.channels
    }
    return response.data.data
  }

  async function syncServerChannels(serverId) {
    isSyncing.value = true
    try {
      await api.post(`/api/v1/discord/servers/${serverId}/sync`)
      // Reload channels after sync
      return await loadServerChannels(serverId)
    } finally {
      isSyncing.value = false
    }
  }

  async function toggleServerFavorite(serverId) {
    await api.post(`/api/v1/discord/servers/${serverId}/favorite`)
    const server = servers.value.find(s => s.id === serverId)
    if (server) {
      server.is_favorite = !server.is_favorite
    }
  }

  async function loadDMChannels(accountId = null) {
    const params = accountId ? { account_id: accountId } : {}
    const response = await api.get('/api/v1/discord/dm-channels', { params })
    dmChannels.value = response.data.data?.items || []
  }

  // Backups
  async function loadBackups(page = 1, perPage = 50) {
    const response = await api.get('/api/v1/discord/backups', {
      params: { page, per_page: perPage }
    })
    backups.value = response.data.data?.items || []
    return response.data.data
  }

  async function createBackup(data) {
    const response = await api.post('/api/v1/discord/backups', data)
    const backup = response.data.data
    backups.value.unshift(backup)
    return backup
  }

  async function getBackup(backupId) {
    const response = await api.get(`/api/v1/discord/backups/${backupId}`)
    return response.data.data
  }

  async function deleteBackup(backupId) {
    await api.delete(`/api/v1/discord/backups/${backupId}`)
    backups.value = backups.value.filter(b => b.id !== backupId)
  }

  async function loadBackupMessages(backupId, page = 1, perPage = 50, search = null) {
    const params = { page, per_page: perPage }
    if (search) params.search = search

    const response = await api.get(`/api/v1/discord/backups/${backupId}/messages`, { params })
    return response.data.data
  }

  // Global Search
  async function searchMessages(query, page = 1, perPage = 50) {
    const params = { q: query, page, per_page: perPage }
    const response = await api.get('/api/v1/discord/search', { params })
    return response.data.data
  }

  // Links
  async function loadLinks(page = 1, perPage = 50, backupId = null) {
    const params = { page, per_page: perPage }
    if (backupId) params.backup_id = backupId
    const response = await api.get('/api/v1/discord/links', { params })
    return response.data.data
  }

  // Channel-specific media and links
  async function loadChannelMedia(channelId, page = 1, perPage = 50) {
    const response = await api.get(`/api/v1/discord/channels/${channelId}/media`, {
      params: { page, per_page: perPage }
    })
    return response.data.data
  }

  async function loadChannelLinks(channelId, page = 1, perPage = 50) {
    const response = await api.get(`/api/v1/discord/channels/${channelId}/links`, {
      params: { page, per_page: perPage }
    })
    return response.data.data
  }

  // Media
  async function loadMedia(page = 1, perPage = 50) {
    const response = await api.get('/api/v1/discord/media', {
      params: { page, per_page: perPage }
    })
    media.value = response.data.data?.items || []
    return response.data.data
  }

  // Message Deletion
  async function searchOwnMessages(accountId, channelId, beforeId = null) {
    const params = { account_id: accountId, channel_id: channelId }
    if (beforeId) params.before = beforeId

    const response = await api.get('/api/v1/discord/messages/search', { params })
    return response.data.data
  }

  async function createDeleteJob(data) {
    const response = await api.post('/api/v1/discord/delete-jobs', data)
    const job = response.data.data
    deleteJobs.value.unshift(job)
    return job
  }

  async function loadDeleteJobs() {
    const response = await api.get('/api/v1/discord/delete-jobs')
    deleteJobs.value = response.data.data?.items || []
  }

  async function getDeleteJob(jobId) {
    const response = await api.get(`/api/v1/discord/delete-jobs/${jobId}`)
    return response.data.data
  }

  async function cancelDeleteJob(jobId) {
    await api.post(`/api/v1/discord/delete-jobs/${jobId}/cancel`)
    const job = deleteJobs.value.find(j => j.id === jobId)
    if (job) {
      job.status = 'cancelled'
    }
  }

  // Reset
  function reset() {
    accounts.value = []
    servers.value = []
    dmChannels.value = []
    backups.value = []
    deleteJobs.value = []
    media.value = []
    selectedAccount.value = null
    selectedServer.value = null
    selectedChannel.value = null
  }

  return {
    // State
    accounts,
    servers,
    dmChannels,
    backups,
    deleteJobs,
    media,
    selectedAccount,
    selectedServer,
    selectedChannel,
    isLoading,
    isSyncing,

    // Getters
    activeAccount,
    favoriteServers,
    serverChannels,
    textChannels,
    pendingBackups,
    runningDeleteJobs,

    // Actions
    loadAccounts,
    addAccount,
    deleteAccount,
    syncAccount,
    loadServers,
    loadServerChannels,
    syncServerChannels,
    toggleServerFavorite,
    loadDMChannels,
    loadBackups,
    createBackup,
    getBackup,
    deleteBackup,
    loadBackupMessages,
    searchMessages,
    loadLinks,
    loadChannelMedia,
    loadChannelLinks,
    loadMedia,
    searchOwnMessages,
    createDeleteJob,
    loadDeleteJobs,
    getDeleteJob,
    cancelDeleteJob,
    reset,
  }
})
