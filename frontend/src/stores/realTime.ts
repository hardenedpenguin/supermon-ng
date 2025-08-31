import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/utils/api'

export interface Node {
  id: string
  node_number?: number
  callsign?: string
  description?: string
  location?: string
  status?: string
  last_heard?: string
  connected_nodes?: any
  cos_keyed?: number
  tx_keyed?: number
  cpu_temp?: string
  alert?: string
  wx?: string
  disk?: string
  is_online?: boolean
  is_keyed?: boolean
  created_at?: string
  updated_at?: string
  // Legacy field for compatibility
  info?: string
  remote_nodes?: ConnectedNode[]
}

export interface ConnectedNode {
  node: string
  info: string
  ip?: string
  last_keyed: string
  link: string
  direction: string
  elapsed: string
  mode: string
  keyed: string
}

export interface NodeConfig {
  [nodeId: string]: {
    host?: string
    hideNodeURL?: number
    lsnodes?: string
    listenlive?: string
    archive?: string
    [key: string]: any
  }
}

export interface AstDbEntry {
  [nodeId: string]: string[]
}

export const useRealTimeStore = defineStore('realTime', () => {
  // State
  const nodes = ref<Node[]>([])
  const nodeConfig = ref<NodeConfig>({})
  const astdb = ref<AstDbEntry>({})
  const isConnected = ref(false)
  const error = ref<string | null>(null)
  const monitoringNodes = ref<string[]>([])

  // Computed
  const isMonitoring = computed(() => monitoringNodes.value.length > 0)

  // Actions
  const initialize = async () => {
    try {
      // Load initial node list
      const response = await api.get('/nodes')
      const rawNodes = response.data.data || []
      
      // Map the nodes to include the info field for compatibility
      nodes.value = rawNodes.map((node: any) => ({
        ...node,
        info: `${node.node_number || node.id} - ${node.description || 'Unknown'}`
      }))
      
      // Load node configuration
      const configResponse = await api.get('/config/nodes')
      nodeConfig.value = configResponse.data.data?.config || {}
      
      // Load ASTDB
      const astdbResponse = await api.get('/database/status')
      if (astdbResponse.data.data?.astdb) {
        astdb.value = astdbResponse.data.data.astdb
      }
      
      error.value = null
    } catch (err) {
      error.value = 'Failed to initialize real-time store'
      console.error('Real-time store initialization error:', err)
    }
  }

  const startMonitoring = (nodeId: string) => {
    console.log('🔍 startMonitoring called with nodeId:', nodeId)
    console.log('🔍 monitoringNodes before:', monitoringNodes.value)
    
    if (!monitoringNodes.value.includes(nodeId)) {
      monitoringNodes.value.push(nodeId)
      console.log('🔍 Added nodeId to monitoringNodes:', nodeId)
    } else {
      console.log('🔍 NodeId already in monitoringNodes:', nodeId)
    }
    
    console.log('🔍 monitoringNodes after:', monitoringNodes.value)
    
    if (!isConnected.value) {
      startPolling()
    }
  }

  const stopMonitoring = (nodeId: string) => {
    const index = monitoringNodes.value.indexOf(nodeId)
    if (index > -1) {
      monitoringNodes.value.splice(index, 1)
    }
    
    if (monitoringNodes.value.length === 0) {
      stopPolling()
    }
  }

  let pollingInterval: NodeJS.Timeout | null = null

  const startPolling = () => {
    if (pollingInterval) {
      clearInterval(pollingInterval)
    }

    // Initial data fetch
    fetchNodeData()
    
    // Set up polling every 1 second for faster updates
    pollingInterval = setInterval(() => {
      fetchNodeData()
    }, 1000)

    isConnected.value = true
    error.value = null
  }

  const stopPolling = () => {
    if (pollingInterval) {
      clearInterval(pollingInterval)
      pollingInterval = null
    }
    isConnected.value = false
  }

  const fetchNodeData = async () => {
    try {
      // If we're monitoring specific nodes, get AMI data for them
      if (monitoringNodes.value.length > 0) {
        const nodeParams = monitoringNodes.value.join(',')
        const amiResponse = await api.get(`/nodes/ami/status?nodes=${encodeURIComponent(nodeParams)}`)
        
        if (amiResponse.data.success) {
          const amiData = amiResponse.data.data
          
          // Update nodes with AMI data
          Object.keys(amiData).forEach(nodeId => {
            const amiNode = amiData[nodeId]
            const existingNodeIndex = nodes.value.findIndex(n => n.id === parseInt(nodeId))
            
            if (existingNodeIndex > -1) {
              // Update existing node with AMI data
              nodes.value[existingNodeIndex] = {
                ...nodes.value[existingNodeIndex],
                status: amiNode.status,
                cos_keyed: amiNode.cos_keyed,
                tx_keyed: amiNode.tx_keyed,
                cpu_temp: amiNode.cpu_temp,
                cpu_up: amiNode.cpu_up,
                cpu_load: amiNode.cpu_load,
                ALERT: amiNode.ALERT,
                WX: amiNode.WX,
                DISK: amiNode.DISK,
                remote_nodes: amiNode.remote_nodes,
                info: amiNode.info
              }
            } else {
              // Add new node with AMI data
              nodes.value.push({
                id: parseInt(nodeId),
                node_number: parseInt(nodeId),
                callsign: 'N/A',
                description: amiNode.info,
                location: 'N/A',
                status: amiNode.status,
                last_heard: null,
                connected_nodes: amiNode.remote_nodes,
                cos_keyed: amiNode.cos_keyed,
                tx_keyed: amiNode.tx_keyed,
                cpu_temp: amiNode.cpu_temp,
                ALERT: amiNode.ALERT,
                WX: amiNode.WX,
                DISK: amiNode.DISK,
                is_online: amiNode.status === 'online',
                is_keyed: amiNode.cos_keyed > 0 || amiNode.tx_keyed > 0,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
                info: amiNode.info,
                remote_nodes: amiNode.remote_nodes
              })
            }
          })
        }
      } else {
        // If no specific nodes are being monitored, fetch basic node list
        const response = await api.get('/nodes')
        const rawNodes = response.data.data || []
        
        // Update nodes with basic data
        nodes.value = rawNodes.map((node: any) => ({
          ...node,
          info: `${node.node_number || node.id} - ${node.description || 'Unknown'}`
        }))
      }
      
      error.value = null
    } catch (err) {
      console.error('Error fetching node data:', err)
      error.value = 'Failed to fetch node data'
      isConnected.value = false
    }
  }





  const getNodeById = (nodeId: string): Node | undefined => {
    return nodes.value.find(n => n.id === nodeId)
  }

  const getNodeInfo = (nodeId: string): string => {
    const node = getNodeById(nodeId)
    if (node) {
      return node.info
    }
    
    // Fallback to ASTDB
    const astdbEntry = astdb.value[nodeId]
    if (astdbEntry && astdbEntry.length >= 4) {
      return `${astdbEntry[1]} ${astdbEntry[2]} ${astdbEntry[3]}`
    }
    
    return 'Node not in database'
  }

  const clearError = () => {
    error.value = null
  }

  // Control functions
  const connectNode = async (nodeId: string, perm: boolean = false) => {
    try {
      await api.connectNode(nodeId, nodeId, perm)
      console.log(`Connected to node ${nodeId}`)
    } catch (error) {
      console.error('Connect error:', error)
      throw error
    }
  }

  const disconnectNode = async (nodeId: string) => {
    try {
      await api.disconnectNode(nodeId, nodeId)
      console.log(`Disconnected from node ${nodeId}`)
    } catch (error) {
      console.error('Disconnect error:', error)
      throw error
    }
  }

  const monitorNode = async (nodeId: string) => {
    try {
      await api.monitorNode(nodeId, nodeId)
      console.log(`Monitoring node ${nodeId}`)
    } catch (error) {
      console.error('Monitor error:', error)
      throw error
    }
  }

  const permConnectNode = async (nodeId: string) => {
    try {
      await api.connectNode(nodeId, nodeId, true)
      console.log(`Permanently connected to node ${nodeId}`)
    } catch (error) {
      console.error('Perm connect error:', error)
      throw error
    }
  }

  const localMonitorNode = async (nodeId: string) => {
    try {
      await api.localMonitorNode(nodeId, nodeId)
      console.log(`Local monitoring node ${nodeId}`)
    } catch (error) {
      console.error('Local monitor error:', error)
      throw error
    }
  }

  const monitorCmdNode = async (nodeId: string) => {
    try {
      await api.monitorNode(nodeId, nodeId)
      console.log(`Monitor CMD for node ${nodeId}`)
    } catch (error) {
      console.error('Monitor CMD error:', error)
      throw error
    }
  }

  const reset = () => {
    nodes.value = []
    nodeConfig.value = {}
    astdb.value = {}
    isConnected.value = false
    error.value = null
    monitoringNodes.value = []
    stopPolling()
  }

  return {
    // State
    nodes,
    nodeConfig,
    astdb,
    isConnected,
    error,
    monitoringNodes,
    
    // Computed
    isMonitoring,
    
    // Actions
    initialize,
    startMonitoring,
    stopMonitoring,
    startPolling,
    stopPolling,
    fetchNodeData,
    getNodeById,
    getNodeInfo,
    clearError,
    connectNode,
    disconnectNode,
    monitorNode,
    permConnectNode,
    localMonitorNode,
    monitorCmdNode,
    reset
  }
})
