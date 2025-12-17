<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useDatabaseStore } from '../../stores/databaseStore'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import DatabaseCell from './DatabaseCell.vue'
import DatabasePropertyHeader from './DatabasePropertyHeader.vue'
import {
  PlusIcon,
  EllipsisHorizontalIcon,
  TrashIcon,
  DocumentDuplicateIcon,
  AdjustmentsHorizontalIcon,
  TableCellsIcon,
  Squares2X2Icon,
  ListBulletIcon,
  CalendarIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  databaseId: {
    type: String,
    required: true
  },
  viewType: {
    type: String,
    default: 'table'
  },
  showHeader: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['change-view', 'updated'])

const databaseStore = useDatabaseStore()
const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

const isLoading = ref(true)
const selectedRows = ref(new Set())
const editingCell = ref(null)  // { rowId, propertyId }
const showAddProperty = ref(false)
const newPropertyName = ref('')
const newPropertyType = ref('text')

// Database data
const database = computed(() => databaseStore.currentDatabase)
const properties = computed(() => database.value?.properties || [])
const rows = computed(() => database.value?.rows || [])

// View icons
const viewIcons = {
  table: TableCellsIcon,
  board: Squares2X2Icon,
  list: ListBulletIcon,
  calendar: CalendarIcon,
  gallery: Squares2X2Icon
}

// Load database
async function loadDatabase() {
  isLoading.value = true
  try {
    await databaseStore.fetchDatabase(props.databaseId)
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Datenbank')
  } finally {
    isLoading.value = false
  }
}

// Add new row
async function addRow() {
  try {
    await databaseStore.addRow(props.databaseId)
    emit('updated')
  } catch (error) {
    uiStore.showError('Fehler beim Hinzufügen der Zeile')
  }
}

// Delete row
async function deleteRow(rowId) {
  if (!await confirm({ message: 'Zeile wirklich löschen?', type: 'danger', confirmText: 'Löschen' })) return
  try {
    await databaseStore.deleteRow(props.databaseId, rowId)
    emit('updated')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen der Zeile')
  }
}

