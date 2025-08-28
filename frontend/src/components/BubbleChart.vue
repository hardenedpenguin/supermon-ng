<template>
  <div v-if="open" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <!-- Modal Header -->
      <div class="modal-header">
        <h3>Bubble Chart</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <!-- Node Input Form -->
        <div class="form-group">
          <label for="nodeInput">Node Number:</label>
          <input
            id="nodeInput"
            v-model="nodeInput"
            type="text"
            class="form-control"
            placeholder="Enter node number (e.g., 546051)"
            @keyup.enter="openBubbleChart"
          />
        </div>

        <!-- Status Messages -->
        <div v-if="loading" class="status-message loading">
          <i class="fas fa-spinner fa-spin"></i> Loading...
        </div>
        
        <div v-if="error" class="status-message error">
          <i class="fas fa-exclamation-triangle"></i> {{ error }}
        </div>

        <div v-if="success" class="status-message success">
          <i class="fas fa-check-circle"></i> {{ success }}
        </div>

        <!-- Bubble Chart URL Display -->
        <div v-if="bubbleChartUrl" class="bubble-chart-info">
          <h4>Bubble Chart URL:</h4>
          <div class="url-display">
            <code>{{ bubbleChartUrl }}</code>
            <button class="copy-button" @click="copyToClipboard">
              <i class="fas fa-copy"></i> Copy
            </button>
          </div>
          <p class="url-description">
            This URL will open the AllStarLink bubble chart for node {{ nodeInput }} on stats.allstarlink.org
          </p>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <button 
          class="btn btn-primary" 
          @click="openBubbleChart"
          :disabled="loading || !nodeInput.trim()"
        >
          <i class="fas fa-external-link-alt"></i> Open Bubble Chart
        </button>
        <button class="btn btn-secondary" @click="closeModal">
          Close
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

// Props
const props = defineProps({
  open: {
    type: Boolean,
    default: false
  },
  localNode: {
    type: String,
    default: ''
  }
})

// Emits
const emit = defineEmits(['update:open'])

// Reactive data
const nodeInput = ref('')
const loading = ref(false)
const error = ref('')
const success = ref('')
const bubbleChartUrl = ref('')

// Methods
const closeModal = () => {
  emit('update:open', false)
  resetState()
}

const resetState = () => {
  nodeInput.value = ''
  loading.value = false
  error.value = ''
  success.value = ''
  bubbleChartUrl.value = ''
}

const openBubbleChart = async () => {
  if (!nodeInput.value.trim()) {
    error.value = 'Please enter a node number'
    return
  }

  loading.value = true
  error.value = ''
  success.value = ''
  bubbleChartUrl.value = ''

  try {
    const response = await axios.post('/api/config/bubblechart', {
      node: nodeInput.value.trim(),
      localNode: props.localNode || ''
    })

    if (response.data.success) {
      bubbleChartUrl.value = response.data.statsUrl
      success.value = response.data.message || 'Bubble chart URL generated successfully'
      
      // Open the bubble chart in a new window
      window.open(bubbleChartUrl.value, 'BubbleChart', 'status=no,location=no,toolbar=no,width=1200,height=800,left=100,top=100')
    } else {
      error.value = response.data.message || 'Failed to generate bubble chart URL'
    }
  } catch (err) {
    console.error('Bubble chart error:', err)
    if (err.response?.data?.message) {
      error.value = err.response.data.message
    } else {
      error.value = 'Failed to generate bubble chart URL. Please try again.'
    }
  } finally {
    loading.value = false
  }
}

const copyToClipboard = async () => {
  if (!bubbleChartUrl.value) return

  try {
    await navigator.clipboard.writeText(bubbleChartUrl.value)
    success.value = 'URL copied to clipboard!'
    setTimeout(() => {
      success.value = ''
    }, 2000)
  } catch (err) {
    console.error('Copy to clipboard failed:', err)
    error.value = 'Failed to copy URL to clipboard'
  }
}

// Watch for modal open/close
watch(() => props.open, (newValue) => {
  if (newValue) {
    // When modal opens, pre-fill with local node if available
    if (props.localNode && !nodeInput.value) {
      nodeInput.value = props.localNode
    }
  } else {
    resetState()
  }
})
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
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
  width: 90%;
  max-width: 600px;
  max-height: 80vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #e0e0e0;
  background: #f8f9fa;
  border-radius: 8px 8px 0 0;
}

.modal-header h3 {
  margin: 0;
  color: #333;
  font-size: 1.25rem;
}

.close-button {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #666;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: background-color 0.2s;
}

.close-button:hover {
  background-color: #e0e0e0;
  color: #333;
}

.modal-body {
  padding: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #333;
}

.form-control {
  width: 100%;
  padding: 12px;
  border: 2px solid #ddd;
  border-radius: 6px;
  font-size: 1rem;
  transition: border-color 0.2s;
  box-sizing: border-box;
}

.form-control:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.status-message {
  padding: 12px;
  border-radius: 6px;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.status-message.loading {
  background-color: #e3f2fd;
  color: #1976d2;
  border: 1px solid #bbdefb;
}

.status-message.error {
  background-color: #ffebee;
  color: #c62828;
  border: 1px solid #ffcdd2;
}

.status-message.success {
  background-color: #e8f5e8;
  color: #2e7d32;
  border: 1px solid #c8e6c9;
}

.bubble-chart-info {
  margin-top: 20px;
  padding: 15px;
  background-color: #f8f9fa;
  border-radius: 6px;
  border: 1px solid #e9ecef;
}

.bubble-chart-info h4 {
  margin: 0 0 10px 0;
  color: #333;
  font-size: 1.1rem;
}

.url-display {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
  flex-wrap: wrap;
}

.url-display code {
  flex: 1;
  min-width: 200px;
  padding: 8px 12px;
  background-color: #f1f3f4;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-family: 'Courier New', monospace;
  font-size: 0.9rem;
  word-break: break-all;
}

.copy-button {
  background-color: #6c757d;
  color: white;
  border: none;
  padding: 8px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  gap: 5px;
  transition: background-color 0.2s;
  white-space: nowrap;
}

.copy-button:hover {
  background-color: #5a6268;
}

.url-description {
  margin: 0;
  color: #666;
  font-size: 0.9rem;
  line-height: 1.4;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 20px;
  border-top: 1px solid #e0e0e0;
  background: #f8f9fa;
  border-radius: 0 0 8px 8px;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 1rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background-color: #007bff;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #0056b3;
}

.btn-secondary {
  background-color: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background-color: #5a6268;
}

/* Responsive design */
@media (max-width: 768px) {
  .modal-content {
    width: 95%;
    margin: 10px;
  }
  
  .url-display {
    flex-direction: column;
    align-items: stretch;
  }
  
  .url-display code {
    min-width: auto;
  }
  
  .modal-footer {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
