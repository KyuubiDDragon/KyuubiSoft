<script setup>
import { ref, computed, watch } from 'vue'

const colorHex = ref('#3B82F6')
const colorRgb = ref({ r: 59, g: 130, b: 246 })
const colorHsl = ref({ h: 217, s: 91, l: 60 })
const activeInput = ref('hex')

// Preset colors
const presets = [
  '#EF4444', '#F97316', '#F59E0B', '#EAB308', '#84CC16',
  '#22C55E', '#10B981', '#14B8A6', '#06B6D4', '#0EA5E9',
  '#3B82F6', '#6366F1', '#8B5CF6', '#A855F7', '#D946EF',
  '#EC4899', '#F43F5E', '#64748B', '#1F2937', '#FFFFFF',
]

// Conversion functions
function hexToRgb(hex) {
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
  if (result) {
    return {
      r: parseInt(result[1], 16),
      g: parseInt(result[2], 16),
      b: parseInt(result[3], 16),
    }
  }
  return null
}

function rgbToHex(r, g, b) {
  return '#' + [r, g, b].map(x => {
    const hex = Math.max(0, Math.min(255, Math.round(x))).toString(16)
    return hex.length === 1 ? '0' + hex : hex
  }).join('').toUpperCase()
}

function rgbToHsl(r, g, b) {
  r /= 255
  g /= 255
  b /= 255

  const max = Math.max(r, g, b)
  const min = Math.min(r, g, b)
  let h, s
  const l = (max + min) / 2

  if (max === min) {
    h = s = 0
  } else {
    const d = max - min
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min)

    switch (max) {
      case r:
        h = ((g - b) / d + (g < b ? 6 : 0)) / 6
        break
      case g:
        h = ((b - r) / d + 2) / 6
        break
      case b:
        h = ((r - g) / d + 4) / 6
        break
    }
  }

  return {
    h: Math.round(h * 360),
    s: Math.round(s * 100),
    l: Math.round(l * 100),
  }
}

function hslToRgb(h, s, l) {
  h /= 360
  s /= 100
  l /= 100

  let r, g, b

  if (s === 0) {
    r = g = b = l
  } else {
    const hue2rgb = (p, q, t) => {
      if (t < 0) t += 1
      if (t > 1) t -= 1
      if (t < 1/6) return p + (q - p) * 6 * t
      if (t < 1/2) return q
      if (t < 2/3) return p + (q - p) * (2/3 - t) * 6
      return p
    }

    const q = l < 0.5 ? l * (1 + s) : l + s - l * s
    const p = 2 * l - q

    r = hue2rgb(p, q, h + 1/3)
    g = hue2rgb(p, q, h)
    b = hue2rgb(p, q, h - 1/3)
  }

  return {
    r: Math.round(r * 255),
    g: Math.round(g * 255),
    b: Math.round(b * 255),
  }
}

// Watchers for synchronization
watch(colorHex, (newVal) => {
  if (activeInput.value !== 'hex') return
  const rgb = hexToRgb(newVal)
  if (rgb) {
    colorRgb.value = rgb
    colorHsl.value = rgbToHsl(rgb.r, rgb.g, rgb.b)
  }
}, { immediate: true })

watch(colorRgb, (newVal) => {
  if (activeInput.value !== 'rgb') return
  colorHex.value = rgbToHex(newVal.r, newVal.g, newVal.b)
  colorHsl.value = rgbToHsl(newVal.r, newVal.g, newVal.b)
}, { deep: true })

watch(colorHsl, (newVal) => {
  if (activeInput.value !== 'hsl') return
  const rgb = hslToRgb(newVal.h, newVal.s, newVal.l)
  colorRgb.value = rgb
  colorHex.value = rgbToHex(rgb.r, rgb.g, rgb.b)
}, { deep: true })

// Computed values
const cssRgb = computed(() => `rgb(${colorRgb.value.r}, ${colorRgb.value.g}, ${colorRgb.value.b})`)
const cssRgba = computed(() => `rgba(${colorRgb.value.r}, ${colorRgb.value.g}, ${colorRgb.value.b}, 1)`)
const cssHsl = computed(() => `hsl(${colorHsl.value.h}, ${colorHsl.value.s}%, ${colorHsl.value.l}%)`)

const contrastColor = computed(() => {
  const luminance = (0.299 * colorRgb.value.r + 0.587 * colorRgb.value.g + 0.114 * colorRgb.value.b) / 255
  return luminance > 0.5 ? '#000000' : '#FFFFFF'
})

// Methods
function setFromHex(hex) {
  activeInput.value = 'hex'
  colorHex.value = hex
}

function updateRgb(component, value) {
  activeInput.value = 'rgb'
  colorRgb.value = { ...colorRgb.value, [component]: parseInt(value) || 0 }
}

