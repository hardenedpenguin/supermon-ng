import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/utils/api'
import type { AstDbEntry } from '@/types'

// ASTDB cache interface
interface AstdbCacheEntry {
  data: AstDbEntry
  timestamp: number
  expiresAt: number
}

interface AstdbStats {
  cache_file_size: number
  compression_ratio: string
  entries_count: number
  is_cached: boolean
  is_compressed: boolean
}

/**
 * ASTDB Store with Browser-Side Caching (Phase 8 optimization)
 * 
 * Implements intelligent caching strategies to reduce redundant API calls
 * and improve frontend performance for ASTDB data access.
 */
export const useAstdbStore = defineStore('astdb', () => {
  // State
  const cache = ref<Map<string, AstdbCacheEntry>>(new Map())
  const fullAstdb = ref<AstDbEntry>({})
  const stats = ref<AstdbStats | null>(null)
  const lastRefresh = ref<number>(0)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  
  // Configuration
  const CACHE_DURATION = 5 * 60 * 1000 // 5 minutes in milliseconds
  const MAX_CACHE_SIZE = 1000 // Maximum number of cached entries
  const REFRESH_THRESHOLD = 10 * 60 * 1000 // 10 minutes before considering data stale
  
  // Computed
  const isCacheValid = computed(() => {
    return Date.now() - lastRefresh.value < REFRESH_THRESHOLD
  })
  
  const cacheSize = computed(() => cache.value.size)
  
  // Actions
  
  /**
   * Initialize ASTDB store and load initial data
   */
  const initialize = async (): Promise<void> => {
    try {
      isLoading.value = true
      error.value = null
      
      // Check if we have valid cached data first
      if (isCacheValid.value && Object.keys(fullAstdb.value).length > 0) {
        console.log('ASTDB: Using cached data')
        return
      }
      
      // Load fresh data from the optimized endpoint
      await loadFullAstdb()
      
    } catch (err) {
      error.value = 'Failed to initialize ASTDB store'
      console.error('ASTDB store initialization error:', err)
    } finally {
      isLoading.value = false
    }
  }
  
  /**
   * Load full ASTDB data with caching
   */
  const loadFullAstdb = async (): Promise<void> => {
    try {
      // Try to get from database status endpoint (includes ASTDB)
      // Use longer timeout for large ASTDB files
      const response = await api.get('/database/status', {
        timeout: 30000 // 30 seconds for large ASTDB files
      })
      
      // Debug: Log the full response structure
      console.log('Database status response:', {
        status: response.status,
        success: response.data?.success,
        hasData: !!response.data?.data,
        hasAstdb: !!response.data?.data?.astdb
      })
      
      // Check if response is valid
      if (!response.data) {
        console.error('Empty response from database status endpoint')
        throw new Error('Invalid response from database status endpoint: empty response')
      }
      
      if (!response.data.success) {
        console.error('Database status endpoint returned success=false:', response.data)
        throw new Error('Invalid response from database status endpoint: ' + (response.data.message || 'Unknown error'))
      }
      
      // Ensure astdb exists in response (may be empty array)
      const astdbData = response.data.data?.astdb
      if (astdbData === undefined || astdbData === null) {
        console.error('ASTDB data missing in response:', response.data)
        throw new Error('No ASTDB data in response')
      }
      
      // Ensure it's an array or object (should always be from backend, but double-check)
      if (!Array.isArray(astdbData) && typeof astdbData !== 'object') {
        console.error('ASTDB data has wrong type:', typeof astdbData, astdbData)
        throw new Error('ASTDB data is not in expected format')
      }
      
      fullAstdb.value = astdbData
      lastRefresh.value = Date.now()
      
      const entryCount = Array.isArray(astdbData) ? astdbData.length : Object.keys(astdbData).length
      console.log(`ASTDB: Loaded ${entryCount} entries`)
    } catch (err) {
      console.error('Failed to load full ASTDB:', err)
      throw err
    }
  }
  
  /**
   * Get single node information with intelligent caching
   */
  const getNodeInfo = async (nodeId: string): Promise<any | null> => {
    try {
      // Check browser cache first
      const cacheKey = `node_${nodeId}`
      const cachedEntry = cache.value.get(cacheKey)
      
      if (cachedEntry && cachedEntry.expiresAt > Date.now()) {
        console.log(`ASTDB: Cache hit for node ${nodeId}`)
        return cachedEntry.data
      }
      
      // Check if we have full ASTDB data
      if (Object.keys(fullAstdb.value).length > 0 && fullAstdb.value[nodeId]) {
        const nodeInfo = fullAstdb.value[nodeId]
        
        // Cache this individual lookup
        cacheNodeInfo(nodeId, nodeInfo)
        return nodeInfo
      }
      
      // Use optimized single node endpoint
      console.log(`ASTDB: Fetching node ${nodeId} from API`)
      const response = await api.get(`/astdb/node/${nodeId}`)
      
      if (response.data.success && response.data.data) {
        const nodeInfo = {
          node_id: nodeId,
          callsign: response.data.data.callsign,
          description: response.data.data.description,
          location: response.data.data.location
        }
        
        // Cache the result
        cacheNodeInfo(nodeId, nodeInfo)
        return nodeInfo
      }
      
      return null
    } catch (err) {
      console.error(`Failed to get node info for ${nodeId}:`, err)
      return null
    }
  }
  
  /**
   * Get multiple nodes information with batch optimization
   */
  const getMultipleNodesInfo = async (nodeIds: string[]): Promise<Record<string, any>> => {
    try {
      const results: Record<string, any> = {}
      const uncachedIds: string[] = []
      
      // Check cache for each node
      for (const nodeId of nodeIds) {
        const cacheKey = `node_${nodeId}`
        const cachedEntry = cache.value.get(cacheKey)
        
        if (cachedEntry && cachedEntry.expiresAt > Date.now()) {
          results[nodeId] = cachedEntry.data
        } else {
          uncachedIds.push(nodeId)
        }
      }
      
      // If we have uncached nodes, use batch endpoint
      if (uncachedIds.length > 0) {
        console.log(`ASTDB: Fetching ${uncachedIds.length} nodes from batch API`)
        const response = await api.get('/astdb/nodes', {
          params: { nodes: uncachedIds.join(',') }
        })
        
        if (response.data.success && response.data.data) {
          for (const [nodeId, nodeInfo] of Object.entries(response.data.data)) {
            if (nodeInfo) {
              results[nodeId] = nodeInfo
              cacheNodeInfo(nodeId, nodeInfo)
            }
          }
        }
      }
      
      return results
    } catch (err) {
      console.error('Failed to get multiple nodes info:', err)
      return {}
    }
  }
  
  /**
   * Search ASTDB with caching
   */
  const searchNodes = async (query: string, limit: number = 50): Promise<any[]> => {
    try {
      const cacheKey = `search_${query}_${limit}`
      const cachedEntry = cache.value.get(cacheKey)
      
      if (cachedEntry && cachedEntry.expiresAt > Date.now()) {
        console.log(`ASTDB: Cache hit for search "${query}"`)
        return cachedEntry.data
      }
      
      console.log(`ASTDB: Searching for "${query}"`)
      const response = await api.get('/astdb/search', {
        params: { q: query, limit }
      })
      
      if (response.data.success && response.data.data?.results) {
        const results = response.data.data.results
        
        // Cache search results
        cache.value.set(cacheKey, {
          data: results,
          timestamp: Date.now(),
          expiresAt: Date.now() + CACHE_DURATION
        })
        
        // Clean cache if it gets too large
        cleanCache()
        
        return results
      }
      
      return []
    } catch (err) {
      console.error('Failed to search ASTDB:', err)
      return []
    }
  }
  
  /**
   * Get ASTDB statistics
   */
  const getStats = async (): Promise<AstdbStats | null> => {
    try {
      const response = await api.get('/astdb/stats')
      if (response.data.success) {
        stats.value = response.data.data
        return stats.value
      }
      return null
    } catch (err) {
      console.error('Failed to get ASTDB stats:', err)
      return null
    }
  }
  
  /**
   * Check ASTDB health
   */
  const checkHealth = async (): Promise<boolean> => {
    try {
      const response = await api.get('/astdb/health')
      return response.data.success && response.data.healthy
    } catch (err) {
      console.error('ASTDB health check failed:', err)
      return false
    }
  }
  
  /**
   * Clear browser cache
   */
  const clearCache = (): void => {
    cache.value.clear()
    fullAstdb.value = {}
    lastRefresh.value = 0
    stats.value = null
    console.log('ASTDB: Browser cache cleared')
  }
  
  /**
   * Force refresh of all data
   */
  const forceRefresh = async (): Promise<void> => {
    clearCache()
    await initialize()
  }
  
  /**
   * Cache individual node info
   */
  const cacheNodeInfo = (nodeId: string, nodeInfo: any): void => {
    const cacheKey = `node_${nodeId}`
    cache.value.set(cacheKey, {
      data: nodeInfo,
      timestamp: Date.now(),
      expiresAt: Date.now() + CACHE_DURATION
    })
    
    // Clean cache if it gets too large
    if (cache.value.size > MAX_CACHE_SIZE) {
      cleanCache()
    }
  }
  
  /**
   * Clean expired and old cache entries
   */
  const cleanCache = (): void => {
    const now = Date.now()
    const entries = Array.from(cache.value.entries())
    
    // Remove expired entries
    for (const [key, entry] of entries) {
      if (entry.expiresAt <= now) {
        cache.value.delete(key)
      }
    }
    
    // If still too large, remove oldest entries
    if (cache.value.size > MAX_CACHE_SIZE) {
      const sortedEntries = entries
        .filter(([_, entry]) => entry.expiresAt > now)
        .sort((a, b) => a[1].timestamp - b[1].timestamp)
      
      const toRemove = sortedEntries.slice(0, sortedEntries.length - MAX_CACHE_SIZE)
      for (const [key] of toRemove) {
        cache.value.delete(key)
      }
    }
    
    console.log(`ASTDB: Cache cleaned, ${cache.value.size} entries remaining`)
  }
  
  /**
   * Get cache statistics for debugging
   */
  const getCacheStats = () => {
    const now = Date.now()
    let validEntries = 0
    let expiredEntries = 0
    
    for (const entry of cache.value.values()) {
      if (entry.expiresAt > now) {
        validEntries++
      } else {
        expiredEntries++
      }
    }
    
    return {
      totalEntries: cache.value.size,
      validEntries,
      expiredEntries,
      fullAstdbEntries: Object.keys(fullAstdb.value).length,
      lastRefresh: new Date(lastRefresh.value).toISOString(),
      isCacheValid: isCacheValid.value
    }
  }
  
  return {
    // State
    cache: readonly(cache),
    fullAstdb: readonly(fullAstdb),
    stats: readonly(stats),
    isLoading: readonly(isLoading),
    error: readonly(error),
    
    // Computed
    isCacheValid,
    cacheSize,
    
    // Actions
    initialize,
    getNodeInfo,
    getMultipleNodesInfo,
    searchNodes,
    getStats,
    checkHealth,
    clearCache,
    forceRefresh,
    getCacheStats
  }
})

// Helper function to create readonly refs (if not available in Vue version)
function readonly<T>(ref: T): T {
  return ref
}
