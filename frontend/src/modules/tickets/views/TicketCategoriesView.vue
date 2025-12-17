<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  PlusIcon,
  PencilIcon,
  TrashIcon,
  XMarkIcon,
  FolderIcon,
  ChevronRightIcon,
  ChevronDownIcon,
  Bars3Icon,
} from '@heroicons/vue/24/outline'
import draggable from 'vuedraggable'

const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// State
const categories = ref([])
const flatCategories = ref([])
const loading = ref(true)
const showModal = ref(false)
const editingCategory = ref(null)

// Form
const form = ref({
  name: '',
  description: '',
  parent_id: '',
  color: '#6366f1',
  icon: 'ticket',
  sla_response_hours: null,
  sla_resolution_hours: null,
  is_active: true,
  sort_order: 0,
})

// Colors
const colors = [
  '#6366f1', '#8B5CF6', '#EC4899', '#EF4444', '#F97316',
  '#EAB308', '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
]

// Icons
const icons = [
  'ticket', 'chat-bubble-left', 'wrench-screwdriver', 'currency-euro',
  'light-bulb', 'bug-ant', 'cog', 'document-text', 'folder',
  'question-mark-circle', 'shield-check', 'server',
]

// Expanded categories for tree view
const expandedCategories = ref([])

// Fetch categories
async function fetchCategories() {
  loading.value = true
  try {
    // Fetch nested
    const nestedResponse = await api.get('/api/v1/tickets/categories', { params: { nested: true, all: true } })
    categories.value = nestedResponse.data.data.categories || []

    // Fetch flat for parent selection
    const flatResponse = await api.get('/api/v1/tickets/categories', { params: { all: true } })
    flatCategories.value = flatResponse.data.data.categories || []
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Kategorien')
  } finally {
    loading.value = false
  }
}

// Open modal
function openModal(category = null) {
  editingCategory.value = category
  if (category) {
    form.value = {
      name: category.name,
      description: category.description || '',
      parent_id: category.parent_id || '',
      color: category.color || '#6366f1',
      icon: category.icon || 'ticket',
      sla_response_hours: category.sla_response_hours,
      sla_resolution_hours: category.sla_resolution_hours,
      is_active: !!category.is_active,
      sort_order: category.sort_order || 0,
    }
  } else {
    form.value = {
      name: '',
      description: '',
      parent_id: '',
      color: '#6366f1',
      icon: 'ticket',
      sla_response_hours: null,
      sla_resolution_hours: null,
      is_active: true,
      sort_order: 0,
    }
  }
  showModal.value = true
}

// Save category
async function saveCategory() {
  if (!form.value.name.trim()) {
    uiStore.showError('Name ist erforderlich')
    return
  }

  try {
    const data = {
      ...form.value,
      parent_id: form.value.parent_id || null,
      is_active: form.value.is_active ? 1 : 0,
    }

    if (editingCategory.value) {
      await api.put(`/api/v1/tickets/categories/${editingCategory.value.id}`, data)
      uiStore.showSuccess('Kategorie aktualisiert')
    } else {
      await api.post('/api/v1/tickets/categories', data)
      uiStore.showSuccess('Kategorie erstellt')
    }
    showModal.value = false
    fetchCategories()
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Speichern')
  }
}

