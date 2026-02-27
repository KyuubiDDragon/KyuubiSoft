<script setup>
import { ref } from 'vue'
import api from '@/core/api/axios'

const url = ref('')
const isLoading = ref(false)
const result = ref(null)
const error = ref('')

async function check() {
  if (!url.value.trim()) return

  isLoading.value = true
  error.value = ''
  result.value = null

  try {
    const response = await api.get('/api/v1/tools/security-headers', {
      params: { url: url.value.trim() },
    })

    if (response.data.data) {
      result.value = response.data.data
    }
  } catch (e) {
    error.value = e.response?.data?.error || e.message || 'Security Headers Check fehlgeschlagen'
  }

  isLoading.value = false
}

function getStatusClass(check) {
  if (!check.present) return 'bg-red-900/30 border-red-500/30'
  if (!check.valid) return 'bg-yellow-900/30 border-yellow-500/30'
  return 'bg-green-900/30 border-green-500/30'
}

function getStatusIcon(check) {
  if (!check.present) return '✗'
  if (!check.valid) return '!'
  return '✓'
}

function getStatusColor(check) {
  if (!check.present) return 'text-red-400'
  if (!check.valid) return 'text-yellow-400'
  return 'text-green-400'
}

function getGradeColor(grade) {
  if (grade.startsWith('A')) return 'text-green-400 border-green-500'
  if (grade === 'B') return 'text-blue-400 border-blue-500'
  if (grade === 'C') return 'text-yellow-400 border-yellow-500'
  if (grade === 'D') return 'text-orange-400 border-orange-500'
  return 'text-red-400 border-red-500'
}

const quickUrls = ['google.com', 'github.com', 'cloudflare.com']
</script>

<template>
  <div class="space-y-4">
    <!-- Input -->
    <div class="flex gap-2">
      <input
        v-model="url"
        type="text"
        @keyup.enter="check"
        class="input flex-1"
        placeholder="example.com oder https://example.com"
      />
      <button
        @click="check"
        :disabled="isLoading || !url.trim()"
        class="btn-primary px-6"
      >
        {{ isLoading ? 'Prüfe...' : 'Prüfen' }}
      </button>
    </div>

    <!-- Quick URLs -->
    <div class="flex gap-2 flex-wrap">
      <span class="text-xs text-gray-500">Beispiele:</span>
      <button
        v-for="u in quickUrls"
        :key="u"
        @click="url = u"
        class="text-xs text-primary-400 hover:text-primary-300"
      >
        {{ u }}
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="text-center py-8 text-gray-400">
      Prüfe Security Headers...
    </div>

    <!-- Error -->
    <div v-if="error" class="p-4 bg-red-900/30 border border-red-500/30 rounded-lg text-red-400 text-sm">
      {{ error }}
    </div>

    <!-- Results -->
    <div v-if="result" class="space-y-4">
      <!-- Score Header -->
      <div class="flex items-center justify-between p-4 bg-white/[0.04] rounded-lg">
        <div>
          <h3 class="text-lg font-medium text-white">{{ result.url }}</h3>
          <p class="text-sm text-gray-400">
            Score: {{ result.score.earned }}/{{ result.score.max }} ({{ result.score.percentage }}%)
          </p>
        </div>
        <div
          class="w-16 h-16 rounded-full flex items-center justify-center text-2xl font-bold border-4"
          :class="getGradeColor(result.score.grade)"
        >
          {{ result.score.grade }}
        </div>
      </div>

      <!-- Security Headers Checks -->
      <div class="space-y-2">
        <h4 class="text-sm text-gray-400 mb-3">Security Headers</h4>

        <div
          v-for="(check, key) in result.checks"
          :key="key"
          class="p-3 rounded-lg border"
          :class="getStatusClass(check)"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span :class="getStatusColor(check)" class="font-medium">
                  {{ getStatusIcon(check) }}
                </span>
                <span class="text-white font-medium">{{ check.name }}</span>
                <span v-if="check.weight > 0" class="text-xs text-gray-500">
                  ({{ check.weight }} Punkte)
                </span>
              </div>
              <p class="text-xs text-gray-400 mt-1">{{ check.description }}</p>

              <div v-if="check.value" class="mt-2">
                <code class="text-xs text-gray-300 bg-white/[0.02] px-2 py-1 rounded block break-all">
                  {{ check.value }}
                </code>
              </div>

              <div v-if="check.recommendation" class="mt-2 text-xs text-yellow-400">
                Empfehlung: {{ check.recommendation }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Summary -->
      <div class="p-4 bg-white/[0.04] rounded-lg">
        <h4 class="text-sm text-gray-400 mb-2">Zusammenfassung</h4>
        <div class="grid grid-cols-3 gap-4 text-center">
          <div>
            <div class="text-2xl font-bold text-green-400">
              {{ Object.values(result.checks).filter(c => c.present && c.valid).length }}
            </div>
            <div class="text-xs text-gray-500">Konfiguriert</div>
          </div>
          <div>
            <div class="text-2xl font-bold text-yellow-400">
              {{ Object.values(result.checks).filter(c => c.present && !c.valid).length }}
            </div>
            <div class="text-xs text-gray-500">Verbesserbar</div>
          </div>
          <div>
            <div class="text-2xl font-bold text-red-400">
              {{ Object.values(result.checks).filter(c => !c.present && c.weight > 0).length }}
            </div>
            <div class="text-xs text-gray-500">Fehlend</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Info -->
    <div class="text-xs text-gray-500 space-y-1">
      <p><strong>Security Headers</strong> sind HTTP-Header, die die Sicherheit einer Website verbessern.</p>
      <p>Ein guter Score bedeutet, dass grundlegende Sicherheitsmaßnahmen implementiert sind.</p>
    </div>
  </div>
</template>
