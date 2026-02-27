<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useMockupStore } from '../stores/mockupStore'
import {
  XMarkIcon,
  TrashIcon,
  PlusCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  show: Boolean,
  elementId: String,
})

const emit = defineEmits(['close'])

const mockupStore = useMockupStore()

// Current tool
const currentTool = ref('circle') // circle, marker, arrow, text
const currentColor = ref('#f4b400')
const strokeWidth = ref(3)
const markerCounter = ref(1)

// Selected annotation for editing
const selectedAnnotationId = ref(null)

// Dragging state
const isDragging = ref(false)
const dragStartPos = ref({ x: 0, y: 0 })
const dragAnnotationStart = ref({ x: 0, y: 0 })

// Image container ref
const imageContainerRef = ref(null)

// Get current element
const element = computed(() => {
  return mockupStore.elements.find(el => el.id === props.elementId)
})

// Get annotations
const annotations = computed(() => {
  return element.value?.annotations || []
})

// Available tools
const tools = [
  { id: 'circle', name: 'Kreis', icon: '○' },
  { id: 'marker', name: 'Marker', icon: '①' },
  { id: 'arrow', name: 'Pfeil', icon: '→' },
  { id: 'text', name: 'Text', icon: 'T' },
]

// Colors
const colors = [
  '#f4b400', // Gold
  '#ef4444', // Red
  '#22c55e', // Green
  '#3b82f6', // Blue
  '#a855f7', // Purple
  '#ffffff', // White
]

// Reset marker counter when opening
watch(() => props.show, (newVal) => {
  if (newVal) {
    // Find highest marker number
    const markers = annotations.value.filter(a => a.type === 'marker')
    if (markers.length > 0) {
      const maxNum = Math.max(...markers.map(m => m.number || 0))
      markerCounter.value = maxNum + 1
    } else {
      markerCounter.value = 1
    }
  }
})

// Handle click on image to add annotation
const handleImageClick = (e) => {
  if (isDragging.value) return
  if (!imageContainerRef.value) return

  const rect = imageContainerRef.value.getBoundingClientRect()
  const x = ((e.clientX - rect.left) / rect.width) * 100
  const y = ((e.clientY - rect.top) / rect.height) * 100

  let annotation = {
    type: currentTool.value,
    x,
    y,
    color: currentColor.value,
  }

  switch (currentTool.value) {
    case 'circle':
      annotation.width = 15 // percentage
      annotation.height = 15
      annotation.strokeWidth = strokeWidth.value
      break
    case 'marker':
      annotation.number = markerCounter.value
      annotation.size = 32
      markerCounter.value++
      break
    case 'arrow':
      annotation.endX = x + 10
      annotation.endY = y + 10
      annotation.strokeWidth = strokeWidth.value
      break
    case 'text':
      annotation.text = 'Label'
      annotation.fontSize = 16
      break
  }

  mockupStore.addAnnotation(props.elementId, annotation)
}

// Handle annotation drag start
const startAnnotationDrag = (e, annotationId) => {
  e.stopPropagation()
  e.preventDefault()

  const annotation = annotations.value.find(a => a.id === annotationId)
  if (!annotation) return

  selectedAnnotationId.value = annotationId
  isDragging.value = true
  dragStartPos.value = { x: e.clientX, y: e.clientY }
  dragAnnotationStart.value = { x: annotation.x, y: annotation.y }

  document.addEventListener('mousemove', handleAnnotationDrag)
  document.addEventListener('mouseup', stopAnnotationDrag)
}

// Handle annotation drag
const handleAnnotationDrag = (e) => {
  if (!isDragging.value || !selectedAnnotationId.value) return
  if (!imageContainerRef.value) return

  const rect = imageContainerRef.value.getBoundingClientRect()
  const deltaX = ((e.clientX - dragStartPos.value.x) / rect.width) * 100
  const deltaY = ((e.clientY - dragStartPos.value.y) / rect.height) * 100

  const newX = Math.max(0, Math.min(100, dragAnnotationStart.value.x + deltaX))
  const newY = Math.max(0, Math.min(100, dragAnnotationStart.value.y + deltaY))

  mockupStore.updateAnnotation(props.elementId, selectedAnnotationId.value, {
    x: newX,
    y: newY,
  })
}

