<script setup>
import { ref, computed } from 'vue'

const textA = ref('')
const textB = ref('')
const viewMode = ref('split') // 'split', 'unified'
const ignoreWhitespace = ref(false)
const ignoreCase = ref(false)

// Simple diff algorithm (line-by-line)
function computeDiff(a, b) {
  const linesA = a.split('\n')
  const linesB = b.split('\n')

  const normalize = (line) => {
    let normalized = line
    if (ignoreWhitespace.value) {
      normalized = normalized.replace(/\s+/g, ' ').trim()
    }
    if (ignoreCase.value) {
      normalized = normalized.toLowerCase()
    }
    return normalized
  }

  // Simple LCS-based diff
  const m = linesA.length
  const n = linesB.length

  // Build LCS table
  const dp = Array(m + 1).fill(null).map(() => Array(n + 1).fill(0))

  for (let i = 1; i <= m; i++) {
    for (let j = 1; j <= n; j++) {
      if (normalize(linesA[i - 1]) === normalize(linesB[j - 1])) {
        dp[i][j] = dp[i - 1][j - 1] + 1
      } else {
        dp[i][j] = Math.max(dp[i - 1][j], dp[i][j - 1])
      }
    }
  }

  // Backtrack to find diff
  const diff = []
  let i = m
  let j = n

  while (i > 0 || j > 0) {
    if (i > 0 && j > 0 && normalize(linesA[i - 1]) === normalize(linesB[j - 1])) {
      diff.unshift({ type: 'same', lineA: i, lineB: j, textA: linesA[i - 1], textB: linesB[j - 1] })
      i--
      j--
    } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
      diff.unshift({ type: 'add', lineB: j, textB: linesB[j - 1] })
      j--
    } else if (i > 0) {
      diff.unshift({ type: 'remove', lineA: i, textA: linesA[i - 1] })
      i--
    }
  }

  return diff
}

const diffResult = computed(() => {
  if (!textA.value && !textB.value) return []
  return computeDiff(textA.value, textB.value)
})

const stats = computed(() => {
  const added = diffResult.value.filter(d => d.type === 'add').length
  const removed = diffResult.value.filter(d => d.type === 'remove').length
  const same = diffResult.value.filter(d => d.type === 'same').length
  return { added, removed, same }
})

const hasChanges = computed(() => {
  return stats.value.added > 0 || stats.value.removed > 0
})

function loadSample() {
  textA.value = `function greet(name) {
  console.log("Hello, " + name);
  return true;
}

const message = "Welcome";
greet(message);`

  textB.value = `function greet(name, greeting = "Hello") {
  console.log(greeting + ", " + name + "!");
  return true;
}

const message = "Welcome";
const user = "World";
greet(user, message);`
}

function clearAll() {
  textA.value = ''
  textB.value = ''
}

function swapTexts() {
  const temp = textA.value
  textA.value = textB.value
  textB.value = temp
}
</script>

