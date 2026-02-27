import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

interface ChatRoom {
  id: string
  unread_count?: number
  last_message_at?: string
  last_message_preview?: string
  [key: string]: unknown
}

interface ChatMessage {
  id: string
  content: string
  created_at: string
  reactions?: Record<string, number>
  [key: string]: unknown
}

interface ChatUser {
  id: string
  name: string
  [key: string]: unknown
}

interface TypingUser {
  id: string
  name: string
  [key: string]: unknown
}

interface CreateRoomData {
  name?: string
  type?: string
  member_ids?: string[]
  [key: string]: unknown
}

interface SendMessageOptions {
  [key: string]: unknown
}

interface SearchParams {
  q: string
  room_id?: string
}

export const useChatStore = defineStore('chat', () => {
  // State
  const rooms = ref<ChatRoom[]>([])
  const currentRoom = ref<ChatRoom | null>(null)
  const messages = ref<ChatMessage[]>([])
  const availableUsers = ref<ChatUser[]>([])
  const loading = ref<boolean>(false)
  const messagesLoading = ref<boolean>(false)
  const error = ref<string | null>(null)
  const typingUsers = ref<TypingUser[]>([])
  const pollingInterval = ref<ReturnType<typeof setInterval> | null>(null)

  // Computed
  const totalUnread = computed<number>(() => {
    return rooms.value.reduce((sum: number, room: ChatRoom) => sum + (room.unread_count || 0), 0)
  })

  const sortedRooms = computed<ChatRoom[]>(() => {
    return [...rooms.value].sort((a: ChatRoom, b: ChatRoom) => {
      if (!a.last_message_at && !b.last_message_at) return 0
      if (!a.last_message_at) return 1
      if (!b.last_message_at) return -1
      return new Date(b.last_message_at).getTime() - new Date(a.last_message_at).getTime()
    })
  })

  // Actions
  async function fetchRooms(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/chat/rooms')
      rooms.value = response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to fetch rooms'
    } finally {
      loading.value = false
    }
  }

  async function fetchRoom(roomId: string): Promise<ChatRoom> {
    try {
      const response = await api.get(`/api/v1/chat/rooms/${roomId}`)
      currentRoom.value = response.data.data
      return response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to fetch room'
      throw err
    }
  }

  async function createRoom(data: CreateRoomData): Promise<ChatRoom> {
    try {
      const response = await api.post('/api/v1/chat/rooms', data)
      rooms.value.unshift(response.data.data)
      return response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to create room'
      throw err
    }
  }

  async function startDirectMessage(userId: string): Promise<ChatRoom> {
    try {
      const response = await api.post('/api/v1/chat/direct', { user_id: userId })
      // Check if room already exists in list
      const roomData = response.data.data
      const existingIndex = rooms.value.findIndex(r => r.id === roomData.id)
      if (existingIndex === -1) {
        rooms.value.unshift(roomData)
      }
      return roomData
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to start direct message'
      throw err
    }
  }

  async function fetchMessages(roomId: string, before: string | null = null): Promise<ChatMessage[]> {
    messagesLoading.value = true
    try {
      const params = before ? { before } : {}
      const response = await api.get(`/api/v1/chat/rooms/${roomId}/messages`, { params })
      if (before) {
        // Prepend older messages
        messages.value = [...response.data.data, ...messages.value]
      } else {
        messages.value = response.data.data
      }
      return response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to fetch messages'
      throw err
    } finally {
      messagesLoading.value = false
    }
  }

  async function sendMessage(roomId: string, content: string, options: SendMessageOptions = {}): Promise<ChatMessage> {
    try {
      const response = await api.post(`/api/v1/chat/rooms/${roomId}/messages`, {
        content,
        ...options
      })
      const msgData = response.data.data
      messages.value.push(msgData)

      // Update room's last message
      const roomIndex = rooms.value.findIndex(r => r.id === roomId)
      if (roomIndex !== -1) {
        rooms.value[roomIndex].last_message_at = msgData.created_at
        rooms.value[roomIndex].last_message_preview = content.substring(0, 100)
      }

      return msgData
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to send message'
      throw err
    }
  }

  async function editMessage(messageId: string, content: string): Promise<ChatMessage> {
    try {
      const response = await api.put(`/api/v1/chat/messages/${messageId}`, { content })
      const index = messages.value.findIndex(m => m.id === messageId)
      if (index !== -1) {
        messages.value[index] = response.data.data
      }
      return response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to edit message'
      throw err
    }
  }

  async function deleteMessage(messageId: string): Promise<void> {
    try {
      await api.delete(`/api/v1/chat/messages/${messageId}`)
      messages.value = messages.value.filter(m => m.id !== messageId)
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to delete message'
      throw err
    }
  }

  async function addReaction(messageId: string, emoji: string): Promise<void> {
    try {
      await api.post(`/api/v1/chat/messages/${messageId}/reactions`, { emoji })
      // Refresh message reactions
      const index = messages.value.findIndex(m => m.id === messageId)
      if (index !== -1) {
        const reactions: Record<string, number> = messages.value[index].reactions || {}
        reactions[emoji] = (reactions[emoji] || 0) + 1
        messages.value[index].reactions = { ...reactions }
      }
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to add reaction'
    }
  }

  async function removeReaction(messageId: string, emoji: string): Promise<void> {
    try {
      await api.delete(`/api/v1/chat/messages/${messageId}/reactions/${encodeURIComponent(emoji)}`)
      const index = messages.value.findIndex(m => m.id === messageId)
      if (index !== -1) {
        const reactions: Record<string, number> = messages.value[index].reactions || {}
        if (reactions[emoji]) {
          reactions[emoji]--
          if (reactions[emoji] <= 0) {
            delete reactions[emoji]
          }
        }
        messages.value[index].reactions = { ...reactions }
      }
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to remove reaction'
    }
  }

  async function markAsRead(roomId: string): Promise<void> {
    try {
      await api.post(`/api/v1/chat/rooms/${roomId}/read`)
      const roomIndex = rooms.value.findIndex(r => r.id === roomId)
      if (roomIndex !== -1) {
        rooms.value[roomIndex].unread_count = 0
      }
    } catch (err: unknown) {
      console.error('Failed to mark as read:', err)
    }
  }

  async function setTyping(roomId: string): Promise<void> {
    try {
      await api.post(`/api/v1/chat/rooms/${roomId}/typing`)
    } catch (err: unknown) {
      // Ignore typing errors
    }
  }

  async function fetchTyping(roomId: string): Promise<void> {
    try {
      const response = await api.get(`/api/v1/chat/rooms/${roomId}/typing`)
      typingUsers.value = response.data.data
    } catch (err: unknown) {
      // Ignore typing errors
    }
  }

  async function fetchAvailableUsers(): Promise<void> {
    try {
      const response = await api.get('/api/v1/chat/users')
      availableUsers.value = response.data.data
    } catch (err: unknown) {
      console.error('Failed to fetch users:', err)
    }
  }

  async function searchMessages(query: string, roomId: string | null = null): Promise<ChatMessage[]> {
    try {
      const params: SearchParams = { q: query }
      if (roomId) params.room_id = roomId
      const response = await api.get('/api/v1/chat/search', { params })
      return response.data.data
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to search messages'
      throw err
    }
  }

  async function leaveRoom(roomId: string): Promise<void> {
    try {
      await api.post(`/api/v1/chat/rooms/${roomId}/leave`)
      rooms.value = rooms.value.filter(r => r.id !== roomId)
      if (currentRoom.value?.id === roomId) {
        currentRoom.value = null
        messages.value = []
      }
    } catch (err: unknown) {
      error.value = (err as { response?: { data?: { error?: string } } }).response?.data?.error || 'Failed to leave room'
      throw err
    }
  }

  function selectRoom(room: ChatRoom): void {
    currentRoom.value = room
    messages.value = []
  }

  function startPolling(roomId: string, interval: number = 3000): void {
    stopPolling()
    pollingInterval.value = setInterval(async () => {
      await fetchTyping(roomId)
      // Could also poll for new messages here for real-time updates
    }, interval)
  }

  function stopPolling(): void {
    if (pollingInterval.value) {
      clearInterval(pollingInterval.value)
      pollingInterval.value = null
    }
  }

  return {
    // State
    rooms,
    currentRoom,
    messages,
    availableUsers,
    loading,
    messagesLoading,
    error,
    typingUsers,
    // Computed
    totalUnread,
    sortedRooms,
    // Actions
    fetchRooms,
    fetchRoom,
    createRoom,
    startDirectMessage,
    fetchMessages,
    sendMessage,
    editMessage,
    deleteMessage,
    addReaction,
    removeReaction,
    markAsRead,
    setTyping,
    fetchTyping,
    fetchAvailableUsers,
    searchMessages,
    leaveRoom,
    selectRoom,
    startPolling,
    stopPolling
  }
})