// Update cell
async function updateCell(rowId, propertyId, value) {
  try {
    await databaseStore.updateRow(props.databaseId, rowId, {
      [propertyId]: value
    })
    editingCell.value = null
    emit('updated')
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

// Add property
async function addProperty() {
  if (!newPropertyName.value.trim()) {
    newPropertyName.value = 'Neue Spalte'
  }

  try {
    await databaseStore.addProperty(props.databaseId, {
      name: newPropertyName.value,
      type: newPropertyType.value
    })
    showAddProperty.value = false
    newPropertyName.value = ''
    newPropertyType.value = 'text'
    emit('updated')
  } catch (error) {
    uiStore.showError('Fehler beim Hinzufügen der Spalte')
  }
}

// Toggle row selection
function toggleRowSelection(rowId) {
  if (selectedRows.value.has(rowId)) {
    selectedRows.value.delete(rowId)
  } else {
    selectedRows.value.add(rowId)
  }
  selectedRows.value = new Set(selectedRows.value)
}

// Start editing cell
function startEdit(rowId, propertyId) {
  editingCell.value = { rowId, propertyId }
}

// Check if cell is being edited
function isEditing(rowId, propertyId) {
  return editingCell.value?.rowId === rowId && editingCell.value?.propertyId === propertyId
}

// Get property by ID
function getProperty(propertyId) {
  return properties.value.find(p => p.id === propertyId)
}

// Watch for database ID changes
watch(() => props.databaseId, () => {
  if (props.databaseId) {
    loadDatabase()
  }
}, { immediate: true })

// Click outside handler
function handleClickOutside(e) {
  if (!e.target.closest('.database-cell-editor')) {
    editingCell.value = null
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div class="database-table bg-dark-800 overflow-hidden">
    <!-- Header (optional) -->
    <div v-if="showHeader" class="flex items-center justify-between px-4 py-3 border-b border-dark-700">
      <div class="flex items-center gap-3">
        <input
          v-if="database"
          :value="database.name"
          @change="databaseStore.updateDatabase(databaseId, { name: $event.target.value })"
          class="text-lg font-semibold bg-transparent border-none focus:outline-none text-white"
        />
      </div>

      <div class="flex items-center gap-2">
        <!-- View switcher -->
        <div class="flex items-center gap-1 bg-dark-700 rounded-lg p-1">
          <button
            v-for="(icon, type) in viewIcons"
            :key="type"
            @click="$emit('change-view', type)"
            :class="[
              'p-1.5 rounded transition-colors',
              viewType === type ? 'bg-primary-600 text-white' : 'text-gray-400 hover:text-white'
            ]"
            :title="type"
          >
            <component :is="icon" class="w-4 h-4" />
          </button>
        </div>

        <!-- Options -->
        <button class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg">
          <AdjustmentsHorizontalIcon class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto">
      <table class="w-full">
        <!-- Table Header -->
        <thead>
          <tr class="border-b border-dark-700">
            <!-- Checkbox column -->
            <th class="w-10 px-2 py-2 text-left">
              <input
                type="checkbox"
                class="rounded border-dark-500 bg-dark-700 text-primary-600"
                :checked="selectedRows.size === rows.length && rows.length > 0"
                @change="selectedRows = selectedRows.size === rows.length ? new Set() : new Set(rows.map(r => r.id))"
              />
            </th>

            <!-- Property headers -->
            <th
              v-for="property in properties"
              :key="property.id"
              class="text-left text-xs font-medium text-gray-400 uppercase tracking-wider"
              :style="{ width: property.width + 'px', minWidth: property.width + 'px' }"
            >
              <DatabasePropertyHeader
                :property="property"
                :database-id="databaseId"
              />
            </th>

            <!-- Add property column -->
            <th class="w-10 px-2">
              <button
                @click="showAddProperty = true"
                class="p-1 text-gray-500 hover:text-white hover:bg-dark-700 rounded"
                title="Spalte hinzufügen"
              >
                <PlusIcon class="w-4 h-4" />
              </button>
            </th>
          </tr>
        </thead>

        <!-- Table Body -->
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            class="group border-b border-dark-700/50 hover:bg-dark-700/30"
          >
            <!-- Checkbox -->
            <td class="px-2 py-1">
              <div class="flex items-center gap-1">
                <input
                  type="checkbox"
                  class="rounded border-dark-500 bg-dark-700 text-primary-600"
                  :checked="selectedRows.has(row.id)"
                  @change="toggleRowSelection(row.id)"
                />
                <button
                  @click="deleteRow(row.id)"
                  class="p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
                  title="Löschen"
                >
                  <TrashIcon class="w-3 h-3" />
                </button>
              </div>
            </td>

            <!-- Cells -->
            <td
              v-for="property in properties"
              :key="property.id"
              class="px-2 py-1"
              :style="{ width: property.width + 'px', minWidth: property.width + 'px' }"
            >
              <DatabaseCell
                :value="row.cells[property.id]"
                :property="property"
                :is-editing="isEditing(row.id, property.id)"
                @edit="startEdit(row.id, property.id)"
                @update="(value) => updateCell(row.id, property.id, value)"
                @cancel="editingCell = null"
              />
            </td>

            <!-- Empty cell for add column -->
            <td class="px-2 py-1"></td>
          </tr>

          <!-- Add row button -->
          <tr>
            <td :colspan="properties.length + 2" class="px-2 py-1">
              <button
                @click="addRow"
                class="flex items-center gap-2 w-full px-2 py-2 text-sm text-gray-500 hover:text-white hover:bg-dark-700/50 rounded transition-colors"
              >
                <PlusIcon class="w-4 h-4" />
                Neue Zeile
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add Property Modal -->
    <Teleport to="body">
      <div
        v-if="showAddProperty"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
        @click.self="showAddProperty = false"
      >
        <div class="bg-dark-800 rounded-xl shadow-xl w-full max-w-md p-6">
          <h3 class="text-lg font-semibold text-white mb-4">Neue Spalte</h3>

          <div class="space-y-4">
            <div>
              <label class="block text-sm text-gray-400 mb-1">Name</label>
              <input
                v-model="newPropertyName"
                type="text"
                placeholder="Spaltenname"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                @keydown.enter="addProperty"
              />
            </div>

            <div>
              <label class="block text-sm text-gray-400 mb-1">Typ</label>
              <select
                v-model="newPropertyType"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              >
                <option
                  v-for="type in databaseStore.propertyTypes"
                  :key="type.value"
                  :value="type.value"
                >
                  {{ type.icon }} {{ type.label }}
                </option>
              </select>
            </div>
          </div>

          <div class="flex justify-end gap-3 mt-6">
            <button
              @click="showAddProperty = false"
              class="px-4 py-2 text-gray-400 hover:text-white"
            >
              Abbrechen
            </button>
            <button
              @click="addProperty"
              class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg"
            >
              Hinzufügen
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.database-table table {
  border-collapse: collapse;
}

.database-table th,
.database-table td {
  border-right: 1px solid rgb(39, 39, 42);
}

.database-table th:last-child,
.database-table td:last-child {
  border-right: none;
}
</style>
