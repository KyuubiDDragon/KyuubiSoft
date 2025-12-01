<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  MagnifyingGlassIcon,
  XMarkIcon,
  DocumentTextIcon,
  ListBulletIcon,
  CodeBracketIcon,
  TableCellsIcon
} from '@heroicons/vue/24/outline'
import api from '@/core/api/axios'

const router = useRouter()

const isOpen = ref(false)
const searchQuery = ref('')
const results = ref({ documents: [], lists: [], total: 0 })
const isLoading = ref(false)
const selectedIndex = ref(-1)

let searchTimeout = null

const allResults = computed(() => {
  const items = []
  results.value.documents.forEach(doc => items.push({ ...doc, category: 'document' }))
  results.value.lists.forEach(list => items.push({ ...list, category: 'list' }))
  return items
})

function getIcon(item) {
  if (item.category === 'list') return ListBulletIcon
  if (item.format === 'code') return CodeBracketIcon
  if (item.format === 'spreadsheet') return TableCellsIcon
  return DocumentTextIcon
}

function getTypeLabel(item) {
  if (item.category === 'list') return item.list_type || 'Liste'
  if (item.format === 'code') return 'Code'
  if (item.format === 'spreadsheet') return 'Tabelle'
  if (item.format === 'markdown') return 'Markdown'
  return 'Rich Text'
}

function open() {
  isOpen.value = true
  searchQuery.value = ''
  results.value = { documents: [], lists: [], total: 0 }
  selectedIndex.value = -1
}

function close() {
  isOpen.value = false
  searchQuery.value = ''
}

async function search() {
  if (searchQuery.value.length < 2) {
    results.value = { documents: [], lists: [], total: 0 }
    return
  }

  isLoading.value = true
  try {
    const response = await api.get('/api/v1/search', {
      params: { q: searchQuery.value, limit: 10 }
    })
    results.value = response.data.data
    selectedIndex.value = -1
  } catch (error) {
    console.error('Search error:', error)
  } finally {
    isLoading.value = false
  }
}

function navigateTo(item) {
  close()
  if (item.category === 'list') {
    router.push({ name: 'lists', query: { open: item.id } })
  } else {
    router.push({ name: 'documents', query: { open: item.id } })
  }
}

function handleKeydown(e) {
  if (e.key === 'Escape') {
    close()
  } else if (e.key === 'ArrowDown') {
    e.preventDefault()
    selectedIndex.value = Math.min(selectedIndex.value + 1, allResults.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    selectedIndex.value = Math.max(selectedIndex.value - 1, -1)
  } else if (e.key === 'Enter' && selectedIndex.value >= 0) {
    e.preventDefault()
    navigateTo(allResults.value[selectedIndex.value])
  }
}

function handleGlobalKeydown(e) {
  // Cmd/Ctrl + K to open search
  if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
    e.preventDefault()
    open()
  }
}

watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(search, 300)
})

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
})

defineExpose({ open, close })
</script>

