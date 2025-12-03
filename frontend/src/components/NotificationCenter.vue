<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import {
  BellIcon,
  CheckIcon,
  TrashIcon,
  XMarkIcon,
  ExclamationCircleIcon,
  InformationCircleIcon,
  CheckCircleIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const api = useApi()

const isOpen = ref(false)
const notifications = ref([])
const unreadCount = ref(0)
const loading = ref(false)
const pollInterval = ref(null)

onMounted(() => {
  fetchUnreadCount()
  // Poll for new notifications every 30 seconds
  pollInterval.value = setInterval(fetchUnreadCount, 30000)
})

onUnmounted(() => {
  if (pollInterval.value) {
    clearInterval(pollInterval.value)
  }
})

async function fetchUnreadCount() {
  try {
    const response = await api.get('/api/v1/notifications/unread-count')
    unreadCount.value = response.data.data.count
  } catch (error) {
    console.error('Failed to fetch unread count:', error)
  }
}

async function fetchNotifications() {
  loading.value = true
  try {
    const response = await api.get('/api/v1/notifications', {
      params: { per_page: 20 }
    })
    notifications.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch notifications:', error)
  } finally {
    loading.value = false
  }
}

async function markAsRead(notification) {
  if (notification.is_read) return

  try {
    await api.post(`/api/v1/notifications/${notification.id}/read`)
    notification.is_read = true
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  } catch (error) {
    console.error('Failed to mark as read:', error)
  }
}

async function markAllAsRead() {
  try {
    await api.post('/api/v1/notifications/mark-all-read')
    notifications.value.forEach(n => n.is_read = true)
    unreadCount.value = 0
  } catch (error) {
    console.error('Failed to mark all as read:', error)
  }
}

async function deleteNotification(notification) {
  try {
    await api.delete(`/api/v1/notifications/${notification.id}`)
    notifications.value = notifications.value.filter(n => n.id !== notification.id)
    if (!notification.is_read) {
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    }
  } catch (error) {
    console.error('Failed to delete notification:', error)
  }
}

async function clearAll() {
  try {
    await api.delete('/api/v1/notifications/clear')
    notifications.value = []
    unreadCount.value = 0
  } catch (error) {
    console.error('Failed to clear notifications:', error)
  }
}

function handleClick(notification) {
  markAsRead(notification)
  if (notification.link) {
    router.push(notification.link)
    isOpen.value = false
  }
}

function toggleOpen() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    fetchNotifications()
  }
}

function getIcon(type) {
  switch (type) {
    case 'uptime_alert':
      return ExclamationCircleIcon
    case 'kanban_reminder':
      return ClockIcon
    case 'webhook_alert':
      return InformationCircleIcon
    case 'system_alert':
      return CheckCircleIcon
    default:
      return BellIcon
  }
}

function getIconColor(type) {
  switch (type) {
    case 'uptime_alert':
      return 'text-red-400'
    case 'kanban_reminder':
      return 'text-yellow-400'
    case 'webhook_alert':
      return 'text-blue-400'
    case 'system_alert':
      return 'text-green-400'
    default:
      return 'text-gray-400'
  }
}

function formatTime(dateStr) {
  const date = new Date(dateStr)
  const now = new Date()
  const diff = now - date

  if (diff < 60000) return 'Gerade eben'
  if (diff < 3600000) return `vor ${Math.floor(diff / 60000)} Min.`
  if (diff < 86400000) return `vor ${Math.floor(diff / 3600000)} Std.`
  if (diff < 604800000) return `vor ${Math.floor(diff / 86400000)} Tagen`

  return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
}
</script>

