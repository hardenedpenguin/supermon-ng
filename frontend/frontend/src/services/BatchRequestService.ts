/**
 * Smart Request Batching Service
 * 
 * Combines multiple API requests into efficient batches, reduces redundant calls,
 * and provides intelligent caching for improved performance.
 */

import { api } from '@/utils/api'

export interface BatchRequest {
  id: string
  endpoint: string
  params?: any
  method: 'GET' | 'POST' | 'PUT' | 'DELETE'
  priority: 'high' | 'medium' | 'low'
  ttl?: number // Cache TTL in milliseconds
  dependencies?: string[] // Request IDs this depends on
}

export interface BatchResponse {
  id: string
  data?: any
  error?: string
  cached?: boolean
  timestamp: number
}

export interface BatchConfig {
  maxBatchSize: number
  batchDelay: number // Delay before batching requests (ms)
  cacheEnabled: boolean
  defaultCacheTTL: number
}

export class BatchRequestService {
  private config: BatchConfig
  private requestQueue: Map<string, BatchRequest> = new Map()
  private responseCache: Map<string, BatchResponse> = new Map()
  private batchTimer: NodeJS.Timeout | null = null
  private pendingRequests: Map<string, { resolve: (value: any) => void; reject: (error: any) => void }> = new Map()

  constructor(config: Partial<BatchConfig> = {}) {
    this.config = {
      maxBatchSize: 10,
      batchDelay: 50, // 50ms delay to collect requests
      cacheEnabled: true,
      defaultCacheTTL: 30000, // 30 seconds default cache
      ...config
    }
  }

  /**
   * Add a request to the batch queue
   */
  public async addRequest(request: Omit<BatchRequest, 'id'>): Promise<any> {
    const id = this.generateRequestId(request)
    
    // Check cache first
    if (this.config.cacheEnabled) {
      const cached = this.getCachedResponse(id)
      if (cached) {
        console.log(`BatchRequestService: Cache hit for ${id}`)
        return cached.data
      }
    }

    // Check if request is already pending
    if (this.pendingRequests.has(id)) {
      console.log(`BatchRequestService: Request ${id} already pending, waiting...`)
      return new Promise((resolve, reject) => {
        // Reuse existing promise
        this.pendingRequests.get(id)!.resolve = resolve
        this.pendingRequests.get(id)!.reject = reject
      })
    }

    const batchRequest: BatchRequest = {
      ...request,
      id,
      ttl: request.ttl || this.config.defaultCacheTTL
    }

    return new Promise((resolve, reject) => {
      // Store the request and promise handlers
      this.requestQueue.set(id, batchRequest)
      this.pendingRequests.set(id, { resolve, reject })

      // Schedule batch processing
      this.scheduleBatch()
    })
  }

  /**
   * Create a batched initialization request
   */
  public async batchInitialization(): Promise<{
    nodes: any
    config: any
    astdbHealth: any
  }> {
    console.log('BatchRequestService: Creating initialization batch')
    
    const batchId = `init_${Date.now()}`
    
    try {
      // Execute requests in parallel but with proper error handling
      const [nodesResponse, configResponse, astdbResponse] = await Promise.allSettled([
        this.addRequest({
          endpoint: '/nodes',
          method: 'GET',
          priority: 'high',
          ttl: 10000 // Cache nodes for 10 seconds
        }),
        this.addRequest({
          endpoint: '/config/nodes',
          method: 'GET',
          priority: 'high',
          ttl: 300000 // Cache config for 5 minutes
        }),
        this.addRequest({
          endpoint: '/astdb/health',
          method: 'GET',
          priority: 'medium',
          ttl: 60000 // Cache health for 1 minute
        })
      ])

      return {
        nodes: nodesResponse.status === 'fulfilled' ? nodesResponse.value : null,
        config: configResponse.status === 'fulfilled' ? configResponse.value : null,
        astdbHealth: astdbResponse.status === 'fulfilled' ? astdbResponse.value : null
      }
    } catch (error) {
      console.error('BatchRequestService: Initialization batch failed', error)
      throw error
    }
  }

  /**
   * Create a batched real-time update request
   */
  public async batchRealTimeUpdate(nodeIds: string[]): Promise<{
    amiStatus: any
    nodeStatus: any
  }> {
    console.log(`BatchRequestService: Creating real-time update batch for ${nodeIds.length} nodes`)
    
    try {
      // For real-time data, we want fresh data so use shorter TTL
      const [amiResponse, statusResponse] = await Promise.allSettled([
        this.addRequest({
          endpoint: '/nodes/ami/status',
          method: 'POST',
          params: { nodes: nodeIds },
          priority: 'high',
          ttl: 2000 // Very short cache for real-time data
        }),
        this.addRequest({
          endpoint: '/nodes/status',
          method: 'POST',
          params: { nodes: nodeIds },
          priority: 'high',
          ttl: 2000 // Very short cache for real-time data
        })
      ])

      return {
        amiStatus: amiResponse.status === 'fulfilled' ? amiResponse.value : null,
        nodeStatus: statusResponse.status === 'fulfilled' ? statusResponse.value : null
      }
    } catch (error) {
      console.error('BatchRequestService: Real-time update batch failed', error)
      throw error
    }
  }

  /**
   * Schedule batch processing
   */
  private scheduleBatch(): void {
    if (this.batchTimer) {
      return // Already scheduled
    }

    this.batchTimer = setTimeout(() => {
      this.processBatch()
    }, this.config.batchDelay)
  }

