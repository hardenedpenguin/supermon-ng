import axios from 'axios'
import { getCsrfService } from '@/services/CsrfTokenService'
import { appUrl } from '@/utils/basePath'

// Use the enhanced CSRF service
const csrfService = getCsrfService()

// Create axios instance with adaptive timeout
const api = axios.create({
  baseURL: appUrl('api/v1'),
  timeout: 5000, // Reduced from 10s to 5s for better responsiveness
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Request interceptor
api.interceptors.request.use(
  async (config) => {
    // Add CSRF token for POST, PUT, DELETE, PATCH requests
    if (config.method && ['post', 'put', 'delete', 'patch'].includes(config.method.toLowerCase())) {
      // Skip CSRF token for bubble chart endpoint since it's disabled on backend
      if (config.url === '/config/bubblechart') {
        return config
      }
      
      try {
        // Always get a fresh token - don't rely on cached value
        const token = await csrfService.getToken()
        if (token) {
          config.headers = config.headers || {}
          config.headers['X-CSRF-Token'] = token
        } else {
          console.warn('CSRF token is empty, attempting refresh')
          const refreshedToken = await csrfService.refreshToken()
          if (refreshedToken) {
            config.headers = config.headers || {}
            config.headers['X-CSRF-Token'] = refreshedToken
          }
        }
      } catch (error) {
        console.error('Failed to get CSRF token:', error)
        // Try one more time to refresh
        try {
          const refreshedToken = await csrfService.refreshToken()
          if (refreshedToken) {
            config.headers = config.headers || {}
            config.headers['X-CSRF-Token'] = refreshedToken
          }
        } catch (refreshError) {
          console.error('Failed to refresh CSRF token:', refreshError)
        }
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
  (response) => {
    return response
  },
  async (error) => {
    // Handle common errors
    if (error.response) {
      // Server responded with error status
      switch (error.response.status) {
        case 401:
          // Anonymous read-only browsing is allowed; do not force login on 401.
          break
        case 403:
          // Check if it's a CSRF token error
          if (error.response.data?.message?.includes('CSRF token validation failed')) {
            // Refresh CSRF token and retry the request
            try {
              csrfService.clearToken()
              const newToken = await csrfService.refreshToken()
              if (newToken && error.config) {
                error.config.headers['X-CSRF-Token'] = newToken
                return api.request(error.config)
              }
            } catch (refreshError) {
              console.error('Failed to refresh CSRF token:', refreshError)
            }
          }
          console.error('Access forbidden')
          break
        case 404:
          // Not found
          console.error('Resource not found')
          break
        case 500:
          // Server error
          console.error('Server error')
          break
        default:
          console.error('API error:', error.response.status, error.response.data)
      }
    } else if (error.request) {
      // Request was made but no response received
      // Suppress timeout errors for real-time data fetching
      if (error.code !== 'ECONNABORTED') {
        console.error('Network error - no response received')
      }
    } else {
      // Something else happened
      console.error('Request error:', error.message)
    }
    
    return Promise.reject(error)
  }
)

/** Node API paths used by realTime store (body: localnode, remotenode). */
export const endpoints = {
  nodes: {
    connect: '/nodes/connect',
    disconnect: '/nodes/disconnect',
    monitor: '/nodes/monitor',
    localMonitor: '/nodes/local-monitor',
    websocketPorts: '/nodes/websocket/ports',
  },
}

// Initialize CSRF token
export const initializeCsrfToken = async (): Promise<void> => {
  await csrfService.getToken()
}

export { api }
export default api
