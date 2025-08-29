<template>
  <table :class="tableClass" :id="`table_${node.id}`">
    <thead>
      <tr>
        <th :colspan="colspan">
          <i v-html="nodeTitle"></i>
        </th>
      </tr>
      <tr>
        <th>Node</th>
        <th>Node Information</th>
        <th v-if="showDetail">Received</th>
        <th>Link</th>
        <th>Dir</th>
        <th v-if="showDetail">Connected</th>
        <th>Mode</th>
      </tr>
    </thead>
    <tbody>
      <!-- Loading State -->
      <tr v-if="!nodeData">
        <td :colspan="colspan">   Waiting...</td>
      </tr>
      
      <!-- Node Data -->
      <template v-else>
        <!-- Header Status Row -->
        <tr :class="headerStatusClass">
          <td colspan="1" align="center">{{ node.id }}</td>
          <td :colspan="headerColspan" align="center">
            <b>{{ headerStatusText }}<span v-html="headerStatusDetails"></span></b>
          </td>
          <td :colspan="headerColspan3"></td>
        </tr>
        
        <!-- Connected Nodes -->
        <template v-if="connectedNodes && connectedNodes.length > 0">
          <tr 
            v-for="(connectedNode, index) in displayedConnectedNodes" 
            :key="`${node.id}-${index}`"
            :class="getConnectedNodeClass(connectedNode)"
          >
            <td 
              class="node-num clickable-node" 
              align="center"
              @click="handleNodeClick(connectedNode.node)"
              :title="`Click to set ${connectedNode.node} as target node`"
            >
              {{ connectedNode.node }}
            </td>
            <td>{{ connectedNode.info || connectedNode.ip || 'Unknown' }}</td>
            <td v-if="showDetail" align="center">
              {{ formatLastKeyed(connectedNode.last_keyed) }}
            </td>
            <td align="center">{{ connectedNode.link || 'n/a' }}</td>
            <td align="center">{{ connectedNode.direction || 'n/a' }}</td>
            <td v-if="showDetail" align="right">
              {{ connectedNode.elapsed || 'N/A' }}
            </td>
            <td align="center">{{ getModeText(connectedNode.mode) }}</td>
          </tr>
          
          <!-- Node Count Summary -->
          <tr v-if="showNodeCount && totalNodes > displayedNodes">
            <td colspan="2">
              {{ displayedNodes }} shown of {{ totalNodes }} nodes connected
              <a href="#" @click="scrollToTop">^^^</a>
            </td>
            <td :colspan="showDetail ? 5 : 3"></td>
          </tr>
        </template>
        
        <!-- No Connections -->
        <tr v-else>
          <td :colspan="colspan">No Connections.</td>
        </tr>
      </template>
    </tbody>
  </table>
  <br />
</template>

<script setup lang="ts">
import { computed, ref, watch, watchEffect } from 'vue'

// Emits
const emit = defineEmits<{
  'node-click': [nodeId: string]
}>()

// Props
interface Props {
  node: {
    id: string
    info?: string
  }
  showDetail?: boolean
  astdb?: Record<string, any>
  config?: Record<string, any>
}

const props = withDefaults(defineProps<Props>(), {
  showDetail: true,
  astdb: () => ({}),
  config: () => ({})
})

// Reactive state
const nodeData = ref<any>(null)
const connectedNodes = ref<any[]>([])
const totalNodes = ref(0)
const displayedNodes = ref(0)

// Computed properties
const colspan = computed(() => props.showDetail ? 7 : 5)

const tableClass = computed(() => {
  return props.showDetail ? 'gridtable' : 'gridtable-large'
})

