import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export interface DiscordAccount {
  id: string
  username?: string
  discriminator?: string
  avatar_url?: string
  token?: string
  [key: string]: unknown
}

export interface DiscordServer {
  id: string
  name?: string
  icon_url?: string
  is_favorite: boolean
  channels?: DiscordChannel[]
  [key: string]: unknown
}

export interface DiscordChannel {
  id: string
  name?: string
  type: string
  [key: string]: unknown
}

export interface DiscordDMChannel {
  id: string
  recipient?: string
  [key: string]: unknown
}

export interface DiscordBackup {
  id: string
  status: string
  channel_id?: string
  server_id?: string
  message_count?: number
  created_at?: string
  [key: string]: unknown
}

export interface DiscordDeleteJob {
  id: string
  status: string
  channel_id?: string
  deleted_count?: number
  [key: string]: unknown
}

export interface DiscordMedia {
  id: string
  url?: string
  filename?: string
  content_type?: string
  [key: string]: unknown
}

export interface DiscordBot {
  id: string
  name?: string
  avatar_url?: string
  bot_token?: string
  [key: string]: unknown
}

export interface DiscordBotServer {
  id: string
  name?: string
  icon_url?: string
  is_favorite: boolean
  channels?: DiscordChannel[]
  [key: string]: unknown
}

export interface BackupCreateData {
  account_id?: string
  bot_id?: string
  server_id?: string
  channel_id?: string
  [key: string]: unknown
}

export interface DeleteJobCreateData {
  account_id: string
  channel_id: string
  [key: string]: unknown
}

export interface BotCreateData {
  bot_token: string
  name?: string
  [key: string]: unknown
}

export interface PaginatedResponse<T = unknown> {
  items: T[]
  pagination?: Record<string, unknown>
  [key: string]: unknown
}

export interface ServerChannelsResponse {
  server: DiscordServer
  channels: DiscordChannel[]
}

