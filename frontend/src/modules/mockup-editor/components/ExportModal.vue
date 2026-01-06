<script setup>
import { ref, computed, watch } from 'vue'
import { useMockupStore } from '../stores/mockupStore'
import {
  XMarkIcon,
  ArrowDownTrayIcon,
  PhotoIcon,
  CheckIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close', 'export'])

const mockupStore = useMockupStore()

// Export options
const format = ref('png')
const quality = ref(100)
const transparent = ref(false)

// Reset options when template changes
watch(() => mockupStore.currentTemplate, (template) => {
  if (template?.transparentBg) {
    transparent.value = true
  }
})

// Computed
const formatOptions = [
  { value: 'png', label: 'PNG', description: 'Beste Qualität, unterstützt Transparenz' },
  { value: 'jpg', label: 'JPG', description: 'Kleinere Dateigröße, keine Transparenz' },
]

const canBeTransparent = computed(() => {
  return format.value === 'png'
})

const estimatedFileName = computed(() => {
  const templateName = mockupStore.currentTemplate?.id || 'mockup'
  return `${templateName}-export.${format.value}`
})

// Methods
const handleExport = () => {
  emit('export', {
    format: format.value,
    quality: quality.value / 100,
    transparent: transparent.value && canBeTransparent.value,
  })
}

const close = () => {
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/70" @click="close" />

        <!-- Modal -->
        <div class="relative bg-gray-800 rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden border border-gray-700">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-amber-500/20 rounded-lg flex items-center justify-center">
                <ArrowDownTrayIcon class="w-5 h-5 text-amber-400" />
              </div>
              <div>
                <h2 class="text-lg font-semibold text-white">Mockup exportieren</h2>
                <p class="text-sm text-gray-400">{{ mockupStore.currentTemplate?.name }}</p>
              </div>
            </div>
            <button @click="close" class="p-2 text-gray-400 hover:text-white transition-colors rounded-lg hover:bg-gray-700">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Content -->
          <div class="p-6 space-y-6">
            <!-- Format Selection -->
            <div class="space-y-3">
              <label class="block text-sm font-medium text-gray-300">Format</label>
              <div class="grid grid-cols-2 gap-3">
                <button
                  v-for="opt in formatOptions"
                  :key="opt.value"
                  @click="format = opt.value"
                  class="relative p-4 rounded-lg border-2 transition-all text-left"
                  :class="format === opt.value
                    ? 'border-amber-500 bg-amber-500/10'
                    : 'border-gray-600 hover:border-gray-500 bg-gray-700/50'"
                >
                  <div class="flex items-center justify-between">
                    <span class="font-semibold text-white">{{ opt.label }}</span>
                    <CheckIcon
                      v-if="format === opt.value"
                      class="w-5 h-5 text-amber-400"
                    />
                  </div>
                  <p class="mt-1 text-xs text-gray-400">{{ opt.description }}</p>
                </button>
              </div>
            </div>

            <!-- Quality (for JPG) -->
            <Transition name="slide-down">
              <div v-if="format === 'jpg'" class="space-y-3">
                <label class="block text-sm font-medium text-gray-300">
                  Qualität: {{ quality }}%
                </label>
                <input
                  v-model="quality"
                  type="range"
                  min="10"
                  max="100"
                  step="5"
                  class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-amber-500"
                />
                <div class="flex justify-between text-xs text-gray-500">
                  <span>Kleinere Datei</span>
                  <span>Beste Qualität</span>
                </div>
              </div>
            </Transition>

            <!-- Transparency Option -->
            <div v-if="canBeTransparent" class="space-y-3">
              <label class="flex items-center gap-3 cursor-pointer group">
                <div class="relative">
                  <input
                    v-model="transparent"
                    type="checkbox"
                    class="sr-only peer"
                  />
                  <div class="w-11 h-6 bg-gray-700 rounded-full peer-checked:bg-amber-500 transition-colors" />
                  <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-transform peer-checked:translate-x-5" />
                </div>
                <div>
                  <span class="text-sm font-medium text-gray-300 group-hover:text-white transition-colors">
                    Transparenter Hintergrund
                  </span>
                  <p class="text-xs text-gray-500">Entfernt den Hintergrund für PNG-Export</p>
                </div>
              </label>
            </div>

            <!-- Preview Info -->
            <div class="bg-gray-900/50 rounded-lg p-4">
              <div class="flex items-center gap-3">
                <PhotoIcon class="w-8 h-8 text-gray-500" />
                <div class="flex-1">
                  <p class="text-sm font-medium text-gray-300">{{ estimatedFileName }}</p>
                  <p class="text-xs text-gray-500">
                    {{ mockupStore.canvasWidth }} x {{ mockupStore.canvasHeight }} Pixel
                    <span v-if="transparent && canBeTransparent" class="text-purple-400 ml-2">+ Transparenz</span>
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-700 bg-gray-900/30">
            <button
              @click="close"
              class="px-4 py-2 text-gray-300 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="handleExport"
              class="flex items-center gap-2 px-6 py-2 bg-amber-500 hover:bg-amber-600 text-gray-900 font-medium rounded-lg transition-colors"
            >
              <ArrowDownTrayIcon class="w-4 h-4" />
              Exportieren
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.2s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
