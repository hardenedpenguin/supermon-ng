<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>Linux System Log Viewer</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>

      <div class="modal-body">
        <!-- Loading State -->
        <div v-if="loading" class="loading">
          <div class="spinner"></div>
          <p>Loading Linux system logs...</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="error-message">
          <p>{{ error }}</p>
        </div>

        <!-- Log Content Display -->
        <div v-if="logData" class="log-display">
          <div class="log-header">
            <h4>{{ logData.description }}</h4>
            <span class="timestamp">{{ formatTimestamp(logData.timestamp) }}</span>
          </div>
          
          <div class="log-info">
            <p><strong>Command:</strong> {{ logData.command }}</p>
          </div>

          <div class="log-content-container">
            <pre class="log-content">{{ logData.content }}</pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { api, fetchCsrfToken } from '@/utils/api'

const props = defineProps({
  isVisible: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:isVisible'])

// Reactive state
const loading = ref(false)
const error = ref('')
const logData = ref(null)

// Watch for modal visibility changes
watch(() => props.isVisible, (newVal) => {
  if (newVal) {
    loadLinuxLog()
  } else {
    resetState()
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
  logData.value = null
}

const loadLinuxLog = async () => {
  loading.value = true
  error.value = ''
  logData.value = null

  try {
    // Ensure CSRF token is available before making requests
    await fetchCsrfToken()
    
    const response = await api.post('/nodes/linuxlog', {}, { timeout: 30000 })

    if (response.data.success) {
      logData.value = response.data.data
    } else {
      error.value = response.data.message || 'Failed to retrieve Linux system log'
    }
  } catch (err) {
    console.error('Linux Log error:', err)
    error.value = err.response?.data?.message || 'Failed to retrieve Linux system log'
  } finally {
    loading.value = false
  }
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
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background-color: var(--background-color);
  border-radius: 8px;
  width: 95%;
  max-width: 1200px;
  max-height: 90vh;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  border: 1px solid var(--border-color);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  background-color: var(--container-bg);
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

.log-display {
  margin-top: 1rem;
}

.log-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--border-color);
}

.log-header h4 {
  margin: 0;
  color: var(--text-color);
}

.timestamp {
  font-size: 0.875rem;
  color: var(--secondary-text);
}

.log-info {
  background-color: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: 4px;
  padding: 1rem;
  margin-bottom: 1rem;
}

.log-info p {
  margin: 0;
  color: var(--text-color);
  font-family: 'Courier New', Consolas, monospace;
  font-size: 0.9rem;
  word-break: break-all;
}

.log-content-container {
  border: 1px solid var(--border-color);
  border-radius: 4px;
  overflow: hidden;
}

.log-content {
  background-color: #000000;
  color: #00ff00;
  padding: 1rem;
  margin: 0;
  font-family: 'Courier New', Consolas, monospace;
  font-size: 11px;
  line-height: 1.2;
  white-space: pre-wrap;
  word-wrap: break-word;
  max-height: 600px;
  overflow-y: auto;
  border: none;
  width: 100%;
  box-sizing: border-box;
}
</style>
