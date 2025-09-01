<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>Select Node for AllStar Status</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>

      <div class="modal-body">
        <div class="node-selection-content">
          <p>Multiple nodes are currently displayed. Please select a node to view its AllStar status:</p>
          
          <div class="node-list">
            <div 
              v-for="node in availableNodes" 
              :key="node.id"
              class="node-option"
              :class="{ 'selected': selectedNodeId === node.id }"
              @click="selectNode(node.id)"
            >
              <span class="node-number">Node {{ node.id }}</span>
              <span v-if="node.description" class="node-description">- {{ node.description }}</span>
            </div>
          </div>

          <div class="modal-actions">
            <button @click="confirmSelection" class="confirm-button" :disabled="!selectedNodeId">
              View AllStar Status
            </button>
            <button @click="closeModal" class="cancel-button">
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  isVisible: {
    type: Boolean,
    default: false
  },
  availableNodes: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:isVisible', 'node-selected'])

const selectedNodeId = ref(null)

// Watch for modal visibility changes
watch(() => props.isVisible, (newVal) => {
  if (newVal && props.availableNodes.length > 0) {
    // Default to the first node
    selectedNodeId.value = props.availableNodes[0].id
  }
})

const closeModal = () => {
  emit('update:isVisible', false)
  selectedNodeId.value = null
}

const selectNode = (nodeId) => {
  selectedNodeId.value = nodeId
}

const confirmSelection = () => {
  if (selectedNodeId.value) {
    emit('node-selected', selectedNodeId.value)
    closeModal()
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background-color: #2c3e50;
  border: 2px solid #34495e;
  border-radius: 8px;
  max-width: 500px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-bottom: 2px solid #34495e;
  background-color: #34495e;
  border-radius: 6px 6px 0 0;
}

.modal-header h3 {
  margin: 0;
  color: #ecf0f1;
  font-size: 1.2rem;
  font-weight: bold;
}

.close-button {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #ecf0f1;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  transition: background-color 0.2s ease;
}

.close-button:hover {
  background-color: #e74c3c;
  color: white;
}

.modal-body {
  padding: 1.5rem;
  background-color: #2c3e50;
}

.node-selection-content p {
  margin-bottom: 1.5rem;
  color: #ecf0f1;
  font-size: 1rem;
  line-height: 1.4;
}

.node-list {
  margin-bottom: 2rem;
}

.node-option {
  padding: 1rem;
  border: 2px solid #34495e;
  border-radius: 6px;
  margin-bottom: 0.75rem;
  cursor: pointer;
  transition: all 0.3s ease;
  background-color: #34495e;
  color: #ecf0f1;
}

.node-option:hover {
  background-color: #3498db;
  border-color: #3498db;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

.node-option.selected {
  background-color: #27ae60;
  color: white;
  border-color: #27ae60;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
}

.node-number {
  font-weight: bold;
  margin-right: 0.5rem;
  font-size: 1.1rem;
}

.node-description {
  color: #bdc3c7;
  font-size: 0.95rem;
}

.node-option.selected .node-description {
  color: rgba(255, 255, 255, 0.9);
}

.modal-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  padding-top: 1rem;
  border-top: 1px solid #34495e;
}

.confirm-button, .cancel-button {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: bold;
  font-size: 1rem;
  transition: all 0.3s ease;
  min-width: 120px;
}

.confirm-button {
  background-color: #27ae60;
  color: white;
  box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3);
}

.confirm-button:hover:not(:disabled) {
  background-color: #2ecc71;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
}

.confirm-button:disabled {
  background-color: #7f8c8d;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.cancel-button {
  background-color: #e74c3c;
  color: white;
  box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
}

.cancel-button:hover {
  background-color: #c0392b;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
}
</style>
