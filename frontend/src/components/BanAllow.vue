<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>Node Access Control Lists</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>

      <div class="modal-body">
        <!-- Node Selection -->
        <div class="node-selection">
          <label for="localnode">Local Node:</label>
          <select id="localnode" v-model="selectedLocalNode" @change="loadLists" :disabled="loading">
            <option value="">Select a node...</option>
            <option v-for="node in displayedNodes" :key="node.id" :value="node.id">
              {{ node.id }} => {{ node.info || 'Node not in database' }}
            </option>
          </select>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading">
          <div class="spinner"></div>
          <p>{{ loadingMessage }}</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="error-message">
          <p>{{ error }}</p>
        </div>

        <!-- Success Message -->
        <div v-if="successMessage" class="success-message">
          <p>{{ successMessage }}</p>
        </div>

        <!-- Main Content -->
        <div v-if="!loading && selectedLocalNode && !error" class="ban-allow-content">
          <!-- Form Section -->
          <div class="form-section">
            <h4>Add/Remove Node</h4>
            <form @submit.prevent="executeAction" class="ban-allow-form">
              <div class="form-row">
                <div class="form-group">
                  <label for="node">Node Number:</label>
                  <input 
                    id="node" 
                    v-model="formData.node" 
                    type="text" 
                    maxlength="7" 
                    pattern="\d+" 
                    required
                    placeholder="Enter node number"
                  >
                </div>
                <div class="form-group">
                  <label for="comment">Comment:</label>
                  <input 
                    id="comment" 
                    v-model="formData.comment" 
                    type="text" 
                    maxlength="50"
                    placeholder="Optional comment"
                  >
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label>List Type:</label>
                  <div class="radio-group">
                    <label class="radio-label">
                      <input type="radio" v-model="formData.listtype" value="allowlist" checked>
                      Allow List
                    </label>
                    <label class="radio-label">
                      <input type="radio" v-model="formData.listtype" value="denylist">
                      Deny List
                    </label>
                  </div>
                </div>
                <div class="form-group">
                  <label>Action:</label>
                  <div class="radio-group">
                    <label class="radio-label">
                      <input type="radio" v-model="formData.deleteadd" value="add" checked>
                      Add
                    </label>
                    <label class="radio-label">
                      <input type="radio" v-model="formData.deleteadd" value="delete">
                      Delete
                    </label>
                  </div>
                </div>
              </div>

              <div class="form-actions">
                <button type="submit" class="execute-button" :disabled="executing">
                  {{ executing ? 'Executing...' : 'Execute' }}
                </button>
                <button type="button" @click="refreshLists" class="refresh-button" :disabled="loading">
                  Refresh Lists
                </button>
              </div>
            </form>
          </div>

          <!-- Lists Section -->
          <div class="lists-section">
            <div class="lists-container">
              <!-- Allow List -->
              <div class="list-container">
                <h4>Allow List</h4>
                <div class="list-content">
                  <div v-if="allowlist.entries && allowlist.entries.length > 0" class="list-table">
                    <table>
                      <thead>
                        <tr>
                          <th>Node</th>
                          <th>Comment</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="entry in allowlist.entries" :key="entry.node">
                          <td>{{ entry.node }}</td>
                          <td>{{ entry.comment }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div v-else class="empty-list">
                    <p>No nodes in allow list</p>
                  </div>
                </div>
              </div>

              <!-- Deny List -->
              <div class="list-container">
                <h4>Deny List</h4>
                <div class="list-content">
                  <div v-if="denylist.entries && denylist.entries.length > 0" class="list-table">
                    <table>
                      <thead>
                        <tr>
                          <th>Node</th>
                          <th>Comment</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="entry in denylist.entries" :key="entry.node">
                          <td>{{ entry.node }}</td>
                          <td>{{ entry.comment }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div v-else class="empty-list">
                    <p>No nodes in deny list</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { api } from '@/utils/api'

const props = defineProps({
  isVisible: {
    type: Boolean,
    default: false
  },
  availableNodes: {
    type: Array,
    default: () => []
  },
  defaultNode: {
    type: String,
    default: ''
  },
  displayedNodes: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:isVisible'])

// Reactive state
const loading = ref(false)
const executing = ref(false)
const error = ref('')
const successMessage = ref('')
const loadingMessage = ref('Loading...')
const selectedLocalNode = ref('')
const allowlist = ref({ entries: [] })
const denylist = ref({ entries: [] })

// Form data
const formData = ref({
  node: '',
  comment: '',
  listtype: 'allowlist',
  deleteadd: 'add'
})

// Watch for modal visibility changes
watch(() => props.isVisible, (newVal) => {
  if (newVal) {
    resetState()
    // Set the default node if provided
    let targetNode = props.defaultNode
    
    // Handle group mode - if defaultNode is comma-separated, use the first node
    if (props.defaultNode && props.defaultNode.includes(',')) {
      targetNode = props.defaultNode.split(',')[0].trim()
    }
    
    if (targetNode && props.displayedNodes.some(node => node.id.toString() === targetNode)) {
      selectedLocalNode.value = targetNode
      // Load the lists for the selected node
      loadLists()
    } else {
      // Try to set the first available node if no default is provided
      if (props.displayedNodes && props.displayedNodes.length > 0) {
        const firstNode = props.displayedNodes[0]
        selectedLocalNode.value = firstNode.id
        loadLists()
      }
    }
  }
})

// Computed properties
const hasValidNode = computed(() => {
  return selectedLocalNode.value && props.displayedNodes.some(node => node.id.toString() === selectedLocalNode.value)
})

// Methods
const closeModal = () => {
  emit('update:isVisible', false)
  resetState()
}

const resetState = () => {
  loading.value = false
  executing.value = false
  error.value = ''
  successMessage.value = ''
  // Don't reset selectedLocalNode here - let the watch handle it
  allowlist.value = { entries: [] }
  denylist.value = { entries: [] }
  formData.value = {
    node: '',
    comment: '',
    listtype: 'allowlist',
    deleteadd: 'add'
  }
}

const loadLists = async () => {
  if (!hasValidNode.value) {
    return
  }

  loading.value = true
  loadingMessage.value = 'Loading access control lists...'
  error.value = ''
  successMessage.value = ''

  try {
    const response = await api.post('/nodes/banallow', { localnode: selectedLocalNode.value })

    if (response.data.success) {
      allowlist.value = response.data.data.allowlist
      denylist.value = response.data.data.denylist
    } else {
      error.value = response.data.message || 'Failed to load access control lists'
    }
  } catch (err) {
    console.error('Ban/Allow error:', err)
    error.value = err.response?.data?.message || 'Failed to load access control lists'
  } finally {
    loading.value = false
  }
}

const executeAction = async () => {
  if (!hasValidNode.value) {
    error.value = 'Please select a valid local node'
    return
  }

  executing.value = true
  error.value = ''
  successMessage.value = ''

  try {
    const response = await api.post('/nodes/banallow/action', {
      localnode: selectedLocalNode.value,
      ...formData.value
    })

    if (response.data.success) {
      successMessage.value = response.data.message
      allowlist.value = response.data.data.allowlist
      denylist.value = response.data.data.denylist
      
      // Clear form
      formData.value.node = ''
      formData.value.comment = ''
    } else {
      error.value = response.data.message || 'Failed to execute action'
    }
  } catch (err) {
    console.error('Ban/Allow action error:', err)
    error.value = err.response?.data?.message || 'Failed to execute action'
  } finally {
    executing.value = false
  }
}

const refreshLists = () => {
  loadLists()
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
  max-width: 1200px;
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

.node-selection {
  margin-bottom: 1.5rem;
  padding: 1rem;
  background-color: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: 4px;
}

.node-selection label {
  display: block;
  margin-bottom: 0.5rem;
  color: var(--text-color);
  font-weight: bold;
}

.node-selection select {
  width: 200px;
  padding: 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background-color: var(--background-color);
  color: var(--text-color);
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

.success-message {
  background-color: var(--success-bg);
  color: var(--success-color);
  padding: 1rem;
  border-radius: 4px;
  margin: 1rem 0;
}

.ban-allow-content {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.form-section {
  background-color: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: 4px;
  padding: 1.5rem;
}

.form-section h4 {
  margin: 0 0 1rem 0;
  color: var(--text-color);
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 0.5rem;
}

.ban-allow-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  color: var(--text-color);
  font-weight: bold;
  font-size: 0.9rem;
}

.form-group input[type="text"] {
  padding: 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background-color: var(--background-color);
  color: var(--text-color);
}

.radio-group {
  display: flex;
  gap: 1rem;
}

.radio-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-color);
  cursor: pointer;
}

.radio-label input[type="radio"] {
  margin: 0;
}

.form-actions {
  display: flex;
  gap: 1rem;
  margin-top: 1rem;
}

.execute-button, .refresh-button {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 4px;
  font-size: 1rem;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

.execute-button {
  background-color: var(--primary-color);
  color: white;
}

.execute-button:hover:not(:disabled) {
  background-color: var(--link-color);
}

.execute-button:disabled {
  background-color: var(--secondary-bg);
  cursor: not-allowed;
}

.refresh-button {
  background-color: var(--secondary-bg);
  color: var(--text-color);
  border: 1px solid var(--border-color);
}

.refresh-button:hover:not(:disabled) {
  background-color: var(--border-color);
}

.lists-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.lists-container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.list-container {
  background-color: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: 4px;
  overflow: hidden;
}

.list-container h4 {
  margin: 0;
  padding: 1rem;
  background-color: var(--border-color);
  color: var(--text-color);
  border-bottom: 1px solid var(--border-color);
}

.list-content {
  padding: 1rem;
}

.list-table {
  overflow-x: auto;
}

.list-table table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.list-table th,
.list-table td {
  padding: 0.5rem;
  text-align: left;
  border-bottom: 1px solid var(--border-color);
}

.list-table th {
  background-color: var(--border-color);
  color: var(--text-color);
  border-bottom: 1px solid #000000;
  font-weight: bold;
}

.list-table td {
  color: var(--text-color);
}

.empty-list {
  text-align: center;
  padding: 2rem;
  color: var(--secondary-text);
  font-style: italic;
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .lists-container {
    grid-template-columns: 1fr;
  }
  
  .radio-group {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .form-actions {
    flex-direction: column;
  }
}
</style>