<template>
  <div class="space-y-4">
    <!-- Controls -->
    <div class="flex flex-wrap gap-4 items-center justify-between">
      <div class="flex gap-2">
        <button
          @click="viewMode = 'split'"
          class="px-3 py-1 text-sm rounded transition-colors"
          :class="viewMode === 'split' ? 'bg-primary-600 text-white' : 'bg-white/[0.04] text-gray-400'"
        >
          Split
        </button>
        <button
          @click="viewMode = 'unified'"
          class="px-3 py-1 text-sm rounded transition-colors"
          :class="viewMode === 'unified' ? 'bg-primary-600 text-white' : 'bg-white/[0.04] text-gray-400'"
        >
          Unified
        </button>
      </div>

      <div class="flex gap-4">
        <label class="flex items-center gap-2 text-sm text-gray-400">
          <input type="checkbox" v-model="ignoreWhitespace" class="rounded bg-white/[0.04] border-white/[0.06]" />
          Leerzeichen ignorieren
        </label>
        <label class="flex items-center gap-2 text-sm text-gray-400">
          <input type="checkbox" v-model="ignoreCase" class="rounded bg-white/[0.04] border-white/[0.06]" />
          Groß/Klein ignorieren
        </label>
      </div>

      <div class="flex gap-2">
        <button @click="loadSample" class="text-xs text-primary-400 hover:text-primary-300">
          Beispiel
        </button>
        <button @click="swapTexts" class="text-xs text-gray-400 hover:text-white">
          Tauschen
        </button>
        <button @click="clearAll" class="text-xs text-gray-400 hover:text-white">
          Löschen
        </button>
      </div>
    </div>

    <!-- Input Areas -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Original (A)</label>
        <textarea
          v-model="textA"
          class="input w-full h-40 font-mono text-sm"
          placeholder="Originaltext hier eingeben..."
        ></textarea>
      </div>
      <div>
        <label class="text-sm text-gray-400 mb-1 block">Geändert (B)</label>
        <textarea
          v-model="textB"
          class="input w-full h-40 font-mono text-sm"
          placeholder="Geänderten Text hier eingeben..."
        ></textarea>
      </div>
    </div>

    <!-- Stats -->
    <div v-if="diffResult.length > 0" class="flex gap-4 text-sm">
      <span class="text-green-400">+{{ stats.added }} hinzugefügt</span>
      <span class="text-red-400">-{{ stats.removed }} entfernt</span>
      <span class="text-gray-400">{{ stats.same }} unverändert</span>
    </div>

    <!-- Diff Result -->
    <div v-if="diffResult.length > 0" class="bg-white/[0.02] rounded-lg overflow-hidden border border-white/[0.06]">
      <!-- Split View -->
      <div v-if="viewMode === 'split'" class="grid grid-cols-2 divide-x divide-white/[0.06] max-h-96 overflow-auto">
        <!-- Left (Original) -->
        <div class="font-mono text-sm">
          <template v-for="(line, idx) in diffResult" :key="'a-' + idx">
            <div
              v-if="line.type !== 'add'"
              class="flex"
              :class="{
                'bg-red-900/30': line.type === 'remove',
              }"
            >
              <span class="w-10 text-right pr-2 text-gray-600 select-none border-r border-white/[0.06] flex-shrink-0">
                {{ line.lineA || '' }}
              </span>
              <span
                class="pl-2 whitespace-pre flex-1"
                :class="line.type === 'remove' ? 'text-red-400' : 'text-gray-300'"
              >{{ line.textA ?? '' }}</span>
            </div>
            <div v-else class="flex bg-white/[0.04]">
              <span class="w-10 text-right pr-2 text-gray-600 select-none border-r border-white/[0.06] flex-shrink-0"></span>
              <span class="pl-2 whitespace-pre flex-1"></span>
            </div>
          </template>
        </div>

        <!-- Right (Changed) -->
        <div class="font-mono text-sm">
          <template v-for="(line, idx) in diffResult" :key="'b-' + idx">
            <div
              v-if="line.type !== 'remove'"
              class="flex"
              :class="{
                'bg-green-900/30': line.type === 'add',
              }"
            >
              <span class="w-10 text-right pr-2 text-gray-600 select-none border-r border-white/[0.06] flex-shrink-0">
                {{ line.lineB || '' }}
              </span>
              <span
                class="pl-2 whitespace-pre flex-1"
                :class="line.type === 'add' ? 'text-green-400' : 'text-gray-300'"
              >{{ line.textB ?? '' }}</span>
            </div>
            <div v-else class="flex bg-white/[0.04]">
              <span class="w-10 text-right pr-2 text-gray-600 select-none border-r border-white/[0.06] flex-shrink-0"></span>
              <span class="pl-2 whitespace-pre flex-1"></span>
            </div>
          </template>
        </div>
      </div>

      <!-- Unified View -->
      <div v-else class="font-mono text-sm max-h-96 overflow-auto">
        <div
          v-for="(line, idx) in diffResult"
          :key="idx"
          class="flex"
          :class="{
            'bg-green-900/30': line.type === 'add',
            'bg-red-900/30': line.type === 'remove',
          }"
        >
          <span class="w-6 text-center text-gray-600 select-none flex-shrink-0">
            {{ line.type === 'add' ? '+' : line.type === 'remove' ? '-' : ' ' }}
          </span>
          <span class="w-10 text-right pr-2 text-gray-600 select-none border-r border-white/[0.06] flex-shrink-0">
            {{ line.lineA || line.lineB || '' }}
          </span>
          <span
            class="pl-2 whitespace-pre flex-1"
            :class="{
              'text-green-400': line.type === 'add',
              'text-red-400': line.type === 'remove',
              'text-gray-300': line.type === 'same',
            }"
          >{{ line.type === 'add' ? line.textB : line.textA }}</span>
        </div>
      </div>
    </div>

    <!-- No changes message -->
    <div
      v-else-if="textA || textB"
      class="text-center py-8 text-gray-400"
    >
      <template v-if="textA === textB">
        Die Texte sind identisch
      </template>
      <template v-else>
        Gib Text in beide Felder ein, um den Vergleich zu sehen
      </template>
    </div>
  </div>
</template>
