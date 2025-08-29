<template>
  <div v-if="open" class="modal-overlay" @click="closeModal">
    <div class="modal-content config-editor-modal" @click.stop>
      <div class="modal-header">
        <h3>Configuration Editor</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>
      
      <div class="modal-body">
        <div v-if="loading" class="loading-container">
          <div class="loading-spinner"></div>
          <p>Loading configuration files...</p>
        </div>
        
        <div v-else-if="error" class="error-container">
          <div class="error-message">
            <h4>Error</h4>
            <p>{{ error }}</p>
            <button class="btn btn-primary" @click="loadFiles">Retry</button>
          </div>
        </div>
        
        <div v-else class="config-editor-content">
          <!-- File Selection Section -->
          <div class="file-selection-section">
            <h4>Select Configuration File</h4>
            <div class="file-categories">
              <div v-for="(files, category) in fileCategories" :key="category" class="file-category">
                <h5>{{ category }}</h5>
                <div class="file-list">
                  <button
                    v-for="file in files"
                    :key="file.path"
                    class="file-button"
                    :class="{ 'active': selectedFile?.path === file.path }"
                    @click="selectFile(file)"
                  >
                    <div class="file-name">{{ file.name }}</div>
                    <div class="file-description">{{ file.description }}</div>
                    <div class="file-path">{{ file.path }}</div>
                  </button>
                </div>
              </div>
            </div>
          </div>
          
          <!-- File Editor Section -->
          <div v-if="selectedFile" class="file-editor-section">
            <div class="editor-header">
              <h4>Editing: {{ selectedFile.name }}</h4>
              <div class="editor-actions">
                <button class="btn btn-secondary" @click="reloadFile" :disabled="saving">
                  <span class="icon">🔄</span> Reload
                </button>
                <button class="btn btn-primary" @click="saveFile" :disabled="saving">
                  <span class="icon">💾</span> Save
                </button>
              </div>
            </div>
            
            <div class="editor-content">
              <div class="file-info">
                <strong>Path:</strong> {{ selectedFile.path }}
              </div>
              
              <div class="editor-container">
                <textarea
                  v-model="fileContent"
                  class="file-editor"
                  placeholder="File content will appear here..."
                  :disabled="saving"
                ></textarea>
              </div>
              
              <div v-if="saveResult" class="save-result">
                <div v-if="saveResult.success" class="success-message">
                  <span class="icon">✅</span> File saved successfully!
                </div>
                <div v-else class="error-message">
                  <span class="icon">❌</span> {{ saveResult.message }}
                </div>
              </div>
            </div>
          </div>
          
          <!-- No File Selected -->
          <div v-else class="no-file-selected">
            <div class="placeholder-content">
              <div class="icon">📁</div>
              <h4>Select a Configuration File</h4>
              <p>Choose a file from the list above to edit its contents.</p>
            </div>
          </div>
        </div>
      </div>
      
      <div class="modal-footer">
        <button class="btn btn-secondary" @click="closeModal">Close</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  open: Boolean
})

const emit = defineEmits(['update:open'])

const loading = ref(false)
const error = ref('')
const fileCategories = ref({})
const selectedFile = ref(null)
const fileContent = ref('')
const saving = ref(false)
const saveResult = ref(null)

const closeModal = () => {
  emit('update:open', false)
  resetState()
}

const resetState = () => {
  selectedFile.value = null
  fileContent.value = ''
  saveResult.value = null
  error.value = ''
}

const loadFiles = async () => {
  loading.value = true
  error.value = ''
  
  try {
    const response = await axios.get('/api/config/configeditor/files')
    if (response.data.success) {
      fileCategories.value = response.data.files
    } else {
      error.value = response.data.message || 'Failed to load configuration files'
    }
  } catch (err) {
    console.error('Config editor files error:', err)
    error.value = err.response?.data?.message || 'Failed to load configuration files'
  } finally {
    loading.value = false
  }
}

