<script setup>
import { ref, computed } from 'vue'

const mode = ref('csv-to-json')
const input = ref('')
const delimiter = ref(',')
const hasHeader = ref(true)
const jsonIndent = ref(2)
const error = ref('')

function parseCSV(csv, delim = ',') {
  const lines = csv.trim().split('\n')
  if (lines.length === 0) return []

  const result = []
  let headers = []

  for (let i = 0; i < lines.length; i++) {
    const values = parseCSVLine(lines[i], delim)

    if (i === 0 && hasHeader.value) {
      headers = values.map(h => h.trim())
    } else {
      if (hasHeader.value) {
        const obj = {}
        headers.forEach((h, idx) => {
          let val = values[idx] || ''
          // Try to parse numbers
          if (!isNaN(val) && val !== '') {
            val = parseFloat(val)
          } else if (val.toLowerCase() === 'true') {
            val = true
          } else if (val.toLowerCase() === 'false') {
            val = false
          } else if (val.toLowerCase() === 'null' || val === '') {
            val = null
          }
          obj[h] = val
        })
        result.push(obj)
      } else {
        result.push(values)
      }
    }
  }

  return result
}

function parseCSVLine(line, delim) {
  const result = []
  let current = ''
  let inQuotes = false

  for (let i = 0; i < line.length; i++) {
    const char = line[i]
    const next = line[i + 1]

    if (char === '"') {
      if (inQuotes && next === '"') {
        current += '"'
        i++
      } else {
        inQuotes = !inQuotes
      }
    } else if (char === delim && !inQuotes) {
      result.push(current)
      current = ''
    } else {
      current += char
    }
  }

  result.push(current)
  return result
}

function jsonToCSV(json, delim = ',') {
  if (!Array.isArray(json) || json.length === 0) {
    throw new Error('JSON muss ein Array von Objekten sein')
  }

  // Get all unique headers
  const headers = [...new Set(json.flatMap(obj => Object.keys(obj)))]

  // Build CSV
  const rows = [headers.join(delim)]

  json.forEach(obj => {
    const values = headers.map(h => {
      let val = obj[h]
      if (val === null || val === undefined) {
        return ''
      }
      val = String(val)
      // Escape quotes and wrap if needed
      if (val.includes(delim) || val.includes('"') || val.includes('\n')) {
        return `"${val.replace(/"/g, '""')}"`
      }
      return val
    })
    rows.push(values.join(delim))
  })

  return rows.join('\n')
}

const output = computed(() => {
  error.value = ''
  if (!input.value.trim()) return ''

  try {
    if (mode.value === 'csv-to-json') {
      const data = parseCSV(input.value, delimiter.value)
      return JSON.stringify(data, null, jsonIndent.value)
    } else {
      const data = JSON.parse(input.value)
      return jsonToCSV(data, delimiter.value)
    }
  } catch (e) {
    error.value = e.message
    return ''
  }
})

const stats = computed(() => {
  if (!output.value) return null

  try {
    if (mode.value === 'csv-to-json') {
      const data = JSON.parse(output.value)
      return {
        rows: Array.isArray(data) ? data.length : 1,
        columns: Array.isArray(data) && data[0] ? Object.keys(data[0]).length : 0,
      }
    } else {
      const lines = output.value.split('\n')
      return {
        rows: lines.length - 1,
        columns: lines[0] ? lines[0].split(delimiter.value).length : 0,
      }
    }
  } catch {
    return null
  }
})

function copyOutput() {
  navigator.clipboard.writeText(output.value)
}

function swapMode() {
  mode.value = mode.value === 'csv-to-json' ? 'json-to-csv' : 'csv-to-json'
  input.value = output.value || input.value
}

function setExample() {
  if (mode.value === 'csv-to-json') {
    input.value = `name,email,age,active
Max Müller,max@example.com,32,true
Anna Schmidt,anna@example.com,28,true
Paul Weber,paul@example.com,45,false`
  } else {
    input.value = `[
  {"name": "Max Müller", "email": "max@example.com", "age": 32, "active": true},
  {"name": "Anna Schmidt", "email": "anna@example.com", "age": 28, "active": true},
  {"name": "Paul Weber", "email": "paul@example.com", "age": 45, "active": false}
]`
  }
}