const nodeTitle = computed(() => {
  const nodeId = props.node.id
  const nodeInfo = props.node.info || 'Node not in database'
  
  // Check for custom URL
  const customUrlKey = `URL_${nodeId}`
  const customUrl = props.config ? (props.config as any)[customUrlKey] : null
  
  let infoDisplay = nodeInfo
  let targetBlank = ''
  
  if (customUrl) {
    let url = customUrl
    if (url.endsWith('>')) {
      url = url.slice(0, -1)
      targetBlank = 'target="_blank"'
    }
    infoDisplay = `<a href="${url}" ${targetBlank}>${nodeInfo}</a>`
  }
  
  // Determine base title and node link
  const isPrivateOrHidden = nodeInfo === 'Node not in database' || 
    (props.config && props.config[nodeId]?.hideNodeURL === 1)
  
  const baseTitle = isPrivateOrHidden ? 'Private Node' : 'Node'
  let nodeLink = nodeId
  
  if (isPrivateOrHidden) {
    if (customUrl) {
      let url = customUrl
      if (url.endsWith('>')) url = url.slice(0, -1)
      nodeLink = `<a href="${url}" ${targetBlank}>${nodeId}</a>`
    }
  } else {
    const allstarNodeUrl = parseInt(nodeId) >= 2000 
      ? `http://stats.allstarlink.org/nodeinfo.cgi?node=${encodeURIComponent(nodeId)}`
      : ''
    
    if (allstarNodeUrl) {
      nodeLink = `<a href="${allstarNodeUrl}" target="_blank">${nodeId}</a>`
    } else if (customUrl) {
      let url = customUrl
      if (url.endsWith('>')) url = url.slice(0, -1)
      nodeLink = `<a href="${url}" ${targetBlank}>${nodeId}</a>`
    }
  }
  
  // Add external links
  const links: string[] = []
  
  if (!isPrivateOrHidden && parseInt(nodeId) >= 2000) {
    const bubbleChart = `http://stats.allstarlink.org/getstatus.cgi?${encodeURIComponent(nodeId)}`
    links.push(`<a href="${bubbleChart}" target="_blank">Bubble Chart</a>`)
  }
  
  if (props.config && props.config[nodeId]?.lsnodes) {
    links.push(`<a href="${props.config[nodeId].lsnodes}" target="_blank">lsNodes</a>`)
  } else if (props.config && props.config[nodeId]?.host && /localhost|127\.0\.0\.1/.test(props.config[nodeId].host)) {
    const lsNodesChart = `/cgi-bin/lsnodes_web?node=${encodeURIComponent(nodeId)}`
    links.push(`<a href="${lsNodesChart}" target="_blank">lsNodes</a>`)
  }
  
  if (props.config && props.config[nodeId]?.listenlive) {
    links.push(`<a href="${props.config[nodeId].listenlive}" target="_blank">Listen Live</a>`)
  }
  
  if (props.config && props.config[nodeId]?.archive) {
    links.push(`<a href="${props.config[nodeId].archive}" target="_blank">Archive</a>`)
  }
  
  let title = `  ${baseTitle} ${nodeLink} => ${infoDisplay}  `
  
  if (links.length > 0) {
    title += '<br>' + links.join('  ')
  }
  
  return title
})

const headerStatusClass = computed(() => {
  if (!nodeData.value) return ''
  
  const cosKeyed = nodeData.value.cos_keyed || 0
  const txKeyed = nodeData.value.tx_keyed || 0
  
  if (cosKeyed === 0) {
    if (txKeyed === 0) return 'gColor' // Idle
    else return 'tColor' // PTT-Keyed
  } else {
    if (txKeyed === 0) return 'lColor' // COS-Detected
    else return 'bColor' // COS-Detected and PTT-Keyed (Full-Duplex)
  }
})

const headerStatusText = computed(() => {
  if (!nodeData.value) return ''
  
  const cosKeyed = nodeData.value.cos_keyed || 0
  const txKeyed = nodeData.value.tx_keyed || 0
  
  let statusText = ''
  
  if (cosKeyed === 0) {
    if (txKeyed === 0) statusText = 'Idle'
    else statusText = 'PTT-Keyed'
  } else {
    if (txKeyed === 0) statusText = 'COS-Detected'
    else statusText = 'COS-Detected and PTT-Keyed (Full-Duplex)'
  }
  
  // Apply the same text transformations as the original - only when cpu_temp exists
  if (nodeData.value.cpu_temp) {
    if (statusText === 'PTT-Keyed') statusText = 'PTT-KEYED'
    if (statusText === 'COS-Detected') statusText = 'COS-DETECTED'
    if (statusText === 'COS-Detected and PTT-Keyed (Full-Duplex)') statusText = 'COS-Detected and PTT-Keyed (Full Duplex)'
  }
  
  return statusText
})

