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
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { api } from '@/utils/api'

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
  try {
    const response = await api.get(`/nodes/voter/status?node=${encodeURIComponent(node)}`)
    
    if (response.data.html) {
      const element = document.getElementById(`link_list_${node}`)
      if (element) {
        element.innerHTML = response.data.html
      }
    }
    
    if (response.data.spinner) {
      spinners.value[node] = response.data.spinner
    }
    
    // Continue polling
    setTimeout(() => pollVoterStatus(node), 1000)
    
  } catch (error) {
    console.error(`Error polling voter status for node ${node}:`, error)
    const element = document.getElementById(`link_list_${node}`)
    if (element) {
      element.innerHTML = `<div class='error-message'>Error receiving updates for node ${node}. The connection was lost.</div>`
    }
    spinners.value[node] = 'X'
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
  background-color: #1a1a1a;
  border-radius: 8px;
  width: 90%;
  max-width: 800px;
  max-height: 90vh;
  overflow-y: auto;
  color: #ffffff;
}

.voter-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #333;
}

.voter-modal-header h2 {
  margin: 0;
  color: #ffffff;
}

.close-button {
  background: none;
  border: none;
  color: #ffffff;
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
  background-color: #333;
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
  color: #ffffff;
}

.voter-input-section input {
  flex: 1;
  min-width: 200px;
  padding: 8px 12px;
  border: 1px solid #333;
  border-radius: 4px;
  background-color: #2a2a2a;
  color: #ffffff;
}

.voter-input-section button {
  padding: 8px 16px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.voter-input-section button:hover {
  background-color: #0056b3;
}

.voter-input-section button:disabled {
  background-color: #666;
  cursor: not-allowed;
}

.voter-containers {
  margin-bottom: 20px;
}

.voter-container {
  margin-bottom: 20px;
  padding: 15px;
  border: 1px solid #333;
  border-radius: 4px;
  background-color: #2a2a2a;
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
  color: #007bff;
}

.voter-info-container {
  background-color: #2a2a2a;
  padding: 15px;
  border-radius: 4px;
  border: 1px solid #333;
}

.voter-description {
  margin-bottom: 15px;
  color: #cccccc;
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
  background-color: #0099FF;
  color: white;
}

.legend-voted {
  background-color: #90EE90;
  color: black;
}

.legend-mix {
  background-color: #00FFFF;
  color: black;
}

.error-message {
  color: #ff6b6b;
  padding: 10px;
  background-color: #2a1a1a;
  border: 1px solid #ff6b6b;
  border-radius: 4px;
}

/* Voter table styles */
:deep(.rtcm) {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
}

:deep(.rtcm th) {
  background-color: #333;
  color: white;
  padding: 8px;
  text-align: left;
  border: 1px solid #555;
}

:deep(.rtcm td) {
  padding: 8px;
  border: 1px solid #555;
  background-color: #2a2a2a;
}

:deep(.rtcm a) {
  color: #007bff;
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
