<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useNotesStore } from '../stores/notesStore'
import { useUiStore } from '@/stores/ui'
import NotesSidebar from '../components/sidebar/NotesSidebar.vue'
import NoteEditor from '../components/editor/NoteEditor.vue'
import NoteHeader from '../components/editor/NoteHeader.vue'
import NoteBreadcrumb from '../components/editor/NoteBreadcrumb.vue'
import NoteBacklinks from '../components/editor/NoteBacklinks.vue'
import NoteQuickSwitcher from '../components/modals/NoteQuickSwitcher.vue'
import NoteTemplatesModal from '../components/modals/NoteTemplatesModal.vue'
import NoteVersionsModal from '../components/modals/NoteVersionsModal.vue'
import NoteTrashModal from '../components/modals/NoteTrashModal.vue'
import CollaborationPresence from '../components/collaboration/CollaborationPresence.vue'
import ExportModal from '../components/export/ExportModal.vue'
import ShareModal from '../components/share/ShareModal.vue'
import {
  PlusIcon,
  Cog6ToothIcon,
  MagnifyingGlassIcon,
  TrashIcon,
  ArrowPathIcon,
  DocumentDuplicateIcon,
  StarIcon,
  ArchiveBoxIcon,
  EllipsisVerticalIcon,
  ClockIcon,
  ArrowDownTrayIcon,
  ShareIcon
} from '@heroicons/vue/24/outline'
import { StarIcon as StarIconSolid } from '@heroicons/vue/24/solid'

const route = useRoute()
const router = useRouter()
const notesStore = useNotesStore()
const uiStore = useUiStore()

// State
const showQuickSwitcher = ref(false)
const showTemplateModal = ref(false)
const showVersionModal = ref(false)
const showTrashModal = ref(false)
const showExportModal = ref(false)
const showShareModal = ref(false)
const showNoteMenu = ref(false)
const sidebarCollapsed = ref(false)
const autoSaveTimeout = ref(null)
const lastSaved = ref(null)

// Editor state
const editorContent = ref('')
const hasUnsavedChanges = ref(false)

// Computed
const currentNote = computed(() => notesStore.currentNote)
const isLoading = computed(() => notesStore.isLoadingNote)
const isSaving = computed(() => notesStore.isSaving)

// Initialize
onMounted(async () => {
  try {
    await notesStore.initialize()

    // Load note if ID in route
    if (route.params.id) {
      await loadNote(route.params.id)
    }
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Notizen')
  }

  // Keyboard shortcuts
  document.addEventListener('keydown', handleKeydown)
})

// Cleanup event listeners
onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown)
})

// Watch route changes
watch(() => route.params.id, async (newId) => {
  if (newId) {
    await loadNote(newId)
  } else {
    notesStore.clearCurrentNote()
    editorContent.value = ''
    hasUnsavedChanges.value = false
  }
})

// Cleanup
function handleKeydown(e) {
  // Cmd/Ctrl + K - Quick Switcher
  if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
    e.preventDefault()
    showQuickSwitcher.value = true
  }

  // Cmd/Ctrl + S - Save
  if ((e.metaKey || e.ctrlKey) && e.key === 's') {
    e.preventDefault()
    saveNote()
  }

  // Cmd/Ctrl + N - New Note
  if ((e.metaKey || e.ctrlKey) && e.key === 'n') {
    e.preventDefault()
    createNewNote()
  }

  // Escape - Close modals
  if (e.key === 'Escape') {
    showQuickSwitcher.value = false
    showTemplateModal.value = false
    showVersionModal.value = false
    showExportModal.value = false
    showShareModal.value = false
    showNoteMenu.value = false
  }
}

// Load note
async function loadNote(noteId) {
  // Save current note if has changes
  if (hasUnsavedChanges.value && currentNote.value) {
    await saveNote()
  }

  try {
    await notesStore.fetchNote(noteId)
    editorContent.value = currentNote.value?.content || ''
    hasUnsavedChanges.value = false
    lastSaved.value = new Date()
  } catch (error) {
    uiStore.showError('Notiz konnte nicht geladen werden')
    router.push('/notes')
  }
}

// Create new note
async function createNewNote(parentId = null) {
  try {
    const note = await notesStore.createNote({
      title: 'Neue Notiz',
      content: '',
      parent_id: parentId
    })
    router.push(`/notes/${note.id}`)
    uiStore.showSuccess('Notiz erstellt')
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen der Notiz')
  }
}

// Handle template modal create
function handleTemplateCreate(data) {
  if (data.useEmpty) {
    createNewNote()
  } else if (data.id) {
    router.push(`/notes/${data.id}`)
  }
}

