import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const useChatStore = defineStore('chat', () => {
  // State
  const rooms = ref([])
  const currentRoom = ref(null)
  const messages = ref([])
  const availableUsers = ref([])
  const loading = ref(false)
  const messagesLoading = ref(false)
  const error = ref(null)
  const typingUsers = ref([])
  const pollingInterval = ref(null)

  // Computed
  const totalUnread = computed(() => {
    return rooms.value.reduce((sum, room) => sum + (room.unread_count || 0), 0)
  })

  const sortedRooms = computed(() => {
    return [...rooms.value].sort((a, b) => {
      if (!a.last_message_at && !b.last_message_at) return 0
      if (!a.last_message_at) return 1
      if (!b.last_message_at) return -1
      return new Date(b.last_message_at) - new Date(a.last_message_at)
    })
  })

  // Actions
  async function fetchRooms() {
    loading.value = true
    error.value = null
    try {
      const response = await api.get('/api/v1/chat/rooms')
      rooms.value = response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch rooms'
    } finally {
      loading.value = false
    }
  }

  async function fetchRoom(roomId) {
    try {
      const response = await api.get(`/api/v1/chat/rooms/${roomId}`)
      currentRoom.value = response.data
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch room'
      throw err
    }
  }

  async function createRoom(data) {
    try {
      const response = await api.post('/api/v1/chat/rooms', data)
      rooms.value.unshift(response.data)
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create room'
      throw err
    }
  }

  async function startDirectMessage(userId) {
    try {
      const response = await api.post('/api/v1/chat/direct', { user_id: userId })
      // Check if room already exists in list
      const existingIndex = rooms.value.findIndex(r => r.id === response.data.id)
      if (existingIndex === -1) {
        rooms.value.unshift(response.data)
      }
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to start direct message'
      throw err
    }
  }

  async function fetchMessages(roomId, before = null) {
    messagesLoading.value = true
    try {
      const params = before ? { before } : {}
      const response = await api.get(`/api/v1/chat/rooms/${roomId}/messages`, { params })
      if (before) {
        // Prepend older messages
        messages.value = [...response.data, ...messages.value]
      } else {
        messages.value = response.data
      }
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch messages'
      throw err
    } finally {
      messagesLoading.value = false
    }
  }

  async function sendMessage(roomId, content, options = {}) {
    try {
      const response = await api.post(`/api/v1/chat/rooms/${roomId}/messages`, {
        content,
        ...options
      })
      messages.value.push(response.data)

      // Update room's last message
      const roomIndex = rooms.value.findIndex(r => r.id === roomId)
      if (roomIndex !== -1) {
        rooms.value[roomIndex].last_message_at = response.data.created_at
        rooms.value[roomIndex].last_message_preview = content.substring(0, 100)
      }

      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to send message'
      throw err
    }
  }

  async function editMessage(messageId, content) {
    try {
      const response = await api.put(`/api/v1/chat/messages/${messageId}`, { content })
      const index = messages.value.findIndex(m => m.id === messageId)
      if (index !== -1) {
        messages.value[index] = response.data
      }
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to edit message'
      throw err
    }
  }

  async function deleteMessage(messageId) {
    try {
      await api.delete(`/api/v1/chat/messages/${messageId}`)
      messages.value = messages.value.filter(m => m.id !== messageId)
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete message'
      throw err
    }
  }

  async function addReaction(messageId, emoji) {
    try {
      await api.post(`/api/v1/chat/messages/${messageId}/reactions`, { emoji })
      // Refresh message reactions
      const index = messages.value.findIndex(m => m.id === messageId)
      if (index !== -1) {
        const reactions = messages.value[index].reactions || {}
        reactions[emoji] = (reactions[emoji] || 0) + 1
        messages.value[index].reactions = { ...reactions }
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to add reaction'
    }
  }

  async function removeReaction(messageId, emoji) {
    try {
      await api.delete(`/api/v1/chat/messages/${messageId}/reactions/${encodeURIComponent(emoji)}`)
      const index = messages.value.findIndex(m => m.id === messageId)
      if (index !== -1) {
        const reactions = messages.value[index].reactions || {}
        if (reactions[emoji]) {
          reactions[emoji]--
          if (reactions[emoji] <= 0) {
            delete reactions[emoji]
          }
        }
        messages.value[index].reactions = { ...reactions }
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to remove reaction'
    }
  }

  async function markAsRead(roomId) {
    try {
      await api.post(`/api/v1/chat/rooms/${roomId}/read`)
      const roomIndex = rooms.value.findIndex(r => r.id === roomId)
      if (roomIndex !== -1) {
        rooms.value[roomIndex].unread_count = 0
      }
    } catch (err) {
      console.error('Failed to mark as read:', err)
    }
  }

  async function setTyping(roomId) {
    try {
      await api.post(`/api/v1/chat/rooms/${roomId}/typing`)
    } catch (err) {
      // Ignore typing errors
    }
  }

  async function fetchTyping(roomId) {
    try {
      const response = await api.get(`/api/v1/chat/rooms/${roomId}/typing`)
      typingUsers.value = response.data
    } catch (err) {
      // Ignore typing errors
    }
  }

  async function fetchAvailableUsers() {
    try {
      const response = await api.get('/api/v1/chat/users')
      availableUsers.value = response.data
    } catch (err) {
      console.error('Failed to fetch users:', err)
    }
  }

  async function searchMessages(query, roomId = null) {
    try {
      const params = { q: query }
      if (roomId) params.room_id = roomId
      const response = await api.get('/api/v1/chat/search', { params })
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to search messages'
      throw err
    }
  }

  async function leaveRoom(roomId) {
    try {
      await api.post(`/api/v1/chat/rooms/${roomId}/leave`)
      rooms.value = rooms.value.filter(r => r.id !== roomId)
      if (currentRoom.value?.id === roomId) {
        currentRoom.value = null
        messages.value = []
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to leave room'
      throw err
    }
  }

  function selectRoom(room) {
    currentRoom.value = room
    messages.value = []
  }

  function startPolling(roomId, interval = 3000) {
    stopPolling()
    pollingInterval.value = setInterval(async () => {
      await fetchTyping(roomId)
      // Could also poll for new messages here for real-time updates
    }, interval)
  }

  function stopPolling() {
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
