<script setup>
import { ref, computed } from 'vue'

const token = ref('')
const error = ref('')

const decoded = computed(() => {
  if (!token.value.trim()) {
    error.value = ''
    return null
  }

  try {
    const parts = token.value.split('.')

    if (parts.length !== 3) {
      error.value = 'Ungültiges JWT Format (muss 3 Teile haben)'
      return null
    }

    const header = JSON.parse(atob(parts[0].replace(/-/g, '+').replace(/_/g, '/')))
    const payload = JSON.parse(atob(parts[1].replace(/-/g, '+').replace(/_/g, '/')))

    error.value = ''

    return {
      header,
      payload,
      signature: parts[2],
    }
  } catch (e) {
    error.value = 'Dekodierung fehlgeschlagen: ' + e.message
    return null
  }
})

const isExpired = computed(() => {
  if (!decoded.value?.payload?.exp) return null
  return Date.now() / 1000 > decoded.value.payload.exp
})

const expirationDate = computed(() => {
  if (!decoded.value?.payload?.exp) return null
  return new Date(decoded.value.payload.exp * 1000).toLocaleString('de-DE')
})

const issuedDate = computed(() => {
  if (!decoded.value?.payload?.iat) return null
  return new Date(decoded.value.payload.iat * 1000).toLocaleString('de-DE')
})

function formatJson(obj) {
  return JSON.stringify(obj, null, 2)
}

// Sample JWT for testing
const sampleJwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyLCJleHAiOjE5MTYyMzkwMjJ9.4S2H9lFkCCNfPQDsn8W1YKkMEwZ5sWNcJPLB8cMkN-4'

function loadSample() {
  token.value = sampleJwt
}

function clearToken() {
  token.value = ''
  error.value = ''
}
</script>

<template>
  <div class="space-y-4">
    <!-- Input -->
    <div>
      <div class="flex justify-between items-center mb-1">
        <label class="text-sm text-gray-400">JWT Token</label>
        <div class="flex gap-2">
          <button @click="loadSample" class="text-xs text-primary-400 hover:text-primary-300">
            Beispiel laden
          </button>
          <button @click="clearToken" class="text-xs text-gray-400 hover:text-white">
            Löschen
          </button>
        </div>
      </div>
      <textarea
        v-model="token"
        class="input w-full h-24 font-mono text-sm"
        placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
      ></textarea>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-3 bg-red-900/30 border border-red-700 rounded-lg text-red-400 text-sm">
      {{ error }}
    </div>

    <!-- Decoded Result -->
    <div v-if="decoded" class="space-y-4">
      <!-- Status -->
      <div class="flex gap-4">
        <div
          v-if="isExpired !== null"
          class="px-3 py-1 rounded-full text-sm font-medium"
          :class="isExpired ? 'bg-red-900/30 text-red-400' : 'bg-green-900/30 text-green-400'"
        >
          {{ isExpired ? 'Abgelaufen' : 'Gültig' }}
        </div>
        <div v-if="expirationDate" class="text-sm text-gray-400">
          Ablauf: {{ expirationDate }}
        </div>
        <div v-if="issuedDate" class="text-sm text-gray-400">
          Ausgestellt: {{ issuedDate }}
        </div>
      </div>

      <!-- Header -->
      <div>
        <label class="text-sm text-gray-400 mb-1 block flex items-center gap-2">
          <span class="w-3 h-3 rounded bg-red-500"></span>
          Header
        </label>
        <pre class="p-3 bg-dark-900 rounded-lg text-sm font-mono text-red-400 overflow-auto">{{ formatJson(decoded.header) }}</pre>
      </div>

      <!-- Payload -->
      <div>
        <label class="text-sm text-gray-400 mb-1 block flex items-center gap-2">
          <span class="w-3 h-3 rounded bg-purple-500"></span>
          Payload
        </label>
        <pre class="p-3 bg-dark-900 rounded-lg text-sm font-mono text-purple-400 overflow-auto">{{ formatJson(decoded.payload) }}</pre>
      </div>

      <!-- Signature -->
      <div>
        <label class="text-sm text-gray-400 mb-1 block flex items-center gap-2">
          <span class="w-3 h-3 rounded bg-cyan-500"></span>
          Signature
        </label>
        <div class="p-3 bg-dark-900 rounded-lg text-sm font-mono text-cyan-400 break-all">
          {{ decoded.signature }}
        </div>
      </div>

      <!-- Common Claims Reference -->
      <div class="text-xs text-gray-500 mt-4">
        <p class="font-medium mb-1">Häufige Claims:</p>
        <ul class="grid grid-cols-2 gap-1">
          <li><code>iss</code> - Issuer</li>
          <li><code>sub</code> - Subject</li>
          <li><code>aud</code> - Audience</li>
          <li><code>exp</code> - Expiration</li>
          <li><code>iat</code> - Issued At</li>
          <li><code>nbf</code> - Not Before</li>
        </ul>
      </div>
    </div>
  </div>
</template>
