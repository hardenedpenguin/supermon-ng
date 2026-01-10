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
          <td colspan="1" align="center" class="local-node-number">{{ node.id }}</td>
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
            :class="getConnectedNodeClass(connectedNode, index)"
          >
            <td 
              class="nodeNum" 
              align="center"
              @click="handleNodeClick(connectedNode.node)"
              :title="`Click to set ${connectedNode.node} as target node`"
            >
              {{ connectedNode.node }}
            </td>
            <td>{{ connectedNode.info || connectedNode.ip || 'Unknown' }}</td>
            <td v-if="showDetail" align="center">
              {{ formatLastKeyed(connectedNode.last_keyed, index) }}
            </td>
            <td align="center">{{ connectedNode.link || 'n/a' }}</td>
            <td align="center">{{ connectedNode.direction || 'n/a' }}</td>
            <td v-if="showDetail" align="right">
              {{ formatElapsed(connectedNode.elapsed, index) }}
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
  
  <!-- Bubble Chart Modal -->
  <BubbleChart 
    v-if="showBubbleChart"
    :open="showBubbleChart"
    :localNode="node.id"
    @update:open="showBubbleChart = $event"
  />
  
  <!-- Lsnod Modal -->
  <LsnodModal 
    v-if="showLsnodModal"
    :isVisible="showLsnodModal"
    :nodeId="node.id"
    @update:isVisible="showLsnodModal = $event"
  />
</template>

<script setup lang="ts">
import { computed, ref, watchEffect, onMounted, onUnmounted } from 'vue'
import { useAppStore } from '@/stores/app'
import BubbleChart from './BubbleChart.vue'
import LsnodModal from './LsnodModal.vue'
import type { ConnectedNode, Node } from '@/types/node'

// Timer state for real-time updates
const timerTick = ref(0)
const nodeTimers = ref<Map<string, { elapsedBase: number | null, elapsedTimestamp: number | null, lastKeyedBase: number | null, lastKeyedTimestamp: number | null }>>(new Map())

// Emits
const emit = defineEmits<{
  'node-click': [nodeId: string, localNodeId: string]
}>()

// Store
const appStore = useAppStore()

