<script setup>
import { ref, computed } from 'vue'
import {
  LinkIcon,
  XMarkIcon,
  CheckIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'submit'])

const url = ref('')
const error = ref('')

// Supported providers for display
const supportedProviders = [
  { name: 'YouTube', icon: '▶️' },
  { name: 'Vimeo', icon: '🎬' },
  { name: 'Twitter/X', icon: '🐦' },
  { name: 'Spotify', icon: '🎵' },
  { name: 'Figma', icon: '🎨' },
  { name: 'CodePen', icon: '💻' },
  { name: 'Loom', icon: '📹' },
  { name: 'Google Maps', icon: '🗺️' },
  { name: 'Miro', icon: '📋' },
]

// Validate URL
const isValidUrl = computed(() => {
  if (!url.value) return false
  try {
    new URL(url.value)
    return true
  } catch {
    return false
  }
})

// Submit
function submit() {
  if (!url.value.trim()) {
    error.value = 'Bitte gib eine URL ein'
    return
  }

  if (!isValidUrl.value) {
    error.value = 'Ungültige URL'
    return
  }

  emit('submit', url.value)
  url.value = ''
  error.value = ''
}

// Close
function close() {
  url.value = ''
  error.value = ''
  emit('close')
}

// Handle paste
function handlePaste(event) {
  const pastedText = event.clipboardData?.getData('text')
  if (pastedText) {
    // Auto-submit on valid URL paste
    try {
      new URL(pastedText)
      url.value = pastedText
      setTimeout(() => submit(), 100)
    } catch {
      // Not a URL, let default paste happen
    }
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
      @click.self="close"
    >
      <div class="bg-dark-800 rounded-xl shadow-xl w-full max-w-lg overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
          <h3 class="text-lg font-semibold text-white flex items-center gap-2">
            <LinkIcon class="w-5 h-5 text-primary-400" />
            Embed einfügen
          </h3>
          <button
            @click="close"
            class="p-1 text-gray-400 hover:text-white rounded"
          >
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- Content -->
        <div class="p-6">
          <!-- URL Input -->
          <div class="mb-4">
            <label class="block text-sm text-gray-400 mb-2">URL einfügen</label>
            <div class="relative">
              <input
                v-model="url"
                type="url"
                placeholder="https://youtube.com/watch?v=..."
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                @keydown.enter="submit"
                @paste="handlePaste"
                autofocus
              />
              <button
                v-if="isValidUrl"
                @click="submit"
                class="absolute right-2 top-1/2 -translate-y-1/2 p-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg"
              >
                <CheckIcon class="w-4 h-4" />
              </button>
            </div>
            <p v-if="error" class="mt-2 text-sm text-red-400">{{ error }}</p>
          </div>

          <!-- Supported providers -->
          <div>
            <p class="text-sm text-gray-500 mb-3">Unterstützte Plattformen:</p>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="provider in supportedProviders"
                :key="provider.name"
                class="inline-flex items-center gap-1 px-2 py-1 bg-dark-700 rounded text-xs text-gray-400"
              >
                {{ provider.icon }} {{ provider.name }}
              </span>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-3 px-6 py-4 bg-dark-850 border-t border-dark-700">
          <button
            @click="close"
            class="px-4 py-2 text-gray-400 hover:text-white"
          >
            Abbrechen
          </button>
          <button
            @click="submit"
            :disabled="!isValidUrl"
            :class="[
              'px-4 py-2 rounded-lg font-medium',
              isValidUrl
                ? 'bg-primary-600 hover:bg-primary-700 text-white'
                : 'bg-dark-600 text-gray-500 cursor-not-allowed'
            ]"
          >
            Einfügen
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.bg-dark-850 {
  background-color: rgb(24, 24, 27);
}
</style>