const headerStatusDetails = computed(() => {
  if (!nodeData.value) return ''
  
  // Original logic: only show details if cpu_temp exists
  if (!nodeData.value.cpu_temp) return ''
  
  const details = []
  
  // Skywarn/Alert information (these contain HTML)
  if (nodeData.value.ALERT) details.push(nodeData.value.ALERT)
  if (nodeData.value.WX) details.push(nodeData.value.WX)
  
  // System information (plain text) - match original format exactly
  const cpuInfo = `CPU=${nodeData.value.cpu_temp}`
  if (nodeData.value.cpu_up) details.push(cpuInfo + ` - ${nodeData.value.cpu_up}`)
  else details.push(cpuInfo)
  
  if (nodeData.value.cpu_load) details.push(nodeData.value.cpu_load)
  if (nodeData.value.DISK) details.push(nodeData.value.DISK)
  
  return details.length > 0 ? '<br>' + details.join('<br>') : ''
})

const headerColspan = computed(() => {
  if (!nodeData.value) return 1
  
  const cosKeyed = nodeData.value.cos_keyed || 0
  const txKeyed = nodeData.value.tx_keyed || 0
  
  // Original logic: when both COS and TX are keyed (both are 1), use colspan 2
  if (cosKeyed === 1 && txKeyed === 1) return 2
  return 1
})

const headerColspan3 = computed(() => {
  if (!nodeData.value) return 5
  
  const cosKeyed = nodeData.value.cos_keyed || 0
  const txKeyed = nodeData.value.tx_keyed || 0
  
  // Original logic: when both COS and TX are keyed (both are 1), use colspan 4
  if (cosKeyed === 1 && txKeyed === 1) return 4
  return 5
})

const displayedConnectedNodes = computed(() => {
  if (!connectedNodes.value) return []
  
  // Filter out node 1 if it has no info
  const filteredNodes = connectedNodes.value.filter(node => 
    node.node !== 1 || (node.info && node.info !== 'NO CONNECTION')
  )
  
  // Apply display logic based on showAll and displayCount
  // TODO: Implement proper display logic based on user preferences
  return filteredNodes
})

const showNodeCount = computed(() => {
  return totalNodes.value > displayedNodes.value && totalNodes.value > 1
})

// Methods
const getConnectedNodeClass = (node: any): string => {
  if (node.keyed === 'yes') {
    return node.mode === 'R' ? 'rxkColor' : 'rColor'
  } else if (node.mode === 'C') {
    return 'cColor'
  } else if (node.mode === 'R') {
    return 'rxColor'
  }
  return ''
}

const getModeText = (mode: string): string => {
  switch (mode) {
    case 'R': return 'RX Only'
    case 'T': return 'Transceive'
    case 'C': return 'Connecting'
    case 'Echolink': return 'Echolink'
    case 'Local RX': return 'Local RX'
    default: return mode || 'n/a'
  }
}

const scrollToTop = () => {
  window.scrollTo(0, 0)
}

const handleNodeClick = (nodeId: string) => {
  // Emit event to parent component
  emit('node-click', nodeId)
}

// Update node data from real-time store
const updateNodeData = (data: any) => {
  nodeData.value = data
  
  if (data && data.remote_nodes) {
    connectedNodes.value = data.remote_nodes
    totalNodes.value = connectedNodes.value.length
    displayedNodes.value = Math.min(totalNodes.value, 999) // TODO: Use actual display count
  } else {
    connectedNodes.value = []
    totalNodes.value = 0
    displayedNodes.value = 0
  }
}

// Refresh data method for parent component
const refreshData = () => {
  // This will be called by the parent when new data is available
}

// Watch for header status details changes
watchEffect(() => {
  // Header status details have changed
})

