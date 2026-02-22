<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useToast } from '@/composables/useToast'
import {
  ArrowLeftIcon,
  CommandLineIcon,
  XMarkIcon,
  SignalIcon,
  SignalSlashIcon,
  ArrowPathIcon,
} from '@heroicons/vue/24/outline'

const route   = useRoute()
const router  = useRouter()
const uiStore = useUiStore()
const toast   = useToast()

// State
const connection = ref(null)
const loading    = ref(true)
const terminalEl = ref(null)
const wsStatus   = ref('disconnected') // disconnected | connecting | connected | error
const wsError    = ref('')

// xterm.js + WebSocket instances
let term      = null
let fitAddon  = null
let ws        = null

// Resolve the WebSocket base URL for the collaboration server
const WS_BASE = import.meta.env.VITE_COLLAB_WS_URL
  || `${window.location.protocol === 'https:' ? 'wss' : 'ws'}://${window.location.hostname}:1234`

// =========================================================================
// Connection loading
// =========================================================================

async function fetchConnection() {
  loading.value = true
  try {
    const res = await api.get(`/api/v1/connections/${route.params.id}`)
    connection.value = res.data.data

    if (!['ssh', 'sftp'].includes(connection.value.type)) {
      uiStore.showError('Diese Verbindung unterstützt kein SSH')
      router.push('/connections')
    }
  } catch {
    uiStore.showError('Verbindung nicht gefunden')
    router.push('/connections')
  } finally {
    loading.value = false
  }
}

// =========================================================================
// Terminal bootstrap
// =========================================================================

async function openTerminal() {
  if (!connection.value) return

  wsStatus.value = 'connecting'
  wsError.value  = ''

  try {
    const res = await api.post('/api/v1/terminal/sessions', {
      connection_id: connection.value.id,
    })

    const { session_id, ws_url } = res.data.data

    // Use provided ws_url or build from WS_BASE
    const wsUrl = (ws_url && ws_url.startsWith('ws'))
      ? ws_url
      : `${WS_BASE}/terminal/${session_id}`

    await initXterm()
    connectWebSocket(wsUrl)
  } catch (err) {
    wsStatus.value = 'error'
    wsError.value  = err.response?.data?.message || 'Fehler beim Erstellen der Terminal-Session'
    toast.error(wsError.value)
  }
}

async function initXterm() {
  const { Terminal }      = await import('xterm')
  const { FitAddon }      = await import('@xterm/addon-fit')
  const { WebLinksAddon } = await import('@xterm/addon-web-links')

  if (term) {
    term.dispose()
    term = null
  }

  term = new Terminal({
    theme: {
      background: '#0d1117',
      foreground: '#c9d1d9',
      cursor: '#58a6ff',
      selectionBackground: 'rgba(88,166,255,0.3)',
    },
    fontFamily: '"Fira Code", "JetBrains Mono", "Cascadia Code", monospace',
    fontSize: 14,
    lineHeight: 1.4,
    cursorBlink: true,
    scrollback: 5000,
  })

  fitAddon = new FitAddon()
  term.loadAddon(fitAddon)
  term.loadAddon(new WebLinksAddon())

  await nextTick()
  if (terminalEl.value) {
    term.open(terminalEl.value)
    fitAddon.fit()
  }

  // Send keystrokes to SSH
  term.onData((data) => {
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify({ type: 'input', data }))
    }
  })

  // Notify server of terminal resize
  term.onResize(({ cols, rows }) => {
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify({ type: 'resize', cols, rows }))
    }
  })
}

// =========================================================================
// WebSocket proxy (via collaboration/terminalHandler.js → SSH PTY)
// =========================================================================

