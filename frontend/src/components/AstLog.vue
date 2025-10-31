<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="open" class="astlog-modal">
        <div class="astlog-overlay" @click="closeModal"></div>
        <div class="astlog-content">
          <div class="astlog-header">
            <h2>Asterisk Messages Log Viewer</h2>
            <button class="close-button" @click="closeModal">&times;</button>
          </div>

          <div class="astlog-body">
            <!-- Loading state -->
            <div v-if="loading" class="loading">
              <p>Loading Asterisk log...</p>
            </div>

            <!-- Error state -->
            <div v-else-if="error" class="error-message">
              <p>{{ error }}</p>
            </div>

            <!-- Log content -->
            <div v-else-if="logContent" class="log-content">
              <div class="log-info">
                <p><strong>Log File:</strong> {{ logPath }}</p>
                <p><strong>Last Updated:</strong> {{ lastModified }}</p>
              </div>
              
              <hr>
              
              <div class="log-controls">
                <button @click="refreshLog" :disabled="loading" class="refresh-btn">
                  {{ loading ? 'Refreshing...' : 'Refresh Log' }}
                </button>
                <button @click="copyToClipboard" class="copy-btn">
                  Copy to Clipboard
                </button>
              </div>
              
              <pre class="log-viewer-pre">{{ logContent }}</pre>
            </div>

            <!-- Empty state -->
            <div v-else class="empty-state">
              <p>No log content available.</p>
            </div>
          </div>

          <div class="astlog-footer">
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
import { api } from '@/utils/api'
import type { AxiosErrorResponse } from '@/types/api'

interface Props {
  open: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const loading = ref(false)
const error = ref('')
const logContent = ref('')
const logPath = ref('')
const lastModified = ref('')

const loadAstLog = async () => {
  loading.value = true
  error.value = ''
  
  try {
    const response = await api.get('/config/astlog')
    
    if (response.data.success) {
      logContent.value = response.data.content
      logPath.value = response.data.path
      lastModified.value = response.data.lastModified
    } else {
      error.value = response.data.message || 'Failed to load Asterisk log'
    }
  } catch (err: unknown) {
    const axiosError = err as AxiosErrorResponse
    error.value = axiosError.response?.data?.message || 'Failed to load Asterisk log'
  } finally {
    loading.value = false
  }
}

const refreshLog = async () => {
  await loadAstLog()
}

const copyToClipboard = async () => {
  try {
    await navigator.clipboard.writeText(logContent.value)
    alert('Log content copied to clipboard!')
  } catch (err) {
    console.error('Failed to copy to clipboard:', err)
    alert('Failed to copy to clipboard')
  }
}

const closeModal = () => {
  emit('update:open', false)
  // Reset state
  loading.value = false
  error.value = ''
  logContent.value = ''
  logPath.value = ''
  lastModified.value = ''
}

// Watch for modal open state
watch(() => props.open, (newOpen) => {
  if (newOpen) {
    loadAstLog()
  }
})

// Load log when component mounts if modal is open
onMounted(() => {
  if (props.open) {
    loadAstLog()
  }
})
</script>

<style scoped>
.astlog-modal {
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

.astlog-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.7);
}

.astlog-content {
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

.astlog-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #374151;
  background-color: #111827;
}

.astlog-header h2 {
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

.astlog-body {
  padding: 20px;
}

.loading, .error-message, .empty-state {
  text-align: center;
  padding: 40px 20px;
}

.loading p {
  color: #9ca3af;
  font-size: 1.1rem;
}

.error-message p {
  color: #ef4444;
  font-size: 1.1rem;
}

.empty-state p {
  color: #6b7280;
  font-size: 1.1rem;
}

.log-info {
  margin-bottom: 20px;
  padding: 15px;
  background-color: #111827;
  border-radius: 6px;
  border: 1px solid #374151;
}

.log-info p {
  margin: 5px 0;
  color: #d1d5db;
}

.log-controls {
  margin-bottom: 20px;
  display: flex;
  gap: 10px;
}

.refresh-btn, .copy-btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 500;
}

.refresh-btn {
  background-color: #2563eb;
  color: white;
}

.refresh-btn:hover:not(:disabled) {
  background-color: #1d4ed8;
}

.refresh-btn:disabled {
  background-color: #6b7280;
  cursor: not-allowed;
}

.copy-btn {
  background-color: #059669;
  color: white;
}

.copy-btn:hover {
  background-color: #047857;
}

.log-viewer-pre {
  background-color: #111827;
  border: 1px solid #374151;
  border-radius: 6px;
  padding: 20px;
  overflow-x: auto;
  font-family: 'Courier New', monospace;
  font-size: 0.9rem;
  line-height: 1.4;
  color: #d1d5db;
  white-space: pre-wrap;
  word-wrap: break-word;
  max-height: 60vh;
  overflow-y: auto;
}

.astlog-footer {
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
