<script setup>
import { ref, reactive, onMounted, defineAsyncComponent } from 'vue'
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
  TableCellsIcon
} from '@heroicons/vue/24/outline'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import TipTapEditor from '@/components/TipTapEditor.vue'

// Lazy load heavy editors
const MonacoEditor = defineAsyncComponent(() => import('@/components/MonacoEditor.vue'))
const UniverSheet = defineAsyncComponent(() => import('@/components/UniverSheet.vue'))

const route = useRoute()
const uiStore = useUiStore()

// State
const documents = ref([])
const selectedDoc = ref(null)
const isLoading = ref(true)
const isEditing = ref(false)
const showCreateModal = ref(false)
const editContent = ref('')

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
onMounted(async () => {
  await loadDocuments()

  // Check for ?open=id query parameter from Dashboard
  const openId = route.query.open
  if (openId) {
    await selectDocument(openId)
  }
})

async function loadDocuments() {
  isLoading.value = true
  try {
    const response = await api.get('/api/v1/documents')
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
    documents.value.unshift(response.data.data)
    showCreateModal.value = false
    resetForm()
    // Open the new document
    await selectDocument(response.data.data.id)
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
      <!-- Empty state -->
      <div v-if="documents.length === 0" class="card p-12 text-center">
        <DocumentTextIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
        <h3 class="text-lg font-medium text-white mb-2">Keine Dokumente vorhanden</h3>
        <p class="text-gray-400 mb-6">Erstelle dein erstes Dokument.</p>
        <button @click="openCreateModal" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Erstes Dokument erstellen
        </button>
      </div>

      <!-- Documents grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="doc in documents"
          :key="doc.id"
          @click="selectDocument(doc.id)"
          class="card-hover p-6 cursor-pointer group"
        >
          <div class="flex items-start justify-between">
            <div class="w-10 h-10 rounded-lg bg-primary-600/20 flex items-center justify-center">
              <component :is="getFormatIcon(doc.format)" class="w-5 h-5 text-primary-400" />
            </div>
            <span class="badge badge-primary">{{ getFormatLabel(doc.format) }}</span>
          </div>
          <h3 class="text-lg font-medium text-white mt-4">{{ doc.title }}</h3>
          <p class="text-gray-500 text-sm mt-2">
            Geändert: {{ formatDate(doc.updated_at) }}
          </p>
          <div class="flex items-center justify-end mt-4">
            <button
              @click.stop="deleteDocument(doc.id)"
              class="opacity-0 group-hover:opacity-100 p-2 text-red-400 hover:bg-red-400/10 rounded-lg transition-all"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- Create Modal -->
    <Teleport to="body">
      <div
        v-if="showCreateModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        @click.self="showCreateModal = false"
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
  </div>
</template>
