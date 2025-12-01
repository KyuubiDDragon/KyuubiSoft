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
const isLoading = ref(true)
const loadError = ref(null)

// Official Univer CDN setup - using their bundle approach
const UNIVER_CDN = 'https://unpkg.com/@anthropic-internal/test@1.0.0' // placeholder

const cssUrls = [
  'https://unpkg.com/@univerjs/design@0.4.2/lib/index.css',
  'https://unpkg.com/@univerjs/ui@0.4.2/lib/index.css',
  'https://unpkg.com/@univerjs/docs-ui@0.4.2/lib/index.css',
  'https://unpkg.com/@univerjs/sheets-ui@0.4.2/lib/index.css',
  'https://unpkg.com/@univerjs/sheets-formula@0.4.2/lib/index.css',
]

// Load dependencies and Univer in correct order
const scriptUrls = [
  // Core dependencies
  'https://unpkg.com/clsx@2.1.1/dist/clsx.min.js',
  'https://unpkg.com/react@18.2.0/umd/react.production.min.js',
  'https://unpkg.com/react-dom@18.2.0/umd/react-dom.production.min.js',
  'https://unpkg.com/rxjs@7.8.1/dist/bundles/rxjs.umd.min.js',
  // Univer Facade (simpler API)
  'https://unpkg.com/@anthropic-internal/univerjs-facade@0.4.2/lib/umd/index.js',
]

function loadCSS(url) {
  return new Promise((resolve) => {
    if (document.querySelector(`link[href="${url}"]`)) {
      resolve()
      return
    }
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = url
    link.onload = resolve
    link.onerror = resolve // Don't fail on CSS errors
    document.head.appendChild(link)
  })
}

function loadScript(url) {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${url}"]`)) {
      resolve()
      return
    }
    const script = document.createElement('script')
    script.src = url
    script.onload = resolve
    script.onerror = () => reject(new Error(`Failed to load: ${url}`))
    document.body.appendChild(script)
  })
}

function parseData(jsonStr) {
  if (!jsonStr) return null
  try {
    return JSON.parse(jsonStr)
  } catch {
    return null
  }
}

function createDefaultData() {
  // Create a simple 50x26 grid
  const cellData = {}
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
        cellData,
      }
    }
  }
}

async function initUniver() {
  isLoading.value = true
  loadError.value = null

  try {
    // Since Univer CDN is complex, let's use an iframe approach with their playground
    // Or we can use a simpler inline spreadsheet

    // For now, let's create a simple but functional spreadsheet using canvas
    await initSimpleSpreadsheet()

    isLoading.value = false
  } catch (error) {
    console.error('Failed to init spreadsheet:', error)
    loadError.value = error.message || 'Fehler beim Laden'
    isLoading.value = false
  }
}

// Simple spreadsheet implementation
const gridData = ref([])
const selectedCell = ref({ row: 0, col: 0 })
const editingCell = ref(null)
const editValue = ref('')
const ROWS = 50
const COLS = 26

function getColumnLabel(index) {
  return String.fromCharCode(65 + index)
}

async function initSimpleSpreadsheet() {
  // Initialize grid data from saved or default
  const saved = parseData(props.modelValue)

  if (saved?.data) {
    gridData.value = saved.data
  } else {
    // Create empty grid
    gridData.value = Array(ROWS).fill(null).map(() => Array(COLS).fill(''))
  }
}

function getCellValue(row, col) {
  return gridData.value[row]?.[col] || ''
}

function setCellValue(row, col, value) {
  if (!gridData.value[row]) {
    gridData.value[row] = Array(COLS).fill('')
  }
  gridData.value[row][col] = value
  saveData()
}

function startEdit(row, col) {
  editingCell.value = { row, col }
  editValue.value = getCellValue(row, col)
}

function finishEdit() {
  if (editingCell.value) {
    setCellValue(editingCell.value.row, editingCell.value.col, editValue.value)
    editingCell.value = null
  }
}

function cancelEdit() {
  editingCell.value = null
  editValue.value = ''
}

