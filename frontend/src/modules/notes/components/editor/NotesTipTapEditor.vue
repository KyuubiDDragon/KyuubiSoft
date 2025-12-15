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
import Callout from '../../extensions/Callout'
import { Toggle, ToggleTitle, ToggleContent } from '../../extensions/Toggle'
import InlineDatabase from '../../extensions/InlineDatabase'
import CollaborationCursor from '../../extensions/CollaborationCursor'
import Embed from '../../extensions/Embed'
import EmbedInput from '../embeds/EmbedInput.vue'
import { useNotesStore } from '../../stores/notesStore'
import { useCollaborationStore } from '../../stores/collaborationStore'
import { useCollaboration } from '../../composables/useCollaboration'

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
  },
  enableCollaboration: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['update:modelValue', 'navigate'])

const router = useRouter()
const notesStore = useNotesStore()
const collaborationStore = useCollaborationStore()

const lowlight = createLowlight(common)

// Collaboration state
const collaborationEnabled = ref(false)

// Embed modal state
const showEmbedInput = ref(false)

// Wiki link suggestion state
const showSuggestions = ref(false)
const suggestions = ref([])
const selectedIndex = ref(0)
const suggestionQuery = ref('')
const suggestionCoords = ref(null)
const suggestionRange = ref(null)

// Slash command state
const showSlashMenu = ref(false)
const slashCommands = ref([])
const slashSelectedIndex = ref(0)
const slashQuery = ref('')
const slashCoords = ref(null)
const slashRange = ref(null)

// Available slash commands
const availableSlashCommands = [
  { title: 'Text', description: 'Normaler Absatztext', icon: 'Aa', keywords: ['paragraph', 'text'] },
  { title: 'Überschrift 1', description: 'Große Überschrift', icon: 'H1', keywords: ['heading', 'h1'] },
  { title: 'Überschrift 2', description: 'Mittlere Überschrift', icon: 'H2', keywords: ['heading', 'h2'] },
  { title: 'Überschrift 3', description: 'Kleine Überschrift', icon: 'H3', keywords: ['heading', 'h3'] },
  { title: 'Aufzählung', description: 'Punktliste erstellen', icon: '•', keywords: ['bullet', 'list'] },
  { title: 'Nummerierung', description: 'Nummerierte Liste', icon: '1.', keywords: ['number', 'ordered'] },
  { title: 'Checkliste', description: 'Aufgabenliste', icon: '☑', keywords: ['todo', 'task', 'checkbox'] },
  { title: 'Zitat', description: 'Zitat hervorheben', icon: '"', keywords: ['quote', 'blockquote'] },
  { title: 'Codeblock', description: 'Code mit Syntax', icon: '</>', keywords: ['code', 'syntax'] },
  { title: 'Trennlinie', description: 'Horizontale Linie', icon: '—', keywords: ['divider', 'line'] },
  { title: 'Info', description: 'Informationshinweis', icon: 'ℹ️', keywords: ['callout', 'info'] },
  { title: 'Warnung', description: 'Warnhinweis', icon: '⚠️', keywords: ['callout', 'warning'] },
  { title: 'Tipp', description: 'Hilfreicher Tipp', icon: '💡', keywords: ['callout', 'tip'] },
  { title: 'Gefahr', description: 'Wichtiger Warnhinweis', icon: '❌', keywords: ['callout', 'danger'] },
  { title: 'Toggle', description: 'Ausklappbarer Bereich', icon: '▶', keywords: ['toggle', 'collapse', 'expand'] },
  { title: 'Tabelle', description: 'Tabelle einfügen', icon: '▦', keywords: ['table', 'grid'] },
  { title: 'Datenbank', description: 'Inline-Datenbank erstellen', icon: '🗃️', keywords: ['database', 'datenbank', 'notion', 'table', 'board'] },
  { title: 'Embed', description: 'YouTube, Twitter, etc. einbetten', icon: '🔗', keywords: ['embed', 'youtube', 'video', 'twitter', 'spotify', 'einbetten'] },
]

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
    Callout,
    Toggle,
    ToggleTitle,
    ToggleContent,
    InlineDatabase,
    CollaborationCursor.configure({
      getCursors: () => {
        if (!collaborationEnabled.value) return []
        return Object.entries(collaborationStore.cursors).map(([userId, data]) => ({
          userId,
          position: data.position,
          user: data.user,
          color: data.user?.color || '#6366F1',
        }))
      },
      getSelections: () => {
        if (!collaborationEnabled.value) return []
        return Object.entries(collaborationStore.selections).map(([userId, data]) => ({
          userId,
          from: data.from,
          to: data.to,
          user: data.user,
          color: data.user?.color || '#6366F1',
        }))
      },
    }),
    Embed,
  ],
  editorProps: {
    noteId: props.noteId,
  },
  onUpdate: () => {
    emit('update:modelValue', editor.value.getHTML())
    checkForWikiLinkTrigger()
    checkForSlashCommand()

    // Send collaboration update (debounced in store)
    if (collaborationEnabled.value) {
      sendCollaborationUpdate()
    }
  },
  onSelectionUpdate: () => {
    checkForWikiLinkTrigger()
    checkForSlashCommand()

    // Send cursor/selection updates
    if (collaborationEnabled.value) {
      sendCursorUpdate()
      sendSelectionUpdate()
    }
  },
})

