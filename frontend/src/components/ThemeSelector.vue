<template>
  <div class="theme-selector">
    <div class="theme-header">
      <h3>Theme Settings</h3>
      <button @click="$emit('close')" class="close-btn" title="Close">
        ×
      </button>
    </div>
    
    <div class="theme-tabs">
      <button 
        @click="activeTab = 'themes'" 
        :class="{ active: activeTab === 'themes' }"
        class="tab-btn"
      >
        Themes
      </button>
      <button 
        @click="activeTab = 'custom'" 
        :class="{ active: activeTab === 'custom' }"
        class="tab-btn"
      >
        Custom Themes
      </button>
    </div>
    
    <!-- Built-in Themes Tab -->
    <div v-if="activeTab === 'themes'" class="theme-options">
      <div 
        v-for="theme in builtInThemes" 
        :key="theme.name"
        class="theme-option"
        :class="{ active: currentTheme === theme.name }"
        @click="selectTheme(theme.name)"
      >
        <div class="theme-preview">
          <div class="preview-header" :style="{ backgroundColor: theme.colors.tableHeader }"></div>
          <div class="preview-content" :style="{ backgroundColor: theme.colors.container }"></div>
          <div class="preview-accent" :style="{ backgroundColor: theme.colors.primary }"></div>
        </div>
        <div class="theme-info">
          <span class="theme-name">{{ theme.label }}</span>
          <span v-if="currentTheme === theme.name" class="current-badge">Current</span>
        </div>
      </div>
    </div>
    
    <!-- Custom Themes Tab -->
    <div v-if="activeTab === 'custom'" class="custom-themes-section">
      <div class="custom-themes-header">
        <h4>Custom Themes</h4>
        <button @click="showCustomBuilder = true" class="create-btn">
          + Create New Theme
        </button>
      </div>
      
      <div v-if="customThemes.length === 0" class="no-custom-themes">
        <p>No custom themes yet. Create your first custom theme!</p>
      </div>
      
      <div v-else class="theme-options">
        <div 
          v-for="theme in customThemes" 
          :key="theme.name"
          class="theme-option custom-theme"
          :class="{ active: currentTheme === theme.name }"
        >
          <div class="theme-preview">
            <div class="preview-header" :style="{ backgroundColor: theme.colors.tableHeader }"></div>
            <div class="preview-content" :style="{ backgroundColor: theme.colors.container }"></div>
            <div class="preview-accent" :style="{ backgroundColor: theme.colors.primary }"></div>
          </div>
          <div class="theme-info">
            <span class="theme-name">{{ theme.label }}</span>
            <span v-if="currentTheme === theme.name" class="current-badge">Current</span>
          </div>
          <div class="theme-actions">
            <button 
              @click.stop="selectTheme(theme.name)"
              class="select-btn"
              :disabled="currentTheme === theme.name"
            >
              {{ currentTheme === theme.name ? 'Selected' : 'Select' }}
            </button>
            <button 
              @click.stop="editTheme(theme)"
              class="edit-btn"
              title="Edit Theme"
            >
              ✏️
            </button>
            <button 
              @click.stop="deleteTheme(theme.name)"
              class="delete-btn"
              title="Delete Theme"
            >
              🗑️
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <div class="theme-footer">
      <button @click="$emit('close')" class="cancel-btn">Close</button>
    </div>
    
    <!-- Custom Theme Builder Modal -->
    <div v-if="showCustomBuilder" class="modal-overlay" @click="showCustomBuilder = false">
      <div class="modal-content" @click.stop>
        <CustomThemeBuilder 
          :editing-theme="editingTheme"
          @close="closeCustomBuilder"
          @saved="onThemeSaved"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useTheme, type Theme } from '@/composables/useTheme'
import CustomThemeBuilder from './CustomThemeBuilder.vue'

const { 
  currentTheme, 
  builtInThemes, 
  customThemes, 
  setTheme, 
  deleteCustomTheme 
} = useTheme()

