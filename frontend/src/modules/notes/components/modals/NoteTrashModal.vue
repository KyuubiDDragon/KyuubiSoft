<script setup>
import { ref, onMounted } from 'vue'
import { useNotesStore } from '../../stores/notesStore'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  XMarkIcon,
  TrashIcon,
  ArrowPathIcon,
  DocumentTextIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'

const emit = defineEmits(['close', 'restore'])

const notesStore = useNotesStore()
const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

const trashedNotes = ref([])
const isLoading = ref(true)
const isDeleting = ref(false)
const isRestoring = ref(null) // note id being restored
const isEmptyingTrash = ref(false)

onMounted(async () => {
  try {
    trashedNotes.value = await notesStore.fetchTrash()
  } catch (error) {
    uiStore.showError('Fehler beim Laden des Papierkorbs')
  } finally {
    isLoading.value = false
  }
})

async function restoreNote(note) {
  isRestoring.value = note.id
  try {
    await notesStore.restoreNote(note.id)
    trashedNotes.value = trashedNotes.value.filter(n => n.id !== note.id)
    uiStore.showSuccess('Notiz wiederhergestellt')
    emit('restore', note.id)
    emit('close')
  } catch (error) {
    uiStore.showError('Fehler beim Wiederherstellen')
  } finally {
    isRestoring.value = null
  }
}

async function permanentDelete(note) {
  if (!await confirm({ message: `"${note.title}" endgültig löschen? Dies kann nicht rückgängig gemacht werden.`, type: 'danger', confirmText: 'Löschen' })) return

  isDeleting.value = true
  try {
    await notesStore.permanentDeleteNote(note.id)
    trashedNotes.value = trashedNotes.value.filter(n => n.id !== note.id)
    uiStore.showSuccess('Notiz endgültig gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  } finally {
    isDeleting.value = false
  }
}

async function emptyTrash() {
  if (!await confirm({ message: `Papierkorb leeren? ${trashedNotes.value.length} Notizen werden endgültig gelöscht.`, type: 'danger', confirmText: 'Löschen' })) return

  isEmptyingTrash.value = true
  try {
    await notesStore.emptyTrash()
    trashedNotes.value = []
    uiStore.showSuccess('Papierkorb geleert')
  } catch (error) {
    uiStore.showError('Fehler beim Leeren des Papierkorbs')
  } finally {
    isEmptyingTrash.value = false
  }
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <!-- Backdrop -->
    <div
      class="absolute inset-0 bg-black/50 backdrop-blur-sm"
      @click="$emit('close')"
    />

    <!-- Modal -->
    <div class="relative w-full max-w-2xl max-h-[80vh] bg-dark-800 rounded-xl shadow-2xl border border-dark-600 overflow-hidden flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-dark-600">
        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
          <TrashIcon class="h-5 w-5 text-red-400" />
          Papierkorb
        </h2>
        <div class="flex items-center gap-2">
          <button
            v-if="trashedNotes.length > 0"
            @click="emptyTrash"
            :disabled="isEmptyingTrash"
            class="flex items-center gap-2 rounded-lg bg-red-600/20 px-3 py-1.5 text-sm text-red-400 hover:bg-red-600/30 disabled:opacity-50"
          >
            <TrashIcon class="h-4 w-4" />
            Leeren
          </button>
          <button
            @click="$emit('close')"
            class="rounded p-1 text-gray-400 hover:bg-dark-700 hover:text-white"
          >
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-y-auto">
        <!-- Loading -->
        <div v-if="isLoading" class="p-8 text-center text-gray-500">
          <ArrowPathIcon class="h-8 w-8 mx-auto mb-2 animate-spin" />
          <p>Laden...</p>
        </div>

        <!-- Empty state -->
        <div v-else-if="trashedNotes.length === 0" class="p-8 text-center text-gray-500">
          <TrashIcon class="h-12 w-12 mx-auto mb-2 opacity-50" />
          <p class="text-lg mb-1">Papierkorb ist leer</p>
          <p class="text-sm">Gelöschte Notizen erscheinen hier</p>
        </div>

        <!-- Trashed notes list -->
        <div v-else class="divide-y divide-dark-700">
          <div
            v-for="note in trashedNotes"
            :key="note.id"
            class="flex items-center gap-4 p-4 hover:bg-dark-700/50"
          >
            <!-- Icon -->
            <div class="flex-shrink-0">
              <span v-if="note.icon" class="text-2xl">{{ note.icon }}</span>
              <DocumentTextIcon v-else class="h-6 w-6 text-gray-500" />
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
              <h3 class="font-medium text-white truncate">{{ note.title }}</h3>
              <p class="text-xs text-gray-500">
                Gelöscht am {{ formatDate(note.deleted_at) }}
              </p>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
              <button
                @click="restoreNote(note)"
                :disabled="isRestoring === note.id"
                class="flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm text-white hover:bg-primary-700 disabled:opacity-50"
              >
                <ArrowPathIcon :class="['h-4 w-4', isRestoring === note.id ? 'animate-spin' : '']" />
                Wiederherstellen
              </button>
              <button
                @click="permanentDelete(note)"
                :disabled="isDeleting"
                class="flex items-center gap-1 rounded-lg bg-red-600/20 px-3 py-1.5 text-sm text-red-400 hover:bg-red-600/30 disabled:opacity-50"
                title="Endgültig löschen"
              >
                <TrashIcon class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer warning -->
      <div class="px-6 py-3 border-t border-dark-700 bg-dark-850 flex items-center gap-2 text-xs text-gray-500">
        <ExclamationTriangleIcon class="h-4 w-4 text-yellow-500" />
        <span>Notizen im Papierkorb werden nach 30 Tagen automatisch gelöscht</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bg-dark-850 {
  background-color: rgb(24, 24, 27);
}
</style>
