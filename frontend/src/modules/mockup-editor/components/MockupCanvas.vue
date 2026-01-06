<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useMockupStore } from '../stores/mockupStore'
import { useToast } from '@/composables/useToast'
import {
  PhotoIcon,
  ArrowUpTrayIcon,
} from '@heroicons/vue/24/outline'

const toast = useToast()
const mockupStore = useMockupStore()

const canvasRef = ref(null)
const isDraggingOver = ref(false)
const dragOverElementId = ref(null)
const isExporting = ref(false)
const exportTransparent = ref(false)

// Element dragging state
const isDraggingElement = ref(false)
const dragStartPos = ref({ x: 0, y: 0 })
const dragElementStart = ref({ x: 0, y: 0 })
const draggingElementId = ref(null)

// Computed
const canvasStyle = computed(() => ({
  width: `${mockupStore.canvasWidth}px`,
  height: `${mockupStore.canvasHeight}px`,
  transform: `scale(${mockupStore.zoom})`,
  transformOrigin: 'center center',
}))

// Handle drag & drop
const handleDragOver = (e, elementId = null) => {
  e.preventDefault()
  isDraggingOver.value = true
  dragOverElementId.value = elementId
}

const handleDragLeave = () => {
  isDraggingOver.value = false
  dragOverElementId.value = null
}

const handleDrop = async (e, elementId) => {
  e.preventDefault()
  isDraggingOver.value = false
  dragOverElementId.value = null

  const files = e.dataTransfer?.files
  if (!files || files.length === 0) return

  const file = files[0]
  if (!file.type.startsWith('image/')) {
    toast.error('Nur Bilddateien erlaubt')
    return
  }

  // Convert to base64
  const reader = new FileReader()
  reader.onload = (event) => {
    mockupStore.setElementImage(elementId, event.target.result)
  }
  reader.readAsDataURL(file)
}

// Handle file input
const handleFileSelect = (e, elementId) => {
  const file = e.target?.files?.[0]
  if (!file) return

  if (!file.type.startsWith('image/')) {
    toast.error('Nur Bilddateien erlaubt')
    return
  }

  const reader = new FileReader()
  reader.onload = (event) => {
    mockupStore.setElementImage(elementId, event.target.result)
  }
  reader.readAsDataURL(file)
}

// Handle element click
const handleElementClick = (elementId) => {
  mockupStore.selectElement(elementId)
}

// Element dragging for repositioning
const startElementDrag = (e, elementId) => {
  // Don't start drag if clicking on file input
  if (e.target.tagName === 'INPUT') return

  e.preventDefault()
  e.stopPropagation()

  const element = mockupStore.elements.find(el => el.id === elementId)
  if (!element) return

  // Select the element
  mockupStore.selectElement(elementId)

  isDraggingElement.value = true
  draggingElementId.value = elementId
  dragStartPos.value = { x: e.clientX, y: e.clientY }
  dragElementStart.value = { x: element.x, y: element.y }

  // Add global listeners
  document.addEventListener('mousemove', handleElementDrag)
  document.addEventListener('mouseup', stopElementDrag)
}

const handleElementDrag = (e) => {
  if (!isDraggingElement.value || !draggingElementId.value) return

  // Calculate delta (account for zoom)
  const zoom = mockupStore.zoom
  const deltaX = (e.clientX - dragStartPos.value.x) / zoom
  const deltaY = (e.clientY - dragStartPos.value.y) / zoom

  // Calculate new position
  const newX = Math.round(dragElementStart.value.x + deltaX)
  const newY = Math.round(dragElementStart.value.y + deltaY)

  // Update element position
  mockupStore.updateElement(draggingElementId.value, {
    x: newX,
    y: newY,
  })
}

const stopElementDrag = () => {
  isDraggingElement.value = false
  draggingElementId.value = null

  // Remove global listeners
  document.removeEventListener('mousemove', handleElementDrag)
  document.removeEventListener('mouseup', stopElementDrag)
}

// Render element style
const getElementStyle = (element) => {
  const base = {
    position: 'absolute',
    left: `${element.x}px`,
    top: `${element.y}px`,
    width: element.width ? `${element.width}px` : 'auto',
    height: element.height ? `${element.height}px` : 'auto',
  }

  if (element.borderRadius) {
    base.borderRadius = typeof element.borderRadius === 'number'
      ? `${element.borderRadius}px`
      : element.borderRadius
  }

  if (element.boxShadow) {
    base.boxShadow = element.boxShadow
  }

  return base
}

