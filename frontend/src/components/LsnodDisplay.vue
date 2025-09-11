<template>
  <div class="lsnod-display">
    <div v-if="loading" class="loading">
      <p>Loading lsnod data...</p>
    </div>
    
    <div v-else-if="error" class="error">
      <h3>Error</h3>
      <p>{{ error }}</p>
    </div>
    
    <div v-else-if="data" class="lsnod-content">
      <h3>lsnod Output for Node {{ nodeId }}</h3>
      <p>Total Nodes: {{ data.node_count || 0 }}</p>
      
      <div v-if="data.nodes && data.nodes.length > 0" class="nodes-table">
        <table class="lsnod-table">
          <thead>
            <tr>
              <th>Node</th>
              <th>Description</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="node in data.nodes" :key="node.node_number">
              <td>{{ node.node_number }}</td>
              <td>{{ node.description }}</td>
              <td>{{ node.status }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <div v-else class="no-nodes">
        <p>No nodes found in lsnod output.</p>
      </div>
      
      <div v-if="showRawData" class="raw-data">
        <details>
          <summary>Raw Data</summary>
          <pre>{{ JSON.stringify(data.raw_data, null, 2) }}</pre>
        </details>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/utils/api'

interface LsnodNode {
  node_number: string
  description: string
  status: string
}

interface LsnodData {
  html?: string
  node_count: number
  nodes: LsnodNode[]
  raw_data?: any
}

const route = useRoute()
const nodeId = ref<string>('')
const data = ref<LsnodData | null>(null)
const loading = ref<boolean>(true)
const error = ref<string>('')
const showRawData = ref<boolean>(false)

const loadLsnodData = async () => {
  try {
    loading.value = true
    error.value = ''
    
    const response = await api.get(`/nodes/${nodeId.value}/lsnodes/web`)
    
    if (response.data.success) {
      data.value = response.data.data
    } else {
      error.value = response.data.message || 'Failed to load lsnod data'
    }
  } catch (err: any) {
    console.error('Error loading lsnod data:', err)
    error.value = err.response?.data?.message || 'Failed to load lsnod data'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  nodeId.value = route.params.id as string
  if (nodeId.value) {
    loadLsnodData()
  }
})
</script>

<style scoped>
.lsnod-display {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.loading {
  text-align: center;
  padding: 40px;
}

.error {
  background-color: #fee;
  border: 1px solid #fcc;
  border-radius: 4px;
  padding: 20px;
  color: #c33;
}

.lsnod-content h3 {
  color: #333;
  margin-bottom: 10px;
}

.nodes-table {
  margin: 20px 0;
}

.lsnod-table {
  width: 100%;
  border-collapse: collapse;
  background-color: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.lsnod-table th {
  background-color: #f8f9fa;
  color: #495057;
  font-weight: 600;
  padding: 12px;
  text-align: left;
  border-bottom: 2px solid #dee2e6;
}

.lsnod-table td {
  padding: 12px;
  border-bottom: 1px solid #dee2e6;
}

.lsnod-table tbody tr:hover {
  background-color: #f8f9fa;
}

.lsnod-table tbody tr:last-child td {
  border-bottom: none;
}

.no-nodes {
  text-align: center;
  padding: 40px;
  color: #666;
}

.raw-data {
  margin-top: 30px;
}

.raw-data details {
  background-color: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  padding: 10px;
}

.raw-data summary {
  cursor: pointer;
  font-weight: 600;
  padding: 5px;
}

.raw-data pre {
  background-color: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  padding: 15px;
  overflow-x: auto;
  font-size: 12px;
  margin-top: 10px;
}
</style>
