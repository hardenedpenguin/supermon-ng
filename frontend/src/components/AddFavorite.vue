<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h2>Add Favorite</h2>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>

      <div class="modal-body">
        <div v-if="loading" class="loading">
          <p>Loading node information...</p>
        </div>

        <div v-else-if="error" class="error-message">
          {{ error }}
        </div>

        <div v-else-if="nodeInfo" class="add-favorite-form">
          <div class="node-info">
            <h3>Node Information</h3>
            <p><strong>Node:</strong> {{ nodeInfo.node }}</p>
            <p><strong>Callsign:</strong> {{ nodeInfo.callsign }}</p>
            <p><strong>Description:</strong> {{ nodeInfo.description }}</p>
            <p><strong>Location:</strong> {{ nodeInfo.location }}</p>
          </div>

          <div class="form-group">
            <label for="custom-label">Custom Label (optional):</label>
            <input
              id="custom-label"
              v-model="customLabel"
              type="text"
              placeholder="Enter custom label or leave blank for default"
              class="form-input"
            />
          </div>

          <div class="form-group">
            <label class="checkbox-label">
              <input
                v-model="addToGeneral"
                type="checkbox"
                class="checkbox-input"
              />
              Add to General Favorites (works with any node)
            </label>
            <p class="help-text">
              If checked, this favorite will be added to the general section and will prompt for a node number when executed.
              If unchecked, it will be added to a node-specific section.
            </p>
          </div>

          <div class="button-group">
            <button
              @click="addFavorite"
              :disabled="saving"
              class="add-button"
            >
              {{ saving ? 'Adding...' : 'Add to Favorites' }}
            </button>
            <button @click="closeModal" class="cancel-button">
              Cancel
            </button>
          </div>
        </div>

        <div v-else class="no-node-info">
          <p>No node information available.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  isVisible: {
    type: Boolean,
    default: false
  },
  nodeNumber: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:isVisible', 'favorite-added'])

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const nodeInfo = ref(null)
const customLabel = ref('')
const addToGeneral = ref(true)

// Watch for changes in nodeNumber and load node info
watch(() => props.nodeNumber, async (newNode) => {
  if (newNode && props.isVisible) {
    await loadNodeInfo(newNode)
  }
})

// Watch for modal visibility
watch(() => props.isVisible, async (visible) => {
  if (visible && props.nodeNumber) {
    await loadNodeInfo(props.nodeNumber)
  } else if (!visible) {
    resetForm()
  }
})

const loadNodeInfo = async (node) => {
  if (!node) return
  
  loading.value = true
  error.value = ''
  
  try {
    // For now, we'll use a mock response since we don't have a direct API
    // In a real implementation, you'd call an API to get node info
    const response = await axios.get(`/api/config/node-info?node=${node}`, {
      withCredentials: true
    })
    
    if (response.data.success) {
      nodeInfo.value = response.data.nodeInfo
      // Set default label
      customLabel.value = `${nodeInfo.value.callsign} ${nodeInfo.value.description} ${node}`
    } else {
      error.value = response.data.message || 'Failed to load node information'
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load node information'
  } finally {
    loading.value = false
  }
}

const addFavorite = async () => {
  if (!props.nodeNumber) return
  
  saving.value = true
  error.value = ''
  
  try {
    const response = await axios.post('/api/config/favorites/add', {
      node: props.nodeNumber,
      custom_label: customLabel.value,
      add_to_general: addToGeneral.value ? '1' : '0'
    }, {
      withCredentials: true
    })
    
    if (response.data.success) {
      emit('favorite-added', {
        success: true,
        message: response.data.message,
        node: response.data.node,
        label: response.data.label
      })
      closeModal()
    } else {
      error.value = response.data.message || 'Failed to add favorite'
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to add favorite'
  } finally {
    saving.value = false
  }
}

const closeModal = () => {
  emit('update:isVisible', false)
}

const resetForm = () => {
  loading.value = false
  saving.value = false
  error.value = ''
  nodeInfo.value = null
  customLabel.value = ''
  addToGeneral.value = true
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background: #2d3748;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
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
  border-bottom: 1px solid #4a5568;
  background: #1a202c;
  border-radius: 8px 8px 0 0;
}

.modal-header h2 {
  margin: 0;
  color: #e2e8f0;
  font-size: 1.5rem;
}

.close-button {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #a0aec0;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  transition: all 0.2s;
}

.close-button:hover {
  background-color: #4a5568;
  color: #e2e8f0;
}

.modal-body {
  padding: 20px;
}

.loading {
  text-align: center;
  color: #a0aec0;
}

.error-message {
  background: #742a2a;
  color: #feb2b2;
  padding: 15px;
  border-radius: 4px;
  border: 1px solid #c53030;
  text-align: center;
}

.node-info {
  background: #2c5282;
  padding: 15px;
  border-radius: 4px;
  border-left: 4px solid #63b3ed;
  margin-bottom: 20px;
}

.node-info h3 {
  margin: 0 0 10px 0;
  color: #bee3f8;
}

.node-info p {
  margin: 5px 0;
  color: #bee3f8;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #e2e8f0;
}

.form-input {
  width: 100%;
  padding: 12px;
  border: 2px solid #4a5568;
  border-radius: 4px;
  font-size: 14px;
  background: #1a202c;
  color: #e2e8f0;
  transition: border-color 0.2s;
  box-sizing: border-box;
}

.form-input:focus {
  outline: none;
  border-color: #63b3ed;
}

.checkbox-label {
  display: flex;
  align-items: center;
  cursor: pointer;
  font-weight: normal;
}

.checkbox-input {
  margin-right: 10px;
  width: 18px;
  height: 18px;
}

.help-text {
  margin-top: 8px;
  font-size: 0.875rem;
  color: #a0aec0;
  font-style: italic;
}

.button-group {
  display: flex;
  gap: 12px;
  justify-content: center;
  margin-top: 20px;
}

.add-button, .cancel-button {
  padding: 12px 24px;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  min-width: 120px;
}

.add-button {
  background: #3182ce;
  color: white;
}

.add-button:hover:not(:disabled) {
  background: #2c5282;
}

.add-button:disabled {
  background: #4a5568;
  cursor: not-allowed;
}

.cancel-button {
  background: #4a5568;
  color: white;
}

.cancel-button:hover {
  background: #2d3748;
}

.no-node-info {
  text-align: center;
  color: #a0aec0;
}
</style>
