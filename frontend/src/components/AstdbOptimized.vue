<template>
  <div class="astdb-optimized">
    <div class="header">
      <h3>ASTDB Optimized (Phase 8)</h3>
      <div class="controls">
        <button @click="refreshStats" :disabled="statsLoading">
          {{ statsLoading ? 'Loading...' : 'Refresh Stats' }}
        </button>
        <button @click="clearCache">Clear Cache</button>
        <button @click="forceRefresh">Force Refresh</button>
      </div>
    </div>

    <!-- Cache Statistics -->
    <div class="stats-section">
      <h4>Cache Statistics</h4>
      <div v-if="stats" class="stats-grid">
        <div class="stat-item">
          <label>Cache Size:</label>
          <span>{{ cacheStats.cacheSize }} entries</span>
        </div>
        <div class="stat-item">
          <label>Valid Entries:</label>
          <span>{{ cacheStats.validEntries }}</span>
        </div>
        <div class="stat-item">
          <label>Last Refresh:</label>
          <span>{{ formatTime(cacheStats.lastRefresh) }}</span>
        </div>
        <div class="stat-item">
          <label>Cache Valid:</label>
          <span :class="cacheStats.isCacheValid ? 'valid' : 'invalid'">
            {{ cacheStats.isCacheValid ? 'Yes' : 'No' }}
          </span>
        </div>
        <div class="stat-item">
          <label>ASTDB Entries:</label>
          <span>{{ cacheStats.fullAstdbEntries }}</span>
        </div>
        <div class="stat-item">
          <label>Compression:</label>
          <span>{{ stats.compression_ratio }}</span>
        </div>
      </div>
      <div v-else-if="statsLoading" class="loading">
        Loading statistics...
      </div>
      <div v-else class="error">
        Failed to load statistics
      </div>
    </div>

    <!-- Node Lookup Demo -->
    <div class="lookup-section">
      <h4>Node Lookup Demo</h4>
      <div class="lookup-controls">
        <input 
          v-model="lookupNodeId" 
          placeholder="Enter node ID (e.g., 546051)"
          @keyup.enter="lookupNode"
        />
        <button @click="lookupNode" :disabled="nodeLoading">
          {{ nodeLoading ? 'Loading...' : 'Lookup' }}
        </button>
      </div>
      
      <div v-if="nodeInfo" class="node-result">
        <h5>Node Information:</h5>
        <div class="node-details">
          <div><strong>Node ID:</strong> {{ nodeInfo.node_id }}</div>
          <div><strong>Callsign:</strong> {{ nodeInfo.callsign }}</div>
          <div><strong>Description:</strong> {{ nodeInfo.description }}</div>
          <div><strong>Location:</strong> {{ nodeInfo.location }}</div>
          <div><strong>Full Info:</strong> {{ nodeInfo.full_info }}</div>
        </div>
      </div>
      
      <div v-else-if="nodeError" class="error">
        {{ nodeError }}
      </div>
    </div>

    <!-- Search Demo -->
    <div class="search-section">
      <h4>Search Demo</h4>
      <div class="search-controls">
        <input 
          v-model="searchQuery" 
          placeholder="Enter search query (e.g., W5GLE)"
          @keyup.enter="performSearch"
        />
        <button @click="performSearch" :disabled="searchLoading">
          {{ searchLoading ? 'Searching...' : 'Search' }}
        </button>
        <button @click="clearSearch">Clear</button>
      </div>
      
      <div v-if="searchResults.length > 0" class="search-results">
        <h5>Search Results ({{ searchResults.length }}):</h5>
        <div class="results-list">
          <div 
            v-for="result in searchResults" 
            :key="result.node_id"
            class="result-item"
          >
            <strong>{{ result.node_id }}</strong>: {{ result.callsign }} - {{ result.location }}
          </div>
        </div>
      </div>
      
      <div v-else-if="searchError" class="error">
        {{ searchError }}
      </div>
    </div>

    <!-- Batch Lookup Demo -->
    <div class="batch-section">
      <h4>Batch Lookup Demo</h4>
      <div class="batch-controls">
        <input 
          v-model="batchNodeIds" 
          placeholder="Enter node IDs (e.g., 546051,546055,546056)"
          @keyup.enter="lookupBatch"
        />
        <button @click="lookupBatch" :disabled="batchLoading">
          {{ batchLoading ? 'Loading...' : 'Batch Lookup' }}
        </button>
      </div>
      
      <div v-if="batchResults && Object.keys(batchResults).length > 0" class="batch-results">
        <h5>Batch Results:</h5>
        <div class="batch-list">
          <div 
            v-for="(result, nodeId) in batchResults" 
            :key="nodeId"
            class="batch-item"
          >
            <div v-if="result">
              <strong>{{ nodeId }}</strong>: {{ result.callsign }} - {{ result.location }}
            </div>
            <div v-else class="not-found">
              <strong>{{ nodeId }}</strong>: Not found
            </div>
          </div>
        </div>
      </div>
      
      <div v-else-if="batchError" class="error">
        {{ batchError }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { 
  useAstdb, 
  useNodeInfo, 
  useAstdbSearch, 
  useMultipleNodesInfo, 
  useAstdbStats,
  useAstdbCache 
} from '@/composables/useAstdb'

// Composables
const { getStats, checkHealth } = useAstdb()
const { stats, isLoading: statsLoading, refresh: refreshStats } = useAstdbStats()
const { clearCache, forceRefresh, getCacheStats } = useAstdbCache()

// Cache statistics
const cacheStats = computed(() => getCacheStats())

// Node lookup
const lookupNodeId = ref('')
const { nodeInfo, isLoading: nodeLoading, error: nodeError, refresh: lookupNode } = useNodeInfo(lookupNodeId)

// Search
const searchQuery = ref('')
const { 
  searchResults, 
  isLoading: searchLoading, 
  error: searchError, 
  search, 
  clearResults: clearSearch 
} = useAstdbSearch()

// Batch lookup
const batchNodeIds = ref('')
const batchResults = ref({})
const batchLoading = ref(false)
const batchError = ref('')

const lookupBatch = async () => {
  if (!batchNodeIds.value.trim()) return
  
  batchLoading.value = true
  batchError.value = ''
  
  try {
    const nodeIds = batchNodeIds.value.split(',').map(id => id.trim()).filter(id => id)
    const { getMultipleNodesInfo } = useAstdb()
    const results = await getMultipleNodesInfo(nodeIds)
    batchResults.value = results
  } catch (err) {
    batchError.value = err.message || 'Batch lookup failed'
    console.error('Batch lookup failed:', err)
  } finally {
    batchLoading.value = false
  }
}

const performSearch = () => {
  if (searchQuery.value.trim()) {
    search(searchQuery.value.trim(), 10)
  }
}

const formatTime = (timestamp: string) => {
  if (!timestamp) return 'Never'
  return new Date(timestamp).toLocaleString()
}

// Initialize on mount
onMounted(() => {
  refreshStats()
})
</script>

<style scoped>
.astdb-optimized {
  padding: 20px;
  max-width: 800px;
  margin: 0 auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 2px solid #e0e0e0;
}

.controls {
  display: flex;
  gap: 10px;
}

.controls button {
  padding: 8px 16px;
  border: 1px solid #ccc;
  border-radius: 4px;
  background: #f5f5f5;
  cursor: pointer;
}

.controls button:hover:not(:disabled) {
  background: #e0e0e0;
}

.controls button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.stats-section, .lookup-section, .search-section, .batch-section {
  margin-bottom: 30px;
  padding: 20px;
  border: 1px solid #ddd;
  border-radius: 8px;
  background: #f9f9f9;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 10px;
}

.stat-item {
  display: flex;
  justify-content: space-between;
  padding: 8px;
  background: white;
  border-radius: 4px;
}

.stat-item label {
  font-weight: bold;
}

.stat-item .valid {
  color: green;
}

.stat-item .invalid {
  color: red;
}

.lookup-controls, .search-controls, .batch-controls {
  display: flex;
  gap: 10px;
  margin-bottom: 15px;
}

.lookup-controls input, .search-controls input, .batch-controls input {
  flex: 1;
  padding: 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
}

.lookup-controls button, .search-controls button, .batch-controls button {
  padding: 8px 16px;
  border: 1px solid #ccc;
  border-radius: 4px;
  background: #f5f5f5;
  cursor: pointer;
}

.node-result, .search-results, .batch-results {
  background: white;
  padding: 15px;
  border-radius: 4px;
  border: 1px solid #ddd;
}

.node-details div {
  margin-bottom: 8px;
}

.results-list, .batch-list {
  max-height: 200px;
  overflow-y: auto;
}

.result-item, .batch-item {
  padding: 8px;
  border-bottom: 1px solid #eee;
}

.result-item:last-child, .batch-item:last-child {
  border-bottom: none;
}

.not-found {
  color: #999;
  font-style: italic;
}

.loading, .error {
  padding: 10px;
  border-radius: 4px;
}

.loading {
  background: #e3f2fd;
  color: #1976d2;
}

.error {
  background: #ffebee;
  color: #c62828;
}

h3, h4, h5 {
  margin: 0 0 15px 0;
  color: #333;
}

h3 {
  color: #1976d2;
}
</style>
