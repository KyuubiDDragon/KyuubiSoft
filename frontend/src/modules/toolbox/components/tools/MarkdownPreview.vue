<script setup>
import { ref, computed } from 'vue'

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

// Simple markdown parser (basic implementation)
function parseMarkdown(text) {
  let html = text

  // Escape HTML
  html = html.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

  // Code blocks (before other processing)
  html = html.replace(/```(\w*)\n([\s\S]*?)```/g, (match, lang, code) => {
    return `<pre class="bg-white/[0.02] p-3 rounded-lg overflow-x-auto my-2"><code class="text-sm text-gray-300">${code.trim()}</code></pre>`
  })

  // Inline code
  html = html.replace(/`([^`]+)`/g, '<code class="bg-white/[0.04] px-1 rounded text-primary-400">$1</code>')

  // Headers
  html = html.replace(/^#### (.+)$/gm, '<h4 class="text-md font-semibold text-white mt-4 mb-2">$1</h4>')
  html = html.replace(/^### (.+)$/gm, '<h3 class="text-lg font-semibold text-white mt-4 mb-2">$1</h3>')
  html = html.replace(/^## (.+)$/gm, '<h2 class="text-xl font-bold text-white mt-6 mb-3">$1</h2>')
  html = html.replace(/^# (.+)$/gm, '<h1 class="text-2xl font-bold text-white mt-6 mb-4">$1</h1>')

  // Bold and Italic
  html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>')
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong class="text-white">$1</strong>')
  html = html.replace(/\*(.+?)\*/g, '<em class="text-gray-300">$1</em>')
  html = html.replace(/~~(.+?)~~/g, '<del class="text-gray-500">$1</del>')

  // Links
  html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" class="text-primary-400 hover:text-primary-300 underline" target="_blank" rel="noopener">$1</a>')

  // Images
  html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" class="max-w-full rounded my-2" />')

  // Blockquotes
  html = html.replace(/^&gt; (.+)$/gm, '<blockquote class="border-l-4 border-primary-500 pl-4 py-1 my-2 text-gray-400 italic">$1</blockquote>')

  // Horizontal rule
  html = html.replace(/^---$/gm, '<hr class="border-white/[0.06] my-4" />')

  // Tables (basic support)
  html = html.replace(/^\|(.+)\|$/gm, (match, content) => {
    const cells = content.split('|').map(c => c.trim())
    if (cells.every(c => /^-+$/.test(c))) {
      return '' // Skip separator row
    }
    const cellHtml = cells.map(c => `<td class="border border-white/[0.06] px-3 py-2">${c}</td>`).join('')
    return `<tr>${cellHtml}</tr>`
  })
  html = html.replace(/(<tr>[\s\S]*?<\/tr>[\s]*)+/g, (match) => {
    return `<table class="w-full border-collapse my-4">${match}</table>`
  })

  // Unordered lists
  html = html.replace(/^(\s*)- (.+)$/gm, (match, indent, content) => {
    const level = Math.floor(indent.length / 2)
    const padding = level * 16
    return `<li class="text-gray-300" style="margin-left: ${padding}px">• ${content}</li>`
  })

  // Ordered lists
  html = html.replace(/^\d+\. (.+)$/gm, '<li class="text-gray-300 list-decimal ml-6">$1</li>')

  // Wrap consecutive li elements in ul/ol
  html = html.replace(/((<li[^>]*>.*<\/li>\s*)+)/g, '<ul class="my-2">$1</ul>')

  // Paragraphs (for remaining text)
  html = html.split('\n\n').map(para => {
    if (para.trim() && !para.startsWith('<')) {
      return `<p class="text-gray-300 my-2">${para}</p>`
    }
    return para
  }).join('\n')

  // Line breaks
  html = html.replace(/\n/g, '<br />')

  // Clean up multiple br tags
  html = html.replace(/(<br \/>){3,}/g, '<br /><br />')

  return html
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
          Vorschau
        </button>
      </div>
      <div class="flex gap-2">
        <button @click="loadSample" class="text-xs text-gray-400 hover:text-white">Beispiel</button>
        <button @click="clearAll" class="text-xs text-gray-400 hover:text-white">Löschen</button>
        <button @click="copyMarkdown" class="text-xs text-primary-400 hover:text-primary-300">MD kopieren</button>
        <button @click="copyHtml" class="text-xs text-primary-400 hover:text-primary-300">HTML kopieren</button>
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
          placeholder="Markdown hier eingeben..."
        ></textarea>
      </div>

      <!-- Preview -->
      <div v-if="viewMode !== 'edit'" class="bg-white/[0.04] rounded-lg p-4 h-96 overflow-auto">
        <div class="prose prose-invert max-w-none" v-html="renderedHtml"></div>
      </div>
    </div>

    <!-- Quick Reference -->
    <details class="text-sm">
      <summary class="text-gray-400 cursor-pointer hover:text-white">Markdown Kurzreferenz</summary>
      <div class="mt-2 p-3 bg-white/[0.04] rounded-lg grid grid-cols-2 gap-2 text-xs font-mono">
        <div><span class="text-gray-500"># </span>Überschrift 1</div>
        <div><span class="text-gray-500">## </span>Überschrift 2</div>
        <div><span class="text-gray-500">**</span>fett<span class="text-gray-500">**</span></div>
        <div><span class="text-gray-500">*</span>kursiv<span class="text-gray-500">*</span></div>
        <div><span class="text-gray-500">- </span>Liste</div>
        <div><span class="text-gray-500">1. </span>Nummerierte Liste</div>
        <div><span class="text-gray-500">`</span>code<span class="text-gray-500">`</span></div>
        <div><span class="text-gray-500">> </span>Zitat</div>
        <div><span class="text-gray-500">[</span>Link<span class="text-gray-500">](url)</span></div>
        <div><span class="text-gray-500">![</span>Bild<span class="text-gray-500">](url)</span></div>
      </div>
    </details>
  </div>
</template>
