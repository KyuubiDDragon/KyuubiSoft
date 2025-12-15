<script setup>
import { ref, watch } from 'vue'
import { FaceSmileIcon, PhotoIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  note: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['update:title', 'update:icon', 'update:cover'])

const editableTitle = ref(props.note?.title || '')
const showIconPicker = ref(false)

// Common emojis for quick access
const quickEmojis = [
  '📝', '📄', '📋', '📌', '🎯', '💡', '⭐', '🔥',
  '✅', '❌', '⚠️', '💼', '📁', '🏠', '🎨', '💻',
  '📊', '📈', '🔧', '⚙️', '🎉', '📚', '✨', '🚀'
]

watch(() => props.note?.title, (newTitle) => {
  editableTitle.value = newTitle || ''
})

function handleTitleBlur() {
  if (editableTitle.value !== props.note.title) {
    emit('update:title', editableTitle.value)
  }
}

function handleTitleKeydown(e) {
  if (e.key === 'Enter') {
    e.preventDefault()
    e.target.blur()
  }
}

function selectIcon(emoji) {
  emit('update:icon', emoji)
  showIconPicker.value = false
}

function removeIcon() {
  emit('update:icon', null)
  showIconPicker.value = false
}
</script>

<template>
  <div class="note-header">
    <!-- Cover Image (if present) -->
    <div
      v-if="note.cover_image"
      class="relative h-48 bg-cover bg-center"
      :style="{ backgroundImage: `url(${note.cover_image})` }"
    >
      <div class="absolute inset-0 bg-gradient-to-t from-dark-900/80 to-transparent" />
      <button
        class="absolute bottom-2 right-2 rounded bg-dark-800/80 px-2 py-1 text-xs text-gray-300 hover:bg-dark-700"
      >
        <PhotoIcon class="inline h-4 w-4 mr-1" />
        Cover ändern
      </button>
    </div>

    <!-- Icon and Title -->
    <div class="px-4 py-4">
      <div class="flex items-start gap-3">
        <!-- Icon -->
        <div class="relative">
          <button
            @click="showIconPicker = !showIconPicker"
            class="group flex h-12 w-12 items-center justify-center rounded-lg hover:bg-dark-700 transition-colors"
            :class="note.icon ? 'text-3xl' : 'text-gray-500'"
          >
            <span v-if="note.icon">{{ note.icon }}</span>
            <FaceSmileIcon v-else class="h-8 w-8 opacity-50 group-hover:opacity-100" />
          </button>

          <!-- Icon Picker Dropdown -->
          <div
            v-if="showIconPicker"
            class="absolute left-0 top-full z-50 mt-1 w-72 rounded-lg bg-dark-700 p-3 shadow-lg border border-dark-600"
          >
            <div class="mb-2 text-xs font-semibold text-gray-400">Emoji auswählen</div>
            <div class="grid grid-cols-8 gap-1">
              <button
                v-for="emoji in quickEmojis"
                :key="emoji"
                @click="selectIcon(emoji)"
                class="flex h-8 w-8 items-center justify-center rounded text-xl hover:bg-dark-600"
              >
                {{ emoji }}
              </button>
            </div>
            <button
              v-if="note.icon"
              @click="removeIcon"
              class="mt-2 w-full rounded bg-dark-600 py-1 text-xs text-gray-400 hover:bg-dark-500"
            >
              Icon entfernen
            </button>
          </div>
        </div>

        <!-- Title -->
        <div class="flex-1 min-w-0">
          <input
            v-model="editableTitle"
            type="text"
            class="w-full bg-transparent text-3xl font-bold text-white placeholder-gray-600 focus:outline-none"
            placeholder="Unbenannt"
            @blur="handleTitleBlur"
            @keydown="handleTitleKeydown"
          />
        </div>
      </div>

      <!-- Add cover button (if no cover) -->
      <button
        v-if="!note.cover_image"
        class="mt-2 flex items-center gap-1 text-xs text-gray-500 hover:text-gray-400"
      >
        <PhotoIcon class="h-4 w-4" />
        Cover hinzufügen
      </button>
    </div>

    <!-- Click outside handler -->
    <div
      v-if="showIconPicker"
      class="fixed inset-0 z-40"
      @click="showIconPicker = false"
    />
  </div>
</template>

<style scoped>
.note-header {
  @apply bg-dark-900;
}
</style>
