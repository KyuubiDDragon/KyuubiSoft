<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { NodeViewWrapper } from '@tiptap/vue-3'
import { useDatabaseStore } from '../../stores/databaseStore'
import { useUiStore } from '@/stores/ui'
import DatabaseTable from './DatabaseTable.vue'
import DatabaseBoard from './DatabaseBoard.vue'
import DatabaseList from './DatabaseList.vue'
import DatabaseCalendarView from './DatabaseCalendarView.vue'
import DatabaseGalleryView from './DatabaseGalleryView.vue'
import {
  TableCellsIcon,
  ViewColumnsIcon,
  ListBulletIcon,
  CalendarIcon,
  PhotoIcon,
  EllipsisHorizontalIcon,
  TrashIcon,
  DocumentDuplicateIcon,
  ArrowsPointingOutIcon,
  PencilIcon,
  PlusIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  node: {
    type: Object,
    required: true
  },
  updateAttributes: {
    type: Function,
    required: true
  },
  deleteNode: {
    type: Function,
    required: true
  },
  editor: {
    type: Object,
    required: true
  },
  selected: {
    type: Boolean,
    default: false
  }
})

const databaseStore = useDatabaseStore()
const uiStore = useUiStore()

const isLoading = ref(true)
const database = ref(null)
const showMenu = ref(false)
const isRenaming = ref(false)
const renameValue = ref('')
const currentView = ref('table')
const isExpanded = ref(false)
const calendarDateProperty = ref(null)
const galleryImageProperty = ref(null)
const galleryCardSize = ref('medium')
const selectedRow = ref(null)

// View options
const viewOptions = [
  { id: 'table', icon: TableCellsIcon, label: 'Tabelle' },
  { id: 'board', icon: ViewColumnsIcon, label: 'Board' },
  { id: 'list', icon: ListBulletIcon, label: 'Liste' },
  { id: 'calendar', icon: CalendarIcon, label: 'Kalender' },
  { id: 'gallery', icon: PhotoIcon, label: 'Galerie' },
]

// Computed
const databaseId = computed(() => props.node.attrs.databaseId)
const noteId = computed(() => props.node.attrs.noteId || props.editor.options?.editorProps?.noteId)
const databaseName = computed(() => database.value?.name || props.node.attrs.name || 'Datenbank')

// Load or create database
onMounted(async () => {
  await loadOrCreateDatabase()
})

// Watch for databaseId changes
watch(() => props.node.attrs.databaseId, async (newId) => {
  if (newId) {
    await loadDatabase(newId)
  }
})

async function loadOrCreateDatabase() {
  isLoading.value = true

  try {
    if (databaseId.value) {
      // Load existing database
      await loadDatabase(databaseId.value)
    } else if (noteId.value) {
      // Create new database
      await createDatabase()
    } else {
      console.error('No noteId available for database creation')
    }
  } catch (error) {
    console.error('Error loading/creating database:', error)
    uiStore.showError('Fehler beim Laden der Datenbank')
  } finally {
    isLoading.value = false
  }
}

async function loadDatabase(id) {
  try {
    database.value = await databaseStore.fetchDatabase(id)
    currentView.value = database.value?.default_view || 'table'
  } catch (error) {
    console.error('Error loading database:', error)
    // Database might have been deleted
    database.value = null
  }
}

async function createDatabase() {
  try {
    const newDatabase = await databaseStore.createDatabase(noteId.value, {
      name: props.node.attrs.name || 'Neue Datenbank',
      default_view: 'table'
    })

    database.value = newDatabase
    currentView.value = newDatabase.default_view || 'table'

    // Update node with new database ID
    props.updateAttributes({
      databaseId: newDatabase.id,
      name: newDatabase.name
    })
  } catch (error) {
    console.error('Error creating database:', error)
    throw error
  }
}

// Switch view
async function switchView(viewId) {
  currentView.value = viewId

  if (database.value) {
    try {
      await databaseStore.updateDatabase(database.value.id, { default_view: viewId })
      props.updateAttributes({ view: viewId })
    } catch (error) {
      console.error('Error updating view:', error)
    }
  }
}

