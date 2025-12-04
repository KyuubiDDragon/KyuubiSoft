<script setup>
import { ref, watch, onMounted } from 'vue'

const notes = ref([])
const currentNote = ref(null)
const searchQuery = ref('')
const showDeleteConfirm = ref(false)
const noteToDelete = ref(null)

// Load notes from localStorage
onMounted(() => {
  const saved = localStorage.getItem('kyuubi_quick_notes')
  if (saved) {
    try {
      notes.value = JSON.parse(saved)
    } catch (e) {
      notes.value = []
    }
  }
})

// Save to localStorage whenever notes change
watch(notes, (newNotes) => {
  localStorage.setItem('kyuubi_quick_notes', JSON.stringify(newNotes))
}, { deep: true })

// Auto-save current note
watch(currentNote, (note) => {
  if (note) {
    note.updatedAt = new Date().toISOString()
  }
}, { deep: true })

const filteredNotes = computed(() => {
  if (!searchQuery.value) return notes.value
  const query = searchQuery.value.toLowerCase()
  return notes.value.filter(note =>
    note.title.toLowerCase().includes(query) ||
    note.content.toLowerCase().includes(query)
  )
})

function createNote() {
  const newNote = {
    id: Date.now().toString(),
    title: 'Neue Notiz',
    content: '',
    color: '#6366f1',
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
    pinned: false,
  }
  notes.value.unshift(newNote)
  currentNote.value = newNote
}

function selectNote(note) {
  currentNote.value = note
}

function confirmDelete(note) {
  noteToDelete.value = note
  showDeleteConfirm.value = true
}

function deleteNote() {
  if (!noteToDelete.value) return

  const index = notes.value.findIndex(n => n.id === noteToDelete.value.id)
  if (index !== -1) {
    notes.value.splice(index, 1)
    if (currentNote.value?.id === noteToDelete.value.id) {
      currentNote.value = notes.value[0] || null
    }
  }

  showDeleteConfirm.value = false
  noteToDelete.value = null
}

function togglePin(note) {
  note.pinned = !note.pinned
  // Sort: pinned first, then by updatedAt
  notes.value.sort((a, b) => {
    if (a.pinned && !b.pinned) return -1
    if (!a.pinned && b.pinned) return 1
    return new Date(b.updatedAt) - new Date(a.updatedAt)
  })
}

function duplicateNote(note) {
  const newNote = {
    ...note,
    id: Date.now().toString(),
    title: note.title + ' (Kopie)',
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
    pinned: false,
  }
  notes.value.unshift(newNote)
  currentNote.value = newNote
}

