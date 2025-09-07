<template>
  <div class="dashboard">
    <!-- Header Section (mimics header.inc structure) -->
    <div class="header" :style="{ backgroundImage: headerBackgroundUrl }">
      <!-- Main Title -->
      <div class="header-title">
        <a href="#"><i>{{ systemInfo?.smServerName || 'Supermon-ng' }} V4.0.0 AllStar Monitor</i></a>
      </div>
      
      <!-- Call Sign -->
      <div class="header-title2">
        <a v-if="systemInfo?.myUrl" :href="getCleanUrl(systemInfo.myUrl)" :target="shouldOpenInNewTab(systemInfo.myUrl) ? '_blank' : '_self'">
          <i>{{ systemInfo?.callsign || 'CALLSIGN' }}</i>
        </a>
        <i v-else>{{ systemInfo?.callsign || 'CALLSIGN' }}</i>
      </div>
      
      <!-- Location and Title -->
      <div class="header-tag">
        <i v-html="formatHeaderTag()"></i>
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
          <img :src="`/user_files/${systemInfo.logoName}`" :style="{ width: systemInfo?.logoSize || '15%', border: '0px' }" alt="Custom Logo">
        </a>
        <img v-else :src="`/user_files/${systemInfo.logoName}`" :style="{ width: systemInfo?.logoSize || '15%', border: '0px' }" alt="Custom Logo">
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

    <!-- Welcome Message -->
    <div v-if="welcomeMessage" class="welcome-message" v-html="welcomeMessage"></div>

    <!-- Control Panel (matches original link.php structure exactly) -->
    <div v-if="hasControlPermissions" id="connect_form" style="text-align: center;">
      <!-- Node Selection (matches original layout exactly) -->
      <div v-if="displayedNodes.length > 0">
        <!-- Local node dropdown (only show if multiple nodes) -->
        <select v-if="displayedNodes.length > 1" v-model="selectedLocalNode" class="submit">
          <option v-for="node in displayedNodes" :key="node.id" :value="node.id" class="submit">
            {{ node.id }} => {{ node.info || 'Node not in database' }}
          </option>
        </select>
        
        <!-- Hidden input for single node -->
        <input v-else-if="displayedNodes.length === 1" type="hidden" v-model="selectedLocalNode">
        
        <!-- Hidden input for single node (matches original) -->
        <input v-else class="submit" type="hidden" :value="displayedNodes[0]">
        
        <!-- Node Input and Permission Controls -->
        <input
          v-model="targetNode"
          type="text"
          class="submit"
          placeholder="Node to connect/DTMF"
        />
        
        <label v-if="appStore.hasPermission('PERMUSER')" class="perm-label">
          Perm <input type="checkbox" v-model="permConnect" />
        </label>
        <br>
      </div>
      
      <!-- Primary Control Buttons (matches original order exactly) -->
      <input type="button" class="submit" value="Connect" @click="connect">
      <input type="button" class="submit" value="Disconnect" @click="disconnect">
      <input v-if="appStore.hasPermission('MONUSER')" type="button" class="submit" value="Monitor" @click="monitor">
      <input v-if="appStore.hasPermission('LMONUSER')" type="button" class="submit" value="Local Monitor" @click="localmonitor">
      <input type="button" class="submit" value="Voter" @click="showVoterModal = true">
      
      <!-- Secondary Control Buttons (matches original order exactly) -->
      <input v-if="appStore.hasPermission('DTMFUSER')" type="button" class="submit" value="DTMF" @click="dtmf">
      <input v-if="appStore.hasPermission('ASTLKUSER')" type="button" class="submit" value="Lookup" @click="astlookup">
      <input v-if="appStore.hasPermission('RSTATUSER')" type="button" class="submit" value="Rpt Stats" @click="rptstats">
      <input v-if="appStore.hasPermission('BUBLUSER')" type="button" class="submit" value="Bubble" @click="bubble">
      <input v-if="appStore.hasPermission('FAVUSER')" type="button" class="submit" value="Favorites" @click="showFavoritesModal = true">
      <input v-if="appStore.hasPermission('FAVUSER')" type="button" class="submit" value="Add Favorite" @click="showAddFavoriteModal = true">
      <input v-if="appStore.hasPermission('FAVUSER')" type="button" class="submit" value="Delete Favorite" @click="showDeleteFavoriteModal = true">
      
      <!-- Configuration Editor Section -->
      <hr class="button-separator">
      <input v-if="appStore.hasPermission('CFGEDUSER')" type="button" class="submit" value="Configuration Editor" @click="configeditor">
      <input v-if="appStore.hasPermission('HWTOUSER')" type="button" class="submit" value="AllStar How To's" @click="openHelp">
      <input v-if="appStore.hasPermission('WIKIUSER')" type="button" class="submit" value="AllStar Wiki" @click="openWiki">
      <input v-if="appStore.hasPermission('CSTATUSER')" type="button" class="submit" value="CPU Status" @click="cpustats">
      <input v-if="appStore.hasPermission('ASTATUSER')" type="button" class="submit" value="AllStar Status" @click="aststats">
      <input v-if="appStore.hasPermission('ACTNUSER')" type="button" class="submit" value="Active Nodes" @click="openActiveNodes">
      <input v-if="appStore.hasPermission('ALLNUSER')" type="button" class="submit" value="All Nodes" @click="openAllNodes">
      
      <!-- Database Section -->
      <input v-if="appStore.hasPermission('DBTUSER')" type="button" class="submit" value="Database" @click="database">
      <input v-if="appStore.hasPermission('LLOGUSER')" type="button" class="submit" value="Linux Log" @click="linuxlog">
      <input v-if="appStore.hasPermission('ASTLUSER')" type="button" class="submit" value="AST Log" @click="astlog">
      <input v-if="appStore.hasPermission('WLOGUSER')" type="button" class="submit" value="Web Access Log" @click="webacclog">
      <input v-if="appStore.hasPermission('WERRUSER')" type="button" class="submit" value="Web Error Log" @click="weberrlog">
      
      <!-- System Control Buttons -->
      <input v-if="appStore.hasPermission('ASTRELUSER')" type="button" class="submit" value="Iax/Rpt/DP RELOAD" @click="astreload">
      <input v-if="appStore.hasPermission('ASTSTRUSER')" type="button" class="submit" value="AST START" @click="astaron">
      <input v-if="appStore.hasPermission('ASTSTPUSER')" type="button" class="submit" value="AST STOP" @click="astaroff">
      <input v-if="appStore.hasPermission('FSTRESUSER')" type="button" class="submit" value="RESTART" @click="fastrestart">
      <input v-if="appStore.hasPermission('RBTUSER')" type="button" class="submit" value="Server REBOOT" @click="reboot">
      
      <!-- Additional System Buttons -->
      <input v-if="appStore.hasPermission('GPIOUSER')" type="button" class="submit" value="GPIO" @click="openpigpio">
      <input v-if="appStore.hasPermission('BANUSER')" type="button" class="submit" value="Access List" @click="openbanallow">
    </div>

    <!-- Bottom Utility Buttons (matches original exactly) -->
    <p class="button-container">
      <input type="button" class="submit" value="Display Configuration" @click="showDisplayConfigModal = true">
      <input v-if="systemInfo?.dvmUrl" type="button" class="submit" value="Digital Dashboard" @click="openDigitalDashboard">
      <input v-if="systemInfo?.hamclockEnabled" type="button" class="submit" value="HamClock" @click="openHamClock">
      <input v-if="appStore.isAuthenticated && appStore.hasPermission('SYSINFUSER')" type="button" class="submit" value="Node Status" @click="openNodeStatus">
      <input v-if="appStore.isAuthenticated && appStore.hasPermission('SYSINFUSER')" type="button" class="submit" value="System Info" @click="systeminfo">
    </p>

    <!-- Node Tables (mimics link.php structure) -->
    <div style="text-align: center;">
      <div v-if="displayedNodes.length === 0" class="no-nodes-message">
        Select a node or group from the menu to display node tables
      </div>
      <div v-else class="node-tables-container">
        <NodeTable 
          v-for="(node, index) in displayedNodes"
          :key="node.id"
          :node="node"
          :show-detail="true"
          :astdb="realTimeStore.astdb"
          :config="realTimeStore.nodeConfig"
          :ref="el => { if (el) nodeTableRefs[index] = el }"
          @node-click="handleNodeClick"
        />
      </div>
    </div>

    <!-- Footer Info -->
    <div class="clearer"></div>
    
    <div id="footer">
      <b>System maintained by: <i>{{ systemInfo?.maintainer || 'System Administrator' }}</i></b>
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
      :node-number="targetNode"
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
          @command-executed="handleCommandExecuted"
        />
        
        <!-- AST Log Modal -->
        <AstLog v-model:open="showAstLogModal" />
        
        <!-- AST Lookup Modal -->
        <AstLookup v-model:open="showAstLookupModal" />
        
        <!-- Bubble Chart Modal -->
        <BubbleChart v-model:open="showBubbleChartModal" :local-node="targetNode" />
        
        <!-- Control Panel Modal -->
        <ControlPanel v-model:isVisible="showControlPanelModal" :local-node="targetNode" />
        
                  <!-- RPT Stats Modal -->
          <RptStats v-model:isVisible="showRptStatsModal" :node-number="targetNode" />
          
          <!-- CPU Stats Modal -->
          <CpuStats v-model:isVisible="showCpuStatsModal" />
          
          <!-- Database Modal -->
          <Database 
            v-model:isVisible="showDatabaseModal" 
            :localnode="selectedLocalNode || selectedNode" 
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
    
    <!-- ConfigEditor Modal -->
    <ConfigEditor v-model:open="showConfigEditorModal" />
    
    <!-- SystemInfo Modal -->
    <SystemInfo v-model:open="showSystemInfoModal" />

    <!-- Digital Dashboard Modal -->
    <DigitalDashboard
      v-model:isVisible="showDigitalDashboardModal"
      :url="systemInfo?.dvmUrl || ''"
    />

    <!-- HamClock Modal -->
    <HamClock
      v-model:isVisible="showHamClockModal"
      :url="hamclockUrl"
      @close="showHamClockModal = false"
    />

    <!-- Node Status Modal -->
    <NodeStatus
      v-model:isVisible="showNodeStatusModal"
      @close="showNodeStatusModal = false"
    />
    
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useAppStore } from '@/stores/app'
import { useRealTimeStore } from '@/stores/realTime'
import { api } from '@/utils/api'
import NodeTable from '@/components/NodeTable.vue'
import LoginForm from '@/components/LoginForm.vue'
import Menu from '@/components/Menu.vue'
import DisplayConfig from '@/components/DisplayConfig.vue'
import AddFavorite from '@/components/AddFavorite.vue'
import DeleteFavorite from '@/components/DeleteFavorite.vue'
import Favorites from '@/components/Favorites.vue'
import AstLog from '@/components/AstLog.vue'
import AstLookup from '@/components/AstLookup.vue'
import BubbleChart from '@/components/BubbleChart.vue'
import ControlPanel from '@/components/ControlPanel.vue'
import RptStats from '@/components/RptStats.vue'
import CpuStats from '@/components/CpuStats.vue'
import Database from '@/components/Database.vue'
import Donate from '@/components/Donate.vue'
import ExtNodes from '@/components/ExtNodes.vue'
import FastRestart from '@/components/FastRestart.vue'
import IRLPLog from '@/components/IRLPLog.vue'
import LinuxLog from '@/components/LinuxLog.vue'
import BanAllow from '@/components/BanAllow.vue'
import PiGPIO from '@/components/PiGPIO.vue'
import Reboot from '@/components/Reboot.vue'
import SMLog from '@/components/SMLog.vue'
import Stats from '@/components/Stats.vue'
import WebAccLog from '@/components/WebAccLog.vue'
import WebErrLog from '@/components/WebErrLog.vue'
import Voter from '@/components/Voter.vue'
import ConfigEditor from '@/components/ConfigEditor.vue'
import SystemInfo from '@/components/SystemInfo.vue'
import DigitalDashboard from '@/components/DigitalDashboard.vue'
import HamClock from '@/components/HamClock.vue'
import NodeStatus from '@/components/NodeStatus.vue'


