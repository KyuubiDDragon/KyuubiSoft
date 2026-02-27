<script setup>
import { ref, computed, watch } from 'vue'

const activeTab = ref('decode')

// ==================== DECODER ====================
const token = ref('')
const decodeError = ref('')

const decoded = computed(() => {
  if (!token.value.trim()) {
    decodeError.value = ''
    return null
  }

  try {
    const parts = token.value.trim().split('.')

    if (parts.length !== 3) {
      decodeError.value = 'Ungültiges JWT Format (muss 3 Teile haben)'
      return null
    }

    const header = JSON.parse(atob(parts[0].replace(/-/g, '+').replace(/_/g, '/')))
    const payload = JSON.parse(atob(parts[1].replace(/-/g, '+').replace(/_/g, '/')))

    decodeError.value = ''

    return {
      header,
      payload,
      signature: parts[2],
    }
  } catch (e) {
    decodeError.value = 'Dekodierung fehlgeschlagen: ' + e.message
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

const sampleJwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyLCJleHAiOjE5MTYyMzkwMjJ9.4S2H9lFkCCNfPQDsn8W1YKkMEwZ5sWNcJPLB8cMkN-4'

function loadSample() {
  token.value = sampleJwt
}

function clearToken() {
  token.value = ''
  decodeError.value = ''
}

// ==================== GENERATOR ====================
const algorithm = ref('HS256')
const secret = ref('')
const headerJson = ref('{\n  "alg": "HS256",\n  "typ": "JWT"\n}')
const payloadJson = ref('{\n  "sub": "1234567890",\n  "name": "Max Mustermann",\n  "iat": ' + Math.floor(Date.now() / 1000) + ',\n  "exp": ' + (Math.floor(Date.now() / 1000) + 3600) + '\n}')
const generatedToken = ref('')
const generateError = ref('')

const algorithms = ['HS256', 'HS384', 'HS512']

// Base64URL encode
function base64UrlEncode(str) {
  return btoa(str)
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '')
}

// HMAC-SHA signing (simplified, using Web Crypto API)
async function hmacSign(data, secret, algorithm) {
  const enc = new TextEncoder()
  const keyData = enc.encode(secret)
  const msgData = enc.encode(data)

  let hashAlgo
  switch (algorithm) {
    case 'HS256': hashAlgo = 'SHA-256'; break
    case 'HS384': hashAlgo = 'SHA-384'; break
    case 'HS512': hashAlgo = 'SHA-512'; break
    default: hashAlgo = 'SHA-256'
  }

  const key = await crypto.subtle.importKey(
    'raw',
    keyData,
    { name: 'HMAC', hash: hashAlgo },
    false,
    ['sign']
  )

  const signature = await crypto.subtle.sign('HMAC', key, msgData)
  const signatureArray = new Uint8Array(signature)

  // Convert to base64url
  let binary = ''
  signatureArray.forEach(byte => binary += String.fromCharCode(byte))
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

async function generateJwt() {
  generateError.value = ''
  generatedToken.value = ''

  try {
    // Parse and validate header
    let header
    try {
      header = JSON.parse(headerJson.value)
    } catch {
      throw new Error('Header ist kein gültiges JSON')
    }

    // Parse and validate payload
    let payload
    try {
      payload = JSON.parse(payloadJson.value)
    } catch {
      throw new Error('Payload ist kein gültiges JSON')
    }

    // Ensure header has alg
    header.alg = algorithm.value
    header.typ = header.typ || 'JWT'

    // Encode header and payload
    const encodedHeader = base64UrlEncode(JSON.stringify(header))
    const encodedPayload = base64UrlEncode(JSON.stringify(payload))

    // Create signing input
    const signingInput = `${encodedHeader}.${encodedPayload}`

    // Sign
    if (!secret.value) {
      throw new Error('Secret ist erforderlich für HMAC Algorithmen')
    }

    const signature = await hmacSign(signingInput, secret.value, algorithm.value)

    generatedToken.value = `${signingInput}.${signature}`
  } catch (e) {
    generateError.value = e.message
  }
}

function copyGeneratedToken() {
  navigator.clipboard.writeText(generatedToken.value)
}

function useGeneratedToken() {
  token.value = generatedToken.value
  activeTab.value = 'decode'
}

// Update header JSON when algorithm changes
watch(algorithm, (newAlg) => {
  try {
    const header = JSON.parse(headerJson.value)
    header.alg = newAlg
    headerJson.value = JSON.stringify(header, null, 2)
  } catch {
    // Keep current value if parsing fails
  }
})

// Quick payload templates
function setPayloadTemplate(type) {
  const now = Math.floor(Date.now() / 1000)
  switch (type) {
    case 'user':
      payloadJson.value = JSON.stringify({
        sub: "user_123",
        name: "Max Mustermann",
        email: "max@example.com",
        role: "admin",
        iat: now,
        exp: now + 3600
      }, null, 2)
      break
    case 'api':
      payloadJson.value = JSON.stringify({
        iss: "https://api.example.com",
        aud: "https://app.example.com",
        sub: "client_456",
        scope: "read write",
        iat: now,
        exp: now + 86400
      }, null, 2)
      break
    case 'refresh':
      payloadJson.value = JSON.stringify({
        sub: "user_123",
        type: "refresh",
        jti: crypto.randomUUID(),
        iat: now,
        exp: now + 604800
      }, null, 2)
      break
  }
}
</script>

<template>
  <div class="space-y-4">
    <!-- Tabs -->
    <div class="flex gap-1 bg-white/[0.04] rounded-lg p-1">
      <button
        @click="activeTab = 'decode'"
        class="flex-1 px-4 py-2 rounded-md text-sm transition-colors"
        :class="activeTab === 'decode' ? 'bg-primary-600 text-white' : 'text-gray-400 hover:text-white'"
      >
        Dekodieren
      </button>
      <button
        @click="activeTab = 'generate'"
        class="flex-1 px-4 py-2 rounded-md text-sm transition-colors"
        :class="activeTab === 'generate' ? 'bg-primary-600 text-white' : 'text-gray-400 hover:text-white'"
      >
        Generieren
      </button>
    </div>

    <!-- ==================== DECODE TAB ==================== -->
    <div v-if="activeTab === 'decode'" class="space-y-4">
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
      <div v-if="decodeError" class="p-3 bg-red-900/30 border border-red-700 rounded-lg text-red-400 text-sm">
        {{ decodeError }}
      </div>

      <!-- Decoded Result -->
      <div v-if="decoded" class="space-y-4">
        <!-- Status -->
        <div class="flex gap-4 flex-wrap">
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
          <pre class="p-3 bg-white/[0.02] rounded-lg text-sm font-mono text-red-400 overflow-auto">{{ formatJson(decoded.header) }}</pre>
        </div>

        <!-- Payload -->
        <div>
          <label class="text-sm text-gray-400 mb-1 block flex items-center gap-2">
            <span class="w-3 h-3 rounded bg-purple-500"></span>
            Payload
          </label>
          <pre class="p-3 bg-white/[0.02] rounded-lg text-sm font-mono text-purple-400 overflow-auto">{{ formatJson(decoded.payload) }}</pre>
        </div>

        <!-- Signature -->
        <div>
          <label class="text-sm text-gray-400 mb-1 block flex items-center gap-2">
            <span class="w-3 h-3 rounded bg-cyan-500"></span>
            Signature
          </label>
          <div class="p-3 bg-white/[0.02] rounded-lg text-sm font-mono text-cyan-400 break-all">
            {{ decoded.signature }}
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== GENERATE TAB ==================== -->
    <div v-if="activeTab === 'generate'" class="space-y-4">
      <!-- Algorithm & Secret -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs text-gray-400 mb-1">Algorithmus</label>
          <select v-model="algorithm" class="input w-full">
            <option v-for="alg in algorithms" :key="alg" :value="alg">{{ alg }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Secret</label>
          <input
            v-model="secret"
            type="text"
            class="input w-full font-mono"
            placeholder="your-256-bit-secret"
          />
        </div>
      </div>

      <!-- Header -->
      <div>
        <label class="block text-xs text-gray-400 mb-1">Header (JSON)</label>
        <textarea
          v-model="headerJson"
          class="input w-full h-20 font-mono text-sm resize-none"
        ></textarea>
      </div>

      <!-- Payload -->
      <div>
        <div class="flex justify-between items-center mb-1">
          <label class="text-xs text-gray-400">Payload (JSON)</label>
          <div class="flex gap-2">
            <button @click="setPayloadTemplate('user')" class="text-xs text-primary-400 hover:text-primary-300">User</button>
            <button @click="setPayloadTemplate('api')" class="text-xs text-primary-400 hover:text-primary-300">API</button>
            <button @click="setPayloadTemplate('refresh')" class="text-xs text-primary-400 hover:text-primary-300">Refresh</button>
          </div>
        </div>
        <textarea
          v-model="payloadJson"
          class="input w-full h-32 font-mono text-sm resize-none"
        ></textarea>
      </div>

      <!-- Generate Button -->
      <button @click="generateJwt" class="btn-primary w-full">
        JWT Generieren
      </button>

      <!-- Error -->
      <div v-if="generateError" class="p-3 bg-red-900/30 border border-red-700 rounded-lg text-red-400 text-sm">
        {{ generateError }}
      </div>

      <!-- Generated Token -->
      <div v-if="generatedToken">
        <div class="flex justify-between items-center mb-1">
          <label class="text-xs text-gray-400">Generiertes JWT</label>
          <div class="flex gap-2">
            <button @click="copyGeneratedToken" class="text-xs text-primary-400 hover:text-primary-300">
              Kopieren
            </button>
            <button @click="useGeneratedToken" class="text-xs text-green-400 hover:text-green-300">
              Dekodieren →
            </button>
          </div>
        </div>
        <div class="p-3 bg-white/[0.02] rounded-lg text-sm font-mono text-green-400 break-all max-h-32 overflow-auto">
          {{ generatedToken }}
        </div>
      </div>

      <!-- Info -->
      <div class="text-xs text-gray-500 space-y-1">
        <p><strong>Hinweis:</strong> JWTs werden nur mit HMAC-SHA Algorithmen im Browser signiert.</p>
        <p>Für RS256/ES256 ist ein Backend erforderlich.</p>
      </div>
    </div>

    <!-- Common Claims Reference -->
    <div class="text-xs text-gray-500 mt-4">
      <p class="font-medium mb-1">Standard Claims:</p>
      <ul class="grid grid-cols-2 gap-1">
        <li><code>iss</code> - Issuer</li>
        <li><code>sub</code> - Subject</li>
        <li><code>aud</code> - Audience</li>
        <li><code>exp</code> - Expiration (Unix)</li>
        <li><code>iat</code> - Issued At (Unix)</li>
        <li><code>nbf</code> - Not Before (Unix)</li>
      </ul>
    </div>
  </div>
</template>
