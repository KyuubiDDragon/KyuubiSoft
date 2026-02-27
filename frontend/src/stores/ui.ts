import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import api from '@/core/api/axios'

type NotificationType = 'success' | 'error' | 'warning' | 'info'

interface Notification {
  message: string
  type: NotificationType
}

export const useUiStore = defineStore('ui', () => {
  // State
  const isDarkMode = ref<boolean>(true)
  const sidebarOpen = ref<boolean>(true)
  const sidebarCollapsed = ref<boolean>(false)
  const notification = ref<Notification | null>(null)
  const isLoading = ref<boolean>(false)
  const isAuthenticated = ref<boolean>(false)
  const settingsLoaded = ref<boolean>(false)

  // Floating widgets visibility (default: hidden)
  const showQuickNotes = ref<boolean>(false)
  const showQuickCapture = ref<boolean>(false)
  const showAIAssistant = ref<boolean>(false)
  const showCommandPalette = ref<boolean>(false)

  // Initialize from localStorage (fallback)
  const storedDarkMode = localStorage.getItem('darkMode')
  if (storedDarkMode !== null) {
    isDarkMode.value = storedDarkMode === 'true'
  }

  const storedSidebarCollapsed = localStorage.getItem('sidebarCollapsed')
  if (storedSidebarCollapsed !== null) {
    sidebarCollapsed.value = storedSidebarCollapsed === 'true'
  }

  // Initialize widget visibility from localStorage
  const storedQuickNotes = localStorage.getItem('showQuickNotes')
  if (storedQuickNotes !== null) {
    showQuickNotes.value = storedQuickNotes === 'true'
  }
  const storedQuickCapture = localStorage.getItem('showQuickCapture')
  if (storedQuickCapture !== null) {
    showQuickCapture.value = storedQuickCapture === 'true'
  }
  const storedAIAssistant = localStorage.getItem('showAIAssistant')
  if (storedAIAssistant !== null) {
    showAIAssistant.value = storedAIAssistant === 'true'
  }

  // Apply dark mode class immediately
  if (isDarkMode.value) {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }

  // Load settings from backend
  async function loadUserSettings(): Promise<void> {
    if (!isAuthenticated.value) return

    try {
      const response = await api.get('/api/v1/settings/user')
      const settings = response.data.data

      if (settings) {
        // Apply server settings (override localStorage)
        if (settings.theme !== undefined) {
          isDarkMode.value = settings.theme === 'dark'
        }
        if (settings.sidebar_collapsed !== undefined) {
          sidebarCollapsed.value = settings.sidebar_collapsed
        }
      }
      settingsLoaded.value = true
    } catch (error) {
      console.error('Failed to load user settings:', error)
      settingsLoaded.value = true
    }
  }

  // Save single setting to backend
  async function saveUserSetting(key: string, value: unknown): Promise<void> {
    if (!isAuthenticated.value) return

    try {
      await api.put('/api/v1/settings/user', { [key]: value })
    } catch (error) {
      console.error('Failed to save user setting:', error)
    }
  }

  // Set auth state (called from auth store)
  function setAuthenticated(value: boolean): void {
    isAuthenticated.value = value
    if (value) {
      loadUserSettings()
    } else {
      settingsLoaded.value = false
    }
  }

  // Watchers
  watch(isDarkMode, (value) => {
    localStorage.setItem('darkMode', String(value))
    if (value) {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
    // Sync to backend
    if (settingsLoaded.value) {
      saveUserSetting('theme', value ? 'dark' : 'light')
    }
  })

  watch(sidebarCollapsed, (value) => {
    localStorage.setItem('sidebarCollapsed', String(value))
    // Sync to backend
    if (settingsLoaded.value) {
      saveUserSetting('sidebar_collapsed', value)
    }
  })

  // Actions
  function toggleDarkMode(): void {
    isDarkMode.value = !isDarkMode.value
  }

  function toggleSidebar(): void {
    sidebarOpen.value = !sidebarOpen.value
  }

  function toggleSidebarCollapse(): void {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function showNotification(message: string, type: NotificationType = 'info', duration = 3000): void {
    notification.value = { message, type }

    setTimeout(() => {
      notification.value = null
    }, duration)
  }

  function showSuccess(message: string): void {
    showNotification(message, 'success')
  }

  function showError(message: string): void {
    showNotification(message, 'error', 5000)
  }

  function showWarning(message: string): void {
    showNotification(message, 'warning')
  }

  function showInfo(message: string): void {
    showNotification(message, 'info')
  }

  function startLoading(): void {
    isLoading.value = true
  }

  function stopLoading(): void {
    isLoading.value = false
  }

  function toggleQuickNotes(): void {
    showQuickNotes.value = !showQuickNotes.value
    localStorage.setItem('showQuickNotes', String(showQuickNotes.value))
  }

  function toggleQuickCapture(): void {
    showQuickCapture.value = !showQuickCapture.value
    localStorage.setItem('showQuickCapture', String(showQuickCapture.value))
  }

  function toggleAIAssistant(): void {
    showAIAssistant.value = !showAIAssistant.value
    localStorage.setItem('showAIAssistant', String(showAIAssistant.value))
  }

  return {
    // State
    isDarkMode,
    sidebarOpen,
    sidebarCollapsed,
    notification,
    isLoading,
    showQuickNotes,
    showQuickCapture,
    showAIAssistant,
    showCommandPalette,

    // Actions
    toggleDarkMode,
    toggleSidebar,
    toggleSidebarCollapse,
    toggleQuickNotes,
    toggleQuickCapture,
    toggleAIAssistant,
    showNotification,
    showSuccess,
    showError,
    showWarning,
    showInfo,
    startLoading,
    stopLoading,
    setAuthenticated,
    loadUserSettings,
  }
})
