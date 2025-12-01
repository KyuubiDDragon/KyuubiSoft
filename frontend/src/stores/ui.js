import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useUiStore = defineStore('ui', () => {
  // State
  const isDarkMode = ref(true)
  const sidebarOpen = ref(true)
  const sidebarCollapsed = ref(false)
  const notification = ref(null)
  const isLoading = ref(false)

  // Initialize from localStorage
  const storedDarkMode = localStorage.getItem('darkMode')
  if (storedDarkMode !== null) {
    isDarkMode.value = storedDarkMode === 'true'
  }

  const storedSidebarCollapsed = localStorage.getItem('sidebarCollapsed')
  if (storedSidebarCollapsed !== null) {
    sidebarCollapsed.value = storedSidebarCollapsed === 'true'
  }

  // Watchers
  watch(isDarkMode, (value) => {
    localStorage.setItem('darkMode', String(value))
    if (value) {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
  }, { immediate: true })

  watch(sidebarCollapsed, (value) => {
    localStorage.setItem('sidebarCollapsed', String(value))
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
  }
})
