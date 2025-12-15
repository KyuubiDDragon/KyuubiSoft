<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useDatabaseStore } from '../../stores/databaseStore'
import { useUiStore } from '@/stores/ui'
import {
  PlusIcon,
  EllipsisHorizontalIcon,
  TrashIcon,
  PencilIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  databaseId: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['updated'])

const databaseStore = useDatabaseStore()
const uiStore = useUiStore()

const isLoading = ref(true)
const database = ref(null)
const groupByProperty = ref(null)
const showColumnMenu = ref(null)
const editingColumnId = ref(null)
const editingColumnName = ref('')

// Find the first select/multi_select property for grouping
const groupProperty = computed(() => {
  if (!database.value?.properties) return null
  return database.value.properties.find(p =>
    ['select', 'multi_select'].includes(p.type) && p.is_visible
  )
})

// Get options for grouping
const groupOptions = computed(() => {
  if (!groupProperty.value?.config?.options) return []
  return groupProperty.value.config.options
})

// Group rows by the select property
const groupedRows = computed(() => {
  if (!database.value?.rows || !groupProperty.value) {
    return { ungrouped: database.value?.rows || [] }
  }

  const groups = {}
  const propertyId = groupProperty.value.id

  // Initialize groups for each option
  groupOptions.value.forEach(option => {
    groups[option.id] = {
      option,
      rows: []
    }
  })

  // Add ungrouped column
  groups['_ungrouped'] = {
    option: { id: '_ungrouped', name: 'Ohne Status', color: 'gray' },
    rows: []
  }

  // Sort rows into groups
  database.value.rows.forEach(row => {
    const cellValue = row.cells?.[propertyId]?.value
    if (cellValue && groups[cellValue]) {
      groups[cellValue].rows.push(row)
    } else {
      groups['_ungrouped'].rows.push(row)
    }
  })

  return groups
})

// Load database
onMounted(async () => {
  await loadDatabase()
})

watch(() => props.databaseId, async () => {
  await loadDatabase()
})

async function loadDatabase() {
  isLoading.value = true
  try {
    database.value = await databaseStore.fetchDatabase(props.databaseId)
  } catch (error) {
    console.error('Error loading database:', error)
    uiStore.showError('Fehler beim Laden der Datenbank')
  } finally {
    isLoading.value = false
  }
}

// Add new row to a group
async function addRow(groupId) {
  try {
    const initialData = {}

    // Set the group property value if not ungrouped
    if (groupId !== '_ungrouped' && groupProperty.value) {
      initialData[groupProperty.value.id] = groupId
    }

    await databaseStore.addRow(props.databaseId, initialData)
    await loadDatabase()
    emit('updated')
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen')
  }
}

