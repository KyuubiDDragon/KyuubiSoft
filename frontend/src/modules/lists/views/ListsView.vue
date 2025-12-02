<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import draggable from 'vuedraggable'
import {
  PlusIcon,
  ListBulletIcon,
  TrashIcon,
  PencilIcon,
  CheckIcon,
  ChevronRightIcon,
  Bars3Icon
} from '@heroicons/vue/24/outline'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useProjectStore } from '@/stores/project'

const route = useRoute()
const uiStore = useUiStore()
const projectStore = useProjectStore()

// Watch for project changes
watch(() => projectStore.selectedProjectId, () => {
  loadLists()
})

// State
const lists = ref([])
const selectedList = ref(null)
const isLoading = ref(true)
const showCreateModal = ref(false)
const showEditModal = ref(false)
const newItemContent = ref('')
const isDragging = ref(false)

// Form für neue/editierte Liste
const listForm = reactive({
  title: '',
  description: '',
  type: 'todo',
  color: '#3B82F6'
})

const listTypes = [
  { value: 'todo', label: 'Todo-Liste' },
  { value: 'shopping', label: 'Einkaufsliste' },
  { value: 'project', label: 'Projekt' },
  { value: 'notes', label: 'Notizen' }
]

const colors = [
  '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
  '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16'
]

// Computed
const completedCount = computed(() => {
  if (!selectedList.value?.items) return 0
  return selectedList.value.items.filter(i => i.is_completed).length
})

const totalCount = computed(() => {
  return selectedList.value?.items?.length || 0
})

// Drag options
const dragOptions = computed(() => ({
  animation: 200,
  group: 'items',
  disabled: false,
  ghostClass: 'ghost-item',
  chosenClass: 'chosen-item',
  dragClass: 'drag-item',
  handle: '.drag-handle'
}))

// API Calls
onMounted(async () => {
  await loadLists()

  // Check for ?open=id query parameter from Dashboard
  const openId = route.query.open
  if (openId) {
    await selectList(openId)
  }
})

async function loadLists() {
  isLoading.value = true
  try {
    const params = projectStore.selectedProjectId
      ? { project_id: projectStore.selectedProjectId }
      : {}
    const response = await api.get('/api/v1/lists', { params })
    lists.value = response.data.data?.items || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Listen')
  } finally {
    isLoading.value = false
  }
}

async function createList() {
  try {
    const response = await api.post('/api/v1/lists', listForm)
    const newList = response.data.data

    // Link to selected project if one is active
    if (projectStore.selectedProjectId) {
      await projectStore.linkToSelectedProject('list', newList.id)
    }

    lists.value.unshift(newList)
    showCreateModal.value = false
    resetForm()
    uiStore.showSuccess('Liste erstellt')
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen der Liste')
  }
}

