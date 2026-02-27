import axios, { type AxiosInstance, type AxiosError, type InternalAxiosRequestConfig, type AxiosResponse } from 'axios'

/** Queued request that is waiting for a token refresh to complete */
interface QueuedRequest {
  resolve: (value: string | PromiseLike<string>) => void
  reject: (reason?: unknown) => void
}

/** Extended request config with retry flag */
interface RetryableRequestConfig extends InternalAxiosRequestConfig {
  _retry?: boolean
}

const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// CSRF token storage
let csrfToken: string | null = null

// Track if we're currently refreshing to prevent multiple refresh attempts
let isRefreshing: boolean = false
let failedQueue: QueuedRequest[] = []

const processQueue = (error: unknown, token: string | null = null): void => {
  failedQueue.forEach((prom: QueuedRequest) => {
    if (error) {
      prom.reject(error)
    } else {
      prom.resolve(token as string)
    }
  })
  failedQueue = []
}

// Request interceptor
api.interceptors.request.use(
  (config: InternalAxiosRequestConfig): InternalAxiosRequestConfig => {
    // Don't send auth header for public API routes
    const publicApiPaths: string[] = ['/documents/public/', '/tickets/public/', '/checklists/public/', '/storage/public/', '/s/']
    const isPublicApi: boolean = publicApiPaths.some((path: string) => config.url?.includes(path))

    if (!isPublicApi) {
      const token: string | null = localStorage.getItem('access_token')
      if (token) {
        config.headers.Authorization = `Bearer ${token}`
      }

      // Attach CSRF token to state-changing requests
      if (csrfToken && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(config.method?.toUpperCase() as string)) {
        config.headers['X-CSRF-Token'] = csrfToken
      }
    }

    return config
  },
  (error: AxiosError): Promise<never> => {
    return Promise.reject(error)
  }
)

// Response interceptor
api.interceptors.response.use(
  (response: AxiosResponse): AxiosResponse => {
    // Capture CSRF token from response headers
    const token: string | undefined = response.headers['x-csrf-token'] as string | undefined
    if (token) {
      csrfToken = token
    }
    return response
  },
  async (error: AxiosError): Promise<AxiosResponse | never> => {
    const originalRequest = error.config as RetryableRequestConfig | undefined

    // Check if we're on a public page that doesn't require authentication
    const publicPagePaths: string[] = ['/doc/', '/ticket/public/', '/support', '/checklist/', '/d/', '/login', '/setup', '/share/', '/s/']
    const isPublicPage: boolean = publicPagePaths.some((path: string) => window.location.pathname.includes(path))

    // Check if the original request was to a public API endpoint
    const publicApiPaths: string[] = ['/documents/public/', '/tickets/public/', '/checklists/public/', '/storage/public/', '/setup/', '/public/', '/s/']
    const isPublicApiRequest: boolean = publicApiPaths.some((path: string) => originalRequest?.url?.includes(path))

    // If 401 and not already retried
    if (error.response?.status === 401 && originalRequest && !originalRequest._retry) {
      // On public pages or for public API requests, don't try to refresh or redirect - just return the error
      if (isPublicPage || isPublicApiRequest) {
        return Promise.reject(error)
      }

      // If we're already refreshing, queue this request
      if (isRefreshing) {
        return new Promise<string>((resolve, reject) => {
          failedQueue.push({ resolve, reject })
        }).then((token: string) => {
          originalRequest.headers.Authorization = `Bearer ${token}`
          return api(originalRequest)
        }).catch((err: unknown) => {
          return Promise.reject(err)
        })
      }

      originalRequest._retry = true
      isRefreshing = true

      const refreshToken: string | null = localStorage.getItem('refresh_token')

      if (refreshToken) {
        try {
          // Try to refresh token
          const response: AxiosResponse = await axios.post(
            `${api.defaults.baseURL}/api/v1/auth/refresh`,
            { refresh_token: refreshToken },
            {
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
              }
            }
          )

          const { access_token, refresh_token: newRefreshToken } = response.data.data as {
            access_token: string
            refresh_token?: string
          }

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
        } catch (refreshError: unknown) {
          isRefreshing = false

          // Refresh failed, process queue with error
          processQueue(refreshError, null)

          // Clear tokens and redirect to login (but not on public pages or public API requests)
          localStorage.removeItem('access_token')
          localStorage.removeItem('refresh_token')

          // Only redirect if not on login page, public pages, or public API requests
          if (!isPublicPage && !isPublicApiRequest) {
            window.location.href = '/login'
          }

          return Promise.reject(refreshError)
        }
      } else {
        // No refresh token - reset flag and redirect (but not on public pages or public API requests)
        isRefreshing = false

        localStorage.removeItem('access_token')

        if (!isPublicPage && !isPublicApiRequest) {
          window.location.href = '/login'
        }

        return Promise.reject(error)
      }
    }

    return Promise.reject(error)
  }
)

export default api
