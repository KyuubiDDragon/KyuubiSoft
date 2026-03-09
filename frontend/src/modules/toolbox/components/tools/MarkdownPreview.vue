<script setup>
import { ref, computed } from 'vue'
import { marked } from 'marked'
import { sanitizeHtmlWithLinks } from '@/core/services/sanitize'

const markdown = ref(`# Markdown Preview

## Features

- **Fett** und *kursiv*
- ~~Durchgestrichen~~
- \`Inline Code\`

### Code Block

\`\`\`javascript
function hello() {
  console.log("Hello, World!");
}
\`\`\`

### Liste

1. Erster Punkt
2. Zweiter Punkt
   - Unterpunkt
   - Noch ein Unterpunkt
3. Dritter Punkt

### Tabelle

| Name | Wert |
|------|------|
| A    | 100  |
| B    | 200  |

### Zitat

> Dies ist ein Zitat.
> Es kann mehrere Zeilen haben.

### Link

[Beispiel Link](https://example.com)

### Bild (Beispiel-URL)

![Alt Text](https://via.placeholder.com/150)

---

Horizontale Linie oben.
`)

const viewMode = ref('split') // 'edit', 'preview', 'split'

// Configure marked for proper Markdown rendering
marked.setOptions({
  breaks: true,
  gfm: true,
})

function parseMarkdown(text) {
  if (!text) return ''
  return marked.parse(text)
}

const renderedHtml = computed(() => parseMarkdown(markdown.value))

function copyMarkdown() {
  navigator.clipboard.writeText(markdown.value)
}

function copyHtml() {
  navigator.clipboard.writeText(renderedHtml.value)
}

function clearAll() {
  markdown.value = ''
}

function loadSample() {
  markdown.value = `# Beispiel Dokument

Dies ist ein **Beispiel** für *Markdown*.

## Features

- Überschriften
- Listen
- Code-Blöcke
- Und mehr...

\`\`\`javascript
console.log("Hello!");
\`\`\`

> Ein inspirierendes Zitat.
`
}
</script>

<template>
  <div class="space-y-4">
    <!-- Toolbar -->
    <div class="flex items-center justify-between">
      <div class="flex gap-2">
        <button
          @click="viewMode = 'edit'"
          class="px-3 py-1 text-sm rounded transition-colors"
          :class="viewMode === 'edit' ? 'bg-primary-600 text-white' : 'bg-white/[0.04] text-gray-400'"
        >
          Editor
        </button>
        <button
          @click="viewMode = 'split'"
          class="px-3 py-1 text-sm rounded transition-colors"
          :class="viewMode === 'split' ? 'bg-primary-600 text-white' : 'bg-white/[0.04] text-gray-400'"
        >
          Split
        </button>
        <button
          @click="viewMode = 'preview'"
          class="px-3 py-1 text-sm rounded transition-colors"
          :class="viewMode === 'preview' ? 'bg-primary-600 text-white' : 'bg-white/[0.04] text-gray-400'"
        >
          {{ $t('toolbox.vorschau') }}
        </button>
      </div>
      <div class="flex gap-2">
        <button @click="loadSample" class="text-xs text-gray-400 hover:text-white">{{ $t('toolbox.beispiel') }}</button>
        <button @click="clearAll" class="text-xs text-gray-400 hover:text-white">{{ $t('common.delete') }}</button>
        <button @click="copyMarkdown" class="text-xs text-primary-400 hover:text-primary-300">{{ $t('toolbox.mdKopieren') }}</button>
        <button @click="copyHtml" class="text-xs text-primary-400 hover:text-primary-300">{{ $t('toolbox.htmlKopieren') }}</button>
      </div>
    </div>

    <!-- Editor/Preview -->
    <div
      class="grid gap-4"
      :class="{
        'grid-cols-1': viewMode !== 'split',
        'grid-cols-2': viewMode === 'split',
      }"
    >
      <!-- Editor -->
      <div v-if="viewMode !== 'preview'">
        <textarea
          v-model="markdown"
          class="input w-full h-96 font-mono text-sm resize-none"
          :placeholder="$t('toolbox.markdownHierEingeben')"
        ></textarea>
      </div>

      <!-- Preview -->
      <div v-if="viewMode !== 'edit'" class="bg-white/[0.04] rounded-lg p-4 h-96 overflow-auto">
        <div class="prose prose-invert max-w-none" v-html="sanitizeHtmlWithLinks(renderedHtml)"></div>
      </div>
    </div>

    <!-- Quick Reference -->
    <details class="text-sm">
      <summary class="text-gray-400 cursor-pointer hover:text-white">{{ $t('toolbox.markdownKurzreferenz') }}</summary>
      <div class="mt-2 p-3 bg-white/[0.04] rounded-lg grid grid-cols-2 gap-2 text-xs font-mono">
        <div><span class="text-gray-500"># </span>{{ $t('toolbox.ueberschrift1') }}</div>
        <div><span class="text-gray-500">## </span>{{ $t('toolbox.ueberschrift2') }}</div>
        <div><span class="text-gray-500">**</span>{{ $t('toolbox.fett') }}<span class="text-gray-500">**</span></div>
        <div><span class="text-gray-500">*</span>{{ $t('toolbox.kursiv') }}<span class="text-gray-500">*</span></div>
        <div><span class="text-gray-500">- </span>{{ $t('toolbox.liste') }}</div>
        <div><span class="text-gray-500">1. </span>{{ $t('toolbox.numerierteListe') }}</div>
        <div><span class="text-gray-500">`</span>code<span class="text-gray-500">`</span></div>
        <div><span class="text-gray-500">> </span>{{ $t('toolbox.zitat') }}</div>
        <div><span class="text-gray-500">[</span>{{ $t('toolbox.link') }}<span class="text-gray-500">](url)</span></div>
        <div><span class="text-gray-500">![</span>{{ $t('toolbox.bild') }}<span class="text-gray-500">](url)</span></div>
      </div>
    </details>
  </div>
</template>
