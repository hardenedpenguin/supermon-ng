<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="open" class="astlookup-modal">
        <div class="astlookup-overlay" @click="closeModal"></div>
        <div class="astlookup-content">
          <div class="astlookup-header">
            <h2>AllStar Node Lookup</h2>
            <button class="close-button" @click="closeModal">&times;</button>
          </div>

          <div class="astlookup-body">
            <!-- Lookup Form -->
            <div class="lookup-form">
              <div class="form-group">
                <label for="lookupInput">Node/Callsign to Lookup:</label>
                <input 
                  type="text" 
                  id="lookupInput"
                  v-model="lookupNode"
                  placeholder="Enter node number or callsign"
                  maxlength="20"
                  @keyup.enter="performLookup"
                  :disabled="loading"
                >
              </div>
              
              <div class="form-actions">
                <button 
                  @click="performLookup" 
                  :disabled="loading"
                  class="lookup-btn"
                >
                  {{ loading ? 'Searching...' : 'Lookup' }}
                </button>
                <button @click="clearResults" class="clear-btn">
                  Clear
                </button>
              </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="loading-state">
              <p>Searching databases...</p>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="error-state">
              <p>{{ error }}</p>
            </div>

            <!-- Results -->
            <div v-else-if="results.length > 0" class="lookup-results">
              <h3>Lookup Results for: {{ lookupNode }}</h3>
              
              <div class="results-table-container">
                <table class="results-table">
                  <thead>
                    <tr>
                      <th>Node</th>
                      <th>Callsign</th>
                      <th>Description</th>
                      <th>Location</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-for="(section, sectionIndex) in results" :key="sectionIndex">
                      <!-- Section Header -->
                      <tr class="section-header">
                        <td colspan="5">{{ section.type }}</td>
                      </tr>
                      
                      <!-- Section Results -->
                      <tr v-for="(result, resultIndex) in section.results" :key="`${sectionIndex}-${resultIndex}`" class="result-row">
                        <td>{{ result.node }}</td>
                        <td>{{ result.callsign }}</td>
                        <td>{{ result.description }}</td>
                        <td>{{ result.location }}</td>
                        <td :class="getStatusClass(result.status)">{{ result.status }}</td>
                      </tr>
                      
                      <!-- No Results Message -->
                      <tr v-if="section.results.length === 0" class="no-results">
                        <td colspan="5">....Nothing Found....</td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="hasSearched" class="empty-state">
              <p>No results found for "{{ lookupNode }}"</p>
            </div>
          </div>

          <div class="astlookup-footer">
            <div class="button-group">
              <input 
                type="button" 
                value="Close" 
                @click="closeModal"
                class="close-btn"
              >
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'

interface Props {
  open: boolean
  localNode?: string // Optional - will use default if not provided
}

interface LookupResult {
  node: string
  callsign: string
  description: string
  location: string
  status: string
}

interface ResultSection {
  type: string
  results: LookupResult[]
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const loading = ref(false)
const error = ref('')
const lookupNode = ref('')
const results = ref<ResultSection[]>([])
const hasSearched = ref(false)

const performLookup = async () => {
  if (!lookupNode.value.trim()) {
    error.value = 'Please enter a node number or callsign to lookup'
    hasSearched.value = true
    return
  }
  
  loading.value = true
  error.value = ''
  hasSearched.value = true
  
  try {
    const response = await axios.post('/api/config/astlookup', {
      lookupNode: lookupNode.value.trim(),
      localNode: props.localNode || '546051' // Default local node if not provided
    }, { 
      withCredentials: true 
    })
    
    if (response.data.success) {
      results.value = response.data.results
    } else {
      error.value = response.data.message || 'Lookup failed'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Lookup failed'
  } finally {
    loading.value = false
  }
}

const clearResults = () => {
  results.value = []
  hasSearched.value = false
  error.value = ''
}

const getStatusClass = (status: string) => {
  if (status === 'ONLINE' || status === 'ON') return 'status-online'
  if (status === 'OFFLINE' || status === 'OFF') return 'status-offline'
  if (status === 'NOT FOUND') return 'status-not-found'
  return 'status-unknown'
}

const closeModal = () => {
  emit('update:open', false)
  // Reset state
  loading.value = false
  error.value = ''
  lookupNode.value = ''
  results.value = []
  hasSearched.value = false
}

// Watch for modal open state
watch(() => props.open, (newOpen) => {
  if (newOpen) {
    // Focus the input when modal opens
    setTimeout(() => {
      const input = document.getElementById('lookupInput')
      if (input) input.focus()
    }, 100)
  }
})
</script>

<style scoped>
.astlookup-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 1000;
  display: flex;
  justify-content: center;
  align-items: center;
}

.astlookup-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.7);
}

