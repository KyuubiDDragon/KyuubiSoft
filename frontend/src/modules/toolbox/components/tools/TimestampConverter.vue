<script setup>
import { ref, computed, watch } from 'vue'

const inputType = ref('unix') // 'unix', 'iso', 'readable'
const unixInput = ref(Math.floor(Date.now() / 1000))
const isoInput = ref(new Date().toISOString())
const readableInput = ref('')

const dateObj = ref(new Date())

// Common formats
const formats = [
  { id: 'unix', name: 'Unix Timestamp (Sekunden)', example: '1701619200' },
  { id: 'unixMs', name: 'Unix Timestamp (Millisekunden)', example: '1701619200000' },
  { id: 'iso', name: 'ISO 8601', example: '2024-12-03T12:00:00.000Z' },
  { id: 'german', name: 'Deutsch', example: '03.12.2024 12:00:00' },
  { id: 'us', name: 'US Format', example: '12/03/2024 12:00:00 PM' },
  { id: 'relative', name: 'Relativ', example: 'vor 5 Minuten' },
]

// Convert from input
function updateFromUnix() {
  const ts = parseInt(unixInput.value)
  if (!isNaN(ts)) {
    // Auto-detect if it's milliseconds or seconds
    const timestamp = ts > 9999999999 ? ts : ts * 1000
    dateObj.value = new Date(timestamp)
  }
}

function updateFromIso() {
  const d = new Date(isoInput.value)
  if (!isNaN(d.getTime())) {
    dateObj.value = d
  }
}

// Watch inputs
watch(unixInput, () => {
  if (inputType.value === 'unix') updateFromUnix()
})

watch(isoInput, () => {
  if (inputType.value === 'iso') updateFromIso()
})

// Initialize
updateFromUnix()

// Computed outputs
const outputs = computed(() => {
  const d = dateObj.value
  if (!d || isNaN(d.getTime())) return {}

  return {
    unix: Math.floor(d.getTime() / 1000),
    unixMs: d.getTime(),
    iso: d.toISOString(),
    german: formatGerman(d),
    us: formatUS(d),
    relative: formatRelative(d),
    utc: d.toUTCString(),
    local: d.toLocaleString('de-DE'),
    date: d.toLocaleDateString('de-DE'),
    time: d.toLocaleTimeString('de-DE'),
    dayOfWeek: d.toLocaleDateString('de-DE', { weekday: 'long' }),
    weekNumber: getWeekNumber(d),
    dayOfYear: getDayOfYear(d),
  }
})

function formatGerman(d) {
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = d.getFullYear()
  const hours = String(d.getHours()).padStart(2, '0')
  const mins = String(d.getMinutes()).padStart(2, '0')
  const secs = String(d.getSeconds()).padStart(2, '0')
  return `${day}.${month}.${year} ${hours}:${mins}:${secs}`
}

function formatUS(d) {
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  const year = d.getFullYear()
  let hours = d.getHours()
  const ampm = hours >= 12 ? 'PM' : 'AM'
  hours = hours % 12
  hours = hours ? hours : 12
  const mins = String(d.getMinutes()).padStart(2, '0')
  const secs = String(d.getSeconds()).padStart(2, '0')
  return `${month}/${day}/${year} ${hours}:${mins}:${secs} ${ampm}`
}

function formatRelative(d) {
  const now = new Date()
  const diff = now.getTime() - d.getTime()
  const absDiff = Math.abs(diff)
  const isPast = diff > 0

  const seconds = Math.floor(absDiff / 1000)
  const minutes = Math.floor(seconds / 60)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)
  const weeks = Math.floor(days / 7)
  const months = Math.floor(days / 30)
  const years = Math.floor(days / 365)

  let result
  if (seconds < 60) result = `${seconds} Sekunden`
  else if (minutes < 60) result = `${minutes} Minuten`
  else if (hours < 24) result = `${hours} Stunden`
  else if (days < 7) result = `${days} Tagen`
  else if (weeks < 4) result = `${weeks} Wochen`
  else if (months < 12) result = `${months} Monaten`
  else result = `${years} Jahren`

  return isPast ? `vor ${result}` : `in ${result}`
}

function getWeekNumber(d) {
  const date = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()))
  const dayNum = date.getUTCDay() || 7
  date.setUTCDate(date.getUTCDate() + 4 - dayNum)
  const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1))
  return Math.ceil((((date - yearStart) / 86400000) + 1) / 7)
}

