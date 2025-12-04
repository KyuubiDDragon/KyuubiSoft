<script setup>
import { ref, computed, watch } from 'vue'

const input = ref('')
const selectedCase = ref('camelCase')

const cases = [
  { value: 'camelCase', name: 'camelCase', example: 'meinBeispielText' },
  { value: 'PascalCase', name: 'PascalCase', example: 'MeinBeispielText' },
  { value: 'snake_case', name: 'snake_case', example: 'mein_beispiel_text' },
  { value: 'SCREAMING_SNAKE', name: 'SCREAMING_SNAKE_CASE', example: 'MEIN_BEISPIEL_TEXT' },
  { value: 'kebab-case', name: 'kebab-case', example: 'mein-beispiel-text' },
  { value: 'COBOL-CASE', name: 'COBOL-CASE', example: 'MEIN-BEISPIEL-TEXT' },
  { value: 'dot.case', name: 'dot.case', example: 'mein.beispiel.text' },
  { value: 'path/case', name: 'path/case', example: 'mein/beispiel/text' },
  { value: 'Title Case', name: 'Title Case', example: 'Mein Beispiel Text' },
  { value: 'Sentence case', name: 'Sentence case', example: 'Mein beispiel text' },
  { value: 'lowercase', name: 'lowercase', example: 'mein beispiel text' },
  { value: 'UPPERCASE', name: 'UPPERCASE', example: 'MEIN BEISPIEL TEXT' },
  { value: 'aLtErNaTiNg', name: 'aLtErNaTiNg CaSe', example: 'mEiN bEiSpIeL tExT' },
  { value: 'iNVERTED', name: 'iNVERTED cASE', example: 'mEIN bEISPIEL tEXT' },
]

