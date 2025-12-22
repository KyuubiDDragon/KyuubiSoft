import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'

export const useAuthStore = defineStore('auth', () => {
  const uiStore = useUiStore()
  // State
  const user = ref(null)
  const accessToken = ref(null)
  const refreshToken = ref(null)
  const isLoading = ref(false)
  const isInitialized = ref(false)

  // Getters
  const isAuthenticated = computed(() => !!accessToken.value && !!user.value)
  const userRoles = computed(() => user.value?.roles || [])
  const userPermissions = computed(() => user.value?.permissions || [])

  // Actions
  function hasPermission(permission) {
    if (userRoles.value.includes('owner')) return true
    if (userPermissions.value.includes(permission)) return true

    // Check wildcard
    const parts = permission.split('.')
    if (parts.length >= 2) {
      const wildcard = `${parts[0]}.*`
      if (userPermissions.value.includes(wildcard)) return true
    }

    return false
  }

  function hasRole(role) {
    return userRoles.value.includes(role)
  }

  async function initialize() {
    if (isInitialized.value) return

    // Check if we're on a public page - don't try to authenticate
    const publicPaths = ['/doc/', '/ticket/public/', '/support', '/checklist/', '/d/', '/share/', '/setup']
    const isPublicPage = publicPaths.some(path => window.location.pathname.includes(path))

    // On public pages, just mark as initialized without trying to authenticate
    if (isPublicPage) {
      isInitialized.value = true
      return
    }

    const storedToken = localStorage.getItem('access_token')
    const storedRefresh = localStorage.getItem('refresh_token')

    if (storedToken) {
      accessToken.value = storedToken
      refreshToken.value = storedRefresh

      try {
        await fetchUser()
      } catch (error) {
        // Token invalid, try refresh
        if (storedRefresh) {
          try {
            await refresh()
          } catch {
            logout()
          }
        } else {
          logout()
        }
      }
    }

    isInitialized.value = true
  }

  async function login(credentials) {
    isLoading.value = true

    try {
      const response = await api.post('/api/v1/auth/login', credentials)
      const data = response.data.data

      if (data.requires_2fa) {
        return { requires2FA: true }
      }

      setTokens(data.access_token, data.refresh_token)
      user.value = data.user

      return { success: true }
    } finally {
      isLoading.value = false
    }
  }

  async function register(userData) {
    isLoading.value = true

    try {
      const response = await api.post('/api/v1/auth/register', userData)
      const data = response.data.data

      // Check if registration requires approval
      if (data.pending_approval) {
        return {
          success: true,
          pendingApproval: true,
          message: data.message
        }
      }

      // Legacy: if tokens are returned (admin-created users)
      if (data.access_token) {
        setTokens(data.access_token, data.refresh_token)
        user.value = data.user
      }

      return { success: true }
    } finally {
      isLoading.value = false
    }
  }

  async function refresh() {
    if (!refreshToken.value) {
      throw new Error('No refresh token')
    }

    const response = await api.post('/api/v1/auth/refresh', {
      refresh_token: refreshToken.value,
    })

    const data = response.data.data
    setTokens(data.access_token, data.refresh_token)

    await fetchUser()
  }

  async function fetchUser() {
    const response = await api.get('/api/v1/auth/me')
    user.value = response.data.data
    uiStore.setAuthenticated(true)
  }

  function logout() {
    // Call logout endpoint (fire and forget)
    if (accessToken.value) {
      api.post('/api/v1/auth/logout', {
        refresh_token: refreshToken.value,
      }).catch(() => {})
    }

    clearTokens()
    user.value = null
    uiStore.setAuthenticated(false)
  }

  function setTokens(access, refresh) {
    accessToken.value = access
    refreshToken.value = refresh
    localStorage.setItem('access_token', access)
    if (refresh) {
      localStorage.setItem('refresh_token', refresh)
    }
  }

  function clearTokens() {
    accessToken.value = null
    refreshToken.value = null
    localStorage.removeItem('access_token')
    localStorage.removeItem('refresh_token')
  }

  return {
    // State
    user,
    accessToken,
    isLoading,
    isInitialized,

    // Getters
    isAuthenticated,
    userRoles,
    userPermissions,

    // Actions
    hasPermission,
    hasRole,
    initialize,
    login,
    register,
    refresh,
    fetchUser,
    logout,
  }
})
