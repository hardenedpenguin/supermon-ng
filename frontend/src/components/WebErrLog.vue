<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>Web Error Log</h3>
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
        <div v-if="!loading && !error" class="weberrlog-content">
          <!-- Log File Info -->
          <div v-if="logData.log_file_path" class="log-file-info">
            <p><strong>Log File:</strong> {{ logData.log_file_path }}</p>
            <p><strong>Last Updated:</strong> {{ formatTimestamp(logData.timestamp) }}</p>
          </div>

          <!-- Parsed Log Entries Table -->
          <div v-if="logData.parsed_data && logData.parsed_data.rows && logData.parsed_data.rows.length > 0" class="log-entries">
            <h4>Web Error Log Entries</h4>
            <div class="table-container">
              <table class="log-table">
                <thead>
                  <tr>
                    <th v-for="header in logData.parsed_data.headers" :key="header" class="log-header">
                      {{ header }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, index) in logData.parsed_data.rows" :key="index" class="log-row">
                    <td v-for="(cell, cellIndex) in row" :key="cellIndex" class="log-cell">
                      <span v-if="cellIndex === 0" class="line-number">{{ cell }}</span>
                      <span v-else-if="cellIndex === 2" class="log-level" :class="getLevelClass(cell)">{{ cell }}</span>
                      <span v-else class="log-content">{{ cell }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- No Entries -->
          <div v-else-if="logData.parsed_data && (!logData.parsed_data.rows || logData.parsed_data.rows.length === 0)" class="no-entries">
            <p>No error log entries found in the web error log file.</p>
          </div>
        </div>

        <!-- Refresh Button -->
        <div v-if="!loading && !error" class="refresh-section">
          <button @click="refreshLog" class="refresh-button" :disabled="refreshing">
            {{ refreshing ? 'Refreshing...' : 'Refresh Log' }}
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
  }
})

const emit = defineEmits(['update:isVisible'])

// Reactive state
const loading = ref(false)
const refreshing = ref(false)
const error = ref(null)
const logData = ref({})
const loadingMessage = ref('Loading web error log...')

// Watch for modal visibility changes
watch(() => props.isVisible, (newVal) => {
  if (newVal) {
    loadLog()
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
  logData.value = {}
}

const loadLog = async () => {
  loading.value = true
  error.value = null
  loadingMessage.value = 'Loading web error log...'

  try {
    const response = await api.post('/nodes/weberrlog')

    if (response.data.success) {
      logData.value = response.data.data
    } else {
      error.value = response.data.message || 'Failed to load web error log'
    }
  } catch (err) {
    console.error('Web error log error:', err)
    error.value = err.response?.data?.message || 'Failed to load web error log'
  } finally {
    loading.value = false
  }
}

const refreshLog = async () => {
  refreshing.value = true
  error.value = null

  try {
    const response = await api.post('/nodes/weberrlog')

    if (response.data.success) {
      logData.value = response.data.data
    } else {
      error.value = response.data.message || 'Failed to refresh web error log'
    }
  } catch (err) {
    console.error('Web error log refresh error:', err)
    error.value = err.response?.data?.message || 'Failed to refresh web error log'
  } finally {
    refreshing.value = false
  }
}

const formatTimestamp = (timestamp) => {
  if (!timestamp) return ''
  return new Date(timestamp).toLocaleString()
}

const getLevelClass = (level) => {
  const levelLower = level.toLowerCase()
  if (levelLower === 'error') return 'level-error'
  if (levelLower === 'warn' || levelLower === 'warning') return 'level-warning'
  if (levelLower === 'info') return 'level-info'
  if (levelLower === 'debug') return 'level-debug'
  return 'level-default'
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
  background-color: var(--background-color);
  border-radius: 8px;
  width: 95%;
  max-width: 1400px;
  max-height: 90vh;
  overflow-y: auto;
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
}

.loading {
  text-align: center;
  padding: 2rem;
}

.spinner {
  border: 3px solid var(--border-color);
  border-top: 3px solid var(--primary-color);
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
  color: var(--text-color);
  margin: 0;
}

.error-message {
  text-align: center;
  padding: 2rem;
  color: #dc3545;
  font-weight: bold;
}

.weberrlog-content {
  color: var(--text-color);
}

.log-file-info {
  background-color: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: 4px;
  padding: 1rem;
  margin-bottom: 1.5rem;
}

.log-file-info p {
  margin: 0.5rem 0;
  color: var(--secondary-text);
}

.log-entries h4 {
  margin-bottom: 1rem;
  color: var(--text-color);
}

.table-container {
  overflow-x: auto;
  border: 1px solid var(--border-color);
  border-radius: 4px;
}

.log-table {
  width: 100%;
  border-collapse: collapse;
  font-family: 'Courier New', Consolas, monospace;
  font-size: 12px;
}

.log-table th {
  background-color: var(--container-bg);
  color: var(--text-color);
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid var(--border-color);
  font-weight: bold;
  white-space: nowrap;
}

.log-table td {
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid var(--border-color);
  vertical-align: top;
  word-wrap: break-word;
}

.log-row:hover {
  background-color: var(--container-bg);
}

.line-number {
  color: var(--secondary-text);
  font-weight: bold;
  text-align: center;
}

.log-level {
  font-weight: bold;
  padding: 2px 6px;
  border-radius: 3px;
  font-size: 11px;
  text-transform: uppercase;
}

.level-error {
  background-color: #dc3545;
  color: white;
}

.level-warning {
  background-color: #ffc107;
  color: #212529;
}

.level-info {
  background-color: #17a2b8;
  color: white;
}

.level-debug {
  background-color: #6c757d;
  color: white;
}

.level-default {
  background-color: var(--border-color);
  color: var(--text-color);
}

.log-content {
  word-break: break-all;
  max-width: 300px;
}

.no-entries {
  text-align: center;
  padding: 2rem;
  color: var(--secondary-text);
  font-style: italic;
}

.refresh-section {
  text-align: center;
  margin-top: 2rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-color);
}

.refresh-button {
  background-color: var(--primary-color);
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  transition: background-color 0.3s ease;
}

.refresh-button:hover:not(:disabled) {
  background-color: var(--link-color);
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
  }
  
  .log-table {
    font-size: 11px;
  }
  
  .log-table th,
  .log-table td {
    padding: 0.25rem 0.5rem;
  }
  
  .log-content {
    max-width: 150px;
  }
}
</style>
