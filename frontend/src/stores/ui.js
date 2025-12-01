import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import api from '@/core/api/axios'

export const useUiStore = defineStore('ui', () => {
  // State
  const isDarkMode = ref(true)
  const sidebarOpen = ref(true)
  const sidebarCollapsed = ref(false)
  const notification = ref(null)
  const isLoading = ref(false)
  const isAuthenticated = ref(false)
  const settingsLoaded = ref(false)

  // Initialize from localStorage (fallback)
  const storedDarkMode = localStorage.getItem('darkMode')
  if (storedDarkMode !== null) {
    isDarkMode.value = storedDarkMode === 'true'
  }

  const storedSidebarCollapsed = localStorage.getItem('sidebarCollapsed')
  if (storedSidebarCollapsed !== null) {
    sidebarCollapsed.value = storedSidebarCollapsed === 'true'
  }

  // Apply dark mode class immediately
  if (isDarkMode.value) {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }

  // Load settings from backend
  async function loadUserSettings() {
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
  async function saveUserSetting(key, value) {
    if (!isAuthenticated.value) return

    try {
      await api.put('/api/v1/settings/user', { [key]: value })
    } catch (error) {
      console.error('Failed to save user setting:', error)
    }
  }

  // Set auth state (called from auth store)
  function setAuthenticated(value) {
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
  function toggleDarkMode() {
    isDarkMode.value = !isDarkMode.value
  }

  function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value
  }

  function toggleSidebarCollapse() {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function showNotification(message, type = 'info', duration = 3000) {
    notification.value = { message, type }

    setTimeout(() => {
      notification.value = null
    }, duration)
  }

  function showSuccess(message) {
    showNotification(message, 'success')
  }

  function showError(message) {
    showNotification(message, 'error', 5000)
  }

  function showWarning(message) {
    showNotification(message, 'warning')
  }

  function showInfo(message) {
    showNotification(message, 'info')
  }

  function startLoading() {
    isLoading.value = true
  }

  function stopLoading() {
    isLoading.value = false
  }

  return {
    // State
    isDarkMode,
    sidebarOpen,
    sidebarCollapsed,
    notification,
    isLoading,

    // Actions
    toggleDarkMode,
    toggleSidebar,
    toggleSidebarCollapse,
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