// Check if user is typing a slash command
function checkForSlashCommand() {
  if (!editor.value) return

  const { $from } = editor.value.state.selection
  const textBefore = $from.parent.textBetween(0, $from.parentOffset)

  // Check for / at start of block or after whitespace
  const match = textBefore.match(/(?:^|\s)\/([^\s]*)$/)

  if (match) {
    const query = match[1] || ''
    slashQuery.value = query

    // Calculate range to delete when executing command
    const fullMatch = match[0]
    const slashStart = $from.pos - query.length - 1 // -1 for the /
    slashRange.value = { from: slashStart, to: $from.pos }

    // Get cursor position for popup
    const coords = editor.value.view.coordsAtPos($from.pos)
    slashCoords.value = coords

    // Filter commands by query
    const filtered = availableSlashCommands.filter(cmd =>
      cmd.title.toLowerCase().includes(query.toLowerCase()) ||
      cmd.keywords?.some(k => k.toLowerCase().includes(query.toLowerCase()))
    )
    slashCommands.value = filtered
    slashSelectedIndex.value = 0
    showSlashMenu.value = true
  } else {
    showSlashMenu.value = false
  }
}

// Execute a slash command
function executeSlashCommand(command) {
  if (!editor.value || !slashRange.value) return

  const { from, to } = slashRange.value

  // Delete the slash command text first
  editor.value.chain().focus().deleteRange({ from, to }).run()

  // Execute the appropriate command
  switch (command.title) {
    case 'Text':
      editor.value.chain().focus().setParagraph().run()
      break
    case 'Überschrift 1':
      editor.value.chain().focus().toggleHeading({ level: 1 }).run()
      break
    case 'Überschrift 2':
      editor.value.chain().focus().toggleHeading({ level: 2 }).run()
      break
    case 'Überschrift 3':
      editor.value.chain().focus().toggleHeading({ level: 3 }).run()
      break
    case 'Aufzählung':
      editor.value.chain().focus().toggleBulletList().run()
      break
    case 'Nummerierung':
      editor.value.chain().focus().toggleOrderedList().run()
      break
    case 'Checkliste':
      editor.value.chain().focus().toggleTaskList().run()
      break
    case 'Zitat':
      editor.value.chain().focus().toggleBlockquote().run()
      break
    case 'Codeblock':
      editor.value.chain().focus().toggleCodeBlock().run()
      break
    case 'Trennlinie':
      editor.value.chain().focus().setHorizontalRule().run()
      break
    case 'Info':
      editor.value.chain().focus().insertContent({
        type: 'callout',
        attrs: { type: 'info' },
        content: [{ type: 'paragraph' }]
      }).run()
      break
    case 'Warnung':
      editor.value.chain().focus().insertContent({
        type: 'callout',
        attrs: { type: 'warning' },
        content: [{ type: 'paragraph' }]
      }).run()
      break
    case 'Tipp':
      editor.value.chain().focus().insertContent({
        type: 'callout',
        attrs: { type: 'tip' },
        content: [{ type: 'paragraph' }]
      }).run()
      break
    case 'Gefahr':
      editor.value.chain().focus().insertContent({
        type: 'callout',
        attrs: { type: 'danger' },
        content: [{ type: 'paragraph' }]
      }).run()
      break
    case 'Toggle':
      editor.value.chain().focus().insertContent({
        type: 'toggle',
        content: [
          {
            type: 'toggleTitle',
            content: [{ type: 'text', text: 'Klicken zum Öffnen' }]
          },
          {
            type: 'toggleContent',
            content: [{ type: 'paragraph' }]
          }
        ]
      }).run()
      break
    case 'Tabelle':
      editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
      break
    case 'Datenbank':
      editor.value.chain().focus().insertContent({
        type: 'inlineDatabase',
        attrs: {
          noteId: props.noteId,
          name: 'Neue Datenbank',
          view: 'table'
        }
      }).run()
      break
    case 'Embed':
      showEmbedInput.value = true
      break
  }

  // Close the menu
  showSlashMenu.value = false
  slashCommands.value = []
}

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
  // Handle wiki link suggestions
  if (showSuggestions.value) {
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
  }

  // Handle slash command menu
  if (showSlashMenu.value) {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      slashSelectedIndex.value = Math.min(slashSelectedIndex.value + 1, slashCommands.value.length - 1)
      return true
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault()
      slashSelectedIndex.value = Math.max(slashSelectedIndex.value - 1, 0)
      return true
    }

    if (event.key === 'Enter' || event.key === 'Tab') {
      if (slashCommands.value.length > 0) {
        event.preventDefault()
        executeSlashCommand(slashCommands.value[slashSelectedIndex.value])
        return true
      }
    }

    if (event.key === 'Escape') {
      event.preventDefault()
      showSlashMenu.value = false
      return true
    }
  }

  return false
}

