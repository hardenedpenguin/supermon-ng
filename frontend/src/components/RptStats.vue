<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>RPT Statistics</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>
      
      <div class="modal-body">
        <!-- Node Selection -->
        <div class="input-section">
          <label for="nodeInput">Node Number:</label>
          <input 
            id="nodeInput"
            v-model="nodeNumber" 
            type="text" 
            placeholder="Enter node number"
            class="node-input"
          />
          <div class="button-group">
            <button @click="getLocalStats" class="btn btn-primary" :disabled="!nodeNumber">
              Get Local Stats
            </button>
            <button @click="getExternalStats" class="btn btn-secondary" :disabled="!nodeNumber">
              Get External Stats
            </button>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading">
          <div class="spinner"></div>
          <p>Retrieving RPT statistics...</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="error-message">
          <p>{{ error }}</p>
        </div>

        <!-- External Stats Redirect -->
        <div v-if="externalUrl" class="external-redirect">
          <p>Redirecting to external AllStar Link statistics...</p>
          <a :href="externalUrl" target="_blank" class="btn btn-primary">
            Open External Stats
          </a>
          <button @click="closeExternalRedirect" class="btn btn-secondary">
            Cancel
          </button>
        </div>

        <!-- Local Stats Display -->
        <div v-if="localStats" class="stats-display">
          <div class="stats-header">
            <h4>RPT Statistics for Node {{ nodeNumber }}</h4>
            <span class="timestamp">{{ formatTimestamp(localStats.timestamp) }}</span>
          </div>
          <pre class="stats-content">{{ localStats.stats }}</pre>
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
  nodeNumber: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:isVisible'])

// Reactive state
const nodeNumber = ref(props.nodeNumber || '')
const loading = ref(false)
const error = ref('')
const externalUrl = ref('')
const localStats = ref(null)

// Watch for prop changes
watch(() => props.isVisible, (newVal) => {
  if (newVal && props.nodeNumber) {
    nodeNumber.value = props.nodeNumber
  }
})

watch(() => props.nodeNumber, (newVal) => {
  if (newVal) {
    nodeNumber.value = newVal
  }
})

// Methods
const closeModal = () => {
  emit('update:isVisible', false)
  resetState()
}

const resetState = () => {
  loading.value = false
  error.value = ''
  externalUrl.value = ''
  localStats.value = null
}

const getLocalStats = async () => {
  if (!nodeNumber.value) {
    error.value = 'Please enter a node number'
    return
  }

  loading.value = true
  error.value = ''
  externalUrl.value = ''
  localStats.value = null

  try {
    const response = await api.post('/nodes/rptstats', {
      localnode: nodeNumber.value
    })

    if (response.data.success) {
      if (response.data.type === 'local') {
        localStats.value = response.data.data
      } else if (response.data.type === 'external') {
        externalUrl.value = response.data.url
      }
    } else {
      error.value = response.data.message || 'Failed to retrieve RPT statistics'
    }
  } catch (err) {
    console.error('RPT Stats error:', err)
    error.value = err.response?.data?.message || 'Failed to retrieve RPT statistics'
  } finally {
    loading.value = false
  }
}

const getExternalStats = async () => {
  if (!nodeNumber.value) {
    error.value = 'Please enter a node number'
    return
  }

  loading.value = true
  error.value = ''
  externalUrl.value = ''
  localStats.value = null

  try {
    const response = await api.post('/nodes/rptstats', {
      node: nodeNumber.value
    })

    if (response.data.success) {
      if (response.data.type === 'external') {
        externalUrl.value = response.data.url
      }
    } else {
      error.value = response.data.message || 'Failed to retrieve external RPT statistics'
    }
  } catch (err) {
    console.error('External RPT Stats error:', err)
    error.value = err.response?.data?.message || 'Failed to retrieve external RPT statistics'
  } finally {
    loading.value = false
  }
}

const closeExternalRedirect = () => {
  externalUrl.value = ''
}

const formatTimestamp = (timestamp) => {
  if (!timestamp) return ''
  return new Date(timestamp).toLocaleString()
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background-color: var(--bg-color);
  border-radius: 8px;
  width: 90%;
  max-width: 800px;
  max-height: 90vh;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  background-color: var(--header-bg);
}

.modal-header h3 {
  margin: 0;
  color: var(--text-color);
}

.close-button {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: var(--text-color);
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-button:hover {
  color: var(--link-color);
}

.modal-body {
  padding: 1.5rem;
  max-height: calc(90vh - 120px);
  overflow-y: auto;
}

.input-section {
  margin-bottom: 1.5rem;
}

.input-section label {
  display: block;
  margin-bottom: 0.5rem;
  color: var(--text-color);
  font-weight: bold;
}

.node-input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background-color: var(--input-bg);
  color: var(--text-color);
  margin-bottom: 1rem;
}

.button-group {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: bold;
  transition: background-color 0.3s ease;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background-color: var(--link-color);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: var(--link-hover);
}

.btn-secondary {
  background-color: var(--secondary-bg);
  color: var(--text-color);
  border: 1px solid var(--border-color);
}

.btn-secondary:hover:not(:disabled) {
  background-color: var(--secondary-hover);
}

.loading {
  text-align: center;
  padding: 2rem;
}

.spinner {
  border: 3px solid var(--border-color);
  border-top: 3px solid var(--link-color);
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

.error-message {
  background-color: var(--error-bg);
  color: var(--error-color);
  padding: 1rem;
  border-radius: 4px;
  margin: 1rem 0;
}

.external-redirect {
  text-align: center;
  padding: 2rem;
  background-color: var(--info-bg);
  border-radius: 4px;
  margin: 1rem 0;
}

.external-redirect p {
  margin-bottom: 1rem;
  color: var(--text-color);
}

.stats-display {
  margin-top: 1.5rem;
}

.stats-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--border-color);
}

.stats-header h4 {
  margin: 0;
  color: var(--text-color);
}

.timestamp {
  font-size: 0.875rem;
  color: var(--secondary-text);
}

.stats-content {
  background-color: #000000;
  color: #ffffff;
  padding: 1rem;
  border-radius: 4px;
  font-family: 'Courier New', Consolas, monospace;
  font-size: 12px;
  line-height: 1.3;
  white-space: pre-wrap;
  word-wrap: break-word;
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid var(--border-color);
}
</style>
