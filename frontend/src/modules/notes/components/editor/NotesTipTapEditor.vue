<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import { Table } from '@tiptap/extension-table'
import { TableRow } from '@tiptap/extension-table-row'
import { TableCell } from '@tiptap/extension-table-cell'
import { TableHeader } from '@tiptap/extension-table-header'
import Placeholder from '@tiptap/extension-placeholder'
import TextAlign from '@tiptap/extension-text-align'
import Underline from '@tiptap/extension-underline'
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight'
import TaskList from '@tiptap/extension-task-list'
import TaskItem from '@tiptap/extension-task-item'
import Highlight from '@tiptap/extension-highlight'
import Subscript from '@tiptap/extension-subscript'
import Superscript from '@tiptap/extension-superscript'
import { common, createLowlight } from 'lowlight'
import { watch, onBeforeUnmount, onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import WikiLink from '../../extensions/WikiLink'
import { useNotesStore } from '../../stores/notesStore'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'Beginne zu schreiben... Nutze [[...]] für Wiki-Links'
  },
  editable: {
    type: Boolean,
    default: true
  },
  minHeight: {
    type: String,
    default: '500px'
  },
  noteId: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue', 'navigate'])

const router = useRouter()
const notesStore = useNotesStore()

const lowlight = createLowlight(common)

// Wiki link suggestion state
const showSuggestions = ref(false)
const suggestions = ref([])
const selectedIndex = ref(0)
const suggestionQuery = ref('')
const suggestionCoords = ref(null)
const suggestionRange = ref(null)

// Handle wiki link navigation
function handleWikiLinkNavigation(href) {
  emit('navigate', href)
}

// Get suggestions for wiki links
async function getSuggestions(query) {
  try {
    const results = await notesStore.getSuggestions(query)
    return results
  } catch (error) {
    console.error('Error getting suggestions:', error)
    return []
  }
}

// Insert wiki link from suggestion
function insertWikiLink(suggestion) {
  if (!editor.value || !suggestionRange.value) return

  const { from, to } = suggestionRange.value
  const title = suggestion.title || suggestion.name

  // Delete the typed text including [[
  editor.value
    .chain()
    .focus()
    .deleteRange({ from, to: editor.value.state.selection.from })
    .insertContent({
      type: 'text',
      marks: [{
        type: 'wikiLink',
        attrs: {
          href: suggestion.slug || suggestion.id,
          title: title,
          exists: true
        }
      }],
      text: title
    })
    .insertContent(']] ')
    .run()

  // Close suggestions
  showSuggestions.value = false
  suggestions.value = []
}

const editor = useEditor({
  content: props.modelValue,
  editable: props.editable,
  extensions: [
    StarterKit.configure({
      codeBlock: false,
    }),
    Underline,
    Link.configure({
      openOnClick: false,
      HTMLAttributes: {
        class: 'text-primary-400 hover:underline cursor-pointer',
      },
    }),
    Image.configure({
      HTMLAttributes: {
        class: 'max-w-full rounded-lg',
      },
    }),
    Table.configure({
      resizable: true,
      HTMLAttributes: {
        class: 'border-collapse table-auto w-full',
      },
    }),
    TableRow,
    TableCell.configure({
      HTMLAttributes: {
        class: 'border border-dark-600 p-2',
      },
    }),
    TableHeader.configure({
      HTMLAttributes: {
        class: 'border border-dark-600 p-2 bg-dark-700 font-semibold',
      },
    }),
    Placeholder.configure({
      placeholder: props.placeholder,
    }),
    TextAlign.configure({
      types: ['heading', 'paragraph'],
    }),
    CodeBlockLowlight.configure({
      lowlight,
      HTMLAttributes: {
        class: 'bg-dark-700 rounded-lg p-4 my-2 overflow-x-auto',
      },
    }),
    TaskList.configure({
      HTMLAttributes: {
        class: 'task-list',
      },
    }),
    TaskItem.configure({
      nested: true,
      HTMLAttributes: {
        class: 'task-item',
      },
    }),
    Highlight.configure({
      multicolor: true,
    }),
    Subscript,
    Superscript,
    WikiLink.configure({
      onNavigate: handleWikiLinkNavigation,
    }),
  ],
  onUpdate: () => {
    emit('update:modelValue', editor.value.getHTML())
    checkForWikiLinkTrigger()
  },
  onSelectionUpdate: () => {
    checkForWikiLinkTrigger()
  },
})

