<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>Supermon Login/Out Log</h3>
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
        <div v-if="!loading && !error" class="smlog-content">
          <!-- Log File Info -->
          <div class="log-info">
            <p><strong>File:</strong> {{ logFilePath }}</p>
            <p><strong>Last Updated:</strong> {{ formatTimestamp(timestamp) }}</p>
          </div>

          <!-- Log Content -->
          <div class="log-content">
            <div v-if="logContent && logContent.length > 0" class="log-entries">
              <div 
                v-for="(entry, index) in logContent" 
                :key="index" 
                class="log-entry"
              >
                {{ entry }}
              </div>
            </div>
            <div v-else class="empty-log">
              <p>No log entries found</p>
            </div>
          </div>

          <!-- Actions -->
          <div class="log-actions">
            <button @click="refreshLog" class="refresh-button" :disabled="loading">
              {{ loading ? 'Refreshing...' : 'Refresh Log' }}
            </button>
            <button @click="closeModal" class="close-button-secondary">
              Close
            </button>
          </div>
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
  }
})

const emit = defineEmits(['update:isVisible'])

// Reactive state
const loading = ref(false)
const error = ref('')
const loadingMessage = ref('Loading...')
const logFilePath = ref('')
const logContent = ref([])
const timestamp = ref('')

// Watch for modal visibility changes
watch(() => props.isVisible, (newVal) => {
  if (newVal) {
    resetState()
    loadSMLog()
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
  logFilePath.value = ''
  logContent.value = []
  timestamp.value = ''
}

const loadSMLog = async () => {
  loading.value = true
  loadingMessage.value = 'Loading Supermon log...'
  error.value = ''

  try {
    const response = await api.post('/nodes/smlog')

    if (response.data.success) {
      logFilePath.value = response.data.data.log_file_path
      logContent.value = response.data.data.log_content
      timestamp.value = response.data.data.timestamp
    } else {
      error.value = response.data.message || 'Failed to load Supermon log'
    }
  } catch (err) {
    console.error('SMLog error:', err)
    error.value = err.response?.data?.message || 'Failed to load Supermon log'
  } finally {
    loading.value = false
  }
}

const refreshLog = () => {
  loadSMLog()
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
  max-width: 1000px;
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

.smlog-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.log-info {
  background-color: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: 4px;
  padding: 1rem;
}

.log-info p {
  margin: 0.5rem 0;
  color: var(--text-color);
  font-size: 0.9rem;
}

.log-content {
  background-color: #000;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  padding: 1rem;
  max-height: 400px;
  overflow-y: auto;
  font-family: 'Courier New', monospace;
  font-size: 0.85rem;
  line-height: 1.4;
}

.log-entries {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.log-entry {
  color: #00ff00;
  padding: 0.25rem 0;
  border-bottom: 1px solid #333;
  word-wrap: break-word;
  white-space: pre-wrap;
}

.log-entry:last-child {
  border-bottom: none;
}

.empty-log {
  text-align: center;
  padding: 2rem;
  color: #666;
  font-style: italic;
}

.log-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  padding-top: 1rem;
  border-top: 1px solid var(--border-color);
}

.refresh-button, .close-button-secondary {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 4px;
  font-size: 1rem;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

.refresh-button {
  background-color: var(--primary-color);
  color: white;
}

.refresh-button:hover:not(:disabled) {
  background-color: var(--link-color);
}

.refresh-button:disabled {
  background-color: var(--secondary-bg);
  cursor: not-allowed;
}

.close-button-secondary {
  background-color: var(--secondary-bg);
  color: var(--text-color);
  border: 1px solid var(--border-color);
}

.close-button-secondary:hover {
  background-color: var(--border-color);
}

@media (max-width: 768px) {
  .modal-content {
    width: 98%;
    max-width: none;
  }
  
  .log-actions {
    flex-direction: column;
  }
  
  .log-content {
    font-size: 0.8rem;
  }
}
</style>
