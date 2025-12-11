<script setup>
import { ref, computed, onMounted } from 'vue'
import { useInboxStore } from '@/stores/inbox'
import {
  InboxArrowDownIcon,
  TrashIcon,
  PencilIcon,
  CheckIcon,
  XMarkIcon,
  FunnelIcon,
  MagnifyingGlassIcon,
  ArrowRightIcon,
  ListBulletIcon,
  DocumentTextIcon,
  ViewColumnsIcon,
  CalendarIcon,
  TagIcon,
  BellIcon,
  ChevronDownIcon,
  ExclamationTriangleIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline'
import api from '@/core/api/axios'

const inboxStore = useInboxStore()

// State
const selectedItems = ref([])
const editingItem = ref(null)
const showMoveModal = ref(false)
const moveTargetType = ref('list')
const moveTargetId = ref(null)
const moveOptions = ref({})
const lists = ref([])
const boards = ref([])
const searchQuery = ref('')
const filterPriority = ref(null)
const filterStatus = ref(null)
const sortBy = ref('newest')

// Move targets
const moveTargets = [
  { type: 'list', label: 'Aufgabenliste', icon: ListBulletIcon },
  { type: 'document', label: 'Dokument', icon: DocumentTextIcon },
  { type: 'kanban', label: 'Kanban-Board', icon: ViewColumnsIcon },
  { type: 'calendar', label: 'Kalender', icon: CalendarIcon },
]

const priorities = [
  { value: 'low', label: 'Niedrig', color: 'text-gray-400', bg: 'bg-gray-500/10' },
  { value: 'normal', label: 'Normal', color: 'text-blue-400', bg: 'bg-blue-500/10' },
  { value: 'high', label: 'Hoch', color: 'text-orange-400', bg: 'bg-orange-500/10' },
  { value: 'urgent', label: 'Dringend', color: 'text-red-400', bg: 'bg-red-500/10' },
]

const sortOptions = [
  { value: 'newest', label: 'Neueste zuerst' },
  { value: 'oldest', label: 'Alteste zuerst' },
  { value: 'priority', label: 'Nach Prioritat' },
]

// Computed
const filteredItems = computed(() => {
  let items = [...inboxStore.items]

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    items = items.filter(item =>
      item.content.toLowerCase().includes(query) ||
      (item.note && item.note.toLowerCase().includes(query))
    )
  }

  if (filterPriority.value) {
    items = items.filter(item => item.priority === filterPriority.value)
  }

  if (filterStatus.value) {
    items = items.filter(item => item.status === filterStatus.value)
  }

  return items
})

const allSelected = computed(() => {
  return filteredItems.value.length > 0 && selectedItems.value.length === filteredItems.value.length
})

// API calls
onMounted(async () => {
  await Promise.all([
    inboxStore.fetchItems(),
    inboxStore.fetchStats(),
    loadMoveTargets()
  ])
})

async function loadMoveTargets() {
  try {
    const [listsRes, boardsRes] = await Promise.all([
      api.get('/api/v1/lists'),
      api.get('/api/v1/kanban/boards')
    ])
    lists.value = listsRes.data.data?.items || listsRes.data?.items || []
    boards.value = boardsRes.data.data?.items || boardsRes.data?.items || []
  } catch (error) {
    console.error('Failed to load move targets:', error)
  }
}

async function refreshItems() {
  inboxStore.setFilter('search', searchQuery.value)
  inboxStore.setFilter('priority', filterPriority.value)
  inboxStore.setFilter('status', filterStatus.value)
  inboxStore.setFilter('sort', sortBy.value)
  await inboxStore.fetchItems()
}

async function deleteItem(id) {
  if (confirm('Mochtest du diesen Eintrag wirklich loschen?')) {
    await inboxStore.deleteItem(id)
  }
}

async function updateItem(item) {
  await inboxStore.updateItem(item.id, {
    content: item.content,
    note: item.note,
    priority: item.priority,
  })
  editingItem.value = null
}

function toggleSelectAll() {
  if (allSelected.value) {
    selectedItems.value = []
  } else {
    selectedItems.value = filteredItems.value.map(item => item.id)
  }
}

function toggleSelect(id) {
  const index = selectedItems.value.indexOf(id)
  if (index > -1) {
    selectedItems.value.splice(index, 1)
  } else {
    selectedItems.value.push(id)
  }
}

