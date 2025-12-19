<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useDiscordStore } from '../stores/discordStore'
import { useUiStore } from '@/stores/ui'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  PlusIcon,
  ArrowPathIcon,
  TrashIcon,
  StarIcon,
  ChatBubbleLeftRightIcon,
  CloudArrowDownIcon,
  MagnifyingGlassIcon,
  FolderIcon,
  PhotoIcon,
  DocumentTextIcon,
  XMarkIcon,
  ChevronRightIcon,
  ServerIcon,
  HashtagIcon,
  UserIcon,
  LinkIcon,
} from '@heroicons/vue/24/outline'
import { StarIcon as StarSolidIcon } from '@heroicons/vue/24/solid'

const discordStore = useDiscordStore()
const uiStore = useUiStore()
const { confirm } = useConfirmDialog()

// Helper for authenticated media URLs
function getMediaUrl(mediaId) {
  const token = localStorage.getItem('access_token')
  return `/api/v1/discord/media/${mediaId}?token=${encodeURIComponent(token)}`
}

// State
const activeTab = ref('servers')
const showAddAccountModal = ref(false)
const showBackupModal = ref(false)
const showDeleteModal = ref(false)
const showMessagesModal = ref(false)
const selectedBackup = ref(null)
const backupMessages = ref([])
const messageSearch = ref('')

// Global Search
const globalSearchQuery = ref('')
const globalSearchResults = ref([])
const globalSearchTotal = ref(0)
const isSearching = ref(false)

// Links
const links = ref([])
const isLoadingLinks = ref(false)

// List Search Filters
const serverSearchQuery = ref('')
const dmSearchQuery = ref('')

// Add Account Form
const tokenInput = ref('')
const isAddingAccount = ref(false)

// Backup Form
const backupForm = reactive({
  account_id: '',
  channel_id: '',
  server_id: '',
  backup_mode: 'full', // 'full', 'media_only', 'links_only'
  include_media: true,
  include_reactions: true,
  include_threads: false,
  include_embeds: true,
  date_from: '',
  date_to: '',
})

// Delete Form
const deleteForm = reactive({
  account_id: '',
  discord_channel_id: '',
  channel_name: '',
  date_from: '',
  date_to: '',
  keyword_filter: '',
})

// Computed
const accounts = computed(() => discordStore.accounts)
const servers = computed(() => discordStore.servers)
const dmChannels = computed(() => discordStore.dmChannels)
const backups = computed(() => discordStore.backups)
const deleteJobs = computed(() => discordStore.deleteJobs)
const isLoading = computed(() => discordStore.isLoading)
const isSyncing = computed(() => discordStore.isSyncing)
const selectedServer = computed(() => discordStore.selectedServer)

// Selected DM state
const selectedDM = ref(null)
const channelMedia = ref([])
const channelLinks = ref([])
const isLoadingChannelData = ref(false)

// Filtered servers (by search query)
const filteredServers = computed(() => {
  const query = serverSearchQuery.value.toLowerCase().trim()
  if (!query) return servers.value
  return servers.value.filter(s => s.name.toLowerCase().includes(query))
})

// Sorted and filtered DM channels (by last_message_id descending - newer messages have higher IDs)
const sortedDMChannels = computed(() => {
  const query = dmSearchQuery.value.toLowerCase().trim()
  let filtered = [...dmChannels.value]

  if (query) {
    filtered = filtered.filter(dm => {
      const name = (dm.recipient_username || dm.name || '').toLowerCase()
      return name.includes(query)
    })
  }

  return filtered.sort((a, b) => {
    // Discord snowflake IDs: higher = more recent
    const aId = BigInt(a.last_message_id || a.discord_channel_id || '0')
    const bId = BigInt(b.last_message_id || b.discord_channel_id || '0')
    return bId > aId ? 1 : bId < aId ? -1 : 0
  })
})

// Lifecycle
onMounted(async () => {
  await discordStore.loadAccounts()
  if (discordStore.selectedAccount) {
    await Promise.all([
      discordStore.loadServers(discordStore.selectedAccount),
      discordStore.loadDMChannels(discordStore.selectedAccount),
      discordStore.loadBackups(),
      discordStore.loadDeleteJobs(),
    ])
  }
})

// Watch for account change
watch(() => discordStore.selectedAccount, async (newVal) => {
  if (newVal) {
    await Promise.all([
      discordStore.loadServers(newVal),
      discordStore.loadDMChannels(newVal),
    ])
  }
})

// Methods
async function addAccount() {
  if (!tokenInput.value.trim()) {
    uiStore.showError('Bitte Token eingeben')
    return
  }

  isAddingAccount.value = true
  try {
    await discordStore.addAccount(tokenInput.value.trim())
    await discordStore.syncAccount(discordStore.selectedAccount)
    uiStore.showSuccess('Discord Account hinzugefügt')
    tokenInput.value = ''
    showAddAccountModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Hinzufügen')
  } finally {
    isAddingAccount.value = false
  }
}

async function removeAccount(account) {
  if (!await confirm({
    title: 'Account entfernen?',
    message: `"${account.discord_username}" wirklich entfernen? Alle zugehörigen Backups werden ebenfalls gelöscht.`,
    type: 'danger'
  })) return

  try {
    await discordStore.deleteAccount(account.id)
    uiStore.showSuccess('Account entfernt')
  } catch (error) {
    uiStore.showError('Fehler beim Entfernen')
  }
}

