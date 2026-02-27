<script setup>
import { computed } from 'vue'
import { PlusIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
  properties: {
    type: Array,
    default: () => [],
  },
  imageProperty: {
    type: String,
    default: null,
  },
  cardSize: {
    type: String,
    default: 'medium', // small, medium, large
  },
  showProperties: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['select-row', 'add-row', 'change-image-property', 'change-card-size'])

// Get title property
const titleProperty = computed(() => {
  return props.properties.find(p => p.type === 'title') || props.properties[0]
})

// Get file/url properties for images
const imageProperties = computed(() => {
  return props.properties.filter(p => p.type === 'file' || p.type === 'url')
})

// Active image property
const activeImageProperty = computed(() => {
  if (props.imageProperty) {
    return props.properties.find(p => p.id === props.imageProperty)
  }
  return imageProperties.value[0]
})

// Properties to show (excluding title and image)
const visibleProperties = computed(() => {
  const excludeIds = [titleProperty.value?.id, activeImageProperty.value?.id].filter(Boolean)
  return props.properties.filter(p => !excludeIds.includes(p.id)).slice(0, 3)
})

// Grid class based on card size
const gridClass = computed(() => {
  switch (props.cardSize) {
    case 'small':
      return 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5'
    case 'large':
      return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3'
    default: // medium
      return 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4'
  }
})

// Card image height based on size
const imageHeightClass = computed(() => {
  switch (props.cardSize) {
    case 'small':
      return 'h-24'
    case 'large':
      return 'h-48'
    default:
      return 'h-32'
  }
})

// Get row title
function getRowTitle(row) {
  if (!titleProperty.value) return 'Untitled'
  return row.values?.[titleProperty.value.id] || 'Untitled'
}

// Get row image
function getRowImage(row) {
  if (!activeImageProperty.value) return null
  const value = row.values?.[activeImageProperty.value.id]
  if (!value) return null

  // If it's a file object with URL
  if (typeof value === 'object' && value.url) {
    return value.url
  }
  // If it's a direct URL string
  if (typeof value === 'string' && (value.startsWith('http') || value.startsWith('/'))) {
    return value
  }
  return null
}

// Get property value for display
function getPropertyValue(row, property) {
  const value = row.values?.[property.id]
  if (value === null || value === undefined) return '-'

  switch (property.type) {
    case 'checkbox':
      return value ? '✓' : '✗'
    case 'date':
      return new Date(value).toLocaleDateString('de-DE')
    case 'select':
      return value || '-'
    case 'multi_select':
      return Array.isArray(value) ? value.join(', ') : value || '-'
    case 'number':
      return typeof value === 'number' ? value.toLocaleString('de-DE') : value
    default:
      return String(value).substring(0, 50)
  }
}

// Get property color (for select/multi_select)
function getPropertyColor(property, value) {
  if (property.type !== 'select' && property.type !== 'multi_select') return null
  const option = property.options?.find(o => o.value === value)
  return option?.color || null
}

// Placeholder gradient based on row index
function getPlaceholderGradient(index) {
  const gradients = [
    'from-blue-900 to-blue-700',
    'from-purple-900 to-purple-700',
    'from-green-900 to-green-700',
    'from-yellow-900 to-yellow-700',
    'from-pink-900 to-pink-700',
    'from-cyan-900 to-cyan-700',
  ]
  return gradients[index % gradients.length]
}
</script>

