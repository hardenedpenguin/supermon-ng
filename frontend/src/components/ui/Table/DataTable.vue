<template>
  <div class="data-table-wrapper">
    <!-- Search and Filters -->
    <div v-if="searchable || filterable" class="table-controls">
      <div v-if="searchable" class="search-control">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search..."
          class="search-input"
        />
      </div>
      
      <div v-if="filterable && filterOptions.length > 0" class="filter-controls">
        <select
          v-for="filter in filterOptions"
          :key="filter.field"
          v-model="activeFilters[filter.field]"
          class="filter-select"
        >
          <option value="">All {{ filter.label }}</option>
          <option
            v-for="option in filter.options"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="table-loading">
      <div class="loading-spinner"></div>
      <p>Loading data...</p>
    </div>

    <!-- Table -->
    <div v-else class="table-container">
      <table class="data-table" :class="tableClasses">
        <thead>
          <tr>
            <th
              v-if="selectable"
              class="select-column"
            >
              <input
                v-if="!singleSelect"
                type="checkbox"
                :checked="allSelected"
                :indeterminate="someSelected && !allSelected"
                @change="toggleSelectAll"
              />
            </th>
            
            <th
              v-for="column in columns"
              :key="column.key"
              :class="[
                'table-header',
                column.align && `text-${column.align}`,
                column.sortable && 'sortable',
                sortColumn === column.key && 'sorted'
              ]"
              :style="column.width && { width: column.width }"
              @click="column.sortable && handleSort(column.key)"
            >
              <div class="header-content">
                <span>{{ column.label }}</span>
                <span
                  v-if="column.sortable"
                  class="sort-indicator"
                  :class="{
                    'sort-asc': sortColumn === column.key && sortDirection === 'asc',
                    'sort-desc': sortColumn === column.key && sortDirection === 'desc'
                  }"
                >
                  ↕️
                </span>
              </div>
            </th>
            
            <th v-if="actions.length > 0" class="actions-column">
              Actions
            </th>
          </tr>
        </thead>
        
        <tbody>
          <tr
            v-for="(item, index) in paginatedData"
            :key="getRowKey(item, index)"
            :class="[
              'table-row',
              selectedItems.includes(getRowKey(item, index)) && 'selected',
              isRowClickable && 'clickable'
            ]"
            @click="handleRowClick(item, index)"
          >
            <td v-if="selectable" class="select-cell">
              <input
                :type="singleSelect ? 'radio' : 'checkbox'"
                :name="singleSelect ? 'table-select' : undefined"
                :checked="selectedItems.includes(getRowKey(item, index))"
                @change="toggleSelectItem(item, index)"
                @click.stop
              />
            </td>
            
            <td
              v-for="column in columns"
              :key="column.key"
              :class="[
                'table-cell',
                column.align && `text-${column.align}`
              ]"
            >
              <!-- Custom component -->
              <component
                v-if="column.component"
                :is="column.component"
                :item="item"
                :value="getNestedValue(item, column.key)"
                :column="column"
              />
              
              <!-- Formatted value -->
              <span v-else-if="column.formatter">
                {{ column.formatter(getNestedValue(item, column.key)) }}
              </span>
              
              <!-- Raw value -->
              <span v-else>
                {{ getNestedValue(item, column.key) }}
              </span>
            </td>
            
            <td v-if="actions.length > 0" class="actions-cell">
              <div class="action-buttons">
                <button
                  v-for="action in actions"
                  :key="action.label"
                  :class="[
                    'action-button',
                    action.variant && `btn-${action.variant}`
                  ]"
                  :disabled="action.disabled && action.disabled(item)"
                  @click.stop="action.handler(item, index)"
                >
                  <span v-if="action.icon" class="action-icon">{{ action.icon }}</span>
                  {{ action.label }}
                </button>
              </div>
            </td>
          </tr>
          
          <!-- Empty State -->
          <tr v-if="paginatedData.length === 0" class="empty-row">
            <td :colspan="totalColumns" class="empty-cell">
              <div class="empty-state">
                <p>{{ emptyMessage || 'No data available' }}</p>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination && totalPages > 1" class="table-pagination">
      <div class="pagination-info">
        Showing {{ startItem }} to {{ endItem }} of {{ filteredData.length }} entries
      </div>
      
      <div class="pagination-controls">
        <button
          class="pagination-button"
          :disabled="currentPage === 1"
          @click="goToPage(1)"
        >
          First
        </button>
        
        <button
          class="pagination-button"
          :disabled="currentPage === 1"
          @click="goToPage(currentPage - 1)"
        >
          Previous
        </button>
        
        <span class="page-info">
          Page {{ currentPage }} of {{ totalPages }}
        </span>
        
        <button
          class="pagination-button"
          :disabled="currentPage === totalPages"
          @click="goToPage(currentPage + 1)"
        >
          Next
        </button>
        
        <button
          class="pagination-button"
          :disabled="currentPage === totalPages"
          @click="goToPage(totalPages)"
        >
          Last
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { TableColumn, TableOptions } from '@/types'

