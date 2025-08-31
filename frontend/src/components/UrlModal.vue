<template>
  <div v-if="isVisible" class="url-modal-overlay" @click="closeModal">
    <div class="url-modal-content" @click.stop>
      <div class="url-modal-header">
        <h3>{{ title }}</h3>
        <button class="url-modal-close" @click="closeModal">&times;</button>
      </div>
      <div class="url-modal-body">
        <iframe 
          v-if="url" 
          :src="url" 
          class="url-iframe"
          frameborder="0"
          allowfullscreen
        ></iframe>
        <div v-else class="url-loading">
          Loading...
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  isVisible: boolean
  url?: string
  title?: string
}

const props = withDefaults(defineProps<Props>(), {
  title: 'External Content'
})

const emit = defineEmits<{
  'update:isVisible': [value: boolean]
}>()

const closeModal = () => {
  emit('update:isVisible', false)
}
</script>

<style scoped>
.url-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.8);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.url-modal-content {
  background-color: var(--background-color);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  width: 90%;
  max-width: 1200px;
  height: 80%;
  max-height: 800px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.url-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  border-bottom: 1px solid var(--border-color);
  background-color: var(--container-bg);
}

.url-modal-header h3 {
  margin: 0;
  color: var(--text-color);
  font-size: 18px;
}

.url-modal-close {
  background: none;
  border: none;
  color: var(--text-color);
  font-size: 24px;
  cursor: pointer;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  transition: background-color 0.3s ease;
}

.url-modal-close:hover {
  background-color: var(--border-color);
}

.url-modal-body {
  flex: 1;
  overflow: hidden;
  position: relative;
}

.url-iframe {
  width: 100%;
  height: 100%;
  border: none;
}

.url-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100%;
  color: var(--text-color);
  font-size: 16px;
}

/* Responsive design */
@media (max-width: 768px) {
  .url-modal-content {
    width: 95%;
    height: 90%;
  }
  
  .url-modal-header {
    padding: 10px 15px;
  }
  
  .url-modal-header h3 {
    font-size: 16px;
  }
}
</style>