<template>
  <div class="database-gallery-view">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-2">
        <!-- Image property selector -->
        <div v-if="imageProperties.length > 0" class="flex items-center gap-2">
          <span class="text-xs text-gray-500">Bild:</span>
          <select
            :value="activeImageProperty?.id"
            @change="emit('change-image-property', $event.target.value)"
            class="select"
          >
            <option value="">Kein Bild</option>
            <option v-for="prop in imageProperties" :key="prop.id" :value="prop.id">
              {{ prop.name }}
            </option>
          </select>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <!-- Card size selector -->
        <div class="flex items-center bg-white/[0.04] rounded-lg p-0.5">
          <button
            @click="emit('change-card-size', 'small')"
            :class="[
              'px-2 py-1 text-xs rounded transition-colors',
              cardSize === 'small' ? 'bg-white/[0.08] text-white' : 'text-gray-400 hover:text-white'
            ]"
          >
            Klein
          </button>
          <button
            @click="emit('change-card-size', 'medium')"
            :class="[
              'px-2 py-1 text-xs rounded transition-colors',
              cardSize === 'medium' ? 'bg-white/[0.08] text-white' : 'text-gray-400 hover:text-white'
            ]"
          >
            Mittel
          </button>
          <button
            @click="emit('change-card-size', 'large')"
            :class="[
              'px-2 py-1 text-xs rounded transition-colors',
              cardSize === 'large' ? 'bg-white/[0.08] text-white' : 'text-gray-400 hover:text-white'
            ]"
          >
            Groß
          </button>
        </div>
      </div>
    </div>

    <!-- Gallery Grid -->
    <div :class="['grid gap-4', gridClass]">
      <!-- Row Cards -->
      <div
        v-for="(row, index) in rows"
        :key="row.id"
        @click="emit('select-row', row)"
        class="group bg-white/[0.04] rounded-xl overflow-hidden border border-white/[0.06] hover:border-white/[0.08] transition-all cursor-pointer hover:shadow-glass"
      >
        <!-- Image/Cover -->
        <div :class="['relative overflow-hidden', imageHeightClass]">
          <img
            v-if="getRowImage(row)"
            :src="getRowImage(row)"
            :alt="getRowTitle(row)"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          />
          <div
            v-else
            :class="[
              'w-full h-full bg-gradient-to-br',
              getPlaceholderGradient(index),
              'flex items-center justify-center'
            ]"
          >
            <span class="text-4xl font-bold text-white/30">
              {{ getRowTitle(row).charAt(0).toUpperCase() }}
            </span>
          </div>
        </div>

        <!-- Content -->
        <div class="p-3">
          <!-- Title -->
          <h4 class="font-medium text-white truncate mb-2">
            {{ getRowTitle(row) }}
          </h4>

          <!-- Properties -->
          <div class="space-y-1">
            <div
              v-for="prop in visibleProperties"
              :key="prop.id"
              class="flex items-center gap-2 text-xs"
            >
              <span class="text-gray-500 truncate flex-shrink-0" style="max-width: 60px">
                {{ prop.name }}:
              </span>
              <span
                v-if="getPropertyColor(prop, row.values?.[prop.id])"
                :style="{ backgroundColor: getPropertyColor(prop, row.values?.[prop.id]) }"
                class="px-1.5 py-0.5 rounded text-white truncate"
              >
                {{ getPropertyValue(row, prop) }}
              </span>
              <span v-else class="text-gray-400 truncate">
                {{ getPropertyValue(row, prop) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Add New Card -->
      <button
        @click="emit('add-row')"
        :class="[
          'border-2 border-dashed border-white/[0.06] rounded-xl flex flex-col items-center justify-center text-gray-500 hover:text-white hover:border-white/[0.08] transition-colors',
          imageHeightClass
        ]"
        style="min-height: 150px"
      >
        <PlusIcon class="w-8 h-8 mb-2" />
        <span class="text-sm">Neu hinzufügen</span>
      </button>
    </div>

    <!-- Empty State -->
    <div
      v-if="rows.length === 0"
      class="text-center py-12 text-gray-500"
    >
      <div class="text-5xl mb-4">🖼️</div>
      <p class="mb-2">Noch keine Einträge</p>
      <button
        @click="emit('add-row')"
        class="text-primary-400 hover:text-primary-300 transition-colors"
      >
        Ersten Eintrag erstellen
      </button>
    </div>
  </div>
</template>

<style scoped>
/* Smooth card animations */
.database-gallery-view .group {
  transition: all 0.2s ease;
}
</style>
