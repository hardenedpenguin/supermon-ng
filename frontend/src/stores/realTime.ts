import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api, endpoints } from '@/utils/api'
import { useAstdbStore } from './astdb'
import { useBatchRequests } from '@/services/BatchRequestService'
import { webSocketService, type WebSocketMessage } from '@/services/WebSocketService'
import type { Node, ConnectedNode, NodeConfig, AstDbEntry, NodeActionType } from '@/types'

export const useRealTimeStore = defineStore('realTime', () => {
  // State
  const nodes = ref<Node[]>([])
  const nodeConfig = ref<NodeConfig>({})
  const astdbStore = useAstdbStore()
  const isConnected = ref(false)
  const error = ref<string | null>(null)
  const monitoringNodes = ref<string[]>([])
  const lastUpdateTime = ref<number>(0)
  const websocketPorts = ref<Record<string, number>>({})

  // Services
  const { batchInitialization, clearCache } = useBatchRequests({
    maxBatchSize: 5,
    batchDelay: 25,
    cacheEnabled: true,
    defaultCacheTTL: 5000
  })

  // WebSocket message handlers per node
  const messageHandlers = new Map<string, () => void>()
  const stateHandlers = new Map<string, () => void>()

  // Computed
  const isMonitoring = computed(() => monitoringNodes.value.length > 0)

  // Actions
  const initialize = async () => {
    try {
      const startTime = Date.now()
      
      // Use batch initialization for better performance
      const batchResult = await batchInitialization()
      
      // Process nodes data
      if (batchResult.nodes?.data) {
        const rawNodes = batchResult.nodes.data || []
        nodes.value = rawNodes.map((node: any) => ({
          ...node,
          info: `${node.node_number || node.id} - ${node.description || 'Unknown'}`
        }))
      }
      
      // Process configuration data
      if (batchResult.config?.data?.config) {
        nodeConfig.value = batchResult.config.data.config
      }
      
      // Initialize ASTDB store (will use caching)
      await astdbStore.initialize()
      
      // Fetch WebSocket port configuration for all nodes
      await fetchWebSocketPorts()
      
      const duration = Date.now() - startTime
      
      error.value = null
      lastUpdateTime.value = Date.now()
    } catch (err) {
      error.value = 'Failed to initialize real-time store'
      console.error('Real-time store initialization error:', err)
    }
  }

  /**
   * Fetch WebSocket port configuration for all nodes
   */
  const fetchWebSocketPorts = async () => {
    try {
      const response = await api.get(endpoints.nodes.websocketPorts)
      if (response.data.success && response.data.nodes) {
        Object.keys(response.data.nodes).forEach(nodeId => {
          websocketPorts.value[nodeId] = response.data.nodes[nodeId].port
        })
      }
    } catch (err) {
      console.error('Error fetching WebSocket ports:', err)
    }
  }

  /**
   * Get WebSocket URL for a node
   */
  const getWebSocketUrl = (nodeId: string): string => {
    // Use the port from configuration or construct URL
    const port = websocketPorts.value[nodeId]
    if (port) {
      // Construct WebSocket URL - Apache will proxy /supermon-ng/ws/{nodeId} to ws://localhost:{port}
      const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'
      const host = window.location.host
      return `${protocol}//${host}/supermon-ng/ws/${nodeId}`
    }
    
    // Fallback: construct URL based on node index (if ports not loaded yet)
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'
    const host = window.location.host
    return `${protocol}//${host}/supermon-ng/ws/${nodeId}`
  }

  /**
   * Start monitoring a node via WebSocket
   */
  const startMonitoring = async (nodeId: string) => {
    if (!monitoringNodes.value.includes(nodeId)) {
      monitoringNodes.value.push(nodeId)
    }
    
    // If WebSocket ports not loaded, fetch them
    if (Object.keys(websocketPorts.value).length === 0) {
      await fetchWebSocketPorts()
    }
    
    // Connect to node's WebSocket
    try {
      const wsUrl = getWebSocketUrl(nodeId)
      
      // Set up message handler
      const unsubscribeMessage = webSocketService.onNodeMessage(nodeId, (data: WebSocketMessage) => {
        updateNodeFromWebSocket(data)
      })
      messageHandlers.set(nodeId, unsubscribeMessage)
      
      // Set up state change handler
      const unsubscribeState = webSocketService.onNodeStateChange(nodeId, (state) => {
        if (state.connected) {
          isConnected.value = true
          error.value = null
        } else if (state.error) {
          error.value = `WebSocket error for node ${nodeId}: ${state.error}`
        }
      })
      stateHandlers.set(nodeId, unsubscribeState)
      
      // Connect to WebSocket
      await webSocketService.connectToNode(nodeId, wsUrl)
      
      isConnected.value = true
      error.value = null
    } catch (err) {
      console.error(`Error connecting to WebSocket for node ${nodeId}:`, err)
      error.value = `Failed to connect to node ${nodeId}`
      
      // Remove from monitoring if connection failed
      const index = monitoringNodes.value.indexOf(nodeId)
      if (index > -1) {
        monitoringNodes.value.splice(index, 1)
      }
    }
  }

  /**
   * Stop monitoring a node
   */
  const stopMonitoring = (nodeId: string) => {
    const index = monitoringNodes.value.indexOf(nodeId)
    if (index > -1) {
      monitoringNodes.value.splice(index, 1)
    }
    
    // Disconnect WebSocket
    webSocketService.disconnectFromNode(nodeId)
    
    // Clean up handlers
    const messageUnsubscribe = messageHandlers.get(nodeId)
    if (messageUnsubscribe) {
      messageUnsubscribe()
      messageHandlers.delete(nodeId)
    }
    
    const stateUnsubscribe = stateHandlers.get(nodeId)
    if (stateUnsubscribe) {
      stateUnsubscribe()
      stateHandlers.delete(nodeId)
    }
    
    // Update connection status
    if (monitoringNodes.value.length === 0) {
      isConnected.value = false
    }
  }

  /**
   * Update node data from WebSocket message
   * Handles structured JSON data from backend
   */
  const updateNodeFromWebSocket = (data: WebSocketMessage) => {
    try {
      const nodeId = String(data.node)
      const existingNodeIndex = nodes.value.findIndex(n => String(n.id) === String(nodeId))
      
      // Map WebSocket data to Node structure
      const nodeUpdate: Partial<Node> = {
        status: data.status || 'online',
        cos_keyed: data.cos_keyed ?? 0,
        tx_keyed: data.tx_keyed ?? 0,
        cpu_temp: data.cpu_temp ?? null,
        cpu_up: data.cpu_up ?? null,
        cpu_load: data.cpu_load ?? null,
        ALERT: data.ALERT ?? null,
        WX: data.WX ?? null,
        DISK: data.DISK ?? null,
        is_online: (data.status || 'online') === 'online',
        is_keyed: (data.cos_keyed ?? 0) > 0 || (data.tx_keyed ?? 0) > 0,
        last_updated: Date.now(),
        updated_at: new Date().toISOString()
      }
      
      // Process remote nodes if available
      if (data.remote_nodes && Array.isArray(data.remote_nodes)) {
        // Convert remote nodes to connected_nodes format
        const connectedNodes: ConnectedNode[] = data.remote_nodes.map((remote: any) => ({
          node: String(remote.node || ''),
          info: remote.info || `Node ${remote.node}`,
          ip: remote.ip || '',
          direction: remote.direction || '',
          elapsed: remote.elapsed || '',
          link: remote.link || 'UNKNOWN',
          keyed: remote.keyed || 'n/a',
          last_keyed: remote.last_keyed || '-1',
          mode: remote.mode || 'Allstar'
        }))
        
        nodeUpdate.connected_nodes = connectedNodes
        nodeUpdate.remote_nodes = connectedNodes
      }
      
      if (existingNodeIndex > -1) {
        // Update existing node
        nodes.value[existingNodeIndex] = {
          ...nodes.value[existingNodeIndex],
          ...nodeUpdate
        }
      } else {
        // Add new node if it doesn't exist
        // Try to get info from ASTDB
        const astdbEntry = astdbStore.fullAstdb[nodeId]
        let info = `Node ${nodeId}`
        let callsign = 'N/A'
        let description = 'Unknown'
        let location = 'N/A'
        
        if (astdbEntry && Array.isArray(astdbEntry) && astdbEntry.length >= 4) {
          callsign = astdbEntry[1] || 'N/A'
          description = astdbEntry[2] || 'Unknown'
          location = astdbEntry[3] || 'N/A'
          info = `${callsign} ${description} ${location}`.trim()
        }
        
        nodes.value.push({
          id: parseInt(nodeId),
          node_number: parseInt(nodeId),
          callsign,
          description,
          location,
          last_heard: null,
          created_at: new Date().toISOString(),
          info,
          ...nodeUpdate
        } as Node)
      }
      
      lastUpdateTime.value = Date.now()
    } catch (err) {
      console.error('Error updating node from WebSocket:', err)
    }
  }

  /**
   * Fetch node data (for initial load or fallback)
   */
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
            const existingNodeIndex = nodes.value.findIndex(n => String(n.id) === String(nodeId))
            
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
      // Suppress timeout errors for real-time data fetching
      if (err.code !== 'ECONNABORTED') {
        console.error('Error fetching node data:', err)
        error.value = 'Failed to fetch node data'
        isConnected.value = false
      }
    }
  }

  const getNodeById = (nodeId: string): Node | undefined => {
    return nodes.value.find(n => String(n.id) === String(nodeId))
  }

  const getNodeInfo = (nodeId: string): string => {
    const node = getNodeById(nodeId)
    if (node) {
      return node.info
    }
    
    // Fallback to ASTDB using the optimized store
    const astdbEntry = astdbStore.fullAstdb[nodeId]
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
      await api.post(endpoints.nodes.connect(nodeId), {
        localnode: nodeId,
        remotenode: nodeId,
        perm
      })
    } catch (error) {
      console.error('Connect error:', error)
      throw error
    }
  }

  const disconnectNode = async (nodeId: string) => {
    try {
      await api.post(endpoints.nodes.disconnect(nodeId), {
        localnode: nodeId,
        remotenode: nodeId
      })
    } catch (error) {
      console.error('Disconnect error:', error)
      throw error
    }
  }

  const monitorNode = async (nodeId: string) => {
    try {
      await api.post(endpoints.nodes.monitor(nodeId), {
        localnode: nodeId,
        remotenode: nodeId
      })
    } catch (error) {
      console.error('Monitor error:', error)
      throw error
    }
  }

  const permConnectNode = async (nodeId: string) => {
    try {
      await api.post(endpoints.nodes.connect(nodeId), {
        localnode: nodeId,
        remotenode: nodeId,
        perm: true
      })
    } catch (error) {
      console.error('Perm connect error:', error)
      throw error
    }
  }

  const localMonitorNode = async (nodeId: string) => {
    try {
      await api.post(endpoints.nodes.localMonitor(nodeId), {
        localnode: nodeId,
        remotenode: nodeId
      })
    } catch (error) {
      console.error('Local monitor error:', error)
      throw error
    }
  }

  const monitorCmdNode = async (nodeId: string) => {
    try {
      await api.post(endpoints.nodes.monitor(nodeId), {
        localnode: nodeId,
        remotenode: nodeId
      })
    } catch (error) {
      console.error('Monitor CMD error:', error)
      throw error
    }
  }

  const reset = () => {
    // Disconnect all WebSocket connections
    monitoringNodes.value.forEach(nodeId => {
      stopMonitoring(nodeId)
    })
    
    nodes.value = []
    nodeConfig.value = {}
    isConnected.value = false
    error.value = null
    monitoringNodes.value = []
    websocketPorts.value = {}
  }

  return {
    // State
    nodes,
    nodeConfig,
    astdb: astdbStore.fullAstdb,
    astdbStore,
    isConnected,
    error,
    monitoringNodes,
    lastUpdateTime,
    websocketPorts,
    
    // Computed
    isMonitoring,
    
    // Actions
    initialize,
    startMonitoring,
    stopMonitoring,
    fetchNodeData,
    updateNodeFromWebSocket,
    getNodeById,
    getNodeInfo,
    clearError,
    connectNode,
    disconnectNode,
    monitorNode,
    permConnectNode,
    localMonitorNode,
    monitorCmdNode,
    reset,
    
    // Optimization features
    clearCache
  }
})