// Delete category
async function deleteCategory(category) {
  if (!await confirm({ message: `Kategorie "${category.name}" wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await api.delete(`/api/v1/tickets/categories/${category.id}`)
    uiStore.showSuccess('Kategorie gelöscht')
    fetchCategories()
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Toggle expand
function toggleExpand(categoryId) {
  const index = expandedCategories.value.indexOf(categoryId)
  if (index >= 0) {
    expandedCategories.value.splice(index, 1)
  } else {
    expandedCategories.value.push(categoryId)
  }
}

// Check if expanded
function isExpanded(categoryId) {
  return expandedCategories.value.includes(categoryId)
}

// Reorder categories
async function onReorder() {
  const order = categories.value.map(c => c.id)
  try {
    await api.post('/api/v1/tickets/categories/reorder', { order })
  } catch (error) {
    uiStore.showError('Fehler beim Sortieren')
    fetchCategories()
  }
}

// Get available parents (exclude self and children)
function getAvailableParents(excludeId = null) {
  if (!excludeId) return flatCategories.value
  return flatCategories.value.filter(c => c.id !== excludeId)
}

onMounted(() => {
  fetchCategories()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-white">Ticket-Kategorien</h1>
        <p class="text-gray-400 text-sm mt-1">Kategorien für Support-Tickets verwalten</p>
      </div>
      <button
        @click="openModal()"
        class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors flex items-center gap-2"
      >
        <PlusIcon class="w-5 h-5" />
        <span>Neue Kategorie</span>
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Categories List -->
    <div v-else-if="categories.length > 0" class="bg-dark-800 border border-dark-700 rounded-xl overflow-hidden">
      <draggable
        v-model="categories"
        item-key="id"
        handle=".drag-handle"
        @end="onReorder"
      >
        <template #item="{ element: category }">
          <div class="border-b border-dark-700 last:border-b-0">
            <!-- Category Row -->
            <div class="flex items-center gap-3 px-4 py-3 hover:bg-dark-700/50 transition-colors">
              <!-- Drag handle -->
              <div class="drag-handle cursor-move text-gray-500 hover:text-gray-400">
                <Bars3Icon class="w-5 h-5" />
              </div>

              <!-- Expand button -->
              <button
                v-if="category.children?.length"
                @click="toggleExpand(category.id)"
                class="p-1 text-gray-400 hover:text-white rounded transition-colors"
              >
                <ChevronDownIcon
                  v-if="isExpanded(category.id)"
                  class="w-4 h-4"
                />
                <ChevronRightIcon v-else class="w-4 h-4" />
              </button>
              <div v-else class="w-6"></div>

              <!-- Color & Icon -->
              <div
                class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                :style="{ backgroundColor: category.color || '#6366f1' }"
              >
                <FolderIcon class="w-5 h-5 text-white" />
              </div>

              <!-- Name & Description -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <span class="font-medium text-white">{{ category.name }}</span>
                  <span
                    v-if="!category.is_active"
                    class="text-xs bg-dark-600 text-gray-400 px-2 py-0.5 rounded"
                  >
                    Inaktiv
                  </span>
                </div>
                <p v-if="category.description" class="text-sm text-gray-500 truncate">
                  {{ category.description }}
                </p>
              </div>

              <!-- SLA Info -->
              <div class="text-right text-sm">
                <div v-if="category.sla_response_hours" class="text-gray-400">
                  Antwort: {{ category.sla_response_hours }}h
                </div>
                <div v-if="category.sla_resolution_hours" class="text-gray-400">
                  Lösung: {{ category.sla_resolution_hours }}h
                </div>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-1">
                <button
                  @click="openModal(category)"
                  class="p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors"
                >
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button
                  @click="deleteCategory(category)"
                  class="p-2 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded-lg transition-colors"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Children (nested) -->
            <div v-if="category.children?.length && isExpanded(category.id)" class="pl-12 bg-dark-900/50">
              <div
                v-for="child in category.children"
                :key="child.id"
                class="flex items-center gap-3 px-4 py-3 border-t border-dark-700 hover:bg-dark-700/50 transition-colors"
              >
                <div class="w-6"></div>

                <!-- Color & Icon -->
                <div
                  class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                  :style="{ backgroundColor: child.color || '#6366f1' }"
                >
                  <FolderIcon class="w-4 h-4 text-white" />
                </div>

                <!-- Name & Description -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="font-medium text-white text-sm">{{ child.name }}</span>
                    <span
                      v-if="!child.is_active"
                      class="text-xs bg-dark-600 text-gray-400 px-2 py-0.5 rounded"
                    >
                      Inaktiv
                    </span>
                  </div>
                  <p v-if="child.description" class="text-xs text-gray-500 truncate">
                    {{ child.description }}
                  </p>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-1">
                  <button
                    @click="openModal(child)"
                    class="p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors"
                  >
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="deleteCategory(child)"
                    class="p-2 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded-lg transition-colors"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </template>
      </draggable>
    </div>

    <!-- Empty State -->
    <div v-else class="bg-dark-800 border border-dark-700 rounded-xl p-12 text-center">
      <FolderIcon class="w-16 h-16 text-gray-500 mx-auto mb-4" />
      <h3 class="text-xl font-semibold text-white mb-2">Keine Kategorien</h3>
      <p class="text-gray-400 mb-6">Erstellen Sie Ihre erste Ticket-Kategorie.</p>
      <button
        @click="openModal()"
        class="px-6 py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors inline-flex items-center gap-2"
      >
        <PlusIcon class="w-5 h-5" />
        Kategorie erstellen
      </button>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">
              {{ editingCategory ? 'Kategorie bearbeiten' : 'Neue Kategorie' }}
            </h2>
            <button @click="showModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4 overflow-y-auto">
            <!-- Name -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Name *</label>
              <input
                v-model="form.name"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Kategoriename"
              />
            </div>

            <!-- Description -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <textarea
                v-model="form.description"
                rows="2"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 resize-none"
                placeholder="Optionale Beschreibung..."
              ></textarea>
            </div>

            <!-- Parent Category -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Übergeordnete Kategorie</label>
              <select
                v-model="form.parent_id"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
              >
                <option value="">Keine (Hauptkategorie)</option>
                <option
                  v-for="c in getAvailableParents(editingCategory?.id)"
                  :key="c.id"
                  :value="c.id"
                >
                  {{ c.name }}
                </option>
              </select>
            </div>

            <!-- Color -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Farbe</label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="color in colors"
                  :key="color"
                  @click="form.color = color"
                  class="w-8 h-8 rounded-lg transition-transform hover:scale-110"
                  :class="{ 'ring-2 ring-white ring-offset-2 ring-offset-dark-800': form.color === color }"
                  :style="{ backgroundColor: color }"
                ></button>
              </div>
            </div>

            <!-- SLA -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">SLA Antwortzeit (Stunden)</label>
                <input
                  v-model.number="form.sla_response_hours"
                  type="number"
                  min="0"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                  placeholder="z.B. 4"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">SLA Lösungszeit (Stunden)</label>
                <input
                  v-model.number="form.sla_resolution_hours"
                  type="number"
                  min="0"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                  placeholder="z.B. 24"
                />
              </div>
            </div>

            <!-- Active -->
            <div>
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  v-model="form.is_active"
                  type="checkbox"
                  class="w-4 h-4 rounded bg-dark-700 border-dark-600 text-primary-600 focus:ring-primary-500"
                />
                <span class="text-gray-300">Kategorie ist aktiv</span>
              </label>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-dark-700">
            <button @click="showModal = false" class="px-4 py-2 text-gray-400 hover:text-white transition-colors">
              Abbrechen
            </button>
            <button @click="saveCategory" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors">
              {{ editingCategory ? 'Speichern' : 'Erstellen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
