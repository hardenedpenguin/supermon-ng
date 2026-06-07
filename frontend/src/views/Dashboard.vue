<template>
  <div class="dashboard">
    <!-- Header Section (mimics header.inc structure) -->
    <div class="header" :style="headerBackgroundUrl ? { backgroundImage: headerBackgroundUrl } : {}">
      <!-- Main Title -->
      <div class="header-title" :style="headerTitleStyle">
        <a href="#"><i>{{ headerTitle }}</i></a>
      </div>
      
      <!-- Call Sign -->
      <div v-if="systemInfo?.callsign" class="header-title2" :style="callsignStyle">
        <a v-if="systemInfo?.myUrl" :href="getCleanUrl(systemInfo.myUrl)" :target="shouldOpenInNewTab(systemInfo.myUrl) ? '_blank' : '_self'">
          <i>{{ systemInfo.callsign }}</i>
        </a>
        <i v-else>{{ systemInfo.callsign }}</i>
      </div>
      
      <!-- Location and Title -->
      <div class="header-tag">
        <i v-html="sanitizeHtml(formatHeaderTag())"></i>
      </div>
      

      
      <!-- Login/Logout positioning -->
      <div v-if="!appStore.isAuthenticated" class="header-login">
        <a href="#" @click.prevent="showLoginModal = true">Login</a>
      </div>
      <div v-else class="header-logout">
        <div class="header-logout2">
          <a href="#" @click.prevent="handleLogout">Logout {{ appStore.user?.name }}</a>
        </div>
      </div>
      
      <!-- Custom Logo (if configured) -->
      <div v-if="systemInfo?.logoName" class="custom-logo" :style="customLogoStyle">
        <a v-if="systemInfo?.logoUrl" :href="getCleanUrl(systemInfo.logoUrl)" :target="shouldOpenInNewTab(systemInfo.logoUrl) ? '_blank' : '_self'">
          <img :src="appUrl(`user_files/${systemInfo.logoName}`)" :style="{ width: systemInfo?.logoSize || '15%', border: '0px' }" alt="Custom Logo">
        </a>
        <img v-else :src="appUrl(`user_files/${systemInfo.logoName}`)" :style="{ width: systemInfo?.logoSize || '15%', border: '0px' }" alt="Custom Logo">
      </div>

      <!-- AllStar Logo (default or if no custom logo) -->
      <div v-if="!systemInfo?.logoName" class="header-img">
        <a :href="systemInfo?.logoUrl ? getCleanUrl(systemInfo.logoUrl) : 'https://www.allstarlink.org'" :target="systemInfo?.logoUrl ? (shouldOpenInNewTab(systemInfo.logoUrl) ? '_blank' : '_self') : '_blank'">
          <img src="/allstarlink.jpg" width="70%" style="border: 0px;" alt="Allstar Logo">
        </a>
      </div>
    </div>

    <!-- Menu Component -->
    <Menu @node-selection="handleNodeSelection" />

    <ConnectionStatus :node-ids="activeMonitoringNodeIds" />

    <!-- Date and Time Display -->
    <div v-if="showDateTime" class="datetime-display">
      {{ currentDateTime }}
    </div>

    <!-- Welcome Message -->
    <div v-if="welcomeMessage" class="welcome-message" v-html="sanitizeHtml(welcomeMessage)"></div>

    <DashboardConnectPanel
      v-if="hasControlPermissions"
      :displayed-nodes="displayedNodes"
      :target-node="targetNode"
      :selected-local-node="selectedLocalNode"
      :perm-connect="permConnect"
      v-bind="nodeControls"
      @update:target-node="targetNode = $event"
      @update:selected-local-node="selectedLocalNode = $event"
      @update:perm-connect="permConnect = $event"
      @action="handleControlAction"
    />

    <!-- Bottom Utility Buttons (matches original exactly) -->
    <p class="button-container">
      <input type="button" class="submit" value="Display Configuration" @click="showDisplayConfigModal = true">
      <input v-if="systemInfo?.dvmUrl" type="button" class="submit" value="Digital Dashboard" @click="openDigitalDashboard">
      <input v-if="appStore.isAuthenticated && appStore.hasPermission('SYSINFUSER')" type="button" class="submit" value="Node Status" @click="openNodeStatus">
      <input v-if="appStore.isAuthenticated && appStore.hasPermission('SYSINFUSER')" type="button" class="submit" value="System Info" @click="systeminfo">
      <input v-if="appStore.isAuthenticated && appStore.hasPermission('SYSINFUSER')" type="button" class="submit" value="Service Health" @click="showServiceHealthModal = true">
      <input v-if="appStore.isAuthenticated && appStore.hasPermission('CFGEDUSER')" type="button" class="submit" value="Config Backup" @click="showConfigBackupModal = true">
      <input v-if="appStore.isAuthenticated && displayedNodes.length > 0" type="button" class="submit" value="Link Map" @click="showLinkMapModal = true">
    </p>

    <!-- Node Tables (mimics link.php structure) -->
    <div style="text-align: center;">
      <div v-if="displayedNodes.length === 0" class="no-nodes-message">
        Select a node or group from the menu to display node tables
      </div>
      <div v-else class="node-tables-container">
        <NodeTable 
          v-for="(node, index) in displayedNodes"
          :key="String(node.id)"
          :node="{ id: String(node.id), info: (node as NodeType).info, callsign: (node as NodeType).callsign }"
          :show-detail="true"
          :astdb="realTimeStore.astdb"
          :config="realTimeStore.nodeConfig"
          @node-click="handleNodeClick"
          @add-favorite="handleAddFavoriteFromLink"
        />
      </div>
    </div>

    <!-- Footer Info -->
    <div class="clearer"></div>
    
    <div v-if="systemInfo?.maintainer" id="footer">
      <b>System maintained by: <i>{{ systemInfo.maintainer }}</i></b>
    </div>

    <!-- Donate Button Section -->
    <div id="donate-section" style="margin-top: 10px; text-align: center;">
      <button id="donatebutton" class="submit-large" @click="openDonatePopup" style="background-color: #6b4ce6; color: white; border: none; padding: 12px 24px; font-size: 1.1em; font-weight: bold; border-radius: 6px; cursor: pointer; transition: background-color 0.3s ease;">
        💝 Support This Project
      </button>
    </div>

    <!-- Login Modal -->
    <div v-if="showLoginModal" class="login-modal" @click="showLoginModal = false">
      <div class="login-modal-content" @click.stop>
        <LoginForm @login-success="handleLoginSuccess" @login-cancel="showLoginModal = false" />
      </div>
    </div>

    <!-- Display Configuration Modal -->
    <DisplayConfig 
      v-model:open="showDisplayConfigModal"
      @settings-updated="handleDisplaySettingsUpdated"
    />

    <!-- Add Favorite Modal -->
    <AddFavorite 
      v-model:isVisible="showAddFavoriteModal"
      :node-number="addFavoriteTarget || targetNode"
      :local-node="addFavoriteLocal || selectedLocalNode"
      :prefer-node-specific="addFavoriteNodeSpecific"
      @favorite-added="handleFavoriteAdded"
    />

            <!-- Delete Favorite Modal -->
        <DeleteFavorite 
          v-model:open="showDeleteFavoriteModal"
          @favorite-deleted="handleFavoriteDeleted"
        />
        
        <!-- Favorites Modal -->
        <Favorites 
          v-model:open="showFavoritesModal"
          :local-node="selectedLocalNode || selectedNode || String(displayedNodes[0]?.id || '')"
          @command-executed="handleCommandExecuted"
        />
        
        <!-- AST Log Modal -->
        <AstLog v-model:open="showAstLogModal" />
        
        <!-- AST Lookup Modal -->
        <AstLookup v-model:open="showAstLookupModal" />
        
        <!-- Bubble Chart Modal -->
        <BubbleChart v-model:open="showBubbleChartModal" :local-node="targetNode" />
        
        <!-- Control Panel Modal -->
        <ControlPanel v-model:isVisible="showControlPanelModal" :local-node="targetNode || selectedLocalNode || selectedNode || String(displayedNodes[0]?.id || '')" />
        
                  <!-- RPT Stats Modal -->
          <RptStats v-model:isVisible="showRptStatsModal" :node-number="targetNode" />
          
          <!-- CPU Stats Modal -->
          <CpuStats v-model:isVisible="showCpuStatsModal" />
          
          <!-- Database Modal -->
          <Database 
            v-model:isVisible="showDatabaseModal" 
            :localnode="selectedLocalNode || selectedNode || String(displayedNodes[0]?.id || '')" 
          />
          
          <!-- Donate Modal -->
          <Donate v-model:isVisible="showDonateModal" />
          
          <!-- ExtNodes Modal -->
          <ExtNodes v-model:isVisible="showExtNodesModal" />
          
          <!-- FastRestart Modal -->
          <FastRestart 
            v-model:isVisible="showFastRestartModal" 
            :localnode="selectedLocalNode || selectedNode" 
          />
          
          <!-- IRLP Log Modal -->
          <IRLPLog v-model:isVisible="showIrlpLogModal" />
          
          <!-- Linux Log Modal -->
          <LinuxLog v-model:isVisible="showLinuxLogModal" />
          
          <!-- Ban/Allow Modal -->
          <BanAllow 
            v-model:isVisible="showBanAllowModal" 
            :available-nodes="availableNodes"
            :default-node="selectedNode"
            :displayed-nodes="displayedNodes"
          />
          
          <!-- Pi GPIO Modal -->
          <PiGPIO v-model:isVisible="showPiGPIOModal" />
          
          <!-- Reboot Modal -->
          <Reboot v-model:isVisible="showRebootModal" />
          
          <!-- SMLog Modal -->
          <SMLog v-model:isVisible="showSMLogModal" />
          
          <!-- Stats Modal -->
          <Stats v-model:isVisible="showStatsModal" :localnode="targetNode" />
          
          <!-- Web Access Log Modal -->
          <WebAccLog v-model:isVisible="showWebAccLogModal" />
          
          <!-- Web Error Log Modal -->
          <WebErrLog v-model:isVisible="showWebErrLogModal" />
    
    <!-- Voter Modal -->
    <Voter :show="showVoterModal" @close="showVoterModal = false" />
    
    <!-- DVSwitch Modal -->
    <DVSwitchModal 
      v-model:isVisible="showDvswitchModal" 
      :local-node="selectedLocalNode || selectedNode || String(displayedNodes[0]?.id || '')" 
    />
    
    <!-- ConfigEditor Modal -->
    <ConfigEditor v-model:open="showConfigEditorModal" />
    
    <!-- SystemInfo Modal -->
    <SystemInfo v-model:open="showSystemInfoModal" />
    <ServiceHealth v-model:open="showServiceHealthModal" />
    <ConfigBackup v-model:open="showConfigBackupModal" />
    <LinkMap v-model:open="showLinkMapModal" :node-ids="activeMonitoringNodeIds.map(String)" />

    <!-- Digital Dashboard Modal -->
    <DigitalDashboard
      v-model:isVisible="showDigitalDashboardModal"
      :url="systemInfo?.dvmUrl || ''"
    />

    <!-- Node Status Modal -->
    <NodeStatus
      v-model:isVisible="showNodeStatusModal"
      @close="showNodeStatusModal = false"
    />
    
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick, onBeforeMount, defineAsyncComponent } from 'vue'
import { useAppStore } from '@/stores/app'
import { useRealTimeStore } from '@/stores/realTime'
import { api } from '@/utils/api'
import { appUrl } from '@/utils/basePath'
import { sanitizeHtml } from '@/utils/sanitize'
import type { Node as NodeType } from '@/types'
import NodeTable from '@/components/NodeTable.vue'
import LoginForm from '@/components/LoginForm.vue'
import Menu from '@/components/Menu.vue'
import ConnectionStatus from '@/components/ConnectionStatus.vue'
import DashboardConnectPanel from '@/components/DashboardConnectPanel.vue'
import { useToast } from '@/composables/useToast'
import { useNodeControls } from '@/composables/useNodeControls'
const DisplayConfig = defineAsyncComponent(() => import('@/components/DisplayConfig.vue'))
const AddFavorite = defineAsyncComponent(() => import('@/components/AddFavorite.vue'))
const DeleteFavorite = defineAsyncComponent(() => import('@/components/DeleteFavorite.vue'))
const Favorites = defineAsyncComponent(() => import('@/components/Favorites.vue'))
const DVSwitchModal = defineAsyncComponent(() => import('@/components/DVSwitchModal.vue'))
const AstLog = defineAsyncComponent(() => import('@/components/AstLog.vue'))
const AstLookup = defineAsyncComponent(() => import('@/components/AstLookup.vue'))
const BubbleChart = defineAsyncComponent(() => import('@/components/BubbleChart.vue'))
const ControlPanel = defineAsyncComponent(() => import('@/components/ControlPanel.vue'))
const RptStats = defineAsyncComponent(() => import('@/components/RptStats.vue'))
const CpuStats = defineAsyncComponent(() => import('@/components/CpuStats.vue'))
const Database = defineAsyncComponent(() => import('@/components/Database.vue'))
const Donate = defineAsyncComponent(() => import('@/components/Donate.vue'))
const ExtNodes = defineAsyncComponent(() => import('@/components/ExtNodes.vue'))
const FastRestart = defineAsyncComponent(() => import('@/components/FastRestart.vue'))
const IRLPLog = defineAsyncComponent(() => import('@/components/IRLPLog.vue'))
const LinuxLog = defineAsyncComponent(() => import('@/components/LinuxLog.vue'))
const BanAllow = defineAsyncComponent(() => import('@/components/BanAllow.vue'))
const PiGPIO = defineAsyncComponent(() => import('@/components/PiGPIO.vue'))
const Reboot = defineAsyncComponent(() => import('@/components/Reboot.vue'))
const SMLog = defineAsyncComponent(() => import('@/components/SMLog.vue'))
const Stats = defineAsyncComponent(() => import('@/components/Stats.vue'))
const WebAccLog = defineAsyncComponent(() => import('@/components/WebAccLog.vue'))
const WebErrLog = defineAsyncComponent(() => import('@/components/WebErrLog.vue'))
const Voter = defineAsyncComponent(() => import('@/components/Voter.vue'))
const ConfigEditor = defineAsyncComponent(() => import('@/components/ConfigEditor.vue'))
const SystemInfo = defineAsyncComponent(() => import('@/components/SystemInfo.vue'))
const ServiceHealth = defineAsyncComponent(() => import('@/components/ServiceHealth.vue'))
const ConfigBackup = defineAsyncComponent(() => import('@/components/ConfigBackup.vue'))
const LinkMap = defineAsyncComponent(() => import('@/components/LinkMap.vue'))
const DigitalDashboard = defineAsyncComponent(() => import('@/components/DigitalDashboard.vue'))
const NodeStatus = defineAsyncComponent(() => import('@/components/NodeStatus.vue'))