export const useDiscordStore = defineStore('discord', () => {
  // State
  const accounts = ref<DiscordAccount[]>([])
  const servers = ref<DiscordServer[]>([])
  const dmChannels = ref<DiscordDMChannel[]>([])
  const backups = ref<DiscordBackup[]>([])
  const deleteJobs = ref<DiscordDeleteJob[]>([])
  const media = ref<DiscordMedia[]>([])

  // Bot State
  const bots = ref<DiscordBot[]>([])
  const selectedBot = ref<string | null>(null)
  const botServers = ref<DiscordBotServer[]>([])
  const selectedBotServer = ref<DiscordBotServer | null>(null)

  const selectedAccount = ref<string | null>(null)
  const selectedServer = ref<DiscordServer | null>(null)
  const selectedChannel = ref<DiscordChannel | null>(null)

  const isLoading = ref<boolean>(false)
  const isSyncing = ref<boolean>(false)

  // Getters
  const activeAccount = computed<DiscordAccount | undefined>(() =>
    accounts.value.find((a: DiscordAccount) => a.id === selectedAccount.value)
  )

  const favoriteServers = computed<DiscordServer[]>(() =>
    servers.value.filter((s: DiscordServer) => s.is_favorite)
  )

  const serverChannels = computed<DiscordChannel[]>(() => {
    if (!selectedServer.value) return []
    return selectedServer.value.channels || []
  })

  const textChannels = computed<DiscordChannel[]>(() => {
    return serverChannels.value.filter((c: DiscordChannel) => c.type === 'text' || c.type === 'announcement')
  })

  const pendingBackups = computed<DiscordBackup[]>(() =>
    backups.value.filter((b: DiscordBackup) => b.status === 'pending' || b.status === 'running')
  )

  const runningDeleteJobs = computed<DiscordDeleteJob[]>(() =>
    deleteJobs.value.filter((j: DiscordDeleteJob) => j.status === 'pending' || j.status === 'running')
  )

  // Actions
  async function loadAccounts(): Promise<void> {
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

  async function addAccount(token: string): Promise<DiscordAccount> {
    const response = await api.post('/api/v1/discord/accounts', { token })
    const account: DiscordAccount = response.data.data

    const existingIndex = accounts.value.findIndex((a: DiscordAccount) => a.id === account.id)
    if (existingIndex >= 0) {
      accounts.value[existingIndex] = account
    } else {
      accounts.value.push(account)
    }

    selectedAccount.value = account.id
    return account
  }

  async function deleteAccount(accountId: string): Promise<void> {
    await api.delete(`/api/v1/discord/accounts/${accountId}`)
    accounts.value = accounts.value.filter((a: DiscordAccount) => a.id !== accountId)

    if (selectedAccount.value === accountId) {
      selectedAccount.value = accounts.value.length > 0 ? accounts.value[0].id : null
    }
  }

  async function syncAccount(accountId: string): Promise<unknown> {
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

  async function loadServers(accountId: string | null = null): Promise<void> {
    const params: Record<string, unknown> = accountId ? { account_id: accountId } : {}
    const response = await api.get('/api/v1/discord/servers', { params })
    servers.value = response.data.data?.items || []
  }

  async function loadServerChannels(serverId: string): Promise<ServerChannelsResponse> {
    const response = await api.get(`/api/v1/discord/servers/${serverId}/channels`)
    selectedServer.value = {
      ...response.data.data.server,
      channels: response.data.data.channels
    }
    return response.data.data
  }

  async function syncServerChannels(serverId: string): Promise<ServerChannelsResponse> {
    isSyncing.value = true
    try {
      await api.post(`/api/v1/discord/servers/${serverId}/sync`)
      // Reload channels after sync
      return await loadServerChannels(serverId)
    } finally {
      isSyncing.value = false
    }
  }

  async function toggleServerFavorite(serverId: string): Promise<void> {
    await api.post(`/api/v1/discord/servers/${serverId}/favorite`)
    const server = servers.value.find((s: DiscordServer) => s.id === serverId)
    if (server) {
      server.is_favorite = !server.is_favorite
    }
  }

  async function loadDMChannels(accountId: string | null = null): Promise<void> {
    const params: Record<string, unknown> = accountId ? { account_id: accountId } : {}
    const response = await api.get('/api/v1/discord/dm-channels', { params })
    dmChannels.value = response.data.data?.items || []
  }

  // Backups
  async function loadBackups(page: number = 1, perPage: number = 50): Promise<PaginatedResponse<DiscordBackup>> {
    const response = await api.get('/api/v1/discord/backups', {
      params: { page, per_page: perPage }
    })
    backups.value = response.data.data?.items || []
    return response.data.data
  }

  async function createBackup(data: BackupCreateData): Promise<DiscordBackup> {
    const response = await api.post('/api/v1/discord/backups', data)
    const backup: DiscordBackup = response.data.data
    backups.value.unshift(backup)
    return backup
  }

  async function getBackup(backupId: string): Promise<DiscordBackup> {
    const response = await api.get(`/api/v1/discord/backups/${backupId}`)
    return response.data.data
  }

  async function deleteBackup(backupId: string): Promise<void> {
    await api.delete(`/api/v1/discord/backups/${backupId}`)
    backups.value = backups.value.filter((b: DiscordBackup) => b.id !== backupId)
  }

  async function loadBackupMessages(
    backupId: string,
    page: number = 1,
    perPage: number = 50,
    search: string | null = null,
    channelId: string | null = null
  ): Promise<PaginatedResponse> {
    const params: Record<string, unknown> = { page, per_page: perPage }
    if (search) params.search = search
    if (channelId) params.channel_id = channelId

    const response = await api.get(`/api/v1/discord/backups/${backupId}/messages`, { params })
    return response.data.data
  }

  async function loadBackupChannels(backupId: string): Promise<DiscordChannel[]> {
    const response = await api.get(`/api/v1/discord/backups/${backupId}/channels`)
    return response.data.data
  }

  async function loadBackupMedia(backupId: string, page: number = 1, perPage: number = 50): Promise<PaginatedResponse<DiscordMedia>> {
    const response = await api.get(`/api/v1/discord/backups/${backupId}/media`, {
      params: { page, per_page: perPage }
    })
    return response.data.data
  }

  async function loadBackupLinks(backupId: string, page: number = 1, perPage: number = 50): Promise<PaginatedResponse> {
    const response = await api.get(`/api/v1/discord/backups/${backupId}/links`, {
      params: { page, per_page: perPage }
    })
    return response.data.data
  }

  // Global Search
  async function searchMessages(query: string, page: number = 1, perPage: number = 50): Promise<PaginatedResponse> {
    const params: Record<string, unknown> = { q: query, page, per_page: perPage }
    const response = await api.get('/api/v1/discord/search', { params })
    return response.data.data
  }

  // Links
  async function loadLinks(page: number = 1, perPage: number = 50, backupId: string | null = null): Promise<PaginatedResponse> {
    const params: Record<string, unknown> = { page, per_page: perPage }
    if (backupId) params.backup_id = backupId
    const response = await api.get('/api/v1/discord/links', { params })
    return response.data.data
  }

  // Channel-specific media and links
  async function loadChannelMedia(channelId: string, page: number = 1, perPage: number = 50): Promise<PaginatedResponse<DiscordMedia>> {
    const response = await api.get(`/api/v1/discord/channels/${channelId}/media`, {
      params: { page, per_page: perPage }
    })
    return response.data.data
  }

  async function loadChannelLinks(channelId: string, page: number = 1, perPage: number = 50): Promise<PaginatedResponse> {
    const response = await api.get(`/api/v1/discord/channels/${channelId}/links`, {
      params: { page, per_page: perPage }
    })
    return response.data.data
  }

  // Media
  async function loadMedia(page: number = 1, perPage: number = 50): Promise<PaginatedResponse<DiscordMedia>> {
    const response = await api.get('/api/v1/discord/media', {
      params: { page, per_page: perPage }
    })
    media.value = response.data.data?.items || []
    return response.data.data
  }

  // Message Deletion
  async function searchOwnMessages(accountId: string, channelId: string, beforeId: string | null = null): Promise<PaginatedResponse> {
    const params: Record<string, unknown> = { account_id: accountId, channel_id: channelId }
    if (beforeId) params.before = beforeId

    const response = await api.get('/api/v1/discord/messages/search', { params })
    return response.data.data
  }

  async function createDeleteJob(data: DeleteJobCreateData): Promise<DiscordDeleteJob> {
    const response = await api.post('/api/v1/discord/delete-jobs', data)
    const job: DiscordDeleteJob = response.data.data
    deleteJobs.value.unshift(job)
    return job
  }

  async function loadDeleteJobs(): Promise<void> {
    const response = await api.get('/api/v1/discord/delete-jobs')
    deleteJobs.value = response.data.data?.items || []
  }

  async function getDeleteJob(jobId: string): Promise<DiscordDeleteJob> {
    const response = await api.get(`/api/v1/discord/delete-jobs/${jobId}`)
    return response.data.data
  }

  async function cancelDeleteJob(jobId: string): Promise<void> {
    await api.post(`/api/v1/discord/delete-jobs/${jobId}/cancel`)
    const job = deleteJobs.value.find((j: DiscordDeleteJob) => j.id === jobId)
    if (job) {
      job.status = 'cancelled'
    }
  }

  // ========== Bot Functions ==========

  async function loadBots(): Promise<void> {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/discord/bots')
      bots.value = response.data.data?.items || []
    } finally {
      isLoading.value = false
    }
  }

  async function validateBot(botToken: string): Promise<unknown> {
    const response = await api.post('/api/v1/discord/bots/validate', { bot_token: botToken })
    return response.data.data
  }

  async function addBot(data: BotCreateData): Promise<DiscordBot> {
    const response = await api.post('/api/v1/discord/bots', data)
    const bot: DiscordBot = response.data.data

    const existingIndex = bots.value.findIndex((b: DiscordBot) => b.id === bot.id)
    if (existingIndex >= 0) {
      bots.value[existingIndex] = bot
    } else {
      bots.value.push(bot)
    }

    return bot
  }

  async function getBot(botId: string): Promise<DiscordBot> {
    const response = await api.get(`/api/v1/discord/bots/${botId}`)
    return response.data.data
  }

  async function deleteBot(botId: string): Promise<void> {
    await api.delete(`/api/v1/discord/bots/${botId}`)
    bots.value = bots.value.filter((b: DiscordBot) => b.id !== botId)

    if (selectedBot.value === botId) {
      selectedBot.value = null
      botServers.value = []
      selectedBotServer.value = null
    }
  }

  async function syncBot(botId: string): Promise<unknown> {
    isSyncing.value = true
    try {
      const response = await api.post(`/api/v1/discord/bots/${botId}/sync`)
      await loadBotServers(botId)
      return response.data.data
    } finally {
      isSyncing.value = false
    }
  }

  async function getBotInviteUrl(botId: string, extended: boolean = false): Promise<unknown> {
    const params: Record<string, unknown> = extended ? { extended: true } : {}
    const response = await api.get(`/api/v1/discord/bots/${botId}/invite`, { params })
    return response.data.data
  }

  async function loadBotServers(botId: string): Promise<DiscordBotServer[]> {
    const response = await api.get(`/api/v1/discord/bots/${botId}/servers`)
    botServers.value = response.data.data?.items || []
    return botServers.value
  }

  async function getBotServer(botId: string, serverId: string): Promise<DiscordBotServer> {
    const response = await api.get(`/api/v1/discord/bots/${botId}/servers/${serverId}`)
    selectedBotServer.value = response.data.data
    return response.data.data
  }

  async function syncBotServerChannels(botId: string, serverId: string): Promise<unknown> {
    isSyncing.value = true
    try {
      const response = await api.post(`/api/v1/discord/bots/${botId}/servers/${serverId}/sync`)
      return response.data.data
    } finally {
      isSyncing.value = false
    }
  }

  async function toggleBotServerFavorite(botId: string, serverId: string): Promise<void> {
    await api.post(`/api/v1/discord/bots/${botId}/servers/${serverId}/favorite`)
    const server = botServers.value.find((s: DiscordBotServer) => s.id === serverId)
    if (server) {
      server.is_favorite = !server.is_favorite
    }
  }

  async function createBotBackup(botId: string, data: BackupCreateData): Promise<DiscordBackup> {
    const response = await api.post(`/api/v1/discord/bots/${botId}/backups`, data)
    const backup: DiscordBackup = response.data.data
    backups.value.unshift(backup)
    return backup
  }

  async function loadBotBackups(botId: string, page: number = 1, perPage: number = 50): Promise<PaginatedResponse<DiscordBackup>> {
    const response = await api.get(`/api/v1/discord/bots/${botId}/backups`, {
      params: { page, per_page: perPage }
    })
    return response.data.data
  }

  // Reset
  function reset(): void {
    accounts.value = []
    servers.value = []
    dmChannels.value = []
    backups.value = []
    deleteJobs.value = []
    media.value = []
    bots.value = []
    botServers.value = []
    selectedAccount.value = null
    selectedServer.value = null
    selectedChannel.value = null
    selectedBot.value = null
    selectedBotServer.value = null
  }

  return {
    // State
    accounts,
    servers,
    dmChannels,
    backups,
    deleteJobs,
    media,
    bots,
    botServers,
    selectedAccount,
    selectedServer,
    selectedChannel,
    selectedBot,
    selectedBotServer,
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
    loadBackupChannels,
    loadBackupMedia,
    loadBackupLinks,
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
    // Bot Actions
    loadBots,
    validateBot,
    addBot,
    getBot,
    deleteBot,
    syncBot,
    getBotInviteUrl,
    loadBotServers,
    getBotServer,
    syncBotServerChannels,
    toggleBotServerFavorite,
    createBotBackup,
    loadBotBackups,
    reset,
  }
})