// Add keyboard listener
onMounted(async () => {
  document.addEventListener('keydown', handleSuggestionKeydown)

  // Initialize collaboration if enabled
  if (props.enableCollaboration && props.noteId) {
    await initCollaboration()
  }
})

// Initialize collaboration
async function initCollaboration() {
  try {
    await collaborationStore.connect()
    await collaborationStore.joinRoom(props.noteId)
    collaborationEnabled.value = true

    // Register update handler
    collaborationStore.onMessage('update', handleRemoteUpdate)

    console.log('Collaboration enabled for note:', props.noteId)
  } catch (error) {
    console.error('Failed to initialize collaboration:', error)
    collaborationEnabled.value = false
  }
}

// Handle remote update from collaboration
function handleRemoteUpdate(data) {
  if (!editor.value) return

  // Skip if this is our own update
  // In production, use proper CRDT conflict resolution
  try {
    if (data.update && typeof data.update === 'string') {
      const content = JSON.parse(data.update)

      // Save current selection
      const { from, to } = editor.value.state.selection

      // Update content without triggering our own update handler
      editor.value.commands.setContent(content, false)

      // Restore selection
      const docSize = editor.value.state.doc.content.size
      editor.value.commands.setTextSelection({
        from: Math.min(from, docSize),
        to: Math.min(to, docSize)
      })
    }
  } catch (error) {
    console.error('Failed to apply remote update:', error)
  }
}

// Send local changes to collaboration server
function sendCollaborationUpdate() {
  if (!collaborationEnabled.value || !editor.value) return

  const content = editor.value.getJSON()
  collaborationStore.sendUpdate(JSON.stringify(content))
}

// Send cursor position
function sendCursorUpdate() {
  if (!collaborationEnabled.value || !editor.value) return

  const { anchor } = editor.value.state.selection
  collaborationStore.sendCursor({ position: anchor })
}

// Send selection update
function sendSelectionUpdate() {
  if (!collaborationEnabled.value || !editor.value) return

  const { from, to } = editor.value.state.selection
  if (from !== to) {
    collaborationStore.sendSelection({ from, to })
  }
}

// Handle embed URL submission
function handleEmbedSubmit(url) {
  if (!editor.value || !url) return

  editor.value.chain().focus().setEmbed({ url }).run()
  showEmbedInput.value = false
}

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleSuggestionKeydown)

  // Leave collaboration room
  if (collaborationEnabled.value) {
    collaborationStore.leaveRoom()
    collaborationEnabled.value = false
  }
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

