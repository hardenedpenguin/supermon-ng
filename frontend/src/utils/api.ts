import axios from 'axios'
import type { ApiResponse, ApiError } from '@/types'

// Create axios instance
const api = axios.create({
  baseURL: '/api',
  timeout: 10000,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Request interceptor
api.interceptors.request.use(
  (config) => {
    // Add any request modifications here
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
  (error) => {
    // Handle common errors
    if (error.response) {
      // Server responded with error status
      switch (error.response.status) {
        case 401:
          // Unauthorized - redirect to login
          window.location.href = '/login'
          break
        case 403:
          // Forbidden
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
      target_node: targetNode,
      perm
    })
  },
  
  async disconnectNode(nodeId: string, targetNode: string) {
    return api.post(endpoints.nodes.disconnect(nodeId), {
      target_node: targetNode
    })
  },
  
  async monitorNode(nodeId: string, targetNode: string) {
    return api.post(endpoints.nodes.monitor(nodeId), {
      target_node: targetNode
    })
  },
  
  async localMonitorNode(nodeId: string, targetNode: string) {
    return api.post(endpoints.nodes.localMonitor(nodeId), {
      target_node: targetNode
    })
  },
  
  async executeDtmf(nodeId: string, targetNode: string) {
    return api.post(endpoints.nodes.dtmf(nodeId), {
      target_node: targetNode
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
  }
}

export { api }
export default api