// Format last keyed time
const formatLastKeyed = (lastKeyed: any): string => {
  if (!lastKeyed || lastKeyed === 'N/A' || lastKeyed === 'n/a') {
    return 'N/A'
  }
  
  // If it's -1, return "Never"
  if (lastKeyed === -1 || lastKeyed === '-1' || lastKeyed === '-1') {
    return 'Never'
  }
  
  // If it's a number, it might be seconds since last keyed
  const numValue = parseInt(lastKeyed)
  if (!isNaN(numValue)) {
    if (numValue === -1) {
      return 'Never'
    }
    
    // Convert seconds to HH:MM:SS format (matching original Supermon-ng)
    const hours = Math.floor(numValue / 3600)
    const minutes = Math.floor((numValue % 3600) / 60)
    const seconds = numValue % 60
    
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
  }
  
  // If it's already a formatted string, return as is
  return String(lastKeyed)
}

// Expose methods for parent component
defineExpose({
  updateNodeData,
  refreshData
})
</script>

<style scoped>
.node-table-wrapper {
  width: 100%;
  margin-bottom: 20px;
}

/* Original Gridtable Styles */
.gridtable {
  font-family: verdana, arial, sans-serif;
  font-size: 12px;
  font-weight: bold;
  color: var(--text-color);
  padding: 4px;
  border-collapse: collapse;
  width: 100%;
}

/* Clickable node styling */
.clickable-node {
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.clickable-node:hover {
  background-color: rgba(255, 255, 255, 0.1);
  text-decoration: underline;
}
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.gridtable-large {
  font-family: verdana, arial, sans-serif;
  font-size: 22px;
  font-weight: bold;
  color: var(--text-color);
  padding: 4px;
  border-collapse: collapse;
}

/* Node number cell */
.node-num {
  cursor: pointer;
  user-select: none;
}

.node-num:hover {
  background-color: var(--border-color) !important;
}

/* Header status details */
.gridtable td b {
  display: block;
  white-space: normal;
  word-wrap: break-word;
  line-height: 1.3;
}

.gridtable td b span {
  display: block;
  margin-top: 2px;
  font-weight: normal;
}

/* Original Supermon-ng table styling */
.gridtable {
  font-family: verdana, arial, sans-serif;
  font-size: 12px;
  font-weight: bold;
  color: var(--text-color);
  padding: 2px;
  border-collapse: collapse;
  width: auto;
  min-width: 0;
  table-layout: auto;
}

.gridtable-large {
  font-family: verdana, arial, sans-serif;
  font-size: 22px;
  font-weight: bold;
  color: var(--text-color);
  padding: 4px;
  border-collapse: collapse;
}

.gridtable th {
  padding: 2px 4px;
  background-color: var(--table-header-bg);
  color: var(--primary-color);
  font-size: 12px;
}

.gridtable td {
  padding: 2px 4px;
  background-color: var(--container-bg);
  font-size: 11px;
  line-height: 1.2;
}

/* Table row colors - matching original Supermon-ng */
.gridtable tr.rColor td {
  background-color: #2a2a2a;
  font-weight: bold;
  color: var(--link-color);
}

.gridtable tr.cColor td,
.gridtable tr.cColor td a,
.gridtable tr.tColor td {
  background-color: #2a2a2a;
  font-weight: bold;
  color: var(--link-color);
}

.gridtable tr.bColor td {
  background-color: var(--success-color);
  font-weight: bold;
  color: var(--background-color);
}

.gridtable tr.gColor td {
  background-color: #404040;
  font-weight: bold;
  color: var(--text-color);
}

.gridtable tr.lColor td {
  background-color: #585858;
  font-weight: bold;
  color: var(--text-color);
}

.gridtable tr.rxkColor td {
  background-color: #2a2a2a;
  font-weight: bold;
  color: var(--link-color);
}



/* Responsive design */
@media (max-width: 768px) {
  .gridtable,
  .gridtable-large {
    font-size: 11px;
  }
  
  .node-table th,
  .node-table td {
    padding: 2px;
  }
}
</style>
