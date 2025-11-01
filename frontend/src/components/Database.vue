<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>Database Contents - Node {{ localnode }}</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>

      <div class="modal-body">
        <!-- ASTDB Generation Section -->
        <div class="astdb-generation-section">
          <h4>Generate ASTDB</h4>
          <p>Generate or update the AllStar database (astdb.txt) file.</p>
          
          <!-- Database Status -->
          <div v-if="databaseStatus" class="database-status">
            <div class="status-item">
              <strong>File Status:</strong> 
              <span :class="databaseStatus.file_exists ? 'status-ok' : 'status-error'">
                {{ databaseStatus.file_exists ? 'Exists' : 'Missing' }}
              </span>
            </div>
            <div v-if="databaseStatus.file_exists" class="status-item">
              <strong>File Size:</strong> {{ formatFileSize(databaseStatus.file_size) }}
            </div>
            <div v-if="databaseStatus.last_modified" class="status-item">
              <strong>Last Modified:</strong> {{ formatTimestamp(databaseStatus.last_modified * 1000) }}
            </div>
            <div v-if="databaseStatus.last_generation" class="status-item">
              <strong>Last Generated:</strong> {{ formatTimestamp(databaseStatus.last_generation * 1000) }}
            </div>
            <div v-if="databaseStatus.next_auto_update" class="status-item">
              <strong>Next Auto Update:</strong> {{ formatTimestamp(databaseStatus.next_auto_update * 1000) }}
            </div>
            <div class="status-item">
              <strong>Auto Update:</strong> 
              <span :class="databaseStatus.auto_update_enabled ? 'status-ok' : 'status-warning'">
                {{ databaseStatus.auto_update_enabled ? 'Enabled' : 'Disabled' }}
              </span>
              <span v-if="databaseStatus.auto_update_enabled">
                (every {{ databaseStatus.auto_update_interval_hours }} hours)
              </span>
            </div>
            <div class="status-item">
              <strong>Private Nodes:</strong> {{ databaseStatus.private_nodes_count }}
            </div>
          </div>
          
          <div class="generation-controls">
            <label class="checkbox-label">
              <input 
                type="checkbox" 
                v-model="strictlyPrivate"
              />
              Strictly Private (only local nodes)
            </label>
            
            <button 
              @click="generateAstdb" 
              :disabled="generating"
              class="generate-btn"
            >
              {{ generating ? 'Generating...' : 'Generate ASTDB' }}
            </button>
          </div>
          
          <div v-if="generationMessage" class="generation-message" :class="generationSuccess ? 'success' : 'error'">
            {{ generationMessage }}
          </div>
        </div>

        <hr class="section-divider">

        <!-- Loading State -->
        <div v-if="loading" class="loading">
          <div class="spinner"></div>
          <p>Retrieving database contents...</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="error-message">
          <p>{{ error }}</p>
        </div>

        <!-- Database Contents Display -->
        <div v-if="databaseData" class="database-display">
          <div class="database-header">
            <h4>Database Entries</h4>
            <span class="timestamp">{{ formatTimestamp(databaseData.timestamp) }}</span>
          </div>
          
          <!-- Database Table -->
          <div v-if="databaseData.entries && databaseData.entries.length > 0" class="database-table-container">
            <table class="database-table">
              <thead>
                <tr>
                  <th>Key</th>
                  <th>Value</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="entry in databaseData.entries" :key="entry.key">
                  <td class="db-key">{{ entry.key }}</td>
                  <td class="db-value">{{ entry.value }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <!-- No Data Message -->
          <div v-else class="no-data-message">
            <p>No database entries found or database is empty.</p>
          </div>
          
          <!-- Raw Output Toggle -->
          <div class="raw-output-section">
            <button 
              class="toggle-raw-btn" 
              @click="showRawOutput = !showRawOutput"
            >
              {{ showRawOutput ? 'Hide' : 'Show' }} Raw Output
            </button>
            
            <div v-if="showRawOutput && databaseData.raw_output" class="raw-output">
              <h5>Raw AMI Output:</h5>
              <pre class="raw-content">{{ databaseData.raw_output }}</pre>
            </div>
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
    type: String,
    required: true
  }
})

