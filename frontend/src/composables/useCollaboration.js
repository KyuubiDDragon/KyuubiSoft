import { ref, onUnmounted, computed } from 'vue'
import * as Y from 'yjs'

/**
 * Composable for real-time collaboration using Yjs
 * @param {string} roomName - The document room name (usually the share token)
 * @param {object} options - Options for the collaboration
 */
export function useCollaboration(roomName, options = {}) {
  const {
    serverUrl = getCollaborationServerUrl(),
    userName = 'Anonym',
    userColor = getRandomColor(),
  } = options

  // State
  const isConnected = ref(false)
  const isSynced = ref(false)
  const connectionError = ref(null)
  const connectedUsers = ref([])
  const isAvailable = ref(false)

  // Yjs instances
  let ydoc = null
  let provider = null
  let awareness = null
  let connectionAttempts = 0
  const MAX_CONNECTION_ATTEMPTS = 3

  /**
   * Initialize collaboration connection
   */
  async function connect() {
    if (provider) {
      console.warn('Already connected to collaboration server')
      return { ydoc, provider, awareness, isAvailable: isAvailable.value }
    }

    // Create Yjs document
    ydoc = new Y.Doc()

    try {
      // Dynamically import y-websocket to catch import errors
      const { WebsocketProvider } = await import('y-websocket')

      // Connect to WebSocket server with limited retries
      provider = new WebsocketProvider(serverUrl, roomName, ydoc, {
        connect: true,
        maxBackoffTime: 10000, // Max 10 seconds between retries
      })

      awareness = provider.awareness

      // Set local user state
      awareness.setLocalStateField('user', {
        name: userName,
        color: userColor,
      })

      // Connection status handlers
      provider.on('status', ({ status }) => {
        isConnected.value = status === 'connected'
        if (status === 'connected') {
          isAvailable.value = true
          connectionError.value = null
          connectionAttempts = 0 // Reset on successful connection
        } else if (status === 'disconnected') {
          if (isAvailable.value) {
            // Only show error if we were connected before
            connectionError.value = 'Verbindung zum Server verloren'
          }
        }
      })

      provider.on('sync', (synced) => {
        isSynced.value = synced
      })

      provider.on('connection-error', (error) => {
        connectionAttempts++
        console.warn(`WebSocket connection error (attempt ${connectionAttempts}/${MAX_CONNECTION_ATTEMPTS})`)

        if (connectionAttempts >= MAX_CONNECTION_ATTEMPTS) {
          // Stop trying to reconnect after max attempts
          connectionError.value = 'Collaboration-Server nicht erreichbar. Lokale Bearbeitung aktiv.'
          if (provider) {
            provider.disconnect()
          }
          isAvailable.value = false
        }
      })

      // Handle WebSocket close events
      provider.on('connection-close', (event) => {
        connectionAttempts++
        if (connectionAttempts >= MAX_CONNECTION_ATTEMPTS && !isAvailable.value) {
          connectionError.value = 'Collaboration-Server nicht erreichbar. Lokale Bearbeitung möglich.'
          if (provider) {
            provider.disconnect()
          }
        }
      })

      // Track connected users via awareness
      awareness.on('change', () => {
        const states = Array.from(awareness.getStates().entries())
        connectedUsers.value = states
          .filter(([_, state]) => state.user)
          .map(([clientId, state]) => ({
            clientId,
            name: state.user.name,
            color: state.user.color,
            isCurrentUser: clientId === awareness.clientID,
          }))
      })

      // Set a timeout to detect if connection is not working
      setTimeout(() => {
        if (!isConnected.value && !connectionError.value) {
          connectionError.value = 'Verbindung zum Collaboration-Server dauert länger als erwartet...'
        }
      }, 5000)

      return { ydoc, provider, awareness, isAvailable: true }
    } catch (error) {
      console.error('Failed to initialize collaboration:', error)
      connectionError.value = 'Collaboration konnte nicht initialisiert werden: ' + error.message
      return { ydoc, provider: null, awareness: null, isAvailable: false }
    }
  }

  /**
   * Disconnect from collaboration
   */
  function disconnect() {
    if (provider) {
      provider.disconnect()
      provider.destroy()
      provider = null
    }
    if (ydoc) {
      ydoc.destroy()
      ydoc = null
    }
    awareness = null
    isConnected.value = false
    isSynced.value = false
    connectedUsers.value = []
  }

  /**
   * Update local user info
   */
  function updateUserInfo(name, color) {
    if (awareness) {
      awareness.setLocalStateField('user', {
        name: name || userName,
        color: color || userColor,
      })
    }
  }

  /**
   * Get the Yjs text type for rich text (ProseMirror/TipTap)
   */
  function getXmlFragment(name = 'prosemirror') {
    if (!ydoc) {
      throw new Error('Not connected. Call connect() first.')
    }
    return ydoc.getXmlFragment(name)
  }

  /**
   * Get the Yjs text type for plain text (Monaco)
   */
  function getText(name = 'monaco') {
    if (!ydoc) {
      throw new Error('Not connected. Call connect() first.')
    }
    return ydoc.getText(name)
  }

  // Cleanup on unmount
  onUnmounted(() => {
    disconnect()
  })

  return {
    // State
    isConnected,
    isSynced,
    connectionError,
    connectedUsers,
    isAvailable,

    // Methods
    connect,
    disconnect,
    updateUserInfo,
    getXmlFragment,
    getText,

    // Getters (for external access)
    getYdoc: () => ydoc,
    getProvider: () => provider,
    getAwareness: () => awareness,
  }
}

/**
 * Get collaboration server URL based on environment
 */
function getCollaborationServerUrl() {
  // Use WebSocket through nginx proxy
  const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'
  const host = window.location.host // includes port if non-standard

  // Use /collab/ path which is proxied by nginx to the collaboration server
  return `${protocol}//${host}/collab`
}

/**
 * Generate a random color for user cursor
 */
function getRandomColor() {
  const colors = [
    '#3b82f6', // blue
    '#10b981', // green
    '#f59e0b', // amber
    '#ef4444', // red
    '#8b5cf6', // violet
    '#ec4899', // pink
    '#06b6d4', // cyan
    '#f97316', // orange
  ]
  return colors[Math.floor(Math.random() * colors.length)]
}

export default useCollaboration
