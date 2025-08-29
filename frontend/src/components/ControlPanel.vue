<template>
  <div v-if="open" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <!-- Modal Header -->
      <div class="modal-header">
        <h3>Control Panel</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <!-- Loading State -->
        <div v-if="loading" class="loading-container">
          <div class="loading-spinner"></div>
          <p>Loading control panel configuration...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-container">
          <div class="error-icon">⚠️</div>
          <h4>Error</h4>
          <p>{{ error }}</p>
          <button class="btn btn-primary" @click="loadConfiguration">Retry</button>
        </div>

        <!-- Control Panel Content -->
        <div v-else class="control-panel-content">
          <!-- Node Selection -->
          <div class="form-group">
            <label for="nodeSelect">Target Node:</label>
            <input
              id="nodeSelect"
              v-model="selectedNode"
              type="text"
              class="form-control"
              placeholder="Enter node number (optional)"
            />
          </div>

          <!-- Available Commands -->
          <div class="commands-section">
            <h4>Available Commands</h4>
            <div class="commands-grid">
              <button
                v-for="command in availableCommands"
                :key="command.name"
                class="command-button"
                :class="{ 'executing': executingCommand === command.name }"
                :disabled="executingCommand !== null"
                @click="executeCommand(command.name)"
              >
                <div class="command-icon">{{ command.icon }}</div>
                <div class="command-info">
                  <div class="command-name">{{ command.name }}</div>
                  <div class="command-description">{{ command.description }}</div>
                </div>
              </button>
            </div>
          </div>

          <!-- Command Results -->
          <div v-if="commandResult" class="results-section">
            <h4>Command Results</h4>
            <div class="result-container">
              <div class="result-header">
                <span class="result-command">{{ commandResult.command }}</span>
                <span class="result-method">{{ commandResult.method }}</span>
              </div>
              <div class="result-content">
                <pre>{{ commandResult.result }}</pre>
              </div>
              <button class="btn btn-secondary" @click="clearResults">Clear Results</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <button class="btn btn-secondary" @click="closeModal">Close</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
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
const loading = ref(false)
const error = ref('')
const selectedNode = ref('')
const availableCommands = ref([])
const executingCommand = ref(null)
const commandResult = ref(null)

// Available commands configuration
const commandsConfig = [
  {
    name: 'rpt reload',
    description: 'Reload RPT configuration',
    icon: '🔄'
  },
  {
    name: 'iax2 reload',
    description: 'Reload IAX2 configuration',
    icon: '📞'
  },
  {
    name: 'extensions reload',
    description: 'Reload extensions configuration',
    icon: '📋'
  },
  {
    name: 'echolink dbdump',
    description: 'Dump EchoLink database',
    icon: '🗄️'
  },
  {
    name: 'astup',
    description: 'Start Asterisk service',
    icon: '🟢'
  },
  {
    name: 'astdn',
    description: 'Stop Asterisk service',
    icon: '🔴'
  }
]

// Methods
const closeModal = () => {
  emit('update:open', false)
  clearResults()
}

const loadConfiguration = async () => {
  loading.value = true
  error.value = ''
  
  try {
    const response = await axios.get('/api/config/controlpanel')
    
    if (response.data.success) {
      availableCommands.value = commandsConfig
    } else {
      error.value = response.data.message || 'Failed to load control panel configuration'
    }
  } catch (err) {
    console.error('Control panel configuration error:', err)
    error.value = err.response?.data?.message || 'Failed to load control panel configuration'
  } finally {
    loading.value = false
  }
}

const executeCommand = async (commandName) => {
  if (executingCommand.value) return
  
  executingCommand.value = commandName
  commandResult.value = null
  
  try {
    const response = await axios.post('/api/config/controlpanel/execute', {
      command: commandName,
      node: selectedNode.value || props.localNode || ''
    })
    
    if (response.data.success) {
      commandResult.value = response.data.result
    } else {
      error.value = response.data.message || 'Failed to execute command'
    }
  } catch (err) {
    console.error('Command execution error:', err)
    error.value = err.response?.data?.message || 'Failed to execute command'
  } finally {
    executingCommand.value = null
  }
}

const clearResults = () => {
  commandResult.value = null
}

// Watchers
watch(() => props.open, (newValue) => {
  if (newValue) {
    loadConfiguration()
    selectedNode.value = props.localNode || ''
  }
})

// Lifecycle
onMounted(() => {
  if (props.open) {
    loadConfiguration()
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
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  max-width: 800px;
  width: 90%;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 600;
}

.close-button {
  background: none;
  border: none;
  color: white;
  font-size: 1.5rem;
  cursor: pointer;
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
  background-color: rgba(255, 255, 255, 0.2);
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #f3f4f6;
  border-top: 4px solid #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error-container {
  text-align: center;
  padding: 40px;
}

.error-icon {
  font-size: 3rem;
  margin-bottom: 16px;
}

.error-container h4 {
  color: #dc2626;
  margin-bottom: 8px;
}

.error-container p {
  color: #6b7280;
  margin-bottom: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #374151;
}

.form-control {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 1rem;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.form-control:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.commands-section {
  margin-bottom: 24px;
}

.commands-section h4 {
  margin-bottom: 16px;
  color: #374151;
  font-weight: 600;
}

.commands-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
}

.command-button {
  display: flex;
  align-items: center;
  padding: 16px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: white;
  cursor: pointer;
  transition: all 0.2s;
  text-align: left;
}

.command-button:hover:not(:disabled) {
  border-color: #667eea;
  box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
  transform: translateY(-1px);
}

.command-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.command-button.executing {
  border-color: #f59e0b;
  background-color: #fef3c7;
}

.command-icon {
  font-size: 1.5rem;
  margin-right: 12px;
  width: 24px;
  text-align: center;
}

.command-info {
  flex: 1;
}

.command-name {
  font-weight: 600;
  color: #374151;
  margin-bottom: 4px;
}

.command-description {
  font-size: 0.875rem;
  color: #6b7280;
}

.results-section {
  margin-top: 24px;
  padding-top: 24px;
  border-top: 1px solid #e5e7eb;
}

.results-section h4 {
  margin-bottom: 16px;
  color: #374151;
  font-weight: 600;
}

.result-container {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 16px;
}

.result-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid #e5e7eb;
}

.result-command {
  font-weight: 600;
  color: #374151;
}

.result-method {
  background: #667eea;
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 500;
}

.result-content {
  margin-bottom: 16px;
}

.result-content pre {
  background: #1f2937;
  color: #f9fafb;
  padding: 12px;
  border-radius: 6px;
  font-size: 0.875rem;
  overflow-x: auto;
  white-space: pre-wrap;
  word-break: break-word;
  margin: 0;
}

.modal-footer {
  padding: 20px 24px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-secondary {
  background: #6b7280;
  color: white;
}

.btn-secondary:hover {
  background: #4b5563;
  transform: translateY(-1px);
}

@media (max-width: 768px) {
  .modal-content {
    width: 95%;
    margin: 20px;
  }
  
  .commands-grid {
    grid-template-columns: 1fr;
  }
  
  .modal-header,
  .modal-body,
  .modal-footer {
    padding: 16px;
  }
}
</style>
