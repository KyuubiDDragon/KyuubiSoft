<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useNotesStore } from '../../stores/notesStore'
import {
  MagnifyingGlassIcon,
  DocumentTextIcon,
  ClockIcon
} from '@heroicons/vue/24/outline'

const emit = defineEmits(['close', 'select'])

const notesStore = useNotesStore()

const searchQuery = ref('')
const selectedIndex = ref(0)
const searchInput = ref(null)
const isSearching = ref(false)

const recentNotes = computed(() => notesStore.recentNotes)

const searchResults = computed(() => {
  if (!searchQuery.value) {
    return recentNotes.value.slice(0, 10)
  }
  return notesStore.searchResults
})

onMounted(async () => {
  await nextTick()
  searchInput.value?.focus()
  await notesStore.fetchRecent()
})

// Search when query changes
let searchTimeout = null
watch(searchQuery, async (newQuery) => {
  selectedIndex.value = 0

  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }

  if (newQuery.length >= 2) {
    isSearching.value = true
    searchTimeout = setTimeout(async () => {
      await notesStore.search(newQuery)
      isSearching.value = false
    }, 200)
  }
})

function handleKeydown(e) {
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    selectedIndex.value = Math.min(selectedIndex.value + 1, searchResults.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (searchResults.value[selectedIndex.value]) {
      selectNote(searchResults.value[selectedIndex.value].id)
    }
  } else if (e.key === 'Escape') {
    emit('close')
  }
}

function selectNote(noteId) {
  emit('select', noteId)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const now = new Date()
  const diff = now - date
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (minutes < 60) return `vor ${minutes} Min`
  if (hours < 24) return `vor ${hours} Std`
  if (days < 7) return `vor ${days} Tagen`
  return date.toLocaleDateString('de-DE')
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-start justify-center pt-24">
    <!-- Backdrop -->
    <div
      class="absolute inset-0 bg-black/50 backdrop-blur-sm"
      @click="$emit('close')"
    />

    <!-- Modal -->
    <div class="relative w-full max-w-xl bg-dark-800 rounded-xl shadow-2xl border border-dark-600 overflow-hidden">
      <!-- Search input -->
      <div class="flex items-center gap-3 px-4 py-3 border-b border-dark-600">
        <MagnifyingGlassIcon class="h-5 w-5 text-gray-500" />
        <input
          ref="searchInput"
          v-model="searchQuery"
          type="text"
          placeholder="Notiz suchen..."
          class="flex-1 bg-transparent text-white placeholder-gray-500 focus:outline-none"
          @keydown="handleKeydown"
        />
        <kbd class="rounded bg-dark-600 px-2 py-0.5 text-xs text-gray-400">esc</kbd>
      </div>

      <!-- Results -->
      <div class="max-h-96 overflow-y-auto">
        <!-- Loading -->
        <div v-if="isSearching" class="p-4 text-center text-gray-500">
          Suchen...
        </div>

        <!-- Empty state -->
        <div v-else-if="searchResults.length === 0" class="p-8 text-center text-gray-500">
          <DocumentTextIcon class="h-12 w-12 mx-auto mb-2 opacity-50" />
          <p v-if="searchQuery">Keine Ergebnisse für "{{ searchQuery }}"</p>
          <p v-else>Noch keine kürzlich geöffneten Notizen</p>
        </div>

        <!-- Results list -->
        <div v-else>
          <div v-if="!searchQuery" class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">
            <ClockIcon class="h-4 w-4 inline mr-1" />
            Kürzlich
          </div>

          <button
            v-for="(note, index) in searchResults"
            :key="note.id"
            @click="selectNote(note.id)"
            @mouseenter="selectedIndex = index"
            :class="[
              'w-full flex items-center gap-3 px-4 py-3 text-left transition-colors',
              selectedIndex === index ? 'bg-dark-700' : 'hover:bg-dark-700/50'
            ]"
          >
            <span v-if="note.icon" class="text-xl">{{ note.icon }}</span>
            <DocumentTextIcon v-else class="h-5 w-5 text-gray-500" />

            <div class="flex-1 min-w-0">
              <div class="font-medium text-white truncate">{{ note.title }}</div>
              <div v-if="note.preview" class="text-xs text-gray-500 truncate">
                {{ note.preview }}
              </div>
            </div>

            <span class="text-xs text-gray-500 flex-shrink-0">
              {{ formatDate(note.accessed_at || note.updated_at) }}
            </span>
          </button>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-between px-4 py-2 border-t border-dark-600 text-xs text-gray-500">
        <div class="flex items-center gap-4">
          <span><kbd class="rounded bg-dark-600 px-1">↑↓</kbd> navigieren</span>
          <span><kbd class="rounded bg-dark-600 px-1">↵</kbd> öffnen</span>
        </div>
      </div>
    </div>
  </div>
</template>
