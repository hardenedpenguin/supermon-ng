// System information and monitoring types

export interface SystemInfo {
  // Server details
  hostname?: string
  os?: string
  kernel?: string
  architecture?: string
  uptime?: string
  
  // Software versions
  phpVersion?: string
  apacheVersion?: string
  asteriskVersion?: string
  allstarVersion?: string
  
  // Resource usage
  cpu?: CpuInfo
  memory?: MemoryInfo
  disk?: DiskInfo[]
  network?: NetworkInfo[]
  
  // Load averages
  loadAverage?: LoadAverage
  
  // Process information
  processes?: ProcessInfo[]
  
  // System status
  systemLoad?: number
  temperature?: number
  services?: ServiceStatus[]
}

export interface CpuInfo {
  model?: string
  cores?: number
  usage?: number
  temperature?: number
  frequency?: number
}

export interface MemoryInfo {
  total?: number
  used?: number
  free?: number
  available?: number
  buffers?: number
  cached?: number
  usagePercent?: number
}

export interface DiskInfo {
  device: string
  mountPoint: string
  fileSystem: string
  size: number
  used: number
  available: number
  usagePercent: number
}

export interface NetworkInfo {
  interface: string
  ip?: string
  mac?: string
  rxBytes?: number
  txBytes?: number
  rxPackets?: number
  txPackets?: number
  status?: 'up' | 'down'
}

export interface LoadAverage {
  oneMinute: number
  fiveMinute: number
  fifteenMinute: number
}

export interface ProcessInfo {
  pid: number
  name: string
  cpu: number
  memory: number
  status: string
}

export interface ServiceStatus {
  name: string
  status: 'active' | 'inactive' | 'failed' | 'unknown'
  enabled: boolean
  description?: string
}

export interface LogEntry {
  timestamp: string
  level: LogLevel
  category: string
  message: string
  details?: any
  source?: string
}

export interface SystemAlert {
  id: string
  type: AlertType
  severity: AlertSeverity
  title: string
  message: string
  timestamp: string
  acknowledged?: boolean
  source?: string
  details?: any
}

export interface PerformanceMetric {
  timestamp: string
  metric: string
  value: number
  unit?: string
  threshold?: number
}

// System status types
export type SystemStatus = 'healthy' | 'warning' | 'critical' | 'unknown'

// Alert types
export type AlertType = 
  | 'system'
  | 'network'
  | 'disk'
  | 'memory'
  | 'cpu'
  | 'service'
  | 'security'

// Alert severity levels
export type AlertSeverity = 'info' | 'warning' | 'error' | 'critical'

// Log levels (re-exported from config.ts to avoid conflicts)
export type { LogLevel } from './config'
