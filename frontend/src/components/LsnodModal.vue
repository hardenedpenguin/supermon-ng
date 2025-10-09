<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeModal">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h3>lsnod Output for Node {{ nodeId }}</h3>
        <button class="close-button" @click="closeModal">&times;</button>
      </div>
      
      <div class="modal-body">
        <div v-if="loading" class="loading">
          <p>Loading lsnod data...</p>
        </div>
        
        <div v-else-if="error" class="error">
          <h4>Error</h4>
          <p>{{ error }}</p>
        </div>
        
        <div v-else-if="data" class="lsnod-content">
          <!-- Header Information -->
          <div class="header-info">
            <h2>Status for {{ getNodeCallsign() }} - Node {{ nodeId }}</h2>
            <p>Last update {{ getCurrentTime() }}</p>
            <p>My IP - {{ getMyIP() }}</p>
            <p class="bubble-chart-link">
              <a :href="getBubbleChartUrl()" target="_blank">View this Node Graphically</a>
            </p>
          </div>

          <!-- Main Content Tables -->
          <div class="tables-container">
            <!-- Left Table: Selected system state -->
            <div class="left-table">
              <table class="system-state-table">
                <tr>
                  <td class="label">Selected system state:</td>
                  <td class="value">{{ data.selected_system_state || '0' }}</td>
                </tr>
                <tr>
                  <td class="label">Signal on input:</td>
                  <td class="value">{{ data.signal_on_input || 'NO' }}</td>
                </tr>
                <tr>
                  <td class="label">System:</td>
                  <td class="value">{{ data.system || 'ENABLED' }}</td>
                </tr>
                <tr>
                  <td class="label">Parrot Mode:</td>
                  <td class="value">{{ data.parrot_mode || 'DISABLED' }}</td>
                </tr>
                <tr>
                  <td class="label">Scheduler:</td>
                  <td class="value">{{ data.scheduler || 'ENABLED' }}</td>
                </tr>
                <tr>
                  <td class="label">Tail Time:</td>
                  <td class="value">{{ data.tail_time || 'STANDARD' }}</td>
                </tr>
                <tr>
                  <td class="label">Time out timer:</td>
                  <td class="value">{{ data.timeout_timer || 'ENABLED' }}</td>
                </tr>
                <tr>
                  <td class="label">Incoming connections:</td>
                  <td class="value">{{ data.incoming_connections || 'ENABLED' }}</td>
                </tr>
                <tr>
                  <td class="label">Time out timer state:</td>
                  <td class="value">{{ data.timeout_timer_state || 'RESET' }}</td>
                </tr>
                <tr>
                  <td class="label">Time outs since system initialization:</td>
                  <td class="value">{{ data.timeouts_since_init || '0' }}</td>
                </tr>
                <tr>
                  <td class="label">Identifier state:</td>
                  <td class="value">{{ data.identifier_state || 'CLEAN' }}</td>
                </tr>
                <tr>
                  <td class="label">Kerchunks today:</td>
                  <td class="value">{{ data.kerchunks_today || '0' }}</td>
                </tr>
                <tr>
                  <td class="label">Kerchunks since system initialization:</td>
                  <td class="value">{{ data.kerchunks_since_init || '0' }}</td>
                </tr>
                <tr>
                  <td class="label">Keyups today:</td>
                  <td class="value">{{ data.keyups_today || '0' }}</td>
                </tr>
                <tr>
                  <td class="label">Keyups since system initialization:</td>
                  <td class="value">{{ data.keyups_since_init || '0' }}</td>
                </tr>
                <tr>
                  <td class="label">DTMF commands today:</td>
                  <td class="value">{{ data.dtmf_commands_today || '0' }}</td>
                </tr>
                <tr>
                  <td class="label">DTMF commands since system initialization:</td>
                  <td class="value">{{ data.dtmf_commands_since_init || '0' }}</td>
                </tr>
                <tr>
                  <td class="label">Last DTMF command executed:</td>
                  <td class="value">{{ data.last_dtmf_command || 'N/A' }}</td>
                </tr>
                <tr>
                  <td class="label">TX time today:</td>
                  <td class="value">{{ data.tx_time_today || '00:00:00:000' }}</td>
                </tr>
                <tr>
                  <td class="label">TX time since system initialization:</td>
                  <td class="value">{{ data.tx_time_since_init || '00:00:00:000' }}</td>
                </tr>
                <tr>
                  <td class="label">Uptime:</td>
                  <td class="value">{{ data.uptime || '00:00:00' }}</td>
                </tr>
                <tr>
                  <td class="label">Nodes currently connected to us:</td>
                  <td class="value">{{ data.nodes_connected || '0' }}</td>
                </tr>
                <tr>
                  <td class="label">Autopatch:</td>
                  <td class="value">{{ data.autopatch || 'ENABLED' }}</td>
                </tr>
                <tr>
                  <td class="label">Autopatch state:</td>
                  <td class="value">{{ data.autopatch_state || 'DOWN' }}</td>
                </tr>
                <tr>
                  <td class="label">Autopatch called number:</td>
                  <td class="value">{{ data.autopatch_called_number || 'N/A' }}</td>
                </tr>
                <tr>
                  <td class="label">Reverse patch/IAXRPT connected:</td>
                  <td class="value">{{ data.reverse_patch || 'DOWN' }}</td>
                </tr>
                <tr>
                  <td class="label">User linking commands:</td>
                  <td class="value">{{ data.user_linking_commands || 'ENABLED' }}</td>
                </tr>
                <tr>
                  <td class="label">User functions:</td>
                  <td class="value">{{ data.user_functions || 'ENABLED' }}</td>
                </tr>
              </table>
            </div>

            <!-- Right Tables -->
            <div class="right-tables">
              <!-- Node Information Table -->
              <div class="node-info-table">
                <table class="info-table">
                  <thead>
                    <tr class="header-row">
                      <th>Node</th>
                      <th>Callsign</th>
                      <th>Frequency</th>
                      <th>Location</th>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- Main node (first row) -->
                    <tr v-if="data?.main_node" class="data-row-1">
                      <td>{{ data.main_node.node_number }}</td>
                      <td>{{ data.main_node.callsign }}</td>
                      <td>{{ data.main_node.frequency }}</td>
                      <td>{{ data.main_node.location }}</td>
                    </tr>
                    <!-- No additional connected nodes in this table - they are shown in the Connected Nodes Table below -->
                  </tbody>
                </table>
              </div>

              <!-- Connected Nodes Table -->
              <div class="connected-nodes-table">
                <table class="info-table">
                  <thead>
                    <tr class="header-row">
                      <th>Node</th>
                      <th>Peer</th>
                      <th>Reconnects</th>
                      <th>Direction</th>
                      <th>Connect Time</th>
                      <th>Connect State</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-if="data?.nodes && data.nodes.length > 0">
                      <tr v-for="(node, index) in data.nodes" :key="node.node_number" 
                          :class="getConnectionRowClass(index)">
                        <td>{{ node.node_number }}</td>
                        <td>{{ node.peer_ip || 'N/A' }}</td>
                        <td>{{ node.reconnects || 'N/A' }}</td>
                        <td>{{ node.direction || 'N/A' }}</td>
                        <td>{{ node.connect_time || 'N/A' }}</td>
                        <td>{{ node.connect_state || 'Connected' }}</td>
                      </tr>
                    </template>
                    <tr v-else class="data-row-3">
                      <td>No connections</td>
                      <td>-</td>
                      <td>-</td>
                      <td>-</td>
                      <td>-</td>
                      <td>-</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Registry Table -->
              <div class="registry-table">
                <table class="info-table">
                  <thead>
                    <tr class="header-row">
                      <th>Host</th>
                      <th>Username</th>
                      <th>Perceived</th>
                      <th>Refresh</th>
                      <th>State</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-if="data?.iax_registry && data.iax_registry.length > 0">
                      <tr v-for="(registration, index) in data.iax_registry" :key="index" 
                          :class="getRegistrationRowClass(index)">
                        <td>{{ registration.host || 'N/A' }}</td>
                        <td>{{ registration.username || 'N/A' }}</td>
                        <td>{{ registration.perceived || 'N/A' }}</td>
                        <td>{{ registration.refresh || 'N/A' }}</td>
                        <td>{{ registration.state || 'N/A' }}</td>
                      </tr>
                    </template>
                    <tr v-else class="data-row-white">
                      <td>No registrations</td>
                      <td>-</td>
                      <td>-</td>
                      <td>-</td>
                      <td>-</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="modal-footer">
        <button class="btn btn-secondary" @click="closeModal">Close</button>
      </div>
    </div>
    
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { api } from '@/utils/api'