  /**
   * Process the current batch of requests
   */
  private async processBatch(): void {
    if (this.batchTimer) {
      clearTimeout(this.batchTimer)
      this.batchTimer = null
    }

    if (this.requestQueue.size === 0) {
      return
    }

    console.log(`BatchRequestService: Processing batch of ${this.requestQueue.size} requests`)
    
    const requests = Array.from(this.requestQueue.values())
    this.requestQueue.clear()

    // Sort by priority
    requests.sort((a, b) => {
      const priorityOrder = { high: 0, medium: 1, low: 2 }
      return priorityOrder[a.priority] - priorityOrder[b.priority]
    })

    // Process requests in batches
    const chunks = this.chunkArray(requests, this.config.maxBatchSize)
    
    for (const chunk of chunks) {
      await this.processBatchChunk(chunk)
    }
  }

  /**
   * Process a chunk of requests
   */
  private async processBatchChunk(requests: BatchRequest[]): Promise<void> {
    // Group requests by method and endpoint for potential batching
    const groups = this.groupRequests(requests)
    
    for (const group of groups) {
      if (group.length === 1) {
        // Single request - execute directly
        await this.executeSingleRequest(group[0])
      } else {
        // Multiple requests - could potentially batch at API level
        await this.executeRequestGroup(group)
      }
    }
  }

  /**
   * Execute a single request
   */
  private async executeSingleRequest(request: BatchRequest): Promise<void> {
    try {
      console.log(`BatchRequestService: Executing single request ${request.id}`)
      
      let response: any
      if (request.method === 'GET') {
        response = await api.get(request.endpoint, { params: request.params })
      } else {
        response = await api.request({
          method: request.method,
          url: request.endpoint,
          data: request.params
        })
      }

      this.handleRequestSuccess(request, response.data)
    } catch (error) {
      this.handleRequestError(request, error)
    }
  }

  /**
   * Execute a group of similar requests
   */
  private async executeRequestGroup(requests: BatchRequest[]): Promise<void> {
    console.log(`BatchRequestService: Executing group of ${requests.length} requests`)
    
    // For now, execute in parallel - could be optimized further
    const promises = requests.map(request => this.executeSingleRequest(request))
    await Promise.allSettled(promises)
  }

  /**
   * Handle successful request
   */
  private handleRequestSuccess(request: BatchRequest, data: any): void {
    const response: BatchResponse = {
      id: request.id,
      data,
      cached: false,
      timestamp: Date.now()
    }

    // Cache the response
    if (this.config.cacheEnabled && request.ttl && request.ttl > 0) {
      this.responseCache.set(request.id, response)
      
      // Set cache expiration
      setTimeout(() => {
        this.responseCache.delete(request.id)
      }, request.ttl)
    }

    // Resolve pending promises
    const pending = this.pendingRequests.get(request.id)
    if (pending) {
      pending.resolve(data)
      this.pendingRequests.delete(request.id)
    }
  }

  /**
   * Handle failed request
   */
  private handleRequestError(request: BatchRequest, error: any): void {
    console.error(`BatchRequestService: Request ${request.id} failed`, error)
    
    // Reject pending promises
    const pending = this.pendingRequests.get(request.id)
    if (pending) {
      pending.reject(error)
      this.pendingRequests.delete(request.id)
    }
  }

  /**
   * Get cached response if available and not expired
   */
  private getCachedResponse(id: string): BatchResponse | null {
    const cached = this.responseCache.get(id)
    if (!cached) {
      return null
    }

    // Check if cache is still valid (basic check)
    const age = Date.now() - cached.timestamp
    if (age > 300000) { // 5 minutes max age for safety
      this.responseCache.delete(id)
      return null
    }

    cached.cached = true
    return cached
  }

  /**
   * Generate unique request ID
   */
  private generateRequestId(request: Omit<BatchRequest, 'id'>): string {
    const paramsStr = request.params ? JSON.stringify(request.params) : ''
    return `${request.method}_${request.endpoint}_${paramsStr}`.replace(/[^a-zA-Z0-9_]/g, '_')
  }

  /**
   * Group requests by similarity for potential batching
   */
  private groupRequests(requests: BatchRequest[]): BatchRequest[][] {
    const groups: Map<string, BatchRequest[]> = new Map()
    
    requests.forEach(request => {
      const key = `${request.method}_${request.endpoint}`
      if (!groups.has(key)) {
        groups.set(key, [])
      }
      groups.get(key)!.push(request)
    })
    
    return Array.from(groups.values())
  }

  /**
   * Split array into chunks
   */
  private chunkArray<T>(array: T[], size: number): T[][] {
    const chunks: T[][] = []
    for (let i = 0; i < array.length; i += size) {
      chunks.push(array.slice(i, i + size))
    }
    return chunks
  }

  /**
   * Clear cache
   */
  public clearCache(): void {
    this.responseCache.clear()
    console.log('BatchRequestService: Cache cleared')
  }

  /**
   * Get cache statistics
   */
  public getCacheStats(): { size: number; entries: string[] } {
    return {
      size: this.responseCache.size,
      entries: Array.from(this.responseCache.keys())
    }
  }
}

/**
 * Vue composable for using the batch request service
 */
export function useBatchRequests(config?: Partial<BatchConfig>) {
  const batchService = new BatchRequestService(config)

  return {
    addRequest: (request: Omit<BatchRequest, 'id'>) => batchService.addRequest(request),
    batchInitialization: () => batchService.batchInitialization(),
    batchRealTimeUpdate: (nodeIds: string[]) => batchService.batchRealTimeUpdate(nodeIds),
    clearCache: () => batchService.clearCache(),
    getCacheStats: () => batchService.getCacheStats()
  }
}
