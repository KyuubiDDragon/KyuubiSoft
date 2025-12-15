<script setup>
import { ref } from 'vue'
import { LinkIcon, ChevronRightIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'

defineProps({
  backlinks: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['navigate'])

const isExpanded = ref(true)

function navigate(noteId) {
  emit('navigate', noteId)
}

function stripHtml(html) {
  const tmp = document.createElement('div')
  tmp.innerHTML = html || ''
  return tmp.textContent || tmp.innerText || ''
}
</script>

<template>
  <aside class="w-64 border-l border-dark-700 bg-dark-850 overflow-hidden flex flex-col">
    <!-- Header -->
    <button
      @click="isExpanded = !isExpanded"
      class="flex items-center gap-2 p-3 text-sm font-semibold text-gray-400 hover:text-white border-b border-dark-700"
    >
      <LinkIcon class="h-4 w-4" />
      <span>Backlinks ({{ backlinks.length }})</span>
      <component
        :is="isExpanded ? ChevronDownIcon : ChevronRightIcon"
        class="ml-auto h-4 w-4"
      />
    </button>

    <!-- Backlinks list -->
    <div v-if="isExpanded" class="flex-1 overflow-y-auto p-2">
      <div
        v-for="link in backlinks"
        :key="link.id"
        @click="navigate(link.id)"
        class="rounded-lg p-2 mb-2 bg-dark-800 hover:bg-dark-700 cursor-pointer transition-colors"
      >
        <div class="flex items-center gap-2 mb-1">
          <span v-if="link.icon" class="text-sm">{{ link.icon }}</span>
          <LinkIcon v-else class="h-4 w-4 text-gray-500" />
          <span class="text-sm font-medium text-white truncate">{{ link.title }}</span>
        </div>
        <p class="text-xs text-gray-500 line-clamp-2">
          {{ stripHtml(link.preview) }}
        </p>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.bg-dark-850 {
  background-color: rgb(24, 24, 27);
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
