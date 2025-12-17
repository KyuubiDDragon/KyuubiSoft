<script setup>
import { ref, computed } from 'vue'
import { useDatabaseStore } from '../../stores/databaseStore'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import {
  ChevronDownIcon,
  PencilIcon,
  TrashIcon,
  EyeSlashIcon,
  ArrowsUpDownIcon,
  PlusIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  property: {
    type: Object,
    required: true
  },
  databaseId: {
    type: String,
    required: true
  }
})

const databaseStore = useDatabaseStore()
const uiStore = useUiStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

const showMenu = ref(false)
const isRenaming = ref(false)
const renameValue = ref('')
const showOptionsEditor = ref(false)
const newOptionName = ref('')
const newOptionColor = ref('gray')

// Property type info
const typeInfo = computed(() => {
  return databaseStore.propertyTypes.find(t => t.value === props.property.type) || { icon: '?', label: 'Unknown' }
})

// Select options
const selectOptions = computed(() => {
  return props.property.config?.options || []
})

// Start renaming
function startRename() {
  renameValue.value = props.property.name
  isRenaming.value = true
  showMenu.value = false
}

// Save rename
async function saveRename() {
  if (!renameValue.value.trim()) return

  try {
    await databaseStore.updateProperty(props.databaseId, props.property.id, {
      name: renameValue.value
    })
    isRenaming.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Umbenennen')
  }
}

// Delete property
async function deleteProperty() {
  if (props.property.is_primary) {
    uiStore.showError('Primäre Spalte kann nicht gelöscht werden')
    return
  }

  if (!await confirm({ message: `Spalte "${props.property.name}" wirklich löschen?`, type: 'danger', confirmText: 'Löschen' })) return

  try {
    await databaseStore.deleteProperty(props.databaseId, props.property.id)
    showMenu.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

// Hide property
async function hideProperty() {
  try {
    await databaseStore.updateProperty(props.databaseId, props.property.id, {
      is_visible: false
    })
    showMenu.value = false
  } catch (error) {
    uiStore.showError('Fehler beim Ausblenden')
  }
}

// Add select option
async function addOption() {
  if (!newOptionName.value.trim()) return

  const currentOptions = [...selectOptions.value]
  currentOptions.push({
    id: crypto.randomUUID(),
    name: newOptionName.value,
    color: newOptionColor.value
  })

  try {
    await databaseStore.updateProperty(props.databaseId, props.property.id, {
      config: { options: currentOptions }
    })
    newOptionName.value = ''
    newOptionColor.value = 'gray'
  } catch (error) {
    uiStore.showError('Fehler beim Hinzufügen')
  }
}

// Delete select option
async function deleteOption(optionId) {
  const currentOptions = selectOptions.value.filter(o => o.id !== optionId)

  try {
    await databaseStore.updateProperty(props.databaseId, props.property.id, {
      config: { options: currentOptions }
    })
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}
</script>

<template>
  <div class="property-header relative">
    <!-- Header content -->
    <button
      v-if="!isRenaming"
      @click="showMenu = !showMenu"
      class="flex items-center gap-1.5 px-2 py-2 w-full hover:bg-dark-700 rounded transition-colors"
    >
      <span class="text-sm">{{ typeInfo.icon }}</span>
      <span class="text-xs font-medium text-gray-400 truncate">{{ property.name }}</span>
      <ChevronDownIcon class="w-3 h-3 text-gray-500 ml-auto flex-shrink-0" />
    </button>

    <!-- Rename input -->
    <input
      v-else
      v-model="renameValue"
      type="text"
      class="w-full bg-dark-700 border border-primary-500 rounded px-2 py-1 text-xs text-white focus:outline-none"
      @blur="saveRename"
      @keydown.enter="saveRename"
      @keydown.escape="isRenaming = false"
      autofocus
    />

    <!-- Dropdown menu -->
    <div
      v-if="showMenu"
      class="absolute top-full left-0 mt-1 w-56 bg-dark-700 border border-dark-600 rounded-lg shadow-xl z-20 overflow-hidden"
    >
      <div class="p-2 border-b border-dark-600">
        <div class="text-xs text-gray-500 mb-1">Typ</div>
        <div class="flex items-center gap-2 text-sm text-gray-300">
          <span>{{ typeInfo.icon }}</span>
          <span>{{ typeInfo.label }}</span>
        </div>
      </div>

      <div class="p-1">
        <button
          @click="startRename"
          class="w-full flex items-center gap-2 px-2 py-1.5 text-sm text-gray-300 hover:bg-dark-600 rounded"
        >
          <PencilIcon class="w-4 h-4" />
          Umbenennen
        </button>

        <!-- Options editor for select/multi_select -->
        <button
          v-if="['select', 'multi_select'].includes(property.type)"
          @click="showOptionsEditor = !showOptionsEditor"
          class="w-full flex items-center gap-2 px-2 py-1.5 text-sm text-gray-300 hover:bg-dark-600 rounded"
        >
          <ArrowsUpDownIcon class="w-4 h-4" />
          Optionen bearbeiten
        </button>

        <button
          @click="hideProperty"
          class="w-full flex items-center gap-2 px-2 py-1.5 text-sm text-gray-300 hover:bg-dark-600 rounded"
        >
          <EyeSlashIcon class="w-4 h-4" />
          Ausblenden
        </button>

        <hr class="my-1 border-dark-600" />

        <button
          @click="deleteProperty"
          :disabled="property.is_primary"
          :class="[
            'w-full flex items-center gap-2 px-2 py-1.5 text-sm rounded',
            property.is_primary ? 'text-gray-600 cursor-not-allowed' : 'text-red-400 hover:bg-dark-600'
          ]"
        >
          <TrashIcon class="w-4 h-4" />
          Löschen
        </button>
      </div>

      <!-- Options editor panel -->
      <div v-if="showOptionsEditor && ['select', 'multi_select'].includes(property.type)" class="border-t border-dark-600 p-2">
        <div class="text-xs text-gray-500 mb-2">Optionen</div>

        <!-- Existing options -->
        <div class="space-y-1 mb-2 max-h-32 overflow-y-auto">
          <div
            v-for="option in selectOptions"
            :key="option.id"
            class="flex items-center gap-2 px-2 py-1 rounded bg-dark-600/50 group"
          >
            <span :class="['w-3 h-3 rounded-full', `bg-${option.color}-500`]"></span>
            <span class="text-sm text-gray-300 flex-1 truncate">{{ option.name }}</span>
            <button
              @click="deleteOption(option.id)"
              class="p-0.5 text-gray-500 hover:text-red-400 opacity-0 group-hover:opacity-100"
            >
              <TrashIcon class="w-3 h-3" />
            </button>
          </div>
        </div>

        <!-- Add option -->
        <div class="flex items-center gap-2">
          <select
            v-model="newOptionColor"
            class="w-16 bg-dark-600 border-none rounded px-1 py-1 text-xs text-white"
          >
            <option
              v-for="color in databaseStore.selectColors"
              :key="color.value"
              :value="color.value"
            >
              {{ color.label }}
            </option>
          </select>
          <input
            v-model="newOptionName"
            type="text"
            placeholder="Option..."
            class="flex-1 bg-dark-600 border-none rounded px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-primary-500"
            @keydown.enter="addOption"
          />
          <button
            @click="addOption"
            class="p-1 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
          >
            <PlusIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Click outside handler -->
    <div
      v-if="showMenu"
      class="fixed inset-0 z-10"
      @click="showMenu = false"
    />
  </div>
</template>

<style scoped>
.property-header {
  position: relative;
}
</style>
