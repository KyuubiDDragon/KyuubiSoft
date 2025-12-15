<script setup>
import { ChevronRightIcon, HomeIcon } from '@heroicons/vue/24/outline'

defineProps({
  breadcrumb: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['navigate'])

function navigate(noteId) {
  emit('navigate', noteId)
}
</script>

<template>
  <nav class="flex items-center gap-1 text-sm">
    <button
      @click="$router.push('/notes')"
      class="flex items-center text-gray-500 hover:text-gray-300"
    >
      <HomeIcon class="h-4 w-4" />
    </button>

    <template v-if="breadcrumb.length > 0">
      <ChevronRightIcon class="h-4 w-4 text-gray-600" />

      <template v-for="(item, index) in breadcrumb" :key="item.id">
        <button
          @click="navigate(item.id)"
          :class="[
            'flex items-center gap-1 rounded px-1.5 py-0.5 transition-colors',
            index === breadcrumb.length - 1
              ? 'text-white font-medium'
              : 'text-gray-400 hover:text-gray-300 hover:bg-dark-700'
          ]"
        >
          <span v-if="item.icon" class="text-sm">{{ item.icon }}</span>
          <span class="max-w-32 truncate">{{ item.title }}</span>
        </button>

        <ChevronRightIcon
          v-if="index < breadcrumb.length - 1"
          class="h-4 w-4 text-gray-600"
        />
      </template>
    </template>
  </nav>
</template>