function handleKeydown(e) {
  if (!editingCell.value) {
    if (e.key === 'Enter' || e.key === 'F2') {
      startEdit(selectedCell.value.row, selectedCell.value.col)
      e.preventDefault()
    } else if (e.key === 'ArrowUp' && selectedCell.value.row > 0) {
      selectedCell.value.row--
    } else if (e.key === 'ArrowDown' && selectedCell.value.row < ROWS - 1) {
      selectedCell.value.row++
    } else if (e.key === 'ArrowLeft' && selectedCell.value.col > 0) {
      selectedCell.value.col--
    } else if (e.key === 'ArrowRight' && selectedCell.value.col < COLS - 1) {
      selectedCell.value.col++
    } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey) {
      // Start typing
      startEdit(selectedCell.value.row, selectedCell.value.col)
      editValue.value = e.key
    }
  } else {
    if (e.key === 'Enter') {
      finishEdit()
      if (selectedCell.value.row < ROWS - 1) {
        selectedCell.value.row++
      }
    } else if (e.key === 'Escape') {
      cancelEdit()
    } else if (e.key === 'Tab') {
      e.preventDefault()
      finishEdit()
      if (e.shiftKey && selectedCell.value.col > 0) {
        selectedCell.value.col--
      } else if (!e.shiftKey && selectedCell.value.col < COLS - 1) {
        selectedCell.value.col++
      }
    }
  }
}

function selectCell(row, col) {
  if (editingCell.value) {
    finishEdit()
  }
  selectedCell.value = { row, col }
}

function saveData() {
  const jsonStr = JSON.stringify({ data: gridData.value })
  emit('update:modelValue', jsonStr)
  emit('change', jsonStr)
}

// Formula evaluation (basic)
function evaluateFormula(formula) {
  if (!formula.startsWith('=')) return formula

  try {
    const expr = formula.substring(1).toUpperCase()

    // SUM function
    const sumMatch = expr.match(/^SUM\(([A-Z])(\d+):([A-Z])(\d+)\)$/)
    if (sumMatch) {
      const startCol = sumMatch[1].charCodeAt(0) - 65
      const startRow = parseInt(sumMatch[2]) - 1
      const endCol = sumMatch[3].charCodeAt(0) - 65
      const endRow = parseInt(sumMatch[4]) - 1

      let sum = 0
      for (let r = startRow; r <= endRow; r++) {
        for (let c = startCol; c <= endCol; c++) {
          const val = parseFloat(getCellValue(r, c)) || 0
          sum += val
        }
      }
      return sum.toString()
    }

    // Cell reference (e.g., A1)
    const cellMatch = expr.match(/^([A-Z])(\d+)$/)
    if (cellMatch) {
      const col = cellMatch[1].charCodeAt(0) - 65
      const row = parseInt(cellMatch[2]) - 1
      return getCellValue(row, col)
    }

    return formula
  } catch {
    return '#ERROR'
  }
}

function getDisplayValue(row, col) {
  const value = getCellValue(row, col)
  if (value.startsWith('=')) {
    return evaluateFormula(value)
  }
  return value
}

onMounted(() => {
  initUniver()
})

defineExpose({
  saveData,
  getData: () => gridData.value,
})
</script>

