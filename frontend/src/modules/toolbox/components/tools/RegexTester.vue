<script setup>
import { ref, computed } from 'vue'

const pattern = ref('')
const flags = ref('g')
const testString = ref('The quick brown fox jumps over the lazy dog.\nAnother line with fox here.')
const error = ref('')

const flagOptions = [
  { id: 'g', name: 'Global (g)', desc: 'Alle Treffer finden' },
  { id: 'i', name: 'Case Insensitive (i)', desc: 'Groß-/Kleinschreibung ignorieren' },
  { id: 'm', name: 'Multiline (m)', desc: '^ und $ pro Zeile' },
  { id: 's', name: 'Dotall (s)', desc: '. matcht auch Newlines' },
]

const matches = computed(() => {
  if (!pattern.value || !testString.value) {
    error.value = ''
    return []
  }

  try {
    const regex = new RegExp(pattern.value, flags.value)
    error.value = ''

    const results = []
    let match

    if (flags.value.includes('g')) {
      while ((match = regex.exec(testString.value)) !== null) {
        results.push({
          match: match[0],
          index: match.index,
          groups: match.slice(1),
        })
        if (!match[0]) break // Prevent infinite loop on empty matches
      }
    } else {
      match = regex.exec(testString.value)
      if (match) {
        results.push({
          match: match[0],
          index: match.index,
          groups: match.slice(1),
        })
      }
    }

    return results
  } catch (e) {
    error.value = e.message
    return []
  }
})

const highlightedText = computed(() => {
  if (!pattern.value || !testString.value || error.value) {
    return testString.value
  }

  try {
    const regex = new RegExp(pattern.value, flags.value)
    return testString.value.replace(regex, '<mark class="bg-yellow-500/50 text-white px-0.5 rounded">$&</mark>')
  } catch {
    return testString.value
  }
})

function toggleFlag(flag) {
  if (flags.value.includes(flag)) {
    flags.value = flags.value.replace(flag, '')
  } else {
    flags.value += flag
  }
}

// Common patterns
const commonPatterns = [
  { name: 'E-Mail', pattern: '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}' },
  { name: 'URL', pattern: 'https?:\\/\\/[\\w\\-._~:/?#[\\]@!$&\'()*+,;=%]+' },
  { name: 'IPv4', pattern: '\\b(?:\\d{1,3}\\.){3}\\d{1,3}\\b' },
  { name: 'Telefon (DE)', pattern: '\\+?49[\\s.-]?\\d{3}[\\s.-]?\\d+' },
  { name: 'Datum (DD.MM.YYYY)', pattern: '\\d{2}\\.\\d{2}\\.\\d{4}' },
  { name: 'Hex Color', pattern: '#[0-9A-Fa-f]{6}\\b' },
]

function usePattern(p) {
  pattern.value = p
}
</script>

<template>
  <div class="space-y-4">
    <!-- Pattern Input -->
    <div>
      <label class="text-sm text-gray-400 mb-1 block">Regex Pattern</label>
      <div class="flex gap-2">
        <span class="text-gray-500 self-center">/</span>
        <input
          v-model="pattern"
          type="text"
          class="input flex-1 font-mono"
          placeholder="[a-z]+"
        />
        <span class="text-gray-500 self-center">/</span>
        <input
          v-model="flags"
          type="text"
          class="input w-20 font-mono text-center"
          placeholder="gim"
        />
      </div>
    </div>

    <!-- Flags -->
    <div class="flex flex-wrap gap-2">
      <button
        v-for="flag in flagOptions"
        :key="flag.id"
        @click="toggleFlag(flag.id)"
        class="px-3 py-1 text-sm rounded-lg transition-colors"
        :class="flags.includes(flag.id) ? 'bg-primary-600 text-white' : 'bg-white/[0.04] text-gray-400 hover:text-white'"
        :title="flag.desc"
      >
        {{ flag.name }}
      </button>
    </div>

    <!-- Common Patterns -->
    <div>
      <label class="text-sm text-gray-400 mb-1 block">Häufige Patterns</label>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="p in commonPatterns"
          :key="p.name"
          @click="usePattern(p.pattern)"
          class="px-2 py-1 text-xs bg-white/[0.04] text-gray-300 rounded hover:bg-white/[0.04]"
        >
          {{ p.name }}
        </button>
      </div>
    </div>

    <!-- Test String -->
    <div>
      <label class="text-sm text-gray-400 mb-1 block">Test String</label>
      <textarea
        v-model="testString"
        class="input w-full h-32 font-mono text-sm"
        placeholder="Text zum Testen eingeben..."
      ></textarea>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-3 bg-red-900/30 border border-red-700 rounded-lg text-red-400 text-sm">
      {{ error }}
    </div>

    <!-- Results -->
    <div class="grid md:grid-cols-2 gap-4">
      <!-- Highlighted Text -->
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Hervorgehobener Text</label>
        <div
          class="p-3 bg-white/[0.02] rounded-lg text-sm font-mono whitespace-pre-wrap break-all min-h-[100px]"
          v-html="highlightedText"
        ></div>
      </div>

      <!-- Matches -->
      <div>
        <label class="text-sm text-gray-400 mb-1 block">
          Treffer ({{ matches.length }})
        </label>
        <div class="bg-white/[0.02] rounded-lg p-3 min-h-[100px] max-h-60 overflow-auto">
          <div v-if="matches.length === 0" class="text-gray-500 text-sm">
            Keine Treffer
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="(m, i) in matches"
              :key="i"
              class="p-2 bg-white/[0.04] rounded text-sm"
            >
              <div class="flex justify-between">
                <span class="font-mono text-green-400">"{{ m.match }}"</span>
                <span class="text-gray-500">Index: {{ m.index }}</span>
              </div>
              <div v-if="m.groups.length > 0" class="mt-1 text-xs text-gray-400">
                Groups: {{ m.groups.join(', ') }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
