// Configuration and system settings types

export interface SystemConfig {
  // Server information
  smServerName?: string
  version?: string
  versionDate?: string
  phpVersion?: string
  
  // Display settings
  callsign?: string
  location?: string
  title2?: string
  title3?: string
  maintainer?: string
  
  // Logo settings
  logoName?: string
  logoSize?: string
  logoPositionRight?: string
  logoPositionTop?: string
  logoUrl?: string
  
  // URLs and links
  myUrl?: string
  welcomeMsg?: string
  welcomeMsgLogged?: string
  
  // Background settings
  backgroundColor?: string
  backgroundHeight?: string
  displayBackground?: boolean
  
  // HamClock integration
  hamclockEnabled?: boolean
  hamclockUrlInternal?: string
  hamclockUrlExternal?: string
  
  // Feature flags
  enableNotifications?: boolean
  enableSSE?: boolean
  enableAutoRefresh?: boolean
}

export interface AllmonConfig {
  [nodeId: string]: {
    host: string
    port?: number
    user?: string
    pass?: string
    menu?: string
    system?: string
    hideNodeURL?: number
    lsnodes?: string
    listenlive?: string
    archive?: string
    [key: string]: any
  }
}

export interface FavoritesConfig {
  [key: string]: {
    label: string
    url?: string
    node?: string
    [key: string]: any
  }
}

export interface NodeInfoConfig {
  enabled: boolean
  customLink?: string
  autoSkyEnabled?: boolean
  sysInfoUser?: string
}

export interface DatabaseConfig {
  astdbPath?: string
  autoUpdate?: boolean
  updateInterval?: number
  backupEnabled?: boolean
}

export interface SecurityConfig {
  authEnabled: boolean
  sessionTimeout?: number
  maxLoginAttempts?: number
  rateLimitEnabled?: boolean
  csrfProtection?: boolean
}

export interface LoggingConfig {
  level: LogLevel
  enableFileLogging?: boolean
  enableConsoleLogging?: boolean
  maxLogSize?: string
  logRotation?: boolean
}

export interface ConfigValidationError {
  field: string
  message: string
  severity: 'error' | 'warning' | 'info'
}

export interface ConfigBackup {
  id: string
  filename: string
  created_at: string
  size: number
  description?: string
}

// Configuration categories
export type ConfigCategory = 
  | 'system'
  | 'nodes'
  | 'favorites'
  | 'security'
  | 'logging'
  | 'database'
  | 'display'

// Log levels
export type LogLevel = 
  | 'debug'
  | 'info'
  | 'warning'
  | 'error'
  | 'critical'