function downloadOutput() {
  const ext = mode.value === 'csv-to-json' ? 'json' : 'csv'
  const type = mode.value === 'csv-to-json' ? 'application/json' : 'text/csv'
  const blob = new Blob([output.value], { type })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `data.${ext}`
  a.click()
  URL.revokeObjectURL(url)
}

const inputPlaceholder = computed(() => {
  if (mode.value === 'csv-to-json') {
    return 'name,email,age\nMax,max@example.com,32'
  }
  return '[{"name": "Max", "email": "max@example.com"}]'
})
</script>

<template>
  <div class="space-y-4">
    <!-- Mode Switch -->
    <div class="flex items-center justify-center gap-4">
      <button
        @click="mode = 'csv-to-json'"
        class="px-4 py-2 rounded-lg transition-colors"
        :class="mode === 'csv-to-json' ? 'bg-primary-600 text-white' : 'bg-dark-700 text-gray-400 hover:text-white'"
      >
        CSV → JSON
      </button>
      <button
        @click="swapMode"
        class="p-2 text-gray-400 hover:text-white bg-dark-700 rounded-lg"
        title="Eingabe/Ausgabe tauschen"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
        </svg>
      </button>
      <button
        @click="mode = 'json-to-csv'"
        class="px-4 py-2 rounded-lg transition-colors"
        :class="mode === 'json-to-csv' ? 'bg-primary-600 text-white' : 'bg-dark-700 text-gray-400 hover:text-white'"
      >
        JSON → CSV
      </button>
    </div>

    <!-- Options -->
    <div class="flex flex-wrap gap-4 items-center">
      <div class="flex items-center gap-2">
        <label class="text-xs text-gray-400">Trennzeichen:</label>
        <select v-model="delimiter" class="input py-1 px-2 w-24">
          <option value=",">Komma (,)</option>
          <option value=";">Semikolon (;)</option>
          <option value="&#9;">Tab</option>
          <option value="|">Pipe (|)</option>
        </select>
      </div>
      <label v-if="mode === 'csv-to-json'" class="flex items-center gap-2 text-sm text-gray-300">
        <input type="checkbox" v-model="hasHeader" class="rounded bg-dark-600" />
        Erste Zeile ist Header
      </label>
      <div v-if="mode === 'csv-to-json'" class="flex items-center gap-2">
        <label class="text-xs text-gray-400">JSON Einrückung:</label>
        <select v-model.number="jsonIndent" class="input py-1 px-2 w-16">
          <option :value="0">Keine</option>
          <option :value="2">2</option>
          <option :value="4">4</option>
        </select>
      </div>
      <button @click="setExample" class="text-xs text-primary-400 hover:text-primary-300">
        Beispiel laden
      </button>
    </div>

    <!-- Input -->
    <div>
      <label class="block text-xs text-gray-400 mb-1">
        {{ mode === 'csv-to-json' ? 'CSV Eingabe' : 'JSON Eingabe' }}
      </label>
      <textarea
        v-model="input"
        class="input w-full h-40 font-mono text-sm resize-none"
        :placeholder="inputPlaceholder"
      ></textarea>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-3 bg-red-900/30 border border-red-500/30 rounded-lg text-red-400 text-sm">
      {{ error }}
    </div>

    <!-- Output -->
    <div v-if="output">
      <div class="flex items-center justify-between mb-1">
        <label class="text-xs text-gray-400">
          {{ mode === 'csv-to-json' ? 'JSON Ausgabe' : 'CSV Ausgabe' }}
          <span v-if="stats" class="text-gray-600 ml-2">
            ({{ stats.rows }} Zeilen, {{ stats.columns }} Spalten)
          </span>
        </label>
        <div class="flex gap-2">
          <button @click="copyOutput" class="text-xs text-primary-400 hover:text-primary-300">
            Kopieren
          </button>
          <button @click="downloadOutput" class="text-xs text-green-400 hover:text-green-300">
            Download
          </button>
        </div>
      </div>
      <pre class="p-3 bg-dark-900 rounded-lg text-sm text-green-400 font-mono max-h-48 overflow-auto">{{ output }}</pre>
    </div>

    <!-- Info -->
    <div class="text-xs text-gray-500 space-y-1">
      <p><strong>CSV → JSON:</strong> Wandelt CSV in ein Array von Objekten um. Zahlen und Booleans werden automatisch konvertiert.</p>
      <p><strong>JSON → CSV:</strong> Erwartet ein Array von Objekten. Alle Keys werden als Spalten verwendet.</p>
    </div>
  </div>
</template>
