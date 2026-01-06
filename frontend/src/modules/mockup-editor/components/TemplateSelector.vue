<script setup>
import { computed } from 'vue'
import { useMockupStore } from '../stores/mockupStore'
import {
  PhotoIcon,
  RectangleGroupIcon,
  Square3Stack3DIcon,
  TagIcon,
  SparklesIcon,
} from '@heroicons/vue/24/outline'

const emit = defineEmits(['select'])
const mockupStore = useMockupStore()

const categoryIcons = {
  hero: PhotoIcon,
  showcase: RectangleGroupIcon,
  frame: Square3Stack3DIcon,
  banner: TagIcon,
  minimal: SparklesIcon,
}

const categoryLabels = {
  hero: 'Hero Sections',
  showcase: 'Showcases',
  frame: 'Frames',
  banner: 'Banner',
  minimal: 'Minimal',
}

const templatesByCategory = computed(() => {
  const grouped = {}
  mockupStore.templates.forEach(template => {
    const cat = template.category || 'other'
    if (!grouped[cat]) {
      grouped[cat] = []
    }
    grouped[cat].push(template)
  })
  return grouped
})

const selectTemplate = (templateId) => {
  emit('select', templateId)
}
</script>

<template>
  <div class="p-4 space-y-6">
    <!-- Categories -->
    <div v-for="(templates, category) in templatesByCategory" :key="category" class="space-y-3">
      <div class="flex items-center gap-2 text-gray-400">
        <component :is="categoryIcons[category] || PhotoIcon" class="w-4 h-4" />
        <h3 class="text-sm font-medium uppercase tracking-wide">
          {{ categoryLabels[category] || category }}
        </h3>
      </div>

      <div class="grid gap-3">
        <button
          v-for="template in templates"
          :key="template.id"
          @click="selectTemplate(template.id)"
          class="group relative bg-gray-700/50 hover:bg-gray-700 rounded-lg overflow-hidden transition-all duration-200 text-left border border-transparent hover:border-amber-500/50"
          :class="{ 'ring-2 ring-amber-500': mockupStore.currentTemplate?.id === template.id }"
        >
          <!-- Thumbnail Preview -->
          <div class="aspect-video bg-gray-900 relative overflow-hidden">
            <!-- Mini Preview based on template -->
            <div v-if="template.id === 'single-image-hero'" class="absolute inset-2 flex">
              <div class="w-3/5 bg-gradient-to-r from-gray-700 to-gray-800 rounded-l-lg"></div>
              <div class="w-2/5 flex flex-col justify-center pl-2 pr-1">
                <div class="h-2 w-16 bg-amber-500 rounded mb-1"></div>
                <div class="h-1 w-12 bg-gray-600 rounded"></div>
              </div>
            </div>
            <div v-else-if="template.id === 'feature-showcase'" class="absolute inset-2 flex gap-1">
              <div class="w-2/3 bg-gray-700 rounded-lg"></div>
              <div class="w-1/3 flex flex-col gap-1">
                <div class="flex-1 bg-gray-700 rounded"></div>
                <div class="flex-1 bg-gray-700 rounded"></div>
                <div class="flex-1 bg-gray-700 rounded"></div>
              </div>
            </div>
            <div v-else-if="template.id === 'corner-frame'" class="absolute inset-2">
              <div class="w-full h-full border-2 border-amber-500 rounded-lg relative">
                <div class="absolute -top-0.5 -left-0.5 w-3 h-3 border-t-2 border-l-2 border-amber-500"></div>
                <div class="absolute -top-0.5 -right-0.5 w-3 h-3 border-t-2 border-r-2 border-amber-500"></div>
                <div class="absolute -bottom-0.5 -left-0.5 w-3 h-3 border-b-2 border-l-2 border-amber-500"></div>
                <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 border-b-2 border-r-2 border-amber-500"></div>
              </div>
            </div>
            <div v-else-if="template.id === 'price-banner'" class="absolute inset-2 flex items-center gap-2">
              <div class="w-8 h-8 bg-gray-700 rounded"></div>
              <div class="flex-1">
                <div class="h-2 w-12 bg-gray-600 rounded mb-1"></div>
                <div class="h-1 w-8 bg-gray-700 rounded"></div>
              </div>
              <div class="h-3 w-8 bg-amber-500 rounded"></div>
            </div>
            <div v-else-if="template.id === 'minimal-dark'" class="absolute inset-2 flex flex-col items-center justify-center gap-1">
              <div class="w-4 h-1.5 bg-gray-700 rounded"></div>
              <div class="w-4/5 h-3/5 bg-gray-700 rounded-lg shadow-lg shadow-amber-500/10"></div>
            </div>
            <div v-else class="absolute inset-2 bg-gray-700 rounded-lg"></div>

            <!-- Transparent BG indicator -->
            <div v-if="template.transparentBg" class="absolute top-1 right-1 px-1.5 py-0.5 bg-purple-500/80 text-white text-[10px] rounded">
              PNG
            </div>
          </div>

          <!-- Info -->
          <div class="p-3">
            <h4 class="font-medium text-white text-sm group-hover:text-amber-400 transition-colors">
              {{ template.name }}
            </h4>
            <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">
              {{ template.description }}
            </p>
            <div class="flex items-center gap-2 mt-2 text-[10px] text-gray-500">
              <span>{{ template.width }} x {{ template.height }}</span>
              <span class="w-1 h-1 bg-gray-600 rounded-full"></span>
              <span>{{ template.aspectRatio }}</span>
            </div>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