interface LsnodData {
  selected_system_state?: string
  signal_on_input?: string
  system?: string
  parrot_mode?: string
  scheduler?: string
  tail_time?: string
  timeout_timer?: string
  incoming_connections?: string
  timeout_timer_state?: string
  timeouts_since_init?: string
  identifier_state?: string
  kerchunks_today?: string
  kerchunks_since_init?: string
  keyups_today?: string
  keyups_since_init?: string
  dtmf_commands_today?: string
  dtmf_commands_since_init?: string
  last_dtmf_command?: string
  tx_time_today?: string
  tx_time_since_init?: string
  uptime?: string
  nodes_connected?: string
  autopatch?: string
  autopatch_state?: string
  autopatch_called_number?: string
  reverse_patch?: string
  user_linking_commands?: string
  user_functions?: string
  main_node?: {
    node_number: string
    callsign: string
    frequency: string
    location: string
  }
  nodes?: any[]
  node_status?: any[]
  node_lstatus?: any[]
  iax_registry?: any[]
  total_nodes?: number
}

interface Props {
  isVisible: boolean
  nodeId: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:isVisible': [value: boolean]
}>()


const loading = ref(false)
const error = ref('')
const data = ref<LsnodData | null>(null)

const loadLsnodData = async () => {
  if (!props.nodeId) return
  
  try {
    loading.value = true
    error.value = ''
    
    const response = await api.get(`/nodes/${props.nodeId}/lsnodes/web`)
    
    if (response.data.success) {
      data.value = response.data.data
    } else {
      error.value = response.data.message || 'Failed to load lsnod data'
    }
  } catch (err: any) {
    console.error('Error loading lsnod data:', err)
    error.value = err.response?.data?.message || 'Failed to load lsnod data'
  } finally {
    loading.value = false
  }
}