function openMoveModal(itemId = null) {
  if (itemId) {
    selectedItems.value = [itemId]
  }
  if (selectedItems.value.length === 0) return
  showMoveModal.value = true
}

async function confirmMove() {
  if (selectedItems.value.length === 0) return

  try {
    if (selectedItems.value.length === 1) {
      await inboxStore.moveToModule(
        selectedItems.value[0],
        moveTargetType.value,
        moveTargetId.value,
        moveOptions.value
      )
    } else {
      await inboxStore.bulkAction(selectedItems.value, 'move', {
        target_type: moveTargetType.value,
        target_id: moveTargetId.value,
        options: moveOptions.value
      })
    }
    selectedItems.value = []
    showMoveModal.value = false
    moveTargetType.value = 'list'
    moveTargetId.value = null
    moveOptions.value = {}
  } catch (error) {
    console.error('Failed to move items:', error)
  }
}

async function bulkDelete() {
  if (selectedItems.value.length === 0) return
  if (confirm(`Mochtest du ${selectedItems.value.length} Eintrage wirklich loschen?`)) {
    await inboxStore.bulkAction(selectedItems.value, 'delete')
    selectedItems.value = []
  }
}

async function bulkArchive() {
  if (selectedItems.value.length === 0) return
  await inboxStore.bulkAction(selectedItems.value, 'archive')
  selectedItems.value = []
}

