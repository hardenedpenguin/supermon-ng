import axios from 'axios'
import { getCsrfService } from '@/services/CsrfTokenService'
// import type { ApiResponse, ApiError } from '@/types'

// Use the enhanced CSRF service
const csrfService = getCsrfService()

// Create axios instance with adaptive timeout
const api = axios.create({
  baseURL: '/supermon-ng/api',
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
          // Unauthorized - redirect to login
          window.location.href = '/supermon-ng/login'
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

// API endpoints
export const endpoints = {
  // Auth
  auth: {
    login: '/auth/login',
    logout: '/auth/logout',
    me: '/auth/me',
    check: '/auth/check'
  },
  
  // Nodes
  nodes: {
    list: '/nodes',
    get: (id: string) => `/nodes/${id}`,
    connect: (id: string) => `/nodes/${id}/connect`,
    disconnect: (id: string) => `/nodes/${id}/disconnect`,
    monitor: (id: string) => `/nodes/${id}/monitor`,
    localMonitor: (id: string) => `/nodes/${id}/local-monitor`,
    dtmf: (id: string) => `/nodes/${id}/dtmf`
  },
  
  // System
  system: {
    info: '/system/info',
    stats: '/system/stats',
    reload: '/system/reload',
    start: '/system/start',
    stop: '/system/stop',
    fastRestart: '/system/fast-restart',
    reboot: '/system/reboot'
  },
  
  // Database
  database: {
    status: '/database/status',
    generate: '/database/generate',
    search: '/database/search',
    get: (id: string) => `/database/${id}`
  },
  
  // ASTDB optimized endpoints (Phase 8)
  astdb: {
    stats: '/astdb/stats',
    health: '/astdb/health',
    search: '/astdb/search',
    nodes: '/astdb/nodes',
    node: (id: string) => `/astdb/node/${id}`,
    clearCache: '/astdb/clear-cache'
  },
  
  // Config
  config: {
    nodes: '/config/nodes',
    user: '/config/user',
    system: '/config/system',
    menu: '/config/menu'
  }
}

// Helper functions
export const apiHelpers = {
  // Node operations
  async connectNode(nodeId: string, targetNode: string, perm: boolean = false) {
    return api.post(endpoints.nodes.connect(nodeId), {
      localnode: nodeId,
      remotenode: targetNode,
      perm
    })
  },
  
  async disconnectNode(nodeId: string, targetNode: string) {
    return api.post(endpoints.nodes.disconnect(nodeId), {
      localnode: nodeId,
      remotenode: targetNode
    })
  },
  
  async monitorNode(nodeId: string, targetNode: string) {
    return api.post(endpoints.nodes.monitor(nodeId), {
      localnode: nodeId,
      remotenode: targetNode
    })
  },
  
  async localMonitorNode(nodeId: string, targetNode: string) {
    return api.post(endpoints.nodes.localMonitor(nodeId), {
      localnode: nodeId,
      remotenode: targetNode
    })
  },
  
  async executeDtmf(nodeId: string, dtmfCommand: string) {
    return api.post(endpoints.nodes.dtmf(nodeId), {
      localnode: nodeId,
      dtmf: dtmfCommand
    })
  },
  
  // System operations
  async reloadServices() {
    return api.post(endpoints.system.reload)
  },
  
  async startAsterisk() {
    return api.post(endpoints.system.start)
  },
  
  async stopAsterisk() {
    return api.post(endpoints.system.stop)
  },
  
  async fastRestart() {
    return api.post(endpoints.system.fastRestart)
  },
  
  async rebootServer() {
    return api.post(endpoints.system.reboot)
  },
  
  // Database operations
  async getDatabaseStatus() {
    return api.get(endpoints.database.status)
  },
  
  async generateDatabase() {
    return api.post(endpoints.database.generate)
  },
  
  async searchDatabase(query: string, limit: number = 10) {
    return api.get(endpoints.database.search, {
      params: { q: query, limit }
    })
  },
  
  async getNodeFromDatabase(nodeId: string) {
    return api.get(endpoints.database.get(nodeId))
  },
  
  // ASTDB optimized operations (Phase 8)
  async getAstdbStats() {
    return api.get(endpoints.astdb.stats)
  },
  
  async getAstdbHealth() {
    return api.get(endpoints.astdb.health)
  },
  
  async getAstdbNode(nodeId: string) {
    return api.get(endpoints.astdb.node(nodeId))
  },
  
  async getAstdbNodes(nodeIds: string[]) {
    return api.get(endpoints.astdb.nodes, {
      params: { nodes: nodeIds.join(',') }
    })
  },
  
  async searchAstdb(query: string, limit: number = 50) {
    return api.get(endpoints.astdb.search, {
      params: { q: query, limit }
    })
  },
  
  async clearAstdbCache() {
    return api.post(endpoints.astdb.clearCache)
  }
}

// Initialize CSRF token
export const initializeCsrfToken = async (): Promise<void> => {
  await csrfService.getToken()
}

export { api }
export default api
