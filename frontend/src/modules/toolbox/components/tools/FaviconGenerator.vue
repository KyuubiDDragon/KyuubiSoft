<script setup>
import { ref, computed, watch } from 'vue'

const text = ref('K')
const bgColor = ref('#6366f1')
const textColor = ref('#ffffff')
const fontSize = ref(70)
const fontFamily = ref('Inter, system-ui, sans-serif')
const borderRadius = ref(20)
const generatedIcons = ref([])
const uploadedImage = ref(null)
const mode = ref('text') // 'text' or 'image'

const sizes = [16, 32, 48, 64, 128, 192, 512]

const fontFamilies = [
  { value: 'Inter, system-ui, sans-serif', name: 'Inter (Modern)' },
  { value: 'Georgia, serif', name: 'Georgia (Serif)' },
  { value: 'monospace', name: 'Monospace' },
  { value: 'Arial, sans-serif', name: 'Arial' },
  { value: 'Impact, sans-serif', name: 'Impact' },
]

function generateIcons() {
  generatedIcons.value = []

  for (const size of sizes) {
    const canvas = document.createElement('canvas')
    canvas.width = size
    canvas.height = size
    const ctx = canvas.getContext('2d')

    // Background
    const radius = (borderRadius.value / 100) * (size / 2)
    roundedRect(ctx, 0, 0, size, size, radius, bgColor.value)

    if (mode.value === 'text') {
      // Text
      const scaledFontSize = (fontSize.value / 100) * size
      ctx.font = `bold ${scaledFontSize}px ${fontFamily.value}`
      ctx.fillStyle = textColor.value
      ctx.textAlign = 'center'
      ctx.textBaseline = 'middle'
      ctx.fillText(text.value, size / 2, size / 2 + scaledFontSize * 0.05)
    } else if (uploadedImage.value) {
      // Image
      const img = new Image()
      img.onload = () => {
        // Clear and redraw
        ctx.clearRect(0, 0, size, size)
        roundedRect(ctx, 0, 0, size, size, radius, bgColor.value)

        // Calculate aspect ratio
        const imgRatio = img.width / img.height
        let drawWidth, drawHeight, drawX, drawY

        const padding = size * 0.1
        const maxSize = size - padding * 2

        if (imgRatio > 1) {
          drawWidth = maxSize
          drawHeight = maxSize / imgRatio
        } else {
          drawHeight = maxSize
          drawWidth = maxSize * imgRatio
        }

        drawX = (size - drawWidth) / 2
        drawY = (size - drawHeight) / 2

        ctx.drawImage(img, drawX, drawY, drawWidth, drawHeight)

        // Update the icon in the array
        const index = generatedIcons.value.findIndex(i => i.size === size)
        if (index !== -1) {
          generatedIcons.value[index].dataUrl = canvas.toDataURL('image/png')
        }
      }
      img.src = uploadedImage.value
    }

    generatedIcons.value.push({
      size,
      dataUrl: canvas.toDataURL('image/png'),
      filename: `favicon-${size}x${size}.png`,
    })
  }
}

function roundedRect(ctx, x, y, width, height, radius, color) {
  ctx.fillStyle = color
  ctx.beginPath()
  ctx.moveTo(x + radius, y)
  ctx.lineTo(x + width - radius, y)
  ctx.quadraticCurveTo(x + width, y, x + width, y + radius)
  ctx.lineTo(x + width, y + height - radius)
  ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height)
  ctx.lineTo(x + radius, y + height)
  ctx.quadraticCurveTo(x, y + height, x, y + height - radius)
  ctx.lineTo(x, y + radius)
  ctx.quadraticCurveTo(x, y, x + radius, y)
  ctx.closePath()
  ctx.fill()
}

function handleImageUpload(event) {
  const file = event.target.files[0]
  if (!file || !file.type.startsWith('image/')) return

  const reader = new FileReader()
  reader.onload = (e) => {
    uploadedImage.value = e.target.result
    mode.value = 'image'
    generateIcons()
  }
  reader.readAsDataURL(file)
}

function downloadIcon(icon) {
  const link = document.createElement('a')
  link.download = icon.filename
  link.href = icon.dataUrl
  link.click()
}

function downloadAll() {
  for (const icon of generatedIcons.value) {
    setTimeout(() => downloadIcon(icon), 100)
  }
}

function generateIco() {
  // For ICO format, we just download the 32x32 version
  // True ICO generation would need a server-side process
  const icon32 = generatedIcons.value.find(i => i.size === 32)
  if (icon32) {
    const link = document.createElement('a')
    link.download = 'favicon.ico'
    link.href = icon32.dataUrl
    link.click()
  }
}

// Generate initial icons
watch([text, bgColor, textColor, fontSize, fontFamily, borderRadius, mode], () => {
  generateIcons()
}, { immediate: true })
</script>

