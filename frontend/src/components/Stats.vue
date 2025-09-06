<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>AllStar Statistics</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>

      <div class="modal-body">
        <!-- Loading State -->
        <div v-if="loading" class="loading">
          <div class="spinner"></div>
          <p>{{ loadingMessage }}</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="error-message">
          <p>{{ error }}</p>
        </div>

        <!-- Main Content -->
        <div v-if="!loading && !error" class="stats-content">
          <!-- Header -->
          <div v-if="statsData.header" class="stats-header">
            <div class="header-line">#################################################################</div>
            <div class="header-info">
              <span class="highlight">{{ statsData.header.host }}</span> AllStar Status: 
              <span class="highlight">{{ statsData.header.date }}</span>
            </div>
            <div class="header-line">#################################################################</div>
          </div>

          <!-- All Nodes Section -->
          <div v-if="statsData.all_nodes" class="stats-section">
            <div v-if="statsData.all_nodes.error" class="error-text">
              {{ statsData.all_nodes.error }}
            </div>
            <div v-else-if="statsData.all_nodes.message" class="none-indicator">
              {{ statsData.all_nodes.message }}
            </div>
            <div v-else-if="statsData.all_nodes.nodes" class="nodes-section">
              <div v-for="node in statsData.all_nodes.nodes" :key="node.node_number" class="node-info">
                <div class="node-header">
                  Node <span class="node-number">{{ node.node_number }}</span> connections => <span class="highlight">{{ node.connections_count || '<NONE>' }}</span>
                </div>
                
                <!-- Connected Nodes Section -->
                <div class="connected-nodes-section">
                  <div class="section-title">************************* CONNECTED NODES *************************</div>
                  <pre class="stats-pre">{{ node.connected_nodes_formatted || '<NONE>' }}</pre>
                </div>
                
                <!-- LStats Info -->
                <div v-if="node.lstats_info" class="lstats-info">
                  <div class="section-title">***************************** LSTATS ******************************</div>
                  <pre class="stats-pre">{{ node.lstats_info }}</pre>
                </div>
              </div>
            </div>
          </div>

          <!-- Peers Section -->
          <div v-if="statsData.peers" class="stats-section">
            <div class="section-title">*************************** OTHER PEERS ***************************</div>
            <div v-if="statsData.peers.error" class="error-text">
              {{ statsData.peers.error }}
            </div>
            <div v-else-if="statsData.peers.message" class="none-indicator">
              {{ statsData.peers.message }}
            </div>
            <div v-else-if="statsData.peers.peers" class="peers-info">
              <pre class="stats-pre">{{ statsData.peers.peers }}</pre>
            </div>
          </div>

          <!-- Channels Section -->
          <div v-if="statsData.channels" class="stats-section">
            <div class="section-title">**************************** CHANNELS *****************************</div>
            <div v-if="statsData.channels.error" class="error-text">
              {{ statsData.channels.error }}
            </div>
            <div v-else-if="statsData.channels.message" class="none-indicator">
              {{ statsData.channels.message }}
            </div>
            <div v-else-if="statsData.channels.channels" class="channels-info">
              <pre class="stats-pre">{{ statsData.channels.channels }}</pre>
            </div>
          </div>

          <!-- Netstats Section -->
          <div v-if="statsData.netstats" class="stats-section">
            <div class="section-title">**************************** NETSTATS *****************************</div>
            <div v-if="statsData.netstats.error" class="error-text">
              {{ statsData.netstats.error }}
            </div>
            <div v-else-if="statsData.netstats.message" class="none-indicator">
              {{ statsData.netstats.message }}
            </div>
            <div v-else-if="statsData.netstats.netstats" class="netstats-info">
              <pre class="stats-pre">{{ statsData.netstats.netstats }}</pre>
            </div>
          </div>
        </div>

        <!-- Refresh Button -->
        <div v-if="!loading && !error" class="refresh-section">
          <button @click="refreshStats" class="refresh-button" :disabled="refreshing">
            {{ refreshing ? 'Refreshing...' : 'Refresh Statistics' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { api } from '@/utils/api'

const props = defineProps({
  isVisible: {
    type: Boolean,
    default: false
  },
  localnode: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['update:isVisible'])

// Reactive state
const loading = ref(false)
const refreshing = ref(false)
const error = ref(null)
const statsData = ref({})
const loadingMessage = ref('Loading AllStar statistics...')

// Watch for modal visibility changes
watch(() => props.isVisible, (newVal) => {
  if (newVal && props.localnode) {
    loadStats()
  }
})

// Methods
const closeModal = () => {
  emit('update:isVisible', false)
  resetState()
}

const resetState = () => {
  loading.value = false
  refreshing.value = false
  error.value = null
  statsData.value = {}
}

const loadStats = async () => {
  if (!props.localnode) {
    error.value = 'No local node specified'
    return
  }

  loading.value = true
  error.value = null
  loadingMessage.value = 'Loading AllStar statistics...'

  try {
    const response = await api.post('/nodes/stats', {
      localnode: props.localnode
    })

    if (response.data.success) {
      statsData.value = response.data.data
    } else {
      error.value = response.data.message || 'Failed to load AllStar statistics'
    }
  } catch (err) {
    console.error('Stats error:', err)
    error.value = err.response?.data?.message || 'Failed to load AllStar statistics'
  } finally {
    loading.value = false
  }
}

const refreshStats = async () => {
  if (!props.localnode) return

  refreshing.value = true
  error.value = null

  try {
    const response = await api.post('/nodes/stats', {
      localnode: props.localnode
    })

    if (response.data.success) {
      statsData.value = response.data.data
    } else {
      error.value = response.data.message || 'Failed to refresh AllStar statistics'
    }
  } catch (err) {
    console.error('Stats refresh error:', err)
    error.value = err.response?.data?.message || 'Failed to refresh AllStar statistics'
  } finally {
    refreshing.value = false
  }
}

</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background-color: #000000;
  border-radius: 8px;
  width: 95%;
  max-width: 1200px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  border: 1px solid #404040;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #404040;
  background-color: #2a2a2a;
}

.modal-header h3 {
  margin: 0;
  color: #e0e0e0;
}

.close-button {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #e0e0e0;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-button:hover {
  color: #2196f3;
}

.modal-body {
  padding: 1.5rem;
  font-family: 'Courier New', Consolas, monospace;
  font-size: 12px;
  line-height: 1.3;
  color: white;
}

.loading {
  text-align: center;
  padding: 2rem;
}

.spinner {
  border: 3px solid #404040;
  border-top: 3px solid #00ff00;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.loading p {
  color: #e0e0e0;
  margin: 0;
}

.error-message {
  text-align: center;
  padding: 2rem;
  color: #ff4444;
  font-weight: bold;
}

.stats-content {
  white-space: pre-wrap;
  word-wrap: break-word;
}

.stats-header {
  margin-bottom: 2rem;
}

.header-line {
  color: #888;
}

.header-info {
  margin: 0.5rem 0;
}

.highlight {
  color: #00ff00;
  font-weight: bold;
}

.stats-section {
  margin-bottom: 2rem;
}

.section-title {
  color: #ffff00;
  font-weight: bold;
  margin: 1rem 0;
}

.node-info {
  margin-bottom: 2rem;
}

.node-header {
  margin-bottom: 1rem;
}

.node-number {
  color: #00ff00;
  font-weight: bold;
}

.connected-nodes-section {
  margin: 1rem 0;
}

.connected-nodes-section .stats-pre {
  color: #00ffff;
  font-weight: bold;
}

.xnode-info,
.lstats-info,
.peers-info,
.channels-info,
.netstats-info {
  margin: 1rem 0;
}

.stats-pre {
  background-color: transparent;
  color: white;
  font-family: 'Courier New', Consolas, monospace;
  font-size: 12px;
  line-height: 1.3;
  white-space: pre-wrap;
  word-wrap: break-word;
  margin: 0;
  padding: 0;
  border: none;
  overflow-x: auto;
}

.error-text {
  color: #ff4444;
  font-weight: bold;
}

.none-indicator {
  color: #888;
  font-style: italic;
}

.refresh-section {
  text-align: center;
  margin-top: 2rem;
  padding-top: 1rem;
  border-top: 1px solid #404040;
}

.refresh-button {
  background-color: #2a2a2a;
  color: #e0e0e0;
  border: 1px solid #404040;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.3s ease;
}

.refresh-button:hover:not(:disabled) {
  background-color: #404040;
  border-color: #00ff00;
}

.refresh-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Responsive design */
@media (max-width: 768px) {
  .modal-content {
    width: 98%;
    max-height: 95vh;
  }
  
  .modal-body {
    padding: 1rem;
    font-size: 11px;
  }
  
  .stats-pre {
    font-size: 11px;
  }
}
</style>
