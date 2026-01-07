<script setup>
import { computed, onMounted } from 'vue'
import { useMockupStore } from '../stores/mockupStore'
import {
  PhotoIcon,
  RectangleGroupIcon,
  Square3Stack3DIcon,
  TagIcon,
  SparklesIcon,
  FolderIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'

const emit = defineEmits(['select'])
const mockupStore = useMockupStore()

// Load custom templates on mount
onMounted(() => {
  mockupStore.fetchCustomTemplates()
})

const categoryIcons = {
  hero: PhotoIcon,
  showcase: RectangleGroupIcon,
  frame: Square3Stack3DIcon,
  banner: TagIcon,
  minimal: SparklesIcon,
  custom: FolderIcon,
}

const categoryLabels = {
  hero: 'Hero Sections',
  showcase: 'Showcases',
  frame: 'Frames',
  banner: 'Banner',
  minimal: 'Minimal',
  custom: 'Eigene Templates',
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

const selectCustomTemplate = (template) => {
  mockupStore.loadCustomTemplate(template)
  emit('select', null) // Close selector
}

const deleteCustomTemplate = async (e, template) => {
  e.stopPropagation()
  if (confirm(`Template "${template.name}" wirklich löschen?`)) {
    try {
      await mockupStore.deleteCustomTemplate(template.id)
    } catch (err) {
      console.error('Failed to delete template:', err)
    }
  }
}
</script>

<template>
  <div class="p-4 space-y-6">
    <!-- Custom Templates Section (if any) -->
    <div v-if="mockupStore.customTemplates.length > 0" class="space-y-3">
      <div class="flex items-center gap-2 text-amber-400">
        <FolderIcon class="w-4 h-4" />
        <h3 class="text-sm font-medium uppercase tracking-wide">
          Meine Templates
        </h3>
        <span class="text-xs text-gray-500">({{ mockupStore.customTemplates.length }})</span>
      </div>

      <div class="grid gap-3">
        <button
          v-for="template in mockupStore.customTemplates"
          :key="template.id"
          @click="selectCustomTemplate(template)"
          class="group relative bg-amber-500/10 hover:bg-amber-500/20 rounded-lg overflow-hidden transition-all duration-200 text-left border border-amber-500/30 hover:border-amber-500/50"
        >
          <!-- Thumbnail Preview -->
          <div class="aspect-video bg-gray-900 relative overflow-hidden">
            <div class="absolute inset-2 bg-gray-700/50 rounded-lg flex items-center justify-center">
              <FolderIcon class="w-8 h-8 text-amber-500/50" />
            </div>

            <!-- Delete button -->
            <button
              @click="(e) => deleteCustomTemplate(e, template)"
              class="absolute top-1 right-1 p-1 bg-red-500/80 hover:bg-red-500 text-white rounded opacity-0 group-hover:opacity-100 transition-opacity"
              title="Template löschen"
            >
              <TrashIcon class="w-3 h-3" />
            </button>
          </div>

          <!-- Info -->
          <div class="p-3">
            <h4 class="font-medium text-amber-400 text-sm group-hover:text-amber-300 transition-colors">
              {{ template.name }}
            </h4>
            <p v-if="template.description" class="text-xs text-gray-500 mt-0.5 line-clamp-2">
              {{ template.description }}
            </p>
            <div class="flex items-center gap-2 mt-2 text-[10px] text-gray-500">
              <span>{{ template.width }} x {{ template.height }}</span>
              <span class="w-1 h-1 bg-gray-600 rounded-full"></span>
              <span>Custom</span>
            </div>
          </div>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="mockupStore.isLoading" class="text-center py-4">
      <div class="animate-spin w-6 h-6 border-2 border-amber-500 border-t-transparent rounded-full mx-auto"></div>
      <p class="text-gray-500 text-sm mt-2">Lade Templates...</p>
    </div>

    <!-- Divider if custom templates exist -->
    <div v-if="mockupStore.customTemplates.length > 0" class="border-t border-gray-700" />

    <!-- Built-in Categories -->
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
            <!-- Single Image Hero Right -->
            <div v-else-if="template.id === 'single-image-hero-right'" class="absolute inset-2 flex">
              <div class="w-2/5 flex flex-col justify-center pr-2 pl-1">
                <div class="h-2 w-16 bg-amber-500 rounded mb-1"></div>
                <div class="h-1 w-12 bg-gray-600 rounded"></div>
              </div>
              <div class="w-3/5 bg-gradient-to-l from-gray-700 to-gray-800 rounded-r-lg"></div>
            </div>
            <!-- Product Card Premium -->
            <div v-else-if="template.id === 'product-card-premium'" class="absolute inset-2 flex flex-col">
              <div class="text-center mb-1">
                <div class="h-1.5 w-12 bg-amber-500 rounded mx-auto mb-0.5"></div>
                <div class="h-1 w-8 bg-gray-600 rounded mx-auto"></div>
              </div>
              <div class="flex gap-0.5 justify-center mb-1">
                <div class="w-4 h-1 bg-gray-600 rounded"></div>
                <div class="w-4 h-1 bg-gray-600 rounded"></div>
                <div class="w-4 h-1 bg-gray-600 rounded"></div>
              </div>
              <div class="flex-1 flex items-end justify-center gap-1 pb-1">
                <div class="w-6 h-8 bg-gray-700 rounded transform rotate-[8deg] origin-bottom"></div>
                <div class="w-7 h-10 bg-gray-600 rounded"></div>
                <div class="w-6 h-8 bg-gray-700 rounded transform -rotate-[8deg] origin-bottom"></div>
              </div>
            </div>
            <!-- Wide Screens Banner -->
            <div v-else-if="template.id === 'wide-screens-banner'" class="absolute inset-2 flex flex-col">
              <div class="text-center mb-1">
                <div class="h-2 w-20 bg-amber-500 rounded mx-auto"></div>
                <div class="h-0.5 w-10 bg-amber-500/30 rounded mx-auto mt-1"></div>
              </div>
              <div class="flex-1 flex items-end justify-center gap-1 pb-1">
                <div class="w-6 h-7 bg-gray-700 rounded transform rotate-[8deg] origin-bottom"></div>
                <div class="w-8 h-9 bg-gray-600 rounded"></div>
                <div class="w-6 h-7 bg-gray-700 rounded transform -rotate-[8deg] origin-bottom"></div>
              </div>
            </div>
            <!-- Feature Gallery 3D -->
            <div v-else-if="template.id === 'feature-gallery-3d'" class="absolute inset-2 flex flex-col">
              <div class="text-center mb-1">
                <div class="h-1.5 w-14 bg-amber-500 rounded mx-auto"></div>
              </div>
              <div class="flex-1 grid grid-cols-4 gap-0.5 px-0.5">
                <div class="flex flex-col gap-0.5">
                  <div class="flex-1 bg-gray-700 rounded transform -rotate-3"></div>
                  <div class="h-1 w-6 bg-gray-600 rounded"></div>
                </div>
                <div class="flex flex-col gap-0.5">
                  <div class="flex-1 bg-gray-700 rounded"></div>
                  <div class="h-1 w-6 bg-gray-600 rounded"></div>
                </div>
                <div class="flex flex-col gap-0.5">
                  <div class="flex-1 bg-gray-700 rounded"></div>
                  <div class="h-1 w-6 bg-gray-600 rounded"></div>
                </div>
                <div class="flex flex-col gap-0.5">
                  <div class="flex-1 bg-gray-700 rounded transform rotate-3"></div>
                  <div class="h-1 w-6 bg-gray-600 rounded"></div>
                </div>
              </div>
              <div class="h-4 mx-4 mt-1 bg-gray-700 rounded"></div>
            </div>
            <!-- Feature Cards Premium -->
            <div v-else-if="template.id === 'feature-cards-premium'" class="absolute inset-2 flex items-end justify-center gap-1 pb-1">
              <div class="w-6 h-12 bg-gray-700 rounded flex flex-col p-0.5 gap-0.5">
                <div class="flex-1 bg-gray-600 rounded transform -rotate-2"></div>
                <div class="h-0.5 w-3 bg-gray-500 rounded"></div>
              </div>
              <div class="w-7 h-14 bg-gray-600 rounded border border-amber-500/30 flex flex-col p-0.5 gap-0.5">
                <div class="flex-1 bg-gray-500 rounded"></div>
                <div class="h-0.5 w-4 bg-amber-500 rounded"></div>
              </div>
              <div class="w-6 h-12 bg-gray-700 rounded flex flex-col p-0.5 gap-0.5">
                <div class="flex-1 bg-gray-600 rounded transform rotate-2"></div>
                <div class="h-0.5 w-3 bg-gray-500 rounded"></div>
              </div>
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