<template>
  <div class="space-y-4">
    <!-- Mode Toggle -->
    <div class="flex gap-2">
      <button
        @click="mode = 'text'; generateIcons()"
        class="px-4 py-2 text-sm rounded-lg transition-colors"
        :class="mode === 'text' ? 'bg-primary-600 text-white' : 'bg-dark-700 text-gray-400'"
      >
        Text
      </button>
      <button
        @click="mode = 'image'"
        class="px-4 py-2 text-sm rounded-lg transition-colors"
        :class="mode === 'image' ? 'bg-primary-600 text-white' : 'bg-dark-700 text-gray-400'"
      >
        Bild
      </button>
    </div>

    <!-- Text Mode Settings -->
    <div v-if="mode === 'text'" class="grid grid-cols-2 gap-4">
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Text (1-2 Zeichen)</label>
        <input
          v-model="text"
          type="text"
          maxlength="2"
          class="input w-full text-center text-2xl"
        />
      </div>
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Schriftart</label>
        <select v-model="fontFamily" class="input w-full">
          <option v-for="font in fontFamilies" :key="font.value" :value="font.value">
            {{ font.name }}
          </option>
        </select>
      </div>
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Textgröße: {{ fontSize }}%</label>
        <input
          v-model.number="fontSize"
          type="range"
          min="30"
          max="100"
          class="w-full"
        />
      </div>
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Textfarbe</label>
        <input v-model="textColor" type="color" class="w-full h-10 cursor-pointer rounded" />
      </div>
    </div>

    <!-- Image Mode Settings -->
    <div v-else class="space-y-3">
      <div
        class="border-2 border-dashed border-dark-600 rounded-lg p-6 text-center hover:border-primary-500 transition-colors cursor-pointer"
        @click="$refs.imageInput.click()"
      >
        <input
          ref="imageInput"
          type="file"
          accept="image/*"
          class="hidden"
          @change="handleImageUpload"
        />
        <div v-if="!uploadedImage" class="text-gray-400">
          <div class="text-3xl mb-2">🖼️</div>
          <p>Bild hochladen</p>
        </div>
        <div v-else class="text-green-400">
          <p>Bild geladen - Klicken zum Ändern</p>
        </div>
      </div>
    </div>

    <!-- Common Settings -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Hintergrundfarbe</label>
        <input v-model="bgColor" type="color" class="w-full h-10 cursor-pointer rounded" />
      </div>
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Eckenradius: {{ borderRadius }}%</label>
        <input
          v-model.number="borderRadius"
          type="range"
          min="0"
          max="50"
          class="w-full"
        />
      </div>
    </div>

    <!-- Preview -->
    <div class="flex items-center justify-center gap-4 p-4 bg-dark-800 rounded-lg">
      <div
        v-for="icon in generatedIcons.filter(i => [32, 64, 128].includes(i.size))"
        :key="icon.size"
        class="text-center"
      >
        <img
          :src="icon.dataUrl"
          :width="icon.size"
          :height="icon.size"
          class="mx-auto"
          :style="{ imageRendering: icon.size <= 32 ? 'pixelated' : 'auto' }"
        />
        <div class="text-xs text-gray-500 mt-1">{{ icon.size }}px</div>
      </div>
    </div>

    <!-- Download Buttons -->
    <div class="flex gap-2">
      <button @click="downloadAll" class="btn-primary flex-1">
        Alle PNG herunterladen
      </button>
      <button @click="generateIco" class="btn-secondary">
        Als ICO
      </button>
    </div>

    <!-- All Sizes -->
    <div>
      <h4 class="text-sm text-gray-400 mb-2">Alle Größen</h4>
      <div class="grid grid-cols-4 gap-2">
        <button
          v-for="icon in generatedIcons"
          :key="icon.size"
          @click="downloadIcon(icon)"
          class="p-2 bg-dark-700 hover:bg-dark-600 rounded-lg text-center transition-colors"
        >
          <img :src="icon.dataUrl" :width="Math.min(icon.size, 48)" class="mx-auto" />
          <div class="text-xs text-gray-400 mt-1">{{ icon.size }}x{{ icon.size }}</div>
        </button>
      </div>
    </div>

    <!-- Usage Info -->
    <details class="text-sm">
      <summary class="text-gray-400 cursor-pointer hover:text-white">Verwendung</summary>
      <div class="mt-2 p-3 bg-dark-700 rounded-lg text-xs text-gray-400 font-mono space-y-2">
        <p>&lt;!-- HTML Head --&gt;</p>
        <p>&lt;link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png"&gt;</p>
        <p>&lt;link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png"&gt;</p>
        <p>&lt;link rel="apple-touch-icon" sizes="192x192" href="/favicon-192x192.png"&gt;</p>
      </div>
    </details>
  </div>
</template>
