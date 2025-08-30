<template>
  <div class="theme-selector">
    <div class="theme-selector-header">
      <h3>Theme Settings</h3>
      <button class="close-button" @click="$emit('close')">&times;</button>
    </div>
    
    <div class="theme-grid">
      <div
        v-for="theme in availableThemes"
        :key="theme.name"
        class="theme-option"
        :class="{ active: currentTheme === theme.name }"
        @click="changeTheme(theme.name)"
      >
        <div class="theme-preview" :style="{ backgroundColor: theme.preview }"></div>
        <div class="theme-info">
          <h4>{{ theme.label }}</h4>
          <p>{{ theme.description }}</p>
        </div>
        <div v-if="currentTheme === theme.name" class="theme-active-indicator">
          ✓
        </div>
      </div>
    </div>

    <!-- Custom Theme Editor -->
    <div v-if="currentTheme === 'custom'" class="custom-theme-editor">
      <h4>Custom Theme Editor</h4>
      <p>Customize your theme colors below:</p>
      
      <div class="color-inputs">
        <div class="color-input-group">
          <label>Primary Color</label>
          <input
            v-model="customColors.primaryColor"
            type="color"
            @change="updateCustomTheme"
          />
        </div>
        
        <div class="color-input-group">
          <label>Background Color</label>
          <input
            v-model="customColors.backgroundColor"
            type="color"
            @change="updateCustomTheme"
          />
        </div>
        
        <div class="color-input-group">
          <label>Text Color</label>
          <input
            v-model="customColors.textColor"
            type="color"
            @change="updateCustomTheme"
          />
        </div>
        
        <div class="color-input-group">
          <label>Container Background</label>
          <input
            v-model="customColors.containerBg"
            type="color"
            @change="updateCustomTheme"
          />
        </div>
        
        <div class="color-input-group">
          <label>Border Color</label>
          <input
            v-model="customColors.borderColor"
            type="color"
            @change="updateCustomTheme"
          />
        </div>
        
        <div class="color-input-group">
          <label>Button Background</label>
          <input
            v-model="customColors.buttonBg"
            type="color"
            @change="updateCustomTheme"
          />
        </div>
      </div>
      
      <div class="custom-theme-actions">
        <button @click="resetCustomTheme" class="btn-secondary">Reset to Default</button>
        <button @click="exportCustomTheme" class="btn-primary">Export Theme</button>
      </div>
    </div>

    <!-- Theme Import -->
    <div class="theme-import">
      <h4>Import Custom Theme</h4>
      <div class="import-controls">
        <input
          ref="fileInput"
          type="file"
          accept=".json,.css"
          @change="importTheme"
          style="display: none"
        />
        <button @click="$refs.fileInput.click()" class="btn-secondary">
          Import Theme File
        </button>
        <button @click="importFromClipboard" class="btn-secondary">
          Import from Clipboard
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useTheme, type Theme } from '@/composables/useTheme'

interface CustomColors {
  primaryColor: string
  backgroundColor: string
  textColor: string
  containerBg: string
  borderColor: string
  buttonBg: string
}

const emit = defineEmits<{
  close: []
}>()

const { currentTheme, availableThemes, changeTheme, setCustomThemeVariables } = useTheme()

const fileInput = ref<HTMLInputElement>()

const customColors = reactive<CustomColors>({
  primaryColor: '#e0e0e0',
  backgroundColor: '#000000',
  textColor: '#e0e0e0',
  containerBg: '#2a2a2a',
  borderColor: '#404040',
  buttonBg: '#404040'
})

const updateCustomTheme = () => {
  const variables = {
    'primary-color': customColors.primaryColor,
    'background-color': customColors.backgroundColor,
    'text-color': customColors.textColor,
    'container-bg': customColors.containerBg,
    'border-color': customColors.borderColor,
    'button-bg': customColors.buttonBg,
    'input-bg': customColors.backgroundColor,
    'input-text': customColors.textColor,
    'menu-background': customColors.containerBg,
    'modal-bg': customColors.backgroundColor,
    'card-bg': customColors.containerBg,
    'card-border': customColors.borderColor
  }
  
  setCustomThemeVariables(variables)
}

const resetCustomTheme = () => {
  Object.assign(customColors, {
    primaryColor: '#e0e0e0',
    backgroundColor: '#000000',
    textColor: '#e0e0e0',
    containerBg: '#2a2a2a',
    borderColor: '#404040',
    buttonBg: '#404040'
  })
  updateCustomTheme()
}

const exportCustomTheme = () => {
  const themeData = {
    name: 'Custom Theme',
    colors: customColors,
    timestamp: new Date().toISOString()
  }
  
  const blob = new Blob([JSON.stringify(themeData, null, 2)], {
    type: 'application/json'
  })
  
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'supermon-custom-theme.json'
  a.click()
  URL.revokeObjectURL(url)
}

