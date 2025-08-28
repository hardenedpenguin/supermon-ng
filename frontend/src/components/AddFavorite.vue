<template>
  <div v-if="open" class="add-favorite-modal" @click="closeModal">
    <div class="add-favorite-content" @click.stop>
      <div class="add-favorite-header">
        <h2>{{ title }}</h2>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>
      
      <!-- Loading State -->
      <div v-if="loading" class="loading">
        Loading node information...
      </div>
      
      <!-- Error State -->
      <div v-else-if="error" class="error">
        {{ error }}
      </div>
      
      <!-- Success State -->
      <div v-else-if="result" class="success">
        {{ result.message }}
        
        <div v-if="result.success && result.file" class="file-info">
          <h3>File Updated</h3>
          <p><strong>File:</strong> {{ result.file }}</p>
          <p><strong>Label:</strong> {{ result.label }}</p>
        </div>
        
        <div class="button-group">
          <input v-if="result.success" type="button" class="submit-large" value="Refresh Parent Window" @click="refreshParent">
          <input type="button" class="submit-large" value="Close" @click="closeModal">
        </div>
      </div>
      
      <!-- Form State -->
      <div v-else class="add-favorite-form">
        <!-- Node Input Section -->
        <div v-if="!nodeInfo" class="node-input-section">
          <div class="form-group">
            <label for="node_input">Enter Node Number:</label>
            <input 
              type="text" 
              id="node_input" 
              v-model="nodeInput" 
              placeholder="e.g., 48752"
              @keyup.enter="loadNodeInfo"
            >
            <small>Enter the node number you want to add to favorites</small>
          </div>
          
          <div class="button-group">
            <input type="button" class="submit-large" value="Load Node Info" @click="loadNodeInfo" :disabled="!nodeInput || loading">
            <input type="button" class="submit-large" value="Cancel" @click="closeModal">
          </div>
        </div>

        <!-- Node Info and Form Section -->
        <div v-else>
        <div class="node-info">
          <h3>Node Information</h3>
          <p><strong>Node:</strong> {{ nodeInfo.node }}</p>
          <p><strong>Callsign:</strong> {{ nodeInfo.callsign }}</p>
          <p><strong>Description:</strong> {{ nodeInfo.description }}</p>
          <p><strong>Location:</strong> {{ nodeInfo.location }}</p>
        </div>

        <div class="node-info" style="margin-top: 15px;">
          <h3>Favorites File</h3>
          <p><strong>User:</strong> {{ currentUser }}</p>
          <p><strong>File:</strong> {{ fileName }}</p>
        </div>

        <div v-if="alreadyExists" class="warning">
          <strong>Warning:</strong> This node already exists in your favorites.
        </div>

        <form @submit.prevent="saveFavorite">
          <div class="form-group">
            <label for="custom_label">Custom Label (optional):</label>
            <input 
              type="text" 
              id="custom_label" 
              v-model="formData.custom_label" 
              :placeholder="defaultLabel"
            >
            <small>Leave blank to use the default label above</small>
          </div>

          <div class="form-group">
            <label>
              <input 
                type="checkbox" 
                v-model="formData.add_to_general" 
                value="1"
              >
              Add to General Favorites (available for all nodes)
            </label>
            <small>If unchecked, this favorite will only be available for the current node</small>
          </div>

          <div class="button-group">
            <input type="submit" class="submit-large" value="Add to Favorites" :disabled="saving">
            <input type="button" class="submit-large" value="Cancel" @click="closeModal">
          </div>
        </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, computed, nextTick } from 'vue'
import axios from 'axios'

interface Props {
  open: boolean
}

