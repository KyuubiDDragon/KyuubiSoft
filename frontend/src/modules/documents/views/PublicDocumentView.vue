<script setup>
import { ref, onMounted, onUnmounted, defineAsyncComponent, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/core/api/axios'
import {
  DocumentTextIcon,
  LockClosedIcon,
  ExclamationCircleIcon,
  EyeIcon,
  CalendarIcon,
  UserIcon,
  UsersIcon,
  PencilSquareIcon,
  CheckCircleIcon,
  ArrowPathIcon,
} from '@heroicons/vue/24/outline'
import TipTapEditor from '@/components/TipTapEditor.vue'

// Lazy load heavy editors for code
const MonacoEditor = defineAsyncComponent(() => import('@/components/MonacoEditor.vue'))

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
const sessionToken = ref(null)
const editorName = ref('')
const showNameModal = ref(false)
const activeEditors = ref([])
const editContent = ref('')
const contentVersion = ref(1)
const isSaving = ref(false)
const lastSaved = ref(null)
const hasUnsavedChanges = ref(false)
const syncError = ref('')
let pollInterval = null
let saveTimeout = null

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
    contentVersion.value = response.data.data.content_version || 1
    activeEditors.value = response.data.data.active_editors || []
    editContent.value = response.data.data.content || ''
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

// Join edit session
async function joinEditSession() {
  if (!editorName.value.trim()) {
    editorName.value = 'Anonym'
  }

  // Store name for future use
  localStorage.setItem('kyuubisoft_editor_name', editorName.value)

  try {
    const token = route.params.token
    const response = await api.post(`/api/v1/documents/public/${token}/join`, {
      editor_name: editorName.value,
      password: storedPassword.value
    })

    sessionToken.value = response.data.data.session_token
    editContent.value = response.data.data.content || ''
    contentVersion.value = response.data.data.content_version || 1
    activeEditors.value = response.data.data.active_editors || []
    isEditing.value = true
    showNameModal.value = false

    // Start polling for changes
    startPolling()
  } catch (err) {
    error.value = 'Fehler beim Beitreten der Bearbeitungssitzung'
  }
}

// Start polling for changes
function startPolling() {
  if (pollInterval) clearInterval(pollInterval)

  pollInterval = setInterval(async () => {
    if (!sessionToken.value) return

    try {
      const token = route.params.token
      const response = await api.get(`/api/v1/documents/public/${token}/poll`, {
        params: {
          session_token: sessionToken.value,
          last_version: contentVersion.value
        }
      })

      const data = response.data.data
      activeEditors.value = data.active_editors || []

      // If there are changes and we don't have unsaved local changes, update content
      if (data.has_changes && !hasUnsavedChanges.value) {
        editContent.value = data.content
        contentVersion.value = data.version
        document.value.content = data.content
      } else if (data.has_changes && hasUnsavedChanges.value) {
        // Conflict - show warning
        syncError.value = 'Andere Benutzer haben Änderungen vorgenommen. Speichere deine Änderungen, um sie zusammenzuführen.'
      }
    } catch (err) {
      console.error('Poll error:', err)
    }
  }, 3000) // Poll every 3 seconds
}

// Stop polling
function stopPolling() {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

// Save content
async function saveContent() {
  if (!sessionToken.value || isSaving.value) return

  isSaving.value = true
  syncError.value = ''

  try {
    const token = route.params.token
    const response = await api.post(`/api/v1/documents/public/${token}/update`, {
      session_token: sessionToken.value,
      content: editContent.value,
      version: contentVersion.value,
      password: storedPassword.value
    })

    contentVersion.value = response.data.data.version
    lastSaved.value = new Date()
    hasUnsavedChanges.value = false
    document.value.content = editContent.value
  } catch (err) {
    if (err.response?.status === 409) {
      // Version conflict
      syncError.value = 'Konflikt: Das Dokument wurde von jemand anderem geändert. Lade die Seite neu.'
      // Update to latest version
      const conflictData = err.response.data.data
      if (conflictData) {
        contentVersion.value = conflictData.current_version
      }
    } else {
      syncError.value = 'Fehler beim Speichern'
    }
  } finally {
    isSaving.value = false
  }
}

// Auto-save with debounce
function onContentChange(newContent) {
  editContent.value = newContent
  hasUnsavedChanges.value = true

  // Clear previous timeout
  if (saveTimeout) clearTimeout(saveTimeout)

  // Auto-save after 2 seconds of inactivity
  saveTimeout = setTimeout(() => {
    saveContent()
  }, 2000)
}

// Leave edit session
async function leaveEditSession() {
  // Save any pending changes
  if (hasUnsavedChanges.value) {
    await saveContent()
  }

  stopPolling()

  if (sessionToken.value) {
    try {
      const token = route.params.token
      await api.post(`/api/v1/documents/public/${token}/leave`, {
        session_token: sessionToken.value
      })
    } catch (err) {
      console.error('Error leaving session:', err)
    }
  }

  sessionToken.value = null
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

// Watch for content changes in editing mode
watch(editContent, () => {
  if (isEditing.value) {
    hasUnsavedChanges.value = true
  }
})

onMounted(() => {
  fetchDocument()
})

onUnmounted(() => {
  leaveEditSession()
  if (saveTimeout) clearTimeout(saveTimeout)
})
</script>

<template>
  <div class="min-h-screen bg-dark-900 py-8 px-4">
    <div class="max-w-4xl mx-auto">
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
              <div class="flex items-center gap-2 text-sm">
                <span v-if="isSaving" class="text-yellow-400 flex items-center gap-1">
                  <ArrowPathIcon class="w-4 h-4 animate-spin" />
                  Speichern...
                </span>
                <span v-else-if="hasUnsavedChanges" class="text-yellow-400">
                  Ungespeicherte Änderungen
                </span>
                <span v-else-if="lastSaved" class="text-green-400 flex items-center gap-1">
                  <CheckCircleIcon class="w-4 h-4" />
                  Gespeichert
                </span>
              </div>
              <button
                @click="saveContent"
                :disabled="isSaving || !hasUnsavedChanges"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-500 transition-colors disabled:opacity-50"
              >
                Speichern
              </button>
              <button
                @click="leaveEditSession"
                class="px-4 py-2 bg-dark-600 text-white rounded-lg hover:bg-dark-500 transition-colors"
              >
                Beenden
              </button>
            </div>
          </div>

          <!-- Active editors -->
          <div v-if="activeEditors.length > 0" class="mt-4 pt-4 border-t border-dark-700">
            <div class="flex items-center gap-2 text-sm text-gray-400">
              <UsersIcon class="w-4 h-4" />
              <span>Aktive Bearbeiter:</span>
              <div class="flex items-center gap-2">
                <span
                  v-for="editor in activeEditors"
                  :key="editor.session_token"
                  class="px-2 py-0.5 bg-dark-700 rounded text-gray-300"
                  :class="{ 'bg-blue-600/20 text-blue-400': editor.session_token === sessionToken }"
                >
                  {{ editor.editor_name }}
                  <span v-if="editor.session_token === sessionToken" class="text-xs">(Du)</span>
                </span>
              </div>
            </div>
          </div>

          <!-- Sync error -->
          <div v-if="syncError" class="mt-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm">
            {{ syncError }}
          </div>
        </div>

        <!-- Content -->
        <div class="bg-dark-800 border border-dark-700 rounded-xl overflow-hidden">
          <!-- Rich Text - Editing -->
          <template v-if="document.format === 'richtext' || !document.format">
            <TipTapEditor
              v-if="isEditing"
              :model-value="editContent"
              @update:model-value="onContentChange"
              :editable="true"
              placeholder="Beginne hier zu schreiben..."
            />
            <div
              v-else
              class="p-8 prose prose-invert max-w-none"
              v-html="document.content"
            ></div>
          </template>

          <!-- Markdown - Editing -->
          <template v-else-if="document.format === 'markdown'">
            <div v-if="isEditing" class="grid grid-cols-1 lg:grid-cols-2">
              <div class="border-r border-dark-700">
                <div class="p-2 bg-dark-700 text-xs text-gray-400 border-b border-dark-600">Markdown</div>
                <textarea
                  :value="editContent"
                  @input="onContentChange($event.target.value)"
                  class="w-full h-[600px] bg-transparent p-4 text-white font-mono text-sm resize-none focus:outline-none"
                  placeholder="Schreibe hier deinen Markdown-Text..."
                ></textarea>
              </div>
              <div>
                <div class="p-2 bg-dark-700 text-xs text-gray-400 border-b border-dark-600">Vorschau</div>
                <div
                  class="p-4 h-[600px] overflow-y-auto prose prose-invert max-w-none"
                  v-html="renderMarkdown(editContent)"
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
            <MonacoEditor
              :model-value="isEditing ? editContent : document.content"
              @update:model-value="onContentChange"
              :read-only="!isEditing"
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
              <h2 class="text-xl font-bold text-white">Bearbeitung starten</h2>
              <p class="text-gray-400 text-sm">Wie möchtest du genannt werden?</p>
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
                @keydown.enter="joinEditSession"
              />
              <p class="text-xs text-gray-500 mt-1">Andere Bearbeiter sehen diesen Namen</p>
            </div>

            <div class="flex gap-3">
              <button
                @click="cancelEditing"
                class="flex-1 px-4 py-3 bg-dark-600 text-white rounded-lg hover:bg-dark-500 transition-colors"
              >
                Abbrechen
              </button>
              <button
                @click="joinEditSession"
                class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-500 transition-colors"
              >
                Bearbeitung starten
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
