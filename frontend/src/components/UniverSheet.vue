<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'

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

// CDN URLs for Univer
const UNIVER_VERSION = '0.5.4'
const CDN_BASE = `https://unpkg.com/@univerjs`

const cssFiles = [
  `${CDN_BASE}/design@${UNIVER_VERSION}/lib/index.css`,
  `${CDN_BASE}/ui@${UNIVER_VERSION}/lib/index.css`,
  `${CDN_BASE}/sheets-ui@${UNIVER_VERSION}/lib/index.css`,
  `${CDN_BASE}/sheets-formula-ui@${UNIVER_VERSION}/lib/index.css`,
]

const jsFiles = [
  `${CDN_BASE}/core@${UNIVER_VERSION}/lib/umd/index.js`,
  `${CDN_BASE}/design@${UNIVER_VERSION}/lib/umd/index.js`,
  `${CDN_BASE}/engine-render@${UNIVER_VERSION}/lib/umd/index.js`,
  `${CDN_BASE}/engine-formula@${UNIVER_VERSION}/lib/umd/index.js`,
  `${CDN_BASE}/ui@${UNIVER_VERSION}/lib/umd/index.js`,
  `${CDN_BASE}/sheets@${UNIVER_VERSION}/lib/umd/index.js`,
  `${CDN_BASE}/sheets-ui@${UNIVER_VERSION}/lib/umd/index.js`,
  `${CDN_BASE}/sheets-formula@${UNIVER_VERSION}/lib/umd/index.js`,
  `${CDN_BASE}/sheets-formula-ui@${UNIVER_VERSION}/lib/umd/index.js`,
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
    id: 'workbook-1',
    locale: window.UniverCore?.LocaleType?.DE_DE || 'deDE',
    name: 'Arbeitsmappe',
    sheetOrder: ['sheet-1'],
    sheets: {
      'sheet-1': {
        id: 'sheet-1',
        name: 'Tabelle 1',
        rowCount: 100,
        columnCount: 26,
        cellData: {},
        rowData: {},
        columnData: {},
        defaultRowHeight: 24,
        defaultColumnWidth: 100,
      }
    }
  }
}

async function initUniver() {
  try {
    // Load CSS files
    await Promise.all(cssFiles.map(loadCSS))

    // Load JS files sequentially (order matters)
    for (const url of jsFiles) {
      await loadScript(url)
    }

    // Wait for globals to be available
    await new Promise(resolve => setTimeout(resolve, 100))

    const {
      Univer,
      UniverInstanceType,
      LocaleType,
    } = window.UniverCore

    const { defaultTheme } = window.UniverDesign
    const { UniverRenderEnginePlugin } = window.UniverEngineRender
    const { UniverFormulaEnginePlugin } = window.UniverEngineFormula
    const { UniverUIPlugin } = window.UniverUi
    const { UniverSheetsPlugin } = window.UniverSheets
    const { UniverSheetsUIPlugin } = window.UniverSheetsUi
    const { UniverSheetsFormulaPlugin } = window.UniverSheetsFormula
    const { UniverSheetsFormulaUIPlugin } = window.UniverSheetsFormulaUi

    // Create Univer instance
    const univer = new Univer({
      theme: defaultTheme,
      locale: LocaleType.DE_DE,
    })

    univerRef.value = univer

    // Register plugins
    univer.registerPlugin(UniverRenderEnginePlugin)
    univer.registerPlugin(UniverFormulaEnginePlugin)
    univer.registerPlugin(UniverUIPlugin, {
      container: containerRef.value,
    })
    univer.registerPlugin(UniverSheetsPlugin)
    univer.registerPlugin(UniverSheetsUIPlugin)
    univer.registerPlugin(UniverSheetsFormulaPlugin)
    univer.registerPlugin(UniverSheetsFormulaUIPlugin)

    // Load or create workbook data
    const savedData = parseWorkbookData(props.modelValue)
    const workbookData = savedData || getDefaultWorkbookData()

    // Create workbook
    const workbook = univer.createUnit(UniverInstanceType.UNIVER_SHEET, workbookData)
    workbookRef.value = workbook

    // Auto-save on changes
    setupAutoSave(univer)

    isLoading.value = false
  } catch (error) {
    console.error('Failed to load Univer:', error)
    loadError.value = error.message || 'Fehler beim Laden der Tabelle'
    isLoading.value = false
  }
}

