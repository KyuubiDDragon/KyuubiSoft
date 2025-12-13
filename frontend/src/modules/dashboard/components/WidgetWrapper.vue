<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  widget: {
    type: Object,
    required: true
  },
  index: {
    type: Number,
    required: true
  },
  isEditMode: {
    type: Boolean,
    default: false
  },
  isDragging: {
    type: Boolean,
    default: false
  },
  style: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['remove', 'dragstart', 'dragend', 'resize'])

function onResizeStart(direction, event) {
  emit('resize', direction, event)
}
</script>

<template>
  <div
    class="card p-6 relative group transition-all duration-200 overflow-hidden"
    :class="[
      isEditMode ? 'cursor-move ring-2 ring-transparent hover:ring-primary-500/50' : '',
      isDragging ? 'opacity-50' : ''
    ]"
    :style="style"
    :draggable="isEditMode"
    @dragstart="$emit('dragstart', $event)"
    @dragend="$emit('dragend', $event)"
  >
    <!-- Remove button -->
    <button
      v-if="isEditMode"
      @click.stop="$emit('remove', index)"
      class="absolute top-2 right-2 z-20 p-1 text-gray-500 hover:text-red-400 bg-dark-800/80 rounded opacity-0 group-hover:opacity-100 transition-opacity"
    >
      <XMarkIcon class="w-4 h-4" />
    </button>

    <!-- Resize handles (only in edit mode) -->
    <template v-if="isEditMode">
      <!-- Corner handles -->
      <div
        class="absolute bottom-0 right-0 w-4 h-4 cursor-se-resize z-10 opacity-0 group-hover:opacity-100 transition-opacity"
        @mousedown.stop.prevent="onResizeStart('se', $event)"
      >
        <div class="absolute bottom-1 right-1 w-2 h-2 bg-primary-500 rounded-sm"></div>
      </div>
      <div
        class="absolute top-0 right-0 w-4 h-4 cursor-ne-resize z-10 opacity-0 group-hover:opacity-100 transition-opacity"
        @mousedown.stop.prevent="onResizeStart('ne', $event)"
      >
        <div class="absolute top-1 right-1 w-2 h-2 bg-primary-500 rounded-sm"></div>
      </div>
      <div
        class="absolute bottom-0 left-0 w-4 h-4 cursor-sw-resize z-10 opacity-0 group-hover:opacity-100 transition-opacity"
        @mousedown.stop.prevent="onResizeStart('sw', $event)"
      >
        <div class="absolute bottom-1 left-1 w-2 h-2 bg-primary-500 rounded-sm"></div>
      </div>
      <div
        class="absolute top-0 left-0 w-4 h-4 cursor-nw-resize z-10 opacity-0 group-hover:opacity-100 transition-opacity"
        @mousedown.stop.prevent="onResizeStart('nw', $event)"
      >
        <div class="absolute top-1 left-1 w-2 h-2 bg-primary-500 rounded-sm"></div>
      </div>

      <!-- Edge handles -->
      <div
        class="absolute top-0 left-4 right-4 h-2 cursor-n-resize z-10 opacity-0 group-hover:opacity-100 transition-opacity"
        @mousedown.stop.prevent="onResizeStart('n', $event)"
      >
        <div class="mx-auto w-8 h-1 bg-primary-500/50 rounded mt-0.5"></div>
      </div>
      <div
        class="absolute bottom-0 left-4 right-4 h-2 cursor-s-resize z-10 opacity-0 group-hover:opacity-100 transition-opacity"
        @mousedown.stop.prevent="onResizeStart('s', $event)"
      >
        <div class="mx-auto w-8 h-1 bg-primary-500/50 rounded mb-0.5"></div>
      </div>
      <div
        class="absolute left-0 top-4 bottom-4 w-2 cursor-w-resize z-10 opacity-0 group-hover:opacity-100 transition-opacity"
        @mousedown.stop.prevent="onResizeStart('w', $event)"
      >
        <div class="absolute left-0.5 top-1/2 -translate-y-1/2 w-1 h-8 bg-primary-500/50 rounded"></div>
      </div>
      <div
        class="absolute right-0 top-4 bottom-4 w-2 cursor-e-resize z-10 opacity-0 group-hover:opacity-100 transition-opacity"
        @mousedown.stop.prevent="onResizeStart('e', $event)"
      >
        <div class="absolute right-0.5 top-1/2 -translate-y-1/2 w-1 h-8 bg-primary-500/50 rounded"></div>
      </div>
    </template>

    <!-- Widget content -->
    <slot></slot>
  </div>
</template>
