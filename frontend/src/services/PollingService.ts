/**
 * Intelligent Polling Service
 * 
 * Provides adaptive polling with activity-based frequency, connection state management,
 * and smart request batching for optimal performance and user experience.
 */

import { ref, computed, onMounted, onUnmounted } from 'vue'
import { api } from '@/utils/api'

export interface PollingConfig {
  activeInterval: number      // Polling interval when user is active (ms)
  inactiveInterval: number    // Polling interval when user is inactive (ms)
  backgroundInterval: number  // Polling interval when tab is hidden (ms)
  inactiveThreshold: number   // Time before considering user inactive (ms)
  maxRetries: number         // Maximum retry attempts on failure
  backoffMultiplier: number  // Exponential backoff multiplier
  maxBackoffInterval: number // Maximum backoff interval (ms)
}

export interface PollingState {
  isActive: boolean
  isVisible: boolean
  isConnected: boolean
  lastActivity: number
  currentInterval: number
  retryCount: number
  errorCount: number
}

export class PollingService {
  private config: PollingConfig
  private state: PollingState
  private intervalId: NodeJS.Timeout | null = null
  private activityTimeout: NodeJS.Timeout | null = null
  private listeners: Set<(state: PollingState) => void> = new Set()
  private requestQueue: Map<string, Promise<any>> = new Map()
  
  // Activity tracking
  private activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click']
  private visibilityChangeHandler: (() => void) | null = null
  private activityHandlers: Map<string, () => void> = new Map()

  constructor(config: Partial<PollingConfig> = {}) {
    this.config = {
      activeInterval: 1000,      // 1 second when active
      inactiveInterval: 5000,    // 5 seconds when inactive
      backgroundInterval: 10000, // 10 seconds when tab hidden
      inactiveThreshold: 30000,  // 30 seconds to become inactive
      maxRetries: 3,
      backoffMultiplier: 2,
      maxBackoffInterval: 30000,
      ...config
    }

    this.state = {
      isActive: true,
      isVisible: true,
      isConnected: false,
      lastActivity: Date.now(),
      currentInterval: this.config.activeInterval,
      retryCount: 0,
      errorCount: 0
    }

    this.setupActivityTracking()
    this.setupVisibilityTracking()
  }

  /**
   * Start the polling service
   */
  public start(): void {
    if (this.intervalId) {
      return // Already running
    }

    console.log('PollingService: Starting adaptive polling')
    this.state.isConnected = true
    this.scheduleNextPoll()
    this.notifyListeners()
  }

  /**
   * Stop the polling service
   */
  public stop(): void {
    if (this.intervalId) {
      clearTimeout(this.intervalId)
      this.intervalId = null
    }
    
    if (this.activityTimeout) {
      clearTimeout(this.activityTimeout)
      this.activityTimeout = null
    }

    console.log('PollingService: Stopped polling')
    this.state.isConnected = false
    this.state.retryCount = 0
    this.state.errorCount = 0
    this.notifyListeners()
  }

  /**
   * Subscribe to polling state changes
   */
  public subscribe(listener: (state: PollingState) => void): () => void {
    this.listeners.add(listener)
    
    // Return unsubscribe function
    return () => {
      this.listeners.delete(listener)
    }
  }

  /**
   * Get current polling state
   */
  public getState(): PollingState {
    return { ...this.state }
  }

  /**
   * Update polling configuration
   */
  public updateConfig(newConfig: Partial<PollingConfig>): void {
    this.config = { ...this.config, ...newConfig }
    this.updateInterval()
  }

  /**
   * Make a request with deduplication
   */
  public async makeRequest<T>(
    key: string, 
    requestFn: () => Promise<T>,
    ttl: number = 5000
  ): Promise<T> {
    // Check if request is already in progress
    if (this.requestQueue.has(key)) {
      console.log(`PollingService: Deduplicating request for ${key}`)
      return this.requestQueue.get(key)!
    }

    // Create new request
    const requestPromise = this.executeRequest(requestFn)
    this.requestQueue.set(key, requestPromise)

    // Clean up after TTL
    setTimeout(() => {
      this.requestQueue.delete(key)
    }, ttl)

    try {
      const result = await requestPromise
      return result
    } finally {
      this.requestQueue.delete(key)
    }
  }

  /**
   * Execute a request with error handling and backoff
   */
  private async executeRequest<T>(requestFn: () => Promise<T>): Promise<T> {
    try {
      const result = await requestFn()
      this.onRequestSuccess()
      return result
    } catch (error) {
      this.onRequestError(error)
      throw error
    }
  }

  /**
   * Handle successful request
   */
  private onRequestSuccess(): void {
    this.state.retryCount = 0
    this.state.errorCount = Math.max(0, this.state.errorCount - 1)
    this.updateInterval()
    this.notifyListeners()
  }

