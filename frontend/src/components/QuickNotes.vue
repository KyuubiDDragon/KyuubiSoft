<script setup>
import { ref, onMounted } from 'vue'
import api from '@/core/api/axios'
import {
  PencilSquareIcon,
  XMarkIcon,
  PlusIcon,
  TrashIcon,
  MapPinIcon,
  ChevronDownIcon,
  ChevronUpIcon,
} from '@heroicons/vue/24/outline'

// Alias for consistency
const PinIcon = MapPinIcon

const isOpen = ref(false)
const isMinimized = ref(false)
const notes = ref([])
const loading = ref(false)
const editingNote = ref(null)
const newNoteContent = ref('')

const colors = [
  { value: 'default', class: 'bg-dark-700' },
  { value: 'yellow', class: 'bg-yellow-900/50' },
  { value: 'green', class: 'bg-green-900/50' },
  { value: 'blue', class: 'bg-blue-900/50' },
  { value: 'red', class: 'bg-red-900/50' },
  { value: 'purple', class: 'bg-purple-900/50' },
]

onMounted(() => {
  fetchNotes()
})

async function fetchNotes() {
  loading.value = true
  try {
    const response = await api.get('/api/v1/quick-notes')
    notes.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch notes:', error)
  } finally {
    loading.value = false
  }
}

async function createNote() {
  if (!newNoteContent.value.trim()) return

  try {
    const response = await api.post('/api/v1/quick-notes', {
      content: newNoteContent.value,
    })
    notes.value.unshift(response.data.data)
    newNoteContent.value = ''
  } catch (error) {
    console.error('Failed to create note:', error)
  }
}

async function updateNote(note) {
  try {
    await api.put(`/api/v1/quick-notes/${note.id}`, {
      content: note.content,
      is_pinned: note.is_pinned,
      color: note.color,
    })
    editingNote.value = null
  } catch (error) {
    console.error('Failed to update note:', error)
  }
}

async function deleteNote(noteId) {
  try {
    await api.delete(`/api/v1/quick-notes/${noteId}`)
    notes.value = notes.value.filter(n => n.id !== noteId)
  } catch (error) {
    console.error('Failed to delete note:', error)
  }
}

async function togglePin(note) {
  note.is_pinned = !note.is_pinned
  await updateNote(note)
  // Resort notes
  notes.value.sort((a, b) => {
    if (a.is_pinned && !b.is_pinned) return -1
    if (!a.is_pinned && b.is_pinned) return 1
    return 0
  })
}

function setNoteColor(note, color) {
  note.color = color
  updateNote(note)
}

function getColorClass(color) {
  return colors.find(c => c.value === color)?.class || 'bg-dark-700'
}

function toggleOpen() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    isMinimized.value = false
  }
}
</script>