const appStore = useAppStore()
const realTimeStore = useRealTimeStore()

// Reactive data
const selectedNode = ref('')
const selectedLocalNode = ref('')
const targetNode = ref('')
const permConnect = ref(false)

// Date and time display
const currentDateTime = ref('')
const showDateTime = ref(true)
let dateTimeInterval: ReturnType<typeof setInterval> | null = null

const updateDateTime = () => {
  const now = new Date()
  const options: Intl.DateTimeFormatOptions = {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: true
  }
  currentDateTime.value = now.toLocaleString('en-US', options)
}

const loadDisplaySettingsFromCookies = () => {
  const cookies = document.cookie.split(';').reduce((acc, cookie) => {
    const [key, value] = cookie.trim().split('=')
    if (key.startsWith('display-data[')) {
      const cleanKey = key.replace('display-data[', '').replace(']', '')
      acc[cleanKey] = value
    }
    return acc
  }, {} as Record<string, string>)

  showDateTime.value = cookies['show-date-time'] !== '0'
}
const showLoginModal = ref(false)
const showDisplayConfigModal = ref(false)
const showAddFavoriteModal = ref(false)
const addFavoriteTarget = ref('')
const addFavoriteLocal = ref('')
const addFavoriteNodeSpecific = ref(false)
const toast = useToast()
const showDeleteFavoriteModal = ref(false)
const showFavoritesModal = ref(false)
const showAstLogModal = ref(false)
const showAstLookupModal = ref(false)
const showBubbleChartModal = ref(false)
const showControlPanelModal = ref(false)
const showRptStatsModal = ref(false)
const showCpuStatsModal = ref(false)
const showDatabaseModal = ref(false)
const showDonateModal = ref(false)
  const showExtNodesModal = ref(false)
  const showFastRestartModal = ref(false)
  const showIrlpLogModal = ref(false)
  const showLinuxLogModal = ref(false)
  const showBanAllowModal = ref(false)
  const showPiGPIOModal = ref(false)
  const showRebootModal = ref(false)
  const showSMLogModal = ref(false)
  const showStatsModal = ref(false)
