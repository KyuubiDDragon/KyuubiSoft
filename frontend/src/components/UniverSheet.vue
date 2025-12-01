<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue'
import Handsontable from 'handsontable'
import 'handsontable/dist/handsontable.full.min.css'

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
const hotInstance = ref(null)
const isLoading = ref(true)

// Parse saved data or create default
function parseData(jsonStr) {
  if (!jsonStr) {
    return getDefaultData()
  }
  try {
    const parsed = JSON.parse(jsonStr)
    return parsed.data || getDefaultData()
  } catch {
    return getDefaultData()
  }
}

function getDefaultData() {
  // Create 20 rows x 10 columns of empty data
  const data = []
  for (let i = 0; i < 20; i++) {
    data.push(Array(10).fill(''))
  }
  return data
}

function getColumnHeaders() {
  return ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']
}

onMounted(() => {
  const initialData = parseData(props.modelValue)

  hotInstance.value = new Handsontable(containerRef.value, {
    data: initialData,
    colHeaders: getColumnHeaders(),
    rowHeaders: true,
    width: '100%',
    height: 500,
    licenseKey: 'non-commercial-and-evaluation',
    readOnly: props.readOnly,

    // Styling
    className: 'htDark',

    // Features
    contextMenu: !props.readOnly,
    manualColumnResize: true,
    manualRowResize: true,
    minRows: 20,
    minCols: 10,
    minSpareRows: 1,
    minSpareCols: 1,

    // Auto-save on change
    afterChange: (changes) => {
      if (changes) {
        saveData()
      }
    },

    afterCreateRow: () => saveData(),
    afterCreateCol: () => saveData(),
    afterRemoveRow: () => saveData(),
    afterRemoveCol: () => saveData(),
  })

  isLoading.value = false
})

onBeforeUnmount(() => {
  if (hotInstance.value) {
    hotInstance.value.destroy()
  }
})

function saveData() {
  if (!hotInstance.value) return

  const data = hotInstance.value.getData()
  const jsonStr = JSON.stringify({ data })
  emit('update:modelValue', jsonStr)
  emit('change', jsonStr)
}

watch(() => props.readOnly, (readOnly) => {
  if (hotInstance.value) {
    hotInstance.value.updateSettings({ readOnly })
  }
})

defineExpose({
  saveData,
  getInstance: () => hotInstance.value
})
</script>

<template>
  <div class="spreadsheet-wrapper">
    <!-- Loading -->
    <div v-if="isLoading" class="flex items-center justify-center bg-dark-800 border border-dark-600 rounded-lg h-[500px]">
      <div class="text-center">
        <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
        <p class="text-gray-400 text-sm">Tabelle wird geladen...</p>
      </div>
    </div>

    <!-- Spreadsheet Container -->
    <div
      ref="containerRef"
      class="handsontable-container border border-dark-600 rounded-lg overflow-hidden"
      :style="{ display: isLoading ? 'none' : 'block' }"
    ></div>
  </div>
</template>

<style>
/* Dark theme for Handsontable */
.handsontable-container .handsontable {
  color: #e4e4e7;
  font-size: 13px;
}

.handsontable-container .handsontable th,
.handsontable-container .handsontable td {
  background: #1a1a2e;
  border-color: #27272a;
}

.handsontable-container .handsontable th {
  background: #27272a;
  color: #a1a1aa;
  font-weight: 600;
}

.handsontable-container .handsontable td.current,
.handsontable-container .handsontable td.area {
  background: rgba(59, 130, 246, 0.2);
}

.handsontable-container .handsontable .htCore td.htDimmed {
  color: #6b7280;
}

.handsontable-container .handsontable thead th .relative {
  padding: 4px 8px;
}

.handsontable-container .handsontable .wtBorder {
  background-color: #3b82f6;
}

.handsontable-container .handsontable .wtBorder.current {
  background-color: #3b82f6;
}

.handsontable-container .handsontable td.area-1::before,
.handsontable-container .handsontable td.area-2::before,
.handsontable-container .handsontable td.area-3::before,
.handsontable-container .handsontable td.area-4::before {
  background: rgba(59, 130, 246, 0.1);
}

/* Selected cell */
.handsontable-container .handsontable td.highlight {
  background: rgba(59, 130, 246, 0.3);
}

/* Input when editing */
.handsontable-container .handsontable .handsontableInput {
  background: #1e1e2e;
  color: #e4e4e7;
  border: 1px solid #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

/* Context menu */
.htContextMenu {
  background: #1e1e2e !important;
  border: 1px solid #27272a !important;
}

.htContextMenu td {
  background: #1e1e2e !important;
  color: #e4e4e7 !important;
}

.htContextMenu td:hover {
  background: #27272a !important;
}

.htContextMenu td.htDisabled {
  color: #4b5563 !important;
}

.htContextMenu td.htSeparator {
  border-top-color: #27272a !important;
}
</style>
