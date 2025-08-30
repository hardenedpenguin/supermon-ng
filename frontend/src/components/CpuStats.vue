<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>CPU and System Statistics</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>
      
      <div class="modal-body">
        <!-- Loading State -->
        <div v-if="loading" class="loading">
          <div class="spinner"></div>
          <p>Retrieving system statistics...</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="error-message">
          <p>{{ error }}</p>
        </div>

        <!-- Statistics Display -->
        <div v-if="statsData" class="stats-display">
          <div class="stats-header">
            <h4>System Statistics</h4>
            <span class="timestamp">{{ formatTimestamp(statsData.timestamp) }}</span>
          </div>
          <pre class="stats-content">{{ statsData.content }}</pre>
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
const statsData = ref(null)

// Watch for modal visibility changes
watch(() => props.isVisible, (newVal) => {
  if (newVal) {
    loadCpuStats()
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
  statsData.value = null
}

const loadCpuStats = async () => {
  loading.value = true
  error.value = ''
  statsData.value = null

  try {
    const response = await api.post('/nodes/cpustats')

    if (response.data.success) {
      statsData.value = response.data.data
    } else {
      error.value = response.data.message || 'Failed to retrieve CPU statistics'
    }
  } catch (err) {
    console.error('CPU Stats error:', err)
    error.value = err.response?.data?.message || 'Failed to retrieve CPU statistics'
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
  width: 80%;
  max-width: 900px;
  max-height: 80vh;
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
  max-height: calc(80vh - 120px);
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

.stats-display {
  margin-top: 1rem;
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
  max-height: 500px;
  overflow-y: auto;
  border: 1px solid var(--border-color);
}
</style>
