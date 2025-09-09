// API response types and interfaces

export interface ApiResponse<T = any> {
  success: boolean
  message?: string
  data?: T
  error?: string
  errors?: Record<string, string[]>
}

export interface ApiError {
  message: string
  status?: number
  code?: string
  details?: any
}

export interface PaginatedResponse<T> {
  data: T[]
  total: number
  page: number
  per_page: number
  last_page: number
  has_more: boolean
}

export interface ApiRequestOptions {
  timeout?: number
  retries?: number
  headers?: Record<string, string>
}

export interface SSEEventData {
  type: string
  data: any
  timestamp?: string
}

export interface SSEConnection {
  url: string
  connected: boolean
  lastMessage?: string
  error?: string
}

// HTTP method types
export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH'

// API endpoint categories
export type ApiEndpoint = 
  | 'auth'
  | 'config' 
  | 'nodes'
  | 'system'
  | 'logs'
  | 'stats'
  | 'database'
