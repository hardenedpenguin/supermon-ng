<template>
  <div class="polling-optimization-demo card">
    <div class="card-header">
      <h3 class="card-title">Frontend Polling Optimization Demo</h3>
      <div class="card-tools">
        <button 
          @click="toggleDemo" 
          :class="['btn', demoActive ? 'btn-danger' : 'btn-success']"
          :disabled="loading"
        >
          {{ demoActive ? 'Stop Demo' : 'Start Demo' }}
        </button>
      </div>
    </div>
    
    <div class="card-body">
      <div v-if="loading" class="alert alert-info">
        <i class="fas fa-spinner fa-spin"></i> Loading optimization demo...
      </div>
      
      <div v-if="error" class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> {{ error }}
      </div>

      <div class="row">
        <!-- Polling Status -->
        <div class="col-md-6">
          <h4><i class="fas fa-tachometer-alt"></i> Polling Status</h4>
          <div class="status-grid">
            <div class="status-item">
              <span class="label">Current Interval:</span>
              <span :class="['value', getIntervalClass()]">{{ formatInterval(pollingEfficiency.currentInterval) }}</span>
            </div>
            <div class="status-item">
              <span class="label">User Active:</span>
              <span :class="['value', pollingEfficiency.isActive ? 'text-success' : 'text-warning']">
                <i :class="pollingEfficiency.isActive ? 'fas fa-user-check' : 'fas fa-user-clock'"></i>
                {{ pollingEfficiency.isActive ? 'Yes' : 'No' }}
              </span>
            </div>
            <div class="status-item">
              <span class="label">Tab Visible:</span>
              <span :class="['value', pollingEfficiency.isVisible ? 'text-success' : 'text-warning']">
                <i :class="pollingEfficiency.isVisible ? 'fas fa-eye' : 'fas fa-eye-slash'"></i>
                {{ pollingEfficiency.isVisible ? 'Yes' : 'No' }}
              </span>
            </div>
            <div class="status-item">
              <span class="label">Error Count:</span>
              <span :class="['value', pollingEfficiency.errorCount > 0 ? 'text-danger' : 'text-success']">
                <i class="fas fa-exclamation-circle"></i>
                {{ pollingEfficiency.errorCount }}
              </span>
            </div>
          </div>
        </div>

        <!-- Performance Metrics -->
        <div class="col-md-6">
          <h4><i class="fas fa-chart-line"></i> Performance Metrics</h4>
          <div class="metrics-grid">
            <div class="metric-item">
              <span class="label">Last Update:</span>
              <span class="value">{{ formatTime(lastUpdateTime) }}</span>
            </div>
            <div class="metric-item">
              <span class="label">Nodes Monitored:</span>
              <span class="value">{{ monitoringNodes.length }}</span>
            </div>
            <div class="metric-item">
              <span class="label">Batch Cache Size:</span>
              <span class="value">{{ batchCacheStats.size }}</span>
            </div>
            <div class="metric-item">
              <span class="label">CSRF Token Status:</span>
              <span :class="['value', csrfTokenInfo.isValid ? 'text-success' : 'text-warning']">
                <i :class="csrfTokenInfo.isValid ? 'fas fa-check-circle' : 'fas fa-clock'"></i>
                {{ csrfTokenInfo.isValid ? 'Valid' : 'Refreshing' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Optimization Features -->
      <div class="row mt-4">
        <div class="col-12">
          <h4><i class="fas fa-cogs"></i> Optimization Features</h4>
          <div class="features-grid">
            <div class="feature-item" :class="{ active: pollingEfficiency.isActive }">
              <i class="fas fa-user"></i>
              <span>Adaptive Polling</span>
              <small>Adjusts frequency based on user activity</small>
            </div>
            <div class="feature-item" :class="{ active: batchCacheStats.size > 0 }">
              <i class="fas fa-layer-group"></i>
              <span>Request Batching</span>
              <small>Combines multiple API calls efficiently</small>
            </div>
            <div class="feature-item" :class="{ active: !pollingEfficiency.isVisible }">
              <i class="fas fa-pause"></i>
              <span>Background Pause</span>
              <small>Reduces polling when tab is hidden</small>
            </div>
            <div class="feature-item" :class="{ active: csrfTokenInfo.isValid }">
              <i class="fas fa-shield-alt"></i>
              <span>Smart CSRF</span>
              <small>Intelligent token caching and refresh</small>
            </div>
            <div class="feature-item" :class="{ active: pollingEfficiency.errorCount === 0 }">
              <i class="fas fa-sync-alt"></i>
              <span>Auto Recovery</span>
              <small>Exponential backoff on errors</small>
            </div>
            <div class="feature-item" :class="{ active: demoActive }">
              <i class="fas fa-rocket"></i>
              <span>Real-time Updates</span>
              <small>Optimized data fetching pipeline</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="row mt-4">
        <div class="col-12">
          <h4><i class="fas fa-tools"></i> Controls</h4>
          <div class="button-group">
            <button @click="clearAllCaches" class="btn btn-warning">
              <i class="fas fa-broom"></i> Clear All Caches
            </button>
            <button @click="refreshCsrfToken" class="btn btn-info">
              <i class="fas fa-refresh"></i> Refresh CSRF Token
            </button>
            <button @click="forceNodeUpdate" class="btn btn-primary" :disabled="!demoActive">
              <i class="fas fa-sync"></i> Force Node Update
            </button>
            <button @click="toggleTabVisibility" class="btn btn-secondary">
              <i class="fas fa-eye"></i> Toggle Tab Visibility (Simulate)
            </button>
          </div>
        </div>
      </div>

      <!-- Performance Comparison -->
      <div class="row mt-4" v-if="demoActive">
        <div class="col-12">
          <h4><i class="fas fa-chart-bar"></i> Performance Comparison</h4>
          <div class="comparison-grid">
            <div class="comparison-item old">
              <h5><i class="fas fa-clock"></i> Before Optimization</h5>
              <ul>
                <li>Fixed 1-second polling</li>
                <li>No request batching</li>
                <li>No activity detection</li>
                <li>CSRF token per request</li>
                <li>No error recovery</li>
                <li>Always active polling</li>
              </ul>
              <div class="performance-score">
                <span class="score-label">Efficiency:</span>
                <span class="score-value bad">Low</span>
              </div>
            </div>
            <div class="comparison-item new">
              <h5><i class="fas fa-rocket"></i> After Optimization</h5>
              <ul>
                <li>Adaptive 1-10 second polling</li>
                <li>Smart request batching</li>
                <li>Activity-based frequency</li>
                <li>Cached CSRF tokens</li>
                <li>Auto error recovery</li>
                <li>Background pause mode</li>
              </ul>
              <div class="performance-score">
                <span class="score-label">Efficiency:</span>
                <span class="score-value good">High</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRealTimeStore } from '@/stores/realTime'
import { useBatchRequests } from '@/services/BatchRequestService'
import { useCsrfToken } from '@/services/CsrfTokenService'

const realTimeStore = useRealTimeStore()
const { getCacheStats } = useBatchRequests()
const { tokenInfo: csrfTokenInfo, refreshToken } = useCsrfToken()

const loading = ref(false)
const error = ref<string | null>(null)
const demoActive = ref(false)
const simulatedTabHidden = ref(false)

// Computed properties
const pollingEfficiency = computed(() => realTimeStore.pollingEfficiency)
const lastUpdateTime = computed(() => realTimeStore.lastUpdateTime)
const monitoringNodes = computed(() => realTimeStore.monitoringNodes)
const batchCacheStats = computed(() => getCacheStats())

// Methods
const formatInterval = (ms: number): string => {
  if (ms < 1000) return `${ms}ms`
  return `${(ms / 1000).toFixed(1)}s`
}

const formatTime = (timestamp: number): string => {
  if (!timestamp) return 'Never'
  const date = new Date(timestamp)
  return date.toLocaleTimeString()
}

const getIntervalClass = (): string => {
  const interval = pollingEfficiency.value.currentInterval
  if (interval <= 1000) return 'text-success'
  if (interval <= 5000) return 'text-warning'
  return 'text-info'
}

const toggleDemo = async () => {
  loading.value = true
  error.value = null
  
  try {
    if (!demoActive.value) {
      // Start demo
      await realTimeStore.initialize()
      
      // Start monitoring a few nodes for demo
      const nodeIds = ['546051', '546055', '546056'] // Example nodes
      nodeIds.forEach(nodeId => {
        realTimeStore.startMonitoring(nodeId)
      })
      
      demoActive.value = true
    } else {
      // Stop demo
      realTimeStore.monitoringNodes.forEach(nodeId => {
        realTimeStore.stopMonitoring(nodeId)
      })
      demoActive.value = false
    }
  } catch (err: unknown) {
    const errorMessage = err instanceof Error ? err.message : 'Failed to toggle demo'
    error.value = errorMessage
  } finally {
    loading.value = false
  }
}

const clearAllCaches = async () => {
  try {
    realTimeStore.clearCache()
    await refreshCsrfToken()
    alert('All caches cleared successfully!')
  } catch (err) {
    console.error('Failed to clear caches:', err)
    alert('Failed to clear caches')
  }
}

const refreshCsrfToken = async () => {
  try {
    await refreshToken()
    console.log('CSRF token refreshed')
  } catch (err) {
    console.error('Failed to refresh CSRF token:', err)
  }
}

const forceNodeUpdate = async () => {
  try {
    await realTimeStore.fetchNodeDataOptimized()
    console.log('Node update forced')
  } catch (err) {
    console.error('Failed to force node update:', err)
  }
}

const toggleTabVisibility = () => {
  simulatedTabHidden.value = !simulatedTabHidden.value
  // This would normally be handled by the actual visibility change event
  console.log('Tab visibility simulation:', simulatedTabHidden.value ? 'hidden' : 'visible')
}

// Lifecycle
onMounted(() => {
  console.log('Polling Optimization Demo mounted')
})

onUnmounted(() => {
  if (demoActive.value) {
    toggleDemo()
  }
})
</script>

<style scoped>
.polling-optimization-demo {
  margin-top: 20px;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.card-header {
  background-color: #f8f9fa;
  border-bottom: 1px solid #e0e0e0;
  padding: 15px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-title {
  margin-bottom: 0;
  font-size: 1.25rem;
  color: #343a40;
}

.card-body {
  padding: 20px;
}

.status-grid, .metrics-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 15px;
}

.status-item, .metric-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background-color: #f8f9fa;
  border-radius: 4px;
  border-left: 3px solid #007bff;
}

.status-item .label, .metric-item .label {
  font-weight: 500;
  color: #6c757d;
}

.status-item .value, .metric-item .value {
  font-weight: 600;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-top: 15px;
}

.feature-item {
  padding: 15px;
  border: 2px solid #e9ecef;
  border-radius: 8px;
  text-align: center;
  transition: all 0.3s ease;
  background-color: #f8f9fa;
}

.feature-item.active {
  border-color: #28a745;
  background-color: #d4edda;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
}

.feature-item i {
  font-size: 1.5rem;
  margin-bottom: 8px;
  display: block;
  color: #6c757d;
}

.feature-item.active i {
  color: #28a745;
}

.feature-item span {
  display: block;
  font-weight: 600;
  margin-bottom: 4px;
  color: #343a40;
}

.feature-item small {
  color: #6c757d;
  font-size: 0.85rem;
}

.button-group {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.comparison-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-top: 15px;
}

.comparison-item {
  padding: 20px;
  border-radius: 8px;
  border: 2px solid #e9ecef;
}

.comparison-item.old {
  border-color: #dc3545;
  background-color: #f8d7da;
}

.comparison-item.new {
  border-color: #28a745;
  background-color: #d4edda;
}

.comparison-item h5 {
  margin-bottom: 15px;
  font-weight: 600;
}

.comparison-item ul {
  list-style: none;
  padding: 0;
  margin-bottom: 15px;
}

.comparison-item li {
  padding: 4px 0;
  color: #6c757d;
}

.performance-score {
  text-align: center;
  padding: 10px;
  background-color: rgba(255, 255, 255, 0.5);
  border-radius: 4px;
}

.score-label {
  font-weight: 500;
  margin-right: 8px;
}

.score-value {
  font-weight: 700;
  font-size: 1.1rem;
}

.score-value.bad {
  color: #dc3545;
}

.score-value.good {
  color: #28a745;
}

@media (max-width: 768px) {
  .status-grid, .metrics-grid {
    grid-template-columns: 1fr;
  }
  
  .comparison-grid {
    grid-template-columns: 1fr;
  }
  
  .features-grid {
    grid-template-columns: 1fr;
  }
  
  .button-group {
    flex-direction: column;
  }
}
</style>