const showWebAccLogModal = ref(false)
const showWebErrLogModal = ref(false)
const showVoterModal = ref(false)
const showConfigEditorModal = ref(false)
const showSystemInfoModal = ref(false)
const showServiceHealthModal = ref(false)
const showConfigBackupModal = ref(false)
const showLinkMapModal = ref(false)
const showDvswitchModal = ref(false)


const systemInfo = ref<any>(null)
const headerBackground = ref<string | null>(null)
const showDigitalDashboardModal = ref(false)
const showNodeStatusModal = ref(false)
const databaseStatus = ref<any>(null)
const isLoadingDefaultNodes = ref(true)

// Computed properties
const hasControlPermissions = computed(() => {
  // Only show controls if user is authenticated
  if (!appStore.isAuthenticated) {
    return false
  }
  
  return appStore.hasPermission('CONNECTUSER') || 
         appStore.hasPermission('DISCONNECTUSER') || 
         appStore.hasPermission('MONITORUSER') || 
         appStore.hasPermission('PERMUSER')
})


const availableNodes = computed(() => {
  return realTimeStore.nodes
})



const displayedNodes = computed((): NodeType[] => {
  // If no node is selected, show empty array until default is loaded
  // This prevents showing all available nodes on initial page load
  if (!selectedNode.value) {
    return []
  }
  
  const selectedNodeStr = String(selectedNode.value)

  // Handle comma-separated node IDs (for groups)
  if (selectedNodeStr.includes(',')) {
    const nodeIds = selectedNodeStr.split(',').map(id => id.trim())

    const filteredNodes = availableNodes.value.filter(node => 
      nodeIds.includes(String(node.id)) || 
      nodeIds.includes(String(node.node_number || node.id))
    )
    
    // Convert node IDs to strings for consistency
    return filteredNodes.map(node => ({
      ...node,
      id: String(node.id)
    }))
  }
  
  // Handle single node ID
  const filteredNodes = availableNodes.value.filter(node => 
    String(node.id) === selectedNodeStr || 
    String(node.node_number || node.id) === selectedNodeStr
  )

  // If no nodes found but we have a selected node, create a placeholder node
  // This handles cases where the node exists in the menu but not yet loaded in availableNodes
  if (filteredNodes.length === 0 && selectedNodeStr && availableNodes.value.length > 0) {
    // Create a temporary node entry for the selected node
    const tempNode: NodeType = {
      id: selectedNodeStr, // Keep as string to match Node type
      node_number: parseInt(selectedNodeStr),
      callsign: `Node ${selectedNodeStr}`,
      description: 'Loading...',
      location: 'Unknown',
      status: 'unknown',
      last_heard: undefined,
      connected_nodes: [],
      cos_keyed: 0,
      tx_keyed: 0,
      cpu_temp: undefined,
      alert: undefined,
      wx: undefined,
      disk: undefined,
      is_online: false,
      is_keyed: false,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
      info: `Node ${selectedNodeStr}`
    }
    return [tempNode]
  }

  // Convert node IDs to strings for consistency
  return filteredNodes.map(node => ({
    ...node,
    id: String(node.id)
  }))
})