const closeModal = () => {
  emit('update:isVisible', false)
}

// Watch for visibility changes and load data when modal opens
watch(() => props.isVisible, (newValue) => {
  if (newValue && props.nodeId) {
    loadLsnodData()
  }
}, { immediate: true })

// Watch for nodeId changes
watch(() => props.nodeId, (newValue) => {
  if (props.isVisible && newValue) {
    loadLsnodData()
  }
})

// Helper functions for display
const getNodeCallsign = () => {
  // Get from main node data if available
  if (data.value?.main_node) {
    return data.value.main_node.callsign || 'Unknown'
  }
  return 'Unknown'
}

const getNodeFrequency = () => {
  // Get from main node data if available
  if (data.value?.main_node) {
    return data.value.main_node.frequency || 'Unknown'
  }
  return 'Unknown'
}

const getNodeLocation = () => {
  // Get from main node data if available
  if (data.value?.main_node) {
    return data.value.main_node.location || 'Unknown'
  }
  return 'Unknown'
}

const getConnectedNode = () => {
  // Get the first connected node from the data
  if (data.value?.nodes && data.value.nodes.length > 0) {
    return data.value.nodes[0].node_number || '0'
  }
  return '0'
}

const getConnectTime = () => {
  // Return a placeholder connect time
  return '00:00:00:000'
}

const getCurrentTime = () => {
  const now = new Date()
  return now.toLocaleDateString() + ' ' + now.toLocaleTimeString()
}

const getMyIP = () => {
  // Return a placeholder IP - this would need to be provided by the backend
  return '127.0.0.1'
}

const getBubbleChartUrl = () => {
  return `https://stats.allstarlink.org/getstatus.cgi?${props.nodeId}`
}


const getNodeRowClass = (index: number) => {
  // Apply different row classes based on index to match original color scheme
  const classes = ['data-row-1', 'data-row-2', 'data-row-3']
  return classes[index % classes.length]
}

const getConnectionRowClass = (index: number) => {
  // Apply different row classes for connection table
  const classes = ['data-row-1', 'data-row-2', 'data-row-3']
  return classes[index % classes.length]
}

const getRegistrationRowClass = (index: number) => {
  // Apply different row classes for registration table
  const classes = ['data-row-1', 'data-row-2', 'data-row-3']
  return classes[index % classes.length]
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.8);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background: #1a1a1a;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.6);
  max-width: 95vw;
  max-height: 95vh;
  width: 1200px;
  display: flex;
  flex-direction: column;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  border-bottom: 1px solid #333;
  background: #2d2d2d;
  border-radius: 8px 8px 0 0;
}

