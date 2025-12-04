<script setup>
import { ref } from 'vue'
import api from '@/core/api/axios'

const domain = ref('')
const isLoading = ref(false)
const result = ref(null)
const error = ref('')

async function lookup() {
  if (!domain.value.trim()) return

  isLoading.value = true
  error.value = ''
  result.value = null

  try {
    const response = await api.get('/api/v1/tools/whois', {
      params: { domain: domain.value.trim() },
    })

    if (response.data.data) {
      result.value = response.data.data
    }
  } catch (e) {
    error.value = e.response?.data?.error || e.message || 'WHOIS-Abfrage fehlgeschlagen'
  }

  isLoading.value = false
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) return dateStr
  return date.toLocaleDateString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

function getDaysUntilExpiry(dateStr) {
  if (!dateStr) return null
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) return null
  const now = new Date()
  return Math.ceil((date - now) / (1000 * 60 * 60 * 24))
}

function copyRawData() {
  if (result.value?.raw) {
    navigator.clipboard.writeText(result.value.raw)
  }
}

const quickDomains = ['google.com', 'github.com', 'cloudflare.com']
</script>

<template>
  <div class="space-y-4">
    <!-- Input -->
    <div class="flex gap-2">
      <input
        v-model="domain"
        type="text"
        @keyup.enter="lookup"
        class="input flex-1"
        placeholder="example.com"
      />
      <button
        @click="lookup"
        :disabled="isLoading || !domain.trim()"
        class="btn-primary px-6"
      >
        {{ isLoading ? 'Suche...' : 'Abfragen' }}
      </button>
    </div>

    <!-- Quick domains -->
    <div class="flex gap-2 flex-wrap">
      <span class="text-xs text-gray-500">Beispiele:</span>
      <button
        v-for="d in quickDomains"
        :key="d"
        @click="domain = d"
        class="text-xs text-primary-400 hover:text-primary-300"
      >
        {{ d }}
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="text-center py-8 text-gray-400">
      Führe WHOIS-Abfrage durch...
    </div>

    <!-- Error -->
    <div v-if="error" class="p-4 bg-red-900/30 border border-red-500/30 rounded-lg text-red-400 text-sm">
      {{ error }}
    </div>

    <!-- Results -->
    <div v-if="result" class="space-y-4">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-medium text-white">{{ result.domain }}</h3>
        <button @click="copyRawData" class="text-xs text-primary-400 hover:text-primary-300">
          Raw Data kopieren
        </button>
      </div>

      <!-- Parsed Data -->
      <div v-if="result.parsed && Object.keys(result.parsed).length > 0" class="grid grid-cols-2 gap-3">
        <div v-if="result.parsed.registrar" class="p-3 bg-dark-700 rounded-lg col-span-2">
          <span class="text-xs text-gray-500">Registrar</span>
          <div class="text-white">{{ result.parsed.registrar }}</div>
        </div>

        <div v-if="result.parsed.createdDate" class="p-3 bg-dark-700 rounded-lg">
          <span class="text-xs text-gray-500">Registriert</span>
          <div class="text-white">{{ formatDate(result.parsed.createdDate) }}</div>
        </div>

        <div v-if="result.parsed.expiryDate" class="p-3 bg-dark-700 rounded-lg">
          <span class="text-xs text-gray-500">Läuft ab</span>
          <div class="text-white">
            {{ formatDate(result.parsed.expiryDate) }}
            <span
              v-if="getDaysUntilExpiry(result.parsed.expiryDate)"
              class="text-xs ml-1"
              :class="getDaysUntilExpiry(result.parsed.expiryDate) < 30 ? 'text-yellow-400' : 'text-gray-500'"
            >
              ({{ getDaysUntilExpiry(result.parsed.expiryDate) }} Tage)
            </span>
          </div>
        </div>

        <div v-if="result.parsed.updatedDate" class="p-3 bg-dark-700 rounded-lg">
          <span class="text-xs text-gray-500">Zuletzt aktualisiert</span>
          <div class="text-white">{{ formatDate(result.parsed.updatedDate) }}</div>
        </div>

        <div v-if="result.parsed.registrantOrg" class="p-3 bg-dark-700 rounded-lg">
          <span class="text-xs text-gray-500">Organisation</span>
          <div class="text-white">{{ result.parsed.registrantOrg }}</div>
        </div>

        <div v-if="result.parsed.registrantCountry" class="p-3 bg-dark-700 rounded-lg">
          <span class="text-xs text-gray-500">Land</span>
          <div class="text-white">{{ result.parsed.registrantCountry }}</div>
        </div>

        <div v-if="result.parsed.nameServers?.length" class="p-3 bg-dark-700 rounded-lg col-span-2">
          <span class="text-xs text-gray-500">Nameserver</span>
          <div class="flex flex-wrap gap-2 mt-1">
            <span
              v-for="ns in result.parsed.nameServers"
              :key="ns"
              class="px-2 py-0.5 text-xs bg-dark-600 text-gray-300 rounded font-mono"
            >
              {{ ns }}
            </span>
          </div>
        </div>

        <div v-if="result.parsed.status?.length" class="p-3 bg-dark-700 rounded-lg col-span-2">
          <span class="text-xs text-gray-500">Status</span>
          <div class="flex flex-wrap gap-2 mt-1">
            <span
              v-for="status in result.parsed.status"
              :key="status"
              class="px-2 py-0.5 text-xs bg-dark-600 text-gray-300 rounded"
            >
              {{ status.split(' ')[0] }}
            </span>
          </div>
        </div>

        <div v-if="result.parsed.dnssec" class="p-3 bg-dark-700 rounded-lg">
          <span class="text-xs text-gray-500">DNSSEC</span>
          <div class="text-white">{{ result.parsed.dnssec }}</div>
        </div>
      </div>

      <!-- Raw Data -->
      <details class="text-sm">
        <summary class="text-gray-400 cursor-pointer hover:text-white">Raw WHOIS Data anzeigen</summary>
        <pre class="mt-2 p-3 bg-dark-900 rounded-lg text-xs text-gray-400 overflow-auto max-h-64 whitespace-pre-wrap">{{ result.raw }}</pre>
      </details>
    </div>

    <!-- Info -->
    <div class="text-xs text-gray-500">
      <p>WHOIS-Daten zeigen Registrierungsinformationen zu einer Domain. Einige Daten können durch Datenschutzservices verborgen sein.</p>
    </div>
  </div>
</template>