function exportNotes() {
  const data = JSON.stringify(notes.value, null, 2)
  const blob = new Blob([data], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'quick_notes_export.json'
  a.click()
  URL.revokeObjectURL(url)
}

function importNotes(event) {
  const file = event.target.files[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = (e) => {
    try {
      const imported = JSON.parse(e.target.result)
      if (Array.isArray(imported)) {
        // Merge with existing notes (avoid duplicates by id)
        const existingIds = new Set(notes.value.map(n => n.id))
        const newNotes = imported.filter(n => !existingIds.has(n.id))
        notes.value = [...notes.value, ...newNotes]
      }
    } catch (error) {
      console.error('Import error:', error)
    }
  }
  reader.readAsText(file)
}

const colors = [
  '#6366f1', '#8b5cf6', '#ec4899', '#ef4444',
  '#f97316', '#eab308', '#22c55e', '#14b8a6',
]

function formatDate(dateStr) {
  const date = new Date(dateStr)
  return date.toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

import { computed } from 'vue'
</script>

<template>
  <div class="flex h-[500px] -m-4">
    <!-- Sidebar / Note List -->
    <div class="w-64 bg-dark-800 border-r border-dark-600 flex flex-col">
      <!-- Header -->
      <div class="p-3 border-b border-dark-600">
        <button
          @click="createNote"
          class="w-full btn-primary py-2 text-sm"
        >
          + Neue Notiz
        </button>
      </div>

      <!-- Search -->
      <div class="p-2 border-b border-dark-600">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Suchen..."
          class="input w-full text-sm py-1"
        />
      </div>

      <!-- Notes List -->
      <div class="flex-1 overflow-y-auto">
        <div
          v-for="note in filteredNotes"
          :key="note.id"
          @click="selectNote(note)"
          class="p-3 border-b border-dark-700 cursor-pointer hover:bg-dark-700 transition-colors"
          :class="currentNote?.id === note.id ? 'bg-dark-700' : ''"
        >
          <div class="flex items-start gap-2">
            <div
              class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0"
              :style="{ backgroundColor: note.color }"
            ></div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-1">
                <span v-if="note.pinned" class="text-xs">📌</span>
                <h4 class="text-sm font-medium text-white truncate">{{ note.title }}</h4>
              </div>
              <p class="text-xs text-gray-500 truncate">{{ note.content || 'Keine Vorschau' }}</p>
              <p class="text-xs text-gray-600 mt-1">{{ formatDate(note.updatedAt) }}</p>
            </div>
          </div>
        </div>

        <div v-if="filteredNotes.length === 0" class="p-4 text-center text-gray-500 text-sm">
          {{ searchQuery ? 'Keine Ergebnisse' : 'Keine Notizen' }}
        </div>
      </div>

      <!-- Footer -->
      <div class="p-2 border-t border-dark-600 flex gap-1">
        <button @click="exportNotes" class="text-xs text-gray-400 hover:text-white px-2">
          Export
        </button>
        <label class="text-xs text-gray-400 hover:text-white px-2 cursor-pointer">
          Import
          <input type="file" accept=".json" @change="importNotes" class="hidden" />
        </label>
      </div>
    </div>

    <!-- Editor -->
    <div class="flex-1 flex flex-col bg-dark-900">
      <template v-if="currentNote">
        <!-- Toolbar -->
        <div class="p-3 border-b border-dark-600 flex items-center gap-3">
          <input
            v-model="currentNote.title"
            type="text"
            class="flex-1 bg-transparent text-lg font-medium text-white focus:outline-none"
            placeholder="Titel..."
          />

          <!-- Color picker -->
          <div class="flex gap-1">
            <button
              v-for="color in colors"
              :key="color"
              @click="currentNote.color = color"
              class="w-5 h-5 rounded-full transition-transform hover:scale-110"
              :style="{ backgroundColor: color }"
              :class="currentNote.color === color ? 'ring-2 ring-white ring-offset-2 ring-offset-dark-800' : ''"
            ></button>
          </div>

          <button
            @click="togglePin(currentNote)"
            class="p-1 text-gray-400 hover:text-white"
            :class="currentNote.pinned ? 'text-yellow-400' : ''"
            title="Anpinnen"
          >
            📌
          </button>

          <button
            @click="duplicateNote(currentNote)"
            class="p-1 text-gray-400 hover:text-white"
            title="Duplizieren"
          >
            📋
          </button>

          <button
            @click="confirmDelete(currentNote)"
            class="p-1 text-gray-400 hover:text-red-400"
            title="Löschen"
          >
            🗑️
          </button>
        </div>

        <!-- Content -->
        <textarea
          v-model="currentNote.content"
          class="flex-1 p-4 bg-transparent text-gray-300 resize-none focus:outline-none"
          placeholder="Notiz hier eingeben..."
        ></textarea>

        <!-- Status bar -->
        <div class="px-3 py-2 border-t border-dark-600 text-xs text-gray-500 flex justify-between">
          <span>{{ currentNote.content.length }} Zeichen</span>
          <span>Zuletzt bearbeitet: {{ formatDate(currentNote.updatedAt) }}</span>
        </div>
      </template>

      <div v-else class="flex-1 flex items-center justify-center text-gray-500">
        <div class="text-center">
          <div class="text-4xl mb-2">📝</div>
          <p>Wähle eine Notiz oder erstelle eine neue</p>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      
    >
      <div class="bg-dark-800 rounded-lg p-6 max-w-sm">
        <h3 class="text-lg font-medium text-white mb-2">Notiz löschen?</h3>
        <p class="text-gray-400 text-sm mb-4">
          Möchtest du "{{ noteToDelete?.title }}" wirklich löschen?
        </p>
        <div class="flex gap-2">
          <button @click="showDeleteConfirm = false" class="btn-secondary flex-1">
            Abbrechen
          </button>
          <button @click="deleteNote" class="btn-primary bg-red-600 hover:bg-red-500 flex-1">
            Löschen
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
