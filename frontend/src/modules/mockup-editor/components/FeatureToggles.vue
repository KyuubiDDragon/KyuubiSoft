<script setup>
import { ref, computed, watch } from 'vue'
import { useMockupStore } from '../stores/mockupStore'
import {
  LanguageIcon,
  Cog6ToothIcon,
  CubeIcon,
  CheckCircleIcon,
  SparklesIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  XMarkIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline'

const mockupStore = useMockupStore()

// Chip configuration
const chipConfig = {
  chipGap: 12,        // Horizontal gap between chips (consistent spacing)
  rowGap: 52,         // Vertical gap between category rows (larger for better separation)
  fontSize: 13,
  padding: '8px 14px',
}

// Calculate chip width based on text and type
const getChipWidth = (text, isLanguage = false) => {
  const cleanText = (text || '').replace('● ', '')
  if (isLanguage) {
    // Language chips are compact (DE, EN, FR, etc.)
    return 48
  }
  // Other chips: base width + character-based width
  // Use generous calculation to account for variable-width fonts
  return Math.max(70, cleanText.length * 9 + 40)
}

// Get chip position based on current template
const getChipBasePosition = () => {
  const template = mockupStore.currentTemplate
  const templateId = template?.id || ''

  // Templates that should have chips at top-left
  const topLeftTemplates = ['wide-screens-banner', 'update-banner', 'discord-banner', 'gallery-strip']

  if (topLeftTemplates.includes(templateId)) {
    return {
      baseX: 30,
      baseY: 35,
      direction: 'down' // Rows go downward from top
    }
  }

  // Default: bottom-left (higher up and more to the left)
  return {
    baseX: 30,
    baseY: mockupStore.canvasHeight - 35,
    direction: 'up' // Rows go upward from bottom
  }
}

// Feature categories with presets
const featureCategories = ref([
  {
    id: 'languages',
    name: 'Sprachen',
    icon: LanguageIcon,
    expanded: true,
    rowIndex: 0,
    presets: [
      // Germany: Black-Red-Gold horizontal stripes
      { id: 'de', label: 'Deutsch', chipText: 'DE', bgGradient: 'linear-gradient(180deg, #000000 33%, #DD0000 33%, #DD0000 66%, #FFCC00 66%)', textColor: '#FFFFFF', textShadow: '0 1px 2px rgba(0,0,0,0.8)' },
      // UK: Union Jack Blue with red accents
      { id: 'en', label: 'English', chipText: 'EN', bgGradient: 'linear-gradient(135deg, #012169 25%, #C8102E 25%, #C8102E 30%, #FFFFFF 30%, #FFFFFF 35%, #012169 35%)', textColor: '#FFFFFF', textShadow: '0 1px 2px rgba(0,0,0,0.6)' },
      // France: Blue-White-Red vertical stripes
      { id: 'fr', label: 'Français', chipText: 'FR', bgGradient: 'linear-gradient(90deg, #002395 33%, #FFFFFF 33%, #FFFFFF 66%, #ED2939 66%)', textColor: '#002395', textShadow: '0 0 3px rgba(255,255,255,0.8)' },
      // Spain: Red-Yellow-Red horizontal
      { id: 'es', label: 'Español', chipText: 'ES', bgGradient: 'linear-gradient(180deg, #AA151B 25%, #F1BF00 25%, #F1BF00 75%, #AA151B 75%)', textColor: '#AA151B', textShadow: '0 0 2px rgba(241,191,0,0.9)' },
      // Poland: White-Red horizontal
      { id: 'pl', label: 'Polski', chipText: 'PL', bgGradient: 'linear-gradient(180deg, #FFFFFF 50%, #DC143C 50%)', textColor: '#DC143C', textShadow: '0 0 2px rgba(255,255,255,0.8)' },
      // Turkey: Red with white crescent hint
      { id: 'tr', label: 'Türkçe', chipText: 'TR', bgGradient: 'linear-gradient(135deg, #E30A17 0%, #E30A17 100%)', textColor: '#FFFFFF', textShadow: '0 1px 2px rgba(0,0,0,0.5)' },
    ]
  },
  {
    id: 'framework',
    name: 'Framework',
    icon: CubeIcon,
    expanded: true,
    rowIndex: 1,
    presets: [
      { id: 'esx', label: 'ESX', chipText: 'ESX', dotColor: '#4ade80', textColor: '#4ade80' },
      { id: 'qbcore', label: 'QBCore', chipText: 'QBCore', dotColor: '#60a5fa', textColor: '#60a5fa' },
      { id: 'standalone', label: 'Standalone', chipText: 'Standalone', dotColor: '#f4b400', textColor: '#f4b400' },
      { id: 'vrp', label: 'vRP', chipText: 'vRP', dotColor: '#c084fc', textColor: '#c084fc' },
      { id: 'ox', label: 'OX Core', chipText: 'OX Core', dotColor: '#f97316', textColor: '#f97316' },
    ]
  },
  {
    id: 'config',
    name: 'Konfiguration',
    icon: Cog6ToothIcon,
    expanded: true,
    rowIndex: 2,
    presets: [
      { id: 'configurable', label: 'Config File', chipText: 'Config', dotColor: '#9898a3', textColor: '#ffffff' },
      { id: 'database', label: 'Database', chipText: 'Database', dotColor: '#9898a3', textColor: '#ffffff' },
      { id: 'webhook', label: 'Discord Webhook', chipText: 'Discord', dotColor: '#5865F2', textColor: '#5865F2' },
      { id: 'permissions', label: 'Permissions', chipText: 'Permissions', dotColor: '#9898a3', textColor: '#ffffff' },
    ]
  },
  {
    id: 'features',
    name: 'Features',
    icon: SparklesIcon,
    expanded: false,
    rowIndex: 3,
    presets: [
      { id: 'optimized', label: 'Optimized', chipText: 'Optimized', dotColor: '#f4b400', textColor: '#f4b400' },
      { id: 'secure', label: 'Secure', chipText: 'Secure', dotColor: '#4ade80', textColor: '#4ade80' },
      { id: 'updates', label: 'Free Updates', chipText: 'Free Updates', dotColor: '#60a5fa', textColor: '#60a5fa' },
      { id: 'support', label: '24/7 Support', chipText: '24/7 Support', dotColor: '#f472b6', textColor: '#f472b6' },
      { id: 'opensource', label: 'Open Source', chipText: 'Open Source', dotColor: '#9898a3', textColor: '#ffffff' },
      { id: 'documented', label: 'Documented', chipText: 'Documented', dotColor: '#9898a3', textColor: '#ffffff' },
    ]
  },
])

// Custom chips
const customChips = ref([])
const showAddCustom = ref(false)
const newCustomText = ref('')
const newCustomColor = ref('#f4b400')

// Check if feature is enabled
const isFeatureEnabled = (categoryId, presetId) => {
  const elementId = `feature-${categoryId}-${presetId}`
  return mockupStore.elements.some(el => el.id === elementId)
}

// Get all chips in a category
const getChipsInCategory = (categoryId) => {
  return mockupStore.elements.filter(el => el.id.startsWith(`feature-${categoryId}-`))
}

// Calculate Y position for a row
// For consistent top-to-bottom order: Languages, Framework, Config, Features
const getRowYPosition = (rowIndex) => {
  const pos = getChipBasePosition()
  const maxRows = 5 // 4 categories + 1 custom

  if (pos.direction === 'down') {
    // Top positioning: row 0 at top, increasing downward
    return pos.baseY + (rowIndex * chipConfig.rowGap)
  } else {
    // Bottom positioning: invert so row 0 (Languages) is at TOP of the chip area
    // This keeps visual order consistent: Languages at top, Features at bottom
    const invertedIndex = maxRows - 1 - rowIndex
    return pos.baseY - (invertedIndex * chipConfig.rowGap)
  }
}

// Reorganize all chips in a category with consistent spacing
const reorganizeCategoryChips = (categoryId, rowIndex) => {
  const chips = getChipsInCategory(categoryId)
  const pos = getChipBasePosition()
  const yPos = getRowYPosition(rowIndex)

  let currentX = pos.baseX
  chips.forEach((chip) => {
    mockupStore.updateElement(chip.id, { x: currentX, y: yPos })
    const isLanguageChip = chip.featureCategory === 'languages'
    const chipWidth = getChipWidth(chip.text, isLanguageChip)
    currentX += chipWidth + chipConfig.chipGap
  })
}

// Toggle feature chip
const toggleFeature = (category, preset) => {
  const elementId = `feature-${category.id}-${preset.id}`
  const exists = mockupStore.elements.some(el => el.id === elementId)

  if (exists) {
    mockupStore.deleteElement(elementId)
    setTimeout(() => reorganizeCategoryChips(category.id, category.rowIndex), 50)
  } else {
    const pos = getChipBasePosition()
    const existingChips = getChipsInCategory(category.id)
    const yPos = getRowYPosition(category.rowIndex)

    // Calculate X position with consistent spacing
    let currentX = pos.baseX
    existingChips.forEach(chip => {
      const isLanguageChip = chip.featureCategory === 'languages'
      const chipWidth = getChipWidth(chip.text, isLanguageChip)
      currentX += chipWidth + chipConfig.chipGap
    })

    // Build chip - use flag gradient for languages, dot for others
    let chipText, chipBgColor, chipBgGradient, chipTextColor, chipBorder, chipTextShadow

    if (preset.bgGradient) {
      // Language chip with flag gradient background
      chipText = preset.chipText
      chipBgGradient = preset.bgGradient
      chipBgColor = undefined
      chipTextColor = preset.textColor
      chipTextShadow = preset.textShadow
      chipBorder = '1px solid rgba(255,255,255,0.25)'
    } else {
      // Other chips with dot indicator
      chipText = `● ${preset.chipText}`
      chipBgColor = 'rgba(28,28,31,0.9)'
      chipBgGradient = undefined
      chipTextColor = preset.textColor || '#ffffff'
      chipTextShadow = undefined
      chipBorder = '1px solid rgba(255,255,255,0.12)'
    }

    const newElement = {
      id: elementId,
      type: 'chip',
      x: currentX,
      y: yPos,
      text: chipText,
      fontFamily: '"DM Sans", sans-serif',
      fontSize: chipConfig.fontSize,
      fontWeight: 700,
      color: chipTextColor,
      backgroundColor: chipBgColor,
      backgroundGradient: chipBgGradient,
      textShadow: chipTextShadow,
      border: chipBorder,
      borderRadius: 6,
      padding: preset.bgGradient ? '6px 12px' : chipConfig.padding,
      featureCategory: category.id,
      featurePreset: preset.id,
    }

    mockupStore.elements.push(newElement)
  }
}

// Custom chip functions
const addCustomChip = () => {
  if (!newCustomText.value.trim()) return

  const customId = `custom-${Date.now()}`
  const customRowIndex = 4
  const pos = getChipBasePosition()
  const yPos = getRowYPosition(customRowIndex)

  const existingCustomChips = mockupStore.elements.filter(el => el.id.startsWith('custom-chip-'))

  let currentX = pos.baseX
  existingCustomChips.forEach(chip => {
    const chipWidth = getChipWidth(chip.text, false)
    currentX += chipWidth + chipConfig.chipGap
  })

  const newElement = {
    id: `custom-chip-${customId}`,
    type: 'chip',
    x: currentX,
    y: yPos,
    text: `● ${newCustomText.value.trim()}`,
    fontFamily: '"DM Sans", sans-serif',
    fontSize: chipConfig.fontSize,
    fontWeight: 600,
    color: newCustomColor.value,
    backgroundColor: 'rgba(28,28,31,0.9)',
    border: '1px solid rgba(255,255,255,0.12)',
    borderRadius: 8,
    padding: chipConfig.padding,
  }

  mockupStore.elements.push(newElement)
  customChips.value.push({
    id: customId,
    elementId: `custom-chip-${customId}`,
    text: newCustomText.value.trim(),
    color: newCustomColor.value,
  })

  newCustomText.value = ''
  newCustomColor.value = '#f4b400'
  showAddCustom.value = false
}

const removeCustomChip = (chip) => {
  mockupStore.deleteElement(chip.elementId)
  const index = customChips.value.findIndex(c => c.id === chip.id)
  if (index !== -1) customChips.value.splice(index, 1)
  setTimeout(reorganizeCustomChips, 50)
}

const reorganizeCustomChips = () => {
  const customRowIndex = 4
  const pos = getChipBasePosition()
  const yPos = getRowYPosition(customRowIndex)

  const chips = mockupStore.elements.filter(el => el.id.startsWith('custom-chip-'))

  let currentX = pos.baseX
  chips.forEach((chip) => {
    mockupStore.updateElement(chip.id, { x: currentX, y: yPos })
    const chipWidth = getChipWidth(chip.text, false)
    currentX += chipWidth + chipConfig.chipGap
  })
}

// UI helpers
const toggleCategory = (category) => {
  category.expanded = !category.expanded
}

const getEnabledCount = (category) => {
  return category.presets.filter(p => isFeatureEnabled(category.id, p.id)).length
}

// Get position label for info text
const positionLabel = computed(() => {
  const pos = getChipBasePosition()
  return pos.direction === 'down' ? 'oben links' : 'unten links'
})
</script>

<template>
  <div class="p-4 space-y-4">
    <div class="flex items-center justify-between mb-2">
      <h3 class="text-sm font-semibold text-white">Feature Chips</h3>
      <span class="text-xs text-gray-500">{{ positionLabel }}</span>
    </div>

    <!-- Feature Categories -->
    <div v-for="category in featureCategories" :key="category.id" class="space-y-2">
      <!-- Category Header -->
      <button
        @click="toggleCategory(category)"
        class="w-full flex items-center justify-between p-2 bg-gray-700/50 hover:bg-gray-700 rounded-lg transition-colors"
      >
        <div class="flex items-center gap-2">
          <component :is="category.icon" class="w-4 h-4 text-amber-400" />
          <span class="text-sm font-medium text-white">{{ category.name }}</span>
          <span
            v-if="getEnabledCount(category) > 0"
            class="px-1.5 py-0.5 text-xs bg-amber-500/20 text-amber-400 rounded"
          >
            {{ getEnabledCount(category) }}
          </span>
        </div>
        <component
          :is="category.expanded ? ChevronUpIcon : ChevronDownIcon"
          class="w-4 h-4 text-gray-400"
        />
      </button>

      <!-- Category Presets -->
      <div v-if="category.expanded" class="grid grid-cols-2 gap-2 pl-2">
        <button
          v-for="preset in category.presets"
          :key="preset.id"
          @click="toggleFeature(category, preset)"
          class="flex items-center gap-2 px-3 py-2.5 rounded-lg border transition-all text-left"
          :class="isFeatureEnabled(category.id, preset.id)
            ? 'bg-amber-500/20 border-amber-500/50 text-amber-400'
            : 'bg-gray-800 border-gray-700 text-gray-400 hover:border-gray-600 hover:text-gray-300'"
        >
          <CheckCircleIcon
            v-if="isFeatureEnabled(category.id, preset.id)"
            class="w-4 h-4 flex-shrink-0"
          />
          <div
            v-else-if="preset.bgGradient"
            class="w-5 h-3.5 rounded flex-shrink-0 border border-white/20"
            :style="{ background: preset.bgGradient }"
          />
          <div
            v-else
            class="w-3 h-3 rounded-full flex-shrink-0"
            :style="{ backgroundColor: preset.dotColor }"
          />
          <span class="text-sm truncate">{{ preset.label }}</span>
        </button>
      </div>
    </div>

    <!-- Divider -->
    <div class="border-t border-gray-700 my-4" />

    <!-- Custom Chips Section -->
    <div class="space-y-3">
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-white">Eigene Chips</span>
        <button
          @click="showAddCustom = !showAddCustom"
          class="p-1 text-gray-400 hover:text-amber-400 transition-colors"
        >
          <PlusIcon v-if="!showAddCustom" class="w-4 h-4" />
          <XMarkIcon v-else class="w-4 h-4" />
        </button>
      </div>

      <!-- Add Custom Form -->
      <div v-if="showAddCustom" class="space-y-2 p-3 bg-gray-800 rounded-lg border border-gray-700">
        <div class="flex items-center gap-2">
          <input
            v-model="newCustomColor"
            type="color"
            class="w-8 h-8 rounded cursor-pointer border-0 bg-transparent flex-shrink-0"
          />
          <input
            v-model="newCustomText"
            type="text"
            placeholder="Chip Text..."
            class="flex-1 bg-gray-700 border border-gray-600 rounded px-2 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
            @keypress.enter="addCustomChip"
          />
        </div>
        <button
          @click="addCustomChip"
          :disabled="!newCustomText.trim()"
          class="w-full py-1.5 bg-amber-500 hover:bg-amber-400 text-gray-900 text-sm font-medium rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Hinzufügen
        </button>
      </div>

      <!-- Custom Chips List -->
      <div v-if="customChips.length > 0" class="space-y-2">
        <div
          v-for="chip in customChips"
          :key="chip.id"
          class="flex items-center justify-between p-2 bg-gray-800 rounded-lg border border-gray-700"
        >
          <div class="flex items-center gap-2">
            <div
              class="w-3 h-3 rounded-full"
              :style="{ backgroundColor: chip.color }"
            />
            <span class="text-sm text-white">{{ chip.text }}</span>
          </div>
          <button
            @click="removeCustomChip(chip)"
            class="p-1 text-gray-400 hover:text-red-400 transition-colors"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
      </div>

      <p v-else-if="!showAddCustom" class="text-xs text-gray-500 text-center py-2">
        Noch keine eigenen Chips
      </p>
    </div>

    <!-- Info -->
    <div class="mt-4 p-3 bg-gray-800/50 rounded-lg border border-gray-700">
      <p class="text-xs text-gray-500">
        💡 Chips erscheinen {{ positionLabel }} und werden nach Kategorie in Reihen sortiert.
      </p>
    </div>
  </div>
</template>