async function syncAccount(accountId) {
  try {
    const result = await discordStore.syncAccount(accountId)
    uiStore.showSuccess(`${result.servers_synced} Server und ${result.dm_channels_synced} DMs synchronisiert`)
  } catch (error) {
    uiStore.showError('Fehler beim Synchronisieren')
  }
}

async function selectServer(server) {
  selectedDM.value = null // Clear DM selection
  await discordStore.loadServerChannels(server.id)

  // If no channels found, sync them from Discord
  if (!discordStore.selectedServer?.channels?.length) {
    try {
      await discordStore.syncServerChannels(server.id)
    } catch (error) {
      console.error('Failed to sync server channels:', error)
    }
  }
}

async function selectDM(dm) {
  discordStore.selectedServer = null // Clear server selection
  selectedDM.value = dm
  channelMedia.value = []
  channelLinks.value = []

  // Load media and links for this channel
  if (dm.discord_channel_id) {
    isLoadingChannelData.value = true
    try {
      const [mediaResult, linksResult] = await Promise.all([
        discordStore.loadChannelMedia(dm.discord_channel_id),
        discordStore.loadChannelLinks(dm.discord_channel_id)
      ])
      channelMedia.value = mediaResult?.items || []
      channelLinks.value = linksResult?.items || []
    } catch (error) {
      console.error('Failed to load channel data:', error)
    } finally {
      isLoadingChannelData.value = false
    }
  }
}

async function toggleFavorite(server) {
  await discordStore.toggleServerFavorite(server.id)
}

function openBackupModal(channel = null, server = null) {
  backupForm.account_id = discordStore.selectedAccount
  backupForm.channel_id = channel?.id || ''
  backupForm.server_id = server?.id || ''
  backupForm.include_media = true
  backupForm.include_reactions = true
  backupForm.include_threads = false
  backupForm.include_embeds = true
  backupForm.date_from = ''
  backupForm.date_to = ''
  showBackupModal.value = true
}

async function createBackup() {
  try {
    const data = {
      account_id: backupForm.account_id,
      include_media: backupForm.include_media,
      include_reactions: backupForm.include_reactions,
      include_threads: backupForm.include_threads,
      include_embeds: backupForm.include_embeds,
    }

    if (backupForm.channel_id) {
      data.channel_id = backupForm.channel_id
    } else if (backupForm.server_id) {
      data.server_id = backupForm.server_id
    }

    if (backupForm.date_from) data.date_from = backupForm.date_from
    if (backupForm.date_to) data.date_to = backupForm.date_to

    await discordStore.createBackup(data)
    uiStore.showSuccess('Backup gestartet')
    showBackupModal.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen des Backups')
  }
}