  /**
   * Handle failed request
   */
  private onRequestError(error: any): void {
    this.state.retryCount++
    this.state.errorCount++
    
    console.warn('PollingService: Request failed', {
      retryCount: this.state.retryCount,
      errorCount: this.state.errorCount,
      error: error.message
    })

    // Apply exponential backoff
    if (this.state.retryCount <= this.config.maxRetries) {
      const backoffInterval = Math.min(
        this.config.activeInterval * Math.pow(this.config.backoffMultiplier, this.state.retryCount),
        this.config.maxBackoffInterval
      )
      
      console.log(`PollingService: Applying backoff of ${backoffInterval}ms`)
      this.state.currentInterval = backoffInterval
    } else {
      // Reset to normal interval after max retries
      this.state.retryCount = 0
      this.updateInterval()
    }

    this.notifyListeners()
  }

  /**
   * Update polling interval based on current state
   */
  private updateInterval(): void {
    let newInterval: number

    if (!this.state.isVisible) {
      newInterval = this.config.backgroundInterval
    } else if (!this.state.isActive) {
      newInterval = this.config.inactiveInterval
    } else {
      newInterval = this.config.activeInterval
    }

    // Don't change interval if we're in backoff mode
    if (this.state.retryCount > 0 && this.state.retryCount <= this.config.maxRetries) {
      return
    }

    if (newInterval !== this.state.currentInterval) {
      console.log(`PollingService: Updating interval from ${this.state.currentInterval}ms to ${newInterval}ms`)
      this.state.currentInterval = newInterval
      
      // Reschedule if currently running
      if (this.intervalId && this.state.isConnected) {
        this.scheduleNextPoll()
      }
    }
  }

  /**
   * Schedule the next poll
   */
  private scheduleNextPoll(): void {
    if (this.intervalId) {
      clearTimeout(this.intervalId)
    }

    this.intervalId = setTimeout(() => {
      this.poll()
    }, this.state.currentInterval)
  }

  /**
   * Perform polling cycle
   */
  private async poll(): void {
    if (!this.state.isConnected) {
      return
    }

    // Reset interval for next poll
    this.intervalId = null
    this.scheduleNextPoll()
  }

  /**
   * Setup activity tracking
   */
  private setupActivityTracking(): void {
    this.activityEvents.forEach(event => {
      const handler = () => {
        this.onUserActivity()
      }
      
      this.activityHandlers.set(event, handler)
      document.addEventListener(event, handler, { passive: true })
    })
  }

  /**
   * Setup visibility change tracking
   */
  private setupVisibilityTracking(): void {
    this.visibilityChangeHandler = () => {
      this.state.isVisible = !document.hidden
      console.log(`PollingService: Tab visibility changed - ${this.state.isVisible ? 'visible' : 'hidden'}`)
      
      if (this.state.isVisible) {
        // Resume normal activity when tab becomes visible
        this.state.isActive = true
        this.state.lastActivity = Date.now()
      }
      
      this.updateInterval()
      this.notifyListeners()
    }

    document.addEventListener('visibilitychange', this.visibilityChangeHandler)
  }

  /**
   * Handle user activity
   */
  private onUserActivity(): void {
    const now = Date.now()
    const wasInactive = !this.state.isActive

    this.state.isActive = true
    this.state.lastActivity = now

    if (wasInactive) {
      console.log('PollingService: User became active')
      this.updateInterval()
      this.notifyListeners()
    }

    // Clear existing timeout
    if (this.activityTimeout) {
      clearTimeout(this.activityTimeout)
    }

    // Set timeout to mark user as inactive
    this.activityTimeout = setTimeout(() => {
      if (Date.now() - this.state.lastActivity >= this.config.inactiveThreshold) {
        console.log('PollingService: User became inactive')
        this.state.isActive = false
        this.updateInterval()
        this.notifyListeners()
      }
    }, this.config.inactiveThreshold)
  }

  /**
   * Notify all listeners of state changes
   */
  private notifyListeners(): void {
    this.listeners.forEach(listener => {
      try {
        listener({ ...this.state })
      } catch (error) {
        console.error('PollingService: Error in listener', error)
      }
    })
  }

  /**
   * Cleanup resources
   */
  public destroy(): void {
    this.stop()

    // Remove event listeners
    this.activityHandlers.forEach((handler, event) => {
      document.removeEventListener(event, handler)
    })
    this.activityHandlers.clear()

    if (this.visibilityChangeHandler) {
      document.removeEventListener('visibilitychange', this.visibilityChangeHandler)
    }

    // Clear request queue
    this.requestQueue.clear()
    this.listeners.clear()
  }
}

/**
 * Vue composable for using the polling service
 */
export function usePolling(config?: Partial<PollingConfig>) {
  const pollingService = new PollingService(config)
  const state = ref<PollingState>(pollingService.getState())

  // Subscribe to state changes
  const unsubscribe = pollingService.subscribe((newState) => {
    state.value = newState
  })

  // Auto-cleanup on unmount
  onUnmounted(() => {
    unsubscribe()
    pollingService.destroy()
  })

  return {
    state: computed(() => state.value),
    start: () => pollingService.start(),
    stop: () => pollingService.stop(),
    makeRequest: <T>(key: string, requestFn: () => Promise<T>, ttl?: number) => 
      pollingService.makeRequest(key, requestFn, ttl),
    updateConfig: (newConfig: Partial<PollingConfig>) => pollingService.updateConfig(newConfig)
  }
}
