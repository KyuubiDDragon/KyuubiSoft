<script setup>
import { ref } from 'vue'

const domain = ref('')
const isLoading = ref(false)
const results = ref(null)
const error = ref('')
const selectedType = ref('A')

const recordTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SOA']

async function lookup() {
  if (!domain.value.trim()) return

  isLoading.value = true
  error.value = ''
  results.value = null

  // Clean domain input
  let cleanDomain = domain.value.trim()
    .replace(/^https?:\/\//, '')
    .replace(/\/.*$/, '')
    .replace(/:\d+$/, '')

  try {
    // Use DNS over HTTPS (Cloudflare)
    const response = await fetch(
      `https://cloudflare-dns.com/dns-query?name=${encodeURIComponent(cleanDomain)}&type=${selectedType.value}`,
      {
        headers: {
          'Accept': 'application/dns-json',
        },
      }
    )

    if (!response.ok) {
      throw new Error('DNS-Abfrage fehlgeschlagen')
    }

    const data = await response.json()
    results.value = {
      domain: cleanDomain,
      type: selectedType.value,
      status: data.Status,
      answers: data.Answer || [],
      authority: data.Authority || [],
      queriedAt: new Date().toISOString(),
    }
  } catch (e) {
    error.value = e.message || 'DNS-Abfrage fehlgeschlagen'
  }

  isLoading.value = false
}

async function lookupAll() {
  if (!domain.value.trim()) return

  isLoading.value = true
  error.value = ''

  let cleanDomain = domain.value.trim()
    .replace(/^https?:\/\//, '')
    .replace(/\/.*$/, '')

  const allResults = {
    domain: cleanDomain,
    records: {},
    queriedAt: new Date().toISOString(),
  }

  for (const type of recordTypes) {
    try {
      const response = await fetch(
        `https://cloudflare-dns.com/dns-query?name=${encodeURIComponent(cleanDomain)}&type=${type}`,
        {
          headers: { 'Accept': 'application/dns-json' },
        }
      )

      if (response.ok) {
        const data = await response.json()
        if (data.Answer && data.Answer.length > 0) {
          allResults.records[type] = data.Answer
        }
      }
    } catch (e) {
      // Skip failed queries
    }
  }

  results.value = allResults
  isLoading.value = false
}

function formatTTL(seconds) {
  if (seconds < 60) return `${seconds}s`
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h`
  return `${Math.floor(seconds / 86400)}d`
}

function getRecordTypeColor(type) {
  const colors = {
    A: 'text-blue-400',
    AAAA: 'text-purple-400',
    CNAME: 'text-green-400',
    MX: 'text-yellow-400',
    TXT: 'text-gray-400',
    NS: 'text-orange-400',
    SOA: 'text-pink-400',
  }
  return colors[type] || 'text-gray-400'
}

const dnsStatusCodes = {
  0: { text: 'NOERROR', desc: 'Erfolgreiche Abfrage' },
  1: { text: 'FORMERR', desc: 'Format-Fehler' },
  2: { text: 'SERVFAIL', desc: 'Server-Fehler' },
  3: { text: 'NXDOMAIN', desc: 'Domain existiert nicht' },
  4: { text: 'NOTIMP', desc: 'Nicht implementiert' },
  5: { text: 'REFUSED', desc: 'Abfrage abgelehnt' },
}

// Quick domains
const quickDomains = ['google.com', 'cloudflare.com', 'github.com']
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
      <select v-model="selectedType" class="input w-24">
        <option v-for="type in recordTypes" :key="type" :value="type">{{ type }}</option>
      </select>
      <button
        @click="lookup"
        :disabled="isLoading || !domain.trim()"
        class="btn-primary px-4"
      >
        Abfragen
      </button>
      <button
        @click="lookupAll"
        :disabled="isLoading || !domain.trim()"
        class="btn-secondary px-4"
        title="Alle Record-Typen abfragen"
      >
        Alle
      </button>
    </div>

    <!-- Quick domains -->
    <div class="flex gap-2 flex-wrap">
      <span class="text-xs text-gray-500">Schnelltest:</span>
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
      Führe DNS-Abfrage durch...
    </div>

    <!-- Error -->
    <div v-if="error" class="p-4 bg-red-900/30 border border-red-500 rounded-lg text-red-400">
      {{ error }}
    </div>

    <!-- Single Query Results -->
    <div v-if="results && results.answers" class="space-y-4">
      <!-- Status -->
      <div class="flex items-center gap-3">
        <div
          class="w-10 h-10 rounded-full flex items-center justify-center"
          :class="results.status === 0 ? 'bg-green-900/30' : 'bg-yellow-900/30'"
        >
          {{ results.status === 0 ? '✓' : '!' }}
        </div>
        <div>
          <h3 class="text-white">{{ results.domain }}</h3>
          <p class="text-sm" :class="results.status === 0 ? 'text-green-400' : 'text-yellow-400'">
            {{ dnsStatusCodes[results.status]?.text || 'Unbekannt' }} -
            {{ dnsStatusCodes[results.status]?.desc || '' }}
          </p>
        </div>
      </div>

      <!-- Records -->
      <div v-if="results.answers.length > 0" class="space-y-2">
        <h4 class="text-sm text-gray-400">{{ results.type }} Records</h4>
        <div
          v-for="(record, i) in results.answers"
          :key="i"
          class="p-3 bg-dark-700 rounded-lg"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
              <span :class="getRecordTypeColor(results.type)" class="text-xs font-medium">
                {{ record.type || results.type }}
              </span>
              <div class="font-mono text-white break-all">{{ record.data }}</div>
              <div class="text-xs text-gray-500">{{ record.name }}</div>
            </div>
            <div class="text-xs text-gray-500">
              TTL: {{ formatTTL(record.TTL) }}
            </div>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-4 text-gray-500">
        Keine {{ results.type }} Records gefunden
      </div>
    </div>

    <!-- All Records Results -->
    <div v-if="results && results.records" class="space-y-4">
      <div class="flex items-center gap-3">
        <h3 class="text-white">{{ results.domain }}</h3>
        <span class="text-xs text-gray-500">Alle DNS Records</span>
      </div>

      <div v-for="(records, type) in results.records" :key="type" class="space-y-2">
        <h4 class="text-sm font-medium" :class="getRecordTypeColor(type)">{{ type }} Records</h4>
        <div
          v-for="(record, i) in records"
          :key="i"
          class="p-3 bg-dark-700 rounded-lg"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="font-mono text-sm text-white break-all">{{ record.data }}</div>
            </div>
            <div class="text-xs text-gray-500">
              TTL: {{ formatTTL(record.TTL) }}
            </div>
          </div>
        </div>
      </div>

      <div v-if="Object.keys(results.records).length === 0" class="text-center py-4 text-gray-500">
        Keine DNS Records gefunden
      </div>
    </div>

    <!-- Info -->
    <div class="text-xs text-gray-500">
      <p>DNS-Abfragen werden über Cloudflare DNS-over-HTTPS (1.1.1.1) durchgeführt.</p>
    </div>
  </div>
</template>
