<script setup>
import { ref, reactive, onMounted, watch, defineAsyncComponent } from 'vue'
import { useRoute } from 'vue-router'
import {
  PlusIcon,
  DocumentTextIcon,
  TrashIcon,
  PencilIcon,
  ChevronRightIcon,
  ClockIcon,
  CodeBracketIcon,
  DocumentIcon,
  TableCellsIcon,
  ShareIcon,
  LinkIcon,
  ClipboardDocumentIcon,
  GlobeAltIcon,
  LockClosedIcon,
  EyeIcon,
  UsersIcon,
  PencilSquareIcon
} from '@heroicons/vue/24/outline'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useProjectStore } from '@/stores/project'
import TipTapEditor from '@/components/TipTapEditor.vue'

// Lazy load heavy editors
const MonacoEditor = defineAsyncComponent(() => import('@/components/MonacoEditor.vue'))
const UniverSheet = defineAsyncComponent(() => import('@/components/UniverSheet.vue'))

const route = useRoute()
const uiStore = useUiStore()
const projectStore = useProjectStore()

// Watch for project changes
watch(() => projectStore.selectedProjectId, () => {
  loadDocuments()
  loadSharedDocuments()
})

// State
const documents = ref([])
const selectedDoc = ref(null)
const isLoading = ref(true)
const isEditing = ref(false)
const showCreateModal = ref(false)
const editContent = ref('')

// Share state
const showShareModal = ref(false)
const shareDoc = ref(null)
const shareForm = reactive({
  password: '',
  expires_in_days: null,
  can_edit: false
})
const shareInfo = ref(null)
const isLoadingShare = ref(false)

// View tabs
const activeTab = ref('all') // 'all' or 'shared'
const sharedDocuments = ref([])

// Form für neues Dokument
const docForm = reactive({
  title: '',
  content: '',
  format: 'richtext'
})

const formatOptions = [
  { value: 'richtext', label: 'Rich Text', icon: DocumentIcon, description: 'WYSIWYG Editor mit Formatierung' },
  { value: 'markdown', label: 'Markdown', icon: CodeBracketIcon, description: 'Markdown mit Live-Vorschau' },
  { value: 'code', label: 'Code', icon: CodeBracketIcon, description: 'Code-Editor mit Syntax-Highlighting' },
  { value: 'spreadsheet', label: 'Tabelle', icon: TableCellsIcon, description: 'Excel-ähnliche Tabellenkalkulation' },
]