interface TableAction {
  label: string
  icon?: string
  variant?: 'primary' | 'secondary' | 'danger'
  handler: (item: any, index: number) => void
  disabled?: (item: any) => boolean
}

interface FilterOption {
  field: string
  label: string
  options: { value: any; label: string }[]
}

interface Props {
  data: any[]
  columns: TableColumn[]
  options?: TableOptions
  actions?: TableAction[]
  filterOptions?: FilterOption[]
  loading?: boolean
  emptyMessage?: string
  rowKey?: string | ((item: any, index: number) => string)
}

interface Emits {
  (e: 'row-click', item: any, index: number): void
  (e: 'selection-change', selectedItems: any[]): void
  (e: 'sort-change', column: string, direction: 'asc' | 'desc'): void
}

const props = withDefaults(defineProps<Props>(), {
  options: () => ({}),
  actions: () => [],
  filterOptions: () => [],
  loading: false,
  rowKey: 'id'
})

const emit = defineEmits<Emits>()

// Reactive data
const searchQuery = ref('')
const sortColumn = ref<string>('')
const sortDirection = ref<'asc' | 'desc'>('asc')
const currentPage = ref(1)
const selectedItems = ref<string[]>([])
const activeFilters = ref<Record<string, any>>({})

// Computed options
const {
  sortable = true,
  filterable = false,
  searchable = true,
  pagination = true,
  pageSize = 10,
  selectable = false,
  singleSelect = false
} = props.options

// Table computed properties
const tableClasses = computed(() => ({
  'table-sortable': sortable,
  'table-selectable': selectable
}))

const totalColumns = computed(() => {
  let count = props.columns.length
  if (selectable) count++
  if (props.actions.length > 0) count++
  return count
})

const isRowClickable = computed(() => {
  return emit['row-click'] !== undefined
})

// Data filtering and sorting
const filteredData = computed(() => {
  let result = [...props.data]

  // Apply search filter
  if (searchQuery.value && searchable) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(item =>
      props.columns.some(column => {
        const value = getNestedValue(item, column.key)
        return String(value).toLowerCase().includes(query)
      })
    )
  }

  // Apply column filters
  if (filterable) {
    Object.entries(activeFilters.value).forEach(([field, filterValue]) => {
      if (filterValue) {
        result = result.filter(item => getNestedValue(item, field) === filterValue)
      }
    })
  }

  // Apply sorting
  if (sortColumn.value && sortable) {
    result.sort((a, b) => {
      const aValue = getNestedValue(a, sortColumn.value)
      const bValue = getNestedValue(b, sortColumn.value)
      
      let comparison = 0
      if (aValue < bValue) comparison = -1
      if (aValue > bValue) comparison = 1
      
      return sortDirection.value === 'desc' ? -comparison : comparison
    })
  }

  return result
})

// Pagination
const totalPages = computed(() => {
  if (!pagination) return 1
  return Math.ceil(filteredData.value.length / pageSize)
})

const startItem = computed(() => {
  if (!pagination) return 1
  return (currentPage.value - 1) * pageSize + 1
})

const endItem = computed(() => {
  if (!pagination) return filteredData.value.length
  return Math.min(currentPage.value * pageSize, filteredData.value.length)
})

const paginatedData = computed(() => {
  if (!pagination) return filteredData.value
  
  const start = (currentPage.value - 1) * pageSize
  const end = start + pageSize
  return filteredData.value.slice(start, end)
})

// Selection
const allSelected = computed(() => {
  return paginatedData.value.length > 0 && 
         paginatedData.value.every(item => 
           selectedItems.value.includes(getRowKey(item, 0))
         )
})

const someSelected = computed(() => {
  return selectedItems.value.length > 0
})

// Utility functions
const getNestedValue = (obj: any, path: string): any => {
  return path.split('.').reduce((current, key) => current?.[key], obj)
}

const getRowKey = (item: any, index: number): string => {
  if (typeof props.rowKey === 'function') {
    return props.rowKey(item, index)
  }
  return String(getNestedValue(item, props.rowKey) ?? index)
}

// Event handlers
const handleSort = (column: string) => {
  if (sortColumn.value === column) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortColumn.value = column
    sortDirection.value = 'asc'
  }
  
  emit('sort-change', column, sortDirection.value)
}

const handleRowClick = (item: any, index: number) => {
  if (isRowClickable.value) {
    emit('row-click', item, index)
  }
}

