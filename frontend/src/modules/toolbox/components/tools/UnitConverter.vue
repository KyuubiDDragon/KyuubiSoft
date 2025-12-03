<script setup>
import { ref, computed } from 'vue'

const categories = [
  {
    id: 'length',
    name: 'Länge',
    icon: '📏',
    units: [
      { id: 'mm', name: 'Millimeter', factor: 0.001 },
      { id: 'cm', name: 'Zentimeter', factor: 0.01 },
      { id: 'm', name: 'Meter', factor: 1 },
      { id: 'km', name: 'Kilometer', factor: 1000 },
      { id: 'in', name: 'Zoll (inch)', factor: 0.0254 },
      { id: 'ft', name: 'Fuß (feet)', factor: 0.3048 },
      { id: 'yd', name: 'Yard', factor: 0.9144 },
      { id: 'mi', name: 'Meile', factor: 1609.344 },
    ],
  },
  {
    id: 'weight',
    name: 'Gewicht',
    icon: '⚖️',
    units: [
      { id: 'mg', name: 'Milligramm', factor: 0.000001 },
      { id: 'g', name: 'Gramm', factor: 0.001 },
      { id: 'kg', name: 'Kilogramm', factor: 1 },
      { id: 't', name: 'Tonne', factor: 1000 },
      { id: 'oz', name: 'Unze', factor: 0.0283495 },
      { id: 'lb', name: 'Pfund', factor: 0.453592 },
    ],
  },
  {
    id: 'temperature',
    name: 'Temperatur',
    icon: '🌡️',
    units: [
      { id: 'c', name: 'Celsius' },
      { id: 'f', name: 'Fahrenheit' },
      { id: 'k', name: 'Kelvin' },
    ],
    special: true,
  },
  {
    id: 'area',
    name: 'Fläche',
    icon: '📐',
    units: [
      { id: 'mm2', name: 'mm²', factor: 0.000001 },
      { id: 'cm2', name: 'cm²', factor: 0.0001 },
      { id: 'm2', name: 'm²', factor: 1 },
      { id: 'km2', name: 'km²', factor: 1000000 },
      { id: 'ha', name: 'Hektar', factor: 10000 },
      { id: 'ac', name: 'Acre', factor: 4046.86 },
      { id: 'sqft', name: 'sq ft', factor: 0.092903 },
    ],
  },
  {
    id: 'volume',
    name: 'Volumen',
    icon: '🧊',
    units: [
      { id: 'ml', name: 'Milliliter', factor: 0.001 },
      { id: 'l', name: 'Liter', factor: 1 },
      { id: 'm3', name: 'Kubikmeter', factor: 1000 },
      { id: 'gal', name: 'Gallone (US)', factor: 3.78541 },
      { id: 'qt', name: 'Quart (US)', factor: 0.946353 },
      { id: 'pt', name: 'Pint (US)', factor: 0.473176 },
      { id: 'cup', name: 'Cup (US)', factor: 0.236588 },
      { id: 'floz', name: 'fl oz (US)', factor: 0.0295735 },
    ],
  },
  {
    id: 'time',
    name: 'Zeit',
    icon: '⏱️',
    units: [
      { id: 'ms', name: 'Millisekunden', factor: 0.001 },
      { id: 's', name: 'Sekunden', factor: 1 },
      { id: 'min', name: 'Minuten', factor: 60 },
      { id: 'h', name: 'Stunden', factor: 3600 },
      { id: 'd', name: 'Tage', factor: 86400 },
      { id: 'w', name: 'Wochen', factor: 604800 },
      { id: 'mo', name: 'Monate (30d)', factor: 2592000 },
      { id: 'y', name: 'Jahre (365d)', factor: 31536000 },
    ],
  },
  {
    id: 'data',
    name: 'Daten',
    icon: '💾',
    units: [
      { id: 'b', name: 'Bytes', factor: 1 },
      { id: 'kb', name: 'Kilobytes', factor: 1024 },
      { id: 'mb', name: 'Megabytes', factor: 1048576 },
      { id: 'gb', name: 'Gigabytes', factor: 1073741824 },
      { id: 'tb', name: 'Terabytes', factor: 1099511627776 },
    ],
  },
  {
    id: 'speed',
    name: 'Geschwindigkeit',
    icon: '🚀',
    units: [
      { id: 'mps', name: 'm/s', factor: 1 },
      { id: 'kmh', name: 'km/h', factor: 0.277778 },
      { id: 'mph', name: 'mph', factor: 0.44704 },
      { id: 'kn', name: 'Knoten', factor: 0.514444 },
    ],
  },
]