function getPriorityInfo(priority) {
  return priorities.find(p => p.value === priority) || priorities[1]
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>

<template>
  <div class="min-h-screen bg-dark-900 p-6">
    <!-- Header -->
    <div class="mb-6">
      <div class="flex items-center gap-3 mb-2">
        <InboxArrowDownIcon class="w-8 h-8 text-indigo-400" />
        <h1 class="text-2xl font-bold text-white">Inbox</h1>
        <span class="px-2 py-0.5 bg-indigo-900/30 text-indigo-300 rounded-full text-sm">
          {{ inboxStore.stats.inbox }} Eintrage
        </span>
        <span v-if="inboxStore.stats.urgent > 0" class="px-2 py-0.5 bg-red-900/30 text-red-300 rounded-full text-sm flex items-center gap-1">
          <ExclamationTriangleIcon class="w-3 h-3" />
          {{ inboxStore.stats.urgent }} dringend
        </span>
      </div>
      <p class="text-gray-400">Erfasste Gedanken und Aufgaben - hier sortierst du sie.</p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div class="bg-dark-800 rounded-lg p-4 border border-dark-600">
        <div class="text-2xl font-bold text-white">{{ inboxStore.stats.total }}</div>
        <div class="text-sm text-gray-400">Gesamt</div>
      </div>
      <div class="bg-dark-800 rounded-lg p-4 border border-dark-600">
        <div class="text-2xl font-bold text-indigo-400">{{ inboxStore.stats.inbox }}</div>
        <div class="text-sm text-gray-400">Im Inbox</div>
      </div>
      <div class="bg-dark-800 rounded-lg p-4 border border-dark-600">
        <div class="text-2xl font-bold text-yellow-400">{{ inboxStore.stats.processing }}</div>
        <div class="text-sm text-gray-400">In Bearbeitung</div>
      </div>
      <div class="bg-dark-800 rounded-lg p-4 border border-dark-600">
        <div class="text-2xl font-bold text-green-400">{{ inboxStore.stats.done }}</div>
        <div class="text-sm text-gray-400">Erledigt</div>
      </div>
    </div>

    <!-- Filters & Actions -->
    <div class="flex items-center justify-between mb-4 gap-4">
      <div class="flex items-center gap-3 flex-1">
        <!-- Search -->
        <div class="relative flex-1 max-w-md">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" />
          <input
            v-model="searchQuery"
            @input="refreshItems"
            type="text"
            placeholder="Suchen..."
            class="w-full pl-10 pr-4 py-2 bg-dark-800 border border-dark-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500"
          />
        </div>

        <!-- Priority Filter -->
        <select
          v-model="filterPriority"
          @change="refreshItems"
          class="px-3 py-2 bg-dark-800 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-indigo-500"
        >
          <option :value="null">Alle Prioritaten</option>
          <option v-for="p in priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
        </select>

        <!-- Sort -->
        <select
          v-model="sortBy"
          @change="refreshItems"
          class="px-3 py-2 bg-dark-800 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-indigo-500"
        >
          <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>

      <!-- Bulk Actions -->
      <div v-if="selectedItems.length > 0" class="flex items-center gap-2">
        <span class="text-sm text-gray-400">{{ selectedItems.length }} ausgewahlt</span>
        <button
          @click="openMoveModal()"
          class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 rounded-lg text-sm text-white"
        >
          Verschieben
        </button>
        <button
          @click="bulkArchive"
          class="px-3 py-1.5 bg-dark-700 hover:bg-dark-600 rounded-lg text-sm text-white"
        >
          Archivieren
        </button>
        <button
          @click="bulkDelete"
          class="px-3 py-1.5 bg-red-600/20 hover:bg-red-600/30 rounded-lg text-sm text-red-400"
        >
          Loschen
        </button>
      </div>
    </div>

    <!-- Items List -->
    <div class="space-y-2">
      <!-- Select All Header -->
      <div class="flex items-center gap-3 px-4 py-2 bg-dark-800/50 rounded-lg text-sm text-gray-400">
        <input
          type="checkbox"
          :checked="allSelected"
          @change="toggleSelectAll"
          class="w-4 h-4 rounded bg-dark-700 border-dark-500 text-indigo-600 focus:ring-indigo-500"
        />
        <span class="flex-1">Inhalt</span>
        <span class="w-24 text-center">Prioritat</span>
        <span class="w-32 text-center">Erstellt</span>
        <span class="w-24 text-center">Aktionen</span>
      </div>

      <!-- Loading -->
      <div v-if="inboxStore.loading" class="flex items-center justify-center py-12">
        <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredItems.length === 0" class="text-center py-12">
        <InboxArrowDownIcon class="w-16 h-16 text-gray-600 mx-auto mb-4" />
        <h3 class="text-lg font-medium text-gray-400 mb-2">Inbox ist leer</h3>
        <p class="text-gray-500">Nutze Quick Capture um Gedanken schnell zu erfassen.</p>
      </div>

      <!-- Items -->
      <div
        v-for="item in filteredItems"
        :key="item.id"
        class="flex items-start gap-3 p-4 bg-dark-800 rounded-lg border border-dark-600 hover:border-dark-500 transition-colors"
        :class="{ 'ring-2 ring-indigo-500': selectedItems.includes(item.id) }"
      >
        <input
          type="checkbox"
          :checked="selectedItems.includes(item.id)"
          @change="toggleSelect(item.id)"
          class="mt-1 w-4 h-4 rounded bg-dark-700 border-dark-500 text-indigo-600 focus:ring-indigo-500"
        />

        <div class="flex-1 min-w-0">
          <!-- Editing Mode -->
          <template v-if="editingItem === item.id">
            <textarea
              v-model="item.content"
              rows="2"
              class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white resize-none focus:outline-none focus:border-indigo-500"
            ></textarea>
            <div class="flex items-center gap-2 mt-2">
              <select
                v-model="item.priority"
                class="px-2 py-1 bg-dark-700 border border-dark-600 rounded text-sm text-white"
              >
                <option v-for="p in priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
              </select>
              <button
                @click="updateItem(item)"
                class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 rounded text-sm text-white"
              >
                Speichern
              </button>
              <button
                @click="editingItem = null"
                class="px-3 py-1 bg-dark-700 hover:bg-dark-600 rounded text-sm text-white"
              >
                Abbrechen
              </button>
            </div>
          </template>

          <!-- Display Mode -->
          <template v-else>
            <p class="text-white">{{ item.content }}</p>
            <p v-if="item.note" class="text-sm text-gray-400 mt-1">{{ item.note }}</p>

            <!-- Tags -->
            <div v-if="item.tags && item.tags.length > 0" class="flex items-center gap-1 mt-2">
              <TagIcon class="w-3 h-3 text-gray-500" />
              <span
                v-for="tag in item.tags"
                :key="tag"
                class="px-2 py-0.5 bg-dark-700 text-gray-400 rounded-full text-xs"
              >
                {{ tag }}
              </span>
            </div>

            <!-- Reminder -->
            <div v-if="item.reminder_at" class="flex items-center gap-1 mt-2 text-xs text-yellow-400">
              <BellIcon class="w-3 h-3" />
              <span>Erinnerung: {{ formatDate(item.reminder_at) }}</span>
            </div>
          </template>
        </div>

        <!-- Priority -->
        <div class="w-24 text-center">
          <span
            class="inline-flex items-center px-2 py-1 rounded-full text-xs"
            :class="[getPriorityInfo(item.priority).color, getPriorityInfo(item.priority).bg]"
          >
            {{ getPriorityInfo(item.priority).label }}
          </span>
        </div>

        <!-- Date -->
        <div class="w-32 text-center text-sm text-gray-500">
          {{ formatDate(item.created_at) }}
        </div>

        <!-- Actions -->
        <div class="w-24 flex items-center justify-center gap-1">
          <button
            @click="editingItem = item.id"
            class="p-1.5 text-gray-400 hover:text-white rounded transition-colors"
            title="Bearbeiten"
          >
            <PencilIcon class="w-4 h-4" />
          </button>
          <button
            @click="openMoveModal(item.id)"
            class="p-1.5 text-gray-400 hover:text-indigo-400 rounded transition-colors"
            title="Verschieben"
          >
            <ArrowRightIcon class="w-4 h-4" />
          </button>
          <button
            @click="deleteItem(item.id)"
            class="p-1.5 text-gray-400 hover:text-red-400 rounded transition-colors"
            title="Loschen"
          >
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Move Modal -->
    <Teleport to="body">
      <div v-if="showMoveModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/60" @click="showMoveModal = false"></div>
        <div class="relative bg-dark-800 rounded-xl border border-dark-600 p-6 w-full max-w-md">
          <h3 class="text-lg font-semibold text-white mb-4">Verschieben nach</h3>

          <!-- Target Type Selection -->
          <div class="grid grid-cols-2 gap-2 mb-4">
            <button
              v-for="target in moveTargets"
              :key="target.type"
              @click="moveTargetType = target.type"
              class="flex items-center gap-2 p-3 rounded-lg border transition-colors"
              :class="moveTargetType === target.type
                ? 'bg-indigo-600/20 border-indigo-500 text-indigo-300'
                : 'bg-dark-700 border-dark-600 text-gray-400 hover:border-dark-500'"
            >
              <component :is="target.icon" class="w-5 h-5" />
              <span class="text-sm">{{ target.label }}</span>
            </button>
          </div>

          <!-- Target Selection -->
          <div v-if="moveTargetType === 'list'" class="mb-4">
            <label class="block text-sm text-gray-400 mb-2">Liste auswahlen</label>
            <select
              v-model="moveTargetId"
              class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
            >
              <option :value="null">Neue Inbox-Liste erstellen</option>
              <option v-for="list in lists" :key="list.id" :value="list.id">{{ list.title }}</option>
            </select>
          </div>

          <div v-if="moveTargetType === 'kanban'" class="mb-4">
            <label class="block text-sm text-gray-400 mb-2">Board auswahlen</label>
            <select
              v-model="moveTargetId"
              class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
            >
              <option :value="null" disabled>Board auswahlen...</option>
              <option v-for="board in boards" :key="board.id" :value="board.id">{{ board.title }}</option>
            </select>
          </div>

          <div v-if="moveTargetType === 'calendar'" class="mb-4">
            <label class="block text-sm text-gray-400 mb-2">Datum</label>
            <input
              v-model="moveOptions.start_date"
              type="datetime-local"
              class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white"
            />
          </div>

          <div v-if="moveTargetType === 'document'" class="mb-4">
            <label class="block text-sm text-gray-400 mb-2">Dokumenttitel</label>
            <input
              v-model="moveOptions.title"
              type="text"
              placeholder="Optional - wird aus Inhalt generiert"
              class="w-full px-3 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-500"
            />
          </div>

          <!-- Actions -->
          <div class="flex justify-end gap-2">
            <button
              @click="showMoveModal = false"
              class="px-4 py-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-white"
            >
              Abbrechen
            </button>
            <button
              @click="confirmMove"
              :disabled="moveTargetType === 'kanban' && !moveTargetId"
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 rounded-lg text-white"
            >
              Verschieben
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
