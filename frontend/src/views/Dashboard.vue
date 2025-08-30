<template>
  <div class="dashboard">
    <!-- Header Section (mimics header.inc structure) -->
    <div class="header">
      <!-- Main Title -->
      <div class="header-title">
        <a href="#"><i>Supermon-ng V4.0.0 AllStar Monitor</i></a>
      </div>
      
      <!-- Call Sign -->
      <div class="header-title2">
        <i>W5GLE</i>
      </div>
      
      <!-- Location and Title -->
      <div class="header-tag">
        <i>Alvin, Texas<br>ASL3+ Management Dashboard</i>
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
      
      <!-- AllStar Logo -->
      <div class="header-img">
        <a href="https://www.allstarlink.org" target="_blank">
          <img src="/allstarlink.jpg" width="70%" style="border: 0px;" alt="Allstar Logo">
        </a>
      </div>
    </div>

    <!-- Menu Component -->
    <Menu @node-selection="handleNodeSelection" />

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
      <input v-if="appStore.hasPermission('CONNECTUSER')" type="button" class="submit" value="Connect" @click="connect">
      <input v-if="appStore.hasPermission('DISCUSER')" type="button" class="submit" value="Disconnect" @click="disconnect">
      <input v-if="appStore.hasPermission('MONUSER')" type="button" class="submit" value="Monitor" @click="monitor">
      <input v-if="appStore.hasPermission('LMONUSER')" type="button" class="submit" value="Local Monitor" @click="localmonitor">
      <br>
      
      <!-- Secondary Control Buttons (matches original order exactly) -->
      <input v-if="appStore.hasPermission('DTMFUSER')" type="button" class="submit2" value="DTMF" @click="dtmf">
      <input v-if="appStore.hasPermission('ASTLKUSER')" type="button" class="submit2" value="Lookup" @click="astlookup">
      <input v-if="appStore.hasPermission('RSTATUSER')" type="button" class="submit" value="Rpt Stats" @click="rptstats">
      <input v-if="appStore.hasPermission('BUBLUSER')" type="button" class="submit2" value="Bubble" @click="bubble">
      <input v-if="appStore.hasPermission('CTRLUSER')" type="button" class="submit2" value="Control" @click="controlpanel">
      <input v-if="appStore.hasPermission('FAVUSER')" type="button" class="submit2" value="Favorites" @click="showFavoritesModal = true">
      <input v-if="appStore.hasPermission('FAVUSER')" type="button" class="submit2" value="Add Favorite" @click="showAddFavoriteModal = true">
        <input v-if="appStore.hasPermission('FAVUSER')" type="button" class="submit2" value="Delete Favorite" @click="showDeleteFavoriteModal = true">
      
      <!-- Detailed View Additional Buttons (matches original exactly) -->
      <hr class="button-separator">
      <input v-if="appStore.hasPermission('CFGEDUSER')" type="button" class="submit" value="Configuration Editor" @click="configeditor">
      <input v-if="appStore.hasPermission('ASTRELUSER')" type="button" class="submit" value="Iax/Rpt/DP RELOAD" @click="astreload">
      <input v-if="appStore.hasPermission('ASTSTRUSER')" type="button" class="submit" value="AST START" @click="astaron">
      <input v-if="appStore.hasPermission('ASTSTPUSER')" type="button" class="submit" value="AST STOP" @click="astaroff">
      <input v-if="appStore.hasPermission('FSTRESUSER')" type="button" class="submit" value="RESTART" @click="fastrestart">
      <input v-if="appStore.hasPermission('RBTUSER')" type="button" class="submit" value="Server REBOOT" @click="reboot">
      <br>
      
      <!-- Information Buttons -->
      <input v-if="appStore.hasPermission('HWTOUSER')" type="button" class="submit" value="AllStar How To's" @click="openHelp">
      <input v-if="appStore.hasPermission('WIKIUSER')" type="button" class="submit" value="AllStar Wiki" @click="openWiki">
      <input v-if="appStore.hasPermission('CSTATUSER')" type="button" class="submit" value="CPU Status" @click="cpustats">
      <input v-if="appStore.hasPermission('ASTATUSER')" type="button" class="submit" value="AllStar Status" @click="aststats">
      <input v-if="appStore.hasPermission('EXNUSER')" type="button" class="submit" value="Registry" @click="extnodes">
      <input v-if="appStore.hasPermission('NINFUSER')" type="button" class="submit" value="Node Info" @click="astnodes">
      <input v-if="appStore.hasPermission('ACTNUSER')" type="button" class="submit" value="Active Nodes" @click="openActiveNodes">
      <input v-if="appStore.hasPermission('ALLNUSER')" type="button" class="submit" value="All Nodes" @click="openAllNodes">
      <input v-if="appStore.hasPermission('DBTUSER')" type="button" class="submit" value="Database" @click="database">
      <br>
      
      <!-- System Buttons -->
      <input v-if="appStore.hasPermission('GPIOUSER')" type="button" class="submit" value="GPIO" @click="openpigpio">
      <input v-if="appStore.hasPermission('LLOGUSER')" type="button" class="submit" value="Linux Log" @click="linuxlog">
      <input v-if="appStore.hasPermission('ASTLUSER')" type="button" class="submit" value="AST Log" @click="astlog">
      <input v-if="appStore.hasPermission('WLOGUSER')" type="button" class="submit" value="Web Access Log" @click="webacclog">
      <input v-if="appStore.hasPermission('WERRUSER')" type="button" class="submit" value="Web Error Log" @click="weberrlog">
      
      <!-- Access List Button -->
      <input v-if="appStore.hasPermission('BANUSER')" type="button" class="submit2" value="Access List" @click="openbanallow">
    </div>

    <!-- Bottom Utility Buttons (matches original exactly) -->
    <p class="button-container">
      <input type="button" class="submit2" value="Display Configuration" @click="showDisplayConfigModal = true">
      <input v-if="systemInfo?.dvmUrl" type="button" class="submit2" value="Digital Dashboard" @click="digitaldashboard">
      <input v-if="appStore.hasPermission('SYSINFUSER')" type="button" class="submit2" value="System Info" @click="systeminfo">
    </p>

    <!-- Node Tables (mimics link.php structure) -->
    <div style="text-align: center;">
      <div v-if="displayedNodes.length === 0" class="no-nodes-message">
        Select a node or group from the menu to display node tables
      </div>
      <table v-else class="fxwidth">
        <tr v-for="(node, index) in displayedNodes" :key="node.id">
          <td>
            <NodeTable 
              :node="node"
              :show-detail="true"
              :astdb="realTimeStore.astdb"
              :config="realTimeStore.nodeConfig"
              :ref="el => { if (el) nodeTableRefs[index] = el }"
              @node-click="handleNodeClick"
            />
          </td>
        </tr>
      </table>
    </div>

    <!-- Footer Info -->
    <div class="clearer"></div>
    
    <div id="footer">
      <b>System maintained by: <i>{{ systemInfo?.maintainer || 'W5GLE, Alvin, Texas' }}</i></b>
    </div>
    
    <!-- Additional System Info -->
    <div class="footer-info">
      <div v-if="systemInfo">
        <strong>System:</strong> {{ systemInfo.system_name || 'Supermon-ng' }} | 
        <strong>Version:</strong> {{ systemInfo.version || 'V4.0.0' }} | 
        <strong>Uptime:</strong> {{ systemInfo.uptime || 'N/A' }}
      </div>
      <div v-if="databaseStatus">
        <strong>Database:</strong> {{ databaseStatus.status || 'Connected' }} | 
        <strong>Nodes:</strong> {{ databaseStatus.node_count || availableNodes.length }}
      </div>
    </div>

    <!-- Donate Button Section -->
    <div id="donate-section" style="margin-top: 20px; text-align: center;">
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
      v-model:open="showAddFavoriteModal"
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