const nodeControls = useNodeControls({
  targetNode,
  selectedLocalNode,
  permConnect,
  selectedNode,
  displayedNodes,
  availableNodes,
})

const headerBackgroundUrl = computed(() => {
  // Don't set background until we've checked for custom header
  // This prevents the default from loading before we know if custom exists
  const backgroundUrl = headerBackground.value || systemInfo.value?.customHeaderBackground
  if (!backgroundUrl) {
    // Return null to prevent any background from loading until we know which one to use
    return null
  }
  return `url('${backgroundUrl}')`
})

const formatHeaderTag = () => {
  const parts = []
  
  if (systemInfo.value?.location) {
    parts.push(systemInfo.value.location)
  }
  
  if (systemInfo.value?.title2) {
    parts.push(systemInfo.value.title2)
  }
  
  if (systemInfo.value?.title3) {
    parts.push(systemInfo.value.title3)
  }
  
  return parts.join('<br>')
}

// Helper methods for URL handling (matching legacy header.inc behavior)
const getCleanUrl = (url: string): string => {
  // Remove trailing ">" if present (legacy format for target="_blank")
  return url.endsWith('>') ? url.slice(0, -1) : url
}

const shouldOpenInNewTab = (url: string): boolean => {
  // Check if URL ends with ">" (legacy format for target="_blank")
  return url.endsWith('>')
}

// Computed property for custom logo positioning
const customLogoStyle = computed(() => {
  if (!systemInfo.value?.logoName) return {}
  
  const styles: Record<string, string> = {
    position: 'absolute',
    border: '0px'
  }
  
  if (systemInfo.value.logoPositionTop) {
    styles.top = systemInfo.value.logoPositionTop
  }
  
  if (systemInfo.value.logoPositionRight) {
    styles.right = systemInfo.value.logoPositionRight
  }
  
  return styles
})

// Computed property for welcome message (depends on authentication status)
const welcomeMessage = computed(() => {
  if (appStore.isAuthenticated && systemInfo.value?.welcomeMsgLogged) {
    return systemInfo.value.welcomeMsgLogged
  } else if (!appStore.isAuthenticated && systemInfo.value?.welcomeMsg) {
    return systemInfo.value.welcomeMsg
  }
  return null
})

const headerTitle = computed(() => {
  if (appStore.isAuthenticated && systemInfo.value?.titleLogged) {
    return systemInfo.value.titleLogged
  } else if (!appStore.isAuthenticated && systemInfo.value?.titleNotLogged) {
    return systemInfo.value.titleNotLogged
  }
  // Fallback to default titles if API doesn't provide them
  return appStore.isAuthenticated ? 
    'Supermon-ng AllStar Manager' : 
    'Supermon-ng AllStar Monitor'
})

// Computed property for header title color (title_logged or title_not_logged)
const headerTitleStyle = computed(() => {
  const color = appStore.isAuthenticated 
    ? systemInfo.value?.titleLoggedColor 
    : systemInfo.value?.titleNotLoggedColor
  
  if (color) {
    return { color: color }
  }
  return {}
})

// Computed property for callsign color
const callsignStyle = computed(() => {
  if (systemInfo.value?.callsignColor) {
    return { color: systemInfo.value.callsignColor }
  }
  return {}
})

// Watcher to update selectedLocalNode when selectedNode changes
watch(selectedNode, (newValue) => {
  if (newValue) {
    const selectedNodeStr = String(newValue)
    if (selectedNodeStr.includes(',')) {
      // Group mode - set first node as local node
      const nodeIds = selectedNodeStr.split(',').map(id => id.trim())
      if (nodeIds.length > 0) {
        selectedLocalNode.value = nodeIds[0]
      }
    } else {
      // Single node mode
      selectedLocalNode.value = selectedNodeStr
    }
  }
}, { immediate: true })

// Watcher to update selectedLocalNode when displayedNodes changes (for single node case)
watch(displayedNodes, (newNodes) => {
  if (newNodes.length === 1 && !selectedLocalNode.value) {
    selectedLocalNode.value = String(newNodes[0].id)
  }
}, { immediate: true })

// Watcher to update document title when systemInfo changes
watch(systemInfo, (newSystemInfo) => {
  if (newSystemInfo?.smServerName) {
    document.title = newSystemInfo.smServerName
  }
}, { immediate: true })

// Methods
const activeMonitoringNodeIds = computed(() => {
  const selectedNodeStr = String(selectedNode.value || '').trim()
  if (!selectedNodeStr) {
    return [...realTimeStore.monitoringNodes]
  }
  if (selectedNodeStr.includes(',')) {
    return selectedNodeStr.split(',').map((id) => id.trim()).filter(Boolean)
  }
  return [selectedNodeStr]
})