.astlookup-content {
  position: relative;
  background-color: #1f2937;
  border: 1px solid #374151;
  border-radius: 8px;
  max-width: 1200px;
  width: 95%;
  max-height: 90vh;
  overflow-y: auto;
  color: #f9fafb;
}

.astlookup-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #374151;
  background-color: #111827;
}

.astlookup-header h2 {
  margin: 0;
  color: #f9fafb;
  font-size: 1.5rem;
}

.close-button {
  background: none;
  border: none;
  color: #9ca3af;
  font-size: 1.5rem;
  cursor: pointer;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-button:hover {
  color: #f9fafb;
}

.astlookup-body {
  padding: 20px;
}

.lookup-form {
  margin-bottom: 30px;
  padding: 20px;
  background-color: #111827;
  border-radius: 6px;
  border: 1px solid #374151;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  color: #d1d5db;
  font-weight: 500;
}

.form-group input {
  width: 100%;
  padding: 10px;
  border: 1px solid #4b5563;
  border-radius: 4px;
  background-color: #374151;
  color: #f9fafb;
  font-size: 1rem;
}

.form-group input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.form-actions {
  display: flex;
  gap: 10px;
}

.lookup-btn, .clear-btn {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 500;
}

.lookup-btn {
  background-color: #2563eb;
  color: white;
}

.lookup-btn:hover:not(:disabled) {
  background-color: #1d4ed8;
}

.lookup-btn:disabled {
  background-color: #6b7280;
  cursor: not-allowed;
}

.clear-btn {
  background-color: #6b7280;
  color: white;
}

.clear-btn:hover {
  background-color: #4b5563;
}

.loading-state, .error-state, .empty-state {
  text-align: center;
  padding: 40px 20px;
}

.loading-state p {
  color: #9ca3af;
  font-size: 1.1rem;
}

.error-state p {
  color: #ef4444;
  font-size: 1.1rem;
}

.empty-state p {
  color: #6b7280;
  font-size: 1.1rem;
}

.lookup-results h3 {
  margin-bottom: 20px;
  color: #f9fafb;
  font-size: 1.3rem;
}

.results-table-container {
  overflow-x: auto;
}

.results-table {
  width: 100%;
  border-collapse: collapse;
  background-color: #111827;
  border: 1px solid #374151;
  border-radius: 6px;
  overflow: hidden;
}

.results-table th {
  background-color: #1f2937;
  color: #f9fafb;
  padding: 12px 8px;
  text-align: left;
  font-weight: 600;
  border-bottom: 1px solid #374151;
}

.results-table td {
  padding: 10px 8px;
  border-bottom: 1px solid #374151;
  color: #d1d5db;
}

.section-header {
  background-color: #374151;
}

.section-header td {
  color: #f9fafb;
  font-weight: 600;
  text-align: center;
  padding: 12px 8px;
}

.result-row:hover {
  background-color: #1f2937;
}

.no-results td {
  text-align: center;
  color: #6b7280;
  font-style: italic;
  padding: 20px 8px;
}

.status-online {
  color: #10b981;
  font-weight: 600;
}

.status-offline {
  color: #ef4444;
  font-weight: 600;
}

.status-not-found {
  color: #f59e0b;
  font-weight: 600;
}

.status-unknown {
  color: #6b7280;
}

.astlookup-footer {
  padding: 20px;
  border-top: 1px solid #374151;
  background-color: #111827;
}

.button-group {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.close-btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 500;
  background-color: #6b7280;
  color: white;
}

.close-btn:hover {
  background-color: #4b5563;
}

/* Modal transition */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
