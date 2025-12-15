import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/core/api/axios'

/**
 * Real-time Collaboration Store
 * Manages WebSocket connection for note collaboration
 */
export const useCollaborationStore = defineStore('noteCollaboration', () => {
  // State
  const socket = ref(null)
  const isConnected = ref(false)
  const isConnecting = ref(false)
  const connectionError = ref(null)
  const currentRoom = ref(null)
  const participants = ref([])
  const cursors = ref({}) // userId => cursor position
  const selections = ref({}) // userId => selection range
  const awareness = ref({}) // userId => awareness data
  const pendingUpdates = ref([])
  const lastSyncVersion = ref(0)

  // WebSocket URL (will be fetched from API)
  const wsUrl = ref(null)

  // Auth store for token
  const authStore = useAuthStore()

  // Computed
  const isAuthenticated = computed(() => socket.value && isConnected.value)
  const participantCount = computed(() => participants.value.length)
  const otherParticipants = computed(() => {
    const userId = authStore.user?.id
    return participants.value.filter(p => p.id !== userId)
  })

  // Message handlers
  const messageHandlers = new Map()

  /**
   * Initialize - fetch WebSocket URL
   */
  async function initialize() {
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
  async function connect() {
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
      socket.value = new WebSocket(wsUrl.value)

      socket.value.onopen = handleOpen
      socket.value.onmessage = handleMessage
      socket.value.onclose = handleClose
      socket.value.onerror = handleError
    } catch (error) {
      console.error('WebSocket connection error:', error)
      connectionError.value = error.message
      isConnecting.value = false
    }
  }

  /**
   * Disconnect from WebSocket server
   */
  function disconnect() {
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
  function handleOpen() {
    console.log('WebSocket connected')
    isConnecting.value = false
    isConnected.value = true

    // Authenticate with JWT token
    const token = authStore.token
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
  function handleMessage(event) {
    try {
      const data = JSON.parse(event.data)
      const type = data.type

      // Call registered handler
      const handler = messageHandlers.get(type)
      if (handler) {
        handler(data)
      }

      // Built-in handlers
      switch (type) {
        case 'connected':
          console.log('Connected to collaboration server')
          break

        case 'authenticated':
          console.log('Authenticated:', data.user)
          break

        case 'joined':
          currentRoom.value = data.roomId
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
          connectionError.value = data.message
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
  function handleClose(event) {
    console.log('WebSocket closed:', event.code, event.reason)
    isConnected.value = false
    isConnecting.value = false

    // Auto-reconnect after delay if not intentional close
    if (event.code !== 1000 && currentRoom.value) {
      setTimeout(() => {
        if (!isConnected.value) {
          console.log('Attempting to reconnect...')
          connect().then(() => {
            if (currentRoom.value) {
              joinRoom(currentRoom.value)
            }
          })
        }
      }, 3000)
    }
  }

  /**
   * Handle WebSocket error
   */
  function handleError(error) {
    console.error('WebSocket error:', error)
    connectionError.value = 'Connection error'
    isConnecting.value = false
  }

  /**
   * Send message through WebSocket
   */
  function send(data) {
    if (socket.value && socket.value.readyState === WebSocket.OPEN) {
      socket.value.send(JSON.stringify(data))
      return true
    }
    return false
  }

  /**
   * Join a collaboration room (note)
   */
  async function joinRoom(noteId) {
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
  function leaveRoom() {
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
  function sendUpdate(update, version = null) {
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
  function sendCursor(cursor) {
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
  function sendSelection(selection) {
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
  function sendAwareness(awarenessData) {
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
  function requestSync() {
    if (!currentRoom.value) return false

    return send({
      type: 'sync',
      roomId: currentRoom.value,
    })
  }

  /**
   * Register custom message handler
   */
  function onMessage(type, handler) {
    messageHandlers.set(type, handler)
    return () => messageHandlers.delete(type)
  }

  /**
   * Start keepalive ping
   */
  let pingInterval = null
  function startPing() {
    if (pingInterval) return

    pingInterval = setInterval(() => {
      if (isConnected.value) {
        send({ type: 'ping' })
      }
    }, 30000) // Every 30 seconds
  }

  function stopPing() {
    if (pingInterval) {
      clearInterval(pingInterval)
      pingInterval = null
    }
  }

  // Watch connection state to manage ping
  watch(isConnected, (connected) => {
    if (connected) {
      startPing()
    } else {
      stopPing()
    }
  })

  /**
   * Get color for user
   */
  function getUserColor(userId) {
    const participant = participants.value.find(p => p.id === userId)
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
