<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="open" class="delete-favorite-modal">
        <div class="delete-favorite-overlay" @click="closeModal"></div>
        <div class="delete-favorite-content">
          <div class="delete-favorite-header">
            <h2>{{ title }}</h2>
            <button class="close-button" @click="closeModal">&times;</button>
          </div>

          <div class="delete-favorite-body">
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
                      <span v-else class="no-node">N/A</span>
                    </td>
                    <td>
                      <button 
                        type="button" 
                        class="delete-btn" 
                        @click="confirmDelete(favorite)"
                        :disabled="saving"
                      >
                        {{ saving ? 'Deleting...' : 'Delete' }}
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="delete-favorite-footer">
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
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'

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

interface DeleteResult {
  success: boolean
  message: string
  deleted_label?: string
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:open': [value: boolean]
  'favorite-deleted': [result: DeleteResult]
}>()

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const result = ref<DeleteResult | null>(null)
const favorites = ref<Favorite[]>([])
const currentUser = ref('')
const fileName = ref('')

const title = computed(() => {
  if (result.value && result.value.success) {
    return 'Favorite Deleted Successfully'
  }
  if (error.value) {
    return 'Error Loading Favorites'
  }
  if (favorites.value.length === 0 && !loading.value) {
    return 'No Favorites Found'
  }
  return 'Delete Favorite'
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

const confirmDelete = async (favorite: Favorite) => {
  if (!confirm(`Are you sure you want to delete the favorite "${favorite.label}"?`)) {
    return
  }
  
  await deleteFavorite(favorite)
}

const deleteFavorite = async (favorite: Favorite) => {
  saving.value = true
  error.value = ''
  
  try {
    const response = await axios.delete('/api/config/favorites', {
      data: {
        section: favorite.section,
        index: favorite.index
      },
      withCredentials: true
    })
    
    if (response.data.success) {
      result.value = response.data
      // Reload favorites after deletion
      await loadFavorites()
      emit('favorite-deleted', response.data)
    } else {
      error.value = response.data.message || 'Failed to delete favorite'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to delete favorite'
  } finally {
    saving.value = false
  }
}

const closeModal = () => {
  emit('update:open', false)
}

const refreshParent = () => {
  window.location.reload()
  closeModal()
}

// Load favorites when modal opens
watch(() => props.open, (newOpen) => {
  if (newOpen) {
    // Reset state when modal opens
    error.value = ''
    result.value = null
    favorites.value = []
    currentUser.value = ''
    fileName.value = ''
    loadFavorites()
  }
})
</script>

<style scoped>
.delete-favorite-modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.delete-favorite-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.7);
}

.delete-favorite-content {
  position: relative;
  background-color: #1A1A1A;
  border: 1px solid #374151;
  border-radius: 8px;
  width: 90%;
  max-width: 900px;
  max-height: 90vh;
  overflow-y: auto;
  color: #E0E0E0;
}

.delete-favorite-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 2px solid #374151;
}

.delete-favorite-header h2 {
  margin: 0;
  color: #4A90E2;
}

.close-button {
  background: none;
  border: none;
  color: #E0E0E0;
  font-size: 24px;
  cursor: pointer;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-button:hover {
  color: #EF4444;
}

.delete-favorite-body {
  padding: 20px;
}

.loading, .error-message, .success-message, .empty-state {
  text-align: center;
  padding: 40px 20px;
}

.error-message {
  background-color: rgba(239, 68, 68, 0.1);
  border: 1px solid #EF4444;
  color: #EF4444;
  border-radius: 4px;
}

.success-message {
  background-color: rgba(16, 185, 129, 0.1);
  border: 1px solid #10B981;
  color: #10B981;
  border-radius: 4px;
}

.empty-state h3 {
  color: #6B7280;
  margin-bottom: 10px;
}

.empty-state p {
  color: #9CA3AF;
  margin: 5px 0;
}

.favorites-info {
  margin-bottom: 20px;
  padding: 15px;
  background-color: #2A2A2A;
  border-radius: 4px;
}

.favorites-info h3 {
  margin: 0 0 10px 0;
  color: #4A90E2;
}

.favorites-info p {
  margin: 5px 0;
  color: #D1D5DB;
}

.favorites-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
}

.favorites-table th,
.favorites-table td {
  padding: 10px;
  text-align: left;
  border-bottom: 1px solid #374151;
}

.favorites-table th {
  background-color: #2A2A2A;
  font-weight: bold;
  color: #4A90E2;
}

.favorites-table tr:hover {
  background-color: #2A2A2A;
}

.section-badge {
  background-color: #4A90E2;
  color: white;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: bold;
}

.section-badge.general {
  background-color: #10B981;
}

.section-badge.node {
  background-color: #F59E0B;
}

.no-node {
  color: #6B7280;
}

.command-preview {
  font-family: monospace;
  background-color: #2A2A2A;
  padding: 5px 8px;
  border-radius: 4px;
  font-size: 12px;
  color: #D1D5DB;
}

.delete-btn {
  background-color: #EF4444;
  color: white;
  border: none;
  padding: 5px 10px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.delete-btn:hover:not(:disabled) {
  background-color: #DC2626;
}

.delete-btn:disabled {
  background-color: #6B7280;
  cursor: not-allowed;
}

.delete-favorite-footer {
  padding: 20px;
  border-top: 1px solid #374151;
}

.button-group {
  text-align: center;
}

.button-group input[type="button"] {
  background-color: #4A90E2;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 8px;
  cursor: pointer;
  margin: 0 8px;
  font-weight: bold;
  transition: all 0.3s ease;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  min-width: 120px;
  max-width: 200px;
}

.button-group input[type="button"]:hover {
  background-color: #5BA0F2;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

.refresh-btn {
  background-color: #10B981 !important;
}

.refresh-btn:hover {
  background-color: #059669 !important;
}

.close-btn {
  background-color: #6B7280 !important;
}

.close-btn:hover {
  background-color: #4B5563 !important;
}

/* Modal transitions */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