const emit = defineEmits(['update:isVisible'])

// Reactive state
const loading = ref(false)
const error = ref('')
const databaseData = ref(null)
const showRawOutput = ref(false)
const databaseStatus = ref(null)

// ASTDB Generation state
const generating = ref(false)
const strictlyPrivate = ref(false)
const generationMessage = ref('')
const generationSuccess = ref(false)

// Watch for modal visibility changes
watch(() => props.isVisible, (newVal) => {
  if (newVal) {
    loadDatabase()
    loadDatabaseStatus()
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
  databaseData.value = null
  showRawOutput.value = false
  databaseStatus.value = null
}

const loadDatabase = async () => {
  loading.value = true
  error.value = ''
  databaseData.value = null

  // Validate localnode prop
  if (!props.localnode || props.localnode.trim() === '') {
    error.value = 'Local node number is required'
    loading.value = false
    return
  }

  try {
    const response = await api.post('/nodes/database', {
      localnode: props.localnode.trim()
    })

    if (response.data.success) {
      databaseData.value = response.data.data
    } else {
      error.value = response.data.message || 'Failed to retrieve database contents'
    }
  } catch (err) {
    console.error('Database error:', err)
    error.value = err.response?.data?.message || 'Failed to retrieve database contents'
  } finally {
    loading.value = false
  }
}

const loadDatabaseStatus = async () => {
  try {
    const response = await api.get('/database/status')
    if (response.data.success) {
      databaseStatus.value = response.data.data
    }
  } catch (err) {
    console.error('Failed to load database status:', err)
  }
}

const formatTimestamp = (timestamp) => {
  if (!timestamp) return ''
  return new Date(timestamp).toLocaleString()
}

const generateAstdb = async () => {
  generating.value = true
  generationMessage.value = ''
  generationSuccess.value = false

  try {
    const response = await api.post('/database/generate', {
      strictly_private: strictlyPrivate.value
    })

    if (response.data.success) {
      generationSuccess.value = true
      generationMessage.value = response.data.message || 'ASTDB generated successfully!'
      loadDatabaseStatus() // Refresh status after generation
    } else {
      generationSuccess.value = false
      generationMessage.value = response.data.message || 'Failed to generate ASTDB'
    }
  } catch (err) {
    console.error('ASTDB generation error:', err)
    generationSuccess.value = false
    generationMessage.value = err.response?.data?.message || 'Failed to generate ASTDB'
  } finally {
    generating.value = false
  }
}

const formatFileSize = (bytes) => {
  if (bytes === null || bytes === undefined) return 'N/A'
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
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
  background-color: var(--modal-bg);
  border-radius: 8px;
  width: 90%;
  max-width: 1000px;
  max-height: 85vh;
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
  background-color: var(--card-bg);
}

.modal-header h3 {
  margin: 0;
  color: var(--text-color);
  font-family: 'Courier New', Consolas, monospace;
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
  color: var(--primary-color);
}

.astdb-generation-section {
  padding: 1rem;
  background-color: var(--card-bg);
  border-radius: 4px;
  margin-bottom: 1rem;
}

.astdb-generation-section h4 {
  color: var(--text-color);
  margin: 0 0 0.5rem 0;
  font-family: 'Courier New', Consolas, monospace;
}

.astdb-generation-section p {
  color: var(--text-color);
  margin: 0 0 1rem 0;
  font-size: 0.9rem;
}

.generation-controls {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-color);
  font-size: 0.9rem;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  margin: 0;
}

.generate-btn {
  background-color: var(--success-color);
  color: var(--background-color);
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
  font-family: 'Courier New', Consolas, monospace;
  font-weight: bold;
  transition: background-color 0.2s;
}

.generate-btn:hover:not(:disabled) {
  background-color: var(--success-color);
  opacity: 0.8;
}

.generate-btn:disabled {
  background-color: var(--border-color);
  cursor: not-allowed;
}

.generation-message {
  padding: 0.5rem;
  border-radius: 4px;
  font-family: 'Courier New', Consolas, monospace;
  font-size: 0.9rem;
}

.generation-message.success {
  background-color: var(--success-color);
  color: var(--background-color);
  border: 1px solid var(--success-color);
}

.generation-message.error {
  background-color: #440000;
  color: #ff0000;
  border: 1px solid #ff0000;
}

.section-divider {
  border: none;
  border-top: 1px solid #333333;
  margin: 1rem 0;
}

.modal-body {
  padding: 1.5rem;
  max-height: calc(85vh - 120px);
  overflow-y: auto;
  background-color: #000000;
}

.loading {
  text-align: center;
  padding: 2rem;
  color: #00ff00;
}

.spinner {
  border: 3px solid #333333;
  border-top: 3px solid #00ff00;
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
  background-color: #330000;
  color: #ff6666;
  padding: 1rem;
  border-radius: 4px;
  margin: 1rem 0;
  border: 1px solid #ff3333;
}

.database-display {
  margin-top: 1rem;
}

.database-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid #333333;
}

