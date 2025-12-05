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
import Collaboration from '@tiptap/extension-collaboration'
import CollaborationCursor from '@tiptap/extension-collaboration-cursor'
import { common, createLowlight } from 'lowlight'
import { watch, onBeforeUnmount, computed } from 'vue'

const props = defineProps({
  ydoc: {
    type: Object,
    required: true
  },
  provider: {
    type: Object,
    required: true
  },
  userName: {
    type: String,
    default: 'Anonym'
  },
  userColor: {
    type: String,
    default: '#3b82f6'
  },
  placeholder: {
    type: String,
    default: 'Schreibe hier...'
  },
  editable: {
    type: Boolean,
    default: true
  },
  minHeight: {
    type: String,
    default: '500px'
  }
})

const emit = defineEmits(['update:modelValue'])

const lowlight = createLowlight(common)

const editor = useEditor({
  editable: props.editable,
  extensions: [
    StarterKit.configure({
      codeBlock: false,
      history: false, // Disable default history, use collaboration undo
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
    // Yjs collaboration
    Collaboration.configure({
      document: props.ydoc,
    }),
    CollaborationCursor.configure({
      provider: props.provider,
      user: {
        name: props.userName,
        color: props.userColor,
      },
    }),
  ],
  onUpdate: () => {
    emit('update:modelValue', editor.value.getHTML())
  },
})

watch(() => props.editable, (value) => {
  editor.value?.setEditable(value)
})

watch(() => props.userName, (name) => {
  if (editor.value) {
    const cursor = editor.value.extensionManager.extensions.find(
      ext => ext.name === 'collaborationCursor'
    )
    if (cursor) {
      cursor.options.user.name = name
      cursor.options.provider.awareness.setLocalStateField('user', {
        name,
        color: props.userColor,
      })
    }
  }
})

onBeforeUnmount(() => {
  editor.value?.destroy()
})

function setLink() {
  const previousUrl = editor.value.getAttributes('link').href
  const url = window.prompt('URL eingeben:', previousUrl)

  if (url === null) return

  if (url === '') {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }

  editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

function addImage() {
  const url = window.prompt('Bild-URL eingeben:')
  if (url) {
    editor.value.chain().focus().setImage({ src: url }).run()
  }
}

function insertTable() {
  editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
}

// Expose editor for parent components
defineExpose({
  getEditor: () => editor.value,
  getHTML: () => editor.value?.getHTML() || '',
})
</script>

<template>
  <div class="tiptap-editor h-full flex flex-col">
    <!-- Toolbar -->
    <div v-if="editable && editor" class="flex flex-wrap gap-1 p-2 bg-dark-700 border border-dark-600 rounded-t-lg">
      <!-- Text Formatting -->
      <div class="flex gap-1 pr-2 border-r border-dark-600">
        <button
          @click="editor.chain().focus().toggleBold().run()"
          :class="{ 'bg-dark-500': editor.isActive('bold') }"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Fett (Strg+B)"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"/>
          </svg>
        </button>
        <button
          @click="editor.chain().focus().toggleItalic().run()"
          :class="{ 'bg-dark-500': editor.isActive('italic') }"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Kursiv (Strg+I)"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 4h4m-2 0v16m-4 0h8"/>
          </svg>
        </button>
        <button
          @click="editor.chain().focus().toggleUnderline().run()"
          :class="{ 'bg-dark-500': editor.isActive('underline') }"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Unterstrichen (Strg+U)"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 3v7a6 6 0 006 6 6 6 0 006-6V3 M4 21h16"/>
          </svg>
        </button>
        <button
          @click="editor.chain().focus().toggleStrike().run()"
          :class="{ 'bg-dark-500': editor.isActive('strike') }"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Durchgestrichen"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2h2 M12 2v8m0 4v8"/>
          </svg>
        </button>
      </div>

      <!-- Headings -->
      <div class="flex gap-1 pr-2 border-r border-dark-600">
        <button
          @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
          :class="{ 'bg-dark-500': editor.isActive('heading', { level: 1 }) }"
          class="p-2 hover:bg-dark-600 rounded transition-colors text-xs font-bold"
          title="Überschrift 1"
        >
          H1
        </button>
        <button
          @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
          :class="{ 'bg-dark-500': editor.isActive('heading', { level: 2 }) }"
          class="p-2 hover:bg-dark-600 rounded transition-colors text-xs font-bold"
          title="Überschrift 2"
        >
          H2
        </button>
        <button
          @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
          :class="{ 'bg-dark-500': editor.isActive('heading', { level: 3 }) }"
          class="p-2 hover:bg-dark-600 rounded transition-colors text-xs font-bold"
          title="Überschrift 3"
        >
          H3
        </button>
      </div>

      <!-- Lists -->
      <div class="flex gap-1 pr-2 border-r border-dark-600">
        <button
          @click="editor.chain().focus().toggleBulletList().run()"
          :class="{ 'bg-dark-500': editor.isActive('bulletList') }"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Aufzählung"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <button
          @click="editor.chain().focus().toggleOrderedList().run()"
          :class="{ 'bg-dark-500': editor.isActive('orderedList') }"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Nummerierte Liste"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
          </svg>
        </button>
      </div>

      <!-- Alignment -->
      <div class="flex gap-1 pr-2 border-r border-dark-600">
        <button
          @click="editor.chain().focus().setTextAlign('left').run()"
          :class="{ 'bg-dark-500': editor.isActive({ textAlign: 'left' }) }"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Linksbündig"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h14"/>
          </svg>
        </button>
        <button
          @click="editor.chain().focus().setTextAlign('center').run()"
          :class="{ 'bg-dark-500': editor.isActive({ textAlign: 'center' }) }"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Zentriert"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M5 18h14"/>
          </svg>
        </button>
        <button
          @click="editor.chain().focus().setTextAlign('right').run()"
          :class="{ 'bg-dark-500': editor.isActive({ textAlign: 'right' }) }"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Rechtsbündig"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M6 18h14"/>
          </svg>
        </button>
      </div>

      <!-- Special -->
      <div class="flex gap-1 pr-2 border-r border-dark-600">
        <button
          @click="editor.chain().focus().toggleBlockquote().run()"
          :class="{ 'bg-dark-500': editor.isActive('blockquote') }"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Zitat"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
          </svg>
        </button>
        <button
          @click="editor.chain().focus().toggleCode().run()"
          :class="{ 'bg-dark-500': editor.isActive('code') }"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Inline-Code"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
          </svg>
        </button>
        <button
          @click="editor.chain().focus().toggleCodeBlock().run()"
          :class="{ 'bg-dark-500': editor.isActive('codeBlock') }"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Code-Block"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </button>
      </div>

      <!-- Insert -->
      <div class="flex gap-1 pr-2 border-r border-dark-600">
        <button
          @click="setLink"
          :class="{ 'bg-dark-500': editor.isActive('link') }"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Link einfügen"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
          </svg>
        </button>
        <button
          @click="addImage"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Bild einfügen"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </button>
        <button
          @click="insertTable"
          :class="{ 'bg-dark-500': editor.isActive('table') }"
          class="p-2 hover:bg-dark-600 rounded transition-colors"
          title="Tabelle einfügen"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
          </svg>
        </button>
      </div>

      <!-- Table Controls (only visible when in table) -->
      <div v-if="editor.isActive('table')" class="flex gap-1 pr-2 border-r border-dark-600">
        <button
          @click="editor.chain().focus().addColumnBefore().run()"
          class="p-2 hover:bg-dark-600 rounded transition-colors text-xs"
          title="Spalte davor"
        >
          +Spalte
        </button>
        <button
          @click="editor.chain().focus().addRowBefore().run()"
          class="p-2 hover:bg-dark-600 rounded transition-colors text-xs"
          title="Zeile davor"
        >
          +Zeile
        </button>
        <button
          @click="editor.chain().focus().deleteTable().run()"
          class="p-2 hover:bg-red-600/20 text-red-400 rounded transition-colors text-xs"
          title="Tabelle löschen"
        >
          Löschen
        </button>
      </div>

      <!-- Undo/Redo -->
      <div class="flex gap-1">
        <button
          @click="editor.chain().focus().undo().run()"
          :disabled="!editor.can().undo()"
          class="p-2 hover:bg-dark-600 rounded transition-colors disabled:opacity-30"
          title="Rückgängig (Strg+Z)"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
          </svg>
        </button>
        <button
          @click="editor.chain().focus().redo().run()"
          :disabled="!editor.can().redo()"
          class="p-2 hover:bg-dark-600 rounded transition-colors disabled:opacity-30"
          title="Wiederholen (Strg+Y)"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Editor Content -->
    <EditorContent
      :editor="editor"
      class="tiptap-content flex-1 overflow-hidden"
      :class="editable ? 'border-x border-b border-dark-600 rounded-b-lg' : 'border border-dark-600 rounded-lg'"
      :style="{ minHeight: props.minHeight }"
    />
  </div>
</template>

<style>
.tiptap-content {
  display: flex;
  flex-direction: column;
}

.tiptap-content .ProseMirror {
  @apply p-4 h-full overflow-y-auto text-gray-300 focus:outline-none;
  flex: 1;
}

.tiptap-content .ProseMirror p.is-editor-empty:first-child::before {
  @apply text-gray-500 float-left h-0 pointer-events-none;
  content: attr(data-placeholder);
}

.tiptap-content .ProseMirror h1 {
  @apply text-2xl font-bold text-white mt-6 mb-4;
}

.tiptap-content .ProseMirror h2 {
  @apply text-xl font-semibold text-white mt-5 mb-3;
}

.tiptap-content .ProseMirror h3 {
  @apply text-lg font-semibold text-white mt-4 mb-2;
}

.tiptap-content .ProseMirror p {
  @apply mb-3;
}

.tiptap-content .ProseMirror ul {
  @apply list-disc ml-6 mb-3;
}

.tiptap-content .ProseMirror ol {
  @apply list-decimal ml-6 mb-3;
}

.tiptap-content .ProseMirror li {
  @apply mb-1;
}

.tiptap-content .ProseMirror blockquote {
  @apply border-l-4 border-primary-500 pl-4 italic text-gray-400 my-4;
}

.tiptap-content .ProseMirror code {
  @apply bg-dark-700 px-1.5 py-0.5 rounded text-primary-400 text-sm;
}

.tiptap-content .ProseMirror pre {
  @apply bg-dark-700 rounded-lg p-4 my-4 overflow-x-auto;
}

.tiptap-content .ProseMirror pre code {
  @apply bg-transparent p-0;
}

.tiptap-content .ProseMirror img {
  @apply max-w-full rounded-lg my-4;
}

.tiptap-content .ProseMirror hr {
  @apply border-dark-600 my-6;
}

.tiptap-content .ProseMirror table {
  @apply border-collapse w-full my-4;
}

.tiptap-content .ProseMirror th,
.tiptap-content .ProseMirror td {
  @apply border border-dark-600 p-2 text-left;
}

.tiptap-content .ProseMirror th {
  @apply bg-dark-700 font-semibold;
}

.tiptap-content .ProseMirror .selectedCell {
  @apply bg-primary-600/20;
}

/* Collaboration cursor styles */
.tiptap-content .collaboration-cursor__caret {
  border-left: 1px solid;
  border-right: 1px solid;
  margin-left: -1px;
  margin-right: -1px;
  pointer-events: none;
  position: relative;
  word-break: normal;
}

.tiptap-content .collaboration-cursor__label {
  border-radius: 3px 3px 3px 0;
  color: white;
  font-size: 10px;
  font-weight: 600;
  left: -1px;
  line-height: normal;
  padding: 0.1rem 0.3rem;
  position: absolute;
  top: -1.4em;
  user-select: none;
  white-space: nowrap;
}
</style>