const appStore = useAppStore()
const realTimeStore = useRealTimeStore()

// Reactive data
const selectedNode = ref('')
const selectedLocalNode = ref('')
const targetNode = ref('')
const permConnect = ref(false)
const showLoginModal = ref(false)
const showDisplayConfigModal = ref(false)
const showAddFavoriteModal = ref(false)
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


const nodeTableRefs = ref<any[]>([])
const systemInfo = ref<any>(null)
const showDigitalDashboardModal = ref(false)
const showHamClockModal = ref(false)
const showNodeStatusModal = ref(false)
const databaseStatus = ref<any>(null)

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



const displayedNodes = computed(() => {
  // If no node is selected, show all available nodes as fallback
  if (!selectedNode.value) {
    
    return availableNodes.value
  }
  
  const selectedNodeStr = String(selectedNode.value)

  // Handle comma-separated node IDs (for groups)
  if (selectedNodeStr.includes(',')) {
    const nodeIds = selectedNodeStr.split(',').map(id => id.trim())

    const filteredNodes = availableNodes.value.filter(node => 
      nodeIds.includes(node.id.toString()) || 
      nodeIds.includes((node.node_number || node.id).toString())
    )
    
    return filteredNodes
  }
  
  // Handle single node ID
  const filteredNodes = availableNodes.value.filter(node => 
    node.id.toString() === selectedNodeStr || 
    (node.node_number || node.id).toString() === selectedNodeStr
  )

  return filteredNodes
})

