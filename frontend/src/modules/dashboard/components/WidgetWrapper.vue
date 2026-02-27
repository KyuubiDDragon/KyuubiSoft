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
  isResizing: {
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
    class="card-glass p-5 relative group transition-all duration-200 overflow-y-auto"
    :class="[
      isEditMode ? 'cursor-move ring-1 ring-transparent hover:ring-primary-500/30 hover:border-primary-500/20 hover:shadow-glow-sm' : '',
      isDragging || isResizing ? 'opacity-40 scale-[0.99]' : ''
    ]"
    :style="{ ...style, height: '100%' }"
    :data-widget-height="widget.height ?? 1"
    :draggable="isEditMode && !isResizing"
    @dragstart="$emit('dragstart', $event)"
    @dragend="$emit('dragend', $event)"
  >
    <!-- Remove button -->
    <button
      v-if="isEditMode"
      @click.stop="$emit('remove', index)"
      class="absolute top-3 right-3 z-20 p-1.5 text-gray-500 hover:text-red-400
             bg-dark-950/80 backdrop-blur-sm border border-white/[0.08] rounded-lg
             opacity-0 group-hover:opacity-100 transition-all duration-150 hover:bg-red-500/10"
    >
      <XMarkIcon class="w-3.5 h-3.5" />
    </button>

    <!-- Resize handles (only in edit mode) -->
    <template v-if="isEditMode">
      <!-- Corner handles -->
      <div
        class="absolute bottom-0 right-0 w-6 h-6 cursor-se-resize z-30"
        @mousedown.stop.prevent="onResizeStart('se', $event)"
      >
        <div class="absolute bottom-1 right-1 w-3 h-3 bg-primary-500 rounded-sm opacity-0 group-hover:opacity-100 transition-opacity shadow-glow-sm" />
      </div>
      <div
        class="absolute top-0 right-0 w-6 h-6 cursor-ne-resize z-30"
        @mousedown.stop.prevent="onResizeStart('ne', $event)"
      >
        <div class="absolute top-1 right-1 w-3 h-3 bg-primary-500 rounded-sm opacity-0 group-hover:opacity-100 transition-opacity" />
      </div>
      <div
        class="absolute bottom-0 left-0 w-6 h-6 cursor-sw-resize z-30"
        @mousedown.stop.prevent="onResizeStart('sw', $event)"
      >
        <div class="absolute bottom-1 left-1 w-3 h-3 bg-primary-500 rounded-sm opacity-0 group-hover:opacity-100 transition-opacity" />
      </div>
      <div
        class="absolute top-0 left-0 w-6 h-6 cursor-nw-resize z-30"
        @mousedown.stop.prevent="onResizeStart('nw', $event)"
      >
        <div class="absolute top-1 left-1 w-3 h-3 bg-primary-500 rounded-sm opacity-0 group-hover:opacity-100 transition-opacity" />
      </div>

      <!-- Edge handles -->
      <div
        class="absolute top-0 left-6 right-6 h-3 cursor-n-resize z-20"
        @mousedown.stop.prevent="onResizeStart('n', $event)"
      >
        <div class="mx-auto w-12 h-1 bg-primary-500/50 rounded mt-0.5 opacity-0 group-hover:opacity-100 transition-opacity" />
      </div>
      <div
        class="absolute bottom-0 left-6 right-6 h-3 cursor-s-resize z-20"
        @mousedown.stop.prevent="onResizeStart('s', $event)"
      >
        <div class="mx-auto w-12 h-1 bg-primary-500/50 rounded mb-0.5 opacity-0 group-hover:opacity-100 transition-opacity" />
      </div>
      <div
        class="absolute left-0 top-6 bottom-6 w-3 cursor-w-resize z-20"
        @mousedown.stop.prevent="onResizeStart('w', $event)"
      >
        <div class="absolute left-0.5 top-1/2 -translate-y-1/2 w-1 h-12 bg-primary-500/50 rounded opacity-0 group-hover:opacity-100 transition-opacity" />
      </div>
      <div
        class="absolute right-0 top-6 bottom-6 w-3 cursor-e-resize z-20"
        @mousedown.stop.prevent="onResizeStart('e', $event)"
      >
        <div class="absolute right-0.5 top-1/2 -translate-y-1/2 w-1 h-12 bg-primary-500/50 rounded opacity-0 group-hover:opacity-100 transition-opacity" />
      </div>
    </template>

    <!-- Widget content -->
    <slot />
  </div>
</template>