const nodeTableRefs = ref<any[]>([])
const systemInfo = ref<any>(null)
const databaseStatus = ref<any>(null)

// Computed properties
const hasControlPermissions = computed(() => {
  return appStore.hasPermission('CONNECTUSER') || 
         appStore.hasPermission('DISCONNECTUSER') || 
         appStore.hasPermission('MONITORUSER') || 
         appStore.hasPermission('PERMUSER')
})

const availableNodes = computed(() => {
  return realTimeStore.nodes
})



const displayedNodes = computed(() => {
  if (!selectedNode.value) {
    return []
  }
  
  const selectedNodeStr = String(selectedNode.value)
  console.log('🔍 displayedNodes computed - selectedNode:', selectedNodeStr)
  console.log('🔍 displayedNodes computed - availableNodes count:', availableNodes.value.length)
  
  // Handle comma-separated node IDs (for groups)
  if (selectedNodeStr.includes(',')) {
    const nodeIds = selectedNodeStr.split(',').map(id => id.trim())
    console.log('🔍 displayedNodes computed - group nodeIds:', nodeIds)
    const filteredNodes = availableNodes.value.filter(node => 
      nodeIds.includes(node.id.toString()) || 
      nodeIds.includes((node.node_number || node.id).toString())
    )
    console.log('🔍 displayedNodes computed - filtered group nodes:', filteredNodes.map(n => n.id))
    return filteredNodes
  }
  
  // Handle single node ID
  const filteredNodes = availableNodes.value.filter(node => 
    node.id.toString() === selectedNodeStr || 
    (node.node_number || node.id).toString() === selectedNodeStr
  )
  console.log('🔍 displayedNodes computed - filtered single node:', filteredNodes.map(n => n.id))
  return filteredNodes
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
  console.log('🔍 onNodeChange called with selectedNode:', selectedNode.value)
  
  // Ensure selectedNode is always a string for processing
  const selectedNodeStr = String(selectedNode.value)
  
  if (selectedNodeStr.includes(',')) {
    // Handle group selection
    const nodeIds = selectedNodeStr.split(',').map(id => id.trim())
    console.log('🔍 Starting monitoring for group nodes:', nodeIds)
    
    // Start monitoring each node in the group
    nodeIds.forEach(nodeId => {
      console.log('🔍 Starting monitoring for nodeId:', nodeId)
      realTimeStore.startMonitoring(nodeId)
    })
    
    // Set the first node as the default local node for operations
    if (nodeIds.length > 0) {
      selectedLocalNode.value = nodeIds[0]
    }
  } else {
    // Handle single node selection
    console.log('🔍 Starting monitoring for single node:', selectedNodeStr)
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
      console.log('Connect success:', response.data.message)
      // Refresh node data after successful connection
      await realTimeStore.fetchNodeData()
    } else {
      console.error('Connect failed:', response.data.message)
    }
  } catch (error) {
    console.error('Connect error:', error)
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
      console.log('Disconnect success:', response.data.message)
      // Refresh node data after successful disconnection
      await realTimeStore.fetchNodeData()
    } else {
      console.error('Disconnect failed:', response.data.message)
    }
  } catch (error) {
    console.error('Disconnect error:', error)
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
      console.log('Monitor success:', response.data.message)
      // Refresh node data after successful monitoring
      await realTimeStore.fetchNodeData()
    } else {
      console.error('Monitor failed:', response.data.message)
    }
  } catch (error) {
    console.error('Monitor error:', error)
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
      console.log('Permanent connect success:', response.data.message)
      // Refresh node data after successful connection
      await realTimeStore.fetchNodeData()
    } else {
      console.error('Permanent connect failed:', response.data.message)
    }
  } catch (error) {
    console.error('Perm connect error:', error)
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
      console.log('Local monitor success:', response.data.message)
      // Refresh node data after successful local monitoring
      await realTimeStore.fetchNodeData()
    } else {
      console.error('Local monitor failed:', response.data.message)
    }
  } catch (error) {
    console.error('Local monitor error:', error)
  }
}

