<template>
  <div class="custom-theme-builder">
    <div class="builder-header">
      <h3>{{ isEditing ? 'Edit Custom Theme' : 'Create Custom Theme' }}</h3>
      <button @click="$emit('close')" class="close-btn" title="Close">
        ×
      </button>
    </div>

    <form @submit.prevent="saveTheme" class="theme-form">
      <div class="form-group">
        <label for="themeName">Theme Name</label>
        <input 
          id="themeName"
          v-model="themeData.label" 
          type="text" 
          required 
          placeholder="Enter theme name"
          class="form-input"
        />
      </div>

      <div class="color-sections">
        <!-- General Colors -->
        <div class="color-section">
          <h4>General Colors</h4>
          <div class="color-grid">
            <div class="color-item">
              <label>Primary Color</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.primary" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.primary" 
                  type="text" 
                  class="color-input"
                  placeholder="#e0e0e0"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Text Color</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.text" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.text" 
                  type="text" 
                  class="color-input"
                  placeholder="#e0e0e0"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Background</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.background" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.background" 
                  type="text" 
                  class="color-input"
                  placeholder="#000000"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Container Background</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.container" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.container" 
                  type="text" 
                  class="color-input"
                  placeholder="#2a2a2a"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Border Color</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.border" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.border" 
                  type="text" 
                  class="color-input"
                  placeholder="#404040"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Link Color</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.link" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.link" 
                  type="text" 
                  class="color-input"
                  placeholder="#2196f3"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Form Elements -->
        <div class="color-section">
          <h4>Form Elements</h4>
          <div class="color-grid">
            <div class="color-item">
              <label>Input Background</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.input" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.input" 
                  type="text" 
                  class="color-input"
                  placeholder="#1a1a1a"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Input Text</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.inputText" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.inputText" 
                  type="text" 
                  class="color-input"
                  placeholder="#e0e0e0"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Menu Background</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.menu" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.menu" 
                  type="text" 
                  class="color-input"
                  placeholder="#2a2a2a"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Table Colors -->
        <div class="color-section">
          <h4>Table Colors</h4>
          <div class="color-grid">
            <div class="color-item">
              <label>Table Header</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.tableHeader" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.tableHeader" 
                  type="text" 
                  class="color-input"
                  placeholder="#404040"
                />
              </div>
            </div>
            <div class="color-item">
              <label>Table Background</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.tableBg" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.tableBg" 
                  type="text" 
                  class="color-input"
                  placeholder="#1a1a1a"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Local Node Table Colors -->
        <div class="color-section">
          <h4>Local Node Table Colors</h4>
          <div class="color-grid">
            <div class="color-item">
              <label>Node Background</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.localNodeBg" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.localNodeBg" 
                  type="text" 
                  class="color-input"
                  placeholder="#2a2a2a"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Node Text</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.localNodeText" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.localNodeText" 
                  type="text" 
                  class="color-input"
                  placeholder="#e0e0e0"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Node Border</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.localNodeBorder" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.localNodeBorder" 
                  type="text" 
                  class="color-input"
                  placeholder="#404040"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Node Header</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.localNodeHeader" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.localNodeHeader" 
                  type="text" 
                  class="color-input"
                  placeholder="#404040"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Node Header Text</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.localNodeHeaderText" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.localNodeHeaderText" 
                  type="text" 
                  class="color-input"
                  placeholder="#e0e0e0"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Status Colors -->
        <div class="color-section">
          <h4>Status Colors</h4>
          <div class="color-grid">
            <div class="color-item">
              <label>Success</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.success" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.success" 
                  type="text" 
                  class="color-input"
                  placeholder="#4caf50"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Warning</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.warning" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.warning" 
                  type="text" 
                  class="color-input"
                  placeholder="#ff9800"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Error</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.error" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.error" 
                  type="text" 
                  class="color-input"
                  placeholder="#f44336"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- COS/PTT Status Colors -->
        <div class="color-section">
          <h4>COS/PTT Status Colors</h4>
          <p class="section-description">These colors control the visual status indicators for node states. Choose colors with good contrast for readability.</p>
          <div class="color-grid">
            <div class="color-item">
              <label>Idle Status</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.statusIdle" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.statusIdle" 
                  type="text" 
                  class="color-input"
                  placeholder="#2a2a2a"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>PTT-Keyed Status</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.statusPtt" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.statusPtt" 
                  type="text" 
                  class="color-input"
                  placeholder="#4a90e2"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>COS-Detected Status</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.statusCos" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.statusCos" 
                  type="text" 
                  class="color-input"
                  placeholder="#17a2b8"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Full-Duplex Status</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.statusFullDuplex" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.statusFullDuplex" 
                  type="text" 
                  class="color-input"
                  placeholder="#e74c3c"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>Receiving Status</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.statusReceiving" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.statusReceiving" 
                  type="text" 
                  class="color-input"
                  placeholder="#9b59b6"
                />
              </div>
            </div>
            
            <div class="color-item">
              <label>First Node Status</label>
              <div class="color-picker-wrapper">
                <input 
                  v-model="themeData.colors.statusFirstNode" 
                  type="color" 
                  class="color-picker"
                />
                <input 
                  v-model="themeData.colors.statusFirstNode" 
                  type="text" 
                  class="color-input"
                  placeholder="#6c757d"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Theme Preview -->
      <div class="theme-preview">
        <h4>Theme Preview</h4>
        <div class="preview-container" :style="previewStyles">
          <div class="preview-header">
            <span>Sample Header</span>
          </div>
          <div class="preview-content">
            <div class="preview-text">Sample text content</div>
            <div class="preview-link">Sample link</div>
            <div class="preview-button">Sample Button</div>
          </div>
          <div class="preview-table">
            <div class="preview-table-header">Table Header</div>
            <div class="preview-table-row">Table Row Content</div>
          </div>
          
          <!-- Status Color Preview -->
          <div class="preview-status-colors">
            <h5>Status Colors Preview</h5>
            <div class="status-preview-grid">
              <div class="status-preview-item" :style="{ backgroundColor: themeData.colors.statusIdle, color: themeData.colors.text }">
                <div>Idle</div>
                <div class="contrast-indicator" :style="{ color: getContrastStatus(getContrastRatio(themeData.colors.statusIdle, themeData.colors.text)).color }">
                  {{ getContrastStatus(getContrastRatio(themeData.colors.statusIdle, themeData.colors.text)).status }}
                </div>
              </div>
              <div class="status-preview-item" :style="{ backgroundColor: themeData.colors.statusPtt, color: themeData.colors.text }">
                <div>PTT</div>
                <div class="contrast-indicator" :style="{ color: getContrastStatus(getContrastRatio(themeData.colors.statusPtt, themeData.colors.text)).color }">
                  {{ getContrastStatus(getContrastRatio(themeData.colors.statusPtt, themeData.colors.text)).status }}
                </div>
              </div>
              <div class="status-preview-item" :style="{ backgroundColor: themeData.colors.statusCos, color: themeData.colors.text }">
                <div>COS</div>
                <div class="contrast-indicator" :style="{ color: getContrastStatus(getContrastRatio(themeData.colors.statusCos, themeData.colors.text)).color }">
                  {{ getContrastStatus(getContrastRatio(themeData.colors.statusCos, themeData.colors.text)).status }}
                </div>
              </div>
              <div class="status-preview-item" :style="{ backgroundColor: themeData.colors.statusFullDuplex, color: themeData.colors.text }">
                <div>Full</div>
                <div class="contrast-indicator" :style="{ color: getContrastStatus(getContrastRatio(themeData.colors.statusFullDuplex, themeData.colors.text)).color }">
                  {{ getContrastStatus(getContrastRatio(themeData.colors.statusFullDuplex, themeData.colors.text)).status }}
                </div>
              </div>
              <div class="status-preview-item" :style="{ backgroundColor: themeData.colors.statusReceiving, color: themeData.colors.text }">
                <div>RX</div>
                <div class="contrast-indicator" :style="{ color: getContrastStatus(getContrastRatio(themeData.colors.statusReceiving, themeData.colors.text)).color }">
                  {{ getContrastStatus(getContrastRatio(themeData.colors.statusReceiving, themeData.colors.text)).status }}
                </div>
              </div>
              <div class="status-preview-item" :style="{ backgroundColor: themeData.colors.statusFirstNode, color: themeData.colors.text }">
                <div>First</div>
                <div class="contrast-indicator" :style="{ color: getContrastStatus(getContrastRatio(themeData.colors.statusFirstNode, themeData.colors.text)).color }">
                  {{ getContrastStatus(getContrastRatio(themeData.colors.statusFirstNode, themeData.colors.text)).status }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button type="button" @click="$emit('close')" class="cancel-btn">
          Cancel
        </button>
        <button type="submit" class="save-btn">
          {{ isEditing ? 'Update Theme' : 'Create Theme' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useTheme, type Theme } from '@/composables/useTheme'

interface Props {
  editingTheme?: Theme
}

const props = withDefaults(defineProps<Props>(), {
  editingTheme: undefined
})

const emit = defineEmits<{
  close: []
  saved: [Theme]
}>()

const { createCustomTheme, updateCustomTheme } = useTheme()

// Utility function to calculate contrast ratio
const getContrastRatio = (color1: string, color2: string): number => {
  const getLuminance = (color: string): number => {
    const hex = color.replace('#', '')
    const r = parseInt(hex.substr(0, 2), 16) / 255
    const g = parseInt(hex.substr(2, 2), 16) / 255
    const b = parseInt(hex.substr(4, 2), 16) / 255
    
    const [rs, gs, bs] = [r, g, b].map(c => 
      c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4)
    )
    
    return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs
  }
  
  const lum1 = getLuminance(color1)
  const lum2 = getLuminance(color2)
  const brightest = Math.max(lum1, lum2)
  const darkest = Math.min(lum1, lum2)
  
  return (brightest + 0.05) / (darkest + 0.05)
}

// Check contrast for text readability
const getContrastStatus = (ratio: number): { status: string; color: string } => {
  if (ratio >= 7) return { status: 'Excellent', color: '#4caf50' }
  if (ratio >= 4.5) return { status: 'Good', color: '#8bc34a' }
  if (ratio >= 3) return { status: 'Fair', color: '#ff9800' }
  return { status: 'Poor', color: '#f44336' }
}

const isEditing = computed(() => !!props.editingTheme)

const themeData = ref({
  label: '',
  colors: {
    primary: '#e0e0e0',
    text: '#e0e0e0',
    background: '#000000',
    container: '#2a2a2a',
    border: '#404040',
    input: '#1a1a1a',
    inputText: '#e0e0e0',
    tableHeader: '#404040',
    success: '#4caf50',
    warning: '#ff9800',
    error: '#f44336',
    link: '#2196f3',
    menu: '#2a2a2a',
    localNodeBg: '#2a2a2a',
    localNodeText: '#e0e0e0',
    localNodeBorder: '#404040',
    localNodeHeader: '#404040',
    localNodeHeaderText: '#e0e0e0',
    tableBg: '#1a1a1a',
    // COS/PTT Status Colors
    statusIdle: '#2a2a2a',
    statusPtt: '#4a90e2',
    statusCos: '#17a2b8',
    statusFullDuplex: '#e74c3c',
    statusReceiving: '#9b59b6',
    statusFirstNode: '#6c757d'
  }
})

const previewStyles = computed(() => ({
  '--preview-primary': themeData.value.colors.primary,
  '--preview-text': themeData.value.colors.text,
  '--preview-bg': themeData.value.colors.background,
  '--preview-container': themeData.value.colors.container,
  '--preview-border': themeData.value.colors.border,
  '--preview-link': themeData.value.colors.link,
  '--preview-header': themeData.value.colors.tableHeader,
  '--preview-success': themeData.value.colors.success
}))

onMounted(() => {
  if (props.editingTheme) {
    themeData.value = {
      label: props.editingTheme.label,
      colors: { ...props.editingTheme.colors }
    }
  }
})

const saveTheme = () => {
  if (isEditing.value && props.editingTheme) {
    // Update existing theme
    const success = updateCustomTheme(props.editingTheme.name, {
      label: themeData.value.label,
      colors: themeData.value.colors
    })
    if (success) {
      emit('saved', props.editingTheme)
      emit('close')
    }
  } else {
    // Create new theme
    const newTheme = createCustomTheme(themeData.value)
    emit('saved', newTheme)
    emit('close')
  }
}
</script>

<style scoped>
.custom-theme-builder {
  background-color: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 1.5rem;
  max-width: 800px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  color: var(--text-color);
}

.builder-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--border-color);
}