// Split input into words
function getWords(text) {
  // Handle camelCase, PascalCase
  text = text.replace(/([a-z])([A-Z])/g, '$1 $2')
  // Handle snake_case, SCREAMING_SNAKE
  text = text.replace(/_/g, ' ')
  // Handle kebab-case
  text = text.replace(/-/g, ' ')
  // Handle dot.case
  text = text.replace(/\./g, ' ')
  // Handle path/case
  text = text.replace(/\//g, ' ')
  // Split by whitespace and filter empty
  return text.split(/\s+/).filter(w => w.length > 0)
}

function toCamelCase(words) {
  if (words.length === 0) return ''
  return words[0].toLowerCase() + words.slice(1).map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join('')
}

function toPascalCase(words) {
  return words.map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join('')
}

function toSnakeCase(words) {
  return words.map(w => w.toLowerCase()).join('_')
}

function toScreamingSnake(words) {
  return words.map(w => w.toUpperCase()).join('_')
}

function toKebabCase(words) {
  return words.map(w => w.toLowerCase()).join('-')
}

function toCobolCase(words) {
  return words.map(w => w.toUpperCase()).join('-')
}

function toDotCase(words) {
  return words.map(w => w.toLowerCase()).join('.')
}

function toPathCase(words) {
  return words.map(w => w.toLowerCase()).join('/')
}

function toTitleCase(words) {
  return words.map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ')
}

function toSentenceCase(words) {
  if (words.length === 0) return ''
  return words[0].charAt(0).toUpperCase() + words[0].slice(1).toLowerCase() + ' ' + words.slice(1).map(w => w.toLowerCase()).join(' ')
}

function toLowercase(words) {
  return words.map(w => w.toLowerCase()).join(' ')
}

function toUppercase(words) {
  return words.map(w => w.toUpperCase()).join(' ')
}

function toAlternating(words) {
  const text = words.join(' ').toLowerCase()
  let result = ''
  let upper = false
  for (const char of text) {
    if (char.match(/[a-z]/i)) {
      result += upper ? char.toUpperCase() : char.toLowerCase()
      upper = !upper
    } else {
      result += char
    }
  }
  return result
}

function toInverted(words) {
  const text = words.join(' ')
  let result = ''
  for (const char of text) {
    if (char === char.toUpperCase()) {
      result += char.toLowerCase()
    } else {
      result += char.toUpperCase()
    }
  }
  return result
}

function convert(text, caseType) {
  const words = getWords(text)
  if (words.length === 0) return ''

  switch (caseType) {
    case 'camelCase': return toCamelCase(words)
    case 'PascalCase': return toPascalCase(words)
    case 'snake_case': return toSnakeCase(words)
    case 'SCREAMING_SNAKE': return toScreamingSnake(words)
    case 'kebab-case': return toKebabCase(words)
    case 'COBOL-CASE': return toCobolCase(words)
    case 'dot.case': return toDotCase(words)
    case 'path/case': return toPathCase(words)
    case 'Title Case': return toTitleCase(words)
    case 'Sentence case': return toSentenceCase(words)
    case 'lowercase': return toLowercase(words)
    case 'UPPERCASE': return toUppercase(words)
    case 'aLtErNaTiNg': return toAlternating(words)
    case 'iNVERTED': return toInverted(words)
    default: return text
  }
}

const output = computed(() => convert(input.value, selectedCase.value))

const allConversions = computed(() => {
  if (!input.value.trim()) return []
  return cases.map(c => ({
    ...c,
    result: convert(input.value, c.value)
  }))
})

function copyOutput() {
  navigator.clipboard.writeText(output.value)
}

function copyResult(text) {
  navigator.clipboard.writeText(text)
}

function setExample() {
  input.value = 'mein beispiel text'
}
</script>

<template>
  <div class="space-y-4">
    <!-- Input -->
    <div>
      <div class="flex justify-between items-center mb-1">
        <label class="block text-xs text-gray-400">Eingabe</label>
        <button @click="setExample" class="text-xs text-primary-400 hover:text-primary-300">
          Beispiel einfügen
        </button>
      </div>
      <textarea
        v-model="input"
        class="input w-full h-24 resize-none font-mono"
        placeholder="Text eingeben..."
      ></textarea>
    </div>

    <!-- Case selector -->
    <div>
      <label class="block text-xs text-gray-400 mb-1">Zielformat</label>
      <select v-model="selectedCase" class="input w-full">
        <option v-for="c in cases" :key="c.value" :value="c.value">
          {{ c.name }} ({{ c.example }})
        </option>
      </select>
    </div>

    <!-- Output -->
    <div v-if="input.trim()">
      <div class="flex justify-between items-center mb-1">
        <label class="block text-xs text-gray-400">Ergebnis</label>
        <button @click="copyOutput" class="text-xs text-primary-400 hover:text-primary-300">
          Kopieren
        </button>
      </div>
      <div class="p-3 bg-dark-700 rounded-lg font-mono text-green-400 break-all select-all">
        {{ output }}
      </div>
    </div>

    <!-- All conversions -->
    <div v-if="input.trim()" class="space-y-2">
      <h4 class="text-sm text-gray-400">Alle Formate</h4>
      <div class="grid gap-2 max-h-80 overflow-y-auto">
        <div
          v-for="conv in allConversions"
          :key="conv.value"
          class="flex items-center justify-between gap-2 p-2 bg-dark-700 rounded-lg group"
        >
          <div class="flex-1 min-w-0">
            <span class="text-xs text-gray-500">{{ conv.name }}</span>
            <div class="font-mono text-sm text-white truncate">{{ conv.result }}</div>
          </div>
          <button
            @click="copyResult(conv.result)"
            class="p-1 text-gray-400 hover:text-white opacity-0 group-hover:opacity-100 transition-opacity shrink-0"
            title="Kopieren"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Info -->
    <div class="text-xs text-gray-500 space-y-1">
      <p><strong>Tipp:</strong> Der Text wird automatisch in Wörter aufgeteilt, unabhängig vom Eingabeformat.</p>
      <p>Unterstützt camelCase, snake_case, kebab-case, Leerzeichen und mehr als Eingabe.</p>
    </div>
  </div>
</template>