function connectWebSocket(url) {
  ws = new WebSocket(url)

  ws.onmessage = (evt) => {
    try {
      const msg = JSON.parse(evt.data)

      switch (msg.type) {
        case 'connected':
          wsStatus.value = 'connected'
          term?.writeln(`\x1b[32mVerbunden mit ${msg.username}@${msg.host}\x1b[0m\r\n`)
          break
        case 'output':
          if (term) {
            try { term.write(atob(msg.data)) } catch { term.write(msg.data) }
          }
          break
        case 'disconnected':
          wsStatus.value = 'disconnected'
          term?.writeln('\r\n\x1b[33mVerbindung getrennt.\x1b[0m')
          break
        case 'error':
          wsStatus.value = 'error'
          wsError.value  = msg.message
          term?.writeln(`\r\n\x1b[31mFehler: ${msg.message}\x1b[0m`)
          break
      }
    } catch {
      if (term) term.write(evt.data)
    }
  }

  ws.onerror = () => {
    wsStatus.value = 'error'
    wsError.value  = 'WebSocket-Verbindungsfehler zum Terminal-Server'
    term?.writeln('\r\n\x1b[31mWebSocket-Fehler\x1b[0m')
  }

  ws.onclose = () => {
    if (wsStatus.value === 'connected' || wsStatus.value === 'connecting') {
      wsStatus.value = 'disconnected'
      term?.writeln('\r\n\x1b[33mVerbindung geschlossen.\x1b[0m')
    }
  }
}

function disconnect() {
  if (ws) { ws.close(); ws = null }
  wsStatus.value = 'disconnected'
}

async function reconnect() {
  disconnect()
  term?.clear()
  await openTerminal()
}

// =========================================================================
// Resize handling
// =========================================================================

let resizeObserver = null

function setupResizeObserver() {
  if (!terminalEl.value) return
  resizeObserver = new ResizeObserver(() => {
    if (fitAddon && term) fitAddon.fit()
  })
  resizeObserver.observe(terminalEl.value)
}

// =========================================================================
// Lifecycle
// =========================================================================

onMounted(async () => {
  await fetchConnection()
  if (connection.value) {
    await openTerminal()
    setupResizeObserver()
  }
})

onUnmounted(() => {
  disconnect()
  resizeObserver?.disconnect()
  term?.dispose()
  term = null
})
</script>

<template>
  <div class="flex flex-col h-full gap-4">
    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <template v-else-if="connection">
      <!-- Header -->
      <div class="flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3">
          <button
            @click="router.push('/connections')"
            class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors"
          >
            <ArrowLeftIcon class="w-5 h-5" />
          </button>
          <div>
            <h1 class="text-xl font-bold text-white flex items-center gap-2">
              <CommandLineIcon class="w-5 h-5 text-primary-400" />
              {{ connection.name }}
            </h1>
            <p class="text-xs text-gray-400 font-mono mt-0.5">
              {{ connection.username }}@{{ connection.host }}:{{ connection.port }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <!-- Status -->
          <div class="flex items-center gap-1.5 text-sm">
            <component
              :is="wsStatus === 'connected' ? SignalIcon : SignalSlashIcon"
              class="w-4 h-4"
              :class="{
                'text-green-400 animate-pulse': wsStatus === 'connected',
                'text-yellow-400': wsStatus === 'connecting',
                'text-red-400': wsStatus === 'error',
                'text-gray-500': wsStatus === 'disconnected',
              }"
            />
            <span
              :class="{
                'text-green-400': wsStatus === 'connected',
                'text-yellow-400': wsStatus === 'connecting',
                'text-red-400': wsStatus === 'error',
                'text-gray-500': wsStatus === 'disconnected',
              }"
            >
              {{ { connected: 'Verbunden', connecting: 'Verbinde…', disconnected: 'Getrennt', error: 'Fehler' }[wsStatus] }}
            </span>
          </div>

          <button
            v-if="wsStatus !== 'connecting'"
            @click="reconnect"
            class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors"
            title="Neu verbinden"
          >
            <ArrowPathIcon class="w-4 h-4" />
          </button>

          <button
            v-if="wsStatus === 'connected'"
            @click="disconnect"
            class="p-2 text-gray-400 hover:text-red-400 hover:bg-dark-700 rounded-lg transition-colors"
            title="Trennen"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Error banner -->
      <div
        v-if="wsStatus === 'error' && wsError"
        class="flex-shrink-0 px-4 py-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm flex items-center gap-2"
      >
        <XMarkIcon class="w-4 h-4 flex-shrink-0" />
        {{ wsError }}
      </div>

      <!-- xterm.js terminal — fills available height -->
      <div
        ref="terminalEl"
        class="flex-1 min-h-0 rounded-xl overflow-hidden border border-dark-700 cursor-text"
        style="background: #0d1117;"
      ></div>
    </template>
  </div>
</template>