const selectFile = async (file) => {
  selectedFile.value = file
  fileContent.value = ''
  saveResult.value = null
  
  try {
    const response = await axios.post('/api/config/configeditor/content', {
      filePath: file.path
    })
    
    if (response.data.success) {
      fileContent.value = response.data.content
    } else {
      error.value = response.data.message || 'Failed to load file content'
    }
  } catch (err) {
    console.error('File content error:', err)
    error.value = err.response?.data?.message || 'Failed to load file content'
  }
}

const reloadFile = async () => {
  if (selectedFile.value) {
    await selectFile(selectedFile.value)
  }
}

const saveFile = async () => {
  if (!selectedFile.value) return
  
  saving.value = true
  saveResult.value = null
  
  try {
    const response = await axios.post('/api/config/configeditor/save', {
      filePath: selectedFile.value.path,
      content: fileContent.value
    })
    
    if (response.data.success) {
      saveResult.value = {
        success: true,
        message: response.data.message
      }
    } else {
      saveResult.value = {
        success: false,
        message: response.data.message
      }
    }
  } catch (err) {
    console.error('Save file error:', err)
    saveResult.value = {
      success: false,
      message: err.response?.data?.message || 'Failed to save file'
    }
  } finally {
    saving.value = false
  }
}

watch(() => props.open, (newValue) => {
  if (newValue) {
    loadFiles()
  }
})

onMounted(() => {
  if (props.open) {
    loadFiles()
  }
})
</script>

<style scoped>
.config-editor-modal {
  max-width: 90vw;
  max-height: 90vh;
  width: 1200px;
  height: 800px;
}

.config-editor-content {
  display: flex;
  height: 600px;
  gap: 20px;
}

.file-selection-section {
  flex: 0 0 400px;
  border-right: 1px solid var(--border-color);
  overflow-y: auto;
}

.file-editor-section {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.file-categories {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.file-category h5 {
  margin: 0 0 10px 0;
  padding: 10px;
  background: var(--primary-color);
  color: white;
  border-radius: 4px;
  font-size: 14px;
}

.file-list {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.file-button {
  text-align: left;
  padding: 10px;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background: white;
  cursor: pointer;
  transition: all 0.2s;
}

.file-button:hover {
  background: var(--hover-color);
  border-color: var(--primary-color);
}

.file-button.active {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.file-name {
  font-weight: bold;
  font-size: 14px;
  margin-bottom: 2px;
}

.file-description {
  font-size: 12px;
  opacity: 0.8;
  margin-bottom: 2px;
}

.file-path {
  font-size: 11px;
  font-family: monospace;
  opacity: 0.6;
}

.editor-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid var(--border-color);
  margin-bottom: 15px;
}

.editor-header h4 {
  margin: 0;
}

.editor-actions {
  display: flex;
  gap: 10px;
}

.editor-content {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.file-info {
  margin-bottom: 10px;
  padding: 8px;
  background: var(--background-secondary);
  border-radius: 4px;
  font-size: 12px;
  font-family: monospace;
}

.editor-container {
  flex: 1;
  margin-bottom: 15px;
}

.file-editor {
  width: 100%;
  height: 100%;
  min-height: 400px;
  padding: 15px;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  font-family: 'Courier New', monospace;
  font-size: 12px;
  line-height: 1.4;
  resize: none;
  background: #f8f9fa;
}

.file-editor:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.save-result {
  padding: 10px;
  border-radius: 4px;
  font-size: 14px;
}

.success-message {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.error-message {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.no-file-selected {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.placeholder-content {
  text-align: center;
  color: var(--text-muted);
}

.placeholder-content .icon {
  font-size: 48px;
  margin-bottom: 15px;
  display: block;
}

.placeholder-content h4 {
  margin: 0 0 10px 0;
}

.placeholder-content p {
  margin: 0;
  font-size: 14px;
}

.icon {
  margin-right: 5px;
}

.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 400px;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid var(--border-color);
  border-top: 4px solid var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 15px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error-container {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 400px;
}

.error-message {
  text-align: center;
  max-width: 400px;
}

.error-message h4 {
  margin: 0 0 10px 0;
  color: var(--danger-color);
}

.error-message p {
  margin: 0 0 15px 0;
  color: var(--text-muted);
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-dark);
}

.btn-secondary {
  background: var(--secondary-color);
  color: var(--text-color);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--secondary-dark);
}
</style>
