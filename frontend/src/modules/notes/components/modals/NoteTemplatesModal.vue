<script setup>
import { ref, computed, onMounted } from 'vue'
import { useNotesStore } from '../../stores/notesStore'
import { useUiStore } from '@/stores/ui'
import {
  XMarkIcon,
  DocumentTextIcon,
  CalendarIcon,
  SunIcon,
  BriefcaseIcon,
  BugAntIcon,
  ScaleIcon,
  ChartBarIcon,
  UsersIcon,
  LightBulbIcon,
  PlusIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  parentId: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['close', 'create'])

const notesStore = useNotesStore()
const uiStore = useUiStore()

const isLoading = ref(false)
const templates = ref({
  system_templates: [],
  custom_templates: [],
  note_templates: []
})
const selectedCategory = ref('all')

// Icon mapping
const iconMap = {
  calendar: CalendarIcon,
  sun: SunIcon,
  briefcase: BriefcaseIcon,
  bug: BugAntIcon,
  scale: ScaleIcon,
  'chart-bar': ChartBarIcon,
  users: UsersIcon,
  lightbulb: LightBulbIcon,
}

// Categories
const categories = computed(() => {
  const cats = new Set(['all'])
  templates.value.system_templates.forEach(t => {
    if (t.category) cats.add(t.category)
  })
  templates.value.custom_templates.forEach(t => {
    if (t.category) cats.add(t.category)
  })
  return Array.from(cats)
})

// Filtered templates
const filteredTemplates = computed(() => {
  let all = [
    ...templates.value.system_templates.map(t => ({ ...t, source: 'system' })),
    ...templates.value.custom_templates.map(t => ({ ...t, source: 'custom' })),
    ...templates.value.note_templates.map(t => ({ ...t, source: 'note' })),
  ]

  if (selectedCategory.value !== 'all') {
    all = all.filter(t => t.category === selectedCategory.value)
  }

  return all
})

// Category labels
const categoryLabels = {
  all: 'Alle',
  meetings: 'Meetings',
  personal: 'Persönlich',
  work: 'Arbeit',
  development: 'Entwicklung',
  creative: 'Kreativ',
  user_note: 'Meine Vorlagen'
}

// Load templates
async function loadTemplates() {
  isLoading.value = true
  try {
    templates.value = await notesStore.getTemplates()
  } catch (error) {
    uiStore.showError('Fehler beim Laden der Vorlagen')
  } finally {
    isLoading.value = false
  }
}

// Create note from template
async function selectTemplate(template) {
  try {
    const noteData = await notesStore.createFromTemplate(template.id, {
      parent_id: props.parentId
    })
    emit('create', noteData)
    emit('close')
    uiStore.showSuccess('Notiz aus Vorlage erstellt')
  } catch (error) {
    uiStore.showError('Fehler beim Erstellen der Notiz')
  }
}

// Create empty note
function createEmpty() {
  emit('create', { useEmpty: true })
  emit('close')
}

// Get icon component
function getIconComponent(iconName) {
  return iconMap[iconName] || DocumentTextIcon
}

onMounted(() => {
  if (props.show) {
    loadTemplates()
  }
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
      @click.self="$emit('close')"
    >
      <div class="bg-dark-800 rounded-xl shadow-xl w-full max-w-3xl max-h-[80vh] flex flex-col overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
          <h2 class="text-lg font-semibold text-white">Vorlage auswählen</h2>
          <button
            @click="$emit('close')"
            class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg"
          >
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- Category tabs -->
        <div class="flex gap-2 px-6 py-3 border-b border-dark-700 overflow-x-auto">
          <button
            v-for="cat in categories"
            :key="cat"
            @click="selectedCategory = cat"
            :class="[
              'px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-colors',
              selectedCategory === cat
                ? 'bg-primary-600 text-white'
                : 'text-gray-400 hover:text-white hover:bg-dark-700'
            ]"
          >
            {{ categoryLabels[cat] || cat }}
          </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-6">
          <!-- Loading -->
          <div v-if="isLoading" class="flex items-center justify-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
          </div>

          <!-- Empty -->
          <div v-else-if="filteredTemplates.length === 0" class="text-center py-12 text-gray-500">
            Keine Vorlagen gefunden
          </div>

          <!-- Templates grid -->
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Empty note card -->
            <button
              @click="createEmpty"
              class="flex flex-col items-center justify-center p-6 rounded-lg border-2 border-dashed border-dark-600 hover:border-primary-500 hover:bg-dark-700/50 transition-all group"
            >
              <div class="w-12 h-12 rounded-lg bg-dark-700 flex items-center justify-center mb-3 group-hover:bg-primary-600/20">
                <PlusIcon class="w-6 h-6 text-gray-500 group-hover:text-primary-400" />
              </div>
              <span class="font-medium text-gray-300">Leere Notiz</span>
              <span class="text-xs text-gray-500 mt-1">Ohne Vorlage starten</span>
            </button>

            <!-- Template cards -->
            <button
              v-for="template in filteredTemplates"
              :key="template.id"
              @click="selectTemplate(template)"
              class="flex flex-col p-4 rounded-lg bg-dark-700 hover:bg-dark-600 transition-all text-left group"
            >
              <div class="flex items-start gap-3 mb-2">
                <div class="w-10 h-10 rounded-lg bg-dark-600 group-hover:bg-primary-600/20 flex items-center justify-center flex-shrink-0">
                  <span v-if="template.icon && !iconMap[template.icon]" class="text-lg">{{ template.icon }}</span>
                  <component
                    v-else
                    :is="getIconComponent(template.icon)"
                    class="w-5 h-5 text-gray-400 group-hover:text-primary-400"
                  />
                </div>
                <div class="flex-1 min-w-0">
                  <h3 class="font-medium text-white truncate">{{ template.name }}</h3>
                  <p v-if="template.description" class="text-xs text-gray-500 line-clamp-2 mt-0.5">
                    {{ template.description }}
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-2 mt-auto pt-2 border-t border-dark-600">
                <span
                  :class="[
                    'text-xs px-2 py-0.5 rounded',
                    template.source === 'system' ? 'bg-blue-500/20 text-blue-400' :
                    template.source === 'note' ? 'bg-green-500/20 text-green-400' :
                    'bg-purple-500/20 text-purple-400'
                  ]"
                >
                  {{ template.source === 'system' ? 'System' : template.source === 'note' ? 'Eigene' : 'Benutzerdefiniert' }}
                </span>
                <span v-if="template.category" class="text-xs text-gray-500">
                  {{ categoryLabels[template.category] || template.category }}
                </span>
              </div>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
