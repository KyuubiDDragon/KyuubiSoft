<script setup>
import { ref, watch, onMounted, onBeforeUnmount, shallowRef } from 'vue'
import loader from '@monaco-editor/loader'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  language: {
    type: String,
    default: 'javascript'
  },
  theme: {
    type: String,
    default: 'vs-dark'
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
      value: props.modelValue,
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
  if (editor.value) {
    editor.value.dispose()
  }
})

// Watch for external value changes
watch(() => props.modelValue, (newValue) => {
  if (editor.value && newValue !== editor.value.getValue()) {
    editor.value.setValue(newValue)
  }
})

// Watch for language changes from dropdown
watch(selectedLanguage, (newLang) => {
  if (editor.value && monaco.value) {
    const model = editor.value.getModel()
    monaco.value.editor.setModelLanguage(model, newLang)
  }
})

// Watch for language changes from props
watch(() => props.language, (newLang) => {
  if (newLang && newLang !== selectedLanguage.value) {
    selectedLanguage.value = newLang
  }
}, { immediate: false })

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
  setValue: (value) => editor.value?.setValue(value),
})
</script>

<template>
  <div class="monaco-editor-wrapper">
    <!-- Toolbar -->
    <div class="flex items-center justify-between p-2 bg-white/[0.03] border border-white/[0.06] rounded-t-xl">
      <div class="flex items-center gap-2">
        <label class="text-xs text-gray-400">Sprache:</label>
        <select
          v-model="selectedLanguage"
          class="select text-sm py-1"
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
          class="px-2 py-1 text-xs bg-white/[0.06] hover:bg-white/[0.10] text-gray-300 rounded-lg transition-colors"
          title="Code formatieren (Shift+Alt+F)"
        >
          Formatieren
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex items-center justify-center bg-white/[0.02] border-x border-white/[0.06]" :style="{ height }">
      <div class="text-center">
        <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
        <p class="text-gray-400 text-sm">Editor wird geladen...</p>
      </div>
    </div>

    <!-- Editor Container -->
    <div
      ref="editorContainer"
      class="border-x border-b border-white/[0.06] rounded-b-xl overflow-hidden"
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
</style>
