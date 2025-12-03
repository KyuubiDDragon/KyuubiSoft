<script setup>
import { ref, computed, onMounted } from 'vue'
import { DocumentDuplicateIcon, CheckIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const password = ref('')
const length = ref(16)
const includeUppercase = ref(true)
const includeLowercase = ref(true)
const includeNumbers = ref(true)
const includeSymbols = ref(true)
const excludeAmbiguous = ref(false)
const copied = ref(false)

const charsets = {
  uppercase: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
  lowercase: 'abcdefghijklmnopqrstuvwxyz',
  numbers: '0123456789',
  symbols: '!@#$%^&*()_+-=[]{}|;:,.<>?',
  ambiguous: 'O0l1I',
}

const strength = computed(() => {
  if (!password.value) return { level: 0, text: '', color: '' }

  let score = 0
  const len = password.value.length

  if (len >= 8) score += 1
  if (len >= 12) score += 1
  if (len >= 16) score += 1
  if (/[a-z]/.test(password.value)) score += 1
  if (/[A-Z]/.test(password.value)) score += 1
  if (/[0-9]/.test(password.value)) score += 1
  if (/[^a-zA-Z0-9]/.test(password.value)) score += 1

  if (score <= 2) return { level: 1, text: 'Schwach', color: 'bg-red-500' }
  if (score <= 4) return { level: 2, text: 'Mittel', color: 'bg-yellow-500' }
  if (score <= 5) return { level: 3, text: 'Stark', color: 'bg-blue-500' }
  return { level: 4, text: 'Sehr Stark', color: 'bg-green-500' }
})

function generatePassword() {
  let charset = ''

  if (includeUppercase.value) charset += charsets.uppercase
  if (includeLowercase.value) charset += charsets.lowercase
  if (includeNumbers.value) charset += charsets.numbers
  if (includeSymbols.value) charset += charsets.symbols

  if (excludeAmbiguous.value) {
    for (const char of charsets.ambiguous) {
      charset = charset.replace(new RegExp(char, 'g'), '')
    }
  }

  if (!charset) {
    password.value = 'Mindestens ein Zeichensatz erforderlich'
    return
  }

  let result = ''
  const array = new Uint32Array(length.value)
  crypto.getRandomValues(array)

  for (let i = 0; i < length.value; i++) {
    result += charset[array[i] % charset.length]
  }

  password.value = result
}

async function copyPassword() {
  if (password.value) {
    await navigator.clipboard.writeText(password.value)
    copied.value = true
    setTimeout(() => copied.value = false, 2000)
  }
}

onMounted(() => {
  generatePassword()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Password Display -->
    <div class="relative">
      <div class="flex items-center gap-2 p-4 bg-dark-900 rounded-lg border border-dark-600">
        <input
          v-model="password"
          type="text"
          readonly
          class="flex-1 bg-transparent text-lg font-mono text-white focus:outline-none"
        />
        <button @click="generatePassword" class="btn-icon" title="Neu generieren">
          <ArrowPathIcon class="w-5 h-5" />
        </button>
        <button @click="copyPassword" class="btn-icon" :title="copied ? 'Kopiert!' : 'Kopieren'">
          <CheckIcon v-if="copied" class="w-5 h-5 text-green-400" />
          <DocumentDuplicateIcon v-else class="w-5 h-5" />
        </button>
      </div>

      <!-- Strength Indicator -->
      <div class="mt-2 flex items-center gap-2">
        <div class="flex-1 h-2 bg-dark-700 rounded-full overflow-hidden">
          <div
            class="h-full transition-all duration-300"
            :class="strength.color"
            :style="{ width: (strength.level / 4) * 100 + '%' }"
          ></div>
        </div>
        <span class="text-sm text-gray-400">{{ strength.text }}</span>
      </div>
    </div>

    <!-- Options -->
    <div class="space-y-4">
      <!-- Length Slider -->
      <div>
        <div class="flex justify-between mb-2">
          <label class="text-sm text-gray-400">Länge</label>
          <span class="text-sm font-medium text-white">{{ length }}</span>
        </div>
        <input
          v-model="length"
          type="range"
          min="4"
          max="64"
          class="w-full accent-primary-500"
          @input="generatePassword"
        />
        <div class="flex justify-between text-xs text-gray-500 mt-1">
          <span>4</span>
          <span>64</span>
        </div>
      </div>

      <!-- Character Options -->
      <div class="grid grid-cols-2 gap-3">
        <label class="flex items-center gap-2 cursor-pointer">
          <input
            v-model="includeUppercase"
            type="checkbox"
            class="w-4 h-4 rounded border-gray-600 text-primary-500 focus:ring-primary-500"
            @change="generatePassword"
          />
          <span class="text-sm text-gray-300">Großbuchstaben (A-Z)</span>
        </label>

        <label class="flex items-center gap-2 cursor-pointer">
          <input
            v-model="includeLowercase"
            type="checkbox"
            class="w-4 h-4 rounded border-gray-600 text-primary-500 focus:ring-primary-500"
            @change="generatePassword"
          />
          <span class="text-sm text-gray-300">Kleinbuchstaben (a-z)</span>
        </label>

        <label class="flex items-center gap-2 cursor-pointer">
          <input
            v-model="includeNumbers"
            type="checkbox"
            class="w-4 h-4 rounded border-gray-600 text-primary-500 focus:ring-primary-500"
            @change="generatePassword"
          />
          <span class="text-sm text-gray-300">Zahlen (0-9)</span>
        </label>

        <label class="flex items-center gap-2 cursor-pointer">
          <input
            v-model="includeSymbols"
            type="checkbox"
            class="w-4 h-4 rounded border-gray-600 text-primary-500 focus:ring-primary-500"
            @change="generatePassword"
          />
          <span class="text-sm text-gray-300">Symbole (!@#$%...)</span>
        </label>

        <label class="flex items-center gap-2 cursor-pointer col-span-2">
          <input
            v-model="excludeAmbiguous"
            type="checkbox"
            class="w-4 h-4 rounded border-gray-600 text-primary-500 focus:ring-primary-500"
            @change="generatePassword"
          />
          <span class="text-sm text-gray-300">Ähnliche Zeichen ausschließen (O, 0, l, 1, I)</span>
        </label>
      </div>
    </div>

    <!-- Generate Button -->
    <button @click="generatePassword" class="btn-primary w-full">
      <ArrowPathIcon class="w-5 h-5 mr-2" />
      Neues Passwort generieren
    </button>
  </div>
</template>
