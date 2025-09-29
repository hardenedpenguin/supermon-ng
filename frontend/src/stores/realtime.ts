/**
 * Real-time data store using Pinia
 * Manages live data updates from WebSocket
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import websocketService, { WebSocketMessage } from '../services/websocket'

export interface NodeStatus {
  node_id: string
  status: string
  last_heard: string
  connected_nodes: any[]
  cos_keyed: string
  tx_keyed: string
  cpu_temp: string
  alert: string | null
  wx: string | null
  disk: string | null
  timestamp: string
}

export interface NodeData {
  id: string
  node_number: string
  callsign: string
  description: string
  location: string
  status: string
  last_heard: string | null
  connected_nodes: any[]
  cos_keyed: string
  tx_keyed: string
  cpu_temp: string | null
  alert: string | null
  wx: string | null
  disk: string | null
  is_online: boolean
  is_keyed: boolean
  created_at: string
  updated_at: string
}

export interface SystemInfo {
  uptime: string
  load_average: number[]
  memory_usage: {
    total: number
    used: number
    free: number
    percentage: number
  }
  disk_usage: {
    total: number
    used: number
    free: number
    percentage: number
  }
  network_interfaces: Array<{
    name: string
    status: string
    ip_addresses: string[]
  }>
  services: Array<{
    name: string
    status: string
    uptime: string
  }>
}

export interface MenuData {
  systems: Array<{
    id: string
    name: string
    description: string
    nodes: any[]
  }>
}

export const useRealtimeStore = defineStore('realtime', () => {
  // State
  const isConnected = ref(false)
  const lastUpdate = ref<Date | null>(null)
  const connectionStats = ref({
    total_connections: 0,
    authenticated_users: 0,
    active_topics: 0,
    topics: [] as string[]
  })

  // Node data
  const nodes = ref<NodeData[]>([])
  const nodeStatuses = ref<Map<string, NodeStatus>>(new Map())
  const systemInfo = ref<SystemInfo | null>(null)
  const menuData = ref<MenuData | null>(null)

  // Computed
  const onlineNodes = computed(() => 
    nodes.value.filter(node => node.is_online)
  )

  const keyedNodes = computed(() => 
    nodes.value.filter(node => node.is_keyed)
  )

  const nodeCount = computed(() => nodes.value.length)

  const isAnyNodeKeyed = computed(() => 
    nodes.value.some(node => node.is_keyed)
  )

  // Actions
  function initializeWebSocket() {
    // Connection state handlers
    websocketService.onConnectionChange((connected) => {
      isConnected.value = connected
      console.log('WebSocket connection state:', connected)
    })

    // Subscribe to all relevant topics
    websocketService.subscribe('node_status')
    websocketService.subscribe('node_list')
    websocketService.subscribe('system_info')
    websocketService.subscribe('menu_update')
    websocketService.subscribe('ami_status')
    websocketService.subscribe('config_update')
    websocketService.subscribe('node_data')
    websocketService.subscribe('heartbeat')
    websocketService.subscribe('errors')
    websocketService.subscribe('stats')

    // Message handlers
    websocketService.onMessage('node_status_update', handleNodeStatusUpdate)
    websocketService.onMessage('node_list_update', handleNodeListUpdate)
    websocketService.onMessage('system_info_update', handleSystemInfoUpdate)
    websocketService.onMessage('menu_update', handleMenuUpdate)
    websocketService.onMessage('ami_status_update', handleAmiStatusUpdate)
    websocketService.onMessage('config_update', handleConfigUpdate)
    websocketService.onMessage('node_data_batch', handleNodeDataBatch)
    websocketService.onMessage('connection_stats', handleConnectionStats)
    websocketService.onMessage('error_notification', handleErrorNotification)

    // Connect to WebSocket
    websocketService.connect().catch(error => {
      console.error('Failed to connect to WebSocket:', error)
    })
  }

  function authenticate(token: string) {
    websocketService.authenticate(token)
  }

  function disconnect() {
    websocketService.disconnect()
    isConnected.value = false
  }

  // Message handlers
  function handleNodeStatusUpdate(message: WebSocketMessage) {
    const nodeId = message.node_id
    const statusData = message.data
    
    nodeStatuses.value.set(nodeId, statusData)
    
    // Update corresponding node in nodes array
    const nodeIndex = nodes.value.findIndex(node => node.node_number === nodeId)
    if (nodeIndex !== -1) {
      nodes.value[nodeIndex] = {
        ...nodes.value[nodeIndex],
        status: statusData.status,
        last_heard: statusData.last_heard,
        cos_keyed: statusData.cos_keyed,
        tx_keyed: statusData.tx_keyed,
        cpu_temp: statusData.cpu_temp,
        alert: statusData.alert,
        wx: statusData.wx,
        disk: statusData.disk,
        is_online: statusData.status === 'online',
        is_keyed: statusData.cos_keyed === '1' || statusData.tx_keyed === '1',
        updated_at: new Date().toISOString()
      }
    }
    
    lastUpdate.value = new Date()
    console.log('Node status updated:', nodeId, statusData)
  }

  function handleNodeListUpdate(message: WebSocketMessage) {
    const nodeList = message.data as NodeData[]
    nodes.value = nodeList
    lastUpdate.value = new Date()
    console.log('Node list updated:', nodeList.length, 'nodes')
  }

  function handleSystemInfoUpdate(message: WebSocketMessage) {
    systemInfo.value = message.data
    lastUpdate.value = new Date()
    console.log('System info updated')
  }

  function handleMenuUpdate(message: WebSocketMessage) {
    menuData.value = message.data
    lastUpdate.value = new Date()
    console.log('Menu updated for user:', message.username)
  }

  function handleAmiStatusUpdate(message: WebSocketMessage) {
    const nodeId = message.node_id
    const status = message.status
    const messageText = message.message
    
    console.log('AMI status update:', nodeId, status, messageText)
    
    // Update node status based on AMI status
    const nodeIndex = nodes.value.findIndex(node => node.node_number === nodeId)
    if (nodeIndex !== -1) {
      nodes.value[nodeIndex].status = status
      nodes.value[nodeIndex].updated_at = new Date().toISOString()
    }
  }

  function handleConfigUpdate(message: WebSocketMessage) {
    const configType = message.config_type
    const configData = message.data
    
    console.log('Config update:', configType, configData)
    
    // Handle specific config updates
    switch (configType) {
      case 'nodes':
        nodes.value = configData
        break
      case 'system':
        systemInfo.value = configData
        break
      default:
        console.log('Unknown config type:', configType)
    }
    
    lastUpdate.value = new Date()
  }

  function handleNodeDataBatch(message: WebSocketMessage) {
    const nodeData = message.data as NodeData[]
    
    // Update nodes with batch data
    nodeData.forEach(nodeDataItem => {
      const nodeIndex = nodes.value.findIndex(node => node.node_number === nodeDataItem.node_number)
      if (nodeIndex !== -1) {
        nodes.value[nodeIndex] = {
          ...nodes.value[nodeIndex],
          ...nodeDataItem,
          updated_at: new Date().toISOString()
        }
      }
    })
    
    lastUpdate.value = new Date()
    console.log('Node data batch updated:', nodeData.length, 'nodes')
  }

  function handleConnectionStats(message: WebSocketMessage) {
    connectionStats.value = message.data
    console.log('Connection stats updated:', message.data)
  }

  function handleErrorNotification(message: WebSocketMessage) {
    const errorType = message.error_type
    const errorMessage = message.message
    const context = message.context
    
    console.error('Real-time error notification:', {
      type: errorType,
      message: errorMessage,
      context: context
    })
    
    // You could emit a global error event here or update error state
  }

  // Utility functions
  function getNodeStatus(nodeId: string): NodeStatus | null {
    return nodeStatuses.value.get(nodeId) || null
  }

  function getNode(nodeId: string): NodeData | null {
    return nodes.value.find(node => node.node_number === nodeId) || null
  }

  function refreshData() {
    // Trigger manual refresh by subscribing to all topics again
    websocketService.subscribe('node_list')
    websocketService.subscribe('system_info')
    websocketService.subscribe('menu_update')
  }

  return {
    // State
    isConnected,
    lastUpdate,
    connectionStats,
    nodes,
    nodeStatuses,
    systemInfo,
    menuData,
    
    // Computed
    onlineNodes,
    keyedNodes,
    nodeCount,
    isAnyNodeKeyed,
    
    // Actions
    initializeWebSocket,
    authenticate,
    disconnect,
    getNodeStatus,
    getNode,
    refreshData
  }
})
