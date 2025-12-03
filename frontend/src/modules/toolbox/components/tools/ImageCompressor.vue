<script setup>
import { ref, computed } from 'vue'

const originalImage = ref(null)
const compressedImage = ref(null)
const originalSize = ref(0)
const compressedSize = ref(0)
const quality = ref(80)
const maxWidth = ref(1920)
const maxHeight = ref(1080)
const maintainAspect = ref(true)
const outputFormat = ref('jpeg') // 'jpeg', 'png', 'webp'
const isProcessing = ref(false)
const fileName = ref('')

const compressionRatio = computed(() => {
  if (!originalSize.value || !compressedSize.value) return 0
  return Math.round((1 - compressedSize.value / originalSize.value) * 100)
})

const formatSize = (bytes) => {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB'
}

async function handleFileSelect(event) {
  const file = event.target.files[0]
  if (!file || !file.type.startsWith('image/')) return

  fileName.value = file.name
  originalSize.value = file.size

  // Read original image
  const reader = new FileReader()
  reader.onload = (e) => {
    originalImage.value = e.target.result
    compressImage()
  }
  reader.readAsDataURL(file)
}

async function compressImage() {
  if (!originalImage.value) return

  isProcessing.value = true

  try {
    const img = new Image()
    img.onload = () => {
      // Calculate new dimensions
      let newWidth = img.width
      let newHeight = img.height

      if (newWidth > maxWidth.value || newHeight > maxHeight.value) {
        if (maintainAspect.value) {
          const ratio = Math.min(maxWidth.value / newWidth, maxHeight.value / newHeight)
          newWidth = Math.round(newWidth * ratio)
          newHeight = Math.round(newHeight * ratio)
        } else {
          newWidth = Math.min(newWidth, maxWidth.value)
          newHeight = Math.min(newHeight, maxHeight.value)
        }
      }

      // Create canvas and compress
      const canvas = document.createElement('canvas')
      canvas.width = newWidth
      canvas.height = newHeight

      const ctx = canvas.getContext('2d')
      ctx.drawImage(img, 0, 0, newWidth, newHeight)

      // Convert to blob
      const mimeType = `image/${outputFormat.value}`
      const qualityValue = outputFormat.value === 'png' ? undefined : quality.value / 100

      canvas.toBlob((blob) => {
        compressedSize.value = blob.size
        compressedImage.value = URL.createObjectURL(blob)
        isProcessing.value = false
      }, mimeType, qualityValue)
    }

    img.src = originalImage.value
  } catch (error) {
    console.error('Compression error:', error)
    isProcessing.value = false
  }
}

function downloadCompressed() {
  if (!compressedImage.value) return

  const link = document.createElement('a')
  const extension = outputFormat.value === 'jpeg' ? 'jpg' : outputFormat.value
  const baseName = fileName.value.replace(/\.[^.]+$/, '')
  link.download = `${baseName}_compressed.${extension}`
  link.href = compressedImage.value
  link.click()
}

function reset() {
  originalImage.value = null
  compressedImage.value = null
  originalSize.value = 0
  compressedSize.value = 0
  fileName.value = ''
}

// Watch for setting changes
import { watch } from 'vue'
watch([quality, maxWidth, maxHeight, maintainAspect, outputFormat], () => {
  if (originalImage.value) {
    compressImage()
  }
})
</script>

<template>
  <div class="space-y-4">
    <!-- File Upload -->
    <div
      class="border-2 border-dashed border-dark-600 rounded-lg p-8 text-center hover:border-primary-500 transition-colors cursor-pointer"
      @click="$refs.fileInput.click()"
      @dragover.prevent
      @drop.prevent="handleFileSelect({ target: { files: $event.dataTransfer.files } })"
    >
      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        class="hidden"
        @change="handleFileSelect"
      />
      <div v-if="!originalImage" class="text-gray-400">
        <div class="text-4xl mb-2">🖼️</div>
        <p>Bild hierher ziehen oder klicken zum Auswählen</p>
        <p class="text-xs text-gray-500 mt-1">PNG, JPG, WebP, GIF</p>
      </div>
      <div v-else class="text-green-400">
        <p>{{ fileName }}</p>
        <p class="text-xs text-gray-400 mt-1">Klicken für neues Bild</p>
      </div>
    </div>

    <!-- Settings -->
    <div v-if="originalImage" class="grid grid-cols-2 gap-4">
      <div>
        <label class="text-sm text-gray-400 mb-1 block">
          Qualität: {{ quality }}%
        </label>
        <input
          v-model.number="quality"
          type="range"
          min="1"
          max="100"
          class="w-full"
        />
      </div>
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Format</label>
        <select v-model="outputFormat" class="input w-full">
          <option value="jpeg">JPEG</option>
          <option value="png">PNG</option>
          <option value="webp">WebP</option>
        </select>
      </div>
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Max. Breite</label>
        <input
          v-model.number="maxWidth"
          type="number"
          min="100"
          max="10000"
          class="input w-full"
        />
      </div>
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Max. Höhe</label>
        <input
          v-model.number="maxHeight"
          type="number"
          min="100"
          max="10000"
          class="input w-full"
        />
      </div>
    </div>

    <label v-if="originalImage" class="flex items-center gap-2 text-sm text-gray-400">
      <input type="checkbox" v-model="maintainAspect" class="rounded bg-dark-700" />
      Seitenverhältnis beibehalten
    </label>

    <!-- Preview -->
    <div v-if="originalImage && compressedImage" class="space-y-4">
      <!-- Size Comparison -->
      <div class="grid grid-cols-3 gap-4 text-center">
        <div class="p-3 bg-dark-700 rounded-lg">
          <div class="text-xs text-gray-500">Original</div>
          <div class="text-lg font-mono text-white">{{ formatSize(originalSize) }}</div>
        </div>
        <div class="p-3 bg-primary-900/30 rounded-lg">
          <div class="text-xs text-gray-500">Ersparnis</div>
          <div class="text-lg font-mono text-primary-400">{{ compressionRatio }}%</div>
        </div>
        <div class="p-3 bg-green-900/30 rounded-lg">
          <div class="text-xs text-gray-500">Komprimiert</div>
          <div class="text-lg font-mono text-green-400">{{ formatSize(compressedSize) }}</div>
        </div>
      </div>

      <!-- Image Preview -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <div class="text-xs text-gray-500 mb-1">Original</div>
          <img :src="originalImage" class="w-full rounded-lg border border-dark-600" />
        </div>
        <div>
          <div class="text-xs text-gray-500 mb-1">Komprimiert</div>
          <img :src="compressedImage" class="w-full rounded-lg border border-dark-600" />
        </div>
      </div>

      <!-- Actions -->
      <div class="flex gap-2">
        <button
          @click="downloadCompressed"
          class="btn-primary flex-1"
          :disabled="isProcessing"
        >
          {{ isProcessing ? 'Verarbeite...' : 'Herunterladen' }}
        </button>
        <button @click="reset" class="btn-secondary">
          Zurücksetzen
        </button>
      </div>
    </div>

    <!-- Processing indicator -->
    <div v-if="isProcessing" class="text-center text-gray-400">
      Komprimiere...
    </div>
  </div>
</template>
