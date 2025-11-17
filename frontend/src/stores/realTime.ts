import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/utils/api'
import { useAstdbStore } from './astdb'
import { usePolling, type PollingConfig } from '@/services/PollingService'
import { useBatchRequests } from '@/services/BatchRequestService'
import { useCsrfToken } from '@/services/CsrfTokenService'
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

  // Services
  const pollingConfig: PollingConfig = {
    activeInterval: 1000,      // 1 second when active
    inactiveInterval: 5000,    // 5 seconds when inactive  
    backgroundInterval: 10000, // 10 seconds when tab hidden
    inactiveThreshold: 30000   // 30 seconds to become inactive
  }
  
  const { state: pollingState, start: startPolling, stop: stopPolling, makeRequest, onVisibilityChange } = usePolling(pollingConfig)
  const { batchInitialization, batchRealTimeUpdate, clearCache } = useBatchRequests({
    maxBatchSize: 5,
    batchDelay: 25, // Faster batching
    cacheEnabled: true,
    defaultCacheTTL: 5000 // 5 second default cache
  })
  const { getToken } = useCsrfToken({
    tokenLifetime: 3600000,    // 1 hour
    refreshThreshold: 300000,  // Refresh 5 minutes before expiry
    requestTimeout: 3000       // Faster timeout
  })

  // Setup visibility change handler once when store is created
  onVisibilityChange(() => {
    console.log('🔄 Tab visibility changed - isConnected:', isConnected.value, 'monitoring:', monitoringNodes.value.length)
    // Only refresh if we're actively monitoring nodes
    if (isConnected.value && monitoringNodes.value.length > 0) {
      console.log('✅ Clearing cache and fetching fresh data')
      clearCache()
      fetchNodeDataOptimized()
    }
  })

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
      
      const duration = Date.now() - startTime
      
      error.value = null
      lastUpdateTime.value = Date.now()
    } catch (err) {
      error.value = 'Failed to initialize real-time store'
      console.error('Real-time store initialization error:', err)
    }
  }

  const startMonitoring = (nodeId: string) => {
    if (!monitoringNodes.value.includes(nodeId)) {
      monitoringNodes.value.push(nodeId)
      
      // If polling is already active, fetch data for the new node
      if (isConnected.value) {
        fetchNodeDataOptimized()
      }
    }
    
    if (!isConnected.value) {
      startIntelligentPolling()
    }
  }

  const stopMonitoring = (nodeId: string) => {
    const index = monitoringNodes.value.indexOf(nodeId)
    if (index > -1) {
      monitoringNodes.value.splice(index, 1)
    }
    
    if (monitoringNodes.value.length === 0) {
      stopIntelligentPolling()
    }
  }

  const startIntelligentPolling = () => {
    isConnected.value = true
    
    // Clear cache to ensure fresh data
    clearCache()
    
    // Initial data fetch
    fetchNodeDataOptimized()
    
    // Start the intelligent polling service
    startPolling()
    
    // Set up simple interval to fetch data every second (matching active polling rate)
    const pollInterval = setInterval(() => {
      if (isConnected.value && monitoringNodes.value.length > 0) {
        fetchNodeDataOptimized()
      }
    }, 1000) // Fixed 1 second interval for real-time updates
    
    // Store interval ID for cleanup
    ;(window as any).__supermonPollInterval = pollInterval
  }

  const stopIntelligentPolling = () => {
    stopPolling()
    isConnected.value = false
    
    // Clear the polling interval
    if ((window as any).__supermonPollInterval) {
      clearInterval((window as any).__supermonPollInterval)
      ;(window as any).__supermonPollInterval = null
    }
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
      // Suppress timeout errors for real-time data fetching
      if (err.code !== 'ECONNABORTED') {
        console.error('Error fetching node data:', err)
        error.value = 'Failed to fetch node data'
        isConnected.value = false
      }
    }
  }





  const fetchNodeDataOptimized = async () => {
    try {
      if (monitoringNodes.value.length === 0) {
        return // No nodes to monitor
      }

      const startTime = Date.now()
      
      // Use batch real-time update for better performance
      const batchResult = await batchRealTimeUpdate(monitoringNodes.value)
      
      if (batchResult.amiStatus?.success && batchResult.amiStatus?.data) {
        const amiData = batchResult.amiStatus.data
        
        // Update nodes with AMI data (same logic as original but more efficient)
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
              info: amiNode.info,
              last_updated: Date.now()
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
              remote_nodes: amiNode.remote_nodes,
              last_updated: Date.now()
            })
          }
        })
        
        const duration = Date.now() - startTime
      }
      
      error.value = null
      lastUpdateTime.value = Date.now()
    } catch (err) {
      console.error('RealTime Store: Error fetching optimized node data:', err)
      error.value = 'Failed to fetch node data'
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
      await api.connectNode(nodeId, nodeId, perm)
    } catch (error) {
      console.error('Connect error:', error)
      throw error
    }
  }

  const disconnectNode = async (nodeId: string) => {
    try {
      await api.disconnectNode(nodeId, nodeId)
    } catch (error) {
      console.error('Disconnect error:', error)
      throw error
    }
  }

  const monitorNode = async (nodeId: string) => {
    try {
      await api.monitorNode(nodeId, nodeId)
    } catch (error) {
      console.error('Monitor error:', error)
      throw error
    }
  }

  const permConnectNode = async (nodeId: string) => {
    try {
      await api.connectNode(nodeId, nodeId, true)
    } catch (error) {
      console.error('Perm connect error:', error)
      throw error
    }
  }

  const localMonitorNode = async (nodeId: string) => {
    try {
      await api.localMonitorNode(nodeId, nodeId)
    } catch (error) {
      console.error('Local monitor error:', error)
      throw error
    }
  }

  const monitorCmdNode = async (nodeId: string) => {
    try {
      await api.monitorNode(nodeId, nodeId)
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
    astdb: astdbStore.fullAstdb,
    astdbStore,
    isConnected,
    error,
    monitoringNodes,
    lastUpdateTime,
    
    // Computed
    isMonitoring,
    
    // Actions
    initialize,
    startMonitoring,
    stopMonitoring,
    startPolling,
    stopPolling,
    fetchNodeData,
    fetchNodeDataOptimized,
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
    clearCache,
    pollingState
  }
})