<template>
  <!-- Floating Button -->
  <button
    v-if="!isOpen"
    @click="toggleOpen"
    class="fixed bottom-6 right-6 w-14 h-14 bg-primary-600 hover:bg-primary-500 rounded-full shadow-lg flex items-center justify-center text-white transition-all z-50 group"
    title="Quick Notes"
  >
    <PencilSquareIcon class="w-6 h-6" />
    <span
      v-if="notes.length > 0"
      class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full text-xs flex items-center justify-center"
    >
      {{ notes.length > 9 ? '9+' : notes.length }}
    </span>
  </button>

  <!-- Notes Panel -->
  <Transition
    enter-active-class="transition ease-out duration-200"
    enter-from-class="transform opacity-0 translate-y-4"
    enter-to-class="transform opacity-100 translate-y-0"
    leave-active-class="transition ease-in duration-150"
    leave-from-class="transform opacity-100 translate-y-0"
    leave-to-class="transform opacity-0 translate-y-4"
  >
    <div
      v-if="isOpen"
      class="fixed bottom-6 right-6 w-80 bg-dark-800 border border-dark-600 rounded-xl shadow-2xl z-50 overflow-hidden"
      :class="{ 'h-auto': isMinimized, 'max-h-[500px]': !isMinimized }"
    >
      <!-- Header -->
      <div class="flex items-center justify-between px-4 py-3 bg-dark-700 border-b border-dark-600">
        <div class="flex items-center gap-2">
          <PencilSquareIcon class="w-5 h-5 text-primary-400" />
          <h3 class="font-semibold text-white">Quick Notes</h3>
        </div>
        <div class="flex items-center gap-1">
          <button
            @click="isMinimized = !isMinimized"
            class="p-1 text-gray-400 hover:text-white rounded transition-colors"
          >
            <ChevronDownIcon v-if="!isMinimized" class="w-4 h-4" />
            <ChevronUpIcon v-else class="w-4 h-4" />
          </button>
          <button
            @click="toggleOpen"
            class="p-1 text-gray-400 hover:text-white rounded transition-colors"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Content -->
      <div v-if="!isMinimized" class="flex flex-col">
        <!-- New Note Input -->
        <div class="p-3 border-b border-dark-600">
          <div class="flex gap-2">
            <input
              v-model="newNoteContent"
              @keyup.enter="createNote"
              type="text"
              placeholder="Neue Notiz..."
              class="flex-1 px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
            />
            <button
              @click="createNote"
              :disabled="!newNoteContent.trim()"
              class="px-3 py-2 bg-primary-600 hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors"
            >
              <PlusIcon class="w-4 h-4 text-white" />
            </button>
          </div>
        </div>

        <!-- Notes List -->
        <div class="flex-1 overflow-y-auto max-h-[350px] p-2 space-y-2">
          <div
            v-if="loading"
            class="flex items-center justify-center py-8"
          >
            <div class="w-6 h-6 border-2 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
          </div>

          <div
            v-else-if="notes.length === 0"
            class="text-center py-8 text-gray-500 text-sm"
          >
            Keine Notizen vorhanden
          </div>

          <div
            v-for="note in notes"
            :key="note.id"
            class="group relative p-3 rounded-lg transition-colors"
            :class="getColorClass(note.color)"
          >
            <!-- Pin indicator -->
            <div
              v-if="note.is_pinned"
              class="absolute -top-1 -left-1 w-4 h-4 bg-yellow-500 rounded-full flex items-center justify-center"
            >
              <PinIcon class="w-2.5 h-2.5 text-yellow-900" />
            </div>

            <!-- Content -->
            <div v-if="editingNote === note.id">
              <textarea
                v-model="note.content"
                @blur="updateNote(note)"
                @keydown.escape="editingNote = null"
                rows="3"
                class="w-full px-2 py-1 bg-dark-600 border border-dark-500 rounded text-sm text-white resize-none focus:outline-none focus:border-primary-500"
                autofocus
              ></textarea>
            </div>
            <p
              v-else
              @dblclick="editingNote = note.id"
              class="text-sm text-gray-200 whitespace-pre-wrap cursor-pointer"
            >
              {{ note.content }}
            </p>

            <!-- Actions -->
            <div class="absolute top-2 right-2 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
              <!-- Color picker -->
              <div class="relative group/color">
                <button class="p-1 text-gray-400 hover:text-white rounded transition-colors">
                  <div class="w-3 h-3 rounded-full" :class="getColorClass(note.color)"></div>
                </button>
                <div class="absolute right-0 top-full mt-1 p-1 bg-dark-600 rounded-lg shadow-lg hidden group-hover/color:flex gap-1 z-10">
                  <button
                    v-for="color in colors"
                    :key="color.value"
                    @click="setNoteColor(note, color.value)"
                    class="w-4 h-4 rounded-full border border-dark-500 hover:scale-110 transition-transform"
                    :class="color.class"
                  ></button>
                </div>
              </div>

              <button
                @click="togglePin(note)"
                class="p-1 text-gray-400 hover:text-yellow-400 rounded transition-colors"
                :class="{ 'text-yellow-400': note.is_pinned }"
              >
                <PinIcon class="w-3.5 h-3.5" />
              </button>

              <button
                @click="deleteNote(note.id)"
                class="p-1 text-gray-400 hover:text-red-400 rounded transition-colors"
              >
                <TrashIcon class="w-3.5 h-3.5" />
              </button>
            </div>

            <!-- Timestamp -->
            <p class="text-xs text-gray-500 mt-2">
              {{ new Date(note.updated_at).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>
