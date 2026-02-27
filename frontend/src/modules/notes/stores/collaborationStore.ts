import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/core/api/axios'

export interface CollaborationParticipant {
  id: string
  name?: string
  color?: string
  [key: string]: unknown
}

export interface CursorPosition {
  line?: number
  column?: number
  offset?: number
  user?: CollaborationParticipant
  [key: string]: unknown
}

export interface SelectionRange {
  anchor?: number
  head?: number
  from?: number
  to?: number
  user?: CollaborationParticipant
  [key: string]: unknown
}

export interface AwarenessData {
  user?: CollaborationParticipant
  [key: string]: unknown
}

export interface CollaborationMessage {
  type: string
  token?: string
  roomId?: string
  noteId?: string
  update?: unknown
  version?: number
  cursor?: CursorPosition
  selection?: SelectionRange
  awareness?: Record<string, unknown>
  userId?: string
  user?: CollaborationParticipant
  participants?: CollaborationParticipant[]
  state?: { version?: number; [key: string]: unknown }
  message?: string
  [key: string]: unknown
}

type MessageHandler = (data: CollaborationMessage) => void

/**
 * Real-time Collaboration Store
 * Manages WebSocket connection for note collaboration
 */
export const useCollaborationStore = defineStore('noteCollaboration', () => {
  // State
  const socket = ref<WebSocket | null>(null)
  const isConnected = ref<boolean>(false)
  const isConnecting = ref<boolean>(false)
  const connectionError = ref<string | null>(null)
  const currentRoom = ref<string | null>(null)
  const participants = ref<CollaborationParticipant[]>([])
  const cursors = ref<Record<string, CursorPosition>>({}) // userId => cursor position
  const selections = ref<Record<string, SelectionRange>>({}) // userId => selection range
  const awareness = ref<Record<string, AwarenessData>>({}) // userId => awareness data
  const pendingUpdates = ref<unknown[]>([])
  const lastSyncVersion = ref<number>(0)

  // WebSocket URL (will be fetched from API)
  const wsUrl = ref<string | null>(null)

  // Auth store for token
  const authStore = useAuthStore()

  // Computed
  const isAuthenticated = computed<boolean>(() => !!socket.value && isConnected.value)
  const participantCount = computed<number>(() => participants.value.length)
  const otherParticipants = computed<CollaborationParticipant[]>(() => {
    const userId = authStore.user?.id
    return participants.value.filter((p: CollaborationParticipant) => p.id !== userId)
  })

  // Message handlers
  const messageHandlers = new Map<string, MessageHandler>()

  /**
   * Initialize - fetch WebSocket URL
   */
  async function initialize(): Promise<boolean> {
    try {
      const response = await api.get('/api/v1/collaboration/status')
      wsUrl.value = response.data.websocket_url
      return true
    } catch (error) {
      console.error('Failed to get collaboration status:', error)
      return false
    }
  }

  /**
   * Connect to WebSocket server
   */
  async function connect(): Promise<void> {
    if (isConnected.value || isConnecting.value) {
      return
    }

    if (!wsUrl.value) {
      const initialized = await initialize()
      if (!initialized) {
        connectionError.value = 'Failed to initialize collaboration'
        return
      }
    }

    isConnecting.value = true
    connectionError.value = null

    try {
      socket.value = new WebSocket(wsUrl.value!)

      socket.value.onopen = handleOpen
      socket.value.onmessage = handleMessage
      socket.value.onclose = handleClose
      socket.value.onerror = handleError
    } catch (error) {
      console.error('WebSocket connection error:', error)
      connectionError.value = (error as Error).message
      isConnecting.value = false
    }
  }

  /**
   * Disconnect from WebSocket server
   */
  function disconnect(): void {
    if (socket.value) {
      socket.value.close()
      socket.value = null
    }
    isConnected.value = false
    currentRoom.value = null
    participants.value = []
    cursors.value = {}
    selections.value = {}
  }

  /**
   * Handle WebSocket open
   */
  function handleOpen(): void {
    isConnecting.value = false
    isConnected.value = true

    // Authenticate with JWT token
    const token = (authStore as unknown as Record<string, unknown>).token as string | undefined
    if (token) {
      send({
        type: 'auth',
        token: token,
      })
    }
  }

  /**
   * Handle incoming WebSocket message
   */
  function handleMessage(event: MessageEvent): void {
    try {
      const data: CollaborationMessage = JSON.parse(event.data)
      const type = data.type

      // Call registered handler
      const handler = messageHandlers.get(type)
      if (handler) {
        handler(data)
      }

      // Built-in handlers
      switch (type) {
        case 'connected':
          // Connection confirmed
          break

        case 'authenticated':
          // Authentication successful
          break

        case 'joined':
          currentRoom.value = data.roomId ?? null
          participants.value = data.participants || []
          lastSyncVersion.value = data.state?.version || 0
          break

        case 'user_joined':
          participants.value = data.participants || []
          break

        case 'user_left':
          participants.value = data.participants || []
          // Clean up cursor/selection for leaving user
          if (data.user?.id) {
            delete cursors.value[data.user.id]
            delete selections.value[data.user.id]
          }
          break

        case 'cursor':
          if (data.userId) {
            cursors.value[data.userId] = {
              ...data.cursor,
              user: data.user,
            }
          }
          break

        case 'selection':
          if (data.userId) {
            selections.value[data.userId] = {
              ...data.selection,
              user: data.user,
            }
          }
          break

        case 'awareness':
          if (data.userId) {
            awareness.value[data.userId] = {
              ...data.awareness,
              user: data.user,
            }
          }
          break

        case 'error':
          console.error('Collaboration error:', data.message)
          connectionError.value = data.message ?? null
          break

        case 'pong':
          // Keepalive response
          break
      }
    } catch (error) {
      console.error('Failed to parse WebSocket message:', error)
    }
  }

  /**
   * Handle WebSocket close
   */
  function handleClose(event: CloseEvent): void {
    isConnected.value = false
    isConnecting.value = false

    // Auto-reconnect after delay if not intentional close
    if (event.code !== 1000 && currentRoom.value) {
      setTimeout(() => {
        if (!isConnected.value) {
          connect().then(() => {
            if (currentRoom.value) {
              joinRoom(currentRoom.value)
            }
          }).catch((error: unknown) => {
            console.error('Reconnection failed:', error)
          })
        }
      }, 3000)
    }
  }

  /**
   * Handle WebSocket error
   */
  function handleError(error: Event): void {
    console.error('WebSocket error:', error)
    connectionError.value = 'Connection error'
    isConnecting.value = false
  }

  /**
   * Send message through WebSocket
   */
  function send(data: Record<string, unknown>): boolean {
    if (socket.value && socket.value.readyState === WebSocket.OPEN) {
      socket.value.send(JSON.stringify(data))
      return true
    }
    return false
  }

  /**
   * Join a collaboration room (note)
   */
  async function joinRoom(noteId: string): Promise<void> {
    if (!isConnected.value) {
      await connect()
    }

    send({
      type: 'join',
      roomId: noteId,
      noteId: noteId,
    })

    currentRoom.value = noteId
  }

  /**
   * Leave current room
   */
  function leaveRoom(): void {
    if (currentRoom.value) {
      send({
        type: 'leave',
        roomId: currentRoom.value,
      })
      currentRoom.value = null
      participants.value = []
      cursors.value = {}
      selections.value = {}
    }
  }

  /**
   * Send document update
   */
  function sendUpdate(update: unknown, version: number | null = null): boolean {
    if (!currentRoom.value) return false

    return send({
      type: 'update',
      roomId: currentRoom.value,
      update: update,
      version: version || lastSyncVersion.value,
    })
  }

  /**
   * Send cursor position
   */
  function sendCursor(cursor: CursorPosition): boolean {
    if (!currentRoom.value) return false

    return send({
      type: 'cursor',
      roomId: currentRoom.value,
      cursor: cursor,
    })
  }

  /**
   * Send selection
   */
  function sendSelection(selection: SelectionRange): boolean {
    if (!currentRoom.value) return false

    return send({
      type: 'selection',
      roomId: currentRoom.value,
      selection: selection,
    })
  }

  /**
   * Send awareness update
   */
  function sendAwareness(awarenessData: Record<string, unknown>): boolean {
    if (!currentRoom.value) return false

    return send({
      type: 'awareness',
      roomId: currentRoom.value,
      awareness: awarenessData,
    })
  }

  /**
   * Request sync
   */
  function requestSync(): boolean {
    if (!currentRoom.value) return false

    return send({
      type: 'sync',
      roomId: currentRoom.value,
    })
  }

  /**
   * Register custom message handler
   */
  function onMessage(type: string, handler: MessageHandler): () => void {
    messageHandlers.set(type, handler)
    return () => messageHandlers.delete(type)
  }

  /**
   * Start keepalive ping
   */
  let pingInterval: ReturnType<typeof setInterval> | null = null
  function startPing(): void {
    if (pingInterval) return

    pingInterval = setInterval(() => {
      if (isConnected.value) {
        send({ type: 'ping' })
      }
    }, 30000) // Every 30 seconds
  }

  function stopPing(): void {
    if (pingInterval) {
      clearInterval(pingInterval)
      pingInterval = null
    }
  }

  // Watch connection state to manage ping
  watch(isConnected, (connected: boolean) => {
    if (connected) {
      startPing()
    } else {
      stopPing()
    }
  })

  /**
   * Get color for user
   */
  function getUserColor(userId: string): string {
    const participant = participants.value.find((p: CollaborationParticipant) => p.id === userId)
    return participant?.color || '#6366F1'
  }

  return {
    // State
    socket,
    isConnected,
    isConnecting,
    connectionError,
    currentRoom,
    participants,
    cursors,
    selections,
    awareness,
    lastSyncVersion,

    // Computed
    isAuthenticated,
    participantCount,
    otherParticipants,

    // Actions
    initialize,
    connect,
    disconnect,
    send,
    joinRoom,
    leaveRoom,
    sendUpdate,
    sendCursor,
    sendSelection,
    sendAwareness,
    requestSync,
    onMessage,
    getUserColor,
  }
})