async function deleteBackup(backup) {
  if (!await confirm({
    title: 'Backup löschen?',
    message: `Backup von "${backup.target_name}" wirklich löschen?`,
    type: 'danger'
  })) return

  try {
    await discordStore.deleteBackup(backup.id)
    uiStore.showSuccess('Backup gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

const ownerDiscordId = ref(null)

async function viewBackupMessages(backup) {
  selectedBackup.value = backup
  messageSearch.value = ''
  try {
    const result = await discordStore.loadBackupMessages(backup.id)
    backupMessages.value = result.items || []
    ownerDiscordId.value = result.owner_discord_id || null
    showMessagesModal.value = true
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Nachrichten')
  }
}

async function searchMessages() {
  if (!selectedBackup.value) return
  try {
    const result = await discordStore.loadBackupMessages(
      selectedBackup.value.id,
      1,
      50,
      messageSearch.value || null
    )
    backupMessages.value = result.items || []
  } catch (error) {
    uiStore.showError('Fehler bei der Suche')
  }
}

async function performGlobalSearch() {
  if (globalSearchQuery.value.length < 2) {
    uiStore.showError('Mindestens 2 Zeichen erforderlich')
    return
  }

  isSearching.value = true
  try {
    const result = await discordStore.searchMessages(globalSearchQuery.value)
    globalSearchResults.value = result.items || []
    globalSearchTotal.value = result.total || 0
  } catch (error) {
    uiStore.showError('Fehler bei der Suche')
  } finally {
    isSearching.value = false
  }
}

async function loadAllLinks() {
  isLoadingLinks.value = true
  try {
    const result = await discordStore.loadLinks()
    links.value = result.items || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Links')
  } finally {
    isLoadingLinks.value = false
  }
}

function openDeleteModal(channel) {
  deleteForm.account_id = discordStore.selectedAccount
  deleteForm.discord_channel_id = channel.discord_channel_id
  deleteForm.channel_name = channel.name
  deleteForm.date_from = ''
  deleteForm.date_to = ''
  deleteForm.keyword_filter = ''
  showDeleteModal.value = true
}

async function createDeleteJob() {
  if (!await confirm({
    title: 'Nachrichten löschen?',
    message: 'Diese Aktion kann nicht rückgängig gemacht werden! Deine Nachrichten werden permanent gelöscht.',
    type: 'danger'
  })) return

  try {
    const data = {
      account_id: deleteForm.account_id,
      discord_channel_id: deleteForm.discord_channel_id,
    }

    if (deleteForm.date_from) data.date_from = deleteForm.date_from
    if (deleteForm.date_to) data.date_to = deleteForm.date_to
    if (deleteForm.keyword_filter) data.keyword_filter = deleteForm.keyword_filter

    await discordStore.createDeleteJob(data)
    uiStore.showSuccess('Lösch-Job gestartet')
    showDeleteModal.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen des Jobs')
  }
}

async function cancelDeleteJob(job) {
  try {
    await discordStore.cancelDeleteJob(job.id)
    uiStore.showSuccess('Job abgebrochen')
  } catch (error) {
    uiStore.showError('Fehler beim Abbrechen')
  }
}

function getStatusColor(status) {
  switch (status) {
    case 'completed': return 'text-green-400'
    case 'running': return 'text-blue-400'
    case 'pending': return 'text-yellow-400'
    case 'failed': return 'text-red-400'
    case 'cancelled': return 'text-gray-400'
    default: return 'text-gray-400'
  }
}

function getStatusLabel(status) {
  switch (status) {
    case 'completed': return 'Abgeschlossen'
    case 'running': return 'Läuft'
    case 'pending': return 'Wartend'
    case 'failed': return 'Fehlgeschlagen'
    case 'cancelled': return 'Abgebrochen'
    default: return status
  }
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleString('de-DE')
}

function formatSize(bytes) {
  if (!bytes) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  while (bytes >= 1024 && i < units.length - 1) {
    bytes /= 1024
    i++
  }
  return `${bytes.toFixed(1)} ${units[i]}`
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">Discord Manager</h1>
        <p class="text-gray-400 mt-1">Backups erstellen, Medien herunterladen, Nachrichten verwalten</p>
      </div>

      <div class="flex gap-3">
        <button @click="showAddAccountModal = true" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Account hinzufügen
        </button>
      </div>
    </div>

    <!-- Account Selector -->
    <div v-if="accounts.length > 0" class="card p-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <label class="text-sm text-gray-400">Account:</label>
          <select
            v-model="discordStore.selectedAccount"
            class="input w-64"
          >
            <option v-for="account in accounts" :key="account.id" :value="account.id">
              {{ account.discord_username }}#{{ account.discord_discriminator }}
            </option>
          </select>
        </div>

        <div class="flex gap-2">
          <button
            @click="syncAccount(discordStore.selectedAccount)"
            :disabled="isSyncing"
            class="btn-secondary"
          >
            <ArrowPathIcon class="w-5 h-5 mr-2" :class="{ 'animate-spin': isSyncing }" />
            Sync
          </button>
          <button
            @click="removeAccount(accounts.find(a => a.id === discordStore.selectedAccount))"
            class="btn-danger"
          >
            <TrashIcon class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>

    <!-- No Account State -->
    <div v-if="accounts.length === 0 && !isLoading" class="card p-12 text-center">
      <ChatBubbleLeftRightIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
      <h3 class="text-xl font-medium text-white mb-2">Kein Discord Account verbunden</h3>
      <p class="text-gray-400 mb-6">Füge deinen Discord User Token hinzu, um loszulegen.</p>
      <button @click="showAddAccountModal = true" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Account hinzufügen
      </button>
    </div>

    <!-- Main Content -->
    <div v-if="accounts.length > 0" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left: Server/DM Browser -->
      <div class="lg:col-span-1 space-y-4">
        <!-- Tabs -->
        <div class="flex border-b border-dark-600">
          <button
            @click="activeTab = 'servers'"
            :class="['px-4 py-2 text-sm font-medium border-b-2 -mb-px', activeTab === 'servers' ? 'text-primary-400 border-primary-400' : 'text-gray-400 border-transparent hover:text-white']"
          >
            <ServerIcon class="w-4 h-4 inline mr-1" />
            Server
          </button>
          <button
            @click="activeTab = 'dms'"
            :class="['px-4 py-2 text-sm font-medium border-b-2 -mb-px', activeTab === 'dms' ? 'text-primary-400 border-primary-400' : 'text-gray-400 border-transparent hover:text-white']"
          >
            <UserIcon class="w-4 h-4 inline mr-1" />
            DMs
          </button>
          <button
            @click="activeTab = 'search'"
            :class="['px-4 py-2 text-sm font-medium border-b-2 -mb-px', activeTab === 'search' ? 'text-primary-400 border-primary-400' : 'text-gray-400 border-transparent hover:text-white']"
          >
            <MagnifyingGlassIcon class="w-4 h-4 inline mr-1" />
            Suche
          </button>
          <button
            @click="activeTab = 'links'; loadAllLinks()"
            :class="['px-4 py-2 text-sm font-medium border-b-2 -mb-px', activeTab === 'links' ? 'text-primary-400 border-primary-400' : 'text-gray-400 border-transparent hover:text-white']"
          >
            <LinkIcon class="w-4 h-4 inline mr-1" />
            Links
          </button>
        </div>

        <!-- Server List -->
        <div v-if="activeTab === 'servers'" class="card">
          <!-- Search Input -->
          <div class="p-3 border-b border-dark-600">
            <div class="relative">
              <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
              <input
                v-model="serverSearchQuery"
                type="text"
                class="input pl-9 py-2 text-sm w-full"
                placeholder="Server suchen..."
              />
            </div>
          </div>

          <div class="divide-y divide-dark-600 max-h-[450px] overflow-y-auto">
            <div
              v-for="server in filteredServers"
              :key="server.id"
              @click="selectServer(server)"
              :class="['p-4 cursor-pointer hover:bg-dark-700 transition-colors', selectedServer?.id === server.id ? 'bg-dark-700' : '']"
            >
              <div class="flex items-center gap-3">
                <img
                  v-if="server.icon_url"
                  :src="server.icon_url"
                  class="w-10 h-10 rounded-full"
                  alt=""
                />
                <div v-else class="w-10 h-10 rounded-full bg-dark-600 flex items-center justify-center">
                  <ServerIcon class="w-5 h-5 text-gray-400" />
                </div>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="font-medium text-white truncate">{{ server.name }}</span>
                    <button @click.stop="toggleFavorite(server)" class="text-gray-400 hover:text-yellow-400">
                      <StarSolidIcon v-if="server.is_favorite" class="w-4 h-4 text-yellow-400" />
                      <StarIcon v-else class="w-4 h-4" />
                    </button>
                  </div>
                  <span class="text-sm text-gray-500">{{ server.channel_count }} Channels</span>
                </div>

                <ChevronRightIcon class="w-5 h-5 text-gray-500" />
              </div>
            </div>

            <div v-if="filteredServers.length === 0 && serverSearchQuery" class="p-8 text-center text-gray-500">
              Keine Server mit "{{ serverSearchQuery }}" gefunden
            </div>
            <div v-else-if="servers.length === 0" class="p-8 text-center text-gray-500">
              Keine Server gefunden
            </div>
          </div>
        </div>

        <!-- DM List -->
        <div v-if="activeTab === 'dms'" class="card">
          <!-- Search Input -->
          <div class="p-3 border-b border-dark-600">
            <div class="relative">
              <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
              <input
                v-model="dmSearchQuery"
                type="text"
                class="input pl-9 py-2 text-sm w-full"
                placeholder="DM suchen..."
              />
            </div>
          </div>

          <div class="divide-y divide-dark-600 max-h-[450px] overflow-y-auto">
            <div
              v-for="dm in sortedDMChannels"
              :key="dm.id"
              @click="selectDM(dm)"
              :class="['p-4 cursor-pointer hover:bg-dark-700 transition-colors', selectedDM?.id === dm.id ? 'bg-dark-700' : '']"
            >
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-dark-600 flex items-center justify-center overflow-hidden">
                  <img
                    v-if="dm.recipient_avatar && dm.recipient_id"
                    :src="`https://cdn.discordapp.com/avatars/${dm.recipient_id}/${dm.recipient_avatar}.png?size=64`"
                    class="w-full h-full object-cover"
                    alt=""
                    @error="$event.target.style.display='none'"
                  />
                  <UserIcon v-else class="w-5 h-5 text-gray-400" />
                </div>

                <div class="flex-1 min-w-0">
                  <span class="font-medium text-white truncate block">{{ dm.recipient_username || dm.name }}</span>
                  <span class="text-sm text-gray-500">{{ dm.backup_count || 0 }} Backups</span>
                </div>

                <ChevronRightIcon class="w-5 h-5 text-gray-500" />
              </div>
            </div>

            <div v-if="sortedDMChannels.length === 0 && dmSearchQuery" class="p-8 text-center text-gray-500">
              Keine DMs mit "{{ dmSearchQuery }}" gefunden
            </div>
            <div v-else-if="dmChannels.length === 0" class="p-8 text-center text-gray-500">
              Keine DMs gefunden
            </div>
          </div>
        </div>

        <!-- Search Panel -->
        <div v-if="activeTab === 'search'" class="card p-4 space-y-4">
          <div class="flex gap-2">
            <input
              v-model="globalSearchQuery"
              type="text"
              class="input flex-1"
              placeholder="Nachrichten durchsuchen..."
              @keyup.enter="performGlobalSearch"
            />
            <button
              @click="performGlobalSearch"
              :disabled="isSearching"
              class="btn-primary"
            >
              <ArrowPathIcon v-if="isSearching" class="w-5 h-5 animate-spin" />
              <MagnifyingGlassIcon v-else class="w-5 h-5" />
            </button>
          </div>

          <p class="text-sm text-gray-500">
            Durchsuche alle gesicherten Nachrichten. Mindestens 2 Zeichen erforderlich.
          </p>

          <div v-if="globalSearchResults.length > 0" class="text-sm text-gray-400">
            {{ globalSearchTotal }} Ergebnisse gefunden
          </div>
        </div>
      </div>

      <!-- Right: Channel Details / Backups -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Search Results -->
        <div v-if="activeTab === 'search' && globalSearchResults.length > 0" class="card">
          <div class="p-4 border-b border-dark-600">
            <h3 class="font-semibold text-white">Suchergebnisse</h3>
            <span class="text-sm text-gray-400">{{ globalSearchTotal }} Nachrichten gefunden</span>
          </div>

          <div class="divide-y divide-dark-600 max-h-[600px] overflow-y-auto">
            <div
              v-for="msg in globalSearchResults"
              :key="msg.id"
              class="p-4 hover:bg-dark-700"
            >
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-dark-600 flex items-center justify-center flex-shrink-0">
                  <UserIcon class="w-5 h-5 text-gray-400" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-medium text-white">{{ msg.author_username }}</span>
                    <span class="text-xs text-gray-500">{{ formatDate(msg.message_timestamp) }}</span>
                    <span class="text-xs text-primary-400 bg-primary-500/10 px-2 py-0.5 rounded">{{ msg.backup_name }}</span>
                  </div>
                  <p class="text-gray-300 mt-1 whitespace-pre-wrap break-words">{{ msg.content }}</p>
                  <div v-if="msg.has_attachments" class="flex items-center gap-1 mt-2 text-sm text-gray-400">
                    <PhotoIcon class="w-4 h-4" />
                    {{ msg.attachment_count }} Anhänge
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- No Search Results -->
        <div v-if="activeTab === 'search' && globalSearchQuery.length >= 2 && globalSearchResults.length === 0 && !isSearching" class="card p-12 text-center">
          <MagnifyingGlassIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
          <h3 class="text-xl font-medium text-white mb-2">Keine Ergebnisse</h3>
          <p class="text-gray-400">Keine Nachrichten mit "{{ globalSearchQuery }}" gefunden.</p>
        </div>

        <!-- Links Panel -->
        <div v-if="activeTab === 'links'" class="card">
          <div class="p-4 border-b border-dark-600 flex items-center justify-between">
            <h3 class="text-lg font-medium text-white">
              <LinkIcon class="w-5 h-5 inline mr-2" />
              Link-Sammlung
            </h3>
            <span class="text-sm text-gray-400">{{ links.length }} Links gefunden</span>
          </div>

          <!-- Loading -->
          <div v-if="isLoadingLinks" class="p-8 text-center">
            <ArrowPathIcon class="w-8 h-8 mx-auto text-primary-400 animate-spin" />
            <p class="text-gray-400 mt-2">Lade Links...</p>
          </div>

          <!-- Links List -->
          <div v-else-if="links.length > 0" class="divide-y divide-dark-600 max-h-[600px] overflow-y-auto">
            <div v-for="link in links" :key="link.url + link.message_id" class="p-4 hover:bg-dark-700">
              <div class="flex items-start gap-3">
                <LinkIcon class="w-5 h-5 text-primary-400 flex-shrink-0 mt-0.5" />
                <div class="flex-1 min-w-0">
                  <a
                    :href="link.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-primary-400 hover:text-primary-300 break-all text-sm"
                  >
                    {{ link.url }}
                  </a>
                  <div class="flex items-center gap-4 mt-1 text-xs text-gray-500">
                    <span>{{ link.author_username }}</span>
                    <span>{{ formatDate(link.message_timestamp) }}</span>
                    <span v-if="link.backup_name" class="bg-dark-600 px-2 py-0.5 rounded">{{ link.backup_name }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- No Links -->
          <div v-else class="p-12 text-center">
            <LinkIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
            <h3 class="text-xl font-medium text-white mb-2">Keine Links gefunden</h3>
            <p class="text-gray-400">Erstelle zuerst ein Backup um Links zu sammeln.</p>
          </div>
        </div>

        <!-- Selected DM User Info -->
        <div v-if="selectedDM" class="card">
          <div class="p-6">
            <div class="flex items-start gap-6">
              <!-- Avatar -->
              <div class="w-20 h-20 rounded-full bg-dark-600 flex items-center justify-center overflow-hidden flex-shrink-0">
                <img
                  v-if="selectedDM.recipient_avatar && selectedDM.recipient_id"
                  :src="`https://cdn.discordapp.com/avatars/${selectedDM.recipient_id}/${selectedDM.recipient_avatar}.png?size=128`"
                  class="w-full h-full object-cover"
                  alt=""
                  @error="$event.target.style.display='none'"
                />
                <UserIcon v-else class="w-10 h-10 text-gray-400" />
              </div>

              <!-- User Info -->
              <div class="flex-1">
                <h3 class="text-2xl font-bold text-white">{{ selectedDM.recipient_username || selectedDM.name }}</h3>
                <p class="text-gray-400 mt-1">
                  <span v-if="selectedDM.type === 'dm'">Direktnachricht</span>
                  <span v-else-if="selectedDM.type === 'group_dm'">Gruppen-DM</span>
                </p>

                <div class="mt-4 flex flex-wrap gap-4 text-sm">
                  <div class="bg-dark-700 rounded-lg px-4 py-2">
                    <span class="text-gray-500">Discord ID</span>
                    <p class="text-white font-mono">{{ selectedDM.recipient_id || selectedDM.discord_channel_id }}</p>
                  </div>
                  <div class="bg-dark-700 rounded-lg px-4 py-2">
                    <span class="text-gray-500">Backups</span>
                    <p class="text-white">{{ selectedDM.backup_count || 0 }}</p>
                  </div>
                  <div class="bg-dark-700 rounded-lg px-4 py-2">
                    <span class="text-gray-500">Synchronisiert</span>
                    <p class="text-white">{{ formatDate(selectedDM.cached_at) }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex gap-3">
              <button @click="openBackupModal(selectedDM)" class="btn-primary">
                <CloudArrowDownIcon class="w-5 h-5 mr-2" />
                Chat Backup erstellen
              </button>
              <button @click="openDeleteModal(selectedDM)" class="btn-danger">
                <TrashIcon class="w-5 h-5 mr-2" />
                Meine Nachrichten löschen
              </button>
            </div>
          </div>

          <!-- Loading Channel Data -->
          <div v-if="isLoadingChannelData" class="p-6 border-t border-dark-600 text-center">
            <ArrowPathIcon class="w-6 h-6 mx-auto text-primary-400 animate-spin" />
            <p class="text-gray-400 text-sm mt-2">Lade Medien & Links...</p>
          </div>

          <!-- Media Gallery -->
          <div v-else-if="channelMedia.length > 0" class="p-6 border-t border-dark-600">
            <h4 class="text-lg font-medium text-white mb-4 flex items-center gap-2">
              <PhotoIcon class="w-5 h-5" />
              Bilder & Medien ({{ channelMedia.length }})
            </h4>
            <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-2 max-h-[300px] overflow-y-auto">
              <a
                v-for="media in channelMedia"
                :key="media.id"
                :href="getMediaUrl(media.id)"
                target="_blank"
                class="aspect-square bg-dark-700 rounded-lg overflow-hidden hover:ring-2 hover:ring-primary-500 transition-all"
              >
                <img
                  v-if="media.mime_type?.startsWith('image/')"
                  :src="getMediaUrl(media.id)"
                  class="w-full h-full object-cover"
                  :alt="media.filename"
                  loading="lazy"
                />
                <div v-else class="w-full h-full flex items-center justify-center">
                  <DocumentTextIcon class="w-8 h-8 text-gray-500" />
                </div>
              </a>
            </div>
          </div>

          <!-- Links -->
          <div v-if="channelLinks.length > 0" class="p-6 border-t border-dark-600">
            <h4 class="text-lg font-medium text-white mb-4 flex items-center gap-2">
              <LinkIcon class="w-5 h-5" />
              Links ({{ channelLinks.length }})
            </h4>
            <div class="space-y-2 max-h-[200px] overflow-y-auto">
              <a
                v-for="link in channelLinks"
                :key="link.url + link.message_id"
                :href="link.url"
                target="_blank"
                rel="noopener noreferrer"
                class="block p-2 bg-dark-700 rounded hover:bg-dark-600 transition-colors"
              >
                <span class="text-primary-400 text-sm break-all">{{ link.url }}</span>
                <div class="text-xs text-gray-500 mt-1">
                  {{ link.author_username }} - {{ formatDate(link.message_timestamp) }}
                </div>
              </a>
            </div>
          </div>

          <!-- No Data Yet -->
          <div v-if="!isLoadingChannelData && channelMedia.length === 0 && channelLinks.length === 0" class="p-6 border-t border-dark-600 text-center text-gray-500">
            <p>Keine Medien oder Links gefunden. Erstelle ein Backup um Inhalte zu sammeln.</p>
          </div>
        </div>

        <!-- Selected Server Channels -->
        <div v-if="selectedServer" class="card">
          <div class="p-4 border-b border-dark-600">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <img
                  v-if="selectedServer.icon_url"
                  :src="selectedServer.icon_url"
                  class="w-10 h-10 rounded-full"
                  alt=""
                />
                <div>
                  <h3 class="font-semibold text-white">{{ selectedServer.name }}</h3>
                  <span class="text-sm text-gray-400">{{ selectedServer.channels?.length || 0 }} Channels</span>
                </div>
              </div>
              <button @click="openBackupModal(null, selectedServer)" class="btn-primary">
                <CloudArrowDownIcon class="w-5 h-5 mr-2" />
                Server Backup
              </button>
            </div>
          </div>

          <div class="divide-y divide-dark-600 max-h-[400px] overflow-y-auto">
            <div
              v-for="channel in selectedServer.channels"
              :key="channel.id"
              class="p-4 hover:bg-dark-700"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <HashtagIcon class="w-5 h-5 text-gray-400" />
                  <span class="text-white">{{ channel.name }}</span>
                  <span class="text-xs text-gray-500 bg-dark-600 px-2 py-0.5 rounded">{{ channel.type }}</span>
                </div>

                <div class="flex gap-2">
                  <button @click="openBackupModal(channel)" class="btn-sm btn-secondary" title="Backup">
                    <CloudArrowDownIcon class="w-4 h-4" />
                  </button>
                  <button @click="openDeleteModal(channel)" class="btn-sm btn-danger" title="Nachrichten löschen">
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Backups -->
        <div class="card">
          <div class="p-4 border-b border-dark-600 flex items-center justify-between">
            <h3 class="font-semibold text-white">Backups</h3>
            <span class="text-sm text-gray-400">{{ backups.length }} Backups</span>
          </div>

          <div class="divide-y divide-dark-600 max-h-[400px] overflow-y-auto">
            <div
              v-for="backup in backups"
              :key="backup.id"
              class="p-4"
            >
              <div class="flex items-start justify-between">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="font-medium text-white">{{ backup.target_name }}</span>
                    <span :class="['text-xs px-2 py-0.5 rounded', getStatusColor(backup.status)]">
                      {{ getStatusLabel(backup.status) }}
                    </span>
                  </div>
                  <div class="text-sm text-gray-500 mt-1">
                    {{ formatDate(backup.created_at) }} &bull;
                    {{ backup.messages_processed }} Nachrichten &bull;
                    {{ backup.media_count }} Medien ({{ formatSize(backup.media_size) }})
                  </div>
                  <div v-if="backup.status === 'running'" class="mt-2">
                    <div class="w-full bg-dark-600 rounded-full h-2">
                      <div
                        class="bg-primary-500 h-2 rounded-full transition-all"
                        :style="{ width: backup.progress_percent + '%' }"
                      ></div>
                    </div>
                    <span class="text-xs text-gray-400">{{ backup.current_action || 'Verarbeite...' }}</span>
                  </div>
                </div>

                <div class="flex gap-2">
                  <button
                    v-if="backup.status === 'completed'"
                    @click="viewBackupMessages(backup)"
                    class="btn-sm btn-secondary"
                    title="Nachrichten anzeigen"
                  >
                    <DocumentTextIcon class="w-4 h-4" />
                  </button>
                  <button @click="deleteBackup(backup)" class="btn-sm btn-danger" title="Löschen">
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>

            <div v-if="backups.length === 0" class="p-8 text-center text-gray-500">
              Keine Backups vorhanden
            </div>
          </div>
        </div>

        <!-- Delete Jobs -->
        <div v-if="deleteJobs.length > 0" class="card">
          <div class="p-4 border-b border-dark-600">
            <h3 class="font-semibold text-white">Lösch-Jobs</h3>
          </div>

          <div class="divide-y divide-dark-600">
            <div
              v-for="job in deleteJobs"
              :key="job.id"
              class="p-4"
            >
              <div class="flex items-start justify-between">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="font-medium text-white">{{ job.channel_name }}</span>
                    <span :class="['text-xs px-2 py-0.5 rounded', getStatusColor(job.status)]">
                      {{ getStatusLabel(job.status) }}
                    </span>
                  </div>
                  <div class="text-sm text-gray-500 mt-1">
                    {{ job.deleted_messages }} / {{ job.total_messages }} gelöscht
                    <span v-if="job.failed_messages > 0" class="text-red-400">
                      ({{ job.failed_messages }} fehlgeschlagen)
                    </span>
                  </div>
                </div>

                <button
                  v-if="job.status === 'running' || job.status === 'pending'"
                  @click="cancelDeleteJob(job)"
                  class="btn-sm btn-danger"
                >
                  Abbrechen
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Account Modal -->
    <Teleport to="body">
      <div v-if="showAddAccountModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-dark-800 rounded-xl w-full max-w-lg">
          <div class="p-6 border-b border-dark-600">
            <div class="flex items-center justify-between">
              <h2 class="text-xl font-semibold text-white">Discord Account hinzufügen</h2>
              <button @click="showAddAccountModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-6 h-6" />
              </button>
            </div>
          </div>

          <div class="p-6 space-y-4">
            <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-lg p-4">
              <p class="text-sm text-yellow-400">
                <strong>Hinweis:</strong> Die Verwendung von User Tokens ist gegen die Discord ToS.
                Verwende diese Funktion nur für deine eigenen Daten und auf eigenes Risiko.
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-400 mb-2">Discord User Token</label>
              <input
                v-model="tokenInput"
                type="password"
                class="input w-full"
                placeholder="Dein Discord User Token..."
              />
              <p class="text-xs text-gray-500 mt-1">
                Öffne Discord im Browser → F12 → Network → Filtere nach "api" → Kopiere den "Authorization" Header
              </p>
            </div>
          </div>

          <div class="p-6 border-t border-dark-600 flex justify-end gap-3">
            <button @click="showAddAccountModal = false" class="btn-secondary">Abbrechen</button>
            <button @click="addAccount" :disabled="isAddingAccount" class="btn-primary">
              <ArrowPathIcon v-if="isAddingAccount" class="w-5 h-5 mr-2 animate-spin" />
              Hinzufügen
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Backup Modal -->
    <Teleport to="body">
      <div v-if="showBackupModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-dark-800 rounded-xl w-full max-w-lg">
          <div class="p-6 border-b border-dark-600">
            <div class="flex items-center justify-between">
              <h2 class="text-xl font-semibold text-white">Backup erstellen</h2>
              <button @click="showBackupModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-6 h-6" />
              </button>
            </div>
          </div>

          <div class="p-6 space-y-4">
            <!-- Backup Mode -->
            <div>
              <label class="block text-sm font-medium text-gray-400 mb-2">Backup-Modus</label>
              <div class="grid grid-cols-3 gap-2">
                <label
                  :class="[
                    'flex flex-col items-center p-3 rounded-lg cursor-pointer border-2 transition-all',
                    backupForm.backup_mode === 'full'
                      ? 'border-primary-500 bg-primary-500/10'
                      : 'border-dark-600 hover:border-dark-500'
                  ]"
                >
                  <input v-model="backupForm.backup_mode" type="radio" value="full" class="sr-only" />
                  <ChatBubbleLeftRightIcon class="w-6 h-6 mb-1" :class="backupForm.backup_mode === 'full' ? 'text-primary-400' : 'text-gray-400'" />
                  <span class="text-sm" :class="backupForm.backup_mode === 'full' ? 'text-white' : 'text-gray-400'">Komplett</span>
                </label>
                <label
                  :class="[
                    'flex flex-col items-center p-3 rounded-lg cursor-pointer border-2 transition-all',
                    backupForm.backup_mode === 'media_only'
                      ? 'border-primary-500 bg-primary-500/10'
                      : 'border-dark-600 hover:border-dark-500'
                  ]"
                >
                  <input v-model="backupForm.backup_mode" type="radio" value="media_only" class="sr-only" />
                  <PhotoIcon class="w-6 h-6 mb-1" :class="backupForm.backup_mode === 'media_only' ? 'text-primary-400' : 'text-gray-400'" />
                  <span class="text-sm" :class="backupForm.backup_mode === 'media_only' ? 'text-white' : 'text-gray-400'">Nur Medien</span>
                </label>
                <label
                  :class="[
                    'flex flex-col items-center p-3 rounded-lg cursor-pointer border-2 transition-all',
                    backupForm.backup_mode === 'links_only'
                      ? 'border-primary-500 bg-primary-500/10'
                      : 'border-dark-600 hover:border-dark-500'
                  ]"
                >
                  <input v-model="backupForm.backup_mode" type="radio" value="links_only" class="sr-only" />
                  <LinkIcon class="w-6 h-6 mb-1" :class="backupForm.backup_mode === 'links_only' ? 'text-primary-400' : 'text-gray-400'" />
                  <span class="text-sm" :class="backupForm.backup_mode === 'links_only' ? 'text-white' : 'text-gray-400'">Nur Links</span>
                </label>
              </div>
              <p class="text-xs text-gray-500 mt-2">
                <span v-if="backupForm.backup_mode === 'full'">Alle Nachrichten, Medien und Links werden gesichert.</span>
                <span v-else-if="backupForm.backup_mode === 'media_only'">Nur Bilder und Dateien werden heruntergeladen.</span>
                <span v-else>Nur Links aus den Nachrichten werden extrahiert.</span>
              </p>
            </div>

            <!-- Options (only for full mode) -->
            <div v-if="backupForm.backup_mode === 'full'" class="grid grid-cols-2 gap-4">
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="backupForm.include_media" type="checkbox" class="checkbox" />
                <span class="text-white">Medien herunterladen</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="backupForm.include_reactions" type="checkbox" class="checkbox" />
                <span class="text-white">Reaktionen</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="backupForm.include_embeds" type="checkbox" class="checkbox" />
                <span class="text-white">Embeds</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="backupForm.include_threads" type="checkbox" class="checkbox" />
                <span class="text-white">Threads</span>
              </label>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Von (optional)</label>
                <input v-model="backupForm.date_from" type="datetime-local" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Bis (optional)</label>
                <input v-model="backupForm.date_to" type="datetime-local" class="input w-full" />
              </div>
            </div>
          </div>

          <div class="p-6 border-t border-dark-600 flex justify-end gap-3">
            <button @click="showBackupModal = false" class="btn-secondary">Abbrechen</button>
            <button @click="createBackup" class="btn-primary">
              <CloudArrowDownIcon class="w-5 h-5 mr-2" />
              Backup starten
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete Modal -->
    <Teleport to="body">
      <div v-if="showDeleteModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-dark-800 rounded-xl w-full max-w-lg">
          <div class="p-6 border-b border-dark-600">
            <div class="flex items-center justify-between">
              <h2 class="text-xl font-semibold text-white">Nachrichten löschen</h2>
              <button @click="showDeleteModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-6 h-6" />
              </button>
            </div>
          </div>

          <div class="p-6 space-y-4">
            <div class="bg-red-500/10 border border-red-500/20 rounded-lg p-4">
              <p class="text-sm text-red-400">
                <strong>Warnung:</strong> Diese Aktion löscht permanent alle deine Nachrichten im Channel
                <strong>{{ deleteForm.channel_name }}</strong>. Dies kann nicht rückgängig gemacht werden!
              </p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Von (optional)</label>
                <input v-model="deleteForm.date_from" type="datetime-local" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-400 mb-2">Bis (optional)</label>
                <input v-model="deleteForm.date_to" type="datetime-local" class="input w-full" />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-400 mb-2">Keyword Filter (optional)</label>
              <input
                v-model="deleteForm.keyword_filter"
                type="text"
                class="input w-full"
                placeholder="Nur Nachrichten mit diesem Text löschen..."
              />
            </div>
          </div>

          <div class="p-6 border-t border-dark-600 flex justify-end gap-3">
            <button @click="showDeleteModal = false" class="btn-secondary">Abbrechen</button>
            <button @click="createDeleteJob" class="btn-danger">
              <TrashIcon class="w-5 h-5 mr-2" />
              Löschen starten
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Messages Modal -->
    <Teleport to="body">
      <div v-if="showMessagesModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-dark-800 rounded-xl w-full max-w-4xl max-h-[80vh] flex flex-col">
          <div class="p-6 border-b border-dark-600">
            <div class="flex items-center justify-between">
              <h2 class="text-xl font-semibold text-white">Backup: {{ selectedBackup?.target_name }}</h2>
              <button @click="showMessagesModal = false" class="text-gray-400 hover:text-white">
                <XMarkIcon class="w-6 h-6" />
              </button>
            </div>

            <div class="mt-4 flex gap-2">
              <input
                v-model="messageSearch"
                type="text"
                class="input flex-1"
                placeholder="Nachrichten durchsuchen..."
                @keyup.enter="searchMessages"
              />
              <button @click="searchMessages" class="btn-secondary">
                <MagnifyingGlassIcon class="w-5 h-5" />
              </button>
            </div>
          </div>

          <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-dark-900">
            <div
              v-for="msg in backupMessages"
              :key="msg.id"
              :class="[
                'flex',
                msg.discord_author_id === ownerDiscordId ? 'justify-end' : 'justify-start'
              ]"
            >
              <div
                :class="[
                  'max-w-[75%] rounded-2xl px-4 py-2',
                  msg.discord_author_id === ownerDiscordId
                    ? 'bg-primary-600 text-white rounded-br-sm'
                    : 'bg-dark-700 text-gray-100 rounded-bl-sm'
                ]"
              >
                <!-- Author name (only for incoming messages) -->
                <div v-if="msg.discord_author_id !== ownerDiscordId" class="text-xs text-primary-400 font-medium mb-1">
                  {{ msg.author_username }}
                </div>

                <!-- Message content -->
                <p class="whitespace-pre-wrap break-words text-sm">{{ msg.content || '[Kein Text]' }}</p>

                <!-- Attachments -->
                <div v-if="msg.has_attachments" class="mt-2">
                  <div class="flex items-center gap-1 text-xs opacity-75">
                    <PhotoIcon class="w-3 h-3" />
                    {{ msg.attachment_count }} Anhänge
                  </div>
                </div>

                <!-- Timestamp -->
                <div
                  :class="[
                    'text-[10px] mt-1',
                    msg.discord_author_id === ownerDiscordId ? 'text-primary-200' : 'text-gray-500'
                  ]"
                >
                  {{ formatDate(msg.message_timestamp) }}
                </div>
              </div>
            </div>

            <div v-if="backupMessages.length === 0" class="text-center text-gray-500 py-8">
              Keine Nachrichten gefunden
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