// Handle version restored
async function handleVersionRestored() {
  if (currentNote.value) {
    await loadNote(currentNote.value.id)
  }
}

// Save note
async function saveNote() {
  if (!currentNote.value || !hasUnsavedChanges.value) return

  try {
    await notesStore.updateNote(currentNote.value.id, {
      content: editorContent.value
    })
    hasUnsavedChanges.value = false
    lastSaved.value = new Date()
  } catch (error) {
    uiStore.showError('Fehler beim Speichern')
  }
}

// Auto-save on content change
function handleContentChange(content) {
  editorContent.value = content
  hasUnsavedChanges.value = true

  // Clear existing timeout
  if (autoSaveTimeout.value) {
    clearTimeout(autoSaveTimeout.value)
  }

  // Set new auto-save timeout (500ms debounce)
  autoSaveTimeout.value = setTimeout(() => {
    saveNote()
  }, 500)
}

// Handle title change
async function handleTitleChange(newTitle) {
  if (!currentNote.value) return

  try {
    await notesStore.updateNote(currentNote.value.id, { title: newTitle })
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren des Titels')
  }
}

// Handle icon change
async function handleIconChange(newIcon) {
  if (!currentNote.value) return

  try {
    await notesStore.updateNote(currentNote.value.id, { icon: newIcon })
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren des Icons')
  }
}

