<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="open" class="favorites-modal">
        <div class="favorites-overlay" @click="closeModal"></div>
        <div class="favorites-content">
          <div class="favorites-header">
            <h2>{{ title }}</h2>
            <div class="header-actions">
              <button @click="showAddFavoriteModal = true" class="add-favorite-btn">
                Add Favorite
              </button>
              <button class="close-button" @click="closeModal">&times;</button>
            </div>
          </div>

          <div class="favorites-body">
            <!-- Loading state -->
            <div v-if="loading" class="loading">
              <p>Loading favorites...</p>
            </div>

            <!-- Error state -->
            <div v-else-if="error" class="error-message">
              <p>{{ error }}</p>
            </div>

            <!-- Success message -->
            <div v-else-if="result && result.success" class="success-message">
              <p>{{ result.message }}</p>
            </div>

            <!-- Empty state -->
            <div v-else-if="!loading && favorites.length === 0" class="empty-state">
              <h3>No Favorites Found</h3>
              <p>You don't have any favorites configured yet.</p>
              <p>Use the "Add Favorite" button to add nodes to your favorites list.</p>
            </div>

            <!-- Favorites table -->
            <div v-else class="favorites-section">
              <div class="favorites-info">
                <h3>Favorites File</h3>
                <p><strong>User:</strong> {{ currentUser }}</p>
                <p><strong>File:</strong> {{ fileName }}</p>
              </div>

              <table class="favorites-table">
                <thead>
                  <tr>
                    <th>Section</th>
                    <th>Label</th>
                    <th>Command</th>
                    <th>Node</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="favorite in favorites" :key="`${favorite.section}-${favorite.index}`">
                    <td>
                      <span :class="['section-badge', favorite.section === 'general' ? 'general' : 'node']">
                        {{ favorite.section }}
                      </span>
                    </td>
                    <td>{{ favorite.label }}</td>
                    <td>
                      <div class="command-preview">
                        {{ favorite.command }}
                      </div>
                    </td>
                    <td>
                      <span v-if="favorite.node" class="section-badge node">
                        {{ favorite.node }}
                      </span>
                      <span v-else class="no-target">-</span>
                    </td>
                    <td>
                      <button 
                        type="button" 
                        class="connect-btn" 
                        @click="executeCommand(favorite)"
                        :disabled="saving"
                      >
                        {{ saving ? 'Connecting...' : 'Connect' }}
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="favorites-footer">
            <div class="button-group">
              <input 
                v-if="result && result.success" 
                type="button" 
                value="Refresh Parent Window" 
                @click="refreshParent"
                class="refresh-btn"
              >
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

    <!-- Add Favorite Modal -->
    <AddFavorite
      v-model:isVisible="showAddFavoriteModal"
      :node-number="selectedNodeForAdd"
      @favorite-added="handleFavoriteAdded"
    />
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import AddFavorite from './AddFavorite.vue'

interface Props {
  open: boolean
}

interface Favorite {
  section: string
  index: number
  label: string
  command: string
  node: string | false
}

interface ExecuteResult {
  success: boolean
  message: string
  executed_label?: string
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:open': [value: boolean]
  'command-executed': [result: ExecuteResult]
}>()

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const result = ref<ExecuteResult | null>(null)
const favorites = ref<Favorite[]>([])
const currentUser = ref('')
const fileName = ref('')
const showAddFavoriteModal = ref(false)
const selectedNodeForAdd = ref('')

const title = computed(() => {
  if (result.value && result.value.success) {
    return 'Command Executed Successfully'
  }
  if (error.value) {
    return 'Error Loading Favorites'
  }
  if (favorites.value.length === 0 && !loading.value) {
    return 'No Favorites Found'
  }
  return 'Favorites Panel'
})

