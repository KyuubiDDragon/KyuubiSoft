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

const route = useRoute()

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
const editorName = ref('')
const showNameModal = ref(false)
const userColor = ref(getRandomColor())

// Collaboration
let collaboration = null
let ydoc = null
let provider = null
let ytext = null

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
    .replace(/```([\s\S]*?)```/gim, '<pre class="bg-dark-700 rounded-lg p-4 my-4 overflow-x-auto"><code>$1</code></pre>')
    .replace(/`(.*?)`/gim, '<code class="bg-dark-700 px-1.5 py-0.5 rounded text-primary-400">$1</code>')
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
    if (err.response?.status === 401 && err.response?.data?.data?.requires_password) {
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
function submitPassword() {
  if (!passwordInput.value.trim()) {
    passwordError.value = 'Bitte Passwort eingeben'
    return
  }
  fetchDocument(passwordInput.value)
}

// Start editing - show name modal
function startEditing() {
  // Try to get stored name from localStorage
  const storedName = localStorage.getItem('kyuubisoft_editor_name')
  if (storedName) {
    editorName.value = storedName
  }
  showNameModal.value = true
}

// Join collaborative session
function joinCollaborativeSession() {
  if (!editorName.value.trim()) {
    editorName.value = 'Anonym'
  }

  // Store name for future use
  localStorage.setItem('kyuubisoft_editor_name', editorName.value)

  const token = route.params.token

  // Initialize collaboration
  collaboration = useCollaboration(token, {
    userName: editorName.value,
    userColor: userColor.value,
  })

  const result = collaboration.connect()
  ydoc = result.ydoc
  provider = result.provider

  // Get the correct Yjs type based on document format
  if (document.value.format === 'code' || document.value.format === 'markdown') {
    ytext = collaboration.getText('monaco')

    // Initialize with existing content if document has content and ytext is empty
    if (document.value.content && ytext.toString() === '') {
      ytext.insert(0, document.value.content)
    }
  } else {
    // For richtext, we use XML fragment (handled by TipTap collaboration extension)
    ytext = null
  }

  isEditing.value = true
  showNameModal.value = false
}

// Leave collaborative session
function leaveSession() {
  if (collaboration) {
    collaboration.disconnect()
    collaboration = null
  }
  ydoc = null
  provider = null
  ytext = null
  isEditing.value = false
}

// Cancel editing
function cancelEditing() {
  showNameModal.value = false
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

onMounted(() => {
  fetchDocument()
})

onUnmounted(() => {
  leaveSession()
})
</script>

<template>
  <div class="min-h-screen bg-dark-900 py-8 px-4 lg:px-8">
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
        <div class="bg-dark-800 border border-dark-700 rounded-xl p-8 text-center">
          <LockClosedIcon class="w-16 h-16 text-primary-400 mx-auto mb-4" />
          <h1 class="text-xl font-bold text-white mb-2">Passwortgeschützt</h1>
          <p class="text-gray-400 mb-6">Dieses Dokument ist mit einem Passwort geschützt.</p>

          <div class="space-y-4">
            <div>
              <input
                v-model="passwordInput"
                type="password"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Passwort eingeben..."
                @keydown.enter="submitPassword"
              />
              <p v-if="passwordError" class="text-red-400 text-sm mt-2">{{ passwordError }}</p>
            </div>

            <button
              @click="submitPassword"
              class="w-full px-4 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
            >
              Entsperren
            </button>
          </div>
        </div>
      </div>

      <!-- Document View -->
      <div v-else-if="document">
        <!-- Header -->
        <div class="bg-dark-800 border border-dark-700 rounded-xl p-6 mb-6">
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
                <span v-if="isConnected" class="text-green-400 flex items-center gap-1">
                  <SignalIcon class="w-4 h-4" />
                  Verbunden
                </span>
                <span v-else class="text-yellow-400 flex items-center gap-1">
                  <SignalSlashIcon class="w-4 h-4" />
                  Verbinde...
                </span>
              </div>
              <button
                @click="leaveSession"
                class="px-4 py-2 bg-dark-600 text-white rounded-lg hover:bg-dark-500 transition-colors"
              >
                Beenden
              </button>
            </div>
          </div>

          <!-- Connected users -->
          <div v-if="isEditing && connectedUsers.length > 0" class="mt-4 pt-4 border-t border-dark-700">
            <div class="flex items-center gap-2 text-sm text-gray-400">
              <UsersIcon class="w-4 h-4" />
              <span>Live-Bearbeiter:</span>
              <div class="flex items-center gap-2">
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

        <!-- Content -->
        <div class="bg-dark-800 border border-dark-700 rounded-xl overflow-hidden">
          <!-- Rich Text -->
          <template v-if="document.format === 'richtext' || !document.format">
            <!-- Collaborative editor when editing -->
            <CollaborativeTipTapEditor
              v-if="isEditing && ydoc && provider"
              :ydoc="ydoc"
              :provider="provider"
              :userName="editorName"
              :userColor="userColor"
              :editable="true"
              placeholder="Beginne hier zu schreiben..."
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
            <!-- For markdown, we'll use a simple textarea with live preview -->
            <div v-if="isEditing && ydoc && provider" class="grid grid-cols-1 lg:grid-cols-2">
              <div class="border-r border-dark-700">
                <div class="p-2 bg-dark-700 text-xs text-gray-400 border-b border-dark-600">Markdown (Echtzeit-Bearbeitung)</div>
                <CollaborativeMonacoEditor
                  v-if="ytext"
                  :ydoc="ydoc"
                  :provider="provider"
                  :ytext="ytext"
                  language="markdown"
                  :readOnly="false"
                  height="600px"
                />
              </div>
              <div>
                <div class="p-2 bg-dark-700 text-xs text-gray-400 border-b border-dark-600">Vorschau</div>
                <div
                  class="p-4 h-[600px] overflow-y-auto prose prose-invert max-w-none"
                  v-html="renderMarkdown(ytext ? ytext.toString() : document.content)"
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
              v-if="isEditing && ydoc && provider && ytext"
              :ydoc="ydoc"
              :provider="provider"
              :ytext="ytext"
              :readOnly="false"
              height="600px"
            />
            <MonacoEditor
              v-else
              :model-value="document.content"
              :read-only="true"
              height="600px"
            />
          </div>

          <!-- Spreadsheet - simplified view -->
          <div v-else-if="document.format === 'spreadsheet'" class="p-8">
            <p class="text-gray-400 text-center">
              Tabellenansicht ist in der öffentlichen Ansicht nicht verfügbar.
            </p>
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

    <!-- Name Input Modal -->
    <Teleport to="body">
      <div
        v-if="showNameModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        @click.self="cancelEditing"
      >
        <div class="bg-dark-800 rounded-xl p-6 w-full max-w-md border border-dark-700">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-lg bg-blue-600/20 flex items-center justify-center">
              <PencilSquareIcon class="w-5 h-5 text-blue-400" />
            </div>
            <div>
              <h2 class="text-xl font-bold text-white">Live-Bearbeitung starten</h2>
              <p class="text-gray-400 text-sm">Bearbeite das Dokument in Echtzeit mit anderen</p>
            </div>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm text-gray-400 mb-2">Dein Name</label>
              <input
                v-model="editorName"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-blue-500"
                placeholder="z.B. Max Mustermann"
                @keydown.enter="joinCollaborativeSession"
              />
              <p class="text-xs text-gray-500 mt-1">Andere Bearbeiter sehen diesen Namen und deinen Cursor</p>
            </div>

            <div class="flex items-center gap-3">
              <label class="block text-sm text-gray-400">Deine Farbe:</label>
              <div class="flex gap-2">
                <button
                  v-for="color in ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316']"
                  :key="color"
                  @click="userColor = color"
                  class="w-6 h-6 rounded-full transition-transform"
                  :class="{ 'ring-2 ring-white ring-offset-2 ring-offset-dark-800 scale-110': userColor === color }"
                  :style="{ backgroundColor: color }"
                ></button>
              </div>
            </div>

            <div class="flex gap-3 pt-2">
              <button
                @click="cancelEditing"
                class="flex-1 px-4 py-3 bg-dark-600 text-white rounded-lg hover:bg-dark-500 transition-colors"
              >
                Abbrechen
              </button>
              <button
                @click="joinCollaborativeSession"
                class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-500 transition-colors"
              >
                Beitreten
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