function setupAutoSave(univer) {
  // Debounce save
  let saveTimeout = null

  const save = () => {
    if (saveTimeout) clearTimeout(saveTimeout)
    saveTimeout = setTimeout(() => {
      if (workbookRef.value) {
        const snapshot = workbookRef.value.save()
        const jsonStr = JSON.stringify(snapshot)
        emit('update:modelValue', jsonStr)
        emit('change', jsonStr)
      }
    }, 500)
  }

  // Listen for command execution (any change)
  univer.onCommandExecuted(() => {
    save()
  })
}

onMounted(() => {
  initUniver()
})

onBeforeUnmount(() => {
  if (univerRef.value) {
    univerRef.value.dispose()
  }
})

function saveData() {
  if (workbookRef.value) {
    const snapshot = workbookRef.value.save()
    const jsonStr = JSON.stringify(snapshot)
    emit('update:modelValue', jsonStr)
    emit('change', jsonStr)
  }
}

defineExpose({
  saveData,
  getInstance: () => univerRef.value,
  getWorkbook: () => workbookRef.value
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
      class="univer-container flex-1 border border-dark-600 rounded-lg overflow-hidden min-h-[600px]"
      :style="{ display: isLoading || loadError ? 'none' : 'block' }"
    ></div>
  </div>
</template>

<style>
/* Univer Dark Theme Overrides */
.univer-wrapper {
  --univer-bg-color: #1a1a2e;
  --univer-bg-color-secondary: #27272a;
  --univer-text-color: #e4e4e7;
  --univer-text-color-secondary: #a1a1aa;
  --univer-border-color: #3f3f46;
  --univer-primary-color: #3b82f6;
}

.univer-container {
  background: var(--univer-bg-color);
}

/* Override Univer's default styles for dark mode */
.univer-container .univer-workbench {
  background: var(--univer-bg-color) !important;
}

.univer-container .univer-toolbar {
  background: var(--univer-bg-color-secondary) !important;
  border-bottom: 1px solid var(--univer-border-color) !important;
}

.univer-container .univer-toolbar-btn {
  color: var(--univer-text-color) !important;
}

.univer-container .univer-toolbar-btn:hover {
  background: rgba(255, 255, 255, 0.1) !important;
}

.univer-container .univer-formula-bar {
  background: var(--univer-bg-color-secondary) !important;
  border-bottom: 1px solid var(--univer-border-color) !important;
}

.univer-container .univer-formula-bar input {
  background: var(--univer-bg-color) !important;
  color: var(--univer-text-color) !important;
  border: 1px solid var(--univer-border-color) !important;
}

.univer-container .univer-sheet-bar {
  background: var(--univer-bg-color-secondary) !important;
  border-top: 1px solid var(--univer-border-color) !important;
}

.univer-container .univer-sheet-bar-btn {
  color: var(--univer-text-color-secondary) !important;
}

.univer-container .univer-sheet-bar-btn.univer-sheet-bar-btn-active {
  background: var(--univer-bg-color) !important;
  color: var(--univer-text-color) !important;
}

/* Scrollbars */
.univer-container ::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

.univer-container ::-webkit-scrollbar-track {
  background: var(--univer-bg-color);
}

.univer-container ::-webkit-scrollbar-thumb {
  background: var(--univer-border-color);
  border-radius: 4px;
}

.univer-container ::-webkit-scrollbar-thumb:hover {
  background: #52525b;
}

/* Context menus and dropdowns */
.univer-context-menu,
.univer-dropdown {
  background: var(--univer-bg-color-secondary) !important;
  border: 1px solid var(--univer-border-color) !important;
  color: var(--univer-text-color) !important;
}

.univer-context-menu-item:hover,
.univer-dropdown-item:hover {
  background: rgba(255, 255, 255, 0.1) !important;
}
</style>
