<template>
  <div v-if="isVisible" class="modal-overlay" @click="close">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>Digital Dashboard</h3>
        <button class="close-button" @click="close">&times;</button>
      </div>

      <div class="modal-body">
        <div v-if="!url" class="error-message">
          Digital Dashboard URL is not configured.
        </div>
        <iframe v-else :src="url" class="iframe" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  isVisible: { type: Boolean, default: false },
  url: { type: String, default: '' }
})

const emit = defineEmits(['update:isVisible'])

const close = () => emit('update:isVisible', false)

// Normalize relative URLs like ../dvswitch → /dvswitch
const url = computed(() => {
  if (!props.url) return ''
  try {
    // If absolute, return as-is
    const u = new URL(props.url, window.location.origin)
    return u.toString()
  } catch {
    return props.url
  }
})
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
  max-width: 1200px;
  max-height: 90vh;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  border: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--border-color);
  background-color: var(--container-bg);
}

.close-button {
  background: none;
  border: none;
  color: var(--text-color);
  font-size: 1.5rem;
  cursor: pointer;
}

.modal-body {
  flex: 1;
  padding: 0;
}

.iframe {
  width: 100%;
  height: 80vh;
  border: none;
}

.error-message {
  padding: 1rem;
  color: var(--error-color);
}
</style>