async function updateList() {
  try {
    await api.put(`/api/v1/lists/${selectedList.value.id}`, listForm)
    await loadLists()
    await selectList(selectedList.value.id)
    showEditModal.value = false
    uiStore.showSuccess('Liste aktualisiert')
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

async function deleteList(listId) {
  if (!confirm('Liste wirklich löschen?')) return

  try {
    await api.delete(`/api/v1/lists/${listId}`)
    lists.value = lists.value.filter(l => l.id !== listId)
    if (selectedList.value?.id === listId) {
      selectedList.value = null
    }
    uiStore.showSuccess('Liste gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function selectList(listId) {
  try {
    const response = await api.get(`/api/v1/lists/${listId}`)
    selectedList.value = response.data.data
    // Ensure items have sort_order
    if (selectedList.value.items) {
      selectedList.value.items = selectedList.value.items.map((item, index) => ({
        ...item,
        sort_order: item.sort_order ?? index
      }))
      selectedList.value.items.sort((a, b) => a.sort_order - b.sort_order)
    }
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Liste')
  }
}

async function addItem() {
  if (!newItemContent.value.trim()) return

  try {
    const response = await api.post(`/api/v1/lists/${selectedList.value.id}/items`, {
      content: newItemContent.value,
      sort_order: selectedList.value.items?.length || 0
    })
    selectedList.value.items.push(response.data.data)
    newItemContent.value = ''
  } catch (error) {
    uiStore.showError('Fehler beim Hinzufügen')
  }
}

async function toggleItem(item) {
  try {
    const response = await api.put(
      `/api/v1/lists/${selectedList.value.id}/items/${item.id}`,
      { is_completed: !item.is_completed }
    )
    Object.assign(item, response.data.data)
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

async function deleteItem(itemId) {
  try {
    await api.delete(`/api/v1/lists/${selectedList.value.id}/items/${itemId}`)
    selectedList.value.items = selectedList.value.items.filter(i => i.id !== itemId)
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

async function onDragEnd() {
  isDragging.value = false

  // Update sort_order for all items
  const itemOrders = selectedList.value.items.map((item, index) => ({
    id: item.id,
    sort_order: index
  }))

  try {
    await api.put(`/api/v1/lists/${selectedList.value.id}/items/reorder`, {
      items: itemOrders
    })
  } catch (error) {
    uiStore.showError('Fehler beim Speichern der Reihenfolge')
    // Reload to get correct order
    await selectList(selectedList.value.id)
  }
}

function openCreateModal() {
  resetForm()
  showCreateModal.value = true
}

function openEditModal() {
  listForm.title = selectedList.value.title
  listForm.description = selectedList.value.description || ''
  listForm.type = selectedList.value.type
  listForm.color = selectedList.value.color
  showEditModal.value = true
}

function resetForm() {
  listForm.title = ''
  listForm.description = ''
  listForm.type = 'todo'
  listForm.color = '#3B82F6'
}

function goBack() {
  selectedList.value = null
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button
          v-if="selectedList"
          @click="goBack"
          class="p-2 hover:bg-dark-700 rounded-lg transition-colors"
        >
          <ChevronRightIcon class="w-5 h-5 text-gray-400 rotate-180" />
        </button>
        <div>
          <h1 class="text-2xl font-bold text-white">
            {{ selectedList ? selectedList.title : 'Listen' }}
          </h1>
          <p class="text-gray-400 mt-1">
            {{ selectedList
              ? `${completedCount} von ${totalCount} erledigt`
              : 'Verwalte deine Listen und Aufgaben'
            }}
          </p>
        </div>
      </div>
      <div class="flex gap-2">
        <button v-if="selectedList" @click="openEditModal" class="btn-secondary">
          <PencilIcon class="w-5 h-5" />
        </button>
        <button v-if="selectedList" @click="deleteList(selectedList.id)" class="btn-secondary text-red-400">
          <TrashIcon class="w-5 h-5" />
        </button>
        <button v-if="!selectedList" @click="openCreateModal" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Neue Liste
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- List Detail View -->
    <div v-else-if="selectedList" class="space-y-4">
      <!-- Add Item -->
      <div class="flex gap-2">
        <input
          v-model="newItemContent"
          @keyup.enter="addItem"
          type="text"
          placeholder="Neuen Eintrag hinzufügen..."
          class="input flex-1"
        />
        <button @click="addItem" class="btn-primary">
          <PlusIcon class="w-5 h-5" />
        </button>
      </div>

      <!-- Drag hint -->
      <p v-if="selectedList.items?.length > 1" class="text-xs text-gray-500 flex items-center gap-1">
        <Bars3Icon class="w-4 h-4" />
        Ziehe Einträge am Griff, um sie neu zu sortieren
      </p>

      <!-- Items with Drag & Drop -->
      <draggable
        v-model="selectedList.items"
        v-bind="dragOptions"
        item-key="id"
        @start="isDragging = true"
        @end="onDragEnd"
        class="space-y-2"
      >
        <template #item="{ element: item }">
          <div
            class="flex items-center gap-3 p-4 bg-dark-800 rounded-lg border border-dark-700 group transition-all"
            :class="{ 'border-primary-500 shadow-lg shadow-primary-500/10': isDragging }"
          >
            <!-- Drag Handle -->
            <div class="drag-handle cursor-grab active:cursor-grabbing p-1 -ml-1 text-gray-600 hover:text-gray-400 transition-colors">
              <Bars3Icon class="w-5 h-5" />
            </div>

            <button
              @click="toggleItem(item)"
              class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-colors shrink-0"
              :class="item.is_completed
                ? 'bg-green-600 border-green-600'
                : 'border-gray-500 hover:border-primary-500'"
            >
              <CheckIcon v-if="item.is_completed" class="w-4 h-4 text-white" />
            </button>
            <span
              class="flex-1"
              :class="item.is_completed ? 'text-gray-500 line-through' : 'text-white'"
            >
              {{ item.content }}
            </span>
            <button
              @click="deleteItem(item.id)"
              class="opacity-0 group-hover:opacity-100 p-2 text-red-400 hover:bg-red-400/10 rounded-lg transition-all"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </template>
      </draggable>

      <div v-if="selectedList.items?.length === 0" class="text-center py-12 text-gray-400">
        Keine Einträge vorhanden. Füge den ersten hinzu!
      </div>
    </div>

    <!-- Lists Overview -->
    <template v-else>
      <!-- Empty state -->
      <div v-if="lists.length === 0" class="card p-12 text-center">
        <ListBulletIcon class="w-16 h-16 mx-auto text-gray-600 mb-4" />
        <h3 class="text-lg font-medium text-white mb-2">Keine Listen vorhanden</h3>
        <p class="text-gray-400 mb-6">Erstelle deine erste Liste, um loszulegen.</p>
        <button @click="openCreateModal" class="btn-primary">
          <PlusIcon class="w-5 h-5 mr-2" />
          Erste Liste erstellen
        </button>
      </div>

      <!-- Lists grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="list in lists"
          :key="list.id"
          @click="selectList(list.id)"
          class="card-hover p-6 cursor-pointer group"
        >
          <div class="flex items-start justify-between">
            <div
              class="w-10 h-10 rounded-lg flex items-center justify-center"
              :style="{ backgroundColor: list.color || '#3B82F6' }"
            >
              <ListBulletIcon class="w-5 h-5 text-white" />
            </div>
            <span class="badge badge-primary">{{ list.type }}</span>
          </div>
          <h3 class="text-lg font-medium text-white mt-4">{{ list.title }}</h3>
          <p class="text-gray-400 text-sm mt-1 line-clamp-2">
            {{ list.description || 'Keine Beschreibung' }}
          </p>
          <div class="flex items-center justify-between mt-4">
            <span class="text-sm text-gray-500">{{ list.item_count || 0 }} Einträge</span>
            <button
              @click.stop="deleteList(list.id)"
              class="opacity-0 group-hover:opacity-100 p-2 text-red-400 hover:bg-red-400/10 rounded-lg transition-all"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div
        v-if="showCreateModal || showEditModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        @click.self="showCreateModal = false; showEditModal = false"
      >
        <div class="bg-dark-800 rounded-xl p-6 w-full max-w-md border border-dark-700">
          <h2 class="text-xl font-bold text-white mb-6">
            {{ showEditModal ? 'Liste bearbeiten' : 'Neue Liste' }}
          </h2>

          <form @submit.prevent="showEditModal ? updateList() : createList()" class="space-y-4">
            <div>
              <label class="label">Titel</label>
              <input v-model="listForm.title" type="text" class="input" placeholder="Listenname" required />
            </div>

            <div>
              <label class="label">Beschreibung</label>
              <textarea v-model="listForm.description" class="input" rows="2" placeholder="Optional"></textarea>
            </div>

            <div>
              <label class="label">Typ</label>
              <select v-model="listForm.type" class="input">
                <option v-for="type in listTypes" :key="type.value" :value="type.value">
                  {{ type.label }}
                </option>
              </select>
            </div>

            <div>
              <label class="label">Farbe</label>
              <div class="flex gap-2">
                <button
                  v-for="color in colors"
                  :key="color"
                  type="button"
                  @click="listForm.color = color"
                  class="w-8 h-8 rounded-lg border-2 transition-transform hover:scale-110"
                  :class="listForm.color === color ? 'border-white scale-110' : 'border-transparent'"
                  :style="{ backgroundColor: color }"
                ></button>
              </div>
            </div>

            <div class="flex gap-3 pt-4">
              <button
                type="button"
                @click="showCreateModal = false; showEditModal = false"
                class="btn-secondary flex-1"
              >
                Abbrechen
              </button>
              <button type="submit" class="btn-primary flex-1">
                {{ showEditModal ? 'Speichern' : 'Erstellen' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.ghost-item {
  @apply opacity-50 bg-primary-600/20 border-primary-500;
}

.chosen-item {
  @apply shadow-lg shadow-primary-500/20;
}

.drag-item {
  @apply opacity-90;
}
</style>
