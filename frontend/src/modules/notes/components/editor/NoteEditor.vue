<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import TipTapEditor from '@/components/TipTapEditor.vue'

const props = defineProps({
  content: {
    type: String,
    default: ''
  },
  noteId: {
    type: String,
    default: ''
  },
  editable: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['update:content'])

const localContent = ref(props.content)

// Watch for external content changes (e.g., when loading a new note)
watch(() => props.content, (newContent) => {
  if (newContent !== localContent.value) {
    localContent.value = newContent
  }
})

// Watch for note ID changes to reset content
watch(() => props.noteId, () => {
  localContent.value = props.content
})

function handleUpdate(newContent) {
  localContent.value = newContent
  emit('update:content', newContent)
}
</script>

<template>
  <div class="note-editor h-full">
    <TipTapEditor
      :model-value="localContent"
      :editable="editable"
      placeholder="Beginne zu schreiben..."
      min-height="100%"
      @update:model-value="handleUpdate"
    />
  </div>
</template>

<style scoped>
.note-editor {
  @apply px-4 py-2;
}

.note-editor :deep(.ProseMirror) {
  @apply min-h-full outline-none;
}

.note-editor :deep(.ProseMirror p.is-editor-empty:first-child::before) {
  @apply text-gray-500;
}

/* Wiki link styles */
.note-editor :deep(.wiki-link) {
  @apply text-primary-400 underline decoration-dotted cursor-pointer hover:text-primary-300;
}

.note-editor :deep(.wiki-link-broken) {
  @apply text-red-400;
}

/* Callout styles */
.note-editor :deep(.callout) {
  @apply my-4 rounded-lg border-l-4 p-4;
}

.note-editor :deep(.callout-info) {
  @apply border-blue-500 bg-blue-500/10;
}

.note-editor :deep(.callout-warning) {
  @apply border-yellow-500 bg-yellow-500/10;
}

.note-editor :deep(.callout-tip) {
  @apply border-green-500 bg-green-500/10;
}

.note-editor :deep(.callout-danger) {
  @apply border-red-500 bg-red-500/10;
}

/* Task list styles */
.note-editor :deep(ul[data-type="taskList"]) {
  @apply list-none pl-0;
}

.note-editor :deep(ul[data-type="taskList"] li) {
  @apply flex items-start gap-2;
}

.note-editor :deep(ul[data-type="taskList"] li > label) {
  @apply mt-1;
}

.note-editor :deep(ul[data-type="taskList"] li > div) {
  @apply flex-1;
}

/* Toggle block styles */
.note-editor :deep(.toggle-block) {
  @apply my-2;
}

.note-editor :deep(.toggle-block-header) {
  @apply flex items-center gap-2 cursor-pointer;
}

.note-editor :deep(.toggle-block-content) {
  @apply ml-6 mt-1;
}
</style>
