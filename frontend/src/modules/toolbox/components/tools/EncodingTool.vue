<script setup>
import { ref, computed, watch } from 'vue'

const input = ref('')
const selectedEncoding = ref('base64')
const mode = ref('encode')

const encodings = [
  { value: 'base64', name: 'Base64', desc: 'Standard Base64 Encoding' },
  { value: 'base64url', name: 'Base64 URL-Safe', desc: 'URL-sichere Base64 Variante' },
  { value: 'url', name: 'URL Encoding', desc: 'Prozent-Kodierung für URLs' },
  { value: 'urlComponent', name: 'URL Component', desc: 'Vollständige URL-Komponenten-Kodierung' },
  { value: 'html', name: 'HTML Entities', desc: 'HTML-Zeichen-Entitäten' },
  { value: 'hex', name: 'Hexadezimal', desc: 'Hex-Darstellung des Textes' },
  { value: 'binary', name: 'Binär', desc: 'Binäre Darstellung' },
  { value: 'unicode', name: 'Unicode Escape', desc: 'JavaScript Unicode Escapes' },
  { value: 'rot13', name: 'ROT13', desc: 'Einfache Buchstabenverschiebung' },
]

// Encoding functions
function encodeBase64(text) {
  try {
    return btoa(unescape(encodeURIComponent(text)))
  } catch {
    return 'Fehler: Ungültige Zeichen'
  }
}

function decodeBase64(text) {
  try {
    return decodeURIComponent(escape(atob(text.trim())))
  } catch {
    return 'Fehler: Ungültiges Base64'
  }
}