.builder-header h3 {
  margin: 0;
  font-size: 1.25rem;
  color: var(--text-color);
}

.close-btn {
  background: none;
  border: none;
  color: var(--text-color);
  font-size: 1.5rem;
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 4px;
  transition: background-color 0.2s ease;
}

.close-btn:hover {
  background-color: var(--border-color);
}

.theme-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  font-weight: 500;
  color: var(--text-color);
}

.form-input {
  padding: 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background-color: var(--input-bg);
  color: var(--input-text);
  font-size: 1rem;
}

.color-sections {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.color-section {
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 1rem;
}

.color-section h4 {
  margin: 0 0 1rem 0;
  color: var(--text-color);
  font-size: 1.1rem;
}

.color-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.color-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.color-item label {
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--text-color);
}

.color-picker-wrapper {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.color-picker {
  width: 50px;
  height: 40px;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  cursor: pointer;
  background: none;
}

.color-input {
  flex: 1;
  padding: 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  background-color: var(--input-bg);
  color: var(--input-text);
  font-family: monospace;
  font-size: 0.9rem;
}

.theme-preview {
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 1rem;
}

.theme-preview h4 {
  margin: 0 0 1rem 0;
  color: var(--text-color);
  font-size: 1.1rem;
}

.preview-container {
  background-color: var(--preview-bg);
  color: var(--preview-text);
  border: 1px solid var(--preview-border);
  border-radius: 6px;
  padding: 1rem;
  font-size: 0.9rem;
}

.preview-header {
  background-color: var(--preview-header);
  color: var(--preview-text);
  padding: 0.5rem;
  border-radius: 4px;
  margin-bottom: 1rem;
  font-weight: 500;
}

.preview-content {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.preview-link {
  color: var(--preview-link);
  text-decoration: underline;
  cursor: pointer;
}

.preview-button {
  background-color: var(--preview-container);
  color: var(--preview-text);
  border: 1px solid var(--preview-border);
  padding: 0.5rem 1rem;
  border-radius: 4px;
  display: inline-block;
  width: fit-content;
  cursor: pointer;
}

.preview-table {
  border: 1px solid var(--preview-border);
  border-radius: 4px;
  overflow: hidden;
}

.preview-table-header {
  background-color: var(--preview-header);
  color: var(--preview-text);
  padding: 0.5rem;
  font-weight: 500;
}

.preview-table-row {
  background-color: var(--preview-container);
  color: var(--preview-text);
  padding: 0.5rem;
}

.section-description {
  font-size: 0.9rem;
  color: var(--text-color);
  opacity: 0.8;
  margin: 0 0 1rem 0;
  line-height: 1.4;
}

.preview-status-colors {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--preview-border);
}

.preview-status-colors h5 {
  margin: 0 0 0.5rem 0;
  font-size: 0.9rem;
  color: var(--preview-text);
}

.status-preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
  gap: 0.5rem;
}

.status-preview-item {
  padding: 0.5rem;
  border-radius: 4px;
  text-align: center;
  font-size: 0.8rem;
  font-weight: 500;
  border: 1px solid var(--preview-border);
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.contrast-indicator {
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  opacity: 0.8;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-color);
}

.cancel-btn {
  background-color: var(--border-color);
  color: var(--text-color);
  border: 1px solid var(--border-color);
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.cancel-btn:hover {
  background-color: var(--input-bg);
}

.save-btn {
  background-color: var(--success-color);
  color: white;
  border: 1px solid var(--success-color);
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-weight: 500;
}

.save-btn:hover {
  background-color: var(--warning-color);
  border-color: var(--warning-color);
}

@media (max-width: 768px) {
  .custom-theme-builder {
    padding: 1rem;
    max-width: 95vw;
  }
  
  .color-grid {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column;
  }
}
</style>