<template>
  <div class="relative">
    <!-- Bell Button -->
    <button
      @click="toggleOpen"
      class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-dark-700 transition-colors relative"
      title="Benachrichtigungen"
    >
      <BellIcon class="w-5 h-5" />
      <span
        v-if="unreadCount > 0"
        class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-red-500 rounded-full text-xs flex items-center justify-center text-white font-medium"
      >
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </button>

    <!-- Dropdown Panel -->
    <Transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div
        v-if="isOpen"
        class="absolute right-0 mt-2 w-96 bg-dark-800 border border-dark-700 rounded-xl shadow-2xl z-50 overflow-hidden"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 bg-dark-700 border-b border-dark-600">
          <div class="flex items-center gap-2">
            <BellIcon class="w-5 h-5 text-primary-400" />
            <h3 class="font-semibold text-white">Benachrichtigungen</h3>
            <span
              v-if="unreadCount > 0"
              class="px-2 py-0.5 bg-primary-600/20 text-primary-400 text-xs rounded-full"
            >
              {{ unreadCount }} neu
            </span>
          </div>
          <div class="flex items-center gap-1">
            <button
              v-if="notifications.some(n => !n.is_read)"
              @click="markAllAsRead"
              class="p-1.5 text-gray-400 hover:text-green-400 rounded transition-colors"
              title="Alle als gelesen markieren"
            >
              <CheckIcon class="w-4 h-4" />
            </button>
            <button
              v-if="notifications.length > 0"
              @click="clearAll"
              class="p-1.5 text-gray-400 hover:text-red-400 rounded transition-colors"
              title="Alle löschen"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Notifications List -->
        <div class="max-h-96 overflow-y-auto">
          <div
            v-if="loading"
            class="flex items-center justify-center py-8"
          >
            <div class="w-6 h-6 border-2 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
          </div>

          <div
            v-else-if="notifications.length === 0"
            class="text-center py-8"
          >
            <BellIcon class="w-12 h-12 text-gray-600 mx-auto mb-2" />
            <p class="text-gray-500 text-sm">Keine Benachrichtigungen</p>
          </div>

          <div
            v-for="notification in notifications"
            :key="notification.id"
            @click="handleClick(notification)"
            class="flex items-start gap-3 px-4 py-3 border-b border-dark-700 hover:bg-dark-700/50 transition-colors cursor-pointer group"
            :class="{ 'bg-dark-700/30': !notification.is_read }"
          >
            <!-- Icon -->
            <div
              class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
              :class="notification.is_read ? 'bg-dark-600' : 'bg-dark-600'"
            >
              <component
                :is="getIcon(notification.type)"
                class="w-4 h-4"
                :class="getIconColor(notification.type)"
              />
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <p
                  class="text-sm font-medium truncate"
                  :class="notification.is_read ? 'text-gray-300' : 'text-white'"
                >
                  {{ notification.title }}
                </p>
                <button
                  @click.stop="deleteNotification(notification)"
                  class="flex-shrink-0 p-1 text-gray-500 hover:text-red-400 rounded opacity-0 group-hover:opacity-100 transition-all"
                >
                  <XMarkIcon class="w-3.5 h-3.5" />
                </button>
              </div>
              <p
                v-if="notification.message"
                class="text-xs text-gray-500 mt-0.5 line-clamp-2"
              >
                {{ notification.message }}
              </p>
              <p class="text-xs text-gray-600 mt-1">
                {{ formatTime(notification.created_at) }}
              </p>
            </div>

            <!-- Unread indicator -->
            <div
              v-if="!notification.is_read"
              class="flex-shrink-0 w-2 h-2 bg-primary-500 rounded-full mt-2"
            ></div>
          </div>
        </div>

        <!-- Footer -->
        <div
          v-if="notifications.length > 0"
          class="px-4 py-2 bg-dark-700/50 border-t border-dark-600"
        >
          <router-link
            to="/settings"
            @click="isOpen = false"
            class="text-xs text-gray-400 hover:text-primary-400 transition-colors"
          >
            Benachrichtigungseinstellungen
          </router-link>
        </div>
      </div>
    </Transition>

    <!-- Backdrop -->
    <div
      v-if="isOpen"
      @click="isOpen = false"
      class="fixed inset-0 z-40"
    ></div>
  </div>
</template>
