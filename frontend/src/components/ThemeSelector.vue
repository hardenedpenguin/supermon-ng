<template>
  <div class="theme-selector">
    <div class="theme-header">
      <h3>Theme Settings</h3>
      <button @click="$emit('close')" class="close-btn" title="Close">
        ×
      </button>
    </div>
    
    <div class="theme-options">
      <div 
        v-for="theme in themes" 
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
    
    <div class="theme-footer">
      <button @click="$emit('close')" class="cancel-btn">Cancel</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useTheme } from '@/composables/useTheme'

const { currentTheme, themes, setTheme } = useTheme()

const selectTheme = (themeName: string) => {
  setTheme(themeName)
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
  max-width: 500px;
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

.theme-preview {
  width: 60px;
  height: 40px;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
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
</style>
