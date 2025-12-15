<script setup>
import { ref, computed, provide, onMounted, watch } from 'vue'
import { useNotesStore } from '../../stores/notesStore'
import { useUiStore } from '@/stores/ui'
import NoteTreeItem from './NoteTreeItem.vue'
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  PlusIcon,
  StarIcon,
  ClockIcon,
  TrashIcon,
  FolderIcon,
  MagnifyingGlassIcon,
  DocumentTextIcon,
  Squares2X2Icon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  collapsed: {
    type: Boolean,
    default: false
  },
  selectedNoteId: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['toggle-collapse', 'select-note', 'create-note', 'show-templates', 'show-trash'])

const notesStore = useNotesStore()
const uiStore = useUiStore()

// State
const searchQuery = ref('')
const expandedSections = ref({
  favorites: true,
  pinned: true,
  recent: true,
  all: true
})

// Expanded notes state for tree (persisted in localStorage)
const STORAGE_KEY = 'notes-expanded-items'
const expandedNotes = ref(new Set(
  JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')
))

// Provide expanded state to tree items
provide('expandedNotes', expandedNotes)

// Persist expanded state
watch(expandedNotes, (val) => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify([...val]))
}, { deep: true })

// Computed
const noteTree = computed(() => notesStore.noteTree)
const favoriteNotes = computed(() => notesStore.favoriteNotes)
const recentNotes = computed(() => notesStore.recentNotes)
const pinnedNotes = computed(() => notesStore.pinnedNotes)
const isLoadingTree = computed(() => notesStore.isLoadingTree)

const filteredTree = computed(() => {
  if (!searchQuery.value) return noteTree.value

  const query = searchQuery.value.toLowerCase()
  return filterTree(noteTree.value, query)
})

// Filter tree by search query
function filterTree(nodes, query) {
  return nodes
    .map(node => {
      const matches = node.title.toLowerCase().includes(query)
      const children = node.children ? filterTree(node.children, query) : []

      if (matches || children.length > 0) {
        return { ...node, children }
      }
      return null
    })
    .filter(Boolean)
}

// Toggle section
function toggleSection(section) {
  expandedSections.value[section] = !expandedSections.value[section]
}

// Handle note selection
function selectNote(noteId) {
  emit('select-note', noteId)
}

// Handle create note
function createNote(parentId = null) {
  emit('create-note', parentId)
}

// Handle note move (drag & drop)
async function handleMove({ noteId, newParentId }) {
  try {
    await notesStore.moveNote(noteId, newParentId)
    uiStore.showSuccess('Notiz verschoben')
  } catch (error) {
    uiStore.showError('Fehler beim Verschieben der Notiz')
  }
}

// Handle note reorder (drag & drop)
async function handleReorder({ noteId, targetId, position }) {
  try {
    // Get the target note to determine parent and position
    const findNoteInTree = (nodes, id, parent = null) => {
      for (let i = 0; i < nodes.length; i++) {
        if (nodes[i].id === id) {
          return { note: nodes[i], parent, index: i, siblings: nodes }
        }
        if (nodes[i].children) {
          const found = findNoteInTree(nodes[i].children, id, nodes[i])
          if (found) return found
        }
      }
      return null
    }

    const targetInfo = findNoteInTree(noteTree.value, targetId)
    if (!targetInfo) return

    const newParentId = targetInfo.parent?.id || null
    let newIndex = targetInfo.index

    if (position === 'after') {
      newIndex++
    }

    // Build reorder items for the parent
    const items = targetInfo.siblings
      .filter(n => n.id !== noteId)
      .map((n, i) => ({
        id: n.id,
        sort_order: i < newIndex ? i : i + 1
      }))

    // Insert the moved note
    items.push({ id: noteId, sort_order: newIndex })
    items.sort((a, b) => a.sort_order - b.sort_order)

    // First move to the new parent if different
    const sourceInfo = findNoteInTree(noteTree.value, noteId)
    const sourceParentId = sourceInfo?.parent?.id || null

    if (sourceParentId !== newParentId) {
      await notesStore.moveNote(noteId, newParentId)
    }

    // Then reorder
    await notesStore.reorderNotes(items)
    await notesStore.fetchTree()
  } catch (error) {
    console.error('Reorder error:', error)
    uiStore.showError('Fehler beim Sortieren')
  }
}

// Navigate to trash
function showTrash() {
  emit('show-trash')
}

