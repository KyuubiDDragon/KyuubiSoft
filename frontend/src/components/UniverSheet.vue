<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

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
const univerRef = ref(null)
const workbookRef = ref(null)
const isLoading = ref(true)
const loadError = ref(null)

// Use Univer's preset bundle which includes all dependencies
const PRESET_VERSION = '0.5.4'

const cssFiles = [
  `https://unpkg.com/@univerjs/preset-sheets-core@${PRESET_VERSION}/lib/index.css`,
]

const jsFiles = [
  // Dependencies first
  'https://unpkg.com/react@18.3.1/umd/react.production.min.js',
  'https://unpkg.com/react-dom@18.3.1/umd/react-dom.production.min.js',
  'https://unpkg.com/rxjs@7.8.1/dist/bundles/rxjs.umd.min.js',
  // Univer preset
  `https://unpkg.com/@univerjs/preset-sheets-core@${PRESET_VERSION}/lib/umd/index.js`,
]

// Load CSS file
function loadCSS(url) {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`link[href="${url}"]`)) {
      resolve()
      return
    }
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = url
    link.onload = resolve
    link.onerror = reject
    document.head.appendChild(link)
  })
}

// Load JS file
function loadScript(url) {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${url}"]`)) {
      resolve()
      return
    }
    const script = document.createElement('script')
    script.src = url
    script.onload = resolve
    script.onerror = reject
    document.body.appendChild(script)
  })
}

// Parse saved data
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
    sheetOrder: ['sheet-1'],
    sheets: {
      'sheet-1': {
        id: 'sheet-1',
        name: 'Tabelle 1',
        rowCount: 100,
        columnCount: 26,
        cellData: {},
      }
    }
  }
}

async function initUniver() {
  isLoading.value = true
  loadError.value = null

  try {
    // Load CSS files
    await Promise.all(cssFiles.map(loadCSS))

    // Load JS files sequentially (order matters for dependencies)
    for (const url of jsFiles) {
      await loadScript(url)
    }

    // Wait for globals to be available
    await new Promise(resolve => setTimeout(resolve, 200))

    // Check if preset is loaded
    const UniverPresetSheetsCore = window.UniverPresetSheetsCore
    if (!UniverPresetSheetsCore) {
      throw new Error('Univer Preset konnte nicht geladen werden')
    }

    const { createUniver, LocaleType, defaultTheme } = UniverPresetSheetsCore

    // Load or create workbook data
    const savedData = parseWorkbookData(props.modelValue)
    const workbookData = savedData || getDefaultWorkbookData()

    // Create Univer with preset
    const { univer, univerAPI } = createUniver({
      locale: LocaleType.EN_US,
      theme: defaultTheme,
      container: containerRef.value,
      workbook: workbookData,
    })

    univerRef.value = { univer, univerAPI }
    workbookRef.value = univerAPI.getActiveWorkbook()

    // Setup auto-save
    setupAutoSave(univer)

    isLoading.value = false
  } catch (error) {
    console.error('Failed to load Univer:', error)
    loadError.value = error.message || 'Fehler beim Laden der Tabelle'
    isLoading.value = false
  }
}

function setupAutoSave(univer) {
  let saveTimeout = null

  const save = () => {
    if (saveTimeout) clearTimeout(saveTimeout)
    saveTimeout = setTimeout(() => {
      if (univerRef.value?.univerAPI) {
        try {
          const workbook = univerRef.value.univerAPI.getActiveWorkbook()
          if (workbook) {
            const snapshot = workbook.save()
            const jsonStr = JSON.stringify(snapshot)
            emit('update:modelValue', jsonStr)
            emit('change', jsonStr)
          }
        } catch (e) {
          console.warn('Auto-save failed:', e)
        }
      }
    }, 500)
  }

  // Listen for command execution
  univer.onCommandExecuted?.(() => {
    save()
  })
}

onMounted(() => {
  initUniver()
})

onBeforeUnmount(() => {
  if (univerRef.value?.univer) {
    try {
      univerRef.value.univer.dispose()
    } catch (e) {
      console.warn('Dispose failed:', e)
    }
  }
})

function saveData() {
  if (univerRef.value?.univerAPI) {
    const workbook = univerRef.value.univerAPI.getActiveWorkbook()
    if (workbook) {
      const snapshot = workbook.save()
      const jsonStr = JSON.stringify(snapshot)
      emit('update:modelValue', jsonStr)
      emit('change', jsonStr)
    }
  }
}

