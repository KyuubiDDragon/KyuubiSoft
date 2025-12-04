<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import {
  PlusIcon,
  ServerIcon,
  MagnifyingGlassIcon,
  TrashIcon,
  PencilIcon,
  StarIcon,
  ClipboardIcon,
  EyeIcon,
  EyeSlashIcon,
  CommandLineIcon,
  CloudIcon,
  CircleStackIcon,
  GlobeAltIcon,
  TagIcon,
  FunnelIcon,
  CheckIcon,
  XMarkIcon,
  KeyIcon,
} from '@heroicons/vue/24/outline'
import { StarIcon as StarIconSolid } from '@heroicons/vue/24/solid'

const route = useRoute()
const uiStore = useUiStore()

// State
const connections = ref([])
const tags = ref([])
const isLoading = ref(true)
const searchQuery = ref('')
const selectedType = ref('')
const selectedTagId = ref('')
const showModal = ref(false)
const showCredentialsModal = ref(false)
const showTagModal = ref(false)
const editingConnection = ref(null)
const viewingCredentials = ref(null)
const showPassword = ref(false)
const showPrivateKey = ref(false)
const copiedField = ref('')

// Connection types
const connectionTypes = [
  { value: 'ssh', label: 'SSH', icon: CommandLineIcon, color: 'text-green-400', defaultPort: 22 },
  { value: 'sftp', label: 'SFTP', icon: CloudIcon, color: 'text-blue-400', defaultPort: 22 },
  { value: 'ftp', label: 'FTP', icon: CloudIcon, color: 'text-yellow-400', defaultPort: 21 },
  { value: 'database', label: 'Datenbank', icon: CircleStackIcon, color: 'text-purple-400', defaultPort: 3306 },
  { value: 'api', label: 'API', icon: GlobeAltIcon, color: 'text-cyan-400', defaultPort: 443 },
  { value: 'other', label: 'Andere', icon: ServerIcon, color: 'text-gray-400', defaultPort: null },
]

const colors = [
  '#6366f1', '#8b5cf6', '#ec4899', '#ef4444',
  '#f59e0b', '#10b981', '#06b6d4', '#3b82f6',
]

// Form
const form = reactive({
  name: '',
  description: '',
  type: 'ssh',
  host: '',
  port: 22,
  username: '',
  password: '',
  private_key: '',
  color: '#6366f1',
  is_favorite: false,
  tag_ids: [],
})

const tagForm = reactive({
  name: '',
  color: '#6366f1',
})

// Computed
const filteredConnections = computed(() => {
  let result = connections.value

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(c =>
      c.name.toLowerCase().includes(query) ||
      c.host.toLowerCase().includes(query) ||
      c.description?.toLowerCase().includes(query)
    )
  }

  if (selectedType.value) {
    result = result.filter(c => c.type === selectedType.value)
  }

  if (selectedTagId.value) {
    result = result.filter(c =>
      c.tags?.some(t => t.id === selectedTagId.value)
    )
  }

  return result
})

// API Calls
onMounted(async () => {
  await Promise.all([loadConnections(), loadTags()])

  // Check for ?open=id query parameter to auto-open a connection
  const openId = route.query.open
  if (openId) {
    const connection = connections.value.find(c => c.id === openId)
    if (connection) {
      showCredentials(connection)
    }
  }
})

async function loadConnections() {
  isLoading.value = true
  try {
    const response = await api.get('/api/v1/connections')
    connections.value = response.data.data?.items || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Verbindungen')
  } finally {
    isLoading.value = false
  }
}

async function loadTags() {
  try {
    const response = await api.get('/api/v1/connections/tags')
    tags.value = response.data.data?.items || []
  } catch (error) {
    console.error('Failed to load tags:', error)
  }
}

