<script setup>
import { ref, computed } from 'vue'

const generatedUuids = ref([])
const count = ref(1)
const version = ref('v4')
const uppercase = ref(false)
const noDashes = ref(false)

const versions = [
  { value: 'v4', name: 'UUID v4 (Random)', desc: 'Zufällig generiert - am häufigsten verwendet' },
  { value: 'v1', name: 'UUID v1 (Timestamp)', desc: 'Basiert auf Zeitstempel + MAC-Adresse' },
  { value: 'ulid', name: 'ULID', desc: 'Sortierbar, kompakter als UUID' },
  { value: 'nanoid', name: 'NanoID (21)', desc: 'Kurze, URL-sichere IDs' },
  { value: 'nanoid-short', name: 'NanoID (10)', desc: 'Sehr kurze IDs' },
]

// UUID v4 generator
function generateUUIDv4() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}

// UUID v1 generator (simplified - uses random node instead of MAC)
function generateUUIDv1() {
  const now = Date.now()
  const timeHigh = ((now / 0x100000000) * 10000) & 0x0fffffff
  const timeLow = (now & 0xffffffff) * 10000

  const uuid = 'xxxxxxxx-xxxx-1xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c, i) => {
    if (i < 8) {
      return ((timeLow >> ((7 - i) * 4)) & 0xf).toString(16)
    } else if (i < 13 && i > 8) {
      return ((timeHigh >> ((12 - i) * 4)) & 0xf).toString(16)
    }
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })

  return uuid
}

// ULID generator
function generateULID() {
  const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'
  const TIME_LEN = 10
  const RANDOM_LEN = 16

  let str = ''
  let now = Date.now()

  // Encode time (48 bits)
  for (let i = TIME_LEN - 1; i >= 0; i--) {
    str = ENCODING[now % 32] + str
    now = Math.floor(now / 32)
  }

  // Encode randomness (80 bits)
  for (let i = 0; i < RANDOM_LEN; i++) {
    str += ENCODING[Math.floor(Math.random() * 32)]
  }

  return str
}

// NanoID generator
function generateNanoID(size = 21) {
  const alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-_'
  let id = ''
  for (let i = 0; i < size; i++) {
    id += alphabet[Math.floor(Math.random() * alphabet.length)]
  }
  return id
}

function formatUuid(uuid) {
  let result = uuid
  if (noDashes.value && result.includes('-')) {
    result = result.replace(/-/g, '')
  }
  if (uppercase.value) {
    result = result.toUpperCase()
  } else {
    result = result.toLowerCase()
  }
  return result
}

function generate() {
  const newUuids = []
  for (let i = 0; i < count.value; i++) {
    let uuid
    switch (version.value) {
      case 'v1':
        uuid = generateUUIDv1()
        break
      case 'ulid':
        uuid = generateULID()
        break
      case 'nanoid':
        uuid = generateNanoID(21)
        break
      case 'nanoid-short':
        uuid = generateNanoID(10)
        break
      default:
        uuid = generateUUIDv4()
    }
    newUuids.push({
      id: Date.now() + i,
      value: formatUuid(uuid),
      raw: uuid,
    })
  }
  generatedUuids.value = [...newUuids, ...generatedUuids.value].slice(0, 100)
}

function copyUuid(uuid) {
  navigator.clipboard.writeText(uuid)
}

function copyAll() {
  const text = generatedUuids.value.map(u => u.value).join('\n')
  navigator.clipboard.writeText(text)
}

function clearAll() {
  generatedUuids.value = []
}

// Generate one on mount
generate()
</script>

<template>
  <div class="space-y-4">
    <!-- Options -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div>
        <label class="block text-xs text-gray-400 mb-1">Version</label>
        <select v-model="version" class="input w-full">
          <option v-for="v in versions" :key="v.value" :value="v.value">
            {{ v.name }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-xs text-gray-400 mb-1">Anzahl</label>
        <input
          v-model.number="count"
          type="number"
          min="1"
          max="100"
          class="input w-full"
        />
      </div>

      <div class="flex items-end gap-4">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" v-model="uppercase" class="rounded bg-dark-700 border-dark-600" />
          <span class="text-sm text-gray-300">UPPERCASE</span>
        </label>
      </div>

      <div class="flex items-end gap-4">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" v-model="noDashes" class="rounded bg-dark-700 border-dark-600" />
          <span class="text-sm text-gray-300">Ohne Bindestriche</span>
        </label>
      </div>
    </div>

    <!-- Version description -->
    <p class="text-xs text-gray-500">
      {{ versions.find(v => v.value === version)?.desc }}
    </p>

    <!-- Generate button -->
    <div class="flex gap-2">
      <button @click="generate" class="btn-primary px-6">
        Generieren
      </button>
      <button
        v-if="generatedUuids.length > 0"
        @click="copyAll"
        class="btn-secondary px-4"
      >
        Alle kopieren
      </button>
      <button
        v-if="generatedUuids.length > 0"
        @click="clearAll"
        class="btn-secondary px-4 text-red-400 hover:text-red-300"
      >
        Leeren
      </button>
    </div>

    <!-- Generated UUIDs -->
    <div v-if="generatedUuids.length > 0" class="space-y-2">
      <div class="flex justify-between items-center">
        <span class="text-sm text-gray-400">{{ generatedUuids.length }} generiert</span>
      </div>

      <div class="space-y-1 max-h-96 overflow-y-auto">
        <div
          v-for="uuid in generatedUuids"
          :key="uuid.id"
          class="flex items-center gap-2 p-2 bg-dark-700 rounded-lg group"
        >
          <code class="flex-1 text-sm font-mono text-green-400 select-all">
            {{ uuid.value }}
          </code>
          <button
            @click="copyUuid(uuid.value)"
            class="p-1 text-gray-400 hover:text-white opacity-0 group-hover:opacity-100 transition-opacity"
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
      <p><strong>UUID v4:</strong> 128-bit zufällig generiert, Standard für die meisten Anwendungen</p>
      <p><strong>UUID v1:</strong> Enthält Zeitstempel, chronologisch sortierbar</p>
      <p><strong>ULID:</strong> 128-bit, lexikografisch sortierbar, kompakter als UUID</p>
      <p><strong>NanoID:</strong> URL-sichere, kompakte Alternative zu UUID</p>
    </div>
  </div>
</template>