defineExpose({
  saveData,
  getInstance: () => univerRef.value,
})
</script>

<template>
  <div class="univer-wrapper h-full flex flex-col">
    <!-- Loading -->
    <div v-if="isLoading" class="flex-1 flex items-center justify-center bg-dark-800 border border-dark-600 rounded-lg min-h-[600px]">
      <div class="text-center">
        <div class="w-10 h-10 border-4 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-gray-400">Tabelle wird geladen...</p>
        <p class="text-gray-500 text-sm mt-1">Univer Spreadsheet Engine</p>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="loadError" class="flex-1 flex items-center justify-center bg-dark-800 border border-red-600/50 rounded-lg min-h-[600px]">
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

    <!-- Univer Container -->
    <div
      ref="containerRef"
      class="univer-container flex-1 rounded-lg overflow-hidden min-h-[600px]"
      :class="{ 'hidden': isLoading || loadError }"
    ></div>
  </div>
</template>

<style>
/* Dark Theme Overrides for Univer */
.univer-wrapper {
  --univer-bg: #1a1a2e;
  --univer-bg-secondary: #27272a;
  --univer-text: #e4e4e7;
  --univer-text-secondary: #a1a1aa;
  --univer-border: #3f3f46;
}

.univer-container {
  background: var(--univer-bg);
  border: 1px solid var(--univer-border);
}

/* Global Univer overrides */
.univer-container [class*="univer"] {
  font-family: inherit;
}

/* Workbench */
.univer-container .univer-app-container-wrapper,
.univer-container .univer-workbench-container,
.univer-container .univer-workbench {
  background: var(--univer-bg) !important;
}

/* Header/Toolbar */
.univer-container .univer-header,
.univer-container .univer-toolbar {
  background: var(--univer-bg-secondary) !important;
  border-color: var(--univer-border) !important;
}

.univer-container .univer-toolbar-group {
  border-color: var(--univer-border) !important;
}

.univer-container .univer-toolbar-btn,
.univer-container .univer-icon-btn {
  color: var(--univer-text) !important;
}

.univer-container .univer-toolbar-btn:hover,
.univer-container .univer-icon-btn:hover {
  background: rgba(255, 255, 255, 0.1) !important;
}

/* Formula Bar */
.univer-container .univer-formula-bar {
  background: var(--univer-bg-secondary) !important;
  border-color: var(--univer-border) !important;
}

.univer-container .univer-formula-bar input,
.univer-container .univer-formula-bar textarea {
  background: var(--univer-bg) !important;
  color: var(--univer-text) !important;
  border-color: var(--univer-border) !important;
}

/* Sheet tabs */
.univer-container .univer-sheet-bar {
  background: var(--univer-bg-secondary) !important;
  border-color: var(--univer-border) !important;
}

.univer-container .univer-sheet-bar-item {
  background: var(--univer-bg-secondary) !important;
  color: var(--univer-text-secondary) !important;
  border-color: var(--univer-border) !important;
}

.univer-container .univer-sheet-bar-item.univer-sheet-bar-item-active,
.univer-container .univer-sheet-bar-item:hover {
  background: var(--univer-bg) !important;
  color: var(--univer-text) !important;
}

/* Cells and grid */
.univer-container .univer-sheet-container {
  background: var(--univer-bg) !important;
}

/* Scrollbars */
.univer-container ::-webkit-scrollbar {
  width: 10px;
  height: 10px;
}

.univer-container ::-webkit-scrollbar-track {
  background: var(--univer-bg);
}

.univer-container ::-webkit-scrollbar-thumb {
  background: var(--univer-border);
  border-radius: 5px;
}

.univer-container ::-webkit-scrollbar-thumb:hover {
  background: #52525b;
}

/* Dropdowns and menus */
.univer-dropdown,
.univer-context-menu,
.univer-popup {
  background: var(--univer-bg-secondary) !important;
  border-color: var(--univer-border) !important;
  color: var(--univer-text) !important;
}

.univer-dropdown-item:hover,
.univer-context-menu-item:hover {
  background: rgba(255, 255, 255, 0.1) !important;
}

/* Input fields */
.univer-container input,
.univer-container textarea,
.univer-container select {
  background: var(--univer-bg) !important;
  color: var(--univer-text) !important;
  border-color: var(--univer-border) !important;
}

/* Status bar */
.univer-container .univer-status-bar {
  background: var(--univer-bg-secondary) !important;
  border-color: var(--univer-border) !important;
  color: var(--univer-text-secondary) !important;
}
</style>
