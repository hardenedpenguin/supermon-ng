<template>
  <div v-if="show" class="voter-modal-overlay" @click="closeModal">
    <div class="voter-modal" @click.stop>
      <div class="voter-modal-header">
        <h2>Voter Status</h2>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>
      
      <div class="voter-modal-content">
        <!-- Node Input -->
        <div class="voter-input-section">
          <label for="voter-nodes">Node(s):</label>
          <input 
            id="voter-nodes"
            v-model="nodeInput" 
            type="text" 
            placeholder="Enter node number(s) e.g., 1234 or 1234,5678"
            @keyup.enter="startVoter"
          />
          <button @click="startVoter" :disabled="!nodeInput.trim()">Start Voter</button>
        </div>

        <!-- Voter Containers -->
        <div v-if="nodes.length > 0" class="voter-containers">
          <div v-for="node in nodes" :key="node" class="voter-container">
            <div :id="`link_list_${node}`" class="voter-content">
              Connecting to Node {{ node }}...
            </div>
            <div class="voter-spinner">
              <span :id="`spinner_${node}`" class="spinner-text">{{ getSpinner(node) }}</span>
            </div>
          </div>
        </div>

        <!-- Voter Information -->
        <div class="voter-info-container">
          <div class="voter-description">
            The numbers indicate the relative signal strength. The value ranges from 0 to 255, a range of approximately 30db.
            A value of zero means that no signal is being received. The color of the bars indicate the type of RTCM client.
          </div>
          <div class="voter-legend">
            <div class="legend-item legend-voting">A blue bar indicates a voting station.</div>
            <div class="legend-item legend-voted">Green indicates the station is voted.</div>
            <div class="legend-item legend-mix">Cyan is a non-voting mix station.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onUnmounted, watch } from 'vue'
import { api } from '@/utils/api'
import { sanitizeHtml } from '@/utils/sanitize'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close'])

const nodeInput = ref('')
const nodes = ref([])
const eventSources = ref({})
const spinners = ref({})
const spinnerChars = ['*', '|', '/', '-', '\\']
let spinnerInterval = null
const pollingTimeouts = ref(new Map())

const closeModal = () => {
  stopVoter()
  emit('close')
}

const startVoter = () => {
  if (!nodeInput.value.trim()) return
  
  // Parse nodes
  const nodeList = nodeInput.value.split(',').map(n => n.trim()).filter(n => n)
  if (nodeList.length === 0) return
  
  nodes.value = nodeList
  startSpinner()
  initializeEventSources()
}

const stopVoter = () => {
  // Close all event sources
  Object.values(eventSources.value).forEach(source => {
    if (source) {
      source.close()
    }
  })
  eventSources.value = {}
  
  // Clear spinner interval
  if (spinnerInterval) {
    clearInterval(spinnerInterval)
    spinnerInterval = null
  }
  
  // Clear all polling timeouts to prevent memory leaks
  pollingTimeouts.value.forEach((timeout, node) => {
    clearTimeout(timeout)
  })
  pollingTimeouts.value.clear()
  
  // Reset state
  nodes.value = []
  spinners.value = {}
}

const startSpinner = () => {
  let spinIndex = 0
  spinnerInterval = setInterval(() => {
    nodes.value.forEach(node => {
      spinners.value[node] = spinnerChars[spinIndex]
    })
    spinIndex = (spinIndex + 1) % spinnerChars.length
  }, 200)
}

const getSpinner = (node) => {
  return spinners.value[node] || '*'
}

const initializeEventSources = () => {
  nodes.value.forEach(node => {
    if (node && node.trim() !== '') {
      // Use polling instead of EventSource for compatibility
      pollVoterStatus(node)
    }
  })
}

const pollVoterStatus = async (node) => {
  // Check if node is still being monitored (prevents polling after stopVoter is called)
  if (!nodes.value.includes(node)) {
    return
  }
  
  try {
    const response = await api.get(`/nodes/voter/status?node=${encodeURIComponent(node)}`)
    
    if (response.data.html) {
      const element = document.getElementById(`link_list_${node}`)
      if (element) {
        element.innerHTML = sanitizeHtml(response.data.html)
      }
    }
    
    if (response.data.spinner) {
      spinners.value[node] = response.data.spinner
    }
    
    // Continue polling only if node is still being monitored
    if (nodes.value.includes(node)) {
      const timeoutId = setTimeout(() => pollVoterStatus(node), 1000)
      pollingTimeouts.value.set(node, timeoutId)
    }
    
  } catch (error) {
    console.error(`Error polling voter status for node ${node}:`, error)
    const element = document.getElementById(`link_list_${node}`)
    if (element) {
      element.innerHTML = sanitizeHtml(`<div class='error-message'>Error receiving updates for node ${node}. The connection was lost.</div>`)
    }
    spinners.value[node] = 'X'
    
    // Clear timeout for this node on error
    const timeoutId = pollingTimeouts.value.get(node)
    if (timeoutId) {
      clearTimeout(timeoutId)
      pollingTimeouts.value.delete(node)
    }
  }
}