// Check if user is typing a wiki link
function checkForWikiLinkTrigger() {
  if (!editor.value) return

  const { $from } = editor.value.state.selection
  const textBefore = $from.parent.textBetween(
    Math.max(0, $from.parentOffset - 50),
    $from.parentOffset
  )

  // Check for [[ pattern
  const match = textBefore.match(/\[\[([^\]]*)?$/)

  if (match) {
    const query = match[1] || ''
    suggestionQuery.value = query
    suggestionRange.value = {
      from: $from.pos - query.length - 2,
      to: $from.pos
    }

    // Get cursor position for popup
    const coords = editor.value.view.coordsAtPos($from.pos)
    suggestionCoords.value = coords

    // Fetch suggestions
    getSuggestions(query).then(results => {
      suggestions.value = results.slice(0, 8) // Limit to 8 suggestions
      selectedIndex.value = 0
      showSuggestions.value = true
    })
  } else {
    showSuggestions.value = false
  }
}

// Handle keyboard navigation in suggestions
function handleSuggestionKeydown(event) {
  if (!showSuggestions.value) return false

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    selectedIndex.value = Math.min(selectedIndex.value + 1, suggestions.value.length - 1)
    return true
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
    return true
  }

  if (event.key === 'Enter' || event.key === 'Tab') {
    if (suggestions.value.length > 0) {
      event.preventDefault()
      insertWikiLink(suggestions.value[selectedIndex.value])
      return true
    }
  }

  if (event.key === 'Escape') {
    event.preventDefault()
    showSuggestions.value = false
    return true
  }

  return false
}

// Add keyboard listener
onMounted(() => {
  document.addEventListener('keydown', handleSuggestionKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleSuggestionKeydown)
  editor.value?.destroy()
})

watch(() => props.modelValue, (value) => {
  const isSame = editor.value?.getHTML() === value
  if (!isSame && editor.value) {
    editor.value.commands.setContent(value, false)
  }
})

watch(() => props.editable, (value) => {
  editor.value?.setEditable(value)
})

// Computed position for suggestions popup
const suggestionStyle = computed(() => {
  if (!suggestionCoords.value) return {}

  return {
    position: 'fixed',
    top: `${suggestionCoords.value.bottom + 5}px`,
    left: `${suggestionCoords.value.left}px`,
    zIndex: 100,
  }
})
</script>

<template>
  <div class="notes-tiptap-editor h-full flex flex-col relative">
    <!-- Editor Content -->
    <EditorContent
      :editor="editor"
      class="tiptap-content flex-1 overflow-hidden"
      :style="{ minHeight: props.minHeight }"
    />

    <!-- Wiki Link Suggestions Popup -->
    <Teleport to="body">
      <div
        v-if="showSuggestions && suggestions.length > 0"
        :style="suggestionStyle"
        class="wiki-link-suggestions bg-dark-700 border border-dark-600 rounded-lg shadow-xl overflow-hidden min-w-[200px] max-w-[300px]"
      >
        <div class="px-3 py-2 text-xs text-gray-500 border-b border-dark-600">
          Notizen verlinken
        </div>
        <div class="max-h-[200px] overflow-y-auto">
          <button
            v-for="(suggestion, index) in suggestions"
            :key="suggestion.id"
            @click="insertWikiLink(suggestion)"
            @mouseenter="selectedIndex = index"
            :class="[
              'w-full flex items-center gap-2 px-3 py-2 text-sm text-left transition-colors',
              index === selectedIndex
                ? 'bg-primary-600/20 text-white'
                : 'text-gray-300 hover:bg-dark-600'
            ]"
          >
            <span v-if="suggestion.icon" class="text-base">{{ suggestion.icon }}</span>
            <span v-else class="w-4 h-4 rounded bg-dark-500 flex-shrink-0"></span>
            <span class="truncate">{{ suggestion.title }}</span>
          </button>
        </div>
        <div class="px-3 py-1.5 text-xs text-gray-500 border-t border-dark-600 flex items-center gap-2">
          <span class="bg-dark-600 px-1 rounded">↑↓</span> navigieren
          <span class="bg-dark-600 px-1 rounded">↵</span> auswählen
          <span class="bg-dark-600 px-1 rounded">esc</span> schließen
        </div>
      </div>
    </Teleport>

    <!-- Create new note suggestion when no results -->
    <Teleport to="body">
      <div
        v-if="showSuggestions && suggestions.length === 0 && suggestionQuery"
        :style="suggestionStyle"
        class="wiki-link-suggestions bg-dark-700 border border-dark-600 rounded-lg shadow-xl overflow-hidden min-w-[200px] max-w-[300px]"
      >
        <div class="px-3 py-3 text-sm text-gray-400">
          <p class="mb-2">Keine Notiz gefunden für "{{ suggestionQuery }}"</p>
          <p class="text-xs text-gray-500">
            Drücke <span class="bg-dark-600 px-1 rounded">↵</span> um eine neue Notiz zu erstellen
          </p>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style>
