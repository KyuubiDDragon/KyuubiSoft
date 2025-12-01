<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { Univer, LocaleType, UniverInstanceType } from '@univerjs/core'
import { defaultTheme } from '@univerjs/design'
import { UniverDocsPlugin } from '@univerjs/docs'
import { UniverDocsUIPlugin } from '@univerjs/docs-ui'
import { UniverFormulaEnginePlugin } from '@univerjs/engine-formula'
import { UniverRenderEnginePlugin } from '@univerjs/engine-render'
import { UniverSheetsPlugin } from '@univerjs/sheets'
import { UniverSheetsUIPlugin } from '@univerjs/sheets-ui'
import { UniverSheetsFormulaPlugin } from '@univerjs/sheets-formula'
import { UniverUIPlugin } from '@univerjs/ui'

// Import styles
import '@univerjs/design/lib/index.css'
import '@univerjs/ui/lib/index.css'
import '@univerjs/docs-ui/lib/index.css'
import '@univerjs/sheets-ui/lib/index.css'
import '@univerjs/sheets-formula/lib/index.css'

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

// Parse saved data or create default
function parseData(jsonStr) {
  if (!jsonStr) {
    return getDefaultWorkbookData()
  }
  try {
    return JSON.parse(jsonStr)
  } catch {
    return getDefaultWorkbookData()
  }
}

function getDefaultWorkbookData() {
  return {
    id: 'workbook-1',
    name: 'Tabelle',
    sheetOrder: ['sheet-1'],
    appVersion: '1.0.0',
    sheets: {
      'sheet-1': {
        id: 'sheet-1',
        name: 'Tabelle 1',
        rowCount: 100,
        columnCount: 26,
        cellData: {},
        rowData: {},
        columnData: {},
        defaultColumnWidth: 100,
        defaultRowHeight: 24
      }
    }
  }
}

onMounted(async () => {
  try {
    // Create Univer instance
    const univer = new Univer({
      theme: defaultTheme,
      locale: LocaleType.DE_DE
    })
    univerRef.value = univer

    // Register plugins
    univer.registerPlugin(UniverRenderEnginePlugin)
    univer.registerPlugin(UniverFormulaEnginePlugin)
    univer.registerPlugin(UniverUIPlugin, {
      container: containerRef.value
    })
    univer.registerPlugin(UniverDocsPlugin)
    univer.registerPlugin(UniverDocsUIPlugin)
    univer.registerPlugin(UniverSheetsPlugin)
    univer.registerPlugin(UniverSheetsUIPlugin)
    univer.registerPlugin(UniverSheetsFormulaPlugin)

    // Load data
    const workbookData = parseData(props.modelValue)
    const workbook = univer.createUnit(UniverInstanceType.UNIVER_SHEET, workbookData)
    workbookRef.value = workbook

    // Listen for changes
    const commandService = univer.__getInjector().get('ICommandService')
    if (commandService) {
      commandService.onCommandExecuted(() => {
        saveData()
      })
    }

    isLoading.value = false
  } catch (error) {
    console.error('Failed to initialize Univer:', error)
    isLoading.value = false
  }
})

onBeforeUnmount(() => {
  if (univerRef.value) {
    univerRef.value.dispose()
  }
})

function saveData() {
  if (!workbookRef.value) return

  try {
    const snapshot = workbookRef.value.save()
    const jsonStr = JSON.stringify(snapshot)
    emit('update:modelValue', jsonStr)
    emit('change', jsonStr)
  } catch (error) {
    console.error('Failed to save spreadsheet data:', error)
  }
}

watch(() => props.modelValue, (newValue) => {
  // Only reload if we have a completely different structure
  // Normal edits are handled by the editor itself
}, { deep: false })

defineExpose({
  saveData,
  getWorkbook: () => workbookRef.value
})
</script>

<template>
  <div class="univer-sheet-wrapper">
    <!-- Loading -->
    <div v-if="isLoading" class="flex items-center justify-center bg-dark-800 border border-dark-600 rounded-lg h-[600px]">
      <div class="text-center">
        <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
        <p class="text-gray-400 text-sm">Tabelle wird geladen...</p>
      </div>
    </div>

    <!-- Spreadsheet Container -->
    <div
      ref="containerRef"
      class="univer-container border border-dark-600 rounded-lg overflow-hidden"
      :style="{ height: '600px', display: isLoading ? 'none' : 'block' }"
    ></div>
  </div>
</template>

<style>
.univer-sheet-wrapper {
  --univer-bg: #1a1a2e;
  --univer-border-color: #27272a;
}

.univer-container {
  background: var(--univer-bg);
}

/* Override Univer styles for dark theme */
.univer-container .univer-toolbar {
  background: #1e1e2e !important;
  border-bottom: 1px solid #27272a !important;
}

.univer-container .univer-sheet-container {
  background: #1a1a2e !important;
}

.univer-container .univer-cell {
  color: #e4e4e7 !important;
}

.univer-container .univer-header-cell {
  background: #27272a !important;
  color: #a1a1aa !important;
}
</style>
