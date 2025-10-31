<template>
  <div v-if="open" class="system-info-modal" @click="closeModal">
    <div class="system-info-modal-content" @click.stop>
      <div class="system-info-header">
        <h3>System Information</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>
      
      <div class="system-info-content">
        <div v-if="loading" class="loading">
          Loading system information...
        </div>
        
        <div v-else-if="error" class="error">
          {{ error }}
        </div>
        
        <div v-else-if="systemData" class="system-data">
          <div class="info-section">
            <h4>Configuration Files</h4>
            <div class="info-item">
              <span class="label">Configurable Files Directory:</span>
              <span class="value">ALL user configurable files are in the `/var/www/html/supermon-ng/user_files` directory.</span>
            </div>
          </div>
          
          <div class="info-section">
            <h4>Login Information</h4>
            <div class="info-item">
              <span class="label">Logged in as:</span>
              <span class="value">'{{ systemData.username || 'anarchy' }}' using INI file: `{{ systemData.iniFile || 'user_files/allmon.ini' }}`</span>
            </div>
            <div class="info-item">
              <span class="label">Supermon Logged OUT INI:</span>
              <span class="value">`{{ systemData.iniFile || 'user_files/allmon.ini' }}`</span>
            </div>
          </div>
          
          <div class="info-section">
            <h4>Selective INI Settings</h4>
            <div class="info-item">
              <span class="label">Selective INI based on username:</span>
              <span class="value">**{{ systemData.selectiveIni || 'INACTIVE' }}** (Using `{{ systemData.iniFile || 'user_files/allmon.ini' }}`)</span>
            </div>
            <div class="info-item">
              <span class="label">Button selective based on username:</span>
              <span class="value">**{{ systemData.buttonSelective || 'ACTIVE' }}** (using rules related to '`{{ systemData.iniFile || 'user_files/allmon.ini' }}`')</span>
            </div>
            <div class="info-item">
              <span class="label">Selective Favorites INI:</span>
              <span class="value">**{{ systemData.selectiveFavorites || 'INACTIVE' }}** (using `{{ systemData.favoritesIni || 'user_files/favorites.ini' }}`)</span>
            </div>
            <div class="info-item">
              <span class="label">Selective Control Panel INI:</span>
              <span class="value">**{{ systemData.selectiveControlPanel || 'INACTIVE' }}** (using `{{ systemData.controlPanelIni || 'user_files/controlpanel.ini' }}`)</span>
            </div>
          </div>
          
          <div class="info-section">
            <h4>System Status</h4>
            <div class="info-item">
              <span class="label">Uptime:</span>
              <span class="value">{{ systemData.uptime || 'N/A' }} - Up since: {{ systemData.upSince || 'N/A' }} - Load Average: {{ systemData.loadAverage || 'N/A' }}</span>
            </div>
            <div class="info-item">
              <span class="label">Core Dumps:</span>
              <span class="value">[ Core dumps: {{ systemData.coreDumps || '0' }} ]</span>
            </div>
            <div class="info-item">
              <span class="label">CPU:</span>
              <span class="value">{{ systemData.cpuTemp || 'N/A' }} @ {{ systemData.cpuTime || 'N/A' }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { api } from '@/utils/api'
import type { AxiosErrorResponse } from '@/types/api'

interface Props {
  open: boolean
}

interface SystemData {
  username?: string
  iniFile?: string
  selectiveIni?: string
  buttonSelective?: string
  selectiveFavorites?: string
  selectiveControlPanel?: string
  favoritesIni?: string
  controlPanelIni?: string
  uptime?: string
  upSince?: string
  loadAverage?: string
  coreDumps?: string
  cpuTemp?: string
  cpuTime?: string
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const loading = ref(false)
const error = ref<string | null>(null)
const systemData = ref<SystemData | null>(null)

const closeModal = () => {
  emit('update:open', false)
}

const loadSystemInfo = async () => {
  if (!props.open) return
  
  loading.value = true
  error.value = null
  
  try {
    // Get system info from the backend API
    const response = await api.get('/config/system-info')
    if (response.data.success) {
      // Use the backend data directly
      systemData.value = response.data.data
    } else {
      error.value = response.data.message || 'Failed to load system information'
    }
  } catch (err: unknown) {
    const axiosError = err as AxiosErrorResponse
    error.value = axiosError.response?.data?.message || 'Failed to load system information'
  } finally {
    loading.value = false
  }
}

watch(() => props.open, (newValue) => {
  if (newValue) {
    loadSystemInfo()
  }
})

onMounted(() => {
  if (props.open) {
    loadSystemInfo()
  }
})
</script>

<style scoped>
.system-info-modal {
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

.system-info-modal-content {
  background-color: var(--background-color);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  width: 90%;
  max-width: 700px;
  max-height: 80vh;
  overflow-y: auto;
}

.system-info-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid var(--border-color);
}

.system-info-header h3 {
  margin: 0;
  color: var(--text-color);
}

.close-button {
  background: none;
  border: none;
  font-size: 24px;
  color: var(--text-color);
  cursor: pointer;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-button:hover {
  color: var(--primary-color);
}

.system-info-content {
  padding: 20px;
}

.loading, .error {
  text-align: center;
  padding: 20px;
  color: var(--text-color);
}

.error {
  color: #ff6b6b;
}

.info-section {
  margin-bottom: 30px;
}

.info-section h4 {
  margin: 0 0 15px 0;
  color: var(--primary-color);
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 5px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid var(--border-color);
}

.info-item:last-child {
  border-bottom: none;
}

.label {
  font-weight: bold;
  color: var(--text-color);
  min-width: 200px;
  margin-right: 15px;
}

.value {
  color: var(--text-color);
  text-align: right;
  flex: 1;
}
</style>


