<script setup>
import { ref, computed, watch } from 'vue'
import { useMockupStore } from '../stores/mockupStore'
import {
  LanguageIcon,
  Cog6ToothIcon,
  CubeIcon,
  CheckCircleIcon,
  SparklesIcon,
  ShieldCheckIcon,
  BoltIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  XMarkIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline'

const mockupStore = useMockupStore()

// Row configuration for chip positioning
const chipConfig = {
  baseX: 50,
  chipGap: 12,
  rowGap: 45, // Vertical gap between category rows
  chipHeight: 36,
  fontSize: 14,
  padding: '10px 16px',
}

// Feature categories with presets - organized by row priority (bottom to top)
const featureCategories = ref([
  {
    id: 'languages',
    name: 'Sprachen',
    icon: LanguageIcon,
    expanded: true,
    rowIndex: 0, // Bottom row
    presets: [
      { id: 'de', label: 'Deutsch', chipText: 'DE', flag: '🇩🇪', color: '#ffffff' },
      { id: 'en', label: 'English', chipText: 'EN', flag: '🇬🇧', color: '#ffffff' },
      { id: 'fr', label: 'Français', chipText: 'FR', flag: '🇫🇷', color: '#ffffff' },
      { id: 'es', label: 'Español', chipText: 'ES', flag: '🇪🇸', color: '#ffffff' },
      { id: 'pl', label: 'Polski', chipText: 'PL', flag: '🇵🇱', color: '#ffffff' },
      { id: 'tr', label: 'Türkçe', chipText: 'TR', flag: '🇹🇷', color: '#ffffff' },
    ]
  },
  {
    id: 'framework',
    name: 'Framework',
    icon: CubeIcon,
    expanded: true,
    rowIndex: 1, // Second row from bottom
    presets: [
      { id: 'esx', label: 'ESX', chipText: 'ESX', color: '#4ade80' },
      { id: 'qbcore', label: 'QBCore', chipText: 'QBCore', color: '#60a5fa' },
      { id: 'standalone', label: 'Standalone', chipText: 'Standalone', color: '#f4b400' },
      { id: 'vrp', label: 'vRP', chipText: 'vRP', color: '#c084fc' },
      { id: 'ox', label: 'OX Core', chipText: 'OX Core', color: '#f97316' },
    ]
  },
  {
    id: 'config',
    name: 'Konfiguration',
    icon: Cog6ToothIcon,
    expanded: true,
    rowIndex: 2, // Third row
    presets: [
      { id: 'configurable', label: 'Config File', chipText: 'Config File', icon: '⚙️', color: '#9898a3' },
      { id: 'database', label: 'Database', chipText: 'Database', icon: '🗄️', color: '#9898a3' },
      { id: 'webhook', label: 'Discord Webhook', chipText: 'Discord', icon: '🔗', color: '#5865F2' },
      { id: 'permissions', label: 'Permissions', chipText: 'Permissions', icon: '🔐', color: '#9898a3' },
    ]
  },
  {
    id: 'features',
    name: 'Features',
    icon: SparklesIcon,
    expanded: false,
    rowIndex: 3, // Fourth row
    presets: [
      { id: 'optimized', label: 'Optimized', chipText: 'Optimized', icon: '⚡', color: '#f4b400' },
      { id: 'secure', label: 'Secure', chipText: 'Secure', icon: '🔒', color: '#4ade80' },
      { id: 'updates', label: 'Free Updates', chipText: 'Free Updates', icon: '🔄', color: '#60a5fa' },
      { id: 'support', label: '24/7 Support', chipText: '24/7 Support', icon: '💬', color: '#f472b6' },
      { id: 'opensource', label: 'Open Source', chipText: 'Open Source', icon: '📂', color: '#9898a3' },
      { id: 'documented', label: 'Documented', chipText: 'Documented', icon: '📚', color: '#9898a3' },
    ]
  },
])

// Custom chips added by user
const customChips = ref([])
const showAddCustom = ref(false)
const newCustomText = ref('')
const newCustomColor = ref('#9898a3')

// Check if a feature chip is enabled (exists in elements)
const isFeatureEnabled = (categoryId, presetId) => {
  const elementId = `feature-${categoryId}-${presetId}`
  return mockupStore.elements.some(el => el.id === elementId)
}

// Get chips in a specific category row
const getChipsInCategory = (categoryId) => {
  return mockupStore.elements.filter(el =>
    el.id.startsWith(`feature-${categoryId}-`)
  )
}

// Calculate X position for a new chip in a category
const getNextXPosition = (categoryId) => {
  const existingChips = getChipsInCategory(categoryId)
  // Estimate chip widths based on text length (rough approximation)
  let totalWidth = 0
  existingChips.forEach(chip => {
    const textLength = (chip.text || '').length
    totalWidth += Math.max(80, textLength * 10 + 40) + chipConfig.chipGap
  })
  return chipConfig.baseX + totalWidth
}

// Get Y position for a category row
const getRowYPosition = (category) => {
  const baseY = mockupStore.canvasHeight - 60
  return baseY - (category.rowIndex * chipConfig.rowGap)
}

// Toggle a feature chip
const toggleFeature = (category, preset) => {
  const elementId = `feature-${category.id}-${preset.id}`
  const exists = mockupStore.elements.some(el => el.id === elementId)

  if (exists) {
    // Remove the chip
    mockupStore.deleteElement(elementId)
    // Reorganize remaining chips in this category
    setTimeout(() => reorganizeCategoryChips(category), 50)
  } else {
    // Build chip text
    let chipDisplayText = ''
    if (preset.flag) {
      chipDisplayText = `${preset.flag} ${preset.chipText}`
    } else if (preset.icon) {
      chipDisplayText = `${preset.icon} ${preset.chipText}`
    } else {
      chipDisplayText = `● ${preset.chipText}`
    }

    // Calculate position
    const xPos = getNextXPosition(category.id)
    const yPos = getRowYPosition(category)

    const newElement = {
      id: elementId,
      type: 'chip',
      x: xPos,
      y: yPos,
      text: chipDisplayText,
      fontFamily: '"DM Sans", "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif',
      fontSize: chipConfig.fontSize,
      color: preset.color,
      backgroundColor: 'rgba(28,28,31,0.9)',
      border: '1px solid rgba(255,255,255,0.12)',
      borderRadius: 12,
      padding: chipConfig.padding,
      featureCategory: category.id,
      featurePreset: preset.id,
    }

    mockupStore.elements.push(newElement)
  }
}

// Reorganize chips in a specific category to remove gaps
const reorganizeCategoryChips = (category) => {
  const chips = getChipsInCategory(category.id)
  const yPos = getRowYPosition(category)

  let currentX = chipConfig.baseX
  chips.forEach((chip) => {
    mockupStore.updateElement(chip.id, {
      x: currentX,
      y: yPos,
    })
    // Estimate width for next position
    const textLength = (chip.text || '').length
    currentX += Math.max(80, textLength * 10 + 40) + chipConfig.chipGap
  })
}

// Add custom chip
const addCustomChip = () => {
  if (!newCustomText.value.trim()) return

  const customId = `custom-${Date.now()}`

  // Custom chips go in their own row (row 4)
  const customRowIndex = 4
  const existingCustomChips = mockupStore.elements.filter(el => el.id.startsWith('custom-chip-'))

  let currentX = chipConfig.baseX
  existingCustomChips.forEach(chip => {
    const textLength = (chip.text || '').length
    currentX += Math.max(80, textLength * 10 + 40) + chipConfig.chipGap
  })

  const yPos = mockupStore.canvasHeight - 60 - (customRowIndex * chipConfig.rowGap)

  const newElement = {
    id: `custom-chip-${customId}`,
    type: 'chip',
    x: currentX,
    y: yPos,
    text: newCustomText.value.trim(),
    fontFamily: '"DM Sans", "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif',
    fontSize: chipConfig.fontSize,
    color: newCustomColor.value,
    backgroundColor: 'rgba(28,28,31,0.9)',
    border: '1px solid rgba(255,255,255,0.12)',
    borderRadius: 12,
    padding: chipConfig.padding,
  }

  mockupStore.elements.push(newElement)
  customChips.value.push({
    id: customId,
    elementId: `custom-chip-${customId}`,
    text: newCustomText.value.trim(),
    color: newCustomColor.value,
  })

  // Reset form
  newCustomText.value = ''
  newCustomColor.value = '#9898a3'
  showAddCustom.value = false
}

// Remove custom chip
const removeCustomChip = (chip) => {
  mockupStore.deleteElement(chip.elementId)
  const index = customChips.value.findIndex(c => c.id === chip.id)
  if (index !== -1) {
    customChips.value.splice(index, 1)
  }
  // Reorganize custom chips
  setTimeout(reorganizeCustomChips, 50)
}

// Reorganize custom chips
const reorganizeCustomChips = () => {
  const customRowIndex = 4
  const chips = mockupStore.elements.filter(el => el.id.startsWith('custom-chip-'))
  const yPos = mockupStore.canvasHeight - 60 - (customRowIndex * chipConfig.rowGap)

  let currentX = chipConfig.baseX
  chips.forEach((chip) => {
    mockupStore.updateElement(chip.id, {
      x: currentX,
      y: yPos,
    })
    const textLength = (chip.text || '').length
    currentX += Math.max(80, textLength * 10 + 40) + chipConfig.chipGap
  })
}

// Toggle category expansion
const toggleCategory = (category) => {
  category.expanded = !category.expanded
}

// Count enabled features in category
const getEnabledCount = (category) => {
  return category.presets.filter(p => isFeatureEnabled(category.id, p.id)).length
}
</script>

<template>
  <div class="p-4 space-y-4">
    <div class="flex items-center justify-between mb-2">
      <h3 class="text-sm font-semibold text-white">Feature Chips</h3>
      <span class="text-xs text-gray-500">Klicke zum Aktivieren</span>
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
            v-else
            class="w-4 h-4 rounded-full border-2 border-current flex-shrink-0"
          />
          <span class="text-sm truncate">
            <span v-if="preset.flag" class="mr-1">{{ preset.flag }}</span>
            <span v-else-if="preset.icon" class="mr-1">{{ preset.icon }}</span>
            {{ preset.label }}
          </span>
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
        💡 Chips werden nach Kategorie in Reihen organisiert. Du kannst sie danach frei verschieben.
      </p>
    </div>
  </div>
</template>