const activeTab = ref<'themes' | 'custom'>('themes')
const showCustomBuilder = ref(false)
const editingTheme = ref<Theme | undefined>(undefined)

const selectTheme = (themeName: string) => {
  setTheme(themeName)
}

const editTheme = (theme: Theme) => {
  editingTheme.value = theme
  showCustomBuilder.value = true
}

const deleteTheme = (themeName: string) => {
  if (confirm('Are you sure you want to delete this custom theme?')) {
    deleteCustomTheme(themeName)
  }
}

const closeCustomBuilder = () => {
  showCustomBuilder.value = false
  editingTheme.value = undefined
}

const onThemeSaved = (theme: Theme) => {
  // Switch to the newly created/updated theme
  setTheme(theme.name)
  closeCustomBuilder()
}

defineEmits<{
  close: []
}>()
</script>

<style scoped>
.theme-selector {
  background-color: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 1.5rem;
  max-width: 600px;
  width: 100%;
  color: var(--text-color);
}

.theme-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--border-color);
}

.theme-header h3 {
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

.theme-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 1rem;
}

.tab-btn {
  background: none;
  border: none;
  color: var(--text-color);
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-weight: 500;
}

.tab-btn:hover {
  background-color: var(--border-color);
}

.tab-btn.active {
  background-color: var(--primary-color);
  color: var(--background-color);
}

.theme-options {
  display: grid;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.theme-option {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border: 2px solid var(--border-color);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.theme-option:hover {
  border-color: var(--primary-color);
  background-color: var(--input-bg);
}

.theme-option.active {
  border-color: var(--primary-color);
  background-color: var(--input-bg);
}

.theme-option.custom-theme {
  position: relative;
}

.theme-preview {
  width: 60px;
  height: 40px;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.preview-header {
  height: 8px;
  flex-shrink: 0;
}

.preview-content {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.preview-accent {
  height: 4px;
  flex-shrink: 0;
}

.theme-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.theme-name {
  font-weight: 500;
  color: var(--text-color);
}

.current-badge {
  font-size: 0.75rem;
  color: var(--success-color);
  font-weight: 500;
}

.theme-actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.select-btn {
  background-color: var(--primary-color);
  color: var(--background-color);
  border: 1px solid var(--primary-color);
  padding: 0.25rem 0.75rem;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.8rem;
  transition: all 0.2s ease;
}

.select-btn:hover:not(:disabled) {
  background-color: var(--border-color);
  border-color: var(--border-color);
}

.select-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.edit-btn, .delete-btn {
  background: none;
  border: none;
  font-size: 1rem;
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 4px;
  transition: background-color 0.2s ease;
}

.edit-btn:hover {
  background-color: var(--border-color);
}

.delete-btn:hover {
  background-color: var(--error-color);
  color: white;
}

.custom-themes-section {
  margin-bottom: 1.5rem;
}

.custom-themes-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.custom-themes-header h4 {
  margin: 0;
  color: var(--text-color);
  font-size: 1.1rem;
}

.create-btn {
  background-color: var(--success-color);
  color: white;
  border: 1px solid var(--success-color);
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  transition: all 0.2s ease;
}

.create-btn:hover {
  background-color: var(--warning-color);
  border-color: var(--warning-color);
}

.no-custom-themes {
  text-align: center;
  padding: 2rem;
  color: var(--text-color);
  opacity: 0.7;
}

.no-custom-themes p {
  margin: 0;
}

.theme-footer {
  display: flex;
  justify-content: flex-end;
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
  background-color: var(--primary-color);
  color: var(--background-color);
}

/* Modal overlay for custom theme builder */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: var(--spacing-md);
}

.modal-content {
  max-width: 90vw;
  max-height: 90vh;
  overflow: auto;
}

@media (max-width: 768px) {
  .theme-selector {
    padding: 1rem;
    max-width: 95vw;
  }
  
  .theme-tabs {
    flex-direction: column;
  }
  
  .theme-option {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .theme-actions {
    width: 100%;
    justify-content: flex-end;
  }
  
  .custom-themes-header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
}
</style>
