<template>
  <div v-if="isVisible" class="modal-overlay" @click.self="closeModal">
    <div class="modal-content node-status-modal">
      <div class="modal-header">
        <h3>Node Status Configuration</h3>
        <button @click="closeModal" class="close-button">&times;</button>
      </div>
      
      <div class="modal-body">
        <div v-if="loading" class="loading">
          <p>Loading configuration...</p>
        </div>
        
        <div v-else class="config-form">
          <!-- Service Status -->
          <div class="status-section">
            <h4>Service Status</h4>
            <div class="status-info">
              <div class="status-item">
                <span class="status-label">Service:</span>
                <span :class="['status-value', serviceStatus?.service_active ? 'active' : 'inactive']">
                  {{ serviceStatus?.service_active ? 'Running' : 'Stopped' }}
                </span>
              </div>
              <div class="status-item">
                <span class="status-label">Auto-start:</span>
                <span :class="['status-value', serviceStatus?.service_enabled ? 'enabled' : 'disabled']">
                  {{ serviceStatus?.service_enabled ? 'Enabled' : 'Disabled' }}
                </span>
              </div>
              <div v-if="serviceStatus?.last_update" class="status-item">
                <span class="status-label">Last Update:</span>
                <span class="status-value">{{ serviceStatus.last_update }}</span>
              </div>
            </div>
            
            <div class="action-buttons">
              <button @click="triggerUpdate" :disabled="updating" class="btn btn-primary">
                {{ updating ? 'Updating...' : 'Update Now' }}
              </button>
            </div>
          </div>

          <!-- Configuration Form -->
          <form @submit.prevent="saveConfig" class="config-section">
            <h4>Configuration</h4>
            
            <!-- Node Numbers -->
            <div class="form-group">
              <label for="nodes">Node Numbers:</label>
              <input 
                type="text" 
                id="nodes" 
                v-model="nodeNumbers" 
                placeholder="546051 546055 546056"
                class="form-control"
                required
              >
              <small class="form-text">Space-separated list of node numbers</small>
            </div>

            <!-- Weather Configuration -->
            <div class="form-group">
              <label for="wx_code">Weather Code:</label>
              <input 
                type="text" 
                id="wx_code" 
                v-model="config.wx_code" 
                placeholder="77511"
                class="form-control"
              >
            </div>

            <div class="form-group">
              <label for="wx_location">Weather Location:</label>
              <input 
                type="text" 
                id="wx_location" 
                v-model="config.wx_location" 
                placeholder="Alvin, Texas"
                class="form-control"
              >
            </div>

            <div class="form-group">
              <label for="temp_unit">Temperature Unit:</label>
              <select id="temp_unit" v-model="config.temp_unit" class="form-control">
                <option value="F">Fahrenheit (°F)</option>
                <option value="C">Celsius (°C)</option>
              </select>
            </div>

            <!-- SkywarnPlus Configuration -->
            <div class="form-group">
              <label class="checkbox-label">
                <input 
                  type="checkbox" 
                  v-model="config.skywarnplus_enabled"
                >
                Enable SkywarnPlus Weather Alerts
              </label>
            </div>

            <div v-if="config.skywarnplus_enabled" class="skywarnplus-config">
              <div class="form-group">
                <label for="api_url">API URL:</label>
                <input 
                  type="url" 
                  id="api_url" 
                  v-model="config.api_url" 
                  placeholder="http://10.0.0.5:8100"
                  class="form-control"
                >
                <small class="form-text">SkywarnPlus-ng API endpoint URL</small>
              </div>

              <div class="form-group">
                <label for="custom_link">Custom Alert Link:</label>
                <input 
                  type="url" 
                  id="custom_link" 
                  v-model="config.custom_link" 
                  placeholder="https://api.weather.gov/alerts/active/zone/TXC039"
                  class="form-control"
                >
                <small class="form-text">Optional custom link for weather alerts</small>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" :disabled="saving" class="btn btn-success">
                {{ saving ? 'Saving...' : 'Save Configuration' }}
              </button>
              <button type="button" @click="loadConfig" class="btn btn-secondary">
                Reset
              </button>
            </div>
          </form>
        </div>

        <!-- Update Output -->
        <div v-if="updateOutput" class="update-output">
          <h4>Update Output:</h4>
          <pre>{{ updateOutput }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, watch, onMounted } from 'vue'
import { api } from '@/utils/api'

