<script setup>
import { ref, computed, inject } from 'vue'
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
  },
  selectedNoteId: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['select', 'create-child', 'move', 'reorder'])

// Inject expanded state from parent
const expandedNotes = inject('expandedNotes', ref(new Set()))

const isExpanded = computed({
  get: () => expandedNotes.value.has(props.note.id),
  set: (val) => {
    const newSet = new Set(expandedNotes.value)
    if (val) {
      newSet.add(props.note.id)
    } else {
      newSet.delete(props.note.id)
    }
    expandedNotes.value = newSet
  }
})

const hasChildren = computed(() =>
  props.note.children && props.note.children.length > 0
)

const isSelected = computed(() => props.selectedNoteId === props.note.id)

const indent = computed(() => `${props.level * 12}px`)

// Drag & Drop state
const isDragging = ref(false)
const isDragOver = ref(false)
const dropPosition = ref(null) // 'before', 'inside', 'after'

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

// Drag handlers
function handleDragStart(e) {
  isDragging.value = true
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('application/json', JSON.stringify({
    id: props.note.id,
    parentId: props.note.parent_id,
    title: props.note.title
  }))
  // Add a class to the dragged element
  e.target.classList.add('opacity-50')
}

function handleDragEnd(e) {
  isDragging.value = false
  isDragOver.value = false
  dropPosition.value = null
  e.target.classList.remove('opacity-50')
}

function handleDragOver(e) {
  e.preventDefault()
  e.dataTransfer.dropEffect = 'move'

  // Determine drop position based on mouse position
  const rect = e.currentTarget.getBoundingClientRect()
  const y = e.clientY - rect.top
  const height = rect.height

  if (y < height * 0.25) {
    dropPosition.value = 'before'
  } else if (y > height * 0.75) {
    dropPosition.value = 'after'
  } else {
    dropPosition.value = 'inside'
  }

  isDragOver.value = true
}

function handleDragLeave(e) {
  // Only reset if we're leaving the element entirely
  if (!e.currentTarget.contains(e.relatedTarget)) {
    isDragOver.value = false
    dropPosition.value = null
  }
}

function handleDrop(e) {
  e.preventDefault()
  isDragOver.value = false

  try {
    const data = JSON.parse(e.dataTransfer.getData('application/json'))

    // Don't drop on self
    if (data.id === props.note.id) {
      dropPosition.value = null
      return
    }

    if (dropPosition.value === 'inside') {
      // Move note inside this note (make it a child)
      emit('move', {
        noteId: data.id,
        newParentId: props.note.id
      })
      // Expand to show the dropped note
      isExpanded.value = true
    } else {
      // Reorder (before or after)
      emit('reorder', {
        noteId: data.id,
        targetId: props.note.id,
        position: dropPosition.value
      })
    }
  } catch (err) {
    console.error('Drop error:', err)
  }

  dropPosition.value = null
}

// Drop indicator classes
const dropIndicatorClass = computed(() => {
  if (!isDragOver.value || !dropPosition.value) return ''

  switch (dropPosition.value) {
    case 'before':
      return 'before:absolute before:left-0 before:right-0 before:top-0 before:h-0.5 before:bg-primary-500'
    case 'after':
      return 'after:absolute after:left-0 after:right-0 after:bottom-0 after:h-0.5 after:bg-primary-500'
    case 'inside':
      return 'ring-2 ring-primary-500 ring-inset'
    default:
      return ''
  }
})
</script>

<template>
  <div>
    <div
      class="group relative flex items-center gap-1 rounded px-1 py-1 text-sm cursor-pointer transition-all"
      :class="[
        isSelected
          ? 'bg-primary-600/20 text-white'
          : 'hover:bg-dark-700 text-gray-300',
        isDragging ? 'opacity-50' : '',
        dropIndicatorClass
      ]"
      :style="{ paddingLeft: indent }"
      draggable="true"
      @dragstart="handleDragStart"
      @dragend="handleDragEnd"
      @dragover="handleDragOver"
      @dragleave="handleDragLeave"
      @drop="handleDrop"
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
        <span class="truncate">{{ note.title }}</span>

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
        :selected-note-id="selectedNoteId"
        @select="emit('select', $event)"
        @create-child="emit('create-child', $event)"
        @move="emit('move', $event)"
        @reorder="emit('reorder', $event)"
      />
    </div>
  </div>
</template>