const onNodeChange = async () => {
  const selectedNodeStr = String(selectedNode.value).trim()
  if (!selectedNodeStr) {
    return
  }

  let nodeIds: string[]
  if (selectedNodeStr.includes(',')) {
    nodeIds = selectedNodeStr.split(',').map((id) => id.trim()).filter(Boolean)
    if (nodeIds.length > 0) {
      selectedLocalNode.value = nodeIds[0]
    }
  } else {
    nodeIds = [selectedNodeStr]
    selectedLocalNode.value = selectedNodeStr
  }

  await realTimeStore.syncMonitoringForSelection(nodeIds)
}

const handleControlAction = (action: string) => {
  switch (action) {
    case 'voter':
      showVoterModal.value = true
      break
    case 'astlookup':
      astlookup()
      break
    case 'rptstats':
      rptstats()
      break
    case 'bubble':
      bubble()
      break
    case 'control-panel':
      showControlPanelModal.value = true
      break
    case 'favorites':
      showFavoritesModal.value = true
      break
    case 'add-favorite':
      openAddFavoriteModal()
      break
    case 'delete-favorite':
      showDeleteFavoriteModal.value = true
      break
    case 'configeditor':
      configeditor()
      break
    case 'open-help':
      openHelp()
      break
    case 'open-wiki':
      openWiki()
      break
    case 'cpustats':
      cpustats()
      break
    case 'aststats':
      aststats()
      break
    case 'open-active-nodes':
      openActiveNodes()
      break
    case 'open-all-nodes':
      openAllNodes()
      break
    case 'database':
      database()
      break
    case 'linuxlog':
      linuxlog()
      break
    case 'astlog':
      astlog()
      break
    case 'webacclog':
      webacclog()
      break
    case 'weberrlog':
      weberrlog()
      break
    case 'astreload':
      astreload()
      break
    case 'astaron':
      astaron()
      break
    case 'astaroff':
      astaroff()
      break
    case 'fastrestart':
      fastrestart()
      break
    case 'reboot':
      reboot()
      break
    case 'openpigpio':
      openpigpio()
      break
    case 'openbanallow':
      openbanallow()
      break
    case 'dvswitch':
      showDvswitchModal.value = true
      break
  }
}

const astlookup = async () => {
  try {
    showAstLookupModal.value = true
  } catch (error) {
    // AST lookup error handled
  }
}

const rptstats = async () => {
  try {
    showRptStatsModal.value = true
  } catch (error) {
    // RPT stats error handled
  }
}



const bubble = async () => {
  try {
    showBubbleChartModal.value = true
  } catch (error) {
    // Bubble chart error handled
  }
}





const openDigitalDashboard = async () => {
  try {
    if (systemInfo.value?.dvmUrl) {
      showDigitalDashboardModal.value = true
    }
  } catch (error) {
    // Digital dashboard error handled
  }
}

const openNodeStatus = async () => {
  try {
    showNodeStatusModal.value = true
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error'
    alert('Error opening Node Status: ' + errorMessage)
  }
}


// Additional button methods to match original Supermon-ng
const configeditor = async () => {
  try {
    showConfigEditorModal.value = true
  } catch (error) {
    // Config editor error handled
  }
}



const astreload = async () => {
  try {
    // IAX2/Module reload is local only - no node selection needed
    const response = await api.post('/config/asterisk/reload', {})

    if (response.data.success) {
      alert('Asterisk configuration reload completed successfully!\n\n' + response.data.results.join('\n'))
    } else {
      alert('Error: ' + response.data.message)
    }
  } catch (error: any) {
    alert('Error executing Asterisk reload: ' + (error.response?.data?.message || error.message))
  }
}

const astaron = async () => {
  try {

    
    if (!confirm('Are you sure you want to START the AllStar service? This will bring the service online.')) {
      return
    }

    const response = await api.post('/config/asterisk/control', {
      action: 'start'
    })

    if (response.data.success) {
      alert('AllStar service started successfully!\n\n' + response.data.output.join('\n'))
    } else {
      alert('Error: ' + response.data.message)
    }
  } catch (error: any) {
    alert('Error starting AllStar service: ' + (error.response?.data?.message || error.message))
  }
}

const astaroff = async () => {
  try {

    
    if (!confirm('Are you sure you want to STOP the AllStar service? This will bring the service offline.')) {
      return
    }

    const response = await api.post('/config/asterisk/control', {
      action: 'stop'
    })

    if (response.data.success) {
      alert('AllStar service stopped successfully!\n\n' + response.data.output.join('\n'))
    } else {
      alert('Error: ' + response.data.message)
    }
  } catch (error: any) {
    alert('Error stopping AllStar service: ' + (error.response?.data?.message || error.message))
  }
}

const fastrestart = async () => {
  try {
    if (selectedLocalNode.value || selectedNode.value) {
      showFastRestartModal.value = true
    } else {
      alert('Please select a node first to perform fast restart.')
    }
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error'
    alert(`Failed to open FastRestart: ${errorMessage}`)
  }
}

const reboot = async () => {
  try {

    showRebootModal.value = true
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error'
    alert(`Failed to open Server Reboot: ${errorMessage}`)
  }
}

const openHelp = async () => {
  try {
    window.open('https://allstarlink.github.io/adv-topics/', 'AllStarHelp', 'status=no,location=no,toolbar=yes,width=800,height=600,left=100,top=100')
  } catch (error) {
    // Help link error handled
  }
}

const openWiki = async () => {
  try {
    window.open('https://wiki.allstarlink.org', 'AllStarWiki', 'status=no,location=no,toolbar=yes,width=800,height=600,left=100,top=100')
  } catch (error) {
    // Wiki link error handled
  }
}

const cpustats = async () => {
  try {

    showCpuStatsModal.value = true
  } catch (error) {
    // CPU Status error handled
  }
}

const aststats = async () => {
  try {
    // Prompt user for node number (like the original implementation)
    const nodeNumber = prompt('Enter node number for AllStar Status:')
    if (!nodeNumber || nodeNumber.trim() === '') {
      return
    }
    
    // Validate node number is numeric
    if (!/^\d+$/.test(nodeNumber.trim())) {
      alert('Please enter a valid numeric node number')
      return
    }
    
    // Set the target node and show the stats modal
    targetNode.value = nodeNumber.trim()
    showStatsModal.value = true
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error'
    alert(`Failed to open AllStar Statistics: ${errorMessage}`)
  }
}