export default {
  name: 'NodeStatus',
  props: {
    isVisible: {
      type: Boolean,
      default: false
    }
  },
  emits: ['close'],
  setup(props, { emit }) {
    const loading = ref(false)
    const saving = ref(false)
    const updating = ref(false)
    const updateOutput = ref('')
    
    const config = ref({
      nodes: [],
      wx_code: '',
      wx_location: '',
      temp_unit: 'F',
      skywarnplus_enabled: false,
      api_url: '',
      custom_link: ''
    })
    
    const nodeNumbers = ref('')
    const serviceStatus = ref(null)

    const closeModal = () => {
      emit('close')
    }

    const loadConfig = async () => {
      loading.value = true
      try {
        const response = await api.get('/node-status/config')
        if (response.data.success && response.data.config) {
          const cfg = response.data.config
          config.value = {
            nodes: cfg.general?.NODE?.split(' ') || [],
            wx_code: cfg.general?.WX_CODE || '',
            wx_location: cfg.general?.WX_LOCATION || '',
            temp_unit: cfg.general?.TEMP_UNIT || 'F',
            skywarnplus_enabled: cfg.skywarnplus?.MASTER_ENABLE === 'yes',
            api_url: cfg.skywarnplus?.API_URL || '',
            custom_link: cfg.skywarnplus?.CUSTOM_LINK || ''
          }
          nodeNumbers.value = config.value.nodes.join(' ')
        }
      } catch (error) {
        console.error('Error loading node status config:', error)
      } finally {
        loading.value = false
      }
    }

    const loadServiceStatus = async () => {
      try {
        const response = await api.get('/node-status/service-status')
        if (response.data.success) {
          serviceStatus.value = response.data
        }
      } catch (error) {
        console.error('Error loading service status:', error)
      }
    }

    const saveConfig = async () => {
      saving.value = true
      try {
        const configData = {
          ...config.value,
          nodes: nodeNumbers.value.split(' ').filter(n => n.trim())
        }
        
        const response = await api.put('/node-status/config', configData)
        if (response.data.success) {
          alert('Configuration saved successfully!')
        } else {
          alert('Error saving configuration: ' + response.data.message)
        }
      } catch (error) {
        console.error('Error saving config:', error)
        alert('Error saving configuration')
      } finally {
        saving.value = false
      }
    }

    const triggerUpdate = async () => {
      updating.value = true
      updateOutput.value = ''
      try {
        const response = await api.post('/node-status/trigger-update')
        if (response.data.success) {
          updateOutput.value = response.data.output || 'Update completed successfully'
          await loadServiceStatus() // Refresh service status
        } else {
          updateOutput.value = 'Error: ' + response.data.message
        }
      } catch (error) {
        console.error('Error triggering update:', error)
        updateOutput.value = 'Error triggering update'
      } finally {
        updating.value = false
      }
    }

    watch(() => props.isVisible, (visible) => {
      if (visible) {
        loadConfig()
        loadServiceStatus()
      }
    })

    return {
      loading,
      saving,
      updating,
      updateOutput,
      config,
      nodeNumbers,
      serviceStatus,
      closeModal,
      loadConfig,
      saveConfig,
      triggerUpdate
    }
  }
}
</script>

<style scoped>
.modal-overlay {
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

.node-status-modal {
  background: #1a1a1a;
  border-radius: 8px;
  width: 90vw;
  max-width: 800px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  border: 1px solid #333;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  border-bottom: 1px solid #333;
  background: #2a2a2a;
  border-radius: 8px 8px 0 0;
}

.modal-header h3 {
  margin: 0;
  color: #fff;
  font-size: 1.2em;
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
  border-radius: 4px;
  transition: background-color 0.2s;
}

.close-button:hover {
  background-color: #444;
}

.modal-body {
  flex: 1;
  padding: 20px;
  overflow-y: auto;
  color: #fff;
}

.loading {
  text-align: center;
  padding: 40px;
}

.status-section {
  margin-bottom: 30px;
  padding: 15px;
  background: #2a2a2a;
  border-radius: 6px;
}

.status-section h4 {
  margin-top: 0;
  color: #fff;
}

.status-info {
  margin: 15px 0;
}

.status-item {
  display: flex;
  justify-content: space-between;
  margin: 8px 0;
}

.status-label {
  font-weight: bold;
}

.status-value.active {
  color: #28a745;
}

.status-value.inactive {
  color: #dc3545;
}

.status-value.enabled {
  color: #28a745;
}

.status-value.disabled {
  color: #ffc107;
}

.action-buttons {
  margin-top: 15px;
}

.config-section h4 {
  margin-top: 0;
  color: #fff;
  border-bottom: 1px solid #444;
  padding-bottom: 10px;
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

.form-control {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #444;
  border-radius: 4px;
  background: #333;
  color: #fff;
  font-size: 14px;
}

.form-control:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.form-text {
  display: block;
  margin-top: 5px;
  font-size: 12px;
  color: #888;
}

.checkbox-label {
  display: flex;
  align-items: center;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  margin-right: 8px;
}

.skywarnplus-config {
  margin-left: 20px;
  padding-left: 15px;
  border-left: 2px solid #444;
}

.form-actions {
  margin-top: 20px;
  display: flex;
  gap: 10px;
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  transition: background-color 0.2s;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: #007bff;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #0056b3;
}

.btn-success {
  background: #28a745;
  color: white;
}

.btn-success:hover:not(:disabled) {
  background: #1e7e34;
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background: #545b62;
}

.update-output {
  margin-top: 20px;
  padding: 15px;
  background: #2a2a2a;
  border-radius: 6px;
}

.update-output h4 {
  margin-top: 0;
  color: #fff;
}

.update-output pre {
  background: #1a1a1a;
  padding: 10px;
  border-radius: 4px;
  overflow-x: auto;
  color: #fff;
  font-size: 12px;
  white-space: pre-wrap;
}
</style>
