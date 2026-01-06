<script setup>
import { ref, computed, watch } from 'vue'
import { useMockupStore } from '../stores/mockupStore'
import {
  PhotoIcon,
  DocumentTextIcon,
  MinusIcon,
  Square2StackIcon,
  ArrowsPointingOutIcon,
  PaintBrushIcon,
  XMarkIcon,
  PlusIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'

const mockupStore = useMockupStore()

// Local state for editing
const localText = ref('')
const localColor = ref('')
const localFontSize = ref(16)
const localHighlightText = ref('')
const localHighlightColor = ref('#f4b400')
const localHighlightWords = ref([])
const newHighlightText = ref('')
const newHighlightColor = ref('#f4b400')

// Watch selected element
watch(() => mockupStore.selectedElement, (element) => {
  if (element) {
    localText.value = element.text || ''
    localColor.value = element.color || '#ffffff'
    localFontSize.value = element.fontSize || 16
    localHighlightText.value = element.highlightText || ''
    localHighlightColor.value = element.highlightColor || '#f4b400'
    // Support multiple highlight words
    localHighlightWords.value = element.highlightWords ? [...element.highlightWords] : []
  }
}, { immediate: true })

// Update handlers
const updateText = () => {
  if (!mockupStore.selectedElementId) return
  mockupStore.updateElement(mockupStore.selectedElementId, { text: localText.value })
}

const updateColor = () => {
  if (!mockupStore.selectedElementId) return
  mockupStore.updateElement(mockupStore.selectedElementId, { color: localColor.value })
}

const updateFontSize = () => {
  if (!mockupStore.selectedElementId) return
  mockupStore.updateElement(mockupStore.selectedElementId, { fontSize: localFontSize.value })
}

const updateHighlight = () => {
  if (!mockupStore.selectedElementId) return
  mockupStore.updateElement(mockupStore.selectedElementId, {
    highlightText: localHighlightText.value,
    highlightColor: localHighlightColor.value,
  })
}

const addHighlightWord = () => {
  if (!mockupStore.selectedElementId || !newHighlightText.value.trim()) return

  const newWord = {
    text: newHighlightText.value.trim(),
    color: newHighlightColor.value
  }

  localHighlightWords.value.push(newWord)
  mockupStore.updateElement(mockupStore.selectedElementId, {
    highlightWords: [...localHighlightWords.value]
  })

  // Reset input
  newHighlightText.value = ''
  newHighlightColor.value = '#f4b400'
}

const removeHighlightWord = (index) => {
  if (!mockupStore.selectedElementId) return

  localHighlightWords.value.splice(index, 1)
  mockupStore.updateElement(mockupStore.selectedElementId, {
    highlightWords: [...localHighlightWords.value]
  })
}

const updateHighlightWordColor = (index, color) => {
  if (!mockupStore.selectedElementId) return

  localHighlightWords.value[index].color = color
  mockupStore.updateElement(mockupStore.selectedElementId, {
    highlightWords: [...localHighlightWords.value]
  })
}

const clearSelection = () => {
  mockupStore.selectElement(null)
}

const clearImage = () => {
  if (!mockupStore.selectedElementId) return
  mockupStore.updateElement(mockupStore.selectedElementId, { src: '' })
}

// Font options
const fontFamilies = [
  { value: 'Outfit', label: 'Outfit' },
  { value: 'DM Sans', label: 'DM Sans' },
  { value: 'Inter', label: 'Inter' },
  { value: 'Roboto', label: 'Roboto' },
  { value: 'Poppins', label: 'Poppins' },
]

const fontWeights = [
  { value: 400, label: 'Normal' },
  { value: 500, label: 'Medium' },
  { value: 600, label: 'Semibold' },
  { value: 700, label: 'Bold' },
  { value: 800, label: 'Extra Bold' },
]

const updateFontFamily = (value) => {
  if (!mockupStore.selectedElementId) return
  mockupStore.updateElement(mockupStore.selectedElementId, { fontFamily: value })
}

const updateFontWeight = (value) => {
  if (!mockupStore.selectedElementId) return
  mockupStore.updateElement(mockupStore.selectedElementId, { fontWeight: parseInt(value) })
}

// Element type icons
const typeIcons = {
  text: DocumentTextIcon,
  image: PhotoIcon,
  line: MinusIcon,
  container: Square2StackIcon,
  corner: ArrowsPointingOutIcon,
  button: PaintBrushIcon,
  background: PaintBrushIcon,
}
</script>

<template>
  <div class="p-4">
    <!-- No selection -->
    <div v-if="!mockupStore.selectedElement" class="text-center py-8">
      <DocumentTextIcon class="w-12 h-12 mx-auto text-gray-600" />
      <p class="mt-4 text-gray-500 text-sm">Klicke auf ein Element, um es zu bearbeiten</p>
    </div>

    <!-- Element Properties -->
    <div v-else class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <component
            :is="typeIcons[mockupStore.selectedElement.type] || DocumentTextIcon"
            class="w-5 h-5 text-amber-400"
          />
          <span class="font-medium text-white capitalize">{{ mockupStore.selectedElement.type }}</span>
        </div>
        <button @click="clearSelection" class="p-1 text-gray-400 hover:text-white transition-colors">
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>

      <div class="text-xs text-gray-500 -mt-4">
        ID: {{ mockupStore.selectedElement.id }}
      </div>

      <!-- Text Properties -->
      <template v-if="mockupStore.selectedElement.type === 'text'">
        <!-- Text Content -->
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-300">Text</label>
          <textarea
            v-model="localText"
            @input="updateText"
            rows="3"
            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 resize-none"
            placeholder="Text eingeben..."
          />
        </div>

        <!-- Font Family -->
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-300">Schriftart</label>
          <select
            :value="mockupStore.selectedElement.fontFamily"
            @change="(e) => updateFontFamily(e.target.value)"
            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
          >
            <option v-for="font in fontFamilies" :key="font.value" :value="font.value">
              {{ font.label }}
            </option>
          </select>
        </div>

        <!-- Font Size & Weight -->
        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-300">Größe</label>
            <div class="flex items-center gap-2">
              <input
                v-model.number="localFontSize"
                @input="updateFontSize"
                type="number"
                min="8"
                max="200"
                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
              />
              <span class="text-gray-500 text-sm">px</span>
            </div>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-300">Gewicht</label>
            <select
              :value="mockupStore.selectedElement.fontWeight"
              @change="(e) => updateFontWeight(e.target.value)"
              class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
            >
              <option v-for="weight in fontWeights" :key="weight.value" :value="weight.value">
                {{ weight.label }}
              </option>
            </select>
          </div>
        </div>

        <!-- Text Color -->
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-300">Textfarbe</label>
          <div class="flex items-center gap-3">
            <input
              v-model="localColor"
              @input="updateColor"
              type="color"
              class="w-10 h-10 rounded cursor-pointer border-0 bg-transparent"
            />
            <input
              v-model="localColor"
              @input="updateColor"
              type="text"
              class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
            />
          </div>
        </div>

        <!-- Multiple Highlight Words -->
        <div class="space-y-3">
          <label class="block text-sm font-medium text-gray-300">Hervorhebungen</label>

          <!-- Existing highlight words -->
          <div v-if="localHighlightWords.length > 0" class="space-y-2">
            <div
              v-for="(word, index) in localHighlightWords"
              :key="index"
              class="flex items-center gap-2 p-2 bg-gray-700/50 rounded-lg"
            >
              <input
                :value="word.color"
                @input="(e) => updateHighlightWordColor(index, e.target.value)"
                type="color"
                class="w-8 h-8 rounded cursor-pointer border-0 bg-transparent flex-shrink-0"
              />
              <span class="flex-1 text-sm text-white truncate" :style="{ color: word.color }">
                {{ word.text }}
              </span>
              <button
                @click="removeHighlightWord(index)"
                class="p-1 text-gray-400 hover:text-red-400 transition-colors flex-shrink-0"
              >
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Add new highlight word -->
          <div class="flex items-center gap-2">
            <input
              v-model="newHighlightColor"
              type="color"
              class="w-8 h-8 rounded cursor-pointer border-0 bg-transparent flex-shrink-0"
            />
            <input
              v-model="newHighlightText"
              type="text"
              placeholder="Wort hinzufugen..."
              class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-3 py-1.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
              @keypress.enter="addHighlightWord"
            />
            <button
              @click="addHighlightWord"
              :disabled="!newHighlightText.trim()"
              class="p-1.5 bg-amber-500 text-black rounded-lg hover:bg-amber-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex-shrink-0"
            >
              <PlusIcon class="w-4 h-4" />
            </button>
          </div>

          <p class="text-xs text-gray-500">
            Worte werden farbig hervorgehoben
          </p>
        </div>
      </template>

      <!-- Image Properties -->
      <template v-else-if="mockupStore.selectedElement.type === 'image'">
        <div class="space-y-4">
          <!-- Preview -->
          <div v-if="mockupStore.selectedElement.src" class="space-y-2">
            <label class="block text-sm font-medium text-gray-300">Vorschau</label>
            <div class="aspect-video bg-gray-700 rounded-lg overflow-hidden">
              <img
                :src="mockupStore.selectedElement.src"
                class="w-full h-full object-cover"
              />
            </div>
            <button
              @click="clearImage"
              class="w-full px-3 py-2 bg-red-500/20 text-red-400 hover:bg-red-500/30 rounded-lg text-sm transition-colors"
            >
              Bild entfernen
            </button>
          </div>

          <!-- Placeholder info -->
          <div v-else class="p-4 bg-gray-700/50 rounded-lg text-center">
            <PhotoIcon class="w-8 h-8 mx-auto text-gray-500" />
            <p class="mt-2 text-sm text-gray-400">Kein Bild geladen</p>
            <p class="text-xs text-gray-500 mt-1">Ziehe ein Bild auf das Element im Canvas</p>
          </div>

          <!-- Position & Size Info -->
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="bg-gray-700/50 rounded-lg p-3">
              <span class="text-gray-500">Position</span>
              <p class="text-white">{{ mockupStore.selectedElement.x }}, {{ mockupStore.selectedElement.y }}</p>
            </div>
            <div class="bg-gray-700/50 rounded-lg p-3">
              <span class="text-gray-500">Größe</span>
              <p class="text-white">{{ mockupStore.selectedElement.width }} x {{ mockupStore.selectedElement.height }}</p>
            </div>
          </div>
        </div>
      </template>

      <!-- Line Properties -->
      <template v-else-if="mockupStore.selectedElement.type === 'line'">
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="bg-gray-700/50 rounded-lg p-3">
              <span class="text-gray-500">Position</span>
              <p class="text-white">{{ mockupStore.selectedElement.x }}, {{ mockupStore.selectedElement.y }}</p>
            </div>
            <div class="bg-gray-700/50 rounded-lg p-3">
              <span class="text-gray-500">Größe</span>
              <p class="text-white">{{ mockupStore.selectedElement.width }} x {{ mockupStore.selectedElement.height }}</p>
            </div>
          </div>

          <div v-if="mockupStore.selectedElement.gradient" class="space-y-2">
            <label class="block text-sm font-medium text-gray-300">Gradient</label>
            <div
              class="h-4 rounded"
              :style="{ background: mockupStore.selectedElement.gradient }"
            />
          </div>
        </div>
      </template>

      <!-- Corner Properties -->
      <template v-else-if="mockupStore.selectedElement.type === 'corner'">
        <div class="space-y-4">
          <div class="bg-gray-700/50 rounded-lg p-3 text-sm">
            <span class="text-gray-500">Position</span>
            <p class="text-white capitalize">{{ mockupStore.selectedElement.position?.replace('-', ' ') }}</p>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-300">Farbe</label>
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 rounded border border-gray-600"
                :style="{ backgroundColor: mockupStore.selectedElement.color }"
              />
              <span class="text-white text-sm">{{ mockupStore.selectedElement.color }}</span>
            </div>
          </div>
        </div>
      </template>

      <!-- Button Properties -->
      <template v-else-if="mockupStore.selectedElement.type === 'button'">
        <div class="space-y-4">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-300">Button Text</label>
            <input
              v-model="localText"
              @input="updateText"
              type="text"
              class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="bg-gray-700/50 rounded-lg p-3">
              <span class="text-gray-500">Position</span>
              <p class="text-white">{{ mockupStore.selectedElement.x }}, {{ mockupStore.selectedElement.y }}</p>
            </div>
            <div class="bg-gray-700/50 rounded-lg p-3">
              <span class="text-gray-500">Größe</span>
              <p class="text-white">{{ mockupStore.selectedElement.width }} x {{ mockupStore.selectedElement.height }}</p>
            </div>
          </div>
        </div>
      </template>

      <!-- Generic Properties -->
      <template v-else>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="bg-gray-700/50 rounded-lg p-3">
              <span class="text-gray-500">Typ</span>
              <p class="text-white capitalize">{{ mockupStore.selectedElement.type }}</p>
            </div>
            <div v-if="mockupStore.selectedElement.x !== undefined" class="bg-gray-700/50 rounded-lg p-3">
              <span class="text-gray-500">Position</span>
              <p class="text-white">{{ mockupStore.selectedElement.x }}, {{ mockupStore.selectedElement.y }}</p>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