// Update row cell
async function updateRowCell(rowId, propertyId, value) {
  try {
    await databaseStore.updateRow(props.databaseId, rowId, { [propertyId]: value })
    await loadDatabase()
    emit('updated')
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

// Delete row
async function deleteRow(rowId) {
  try {
    await databaseStore.deleteRow(props.databaseId, rowId)
    await loadDatabase()
    emit('updated')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Move card to different group
async function moveCard(rowId, newGroupId) {
  if (!groupProperty.value) return

  try {
    const value = newGroupId === '_ungrouped' ? null : newGroupId
    await databaseStore.updateRow(props.databaseId, rowId, {
      [groupProperty.value.id]: value
    })
    await loadDatabase()
    emit('updated')
  } catch (error) {
    uiStore.showError('Fehler beim Verschieben')
  }
}

// Get title property
const titleProperty = computed(() => {
  if (!database.value?.properties) return null
  return database.value.properties.find(p => p.is_primary)
})

// Get display title for row
function getRowTitle(row) {
  if (!titleProperty.value) return 'Ohne Titel'
  return row.cells?.[titleProperty.value.id]?.value || 'Ohne Titel'
}

// Get visible properties for card (excluding group and title)
const cardProperties = computed(() => {
  if (!database.value?.properties) return []
  return database.value.properties.filter(p =>
    p.is_visible &&
    !p.is_primary &&
    p.id !== groupProperty.value?.id
  ).slice(0, 3) // Limit to 3 properties on cards
})

// Format cell value for display
function formatCellValue(property, row) {
  const cell = row.cells?.[property.id]
  if (!cell?.value) return '-'

  switch (property.type) {
    case 'checkbox':
      return cell.value ? '✓' : '✗'
    case 'date':
      return new Date(cell.value).toLocaleDateString('de-DE')
    case 'select':
      const option = property.config?.options?.find(o => o.id === cell.value)
      return option?.name || cell.value
    case 'multi_select':
      if (Array.isArray(cell.value)) {
        return cell.value
          .map(id => property.config?.options?.find(o => o.id === id)?.name || id)
          .join(', ')
      }
      return cell.value
    default:
      return String(cell.value)
  }
}

// Get color class for group
function getColorClass(color) {
  const colors = {
    gray: 'bg-gray-500',
    red: 'bg-red-500',
    orange: 'bg-orange-500',
    yellow: 'bg-yellow-500',
    green: 'bg-green-500',
    blue: 'bg-blue-500',
    purple: 'bg-purple-500',
    pink: 'bg-pink-500',
  }
  return colors[color] || colors.gray
}

// Handle drag start
function handleDragStart(event, rowId) {
  event.dataTransfer.setData('text/plain', rowId)
  event.dataTransfer.effectAllowed = 'move'
}

// Handle drag over
function handleDragOver(event) {
  event.preventDefault()
  event.dataTransfer.dropEffect = 'move'
}

// Handle drop
function handleDrop(event, groupId) {
  event.preventDefault()
  const rowId = event.dataTransfer.getData('text/plain')
  if (rowId) {
    moveCard(rowId, groupId)
  }
}
</script>

<template>
  <div class="database-board h-full">
    <!-- Loading -->
    <div v-if="isLoading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
    </div>

    <!-- No group property found -->
    <div v-else-if="!groupProperty" class="text-center py-12 text-gray-500">
      <p class="mb-2">Board-Ansicht benötigt eine Select-Spalte</p>
      <p class="text-sm">Füge eine Select- oder Multi-Select-Spalte hinzu um die Board-Ansicht zu nutzen</p>
    </div>

    <!-- Board -->
    <div v-else class="flex gap-4 p-4 overflow-x-auto h-full">
      <!-- Columns -->
      <div
        v-for="(group, groupId) in groupedRows"
        :key="groupId"
        class="board-column flex-shrink-0 w-72 bg-dark-700 rounded-lg flex flex-col max-h-full"
        @dragover="handleDragOver"
        @drop="handleDrop($event, groupId)"
      >
        <!-- Column header -->
        <div class="column-header flex items-center gap-2 px-3 py-2 border-b border-dark-600">
          <span :class="['w-3 h-3 rounded-full flex-shrink-0', getColorClass(group.option.color)]"></span>
          <span class="text-sm font-medium text-white truncate flex-1">
            {{ group.option.name }}
          </span>
          <span class="text-xs text-gray-500">{{ group.rows.length }}</span>
        </div>

        <!-- Cards -->
        <div class="column-content flex-1 overflow-y-auto p-2 space-y-2">
          <div
            v-for="row in group.rows"
            :key="row.id"
            class="board-card bg-dark-800 border border-dark-600 rounded-lg p-3 cursor-grab hover:border-dark-500 transition-colors group"
            draggable="true"
            @dragstart="handleDragStart($event, row.id)"
          >
            <!-- Card title -->
            <div class="font-medium text-white mb-2 flex items-start justify-between gap-2">
              <span class="line-clamp-2">{{ getRowTitle(row) }}</span>
              <button
                @click="deleteRow(row.id)"
                class="p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 flex-shrink-0"
              >
                <TrashIcon class="w-3.5 h-3.5" />
              </button>
            </div>

            <!-- Card properties -->
            <div v-if="cardProperties.length > 0" class="space-y-1">
              <div
                v-for="prop in cardProperties"
                :key="prop.id"
                class="flex items-center gap-2 text-xs"
              >
                <span class="text-gray-500 truncate">{{ prop.name }}:</span>
                <span class="text-gray-300 truncate">{{ formatCellValue(prop, row) }}</span>
              </div>
            </div>
          </div>

          <!-- Empty state -->
          <div
            v-if="group.rows.length === 0"
            class="text-center py-4 text-gray-500 text-sm border-2 border-dashed border-dark-600 rounded-lg"
          >
            Keine Einträge
          </div>
        </div>

        <!-- Add card button -->
        <div class="p-2 border-t border-dark-600">
          <button
            @click="addRow(groupId)"
            class="w-full flex items-center justify-center gap-2 px-3 py-2 text-sm text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors"
          >
            <PlusIcon class="w-4 h-4" />
            Neu
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.board-column {
  min-height: 200px;
}

.column-content {
  scrollbar-width: thin;
  scrollbar-color: rgb(55, 65, 81) transparent;
}

.column-content::-webkit-scrollbar {
  width: 6px;
}

.column-content::-webkit-scrollbar-thumb {
  background-color: rgb(55, 65, 81);
  border-radius: 3px;
}

.board-card:active {
  cursor: grabbing;
}

.board-card.dragging {
  opacity: 0.5;
}
</style>