// Get background style
const getBackgroundStyle = (element) => {
  if (element.gradient) {
    return { background: element.gradient }
  }
  return { backgroundColor: element.color || 'transparent' }
}

// Check if element is selected (hide during export)
const isSelected = (elementId) => {
  if (isExporting.value) return false
  return mockupStore.selectedElementId === elementId
}

// Export function (exposed to parent)
const exportImage = async (options = {}) => {
  const { format = 'png', quality = 1, transparent = false } = options

  if (!canvasRef.value) {
    throw new Error('Canvas not found')
  }

  // Use html-to-image for better CSS support (clip-path, transforms, etc.)
  const htmlToImage = await import('html-to-image')

  // Get the actual canvas element (not the zoomed wrapper)
  const targetElement = canvasRef.value

  // Temporarily reset zoom for export and hide selection
  const originalTransform = targetElement.style.transform
  targetElement.style.transform = 'none'
  isExporting.value = true
  exportTransparent.value = transparent

  // Wait for Vue to update the DOM (remove selection rings and background if transparent)
  await nextTick()

  try {
    let dataUrl

    // Determine background color
    const bgColor = transparent || mockupStore.backgroundColor === 'transparent'
      ? undefined
      : mockupStore.backgroundColor || '#0d0d0f'

    const exportOptions = {
      quality: quality,
      pixelRatio: 2, // Higher resolution
      backgroundColor: bgColor,
      style: {
        transform: 'none',
      },
      // Filter out selection rings for export
      filter: (node) => {
        // Remove ring classes for clean export
        if (node.classList && (
          node.classList.contains('ring-2') ||
          node.classList.contains('ring-amber-500')
        )) {
          return true // Still include, but the inline filter won't show the ring
        }
        return true
      }
    }

    if (format === 'jpg') {
      dataUrl = await htmlToImage.toJpeg(targetElement, exportOptions)
    } else {
      dataUrl = await htmlToImage.toPng(targetElement, exportOptions)
    }

    // Download
    const link = document.createElement('a')
    link.href = dataUrl
    link.download = `mockup-${mockupStore.currentTemplate?.id || 'export'}-${Date.now()}.${format}`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } finally {
    // Restore zoom and selection visibility
    targetElement.style.transform = originalTransform
    isExporting.value = false
    exportTransparent.value = false
  }
}

// Expose export function
defineExpose({ exportImage })

