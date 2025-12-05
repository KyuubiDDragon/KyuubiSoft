import { ref, onUnmounted, computed } from 'vue'
import * as Y from 'yjs'
import { WebsocketProvider } from 'y-websocket'

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

  // Yjs instances
  let ydoc = null
  let provider = null
  let awareness = null

  /**
   * Initialize collaboration connection
   */
  function connect() {
    if (provider) {
      console.warn('Already connected to collaboration server')
      return { ydoc, provider, awareness }
    }

    // Create Yjs document
    ydoc = new Y.Doc()

    // Connect to WebSocket server
    provider = new WebsocketProvider(serverUrl, roomName, ydoc, {
      connect: true,
      awareness: true,
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
      if (status === 'disconnected') {
        connectionError.value = 'Verbindung zum Server verloren'
      } else {
        connectionError.value = null
      }
    })

    provider.on('sync', (synced) => {
      isSynced.value = synced
    })

    provider.on('connection-error', (error) => {
      connectionError.value = 'Verbindungsfehler: ' + error.message
      console.error('WebSocket connection error:', error)
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

    return { ydoc, provider, awareness }
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
