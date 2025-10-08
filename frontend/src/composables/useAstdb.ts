import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useAstdbStore } from '@/stores/astdb'

/**
 * ASTDB Composable (Phase 8 optimization)
 * 
 * Provides reactive access to ASTDB data with automatic caching and optimization.
 * Handles loading states, error handling, and intelligent data fetching.
 */

export function useAstdb() {
  const astdbStore = useAstdbStore()
  
  // Initialize store on first use
  if (!astdbStore.isCacheValid && Object.keys(astdbStore.fullAstdb).length === 0) {
    astdbStore.initialize()
  }
  
  return {
    // Reactive state
    isLoading: computed(() => astdbStore.isLoading),
    error: computed(() => astdbStore.error),
    isCacheValid: computed(() => astdbStore.isCacheValid),
    
    // Actions
    getNodeInfo: astdbStore.getNodeInfo,
    getMultipleNodesInfo: astdbStore.getMultipleNodesInfo,
    searchNodes: astdbStore.searchNodes,
    getStats: astdbStore.getStats,
    checkHealth: astdbStore.checkHealth,
    clearCache: astdbStore.clearCache,
    forceRefresh: astdbStore.forceRefresh,
    getCacheStats: astdbStore.getCacheStats
  }
}

/**
 * Hook for getting a single node's information with automatic caching
 */
export function useNodeInfo(nodeId: string) {
  const astdbStore = useAstdbStore()
  const nodeInfo = ref(null)
  const isLoading = ref(false)
  const error = ref(null)
  
  const loadNodeInfo = async () => {
    if (!nodeId) return
    
    isLoading.value = true
    error.value = null
    
    try {
      const info = await astdbStore.getNodeInfo(nodeId)
      nodeInfo.value = info
    } catch (err) {
      error.value = err.message || 'Failed to load node info'
      console.error('Failed to load node info:', err)
    } finally {
      isLoading.value = false
    }
  }
  
  // Auto-load on mount
  onMounted(() => {
    loadNodeInfo()
  })
  
  return {
    nodeInfo: computed(() => nodeInfo.value),
    isLoading: computed(() => isLoading.value),
    error: computed(() => error.value),
    refresh: loadNodeInfo
  }
}

/**
 * Hook for getting multiple nodes' information with batch optimization
 */
export function useMultipleNodesInfo(nodeIds: string[]) {
  const astdbStore = useAstdbStore()
  const nodesInfo = ref({})
  const isLoading = ref(false)
  const error = ref(null)
  
  const loadNodesInfo = async () => {
    if (!nodeIds || nodeIds.length === 0) return
    
    isLoading.value = true
    error.value = null
    
    try {
      const info = await astdbStore.getMultipleNodesInfo(nodeIds)
      nodesInfo.value = info
    } catch (err) {
      error.value = err.message || 'Failed to load nodes info'
      console.error('Failed to load nodes info:', err)
    } finally {
      isLoading.value = false
    }
  }
  
  // Auto-load on mount
  onMounted(() => {
    loadNodesInfo()
  })
  
  return {
    nodesInfo: computed(() => nodesInfo.value),
    isLoading: computed(() => isLoading.value),
    error: computed(() => error.value),
    refresh: loadNodesInfo
  }
}

/**
 * Hook for ASTDB search with caching
 */
export function useAstdbSearch() {
  const astdbStore = useAstdbStore()
  const searchResults = ref([])
  const isLoading = ref(false)
  const error = ref(null)
  const lastQuery = ref('')
  
  const search = async (query: string, limit: number = 50) => {
    if (!query || query.trim().length < 2) {
      searchResults.value = []
      return
    }
    
    // Don't search again if it's the same query
    if (query === lastQuery.value && searchResults.value.length > 0) {
      return
    }
    
    isLoading.value = true
    error.value = null
    lastQuery.value = query
    
    try {
      const results = await astdbStore.searchNodes(query.trim(), limit)
      searchResults.value = results
    } catch (err) {
      error.value = err.message || 'Search failed'
      console.error('ASTDB search failed:', err)
      searchResults.value = []
    } finally {
      isLoading.value = false
    }
  }
  
  const clearResults = () => {
    searchResults.value = []
    lastQuery.value = ''
    error.value = null
  }
  
  return {
    searchResults: computed(() => searchResults.value),
    isLoading: computed(() => isLoading.value),
    error: computed(() => error.value),
    lastQuery: computed(() => lastQuery.value),
    search,
    clearResults
  }
}

/**
 * Hook for ASTDB statistics and health monitoring
 */
export function useAstdbStats() {
  const astdbStore = useAstdbStore()
  const stats = ref(null)
  const isHealthy = ref(false)
  const isLoading = ref(false)
  const error = ref(null)
  
  const loadStats = async () => {
    isLoading.value = true
    error.value = null
    
    try {
      const [statsData, health] = await Promise.all([
        astdbStore.getStats(),
        astdbStore.checkHealth()
      ])
      
      stats.value = statsData
      isHealthy.value = health
    } catch (err) {
      error.value = err.message || 'Failed to load stats'
      console.error('Failed to load ASTDB stats:', err)
    } finally {
      isLoading.value = false
    }
  }
  
  // Auto-load on mount
  onMounted(() => {
    loadStats()
  })
  
  return {
    stats: computed(() => stats.value),
    isHealthy: computed(() => isHealthy.value),
    isLoading: computed(() => isLoading.value),
    error: computed(() => error.value),
    refresh: loadStats
  }
}

/**
 * Hook for managing ASTDB cache
 */
export function useAstdbCache() {
  const astdbStore = useAstdbStore()
  
  const clearCache = () => {
    astdbStore.clearCache()
  }
  
  const forceRefresh = async () => {
    await astdbStore.forceRefresh()
  }
  
  const getCacheStats = () => {
    return astdbStore.getCacheStats()
  }
  
  return {
    clearCache,
    forceRefresh,
    getCacheStats,
    cacheSize: computed(() => astdbStore.cacheSize),
    isCacheValid: computed(() => astdbStore.isCacheValid)
  }
}
