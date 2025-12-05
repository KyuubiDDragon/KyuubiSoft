<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/core/api/axios'
import {
  TicketIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  ArrowLeftIcon,
  ChatBubbleLeftIcon,
  CheckCircleIcon,
  ClockIcon,
  XMarkIcon,
  ExclamationCircleIcon,
  ArrowPathIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()

// View mode: 'home' | 'create' | 'lookup' | 'view'
const viewMode = ref('home')

// State
const loading = ref(false)
const error = ref('')
const success = ref('')
const categories = ref([])
const ticket = ref(null)
const comments = ref([])

// Lookup form
const accessCode = ref('')

// Create form
const createForm = ref({
  guest_name: '',
  guest_email: '',
  title: '',
  description: '',
  category_id: '',
  priority: 'normal',
})

// Comment form
const newComment = ref('')
const submittingComment = ref(false)

// Status options
const statusOptions = [
  { value: 'open', label: 'Offen', color: 'bg-blue-500', textColor: 'text-blue-400', icon: TicketIcon },
  { value: 'in_progress', label: 'In Bearbeitung', color: 'bg-yellow-500', textColor: 'text-yellow-400', icon: ArrowPathIcon },
  { value: 'waiting', label: 'Warten auf Antwort', color: 'bg-purple-500', textColor: 'text-purple-400', icon: ClockIcon },
  { value: 'resolved', label: 'Gelöst', color: 'bg-green-500', textColor: 'text-green-400', icon: CheckCircleIcon },
  { value: 'closed', label: 'Geschlossen', color: 'bg-gray-500', textColor: 'text-gray-400', icon: XMarkIcon },
]

// Priority options
const priorityOptions = [
  { value: 'low', label: 'Niedrig', color: 'bg-gray-500' },
  { value: 'normal', label: 'Normal', color: 'bg-blue-500' },
  { value: 'high', label: 'Hoch', color: 'bg-orange-500' },
  { value: 'urgent', label: 'Dringend', color: 'bg-red-500' },
]

// Fetch categories (public endpoint)
async function fetchCategories() {
  try {
    const response = await api.get('/api/v1/public/ticket-categories')
    categories.value = response.data.data.categories || []
  } catch (err) {
    console.error('Error fetching categories:', err)
  }
}

// Create ticket
async function createTicket() {
  error.value = ''
  success.value = ''

  if (!createForm.value.guest_name.trim()) {
    error.value = 'Bitte geben Sie Ihren Namen an'
    return
  }
  if (!createForm.value.guest_email.trim()) {
    error.value = 'Bitte geben Sie Ihre E-Mail-Adresse an'
    return
  }
  if (!createForm.value.title.trim()) {
    error.value = 'Bitte geben Sie einen Titel an'
    return
  }
  if (!createForm.value.description.trim()) {
    error.value = 'Bitte beschreiben Sie Ihr Anliegen'
    return
  }

  loading.value = true

  try {
    const response = await api.post('/api/v1/tickets/public', createForm.value)
    ticket.value = response.data.data.ticket
    accessCode.value = ticket.value.access_code
    success.value = `Ihr Ticket wurde erstellt! Ihr Zugriffscode lautet: ${ticket.value.access_code}`
    viewMode.value = 'view'

    // Reset form
    createForm.value = {
      guest_name: '',
      guest_email: '',
      title: '',
      description: '',
      category_id: '',
      priority: 'normal',
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Fehler beim Erstellen des Tickets'
  } finally {
    loading.value = false
  }
}

// Lookup ticket
async function lookupTicket() {
  error.value = ''

  if (!accessCode.value.trim()) {
    error.value = 'Bitte geben Sie Ihren Zugriffscode ein'
    return
  }

  loading.value = true

  try {
    const response = await api.get(`/api/v1/tickets/public/${accessCode.value}`)
    ticket.value = response.data.data.ticket
    comments.value = response.data.data.comments || []
    viewMode.value = 'view'
  } catch (err) {
    error.value = 'Ticket nicht gefunden. Bitte überprüfen Sie Ihren Zugriffscode.'
  } finally {
    loading.value = false
  }
}

// Add comment to ticket
async function addComment() {
  if (!newComment.value.trim()) return

  submittingComment.value = true
  error.value = ''

  try {
    const response = await api.post(`/api/v1/tickets/public/${accessCode.value}/comments`, {
      content: newComment.value,
      guest_name: ticket.value.guest_name,
    })
    comments.value.push(response.data.data.comment)
    newComment.value = ''
  } catch (err) {
    error.value = 'Fehler beim Hinzufügen des Kommentars'
  } finally {
    submittingComment.value = false
  }
}

// Go back to home
function goHome() {
  viewMode.value = 'home'
  ticket.value = null
  comments.value = []
  error.value = ''
  success.value = ''
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

onMounted(() => {
  fetchCategories()

  // Check if we have a code in the URL
  if (route.params.code) {
    accessCode.value = route.params.code
    lookupTicket()
  }
})
</script>

<template>
  <div class="min-h-screen bg-dark-900 py-12 px-4">
    <div class="max-w-2xl mx-auto">
      <!-- Logo/Header -->
      <div class="text-center mb-8">
        <div class="w-16 h-16 bg-primary-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <TicketIcon class="w-8 h-8 text-white" />
        </div>
        <h1 class="text-2xl font-bold text-white">Support-Ticket</h1>
        <p class="text-gray-400 mt-2">Wir helfen Ihnen gerne weiter</p>
      </div>

      <!-- Error/Success Messages -->
      <div v-if="error" class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-xl flex items-start gap-3">
        <ExclamationCircleIcon class="w-5 h-5 text-red-400 flex-shrink-0 mt-0.5" />
        <p class="text-red-400">{{ error }}</p>
      </div>

      <div v-if="success && viewMode === 'view'" class="mb-6 p-4 bg-green-500/10 border border-green-500/20 rounded-xl flex items-start gap-3">
        <CheckCircleIcon class="w-5 h-5 text-green-400 flex-shrink-0 mt-0.5" />
        <div>
          <p class="text-green-400">{{ success }}</p>
          <p class="text-green-300 text-sm mt-1">
            Bewahren Sie diesen Code auf, um den Status Ihres Tickets zu verfolgen.
          </p>
        </div>
      </div>

      <!-- Home View -->
      <div v-if="viewMode === 'home'" class="space-y-4">
        <div class="bg-dark-800 border border-dark-700 rounded-xl p-6 space-y-6">
          <h2 class="text-lg font-semibold text-white text-center">Was möchten Sie tun?</h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <button
              @click="viewMode = 'create'"
              class="p-6 bg-dark-700 hover:bg-dark-600 rounded-xl transition-colors text-left"
            >
              <PlusIcon class="w-10 h-10 text-primary-400 mb-3" />
              <h3 class="font-medium text-white">Neues Ticket erstellen</h3>
              <p class="text-sm text-gray-400 mt-1">Erstellen Sie eine neue Support-Anfrage</p>
            </button>

            <button
              @click="viewMode = 'lookup'"
              class="p-6 bg-dark-700 hover:bg-dark-600 rounded-xl transition-colors text-left"
            >
              <MagnifyingGlassIcon class="w-10 h-10 text-primary-400 mb-3" />
              <h3 class="font-medium text-white">Ticket nachverfolgen</h3>
              <p class="text-sm text-gray-400 mt-1">Status eines bestehenden Tickets prüfen</p>
            </button>
          </div>
        </div>
      </div>

      <!-- Create View -->
      <div v-if="viewMode === 'create'" class="space-y-4">
        <button
          @click="goHome"
          class="flex items-center gap-2 text-gray-400 hover:text-white transition-colors"
        >
          <ArrowLeftIcon class="w-5 h-5" />
          Zurück
        </button>

        <div class="bg-dark-800 border border-dark-700 rounded-xl p-6">
          <h2 class="text-lg font-semibold text-white mb-6">Neues Ticket erstellen</h2>

          <div class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Ihr Name *</label>
                <input
                  v-model="createForm.guest_name"
                  type="text"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                  placeholder="Max Mustermann"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">E-Mail-Adresse *</label>
                <input
                  v-model="createForm.guest_email"
                  type="email"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                  placeholder="max@beispiel.de"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Betreff *</label>
              <input
                v-model="createForm.title"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Kurze Beschreibung Ihres Anliegens"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung *</label>
              <textarea
                v-model="createForm.description"
                rows="5"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 resize-none"
                placeholder="Beschreiben Sie Ihr Anliegen so detailliert wie möglich..."
              ></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Kategorie</label>
                <select
                  v-model="createForm.category_id"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                >
                  <option value="">Bitte wählen...</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">
                    {{ c.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Priorität</label>
                <select
                  v-model="createForm.priority"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                >
                  <option v-for="p in priorityOptions" :key="p.value" :value="p.value">
                    {{ p.label }}
                  </option>
                </select>
              </div>
            </div>

            <button
              @click="createTicket"
              :disabled="loading"
              class="w-full px-4 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              <div v-if="loading" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              <span>{{ loading ? 'Wird erstellt...' : 'Ticket absenden' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Lookup View -->
      <div v-if="viewMode === 'lookup'" class="space-y-4">
        <button
          @click="goHome"
          class="flex items-center gap-2 text-gray-400 hover:text-white transition-colors"
        >
          <ArrowLeftIcon class="w-5 h-5" />
          Zurück
        </button>

        <div class="bg-dark-800 border border-dark-700 rounded-xl p-6">
          <h2 class="text-lg font-semibold text-white mb-6">Ticket nachverfolgen</h2>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Zugriffscode</label>
              <input
                v-model="accessCode"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 font-mono"
                placeholder="TKT-XXXXXXXX"
                @keydown.enter="lookupTicket"
              />
              <p class="text-xs text-gray-500 mt-1">
                Den Zugriffscode haben Sie bei der Erstellung des Tickets erhalten.
              </p>
            </div>

            <button
              @click="lookupTicket"
              :disabled="loading"
              class="w-full px-4 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              <div v-if="loading" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              <MagnifyingGlassIcon v-else class="w-5 h-5" />
              <span>{{ loading ? 'Wird gesucht...' : 'Ticket suchen' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- View Ticket -->
      <div v-if="viewMode === 'view' && ticket" class="space-y-4">
        <button
          @click="goHome"
          class="flex items-center gap-2 text-gray-400 hover:text-white transition-colors"
        >
          <ArrowLeftIcon class="w-5 h-5" />
          Zurück
        </button>

        <!-- Ticket Details -->
        <div class="bg-dark-800 border border-dark-700 rounded-xl p-6">
          <div class="flex items-start justify-between gap-4 mb-4">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="text-sm text-gray-500 font-mono">#{{ ticket.ticket_number }}</span>
                <span
                  class="px-2 py-0.5 text-xs rounded-full"
                  :class="getStatusInfo(ticket.status).color"
                >
                  {{ getStatusInfo(ticket.status).label }}
                </span>
              </div>
              <h2 class="text-xl font-bold text-white">{{ ticket.title }}</h2>
            </div>
          </div>

          <div class="bg-dark-700 rounded-lg p-4 mb-4">
            <p class="text-gray-300 whitespace-pre-wrap">{{ ticket.description }}</p>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
              <span class="text-gray-500 block">Status</span>
              <span :class="getStatusInfo(ticket.status).textColor">{{ getStatusInfo(ticket.status).label }}</span>
            </div>
            <div>
              <span class="text-gray-500 block">Priorität</span>
              <span class="text-white">{{ getPriorityInfo(ticket.priority).label }}</span>
            </div>
            <div>
              <span class="text-gray-500 block">Erstellt</span>
              <span class="text-white">{{ formatDate(ticket.created_at) }}</span>
            </div>
            <div>
              <span class="text-gray-500 block">Zugriffscode</span>
              <span class="text-white font-mono">{{ ticket.access_code }}</span>
            </div>
          </div>
        </div>

        <!-- Comments -->
        <div class="bg-dark-800 border border-dark-700 rounded-xl p-6">
          <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
            <ChatBubbleLeftIcon class="w-5 h-5" />
            Kommunikation
          </h3>

          <div class="space-y-4 mb-6">
            <div
              v-for="comment in comments"
              :key="comment.id"
              class="flex gap-3"
              :class="{ 'flex-row-reverse': !comment.user_id }"
            >
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-medium flex-shrink-0"
                :class="comment.user_id ? 'bg-primary-600' : 'bg-dark-600'"
              >
                {{ (comment.username || comment.guest_name)?.[0]?.toUpperCase() || '?' }}
              </div>
              <div
                class="flex-1 max-w-[80%] rounded-lg p-3"
                :class="comment.user_id ? 'bg-primary-600/20' : 'bg-dark-700'"
              >
                <div class="flex items-center gap-2 mb-1">
                  <span class="font-medium text-white text-sm">
                    {{ comment.username || comment.guest_name || 'Sie' }}
                  </span>
                  <span v-if="comment.user_id" class="text-xs text-primary-400 bg-primary-500/20 px-1.5 py-0.5 rounded">
                    Support
                  </span>
                  <span class="text-xs text-gray-500">{{ formatRelativeDate(comment.created_at) }}</span>
                </div>
                <p class="text-gray-300 text-sm whitespace-pre-wrap">{{ comment.content }}</p>
              </div>
            </div>

            <div v-if="comments.length === 0" class="text-center py-8 text-gray-500">
              Noch keine Nachrichten
            </div>
          </div>

          <!-- Add Comment -->
          <div v-if="ticket.status !== 'closed'" class="border-t border-dark-700 pt-4">
            <textarea
              v-model="newComment"
              rows="3"
              class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 resize-none"
              placeholder="Ihre Nachricht..."
            ></textarea>
            <div class="flex justify-end mt-3">
              <button
                @click="addComment"
                :disabled="!newComment.trim() || submittingComment"
                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ submittingComment ? 'Wird gesendet...' : 'Nachricht senden' }}
              </button>
            </div>
          </div>

          <div v-else class="border-t border-dark-700 pt-4 text-center text-gray-500">
            Dieses Ticket wurde geschlossen. Keine weiteren Nachrichten möglich.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
