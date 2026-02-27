<script setup>
import { ref, onMounted, onUnmounted, defineAsyncComponent, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/core/api/axios'
import { useCollaboration } from '@/composables/useCollaboration'
import {
  DocumentTextIcon,
  LockClosedIcon,
  ExclamationCircleIcon,
  EyeIcon,
  CalendarIcon,
  UserIcon,
  UsersIcon,
  PencilSquareIcon,
  SignalIcon,
  SignalSlashIcon,
} from '@heroicons/vue/24/outline'
import TipTapEditor from '@/components/TipTapEditor.vue'

// Lazy load heavy editors
const MonacoEditor = defineAsyncComponent(() => import('@/components/MonacoEditor.vue'))
const CollaborativeTipTapEditor = defineAsyncComponent(() => import('@/components/CollaborativeTipTapEditor.vue'))
const CollaborativeMonacoEditor = defineAsyncComponent(() => import('@/components/CollaborativeMonacoEditor.vue'))
const UniverSheet = defineAsyncComponent(() => import('@/components/UniverSheet.vue'))

const route = useRoute()

// Duck name parts for random name generation (must be before refs that use it)
const duckAdjectives = [
  'Feuerwehr', 'Geröstete', 'Fliegende', 'Tanzende', 'Singende', 'Schnelle',
  'Mutige', 'Schlaue', 'Coole', 'Ninja', 'Pirat', 'Astronaut', 'Detektiv',
  'Zauberer', 'Ritter', 'Wikinger', 'Samurai', 'Cowboy', 'Superhelden',
  'Rockstar', 'DJ', 'Koch', 'Kapitän', 'Professor', 'Doktor', 'Agent',
  'Turbo', 'Mega', 'Ultra', 'Super', 'Hyper', 'Power', 'Laser', 'Pixel',
  'Goldene', 'Silberne', 'Diamant', 'Kristall', 'Regenbogen', 'Blitz',
  'Donner', 'Sturm', 'Nebel', 'Schatten', 'Licht', 'Feuer', 'Eis', 'Elektro'
]

// Generate random duck name
function getRandomDuckName() {
  const adjective = duckAdjectives[Math.floor(Math.random() * duckAdjectives.length)]
  return `${adjective} Ente`
}

// Get random color for cursor
function getRandomColor() {
  const colors = [
    '#3b82f6', // blue
    '#10b981', // green
    '#f59e0b', // amber
    '#ef4444', // red
    '#8b5cf6', // violet
    '#ec4899', // pink
    '#06b6d4', // cyan
    '#f97316', // orange
  ]
  return colors[Math.floor(Math.random() * colors.length)]
}

// Helper function to wait for Yjs sync
function waitForSync(provider, timeout = 2000) {
  return new Promise((resolve) => {
    // If already synced, resolve immediately
    if (provider.synced) {
      resolve(true)
      return
    }

    const timeoutId = setTimeout(() => {
      resolve(false) // Timeout reached, continue anyway
    }, timeout)

    provider.once('sync', (synced) => {
      clearTimeout(timeoutId)
      resolve(synced)
    })
  })
}

// State
const document = ref(null)
const loading = ref(true)
const error = ref('')
const requiresPassword = ref(false)
const passwordInput = ref('')
const passwordError = ref('')
const storedPassword = ref(null)

// Collaborative editing state
const canEdit = ref(false)
const isEditing = ref(false)
const editorName = ref(getRandomDuckName()) // Generate duck name immediately on page load
const userColor = ref(getRandomColor())
const collaborationAvailable = ref(false)
const isConnecting = ref(false)
const localContent = ref('') // Track local content for saving (only for non-collaborative mode)
const isSaving = ref(false)

// Collaboration
let collaboration = null
let ydoc = null
let provider = null
let ytext = null

// Ref for the collaborative editor to get HTML content
const collaborativeEditorRef = ref(null)

// Computed for showing connected users
const connectedUsers = computed(() => {
  if (!collaboration) return []
  return collaboration.connectedUsers.value
})

const isConnected = computed(() => {
  if (!collaboration) return false
  return collaboration.isConnected.value
})

const connectionError = computed(() => {
  if (!collaboration) return null
  return collaboration.connectionError.value
})

// Simple Markdown to HTML conversion
function renderMarkdown(content) {
  if (!content) return ''

  let html = content
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/^### (.*$)/gim, '<h3 class="text-lg font-semibold text-white mt-4 mb-2">$1</h3>')
    .replace(/^## (.*$)/gim, '<h2 class="text-xl font-semibold text-white mt-6 mb-3">$1</h2>')
    .replace(/^# (.*$)/gim, '<h1 class="text-2xl font-bold text-white mt-6 mb-4">$1</h1>')
    .replace(/\*\*\*(.*?)\*\*\*/gim, '<strong><em>$1</em></strong>')
    .replace(/\*\*(.*?)\*\*/gim, '<strong class="font-semibold text-white">$1</strong>')
    .replace(/\*(.*?)\*/gim, '<em class="italic">$1</em>')
    .replace(/```([\s\S]*?)```/gim, '<pre class="bg-white/[0.04] rounded-lg p-4 my-4 overflow-x-auto"><code>$1</code></pre>')
    .replace(/`(.*?)`/gim, '<code class="bg-white/[0.04] px-1.5 py-0.5 rounded text-primary-400">$1</code>')
    .replace(/^\- (.*$)/gim, '<li class="ml-4">$1</li>')
    .replace(/^\* (.*$)/gim, '<li class="ml-4">$1</li>')
    .replace(/^\d+\. (.*$)/gim, '<li class="ml-4 list-decimal">$1</li>')
    .replace(/\[(.*?)\]\((.*?)\)/gim, '<a href="$2" class="text-primary-400 hover:underline" target="_blank">$1</a>')
    .replace(/\n\n/gim, '</p><p class="text-gray-300 mb-4">')
    .replace(/\n/gim, '<br>')

  return `<p class="text-gray-300 mb-4">${html}</p>`
}

// Fetch document
async function fetchDocument(password = null) {
  loading.value = true
  error.value = ''
  passwordError.value = ''

  try {
    const token = route.params.token
    let response

    if (password) {
      response = await api.post(`/api/v1/documents/public/${token}`, { password })
      storedPassword.value = password
    } else {
      response = await api.get(`/api/v1/documents/public/${token}`)
    }

    document.value = response.data.data
    canEdit.value = response.data.data.can_edit || false
    requiresPassword.value = false
  } catch (err) {
    if (err.response?.status === 401 && err.response?.data?.errors?.requires_password) {
      requiresPassword.value = true
      if (password) {
        passwordError.value = 'Falsches Passwort'
      }
    } else if (err.response?.status === 403) {
      error.value = err.response?.data?.message || 'Dieser Link ist abgelaufen'
    } else if (err.response?.status === 404) {
      error.value = 'Dokument nicht gefunden oder nicht öffentlich freigegeben'
    } else {
      error.value = 'Fehler beim Laden des Dokuments'
    }
  } finally {
    loading.value = false
  }
}

// Submit password
async function submitPassword() {
  if (!passwordInput.value.trim()) {
    passwordError.value = 'Bitte Passwort eingeben'
    return
  }
  await fetchDocument(passwordInput.value)

  // Auto-start editing after successful password verification
  if (canEdit.value && !requiresPassword.value && !error.value) {
    startEditing()
  }
}

// Start editing - automatically join with duck name
function startEditing() {
  // Generate a random duck name
  editorName.value = getRandomDuckName()
  // Start the collaborative session directly
  joinCollaborativeSession()
}

// Join collaborative session
async function joinCollaborativeSession() {
  if (!editorName.value.trim()) {
    editorName.value = getRandomDuckName()
  }

  const token = route.params.token

  // Set isEditing immediately to prevent button flash
  isEditing.value = true
  isConnecting.value = true

  // Initialize collaboration
  collaboration = useCollaboration(token, {
    userName: editorName.value,
    userColor: userColor.value,
  })

  try {
    const result = await collaboration.connect()
    ydoc = result.ydoc
    provider = result.provider
    collaborationAvailable.value = result.isAvailable && result.provider !== null

    // Get the correct Yjs type based on document format
    if (document.value.format === 'code' || document.value.format === 'markdown') {
      if (collaborationAvailable.value) {
        // Wait for initial sync before checking content
        await waitForSync(provider, 2000)

        ytext = collaboration.getText('monaco')

        // Initialize with existing content if document has content and ytext is empty
        if (document.value.content && ytext.toString() === '') {
          ytext.insert(0, document.value.content)
        }
      }
    } else {
      // For richtext, wait for initial sync before proceeding
      // This ensures we know the true state of the Yjs document before creating the editor
      if (collaborationAvailable.value) {
        await waitForSync(provider, 2000) // Wait up to 2 seconds for initial sync
      }
      ytext = null
    }

    // In collaborative mode, saving happens automatically via the collaboration server
    // No need for frontend auto-save
  } catch (error) {
    console.error('Failed to join collaborative session:', error)
    collaborationAvailable.value = false
    // isEditing was already set to true at the start, keep editing even without collaboration
  } finally {
    isConnecting.value = false
  }
}

// Save document content to backend (only for non-collaborative mode fallback)
async function saveDocument() {
  // In collaborative mode, saving is handled by the collaboration server via Redis
  if (collaborationAvailable.value) {
    console.log('Automatisch über Collaboration-Server gespeichert')
    return
  }

  if (!document.value || isSaving.value) return

  // Get local content for non-collaborative mode
  let content = localContent.value || document.value.content

  // Don't save if content hasn't changed
  if (content === document.value.content) {
    return
  }

  isSaving.value = true

  try {
    const token = route.params.token
    await api.post(`/api/v1/documents/public/${token}/update`, {
      content: content,
      password: storedPassword.value
    })
    document.value.content = content
    console.log('Dokument gespeichert')
  } catch (error) {
    console.error('Fehler beim Speichern:', error)
  } finally {
    isSaving.value = false
  }
}

// Handle spreadsheet changes
function onSpreadsheetChange(content) {
  if (!collaborationAvailable.value) {
    localContent.value = content
    // Auto-save for non-collaborative mode
    saveDocument()
  }
}

// Leave collaborative session
async function leaveSession() {
  // In non-collaborative mode, save before leaving
  if (!collaborationAvailable.value) {
    await saveDocument()
  } else if (collaborationAvailable.value && document.value) {
    // In collaborative mode, save HTML content for richtext documents
    if (document.value.format === 'richtext' || !document.value.format) {
      // Get HTML from the collaborative editor
      const htmlContent = collaborativeEditorRef.value?.getHTML?.() || ''
      if (htmlContent && htmlContent !== document.value.content) {
        try {
          const token = route.params.token
          await api.post(`/api/v1/documents/public/${token}/update`, {
            content: htmlContent,
            password: storedPassword.value
          })
          console.log('Richtext content saved on leave')
        } catch (error) {
          console.error('Error saving richtext content:', error)
        }
      }
    }
  }

  if (collaboration) {
    collaboration.disconnect()
    collaboration = null
  }
  ydoc = null
  provider = null
  ytext = null
  isEditing.value = false
  collaborationAvailable.value = false
  isConnecting.value = false
}


// Format date
function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Get format label
function getFormatLabel(format) {
  const labels = {
    richtext: 'Rich Text',
    markdown: 'Markdown',
    code: 'Code',
    spreadsheet: 'Tabelle',
  }
  return labels[format] || format
}

// Handle page refresh/close - disconnect immediately to avoid ghost clients
function handleBeforeUnload() {
  if (collaboration) {
    collaboration.disconnect()
  }
}

onMounted(async () => {
  // Add beforeunload listener to clean up on refresh
  window.addEventListener('beforeunload', handleBeforeUnload)

  await fetchDocument()
  // Auto-start editing if editing is allowed
  if (canEdit.value) {
    startEditing()
  }
})

onUnmounted(() => {
  // Remove beforeunload listener
  window.removeEventListener('beforeunload', handleBeforeUnload)
  leaveSession()
})
</script>

<template>
  <div class="min-h-screen bg-white/[0.02] py-8 px-4 lg:px-8">
    <div class="max-w-7xl mx-auto">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-20">
        <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="text-center py-20">
        <ExclamationCircleIcon class="w-16 h-16 text-red-400 mx-auto mb-4" />
        <h1 class="text-xl font-bold text-white mb-2">Dokument nicht verfügbar</h1>
        <p class="text-gray-400">{{ error }}</p>
      </div>

      <!-- Password Required -->
      <div v-else-if="requiresPassword" class="max-w-md mx-auto">
        <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-8 text-center">
          <LockClosedIcon class="w-16 h-16 text-primary-400 mx-auto mb-4" />
          <h1 class="text-xl font-bold text-white mb-2">Passwortgeschützt</h1>
          <p class="text-gray-400 mb-6">Dieses Dokument ist mit einem Passwort geschützt.</p>

          <div class="space-y-4">
            <div>
              <input
                v-model="passwordInput"
                type="password"
                class="input w-full"
                placeholder="Passwort eingeben..."
                @keydown.enter="submitPassword"
              />
              <p v-if="passwordError" class="text-red-400 text-sm mt-2">{{ passwordError }}</p>
            </div>

            <button
              @click="submitPassword"
              class="btn-primary w-full"
            >
              Entsperren
            </button>
          </div>
        </div>
      </div>

      <!-- Document View -->
      <div v-else-if="document">
        <!-- Header -->
        <div class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-6 mb-6">
          <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-lg bg-primary-600/20 flex items-center justify-center flex-shrink-0">
              <DocumentTextIcon class="w-6 h-6 text-primary-400" />
            </div>
            <div class="flex-1 min-w-0">
              <h1 class="text-2xl font-bold text-white">{{ document.title }}</h1>
              <div class="flex flex-wrap items-center gap-4 mt-2 text-sm text-gray-400">
                <span class="flex items-center gap-1">
                  <UserIcon class="w-4 h-4" />
                  {{ document.owner_name || 'Unbekannt' }}
                </span>
                <span class="flex items-center gap-1">
                  <CalendarIcon class="w-4 h-4" />
                  {{ formatDate(document.updated_at) }}
                </span>
                <span class="flex items-center gap-1">
                  <EyeIcon class="w-4 h-4" />
                  {{ document.public_view_count }} Aufrufe
                </span>
                <span class="px-2 py-0.5 bg-primary-600/20 text-primary-400 rounded text-xs">
                  {{ getFormatLabel(document.format) }}
                </span>
              </div>
            </div>

            <!-- Edit button -->
            <div v-if="canEdit && !isEditing" class="flex-shrink-0">
              <button
                @click="startEditing"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-500 transition-colors flex items-center gap-2"
              >
                <PencilSquareIcon class="w-5 h-5" />
                Bearbeiten
              </button>
            </div>

            <!-- Editing controls -->
            <div v-if="isEditing" class="flex-shrink-0 flex items-center gap-3">
              <!-- Connection status -->
              <div class="flex items-center gap-2 text-sm">
                <template v-if="collaborationAvailable">
                  <span v-if="isConnected" class="text-green-400 flex items-center gap-1">
                    <SignalIcon class="w-4 h-4" />
                    Automatisch gespeichert
                  </span>
                  <span v-else class="text-yellow-400 flex items-center gap-1">
                    <SignalSlashIcon class="w-4 h-4" />
                    Verbinde...
                  </span>
                </template>
                <template v-else>
                  <span class="text-orange-400 flex items-center gap-1">
                    <SignalSlashIcon class="w-4 h-4" />
                    Lokale Bearbeitung
                  </span>
                  <button
                    @click="saveDocument"
                    :disabled="isSaving"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-500 transition-colors disabled:opacity-50 flex items-center gap-2"
                  >
                    <span v-if="isSaving" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    {{ isSaving ? 'Speichert...' : 'Speichern' }}
                  </button>
                </template>
              </div>
            </div>
          </div>

          <!-- Connected users -->
          <div v-if="isEditing && collaborationAvailable && connectedUsers.length > 0" class="mt-4 pt-4 border-t border-white/[0.06]">
            <div class="flex flex-wrap items-center gap-2 text-sm text-gray-400">
              <UsersIcon class="w-4 h-4 flex-shrink-0" />
              <span class="flex-shrink-0">Live-Bearbeiter:</span>
              <div class="flex flex-wrap items-center gap-2">
                <span
                  v-for="user in connectedUsers"
                  :key="user.clientId"
                  class="px-2 py-0.5 rounded text-white text-xs"
                  :style="{ backgroundColor: user.color + '40', borderColor: user.color, borderWidth: '1px' }"
                >
                  {{ user.name }}
                  <span v-if="user.isCurrentUser" class="opacity-60">(Du)</span>
                </span>
              </div>
            </div>
          </div>

          <!-- Connection error -->
          <div v-if="connectionError" class="mt-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm">
            {{ connectionError }}
          </div>
        </div>

        <!-- Connecting indicator -->
        <div v-if="isConnecting" class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-8 text-center">
          <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p class="text-gray-400">Verbinde mit Collaboration-Server...</p>
        </div>

        <!-- Content -->
        <div v-else class="bg-white/[0.04] border border-white/[0.06] rounded-xl overflow-hidden">
          <!-- Rich Text -->
          <template v-if="document.format === 'richtext' || !document.format">
            <!-- Collaborative editor when editing and collaboration is available -->
            <CollaborativeTipTapEditor
              v-if="isEditing && collaborationAvailable && ydoc && provider"
              ref="collaborativeEditorRef"
              :ydoc="ydoc"
              :provider="provider"
              :userName="editorName"
              :userColor="userColor"
              :editable="true"
              :initialContent="document.content || ''"
              placeholder="Beginne hier zu schreiben..."
            />
            <!-- Fallback TipTap editor when editing but collaboration is not available -->
            <TipTapEditor
              v-else-if="isEditing && !collaborationAvailable"
              :model-value="document.content"
              :editable="true"
              placeholder="Collaboration nicht verfügbar. Lokale Bearbeitung aktiv..."
              @update:model-value="(val) => { localContent = val; document.content = val }"
            />
            <!-- Regular view when not editing -->
            <div
              v-else
              class="p-8 prose prose-invert max-w-none"
              v-html="document.content"
            ></div>
          </template>

          <!-- Markdown -->
          <template v-else-if="document.format === 'markdown'">
            <!-- For markdown with collaboration -->
            <div v-if="isEditing && collaborationAvailable && ydoc && provider && ytext" class="grid grid-cols-1 lg:grid-cols-2">
              <div class="border-r border-white/[0.06]">
                <div class="p-2 bg-white/[0.04] text-xs text-gray-400 border-b border-white/[0.06]">Markdown (Echtzeit-Bearbeitung)</div>
                <CollaborativeMonacoEditor
                  :ydoc="ydoc"
                  :provider="provider"
                  :ytext="ytext"
                  language="markdown"
                  :readOnly="false"
                  height="600px"
                />
              </div>
              <div>
                <div class="p-2 bg-white/[0.04] text-xs text-gray-400 border-b border-white/[0.06]">Vorschau</div>
                <div
                  class="p-4 h-[600px] overflow-y-auto prose prose-invert max-w-none"
                  v-html="renderMarkdown(ytext ? ytext.toString() : document.content)"
                ></div>
              </div>
            </div>
            <!-- Fallback Monaco editor when collaboration not available -->
            <div v-else-if="isEditing && !collaborationAvailable" class="grid grid-cols-1 lg:grid-cols-2">
              <div class="border-r border-white/[0.06]">
                <div class="p-2 bg-white/[0.04] text-xs text-gray-400 border-b border-white/[0.06]">Markdown (Lokale Bearbeitung)</div>
                <MonacoEditor
                  :model-value="document.content"
                  :read-only="false"
                  language="markdown"
                  height="600px"
                  @update:model-value="(val) => { localContent = val; document.content = val }"
                />
              </div>
              <div>
                <div class="p-2 bg-white/[0.04] text-xs text-gray-400 border-b border-white/[0.06]">Vorschau</div>
                <div
                  class="p-4 h-[600px] overflow-y-auto prose prose-invert max-w-none"
                  v-html="renderMarkdown(localContent || document.content)"
                ></div>
              </div>
            </div>
            <div
              v-else
              class="p-8 prose prose-invert max-w-none"
              v-html="renderMarkdown(document.content)"
            ></div>
          </template>

          <!-- Code -->
          <div v-else-if="document.format === 'code'">
            <CollaborativeMonacoEditor
              v-if="isEditing && collaborationAvailable && ydoc && provider && ytext"
              :ydoc="ydoc"
              :provider="provider"
              :ytext="ytext"
              :language="document.code_language || 'javascript'"
              :readOnly="false"
              height="600px"
            />
            <!-- Fallback Monaco editor when collaboration not available -->
            <MonacoEditor
              v-else-if="isEditing && !collaborationAvailable"
              :model-value="document.content"
              :language="document.code_language || 'javascript'"
              :read-only="false"
              height="600px"
              @update:model-value="(val) => { localContent = val; document.content = val }"
            />
            <MonacoEditor
              v-else
              :model-value="document.content"
              :language="document.code_language || 'javascript'"
              :read-only="true"
              height="600px"
            />
          </div>

          <!-- Spreadsheet -->
          <div v-else-if="document.format === 'spreadsheet'">
            <UniverSheet
              :model-value="document.content"
              :read-only="true"
            />
          </div>

          <!-- Empty -->
          <div v-if="!document.content && !isEditing" class="p-8 text-center text-gray-400">
            Dieses Dokument hat keinen Inhalt.
          </div>
        </div>

        <!-- Footer -->
        <div class="mt-6 text-center text-sm text-gray-500">
          Geteilt über KyuubiSoft
        </div>
      </div>
    </div>

  </div>
</template>