const openActiveNodes = async () => {
  try {
    window.open('https://stats.allstarlink.org/', 'ActiveNodes', 'status=no,location=no,toolbar=yes,width=1200,height=800,left=100,top=100')
  } catch (error) {
    // Active nodes link error handled
  }
}

const openAllNodes = async () => {
  try {
    window.open('https://www.allstarlink.org/nodelist/', 'AllNodes', 'status=no,location=no,toolbar=yes,width=1200,height=800,left=100,top=100')
  } catch (error) {
    // All nodes link error handled
  }
}

const database = async () => {
  try {
    showDatabaseModal.value = true
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error'
    alert(`Failed to open Database: ${errorMessage}`)
  }
}

const openpigpio = async () => {
  try {

    showPiGPIOModal.value = true
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error'
    alert(`Failed to open Pi GPIO: ${errorMessage}`)
  }
}

const linuxlog = async () => {
  try {

    showLinuxLogModal.value = true
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error'
    alert(`Failed to open Linux Log: ${errorMessage}`)
  }
}

const astlog = async () => {
  try {

    showAstLogModal.value = true
  } catch (error) {
    // AST Log error handled
  }
}

const webacclog = async () => {
  try {

    showWebAccLogModal.value = true
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error'
    alert(`Failed to open Web Access Log: ${errorMessage}`)
  }
}

const weberrlog = async () => {
  try {

    showWebErrLogModal.value = true
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error'
    alert(`Failed to open Web Error Log: ${errorMessage}`)
  }
}

const openbanallow = async () => {
  try {

    showBanAllowModal.value = true
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error'
    alert(`Failed to open Ban/Allow: ${errorMessage}`)
  }
}

const systeminfo = async () => {
  try {
    showSystemInfoModal.value = true
  } catch (error) {
    // System info error handled
  }
}

const handleLoginSuccess = async () => {
  showLoginModal.value = false
  await appStore.checkAuth()

  // Reload node list from user-specific allmon.ini (e.g. anarchy-allmon.ini with remote hosts)
  realTimeStore.clearCache()
  await realTimeStore.initialize()

  try {
    const nodesResponse = await api.get('/config/nodes')
    if (nodesResponse.data.success && nodesResponse.data.data?.default_node) {
      selectedNode.value = nodesResponse.data.data.default_node
      await onNodeChange()
    }
  } catch (error) {
    // Configuration refetch error handled
  }
}

const handleLogout = async () => {
  await appStore.logout()

  realTimeStore.reset()
  realTimeStore.clearCache()
  await realTimeStore.initialize()

  try {
    const nodesResponse = await api.get('/config/nodes')
    if (nodesResponse.data.success && nodesResponse.data.data?.default_node) {
      selectedNode.value = nodesResponse.data.data.default_node
      await onNodeChange()
    }
  } catch (error) {
    // Configuration refetch error handled
  }
}

const openDonatePopup = () => {
  showDonateModal.value = true
}

  // Handle menu node selection
  const handleNodeSelection = async (nodeId: string) => {
    // Ensure realTime store is initialized and nodes are loaded
    if (realTimeStore.nodes.length === 0) {
      await realTimeStore.initialize()
    }
    
    selectedNode.value = nodeId
    await onNodeChange()
  }

const openAddFavoriteModal = () => {
  addFavoriteTarget.value = targetNode.value || ''
  addFavoriteLocal.value = selectedLocalNode.value || ''
  addFavoriteNodeSpecific.value = false
  showAddFavoriteModal.value = true
}

const handleAddFavoriteFromLink = (nodeId: string, localNodeId: string) => {
  addFavoriteTarget.value = nodeId
  addFavoriteLocal.value = localNodeId
  addFavoriteNodeSpecific.value = true
  showAddFavoriteModal.value = true
}

  // Handle node click from NodeTable (for quick target node selection)
  const handleNodeClick = (nodeId: string, localNodeId: string) => {
    // Set the clicked node as the target node
    targetNode.value = nodeId
    
    // Update the local node selection to the table owner
    selectedLocalNode.value = localNodeId
    

    
    // Scroll to the control panel
    const controlPanel = document.getElementById('connect_form')
    if (controlPanel) {
      controlPanel.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }
  }

  // Handle display settings updated
  const handleDisplaySettingsUpdated = (_settings: any) => {
    // Refresh the page to apply new display settings
    window.location.reload()
  }

  // Handle favorite added
  const handleFavoriteAdded = (result: { success?: boolean; message?: string }) => {
    if (result?.success) {
      toast.success(result.message || 'Favorite added')
    }
  }

  // Handle favorite deleted
  const handleFavoriteDeleted = (result: { success?: boolean; message?: string }) => {
    if (result?.success) {
      toast.success(result.message || 'Favorite deleted')
    }
  }

  // Handle command executed
  const handleCommandExecuted = (_result: any) => {
    // Could show a notification or refresh the page
    
  }

// Check for custom header background BEFORE component mounts to prevent default from loading
// We'll check quickly, but won't set default until we've confirmed from systemInfo
onBeforeMount(async () => {
  // Check for custom header immediately using fetch with very short timeout
  // This runs before the component is mounted
  const controller = new AbortController()
  const timeoutId = setTimeout(() => controller.abort(), 50) // 50ms timeout - fail very fast
  
  try {
    const response = await fetch(appUrl('api/v1/config/header-background'), {
      method: 'GET',
      cache: 'no-cache',
      signal: controller.signal
    })
    clearTimeout(timeoutId)
    if (response.ok) {
      // Custom header exists - set it immediately
      headerBackground.value = appUrl('api/v1/config/header-background')
      return
    }
  } catch (e) {
    clearTimeout(timeoutId)
    // Don't set default here - wait for systemInfo to confirm
    // This prevents default from loading if systemInfo will have the custom URL
  }
  // Don't set default here - let systemInfo be the source of truth
  // Only set default if systemInfo confirms there's no custom header
})