interface Emits {
  (e: 'update:open', value: boolean): void
  (e: 'favorite-added', result: any): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const result = ref<any>(null)
const nodeInfo = ref<any>(null)
const alreadyExists = ref(false)
const currentUser = ref('')
const fileName = ref('')
const nodeInput = ref('')

const formData = ref({
  custom_label: '',
  add_to_general: true
})

const title = computed(() => {
  if (result.value) {
    return result.value.success ? 'Favorite Added Successfully' : 'Error Adding Favorite'
  }
  if (nodeInfo.value) {
    return `Add Favorite - Node ${nodeInfo.value.node}`
  }
  return 'Add Favorite'
})

const defaultLabel = computed(() => {
  if (nodeInfo.value) {
    const parts = [nodeInfo.value.callsign]
    if (nodeInfo.value.description && nodeInfo.value.description.trim()) {
      parts.push(nodeInfo.value.description)
    }
    parts.push(nodeInfo.value.node)
    return parts.join(' ')
  }
  return ''
})

const closeModal = () => {
  emit('update:open', false)
  // Reset state
  error.value = ''
  result.value = null
  nodeInfo.value = null
  alreadyExists.value = false
  nodeInput.value = ''
  formData.value = {
    custom_label: '',
    add_to_general: true
  }
}

const refreshParent = () => {
  // Refresh the parent window
  window.location.reload()
  closeModal()
}

const loadNodeInfo = async () => {
  if (!nodeInput.value) {
    error.value = 'Please enter a node number'
    return
  }

  try {
    loading.value = true
    error.value = ''
    
    const response = await axios.get(`/api/config/node-info?node=${nodeInput.value}`, { 
      withCredentials: true 
    })
    
    if (response.data.success) {
      const data = response.data.data
      nodeInfo.value = data.nodeInfo
      alreadyExists.value = data.alreadyExists
      currentUser.value = data.currentUser || 'admin'
      fileName.value = data.fileName || `${currentUser.value}-favorites.ini`
      
      // Set default label after ensuring reactivity
      await nextTick()
      formData.value.custom_label = defaultLabel.value
    } else {
      error.value = response.data.message || 'Failed to load node information'
    }
  } catch (err: any) {
    console.error('Failed to load node info:', err)
    error.value = err.response?.data?.message || 'Failed to load node information'
  } finally {
    loading.value = false
  }
}

const saveFavorite = async () => {
  if (!nodeInfo.value?.node) {
    error.value = 'No node information available'
    return
  }

  try {
    saving.value = true
    error.value = ''
    
    const response = await axios.post('/api/config/add-favorite', {
      node: nodeInfo.value.node,
      custom_label: formData.value.custom_label,
      add_to_general: formData.value.add_to_general ? '1' : '0'
    }, { 
      withCredentials: true 
    })
    
    result.value = response.data
    
    if (response.data.success) {
      emit('favorite-added', response.data)
    }
  } catch (err: any) {
    console.error('Failed to add favorite:', err)
    result.value = {
      success: false,
      message: err.response?.data?.message || 'Failed to add favorite'
    }
  } finally {
    saving.value = false
  }
}

// Reset form when modal opens
watch(() => props.open, (newOpen) => {
  if (newOpen) {
    // Reset all state when modal opens
    error.value = ''
    result.value = null
    nodeInfo.value = null
    alreadyExists.value = false
    nodeInput.value = ''
    formData.value = {
      custom_label: '',
      add_to_general: true
    }
  }
})
</script>

<style scoped>
.add-favorite-modal {
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

.add-favorite-content {
  background-color: #1a1a1a;
  border: 1px solid #333;
  border-radius: 8px;
  padding: 20px;
  max-width: 600px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
}

.add-favorite-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  border-bottom: 1px solid #333;
  padding-bottom: 10px;
}

.add-favorite-header h2 {
  margin: 0;
  color: #fff;
  font-size: 1.5em;
}

.close-button {
  background: none;
  border: none;
  color: #fff;
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

.loading {
  text-align: center;
  color: #fff;
  padding: 20px;
}

.error {
  background-color: rgba(239, 68, 68, 0.1);
  border: 1px solid #EF4444;
  color: #EF4444;
  padding: 15px;
  border-radius: 4px;
  margin-bottom: 20px;
}

.success {
  background-color: rgba(16, 185, 129, 0.1);
  border: 1px solid #10B981;
  color: #10B981;
  padding: 15px;
  border-radius: 4px;
  margin-bottom: 20px;
}

.warning {
  background-color: rgba(245, 124, 0, 0.1);
  border: 1px solid #F59E0B;
  color: #F59E0B;
  padding: 15px;
  border-radius: 4px;
  margin-bottom: 20px;
}

.file-info {
  background-color: #2A2A2A;
  border: 1px solid #374151;
  border-radius: 4px;
  padding: 15px;
  margin-top: 15px;
}

.file-info h3 {
  margin-top: 0;
  color: #4A90E2;
}

.file-info p {
  margin: 5px 0;
}

.node-info {
  background-color: #2A2A2A;
  border: 1px solid #374151;
  border-radius: 4px;
  padding: 15px;
  margin-bottom: 20px;
}

.node-info h3 {
  margin-top: 0;
  color: #4A90E2;
}

.node-info p {
  margin: 5px 0;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
  color: #fff;
}

.form-group input[type="text"] {
  width: 100%;
  padding: 8px;
  border: 1px solid #374151;
  border-radius: 4px;
  background-color: #2A2A2A;
  color: #E0E0E0;
  box-sizing: border-box;
}

.form-group input[type="text"]:focus {
  outline: none;
  border-color: #007bff;
}

.form-group input[type="checkbox"] {
  margin-right: 8px;
}

.form-group small {
  color: #999;
  font-size: 0.9em;
}

.node-input-section {
  text-align: center;
  padding: 20px 0;
}

.button-group {
  text-align: center;
  margin-top: 20px;
}

.submit-large {
  background-color: #007bff;
  color: white;
  border: none;
  padding: 10px 20px;
  margin: 5px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
}

.submit-large:hover:not(:disabled) {
  background-color: #0056b3;
}

.submit-large:disabled {
  background-color: #6c757d;
  cursor: not-allowed;
}

.submit-large[value="Add to Favorites"] {
  background-color: #10B981;
}

.submit-large[value="Add to Favorites"]:hover:not(:disabled) {
  background-color: #059669;
}

.submit-large[value="Cancel"] {
  background-color: #6B7280;
}

.submit-large[value="Cancel"]:hover:not(:disabled) {
  background-color: #4B5563;
}
</style>
