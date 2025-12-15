<script setup>
import { ref, computed } from 'vue'
import {
  ChevronRightIcon,
  PlusIcon,
  DocumentTextIcon,
  FolderIcon,
  FolderOpenIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  note: {
    type: Object,
    required: true
  },
  level: {
    type: Number,
    default: 0
  }
})

const emit = defineEmits(['select', 'create-child'])

const isExpanded = ref(false)

const hasChildren = computed(() =>
  props.note.children && props.note.children.length > 0
)

const indent = computed(() => `${props.level * 12}px`)

function toggle() {
  if (hasChildren.value) {
    isExpanded.value = !isExpanded.value
  }
}

function select() {
  emit('select', props.note.id)
}

function createChild() {
  emit('create-child', props.note.id)
}
</script>

<template>
  <div>
    <div
      class="group flex items-center gap-1 rounded px-1 py-1 text-sm hover:bg-dark-700 cursor-pointer"
      :style="{ paddingLeft: indent }"
    >
      <!-- Expand/Collapse toggle -->
      <button
        v-if="hasChildren"
        @click.stop="toggle"
        class="flex-shrink-0 rounded p-0.5 text-gray-500 hover:bg-dark-600 hover:text-white"
      >
        <ChevronRightIcon
          :class="[
            'h-3.5 w-3.5 transition-transform',
            isExpanded ? 'rotate-90' : ''
          ]"
        />
      </button>
      <span v-else class="w-4.5"></span>

      <!-- Note content (clickable) -->
      <button
        @click="select"
        class="flex-1 flex items-center gap-2 min-w-0 text-left"
      >
        <!-- Icon -->
        <span v-if="note.icon" class="flex-shrink-0 text-base">{{ note.icon }}</span>
        <template v-else>
          <FolderOpenIcon
            v-if="hasChildren && isExpanded"
            class="h-4 w-4 flex-shrink-0 text-yellow-500"
          />
          <FolderIcon
            v-else-if="hasChildren"
            class="h-4 w-4 flex-shrink-0 text-yellow-500"
          />
          <DocumentTextIcon
            v-else
            class="h-4 w-4 flex-shrink-0 text-gray-500"
          />
        </template>

        <!-- Title -->
        <span class="truncate text-gray-300">{{ note.title }}</span>

        <!-- Children count badge -->
        <span
          v-if="note.children_count > 0 && !isExpanded"
          class="flex-shrink-0 rounded bg-dark-600 px-1.5 py-0.5 text-xs text-gray-500"
        >
          {{ note.children_count }}
        </span>
      </button>

      <!-- Add child button (shows on hover) -->
      <button
        @click.stop="createChild"
        class="flex-shrink-0 rounded p-0.5 text-gray-500 opacity-0 group-hover:opacity-100 hover:bg-dark-600 hover:text-white"
        title="Unternotiz erstellen"
      >
        <PlusIcon class="h-3.5 w-3.5" />
      </button>
    </div>

    <!-- Children (recursive) -->
    <div v-if="hasChildren && isExpanded">
      <NoteTreeItem
        v-for="child in note.children"
        :key="child.id"
        :note="child"
        :level="level + 1"
        @select="emit('select', $event)"
        @create-child="emit('create-child', $event)"
      />
    </div>
  </div>
</template>