.notes-tiptap-editor .tiptap-content {
  display: flex;
  flex-direction: column;
}

.notes-tiptap-editor .tiptap-content .ProseMirror {
  @apply p-4 h-full overflow-y-auto text-gray-300 focus:outline-none;
  flex: 1;
}

.notes-tiptap-editor .tiptap-content .ProseMirror p.is-editor-empty:first-child::before {
  @apply text-gray-500 float-left h-0 pointer-events-none;
  content: attr(data-placeholder);
}

.notes-tiptap-editor .tiptap-content .ProseMirror h1 {
  @apply text-2xl font-bold text-white mt-6 mb-4;
}

.notes-tiptap-editor .tiptap-content .ProseMirror h2 {
  @apply text-xl font-semibold text-white mt-5 mb-3;
}

.notes-tiptap-editor .tiptap-content .ProseMirror h3 {
  @apply text-lg font-semibold text-white mt-4 mb-2;
}

.notes-tiptap-editor .tiptap-content .ProseMirror p {
  @apply mb-3;
}

.notes-tiptap-editor .tiptap-content .ProseMirror ul {
  @apply list-disc ml-6 mb-3;
}

.notes-tiptap-editor .tiptap-content .ProseMirror ol {
  @apply list-decimal ml-6 mb-3;
}

.notes-tiptap-editor .tiptap-content .ProseMirror li {
  @apply mb-1;
}

.notes-tiptap-editor .tiptap-content .ProseMirror blockquote {
  @apply border-l-4 border-primary-500 pl-4 italic text-gray-400 my-4;
}

.notes-tiptap-editor .tiptap-content .ProseMirror code {
  @apply bg-dark-700 px-1.5 py-0.5 rounded text-primary-400 text-sm;
}

.notes-tiptap-editor .tiptap-content .ProseMirror pre {
  @apply bg-dark-700 rounded-lg p-4 my-4 overflow-x-auto;
}

.notes-tiptap-editor .tiptap-content .ProseMirror pre code {
  @apply bg-transparent p-0;
}

.notes-tiptap-editor .tiptap-content .ProseMirror img {
  @apply max-w-full rounded-lg my-4;
}

.notes-tiptap-editor .tiptap-content .ProseMirror hr {
  @apply border-dark-600 my-6;
}

.notes-tiptap-editor .tiptap-content .ProseMirror table {
  @apply border-collapse w-full my-4;
}

.notes-tiptap-editor .tiptap-content .ProseMirror th,
.notes-tiptap-editor .tiptap-content .ProseMirror td {
  @apply border border-dark-600 p-2 text-left;
}

.notes-tiptap-editor .tiptap-content .ProseMirror th {
  @apply bg-dark-700 font-semibold;
}

/* Wiki Link Styles */
.notes-tiptap-editor .tiptap-content .ProseMirror span[data-wiki-link] {
  @apply text-primary-400 underline decoration-dotted cursor-pointer;
}

.notes-tiptap-editor .tiptap-content .ProseMirror span[data-wiki-link]:hover {
  @apply text-primary-300;
}

.notes-tiptap-editor .tiptap-content .ProseMirror span[data-wiki-link].wiki-link-broken {
  @apply text-red-400 decoration-red-400;
}

/* Task List (Checkboxes) */
.notes-tiptap-editor .tiptap-content .ProseMirror ul[data-type="taskList"] {
  @apply list-none ml-0 pl-0;
}

.notes-tiptap-editor .tiptap-content .ProseMirror ul[data-type="taskList"] li {
  @apply flex items-start gap-2 mb-2;
}

.notes-tiptap-editor .tiptap-content .ProseMirror ul[data-type="taskList"] li > label {
  @apply flex items-center justify-center w-5 h-5 mt-0.5 flex-shrink-0;
}

.notes-tiptap-editor .tiptap-content .ProseMirror ul[data-type="taskList"] li > label input[type="checkbox"] {
  @apply w-4 h-4 rounded border-2 border-dark-500 bg-dark-700 text-primary-500 focus:ring-primary-500 focus:ring-offset-0 cursor-pointer;
}

.notes-tiptap-editor .tiptap-content .ProseMirror ul[data-type="taskList"] li > label input[type="checkbox"]:checked {
  @apply bg-primary-600 border-primary-600;
}

.notes-tiptap-editor .tiptap-content .ProseMirror ul[data-type="taskList"] li > div {
  @apply flex-1;
}

.notes-tiptap-editor .tiptap-content .ProseMirror ul[data-type="taskList"] li[data-checked="true"] > div {
  @apply line-through text-gray-500;
}

/* Highlight */
.notes-tiptap-editor .tiptap-content .ProseMirror mark {
  @apply bg-yellow-500/40 px-0.5 rounded;
}
</style>
