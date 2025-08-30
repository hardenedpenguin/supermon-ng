<template>
  <div id="app">
    <!-- Theme Toggle Button -->
    <div class="theme-toggle">
      <button @click="showThemeSelector = true" class="theme-toggle-btn" title="Theme Settings">
        🎨
      </button>
    </div>

    <!-- Main App Content -->
    <router-view />

    <!-- Theme Selector Modal -->
    <div v-if="showThemeSelector" class="modal-overlay" @click="showThemeSelector = false">
      <div class="modal-content" @click.stop>
        <ThemeSelector @close="showThemeSelector = false" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useTheme } from '@/composables/useTheme'
import ThemeSelector from '@/components/ThemeSelector.vue'

const showThemeSelector = ref(false)
const { loadTheme } = useTheme()

onMounted(() => {
  // Initialize theme
  loadTheme()
})
</script>

<style>
/* Global CSS Variables - These will be overridden by theme selectors */
:root {
  /* Default fallback values - will be overridden by [data-theme] selectors */
  --primary-color: #e0e0e0;
  --text-color: #e0e0e0;
  --background-color: #000000;
  --container-bg: #2a2a2a;
  --border-color: #404040;
  --input-bg: #1a1a1a;
  --input-text: #e0e0e0;
  --table-header-bg: #404040;
  --success-color: #4caf50;
  --warning-color: #ff9800;
  --error-color: #f44336;
  --link-color: #2196f3;
  --menu-background: #2a2a2a;
  --modal-bg: #1a1a1a;
  --modal-overlay: rgba(0, 0, 0, 0.8);
  --button-bg: #404040;
  --button-hover: #505050;
  --button-active: #606060;
  --card-bg: #2a2a2a;
  --card-border: #404040;
  --tooltip-bg: #1a1a1a;
  --tooltip-text: #e0e0e0;
}

/* Global styles */
* {
  box-sizing: border-box;
}

body {
  margin: 0;
  padding: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen',
    'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue',
    sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  background-color: var(--background-color);
  color: var(--text-color);
}

#app {
  min-height: 100vh;
  background-color: var(--background-color);
  color: var(--text-color);
  transition: background-color var(--transition-normal), color var(--transition-normal);
}

/* Utility classes */
.text-center {
  text-align: center;
}

.text-left {
  text-align: left;
}

.text-right {
  text-align: right;
}

.mt-1 { margin-top: 0.25rem; }
.mt-2 { margin-top: 0.5rem; }
.mt-3 { margin-top: 1rem; }
.mt-4 { margin-top: 1.5rem; }
.mt-5 { margin-top: 3rem; }

.mb-1 { margin-bottom: 0.25rem; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-3 { margin-bottom: 1rem; }
.mb-4 { margin-bottom: 1.5rem; }
.mb-5 { margin-bottom: 3rem; }

.p-1 { padding: 0.25rem; }
.p-2 { padding: 0.5rem; }
.p-3 { padding: 1rem; }
.p-4 { padding: 1.5rem; }
.p-5 { padding: 3rem; }

/* Responsive utilities */
@media (max-width: 768px) {
  .hide-mobile {
    display: none !important;
  }
}

@media (min-width: 769px) {
  .hide-desktop {
    display: none !important;
  }
}

.theme-toggle {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 1000;
}

.theme-toggle-btn {
  background: var(--button-bg);
  color: var(--text-color);
  border: 1px solid var(--border-color);
  border-radius: 50%;
  width: 50px;
  height: 50px;
  font-size: 1.5rem;
  cursor: pointer;
  transition: all var(--transition-fast);
  box-shadow: var(--shadow-md);
}

.theme-toggle-btn:hover {
  background: var(--button-hover);
  transform: scale(1.1);
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: var(--modal-overlay);
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
</style>


