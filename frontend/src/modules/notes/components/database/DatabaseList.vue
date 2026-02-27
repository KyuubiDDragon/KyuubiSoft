<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useDatabaseStore } from '../../stores/databaseStore'
import { useUiStore } from '@/stores/ui'
import DatabaseCell from './DatabaseCell.vue'
import {
  PlusIcon,
  ChevronRightIcon,
  TrashIcon,
  CheckIcon
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
const expandedRows = ref(new Set())

// Computed
const visibleProperties = computed(() => {
  if (!database.value?.properties) return []
  return database.value.properties.filter(p => p.is_visible && !p.is_primary)
})

const titleProperty = computed(() => {
  if (!database.value?.properties) return null
  return database.value.properties.find(p => p.is_primary)
})

const rows = computed(() => database.value?.rows || [])

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

// Toggle row expansion
function toggleRow(rowId) {
  if (expandedRows.value.has(rowId)) {
    expandedRows.value.delete(rowId)
  } else {
    expandedRows.value.add(rowId)
  }
  expandedRows.value = new Set(expandedRows.value)
}

// Get row title
function getRowTitle(row) {
  if (!titleProperty.value) return 'Ohne Titel'
  return row.cells?.[titleProperty.value.id]?.value || 'Ohne Titel'
}

// Add new row
async function addRow() {
  try {
    await databaseStore.addRow(props.databaseId)
    await loadDatabase()
    emit('updated')
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen')
  }
}

// Update cell
async function handleCellUpdate(rowId, propertyId, value) {
  try {
    await databaseStore.updateRow(props.databaseId, rowId, { [propertyId]: value })
    await loadDatabase()
    emit('updated')
  } catch (error) {
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

// Delete row
async function deleteRow(rowId, event) {
  event.stopPropagation()

  try {
    await databaseStore.deleteRow(props.databaseId, rowId)
    await loadDatabase()
    emit('updated')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Check if row has a checkbox property that is checked
function hasCheckbox(row) {
  const checkboxProp = database.value?.properties?.find(p => p.type === 'checkbox')
  if (!checkboxProp) return null
  return row.cells?.[checkboxProp.id]?.value || false
}

// Get checkbox property
const checkboxProperty = computed(() => {
  if (!database.value?.properties) return null
  return database.value.properties.find(p => p.type === 'checkbox')
})

// Toggle checkbox
async function toggleCheckbox(row, event) {
  event.stopPropagation()

  if (!checkboxProperty.value) return

  const currentValue = row.cells?.[checkboxProperty.value.id]?.value || false
  await handleCellUpdate(row.id, checkboxProperty.value.id, !currentValue)
}

// Get color for select value
function getSelectColor(property, value) {
  const option = property.config?.options?.find(o => o.id === value)
  return option?.color || 'gray'
}

// Format cell value preview
function formatPreview(property, row) {
  const cell = row.cells?.[property.id]
  if (!cell?.value) return null

  switch (property.type) {
    case 'select':
      const option = property.config?.options?.find(o => o.id === cell.value)
      return option ? { type: 'select', name: option.name, color: option.color } : null
    case 'date':
      return { type: 'text', value: new Date(cell.value).toLocaleDateString('de-DE') }
    case 'checkbox':
      return null // Handled separately
    case 'url':
      return { type: 'url', value: cell.value }
    default:
      const value = String(cell.value)
      return value.length > 30 ? { type: 'text', value: value.substring(0, 30) + '...' } : { type: 'text', value }
  }
}
</script>

<template>
  <div class="database-list">
    <!-- Loading -->
    <div v-if="isLoading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
    </div>

    <!-- List -->
    <div v-else class="divide-y divide-white/[0.06]">
      <!-- List items -->
      <div
        v-for="row in rows"
        :key="row.id"
        class="list-item"
      >
        <!-- Row header (always visible) -->
        <div
          @click="toggleRow(row.id)"
          :class="[
            'flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-white/[0.04] transition-colors group',
            hasCheckbox(row) ? 'opacity-60' : ''
          ]"
        >
          <!-- Expand icon -->
          <ChevronRightIcon
            :class="[
              'w-4 h-4 text-gray-500 transition-transform flex-shrink-0',
              expandedRows.has(row.id) ? 'transform rotate-90' : ''
            ]"
          />

          <!-- Checkbox (if exists) -->
          <button
            v-if="checkboxProperty"
            @click="toggleCheckbox(row, $event)"
            :class="[
              'w-5 h-5 rounded border-2 flex items-center justify-center flex-shrink-0 transition-colors',
              hasCheckbox(row)
                ? 'bg-primary-600 border-primary-600 text-white'
                : 'border-white/[0.08] hover:border-white/[0.12]'
            ]"
          >
            <CheckIcon v-if="hasCheckbox(row)" class="w-3 h-3" />
          </button>

          <!-- Title -->
          <div :class="['flex-1 min-w-0', hasCheckbox(row) ? 'line-through' : '']">
            <span class="font-medium text-white">{{ getRowTitle(row) }}</span>
          </div>

          <!-- Preview of properties -->
          <div class="flex items-center gap-2 flex-shrink-0">
            <template v-for="prop in visibleProperties.slice(0, 3)" :key="prop.id">
              <span v-if="formatPreview(prop, row)" class="text-xs">
                <template v-if="formatPreview(prop, row).type === 'select'">
                  <span :class="[
                    'px-2 py-0.5 rounded',
                    `bg-${formatPreview(prop, row).color}-500/20 text-${formatPreview(prop, row).color}-400`
                  ]">
                    {{ formatPreview(prop, row).name }}
                  </span>
                </template>
                <template v-else-if="formatPreview(prop, row).type === 'url'">
                  <a
                    :href="formatPreview(prop, row).value"
                    target="_blank"
                    @click.stop
                    class="text-primary-400 hover:underline"
                  >
                    Link
                  </a>
                </template>
                <template v-else>
                  <span class="text-gray-400">{{ formatPreview(prop, row).value }}</span>
                </template>
              </span>
            </template>
          </div>

          <!-- Delete button -->
          <button
            @click="deleteRow(row.id, $event)"
            class="p-1 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100 flex-shrink-0"
          >
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>

        <!-- Expanded content -->
        <div
          v-if="expandedRows.has(row.id)"
          class="px-4 pb-4 pl-12 bg-white/[0.02]"
        >
          <div class="grid gap-3 py-3">
            <!-- Title property -->
            <div v-if="titleProperty" class="flex items-start gap-3">
              <span class="text-sm text-gray-500 w-28 flex-shrink-0 pt-1">
                {{ titleProperty.name }}
              </span>
              <div class="flex-1">
                <DatabaseCell
                  :property="titleProperty"
                  :row="row"
                  :database-id="databaseId"
                  @update="(val) => handleCellUpdate(row.id, titleProperty.id, val)"
                />
              </div>
            </div>

            <!-- Other properties -->
            <div
              v-for="prop in visibleProperties"
              :key="prop.id"
              class="flex items-start gap-3"
            >
              <span class="text-sm text-gray-500 w-28 flex-shrink-0 pt-1">
                {{ prop.name }}
              </span>
              <div class="flex-1">
                <DatabaseCell
                  :property="prop"
                  :row="row"
                  :database-id="databaseId"
                  @update="(val) => handleCellUpdate(row.id, prop.id, val)"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="rows.length === 0" class="text-center py-8 text-gray-500">
        <p>Keine Einträge vorhanden</p>
      </div>

      <!-- Add row button -->
      <button
        @click="addRow"
        class="w-full flex items-center gap-2 px-4 py-3 text-sm text-gray-400 hover:text-white hover:bg-white/[0.04] transition-colors"
      >
        <PlusIcon class="w-4 h-4" />
        Neuer Eintrag
      </button>
    </div>
  </div>
</template>

<style scoped>
.list-item {
  transition: background-color 0.15s ease;
}
</style>