// Render text with highlight (supports multiple words)
const renderTextWithHighlight = (element) => {
  if (!element.highlightText && !element.highlightWords) return element.text

  // Support both old single-word format and new multi-word format
  let highlightList = []

  if (element.highlightWords && element.highlightWords.length > 0) {
    highlightList = element.highlightWords
  } else if (element.highlightText) {
    highlightList = [{ text: element.highlightText, color: element.highlightColor || '#f4b400' }]
  }

  if (highlightList.length === 0) return element.text

  // Build regex to match all highlight words
  const escapedWords = highlightList.map(h => h.text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
  const regex = new RegExp(`(${escapedWords.join('|')})`, 'gi')

  const parts = element.text.split(regex)
  return parts.map((part) => {
    const highlightMatch = highlightList.find(h => h.text.toLowerCase() === part.toLowerCase())
    if (highlightMatch) {
      return `<span style="color: ${highlightMatch.color || '#f4b400'}">${part}</span>`
    }
    return part
  }).join('')
}
</script>

<template>
  <div class="relative inline-block">
    <!-- Canvas -->
    <div
      ref="canvasRef"
      :style="canvasStyle"
      class="relative shadow-2xl"
      :class="{
        'bg-checkered': mockupStore.backgroundColor === 'transparent' && !isExporting,
        'exporting': isExporting,
      }"
    >
      <!-- Elements -->
      <template v-for="element in mockupStore.elements" :key="element.id">
        <!-- Background (hidden during transparent export) -->
        <div
          v-if="element.type === 'background' && !exportTransparent"
          class="absolute inset-0"
          :style="getBackgroundStyle(element)"
        />

        <!-- Container -->
        <div
          v-else-if="element.type === 'container'"
          :style="{
            ...getElementStyle(element),
            cursor: isDraggingElement ? 'grabbing' : 'grab',
          }"
          class="transition-all"
          :class="{ 'ring-2 ring-amber-500 ring-offset-2 ring-offset-gray-900': isSelected(element.id) }"
          @mousedown="(e) => startElementDrag(e, element.id)"
        >
          <div
            class="w-full h-full"
            :style="{
              backgroundColor: element.backgroundColor,
              border: element.border,
              borderRadius: element.borderRadius ? `${element.borderRadius}px` : 0,
              boxShadow: element.boxShadow,
              clipPath: element.clipPath,
            }"
          />
        </div>

        <!-- Image -->
        <div
          v-else-if="element.type === 'image'"
          :style="{
            ...getElementStyle(element),
            cursor: isDraggingElement ? 'grabbing' : 'grab',
          }"
          class="relative overflow-hidden transition-all group"
          :class="{
            'ring-2 ring-amber-500 ring-offset-2 ring-offset-gray-900': isSelected(element.id),
            'ring-2 ring-amber-500/50': !isExporting && dragOverElementId === element.id,
          }"
          @mousedown="(e) => startElementDrag(e, element.id)"
          @dragover="(e) => handleDragOver(e, element.id)"
          @dragleave="handleDragLeave"
          @drop="(e) => handleDrop(e, element.id)"
        >
          <!-- Image Content -->
          <template v-if="element.src">
            <img
              :src="element.src"
              :alt="element.placeholder"
              class="w-full h-full"
              :style="{
                objectFit: element.objectFit || 'cover',
                clipPath: element.clipPath,
              }"
            />
            <!-- Overlay -->
            <div
              v-if="element.overlay"
              class="absolute inset-0 pointer-events-none"
              :style="{ background: element.overlay }"
            />
          </template>

          <!-- Placeholder -->
          <template v-else>
            <div
              class="w-full h-full flex flex-col items-center justify-center bg-gray-800/80 border-2 border-dashed border-gray-600 hover:border-amber-500/50 transition-colors"
              :style="{ clipPath: element.clipPath }"
            >
              <input
                type="file"
                accept="image/*"
                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                @change="(e) => handleFileSelect(e, element.id)"
              />
              <PhotoIcon class="w-12 h-12 text-gray-500" />
              <span class="mt-2 text-sm text-gray-400">{{ element.placeholder }}</span>
              <span class="mt-1 text-xs text-gray-500">Klicken oder Bild ablegen</span>
            </div>
          </template>

          <!-- Upload overlay on hover if has image -->
          <div
            v-if="element.src"
            class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center z-20"
          >
            <input
              type="file"
              accept="image/*"
              class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-30"
              @change="(e) => handleFileSelect(e, element.id)"
            />
            <ArrowUpTrayIcon class="w-8 h-8 text-white" />
            <span class="mt-2 text-sm text-white">Bild ersetzen</span>
          </div>
        </div>

        <!-- Text -->
        <div
          v-else-if="element.type === 'text'"
          :style="{
            ...getElementStyle(element),
            fontFamily: element.fontFamily,
            fontSize: `${element.fontSize}px`,
            fontWeight: element.fontWeight,
            color: element.color,
            letterSpacing: element.letterSpacing,
            textTransform: element.textTransform,
            lineHeight: element.lineHeight,
            textAlign: element.textAlign,
            textShadow: element.textShadow,
            cursor: isDraggingElement ? 'grabbing' : 'grab',
          }"
          class="transition-all whitespace-pre-wrap select-none"
          :class="{ 'ring-2 ring-amber-500 ring-offset-2 ring-offset-transparent rounded': isSelected(element.id) }"
          @mousedown="(e) => startElementDrag(e, element.id)"
          v-html="element.highlightText ? renderTextWithHighlight(element) : element.text"
        />

        <!-- Line -->
        <div
          v-else-if="element.type === 'line'"
          :style="{
            ...getElementStyle(element),
            background: element.gradient || element.color,
            cursor: isDraggingElement ? 'grabbing' : 'grab',
          }"
          class="transition-all"
          :class="{ 'ring-2 ring-amber-500 ring-offset-2 ring-offset-gray-900': isSelected(element.id) }"
          @mousedown="(e) => startElementDrag(e, element.id)"
        />

        <!-- Corner -->
        <div
          v-else-if="element.type === 'corner'"
          :style="{
            position: 'absolute',
            left: `${element.x}px`,
            top: `${element.y}px`,
            cursor: isDraggingElement ? 'grabbing' : 'grab',
          }"
          class="transition-all"
          :class="{ 'ring-2 ring-amber-500 ring-offset-2 ring-offset-transparent': isSelected(element.id) }"
          @mousedown="(e) => startElementDrag(e, element.id)"
        >
          <!-- Top-Left -->
          <svg
            v-if="element.position === 'top-left'"
            :width="element.size"
            :height="element.size"
            viewBox="0 0 60 60"
          >
            <path
              :d="`M0,${element.size} L0,0 L${element.size},0`"
              fill="none"
              :stroke="element.color"
              :stroke-width="element.thickness"
            />
          </svg>
          <!-- Top-Right -->
          <svg
            v-else-if="element.position === 'top-right'"
            :width="element.size"
            :height="element.size"
            viewBox="0 0 60 60"
            style="transform: translateX(-100%)"
          >
            <path
              :d="`M0,0 L${element.size},0 L${element.size},${element.size}`"
              fill="none"
              :stroke="element.color"
              :stroke-width="element.thickness"
            />
          </svg>
          <!-- Bottom-Left -->
          <svg
            v-else-if="element.position === 'bottom-left'"
            :width="element.size"
            :height="element.size"
            viewBox="0 0 60 60"
            style="transform: translateY(-100%)"
          >
            <path
              :d="`M0,0 L0,${element.size} L${element.size},${element.size}`"
              fill="none"
              :stroke="element.color"
              :stroke-width="element.thickness"
            />
          </svg>
          <!-- Bottom-Right -->
          <svg
            v-else-if="element.position === 'bottom-right'"
            :width="element.size"
            :height="element.size"
            viewBox="0 0 60 60"
            style="transform: translate(-100%, -100%)"
          >
            <path
              :d="`M${element.size},0 L${element.size},${element.size} L0,${element.size}`"
              fill="none"
              :stroke="element.color"
              :stroke-width="element.thickness"
            />
          </svg>
        </div>

        <!-- Button -->
        <div
          v-else-if="element.type === 'button'"
          :style="{
            ...getElementStyle(element),
            backgroundColor: element.backgroundColor,
            color: element.color,
            fontFamily: element.fontFamily,
            fontSize: `${element.fontSize}px`,
            fontWeight: element.fontWeight,
            borderRadius: `${element.borderRadius}px`,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            cursor: isDraggingElement ? 'grabbing' : 'grab',
          }"
          class="transition-all select-none"
          :class="{ 'ring-2 ring-amber-500 ring-offset-2 ring-offset-gray-900': isSelected(element.id) }"
          @mousedown="(e) => startElementDrag(e, element.id)"
        >
          {{ element.text }}
        </div>

        <!-- Chip (Feature Tag) -->
        <div
          v-else-if="element.type === 'chip'"
          :style="{
            position: 'absolute',
            left: `${element.x}px`,
            top: `${element.y}px`,
            fontFamily: element.fontFamily,
            fontSize: `${element.fontSize}px`,
            color: element.color,
            backgroundColor: element.backgroundColor,
            border: element.border,
            borderRadius: `${element.borderRadius}px`,
            padding: element.padding || '8px 10px',
            display: 'inline-flex',
            alignItems: 'center',
            gap: '8px',
            cursor: isDraggingElement ? 'grabbing' : 'grab',
          }"
          class="transition-all whitespace-nowrap select-none"
          :class="{ 'ring-2 ring-amber-500 ring-offset-2 ring-offset-gray-900': isSelected(element.id) }"
          @mousedown="(e) => startElementDrag(e, element.id)"
        >
          {{ element.text }}
        </div>

        <!-- Stat Box -->
        <div
          v-else-if="element.type === 'stat'"
          :style="{
            position: 'absolute',
            left: `${element.x}px`,
            top: `${element.y}px`,
            width: `${element.width}px`,
            cursor: isDraggingElement ? 'grabbing' : 'grab',
          }"
          class="transition-all select-none"
          :class="{ 'ring-2 ring-amber-500 ring-offset-2 ring-offset-gray-900': isSelected(element.id) }"
          @mousedown="(e) => startElementDrag(e, element.id)"
        >
          <div class="bg-[rgba(28,28,31,0.72)] border border-[rgba(255,255,255,0.08)] rounded-xl p-3">
            <div class="text-xs text-[#606068]">{{ element.label }}</div>
            <div
              class="mt-0.5 font-mono font-medium text-sm text-white"
              v-html="element.highlightText ? renderTextWithHighlight(element) : element.value"
            />
          </div>
        </div>

        <!-- 3D Screen -->
        <div
          v-else-if="element.type === 'screen3d'"
          :style="{
            position: 'absolute',
            left: `${element.x}px`,
            top: `${element.y}px`,
            width: `${element.width}px`,
            height: `${element.height}px`,
            transform: element.perspective === 'left'
              ? 'perspective(900px) rotateY(14deg) rotateZ(-0.8deg)'
              : element.perspective === 'right'
              ? 'perspective(900px) rotateY(-14deg) rotateZ(0.8deg)'
              : 'perspective(900px) scale(1.04)',
            transformOrigin: 'bottom center',
            opacity: element.perspective === 'center' ? 1 : 0.95,
            cursor: isDraggingElement ? 'grabbing' : 'grab',
          }"
          class="transition-all group"
          :class="{
            'ring-2 ring-amber-500 ring-offset-2 ring-offset-gray-900': isSelected(element.id),
            'ring-2 ring-amber-500/50': !isExporting && dragOverElementId === element.id,
          }"
          @mousedown="(e) => startElementDrag(e, element.id)"
          @dragover="(e) => handleDragOver(e, element.id)"
          @dragleave="handleDragLeave"
          @drop="(e) => handleDrop(e, element.id)"
        >
          <div
            class="w-full h-full rounded-xl bg-[#1c1c1f] border border-[rgba(255,255,255,0.08)] overflow-hidden relative"
            :style="{
              boxShadow: '0 22px 70px rgba(0,0,0,0.55), inset 0 1px 0 rgba(255,255,255,0.06)',
              borderRadius: `${element.borderRadius}px`,
            }"
          >
            <!-- Gold micro-line -->
            <div class="absolute top-0 left-3.5 right-3.5 h-0.5 bg-gradient-to-r from-transparent via-[rgba(244,180,0,0.85)] to-transparent opacity-55 z-10" />

            <!-- Reflection -->
            <div class="absolute -inset-x-[30%] -top-[40%] h-[75%] bg-gradient-to-br from-white/[0.14] via-white/[0.03] to-transparent rotate-[10deg] pointer-events-none z-10" />

            <!-- Image Content -->
            <template v-if="element.src">
              <img
                :src="element.src"
                :alt="element.placeholder"
                class="w-full h-full object-cover relative z-0"
              />
            </template>

            <!-- Placeholder -->
            <template v-else>
              <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-[#202025] to-[#151519] border-2 border-dashed border-gray-600 hover:border-amber-500/50 transition-colors">
                <input
                  type="file"
                  accept="image/*"
                  class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20"
                  @change="(e) => handleFileSelect(e, element.id)"
                />
                <PhotoIcon class="w-10 h-10 text-gray-500" />
                <span class="mt-2 text-sm text-gray-400">{{ element.placeholder }}</span>
              </div>
            </template>

            <!-- Upload overlay on hover if has image -->
            <div
              v-if="element.src"
              class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center z-20"
            >
              <input
                type="file"
                accept="image/*"
                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-30"
                @change="(e) => handleFileSelect(e, element.id)"
              />
              <ArrowUpTrayIcon class="w-8 h-8 text-white" />
              <span class="mt-2 text-sm text-white">Bild ersetzen</span>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.bg-checkered {
  background-image:
    linear-gradient(45deg, #1a1a1a 25%, transparent 25%),
    linear-gradient(-45deg, #1a1a1a 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, #1a1a1a 75%),
    linear-gradient(-45deg, transparent 75%, #1a1a1a 75%);
  background-size: 20px 20px;
  background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
  background-color: #0f0f0f;
}

/* Hide all selection rings during export */
.exporting :deep(.ring-2),
.exporting :deep(.ring-amber-500),
.exporting :deep(.ring-offset-2) {
  --tw-ring-color: transparent !important;
  --tw-ring-offset-color: transparent !important;
  --tw-ring-shadow: none !important;
  --tw-ring-offset-shadow: none !important;
  box-shadow: none !important;
}
</style>
