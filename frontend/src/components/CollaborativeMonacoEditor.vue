<script setup>
import { ref, watch, onMounted, onBeforeUnmount, shallowRef } from 'vue'
import loader from '@monaco-editor/loader'
import { MonacoBinding } from 'y-monaco'

const props = defineProps({
  ydoc: {
    type: Object,
    required: true
  },
  provider: {
    type: Object,
    required: true
  },
  ytext: {
    type: Object,
    required: true
  },
  language: {
    type: String,
    default: 'javascript'
  },
  readOnly: {
    type: Boolean,
    default: false
  },
  height: {
    type: String,
    default: '500px'
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

const editorContainer = ref(null)
const editor = shallowRef(null)
const monaco = shallowRef(null)
const binding = shallowRef(null)
const isLoading = ref(true)

const languages = [
  { value: 'javascript', label: 'JavaScript' },
  { value: 'typescript', label: 'TypeScript' },
  { value: 'html', label: 'HTML' },
  { value: 'css', label: 'CSS' },
  { value: 'json', label: 'JSON' },
  { value: 'python', label: 'Python' },
  { value: 'php', label: 'PHP' },
  { value: 'sql', label: 'SQL' },
  { value: 'markdown', label: 'Markdown' },
  { value: 'yaml', label: 'YAML' },
  { value: 'xml', label: 'XML' },
  { value: 'shell', label: 'Shell/Bash' },
  { value: 'java', label: 'Java' },
  { value: 'csharp', label: 'C#' },
  { value: 'cpp', label: 'C++' },
  { value: 'go', label: 'Go' },
  { value: 'rust', label: 'Rust' },
  { value: 'ruby', label: 'Ruby' },
  { value: 'swift', label: 'Swift' },
  { value: 'kotlin', label: 'Kotlin' },
]

const selectedLanguage = ref(props.language)

onMounted(async () => {
  try {
    // Configure Monaco loader
    loader.config({
      paths: {
        vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs'
      }
    })

    monaco.value = await loader.init()

    // Define custom dark theme
    monaco.value.editor.defineTheme('kyuubi-dark', {
      base: 'vs-dark',
      inherit: true,
      rules: [
        { token: 'comment', foreground: '6A9955' },
        { token: 'keyword', foreground: 'C586C0' },
        { token: 'string', foreground: 'CE9178' },
        { token: 'number', foreground: 'B5CEA8' },
        { token: 'type', foreground: '4EC9B0' },
      ],
      colors: {
        'editor.background': '#1a1a2e',
        'editor.foreground': '#e4e4e7',
        'editorLineNumber.foreground': '#6b7280',
        'editorLineNumber.activeForeground': '#e4e4e7',
        'editor.selectionBackground': '#3b82f640',
        'editor.lineHighlightBackground': '#27272a',
        'editorCursor.foreground': '#3b82f6',
        'editorIndentGuide.background': '#27272a',
        'editorIndentGuide.activeBackground': '#3f3f46',
      }
    })

    // Create editor
    editor.value = monaco.value.editor.create(editorContainer.value, {
      value: '',
      language: props.language,
      theme: 'kyuubi-dark',
      readOnly: props.readOnly,
      automaticLayout: true,
      minimap: { enabled: true },
      scrollBeyondLastLine: false,
      fontSize: 14,
      fontFamily: "'JetBrains Mono', 'Fira Code', Consolas, monospace",
      fontLigatures: true,
      lineNumbers: 'on',
      renderLineHighlight: 'line',
      tabSize: 2,
      insertSpaces: true,
      wordWrap: 'on',
      bracketPairColorization: { enabled: true },
      padding: { top: 16, bottom: 16 },
      smoothScrolling: true,
      cursorBlinking: 'smooth',
      cursorSmoothCaretAnimation: 'on',
    })

    // Create Yjs binding
    binding.value = new MonacoBinding(
      props.ytext,
      editor.value.getModel(),
      new Set([editor.value]),
      props.provider.awareness
    )

    // Listen for changes
    editor.value.onDidChangeModelContent(() => {
      const value = editor.value.getValue()
      emit('update:modelValue', value)
      emit('change', value)
    })

    isLoading.value = false
  } catch (error) {
    console.error('Failed to load Monaco Editor:', error)
    isLoading.value = false
  }
})

onBeforeUnmount(() => {
  if (binding.value) {
    binding.value.destroy()
  }
  if (editor.value) {
    editor.value.dispose()
  }
})

// Watch for language changes
watch(selectedLanguage, (newLang) => {
  if (editor.value && monaco.value) {
    const model = editor.value.getModel()
    monaco.value.editor.setModelLanguage(model, newLang)
  }
})

watch(() => props.readOnly, (readOnly) => {
  if (editor.value) {
    editor.value.updateOptions({ readOnly })
  }
})

// Format code
function formatCode() {
  if (editor.value) {
    editor.value.getAction('editor.action.formatDocument')?.run()
  }
}

// Expose methods
defineExpose({
  formatCode,
  getEditor: () => editor.value,
  getValue: () => editor.value?.getValue() || '',
})
</script>

<template>
  <div class="monaco-editor-wrapper">
    <!-- Toolbar -->
    <div class="flex items-center justify-between p-2 bg-dark-700 border border-dark-600 rounded-t-lg">
      <div class="flex items-center gap-2">
        <label class="text-xs text-gray-400">Sprache:</label>
        <select
          v-model="selectedLanguage"
          class="bg-dark-600 text-white text-sm rounded px-2 py-1 border border-dark-500 focus:border-primary-500 focus:outline-none"
          :disabled="readOnly"
        >
          <option v-for="lang in languages" :key="lang.value" :value="lang.value">
            {{ lang.label }}
          </option>
        </select>
      </div>
      <div class="flex items-center gap-2">
        <button
          v-if="!readOnly"
          @click="formatCode"
          class="px-2 py-1 text-xs bg-dark-600 hover:bg-dark-500 text-gray-300 rounded transition-colors"
          title="Code formatieren (Shift+Alt+F)"
        >
          Formatieren
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex items-center justify-center bg-dark-800 border-x border-dark-600" :style="{ height }">
      <div class="text-center">
        <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
        <p class="text-gray-400 text-sm">Editor wird geladen...</p>
      </div>
    </div>

    <!-- Editor Container -->
    <div
      ref="editorContainer"
      class="border-x border-b border-dark-600 rounded-b-lg overflow-hidden"
      :style="{ height, display: isLoading ? 'none' : 'block' }"
    ></div>
  </div>
</template>

<style>
.monaco-editor-wrapper .monaco-editor {
  padding-top: 0 !important;
}

.monaco-editor-wrapper .monaco-editor .margin {
  background-color: #1a1a2e !important;
}

/* Yjs remote cursor styles */
.yRemoteSelection {
  background-color: rgba(59, 130, 246, 0.3);
}

.yRemoteSelectionHead {
  position: absolute;
  border-left: 2px solid;
  border-color: inherit;
  height: 100%;
  box-sizing: border-box;
}

.yRemoteSelectionHead::after {
  position: absolute;
  content: attr(data-name);
  color: white;
  font-size: 10px;
  font-weight: 600;
  left: -2px;
  top: -1.4em;
  padding: 0.1rem 0.3rem;
  border-radius: 3px 3px 3px 0;
  background-color: inherit;
  white-space: nowrap;
}
</style>
