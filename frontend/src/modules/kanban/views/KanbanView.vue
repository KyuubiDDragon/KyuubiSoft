<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useProjectStore } from '@/stores/project'
import draggable from 'vuedraggable'
import {
  PlusIcon,
  TrashIcon,
  PencilIcon,
  XMarkIcon,
  ViewColumnsIcon,
  CalendarIcon,
  TagIcon,
  FlagIcon,
  UserCircleIcon,
} from '@heroicons/vue/24/outline'
import { ViewColumnsIcon as ViewColumnsIconSolid } from '@heroicons/vue/24/solid'

const route = useRoute()
const uiStore = useUiStore()
const projectStore = useProjectStore()

// Watch for project changes
watch(() => projectStore.selectedProjectId, () => {
  fetchBoards()
  selectedBoard.value = null
})

// State
const boards = ref([])
const selectedBoard = ref(null)
const loading = ref(true)
const showBoardModal = ref(false)
const showCardModal = ref(false)
const showColumnModal = ref(false)
const editingBoard = ref(null)
const editingCard = ref(null)
const editingColumn = ref(null)
const targetColumnId = ref(null)
const boardUsers = ref([])

// Board form
const boardForm = ref({
  title: '',
  description: '',
  color: '#6366f1',
})

// Column form
const columnForm = ref({
  title: '',
  color: '#3B82F6',
  wip_limit: null,
})

// Card form
const cardForm = ref({
  title: '',
  description: '',
  priority: 'medium',
  due_date: null,
  labels: [],
  color: null,
  assigned_to: null,
})

// Colors for boards
const boardColors = [
  '#6366f1', '#8B5CF6', '#EC4899', '#EF4444', '#F97316',
  '#EAB308', '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
]

// Priority options
const priorities = [
  { value: 'low', label: 'Niedrig', color: 'bg-gray-500' },
  { value: 'medium', label: 'Mittel', color: 'bg-blue-500' },
  { value: 'high', label: 'Hoch', color: 'bg-orange-500' },
  { value: 'urgent', label: 'Dringend', color: 'bg-red-500' },
]

// Label colors
const labelColors = [
  { name: 'red', bg: 'bg-red-500', text: 'text-red-500' },
  { name: 'orange', bg: 'bg-orange-500', text: 'text-orange-500' },
  { name: 'yellow', bg: 'bg-yellow-500', text: 'text-yellow-500' },
  { name: 'green', bg: 'bg-green-500', text: 'text-green-500' },
  { name: 'blue', bg: 'bg-blue-500', text: 'text-blue-500' },
  { name: 'purple', bg: 'bg-purple-500', text: 'text-purple-500' },
  { name: 'pink', bg: 'bg-pink-500', text: 'text-pink-500' },
]

// Fetch boards
async function fetchBoards() {
  loading.value = true
  try {
    const params = projectStore.selectedProjectId
      ? { project_id: projectStore.selectedProjectId }
      : {}
    const response = await api.get('/api/v1/kanban/boards', { params })
    boards.value = response.data.data.items || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Boards')
  } finally {
    loading.value = false
  }
}

// Fetch single board with columns and cards
async function fetchBoard(boardId) {
  try {
    const response = await api.get(`/api/v1/kanban/boards/${boardId}`)
    selectedBoard.value = response.data.data
    // Also fetch board users
    await fetchBoardUsers(boardId)
  } catch (error) {
    uiStore.showError('Fehler beim Laden des Boards')
    selectedBoard.value = null
  }
}

// Fetch board users for assignment
async function fetchBoardUsers(boardId) {
  try {
    const response = await api.get(`/api/v1/kanban/boards/${boardId}/users`)
    boardUsers.value = response.data.data.users || []
  } catch (error) {
    boardUsers.value = []
  }
}

// Open board modal
function openBoardModal(board = null) {
  editingBoard.value = board
  if (board) {
    boardForm.value = {
      title: board.title,
      description: board.description || '',
      color: board.color || '#6366f1',
    }
  } else {
    boardForm.value = { title: '', description: '', color: '#6366f1' }
  }
  showBoardModal.value = true
}