// Lifecycle
onMounted(async () => {
  // Initialize date/time display
  loadDisplaySettingsFromCookies()
  if (showDateTime.value) {
    updateDateTime()
    dateTimeInterval = setInterval(updateDateTime, 1000)
  }
  
  // Initialize realTime store
  await realTimeStore.initialize()
  
  const boot = appStore.bootstrapData
  if (boot?.systemInfo != null || boot?.databaseStatus != null || boot?.nodes != null) {
    if (boot.systemInfo) {
      systemInfo.value = boot.systemInfo
      if (systemInfo.value?.customHeaderBackground) {
        headerBackground.value = systemInfo.value.customHeaderBackground
      } else if (!headerBackground.value) {
        headerBackground.value = appUrl('background.jpg')
      }
      if (systemInfo.value?.smServerName) {
        document.title = systemInfo.value.smServerName
      }
    }
    if (boot.databaseStatus) databaseStatus.value = boot.databaseStatus
    const defaultNode = boot.nodes?.default_node
    if (defaultNode) {
      selectedNode.value = defaultNode
      await onNodeChange()
      await nextTick()
      isLoadingDefaultNodes.value = false
    } else {
      isLoadingDefaultNodes.value = false
    }
  } else {
    try {
      const [systemResponse, databaseResponse, nodesResponse] = await Promise.all([
        api.get('/config/system-info'),
        api.get('/database/status'),
        api.get('/config/nodes')
      ])
      
      if (systemResponse.data.success) {
        systemInfo.value = systemResponse.data.data || systemResponse.data
        if (systemInfo.value?.customHeaderBackground) {
          headerBackground.value = systemInfo.value.customHeaderBackground
        } else if (!headerBackground.value) {
          headerBackground.value = appUrl('background.jpg')
        }
        if (systemInfo.value?.smServerName) {
          document.title = systemInfo.value.smServerName
        }
      }
      
      if (databaseResponse.data.success) {
        databaseStatus.value = databaseResponse.data.data || databaseResponse.data
      }

      if (nodesResponse.data.success && nodesResponse.data.data?.default_node) {
        const defaultNode = nodesResponse.data.data.default_node
    
        
        selectedNode.value = defaultNode
        await onNodeChange()
        await nextTick()
        
        // Small delay to ensure the default node is properly set
        await new Promise(resolve => setTimeout(resolve, 100))
      } else {
        // No default node from backend, wait for user to select a node
        // This prevents showing all nodes initially
    
        if (realTimeStore.nodes.length > 0) {
          const firstNode = realTimeStore.nodes[0]
          selectedNode.value = String(firstNode.id)
          await onNodeChange()
        }
      }
      
      // Mark default nodes as loaded
      isLoadingDefaultNodes.value = false
    } catch (error) {
      console.error('Error loading system info and default nodes:', error)
      isLoadingDefaultNodes.value = false
    }
  }
})

onUnmounted(() => {
  // Clean up date/time interval
  if (dateTimeInterval) {
    clearInterval(dateTimeInterval)
    dateTimeInterval = null
  }
  
  // WebSocket connections are cleaned up automatically when stopMonitoring() is called
})

</script>

<style scoped>
.dashboard {
  max-width: 880px;
  width: 100%;
  margin: 0 auto;
  padding: 20px;
  background-color: var(--background-color);
  color: var(--text-color);
  /* Allow menu dropdowns to extend beyond dashboard boundaries */
  overflow: visible;
  /* Prevent horizontal scrolling on mobile */
  box-sizing: border-box;
}


/* Original Supermon-ng table container styling */
.fxwidth {
  width: auto;
  min-width: 50%;
  max-width: 880px;
  margin: 0 auto;
  border-collapse: collapse;
  overflow-x: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.fxwidth td {
  padding: 5px;
  vertical-align: top;
}

.no-nodes-message {
  text-align: center;
  padding: 40px;
  color: var(--text-color);
  font-size: 16px;
  background-color: var(--container-bg);
  border: 1px solid var(--primary-color);
  border-radius: 4px;
  margin: 20px 0;
}

.node-tables-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  width: 100%;
}

/* Header styling (mimics header.inc layout) */
.header {
  position: relative;
  width: calc(100% + 40px); /* Extend beyond dashboard padding */
  height: 164px;
  background-size: cover;
  background-position: center;
  border-radius: 8px;
  margin-bottom: 0;
  margin-left: -20px; /* Offset the dashboard padding */
  margin-right: -20px; /* Offset the dashboard padding */
  overflow: hidden;
}

/* Header Title (main title) */
.header-title {
  position: absolute;
  top: 3px;
  left: 25px; /* Adjusted for header extension */
  margin: 0;
  font-weight: bold;
  font-size: 1.1em;
  color: white;
  line-height: normal;
  letter-spacing: normal;
  font-family: "Lucida Grande", Lucida, Verdana, sans-serif;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
}

.header-title a,
.header-title a:link,
.header-title a:visited {
  text-decoration: none;
  color: inherit;
}

/* Header Title2 (call sign) */
.header-title2 {
  position: absolute;
  top: 5px;
  left: 570px; /* Adjusted for header extension */
  margin: 0;
  font-weight: bold;
  font-size: 1.1em;
  color: white;
  line-height: normal;
  font-family: "Lucida Grande", Lucida, Verdana, sans-serif;
  letter-spacing: normal;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
}

.header-title2 a,
.header-title2 a:link,
.header-title2 a:visited {
  text-decoration: none;
  color: inherit;
}

/* Header Tag (location and title) */
.header-tag {
  position: absolute;
  bottom: 2%;
  left: 30px; /* Adjusted for header extension */
  text-align: left;
  margin: 0;
  color: white;
  font-weight: bold;
  font-size: 1.1em;
  line-height: normal;
  font-family: "Lucida Grande", Lucida, Verdana, sans-serif;
  letter-spacing: normal;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
}

/* Header2 Tag (additional info when logged in) */
.header2-tag {
  position: absolute;
  top: 145px;
  left: 30px; /* Adjusted for header extension */
  margin: 0;
  font-size: 0.9em;
  color: white;
  font-weight: bold;
  line-height: normal;
  font-family: "Lucida Grande", Lucida, Verdana, sans-serif;
  letter-spacing: normal;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
}

/* Header Login (positioned like original) */
.header-login {
  position: absolute;
  top: 82%;
  left: 92%;
  font-size: 14px;
  font-family: Verdana, Arial, sans-serif;
  color: white;
  text-align: right;
  font-weight: bold;
  font-style: italic;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
}

.header-login a,
.header-login a:link,
.header-login a:visited {
  text-decoration: none;
  color: white;
}

.header-login a:hover {
  text-decoration: none;
  font-size: 16px;
  font-weight: bold;
  color: #00ff00;
}

