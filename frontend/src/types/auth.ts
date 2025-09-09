// Authentication and authorization types

export interface User {
  id?: string
  name: string
  username?: string
  email?: string
  permissions: Record<string, boolean>
  preferences?: UserPreferences
  roles?: string[]
  created_at?: string
  updated_at?: string
}

export interface UserPreferences {
  showDetail: boolean
  displayedNodes: number
  showCount: boolean
  showAll: boolean
  theme?: string
  autoRefresh?: boolean
  refreshInterval?: number
  notifications?: NotificationPreferences
}

export interface NotificationPreferences {
  enabled: boolean
  sound: boolean
  desktop: boolean
  types: {
    nodeStatus: boolean
    systemAlerts: boolean
    errors: boolean
    warnings: boolean
  }
}

export interface LoginCredentials {
  username: string
  password: string
  remember?: boolean
}

export interface LoginResponse {
  success: boolean
  message?: string
  data?: {
    user: User
    permissions: Record<string, boolean>
    authenticated: boolean
    token?: string
    expires_at?: string
  }
}

export interface Permission {
  name: string
  description?: string
  category?: string
}

export interface Role {
  name: string
  permissions: string[]
  description?: string
}

// Authentication states
export type AuthState = 'loading' | 'authenticated' | 'unauthenticated' | 'error'

// Permission categories
export type PermissionCategory = 
  | 'system'
  | 'nodes'
  | 'config'
  | 'logs'
  | 'stats'
  | 'admin'
