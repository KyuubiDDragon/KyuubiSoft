import { ref, onUnmounted, computed } from 'vue'
import type { Ref } from 'vue'
import * as Y from 'yjs'

// Interfaces
interface CollaborationOptions {
  serverUrl?: string
  userName?: string
  userColor?: string
}

interface ConnectedUser {
  clientId: number
  name: string
  color: string
  isCurrentUser: boolean
}

interface ConnectResult {
  ydoc: Y.Doc | null
  provider: WebsocketProviderInstance | null
  awareness: AwarenessInstance | null
  isAvailable: boolean
}

interface AwarenessState {
  user?: {
    name: string
    color: string
  }
  [key: string]: unknown
}

interface AwarenessInstance {
  clientID: number
  setLocalStateField: (field: string, value: unknown) => void
  getStates: () => Map<number, AwarenessState>
  on: (event: string, callback: (...args: unknown[]) => void) => void
}

interface WebsocketProviderInstance {
  awareness: AwarenessInstance
  on: (event: string, callback: (...args: unknown[]) => void) => void
  disconnect: () => void
  destroy: () => void
}

interface UseCollaborationReturn {
  // State
  isConnected: Ref<boolean>
  isSynced: Ref<boolean>
  connectionError: Ref<string | null>
  connectedUsers: Ref<ConnectedUser[]>
  isAvailable: Ref<boolean>

  // Methods
  connect: () => Promise<ConnectResult>
  disconnect: () => void
  updateUserInfo: (name?: string, color?: string) => void
  getXmlFragment: (name?: string) => Y.XmlFragment
  getText: (name?: string) => Y.Text

  // Getters (for external access)
  getYdoc: () => Y.Doc | null
  getProvider: () => WebsocketProviderInstance | null
  getAwareness: () => AwarenessInstance | null
}

/**
 * Composable for real-time collaboration using Yjs
 */
export function useCollaboration(roomName: string, options: CollaborationOptions = {}): UseCollaborationReturn {
  const {
    serverUrl = getCollaborationServerUrl(),
    userName = 'Anonym',
    userColor = getRandomColor(),
  } = options

  // State
  const isConnected: Ref<boolean> = ref<boolean>(false)
  const isSynced: Ref<boolean> = ref<boolean>(false)
  const connectionError: Ref<string | null> = ref<string | null>(null)
  const connectedUsers: Ref<ConnectedUser[]> = ref<ConnectedUser[]>([])
  const isAvailable: Ref<boolean> = ref<boolean>(false)

  // Yjs instances
  let ydoc: Y.Doc | null = null
  let provider: WebsocketProviderInstance | null = null
  let awareness: AwarenessInstance | null = null
  let connectionAttempts: number = 0
  const MAX_CONNECTION_ATTEMPTS: number = 3

  /**
   * Initialize collaboration connection
   */
  async function connect(): Promise<ConnectResult> {
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
      }) as unknown as WebsocketProviderInstance

      awareness = provider.awareness

      // Set local user state
      awareness.setLocalStateField('user', {
        name: userName,
        color: userColor,
      })

      // Connection status handlers
      provider.on('status', ({ status }: { status: string }) => {
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

      provider.on('sync', (synced: boolean) => {
        isSynced.value = synced
      })

      provider.on('connection-error', (error: Error) => {
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
      provider.on('connection-close', (event: Event) => {
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
        const states: [number, AwarenessState][] = Array.from(awareness!.getStates().entries())
        connectedUsers.value = states
          .filter(([_, state]: [number, AwarenessState]) => state.user)
          .map(([clientId, state]: [number, AwarenessState]) => ({
            clientId,
            name: state.user!.name,
            color: state.user!.color,
            isCurrentUser: clientId === awareness!.clientID,
          }))
      })

      // Set a timeout to detect if connection is not working
      setTimeout(() => {
        if (!isConnected.value && !connectionError.value) {
          connectionError.value = 'Verbindung zum Collaboration-Server dauert länger als erwartet...'
        }
      }, 5000)

      return { ydoc, provider, awareness, isAvailable: true }
    } catch (error: unknown) {
      console.error('Failed to initialize collaboration:', error)
      connectionError.value = 'Collaboration konnte nicht initialisiert werden: ' + (error as Error).message
      return { ydoc, provider: null, awareness: null, isAvailable: false }
    }
  }

  /**
   * Disconnect from collaboration
   */
  function disconnect(): void {
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
  function updateUserInfo(name?: string, color?: string): void {
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
  function getXmlFragment(name: string = 'prosemirror'): Y.XmlFragment {
    if (!ydoc) {
      throw new Error('Not connected. Call connect() first.')
    }
    return ydoc.getXmlFragment(name)
  }

  /**
   * Get the Yjs text type for plain text (Monaco)
   */
  function getText(name: string = 'monaco'): Y.Text {
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
    getYdoc: (): Y.Doc | null => ydoc,
    getProvider: (): WebsocketProviderInstance | null => provider,
    getAwareness: (): AwarenessInstance | null => awareness,
  }
}

/**
 * Get collaboration server URL based on environment
 */
function getCollaborationServerUrl(): string {
  // Use WebSocket through nginx proxy
  const protocol: string = window.location.protocol === 'https:' ? 'wss:' : 'ws:'
  const host: string = window.location.host // includes port if non-standard

  // Use /collab/ path which is proxied by nginx to the collaboration server
  return `${protocol}//${host}/collab`
}

/**
 * Generate a random color for user cursor
 */
function getRandomColor(): string {
  const colors: string[] = [
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