const selectedCategory = ref(categories[0])
const inputValue = ref(1)
const fromUnit = ref(categories[0].units[2]) // meter
const toUnit = ref(categories[0].units[3]) // kilometer

// Temperature conversion functions
function convertTemperature(value, from, to) {
  // First convert to Celsius
  let celsius
  switch (from) {
    case 'c':
      celsius = value
      break
    case 'f':
      celsius = (value - 32) * 5 / 9
      break
    case 'k':
      celsius = value - 273.15
      break
    default:
      celsius = value
  }

  // Then convert from Celsius to target
  switch (to) {
    case 'c':
      return celsius
    case 'f':
      return celsius * 9 / 5 + 32
    case 'k':
      return celsius + 273.15
    default:
      return celsius
  }
}

const result = computed(() => {
  if (inputValue.value === '' || isNaN(inputValue.value)) {
    return ''
  }

  const value = parseFloat(inputValue.value)

  if (selectedCategory.value.special) {
    // Temperature
    return convertTemperature(value, fromUnit.value.id, toUnit.value.id)
  }

  // Standard conversion: value * fromFactor / toFactor
  const baseValue = value * fromUnit.value.factor
  return baseValue / toUnit.value.factor
})

const formattedResult = computed(() => {
  if (result.value === '') return ''

  const num = parseFloat(result.value)

  // Format based on size
  if (Math.abs(num) < 0.0001 || Math.abs(num) >= 1000000) {
    return num.toExponential(6)
  }

  // Round to reasonable precision
  const precision = Math.abs(num) < 1 ? 6 : 4
  return parseFloat(num.toPrecision(precision)).toString()
})

function selectCategory(category) {
  selectedCategory.value = category
  fromUnit.value = category.units[0]
  toUnit.value = category.units[1]
  inputValue.value = 1
}

function swapUnits() {
  const temp = fromUnit.value
  fromUnit.value = toUnit.value
  toUnit.value = temp
}
</script>

<template>
  <div class="space-y-4">
    <!-- Category Selection -->
    <div class="flex flex-wrap gap-2">
      <button
        v-for="cat in categories"
        :key="cat.id"
        @click="selectCategory(cat)"
        class="px-3 py-2 rounded-lg text-sm transition-colors"
        :class="selectedCategory.id === cat.id
          ? 'bg-primary-600 text-white'
          : 'bg-dark-700 text-gray-400 hover:text-white'"
      >
        <span class="mr-1">{{ cat.icon }}</span>
        {{ cat.name }}
      </button>
    </div>

    <!-- Converter -->
    <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-4 items-end">
      <!-- From -->
      <div class="space-y-2">
        <label class="text-sm text-gray-400">Von</label>
        <input
          v-model="inputValue"
          type="number"
          step="any"
          class="input w-full text-lg font-mono"
        />
        <select
          v-model="fromUnit"
          class="input w-full"
        >
          <option
            v-for="unit in selectedCategory.units"
            :key="unit.id"
            :value="unit"
          >
            {{ unit.name }}
          </option>
        </select>
      </div>

      <!-- Swap Button -->
      <button
        @click="swapUnits"
        class="p-3 bg-dark-700 hover:bg-dark-600 rounded-lg transition-colors self-center mb-6 md:mb-0"
        title="Einheiten tauschen"
      >
        ⇄
      </button>

      <!-- To -->
      <div class="space-y-2">
        <label class="text-sm text-gray-400">Zu</label>
        <div class="input w-full text-lg font-mono bg-dark-900 min-h-[42px] flex items-center">
          {{ formattedResult || '0' }}
        </div>
        <select
          v-model="toUnit"
          class="input w-full"
        >
          <option
            v-for="unit in selectedCategory.units"
            :key="unit.id"
            :value="unit"
          >
            {{ unit.name }}
          </option>
        </select>
      </div>
    </div>

    <!-- Formula Display -->
    <div class="p-3 bg-dark-700 rounded-lg text-sm text-gray-400 text-center">
      {{ inputValue }} {{ fromUnit.name }} = {{ formattedResult }} {{ toUnit.name }}
    </div>

    <!-- Quick Reference for current category -->
    <div class="text-xs text-gray-500">
      <p class="font-medium mb-2">Alle Einheiten ({{ selectedCategory.name }}):</p>
      <div class="flex flex-wrap gap-2">
        <span
          v-for="unit in selectedCategory.units"
          :key="unit.id"
          class="px-2 py-1 bg-dark-800 rounded"
        >
          {{ unit.name }}
        </span>
      </div>
    </div>
  </div>
</template>