const headerBackgroundUrl = computed(() => {
  // Use custom background if available, otherwise use default
  if (systemInfo.value?.customHeaderBackground) {
    return `url('${systemInfo.value.customHeaderBackground}')`
  }
  return "url('/background.jpg')"
})

const formatHeaderTag = () => {
  const location = systemInfo.value?.location || 'Your Location'
  const title2 = systemInfo.value?.title2 || 'AllStar Management Dashboard'
  return `${location}<br>${title2}`
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

// Methods
const onNodeChange = () => {
  
  // Ensure selectedNode is always a string for processing
  const selectedNodeStr = String(selectedNode.value)
  
  if (selectedNodeStr.includes(',')) {
    // Handle group selection
    const nodeIds = selectedNodeStr.split(',').map(id => id.trim())

    
    // Start monitoring each node in the group
    nodeIds.forEach(nodeId => {

      realTimeStore.startMonitoring(nodeId)
    })
    
    // Set the first node as the default local node for operations
    if (nodeIds.length > 0) {
      selectedLocalNode.value = nodeIds[0]
    }
  } else {
    // Handle single node selection

    selectedLocalNode.value = selectedNodeStr
    realTimeStore.startMonitoring(selectedNodeStr)
  }
}

const connect = async () => {
  if (!targetNode.value || !selectedLocalNode.value) return
  
  try {
    const response = await api.post('/nodes/connect', {
      localnode: selectedLocalNode.value,
      remotenode: targetNode.value,
      perm: permConnect.value ? 'on' : null
    })
    
    if (response.data.success) {
      // Refresh node data after successful connection
      await realTimeStore.fetchNodeData()
    } else {
      // Connect failed - error already shown to user
    }
  } catch (error) {
    // Connect error - error already shown to user
  }
}

const disconnect = async () => {
  if (!targetNode.value || !selectedLocalNode.value) return
  
  try {
    const response = await api.post('/nodes/disconnect', {
      localnode: selectedLocalNode.value,
      remotenode: targetNode.value,
      perm: null
    })
    
    if (response.data.success) {
      // Refresh node data after successful disconnection
      await realTimeStore.fetchNodeData()
    }
  } catch (error) {
    // Disconnect error handled
  }
}

const monitor = async () => {
  if (!targetNode.value || !selectedLocalNode.value) return
  try {
    const response = await api.post('/nodes/monitor', {
      localnode: selectedLocalNode.value,
      remotenode: targetNode.value,
      perm: null
    })
    
    if (response.data.success) {

      // Refresh node data after successful monitoring
      await realTimeStore.fetchNodeData()
    }
  } catch (error) {
    // Monitor error handled
  }
}

const permconnect = async () => {
  if (!targetNode.value || !selectedLocalNode.value) return
  
  try {
    const response = await api.post('/nodes/connect', {
      localnode: selectedLocalNode.value,
      remotenode: targetNode.value,
      perm: 'on'
    })
    
    if (response.data.success) {

      // Refresh node data after successful connection
      await realTimeStore.fetchNodeData()
    }
  } catch (error) {
    // Permanent connect error handled
  }
}

const localmonitor = async () => {
  if (!targetNode.value || !selectedLocalNode.value) return
  try {
    const response = await api.post('/nodes/local-monitor', {
      localnode: selectedLocalNode.value,
      remotenode: targetNode.value,
      perm: null
    })
    
    if (response.data.success) {

      // Refresh node data after successful local monitoring
      await realTimeStore.fetchNodeData()
    }
  } catch (error) {
    // Local monitor error handled
  }
}

const monitorcmd = async () => {
  if (!targetNode.value) return
  try {
    await realTimeStore.monitorCmdNode(targetNode.value)
  } catch (error) {
    // Monitor CMD error handled
  }
}

const refreshData = () => {
  realTimeStore.fetchNodeData()
}

// Manual data clearing function - only use for explicit reset actions
const clearData = () => {
  selectedNode.value = ''
  targetNode.value = ''
  permConnect.value = false
}

// Additional button methods to match link.php
const dtmf = async () => {
  // Always prompt user for DTMF command first
  const dtmfCommand = prompt('Enter DTMF command:')
  if (!dtmfCommand || dtmfCommand.trim() === '') {
    return
  }
  
  // Check if we have a local node selected
  if (!selectedLocalNode.value) {
    alert('No local node selected. Please select a node first.')
    return
  }
  
  try {
    const response = await api.post('/nodes/dtmf', {
      localnode: selectedLocalNode.value,
      dtmf: dtmfCommand.trim()
    })
    
    if (response.data.success) {

      // Show success message to user
      
      // Refresh node data after successful DTMF command
      await realTimeStore.fetchNodeData()
    }
  } catch (error) {
    // DTMF error handled
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

const extnodes = async () => {
  try {
    showExtNodesModal.value = true
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error'
    alert(`Failed to open ExtNodes: ${errorMessage}`)
  }
}



const controlpanel = async () => {
  try {

    
    // Use selectedLocalNode if available, otherwise use selectedNode
    if (selectedLocalNode.value) {
      targetNode.value = String(selectedLocalNode.value)
    } else if (selectedNode.value) {
      targetNode.value = String(selectedNode.value)
    } else {
      alert('Please select a node first')
      return
    }
    

    showControlPanelModal.value = true
  } catch (error) {
    // Control Panel error handled
  }
}

const favorites = async () => {
  try {
    // Implement favorites functionality

  } catch (error) {
    // Favorites error handled
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

const openHamClock = async () => {
  try {
    if (systemInfo.value?.hamclockEnabled) {
      showHamClockModal.value = true
    } else {
      alert('HamClock is not enabled')
    }
  } catch (error) {
    alert('Error opening HamClock: ' + (error.message || 'Unknown error'))
  }
}

const openNodeStatus = async () => {
  try {
    showNodeStatusModal.value = true
  } catch (error) {
    alert('Error opening Node Status: ' + (error.message || 'Unknown error'))
  }
}


// Helper function to check if user is accessing site internally
const isInternalAccess = (): boolean => {
  const hostname = window.location.hostname
  
  // Check if accessing via localhost, IP address, or internal domain patterns
  const internalPatterns = [
    /^localhost$/i,
    /^127\./,                    // 127.0.0.0/8 (localhost)
    /^10\./,                     // 10.0.0.0/8
    /^172\.(1[6-9]|2[0-9]|3[0-1])\./, // 172.16.0.0/12
    /^192\.168\./,               // 192.168.0.0/16
    /^169\.254\./,               // 169.254.0.0/16 (link-local)
    /\.local$/i,                 // .local domains
    /\.lan$/i,                   // .lan domains
    /\.internal$/i               // .internal domains
  ]
  
  return internalPatterns.some(pattern => pattern.test(hostname))
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

    
    if (!selectedNode.value) {
      alert('Please select a node first')
      return
    }

    const response = await api.post('/config/asterisk/reload', {
      localnode: selectedNode.value
    })

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
  
      
      // Use selectedLocalNode if available, otherwise use selectedNode
      if (selectedLocalNode.value) {
        showFastRestartModal.value = true
      } else if (selectedNode.value) {
        showFastRestartModal.value = true
      } else {
        alert('Please select a node first to perform fast restart.')
        return
      }
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Unknown error'
      alert(`Failed to open FastRestart: ${errorMessage}`)
    }
  }

  const irlplog = async () => {
    try {
  
      showIrlpLogModal.value = true
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Unknown error'
      alert(`Failed to open IRLP Log: ${errorMessage}`)
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

const smlog = async () => {
  try {

    showSMLogModal.value = true
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error'
    alert(`Failed to open SMLog: ${errorMessage}`)
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
  
  // Refetch configuration data after login to get user's personal default node
  try {
    const nodesResponse = await fetch('/api/config/nodes')
    if (nodesResponse.ok) {
      const nodesData = await nodesResponse.json()
      if (nodesData.data?.default_node) {
        const defaultNode = nodesData.data.default_node
    
        
        // Set the default node as selected
        selectedNode.value = defaultNode
        
        // Start monitoring the default node(s)
        if (defaultNode.includes(',')) {
          // Group mode - monitor each node individually
          const nodeIds = defaultNode.split(',').map((id: string) => id.trim())
          for (const nodeId of nodeIds) {
            await realTimeStore.startMonitoring(nodeId)
          }
        } else {
          // Single node mode
          await realTimeStore.startMonitoring(defaultNode)
        }
      }
    }
  } catch (error) {
    // Configuration refetch error handled
  }
}

const handleLogout = async () => {
  await appStore.logout()
  
  // Refetch configuration data after logout to get new default node
  try {
    const nodesResponse = await fetch('/api/config/nodes')
    if (nodesResponse.ok) {
      const nodesData = await nodesResponse.json()
      if (nodesData.data?.default_node) {
        const defaultNode = nodesData.data.default_node
    
        
        // Set the default node as selected
        selectedNode.value = defaultNode
        
        // Start monitoring the default node(s)
        if (defaultNode.includes(',')) {
          // Group mode - monitor each node individually
          const nodeIds = defaultNode.split(',').map((id: string) => id.trim())
          for (const nodeId of nodeIds) {
            await realTimeStore.startMonitoring(nodeId)
          }
        } else {
          // Single node mode
          await realTimeStore.startMonitoring(defaultNode)
        }
      }
    }
  } catch (error) {
    // Configuration refetch error handled
  }
}

const openDonatePopup = () => {
  showDonateModal.value = true
}

  // Handle menu node selection
  const handleNodeSelection = (nodeId: string) => {
      selectedNode.value = nodeId
    onNodeChange()
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
  const handleDisplaySettingsUpdated = (settings: any) => {
    // Refresh the page to apply new display settings
    window.location.reload()
  }

  // Handle favorite added
  const handleFavoriteAdded = (result: any) => {
    // Could show a notification or refresh the page
    
  }

  // Handle favorite deleted
  const handleFavoriteDeleted = (result: any) => {
    // Could show a notification or refresh the page
    
  }

  // Handle command executed
  const handleCommandExecuted = (result: any) => {
    // Could show a notification or refresh the page
    
  }

// Lifecycle
onMounted(async () => {
  await appStore.checkAuth()
  
  // Initialize realTime store
  await realTimeStore.initialize()
  realTimeStore.startPolling()
  
  // Load system info, database status, and check for default node
  try {
    const [systemResponse, databaseResponse, nodesResponse] = await Promise.all([
      fetch('/api/config/system-info'),
      fetch('/api/database/status'),
      fetch('/api/config/nodes')
    ])
    
    if (systemResponse.ok) {
      const systemData = await systemResponse.json()
      systemInfo.value = systemData.data || systemData
    }
    
    if (databaseResponse.ok) {
      const databaseData = await databaseResponse.json()
      databaseStatus.value = databaseData.data || databaseData
    }

    // Check for default node configuration
    if (nodesResponse.ok) {
      const nodesData = await nodesResponse.json()
      if (nodesData.data?.default_node) {
        const defaultNode = nodesData.data.default_node
    
        
        // Set the default node as selected
        selectedNode.value = defaultNode
        
        // Start monitoring the default node(s)
        if (defaultNode.includes(',')) {
          // Group mode - monitor each node individually
          const nodeIds = defaultNode.split(',').map((id: string) => id.trim())
          for (const nodeId of nodeIds) {
            await realTimeStore.startMonitoring(nodeId)
          }
        } else {
          // Single node mode
          await realTimeStore.startMonitoring(defaultNode)
        }
        
        // Force a reactive update to ensure displayedNodes recomputes
        await nextTick()
        
        // Small delay to ensure the default node is properly set
        await new Promise(resolve => setTimeout(resolve, 100))
      } else {
        // No default node from backend, use first available node as fallback
    
        if (realTimeStore.nodes.length > 0) {
          const firstNode = realTimeStore.nodes[0]
          selectedNode.value = String(firstNode.id)
      
          await realTimeStore.startMonitoring(String(firstNode.id))
        }
      }
    }
  } catch (error) {
    // System info loading error handled
  }
})

onUnmounted(() => {
  realTimeStore.stopPolling()
})

// Watch for node table refs updates
watch(nodeTableRefs, (newRefs) => {
  if (Array.isArray(newRefs)) {
    nextTick(() => {
      newRefs.forEach(ref => {
        if (ref && typeof ref.refreshData === 'function') {
          ref.refreshData()
        }
      })
    })
  }
}, { deep: true })

// Watch for real-time store updates and update NodeTable components
watch(() => realTimeStore.nodes, (newNodes) => {
  nextTick(() => {
    nodeTableRefs.value.forEach((ref, index) => {
      if (ref && typeof ref.updateNodeData === 'function') {
        const nodeId = displayedNodes.value[index]?.id
        if (nodeId) {
          const nodeData = newNodes.find(n => String(n.id) === String(nodeId))
          if (nodeData) {
            ref.updateNodeData(nodeData)
          }
        }
      }
    })
  })
}, { deep: true })

// Watch for displayed nodes changes and update NodeTable components
// TEMPORARILY DISABLED TO DEBUG GROUP MODE ISSUE
/*
watch(displayedNodes, (newDisplayedNodes) => {
  
  
  // Only set default node if we don't have any selection AND we have multiple nodes
  // This prevents overriding group selections
  if (newDisplayedNodes.length > 1 && !selectedNode.value) {
    nextTick(() => {
      // Create a group selection from all displayed nodes
      const nodeIds = newDisplayedNodes.map(node => node.id).join(',')
      selectedNode.value = nodeIds
  
      // Trigger onNodeChange to update target node and start monitoring
      onNodeChange()
    })
  } else if (newDisplayedNodes.length > 1 && selectedNode.value) {
    // If we have a selection and multiple displayed nodes, check if it's a group selection
    const selectedNodeStr = String(selectedNode.value)
    if (!selectedNodeStr.includes(',')) {
      // If current selection is not a group, but we have multiple displayed nodes,
      // this might be a case where we need to create a group selection
      // But we should be careful not to override existing group selections
  
    } else {
      
    }
  }
  
  nextTick(() => {
    newDisplayedNodes.forEach((node, index) => {
      if (nodeTableRefs.value[index] && typeof nodeTableRefs.value[index].updateNodeData === 'function') {
        const nodeData = realTimeStore.nodes.find(n => String(n.id) === String(node.id))
        if (nodeData) {
          nodeTableRefs.value[index].updateNodeData(nodeData)
        }
      }
    })
  })
}, { deep: true })
*/
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
  width: 100%;
  height: 164px;
  background-size: cover;
  background-position: center;
  border-radius: 8px;
  margin-bottom: 0;
  overflow: hidden;
}

/* Header Title (main title) */
.header-title {
  position: absolute;
  top: 3px;
  left: 5px;
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
  color: white;
}

/* Header Title2 (call sign) */
.header-title2 {
  position: absolute;
  top: 5px;
  left: 550px;
  margin: 0;
  font-weight: bold;
  font-size: 1.1em;
  color: white;
  line-height: normal;
  font-family: "Lucida Grande", Lucida, Verdana, sans-serif;
  letter-spacing: normal;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
}

/* Header Tag (location and title) */
.header-tag {
  position: absolute;
  bottom: 2%;
  left: 10px;
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
  left: 10px;
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
  display: inline-block;
  white-space: nowrap;
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
  }
  
  .header-title {
    font-size: 20px;
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
  
  .node-dropdown,
  .node-input {
    min-width: auto;
  }
}
</style>
