<script setup>
import { ref, computed, watch } from 'vue'

const inputValue = ref('255')
const inputBase = ref(10)
const errorMessage = ref('')

const bases = [
  { value: 2, name: 'Binär', prefix: '0b' },
  { value: 8, name: 'Oktal', prefix: '0o' },
  { value: 10, name: 'Dezimal', prefix: '' },
  { value: 16, name: 'Hexadezimal', prefix: '0x' },
]

// Validate input for current base
function isValidForBase(value, base) {
  if (!value) return true

  const validChars = {
    2: /^[01]+$/,
    8: /^[0-7]+$/,
    10: /^[0-9]+$/,
    16: /^[0-9a-fA-F]+$/,
  }

  // Remove common prefixes
  let cleaned = value.trim()
  cleaned = cleaned.replace(/^0[bB]/, '') // binary
  cleaned = cleaned.replace(/^0[oO]/, '') // octal
  cleaned = cleaned.replace(/^0[xX]/, '') // hex

  return validChars[base].test(cleaned)
}

// Convert to decimal (base 10)
function toDecimal(value, fromBase) {
  if (!value) return 0

  let cleaned = value.trim()
  cleaned = cleaned.replace(/^0[bBxXoO]/, '')

  return parseInt(cleaned, fromBase)
}

// Convert from decimal to target base
function fromDecimal(decimal, toBase) {
  if (isNaN(decimal)) return ''
  return decimal.toString(toBase).toUpperCase()
}

const decimalValue = computed(() => {
  if (!inputValue.value) return 0
  if (!isValidForBase(inputValue.value, inputBase.value)) {
    return NaN
  }
  return toDecimal(inputValue.value, inputBase.value)
})

const conversions = computed(() => {
  const dec = decimalValue.value

  if (isNaN(dec)) {
    return bases.map(b => ({ ...b, result: 'Ungültige Eingabe', error: true }))
  }

  return bases.map(base => ({
    ...base,
    result: fromDecimal(dec, base.value),
    error: false,
  }))
})

// Additional representations
const additionalFormats = computed(() => {
  const dec = decimalValue.value
  if (isNaN(dec) || dec < 0) return []

  const results = []

  // ASCII (if in printable range)
  if (dec >= 32 && dec <= 126) {
    results.push({ name: 'ASCII Zeichen', value: String.fromCharCode(dec) })
  }

  // Binary with groups
  if (dec >= 0) {
    const bin = dec.toString(2).padStart(Math.ceil(dec.toString(2).length / 8) * 8, '0')
    const grouped = bin.match(/.{1,4}/g)?.join(' ') || bin
    results.push({ name: 'Binär (gruppiert)', value: grouped })
  }

  // Hex with groups
  if (dec >= 0) {
    const hex = dec.toString(16).toUpperCase().padStart(Math.ceil(dec.toString(16).length / 2) * 2, '0')
    const grouped = hex.match(/.{1,2}/g)?.join(' ') || hex
    results.push({ name: 'Hex (gruppiert)', value: grouped })
  }

  return results
})

function setInput(value, base) {
  inputValue.value = value
  inputBase.value = base
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text)
}

// Common conversions
const presets = [
  { value: '255', base: 10, label: '255 (Max Byte)' },
  { value: '256', base: 10, label: '256' },
  { value: '1024', base: 10, label: '1024 (1KB)' },
  { value: '65535', base: 10, label: '65535 (Max 16-bit)' },
  { value: 'FF', base: 16, label: '0xFF' },
  { value: 'FFFF', base: 16, label: '0xFFFF' },
  { value: '11111111', base: 2, label: '8 Bits' },
]
</script>

<template>
  <div class="space-y-4">
    <!-- Input -->
    <div class="grid grid-cols-3 gap-4">
      <div class="col-span-2">
        <label class="text-sm text-gray-400 mb-1 block">Eingabe</label>
        <input
          v-model="inputValue"
          type="text"
          class="input w-full font-mono text-lg"
          placeholder="Wert eingeben..."
          :class="{ 'border-red-500': !isValidForBase(inputValue, inputBase) }"
        />
      </div>
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Basis</label>
        <select v-model="inputBase" class="input w-full">
          <option v-for="base in bases" :key="base.value" :value="base.value">
            {{ base.name }} ({{ base.value }})
          </option>
        </select>
      </div>
    </div>

    <!-- Validation Error -->
    <div v-if="!isValidForBase(inputValue, inputBase)" class="text-red-400 text-sm">
      Ungültige Zeichen für Basis {{ inputBase }}
    </div>

    <!-- Results -->
    <div class="space-y-2">
      <div
        v-for="conv in conversions"
        :key="conv.value"
        class="flex items-center justify-between p-3 bg-dark-700 rounded-lg"
        :class="{ 'opacity-50': conv.error }"
      >
        <div class="flex-1">
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-400">{{ conv.name }}</span>
            <span class="text-xs text-gray-600">(Basis {{ conv.value }})</span>
          </div>
          <div class="font-mono text-lg text-white">
            <span class="text-gray-500">{{ conv.prefix }}</span>{{ conv.result }}
          </div>
        </div>
        <button
          v-if="!conv.error"
          @click="copyToClipboard(conv.prefix + conv.result)"
          class="px-3 py-1 text-sm text-primary-400 hover:text-primary-300"
        >
          Kopieren
        </button>
      </div>
    </div>

    <!-- Additional Formats -->
    <div v-if="additionalFormats.length > 0" class="space-y-2">
      <h4 class="text-sm text-gray-400">Weitere Formate</h4>
      <div
        v-for="format in additionalFormats"
        :key="format.name"
        class="flex items-center justify-between p-2 bg-dark-800 rounded"
      >
        <div>
          <span class="text-xs text-gray-500">{{ format.name }}</span>
          <div class="font-mono text-white">{{ format.value }}</div>
        </div>
        <button
          @click="copyToClipboard(format.value)"
          class="text-xs text-primary-400 hover:text-primary-300"
        >
          Kopieren
        </button>
      </div>
    </div>

    <!-- Presets -->
    <div>
      <h4 class="text-sm text-gray-400 mb-2">Schnellauswahl</h4>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="preset in presets"
          :key="preset.label"
          @click="setInput(preset.value, preset.base)"
          class="px-3 py-1 text-xs bg-dark-600 hover:bg-dark-500 text-gray-300 rounded"
        >
          {{ preset.label }}
        </button>
      </div>
    </div>
  </div>
</template>