// Expand parent notes when selecting a note
function expandToNote(noteId) {
  const findPath = (nodes, id, path = []) => {
    for (const node of nodes) {
      if (node.id === id) {
        return path
      }
      if (node.children) {
        const found = findPath(node.children, id, [...path, node.id])
        if (found) return found
      }
    }
    return null
  }

  const path = findPath(noteTree.value, noteId)
  if (path) {
    const newExpanded = new Set(expandedNotes.value)
    path.forEach(id => newExpanded.add(id))
    expandedNotes.value = newExpanded
  }
}

// Watch for selectedNoteId changes to auto-expand
watch(() => props.selectedNoteId, (id) => {
  if (id) {
    expandToNote(id)
  }
})
</script>

<template>
  <aside
    :class="[
      'flex flex-col border-r border-dark-600 bg-dark-800 transition-all duration-300',
      collapsed ? 'w-16' : 'w-64'
    ]"
  >
    <!-- Header -->
    <div class="flex items-center justify-between p-3 border-b border-dark-700">
      <h2 v-if="!collapsed" class="font-semibold text-white">Notes</h2>
      <button
        @click="$emit('toggle-collapse')"
        class="rounded p-1 text-gray-400 hover:bg-dark-700 hover:text-white"
      >
        <ChevronLeftIcon v-if="!collapsed" class="h-5 w-5" />
        <ChevronRightIcon v-else class="h-5 w-5" />
      </button>
    </div>

    <!-- Collapsed state - icons only -->
    <template v-if="collapsed">
      <div class="flex flex-col items-center gap-2 p-2">
        <button
          @click="$emit('create-note')"
          class="rounded-lg bg-primary-600 p-2 text-white hover:bg-primary-700"
          title="Neue Notiz"
        >
          <PlusIcon class="h-5 w-5" />
        </button>
        <button
          @click="$emit('show-templates')"
          class="rounded p-2 text-gray-400 hover:bg-dark-700 hover:text-white"
          title="Vorlagen"
        >
          <Squares2X2Icon class="h-5 w-5" />
        </button>
      </div>
    </template>

    <!-- Expanded state -->
    <template v-else>
      <!-- Search -->
      <div class="p-3">
        <div class="relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Suchen..."
            class="w-full rounded-lg bg-dark-700 py-2 pl-9 pr-3 text-sm text-white placeholder-gray-500 focus:bg-dark-600 focus:outline-none focus:ring-1 focus:ring-primary-500"
          />
        </div>
      </div>

      <!-- Scrollable content -->
      <div class="flex-1 overflow-y-auto px-2">
        <!-- Quick actions -->
        <div class="mb-4 flex gap-2">
          <button
            @click="$emit('create-note')"
            class="flex-1 flex items-center justify-center gap-2 rounded-lg bg-primary-600 py-2 text-sm font-medium text-white hover:bg-primary-700"
          >
            <PlusIcon class="h-4 w-4" />
            Neue Notiz
          </button>
        </div>

        <!-- Favorites Section -->
        <div v-if="favoriteNotes.length > 0" class="mb-4">
          <button
            @click="toggleSection('favorites')"
            class="flex w-full items-center gap-2 rounded px-2 py-1 text-xs font-semibold uppercase text-gray-500 hover:bg-dark-700"
          >
            <StarIcon class="h-4 w-4 text-yellow-500" />
            <span>Favoriten</span>
            <ChevronRightIcon
              :class="[
                'ml-auto h-3 w-3 transition-transform',
                expandedSections.favorites ? 'rotate-90' : ''
              ]"
            />
          </button>
          <div v-show="expandedSections.favorites" class="mt-1 space-y-0.5">
            <button
              v-for="note in favoriteNotes"
              :key="note.id"
              @click="selectNote(note.id)"
              :class="[
                'w-full flex items-center gap-2 rounded px-2 py-1.5 text-sm',
                selectedNoteId === note.id
                  ? 'bg-primary-600/20 text-white'
                  : 'text-gray-300 hover:bg-dark-700'
              ]"
            >
              <span v-if="note.icon" class="text-base">{{ note.icon }}</span>
              <DocumentTextIcon v-else class="h-4 w-4 text-gray-500" />
              <span class="truncate">{{ note.title }}</span>
            </button>
          </div>
        </div>

        <!-- Pinned Section -->
        <div v-if="pinnedNotes.length > 0" class="mb-4">
          <button
            @click="toggleSection('pinned')"
            class="flex w-full items-center gap-2 rounded px-2 py-1 text-xs font-semibold uppercase text-gray-500 hover:bg-dark-700"
          >
            <svg class="h-4 w-4 text-primary-500" fill="currentColor" viewBox="0 0 24 24">
              <path d="M16 4v4h1V4c0-1.1-.9-2-2-2h-4c-1.1 0-2 .9-2 2v4h1V4h6z"/>
            </svg>
            <span>Angepinnt</span>
            <ChevronRightIcon
              :class="[
                'ml-auto h-3 w-3 transition-transform',
                expandedSections.pinned ? 'rotate-90' : ''
              ]"
            />
          </button>
          <div v-show="expandedSections.pinned" class="mt-1 space-y-0.5">
            <button
              v-for="note in pinnedNotes"
              :key="note.id"
              @click="selectNote(note.id)"
              :class="[
                'w-full flex items-center gap-2 rounded px-2 py-1.5 text-sm',
                selectedNoteId === note.id
                  ? 'bg-primary-600/20 text-white'
                  : 'text-gray-300 hover:bg-dark-700'
              ]"
            >
              <span v-if="note.icon" class="text-base">{{ note.icon }}</span>
              <DocumentTextIcon v-else class="h-4 w-4 text-gray-500" />
              <span class="truncate">{{ note.title }}</span>
            </button>
          </div>
        </div>

        <!-- Recent Section -->
        <div v-if="recentNotes.length > 0" class="mb-4">
          <button
            @click="toggleSection('recent')"
            class="flex w-full items-center gap-2 rounded px-2 py-1 text-xs font-semibold uppercase text-gray-500 hover:bg-dark-700"
          >
            <ClockIcon class="h-4 w-4 text-gray-400" />
            <span>Kürzlich</span>
            <ChevronRightIcon
              :class="[
                'ml-auto h-3 w-3 transition-transform',
                expandedSections.recent ? 'rotate-90' : ''
              ]"
            />
          </button>
          <div v-show="expandedSections.recent" class="mt-1 space-y-0.5">
            <button
              v-for="note in recentNotes.slice(0, 5)"
              :key="note.id"
              @click="selectNote(note.id)"
              :class="[
                'w-full flex items-center gap-2 rounded px-2 py-1.5 text-sm',
                selectedNoteId === note.id
                  ? 'bg-primary-600/20 text-white'
                  : 'text-gray-300 hover:bg-dark-700'
              ]"
            >
              <span v-if="note.icon" class="text-base">{{ note.icon }}</span>
              <DocumentTextIcon v-else class="h-4 w-4 text-gray-500" />
              <span class="truncate">{{ note.title }}</span>
            </button>
          </div>
        </div>

        <!-- Divider -->
        <hr class="my-3 border-dark-600" />

        <!-- All Notes Tree -->
        <div class="mb-4">
          <button
            @click="toggleSection('all')"
            class="flex w-full items-center gap-2 rounded px-2 py-1 text-xs font-semibold uppercase text-gray-500 hover:bg-dark-700"
          >
            <FolderIcon class="h-4 w-4" />
            <span>Alle Notizen</span>
            <ChevronRightIcon
              :class="[
                'ml-auto h-3 w-3 transition-transform',
                expandedSections.all ? 'rotate-90' : ''
              ]"
            />
          </button>

          <div v-show="expandedSections.all" class="mt-1">
            <!-- Loading state -->
            <div v-if="isLoadingTree" class="px-2 py-4 text-center text-gray-500 text-sm">
              Laden...
            </div>

            <!-- Empty state -->
            <div v-else-if="filteredTree.length === 0" class="px-2 py-4 text-center text-gray-500 text-sm">
              <template v-if="searchQuery">
                Keine Ergebnisse für "{{ searchQuery }}"
              </template>
              <template v-else>
                Noch keine Notizen
              </template>
            </div>

            <!-- Tree -->
            <div v-else class="space-y-0.5">
              <NoteTreeItem
                v-for="note in filteredTree"
                :key="note.id"
                :note="note"
                :level="0"
                :selected-note-id="selectedNoteId"
                @select="selectNote"
                @create-child="createNote"
                @move="handleMove"
                @reorder="handleReorder"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="border-t border-dark-700 p-3">
        <button
          @click="showTrash"
          class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-sm text-gray-400 hover:bg-dark-700 hover:text-white"
        >
          <TrashIcon class="h-4 w-4" />
          <span>Papierkorb</span>
        </button>
      </div>
    </template>
  </aside>
</template>
