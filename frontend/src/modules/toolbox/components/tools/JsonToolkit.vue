<script setup>
import { ref, computed } from 'vue'
import { DocumentDuplicateIcon, CheckIcon } from '@heroicons/vue/24/outline'

const activeTab = ref('json')
const input = ref('')
const output = ref('')
const error = ref('')
const copied = ref(false)

const tabs = [
  { id: 'json', name: 'JSON' },
  { id: 'base64', name: 'Base64' },
  { id: 'url', name: 'URL' },
  { id: 'uuid', name: 'UUID' },
]

// JSON Functions
function formatJson() {
  try {
    const parsed = JSON.parse(input.value)
    output.value = JSON.stringify(parsed, null, 2)
    error.value = ''
  } catch (e) {
    error.value = 'Ungültiges JSON: ' + e.message
    output.value = ''
  }
}

function minifyJson() {
  try {
    const parsed = JSON.parse(input.value)
    output.value = JSON.stringify(parsed)
    error.value = ''
  } catch (e) {
    error.value = 'Ungültiges JSON: ' + e.message
    output.value = ''
  }
}

function validateJson() {
  try {
    JSON.parse(input.value)
    output.value = '✓ Valides JSON'
    error.value = ''
  } catch (e) {
    error.value = 'Ungültiges JSON: ' + e.message
    output.value = ''
  }
}

// Base64 Functions
function encodeBase64() {
  try {
    output.value = btoa(unescape(encodeURIComponent(input.value)))
    error.value = ''
  } catch (e) {
    error.value = 'Encoding fehlgeschlagen: ' + e.message
    output.value = ''
  }
}

function decodeBase64() {
  try {
    output.value = decodeURIComponent(escape(atob(input.value)))
    error.value = ''
  } catch (e) {
    error.value = 'Decoding fehlgeschlagen: Ungültiger Base64 String'
    output.value = ''
  }
}

// URL Functions
function encodeUrl() {
  output.value = encodeURIComponent(input.value)
  error.value = ''
}

function decodeUrl() {
  try {
    output.value = decodeURIComponent(input.value)
    error.value = ''
  } catch (e) {
    error.value = 'Decoding fehlgeschlagen: Ungültiger URL String'
    output.value = ''
  }
}

// UUID Functions
function generateUuidV4() {
  output.value = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0
    const v = c === 'x' ? r : (r & 0x3 | 0x8)
    return v.toString(16)
  })
  error.value = ''
}

function generateMultipleUuids() {
  const uuids = []
  for (let i = 0; i < 10; i++) {
    uuids.push('xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0
      const v = c === 'x' ? r : (r & 0x3 | 0x8)
      return v.toString(16)
    }))
  }
  output.value = uuids.join('\n')
  error.value = ''
}

// Copy function
async function copyOutput() {
  if (output.value) {
    await navigator.clipboard.writeText(output.value)
    copied.value = true
    setTimeout(() => copied.value = false, 2000)
  }
}

function clearAll() {
  input.value = ''
  output.value = ''
  error.value = ''
}
</script>

<template>
  <div class="space-y-4">
    <!-- Tabs -->
    <div class="flex gap-2 border-b border-dark-600 pb-2">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="activeTab = tab.id; clearAll()"
        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
        :class="activeTab === tab.id ? 'bg-primary-600 text-white' : 'text-gray-400 hover:text-white hover:bg-dark-700'"
      >
        {{ tab.name }}
      </button>
    </div>

    <!-- JSON Tab -->
    <div v-if="activeTab === 'json'" class="space-y-4">
      <div>
        <label class="text-sm text-gray-400 mb-1 block">JSON Input</label>
        <textarea
          v-model="input"
          class="input w-full h-40 font-mono text-sm"
          placeholder='{"key": "value"}'
        ></textarea>
      </div>
      <div class="flex gap-2">
        <button @click="formatJson" class="btn-primary">Formatieren</button>
        <button @click="minifyJson" class="btn-secondary">Minifizieren</button>
        <button @click="validateJson" class="btn-secondary">Validieren</button>
        <button @click="clearAll" class="btn-ghost">Löschen</button>
      </div>
    </div>

    <!-- Base64 Tab -->
    <div v-if="activeTab === 'base64'" class="space-y-4">
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Text / Base64 Input</label>
        <textarea
          v-model="input"
          class="input w-full h-40 font-mono text-sm"
          placeholder="Text eingeben..."
        ></textarea>
      </div>
      <div class="flex gap-2">
        <button @click="encodeBase64" class="btn-primary">Encode</button>
        <button @click="decodeBase64" class="btn-secondary">Decode</button>
        <button @click="clearAll" class="btn-ghost">Löschen</button>
      </div>
    </div>

    <!-- URL Tab -->
    <div v-if="activeTab === 'url'" class="space-y-4">
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Text / URL Input</label>
        <textarea
          v-model="input"
          class="input w-full h-40 font-mono text-sm"
          placeholder="Text oder URL eingeben..."
        ></textarea>
      </div>
      <div class="flex gap-2">
        <button @click="encodeUrl" class="btn-primary">Encode</button>
        <button @click="decodeUrl" class="btn-secondary">Decode</button>
        <button @click="clearAll" class="btn-ghost">Löschen</button>
      </div>
    </div>

    <!-- UUID Tab -->
    <div v-if="activeTab === 'uuid'" class="space-y-4">
      <p class="text-sm text-gray-400">Generiere zufällige UUIDs (Version 4)</p>
      <div class="flex gap-2">
        <button @click="generateUuidV4" class="btn-primary">1x UUID</button>
        <button @click="generateMultipleUuids" class="btn-secondary">10x UUIDs</button>
      </div>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-3 bg-red-900/30 border border-red-700 rounded-lg text-red-400 text-sm">
      {{ error }}
    </div>

    <!-- Output -->
    <div v-if="output" class="relative">
      <label class="text-sm text-gray-400 mb-1 block">Output</label>
      <pre class="p-4 bg-dark-900 rounded-lg text-sm font-mono text-green-400 overflow-auto max-h-60 whitespace-pre-wrap">{{ output }}</pre>
      <button
        @click="copyOutput"
        class="absolute top-8 right-2 btn-icon"
        :title="copied ? 'Kopiert!' : 'Kopieren'"
      >
        <CheckIcon v-if="copied" class="w-4 h-4 text-green-400" />
        <DocumentDuplicateIcon v-else class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>