// Simple Markdown to HTML conversion
function renderMarkdown(content) {
  if (!content) return ''

  let html = content
    // Escape HTML
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    // Headers
    .replace(/^### (.*$)/gim, '<h3 class="text-lg font-semibold text-white mt-4 mb-2">$1</h3>')
    .replace(/^## (.*$)/gim, '<h2 class="text-xl font-semibold text-white mt-6 mb-3">$1</h2>')
    .replace(/^# (.*$)/gim, '<h1 class="text-2xl font-bold text-white mt-6 mb-4">$1</h1>')
    // Bold & Italic
    .replace(/\*\*\*(.*?)\*\*\*/gim, '<strong><em>$1</em></strong>')
    .replace(/\*\*(.*?)\*\*/gim, '<strong class="font-semibold text-white">$1</strong>')
    .replace(/\*(.*?)\*/gim, '<em class="italic">$1</em>')
    // Code blocks
    .replace(/```([\s\S]*?)```/gim, '<pre class="bg-dark-700 rounded-lg p-4 my-4 overflow-x-auto"><code>$1</code></pre>')
    .replace(/`(.*?)`/gim, '<code class="bg-dark-700 px-1.5 py-0.5 rounded text-primary-400">$1</code>')
    // Lists
    .replace(/^\- (.*$)/gim, '<li class="ml-4">$1</li>')
    .replace(/^\* (.*$)/gim, '<li class="ml-4">$1</li>')
    .replace(/^\d+\. (.*$)/gim, '<li class="ml-4 list-decimal">$1</li>')
    // Links
    .replace(/\[(.*?)\]\((.*?)\)/gim, '<a href="$2" class="text-primary-400 hover:underline" target="_blank">$1</a>')
    // Line breaks
    .replace(/\n\n/gim, '</p><p class="text-gray-300 mb-4">')
    .replace(/\n/gim, '<br>')

  return `<p class="text-gray-300 mb-4">${html}</p>`
}

function getFormatIcon(format) {
  if (format === 'markdown') return CodeBracketIcon
  if (format === 'code') return CodeBracketIcon
  if (format === 'spreadsheet') return TableCellsIcon
  return DocumentIcon
}

function getFormatLabel(format) {
  const option = formatOptions.find(o => o.value === format)
  return option?.label || format
}

// API Calls

async function loadDocuments() {
  isLoading.value = true
  try {
    const params = projectStore.selectedProjectId
      ? { project_id: projectStore.selectedProjectId }
      : {}
    const response = await api.get('/api/v1/documents', { params })
    documents.value = response.data.data?.items || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Dokumente')
  } finally {
    isLoading.value = false
  }
}

async function createDocument() {
  try {
    const response = await api.post('/api/v1/documents', docForm)
    const newDoc = response.data.data

    // Link to selected project if one is active
    if (projectStore.selectedProjectId) {
      await projectStore.linkToSelectedProject('document', newDoc.id)
    }

    documents.value.unshift(newDoc)
    showCreateModal.value = false
    resetForm()
    // Open the new document
    await selectDocument(newDoc.id)
    isEditing.value = true
    uiStore.showSuccess('Dokument erstellt')
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen')
  }
}

async function updateDocument() {
  try {
    await api.put(`/api/v1/documents/${selectedDoc.value.id}`, {
      title: selectedDoc.value.title,
      content: editContent.value
    })
    selectedDoc.value.content = editContent.value
    isEditing.value = false
    await loadDocuments()
    uiStore.showSuccess('Dokument gespeichert')
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deleteDocument(docId) {
  if (!confirm('Dokument wirklich löschen?')) return

  try {
    await api.delete(`/api/v1/documents/${docId}`)
    documents.value = documents.value.filter(d => d.id !== docId)
    if (selectedDoc.value?.id === docId) {
      selectedDoc.value = null
    }
    uiStore.showSuccess('Dokument gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function selectDocument(docId) {
  try {
    const response = await api.get(`/api/v1/documents/${docId}`)
    selectedDoc.value = response.data.data
    editContent.value = selectedDoc.value.content || ''
    isEditing.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Laden')
  }
}

function openCreateModal() {
  resetForm()
  showCreateModal.value = true
}

function startEditing() {
  editContent.value = selectedDoc.value.content || ''
  isEditing.value = true
}

function cancelEditing() {
  editContent.value = selectedDoc.value.content || ''
  isEditing.value = false
}

function resetForm() {
  docForm.title = ''
  docForm.content = ''
  docForm.format = 'richtext'
}

function goBack() {
  selectedDoc.value = null
  isEditing.value = false
}

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Share functions
async function openShareModal(doc, event) {
  if (event) event.stopPropagation()
  shareDoc.value = doc
  shareForm.password = ''
  shareForm.expires_in_days = null
  shareForm.can_edit = false
  shareInfo.value = null
  showShareModal.value = true

  // Load current share info if shared
  if (doc.is_public) {
    await loadShareInfo(doc.id)
  }
}

async function loadShareInfo(docId) {
  isLoadingShare.value = true
  try {
    const response = await api.get(`/api/v1/documents/${docId}/public`)
    shareInfo.value = response.data.data
  } catch (error) {
    console.error('Error loading share info:', error)
  } finally {
    isLoadingShare.value = false
  }
}

async function enableShare() {
  isLoadingShare.value = true
  try {
    const response = await api.post(`/api/v1/documents/${shareDoc.value.id}/public`, {
      password: shareForm.password || null,
      expires_in_days: shareForm.expires_in_days || null,
      can_edit: shareForm.can_edit
    })
    shareInfo.value = response.data.data

    // Update document in list
    const docIndex = documents.value.findIndex(d => d.id === shareDoc.value.id)
    if (docIndex !== -1) {
      documents.value[docIndex].is_public = true
      documents.value[docIndex].public_token = shareInfo.value.token
    }

    uiStore.showSuccess('Dokument freigegeben')
    await loadSharedDocuments()
  } catch (error) {
    uiStore.showError('Fehler beim Freigeben')
  } finally {
    isLoadingShare.value = false
  }
}

async function disableShare() {
  if (!confirm('Freigabe wirklich deaktivieren?')) return

  isLoadingShare.value = true
  try {
    await api.delete(`/api/v1/documents/${shareDoc.value.id}/public`)
    shareInfo.value = null

    // Update document in list
    const docIndex = documents.value.findIndex(d => d.id === shareDoc.value.id)
    if (docIndex !== -1) {
      documents.value[docIndex].is_public = false
      documents.value[docIndex].public_token = null
    }

    showShareModal.value = false
    uiStore.showSuccess('Freigabe deaktiviert')
    await loadSharedDocuments()
  } catch (error) {
    uiStore.showError('Fehler beim Deaktivieren')
  } finally {
    isLoadingShare.value = false
  }
}

function getPublicUrl(token) {
  return `${window.location.origin}/doc/${token}`
}

function copyPublicUrl() {
  if (!shareInfo.value?.token) return
  navigator.clipboard.writeText(getPublicUrl(shareInfo.value.token))
  uiStore.showSuccess('Link kopiert!')
}

async function loadSharedDocuments() {
  try {
    const params = { include_shared: '1' }
    // Apply project filter if a project is selected
    if (projectStore.selectedProjectId) {
      params.project_id = projectStore.selectedProjectId
    }
    const response = await api.get('/api/v1/documents', { params })
    // Show documents that are either shared with user (shared_permission) or public (is_public)
    // Note: is_public comes as "0" or "1" string from backend, need to convert
    sharedDocuments.value = (response.data.data?.items || []).filter(d =>
      d.shared_permission || (d.is_public && d.is_public !== '0' && d.is_public !== 0)
    )
  } catch (error) {
    console.error('Error loading shared documents:', error)
  }
}

// Copy public URL to clipboard
function copyDocumentLink(token) {
  if (!token) {
    uiStore.showError('Kein öffentlicher Link vorhanden')
    return
  }
  const url = getPublicUrl(token)
  navigator.clipboard.writeText(url).then(() => {
    uiStore.showSuccess('Link kopiert!')
  }).catch(() => {
    uiStore.showError('Link konnte nicht kopiert werden')
  })
}

// Load shared documents on mount
onMounted(async () => {
  await loadDocuments()
  await loadSharedDocuments()

  // Check for ?open=id query parameter from Dashboard
  const openId = route.query.open
  if (openId) {
    await selectDocument(openId)
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button
          v-if="selectedDoc"
          @click="goBack"
          class="p-2 hover:bg-dark-700 rounded-lg transition-colors"
        >
          <ChevronRightIcon class="w-5 h-5 text-gray-400 rotate-180" />
        </button>
        <div>
          <h1 class="text-2xl font-bold text-white">
            {{ selectedDoc ? selectedDoc.title : 'Dokumente' }}
          </h1>
          <p v-if="selectedDoc" class="text-gray-400 mt-1 flex items-center gap-2">
            <ClockIcon class="w-4 h-4" />
            Zuletzt bearbeitet: {{ formatDate(selectedDoc.updated_at) }}
            <span class="badge badge-primary ml-2">{{ getFormatLabel(selectedDoc.format) }}</span>
          </p>
          <p v-else class="text-gray-400 mt-1">Verwalte deine Dokumente</p>
        </div>
      </div>
      <div class="flex gap-2">
        <template v-if="selectedDoc">
          <!-- Spreadsheet doesn't need edit toggle -->
          <template v-if="selectedDoc.format !== 'spreadsheet'">
            <button v-if="!isEditing" @click="startEditing" class="btn-primary">
              <PencilIcon class="w-5 h-5 mr-2" />
              Bearbeiten
            </button>
            <template v-else>
              <button @click="cancelEditing" class="btn-secondary">Abbrechen</button>
              <button @click="updateDocument" class="btn-primary">Speichern</button>
            </template>
          </template>
          <!-- Spreadsheet auto-saves -->
          <button v-else @click="updateDocument" class="btn-primary">
            Speichern
          </button>
          <button @click="openShareModal(selectedDoc)" class="btn-secondary">
            <ShareIcon class="w-5 h-5 mr-2" />
            Teilen
          </button>
          <button @click="deleteDocument(selectedDoc.id)" class="btn-secondary text-red-400">
            <TrashIcon class="w-5 h-5" />
          </button>
        </template>
        <button v-else @click="openCreateModal" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Neues Dokument
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Document View/Edit -->
    <template v-else-if="selectedDoc">
      <!-- Rich Text Editor (TipTap) -->
      <template v-if="selectedDoc.format === 'richtext' || !selectedDoc.format">
        <div class="bg-dark-800 rounded-lg overflow-hidden">
          <TipTapEditor
            v-model="editContent"
            :editable="isEditing"
            placeholder="Beginne hier zu schreiben..."
          />
        </div>
        <div v-if="!isEditing && !selectedDoc.content" class="text-center py-12 text-gray-400">
          Dieses Dokument ist leer. Klicke auf "Bearbeiten" um Inhalt hinzuzufügen.
        </div>
      </template>

      <!-- Markdown Editor -->
      <template v-else-if="selectedDoc.format === 'markdown'">
        <!-- Editor -->
        <div v-if="isEditing" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="label">Markdown</label>
            <textarea
              v-model="editContent"
              class="input font-mono text-sm h-[600px] resize-none"
              placeholder="Schreibe hier deinen Markdown-Text..."
            ></textarea>
          </div>
          <div class="space-y-2">
            <label class="label">Vorschau</label>
            <div
              class="bg-dark-800 border border-dark-700 rounded-lg p-6 h-[600px] overflow-y-auto prose prose-invert max-w-none"
              v-html="renderMarkdown(editContent)"
            ></div>
          </div>
        </div>

        <!-- Read-only View -->
        <div v-else class="bg-dark-800 border border-dark-700 rounded-lg p-8">
          <div
            class="prose prose-invert max-w-none"
            v-html="renderMarkdown(selectedDoc.content)"
          ></div>
          <div v-if="!selectedDoc.content" class="text-center py-12 text-gray-400">
            Dieses Dokument ist leer. Klicke auf "Bearbeiten" um Inhalt hinzuzufügen.
          </div>
        </div>
      </template>

      <!-- Code Editor (Monaco) -->
      <template v-else-if="selectedDoc.format === 'code'">
        <div class="bg-dark-800 rounded-lg overflow-hidden">
          <MonacoEditor
            v-model="editContent"
            :read-only="!isEditing"
            height="600px"
          />
        </div>
        <div v-if="!isEditing && !selectedDoc.content" class="text-center py-12 text-gray-400">
          Dieses Dokument ist leer. Klicke auf "Bearbeiten" um Code hinzuzufügen.
        </div>
      </template>

      <!-- Spreadsheet (Univer) -->
      <template v-else-if="selectedDoc.format === 'spreadsheet'">
        <div class="bg-dark-800 rounded-lg overflow-hidden">
          <UniverSheet
            v-model="editContent"
            :read-only="false"
          />
        </div>
      </template>
    </template>

    <!-- Documents List -->
    <template v-else>
      <!-- Tabs -->
      <div class="flex gap-4 border-b border-dark-700 mb-6">
        <button
          @click="activeTab = 'all'"
          class="pb-3 px-1 text-sm font-medium transition-colors border-b-2"
          :class="activeTab === 'all'
            ? 'text-primary-400 border-primary-400'
            : 'text-gray-400 border-transparent hover:text-gray-300'"
        >
          Alle Dokumente
          <span class="ml-2 px-2 py-0.5 rounded-full text-xs bg-dark-700">{{ documents.length }}</span>
        </button>
        <button
          @click="activeTab = 'shared'"
          class="pb-3 px-1 text-sm font-medium transition-colors border-b-2"
          :class="activeTab === 'shared'
            ? 'text-primary-400 border-primary-400'
            : 'text-gray-400 border-transparent hover:text-gray-300'"
        >
          <GlobeAltIcon class="w-4 h-4 inline mr-1" />
          Geteilte Dokumente
          <span class="ml-2 px-2 py-0.5 rounded-full text-xs bg-dark-700">{{ sharedDocuments.length }}</span>
        </button>
      </div>

      <!-- Empty state -->
      <div v-if="activeTab === 'all' && documents.length === 0" class="card p-12 text-center">
        <DocumentTextIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
        <h3 class="text-lg font-medium text-white mb-2">Keine Dokumente vorhanden</h3>
        <p class="text-gray-400 mb-6">Erstelle dein erstes Dokument.</p>
        <button @click="openCreateModal" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Erstes Dokument erstellen
        </button>
      </div>

      <!-- All Documents grid -->
      <div v-else-if="activeTab === 'all'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="doc in documents"
          :key="doc.id"
          @click="selectDocument(doc.id)"
          class="card-hover p-6 cursor-pointer group"
        >
          <div class="flex items-start justify-between">
            <div class="w-10 h-10 rounded-lg bg-primary-600/20 flex items-center justify-center relative">
              <component :is="getFormatIcon(doc.format)" class="w-5 h-5 text-primary-400" />
              <div v-if="doc.is_public" class="absolute -top-1 -right-1 w-4 h-4 bg-green-500 rounded-full flex items-center justify-center">
                <GlobeAltIcon class="w-2.5 h-2.5 text-white" />
              </div>
            </div>
            <span class="badge badge-primary">{{ getFormatLabel(doc.format) }}</span>
          </div>
          <h3 class="text-lg font-medium text-white mt-4">{{ doc.title }}</h3>
          <p class="text-gray-500 text-sm mt-2">
            Geändert: {{ formatDate(doc.updated_at) }}
          </p>
          <div class="flex items-center justify-end mt-4 gap-2">
            <button
              @click.stop="openShareModal(doc, $event)"
              class="opacity-0 group-hover:opacity-100 p-2 text-primary-400 hover:bg-primary-400/10 rounded-lg transition-all"
              :title="doc.is_public ? 'Freigabe bearbeiten' : 'Teilen'"
            >
              <ShareIcon class="w-4 h-4" />
            </button>
            <button
              @click.stop="deleteDocument(doc.id)"
              class="opacity-0 group-hover:opacity-100 p-2 text-red-400 hover:bg-red-400/10 rounded-lg transition-all"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- Shared Documents grid -->
      <template v-else-if="activeTab === 'shared'">
        <div v-if="sharedDocuments.length === 0" class="card p-12 text-center">
          <GlobeAltIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
          <h3 class="text-lg font-medium text-white mb-2">Keine geteilten Dokumente</h3>
          <p class="text-gray-400">Teile ein Dokument um es hier zu sehen.</p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="doc in sharedDocuments"
            :key="doc.id"
            class="card p-6 group"
          >
            <div class="flex items-start justify-between">
              <div class="w-10 h-10 rounded-lg bg-green-600/20 flex items-center justify-center">
                <GlobeAltIcon class="w-5 h-5 text-green-400" />
              </div>
              <div class="flex items-center gap-2">
                <span v-if="doc.public_password" class="badge bg-yellow-500/20 text-yellow-400">
                  <LockClosedIcon class="w-3 h-3 mr-1" />
                  Passwort
                </span>
                <span class="badge badge-primary">{{ getFormatLabel(doc.format) }}</span>
              </div>
            </div>
            <h3 class="text-lg font-medium text-white mt-4">{{ doc.title }}</h3>
            <p class="text-gray-500 text-sm mt-2">
              <EyeIcon class="w-4 h-4 inline mr-1" />
              {{ doc.public_view_count || 0 }} Aufrufe
            </p>
            <p v-if="doc.public_expires_at" class="text-gray-500 text-sm mt-1">
              Läuft ab: {{ formatDate(doc.public_expires_at) }}
            </p>
            <div class="flex items-center gap-2 mt-4">
              <button
                v-if="doc.public_token"
                @click="copyDocumentLink(doc.public_token)"
                class="flex-1 btn-secondary py-2 text-sm"
              >
                <ClipboardDocumentIcon class="w-4 h-4 mr-1" />
                Link kopieren
              </button>
              <button
                @click="selectDocument(doc.id)"
                class="flex-1 btn-secondary py-2 text-sm"
              >
                Öffnen
              </button>
              <button
                v-if="doc.is_owner"
                @click="openShareModal(doc)"
                class="btn-secondary py-2 text-sm"
              >
                <PencilIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </template>
    </template>

    <!-- Create Modal -->
    <Teleport to="body">
      <div
        v-if="showCreateModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        
      >
        <div class="bg-dark-800 rounded-xl p-6 w-full max-w-2xl border border-dark-700">
          <h2 class="text-xl font-bold text-white mb-6">Neues Dokument</h2>

          <form @submit.prevent="createDocument" class="space-y-6">
            <div>
              <label class="label">Titel</label>
              <input
                v-model="docForm.title"
                type="text"
                class="input"
                placeholder="Dokumentname"
                required
              />
            </div>

            <div>
              <label class="label">Format</label>
              <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-2">
                <button
                  v-for="option in formatOptions"
                  :key="option.value"
                  type="button"
                  @click="docForm.format = option.value"
                  class="p-4 rounded-lg border-2 transition-all text-left"
                  :class="docForm.format === option.value
                    ? 'border-primary-500 bg-primary-500/10'
                    : 'border-dark-600 hover:border-dark-500'"
                >
                  <component :is="option.icon" class="w-6 h-6 text-primary-400 mb-2" />
                  <p class="font-medium text-white text-sm">{{ option.label }}</p>
                  <p class="text-xs text-gray-400 mt-1">{{ option.description }}</p>
                </button>
              </div>
            </div>

            <div class="flex gap-3 pt-4">
              <button
                type="button"
                @click="showCreateModal = false"
                class="btn-secondary flex-1"
              >
                Abbrechen
              </button>
              <button type="submit" class="btn-primary flex-1">
                Erstellen
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Share Modal -->
    <Teleport to="body">
      <div
        v-if="showShareModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        @click.self="showShareModal = false"
      >
        <div class="bg-dark-800 rounded-xl p-6 w-full max-w-lg border border-dark-700">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-lg bg-primary-600/20 flex items-center justify-center">
              <ShareIcon class="w-5 h-5 text-primary-400" />
            </div>
            <div>
              <h2 class="text-xl font-bold text-white">Dokument teilen</h2>
              <p class="text-gray-400 text-sm">{{ shareDoc?.title }}</p>
            </div>
          </div>

          <!-- Loading -->
          <div v-if="isLoadingShare" class="flex justify-center py-8">
            <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
          </div>

          <!-- Share Info (when already shared) -->
          <div v-else-if="shareInfo" class="space-y-4">
            <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4">
              <div class="flex items-center gap-2 text-green-400 mb-2">
                <GlobeAltIcon class="w-5 h-5" />
                <span class="font-medium">Dokument ist öffentlich</span>
              </div>
              <div class="flex items-center gap-4 text-gray-400 text-sm">
                <span class="flex items-center gap-1">
                  <EyeIcon class="w-4 h-4" />
                  {{ shareInfo.view_count || 0 }} Aufrufe
                </span>
                <span v-if="shareInfo.can_edit" class="flex items-center gap-1 text-blue-400">
                  <PencilSquareIcon class="w-4 h-4" />
                  Bearbeiten erlaubt
                </span>
                <span v-if="shareInfo.active_editors > 0" class="flex items-center gap-1 text-yellow-400">
                  <UsersIcon class="w-4 h-4" />
                  {{ shareInfo.active_editors }} aktive Bearbeiter
                </span>
              </div>
            </div>

            <!-- Public URL -->
            <div>
              <label class="label">Öffentlicher Link</label>
              <div class="flex gap-2">
                <input
                  :value="getPublicUrl(shareInfo.token)"
                  readonly
                  class="input flex-1 text-sm"
                />
                <button @click="copyPublicUrl" class="btn-primary">
                  <ClipboardDocumentIcon class="w-5 h-5" />
                </button>
              </div>
            </div>

            <!-- Info -->
            <div class="space-y-2 text-sm text-gray-400">
              <p v-if="shareInfo.has_password" class="flex items-center gap-2">
                <LockClosedIcon class="w-4 h-4 text-yellow-400" />
                Passwortgeschützt
              </p>
              <p v-if="shareInfo.can_edit" class="flex items-center gap-2">
                <PencilSquareIcon class="w-4 h-4 text-blue-400" />
                Kollaboratives Bearbeiten aktiviert
              </p>
              <p v-if="shareInfo.expires_at">
                Läuft ab: {{ formatDate(shareInfo.expires_at) }}
              </p>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 pt-4 border-t border-dark-700">
              <button
                @click="disableShare"
                class="btn-secondary flex-1 text-red-400"
                :disabled="isLoadingShare"
              >
                Freigabe deaktivieren
              </button>
              <button
                @click="showShareModal = false"
                class="btn-primary flex-1"
              >
                Schließen
              </button>
            </div>
          </div>

          <!-- Share Form (when not shared) -->
          <div v-else class="space-y-4">
            <p class="text-gray-400">
              Erstelle einen öffentlichen Link zu diesem Dokument.
            </p>

            <!-- Access Mode -->
            <div>
              <label class="label">Zugriffsmodus</label>
              <div class="grid grid-cols-2 gap-3 mt-2">
                <button
                  type="button"
                  @click="shareForm.can_edit = false"
                  class="p-3 rounded-lg border-2 transition-all text-left"
                  :class="!shareForm.can_edit
                    ? 'border-primary-500 bg-primary-500/10'
                    : 'border-dark-600 hover:border-dark-500'"
                >
                  <EyeIcon class="w-5 h-5 text-primary-400 mb-1" />
                  <p class="font-medium text-white text-sm">Nur Lesen</p>
                  <p class="text-xs text-gray-400">Besucher können nur ansehen</p>
                </button>
                <button
                  type="button"
                  @click="shareForm.can_edit = true"
                  class="p-3 rounded-lg border-2 transition-all text-left"
                  :class="shareForm.can_edit
                    ? 'border-blue-500 bg-blue-500/10'
                    : 'border-dark-600 hover:border-dark-500'"
                >
                  <PencilSquareIcon class="w-5 h-5 text-blue-400 mb-1" />
                  <p class="font-medium text-white text-sm">Bearbeiten</p>
                  <p class="text-xs text-gray-400">Kollaboratives Arbeiten</p>
                </button>
              </div>
              <p v-if="shareForm.can_edit" class="text-xs text-blue-400 mt-2 flex items-center gap-1">
                <UsersIcon class="w-3 h-3" />
                Mehrere Personen können gleichzeitig bearbeiten - Änderungen werden synchronisiert.
              </p>
            </div>

            <div>
              <label class="label">Passwort (optional)</label>
              <input
                v-model="shareForm.password"
                type="password"
                class="input"
                placeholder="Leer lassen für keinen Schutz"
              />
            </div>

            <div>
              <label class="label">Gültigkeitsdauer (optional)</label>
              <select v-model="shareForm.expires_in_days" class="input">
                <option :value="null">Unbegrenzt</option>
                <option :value="1">1 Tag</option>
                <option :value="7">7 Tage</option>
                <option :value="30">30 Tage</option>
                <option :value="90">90 Tage</option>
              </select>
            </div>

            <div class="flex gap-3 pt-4">
              <button
                @click="showShareModal = false"
                class="btn-secondary flex-1"
              >
                Abbrechen
              </button>
              <button
                @click="enableShare"
                class="btn-primary flex-1"
                :disabled="isLoadingShare"
              >
                <LinkIcon class="w-5 h-5 mr-2" />
                Link erstellen
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