/* Header Logout (positioned like original) */
.header-logout2 {
  position: absolute;
  bottom: 2%;
  left: 58%;
  text-align: right;
  width: 22em;
  font-size: 14px;
  font-family: Verdana, Arial, sans-serif;
  color: white;
  font-weight: bold;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
}

.header-logout a:link,
.header-logout a:visited {
  text-decoration: none;
  font-weight: bold;
  color: white;
}

.header-logout a:hover {
  text-decoration: none;
  font-size: 15px;
  font-weight: bold;
  color: #00ff00;
}

/* Header Image (AllStar logo) */
.header-img {
  position: absolute;
  top: 2px;
  right: 12px;
}

.custom-logo {
  /* Default positioning - will be overridden by inline styles from global.inc */
  position: absolute;
  top: 20%;
  right: 12%;
}

.datetime-display {
  margin: 15px auto;
  max-width: 880px;
  padding: 10px;
  text-align: center;
  font-size: 1.1em;
  font-weight: 500;
  color: var(--text-color, #e0e0e0);
  width: calc(100% + 40px);
  margin-left: -20px;
  margin-right: -20px;
}

.welcome-message {
  margin: 20px auto;
  max-width: 880px;
  padding: 10px;
  text-align: center;
}

/* Configuration buttons */
.config-buttons {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

.config-button {
  padding: 8px 16px;
  background-color: var(--background-color);
  color: var(--text-color);
  border: 1px solid var(--primary-color);
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.3s ease;
}

.config-button.active {
  background-color: var(--primary-color);
  color: white;
}

.config-button:hover {
  background-color: var(--primary-color);
  color: white;
}

/* Target node indicator styling */
.target-node-indicator {
  margin: 5px 0;
  padding: 5px 10px;
  background-color: var(--primary-color);
  color: white;
  border-radius: 4px;
  font-size: 14px;
  text-align: center;
  border: 1px solid var(--primary-color);
}

/* Control panel styling */
.control-panel {
  padding: 15px;
  background-color: var(--background-color);
  border: 1px solid var(--primary-color);
  border-radius: 4px;
  margin-bottom: 20px;
}

/* Original Supermon-ng layout styling */
.perm-label {
  font-size: 14px;
  font-weight: bold;
  color: var(--text-color);
  display: inline-block;
  margin-left: 5px;
}

.perm-label input[type="checkbox"] {
  margin: 0;
}

/* Perm input styling - matches original forms.css */
.perm-input-detailed {
  margin-top: 10px;
  font-size: 16px;
  border-radius: 15px;
}

.perm-input-large {
  font-size: 22px;
  margin-top: 15px;
}

.perm-label-detailed {
  font-size: 16px;
}

.perm-label-large {
  font-size: 22px;
}

.button-separator {
  border: none;
  height: 1px;
  background-color: var(--border-color);
  margin: 10px 0;
}

.section-label {
  background-color: var(--table-header-bg);
  color: var(--text-color);
  padding: 8px 16px;
  margin: 10px 0;
  border-radius: 15px;
  font-weight: bold;
  font-size: 14px;
  display: inline-block;
  border: 1px solid var(--border-color);
}

.button-container {
  margin-bottom: 5px;
  margin-top: 5px;
  text-align: center;
}

/* Button margin classes - matches original forms.css */
.button-margin-top {
  margin-top: 2px;
}

.button-margin-bottom {
  margin-bottom: 5px;
}

.submit, .submit-large, .submit2 {
  background-color: var(--table-header-bg);
  color: var(--primary-color);
  border: 1px solid var(--border-color);
  padding: 5px 10px;
  margin: 2px;
  border-radius: 15px;
  cursor: pointer;
  font-weight: bold;
  transition: all 0.3s ease;
  font-size: 14px;
}


.submit:hover, .submit-large:hover, .submit2:hover {
  background-color: var(--primary-color);
  color: var(--background-color);
  border-color: var(--primary-color);
  transform: translateY(-1px);
}

.submit2 {
  background-color: var(--table-header-bg);
}

.no-nodes-message {
  text-align: center;
  padding: 40px;
  color: var(--text-color);
  font-size: 16px;
  opacity: 0.7;
}

/* Footer styling - matching original footer.inc */
.clearer {
  clear: both;
}

#footer {
  text-align: center;
  margin: 20px 0;
  padding: 10px;
  color: var(--text-color);
  font-size: 14px;
}

/* Login modal */
.login-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.login-modal-content {
  background-color: var(--background-color);
  padding: 30px;
  border-radius: 8px;
  border: 2px solid var(--primary-color);
  max-width: 400px;
  width: 90%;
}

/* Responsive design */
@media (max-width: 768px) {
  .dashboard {
    padding: 10px;
  }
  
  .header {
    height: 150px;
    width: calc(100% + 20px); /* Adjust for mobile padding (10px each side) */
    margin-left: -10px; /* Offset mobile padding */
    margin-right: -10px; /* Offset mobile padding */
  }
  
  .header-title {
    font-size: 20px;
    left: 15px; /* Adjust for mobile header extension */
  }
  
  .header-title2 {
    left: 280px; /* Adjust callsign position for mobile */
  }
  
  .header-tag {
    left: 20px; /* Adjust for mobile header extension */
  }
  
  .header2-tag {
    left: 20px; /* Adjust for mobile header extension */
  }
  
  .nav-tabs {
    flex-direction: column;
  }
  
  .button-group {
    flex-direction: column;
  }
  
  .node-selection {
    flex-direction: column;
    align-items: stretch;
  }
}

/* Extra small screens */
@media (max-width: 480px) {
  .dashboard {
    padding: 5px;
  }
  
  .header {
    width: calc(100% + 10px); /* Adjust for extra small padding */
    margin-left: -5px;
    margin-right: -5px;
    height: 120px; /* Reduce height for small screens */
  }
  
  .header-title {
    font-size: 16px;
    left: 10px;
  }
  
  .header-title2 {
    left: 200px; /* Further adjust for small screens */
    font-size: 0.9em;
  }
  
  .header-tag {
    left: 10px;
    font-size: 0.9em;
  }
  
  .header2-tag {
    left: 10px;
    font-size: 0.8em;
  }
  
  .node-dropdown,
  .node-input {
    min-width: auto;
  }
}
</style>
