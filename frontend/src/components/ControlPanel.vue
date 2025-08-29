<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h2>Control Panel</h2>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>
      
      <div class="modal-body">
        <div v-if="loading" class="loading">
          <div class="spinner"></div>
          <p>Loading control panel commands...</p>
        </div>
        
        <div v-else-if="error" class="error-message">
          <p>{{ error }}</p>
        </div>
        
        <div v-else-if="!hasCommands" class="no-commands">
          <h3>No Control Panel Commands Configured</h3>
          <p>To use the Control Panel, you need to configure commands in:</p>
          <code>user_files/controlpanel.ini</code>
          <p>Edit the file and uncomment the commands you want to use by removing the semicolon (;) from the beginning of the lines.</p>
        </div>
        
        <div v-else class="control-panel">
          <div class="node-info">
            <p><strong>Sending Command to node:</strong> {{ localNode }}</p>
          </div>
          
          <div class="command-section">
            <label for="commandSelect">Control command (select one):</label>
            <select 
              id="commandSelect" 
              v-model="selectedCommand" 
              class="command-select"
              :disabled="executing"
            >
              <option value="">-- Select a command --</option>
              <option 
                v-for="(label, index) in commands.labels" 
                :key="index"
                :value="commands.cmds[index]"
              >
                {{ label }}
              </option>
            </select>
          </div>
          
          <div class="execute-section">
            <button 
              @click="executeCommand" 
              :disabled="!selectedCommand || executing"
              class="execute-button"
            >
              <span v-if="executing">Executing...</span>
              <span v-else>Execute</span>
            </button>
          </div>
          
          <div v-if="result" class="result-section">
            <h4>Command Result:</h4>
            <div class="result-content" :class="{ 'error': result.error }">
              <pre>{{ result.message }}</pre>
            </div>
          </div>
        </div>
      </div>
      
      <div class="modal-footer">
        <button @click="closeModal" class="close-window-button">Close Window</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { api } from '@/utils/api'

// Props
const props = defineProps({
  isVisible: {
    type: Boolean,
    default: false
  },
  localNode: {
    type: String,
    required: true
  }
})

// Emits
const emit = defineEmits(['close'])

// Reactive state
const loading = ref(false)
const error = ref('')
const commands = ref({ labels: [], cmds: [] })
const selectedCommand = ref('')
const executing = ref(false)
const result = ref(null)

// Computed
const hasCommands = computed(() => {
  return commands.value.labels && commands.value.labels.length > 0
})

// Methods
const closeModal = () => {
  emit('close')
  resetState()
}

const resetState = () => {
  loading.value = false
  error.value = ''
  commands.value = { labels: [], cmds: [] }
  selectedCommand.value = ''
  executing.value = false
  result.value = null
}

const loadCommands = async () => {
  if (!props.localNode) return
  
  loading.value = true
  error.value = ''
  
  try {
    const response = await api.get(`/config/controlpanel?node=${props.localNode}`)
    
    if (response.data.success) {
      commands.value = response.data.data
    } else {
      error.value = response.data.message || 'Failed to load control panel commands'
    }
  } catch (err) {
    console.error('Error loading control panel commands:', err)
    error.value = 'Failed to load control panel commands. Please try again.'
  } finally {
    loading.value = false
  }
}

const executeCommand = async () => {
  if (!selectedCommand.value || !props.localNode) return
  
  executing.value = true
  result.value = null
  
  try {
    const response = await api.post('/config/controlpanel/execute', {
      node: props.localNode,
      command: selectedCommand.value
    })
    
    if (response.data.success) {
      result.value = {
        error: false,
        message: response.data.data.result || 'Command executed successfully'
      }
    } else {
      result.value = {
        error: true,
        message: response.data.message || 'Command execution failed'
      }
    }
  } catch (err) {
    console.error('Error executing command:', err)
    result.value = {
      error: true,
      message: 'Failed to execute command. Please try again.'
    }
  } finally {
    executing.value = false
  }
}

// Watch for visibility changes
watch(() => props.isVisible, (newValue) => {
  if (newValue && props.localNode) {
    loadCommands()
  }
})

// Watch for localNode changes
watch(() => props.localNode, (newValue) => {
  if (props.isVisible && newValue) {
    loadCommands()
  }
})
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
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
  max-width: 600px;
  width: 90%;
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

.modal-header h2 {
  margin: 0;
  color: #333;
  font-size: 1.5rem;
}

.close-button {
  background: none;
  border: none;
  font-size: 24px;
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

.loading {
  text-align: center;
  padding: 40px 20px;
}

.spinner {
  border: 3px solid #f3f3f3;
  border-top: 3px solid #007bff;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  animation: spin 1s linear infinite;
  margin: 0 auto 20px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error-message {
  background: #f8d7da;
  color: #721c24;
  padding: 15px;
  border-radius: 4px;
  border: 1px solid #f5c6cb;
  text-align: center;
}

.no-commands {
  text-align: center;
  padding: 20px;
}

.no-commands h3 {
  color: #666;
  margin-bottom: 15px;
}

.no-commands code {
  background: #f8f9fa;
  padding: 8px 12px;
  border-radius: 4px;
  font-family: monospace;
  display: inline-block;
  margin: 10px 0;
}

.control-panel {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.node-info {
  background: #e3f2fd;
  padding: 15px;
  border-radius: 4px;
  border-left: 4px solid #2196f3;
}

.node-info p {
  margin: 0;
  color: #1976d2;
}

.command-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.command-section label {
  font-weight: 600;
  color: #333;
}

.command-select {
  padding: 12px;
  border: 2px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  background: white;
  transition: border-color 0.2s;
}

.command-select:focus {
  outline: none;
  border-color: #007bff;
}

.command-select:disabled {
  background: #f5f5f5;
  cursor: not-allowed;
}

.execute-section {
  display: flex;
  justify-content: center;
}

.execute-button {
  background: #007bff;
  color: white;
  border: none;
  padding: 12px 30px;
  border-radius: 4px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s;
  min-width: 120px;
}

.execute-button:hover:not(:disabled) {
  background: #0056b3;
}

.execute-button:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.result-section {
  margin-top: 20px;
  padding: 15px;
  border-radius: 4px;
  background: #f8f9fa;
  border: 1px solid #e9ecef;
}

.result-section h4 {
  margin: 0 0 10px 0;
  color: #333;
}

.result-content {
  background: white;
  padding: 12px;
  border-radius: 4px;
  border: 1px solid #dee2e6;
}

.result-content.error {
  background: #fff5f5;
  border-color: #feb2b2;
}

.result-content pre {
  margin: 0;
  white-space: pre-wrap;
  word-wrap: break-word;
  font-family: monospace;
  font-size: 13px;
  line-height: 1.4;
}

.modal-footer {
  padding: 20px;
  border-top: 1px solid #e0e0e0;
  text-align: center;
  background: #f8f9fa;
  border-radius: 0 0 8px 8px;
}

.close-window-button {
  background: #6c757d;
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 4px;
  font-size: 14px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.close-window-button:hover {
  background: #5a6268;
}
</style>
