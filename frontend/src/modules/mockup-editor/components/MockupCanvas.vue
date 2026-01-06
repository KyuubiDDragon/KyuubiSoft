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

// Check if element is selected
const isSelected = (elementId) => {
  return mockupStore.selectedElementId === elementId
}

// Export function (exposed to parent)
const exportImage = async (options = {}) => {
  const { format = 'png', quality = 1, transparent = false } = options

  if (!canvasRef.value) {
    throw new Error('Canvas not found')
  }

  // Use html2canvas for export
  const html2canvas = (await import('html2canvas')).default

  const canvas = await html2canvas(canvasRef.value, {
    backgroundColor: transparent ? null : (mockupStore.backgroundColor === 'transparent' ? null : mockupStore.backgroundColor),
    scale: 2, // Higher resolution
    useCORS: true,
    allowTaint: true,
    logging: false,
  })

  // Convert to blob
  const mimeType = format === 'jpg' ? 'image/jpeg' : 'image/png'
  const blob = await new Promise(resolve => {
    canvas.toBlob(resolve, mimeType, quality)
  })

  // Download
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `mockup-${mockupStore.currentTemplate?.id || 'export'}-${Date.now()}.${format}`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

// Expose export function
defineExpose({ exportImage })

// Render text with highlight
const renderTextWithHighlight = (element) => {
  if (!element.highlightText) return element.text

  const parts = element.text.split(new RegExp(`(${element.highlightText})`, 'gi'))
  return parts.map((part, i) => {
    if (part.toLowerCase() === element.highlightText?.toLowerCase()) {
      return `<span style="color: ${element.highlightColor || '#f4b400'}">${part}</span>`
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
        'bg-checkered': mockupStore.backgroundColor === 'transparent',
      }"
    >
      <!-- Elements -->
      <template v-for="element in mockupStore.elements" :key="element.id">
        <!-- Background -->
        <div
          v-if="element.type === 'background'"
          class="absolute inset-0"
          :style="getBackgroundStyle(element)"
        />

        <!-- Container -->
        <div
          v-else-if="element.type === 'container'"
          :style="getElementStyle(element)"
          class="cursor-pointer transition-all"
          :class="{ 'ring-2 ring-amber-500 ring-offset-2 ring-offset-gray-900': isSelected(element.id) }"
          @click="handleElementClick(element.id)"
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
          :style="getElementStyle(element)"
          class="relative cursor-pointer overflow-hidden transition-all group"
          :class="{
            'ring-2 ring-amber-500 ring-offset-2 ring-offset-gray-900': isSelected(element.id),
            'ring-2 ring-amber-500/50': dragOverElementId === element.id,
          }"
          @click="handleElementClick(element.id)"
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
          }"
          class="cursor-pointer transition-all whitespace-pre-wrap"
          :class="{ 'ring-2 ring-amber-500 ring-offset-2 ring-offset-transparent rounded': isSelected(element.id) }"
          @click="handleElementClick(element.id)"
          v-html="element.highlightText ? renderTextWithHighlight(element) : element.text"
        />

        <!-- Line -->
        <div
          v-else-if="element.type === 'line'"
          :style="{
            ...getElementStyle(element),
            background: element.gradient || element.color,
          }"
          class="cursor-pointer transition-all"
          :class="{ 'ring-2 ring-amber-500 ring-offset-2 ring-offset-gray-900': isSelected(element.id) }"
          @click="handleElementClick(element.id)"
        />

        <!-- Corner -->
        <div
          v-else-if="element.type === 'corner'"
          :style="{
            position: 'absolute',
            left: `${element.x}px`,
            top: `${element.y}px`,
          }"
          class="cursor-pointer transition-all"
          :class="{ 'ring-2 ring-amber-500 ring-offset-2 ring-offset-transparent': isSelected(element.id) }"
          @click="handleElementClick(element.id)"
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
          }"
          class="cursor-pointer transition-all"
          :class="{ 'ring-2 ring-amber-500 ring-offset-2 ring-offset-gray-900': isSelected(element.id) }"
          @click="handleElementClick(element.id)"
        >
          {{ element.text }}
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
</style>