<template>
  <!-- Search Trigger Button -->
  <button
    @click="open"
    class="flex items-center gap-2 px-3 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg transition-colors text-gray-400 hover:text-white"
  >
    <MagnifyingGlassIcon class="w-5 h-5" />
    <span class="hidden sm:inline text-sm">Suchen...</span>
    <kbd class="hidden md:inline-flex items-center gap-1 px-2 py-0.5 text-xs bg-dark-600 rounded">
      <span class="text-[10px]">⌘</span>K
    </kbd>
  </button>

  <!-- Search Modal -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-start justify-center pt-[15vh]"
        @click.self="close"
      >
        <div class="bg-dark-800 rounded-xl w-full max-w-2xl border border-dark-700 shadow-2xl overflow-hidden">
          <!-- Search Input -->
          <div class="flex items-center gap-3 p-4 border-b border-dark-700">
            <MagnifyingGlassIcon class="w-5 h-5 text-gray-400 shrink-0" />
            <input
              v-model="searchQuery"
              @keydown="handleKeydown"
              type="text"
              placeholder="Dokumente und Listen durchsuchen..."
              class="flex-1 bg-transparent text-white placeholder-gray-500 focus:outline-none text-lg"
              autofocus
            />
            <button @click="close" class="p-1 hover:bg-dark-700 rounded transition-colors">
              <XMarkIcon class="w-5 h-5 text-gray-400" />
            </button>
          </div>

          <!-- Results -->
          <div class="max-h-[60vh] overflow-y-auto">
            <!-- Loading -->
            <div v-if="isLoading" class="p-8 text-center">
              <div class="w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto"></div>
            </div>

            <!-- No Query -->
            <div v-else-if="searchQuery.length < 2" class="p-8 text-center text-gray-500">
              <MagnifyingGlassIcon class="w-12 h-12 mx-auto mb-3 opacity-50" />
              <p>Gib mindestens 2 Zeichen ein um zu suchen</p>
            </div>

            <!-- No Results -->
            <div v-else-if="allResults.length === 0" class="p-8 text-center text-gray-500">
              <p>Keine Ergebnisse für "{{ searchQuery }}"</p>
            </div>

            <!-- Results List -->
            <div v-else class="py-2">
              <!-- Documents Section -->
              <template v-if="results.documents.length > 0">
                <div class="px-4 py-2 text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Dokumente
                </div>
                <button
                  v-for="(doc, index) in results.documents"
                  :key="'doc-' + doc.id"
                  @click="navigateTo({ ...doc, category: 'document' })"
                  class="w-full flex items-start gap-3 px-4 py-3 hover:bg-dark-700 transition-colors text-left"
                  :class="{ 'bg-dark-700': selectedIndex === index }"
                >
                  <div class="w-10 h-10 rounded-lg bg-primary-600/20 flex items-center justify-center shrink-0">
                    <component :is="getIcon({ ...doc, category: 'document' })" class="w-5 h-5 text-primary-400" />
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                      <span class="font-medium text-white truncate">{{ doc.title }}</span>
                      <span class="badge badge-primary text-xs">{{ getTypeLabel({ ...doc, category: 'document' }) }}</span>
                    </div>
                    <p v-if="doc.snippet" class="text-sm text-gray-400 mt-1 line-clamp-2">{{ doc.snippet }}</p>
                  </div>
                </button>
              </template>

              <!-- Lists Section -->
              <template v-if="results.lists.length > 0">
                <div class="px-4 py-2 text-xs font-medium text-gray-500 uppercase tracking-wider" :class="{ 'mt-2 border-t border-dark-700 pt-4': results.documents.length > 0 }">
                  Listen
                </div>
                <button
                  v-for="(list, index) in results.lists"
                  :key="'list-' + list.id"
                  @click="navigateTo({ ...list, category: 'list' })"
                  class="w-full flex items-start gap-3 px-4 py-3 hover:bg-dark-700 transition-colors text-left"
                  :class="{ 'bg-dark-700': selectedIndex === results.documents.length + index }"
                >
                  <div
                    class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
                    :style="{ backgroundColor: list.color || '#3B82F6' }"
                  >
                    <ListBulletIcon class="w-5 h-5 text-white" />
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                      <span class="font-medium text-white truncate">{{ list.title }}</span>
                      <span class="badge badge-primary text-xs">{{ list.list_type || 'Liste' }}</span>
                      <span v-if="list.item_count" class="text-xs text-gray-500">{{ list.item_count }} Einträge</span>
                    </div>
                    <p v-if="list.snippet" class="text-sm text-gray-400 mt-1 line-clamp-2">{{ list.snippet }}</p>
                  </div>
                </button>
              </template>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-4 py-3 border-t border-dark-700 flex items-center justify-between text-xs text-gray-500">
            <div class="flex items-center gap-4">
              <span class="flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 bg-dark-700 rounded">↑↓</kbd>
                Navigieren
              </span>
              <span class="flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 bg-dark-700 rounded">↵</kbd>
                Öffnen
              </span>
              <span class="flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 bg-dark-700 rounded">Esc</kbd>
                Schließen
              </span>
            </div>
            <span v-if="results.total > 0">{{ results.total }} Ergebnisse</span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
