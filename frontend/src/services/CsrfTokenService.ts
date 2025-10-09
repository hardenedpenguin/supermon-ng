/**
 * Enhanced CSRF Token Service
 * 
 * Provides intelligent CSRF token caching, automatic refresh, and request deduplication
 * to reduce redundant token requests and improve performance.
 */

import { ref, computed, onUnmounted } from 'vue'
import { api } from '@/utils/api'

export interface TokenInfo {
  token: string
  timestamp: number
  expiresAt: number
  refreshPromise?: Promise<string>
}

export interface CsrfConfig {
  tokenLifetime: number      // Token lifetime in milliseconds
  refreshThreshold: number   // Refresh token when this much time is left (ms)
  maxRetries: number        // Maximum retry attempts
  retryDelay: number        // Delay between retries (ms)
  requestTimeout: number    // Request timeout (ms)
}

export class CsrfTokenService {
  private config: CsrfConfig
  private tokenInfo: TokenInfo | null = null
  private refreshPromise: Promise<string> | null = null
  private requestQueue: Set<Promise<string>> = new Set()

  constructor(config: Partial<CsrfConfig> = {}) {
    this.config = {
      tokenLifetime: 3600000,    // 1 hour default
      refreshThreshold: 300000,  // Refresh 5 minutes before expiry
      maxRetries: 3,
      retryDelay: 1000,
      requestTimeout: 5000,
      ...config
    }
  }

  /**
   * Get a valid CSRF token, refreshing if necessary
   */
  public async getToken(): Promise<string> {
    // Check if we have a valid token
    if (this.isTokenValid()) {
      return this.tokenInfo!.token
    }

    // Check if token needs refresh
    if (this.shouldRefreshToken()) {
      return this.refreshToken()
    }

    // No token or expired - fetch new one
    return this.fetchNewToken()
  }

  /**
   * Get token synchronously (may return expired token)
   */
  public getTokenSync(): string | null {
    return this.tokenInfo?.token || null
  }

  /**
   * Force refresh the token
   */
  public async refreshToken(): Promise<string> {
    // If already refreshing, wait for that promise
    if (this.refreshPromise) {
      return this.refreshPromise
    }

    // Start new refresh
    this.refreshPromise = this.performTokenRefresh()
    
    try {
      const token = await this.refreshPromise
      return token
    } finally {
      this.refreshPromise = null
    }
  }

  /**
   * Clear the current token
   */
  public clearToken(): void {
    this.tokenInfo = null
    this.refreshPromise = null
  }

  /**
   * Check if current token is valid
   */
  private isTokenValid(): boolean {
    if (!this.tokenInfo) {
      return false
    }

    const now = Date.now()
    return now < this.tokenInfo.expiresAt
  }

  /**
   * Check if token should be refreshed
   */
  private shouldRefreshToken(): boolean {
    if (!this.tokenInfo) {
      return false
    }

    const now = Date.now()
    const timeUntilExpiry = this.tokenInfo.expiresAt - now
    return timeUntilExpiry <= this.config.refreshThreshold
  }

  /**
   * Fetch a new token from the server
   */
  private async fetchNewToken(): Promise<string> {
    const startTime = Date.now()
    
    try {
      const response = await this.makeTokenRequest()
      const token = response.data.csrf_token || ''
      
      if (!token) {
        throw new Error('No CSRF token in response')
      }

      this.storeToken(token)
      
      const duration = Date.now() - startTime
      
      return token
    } catch (error) {
      const duration = Date.now() - startTime
      throw error
    }
  }

  /**
   * Perform token refresh
   */
  private async performTokenRefresh(): Promise<string> {
    
    // Clear existing token info but keep the old token as fallback
    const oldToken = this.tokenInfo?.token
    
    try {
      const newToken = await this.fetchNewToken()
      return newToken
    } catch (error) {
      
      // If refresh failed but we have an old token, extend its lifetime temporarily
      if (oldToken && this.tokenInfo) {
        this.tokenInfo.expiresAt = Date.now() + 60000 // Extend by 1 minute
        return oldToken
      }
      
      throw error
    }
  }

  /**
   * Make the actual token request with retry logic
   */
  private async makeTokenRequest(): Promise<any> {
    let lastError: any
    
    for (let attempt = 1; attempt <= this.config.maxRetries; attempt++) {
      try {
        
        const response = await api.get('/csrf-token', {
          timeout: this.config.requestTimeout,
          headers: {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
          }
        })
        
        return response
      } catch (error) {
        lastError = error
        
        // Don't retry on certain errors
        if (this.isNonRetryableError(error)) {
          throw error
        }
        
        // Wait before retry (except on last attempt)
        if (attempt < this.config.maxRetries) {
          await this.delay(this.config.retryDelay * attempt) // Exponential backoff
        }
      }
    }
    
    throw lastError
  }

  /**
   * Check if error is non-retryable
   */
  private isNonRetryableError(error: any): boolean {
    // Don't retry on authentication errors, client errors, or timeouts
    const status = error.response?.status
    return status >= 400 && status < 500 && status !== 408 // 408 is timeout, retry that
  }

  /**
   * Store token with metadata
   */
  private storeToken(token: string): void {
    const now = Date.now()
    this.tokenInfo = {
      token,
      timestamp: now,
      expiresAt: now + this.config.tokenLifetime
    }
    
  }

  /**
   * Utility delay function
   */
  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms))
  }

  /**
   * Get token information for debugging
   */
  public getTokenInfo(): { 
    hasToken: boolean
    isValid: boolean
    timeUntilExpiry: number
    timeUntilRefresh: number
  } {
    if (!this.tokenInfo) {
      return {
        hasToken: false,
        isValid: false,
        timeUntilExpiry: 0,
        timeUntilRefresh: 0
      }
    }

    const now = Date.now()
    return {
      hasToken: true,
      isValid: this.isTokenValid(),
      timeUntilExpiry: Math.max(0, this.tokenInfo.expiresAt - now),
      timeUntilRefresh: Math.max(0, this.tokenInfo.expiresAt - this.config.refreshThreshold - now)
    }
  }

  /**
   * Update configuration
   */
  public updateConfig(newConfig: Partial<CsrfConfig>): void {
    this.config = { ...this.config, ...newConfig }
  }
}

// Global instance
let globalCsrfService: CsrfTokenService | null = null

/**
 * Get the global CSRF service instance
 */
export function getCsrfService(): CsrfTokenService {
  if (!globalCsrfService) {
    globalCsrfService = new CsrfTokenService()
  }
  return globalCsrfService
}

/**
 * Vue composable for using CSRF tokens
 */
export function useCsrfToken(config?: Partial<CsrfConfig>) {
  const csrfService = config ? new CsrfTokenService(config) : getCsrfService()
  const tokenInfo = ref(csrfService.getTokenInfo())

  // Update token info periodically
  const updateTokenInfo = () => {
    tokenInfo.value = csrfService.getTokenInfo()
  }

  const intervalId = setInterval(updateTokenInfo, 30000) // Update every 30 seconds

  onUnmounted(() => {
    clearInterval(intervalId)
  })

  return {
    getToken: () => csrfService.getToken(),
    getTokenSync: () => csrfService.getTokenSync(),
    refreshToken: () => csrfService.refreshToken(),
    clearToken: () => csrfService.clearToken(),
    tokenInfo: computed(() => tokenInfo.value),
    updateConfig: (newConfig: Partial<CsrfConfig>) => csrfService.updateConfig(newConfig)
  }
}