// Computed position for slash command menu
const slashMenuStyle = computed(() => {
  if (!slashCoords.value) return {}

  return {
    position: 'fixed',
    top: `${slashCoords.value.bottom + 5}px`,
    left: `${slashCoords.value.left}px`,
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

    <!-- Slash Command Menu -->
    <Teleport to="body">
      <div
        v-if="showSlashMenu && slashCommands.length > 0"
        :style="slashMenuStyle"
        class="slash-command-menu bg-dark-700 border border-dark-600 rounded-lg shadow-xl overflow-hidden min-w-[250px] max-w-[320px]"
      >
        <div class="px-3 py-2 text-xs text-gray-500 border-b border-dark-600">
          Befehle
        </div>
        <div class="max-h-[300px] overflow-y-auto">
          <button
            v-for="(command, index) in slashCommands"
            :key="command.title"
            @click="executeSlashCommand(command)"
            @mouseenter="slashSelectedIndex = index"
            :class="[
              'w-full flex items-center gap-3 px-3 py-2 text-left transition-colors',
              index === slashSelectedIndex
                ? 'bg-primary-600/20 text-white'
                : 'text-gray-300 hover:bg-dark-600'
            ]"
          >
            <span class="w-8 h-8 flex items-center justify-center rounded bg-dark-600 text-sm flex-shrink-0">
              {{ command.icon }}
            </span>
            <div class="flex-1 min-w-0">
              <div class="font-medium truncate">{{ command.title }}</div>
              <div class="text-xs text-gray-500 truncate">{{ command.description }}</div>
            </div>
          </button>
        </div>
        <div class="px-3 py-1.5 text-xs text-gray-500 border-t border-dark-600 flex items-center gap-2">
          <span class="bg-dark-600 px-1 rounded">↑↓</span> navigieren
          <span class="bg-dark-600 px-1 rounded">↵</span> auswählen
          <span class="bg-dark-600 px-1 rounded">esc</span> schließen
        </div>
      </div>
    </Teleport>

    <!-- Embed Input Modal -->
    <EmbedInput
      :show="showEmbedInput"
      @close="showEmbedInput = false"
      @submit="handleEmbedSubmit"
    />
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

/* Callout Styles (already styled inline by extension, but adding additional polish) */
.notes-tiptap-editor .tiptap-content .ProseMirror div[data-callout] {
  @apply my-4;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-callout] .callout-content p:last-child {
  @apply mb-0;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-callout] .callout-header {
  @apply text-white;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-callout].callout-info .callout-header {
  @apply text-blue-400;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-callout].callout-warning .callout-header {
  @apply text-yellow-400;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-callout].callout-tip .callout-header {
  @apply text-green-400;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-callout].callout-danger .callout-header {
  @apply text-red-400;
}

/* Toggle (Collapsible) Styles */
.notes-tiptap-editor .tiptap-content .ProseMirror div[data-toggle] {
  @apply my-3 border border-dark-600 rounded-lg overflow-hidden bg-dark-800/30;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-toggle-title] {
  @apply px-4 py-2 bg-dark-700 cursor-pointer;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-toggle-title] .toggle-icon {
  @apply text-gray-500;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-toggle][data-open="true"] div[data-toggle-title] .toggle-icon {
  @apply transform rotate-90;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-toggle-content] {
  @apply px-4 py-3;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-toggle][data-open="false"] div[data-toggle-content] {
  @apply hidden;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-toggle-content] p:last-child {
  @apply mb-0;
}

/* Inline Database Styles */
.notes-tiptap-editor .tiptap-content .ProseMirror div[data-inline-database] {
  @apply my-4;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-inline-database] .inline-database-wrapper {
  @apply select-none;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-inline-database].ProseMirror-selectednode {
  @apply ring-2 ring-primary-500;
}

/* Collaboration Cursor Styles */
.notes-tiptap-editor .tiptap-content .ProseMirror .collaboration-cursor {
  position: relative;
  pointer-events: none;
  animation: cursor-blink 1s infinite;
}

.notes-tiptap-editor .tiptap-content .ProseMirror .collaboration-cursor-flag {
  animation: flag-fade-in 0.2s ease-out;
}

.notes-tiptap-editor .tiptap-content .ProseMirror .collaboration-selection {
  border-radius: 2px;
}

@keyframes cursor-blink {
  0%, 50% { opacity: 1; }
  51%, 100% { opacity: 0.7; }
}

@keyframes flag-fade-in {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 0.9; transform: translateY(-2px); }
}

/* Embed Styles */
.notes-tiptap-editor .tiptap-content .ProseMirror div[data-embed] {
  @apply my-4;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-embed].ProseMirror-selectednode {
  @apply ring-2 ring-primary-500;
}

.notes-tiptap-editor .tiptap-content .ProseMirror div[data-embed] .embed-wrapper {
  @apply select-none;
}
</style>