// Cleanup on unmount
onUnmounted(() => {
  stopVoter()
})

// Watch for modal close
watch(() => props.show, (newVal) => {
  if (!newVal) {
    stopVoter()
  }
})
</script>

<style scoped>
.voter-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.8);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.voter-modal {
  background-color: var(--modal-bg);
  border-radius: 8px;
  width: 90%;
  max-width: 800px;
  max-height: 90vh;
  overflow-y: auto;
  color: var(--text-color);
}

.voter-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid var(--border-color);
}

.voter-modal-header h2 {
  margin: 0;
  color: var(--text-color);
}

.close-button {
  background: none;
  border: none;
  color: var(--text-color);
  font-size: 24px;
  cursor: pointer;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-button:hover {
  background-color: var(--border-color);
  border-radius: 4px;
}

.voter-modal-content {
  padding: 20px;
}

.voter-input-section {
  margin-bottom: 20px;
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.voter-input-section label {
  font-weight: bold;
  color: var(--text-color);
}

.voter-input-section input {
  flex: 1;
  min-width: 200px;
  padding: 8px 12px;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background-color: var(--container-bg);
  color: var(--text-color);
}

.voter-input-section button {
  padding: 8px 16px;
  background-color: var(--button-bg);
  color: var(--text-color);
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.voter-input-section button:hover {
  background-color: var(--button-hover);
}

.voter-input-section button:disabled {
  background-color: var(--border-color);
  cursor: not-allowed;
}

.voter-containers {
  margin-bottom: 20px;
}

.voter-container {
  margin-bottom: 20px;
  padding: 15px;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background-color: var(--container-bg);
}

.voter-content {
  margin-bottom: 10px;
}

.voter-spinner {
  text-align: center;
}

.spinner-text {
  font-family: monospace;
  font-size: 18px;
  color: var(--link-color);
}

.voter-info-container {
  background-color: var(--container-bg);
  padding: 15px;
  border-radius: 4px;
  border: 1px solid var(--border-color);
}

.voter-description {
  margin-bottom: 15px;
  color: var(--text-color);
  line-height: 1.5;
}

.voter-legend {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.legend-item {
  padding: 5px 10px;
  border-radius: 4px;
  font-size: 14px;
}

.legend-voting {
  background-color: var(--link-color);
  color: var(--background-color);
}

.legend-voted {
  background-color: var(--success-color);
  color: var(--background-color);
}

.legend-mix {
  background-color: var(--warning-color);
  color: var(--background-color);
}

.error-message {
  color: var(--error-color);
  padding: 10px;
  background-color: var(--container-bg);
  border: 1px solid var(--error-color);
  border-radius: 4px;
}

/* Voter table styles */
:deep(.rtcm) {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
}

:deep(.rtcm th) {
  background-color: var(--table-header-bg);
  color: var(--text-color);
  padding: 8px;
  text-align: left;
  border: 1px solid var(--border-color);
}

:deep(.rtcm td) {
  padding: 8px;
  border: 1px solid var(--border-color);
  background-color: var(--container-bg);
}

:deep(.rtcm a) {
  color: var(--link-color);
  text-decoration: none;
}

:deep(.rtcm a:hover) {
  text-decoration: underline;
}

:deep(.barbox_a) {
  width: 300px;
  height: 20px;
  border: 1px solid #555;
  background-color: #1a1a1a;
  position: relative;
}

:deep(.bar) {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: bold;
  min-width: 20px;
}

:deep(.voter-no-clients) {
  color: #999;
  font-style: italic;
}

:deep(.voter-empty-bar) {
  background-color: #1a1a1a;
  height: 20px;
  border: 1px solid #555;
}

:deep(.text) {
  display: flex;
  align-items: center;
}
</style>
