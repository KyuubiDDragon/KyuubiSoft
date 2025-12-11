import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'

export const useNotificationsStore = defineStore('notifications', () => {
  const notifications = ref([])
  const unreadCount = ref(0)
  const channels = ref([])
  const preferences = ref([])
  const notificationTypes = ref({})
  const isLoading = ref(false)
  const error = ref(null)

  // Polling interval for unread count
  let pollInterval = null

  const hasUnread = computed(() => unreadCount.value > 0)

  const channelLabels = {
    in_app: 'In-App',
    email: 'E-Mail',
    webhook: 'Webhook',
    slack: 'Slack',
    telegram: 'Telegram',
  }

  async function loadNotifications(options = {}) {
    isLoading.value = true
    error.value = null
    try {
      const params = {}
      if (options.unreadOnly) params.unread = 'true'
      if (options.limit) params.limit = options.limit
      if (options.offset) params.offset = options.offset

      const response = await api.get('/api/v1/notifications', { params })
      notifications.value = response.data.data
      unreadCount.value = response.data.meta?.unread_count || 0
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to load notifications'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function loadUnreadCount() {
    try {
      const response = await api.get('/api/v1/notifications/unread-count')
      unreadCount.value = response.data.data?.count || 0
      return unreadCount.value
    } catch (err) {
      console.error('Failed to load unread count', err)
      return 0
    }
  }

  async function markAsRead(notificationId) {
    try {
      await api.post(`/api/v1/notifications/${notificationId}/read`)
      const notification = notifications.value.find(n => n.id === notificationId)
      if (notification) {
        notification.is_read = true
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
    } catch (err) {
      throw err
    }
  }

  async function markAllAsRead() {
    try {
      const response = await api.post('/api/v1/notifications/mark-all-read')
      notifications.value.forEach(n => (n.is_read = true))
      unreadCount.value = 0
      return response.data.data?.marked_count || 0
    } catch (err) {
      throw err
    }
  }

  async function deleteNotification(notificationId) {
    try {
      await api.delete(`/api/v1/notifications/${notificationId}`)
      const notification = notifications.value.find(n => n.id === notificationId)
      if (notification && !notification.is_read) {
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
      notifications.value = notifications.value.filter(n => n.id !== notificationId)
    } catch (err) {
      throw err
    }
  }

  async function loadChannels() {
    try {
      const response = await api.get('/api/v1/notifications/channels')
      channels.value = response.data.data
      return response.data.data
    } catch (err) {
      console.error('Failed to load channels', err)
      return []
    }
  }

  async function updateChannel(channelType, data) {
    try {
      const response = await api.put(`/api/v1/notifications/channels/${channelType}`, data)
      const index = channels.value.findIndex(c => c.channel_type === channelType)
      if (index !== -1) {
        channels.value[index] = response.data.data
      } else {
        channels.value.push(response.data.data)
      }
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function testChannel(channelType) {
    try {
      await api.post(`/api/v1/notifications/channels/${channelType}/test`)
      return true
    } catch (err) {
      throw err
    }
  }

  async function loadPreferences() {
    try {
      const response = await api.get('/api/v1/notifications/preferences')
      preferences.value = response.data.data?.preferences || []
      notificationTypes.value = response.data.data?.types || {}
      return response.data.data
    } catch (err) {
      console.error('Failed to load preferences', err)
      return { preferences: [], types: {} }
    }
  }

  async function updatePreference(notificationType, data) {
    try {
      const response = await api.put(`/api/v1/notifications/preferences/${notificationType}`, data)
      const index = preferences.value.findIndex(p => p.notification_type === notificationType)
      if (index !== -1) {
        preferences.value[index] = response.data.data
      } else {
        preferences.value.push(response.data.data)
      }
      return response.data.data
    } catch (err) {
      throw err
    }
  }

  async function loadTypes() {
    try {
      const response = await api.get('/api/v1/notifications/types')
      notificationTypes.value = response.data.data
      return response.data.data
    } catch (err) {
      console.error('Failed to load notification types', err)
      return {}
    }
  }

  function startPolling(intervalMs = 60000) {
    stopPolling()
    loadUnreadCount()
    pollInterval = setInterval(loadUnreadCount, intervalMs)
  }

  function stopPolling() {
    if (pollInterval) {
      clearInterval(pollInterval)
      pollInterval = null
    }
  }

  function formatDate(dateStr) {
    if (!dateStr) return '-'
    const date = new Date(dateStr)
    const now = new Date()
    const diff = now - date

    // Less than 1 minute
    if (diff < 60000) {
      return 'Gerade eben'
    }
    // Less than 1 hour
    if (diff < 3600000) {
      const mins = Math.floor(diff / 60000)
      return `vor ${mins} Minute${mins !== 1 ? 'n' : ''}`
    }
    // Less than 24 hours
    if (diff < 86400000) {
      const hours = Math.floor(diff / 3600000)
      return `vor ${hours} Stunde${hours !== 1 ? 'n' : ''}`
    }
    // Less than 7 days
    if (diff < 604800000) {
      const days = Math.floor(diff / 86400000)
      return `vor ${days} Tag${days !== 1 ? 'en' : ''}`
    }
    // Otherwise show date
    return date.toLocaleDateString('de-DE', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    })
  }

  function getPriorityColor(priority) {
    switch (priority) {
      case 'urgent':
        return 'text-red-500'
      case 'high':
        return 'text-orange-500'
      case 'normal':
        return 'text-blue-500'
      case 'low':
        return 'text-gray-400'
      default:
        return 'text-blue-500'
    }
  }

  function getTypeIcon(type) {
    const icons = {
      task_due: 'ClockIcon',
      task_assigned: 'UserPlusIcon',
      mention: 'AtSymbolIcon',
      share: 'ShareIcon',
      comment: 'ChatBubbleLeftIcon',
      project_update: 'FolderIcon',
      recurring_task: 'ArrowPathIcon',
      system: 'CogIcon',
      security: 'ShieldCheckIcon',
    }
    return icons[type] || 'BellIcon'
  }

  return {
    notifications,
    unreadCount,
    channels,
    preferences,
    notificationTypes,
    isLoading,
    error,
    hasUnread,
    channelLabels,
    loadNotifications,
    loadUnreadCount,
    markAsRead,
    markAllAsRead,
    deleteNotification,
    loadChannels,
    updateChannel,
    testChannel,
    loadPreferences,
    updatePreference,
    loadTypes,
    startPolling,
    stopPolling,
    formatDate,
    getPriorityColor,
    getTypeIcon,
  }
})