// Props
interface Props {
  node: {
    id: string
    info?: string
    callsign?: string
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
const showBubbleChart = ref(false)
const showLsnodModal = ref(false)

// Computed properties
const colspan = computed(() => props.showDetail ? 7 : 5)

const tableClass = computed(() => {
  return props.showDetail ? 'gridtable' : 'gridtable-large'
})

const nodeTitle = computed(() => {
  const nodeId = props.node.id
  let nodeInfo = props.node.info || 'Node not in database'
  
  // If nodeInfo is just "Node X", "Node not in database", or missing, try to get info from ASTDB
  if (props.astdb && (!nodeInfo || nodeInfo === `Node ${nodeId}` || nodeInfo === 'Node not in database' || (nodeInfo.startsWith('Node ') && nodeInfo.trim() === `Node ${nodeId}`))) {
    const astdbEntry = props.astdb[nodeId]
    if (astdbEntry && Array.isArray(astdbEntry) && astdbEntry.length >= 4) {
      const callsign = astdbEntry[1] || 'N/A'
      const description = astdbEntry[2] || 'Unknown'
      const location = astdbEntry[3] || 'N/A'
      nodeInfo = `${callsign} ${description} ${location}`.trim()
    }
  }
  
  // Prepend callsign if available and not already in info (like in 4.0.9)
  if (props.node.callsign && props.node.callsign !== 'N/A' && !nodeInfo.startsWith(props.node.callsign)) {
    nodeInfo = `${props.node.callsign} ${nodeInfo}`.trim()
  }
  
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
  
  // Add Bubble Chart and lsNodes links for the main node on the same line
  const modalLinks = []
  if (!isPrivateOrHidden && parseInt(nodeId) >= 2000) {
    // Bubble Chart link - now opens modal instead of external site
    modalLinks.push(`<a href="#" class="bubble-chart-modal-link" data-node-id="${nodeId}">Bubble Chart</a>`)
  }
  modalLinks.push(`<a href="#" class="lsnod-modal-link" data-node-id="${nodeId}">lsNodes</a>`)
  
  if (props.config && props.config[nodeId]?.listenlive) {
    links.push(`<a href="${props.config[nodeId].listenlive}" target="_blank">Listen Live</a>`)
  }
  
  if (props.config && props.config[nodeId]?.archive) {
    links.push(`<a href="${props.config[nodeId].archive}" target="_blank">Archive</a>`)
  }
  
  let title = `  ${baseTitle} ${nodeLink} => ${infoDisplay}  `
  
  // Add modal links on the same line with 4 spaces between them
  if (modalLinks.length > 0) {
    title += '<br>' + modalLinks.join('    ')
  }
  
  if (links.length > 0) {
    title += '<br>' + links.join('  ')
  }
  
  return title
})

const headerStatusClass = computed(() => {
  if (!nodeData.value) return ''
  
  const cosKeyed = nodeData.value.cos_keyed || 0
  const txKeyed = nodeData.value.tx_keyed || 0
  
  let statusClass = ''
  
  if (cosKeyed === 0) {
    if (txKeyed === 0) statusClass = 'gColor' // Idle
    else statusClass = 'tColor' // PTT-Keyed
  } else {
    if (txKeyed === 0) statusClass = 'lColor' // COS-Detected
    else statusClass = 'bColor' // COS-Detected and PTT-Keyed (Full-Duplex)
  }
  

  
  return statusClass
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
  
  return details.length > 0 ? details.join('<br>') : ''
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
  
  // Apply display logic based on user preferences
if (appStore.user?.preferences?.showAll) {
  return filteredNodes
} else {
  const maxNodes = appStore.user?.preferences?.displayedNodes || 999
  return filteredNodes.slice(0, maxNodes)
}
})

const showNodeCount = computed(() => {
  return totalNodes.value > displayedNodes.value && totalNodes.value > 1
})

// Methods
const getConnectedNodeClass = (node: ConnectedNode, index: number): string => {
  // First connected node gets lighter header color, others get darker gColor
  let className = ''
  
  if (index === 0) {
    if (node.keyed === 'yes') {
      className = node.mode === 'R' ? 'rxkColor' : 'rColor'
    } else if (node.mode === 'C') {
      className = 'cColor'
    } else if (node.mode === 'R') {
      className = 'rxColor'
    } else {
      className = 'firstNodeColor' // Lighter color for first connected node when idle (different from gColor)
    }
  } else {
    // Subsequent nodes get darker gColor (idle state)
    className = 'gColor'
  }
  

  
  return className
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
  // Emit event to parent component with both the clicked node and the local node (table owner)
  emit('node-click', nodeId, props.node.id)
}

const openBubbleChartModal = () => {
  showBubbleChart.value = true
}

const openLsnodModal = () => {
  showLsnodModal.value = true
}

// Update node data from real-time store
const updateNodeData = (data: Node | null) => {
  nodeData.value = data
  
  if (data && data.remote_nodes) {
    connectedNodes.value = data.remote_nodes
    totalNodes.value = connectedNodes.value.length
    // Use user preferences for display count, fallback to 999
    const maxDisplay = appStore.user?.preferences?.displayedNodes || 999
    displayedNodes.value = Math.min(totalNodes.value, maxDisplay)
    
    // Update timer bases for real-time calculations
    connectedNodes.value.forEach((node: ConnectedNode) => {
      const nodeKey = `${props.node.id}-${node.node}`
      
      // Parse elapsed time - could be HH:MM:SS or seconds
      let elapsedBase: number | null = null
      let elapsedTimestamp: number | null = null
      if (node.elapsed && node.elapsed !== 'N/A' && node.elapsed !== 'unknown' && node.elapsed !== '') {
        // Try parsing as HH:MM:SS
        const timeMatch = node.elapsed.match(/(\d+):(\d+):(\d+)/)
        if (timeMatch) {
          const hours = parseInt(timeMatch[1])
          const minutes = parseInt(timeMatch[2])
          const seconds = parseInt(timeMatch[3])
          elapsedBase = hours * 3600 + minutes * 60 + seconds
          elapsedTimestamp = Date.now() // Store when we received this value
        } else {
          // Try parsing as seconds
          const seconds = parseInt(node.elapsed)
          if (!isNaN(seconds)) {
            elapsedBase = seconds
            elapsedTimestamp = Date.now() // Store when we received this value
          }
        }
      }
      
      // Parse last_keyed - should be seconds since last keyed
      let lastKeyedBase: number | null = null
      let lastKeyedTimestamp: number | null = null
      if (node.last_keyed && node.last_keyed !== '-1' && node.last_keyed !== 'N/A' && node.last_keyed !== 'n/a') {
        const seconds = parseInt(node.last_keyed)
        if (!isNaN(seconds) && seconds >= 0) {
          lastKeyedBase = seconds
          lastKeyedTimestamp = Date.now() // Store when we received this value
        }
      }
      
      // Update or create timer entry
      nodeTimers.value.set(nodeKey, {
        elapsedBase,
        elapsedTimestamp,
        lastKeyedBase,
        lastKeyedTimestamp
      })
    })
  } else {
    connectedNodes.value = []
    totalNodes.value = 0
    displayedNodes.value = 0
    // Clear timers when no nodes
    nodeTimers.value.clear()
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

// Format last keyed time with real-time updates
const formatLastKeyed = (lastKeyed: string | null | undefined, index: number): string => {
  // Access timerTick to trigger reactivity
  const _ = timerTick.value
  
  if (!lastKeyed || lastKeyed === 'N/A' || lastKeyed === 'n/a') {
    return 'N/A'
  }
  
  // If it's -1, return "Never"
  if (lastKeyed === -1 || lastKeyed === '-1' || lastKeyed === '-1') {
    return 'Never'
  }
  
  // Get the connected node to find its timer
  const connectedNode = connectedNodes.value[index]
  if (!connectedNode) {
    return 'N/A'
  }
  
  const nodeKey = `${props.node.id}-${connectedNode.node}`
  const timer = nodeTimers.value.get(nodeKey)
  
  // Calculate current seconds since last keyed
  let currentSeconds: number | null = null
  if (timer && timer.lastKeyedBase !== null && timer.lastKeyedTimestamp !== null) {
    // Calculate elapsed time since we received the value
    const elapsedSinceUpdate = Math.floor((Date.now() - timer.lastKeyedTimestamp) / 1000)
    currentSeconds = timer.lastKeyedBase + elapsedSinceUpdate
  } else {
    // Fallback: try parsing the value directly
    const numValue = parseInt(lastKeyed)
    if (!isNaN(numValue) && numValue >= 0) {
      currentSeconds = numValue
    }
  }
  
  if (currentSeconds !== null) {
    // Convert seconds to HH:MM:SS format (matching original Supermon-ng)
    const hours = Math.floor(currentSeconds / 3600)
    const minutes = Math.floor((currentSeconds % 3600) / 60)
    const seconds = currentSeconds % 60
    
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
  }
  
  // If it's already a formatted string, return as is
  return String(lastKeyed)
}

// Format elapsed time with real-time updates
const formatElapsed = (elapsed: string | null | undefined, index: number): string => {
  // Access timerTick to trigger reactivity
  const _ = timerTick.value
  
  if (!elapsed || elapsed === 'N/A' || elapsed === 'unknown' || elapsed === '') {
    return 'N/A'
  }
  
  // Get the connected node to find its timer
  const connectedNode = connectedNodes.value[index]
  if (!connectedNode) {
    return 'N/A'
  }
  
  const nodeKey = `${props.node.id}-${connectedNode.node}`
  const timer = nodeTimers.value.get(nodeKey)
  
  // Calculate current elapsed time
  let currentSeconds: number | null = null
  if (timer && timer.elapsedBase !== null && timer.elapsedTimestamp !== null) {
    // Calculate elapsed time since we received the value
    // The backend sends the elapsed time since connection started
    // So we add the time that has passed since we received the update
    const elapsedSinceUpdate = Math.floor((Date.now() - timer.elapsedTimestamp) / 1000)
    currentSeconds = timer.elapsedBase + elapsedSinceUpdate
  } else {
    // Try parsing the value directly
    // Try parsing as HH:MM:SS
    const timeMatch = elapsed.match(/(\d+):(\d+):(\d+)/)
    if (timeMatch) {
      const hours = parseInt(timeMatch[1])
      const minutes = parseInt(timeMatch[2])
      const seconds = parseInt(timeMatch[3])
      currentSeconds = hours * 3600 + minutes * 60 + seconds
    } else {
      // Try parsing as seconds
      const seconds = parseInt(elapsed)
      if (!isNaN(seconds)) {
        currentSeconds = seconds
      }
    }
  }
  
  if (currentSeconds !== null) {
    // Convert seconds to HH:MM:SS format
    const hours = Math.floor(currentSeconds / 3600)
    const minutes = Math.floor((currentSeconds % 3600) / 60)
    const secs = currentSeconds % 60
    
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
  }
  
  // If it's already a formatted string, return as is
  return String(elapsed)
}

// Event handling for modal links
const handleModalLinkClick = (event: Event) => {
  const target = event.target as HTMLElement
  if (target.classList.contains('bubble-chart-modal-link')) {
    event.preventDefault()
    event.stopPropagation()
    openBubbleChartModal()
  } else if (target.classList.contains('lsnod-modal-link')) {
    event.preventDefault()
    event.stopPropagation()
    openLsnodModal()
  }
}

// Set up real-time timer updates
let timerInterval: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  // Use a more specific selector to avoid conflicts
  const tableElement = document.querySelector(`#table_${props.node.id}`)
  if (tableElement) {
    tableElement.addEventListener('click', handleModalLinkClick)
  }
  
  // Start timer for real-time updates (update every second)
  timerInterval = setInterval(() => {
    timerTick.value = Date.now()
  }, 1000)
})

onUnmounted(() => {
  const tableElement = document.querySelector(`#table_${props.node.id}`)
  if (tableElement) {
    tableElement.removeEventListener('click', handleModalLinkClick)
  }
  
  // Clear timer interval
  if (timerInterval) {
    clearInterval(timerInterval)
    timerInterval = null
  }
  
  // Clear timers
  nodeTimers.value.clear()
})

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
  width: auto;
  min-width: 50%;
  max-width: 100%;
  margin: 0 auto;
  background: transparent;
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

.gridtable-large {
  font-family: verdana, arial, sans-serif;
  font-size: 22px;
  font-weight: bold;
  color: var(--text-color);
  padding: 4px;
  border-collapse: collapse;
  border-radius: 8px;
  overflow: hidden;
}

/* Node number cell - matches original Supermon-ng styling */
.nodeNum {
  color: var(--link-color);
  text-decoration: none;
  transition: color 0.3s ease;
  cursor: pointer;
  user-select: none;
}

.nodeNum:hover {
  color: var(--link-hover);
  font-weight: bold;
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
  margin-top: 0;
  font-weight: normal;
}

/* Ensure proper centering for header status row */
.gridtable tr.rColor td {
  text-align: center;
  vertical-align: middle;
}

/* Ensure proper centering for local node number in header status row */
.gridtable tr.rColor td:first-child {
  text-align: center !important;
  vertical-align: middle !important;
  line-height: normal !important;
}

/* Specific styling for local node number */
.gridtable .local-node-number {
  text-align: center !important;
  vertical-align: middle !important;
  line-height: normal !important;
  height: 100% !important;
  display: table-cell !important;
}

/* Center local node information column */
.gridtable tr.rColor td:nth-child(2),
.gridtable tr.cColor td:nth-child(2),
.gridtable tr.tColor td:nth-child(2) {
  text-align: center !important;
}

/* Force center all content in local node information column */
.gridtable tr.rColor td:nth-child(2) *,
.gridtable tr.cColor td:nth-child(2) *,
.gridtable tr.tColor td:nth-child(2) * {
  text-align: center !important;
}

/* Ultra-specific centering for local node info */
.gridtable tr.rColor td:nth-child(2),
.gridtable tr.cColor td:nth-child(2),
.gridtable tr.tColor td:nth-child(2),
.gridtable tr.rColor td:nth-child(2) b,
.gridtable tr.cColor td:nth-child(2) b,
.gridtable tr.tColor td:nth-child(2) b,
.gridtable tr.rColor td:nth-child(2) span,
.gridtable tr.cColor td:nth-child(2) span,
.gridtable tr.tColor td:nth-child(2) span,
.gridtable tr.rColor td:nth-child(2) i,
.gridtable tr.cColor td:nth-child(2) i,
.gridtable tr.tColor td:nth-child(2) i {
  text-align: center !important;
  display: block !important;
}

/* Local node table styling with custom theme support */
.gridtable tr.rColor {
  background-color: var(--local-node-header) !important;
  color: var(--local-node-header-text) !important;
}

.gridtable tr.rColor td {
  background-color: var(--local-node-header) !important;
  color: var(--local-node-header-text) !important;
  border-color: var(--local-node-border) !important;
}

.gridtable tr.cColor {
  background-color: var(--local-node-bg) !important;
  color: var(--local-node-text) !important;
}

.gridtable tr.cColor td {
  background-color: var(--local-node-bg) !important;
  color: var(--local-node-text) !important;
  border-color: var(--local-node-border) !important;
}

/* Connected nodes table styling with custom theme support */
.gridtable tr:not(.rColor):not(.cColor):not(.gColor):not(.tColor):not(.bColor):not(.lColor):not(.rxkColor):not(.firstNodeColor) {
  background-color: var(--table-bg) !important;
  color: var(--text-color) !important;
}

.gridtable tr:not(.rColor):not(.cColor):not(.gColor):not(.tColor):not(.bColor):not(.lColor):not(.rxkColor):not(.firstNodeColor) td {
  background-color: var(--table-bg) !important;
  color: var(--text-color) !important;
  border-color: transparent !important;
}


.gridtable th {
  padding: 2px 4px;
  background-color: var(--table-header-bg);
  color: var(--primary-color);
  font-size: 12px;
  text-align: center;
  border-bottom: 1px solid #000000;
}

.gridtable td {
  padding: 2px 4px;
  background-color: var(--container-bg);
  font-size: 11px;
  line-height: 1.2;
}

/* Table row colors - matching original Supermon-ng with theme support */
/* Removed duplicate rule - handled by status colors above */

.gridtable tr.cColor td,
.gridtable tr.cColor td a {
  background-color: var(--local-node-bg) !important;
  font-weight: bold;
  color: var(--local-node-text) !important;
  text-align: center !important;
}

/* Status colors for local node - dedicated COS/PTT theme colors */
.gridtable tr.gColor td {
  background-color: var(--status-idle) !important;
  font-weight: bold;
  color: var(--local-node-text) !important;
}

.gridtable tr.tColor td {
  background-color: var(--status-ptt) !important;
  font-weight: bold;
  color: var(--background-color) !important;
}

.gridtable tr.bColor td {
  background-color: var(--status-full-duplex) !important;
  font-weight: bold;
  color: var(--background-color) !important;
}

.gridtable tr.lColor td {
  background-color: var(--status-cos) !important;
  font-weight: bold;
  color: var(--background-color) !important;
}

.gridtable tr.rxkColor td {
  background-color: var(--status-receiving) !important;
  font-weight: bold;
  color: var(--background-color) !important;
}

.gridtable tr.firstNodeColor td {
  background-color: var(--status-first-node) !important;
  font-weight: bold;
  color: var(--background-color) !important;
}

/* Always center Node Information column content */
.gridtable td:nth-child(2) {
  text-align: center !important;
}


/* Enable horizontal scrolling for tables on mobile to show all content */
.node-table-wrapper {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
</style>
