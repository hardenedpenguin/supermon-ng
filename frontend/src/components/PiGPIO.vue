<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>Pi GPIO Control</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>

      <div class="modal-body">
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
        <div v-if="!loading && !error" class="gpio-content">
          <!-- Form Section -->
          <div class="form-section">
            <h4>GPIO Pin Control</h4>
            <form @submit.prevent="executeAction" class="gpio-form">
              <div class="form-row">
                <div class="form-group">
                  <label for="pin">GPIO Pin:</label>
                  <input 
                    id="pin" 
                    v-model="formData.pin" 
                    type="number" 
                    min="0" 
                    max="40" 
                    required
                    placeholder="0-40"
                  >
                </div>
                <div class="form-group">
                  <label for="state">State:</label>
                  <select id="state" v-model="formData.state" required>
                    <option value="">Select State</option>
                    <option value="input">Input</option>
                    <option value="output">Output</option>
                    <option value="up">Pull Up</option>
                    <option value="down">Pull Down</option>
                    <option value="0">Write 0 (LOW)</option>
                    <option value="1">Write 1 (HIGH)</option>
                  </select>
                </div>
              </div>

              <div class="form-actions">
                <button type="submit" class="execute-button" :disabled="executing">
                  {{ executing ? 'Executing...' : 'Execute' }}
                </button>
                <button type="button" @click="refreshStatus" class="refresh-button" :disabled="loading">
                  Refresh Status
                </button>
              </div>
            </form>
          </div>

          <!-- Status Section -->
          <div class="status-section">
            <h4>GPIO Status</h4>
            <div class="status-content">
              <div v-if="gpioStatus.pins && gpioStatus.pins.length > 0" class="status-table">
                <table>
                  <thead>
                    <tr>
                      <th>Pin</th>
                      <th>Mode</th>
                      <th>Value</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="pin in gpioStatus.pins" :key="pin.pin">
                      <td>{{ pin.pin }}</td>
                      <td>
                        <span :class="getModeClass(pin.mode)">{{ pin.mode }}</span>
                      </td>
                      <td>
                        <span :class="getValueClass(pin.value)">{{ pin.value }}</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-else-if="gpioStatus.error" class="error-status">
                <p>{{ gpioStatus.error }}</p>
              </div>
              <div v-else class="empty-status">
                <p>No GPIO status available</p>
              </div>
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
  }
})

const emit = defineEmits(['update:isVisible'])

// Reactive state
const loading = ref(false)
const executing = ref(false)
const error = ref('')
const successMessage = ref('')
const loadingMessage = ref('Loading...')
const gpioStatus = ref({ pins: [] })

// Form data
const formData = ref({
  pin: '',
  state: ''
})

// Watch for modal visibility changes
watch(() => props.isVisible, (newVal) => {
  if (newVal) {
    resetState()
    loadGPIOStatus()
  }
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
  gpioStatus.value = { pins: [] }
  formData.value = {
    pin: '',
    state: ''
  }
}

const loadGPIOStatus = async () => {
  loading.value = true
  loadingMessage.value = 'Loading GPIO status...'
  error.value = ''
  successMessage.value = ''

  try {
    const response = await api.post('/nodes/pigpio')

    if (response.data.success) {
      gpioStatus.value = response.data.data.gpio_status
    } else {
      error.value = response.data.message || 'Failed to load GPIO status'
    }
  } catch (err) {
    console.error('GPIO error:', err)
    error.value = err.response?.data?.message || 'Failed to load GPIO status'
  } finally {
    loading.value = false
  }
}

const executeAction = async () => {
  if (!formData.value.pin || !formData.value.state) {
    error.value = 'Please fill in all fields'
    return
  }

  executing.value = true
  error.value = ''
  successMessage.value = ''

  try {
    const response = await api.post('/nodes/pigpio/action', {
      pin: formData.value.pin,
      state: formData.value.state
    })

    if (response.data.success) {
      successMessage.value = response.data.message
      gpioStatus.value = response.data.data.gpio_status
      
      // Clear form
      formData.value.pin = ''
      formData.value.state = ''
    } else {
      error.value = response.data.message || 'Failed to execute GPIO command'
    }
  } catch (err) {
    console.error('GPIO action error:', err)
    error.value = err.response?.data?.message || 'Failed to execute GPIO command'
  } finally {
    executing.value = false
  }
}

const refreshStatus = () => {
  loadGPIOStatus()
}

const getModeClass = (mode) => {
  const modeClasses = {
    'input': 'mode-input',
    'output': 'mode-output',
    'up': 'mode-up',
    'down': 'mode-down'
  }
  return modeClasses[mode] || 'mode-default'
}

const getValueClass = (value) => {
  const valueClasses = {
    '0': 'value-low',
    '1': 'value-high',
    'LOW': 'value-low',
    'HIGH': 'value-high'
  }
  return valueClasses[value] || 'value-default'
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
  max-width: 1000px;
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

.gpio-content {
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

.gpio-form {
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

.form-group input,
.form-group select {
  padding: 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background-color: var(--background-color);
  color: var(--text-color);
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

.status-section {
  background-color: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: 4px;
  padding: 1.5rem;
}

.status-section h4 {
  margin: 0 0 1rem 0;
  color: var(--text-color);
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 0.5rem;
}

.status-table {
  overflow-x: auto;
}

.status-table table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.status-table th,
.status-table td {
  padding: 0.5rem;
  text-align: left;
  border-bottom: 1px solid var(--border-color);
}

.status-table th {
  background-color: var(--border-color);
  color: var(--text-color);
  border-bottom: 1px solid #000000;
  font-weight: bold;
}

.status-table td {
  color: var(--text-color);
}

/* Mode classes */
.mode-input {
  color: #17a2b8;
  font-weight: bold;
}

.mode-output {
  color: #28a745;
  font-weight: bold;
}

.mode-up {
  color: #ffc107;
  font-weight: bold;
}

.mode-down {
  color: #6c757d;
  font-weight: bold;
}

.mode-default {
  color: var(--text-color);
}

/* Value classes */
.value-low {
  color: #dc3545;
  font-weight: bold;
}

.value-high {
  color: #28a745;
  font-weight: bold;
}

.value-default {
  color: var(--text-color);
}

.error-status {
  text-align: center;
  padding: 2rem;
  color: var(--error-color);
  font-style: italic;
}

.empty-status {
  text-align: center;
  padding: 2rem;
  color: var(--secondary-text);
  font-style: italic;
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column;
  }
}
</style>