const monitorcmd = async () => {
  if (!targetNode.value) return
  try {
    await realTimeStore.monitorCmdNode(targetNode.value)
  } catch (error) {
    console.error('Monitor CMD error:', error)
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
  if (!targetNode.value) return
  try {
    // Implement DTMF functionality
    console.log('DTMF for node:', targetNode.value)
  } catch (error) {
    console.error('DTMF error:', error)
  }
}

const astlookup = async () => {
  try {
    console.log('Opening AST lookup modal')
    showAstLookupModal.value = true
  } catch (error) {
    console.error('AST lookup error:', error)
  }
}

const rptstats = async () => {
  try {
    // Implement RPT stats functionality
    console.log('RPT stats')
  } catch (error) {
    console.error('RPT stats error:', error)
  }
}

const bubble = async () => {
  try {
    console.log('Opening Bubble Chart modal')
    showBubbleChartModal.value = true
  } catch (error) {
    console.error('Bubble error:', error)
  }
}

const extnodes = async () => {
  try {
    // Implement Registry functionality
    console.log('Registry')
  } catch (error) {
    console.error('Registry error:', error)
  }
}

const controlpanel = async () => {
  try {
    console.log('Opening Control Panel modal')
    // Set targetNode to the currently selected node before opening modal
    targetNode.value = String(selectedNode.value)
    showControlPanelModal.value = true
  } catch (error) {
    console.error('Control Panel error:', error)
  }
}

const favorites = async () => {
  try {
    // Implement favorites functionality
    console.log('Opening favorites panel')
  } catch (error) {
    console.error('Favorites error:', error)
  }
}







const digitaldashboard = async () => {
  try {
    if (systemInfo.value?.dvmUrl) {
      window.open(systemInfo.value.dvmUrl, 'DigitalDashboard', 'status=no,location=no,toolbar=no,width=960,height=850,left=100,top=100')
    } else {
      console.log('Digital Dashboard URL not configured')
    }
  } catch (error) {
    console.error('Digital dashboard error:', error)
  }
}

// Additional button methods to match original Supermon-ng
const configeditor = async () => {
  try {
    console.log('Opening Config Editor in new window')
    window.open('http://localhost:8000/configeditor.html', 'ConfigEditor', 'status=no,location=no,toolbar=no,width=1200,height=800,left=100,top=100')
  } catch (error) {
    console.error('Config editor error:', error)
  }
}

const astreload = async () => {
  try {
    console.log('AST RELOAD command')
    
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
  } catch (error) {
    console.error('AST RELOAD error:', error)
    alert('Error executing Asterisk reload: ' + (error.response?.data?.message || error.message))
  }
}

const astaron = async () => {
  try {
    console.log('AST START command')
    
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
  } catch (error) {
    console.error('AST START error:', error)
    alert('Error starting AllStar service: ' + (error.response?.data?.message || error.message))
  }
}

const astaroff = async () => {
  try {
    console.log('AST STOP command')
    
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
  } catch (error) {
    console.error('AST STOP error:', error)
    alert('Error stopping AllStar service: ' + (error.response?.data?.message || error.message))
  }
}

const fastrestart = async () => {
  try {
    console.log('FAST RESTART command')
  } catch (error) {
    console.error('FAST RESTART error:', error)
  }
}

const reboot = async () => {
  try {
    console.log('Server REBOOT command')
  } catch (error) {
    console.error('Server REBOOT error:', error)
  }
}

const openHelp = async () => {
  try {
    window.open('https://allstarlink.org/howto.html', 'AllStarHelp', 'status=no,location=no,toolbar=yes,width=800,height=600,left=100,top=100')
  } catch (error) {
    console.error('Open help error:', error)
  }
}

const openWiki = async () => {
  try {
    window.open('https://wiki.allstarlink.org', 'AllStarWiki', 'status=no,location=no,toolbar=yes,width=800,height=600,left=100,top=100')
  } catch (error) {
    console.error('Open wiki error:', error)
  }
}

const cpustats = async () => {
  try {
    console.log('CPU Status')
  } catch (error) {
    console.error('CPU Status error:', error)
  }
}

const aststats = async () => {
  try {
    console.log('AllStar Status')
  } catch (error) {
    console.error('AllStar Status error:', error)
  }
}

const astnodes = async () => {
  try {
    console.log('Node Info')
  } catch (error) {
    console.error('Node Info error:', error)
  }
}

const openActiveNodes = async () => {
  try {
    window.open('https://stats.allstarlink.org/', 'ActiveNodes', 'status=no,location=no,toolbar=yes,width=1200,height=800,left=100,top=100')
  } catch (error) {
    console.error('Open active nodes error:', error)
  }
}

const openAllNodes = async () => {
  try {
    window.open('https://www.allstarlink.org/nodelist/', 'AllNodes', 'status=no,location=no,toolbar=yes,width=1200,height=800,left=100,top=100')
  } catch (error) {
    console.error('Open all nodes error:', error)
  }
}

const database = async () => {
  try {
    console.log('Database')
  } catch (error) {
    console.error('Database error:', error)
  }
}

const openpigpio = async () => {
  try {
    console.log('GPIO')
  } catch (error) {
    console.error('GPIO error:', error)
  }
}

const linuxlog = async () => {
  try {
    console.log('Linux Log')
  } catch (error) {
    console.error('Linux Log error:', error)
  }
}

const astlog = async () => {
  try {
    console.log('AST Log')
    showAstLogModal.value = true
  } catch (error) {
    console.error('AST Log error:', error)
  }
}

const webacclog = async () => {
  try {
    console.log('Web Access Log')
  } catch (error) {
    console.error('Web Access Log error:', error)
  }
}

const weberrlog = async () => {
  try {
    console.log('Web Error Log')
  } catch (error) {
    console.error('Web Error Log error:', error)
  }
}

const openbanallow = async () => {
  try {
    console.log('Access List')
  } catch (error) {
    console.error('Access List error:', error)
  }
}

const systeminfo = async () => {
  try {
    window.open('system-info.php', 'SystemInfo', 'status=no,location=no,toolbar=yes,width=950,height=550,left=100,top=100')
  } catch (error) {
    console.error('System info error:', error)
  }
}

const handleLoginSuccess = async () => {
  showLoginModal.value = false
  await appStore.checkAuth()
  // Node state is preserved during login - displayed nodes remain visible
}

const handleLogout = async () => {
  await appStore.logout()
  // Menu will automatically refresh due to authentication state change
}

const openDonatePopup = () => {
  const width = 600
  const height = 700
  const left = (screen.width - width) / 2
  const top = (screen.height - height) / 2
  const popup = window.open('donate.php', 'Donate', `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`)
  if (popup) {
    popup.focus()
  }
}

  // Handle menu node selection
  const handleNodeSelection = (nodeId: string) => {
    console.log('🔍 handleNodeSelection called with nodeId:', nodeId)
    console.log('🔍 Previous selectedNode value:', selectedNode.value)
    selectedNode.value = nodeId
    console.log('🔍 New selectedNode value:', selectedNode.value)
    onNodeChange()
  }

  // Handle node click from NodeTable (for quick target node selection)
  const handleNodeClick = (nodeId: string, localNodeId: string) => {
    // Set the clicked node as the target node
    targetNode.value = nodeId
    
    // Update the local node selection to the table owner
    selectedLocalNode.value = localNodeId
    
    console.log('🔍 Node clicked - Target node:', nodeId, 'Local node:', localNodeId)
    
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
    console.log('Favorite added:', result)
  }

  // Handle favorite deleted
  const handleFavoriteDeleted = (result: any) => {
    // Could show a notification or refresh the page
    console.log('Favorite deleted:', result)
  }

  // Handle command executed
  const handleCommandExecuted = (result: any) => {
    // Could show a notification or refresh the page
    console.log('Command executed:', result)
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
        console.log('🔍 Loading default node:', defaultNode)
        
        // Set the default node as selected
        selectedNode.value = defaultNode
        
        // Start monitoring the default node(s)
        if (defaultNode.includes(',')) {
          // Group mode - monitor each node individually
          const nodeIds = defaultNode.split(',').map(id => id.trim())
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
    console.error('Error loading system info:', error)
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
  console.log('🔍 displayedNodes watcher triggered - newDisplayedNodes length:', newDisplayedNodes.length)
  console.log('🔍 displayedNodes watcher - current selectedNode:', selectedNode.value)
  
  // Only set default node if we don't have any selection AND we have multiple nodes
  // This prevents overriding group selections
  if (newDisplayedNodes.length > 1 && !selectedNode.value) {
    nextTick(() => {
      // Create a group selection from all displayed nodes
      const nodeIds = newDisplayedNodes.map(node => node.id).join(',')
      selectedNode.value = nodeIds
      console.log('🔍 Created group selection from displayed nodes:', nodeIds)
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
      console.log('🔍 Multiple displayed nodes but single node selection - keeping current selection')
    } else {
      console.log('🔍 Multiple displayed nodes with group selection - keeping group selection')
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
}

/* Original Supermon-ng table container styling */
.fxwidth {
  width: auto;
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

/* Header styling (mimics header.inc layout) */
.header {
  position: relative;
  width: 100%;
  height: 164px;
  background-image: url('/background.jpg');
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

.footer-info {
  margin-top: 10px;
  padding: 15px;
  background-color: var(--background-color);
  border: 1px solid var(--primary-color);
  border-radius: 4px;
  text-align: center;
  font-size: 14px;
  color: var(--text-color);
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
