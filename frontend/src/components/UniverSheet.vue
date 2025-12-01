<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { createUniver, defaultTheme, LocaleType, merge } from '@univerjs/presets'
import { UniverSheetsCorePreset } from '@univerjs/preset-sheets-core'
import sheetsCoreEnUS from '@univerjs/preset-sheets-core/locales/en-US'

import '@univerjs/preset-sheets-core/lib/index.css'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  readOnly: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

const containerRef = ref(null)
const univerInstanceRef = ref(null)
const isLoading = ref(true)
const loadError = ref(null)

// Parse saved workbook data
function parseWorkbookData(jsonStr) {
  if (!jsonStr) return null
  try {
    return JSON.parse(jsonStr)
  } catch {
    return null
  }
}

// Create default workbook data
function getDefaultWorkbookData() {
  return {
    id: 'workbook-' + Date.now(),
    name: 'Arbeitsmappe',
    appVersion: '0.0.0',
    sheetOrder: ['sheet-1'],
    sheets: {
      'sheet-1': {
        id: 'sheet-1',
        name: 'Tabelle 1',
        rowCount: 200,
        columnCount: 30,
        cellData: {},
        rowData: {},
        columnData: {},
        showGridlines: 1,
        defaultRowHeight: 24,
        defaultColumnWidth: 88,
        rowHeader: {
          width: 46,
          hidden: 0,
        },
        columnHeader: {
          height: 20,
          hidden: 0,
        },
        scrollTop: 0,
        scrollLeft: 0,
        zoomRatio: 1,
      }
    }
  }
}

async function initUniver() {
  if (!containerRef.value) return

  isLoading.value = true
  loadError.value = null

  try {
    // Get saved or default data
    const savedData = parseWorkbookData(props.modelValue)
    const workbookData = savedData || getDefaultWorkbookData()

    // Create Univer instance with preset
    const { univer, univerAPI } = createUniver({
      locale: LocaleType.EN_US,
      locales: {
        [LocaleType.EN_US]: merge({}, sheetsCoreEnUS),
      },
      theme: defaultTheme,
      darkMode: true,
      presets: [
        UniverSheetsCorePreset({
          container: containerRef.value,
        }),
      ],
    })

    // Create the sheet
    univerAPI.createUniverSheet(workbookData)

    // Store references
    univerInstanceRef.value = { univer, univerAPI }

    // Setup auto-save
    setupAutoSave(univer)

    isLoading.value = false
  } catch (error) {
    console.error('Failed to initialize Univer:', error)
    loadError.value = error.message || 'Fehler beim Laden der Tabelle'
    isLoading.value = false
  }
}

function setupAutoSave(univer) {
  let saveTimeout = null

  const save = () => {
    if (saveTimeout) clearTimeout(saveTimeout)
    saveTimeout = setTimeout(() => {
      saveData()
    }, 500)
  }

  // Listen for command execution
  if (univer.onCommandExecuted) {
    univer.onCommandExecuted(() => {
      save()
    })
  }
}

function saveData() {
  if (!univerInstanceRef.value?.univerAPI) return

  try {
    const workbook = univerInstanceRef.value.univerAPI.getActiveWorkbook()
    if (workbook) {
      const snapshot = workbook.save()
      const jsonStr = JSON.stringify(snapshot)
      emit('update:modelValue', jsonStr)
      emit('change', jsonStr)
    }
  } catch (error) {
    console.warn('Failed to save spreadsheet:', error)
  }
}

onMounted(() => {
  initUniver()
})

onBeforeUnmount(() => {
  if (univerInstanceRef.value?.univer) {
    try {
      univerInstanceRef.value.univer.dispose()
    } catch (e) {
      console.warn('Failed to dispose Univer:', e)
    }
  }
})

watch(() => props.readOnly, (readOnly) => {
  // TODO: Handle read-only mode changes if needed
})

defineExpose({
  saveData,
  getInstance: () => univerInstanceRef.value,
})
</script>

<template>
  <div class="univer-wrapper">
    <!-- Loading -->
    <div
      v-if="isLoading"
      class="loading-container"
    >
      <div class="text-center">
        <div class="w-10 h-10 border-4 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-gray-400">Tabelle wird geladen...</p>
        <p class="text-gray-500 text-sm mt-1">Univer Spreadsheet</p>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-else-if="loadError"
      class="loading-container border-red-600/50"
    >
      <div class="text-center p-6">
        <svg class="w-12 h-12 text-red-500 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <p class="text-red-400 font-medium">Fehler beim Laden</p>
        <p class="text-gray-500 text-sm mt-1">{{ loadError }}</p>
        <button @click="initUniver" class="btn-primary mt-4">
          Erneut versuchen
        </button>
      </div>
    </div>

    <!-- Univer Container - always rendered but hidden when loading -->
    <div
      v-show="!isLoading && !loadError"
      ref="containerRef"
      class="univer-container"
    ></div>
  </div>
</template>

<style>
/* Wrapper needs explicit height */
.univer-wrapper {
  width: 100%;
  height: 700px;
  position: relative;
}

/* Loading state */
.loading-container {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #1a1a2e;
  border: 1px solid #3f3f46;
  border-radius: 0.5rem;
}

/* Main container - explicit dimensions required for Univer */
.univer-container {
  width: 100%;
  height: 100%;
  border: 1px solid #3f3f46;
  border-radius: 0.5rem;
  overflow: hidden;
}

/* Ensure Univer's internal container fills the space */
.univer-container > div,
.univer-container .univer-app-container-wrapper {
  width: 100% !important;
  height: 100% !important;
}

/* Custom scrollbar for dark mode */
.univer-container ::-webkit-scrollbar {
  width: 10px;
  height: 10px;
}

.univer-container ::-webkit-scrollbar-track {
  background: #1a1a2e;
}

.univer-container ::-webkit-scrollbar-thumb {
  background: #3f3f46;
  border-radius: 5px;
}

.univer-container ::-webkit-scrollbar-thumb:hover {
  background: #52525b;
}
</style>
