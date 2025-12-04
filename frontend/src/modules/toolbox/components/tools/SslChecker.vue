<script setup>
import { ref } from 'vue'
import api from '@/core/api/axios'

const domain = ref('')
const port = ref(443)
const isLoading = ref(false)
const result = ref(null)
const error = ref('')

async function checkSSL() {
  if (!domain.value.trim()) return

  isLoading.value = true
  error.value = ''
  result.value = null

  try {
    const response = await api.get('/api/v1/tools/ssl-check', {
      params: {
        domain: domain.value.trim(),
        port: port.value,
      },
    })

    if (response.data.data) {
      result.value = response.data.data
    }
  } catch (e) {
    error.value = e.response?.data?.error || e.message || 'SSL-Prüfung fehlgeschlagen'
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

function getExpiryStatus(days) {
  if (days === null || days === undefined) return { class: 'text-gray-400', text: 'Unbekannt' }
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
      <input
        v-model.number="port"
        type="number"
        min="1"
        max="65535"
        class="input w-24"
        placeholder="Port"
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
        @click="domain = d; port = 443; checkSSL()"
        class="text-xs text-primary-400 hover:text-primary-300"
      >
        {{ d }}
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="text-center py-8 text-gray-400">
      Prüfe SSL-Zertifikat...
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
          <h3 class="text-lg font-medium text-white">{{ result.domain }}:{{ result.port }}</h3>
          <p :class="result.valid ? 'text-green-400' : 'text-red-400'">
            {{ result.valid ? 'SSL-Zertifikat gültig' : 'SSL-Problem erkannt' }}
          </p>
        </div>
      </div>

      <!-- Certificate Details -->
      <div class="grid grid-cols-2 gap-3" v-if="result.certificate">
        <div class="p-3 bg-dark-700 rounded-lg col-span-2">
          <span class="text-xs text-gray-500">Common Name</span>
          <div class="text-white font-mono">{{ result.certificate.commonName }}</div>
        </div>

        <div class="p-3 bg-dark-700 rounded-lg col-span-2">
          <span class="text-xs text-gray-500">Aussteller</span>
          <div class="text-white">{{ result.certificate.issuer }}</div>
        </div>

        <div class="p-3 bg-dark-700 rounded-lg">
          <span class="text-xs text-gray-500">Gültig ab</span>
          <div class="text-white">{{ formatDate(result.certificate.validFrom) }}</div>
        </div>

        <div class="p-3 bg-dark-700 rounded-lg">
          <span class="text-xs text-gray-500">Gültig bis</span>
          <div class="text-white">{{ formatDate(result.certificate.validTo) }}</div>
        </div>

        <div class="p-3 bg-dark-700 rounded-lg">
          <span class="text-xs text-gray-500">Verbleibende Zeit</span>
          <div :class="getExpiryStatus(result.certificate.daysUntilExpiry).class">
            {{ getExpiryStatus(result.certificate.daysUntilExpiry).text }}
          </div>
        </div>

        <div class="p-3 bg-dark-700 rounded-lg">
          <span class="text-xs text-gray-500">Signatur-Algorithmus</span>
          <div class="text-white text-sm">{{ result.certificate.signatureAlgorithm || '-' }}</div>
        </div>

        <div v-if="result.certificate.serialNumber" class="p-3 bg-dark-700 rounded-lg col-span-2">
          <span class="text-xs text-gray-500">Seriennummer</span>
          <div class="text-sm text-gray-300 font-mono break-all">{{ result.certificate.serialNumber }}</div>
        </div>

        <div v-if="result.certificate.san?.length" class="p-3 bg-dark-700 rounded-lg col-span-2">
          <span class="text-xs text-gray-500">Alternative Namen (SAN)</span>
          <div class="flex flex-wrap gap-1 mt-1">
            <span
              v-for="name in result.certificate.san"
              :key="name"
              class="px-2 py-0.5 text-xs bg-dark-600 text-gray-300 rounded font-mono"
            >
              {{ name }}
            </span>
          </div>
        </div>
      </div>

      <!-- Warnings -->
      <div v-if="result.certificate?.isExpired" class="p-3 bg-red-900/20 border border-red-500/30 rounded-lg text-sm text-red-400">
        Das Zertifikat ist abgelaufen!
      </div>

      <div v-if="result.certificate?.isNotYetValid" class="p-3 bg-yellow-900/20 border border-yellow-500/30 rounded-lg text-sm text-yellow-400">
        Das Zertifikat ist noch nicht gültig!
      </div>

      <div v-if="result.certificate?.daysUntilExpiry > 0 && result.certificate?.daysUntilExpiry < 30" class="p-3 bg-yellow-900/20 border border-yellow-500/30 rounded-lg text-sm text-yellow-400">
        Das Zertifikat läuft in weniger als 30 Tagen ab. Erneuerung empfohlen!
      </div>
    </div>

    <!-- Info -->
    <div class="text-xs text-gray-500 space-y-1">
      <p class="font-medium">Hinweis:</p>
      <p>Dieser Check prüft das SSL/TLS-Zertifikat einer Domain auf Gültigkeit und zeigt Details wie Aussteller und Ablaufdatum an.</p>
    </div>
  </div>
</template>
