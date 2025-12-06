<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useProjectStore } from '@/stores/project'
import { useAuthStore } from '@/stores/auth'
import {
  PlusIcon,
  FunnelIcon,
  MagnifyingGlassIcon,
  TicketIcon,
  UserCircleIcon,
  CalendarIcon,
  TagIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  XMarkIcon,
  ExclamationCircleIcon,
  ClockIcon,
  CheckCircleIcon,
  ArrowPathIcon,
  LinkIcon,
  ClipboardDocumentIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const uiStore = useUiStore()
const projectStore = useProjectStore()
const authStore = useAuthStore()

// State
const tickets = ref([])
const categories = ref([])
const loading = ref(true)
const showCreateModal = ref(false)
const showFilterPanel = ref(false)
const showPublicLinkModal = ref(false)
const stats = ref(null)

// Pagination
const page = ref(1)
const limit = ref(20)
const total = ref(0)

// Filters
const filters = ref({
  status: '',
  priority: '',
  category_id: '',
  assigned_to: '',
  search: '',
})

// Form
const form = ref({
  title: '',
  description: '',
  category_id: '',
  priority: 'normal',
  project_id: null,
})

// Status options
const statusOptions = [
  { value: 'open', label: 'Offen', color: 'bg-blue-500', icon: TicketIcon },
  { value: 'in_progress', label: 'In Bearbeitung', color: 'bg-yellow-500', icon: ArrowPathIcon },
  { value: 'waiting', label: 'Warten', color: 'bg-purple-500', icon: ClockIcon },
  { value: 'resolved', label: 'Gelöst', color: 'bg-green-500', icon: CheckCircleIcon },
  { value: 'closed', label: 'Geschlossen', color: 'bg-gray-500', icon: XMarkIcon },
]

// Priority options
const priorityOptions = [
  { value: 'low', label: 'Niedrig', color: 'bg-gray-500' },
  { value: 'normal', label: 'Normal', color: 'bg-blue-500' },
  { value: 'high', label: 'Hoch', color: 'bg-orange-500' },
  { value: 'urgent', label: 'Dringend', color: 'bg-red-500' },
]

// Watch for project changes
watch(() => projectStore.selectedProjectId, () => {
  page.value = 1
  fetchTickets()
})

// Fetch tickets
async function fetchTickets() {
  loading.value = true
  try {
    const params = {
      page: page.value,
      limit: limit.value,
      ...filters.value,
    }

    if (projectStore.selectedProjectId) {
      params.project_id = projectStore.selectedProjectId
    }

    // Remove empty filters
    Object.keys(params).forEach(key => {
      if (params[key] === '') delete params[key]
    })

    const response = await api.get('/api/v1/tickets', { params })
    tickets.value = response.data.data.tickets || []
    total.value = response.data.data.total || 0
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Tickets')
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

// Fetch stats
async function fetchStats() {
  try {
    const response = await api.get('/api/v1/tickets/stats')
    // Use all_stats if available, otherwise fall back to my_stats
    stats.value = response.data.data.all_stats || response.data.data.my_stats || response.data.data
  } catch (error) {
    console.error('Error fetching stats:', error)
  }
}

// Create ticket
async function createTicket() {
  if (!form.value.title.trim()) {
    uiStore.showError('Titel ist erforderlich')
    return
  }
  if (!form.value.description.trim()) {
    uiStore.showError('Beschreibung ist erforderlich')
    return
  }

  try {
    if (projectStore.selectedProjectId) {
      form.value.project_id = projectStore.selectedProjectId
    }

    const response = await api.post('/api/v1/tickets', form.value)
    uiStore.showSuccess('Ticket erstellt')
    showCreateModal.value = false
    resetForm()
    fetchTickets()
    fetchStats()

    // Navigate to the new ticket
    router.push(`/tickets/${response.data.data.ticket.id}`)
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Erstellen')
  }
}

// Reset form
function resetForm() {
  form.value = {
    title: '',
    description: '',
    category_id: '',
    priority: 'normal',
    project_id: null,
  }
}

// Apply filters
function applyFilters() {
  page.value = 1
  fetchTickets()
}

// Clear filters
function clearFilters() {
  filters.value = {
    status: '',
    priority: '',
    category_id: '',
    assigned_to: '',
    search: '',
  }
  page.value = 1
  fetchTickets()
}

// Navigate to ticket detail
function openTicket(ticket) {
  router.push(`/tickets/${ticket.id}`)
}

// Get status info
function getStatusInfo(status) {
  return statusOptions.find(s => s.value === status) || statusOptions[0]
}

// Get priority info
function getPriorityInfo(priority) {
  return priorityOptions.find(p => p.value === priority) || priorityOptions[1]
}

// Get category by id
function getCategory(categoryId) {
  return categories.value.find(c => c.id === categoryId)
}

// Format date
function formatDate(dateStr) {
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

// Pagination
const totalPages = computed(() => Math.ceil(total.value / limit.value))

function prevPage() {
  if (page.value > 1) {
    page.value--
    fetchTickets()
  }
}

function nextPage() {
  if (page.value < totalPages.value) {
    page.value++
    fetchTickets()
  }
}

// Active filters count
const activeFiltersCount = computed(() => {
  return Object.values(filters.value).filter(v => v !== '').length
})

// Public ticket URL
const publicTicketUrl = computed(() => {
  return `${window.location.origin}/support`
})

// Copy public link to clipboard
async function copyPublicLink() {
  try {
    await navigator.clipboard.writeText(publicTicketUrl.value)
    uiStore.showSuccess('Link in die Zwischenablage kopiert!')
  } catch (error) {
    // Fallback for older browsers
    const textArea = document.createElement('textarea')
    textArea.value = publicTicketUrl.value
    document.body.appendChild(textArea)
    textArea.select()
    document.execCommand('copy')
    document.body.removeChild(textArea)
    uiStore.showSuccess('Link in die Zwischenablage kopiert!')
  }
}

// Open public ticket page in new tab
function openPublicTicketPage() {
  window.open(publicTicketUrl.value, '_blank')
}

onMounted(() => {
  fetchTickets()
  fetchCategories()
  fetchStats()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Tickets</h1>
        <p class="text-gray-400 text-sm mt-1">Support-Anfragen verwalten</p>
      </div>
      <div class="flex items-center gap-3">
        <button
          @click="showPublicLinkModal = true"
          class="px-4 py-2 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors flex items-center gap-2"
          title="Öffentlichen Support-Link teilen"
        >
          <LinkIcon class="w-5 h-5" />
          <span class="hidden sm:inline">Öffentlicher Link</span>
        </button>
        <button
          @click="showFilterPanel = !showFilterPanel"
          class="px-4 py-2 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors flex items-center gap-2"
          :class="{ 'ring-2 ring-primary-500': activeFiltersCount > 0 }"
        >
          <FunnelIcon class="w-5 h-5" />
          <span>Filter</span>
          <span v-if="activeFiltersCount > 0" class="ml-1 w-5 h-5 bg-primary-600 rounded-full text-xs flex items-center justify-center">
            {{ activeFiltersCount }}
          </span>
        </button>
        <button
          @click="showCreateModal = true"
          class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors flex items-center gap-2"
        >
          <PlusIcon class="w-5 h-5" />
          <span>Neues Ticket</span>
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div v-if="stats" class="grid grid-cols-2 md:grid-cols-5 gap-4">
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="text-2xl font-bold text-white">{{ stats.total }}</div>
        <div class="text-sm text-gray-400">Gesamt</div>
      </div>
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="text-2xl font-bold text-blue-400">{{ stats.open }}</div>
        <div class="text-sm text-gray-400">Offen</div>
      </div>
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="text-2xl font-bold text-yellow-400">{{ stats.in_progress }}</div>
        <div class="text-sm text-gray-400">In Bearbeitung</div>
      </div>
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="text-2xl font-bold text-purple-400">{{ stats.waiting }}</div>
        <div class="text-sm text-gray-400">Wartend</div>
      </div>
      <div class="bg-dark-800 border border-dark-700 rounded-xl p-4">
        <div class="text-2xl font-bold text-green-400">{{ stats.resolved + stats.closed }}</div>
        <div class="text-sm text-gray-400">Erledigt</div>
      </div>
    </div>

    <!-- Filter Panel -->
    <div v-if="showFilterPanel" class="bg-dark-800 border border-dark-700 rounded-xl p-4">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <!-- Search -->
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-300 mb-1">Suche</label>
          <div class="relative">
            <MagnifyingGlassIcon class="w-5 h-5 text-gray-500 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              v-model="filters.search"
              type="text"
              class="w-full bg-dark-700 border border-dark-600 rounded-lg pl-10 pr-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
              placeholder="Ticket-Nr. oder Titel..."
              @keydown.enter="applyFilters"
            />
          </div>
        </div>

        <!-- Status -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1">Status</label>
          <select
            v-model="filters.status"
            class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
          >
            <option value="">Alle</option>
            <option v-for="s in statusOptions" :key="s.value" :value="s.value">
              {{ s.label }}
            </option>
          </select>
        </div>

        <!-- Priority -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1">Priorität</label>
          <select
            v-model="filters.priority"
            class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
          >
            <option value="">Alle</option>
            <option v-for="p in priorityOptions" :key="p.value" :value="p.value">
              {{ p.label }}
            </option>
          </select>
        </div>

        <!-- Category -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-1">Kategorie</label>
          <select
            v-model="filters.category_id"
            class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
          >
            <option value="">Alle</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">
              {{ c.name }}
            </option>
          </select>
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 mt-4">
        <button
          @click="clearFilters"
          class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
        >
          Zurücksetzen
        </button>
        <button
          @click="applyFilters"
          class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
        >
          Anwenden
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Tickets List -->
    <div v-else-if="tickets.length > 0" class="space-y-3">
      <div
        v-for="ticket in tickets"
        :key="ticket.id"
        @click="openTicket(ticket)"
        class="bg-dark-800 border border-dark-700 rounded-xl p-4 cursor-pointer hover:border-dark-600 transition-all group"
      >
        <div class="flex items-start gap-4">
          <!-- Priority indicator -->
          <div
            class="w-1 h-full min-h-[60px] rounded-full flex-shrink-0"
            :class="getPriorityInfo(ticket.priority).color"
          ></div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-xs text-gray-500 font-mono">#{{ ticket.ticket_number }}</span>
                  <span
                    class="px-2 py-0.5 text-xs rounded-full"
                    :class="getStatusInfo(ticket.status).color"
                  >
                    {{ getStatusInfo(ticket.status).label }}
                  </span>
                  <span
                    v-if="ticket.category_name"
                    class="px-2 py-0.5 text-xs rounded-full bg-dark-600 text-gray-300"
                    :style="{ borderLeft: `3px solid ${ticket.category_color || '#6366f1'}` }"
                  >
                    {{ ticket.category_name }}
                  </span>
                </div>
                <h3 class="text-white font-medium group-hover:text-primary-400 transition-colors">
                  {{ ticket.title }}
                </h3>
                <p class="text-gray-400 text-sm line-clamp-1 mt-1">
                  {{ ticket.description }}
                </p>
              </div>

              <!-- Meta -->
              <div class="flex-shrink-0 text-right">
                <div class="text-xs text-gray-500">{{ formatDate(ticket.created_at) }}</div>
                <div v-if="ticket.assignee_name" class="flex items-center gap-1 text-xs text-gray-400 mt-1 justify-end">
                  <UserCircleIcon class="w-4 h-4" />
                  {{ ticket.assignee_name }}
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center gap-4 mt-3 text-xs text-gray-500">
              <div class="flex items-center gap-1">
                <UserCircleIcon class="w-4 h-4" />
                {{ ticket.creator_name || ticket.guest_name || 'Gast' }}
              </div>
              <div v-if="ticket.project_name" class="flex items-center gap-1">
                <TagIcon class="w-4 h-4" />
                {{ ticket.project_name }}
              </div>
              <div v-if="ticket.comment_count > 0" class="flex items-center gap-1">
                <span>{{ ticket.comment_count }} Kommentar{{ ticket.comment_count !== 1 ? 'e' : '' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between pt-4">
        <div class="text-sm text-gray-400">
          {{ (page - 1) * limit + 1 }} - {{ Math.min(page * limit, total) }} von {{ total }} Tickets
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="prevPage"
            :disabled="page <= 1"
            class="p-2 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ChevronLeftIcon class="w-5 h-5" />
          </button>
          <span class="text-gray-400 text-sm">Seite {{ page }} / {{ totalPages }}</span>
          <button
            @click="nextPage"
            :disabled="page >= totalPages"
            class="p-2 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ChevronRightIcon class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="bg-dark-800 border border-dark-700 rounded-xl p-12 text-center">
      <TicketIcon class="w-16 h-16 text-gray-500 mx-auto mb-4" />
      <h3 class="text-xl font-semibold text-white mb-2">Keine Tickets gefunden</h3>
      <p class="text-gray-400 mb-6">
        {{ activeFiltersCount > 0 ? 'Versuchen Sie andere Filteroptionen.' : 'Erstellen Sie Ihr erstes Ticket.' }}
      </p>
      <button
        v-if="activeFiltersCount === 0"
        @click="showCreateModal = true"
        class="px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors inline-flex items-center gap-2"
      >
        <PlusIcon class="w-5 h-5" />
        Ticket erstellen
      </button>
      <button
        v-else
        @click="clearFilters"
        class="px-6 py-3 bg-dark-600 text-white rounded-lg hover:bg-dark-500 transition-colors"
      >
        Filter zurücksetzen
      </button>
    </div>

    <!-- Create Ticket Modal -->
    <Teleport to="body">
      <div
        v-if="showCreateModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">Neues Ticket erstellen</h2>
            <button
              @click="showCreateModal = false; resetForm()"
              class="p-1 text-gray-400 hover:text-white rounded"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4 overflow-y-auto">
            <!-- Title -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Titel *</label>
              <input
                v-model="form.title"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Kurze Beschreibung des Problems..."
              />
            </div>

            <!-- Description -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung *</label>
              <textarea
                v-model="form.description"
                rows="5"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 resize-none"
                placeholder="Detaillierte Beschreibung des Problems, Schritte zur Reproduktion, etc..."
              ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <!-- Category -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Kategorie</label>
                <select
                  v-model="form.category_id"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                >
                  <option value="">Keine Kategorie</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">
                    {{ c.name }}
                  </option>
                </select>
              </div>

              <!-- Priority -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Priorität</label>
                <select
                  v-model="form.priority"
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
            <button
              @click="showCreateModal = false; resetForm()"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="createTicket"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
            >
              Ticket erstellen
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Public Link Modal -->
    <Teleport to="body">
      <div
        v-if="showPublicLinkModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-lg overflow-hidden">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">Öffentlicher Support-Link</h2>
            <button
              @click="showPublicLinkModal = false"
              class="p-1 text-gray-400 hover:text-white rounded"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-6 space-y-4">
            <p class="text-gray-400 text-sm">
              Teilen Sie diesen Link mit Ihren Kunden, damit diese Support-Tickets erstellen können, ohne sich anzumelden.
            </p>

            <div class="flex items-center gap-2">
              <input
                :value="publicTicketUrl"
                readonly
                class="flex-1 bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white font-mono text-sm focus:outline-none"
              />
              <button
                @click="copyPublicLink"
                class="p-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
                title="Link kopieren"
              >
                <ClipboardDocumentIcon class="w-5 h-5" />
              </button>
            </div>

            <div class="bg-dark-700/50 border border-dark-600 rounded-lg p-4">
              <h4 class="text-sm font-medium text-white mb-2">So funktioniert es:</h4>
              <ul class="text-sm text-gray-400 space-y-1">
                <li>1. Kunden öffnen den Link</li>
                <li>2. Geben Name, E-Mail und Anliegen ein</li>
                <li>3. Erhalten einen Zugriffscode zum Verfolgen</li>
                <li>4. Tickets erscheinen hier in Ihrer Übersicht</li>
              </ul>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-dark-700">
            <button
              @click="showPublicLinkModal = false"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Schließen
            </button>
            <button
              @click="openPublicTicketPage"
              class="px-4 py-2 bg-dark-600 text-white rounded-lg hover:bg-dark-500 transition-colors flex items-center gap-2"
            >
              <LinkIcon class="w-4 h-4" />
              Seite öffnen
            </button>
            <button
              @click="copyPublicLink"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors flex items-center gap-2"
            >
              <ClipboardDocumentIcon class="w-4 h-4" />
              Link kopieren
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
