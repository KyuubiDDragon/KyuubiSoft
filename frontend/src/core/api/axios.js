import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Track if we're currently refreshing to prevent multiple refresh attempts
let isRefreshing = false
let failedQueue = []

const processQueue = (error, token = null) => {
  failedQueue.forEach(prom => {
    if (error) {
      prom.reject(error)
    } else {
      prom.resolve(token)
    }
  })
  failedQueue = []
}

// Request interceptor
api.interceptors.request.use(
  (config) => {
    // Don't send auth header for public API routes
    const publicApiPaths = ['/documents/public/', '/tickets/public/', '/checklists/public/', '/storage/public/']
    const isPublicApi = publicApiPaths.some(path => config.url?.includes(path))

    if (!isPublicApi) {
      const token = localStorage.getItem('access_token')
      if (token) {
        config.headers.Authorization = `Bearer ${token}`
      }
    }

    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config

    // Check if we're on a public page that doesn't require authentication
    const publicPaths = ['/doc/', '/ticket/public/', '/support', '/checklist/', '/d/']
    const isPublicPage = publicPaths.some(path => window.location.pathname.includes(path))

    // If 401 and not already retried
    if (error.response?.status === 401 && !originalRequest._retry) {
      // On public pages, don't try to refresh or redirect - just return the error
      if (isPublicPage) {
        return Promise.reject(error)
      }

      // If we're already refreshing, queue this request
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject })
        }).then(token => {
          originalRequest.headers.Authorization = `Bearer ${token}`
          return api(originalRequest)
        }).catch(err => {
          return Promise.reject(err)
        })
      }

      originalRequest._retry = true
      isRefreshing = true

      const refreshToken = localStorage.getItem('refresh_token')

      if (refreshToken) {
        try {
          // Try to refresh token
          const response = await axios.post(
            `${api.defaults.baseURL}/api/v1/auth/refresh`,
            { refresh_token: refreshToken },
            {
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              }
            }
          )

          const { access_token, refresh_token: newRefreshToken } = response.data.data

          // Store new tokens
          localStorage.setItem('access_token', access_token)
          if (newRefreshToken) {
            localStorage.setItem('refresh_token', newRefreshToken)
          }

          isRefreshing = false

          // Process queued requests
          processQueue(null, access_token)

          // Retry original request
          originalRequest.headers.Authorization = `Bearer ${access_token}`
          return api(originalRequest)
        } catch (refreshError) {
          isRefreshing = false

          // Refresh failed, process queue with error
          processQueue(refreshError, null)

          // Clear tokens and redirect to login (but not on public pages)
          localStorage.removeItem('access_token')
          localStorage.removeItem('refresh_token')

          // Only redirect if not on login page or public pages
          if (!window.location.pathname.includes('/login') && !isPublicPage) {
            window.location.href = '/login'
          }

          return Promise.reject(refreshError)
        }
      } else {
        // No refresh token - reset flag and redirect (but not on public pages)
        isRefreshing = false

        localStorage.removeItem('access_token')

        if (!window.location.pathname.includes('/login') && !isPublicPage) {
          window.location.href = '/login'
        }

        return Promise.reject(error)
      }
    }

    return Promise.reject(error)
  }
)

export default api