async function saveConnection() {
  try {
    const data = { ...form }

    if (editingConnection.value) {
      await api.put(`/api/v1/connections/${editingConnection.value.id}`, data)
      uiStore.showSuccess('Verbindung aktualisiert')
    } else {
      await api.post('/api/v1/connections', data)
      uiStore.showSuccess('Verbindung erstellt')
    }

    await loadConnections()
    closeModal()
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deleteConnection(connection) {
  if (!confirm(`Verbindung "${connection.name}" wirklich löschen?`)) return

  try {
    await api.delete(`/api/v1/connections/${connection.id}`)
    connections.value = connections.value.filter(c => c.id !== connection.id)
    uiStore.showSuccess('Verbindung gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function toggleFavorite(connection) {
  try {
    await api.put(`/api/v1/connections/${connection.id}`, {
      is_favorite: !connection.is_favorite,
    })
    connection.is_favorite = !connection.is_favorite
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

async function showCredentials(connection) {
  try {
    const response = await api.get(`/api/v1/connections/${connection.id}/credentials`)
    viewingCredentials.value = {
      ...connection,
      ...response.data.data,
    }
    showPassword.value = false
    showPrivateKey.value = false
    showCredentialsModal.value = true
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Zugangsdaten')
  }
}

async function saveTag() {
  try {
    await api.post('/api/v1/connections/tags', tagForm)
    await loadTags()
    showTagModal.value = false
    tagForm.name = ''
    tagForm.color = '#6366f1'
    uiStore.showSuccess('Tag erstellt')
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen')
  }
}

async function deleteTag(tag) {
  if (!confirm(`Tag "${tag.name}" wirklich löschen?`)) return

  try {
    await api.delete(`/api/v1/connections/tags/${tag.id}`)
    await loadTags()
    if (selectedTagId.value === tag.id) {
      selectedTagId.value = ''
    }
    uiStore.showSuccess('Tag gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Clipboard
async function copyToClipboard(text, fieldName) {
  try {
    await navigator.clipboard.writeText(text)
    copiedField.value = fieldName
    setTimeout(() => {
      copiedField.value = ''
    }, 2000)
  } catch (error) {
    uiStore.showError('Kopieren fehlgeschlagen')
  }
}

function copySSHCommand() {
  const conn = viewingCredentials.value
  const cmd = `ssh ${conn.username}@${conn.host}${conn.port !== 22 ? ` -p ${conn.port}` : ''}`
  copyToClipboard(cmd, 'ssh')
}

// Modal helpers
function openCreateModal() {
  editingConnection.value = null
  Object.assign(form, {
    name: '',
    description: '',
    type: 'ssh',
    host: '',
    port: 22,
    username: '',
    password: '',
    private_key: '',
    color: '#6366f1',
    is_favorite: false,
    tag_ids: [],
  })
  showModal.value = true
}

function openEditModal(connection) {
  editingConnection.value = connection
  Object.assign(form, {
    name: connection.name,
    description: connection.description || '',
    type: connection.type,
    host: connection.host,
    port: connection.port,
    username: connection.username || '',
    password: '',
    private_key: '',
    color: connection.color || '#6366f1',
    is_favorite: connection.is_favorite,
    tag_ids: connection.tags?.map(t => t.id) || [],
  })
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingConnection.value = null
}

function onTypeChange() {
  const typeInfo = connectionTypes.find(t => t.value === form.type)
  if (typeInfo?.defaultPort) {
    form.port = typeInfo.defaultPort
  }
}

function getTypeInfo(type) {
  return connectionTypes.find(t => t.value === type) || connectionTypes[5]
}

function formatDate(dateString) {
  if (!dateString) return 'Nie'
  return new Date(dateString).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">Verbindungen</h1>
        <p class="text-gray-400 mt-1">Verwalte deine Server- und Datenbankverbindungen</p>
      </div>
      <div class="flex gap-2">
        <button @click="showTagModal = true" class="btn-secondary">
          <TagIcon class="w-5 h-5 mr-2" />
          Tags
        </button>
        <button @click="openCreateModal" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Neue Verbindung
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-4">
      <div class="relative flex-1">
        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Suchen..."
          class="input pl-10 w-full"
        />
      </div>
      <select v-model="selectedType" class="input w-full sm:w-48">
        <option value="">Alle Typen</option>
        <option v-for="type in connectionTypes" :key="type.value" :value="type.value">
          {{ type.label }}
        </option>
      </select>
      <select v-model="selectedTagId" class="input w-full sm:w-48">
        <option value="">Alle Tags</option>
        <option v-for="tag in tags" :key="tag.id" :value="tag.id">
          {{ tag.name }}
        </option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Empty state -->
    <div v-else-if="filteredConnections.length === 0" class="card p-12 text-center">
      <ServerIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
      <h3 class="text-lg font-medium text-white mb-2">
        {{ searchQuery || selectedType || selectedTagId ? 'Keine Ergebnisse' : 'Keine Verbindungen' }}
      </h3>
      <p class="text-gray-400 mb-6">
        {{ searchQuery || selectedType || selectedTagId
          ? 'Versuche andere Suchkriterien'
          : 'Erstelle deine erste Verbindung'
        }}
      </p>
      <button v-if="!searchQuery && !selectedType && !selectedTagId" @click="openCreateModal" class="btn-primary">
        <PlusIcon class="w-5 h-5 mr-2" />
        Verbindung erstellen
      </button>
    </div>

    <!-- Connections grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="conn in filteredConnections"
        :key="conn.id"
        class="card-hover p-5 group"
      >
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-lg flex items-center justify-center"
              :style="{ backgroundColor: conn.color + '20' }"
            >
              <component
                :is="getTypeInfo(conn.type).icon"
                class="w-5 h-5"
                :class="getTypeInfo(conn.type).color"
              />
            </div>
            <div>
              <h3 class="font-medium text-white">{{ conn.name }}</h3>
              <p class="text-sm text-gray-500">{{ getTypeInfo(conn.type).label }}</p>
            </div>
          </div>
          <button
            @click="toggleFavorite(conn)"
            class="p-1.5 rounded hover:bg-dark-600 transition-colors"
          >
            <StarIconSolid v-if="conn.is_favorite" class="w-5 h-5 text-yellow-400" />
            <StarIcon v-else class="w-5 h-5 text-gray-500 hover:text-yellow-400" />
          </button>
        </div>

        <div class="space-y-2 mb-4">
          <div class="flex items-center gap-2 text-sm">
            <span class="text-gray-400">Host:</span>
            <span class="text-gray-200 font-mono">{{ conn.host }}</span>
          </div>
          <div v-if="conn.port" class="flex items-center gap-2 text-sm">
            <span class="text-gray-400">Port:</span>
            <span class="text-gray-200 font-mono">{{ conn.port }}</span>
          </div>
          <div v-if="conn.username" class="flex items-center gap-2 text-sm">
            <span class="text-gray-400">User:</span>
            <span class="text-gray-200 font-mono">{{ conn.username }}</span>
          </div>
        </div>

        <!-- Tags -->
        <div v-if="conn.tags?.length" class="flex flex-wrap gap-1 mb-4">
          <span
            v-for="tag in conn.tags"
            :key="tag.id"
            class="px-2 py-0.5 text-xs rounded-full bg-dark-600 text-gray-300"
          >
            {{ tag.name }}
          </span>
        </div>

        <div class="text-xs text-gray-500 mb-4">
          Zuletzt: {{ formatDate(conn.last_used_at) }}
        </div>

        <!-- Actions -->
        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
          <button
            @click="showCredentials(conn)"
            class="flex-1 btn-secondary py-2 text-sm"
          >
            <KeyIcon class="w-4 h-4 mr-1" />
            Zugangsdaten
          </button>
          <button
            @click="openEditModal(conn)"
            class="p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors"
          >
            <PencilIcon class="w-4 h-4" />
          </button>
          <button
            @click="deleteConnection(conn)"
            class="p-2 text-red-400 hover:text-red-300 hover:bg-red-400/10 rounded-lg transition-colors"
          >
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-lg border border-dark-700 max-h-[90vh] overflow-y-auto">
          <div class="p-6 border-b border-dark-700">
            <h2 class="text-xl font-bold text-white">
              {{ editingConnection ? 'Verbindung bearbeiten' : 'Neue Verbindung' }}
            </h2>
          </div>

          <form @submit.prevent="saveConnection" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="label">Name *</label>
                <input v-model="form.name" type="text" class="input" required placeholder="Mein Server" />
              </div>

              <div>
                <label class="label">Typ *</label>
                <select v-model="form.type" @change="onTypeChange" class="input">
                  <option v-for="type in connectionTypes" :key="type.value" :value="type.value">
                    {{ type.label }}
                  </option>
                </select>
              </div>

              <div>
                <label class="label">Farbe</label>
                <div class="flex gap-2">
                  <button
                    v-for="color in colors"
                    :key="color"
                    type="button"
                    @click="form.color = color"
                    class="w-8 h-8 rounded-lg border-2 transition-transform hover:scale-110"
                    :class="form.color === color ? 'border-white scale-110' : 'border-transparent'"
                    :style="{ backgroundColor: color }"
                  ></button>
                </div>
              </div>

              <div>
                <label class="label">Host *</label>
                <input v-model="form.host" type="text" class="input" required placeholder="192.168.1.1" />
              </div>

              <div>
                <label class="label">Port</label>
                <input v-model.number="form.port" type="number" class="input" placeholder="22" />
              </div>

              <div>
                <label class="label">Benutzername</label>
                <input v-model="form.username" type="text" class="input" placeholder="root" />
              </div>

              <div>
                <label class="label">Passwort</label>
                <input
                  v-model="form.password"
                  type="password"
                  class="input"
                  :placeholder="editingConnection ? '(unverändert)' : ''"
                />
              </div>

              <div class="col-span-2">
                <label class="label">Private Key (SSH)</label>
                <textarea
                  v-model="form.private_key"
                  class="input font-mono text-sm"
                  rows="3"
                  :placeholder="editingConnection ? '(unverändert)' : '-----BEGIN OPENSSH PRIVATE KEY-----'"
                ></textarea>
              </div>

              <div class="col-span-2">
                <label class="label">Beschreibung</label>
                <textarea v-model="form.description" class="input" rows="2" placeholder="Optional"></textarea>
              </div>

              <div class="col-span-2">
                <label class="label">Tags</label>
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="tag in tags"
                    :key="tag.id"
                    type="button"
                    @click="form.tag_ids.includes(tag.id)
                      ? form.tag_ids = form.tag_ids.filter(id => id !== tag.id)
                      : form.tag_ids.push(tag.id)"
                    class="px-3 py-1 rounded-full text-sm transition-colors"
                    :class="form.tag_ids.includes(tag.id)
                      ? 'bg-primary-600 text-white'
                      : 'bg-dark-600 text-gray-300 hover:bg-dark-500'"
                  >
                    {{ tag.name }}
                  </button>
                  <span v-if="tags.length === 0" class="text-gray-500 text-sm">
                    Noch keine Tags erstellt
                  </span>
                </div>
              </div>

              <div class="col-span-2">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input v-model="form.is_favorite" type="checkbox" class="checkbox" />
                  <span class="text-gray-300">Als Favorit markieren</span>
                </label>
              </div>
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" @click="closeModal" class="btn-secondary flex-1">
                Abbrechen
              </button>
              <button type="submit" class="btn-primary flex-1">
                {{ editingConnection ? 'Speichern' : 'Erstellen' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Credentials Modal -->
    <Teleport to="body">
      <div
        v-if="showCredentialsModal && viewingCredentials"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-lg border border-dark-700">
          <div class="p-6 border-b border-dark-700 flex items-center justify-between">
            <h2 class="text-xl font-bold text-white">{{ viewingCredentials.name }}</h2>
            <button @click="showCredentialsModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>

          <div class="p-6 space-y-4">
            <!-- Quick copy buttons for SSH -->
            <div v-if="viewingCredentials.type === 'ssh' || viewingCredentials.type === 'sftp'" class="flex gap-2 mb-4">
              <button @click="copySSHCommand" class="btn-primary flex-1">
                <CommandLineIcon class="w-4 h-4 mr-2" />
                {{ copiedField === 'ssh' ? 'Kopiert!' : 'SSH Befehl kopieren' }}
              </button>
            </div>

            <!-- Credentials list -->
            <div class="space-y-3">
              <div class="flex items-center justify-between p-3 bg-dark-700 rounded-lg">
                <div>
                  <span class="text-xs text-gray-400">Host</span>
                  <p class="font-mono text-white">{{ viewingCredentials.host }}</p>
                </div>
                <button
                  @click="copyToClipboard(viewingCredentials.host, 'host')"
                  class="p-2 text-gray-400 hover:text-white rounded transition-colors"
                >
                  <CheckIcon v-if="copiedField === 'host'" class="w-5 h-5 text-green-400" />
                  <ClipboardIcon v-else class="w-5 h-5" />
                </button>
              </div>

              <div v-if="viewingCredentials.port" class="flex items-center justify-between p-3 bg-dark-700 rounded-lg">
                <div>
                  <span class="text-xs text-gray-400">Port</span>
                  <p class="font-mono text-white">{{ viewingCredentials.port }}</p>
                </div>
                <button
                  @click="copyToClipboard(String(viewingCredentials.port), 'port')"
                  class="p-2 text-gray-400 hover:text-white rounded transition-colors"
                >
                  <CheckIcon v-if="copiedField === 'port'" class="w-5 h-5 text-green-400" />
                  <ClipboardIcon v-else class="w-5 h-5" />
                </button>
              </div>

              <div v-if="viewingCredentials.username" class="flex items-center justify-between p-3 bg-dark-700 rounded-lg">
                <div>
                  <span class="text-xs text-gray-400">Benutzername</span>
                  <p class="font-mono text-white">{{ viewingCredentials.username }}</p>
                </div>
                <button
                  @click="copyToClipboard(viewingCredentials.username, 'username')"
                  class="p-2 text-gray-400 hover:text-white rounded transition-colors"
                >
                  <CheckIcon v-if="copiedField === 'username'" class="w-5 h-5 text-green-400" />
                  <ClipboardIcon v-else class="w-5 h-5" />
                </button>
              </div>

              <div v-if="viewingCredentials.password" class="flex items-center justify-between p-3 bg-dark-700 rounded-lg">
                <div class="flex-1 min-w-0">
                  <span class="text-xs text-gray-400">Passwort</span>
                  <p class="font-mono text-white truncate">
                    {{ showPassword ? viewingCredentials.password : '••••••••••••' }}
                  </p>
                </div>
                <div class="flex gap-1">
                  <button
                    @click="showPassword = !showPassword"
                    class="p-2 text-gray-400 hover:text-white rounded transition-colors"
                  >
                    <EyeSlashIcon v-if="showPassword" class="w-5 h-5" />
                    <EyeIcon v-else class="w-5 h-5" />
                  </button>
                  <button
                    @click="copyToClipboard(viewingCredentials.password, 'password')"
                    class="p-2 text-gray-400 hover:text-white rounded transition-colors"
                  >
                    <CheckIcon v-if="copiedField === 'password'" class="w-5 h-5 text-green-400" />
                    <ClipboardIcon v-else class="w-5 h-5" />
                  </button>
                </div>
              </div>

              <div v-if="viewingCredentials.private_key" class="p-3 bg-dark-700 rounded-lg">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-xs text-gray-400">Private Key</span>
                  <div class="flex gap-1">
                    <button
                      @click="showPrivateKey = !showPrivateKey"
                      class="p-1 text-gray-400 hover:text-white rounded transition-colors"
                    >
                      <EyeSlashIcon v-if="showPrivateKey" class="w-4 h-4" />
                      <EyeIcon v-else class="w-4 h-4" />
                    </button>
                    <button
                      @click="copyToClipboard(viewingCredentials.private_key, 'key')"
                      class="p-1 text-gray-400 hover:text-white rounded transition-colors"
                    >
                      <CheckIcon v-if="copiedField === 'key'" class="w-4 h-4 text-green-400" />
                      <ClipboardIcon v-else class="w-4 h-4" />
                    </button>
                  </div>
                </div>
                <pre v-if="showPrivateKey" class="font-mono text-xs text-white overflow-x-auto max-h-32">{{ viewingCredentials.private_key }}</pre>
                <p v-else class="font-mono text-white text-sm">••••••••••••••••••••</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Tag Management Modal -->
    <Teleport to="body">
      <div
        v-if="showTagModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-md border border-dark-700">
          <div class="p-6 border-b border-dark-700 flex items-center justify-between">
            <h2 class="text-xl font-bold text-white">Tags verwalten</h2>
            <button @click="showTagModal = false" class="text-gray-400 hover:text-white">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>

          <div class="p-6">
            <!-- Create new tag -->
            <form @submit.prevent="saveTag" class="flex gap-2 mb-6">
              <input
                v-model="tagForm.name"
                type="text"
                class="input flex-1"
                placeholder="Neuer Tag..."
                required
              />
              <select v-model="tagForm.color" class="input w-24">
                <option v-for="color in colors" :key="color" :value="color">
                  {{ color }}
                </option>
              </select>
              <button type="submit" class="btn-primary">
                <PlusIcon class="w-5 h-5" />
              </button>
            </form>

            <!-- Tag list -->
            <div class="space-y-2">
              <div
                v-for="tag in tags"
                :key="tag.id"
                class="flex items-center justify-between p-3 bg-dark-700 rounded-lg"
              >
                <div class="flex items-center gap-2">
                  <div
                    class="w-3 h-3 rounded-full"
                    :style="{ backgroundColor: tag.color }"
                  ></div>
                  <span class="text-white">{{ tag.name }}</span>
                  <span class="text-xs text-gray-500">({{ tag.connection_count || 0 }})</span>
                </div>
                <button
                  @click="deleteTag(tag)"
                  class="p-1 text-red-400 hover:text-red-300 rounded transition-colors"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
              <p v-if="tags.length === 0" class="text-center text-gray-500 py-4">
                Noch keine Tags erstellt
              </p>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