const importTheme = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  
  if (file) {
    const reader = new FileReader()
    reader.onload = (e) => {
      try {
        const themeData = JSON.parse(e.target?.result as string)
        if (themeData.colors) {
          Object.assign(customColors, themeData.colors)
          updateCustomTheme()
          changeTheme('custom')
        }
      } catch (error) {
        console.error('Failed to import theme:', error)
        alert('Failed to import theme file. Please check the format.')
      }
    }
    reader.readAsText(file)
  }
}

const importFromClipboard = async () => {
  try {
    const text = await navigator.clipboard.readText()
    const themeData = JSON.parse(text)
    
    if (themeData.colors) {
      Object.assign(customColors, themeData.colors)
      updateCustomTheme()
      changeTheme('custom')
    }
  } catch (error) {
    console.error('Failed to import from clipboard:', error)
    alert('Failed to import theme from clipboard. Please check the format.')
  }
}
</script>

<style scoped>
.theme-selector {
  background: var(--modal-bg);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius-lg);
  padding: var(--spacing-lg);
  max-width: 800px;
  max-height: 80vh;
  overflow-y: auto;
}

.theme-selector-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--spacing-lg);
  padding-bottom: var(--spacing-md);
  border-bottom: 1px solid var(--border-color);
}

.theme-selector-header h3 {
  margin: 0;
  color: var(--text-color);
}

.close-button {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--text-color);
  cursor: pointer;
  padding: var(--spacing-xs);
  border-radius: var(--border-radius-sm);
  transition: background-color var(--transition-fast);
}

.close-button:hover {
  background-color: var(--button-hover);
}

.theme-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--spacing-md);
  margin-bottom: var(--spacing-lg);
}

.theme-option {
  background: var(--card-bg);
  border: 2px solid var(--card-border);
  border-radius: var(--border-radius-md);
  padding: var(--spacing-md);
  cursor: pointer;
  transition: all var(--transition-fast);
  position: relative;
}

.theme-option:hover {
  border-color: var(--primary-color);
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.theme-option.active {
  border-color: var(--success-color);
  box-shadow: 0 0 0 2px var(--success-color);
}

.theme-preview {
  width: 100%;
  height: 60px;
  border-radius: var(--border-radius-sm);
  margin-bottom: var(--spacing-sm);
}

.theme-info h4 {
  margin: 0 0 var(--spacing-xs) 0;
  color: var(--text-color);
  font-size: 1rem;
}

.theme-info p {
  margin: 0;
  color: var(--text-color);
  opacity: 0.7;
  font-size: 0.875rem;
  line-height: 1.4;
}

.theme-active-indicator {
  position: absolute;
  top: var(--spacing-sm);
  right: var(--spacing-sm);
  background: var(--success-color);
  color: white;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.875rem;
  font-weight: bold;
}

.custom-theme-editor {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius-md);
  padding: var(--spacing-lg);
  margin-bottom: var(--spacing-lg);
}

.custom-theme-editor h4 {
  margin: 0 0 var(--spacing-md) 0;
  color: var(--text-color);
}

.custom-theme-editor p {
  margin: 0 0 var(--spacing-lg) 0;
  color: var(--text-color);
  opacity: 0.7;
}

.color-inputs {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: var(--spacing-md);
  margin-bottom: var(--spacing-lg);
}

.color-input-group {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);
}

.color-input-group label {
  color: var(--text-color);
  font-size: 0.875rem;
  font-weight: 500;
}

.color-input-group input[type="color"] {
  width: 100%;
  height: 40px;
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius-sm);
  background: var(--input-bg);
  cursor: pointer;
}

.custom-theme-actions {
  display: flex;
  gap: var(--spacing-md);
  justify-content: flex-end;
}

.theme-import {
  background: var(--card-bg);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius-md);
  padding: var(--spacing-lg);
}

.theme-import h4 {
  margin: 0 0 var(--spacing-md) 0;
  color: var(--text-color);
}

.import-controls {
  display: flex;
  gap: var(--spacing-md);
  flex-wrap: wrap;
}

.btn-primary,
.btn-secondary {
  padding: var(--spacing-sm) var(--spacing-md);
  border: none;
  border-radius: var(--border-radius-sm);
  cursor: pointer;
  font-size: 0.875rem;
  font-weight: 500;
  transition: all var(--transition-fast);
}

.btn-primary {
  background: var(--button-bg);
  color: var(--text-color);
}

.btn-primary:hover {
  background: var(--button-hover);
}

.btn-secondary {
  background: var(--card-bg);
  color: var(--text-color);
  border: 1px solid var(--border-color);
}

.btn-secondary:hover {
  background: var(--button-hover);
}

@media (max-width: 768px) {
  .theme-grid {
    grid-template-columns: 1fr;
  }
  
  .color-inputs {
    grid-template-columns: 1fr;
  }
  
  .custom-theme-actions,
  .import-controls {
    flex-direction: column;
  }
}
</style>
