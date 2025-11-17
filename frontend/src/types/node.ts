// Node and AllStar system types

export interface Node {
  id: string | number  // Allow both for compatibility
  node_number?: number
  callsign?: string
  description?: string
  location?: string
  status?: NodeStatus
  last_heard?: string
  connected_nodes?: ConnectedNode[]
  cos_keyed?: number
  tx_keyed?: number
  cpu_temp?: string
  cpu_up?: string
  cpu_load?: string
  alert?: string
  wx?: string
  disk?: string
  is_online?: boolean
  is_keyed?: boolean
  created_at?: string
  updated_at?: string
  // Legacy compatibility
  info?: string
  remote_nodes?: ConnectedNode[]
  // Additional AMI fields
  ALERT?: string
  WX?: string
  DISK?: string
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
    port?: number
    hideNodeURL?: number
    lsnodes?: string
    listenlive?: string
    archive?: string
    menu?: string
    system?: string
    [key: string]: any
  }
}

export interface NodeStatistics {
  nodeId: string
  totalConnections: number
  activeConnections: number
  uptime: string
  lastActivity: string
  trafficStats: {
    rxBytes: number
    txBytes: number
    packets: number
  }
}

export interface NodeCommand {
  command: string
  nodeId: string
  parameters?: Record<string, any>
  timeout?: number
}

export interface AstDbEntry {
  [nodeId: string]: string[]
}

export interface NodeAction {
  type: NodeActionType
  nodeId: string
  targetNodeId?: string
  permanent?: boolean
}

// Node status types
export type NodeStatus = 
  | 'online'
  | 'offline'
  | 'connecting'
  | 'error'
  | 'unknown'

// Node action types
export type NodeActionType = 
  | 'connect'
  | 'disconnect'
  | 'monitor'
  | 'local_monitor'
  | 'perm_connect'
  | 'reboot'
  | 'restart'

// Node connection states
export type NodeConnectionState = 
  | 'idle'
  | 'receiving'
  | 'transmitting'
  | 'full_duplex'
  | 'cos'
  | 'ptt'

// Node types
export type NodeType = 
  | 'repeater'
  | 'simplex'
  | 'echolink'
  | 'irlp'
  | 'hub'
