<script setup>
import { ref } from 'vue'

const domain = ref('')
const isLoading = ref(false)
const result = ref(null)
const error = ref('')

async function checkSSL() {
  if (!domain.value.trim()) return

  isLoading.value = true
  error.value = ''
  result.value = null

  // Clean domain input
  let cleanDomain = domain.value.trim()
    .replace(/^https?:\/\//, '')
    .replace(/\/.*$/, '')
    .replace(/:\d+$/, '')

  try {
    // Use a public API to check SSL
    const response = await fetch(`https://ssl-checker.io/api/v1/check/${cleanDomain}`)

    if (!response.ok) {
      throw new Error('Konnte SSL-Informationen nicht abrufen')
    }

    const data = await response.json()
    result.value = data
  } catch (e) {
    // Fallback: Try to get basic info via fetch
    try {
      // We can at least check if the site is reachable via HTTPS
      const testUrl = `https://${cleanDomain}`
      const testResponse = await fetch(testUrl, {
        method: 'HEAD',
        mode: 'no-cors',
      })

      result.value = {
        domain: cleanDomain,
        valid: true,
        note: 'HTTPS erreichbar (Details benötigen Server-Side Check)',
        checkedAt: new Date().toISOString(),
      }
    } catch (fetchError) {
      error.value = 'SSL-Prüfung fehlgeschlagen. Domain möglicherweise nicht erreichbar oder CORS-Einschränkungen.'
    }
  }

  isLoading.value = false
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function getDaysUntilExpiry(dateStr) {
  if (!dateStr) return null
  const expiry = new Date(dateStr)
  const now = new Date()
  const diff = expiry - now
  return Math.ceil(diff / (1000 * 60 * 60 * 24))
}

function getExpiryStatus(dateStr) {
  const days = getDaysUntilExpiry(dateStr)
  if (days === null) return { class: 'text-gray-400', text: 'Unbekannt' }
  if (days < 0) return { class: 'text-red-500', text: 'Abgelaufen' }
  if (days < 7) return { class: 'text-red-400', text: `${days} Tage (Kritisch)` }
  if (days < 30) return { class: 'text-yellow-400', text: `${days} Tage (Warnung)` }
  return { class: 'text-green-400', text: `${days} Tage` }
}

// Common domains for quick test
const quickDomains = [
  'google.com',
  'github.com',
  'cloudflare.com',
]
</script>

<template>
  <div class="space-y-4">
    <!-- Input -->
    <div class="flex gap-2">
      <input
        v-model="domain"
        type="text"
        @keyup.enter="checkSSL"
        class="input flex-1"
        placeholder="example.com"
      />
      <button
        @click="checkSSL"
        :disabled="isLoading || !domain.trim()"
        class="btn-primary px-6"
      >
        {{ isLoading ? 'Prüfe...' : 'Prüfen' }}
      </button>
    </div>

    <!-- Quick domains -->
    <div class="flex gap-2 flex-wrap">
      <span class="text-xs text-gray-500">Schnelltest:</span>
      <button
        v-for="d in quickDomains"
        :key="d"
        @click="domain = d; checkSSL()"
        class="text-xs text-primary-400 hover:text-primary-300"
      >
        {{ d }}
      </button>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-4 bg-red-900/30 border border-red-500 rounded-lg text-red-400">
      {{ error }}
    </div>

    <!-- Results -->
    <div v-if="result" class="space-y-4">
      <!-- Status Badge -->
      <div class="flex items-center gap-3">
        <div
          class="w-12 h-12 rounded-full flex items-center justify-center text-2xl"
          :class="result.valid ? 'bg-green-900/30' : 'bg-red-900/30'"
        >
          {{ result.valid ? '🔒' : '⚠️' }}
        </div>
        <div>
          <h3 class="text-lg font-medium text-white">{{ result.domain || domain }}</h3>
          <p :class="result.valid ? 'text-green-400' : 'text-red-400'">
            {{ result.valid ? 'SSL-Zertifikat gültig' : 'SSL-Problem erkannt' }}
          </p>
        </div>
      </div>

      <!-- Certificate Details -->
      <div class="grid grid-cols-2 gap-3" v-if="result.certificate">
        <div class="p-3 bg-dark-700 rounded-lg">
          <span class="text-xs text-gray-500">Aussteller</span>
          <div class="text-white">{{ result.certificate.issuer || '-' }}</div>
        </div>
        <div class="p-3 bg-dark-700 rounded-lg">
          <span class="text-xs text-gray-500">Gültig ab</span>
          <div class="text-white">{{ formatDate(result.certificate.valid_from) }}</div>
        </div>
        <div class="p-3 bg-dark-700 rounded-lg">
          <span class="text-xs text-gray-500">Gültig bis</span>
          <div class="text-white">{{ formatDate(result.certificate.valid_to) }}</div>
        </div>
        <div class="p-3 bg-dark-700 rounded-lg">
          <span class="text-xs text-gray-500">Verbleibende Zeit</span>
          <div :class="getExpiryStatus(result.certificate.valid_to).class">
            {{ getExpiryStatus(result.certificate.valid_to).text }}
          </div>
        </div>
      </div>

      <!-- Additional info if available -->
      <div v-if="result.certificate?.subject" class="p-3 bg-dark-800 rounded-lg">
        <span class="text-xs text-gray-500">Subject</span>
        <div class="text-sm text-gray-300 font-mono">{{ result.certificate.subject }}</div>
      </div>

      <div v-if="result.certificate?.san" class="p-3 bg-dark-800 rounded-lg">
        <span class="text-xs text-gray-500">Alternative Namen (SAN)</span>
        <div class="flex flex-wrap gap-1 mt-1">
          <span
            v-for="name in (Array.isArray(result.certificate.san) ? result.certificate.san : [result.certificate.san])"
            :key="name"
            class="px-2 py-0.5 text-xs bg-dark-600 text-gray-300 rounded"
          >
            {{ name }}
          </span>
        </div>
      </div>

      <!-- Note for limited results -->
      <div v-if="result.note" class="p-3 bg-blue-900/20 border border-blue-500/30 rounded-lg text-sm text-blue-400">
        {{ result.note }}
      </div>
    </div>

    <!-- Info -->
    <div class="text-xs text-gray-500 space-y-1">
      <p class="font-medium">Hinweis:</p>
      <p>Dieser Check prüft das SSL/TLS-Zertifikat einer Domain auf Gültigkeit und zeigt Details wie Aussteller und Ablaufdatum an.</p>
      <p>Für detaillierte Analysen empfehlen wir Tools wie SSL Labs oder Qualys.</p>
    </div>
  </div>
</template>