function encodeBase64Url(text) {
  return encodeBase64(text).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

function decodeBase64Url(text) {
  let base64 = text.replace(/-/g, '+').replace(/_/g, '/')
  while (base64.length % 4) base64 += '='
  return decodeBase64(base64)
}

function encodeUrl(text) {
  return encodeURI(text)
}

function decodeUrl(text) {
  try {
    return decodeURI(text)
  } catch {
    return 'Fehler: Ungültige URL-Kodierung'
  }
}

function encodeUrlComponent(text) {
  return encodeURIComponent(text)
}

function decodeUrlComponent(text) {
  try {
    return decodeURIComponent(text)
  } catch {
    return 'Fehler: Ungültige URL-Komponente'
  }
}

function encodeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }
  return text.replace(/[&<>"']/g, c => map[c])
}

function decodeHtml(text) {
  const doc = new DOMParser().parseFromString(text, 'text/html')
  return doc.documentElement.textContent || ''
}

function encodeHex(text) {
  return Array.from(new TextEncoder().encode(text))
    .map(b => b.toString(16).padStart(2, '0'))
    .join(' ')
}

function decodeHex(text) {
  try {
    const bytes = text.trim().split(/\s+/).map(h => parseInt(h, 16))
    return new TextDecoder().decode(new Uint8Array(bytes))
  } catch {
    return 'Fehler: Ungültiges Hex'
  }
}

function encodeBinary(text) {
  return Array.from(new TextEncoder().encode(text))
    .map(b => b.toString(2).padStart(8, '0'))
    .join(' ')
}

function decodeBinary(text) {
  try {
    const bytes = text.trim().split(/\s+/).map(b => parseInt(b, 2))
    return new TextDecoder().decode(new Uint8Array(bytes))
  } catch {
    return 'Fehler: Ungültiges Binär'
  }
}

function encodeUnicode(text) {
  return Array.from(text)
    .map(c => {
      const code = c.charCodeAt(0)
      if (code > 127) {
        return '\\u' + code.toString(16).padStart(4, '0')
      }
      return c
    })
    .join('')
}

function decodeUnicode(text) {
  try {
    return text.replace(/\\u([0-9a-fA-F]{4})/g, (_, hex) =>
      String.fromCharCode(parseInt(hex, 16))
    )
  } catch {
    return 'Fehler: Ungültiges Unicode'
  }
}

function encodeRot13(text) {
  return text.replace(/[a-zA-Z]/g, c => {
    const base = c <= 'Z' ? 65 : 97
    return String.fromCharCode(((c.charCodeAt(0) - base + 13) % 26) + base)
  })
}

// ROT13 is symmetric
const decodeRot13 = encodeRot13

function process(text, encoding, isEncode) {
  if (!text) return ''

  switch (encoding) {
    case 'base64':
      return isEncode ? encodeBase64(text) : decodeBase64(text)
    case 'base64url':
      return isEncode ? encodeBase64Url(text) : decodeBase64Url(text)
    case 'url':
      return isEncode ? encodeUrl(text) : decodeUrl(text)
    case 'urlComponent':
      return isEncode ? encodeUrlComponent(text) : decodeUrlComponent(text)
    case 'html':
      return isEncode ? encodeHtml(text) : decodeHtml(text)
    case 'hex':
      return isEncode ? encodeHex(text) : decodeHex(text)
    case 'binary':
      return isEncode ? encodeBinary(text) : decodeBinary(text)
    case 'unicode':
      return isEncode ? encodeUnicode(text) : decodeUnicode(text)
    case 'rot13':
      return encodeRot13(text) // Symmetric
    default:
      return text
  }
}

const output = computed(() => process(input.value, selectedEncoding.value, mode.value === 'encode'))

function copyOutput() {
  navigator.clipboard.writeText(output.value)
}

function swapInputOutput() {
  input.value = output.value
  mode.value = mode.value === 'encode' ? 'decode' : 'encode'
}

function clearInput() {
  input.value = ''
}

const inputStats = computed(() => {
  const text = input.value
  return {
    chars: text.length,
    bytes: new TextEncoder().encode(text).length,
  }
})

const outputStats = computed(() => {
  const text = output.value
  return {
    chars: text.length,
    bytes: new TextEncoder().encode(text).length,
  }
})
</script>

<template>
  <div class="space-y-4">
    <!-- Mode & Encoding Selection -->
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs text-gray-400 mb-1">Modus</label>
        <div class="flex gap-2">
          <button
            @click="mode = 'encode'"
            class="flex-1 px-3 py-2 rounded-lg text-sm transition-colors"
            :class="mode === 'encode' ? 'bg-primary-600 text-white' : 'bg-dark-700 text-gray-300 hover:bg-dark-600'"
          >
            Kodieren
          </button>
          <button
            @click="mode = 'decode'"
            class="flex-1 px-3 py-2 rounded-lg text-sm transition-colors"
            :class="mode === 'decode' ? 'bg-primary-600 text-white' : 'bg-dark-700 text-gray-300 hover:bg-dark-600'"
          >
            Dekodieren
          </button>
        </div>
      </div>

      <div>
        <label class="block text-xs text-gray-400 mb-1">Kodierung</label>
        <select v-model="selectedEncoding" class="input w-full">
          <option v-for="enc in encodings" :key="enc.value" :value="enc.value">
            {{ enc.name }}
          </option>
        </select>
      </div>
    </div>

    <!-- Encoding description -->
    <p class="text-xs text-gray-500">
      {{ encodings.find(e => e.value === selectedEncoding)?.desc }}
    </p>

    <!-- Input -->
    <div>
      <div class="flex justify-between items-center mb-1">
        <label class="block text-xs text-gray-400">
          Eingabe
          <span class="text-gray-600">({{ inputStats.chars }} Zeichen, {{ inputStats.bytes }} Bytes)</span>
        </label>
        <button @click="clearInput" class="text-xs text-gray-500 hover:text-white">
          Leeren
        </button>
      </div>
      <textarea
        v-model="input"
        class="input w-full h-32 resize-none font-mono text-sm"
        :placeholder="mode === 'encode' ? 'Text zum Kodieren...' : 'Text zum Dekodieren...'"
      ></textarea>
    </div>

    <!-- Swap Button -->
    <div class="flex justify-center">
      <button
        @click="swapInputOutput"
        class="p-2 text-gray-400 hover:text-white bg-dark-700 rounded-lg"
        title="Eingabe/Ausgabe tauschen"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
        </svg>
      </button>
    </div>

    <!-- Output -->
    <div>
      <div class="flex justify-between items-center mb-1">
        <label class="block text-xs text-gray-400">
          Ausgabe
          <span class="text-gray-600">({{ outputStats.chars }} Zeichen, {{ outputStats.bytes }} Bytes)</span>
        </label>
        <button @click="copyOutput" class="text-xs text-primary-400 hover:text-primary-300">
          Kopieren
        </button>
      </div>
      <div class="p-3 bg-dark-700 rounded-lg min-h-[8rem] max-h-48 overflow-auto">
        <pre class="font-mono text-sm text-green-400 whitespace-pre-wrap break-all">{{ output || '...' }}</pre>
      </div>
    </div>

    <!-- Quick Examples -->
    <div class="flex flex-wrap gap-2">
      <span class="text-xs text-gray-500">Beispiele:</span>
      <button
        @click="input = 'Hallo Welt! 🌍'"
        class="text-xs text-primary-400 hover:text-primary-300"
      >
        Text mit Emoji
      </button>
      <button
        @click="input = 'https://example.com/path?q=test&lang=de'"
        class="text-xs text-primary-400 hover:text-primary-300"
      >
        URL
      </button>
      <button
        @click="input = '<script>alert(\"XSS\")</script>'"
        class="text-xs text-primary-400 hover:text-primary-300"
      >
        HTML
      </button>
    </div>

    <!-- Info -->
    <div class="text-xs text-gray-500 space-y-1">
      <p><strong>Base64:</strong> Standard-Kodierung für Binärdaten in Text</p>
      <p><strong>URL Encoding:</strong> Für sichere URL-Parameter</p>
      <p><strong>HTML Entities:</strong> Verhindert XSS-Angriffe in HTML</p>
      <p><strong>ROT13:</strong> Einfache Verschleierung (keine echte Verschlüsselung)</p>
    </div>
  </div>
</template>
