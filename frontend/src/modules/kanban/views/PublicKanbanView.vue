<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import axios from 'axios'
import {
  LockClosedIcon,
  ViewColumnsIcon,
  UserIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  XMarkIcon,
  CheckIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const token = computed(() => route.params.token)

// State
const state = ref('loading') // loading | auth | view | error
const board = ref(null)
const canEdit = ref(false)
const username = ref('')
const password = ref('')
const authError = ref('')
const errorMessage = ref('')
const isSubmitting = ref(false)

// Stored credentials for edit API calls
const storedCredentials = ref(null)

// Card creation
const addingToColumn = ref(null)
const newCardTitle = ref('')
const newCardDescription = ref('')
const newCardPriority = ref('medium')
const savingCard = ref(false)

// Card detail (read-only view)
const viewingCard = ref(null)
const lightboxImage = ref(null)

// Card editing
const editingCard = ref(null)
const editForm = ref({ title: '', description: '', priority: 'medium', due_date: '' })
const savingEdit = ref(false)

// Drag & drop
const dragCard = ref(null)
const dragOverColumn = ref(null)

// API base
const apiBase = window.location.origin + '/api/v1'

onMounted(async () => {
  await loadBoard()
})

function getAuthPayload() {
  if (!storedCredentials.value) return {}
  return {
    username: storedCredentials.value.username,
    password: storedCredentials.value.password,
  }
}

async function loadBoard(user, pw) {
  state.value = 'loading'
  authError.value = ''
  try {
    const config = {}
    if (user && pw) {
      config.method = 'post'
      config.url = `${apiBase}/kanban/public/${token.value}`
      config.data = { username: user, password: pw }
    } else {
      config.method = 'get'
      config.url = `${apiBase}/kanban/public/${token.value}`
    }
    const res = await axios(config)
    const resData = res.data.data

    if (resData.requires_auth) {
      state.value = 'auth'
      return
    }

    // Store credentials for later edit calls
    if (user && pw) {
      storedCredentials.value = { username: user, password: pw }
    }

    board.value = resData
    canEdit.value = !!resData.can_edit
    state.value = 'view'
  } catch (e) {
    const status = e.response?.status
    const msg = e.response?.data?.message || ''
    if (status === 401) {
      if (user) {
        authError.value = t('kanbanModule.kanbanmoduleungueltigerbenutzernameoderpasswort')
      }
      state.value = 'auth'
    } else if (status === 403) {
      state.value = 'error'
      errorMessage.value = msg || t('kanban.linkExpired')
    } else if (status === 404) {
      state.value = 'error'
      errorMessage.value = t('kanban.boardNotFound')
    } else {
      state.value = 'error'
      errorMessage.value = msg || t('errors.generic')
    }
  }
}

async function submitAuth() {
  if (!username.value || !password.value) return
  isSubmitting.value = true
  await loadBoard(username.value, password.value)
  isSubmitting.value = false
}

// ===== Card Creation =====
function startAddCard(columnId) {
  addingToColumn.value = columnId
  newCardTitle.value = ''
  newCardDescription.value = ''
  newCardPriority.value = 'medium'
}

function cancelAddCard() {
  addingToColumn.value = null
}

async function saveNewCard(columnId) {
  if (!newCardTitle.value.trim()) return
  savingCard.value = true
  try {
    const res = await axios.post(
      `${apiBase}/kanban/public/${token.value}/columns/${columnId}/cards`,
      {
        ...getAuthPayload(),
        title: newCardTitle.value.trim(),
        description: newCardDescription.value.trim() || null,
        priority: newCardPriority.value,
      },
    )
    const newCard = res.data.data
    newCard.tags = newCard.tags || []
    const col = board.value.columns.find((c) => c.id === columnId)
    if (col) {
      if (!col.cards) col.cards = []
      col.cards.push(newCard)
    }
    addingToColumn.value = null
  } catch {
    // silently fail
  } finally {
    savingCard.value = false
  }
}

// ===== Card Editing =====
function startEditCard(card) {
  editingCard.value = card
  editForm.value = {
    title: card.title,
    description: card.description || '',
    priority: card.priority || 'medium',
    due_date: card.due_date || '',
  }
}

function cancelEditCard() {
  editingCard.value = null
}

async function saveEditCard() {
  if (!editForm.value.title.trim()) return
  savingEdit.value = true
  try {
    const res = await axios.put(
      `${apiBase}/kanban/public/${token.value}/cards/${editingCard.value.id}`,
      {
        ...getAuthPayload(),
        title: editForm.value.title.trim(),
        description: editForm.value.description.trim() || null,
        priority: editForm.value.priority,
        due_date: editForm.value.due_date || null,
      },
    )
    const updated = res.data.data
    for (const col of board.value.columns) {
      const idx = col.cards?.findIndex((c) => c.id === updated.id)
      if (idx !== undefined && idx >= 0) {
        const oldTags = col.cards[idx].tags
        Object.assign(col.cards[idx], updated)
        col.cards[idx].tags = oldTags || []
        break
      }
    }
    editingCard.value = null
  } catch {
    // silently fail
  } finally {
    savingEdit.value = false
  }
}

async function deleteCard(card) {
  if (!window.confirm(t('kanbanModule.karteWirklichLoeschen'))) return
  try {
    await axios.delete(`${apiBase}/kanban/public/${token.value}/cards/${card.id}`, {
      data: getAuthPayload(),
    })
    for (const col of board.value.columns) {
      const idx = col.cards?.findIndex((c) => c.id === card.id)
      if (idx !== undefined && idx >= 0) {
        col.cards.splice(idx, 1)
        break
      }
    }
    if (editingCard.value?.id === card.id) {
      editingCard.value = null
    }
  } catch {
    // silently fail
  }
}

// ===== Drag & Drop =====
function onDragStart(event, card, columnId) {
  dragCard.value = { card, fromColumnId: columnId }
  event.dataTransfer.effectAllowed = 'move'
}

function onDragOver(event, columnId) {
  event.preventDefault()
  dragOverColumn.value = columnId
}

function onDragLeave() {
  dragOverColumn.value = null
}

async function onDrop(event, targetColumnId) {
  event.preventDefault()
  dragOverColumn.value = null

  if (!dragCard.value) return
  const { card, fromColumnId } = dragCard.value
  dragCard.value = null

  if (fromColumnId === targetColumnId) return

  const fromCol = board.value.columns.find((c) => c.id === fromColumnId)
  const toCol = board.value.columns.find((c) => c.id === targetColumnId)
  if (!fromCol || !toCol) return

  const idx = fromCol.cards.findIndex((c) => c.id === card.id)
  if (idx < 0) return
  fromCol.cards.splice(idx, 1)
  card.column_id = targetColumnId
  toCol.cards.push(card)

  try {
    await axios.put(`${apiBase}/kanban/public/${token.value}/cards/${card.id}/move`, {
      ...getAuthPayload(),
      column_id: targetColumnId,
      position: toCol.cards.length - 1,
    })
  } catch {
    const revertIdx = toCol.cards.findIndex((c) => c.id === card.id)
    if (revertIdx >= 0) toCol.cards.splice(revertIdx, 1)
    card.column_id = fromColumnId
    fromCol.cards.splice(idx, 0, card)
  }
}

function onDragEnd() {
  dragCard.value = null
  dragOverColumn.value = null
}

// ===== Card Detail =====
function openCard(card) {
  viewingCard.value = card
}

function closeCard() {
  viewingCard.value = null
}

function openLightbox(url) {
  lightboxImage.value = url
}

function closeLightbox() {
  lightboxImage.value = null
}

// ===== Helpers =====
function getAttachmentUrl(filename) {
  return `/api/v1/kanban/attachments/${filename}?share_token=${token.value}`
}

function getPriorityColor(priority) {
  const colors = {
    low: 'bg-gray-500',
    medium: 'bg-blue-500',
    high: 'bg-orange-500',
    urgent: 'bg-red-500',
  }
  return colors[priority] || 'bg-gray-500'
}

function getPriorityLabel(priority) {
  const labels = {
    low: 'Niedrig',
    medium: 'Mittel',
    high: 'Hoch',
    urgent: 'Dringend',
  }
  return labels[priority] || priority
}

function getLabelColorClass(label) {
  const colors = {
    red: 'bg-red-500',
    orange: 'bg-orange-500',
    yellow: 'bg-yellow-500',
    green: 'bg-green-500',
    blue: 'bg-blue-500',
    purple: 'bg-purple-500',
    pink: 'bg-pink-500',
  }
  return colors[label] || 'bg-gray-500'
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}
</script>

<template>
  <div class="min-h-screen bg-dark-900">
    <!-- Loading -->
    <div v-if="state === 'loading'" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <div
          class="w-12 h-12 mx-auto border-4 border-primary-500 border-t-transparent rounded-full animate-spin"
        ></div>
        <p class="mt-4 text-gray-400">{{ $t('kanbanModule.laedt') }}</p>
      </div>
    </div>

    <!-- Auth Form -->
    <div v-else-if="state === 'auth'" class="flex items-center justify-center min-h-screen px-4">
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-8 w-full max-w-sm">
        <div class="text-center mb-6">
          <div
            class="w-16 h-16 mx-auto bg-primary-600/20 rounded-full flex items-center justify-center mb-4"
          >
            <LockClosedIcon class="w-8 h-8 text-primary-400" />
          </div>
          <h1 class="text-xl font-bold text-white">Kanban Board</h1>
          <p class="text-gray-400 text-sm mt-1">{{ $t('kanbanModule.bitteAnmeldenUmDasBoardZuSehen') }}</p>
        </div>

        <form @submit.prevent="submitAuth" class="space-y-4">
          <div>
            <label class="block text-sm text-gray-400 mb-1">{{ $t('passwords.username') }}</label>
            <div class="relative">
              <UserIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
              <input
                v-model="username"
                type="text"
                :placeholder="$t('kanban.username')"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg pl-10 pr-3 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                autocomplete="username"
              />
            </div>
          </div>

          <div>
            <label class="block text-sm text-gray-400 mb-1">{{ $t('auth.password') }}</label>
            <div class="relative">
              <LockClosedIcon
                class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500"
              />
              <input
                v-model="password"
                type="password"
                :placeholder="$t('auth.password')"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg pl-10 pr-3 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                autocomplete="current-password"
              />
            </div>
          </div>

          <p v-if="authError" class="text-red-400 text-sm">{{ authError }}</p>

          <button
            type="submit"
            :disabled="isSubmitting || !username || !password"
            class="w-full py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed font-medium"
          >
            {{ isSubmitting ? $t('contractsModule.pruefe') : 'Anmelden' }}
          </button>
        </form>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="state === 'error'" class="flex items-center justify-center min-h-screen px-4">
      <div class="text-center max-w-md">
        <div
          class="w-16 h-16 mx-auto bg-red-500/20 rounded-full flex items-center justify-center mb-4"
        >
          <svg class="w-8 h-8 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"
            />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-white mb-2">{{ $t('kanbanModule.kanbanmodulezugriffnichtmoeglich') }}</h1>
        <p class="text-gray-400">{{ errorMessage }}</p>
      </div>
    </div>

    <!-- Board View -->
    <div v-else-if="state === 'view' && board" class="p-4 sm:p-6">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex items-center gap-3 mb-2">
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center"
            :style="{ backgroundColor: board.color || '#6366f1' }"
          >
            <ViewColumnsIcon class="w-6 h-6 text-white" />
          </div>
          <div>
            <h1 class="text-2xl font-bold text-white">{{ board.title }}</h1>
            <p v-if="board.description" class="text-gray-400 text-sm">{{ board.description }}</p>
          </div>
          <div v-if="canEdit" class="ml-auto">
            <span
              class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded-lg text-xs font-medium"
            >{{ $t('kanban.editingActive') }}</span>
          </div>
          <div v-else class="ml-auto">
            <span
              class="px-2 py-1 bg-gray-500/20 text-gray-400 rounded-lg text-xs font-medium"
            >{{ $t('kanbanModule.nurLesen') }}</span>
          </div>
        </div>
      </div>

      <!-- Columns -->
      <div class="flex gap-4 overflow-x-auto pb-4" style="min-height: calc(100vh - 150px)">
        <div
          v-for="column in board.columns"
          :key="column.id"
          class="flex-shrink-0 w-80 bg-white/[0.04] rounded-xl border transition-colors"
          :class="
            dragOverColumn === column.id
              ? 'border-primary-500/50 bg-primary-500/5'
              : 'border-white/[0.06]'
          "
          @dragover="canEdit ? onDragOver($event, column.id) : null"
          @dragleave="onDragLeave"
          @drop="canEdit ? onDrop($event, column.id) : null"
        >
          <!-- Column Header -->
          <div class="p-3 border-b border-white/[0.06]">
            <div class="flex items-center gap-2">
              <div
                class="w-3 h-3 rounded-full"
                :style="{ backgroundColor: column.color || '#3B82F6' }"
              ></div>
              <h3 class="font-semibold text-white">{{ column.title }}</h3>
              <span class="text-xs text-gray-500 bg-white/[0.04] px-2 py-0.5 rounded-full">
                {{ column.cards?.length || 0 }}
              </span>
              <button
                v-if="canEdit"
                @click="startAddCard(column.id)"
                class="ml-auto p-1 text-gray-500 hover:text-primary-400 transition-colors rounded"
                :title="$t('kanbanModule.karteHinzufuegen')"
              >
                <PlusIcon class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Cards -->
          <div class="p-2 space-y-2 max-h-[calc(100vh-250px)] overflow-y-auto">
            <!-- Add card form -->
            <div
              v-if="addingToColumn === column.id"
              class="bg-dark-700 rounded-lg p-3 space-y-2 border border-primary-500/30"
            >
              <input
                v-model="newCardTitle"
                type="text"
                :placeholder="$t('kanban.cardTitlePlaceholder')"
                class="w-full bg-dark-600 border border-dark-500 rounded px-2 py-1.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                @keydown.enter="saveNewCard(column.id)"
                @keydown.escape="cancelAddCard"
                autofocus
              />
              <textarea
                v-model="newCardDescription"
                :placeholder="$t('scripts.scriptsbeschreibungoptional')"
                rows="2"
                class="w-full bg-dark-600 border border-dark-500 rounded px-2 py-1.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 resize-none"
              ></textarea>
              <select
                v-model="newCardPriority"
                class="w-full bg-dark-600 border border-dark-500 rounded px-2 py-1.5 text-sm text-white focus:outline-none focus:border-primary-500"
              >
                <option value="low">{{ $t('kanban.low') }}</option>
                <option value="medium">{{ $t('kanbanModule.mittel') }}</option>
                <option value="high">{{ $t('kanban.high') }}</option>
                <option value="urgent">{{ $t('kanban.urgent') }}</option>
              </select>
              <div class="flex gap-2">
                <button
                  @click="saveNewCard(column.id)"
                  :disabled="!newCardTitle.trim() || savingCard"
                  class="flex-1 py-1.5 bg-primary-600 text-white rounded text-sm hover:bg-primary-500 disabled:opacity-50"
                >
                  {{ savingCard ? 'Speichert...' : $t('common.add') }}
                </button>
                <button
                  @click="cancelAddCard"
                  class="px-3 py-1.5 bg-dark-600 text-gray-400 rounded text-sm hover:text-white"
                >
                  <XMarkIcon class="w-4 h-4" />
                </button>
              </div>
            </div>

            <div
              v-for="card in column.cards"
              :key="card.id"
              class="bg-white/[0.04] rounded-lg p-3 group cursor-pointer hover:bg-white/[0.07] transition-colors"
              :class="[
                card.color ? 'border-l-4' : '',
                canEdit ? 'cursor-grab active:cursor-grabbing' : '',
              ]"
              :style="card.color ? { borderLeftColor: card.color } : {}"
              :draggable="canEdit"
              @dragstart="canEdit ? onDragStart($event, card, column.id) : null"
              @dragend="onDragEnd"
              @click="openCard(card)"
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

              <!-- Tags -->
              <div v-if="card.tags?.length" class="flex flex-wrap gap-1 mb-2">
                <span
                  v-for="tag in card.tags"
                  :key="tag.id"
                  class="px-2 py-0.5 rounded-full text-xs font-medium"
                  :style="{
                    backgroundColor: tag.color + '20',
                    color: tag.color,
                    border: '1px solid ' + tag.color + '40',
                  }"
                >
                  {{ tag.name }}
                </span>
              </div>

              <!-- Title + edit buttons -->
              <div class="flex items-start gap-1">
                <p class="text-white text-sm font-medium mb-1 flex-1">{{ card.title }}</p>
                <div
                  v-if="canEdit"
                  class="flex gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity shrink-0"
                >
                  <button
                    @click.stop="startEditCard(card)"
                    class="p-1 text-gray-500 hover:text-primary-400 rounded transition-colors"
                  >
                    <PencilIcon class="w-3.5 h-3.5" />
                  </button>
                  <button
                    @click.stop="deleteCard(card)"
                    class="p-1 text-gray-500 hover:text-red-400 rounded transition-colors"
                  >
                    <TrashIcon class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>

              <!-- Description preview -->
              <p v-if="card.description" class="text-gray-500 text-xs line-clamp-2 mb-2">
                {{ card.description }}
              </p>

              <!-- Attachment thumbnails -->
              <div v-if="card.attachments?.length" class="flex gap-1 mb-2">
                <img
                  v-for="(att, i) in card.attachments.slice(0, 3)"
                  :key="att.id"
                  :src="getAttachmentUrl(att.filename)"
                  class="w-12 h-12 rounded object-cover hover:opacity-80 transition-opacity"
                  @click.stop="openLightbox(getAttachmentUrl(att.filename))"
                />
                <span
                  v-if="card.attachments.length > 3"
                  class="w-12 h-12 rounded bg-white/[0.04] flex items-center justify-center text-xs text-gray-400"
                >
                  +{{ card.attachments.length - 3 }}
                </span>
              </div>

              <!-- Meta -->
              <div class="flex items-center gap-2 flex-wrap">
                <span
                  v-if="card.priority && card.priority !== 'medium'"
                  class="text-xs px-1.5 py-0.5 rounded text-white"
                  :class="getPriorityColor(card.priority)"
                >
                  {{ getPriorityLabel(card.priority) }}
                </span>
                <span v-if="card.due_date" class="text-xs text-gray-500">
                  {{ formatDate(card.due_date) }}
                </span>
                <span v-if="card.assignee_name" class="text-xs text-gray-500">
                  {{ card.assignee_name }}
                </span>
              </div>
            </div>

            <!-- Empty column -->
            <div
              v-if="!column.cards?.length && addingToColumn !== column.id"
              class="text-center py-8 text-gray-600 text-sm"
            >
              Keine Karten
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="mt-4 text-center text-sm text-gray-600">{{ $t('kanbanModule.erstelltMitKyuubisoft') }}</div>
    </div>

    <!-- Card Detail Modal (read-only) -->
    <Teleport to="body">
      <div
        v-if="viewingCard"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @mousedown.self="closeCard"
      >
        <div class="fixed inset-0 bg-black/60"></div>
        <div class="relative bg-dark-800 border border-dark-700 rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <!-- Header -->
          <div class="sticky top-0 bg-dark-800 border-b border-dark-700 p-4 flex items-start justify-between gap-3 z-10">
            <div class="flex-1 min-w-0">
              <h3 class="text-lg font-semibold text-white">{{ viewingCard.title }}</h3>
              <div class="flex items-center gap-2 mt-1 flex-wrap">
                <span
                  v-if="viewingCard.priority"
                  class="text-xs px-2 py-0.5 rounded text-white"
                  :class="getPriorityColor(viewingCard.priority)"
                >
                  {{ getPriorityLabel(viewingCard.priority) }}
                </span>
                <span v-if="viewingCard.due_date" class="text-xs text-gray-400">
                  Fällig: {{ formatDate(viewingCard.due_date) }}
                </span>
                <span v-if="viewingCard.assignee_name" class="text-xs text-gray-400">
                  {{ viewingCard.assignee_name }}
                </span>
              </div>
            </div>
            <button @click="closeCard" class="p-1 text-gray-400 hover:text-white rounded shrink-0">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4">
            <!-- Labels -->
            <div v-if="viewingCard.labels?.length" class="flex flex-wrap gap-1.5">
              <span
                v-for="label in viewingCard.labels"
                :key="label"
                class="w-10 h-2 rounded-full"
                :class="getLabelColorClass(label)"
              ></span>
            </div>

            <!-- Tags -->
            <div v-if="viewingCard.tags?.length" class="flex flex-wrap gap-1.5">
              <span
                v-for="tag in viewingCard.tags"
                :key="tag.id"
                class="px-2.5 py-1 rounded-full text-xs font-medium"
                :style="{
                  backgroundColor: tag.color + '20',
                  color: tag.color,
                  border: '1px solid ' + tag.color + '40',
                }"
              >
                {{ tag.name }}
              </span>
            </div>

            <!-- Description -->
            <div v-if="viewingCard.description">
              <h4 class="text-sm font-medium text-gray-400 mb-1">{{ $t('common.description') }}</h4>
              <p class="text-gray-300 text-sm whitespace-pre-wrap">{{ viewingCard.description }}</p>
            </div>

            <!-- Attachments -->
            <div v-if="viewingCard.attachments?.length">
              <h4 class="text-sm font-medium text-gray-400 mb-2">
                Anhänge ({{ viewingCard.attachments.length }})
              </h4>
              <div class="grid grid-cols-2 gap-2">
                <img
                  v-for="att in viewingCard.attachments"
                  :key="att.id"
                  :src="getAttachmentUrl(att.filename)"
                  class="w-full h-40 rounded-lg object-cover cursor-pointer hover:opacity-80 transition-opacity border border-white/[0.06]"
                  @click.stop="openLightbox(getAttachmentUrl(att.filename))"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Image Lightbox -->
    <Teleport to="body">
      <div
        v-if="lightboxImage"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4 cursor-pointer"
        @click="closeLightbox"
      >
        <div class="fixed inset-0 bg-black/80"></div>
        <img
          :src="lightboxImage"
          class="relative max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl"
          @click.stop
        />
        <button
          @click="closeLightbox"
          class="absolute top-4 right-4 p-2 bg-black/50 text-white rounded-full hover:bg-black/70 transition-colors z-10"
        >
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>
    </Teleport>

    <!-- Edit Card Modal -->
    <Teleport to="body">
      <div
        v-if="editingCard"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @mousedown.self="cancelEditCard"
      >
        <div class="fixed inset-0 bg-black/60"></div>
        <div
          class="relative bg-dark-800 border border-dark-700 rounded-xl w-full max-w-md p-6 space-y-4"
        >
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">{{ $t('kanban.editCard') }}</h3>
            <button @click="cancelEditCard" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div>
            <label class="block text-sm text-gray-400 mb-1">{{ $t('kanban.titleLabel') }}</label>
            <input
              v-model="editForm.title"
              type="text"
              class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
            />
          </div>

          <div>
            <label class="block text-sm text-gray-400 mb-1">{{ $t('common.description') }}</label>
            <textarea
              v-model="editForm.description"
              rows="3"
              class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500 resize-none"
            ></textarea>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm text-gray-400 mb-1">{{ $t('quickCapture.priority') }}</label>
              <select
                v-model="editForm.priority"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
              >
                <option value="low">{{ $t('kanban.low') }}</option>
                <option value="medium">{{ $t('kanbanModule.mittel') }}</option>
                <option value="high">{{ $t('kanban.high') }}</option>
                <option value="urgent">{{ $t('kanban.urgent') }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-1">{{ $t('kanbanModule.faelligAm') }}</label>
              <input
                v-model="editForm.due_date"
                type="date"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
              />
            </div>
          </div>

          <div class="flex gap-2 pt-2">
            <button
              @click="saveEditCard"
              :disabled="!editForm.title.trim() || savingEdit"
              class="flex-1 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 disabled:opacity-50 font-medium text-sm flex items-center justify-center gap-2"
            >
              <CheckIcon class="w-4 h-4" />
              {{ savingEdit ? 'Speichert...' : $t('common.save') }}
            </button>
            <button
              @click="deleteCard(editingCard)"
              class="px-4 py-2 bg-red-600/20 text-red-400 rounded-lg hover:bg-red-600/30 text-sm flex items-center gap-2"
            >
              <TrashIcon class="w-4 h-4" />
              {{ $t('common.delete') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