const loadFavorites = async () => {
  loading.value = true
  error.value = ''
  result.value = null
  
  try {
    const response = await axios.get('/api/config/favorites', { 
      withCredentials: true 
    })
    
    if (response.data.success) {
      favorites.value = response.data.data
      // Get user info from the API response
      currentUser.value = response.data.user || 'Unknown User'
      fileName.value = response.data.fileName || 'favorites.ini'
    } else {
      error.value = response.data.message || 'Failed to load favorites'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load favorites'
  } finally {
    loading.value = false
  }
}

const executeCommand = async (favorite: Favorite) => {
  // For general favorites, we need to prompt for a node number
  if (favorite.section === 'general') {
    const nodeNumber = prompt(`Enter node number for command "${favorite.label}":`)
    if (!nodeNumber) {
      return
    }
    await executeFavoriteCommand(favorite, nodeNumber)
  } else {
    // For node-specific favorites, use the node from the favorite
    if (favorite.node) {
      await executeFavoriteCommand(favorite, favorite.node)
    } else {
      error.value = 'No node specified for this favorite'
    }
  }
}

const executeFavoriteCommand = async (favorite: Favorite, node: string) => {
  saving.value = true
  error.value = ''
  
  try {
    const response = await axios.post('/api/config/favorites/execute', {
      node: node,
      command: favorite.command
    }, { 
      withCredentials: true 
    })
    
    result.value = {
      success: response.data.success,
      message: response.data.message,
      executed_label: favorite.label
    }
    
    emit('command-executed', result.value)
  } catch (err: any) {
    result.value = {
      success: false,
      message: err.response?.data?.message || 'Failed to execute command',
      executed_label: favorite.label
    }
  } finally {
    saving.value = false
  }
}

const refreshParent = () => {
  window.location.reload()
}

const handleFavoriteAdded = (favoriteResult: any) => {
  if (favoriteResult.success) {
    // Reload favorites after adding
    loadFavorites()
    // Show success message
    result.value = {
      success: true,
      message: favoriteResult.message,
      executed_label: favoriteResult.label
    }
  }
}

const closeModal = () => {
  emit('update:open', false)
  // Reset state
  loading.value = false
  saving.value = false
  error.value = ''
  result.value = null
  favorites.value = []
  currentUser.value = ''
  fileName.value = ''
}

// Watch for modal open state
watch(() => props.open, (newOpen) => {
  if (newOpen) {
    loadFavorites()
  }
})

// Load favorites when component mounts if modal is open
onMounted(() => {
  if (props.open) {
    loadFavorites()
  }
})
</script>

<style scoped>
.favorites-modal {
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

.favorites-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.7);
}

.favorites-content {
  position: relative;
  background-color: #1f2937;
  border: 1px solid #374151;
  border-radius: 8px;
  max-width: 900px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
  color: #f9fafb;
}

.favorites-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #374151;
  background-color: #111827;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.add-favorite-btn {
  background: #10b981;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: background-color 0.2s;
}

.add-favorite-btn:hover {
  background: #059669;
}

.favorites-header h2 {
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

.favorites-body {
  padding: 20px;
}

.loading, .error-message, .success-message, .empty-state {
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

.success-message p {
  color: #10b981;
  font-size: 1.1rem;
}

.empty-state h3 {
  color: #9ca3af;
  margin-bottom: 10px;
}

.empty-state p {
  color: #6b7280;
  margin-bottom: 5px;
}

.favorites-info {
  margin-bottom: 20px;
  padding: 15px;
  background-color: #111827;
  border-radius: 6px;
  border: 1px solid #374151;
}

.favorites-info h3 {
  margin: 0 0 10px 0;
  color: #f9fafb;
  font-size: 1.2rem;
}

.favorites-info p {
  margin: 5px 0;
  color: #d1d5db;
}

.favorites-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
  background-color: #111827;
  border-radius: 6px;
  overflow: hidden;
}

.favorites-table th {
  background-color: #374151;
  color: #f9fafb;
  padding: 12px;
  text-align: left;
  font-weight: 600;
  border-bottom: 1px solid #4b5563;
}

.favorites-table td {
  padding: 12px;
  border-bottom: 1px solid #374151;
  color: #d1d5db;
}

.favorites-table tr:hover {
  background-color: #1f2937;
}

.section-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
}

.section-badge.general {
  background-color: #059669;
  color: #ffffff;
}

.section-badge.node {
  background-color: #2563eb;
  color: #ffffff;
}

.no-node {
  color: #6b7280;
  font-style: italic;
}

.general-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  background-color: #7c3aed;
  color: #ffffff;
}

.prompt-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  background-color: #f59e0b;
  color: #ffffff;
}

.no-target {
  color: #6b7280;
  font-style: italic;
}

.command-preview {
  max-width: 200px;
  word-wrap: break-word;
  font-family: monospace;
  font-size: 0.9rem;
  color: #9ca3af;
}

.connect-btn {
  background-color: #059669;
  color: white;
  border: none;
  padding: 6px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 500;
}

.connect-btn:hover:not(:disabled) {
  background-color: #047857;
}

.connect-btn:disabled {
  background-color: #6b7280;
  cursor: not-allowed;
}

.favorites-footer {
  padding: 20px;
  border-top: 1px solid #374151;
  background-color: #111827;
}

.button-group {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.refresh-btn, .close-btn {
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

.refresh-btn:hover {
  background-color: #1d4ed8;
}

.close-btn {
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
