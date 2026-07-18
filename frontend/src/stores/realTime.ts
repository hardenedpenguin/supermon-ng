import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api, endpoints } from '@/utils/api'
import { appUrl } from '@/utils/basePath'
import { useAppStore } from './app'
import { useAstdbStore } from './astdb'
import { useBatchRequests } from '@/services/BatchRequestService'
import { webSocketService, type WebSocketMessage } from '@/services/WebSocketService'
import type { Node, ConnectedNode, NodeConfig, AstDbEntry, NodeActionType } from '@/types'

export interface RealTimeInitOptions {
  nodesConfig?: {
    config: Record<string, unknown>
    ini_file: string
    default_node: string | null
  }
}

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
  type NodeMonitoringMode = 'live' | 'polling' | 'connecting' | 'offline'
  /** Reactive per-node connection mode for UI (webSocketService state is not reactive). */
  const wsMonitoringModes = ref<Record<string, NodeMonitoringMode>>({})

  // Services
  const { batchInitialization, clearCache } = useBatchRequests({
    maxBatchSize: 5,
    batchDelay: 0,
    cacheEnabled: true,
    defaultCacheTTL: 5000
  })

  // WebSocket message handlers per node
  const messageHandlers = new Map<string, () => void>()
  const stateHandlers = new Map<string, () => void>()

  /** Poll AMI over HTTP (required for remote hosts and when WebSocket/AMI on 8105 is unavailable). */
  let amiPollTimer: ReturnType<typeof setInterval> | null = null
  const AMI_POLL_INTERVAL_MS = 8000
  const WS_RETRY_EVERY_N_POLLS = 4
  let amiPollCount = 0
  const monitoringPromises = new Map<string, Promise<void>>()
  const wsUrlByNode = new Map<string, string>()

  // Re-expose the ASTDB map as a computed so consumers stay reactive to the
  // astdb store reassigning fullAstdb. Reading astdbStore.fullAstdb directly
  // into the returned object would capture the initial (empty) map once and
  // never reflect later loads, leaving ASTDB fallback titles stale.
  const astdb = computed(() => astdbStore.fullAstdb)

  const setWsMonitoringMode = (nodeId: string, mode: NodeMonitoringMode) => {
    const key = String(nodeId)
    if (wsMonitoringModes.value[key] !== mode) {
      wsMonitoringModes.value = { ...wsMonitoringModes.value, [key]: mode }
    }
  }

  const refreshWsMonitoringMode = (nodeId: string) => {
    const key = String(nodeId)
    const wsState = webSocketService.getNodeState(key)
    if (wsState?.connected) {
      setWsMonitoringMode(key, 'live')
    } else if (wsState?.connecting) {
      setWsMonitoringMode(key, 'connecting')
    } else if (monitoringNodes.value.includes(key)) {
      setWsMonitoringMode(key, 'polling')
    } else {
      const next = { ...wsMonitoringModes.value }
      delete next[key]
      wsMonitoringModes.value = next
    }
  }

  const retryWebSocketsForMonitoring = async () => {
    const appStore = useAppStore()
    for (const nodeId of monitoringNodes.value) {
      if (webSocketService.isNodeConnected(nodeId)) {
        setWsMonitoringMode(nodeId, 'live')
        continue
      }
      const state = webSocketService.getNodeState(nodeId)
      if (state?.connecting) {
        continue
      }

      let wsUrl = wsUrlByNode.get(nodeId)
      if (!wsUrl) {
        wsUrl = await getWebSocketUrl(nodeId, appStore.isAuthenticated)
        wsUrlByNode.set(nodeId, wsUrl)
      }

      webSocketService.resetReconnectAttempts(nodeId)
      try {
        setWsMonitoringMode(nodeId, 'connecting')
        await webSocketService.connectToNode(nodeId, wsUrl)
        isConnected.value = true
        error.value = null
        setWsMonitoringMode(nodeId, 'live')
        reconcileAmiPolling()
      } catch (err) {
        console.warn(`WebSocket retry failed for node ${nodeId}:`, err)
        refreshWsMonitoringMode(nodeId)
        reconcileAmiPolling()
      }
    }
  }

  /** True when at least one monitored node has no live WebSocket (needs HTTP AMI). */
  const anyNodeNeedsAmiFallback = (): boolean =>
    monitoringNodes.value.some((id) => !webSocketService.isNodeConnected(id))

  const stopAmiPollTimer = () => {
    if (amiPollTimer) {
      clearInterval(amiPollTimer)
      amiPollTimer = null
    }
    amiPollCount = 0
  }

  const onAmiFallbackTick = () => {
    if (monitoringNodes.value.length === 0 || !anyNodeNeedsAmiFallback()) {
      stopAmiPollTimer()
      return
    }
    void fetchNodeData()
    amiPollCount++
    if (amiPollCount >= WS_RETRY_EVERY_N_POLLS) {
      amiPollCount = 0
      void retryWebSocketsForMonitoring()
    }
  }

  /**
   * Start HTTP AMI polling only while WebSocket is unavailable for monitored node(s).
   * When all nodes are live on WS, polling stops — WS pushes updates (~1s server-side).
   */
  const reconcileAmiPolling = () => {
    if (monitoringNodes.value.length === 0 || !anyNodeNeedsAmiFallback()) {
      stopAmiPollTimer()
      return
    }
    if (amiPollTimer) {
      return
    }
    void fetchNodeData()
    amiPollTimer = setInterval(onAmiFallbackTick, AMI_POLL_INTERVAL_MS)
  }

  // Computed
  const isMonitoring = computed(() => monitoringNodes.value.length > 0)

  // Actions
  const initialize = async (options?: RealTimeInitOptions) => {
    try {
      const startTime = Date.now()
      const skipNodesConfig = !!options?.nodesConfig?.config

      const batchResult = await batchInitialization({ skipNodesConfig })
      
      // Process nodes data - merge with existing nodes to preserve WebSocket updates
      if (batchResult.nodes?.data) {
        const rawNodes = batchResult.nodes.data || []
        const newNodes = rawNodes.map((node: any) => {
          // Header uses description only, not full format
          // Full format (callsign + description + location) is used for connected nodes
          const info = node.info || node.description || `Node ${node.node_number || node.id}`
          return {
            ...node,
            info
          }
        })
        
        // Merge new nodes with existing nodes (preserve WebSocket-updated data)
        newNodes.forEach((newNode: Node) => {
          const nodeId = String(newNode.id || newNode.node_number)
          const existingIndex = nodes.value.findIndex(n => String(n.id) === nodeId)
          
          if (existingIndex > -1) {
            // Merge: preserve WebSocket-updated real-time fields, update static fields from API
            const existing = nodes.value[existingIndex]
            nodes.value[existingIndex] = {
              ...existing, // Start with existing (has WebSocket real-time data)
              // Update static fields from API (callsign, description, location)
              callsign: newNode.callsign || existing.callsign,
              description: newNode.description || existing.description,
              location: newNode.location || existing.location,
              node_number: newNode.node_number || existing.node_number,
              id: newNode.id || existing.id,
              info: newNode.info || existing.info,
              // Preserve WebSocket-provided connection lists (the API bootstrap
              // does not carry live connection detail), but let this refresh
              // correct online/status — otherwise a stale value would stick
              // forever once set.
              connected_nodes: existing.connected_nodes || newNode.connected_nodes,
              remote_nodes: existing.remote_nodes || newNode.remote_nodes,
              status: newNode.status ?? existing.status,
              is_online: newNode.is_online ?? existing.is_online,
              last_updated: newNode.last_updated ?? existing.last_updated
            } as Node
          } else {
            // Add new node
            nodes.value.push(newNode)
          }
        })
        
        // Remove nodes that are no longer in the API response (cleanup)
        const newNodeIds = new Set(newNodes.map(n => String(n.id || n.node_number)))
        nodes.value = nodes.value.filter(n => newNodeIds.has(String(n.id || n.node_number)))
      }
      
      if (options?.nodesConfig?.config) {
        nodeConfig.value = options.nodesConfig.config as NodeConfig
      } else if (batchResult.config?.data?.config) {
        nodeConfig.value = batchResult.config.data.config
      }

      await astdbStore.initialize()

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
   * Get authenticated WebSocket URL for a node (includes short-lived token).
   */
  const getWebSocketUrl = async (nodeId: string, authenticated: boolean): Promise<string> => {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'
    const host = window.location.host
    const baseUrl = `${protocol}//${host}${appUrl(`ws/${nodeId}`)}`

    if (!authenticated) {
      return baseUrl
    }

    try {
      const response = await api.get(`/nodes/${encodeURIComponent(nodeId)}/websocket/token`)
      if (response.data?.success && response.data?.ws_url) {
        return response.data.ws_url as string
      }
    } catch (err) {
      console.warn(`WebSocket token unavailable for node ${nodeId}:`, err)
    }

    return baseUrl
  }

  /**
   * Start monitoring a node via WebSocket
   */
  const startMonitoring = async (nodeId: string) => {
    const nodeKey = String(nodeId).trim()
    if (!nodeKey) {
      return
    }

    if (!monitoringNodes.value.includes(nodeKey)) {
      monitoringNodes.value.push(nodeKey)
    }

    if (webSocketService.isNodeConnected(nodeKey)) {
      setWsMonitoringMode(nodeKey, 'live')
      reconcileAmiPolling()
      return
    }

    const inflight = monitoringPromises.get(nodeKey)
    if (inflight) {
      await inflight
      return
    }

    const task = (async () => {
      if (Object.keys(websocketPorts.value).length === 0) {
        await fetchWebSocketPorts()
      }

      try {
        const appStore = useAppStore()
        const wsUrl = await getWebSocketUrl(nodeKey, appStore.isAuthenticated)
        wsUrlByNode.set(nodeKey, wsUrl)

        if (!messageHandlers.has(nodeKey)) {
          const unsubscribeMessage = webSocketService.onNodeMessage(nodeKey, (data: WebSocketMessage) => {
            updateNodeFromWebSocket(data)
          })
          messageHandlers.set(nodeKey, unsubscribeMessage)
        }

        if (!stateHandlers.has(nodeKey)) {
          const unsubscribeState = webSocketService.onNodeStateChange(nodeKey, (state) => {
            refreshWsMonitoringMode(nodeKey)
            reconcileAmiPolling()
            if (state.connected) {
              isConnected.value = true
              error.value = null
            } else if (state.error) {
              error.value = `WebSocket error for node ${nodeKey}: ${state.error}`
            }
          })
          stateHandlers.set(nodeKey, unsubscribeState)
        }

        if (webSocketService.isNodeConnected(nodeKey)) {
          setWsMonitoringMode(nodeKey, 'live')
          return
        }

        setWsMonitoringMode(nodeKey, 'connecting')
        await webSocketService.connectToNode(nodeKey, wsUrl)

        isConnected.value = true
        error.value = null
        setWsMonitoringMode(nodeKey, 'live')
      } catch (err) {
        console.error(`Error connecting to WebSocket for node ${nodeKey}:`, err)
        error.value = `Failed to connect to node ${nodeKey}`
        refreshWsMonitoringMode(nodeKey)
      }

      reconcileAmiPolling()
      if (anyNodeNeedsAmiFallback()) {
        await fetchNodeData()
      }
    })()

    monitoringPromises.set(nodeKey, task)
    try {
      await task
    } finally {
      monitoringPromises.delete(nodeKey)
    }
  }

  /**
   * Stop monitoring a node
   */
  /**
   * Keep monitoring only the selected node(s); stop WebSocket/poll for deselected nodes.
   */
  const syncMonitoringForSelection = async (nodeIds: string[]) => {
    const desired = [...new Set(nodeIds.map((id) => String(id).trim()).filter(Boolean))]
    const current = [...monitoringNodes.value]

    for (const nodeId of current) {
      if (!desired.includes(nodeId)) {
        stopMonitoring(nodeId)
      }
    }

    await Promise.all(desired.map((nodeId) => startMonitoring(nodeId)))
  }

  const getNodeMonitoringMode = (nodeId: string): NodeMonitoringMode => {
    const key = String(nodeId)
    const cached = wsMonitoringModes.value[key]
    if (cached) {
      return cached
    }
    if (monitoringNodes.value.includes(key)) {
      return 'polling'
    }
    return 'offline'
  }

  const stopMonitoring = (nodeId: string) => {
    const nodeKey = String(nodeId).trim()
    const index = monitoringNodes.value.indexOf(nodeKey)
    if (index > -1) {
      monitoringNodes.value.splice(index, 1)
    }

    wsUrlByNode.delete(nodeKey)

    const nextModes = { ...wsMonitoringModes.value }
    delete nextModes[nodeKey]
    wsMonitoringModes.value = nextModes
    
    // Disconnect WebSocket
    webSocketService.disconnectFromNode(nodeKey)
    
    // Clean up handlers
    const messageUnsubscribe = messageHandlers.get(nodeKey)
    if (messageUnsubscribe) {
      messageUnsubscribe()
      messageHandlers.delete(nodeKey)
    }
    
    const stateUnsubscribe = stateHandlers.get(nodeKey)
    if (stateUnsubscribe) {
      stateUnsubscribe()
      stateHandlers.delete(nodeKey)
    }
    
    // Update connection status
    if (monitoringNodes.value.length === 0) {
      isConnected.value = false
    }

    reconcileAmiPolling()
  }

  /**
   * Update node data from WebSocket message
   * Handles structured JSON data from backend
   */
  const updateNodeFromWebSocket = (data: WebSocketMessage) => {
    try {
      const nodeId = String(data.node)
      setWsMonitoringMode(nodeId, 'live')
      const existingNodeIndex = nodes.value.findIndex(n => String(n.id) === String(nodeId))
      
      // Map WebSocket data to Node structure (only update fields that are provided)
      const nodeUpdate: Partial<Node> = {
        status: data.status || 'online',
        is_online: (data.status || 'online') === 'online',
        is_keyed: (data.cos_keyed ?? 0) > 0 || (data.tx_keyed ?? 0) > 0,
        last_updated: Date.now(),
        updated_at: new Date().toISOString()
      }
      
      // Only update these fields if they are provided (not null/undefined)
      if (data.cos_keyed !== undefined && data.cos_keyed !== null) {
        nodeUpdate.cos_keyed = data.cos_keyed
      }
      if (data.tx_keyed !== undefined && data.tx_keyed !== null) {
        nodeUpdate.tx_keyed = data.tx_keyed
      }
      if (data.cpu_temp !== undefined && data.cpu_temp !== null) {
        nodeUpdate.cpu_temp = data.cpu_temp
      }
      if (data.cpu_up !== undefined && data.cpu_up !== null) {
        nodeUpdate.cpu_up = data.cpu_up
      }
      if (data.cpu_load !== undefined && data.cpu_load !== null) {
        nodeUpdate.cpu_load = data.cpu_load
      }
      if (data.ALERT !== undefined && data.ALERT !== null) {
        nodeUpdate.ALERT = data.ALERT
      }
      if (data.WX !== undefined && data.WX !== null) {
        nodeUpdate.WX = data.WX
      }
      if (data.DISK !== undefined && data.DISK !== null) {
        nodeUpdate.DISK = data.DISK
      }
      
      // Process remote nodes if available (only update if provided)
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
      // Note: If remote_nodes is not provided, we preserve existing connected_nodes
      
      if (existingNodeIndex > -1) {
        // Update existing node - preserve all existing fields, only update provided ones
        const existingNode = nodes.value[existingNodeIndex]
        nodes.value[existingNodeIndex] = {
          ...existingNode,
          ...nodeUpdate
        } as Node
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
              // Add new node with AMI data (e.g. remote node selected before /nodes list refresh)
              nodes.value.push({
                id: nodeId,
                node_number: parseInt(nodeId, 10) || nodeId,
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
      
      lastUpdateTime.value = Date.now()
      error.value = null
    } catch (err: unknown) {
      // Suppress timeout errors for real-time data fetching
      const errCode = (err as { code?: string })?.code
      if (errCode !== 'ECONNABORTED') {
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
  const connectNode = async (localNode: string, remoteNode: string, perm: boolean = false) => {
    try {
      await api.post(endpoints.nodes.connect, {
        localnode: localNode,
        remotenode: remoteNode,
        perm,
      })
    } catch (error) {
      console.error('Connect error:', error)
      throw error
    }
  }

  const disconnectNode = async (localNode: string, remoteNode: string) => {
    try {
      await api.post(endpoints.nodes.disconnect, {
        localnode: localNode,
        remotenode: remoteNode,
      })
    } catch (error) {
      console.error('Disconnect error:', error)
      throw error
    }
  }

  const monitorNode = async (localNode: string, remoteNode: string) => {
    try {
      await api.post(endpoints.nodes.monitor, {
        localnode: localNode,
        remotenode: remoteNode,
      })
    } catch (error) {
      console.error('Monitor error:', error)
      throw error
    }
  }

  const permConnectNode = async (localNode: string, remoteNode: string) => {
    try {
      await api.post(endpoints.nodes.connect, {
        localnode: localNode,
        remotenode: remoteNode,
        perm: true,
      })
    } catch (error) {
      console.error('Perm connect error:', error)
      throw error
    }
  }

  const localMonitorNode = async (localNode: string, remoteNode: string) => {
    try {
      await api.post(endpoints.nodes.localMonitor, {
        localnode: localNode,
        remotenode: remoteNode,
      })
    } catch (error) {
      console.error('Local monitor error:', error)
      throw error
    }
  }

  const monitorCmdNode = async (localNode: string, remoteNode: string) => {
    try {
      await api.post(endpoints.nodes.monitor, {
        localnode: localNode,
        remotenode: remoteNode,
      })
    } catch (error) {
      console.error('Monitor CMD error:', error)
      throw error
    }
  }

  const reset = () => {
    stopAmiPollTimer()
    monitoringPromises.clear()
    wsUrlByNode.clear()
    wsMonitoringModes.value = {}

    // Disconnect all WebSocket connections. Iterate a snapshot because
    // stopMonitoring() splices monitoringNodes.value, and mutating the array
    // being iterated would skip entries and leak their WebSocket connections.
    ;[...monitoringNodes.value].forEach(nodeId => {
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
    astdb,
    astdbStore,
    isConnected,
    error,
    monitoringNodes,
    lastUpdateTime,
    websocketPorts,
    wsMonitoringModes,
    
    // Computed
    isMonitoring,
    
    // Actions
    initialize,
    startMonitoring,
    stopMonitoring,
    syncMonitoringForSelection,
    getNodeMonitoringMode,
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
