<template>
  <div v-if="isVisible" class="dvswitch-modal-overlay" @click="closeModal">
    <div class="dvswitch-modal" @click.stop>
      <div class="dvswitch-modal-header">
        <h2>DVSwitch Mode Switcher</h2>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>
      
      <div class="dvswitch-modal-content">
        <!-- Loading State -->
        <div v-if="loading" class="loading-message">
          Loading modes...
        </div>
        
        <!-- Error Message -->
        <div v-if="error" class="error-message">
          {{ error }}
        </div>
        
        <!-- Node and Mode Selection -->
        <div v-if="!loading && !error" class="dvswitch-section">
          <!-- Node Selection -->
          <div class="dvswitch-input-section">
            <label for="dvswitch-node">Node:</label>
            <select 
              id="dvswitch-node"
              v-model="selectedNode" 
              @change="onNodeChange"
              :disabled="switching"
            >
              <option value="">Select a node</option>
              <option v-for="node in availableNodes" :key="node.id" :value="node.id">
                Node {{ node.id }} {{ node.system ? `(${node.system})` : '' }}
              </option>
            </select>
          </div>
          
          <!-- Mode Selection (only show after node is selected) -->
          <div v-if="selectedNode" class="dvswitch-input-section">
            <label for="dvswitch-mode">Mode:</label>
            <select 
              id="dvswitch-mode"
              v-model="selectedMode" 
              @change="onModeChange"
              :disabled="switching || loadingModes"
            >
              <option value="">{{ loadingModes ? 'Loading modes...' : 'Select a mode' }}</option>
              <option v-for="mode in modes" :key="mode.name" :value="mode.name">
                {{ mode.name }}
              </option>
            </select>
          </div>
          
          <!-- Talkgroup Selection -->
          <div v-if="selectedMode" class="dvswitch-input-section">
            <label for="dvswitch-talkgroup">Talkgroup:</label>
            <select 
              id="dvswitch-talkgroup"
              v-model="selectedTalkgroup" 
              @change="onTalkgroupChange"
              :disabled="switching"
            >
              <option value="">Select a talkgroup</option>
              <option v-for="tg in talkgroups" :key="tg.tgid" :value="tg.tgid">
                {{ tg.alias }} ({{ tg.tgid }})
              </option>
              <option value="__CUSTOM__">Custom TG</option>
            </select>
            
            <!-- Custom Talkgroup Input (shown when Custom TG is selected) -->
            <div v-if="selectedTalkgroup === '__CUSTOM__'" class="custom-tg-input">
              <input
                id="custom-talkgroup"
                v-model="customTalkgroup"
                type="text"
                placeholder="Enter custom talkgroup ID"
                :disabled="switching"
                class="custom-tg-field"
              />
            </div>
            
            <!-- TGIF Network Note -->
            <div v-if="effectiveTalkgroup && effectiveTalkgroup.includes('tgif.network')" class="tgif-note">
              <small>ℹ️ Note: TGIF Network requires a key-up (transmission) after switching talkgroups for the change to take effect on the network.</small>
            </div>
          </div>
          
          <!-- Action Buttons -->
          <div class="dvswitch-buttons">
            <button 
              @click="switchMode" 
              :disabled="!selectedNode || !selectedMode || switching"
              class="action-button"
            >
              {{ switching ? 'Switching...' : 'Switch Mode' }}
            </button>
            
            <button 
              v-if="selectedNode && selectedMode && effectiveTalkgroup"
              @click="switchTalkgroup" 
              :disabled="!effectiveTalkgroup || switching"
              class="action-button"
            >
              {{ switching ? 'Switching...' : 'Switch Talkgroup' }}
            </button>
            
            <button @click="closeModal" class="cancel-button">Cancel</button>
          </div>
          
          <!-- Success Message -->
          <div v-if="successMessage" class="success-message">
            {{ successMessage }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { api } from '@/utils/api'

interface Mode {
  name: string
  talkgroups: Talkgroup[]
}

interface Talkgroup {
  tgid: string
  alias: string
}

interface DvswitchNode {
  id: string
  host?: string
  system?: string
}

const props = defineProps<{
  isVisible: boolean
}>()

const emit = defineEmits<{
  'update:isVisible': [value: boolean]
}>()

const availableNodes = ref<DvswitchNode[]>([])
const modes = ref<Mode[]>([])
const talkgroups = ref<Talkgroup[]>([])
const selectedNode = ref('')
const selectedMode = ref('')
const selectedTalkgroup = ref('')
const customTalkgroup = ref('')
const loading = ref(false)
const loadingModes = ref(false)
const switching = ref(false)
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// Computed property to get the effective talkgroup (either from dropdown or custom input)
const effectiveTalkgroup = computed(() => {
  if (selectedTalkgroup.value === '__CUSTOM__') {
    return customTalkgroup.value.trim()
  }
  return selectedTalkgroup.value
})

const closeModal = () => {
  emit('update:isVisible', false)
  // Reset state when closing
  setTimeout(() => {
    selectedNode.value = ''
    selectedMode.value = ''
    selectedTalkgroup.value = ''
    customTalkgroup.value = ''
    modes.value = []
    talkgroups.value = []
    error.value = null
    successMessage.value = null
  }, 300)
}

const loadNodes = async () => {
  loading.value = true
  error.value = null
  
  try {
    const response = await api.get('/dvswitch/nodes')
    if (response.data.success) {
      availableNodes.value = response.data.data || []
      if (availableNodes.value.length === 0) {
        error.value = 'No nodes with DVSwitch configured found.'
      }
    } else {
      error.value = response.data.message || 'Failed to load nodes'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error loading nodes. Make sure DVSwitch is configured.'
    console.error('Error loading DVSwitch nodes:', err)
  } finally {
    loading.value = false
  }
}

const onNodeChange = async () => {
  // Reset mode and talkgroup when node changes
  selectedMode.value = ''
  selectedTalkgroup.value = ''
  customTalkgroup.value = ''
  modes.value = []
  talkgroups.value = []
  
  if (!selectedNode.value) {
    return
  }
  
  await loadModes()
}

const loadModes = async () => {
  if (!selectedNode.value) return
  
  loadingModes.value = true
  error.value = null
  
  try {
    const response = await api.get(`/dvswitch/node/${encodeURIComponent(selectedNode.value)}/modes`)
    if (response.data.success) {
      modes.value = response.data.data || []
    } else {
      error.value = response.data.message || 'Failed to load modes'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error loading modes. Make sure DVSwitch is configured for this node.'
    console.error('Error loading DVSwitch modes:', err)
  } finally {
    loadingModes.value = false
  }
}

const onModeChange = async () => {
  if (!selectedMode.value || !selectedNode.value) {
    talkgroups.value = []
    selectedTalkgroup.value = ''
    customTalkgroup.value = ''
    return
  }
  
  loadingModes.value = true
  error.value = null
  
  try {
    const response = await api.get(`/dvswitch/node/${encodeURIComponent(selectedNode.value)}/mode/${encodeURIComponent(selectedMode.value)}/talkgroups`)
    if (response.data.success) {
      talkgroups.value = response.data.data || []
    } else {
      error.value = response.data.message || 'Failed to load talkgroups'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error loading talkgroups'
    console.error('Error loading talkgroups:', err)
  } finally {
    loadingModes.value = false
  }
}

const onTalkgroupChange = () => {
  // Clear custom talkgroup when switching away from custom option
  if (selectedTalkgroup.value !== '__CUSTOM__') {
    customTalkgroup.value = ''
  }
}

const switchMode = async () => {
  if (!selectedMode.value || !selectedNode.value) return
  
  switching.value = true
  error.value = null
  successMessage.value = null
  
  try {
    const response = await api.post(`/dvswitch/node/${encodeURIComponent(selectedNode.value)}/mode/${encodeURIComponent(selectedMode.value)}`)
    if (response.data.success) {
      successMessage.value = response.data.data?.message || `Switched node ${selectedNode.value} to mode: ${selectedMode.value}`
      // Update talkgroups after mode switch
      if (response.data.data?.talkgroups) {
        talkgroups.value = response.data.data.talkgroups
      } else {
        await onModeChange()
      }
    } else {
      error.value = response.data.message || 'Failed to switch mode'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error switching mode'
    console.error('Error switching mode:', err)
  } finally {
    switching.value = false
  }
}

const switchTalkgroup = async () => {
  const tgid = effectiveTalkgroup.value
  if (!tgid || !selectedNode.value) return
  
  switching.value = true
  error.value = null
  successMessage.value = null
  
  try {
    const encodedTgid = encodeURIComponent(tgid)
    const response = await api.post(`/dvswitch/node/${encodeURIComponent(selectedNode.value)}/tune/${encodedTgid}`, {
      node: selectedNode.value
    })
    if (response.data.success) {
      successMessage.value = response.data.data?.message || `Switched node ${selectedNode.value} to talkgroup: ${tgid}`
    } else {
      error.value = response.data.message || 'Failed to switch talkgroup'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error switching talkgroup'
    console.error('Error switching talkgroup:', err)
  } finally {
    switching.value = false
  }
}

// Load nodes when modal opens
watch(() => props.isVisible, (newVal) => {
  if (newVal) {
    loadNodes()
  }
})

onMounted(() => {
  if (props.isVisible) {
    loadNodes()
  }
})
</script>

<style scoped>
.dvswitch-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.dvswitch-modal {
  background: var(--container-bg);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 0;
  max-width: 600px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
  color: var(--text-color);
}

.dvswitch-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid var(--border-color);
  background-color: var(--table-header-bg);
}

.dvswitch-modal-header h2 {
  margin: 0;
  font-size: 1.5em;
  color: var(--text-color);
}

.close-button {
  background: none;
  border: none;
  font-size: 2em;
  cursor: pointer;
  color: var(--text-color);
  padding: 0;
  width: 30px;
  height: 30px;
  line-height: 1;
  opacity: 0.7;
  transition: opacity 0.2s;
}

.close-button:hover {
  opacity: 1;
  color: var(--primary-color);
}

.dvswitch-modal-content {
  padding: 20px;
}

.loading-message,
.error-message,
.success-message {
  padding: 15px;
  margin-bottom: 20px;
  border-radius: 4px;
  text-align: center;
}

.loading-message {
  background-color: var(--link-color);
  color: var(--background-color);
  opacity: 0.2;
}

.error-message {
  background-color: var(--error-color);
  color: var(--background-color);
}

.success-message {
  background-color: var(--success-color);
  color: var(--background-color);
}

.dvswitch-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.dvswitch-input-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.dvswitch-input-section label {
  font-weight: bold;
  color: var(--text-color);
}

.dvswitch-input-section select {
  padding: 10px;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  font-size: 1em;
  background-color: var(--input-bg);
  color: var(--input-text);
}

.dvswitch-input-section select:disabled {
  background-color: var(--border-color);
  cursor: not-allowed;
  opacity: 0.6;
}

.custom-tg-input {
  margin-top: 8px;
}

.custom-tg-field {
  width: 100%;
  padding: 10px;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  font-size: 1em;
  background-color: var(--input-bg);
  color: var(--input-text);
}

.custom-tg-field:disabled {
  background-color: var(--border-color);
  cursor: not-allowed;
  opacity: 0.6;
}

.custom-tg-field:focus {
  outline: none;
  border-color: var(--primary-color);
}

.dvswitch-buttons {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.action-button,
.cancel-button {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  font-size: 1em;
  cursor: pointer;
  transition: background-color 0.2s, opacity 0.2s;
}

.action-button {
  background-color: var(--link-color);
  color: var(--background-color);
}

.action-button:hover:not(:disabled) {
  opacity: 0.9;
  filter: brightness(1.1);
}

.action-button:disabled {
  background-color: var(--border-color);
  cursor: not-allowed;
  opacity: 0.5;
}

.cancel-button {
  background-color: var(--border-color);
  color: var(--text-color);
}

.cancel-button:hover {
  background-color: var(--primary-color);
  color: var(--background-color);
}

.tgif-note {
  margin-top: 8px;
  padding: 8px 12px;
  background-color: var(--link-color);
  color: var(--background-color);
  border-radius: 4px;
  font-size: 0.9em;
  opacity: 0.9;
}

.tgif-note small {
  display: block;
  line-height: 1.4;
}
</style>

