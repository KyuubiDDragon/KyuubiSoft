<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useTagsStore } from '@/stores/tags'
import { XMarkIcon, PlusIcon, TagIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  taggableType: {
    type: String,
    required: true
  },
  taggableId: {
    type: String,
    default: null
  },
  placeholder: {
    type: String,
    default: 'Tags hinzufügen...'
  },
  readonly: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

const tagsStore = useTagsStore()
const isOpen = ref(false)
const searchQuery = ref('')
const newTagName = ref('')
const newTagColor = ref('#6366f1')
const isCreating = ref(false)

const selectedTags = computed(() => {
  return props.modelValue.map(id => tagsStore.tagById[id]).filter(Boolean)
})

const filteredTags = computed(() => {
  const query = searchQuery.value.toLowerCase()
  return tagsStore.tags.filter(tag => {
    const notSelected = !props.modelValue.includes(tag.id)
    const matchesQuery = !query || tag.name.toLowerCase().includes(query)
    return notSelected && matchesQuery
  })
})

const canCreateTag = computed(() => {
  if (!searchQuery.value.trim()) return false
  const query = searchQuery.value.toLowerCase().trim()
  return !tagsStore.tags.some(tag => tag.name.toLowerCase() === query)
})

onMounted(async () => {
  if (tagsStore.tags.length === 0) {
    await tagsStore.loadTags()
  }

  // Load tags for existing item
  if (props.taggableId && props.modelValue.length === 0) {
    try {
      const itemTags = await tagsStore.getItemTags(props.taggableType, props.taggableId)
      emit('update:modelValue', itemTags.map(t => t.id))
    } catch (e) {
      // Ignore if item doesn't have tags yet
    }
  }
})

function selectTag(tag) {
  const newValue = [...props.modelValue, tag.id]
  emit('update:modelValue', newValue)
  emit('change', newValue)
  searchQuery.value = ''
}

function removeTag(tagId) {
  const newValue = props.modelValue.filter(id => id !== tagId)
  emit('update:modelValue', newValue)
  emit('change', newValue)
}

async function createAndSelectTag() {
  if (!searchQuery.value.trim() || isCreating.value) return

  isCreating.value = true
  try {
    const tag = await tagsStore.createTag({
      name: searchQuery.value.trim(),
      color: newTagColor.value
    })
    selectTag(tag)
  } catch (e) {
    console.error('Failed to create tag', e)
  } finally {
    isCreating.value = false
  }
}

function handleClickOutside(event) {
  if (!event.target.closest('.tag-selector')) {
    isOpen.value = false
  }
}

watch(isOpen, (open) => {
  if (open) {
    document.addEventListener('click', handleClickOutside)
  } else {
    document.removeEventListener('click', handleClickOutside)
    searchQuery.value = ''
  }
})

const predefinedColors = [
  '#6366f1', // indigo
  '#8b5cf6', // violet
  '#ec4899', // pink
  '#ef4444', // red
  '#f97316', // orange
  '#eab308', // yellow
  '#22c55e', // green
  '#14b8a6', // teal
  '#06b6d4', // cyan
  '#3b82f6', // blue
]
</script>

<template>
  <div class="tag-selector relative">
    <!-- Selected Tags & Input -->
    <div
      class="flex flex-wrap items-center gap-1.5 p-2 bg-white/[0.04] border border-white/[0.06] rounded-xl min-h-[42px] cursor-text"
      :class="{ 'border-primary-500': isOpen }"
      @click="!readonly && (isOpen = true)"
    >
      <!-- Selected Tags -->
      <span
        v-for="tag in selectedTags"
        :key="tag.id"
        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
        :style="{ backgroundColor: tag.color + '20', color: tag.color }"
      >
        <span
          class="w-2 h-2 rounded-full"
          :style="{ backgroundColor: tag.color }"
        />
        {{ tag.name }}
        <button
          v-if="!readonly"
          @click.stop="removeTag(tag.id)"
          class="ml-0.5 hover:opacity-70"
        >
          <XMarkIcon class="w-3 h-3" />
        </button>
      </span>

      <!-- Placeholder / Add Button -->
      <span
        v-if="selectedTags.length === 0 && !isOpen"
        class="text-sm text-gray-500"
      >
        {{ placeholder }}
      </span>

      <button
        v-if="!readonly && !isOpen"
        @click.stop="isOpen = true"
        class="text-gray-400 hover:text-white p-0.5"
      >
        <PlusIcon class="w-4 h-4" />
      </button>
    </div>

    <!-- Dropdown -->
    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="transform scale-95 opacity-0"
      enter-to-class="transform scale-100 opacity-100"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="transform scale-100 opacity-100"
      leave-to-class="transform scale-95 opacity-0"
    >
      <div
        v-if="isOpen && !readonly"
        class="absolute z-50 mt-1 w-full bg-dark-900/95 backdrop-blur-2xl border border-white/[0.08] rounded-xl shadow-float overflow-hidden"
      >
        <!-- Search Input -->
        <div class="p-2 border-b border-white/[0.06]">
          <input
            v-model="searchQuery"
            type="text"
            class="w-full px-3 py-1.5 input text-sm"
            placeholder="Tag suchen oder erstellen..."
            @keydown.enter="canCreateTag ? createAndSelectTag() : null"
          />
        </div>

        <!-- Tag List -->
        <div class="max-h-48 overflow-y-auto">
          <!-- Create New Tag Option -->
          <button
            v-if="canCreateTag"
            @click="createAndSelectTag"
            class="w-full flex items-center gap-2 px-3 py-2 text-left text-sm hover:bg-white/[0.04] text-primary-400"
            :disabled="isCreating"
          >
            <PlusIcon class="w-4 h-4" />
            <span v-if="isCreating">Erstelle...</span>
            <span v-else>
              "{{ searchQuery }}" erstellen
            </span>
          </button>

          <!-- Existing Tags -->
          <button
            v-for="tag in filteredTags"
            :key="tag.id"
            @click="selectTag(tag)"
            class="w-full flex items-center gap-2 px-3 py-2 text-left text-sm hover:bg-white/[0.04] text-gray-300"
          >
            <span
              class="w-3 h-3 rounded-full shrink-0"
              :style="{ backgroundColor: tag.color }"
            />
            <span class="flex-1 truncate">{{ tag.name }}</span>
            <span class="text-xs text-gray-500">{{ tag.usage_count || 0 }}</span>
          </button>

          <!-- Empty State -->
          <div
            v-if="filteredTags.length === 0 && !canCreateTag"
            class="px-3 py-4 text-center text-sm text-gray-500"
          >
            <TagIcon class="w-8 h-8 mx-auto mb-2 opacity-50" />
            <p>Keine Tags gefunden</p>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>