// Delete note
async function deleteCurrentNote() {
  if (!currentNote.value) return

  if (!confirm('Notiz in den Papierkorb verschieben?')) return

  try {
    await notesStore.deleteNote(currentNote.value.id)
    router.push('/notes')
    uiStore.showSuccess('Notiz in Papierkorb verschoben')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Archive note
async function archiveCurrentNote() {
  if (!currentNote.value) return

  try {
    const isArchived = !currentNote.value.is_archived
    await notesStore.updateNote(currentNote.value.id, { is_archived: isArchived })
    showNoteMenu.value = false

    if (isArchived) {
      router.push('/notes')
      uiStore.showSuccess('Notiz archiviert')
    } else {
      uiStore.showSuccess('Notiz wiederhergestellt')
    }
  } catch (error) {
    uiStore.showError('Fehler beim Archivieren')
  }
}

// Toggle favorite
async function toggleFavorite() {
  if (!currentNote.value) return

  try {
    await notesStore.toggleFavorite(currentNote.value.id)
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

// Toggle pin
async function togglePin() {
  if (!currentNote.value) return

  try {
    await notesStore.togglePin(currentNote.value.id)
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

// Duplicate note
async function duplicateNote() {
  if (!currentNote.value) return

  try {
    const note = await notesStore.duplicateNote(currentNote.value.id)
    router.push(`/notes/${note.id}`)
    showNoteMenu.value = false
    uiStore.showSuccess('Notiz dupliziert')
  } catch (error) {
    uiStore.showError('Fehler beim Duplizieren')
  }
}

// Navigate to note from sidebar/search
function navigateToNote(noteId) {
  router.push(`/notes/${noteId}`)
  showQuickSwitcher.value = false
}

// Handle wiki link navigation (from editor)
async function handleWikiLinkNavigation(href) {
  // href could be a slug or note ID
  try {
    // First try to find by slug
    const note = await notesStore.fetchNoteBySlug(href)
    if (note) {
      router.push(`/notes/${note.id}`)
    } else {
      // If not found, it might be a direct ID
      router.push(`/notes/${href}`)
    }
  } catch (error) {
    // Note doesn't exist - could offer to create it
    if (confirm(`Notiz "${href}" existiert nicht. Neu erstellen?`)) {
      const newNote = await notesStore.createNote({
        title: href.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
        content: ''
      })
      router.push(`/notes/${newNote.id}`)
    }
  }
}

// Format relative time
function formatRelativeTime(date) {
  if (!date) return ''
  const now = new Date()
  const diff = now - new Date(date)
  const seconds = Math.floor(diff / 1000)
  const minutes = Math.floor(seconds / 60)
  const hours = Math.floor(minutes / 60)

  if (seconds < 60) return 'gerade eben'
  if (minutes < 60) return `vor ${minutes} Min`
  if (hours < 24) return `vor ${hours} Std`
  return new Date(date).toLocaleDateString('de-DE')
}
</script>

<template>
  <div class="flex h-full bg-dark-900">
    <!-- Sidebar -->
    <NotesSidebar
      :collapsed="sidebarCollapsed"
      :selected-note-id="currentNote?.id"
      @toggle-collapse="sidebarCollapsed = !sidebarCollapsed"
      @select-note="navigateToNote"
      @create-note="createNewNote"
      @show-templates="showTemplateModal = true"
      @show-trash="showTrashModal = true"
    />

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <!-- Header Bar -->
      <div class="flex items-center justify-between border-b border-dark-600 bg-dark-800 px-4 py-2">
        <div class="flex items-center gap-2">
          <!-- Quick Switcher Button -->
          <button
            @click="showQuickSwitcher = true"
            class="flex items-center gap-2 rounded-lg bg-dark-700 px-3 py-1.5 text-sm text-gray-400 hover:bg-dark-600 hover:text-white transition-colors"
          >
            <MagnifyingGlassIcon class="h-4 w-4" />
            <span class="hidden sm:inline">Suchen</span>
            <kbd class="hidden sm:inline ml-2 rounded bg-dark-600 px-1.5 py-0.5 text-xs">Cmd+K</kbd>
          </button>
        </div>

        <div class="flex items-center gap-2">
          <!-- New Note Button -->
          <button
            @click="createNewNote()"
            class="flex items-center gap-2 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 transition-colors"
          >
            <PlusIcon class="h-4 w-4" />
            <span class="hidden sm:inline">Neue Notiz</span>
          </button>

          <!-- Template Button -->
          <button
            @click="showTemplateModal = true"
            class="rounded-lg bg-dark-700 p-2 text-gray-400 hover:bg-dark-600 hover:text-white transition-colors"
            title="Aus Vorlage erstellen"
          >
            <DocumentDuplicateIcon class="h-5 w-5" />
          </button>
        </div>
      </div>

      <!-- Note Area -->
      <div class="flex-1 flex overflow-hidden">
        <div v-if="!currentNote" class="flex-1 flex items-center justify-center text-gray-500">
          <div class="text-center">
            <DocumentDuplicateIcon class="h-16 w-16 mx-auto mb-4 opacity-50" />
            <p class="text-lg mb-2">Keine Notiz ausgewählt</p>
            <p class="text-sm mb-4">Wähle eine Notiz aus der Sidebar oder erstelle eine neue</p>
            <button
              @click="createNewNote()"
              class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-white hover:bg-primary-700 transition-colors"
            >
              <PlusIcon class="h-5 w-5" />
              Neue Notiz erstellen
            </button>
          </div>
        </div>

        <!-- Note Content -->
        <div v-else class="flex-1 flex flex-col min-w-0 overflow-hidden">
          <!-- Note Header with Breadcrumb and Actions -->
          <div class="flex items-center justify-between border-b border-dark-700 bg-dark-850 px-4 py-2">
            <NoteBreadcrumb
              :breadcrumb="currentNote.breadcrumb || []"
              @navigate="navigateToNote"
            />

            <div class="flex items-center gap-2">
              <!-- Save indicator -->
              <span v-if="isSaving" class="text-xs text-gray-500">
                <ArrowPathIcon class="h-4 w-4 animate-spin inline mr-1" />
                Speichert...
              </span>
              <span v-else-if="hasUnsavedChanges" class="text-xs text-yellow-500">
                Ungespeichert
              </span>
              <span v-else-if="lastSaved" class="text-xs text-gray-500">
                Gespeichert {{ formatRelativeTime(lastSaved) }}
              </span>

              <!-- Collaboration Presence -->
              <CollaborationPresence class="mx-2" />

              <!-- Favorite -->
              <button
                @click="toggleFavorite"
                :class="[
                  'rounded p-1.5 transition-colors',
                  currentNote.is_favorite
                    ? 'text-yellow-500 hover:text-yellow-400'
                    : 'text-gray-400 hover:text-yellow-500'
                ]"
                title="Favorit"
              >
                <StarIconSolid v-if="currentNote.is_favorite" class="h-5 w-5" />
                <StarIcon v-else class="h-5 w-5" />
              </button>

              <!-- Pin -->
              <button
                @click="togglePin"
                :class="[
                  'rounded p-1.5 transition-colors',
                  currentNote.is_pinned
                    ? 'text-primary-500 hover:text-primary-400'
                    : 'text-gray-400 hover:text-primary-500'
                ]"
                title="Anpinnen"
              >
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M16 4v4h1V4c0-1.1-.9-2-2-2h-4c-1.1 0-2 .9-2 2v4h1V4h6zm-9 8h10v2H7v-2zm0-2c-.55 0-1 .45-1 1v4c0 .55.45 1 1 1h4v5h2v-5h4c.55 0 1-.45 1-1v-4c0-.55-.45-1-1-1H7z"/>
                </svg>
              </button>

              <!-- Version History -->
              <button
                @click="showVersionModal = true"
                class="rounded p-1.5 text-gray-400 hover:text-white transition-colors"
                title="Versionen"
              >
                <ClockIcon class="h-5 w-5" />
              </button>

              <!-- More Menu -->
              <div class="relative">
                <button
                  @click="showNoteMenu = !showNoteMenu"
                  class="rounded p-1.5 text-gray-400 hover:text-white transition-colors"
                >
                  <EllipsisVerticalIcon class="h-5 w-5" />
                </button>

                <div
                  v-if="showNoteMenu"
                  class="absolute right-0 top-full mt-1 w-48 rounded-lg bg-dark-700 py-1 shadow-lg border border-dark-600 z-50"
                >
                  <button
                    @click="duplicateNote"
                    class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-dark-600"
                  >
                    <DocumentDuplicateIcon class="h-4 w-4" />
                    Duplizieren
                  </button>
                  <button
                    @click="showExportModal = true; showNoteMenu = false"
                    class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-dark-600"
                  >
                    <ArrowDownTrayIcon class="h-4 w-4" />
                    Exportieren
                  </button>
                  <button
                    @click="showShareModal = true; showNoteMenu = false"
                    class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-dark-600"
                  >
                    <ShareIcon class="h-4 w-4" />
                    Teilen
                  </button>
                  <button
                    @click="archiveCurrentNote"
                    class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-dark-600"
                  >
                    <ArchiveBoxIcon class="h-4 w-4" />
                    {{ currentNote?.is_archived ? 'Dearchivieren' : 'Archivieren' }}
                  </button>
                  <hr class="my-1 border-dark-600" />
                  <button
                    @click="deleteCurrentNote"
                    class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-400 hover:bg-dark-600"
                  >
                    <TrashIcon class="h-4 w-4" />
                    Löschen
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Note Header (Title, Icon, Cover) -->
          <NoteHeader
            :note="currentNote"
            @update:title="handleTitleChange"
            @update:icon="handleIconChange"
          />

          <!-- Editor -->
          <div class="flex-1 overflow-hidden flex">
            <div class="flex-1 overflow-auto">
              <NoteEditor
                :content="editorContent"
                :note-id="currentNote.id"
                @update:content="handleContentChange"
                @navigate="handleWikiLinkNavigation"
              />
            </div>

            <!-- Backlinks Panel -->
            <NoteBacklinks
              v-if="currentNote.backlinks?.length > 0"
              :backlinks="currentNote.backlinks"
              @navigate="navigateToNote"
            />
          </div>

          <!-- Status Bar -->
          <div class="flex items-center justify-between border-t border-dark-700 bg-dark-850 px-4 py-1 text-xs text-gray-500">
            <div class="flex items-center gap-4">
              <span>{{ currentNote.word_count || 0 }} Wörter</span>
              <span>Version {{ currentNote.content_version || 1 }}</span>
            </div>
            <div>
              Zuletzt bearbeitet: {{ formatRelativeTime(currentNote.updated_at) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Switcher Modal -->
    <NoteQuickSwitcher
      v-if="showQuickSwitcher"
      @close="showQuickSwitcher = false"
      @select="navigateToNote"
    />

    <!-- Template Modal -->
    <NoteTemplatesModal
      :show="showTemplateModal"
      @close="showTemplateModal = false"
      @create="handleTemplateCreate"
    />

    <!-- Version Modal -->
    <NoteVersionsModal
      :show="showVersionModal && !!currentNote"
      :note-id="currentNote?.id || ''"
      :current-title="currentNote?.title || ''"
      @close="showVersionModal = false"
      @restored="handleVersionRestored"
    />

    <!-- Trash Modal -->
    <NoteTrashModal
      v-if="showTrashModal"
      @close="showTrashModal = false"
      @restore="navigateToNote"
    />

    <!-- Export Modal -->
    <ExportModal
      :show="showExportModal"
      :note="currentNote"
      @close="showExportModal = false"
    />

    <!-- Share Modal -->
    <ShareModal
      :show="showShareModal"
      :note-id="currentNote?.id || ''"
      :note-title="currentNote?.title || 'Untitled'"
      @close="showShareModal = false"
    />

    <!-- Click outside handler for menu -->
    <div
      v-if="showNoteMenu"
      class="fixed inset-0 z-40"
      @click="showNoteMenu = false"
    />
  </div>
</template>

<style scoped>
.bg-dark-850 {
  background-color: rgb(24, 24, 27);
}
</style>