function getDayOfYear(d) {
  const start = new Date(d.getFullYear(), 0, 0)
  const diff = d - start
  const oneDay = 1000 * 60 * 60 * 24
  return Math.floor(diff / oneDay)
}

function setNow() {
  dateObj.value = new Date()
  unixInput.value = Math.floor(Date.now() / 1000)
  isoInput.value = new Date().toISOString()
}

function setStartOfDay() {
  const d = new Date(dateObj.value)
  d.setHours(0, 0, 0, 0)
  dateObj.value = d
  unixInput.value = Math.floor(d.getTime() / 1000)
}

function setEndOfDay() {
  const d = new Date(dateObj.value)
  d.setHours(23, 59, 59, 999)
  dateObj.value = d
  unixInput.value = Math.floor(d.getTime() / 1000)
}

function addTime(amount, unit) {
  const d = new Date(dateObj.value)
  switch (unit) {
    case 'minute': d.setMinutes(d.getMinutes() + amount); break
    case 'hour': d.setHours(d.getHours() + amount); break
    case 'day': d.setDate(d.getDate() + amount); break
    case 'week': d.setDate(d.getDate() + amount * 7); break
    case 'month': d.setMonth(d.getMonth() + amount); break
    case 'year': d.setFullYear(d.getFullYear() + amount); break
  }
  dateObj.value = d
  unixInput.value = Math.floor(d.getTime() / 1000)
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(String(text))
}
</script>

<template>
  <div class="space-y-4">
    <!-- Input Section -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Unix Timestamp</label>
        <input
          v-model="unixInput"
          type="number"
          @focus="inputType = 'unix'"
          @input="updateFromUnix"
          class="input w-full font-mono"
          placeholder="1701619200"
        />
      </div>
      <div>
        <label class="text-sm text-gray-400 mb-1 block">ISO 8601</label>
        <input
          v-model="isoInput"
          type="text"
          @focus="inputType = 'iso'"
          @input="updateFromIso"
          class="input w-full font-mono text-sm"
          placeholder="2024-12-03T12:00:00Z"
        />
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="flex flex-wrap gap-2">
      <button @click="setNow" class="px-3 py-1 text-xs bg-primary-600 hover:bg-primary-500 text-white rounded">
        Jetzt
      </button>
      <button @click="setStartOfDay" class="px-3 py-1 text-xs bg-white/[0.08] hover:bg-white/[0.08] text-gray-300 rounded">
        Tagesanfang
      </button>
      <button @click="setEndOfDay" class="px-3 py-1 text-xs bg-white/[0.08] hover:bg-white/[0.08] text-gray-300 rounded">
        Tagesende
      </button>
      <span class="text-gray-600">|</span>
      <button @click="addTime(-1, 'hour')" class="px-2 py-1 text-xs bg-white/[0.04] hover:bg-white/[0.04] text-gray-400 rounded">-1h</button>
      <button @click="addTime(1, 'hour')" class="px-2 py-1 text-xs bg-white/[0.04] hover:bg-white/[0.04] text-gray-400 rounded">+1h</button>
      <button @click="addTime(-1, 'day')" class="px-2 py-1 text-xs bg-white/[0.04] hover:bg-white/[0.04] text-gray-400 rounded">-1d</button>
      <button @click="addTime(1, 'day')" class="px-2 py-1 text-xs bg-white/[0.04] hover:bg-white/[0.04] text-gray-400 rounded">+1d</button>
      <button @click="addTime(-1, 'week')" class="px-2 py-1 text-xs bg-white/[0.04] hover:bg-white/[0.04] text-gray-400 rounded">-1w</button>
      <button @click="addTime(1, 'week')" class="px-2 py-1 text-xs bg-white/[0.04] hover:bg-white/[0.04] text-gray-400 rounded">+1w</button>
    </div>

    <!-- Output Section -->
    <div class="space-y-2">
      <div
        v-for="(value, key) in outputs"
        :key="key"
        class="flex items-center justify-between p-2 bg-white/[0.04] rounded"
      >
        <div class="flex-1 min-w-0">
          <span class="text-xs text-gray-500 uppercase">{{ key }}</span>
          <div class="font-mono text-sm text-white truncate">{{ value }}</div>
        </div>
        <button
          @click="copyToClipboard(value)"
          class="ml-2 px-2 py-1 text-xs text-primary-400 hover:text-primary-300"
        >
          Kopieren
        </button>
      </div>
    </div>
  </div>
</template>