.database-header h4 {
  margin: 0;
  color: #00ff00;
  font-family: 'Courier New', Consolas, monospace;
}

.timestamp {
  font-size: 0.875rem;
  color: #888888;
  font-family: 'Courier New', Consolas, monospace;
}

.database-table-container {
  margin-bottom: 1.5rem;
}

.database-table {
  width: 100%;
  border-collapse: collapse;
  font-family: 'Courier New', Consolas, monospace;
  font-size: 0.9em;
  background-color: #000000;
}

.database-table th,
.database-table td {
  border: 1px solid #333333;
  padding: 8px 12px;
  text-align: left;
  vertical-align: top;
}

.database-table th {
  background-color: #111111;
  color: #00ff00;
  border-bottom: 1px solid #000000;
  font-weight: bold;
  font-size: 0.95em;
}

.database-table tbody tr {
  background-color: #000000;
  color: #00ff00;
}

.database-table tbody tr:hover {
  background-color: #111111;
  color: #ffffff;
}

.db-key {
  font-weight: bold;
  color: #00ff00;
  word-break: break-all;
}

.db-value {
  color: #00ff00;
  word-break: break-word;
  max-width: 400px;
}

.no-data-message {
  text-align: center;
  padding: 2rem;
  color: #888888;
  font-style: italic;
}

.raw-output-section {
  margin-top: 1.5rem;
  border-top: 1px solid #333333;
  padding-top: 1rem;
}

.toggle-raw-btn {
  background-color: #111111;
  color: #00ff00;
  border: 1px solid #333333;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
  font-family: 'Courier New', Consolas, monospace;
  font-size: 0.9em;
}

.toggle-raw-btn:hover {
  background-color: #333333;
  color: #ffffff;
}

.raw-output {
  margin-top: 1rem;
}

.raw-output h5 {
  color: #00ff00;
  margin: 0 0 0.5rem 0;
  font-family: 'Courier New', Consolas, monospace;
}

.raw-content {
  background-color: #111111;
  color: #00ff00;
  padding: 1rem;
  border-radius: 4px;
  font-family: 'Courier New', Consolas, monospace;
  font-size: 12px;
  line-height: 1.3;
  white-space: pre-wrap;
  word-wrap: break-word;
  max-height: 300px;
  overflow-y: auto;
  border: 1px solid #333333;
}

.database-status {
  background-color: #111111;
  border: 1px solid #333333;
  border-radius: 4px;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
  font-family: 'Courier New', Consolas, monospace;
  font-size: 0.9rem;
  color: #cccccc;
}

.status-item {
  margin-bottom: 0.25rem;
}

.status-item strong {
  color: #00ff00;
}

.status-ok {
  color: #00ff00;
  font-weight: bold;
}

.status-error {
  color: #ff0000;
  font-weight: bold;
}

.status-warning {
  color: #ffff00;
  font-weight: bold;
}
</style>