const toggleSelectItem = (item: any, index: number) => {
  const key = getRowKey(item, index)
  
  if (singleSelect) {
    selectedItems.value = [key]
  } else {
    const currentIndex = selectedItems.value.indexOf(key)
    if (currentIndex > -1) {
      selectedItems.value.splice(currentIndex, 1)
    } else {
      selectedItems.value.push(key)
    }
  }
  
  emit('selection-change', getSelectedData())
}

const toggleSelectAll = () => {
  if (allSelected.value) {
    selectedItems.value = []
  } else {
    selectedItems.value = paginatedData.value.map((item, index) => 
      getRowKey(item, index)
    )
  }
  
  emit('selection-change', getSelectedData())
}

const getSelectedData = () => {
  return props.data.filter((item, index) => 
    selectedItems.value.includes(getRowKey(item, index))
  )
}

const goToPage = (page: number) => {
  currentPage.value = Math.max(1, Math.min(page, totalPages.value))
}

// Watchers
watch(filteredData, () => {
  // Reset to first page when data changes
  currentPage.value = 1
})

watch(searchQuery, () => {
  // Reset to first page when search changes
  currentPage.value = 1
})
</script>

<style scoped>
.data-table-wrapper {
  background: var(--container-bg);
  border-radius: 0.5rem;
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}

.table-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid var(--border-color);
  flex-wrap: wrap;
  gap: 1rem;
}

.search-input {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 0.375rem;
  background: var(--input-bg, var(--container-bg));
  color: var(--text-color);
  min-width: 200px;
}

.filter-controls {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.filter-select {
  padding: 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 0.375rem;
  background: var(--input-bg, var(--container-bg));
  color: var(--text-color);
}

.table-loading {
  padding: 2rem;
  text-align: center;
}

.loading-spinner {
  width: 2rem;
  height: 2rem;
  border: 2px solid var(--border-color);
  border-top: 2px solid var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.table-container {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.table-header {
  background: var(--table-header-bg, var(--surface-color));
  color: var(--text-color);
  padding: 0.75rem;
  text-align: left;
  font-weight: 600;
  border-bottom: 1px solid #000000;
}

.table-header.sortable {
  cursor: pointer;
  user-select: none;
}

.table-header.sortable:hover {
  background: var(--hover-color, rgba(0, 0, 0, 0.05));
}

.header-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.sort-indicator {
  opacity: 0.5;
  transition: opacity 0.2s;
}

.sort-indicator.sort-asc,
.sort-indicator.sort-desc {
  opacity: 1;
}

.sort-indicator.sort-asc::before {
  content: '↑';
}

.sort-indicator.sort-desc::before {
  content: '↓';
}

.table-row {
  border-bottom: 1px solid var(--border-color);
  transition: background-color 0.15s;
}

.table-row:hover {
  background: var(--hover-color, rgba(0, 0, 0, 0.02));
}

.table-row.selected {
  background: var(--selected-color, rgba(0, 123, 255, 0.1));
}

.table-row.clickable {
  cursor: pointer;
}

.table-cell {
  padding: 0.75rem;
  color: var(--text-color);
}

.text-left { text-align: left; }
.text-center { text-align: center; }
.text-right { text-align: right; }

.select-column,
.actions-column {
  width: 1%;
  white-space: nowrap;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.action-button {
  padding: 0.25rem 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 0.25rem;
  background: var(--button-bg, var(--container-bg));
  color: var(--text-color);
  cursor: pointer;
  font-size: 0.875rem;
  transition: all 0.15s;
}

.action-button:hover:not(:disabled) {
  background: var(--button-hover-bg, var(--hover-color));
}

.action-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.btn-danger {
  background: var(--error-color);
  color: white;
  border-color: var(--error-color);
}

.empty-state {
  padding: 2rem;
  text-align: center;
  color: var(--text-muted, #6c757d);
}

.table-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-top: 1px solid var(--border-color);
  flex-wrap: wrap;
  gap: 1rem;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.pagination-button {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 0.375rem;
  background: var(--button-bg, var(--container-bg));
  color: var(--text-color);
  cursor: pointer;
  transition: all 0.15s;
}

.pagination-button:hover:not(:disabled) {
  background: var(--button-hover-bg, var(--hover-color));
}

.pagination-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-info {
  margin: 0 1rem;
  font-weight: 500;
}

@media (max-width: 768px) {
  .table-controls {
    flex-direction: column;
    align-items: stretch;
  }
  
  .search-input {
    min-width: auto;
  }
  
  .table-pagination {
    flex-direction: column;
    text-align: center;
  }
  
  .pagination-controls {
    justify-content: center;
  }
}
</style>
