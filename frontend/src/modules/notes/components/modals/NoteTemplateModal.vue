<script setup>
import { ref, computed, onMounted } from 'vue'
import { useNotesStore } from '../../stores/notesStore'
import {
  XMarkIcon,
  DocumentTextIcon,
  CalendarIcon,
  BriefcaseIcon,
  BugAntIcon,
  ScaleIcon,
  ChartBarIcon,
  UsersIcon,
  LightBulbIcon,
  SunIcon,
  Squares2X2Icon
} from '@heroicons/vue/24/outline'

const emit = defineEmits(['close', 'select'])

const notesStore = useNotesStore()

const isLoading = ref(true)
const selectedCategory = ref('all')

// Icon mapping for templates
const iconMap = {
  'calendar': CalendarIcon,
  'sun': SunIcon,
  'briefcase': BriefcaseIcon,
  'bug': BugAntIcon,
  'scale': ScaleIcon,
  'chart-bar': ChartBarIcon,
  'users': UsersIcon,
  'lightbulb': LightBulbIcon,
}

const templates = computed(() => notesStore.templates)

const categories = computed(() => {
  const cats = new Set(['all'])
  templates.value.system_templates?.forEach(t => cats.add(t.category))
  templates.value.custom_templates?.forEach(t => cats.add(t.category))
  return Array.from(cats)
})

const filteredTemplates = computed(() => {
  const all = [
    ...(templates.value.system_templates || []),
    ...(templates.value.custom_templates || []),
    ...(templates.value.note_templates || [])
  ]

  if (selectedCategory.value === 'all') {
    return all
  }

  return all.filter(t => t.category === selectedCategory.value)
})

const categoryLabels = {
  'all': 'Alle',
  'meetings': 'Meetings',
  'personal': 'Persönlich',
  'work': 'Arbeit',
  'development': 'Entwicklung',
  'creative': 'Kreativ',
  'custom': 'Eigene',
  'user_note': 'Notiz-Vorlagen'
}

onMounted(async () => {
  try {
    await notesStore.fetchTemplates()
  } finally {
    isLoading.value = false
  }
})

function selectTemplate(templateId) {
  emit('select', templateId)
}

function getIcon(iconName) {
  return iconMap[iconName] || DocumentTextIcon
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <!-- Backdrop -->
    <div
      class="absolute inset-0 bg-black/50 backdrop-blur-sm"
      @click="$emit('close')"
    />

    <!-- Modal -->
    <div class="relative w-full max-w-2xl bg-dark-800 rounded-xl shadow-2xl border border-dark-600 overflow-hidden">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-dark-600">
        <h2 class="text-lg font-semibold text-white">Vorlage auswählen</h2>
        <button
          @click="$emit('close')"
          class="rounded p-1 text-gray-400 hover:bg-dark-700 hover:text-white"
        >
          <XMarkIcon class="h-5 w-5" />
        </button>
      </div>

      <!-- Category tabs -->
      <div class="flex gap-2 px-6 py-3 border-b border-dark-700 overflow-x-auto">
        <button
          v-for="cat in categories"
          :key="cat"
          @click="selectedCategory = cat"
          :class="[
            'px-3 py-1.5 rounded-lg text-sm whitespace-nowrap transition-colors',
            selectedCategory === cat
              ? 'bg-primary-600 text-white'
              : 'bg-dark-700 text-gray-400 hover:bg-dark-600 hover:text-white'
          ]"
        >
          {{ categoryLabels[cat] || cat }}
        </button>
      </div>

      <!-- Content -->
      <div class="max-h-96 overflow-y-auto p-6">
        <!-- Loading -->
        <div v-if="isLoading" class="text-center py-8 text-gray-500">
          Laden...
        </div>

        <!-- Empty state -->
        <div v-else-if="filteredTemplates.length === 0" class="text-center py-8 text-gray-500">
          <Squares2X2Icon class="h-12 w-12 mx-auto mb-2 opacity-50" />
          <p>Keine Vorlagen in dieser Kategorie</p>
        </div>

        <!-- Templates grid -->
        <div v-else class="grid grid-cols-2 gap-4">
          <button
            v-for="template in filteredTemplates"
            :key="template.id"
            @click="selectTemplate(template.id)"
            class="flex items-start gap-3 p-4 rounded-lg bg-dark-700 hover:bg-dark-600 text-left transition-colors"
          >
            <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-primary-600/20 flex items-center justify-center">
              <component
                :is="getIcon(template.icon)"
                class="h-5 w-5 text-primary-400"
              />
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-medium text-white">{{ template.name }}</div>
              <div v-if="template.description" class="text-xs text-gray-400 mt-1 line-clamp-2">
                {{ template.description }}
              </div>
              <div class="flex items-center gap-2 mt-2">
                <span
                  v-if="template.is_system"
                  class="text-xs bg-dark-600 text-gray-400 px-2 py-0.5 rounded"
                >
                  System
                </span>
                <span class="text-xs text-gray-500">
                  {{ categoryLabels[template.category] || template.category }}
                </span>
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex justify-end gap-3 px-6 py-4 border-t border-dark-600">
        <button
          @click="$emit('close')"
          class="px-4 py-2 rounded-lg bg-dark-700 text-gray-300 hover:bg-dark-600"
        >
          Abbrechen
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