function updateHsl(component, value) {
  activeInput.value = 'hsl'
  colorHsl.value = { ...colorHsl.value, [component]: parseInt(value) || 0 }
}

function handleHexInput(e) {
  activeInput.value = 'hex'
  let val = e.target.value
  if (!val.startsWith('#')) {
    val = '#' + val
  }
  colorHex.value = val.toUpperCase()
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text)
}
</script>

<template>
  <div class="space-y-4">
    <!-- Color Preview -->
    <div class="flex gap-4">
      <div
        class="w-32 h-32 rounded-xl border-2 border-dark-600 flex items-center justify-center text-sm font-mono"
        :style="{ backgroundColor: colorHex, color: contrastColor }"
      >
        {{ colorHex }}
      </div>

      <div class="flex-1 space-y-2">
        <!-- Native Color Picker -->
        <div>
          <label class="text-sm text-gray-400 mb-1 block">Farbauswahl</label>
          <input
            type="color"
            :value="colorHex"
            @input="setFromHex($event.target.value)"
            class="w-full h-10 cursor-pointer rounded bg-transparent"
          />
        </div>

        <!-- HEX Input -->
        <div>
          <label class="text-sm text-gray-400 mb-1 block">HEX</label>
          <div class="flex gap-2">
            <input
              :value="colorHex"
              @input="handleHexInput"
              @focus="activeInput = 'hex'"
              class="input flex-1 font-mono"
              placeholder="#FFFFFF"
            />
            <button
              @click="copyToClipboard(colorHex)"
              class="btn-secondary px-3"
              title="Kopieren"
            >
              📋
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- RGB Inputs -->
    <div>
      <label class="text-sm text-gray-400 mb-2 block">RGB</label>
      <div class="grid grid-cols-3 gap-2">
        <div>
          <label class="text-xs text-gray-500">R</label>
          <input
            type="number"
            min="0"
            max="255"
            :value="colorRgb.r"
            @input="updateRgb('r', $event.target.value)"
            @focus="activeInput = 'rgb'"
            class="input w-full font-mono"
          />
        </div>
        <div>
          <label class="text-xs text-gray-500">G</label>
          <input
            type="number"
            min="0"
            max="255"
            :value="colorRgb.g"
            @input="updateRgb('g', $event.target.value)"
            @focus="activeInput = 'rgb'"
            class="input w-full font-mono"
          />
        </div>
        <div>
          <label class="text-xs text-gray-500">B</label>
          <input
            type="number"
            min="0"
            max="255"
            :value="colorRgb.b"
            @input="updateRgb('b', $event.target.value)"
            @focus="activeInput = 'rgb'"
            class="input w-full font-mono"
          />
        </div>
      </div>
      <button
        @click="copyToClipboard(cssRgb)"
        class="mt-2 text-xs text-primary-400 hover:text-primary-300"
      >
        {{ cssRgb }} kopieren
      </button>
    </div>

    <!-- HSL Inputs -->
    <div>
      <label class="text-sm text-gray-400 mb-2 block">HSL</label>
      <div class="grid grid-cols-3 gap-2">
        <div>
          <label class="text-xs text-gray-500">H (0-360)</label>
          <input
            type="number"
            min="0"
            max="360"
            :value="colorHsl.h"
            @input="updateHsl('h', $event.target.value)"
            @focus="activeInput = 'hsl'"
            class="input w-full font-mono"
          />
        </div>
        <div>
          <label class="text-xs text-gray-500">S (0-100)</label>
          <input
            type="number"
            min="0"
            max="100"
            :value="colorHsl.s"
            @input="updateHsl('s', $event.target.value)"
            @focus="activeInput = 'hsl'"
            class="input w-full font-mono"
          />
        </div>
        <div>
          <label class="text-xs text-gray-500">L (0-100)</label>
          <input
            type="number"
            min="0"
            max="100"
            :value="colorHsl.l"
            @input="updateHsl('l', $event.target.value)"
            @focus="activeInput = 'hsl'"
            class="input w-full font-mono"
          />
        </div>
      </div>
      <button
        @click="copyToClipboard(cssHsl)"
        class="mt-2 text-xs text-primary-400 hover:text-primary-300"
      >
        {{ cssHsl }} kopieren
      </button>
    </div>

    <!-- Preset Colors -->
    <div>
      <label class="text-sm text-gray-400 mb-2 block">Vorlagen</label>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="color in presets"
          :key="color"
          @click="setFromHex(color)"
          class="w-8 h-8 rounded-lg border-2 border-dark-600 hover:border-primary-500 transition-colors"
          :style="{ backgroundColor: color }"
          :class="colorHex.toUpperCase() === color ? 'ring-2 ring-primary-500' : ''"
          :title="color"
        ></button>
      </div>
    </div>
  </div>
</template>
