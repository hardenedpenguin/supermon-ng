<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>Fast Restart Asterisk</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>

      <div class="modal-body">
        <!-- Confirmation State -->
        <div v-if="!isExecuting && !result" class="confirmation">
          <div class="warning-icon">⚠️</div>
          <h4>Confirm Fast Restart</h4>
          <p class="warning-text">
            You are about to perform a <strong>FAST RESTART</strong> of Asterisk on node <strong>{{ localnode }}</strong>.
          </p>
          <p class="warning-text">
            This will immediately restart the Asterisk service. All active calls and connections will be terminated.
          </p>
          <p class="warning-text">
            <strong>This action cannot be undone!</strong>
          </p>
          
          <div class="confirmation-actions">
            <button @click="executeRestart" class="restart-button">
              🚀 Execute Fast Restart
            </button>
            <button @click="closeModal" class="cancel-button">
              Cancel
            </button>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="isExecuting" class="loading">
          <div class="spinner"></div>
          <h4>Executing Fast Restart...</h4>
          <p>Please wait while the restart command is being sent to Asterisk.</p>
        </div>

        <!-- Result State -->
        <div v-if="result" class="result">
          <div v-if="result.success" class="success-result">
            <div class="success-icon">✅</div>
            <h4>Fast Restart Executed Successfully</h4>
            <p>{{ result.message }}</p>
            <div class="result-details">
              <p><strong>Node:</strong> {{ result.localnode }}</p>
              <p><strong>Timestamp:</strong> {{ formatTimestamp(result.timestamp) }}</p>
            </div>
          </div>
          
          <div v-else class="error-result">
            <div class="error-icon">❌</div>
            <h4>Fast Restart Failed</h4>
            <p>{{ result.message }}</p>
          </div>
          
          <div class="result-actions">
            <button @click="closeModal" class="close-result-button">
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
  },
  localnode: {
    type: [String, Number],
    default: null
  }
})

const emit = defineEmits(['update:isVisible'])

// Reactive state
const isExecuting = ref(false)
const result = ref(null)

// Watch for modal visibility changes
watch(() => props.isVisible, (newVal) => {
  if (!newVal) {
    resetState()
  }
})

// Methods
const closeModal = () => {
  emit('update:isVisible', false)
  resetState()
}

const resetState = () => {
  isExecuting.value = false
  result.value = null
}

const executeRestart = async () => {
  if (!props.localnode) {
    result.value = {
      success: false,
      message: 'No node selected for restart operation.'
    }
    return
  }

  isExecuting.value = true
  result.value = null

  try {
    const response = await api.post('/nodes/fastrestart', { localnode: props.localnode })

    if (response.data.success) {
      result.value = response.data.data
    } else {
      result.value = {
        success: false,
        message: response.data.message || 'Failed to execute fast restart'
      }
    }
  } catch (err) {
    console.error('Fast restart error:', err)
    result.value = {
      success: false,
      message: err.response?.data?.message || 'Failed to execute fast restart'
    }
  } finally {
    isExecuting.value = false
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
  background-color: rgba(0, 0, 0, 0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background-color: var(--background-color);
  border-radius: 8px;
  width: 90%;
  max-width: 500px;
  max-height: 80vh;
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

.confirmation {
  text-align: center;
}

.warning-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.confirmation h4 {
  color: #ff6b35;
  margin-bottom: 1rem;
}

.warning-text {
  color: var(--text-color);
  line-height: 1.5;
  margin-bottom: 1rem;
}

.confirmation-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
  margin-top: 2rem;
}

.restart-button {
  background-color: #dc3545;
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 6px;
  font-size: 1rem;
  font-weight: bold;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

.restart-button:hover {
  background-color: #c82333;
}

.cancel-button {
  background-color: var(--secondary-bg);
  color: var(--text-color);
  border: 1px solid var(--border-color);
  padding: 12px 24px;
  border-radius: 6px;
  font-size: 1rem;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

.cancel-button:hover {
  background-color: var(--border-color);
}

.loading {
  text-align: center;
  padding: 2rem;
}

.spinner {
  border: 3px solid var(--border-color);
  border-top: 3px solid #ff6b35;
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

.loading h4 {
  color: var(--text-color);
  margin-bottom: 1rem;
}

.loading p {
  color: var(--secondary-text);
}

.result {
  text-align: center;
}

.success-result {
  color: var(--text-color);
}

.success-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.success-result h4 {
  color: #28a745;
  margin-bottom: 1rem;
}

.error-result {
  color: var(--text-color);
}

.error-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.error-result h4 {
  color: #dc3545;
  margin-bottom: 1rem;
}

.result-details {
  background-color: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: 4px;
  padding: 1rem;
  margin: 1rem 0;
  text-align: left;
}

.result-details p {
  margin: 0.5rem 0;
  color: var(--secondary-text);
}

.result-actions {
  margin-top: 2rem;
}

.close-result-button {
  background-color: var(--primary-color);
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 6px;
  font-size: 1rem;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

.close-result-button:hover {
  background-color: var(--link-color);
}
</style>
