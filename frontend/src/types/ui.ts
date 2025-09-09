// UI component and theme types

export interface Theme {
  name: string
  displayName: string
  primaryColor: string
  secondaryColor: string
  backgroundColor: string
  surfaceColor: string
  textColor: string
  borderColor: string
  accentColor: string
  successColor: string
  warningColor: string
  errorColor: string
  infoColor: string
  // Status colors for nodes
  statusIdle: string
  statusPtt: string
  statusCos: string
  statusFullDuplex: string
  statusReceiving: string
  // Menu colors
  menuBackground: string
  menuHover: string
  menuActive: string
  // Container colors
  containerBg: string
  cardBg: string
  headerBg: string
  footerBg: string
  // Shadow colors
  shadowSm: string
  shadowMd: string
  shadowLg: string
}

export interface Notification {
  id: string
  type: NotificationType
  title: string
  message: string
  timestamp: Date
  duration?: number
  actions?: NotificationAction[]
  persistent?: boolean
  icon?: string
}

export interface NotificationAction {
  label: string
  action: () => void
  style?: 'primary' | 'secondary' | 'danger'
}

export interface Modal {
  id: string
  component: string
  props?: Record<string, any>
  options?: ModalOptions
}

export interface ModalOptions {
  title?: string
  size?: ModalSize
  closable?: boolean
  backdrop?: boolean
  persistent?: boolean
  fullscreen?: boolean
}

export interface TableColumn {
  key: string
  label: string
  sortable?: boolean
  width?: string
  align?: 'left' | 'center' | 'right'
  formatter?: (value: any) => string
  component?: string
}

export interface TableOptions {
  sortable?: boolean
  filterable?: boolean
  pagination?: boolean
  pageSize?: number
  selectable?: boolean
  expandable?: boolean
}

export interface ChartData {
  labels: string[]
  datasets: ChartDataset[]
}

export interface ChartDataset {
  label: string
  data: number[]
  backgroundColor?: string | string[]
  borderColor?: string | string[]
  borderWidth?: number
  fill?: boolean
}

export interface ChartOptions {
  responsive?: boolean
  maintainAspectRatio?: boolean
  plugins?: {
    legend?: {
      display?: boolean
      position?: 'top' | 'bottom' | 'left' | 'right'
    }
    tooltip?: {
      enabled?: boolean
    }
  }
  scales?: {
    x?: {
      display?: boolean
    }
    y?: {
      display?: boolean
      beginAtZero?: boolean
    }
  }
}

export interface MenuItem {
  id: string
  label: string
  icon?: string
  url?: string
  component?: string
  children?: MenuItem[]
  permissions?: string[]
  badge?: string
  active?: boolean
}

export interface Breadcrumb {
  label: string
  url?: string
  active?: boolean
}

// UI component sizes
export type ComponentSize = 'small' | 'medium' | 'large'

// Modal sizes
export type ModalSize = 'small' | 'medium' | 'large' | 'extra-large'

// Notification types
export type NotificationType = 'success' | 'info' | 'warning' | 'error'

// Button variants
export type ButtonVariant = 
  | 'primary'
  | 'secondary' 
  | 'success'
  | 'warning'
  | 'error'
  | 'info'
  | 'outline'
  | 'ghost'

// Input types
export type InputType = 
  | 'text'
  | 'password'
  | 'email'
  | 'number'
  | 'tel'
  | 'url'
  | 'search'
  | 'textarea'
  | 'select'
  | 'checkbox'
  | 'radio'