// Start renaming
function startRename() {
  renameValue.value = databaseName.value
  isRenaming.value = true
  showMenu.value = false
}

// Save rename
async function saveRename() {
  if (!renameValue.value.trim() || !database.value) return

  try {
    await databaseStore.updateDatabase(database.value.id, { name: renameValue.value })
    database.value.name = renameValue.value
    props.updateAttributes({ name: renameValue.value })
    isRenaming.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Umbenennen')
  }
}

// Duplicate database
async function duplicateDatabase() {
  if (!database.value) return

  try {
    const duplicated = await databaseStore.duplicateDatabase(database.value.id)

    // Insert a new database node after the current one
    const { from, to } = props.editor.state.selection
    props.editor.chain().focus().insertContentAt(to + 1, {
      type: 'inlineDatabase',
      attrs: {
        databaseId: duplicated.id,
        name: duplicated.name,
        noteId: noteId.value,
        view: duplicated.default_view
      }
    }).run()

    showMenu.value = false
    uiStore.showSuccess('Datenbank dupliziert')
  } catch (error) {
    uiStore.showError('Fehler beim Duplizieren')
  }
}

// Delete database
async function handleDelete() {
  if (!confirm('Datenbank wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) return

  try {
    if (database.value) {
      await databaseStore.deleteDatabase(database.value.id)
    }
    props.deleteNode()
    uiStore.showSuccess('Datenbank gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Toggle expanded view
function toggleExpanded() {
  isExpanded.value = !isExpanded.value
}

// Handle database updates from child components
async function handleDatabaseUpdate() {
  if (database.value) {
    await loadDatabase(database.value.id)
  }
}

// Handle row selection (for calendar/gallery)
function handleSelectRow(row) {
  // TODO: Open detail modal for row editing
  selectedRow.value = row
}

// Handle add row (for calendar/gallery)
async function handleAddRow(options = {}) {
  if (!database.value) return

  try {
    const newRow = await databaseStore.addRow(database.value.id, options)
    await handleDatabaseUpdate()
    return newRow
  } catch (error) {
    uiStore.showError('Fehler beim Hinzufügen')
  }
}

// Change calendar date property
function changeCalendarDateProperty(propertyId) {
  calendarDateProperty.value = propertyId
}

// Change gallery image property
function changeGalleryImageProperty(propertyId) {
  galleryImageProperty.value = propertyId
}

// Change gallery card size
function changeGalleryCardSize(size) {
  galleryCardSize.value = size
}
</script>

<template>
  <NodeViewWrapper
    as="div"
    :class="[
      'inline-database my-4 rounded-lg border border-dark-600 overflow-hidden',
      selected ? 'ring-2 ring-primary-500' : '',
      isExpanded ? 'fixed inset-4 z-50 bg-dark-900' : ''
    ]"
  >
    <!-- Header -->
    <div class="database-header flex items-center justify-between px-3 py-2 bg-dark-700 border-b border-dark-600">
      <div class="flex items-center gap-2 flex-1 min-w-0">
        <!-- Database icon -->
        <TableCellsIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />

        <!-- Name (editable) -->
        <input
          v-if="isRenaming"
          v-model="renameValue"
          type="text"
          class="flex-1 bg-dark-600 border border-primary-500 rounded px-2 py-0.5 text-sm text-white focus:outline-none"
          @blur="saveRename"
          @keydown.enter="saveRename"
          @keydown.escape="isRenaming = false"
          autofocus
        />
        <span v-else class="text-sm font-medium text-white truncate">
          {{ databaseName }}
        </span>
      </div>

      <div class="flex items-center gap-1">
        <!-- View switcher -->
        <div class="flex items-center bg-dark-600 rounded p-0.5">
          <button
            v-for="view in viewOptions"
            :key="view.id"
            @click="switchView(view.id)"
            :class="[
              'p-1.5 rounded transition-colors',
              currentView === view.id
                ? 'bg-dark-500 text-white'
                : 'text-gray-400 hover:text-white'
            ]"
            :title="view.label"
          >
            <component :is="view.icon" class="w-4 h-4" />
          </button>
        </div>

        <!-- Expand button -->
        <button
          @click="toggleExpanded"
          class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
          title="Erweitern"
        >
          <ArrowsPointingOutIcon class="w-4 h-4" />
        </button>

        <!-- Menu -->
        <div class="relative">
          <button
            @click="showMenu = !showMenu"
            class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
          >
            <EllipsisHorizontalIcon class="w-4 h-4" />
          </button>

          <!-- Dropdown menu -->
          <div
            v-if="showMenu"
            class="absolute right-0 top-full mt-1 w-48 bg-dark-700 border border-dark-600 rounded-lg shadow-xl z-30 overflow-hidden"
          >
            <button
              @click="startRename"
              class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-300 hover:bg-dark-600"
            >
              <PencilIcon class="w-4 h-4" />
              Umbenennen
            </button>
            <button
              @click="duplicateDatabase"
              class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-300 hover:bg-dark-600"
            >
              <DocumentDuplicateIcon class="w-4 h-4" />
              Duplizieren
            </button>
            <hr class="border-dark-600" />
            <button
              @click="handleDelete"
              class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-400 hover:bg-dark-600"
            >
              <TrashIcon class="w-4 h-4" />
              Löschen
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div :class="['database-content bg-dark-800', isExpanded ? 'flex-1 overflow-auto' : 'max-h-96 overflow-auto']">
      <!-- Loading -->
      <div v-if="isLoading" class="flex items-center justify-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
      </div>

      <!-- Error state -->
      <div v-else-if="!database" class="text-center py-12 text-gray-500">
        <TableCellsIcon class="w-12 h-12 mx-auto mb-2 opacity-50" />
        <p>Datenbank konnte nicht geladen werden</p>
        <button
          @click="loadOrCreateDatabase"
          class="mt-3 px-3 py-1.5 bg-primary-600 text-white text-sm rounded hover:bg-primary-700"
        >
          Erneut versuchen
        </button>
      </div>

      <!-- Table View -->
      <DatabaseTable
        v-else-if="currentView === 'table'"
        :database-id="database.id"
        @updated="handleDatabaseUpdate"
      />

      <!-- Board View -->
      <DatabaseBoard
        v-else-if="currentView === 'board'"
        :database-id="database.id"
        @updated="handleDatabaseUpdate"
      />

      <!-- List View -->
      <DatabaseList
        v-else-if="currentView === 'list'"
        :database-id="database.id"
        @updated="handleDatabaseUpdate"
      />

      <!-- Calendar View -->
      <DatabaseCalendarView
        v-else-if="currentView === 'calendar'"
        :rows="database.rows || []"
        :properties="database.properties || []"
        :date-property="calendarDateProperty"
        @select-row="handleSelectRow"
        @add-row="handleAddRow"
        @change-date-property="changeCalendarDateProperty"
      />

      <!-- Gallery View -->
      <DatabaseGalleryView
        v-else-if="currentView === 'gallery'"
        :rows="database.rows || []"
        :properties="database.properties || []"
        :image-property="galleryImageProperty"
        :card-size="galleryCardSize"
        @select-row="handleSelectRow"
        @add-row="handleAddRow"
        @change-image-property="changeGalleryImageProperty"
        @change-card-size="changeGalleryCardSize"
      />
    </div>

    <!-- Click outside handler for menu -->
    <div
      v-if="showMenu"
      class="fixed inset-0 z-20"
      @click="showMenu = false"
    />

    <!-- Expanded overlay background -->
    <Teleport to="body">
      <div
        v-if="isExpanded"
        class="fixed inset-0 bg-black/60 z-40"
        @click="isExpanded = false"
      />
    </Teleport>
  </NodeViewWrapper>
</template>

<style scoped>
.inline-database {
  transition: all 0.2s ease;
}

.inline-database:hover {
  border-color: rgb(59, 130, 246, 0.3);
}
</style>