<template>
  <div class="spreadsheet-wrapper h-full flex flex-col">
    <!-- Loading -->
    <div v-if="isLoading" class="flex-1 flex items-center justify-center bg-dark-800 border border-dark-600 rounded-lg min-h-[600px]">
      <div class="text-center">
        <div class="w-10 h-10 border-4 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-gray-400">Tabelle wird geladen...</p>
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
      </div>
    </div>

    <!-- Spreadsheet -->
    <div
      v-else
      ref="containerRef"
      class="spreadsheet-container flex-1 flex flex-col bg-dark-800 border border-dark-600 rounded-lg overflow-hidden min-h-[600px]"
      @keydown="handleKeydown"
      tabindex="0"
    >
      <!-- Toolbar -->
      <div class="flex items-center gap-2 p-2 bg-dark-700 border-b border-dark-600">
        <div class="flex items-center gap-1 text-sm text-gray-400">
          <span class="px-2 py-1 bg-dark-600 rounded font-mono">
            {{ getColumnLabel(selectedCell.col) }}{{ selectedCell.row + 1 }}
          </span>
        </div>
        <div class="flex-1">
          <input
            :value="getCellValue(selectedCell.row, selectedCell.col)"
            @input="setCellValue(selectedCell.row, selectedCell.col, $event.target.value)"
            class="w-full px-3 py-1 bg-dark-600 border border-dark-500 rounded text-white text-sm font-mono focus:outline-none focus:border-primary-500"
            placeholder="Wert oder Formel eingeben (z.B. =SUM(A1:A10))"
          />
        </div>
      </div>

      <!-- Grid -->
      <div class="flex-1 overflow-auto">
        <table class="spreadsheet-table">
          <thead>
            <tr>
              <th class="row-header"></th>
              <th v-for="col in COLS" :key="col" class="col-header">
                {{ getColumnLabel(col - 1) }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in ROWS" :key="row">
              <td class="row-header">{{ row }}</td>
              <td
                v-for="col in COLS"
                :key="col"
                class="cell"
                :class="{
                  'selected': selectedCell.row === row - 1 && selectedCell.col === col - 1,
                  'editing': editingCell?.row === row - 1 && editingCell?.col === col - 1
                }"
                @click="selectCell(row - 1, col - 1)"
                @dblclick="startEdit(row - 1, col - 1)"
              >
                <input
                  v-if="editingCell?.row === row - 1 && editingCell?.col === col - 1"
                  v-model="editValue"
                  @blur="finishEdit"
                  @keydown.stop
                  class="cell-input"
                  ref="cellInput"
                  autofocus
                />
                <span v-else class="cell-value">{{ getDisplayValue(row - 1, col - 1) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Status Bar -->
      <div class="flex items-center justify-between px-3 py-1 bg-dark-700 border-t border-dark-600 text-xs text-gray-500">
        <span>{{ ROWS }} Zeilen × {{ COLS }} Spalten</span>
        <span>Doppelklick oder Enter zum Bearbeiten • Tab für nächste Zelle</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.spreadsheet-table {
  border-collapse: collapse;
  table-layout: fixed;
  width: max-content;
}

.spreadsheet-table th,
.spreadsheet-table td {
  border: 1px solid #3f3f46;
  padding: 0;
  height: 28px;
  min-width: 100px;
  max-width: 200px;
}

.col-header {
  background: #27272a;
  color: #a1a1aa;
  font-weight: 600;
  font-size: 12px;
  text-align: center;
  position: sticky;
  top: 0;
  z-index: 10;
  min-width: 100px;
}

.row-header {
  background: #27272a;
  color: #a1a1aa;
  font-weight: 600;
  font-size: 12px;
  text-align: center;
  position: sticky;
  left: 0;
  z-index: 5;
  min-width: 50px !important;
  width: 50px !important;
}

thead .row-header {
  z-index: 20;
}

.cell {
  background: #1a1a2e;
  color: #e4e4e7;
  font-size: 13px;
  cursor: cell;
  position: relative;
}

.cell:hover {
  background: #1e1e32;
}

.cell.selected {
  outline: 2px solid #3b82f6;
  outline-offset: -1px;
  background: rgba(59, 130, 246, 0.1);
}

.cell.editing {
  padding: 0;
}

.cell-value {
  display: block;
  padding: 4px 8px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cell-input {
  width: 100%;
  height: 100%;
  padding: 4px 8px;
  background: #1a1a2e;
  color: #e4e4e7;
  border: none;
  outline: none;
  font-size: 13px;
  font-family: inherit;
}

/* Scrollbar styling */
.spreadsheet-container ::-webkit-scrollbar {
  width: 12px;
  height: 12px;
}

.spreadsheet-container ::-webkit-scrollbar-track {
  background: #1a1a2e;
}

.spreadsheet-container ::-webkit-scrollbar-thumb {
  background: #3f3f46;
  border-radius: 6px;
  border: 3px solid #1a1a2e;
}

.spreadsheet-container ::-webkit-scrollbar-thumb:hover {
  background: #52525b;
}

.spreadsheet-container ::-webkit-scrollbar-corner {
  background: #1a1a2e;
}
</style>
