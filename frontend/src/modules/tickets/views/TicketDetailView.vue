<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import {
  ArrowLeftIcon,
  PencilIcon,
  TrashIcon,
  UserCircleIcon,
  CalendarIcon,
  TagIcon,
  ClockIcon,
  CheckCircleIcon,
  XMarkIcon,
  ChatBubbleLeftIcon,
  PaperClipIcon,
  ArrowPathIcon,
  ExclamationCircleIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const uiStore = useUiStore()
const authStore = useAuthStore()

// State
const ticket = ref(null)
const comments = ref([])
const statusHistory = ref([])
const categories = ref([])
const users = ref([])
const loading = ref(true)
const showEditModal = ref(false)
const showAssignModal = ref(false)
const showStatusModal = ref(false)

// Comment
const newComment = ref('')
const isInternalComment = ref(false)
const submittingComment = ref(false)

// Edit form
const editForm = ref({
  title: '',
  description: '',
  category_id: '',
  priority: 'normal',
})

// Status form
const statusForm = ref({
  status: '',
  comment: '',
})

// Status options
const statusOptions = [
  { value: 'open', label: 'Offen', color: 'bg-blue-500', textColor: 'text-blue-400' },
  { value: 'in_progress', label: 'In Bearbeitung', color: 'bg-yellow-500', textColor: 'text-yellow-400' },
  { value: 'waiting', label: 'Warten', color: 'bg-purple-500', textColor: 'text-purple-400' },
  { value: 'resolved', label: 'Gelöst', color: 'bg-green-500', textColor: 'text-green-400' },
  { value: 'closed', label: 'Geschlossen', color: 'bg-gray-500', textColor: 'text-gray-400' },
]

// Priority options
const priorityOptions = [
  { value: 'low', label: 'Niedrig', color: 'bg-gray-500' },
  { value: 'normal', label: 'Normal', color: 'bg-blue-500' },
  { value: 'high', label: 'Hoch', color: 'bg-orange-500' },
  { value: 'urgent', label: 'Dringend', color: 'bg-red-500' },
]

// Fetch ticket
async function fetchTicket() {
  loading.value = true
  try {
    const response = await api.get(`/api/v1/tickets/${route.params.id}`)
    ticket.value = response.data.data.ticket
    comments.value = response.data.data.comments || []
    statusHistory.value = response.data.data.status_history || []
  } catch (error) {
    uiStore.showError('Ticket nicht gefunden')
    router.push('/tickets')
  } finally {
    loading.value = false
  }
}

// Fetch categories
async function fetchCategories() {
  try {
    const response = await api.get('/api/v1/tickets/categories')
    categories.value = response.data.data.categories || []
  } catch (error) {
    console.error('Error fetching categories:', error)
  }
}

// Fetch users for assignment
async function fetchUsers() {
  try {
    const response = await api.get('/api/v1/users')
    users.value = response.data.data.users || []
  } catch (error) {
    console.error('Error fetching users:', error)
  }
}

// Add comment
async function addComment() {
  if (!newComment.value.trim()) return

  submittingComment.value = true
  try {
    const response = await api.post(`/api/v1/tickets/${ticket.value.id}/comments`, {
      content: newComment.value,
      is_internal: isInternalComment.value ? 1 : 0,
    })
    comments.value.push(response.data.data.comment)
    newComment.value = ''
    isInternalComment.value = false
    uiStore.showSuccess('Kommentar hinzugefügt')
  } catch (error) {
    uiStore.showError('Fehler beim Hinzufügen des Kommentars')
  } finally {
    submittingComment.value = false
  }
}

// Delete comment
async function deleteComment(commentId) {
  if (!confirm('Kommentar wirklich löschen?')) return

  try {
    await api.delete(`/api/v1/tickets/${ticket.value.id}/comments/${commentId}`)
    comments.value = comments.value.filter(c => c.id !== commentId)
    uiStore.showSuccess('Kommentar gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Open edit modal
function openEditModal() {
  editForm.value = {
    title: ticket.value.title,
    description: ticket.value.description,
    category_id: ticket.value.category_id || '',
    priority: ticket.value.priority,
  }
  showEditModal.value = true
}

// Save edit
async function saveEdit() {
  if (!editForm.value.title.trim()) {
    uiStore.showError('Titel ist erforderlich')
    return
  }

  try {
    await api.put(`/api/v1/tickets/${ticket.value.id}`, editForm.value)
    uiStore.showSuccess('Ticket aktualisiert')
    showEditModal.value = false
    fetchTicket()
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Speichern')
  }
}

// Open status modal
function openStatusModal() {
  statusForm.value = {
    status: ticket.value.status,
    comment: '',
  }
  showStatusModal.value = true
}

// Update status
async function updateStatus() {
  try {
    await api.put(`/api/v1/tickets/${ticket.value.id}/status`, statusForm.value)
    uiStore.showSuccess('Status aktualisiert')
    showStatusModal.value = false
    fetchTicket()
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Aktualisieren')
  }
}

// Assign ticket
async function assignTicket(userId) {
  try {
    await api.put(`/api/v1/tickets/${ticket.value.id}/assign`, {
      assigned_to: userId,
    })
    uiStore.showSuccess('Ticket zugewiesen')
    showAssignModal.value = false
    fetchTicket()
  } catch (error) {
    uiStore.showError('Fehler bei der Zuweisung')
  }
}

// Delete ticket
async function deleteTicket() {
  if (!confirm('Ticket wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) return

  try {
    await api.delete(`/api/v1/tickets/${ticket.value.id}`)
    uiStore.showSuccess('Ticket gelöscht')
    router.push('/tickets')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Get status info
function getStatusInfo(status) {
  return statusOptions.find(s => s.value === status) || statusOptions[0]
}

// Get priority info
function getPriorityInfo(priority) {
  return priorityOptions.find(p => p.value === priority) || priorityOptions[1]
}

// Format date
function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Format relative date
function formatRelativeDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now - date
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'gerade eben'
  if (diffMins < 60) return `vor ${diffMins} Min.`
  if (diffHours < 24) return `vor ${diffHours} Std.`
  if (diffDays < 7) return `vor ${diffDays} Tagen`
  return date.toLocaleDateString('de-DE')
}

// Can manage ticket (admin or assignee)
const canManage = computed(() => {
  if (!ticket.value) return false
  return authStore.hasRole('admin') ||
         authStore.hasRole('owner') ||
         ticket.value.assigned_to === authStore.user?.id
})

onMounted(() => {
  fetchTicket()
  fetchCategories()
  fetchUsers()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <template v-else-if="ticket">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-4">
          <button
            @click="router.push('/tickets')"
            class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors mt-1"
          >
            <ArrowLeftIcon class="w-5 h-5" />
          </button>
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="text-sm text-gray-500 font-mono">#{{ ticket.ticket_number }}</span>
              <span
                class="px-2 py-0.5 text-xs rounded-full"
                :class="getStatusInfo(ticket.status).color"
              >
                {{ getStatusInfo(ticket.status).label }}
              </span>
              <span
                class="px-2 py-0.5 text-xs rounded-full"
                :class="getPriorityInfo(ticket.priority).color"
              >
                {{ getPriorityInfo(ticket.priority).label }}
              </span>
            </div>
            <h1 class="text-2xl font-bold text-white">{{ ticket.title }}</h1>
          </div>
        </div>

        <div v-if="canManage" class="flex items-center gap-2">
          <button
            @click="openStatusModal"
            class="px-4 py-2 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors flex items-center gap-2"
          >
            <ArrowPathIcon class="w-5 h-5" />
            Status
          </button>
          <button
            @click="showAssignModal = true"
            class="px-4 py-2 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors flex items-center gap-2"
          >
            <UserCircleIcon class="w-5 h-5" />
            Zuweisen
          </button>
          <button
            @click="openEditModal"
            class="px-4 py-2 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors flex items-center gap-2"
          >
            <PencilIcon class="w-5 h-5" />
            Bearbeiten
          </button>
          <button
            @click="deleteTicket"
            class="p-2 text-gray-400 hover:text-red-400 hover:bg-dark-700 rounded-lg transition-colors"
          >
            <TrashIcon class="w-5 h-5" />
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Description -->
          <div class="bg-dark-800 border border-dark-700 rounded-xl p-6">
            <h3 class="text-sm font-medium text-gray-400 mb-3">Beschreibung</h3>
            <div class="text-white whitespace-pre-wrap">{{ ticket.description }}</div>
          </div>

          <!-- Comments -->
          <div class="bg-dark-800 border border-dark-700 rounded-xl p-6">
            <h3 class="text-sm font-medium text-gray-400 mb-4 flex items-center gap-2">
              <ChatBubbleLeftIcon class="w-5 h-5" />
              Kommentare ({{ comments.length }})
            </h3>

            <!-- Comment list -->
            <div class="space-y-4 mb-6">
              <div
                v-for="comment in comments"
                :key="comment.id"
                class="flex gap-3"
                :class="{ 'bg-dark-700/50 p-3 rounded-lg border-l-2 border-yellow-500': comment.is_internal }"
              >
                <div class="w-10 h-10 rounded-full bg-primary-600 flex items-center justify-center text-white text-sm font-medium flex-shrink-0">
                  {{ comment.username?.[0]?.toUpperCase() || comment.guest_name?.[0]?.toUpperCase() || '?' }}
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="font-medium text-white">
                      {{ comment.username || comment.guest_name || 'Gast' }}
                    </span>
                    <span class="text-xs text-gray-500">{{ formatRelativeDate(comment.created_at) }}</span>
                    <span v-if="comment.is_internal" class="text-xs text-yellow-500 bg-yellow-500/20 px-2 py-0.5 rounded">
                      Intern
                    </span>
                  </div>
                  <p class="text-gray-300 whitespace-pre-wrap">{{ comment.content }}</p>
                  <button
                    v-if="comment.user_id === authStore.user?.id || canManage"
                    @click="deleteComment(comment.id)"
                    class="mt-2 text-xs text-gray-500 hover:text-red-400 transition-colors"
                  >
                    Löschen
                  </button>
                </div>
              </div>

              <div v-if="comments.length === 0" class="text-center py-8 text-gray-500">
                Noch keine Kommentare
              </div>
            </div>

            <!-- Add comment -->
            <div class="border-t border-dark-700 pt-4">
              <textarea
                v-model="newComment"
                rows="3"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 resize-none"
                placeholder="Kommentar schreiben..."
              ></textarea>
              <div class="flex items-center justify-between mt-3">
                <label v-if="canManage" class="flex items-center gap-2 text-sm text-gray-400 cursor-pointer">
                  <input
                    v-model="isInternalComment"
                    type="checkbox"
                    class="w-4 h-4 rounded bg-dark-700 border-dark-600 text-primary-600 focus:ring-primary-500"
                  />
                  Interner Kommentar (nur für Team sichtbar)
                </label>
                <div v-else></div>
                <button
                  @click="addComment"
                  :disabled="!newComment.trim() || submittingComment"
                  class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {{ submittingComment ? 'Wird gesendet...' : 'Kommentar senden' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Status History -->
          <div class="bg-dark-800 border border-dark-700 rounded-xl p-6">
            <h3 class="text-sm font-medium text-gray-400 mb-4 flex items-center gap-2">
              <ClockIcon class="w-5 h-5" />
              Verlauf
            </h3>

            <div class="space-y-4">
              <div
                v-for="history in statusHistory"
                :key="history.id"
                class="flex gap-3"
              >
                <div class="w-8 h-8 rounded-full bg-dark-600 flex items-center justify-center flex-shrink-0">
                  <ArrowPathIcon class="w-4 h-4 text-gray-400" />
                </div>
                <div>
                  <div class="flex items-center gap-2 text-sm">
                    <span class="text-white font-medium">{{ history.changed_by_name || 'System' }}</span>
                    <span class="text-gray-500">hat den Status geändert</span>
                    <span v-if="history.old_status" class="text-gray-400">
                      von <span :class="getStatusInfo(history.old_status).textColor">{{ getStatusInfo(history.old_status).label }}</span>
                    </span>
                    <span class="text-gray-500">auf</span>
                    <span :class="getStatusInfo(history.new_status).textColor">{{ getStatusInfo(history.new_status).label }}</span>
                  </div>
                  <p v-if="history.comment" class="text-sm text-gray-400 mt-1">{{ history.comment }}</p>
                  <div class="text-xs text-gray-500 mt-1">{{ formatRelativeDate(history.created_at) }}</div>
                </div>
              </div>

              <div v-if="statusHistory.length === 0" class="text-center py-4 text-gray-500 text-sm">
                Keine Statusänderungen
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Meta Info -->
          <div class="bg-dark-800 border border-dark-700 rounded-xl p-6 space-y-4">
            <div>
              <h4 class="text-xs font-medium text-gray-500 uppercase mb-1">Erstellt von</h4>
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-dark-600 flex items-center justify-center text-white text-sm">
                  {{ ticket.creator_name?.[0]?.toUpperCase() || ticket.guest_name?.[0]?.toUpperCase() || '?' }}
                </div>
                <div>
                  <div class="text-white text-sm">{{ ticket.creator_name || ticket.guest_name || 'Gast' }}</div>
                  <div v-if="ticket.guest_email" class="text-xs text-gray-500">{{ ticket.guest_email }}</div>
                </div>
              </div>
            </div>

            <div>
              <h4 class="text-xs font-medium text-gray-500 uppercase mb-1">Zugewiesen an</h4>
              <div v-if="ticket.assignee_name" class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center text-white text-sm">
                  {{ ticket.assignee_name[0].toUpperCase() }}
                </div>
                <span class="text-white text-sm">{{ ticket.assignee_name }}</span>
              </div>
              <div v-else class="text-gray-500 text-sm">Nicht zugewiesen</div>
            </div>

            <div>
              <h4 class="text-xs font-medium text-gray-500 uppercase mb-1">Kategorie</h4>
              <div v-if="ticket.category_name" class="flex items-center gap-2">
                <div
                  class="w-3 h-3 rounded-full"
                  :style="{ backgroundColor: ticket.category_color || '#6366f1' }"
                ></div>
                <span class="text-white text-sm">{{ ticket.category_name }}</span>
              </div>
              <div v-else class="text-gray-500 text-sm">Keine Kategorie</div>
            </div>

            <div v-if="ticket.project_name">
              <h4 class="text-xs font-medium text-gray-500 uppercase mb-1">Projekt</h4>
              <div class="text-white text-sm">{{ ticket.project_name }}</div>
            </div>

            <div>
              <h4 class="text-xs font-medium text-gray-500 uppercase mb-1">Erstellt am</h4>
              <div class="text-white text-sm">{{ formatDate(ticket.created_at) }}</div>
            </div>

            <div>
              <h4 class="text-xs font-medium text-gray-500 uppercase mb-1">Zuletzt aktualisiert</h4>
              <div class="text-white text-sm">{{ formatDate(ticket.updated_at) }}</div>
            </div>

            <div v-if="ticket.resolved_at">
              <h4 class="text-xs font-medium text-gray-500 uppercase mb-1">Gelöst am</h4>
              <div class="text-green-400 text-sm">{{ formatDate(ticket.resolved_at) }}</div>
            </div>

            <div v-if="ticket.access_code">
              <h4 class="text-xs font-medium text-gray-500 uppercase mb-1">Zugriffscode</h4>
              <div class="text-gray-300 text-sm font-mono bg-dark-700 px-2 py-1 rounded">
                {{ ticket.access_code }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Edit Modal -->
    <Teleport to="body">
      <div
        v-if="showEditModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-2xl">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">Ticket bearbeiten</h2>
            <button @click="showEditModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Titel</label>
              <input
                v-model="editForm.title"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <textarea
                v-model="editForm.description"
                rows="5"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500 resize-none"
              ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Kategorie</label>
                <select
                  v-model="editForm.category_id"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                >
                  <option value="">Keine Kategorie</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">
                    {{ c.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Priorität</label>
                <select
                  v-model="editForm.priority"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                >
                  <option v-for="p in priorityOptions" :key="p.value" :value="p.value">
                    {{ p.label }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-dark-700">
            <button @click="showEditModal = false" class="px-4 py-2 text-gray-400 hover:text-white transition-colors">
              Abbrechen
            </button>
            <button @click="saveEdit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors">
              Speichern
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Status Modal -->
    <Teleport to="body">
      <div
        v-if="showStatusModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">Status ändern</h2>
            <button @click="showStatusModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Neuer Status</label>
              <div class="grid grid-cols-2 gap-2">
                <button
                  v-for="s in statusOptions"
                  :key="s.value"
                  @click="statusForm.status = s.value"
                  class="px-3 py-2 rounded-lg text-sm transition-all"
                  :class="statusForm.status === s.value
                    ? `${s.color} text-white`
                    : 'bg-dark-700 text-gray-300 hover:bg-dark-600'"
                >
                  {{ s.label }}
                </button>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Kommentar (optional)</label>
              <textarea
                v-model="statusForm.comment"
                rows="3"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500 resize-none"
                placeholder="Grund für die Statusänderung..."
              ></textarea>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-dark-700">
            <button @click="showStatusModal = false" class="px-4 py-2 text-gray-400 hover:text-white transition-colors">
              Abbrechen
            </button>
            <button @click="updateStatus" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors">
              Status ändern
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Assign Modal -->
    <Teleport to="body">
      <div
        v-if="showAssignModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">Ticket zuweisen</h2>
            <button @click="showAssignModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4">
            <div class="space-y-2 max-h-64 overflow-y-auto">
              <button
                @click="assignTicket(null)"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left transition-colors"
                :class="!ticket?.assigned_to ? 'bg-primary-600 text-white' : 'bg-dark-700 text-gray-300 hover:bg-dark-600'"
              >
                <div class="w-8 h-8 rounded-full bg-dark-500 flex items-center justify-center">
                  <XMarkIcon class="w-4 h-4 text-gray-400" />
                </div>
                <span>Nicht zugewiesen</span>
              </button>

              <button
                v-for="user in users"
                :key="user.id"
                @click="assignTicket(user.id)"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left transition-colors"
                :class="ticket?.assigned_to === user.id ? 'bg-primary-600 text-white' : 'bg-dark-700 text-gray-300 hover:bg-dark-600'"
              >
                <div class="w-8 h-8 rounded-full bg-dark-500 flex items-center justify-center text-white text-sm">
                  {{ user.username[0].toUpperCase() }}
                </div>
                <div>
                  <div class="font-medium">{{ user.username }}</div>
                  <div class="text-xs opacity-75">{{ user.email }}</div>
                </div>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
