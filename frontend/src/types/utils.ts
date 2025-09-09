// Utility types and helpers

// Generic utility types
export type Optional<T> = T | undefined
export type Nullable<T> = T | null
export type DeepPartial<T> = {
  [P in keyof T]?: T[P] extends object ? DeepPartial<T[P]> : T[P]
}

// Event handler types
export type EventHandler<T = Event> = (event: T) => void
export type AsyncEventHandler<T = Event> = (event: T) => Promise<void>

// Form types
export interface FormField {
  name: string
  type: string
  label: string
  value?: any
  required?: boolean
  validation?: ValidationRule[]
  options?: SelectOption[]
  placeholder?: string
  disabled?: boolean
  readonly?: boolean
}

export interface ValidationRule {
  type: 'required' | 'email' | 'min' | 'max' | 'pattern' | 'custom'
  value?: any
  message: string
  validator?: (value: any) => boolean
}

export interface SelectOption {
  value: any
  label: string
  disabled?: boolean
  group?: string
}

export interface FormErrors {
  [fieldName: string]: string[]
}

// Date/time utilities
export interface DateRange {
  start: Date
  end: Date
}

export interface TimeInterval {
  value: number
  unit: 'seconds' | 'minutes' | 'hours' | 'days' | 'weeks' | 'months' | 'years'
}

// File handling
export interface FileInfo {
  name: string
  size: number
  type: string
  lastModified: Date
  path?: string
}

export interface UploadProgress {
  loaded: number
  total: number
  percentage: number
}

// Error handling
export interface ErrorInfo {
  message: string
  code?: string | number
  details?: any
  stack?: string
  timestamp?: Date
}

// Search and filtering
export interface SearchOptions {
  query: string
  fields?: string[]
  caseSensitive?: boolean
  exactMatch?: boolean
  fuzzy?: boolean
}

export interface FilterOption {
  field: string
  operator: FilterOperator
  value: any
}

export interface SortOption {
  field: string
  direction: 'asc' | 'desc'
}

// Pagination
export interface PaginationInfo {
  page: number
  pageSize: number
  total: number
  totalPages: number
  hasNext: boolean
  hasPrev: boolean
}

// Cache types
export interface CacheEntry<T> {
  data: T
  timestamp: Date
  ttl?: number
}

export interface CacheOptions {
  ttl?: number
  maxSize?: number
  strategy?: 'lru' | 'fifo' | 'lfu'
}

// Async operations
export type AsyncState = 'idle' | 'loading' | 'success' | 'error'

export interface AsyncResult<T> {
  state: AsyncState
  data?: T
  error?: ErrorInfo
  loading: boolean
}

// Keyboard shortcuts
export interface KeyboardShortcut {
  key: string
  ctrlKey?: boolean
  altKey?: boolean
  shiftKey?: boolean
  metaKey?: boolean
  action: () => void
  description?: string
}

// Filter operators
export type FilterOperator = 
  | 'equals'
  | 'not_equals'
  | 'contains'
  | 'not_contains'
  | 'starts_with'
  | 'ends_with'
  | 'greater_than'
  | 'less_than'
  | 'greater_equal'
  | 'less_equal'
  | 'in'
  | 'not_in'
  | 'is_null'
  | 'is_not_null'

// Generic callback types
export type Callback<T = void> = () => T
export type AsyncCallback<T = void> = () => Promise<T>
export type CallbackWithParam<P, T = void> = (param: P) => T
export type AsyncCallbackWithParam<P, T = void> = (param: P) => Promise<T>