// Save board
async function saveBoard() {
  if (!boardForm.value.title.trim()) {
    uiStore.showError('Titel ist erforderlich')
    return
  }

  try {
    if (editingBoard.value) {
      await api.put(`/api/v1/kanban/boards/${editingBoard.value.id}`, boardForm.value)
      uiStore.showSuccess('Board aktualisiert')
      if (selectedBoard.value?.id === editingBoard.value.id) {
        await fetchBoard(selectedBoard.value.id)
      }
    } else {
      const response = await api.post('/api/v1/kanban/boards', boardForm.value)
      const newBoard = response.data.data

      // Link to selected project if one is active
      if (projectStore.selectedProjectId) {
        await projectStore.linkToSelectedProject('kanban_board', newBoard.id)
      }

      uiStore.showSuccess('Board erstellt')
      // Select the new board
      await fetchBoard(newBoard.id)
    }
    await fetchBoards()
    showBoardModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Speichern')
  }
}

// Delete board
async function deleteBoard(board) {
  if (!confirm(`Board "${board.title}" wirklich löschen?`)) return

  try {
    await api.delete(`/api/v1/kanban/boards/${board.id}`)
    uiStore.showSuccess('Board gelöscht')
    if (selectedBoard.value?.id === board.id) {
      selectedBoard.value = null
    }
    await fetchBoards()
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Select board
function selectBoard(board) {
  fetchBoard(board.id)
}

// Go back to boards list
function backToBoards() {
  selectedBoard.value = null
}

// Open column modal
function openColumnModal(column = null) {
  editingColumn.value = column
  if (column) {
    columnForm.value = {
      title: column.title,
      color: column.color || '#3B82F6',
      wip_limit: column.wip_limit,
    }
  } else {
    columnForm.value = { title: '', color: '#3B82F6', wip_limit: null }
  }
  showColumnModal.value = true
}

// Save column
async function saveColumn() {
  if (!columnForm.value.title.trim()) {
    uiStore.showError('Titel ist erforderlich')
    return
  }

  try {
    if (editingColumn.value) {
      await api.put(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/columns/${editingColumn.value.id}`,
        columnForm.value
      )
      uiStore.showSuccess('Spalte aktualisiert')
    } else {
      await api.post(`/api/v1/kanban/boards/${selectedBoard.value.id}/columns`, columnForm.value)
      uiStore.showSuccess('Spalte erstellt')
    }
    await fetchBoard(selectedBoard.value.id)
    showColumnModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Speichern')
  }
}

// Delete column
async function deleteColumn(column) {
  if (!confirm(`Spalte "${column.title}" wirklich löschen? Alle Karten werden ebenfalls gelöscht.`)) return

  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/columns/${column.id}`)
    uiStore.showSuccess('Spalte gelöscht')
    await fetchBoard(selectedBoard.value.id)
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Open card modal
function openCardModal(columnId, card = null) {
  targetColumnId.value = columnId
  editingCard.value = card
  if (card) {
    cardForm.value = {
      title: card.title,
      description: card.description || '',
      priority: card.priority || 'medium',
      due_date: card.due_date,
      labels: card.labels || [],
      color: card.color,
      assigned_to: card.assigned_to || null,
    }
  } else {
    cardForm.value = {
      title: '',
      description: '',
      priority: 'medium',
      due_date: null,
      labels: [],
      color: null,
      assigned_to: null,
    }
  }
  showCardModal.value = true
}

// Save card
async function saveCard() {
  if (!cardForm.value.title.trim()) {
    uiStore.showError('Titel ist erforderlich')
    return
  }

  try {
    if (editingCard.value) {
      await api.put(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}`,
        cardForm.value
      )
      uiStore.showSuccess('Karte aktualisiert')
    } else {
      await api.post(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/columns/${targetColumnId.value}/cards`,
        cardForm.value
      )
      uiStore.showSuccess('Karte erstellt')
    }
    await fetchBoard(selectedBoard.value.id)
    showCardModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Speichern')
  }
}

// Delete card
async function deleteCard(card) {
  if (!confirm(`Karte "${card.title}" wirklich löschen?`)) return

  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${card.id}`)
    uiStore.showSuccess('Karte gelöscht')
    await fetchBoard(selectedBoard.value.id)
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Handle card drag end
async function onCardDragEnd(columnId, evt) {
  if (!evt.added && !evt.moved) return

  const card = evt.added?.element || evt.moved?.element
  const newIndex = evt.added?.newIndex ?? evt.moved?.newIndex

  try {
    await api.put(`/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${card.id}/move`, {
      column_id: columnId,
      position: newIndex,
    })
  } catch (error) {
    uiStore.showError('Fehler beim Verschieben')
    await fetchBoard(selectedBoard.value.id)
  }
}

// Handle column drag end
async function onColumnDragEnd() {
  const columnIds = selectedBoard.value.columns.map(col => col.id)
  try {
    await api.put(`/api/v1/kanban/boards/${selectedBoard.value.id}/columns/reorder`, {
      columns: columnIds,
    })
  } catch (error) {
    uiStore.showError('Fehler beim Sortieren')
    await fetchBoard(selectedBoard.value.id)
  }
}

// Toggle label on card form
function toggleLabel(color) {
  const idx = cardForm.value.labels.indexOf(color)
  if (idx >= 0) {
    cardForm.value.labels.splice(idx, 1)
  } else {
    cardForm.value.labels.push(color)
  }
}

// Get priority info
function getPriorityInfo(priority) {
  return priorities.find(p => p.value === priority) || priorities[1]
}

// Get label color class
function getLabelColorClass(color) {
  const label = labelColors.find(l => l.name === color)
  return label?.bg || 'bg-gray-500'
}

// Format date
function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
}

// Check if date is overdue
function isOverdue(dateStr) {
  if (!dateStr) return false
  return new Date(dateStr) < new Date()
}

onMounted(async () => {
  await fetchBoards()

  // Check for ?open=id query parameter to auto-open a board
  const openId = route.query.open
  if (openId) {
    await fetchBoard(openId)
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button
          v-if="selectedBoard"
          @click="backToBoards"
          class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div>
          <h1 class="text-2xl font-bold text-white">
            {{ selectedBoard ? selectedBoard.title : 'Kanban Boards' }}
          </h1>
          <p v-if="selectedBoard?.description" class="text-gray-400 text-sm mt-1">
            {{ selectedBoard.description }}
          </p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <button
          v-if="selectedBoard"
          @click="openColumnModal()"
          class="px-4 py-2 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors flex items-center gap-2"
        >
          <PlusIcon class="w-5 h-5" />
          <span>Spalte</span>
        </button>
        <button
          v-if="selectedBoard"
          @click="openBoardModal(selectedBoard)"
          class="px-4 py-2 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors flex items-center gap-2"
        >
          <PencilIcon class="w-5 h-5" />
          <span>Bearbeiten</span>
        </button>
        <button
          v-if="!selectedBoard"
          @click="openBoardModal()"
          class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors flex items-center gap-2"
        >
          <PlusIcon class="w-5 h-5" />
          <span>Neues Board</span>
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Boards Grid (when no board selected) -->
    <div v-else-if="!selectedBoard" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <div
        v-for="board in boards"
        :key="board.id"
        @click="selectBoard(board)"
        class="bg-dark-800 border border-dark-700 rounded-xl p-4 cursor-pointer hover:border-dark-600 transition-all group"
      >
        <div class="flex items-start justify-between mb-3">
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center"
            :style="{ backgroundColor: board.color || '#6366f1' }"
          >
            <ViewColumnsIconSolid class="w-6 h-6 text-white" />
          </div>
          <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <button
              @click.stop="openBoardModal(board)"
              class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
            >
              <PencilIcon class="w-4 h-4" />
            </button>
            <button
              @click.stop="deleteBoard(board)"
              class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
        <h3 class="text-white font-semibold mb-1">{{ board.title }}</h3>
        <p v-if="board.description" class="text-gray-400 text-sm line-clamp-2 mb-3">
          {{ board.description }}
        </p>
        <div class="flex items-center gap-4 text-sm text-gray-500">
          <span>{{ board.column_count || 0 }} Spalten</span>
          <span>{{ board.card_count || 0 }} Karten</span>
        </div>
      </div>

      <!-- Empty state -->
      <div
        v-if="boards.length === 0"
        @click="openBoardModal()"
        class="bg-dark-800 border-2 border-dashed border-dark-600 rounded-xl p-8 cursor-pointer hover:border-dark-500 transition-colors flex flex-col items-center justify-center text-center col-span-full"
      >
        <ViewColumnsIcon class="w-12 h-12 text-gray-500 mb-3" />
        <p class="text-gray-400">Kein Board vorhanden</p>
        <p class="text-primary-500 mt-1">Klicken um ein Board zu erstellen</p>
      </div>
    </div>

    <!-- Board View (columns with cards) -->
    <div v-else class="flex gap-4 overflow-x-auto pb-4" style="min-height: calc(100vh - 200px)">
      <draggable
        v-model="selectedBoard.columns"
        group="columns"
        item-key="id"
        handle=".column-handle"
        class="flex gap-4"
        @end="onColumnDragEnd"
      >
        <template #item="{ element: column }">
          <div class="flex-shrink-0 w-80 bg-dark-800 rounded-xl border border-dark-700">
            <!-- Column Header -->
            <div class="p-3 border-b border-dark-700 column-handle cursor-move">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <div
                    class="w-3 h-3 rounded-full"
                    :style="{ backgroundColor: column.color || '#3B82F6' }"
                  ></div>
                  <h3 class="font-semibold text-white">{{ column.title }}</h3>
                  <span class="text-xs text-gray-500 bg-dark-700 px-2 py-0.5 rounded-full">
                    {{ column.cards?.length || 0 }}
                    <template v-if="column.wip_limit">/ {{ column.wip_limit }}</template>
                  </span>
                </div>
                <div class="flex items-center gap-1">
                  <button
                    @click="openCardModal(column.id)"
                    class="p-1 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
                  >
                    <PlusIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="openColumnModal(column)"
                    class="p-1 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
                  >
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="deleteColumn(column)"
                    class="p-1 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Cards -->
            <div class="p-2 space-y-2 max-h-[calc(100vh-300px)] overflow-y-auto">
              <draggable
                :list="column.cards"
                group="cards"
                item-key="id"
                class="space-y-2 min-h-[40px]"
                @change="(evt) => onCardDragEnd(column.id, evt)"
              >
                <template #item="{ element: card }">
                  <div
                    @click="openCardModal(column.id, card)"
                    class="bg-dark-700 rounded-lg p-3 cursor-pointer hover:bg-dark-600 transition-colors group"
                    :class="{ 'border-l-4': card.color }"
                    :style="card.color ? { borderLeftColor: card.color } : {}"
                  >
                    <!-- Labels -->
                    <div v-if="card.labels?.length" class="flex flex-wrap gap-1 mb-2">
                      <span
                        v-for="label in card.labels"
                        :key="label"
                        class="w-8 h-1.5 rounded-full"
                        :class="getLabelColorClass(label)"
                      ></span>
                    </div>

                    <!-- Title -->
                    <p class="text-white text-sm font-medium mb-2">{{ card.title }}</p>

                    <!-- Description preview -->
                    <p v-if="card.description" class="text-gray-400 text-xs line-clamp-2 mb-2">
                      {{ card.description }}
                    </p>

                    <!-- Footer -->
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <!-- Priority -->
                        <span
                          class="w-2 h-2 rounded-full"
                          :class="getPriorityInfo(card.priority).color"
                          :title="getPriorityInfo(card.priority).label"
                        ></span>
                        <!-- Due date -->
                        <span
                          v-if="card.due_date"
                          class="text-xs flex items-center gap-1"
                          :class="isOverdue(card.due_date) ? 'text-red-400' : 'text-gray-500'"
                        >
                          <CalendarIcon class="w-3 h-3" />
                          {{ formatDate(card.due_date) }}
                        </span>
                        <!-- Assignee -->
                        <span
                          v-if="card.assignee"
                          class="text-xs flex items-center gap-1 text-gray-400"
                          :title="card.assignee.username"
                        >
                          <div class="w-5 h-5 rounded-full bg-primary-600 flex items-center justify-center text-[10px] text-white font-medium">
                            {{ card.assignee.username?.[0]?.toUpperCase() || '?' }}
                          </div>
                        </span>
                      </div>

                      <!-- Actions -->
                      <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                          @click.stop="deleteCard(card)"
                          class="p-1 text-gray-400 hover:text-red-400 rounded"
                        >
                          <TrashIcon class="w-3 h-3" />
                        </button>
                      </div>
                    </div>
                  </div>
                </template>
              </draggable>

              <!-- Add card button -->
              <button
                @click="openCardModal(column.id)"
                class="w-full p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors flex items-center justify-center gap-2 text-sm"
              >
                <PlusIcon class="w-4 h-4" />
                <span>Karte hinzufügen</span>
              </button>
            </div>
          </div>
        </template>
      </draggable>

      <!-- Add column button -->
      <div
        @click="openColumnModal()"
        class="flex-shrink-0 w-80 bg-dark-800/50 border-2 border-dashed border-dark-600 rounded-xl p-4 cursor-pointer hover:border-dark-500 transition-colors flex items-center justify-center"
      >
        <div class="text-center">
          <PlusIcon class="w-8 h-8 text-gray-500 mx-auto mb-2" />
          <p class="text-gray-400">Spalte hinzufügen</p>
        </div>
      </div>
    </div>

    <!-- Board Modal -->
    <Teleport to="body">
      <div
        v-if="showBoardModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="showBoardModal = false"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-md">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">
              {{ editingBoard ? 'Board bearbeiten' : 'Neues Board' }}
            </h2>
            <button
              @click="showBoardModal = false"
              class="p-1 text-gray-400 hover:text-white rounded"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Titel *</label>
              <input
                v-model="boardForm.title"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Board Name"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <textarea
                v-model="boardForm.description"
                rows="3"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 resize-none"
                placeholder="Optionale Beschreibung..."
              ></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Farbe</label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="color in boardColors"
                  :key="color"
                  @click="boardForm.color = color"
                  class="w-8 h-8 rounded-lg transition-transform hover:scale-110"
                  :class="{ 'ring-2 ring-white ring-offset-2 ring-offset-dark-800': boardForm.color === color }"
                  :style="{ backgroundColor: color }"
                ></button>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-dark-700">
            <button
              @click="showBoardModal = false"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="saveBoard"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
            >
              {{ editingBoard ? 'Speichern' : 'Erstellen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Column Modal -->
    <Teleport to="body">
      <div
        v-if="showColumnModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="showColumnModal = false"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-md">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">
              {{ editingColumn ? 'Spalte bearbeiten' : 'Neue Spalte' }}
            </h2>
            <button
              @click="showColumnModal = false"
              class="p-1 text-gray-400 hover:text-white rounded"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Titel *</label>
              <input
                v-model="columnForm.title"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Spaltenname"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Farbe</label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="color in boardColors"
                  :key="color"
                  @click="columnForm.color = color"
                  class="w-8 h-8 rounded-lg transition-transform hover:scale-110"
                  :class="{ 'ring-2 ring-white ring-offset-2 ring-offset-dark-800': columnForm.color === color }"
                  :style="{ backgroundColor: color }"
                ></button>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">WIP Limit (optional)</label>
              <input
                v-model.number="columnForm.wip_limit"
                type="number"
                min="0"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Max. Anzahl Karten"
              />
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-dark-700">
            <button
              @click="showColumnModal = false"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="saveColumn"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
            >
              {{ editingColumn ? 'Speichern' : 'Erstellen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Card Modal -->
    <Teleport to="body">
      <div
        v-if="showCardModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="showCardModal = false"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">
              {{ editingCard ? 'Karte bearbeiten' : 'Neue Karte' }}
            </h2>
            <button
              @click="showCardModal = false"
              class="p-1 text-gray-400 hover:text-white rounded"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Titel *</label>
              <input
                v-model="cardForm.title"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Kartenname"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <textarea
                v-model="cardForm.description"
                rows="4"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 resize-none"
                placeholder="Details zur Karte..."
              ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">
                  <FlagIcon class="w-4 h-4 inline mr-1" />
                  Priorität
                </label>
                <select
                  v-model="cardForm.priority"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                >
                  <option v-for="p in priorities" :key="p.value" :value="p.value">
                    {{ p.label }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">
                  <CalendarIcon class="w-4 h-4 inline mr-1" />
                  Fälligkeitsdatum
                </label>
                <input
                  v-model="cardForm.due_date"
                  type="date"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">
                <UserCircleIcon class="w-4 h-4 inline mr-1" />
                Zugewiesen an
              </label>
              <select
                v-model="cardForm.assigned_to"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
              >
                <option :value="null">Niemand</option>
                <option v-for="user in boardUsers" :key="user.id" :value="user.id">
                  {{ user.username }} ({{ user.email }})
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">
                <TagIcon class="w-4 h-4 inline mr-1" />
                Labels
              </label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="label in labelColors"
                  :key="label.name"
                  @click="toggleLabel(label.name)"
                  class="w-10 h-6 rounded transition-all"
                  :class="[
                    label.bg,
                    cardForm.labels.includes(label.name) ? 'ring-2 ring-white' : 'opacity-50 hover:opacity-100'
                  ]"
                ></button>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Kartenfarbe (Akzent)</label>
              <div class="flex flex-wrap gap-2">
                <button
                  @click="cardForm.color = null"
                  class="w-8 h-8 rounded-lg bg-dark-600 flex items-center justify-center transition-transform hover:scale-110"
                  :class="{ 'ring-2 ring-white ring-offset-2 ring-offset-dark-800': !cardForm.color }"
                >
                  <XMarkIcon class="w-4 h-4 text-gray-400" />
                </button>
                <button
                  v-for="color in boardColors"
                  :key="color"
                  @click="cardForm.color = color"
                  class="w-8 h-8 rounded-lg transition-transform hover:scale-110"
                  :class="{ 'ring-2 ring-white ring-offset-2 ring-offset-dark-800': cardForm.color === color }"
                  :style="{ backgroundColor: color }"
                ></button>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-dark-700">
            <button
              @click="showCardModal = false"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="saveCard"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
            >
              {{ editingCard ? 'Speichern' : 'Erstellen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