.modal-header h3 {
  margin: 0;
  color: #e0e0e0;
  font-size: 1.25rem;
}

.close-button {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #888;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: background-color 0.2s;
}

.close-button:hover {
  background-color: #444;
  color: #e0e0e0;
}

.modal-body {
  padding: 20px;
  overflow-y: auto;
  flex: 1;
  background: #1a1a1a;
}

.modal-footer {
  padding: 15px 20px;
  border-top: 1px solid #333;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  background: #2d2d2d;
  border-radius: 0 0 8px 8px;
}

.btn {
  padding: 8px 16px;
  border: 1px solid #555;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  background: #333;
  color: #e0e0e0;
  transition: background-color 0.2s;
}

.btn:hover {
  background-color: #444;
}

.btn-secondary {
  background: #6c757d;
  color: white;
  border-color: #6c757d;
}

.btn-secondary:hover {
  background: #5a6268;
  border-color: #5a6268;
}

.loading {
  text-align: center;
  padding: 20px;
  color: #e0e0e0;
  font-size: 16px;
}

.error {
  color: #ff6b6b;
  padding: 20px;
  background-color: #2d1b1b;
  border: 1px solid #4a2c2c;
  border-radius: 4px;
}

.lsnod-content {
  color: #e0e0e0;
}

.header-info {
  margin-bottom: 20px;
}

.header-info h2 {
  margin: 0 0 10px 0;
  color: #e0e0e0;
  font-size: 1.5rem;
}

.header-info p {
  margin: 5px 0;
  color: #ccc;
  font-size: 14px;
}

.bubble-chart-link a {
  color: #4a9eff;
  text-decoration: none;
}

.bubble-chart-link a:hover {
  color: #6bb6ff;
  text-decoration: underline;
}

.tables-container {
  display: flex;
  gap: 20px;
}

.left-table {
  flex: 1;
}

.right-tables {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.system-state-table {
  width: 100%;
  border-collapse: collapse;
  background: #87ceeb; /* powderblue - matching original */
  border: 1px solid #666;
}

/* Remove hover effects from system state table to make it static */
.system-state-table tr:hover {
  background-color: inherit !important;
}

.system-state-table tr {
  border-bottom: 1px solid #666;
}

.system-state-table td {
  padding: 8px 12px;
  vertical-align: top;
}

.system-state-table .label {
  font-weight: bold;
  color: #000;
  width: 60%;
}

.system-state-table .value {
  color: #000;
  width: 40%;
}

.info-table {
  width: 100%;
  border-collapse: collapse;
  background: #1a1a1a;
  border: 1px solid #666;
}

.info-table th {
  background: #add8e6; /* lightblue - matching original */
  color: #000;
  border-bottom: 1px solid #000000;
  padding: 8px 12px;
  text-align: left;
  font-weight: bold;
  border: 1px solid #666;
}

.info-table td {
  padding: 8px 12px;
  border: 1px solid #666;
  color: #000;
}

.header-row {
  background: #add8e6 !important; /* lightblue - matching original */
  color: #000 !important;
}

.data-row-1 {
  background: #FF90AF !important; /* Exact original pink color */
}

.data-row-2 {
  background: #90EE90 !important; /* lightgreen - matching original */
}

.data-row-3 {
  background: #D3D3D3 !important; /* lightgray - matching original */
}

.data-row-white {
  background: #00BFFF !important; /* lightblue background for registry table */
}

.node-info-table,
.connected-nodes-table,
.registry-table {
  margin-bottom: 15px;
}

.node-info-table {
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid #666;
}

@media (max-width: 1024px) {
  .tables-container {
    flex-direction: column;
  }
  
  .modal-content {
    width: 95vw;
    max-width: none;
  }
}

@media (max-width: 768px) {
  .modal-content {
    width: 100vw;
    height: 100vh;
    max-height: 100vh;
    border-radius: 0;
  }
  
  .modal-header,
  .modal-footer {
    border-radius: 0;
  }
  
  .system-state-table .label,
  .system-state-table .value {
    width: auto;
    display: block;
  }
  
  .system-state-table .label {
    font-weight: bold;
    margin-bottom: 2px;
  }
}

</style>