// Stop annotation drag
const stopAnnotationDrag = () => {
  isDragging.value = false
  document.removeEventListener('mousemove', handleAnnotationDrag)
  document.removeEventListener('mouseup', stopAnnotationDrag)
}

// Delete selected annotation
const deleteSelectedAnnotation = () => {
  if (selectedAnnotationId.value) {
    mockupStore.deleteAnnotation(props.elementId, selectedAnnotationId.value)
    selectedAnnotationId.value = null
  }
}

// Clear all annotations
const clearAllAnnotations = () => {
  mockupStore.clearAnnotations(props.elementId)
  selectedAnnotationId.value = null
  markerCounter.value = 1
}

// Select annotation
const selectAnnotation = (e, annotationId) => {
  e.stopPropagation()
  selectedAnnotationId.value = annotationId
}

// Deselect annotation
const deselectAnnotation = () => {
  selectedAnnotationId.value = null
}

// Close handler
const handleClose = () => {
  selectedAnnotationId.value = null
  emit('close')
}

// Keyboard shortcuts
const handleKeydown = (e) => {
  if (!props.show) return

  if (e.key === 'Delete' || e.key === 'Backspace') {
    if (selectedAnnotationId.value) {
      deleteSelectedAnnotation()
    }
  } else if (e.key === 'Escape') {
    if (selectedAnnotationId.value) {
      deselectAnnotation()
    } else {
      handleClose()
    }
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="show && element" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/80" @click="handleClose" />

        <!-- Modal -->
        <div class="relative bg-white/[0.04] rounded-xl shadow-float w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between px-4 py-3 border-b border-white/[0.06]">
            <h2 class="text-lg font-semibold text-white">Screenshot bearbeiten</h2>
            <button @click="handleClose" class="p-1 text-gray-400 hover:text-white transition-colors">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Content -->
          <div class="flex flex-1 overflow-hidden">
            <!-- Toolbar -->
            <div class="w-16 bg-white/[0.02] border-r border-white/[0.06] flex flex-col items-center py-4 gap-2">
              <!-- Tools -->
              <div class="space-y-1">
                <button
                  v-for="tool in tools"
                  :key="tool.id"
                  @click="currentTool = tool.id"
                  class="w-10 h-10 rounded-lg flex items-center justify-center text-lg transition-colors"
                  :class="currentTool === tool.id ? 'bg-amber-500 text-gray-900' : 'text-gray-400 hover:text-white hover:bg-white/[0.04]'"
                  :title="tool.name"
                >
                  {{ tool.icon }}
                </button>
              </div>

              <div class="w-8 h-px bg-white/[0.04] my-2" />

              <!-- Colors -->
              <div class="space-y-1">
                <button
                  v-for="color in colors"
                  :key="color"
                  @click="currentColor = color"
                  class="w-6 h-6 rounded-full border-2 transition-transform hover:scale-110"
                  :style="{ backgroundColor: color }"
                  :class="currentColor === color ? 'border-white scale-110' : 'border-transparent'"
                />
              </div>

              <div class="w-8 h-px bg-white/[0.04] my-2" />

              <!-- Actions -->
              <button
                v-if="selectedAnnotationId"
                @click="deleteSelectedAnnotation"
                class="w-10 h-10 rounded-lg flex items-center justify-center text-red-400 hover:text-red-300 hover:bg-red-500/20 transition-colors"
                title="Löschen (Del)"
              >
                <TrashIcon class="w-5 h-5" />
              </button>

              <button
                v-if="annotations.length > 0"
                @click="clearAllAnnotations"
                class="w-10 h-10 rounded-lg flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/[0.04] transition-colors"
                title="Alle löschen"
              >
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>

            <!-- Image Preview Area -->
            <div class="flex-1 p-4 overflow-auto bg-white/[0.01] flex items-center justify-center">
              <div
                ref="imageContainerRef"
                class="relative cursor-crosshair select-none"
                :style="{
                  maxWidth: '100%',
                  maxHeight: '100%',
                }"
                @click="handleImageClick"
              >
                <!-- Image -->
                <img
                  v-if="element.src"
                  :src="element.src"
                  class="max-w-full max-h-[70vh] rounded-lg"
                  :style="{ borderRadius: element.borderRadius ? `${element.borderRadius}px` : 0 }"
                  draggable="false"
                />
                <div v-else class="w-[600px] h-[400px] bg-white/[0.04] rounded-lg flex items-center justify-center text-gray-500">
                  Kein Bild vorhanden
                </div>

                <!-- Annotations Layer -->
                <svg class="absolute inset-0 w-full h-full pointer-events-none" style="overflow: visible;">
                  <template v-for="annotation in annotations" :key="annotation.id">
                    <!-- Circle -->
                    <ellipse
                      v-if="annotation.type === 'circle'"
                      :cx="`${annotation.x}%`"
                      :cy="`${annotation.y}%`"
                      :rx="`${annotation.width / 2}%`"
                      :ry="`${annotation.height / 2}%`"
                      fill="none"
                      :stroke="annotation.color"
                      :stroke-width="annotation.strokeWidth || 3"
                      class="pointer-events-auto cursor-move"
                      :class="{ 'opacity-50': selectedAnnotationId && selectedAnnotationId !== annotation.id }"
                      @mousedown="(e) => startAnnotationDrag(e, annotation.id)"
                      @click="(e) => selectAnnotation(e, annotation.id)"
                    />

                    <!-- Arrow -->
                    <g v-else-if="annotation.type === 'arrow'" class="pointer-events-auto cursor-move">
                      <defs>
                        <marker
                          :id="`arrowhead-${annotation.id}`"
                          markerWidth="10"
                          markerHeight="7"
                          refX="9"
                          refY="3.5"
                          orient="auto"
                        >
                          <polygon points="0 0, 10 3.5, 0 7" :fill="annotation.color" />
                        </marker>
                      </defs>
                      <line
                        :x1="`${annotation.x}%`"
                        :y1="`${annotation.y}%`"
                        :x2="`${annotation.endX || annotation.x + 10}%`"
                        :y2="`${annotation.endY || annotation.y + 10}%`"
                        :stroke="annotation.color"
                        :stroke-width="annotation.strokeWidth || 3"
                        :marker-end="`url(#arrowhead-${annotation.id})`"
                        :class="{ 'opacity-50': selectedAnnotationId && selectedAnnotationId !== annotation.id }"
                        @mousedown="(e) => startAnnotationDrag(e, annotation.id)"
                        @click="(e) => selectAnnotation(e, annotation.id)"
                      />
                    </g>
                  </template>
                </svg>

                <!-- HTML Annotations (Markers and Text) -->
                <template v-for="annotation in annotations" :key="`html-${annotation.id}`">
                  <!-- Marker -->
                  <div
                    v-if="annotation.type === 'marker'"
                    class="absolute flex items-center justify-center rounded-full font-bold text-gray-900 cursor-move select-none pointer-events-auto"
                    :style="{
                      left: `${annotation.x}%`,
                      top: `${annotation.y}%`,
                      width: `${annotation.size || 32}px`,
                      height: `${annotation.size || 32}px`,
                      backgroundColor: annotation.color,
                      transform: 'translate(-50%, -50%)',
                      fontSize: `${(annotation.size || 32) * 0.5}px`,
                      boxShadow: '0 2px 8px rgba(0,0,0,0.4)',
                    }"
                    :class="{ 'opacity-50': selectedAnnotationId && selectedAnnotationId !== annotation.id, 'ring-2 ring-white': selectedAnnotationId === annotation.id }"
                    @mousedown="(e) => startAnnotationDrag(e, annotation.id)"
                    @click="(e) => selectAnnotation(e, annotation.id)"
                  >
                    {{ annotation.number }}
                  </div>

                  <!-- Text Label -->
                  <div
                    v-else-if="annotation.type === 'text'"
                    class="absolute px-2 py-1 rounded cursor-move select-none pointer-events-auto"
                    :style="{
                      left: `${annotation.x}%`,
                      top: `${annotation.y}%`,
                      color: annotation.color,
                      fontSize: `${annotation.fontSize || 16}px`,
                      fontWeight: 600,
                      textShadow: '0 1px 3px rgba(0,0,0,0.8)',
                      transform: 'translate(-50%, -50%)',
                    }"
                    :class="{ 'opacity-50': selectedAnnotationId && selectedAnnotationId !== annotation.id, 'ring-2 ring-white rounded': selectedAnnotationId === annotation.id }"
                    @mousedown="(e) => startAnnotationDrag(e, annotation.id)"
                    @click="(e) => selectAnnotation(e, annotation.id)"
                  >
                    {{ annotation.text }}
                  </div>
                </template>

                <!-- Selection Ring for SVG elements -->
                <div
                  v-if="selectedAnnotationId"
                  class="absolute border-2 border-dashed border-white/50 rounded pointer-events-none"
                  :style="getSelectionBoxStyle()"
                />
              </div>
            </div>

            <!-- Properties Panel -->
            <div class="w-64 bg-white/[0.04] border-l border-white/[0.06] p-4 overflow-y-auto">
              <h3 class="text-sm font-medium text-gray-400 mb-4">Eigenschaften</h3>

              <template v-if="selectedAnnotationId">
                <div class="space-y-4">
                  <!-- Annotation type info -->
                  <div>
                    <label class="text-xs text-gray-500">Typ</label>
                    <div class="text-white capitalize">
                      {{ annotations.find(a => a.id === selectedAnnotationId)?.type }}
                    </div>
                  </div>

                  <!-- Text input for text annotations -->
                  <div v-if="annotations.find(a => a.id === selectedAnnotationId)?.type === 'text'">
                    <label class="text-xs text-gray-500">Text</label>
                    <input
                      type="text"
                      :value="annotations.find(a => a.id === selectedAnnotationId)?.text"
                      @input="(e) => mockupStore.updateAnnotation(elementId, selectedAnnotationId, { text: e.target.value })"
                      class="w-full mt-1 px-3 py-2 bg-white/[0.04] border border-white/[0.06] rounded-lg text-white text-sm focus:outline-none focus:border-amber-500"
                    />
                  </div>

                  <!-- Size for markers -->
                  <div v-if="annotations.find(a => a.id === selectedAnnotationId)?.type === 'marker'">
                    <label class="text-xs text-gray-500">Größe</label>
                    <input
                      type="range"
                      min="20"
                      max="60"
                      :value="annotations.find(a => a.id === selectedAnnotationId)?.size || 32"
                      @input="(e) => mockupStore.updateAnnotation(elementId, selectedAnnotationId, { size: parseInt(e.target.value) })"
                      class="w-full mt-1"
                    />
                  </div>

                  <!-- Stroke width for circles and arrows -->
                  <div v-if="['circle', 'arrow'].includes(annotations.find(a => a.id === selectedAnnotationId)?.type)">
                    <label class="text-xs text-gray-500">Strichstärke</label>
                    <input
                      type="range"
                      min="1"
                      max="8"
                      :value="annotations.find(a => a.id === selectedAnnotationId)?.strokeWidth || 3"
                      @input="(e) => mockupStore.updateAnnotation(elementId, selectedAnnotationId, { strokeWidth: parseInt(e.target.value) })"
                      class="w-full mt-1"
                    />
                  </div>

                  <!-- Circle size -->
                  <div v-if="annotations.find(a => a.id === selectedAnnotationId)?.type === 'circle'">
                    <label class="text-xs text-gray-500">Breite (%)</label>
                    <input
                      type="range"
                      min="5"
                      max="50"
                      :value="annotations.find(a => a.id === selectedAnnotationId)?.width || 15"
                      @input="(e) => mockupStore.updateAnnotation(elementId, selectedAnnotationId, { width: parseInt(e.target.value) })"
                      class="w-full mt-1"
                    />
                    <label class="text-xs text-gray-500 mt-2 block">Höhe (%)</label>
                    <input
                      type="range"
                      min="5"
                      max="50"
                      :value="annotations.find(a => a.id === selectedAnnotationId)?.height || 15"
                      @input="(e) => mockupStore.updateAnnotation(elementId, selectedAnnotationId, { height: parseInt(e.target.value) })"
                      class="w-full mt-1"
                    />
                  </div>

                  <!-- Color -->
                  <div>
                    <label class="text-xs text-gray-500">Farbe</label>
                    <div class="flex gap-1 mt-1">
                      <button
                        v-for="color in colors"
                        :key="color"
                        @click="mockupStore.updateAnnotation(elementId, selectedAnnotationId, { color })"
                        class="w-6 h-6 rounded-full border-2 transition-transform hover:scale-110"
                        :style="{ backgroundColor: color }"
                        :class="annotations.find(a => a.id === selectedAnnotationId)?.color === color ? 'border-white scale-110' : 'border-transparent'"
                      />
                    </div>
                  </div>

                  <!-- Delete button -->
                  <button
                    @click="deleteSelectedAnnotation"
                    class="w-full mt-4 px-4 py-2 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30 transition-colors flex items-center justify-center gap-2"
                  >
                    <TrashIcon class="w-4 h-4" />
                    Löschen
                  </button>
                </div>
              </template>

              <template v-else>
                <p class="text-sm text-gray-500">
                  Klicke auf das Bild um eine Annotation hinzuzufügen, oder wähle eine bestehende aus.
                </p>

                <div class="mt-4 space-y-2">
                  <div class="text-xs text-gray-500">Aktives Tool</div>
                  <div class="flex flex-wrap gap-1">
                    <button
                      v-for="tool in tools"
                      :key="tool.id"
                      @click="currentTool = tool.id"
                      class="px-3 py-1.5 rounded-lg text-sm transition-colors"
                      :class="currentTool === tool.id ? 'bg-amber-500 text-gray-900' : 'bg-white/[0.04] text-gray-300 hover:bg-white/[0.04]'"
                    >
                      {{ tool.name }}
                    </button>
                  </div>
                </div>

                <div class="mt-4">
                  <div class="text-xs text-gray-500 mb-2">Tastenkürzel</div>
                  <div class="text-xs text-gray-400 space-y-1">
                    <div><kbd class="px-1 bg-white/[0.04] rounded">Del</kbd> - Löschen</div>
                    <div><kbd class="px-1 bg-white/[0.04] rounded">Esc</kbd> - Abwählen/Schließen</div>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 px-4 py-3 border-t border-white/[0.06]">
            <span class="text-sm text-gray-500 mr-auto">
              {{ annotations.length }} Annotation{{ annotations.length !== 1 ? 'en' : '' }}
            </span>
            <button
              @click="handleClose"
              class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-gray-900 font-medium rounded-lg transition-colors"
            >
              Fertig
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script>
export default {
  methods: {
    getSelectionBoxStyle() {
      // This could be enhanced to show a selection box around SVG elements
      return { display: 'none' }
    }
  }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

input[type="range"] {
  @apply h-2 bg-white/[0.04] rounded-lg appearance-none cursor-pointer;
}

input[type="range"]::-webkit-slider-thumb {
  @apply appearance-none w-4 h-4 bg-amber-500 rounded-full cursor-pointer;
}
</style>
